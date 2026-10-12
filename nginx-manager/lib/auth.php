<?php
/**
 * Autentikasi & session panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Mulai session dengan pengaturan aman.
 */

function auth_start_session(): void
{
    // Di CLI, session tidak ada gunanya. Skip.
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Kalau header sudah terkirim, jangan start session (hindari warning)
    if (headers_sent()) {
        return;
    }

    $config = require __DIR__ . '/../config.php';

    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => $config['session_lifetime'],
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 300) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}
/**
 * Cek apakah user sudah login.
 */
function auth_check(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    auth_start_session();
    return isset($_SESSION['user_id']);
}

/**
 * Ambil data user yang sedang login.
 */
function auth_user(): ?array
{
    if (!auth_check()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
    ];
}

/**
 * Paksa login, redirect kalau belum.
 */
function auth_require(): void
{
    if (!auth_check()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Coba login. Return array [success, message].
 */
function auth_login(string $username, string $password): array
{
    $config = require __DIR__ . '/../config.php';
    $pdo    = panel_db();
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Ambil user
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Tidak ada user → tetap jalankan hash palsu untuk timing attack
    if (!$user) {
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        return [false, 'Username atau password salah.'];
    }

    // Cek lockout
    if ($user['locked_until'] !== null && $user['locked_until'] > time()) {
        $sisa = (int) ceil(($user['locked_until'] - time()) / 60);
        return [false, "Akun terkunci sementara. Coba lagi dalam {$sisa} menit."];
    }

    // Cek password
    if (!password_verify($password, $user['password_hash'])) {
        $failed = (int) $user['failed_count'] + 1;
        $lockedUntil = null;

        if ($failed >= $config['login_max_attempts']) {
            $lockedUntil = time() + $config['lockout_duration'];
            $failed = 0;
        }

        $upd = $pdo->prepare('UPDATE users SET failed_count = ?, locked_until = ? WHERE id = ?');
        $upd->execute([$failed, $lockedUntil, $user['id']]);

        if ($lockedUntil !== null) {
            return [false, 'Terlalu banyak percobaan. Akun terkunci sementara.'];
        }

        $sisa = $config['login_max_attempts'] - $failed;
        return [false, "Username atau password salah. Sisa percobaan: {$sisa}."];
    }

    // Sukses
    $upd = $pdo->prepare('UPDATE users SET failed_count = 0, locked_until = NULL, last_login = ? WHERE id = ?');
    $upd->execute([time(), $user['id']]);

    auth_start_session();
    session_regenerate_id(true);

    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['_created'] = time();

    return [true, 'Login berhasil.'];
}

/**
 * Logout & hancurkan session.
 */
function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * Buat user baru. Return [success, message].
 */
function auth_create_user(string $username, string $password): array
{
    if (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
        return [false, 'Username harus 3-32 karakter, hanya huruf kecil, angka, dan underscore.'];
    }

    if (strlen($password) < 8) {
        return [false, 'Password minimal 8 karakter.'];
    }

    $pdo = panel_db();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hash, time()]);
        return [true, 'User berhasil dibuat.'];
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            return [false, 'Username sudah dipakai.'];
        }
        return [false, 'Gagal membuat user: ' . $e->getMessage()];
    }
}

/**
 * Ganti password user.
 * Return [success, message].
 */
function auth_change_password(string $username, string $newPassword): array
{
    if (strlen($newPassword) < 8) {
        return [false, 'Password minimal 8 karakter.'];
    }

    $pdo = panel_db();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return [false, 'User tidak ditemukan.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $upd = $pdo->prepare('
        UPDATE users
        SET password_hash = ?,
            failed_count = 0,
            locked_until = NULL
        WHERE id = ?
    ');
    $upd->execute([$hash, $user['id']]);

    return [true, 'Password berhasil diubah.'];
}
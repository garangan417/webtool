<?php
/**
 * Proteksi CSRF sederhana.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Ambil token CSRF untuk session ini (buat kalau belum ada).
 */
function csrf_token(): string
{
    auth_start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Cetak hidden input untuk form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Verifikasi token dari POST. Return true kalau valid.
 */
function csrf_verify(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    auth_start_session();

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Paksa verifikasi; kalau gagal, kirim 403 dan exit.
 */
function csrf_require(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!csrf_verify($token)) {
        http_response_code(403);
        exit('CSRF token tidak valid.');
    }
}
<?php
/**
 * Wrapper untuk operasi Nginx (read-only dulu).
 */

declare(strict_types=1);

/**
 * Ambil konfigurasi panel.
 */
function nginx_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

/**
 * Jalankan perintah shell dan kembalikan [exit_code, stdout, stderr].
 */
function nginx_exec(string $command): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        return [1, '', 'Gagal menjalankan perintah.'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [$exitCode, (string) $stdout, (string) $stderr];
}

/**
 * Tes konfigurasi Nginx.
 * Return ['ok' => bool, 'output' => string].
 */
function nginx_test(): array
{
    $config = nginx_config();
    $bin    = escapeshellarg($config['nginx_bin']);

    [$code, $out, $err] = nginx_exec("sudo {$bin} -t 2>&1");

    return [
        'ok'     => $code === 0,
        'output' => trim($out . "\n" . $err),
    ];
}

/**
 * Cek status Nginx.
 * Return ['active' => bool, 'state' => string].
 */
function nginx_status(): array
{
    $config = nginx_config();
    $bin    = escapeshellarg($config['systemctl_bin']);

    [$code, $out, ] = nginx_exec("sudo {$bin} is-active nginx 2>/dev/null");
    $state = trim($out);

    return [
        'active' => $code === 0 && $state === 'active',
        'state'  => $state !== '' ? $state : 'unknown',
    ];
}

/**
 * Ambil versi Nginx.
 */
function nginx_version(): string
{
    $config = nginx_config();
    $bin    = escapeshellarg($config['nginx_bin']);

    [$code, $out, ] = nginx_exec("{$bin} -v 2>&1");

    // Output format: nginx version: nginx/1.24.0
    if (preg_match('#nginx/([0-9.]+)#', $out, $m)) {
        return $m[1];
    }

    return 'unknown';
}

/**
 * Ambil uptime proses Nginx (kalau ada).
 */
function nginx_uptime(): string
{
    $config = nginx_config();
    $bin    = escapeshellarg($config['systemctl_bin']);

    [$code, $out, ] = nginx_exec("{$bin} show nginx --property=ActiveEnterTimestamp 2>/dev/null");

    if (preg_match('/ActiveEnterTimestamp=(.+)/', $out, $m)) {
        $ts = strtotime(trim($m[1]));
        if ($ts !== false) {
            $diff = time() - $ts;
            return nginx_human_duration($diff);
        }
    }

    return 'unknown';
}

/**
 * Format durasi ke bahasa manusia.
 */
function nginx_human_duration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' detik';
    }

    $m = floor($seconds / 60);
    if ($m < 60) {
        return $m . ' menit';
    }

    $h = floor($m / 60);
    $m = $m % 60;
    if ($h < 24) {
        return $h . ' jam ' . $m . ' menit';
    }

    $d = floor($h / 24);
    $h = $h % 24;
    return $d . ' hari ' . $h . ' jam';
}

/**
 * Scan direktori sites-managed dan kembalikan daftar file .conf.
 * Return array of ['name', 'path', 'size', 'mtime'].
 */
function nginx_list_managed_files(): array
{
    $config = nginx_config();
    $dir    = $config['managed_dir'];

    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.conf');
    if ($files === false) {
        return [];
    }

    $result = [];

    foreach ($files as $file) {
        $result[] = [
            'name'  => basename($file),
            'path'  => $file,
            'size'  => filesize($file),
            'mtime' => filemtime($file),
        ];
    }

    usort($result, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

    return $result;
}

/**
 * Parse file konfigurasi vhost sederhana (server block).
 * Return ['server_name', 'root', 'listen', 'ssl'].
 */
function nginx_parse_vhost(string $path): array
{
    $result = [
        'server_name' => '-',
        'root'        => '-',
        'listen'      => [],
        'ssl'         => false,
        'php_socket'  => '-',
    ];

    if (!is_readable($path)) {
        return $result;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return $result;
    }

    // server_name
    if (preg_match('/^\s*server_name\s+([^;]+);/m', $content, $m)) {
        $result['server_name'] = trim($m[1]);
    }

    // root
    if (preg_match('/^\s*root\s+([^;]+);/m', $content, $m)) {
        $result['root'] = trim($m[1]);
    }

    // listen
    if (preg_match_all('/^\s*listen\s+([^;]+);/m', $content, $m)) {
        $result['listen'] = array_map('trim', $m[1]);
    }

    // ssl
    if (preg_match('/\bssl_certificate\b/', $content)) {
        $result['ssl'] = true;
    }

    // php-fpm socket
    if (preg_match('#fastcgi_pass\s+(unix:[^;]+);#', $content, $m)) {
        $result['php_socket'] = trim($m[1]);
    }

    return $result;
}

/* =========================================================
   WRITE OPERATIONS
   Semua fungsi di bawah ini menyentuh sistem. Pastikan
   guardrail (validasi, backup, test, rollback) berjalan.
   ========================================================= */

/**
 * Validasi input situs. Return [valid, errors].
 *
 * @return array{0: bool, 1: array<string,string>}
 */
function nginx_validate_site_input(array $input): array
{
    $config = nginx_config();
    $errors = [];

    // --- Domain ---
    $domain = trim((string) ($input['server_name'] ?? ''));
    if ($domain === '') {
        $errors['server_name'] = 'Domain wajib diisi.';
    } elseif (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*$/', $domain)) {
        $errors['server_name'] = 'Format domain tidak valid. Contoh: toko.example.com';
    } elseif (strlen($domain) > 253) {
        $errors['server_name'] = 'Domain terlalu panjang.';
    }

    // --- Root ---
    $root = trim((string) ($input['root'] ?? ''));
    if ($root === '') {
        $errors['root'] = 'Document root wajib diisi.';
    } elseif (!str_starts_with($root, '/')) {
        $errors['root'] = 'Document root harus berupa path absolut.';
    } elseif (str_contains($root, '..')) {
        $errors['root'] = 'Document root tidak boleh mengandung "..".';
    } else {
        // Cek apakah berada di dalam allowed_roots
        $allowed = false;
        foreach ($config['allowed_roots'] as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($root === $prefix || str_starts_with($root, $prefix . '/')) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            $errors['root'] = 'Document root harus berada di dalam: ' . implode(', ', $config['allowed_roots']);
        }
    }

    // --- PHP Socket ---
    $php = trim((string) ($input['php'] ?? ''));
    if ($php === '' || !array_key_exists($php, $config['php_fpm_sockets'])) {
        $errors['php'] = 'Pilih versi PHP-FPM yang valid.';
    }

    return [empty($errors), $errors];
}

/**
 * Susun isi file vhost dari input.
 *
 * @param array{server_name:string, root:string, php:string} $input
 */
function nginx_build_vhost(array $input): string
{
    $config = nginx_config();

    $domain = $input['server_name'];
    $root   = rtrim($input['root'], '/');
    $socket = $config['php_fpm_sockets'][$input['php']] ?? '';

    $createdAt = date('Y-m-d H:i:s');

    // ---------- Blok HTTP ----------
    $http = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};

    root {$root};
    index index.php index.html index.htm;

    access_log /var/log/nginx/{$domain}.access.log;
    error_log  /var/log/nginx/{$domain}.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass {$socket};
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX;

    $header = <<<HEADER
# ============================================================
# Managed by Nginx Manager
# Domain    : {$domain}
# Created   : {$createdAt}
# ============================================================
HEADER;

    return $header . "\n\n" . $http . "\n";
}

/**
 * Tulis vhost baru dengan guardrail lengkap.
 *
 * Langkah:
 *   1. Backup file lama (kalau ada)
 *   2. Tulis file baru
 *   3. nginx -t
 *   4. Kalau gagal → rollback, return error
 *   5. Kalau sukses → reload, return sukses
 *
 * @return array{ok: bool, message: string, backup: ?string}
 */
function nginx_write_vhost(string $filename, string $content, string $action = 'site.create'): array
{
    $config = nginx_config();
    $path   = $config['managed_dir'] . '/' . $filename;

    require_once __DIR__ . '/backup.php';
    require_once __DIR__ . '/logger.php';

    // 1. Backup file lama kalau ada
    $backupPath = null;
    if (is_file($path)) {
        $backupPath = backup_file($path);
        if ($backupPath === null) {
            log_activity($action . '.fail', $filename, ['reason' => 'backup gagal']);
            return ['ok' => false, 'message' => 'Gagal membuat backup file lama.', 'backup' => null];
        }
    }

    // 2. Tulis file baru
    $written = @file_put_contents($path, $content);
    if ($written === false) {
        log_activity($action . '.fail', $filename, ['reason' => 'write gagal']);
        return ['ok' => false, 'message' => 'Gagal menulis file vhost. Cek izin folder.', 'backup' => $backupPath];
    }

    // 3. Test konfigurasi
    $test = nginx_test();

    // 4. Kalau gagal → rollback
    if (!$test['ok']) {
        if ($backupPath !== null) {
            restore_backup($backupPath, $path);
        } else {
            // File baru, tidak ada backup → hapus saja
            @unlink($path);
        }

        log_activity($action . '.rollback', $filename, [
            'reason' => 'nginx -t gagal',
            'output' => $test['output'],
        ]);

        return [
            'ok'      => false,
            'message' => "Konfigurasi tidak valid, perubahan dibatalkan.\n\n" . $test['output'],
            'backup'  => $backupPath,
        ];
    }

    // 5. Reload Nginx
    $reload = nginx_reload();
    if (!$reload['ok']) {
        log_activity($action . '.reload_fail', $filename, ['output' => $reload['output']]);
        return ['ok' => false, 'message' => 'Konfigurasi valid tapi reload gagal: ' . $reload['output'], 'backup' => $backupPath];
    }

    log_activity($action, $filename, ['domain' => $filename, 'backup' => $backupPath]);

    return ['ok' => true, 'message' => 'Situs berhasil disimpan dan Nginx di-reload.', 'backup' => $backupPath];
}

/**
 * Hapus vhost dengan backup.
 *
 * @return array{ok: bool, message: string, backup: ?string}
 */
function nginx_delete_vhost(string $filename): array
{
    $config = nginx_config();
    $path   = $config['managed_dir'] . '/' . $filename;

    require_once __DIR__ . '/backup.php';
    require_once __DIR__ . '/logger.php';

    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'File tidak ditemukan.', 'backup' => null];
    }

    // Backup dulu
    $backupPath = backup_file($path);
    if ($backupPath === null) {
        return ['ok' => false, 'message' => 'Gagal membuat backup sebelum hapus.', 'backup' => null];
    }

    // Hapus file
    if (!@unlink($path)) {
        log_activity('site.delete.fail', $filename, ['reason' => 'unlink gagal']);
        return ['ok' => false, 'message' => 'Gagal menghapus file.', 'backup' => $backupPath];
    }

    // Test konfigurasi
    $test = nginx_test();
    if (!$test['ok']) {
        // Rollback: restore backup
        restore_backup($backupPath, $path);
        log_activity('site.delete.rollback', $filename, ['output' => $test['output']]);
        return ['ok' => false, 'message' => "Konfigurasi jadi tidak valid, penghapusan dibatalkan.\n\n" . $test['output'], 'backup' => $backupPath];
    }

    // Reload
    $reload = nginx_reload();
    if (!$reload['ok']) {
        restore_backup($backupPath, $path);
        log_activity('site.delete.reload_fail', $filename, ['output' => $reload['output']]);
        return ['ok' => false, 'message' => 'Reload gagal, penghapusan dibatalkan: ' . $reload['output'], 'backup' => $backupPath];
    }

    log_activity('site.delete', $filename, ['backup' => $backupPath]);

    return ['ok' => true, 'message' => 'Situs berhasil dihapus dan Nginx di-reload.', 'backup' => $backupPath];
}

/**
 * Reload Nginx.
 * Return ['ok' => bool, 'output' => string].
 */
function nginx_reload(): array
{
    $config = nginx_config();
    $bin    = escapeshellarg($config['systemctl_bin']);

    [$code, $out, $err] = nginx_exec("sudo {$bin} reload nginx 2>&1");

    return [
        'ok'     => $code === 0,
        'output' => trim($out . "\n" . $err),
    ];
}
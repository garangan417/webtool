<?php
/**
 * Konfigurasi Panel Nginx Manager
 *
 * File ini menyimpan semua pengaturan panel.
 * Jangan commit ke repository publik kalau ada data sensitif.
 */

declare(strict_types=1);

return [
    // Identitas panel
    'app_name'      => 'Nginx Manager',
    'app_version'   => '0.1.0',

    // Path absolut ke direktori panel (folder tempat file ini berada)
    'base_path'     => __DIR__,

    // Path ke direktori vhost yang dikelola panel
    'managed_dir'   => '/etc/nginx/sites-managed',

    // Path ke direktori backup vhost
    'backup_dir'    => __DIR__ . '/storage/backups',

    // Path ke file SQLite untuk data panel
    'db_path'       => __DIR__ . '/storage/panel.sqlite',

    // Path ke log aktivitas
    'log_path'      => __DIR__ . '/storage/logs/panel.log',

    // Nama cookie session
    'session_name'  => 'nginx_manager_sess',

    // Durasi session (detik). 7200 = 2 jam
    'session_lifetime' => 7200,

    // Rate limit login: max percobaan per window
    'login_max_attempts' => 5,
    'login_window'       => 900,  // 15 menit

    // Path binary (sesuai hasil `which`)
    'nginx_bin'     => '/usr/sbin/nginx',
    'systemctl_bin' => '/usr/bin/systemctl',

    // Socket PHP-FPM yang tersedia untuk dipilih user
    // Sesuaikan dengan yang ada di server Anda
    'php_fpm_sockets' => [
        'php8.3' => 'unix:/run/php/php8.3-fpm.sock',
        'php8.2' => 'unix:/run/php/php8.2-fpm.sock',
    ],

    // Default PHP-FPM version untuk situs baru
    'default_php'   => 'php8.3',

    // Direktori yang diizinkan sebagai document root situs
    // Panel akan menolak root di luar daftar ini
    'allowed_roots' => [
        '/var/www',
    ],

    // Setelah berapa kali gagal login berturut-turut, akun dikunci sementara
    'lockout_duration' => 900,  // 15 menit

    // Debug mode: kalau true, error ditampilkan. Matikan di production
    'debug' => false,
];
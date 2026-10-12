<?php
/**
 * Backup & restore file konfigurasi vhost.
 */

declare(strict_types=1);

/**
 * Backup file ke direktori storage/backups/.
 * Return path backup kalau sukses, null kalau gagal.
 */
function backup_file(string $sourcePath): ?string
{
    if (!is_file($sourcePath)) {
        return null;
    }

    $config = require __DIR__ . '/../config.php';
    $dir    = $config['backup_dir'];

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $name    = basename($sourcePath);
    $stamp   = date('Ymd-His');
    $target  = "{$dir}/{$name}.{$stamp}.bak";

    // Hindari tabrakan nama
    $i = 1;
    while (file_exists($target)) {
        $target = "{$dir}/{$name}.{$stamp}.{$i}.bak";
        $i++;
    }

    if (!@copy($sourcePath, $target)) {
        return null;
    }

    // Simpan metadata
    $meta = [
        'source'     => $sourcePath,
        'backup'     => $target,
        'size'       => filesize($target),
        'created_at' => time(),
        'sha256'     => hash_file('sha256', $target),
    ];

    @file_put_contents($target . '.json', json_encode($meta, JSON_PRETTY_PRINT));

    return $target;
}

/**
 * Restore file dari backup.
 * Return true kalau sukses.
 */
function restore_backup(string $backupPath, string $targetPath): bool
{
    if (!is_file($backupPath)) {
        return false;
    }

    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        return false;
    }

    return @copy($backupPath, $targetPath);
}

/**
 * Daftar backup yang tersedia, diurutkan terbaru dulu.
 *
 * @return array<int, array{file: string, name: string, size: int, mtime: int}>
 */
function backup_list(?string $filterName = null): array
{
    $config = require __DIR__ . '/../config.php';
    $dir    = $config['backup_dir'];

    if (!is_dir($dir)) {
        return [];
    }

    $pattern = $filterName !== null
        ? $dir . '/' . $filterName . '.*.bak'
        : $dir . '/*.bak';

    $files = glob($pattern);
    if ($files === false) {
        return [];
    }

    $result = [];
    foreach ($files as $file) {
        $result[] = [
            'file'  => $file,
            'name'  => basename($file),
            'size'  => filesize($file),
            'mtime' => filemtime($file),
        ];
    }

    usort($result, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $result;
}

/**
 * Hapus backup lebih tua dari N hari.
 */
function backup_prune(int $days = 30): int
{
    $config = require __DIR__ . '/../config.php';
    $dir    = $config['backup_dir'];

    if (!is_dir($dir)) {
        return 0;
    }

    $cutoff = time() - ($days * 86400);
    $count  = 0;

    foreach (glob($dir . '/*.bak') ?: [] as $file) {
        if (filemtime($file) < $cutoff) {
            if (@unlink($file)) {
                @unlink($file . '.json');
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Format ukuran byte ke human-readable.
 */
function backup_human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, 1) . ' ' . $units[$i];
}
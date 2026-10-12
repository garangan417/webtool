<?php
/**
 * Logger aktivitas panel.
 * Menyimpan ke SQLite (activity_log) + file log.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Catat aktivitas ke database + file.
 *
 * @param string      $action  Aksi singkat, misal: 'site.create', 'site.delete'
 * @param string|null $target  Target aksi, misal: 'toko.conf'
 * @param array|null  $detail  Detail tambahan (akan di-json-encode)
 */
function log_activity(string $action, ?string $target = null, ?array $detail = null): void
{
    $user = auth_user();
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'cli';

    $userId   = $user['id'] ?? null;
    $username = $user['username'] ?? 'system';

    try {
        $pdo  = panel_db();
        $stmt = $pdo->prepare('
            INSERT INTO activity_log
                (user_id, username, action, target, detail, ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $username,
            $action,
            $target,
            $detail !== null ? json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            $ip,
            time(),
        ]);
    } catch (Throwable $e) {
        // Jangan sampai logging bikin panel crash
    }

    // Tulis juga ke file log untuk audit eksternal
    log_to_file($action, $target, $detail, $username, $ip);
}

/**
 * Tulis log ke file teks (append).
 */
function log_to_file(string $action, ?string $target, ?array $detail, string $username, string $ip): void
{
    $config = require __DIR__ . '/../config.php';
    $path   = $config['log_path'];

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = sprintf(
        "[%s] %s | %s | action=%s | target=%s | ip=%s | detail=%s\n",
        date('Y-m-d H:i:s'),
        $username,
        'INFO',
        $action,
        $target ?? '-',
        $ip,
        $detail !== null ? json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '-'
    );

    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Ambil log aktivitas terbaru dari database.
 *
 * @return array<int, array>
 */
function log_recent(int $limit = 100): array
{
    $pdo  = panel_db();
    $stmt = $pdo->prepare('
        SELECT id, username, action, target, detail, ip, created_at
        FROM activity_log
        ORDER BY created_at DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Hapus log lebih lama dari N hari (housekeeping).
 */
function log_prune(int $days = 90): int
{
    $pdo   = panel_db();
    $cutoff = time() - ($days * 86400);
    $stmt  = $pdo->prepare('DELETE FROM activity_log WHERE created_at < ?');
    $stmt->execute([$cutoff]);

    return $stmt->rowCount();
}
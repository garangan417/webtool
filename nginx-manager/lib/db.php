<?php
/**
 * Koneksi SQLite + inisialisasi skema panel.
 */

declare(strict_types=1);

/**
 * Ambil koneksi PDO ke SQLite panel (singleton).
 */
function panel_db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config.php';
    $dbPath = $config['db_path'];

    // Pastikan folder storage ada
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Aktifkan foreign key + WAL untuk performa
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    panel_db_migrate($pdo);

    return $pdo;
}

/**
 * Buat tabel-tabel yang dibutuhkan kalau belum ada.
 */
function panel_db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at    INTEGER NOT NULL,
            last_login    INTEGER,
            failed_count  INTEGER NOT NULL DEFAULT 0,
            locked_until  INTEGER
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS activity_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER,
            username   TEXT,
            action     TEXT NOT NULL,
            target     TEXT,
            detail     TEXT,
            ip         TEXT,
            created_at INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_created ON activity_log(created_at DESC)');
}
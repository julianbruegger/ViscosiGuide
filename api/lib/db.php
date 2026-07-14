<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO connection (MariaDB/MySQL in production, SQLite for local dev).
 * Uses prepared statements everywhere; exceptions on error; no emulated prepares.
 */
function vg_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = vg_config()['db'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (($db['driver'] ?? 'mysql') === 'sqlite') {
        $path = $db['sqlite_path'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            (int) ($db['port'] ?? 3306),
            $db['name']
        );
        $pdo = new PDO($dsn, $db['user'], $db['password'], $options);
    }

    return $pdo;
}

/** True when the active connection is SQLite (used for driver-specific SQL). */
function vg_db_is_sqlite(): bool
{
    return (vg_config()['db']['driver'] ?? 'mysql') === 'sqlite';
}

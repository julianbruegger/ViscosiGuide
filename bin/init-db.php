<?php
declare(strict_types=1);

/**
 * Local development helper — initialise the database.
 *
 *   php bin/init-db.php          # create tables (driver from config/env)
 *   php bin/init-db.php --seed   # also load demo food spots
 *
 * For MariaDB/MySQL, prefer applying db/schema.sql directly (e.g. via phpMyAdmin).
 * This script is primarily for the SQLite dev database so flows can be verified
 * without a DB server. It keeps a SQLite-compatible copy of the schema in sync.
 */

require_once __DIR__ . '/../api/lib/config.php';
require_once __DIR__ . '/../api/lib/db.php';

$seed = in_array('--seed', $argv, true);
$db = vg_db();

if (vg_db_is_sqlite()) {
    $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  email_verified INTEGER NOT NULL DEFAULT 0,
  verify_token_hash TEXT,
  verify_expires TEXT,
  reset_token_hash TEXT,
  reset_expires TEXT,
  notify_new_buddy INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS food_spots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT,
  category TEXT NOT NULL DEFAULT 'other',
  lat REAL NOT NULL,
  lng REAL NOT NULL,
  address TEXT,
  logo TEXT,
  location_url TEXT,
  price_level INTEGER NOT NULL DEFAULT 2,
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS ratings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  spot_id INTEGER NOT NULL REFERENCES food_spots(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  rating INTEGER NOT NULL,
  price_rating INTEGER NOT NULL,
  bang_for_buck INTEGER NOT NULL,
  comment TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (spot_id, user_id)
);
CREATE TABLE IF NOT EXISTS buddy_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL DEFAULT 'lunch',
  title TEXT NOT NULL,
  craving TEXT,
  spot_id INTEGER REFERENCES food_spots(id) ON DELETE SET NULL,
  desired_time TEXT,
  location_note TEXT,
  status TEXT NOT NULL DEFAULT 'open',
  expires_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS grill_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_id INTEGER NOT NULL REFERENCES buddy_requests(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  choice TEXT NOT NULL DEFAULT 'beef',
  custom_text TEXT,
  bring_own INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (request_id, user_id)
);
CREATE TABLE IF NOT EXISTS buddy_participants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_id INTEGER NOT NULL REFERENCES buddy_requests(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (request_id, user_id)
);
CREATE TABLE IF NOT EXISTS buddy_proposals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_id INTEGER NOT NULL REFERENCES buddy_requests(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  spot_id INTEGER REFERENCES food_spots(id) ON DELETE SET NULL,
  message TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS rate_limits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  bucket TEXT NOT NULL,
  attempt_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);
    echo "SQLite schema ready.\n";
} else {
    $sql = file_get_contents(__DIR__ . '/../db/schema.sql');
    $db->exec($sql);
    echo "MySQL schema applied.\n";
}

// Idempotent column top-up for databases created before the grill/logo update.
// (CREATE TABLE IF NOT EXISTS above leaves pre-existing tables untouched.)
vg_add_column_if_missing($db, 'food_spots', 'logo', "VARCHAR(16) DEFAULT NULL");
vg_add_column_if_missing($db, 'food_spots', 'location_url', "VARCHAR(500) DEFAULT NULL");
vg_add_column_if_missing($db, 'buddy_requests', 'type', "VARCHAR(16) NOT NULL DEFAULT 'lunch'");
vg_add_column_if_missing($db, 'buddy_requests', 'expires_at', "DATETIME DEFAULT NULL");

/** Add a column only when it isn't already present (works for SQLite + MySQL). */
function vg_add_column_if_missing(PDO $db, string $table, string $column, string $definition): void
{
    if (vg_db_is_sqlite()) {
        $cols = $db->query("PRAGMA table_info($table)")->fetchAll();
        foreach ($cols as $c) {
            if (($c['name'] ?? null) === $column) {
                return;
            }
        }
    } else {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        if ($stmt->fetch()) {
            return;
        }
    }
    $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    echo "Added column $table.$column.\n";
}

if ($seed) {
    $count = (int) $db->query('SELECT COUNT(*) FROM food_spots')->fetchColumn();
    if ($count === 0) {
        $seedSql = file_get_contents(__DIR__ . '/../db/seed.sql');
        $db->exec($seedSql);
        echo "Seed data loaded.\n";
    } else {
        echo "Spots already present — skipping seed.\n";
    }
}

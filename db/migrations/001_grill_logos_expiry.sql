-- ViscosiGuide migration 001 — logos & location links, grill offers, buddy expiry.
-- Apply once on an EXISTING MariaDB/MySQL database (fresh installs get this from
-- db/schema.sql automatically). Run via phpMyAdmin or:  mysql < db/migrations/001_grill_logos_expiry.sql
--
-- Safe to re-run: each ALTER uses IF NOT EXISTS (MariaDB 10.5+ / MySQL 8.0+).

ALTER TABLE food_spots
  ADD COLUMN IF NOT EXISTS logo         VARCHAR(16)  DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS location_url VARCHAR(500) DEFAULT NULL AFTER logo;

ALTER TABLE buddy_requests
  ADD COLUMN IF NOT EXISTS type       VARCHAR(16) NOT NULL DEFAULT 'lunch' AFTER user_id,
  ADD COLUMN IF NOT EXISTS expires_at DATETIME    DEFAULT NULL AFTER status;

ALTER TABLE buddy_requests
  ADD INDEX IF NOT EXISTS idx_buddy_expires (expires_at);

CREATE TABLE IF NOT EXISTS grill_orders (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  choice      VARCHAR(16)  NOT NULL DEFAULT 'beef',
  custom_text VARCHAR(120) DEFAULT NULL,
  bring_own   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grill_order (request_id, user_id),
  CONSTRAINT fk_order_req  FOREIGN KEY (request_id) REFERENCES buddy_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

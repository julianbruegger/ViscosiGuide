-- ViscosiGuide — MariaDB / MySQL schema
-- Apply once on HostPoint via phpMyAdmin (or `mysql < db/schema.sql`).
-- The local dev helper `bin/init-db.php` adapts this file for SQLite.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email             VARCHAR(255) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  display_name      VARCHAR(80)  NOT NULL,
  email_verified    TINYINT(1)   NOT NULL DEFAULT 0,
  verify_token_hash CHAR(64)     DEFAULT NULL,
  verify_expires    DATETIME     DEFAULT NULL,
  reset_token_hash  CHAR(64)     DEFAULT NULL,
  reset_expires     DATETIME     DEFAULT NULL,
  notify_new_buddy  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS food_spots (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  description  TEXT         DEFAULT NULL,
  category     VARCHAR(40)  NOT NULL DEFAULT 'other',
  lat          DECIMAL(10,7) NOT NULL,
  lng          DECIMAL(10,7) NOT NULL,
  address      VARCHAR(255) DEFAULT NULL,
  logo         VARCHAR(16)  DEFAULT NULL,   -- emoji "logo" (kept tiny; CSP forbids remote images)
  location_url VARCHAR(500) DEFAULT NULL,   -- external map / website link for the location
  price_level  TINYINT      NOT NULL DEFAULT 2,
  created_by   INT UNSIGNED DEFAULT NULL,
  status       VARCHAR(16)  NOT NULL DEFAULT 'active',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_spots_geo (lat, lng),
  KEY idx_spots_category (category),
  CONSTRAINT fk_spots_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ratings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  spot_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  rating        TINYINT      NOT NULL,
  price_rating  TINYINT      NOT NULL,
  bang_for_buck TINYINT      NOT NULL,
  comment       VARCHAR(600) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rating_user_spot (spot_id, user_id),
  CONSTRAINT fk_rating_spot FOREIGN KEY (spot_id) REFERENCES food_spots (id) ON DELETE CASCADE,
  CONSTRAINT fk_rating_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buddy_requests (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  type          VARCHAR(16)  NOT NULL DEFAULT 'lunch',  -- 'lunch' or 'grill'
  title         VARCHAR(120) NOT NULL,
  craving       VARCHAR(120) DEFAULT NULL,
  spot_id       INT UNSIGNED DEFAULT NULL,
  desired_time  DATETIME     DEFAULT NULL,
  location_note VARCHAR(160) DEFAULT NULL,
  status        VARCHAR(16)  NOT NULL DEFAULT 'open',
  expires_at    DATETIME     DEFAULT NULL,             -- offer auto-expires at end of business day
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_buddy_status (status),
  KEY idx_buddy_expires (expires_at),
  CONSTRAINT fk_buddy_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_buddy_spot FOREIGN KEY (spot_id) REFERENCES food_spots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Food orders for a grill request: what each participant wants on the grill.
CREATE TABLE IF NOT EXISTS grill_orders (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  choice      VARCHAR(16)  NOT NULL DEFAULT 'beef',   -- 'beef' | 'pork' | 'veg' | 'other'
  custom_text VARCHAR(120) DEFAULT NULL,              -- free-text when choice = 'other'
  bring_own   TINYINT(1)   NOT NULL DEFAULT 0,        -- 1 = "I'm bringing it myself"
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grill_order (request_id, user_id),
  CONSTRAINT fk_order_req  FOREIGN KEY (request_id) REFERENCES buddy_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buddy_participants (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participant (request_id, user_id),
  CONSTRAINT fk_part_req FOREIGN KEY (request_id) REFERENCES buddy_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_part_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buddy_proposals (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  spot_id    INT UNSIGNED DEFAULT NULL,
  message    VARCHAR(400) DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_prop_req FOREIGN KEY (request_id) REFERENCES buddy_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_prop_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_prop_spot FOREIGN KEY (spot_id) REFERENCES food_spots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple DB-backed rate limiting (login / register / reset attempts).
CREATE TABLE IF NOT EXISTS rate_limits (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket      VARCHAR(160) NOT NULL,
  attempt_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rl_bucket (bucket, attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

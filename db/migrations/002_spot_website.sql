-- ViscosiGuide migration 002 — company website per spot (brand logo source).
-- The frontend derives each spot's brand logo from its website's favicon, so the
-- strict image CSP is opened to a single icon host (see .htaccess / BaseLayout).
-- Apply once on an EXISTING MariaDB/MySQL database (fresh installs get this from
-- db/schema.sql).  Run:  mysql < db/migrations/002_spot_website.sql

ALTER TABLE food_spots
  ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT NULL AFTER location_url;

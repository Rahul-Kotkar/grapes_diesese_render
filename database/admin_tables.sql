-- ─────────────────────────────────────────────────────────────────────────────
-- admin_tables.sql
-- Creates the farm_users table for admin panel user management.
-- Run ONCE in phpMyAdmin.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `farm_users` (
  `id`         INT           NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100)  NOT NULL,
  `status`     TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Farm users managed via the admin panel';

-- Seed default users (Farm1 and farm2)
INSERT INTO `farm_users` (`username`, `status`) VALUES
  ('Farm1',           0),
  ('farm2',           0);

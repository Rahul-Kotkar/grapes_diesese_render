-- ─────────────────────────────────────────────────────────────────────────────
-- FULL DATABASE SCHEMA FOR GRAPES DISEASE MONITORING
-- Run this complete script ONCE in your MySQL Query Console / phpMyAdmin
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. Create sensor_data table with ML columns
CREATE TABLE IF NOT EXISTS `sensor_data` (
    `id`           INT            NOT NULL AUTO_INCREMENT,
    `api_key`      VARCHAR(50)    NOT NULL,
    `user_id`      INT            NOT NULL,
    `temperature`  FLOAT          NOT NULL,
    `humidity`     FLOAT          NOT NULL,
    `sunlight`     FLOAT          NOT NULL,
    `rainfall`     FLOAT          NOT NULL,
    `leaf_wetness` FLOAT          NOT NULL,
    `dsi`          FLOAT          NULL DEFAULT NULL,
    `risk_level`   VARCHAR(20)    NULL DEFAULT NULL,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id`   (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create farm_users table
CREATE TABLE IF NOT EXISTS `farm_users` (
  `id`         INT           NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100)  NOT NULL,
  `status`     TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed default admin & farm users
INSERT INTO `farm_users` (`username`, `status`) VALUES
  ('Admin', 0),
  ('Farm1', 0),
  ('farm2', 0),
  ('Test',  0)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- ─────────────────────────────────────────────────────────────────────────────
-- sensor_data.sql
-- IoT Sensor Monitoring System — Database Schema
--
-- Run this file once in your InfinityFree phpMyAdmin to create the table.
-- Make sure to select the correct database first (USE `your_db_name`;)
-- ─────────────────────────────────────────────────────────────────────────────

-- Use your actual database name; replace `if0_xxxxxxxx_sensordb` accordingly.
-- Uncomment the line below if you want the SQL file to auto-select the database:
-- USE `if0_xxxxxxxx_sensordb`;

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: sensor_data
-- Stores environmental readings sent by ESP32 IoT devices.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `sensor_data` (

    -- Primary key — auto-incremented unique row identifier
    `id`           INT            NOT NULL AUTO_INCREMENT,

    -- The API key used when the reading was submitted (for audit trail)
    `api_key`      VARCHAR(50)    NOT NULL,

    -- Identifier of the user/farm associated with this device
    `user_id`      INT            NOT NULL,

    -- Ambient temperature in degrees Celsius (°C)
    `temperature`  FLOAT          NOT NULL,

    -- Relative humidity in percentage (%)
    `humidity`     FLOAT          NOT NULL,

    -- Sunlight intensity (e.g. hours of sunlight or lux — device-dependent)
    `sunlight`     FLOAT          NOT NULL,

    -- Rainfall amount in millimetres (mm)
    `rainfall`     FLOAT          NOT NULL,

    -- Leaf wetness index (device-specific scale)
    `leaf_wetness` FLOAT          NOT NULL,

    -- Timestamp automatically set to the server time when a row is inserted
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Index on user_id speeds up queries like "get all readings for user X"
    INDEX `idx_user_id`   (`user_id`),

    -- Index on created_at speeds up time-range queries (dashboards, charts)
    INDEX `idx_created_at` (`created_at`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores IoT sensor readings from ESP32 devices';

-- ─────────────────────────────────────────────────────────────────────────────
-- VERIFICATION
-- After importing, run the following to confirm the table was created:
--   DESCRIBE sensor_data;
-- ─────────────────────────────────────────────────────────────────────────────

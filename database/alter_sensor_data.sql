-- ─────────────────────────────────────────────────────────────────────────────
-- alter_sensor_data.sql
-- Adds ML prediction columns to sensor_data table.
-- Run ONCE in phpMyAdmin after the base table already exists.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `sensor_data`
  ADD COLUMN `dsi`        FLOAT        NULL DEFAULT NULL
    COMMENT 'Disease Severity Index returned by the ML API',
  ADD COLUMN `risk_level` VARCHAR(20)  NULL DEFAULT NULL
    COMMENT 'Risk level: Low / Medium / High';

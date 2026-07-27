-- ─────────────────────────────────────────────────────────────────────────────
-- add_email_column.sql
-- Adds email column to farm_users table for notifications.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `farm_users`
  ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Email address for notifications';

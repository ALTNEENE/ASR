-- ============================================================
-- Migration: report_shares table
-- Run in phpMyAdmin on database: project_db
-- Date: 2026-02-25
-- ============================================================

CREATE TABLE IF NOT EXISTS `report_shares` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED     NOT NULL,
  `token`             CHAR(32)         NOT NULL,
  `selected_ids_text` TEXT             NOT NULL COMMENT 'Comma-separated glucose_readings IDs',
  `date_from`         DATE             NULL,
  `date_to`           DATE             NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`        DATETIME         NOT NULL,
  `revoked_at`        DATETIME         NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`      (`token`),
  KEY       `idx_user_id`    (`user_id`),
  KEY       `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

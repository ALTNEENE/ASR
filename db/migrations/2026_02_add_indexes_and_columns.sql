-- ============================================================
-- Migration: Add indexes, validate data, and cleanup
-- Safe for MariaDB / phpMyAdmin
-- Run this in phpMyAdmin SQL tab
-- ============================================================

-- 1) Add index on glucose_readings(user_id, reading_date) if not exists
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'glucose_readings'
               AND INDEX_NAME = 'idx_gr_user_date');
SET @sql := IF(@idx = 0,
    'ALTER TABLE `glucose_readings` ADD INDEX `idx_gr_user_date` (`user_id`, `reading_date`)',
    'SELECT "idx_gr_user_date already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Add index on glucose_readings(classification, reading_context)
SET @idx2 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'glucose_readings'
                AND INDEX_NAME = 'idx_gr_classification');
SET @sql2 := IF(@idx2 = 0,
    'ALTER TABLE `glucose_readings` ADD INDEX `idx_gr_classification` (`classification`, `reading_context`)',
    'SELECT "idx_gr_classification already exists"');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 3) Add index on patient_data(user_id) if not exists
SET @idx3 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'patient_data'
                AND INDEX_NAME = 'idx_pd_user');
SET @sql3 := IF(@idx3 = 0,
    'ALTER TABLE `patient_data` ADD INDEX `idx_pd_user` (`user_id`)',
    'SELECT "idx_pd_user already exists"');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 4) Add index on health_tips(is_active, priority)
SET @idx4 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'health_tips'
                AND INDEX_NAME = 'idx_ht_active_priority');
SET @sql4 := IF(@idx4 = 0,
    'ALTER TABLE `health_tips` ADD INDEX `idx_ht_active_priority` (`is_active`, `priority`)',
    'SELECT "idx_ht_active_priority already exists"');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- 5) Cleanup: delete readings with invalid values (<=0 or >600 mg/dL or <1.1 mmol/L)
DELETE FROM `glucose_readings`
WHERE (reading_unit = 'mg' AND (reading_value <= 0 OR reading_value > 600))
   OR (reading_unit = 'mmol' AND (reading_value <= 0 OR reading_value > 33.4));

-- 6) Fix stored classification for remaining rows using stored context only
-- (Best done via PHP classify_glucose, but as a SQL approximation:)
UPDATE `glucose_readings`
SET classification = 'منخفض'
WHERE reading_unit = 'mg'
  AND reading_value < 70
  AND (classification IS NULL OR classification NOT IN ('منخفض','طبيعي','مرتفع'));

UPDATE `glucose_readings`
SET classification = 'مرتفع'
WHERE reading_unit = 'mg'
  AND reading_context = 'post'
  AND reading_value >= 140
  AND (classification IS NULL OR classification NOT IN ('منخفض','طبيعي','مرتفع'));

UPDATE `glucose_readings`
SET classification = 'مرتفع'
WHERE reading_unit = 'mg'
  AND reading_context = 'fasting'
  AND reading_value >= 111
  AND (classification IS NULL OR classification NOT IN ('منخفض','طبيعي','مرتفع'));

UPDATE `glucose_readings`
SET classification = 'طبيعي'
WHERE (classification IS NULL OR classification NOT IN ('منخفض','طبيعي','مرتفع'));

-- 7) Create ai_evaluations table for caching AI evaluation per user
CREATE TABLE IF NOT EXISTS `ai_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `period_days` TINYINT UNSIGNED NOT NULL DEFAULT 30,
  `evaluation_text` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_period` (`user_id`, `period_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

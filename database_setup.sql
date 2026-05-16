-- Diabetes awareness app database bootstrap.
-- Safe to run on a fresh local XAMPP MySQL/MariaDB server.

CREATE DATABASE IF NOT EXISTS `project_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `project_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `google_id` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(50) NULL,
  `password` VARCHAR(255) NULL,
  `full_name` VARCHAR(255) NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'diabetic',
  `survey_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `profile_completed_at` DATETIME NULL,
  `remember_token` VARCHAR(128) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_google_id` (`google_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_profile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(255) NULL,
  `age` INT NULL,
  `gender` VARCHAR(30) NULL,
  `weight` DECIMAL(6,2) NULL,
  `height` DECIMAL(6,2) NULL,
  `diagnosis_date` DATE NULL,
  `treatment_type` VARCHAR(100) NULL,
  `diabetes_type` VARCHAR(50) NULL,
  `phone` VARCHAR(50) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_profile_user` (`user_id`),
  KEY `idx_user_profile_age` (`age`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_data` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NULL,
  `age` INT NULL,
  `gender` VARCHAR(30) NULL,
  `weight` DECIMAL(6,2) NULL,
  `height` DECIMAL(6,2) NULL,
  `phone` VARCHAR(50) NULL,
  `therapy_type` VARCHAR(100) NULL,
  `diabetes_type` VARCHAR(50) NULL,
  `drug_dose_map` LONGTEXT NULL,
  `date_of_diagnosis` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patient_data_user` (`user_id`),
  KEY `idx_patient_data_age` (`age`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glucose_readings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `reading_value` DECIMAL(6,2) NOT NULL,
  `reading_unit` VARCHAR(10) NOT NULL DEFAULT 'mg',
  `reading_context` VARCHAR(30) NOT NULL DEFAULT 'fasting',
  `reading_date` DATE NOT NULL,
  `reading_time` TIME NULL,
  `note` TEXT NULL,
  `medications` TEXT NULL,
  `psychological_status` VARCHAR(50) NULL,
  `classification` VARCHAR(100) NULL,
  `status_key` VARCHAR(20) NULL,
  `status_ar` VARCHAR(50) NULL,
  `reason_ar` VARCHAR(255) NULL,
  `weight_kg` DECIMAL(5,1) NULL,
  `last_medication_dose` VARCHAR(50) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gr_user_date` (`user_id`, `reading_date`),
  KEY `idx_gr_classification` (`classification`, `reading_context`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar` VARCHAR(255) NULL,
  `name_en` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) NULL,
  `suitability` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medications_name_en` (`name_en`),
  KEY `idx_medications_name_ar` (`name_ar`),
  KEY `idx_medications_name_en` (`name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_medications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `medication_id` INT UNSIGNED NOT NULL,
  `dose` VARCHAR(100) NULL,
  `note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_medication` (`user_id`, `medication_id`),
  KEY `idx_um_medication` (`medication_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tips` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_ar` VARCHAR(255) NOT NULL,
  `body_ar` TEXT NOT NULL,
  `min_age` INT NOT NULL DEFAULT 0,
  `max_age` INT NOT NULL DEFAULT 150,
  `gender_target` VARCHAR(30) NOT NULL DEFAULT 'any',
  `diabetes_target` VARCHAR(50) NOT NULL DEFAULT 'any',
  `priority` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_health_tips_title` (`title_ar`),
  KEY `idx_ht_active_priority` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glucose_standards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `diabetes_type` VARCHAR(50) NOT NULL DEFAULT 'any',
  `age_min` INT NOT NULL DEFAULT 0,
  `age_max` INT NOT NULL DEFAULT 150,
  `fasting_min` INT NOT NULL DEFAULT 70,
  `fasting_max` INT NOT NULL DEFAULT 130,
  `post2h_max` INT NOT NULL DEFAULT 180,
  `hba1c_max` DECIMAL(3,1) NOT NULL DEFAULT 7.0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_glucose_standards_type_age` (`diabetes_type`, `age_min`, `age_max`),
  KEY `idx_gs_type_age` (`diabetes_type`, `age_min`, `age_max`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `period_days` TINYINT UNSIGNED NOT NULL DEFAULT 30,
  `evaluation_text` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_period` (`user_id`, `period_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_shares` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` CHAR(32) NOT NULL,
  `selected_ids_text` TEXT NOT NULL,
  `date_from` DATE NULL,
  `date_to` DATE NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_report_shares_user` (`user_id`),
  KEY `idx_report_shares_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `awareness_articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `title_ar` VARCHAR(255) NOT NULL,
  `summary_ar` TEXT NULL,
  `body_html_ar` MEDIUMTEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_awareness_articles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `glucose_standards`
  (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`)
VALUES
  ('any', 0, 150, 70, 130, 180, 7.0),
  ('type1', 0, 17, 90, 130, 180, 7.5),
  ('type1', 18, 150, 80, 130, 180, 7.0),
  ('type2', 0, 150, 80, 130, 180, 7.0),
  ('gestational', 0, 150, 70, 95, 120, 6.5),
  ('prediabetes', 0, 150, 70, 125, 180, 5.7)
ON DUPLICATE KEY UPDATE `fasting_min` = VALUES(`fasting_min`);

INSERT INTO `health_tips`
  (`title_ar`, `body_ar`, `min_age`, `max_age`, `gender_target`, `diabetes_target`, `priority`, `is_active`)
VALUES
  ('Daily monitoring', 'Check glucose at consistent times and keep a short note about meals, stress, and activity.', 0, 150, 'any', 'any', 10, 1),
  ('Balanced meals', 'Build meals around vegetables, lean protein, whole grains, and measured carbohydrate portions.', 0, 150, 'any', 'any', 9, 1),
  ('Physical activity', 'Aim for regular light or moderate activity when your clinician says it is safe.', 0, 150, 'any', 'any', 8, 1),
  ('Medication routine', 'Take prescribed medication exactly as directed and ask your clinician before changing doses.', 0, 150, 'any', 'any', 7, 1)
ON DUPLICATE KEY UPDATE `priority` = VALUES(`priority`);

INSERT INTO `medications` (`name_ar`, `name_en`, `type`, `suitability`) VALUES
  ('Metformin', 'Metformin', 'oral', 'type2'),
  ('Insulin glargine', 'Insulin glargine', 'insulin', 'type1,type2'),
  ('Insulin lispro', 'Insulin lispro', 'insulin', 'type1,type2'),
  ('Gliclazide', 'Gliclazide', 'oral', 'type2'),
  ('Sitagliptin', 'Sitagliptin', 'oral', 'type2')
ON DUPLICATE KEY UPDATE `name_en` = VALUES(`name_en`);

SELECT 'Database setup completed' AS `status`;

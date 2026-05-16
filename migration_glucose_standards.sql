-- 1. Create the standards table
CREATE TABLE IF NOT EXISTS `glucose_standards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `diabetes_type` enum('any','type1','type2','gestational','prediabetes','none') DEFAULT 'any',
  `age_min` int(11) NOT NULL DEFAULT 0,
  `age_max` int(11) NOT NULL DEFAULT 150,
  `fasting_min` int(11) NOT NULL DEFAULT 70,
  `fasting_max` int(11) NOT NULL DEFAULT 110,
  `post2h_max` int(11) NOT NULL DEFAULT 140,
  `hba1c_max` decimal(3,1) NOT NULL DEFAULT 5.7,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Clear existing to be safe during re-runs
TRUNCATE TABLE `glucose_standards`;

-- 3. Insert standards based on age and type

-- Non-Diabetics ('none') - stricter normal bounds
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('none', 0, 150, 70, 99, 140, 5.7);

-- Gestational Diabetes (Pregnant)
-- Usually fasting < 95, 1h < 140, 2h < 120
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('gestational', 0, 100, 70, 95, 120, 6.0);

-- Children & Adolescents (Type 1 mostly, age 0-19)
-- Fasting 90-130 mg/dL, Bedtime/Overnight 90-150 mg/dL
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('type1', 0, 19, 90, 130, 150, 7.5);

-- Type 1 Adults (20+) - general targets
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('type1', 20, 150, 70, 130, 180, 7.0);

-- Type 2 / Any (Fallback rules as requested by user based on age)
-- Age 0-20 (if Type 2 or 'any' fallback)
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 0, 19, 70, 110, 140, 5.7),
('type2', 0, 19, 70, 110, 140, 5.7);

-- Age 20-30
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 20, 30, 70, 110, 140, 5.7),
('type2', 20, 30, 70, 110, 140, 5.7);

-- Age 31-40
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 31, 40, 70, 110, 140, 5.7),
('type2', 31, 40, 70, 110, 140, 5.7);

-- Age 41-50
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 41, 50, 70, 110, 140, 5.7),
('type2', 41, 50, 70, 110, 140, 5.7);

-- Age 51-60
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 51, 60, 70, 120, 140, 5.7),
('type2', 51, 60, 70, 120, 140, 5.7);

-- Age 61+
INSERT INTO `glucose_standards` (`diabetes_type`, `age_min`, `age_max`, `fasting_min`, `fasting_max`, `post2h_max`, `hba1c_max`) VALUES
('any', 61, 150, 70, 130, 140, 6.0),
('type2', 61, 150, 70, 130, 140, 6.0);


-- 4. Update the existing classifications in the glucose_readings table.
-- First, standardize the context inside the readings. Treat 'ramadan' same as 'fasting'.
-- Using a subquery approach because MySQL's UPDATE JOIN syntax can be tricky.

UPDATE `glucose_readings` gr
JOIN `patient_data` pd ON gr.user_id = pd.user_id
JOIN `glucose_standards` gs ON 
    (pd.age BETWEEN gs.age_min AND gs.age_max) 
    AND (
        (pd.diabetes_type = 'type1' AND gs.diabetes_type = 'type1') OR
        (pd.diabetes_type = 'type2' AND gs.diabetes_type = 'type2') OR
        (pd.diabetes_type = 'gestational' AND gs.diabetes_type = 'gestational') OR
        (pd.diabetes_type = 'none' AND gs.diabetes_type = 'none') OR
        (pd.diabetes_type NOT IN ('type1', 'type2', 'gestational', 'none') AND gs.diabetes_type = 'any')
    )
SET gr.classification = CASE
    WHEN gr.reading_context IN ('fasting', 'ramadan') THEN
        CASE 
            WHEN IF(gr.reading_unit = 'mmol', gr.reading_value * 18, gr.reading_value) < gs.fasting_min THEN 'منخفض'
            WHEN IF(gr.reading_unit = 'mmol', gr.reading_value * 18, gr.reading_value) <= gs.fasting_max THEN 'طبيعي'
            ELSE 'مرتفع'
        END
    WHEN gr.reading_context = 'post' THEN
        CASE 
            WHEN IF(gr.reading_unit = 'mmol', gr.reading_value * 18, gr.reading_value) < 70 THEN 'منخفض'
            WHEN IF(gr.reading_unit = 'mmol', gr.reading_value * 18, gr.reading_value) <= gs.post2h_max THEN 'طبيعي'
            ELSE 'مرتفع'
        END
    ELSE 'غير معروف'
END;

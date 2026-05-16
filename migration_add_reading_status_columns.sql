-- Add deterministic glucose status columns (run once)
ALTER TABLE glucose_readings
    ADD COLUMN IF NOT EXISTS status_key VARCHAR(10) NULL AFTER classification,
    ADD COLUMN IF NOT EXISTS status_ar VARCHAR(20) NULL AFTER status_key,
    ADD COLUMN IF NOT EXISTS reason_ar VARCHAR(255) NULL AFTER status_ar;


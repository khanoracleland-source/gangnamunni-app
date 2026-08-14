-- Add an optional body-part label to each hospital treatment price option.
-- Safe to run more than once.
DROP PROCEDURE IF EXISTS gnu_add_treatment_body_part;

CREATE PROCEDURE gnu_add_treatment_body_part()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_hospital_treatment_prices'
      AND column_name = 'body_part'
  ) THEN
    ALTER TABLE gangnamunni_hospital_treatment_prices
      ADD COLUMN body_part VARCHAR(100) NOT NULL DEFAULT '' AFTER option_name;
  END IF;
END;

CALL gnu_add_treatment_body_part();
DROP PROCEDURE IF EXISTS gnu_add_treatment_body_part;

-- Booking treatment detail option names (safe to run more than once via procedure).
DROP PROCEDURE IF EXISTS gnu_add_treatment_option_name;
DELIMITER //
CREATE PROCEDURE gnu_add_treatment_option_name()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_hospital_treatment_prices'
      AND column_name = 'option_name'
  ) THEN
    ALTER TABLE gangnamunni_hospital_treatment_prices
      ADD COLUMN option_name VARCHAR(100) NOT NULL DEFAULT '' AFTER hospital_treatment_id;
  END IF;
END//
DELIMITER ;
CALL gnu_add_treatment_option_name();
DROP PROCEDURE IF EXISTS gnu_add_treatment_option_name;

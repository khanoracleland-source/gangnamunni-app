-- Add a reusable default detail image to each treatment master (idempotent).
DROP PROCEDURE IF EXISTS gnu_add_treatment_default_image;
DELIMITER $$
CREATE PROCEDURE gnu_add_treatment_default_image()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_treatment_master'
      AND column_name = 'default_image_url'
  ) THEN
    ALTER TABLE gangnamunni_treatment_master
      ADD COLUMN default_image_url VARCHAR(500) DEFAULT NULL AFTER description;
  END IF;
END$$
DELIMITER ;
CALL gnu_add_treatment_default_image();
DROP PROCEDURE IF EXISTS gnu_add_treatment_default_image;

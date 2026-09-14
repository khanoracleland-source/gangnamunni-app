-- Booking schema migration — deploy once with a database administrator account.
-- Application/web requests never execute these ALTER statements.

DROP PROCEDURE IF EXISTS gnu_bookings_upgrade;
DELIMITER //
CREATE PROCEDURE gnu_bookings_upgrade()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_bookings'
      AND column_name = 'doctor_id'
  ) THEN
    ALTER TABLE gangnamunni_bookings
      ADD COLUMN doctor_id INT NULL AFTER hms_appointment_id;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_bookings'
      AND column_name = 'doctor_name'
  ) THEN
    ALTER TABLE gangnamunni_bookings
      ADD COLUMN doctor_name VARCHAR(100) NULL AFTER doctor_id;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_bookings'
      AND column_name = 'edit_count'
  ) THEN
    ALTER TABLE gangnamunni_bookings
      ADD COLUMN edit_count INT NOT NULL DEFAULT 0;
  END IF;

  ALTER TABLE gangnamunni_bookings
    MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending';
END//
DELIMITER ;

CALL gnu_bookings_upgrade();
DROP PROCEDURE IF EXISTS gnu_bookings_upgrade;

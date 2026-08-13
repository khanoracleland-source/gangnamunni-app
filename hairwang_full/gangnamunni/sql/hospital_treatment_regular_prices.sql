-- 병원별 시술 가격 옵션에 정가를 추가한다 (여러 번 실행 가능).
-- 기존 price 컬럼은 판매가로 유지한다.
DROP PROCEDURE IF EXISTS gnu_add_treatment_regular_price;
DELIMITER //
CREATE PROCEDURE gnu_add_treatment_regular_price()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_hospital_treatment_prices'
      AND column_name = 'regular_price'
  ) THEN
    ALTER TABLE gangnamunni_hospital_treatment_prices
      ADD COLUMN regular_price INT NOT NULL DEFAULT 0 AFTER dosage_unit;
  END IF;
END//
DELIMITER ;
CALL gnu_add_treatment_regular_price();
DROP PROCEDURE IF EXISTS gnu_add_treatment_regular_price;

DROP PROCEDURE IF EXISTS gnu_add_treatment_representative_price;
DELIMITER //
CREATE PROCEDURE gnu_add_treatment_representative_price()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'gangnamunni_hospital_treatment_prices'
      AND column_name = 'is_representative'
  ) THEN
    ALTER TABLE gangnamunni_hospital_treatment_prices
      ADD COLUMN is_representative TINYINT(1) NOT NULL DEFAULT 0 AFTER price;
  END IF;
END//
DELIMITER ;
CALL gnu_add_treatment_representative_price();
DROP PROCEDURE IF EXISTS gnu_add_treatment_representative_price;

UPDATE gangnamunni_hospital_treatment_prices
SET regular_price = price
WHERE regular_price <= 0 AND price > 0;

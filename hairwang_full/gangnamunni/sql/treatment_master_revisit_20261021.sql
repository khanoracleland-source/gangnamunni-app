-- 시술(마스터)별 재시술 간격(일)과 필수 여부(admin_treatments.php 에서 관리). 배포 전에 한 번 실행한다.
-- revisit_days: NULL 또는 0 = 제한 없음. revisit_strict: 1 = 간격 전에는 예약 불가(05-16), 0 = 권장만(05-15, "그래도 예약" 가능).
SET @h1 := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_treatment_master' AND COLUMN_NAME = 'revisit_days');
SET @d1 := IF(@h1 = 0, 'ALTER TABLE gangnamunni_treatment_master ADD COLUMN revisit_days SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT ''재시술 최소 간격(일)''', 'SELECT 1');
PREPARE s1 FROM @d1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @h2 := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_treatment_master' AND COLUMN_NAME = 'revisit_strict');
SET @d2 := IF(@h2 = 0, 'ALTER TABLE gangnamunni_treatment_master ADD COLUMN revisit_strict TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1=간격 전 예약 불가''', 'SELECT 1');
PREPARE s2 FROM @d2; EXECUTE s2; DEALLOCATE PREPARE s2;

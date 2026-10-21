-- 시술별 예약 가능 최소 만 나이 (NULL 또는 0 = 제한 없음). 배포 전에 한 번 실행한다.
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_hospital_treatments' AND COLUMN_NAME = 'min_age');
SET @ddl := IF(@has = 0,
  'ALTER TABLE gangnamunni_hospital_treatments ADD COLUMN min_age TINYINT UNSIGNED NULL DEFAULT NULL COMMENT ''예약 가능 최소 만 나이(NULL=제한없음)''',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 생년월일 변경 이력 (회원이 스스로 바꾼 기록과 횟수 제한 확인용)
CREATE TABLE IF NOT EXISTS gangnamunni_member_birth_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mb_id VARCHAR(60) NOT NULL,
  old_birth VARCHAR(10) NOT NULL DEFAULT '',
  new_birth VARCHAR(10) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mb_created (mb_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

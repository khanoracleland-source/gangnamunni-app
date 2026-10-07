-- ============================================================
-- 포인트 제도 v2 스키마 (#포인트제도 v2) — 배포 시 1회 실행(관리자 권한). 멱등.
--   선행: sql/points_ledger.sql (schema_version=3)
--   예)  mysql -u<admin> -p <db_name> < sql/points_v2.sql
--
-- 이 스크립트는 "테이블/컬럼/설정 키"만 만든다. 모든 v2 기능 스위치는 0(꺼짐)으로 심는다.
-- 운영 반영 순서(관리자 > 포인트 관리 > 제도 v2 에서 확인 가능):
--   1) 이 SQL 실행 → v2_schema_version=1
--   2) php tools/points_v2_backfill.php   (기존 내역으로 적립 단위(lot) 재구성, lots_ready=1)
--   3) 관리자 화면에서 기능별 스위치 ON
-- ============================================================

-- 1) 적립 단위(lot): 적립 1건 = lot 1개. remaining 은 아직 쓰지 않은 잔량.
--    잔액 = SUM(remaining) (잔액이 음수면 lot 은 모두 0)
CREATE TABLE IF NOT EXISTS gangnamunni_point_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  point_id INT NOT NULL COMMENT '적립 거래 gangnamunni_points.id',
  amount INT NOT NULL,
  remaining INT NOT NULL,
  expires_at DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_point (point_id),
  KEY idx_member_open (member_id, remaining, expires_at),
  KEY idx_expire (expires_at, remaining)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) 차감 거래가 어떤 lot 에서 얼마를 썼는지(결제 취소 시 원래 lot 으로 복구하는 근거)
CREATE TABLE IF NOT EXISTS gangnamunni_point_lot_allocs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  point_id INT NOT NULL COMMENT '차감 거래 gangnamunni_points.id',
  lot_id INT NOT NULL,
  amount INT NOT NULL,
  KEY idx_point (point_id),
  KEY idx_lot (lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) 결제 시 포인트 사용(홀드). 결제 1건에 활성 홀드는 최대 1개.
CREATE TABLE IF NOT EXISTS gangnamunni_point_payment_holds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  booking_id BIGINT UNSIGNED NOT NULL,
  member_id INT NOT NULL,
  points INT NOT NULL,
  status ENUM('held','captured','released','restored') NOT NULL DEFAULT 'held',
  use_point_id INT DEFAULT NULL COMMENT '사용(use) 거래 id',
  restore_point_id INT DEFAULT NULL COMMENT '복구(refund) 거래 id',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payment (payment_id, status),
  KEY idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) 경과 후기(일기형): 후기 1개 + 회차별 기록 N개
CREATE TABLE IF NOT EXISTS gangnamunni_progress_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  mb_id VARCHAR(100) NOT NULL DEFAULT '',
  hospital_id INT DEFAULT NULL,
  booking_id BIGINT UNSIGNED DEFAULT NULL,
  treatment_name VARCHAR(200) NOT NULL DEFAULT '',
  kind ENUM('procedure','surgery') NOT NULL DEFAULT 'procedure' COMMENT 'procedure=시술, surgery=수술',
  procedure_date DATE DEFAULT NULL,
  title VARCHAR(200) NOT NULL DEFAULT '',
  status ENUM('writing','submitted','approved','rejected','deleted') NOT NULL DEFAULT 'writing',
  submitted_at DATETIME DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  reviewed_by VARCHAR(100) DEFAULT NULL,
  reject_reason VARCHAR(500) DEFAULT NULL,
  awarded_point INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_member (member_id, status),
  KEY idx_status (status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gangnamunni_progress_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  progress_id INT NOT NULL,
  entry_date DATE NOT NULL,
  content TEXT NOT NULL,
  photo_urls TEXT DEFAULT NULL COMMENT 'JSON 배열',
  photo_consent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_progress (progress_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) 결제 원장: 포인트 사용 전 금액/사용 포인트 (amount 는 실제 PG 결제액 그대로)
DROP PROCEDURE IF EXISTS gnu_points_v2_upgrade;
DELIMITER //
CREATE PROCEDURE gnu_points_v2_upgrade()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='gangnamunni_platform_payments') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='gangnamunni_platform_payments' AND column_name='gross_amount') THEN
      ALTER TABLE gangnamunni_platform_payments
        ADD COLUMN gross_amount BIGINT UNSIGNED DEFAULT NULL COMMENT '포인트 차감 전 시술 금액' AFTER amount,
        ADD COLUMN points_used BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '결제에 사용한 포인트' AFTER gross_amount;
    END IF;
  END IF;
END//
DELIMITER ;
CALL gnu_points_v2_upgrade();
DROP PROCEDURE IF EXISTS gnu_points_v2_upgrade;

-- 6) 설정 키(없을 때만). 모든 기능 스위치는 0 = 꺼짐.
INSERT INTO gangnamunni_point_settings (skey, sval) VALUES
  ('v2_policy_enabled','0'),          -- v2 적립 규칙(후기 유형·결제 금액 구간·90일 기한·회수)
  ('v2_expire_job_enabled','0'),      -- 기한 지난 포인트 자동 소멸(cron)
  ('v2_point_use_enabled','0'),       -- 결제 시 포인트 사용
  ('v2_progress_enabled','0'),        -- 경과 후기 기능 노출
  ('v2_first_review_enabled','0'),    -- 첫 후기 주간 일괄 적립(cron)
  ('lots_ready','0'),                 -- backfill 완료 여부(도구가 1로 설정)
  ('v2_expire_days','90'),
  ('v2_monthly_cap','3'),
  ('v2_review_photo_point','3000'),
  ('v2_review_receipt_point','3000'),
  ('v2_review_first_point','10000'),
  ('v2_first_yearly_cap','5'),
  ('v2_progress_procedure_point','6000'),
  ('v2_progress_surgery_point','15000'),
  ('v2_progress_procedure_entries','3'),
  ('v2_progress_surgery_entries','10'),
  ('v2_progress_resubmit_days','30'),
  ('v2_pay_tier1_under','50000'),
  ('v2_pay_tier1_point','3000'),
  ('v2_pay_tier2_under','100000'),
  ('v2_pay_tier2_point','5000'),
  ('v2_pay_tier3_point','10000'),
  ('v2_point_use_min_pay','1000'),
  ('v2_hold_ttl_minutes','60'),
  ('v2_schema_version','1')
ON DUPLICATE KEY UPDATE sval = IF(skey = 'v2_schema_version', VALUES(sval), sval);

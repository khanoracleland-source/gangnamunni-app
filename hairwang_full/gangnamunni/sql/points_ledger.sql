-- ============================================================
-- 포인트 원장(#포인트제도) 스키마 — 배포 시 1회 실행(관리자 권한).
-- ------------------------------------------------------------
-- 목적: 애플리케이션(웹 요청 경로)에서 CREATE/ALTER TABLE 을 실행하지 않도록,
--       DDL 을 배포 단계에서 미리 적용한다. 이 스크립트가 schema_version=2 를 심어두면
--       points_ensure_schema() 의 빠른 경로가 곧바로 종료되어 이후 DDL 이 일어나지 않는다.
-- 특징: 멱등(재실행 안전). DDL 권한이 있는 사용자로 1회 실행.
--   예)  mysql -u<admin> -p <db_name> < sql/points_ledger.sql
-- ============================================================

-- 1) 원장 테이블(신규 설치 시)
CREATE TABLE IF NOT EXISTS gangnamunni_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL COMMENT 'g5_member.mb_no',
  type ENUM('earn','use','refund','expire','admin_add','admin_sub','earn_cancel','admin') NOT NULL,
  amount INT NOT NULL COMMENT '항상 양수. 부호는 type 으로 판단',
  balance INT NOT NULL DEFAULT 0 COMMENT '거래 후 잔액 스냅샷',
  reason VARCHAR(200) DEFAULT NULL,
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  expires_at DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_member (member_id, id),
  KEY idx_member_id2 (member_id, id),
  KEY idx_expires (expires_at),
  UNIQUE KEY uniq_ref (reference_type, reference_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) 설정 테이블
CREATE TABLE IF NOT EXISTS gangnamunni_point_settings (
  skey VARCHAR(50) PRIMARY KEY,
  sval VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) 회원 등급 테이블(membership.php 가 사용)
CREATE TABLE IF NOT EXISTS gangnamunni_membership_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  min_points INT DEFAULT 0,
  icon VARCHAR(50) DEFAULT '',
  color VARCHAR(20) DEFAULT '#888',
  benefits TEXT,
  discount_rate DECIMAL(4,1) DEFAULT 0,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) 기존(구) 테이블 승격 — 멱등 프로시저로 ENUM/인덱스 보강
DROP PROCEDURE IF EXISTS gnu_points_upgrade;
DELIMITER //
CREATE PROCEDURE gnu_points_upgrade()
BEGIN
  -- ENUM 확장(과거 값 'admin','expire' 보존)
  ALTER TABLE gangnamunni_points
    MODIFY COLUMN type ENUM('earn','use','refund','expire','admin_add','admin_sub','earn_cancel','admin') NOT NULL;

  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
      WHERE table_schema=DATABASE() AND table_name='gangnamunni_points' AND index_name='uniq_ref') THEN
    ALTER TABLE gangnamunni_points ADD UNIQUE KEY uniq_ref (reference_type, reference_id, type);
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
      WHERE table_schema=DATABASE() AND table_name='gangnamunni_points' AND index_name='idx_member_id2') THEN
    ALTER TABLE gangnamunni_points ADD KEY idx_member_id2 (member_id, id);
  END IF;
END//
DELIMITER ;
CALL gnu_points_upgrade();
DROP PROCEDURE IF EXISTS gnu_points_upgrade;

-- 5) 기본 설정값(없을 때만) + schema_version=2 확정
INSERT INTO gangnamunni_point_settings (skey, sval) VALUES
  ('enabled','1'),
  ('signup_point','1000'),
  ('booking_percent','0'),
  ('review_default_point','1000'),
  ('expire_days','365'),
  ('schema_version','2')
ON DUPLICATE KEY UPDATE
  sval = IF(skey = 'schema_version', VALUES(sval), sval);

-- 6) 등급 시드(테이블이 비어 있을 때만)
INSERT INTO gangnamunni_membership_levels (name,min_points,icon,color,benefits,discount_rate,sort_order)
SELECT * FROM (
  SELECT '브론즈' AS name, 0 AS min_points, 'bi-shield' AS icon, '#cd7f32' AS color, '기본 혜택, 후기 작성 포인트 적립' AS benefits, 0 AS discount_rate, 0 AS sort_order
  UNION ALL SELECT '실버',1000,'bi-shield-fill','#c0c0c0','5% 할인 쿠폰 매월 지급, 우선 예약',5,1
  UNION ALL SELECT '골드',5000,'bi-trophy','#ffd700','10% 할인, 무료 상담, VIP 이벤트 초대',10,2
  UNION ALL SELECT '다이아몬드',20000,'bi-gem','#AE1932','15% 할인, 전용 상담사, 무료 시술 쿠폰',15,3
) seed
WHERE (SELECT COUNT(*) FROM gangnamunni_membership_levels) = 0;

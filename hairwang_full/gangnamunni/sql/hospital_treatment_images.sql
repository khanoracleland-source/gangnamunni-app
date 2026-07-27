-- ============================================================
-- 병원별 시술 상세페이지 이미지(#시술이미지) 스키마 — 배포 시 1회 실행.
-- ------------------------------------------------------------
-- 목적: 병원이 배정받은 시술 각각에 대해 "시술 상세페이지"에 노출할
--       이미지를 여러 장 등록할 수 있게 한다.
--       detail.php 의 "상세 정보" 영역이 이 테이블을 읽는다.
-- 특징: 멱등(재실행 안전). DDL 권한이 있는 사용자로 1회 실행.
--   예)  mysql -u<admin> -p <db_name> < sql/hospital_treatment_images.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS gangnamunni_hospital_treatment_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_treatment_id INT NOT NULL COMMENT 'gangnamunni_hospital_treatments.id',
  hospital_id INT NOT NULL COMMENT '조회 편의를 위한 비정규화 컬럼',
  treatment_id INT NOT NULL COMMENT '조회 편의를 위한 비정규화 컬럼',
  image_url VARCHAR(500) NOT NULL COMMENT 'gangnamunni/ 기준 상대경로 또는 외부 URL',
  caption VARCHAR(200) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ht (hospital_treatment_id, sort_order, id),
  KEY idx_hospital_treatment (hospital_id, treatment_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 입점 신청(상담 요청) 접수 테이블 — about-apply.php 에서 INSERT, admin_hospital_leads.php 에서 관리
-- 2026-10-13
--
-- hospital_apply_public.php(이메일 OTP + 서류 업로드 → gangnamunni_hospitals) 와는 별개다.
-- 이 테이블은 "담당자가 연락드리는" 단계의 상담 요청만 담는다.

CREATE TABLE IF NOT EXISTS `gangnamunni_hospital_leads` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_name`     VARCHAR(191)  NOT NULL                COMMENT '병원명',
  `department`        VARCHAR(50)   NOT NULL                COMMENT '진료과목',
  `sido`              VARCHAR(50)   NOT NULL                COMMENT '시/도',
  `address_detail`    VARCHAR(255)      NULL                COMMENT '상세 주소',
  `manager`           VARCHAR(100)  NOT NULL                COMMENT '담당자 성함/직책',
  `phone`             VARCHAR(20)   NOT NULL                COMMENT '연락처',
  `email`             VARCHAR(191)      NULL                COMMENT '이메일',
  `memo`              TEXT              NULL                COMMENT '문의 내용',
  `status`            ENUM('new','contacting','done','hold') NOT NULL DEFAULT 'new' COMMENT '처리 상태',
  `admin_memo`        TEXT              NULL                COMMENT '관리자 메모',
  `privacy_agreed_at` DATETIME      NOT NULL                COMMENT '개인정보 수집·이용 동의 시각',
  `client_ip`         VARCHAR(45)       NULL                COMMENT '제출 IP (스팸 차단용)',
  `user_agent`        VARCHAR(255)      NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_ip_created` (`client_ip`, `created_at`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='입점 상담 신청';

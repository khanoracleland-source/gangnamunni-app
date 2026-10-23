-- 07-40/07-41 상담 내역: 연락 실패 상태, 병원 메시지, 다시 연락받을 시간, 진행 기록.
-- (sql/consultation_member_20261023.sql 을 먼저 실행하세요.)
-- status 가 ENUM 이든 VARCHAR 든 'contact_failed' 를 담을 수 있도록 VARCHAR 로 통일한다(기존 값은 그대로 유지된다).
ALTER TABLE gangnamunni_consultations
  MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending',
  ADD COLUMN callback_slot VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN hospital_message TEXT NULL,
  ADD COLUMN hospital_message_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS gangnamunni_consultation_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  consultation_id INT NOT NULL,
  kind VARCHAR(20) NOT NULL COMMENT 'no_answer | rerequest | completed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_consult (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

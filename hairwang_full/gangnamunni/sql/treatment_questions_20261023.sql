-- [05-19] 시술 전 확인 질문(병원 시술별, 예/아니오)과 고객 답변.
-- 질문은 admin_hospital_treatments.php 에서 병원 시술마다 관리자가 직접 적는다. 모두 선택 사항이다.
-- 실행: mysql -u root -p hms_saas < sql/treatment_questions_20261023.sql
CREATE TABLE IF NOT EXISTS gangnamunni_treatment_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospital_treatment_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ht (hospital_treatment_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 답변은 예약 당시의 질문 문구를 그대로 저장한다(질문이 나중에 바뀌어도 기록이 남도록). 답한 문항만 저장한다.
CREATE TABLE IF NOT EXISTS gangnamunni_booking_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TINYINT NOT NULL COMMENT '1=예, 0=아니오',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

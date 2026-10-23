-- [05-18] 같은 날 함께 예약하기 어려운 시술 조합 (전체 병원 공통, 시술 마스터 기준).
-- 한 쌍은 항상 (시술 마스터 ID 작은 쪽, 큰 쪽) 으로 저장한다.
-- 실행: mysql -u root -p hms_saas < sql/treatment_conflicts_20261023.sql
CREATE TABLE IF NOT EXISTS gangnamunni_treatment_conflicts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tm_a_id INT NOT NULL,
    tm_b_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pair (tm_a_id, tm_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

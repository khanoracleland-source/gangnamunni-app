-- 결제 수수료율: 정산 금액 계산용. 수단별 요율(rate_bp: 1/100 %, 143 = 1.43%)이 effective_from 날짜부터 적용된다.
-- 행은 추가만 한다(수정 없음). 삭제는 아직 적용 전(effective_from > 오늘)인 행만 코드가 허용한다: 이미 적용된 요율은 지워지지 않아 이전 결제의 계산이 변하지 않는다. 결제일(paid_at, 서울 날짜)에
-- 적용되는 요율 = 그 수단에서 effective_from <= 결제일인 가장 늦은 행.
-- 여러 번 실행해도 안전하다. 쓰기는 최고관리자만(관리자 API ADMIN_PAYMENTS_FEES=1, PHP 설정 화면).
CREATE TABLE IF NOT EXISTS gangnamunni_payment_fee_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pay_method ENUM('card','bank_transfer') NOT NULL,
    rate_bp SMALLINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_by_name VARCHAR(191) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fee_rate_method_from (pay_method, effective_from),
    CONSTRAINT chk_fee_rate_bp CHECK (rate_bp <= 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 관리자 API 계정(자리표시자는 deploy/README.md 와 같다): 읽기 전용은 SELECT, 쓰기 계정은 SELECT·INSERT·DELETE(적용 전 요율의 잘못된 입력을 지우기 위함; UPDATE 없음).
-- GRANT SELECT ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_ro'@'127.0.0.1';
-- GRANT SELECT ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_ro'@'localhost';
-- GRANT SELECT, INSERT, DELETE ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_rw'@'127.0.0.1';
-- GRANT SELECT, INSERT, DELETE ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_rw'@'localhost';

-- 정산 수수료: 결제가 확정(Counted)되는 순간의 요율과 수수료를 결제 행에 한 번 기록한다. 이후 요율이 바뀌어도 이 값은 변하지 않는다.
-- fee_rate_bp: 그 결제일에 적용된 요율(1/100 %, 143 = 1.43%), fee_amount = floor(amount x fee_rate_bp / 10000) (원).
-- NULL = 계산하지 않음(결제 수단이나 요율이 없을 때, 이전 결제). 이전 결제는 채우지 않는다.
-- 여러 번 실행해도 안전하다. 기록은 서버 스위치(Nest PAYMENTS_RECORD_FEE / PHP G5_PAYMENTS_RECORD_FEE)가 켜져야 시작되며,
-- pay_method 기록(platform_payments_pay_method.sql)과 gangnamunni_payment_fee_rates(payment_fee_rates.sql)가 먼저 있어야 한다.
-- 결제 DB 계정(Nest DB_PAY_USER)은 요율 표를 읽을 수 있어야 한다:
-- GRANT SELECT ON <DB_NAME>.gangnamunni_payment_fee_rates TO '<DB_PAY_USER>'@'127.0.0.1';
ALTER TABLE gangnamunni_platform_payments
    ADD COLUMN IF NOT EXISTS fee_rate_bp SMALLINT UNSIGNED DEFAULT NULL
        COMMENT '결제일에 적용된 수수료율 (1/100 %)' AFTER pay_method,
    ADD COLUMN IF NOT EXISTS fee_amount BIGINT UNSIGNED DEFAULT NULL
        COMMENT '수수료 (원, 내림)' AFTER fee_rate_bp;

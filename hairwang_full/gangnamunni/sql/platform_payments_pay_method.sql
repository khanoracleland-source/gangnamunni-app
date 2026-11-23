-- 결제 수단: 정산(수수료) 계산용. card = 카드, bank_transfer = 계좌이체. 이미 있는 결제는 NULL(수단 미상)로 남는다.
-- 여러 번 실행해도 안전하다. 수단 기록은 서버 스위치(Nest PAYMENTS_RECORD_PAY_METHOD / PHP G5_PAYMENTS_RECORD_PAY_METHOD)가 켜져야 시작된다.
ALTER TABLE gangnamunni_platform_payments
    ADD COLUMN IF NOT EXISTS pay_method ENUM('card','bank_transfer') DEFAULT NULL
        COMMENT '결제 수단 (NULL = 미상)' AFTER provider;

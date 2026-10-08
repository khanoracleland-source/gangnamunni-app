-- 토스페이먼츠 가이드: 성공 URL 로 돌아온 paymentKey·orderId·amount 를 승인(confirm) 전에 서버에 저장한다.
-- 한 결제 건(merchant_uid)에 여러 번 시도할 수 있으므로 시도마다 한 행.
CREATE TABLE IF NOT EXISTS gangnamunni_payment_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    merchant_uid VARCHAR(64) NOT NULL,
    provider VARCHAR(30) NOT NULL,
    pg_payment_key VARCHAR(200) NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    outcome ENUM('received','paid','refused','rejected','pending') NOT NULL DEFAULT 'received',
    reason VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_attempt_key (provider, pg_payment_key),
    KEY idx_payment_attempt_payment (payment_id, created_at),
    CONSTRAINT fk_payment_attempt_payment FOREIGN KEY (payment_id)
        REFERENCES gangnamunni_platform_payments(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

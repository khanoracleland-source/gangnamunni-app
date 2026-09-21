ALTER TABLE gangnamunni_platform_payments
    ADD COLUMN IF NOT EXISTS verified_amount BIGINT UNSIGNED DEFAULT NULL AFTER provider_transaction_id,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL AFTER verified_amount,
    ADD COLUMN IF NOT EXISTS verification_source ENUM('webhook','server_query') DEFAULT NULL AFTER verified_at;

ALTER TABLE gangnamunni_platform_payments
    ADD UNIQUE KEY IF NOT EXISTS uq_platform_payment_booking (payment_type,booking_id);

ALTER TABLE gangnamunni_platform_payments
    DROP CONSTRAINT IF EXISTS chk_platform_payment_paid;

ALTER TABLE gangnamunni_platform_payments
    ADD CONSTRAINT chk_platform_payment_paid CHECK (
        status NOT IN ('paid','partially_refunded','refunded')
        OR (
            paid_at IS NOT NULL
            AND provider IS NOT NULL
            AND provider_transaction_id IS NOT NULL
            AND verified_amount = amount
            AND verified_at IS NOT NULL
            AND verification_source IS NOT NULL
        )
    );

ALTER TABLE gangnamunni_platform_payments
    DROP CONSTRAINT IF EXISTS chk_platform_payment_refund_state;

ALTER TABLE gangnamunni_platform_payments
    ADD CONSTRAINT chk_platform_payment_refund_state CHECK (
        (status='partially_refunded' AND refunded_amount>0 AND refunded_amount<amount AND refunded_at IS NOT NULL)
        OR (status='refunded' AND refunded_amount=amount AND refunded_at IS NOT NULL)
        OR status NOT IN ('partially_refunded','refunded')
    );

ALTER TABLE gangnamunni_hospital_settlements
    ADD COLUMN IF NOT EXISTS payout_idempotency_key CHAR(64) NOT NULL DEFAULT '' AFTER payout_reference,
    ADD COLUMN IF NOT EXISTS approved_by BIGINT UNSIGNED DEFAULT NULL AFTER payout_idempotency_key,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL AFTER approved_by;

UPDATE gangnamunni_hospital_settlements
SET payout_idempotency_key = SHA2(
        CONCAT('hospital-settlement|', hospital_id, '|', period_start, '|', period_end, '|', currency), 256)
WHERE payout_idempotency_key = '';

ALTER TABLE gangnamunni_hospital_settlements
    ADD UNIQUE KEY IF NOT EXISTS uq_hospital_settlement_idempotency (payout_idempotency_key);

ALTER TABLE gangnamunni_hospital_settlements
    DROP CONSTRAINT IF EXISTS chk_hospital_settlement_processing;

ALTER TABLE gangnamunni_hospital_settlements
    ADD CONSTRAINT chk_hospital_settlement_processing CHECK (
        status NOT IN ('processing','paid')
        OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)
    );

ALTER TABLE gangnamunni_bookings
    ADD COLUMN IF NOT EXISTS refund_status VARCHAR(20) NOT NULL DEFAULT 'not_applicable' AFTER status;

CREATE TABLE IF NOT EXISTS gangnamunni_refund_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    actor_type ENUM('system','admin','webhook') NOT NULL,
    actor_id BIGINT UNSIGNED DEFAULT NULL,
    actor_name VARCHAR(191) NOT NULL DEFAULT '',
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_refund_audit_booking (booking_id,created_at),
    KEY idx_refund_audit_payment (payment_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

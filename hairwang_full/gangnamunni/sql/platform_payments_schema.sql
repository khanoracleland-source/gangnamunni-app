-- One canonical Seoul Beauty ledger for the two active payment products.
-- YoungCart/shop payments are intentionally outside this ledger.

CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_type ENUM('booking','ad_package') NOT NULL,
    funds_recipient ENUM('hospital','platform') NOT NULL,
    booking_id BIGINT UNSIGNED DEFAULT NULL,
    ad_id BIGINT UNSIGNED DEFAULT NULL,
    hospital_id BIGINT UNSIGNED NOT NULL,
    hospital_name VARCHAR(255) NOT NULL DEFAULT '',
    patient_mb_id VARCHAR(100) NOT NULL DEFAULT '',
    patient_name VARCHAR(100) NOT NULL DEFAULT '',
    patient_phone VARCHAR(30) NOT NULL DEFAULT '',
    treatment_name VARCHAR(255) NOT NULL DEFAULT '',
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    amount BIGINT UNSIGNED NOT NULL,
    refunded_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'KRW',
    provider VARCHAR(30) DEFAULT NULL,
    merchant_uid VARCHAR(100) NOT NULL,
    provider_transaction_id VARCHAR(191) DEFAULT NULL,
    status ENUM('pending','paid','failed','cancelled','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    refunded_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_payment_merchant (merchant_uid),
    UNIQUE KEY uq_platform_payment_provider_tx (provider,provider_transaction_id),
    KEY idx_platform_payment_booking (booking_id),
    KEY idx_platform_payment_ad (ad_id),
    KEY idx_platform_payment_hospital_paid (hospital_id,payment_type,status,paid_at),
    CONSTRAINT chk_platform_payment_owner CHECK (
        (payment_type='booking' AND funds_recipient='hospital' AND booking_id IS NOT NULL)
        OR (payment_type='ad_package' AND funds_recipient='platform' AND ad_id IS NOT NULL)
    ),
    CONSTRAINT chk_platform_payment_refund CHECK (refunded_amount <= amount),
    CONSTRAINT chk_platform_payment_paid CHECK (status <> 'paid' OR paid_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

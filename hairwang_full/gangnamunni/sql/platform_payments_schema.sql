

ALTER TABLE gangnamunni_advertisers
    ADD COLUMN IF NOT EXISTS hospital_id BIGINT UNSIGNED DEFAULT NULL AFTER id;

ALTER TABLE gangnamunni_ads
    ADD COLUMN IF NOT EXISTS hospital_id BIGINT UNSIGNED DEFAULT NULL AFTER id;

UPDATE gangnamunni_advertisers adv
JOIN (
    SELECT name,MIN(id) AS hospital_id
    FROM gangnamunni_hospitals GROUP BY name HAVING COUNT(*)=1
) h ON h.name=adv.hospital_name
SET adv.hospital_id=h.hospital_id
WHERE adv.hospital_id IS NULL;

UPDATE gangnamunni_ads a
JOIN gangnamunni_advertisers adv ON adv.id=a.advertiser_id
SET a.hospital_id=adv.hospital_id
WHERE a.hospital_id IS NULL AND adv.hospital_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_type ENUM('booking') NOT NULL,
    funds_recipient ENUM('hospital','platform') NOT NULL,
    booking_id BIGINT UNSIGNED DEFAULT NULL,
    hospital_id BIGINT UNSIGNED NOT NULL,
    hospital_name VARCHAR(255) NOT NULL DEFAULT '',
    patient_mb_id VARCHAR(100) NOT NULL DEFAULT '',
    patient_name VARCHAR(100) NOT NULL DEFAULT '',
    patient_phone VARCHAR(30) NOT NULL DEFAULT '',
    treatment_name VARCHAR(255) NOT NULL DEFAULT '',
    amount BIGINT UNSIGNED NOT NULL,
    refunded_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'KRW',
    provider VARCHAR(30) DEFAULT NULL,
    merchant_uid VARCHAR(100) NOT NULL,
    provider_transaction_id VARCHAR(191) DEFAULT NULL,
    verified_amount BIGINT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    verification_source ENUM('webhook','server_query') DEFAULT NULL,
    status ENUM('pending','paid','failed','cancelled','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    refunded_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_payment_merchant (merchant_uid),
    UNIQUE KEY uq_platform_payment_provider_tx (provider,provider_transaction_id),
    UNIQUE KEY uq_platform_payment_booking (payment_type,booking_id),
    KEY idx_platform_payment_booking (booking_id),
    KEY idx_platform_payment_hospital_paid (hospital_id,payment_type,status,paid_at),
    CONSTRAINT chk_platform_payment_owner CHECK (
        payment_type='booking' AND funds_recipient='hospital' AND booking_id IS NOT NULL
    ),
    CONSTRAINT chk_platform_payment_refund CHECK (refunded_amount <= amount),
    CONSTRAINT chk_platform_payment_paid CHECK (
        status NOT IN ('paid','partially_refunded','refunded')
        OR (
            paid_at IS NOT NULL
            AND provider IS NOT NULL
            AND provider_transaction_id IS NOT NULL
            AND verified_amount = amount
            AND verified_at IS NOT NULL
            AND verification_source IS NOT NULL
        )
    ),
    CONSTRAINT chk_platform_payment_refund_state CHECK (
        (status='partially_refunded' AND refunded_amount>0 AND refunded_amount<amount AND refunded_at IS NOT NULL)
        OR (status='refunded' AND refunded_amount=amount AND refunded_at IS NOT NULL)
        OR status NOT IN ('partially_refunded','refunded')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gangnamunni_payment_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_event_id VARCHAR(191) NOT NULL,
    event_type ENUM('payment_verified','payment_failed','refund_verified','payout_adjustment') NOT NULL,
    amount BIGINT UNSIGNED DEFAULT NULL,
    payload_sha256 CHAR(64) NOT NULL,
    provider_occurred_at DATETIME DEFAULT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_provider_event (provider,provider_event_id),
    KEY idx_payment_event_payment (payment_id,event_type,received_at),
    CONSTRAINT fk_payment_event_payment FOREIGN KEY (payment_id)
        REFERENCES gangnamunni_platform_payments(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gangnamunni_payment_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    old_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) NOT NULL,
    actor_type ENUM('system','provider','admin') NOT NULL,
    actor_id VARCHAR(100) DEFAULT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payment_history_payment (payment_id,created_at),
    CONSTRAINT fk_payment_history_payment FOREIGN KEY (payment_id)
        REFERENCES gangnamunni_platform_payments(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Seoul Beauty marketplace settlement model.
--
-- booking payment -> platform merchant account -> hospital monthly payout
-- ad package payment -> platform merchant account -> platform revenue
--
-- A row is not proof that money moved. `paid` requires a provider/bank
-- reference and verification timestamp.

CREATE TABLE IF NOT EXISTS gangnamunni_hospital_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospital_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    scheduled_payout_date DATE NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KRW',
    treatment_gross BIGINT UNSIGNED NOT NULL DEFAULT 0,
    refunds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    platform_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
    adjustments BIGINT NOT NULL DEFAULT 0,
    payout_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft','scheduled','processing','paid','failed','held') NOT NULL DEFAULT 'draft',
    payout_provider VARCHAR(30) DEFAULT NULL,
    payout_reference VARCHAR(191) DEFAULT NULL,
    failure_reason VARCHAR(500) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hospital_settlement_period (hospital_id, period_start, period_end),
    UNIQUE KEY uq_hospital_settlement_payout_ref (payout_provider, payout_reference),
    KEY idx_hospital_settlement_due (status, scheduled_payout_date),
    CONSTRAINT chk_hospital_settlement_period
        CHECK (period_start <= period_end AND scheduled_payout_date = period_end),
    CONSTRAINT chk_hospital_settlement_paid
        CHECK (status <> 'paid' OR (paid_at IS NOT NULL AND payout_reference IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gangnamunni_hospital_settlement_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_id BIGINT UNSIGNED NOT NULL,
    source_type ENUM('booking_payment','refund','adjustment') NOT NULL,
    source_id BIGINT UNSIGNED DEFAULT NULL,
    amount BIGINT NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    occurred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settlement_source (source_type, source_id),
    KEY idx_settlement_item_settlement (settlement_id),
    CONSTRAINT fk_settlement_item_header FOREIGN KEY (settlement_id)
        REFERENCES gangnamunni_hospital_settlements(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only `payment_type=booking AND funds_recipient=hospital` is eligible.
-- `ad_package/platform` is intentionally excluded from hospital settlements.

-- Upgrade path for databases where the FIRST version of
-- platform_payments_schema.sql / monthly_hospital_settlements.sql was already applied.
--
-- Those files start with CREATE TABLE IF NOT EXISTS, so re-running them silently
-- skips existing tables and never adds the verification columns/constraints the
-- payment core depends on. Run this file once on such databases.
--
-- Safe to run twice: every statement is guarded with IF NOT EXISTS / IF EXISTS.
-- Databases that never applied the first version should just run the main
-- schema files instead; this file is a no-op there only if the tables exist.

-- ─────────────────────────────────────────────────────────────
-- gangnamunni_platform_payments: verification evidence
-- ─────────────────────────────────────────────────────────────
ALTER TABLE gangnamunni_platform_payments
    ADD COLUMN IF NOT EXISTS verified_amount BIGINT UNSIGNED DEFAULT NULL AFTER provider_transaction_id,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL AFTER verified_amount,
    ADD COLUMN IF NOT EXISTS verification_source ENUM('webhook','server_query') DEFAULT NULL AFTER verified_at;

-- One payment per booking / per ad.
ALTER TABLE gangnamunni_platform_payments
    ADD UNIQUE KEY IF NOT EXISTS uq_platform_payment_booking (payment_type,booking_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_platform_payment_ad (payment_type,ad_id);

-- Replace the weak paid CHECK with the evidence-backed one.
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

-- ─────────────────────────────────────────────────────────────
-- gangnamunni_hospital_settlements: idempotency key + approval trail
-- ─────────────────────────────────────────────────────────────
ALTER TABLE gangnamunni_hospital_settlements
    ADD COLUMN IF NOT EXISTS payout_idempotency_key CHAR(64) NOT NULL DEFAULT '' AFTER payout_reference,
    ADD COLUMN IF NOT EXISTS approved_by BIGINT UNSIGNED DEFAULT NULL AFTER payout_idempotency_key,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL AFTER approved_by;

-- Backfill existing rows with the same key the cron computes, so the UNIQUE
-- index below cannot collide on repeated empty strings.
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

-- Apply before enabling the optional payment-free preview flow (MySQL 8 / MariaDB).
-- A preview never changes the financial ledger or represents money received.
CREATE TABLE IF NOT EXISTS gangnamunni_booking_payment_previews (
    booking_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gangnamunni_payment_refund_requests (
    request_id CHAR(64) NOT NULL PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    refunded_before BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    KEY idx_refund_request_payment (payment_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

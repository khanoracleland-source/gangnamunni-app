<?php

require_once __DIR__ . '/payment_fee.php';
require_once __DIR__ . '/booking_payment_config.php';
/**
 * Canonical Seoul Beauty payment service.
 * Controllers must never update financial statuses directly.
 */

function platform_payment_create_booking(mysqli $db, int $booking_id): array {
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db,
            "SELECT b.id,b.hospital_id,b.hospital_name,b.mb_id,b.name,b.phone,b.treatment,b.price
             FROM gangnamunni_bookings b WHERE b.id=? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
        mysqli_stmt_execute($stmt);
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$booking || (int)$booking['hospital_id'] <= 0 || (int)$booking['price'] <= 0) {
            throw new RuntimeException('Booking or server-side price is invalid.');
        }

        $existing = mysqli_prepare($db,
            "SELECT id,merchant_uid,status FROM gangnamunni_platform_payments
             WHERE payment_type='booking' AND booking_id=? LIMIT 1");
        mysqli_stmt_bind_param($existing, 'i', $booking_id);
        mysqli_stmt_execute($existing);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($existing));
        mysqli_stmt_close($existing);
        if ($row) {
            mysqli_commit($db);
            $row['created'] = false; // an order already existed (e.g. a re-confirm after a change)
            return $row;
        }

        $merchant_uid = 'BK_' . date('YmdHis') . '_' . $booking_id . '_' . bin2hex(random_bytes(6));
        $hospital_id = (int)$booking['hospital_id'];
        $amount = (int)$booking['price'];
        $stmt = mysqli_prepare($db,
            "INSERT INTO gangnamunni_platform_payments
                (payment_type,funds_recipient,booking_id,hospital_id,hospital_name,
                 patient_mb_id,patient_name,patient_phone,treatment_name,amount,currency,
                 merchant_uid,status)
             VALUES ('booking','hospital',?,?,?,?,?,?,?,?,'KRW',?,'pending')");
        mysqli_stmt_bind_param($stmt, 'iisssssis',
            $booking_id, $hospital_id, $booking['hospital_name'], $booking['mb_id'],
            $booking['name'], $booking['phone'], $booking['treatment'], $amount, $merchant_uid);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);
        mysqli_commit($db);
        return ['id' => $id, 'merchant_uid' => $merchant_uid, 'status' => 'pending', 'created' => true];
    } catch (Throwable $error) {
        mysqli_rollback($db);
        throw $error;
    }
}

/** card|bank_transfer when G5_PAYMENTS_RECORD_PAY_METHOD=1 (server switch, default off), otherwise null: the column stays untouched. */
function platform_payment_method_to_record(?string $pay_method): ?string {
    if ($pay_method === null || !in_array($pay_method, ['card', 'bank_transfer'], true)) return null;
    if (!function_exists('booking_payment_setting') || booking_payment_setting('G5_PAYMENTS_RECORD_PAY_METHOD', '0') !== '1') return null;
    return $pay_method;
}

/**
 * Fee and rate of a payment, or null: switch G5_PAYMENTS_RECORD_FEE=1 (default off), the rate in force on the payment's Seoul date
 * (latest effective_from <= that date) from gangnamunni_payment_fee_rates. A missing rate or an unreadable table returns null and never stops the payment.
 * @return array{rate_bp:int,amount:int}|null
 */
function platform_payment_fee_for(mysqli $db, string $method, string $paid_at, int $amount): ?array {
    if (!function_exists('booking_payment_setting') || booking_payment_setting('G5_PAYMENTS_RECORD_FEE', '0') !== '1') return null;
    try {
        $day = substr($paid_at, 0, 10);
        $st = mysqli_prepare($db, "SELECT rate_bp FROM gangnamunni_payment_fee_rates WHERE pay_method=? AND effective_from<=? ORDER BY effective_from DESC LIMIT 1");
        if (!$st) return null;
        mysqli_stmt_bind_param($st, 'ss', $method, $day);
        $ok = mysqli_stmt_execute($st);
        $row = $ok ? mysqli_fetch_assoc(mysqli_stmt_get_result($st)) : null;
        mysqli_stmt_close($st);
        if (!$row) return null;
        $bp = (int)$row['rate_bp'];
        if ($bp < 0 || $bp > 10000) return null;
        return ['rate_bp' => $bp, 'amount' => platform_payment_fee_amount($amount, $bp)];
    } catch (Throwable $e) {
        error_log('fee rate lookup failed: ' . $e->getMessage());
        return null;
    }
}

function platform_payment_mark_verified(
    mysqli $db,
    string $merchant_uid,
    string $provider,
    string $provider_transaction_id,
    string $provider_event_id,
    int $verified_amount,
    string $payload_sha256,
    string $source = 'server_query',
    ?int $provider_paid_at = null,
    bool $manage_transaction = true,
    ?string $pay_method = null
): bool {
    if (!booking_payment_is_pg($provider)
        || !in_array($source, ['webhook', 'server_query'], true)
        || $provider === '' || $provider_transaction_id === ''
        || $provider_event_id === '' || !preg_match('/^[a-f0-9]{64}$/', $payload_sha256)) {
        throw new InvalidArgumentException('Invalid provider verification evidence.');
    }
    if ($manage_transaction) mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db,
            "SELECT id,amount,status,provider,provider_transaction_id,payment_type
             FROM gangnamunni_platform_payments
             WHERE merchant_uid=? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 's', $merchant_uid);
        mysqli_stmt_execute($stmt);
        $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$payment || (int)$payment['amount'] !== $verified_amount) {
            throw new RuntimeException('Verified amount does not match the order.');
        }
        if ($payment['payment_type'] === 'ad_package') {
            throw new RuntimeException('Legacy ad-package billing has been retired.');
        }
        if ($payment['status'] === 'paid') {
            if ($payment['provider'] !== $provider
                || $payment['provider_transaction_id'] !== $provider_transaction_id) {
                throw new RuntimeException('Conflicting verification evidence.');
            }
            if ($manage_transaction) mysqli_commit($db);
            return true;
        }
        if ($payment['status'] !== 'pending') throw new RuntimeException('Invalid payment state transition.');

        $payment_id = (int)$payment['id'];
        $paid_at = gmdate('Y-m-d H:i:s', ($provider_paid_at ?? time()) + 9 * 3600);
        $event = mysqli_prepare($db,
            "INSERT INTO gangnamunni_payment_events
                (payment_id,provider,provider_event_id,event_type,amount,payload_sha256,provider_occurred_at)
             VALUES (?,?,?,'payment_verified',?,?,?)");
        mysqli_stmt_bind_param($event, 'ississ', $payment_id, $provider, $provider_event_id, $verified_amount, $payload_sha256, $paid_at);
        if (!mysqli_stmt_execute($event)) throw new RuntimeException(mysqli_stmt_error($event));
        mysqli_stmt_close($event);

        // pay_method (card|bank_transfer) is written only when the server switch is on and the value is one of the two known methods.
        $method = platform_payment_method_to_record($pay_method);
        // The fee is fixed now, once: the rate in force on the payment's date (null = not recorded; never blocks the payment).
        $fee = $method !== null ? platform_payment_fee_for($db, $method, $paid_at, $verified_amount) : null;
        $sets = "provider=?,provider_transaction_id=?,verified_amount=?,verified_at=NOW(),verification_source=?,status='paid',paid_at=?";
        $types = 'ssiss';
        $params = [$provider, $provider_transaction_id, $verified_amount, $source, $paid_at];
        if ($method !== null) { $sets .= ',pay_method=?'; $types .= 's'; $params[] = $method; }
        if ($fee !== null) { $sets .= ',fee_rate_bp=?,fee_amount=?'; $types .= 'ii'; $params[] = $fee['rate_bp']; $params[] = $fee['amount']; }
        $types .= 'i';
        $params[] = $payment_id;
        $update = mysqli_prepare($db, "UPDATE gangnamunni_platform_payments SET " . $sets . " WHERE id=? AND status='pending'");
        mysqli_stmt_bind_param($update, $types, ...$params);
        if (!mysqli_stmt_execute($update) || mysqli_stmt_affected_rows($update) !== 1) {
            throw new RuntimeException('Payment verification update failed.');
        }
        mysqli_stmt_close($update);

        $history = mysqli_prepare($db,
            "INSERT INTO gangnamunni_payment_status_history
                (payment_id,old_status,new_status,actor_type,actor_id,reason)
             VALUES (?,'pending','paid','provider',?,'Server-to-server verification')");
        mysqli_stmt_bind_param($history, 'is', $payment_id, $provider);
        if (!mysqli_stmt_execute($history)) throw new RuntimeException(mysqli_stmt_error($history));
        mysqli_stmt_close($history);
        if ($manage_transaction) mysqli_commit($db);
        return true;
    } catch (Throwable $error) {
        if ($manage_transaction) mysqli_rollback($db);
        throw $error;
    }
}

function platform_payment_record_refund(
    mysqli $db,
    int $payment_id,
    string $provider,
    string $provider_event_id,
    int $refund_amount,
    string $payload_sha256,
    bool $manage_transaction = true
): bool {
    if ($refund_amount <= 0 || $provider === '' || $provider_event_id === ''
        || !preg_match('/^[a-f0-9]{64}$/', $payload_sha256)) {
        throw new InvalidArgumentException('Invalid refund evidence.');
    }
    if ($manage_transaction) mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db,
            "SELECT amount,refunded_amount,status,provider,payment_type
             FROM gangnamunni_platform_payments WHERE id=? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $payment_id);
        mysqli_stmt_execute($stmt);
        $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$payment || $payment['provider'] !== $provider
            || !in_array($payment['status'], ['paid','partially_refunded'], true)) {
            throw new RuntimeException('Payment is not refundable.');
        }
        $new_refunded = (int)$payment['refunded_amount'] + $refund_amount;
        if ($new_refunded > (int)$payment['amount']) throw new RuntimeException('Refund exceeds paid amount.');

        $event = mysqli_prepare($db,
            "INSERT INTO gangnamunni_payment_events
                (payment_id,provider,provider_event_id,event_type,amount,payload_sha256,provider_occurred_at)
             VALUES (?,?,?,'refund_verified',?,?,NOW())");
        mysqli_stmt_bind_param($event, 'issis', $payment_id, $provider, $provider_event_id, $refund_amount, $payload_sha256);
        if (!mysqli_stmt_execute($event)) throw new RuntimeException(mysqli_stmt_error($event));
        mysqli_stmt_close($event);

        $new_status = $new_refunded === (int)$payment['amount'] ? 'refunded' : 'partially_refunded';
        $old_status = $payment['status'];
        $update = mysqli_prepare($db,
            "UPDATE gangnamunni_platform_payments
             SET refunded_amount=?,refunded_at=NOW(),status=? WHERE id=?");
        mysqli_stmt_bind_param($update, 'isi', $new_refunded, $new_status, $payment_id);
        if (!mysqli_stmt_execute($update)) throw new RuntimeException(mysqli_stmt_error($update));
        mysqli_stmt_close($update);

        $history = mysqli_prepare($db,
            "INSERT INTO gangnamunni_payment_status_history
                (payment_id,old_status,new_status,actor_type,actor_id,reason)
             VALUES (?,?,?,'provider',?,'Verified provider refund')");
        mysqli_stmt_bind_param($history, 'isss', $payment_id, $old_status, $new_status, $provider);
        if (!mysqli_stmt_execute($history)) throw new RuntimeException(mysqli_stmt_error($history));
        mysqli_stmt_close($history);
        if ($manage_transaction) mysqli_commit($db);
        return true;
    } catch (Throwable $error) {
        if ($manage_transaction) mysqli_rollback($db);
        throw $error;
    }
}

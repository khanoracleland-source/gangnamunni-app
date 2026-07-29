<?php
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
        return ['id' => $id, 'merchant_uid' => $merchant_uid, 'status' => 'pending'];
    } catch (Throwable $error) {
        mysqli_rollback($db);
        throw $error;
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
    string $source = 'server_query'
): bool {
    if (!in_array($source, ['webhook', 'server_query'], true)
        || $provider === '' || $provider_transaction_id === ''
        || $provider_event_id === '' || !preg_match('/^[a-f0-9]{64}$/', $payload_sha256)) {
        throw new InvalidArgumentException('Invalid provider verification evidence.');
    }
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db,
            "SELECT id,amount,status,provider,provider_transaction_id,payment_type,ad_id,pricing_id
             FROM gangnamunni_platform_payments
             WHERE merchant_uid=? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 's', $merchant_uid);
        mysqli_stmt_execute($stmt);
        $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$payment || (int)$payment['amount'] !== $verified_amount) {
            throw new RuntimeException('Verified amount does not match the order.');
        }
        if ($payment['status'] === 'paid') {
            if ($payment['provider'] !== $provider
                || $payment['provider_transaction_id'] !== $provider_transaction_id) {
                throw new RuntimeException('Conflicting verification evidence.');
            }
            mysqli_commit($db);
            return true;
        }
        if ($payment['status'] !== 'pending') throw new RuntimeException('Invalid payment state transition.');

        $payment_id = (int)$payment['id'];
        $event = mysqli_prepare($db,
            "INSERT INTO gangnamunni_payment_events
                (payment_id,provider,provider_event_id,event_type,amount,payload_sha256,provider_occurred_at)
             VALUES (?,?,?,'payment_verified',?,?,NOW())");
        mysqli_stmt_bind_param($event, 'issis', $payment_id, $provider, $provider_event_id, $verified_amount, $payload_sha256);
        if (!mysqli_stmt_execute($event)) throw new RuntimeException(mysqli_stmt_error($event));
        mysqli_stmt_close($event);

        $update = mysqli_prepare($db,
            "UPDATE gangnamunni_platform_payments
             SET provider=?,provider_transaction_id=?,verified_amount=?,verified_at=NOW(),
                 verification_source=?,status='paid',paid_at=NOW()
             WHERE id=? AND status='pending'");
        mysqli_stmt_bind_param($update, 'ssisi', $provider, $provider_transaction_id, $verified_amount, $source, $payment_id);
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
        if ($payment['payment_type'] === 'ad_package' && (int)$payment['ad_id'] > 0) {
            $ad_id = (int)$payment['ad_id'];
            $activate = mysqli_prepare($db,
                "UPDATE gangnamunni_ads a
                 JOIN gangnamunni_platform_payments p ON p.id=? AND p.ad_id=a.id
                 JOIN gangnamunni_ad_pricing pr ON pr.id=p.pricing_id
                 SET a.pricing_id=p.pricing_id,a.is_active=1,a.paid_at=NOW(),a.start_date=NOW(),
                     a.end_date=DATE_ADD(NOW(),INTERVAL pr.duration_days DAY),a.updated_at=NOW()
                 WHERE a.id=? AND pr.is_active=1");
            mysqli_stmt_bind_param($activate, 'ii', $payment_id, $ad_id);
            if (!mysqli_stmt_execute($activate) || mysqli_stmt_affected_rows($activate) !== 1) {
                throw new RuntimeException('Verified ad could not be activated.');
            }
            mysqli_stmt_close($activate);
        }
        mysqli_commit($db);
        return true;
    } catch (Throwable $error) {
        mysqli_rollback($db);
        throw $error;
    }
}

function platform_payment_record_refund(
    mysqli $db,
    int $payment_id,
    string $provider,
    string $provider_event_id,
    int $refund_amount,
    string $payload_sha256
): bool {
    if ($refund_amount <= 0 || $provider === '' || $provider_event_id === ''
        || !preg_match('/^[a-f0-9]{64}$/', $payload_sha256)) {
        throw new InvalidArgumentException('Invalid refund evidence.');
    }
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db,
            "SELECT amount,refunded_amount,status,provider,payment_type,ad_id
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
        if ($payment['payment_type'] === 'ad_package'
            && $new_status === 'refunded' && (int)$payment['ad_id'] > 0) {
            $ad_id = (int)$payment['ad_id'];
            $disable = mysqli_prepare($db,
                "UPDATE gangnamunni_ads SET is_active=0,updated_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($disable, 'i', $ad_id);
            if (!mysqli_stmt_execute($disable)) throw new RuntimeException(mysqli_stmt_error($disable));
            mysqli_stmt_close($disable);
        }
        mysqli_commit($db);
        return true;
    } catch (Throwable $error) {
        mysqli_rollback($db);
        throw $error;
    }
}

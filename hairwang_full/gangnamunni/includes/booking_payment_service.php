<?php
require_once __DIR__ . '/payment_core.php';
require_once __DIR__ . '/booking_payment_access.php';
require_once __DIR__ . '/booking_payment_gateway.php';
require_once __DIR__ . '/points_checkout.php';

function booking_payment_lock(mysqli $db, string $merchant, callable $work) {
    $key = 'payment:' . substr(hash('sha256', $merchant), 0, 48);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 5) AS acquired');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_assoc()['acquired'] !== 1) throw new RuntimeException('Payment is busy. Retry later.');
    try { return $work(); }
    finally {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->bind_param('s', $key);
        $stmt->execute();
    }
}
function booking_payment_by_merchant(mysqli $db, string $merchant): array {
    $stmt = $db->prepare("SELECT * FROM gangnamunni_platform_payments WHERE merchant_uid=? AND payment_type='booking'");
    $stmt->bind_param('s', $merchant);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) throw new RuntimeException('Unknown order.');
    return $order;
}

function booking_payment_refund_audit(mysqli $db, array $order, int $amount, string $actorType, int $actorId, string $actorName, string $status = 'completed'): void {
    $bookingId = (int)($order['booking_id'] ?? 0);
    $paymentId = (int)($order['id'] ?? 0);
    if ($bookingId <= 0 || $paymentId <= 0 || $amount <= 0) return;
    try {
        $stmt = $db->prepare("INSERT INTO gangnamunni_refund_audit
                (booking_id,payment_id,amount,actor_type,actor_id,actor_name,status)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return;
        $stmt->bind_param('iiisiss', $bookingId, $paymentId, $amount, $actorType, $actorId, $actorName, $status);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('[booking_payment_refund_audit] insert failed: ' . $e->getMessage());
    }
}

/**
 * PG 응답(정규화된 형태)을 원장과 대조한다. 회귀 테스트에서도 쓰는 순수 함수.
 * 실결제/샌드박스 구분은 PG 클라이언트가 provider() 로 정한다(예: toss / toss_test).
 */
function booking_payment_validate(array $order, array $remote, string $provider): string {
    if (!booking_payment_is_pg($provider)) throw new RuntimeException('Unknown payment gateway provider.');
    if (($remote['payment_id'] ?? '') !== $order['merchant_uid']
        || !is_int($remote['amount'] ?? null) || $remote['amount'] !== (int)$order['amount']
        || ($remote['currency'] ?? '') !== 'KRW') {
        throw new RuntimeException('Payment identity, amount or currency mismatch.');
    }
    if (in_array($remote['status'] ?? '', ['paid', 'cancelled'], true)) {
        // Transaction identity and time only carry meaning once money exists.
        if (!is_string($remote['transaction_id'] ?? null) || $remote['transaction_id'] === '') {
            throw new RuntimeException('Missing provider transaction ID.');
        }
        if (!is_int($remote['paid_at'] ?? null) || $remote['paid_at'] <= 0 || $remote['paid_at'] > time() + 300) {
            throw new RuntimeException('Invalid provider payment time.');
        }
    }
    // A verified order keeps its original transaction and environment.
    if (!empty($order['provider_transaction_id'])
        && ($order['provider_transaction_id'] !== ($remote['transaction_id'] ?? null) || $order['provider'] !== $provider)) {
        throw new RuntimeException('Conflicting payment transaction.');
    }
    $cancelled = $remote['cancelled_amount'] ?? 0;
    if (!is_int($cancelled) || $cancelled < 0 || $cancelled > (int)$order['amount']) {
        throw new RuntimeException('Invalid refund total.');
    }
    return $provider;
}

/** Call only inside booking_payment_lock, after fetching fresh provider data. */
function booking_payment_apply_remote(mysqli $db, array $order, array $remote, string $source, string $provider): array {
    $provider = booking_payment_validate($order, $remote, $provider);
    $status = $remote['status'] ?? '';
    $validStates = ['ready', 'paid', 'failed', 'cancelled'];
    if (!in_array($status, $validStates, true)) {
        throw new RuntimeException('Unknown payment state: ' . $status);
    }
    $currentStatus = (string)($order['status'] ?? '');
    $validTransitions = [
        'pending' => ['ready', 'paid', 'failed', 'cancelled'],
        'paid' => ['paid', 'cancelled'],
        'partially_refunded' => ['paid', 'cancelled'],
        'failed' => ['ready'],
        'cancelled' => ['cancelled'],
        'refunded' => ['cancelled'],
    ];
    $allowed = $validTransitions[$currentStatus] ?? [];
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException("Invalid transition {$currentStatus} -> {$status}");
    }
    if (in_array($status, ['ready', 'failed'], true)) return $order;
    $cancelled = (int)($remote['cancelled_amount'] ?? 0);
    if ($status === 'cancelled' && $cancelled === 0) throw new RuntimeException('Cancellation total is missing.');
    $hash = hash('sha256', json_encode($remote, JSON_THROW_ON_ERROR));
    $db->begin_transaction();
    try {
        $existingEvent = $db->prepare("SELECT id FROM gangnamunni_payment_events WHERE payment_id=? AND payload_sha256=? LIMIT 1");
        $existingEvent->bind_param('is', $order['id'], $hash);
        $existingEvent->execute();
        if ($existingEvent->get_result()->num_rows > 0) {
            $db->commit();
            return booking_payment_by_merchant($db, $order['merchant_uid']);
        }
        if ($order['status'] === 'pending') {
            platform_payment_mark_verified($db, $order['merchant_uid'], $provider, $remote['transaction_id'],
                'paid:' . $remote['transaction_id'], (int)$remote['amount'], $hash, $source, (int)$remote['paid_at'], false);
            $order = booking_payment_by_merchant($db, $order['merchant_uid']);
        }
        if ($cancelled < (int)$order['refunded_amount']) throw new RuntimeException('Provider refund total regressed.');
        if ($cancelled > (int)$order['refunded_amount']) {
            $refundDelta = $cancelled - (int)$order['refunded_amount'];
            platform_payment_record_refund($db, (int)$order['id'], $provider,
                'refund:' . $remote['transaction_id'] . ':' . $cancelled,
                $refundDelta, $hash, false);
            if ($source === 'webhook') {
                booking_payment_refund_audit($db, $order, $refundDelta, 'webhook', 0, 'PG Webhook');
            }
        }
        $confirmed = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='succeeded',completed_at=NOW()
            WHERE payment_id=? AND status='pending' AND refunded_before+amount<=?");
        $confirmed->bind_param('ii', $order['id'], $cancelled);
        $confirmed->execute();
        $result = booking_payment_by_merchant($db, $order['merchant_uid']);
        // 포인트 결제(#포인트제도 v2): 결제 완료 → 홀드 확정, 전액 취소 → 사용 포인트 복구(같은 트랜잭션)
        points_checkout_after_sync($db, $result);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
function booking_payment_sync(mysqli $db, string $merchant, string $source = 'server_query', ?BookingPaymentGateway $gateway = null): array {
    $gateway = $gateway ?? booking_payment_gateway();
    // No PG connected: no payment can exist outside our ledger, so the ledger is the answer.
    if (!$gateway) return booking_payment_by_merchant($db, $merchant);
    return booking_payment_lock($db, $merchant, function () use ($db, $merchant, $source, $gateway) {
        $order = booking_payment_by_merchant($db, $merchant);
        return booking_payment_apply_remote($db, $order, $gateway->payment($merchant), $source, $gateway->provider());
    });
}
function booking_payment_refund(mysqli $db, string $merchant, int $amount, string $reason, string $requestId, int $actorId, ?BookingPaymentGateway $gateway = null): array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestId) || $actorId < 0 || trim($reason) === '' || strlen($reason) > 500) throw new InvalidArgumentException('Invalid refund request.');
    $gateway = $gateway ?? booking_payment_require_gateway();
    $provider = $gateway->provider();
    return booking_payment_lock($db, $merchant, function () use ($db, $merchant, $amount, $reason, $requestId, $actorId, $gateway, $provider) {
        $order = booking_payment_by_merchant($db, $merchant);
        if (!booking_payment_is_pg($order['provider']) || empty($order['provider_transaction_id'])) {
            throw new RuntimeException('Only verified PG payments can be refunded.');
        }
        $request = $db->prepare("SELECT * FROM gangnamunni_payment_refund_requests WHERE request_id=?");
        $request->bind_param('s', $requestId);
        $request->execute();
        $previous = $request->get_result()->fetch_assoc();
        if ($previous) {
            if ((int)$previous['payment_id'] !== (int)$order['id']) {
                throw new RuntimeException('Refund ID conflict: payment mismatch.');
            }
            if ((int)$previous['amount'] !== $amount) {
                throw new RuntimeException('Refund ID conflict: amount mismatch.');
            }
        }
        $before = (int)$order['refunded_amount'];
        $remote = $gateway->payment($merchant);
        $order = booking_payment_apply_remote($db, $order, $remote, 'server_query', $provider);
        if ($previous) {
            if ($previous['status'] === 'failed') throw new RuntimeException('This refund request was rejected. Use a new request after review.');
            if ($previous['status'] === 'succeeded') return $order;
            if ((int)$order['refunded_amount'] >= (int)$previous['refunded_before'] + $amount) {
                $done = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='succeeded',completed_at=NOW() WHERE request_id=?");
                $done->bind_param('s', $requestId);
                $done->execute();
                return $order;
            }
            throw new RuntimeException('Refund outcome is pending. Reconcile before requesting another refund.');
        }
        $pending = $db->prepare("SELECT request_id FROM gangnamunni_payment_refund_requests WHERE payment_id=? AND status='pending' LIMIT 1");
        $pending->bind_param('i', $order['id']);
        $pending->execute();
        if ($pending->get_result()->num_rows) throw new RuntimeException('An earlier refund requires reconciliation.');
        if ((int)$order['refunded_amount'] !== $before) {
            throw new RuntimeException('Refund state refreshed. Review the remaining amount before making a new request.');
        }
        $orderAmount = (int)$order['amount'];
        $refundedAmount = (int)$order['refunded_amount'];
        if ($orderAmount <= 0) {
            throw new RuntimeException('Invalid order amount in database.');
        }
        $remaining = $orderAmount - $refundedAmount;
        if (!in_array($order['status'], ['paid', 'partially_refunded'], true)) {
            throw new RuntimeException('Cannot refund order in ' . $order['status'] . ' status.');
        }
        if ($amount <= 0 || $amount > $remaining) {
            throw new RuntimeException('Refund amount must be 1-' . $remaining);
        }
        $save = $db->prepare("INSERT INTO gangnamunni_payment_refund_requests (request_id,payment_id,amount,refunded_before,actor_id,reason) VALUES (?,?,?,?,?,?)");
        $save->bind_param('siiiis', $requestId, $order['id'], $amount, $before, $actorId, $reason);
        $save->execute();
        try {
            $gateway->cancel($merchant, (string)$order['provider_transaction_id'], $amount, $remaining, $reason, $requestId);
        } catch (BookingPaymentRejectedException $e) {
            $failed = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='failed',completed_at=NOW() WHERE request_id=?");
            $failed->bind_param('s', $requestId);
            $failed->execute();
            throw $e;
        }
        $result = booking_payment_apply_remote($db, $order, $gateway->payment($merchant), 'server_query', $provider);
        if ((int)$result['refunded_amount'] !== $before + $amount) throw new RuntimeException('Refund confirmation pending.');
        $done = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='succeeded',completed_at=NOW() WHERE request_id=?");
        $done->bind_param('s', $requestId);
        $done->execute();
        booking_payment_refund_audit($db, $result, $amount, $actorId > 0 ? 'admin' : 'system', $actorId, $actorId > 0 ? 'Admin (' . $actorId . ')' : 'System');
        return $result;
    });
}

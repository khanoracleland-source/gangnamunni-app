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
/**
 * 예약 취소처럼 결제 승인(booking_payment_confirm)과 겹칠 수 있는 작업을 같은 결제 잠금 안에서 실행한다.
 * 결제 건이 없으면 잠금 없이 바로 실행한다.
 */
function booking_payment_lock_for_booking(mysqli $db, int $bookingId, callable $work) {
    $order = booking_payment_order($db, $bookingId);
    if (!$order || empty($order['merchant_uid'])) return $work();
    return booking_payment_lock($db, (string)$order['merchant_uid'], $work);
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
    if (($remote['payment_id'] ?? '') !== $order['merchant_uid']) {
        throw new RuntimeException('Payment identity mismatch.');
    }
    $settled = in_array($remote['status'] ?? '', ['paid', 'cancelled'], true);
    // Unsettled attempts (not yet confirmed, expired) move no money, so an amount that
    // differs from the ledger (e.g. points changed between attempts) is not an error.
    if ($settled && (!is_int($remote['amount'] ?? null) || $remote['amount'] !== (int)$order['amount']
        || ($remote['currency'] ?? '') !== 'KRW')) {
        throw new RuntimeException('Payment amount or currency mismatch.');
    }
    if ($settled) {
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
    $notifyRefundedBookingId = 0;
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
        // 전액 환불이 원장에 반영되면 예약도 같은 트랜잭션에서 환불완료로 맞춘다(관리자 환불·Toss 콘솔 환불·웹훅 공통).
        // 예약 status 는 취소 상태일 때만 'refunded' 로 바꾸고, refund_status 는 항상 맞춘다.
        if (($result['status'] ?? '') === 'refunded' && (int)($result['booking_id'] ?? 0) > 0) {
            $bookingId = (int)$result['booking_id'];
            $sync = $db->prepare("UPDATE gangnamunni_bookings
                SET status=IF(status='cancelled','refunded',status), refund_status='refunded', updated_at=NOW()
                WHERE id=? AND refund_status<>'refunded'");
            $sync->bind_param('i', $bookingId);
            if (!$sync->execute()) throw new RuntimeException('Booking refund status sync failed.');
            if ($sync->affected_rows === 1) $notifyRefundedBookingId = $bookingId;
            $sync->close();
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    // 알림은 커밋 후 한 번만(실패해도 환불 처리에는 영향 없음).
    if ($notifyRefundedBookingId > 0) {
        try {
            require_once __DIR__ . '/booking_notifications.php';
            $booking = booking_notification_fetch_booking_by_id($db, $notifyRefundedBookingId);
            if ($booking) notify_booking_member($db, $booking, 'refunded');
        } catch (Throwable $e) {
            error_log('[booking_payment_apply_remote] refund notification failed: ' . $e->getMessage());
        }
    }
    return $result;
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

/**
 * 2단계 결제의 서버 승인(성공 URL 에서 호출).
 *
 * 승인 직전에 잠금 안에서 원장·예약·포인트를 다시 확인한다. 하나라도 어긋나면 승인하지 않으며,
 * 이 경우 돈은 빠져나가지 않고 PG 의 결제 건은 만료된다(사용자는 다시 결제하면 된다).
 *
 * @return array{outcome:string, reason:string, order:array}
 *   outcome: paid(승인·반영 완료) | refused(우리가 승인 거부) | rejected(PG/카드사 거절) | pending(결과 확인 중)
 */
function booking_payment_confirm(mysqli $db, string $merchant, string $paymentKey, int $amount, ?BookingPaymentGateway $gateway = null): array {
    $gateway = $gateway ?? booking_payment_require_gateway();
    $provider = $gateway->provider();
    return booking_payment_lock($db, $merchant, function () use ($db, $merchant, $paymentKey, $amount, $gateway, $provider) {
        $order = booking_payment_by_merchant($db, $merchant);
        // Toss 가이드: 승인 전에 성공 URL 의 paymentKey·orderId·amount 를 서버에 저장한다.
        booking_payment_attempt_save($db, $order, $provider, $paymentKey, $amount, 'received', null);
        $result = function (string $outcome, string $reason, array $order) use ($db, $provider, $paymentKey, $amount): array {
            booking_payment_attempt_save($db, $order, $provider, $paymentKey, $amount, $outcome, $reason);
            return ['outcome' => $outcome, 'reason' => $reason, 'order' => $order];
        };
        if (in_array($order['status'], ['paid', 'partially_refunded'], true)
            && hash_equals((string)$order['provider_transaction_id'], $paymentKey)) {
            return $result('paid', 'ALREADY_CONFIRMED', $order);   // 새로고침 등 재진입
        }
        if ($order['status'] !== 'pending') return $result('refused', 'ORDER_STATE', $order);
        if ($amount !== (int)$order['amount'] || $amount <= 0) return $result('refused', 'AMOUNT_MISMATCH', $order);
        $stmt = $db->prepare('SELECT status FROM gangnamunni_bookings WHERE id=?');
        $bookingId = (int)$order['booking_id'];
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking || ($booking['status'] ?? '') !== 'confirmed') return $result('refused', 'BOOKING_NOT_CONFIRMED', $order);
        $pointsUsed = (int)($order['points_used'] ?? 0);
        if ($pointsUsed > 0) {
            $hold = function_exists('points_checkout_active_hold') ? points_checkout_active_hold($db, (int)$order['id']) : null;
            if (!$hold || ($hold['status'] ?? '') !== 'held' || (int)$hold['points'] !== $pointsUsed) {
                return $result('refused', 'POINT_HOLD_EXPIRED', $order);
            }
        }
        try {
            $remote = $gateway->confirm($merchant, $paymentKey, $amount);
        } catch (BookingPaymentRejectedException $e) {
            return $result('rejected', $e->getMessage(), $order);
        } catch (Throwable $e) {
            // Outcome unknown (timeout, PG error, already processed): ask the PG what happened.
            error_log('[booking_payment_confirm] ' . $e->getMessage());
            try {
                $remote = $gateway->payment($merchant);
            } catch (Throwable $e2) {
                error_log('[booking_payment_confirm] re-query: ' . $e2->getMessage());
                return $result('pending', 'UNRESOLVED', $order);
            }
        }
        if (($remote['transaction_id'] ?? null) !== null && $remote['transaction_id'] !== $paymentKey
            && ($remote['status'] ?? '') !== 'ready') {
            error_log('[booking_payment_confirm] PG returned a different paymentKey for ' . $merchant);
        }
        $order = booking_payment_apply_remote($db, $order, $remote, 'server_query', $provider);
        if (in_array($order['status'], ['paid', 'partially_refunded'], true)) return $result('paid', 'CONFIRMED', $order);
        return $result('pending', (string)($remote['raw_status'] ?? 'UNKNOWN'), $order);
    });
}

/** 승인 시도 기록(gangnamunni_payment_attempts). 같은 paymentKey 는 한 행을 갱신한다. */
function booking_payment_attempt_save(mysqli $db, array $order, string $provider, string $paymentKey, int $amount, string $outcome, ?string $reason): void {
    $paymentId = (int)$order['id'];
    $merchant = (string)$order['merchant_uid'];
    $amount = max(0, $amount);
    $reason = $reason !== null ? substr($reason, 0, 100) : null;
    $stmt = $db->prepare("INSERT INTO gangnamunni_payment_attempts
            (payment_id, merchant_uid, provider, pg_payment_key, amount, outcome, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE outcome = VALUES(outcome), reason = VALUES(reason)");
    $stmt->bind_param('isssiss', $paymentId, $merchant, $provider, $paymentKey, $amount, $outcome, $reason);
    $stmt->execute();
}

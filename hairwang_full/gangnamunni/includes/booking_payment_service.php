<?php
require_once __DIR__ . '/payment_core.php';
require_once __DIR__ . '/booking_payment_access.php';
require_once __DIR__ . '/portone_client.php';

/** Serialize all provider observations for an order, including admin refunds. */
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
/** Call only inside booking_payment_lock, after fetching fresh provider data. */
function booking_payment_apply_remote(mysqli $db, array $order, array $remote, string $source): array {
    $provider = booking_portone_validate($order, $remote);
    $status = $remote['status'] ?? '';
    if (!in_array($status, ['paid', 'cancelled', 'ready', 'failed'], true)) throw new RuntimeException('Unknown payment state.');
    // Failed attempts remain retryable. Never erase a verified payment on an old failure.
    if (in_array($status, ['ready', 'failed'], true)) return $order;
    $cancelled = (int)($remote['cancel_amount'] ?? 0);
    if ($status === 'cancelled' && $cancelled === 0) throw new RuntimeException('Cancellation total is missing.');
    $db->begin_transaction();
    try {
        $hash = hash('sha256', json_encode($remote, JSON_THROW_ON_ERROR));
        if ($order['status'] === 'pending') {
            platform_payment_mark_verified($db, $order['merchant_uid'], $provider, $remote['imp_uid'],
                'paid:' . $remote['imp_uid'], (int)$remote['amount'], $hash, $source, (int)$remote['paid_at'], false);
            $order = booking_payment_by_merchant($db, $order['merchant_uid']);
        }
        if ($cancelled < (int)$order['refunded_amount']) throw new RuntimeException('Provider refund total regressed.');
        if ($cancelled > (int)$order['refunded_amount']) {
            platform_payment_record_refund($db, (int)$order['id'], $provider,
                'refund:' . $remote['imp_uid'] . ':' . $cancelled,
                $cancelled - (int)$order['refunded_amount'], $hash, false);
        }
        // A webhook/cron may confirm a refund whose HTTP response was lost.
        $confirmed = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='succeeded',completed_at=NOW()
            WHERE payment_id=? AND status='pending' AND refunded_before+amount<=?");
        $confirmed->bind_param('ii', $order['id'], $cancelled);
        $confirmed->execute();
        $result = booking_payment_by_merchant($db, $order['merchant_uid']);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
function booking_payment_sync(mysqli $db, string $merchant, ?string $imp, string $source = 'server_query', ?BookingPortOneClient $client = null): array {
    $client = $client ?? new BookingPortOneClient();
    return booking_payment_lock($db, $merchant, function () use ($db, $merchant, $imp, $source, $client) {
        $order = booking_payment_by_merchant($db, $merchant);
        // Remote data is fetched AFTER acquiring the lock to avoid stale observations.
        $lookup = $imp ?: ($order['provider_transaction_id'] ?? null);
        $remote = $lookup ? $client->payment($lookup) : $client->find($merchant);
        if ($lookup && ($remote['imp_uid'] ?? '') !== $lookup) throw new RuntimeException('Provider transaction ID mismatch.');
        return booking_payment_apply_remote($db, $order, $remote, $source);
    });
}
function booking_payment_refund(mysqli $db, string $merchant, int $amount, string $reason, string $requestId, int $actorId, ?BookingPortOneClient $client = null): array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestId) || $actorId <= 0 || trim($reason) === '' || strlen($reason) > 500) throw new InvalidArgumentException('Invalid refund request.');
    $client = $client ?? new BookingPortOneClient();
    return booking_payment_lock($db, $merchant, function () use ($db, $merchant, $amount, $reason, $requestId, $actorId, $client) {
        $order = booking_payment_by_merchant($db, $merchant);
        if (!in_array($order['provider'], ['portone', 'portone_test'], true) || empty($order['provider_transaction_id'])) {
            throw new RuntimeException('Only verified PortOne payments can be refunded.');
        }
        $request = $db->prepare("SELECT * FROM gangnamunni_payment_refund_requests WHERE request_id=?");
        $request->bind_param('s', $requestId);
        $request->execute();
        $previous = $request->get_result()->fetch_assoc();
        if ($previous && ((int)$previous['payment_id'] !== (int)$order['id'] || (int)$previous['amount'] !== $amount)) {
            throw new RuntimeException('Conflicting refund request ID.');
        }
        $before = (int)$order['refunded_amount'];
        $remote = $client->payment($order['provider_transaction_id']);
        $order = booking_payment_apply_remote($db, $order, $remote, 'server_query');
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
            // A prior timed-out cancellation succeeded: sync it; do not send another refund.
            throw new RuntimeException('Refund state refreshed. Review the remaining amount before making a new request.');
        }
        $remaining = (int)$order['amount'] - (int)$order['refunded_amount'];
        if (!in_array($order['status'], ['paid', 'partially_refunded'], true) || $amount <= 0 || $amount > $remaining) {
            throw new RuntimeException('Invalid refund amount or state.');
        }
        $save = $db->prepare("INSERT INTO gangnamunni_payment_refund_requests (request_id,payment_id,amount,refunded_before,actor_id,reason) VALUES (?,?,?,?,?,?)");
        $save->bind_param('siiiis', $requestId, $order['id'], $amount, $before, $actorId, $reason);
        $save->execute(); // Persist intent before any remote side effect.
        try {
            $client->cancel($order['provider_transaction_id'], $amount, $remaining, $reason);
        } catch (BookingPortOneRejectedException $e) {
            // A valid PortOne error response confirms the request was rejected.
            // Transport errors and timeouts remain pending to prevent double refunds.
            $failed = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='failed',completed_at=NOW() WHERE request_id=?");
            $failed->bind_param('s', $requestId);
            $failed->execute();
            throw $e;
        }
        $result = booking_payment_apply_remote($db, $order, $client->payment($order['provider_transaction_id']), 'server_query');
        if ((int)$result['refunded_amount'] !== $before + $amount) throw new RuntimeException('Refund confirmation pending.');
        $done = $db->prepare("UPDATE gangnamunni_payment_refund_requests SET status='succeeded',completed_at=NOW() WHERE request_id=?");
        $done->bind_param('s', $requestId);
        $done->execute();
        return $result;
    });
}

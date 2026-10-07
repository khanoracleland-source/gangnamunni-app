<?php

require_once __DIR__ . '/points_ledger.php';

if (!defined('POINTS_CHECKOUT_LOADED')) {
    define('POINTS_CHECKOUT_LOADED', 1);

    function points_checkout_ready($connect) {
        return points_v2_on($connect, 'v2_point_use_enabled');
    }

    function points_checkout_active_hold($connect, $payment_id) {
        if (!points_v2_schema_ready($connect)) return null;
        $st = mysqli_prepare($connect, "SELECT * FROM gangnamunni_point_payment_holds
            WHERE payment_id = ? AND status IN ('held','captured') ORDER BY id DESC LIMIT 1");
        if (!$st) return null;
        $pid = (int)$payment_id;
        mysqli_stmt_bind_param($st, 'i', $pid);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
        mysqli_stmt_close($st);
        return $row;
    }

    function points_checkout_max($connect, $member_no, $gross, $already_held = 0) {
        $bal = max(0, (int)points_balance_safe($connect, $member_no)) + max(0, (int)$already_held);
        $cap = max(0, (int)$gross - points_v2_int($connect, 'v2_point_use_min_pay', 1000));
        return max(0, min($bal, $cap));
    }

    function points_checkout_order_reset($connect, $payment_id) {
        $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments
            SET amount = COALESCE(gross_amount, amount), points_used = 0
            WHERE id = ? AND status = 'pending'");
        $pid = (int)$payment_id;
        mysqli_stmt_bind_param($st, 'i', $pid);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

 
    function points_checkout_apply($connect, array $order, $member_no, $point_use) {
        $point_use = max(0, (int)$point_use);
        $member_no = (int)$member_no;
        if (($order['status'] ?? '') !== 'pending') throw new RuntimeException('결제할 수 없는 주문 상태입니다.');
        $payment_id = (int)$order['id'];
        $gross = (int)($order['gross_amount'] ?? 0);
        if ($gross <= 0) $gross = (int)$order['amount'] + (int)($order['points_used'] ?? 0);
        $hold = points_checkout_active_hold($connect, $payment_id);
        $held = $hold ? (int)$hold['points'] : 0;

        if ($point_use > 0) {
            if (!points_checkout_ready($connect)) throw new RuntimeException('포인트 결제는 현재 이용할 수 없습니다.');
            if ($member_no <= 0) throw new RuntimeException('포인트는 회원만 사용할 수 있습니다.');
            if ($hold && (int)$hold['member_id'] !== $member_no) throw new RuntimeException('다른 회원의 포인트가 적용된 주문입니다.');
            $max = points_checkout_max($connect, $member_no, $gross, $held);
            if ($point_use > $max) throw new RuntimeException('사용 가능한 포인트는 최대 ' . number_format($max) . 'P 입니다.');
        }
        if ($held === $point_use) {
            return ['amount' => $gross - $held, 'gross' => $gross, 'points' => $held];
        }

        if ($hold) {
            points_checkout_release_hold($connect, $hold, '결제 포인트 변경');
        }
        if ($point_use === 0) {
            $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments SET gross_amount = ?, points_used = 0, amount = ? WHERE id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($st, 'iii', $gross, $gross, $payment_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            return ['amount' => $gross, 'gross' => $gross, 'points' => 0];
        }

        $booking_id = (int)($order['booking_id'] ?? 0);
        $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_point_payment_holds (payment_id, booking_id, member_id, points, status) VALUES (?, ?, ?, ?, 'held')");
        mysqli_stmt_bind_param($ins, 'iiii', $payment_id, $booking_id, $member_no, $point_use);
        if (!mysqli_stmt_execute($ins)) throw new RuntimeException('포인트 적용에 실패했습니다.');
        $hold_id = (int)mysqli_insert_id($connect);
        mysqli_stmt_close($ins);

        $r = points_txn($connect, $member_no, 'use', $point_use, '결제 시 포인트 사용 (예약 #' . $booking_id . ')', 'point_hold', $hold_id);
        if (empty($r['ok']) || !empty($r['skipped']) || empty($r['point_id'])) {
            $f = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET status='released' WHERE id = ?");
            mysqli_stmt_bind_param($f, 'i', $hold_id); mysqli_stmt_execute($f); mysqli_stmt_close($f);
            throw new RuntimeException(($r['error'] ?? '') === 'insufficient' ? '보유 포인트가 부족합니다.' : '포인트 적용에 실패했습니다.');
        }
        $use_pid = (int)$r['point_id'];
        $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET use_point_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($u, 'ii', $use_pid, $hold_id); mysqli_stmt_execute($u); mysqli_stmt_close($u);

        $amount = $gross - $point_use;
        $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments SET gross_amount = ?, points_used = ?, amount = ? WHERE id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($st, 'iiii', $gross, $point_use, $amount, $payment_id);
        mysqli_stmt_execute($st);
        $changed = mysqli_stmt_affected_rows($st);
        mysqli_stmt_close($st);
        if ($changed < 0) throw new RuntimeException('주문 금액 갱신에 실패했습니다.');
        return ['amount' => $amount, 'gross' => $gross, 'points' => $point_use];
    }

    function points_checkout_release_hold($connect, array $hold, $reason, $final_status = 'released') {
        $hold_id = (int)$hold['id'];
        $r = ['ok' => true, 'skipped' => true];
        if ((int)$hold['points'] > 0 && !empty($hold['use_point_id'])) {
            $r = points_txn($connect, (int)$hold['member_id'], 'refund', (int)$hold['points'], $reason, 'point_hold', $hold_id, null,
                ['restore_from' => (int)$hold['use_point_id']]);
            if (empty($r['ok'])) throw new RuntimeException('포인트 복구에 실패했습니다.');
        }
        $rp = (int)($r['point_id'] ?? 0) ?: null;
        $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET status = ?, restore_point_id = COALESCE(?, restore_point_id) WHERE id = ?");
        mysqli_stmt_bind_param($u, 'sii', $final_status, $rp, $hold_id);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
        return $r;
    }

    function points_checkout_release($connect, $payment_id, $reason = '결제 미완료 포인트 복구') {
        $hold = points_checkout_active_hold($connect, $payment_id);
        if (!$hold || $hold['status'] !== 'held') return false;
        points_checkout_release_hold($connect, $hold, $reason, 'released');
        points_checkout_order_reset($connect, $payment_id);
        return true;
    }


    function points_checkout_capture($connect, $payment_id) {
        if (!points_v2_schema_ready($connect)) return;
        $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET status='captured' WHERE payment_id = ? AND status='held'");
        $pid = (int)$payment_id;
        mysqli_stmt_bind_param($u, 'i', $pid);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
    }

    function points_checkout_restore_on_refund($connect, array $order) {
        if (($order['status'] ?? '') !== 'refunded') return false;
        $hold = points_checkout_active_hold($connect, (int)$order['id']);
        if (!$hold) return false;
        points_checkout_release_hold($connect, $hold, '결제 취소 포인트 복구 (예약 #' . (int)$hold['booking_id'] . ')', 'restored');
        return true;
    }

    function points_checkout_after_sync($connect, array $order) {
        if (!points_v2_schema_ready($connect)) return;
        $status = (string)($order['status'] ?? '');
        if (in_array($status, ['paid', 'partially_refunded'], true)) points_checkout_capture($connect, (int)$order['id']);
        if ($status === 'refunded') points_checkout_restore_on_refund($connect, $order);
    }

   
    function points_checkout_release_for_booking($connect, $booking_id, $reason = '예약 취소 포인트 복구') {
        if (!points_v2_schema_ready($connect)) return false;
        require_once __DIR__ . '/booking_payment_service.php';
        $order = booking_payment_order($connect, (int)$booking_id);
        if (!$order || ($order['status'] ?? '') !== 'pending' || !points_checkout_active_hold($connect, (int)$order['id'])) return false;
        $merchant = (string)$order['merchant_uid'];
        return booking_payment_lock($connect, $merchant, function () use ($connect, $merchant, $reason) {
            try {
                booking_payment_apply_remote($connect, booking_payment_by_merchant($connect, $merchant),
                    (new BookingPortOneClient())->payment($merchant), 'server_query');
            } catch (BookingPortOneRejectedException $e) {
            }
            $fresh = booking_payment_by_merchant($connect, $merchant);
            return ($fresh['status'] ?? '') === 'pending' ? points_checkout_release($connect, (int)$fresh['id'], $reason) : false;
        });
    }
}

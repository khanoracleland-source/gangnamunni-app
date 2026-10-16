<?php

require_once __DIR__ . '/points_ledger.php';

if (!defined('POINTS_CHECKOUT_LOADED')) {
    define('POINTS_CHECKOUT_LOADED', 1);

    /** @param \mysqli $connect */
    function points_checkout_ready($connect)
    {
        return points_v2_on($connect, 'v2_point_use_enabled');
    }

    /**
     * @param \mysqli    $connect
     * @param int|string $payment_id
     */
    function points_checkout_active_hold($connect, $payment_id)
    {
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

    /**
     * @param \mysqli    $connect
     * @param int|string $member_no
     * @param int|string $gross
     * @param int|string $already_held
     */
    function points_checkout_max($connect, $member_no, $gross, $already_held = 0)
    {
        $bal = max(0, (int)points_balance_safe($connect, $member_no)) + max(0, (int)$already_held);
        $cap = max(0, (int)$gross - points_v2_int($connect, 'v2_point_use_min_pay', 1000));
        return max(0, min($bal, $cap));
    }

    /**
     * @param \mysqli    $connect
     * @param int|string $payment_id
     */
    function points_checkout_order_reset($connect, $payment_id)
    {
        $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments
            SET amount = COALESCE(gross_amount, amount), points_used = 0
            WHERE id = ? AND status = 'pending'");
        $pid = (int)$payment_id;
        mysqli_stmt_bind_param($st, 'i', $pid);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }


    /**
     * @param \mysqli    $connect
     * @param int|string $member_no
     * @param int|string $point_use
     */
    function points_checkout_apply($connect, array $order, $member_no, $point_use)
    {
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
            // 같은 포인트로 "결제하기"를 다시 누른 경우: 방치 홀드 cron(created_at 기준)이 결제 진행 중에 풀지 않도록 시각을 갱신한다.
            if ($hold && $hold['status'] === 'held') {
                $touch = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET created_at = NOW() WHERE id = ? AND status = 'held'");
                $hid = (int)$hold['id'];
                mysqli_stmt_bind_param($touch, 'i', $hid);
                mysqli_stmt_execute($touch);
                mysqli_stmt_close($touch);
            }
            return ['amount' => $gross - $held, 'gross' => $gross, 'points' => $held];
        }

        // 아래 세 가지는 반드시 함께 일어나야 한다: (1) 기존 홀드 해제(포인트 환원),
        // (2) 새 홀드 + 포인트 차감, (3) 주문 금액(amount/points_used) 갱신.
        // 호출부(booking_payment_request.php)는 GET_LOCK 만 잡고 트랜잭션은 열지 않으므로
        // 예전에는 각 문장이 개별 커밋됐다. 그래서 (1) 뒤에 (2)나 (3)이 실패하면
        // "포인트는 회원에게 돌아갔는데 주문 금액은 여전히 할인가" 상태가 남고,
        // 그 상태로 결제를 승인하면 할인분을 플랫폼이 떠안게 된다.
        // points_txn()/points_ledger.php 는 자체 트랜잭션을 쓰지 않고 GET_LOCK 만 쓰므로
        // 여기서 트랜잭션을 열어도 중첩 문제가 없다.
        mysqli_begin_transaction($connect);
        try {
            if ($hold) {
                points_checkout_release_hold($connect, $hold, '결제 포인트 변경');
            }
            if ($point_use === 0) {
                $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments SET gross_amount = ?, points_used = 0, amount = ? WHERE id = ? AND status = 'pending'");
                mysqli_stmt_bind_param($st, 'iii', $gross, $gross, $payment_id);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
                mysqli_commit($connect);
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
                // 롤백이 이 홀드 INSERT 까지 되돌리지만, 메시지는 그대로 사용자에게 전달한다.
                throw new RuntimeException(($r['error'] ?? '') === 'insufficient' ? '보유 포인트가 부족합니다.' : '포인트 적용에 실패했습니다.');
            }
            $use_pid = (int)$r['point_id'];
            $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET use_point_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($u, 'ii', $use_pid, $hold_id);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);

            $amount = $gross - $point_use;
            $st = mysqli_prepare($connect, "UPDATE gangnamunni_platform_payments SET gross_amount = ?, points_used = ?, amount = ? WHERE id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($st, 'iiii', $gross, $point_use, $amount, $payment_id);
            mysqli_stmt_execute($st);
            $changed = mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);
            // 주문이 더 이상 pending 이 아니면(그 사이 승인/취소) 할인가를 쓰면 안 된다.
            if ($changed !== 1) throw new RuntimeException('주문 금액 갱신에 실패했습니다.');
            mysqli_commit($connect);
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            throw $e;
        }
        return ['amount' => $amount, 'gross' => $gross, 'points' => $point_use];
    }

    /**
     * @param \mysqli    $connect
     * @param string     $reason
     * @param string     $final_status
     */
    function points_checkout_release_hold($connect, array $hold, $reason, $final_status = 'released')
    {
        $hold_id = (int)$hold['id'];
        $r = ['ok' => true, 'skipped' => true];
        if ((int)$hold['points'] > 0 && !empty($hold['use_point_id'])) {
            $r = points_txn(
                $connect,
                (int)$hold['member_id'],
                'refund',
                (int)$hold['points'],
                $reason,
                'point_hold',
                $hold_id,
                null,
                ['restore_from' => (int)$hold['use_point_id']]
            );
            if (empty($r['ok'])) throw new RuntimeException('포인트 복구에 실패했습니다.');
        }
        $rp = (int)($r['point_id'] ?? 0) ?: null;
        $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET status = ?, restore_point_id = COALESCE(?, restore_point_id) WHERE id = ?");
        mysqli_stmt_bind_param($u, 'sii', $final_status, $rp, $hold_id);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
        return $r;
    }

    /**
     * @param \mysqli    $connect
     * @param int|string $payment_id
     * @param string     $reason
     */
    function points_checkout_release($connect, $payment_id, $reason = '결제 미완료 포인트 복구')
    {
        $hold = points_checkout_active_hold($connect, $payment_id);
        if (!$hold || $hold['status'] !== 'held') return false;
        // 홀드 해제와 주문 금액 원복은 한 묶음이어야 한다 — 사이에서 끊기면
        // 포인트는 환원됐는데 주문은 할인가로 남는다(points_checkout_apply 참고).
        mysqli_begin_transaction($connect);
        try {
            points_checkout_release_hold($connect, $hold, $reason, 'released');
            points_checkout_order_reset($connect, $payment_id);
            mysqli_commit($connect);
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            throw $e;
        }
        return true;
    }


    /**
     * @param \mysqli    $connect
     * @param int|string $payment_id
     */
    function points_checkout_capture($connect, $payment_id)
    {
        if (!points_v2_schema_ready($connect)) return;
        $pid = (int)$payment_id;

        $u = mysqli_prepare($connect, "UPDATE gangnamunni_point_payment_holds SET status='captured' WHERE payment_id = ? AND status='held'");
        mysqli_stmt_bind_param($u, 'i', $pid);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        // 마지막 안전장치: 주문이 포인트 할인가로 승인됐다면 그만큼의 포인트가
        // 실제로 확정(captured)돼 있어야 한다. 이전에는 홀드가 없어도 위 UPDATE 가
        // 0행을 바꾸고 조용히 넘어가서, "포인트는 회원이 그대로 보유 + 결제는
        // 할인가로 paid" 가 그대로 굳었다. 호출부(booking_payment_apply_remote)의
        // 트랜잭션 안에서 돌기 때문에, 여기서 예외를 던지면 paid 전환도 함께
        // 롤백되고 결제는 pending 으로 남아 재동기화/점검 대상이 된다.
        $need = 0;
        $q = mysqli_prepare($connect, "SELECT COALESCE(points_used, 0) AS points_used FROM gangnamunni_platform_payments WHERE id = ?");
        if ($q) {
            mysqli_stmt_bind_param($q, 'i', $pid);
            mysqli_stmt_execute($q);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
            mysqli_stmt_close($q);
            $need = (int)($row['points_used'] ?? 0);
        }
        if ($need <= 0) return;

        $have = 0;
        $c = mysqli_prepare($connect, "SELECT COALESCE(SUM(points), 0) AS pts FROM gangnamunni_point_payment_holds WHERE payment_id = ? AND status = 'captured'");
        if ($c) {
            mysqli_stmt_bind_param($c, 'i', $pid);
            mysqli_stmt_execute($c);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($c));
            mysqli_stmt_close($c);
            $have = (int)($row['pts'] ?? 0);
        }
        if ($have !== $need) {
            throw new RuntimeException("Point hold mismatch for payment {$pid}: order discounts {$need}P but {$have}P is captured.");
        }
    }

    /**
     * @param \mysqli    $connect
     */
    function points_checkout_restore_on_refund($connect, array $order)
    {
        if (($order['status'] ?? '') !== 'refunded') return false;
        $hold = points_checkout_active_hold($connect, (int)$order['id']);
        if (!$hold) return false;
        points_checkout_release_hold($connect, $hold, '결제 취소 포인트 복구 (예약 #' . (int)$hold['booking_id'] . ')', 'restored');
        return true;
    }

    /**
     * @param \mysqli    $connect
     */
    function points_checkout_after_sync($connect, array $order)
    {
        if (!points_v2_schema_ready($connect)) return;
        $status = (string)($order['status'] ?? '');
        if (in_array($status, ['paid', 'partially_refunded'], true)) points_checkout_capture($connect, (int)$order['id']);
        if ($status === 'refunded') points_checkout_restore_on_refund($connect, $order);
    }


    /**
     * @param \mysqli    $connect
     * @param int|string $booking_id
     * @param string     $reason
     * @param bool $keepIfInProgress true 면 Toss 가 IN_PROGRESS(카드 인증 중·승인 전)일 때 홀드를 풀지 않는다.
     *   방치 홀드 cron 용: 결제창이 열려 있는 동안 풀면 승인 단계에서 POINT_HOLD_EXPIRED 로 거부된다.
     *   예약 취소는 false(기본): 취소되면 승인이 어차피 거부되므로 바로 복구한다.
     */
    function points_checkout_release_for_booking($connect, $booking_id, $reason = '예약 취소 포인트 복구', $keepIfInProgress = false)
    {
        if (!points_v2_schema_ready($connect)) return false;
        require_once __DIR__ . '/booking_payment_service.php';
        $order = booking_payment_order($connect, (int)$booking_id);
        if (!$order || ($order['status'] ?? '') !== 'pending' || !points_checkout_active_hold($connect, (int)$order['id'])) return false;
        $merchant = (string)$order['merchant_uid'];
        $gateway = booking_payment_gateway();
        return booking_payment_lock($connect, $merchant, function () use ($connect, $merchant, $reason, $gateway, $keepIfInProgress) {
            // PG가 연결돼 있으면 최신 결제 상태를 먼저 반영한다. 연결 전에는 외부 결제 건이 없다.
            if ($gateway) {
                try {
                    $remote = $gateway->payment($merchant);
                    if ($keepIfInProgress && ($remote['raw_status'] ?? '') === 'IN_PROGRESS') return false;
                    booking_payment_apply_remote(
                        $connect,
                        booking_payment_by_merchant($connect, $merchant),
                        $remote,
                        'server_query',
                        $gateway->provider()
                    );
                } catch (BookingPaymentRejectedException $e) {
                }
            }
            $fresh = booking_payment_by_merchant($connect, $merchant);
            return ($fresh['status'] ?? '') === 'pending' ? points_checkout_release($connect, (int)$fresh['id'], $reason) : false;
        });
    }
}

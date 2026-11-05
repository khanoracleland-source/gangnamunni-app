<?php
/**
 * KG이니시스 카드 결제(INIPayPro) — PHP(iOS) 쪽 승인 흐름. Nest(seoulbeauty-api/src/bookings/inicis/inicis-payment.service.ts)와 같은 규칙을 따른다.
 *
 *   booking_payment_request.php  -> InicisGateway::checkoutParams()  (서명된 결제창 파라미터, 아무것도 기록하지 않는다)
 *   브라우저가 INIPayPro 로 인증 -> 이니시스가 인증 결과를 P_NEXT_URL(booking_payment_inicis_return.php)로 POST
 *   booking_payment_inicis_return() : 원장·금액·MID·IDC 검증 -> payAppl.ini 승인 -> 응답 재검증 -> 원장 반영
 *                                     (booking_payment_apply_remote: 같은 잠금·이벤트·상태 이력·포인트 처리) / 실패 시 망취소
 *
 * 이 요청은 이니시스가 사용자의 브라우저로 보내는 cross-site POST 라서 세션·CSRF 가 없다. 그래서 주문은 P_OID 로 원장에서 찾고,
 * 모든 값을 원장과 이니시스의 승인 응답에 대조한다. 이니시스가 보낸 문구는 화면에 되돌리지 않는다.
 *
 * DB 에 닿는 부분은 $io 로 주입한다(기본값: booking_payment_inicis_io). 테스트는 DB·네트워크 없이 $io 와 가짜 transport 로 돌린다.
 */
require_once __DIR__ . '/booking_payment_inicis.php';

/** 한 주문이 1시간 안에 가질 수 있는 카드 인증 시도 수(auth TID 하나 = attempts 한 행). 창이 미끄러지므로 영구 차단이 아니다. */
const BOOKING_PAYMENT_INICIS_MAX_ATTEMPTS_PER_HOUR = 30;

/**
 * 돈이 걸린 이벤트(망취소 실패, 카드는 결제됐는데 원장 미반영, 승인 결과 불명 등): 항상 error_log 한 줄,
 * INICIS_ALERT_WEBHOOK(https, Slack 호환 {"text": ...})이 있으면 거기에도 보낸다. 예외를 던지지 않는다. 키·카드 정보는 보내지 않는다.
 */
function booking_payment_inicis_alert(string $code, string $detail): void {
    $text = '[inicis][ALERT][' . $code . '] ' . $detail;
    error_log($text);
    $url = trim(booking_payment_setting('INICIS_ALERT_WEBHOOK'));
    if (strpos($url, 'https://') !== 0 || !function_exists('curl_init')) return;
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Throwable $e) { /* 알림 실패가 결제 처리를 막으면 안 된다 */ }
}

/** DB 의존 부분의 기본 구현. 서비스 파일은 여기서 처음 불러온다(테스트가 DB 없이 이 파일만 읽을 수 있게). */
function booking_payment_inicis_io(mysqli $db, InicisGateway $gateway): array {
    require_once __DIR__ . '/booking_payment_service.php';
    return [
        'lock' => function (string $merchant, callable $work) use ($db) { return booking_payment_lock($db, $merchant, $work); },
        'order' => function (string $merchant) use ($db): ?array {
            try { return booking_payment_by_merchant($db, $merchant); }
            catch (RuntimeException $e) { if ($e->getMessage() === 'Unknown order.') return null; throw $e; }
        },
        'booking_status' => function (int $bookingId) use ($db): string {
            $stmt = $db->prepare('SELECT status FROM gangnamunni_bookings WHERE id=?');
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (string)($row['status'] ?? '');
        },
        'recent_attempts' => function (int $paymentId) use ($db): int { return booking_payment_inicis_recent_attempts($db, $paymentId); },
        'attempt' => function (array $order, string $key, int $amount, string $outcome, ?string $reason) use ($db, $gateway): void {
            booking_payment_attempt_save($db, $order, $gateway->provider(), $key, $amount, $outcome, $reason);
        },
        'apply' => function (array $order, array $remote) use ($db, $gateway): array {
            return booking_payment_apply_remote($db, $order, $remote, 'server_query', $gateway->provider());
        },
        'allowed' => function (string $mode, string $memberId): bool { return booking_payment_inicis_member_allowed($mode, $memberId); },
        'alert' => 'booking_payment_inicis_alert',
        'now' => function (): int { return time(); },
    ];
}

/** P_APPL_DT(yyyymmdd) + P_APPL_TM(hhmmss), KST -> unix. 없거나 미래면 지금. */
/** 최근 1시간 동안의 인증 시도 수(prepare 와 P_NEXT_URL 이 같은 기준을 쓴다). */
function booking_payment_inicis_recent_attempts(mysqli $db, int $paymentId): int {
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM gangnamunni_payment_attempts WHERE payment_id=? AND created_at > NOW() - INTERVAL 1 HOUR');
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

function booking_payment_inicis_paid_at(string $dt, string $tm, int $now): int {
    if (!preg_match('/^\d{8}$/D', $dt) || !preg_match('/^\d{6}$/D', $tm)) return $now;
    $d = DateTime::createFromFormat('YmdHis', $dt . $tm, new DateTimeZone('Asia/Seoul'));
    if (!$d) return $now;
    return min($d->getTimestamp(), $now);
}

/**
 * P_NEXT_URL 처리. 항상 이동할 URL 을 돌려준다(예외를 던지지 않는다).
 *
 * @param array  $post    $_POST (이니시스가 보낸 인증 결과)
 * @param string $baseUrl BOOKING_PAYMENT_BASE_URL
 */
function booking_payment_inicis_return(InicisGateway $gateway, array $post, string $baseUrl, array $io): string {
    $baseUrl = rtrim($baseUrl, '/');
    $str = function (string $key) use ($post): string { $v = $post[$key] ?? ''; return is_string($v) ? substr($v, 0, 500) : ''; };
    $oid = $str('P_OID');
    $bid = preg_match('/^BK_\d{14}_(\d{1,9})_[0-9a-f]{12}$/D', $oid, $m) ? (int)$m[1] : 0;
    // 안내는 결제 화면이 보여준다(pay_error). 주문번호에 예약 번호가 들어 있어 원장을 읽기 전의 오류도 그 화면으로 보낸다.
    $notice = function (string $code, int $bookingId) use ($baseUrl): string {
        return $bookingId > 0 ? $baseUrl . '/booking_payment.php?booking_id=' . $bookingId . '&pay_error=' . $code
                              : $baseUrl . '/my_bookings.php?pay_error=' . $code;
    };
    if ($bid <= 0) return $notice('UNAVAILABLE', 0);
    try {
        return $io['lock']($oid, function () use ($gateway, $str, $oid, $baseUrl, $io, $notice) {
            return booking_payment_inicis_confirm($gateway, $str, $oid, $baseUrl, $io, $notice);
        });
    } catch (Throwable $e) {
        error_log('[inicis_return] ' . $oid . ': ' . $e->getMessage());
        return $notice(strpos($e->getMessage(), 'Payment is busy') === 0 ? 'BUSY' : 'ERROR', $bid);
    }
}

/** 결제 잠금 안에서 실행된다. */
function booking_payment_inicis_confirm(InicisGateway $gateway, callable $str, string $oid, string $baseUrl, array $io, callable $notice): string {
    $order = $io['order']($oid);
    if (!$order) return $notice('UNKNOWN_ORDER', preg_match('/^BK_\d{14}_(\d{1,9})_/', $oid, $m) ? (int)$m[1] : 0);
    $bookingId = (int)$order['booking_id'];
    $payPage = function (string $code) use ($notice, $bookingId): string { return $notice($code, $bookingId); };
    $success = $baseUrl . '/booking_payment_success.php?booking_id=' . $bookingId;
    $provider = $gateway->provider();
    $authTid = $str('P_AUTH_TID');
    $status = $str('P_STATUS');
    $amtRaw = $str('P_AMT');
    $amount = (int)$order['amount'];
    $record = function (array $o, string $key, int $amt, string $outcome, ?string $reason) use ($io): void {
        try { $io['attempt']($o, $key, $amt, $outcome, $reason); }
        catch (Throwable $e) { error_log('[inicis_return] attempt save failed (' . $o['merchant_uid'] . '): ' . $e->getMessage()); }
    };

    if (in_array($order['status'], ['paid', 'partially_refunded'], true)) return $success;   // 새로고침·중복 제출
    // 공개 엔드포인트: auth TID 가 없는 post(닫힌 결제창, 잡음)는 증거 행을 남기지 않고, 한 주문이 1시간에 가질 행 수도 제한한다.
    if ($authTid === '' || strlen($authTid) > 191) return $payPage($status === '00' ? 'AUTH_INVALID' : 'AUTH_FAILED');
    if ($io['recent_attempts']((int)$order['id']) >= BOOKING_PAYMENT_INICIS_MAX_ATTEMPTS_PER_HOUR) {
        error_log('[inicis_return] attempt limit reached (' . $oid . ')');
        return $payPage('TOO_MANY_ATTEMPTS');   // 승인을 호출하지 않으므로 청구되지 않는다
    }
    $record($order, $authTid, ctype_digit($amtRaw) ? (int)$amtRaw : 0, 'received', null);
    if ($status !== '00') {
        $record($order, $authTid, $amount, 'rejected', 'AUTH_' . ($status !== '' ? $status : 'NONE'));
        return $payPage('AUTH_FAILED');
    }
    $refuse = function (string $reason) use ($record, $order, $authTid, $amount, $payPage): string {
        $record($order, $authTid, $amount, 'refused', $reason);
        return $payPage($reason);
    };
    $idc = $str('P_IDCNAME');
    if (!$io['allowed']($gateway->mode(), (string)($order['patient_mb_id'] ?? ''))) return $refuse('UNAVAILABLE');
    if ($order['status'] !== 'pending') return $refuse('ORDER_STATE');
    if (!ctype_digit($amtRaw) || (int)$amtRaw !== $amount || $amount <= 0) return $refuse('AMOUNT_MISMATCH');
    if ($str('P_MID') !== $gateway->mid()) return $refuse('MID_MISMATCH');
    if (!in_array($idc, InicisGateway::IDC_CODES, true)) return $refuse('AUTH_INVALID');
    if ((int)($order['points_used'] ?? 0) > 0) return $refuse('POINTS_NOT_SUPPORTED');
    if ($io['booking_status']($bookingId) !== 'confirmed') return $refuse('BOOKING_NOT_CONFIRMED');

    $alert = $io['alert'];
    $cancel = function (string $reason) use ($gateway, $idc, $authTid, $amount, $oid, $alert): bool {
        try {
            $r = $gateway->netCancel($idc, $authTid, $amount, $oid, $reason);
            if (($r['P_STATUS'] ?? '') === '00') return true;
            $alert('NET_CANCEL_UNCONFIRMED', $oid . ' auth=' . $authTid . ': P_STATUS=' . ($r['P_STATUS'] ?? '?') . ': check the Inicis admin and refund by hand');
            return false;
        } catch (Throwable $e) {
            $alert('NET_CANCEL_FAILED', $oid . ' auth=' . $authTid . ': ' . $e->getMessage() . ': check the Inicis admin and refund by hand');
            return false;
        }
    };
    try {
        $res = $gateway->approve($idc, $authTid, $amount);
    } catch (Throwable $e) {
        // 결과 불명(타임아웃·네트워크): 이니시스가 승인했을 수 있으므로 망취소한다.
        $cancelled = $cancel('approval result unknown');
        $alert($cancelled ? 'UNRESOLVED_CANCELLED' : 'UNRESOLVED_NOT_CANCELLED', $oid . ' auth=' . $authTid . ': ' . $e->getMessage()
            . ($cancelled ? ': net cancel sent, verify in the Inicis admin' : ': card MAY be charged, check the Inicis admin'));
        $record($order, $authTid, $amount, 'pending', 'UNRESOLVED');
        return $payPage('UNRESOLVED');
    }
    if (($res['P_STATUS'] ?? '') !== '00') {
        $record($order, $authTid, $amount, 'rejected', 'APPROVE_' . preg_replace('/[^A-Za-z0-9]/', '', (string)($res['P_STATUS'] ?? 'NONE')));
        return $payPage('REJECTED');
    }
    $tid = (string)($res['P_APPL_TID'] ?? '');
    if (($res['P_OID'] ?? null) !== $oid || ($res['P_AMT'] ?? null) !== (string)$amount || ($res['P_MID'] ?? null) !== $gateway->mid() || $tid === '' || strlen($tid) > 191) {
        $cancelled = $cancel('approval result mismatch');
        $alert('RESULT_MISMATCH', $oid . ' tid=' . $tid . ': oid=' . ($res['P_OID'] ?? '') . ' amt=' . ($res['P_AMT'] ?? '') . ' net cancel ' . ($cancelled ? 'sent' : 'FAILED'));
        $record($order, $authTid, $amount, 'refused', 'RESULT_MISMATCH');
        return $payPage('RESULT_MISMATCH');
    }
    try {
        $now = (int)$io['now']();
        $remote = ['payment_id' => $oid, 'transaction_id' => $tid, 'status' => 'paid', 'raw_status' => 'INICIS_00', 'amount' => $amount,
            'cancelled_amount' => 0, 'currency' => 'KRW',
            'paid_at' => booking_payment_inicis_paid_at((string)($res['P_APPL_DT'] ?? ''), (string)($res['P_APPL_TM'] ?? ''), $now)];
        $applied = $io['apply']($order, $remote);
        if (!in_array($applied['status'] ?? '', ['paid', 'partially_refunded'], true)) throw new RuntimeException('Ledger is not paid after apply.');
    } catch (Throwable $e) {
        $cancelled = $cancel('ledger update failed');
        $alert($cancelled ? 'FINALIZE_FAILED' : 'FINALIZE_FAILED_UNCANCELLED', $oid . ' tid=' . $tid . ': ' . $e->getMessage()
            . ': ' . ($cancelled ? 'net cancel sent' : 'CARD CHARGED, ledger not updated: refund by hand'));
        $record($order, $authTid, $amount, 'pending', $cancelled ? 'FINALIZE_FAILED' : 'FINALIZE_FAILED_UNCANCELLED');
        // 취소되지 않았다 = 카드는 결제됐는데 원장에 기록이 없다: 사용자에게 다시 결제하지 말라고 알린다.
        return $payPage($cancelled ? 'FINALIZE_FAILED' : 'FINALIZE_FAILED_UNCANCELLED');
    }
    $record($order, $authTid, $amount, 'paid', 'CONFIRMED');
    return $success;
}

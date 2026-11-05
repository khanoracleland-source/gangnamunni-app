<?php
/**
 * KG이니시스 PG 클라이언트 (BookingPaymentGateway 구현).
 *
 * 역할 분담
 *  - 카드 결제는 Nest API(seoulbeauty-api/src/bookings/inicis)와 이 PHP(iOS) 두 곳에서 같은 방식으로 처리하고 같은 원장
 *    (gangnamunni_platform_payments)에 기록한다. PHP: checkoutParams()(결제창 파라미터) -> booking_payment_inicis_return.php(P_NEXT_URL)
 *    -> approve()(payAppl.ini) -> 원장 반영, 실패 시 netCancel()(망취소). 흐름은 includes/booking_payment_inicis_flow.php.
 *  - 환불(전액)은 이 클라이언트가 INIAPI 로 요청한다 — admin_bookings.php '환불 승인', admin_booking_payments.php.
 *    https://manual.inicis.com/pay/cancel.html  (V1 NVP: POST https://iniapi.inicis.com/api/v1/refund)
 *
 * 이니시스에는 주문번호(merchant_uid)로 결제 상태를 조회하는 공개 API 가 없다. 따라서 payment() 는
 * 우리 원장을 정규화해 돌려준다(원장이 진실의 원천). 결과:
 *  - 환불 흐름(booking_payment_refund)은 그대로 동작한다: cancel() 성공을 이 객체가 기억해 payment() 에 반영한다.
 *  - 이니시스 관리자 화면에서 직접 취소한 건은 원장에 반영되지 않는다. 취소는 반드시 우리 관리자 화면으로만 한다.
 *  - cron_reconcile_booking_payments.php 는 대조할 외부 상태가 없으므로 아무것도 하지 않는다(queriesProvider() = false).
 *
 * 부분 환불은 정책상 지원하지 않는다(cancel() 은 남은 금액 전액만 받는다).
 */
require_once __DIR__ . '/booking_payment_gateway.php';

/** 승인/망취소 호출의 결과를 알 수 없다(타임아웃·네트워크·해석 불가 응답). 승인은 이뤄졌을 수 있으므로 호출자는 망취소를 시도해야 한다. */
class InicisUnknownOutcomeException extends RuntimeException {}

final class InicisGateway implements BookingPaymentGateway {
    const TEST_MIDS = ['INIpayTest', 'INIBillTst', 'iniescrow0'];
    /** INIAPI resultCode 가 이 값이면 '이미 취소된 거래' 로 보고 성공 처리한다 — 첫 실거래 테스트에서 코드를 확정한 뒤 채운다. */
    const ALREADY_CANCELLED_CODES = [];

    private $mid; private $apiKey; private $mode; private $clientIp; private $transport; private $ledger; private $timeout; private $hashKey;
    /** cancel() 이 성공한 주문: merchant_uid => cancelled amount (같은 요청 안에서 payment() 가 반영) */
    private $cancelled = [];

    /**
     * @param callable      $ledger    fn(string $merchantUid): ?array  — 원장 행(gangnamunni_platform_payments) 조회
     * @param callable|null $transport fn(string $url, array $form, int $timeoutSec): array{status:int, body:string} — HTTP POST (기본: cURL)
     */
    public function __construct(string $mid, string $apiKey, string $mode, string $clientIp, callable $ledger, ?callable $transport = null, int $timeout = 15, string $hashKey = '') {
        if ($mid === '' || $apiKey === '') throw new InvalidArgumentException('Inicis MID and INIAPI key are required.');
        if (!in_array($mode, ['live', 'test'], true)) throw new InvalidArgumentException('Inicis mode must be live or test.');
        $isTestMid = in_array($mid, self::TEST_MIDS, true);
        if ($mode === 'live' && $isTestMid) throw new InvalidArgumentException('Live mode refuses a test MID.');
        if ($mode === 'test' && !$isTestMid) throw new InvalidArgumentException('Test mode requires a test MID.');
        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) throw new InvalidArgumentException('Inicis clientIp must be an IP address.');
        $this->mid = $mid; $this->apiKey = $apiKey; $this->mode = $mode; $this->clientIp = $clientIp;
        $this->ledger = $ledger; $this->transport = $transport ?: [$this, 'curlPost']; $this->timeout = $timeout; $this->hashKey = $hashKey;
    }

    public function provider(): string { return $this->mode === 'live' ? 'inicis' : 'inicis_test'; }
    public function mode(): string { return $this->mode; }
    /** 외부 상태를 조회하지 않는다(원장이 진실). 대조 cron 은 이 값을 보고 건너뛴다. */
    public function queriesProvider(): bool { return false; }
    public function refundUrl(): string { return 'https://' . ($this->mode === 'live' ? 'iniapi' : 'stginiapi') . '.inicis.com/api/v1/refund'; }

    const SDK_URL = 'https://paypro.inicis.com/std/payment/js/INIPayPro_v2.js';
    /** P_IDCNAME 으로 허용되는 값 (승인/망취소 호스트 {fc|ks|stg}paypro.inicis.com 의 일부가 되므로 그 밖의 값은 거부). */
    const IDC_CODES = ['fc', 'ks', 'stg'];
    const MERCHANT_UID_PATTERN = '/^BK_\d{14}_\d{1,9}_[0-9a-f]{12}$/D';

    public function mid(): string { return $this->mid; }
    /** 카드 결제창을 열 수 있는가: '모바일 금액위변조 Hash Key'(INICIS_HASHKEY) 설정이 있어야 한다. */
    public function canCheckout(): bool { return $this->hashKey !== ''; }

    /**
     * 2단계 서버 승인은 paymentKey 한 값으로 끝나지 않는다(IDC 코드·MID 가 필요). 승인 흐름은
     * booking_payment_inicis_return()(includes/booking_payment_inicis_flow.php) 이고 approve()/netCancel() 을 쓴다.
     */
    public function confirm(string $merchantUid, string $paymentKey, int $amount): array {
        throw new BookingPaymentGatewayUnavailableException('Inicis approval runs in booking_payment_inicis_return(); confirm() is not used.');
    }

    /** P_CHKFAKE = BASE64(SHA-512(P_AMT + P_OID + P_TIMESTAMP + HashKey)) — 원시(raw) 다이제스트를 base64. */
    public static function chkFake(string $amt, string $oid, string $timestamp, string $hashKey): string {
        return base64_encode(hash('sha512', $amt . $oid . $timestamp . $hashKey, true));
    }

    /** UTF-8 문자를 자르지 않고 최대 $max 바이트로 자른다. */
    public static function clipBytes(string $value, int $max): string { return mb_strcut($value, 0, $max, 'UTF-8'); }

    /**
     * INIPayPro.requestPayment() 에 넘길 서명된 파라미터. 금액·주문번호는 원장 행에서만 읽는다(호출자가 요청값을 넘기지 않는다).
     * HashKey 는 결과에 포함되지 않는다(P_CHKFAKE 만 포함).
     *
     * @param array $order    gangnamunni_platform_payments 행 (merchant_uid, amount, booking_id)
     * @param array $booking  gangnamunni_bookings 행 (treatment, name)
     * @param string $baseUrl BOOKING_PAYMENT_BASE_URL (공개 https gangnamunni URL)
     * @param string $method  'card'(신용/체크카드) | 'bank'(계좌이체)
     */
    public function checkoutParams(array $order, array $booking, bool $mobile, string $baseUrl, string $appScheme = 'wellness', ?string $timestampMs = null, string $method = 'card'): array {
        $payType = ['card' => 'CARD', 'bank' => 'BANK'][$method] ?? null;   // 카드 결제창 / 계좌이체 결제창
        if ($payType === null) throw new InvalidArgumentException('Unsupported payment method.');
        if (!$this->canCheckout()) throw new BookingPaymentGatewayUnavailableException('Inicis hash key is not configured.');
        $oid = (string)($order['merchant_uid'] ?? '');
        $amount = (int)($order['amount'] ?? 0);
        $bookingId = (int)($order['booking_id'] ?? 0);
        if ($amount <= 0 || $bookingId <= 0 || !preg_match(self::MERCHANT_UID_PATTERN, $oid) || strlen($oid) > 40) {
            throw new InvalidArgumentException('Invalid order for checkout.');
        }
        $baseUrl = rtrim($baseUrl, '/');
        if (strpos($baseUrl, 'https://') !== 0) throw new InvalidArgumentException('Checkout base URL must be https.');
        if (!preg_match('/^[a-z][a-z0-9+.\-]*$/i', $appScheme)) throw new InvalidArgumentException('Invalid app scheme.');
        $ts = $timestampMs ?? (string)(int)round(microtime(true) * 1000);   // 매뉴얼: 13자리 밀리초
        $goods = self::clipBytes(trim((string)($booking['treatment'] ?? '')), 40);
        $name = self::clipBytes(trim((string)($booking['name'] ?? '')), 30);
        // iOS 카드 앱은 앱 URL 스킴으로 돌아온다(P_RESERVED.app_scheme). 웹은 빈 객체.
        $reserved = $mobile ? ['app_scheme' => $appScheme . '://payment'] : new stdClass();
        return [
            'P_MID' => $this->mid, 'P_OID' => $oid, 'P_PAY_TYPE' => $payType, 'P_DEVICE_TYPE' => $mobile ? 'MOBILE' : 'WEB', 'P_IDCCODE' => 'Y',
            'P_AMT' => (string)$amount, 'P_GOODS' => $goods !== '' ? $goods : '예약 결제', 'P_UNAME' => $name !== '' ? $name : '고객',
            'P_NEXT_URL' => $baseUrl . '/booking_payment_inicis_return.php',
            'P_CLOSE_URL' => $baseUrl . '/booking_payment.php?booking_id=' . $bookingId,
            'P_CHARSET' => 'UTF-8', 'P_NOTI' => (string)$bookingId, 'P_TIMESTAMP' => $ts,
            'P_CHKFAKE' => self::chkFake((string)$amount, $oid, $ts, $this->hashKey),
            'P_RESERVED' => json_encode($reserved, JSON_UNESCAPED_SLASHES),
        ];
    }

    /** payAppl.ini: 인증 성공(P_STATUS=00) 뒤의 실제 승인. 결과가 불분명하면 InicisUnknownOutcomeException. */
    public function approve(string $idc, string $authTid, int $amount): array {
        return $this->paypro($idc, '/payment/v1/rest/payAppl.ini',
            ['P_MID' => $this->mid, 'P_AUTH_TID' => $authTid, 'P_AMT' => (string)$amount, 'P_CHARSET' => 'UTF-8']);
    }

    /**
     * payNetCancel.ini (망취소): 승인 결과를 처리하다 실패했을 때만(인증 TID 기준 10분 / 승인 TID 기준 1분 이내). 일반 취소가 아니다.
     * 주의: P_CHKFAKE 공식은 결제 요청과 같다고 가정했다(매뉴얼에 명시 없음) — 실거래에서 확인 필요.
     * 성공 판정은 호출자 몫: 반환값의 P_STATUS 가 '00' 인지 확인한다.
     */
    public function netCancel(string $idc, string $authTid, int $amount, string $oid, string $reason, ?string $timestampMs = null): array {
        if (!$this->canCheckout()) throw new BookingPaymentGatewayUnavailableException('Inicis hash key is not configured.');
        $ts = $timestampMs ?? (string)(int)round(microtime(true) * 1000);
        return $this->paypro($idc, '/payment/v1/rest/payNetCancel.ini', [
            'P_MID' => $this->mid, 'P_AUTH_TID' => $authTid, 'P_AMT' => (string)$amount, 'P_OID' => $oid,
            'P_CANCEL_MSG' => self::clipBytes($reason, 100), 'P_TIMESTAMP' => $ts,
            'P_CHKFAKE' => self::chkFake((string)$amount, $oid, $ts, $this->hashKey), 'P_CHARSET' => 'UTF-8']);
    }

    /** 이니시스 NVP 응답 `KEY=VALUE&KEY=VALUE`. 값은 URL 인코딩되지 않았고 '=' 를 포함할 수 있다. */
    public static function parseNvp(string $body): array {
        $out = [];
        foreach (explode('&', $body) as $part) {
            $i = strpos($part, '=');
            if ($i === false || $i === 0) continue;
            $out[substr($part, 0, $i)] = substr($part, $i + 1);
        }
        return $out;
    }

    private function paypro(string $idc, string $path, array $form): array {
        if (!in_array($idc, self::IDC_CODES, true)) throw new InvalidArgumentException('Unknown Inicis IDC code.');
        try {
            $res = ($this->transport)('https://' . $idc . 'paypro.inicis.com' . $path, $form, min($this->timeout, 10));
        } catch (Throwable $e) {
            throw new InicisUnknownOutcomeException('Inicis outcome unknown: ' . $e->getMessage(), 0, $e);
        }
        if ((int)($res['status'] ?? 0) !== 200) throw new InicisUnknownOutcomeException('Inicis outcome unknown: HTTP ' . (int)($res['status'] ?? 0));
        $parsed = self::parseNvp((string)($res['body'] ?? ''));
        if (!isset($parsed['P_STATUS'])) throw new InicisUnknownOutcomeException('Inicis outcome unknown: unparsable answer.');
        return $parsed;
    }

    /** 원장을 PG 응답 형태로 정규화한다. 없는 주문은 결제 전(ready, NOT_FOUND). */
    public function payment(string $merchantUid): array {
        $row = ($this->ledger)($merchantUid);
        if (!$row) {
            return ['payment_id' => $merchantUid, 'transaction_id' => null, 'status' => 'ready', 'raw_status' => 'NOT_FOUND',
                'amount' => 0, 'cancelled_amount' => 0, 'currency' => 'KRW', 'paid_at' => null];
        }
        $amount = (int)$row['amount'];
        $refunded = (int)($row['refunded_amount'] ?? 0);
        if (isset($this->cancelled[$merchantUid])) $refunded = max($refunded, $this->cancelled[$merchantUid]);
        $ledgerStatus = (string)($row['status'] ?? '');
        $tid = isset($row['provider_transaction_id']) && $row['provider_transaction_id'] !== '' ? (string)$row['provider_transaction_id'] : null;
        $paidAt = !empty($row['paid_at']) ? self::kstToUnix((string)$row['paid_at']) : null;
        switch ($ledgerStatus) {
            case 'paid': case 'partially_refunded': case 'refunded': case 'cancelled':
                $status = ($refunded >= $amount && $amount > 0) ? 'cancelled' : 'paid';
                break;
            case 'failed': $status = 'failed'; break;
            default: $status = 'ready';
        }
        return ['payment_id' => $merchantUid, 'transaction_id' => $tid, 'status' => $status, 'raw_status' => 'LEDGER_' . strtoupper($ledgerStatus),
            'amount' => $amount, 'cancelled_amount' => min($refunded, $amount), 'currency' => 'KRW', 'paid_at' => $paidAt];
    }

    /**
     * 전액 취소(INIAPI Refund). 명확한 거절은 BookingPaymentRejectedException(메시지 = 코드),
     * 결과가 불분명한 오류(네트워크·타임아웃·깨진 응답)는 RuntimeException — 호출자가 pending 으로 남기고 재시도한다.
     */
    public function cancel(string $merchantUid, string $transactionId, int $amount, int $remaining, string $reason, string $requestId): array {
        if ($transactionId === '' || strlen($transactionId) > 40) throw new BookingPaymentRejectedException('INVALID_TID');
        if ($amount <= 0 || $amount !== $remaining) throw new BookingPaymentRejectedException('PARTIAL_NOT_SUPPORTED');
        $row = ($this->ledger)($merchantUid);
        if (!$row || (string)($row['provider_transaction_id'] ?? '') !== $transactionId) throw new BookingPaymentRejectedException('TID_MISMATCH');
        $form = $this->refundForm($transactionId, $reason);
        try {
            $res = ($this->transport)($this->refundUrl(), $form, $this->timeout);
        } catch (Throwable $e) {
            throw new RuntimeException('Inicis refund outcome unknown: ' . $e->getMessage(), 0, $e);
        }
        $body = json_decode((string)($res['body'] ?? ''), true);
        if ((int)($res['status'] ?? 0) !== 200 || !is_array($body) || !isset($body['resultCode'])) {
            throw new RuntimeException('Inicis refund outcome unknown: HTTP ' . (int)($res['status'] ?? 0));
        }
        $code = (string)$body['resultCode'];
        if ($code !== '00' && !in_array($code, self::ALREADY_CANCELLED_CODES, true)) {
            error_log('[inicis_refund] ' . $merchantUid . ' tid=' . $transactionId . ' rejected ' . $code . ' ' . (string)($body['detailResultCode'] ?? '') . ' ' . (string)($body['resultMsg'] ?? ''));
            throw new BookingPaymentRejectedException('INICIS_' . preg_replace('/[^A-Za-z0-9]/', '', $code));
        }
        $this->cancelled[$merchantUid] = $amount;
        return ['cancelled_amount' => $amount, 'result_code' => $code,
            'cancel_date' => (string)($body['cancelDate'] ?? ''), 'cancel_time' => (string)($body['cancelTime'] ?? '')];
    }

    /** INIAPI V1 Refund 폼 + hashData = SHA512(INIAPIKey + type + paymethod + timestamp + clientIp + mid + tid). */
    public function refundForm(string $tid, string $reason, ?string $timestamp = null): array {
        $timestamp = $timestamp ?? (new DateTime('now', new DateTimeZone('Asia/Seoul')))->format('YmdHis');
        $type = 'Refund'; $paymethod = 'Card';
        $msg = mb_strcut(trim($reason) === '' ? 'refund' : trim($reason), 0, 80, 'UTF-8');   // 80 bytes max, never splits a character
        $hash = hash('sha512', $this->apiKey . $type . $paymethod . $timestamp . $this->clientIp . $this->mid . $tid);
        return ['type' => $type, 'paymethod' => $paymethod, 'timestamp' => $timestamp, 'clientIp' => $this->clientIp,
            'mid' => $this->mid, 'tid' => $tid, 'msg' => $msg, 'hashData' => $hash];
    }

    /** @return array{status:int, body:string} */
    private function curlPost(string $url, array $form, int $timeout): array {
        if (!function_exists('curl_init')) throw new RuntimeException('cURL extension is required.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSLVERSION => defined('CURL_SSLVERSION_TLSv1_2') ? CURL_SSLVERSION_TLSv1_2 : 0,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('cURL: ' . $err);
        return ['status' => $status, 'body' => (string)$body];
    }

    private static function kstToUnix(string $datetime): ?int {
        try { return (new DateTime($datetime, new DateTimeZone('Asia/Seoul')))->getTimestamp(); }
        catch (Throwable $e) { return null; }
    }
}

/** 원장 조회(제공자 연결용, 서비스 파일과 독립). */
function booking_payment_inicis_ledger_reader(mysqli $db): callable {
    return function (string $merchantUid) use ($db): ?array {
        $stmt = $db->prepare("SELECT id, merchant_uid, amount, refunded_amount, status, provider, provider_transaction_id, paid_at
                              FROM gangnamunni_platform_payments WHERE merchant_uid=? AND payment_type='booking' LIMIT 1");
        $stmt->bind_param('s', $merchantUid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    };
}

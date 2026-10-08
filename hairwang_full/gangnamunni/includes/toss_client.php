<?php
/**
 * Toss Payments (API 개별 연동) 클라이언트.
 *
 * 결제는 2단계다: 브라우저 결제창에서 인증 → 서버가 /v1/payments/confirm 으로 승인.
 * 승인 전에는 돈이 빠져나가지 않으며, 승인되지 않은 결제는 PG에서 만료된다.
 * 결제 상태는 항상 이 클라이언트로 PG에 다시 물어서 확정한다(웹훅 본문은 신호일 뿐).
 *
 * 키: 개발자센터 > API 개별 연동 키 (test_ck_/test_sk_ 또는 live_ck_/live_sk_).
 * 결제위젯 키(gck/gsk)는 이 연동에서 쓰지 않는다.
 */
require_once __DIR__ . '/booking_payment_gateway.php';

final class BookingTossClient implements BookingPaymentGateway {
    private const BASE_URL = 'https://api.tosspayments.com';

    /**
     * 결과가 불분명하거나 이미 처리되었을 수 있는 오류. 이런 경우 요청을
     * 실패로 확정하지 않고 결제 조회로 실제 상태를 다시 확인한다.
     */
    private const UNRESOLVED_ERRORS = [
        'PROVIDER_ERROR',
        'FAILED_INTERNAL_SYSTEM_PROCESSING',
        'FAILED_PAYMENT_INTERNAL_SYSTEM_PROCESSING',
        'FAILED_CARD_COMPANY_RESPONSE',
        'UNKNOWN_PAYMENT_ERROR',
        'ALREADY_PROCESSED_PAYMENT',
        'ALREADY_PROCESSING_REQUEST',
        'IDEMPOTENT_REQUEST_PROCESSING',
        'FORBIDDEN_CONSECUTIVE_REQUEST',
        'ALREADY_CANCELED_PAYMENT',
        'ALREADY_REFUND_PAYMENT',
        'NOT_MATCHES_REFUNDABLE_AMOUNT',
        'FAILED_REFUND_PROCESS',
        'FAILED_METHOD_HANDLING_CANCEL',
        'FAILED_PARTIAL_REFUND',
        'COMMON_ERROR',
    ];

    private $secretKey;
    private $transport;

    public function __construct(string $secretKey, ?callable $transport = null) {
        if (!self::validSecretKey($secretKey)) throw new InvalidArgumentException('Invalid Toss secret key.');
        $this->secretKey = $secretKey;
        $this->transport = $transport;
    }

    public static function validSecretKey(string $key): bool {
        return (bool)preg_match('/^(test|live)_sk_[A-Za-z0-9]+$/D', $key);
    }
    public static function validClientKey(string $key): bool {
        return (bool)preg_match('/^(test|live)_ck_[A-Za-z0-9]+$/D', $key);
    }
    /** 클라이언트 키와 시크릿 키는 같은 환경(test/live)의 짝이어야 한다. */
    public static function keysPair(string $clientKey, string $secretKey): bool {
        return self::validClientKey($clientKey) && self::validSecretKey($secretKey)
            && strtok($clientKey, '_') === strtok($secretKey, '_');
    }

    /** 테스트 키는 테스트 결제만 볼 수 있으므로 키 접두어로 환경을 정한다. */
    public function provider(): string {
        return strncmp($this->secretKey, 'live_', 5) === 0 ? 'toss' : 'toss_test';
    }

    public function payment(string $merchantUid): array {
        [$status, $data] = $this->request('GET', '/v1/payments/orders/' . rawurlencode($merchantUid));
        if ($status === 404 && in_array($data['code'] ?? '', ['NOT_FOUND_PAYMENT', 'NOT_FOUND'], true)) {
            // 결제창에서 인증까지 가지 않은 주문. 돈도 PG 결제 건도 없다.
            return ['payment_id' => $merchantUid, 'transaction_id' => null, 'status' => 'ready',
                'raw_status' => 'NOT_FOUND', 'amount' => null, 'cancelled_amount' => 0,
                'currency' => 'KRW', 'paid_at' => null];
        }
        return self::normalize($this->expectOk($status, $data));
    }

    /** 결제 승인. 같은 paymentKey 재시도는 멱등키로 한 번만 처리된다. */
    public function confirm(string $merchantUid, string $paymentKey, int $amount): array {
        if ($paymentKey === '' || strlen($paymentKey) > 200 || $amount <= 0) {
            throw new InvalidArgumentException('Invalid confirm request.');
        }
        [$status, $data] = $this->request('POST', '/v1/payments/confirm',
            ['paymentKey' => $paymentKey, 'orderId' => $merchantUid, 'amount' => $amount],
            'confirm_' . $merchantUid . '_' . hash('sha256', $paymentKey));
        return self::normalize($this->expectOk($status, $data));
    }

    public function cancel(string $merchantUid, string $transactionId, int $amount, int $remaining, string $reason, string $requestId): array {
        if ($amount <= 0 || $amount > $remaining || $transactionId === '') throw new InvalidArgumentException('Invalid refund amount.');
        $reason = trim($reason);
        if (function_exists('mb_substr')) $reason = mb_substr($reason, 0, 200, 'UTF-8');
        [$status, $data] = $this->request('POST', '/v1/payments/' . rawurlencode($transactionId) . '/cancel',
            ['cancelReason' => $reason !== '' ? $reason : '관리자 환불', 'cancelAmount' => $amount],
            'refund_' . $requestId);
        return self::normalize($this->expectOk($status, $data));
    }

    private function expectOk(int $status, ?array $data): array {
        if ($status >= 200 && $status < 300) {
            if (!is_array($data)) throw new RuntimeException('Invalid Toss response.');
            return $data;
        }
        $code = is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
        // 4xx 와 명확한 오류 코드 = PG의 결정. 5xx·통신 오류·불분명 목록은 결과 미확정으로 남긴다.
        if ($status >= 400 && $status < 500 && $code !== '' && !in_array($code, self::UNRESOLVED_ERRORS, true)) {
            throw new BookingPaymentRejectedException($code);
        }
        throw new RuntimeException('Toss request unresolved' . ($code !== '' ? ': ' . $code : '') . '; reconcile before retrying.');
    }

    /** @return array{0:int,1:?array} */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array {
        $headers = ['Authorization: Basic ' . base64_encode($this->secretKey . ':'), 'Content-Type: application/json'];
        if ($idempotencyKey !== null) $headers[] = 'Idempotency-Key: ' . substr($idempotencyKey, 0, 300);
        if ($this->transport) {
            [$status, $raw] = ($this->transport)($method, $path, $body, $headers);
        } else {
            $ch = curl_init(self::BASE_URL . $path);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return [(int)$status, is_array($data) ? $data : null];
    }

    /** Toss Payment 객체를 원장이 읽는 PG 중립 형태로 줄인다. */
    public static function normalize(array $p): array {
        static $states = [
            'READY' => 'ready',
            'IN_PROGRESS' => 'ready',          // 인증 완료, 아직 승인 전 — 돈 없음
            'WAITING_FOR_DEPOSIT' => 'ready',  // 가상계좌 발급, 입금 전
            'DONE' => 'paid',
            'PARTIAL_CANCELED' => 'paid',      // 일부 남아 있음; cancelled_amount 가 환불액
            'CANCELED' => 'cancelled',
            'ABORTED' => 'failed',
            'EXPIRED' => 'failed',
        ];
        $status = (string)($p['status'] ?? '');
        if (!isset($states[$status])) throw new RuntimeException('Unknown Toss payment status.');
        $total = $p['totalAmount'] ?? null;
        $balance = $p['balanceAmount'] ?? null;
        $total = is_int($total) || (is_float($total) && floor($total) === $total) ? (int)$total : null;
        $balance = is_int($balance) || (is_float($balance) && floor($balance) === $balance) ? (int)$balance : null;
        $approvedAt = is_string($p['approvedAt'] ?? null) ? strtotime($p['approvedAt']) : false;
        return [
            'payment_id' => (string)($p['orderId'] ?? ''),
            'transaction_id' => is_string($p['paymentKey'] ?? null) && $p['paymentKey'] !== '' ? $p['paymentKey'] : null,
            'status' => $states[$status],
            'raw_status' => $status,
            'amount' => $total,
            'cancelled_amount' => ($total !== null && $balance !== null) ? max(0, $total - $balance) : 0,
            'currency' => (string)($p['currency'] ?? ''),
            'paid_at' => $approvedAt === false ? null : $approvedAt,
            'method' => is_string($p['method'] ?? null) ? $p['method'] : null,
            'receipt_url' => is_string($p['receipt']['url'] ?? null) ? $p['receipt']['url'] : null,
        ];
    }
}

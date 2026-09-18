<?php
require_once __DIR__ . '/booking_payment_config.php';

/**
 * A well-formed PortOne response that definitively rejected the request.
 * Only these may fail a money request; anything unresolved stays pending.
 */
final class BookingPortOneRejectedException extends RuntimeException {}

final class BookingPortOneClient {
    /**
     * Errors whose money outcome is unknown, or that mean the money already
     * moved. A refund request must never be marked failed on these, otherwise
     * an operator could refund the same amount twice.
     */
    private const UNRESOLVED_ERRORS = [
        'PG_PROVIDER',                           // the PG itself errored mid-request
        'PAYMENT_ALREADY_CANCELLED',             // the cancellation already happened
        'CANCELLABLE_AMOUNT_CONSISTENCY_BROKEN', // our balance view drifted; re-query first
    ];

    private $transport;

    public function __construct(?callable $transport = null) { $this->transport = $transport; }

    private function request(string $method, string $path, ?array $body = null): array {
        $secret = trim(booking_payment_setting('PORTONE_API_SECRET'));
        if ($secret === '') throw new RuntimeException('PortOne credentials are not configured.');
        // V2 authenticates with the API secret directly; there is no token exchange.
        $headers = ['Content-Type: application/json', 'Authorization: PortOne ' . $secret];
        if ($this->transport) {
            [$status, $raw] = ($this->transport)($method, $path, $body, $headers);
        } else {
            $ch = curl_init('https://api.portone.io' . $path);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            $raw = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($status >= 200 && $status < 300) {
            if (!is_array($data)) throw new RuntimeException('Invalid PortOne response.');
            return $data;
        }
        $type = (is_array($data) && isset($data['type']) && is_string($data['type'])) ? $data['type'] : '';
        // A 4xx carrying a known error type is a decision. 5xx, transport failures
        // and the unresolved list above leave the outcome unknown on purpose.
        if ($status >= 400 && $status < 500 && $type !== '' && !in_array($type, self::UNRESOLVED_ERRORS, true)) {
            throw new BookingPortOneRejectedException('PortOne rejected the request: ' . $type);
        }
        throw new RuntimeException('PortOne request failed; reconcile payment before retrying a money operation.');
    }

    /** Register the server-owned amount before the checkout window opens. */
    public function prepare(string $paymentId, int $amount): void {
        $this->request('POST', '/payments/' . rawurlencode($paymentId) . '/pre-register',
            ['totalAmount' => $amount, 'currency' => 'KRW']);
    }

    /** Authoritative payment state, keyed by the id we issued ourselves. */
    public function payment(string $paymentId): array {
        return self::normalize($this->request('GET', '/payments/' . rawurlencode($paymentId)));
    }

    /**
     * V2 has a single identifier, so a merchant lookup is the same call.
     * Kept so callers do not have to know which version is underneath.
     */
    public function find(string $paymentId): array {
        return $this->payment($paymentId);
    }

    /** Partial or full refund. $remaining guards against refunding twice. */
    public function cancel(string $paymentId, int $amount, int $remaining, string $reason): array {
        if ($amount <= 0 || $amount > $remaining) throw new InvalidArgumentException('Invalid refund amount.');
        return $this->request('POST', '/payments/' . rawurlencode($paymentId) . '/cancel',
            ['amount' => $amount, 'reason' => $reason, 'currentCancellableAmount' => $remaining]);
    }

    /**
     * Reduce a V2 payment object to the version-neutral shape the ledger reads.
     * Unpaid states collapse to 'ready' because no money exists yet.
     */
    private static function normalize(array $p): array {
        static $states = [
            'PAID' => 'paid',
            'PARTIAL_CANCELLED' => 'paid',      // still partly held; cancelled_amount carries the refund
            'CANCELLED' => 'cancelled',
            'READY' => 'ready',
            'FAILED' => 'failed',
            'PENDING' => 'ready',               // awaiting provider approval
            'VIRTUAL_ACCOUNT_ISSUED' => 'ready' // account issued, nothing deposited
        ];
        $status = (string)($p['status'] ?? '');
        if (!isset($states[$status])) throw new RuntimeException('Unknown PortOne payment status.');
        $amount = is_array($p['amount'] ?? null) ? $p['amount'] : [];
        $paidAt = isset($p['paidAt']) && is_string($p['paidAt']) ? strtotime($p['paidAt']) : false;
        $channel = is_array($p['channel'] ?? null) ? ($p['channel']['type'] ?? null) : null;
        return [
            'payment_id' => (string)($p['id'] ?? ''),
            'transaction_id' => isset($p['transactionId']) && is_string($p['transactionId']) ? $p['transactionId'] : null,
            'status' => $states[$status],
            'raw_status' => $status,
            'amount' => isset($amount['total']) && is_int($amount['total']) ? $amount['total'] : null,
            'cancelled_amount' => isset($amount['cancelled']) && is_int($amount['cancelled']) ? $amount['cancelled'] : 0,
            'currency' => (string)($p['currency'] ?? ''),
            'paid_at' => $paidAt === false ? null : $paidAt,
            // Absent on states that never reached a channel; only checked once money exists.
            'live' => $channel === null ? null : $channel === 'LIVE',
        ];
    }
}

/** Pure validation used by callbacks, webhooks, reconciliation and regression tests. */
function booking_portone_validate(array $order, array $remote): string {
    if (($remote['payment_id'] ?? '') !== $order['merchant_uid']
        || !is_int($remote['amount'] ?? null) || $remote['amount'] !== (int)$order['amount']
        || ($remote['currency'] ?? '') !== 'KRW') {
        throw new RuntimeException('Payment identity, amount or currency mismatch.');
    }
    // Existing transactions keep their original environment after a rollout switch.
    $sandbox = in_array($order['provider'] ?? '', ['portone', 'portone_test'], true)
        ? $order['provider'] === 'portone_test'
        : booking_payment_setting('PORTONE_SANDBOX', '1') === '1';
    $provider = $sandbox ? 'portone_test' : 'portone';
    $settled = in_array($remote['status'] ?? '', ['paid', 'cancelled'], true);
    if ($settled) {
        // Environment and transaction identity only carry meaning once money exists.
        if (!is_bool($remote['live'] ?? null) || $remote['live'] === $sandbox) {
            throw new RuntimeException('Unexpected PortOne environment.');
        }
        if (!is_string($remote['transaction_id'] ?? null) || $remote['transaction_id'] === '') {
            throw new RuntimeException('Missing provider transaction ID.');
        }
        if (!is_int($remote['paid_at'] ?? null) || $remote['paid_at'] <= 0 || $remote['paid_at'] > time() + 300) {
            throw new RuntimeException('Invalid provider payment time.');
        }
    }
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

/**
 * Standard Webhooks verification (https://www.standardwebhooks.com/).
 * Pure so the regression suite can exercise it without an HTTP request.
 * Throws on any failure; returns nothing when the signature is valid.
 */
function booking_portone_verify_webhook(string $secret, string $payload, array $headers, ?int $now = null): void {
    $now = $now ?? time();
    $header = static function (array $all, string $name): string {
        foreach ($all as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) return is_array($value) ? (string)reset($value) : (string)$value;
        }
        return '';
    };
    $id = $header($headers, 'webhook-id');
    $timestamp = $header($headers, 'webhook-timestamp');
    $signature = $header($headers, 'webhook-signature');
    if ($id === '' || $timestamp === '' || $signature === '') throw new RuntimeException('Missing webhook signature headers.');
    if (!preg_match('/^\d{1,10}$/D', $timestamp)) throw new RuntimeException('Invalid webhook timestamp.');
    // A replayed notification outside the tolerance window is refused.
    if (abs($now - (int)$timestamp) > 300) throw new RuntimeException('Webhook timestamp outside tolerance.');
    $encoded = strncmp($secret, 'whsec_', 6) === 0 ? substr($secret, 6) : $secret;
    $key = base64_decode($encoded, true);
    if ($key === false || $key === '') throw new RuntimeException('Invalid webhook secret.');
    $expected = hash_hmac('sha256', $id . '.' . $timestamp . '.' . $payload, $key, true);
    // The header may carry several space-separated versioned signatures.
    foreach (explode(' ', $signature) as $candidate) {
        $parts = explode(',', $candidate, 2);
        if (count($parts) !== 2 || $parts[0] !== 'v1') continue;
        $given = base64_decode($parts[1], true);
        if ($given !== false && hash_equals($expected, $given)) return;
    }
    throw new RuntimeException('No matching webhook signature.');
}

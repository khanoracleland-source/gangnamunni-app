<?php
require_once __DIR__ . '/booking_payment_config.php';
final class BookingPortOneRejectedException extends RuntimeException {}

final class BookingPortOneClient {
    private $transport;
    private ?string $token = null;
    public function __construct(?callable $transport = null) { $this->transport = $transport; }
    private function request(string $method, string $path, ?array $body = null, bool $authenticated = true): array {
        $headers = ['Content-Type: application/json'];
        if ($authenticated) $headers[] = 'Authorization: Bearer ' . $this->token();
        if ($this->transport) {
            [$status, $raw] = ($this->transport)($method, $path, $body, $headers);
        } else {
            $ch = curl_init('https://api.iamport.kr' . $path);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            $raw = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($status >= 200 && $status < 300 && is_array($data)
            && isset($data['code']) && is_int($data['code']) && $data['code'] !== 0) {
            throw new BookingPortOneRejectedException('PortOne rejected the request.');
        }
        if ($status < 200 || $status >= 300 || !is_array($data) || ($data['code'] ?? null) !== 0
            || !is_array($data['response'] ?? null)) {
            throw new RuntimeException('PortOne request failed; reconcile payment before retrying a money operation.');
        }
        return $data['response'];
    }
    private function token(): string {
        if ($this->token !== null) return $this->token;
        $key = booking_payment_setting('PORTONE_API_KEY');
        $secret = booking_payment_setting('PORTONE_API_SECRET');
        if ($key === '' || $secret === '') throw new RuntimeException('PortOne credentials are not configured.');
        $data = $this->request('POST', '/users/getToken', ['imp_key' => $key, 'imp_secret' => $secret], false);
        if (empty($data['access_token']) || !is_string($data['access_token'])) throw new RuntimeException('Invalid PortOne token.');
        return $this->token = $data['access_token'];
    }
    public function prepare(string $merchant, int $amount): void {
        $this->request('POST', '/payments/prepare', ['merchant_uid' => $merchant, 'amount' => $amount]);
    }
    public function payment(string $id): array {
        if (!preg_match('/^imp_[A-Za-z0-9_-]{1,180}$/D', $id)) throw new InvalidArgumentException('Invalid payment ID.');
        return $this->request('GET', '/payments/' . rawurlencode($id));
    }
    public function find(string $merchant): array {
        return $this->request('GET', '/payments/find/' . rawurlencode($merchant));
    }
    public function cancel(string $id, int $amount, int $remaining, string $reason): array {
        if ($amount <= 0 || $amount > $remaining) throw new InvalidArgumentException('Invalid refund amount.');
        return $this->request('POST', '/payments/cancel', ['imp_uid' => $id, 'amount' => $amount,
            'checksum' => $remaining, 'reason' => $reason]);
    }
}
/** Pure validation used by callbacks, webhooks, reconciliation and regression tests. */
function booking_portone_validate(array $order, array $remote): string {
    if (($remote['merchant_uid'] ?? '') !== $order['merchant_uid']
        || !preg_match('/^imp_[A-Za-z0-9_-]{1,180}$/D', (string)($remote['imp_uid'] ?? ''))
        || !is_numeric($remote['amount'] ?? null) || (float)$remote['amount'] !== (float)$order['amount']
        || ($remote['currency'] ?? '') !== 'KRW'
        || !is_bool($remote['sandbox'] ?? null)) {
        throw new RuntimeException('Payment identity, amount, currency or environment mismatch.');
    }
    // Existing transactions keep their original environment after a rollout switch.
    $sandbox = in_array($order['provider'] ?? '', ['portone', 'portone_test'], true)
        ? $order['provider'] === 'portone_test'
        : booking_payment_setting('PORTONE_SANDBOX', '1') === '1';
    if ($remote['sandbox'] !== $sandbox) throw new RuntimeException('Unexpected PortOne environment.');
    $provider = $sandbox ? 'portone_test' : 'portone';
    if (!empty($order['provider_transaction_id'])
        && ($order['provider_transaction_id'] !== $remote['imp_uid'] || $order['provider'] !== $provider)) {
        throw new RuntimeException('Conflicting payment transaction.');
    }
    $cancelled = $remote['cancel_amount'] ?? 0;
    if (!is_numeric($cancelled) || (float)$cancelled !== (float)(int)$cancelled
        || $cancelled < 0 || $cancelled > $order['amount']) throw new RuntimeException('Invalid refund total.');
    if (in_array($remote['status'] ?? '', ['paid', 'cancelled'], true)
        && (!is_int($remote['paid_at'] ?? null) || $remote['paid_at'] <= 0 || $remote['paid_at'] > time() + 300)) {
        throw new RuntimeException('Invalid provider payment time.');
    }
    return $provider;
}

<?php
/** Server-owned payment switches. Never accept these switches from a request. */
function booking_payment_setting(string $key, string $default = ''): string {
    if (defined($key)) return (string)constant($key);
    $value = getenv($key);
    return $value === false ? $default : (string)$value;
}
function booking_payment_live_enabled(): bool {
    return booking_payment_setting('G5_PG_PAYMENTS_ENABLED', '0') === '1';
}
function booking_payment_preview_enabled(): bool {
    // Kept available during rollout at the owner's request. Set to 0 to retire it.
    return booking_payment_setting('BOOKING_PAYMENT_PREVIEW_ENABLED', '1') === '1';
}
function booking_payment_base_url(): string {
    $url = rtrim(booking_payment_setting('BOOKING_PAYMENT_BASE_URL'), '/');
    if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
        || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) {
        throw new RuntimeException('Configure BOOKING_PAYMENT_BASE_URL as the public HTTPS gangnamunni URL.');
    }
    return $url;
}
function booking_payment_channels(): array {
    $channels = [];
    foreach (['card', 'bank', 'naverpay', 'kakaopay', 'tosspay'] as $method) {
        $key = booking_payment_setting('PORTONE_CHANNEL_' . strtoupper($method));
        if ($key !== '') $channels[$method] = $key;
    }
    return $channels;
}
function booking_payment_is_test(?string $provider): bool {
    return in_array($provider, ['mock_booking_payment', 'portone_test'], true);
}

function booking_payment_checkout_ready(): bool {
    if (!booking_payment_live_enabled() || !booking_payment_channels()) return false;
    foreach (['PORTONE_STORE_ID','PORTONE_API_SECRET','PORTONE_WEBHOOK_SECRET'] as $key) {
        if (trim(booking_payment_setting($key)) === '') return false;
    }
    try { booking_payment_base_url(); } catch (Throwable $e) { return false; }
    return true;
}
/** Consistent presentation for the checkout and booking list. */
function booking_payment_display_state(?array $order, bool $preview = false): string {
    if (!$order) return '';
    $status = $order['status'] ?? '';
    if (in_array($status, ['refunded','cancelled','failed'], true)) return $status;
    if (in_array($status, ['paid','partially_refunded'], true)) {
        return booking_payment_is_test($order['provider'] ?? null) ? 'test_paid' : $status;
    }
    if ($status === 'preview' || ($status === 'pending' && $preview)) return 'preview';
    return $status === 'pending' && (int)($order['amount'] ?? 0) > 0 ? 'payable' : '';
}

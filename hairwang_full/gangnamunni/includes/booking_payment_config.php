<?php
require_once __DIR__ . '/booking_payment_gateway.php';
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
/**
 * 결제 화면에서 켤 결제 수단. BOOKING_PAYMENT_METHODS='card,bank,...' (서버 설정).
 * 반환: [method => true]. PG 계약에서 승인된 수단만 켠다.
 */
function booking_payment_methods(): array {
    $allowed = ['card', 'bank', 'naverpay', 'kakaopay', 'tosspay'];
    $methods = [];
    foreach (explode(',', booking_payment_setting('BOOKING_PAYMENT_METHODS')) as $method) {
        $method = strtolower(trim($method));
        if (in_array($method, $allowed, true)) $methods[$method] = true;
    }
    // 화면 표시 순서를 고정한다.
    return array_intersect_key(array_fill_keys($allowed, true), $methods);
}

/**
 * provider 컬럼 값 목록 — 이 목록만 바꾸면 원장/정산/관리 화면이 함께 따라온다.
 * PG 연동이 기록하는 값(실결제+샌드박스).
 */
function booking_payment_pg_providers(): array {
    return ['toss', 'toss_test'];
}
/**
 * 매출·정산에서 제외할 값. 'portone_test' 는 제거된 PortOne 샌드박스 시절의
 * 기존 행이 혹시 남아 있어도 정산에 들어가지 않도록 유지한다.
 */
function booking_payment_test_providers(): array {
    return ['mock_booking_payment', 'toss_test', 'portone_test'];
}
function booking_payment_is_test(?string $provider): bool {
    return in_array($provider, booking_payment_test_providers(), true);
}
function booking_payment_is_pg(?string $provider): bool {
    return in_array($provider, booking_payment_pg_providers(), true);
}
/** 실제 돈이 오간 PG 결제(샌드박스 제외). */
function booking_payment_is_live_pg(?string $provider): bool {
    return booking_payment_is_pg($provider) && !booking_payment_is_test($provider);
}
/** SQL IN (...) 용 목록. 값은 고정 상수(영문 소문자/밑줄)만 허용한다. */
function booking_payment_sql_list(array $providers): string {
    if (!$providers) throw new LogicException('Empty provider list.');
    foreach ($providers as $p) {
        if (!preg_match('/^[a-z0-9_]+$/D', (string)$p)) throw new LogicException('Invalid provider constant.');
    }
    return "'" . implode("','", $providers) . "'";
}

function booking_payment_checkout_ready(): bool {
    if (!booking_payment_live_enabled() || !booking_payment_methods()) return false;
    if (!booking_payment_gateway()) return false;
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

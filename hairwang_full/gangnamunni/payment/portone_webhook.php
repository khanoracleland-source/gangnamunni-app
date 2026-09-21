<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
require_once dirname(__DIR__) . '/includes/booking_payment_service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') booking_payment_json(['success' => false], 405);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) booking_payment_json(['success' => false], 413);
$raw = (string)file_get_contents('php://input', false, null, 0, 16385);

function portone_webhook_headers(): array {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strncmp($key, 'HTTP_', 5) === 0) {
            $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = $value;
        }
    }
    return $headers;
}

$secret = booking_payment_setting('PORTONE_WEBHOOK_SECRET');
if ($secret === '') {
    error_log('[portone_webhook] PORTONE_WEBHOOK_SECRET is not configured.');
    booking_payment_json(['success' => false], 503);
}
try {
    booking_portone_verify_webhook($secret, $raw, portone_webhook_headers());
} catch (Throwable $e) {
    error_log('[portone_webhook] signature rejected: ' . $e->getMessage());
    booking_payment_json(['success' => false], 401);
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    error_log('[portone_webhook] invalid json payload');
    booking_payment_json(['success' => false], 400);
}
$type = (string)($input['type'] ?? '');
$data = $input['data'] ?? null;
if (!is_array($data)) {
    error_log('[portone_webhook] missing data field');
    booking_payment_json(['success' => false, 'ignored' => true]);
}
$merchant = (string)($data['paymentId'] ?? '');
if (strncmp($type, 'Transaction.', 12) !== 0
    || !preg_match('/^BK_[A-Za-z0-9_]{1,96}$/D', $merchant)) {
    error_log('[portone_webhook] ignored: ' . ($type !== '' ? $type : 'unparsable body'));
    booking_payment_json(['success' => true, 'ignored' => true]);
}
try {
    $db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone='+09:00'");
    booking_payment_sync($db, $merchant, 'webhook');
    booking_payment_json(['success' => true]);
} catch (Throwable $e) {
    error_log('[portone_webhook] ' . $e->getMessage());
    booking_payment_json(['success' => false], 503);
}

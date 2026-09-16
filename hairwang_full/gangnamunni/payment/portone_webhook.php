<?php
// Do not use session/HTML bootstrap for server-to-server notifications.
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
require_once dirname(__DIR__) . '/includes/booking_payment_service.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') booking_payment_json(['success'=>false], 405);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) booking_payment_json(['success'=>false], 413);
$input = json_decode(file_get_contents('php://input', false, null, 0, 16385), true);
if (!is_array($input)) $input = $_POST;
$merchant = (string)($input['merchant_uid'] ?? '');
$imp = (string)($input['imp_uid'] ?? '');
if (!preg_match('/^BK_[A-Za-z0-9_]{1,96}$/D', $merchant) || !preg_match('/^imp_[A-Za-z0-9_-]{1,180}$/D', $imp)) {
    booking_payment_json(['success'=>false], 400);
}
try {
    $db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone='+09:00'");
    // Ignore claimed status/amount. Only authenticated PortOne API data changes money.
    booking_payment_sync($db, $merchant, $imp, 'webhook');
    booking_payment_json(['success'=>true]);
} catch (Throwable $e) {
    error_log('[portone_webhook] ' . $e->getMessage());
    booking_payment_json(['success'=>false], 503); // Retry instead of losing a payment.
}

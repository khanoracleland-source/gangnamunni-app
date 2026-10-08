<?php
/**
 * Toss 결제 웹훅(PAYMENT_STATUS_CHANGED). 개발자센터 > 웹훅에 이 URL 을 등록한다.
 * 결제 웹훅에는 서명이 없으므로 본문은 "이 주문을 다시 확인하라"는 신호로만 쓰고,
 * 실제 상태는 booking_payment_sync() 가 인증된 API 로 다시 조회해 결정한다.
 * 200 이 아니면 Toss 가 재전송한다.
 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
require_once dirname(__DIR__) . '/includes/booking_payment_service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') booking_payment_json(['success' => false], 405);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) booking_payment_json(['success' => false], 413);
$input = json_decode((string)file_get_contents('php://input', false, null, 0, 65537), true);
if (!is_array($input)) booking_payment_json(['success' => false], 400);

$type = (string)($input['eventType'] ?? '');
$merchant = is_array($input['data'] ?? null) ? (string)($input['data']['orderId'] ?? '') : '';
if ($type !== 'PAYMENT_STATUS_CHANGED' || !preg_match('/^BK_[A-Za-z0-9_]{1,61}$/D', $merchant)) {
    booking_payment_json(['success' => true, 'ignored' => true]);
}
if (!booking_payment_gateway()) {
    error_log('[toss_webhook] received while no gateway is configured');
    booking_payment_json(['success' => true, 'ignored' => true]);
}
try {
    $db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone='+09:00'");
    $known = $db->prepare("SELECT 1 FROM gangnamunni_platform_payments WHERE merchant_uid=? AND payment_type='booking'");
    $known->bind_param('s', $merchant);
    $known->execute();
    if (!$known->get_result()->num_rows) booking_payment_json(['success' => true, 'ignored' => true]);   // not our order
    booking_payment_sync($db, $merchant, 'webhook');
    booking_payment_json(['success' => true]);
} catch (Throwable $e) {
    error_log('[toss_webhook] ' . $e->getMessage());
    booking_payment_json(['success' => false], 500);
}

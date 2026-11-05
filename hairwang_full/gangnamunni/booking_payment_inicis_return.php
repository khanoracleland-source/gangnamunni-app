<?php
/**
 * P_NEXT_URL: 이니시스가 사용자의 브라우저로 인증 결과를 POST 하는 곳(cross-site, 세션·CSRF 없음).
 * 세션/그누보드를 불러오지 않는다(요청값 가공·쿠키 부작용 없음). 항상 303 으로 사이트 안 화면으로 돌려보내고 이니시스 문구는 되돌리지 않는다.
 * 흐름: includes/booking_payment_inicis_flow.php
 */
header('Cache-Control: no-store');
require_once __DIR__ . '/includes/booking_payment_inicis_flow.php';
require_once __DIR__ . '/includes/booking_payment_config.php';
try { $base = booking_payment_base_url(); }
catch (Throwable $e) { error_log('[inicis_return] ' . $e->getMessage()); http_response_code(503); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: ' . $base . '/my_bookings.php', true, 303); exit; }
$gateway = booking_payment_gateway();
if (!($gateway instanceof InicisGateway) || !$gateway->canCheckout()) {
    header('Location: ' . $base . '/my_bookings.php?pay_error=UNAVAILABLE', true, 303);
    exit;
}
$to = booking_payment_inicis_return($gateway, $_POST, $base, booking_payment_inicis_io(booking_payment_ledger_db(), $gateway));
header('Location: ' . $to, true, 303);
exit;

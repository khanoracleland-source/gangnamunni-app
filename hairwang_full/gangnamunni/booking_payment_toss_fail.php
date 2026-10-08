<?php
/**
 * Toss 결제창 실패 URL(사용자 취소, 인증 실패 등). 승인 전이므로 청구된 금액은 없다.
 * PG 가 보낸 message 는 화면에 그대로 쓰지 않고, 코드만 결제 화면에 넘긴다.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
header('Cache-Control: no-store');

$code = preg_replace('/[^A-Z_]/', '', (string)($_GET['code'] ?? ''));
$merchant = (string)($_GET['orderId'] ?? '');
$bookingId = 0;
if (preg_match('/^BK_[A-Za-z0-9_]{1,61}$/D', $merchant)) {
    try {
        $bookingId = (int)booking_payment_by_merchant($connect, $merchant)['booking_id'];
    } catch (Throwable $e) {
        $bookingId = 0;
    }
}
if ($code !== '' && !in_array($code, ['PAY_PROCESS_CANCELED', 'USER_CANCEL'], true)) {
    error_log('[booking_payment_toss_fail] ' . ($merchant !== '' ? $merchant . ' ' : '') . $code);
}
header('Location: ' . ($bookingId > 0
    ? 'booking_payment.php?booking_id=' . $bookingId . '&pay_error=' . rawurlencode($code !== '' ? $code : 'PAY_PROCESS_ABORTED')
    : 'my_bookings.php'));
exit;

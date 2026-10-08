<?php
/**
 * Toss 결제창 성공 URL. 여기서 서버가 결제를 승인(confirm)한다.
 * 쿼리(paymentKey·orderId·amount)는 신뢰하지 않고 잠금 안에서 원장·예약·포인트와 대조한다.
 * 승인하지 않으면 돈은 빠져나가지 않고 PG 결제 건은 만료된다.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
header('Cache-Control: no-store');

function toss_success_redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

$merchant = (string)($_GET['orderId'] ?? '');
$paymentKey = (string)($_GET['paymentKey'] ?? '');
$amountRaw = (string)($_GET['amount'] ?? '');
if (!preg_match('/^BK_[A-Za-z0-9_]{1,61}$/D', $merchant)
    || !preg_match('/^[A-Za-z0-9_\-]{1,200}$/D', $paymentKey)
    || !preg_match('/^[1-9][0-9]{0,9}$/D', $amountRaw)) {
    toss_success_redirect('my_bookings.php');
}
try {
    $order = booking_payment_by_merchant($connect, $merchant);
} catch (Throwable $e) {
    toss_success_redirect('my_bookings.php');
}
$bookingId = (int)$order['booking_id'];
try {
    $result = booking_payment_confirm($connect, $merchant, $paymentKey, (int)$amountRaw);
} catch (Throwable $e) {
    error_log('[booking_payment_toss_success] ' . $e->getMessage());
    $result = ['outcome' => 'pending', 'reason' => 'UNRESOLVED'];
}
if ($result['outcome'] === 'paid') {
    toss_success_redirect('booking_payment_success.php?booking_id=' . $bookingId);
}
if ($result['outcome'] !== 'pending') {
    error_log('[booking_payment_toss_success] ' . $merchant . ' ' . $result['outcome'] . ': ' . $result['reason']);
}
$code = $result['outcome'] === 'pending' ? 'UNRESOLVED' : preg_replace('/[^A-Z_]/', '', (string)$result['reason']);
toss_success_redirect('booking_payment.php?booking_id=' . $bookingId . '&pay_error=' . rawurlencode($code));

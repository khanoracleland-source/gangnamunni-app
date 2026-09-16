<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
booking_payment_require_post();
$id = (int)($_POST['booking_id'] ?? 0);
if (!booking_payment_owned($connect, $id)) booking_payment_json(['success'=>false, 'message'=>'예약에 접근할 수 없습니다.'], 404);
try {
    $order = booking_payment_order($connect, $id);
    if (!$order) throw new RuntimeException('Order not found.');
    $order = booking_payment_sync($connect, $order['merchant_uid'], (string)($_POST['imp_uid'] ?? ''));
    $paid = in_array($order['status'], ['paid', 'partially_refunded'], true);
    booking_payment_json(['success'=>$paid, 'status'=>$order['status'],
        'message'=>$paid ? '결제가 확인되었습니다.' : '결제가 아직 확인되지 않았습니다. 다시 확인해주세요.',
        'redirect_url'=>'booking_payment_success.php?booking_id=' . $id]);
} catch (Throwable $e) {
    error_log('[payment_verify] ' . $e->getMessage());
    booking_payment_json(['success'=>false, 'message'=>'결제 확인이 지연되고 있습니다. 다시 결제하지 말고 결제 상태를 확인해주세요.'], 502);
}

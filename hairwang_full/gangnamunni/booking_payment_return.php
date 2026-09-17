<?php
// Mobile redirects are untrusted; query PortOne with the owner's server-side order.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
header('Cache-Control: no-store');
$id = (int)($_GET['booking_id'] ?? 0);
if (!booking_payment_owned($connect, $id)) {
    header('Location: auth/login.php?url=' . urlencode('/app/booking_payment.php?booking_id=' . $id));
    exit;
}
try {
    $order = booking_payment_order($connect, $id);
    if (!$order) throw new RuntimeException('Order not found.');
    booking_payment_sync($connect, $order['merchant_uid'], (string)($_GET['imp_uid'] ?? ''));
} catch (Throwable $e) { error_log('[payment_return] ' . $e->getMessage()); }
header('Location: booking_payment_success.php?booking_id=' . $id);
exit;

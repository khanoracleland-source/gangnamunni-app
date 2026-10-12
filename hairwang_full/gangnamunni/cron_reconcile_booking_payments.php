<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/includes/booking_payment_db_config.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
if (!booking_payment_gateway()) {
    echo "No payment gateway configured; nothing to reconcile.\n";
    exit(0);
}
$db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
$db->set_charset('utf8mb4');
$db->query("SET time_zone='+09:00'");
// Cursor supports scanning ALL orders, rather than starving older orders behind a LIMIT.
$after = max(0, (int)($argv[1] ?? 0));
// pending 은 승인 시도(gangnamunni_payment_attempts)가 있는 건만 조회한다: 결제창을 열지도 않은 주문까지 Toss API 를 부르지 않기 위함.
// paid/partially_refunded 는 Toss 콘솔 환불 등을 반영해야 하므로 계속 전부 대조한다.
$rows = $db->query("SELECT p.id,p.merchant_uid,p.provider_transaction_id FROM gangnamunni_platform_payments p
    WHERE p.id>$after AND p.payment_type='booking' AND (p.provider IS NULL OR p.provider IN (" . booking_payment_sql_list(booking_payment_pg_providers()) . "))
      AND (p.status IN ('paid','partially_refunded')
           OR (p.status='pending' AND EXISTS (SELECT 1 FROM gangnamunni_payment_attempts a WHERE a.payment_id=p.id)))
    ORDER BY p.id LIMIT 100");
$failed = 0;
while ($row = $rows->fetch_assoc()) {
    $after = (int)$row['id'];
    try {
        booking_payment_sync($db, $row['merchant_uid']);
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "Payment {$row['id']}: reconciliation unavailable.\n");
    }
}
echo "Next cursor: $after; failures: $failed\n";
exit($failed ? 1 : 0);

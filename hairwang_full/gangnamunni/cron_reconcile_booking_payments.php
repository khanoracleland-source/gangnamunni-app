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
$rows = $db->query("SELECT id,merchant_uid,provider_transaction_id FROM gangnamunni_platform_payments
    WHERE id>$after AND payment_type='booking' AND (provider IS NULL OR provider IN (" . booking_payment_sql_list(booking_payment_pg_providers()) . "))
      AND status IN ('pending','paid','partially_refunded') ORDER BY id LIMIT 100");
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

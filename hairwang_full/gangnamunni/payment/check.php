<?php
/** Read-only preflight. Prints flags and schema availability, never secret values. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/booking_payment_db_config.php';
require_once dirname(__DIR__).'/includes/booking_payment_config.php';
echo 'Real checkout: '.(booking_payment_checkout_ready() ? 'READY' : 'NOT READY')."\n";
echo 'Payment-free preview: '.(booking_payment_preview_enabled() ? 'ON' : 'OFF')."\n";
$gateway = booking_payment_gateway();
echo 'Payment gateway: '.($gateway ? $gateway->provider() : 'NOT CONNECTED')."\n";
foreach (['BOOKING_PAYMENT_BASE_URL'] as $key) {
    echo $key.': '.(trim(booking_payment_setting($key)) === '' ? 'MISSING' : 'SET')."\n";
}
echo 'Configured methods: '.(implode(', ',array_keys(booking_payment_methods())) ?: 'NONE')."\n";
try {
    $db = new mysqli(G5_MYSQL_HOST,G5_MYSQL_USER,G5_MYSQL_PASSWORD,G5_MYSQL_DB,defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    $required=['gangnamunni_platform_payments','gangnamunni_payment_events','gangnamunni_payment_status_history','gangnamunni_booking_payment_previews','gangnamunni_payment_refund_requests','gangnamunni_hospital_settlements','gangnamunni_hospital_settlement_items'];
    $missing=false;
    foreach($required as $table) {
        $stmt=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->bind_param('s',$table);$stmt->execute();
        $exists=$stmt->get_result()->num_rows>0;
        echo $table.': '.($exists?'OK':'MISSING')."\n";
        $missing=$missing || !$exists;
    }
    if (!$missing) {
        $status=$db->query("SHOW COLUMNS FROM gangnamunni_payment_refund_requests LIKE 'status'")->fetch_assoc();
        $refundReady=strpos($status['Type'],"'failed'")!==false;
        $carryReady=$db->query("SHOW COLUMNS FROM gangnamunni_hospital_settlements LIKE 'carry_forward_amount'")->num_rows>0;
        echo 'Current migration: '.($refundReady && $carryReady ? 'OK':'REQUIRED')."\n";
        $missing=!$refundReady || !$carryReady;
    }
    exit($missing?1:0);
} catch(Throwable $e) { fwrite(STDERR,"Database preflight failed. Check connection/schema locally.\n");exit(1); }

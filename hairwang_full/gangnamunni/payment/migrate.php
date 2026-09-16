<?php
// Explicit CLI migration. Never mutate schema during a checkout request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
if (in_array('--local-only', $argv, true) && !in_array(G5_MYSQL_HOST, ['mysql','localhost','127.0.0.1'], true)) {
    fwrite(STDERR, "Refusing a non-local database.\n"); exit(1);
}
$db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
$db->set_charset('utf8mb4');
foreach (explode(';', file_get_contents(dirname(__DIR__) . '/sql/booking_payment_previews.sql')) as $sql) {
    if (trim($sql) !== '') $db->query($sql);
}
$refund_status = $db->query("SHOW COLUMNS FROM gangnamunni_payment_refund_requests LIKE 'status'")->fetch_assoc();
if (strpos($refund_status['Type'], "'failed'") === false) {
    $db->query("ALTER TABLE gangnamunni_payment_refund_requests MODIFY status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending'");
}
$table = $db->query("SHOW TABLES LIKE 'gangnamunni_hospital_settlements'");
if ($table->num_rows && !$db->query("SHOW COLUMNS FROM gangnamunni_hospital_settlements LIKE 'carry_forward_amount'")->num_rows) {
    $db->query('ALTER TABLE gangnamunni_hospital_settlements ADD COLUMN carry_forward_amount BIGINT NOT NULL DEFAULT 0 AFTER adjustments');
}
echo "Payment preview and settlement migration complete.\n";

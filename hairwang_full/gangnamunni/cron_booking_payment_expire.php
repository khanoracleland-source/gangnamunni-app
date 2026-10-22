<?php
/**
 * [05-37] 결제 기한 자동 취소.
 * 병원이 확정한 뒤 payment_deadline_hours(기본 24시간) 안에 결제하지 않은 예약을 취소한다.
 * - 결제된 예약(paid/partially_refunded/refunded)과 '결제 없이 계속하기'를 누른 예약은 제외
 * - 취소자는 cancelled_by='system' 으로 기록해 노쇼·잦은 취소 집계에서 빠진다
 * - 결제에 걸어 둔 포인트는 복구한다
 *
 * Suggested crontab (10분마다):
 * *​/10 * * * * /usr/bin/php /var/www/html/.../gangnamunni/cron_booking_payment_expire.php >> /var/log/booking_payment_expire.log 2>&1
 * 인자: --dry-run  (취소하지 않고 대상만 출력)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/includes/booking_payment_db_config.php';
require_once __DIR__ . '/includes/booking_payment_service.php';

$dry = in_array('--dry-run', $argv ?? [], true);
$connect = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
$connect->set_charset('utf8mb4');
$connect->query("SET time_zone='+09:00'");

$col = $connect->query("SHOW COLUMNS FROM gangnamunni_bookings LIKE 'confirmed_at'");
if (!$col || $col->num_rows === 0) { echo "confirmed_at column missing; run sql/booking_payment_deadline_20261022.sql\n"; exit(1); }

$hours = 24;
$sr = $connect->query("SELECT setting_value FROM gangnamunni_settings WHERE setting_key='payment_deadline_hours' LIMIT 1");
if ($sr && ($row = $sr->fetch_assoc()) && ctype_digit((string)$row['setting_value'])) $hours = (int)$row['setting_value'];
if ($hours < 1 || $hours > 168) $hours = 24;

$prev = $connect->query("SHOW TABLES LIKE 'gangnamunni_booking_payment_previews'");
$has_prev = $prev && $prev->num_rows > 0;

$sql = "SELECT b.id FROM gangnamunni_bookings b
        WHERE b.status='confirmed' AND b.confirmed_at IS NOT NULL
          AND b.confirmed_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
          AND NOT EXISTS (SELECT 1 FROM gangnamunni_platform_payments p
                          WHERE p.payment_type='booking' AND p.booking_id=b.id
                            AND p.status IN ('paid','partially_refunded','refunded'))"
     . ($has_prev ? " AND NOT EXISTS (SELECT 1 FROM gangnamunni_booking_payment_previews v WHERE v.booking_id=b.id)" : '')
     . " ORDER BY b.id LIMIT 200";
$rows = $connect->query($sql);
$done = 0; $failed = 0;
while ($rows && ($r = $rows->fetch_assoc())) {
    $id = (int)$r['id'];
    if ($dry) { echo "[dry-run] would cancel booking {$id}\n"; continue; }
    try {
        $ok = booking_payment_lock_for_booking($connect, $id, function () use ($connect, $id, $has_prev) {
            // 잠금 안에서 다시 확인: 그 사이 결제/취소됐으면 건드리지 않는다.
            $c = $connect->query("SELECT status, hms_appointment_id FROM gangnamunni_bookings WHERE id={$id} FOR UPDATE");
            $b = $c ? $c->fetch_assoc() : null;
            if (!$b || $b['status'] !== 'confirmed') return false;
            $o = booking_payment_order($connect, $id);
            if ($o && in_array($o['status'], ['paid','partially_refunded','refunded'], true)) return false;
            $connect->begin_transaction();
            try {
                $reason = $connect->real_escape_string('결제 기한 만료(자동 취소)');
                $connect->query("UPDATE gangnamunni_bookings SET status='cancelled', cancel_reason='{$reason}', cancelled_at=NOW(),
                                 updated_at=NOW(), refund_status='not_applicable'
                                 WHERE id={$id} AND status='confirmed'");
                if ($connect->affected_rows !== 1) throw new RuntimeException('update_failed');
                $cb = $connect->query("SHOW COLUMNS FROM gangnamunni_bookings LIKE 'cancelled_by'");
                if ($cb && $cb->num_rows) $connect->query("UPDATE gangnamunni_bookings SET cancelled_by='system' WHERE id={$id}");
                if (!empty($b['hms_appointment_id'])) {
                    $apt = (int)$b['hms_appointment_id'];
                    $connect->query("UPDATE appointments SET is_completed=2, updated_at=NOW() WHERE id={$apt}");
                }
                $connect->commit();
            } catch (Throwable $e) { $connect->rollback(); throw $e; }
            return true;
        });
        if ($ok) {
            $done++;
            try { points_checkout_release_for_booking($connect, $id, '결제 기한 만료 포인트 복구'); }
            catch (Throwable $e) { fwrite(STDERR, "booking {$id}: point release failed\n"); }
            echo "cancelled booking {$id}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "booking {$id}: " . $e->getMessage() . "\n");
    }
}
echo date('Y-m-d H:i:s') . " deadline={$hours}h cancelled={$done} failed={$failed}\n";
exit($failed ? 1 : 0);

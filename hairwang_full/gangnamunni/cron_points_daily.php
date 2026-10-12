<?php
/**
 * 포인트 제도 v2 — 일일 크론 (#포인트제도 v2)
 *   권장: 매일 00:10 KST
 *   10 0 * * * php /path/gangnamunni/cron_points_daily.php >> /var/log/points_daily.log 2>&1
 *
 * 1) 결제 전 방치된 포인트 홀드 복구(v2_hold_ttl_minutes 경과 + 결제 미완료)
 * 2) 기한 지난 포인트 소멸            (스위치: v2_expire_job_enabled)
 * 3) 첫 후기 주간 일괄 적립(화요일)     (스위치: v2_first_review_enabled)
 * 스위치가 꺼져 있으면 해당 단계는 아무 것도 하지 않는다.
 *   옵션: --force-first-review  (오늘이 일괄 적립일이 아니어도 전주분 실행)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/includes/booking_payment_db_config.php';
require_once __DIR__ . '/includes/points_policy_engine.php';
require_once __DIR__ . '/includes/points_checkout.php';

$connect = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
$connect->set_charset('utf8mb4');
$connect->query("SET time_zone='+09:00'");
date_default_timezone_set('Asia/Seoul');

if (!points_v2_schema_ready($connect)) { echo "points v2 schema not installed (sql/points_v2.sql). skip\n"; exit(0); }
$fail = 0;

// 1) 방치된 홀드
$ttl = max(5, points_v2_int($connect, 'v2_hold_ttl_minutes', 60));
$res = $connect->query("SELECT h.booking_id FROM gangnamunni_point_payment_holds h
    JOIN gangnamunni_platform_payments p ON p.id = h.payment_id
    WHERE h.status = 'held' AND p.status = 'pending' AND h.created_at < DATE_SUB(NOW(), INTERVAL $ttl MINUTE)
    ORDER BY h.id LIMIT 200");
$released = 0;
while ($res && ($row = $res->fetch_assoc())) {
    try { if (points_checkout_release_for_booking($connect, (int)$row['booking_id'], '결제 미완료 포인트 복구', true)) $released++; }
    catch (Throwable $e) { $fail++; fwrite(STDERR, "hold booking {$row['booking_id']}: " . $e->getMessage() . "\n"); }
}
echo "holds released: $released\n";

// 2) 소멸
try {
    $total = ['expired_lots' => 0, 'expired_points' => 0];
    do {
        $r = points_v2_expire_due($connect, 500);
        $total['expired_lots'] += $r['expired_lots'];
        $total['expired_points'] += $r['expired_points'];
    } while ($r['expired_lots'] >= 500);
    echo "expired lots: {$total['expired_lots']} ({$total['expired_points']}P)" . (points_v2_on($connect, 'v2_expire_job_enabled') ? '' : ' [switch off]') . "\n";
} catch (Throwable $e) { $fail++; fwrite(STDERR, 'expire: ' . $e->getMessage() . "\n"); }

// 3) 첫 후기 일괄 적립
try {
    $force = in_array('--force-first-review', $argv ?? [], true);
    $r = points_v2_first_review_batch($connect, null, $force);
    echo 'first review batch: ' . ($r['ran'] ? "awarded {$r['awarded']}, skipped " . count($r['skipped']) : 'not today / switch off') . "\n";
} catch (Throwable $e) { $fail++; fwrite(STDERR, 'first review: ' . $e->getMessage() . "\n"); }

exit($fail ? 1 : 0);

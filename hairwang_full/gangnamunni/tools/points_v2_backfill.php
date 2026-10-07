<?php
/**
 * 포인트 제도 v2 — 적립 단위(lot) 재구성 도구 (#포인트제도 v2)
 *   선행: sql/points_v2.sql 적용
 *   실행: php gangnamunni/tools/points_v2_backfill.php [--member=<mb_no>] [--dry-run]
 *
 * 원장(gangnamunni_points) 전체를 회원별로 다시 읽어 lot/사용내역을 만든다(멱등, 재실행 안전).
 * 끝나면 회원별로 SUM(lot.remaining) == max(0, 잔액) 을 검증하고, 전원 일치 시 lots_ready=1 로 설정한다.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
require_once dirname(__DIR__) . '/includes/points_ledger.php';

$opts = getopt('', ['member::', 'dry-run']);
$connect = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
$connect->set_charset('utf8mb4');
$connect->query("SET time_zone='+09:00'");
if (!points_v2_schema_ready($connect)) { fwrite(STDERR, "Run sql/points_v2.sql first.\n"); exit(1); }

$members = [];
if (!empty($opts['member'])) $members[] = (int)$opts['member'];
else {
    $r = $connect->query("SELECT DISTINCT member_id FROM gangnamunni_points ORDER BY member_id");
    while ($row = $r->fetch_row()) $members[] = (int)$row[0];
}
$dry = isset($opts['dry-run']);
$bad = 0;
foreach ($members as $m) {
    if (!$dry && !points_lots_rebuild_member($connect, $m)) { fwrite(STDERR, "member $m: lock failed\n"); $bad++; continue; }
    $bal = points_balance($connect, $m);
    $sum = (int)($connect->query("SELECT COALESCE(SUM(remaining),0) FROM gangnamunni_point_lots WHERE member_id = " . (int)$m)->fetch_row()[0] ?? 0);
    if ($sum !== max(0, $bal)) { $bad++; echo "member $m: balance $bal, lots $sum  MISMATCH\n"; }
}
echo count($members) . " members, mismatches: $bad\n";
if (!$dry && empty($opts['member']) && $bad === 0) {
    points_save_settings($connect, ['lots_ready' => '1']);
    echo "lots_ready=1\n";
}
exit($bad ? 1 : 0);

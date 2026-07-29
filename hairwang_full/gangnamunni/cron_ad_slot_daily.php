<?php
/**
 * 광고 슬롯 일일 과금 크론 작업
 *
 * 매일 자정 이후 실행 (권장: 00:10, cron_cpv_daily.php 다음에)
 * crontab 예시: 10 0 * * * /usr/bin/php /var/www/html/.../gangnamunni/cron_ad_slot_daily.php >> /var/log/ad_slot_cron.log 2>&1
 *
 * 수행 작업:
 * 전일 활성 상태였던 슬롯 배정(ad_slot_bookings)마다
 *   일할 단가 = monthly_price / 전일이 속한 달의 일수
 * 만큼 병원 CPV 지갑(gangnamunni_cpv_wallets)에서 차감.
 * 잔액이 부족하면 배정을 자동 종료하고 관리자에게 알림.
 * ad_slot_charges 테이블로 중복 과금을 방지(멱등).
 */

// CLI 전용 실행 확인
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI 전용입니다.';
    exit(1);
}

$start_time = microtime(true);
$run_date = date('Y-m-d H:i:s');

echo "========================================\n";
echo "[광고 슬롯 일일 크론] 시작: {$run_date}\n";
echo "========================================\n\n";

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(
    G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
);
if (!$connect) {
    echo "[ERROR] DB 연결 실패: " . mysqli_connect_error() . "\n";
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

require_once __DIR__ . '/includes/cpv_engine.php';

// 처리 대상 날짜 (어제) — 하루가 완전히 끝난 뒤 과금
$charge_date = date('Y-m-d', strtotime('-1 day'));
$days_in_month = (int) date('t', strtotime($charge_date));
echo "[INFO] 처리 대상 날짜: {$charge_date} (해당 월 일수: {$days_in_month}일)\n\n";


echo "--- 전일 활성 슬롯 배정 조회 ---\n";

$bookings = [];
$b_stmt = mysqli_prepare($connect, "
    SELECT b.id AS booking_id, b.hospital_id, b.advertiser_id, p.monthly_price,
           h.name AS hospital_name
    FROM gangnamunni_ad_slot_bookings b
    INNER JOIN gangnamunni_ad_slot_products p ON b.product_id = p.id
    INNER JOIN gangnamunni_hospitals h ON h.id = b.hospital_id
    WHERE b.status = 'active'
      AND p.billing_model = 'monthly'
      AND b.start_date <= ?
      AND (b.end_date IS NULL OR b.end_date >= ?)
");
if ($b_stmt) {
    mysqli_stmt_bind_param($b_stmt, 'ss', $charge_date, $charge_date);
    mysqli_stmt_execute($b_stmt);
    $b_result = mysqli_stmt_get_result($b_stmt);
    while ($row = mysqli_fetch_assoc($b_result)) { $bookings[] = $row; }
    mysqli_stmt_close($b_stmt);
}
echo "[INFO] 대상 배정: " . count($bookings) . "건\n\n";

// ============================================================
// 배정별 일할 과금
// ============================================================
echo "--- 일할 과금 처리 ---\n";

$charged_count = 0;
$skipped_count = 0;
$ended_count = 0;

foreach ($bookings as $bk) {
    $booking_id = (int) $bk['booking_id'];
    $hospital_id = (int) $bk['hospital_id'];
    $hospital_name = $bk['hospital_name'] ?: ('병원 #' . $hospital_id);
    $daily_amount = round($bk['monthly_price'] / $days_in_month, 2);

    // 멱등성: 이미 이 날짜에 과금되었으면 건너뜀
    $dup_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_ad_slot_charges WHERE booking_id = ? AND charge_date = ? LIMIT 1");
    mysqli_stmt_bind_param($dup_stmt, 'is', $booking_id, $charge_date);
    mysqli_stmt_execute($dup_stmt);
    $dup_result = mysqli_stmt_get_result($dup_stmt);
    $already_charged = mysqli_fetch_assoc($dup_result) !== null;
    mysqli_stmt_close($dup_stmt);

    if ($already_charged) {
        echo "  [SKIP] booking#{$booking_id} ({$hospital_name}): 이미 {$charge_date} 과금됨\n";
        $skipped_count++;
        continue;
    }

    $wallet = getCpvWallet($connect, $hospital_id);
    $balance = $wallet ? (float) $wallet['balance'] : 0.0;

    if (!$wallet || $balance < $daily_amount) {
        // 잔액 부족 또는 지갑 없음 — 배정 자동 종료 + 관리자 알림
        $end_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_ad_slot_bookings SET status='ended', end_date=? WHERE id=? AND status='active'");
        mysqli_stmt_bind_param($end_stmt, 'si', $charge_date, $booking_id);
        mysqli_stmt_execute($end_stmt);
        mysqli_stmt_close($end_stmt);
        $advertiser_id = (int)($bk['advertiser_id'] ?? 0);
        if ($advertiser_id > 0) {
            $deactivate = mysqli_prepare($connect,
                "UPDATE gangnamunni_ads a SET is_active=0
                 WHERE a.advertiser_id=?
                   AND NOT EXISTS (
                       SELECT 1 FROM gangnamunni_ad_slot_bookings active_b
                       WHERE active_b.advertiser_id=a.advertiser_id
                         AND active_b.status='active'
                         AND active_b.start_date<=CURDATE()
                         AND (active_b.end_date IS NULL OR active_b.end_date>=CURDATE())
                   )");
            mysqli_stmt_bind_param($deactivate, 'i', $advertiser_id);
            mysqli_stmt_execute($deactivate);
            mysqli_stmt_close($deactivate);
        }

        $noti_title = "[광고 슬롯] {$hospital_name} 배정 자동 종료";
        $noti_message = "{$hospital_name}의 광고 슬롯 배정이 지갑 잔액 부족(" . number_format($balance) . "원)으로 자동 종료되었습니다. 필요 금액: " . number_format($daily_amount) . "원/일.";
        $noti_link = "admin_ad_slots.php";
        $noti_stmt = mysqli_prepare($connect, "
            INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon, is_read, created_at)
            VALUES (NULL, 'ad_slot', ?, ?, ?, 'bi-exclamation-triangle', 0, NOW())
        ");
        if ($noti_stmt) {
            mysqli_stmt_bind_param($noti_stmt, 'sss', $noti_title, $noti_message, $noti_link);
            mysqli_stmt_execute($noti_stmt);
            mysqli_stmt_close($noti_stmt);
        }

        echo "  [END] booking#{$booking_id} ({$hospital_name}): 잔액 부족(" . number_format($balance) . "원 < " . number_format($daily_amount) . "원) → 배정 종료\n";
        $ended_count++;
        continue;
    }

    // 차감
    $ok = deductCpvWallet($connect, $hospital_id, $daily_amount);
    if (!$ok) {
        echo "  [ERROR] booking#{$booking_id} ({$hospital_name}): 차감 실패 (동시성 충돌 가능)\n";
        continue;
    }
    $new_wallet = getCpvWallet($connect, $hospital_id);
    $new_balance = $new_wallet ? (float) $new_wallet['balance'] : 0.0;

    recordCpvTransaction(
        $connect, $hospital_id, 'deduct', $daily_amount, $new_balance,
        "광고 슬롯 일할 과금 (booking#{$booking_id}, {$charge_date})"
    );

    $log_stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_ad_slot_charges (booking_id, charge_date, amount) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($log_stmt, 'isd', $booking_id, $charge_date, $daily_amount);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    echo "  [OK] booking#{$booking_id} ({$hospital_name}): " . number_format($daily_amount) . "원 차감 (잔액: " . number_format($new_balance) . "원)\n";
    $charged_count++;
}

echo "\n[INFO] 과금 완료: {$charged_count}건, 스킵: {$skipped_count}건, 자동 종료: {$ended_count}건\n\n";

// ============================================================
// 완료 리포트
// ============================================================
$elapsed = round(microtime(true) - $start_time, 3);
$end_time = date('Y-m-d H:i:s');

echo "========================================\n";
echo "[광고 슬롯 일일 크론] 완료: {$end_time}\n";
echo "소요 시간: {$elapsed}초\n";
echo "========================================\n";

mysqli_close($connect);
exit(0);

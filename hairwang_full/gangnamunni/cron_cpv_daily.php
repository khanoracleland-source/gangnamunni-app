<?php
/**
 * CPV 일일 크론 작업
 *
 * 매일 자정 이후 실행 (권장: 00:05)
 * crontab 예시: 5 0 * * * /usr/bin/php /var/www/html/.../gangnamunni/cron_cpv_daily.php >> /var/log/cpv_cron.log 2>&1
 *
 * 수행 작업:
 * 1. 전일 일일 통계 집계 (cpv_logs → cpv_daily_stats)
 * 2. 잔액 부족 병원 알림 발송
 * 3. 오래된 로그 정리 (보관일수 기준)
 * 4. 오래된 거래 내역 정리
 * 5. 일일 일시정지 플래그 리셋
 */

// CLI 전용 실행 확인
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI 전용입니다.';
    exit(1);
}

// 실행 시작 타임스탬프
$start_time = microtime(true);
$run_date = date('Y-m-d H:i:s');

echo "========================================\n";
echo "[CPV 일일 크론] 시작: {$run_date}\n";
echo "========================================\n\n";

// DB 연결
// 크론에서는 __DIR__ 기준 상대경로
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

// 헬퍼 로드
require_once __DIR__ . '/includes/cpv_helper.php';

// 처리 대상 날짜 (어제)
$yesterday = date('Y-m-d', strtotime('-1 day'));
echo "[INFO] 처리 대상 날짜: {$yesterday}\n\n";


// ============================================================
// 1. 전일 일일 통계 집계
// ============================================================
echo "--- [1/5] 일일 통계 집계 ---\n";

$agg_query = "
    INSERT INTO gangnamunni_cpv_daily_stats
        (hospital_id, stat_date, total_views, charged_views, total_amount, avg_rate, unique_visitors)
    SELECT
        hospital_id,
        DATE(created_at) as stat_date,
        COUNT(*) as total_views,
        SUM(CASE WHEN is_charged = 1 THEN 1 ELSE 0 END) as charged_views,
        COALESCE(SUM(CASE WHEN is_charged = 1 THEN cpv_rate ELSE 0 END), 0) as total_amount,
        COALESCE(AVG(CASE WHEN is_charged = 1 THEN cpv_rate ELSE NULL END), 0) as avg_rate,
        COUNT(DISTINCT visitor_ip) as unique_visitors
    FROM gangnamunni_cpv_logs
    WHERE DATE(created_at) = ?
    GROUP BY hospital_id
    ON DUPLICATE KEY UPDATE
        total_views = VALUES(total_views),
        charged_views = VALUES(charged_views),
        total_amount = VALUES(total_amount),
        avg_rate = VALUES(avg_rate),
        unique_visitors = VALUES(unique_visitors)
";

$stmt = mysqli_prepare($connect, $agg_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $yesterday);
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        echo "[OK] 통계 집계 완료: {$affected}건 처리\n";
    } else {
        echo "[ERROR] 통계 집계 실패: " . mysqli_stmt_error($stmt) . "\n";
    }
    mysqli_stmt_close($stmt);
} else {
    echo "[ERROR] 통계 쿼리 준비 실패: " . mysqli_error($connect) . "\n";
}
echo "\n";


// ============================================================
// 2. 잔액 부족 병원 알림
// ============================================================
echo "--- [2/5] 잔액 부족 알림 ---\n";

$low_balance_threshold = (int) getCpvSetting('low_balance_alert_threshold', 50000);
$alert_enabled = getCpvSetting('low_balance_alert_enabled', '1') === '1';

if ($alert_enabled && $low_balance_threshold > 0) {
    $low_query = "
        SELECT w.hospital_id, w.balance,
               (SELECT name FROM gangnamunni_hospitals WHERE id = w.hospital_id LIMIT 1) as hospital_name
        FROM gangnamunni_cpv_wallets w
        WHERE w.is_active = 1 AND w.is_paused = 0
          AND w.balance > 0 AND w.balance <= ?
    ";
    $stmt = mysqli_prepare($connect, $low_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $low_balance_threshold);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $alert_count = 0;

        while ($row = mysqli_fetch_assoc($result)) {
            $hospital_name = $row['hospital_name'] ?: ('병원 #' . $row['hospital_id']);
            $balance_formatted = number_format($row['balance']);

            // 관리자 알림 테이블에 기록
            $noti_stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_admin_notifications
                 (type, title, message, link, is_read, created_at)
                 VALUES ('system', ?, ?, ?, 0, NOW())"
            );
            if ($noti_stmt) {
                $noti_title = "[CPV] {$hospital_name} 잔액 부족";
                $noti_message = "{$hospital_name}의 CPV 지갑 잔액이 {$balance_formatted}원입니다. 충전이 필요합니다.";
                $noti_link = "admin_cpv_settings.php?tab=wallets&hospital_id=" . $row['hospital_id'];

                mysqli_stmt_bind_param($noti_stmt, 'sss', $noti_title, $noti_message, $noti_link);
                mysqli_stmt_execute($noti_stmt);
                mysqli_stmt_close($noti_stmt);
                $alert_count++;
            }

            echo "  [ALERT] {$hospital_name}: 잔액 {$balance_formatted}원\n";
        }
        mysqli_stmt_close($stmt);
        echo "[OK] 잔액 부족 알림 발송: {$alert_count}건\n";
    }
} else {
    echo "[SKIP] 잔액 부족 알림 비활성화\n";
}
echo "\n";


// ============================================================
// 3. 오래된 조회 로그 정리
// ============================================================
echo "--- [3/5] 오래된 조회 로그 정리 ---\n";

$log_retention_days = (int) getCpvSetting('cpv_log_retention_days', 90);
if ($log_retention_days > 0) {
    $stmt = mysqli_prepare($connect,
        "DELETE FROM gangnamunni_cpv_logs WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $log_retention_days);
        if (mysqli_stmt_execute($stmt)) {
            $deleted = mysqli_stmt_affected_rows($stmt);
            echo "[OK] {$log_retention_days}일 이전 로그 삭제: {$deleted}건\n";
        } else {
            echo "[ERROR] 로그 삭제 실패: " . mysqli_stmt_error($stmt) . "\n";
        }
        mysqli_stmt_close($stmt);
    }
} else {
    echo "[SKIP] 로그 보관일수 미설정 (무제한)\n";
}
echo "\n";


// ============================================================
// 4. 오래된 거래 내역 정리
// ============================================================
echo "--- [4/5] 오래된 거래 내역 정리 ---\n";

$tx_retention_days = (int) getCpvSetting('transaction_retention_days', 365);
if ($tx_retention_days > 0) {
    $stmt = mysqli_prepare($connect,
        "DELETE FROM gangnamunni_cpv_transactions WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $tx_retention_days);
        if (mysqli_stmt_execute($stmt)) {
            $deleted = mysqli_stmt_affected_rows($stmt);
            echo "[OK] {$tx_retention_days}일 이전 거래 내역 삭제: {$deleted}건\n";
        } else {
            echo "[ERROR] 거래 내역 삭제 실패: " . mysqli_stmt_error($stmt) . "\n";
        }
        mysqli_stmt_close($stmt);
    }
} else {
    echo "[SKIP] 거래 내역 보관일수 미설정 (무제한)\n";
}
echo "\n";


// ============================================================
// 5. 일일 일시정지 플래그 리셋
// ============================================================
echo "--- [5/5] 일일 일시정지 리셋 ---\n";

// daily_cap으로 자동 일시정지된 지갑은 다음 날 해제하지 않음 (is_paused는 수동/잔액부족 전용)
// 대신, daily_cap 스킵 로직은 당일 기준이므로 별도 리셋 불필요
// 여기서는 잔액이 충전되었는데 아직 paused인 지갑을 자동 해제

$stmt = mysqli_prepare($connect,
    "UPDATE gangnamunni_cpv_wallets
     SET is_paused = 0, updated_at = NOW()
     WHERE is_paused = 1 AND is_active = 1 AND balance > 0"
);
if ($stmt) {
    if (mysqli_stmt_execute($stmt)) {
        $reset_count = mysqli_stmt_affected_rows($stmt);
        echo "[OK] 잔액 충전 후 일시정지 해제: {$reset_count}건\n";
    } else {
        echo "[ERROR] 일시정지 리셋 실패: " . mysqli_stmt_error($stmt) . "\n";
    }
    mysqli_stmt_close($stmt);
}
echo "\n";


// ============================================================
// 완료 리포트
// ============================================================
$elapsed = round(microtime(true) - $start_time, 3);
$end_time = date('Y-m-d H:i:s');

echo "========================================\n";
echo "[CPV 일일 크론] 완료: {$end_time}\n";
echo "소요 시간: {$elapsed}초\n";
echo "========================================\n";

mysqli_close($connect);
exit(0);

<?php
/**
 * Push token cleanup cron.
 *
 * Suggested crontab:
 * 25 0 * * * /usr/bin/php /var/www/html/.../gangnamunni/cron_push_token_cleanup.php >> /var/log/push_token_cleanup.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI 전용입니다.';
    exit(1);
}

$startedAt = microtime(true);
echo "========================================\n";
echo "[Push token cleanup] 시작: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(
    G5_MYSQL_HOST,
    G5_MYSQL_USER,
    G5_MYSQL_PASSWORD,
    G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
);
if (!$connect) {
    echo "[ERROR] DB 연결 실패: " . mysqli_connect_error() . "\n";
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

$tableCheck = mysqli_query($connect, "SHOW TABLES LIKE 'g5_push_tokens'");
if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
    echo "[SKIP] g5_push_tokens 테이블이 없습니다.\n";
    exit(0);
}

function cleanup_exec($connect, $label, $sql) {
    if (!mysqli_query($connect, $sql)) {
        echo "[ERROR] {$label}: " . mysqli_error($connect) . "\n";
        return 0;
    }
    $affected = mysqli_affected_rows($connect);
    echo "[OK] {$label}: {$affected}건\n";
    return max(0, $affected);
}

function cleanup_device_id($deviceInfo) {
    if (!is_string($deviceInfo) || $deviceInfo === '') return '';
    $decoded = json_decode($deviceInfo, true);
    if (is_array($decoded) && isset($decoded['device_id'])) {
        return trim((string)$decoded['device_id']);
    }
    if (preg_match('/"device_id"\s*:\s*"([^"]+)"/', $deviceInfo, $matches)) {
        return trim((string)$matches[1]);
    }
    return '';
}

$fixedPlatform = cleanup_exec(
    $connect,
    'iPhone/iPad 토큰 platform=ios 보정',
    "UPDATE g5_push_tokens
     SET pt_platform = 'ios'
     WHERE pt_use = 1
       AND pt_platform = 'android'
       AND (pt_device_info LIKE '%iPhone%' OR pt_device_info LIKE '%iPad%' OR pt_device_info LIKE '%iPod%')"
);

$deactivatedEmptyMember = cleanup_exec(
    $connect,
    '오래된 mb_id 빈 active token 비활성화',
    "UPDATE g5_push_tokens
     SET pt_use = 0
     WHERE pt_use = 1
       AND (mb_id IS NULL OR mb_id = '')
       AND pt_datetime < DATE_SUB(NOW(), INTERVAL 14 DAY)"
);

$deactivatedDuplicates = 0;
$tokensByDevice = [];
$tokenResult = mysqli_query(
    $connect,
    "SELECT pt_id, mb_id, pt_platform, pt_device_info, pt_datetime
     FROM g5_push_tokens
     WHERE pt_use = 1
       AND mb_id IS NOT NULL
       AND mb_id <> ''
     ORDER BY pt_datetime DESC, pt_id DESC"
);
if ($tokenResult) {
    while ($row = mysqli_fetch_assoc($tokenResult)) {
        $deviceId = cleanup_device_id($row['pt_device_info'] ?? '');
        if ($deviceId === '') continue;
        $key = $row['mb_id'] . "\n" . $row['pt_platform'] . "\n" . $deviceId;
        $tokensByDevice[$key][] = (int)$row['pt_id'];
    }
}

$deactivateStmt = mysqli_prepare($connect, "UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_id = ?");
foreach ($tokensByDevice as $ids) {
    if (count($ids) <= 1) continue;
    // The query is ordered newest first, and pt_id breaks timestamp ties.
    array_shift($ids);
    foreach ($ids as $ptId) {
        if (!$deactivateStmt) continue;
        mysqli_stmt_bind_param($deactivateStmt, 'i', $ptId);
        if (mysqli_stmt_execute($deactivateStmt)) {
            $deactivatedDuplicates += max(0, mysqli_stmt_affected_rows($deactivateStmt));
        }
    }
}
if ($deactivateStmt) mysqli_stmt_close($deactivateStmt);
echo "[OK] 동일 device_id 중복 active token 비활성화: {$deactivatedDuplicates}건\n";

$deletedInactive = cleanup_exec(
    $connect,
    '오래된 inactive token 삭제',
    "DELETE FROM g5_push_tokens
     WHERE pt_use = 0
       AND pt_datetime < DATE_SUB(NOW(), INTERVAL 90 DAY)"
);

$summary = mysqli_query(
    $connect,
    "SELECT pt_platform, pt_use, COUNT(*) AS cnt
     FROM g5_push_tokens
     GROUP BY pt_platform, pt_use
     ORDER BY pt_platform, pt_use"
);
if ($summary) {
    echo "\n--- 현재 token 요약 ---\n";
    while ($row = mysqli_fetch_assoc($summary)) {
        echo "{$row['pt_platform']} / use={$row['pt_use']}: {$row['cnt']}\n";
    }
}

$elapsed = round(microtime(true) - $startedAt, 3);
echo "\n[완료] platform 보정 {$fixedPlatform}, 빈 회원 비활성 {$deactivatedEmptyMember}, 중복 비활성 {$deactivatedDuplicates}, inactive 삭제 {$deletedInactive}\n";
echo "소요 시간: {$elapsed}초\n";
echo "========================================\n";

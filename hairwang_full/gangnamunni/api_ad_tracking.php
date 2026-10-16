<?php
/**
 * 광고 노출/클릭 트래킹 API
 * POST /gangnamunni/api_ad_tracking.php
 *
 * Parameters:
 *   action: "impression" | "click"
 *   ad_id: int
 *   page_url: string (optional)
 *   target_url: string (optional, for clicks)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$action = $_POST['action'] ?? '';
$ad_id = intval($_POST['ad_id'] ?? 0);
// 모든 값은 prepared statement 로 바인딩한다 (문자열 보간 금지).
// 예전에는 session_start() 없이 $_COOKIE['PHPSESSID'] 원문을 쿼리에 그대로 넣어
// 인증 없이 SQL 인젝션이 가능했다.
$page_url = mb_substr((string)($_POST['page_url'] ?? ''), 0, 1000);
$target_url = mb_substr((string)($_POST['target_url'] ?? ''), 0, 1000);
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$sid_raw = (string)($_COOKIE[session_name()] ?? '');
// PHP 세션 ID 형식(영숫자·,-)만 기록, 그 외는 버린다
$sid = preg_match('/^[A-Za-z0-9,-]{1,128}$/', $sid_raw) ? $sid_raw : '';

if ($ad_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ad_id']);
    exit;
}

// 봇 필터링
$bot_patterns = ['bot', 'crawl', 'spider', 'slurp', 'Googlebot', 'Bingbot', 'Yahoo'];
foreach ($bot_patterns as $bot) {
    if (stripos($ua, $bot) !== false) {
        echo json_encode(['success' => true, 'filtered' => 'bot']);
        exit;
    }
}

function ad_tracking_bump($connect, $column, $ad_id) {
    // $column 은 아래 두 값 중 하나로만 호출된다(화이트리스트)
    if (!in_array($column, ['impression_count', 'click_count'], true)) return;
    $st = mysqli_prepare($connect, "UPDATE gangnamunni_ads SET {$column} = {$column} + 1 WHERE id = ?");
    if ($st) { mysqli_stmt_bind_param($st, 'i', $ad_id); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
}

if ($action === 'impression') {
    // 같은 IP에서 같은 광고를 5분 내 중복 노출 카운트 방지
    $dup = false;
    $check = mysqli_prepare($connect,
        "SELECT id FROM gangnamunni_ad_impressions
         WHERE ad_id = ? AND ip_address = ?
         AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1"
    );
    if ($check) {
        mysqli_stmt_bind_param($check, 'is', $ad_id, $ip);
        mysqli_stmt_execute($check);
        $dup = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);
    }
    if ($dup) {
        echo json_encode(['success' => true, 'duplicate' => true]);
        exit;
    }

    $ok = false;
    $st = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_ad_impressions (ad_id, page_url, ip_address, user_agent, session_id)
         VALUES (?, ?, ?, ?, ?)");
    if ($st) {
        mysqli_stmt_bind_param($st, 'issss', $ad_id, $page_url, $ip, $ua, $sid);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
    if ($ok) {
        // 캐시 카운터 업데이트
        ad_tracking_bump($connect, 'impression_count', $ad_id);
        echo json_encode(['success' => true, 'action' => 'impression', 'ad_id' => $ad_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
} elseif ($action === 'click') {
    $ok = false;
    $st = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_ad_clicks (ad_id, page_url, target_url, ip_address, user_agent, session_id)
         VALUES (?, ?, ?, ?, ?, ?)");
    if ($st) {
        mysqli_stmt_bind_param($st, 'isssss', $ad_id, $page_url, $target_url, $ip, $ua, $sid);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
    if ($ok) {
        ad_tracking_bump($connect, 'click_count', $ad_id);
        echo json_encode(['success' => true, 'action' => 'click', 'ad_id' => $ad_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action. Use impression or click']);
}

mysqli_close($connect);

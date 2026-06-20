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
$page_url = mysqli_real_escape_string($connect, $_POST['page_url'] ?? '');
$target_url = mysqli_real_escape_string($connect, $_POST['target_url'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = mysqli_real_escape_string($connect, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));
$sid = session_id() ?: (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '');

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

if ($action === 'impression') {
    // 같은 세션/IP에서 같은 광고를 5분 내 중복 노출 카운트 방지
    $check = mysqli_query($connect,
        "SELECT id FROM gangnamunni_ad_impressions
         WHERE ad_id = {$ad_id} AND ip_address = '{$ip}'
         AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1"
    );
    if ($check && mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => true, 'duplicate' => true]);
        exit;
    }

    $sql = "INSERT INTO gangnamunni_ad_impressions (ad_id, page_url, ip_address, user_agent, session_id)
            VALUES ({$ad_id}, '{$page_url}', '{$ip}', '{$ua}', '{$sid}')";
    if (mysqli_query($connect, $sql)) {
        // 캐시 카운터 업데이트
        mysqli_query($connect, "UPDATE gangnamunni_ads SET impression_count = impression_count + 1 WHERE id = {$ad_id}");
        echo json_encode(['success' => true, 'action' => 'impression', 'ad_id' => $ad_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
} elseif ($action === 'click') {
    $sql = "INSERT INTO gangnamunni_ad_clicks (ad_id, page_url, target_url, ip_address, user_agent, session_id)
            VALUES ({$ad_id}, '{$page_url}', '{$target_url}', '{$ip}', '{$ua}', '{$sid}')";
    if (mysqli_query($connect, $sql)) {
        mysqli_query($connect, "UPDATE gangnamunni_ads SET click_count = click_count + 1 WHERE id = {$ad_id}");
        echo json_encode(['success' => true, 'action' => 'click', 'ad_id' => $ad_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action. Use impression or click']);
}

mysqli_close($connect);

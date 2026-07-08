<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/ad_slot_performance.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'reason' => 'post_only']);
    exit;
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (! $connect) {
    http_response_code(503);
    echo json_encode(['success' => false, 'reason' => 'database_unavailable']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (adSlotIsBot($userAgent)) {
    echo json_encode(['success' => true, 'charged' => false, 'reason' => 'bot_filtered']);
    mysqli_close($connect);
    exit;
}

$eventType = $_POST['action'] ?? '';
if (! in_array($eventType, ['impression', 'click'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'reason' => 'invalid_action']);
    mysqli_close($connect);
    exit;
}

$result = adSlotProcessEvent($connect, [
    'event_type' => $eventType,
    'booking_id' => (int) ($_POST['booking_id'] ?? 0),
    'keyword' => $_POST['keyword'] ?? '',
    'page_url' => $_POST['page_url'] ?? '',
    'target_url' => $_POST['target_url'] ?? '',
    'user_agent' => $userAgent,
]);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
mysqli_close($connect);

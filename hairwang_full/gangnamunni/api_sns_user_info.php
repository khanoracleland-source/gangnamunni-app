<?php
/**
 * SNS 로그인 사용자 정보 API
 * 앱에서 SNS 로그인 콜백 후 사용자 정보 조회
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$mb_id = isset($_GET['mb_id']) ? trim($_GET['mb_id']) : '';

if (empty($mb_id)) {
    echo json_encode(['error' => 'mb_id required']);
    exit;
}

// g5_member에서 사용자 정보 조회
$stmt = mysqli_prepare($connect, "SELECT mb_id, mb_name, mb_nick, mb_email, mb_hp FROM g5_member WHERE mb_id = ?");
mysqli_stmt_bind_param($stmt, 's', $mb_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$member) {
    echo json_encode(['error' => 'user not found']);
    exit;
}

echo json_encode([
    'mb_id' => $member['mb_id'],
    'name' => $member['mb_name'] ?: $member['mb_nick'],
    'email' => $member['mb_email'],
    'phone' => $member['mb_hp'],
]);

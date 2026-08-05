<?php
include_once('./_common.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'POST method required']));
}

// POST 데이터 받기
$input = json_decode(file_get_contents('php://input'), true);

$token = isset($input['token']) ? trim($input['token']) : '';
$platform = isset($input['platform']) ? trim($input['platform']) : '';

// 로그인 체크 - 비회원도 저장하려면 이 부분 주석처리
if(!$member['mb_id']) {
    die(json_encode(['success' => false, 'message' => '로그인 필요', 'debug' => 'no_member']));
}

// 토큰 체크
if(!$token) {
    die(json_encode(['success' => false, 'message' => '토큰 없음']));
}

if (strlen($token) > 500 || !in_array($platform, ['android', 'ios', 'web'], true)) {
    http_response_code(422);
    die(json_encode(['success' => false, 'message' => 'Invalid token or platform']));
}

// DB에 저장
$mb_id = $member['mb_id'];
$escaped_mb_id = sql_real_escape_string($mb_id);
$escaped_token = sql_real_escape_string($token);
$escaped_platform = sql_real_escape_string($platform);
$sql = "INSERT INTO g5_push_tokens 
        SET mb_id = '{$escaped_mb_id}',
            pt_token = '{$escaped_token}',
            pt_platform = '{$escaped_platform}',
            pt_datetime = NOW()
        ON DUPLICATE KEY UPDATE
            mb_id = '{$escaped_mb_id}',
            pt_platform = '{$escaped_platform}',
            pt_use = 1,
            pt_update_datetime = NOW()";

$result = sql_query($sql, false);

if (!$result) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Token could not be saved']));
}

echo json_encode([
    'success' => true, 
    'message' => '토큰 저장 완료',
    'mb_id' => $mb_id,
    'token' => substr($token, 0, 20) . '...'
]);
?>

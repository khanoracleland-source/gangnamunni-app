<?php
include_once('./_common.php');

header('Content-Type: application/json');

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

// DB에 저장
$mb_id = $member['mb_id'];
$sql = "INSERT INTO g5_push_tokens 
        SET mb_id = '{$mb_id}',
            pt_token = '{$token}',
            pt_platform = '{$platform}',
            pt_datetime = NOW()
        ON DUPLICATE KEY UPDATE
            mb_id = '{$mb_id}',
            pt_datetime = NOW()";

$result = sql_query($sql);

echo json_encode([
    'success' => true, 
    'message' => '토큰 저장 완료',
    'mb_id' => $mb_id,
    'token' => substr($token, 0, 20) . '...'
]);
?>
<?php
include_once('./_common.php');

header('Content-Type: application/json');

// 로그인 체크
if (!$member['mb_id']) {
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit;
}

// POST 데이터 검증
$type = isset($_POST['type']) ? $_POST['type'] : '';
$value = isset($_POST['value']) ? (int)$_POST['value'] : 0;

// 허용된 타입 목록
$allowed_types = ['all_notification', 'comment', 'like', 'mention', 'follow', 'marketing'];

if (!in_array($type, $allowed_types)) {
    echo json_encode(['error' => '잘못된 요청입니다.']);
    exit;
}

// 현재 사용자의 설정이 있는지 확인
$sql = "SELECT COUNT(*) as cnt FROM g5_notification_settings WHERE mb_id = '{$member['mb_id']}'";
$row = sql_fetch($sql);

if ($row['cnt'] == 0) {
    // 설정이 없으면 새로 생성
    $sql = "INSERT INTO g5_notification_settings 
            SET mb_id = '{$member['mb_id']}',
                ns_all_notification = '1',
                ns_comment = '1',
                ns_like = '1',
                ns_mention = '1',
                ns_follow = '1',
                ns_marketing = '1',
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'";
    sql_query($sql);
}

// 전체 알림이 꺼지면 모든 개별 알림도 끄기
if ($type == 'all_notification' && $value == 0) {
    $sql = "UPDATE g5_notification_settings 
            SET ns_all_notification = 0,
                ns_comment = 0,
                ns_like = 0,
                ns_mention = 0,
                ns_follow = 0,
                ns_marketing = 0,
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'
            WHERE mb_id = '{$member['mb_id']}'";
} 
// 전체 알림이 켜지면 모든 개별 알림도 켜기
else if ($type == 'all_notification' && $value == 1) {
    $sql = "UPDATE g5_notification_settings 
            SET ns_all_notification = 1,
                ns_comment = 1,
                ns_like = 1,
                ns_mention = 1,
                ns_follow = 1,
                ns_marketing = 1,
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'
            WHERE mb_id = '{$member['mb_id']}'";
}
// 개별 알림 설정
else {
    $field = 'ns_' . $type;
    $sql = "UPDATE g5_notification_settings 
            SET {$field} = {$value},
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'
            WHERE mb_id = '{$member['mb_id']}'";
}

$result = sql_query($sql);

if ($result) {
    // 성공 시 현재 설정 상태 반환
    $sql = "SELECT * FROM g5_notification_settings WHERE mb_id = '{$member['mb_id']}'";
    $settings = sql_fetch($sql);
    
    echo json_encode([
        'success' => true,
        'message' => '설정이 저장되었습니다.',
        'settings' => $settings
    ]);
} else {
    echo json_encode(['error' => '설정 저장에 실패했습니다.']);
}
?>
<?php
// /rb/ajax.fcm_token.php
include_once('./_common.php');
header('Content-Type: application/json; charset=UTF-8');

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request method']));
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (empty($member['mb_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Login required']));
}

$mb_id = $member['mb_id'];

if (!$token) {
    die(json_encode(['error' => 'Token is required']));
}

// 토큰 유효성 검사 (간단한 길이 체크)
if (strlen($token) < 20 || strlen($token) > 500) {
    die(json_encode(['error' => 'Invalid token format']));
}

// 현재 접속자 정보
$device_info = array(
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    'created_at' => G5_TIME_YMDHIS
);

// FCM 토큰 테이블이 없으면 생성
$sql = "CREATE TABLE IF NOT EXISTS `g5_fcm_tokens` (
    `ft_id` int(11) NOT NULL AUTO_INCREMENT,
    `mb_id` varchar(20) NOT NULL DEFAULT '',
    `ft_token` varchar(500) NOT NULL DEFAULT '',
    `ft_device_info` text NOT NULL,
    `ft_created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `ft_updated_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `ft_is_active` tinyint(4) NOT NULL DEFAULT 1,
    PRIMARY KEY (`ft_id`),
    UNIQUE KEY `ft_token` (`ft_token`),
    KEY `mb_id` (`mb_id`),
    KEY `ft_is_active` (`ft_is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
sql_query($sql, false);

// 이미 등록된 토큰인지 확인
$sql = "SELECT ft_id FROM g5_fcm_tokens WHERE ft_token = '".sql_real_escape_string($token)."'";
$row = sql_fetch($sql);

if ($row) {
    // 기존 토큰 업데이트
    $sql = "UPDATE g5_fcm_tokens SET 
                mb_id = '".sql_real_escape_string($mb_id)."',
                ft_device_info = '".sql_real_escape_string(json_encode($device_info))."',
                ft_updated_at = '".G5_TIME_YMDHIS."',
                ft_is_active = 1
            WHERE ft_token = '".sql_real_escape_string($token)."'";
    sql_query($sql);
    
    echo json_encode(['success' => true, 'message' => 'Token updated']);
} else {
    // 새 토큰 등록
    $sql = "INSERT INTO g5_fcm_tokens SET 
                mb_id = '".sql_real_escape_string($mb_id)."',
                ft_token = '".sql_real_escape_string($token)."',
                ft_device_info = '".sql_real_escape_string(json_encode($device_info))."',
                ft_created_at = '".G5_TIME_YMDHIS."',
                ft_updated_at = '".G5_TIME_YMDHIS."',
                ft_is_active = 1";
    sql_query($sql);
    
    echo json_encode(['success' => true, 'message' => 'Token registered']);
}

// 회원별 토큰 수 제한 (최대 5개)
if ($mb_id) {
    $sql = "SELECT ft_id FROM g5_fcm_tokens 
            WHERE mb_id = '".sql_real_escape_string($mb_id)."' 
            ORDER BY ft_updated_at DESC 
            LIMIT 5, 100";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        // 5개를 초과하는 오래된 토큰 삭제
        sql_query("DELETE FROM g5_fcm_tokens WHERE ft_id = '{$row['ft_id']}'");
    }
}

// 30일 이상 업데이트되지 않은 토큰 비활성화
$inactive_date = date('Y-m-d H:i:s', strtotime('-30 days'));
sql_query("UPDATE g5_fcm_tokens SET ft_is_active = 0 WHERE ft_updated_at < '{$inactive_date}'");
?>

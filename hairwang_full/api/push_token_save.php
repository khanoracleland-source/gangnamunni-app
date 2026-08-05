<?php
// CORS 설정 (앱에서 호출 가능하도록)
header('Content-Type: application/json; charset=UTF-8');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once('../common.php');

if (empty($member['mb_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Login required']);
    exit;
}

// POST만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'code' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// JSON 데이터 파싱
$input = json_decode(file_get_contents('php://input'), true);

// 필수 파라미터 체크
$fcm_token = isset($input['fcm_token']) ? trim($input['fcm_token']) : '';
$platform = isset($input['platform']) ? trim($input['platform']) : '';
$device_id = isset($input['device_id']) ? trim($input['device_id']) : '';
$app_version = isset($input['app_version']) ? trim($input['app_version']) : '';

// 토큰 필수 체크
if (!$fcm_token) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code' => 'MISSING_TOKEN',
        'message' => 'FCM token is required'
    ]);
    exit;
}

// 플랫폼 체크 (android/ios)
if (!in_array($platform, ['android', 'ios', 'web'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code' => 'INVALID_PLATFORM',
        'message' => 'Platform must be android, ios or web'
    ]);
    exit;
}

// DB 테이블 생성 (없으면 자동 생성)
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `g5_push_tokens` (
    `pt_id` int(11) NOT NULL AUTO_INCREMENT,
    `mb_id` varchar(20) NOT NULL DEFAULT '',
    `pt_token` varchar(500) NOT NULL,
    `pt_platform` varchar(10) NOT NULL DEFAULT '',
    `pt_device_id` varchar(100) NOT NULL DEFAULT '',
    `pt_app_version` varchar(20) NOT NULL DEFAULT '',
    `pt_device_info` text,
    `pt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `pt_update_datetime` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `pt_use` tinyint(4) NOT NULL DEFAULT '1',
    PRIMARY KEY (`pt_id`),
    UNIQUE KEY `pt_token` (`pt_token`),
    KEY `mb_id` (`mb_id`),
    KEY `pt_device_id` (`pt_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
@sql_query($create_table_sql);

$mb_id = $member['mb_id'];

// device_info 구성
$device_info = json_encode([
    'device_id' => $device_id,
    'platform' => $platform,
    'app_version' => $app_version,
    'registered_at' => date('Y-m-d H:i:s')
]);

try {
    // 기존 토큰 확인
    $sql = "SELECT pt_id, mb_id FROM g5_push_tokens WHERE pt_token = '".sql_real_escape_string($fcm_token)."'";
    $existing = sql_fetch($sql);
    
    if ($existing) {
        // 업데이트
        $sql = "UPDATE g5_push_tokens SET 
                mb_id = '".sql_real_escape_string($mb_id)."',
                pt_platform = '".sql_real_escape_string($platform)."',
                pt_device_id = '".sql_real_escape_string($device_id)."',
                pt_app_version = '".sql_real_escape_string($app_version)."',
                pt_device_info = '".sql_real_escape_string($device_info)."',
                pt_update_datetime = NOW(),
                pt_use = 1
                WHERE pt_token = '".sql_real_escape_string($fcm_token)."'";
        
        sql_query($sql);
        $action = 'updated';
        $token_id = $existing['pt_id'];
        
    } else {
        // 신규 등록
        // 같은 device_id의 이전 토큰 비활성화
        if ($device_id) {
            $sql = "UPDATE g5_push_tokens SET pt_use = 0 
                    WHERE pt_device_id = '".sql_real_escape_string($device_id)."'";
            sql_query($sql);
        }
        
        // 새 토큰 등록
        $sql = "INSERT INTO g5_push_tokens SET 
                mb_id = '".sql_real_escape_string($mb_id)."',
                pt_token = '".sql_real_escape_string($fcm_token)."',
                pt_platform = '".sql_real_escape_string($platform)."',
                pt_device_id = '".sql_real_escape_string($device_id)."',
                pt_app_version = '".sql_real_escape_string($app_version)."',
                pt_device_info = '".sql_real_escape_string($device_info)."',
                pt_datetime = NOW(),
                pt_use = 1";
        
        sql_query($sql);
        $action = 'created';
        $token_id = sql_insert_id();
    }
    
    // 성공 응답
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'code' => 'SUCCESS',
        'message' => 'Token saved successfully',
        'data' => [
            'token_id' => $token_id,
            'action' => $action,
            'user_id' => $mb_id ?: null,
            'platform' => $platform,
            'saved_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // 에러 응답
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'code' => 'SERVER_ERROR',
        'message' => 'Failed to save token'
    ]);
    error_log('Push token save failed: ' . $e->getMessage());
}
?>

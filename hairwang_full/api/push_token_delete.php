<?php
header('Content-Type: application/json; charset=UTF-8');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'code' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$fcm_token = isset($input['fcm_token']) ? trim($input['fcm_token']) : '';
$device_id = isset($input['device_id']) ? trim($input['device_id']) : '';

if (!$fcm_token && !$device_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code' => 'MISSING_PARAMETER',
        'message' => 'Either fcm_token or device_id is required'
    ]);
    exit;
}

// 토큰 비활성화
$where = "";
if ($fcm_token) {
    $where = "pt_token = '".sql_real_escape_string($fcm_token)."'";
} else if ($device_id) {
    $where = "pt_device_id = '".sql_real_escape_string($device_id)."'";
}

$mb_id = sql_real_escape_string($member['mb_id']);
$sql = "UPDATE g5_push_tokens SET pt_use = 0, pt_update_datetime = NOW() WHERE {$where} AND mb_id = '{$mb_id}'";
$result = sql_query($sql, false);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'Token could not be disabled']);
    exit;
}

echo json_encode([
    'success' => true,
    'code' => 'SUCCESS',
    'message' => 'Token deleted successfully'
]);
?>

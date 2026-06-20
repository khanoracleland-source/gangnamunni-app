<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once('../common.php');

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

$sql = "UPDATE g5_push_tokens SET pt_use = 0 WHERE {$where}";
sql_query($sql);

echo json_encode([
    'success' => true,
    'code' => 'SUCCESS',
    'message' => 'Token deleted successfully'
]);
?>
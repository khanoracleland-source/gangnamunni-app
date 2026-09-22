<?php
/**
 * Backward-compatible Android FCM token registration endpoint.
 *
 * The currently published native WebView app posts its Firebase token to this
 * legacy URL. Keep the response deliberately small because older app builds do
 * not parse a structured API envelope consistently.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

require_once dirname(__DIR__) . '/includes/init.php';

if (!isset($connect) || !$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

// MARS/WebView app versions have used different parameter names over time.
$token = trim((string) (
    $input['token']
    ?? $input['fcm_token']
    ?? $input['regid']
    ?? $input['reg_id']
    ?? $input['registration_id']
    ?? $input['user_token']
    ?? ''
));

if ($token === '' || strlen($token) < 32 || strlen($token) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid FCM token']);
    exit;
}

$platform = strtolower(trim((string) ($input['platform'] ?? $input['app'] ?? 'android')));
if (!in_array($platform, ['android', 'ios', 'web'], true)) {
    $platform = 'android';
}

// Never trust a user id supplied by the client when a Gnuboard session exists.
$memberId = '';
if (isset($member['mb_id']) && is_string($member['mb_id'])) {
    $memberId = trim($member['mb_id']);
}

$deviceInfo = json_encode([
    'device_id' => trim((string) ($input['device_id'] ?? $input['uuid'] ?? '')),
    'app_version' => trim((string) ($input['app_version'] ?? $input['version'] ?? '')),
    'platform' => $platform,
    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

@mysqli_query($connect, "ALTER TABLE g5_push_tokens MODIFY pt_token VARCHAR(500) NOT NULL");

$stmt = mysqli_prepare(
    $connect,
    "INSERT INTO g5_push_tokens
        (mb_id, pt_token, pt_platform, pt_device_info, pt_datetime, pt_use)
     VALUES (?, ?, ?, ?, NOW(), 1)
     ON DUPLICATE KEY UPDATE
        mb_id = VALUES(mb_id),
        pt_platform = VALUES(pt_platform),
        pt_device_info = VALUES(pt_device_info),
        pt_datetime = NOW(),
        pt_use = 1"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Token registration failed']);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ssss', $memberId, $token, $platform, $deviceInfo);
$registered = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$registered) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Token registration failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'result' => 'OK',
    'linked' => $memberId !== '',
]);

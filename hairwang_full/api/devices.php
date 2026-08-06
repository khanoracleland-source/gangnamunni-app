<?php
/**
 * Android device registration endpoint.
 *
 * The native application calls POST /api/devices.php?action=register on
 * startup. Registered FCM tokens are used by the Admin notification page.
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

if (($_GET['action'] ?? '') !== 'register') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported action']);
    exit;
}

require_once dirname(__DIR__) . '/common.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$token = trim((string)($input['token'] ?? $input['fcm_token'] ?? ''));
$platform = strtolower(trim((string)($input['platform'] ?? 'android')));
$deviceId = trim((string)($input['device_id'] ?? ''));
$appVersion = trim((string)($input['app_version'] ?? ''));
$memberId = !empty($member['mb_id']) ? (string)$member['mb_id'] : '';

if (strlen($token) < 20 || strlen($token) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid FCM token']);
    exit;
}

if (!in_array($platform, ['android', 'ios', 'web'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid platform']);
    exit;
}

$createSql = "CREATE TABLE IF NOT EXISTS `g5_push_tokens` (
    `pt_id` int(11) NOT NULL AUTO_INCREMENT,
    `mb_id` varchar(20) NOT NULL DEFAULT '',
    `pt_token` varchar(500) NOT NULL,
    `pt_platform` varchar(10) NOT NULL DEFAULT '',
    `pt_device_id` varchar(100) NOT NULL DEFAULT '',
    `pt_app_version` varchar(50) NOT NULL DEFAULT '',
    `pt_device_info` text,
    `pt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `pt_update_datetime` datetime DEFAULT NULL,
    `pt_use` tinyint(4) NOT NULL DEFAULT '1',
    PRIMARY KEY (`pt_id`),
    UNIQUE KEY `pt_token` (`pt_token`),
    KEY `mb_id` (`mb_id`),
    KEY `pt_device_id` (`pt_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!sql_query($createSql, false)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Token table unavailable']);
    exit;
}

$deviceInfo = json_encode([
    'device_id' => $deviceId,
    'platform' => $platform,
    'app_version' => $appVersion,
    'app_identifier' => (string)($input['app_identifier'] ?? ''),
    'os_version' => (string)($input['os_version'] ?? ''),
    'device_model' => (string)($input['device_model'] ?? ''),
    'default_topic' => (string)($input['default_topic'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$sql = "INSERT INTO g5_push_tokens SET
            mb_id = '" . sql_real_escape_string($memberId) . "',
            pt_token = '" . sql_real_escape_string($token) . "',
            pt_platform = '" . sql_real_escape_string($platform) . "',
            pt_device_info = '" . sql_real_escape_string($deviceInfo) . "',
            pt_datetime = NOW(),
            pt_use = 1
        ON DUPLICATE KEY UPDATE
            mb_id = IF(VALUES(mb_id) <> '', VALUES(mb_id), mb_id),
            pt_platform = VALUES(pt_platform),
            pt_device_info = VALUES(pt_device_info),
            pt_datetime = NOW(),
            pt_use = 1";

if (!sql_query($sql, false)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Token registration failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'registered' => true,
    'platform' => $platform,
]);

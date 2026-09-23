<?php
include_once('./_common.php');
require_once G5_PATH . '/gangnamunni/includes/push_token_registry.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$memberId = isset($member['mb_id']) ? (string)$member['mb_id'] : '';
$result = seoulbeauty_register_push_token($connect ?? null, $memberId, $input);

if (empty($result['success'])) {
    http_response_code((int)($result['http_code'] ?? 500));
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Token could not be saved',
        'debug' => $result['debug'] ?? null,
    ]);
    exit;
}

echo json_encode($result);

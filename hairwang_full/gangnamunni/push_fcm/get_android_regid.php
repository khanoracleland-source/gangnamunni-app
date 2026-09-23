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
require_once dirname(__DIR__) . '/includes/push_token_registry.php';

if (!isset($connect) || !$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

// Never trust a user id supplied by the client when a Gnuboard session exists.
$memberId = '';
if (isset($member['mb_id']) && is_string($member['mb_id'])) {
    $memberId = trim($member['mb_id']);
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;
$result = seoulbeauty_register_push_token($connect, $memberId, $input, ['allow_empty_member' => true]);

if (empty($result['success'])) {
    http_response_code((int)($result['http_code'] ?? 500));
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Token registration failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'result' => 'OK',
    'linked' => !empty($result['linked']),
]);

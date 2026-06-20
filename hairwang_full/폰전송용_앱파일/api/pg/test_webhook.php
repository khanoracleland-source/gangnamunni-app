<?php
/**
 * 웹훅 테스트 페이지
 * PG 결제 완료 후 웹훅을 테스트하는 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'error' => array(
            'message' => 'Method Not Allowed. Only POST is supported.',
            'code' => 'METHOD_NOT_ALLOWED'
        )
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 요청 파싱
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($content_type, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
} else {
    $webhook_data = $_POST;
}

// 웹훅 데이터 검증
$webhook_secret = defined('G5_WEBHOOK_SECRET') ? G5_WEBHOOK_SECRET : 'default_webhook_secret_change_me';
$webhook_type = isset($webhook_data['type']) ? trim($webhook_data['type']) : '';
$order_id = isset($webhook_data['order_id']) ? trim($webhook_data['order_id']) : '';
$external_order_id = isset($webhook_data['external_order_id']) ? trim($webhook_data['external_order_id']) : '';
$status = isset($webhook_data['status']) ? trim($webhook_data['status']) : '';
$timestamp = isset($webhook_data['timestamp']) ? (int)$webhook_data['timestamp'] : 0;

// 웹훅 처리 로그 기록
$log_file = G5_DATA_PATH.'/api/logs/webhook_' . date('Ymd') . '.log';
$log_entry = date('Y-m-d H:i:s') . ' [WEBHOOK] Type: ' . $webhook_type . ', Order ID: ' . $order_id . ', External Order ID: ' . $external_order_id . ', Status: ' . $status . PHP_EOL;
file_put_contents($log_file, $log_entry, FILE_APPEND);

// 응답
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$response = array(
    'success' => true,
    'webhook_type' => $webhook_type,
    'order_id' => $order_id,
    'external_order_id' => $external_order_id,
    'status' => $status,
    'timestamp' => time(),
    'message' => 'Webhook received successfully'
);

echo json_encode($response, JSON_UNESCAPED_UNICODE);

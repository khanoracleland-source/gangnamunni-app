<?php
/**
 * 웹훅 API
 * 결제 완료 후 외부 사이트로 알림을 보내는 웹훅 처리 API
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// 쇼핑몰 설정 파일 포함
if (file_exists(G5_PATH . '/shop/_common.php')) {
    include_once G5_PATH . '/shop/_common.php';
}

// 요청 메서드 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

// 웹훅 서명 확인 (보안)
$webhook_secret = defined('G5_WEBHOOK_SECRET') ? G5_WEBHOOK_SECRET : 'default_webhook_secret_change_me';

// 웹훅 데이터 가져오기
$input = file_get_contents('php://input');
$webhook_data = json_decode($input, true);

if (!$webhook_data) {
    ApiResponse::error('Invalid webhook data', 400, 'INVALID_DATA');
}

// 웹훅 타입 확인
$webhook_type = isset($webhook_data['type']) ? trim($webhook_data['type']) : '';

if (empty($webhook_type)) {
    ApiResponse::error('Webhook type is required', 400, 'WEBHOOK_TYPE_REQUIRED');
}

// 웹훅 처리
try {
    global $g5;
    
    switch ($webhook_type) {
        case 'payment_completed':
            // 결제 완료 웹훅
            process_payment_completed_webhook($webhook_data);
            break;
        case 'payment_failed':
            // 결제 실패 웹훅
            process_payment_failed_webhook($webhook_data);
            break;
        case 'order_status_changed':
            // 주문 상태 변경 웹훅
            process_order_status_changed_webhook($webhook_data);
            break;
        default:
            ApiResponse::error('Unknown webhook type', 400, 'UNKNOWN_WEBHOOK_TYPE');
    }
    
    // 성공 응답
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => true,
        'message' => 'Webhook processed successfully'
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // 에러 로그
    ApiLogger::logError("Webhook processing error: " . $e->getMessage(), 'ERROR');
    
    // 에러 응답
    ApiResponse::error($e->getMessage(), 400, 'WEBHOOK_PROCESSING_ERROR');
}

/**
 * 결제 완료 웹훅 처리
 */
function process_payment_completed_webhook($webhook_data) {
    global $g5;
    
    $order_id = isset($webhook_data['order_id']) ? trim($webhook_data['order_id']) : '';
    $external_order_id = isset($webhook_data['external_order_id']) ? trim($webhook_data['external_order_id']) : '';
    $tid = isset($webhook_data['tid']) ? trim($webhook_data['tid']) : '';
    $amount = isset($webhook_data['amount']) ? (int)$webhook_data['amount'] : 0;
    $pg_code = isset($webhook_data['pg_code']) ? trim($webhook_data['pg_code']) : '';
    $timestamp = isset($webhook_data['timestamp']) ? (int)$webhook_data['timestamp'] : 0;
    
    // 유효성 검사
    if (empty($order_id)) {
        throw new Exception('Order ID is required', 'ORDER_ID_REQUIRED');
    }
    
    if (empty($external_order_id)) {
        throw new Exception('External Order ID is required', 'EXTERNAL_ORDER_ID_REQUIRED');
    }
    
    if (empty($tid)) {
        throw new Exception('Transaction ID is required', 'TID_REQUIRED');
    }
    
    // PG 주문 테이블 업데이트
    if (!isset($g5['g5_pg_api_orders_table'])) {
        $g5['g5_pg_api_orders_table'] = G5_TABLE_PREFIX.'pg_api_orders';
    }
    
    // 이미 처리된 주문인지 확인
    $sql = "
        SELECT po_id, po_status
        FROM {$g5['g5_pg_api_orders_table']}
        WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
    ";
    $pg_order = sql_fetch($sql);
    
    if (!$pg_order) {
        throw new Exception('Order not found', 'ORDER_NOT_FOUND');
    }
    
    // 이미 결제 완료된 주문이면 무시
    if ($pg_order['po_status'] === 'paid') {
        return;
    }
    
    // 주문 상태 업데이트
    $update_sql = "
        UPDATE {$g5['g5_pg_api_orders_table']}
        SET po_status = 'paid',
            po_pg_tid = '" . sql_real_escape_string($tid) . "',
            po_pg_response = '" . sql_real_escape_string(json_encode($webhook_data, JSON_UNESCAPED_UNICODE)) . "',
            po_updated_at = FROM_UNIXTIME($timestamp)
        WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
    ";
    
    sql_query($update_sql);
    
    // 쇼핑몰 주문 테이블 업데이트
    if (!isset($g5['g5_shop_order_table'])) {
        $g5['g5_shop_order_table'] = G5_TABLE_PREFIX.'shop_order';
    }
    
    $update_shop_sql = "
        UPDATE {$g5['g5_shop_order_table']}
        SET od_receipt_time = FROM_UNIXTIME($timestamp),
            od_receipt_price = '" . sql_real_escape_string($amount) . "',
            od_misu = 0,
            od_status = '주문',
            od_shop_memo = CONCAT(COALESCE(od_shop_memo, ''), '\\nPayment completed via Webhook. TID: " . sql_real_escape_string($tid) . "')
        WHERE od_id = '" . sql_real_escape_string($order_id) . "'
    ";
    
    sql_query($update_shop_sql);
    
    // 로그 기록
    ApiLogger::logError("Payment completed webhook processed. Order ID: $order_id, External Order ID: $external_order_id, TID: $tid", 'INFO');
}

/**
 * 결제 실패 웹훅 처리
 */
function process_payment_failed_webhook($webhook_data) {
    global $g5;
    
    $order_id = isset($webhook_data['order_id']) ? trim($webhook_data['order_id']) : '';
    $external_order_id = isset($webhook_data['external_order_id']) ? trim($webhook_data['external_order_id']) : '';
    $error_code = isset($webhook_data['error_code']) ? trim($webhook_data['error_code']) : '';
    $error_message = isset($webhook_data['error_message']) ? trim($webhook_data['error_message']) : '';
    $timestamp = isset($webhook_data['timestamp']) ? (int)$webhook_data['timestamp'] : 0;
    
    // 유효성 검사
    if (empty($order_id)) {
        throw new Exception('Order ID is required', 'ORDER_ID_REQUIRED');
    }
    
    // PG 주문 테이블 업데이트
    if (!isset($g5['g5_pg_api_orders_table'])) {
        $g5['g5_pg_api_orders_table'] = G5_TABLE_PREFIX.'pg_api_orders';
    }
    
    $update_sql = "
        UPDATE {$g5['g5_pg_api_orders_table']}
        SET po_status = 'failed',
            po_pg_response = '" . sql_real_escape_string(json_encode($webhook_data, JSON_UNESCAPED_UNICODE)) . "',
            po_updated_at = FROM_UNIXTIME($timestamp)
        WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
    ";
    
    sql_query($update_sql);
    
    // 로그 기록
    ApiLogger::logError("Payment failed webhook processed. Order ID: $order_id, Error Code: $error_code, Error Message: $error_message", 'INFO');
}

/**
 * 주문 상태 변경 웹훅 처리
 */
function process_order_status_changed_webhook($webhook_data) {
    global $g5;
    
    $order_id = isset($webhook_data['order_id']) ? trim($webhook_data['order_id']) : '';
    $external_order_id = isset($webhook_data['external_order_id']) ? trim($webhook_data['external_order_id']) : '';
    $status = isset($webhook_data['status']) ? trim($webhook_data['status']) : '';
    $timestamp = isset($webhook_data['timestamp']) ? (int)$webhook_data['timestamp'] : 0;
    
    // 유효성 검사
    if (empty($order_id)) {
        throw new Exception('Order ID is required', 'ORDER_ID_REQUIRED');
    }
    
    if (empty($status)) {
        throw new Exception('Status is required', 'STATUS_REQUIRED');
    }
    
    // 쇼핑몰 주문 테이블 업데이트
    if (!isset($g5['g5_shop_order_table'])) {
        $g5['g5_shop_order_table'] = G5_TABLE_PREFIX.'shop_order';
    }
    
    $update_sql = "
        UPDATE {$g5['g5_shop_order_table']}
        SET od_status = '" . sql_real_escape_string($status) . "',
            od_shop_memo = CONCAT(COALESCE(od_shop_memo, ''), '\\nStatus changed via Webhook. Status: " . sql_real_escape_string($status) . "')
        WHERE od_id = '" . sql_real_escape_string($order_id) . "'
    ";
    
    sql_query($update_sql);
    
    // 로그 기록
    ApiLogger::logError("Order status changed webhook processed. Order ID: $order_id, New Status: $status", 'INFO');
}

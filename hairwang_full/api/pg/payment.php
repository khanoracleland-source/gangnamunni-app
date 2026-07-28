<?php
/**
 * 결제 요청 API
 * PG 사와 연동하여 결제를 처리하는 API
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// 쇼핑몰 설정 파일 포함
if (file_exists(G5_PATH . '/shop/_common.php')) {
    include_once G5_PATH . '/shop/_common.php';
}

// 요청 메서드 확인 (POST만 허용)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

// API 인증 확인
$auth = ApiAuth::getAuthFromHeader();
if (!$auth) {
    ApiResponse::error('API authentication required. Provide X-API-Key and X-API-Secret headers.', 401, 'AUTH_REQUIRED');
}

$api_key_info = ApiAuth::verifyApiKey($auth['api_key'], $auth['api_secret']);
if (!$api_key_info) {
    ApiResponse::error('Invalid API key or secret', 401, 'INVALID_API_KEY');
}

// API 키 타입 확인
if ($api_key_info['ak_type'] !== 'pg' && $api_key_info['ak_type'] !== 'all') {
    ApiResponse::error('This API key is not authorized for PG operations', 403, 'FORBIDDEN');
}

// IP 화이트리스트 확인
$request_ip = ApiAuth::getRequestIp();
if (!ApiAuth::verifyIpWhitelist($api_key_info['ak_id'], $request_ip)) {
    ApiResponse::error('Your IP address is not authorized', 403, 'IP_NOT_ALLOWED');
}
if (!ApiAuth::enforceRateLimit($api_key_info['ak_id'], 'pg-payment', 30)) {
    ApiResponse::error('Too many requests', 429, 'RATE_LIMITED');
}

if (!defined('G5_PG_PAYMENTS_ENABLED') || G5_PG_PAYMENTS_ENABLED !== true) {
    ApiResponse::error('Payment providers are not configured. No payment state was changed.', 503, 'PAYMENT_PROVIDER_NOT_CONFIGURED');
}

// 요청 파라미터 가져오기
$order_id = isset($_POST['order_id']) ? trim($_POST['order_id']) : '';
$external_order_id = isset($_POST['external_order_id']) ? trim($_POST['external_order_id']) : '';

// 요청 유효성 검사
$errors = array();

if (empty($order_id)) {
    $errors['order_id'] = 'Order ID is required';
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// 로깅 시작
$log_start_time = microtime(true);

try {
    global $g5;
    
    // PG 주문 테이블 확인
    if (!isset($g5['g5_pg_api_orders_table'])) {
        $g5['g5_pg_api_orders_table'] = G5_TABLE_PREFIX.'pg_api_orders';
    }
    
    // 쇼핑몰 주문 테이블 확인
    if (!isset($g5['g5_shop_order_table'])) {
        $g5['g5_shop_order_table'] = G5_TABLE_PREFIX.'shop_order';
    }
    
    // 주문 정보 조회
    $sql = "
        SELECT
            po.*,
            od.od_price,
            od.od_settle_case,
            od.od_name,
            od.od_tel
        FROM {$g5['g5_pg_api_orders_table']} po
        LEFT JOIN {$g5['g5_shop_order_table']} od ON po.po_g5_order_id = od.od_id
        WHERE po.po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
          AND po.ak_id = '" . sql_real_escape_string($api_key_info['ak_id']) . "'
    ";
    
    $order = sql_fetch($sql);
    
    if (!$order) {
        throw new ApiException('Order not found', 'ORDER_NOT_FOUND');
    }
    
    if ($order['po_status'] === 'paid') {
        throw new ApiException('Order is already paid', 'ALREADY_PAID');
    }
    
    if ($order['po_status'] === 'cancelled') {
        throw new ApiException('Order is cancelled', 'ORDER_CANCELLED');
    }
    
    // PG 사별 결제 처리
    $pg_code = $order['po_pg_code'];
    $payment_result = null;
    
    switch ($pg_code) {
        case 'inicis':
            $payment_result = process_inicis_payment($order);
            break;
        case 'lg':
            $payment_result = process_lg_payment($order);
            break;
        case 'kcp':
            $payment_result = process_kcp_payment($order);
            break;
        case 'kakaopay':
            $payment_result = process_kakaopay_payment($order);
            break;
        case 'nicepay':
            $payment_result = process_nicepay_payment($order);
            break;
        default:
            throw new ApiException('Unsupported payment method', 'UNSUPPORTED_PAYMENT_METHOD');
    }
    
    // 결제 결과 처리
    if ($payment_result['success']) {
        // 결제 성공 처리
        $tid = $payment_result['tid'];
        $pg_response = json_encode($payment_result['response'], JSON_UNESCAPED_UNICODE);
        
        // PG 주문 테이블 업데이트
        $update_pg_order_sql = "
            UPDATE {$g5['g5_pg_api_orders_table']}
            SET po_status = 'paid',
                po_pg_tid = '" . sql_real_escape_string($tid) . "',
                po_pg_response = '" . sql_real_escape_string($pg_response) . "',
                po_updated_at = NOW()
            WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
        ";
        
        sql_query($update_pg_order_sql);
        
        // 쇼핑몰 주문 테이블 업데이트
        $update_shop_order_sql = "
            UPDATE {$g5['g5_shop_order_table']}
            SET od_receipt_time = NOW(),
                od_receipt_price = od_price,
                od_misu = 0,
                od_status = '주문',
                od_shop_memo = CONCAT(od_shop_memo, '\\nPayment completed via API. TID: " . sql_real_escape_string($tid) . "')
            WHERE od_id = '" . sql_real_escape_string($order_id) . "'
        ";
        
        sql_query($update_shop_order_sql);
        
        // 콜백 URL로 결과 전송
        if ($order['po_callback_url']) {
            send_callback($order['po_callback_url'], array(
                'external_order_id' => $order['po_external_order_id'],
                'order_id' => $order_id,
                'pg_order_id' => $order['po_id'],
                'status' => 'paid',
                'tid' => $tid,
                'amount' => $order['po_amount'],
                'pg_code' => $pg_code,
                'timestamp' => time()
            ));
        }
        
        $result = array(
            'order_id' => $order_id,
            'external_order_id' => $order['po_external_order_id'],
            'pg_order_id' => $order['po_id'],
            'status' => 'paid',
            'tid' => $tid,
            'amount' => $order['po_amount'],
            'pg_code' => $pg_code,
            'message' => 'Payment completed successfully'
        );
    } else {
        // 결제 실패 처리
        $error_message = isset($payment_result['error']) ? $payment_result['error'] : 'Payment failed';
        
        // PG 주문 테이블 업데이트
        $update_pg_order_sql = "
            UPDATE {$g5['g5_pg_api_orders_table']}
            SET po_status = 'failed',
                po_pg_response = '" . sql_real_escape_string(json_encode($payment_result['response'], JSON_UNESCAPED_UNICODE)) . "',
                po_updated_at = NOW()
            WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
        ";
        
        sql_query($update_pg_order_sql);
        
        throw new ApiException($error_message, 'PAYMENT_FAILED');
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/payment.php',
        'POST',
        $request_ip,
        array('order_id' => $order_id, 'external_order_id' => $external_order_id),
        200,
        $result,
        null,
        $execution_time
    );
    
    // 성공 응답
    ApiResponse::success($result, 200);
    
} catch (Exception $e) {
    // 에러 로그 기록
    $error_message = $e->getMessage();
    $error_code = $e instanceof ApiException ? $e->apiCode : 'UNKNOWN_ERROR';
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/payment.php',
        'POST',
        $request_ip,
        array('order_id' => $order_id, 'external_order_id' => $external_order_id),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * 이니시스 결제 처리
 */
function process_inicis_payment($order) {
    // 기존 이니시스 결제 모듈 포함
    $inicis_inc_path = G5_SHOP_PATH . '/settle_inicis.inc.php';
    
    if (file_exists($inicis_inc_path)) {
        include_once $inicis_inc_path;
        
        // 이니시스 API 호출 로직
        // API 모드에서 동작하도록 수정 필요
        
        return array(
            'success' => false,
            'error' => 'Inicis adapter is not configured',
            'response' => array('code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED')
        );
    } else {
        throw new ApiException('Inicis payment module not found', 'PAYMENT_MODULE_NOT_FOUND');
    }
}

/**
 * LG U+ 결제 처리
 */
function process_lg_payment($order) {
    $lg_inc_path = G5_SHOP_PATH . '/settle_lg.inc.php';
    
    if (file_exists($lg_inc_path)) {
        include_once $lg_inc_path;
        
        // LG U+ API 호출 로직
        
        return array(
            'success' => false,
            'error' => 'LG U+ adapter is not configured',
            'response' => array('code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED')
        );
    } else {
        throw new ApiException('LG payment module not found', 'PAYMENT_MODULE_NOT_FOUND');
    }
}

/**
 * KCP 결제 처리
 */
function process_kcp_payment($order) {
    $kcp_inc_path = G5_SHOP_PATH . '/settle_kcp.inc.php';
    
    if (file_exists($kcp_inc_path)) {
        include_once $kcp_inc_path;
        
        // KCP API 호출 로직
        
        return array(
            'success' => false,
            'error' => 'KCP adapter is not configured',
            'response' => array('code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED')
        );
    } else {
        throw new ApiException('KCP payment module not found', 'PAYMENT_MODULE_NOT_FOUND');
    }
}

/**
 * 카카오페이 결제 처리
 */
function process_kakaopay_payment($order) {
    $kakaopay_inc_path = G5_SHOP_PATH . '/settle_kakaopay.inc.php';
    
    if (file_exists($kakaopay_inc_path)) {
        include_once $kakaopay_inc_path;
        
        // 카카오페이 API 호출 로직
        
        return array(
            'success' => false,
            'error' => 'KakaoPay adapter is not configured',
            'response' => array('code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED')
        );
    } else {
        throw new ApiException('Kakaopay payment module not found', 'PAYMENT_MODULE_NOT_FOUND');
    }
}

/**
 * 나이스페이 결제 처리
 */
function process_nicepay_payment($order) {
    $nicepay_inc_path = G5_SHOP_PATH . '/settle_nicepay.inc.php';
    
    if (file_exists($nicepay_inc_path)) {
        include_once $nicepay_inc_path;
        
        // 나이스페이 API 호출 로직
        
        return array(
            'success' => false,
            'error' => 'NicePay adapter is not configured',
            'response' => array('code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED')
        );
    } else {
        throw new ApiException('Nicepay payment module not found', 'PAYMENT_MODULE_NOT_FOUND');
    }
}

/**
 * 콜백 URL로 결과 전송
 */
function send_callback($callback_url, $data) {
    if (!pg_api_callback_url_is_allowed($callback_url)) {
        ApiLogger::logError('Blocked payment callback to a non-allowlisted URL', 'WARNING');
        return false;
    }

    $timestamp = (string)time();
    $payload = http_build_query($data);
    $secret = defined('G5_WEBHOOK_SECRET') ? G5_WEBHOOK_SECRET : '';
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $callback_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-Webhook-Timestamp: ' . $timestamp,
        'X-Webhook-Signature: ' . $signature,
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    // 콜백 전송 로그 기록
    ApiLogger::logError("Callback sent to $callback_url. HTTP Code: $http_code, Response: $response", 'INFO');
    
    return $http_code === 200;
}

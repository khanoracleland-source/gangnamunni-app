<?php
/**
 * 주문 생성 API
 * 주문을 생성하는 API
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

// 요청 파라미터 가져오기
$external_order_id = isset($_POST['external_order_id']) ? trim($_POST['external_order_id']) : '';
$cart_id = isset($_POST['cart_id']) ? trim($_POST['cart_id']) : '';
$receiver_name = isset($_POST['receiver_name']) ? trim($_POST['receiver_name']) : '';
$receiver_phone = isset($_POST['receiver_phone']) ? trim($_POST['receiver_phone']) : '';
$receiver_zipcode = isset($_POST['receiver_zipcode']) ? trim($_POST['receiver_zipcode']) : '';
$receiver_address1 = isset($_POST['receiver_address1']) ? trim($_POST['receiver_address1']) : '';
$receiver_address2 = isset($_POST['receiver_address2']) ? trim($_POST['receiver_address2']) : '';
$receiver_address3 = isset($_POST['receiver_address3']) ? trim($_POST['receiver_address3']) : '';
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$callback_url = isset($_POST['callback_url']) ? trim($_POST['callback_url']) : '';
$custom_fields = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : array();

// 요청 유효성 검사
$errors = array();

if (empty($external_order_id)) {
    $errors['external_order_id'] = 'External Order ID is required';
}

if (empty($cart_id)) {
    $errors['cart_id'] = 'Cart ID is required';
}

if (empty($receiver_name)) {
    $errors['receiver_name'] = 'Receiver Name is required';
}

if (empty($receiver_phone)) {
    $errors['receiver_phone'] = 'Receiver Phone is required';
}

if (empty($receiver_zipcode)) {
    $errors['receiver_zipcode'] = 'Receiver Zipcode is required';
}

if (empty($receiver_address1)) {
    $errors['receiver_address1'] = 'Receiver Address1 is required';
}

if (empty($payment_method)) {
    $errors['payment_method'] = 'Payment Method is required';
}

// 지원하는 결제 방법
$supported_payment_methods = array('inicis', 'lg', 'kcp', 'kakaopay', 'nicepay');
if (!in_array($payment_method, $supported_payment_methods)) {
    $errors['payment_method'] = 'Invalid payment method. Supported: ' . implode(', ', $supported_payment_methods);
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
    
    // 중복 주문 확인
    $sql = "
        SELECT COUNT(*) as cnt
        FROM {$g5['g5_pg_api_orders_table']}
        WHERE po_external_order_id = '" . sql_real_escape_string($external_order_id) . "'
    ";
    $result = sql_fetch($sql);
    
    if ($result['cnt'] > 0) {
        throw new Exception('Duplicate external order ID', 'DUPLICATE_ORDER');
    }
    
    // 장바구니 아이템 조회
    $sql = "
        SELECT
            ct_id,
            it_id,
            it_name,
            it_price,
            ct_qty,
            ct_amount,
            ct_opt1,
            ct_opt2,
            ct_opt3
        FROM {$g5['prefix']}shop_cart
        WHERE od_id = '" . sql_real_escape_string($cart_id) . "'
          AND ct_select = '1'
          AND ct_status = '0'
    ";
    
    $cart_result = sql_query($sql);
    $cart_items = array();
    $total_amount = 0;
    
    while ($row = sql_fetch_array($cart_result)) {
        $cart_items[] = array(
            'ct_id' => $row['ct_id'],
            'it_id' => $row['it_id'],
            'it_name' => $row['it_name'],
            'it_price' => (int)$row['it_price'],
            'ct_qty' => (int)$row['ct_qty'],
            'ct_amount' => (int)$row['ct_amount'],
            'ct_opt1' => $row['ct_opt1'],
            'ct_opt2' => $row['ct_opt2'],
            'ct_opt3' => $row['ct_opt3']
        );
        
        $total_amount += (int)$row['ct_amount'];
    }
    
    if (empty($cart_items)) {
        throw new Exception('Cart is empty', 'EMPTY_CART');
    }
    
    // 주문 번호 생성
    $order_id = generate_order_id();
    
    // 주문 정보 생성
    $order_insert = "
        INSERT INTO {$g5['g5_shop_order_table']}
        SET od_id = '" . sql_real_escape_string($order_id) . "',
            mb_id = '',
            od_name = '" . sql_real_escape_string($receiver_name) . "',
            od_tel = '" . sql_real_escape_string($receiver_phone) . "',
            od_zip1 = '" . sql_real_escape_string(substr($receiver_zipcode, 0, 3)) . "',
            od_zip2 = '" . sql_real_escape_string(substr($receiver_zipcode, 3)) . "',
            od_addr1 = '" . sql_real_escape_string($receiver_address1) . "',
            od_addr2 = '" . sql_real_escape_string($receiver_address2) . "',
            od_addr3 = '" . sql_real_escape_string($receiver_address3) . "',
            od_b_name = '" . sql_real_escape_string($receiver_name) . "',
            od_b_tel = '" . sql_real_escape_string($receiver_phone) . "',
            od_b_zip1 = '" . sql_real_escape_string(substr($receiver_zipcode, 0, 3)) . "',
            od_b_zip2 = '" . sql_real_escape_string(substr($receiver_zipcode, 3)) . "',
            od_b_addr1 = '" . sql_real_escape_string($receiver_address1) . "',
            od_b_addr2 = '" . sql_real_escape_string($receiver_address2) . "',
            od_b_addr3 = '" . sql_real_escape_string($receiver_address3) . "',
            od_deposit_name = '" . sql_real_escape_string($receiver_name) . "',
            od_temp_point = 0,
            od_receipt_point = 0,
            od_cash = 0,
            od_bank_account = '',
            od_receipt_time = '0000-00-00 00:00:00',
            od_price = '" . sql_real_escape_string($total_amount) . "',
            od_cart_price = '" . sql_real_escape_string($total_amount) . "',
            od_send_cost = 0,
            od_send_cost2 = 0,
            od_samche = '0',
            od_misu = '" . sql_real_escape_string($total_amount) . "',
            od_settle_case = '" . sql_real_escape_string($payment_method) . "',
            od_test = '0',
            od_hope_date = '',
            od_time = NOW(),
            od_ip = '" . sql_real_escape_string($_SERVER['REMOTE_ADDR']) . "',
            od_shop_memo = 'External Order ID: ' . sql_real_escape_string($external_order_id)
    ";
    
    sql_query($order_insert);
    
    // 주문 상세 정보 생성
    foreach ($cart_items as $cart_item) {
        $order_detail_insert = "
            INSERT INTO {$g5['prefix']}shop_order_detail
            SET od_id = '" . sql_real_escape_string($order_id) . "',
                it_id = '" . sql_real_escape_string($cart_item['it_id']) . "',
                it_name = '" . sql_real_escape_string($cart_item['it_name']) . "',
                ct_option = '" . sql_real_escape_string($cart_item['ct_opt1'] . ' ' . $cart_item['ct_opt2'] . ' ' . $cart_item['ct_opt3']) . "',
                ct_qty = '" . sql_real_escape_string($cart_item['ct_qty']) . "',
                ct_amount = '" . sql_real_escape_string($cart_item['ct_amount']) . "',
                io_type = 0,
                io_price = '" . sql_real_escape_string($cart_item['it_price']) . "'
        ";
        
        sql_query($order_detail_insert);
    }
    
    // PG API 주문 정보 저장
    $pg_order_insert = "
        INSERT INTO {$g5['g5_pg_api_orders_table']}
        SET ak_id = '" . sql_real_escape_string($api_key_info['ak_id']) . "',
            po_external_order_id = '" . sql_real_escape_string($external_order_id) . "',
            po_g5_order_id = '" . sql_real_escape_string($order_id) . "',
            po_item_name = '" . sql_real_escape_string($cart_items[0]['it_name']) . "',
            po_amount = '" . sql_real_escape_string($total_amount) . "',
            po_status = 'pending',
            po_pg_code = '" . sql_real_escape_string($payment_method) . "',
            po_callback_url = '" . sql_real_escape_string($callback_url) . "',
            po_custom_fields = " . (!empty($custom_fields) ? "'" . sql_real_escape_string(json_encode($custom_fields, JSON_UNESCAPED_UNICODE)) . "'" : "NULL") . ",
            po_created_at = NOW(),
            po_updated_at = NOW()
    ";
    
    sql_query($pg_order_insert);
    
    $pg_order_id = sql_insert_id();
    
    // 커스텀 필드 저장
    if (!empty($custom_fields)) {
        save_custom_fields('pg', $pg_order_id, null, $custom_fields);
    }
    
    // 결제 정보 생성
    $payment_data = array(
        'order_id' => $order_id,
        'external_order_id' => $external_order_id,
        'amount' => $total_amount,
        'payment_method' => $payment_method,
        'callback_url' => $callback_url
    );
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/order.php',
        'POST',
        $request_ip,
        array(
            'external_order_id' => $external_order_id,
            'cart_id' => $cart_id,
            'receiver_name' => $receiver_name,
            'payment_method' => $payment_method
        ),
        200,
        array(
            'order_id' => $order_id,
            'external_order_id' => $external_order_id,
            'status' => 'pending'
        ),
        null,
        $execution_time
    );
    
    // 성공 응답
    ApiResponse::success(array(
        'order_id' => $order_id,
        'external_order_id' => $external_order_id,
        'pg_order_id' => $pg_order_id,
        'amount' => $total_amount,
        'status' => 'pending',
        'payment_data' => $payment_data,
        'created_at' => date('Y-m-d H:i:s')
    ), 200);
    
} catch (Exception $e) {
    // 에러 로그 기록
    $error_message = $e->getMessage();
    $error_code = $e->getCode() ?: 'UNKNOWN_ERROR';
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/order.php',
        'POST',
        $request_ip,
        array(
            'external_order_id' => $external_order_id,
            'cart_id' => $cart_id,
            'receiver_name' => $receiver_name,
            'payment_method' => $payment_method
        ),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * 주문 번호 생성
 */
function generate_order_id() {
    return date('Ymd') . '-' . time() . '-' . mt_rand(1000, 9999);
}

/**
 * 커스텀 필드 저장
 */
function save_custom_fields($category, $ref_id, $mb_id, $custom_fields) {
    global $g5;
    
    foreach ($custom_fields as $field_code => $field_value) {
        // 필드 정의 조회
        $field_sql = "SELECT * FROM {$g5['prefix']}api_fields WHERE af_code = '" . sql_real_escape_string($field_code) . "' AND af_status = 1";
        $field_info = sql_fetch($field_sql);
        
        if (!$field_info) {
            continue;
        }
        
        // 필드 값 저장
        $value_sql = "
            INSERT INTO {$g5['prefix']}api_field_values
            SET af_id = '" . sql_real_escape_string($field_info['af_id']) . "',
                sl_id = " . ($category === 'sns' && $ref_id ? "'" . sql_real_escape_string($ref_id) . "'" : "NULL") . ",
                po_id = " . ($category === 'pg' && $ref_id ? "'" . sql_real_escape_string($ref_id) . "'" : "NULL") . ",
                fv_value = '" . sql_real_escape_string(is_array($field_value) ? json_encode($field_value, JSON_UNESCAPED_UNICODE) : $field_value) . "',
                fv_created_at = NOW()
        ";
        
        sql_query($value_sql);
    }
}

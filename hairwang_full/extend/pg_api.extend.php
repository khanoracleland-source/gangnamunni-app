<?php
/**
 * PG API 확장 파일
 * PG API 관련 설정 및 함수 정의
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

if (!defined('G5_PATH')) {
    exit;
}

// PG API 관련 상수 정의
if (!defined('G5_PG_API_DIR')) {
    define('G5_PG_API_DIR', G5_PATH . '/api/pg');
}

if (!defined('G5_PG_API_URL')) {
    define('G5_PG_API_URL', G5_URL . '/api/pg');
}

// 데이터베이스 테이블 정의
if (!isset($g5['pg_api_orders_table'])) {
    $g5['pg_api_orders_table'] = G5_TABLE_PREFIX.'pg_api_orders';
}

// PG 결제 API 헬퍼 함수

/**
 * PG API URL 생성
 */
function get_pg_api_url($endpoint) {
    return G5_PG_API_URL . '/' . $endpoint . '.php';
}

/**
 * 상품 조회 API URL 생성
 */
function get_pg_item_api_url($action = 'list') {
    return get_pg_api_url('item');
}

/**
 * 장바구니 API URL 생성
 */
function get_pg_cart_api_url($action = 'list') {
    return get_pg_api_url('cart');
}

/**
 * 주문 API URL 생성
 */
function get_pg_order_api_url() {
    return get_pg_api_url('order');
}

/**
 * 결제 API URL 생성
 */
function get_pg_payment_api_url() {
    return get_pg_api_url('payment');
}

/**
 * 웹훅 API URL 생성
 */
function get_pg_webhook_api_url() {
    return get_pg_api_url('webhook');
}

/**
 * 외부 사이트용 상품 조회
 */
function request_pg_item_list($api_key, $api_secret, $ca_id = '', $page = 1, $limit = 20) {
    $url = get_pg_item_api_url('list');
    
    $params = array(
        'action' => 'list',
        'ca_id' => $ca_id,
        'page' => $page,
        'limit' => $limit
    );
    
    $url .= '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $api_secret
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return array(
        'success' => $http_code === 200 && isset($result['success']) && $result['success'],
        'http_code' => $http_code,
        'data' => $result,
        'raw_response' => $response
    );
}

/**
 * 상품 상세 조회
 */
function request_pg_item_detail($api_key, $api_secret, $item_id) {
    $url = get_pg_item_api_url('detail');
    
    $params = array(
        'action' => 'detail',
        'item_id' => $item_id
    );
    
    $url .= '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $api_secret
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return array(
        'success' => $http_code === 200 && isset($result['success']) && $result['success'],
        'http_code' => $http_code,
        'data' => $result,
        'raw_response' => $response
    );
}

/**
 * 장바구니에 상품 추가
 */
function request_pg_cart_add($api_key, $api_secret, $item_id, $quantity = 1, $options = array(), $cart_id = '') {
    $url = get_pg_cart_api_url('add');
    
    $data = array(
        'item_id' => $item_id,
        'quantity' => $quantity,
        'cart_id' => $cart_id
    );
    
    if (isset($options['option1'])) $data['option1'] = $options['option1'];
    if (isset($options['option2'])) $data['option2'] = $options['option2'];
    if (isset($options['option3'])) $data['option3'] = $options['option3'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $api_secret
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return array(
        'success' => $http_code === 200 && isset($result['success']) && $result['success'],
        'http_code' => $http_code,
        'data' => $result,
        'raw_response' => $response
    );
}

/**
 * 주문 생성
 */
function request_pg_order_create($api_key, $api_secret, $external_order_id, $cart_id, $receiver_info, $payment_method, $callback_url = '', $custom_fields = array()) {
    $url = get_pg_order_api_url();
    
    $data = array(
        'external_order_id' => $external_order_id,
        'cart_id' => $cart_id,
        'payment_method' => $payment_method,
        'callback_url' => $callback_url,
        'custom_fields' => $custom_fields
    );
    
    if (isset($receiver_info['name'])) $data['receiver_name'] = $receiver_info['name'];
    if (isset($receiver_info['phone'])) $data['receiver_phone'] = $receiver_info['phone'];
    if (isset($receiver_info['zipcode'])) $data['receiver_zipcode'] = $receiver_info['zipcode'];
    if (isset($receiver_info['address1'])) $data['receiver_address1'] = $receiver_info['address1'];
    if (isset($receiver_info['address2'])) $data['receiver_address2'] = $receiver_info['address2'];
    if (isset($receiver_info['address3'])) $data['receiver_address3'] = $receiver_info['address3'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $api_secret
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return array(
        'success' => $http_code === 200 && isset($result['success']) && $result['success'],
        'http_code' => $http_code,
        'data' => $result,
        'raw_response' => $response
    );
}

/**
 * 결제 요청
 */
function request_pg_payment($api_key, $api_secret, $order_id, $external_order_id = '') {
    $url = get_pg_payment_api_url();
    
    $data = array(
        'order_id' => $order_id,
        'external_order_id' => $external_order_id
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $api_secret
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return array(
        'success' => $http_code === 200 && isset($result['success']) && $result['success'],
        'http_code' => $http_code,
        'data' => $result,
        'raw_response' => $response
    );
}

/**
 * 웹훅 전송
 */
function send_pg_webhook($webhook_url, $webhook_data) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return array(
        'success' => $http_code === 200,
        'http_code' => $http_code,
        'response' => $response
    );
}

// PG API 로깅
function log_pg_api_request($order_id, $external_order_id, $status, $pg_code = '', $tid = '') {
    global $g5;
    
    if (!isset($g5['pg_api_orders_table'])) {
        $g5['pg_api_orders_table'] = G5_TABLE_PREFIX.'pg_api_orders';
    }
    
    $update_sql = "
        UPDATE {$g5['pg_api_orders_table']}
        SET po_status = '" . sql_real_escape_string($status) . "'"
        . ($tid ? ", po_pg_tid = '" . sql_real_escape_string($tid) . "'" : '')
        . ", po_updated_at = NOW()
        WHERE po_g5_order_id = '" . sql_real_escape_string($order_id) . "'
    ";
    
    sql_query($update_sql);
}

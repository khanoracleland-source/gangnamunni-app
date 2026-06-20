<?php
/**
 * 장바구니 API
 * 장바구니 관련 API (추가, 조회, 삭제 등)
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
$allowed_methods = array('GET', 'POST', 'PUT', 'DELETE');
if (!in_array($_SERVER['REQUEST_METHOD'], $allowed_methods)) {
    ApiResponse::error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
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

// 요청 파라미터 처리
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 로깅 시작
$log_start_time = microtime(true);

try {
    global $g5;
    
    // 쇼핑몰 장바구니 테이블 확인
    if (!isset($g5['g5_shop_cart_table'])) {
        $g5['g5_shop_cart_table'] = G5_TABLE_PREFIX.'shop_cart';
    }
    
    $result = array();
    
    switch ($method) {
        case 'GET':
            // 장바구니 조회
            $result = get_cart();
            break;
        case 'POST':
            // 장바구니 추가
            $result = add_to_cart();
            break;
        case 'PUT':
            // 장바구니 수정
            $result = update_cart();
            break;
        case 'DELETE':
            // 장바구니 삭제
            $result = delete_from_cart();
            break;
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/cart.php',
        $method,
        $request_ip,
        $_REQUEST,
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
    $error_code = $e->getCode() ?: 'UNKNOWN_ERROR';
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/cart.php',
        $method,
        $request_ip,
        $_REQUEST,
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * 장바구니 조회
 */
function get_cart() {
    global $g5;
    
    // 외부 사이트용 장바구니 세션 ID (API 키 기반)
    $cart_id = isset($_GET['cart_id']) ? trim($_GET['cart_id']) : '';
    
    if (empty($cart_id)) {
        throw new Exception('Cart ID is required', 'CART_ID_REQUIRED');
    }
    
    // 장바구니 목록 조회
    $sql = "
        SELECT
            ct_id,
            it_id,
            it_name,
            it_price,
            ct_stock,
            ct_qty,
            ct_amount,
            ct_opt1,
            ct_opt2,
            ct_opt3,
            ct_time,
            ct_status
        FROM {$g5['g5_shop_cart_table']}
        WHERE ct_direct = '0'
          AND ct_select = '1'
          AND od_id = '" . sql_real_escape_string($cart_id) . "'
        ORDER BY ct_id DESC
    ";
    
    $result = sql_query($sql);
    
    $cart_items = array();
    $total_amount = 0;
    $total_qty = 0;
    
    while ($row = sql_fetch_array($result)) {
        $cart_items[] = array(
            'cart_id' => $row['ct_id'],
            'item_id' => $row['it_id'],
            'item_name' => $row['it_name'],
            'price' => (int)$row['it_price'],
            'stock' => (int)$row['ct_stock'],
            'quantity' => (int)$row['ct_qty'],
            'amount' => (int)$row['ct_amount'],
            'option1' => $row['ct_opt1'],
            'option2' => $row['ct_opt2'],
            'option3' => $row['ct_opt3'],
            'added_at' => $row['ct_time'],
            'status' => $row['ct_status']
        );
        
        $total_amount += (int)$row['ct_amount'];
        $total_qty += (int)$row['ct_qty'];
    }
    
    return array(
        'action' => 'get',
        'cart_id' => $cart_id,
        'items' => $cart_items,
        'summary' => array(
            'total_items' => count($cart_items),
            'total_quantity' => $total_qty,
            'total_amount' => $total_amount
        )
    );
}

/**
 * 장바구니 추가
 */
function add_to_cart() {
    global $g5;
    
    // 요청 파라미터
    $item_id = isset($_POST['item_id']) ? trim($_POST['item_id']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $option1 = isset($_POST['option1']) ? trim($_POST['option1']) : '';
    $option2 = isset($_POST['option2']) ? trim($_POST['option2']) : '';
    $option3 = isset($_POST['option3']) ? trim($_POST['option3']) : '';
    $cart_id = isset($_POST['cart_id']) ? trim($_POST['cart_id']) : generate_cart_id();
    
    // 유효성 검사
    if (empty($item_id)) {
        throw new Exception('Item ID is required', 'ITEM_ID_REQUIRED');
    }
    
    if ($quantity < 1) {
        throw new Exception('Quantity must be greater than 0', 'INVALID_QUANTITY');
    }
    
    // 상품 정보 조회
    $item = sql_fetch("SELECT * FROM {$g5['prefix']}shop_item WHERE it_id = '" . sql_real_escape_string($item_id) . "'");
    
    if (!$item) {
        throw new Exception('Item not found', 'ITEM_NOT_FOUND');
    }
    
    if (!$item['it_use']) {
        throw new Exception('Item is not available', 'ITEM_NOT_AVAILABLE');
    }
    
    // 재고 확인
    if ($item['it_stock_qty'] < $quantity) {
        throw new Exception('Insufficient stock', 'INSUFFICIENT_STOCK');
    }
    
    // 옵션 확인
    if (($item['it_option1'] && empty($option1)) || ($item['it_option2'] && empty($option2)) || ($item['it_option3'] && empty($option3))) {
        throw new Exception('Options are required', 'OPTIONS_REQUIRED');
    }
    
    // 장바구니에 이미 있는지 확인
    $sql = "
        SELECT ct_id, ct_qty
        FROM {$g5['g5_shop_cart_table']}
        WHERE it_id = '" . sql_real_escape_string($item_id) . "'
          AND ct_opt1 = '" . sql_real_escape_string($option1) . "'
          AND ct_opt2 = '" . sql_real_escape_string($option2) . "'
          AND ct_opt3 = '" . sql_real_escape_string($option3) . "'
          AND od_id = '" . sql_real_escape_string($cart_id) . "'
          AND ct_status = '0'
    ";
    
    $existing = sql_fetch($sql);
    
    if ($existing) {
        // 이미 있으면 수량 증가
        $new_qty = $existing['ct_qty'] + $quantity;
        
        if ($item['it_stock_qty'] < $new_qty) {
            throw new Exception('Insufficient stock', 'INSUFFICIENT_STOCK');
        }
        
        $update_sql = "
            UPDATE {$g5['g5_shop_cart_table']}
            SET ct_qty = '" . sql_real_escape_string($new_qty) . "',
                ct_amount = ct_price * '" . sql_real_escape_string($new_qty) . "'
            WHERE ct_id = '" . $existing['ct_id'] . "'
        ";
        
        sql_query($update_sql);
        
        $cart_item_id = $existing['ct_id'];
    } else {
        // 새로 추가
        $insert_sql = "
            INSERT INTO {$g5['g5_shop_cart_table']}
            SET od_id = '" . sql_real_escape_string($cart_id) . "',
                mb_id = '',
                it_id = '" . sql_real_escape_string($item_id) . "',
                it_name = '" . sql_real_escape_string($item['it_name']) . "',
                it_price = '" . sql_real_escape_string($item['it_price']) . "',
                ct_stock = '" . sql_real_escape_string($item['it_stock_qty']) . "',
                ct_qty = '" . sql_real_escape_string($quantity) . "',
                ct_amount = ct_price * '" . sql_real_escape_string($quantity) . "',
                ct_opt1 = '" . sql_real_escape_string($option1) . "',
                ct_opt2 = '" . sql_real_escape_string($option2) . "',
                ct_opt3 = '" . sql_real_escape_string($option3) . "',
                ct_time = NOW(),
                ct_status = '0',
                ct_direct = '0',
                ct_select = '1'
        ";
        
        sql_query($insert_sql);
        
        $cart_item_id = sql_insert_id();
    }
    
    return array(
        'action' => 'add',
        'cart_id' => $cart_id,
        'cart_item_id' => $cart_item_id,
        'message' => 'Item added to cart successfully'
    );
}

/**
 * 장바구니 수정
 */
function update_cart() {
    global $g5;
    
    // 요청 파라미터
    $cart_id = isset($_GET['cart_id']) ? trim($_GET['cart_id']) : '';
    $cart_item_id = isset($_GET['cart_item_id']) ? trim($_GET['cart_item_id']) : '';
    $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 0;
    
    // 유효성 검사
    if (empty($cart_id)) {
        throw new Exception('Cart ID is required', 'CART_ID_REQUIRED');
    }
    
    if (empty($cart_item_id)) {
        throw new Exception('Cart item ID is required', 'CART_ITEM_ID_REQUIRED');
    }
    
    if ($quantity < 1) {
        throw new Exception('Quantity must be greater than 0', 'INVALID_QUANTITY');
    }
    
    // 장바구니 아이템 조회
    $sql = "
        SELECT ct.*, it.it_stock_qty
        FROM {$g5['g5_shop_cart_table']} ct
        LEFT JOIN {$g5['prefix']}shop_item it ON ct.it_id = it.it_id
        WHERE ct.ct_id = '" . sql_real_escape_string($cart_item_id) . "'
          AND ct.od_id = '" . sql_real_escape_string($cart_id) . "'
    ";
    
    $cart_item = sql_fetch($sql);
    
    if (!$cart_item) {
        throw new Exception('Cart item not found', 'CART_ITEM_NOT_FOUND');
    }
    
    // 재고 확인
    if ($cart_item['it_stock_qty'] < $quantity) {
        throw new Exception('Insufficient stock', 'INSUFFICIENT_STOCK');
    }
    
    // 수량 수정
    $update_sql = "
        UPDATE {$g5['g5_shop_cart_table']}
        SET ct_qty = '" . sql_real_escape_string($quantity) . "',
            ct_amount = ct_price * '" . sql_real_escape_string($quantity) . "'
        WHERE ct_id = '" . sql_real_escape_string($cart_item_id) . "'
    ";
    
    sql_query($update_sql);
    
    return array(
        'action' => 'update',
        'cart_id' => $cart_id,
        'cart_item_id' => $cart_item_id,
        'message' => 'Cart item updated successfully'
    );
}

/**
 * 장바구니 삭제
 */
function delete_from_cart() {
    global $g5;
    
    // 요청 파라미터
    $cart_id = isset($_GET['cart_id']) ? trim($_GET['cart_id']) : '';
    $cart_item_id = isset($_GET['cart_item_id']) ? trim($_GET['cart_item_id']) : '';
    
    // 유효성 검사
    if (empty($cart_id)) {
        throw new Exception('Cart ID is required', 'CART_ID_REQUIRED');
    }
    
    if (empty($cart_item_id)) {
        throw new Exception('Cart item ID is required', 'CART_ITEM_ID_REQUIRED');
    }
    
    // 장바구니 아이템 조회
    $sql = "
        SELECT ct_id
        FROM {$g5['g5_shop_cart_table']}
        WHERE ct_id = '" . sql_real_escape_string($cart_item_id) . "'
          AND od_id = '" . sql_real_escape_string($cart_id) . "'
    ";
    
    $cart_item = sql_fetch($sql);
    
    if (!$cart_item) {
        throw new Exception('Cart item not found', 'CART_ITEM_NOT_FOUND');
    }
    
    // 삭제
    $delete_sql = "
        DELETE FROM {$g5['g5_shop_cart_table']}
        WHERE ct_id = '" . sql_real_escape_string($cart_item_id) . "'
    ";
    
    sql_query($delete_sql);
    
    return array(
        'action' => 'delete',
        'cart_id' => $cart_id,
        'cart_item_id' => $cart_item_id,
        'message' => 'Cart item deleted successfully'
    );
}

/**
 * 장바구니 ID 생성
 */
function generate_cart_id() {
    return 'cart_' . time() . '_' . mt_rand(1000, 9999);
}

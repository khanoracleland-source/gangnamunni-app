<?php
/**
 * 상품 조회 API
 * 쇼핑몰 상품 정보를 조회하는 API
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// 쇼핑몰 설정 파일 포함
if (file_exists(G5_PATH . '/shop/_common.php')) {
    include_once G5_PATH . '/shop/_common.php';
}

// 요청 메서드 확인 (GET만 허용)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Only GET method is allowed', 405, 'METHOD_NOT_ALLOWED');
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
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$item_id = isset($_GET['item_id']) ? trim($_GET['item_id']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$ca_id = isset($_GET['ca_id']) ? trim($_GET['ca_id']) : '';

// 요청 유효성 검사
$errors = array();

if (!in_array($action, array('list', 'detail', 'stock'))) {
    $errors['action'] = 'Invalid action. Supported: list, detail, stock';
}

if (in_array($action, array('detail', 'stock')) && empty($item_id)) {
    $errors['item_id'] = 'Item ID is required';
}

if ($page < 1) {
    $errors['page'] = 'Page must be greater than 0';
}

if ($limit < 1 || $limit > 100) {
    $errors['limit'] = 'Limit must be between 1 and 100';
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// 로깅 시작
$log_start_time = microtime(true);

try {
    global $g5;
    
    // 쇼핑몰 상품 테이블 확인
    if (!isset($g5['g5_shop_item_table'])) {
        $g5['g5_shop_item_table'] = G5_TABLE_PREFIX.'shop_item';
    }
    
    if (!isset($g5['g5_shop_category_table'])) {
        $g5['g5_shop_category_table'] = G5_TABLE_PREFIX.'shop_category';
    }
    
    $result = array();
    
    switch ($action) {
        case 'list':
            // 상품 목록 조회
            $result = get_item_list($page, $limit, $ca_id);
            break;
        case 'detail':
            // 상품 상세 조회
            $result = get_item_detail($item_id);
            break;
        case 'stock':
            // 재고 확인
            $result = check_item_stock($item_id);
            break;
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/pg/item.php',
        'GET',
        $request_ip,
        array('action' => $action, 'item_id' => $item_id, 'page' => $page, 'limit' => $limit, 'ca_id' => $ca_id),
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
        'api/pg/item.php',
        'GET',
        $request_ip,
        array('action' => $action, 'item_id' => $item_id, 'page' => $page, 'limit' => $limit, 'ca_id' => $ca_id),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * 상품 목록 조회
 */
function get_item_list($page, $limit, $ca_id) {
    global $g5;
    
    $offset = ($page - 1) * $limit;
    
    // WHERE 조건
    $where = "WHERE it_use = 1"; // 판매 중인 상품만
    
    if ($ca_id) {
        // 카테고리별 조회
        $where .= " AND ca_id = '" . sql_real_escape_string($ca_id) . "'";
    }
    
    // 총 개수 조회
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['g5_shop_item_table']} $where";
    $total_count = sql_fetch($sql)['cnt'];
    
    // 상품 목록 조회
    $sql = "
        SELECT
            it_id,
            ca_id,
            it_name,
            it_basic,
            it_price,
            it_stock_qty,
            it_sell_cnt,
            it_view,
            it_regist_time,
            it_update_time,
            it_sc_type,
            it_sc_method,
            it_sc_price,
            it_tel_inq
        FROM {$g5['g5_shop_item_table']}
        $where
        ORDER BY it_order, it_id DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $result = sql_query($sql);
    
    $items = array();
    while ($row = sql_fetch_array($result)) {
        $items[] = array(
            'item_id' => $row['it_id'],
            'category_id' => $row['ca_id'],
            'name' => $row['it_name'],
            'summary' => $row['it_basic'],
            'price' => (int)$row['it_price'],
            'stock_quantity' => (int)$row['it_stock_qty'],
            'sold_count' => (int)$row['it_sell_cnt'],
            'view_count' => (int)$row['it_view'],
            'registered_at' => $row['it_regist_time'],
            'updated_at' => $row['it_update_time'],
            'shipping_type' => $row['it_sc_type'], // 배송비 유형
            'shipping_method' => $row['it_sc_method'], // 배송 방법
            'shipping_price' => (int)$row['it_sc_price'], // 배송비
            'tel_inquiry' => (bool)$row['it_tel_inq'] // 전화문의 여부
        );
    }
    
    // 총 페이지 수 계산
    $total_pages = ceil($total_count / $limit);
    
    return array(
        'action' => 'list',
        'items' => $items,
        'pagination' => array(
            'page' => $page,
            'limit' => $limit,
            'total_count' => (int)$total_count,
            'total_pages' => $total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        )
    );
}

/**
 * 상품 상세 조회
 */
function get_item_detail($item_id) {
    global $g5;
    
    // 상품 정보 조회
    $sql = "
        SELECT
            it_id,
            ca_id,
            ca_name,
            it_name,
            it_basic,
            it_explan,
            it_price,
            it_stock_qty,
            it_sell_cnt,
            it_view,
            it_regist_time,
            it_update_time,
            it_sc_type,
            it_sc_method,
            it_sc_price,
            it_tel_inq,
            it_option1,
            it_option2,
            it_option3,
            it_option4,
            it_option5,
            it_option1_subject,
            it_option2_subject,
            it_option3_subject,
            it_option4_subject,
            it_option5_subject,
            it_img1,
            it_img2,
            it_img3,
            it_img4,
            it_img5,
            it_img6,
            it_img7,
            it_img8,
            it_img9,
            it_img10
        FROM {$g5['g5_shop_item_table']}
        LEFT JOIN {$g5['g5_shop_category_table']} ON {$g5['g5_shop_item_table']}.ca_id = {$g5['g5_shop_category_table']}.ca_id
        WHERE it_id = '" . sql_real_escape_string($item_id) . "'
          AND it_use = 1
    ";
    
    $row = sql_fetch($sql);
    
    if (!$row) {
        throw new Exception('Item not found', 'ITEM_NOT_FOUND');
    }
    
    // 이미지 URL 생성
    $images = array();
    for ($i = 1; $i <= 10; $i++) {
        $img_field = 'it_img' . $i;
        if ($row[$img_field]) {
            $images[] = G5_SHOP_URL . '/data/item/' . $row[$img_field];
        }
    }
    
    // 옵션 정보
    $options = array();
    for ($i = 1; $i <= 5; $i++) {
        $option_field = 'it_option' . $i;
        $subject_field = 'it_option' . $i . '_subject';
        if ($row[$option_field]) {
            $option_values = explode(',', $row[$option_field]);
            $options[] = array(
                'subject' => $row[$subject_field],
                'values' => $option_values
            );
        }
    }
    
    return array(
        'action' => 'detail',
        'item' => array(
            'item_id' => $row['it_id'],
            'category_id' => $row['ca_id'],
            'category_name' => $row['ca_name'],
            'name' => $row['it_name'],
            'summary' => $row['it_basic'],
            'description' => $row['it_explan'],
            'price' => (int)$row['it_price'],
            'stock_quantity' => (int)$row['it_stock_qty'],
            'sold_count' => (int)$row['it_sell_cnt'],
            'view_count' => (int)$row['it_view'],
            'registered_at' => $row['it_regist_time'],
            'updated_at' => $row['it_update_time'],
            'shipping_type' => $row['it_sc_type'],
            'shipping_method' => $row['it_sc_method'],
            'shipping_price' => (int)$row['it_sc_price'],
            'tel_inquiry' => (bool)$row['it_tel_inq'],
            'images' => $images,
            'options' => $options
        )
    );
}

/**
 * 재고 확인
 */
function check_item_stock($item_id) {
    global $g5;
    
    // 상품 정보 조회
    $sql = "
        SELECT it_id, it_name, it_stock_qty, it_stock_sms, it_use
        FROM {$g5['g5_shop_item_table']}
        WHERE it_id = '" . sql_real_escape_string($item_id) . "'
    ";
    
    $row = sql_fetch($sql);
    
    if (!$row) {
        throw new Exception('Item not found', 'ITEM_NOT_FOUND');
    }
    
    $in_stock = $row['it_stock_qty'] > 0;
    
    return array(
        'action' => 'stock',
        'item' => array(
            'item_id' => $row['it_id'],
            'name' => $row['it_name'],
            'stock_quantity' => (int)$row['it_stock_qty'],
            'in_stock' => $in_stock,
            'use' => (bool)$row['it_use']
        )
    );
}

<?php
/**
 * API 인증 확장 파일
 * API 인증 관련 설정 및 함수 정의
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

if (!defined('G5_PATH')) {
    exit;
}

// API 인증 관련 상수 정의
if (!defined('G5_API_SECRET')) {
    define('G5_API_SECRET', 'default_api_secret_change_me_please');
}

if (!defined('G5_WEBHOOK_SECRET')) {
    define('G5_WEBHOOK_SECRET', 'default_webhook_secret_change_me_please');
}

// Payment API is fail-closed until a real PG adapter is installed.
if (!defined('G5_PG_PAYMENTS_ENABLED')) {
    define('G5_PG_PAYMENTS_ENABLED', false);
}

// Comma-separated callback hosts. Empty means callbacks are disabled.
if (!defined('G5_PG_CALLBACK_HOSTS')) {
    define('G5_PG_CALLBACK_HOSTS', '');
}

if (!defined('G5_PG_WEBHOOK_TOLERANCE')) {
    define('G5_PG_WEBHOOK_TOLERANCE', 300);
}

if (!defined('G5_API_ALLOWED_ORIGINS')) {
    define('G5_API_ALLOWED_ORIGINS', '');
}

// API 헤더 관련 상수
if (!defined('G5_API_KEY_HEADER')) {
    define('G5_API_KEY_HEADER', 'X-API-Key');
}

if (!defined('G5_API_SECRET_HEADER')) {
    define('G5_API_SECRET_HEADER', 'X-API-Secret');
}

// API 인증 헬퍼 함수

/**
 * API 키 생성
 */
function generate_api_key() {
    return bin2hex(random_bytes(32));
}

/**
 * API 시크릿 생성
 */
function generate_api_secret() {
    return bin2hex(random_bytes(32));
}

/**
 * API 키 데이터베이스 테이블 이름 가져오기
 */
function get_api_key_table() {
    global $g5;
    
    if (!isset($g5['api_key_table'])) {
        $g5['api_key_table'] = G5_TABLE_PREFIX.'api_keys';
    }
    
    return $g5['api_key_table'];
}

/**
 * API IP 화이트리스트 테이블 이름 가져오기
 */
function get_api_ip_table() {
    global $g5;
    
    if (!isset($g5['api_ip_table'])) {
        $g5['api_ip_table'] = G5_TABLE_PREFIX.'api_ips';
    }
    
    return $g5['api_ip_table'];
}

/**
 * API 로그 테이블 이름 가져오기
 */
function get_api_log_table() {
    global $g5;
    
    if (!isset($g5['api_log_table'])) {
        $g5['api_log_table'] = G5_TABLE_PREFIX.'api_logs';
    }
    
    return $g5['api_log_table'];
}

/**
 * API 키 생성
 */
function create_api_key($name, $type = 'all', $expired_at = null, $ip_whitelist = array()) {
    global $g5;
    
    $api_key = generate_api_key();
    $api_secret = generate_api_secret();
    $api_secret_hash = password_hash($api_secret, PASSWORD_DEFAULT);
    sql_query("ALTER TABLE " . get_api_key_table() . " MODIFY ak_secret varchar(255) NOT NULL", true);
    
    $sql = "
        INSERT INTO " . get_api_key_table() . "
        SET ak_name = '" . sql_real_escape_string($name) . "',
            ak_key = '" . sql_real_escape_string($api_key) . "',
            ak_secret = '" . sql_real_escape_string($api_secret_hash) . "',
            ak_type = '" . sql_real_escape_string($type) . "',
            ak_ip_whitelist = " . (!empty($ip_whitelist) ? "'" . sql_real_escape_string(json_encode($ip_whitelist, JSON_UNESCAPED_UNICODE)) . "'" : "NULL") . ",
            ak_status = 1,
            ak_created_at = NOW(),
            ak_expired_at = " . ($expired_at ? "'" . sql_real_escape_string($expired_at) . "'" : "NULL") . "
    ";
    
    $result = sql_query($sql);
    
    if ($result) {
        $ak_id = sql_insert_id();
        
        // IP 화이트리스트 추가
        if (!empty($ip_whitelist)) {
            foreach ($ip_whitelist as $ip) {
                if (is_array($ip)) {
                    add_api_ip_whitelist($ak_id, $ip['ip'], isset($ip['description']) ? $ip['description'] : '');
                } else {
                    add_api_ip_whitelist($ak_id, $ip, '');
                }
            }
        }
        
        return array(
            'ak_id' => $ak_id,
            'api_key' => $api_key,
            'api_secret' => $api_secret
        );
    }
    
    return false;
}

/**
 * API 키 조회
 */
function get_api_key($ak_id) {
    global $g5;
    
    $sql = "
        SELECT *
        FROM " . get_api_key_table() . "
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ";
    
    return sql_fetch($sql);
}

/**
 * API 키 조회 (키로)
 */
function get_api_key_by_key($api_key) {
    global $g5;
    
    $sql = "
        SELECT *
        FROM " . get_api_key_table() . "
        WHERE ak_key = '" . sql_real_escape_string($api_key) . "'
    ";
    
    return sql_fetch($sql);
}

/**
 * API 키 검증
 */
function verify_api_key($api_key, $api_secret) {
    $api_key_info = get_api_key_by_key($api_key);
    
    if (!$api_key_info) {
        return false;
    }
    
    if ($api_key_info['ak_secret'] !== $api_secret) {
        return false;
    }
    
    if ($api_key_info['ak_status'] != 1) {
        return false;
    }
    
    // 만료 확인
    if ($api_key_info['ak_expired_at'] && strtotime($api_key_info['ak_expired_at']) < time()) {
        return false;
    }
    
    return $api_key_info;
}

/**
 * API 키 업데이트
 */
function update_api_key($ak_id, $data) {
    global $g5;
    
    $update_fields = array();
    
    if (isset($data['name'])) {
        $update_fields[] = "ak_name = '" . sql_real_escape_string($data['name']) . "'";
    }
    
    if (isset($data['status'])) {
        $update_fields[] = "ak_status = '" . sql_real_escape_string($data['status']) . "'";
    }
    
    if (isset($data['expired_at'])) {
        $update_fields[] = "ak_expired_at = " . ($data['expired_at'] ? "'" . sql_real_escape_string($data['expired_at']) . "'" : "NULL");
    }
    
    if (isset($data['ip_whitelist'])) {
        $update_fields[] = "ak_ip_whitelist = " . (!empty($data['ip_whitelist']) ? "'" . sql_real_escape_string(json_encode($data['ip_whitelist'], JSON_UNESCAPED_UNICODE)) . "'" : "NULL");
    }
    
    if (empty($update_fields)) {
        return false;
    }
    
    $sql = "
        UPDATE " . get_api_key_table() . "
        SET " . implode(', ', $update_fields) . "
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ";
    
    return sql_query($sql);
}

/**
 * API 키 삭제
 */
function delete_api_key($ak_id) {
    global $g5;
    
    // 연결된 IP 화이트리스트 삭제
    sql_query("DELETE FROM " . get_api_ip_table() . " WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
    
    // API 키 삭제
    return sql_query("DELETE FROM " . get_api_key_table() . " WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
}

/**
 * API 시크릿 재발급
 */
function regenerate_api_secret($ak_id) {
    global $g5;
    
    $api_secret = generate_api_secret();
    
    $sql = "
        UPDATE " . get_api_key_table() . "
        SET ak_secret = '" . sql_real_escape_string($api_secret) . "'
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ";
    
    if (sql_query($sql)) {
        return $api_secret;
    }
    
    return false;
}

/**
 * API IP 화이트리스트 추가
 */
function add_api_ip_whitelist($ak_id, $ip, $description = '') {
    global $g5;
    
    $sql = "
        INSERT INTO " . get_api_ip_table() . "
        SET ak_id = '" . sql_real_escape_string($ak_id) . "',
            ai_ip = '" . sql_real_escape_string($ip) . "',
            ai_description = '" . sql_real_escape_string($description) . "',
            ai_status = 1,
            ai_created_at = NOW()
    ";
    
    return sql_query($sql);
}

/**
 * API IP 화이트리스트 조회
 */
function get_api_ip_whitelist($ak_id) {
    global $g5;
    
    $sql = "
        SELECT *
        FROM " . get_api_ip_table() . "
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
          AND ai_status = 1
        ORDER BY ai_id ASC
    ";
    
    $result = sql_query($sql);
    
    $whitelist = array();
    while ($row = sql_fetch_array($result)) {
        $whitelist[] = array(
            'ai_id' => $row['ai_id'],
            'ip' => $row['ai_ip'],
            'description' => $row['ai_description']
        );
    }
    
    return $whitelist;
}

/**
 * API IP 화이트리스트 검증
 */
function verify_api_ip_whitelist($ak_id, $ip) {
    $whitelist = get_api_ip_whitelist($ak_id);
    
    // 화이트리스트가 비어있으면 모든 IP 허용
    if (empty($whitelist)) {
        return true;
    }
    
    foreach ($whitelist as $whitelist_ip) {
        // CIDR 지원
        if (cidr_match($ip, $whitelist_ip['ip'])) {
            return true;
        }
        
        // 정확한 IP 매칭
        if ($ip === $whitelist_ip['ip']) {
            return true;
        }
    }
    
    return false;
}

/**
 * CIDR 매칭 검사
 */
function cidr_match($ip, $cidr) {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    
    list($subnet, $mask) = explode('/', $cidr);
    
    if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet);
}

/**
 * API 로그 기록
 */
function log_api_request($ak_id, $endpoint, $method, $request_ip, $request_data = null, $response_code = null, $response_data = null, $error = null, $execution_time = null) {
    global $g5;
    
    $sql = "
        INSERT INTO " . get_api_log_table() . "
        SET ak_id = " . ($ak_id ? "'" . sql_real_escape_string($ak_id) . "'" : "NULL") . ",
            al_endpoint = '" . sql_real_escape_string($endpoint) . "',
            al_method = '" . sql_real_escape_string($method) . "',
            al_request_ip = '" . sql_real_escape_string($request_ip) . "',
            al_request_data = " . ($request_data ? "'" . sql_real_escape_string(json_encode($request_data, JSON_UNESCAPED_UNICODE)) . "'" : "NULL") . ",
            al_response_code = " . ($response_code !== null ? "'" . sql_real_escape_string($response_code) . "'" : "NULL") . ",
            al_response_data = " . ($response_data ? "'" . sql_real_escape_string(json_encode($response_data, JSON_UNESCAPED_UNICODE)) . "'" : "NULL") . ",
            al_error = " . ($error ? "'" . sql_real_escape_string($error) . "'" : "NULL") . ",
            al_execution_time = " . ($execution_time !== null ? "'" . sql_real_escape_string($execution_time) . "'" : "NULL") . ",
            al_created_at = NOW()
    ";
    
    $result = sql_query($sql);
    
    if ($result) {
        // API 키의 마지막 사용 시간 업데이트
        if ($ak_id) {
            sql_query("UPDATE " . get_api_key_table() . " SET ak_last_used = NOW() WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
        }
        
        return sql_insert_id();
    }
    
    return false;
}

/**
 * 클라이언트 IP 가져오기
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return $ip;
}

/**
 * API 인증 헤더 가져오기
 */
function get_api_auth_headers() {
    $headers = getallheaders();
    
    return array(
        'api_key' => isset($headers[G5_API_KEY_HEADER]) ? $headers[G5_API_KEY_HEADER] : (isset($headers[strtolower(G5_API_KEY_HEADER)]) ? $headers[strtolower(G5_API_KEY_HEADER)] : null),
        'api_secret' => isset($headers[G5_API_SECRET_HEADER]) ? $headers[G5_API_SECRET_HEADER] : (isset($headers[strtolower(G5_API_SECRET_HEADER)]) ? $headers[strtolower(G5_API_SECRET_HEADER)] : null)
    );
}

/**
 * API 인증 수행
 */
function authenticate_api_request() {
    $auth = get_api_auth_headers();
    
    if (!$auth['api_key'] || !$auth['api_secret']) {
        return false;
    }
    
    $api_key_info = verify_api_key($auth['api_key'], $auth['api_secret']);
    
    if (!$api_key_info) {
        return false;
    }
    
    // IP 화이트리스트 확인
    $client_ip = get_client_ip();
    if (!verify_api_ip_whitelist($api_key_info['ak_id'], $client_ip)) {
        return false;
    }
    
    return $api_key_info;
}

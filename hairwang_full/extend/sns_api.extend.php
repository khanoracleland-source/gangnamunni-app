<?php
/**
 * SNS API 확장 파일
 * SNS API 관련 설정 및 함수 정의
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

if (!defined('G5_PATH')) {
    exit;
}

// SNS API 관련 상수 정의
if (!defined('G5_SNS_API_DIR')) {
    define('G5_SNS_API_DIR', G5_PATH . '/api/sns');
}

if (!defined('G5_SNS_API_URL')) {
    define('G5_SNS_API_URL', G5_URL . '/api/sns');
}

// 데이터베이스 테이블 정의
if (!isset($g5['sns_api_log_table'])) {
    $g5['sns_api_log_table'] = G5_TABLE_PREFIX.'sns_api_logs';
}

// SNS 로그인 API 헬퍼 함수

/**
 * SNS 로그인 API URL 생성
 */
function get_sns_login_api_url($provider) {
    return G5_SNS_API_URL . '/login.php';
}

/**
 * SNS 토큰 API URL 생성
 */
function get_sns_token_api_url($action = 'issue') {
    return G5_SNS_API_URL . '/token.php';
}

/**
 * SNS 콜백 API URL 생성
 */
function get_sns_callback_api_url($action = 'unlink') {
    return G5_SNS_API_URL . '/callback.php';
}

/**
 * 외부 사이트용 SNS 로그인 링크 생성
 */
function get_external_sns_login_link($provider, $api_key, $api_secret, $redirect_url = '') {
    $params = array(
        'provider' => $provider,
        'redirect_url' => $redirect_url
    );
    
    $url = G5_SNS_API_URL . '/login.php?' . http_build_query($params);
    
    return $url;
}

/**
 * SNS 로그인 API 요청 보내기
 */
function request_sns_login_api($api_key, $api_secret, $provider, $token, $auto_register = true, $custom_fields = array()) {
    $url = G5_SNS_API_URL . '/login.php';
    
    $data = array(
        'provider' => $provider,
        'token' => $token,
        'auto_register' => $auto_register,
        'custom_fields' => $custom_fields
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
 * SNS 토큰 발급 요청
 */
function request_sns_token_issue($api_key, $api_secret, $mb_id) {
    $url = G5_SNS_API_URL . '/token.php';
    
    $data = array(
        'action' => 'issue',
        'mb_id' => $mb_id
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
 * SNS 연동 해제 요청
 */
function request_sns_unlink($api_key, $api_secret, $provider, $mb_id) {
    $url = G5_SNS_API_URL . '/callback.php';
    
    $data = array(
        'provider' => $provider,
        'action' => 'unlink',
        'mb_id' => $mb_id
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

// SNS API 로깅
function log_sns_api_request($provider, $action, $success, $error_code = null, $error_message = null) {
    global $g5;
    
    if (!isset($g5['sns_api_log_table'])) {
        $g5['sns_api_log_table'] = G5_TABLE_PREFIX.'sns_api_logs';
    }
    
    $sql = "
        INSERT INTO {$g5['sns_api_log_table']}
        SET sl_provider = '" . sql_real_escape_string($provider) . "',
            sl_action = '" . sql_real_escape_string($action) . "',
            sl_success = '" . sql_real_escape_string($success ? 1 : 0) . "',
            sl_error_code = " . ($error_code ? "'" . sql_real_escape_string($error_code) . "'" : "NULL") . ",
            sl_error_message = " . ($error_message ? "'" . sql_real_escape_string($error_message) . "'" : "NULL") . ",
            sl_created_at = NOW()
    ";
    
    sql_query($sql);
}

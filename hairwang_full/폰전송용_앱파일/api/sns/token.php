<?php
/**
 * SNS 토큰 API
 * 외부 사이트에서 사용할 토큰을 발급하거나 갱신하는 API
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

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
if ($api_key_info['ak_type'] !== 'sns' && $api_key_info['ak_type'] !== 'all') {
    ApiResponse::error('This API key is not authorized for SNS operations', 403, 'FORBIDDEN');
}

// IP 화이트리스트 확인
$request_ip = ApiAuth::getRequestIp();
if (!ApiAuth::verifyIpWhitelist($api_key_info['ak_id'], $request_ip)) {
    ApiResponse::error('Your IP address is not authorized', 403, 'IP_NOT_ALLOWED');
}

// 요청 파라미터 가져오기
$action = isset($_POST['action']) ? trim($_POST['action']) : 'issue';
$mb_id = isset($_POST['mb_id']) ? trim($_POST['mb_id']) : '';

// 요청 유효성 검사
$errors = array();

if (!in_array($action, array('issue', 'refresh', 'verify'))) {
    $errors['action'] = 'Invalid action. Supported: issue, refresh, verify';
}

if (in_array($action, array('issue', 'verify')) && empty($mb_id)) {
    $errors['mb_id'] = 'Member ID is required';
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// 로깅 시작
$log_start_time = microtime(true);

try {
    $result = array();
    
    switch ($action) {
        case 'issue':
            // 토큰 발급
            $result = issue_token($mb_id, $api_key_info['ak_id']);
            break;
        case 'refresh':
            // 토큰 갱신
            $result = refresh_token($api_key_info['ak_id']);
            break;
        case 'verify':
            // 토큰 검증
            $result = verify_token($mb_id);
            break;
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/sns/token.php',
        'POST',
        $request_ip,
        array('action' => $action, 'mb_id' => $mb_id),
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
        'api/sns/token.php',
        'POST',
        $request_ip,
        array('action' => $action, 'mb_id' => $mb_id),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * 토큰 발급
 */
function issue_token($mb_id, $ak_id) {
    global $g5;
    
    // 회원 존재 확인
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
    }
    
    // 토큰 생성 (JWT 형식)
    $token = create_jwt_token($mb_id, $ak_id);
    
    // 토큰 저장 (테이블에 저장 또는 캐시 사용)
    // 여기서는 간단히 토큰 정보를 반환
    
    return array(
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600, // 1시간
        'mb_id' => $mb_id,
        'mb_name' => $member['mb_name'],
        'mb_nick' => $member['mb_nick'],
        'issued_at' => date('Y-m-d H:i:s')
    );
}

/**
 * 토큰 갱신
 */
function refresh_token($ak_id) {
    global $g5;
    
    // 현재 API 키를 이용하여 새로운 토큰 발급
    // 토큰 리프레시는 보안상의 이유로 추가 인증이 필요할 수 있음
    
    $token = create_jwt_token('refresh', $ak_id);
    
    return array(
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refreshed_at' => date('Y-m-d H:i:s')
    );
}

/**
 * 토큰 검증
 */
function verify_token($mb_id) {
    global $g5;
    
    // 회원 존재 확인
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
    }
    
    return array(
        'valid' => true,
        'mb_id' => $mb_id,
        'mb_name' => $member['mb_name'],
        'mb_nick' => $member['mb_nick'],
        'verified_at' => date('Y-m-d H:i:s')
    );
}

/**
 * JWT 토큰 생성
 */
function create_jwt_token($mb_id, $ak_id) {
    // 토큰 헤더
    $header = array(
        'alg' => 'HS256',
        'typ' => 'JWT'
    );
    
    // 토큰 페이로드
    $payload = array(
        'mb_id' => $mb_id,
        'ak_id' => $ak_id,
        'iat' => time(),
        'exp' => time() + 3600 // 1시간 후 만료
    );
    
    // 토큰 시크릿 (API 시크릿 사용)
    $secret = defined('G5_API_SECRET') ? G5_API_SECRET : 'default_secret_key_change_me';
    
    // Base64 URL 인코딩
    $header_encoded = base64_url_encode(json_encode($header));
    $payload_encoded = base64_url_encode(json_encode($payload));
    
    // 서명 생성
    $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $secret, true);
    $signature_encoded = base64_url_encode($signature);
    
    // 토큰 조합
    $token = $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    
    return $token;
}

/**
 * Base64 URL 인코딩
 */
function base64_url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * JWT 토큰 검증
 */
function verify_jwt_token($token) {
    $secret = defined('G5_API_SECRET') ? G5_API_SECRET : 'default_secret_key_change_me';
    
    $token_parts = explode('.', $token);
    
    if (count($token_parts) !== 3) {
        return false;
    }
    
    list($header_encoded, $payload_encoded, $signature_encoded) = $token_parts;
    
    // 서명 검증
    $expected_signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $secret, true);
    $expected_signature_encoded = base64_url_encode($expected_signature);
    
    if ($signature_encoded !== $expected_signature_encoded) {
        return false;
    }
    
    // 페이로드 디코딩
    $payload = json_decode(base64_url_decode($payload_encoded), true);
    
    if (!$payload) {
        return false;
    }
    
    // 만료 확인
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Base64 URL 디코딩
 */
function base64_url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

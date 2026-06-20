<?php
/**
 * SNS 콜백 API
 * SNS 서버로부터 콜백을 처리하는 API
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
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$mb_id = isset($_POST['mb_id']) ? trim($_POST['mb_id']) : '';
$data = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();

// 요청 유효성 검사
$errors = array();

if (empty($provider)) {
    $errors['provider'] = 'Provider is required';
}

if (empty($action)) {
    $errors['action'] = 'Action is required';
}

// 지원하는 SNS 제공자 목록
$supported_providers = array('naver', 'kakao', 'google', 'facebook', 'twitter', 'payco');
if (!in_array($provider, $supported_providers)) {
    $errors['provider'] = 'Invalid provider. Supported: ' . implode(', ', $supported_providers);
}

// 지원하는 액션 목록
$supported_actions = array('unlink', 'update_profile', 'delete_account');
if (!in_array($action, $supported_actions)) {
    $errors['action'] = 'Invalid action. Supported: ' . implode(', ', $supported_actions);
}

if (in_array($action, array('unlink', 'update_profile', 'delete_account')) && empty($mb_id)) {
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
        case 'unlink':
            // SNS 연동 해제
            $result = unlink_sns($provider, $mb_id, $api_key_info['ak_id']);
            break;
        case 'update_profile':
            // SNS 프로필 업데이트
            $result = update_sns_profile($provider, $mb_id, $data, $api_key_info['ak_id']);
            break;
        case 'delete_account':
            // 계정 삭제
            $result = delete_sns_account($provider, $mb_id, $api_key_info['ak_id']);
            break;
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/sns/callback.php',
        'POST',
        $request_ip,
        array('provider' => $provider, 'action' => $action, 'mb_id' => $mb_id),
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
        'api/sns/callback.php',
        'POST',
        $request_ip,
        array('provider' => $provider, 'action' => $action, 'mb_id' => $mb_id),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * SNS 연동 해제
 */
function unlink_sns($provider, $mb_id, $ak_id) {
    global $g5;
    
    // 회원 존재 확인
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
    }
    
    // SNS 프로필 테이블 확인
    if (!isset($g5['social_profile_table'])) {
        $g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';
    }
    
    // SNS 연동 정보 조회
    $sql = "
        SELECT * FROM {$g5['social_profile_table']}
        WHERE provider = '" . sql_real_escape_string($provider) . "'
          AND mb_id = '" . sql_real_escape_string($mb_id) . "'
    ";
    
    $social_profile = sql_fetch($sql);
    
    if (!$social_profile) {
        throw new Exception('SNS connection not found', 'CONNECTION_NOT_FOUND');
    }
    
    // SNS 연동 정보 삭제
    $delete_sql = "
        DELETE FROM {$g5['social_profile_table']}
        WHERE provider = '" . sql_real_escape_string($provider) . "'
          AND mb_id = '" . sql_real_escape_string($mb_id) . "'
    ";
    
    sql_query($delete_sql);
    
    // 로그 기록
    log_sns_action($ak_id, $provider, 'unlink', $mb_id, null, true, null, null);
    
    return array(
        'status' => 'success',
        'message' => 'SNS connection unlinked successfully',
        'provider' => $provider,
        'mb_id' => $mb_id,
        'unlinked_at' => date('Y-m-d H:i:s')
    );
}

/**
 * SNS 프로필 업데이트
 */
function update_sns_profile($provider, $mb_id, $data, $ak_id) {
    global $g5;
    
    // 회원 존재 확인
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
    }
    
    // SNS 프로필 테이블 확인
    if (!isset($g5['social_profile_table'])) {
        $g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';
    }
    
    // SNS 연동 정보 조회
    $sql = "
        SELECT * FROM {$g5['social_profile_table']}
        WHERE provider = '" . sql_real_escape_string($provider) . "'
          AND mb_id = '" . sql_real_escape_string($mb_id) . "'
    ";
    
    $social_profile = sql_fetch($sql);
    
    if (!$social_profile) {
        throw new Exception('SNS connection not found', 'CONNECTION_NOT_FOUND');
    }
    
    // 업데이트할 데이터 준비
    $update_data = array();
    
    if (isset($data['photo_url'])) {
        $update_data['mp_photo'] = "'" . sql_real_escape_string($data['photo_url']) . "'";
    }
    
    if (isset($data['extra'])) {
        $existing_extra = $social_profile['mp_extra'] ? json_decode($social_profile['mp_extra'], true) : array();
        $new_extra = array_merge($existing_extra, $data['extra']);
        $update_data['mp_extra'] = "'" . sql_real_escape_string(json_encode($new_extra, JSON_UNESCAPED_UNICODE)) . "'";
    }
    
    // 업데이트 실행
    if (!empty($update_data)) {
        $update_sql = "
            UPDATE {$g5['social_profile_table']}
            SET " . implode(', ', $update_data) . "
            WHERE provider = '" . sql_real_escape_string($provider) . "'
              AND mb_id = '" . sql_real_escape_string($mb_id) . "'
        ";
        
        sql_query($update_sql);
    }
    
    // 로그 기록
    log_sns_action($ak_id, $provider, 'update_profile', $mb_id, null, true, null, null);
    
    return array(
        'status' => 'success',
        'message' => 'SNS profile updated successfully',
        'provider' => $provider,
        'mb_id' => $mb_id,
        'updated_at' => date('Y-m-d H:i:s')
    );
}

/**
 * SNS 계정 삭제
 */
function delete_sns_account($provider, $mb_id, $ak_id) {
    global $g5;
    
    // 회원 존재 확인
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
    }
    
    // SNS 프로필 테이블 확인
    if (!isset($g5['social_profile_table'])) {
        $g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';
    }
    
    // SNS 연동 정보 조회
    $sql = "
        SELECT * FROM {$g5['social_profile_table']}
        WHERE provider = '" . sql_real_escape_string($provider) . "'
          AND mb_id = '" . sql_real_escape_string($mb_id) . "'
    ";
    
    $social_profile = sql_fetch($sql);
    
    if (!$social_profile) {
        throw new Exception('SNS connection not found', 'CONNECTION_NOT_FOUND');
    }
    
    // 회원의 다른 SNS 연동 확인
    $sql = "
        SELECT COUNT(*) as cnt
        FROM {$g5['social_profile_table']}
        WHERE mb_id = '" . sql_real_escape_string($mb_id) . "'
    ";
    $result = sql_fetch($sql);
    
    if ($result['cnt'] > 1) {
        // 다른 SNS 연동이 있으면 해당 연동만 삭제
        $delete_sql = "
            DELETE FROM {$g5['social_profile_table']}
            WHERE provider = '" . sql_real_escape_string($provider) . "'
              AND mb_id = '" . sql_real_escape_string($mb_id) . "'
        ";
        sql_query($delete_sql);
        
        $action = 'unlink';
    } else {
        // 다른 SNS 연동이 없으면 회원 탈퇴 처리
        // 회원 정보 삭제 (또는 탈퇴 상태로 변경)
        $delete_member_sql = "
            DELETE FROM {$g5['member_table']}
            WHERE mb_id = '" . sql_real_escape_string($mb_id) . "'
        ";
        sql_query($delete_member_sql);
        
        // SNS 연동 정보 삭제
        $delete_sns_sql = "
            DELETE FROM {$g5['social_profile_table']}
            WHERE mb_id = '" . sql_real_escape_string($mb_id) . "'
        ";
        sql_query($delete_sns_sql);
        
        $action = 'delete_account';
    }
    
    // 로그 기록
    log_sns_action($ak_id, $provider, $action, $mb_id, null, true, null, null);
    
    return array(
        'status' => 'success',
        'message' => $action === 'delete_account' ? 'Account deleted successfully' : 'SNS connection unlinked successfully',
        'provider' => $provider,
        'mb_id' => $mb_id,
        'action' => $action,
        'deleted_at' => date('Y-m-d H:i:s')
    );
}

/**
 * SNS 액션 로그 기록
 */
function log_sns_action($ak_id, $provider, $action, $mb_id, $identifier, $success, $error_code, $error_message) {
    global $g5;
    
    $sql = "
        INSERT INTO {$g5['prefix']}sns_api_logs
        SET ak_id = '" . sql_real_escape_string($ak_id) . "',
            sl_provider = '" . sql_real_escape_string($provider) . "',
            sl_action = '" . sql_real_escape_string($action) . "',
            sl_mb_id = " . ($mb_id ? "'" . sql_real_escape_string($mb_id) . "'" : "NULL") . ",
            sl_identifier = " . ($identifier ? "'" . sql_real_escape_string($identifier) . "'" : "NULL") . ",
            sl_success = '" . sql_real_escape_string($success ? 1 : 0) . "',
            sl_error_code = " . ($error_code ? "'" . sql_real_escape_string($error_code) . "'" : "NULL") . ",
            sl_error_message = " . ($error_message ? "'" . sql_real_escape_string($error_message) . "'" : "NULL") . ",
            sl_created_at = NOW()
    ";
    
    sql_query($sql);
}

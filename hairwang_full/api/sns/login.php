<?php
/**
 * SNS 로그인 API
 * 외부 사이트에서 SNS 로그인을 호출하는 API
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
    ApiResponse::error('This API key is not authorized for SNS login', 403, 'FORBIDDEN');
}

// IP 화이트리스트 확인
$request_ip = ApiAuth::getRequestIp();
if (!ApiAuth::verifyIpWhitelist($api_key_info['ak_id'], $request_ip)) {
    ApiResponse::error('Your IP address is not authorized', 403, 'IP_NOT_ALLOWED');
}

// 요청 파라미터 가져오기
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$auto_register = isset($_POST['auto_register']) ? (bool)$_POST['auto_register'] : true;
$custom_fields = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : array();

// 요청 유효성 검사
$errors = array();

if (empty($provider)) {
    $errors['provider'] = 'Provider is required';
}

if (empty($token)) {
    $errors['token'] = 'Token is required';
}

// 지원하는 SNS 제공자 목록
$supported_providers = array('naver', 'kakao', 'google', 'facebook', 'twitter', 'payco');
if (!in_array($provider, $supported_providers)) {
    $errors['provider'] = 'Invalid provider. Supported: ' . implode(', ', $supported_providers);
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// SNS 로그인 플러그인 경로 확인
$sns_plugin_dir = G5_PLUGIN_PATH . '/social';
if (!is_dir($sns_plugin_dir)) {
    ApiResponse::error('SNS login plugin not found', 500, 'PLUGIN_NOT_FOUND');
}

// 로깅 시작
$log_start_time = microtime(true);
$log_id = null;

try {
    // SNS 제공자별로 토큰 검증 및 사용자 정보 가져오기
    $user_profile = null;
    $identifier = null;
    $display_name = null;
    $email = null;
    
    switch ($provider) {
        case 'naver':
            $user_profile = get_naver_profile_by_token($token);
            break;
        case 'kakao':
            $user_profile = get_kakao_profile_by_token($token);
            break;
        case 'google':
            $user_profile = get_google_profile_by_token($token);
            break;
        case 'facebook':
            $user_profile = get_facebook_profile_by_token($token);
            break;
        case 'twitter':
            $user_profile = get_twitter_profile_by_token($token);
            break;
        case 'payco':
            $user_profile = get_payco_profile_by_token($token);
            break;
    }
    
    // 사용자 프로필이 없는 경우 에러
    if (!$user_profile) {
        throw new Exception('Failed to get user profile from ' . $provider, 'PROFILE_FETCH_FAILED');
    }

    $identifier = isset($user_profile['identifier']) ? $user_profile['identifier'] : null;
    $display_name = isset($user_profile['display_name']) ? $user_profile['display_name'] : null;
    $email = isset($user_profile['email']) ? $user_profile['email'] : null;
    
    if (!$identifier) {
        throw new Exception('User identifier not found', 'IDENTIFIER_NOT_FOUND');
    }
    
    // 데이터베이스에서 기존 연동 정보 확인
    global $g5;
    if (!isset($g5['social_profile_table'])) {
        $g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';
    }
    
    $sql = "
        SELECT sp.*, mb.mb_id, mb.mb_email, mb.mb_name, mb.mb_nick
        FROM {$g5['social_profile_table']} sp
        LEFT JOIN {$g5['member_table']} mb ON sp.mb_id = mb.mb_id
        WHERE sp.provider = '" . sql_real_escape_string($provider) . "'
          AND sp.identifier = '" . sql_real_escape_string($identifier) . "'
    ";
    
    $social_profile = sql_fetch($sql);
    
    $mb_id = null;
    $is_new_user = false;
    
    // 기존 회원인 경우 로그인 처리
    if ($social_profile && $social_profile['mb_id']) {
        $mb_id = $social_profile['mb_id'];
        
        // 회원 탈퇴 여부 확인
        if (!$social_profile['mb_id']) {
            throw new Exception('Member not found', 'MEMBER_NOT_FOUND');
        }
        
        // SNS 로그인 로그 기록 (성공)
        $action = 'login';
        
    } else {
        // 신규 회원인 경우
        if (!$auto_register) {
            // 자동 가입 비활성화인 경우
            $log_data = array(
                'ak_id' => $api_key_info['ak_id'],
                'sl_provider' => $provider,
                'sl_action' => 'error',
                'sl_identifier' => $identifier,
                'sl_success' => 0,
                'sl_error_code' => 'AUTO_REGISTER_DISABLED',
                'sl_error_message' => 'Auto register is disabled',
                'sl_custom_fields' => json_encode($custom_fields, JSON_UNESCAPED_UNICODE),
                'sl_created_at' => 'NOW()'
            );
            sql_query("INSERT INTO {$g5['prefix']}sns_api_logs SET " . http_build_query($log_data, '', ', '));
            
            ApiResponse::success(array(
                'status' => 'new_user',
                'provider' => $provider,
                'identifier' => $identifier,
                'display_name' => $display_name,
                'email' => $email,
                'message' => 'New user detected. Auto register is disabled. Please use register endpoint.'
            ), 200);
        }
        
        // 신규 회원 자동 가입 처리
        $mb_id = create_social_member($provider, $identifier, $display_name, $email, $custom_fields);
        $is_new_user = true;
        $action = 'register';
        
        if (!$mb_id) {
            throw new Exception('Failed to create new member', 'MEMBER_CREATE_FAILED');
        }
    }
    
    // 회원 정보 조회
    $member = get_member($mb_id);
    
    if (!$member) {
        throw new Exception('Failed to get member information', 'MEMBER_FETCH_FAILED');
    }
    
    // 세션 로그인 처리 (필요한 경우)
    set_session('ss_mb_id', $mb_id);
    set_session('ss_mb_key', md5($member['mb_datetime'] . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']));
    
    // SNS 로그인 API 로그 기록 (성공)
    $log_data = array(
        'ak_id' => $api_key_info['ak_id'],
        'sl_provider' => $provider,
        'sl_action' => $action,
        'sl_mb_id' => $mb_id,
        'sl_identifier' => $identifier,
        'sl_success' => 1,
        'sl_custom_fields' => !empty($custom_fields) ? "'" . sql_real_escape_string(json_encode($custom_fields, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL',
        'sl_created_at' => 'NOW()'
    );
    $sql = "INSERT INTO {$g5['prefix']}sns_api_logs SET " . http_build_query($log_data, '', ', ');
    sql_query($sql);
    $log_id = sql_insert_id();
    
    // 커스텀 필드 저장
    if (!empty($custom_fields)) {
        save_custom_fields('sns', $log_id, $mb_id, $custom_fields);
    }
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/sns/login.php',
        'POST',
        $request_ip,
        array('provider' => $provider, 'auto_register' => $auto_register),
        200,
        array('status' => 'success', 'mb_id' => $mb_id),
        null,
        $execution_time
    );
    
    // 성공 응답
    ApiResponse::success(array(
        'status' => 'success',
        'mb_id' => $mb_id,
        'mb_name' => $member['mb_name'],
        'mb_nick' => $member['mb_nick'],
        'mb_email' => $member['mb_email'],
        'provider' => $provider,
        'is_new_user' => $is_new_user,
        'login_time' => date('Y-m-d H:i:s')
    ), 200);
    
} catch (Exception $e) {
    // 에러 로그 기록
    $error_message = $e->getMessage();
    $error_code = $e->getCode() ?: 'UNKNOWN_ERROR';
    
    // SNS 로그인 API 로그 기록 (실패)
    $log_data = array(
        'ak_id' => $api_key_info['ak_id'],
        'sl_provider' => $provider,
        'sl_action' => 'error',
        'sl_identifier' => isset($identifier) ? "'" . sql_real_escape_string($identifier) . "'" : 'NULL',
        'sl_success' => 0,
        'sl_error_code' => "'" . sql_real_escape_string($error_code) . "'",
        'sl_error_message' => "'" . sql_real_escape_string($error_message) . "'",
        'sl_custom_fields' => !empty($custom_fields) ? "'" . sql_real_escape_string(json_encode($custom_fields, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL',
        'sl_created_at' => 'NOW()'
    );
    sql_query("INSERT INTO {$g5['prefix']}sns_api_logs SET " . http_build_query($log_data, '', ', '));
    
    // API 요청 로그 기록
    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'api/sns/login.php',
        'POST',
        $request_ip,
        array('provider' => $provider, 'auto_register' => $auto_register),
        400,
        null,
        $error_message,
        $execution_time
    );
    
    // 에러 응답
    ApiResponse::error($error_message, 400, $error_code);
}

/**
 * SNS 토큰으로 사용자 프로필 가져오기 (Provider별)
 * 기존 SNS 플러그인의 functions.php를 확장
 */

/**
 * 네이버 토큰으로 사용자 프로필 가져오기
 */
function get_naver_profile_by_token($token) {
    $url = 'https://openapi.naver.com/v1/nid/me';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || isset($data->resultcode) && $data->resultcode !== '00') {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->response->id) ? $data->response->id : null;
    $profile->displayName = isset($data->response->name) ? $data->response->name : null;
    $profile->email = isset($data->response->email) ? $data->response->email : null;
    $profile->photoURL = isset($data->response->profile_image) ? $data->response->profile_image : null;
    
    return $profile;
}

/**
 * 카카오 토큰으로 사용자 프로필 가져오기
 */
function get_kakao_profile_by_token($token) {
    $url = 'https://kapi.kakao.com/v2/user/me';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || isset($data->code)) {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->id) ? $data->id : null;
    $profile->displayName = isset($data->properties->nickname) ? $data->properties->nickname : null;
    $profile->email = isset($data->kakao_account->email) ? $data->kakao_account->email : null;
    $profile->photoURL = isset($data->properties->profile_image) ? $data->properties->profile_image : null;
    
    return $profile;
}

/**
 * 구글 토큰으로 사용자 프로필 가져오기
 */
function get_google_profile_by_token($token) {
    $url = 'https://www.googleapis.com/oauth2/v3/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || isset($data->error)) {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->sub) ? $data->sub : null;
    $profile->displayName = isset($data->name) ? $data->name : null;
    $profile->email = isset($data->email) ? $data->email : null;
    $profile->photoURL = isset($data->picture) ? $data->picture : null;
    
    return $profile;
}

/**
 * 페이스북 토큰으로 사용자 프로필 가져오기
 */
function get_facebook_profile_by_token($token) {
    $url = 'https://graph.facebook.com/me?fields=id,name,email,picture';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || isset($data->error)) {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->id) ? $data->id : null;
    $profile->displayName = isset($data->name) ? $data->name : null;
    $profile->email = isset($data->email) ? $data->email : null;
    $profile->photoURL = isset($data->picture->data->url) ? $data->picture->data->url : null;
    
    return $profile;
}

/**
 * 트위터 토큰으로 사용자 프로필 가져오기
 */
function get_twitter_profile_by_token($token) {
    // 트위터 API v2 사용
    $url = 'https://api.twitter.com/2/users/me?user.fields=profile_image_url';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || isset($data->errors)) {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->data->id) ? $data->data->id : null;
    $profile->displayName = isset($data->data->name) ? $data->data->name : null;
    $profile->email = null; // 트위터는 이메일 제공하지 않음
    $profile->photoURL = isset($data->data->profile_image_url) ? $data->data->profile_image_url : null;
    
    return $profile;
}

/**
 * 페이코 토큰으로 사용자 프로필 가져오기
 */
function get_payco_profile_by_token($token) {
    $url = 'https://api-id.payco.com/oauth2.0/getProfile';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response);
    
    if (!$data || !$data->result || $data->result !== 'SUCCESS') {
        return null;
    }
    
    // HybridAuth User_Profile 형식으로 변환
    $profile = new stdClass();
    $profile->identifier = isset($data->data->id) ? $data->data->id : null;
    $profile->displayName = isset($data->data->name) ? $data->data->name : null;
    $profile->email = isset($data->data->email) ? $data->data->email : null;
    $profile->photoURL = isset($data->data->profileImage) ? $data->data->profileImage : null;
    
    return $profile;
}

/**
 * SNS 회원 자동 가입
 */
function create_social_member($provider, $identifier, $display_name, $email, $custom_fields) {
    global $g5, $config;
    
    // 회원 ID 생성
    $mb_id = get_unique_member_id($provider);
    
    // 회원명 생성
    $mb_name = $display_name ? $display_name : $provider . ' 사용자';
    if (mb_strlen($mb_name) > 20) {
        $mb_name = mb_substr($mb_name, 0, 20);
    }
    
    // 닉네임 생성
    $mb_nick = $display_name ? $display_name : $provider . ' 사용자';
    if (mb_strlen($mb_nick) > 20) {
        $mb_nick = mb_substr($mb_nick, 0, 20);
    }
    
    // 닉네임 중복 확인 후 수정
    $mb_nick = get_unique_member_nick($mb_nick);
    
    // 이메일 설정
    $mb_email = $email ? $email : $mb_id . '@social.local';
    
    // 비밀번호 생성 (랜덤)
    $mb_password = generate_random_password();
    
    // 데이터베이스에 회원 정보 저장
    $sql = "
        INSERT INTO {$g5['member_table']}
        SET mb_id = '" . sql_real_escape_string($mb_id) . "',
            mb_password = '" . sql_real_escape_string(get_encrypt_string($mb_password)) . "',
            mb_name = '" . sql_real_escape_string($mb_name) . "',
            mb_nick = '" . sql_real_escape_string($mb_nick) . "',
            mb_email = '" . sql_real_escape_string($mb_email) . "',
            mb_level = '" . sql_real_escape_string($config['cf_register_level']) . "',
            mb_email_certify = '" . sql_real_escape_string(date('Ymd', time())) . "',
            mb_datetime = NOW(),
            mb_today_login = NOW(),
            mb_ip = '" . sql_real_escape_string($_SERVER['REMOTE_ADDR']) . "'
    ";
    
    $result = sql_query($sql);
    
    if (!$result) {
        return false;
    }
    
    // SNS 프로필 테이블에 저장
    if (!isset($g5['social_profile_table'])) {
        $g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';
    }
    
    $social_sql = "
        INSERT INTO {$g5['social_profile_table']}
        SET provider = '" . sql_real_escape_string($provider) . "',
            identifier = '" . sql_real_escape_string($identifier) . "',
            mb_id = '" . sql_real_escape_string($mb_id) . "',
            mp_register_day = CURDATE(),
            mp_extra = '" . sql_real_escape_string(json_encode($custom_fields, JSON_UNESCAPED_UNICODE)) . "'
    ";
    
    sql_query($social_sql);
    
    return $mb_id;
}

/**
 * 고유한 회원 ID 생성
 */
function get_unique_member_id($provider) {
    global $g5;
    
    for ($i = 0; $i < 100; $i++) {
        $candidate = $provider . '_' . mt_rand(100000, 999999);
        
        $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_id = '" . sql_real_escape_string($candidate) . "'";
        $result = sql_fetch($sql);
        
        if ($result['cnt'] == 0) {
            return $candidate;
        }
    }
    
    return $provider . '_' . time();
}

/**
 * 고유한 회원 닉네임 생성
 */
function get_unique_member_nick($mb_nick) {
    global $g5;
    
    $original_nick = $mb_nick;
    $suffix = 1;
    
    while (true) {
        $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_nick = '" . sql_real_escape_string($mb_nick) . "'";
        $result = sql_fetch($sql);
        
        if ($result['cnt'] == 0) {
            return $mb_nick;
        }
        
        $suffix++;
        $mb_nick = $original_nick . '_' . $suffix;
        
        // 너무 많이 반복하면 중단
        if ($suffix > 100) {
            return $original_nick . '_' . time();
        }
    }
}

/**
 * 랜덤 비밀번호 생성
 */
function generate_random_password() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < 16; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
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

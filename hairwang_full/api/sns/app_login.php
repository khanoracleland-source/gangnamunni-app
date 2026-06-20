<?php
/**
 * 앱 전용 SNS 로그인 시작 API
 *
 * 기능:
 * - 앱(Android/iOS)에서 SNS OAuth 인증을 시작할 때 호출
 * - SNS 제공자별 OAuth 인증 URL을 생성하여 반환
 * - 앱은 이 URL을 Chrome Custom Tabs (Android) 또는 ASWebAuthenticationSession (iOS)에서 실행
 *
 * 요청 파라미터:
 * - platform: android | ios | web (필수)
 * - provider: naver | kakao | google | facebook | twitter | payco (필수)
 * - redirect_uri: 앱 딥링크 URL (필수, 예: hairwang://oauth/callback)
 * - state: CSRF 방지 토큰 (필수)
 *
 * 응답:
 * - success: true/false
 * - data.auth_url: SNS OAuth 인증 URL
 * - data.provider: SNS 제공자
 * - data.state: State 파라미터
 *
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// ============================================================
// 1. 초기화 및 공통 파일 포함
// ============================================================

// API 공통 파일 포함 (인증, 응답 헬퍼 함수 등)
include_once dirname(__FILE__).'/../common.php';

// ============================================================
// 2. 요청 메서드 확인 (POST만 허용)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

// ============================================================
// 3. API 인증 확인
// ============================================================

// 헤더에서 API Key와 Secret 가져오기
$auth = ApiAuth::getAuthFromHeader();
if (!$auth) {
    ApiResponse::error('API authentication required. Provide X-API-Key and X-API-Secret headers.', 401, 'AUTH_REQUIRED');
}

// API Key 검증
$api_key_info = ApiAuth::verifyApiKey($auth['api_key'], $auth['api_secret']);
if (!$api_key_info) {
    ApiResponse::error('Invalid API key or secret', 401, 'INVALID_API_KEY');
}

// API 키 타입 확인 (SNS 또는 ALL 타입만 허용)
if ($api_key_info['ak_type'] !== 'sns' && $api_key_info['ak_type'] !== 'all') {
    ApiResponse::error('This API key is not authorized for SNS login', 403, 'FORBIDDEN');
}

// IP 화이트리스트 확인
$request_ip = ApiAuth::getRequestIp();
if (!ApiAuth::verifyIpWhitelist($api_key_info['ak_id'], $request_ip)) {
    ApiResponse::error('Your IP address is not authorized', 403, 'IP_NOT_ALLOWED');
}

// ============================================================
// 4. 요청 파라미터 가져오기
// ============================================================

$platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$redirect_uri = isset($_POST['redirect_uri']) ? trim($_POST['redirect_uri']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';

// ============================================================
// 5. 요청 유효성 검사
// ============================================================

$errors = array();

// 플랫폼 검증
if (empty($platform)) {
    $errors['platform'] = 'Platform is required';
} elseif (!in_array($platform, ['android', 'ios', 'web'])) {
    $errors['platform'] = 'Invalid platform. Allowed: android, ios, web';
}

// SNS 제공자 검증
$supported_providers = array('naver', 'kakao', 'google', 'facebook', 'twitter', 'payco');
if (empty($provider)) {
    $errors['provider'] = 'Provider is required';
} elseif (!in_array($provider, $supported_providers)) {
    $errors['provider'] = 'Invalid provider. Supported: ' . implode(', ', $supported_providers);
}

// Redirect URI 검증
if (empty($redirect_uri)) {
    $errors['redirect_uri'] = 'Redirect URI is required';
}

// State 파라미터 검증 (CSRF 방지)
if (empty($state)) {
    $errors['state'] = 'State parameter is required for CSRF protection';
}

// 에러가 있으면 응답
if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// ============================================================
// 6. SNS 로그인 플러그인 경로 확인
// ============================================================

$sns_plugin_dir = G5_PLUGIN_PATH . '/social';
if (!is_dir($sns_plugin_dir)) {
    ApiResponse::error('SNS login plugin not found', 500, 'PLUGIN_NOT_FOUND');
}

// ============================================================
// 7. 로깅 시작
// ============================================================

$log_start_time = microtime(true);

try {
    // ============================================================
    // 8. OAuth 인증 URL 생성
    // ============================================================

    $auth_url = generate_sns_oauth_url($provider, $redirect_uri, $state, $platform);

    // ============================================================
    // 9. API 로그 기록
    // ============================================================

    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'sns',
        'app_login',
        $_POST,
        [
            'auth_url' => $auth_url,
            'provider' => $provider,
            'platform' => $platform
        ],
        'success',
        $execution_time
    );

    // ============================================================
    // 10. 성공 응답
    // ============================================================

    ApiResponse::success([
        'auth_url' => $auth_url,
        'provider' => $provider,
        'platform' => $platform,
        'state' => $state,
        'redirect_uri' => $redirect_uri
    ]);

} catch (Exception $e) {
    // ============================================================
    // 11. 에러 처리
    // ============================================================

    $execution_time = microtime(true) - $log_start_time;
    ApiLogger::logRequest(
        $api_key_info['ak_id'],
        'sns',
        'app_login',
        $_POST,
        null,
        'error: ' . $e->getMessage(),
        $execution_time
    );

    ApiResponse::error($e->getMessage(), 500, 'INTERNAL_ERROR');
}

// ============================================================
// 함수 정의
// ============================================================

/**
 * SNS OAuth 인증 URL 생성
 *
 * @param string $provider SNS 제공자 (naver, kakao, google 등)
 * @param string $redirect_uri 콜백 URL (앱 딥링크)
 * @param string $state CSRF 방지 토큰
 * @param string $platform 플랫폼 (android, ios, web)
 * @return string OAuth 인증 URL
 * @throws Exception SNS 설정을 찾을 수 없거나 URL 생성 실패 시
 */
function generate_sns_oauth_url($provider, $redirect_uri, $state, $platform) {
    // SNS 설정 불러오기 (client_id, client_secret 등)
    $sns_config = get_sns_config($provider);

    if (!$sns_config || empty($sns_config['client_id'])) {
        throw new Exception("SNS configuration not found for provider: {$provider}");
    }

    // 기본 OAuth 파라미터
    $params = [
        'client_id' => $sns_config['client_id'],
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'state' => $state
    ];

    // ============================================================
    // SNS 제공자별 OAuth URL 및 추가 파라미터
    // ============================================================

    $oauth_urls = [
        'naver' => 'https://nid.naver.com/oauth2.0/authorize',
        'kakao' => 'https://kauth.kakao.com/oauth/authorize',
        'google' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'facebook' => 'https://www.facebook.com/v18.0/dialog/oauth',
        'twitter' => 'https://twitter.com/i/oauth2/authorize',
        'payco' => 'https://id.payco.com/oauth2.0/authorize'
    ];

    // 제공자별 추가 파라미터 설정
    switch ($provider) {
        case 'naver':
            // 네이버: 이름, 이메일 권한 요청
            $params['scope'] = 'name,email';
            break;

        case 'kakao':
            // 카카오: 프로필, 이메일 권한 요청
            $params['scope'] = 'profile_nickname,profile_image,account_email';
            break;

        case 'google':
            // 구글: OpenID Connect + 이메일 + 프로필
            $params['scope'] = 'openid email profile';
            $params['access_type'] = 'offline'; // Refresh Token 받기
            $params['prompt'] = 'consent'; // 항상 동의 화면 표시
            break;

        case 'facebook':
            // 페이스북: 이메일, 공개 프로필 권한
            $params['scope'] = 'email,public_profile';
            break;

        case 'twitter':
            // 트위터: 사용자 정보 읽기 권한
            $params['scope'] = 'tweet.read users.read';
            $params['code_challenge'] = 'challenge'; // PKCE
            $params['code_challenge_method'] = 'plain';
            break;

        case 'payco':
            // 페이코: 이메일 권한
            $params['serviceProviderCode'] = 'FRIENDS';
            break;
    }

    // OAuth URL 생성
    $base_url = $oauth_urls[$provider];
    $query_string = http_build_query($params);

    return $base_url . '?' . $query_string;
}

/**
 * SNS 설정 불러오기
 *
 * 실제 운영 환경에서는:
 * 1. 데이터베이스에서 SNS 설정 불러오기
 * 2. 또는 설정 파일에서 불러오기
 * 3. 환경 변수에서 불러오기
 *
 * @param string $provider SNS 제공자
 * @return array|false SNS 설정 배열 (client_id, client_secret 등)
 */
function get_sns_config($provider) {
    global $g5;

    // ============================================================
    // 방법 1: 데이터베이스에서 불러오기 (권장)
    // ============================================================

    // 예시: g5_config 테이블 또는 별도 SNS 설정 테이블
    // $sql = "SELECT * FROM {$g5['config_table']} WHERE cf_key = 'sns_{$provider}_client_id'";
    // $result = sql_fetch($sql);

    // ============================================================
    // 방법 2: 기존 소셜 로그인 플러그인 설정 활용
    // ============================================================

    $sns_plugin_config = G5_DATA_PATH . '/social/config.php';
    if (file_exists($sns_plugin_config)) {
        include $sns_plugin_config;

        // 플러그인 설정 변수 확인
        if (isset($social_config) && isset($social_config[$provider])) {
            return [
                'client_id' => $social_config[$provider]['client_id'] ?? '',
                'client_secret' => $social_config[$provider]['client_secret'] ?? ''
            ];
        }
    }

    // ============================================================
    // 방법 3: 하드코딩 (개발 환경용, 운영에서는 절대 사용 금지!)
    // ============================================================

    // 주의: 실제 운영 환경에서는 반드시 위 방법 1 또는 2를 사용하세요!
    // 이 코드는 개발/테스트용 예시입니다.

    $configs = [
        'naver' => [
            'client_id' => 'YOUR_NAVER_CLIENT_ID',
            'client_secret' => 'YOUR_NAVER_CLIENT_SECRET'
        ],
        'kakao' => [
            'client_id' => 'YOUR_KAKAO_CLIENT_ID',
            'client_secret' => 'YOUR_KAKAO_CLIENT_SECRET'
        ],
        'google' => [
            'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
            'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET'
        ],
        'facebook' => [
            'client_id' => 'YOUR_FACEBOOK_CLIENT_ID',
            'client_secret' => 'YOUR_FACEBOOK_CLIENT_SECRET'
        ],
        'twitter' => [
            'client_id' => 'YOUR_TWITTER_CLIENT_ID',
            'client_secret' => 'YOUR_TWITTER_CLIENT_SECRET'
        ],
        'payco' => [
            'client_id' => 'YOUR_PAYCO_CLIENT_ID',
            'client_secret' => 'YOUR_PAYCO_CLIENT_SECRET'
        ]
    ];

    return isset($configs[$provider]) ? $configs[$provider] : false;
}
?>

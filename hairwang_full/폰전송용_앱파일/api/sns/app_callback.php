<?php
/**
 * 앱 전용 SNS 로그인 콜백 API
 *
 * 기능:
 * - SNS OAuth 인증 후 SNS 서버로부터 authorization code를 받아 처리
 * - Authorization Code를 Access Token으로 교환
 * - Access Token으로 사용자 프로필 조회
 * - 자동 회원가입 또는 기존 회원 로그인 처리
 * - JWT 토큰 발급
 * - 앱 딥링크로 리디렉션 (토큰 전달)
 *
 * 흐름:
 * 1. SNS 서버 → 이 API (code, state 파라미터)
 * 2. Code → Access Token 교환
 * 3. Access Token → 사용자 프로필 조회
 * 4. 회원 처리 (가입 또는 로그인)
 * 5. JWT 토큰 생성
 * 6. 앱으로 리디렉션: hairwang://oauth/callback?token=JWT_TOKEN
 *
 * GET/POST 파라미터:
 * - code: SNS로부터 받은 authorization code (필수)
 * - state: CSRF 검증용 (필수)
 * - provider: SNS 제공자 (필수)
 * - error: SNS 로그인 실패 시 에러 코드
 *
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// ============================================================
// 1. 초기화 및 공통 파일 포함
// ============================================================

// 그누보드 공통 파일 포함
include_once '../../common.php';

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// ============================================================
// 2. 요청 메서드 확인 (GET/POST 모두 허용)
// ============================================================

// OAuth 콜백은 GET 또는 POST로 올 수 있음
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Only GET/POST method is allowed";
    exit;
}

// ============================================================
// 3. 요청 파라미터 가져오기
// ============================================================

// GET 우선, 없으면 POST에서 가져오기
$code = isset($_GET['code']) ? $_GET['code'] : (isset($_POST['code']) ? $_POST['code'] : '');
$state = isset($_GET['state']) ? $_GET['state'] : (isset($_POST['state']) ? $_POST['state'] : '');
$provider = isset($_GET['provider']) ? $_GET['provider'] : (isset($_POST['provider']) ? $_POST['provider'] : '');
$error = isset($_GET['error']) ? $_GET['error'] : (isset($_POST['error']) ? $_POST['error'] : '');
$error_description = isset($_GET['error_description']) ? $_GET['error_description'] : '';

// ============================================================
// 4. 에러 처리 (SNS에서 에러 반환한 경우)
// ============================================================

if (!empty($error)) {
    // 사용자가 로그인 취소한 경우
    if ($error === 'access_denied' || $error === 'user_cancelled') {
        $redirect_url = "hairwang://oauth/callback?error=user_cancelled";
    } else {
        // 기타 에러
        $error_msg = !empty($error_description) ? $error_description : $error;
        $redirect_url = "hairwang://oauth/callback?error=" . urlencode($error_msg);
    }

    header("Location: " . $redirect_url);
    exit;
}

// ============================================================
// 5. 필수 파라미터 검증
// ============================================================

if (empty($code) || empty($state)) {
    $redirect_url = "hairwang://oauth/callback?error=missing_parameters";
    header("Location: " . $redirect_url);
    exit;
}

// Provider가 URL에 없으면 세션 또는 State에서 복원 시도
if (empty($provider)) {
    // State에서 provider 추출 시도 (예: state=naver_1234567890)
    $state_parts = explode('_', $state);
    if (count($state_parts) >= 2) {
        $provider = $state_parts[0];
    }
}

if (empty($provider)) {
    $redirect_url = "hairwang://oauth/callback?error=missing_provider";
    header("Location: " . $redirect_url);
    exit;
}

// ============================================================
// 6. SNS 로그인 처리
// ============================================================

try {
    // ============================================================
    // 6.1. Authorization Code를 Access Token으로 교환
    // ============================================================

    $access_token = exchange_code_for_token($provider, $code);

    // ============================================================
    // 6.2. Access Token으로 사용자 프로필 조회
    // ============================================================

    $user_profile = get_user_profile($provider, $access_token);

    // ============================================================
    // 6.3. 자동 회원가입 또는 로그인 처리
    // ============================================================

    $member = process_sns_login($provider, $user_profile);

    // ============================================================
    // 6.4. JWT 토큰 발급
    // ============================================================

    $jwt_token = generate_jwt_token($member);

    // ============================================================
    // 6.5. 앱 딥링크로 리디렉션 (토큰 포함)
    // ============================================================

    $redirect_url = "hairwang://oauth/callback?token=" . urlencode($jwt_token) . "&provider=" . $provider;
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    // ============================================================
    // 7. 에러 발생 시 앱으로 리디렉션
    // ============================================================

    $redirect_url = "hairwang://oauth/callback?error=" . urlencode($e->getMessage());
    header("Location: " . $redirect_url);
    exit;
}

// ============================================================
// 함수 정의
// ============================================================

/**
 * Authorization Code를 Access Token으로 교환
 *
 * @param string $provider SNS 제공자
 * @param string $code Authorization code
 * @return string Access token
 * @throws Exception 토큰 발급 실패 시
 */
function exchange_code_for_token($provider, $code) {
    // SNS 설정 불러오기
    $sns_config = get_sns_config($provider);

    if (!$sns_config || empty($sns_config['client_id']) || empty($sns_config['client_secret'])) {
        throw new Exception("SNS configuration not found for provider: {$provider}");
    }

    // ============================================================
    // SNS 제공자별 토큰 엔드포인트
    // ============================================================

    $token_urls = [
        'naver' => 'https://nid.naver.com/oauth2.0/token',
        'kakao' => 'https://kauth.kakao.com/oauth/token',
        'google' => 'https://oauth2.googleapis.com/token',
        'facebook' => 'https://graph.facebook.com/v18.0/oauth/access_token',
        'twitter' => 'https://api.twitter.com/2/oauth2/token',
        'payco' => 'https://id.payco.com/oauth2.0/token'
    ];

    // ============================================================
    // 토큰 요청 파라미터
    // ============================================================

    $params = [
        'grant_type' => 'authorization_code',
        'client_id' => $sns_config['client_id'],
        'client_secret' => $sns_config['client_secret'],
        'code' => $code,
        'redirect_uri' => 'hairwang://oauth/callback' // 인증 시 사용한 것과 동일해야 함
    ];

    // ============================================================
    // HTTP POST 요청으로 토큰 발급
    // ============================================================

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_urls[$provider]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 보안: SSL 검증
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30초 타임아웃

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // ============================================================
    // 응답 처리
    // ============================================================

    if ($curl_error) {
        throw new Exception("Network error: {$curl_error}");
    }

    if ($http_code !== 200) {
        throw new Exception("Token request failed with HTTP {$http_code}: {$response}");
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: {$response}");
    }

    // Access Token 추출
    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        $error_msg = isset($data['error_description']) ? $data['error_description'] : 'Unknown error';
        throw new Exception("Failed to get access token: {$error_msg}");
    }
}

/**
 * Access Token으로 사용자 프로필 조회
 *
 * @param string $provider SNS 제공자
 * @param string $access_token Access token
 * @return array 정규화된 사용자 프로필 (id, name, email, profile_image 등)
 * @throws Exception 프로필 조회 실패 시
 */
function get_user_profile($provider, $access_token) {
    // ============================================================
    // SNS 제공자별 프로필 API 엔드포인트
    // ============================================================

    $profile_urls = [
        'naver' => 'https://openapi.naver.com/v1/nid/me',
        'kakao' => 'https://kapi.kakao.com/v2/user/me',
        'google' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'facebook' => 'https://graph.facebook.com/v18.0/me?fields=id,name,email,picture',
        'twitter' => 'https://api.twitter.com/2/users/me',
        'payco' => 'https://apis.payco.com/oauth2.0/user/me'
    ];

    // ============================================================
    // HTTP GET 요청으로 프로필 조회
    // ============================================================

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profile_urls[$provider]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // ============================================================
    // 응답 처리
    // ============================================================

    if ($curl_error) {
        throw new Exception("Network error: {$curl_error}");
    }

    if ($http_code !== 200) {
        throw new Exception("Profile request failed with HTTP {$http_code}");
    }

    $profile = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response");
    }

    // ============================================================
    // 제공자별 프로필 정규화
    // ============================================================

    return normalize_profile($provider, $profile);
}

/**
 * SNS 제공자별 프로필을 공통 형식으로 정규화
 *
 * @param string $provider SNS 제공자
 * @param array $profile 원본 프로필 데이터
 * @return array 정규화된 프로필 (id, name, email, profile_image)
 */
function normalize_profile($provider, $profile) {
    $normalized = [
        'id' => '',
        'name' => '',
        'email' => '',
        'profile_image' => ''
    ];

    switch ($provider) {
        case 'naver':
            // 네이버: response.id, response.name, response.email
            $response = $profile['response'] ?? [];
            $normalized['id'] = $response['id'] ?? '';
            $normalized['name'] = $response['name'] ?? '';
            $normalized['email'] = $response['email'] ?? '';
            $normalized['profile_image'] = $response['profile_image'] ?? '';
            break;

        case 'kakao':
            // 카카오: id, kakao_account.profile.nickname, kakao_account.email
            $normalized['id'] = $profile['id'] ?? '';
            $kakao_account = $profile['kakao_account'] ?? [];
            $kakao_profile = $kakao_account['profile'] ?? [];
            $normalized['name'] = $kakao_profile['nickname'] ?? '';
            $normalized['email'] = $kakao_account['email'] ?? '';
            $normalized['profile_image'] = $kakao_profile['profile_image_url'] ?? '';
            break;

        case 'google':
            // 구글: id, name, email, picture
            $normalized['id'] = $profile['id'] ?? '';
            $normalized['name'] = $profile['name'] ?? '';
            $normalized['email'] = $profile['email'] ?? '';
            $normalized['profile_image'] = $profile['picture'] ?? '';
            break;

        case 'facebook':
            // 페이스북: id, name, email, picture.data.url
            $normalized['id'] = $profile['id'] ?? '';
            $normalized['name'] = $profile['name'] ?? '';
            $normalized['email'] = $profile['email'] ?? '';
            $picture = $profile['picture']['data'] ?? [];
            $normalized['profile_image'] = $picture['url'] ?? '';
            break;

        case 'twitter':
            // 트위터: data.id, data.name, data.username
            $data = $profile['data'] ?? [];
            $normalized['id'] = $data['id'] ?? '';
            $normalized['name'] = $data['name'] ?? '';
            $normalized['email'] = ''; // 트위터는 이메일 제공 안 함
            $normalized['profile_image'] = $data['profile_image_url'] ?? '';
            break;

        case 'payco':
            // 페이코: member.idNo, member.name, member.email
            $member = $profile['member'] ?? [];
            $normalized['id'] = $member['idNo'] ?? '';
            $normalized['name'] = $member['name'] ?? '';
            $normalized['email'] = $member['email'] ?? '';
            $normalized['profile_image'] = '';
            break;
    }

    return $normalized;
}

/**
 * SNS 로그인 처리 (자동 회원가입 포함)
 *
 * @param string $provider SNS 제공자
 * @param array $profile 정규화된 사용자 프로필
 * @return array 회원 정보
 * @throws Exception 회원 처리 실패 시
 */
function process_sns_login($provider, $profile) {
    global $g5;

    // ============================================================
    // 회원 ID 생성: {provider}_{sns_user_id}
    // ============================================================

    $mb_id = $provider . '_' . $profile['id'];

    // ============================================================
    // 기존 회원 확인
    // ============================================================

    $sql = "SELECT * FROM {$g5['member_table']} WHERE mb_id = '" . sql_real_escape_string($mb_id) . "'";
    $member = sql_fetch($sql);

    if ($member) {
        // ============================================================
        // 기존 회원: 로그인 처리
        // ============================================================

        // 최종 로그인 시간 업데이트
        $sql_update = "UPDATE {$g5['member_table']} SET mb_today_login = NOW() WHERE mb_id = '{$mb_id}'";
        sql_query($sql_update);

        return $member;

    } else {
        // ============================================================
        // 신규 회원: 자동 회원가입
        // ============================================================

        $member = auto_register_member($provider, $profile, $mb_id);
        return $member;
    }
}

/**
 * 자동 회원가입
 *
 * @param string $provider SNS 제공자
 * @param array $profile 사용자 프로필
 * @param string $mb_id 회원 ID
 * @return array 생성된 회원 정보
 * @throws Exception 회원가입 실패 시
 */
function auto_register_member($provider, $profile, $mb_id) {
    global $g5;

    // ============================================================
    // 회원 데이터 준비
    // ============================================================

    $mb_name = !empty($profile['name']) ? $profile['name'] : $mb_id;
    $mb_nick = $mb_name; // 닉네임 = 이름
    $mb_email = !empty($profile['email']) ? $profile['email'] : '';
    $mb_password = '';  // SNS 로그인은 비밀번호 없음
    $mb_level = 2; // 기본 회원 레벨

    // ============================================================
    // 중복 닉네임 처리
    // ============================================================

    $nick_suffix = 1;
    $original_nick = $mb_nick;
    while (true) {
        $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_nick = '" . sql_real_escape_string($mb_nick) . "'";
        $row = sql_fetch($sql);

        if ($row['cnt'] == 0) {
            break; // 사용 가능한 닉네임
        }

        // 중복 시 숫자 추가
        $mb_nick = $original_nick . $nick_suffix;
        $nick_suffix++;
    }

    // ============================================================
    // 회원 INSERT
    // ============================================================

    $sql = "
        INSERT INTO {$g5['member_table']} SET
            mb_id = '" . sql_real_escape_string($mb_id) . "',
            mb_name = '" . sql_real_escape_string($mb_name) . "',
            mb_nick = '" . sql_real_escape_string($mb_nick) . "',
            mb_email = '" . sql_real_escape_string($mb_email) . "',
            mb_password = '',
            mb_level = {$mb_level},
            mb_datetime = NOW(),
            mb_today_login = NOW(),
            mb_ip = '{$_SERVER['REMOTE_ADDR']}',
            mb_email_certify = NOW()
    ";

    $result = sql_query($sql, false);

    if (!$result) {
        throw new Exception("Failed to create member account");
    }

    // ============================================================
    // 생성된 회원 정보 반환
    // ============================================================

    $sql = "SELECT * FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'";
    $member = sql_fetch($sql);

    return $member;
}

/**
 * JWT 토큰 발급
 *
 * @param array $member 회원 정보
 * @return string JWT 토큰
 * @throws Exception JWT 생성 실패 시
 */
function generate_jwt_token($member) {
    // ============================================================
    // JWT 라이브러리 로드
    // ============================================================

    // Firebase JWT 라이브러리 경로 (실제 경로로 수정 필요)
    $jwt_lib_path = G5_LIB_PATH . '/jwt/JWT.php';

    if (file_exists($jwt_lib_path)) {
        require_once $jwt_lib_path;
        require_once G5_LIB_PATH . '/jwt/Key.php';
    } else {
        // JWT 라이브러리가 없는 경우 간단한 토큰 생성
        // 주의: 운영 환경에서는 반드시 JWT 라이브러리 사용!
        return base64_encode(json_encode([
            'mb_id' => $member['mb_id'],
            'mb_name' => $member['mb_name'],
            'timestamp' => time()
        ]));
    }

    // ============================================================
    // JWT Payload 생성
    // ============================================================

    $payload = [
        'iss' => G5_DOMAIN, // 발급자
        'iat' => time(), // 발급 시간
        'exp' => time() + (60 * 60 * 24 * 7), // 만료 시간 (7일)
        'mb_id' => $member['mb_id'],
        'mb_name' => $member['mb_name'],
        'mb_nick' => $member['mb_nick'],
        'mb_level' => $member['mb_level']
    ];

    // ============================================================
    // JWT Secret Key (보안: 설정 파일이나 환경 변수로 관리)
    // ============================================================

    $secret = defined('G5_JWT_SECRET') ? G5_JWT_SECRET : 'hairwang_jwt_secret_key_change_this';

    // ============================================================
    // JWT 토큰 인코딩
    // ============================================================

    try {
        if (class_exists('Firebase\JWT\JWT')) {
            return \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
        } else {
            // Fallback: 간단한 인코딩
            return base64_encode(json_encode($payload));
        }
    } catch (Exception $e) {
        throw new Exception("Failed to generate JWT token: " . $e->getMessage());
    }
}

/**
 * SNS 설정 불러오기 (app_login.php와 동일)
 *
 * @param string $provider SNS 제공자
 * @return array|false SNS 설정 (client_id, client_secret)
 */
function get_sns_config($provider) {
    global $g5;

    // 기존 소셜 로그인 플러그인 설정 활용
    $sns_plugin_config = G5_DATA_PATH . '/social/config.php';
    if (file_exists($sns_plugin_config)) {
        include $sns_plugin_config;

        if (isset($social_config) && isset($social_config[$provider])) {
            return [
                'client_id' => $social_config[$provider]['client_id'] ?? '',
                'client_secret' => $social_config[$provider]['client_secret'] ?? ''
            ];
        }
    }

    // Fallback: 하드코딩 (개발용)
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

<?php
/**
 * API 공통 파일
 * API 인증, 로깅, 에러 처리 등 공통 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// 그누보드 공통 파일 포함
if (file_exists(dirname(__FILE__).'/../common.php')) {
    include_once dirname(__FILE__).'/../common.php';
} elseif (file_exists(dirname(__FILE__).'/../../common.php')) {
    include_once dirname(__FILE__).'/../../common.php';
}

// API 관련 상수 정의
if (!defined('G5_API_DIR')) {
    define('G5_API_DIR', dirname(__FILE__));
}
if (!defined('G5_API_DATA_DIR')) {
    define('G5_API_DATA_DIR', G5_DATA_PATH.'/api');
}
if (!defined('G5_API_LOG_DIR')) {
    define('G5_API_LOG_DIR', G5_API_DATA_DIR.'/logs');
}
if (!defined('G5_API_CACHE_DIR')) {
    define('G5_API_CACHE_DIR', G5_API_DATA_DIR.'/cache');
}

// 필요한 디렉토리 생성
if (!is_dir(G5_API_DATA_DIR)) {
    @mkdir(G5_API_DATA_DIR, G5_DIR_PERMISSION, true);
}
if (!is_dir(G5_API_LOG_DIR)) {
    @mkdir(G5_API_LOG_DIR, G5_DIR_PERMISSION, true);
}
if (!is_dir(G5_API_CACHE_DIR)) {
    @mkdir(G5_API_CACHE_DIR, G5_DIR_PERMISSION, true);
}

// 데이터베이스 테이블 정의 (자동 생성)
if (!isset($g5['api_key_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_keys` (
          `ak_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_name` varchar(100) NOT NULL,
          `ak_key` varchar(64) NOT NULL,
          `ak_secret` varchar(64) NOT NULL,
          `ak_type` enum('sns','pg','all') NOT NULL DEFAULT 'all',
          `ak_ip_whitelist` text,
          `ak_status` tinyint(4) NOT NULL DEFAULT '1',
          `ak_created_at` datetime NOT NULL,
          `ak_expired_at` datetime,
          `ak_last_used` datetime,
          PRIMARY KEY (`ak_id`),
          UNIQUE KEY `ak_key` (`ak_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_key_table'] = $table_prefix.'api_keys';
}

if (!isset($g5['api_ip_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_ips` (
          `ai_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_id` int(11) NOT NULL,
          `ai_ip` varchar(45) NOT NULL,
          `ai_description` varchar(255),
          `ai_status` tinyint(4) NOT NULL DEFAULT '1',
          `ai_created_at` datetime NOT NULL,
          PRIMARY KEY (`ai_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_ip_table'] = $table_prefix.'api_ips';
}

if (!isset($g5['api_log_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_logs` (
          `al_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_id` int(11),
          `al_endpoint` varchar(255) NOT NULL,
          `al_method` varchar(10) NOT NULL,
          `al_request_ip` varchar(45) NOT NULL,
          `al_request_data` text,
          `al_response_code` int(11),
          `al_response_data` text,
          `al_error` text,
          `al_execution_time` float,
          `al_created_at` datetime NOT NULL,
          PRIMARY KEY (`al_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_log_table'] = $table_prefix.'api_logs';
}

// API 로거 클래스
class ApiLogger {
    
    /**
     * API 요청 로그 기록
     *
     * @param int $ak_id API 키 ID
     * @param string $endpoint 엔드포인트
     * @param string $method HTTP 메서드
     * @param string $request_ip 요청 IP
     * @param array $request_data 요청 데이터
     * @param int $response_code 응답 코드
     * @param array $response_data 응답 데이터
     * @param string $error 에러 메시지
     * @param float $execution_time 실행 시간
     * @return int|false 로그 ID 또는 실패시 false
     */
    public static function logRequest($ak_id, $endpoint, $method, $request_ip, $request_data = null, $response_code = null, $response_data = null, $error = null, $execution_time = null) {
        global $g5;
        
        $sql = "
            INSERT INTO {$g5['api_log_table']}
            SET ak_id = '" . sql_real_escape_string($ak_id) . "',
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
            $log_id = sql_insert_id();
            
            // API 키의 마지막 사용 시간 업데이트
            if ($ak_id) {
                sql_query("UPDATE {$g5['api_key_table']} SET ak_last_used = NOW() WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
            }
            
            return $log_id;
        }
        
        return false;
    }
    
    /**
     * 에러 로그 파일에 기록
     *
     * @param string $message 에러 메시지
     * @param string $level 로그 레벨 (ERROR, WARNING, INFO)
     */
    public static function logError($message, $level = 'ERROR') {
        $log_file = G5_API_LOG_DIR . '/api_error_' . date('Ymd') . '.log';
        $log_entry = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// API 인증 클래스
class ApiAuth {
    
    /**
     * API 키 및 시크릿 검증
     *
     * @param string $api_key API 키
     * @param string $api_secret API 시크릿
     * @return array|false API 키 정보 또는 실패시 false
     */
    public static function verifyApiKey($api_key, $api_secret) {
        global $g5;
        
        if (empty($api_key) || empty($api_secret)) {
            return false;
        }
        
        $sql = "
            SELECT * FROM {$g5['api_key_table']}
            WHERE ak_key = '" . sql_real_escape_string($api_key) . "'
              AND ak_secret = '" . sql_real_escape_string($api_secret) . "'
              AND ak_status = 1
              AND (ak_expired_at IS NULL OR ak_expired_at > NOW())
        ";
        
        $result = sql_fetch($sql);
        
        return $result ? $result : false;
    }
    
    /**
     * IP 화이트리스트 검증
     *
     * @param int $ak_id API 키 ID
     * @param string $ip 요청 IP
     * @return bool 허용 여부
     */
    public static function verifyIpWhitelist($ak_id, $ip) {
        global $g5;
        
        // IP 화이트리스트가 설정되지 않은 경우 모든 IP 허용
        $sql = "SELECT ai_ip FROM {$g5['api_ip_table']} WHERE ak_id = '" . sql_real_escape_string($ak_id) . "' AND ai_status = 1";
        $result = sql_query($sql);
        
        if (sql_num_rows($result) === 0) {
            return true;
        }
        
        // IP 화이트리스트에 있는지 확인
        $whitelisted = false;
        while ($row = sql_fetch_array($result)) {
            if ($row['ai_ip'] === $ip) {
                $whitelisted = true;
                break;
            }
            // CIDR 지원
            if (self::cidrMatch($ip, $row['ai_ip'])) {
                $whitelisted = true;
                break;
            }
        }
        
        return $whitelisted;
    }
    
    /**
     * CIDR 매칭 검사
     *
     * @param string $ip IP 주소
     * @param string $cidr CIDR 표기법
     * @return bool 매칭 여부
     */
    private static function cidrMatch($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet);
    }
    
    /**
     * 헤더에서 API 인증 정보 추출
     *
     * @return array|false 인증 정보 또는 실패시 false
     */
    public static function getAuthFromHeader() {
        $headers = getallheaders();
        
        // X-API-Key와 X-API-Secret 헤더 확인
        $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : null);
        $api_secret = isset($headers['X-API-Secret']) ? $headers['X-API-Secret'] : (isset($headers['x-api-secret']) ? $headers['x-api-secret'] : null);
        
        if ($api_key && $api_secret) {
            return array(
                'api_key' => $api_key,
                'api_secret' => $api_secret
            );
        }
        
        return false;
    }
    
    /**
     * 요청 IP 가져오기
     *
     * @return string 요청 IP
     */
    public static function getRequestIp() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // 프록시 서버를 통한 요청인 경우 실제 IP 확인
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $ip;
    }
}

// API 응답 클래스
class ApiResponse {
    
    /**
     * 성공 응답 전송
     *
     * @param array $data 응답 데이터
     * @param int $code HTTP 상태 코드
     */
    public static function success($data = array(), $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        
        $response = array(
            'success' => true,
            'data' => $data,
            'message' => 'Success'
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * 에러 응답 전송
     *
     * @param string $message 에러 메시지
     * @param int $code HTTP 상태 코드
     * @param string $error_code 에러 코드
     */
    public static function error($message = 'Error', $code = 400, $error_code = null) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        
        $response = array(
            'success' => false,
            'error' => array(
                'message' => $message,
                'code' => $error_code
            )
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * 유효성 검사 에러 응답 전송
     *
     * @param array $errors 유효성 검사 에러 목록
     */
    public static function validationError($errors) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        
        $response = array(
            'success' => false,
            'error' => array(
                'message' => 'Validation Error',
                'code' => 'VALIDATION_ERROR',
                'details' => $errors
            )
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-API-Secret');

// OPTIONS 요청 처리 (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// JSON 요청 파싱
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($content_type, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $_POST = json_decode($input, true) ?: array();
    }
}

// SNS 프로필 가져오기 함수들
if (!function_exists('get_naver_profile_by_token')) {
    function get_naver_profile_by_token($token) {
        global $g5;

        $url = 'https://openapi.naver.com/v1/nid/me';
        $headers = array(
            'Authorization: Bearer ' . $token
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Naver profile');
        }

        $data = json_decode($response, true);

        if (!isset($data['response'])) {
            throw new Exception('Invalid Naver profile response');
        }

        return array(
            'identifier' => $data['response']['id'],
            'email' => isset($data['response']['email']) ? $data['response']['email'] : '',
            'display_name' => $data['response']['name'],
            'profile_url' => $data['response']['profile_image'],
            'provider' => 'naver'
        );
    }
}

if (!function_exists('get_kakao_profile_by_token')) {
    function get_kakao_profile_by_token($token) {
        global $g5;

        $url = 'https://kapi.kakao.com/v2/user/me';
        $headers = array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/x-www-form-urlencoded'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Kakao profile');
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            throw new Exception('Invalid Kakao profile response');
        }

        $kakao_account = isset($data['kakao_account']) ? $data['kakao_account'] : array();

        return array(
            'identifier' => strval($data['id']),
            'email' => isset($kakao_account['email']) ? $kakao_account['email'] : '',
            'display_name' => isset($kakao_account['profile']['nickname']) ? $kakao_account['profile']['nickname'] : '',
            'profile_url' => isset($kakao_account['profile']['profile_image_url']) ? $kakao_account['profile']['profile_image_url'] : '',
            'provider' => 'kakao'
        );
    }
}

if (!function_exists('get_google_profile_by_token')) {
    function get_google_profile_by_token($token) {
        global $g5;

        $url = 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Google profile');
        }

        $data = json_decode($response, true);

        if (!isset($data['sub'])) {
            throw new Exception('Invalid Google profile response');
        }

        return array(
            'identifier' => $data['sub'],
            'email' => isset($data['email']) ? $data['email'] : '',
            'display_name' => isset($data['name']) ? $data['name'] : '',
            'profile_url' => isset($data['picture']) ? $data['picture'] : '',
            'provider' => 'google'
        );
    }
}

if (!function_exists('get_facebook_profile_by_token')) {
    function get_facebook_profile_by_token($token) {
        global $g5;

        $url = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . $token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Facebook profile');
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            throw new Exception('Invalid Facebook profile response');
        }

        $profile_url = '';
        if (isset($data['picture']['data']['url'])) {
            $profile_url = $data['picture']['data']['url'];
        }

        return array(
            'identifier' => $data['id'],
            'email' => isset($data['email']) ? $data['email'] : '',
            'display_name' => isset($data['name']) ? $data['name'] : '',
            'profile_url' => $profile_url,
            'provider' => 'facebook'
        );
    }
}

if (!function_exists('get_twitter_profile_by_token')) {
    function get_twitter_profile_by_token($token) {
        global $g5;

        // Twitter API v2 사용
        $url = 'https://api.twitter.com/2/users/me';
        $headers = array(
            'Authorization: Bearer ' . $token
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Twitter profile');
        }

        $data = json_decode($response, true);

        if (!isset($data['data']['id'])) {
            throw new Exception('Invalid Twitter profile response');
        }

        return array(
            'identifier' => strval($data['data']['id']),
            'email' => isset($data['data']['email']) ? $data['data']['email'] : '',
            'display_name' => isset($data['data']['name']) ? $data['data']['name'] : '',
            'profile_url' => '',
            'provider' => 'twitter'
        );
    }
}

if (!function_exists('get_payco_profile_by_token')) {
    function get_payco_profile_by_token($token) {
        global $g5;

        $url = 'https://id.payco.com/oauth2.0/token';
        $params = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $token
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to refresh Payco token');
        }

        $token_data = json_decode($response, true);
        $access_token = isset($token_data['access_token']) ? $token_data['access_token'] : '';

        // 액세스 토큰으로 프로필 가져오기
        $profile_url = 'https://alpha-id.payco.com/oauth2.0/token';
        $headers = array(
            'Authorization: Bearer ' . $access_token
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $profile_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $profile_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to get Payco profile');
        }

        $profile_data = json_decode($profile_response, true);

        if (!isset($profile_data['member']['id'])) {
            throw new Exception('Invalid Payco profile response');
        }

        return array(
            'identifier' => strval($profile_data['member']['id']),
            'email' => isset($profile_data['member']['email']) ? $profile_data['member']['email'] : '',
            'display_name' => isset($profile_data['member']['name']) ? $profile_data['member']['name'] : '',
            'profile_url' => '',
            'provider' => 'payco'
        );
    }
}

if (!function_exists('create_social_member')) {
    function create_social_member($provider, $identifier, $display_name, $email, $custom_fields = array()) {
        global $g5;

        // 고유한 회원 ID 생성
        $mb_id = get_unique_member_id($provider);

        // 고유한 회원 닉네임 생성
        $mb_nick = get_unique_member_nick($display_name ? $display_name : 'sns_user');

        // 회원명 설정
        $mb_name = $display_name ? $display_name : 'SNS 회원';

        // 이메일 설정
        $mb_email = $email ? $email : 'noemail_' . time() . '@temp.com';

        // 비밀번호 생성
        $mb_password = generate_random_password();
        $mb_password_hash = get_encrypt_string($mb_password);

        // IP 주소
        $mb_ip = $_SERVER['REMOTE_ADDR'];

        // 회원 테이블에 삽입
        $sql = "
            INSERT INTO {$g5['member_table']}
            SET mb_id = '" . sql_real_escape_string($mb_id) . "',
                mb_password = '" . sql_real_escape_string($mb_password_hash) . "',
                mb_name = '" . sql_real_escape_string($mb_name) . "',
                mb_nick = '" . sql_real_escape_string($mb_nick) . "',
                mb_email = '" . sql_real_escape_string($mb_email) . "',
                mb_datetime = NOW(),
                mb_ip = '" . sql_real_escape_string($mb_ip) . "',
                mb_level = 2,
                mb_maile = 1
        ";

        $result = sql_query($sql);

        if (!$result) {
            return false;
        }

        // SNS 프로필 테이블에 연동 정보 저장
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
}

if (!function_exists('get_unique_member_id')) {
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
}

if (!function_exists('get_unique_member_nick')) {
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
}

if (!function_exists('generate_random_password')) {
    function generate_random_password() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < 16; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}

if (!function_exists('save_custom_fields')) {
    function save_custom_fields($category, $ref_id, $mb_id, $custom_fields) {
        global $g5;

        $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';

        foreach ($custom_fields as $field_code => $field_value) {
            // 필드 정의 조회
            $field_sql = "SELECT * FROM {$table_prefix}api_fields WHERE af_code = '" . sql_real_escape_string($field_code) . "' AND af_status = 1";
            $field_info = sql_fetch($field_sql);

            if (!$field_info) {
                continue;
            }

            // 필드 값 저장
            $value_sql = "
                INSERT INTO {$table_prefix}api_field_values
                SET af_id = '" . sql_real_escape_string($field_info['af_id']) . "',
                    sl_id = " . ($category === 'sns' && $ref_id ? "'" . sql_real_escape_string($ref_id) . "'" : "NULL") . ",
                    po_id = " . ($category === 'pg' && $ref_id ? "'" . sql_real_escape_string($ref_id) . "'" : "NULL") . ",
                    fv_value = '" . sql_real_escape_string(is_array($field_value) ? json_encode($field_value, JSON_UNESCAPED_UNICODE) : $field_value) . "',
                    fv_created_at = NOW()
            ";

            sql_query($value_sql);
        }
    }
}

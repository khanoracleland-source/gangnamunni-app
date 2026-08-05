<?php
/**
 * ========================================
 * 푸시 알림 시스템 설정 파일
 * ========================================
 *
 * Firebase 서비스 계정 JSON 파일과 토픽 설정을 관리합니다.
 *
 * @file    config.php
 * @created 2025-12-18
 * @version 1.0.0
 * @updated 2026-01-29 - 환경 변수 지원 추가
 */

// 환경 변수 로더
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

// 설정 파일 경로
define('CONFIG_FILE', __DIR__ . '/push_config.json');
define('TEST_NOTIFICATION_CONFIG_FILE', __DIR__ . '/test_notification_config.json');

/**
 * 기본 설정값 (.env 파일에서 우선 로드)
 *
 * - firebase_json_file: Firebase 서비스 계정 JSON 파일 경로
 * - topic_name: FCM 토픽 이름 (영문자, 숫자, 밑줄(_)만 가능)
 * - project_id: Firebase 프로젝트 ID
 */
$default_config = [
    'firebase_json_file' => env('FIREBASE_JSON_PATH', ''),
    'topic_name' => env('DEFAULT_TOPIC', 'news_test'),
    'project_id' => env('FIREBASE_PROJECT_ID', 'hairwang-web-app'),
    'arg3_value' => env('DEFAULT_SENDER', 'HAIRWANG'),  // 푸시 알림 arg3 기본값
    'default_url' => env('DEFAULT_URL', 'https://hairwang.com')  // 푸시 알림 기본 URL
];

/**
 * 설정 파일 읽기
 *
 * push_config.json 파일에서 설정을 읽어옵니다.
 * 파일이 없으면 기본값을 반환합니다.
 *
 * @return array 설정 배열
 */
function get_push_config() {
    global $default_config;

    $config = $default_config;

    // 설정 파일이 존재하면 읽기
    if (file_exists(CONFIG_FILE)) {
        $config_content = file_get_contents(CONFIG_FILE);
        $file_config = json_decode($config_content, true);

        // JSON 파싱 성공 시
        if (json_last_error() === JSON_ERROR_NONE && is_array($file_config)) {
            // 기본값과 병합 (누락된 키 방지)
            $config = array_merge($config, $file_config);
        }
    }

    // Secrets and deployment-specific values from the environment always win.
    $environment_overrides = [
        'firebase_json_file' => env('FIREBASE_JSON_PATH', ''),
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'topic_name' => env('DEFAULT_TOPIC', ''),
        'arg3_value' => env('DEFAULT_SENDER', ''),
        'default_url' => env('DEFAULT_URL', ''),
    ];

    foreach ($environment_overrides as $key => $value) {
        if (is_string($value) && trim($value) !== '') {
            $config[$key] = trim($value);
        }
    }

    return $config;
}

/**
 * 설정 파일 저장
 *
 * 설정 배열을 push_config.json 파일에 저장합니다.
 *
 * @param array $config 저장할 설정 배열
 * @return bool 저장 성공 여부
 */
function save_push_config($config) {
    // JSON 형식으로 변환 (보기 좋게 포맷팅)
    $json_content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // 파일 저장
    $result = file_put_contents(CONFIG_FILE, $json_content);

    return $result !== false;
}

/**
 * Firebase JSON 파일 경로 가져오기
 *
 * 현재 설정된 Firebase 서비스 계정 JSON 파일의 전체 경로를 반환합니다.
 * 절대 경로면 그대로 반환하고, 상대 경로면 현재 디렉토리 기준으로 변환합니다.
 *
 * @return string Firebase JSON 파일 전체 경로
 */
function get_firebase_json_path() {
    $config = get_push_config();
    $file_path = $config['firebase_json_file'];

    if (!is_string($file_path) || trim($file_path) === '') {
        return '';
    }

    // 절대 경로인지 확인 (Windows: C:\... 또는 \\..., Linux: /...)
    if (preg_match('/^[a-zA-Z]:[\\\\\\/]/', $file_path) || preg_match('/^[\\\\\\/]/', $file_path)) {
        return $file_path;  // 절대 경로면 그대로 반환
    }

    // 상대 경로면 현재 디렉토리 기준으로 변환
    return __DIR__ . '/' . $file_path;
}

/**
 * 현재 토픽 이름 가져오기
 *
 * 전체 푸시에 사용될 토픽 이름을 반환합니다.
 *
 * @return string 토픽 이름
 */
function get_topic_name() {
    $config = get_push_config();
    return $config['topic_name'];
}

/**
 * Firebase 프로젝트 ID 가져오기
 *
 * FCM API에 사용될 프로젝트 ID를 반환합니다.
 * Firebase JSON 파일에서 직접 읽어오거나, 설정 파일에서 가져옵니다.
 *
 * @return string 프로젝트 ID
 */
function get_project_id() {
    // 먼저 Firebase JSON 파일에서 project_id를 읽어옵니다
    $firebase_json_path = get_firebase_json_path();

    if (file_exists($firebase_json_path)) {
        $firebase_content = file_get_contents($firebase_json_path);
        $firebase_data = json_decode($firebase_content, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($firebase_data['project_id'])) {
            return $firebase_data['project_id'];
        }
    }

    // Firebase JSON 파일에서 읽기 실패 시 config에서 가져오기
    $config = get_push_config();
    return isset($config['project_id']) ? $config['project_id'] : 'hairwang-web-app';
}

/**
 * 푸시 알림 arg3 값 가져오기
 *
 * @return string arg3 값
 */
function get_arg3_value() {
    $config = get_push_config();
    return isset($config['arg3_value']) ? $config['arg3_value'] : 'HAIRWANG';
}

/**
 * 푸시 알림 기본 URL 가져오기
 *
 * @return string 기본 URL
 */
function get_default_url() {
    $config = get_push_config();
    return isset($config['default_url']) ? $config['default_url'] : 'https://hairwang.com';
}

/**
 * 토픽 이름 유효성 검사
 *
 * FCM 토픽 이름은 다음 규칙을 따라야 합니다:
 * - 영문자, 숫자, 밑줄(_), 하이픈(-)만 사용 가능
 * - 1~900자 길이
 *
 * @param string $topic_name 검사할 토픽 이름
 * @return bool 유효하면 true, 아니면 false
 */
function validate_topic_name($topic_name) {
    // 길이 검사 (1~900자)
    if (strlen($topic_name) < 1 || strlen($topic_name) > 900) {
        return false;
    }

    // 패턴 검사 (영문자, 숫자, 밑줄, 하이픈만 허용)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $topic_name)) {
        return false;
    }

    return true;
}

/**
 * Firebase JSON 파일 유효성 검사
 *
 * Firebase 서비스 계정 JSON 파일이 올바른 형식인지 검사합니다.
 *
 * @param string $file_path 검사할 파일 경로
 * @return array ['valid' => bool, 'message' => string, 'data' => array|null]
 */
function validate_firebase_json($file_path) {
    $result = [
        'valid' => false,
        'message' => '',
        'data' => null
    ];

    // 파일 존재 확인
    if (!file_exists($file_path)) {
        $result['message'] = '파일이 존재하지 않습니다.';
        return $result;
    }

    // 파일 읽기
    $content = file_get_contents($file_path);
    if ($content === false) {
        $result['message'] = '파일을 읽을 수 없습니다.';
        return $result;
    }

    // JSON 파싱
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result['message'] = 'JSON 형식이 올바르지 않습니다: ' . json_last_error_msg();
        return $result;
    }

    // 필수 키 확인
    $required_keys = ['type', 'project_id', 'private_key', 'client_email', 'token_uri'];
    $missing_keys = [];

    foreach ($required_keys as $key) {
        if (!isset($data[$key]) || empty($data[$key])) {
            $missing_keys[] = $key;
        }
    }

    if (!empty($missing_keys)) {
        $result['message'] = '필수 키가 누락되었습니다: ' . implode(', ', $missing_keys);
        return $result;
    }

    // 서비스 계정 타입 확인
    if ($data['type'] !== 'service_account') {
        $result['message'] = '올바른 서비스 계정 파일이 아닙니다.';
        return $result;
    }

    // 모든 검증 통과
    $result['valid'] = true;
    $result['message'] = '유효한 Firebase 서비스 계정 파일입니다.';
    $result['data'] = [
        'project_id' => $data['project_id'],
        'client_email' => $data['client_email']
    ];

    return $result;
}

/**
 * ========================================
 * 테스트 알림 설정 함수
 * ========================================
 */

/**
 * 테스트 알림 설정 읽기
 *
 * @return array 테스트 알림 설정 배열
 */
function get_test_notification_config() {
    $default = [
        'title' => '테스트 알림',
        'message' => '테스트 메시지입니다.',
        'domain' => 'https://hairwang.com',
        'image' => ''
    ];

    if (file_exists(TEST_NOTIFICATION_CONFIG_FILE)) {
        $content = file_get_contents(TEST_NOTIFICATION_CONFIG_FILE);
        $config = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return array_merge($default, $config);
        }
    }

    return $default;
}

/**
 * 테스트 알림 설정 저장
 *
 * @param array $config 저장할 설정 배열
 * @return bool 저장 성공 여부
 */
function save_test_notification_config($config) {
    $json_content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = file_put_contents(TEST_NOTIFICATION_CONFIG_FILE, $json_content);
    return $result !== false;
}

/**
 * 이미지 폴더에서 이미지 파일 목록 가져오기
 *
 * @return array 이미지 파일 경로 배열
 */
function get_image_files() {
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $image_files = [];

    foreach ($image_extensions as $ext) {
        $files = glob(__DIR__ . '/*.' . $ext);
        $image_files = array_merge($image_files, $files);
    }

    return array_map('basename', $image_files);
}

// 초기화: 설정 파일이 없으면 기본값으로 생성
if (!file_exists(CONFIG_FILE)) {
    save_push_config($default_config);
}
?>

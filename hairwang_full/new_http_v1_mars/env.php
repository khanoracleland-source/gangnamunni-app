<?php
/**
 * ========================================
 * .env 환경 변수 로더
 * ========================================
 *
 * .env 파일에서 환경 변수를 읽어와 PHP에서 사용할 수 있게 합니다.
 *
 * @file    env.php
 * @created 2026-01-29
 * @version 1.0.0
 *
 * @usage
 *   require_once __DIR__ . '/env.php';
 *   $api_key = env('PUSH_API_KEY', 'default_value');
 */

/**
 * .env 파일 경로
 */
define('ENV_FILE_PATH', __DIR__ . '/.env');

/**
 * 환경 변수 캐시
 */
$_ENV_CACHE = [];

/**
 * .env 파일 로드 함수
 *
 * @return void
 */
function load_env() {
    global $_ENV_CACHE;

    try {
        // 이미 로드된 경우 스킵
        if (!empty($_ENV_CACHE)) {
            return;
        }

        // .env 파일이 없으면 종료
        if (!file_exists(ENV_FILE_PATH)) {
            error_log('.env file not found: ' . ENV_FILE_PATH);
            return;
        }

        // 파일 읽기
        $lines = @file(ENV_FILE_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new Exception('Failed to read .env file: ' . ENV_FILE_PATH);
        }

        // 각 라인 파싱
        foreach ($lines as $line) {
            try {
                // 주석 제거 (#으로 시작하는 라인 무시)
                $line = trim($line);

                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                // KEY=VALUE 형식 파싱
                $parts = explode('=', $line, 2);

                if (count($parts) !== 2) {
                    continue;
                }

                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // 따옴표 제거 (있는 경우)
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // 환경 변수로 저장
                $_ENV_CACHE[$key] = $value;
                @putenv("$key=$value");
            } catch (Exception $e) {
                // 개별 라인 파싱 오류는 로그만 남기고 계속 진행
                error_log('Error parsing .env line: ' . $e->getMessage());
                continue;
            }
        }
    } catch (Exception $e) {
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

/**
 * 환경 변수 가져오기 함수
 *
 * @param string $key 환경 변수 키
 * @param mixed $default 기본값 (환경 변수가 없을 때)
 *
 * @return mixed 환경 변수 값 또는 기본값
 */
function env($key, $default = null) {
    global $_ENV_CACHE;

    try {
        // .env 파일 로드 (최초 1회만)
        if (empty($_ENV_CACHE)) {
            load_env();
        }

        // 캐시에서 찾기
        if (isset($_ENV_CACHE[$key])) {
            return $_ENV_CACHE[$key];
        }

        // $_ENV 전역 변수에서 찾기
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // getenv()로 찾기
        $value = @getenv($key);
        if ($value !== false) {
            return $value;
        }

        // 기본값 반환
        return $default;
    } catch (Exception $e) {
        error_log('Error getting env variable ' . $key . ': ' . $e->getMessage());
        return $default;
    }
}

/**
 * 환경 변수 존재 여부 확인
 *
 * @param string $key 환경 변수 키
 *
 * @return bool 존재 여부
 */
function env_exists($key) {
    global $_ENV_CACHE;

    // .env 파일 로드 (최초 1회만)
    if (empty($_ENV_CACHE)) {
        load_env();
    }

    return isset($_ENV_CACHE[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
}

/**
 * 모든 환경 변수 가져오기
 *
 * @return array 환경 변수 배열
 */
function env_all() {
    global $_ENV_CACHE;

    // .env 파일 로드 (최초 1회만)
    if (empty($_ENV_CACHE)) {
        load_env();
    }

    return $_ENV_CACHE;
}

/**
 * 환경 변수 타입 변환 헬퍼 함수
 */

/**
 * 환경 변수를 boolean으로 변환
 *
 * @param string $key 환경 변수 키
 * @param bool $default 기본값
 *
 * @return bool
 */
function env_bool($key, $default = false) {
    try {
        $value = env($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower($value);

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    } catch (Exception $e) {
        error_log('Error converting env to bool ' . $key . ': ' . $e->getMessage());
        return $default;
    }
}

/**
 * 환경 변수를 정수로 변환
 *
 * @param string $key 환경 변수 키
 * @param int $default 기본값
 *
 * @return int
 */
function env_int($key, $default = 0) {
    try {
        $value = env($key, $default);
        return (int) $value;
    } catch (Exception $e) {
        error_log('Error converting env to int ' . $key . ': ' . $e->getMessage());
        return $default;
    }
}

/**
 * 환경 변수를 실수로 변환
 *
 * @param string $key 환경 변수 키
 * @param float $default 기본값
 *
 * @return float
 */
function env_float($key, $default = 0.0) {
    try {
        $value = env($key, $default);
        return (float) $value;
    } catch (Exception $e) {
        error_log('Error converting env to float ' . $key . ': ' . $e->getMessage());
        return $default;
    }
}

/**
 * 환경 변수를 배열로 변환 (콤마로 구분)
 *
 * @param string $key 환경 변수 키
 * @param array $default 기본값
 *
 * @return array
 */
function env_array($key, $default = []) {
    try {
        $value = env($key);

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        return array_map('trim', explode(',', $value));
    } catch (Exception $e) {
        error_log('Error converting env to array ' . $key . ': ' . $e->getMessage());
        return $default;
    }
}

// 파일 로드 시 자동으로 .env 로드
load_env();

/**
 * ========================================
 * 사용 예시
 * ========================================
 *
 * // env.php 로드
 * require_once __DIR__ . '/env.php';
 *
 * // 환경 변수 가져오기
 * $api_key = env('PUSH_API_KEY', 'default_api_key');
 * $firebase_path = env('FIREBASE_JSON_PATH');
 * $project_id = env('FIREBASE_PROJECT_ID');
 *
 * // boolean 타입으로 가져오기
 * $debug_mode = env_bool('DEBUG_MODE', false);
 *
 * // 정수 타입으로 가져오기
 * $max_attempts = env_int('MAX_LOGIN_ATTEMPTS', 5);
 *
 * // 배열로 가져오기 (콤마로 구분된 값)
 * $allowed_ips = env_array('ALLOWED_IPS', ['127.0.0.1']);
 *
 * // 존재 여부 확인
 * if (env_exists('CUSTOM_KEY')) {
 *     // ...
 * }
 */
?>

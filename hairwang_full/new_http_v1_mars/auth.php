<?php
/**
 * ========================================
 * 인증 관리 시스템
 * ========================================
 *
 * 푸시 알림 대시보드에 대한 접근 권한을 관리합니다.
 * 세션 기반 인증을 사용하여 로그인 상태를 유지합니다.
 *
 * @file    auth.php
 * @created 2025-12-18
 * @version 1.0.0
 *
 * @usage
 *   // 페이지 시작 부분에 추가
 *   require_once __DIR__ . '/auth.php';
 *   require_login();  // 로그인하지 않은 사용자는 login.php로 리다이렉트
 */

// 환경 변수 로더
require_once __DIR__ . '/env.php';

// 세션 시작 (이미 시작된 경우 무시)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ========================================
 * 인증 정보 설정
 * ========================================
 */

// 관리자 계정 정보 (.env 파일에서 로드)
define('ADMIN_USERNAME', env('ADMIN_USERNAME', 'admin'));
// 비밀번호 해시 (.env 파일에서 로드)
// 새 비밀번호를 설정하려면 generate_password_hash.php를 실행하세요
define('ADMIN_PASSWORD_HASH', env('ADMIN_PASSWORD_HASH', '$2y$10$Qb9F5nZQXhVxJy4KqN8XLuGZfO8vH7pYjKwB3mTdR6eS1cA2oU8yi'));

// 세션 유효 시간 (초 단위, .env 파일에서 로드, 기본값: 2시간)
define('SESSION_LIFETIME', env_int('SESSION_LIFETIME', 7200));

// Brute Force 방어 설정 (.env 파일에서 로드)
define('MAX_LOGIN_ATTEMPTS', env_int('MAX_LOGIN_ATTEMPTS', 5));  // 최대 로그인 시도 횟수
define('LOGIN_LOCKOUT_TIME', env_int('LOGIN_LOCKOUT_TIME', 1800));  // 잠금 시간 (초, 기본 30분)

/**
 * ========================================
 * 인증 함수
 * ========================================
 */

/**
 * 로그인 처리 함수
 *
 * 사용자가 제공한 아이디와 비밀번호를 검증하고 세션을 생성합니다.
 * Brute Force 공격 방어 기능 포함.
 *
 * @param string $username 사용자 아이디
 * @param string $password 사용자 비밀번호
 *
 * @return bool 로그인 성공 여부
 */
function login($username, $password) {
    try {
        // 입력값 검증
        if (empty($username) || empty($password)) {
            return false;
        }

        // Brute Force 방어: 계정이 잠겨있는지 확인
        if (is_account_locked($username)) {
            $remaining_time = get_lockout_remaining_time($username);
            $minutes = ceil($remaining_time / 60);

            error_log("Login attempt blocked - Account locked: $username (Remaining: {$minutes}m)");
            return false;
        }

        // 계정 정보 확인
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            // 로그인 성공 - 실패 횟수 초기화
            clear_login_attempts($username);

            // 세션 생성
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();  // 로그인 시각 기록
            $_SESSION['last_activity'] = time();  // 마지막 활동 시각 기록

            // 세션 하이재킹 방지를 위한 세션 ID 재생성
            session_regenerate_id(true);

            return true;
        }

        // 로그인 실패 - 실패 횟수 증가
        increment_login_attempts($username);

        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * 로그아웃 처리 함수
 *
 * 현재 세션을 완전히 삭제하고 로그인 페이지로 리다이렉트합니다.
 *
 * @param bool $redirect 로그아웃 후 로그인 페이지로 리다이렉트할지 여부 (기본값: true)
 *
 * @return void
 */
function logout($redirect = true) {
    // 세션 변수 모두 삭제
    $_SESSION = array();

    // 세션 쿠키 삭제
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    // 세션 파기
    session_destroy();

    // 로그인 페이지로 리다이렉트
    if ($redirect) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 로그인 상태 확인 함수
 *
 * 현재 사용자가 로그인되어 있는지 확인합니다.
 * 세션 만료 시간도 함께 체크합니다.
 *
 * @return bool 로그인 상태 여부
 */
function is_logged_in() {
    // 로그인 세션이 없는 경우
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    // 세션 만료 시간 확인
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];

        // 세션 만료 시간 초과 시 로그아웃
        if ($elapsed_time > SESSION_LIFETIME) {
            logout(false);
            return false;
        }
    }

    // 마지막 활동 시각 업데이트
    $_SESSION['last_activity'] = time();

    return true;
}

/**
 * 로그인 필수 페이지 보호 함수
 *
 * 이 함수를 호출한 페이지는 로그인이 필요한 페이지로 설정됩니다.
 * 로그인하지 않은 사용자는 자동으로 로그인 페이지로 리다이렉트됩니다.
 *
 * @return void
 */
function require_login() {
    if (!is_logged_in()) {
        // 현재 페이지 URL 저장 (로그인 후 돌아올 페이지)
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

        // 로그인 페이지로 리다이렉트
        header('Location: login.php');
        exit;
    }
}

/**
 * 현재 로그인한 사용자 정보 반환 함수
 *
 * @return array|null 사용자 정보 배열 또는 null (로그인하지 않은 경우)
 */
function get_auth_user() {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'username' => $_SESSION['username'] ?? '',
        'login_time' => $_SESSION['login_time'] ?? 0,
        'last_activity' => $_SESSION['last_activity'] ?? 0
    ];
}

/**
 * 세션 남은 시간 계산 함수
 *
 * @return int 남은 시간 (초 단위)
 */
function get_session_remaining_time() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }

    $elapsed_time = time() - $_SESSION['last_activity'];
    $remaining_time = SESSION_LIFETIME - $elapsed_time;

    return max(0, $remaining_time);
}

/**
 * ========================================
 * 유틸리티 함수
 * ========================================
 */

/**
 * CSRF 토큰 생성 함수
 *
 * Cross-Site Request Forgery 공격을 방지하기 위한 토큰을 생성합니다.
 *
 * @return string CSRF 토큰
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * CSRF 토큰 검증 함수
 *
 * 제출된 토큰이 세션에 저장된 토큰과 일치하는지 확인합니다.
 *
 * @param string $token 검증할 토큰
 *
 * @return bool 토큰 유효성 여부
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 에러 메시지 설정 함수
 *
 * @param string $message 에러 메시지
 *
 * @return void
 */
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * 성공 메시지 설정 함수
 *
 * @param string $message 성공 메시지
 *
 * @return void
 */
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * 에러 메시지 가져오기 함수 (가져온 후 삭제)
 *
 * @return string|null 에러 메시지 또는 null
 */
function get_error_message() {
    $message = $_SESSION['error_message'] ?? null;
    unset($_SESSION['error_message']);
    return $message;
}

/**
 * 성공 메시지 가져오기 함수 (가져온 후 삭제)
 *
 * @return string|null 성공 메시지 또는 null
 */
function get_success_message() {
    $message = $_SESSION['success_message'] ?? null;
    unset($_SESSION['success_message']);
    return $message;
}

/**
 * ========================================
 * Brute Force 방어 함수
 * ========================================
 */

/**
 * 로그인 실패 횟수 증가
 *
 * @param string $username 사용자 아이디
 *
 * @return void
 */
function increment_login_attempts($username) {
    try {
        $key = 'login_attempts_' . $username;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }

        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();

        // 최대 시도 횟수 초과 시 계정 잠금
        if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;

            error_log("Account locked due to too many failed attempts: $username (Attempts: {$_SESSION[$key]['count']})");
        }
    } catch (Exception $e) {
        error_log("Error incrementing login attempts: " . $e->getMessage());
    }
}

/**
 * 로그인 실패 횟수 초기화
 *
 * @param string $username 사용자 아이디
 *
 * @return void
 */
function clear_login_attempts($username) {
    $key = 'login_attempts_' . $username;
    unset($_SESSION[$key]);
}

/**
 * 계정 잠금 여부 확인
 *
 * @param string $username 사용자 아이디
 *
 * @return bool 잠금 여부
 */
function is_account_locked($username) {
    $key = 'login_attempts_' . $username;

    if (!isset($_SESSION[$key]['locked_until'])) {
        return false;
    }

    $locked_until = $_SESSION[$key]['locked_until'];

    // 잠금 시간이 지났으면 잠금 해제
    if (time() >= $locked_until) {
        clear_login_attempts($username);
        return false;
    }

    return true;
}

/**
 * 잠금 남은 시간 계산
 *
 * @param string $username 사용자 아이디
 *
 * @return int 남은 시간 (초 단위)
 */
function get_lockout_remaining_time($username) {
    $key = 'login_attempts_' . $username;

    if (!isset($_SESSION[$key]['locked_until'])) {
        return 0;
    }

    $remaining = $_SESSION[$key]['locked_until'] - time();

    return max(0, $remaining);
}

/**
 * 로그인 시도 정보 가져오기
 *
 * @param string $username 사용자 아이디
 *
 * @return array|null 시도 정보 또는 null
 */
function get_login_attempt_info($username) {
    $key = 'login_attempts_' . $username;

    if (!isset($_SESSION[$key])) {
        return null;
    }

    $info = $_SESSION[$key];
    $info['is_locked'] = is_account_locked($username);
    $info['remaining_time'] = get_lockout_remaining_time($username);
    $info['remaining_attempts'] = max(0, MAX_LOGIN_ATTEMPTS - $info['count']);

    return $info;
}

/**
 * ========================================
 * 로깅 함수
 * ========================================
 */

/**
 * 로그인 시도 기록 함수
 *
 * @param string $username 시도한 사용자 아이디
 * @param bool $success 로그인 성공 여부
 * @param string $ip_address IP 주소
 *
 * @return void
 */
function log_login_attempt($username, $success, $ip_address = null) {
    try {
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $log_file = __DIR__ . '/logs/login_attempts.log';
        $log_dir = dirname($log_file);

        // 로그 디렉토리 생성 (없는 경우)
        if (!is_dir($log_dir)) {
            if (!@mkdir($log_dir, 0755, true)) {
                throw new Exception("Failed to create log directory: {$log_dir}");
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';

        // Brute Force 방어 정보 추가
        $attempt_info = get_login_attempt_info($username);
        $extra_info = '';

        if ($attempt_info && !$success) {
            $extra_info = ", Attempts: {$attempt_info['count']}/{" . MAX_LOGIN_ATTEMPTS . "}";

            if ($attempt_info['is_locked']) {
                $extra_info .= ", Status: LOCKED";
            }
        }

        $log_message = "[$timestamp] [$status] Username: $username, IP: $ip_address$extra_info\n";

        // 로그 파일에 기록
        if (@file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
            throw new Exception("Failed to write to log file: {$log_file}");
        }
    } catch (Exception $e) {
        error_log("Error logging login attempt: " . $e->getMessage());
    }
}
?>

<?php
/**
 * API 공통 부트스트랩
 * 모든 API 엔드포인트에서 require하여 공통 설정 적용
 *
 * 기능:
 * - CORS 헤더 (PHP 레벨 백업)
 * - JSON Content-Type
 * - 에러 핸들링 (JSON 응답)
 * - OPTIONS 프리플라이트 처리
 * - 요청 메서드 검증 헬퍼
 */

// 에러를 JSON으로 반환하기 위한 핸들러 설정
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => '서버 내부 오류가 발생했습니다.',
        'error_code' => 'INTERNAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    // 프로덕션에서는 에러 로그에만 기록
    error_log("[API Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
    exit;
});

// 출력 버퍼링 시작 (비정상 출력 방지)
ob_start();

// CORS 헤더 - 자동 도메인 감지 (어떤 호스팅이든 즉시 동작)
$_is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$_scheme = $_is_https ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_self_origin = $_scheme . '://' . $_host;

// 현재 서버 도메인 + www 변형 + 로컬 개발 자동 허용
$allowed_origins = [$_self_origin, 'http://localhost', 'http://localhost:8080', 'http://127.0.0.1'];
if (strpos($_host, 'www.') === 0) {
    $allowed_origins[] = $_scheme . '://' . substr($_host, 4);
} elseif ($_host !== 'localhost' && $_host !== '127.0.0.1') {
    $allowed_origins[] = $_scheme . '://www.' . $_host;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} else {
    // 동일 출처 요청이거나 Origin 미전송 시 - 현재 도메인 허용
    header("Access-Control-Allow-Origin: {$_self_origin}");
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Max-Age: 3600');

// OPTIONS 프리플라이트 응답
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// JSON Content-Type
header('Content-Type: application/json; charset=utf-8');

// 캐시 방지 (API 응답은 항상 최신)
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 공통 includes
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

/**
 * 요청 메서드 검증
 * @param string|array $allowed 허용 메서드 ('POST' 또는 ['GET', 'POST'])
 */
function requireMethod($allowed)
{
    if (is_string($allowed)) $allowed = [$allowed];
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, $allowed)) {
        jsonError("{$method} 요청은 허용되지 않습니다.", 405);
    }
}

/**
 * 필수 필드 검증 (JSON body)
 * @param array $body 요청 body
 * @param array $fields 필수 필드 배열 ['field_name' => '표시 이름']
 */
function requireFields(array $body, array $fields)
{
    foreach ($fields as $key => $label) {
        if (is_int($key)) {
            $key = $label;
            $label = $key;
        }
        if (!isset($body[$key]) || (is_string($body[$key]) && trim($body[$key]) === '')) {
            jsonError("{$label} 항목은 필수입니다.");
        }
    }
}

/**
 * API Rate Limiting 공통 래퍼
 * @param string $action 액션 식별자
 * @param int $max 최대 횟수
 * @param int $window 시간 윈도우 (초)
 */
function apiRateLimit(string $action, int $max = 60, int $window = 60)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!checkRateLimit($action . '_' . $ip, $max, $window)) {
        jsonError('요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429);
    }
}

/**
 * 인증된 사용자 확인 (Gnuboard 세션 기반)
 * @param bool $required 필수 여부 (false면 미인증시 null 반환)
 * @return array|null ['user_id' => ..., 'user_name' => ..., 'mb_level' => ...]
 */
function getAuthUser(bool $required = true)
{
    // 세션 보안 설정 + 시작
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        if ($_is_https ?? false) {
            ini_set('session.cookie_secure', 1);
        }
        session_start();
    }

    $user_id = $_SESSION['ss_mb_id'] ?? null;
    if (empty($user_id)) {
        if ($required) {
            jsonError('로그인이 필요합니다.', 401);
        }
        return null;
    }

    return [
        'user_id' => $user_id,
        'user_name' => $_SESSION['ss_mb_nick'] ?? $user_id,
        'mb_level' => (int)($_SESSION['ss_mb_level'] ?? 1)
    ];
}

/**
 * 관리자 권한 확인
 * @param int $minLevel 최소 레벨 (기본 10)
 * @return array 인증된 관리자 정보
 */
function requireAdmin(int $minLevel = 10)
{
    $user = getAuthUser(true);
    if ($user['mb_level'] < $minLevel) {
        jsonError('관리자 권한이 필요합니다.', 403);
    }
    return $user;
}

/**
 * CSRF 토큰 검증 (POST 요청 시 호출)
 * JSON body 또는 헤더에서 토큰 추출
 */
function requireCsrf()
{
    // 헤더에서 토큰 확인
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    // JSON body에서 토큰 확인
    if (!$token) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $token = $data['csrf_token'] ?? null;
    }

    // FormData에서 토큰 확인
    if (!$token) {
        $token = $_POST['csrf_token'] ?? null;
    }

    if (!$token || !validateCsrfToken($token)) {
        jsonError('유효하지 않은 요청입니다. 페이지를 새로고침 해주세요.', 403);
    }
}

/**
 * 리소스 소유자 확인
 * @param string $ownerUserId 리소스 소유자의 user_id
 * @param array $authUser 현재 인증된 사용자 (getAuthUser 반환값)
 * @param bool $allowAdmin 관리자 우회 허용 여부
 */
function requireOwnership(string $ownerUserId, array $authUser, bool $allowAdmin = true)
{
    if ($authUser['user_id'] === $ownerUserId) return;
    if ($allowAdmin && ($authUser['mb_level'] ?? 0) >= 10) return;
    jsonError('권한이 없습니다.', 403);
}

// shutdown 핸들러 - 출력 버퍼 정리
register_shutdown_function(function () {
    // Fatal error 발생 시에도 JSON 응답 보장
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '서버 오류가 발생했습니다.',
            'error_code' => 'FATAL_ERROR'
        ], JSON_UNESCAPED_UNICODE);
        error_log("[API Fatal] {$error['message']} in {$error['file']}:{$error['line']}");
    } else {
        // 정상 종료 시 버퍼 플러시
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
});

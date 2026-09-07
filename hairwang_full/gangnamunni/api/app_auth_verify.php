<?php
/**
 * App Native Auth - Verify API
 * POST /gangnamunni/api/app_auth_verify.php
 * Header: Authorization: Bearer <access_token>  (또는 body: { "access_token": "..." })
 * Body: { "device_id": "<64자리 hex>" }
 *
 * 로컬(폰)에 저장된 accessToken의 만료 시각만으로는 충분하지 않다 — 서버가
 * 이미 이 토큰을 폐기했을 수 있다(로그아웃/탈퇴/차단). 이 엔드포인트는 매번
 * 서버에서 최종 판단하고, 유효하면 WebView 세션 복구용 1회성 브릿지 토큰을
 * 함께 내려준다. 실패(401)면 앱은 refreshToken으로 갱신을 시도해야 한다.
 * 네트워크/서버 오류(그 외 상태코드)는 토큰을 지우지 말고 재시도해야 한다.
 *
 * @created 2026-09-07
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/../includes/app_native_auth.php';

if (!defined('G5_SERVER_TIME')) define('G5_SERVER_TIME', time());

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// Query failures must roll back and remain distinct from invalid credentials.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$access_token = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $access_token = $m[1];
} else {
    $access_token = trim((string)($input['access_token'] ?? ''));
}
$device_id = trim((string)($input['device_id'] ?? ''));

if ($access_token === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'access_token and device_id are required']);
    exit;
}

try {
    $response = app_native_auth_transaction($connect, function () use ($connect, $access_token, $device_id) {
        $mb_id = app_verify_access_token($connect, $access_token, $device_id, true);
        if ($mb_id === '') {
            throw new DomainException('access_token_invalid', 401);
        }

        $stmt = mysqli_prepare($connect,
            "SELECT mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_level, mb_intercept_date, mb_leave_date, mb_email_certify
             FROM g5_member WHERE mb_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $mb_id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (app_native_auth_member_is_blocked($member, $connect)) {
            throw new DomainException('account_unavailable', 401);
        }

        $bridge_token = app_issue_session_bridge_token($connect, $mb_id, $device_id);
        if ($bridge_token === '') {
            throw new RuntimeException('bridge_issue_failed');
        }

        return [
            'success' => true,
            'session_bridge_token' => $bridge_token,
            'user' => [
                'mb_id' => $member['mb_id'],
                'name' => $member['mb_name'],
                'nickname' => $member['mb_nick'],
                'email' => $member['mb_email'],
                'phone' => $member['mb_hp'],
                'level' => (int)$member['mb_level'],
            ],
        ];
    });
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[app-native-auth] verify failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'verify_unavailable']);
}

mysqli_close($connect);

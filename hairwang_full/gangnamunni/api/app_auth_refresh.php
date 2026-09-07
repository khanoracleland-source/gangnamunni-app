<?php
/**
 * App Native Auth - Refresh API
 * POST /gangnamunni/api/app_auth_refresh.php
 * Body: { "mb_id": "...", "refresh_token": "...", "device_id": "<64자리 hex>" }
 *
 * accessToken이 만료됐을 때만 호출한다. refreshToken 검증/갱신은 기존
 * lib/common.lib.php의 create_remember_login_token()/verify_remember_login_token()
 * 를 그대로 재사용한다 — 그 함수는 이미 "응답 유실에도 안전"하도록
 * (교체가 아니라 만료시각만 늘리는 renew-in-place) 구현/검증되어 있다.
 * 응답이 끊겨도 넘겨준 refresh_token은 갱신된 형태로 그대로 유효하게 남는다.
 *
 * refresh_token 자체가 무효(만료/기기불일치/폐기)면 401 — 그때만 앱은
 * 로그인 화면을 보여줘야 한다. 그 외 오류(DB 장애 등)는 5xx로 알리고
 * 앱은 토큰을 지우지 말고 재시도해야 한다.
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
require_once __DIR__ . '/../includes/app_native_auth.php'; // pulls in common.lib.php's remember-token helpers

if (!defined('G5_SERVER_TIME')) define('G5_SERVER_TIME', time());

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// app_native_auth_transaction()'s rollback safety net depends on mysqli
// throwing on error (PHP 8.1+'s default) rather than silently returning
// false (pre-8.1, and how Gnuboard's own sql_connect() sets it via
// MYSQLI_REPORT_OFF for the shared $connect elsewhere). This endpoint owns
// its whole request, so forcing it here is safe and makes the guarantee
// hold regardless of PHP version.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$mb_id = trim((string)($input['mb_id'] ?? ''));
$refresh_token = trim((string)($input['refresh_token'] ?? ''));
$device_id = trim((string)($input['device_id'] ?? ''));

if ($mb_id === '' || $refresh_token === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'mb_id, refresh_token and device_id are required']);
    exit;
}
$mb_id = substr(preg_replace('/[^a-zA-Z0-9_]*/', '', $mb_id), 0, 20);

try {
    $response = app_native_auth_transaction($connect, function () use ($connect, $mb_id, $refresh_token, $device_id) {
        // Lock the refresh row first, then access/bridge rows, just like logout.
        $renewed = app_renew_native_refresh_token($connect, $mb_id, $refresh_token, $device_id);
        $stmt = mysqli_prepare($connect, "SELECT * FROM g5_member WHERE mb_id = ? LIMIT 1");
        if (!$stmt) throw new RuntimeException('Member lookup failed');
        mysqli_stmt_bind_param($stmt, 's', $mb_id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (app_native_auth_member_is_blocked($member, $connect)) {
            throw new DomainException('account_unavailable', 401);
        }
        $access = app_issue_access_token($connect, $mb_id, $device_id);
        $bridge = app_issue_session_bridge_token($connect, $mb_id, $device_id);
        if ($access['token'] === '' || $bridge === '') throw new RuntimeException('Token issuance failed');
        return [
            'success' => true, 'access_token' => $access['token'],
            'access_expires_at' => $access['expires_at'], 'refresh_token' => $renewed,
            'refresh_expires_at' => G5_SERVER_TIME + APP_REFRESH_TOKEN_LIFETIME,
            'session_bridge_token' => $bridge,
            'user' => ['mb_id' => $mb_id, 'name' => $member['mb_name'],
                'nickname' => $member['mb_nick'], 'email' => $member['mb_email'],
                'phone' => $member['mb_hp'], 'level' => (int)$member['mb_level']],
        ];
    });
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[app-native-auth] refresh failed: ' . get_class($e));
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'refresh_unavailable']);
}
mysqli_close($connect);

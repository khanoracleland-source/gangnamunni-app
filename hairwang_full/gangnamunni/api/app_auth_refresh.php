<?php
/**
 * App Native Auth - Refresh API
 * POST /gangnamunni/api/app_auth_refresh.php
 * Body: { "mb_id": "...", "refresh_token": "...", "device_id": "<64자리 hex>" }
 *
 * Renew the presented refresh token in place under a transaction lock.
 * A lost response can retry the same credential; a concurrent logout cannot
 * recreate a revoked token. Issue a short access token and one-shot bridge.
 *
 * refresh_token 자체가 무효(만료/기기불일치/폐기)면 401 — 그때만 앱은
 * 로그인 화면을 보여줘야 한다. 그 외 오류(DB 장애 등)는 5xx로 알리고
 * 앱은 토큰을 지우지 말고 재시도해야 한다.
 *
 * @created 2026-09-07
 */
require_once __DIR__ . '/../includes/app_native_auth_http.php';
$input = app_auth_http_input();
$connect = null;

$mb_id = app_auth_input_string($input, 'mb_id');
$refresh_token = app_auth_input_string($input, 'refresh_token');
$device_id = app_auth_input_string($input, 'device_id');

if ($mb_id === '' || $refresh_token === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'mb_id, refresh_token and device_id are required']);
    exit;
}

try {
    $connect = app_auth_open_connection();
    $response = app_native_auth_transaction($connect, function () use ($connect, $mb_id, $refresh_token, $device_id) {
        // Lock the refresh row first, then access/bridge rows, just like logout.
        $renewed = app_renew_native_refresh_token($connect, $mb_id, $refresh_token, $device_id);
        $user = app_auth_member($connect, $mb_id);
        $access = app_issue_access_token($connect, $mb_id, $device_id);
        $bridge = app_issue_session_bridge_token($connect, $mb_id, $device_id);
        if ($access['token'] === '' || $bridge === '') throw new RuntimeException('Token issuance failed');
        return [
            'success' => true, 'access_token' => $access['token'],
            'access_expires_at' => $access['expires_at'], 'refresh_token' => $renewed,
            'refresh_expires_at' => G5_SERVER_TIME + APP_REFRESH_TOKEN_LIFETIME,
            'session_bridge_token' => $bridge,
            'user' => $user,
        ];
    });
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[app-native-auth] refresh failed: ' . get_class($e));
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'refresh_unavailable']);
}
if ($connect) mysqli_close($connect);

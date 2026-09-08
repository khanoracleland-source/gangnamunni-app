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
require_once __DIR__ . '/../includes/app_native_auth_http.php';
$input = app_auth_http_input();
$connect = null;

$access_token = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $access_token = $m[1];
} else {
    $access_token = app_auth_input_string($input, 'access_token');
}
$device_id = app_auth_input_string($input, 'device_id');

if ($access_token === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'access_token and device_id are required']);
    exit;
}

try {
    $connect = app_auth_open_connection();
    $response = app_native_auth_transaction($connect, function () use ($connect, $access_token, $device_id) {
        $mb_id = app_verify_access_token($connect, $access_token, $device_id, true);
        if ($mb_id === '') {
            throw new DomainException('access_token_invalid', 401);
        }

        $user = app_auth_member($connect, $mb_id);

        $bridge_token = app_issue_session_bridge_token($connect, $mb_id, $device_id);
        if ($bridge_token === '') {
            throw new RuntimeException('bridge_issue_failed');
        }

        return [
            'success' => true,
            'session_bridge_token' => $bridge_token,
            'user' => $user,
        ];
    });
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[app-native-auth] verify failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'verify_unavailable']);
}

if ($connect) mysqli_close($connect);

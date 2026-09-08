<?php
require_once __DIR__ . '/../includes/app_native_auth_http.php';
$input = app_auth_http_input();
$connect = null;

try {
    $connect = app_auth_open_connection();
    $token = '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
        $token = $m[1];
    }
    if (!$token) {
        $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
    }

    $native_mb_id = app_auth_input_string($input, 'mb_id');
    $native_device_id = app_auth_input_string($input, 'device_id');
    $native_access_token = app_auth_input_string($input, 'access_token');
    $native_refresh_token = app_auth_input_string($input, 'refresh_token');
    if ($native_mb_id !== '' && app_native_auth_is_valid_device_id($native_device_id)) {

        $ownership_proven = false;
        if ($native_access_token !== '') {
            $ownership_proven = app_verify_access_token($connect, $native_access_token, $native_device_id) === $native_mb_id;
        }
        if (!$ownership_proven && $native_refresh_token !== '') {
            $ownership_proven = verify_remember_login_token(
                $native_mb_id,
                $native_refresh_token,
                ['is_patient_app' => true, 'device_id' => $native_device_id]
            );
        }

        if ($ownership_proven) {
            app_revoke_native_auth_tokens($connect, $native_mb_id, $native_device_id);
        }
    }

    // Legacy app_login.php issues a raw gnuboard SSO token. Native token
    // hashes must never be accepted as raw bearer credentials here.
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/i', $token)) {
        $stmt = mysqli_prepare($connect,
            "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'gnuboard'");
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $remember_cookie_name = md5('ck_auto');
    $remember_cookie = $_COOKIE[$remember_cookie_name] ?? '';
    $remember_raw = is_string($remember_cookie)
        ? base64_decode($remember_cookie, true)
        : false;
    if (is_string($remember_raw) && preg_match('/^[a-f0-9]{64}$/i', $remember_raw)) {
        $remember_hash = hash('sha256', $remember_raw);
        $remember_delete = mysqli_prepare($connect,
            "DELETE FROM sso_tokens
             WHERE token = ? AND user_type IN ('web_remember', 'patient_app_remember')");
        if ($remember_delete) {
            mysqli_stmt_bind_param($remember_delete, 's', $remember_hash);
            mysqli_stmt_execute($remember_delete);
            mysqli_stmt_close($remember_delete);
        }
    }
} catch (Throwable $e) {
    // Best-effort cleanup below still runs regardless — a transient DB
    // error here must not stop the client's cookies/session from clearing.
    http_response_code(503);
    error_log('[app-native-auth] logout cleanup failed: ' . get_class($e) . ': ' . $e->getMessage());
}

setcookie('sb_sso_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_id()) {
    session_unset();
    session_destroy();
}

foreach (['ck_mb_id', 'ck_auto'] as $cookie_name) {
    setcookie(md5($cookie_name), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

echo json_encode(http_response_code() === 503
    ? ['success' => false, 'error' => 'logout_unavailable']
    : ['success' => true, 'message' => '로그아웃 완료']);

if ($connect) mysqli_close($connect);

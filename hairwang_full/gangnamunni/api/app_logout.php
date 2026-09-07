<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/../includes/app_native_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// app_native_auth_transaction()'s rollback safety net (used by
// app_revoke_native_auth_tokens()) depends on mysqli throwing on error
// (PHP 8.1+'s default) rather than silently returning false (pre-8.1, and
// how Gnuboard's own sql_connect() sets it via MYSQLI_REPORT_OFF for the
// shared $connect elsewhere). This endpoint owns its whole request, so
// forcing it here makes that guarantee hold regardless of PHP version.
//
// This also means the legacy (non-transactional) cleanup below can now
// throw where it previously just returned false silently — it is wrapped
// so a DB hiccup there still lets cookie/session cleanup run and still
// returns a response, instead of a bare fatal error.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

try {
    $token = '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
        $token = $m[1];
    }
    if (!$token) {
        $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
    }

    $native_mb_id = trim((string)($input['mb_id'] ?? ''));
    $native_device_id = trim((string)($input['device_id'] ?? ''));
    $native_access_token = trim((string)($input['access_token'] ?? ''));
    $native_refresh_token = trim((string)($input['refresh_token'] ?? ''));
    if ($native_mb_id !== '' && app_native_auth_is_valid_device_id($native_device_id)) {
        $native_mb_id = substr(preg_replace('/[^a-zA-Z0-9_]*/', '', $native_mb_id), 0, 20);

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

    $deleted = false;

    if (!empty($token)) {
        $stmt = mysqli_prepare($connect, "SELECT g5_mb_id FROM sso_tokens WHERE token = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $sso = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($sso && !empty($sso['g5_mb_id'])) {
                $del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ?");
                if ($del) {
                    mysqli_stmt_bind_param($del, 's', $token);
                    mysqli_stmt_execute($del);
                    $deleted = (mysqli_affected_rows($connect) > 0);
                    mysqli_stmt_close($del);
                }

            }
        }

        if (!$deleted) {
            $del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ?");
            if ($del) {
                mysqli_stmt_bind_param($del, 's', $token);
                mysqli_stmt_execute($del);
                $deleted = (mysqli_affected_rows($connect) > 0);
                mysqli_stmt_close($del);
            }
        }
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

echo json_encode([
    'success' => true,
    'message' => '로그아웃 완료'
]);

mysqli_close($connect);

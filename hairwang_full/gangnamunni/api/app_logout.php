<?php
/**
 * App Logout API
 * POST /api/app_logout.php
 * Header: Authorization: Bearer <token>  OR  Body: { "token": "..." }
 *
 * Phase5: 통합 로그아웃 — SSO 토큰 삭제 + 세션 파기 + 쿠키 제거
 *
 * @created 2026-03-22
 */
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
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// Get token
$token = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $token = $m[1];
}
if (!$token) {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
}

$deleted = false;

if (!empty($token)) {
    // 1. Revoke only the presented SSO token. Logging out this client must not
    // invalidate the same user's patient-app remember tokens on other devices.
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

    // 2. 해당 토큰만이라도 삭제 (위에서 못 찾은 경우)
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

// Revoke the exact remember credential even when the shorter-lived SSO token
// is already expired or absent. Gnuboard stores the raw 64-hex value as a
// base64 cookie and only its SHA-256 hash in the unique token column, so this
// cannot affect another browser, account, or app device.
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

// 3. 쿠키 제거
setcookie('sb_sso_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// 4. 세션 파기
if (session_id()) {
    session_unset();
    session_destroy();
}

// 5. Gnuboard 자동 로그인 쿠키도 제거
foreach (['ck_mb_id', 'ck_auto'] as $cookie_name) {
    // Gnuboard set_cookie() hashes the public cookie name with md5.
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

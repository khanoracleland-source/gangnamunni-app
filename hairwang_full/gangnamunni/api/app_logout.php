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
    // 1. SSO 토큰으로 사용자 확인 후 해당 사용자의 모든 토큰 삭제
    $stmt = mysqli_prepare($connect, "SELECT g5_mb_id FROM sso_tokens WHERE token = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $sso = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($sso && !empty($sso['g5_mb_id'])) {
            // 해당 사용자의 모든 토큰 삭제
            $del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = ?");
            if ($del) {
                mysqli_stmt_bind_param($del, 's', $sso['g5_mb_id']);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
                $deleted = true;
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
setcookie('ck_mb_id', '', time() - 3600, '/');
setcookie('ck_auto', '', time() - 3600, '/');

echo json_encode([
    'success' => true,
    'message' => '로그아웃 완료'
]);

mysqli_close($connect);

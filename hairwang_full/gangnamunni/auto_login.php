<?php
/**
 * 데모 전용 자동 로그인
 * 앱 TEST_MODE에서만 사용 — 프로덕션 빌드에서는 호출 안 됨
 *
 * GET 파라미터:
 *   u = username
 *   p = password
 *   r = redirect (로그인 후 이동할 페이지, 기본: dashboard.php)
 *   demo = 1 (필수 체크 — 없으면 거부)
 */
session_start();
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

// ─────────────────────────────────────────
//  보안 체크 1: demo=1 필수
// ─────────────────────────────────────────
if (($_GET['demo'] ?? '') !== '1') {
    http_response_code(403);
    die('접근 불가');
}

// ─────────────────────────────────────────
//  보안 체크 2: User-Agent에 hybrid_android 포함 여부
//  (앱에서만 호출 가능하도록)
// ─────────────────────────────────────────
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($ua, 'hybrid_android') === false && strpos($ua, 'hybrid_ios') === false) {
    // 브라우저 직접 접근 차단 (앱 UserAgent만 허용)
    http_response_code(403);
    die('앱에서만 접근 가능합니다');
}

// ─────────────────────────────────────────
//  로그인 처리
// ─────────────────────────────────────────
$username = trim($_GET['u'] ?? '');
$password = trim($_GET['p'] ?? '');
$redirect = trim($_GET['r'] ?? 'dashboard.php');

// redirect 경로 안전 처리 (경로 탈출 방지)
$redirect = preg_replace('/[^a-zA-Z0-9_.?=&]/', '', $redirect);
if (empty($redirect)) $redirect = 'dashboard.php';

if (empty($username) || empty($password)) {
    die('파라미터 오류');
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 오류');
mysqli_set_charset($connect, 'utf8mb4');

if (admin_login($username, $password, $connect)) {
    mysqli_close($connect);
    // 로그인 성공 → 리다이렉트
    header('Location: ' . $redirect);
    exit;
} else {
    mysqli_close($connect);
    http_response_code(401);
    die('로그인 실패');
}

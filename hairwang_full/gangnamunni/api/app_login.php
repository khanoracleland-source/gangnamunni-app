<?php
/**
 * App Login API
 * POST /api/app_login.php
 * Phase5 fix: sso_tokens column = g5_mb_id (was mb_id which doesn't exist)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'error' => 'POST only']); exit; }

require_once __DIR__ . '/../../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$username = trim($input['username'] ?? $input['mb_id'] ?? '');
$password = trim($input['password'] ?? $input['mb_password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'ID/PW required']);
    exit;
}

$stmt = mysqli_prepare($connect,
    "SELECT mb_no, mb_id, mb_password, mb_name, mb_nick, mb_email, mb_hp, mb_level, mb_point
     FROM g5_member WHERE (mb_id = ? OR mb_email = ?) AND mb_leave_date = '' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$member) {
    echo json_encode(['success' => false, 'error' => 'Account not found']);
    exit;
}

if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../../lib/pbkdf2.compat.php';

$pw_ok = false;
if (strpos($member['mb_password'], 'sha256:') === 0) {
    $pw_ok = validate_password($password, $member['mb_password']);
} else {
    $r = mysqli_query($connect, "SELECT PASSWORD('" . mysqli_real_escape_string($connect, $password) . "') as pw");
    $row = mysqli_fetch_assoc($r);
    $pw_ok = ($row['pw'] === $member['mb_password']);
}

if (!$pw_ok) {
    echo json_encode(['success' => false, 'error' => 'Wrong password']);
    exit;
}

// Generate SSO token (30 days)
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', time() + 86400 * 30);

// Phase5 fix: use correct column g5_mb_id (NOT mb_id which doesn't exist in sso_tokens)
$del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = ?");
if ($del) {
    mysqli_stmt_bind_param($del, 's', $member['mb_id']);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$user_type = 'gnuboard';

$ins = mysqli_prepare($connect,
    "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($ins, 'ssssss', $token, $user_type, $member['mb_id'], $ip, $ua, $expiry);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

// Set SSO cookie
setcookie('sb_sso_token', $token, [
    'expires' => time() + 86400 * 30,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Update last login
$mb_no = intval($member['mb_no']);
$login_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$upd = mysqli_prepare($connect, "UPDATE g5_member SET mb_today_login = NOW(), mb_login_ip = ? WHERE mb_no = ?");
mysqli_stmt_bind_param($upd, 'si', $login_ip, $mb_no);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

// Get gangnamunni member info
$gn_member = null;
$gn_stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_members WHERE mb_no = ? LIMIT 1");
if ($gn_stmt) {
    mysqli_stmt_bind_param($gn_stmt, 'i', $mb_no);
    mysqli_stmt_execute($gn_stmt);
    $gn_r = mysqli_stmt_get_result($gn_stmt);
    if ($gn_r) $gn_member = mysqli_fetch_assoc($gn_r);
    mysqli_stmt_close($gn_stmt);
}

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'mb_no' => (int)$member['mb_no'],
        'mb_id' => $member['mb_id'],
        'name' => $member['mb_name'],
        'nickname' => $member['mb_nick'],
        'email' => $member['mb_email'],
        'phone' => $member['mb_hp'],
        'level' => (int)$member['mb_level'],
        'point' => (int)$member['mb_point'],
        'avatar' => $gn_member['avatar'] ?? null,
    ]
], JSON_UNESCAPED_UNICODE);

mysqli_close($connect);

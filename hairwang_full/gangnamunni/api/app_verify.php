<?php
/**
 * App Token Verify API
 * POST/GET /api/app_verify.php
 * Phase5 fix: sso_tokens column = g5_mb_id (was mb_id/mb_no which don't exist)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// Get token from header or body or query
$token = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $token = $m[1];
}
if (!$token) {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
}

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Token required']);
    exit;
}

// Phase5 fix: use g5_mb_id column (NOT mb_id/mb_no)
$stmt = mysqli_prepare($connect, "SELECT g5_mb_id, expires_at FROM sso_tokens WHERE token = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$sso = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$sso) {
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

if (strtotime($sso['expires_at']) < time()) {
    echo json_encode(['success' => false, 'error' => 'Token expired']);
    exit;
}

$mb_id = $sso['g5_mb_id'];
$stmt = mysqli_prepare($connect,
    "SELECT mb_no, mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_level, mb_point
     FROM g5_member WHERE mb_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $mb_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$member) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$gn_member = null;
$mb_no = intval($member['mb_no']);
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

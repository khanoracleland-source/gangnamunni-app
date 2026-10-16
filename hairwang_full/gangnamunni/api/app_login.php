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

$username = trim((string)($input['username'] ?? $input['mb_id'] ?? ''));
// Parol trim qilinmaydi — web login (auth/login.php) bilan bir xil.
$password = (string)($input['password'] ?? $input['mb_password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'ID/PW required']);
    exit;
}

/**
 * Login rate limit — auth/login.php dagi gnu_login_rate_hit bilan bir xil format va katalog,
 * shuning uchun web va app urinishlari bitta hisoblagichga tushadi.
 */
if (!function_exists('gnu_login_rate_hit')) {
    function gnu_login_rate_hit($scope, $value, $limit, $window, $record = true) {
        $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : (is_dir(__DIR__ . '/../../data') ? __DIR__ . '/../../data' : sys_get_temp_dir());
        $dir = $base . '/cache/login_rate';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return ['allowed' => false, 'retry' => 60];
        $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower((string)$value)) . '.json';
        $fp = @fopen($path, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return ['allowed' => false, 'retry' => 60]; }
        $data = json_decode(stream_get_contents($fp) ?: '', true);
        $now = time();
        $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
        $hits = array_values(array_filter($hits, function ($ts) use ($now, $window) { return (int)$ts > $now - $window; }));
        $retry = count($hits) >= $limit ? max(1, $window - ($now - (int)$hits[0])) : 0;
        if (!$retry && $record) $hits[] = $now;
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode(['hits' => $hits])); fflush($fp);
        flock($fp, LOCK_UN); fclose($fp);
        return ['allowed' => $retry === 0, 'retry' => $retry];
    }
}

function app_login_fail($code = 401, $retry = 0) {
    http_response_code($code);
    if ($retry > 0) header('Retry-After: ' . (int)$retry);
    echo json_encode([
        'success' => false,
        // Akkaunt mavjudligi / bloklangani / parol xatosi farqlanmaydi (enumeration oldini olish)
        'error' => $code === 429 ? 'Too many attempts' : 'Invalid credentials',
    ] + ($retry > 0 ? ['retry_after' => (int)$retry] : []));
    exit;
}

$request_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$login_key  = strtolower($username);
$rate_rules = [
    ['ip',       $request_ip,                    10, 900],
    ['ip_email', $request_ip . '|' . $login_key,  5, 900],
    ['account',  $login_key,                     20, 3600],
];
$retry = 0;
foreach ($rate_rules as $r) {
    $res = gnu_login_rate_hit($r[0], $r[1], $r[2], $r[3], false);
    if (!$res['allowed']) $retry = max($retry, (int)$res['retry']);
}
if ($retry > 0) {
    error_log('[auth][app_rate_limited] account=' . substr(hash('sha256', $login_key), 0, 16) . '; ip=' . $request_ip);
    app_login_fail(429, $retry);
}
$record_failure = function () use ($rate_rules) {
    foreach ($rate_rules as $r) gnu_login_rate_hit($r[0], $r[1], $r[2], $r[3], true);
};

$stmt = mysqli_prepare($connect,
    "SELECT mb_no, mb_id, mb_password, mb_name, mb_nick, mb_email, mb_hp, mb_level, mb_point,
            mb_intercept_date, mb_leave_date
     FROM g5_member WHERE (mb_id = ? OR mb_email = ?) LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../../lib/pbkdf2.compat.php';

// Akkaunt topilmasa ham bir xil PBKDF2 ish hajmi — javob vaqti orqali enumeration bo'lmasin.
$dummy_hash = 'sha256:12000:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

$pw_ok = false;
if (!$member) {
    validate_password($password, $dummy_hash);
} elseif (strpos($member['mb_password'], 'sha256:') === 0) {
    $pw_ok = validate_password($password, $member['mb_password']);
} else {
    $r = mysqli_query($connect, "SELECT PASSWORD('" . mysqli_real_escape_string($connect, $password) . "') as pw");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    $pw_ok = $row && hash_equals((string)$member['mb_password'], (string)$row['pw']);
}

$today = date('Ymd');
$blocked = $member && !empty($member['mb_intercept_date']) && $member['mb_intercept_date'] <= $today;
$left    = $member && !empty($member['mb_leave_date']) && $member['mb_leave_date'] <= $today;

if (!$member || !$pw_ok || $blocked || $left) {
    $record_failure();
    $event = !$member ? 'not_found' : (!$pw_ok ? 'password_failed' : ($blocked ? 'account_blocked' : 'account_deleted'));
    error_log('[auth][app_' . $event . '] account=' . substr(hash('sha256', $login_key), 0, 16) . '; ip=' . $request_ip);
    app_login_fail(401);
}

// Generate SSO token (30 days)
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', time() + 86400 * 30);

// Phase5 fix: use correct column g5_mb_id (NOT mb_id which doesn't exist in sso_tokens)
$login_user_type = 'gnuboard';
$del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = ?");
if ($del) {
    mysqli_stmt_bind_param($del, 'ss', $member['mb_id'], $login_user_type);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$user_type = $login_user_type;

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

<?php
/**
 * POST /api/session_login.php — session-mint bridge for the NestJS API (D2 register, D3 login).
 *
 * Nest does the credential/registration work; only PHP can create the Gnuboard session. Nest calls this
 * endpoint server-side (loopback), forwarding the browser's Cookie header, and relays the Set-Cookie
 * headers of the answer to the browser.
 *
 * Guards: loopback caller only + HMAC-SHA256(secret, "mb_id|mode|remember|ts") with a 60 s window.
 * The secret is SESSION_BRIDGE_SECRET (gangnamunni/.env or environment); empty secret = endpoint disabled.
 * Body (JSON): {mb_id, mode: "register"|"login", remember: 0|1, ts, sig}
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

function sl_fail($code, $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') sl_fail(405, 'method');
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true) || !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) sl_fail(403, 'forbidden');

// .env reader (same rules as includes/mail_helper.php)
$secret = '';
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        $p = strpos($line, '=');
        if ($p !== false && trim(substr($line, 0, $p)) === 'SESSION_BRIDGE_SECRET') $secret = trim(trim(substr($line, $p + 1)), "\"'");
    }
}
if ($secret === '') $secret = (string)(getenv('SESSION_BRIDGE_SECRET') ?: '');
if (strlen($secret) < 32) sl_fail(503, 'disabled');

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) sl_fail(400, 'body');
$mb_id = (string)($in['mb_id'] ?? '');
$mode = (string)($in['mode'] ?? '');
$remember = !empty($in['remember']) ? 1 : 0;
$ts = (int)($in['ts'] ?? 0);
$sig = (string)($in['sig'] ?? '');
if (!preg_match('/^[0-9a-zA-Z_]{3,20}$/', $mb_id) || !in_array($mode, ['register', 'login'], true)) sl_fail(400, 'args');
if (abs(time() - $ts) > 60) sl_fail(403, 'expired');
$expect = hash_hmac('sha256', $mb_id . '|' . $mode . '|' . $remember . '|' . $ts, $secret);
if (!hash_equals($expect, $sig)) sl_fail(403, 'signature');

// init.php may echo <script> tags meant for HTML pages: capture and discard (same as session_me.php)
ob_start();
try {
    require_once __DIR__ . '/../includes/init.php';
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[session_login] init failed: ' . $e->getMessage());
    sl_fail(503, 'session_unavailable');
}
ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');

$member = get_member($mb_id);
if (empty($member['mb_id']) || !empty($member['mb_leave_date']) || !empty($member['mb_intercept_date'])) sl_fail(404, 'member');
if ($mode === 'register' && substr($mb_id, 0, 3) !== 'sb_') sl_fail(400, 'mode');
// A register session may only be minted for an account created in the last 2 minutes.
if ($mode === 'register' && strtotime((string)($member['mb_datetime'] ?? '')) < time() - 120) sl_fail(403, 'not_new');

$now = date('Y-m-d H:i:s');
if ($mode === 'login' && function_exists('run_event')) run_event('login_session_before', $member, false);
if (!(defined('SKIP_SESSION_REGENERATE_ID') && SKIP_SESSION_REGENERATE_ID)) {
    session_regenerate_id(false);
    if (function_exists('session_start_samesite')) session_start_samesite();
}
set_session('ss_mb_id', $mb_id);
if ($mode === 'register') set_session('ss_mb_reg', $mb_id);
generate_mb_key($member);
if (function_exists('update_auth_session_token')) update_auth_session_token($member['mb_datetime']);

if ($mode === 'login') {
    if ($remember) {
        $is_patient_app = isset($_COOKIE['sb_patient_app']) && hash_equals('1', (string)$_COOKIE['sb_patient_app']);
        $life = $is_patient_app ? 86400 * 120 : 86400 * 31;
        $tok = function_exists('create_remember_login_token') ? create_remember_login_token($member, $life) : '';
        if ($tok !== '') { set_cookie('ck_mb_id', $mb_id, $life); set_cookie('ck_auto', $tok, $life); }
        else { set_cookie('ck_mb_id', '', 0); set_cookie('ck_auto', '', 0); error_log('[session_login] remember token creation failed'); }
    } else {
        if (function_exists('revoke_remember_login_token')) revoke_remember_login_token($mb_id);
        set_cookie('ck_mb_id', '', 0);
        set_cookie('ck_auto', '', 0);
    }
    if (function_exists('run_event')) { $after = ''; run_event('member_login_check', $member, $after, false); }
} else {
    // sign-up bonus (ledger decides whether it is enabled) + HMS sync hook, exactly as auth/register.php
    $welcome = 0;
    try {
        global $connect;
        require_once dirname(__DIR__) . '/includes/points_ledger.php';
        $no = points_member_no($connect, $mb_id);
        $pr = points_earn_signup($connect, $no);
        if (!empty($pr['ok']) && empty($pr['skipped'])) { $welcome = (int)$pr['amount']; $_SESSION['reg_welcome_point'] = $welcome; }
    } catch (Throwable $e) {
        error_log('[session_login] signup point award failed: ' . $e->getMessage());
    }
    if (function_exists('run_event')) run_event('register_form_update_after', $mb_id, '');
}
echo json_encode(['ok' => true, 'mode' => $mode], JSON_UNESCAPED_UNICODE);

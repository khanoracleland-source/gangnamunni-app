<?php
// Run against a real MySQL/MariaDB instance with the migrated sso_tokens
// schema (not a mock). No default credentials are baked in — all of these
// are required:
//   G5_MYSQL_HOST=127.0.0.1 G5_MYSQL_PORT=3308 G5_MYSQL_USER=root \
//   G5_MYSQL_PASSWORD='...' G5_MYSQL_DB=hms_saas \
//   php -n tests/regression/patient_app_native_auth.php
//
// Exercises the native accessToken/refreshToken helpers end to end: the
// exchange code (claimed by whichever device submits it first, idempotent
// — same stored payload — for THAT device on retry, rejected for any other
// device, and invalidated by logout before its own expiry), device-bound
// access tokens, the refresh_token renew-in-place path, the device-isolated
// single-use session bridge token (race-safe consumption, and BOTH
// promoters — extend/sso_auto_login.extend.php and gangnamunni's own —
// correctly set $is_member/$member so the first page after promotion does
// not render as a guest), email-certify enforcement, and per-device logout.
namespace NativeAuthRegression;

$host = getenv('G5_MYSQL_HOST');
$port = getenv('G5_MYSQL_PORT');
$user = getenv('G5_MYSQL_USER');
$pass = getenv('G5_MYSQL_PASSWORD');
$db   = getenv('G5_MYSQL_DB');

if ($host === false || $port === false || $user === false || $pass === false || $db === false) {
    fwrite(STDERR, "Set G5_MYSQL_HOST, G5_MYSQL_PORT, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB — no defaults on purpose (do not hardcode DB credentials here).\n");
    exit(1);
}
$port = (int)$port;
// Ephemeral test-only key; never use this generated value in production.
putenv('APP_NATIVE_AUTH_KEY=' . base64_encode(random_bytes(32)));

$connect = mysqli_connect($host, $user, $pass, $db, $port);
if (!$connect) {
    fwrite(STDERR, "DB connection failed: " . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

// Buffered so sso_create_token()'s setcookie() (harmless in the real HTTP
// response, which hasn't echoed a body yet at that point) doesn't warn here
// just because this CLI script already printed earlier "ok" lines.
ob_start();

define('G5_SERVER_TIME', time());
define('G5_COOKIE_DOMAIN', ''); // set_cookie()'s default param — never loaded outside the full Gnuboard bootstrap
define('G5_TABLE_PREFIX', 'g5_'); // generate_mb_key()/update_auth_session_token()'s key derivation
define('G5_DISPLAY_SQL_ERROR', false); // sql_fetch()'s error-display branch, checked by get_member()
define('G5_COLLECT_QUERY', false); // sql_query()'s profiling branch
define('G5_MYSQLI_USE', true); // sql_query()'s driver branch — this codebase uses mysqli
$GLOBALS['g5']['member_table'] = 'g5_member'; // get_member()'s table name, normally set by common.php's bootstrap
$GLOBALS['g5']['connect_db'] = $connect; // sql_query()'s default link when none is passed explicitly
if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../../hairwang_full/lib/hook.lib.php'; // run_event()/run_replace(), needed by set_session()
require_once __DIR__ . '/../../hairwang_full/lib/get_data.lib.php'; // get_permission_debug_show(), needed by sql_query() inside get_member()
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/app_native_auth.php'; // pulls in common.lib.php
app_native_auth_configure_connection($connect);
app_native_auth_ensure_config($connect);
$_SERVER['HTTP_USER_AGENT'] = 'NativeAuthRegression';

function check($condition, $message) {
    if (!$condition) throw new \RuntimeException("FAIL: $message");
    echo "ok - $message\n";
}

$test_mb_id = 'natv_' . bin2hex(random_bytes(6)); // g5_mb_id is varchar(20)
$device_a = str_repeat('a', 64);
$device_b = str_repeat('b', 64);

// Clean slate for this synthetic account.
mysqli_query($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = '{$test_mb_id}'");

// --- Exchange code: claimed by whichever device submits it first, then
// idempotent for THAT device only, rejected for any other device, and
// invalidated by logout even before its own natural expiry. ---
$code = app_issue_exchange_code($connect, $test_mb_id);
check(strlen($code) === 64, 'exchange code is issued');

$claim1 = app_consume_exchange_code($connect, $code, $device_a);
check($claim1['mb_id'] === $test_mb_id, 'exchange code resolves to mb_id for the first (claiming) device');
check($claim1['payload'] === null, 'no payload exists yet before the endpoint stores one');

// app_auth_exchange.php stores the issued token bundle right after a
// successful first claim — simulate that here.
$fakePayload = ['access_token' => bin2hex(random_bytes(32)), 'refresh_token' => bin2hex(random_bytes(32))];
app_store_exchange_payload($connect, $code, $fakePayload);

// Requirement 1: same code + same device -> the exact previous result,
// recovering a lost response without minting a second, orphaned token set.
$claim2 = app_consume_exchange_code($connect, $code, $device_a);
check($claim2['mb_id'] === $test_mb_id, 'same code + same device resolves again (retry after a lost response)');
check($claim2['payload'] !== null && app_decrypt_exchange_payload($code, $claim2['payload']) === $fakePayload,
    'same code + same device returns the exact previously-issued token bundle, not a fresh one');

// Requirement 2: same code + a different device -> rejected outright, even
// though the code itself has not expired.
$claimOtherDevice = app_consume_exchange_code($connect, $code, $device_b);
check($claimOtherDevice['mb_id'] === '', 'same code presented by a different device is rejected');

// Requirement 3: logging out the claiming device invalidates the code
// immediately — the remaining time-to-expiry no longer matters.
app_revoke_native_auth_tokens($connect, $test_mb_id, $device_a);
$claimAfterLogout = app_consume_exchange_code($connect, $code, $device_a);
check($claimAfterLogout['mb_id'] === '', 'after logout, even the claiming device can no longer use the previous code');

check(app_consume_exchange_code($connect, 'not-a-real-code', $device_a)['mb_id'] === '', 'garbage code is rejected');

// An expired code is rejected even though its row has not been deleted yet.
$code2 = app_issue_exchange_code($connect, $test_mb_id);
mysqli_query($connect, "UPDATE sso_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
    WHERE token = '" . hash('sha256', $code2) . "' AND user_type = 'patient_app_exchange'");
check(app_consume_exchange_code($connect, $code2, $device_a)['mb_id'] === '', 'an expired exchange code is rejected');

// A never-yet-claimed code presented by two different devices: only the
// first claimant may ever succeed, regardless of call order.
$code3 = app_issue_exchange_code($connect, $test_mb_id);
$claimRace1 = app_consume_exchange_code($connect, $code3, $device_a);
$claimRace2 = app_consume_exchange_code($connect, $code3, $device_b);
check($claimRace1['mb_id'] === $test_mb_id, 'the first device to claim an unclaimed code succeeds');
check($claimRace2['mb_id'] === '', 'a second, different device can never claim that same code afterward');

// --- Parallel exchange from the SAME device must not both see payload=null
// and mint two separate token sets. app_consume_exchange_code()'s SELECT ...
// FOR UPDATE must be held for the whole claim+mint+store, not just the read. ---
$code4 = app_issue_exchange_code($connect, $test_mb_id);
$connD = mysqli_connect($host, $user, $pass, $db, $port);
app_native_auth_configure_connection($connD);
mysqli_query($connD, 'SET SESSION innodb_lock_wait_timeout = 1');

mysqli_begin_transaction($connect);
$claimD1 = app_consume_exchange_code($connect, $code4, $device_a);
check($claimD1['mb_id'] === $test_mb_id && $claimD1['payload'] === null,
    'connection A claims the code and holds its row lock (mint not yet finished)');

// A second request from the SAME device, concurrently, must be blocked by
// A's still-open transaction — never independently see payload=null too.
$blockedSameDevice = false;
try {
    app_native_auth_transaction($connD, function () use ($connD, $code4, $device_a) {
        return app_consume_exchange_code($connD, $code4, $device_a);
    });
} catch (\Throwable $e) {
    $blockedSameDevice = true;
}
check($blockedSameDevice, 'a parallel same-device exchange attempt is blocked while A is still minting, not allowed to race');

// A finishes its mint and stores the payload.
$payloadD = ['access_token' => bin2hex(random_bytes(32)), 'refresh_token' => bin2hex(random_bytes(32))];
app_store_exchange_payload($connect, $code4, $payloadD);
mysqli_commit($connect);

// Now that A has committed, B's retry sees the exact stored result instead
// of minting its own — confirming the lock, not luck, prevented the race.
$claimD2 = app_native_auth_transaction($connD, function () use ($connD, $code4, $device_a) {
    return app_consume_exchange_code($connD, $code4, $device_a);
});
check($claimD2['payload'] !== null && app_decrypt_exchange_payload($code4, $claimD2['payload']) === $payloadD,
    'the retry after A commits receives the exact bundle A stored, never a second independently-minted one');
mysqli_close($connD);

// --- A failure partway through (e.g. persistence) must roll back
// EVERYTHING already done in that attempt — the claim, any tokens already
// issued, all of it — not leave a half-finished, orphaned state behind. ---
$failMbId = 'natvfail_' . bin2hex(random_bytes(4));
$failCode = app_issue_exchange_code($connect, $failMbId);
$rolledBack = false;
try {
    app_native_auth_transaction($connect, function () use ($connect, $failCode, $failMbId, $device_a) {
        app_consume_exchange_code($connect, $failCode, $device_a);
        app_issue_access_token($connect, $failMbId, $device_a);
        $bundle = ['access_token' => 'x', 'refresh_token' => 'y'];
        app_store_exchange_payload($connect, $failCode, $bundle);
        // A second persistence attempt fails (payload is no longer NULL) —
        // simulates any failure partway through and must unwind everything.
        app_store_exchange_payload($connect, $failCode, $bundle);
    });
} catch (\Throwable $e) {
    $rolledBack = true;
}
check($rolledBack, 'a persistence failure partway through the exchange throws instead of reporting success');

$leftoverAccess = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id = '{$failMbId}' AND user_type = 'patient_app_access'"));
check((int)$leftoverAccess['n'] === 0, 'the failed exchange leaves no orphaned access token behind');

$failRow = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT user_agent, payload FROM sso_tokens WHERE token = '" . hash('sha256', $failCode) . "' AND user_type = 'patient_app_exchange'"));
check($failRow['user_agent'] === null && $failRow['payload'] === null,
    'the failed exchange also rolls back the claim itself — the code is unclaimed again, not stuck half-issued');
mysqli_query($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = '{$failMbId}'");

// --- Access token: device-bound, short-lived ---
$access = app_issue_access_token($connect, $test_mb_id, $device_a);
check(strlen($access['token']) === 64, 'access token is issued');
check(app_verify_access_token($connect, $access['token'], $device_a) === $test_mb_id, 'access token verifies on its own device');
check(app_verify_access_token($connect, $access['token'], $device_b) === '', 'access token rejected on another device');
check(app_verify_access_token($connect, 'deadbeef', $device_a) === '', 'malformed access token rejected');

// Re-issuing for the same device replaces the old one instead of piling up rows.
$access2 = app_issue_access_token($connect, $test_mb_id, $device_a);
check($access2['token'] !== $access['token'], 'reissuing access token gives a new value');
check(app_verify_access_token($connect, $access['token'], $device_a) === '', 'old access token is retired after reissue');
check(app_verify_access_token($connect, $access2['token'], $device_a) === $test_mb_id, 'new access token verifies');

// --- Refresh token: reuses the already-hardened remember-login mechanism ---
$member = ['mb_id' => $test_mb_id];
$refresh_opts = ['is_patient_app' => true, 'device_id' => $device_a];
$refresh = create_remember_login_token($member, APP_REFRESH_TOKEN_LIFETIME, $refresh_opts);
check(strlen($refresh) === 64, 'refresh token is issued');
check(verify_remember_login_token($test_mb_id, $refresh, $refresh_opts), 'refresh token verifies for its device');
check(!verify_remember_login_token($test_mb_id, $refresh, ['is_patient_app' => true, 'device_id' => $device_b]), 'refresh token rejected on another device');

// Renewal keeps the same value (lost-response safety) rather than rotating it.
$renewed = create_remember_login_token($member, APP_REFRESH_TOKEN_LIFETIME + 86400,
    array_merge($refresh_opts, ['existing_token' => $refresh]));
check($renewed === $refresh, 'refresh renewal preserves the token value');
check(verify_remember_login_token($test_mb_id, $refresh, $refresh_opts), 'renewed refresh token still verifies');

// --- app_renew_native_refresh_token() vs app_revoke_native_auth_tokens():
// a concurrent logout must not be lost to a race with an in-flight refresh,
// and — the critical direction — a refresh racing a logout that already won
// must fail closed (401) rather than minting a brand-new replacement token
// for a credential that no longer exists. Both share the SAME existing row
// as their lock point, so whichever gets there first serializes the other. ---
$connE = mysqli_connect($host, $user, $pass, $db, $port);
app_native_auth_configure_connection($connE);
mysqli_query($connE, 'SET SESSION innodb_lock_wait_timeout = 1');

// Case 1: refresh locks (renews) first; a concurrent logout must wait, then
// still revoke the token once refresh commits — not lose it to the race.
$raceRefresh = create_remember_login_token($member, APP_REFRESH_TOKEN_LIFETIME, $refresh_opts);
mysqli_begin_transaction($connect);
$renewedRace = app_renew_native_refresh_token($connect, $test_mb_id, $raceRefresh, $device_a);
check($renewedRace === $raceRefresh, 'refresh renews the existing row and holds its lock');
$logoutBlocked = false;
try {
    app_revoke_native_auth_tokens($connE, $test_mb_id, $device_a);
} catch (\Throwable $e) {
    $logoutBlocked = true;
}
check($logoutBlocked, 'a concurrent logout is blocked while a refresh is still renewing the same token');
mysqli_commit($connect);
app_revoke_native_auth_tokens($connect, $test_mb_id, $device_a);
check(!verify_remember_login_token($test_mb_id, $raceRefresh, $refresh_opts), 'logout still revokes the token after the concurrent refresh committed');

// Case 2 (the critical direction): logout deletes the row first; a refresh
// already mid-flight must fail closed instead of resurrecting the login by
// minting a replacement token for a credential that is being removed.
$raceRefresh2 = create_remember_login_token($member, APP_REFRESH_TOKEN_LIFETIME, $refresh_opts);
mysqli_begin_transaction($connect);
$hashedRaceToken2 = hash('sha256', $raceRefresh2);
$delRace = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_remember'");
mysqli_stmt_bind_param($delRace, 's', $hashedRaceToken2);
mysqli_stmt_execute($delRace);
mysqli_stmt_close($delRace);
// (delete left uncommitted here — simulates a logout transaction mid-flight)
$refreshBlocked = false;
try {
    app_native_auth_transaction($connE, function () use ($connE, $test_mb_id, $raceRefresh2, $device_a) {
        return app_renew_native_refresh_token($connE, $test_mb_id, $raceRefresh2, $device_a);
    });
} catch (\Throwable $e) {
    $refreshBlocked = true;
}
check($refreshBlocked, 'a concurrent refresh is blocked while a logout is still deleting the same token');
mysqli_commit($connect); // logout's delete finalizes

$refreshRejectedCleanly = false;
try {
    app_native_auth_transaction($connE, function () use ($connE, $test_mb_id, $raceRefresh2, $device_a) {
        return app_renew_native_refresh_token($connE, $test_mb_id, $raceRefresh2, $device_a);
    });
} catch (\DomainException $e) {
    $refreshRejectedCleanly = ($e->getMessage() === 'refresh_invalid');
}
check($refreshRejectedCleanly, 'after logout wins the race, the refresh fails closed (401) instead of minting a replacement token');

$leftoverRefresh = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id = '{$test_mb_id}' AND user_type = 'patient_app_remember'"));
check((int)$leftoverRefresh['n'] === 0, 'no replacement refresh token exists after the logout won the race — the account stays logged out');
mysqli_close($connE);

// --- Session bridge: device-isolated and single-use ---
$bridge_a = app_issue_session_bridge_token($connect, $test_mb_id, $device_a);
check(strlen($bridge_a) === 64, 'session bridge token is issued');
$row = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT user_type FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_a) . "'"));
check($row && $row['user_type'] === 'patient_app_bridge', 'bridge token uses its own dedicated type, not the shared gnuboard SSO channel');

// Issuing device B's bridge token must not delete device A's still-unused one.
$bridge_b = app_issue_session_bridge_token($connect, $test_mb_id, $device_b);
$stillThere = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_a) . "'"));
check((bool)$stillThere, "issuing another device's bridge token does not delete this device's unused bridge token");
check($bridge_a !== $bridge_b, 'each device gets its own bridge token value');

// Wrong-device attempts must preserve the unused bridge for its owner.
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/sso_auto_login.php';
$is_member = false;
$member = [];
if (!session_id()) @session_start();
$_COOKIE['sb_sso_token'] = $bridge_b; // belongs to device_b
$_COOKIE['sb_app_device'] = $device_a; // presented from the wrong device
check(sso_try_auto_login($connect) === false, 'sso_auto_login rejects a bridge token presented from the wrong device');
$stillThereAfterMismatch = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_b) . "'"));
check((bool)$stillThereAfterMismatch, 'a device-mismatched attempt does not consume the bridge token');
unset($_COOKIE['sb_sso_token'], $_COOKIE['sb_app_device']);

// --- Full success path: a real g5_member row, session promotion, single-use ---
mysqli_query($connect, "DELETE FROM g5_member WHERE mb_id = '{$test_mb_id}'");
$test_email = $test_mb_id . '@example.test';
$insertMember = mysqli_prepare($connect,
    "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_email, mb_signature, mb_memo, mb_profile, mb_email_certify)
     VALUES (?, '', 'Native Auth Test', 'nativetest', ?, '', '', '', NOW())");
mysqli_stmt_bind_param($insertMember, 'ss', $test_mb_id, $test_email);
mysqli_stmt_execute($insertMember);
mysqli_stmt_close($insertMember);

$bridge_c = app_issue_session_bridge_token($connect, $test_mb_id, $device_a);
$_COOKIE['sb_sso_token'] = $bridge_c;
$_COOKIE['sb_app_device'] = $device_a;
$is_member = false;
$member = [];
check(sso_try_auto_login($connect) === true, 'sso_auto_login promotes a matching-device bridge token to a real session');
check($is_member === true && $member['mb_id'] === $test_mb_id, 'global $is_member/$member reflect the promoted session');
$bridgeCGone = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_c) . "'"));
check(!$bridgeCGone, 'the bridge token is deleted (single-use) after a successful promotion');
unset($_COOKIE['sb_sso_token'], $_COOKIE['sb_app_device']);

// --- extend/sso_auto_login.extend.php's own promoter must ALSO set
// $is_member/$member (not just $_SESSION) — it runs during common.php's
// bootstrap, AFTER the "회원, 비회원 구분" block that decides those globals
// for the CURRENT request. Setting only the session left the very first
// page after a fresh app-token verification rendering as a guest; the
// gangnamunni-side helper above couldn't fix it either because by the time
// it ran, this hook had already deleted the (single-use) bridge token. ---
require_once __DIR__ . '/../../hairwang_full/extend/sso_auto_login.extend.php';
$bridge_e = app_issue_session_bridge_token($connect, $test_mb_id, $device_a);
$_COOKIE['sb_sso_token'] = $bridge_e;
$_COOKIE['sb_app_device'] = $device_a;
$is_member = false;
$member = [];
$is_admin = '';
\_sso_try_auto_login(); // defined in the global namespace (that file has no namespace declaration)
check($is_member === true && $member['mb_id'] === $test_mb_id,
    "extend/sso_auto_login.extend.php's promoter sets \$is_member/\$member itself, not just \$_SESSION");
check(array_key_exists('mb_dir', $member) && $member['mb_dir'] === substr($test_mb_id, 0, 2),
    '$member is shaped the same way common.php\'s own block would have shaped it (mb_dir present)');
$bridgeEGone = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_e) . "'"));
check(!$bridgeEGone, 'this promoter also consumes the bridge token (single-use)');
unset($_COOKIE['sb_sso_token'], $_COOKIE['sb_app_device']);

// --- Concurrent claim of the SAME (still valid) bridge token: only one side may win ---
$bridge_d = app_issue_session_bridge_token($connect, $test_mb_id, $device_a);
$connC = mysqli_connect($host, $user, $pass, $db, $port);
app_native_auth_configure_connection($connC);
mysqli_query($connC, 'SET SESSION innodb_lock_wait_timeout = 1');

mysqli_begin_transaction($connect);
$lockStmt2 = mysqli_prepare($connect, "SELECT g5_mb_id FROM sso_tokens WHERE token = ? LIMIT 1 FOR UPDATE");
mysqli_stmt_bind_param($lockStmt2, 's', $bridge_d);
mysqli_stmt_execute($lockStmt2);
$lockRow2 = mysqli_fetch_assoc(mysqli_stmt_get_result($lockStmt2));
mysqli_stmt_close($lockStmt2);
check($lockRow2 && $lockRow2['g5_mb_id'] === $test_mb_id, 'connection A locks the bridge token row (session promotion not yet committed)');

$_COOKIE['sb_sso_token'] = $bridge_d;
$_COOKIE['sb_app_device'] = $device_a;
$is_member = false;
$member = [];
check(sso_try_auto_login($connC) === false, 'connection C cannot claim the same bridge token while A holds it (blocked/timed out, not double-promoted)');

$delStmt2 = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge'");
mysqli_stmt_bind_param($delStmt2, 's', $bridge_d);
mysqli_stmt_execute($delStmt2);
$aAffected2 = mysqli_stmt_affected_rows($delStmt2);
mysqli_stmt_close($delStmt2);
mysqli_commit($connect);
check($aAffected2 === 1, 'connection A is the one that actually claims the bridge token');
unset($_COOKIE['sb_sso_token'], $_COOKIE['sb_app_device']);
mysqli_close($connC);

// --- Email-certify: the API context now actually enforces the site's config,
// instead of is_use_email_certify() silently reading an always-empty $config ---
app_native_auth_ensure_config($connect);
check(isset($GLOBALS['config']['cf_use_email_certify']), '$config is now populated from g5_config (previously never loaded in this API context)');

$uncertified = ['mb_id' => $test_mb_id, 'mb_email_certify' => '0000-00-00 00:00:00'];
$certified = ['mb_id' => $test_mb_id, 'mb_email_certify' => date('Y-m-d H:i:s')];
check(app_native_auth_member_is_blocked($uncertified, $connect) === false,
    "uncertified member is allowed while this instance's cf_use_email_certify is off");

// Simulate the site requiring certification, without touching the DB row —
// app_native_auth_ensure_config() only loads once per process (matches one
// request in production), so this override sticks for the rest of this run.
$GLOBALS['config']['cf_use_email_certify'] = 1;
check(app_native_auth_member_is_blocked($uncertified, $connect) === true,
    'uncertified member is blocked once email certification is required — same rule regular login enforces');
check(app_native_auth_member_is_blocked($certified, $connect) === false,
    'a certified member is still allowed when certification is required');
$GLOBALS['config']['cf_use_email_certify'] = 0;

mysqli_query($connect, "DELETE FROM g5_member WHERE mb_id = '{$test_mb_id}'");
$member = ['mb_id' => $test_mb_id]; // sso_try_auto_login() needed $member as a global; restore it for the rest of this script

// --- Logout: revokes this device's native tokens without touching the other device ---
$access_b = app_issue_access_token($connect, $test_mb_id, $device_b);
$refresh_b = create_remember_login_token($member, APP_REFRESH_TOKEN_LIFETIME,
    ['is_patient_app' => true, 'device_id' => $device_b]);
app_revoke_native_auth_tokens($connect, $test_mb_id, $device_a);
check(app_verify_access_token($connect, $access2['token'], $device_a) === '', 'logout revokes this device access token');
check(!verify_remember_login_token($test_mb_id, $refresh, $refresh_opts), 'logout revokes this device refresh token');
$bridgeAGone = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_a) . "'"));
check(!$bridgeAGone, 'logout also revokes this device bridge token');
check(app_verify_access_token($connect, $access_b['token'], $device_b) === $test_mb_id, 'logout preserves another device access token');
check(verify_remember_login_token($test_mb_id, $refresh_b, ['is_patient_app' => true, 'device_id' => $device_b]), 'logout preserves another device refresh token');
$bridgeBStillThere = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT 1 AS x FROM sso_tokens WHERE token = '" . mysqli_real_escape_string($connect, $bridge_b) . "'"));
check((bool)$bridgeBStillThere, 'logout preserves another device bridge token');

mysqli_query($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = '{$test_mb_id}'");
mysqli_query($connect, "DELETE FROM g5_member WHERE mb_id = '{$test_mb_id}'");
mysqli_close($connect);

echo "PASS: device-claimed idempotent exchange codes (same device recovers, other device rejected, logout invalidates), race-safe session-bridge consumption with correct \$is_member propagation from both promoters, device-bound access tokens, refresh renew-in-place, per-device logout, email-certify enforcement\n";

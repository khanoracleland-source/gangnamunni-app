<?php
// Run against a real MySQL/MariaDB instance with the migrated schema — the
// mb_cred_epoch / cred_epoch columns from
// hairwang_full/gangnamunni/sql/auth_credential_epoch_20261015.sql must exist.
// No default credentials are baked in; all of these are required:
//   G5_MYSQL_HOST=127.0.0.1 G5_MYSQL_PORT=3306 G5_MYSQL_USER=root \
//   G5_MYSQL_PASSWORD='...' G5_MYSQL_DB=hms_saas \
//   php -n tests/regression/credential_revocation.php
//
// Covers the "password change revokes everything" guarantee: changing (or
// resetting) a password must invalidate the remember cookie, the patient
// app's refresh/access/bridge/exchange tokens, every SSO channel token, the
// sessions living on other devices, the HMS (Laravel Sanctum) API tokens,
// and any unused temporary password still sitting in mb_lost_certify —
// while leaving other accounts' credentials untouched.
namespace CredentialRevocationRegression;

$host = getenv('G5_MYSQL_HOST');
$port = getenv('G5_MYSQL_PORT');
$user = getenv('G5_MYSQL_USER');
$pass = getenv('G5_MYSQL_PASSWORD');
$db   = getenv('G5_MYSQL_DB');

if ($host === false || $port === false || $user === false || $pass === false || $db === false) {
    fwrite(STDERR, "Set G5_MYSQL_HOST, G5_MYSQL_PORT, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB — no defaults on purpose (do not hardcode DB credentials here).\n");
    exit(1);
}
$port = (int) $port;

$connect = mysqli_connect($host, $user, $pass, $db, $port);
if (!$connect) {
    fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');
mysqli_query($connect, "SET time_zone = '+09:00'");

define('G5_SERVER_TIME', time());
define('G5_COOKIE_DOMAIN', '');
define('G5_TABLE_PREFIX', 'g5_');
define('G5_DISPLAY_SQL_ERROR', false);
define('G5_COLLECT_QUERY', false);
define('G5_MYSQLI_USE', true);
$GLOBALS['g5']['member_table'] = 'g5_member';
$GLOBALS['g5']['connect_db'] = $connect;
$GLOBALS['connect'] = $connect;
if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../../hairwang_full/lib/hook.lib.php';
require_once __DIR__ . '/../../hairwang_full/lib/get_data.lib.php';
require_once __DIR__ . '/../../hairwang_full/lib/common.lib.php';

$_SERVER['HTTP_USER_AGENT'] = 'CredentialRevocationRegression';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
// Buffered so set_cookie()/setcookie() inside the helpers don't warn just
// because this CLI script already printed earlier "ok" lines.
ob_start();

/**
 * @param mixed $condition
 * @param mixed $message
 */
function check($condition, $message) {
    if (!$condition) {
        ob_end_flush();
        throw new \RuntimeException("FAIL: $message");
    }
    echo "ok - $message\n";
}

/**
 * @param mixed $connect
 * @param mixed $table
 * @param mixed $column
 */
function column_exists($connect, $table, $column) {
    $res = mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '" . mysqli_real_escape_string($connect, $column) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * @param mixed $connect
 * @param mixed $mb_id
 */
function token_count($connect, $mb_id) {
    $row = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) AS c FROM sso_tokens WHERE g5_mb_id = '" . mysqli_real_escape_string($connect, $mb_id) . "'"));
    return (int) $row['c'];
}

/**
 * @param mixed $connect
 * @param mixed $mb_id
 */
function member_row($connect, $mb_id) {
    $row = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM g5_member WHERE mb_id = '" . mysqli_real_escape_string($connect, $mb_id) . "'"));
    if (!$row) throw new \RuntimeException("FAIL: fixture member {$mb_id} is missing");
    return $row;
}

/**
 * @param mixed $connect
 * @param mixed $hms_user_id
 */
function pat_count($connect, $hms_user_id) {
    $row = mysqli_fetch_assoc(mysqli_query($connect,
        'SELECT COUNT(*) AS c FROM personal_access_tokens WHERE tokenable_id = ' . (int) $hms_user_id));
    return (int) $row['c'];
}

/**
 * @param mixed $connect
 * @param mixed $mb_id
 * @param mixed $user_type
 * @param mixed $user_agent
 * @param mixed $raw
 */
function insert_token($connect, $mb_id, $user_type, $user_agent, $raw = null) {
    $raw = $raw ?: bin2hex(random_bytes(32));
    $stmt = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at)
         VALUES (?, ?, ?, '127.0.0.1', ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
    mysqli_stmt_bind_param($stmt, 'ssss', $raw, $user_type, $mb_id, $user_agent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $raw;
}

check(column_exists($connect, 'g5_member', 'mb_cred_epoch'),
    'g5_member.mb_cred_epoch exists (apply gangnamunni/sql/auth_credential_epoch_20261015.sql first)');

$victim    = 'rev_' . bin2hex(random_bytes(5)); // g5_mb_id is varchar(20)
$bystander = 'byst_' . bin2hex(random_bytes(5));
$device    = str_repeat('d', 64);
$victim_hms_user_id    = 900000000 + random_int(1, 9999);
$bystander_hms_user_id = $victim_hms_user_id + 1;

$cleanup = function () use ($connect, $victim, $bystander, $victim_hms_user_id, $bystander_hms_user_id) {
    foreach ([$victim, $bystander] as $id) {
        $e = mysqli_real_escape_string($connect, $id);
        mysqli_query($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = '{$e}'");
        mysqli_query($connect, "DELETE FROM member_sync WHERE mb_id = '{$e}'");
        mysqli_query($connect, "DELETE FROM g5_member WHERE mb_id = '{$e}'");
    }
    mysqli_query($connect, 'DELETE FROM personal_access_tokens WHERE tokenable_id IN ('
        . (int) $victim_hms_user_id . ', ' . (int) $bystander_hms_user_id . ')');
};
$cleanup();

try {
    // ── Fixtures ────────────────────────────────────────────────────────
    foreach ([[$victim, $victim_hms_user_id], [$bystander, $bystander_hms_user_id]] as $pair) {
        list($id, $hms_id) = $pair;
        $stmt = mysqli_prepare($connect,
            "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_email, mb_level, mb_datetime, mb_email_certify)
             VALUES (?, 'dummy-hash', 'Regression', ?, ?, 2, NOW(), NOW())");
        $email = $id . '@example.test';
        mysqli_stmt_bind_param($stmt, 'sss', $id, $id, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($connect,
            "INSERT INTO member_sync (mb_id, hms_user_id, sync_status) VALUES (?, ?, 'synced')");
        mysqli_stmt_bind_param($stmt, 'si', $id, $hms_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($connect,
            "INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at)
             VALUES ('App\\\\Models\\\\User', ?, 'regression', ?, '[\"*\"]', NOW(), NOW())");
        $pat = hash('sha256', $id . 'pat');
        mysqli_stmt_bind_param($stmt, 'is', $hms_id, $pat);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // An unused temporary password from a "forgot password" mail the attacker
    // may have triggered — it must not survive the real password change.
    mysqli_query($connect, "UPDATE g5_member SET mb_lost_certify = '"
        . str_repeat('a', 32) . " " . (time() + 3600) . " pending-hash' WHERE mb_id = '"
        . mysqli_real_escape_string($connect, $victim) . "'");

    $victim_member = member_row($connect, $victim);
    check((int) $victim_member['mb_cred_epoch'] === 0, 'a fresh account starts at credential epoch 0');

    // ── Session binding ────────────────────────────────────────────────
    set_session('ss_mb_id', $victim);
    check((int) get_session('ss_cred_epoch') === 0,
        'creating a login session records the account\'s current credential epoch');
    check(auth_credential_epoch_is_stale($victim_member) === false,
        'a session created at the current epoch is not stale');

    // A session that predates this feature has no ss_cred_epoch at all; it
    // reads as 0 and must keep working until a password actually changes
    // (no mass logout on deploy).
    unset($_SESSION['ss_cred_epoch']);
    check(auth_credential_epoch_is_stale($victim_member) === false,
        'a pre-existing session without ss_cred_epoch survives while the epoch is still 0');
    set_session('ss_mb_id', $victim);

    // ── Credentials across every channel ───────────────────────────────
    $web_remember = create_remember_login_token(['mb_id' => $victim], 86400 * 31, ['is_patient_app' => false]);
    check($web_remember !== '' && verify_remember_login_token($victim, $web_remember, ['is_patient_app' => false]),
        'a web remember-me token is issued and verifies');

    $device_binding = 'patient-app-device:' . hash('sha256', strtolower($device));
    $app_refresh = insert_token($connect, $victim, 'patient_app_remember', $device_binding);
    insert_token($connect, $victim, 'patient_app_access', $device_binding);
    insert_token($connect, $victim, 'patient_app_bridge', $device_binding);
    insert_token($connect, $victim, 'patient_app_exchange', $device_binding);
    insert_token($connect, $victim, 'gnuboard', 'legacy-sso');
    insert_token($connect, $victim, 'hms', 'legacy-sso');
    check(token_count($connect, $victim) >= 7, 'remember, app refresh/access/bridge/exchange and SSO tokens all exist');

    $bystander_remember = create_remember_login_token(['mb_id' => $bystander], 86400 * 31, ['is_patient_app' => false]);
    insert_token($connect, $bystander, 'patient_app_remember', $device_binding);
    $bystander_tokens_before = token_count($connect, $bystander);

    // ── The password change ────────────────────────────────────────────
    check(revoke_all_auth_credentials($connect, $victim) === true, 'revoke_all_auth_credentials() reports success');

    check(token_count($connect, $victim) === 0,
        'every sso_tokens row for the account is gone (remember + app refresh/access/bridge/exchange + SSO)');
    check(verify_remember_login_token($victim, $web_remember, ['is_patient_app' => false]) === false,
        'the old web remember-me cookie no longer verifies');
    check(verify_remember_login_token($victim, $app_refresh, ['is_patient_app' => true, 'device_id' => $device]) === false,
        'the old patient-app refresh token no longer verifies');
    check(pat_count($connect, $victim_hms_user_id) === 0, 'the account\'s HMS (Sanctum) API tokens are deleted');

    $after = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT mb_cred_epoch, mb_lost_certify FROM g5_member WHERE mb_id = '" . mysqli_real_escape_string($connect, $victim) . "'"));
    check((int) $after['mb_cred_epoch'] === 1, 'the credential epoch is bumped');
    check($after['mb_lost_certify'] === '', 'an unused temporary password in mb_lost_certify is cleared');

    // Other accounts are untouched.
    check(token_count($connect, $bystander) === $bystander_tokens_before,
        'another account\'s tokens are untouched');
    check(verify_remember_login_token($bystander, $bystander_remember, ['is_patient_app' => false]) === true,
        'another account\'s remember-me cookie still verifies');
    check(pat_count($connect, $bystander_hms_user_id) === 1, 'another account\'s HMS API token is untouched');

    // ── Sessions on other devices ──────────────────────────────────────
    // Those sessions still hold the pre-change epoch; common.php drops them.
    $victim_member_after = member_row($connect, $victim);
    $_SESSION['ss_cred_epoch'] = '0';
    check(auth_credential_epoch_is_stale($victim_member_after) === true,
        'a session created before the password change is detected as stale (other devices are logged out)');

    // ── The current request's own session and cookies ──────────────────
    set_session('ss_mb_id', $victim);
    check((int) get_session('ss_cred_epoch') === 1, 'a fresh login after the change binds the new epoch');
    check(auth_credential_epoch_is_stale(member_row($connect, $victim)) === false,
        'that fresh session is not stale');

    revoke_all_auth_credentials($connect, $victim);
    check(get_session('ss_mb_id') === '', 'revoking also clears the session of the request that changed the password');

    // keep_current_session is for an admin resetting someone else's password.
    set_session('ss_mb_id', $victim);
    revoke_all_auth_credentials($connect, $victim, ['keep_current_session' => true]);
    check(get_session('ss_mb_id') === $victim,
        'keep_current_session leaves the acting request\'s session alone (admin resetting another account)');

    // ── Guards ─────────────────────────────────────────────────────────
    check(revoke_all_auth_credentials($connect, '') === false, 'an empty mb_id is rejected');
    check(revoke_all_auth_credentials($connect, "bad' id") === false, 'a non-identifier mb_id is rejected');

    ob_end_flush();
    echo "\nALL PASS\n";
} finally {
    $cleanup();
}

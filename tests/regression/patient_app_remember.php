<?php
// Run: php -n tests/regression/patient_app_remember.php
// Execute the production helpers with SQLite-backed mysqli shims, without
// booting Gnuboard or requiring production credentials. Time is controllable.
namespace RememberLoginRegression;

const G5_SERVER_TIME = 1800000000;
const LIFETIME = 120 * 86400;
$now = G5_SERVER_TIME;
$failInsert = false;
$failRenew = false;
$connect = new \PDO('sqlite::memory:');
$connect->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$connect->exec('CREATE TABLE sso_tokens (token TEXT PRIMARY KEY, user_type TEXT, g5_mb_id TEXT, ip_address TEXT, user_agent TEXT, expires_at TEXT, created_at TEXT)');

function mysqli_prepare($db, $sql) {
    global $now, $failInsert, $failRenew;
    if (($failInsert && strpos($sql, 'INSERT') === 0)
        || ($failRenew && strpos($sql, 'UPDATE') === 0)) return false;
    $sql = str_replace(['NOW()', 'GREATEST('], ["'" . date('Y-m-d H:i:s', $now) . "'", 'MAX('], $sql);
    return (object)['statement' => $db->prepare($sql), 'params' => []];
}
function mysqli_stmt_bind_param($stmt, $types, &...$params) { $stmt->params = $params; }
function mysqli_stmt_execute($stmt) { return $stmt->statement->execute($stmt->params); }
function mysqli_stmt_close($stmt) {}
function mysqli_stmt_get_result($stmt) { return $stmt->statement; }
function mysqli_fetch_assoc($result) { return $result->fetch(\PDO::FETCH_ASSOC); }
function mysqli_error($db) { return 'simulated failure'; }
function mysqli_stmt_error($stmt) { return 'simulated failure'; }
function get_cookie($name) { return $_COOKIE[$name] ?? ''; }

$source = file_get_contents(__DIR__ . '/../../hairwang_full/lib/common.lib.php');
$start = strpos($source, 'function remember_login_diagnostic(');
// Find the function's closing brace with the tokenizer, including nested blocks.
$tail = substr($source, $start);
$tokens = token_get_all('<?php ' . $tail);
$code = ''; $depth = 0; $functions = 0;
foreach (array_slice($tokens, 1) as $token) {
    $text = is_array($token) ? $token[1] : $token;
    if (is_array($token) && $token[0] === T_FUNCTION) $functions++;
    if ($text === '{') $depth++;
    if ($text === '}') $depth--;
    $code .= $text;
    if ($functions === 4 && $text === '}' && $depth === 0) break;
}
eval('namespace ' . __NAMESPACE__ . ';' . $code);
function check($condition, $message) {
    if (!$condition) throw new \RuntimeException($message);
}
function expiry($token) {
    global $connect;
    $q = $connect->prepare('SELECT expires_at FROM sso_tokens WHERE token = ?');
    $q->execute([hash('sha256', $token)]);
    return strtotime($q->fetchColumn());
}
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$_SERVER['HTTP_USER_AGENT'] = 'Android WebView v1';
$_COOKIE = ['sb_patient_app' => '1', 'sb_app_device' => str_repeat('a', 64)];
$member = ['mb_id' => 'patient_test'];
$token = create_remember_login_token($member, LIFETIME);
check(strlen($token) === 64, 'login creates a token');
check(expiry($token) === G5_SERVER_TIME + LIFETIME, 'token lasts 120 days');
$_COOKIE['ck_auto'] = $token;
$now += 86400;
check(verify_remember_login_token($member['mb_id'], $token), 'restores after one day without a PHP session');
$_SERVER['REMOTE_ADDR'] = '198.51.100.1';
$_SERVER['HTTP_USER_AGENT'] = 'Android WebView v2';
check(verify_remember_login_token($member['mb_id'], $token), 'network and WebView updates preserve login');
// Simulate two requests holding the same cookie and responses arriving out of order.
$renewed = create_remember_login_token($member, LIFETIME + 86400);
$olderResponse = create_remember_login_token($member, LIFETIME);
check($renewed === $token && $olderResponse === $token, 'parallel renewal preserves the persisted credential');
check(expiry($token) === G5_SERVER_TIME + LIFETIME + 86400, 'older request cannot shorten expiry');
$failRenew = true;
check(create_remember_login_token($member, LIFETIME) === '', 'renewal failure is reported');
check(verify_remember_login_token($member['mb_id'], $token), 'failed renewal retains old credential');
$failRenew = false;
unset($_COOKIE['ck_auto']);
$failInsert = true;
check(create_remember_login_token($member, LIFETIME) === '', 'insert failure is reported');
check(verify_remember_login_token($member['mb_id'], $token), 'failed insert does not delete old credential');
$failInsert = false;
$second = create_remember_login_token($member, LIFETIME);
check(verify_remember_login_token($member['mb_id'], $token), 'lost new-cookie response does not invalidate persisted token');
$_COOKIE['sb_app_device'] = str_repeat('b', 64);
check(!verify_remember_login_token($member['mb_id'], $token), 'copied token fails on another device');
$otherDevice = create_remember_login_token($member, LIFETIME);
$_COOKIE['sb_app_device'] = str_repeat('a', 64);
$now = G5_SERVER_TIME + 119 * 86400;
check(verify_remember_login_token($member['mb_id'], $token), 'session restores on day 119');
$now = G5_SERVER_TIME + 121 * 86400;
check(!verify_remember_login_token($member['mb_id'], $token), 'renewed token expires at its deadline');
$now = G5_SERVER_TIME + 86400;
revoke_remember_login_token($member['mb_id']);
check(!verify_remember_login_token($member['mb_id'], $token), 'logout revokes persisted token');
check(!verify_remember_login_token($member['mb_id'], $second), 'logout revokes overlapping tokens');
$_COOKIE['sb_app_device'] = str_repeat('b', 64);
check(verify_remember_login_token($member['mb_id'], $otherDevice), 'logout preserves another device');
$_COOKIE = [];
$web = create_remember_login_token($member, 31 * 86400);
revoke_remember_login_token($member['mb_id']);
$_COOKIE = ['sb_patient_app' => '1', 'sb_app_device' => str_repeat('b', 64)];
check(verify_remember_login_token($member['mb_id'], $otherDevice), 'browser logout preserves app login');
// A previous browser token is upgraded without accepting a changed user agent.
$_COOKIE = [];
$legacy = create_remember_login_token($member, 31 * 86400);
$_COOKIE = ['sb_patient_app' => '1', 'sb_app_device' => str_repeat('c', 64), 'ck_auto' => $legacy];
$upgraded = create_remember_login_token($member, LIFETIME);
check($upgraded !== $legacy && verify_remember_login_token($member['mb_id'], $upgraded), 'legacy browser credential upgrades to device binding');
$_COOKIE['ck_auto'] = $upgraded;
check(create_remember_login_token($member, LIFETIME) === $upgraded, 'upgraded credential renews without replacement');
echo "PASS: 120-day expiry, session restoration, concurrent renewal, failure recovery, device binding and logout\n";

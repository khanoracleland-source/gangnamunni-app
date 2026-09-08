<?php
// Uses a disposable schema on an explicitly provided local test socket.
// APP_AUTH_TEST_SOCKET=/path/mysql.sock php -n tests/regression/exchange_atomic_encryption.php
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/app_native_auth.php';
date_default_timezone_set('UTC');
define('G5_SERVER_TIME', time());
putenv('APP_NATIVE_AUTH_KEY=' . base64_encode(random_bytes(32)));
function expect_auth($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$code = bin2hex(random_bytes(32));
$bundle = ['access_token' => bin2hex(random_bytes(32)), 'refresh_token' => bin2hex(random_bytes(32))];
$encrypted = app_encrypt_exchange_payload($code, $bundle);
expect_auth(strpos($encrypted, $bundle['refresh_token']) === false, 'no plaintext refresh token');
expect_auth(app_decrypt_exchange_payload($code, $encrypted) === $bundle, 'encrypted payload round trip');
foreach (['wrong code' => [str_repeat('a',64), $encrypted], 'modified ciphertext' => [$code, substr($encrypted,0,-5).'AAAAA'], 'legacy plaintext' => [$code,json_encode($bundle)]] as $label => $args) {
    $rejected = false;
    try { app_decrypt_exchange_payload(...$args); } catch (Throwable $e) { $rejected = true; }
    expect_auth($rejected, $label . ' rejected');
}
$key = getenv('APP_NATIVE_AUTH_KEY');
putenv('APP_NATIVE_AUTH_KEY');
$rejected = false;
try { app_encrypt_exchange_payload($code, $bundle); } catch (Throwable $e) { $rejected = true; }
expect_auth($rejected, 'missing key fails closed');
putenv('APP_NATIVE_AUTH_KEY='.$key);
$socket = getenv('APP_AUTH_TEST_SOCKET');
if (!$socket) { echo "SKIP: DB checks require APP_AUTH_TEST_SOCKET\n"; exit(0); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$a = new mysqli('localhost', 'root', '', '', 0, $socket);
$schema = 'auth_regression_' . bin2hex(random_bytes(5));
$a->query("CREATE DATABASE $schema");
$b = null;
try {
    $a->select_db($schema);
    $a->query("SET time_zone = '+00:00'");
    $a->query("CREATE TABLE sso_tokens (token CHAR(64) PRIMARY KEY, user_type VARCHAR(32), g5_mb_id VARCHAR(20), ip_address VARCHAR(64), user_agent VARCHAR(255) NULL, payload TEXT NULL, expires_at DATETIME, created_at DATETIME, INDEX (g5_mb_id,user_type)) ENGINE=InnoDB");
    $b = new mysqli('localhost', 'root', '', $schema, 0, $socket);
    $b->query("SET time_zone = '+00:00'");
    $b->query('SET SESSION innodb_lock_wait_timeout=1');
    $connect = $a; // production remember helpers use this global
    $device = str_repeat('a',64);
    // PHP defaults to UTC in a standalone API while web config uses Seoul.
    // Starting both connections at UTC reproduces a real deployment mismatch.
    app_native_auth_configure_connection($a);
    app_native_auth_configure_connection($b);
    expect_auth(date_default_timezone_get() === 'Asia/Seoul', 'standalone API uses the web timezone');
    $tzBridge = app_issue_session_bridge_token($a, 'timezone_member', $device);
    $tzRow = $a->query("SELECT expires_at, TIMESTAMPDIFF(SECOND,NOW(),expires_at) AS remaining FROM sso_tokens WHERE g5_mb_id='timezone_member'")->fetch_assoc();
    expect_auth(abs((int)$tzRow['remaining'] - 300) <= 2, 'SQL sees a five-minute bridge lifetime');
    expect_auth(abs(strtotime($tzRow['expires_at']) - time() - 300) <= 2,
        'web PHP sees the same five-minute bridge lifetime');
    $_SESSION = ['app_exchange_window_until' => time() + 60];
    $pageCode = app_session_exchange_code($a, 'page_member');
    $nextPageCode = app_session_exchange_code($a, 'page_member');
    expect_auth(strlen($pageCode) === 64 && $pageCode === $nextPageCode,
        'page navigation reuses the same exchange code');
    $count = $a->query("SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id='page_member'")->fetch_assoc();
    expect_auth((int)$count['n'] === 1, 'page navigation does not mint competing exchanges');
    expect_auth(app_session_exchange_code($a, 'other_member') !== $pageCode,
        'another account cannot reuse the previous account exchange');
    $_SESSION['app_exchange_window_until'] = time() - 1;
    expect_auth(app_session_exchange_code($a, 'other_member') === '', 'expired login window emits no code');
    $_SESSION = ['app_exchange_window_until' => time() + 60];
    expect_auth(app_session_exchange_code($a, 'page_member') !== $pageCode,
        'fresh login gets a fresh exchange code');
    $code = app_issue_exchange_code($a, 'test_member');
    $a->begin_transaction();
    $claim = app_consume_exchange_code($a, $code, $device);
    expect_auth($claim['mb_id'] === 'test_member' && $claim['payload'] === null, 'first exchange owns empty payload');
    $blocked = false;
    try { app_native_auth_transaction($b, fn() => app_consume_exchange_code($b, $code, $device)); }
    catch (mysqli_sql_exception $e) { $blocked = $e->getCode() === 1205; }
    expect_auth($blocked, 'parallel same-device exchange waits for first transaction');
    app_store_exchange_payload($a, $code, $bundle);
    $a->commit();
    $retry = app_native_auth_transaction($b, fn() => app_consume_exchange_code($b, $code, $device));
    expect_auth(app_decrypt_exchange_payload($code,$retry['payload']) === $bundle, 'retry receives exact committed bundle');
    expect_auth(app_consume_exchange_code($b,$code,str_repeat('b',64))['mb_id'] === '', 'different device rejected');

    $failedCode = app_issue_exchange_code($a, 'failure_member');
    $rolledBack = false;
    try {
        app_native_auth_transaction($a, function () use ($a,$failedCode,$device,$bundle) {
            app_consume_exchange_code($a,$failedCode,$device);
            app_issue_access_token($a,'failure_member',$device);
            app_store_exchange_payload($a,$failedCode,$bundle);
            // A duplicate persistence attempt must fail and roll back everything.
            app_store_exchange_payload($a,$failedCode,$bundle);
        });
    } catch (RuntimeException $e) { $rolledBack=true; }
    expect_auth($rolledBack, 'payload persistence failure aborts exchange');
    $row=$a->query("SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id='failure_member' AND user_type='patient_app_access'")->fetch_assoc();
    expect_auth((int)$row['n']===0,'failed exchange leaves no issued access token');
    $row=$a->query("SELECT user_agent,payload FROM sso_tokens WHERE g5_mb_id='failure_member'")->fetch_assoc();
    expect_auth($row['user_agent']===null && $row['payload']===null,'failed exchange rolls back claim and payload');

    $a->begin_transaction();
    app_consume_exchange_code($a,$code,$device);
    $blocked=false;
    try { app_revoke_native_auth_tokens($b,'test_member',$device); }
    catch (mysqli_sql_exception $e) { $blocked=$e->getCode()===1205; }
    expect_auth($blocked,'logout waits for in-flight exchange');
    $a->commit();
    app_revoke_native_auth_tokens($a,'test_member',$device);
    expect_auth(app_consume_exchange_code($a,$code,$device)['mb_id']==='', 'logout invalidates cached exchange');
    // Verify must hold its access lock until the bridge is committed.
    $access = app_issue_access_token($a, 'verify_member', $device)['token'];
    $a->begin_transaction();
    expect_auth(app_verify_access_token($a, $access, $device, true) === 'verify_member',
        'verify locks a valid access token');
    $blocked = false;
    try { app_revoke_native_auth_tokens($b, 'verify_member', $device); }
    catch (mysqli_sql_exception $e) { $blocked = $e->getCode() === 1205; }
    expect_auth($blocked, 'logout waits for verify transaction');
    app_issue_session_bridge_token($a, 'verify_member', $device);
    $a->commit();
    app_revoke_native_auth_tokens($b, 'verify_member', $device);
    $count = $a->query("SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id='verify_member'")->fetch_assoc();
    expect_auth((int)$count['n'] === 0, 'logout removes the bridge committed by verify');
    expect_auth(app_native_auth_transaction($a,
        fn() => app_verify_access_token($a, $access, $device, true)) === '',
        'verify after logout rejects the revoked access token');

    // Both refresh/logout orderings: no credential can be resurrected.
    $refresh = create_remember_login_token(['mb_id'=>'refresh_member'], APP_REFRESH_TOKEN_LIFETIME,
        ['is_patient_app'=>true,'device_id'=>$device]);
    $a->begin_transaction();
    expect_auth(app_renew_native_refresh_token($a,'refresh_member',$refresh,$device)===$refresh,
        'refresh preserves the credential value');
    $blocked=false;
    try { app_revoke_native_auth_tokens($b,'refresh_member',$device); }
    catch (mysqli_sql_exception $e) { $blocked=$e->getCode()===1205; }
    expect_auth($blocked,'logout waits for refresh transaction');
    app_issue_access_token($a,'refresh_member',$device);
    app_issue_session_bridge_token($a,'refresh_member',$device);
    $a->commit();
    app_revoke_native_auth_tokens($b,'refresh_member',$device);
    $count=$a->query("SELECT COUNT(*) AS n FROM sso_tokens WHERE g5_mb_id='refresh_member'")->fetch_assoc();
    expect_auth((int)$count['n']===0,'logout removes refresh and newly issued access/bridge together');
    $rejected=false;
    try { app_native_auth_transaction($a, fn()=>app_renew_native_refresh_token($a,'refresh_member',$refresh,$device)); }
    catch (DomainException $e) { $rejected=$e->getCode()===401; }
    expect_auth($rejected,'refresh after logout rejects instead of inserting a token');

    $refresh=create_remember_login_token(['mb_id'=>'rollback_member'],APP_REFRESH_TOKEN_LIFETIME,
        ['is_patient_app'=>true,'device_id'=>$device]);
    $a->query("UPDATE sso_tokens SET expires_at=DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE g5_mb_id='rollback_member'");
    $before=$a->query("SELECT expires_at FROM sso_tokens WHERE g5_mb_id='rollback_member'")->fetch_assoc()['expires_at'];
    try {
        app_native_auth_transaction($a,function () use ($a,$refresh,$device) {
            app_renew_native_refresh_token($a,'rollback_member',$refresh,$device);
            app_issue_access_token($a,'rollback_member',$device);
            throw new RuntimeException('simulated downstream failure');
        });
    } catch (RuntimeException $e) {}
    $rows=$a->query("SELECT expires_at FROM sso_tokens WHERE g5_mb_id='rollback_member'")->fetch_all(MYSQLI_ASSOC);
    expect_auth(count($rows)===1 && $rows[0]['expires_at']===$before,
        'refresh failure rolls back expiry extension and access issuance');
} finally {
    if ($b) $b->close();
    $a->rollback();
    $a->query("DROP DATABASE $schema");
    $a->close();
}

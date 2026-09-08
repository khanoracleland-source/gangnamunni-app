<?php
// Real PHP HTTP handlers + disposable MySQL schema; never application DB.
// APP_AUTH_TEST_SOCKET=/tmp/test/mysql.sock php -n tests/regression/native_auth_http.php
$socket = getenv('APP_AUTH_TEST_SOCKET');
if (!$socket) { fwrite(STDERR, "APP_AUTH_TEST_SOCKET is required\n"); exit(1); }
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/app_native_auth.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connect = new mysqli('localhost', 'root', '', '', 0, $socket);
$schema = 'auth_http_' . bin2hex(random_bytes(5));
$root = sys_get_temp_dir() . '/' . $schema;
$server = null;
function check_http($ok, $message) {
    if (!$ok) {
        global $root;
        fwrite(STDERR, file_get_contents($root . '/server.log'));
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
function request_auth($endpoint, $body, $cookie = '', $method = 'POST') {
    global $origin;
    $context = stream_context_create(['http' => ['method' => $method,
        'header' => "Content-Type: application/json\r\nUser-Agent: NativeAuthTest\r\n" . ($cookie !== '' ? "Cookie: $cookie\r\n" : ''), 'content' => json_encode($body),
        'ignore_errors' => true, 'timeout' => 5]]);
    $raw = file_get_contents($origin . '/gangnamunni/api/' . $endpoint, false, $context);
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $match);
    return [(int)$match[1], json_decode($raw, true)];
}
function remove_fixture($path) {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') remove_fixture($path . '/' . $name);
        rmdir($path);
    } else { unlink($path); }
}
try {
    $connect->query("CREATE DATABASE $schema");
    $connect->select_db($schema);
    app_native_auth_configure_connection($connect);
    $connect->query("CREATE TABLE sso_tokens (token CHAR(64) PRIMARY KEY, user_type ENUM('gnuboard','hms','admin','web_remember','patient_app_remember','patient_app_access','patient_app_exchange','patient_app_bridge') NOT NULL, g5_mb_id VARCHAR(20), ip_address VARCHAR(64), user_agent VARCHAR(255) NULL, payload TEXT NULL, expires_at DATETIME, created_at DATETIME, INDEX (g5_mb_id,user_type)) ENGINE=InnoDB");
    $connect->query("CREATE TABLE g5_config (cf_use_email_certify INT NOT NULL DEFAULT 0)");
    $connect->query('INSERT INTO g5_config VALUES (0)');
    $connect->query("CREATE TABLE g5_member (mb_id VARCHAR(20) PRIMARY KEY, mb_name VARCHAR(30), mb_nick VARCHAR(30), mb_email VARCHAR(100), mb_hp VARCHAR(30), mb_level INT, mb_intercept_date VARCHAR(8) DEFAULT '', mb_leave_date VARCHAR(8) DEFAULT '', mb_email_certify DATETIME NULL, mb_datetime DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $connect->query("INSERT INTO g5_member (mb_id,mb_name,mb_nick,mb_email,mb_hp,mb_level,mb_email_certify) VALUES ('http_patient','Test','test','test@example.test','',2,NOW())");
    mkdir($root . '/gangnamunni/api', 0700, true);
    mkdir($root . '/gangnamunni/includes', 0700, true);
    mkdir($root . '/lib', 0700, true);
    $source = realpath(__DIR__ . '/../../hairwang_full');
    foreach (['app_auth_exchange.php','app_auth_verify.php','app_auth_refresh.php','app_logout.php'] as $file) {
        copy($source . '/gangnamunni/api/' . $file, $root . '/gangnamunni/api/' . $file);
    }
    copy($source . '/gangnamunni/includes/app_native_auth.php', $root . '/gangnamunni/includes/app_native_auth.php');
    copy($source . '/gangnamunni/includes/app_native_auth_http.php', $root . '/gangnamunni/includes/app_native_auth_http.php');
    copy($source . '/lib/common.lib.php', $root . '/lib/common.lib.php');
    copy($source . '/lib/pbkdf2.compat.php', $root . '/lib/pbkdf2.compat.php');
    file_put_contents($root . '/dbconfig.php', "<?php\ndefine('G5_MYSQL_HOST','localhost');\ndefine('G5_MYSQL_PORT',3306);\ndefine('G5_MYSQL_USER','root');\ndefine('G5_MYSQL_PASSWORD','');\ndefine('G5_MYSQL_DB'," . var_export($schema,true) . ");\n");
    $listener = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($listener, false); fclose($listener);
    $origin = 'http://' . $address;
    putenv('APP_NATIVE_AUTH_KEY=' . base64_encode(random_bytes(32)));
    $server = proc_open([PHP_BINARY,'-n','-d','mysqli.default_socket=' . $socket,
        '-d','date.timezone=UTC','-S',$address,'-t',$root],
        [0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']], $pipes);
    fclose($pipes[0]);
    for ($i=0; $i<100; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $errstr, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(20000);
    }
    $device = str_repeat('a',64); $other = str_repeat('b',64);
    // Exercise the actual session promoter with the smallest Gnuboard setup.
    // This fixture only replaces application bootstrap; the promoter and
    // cookie/session helpers themselves are production files.
    $restore = '<?php require ' . var_export($source . '/gangnamunni/includes/app_native_auth.php', true) . ';'
        . 'require ' . var_export($source . '/lib/hook.lib.php', true) . ';'
        . 'require ' . var_export($root . '/dbconfig.php', true) . ';'
        . 'define("G5_COOKIE_DOMAIN", ""); define("G5_TABLE_PREFIX", "g5_");'
        . '$connect=new mysqli(G5_MYSQL_HOST,G5_MYSQL_USER,G5_MYSQL_PASSWORD,G5_MYSQL_DB);'
        . 'app_native_auth_configure_connection($connect);'
        . '$config=["cf_admin"=>"admin","cf_use_email_certify"=>0];'
        . 'session_start(); $is_member=false; $member=[];'
        . 'require ' . var_export($source . '/gangnamunni/includes/sso_auto_login.php', true) . ';'
        . 'header("Content-Type: application/json"); echo json_encode(["success"=>$is_member,"mb_id"=>$_SESSION["ss_mb_id"]??null]);';
    file_put_contents($root . '/gangnamunni/api/test_web_restore.php', $restore);
    $cookieFallback = create_remember_login_token(['mb_id'=>'http_patient'], APP_REFRESH_TOKEN_LIFETIME, ['is_patient_app'=>true,'device_id'=>$device]);
    $code = app_issue_exchange_code($connect, 'http_patient');
    [$status,$login] = request_auth('app_auth_exchange.php', ['code'=>$code,'device_id'=>$device]);
    check_http($status===200 && ($login['success']??false)===true, 'HTTP ' . $status . ': real HTTP exchange issues a complete bundle with PHP initially UTC');
    check_http(strlen($login['refresh_token'])===64 && $login['refresh_expires_at']-time()>119*86400, 'refresh lifetime is 120 days');
    check_http(verify_remember_login_token('http_patient', $cookieFallback, ['is_patient_app'=>true,'device_id'=>$device]),
        'process kill before native save still has a valid WebView remember credential');
    [$status,$repeated] = request_auth('app_auth_exchange.php', ['code'=>$code,'device_id'=>$device]);
    check_http($status===200 && $repeated===$login, 'repeating HTTP exchange returns the exact committed bundle');
    [$status,$data] = request_auth('app_auth_exchange.php', ['code'=>$code,'device_id'=>$other]);
    check_http($status===401, 'another device cannot replay the exchange');
    foreach (['access'=>$login['access_token'], 'refresh'=>$login['refresh_token'], 'exchange'=>$code] as $kind=>$credential) {
        [$status,$restored] = request_auth('test_web_restore.php', [], 'sb_sso_token=' . hash('sha256', $credential) . '; sb_app_device=' . $device);
        check_http($status===200 && $restored['success']===false, "stored $kind hash is never accepted as a raw SSO login token");
    }
    [$status,$verified] = request_auth('app_auth_verify.php', ['access_token'=>$login['access_token'],'device_id'=>$device]);
    check_http($status===200 && ($verified['success']??false), 'cold-start HTTP verify returns a session bridge');
    $stmt=$connect->prepare('SELECT expires_at FROM sso_tokens WHERE token=?');
    $stmt->bind_param('s',$verified['session_bridge_token']);$stmt->execute();$bridge=$stmt->get_result()->fetch_assoc();$stmt->close();
    check_http(abs(strtotime($bridge['expires_at'])-time()-300)<=3, 'web timezone sees the returned bridge as valid for five minutes');
    $webCookie = 'sb_sso_token=' . $verified['session_bridge_token'] . '; sb_app_device=' . $device;
    [$status,$restored] = request_auth('test_web_restore.php', [], $webCookie);
    check_http($status===200 && ($restored['success']??false) && $restored['mb_id']==='http_patient',
        'actual web session promoter restores the correct account on first page');
    [$status,$restored] = request_auth('test_web_restore.php', [], $webCookie);
    check_http($status===200 && $restored['success']===false, 'consumed bridge cannot open another web session');

    [$status,$data] = request_auth('app_auth_verify.php', ['access_token'=>$login['access_token'],'device_id'=>$other]);
    check_http($status===401, 'wrong-device verify is rejected');
    $connect->query("UPDATE sso_tokens SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_type='patient_app_access'");
    [$status,$data] = request_auth('app_auth_verify.php', ['access_token'=>$login['access_token'],'device_id'=>$device]);
    check_http($status===401 && $data['error']==='access_token_invalid', 'expired access returns a refreshable rejection');
    $refreshBody=['mb_id'=>'http_patient','refresh_token'=>$login['refresh_token'],'device_id'=>$device];
    [$status,$renewed] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===200 && $renewed['refresh_token']===$login['refresh_token'], 'HTTP refresh preserves the long-lived token value');
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===200, 'lost refresh response can be retried with the same credential');
    $connect->query("UPDATE sso_tokens SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_type='patient_app_remember'");
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===401 && $data['error']==='refresh_invalid', 'expired 120-day refresh is rejected');
    $connect->query("UPDATE sso_tokens SET expires_at=DATE_ADD(NOW(),INTERVAL 120 DAY) WHERE user_type='patient_app_remember'");
    [$status,$data] = request_auth('app_auth_refresh.php', array_merge($refreshBody, ['mb_id'=>'http_patient!']));
    check_http($status===401, 'member id is matched exactly, never rewritten to another account');
    foreach (['app_auth_exchange.php','app_auth_verify.php','app_auth_refresh.php','app_logout.php'] as $endpoint) {
        [$status,$data] = request_auth($endpoint, [], '', 'GET');
        check_http($status===405 && $data['success']===false, "$endpoint rejects GET consistently");
    }
    foreach (['app_auth_exchange.php'=>['code'=>['bad'],'device_id'=>$device],
        'app_auth_verify.php'=>['access_token'=>['bad'],'device_id'=>$device],
        'app_auth_refresh.php'=>['mb_id'=>['bad'],'refresh_token'=>$login['refresh_token'],'device_id'=>$device]] as $endpoint=>$body) {
        [$status,$data] = request_auth($endpoint, $body);
        check_http($status===400 && $data['success']===false, "$endpoint rejects structured credential fields without PHP warnings");
    }
    $connect->query('DELETE FROM g5_config');
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===503 && $data['error']==='refresh_unavailable', 'missing site configuration cannot silently bypass email certification');
    $connect->query('INSERT INTO g5_config VALUES (0)');

    $otherCode = app_issue_exchange_code($connect, 'http_patient');
    [$status,$otherLogin] = request_auth('app_auth_exchange.php', ['code'=>$otherCode,'device_id'=>$other]);
    check_http($status===200, 'same account can log in on a second device');

    $connect->query("UPDATE g5_member SET mb_intercept_date=DATE_FORMAT(NOW(),'%Y%m%d') WHERE mb_id='http_patient'");
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===401 && $data['error']==='account_unavailable', 'blocked account cannot refresh');
    $connect->query("UPDATE g5_member SET mb_intercept_date='' WHERE mb_id='http_patient'");
    [$status,$data] = request_auth('app_logout.php', $refreshBody);
    check_http($status===200 && $data['success']===true, 'HTTP logout accepts refresh ownership proof');
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===401 && $data['error']==='refresh_invalid', 'refresh after logout cannot recreate a session');
    [$status,$data] = request_auth('app_auth_refresh.php', ['mb_id'=>'http_patient', 'refresh_token'=>$otherLogin['refresh_token'], 'device_id'=>$other]);
    check_http($status===200, 'logout on first device preserves the second device login');

    [$status,$data] = request_auth('app_auth_exchange.php', ['code'=>$code,'device_id'=>$device]);
    check_http($status===401, 'old exchange is unusable after HTTP logout');
    // Run the read-only server preflight against this disposable DB/root.
    foreach (['common.php','config.php','bbs/logout.php','extend/app_native_auth.extend.php',
        'extend/sso_auto_login.extend.php','gangnamunni/includes/init.php',
        'gangnamunni/includes/sso_session_token.php','gangnamunni/includes/sso_auto_login.php',
        'gangnamunni/api/app_auth_bootstrap.php'] as $file) {
        if (!is_dir(dirname($root . '/' . $file))) mkdir(dirname($root . '/' . $file),0700,true);
        symlink($source . '/' . $file, $root . '/' . $file);
    }
    $preflightCommand = [PHP_BINARY, '-n', '-d', 'mysqli.default_socket=' . $socket,
        realpath(__DIR__ . '/../../scripts/auth/inspect_native_auth.php'), $root];
    $preflight = proc_open($preflightCommand, [0=>['file','/dev/null','r'],
        1=>['file',$root . '/preflight.log','w'],2=>['file',$root . '/preflight.log','a']], $preflightPipes);
    $preflightStatus = proc_close($preflight);
    $preflightLog = file_get_contents($root . '/preflight.log');
    check_http($preflightStatus===0 && strpos($preflightLog, 'FAIL:')===false,
        'read-only CLI preflight accepts complete files, key and migrated schema');
    check_http(strpos($preflightLog, 'worker environment is NOT verified')!==false
        && strpos($preflightLog, getenv('APP_NATIVE_AUTH_KEY'))===false,
        'CLI preflight never prints the key or claims to verify the HTTP worker');
    $connect->query('ALTER TABLE sso_tokens DROP COLUMN payload');
    [$status,$data] = request_auth('app_auth_exchange.php', ['code'=>str_repeat('c',64),'device_id'=>$device]);
    check_http($status===503 && $data['error']==='exchange_unavailable', 'missing payload migration produces a release-blocking 503');
    check_http(strpos(file_get_contents($root . '/server.log'), 'stage=payload_schema') !== false,
        'server log distinguishes missing payload schema without exposing SQL or tokens');
    // A raw legacy bearer must never be treated as a native token hash.
    $otherRefreshHash = hash('sha256', $otherLogin['refresh_token']);
    [$status,$data] = request_auth('app_logout.php', ['token'=>$otherRefreshHash]);
    check_http($status===200 && verify_remember_login_token('http_patient', $otherLogin['refresh_token'], ['is_patient_app'=>true,'device_id'=>$other]),
        'legacy logout cannot revoke a native token using its database hash');
    $connect->query('DROP TABLE sso_tokens');
    [$status,$data] = request_auth('app_auth_refresh.php', $refreshBody);
    check_http($status===503 && $data['error']==='refresh_unavailable', 'database failure is not misreported as invalid credentials');
    [$status,$data] = request_auth('app_logout.php', $refreshBody);
    check_http($status===503 && $data['success']===false && $data['error']==='logout_unavailable',
        'failed server revocation does not claim successful logout');
    // Break only the disposable HTTP fixture's DB selection, not the test connection.
    $dbconfig = file_get_contents($root . '/dbconfig.php');
    file_put_contents($root . '/dbconfig.php', str_replace($schema, $schema . '_missing', $dbconfig));
    foreach (['app_auth_exchange.php'=>['code'=>$code,'device_id'=>$device],
        'app_auth_verify.php'=>['access_token'=>$login['access_token'],'device_id'=>$device],
        'app_auth_refresh.php'=>$refreshBody, 'app_logout.php'=>$refreshBody] as $endpoint=>$body) {
        [$status,$data] = request_auth($endpoint, $body);
        check_http($status===503 && $data['success']===false, "$endpoint reports connection failure as retryable JSON");
    }

    file_put_contents($root . '/dbconfig.php', $dbconfig . "\nputenv('APP_NATIVE_AUTH_KEY');\n");
    [$status,$data] = request_auth('app_auth_exchange.php', ['code'=>$code,'device_id'=>$device]);
    check_http($status===503 && $data['error']==='exchange_unavailable', 'missing worker key remains a retryable exchange failure');
    $log = file_get_contents($root . '/server.log');
    check_http(strpos($log, 'stage=encryption_key') !== false && strpos($log, 'stage=connection') !== false,
        'server log separates worker-key failure from database connection failure');
    check_http(strpos($log, $login['refresh_token']) === false && strpos($log, $login['access_token']) === false,
        'failure diagnostics do not expose issued credentials');

} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $connect->query("DROP DATABASE IF EXISTS $schema"); $connect->close();
    if (is_dir($root)) remove_fixture($root);
}

<?php
// Read-only server preflight. Never publish this as an HTTP diagnostic page.
// php scripts/auth/inspect_native_auth.php /actual/path/to/hairwang_full
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root . '/dbconfig.php')) {
    fwrite(STDERR, "Provide the active hairwang web root containing dbconfig.php.\n");
    exit(2);
}
$failed = false;
function report_auth_check($ok, $label) {
    global $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $failed = $failed || !$ok;
}
echo "Read-only CLI preflight; PHP-FPM/Apache worker environment is NOT verified.\n";
$files = ['common.php', 'config.php', 'lib/common.lib.php', 'bbs/logout.php',
    'extend/app_native_auth.extend.php', 'extend/sso_auto_login.extend.php',
    'gangnamunni/includes/init.php', 'gangnamunni/includes/app_native_auth.php',
    'gangnamunni/includes/app_native_auth_http.php', 'gangnamunni/includes/sso_session_token.php',
    'gangnamunni/includes/sso_auto_login.php', 'gangnamunni/api/app_auth_bootstrap.php',
    'gangnamunni/api/app_auth_exchange.php', 'gangnamunni/api/app_auth_verify.php',
    'gangnamunni/api/app_auth_refresh.php', 'gangnamunni/api/app_logout.php'];
foreach ($files as $file) {
    $exists = is_readable($root . '/' . $file);
    report_auth_check($exists, 'required file ' . $file);
    if ($exists) echo 'SHA256: ' . $file . ' ' . hash_file('sha256', $root . '/' . $file) . "\n";
}
foreach (['mysqli', 'openssl'] as $extension) {
    report_auth_check(extension_loaded($extension), 'extension ' . $extension);
}
$encoded = getenv('APP_NATIVE_AUTH_KEY');
$key = is_string($encoded) ? base64_decode($encoded, true) : false;
report_auth_check($key !== false && strlen($key) === 32,
    'CLI APP_NATIVE_AUTH_KEY decodes to 32 bytes (value never printed)');
unset($key, $encoded);
if (extension_loaded('openssl')) {
    report_auth_check(in_array('aes-256-gcm', openssl_get_cipher_methods(), true), 'AES-256-GCM available');
}
$connection = null;
try {
    require $root . '/dbconfig.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD,
        G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    report_auth_check(true, 'connection through active web dbconfig.php');
    $columns = [];
    $result = $connection->query('SHOW COLUMNS FROM sso_tokens');
    while ($column = $result->fetch_assoc()) $columns[$column['Field']] = $column;
    foreach (['token', 'g5_mb_id', 'user_type', 'user_agent', 'payload', 'expires_at', 'created_at', 'ip_address'] as $name) {
        report_auth_check(isset($columns[$name]), 'sso_tokens column ' . $name);
    }
    $type = strtolower($columns['user_type']['Type'] ?? '');
    foreach (['patient_app_remember', 'patient_app_exchange', 'patient_app_access', 'patient_app_bridge'] as $name) {
        report_auth_check(strpos($type, "'" . $name . "'") !== false, 'user_type enum includes ' . $name);
    }
    $row = $connection->query("SELECT ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sso_tokens'")->fetch_assoc();
    report_auth_check(strcasecmp($row['ENGINE'] ?? '', 'InnoDB') === 0, 'sso_tokens uses InnoDB');
    $row = $connection->query('SELECT cf_use_email_certify FROM g5_config LIMIT 1')->fetch_assoc();
    report_auth_check(is_array($row), 'site authentication configuration exists');
} catch (Throwable $e) {
    report_auth_check(false, 'database preflight: ' . get_class($e) . ' code=' . (int)$e->getCode());
} finally {
    if ($connection) $connection->close();
}
echo "Compare file hashes with the intended release. Then check the HTTP worker and real login flow.\n";
exit($failed ? 1 : 0);

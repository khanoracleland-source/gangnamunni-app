<?php
// /rb/rb.mod/pwa/api/check_host.php
// 환경 점검: PHP 버전, 필수 확장, OpenSSL EC(prime256v1), autoload 경로

header('Content-Type: text/plain; charset=utf-8');

// ---- Basic info
printf("Host     : %s\n", $_SERVER['HTTP_HOST'] ?? 'unknown');
printf("HTTPS    : %s\n", (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'ON' : 'OFF');
printf("PHP      : %s (SAPI: %s)\n", PHP_VERSION, PHP_SAPI);
printf("OS       : %s\n", PHP_OS_FAMILY);
if (defined('OPENSSL_VERSION_TEXT')) {
    printf("OpenSSL  : %s\n", OPENSSL_VERSION_TEXT);
}

// ---- Extensions
$need = ['openssl','curl','mbstring','json'];
echo "\nExtensions:\n";
$allOk = true;
foreach ($need as $e) {
    $ok = extension_loaded($e);
    printf("  - %-9s : %s\n", $e, $ok ? 'OK' : 'MISSING');
    if (!$ok) $allOk = false;
}
if (function_exists('curl_version')) {
    $cv = curl_version();
    printf("    curl ver : %s (%s)\n", $cv['version'] ?? '?', $cv['ssl_version'] ?? '?');
}

// ---- OpenSSL EC (prime256v1) 지원 확인
$ecOk = false;
$detail = '';

if (function_exists('openssl_get_curve_names')) {
    $names = openssl_get_curve_names();
    if (is_array($names)) {
        $ecOk = in_array('prime256v1', $names, true);
        $detail = $ecOk ? 'prime256v1 in curve list' : 'prime256v1 not in curve list';
    }
}

if (!$ecOk && defined('OPENSSL_KEYTYPE_EC')) {
    // 키 생성 시도로 재확인 (PHP 7.3+)
    $cfg = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
    $res = @openssl_pkey_new($cfg);
    if ($res !== false) { $ecOk = true; $detail = 'EC key generation success'; }
}

echo "\nOpenSSL EC:\n";
echo "  - prime256v1 : " . ($ecOk ? "OK" : "NO") . ($detail ? " ({$detail})" : "") . "\n";

// ---- Optional performance extensions
echo "\nPerformance (optional):\n";
printf("  - bcmath : %s\n", extension_loaded('bcmath') ? 'OK' : 'missing');
printf("  - gmp    : %s\n", extension_loaded('gmp')    ? 'OK' : 'missing');

// ---- Autoload path check
$rbRoot   = realpath(__DIR__ . '/../../..'); // /rb
$autoload = $rbRoot . '/rb.lib/vendor/autoload.php';
echo "\nAutoload:\n";
printf("  - %s : %s\n", $autoload, is_file($autoload) ? 'FOUND' : 'NOT FOUND');

// ---- Final result
echo "\nResult:\n";
if (PHP_VERSION_ID < 70300) {
    echo "  -> FAIL: PHP 7.3 이상 필요.\n";
} elseif (!$allOk) {
    echo "  -> FAIL: 필수 확장(openssl/curl/mbstring/json) 중 누락 있음.\n";
} elseif (!$ecOk) {
    echo "  -> FAIL: OpenSSL EC prime256v1 미지원. 호스팅사에 EC 지원 요청 필요.\n";
} elseif (!is_file($autoload)) {
    echo "  -> FAIL: autoload.php 경로 확인 필요.\n";
} else {
    echo "  -> OK: Web Push(PWA) 구동 가능한 환경입니다.\n";
}
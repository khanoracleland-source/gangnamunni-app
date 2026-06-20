<?php
@ini_set('display_errors', 0);
@date_default_timezone_set('Asia/Seoul');

$rbRoot   = realpath(__DIR__ . '/../../..'); // /rb
$autoload = $rbRoot . '/rb.lib/vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(500); exit('vendor/autoload.php 없음'); }
require_once $autoload;

// 그누보드 common.php 로드
$commonCandidates = [
  $rbRoot.'/common.php',
  dirname($rbRoot).'/common.php',
  $_SERVER['DOCUMENT_ROOT'].'/common.php'
];
foreach ($commonCandidates as $c) { if (is_file($c)) { include_once $c; break; } }

// 헬퍼
function rb_pwa_cfg() {
    $row = sql_fetch("SELECT * FROM rb_pwa_config WHERE id=1");
    return $row ?: [];
}
function rb_pwa_enabled() {
    $c = rb_pwa_cfg();
    return !empty($c['use_yn']);
}

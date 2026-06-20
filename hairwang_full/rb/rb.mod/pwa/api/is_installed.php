<?php
require __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// 1) 쿠키 우선
$cookie_ok = isset($_COOKIE['rb_pwa_installed']) && $_COOKIE['rb_pwa_installed'] === '1';
if ($cookie_ok) { echo json_encode(['installed'=>true, 'via'=>'cookie']); exit; }

// 2) 로그인한 경우 DB 백업 체크
$installed = false;
$via = 'none';
if ($is_member && !empty($member['mb_id'])) {
  $mb_id = $member['mb_id'];
  $sql = "SELECT 1 FROM rb_pwa_app_installs
          WHERE mb_id = '".sql_real_escape_string($mb_id)."'
            AND first_installed_at IS NOT NULL
          LIMIT 1";
  $row = sql_fetch($sql);
  if ($row) { $installed = true; $via = 'db-mb'; }
}

echo json_encode(['installed'=>$installed, 'via'=>$via]);

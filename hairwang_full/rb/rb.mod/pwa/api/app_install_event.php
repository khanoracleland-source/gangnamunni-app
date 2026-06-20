<?php
require __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function rb_set_installed_cookie() {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  // 도메인 지정이 필요하면 4번째 인자 '/' 뒤에 도메인 넣기 또는 Gnuboard 상수 사용
  setcookie('rb_pwa_installed', '1', [
    'expires'  => time() + 3600*24*365,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
  ]);
}

$raw  = file_get_contents('php://input');
$in   = json_decode($raw, true) ?: [];

$device_uid = trim($in['device_uid'] ?? '');
$event      = trim($in['event'] ?? '');          // installed | open
$mode       = strtolower(trim($in['display'] ?? '')); // standalone | browser | twa | ...
$source     = trim($in['source'] ?? '');         // beforeinstallprompt | a2hs-ios | unknown

if ($device_uid === '' || ($event !== 'installed' && $event !== 'open')) {
  echo json_encode(['ok'=>false,'msg'=>'BADREQ']); exit;
}

$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$ua_raw  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$mb_id   = $is_member ? ($member['mb_id'] ?? '') : '';

// platform/os/browser 파싱(라이트)
$ua = strtolower($ua_raw);
$platform = (strpos($ua,'android')!==false) ? 'android'
          : ((strpos($ua,'iphone')!==false||strpos($ua,'ipad')!==false||strpos($ua,'ipod')!==false) ? 'ios'
          : ((strpos($ua,'windows')!==false||strpos($ua,'macintosh')!==false||strpos($ua,'linux')!==false) ? 'desktop' : 'web'));
$os = (strpos($ua,'android')!==false) ? 'Android'
     : ((strpos($ua,'iphone')!==false||strpos($ua,'ipad')!==false||strpos($ua,'ipod')!==false) ? 'iOS'
     : (strpos($ua,'windows')!==false ? 'Windows' : (strpos($ua,'macintosh')!==false ? 'macOS' : '')));
$browser = (strpos($ua,'chrome')!==false) ? 'Chrome'
         : ((strpos($ua,'safari')!==false) ? 'Safari'
         : (strpos($ua,'firefox')!==false ? 'Firefox' : ''));

// 시간: 그누보드 서버시간으로 통일
$now  = G5_TIME_YMDHIS;
$mode = $mode ?: 'unknown';

// 기존 행 조회
$row = sql_fetch("SELECT id, device_uid, first_installed_at FROM rb_pwa_app_installs WHERE device_uid='".sql_real_escape_string($device_uid)."'");

// -------------------------------
// 정책
// 1) INSERT 허용: event='installed' 이거나 display=standalone (실제 설치로 간주)
// 2) OPEN 브라우저 모드만으로는 INSERT 금지 (아무 것도 저장 안 함)
// 3) 기존 행 UPDATE: 존재할 때만 수행
// -------------------------------

if (!$row) {
  // 새 행 필요 여부 판단
  $allow_insert =
    ($event === 'installed') ||
    (in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true));

  if (!$allow_insert) {
    // 설치 안 했고, 브라우저 모드 open → 저장하지 않음
    echo json_encode(['ok'=>true,'skip'=>'not-installed']); exit;
  }

  // INSERT (설치로 인정되는 경우만)
  $first_installed = ($event === 'installed' || in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true)) ? $now : null;
  $last_opened     = in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true) ? $now : null;

  sql_query("INSERT INTO rb_pwa_app_installs
    (device_uid, mb_id, platform, os, browser, last_seen_mode, install_source,
     first_installed_at, last_opened_at, ip, ua, created_at, updated_at)
    VALUES (
      '".sql_real_escape_string($device_uid)."',
      '".sql_real_escape_string($mb_id)."',
      '".sql_real_escape_string($platform)."',
      '".sql_real_escape_string($os)."',
      '".sql_real_escape_string($browser)."',
      '".sql_real_escape_string($mode)."',
      '".sql_real_escape_string($source)."',
      ".($first_installed ? "'{$first_installed}'" : "NULL").",
      ".($last_opened ? "'{$last_opened}'" : "NULL").",
      '".sql_real_escape_string($ip)."',
      '".sql_real_escape_string($ua_raw)."',
      '{$now}', '{$now}'
    )");

    rb_set_installed_cookie();
    echo json_encode(['ok'=>true,'inserted'=>true, 'installed'=>true]); exit;
}

// 기존 행 UPDATE (설치된 기기)
$set = "
  mb_id='".sql_real_escape_string($mb_id)."',
  platform='".sql_real_escape_string($platform)."',
  os='".sql_real_escape_string($os)."',
  browser='".sql_real_escape_string($browser)."',
  last_seen_mode='".sql_real_escape_string($mode)."',
  ip='".sql_real_escape_string($ip)."',
  ua='".sql_real_escape_string($ua_raw)."',
  updated_at='{$now}'
";

// 설치 이벤트면 최초설치시각/소스 백필
if ($event === 'installed' && empty($row['first_installed_at'])) {
  $set .= ", first_installed_at='{$now}', install_source='".sql_real_escape_string($source)."'";
}

// standalone 계열 실행이면 마지막 실행시각 갱신 + (안 찍혀 있으면) 설치시각 보수
if (in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true)) {
  $set .= ", last_opened_at='{$now}'";
  if (empty($row['first_installed_at'])) {
    $set .= ", first_installed_at='{$now}'";
  }
}

// 브라우저 모드(open)인 경우엔 last_opened_at 업데이트 안 함 (설치자만 유지)
sql_query("UPDATE rb_pwa_app_installs SET {$set} WHERE id=".(int)$row['id']);

// 설치 신호가 있었다면(설치 이벤트 또는 standalone 계열 실행) 쿠키 남김
if ($event === 'installed' || in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true)) {
  rb_set_installed_cookie();
}
echo json_encode(['ok'=>true,'updated'=>true, 'installed'=>($event==='installed' || in_array($mode, ['standalone','twa','fullscreen','minimal-ui'], true))]);
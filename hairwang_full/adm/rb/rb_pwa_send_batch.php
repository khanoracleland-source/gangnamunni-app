<?php
// /adm/rb/rb_pwa_send_batch.php
include_once('./_common.php');
header('Content-Type: application/json; charset=utf-8');

$rbRoot   = G5_PATH.'/rb';
$autoload = $rbRoot.'/rb.lib/vendor/autoload.php';
if (!is_file($autoload)) { echo json_encode(['ok'=>false,'msg'=>'autoload 없음']); exit; }
require_once $autoload;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

if (!$is_admin || $is_admin!=='super'){ echo json_encode(['ok'=>false,'msg'=>'권한']); exit; }

$log_id = (int)($_GET['log_id'] ?? 0);
if ($log_id<=0){ echo json_encode(['ok'=>false,'msg'=>'log_id']); exit; }

// ===== helpers =====
// 절대/상대 URL을 서비스워커 스코프 기준 "상대 경로(/...)"로 강제 변환
function rb_to_scope_path($u){
    if (!$u) return '';
    // 이미 절대 URL이면 path만 꺼냄 (호스트 섞여도 스코프 상대경로로 통일)
    if (preg_match('~^https?://~i', $u)) {
        $p = parse_url($u);
        if (!empty($p['path'])) return $p['path'] . (isset($p['query']) ? '?'.$p['query'] : '');
        return '';
    }
    // 상대경로면 루트 시작으로 보정
    return ($u[0] === '/') ? $u : '/'.$u;
}
// 현재 호스트의 물리 파일 경로 (같은 호스트에서만 확인 가능)
function rb_url_to_file_on_this_host($urlOrPath){
    if (!$urlOrPath) return '';
    // 절대 URL이면 path만 추출
    $path = $urlOrPath;
    if (preg_match('~^https?://~i', $urlOrPath)) {
        $parts = parse_url($urlOrPath);
        if (!$parts || empty($parts['path'])) return '';
        // 다른 호스트면 파일 존재 확인 불가
        if (!empty($parts['host']) && isset($_SERVER['HTTP_HOST']) && strcasecmp($parts['host'], $_SERVER['HTTP_HOST']) !== 0) {
            return '';
        }
        $path = $parts['path'];
    }
    if ($path[0] !== '/') return '';
    return rtrim($_SERVER['DOCUMENT_ROOT'],'/').$path;
}

function rb_append_ver_to_scope_path($scopePath){
    if (!$scopePath || $scopePath[0] !== '/') return $scopePath;
    $abs = rtrim($_SERVER['DOCUMENT_ROOT'],'/').$scopePath;
    if (is_file($abs)) {
        $v = @filemtime($abs) ?: time();
        return $scopePath . (strpos($scopePath, '?')===false ? '?v='.$v : '&v='.$v);
    }
    return $scopePath;
}

// ===== VAPID =====
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$v = sql_fetch("SELECT * FROM rb_pwa_vapid WHERE domain='".sql_real_escape_string($host)."'");
if (!$v){ echo json_encode(['ok'=>false,'msg'=>'VAPID 없음']); exit; }

$auth = ['VAPID'=>[
    'subject'=>'mailto:support@'.$host,
    'publicKey'=>$v['pubkey'],
    'privateKey'=>$v['prikey']
]];
$webPush = new WebPush($auth, ['TTL'=>3600, 'timeout'=>10]);

// ===== DATA =====
$DATA_PATH = defined('G5_DATA_PATH') ? G5_DATA_PATH : (G5_PATH.'/data');
$DATA_URL  = defined('G5_DATA_URL')  ? G5_DATA_URL  : (G5_URL .'/data');

$log = sql_fetch("SELECT * FROM rb_pwa_push_log WHERE id={$log_id}");
if (!$log){ echo json_encode(['ok'=>false,'msg'=>'로그 없음']); exit; }

$cfg = sql_fetch("SELECT icon_192 FROM rb_pwa_config WHERE id=1");

// 아이콘(왼쪽 큰 아이콘) 경로 → 스코프 상대경로로
$iconSrc = !empty($cfg['icon_192']) ? $cfg['icon_192'] : ($DATA_URL.'/pwa/icons/icon-192.png');
$iconPath = rb_to_scope_path($iconSrc);
// 실제 파일이 없으면 기본 경로로 교체
$iconFile = rb_url_to_file_on_this_host($iconPath);
if ($iconFile && !is_file($iconFile)) {
    $iconPath = rb_to_scope_path($DATA_URL.'/pwa/icons/icon-192.png');
}

// 배지(우측 모노크롬) — 안드로이드 전용으로만 사용
$badgePath = rb_to_scope_path($DATA_URL.'/pwa/icons/badge-72.png');
$badgeFile = rb_url_to_file_on_this_host($badgePath);
$hasBadge  = ($badgeFile && is_file($badgeFile));

// 첨부 이미지(알림 확장 시 큰 이미지)
$imagePath = '';
if (!empty($log['image'])) {
    $imagePath = rb_to_scope_path($log['image']);
    $imgFile   = rb_url_to_file_on_this_host($imagePath);
    if ($imgFile && !is_file($imgFile)) $imagePath = '';
}

// ===== 큐 처리 =====
$BATCH = 50;
$rows  = [];
// UA를 조인해서 플랫폼 분기
$q = sql_query("
  SELECT q.id, q.endpoint, q.p256dh, q.auth, s.ua
  FROM rb_pwa_push_queue q
  LEFT JOIN rb_pwa_subscriptions s
    ON s.endpoint = q.endpoint
  WHERE q.log_id={$log_id} AND q.status='NEW'
  ORDER BY q.id ASC
  LIMIT {$BATCH}
");
while($r=sql_fetch_array($q)) $rows[]=$r;

foreach($rows as $row){
  try{
    $sub = Subscription::create([
      'endpoint'=>$row['endpoint'],
      'keys'=>['p256dh'=>$row['p256dh'],'auth'=>$row['auth']]
    ]);

    $ua = strtolower($row['ua'] ?? '');
    $isAndroid = (strpos($ua,'android') !== false);
    $isIOS     = (strpos($ua,'iphone') !== false) || (strpos($ua,'ipad') !== false) || (strpos($ua,'ipod') !== false);
      
    // 기존 경로 계산 로직 그대로 두고, 마지막에 버전만 덧붙임
    $iconPathVer  = rb_append_ver_to_scope_path($iconPath);
    $badgePathVer = $hasBadge ? rb_append_ver_to_scope_path($badgePath) : '';
    $imagePathVer = $imagePath ? rb_append_ver_to_scope_path($imagePath) : '';

    // payload: 아이콘/배지/이미지는 "스코프 상대경로"로 전달
    $body = str_replace(["\r\n","\r"], "\n", (string)$log['body']);
    $payload = [
      'title' => $log['title'],
      'body'  => $log['body'],
      'url'   => $log['url'],
      'icon'  => $iconPathVer,
    ];

    if ($isAndroid) {
        if ($hasBadge)        $payload['badge'] = $badgePathVer;
        if ($imagePathVer)    $payload['image'] = $imagePathVer;
    } else if (!$isIOS) {
        if ($imagePathVer)    $payload['image'] = $imagePathVer;
        if ($hasBadge)        $payload['badge'] = $badgePathVer;
    }
    // iOS: badge/image 미지원 → 생략

    $report = $webPush->sendOneNotification($sub, json_encode($payload));
    $ok = $report->isSuccess();
    $st = $ok ? 'SENT' : 'FAIL';
    $rs = $ok ? 'OK' : (method_exists($report,'getReason') ? substr($report->getReason(),0,250) : 'ERR');

    sql_query("UPDATE rb_pwa_push_queue SET status='{$st}', result='".sql_real_escape_string($rs)."', updated_at='".G5_TIME_YMDHIS."' WHERE id={$row['id']}");

    if(!$ok && method_exists($report,'isSubscriptionExpired') && $report->isSubscriptionExpired()){
      sql_query("DELETE FROM rb_pwa_subscriptions WHERE endpoint='".sql_real_escape_string($row['endpoint'])."'");
    }
  }catch(Throwable $e){
    $msg = substr($e->getMessage(), 0, 250);
    sql_query("UPDATE rb_pwa_push_queue SET status='FAIL', result='".sql_real_escape_string($msg)."', updated_at='".G5_TIME_YMDHIS."' WHERE id={$row['id']}");
  }
}

// 집계/응답
$c_s = sql_fetch("SELECT COUNT(*) c FROM rb_pwa_push_queue WHERE log_id={$log_id} AND status='SENT'")['c'];
$c_f = sql_fetch("SELECT COUNT(*) c FROM rb_pwa_push_queue WHERE log_id={$log_id} AND status='FAIL'")['c'];
$done = $c_s + $c_f;
sql_query("UPDATE rb_pwa_push_log SET succ_cnt={$c_s}, fail_cnt={$c_f} WHERE id={$log_id}");
$finished = ($done >= (int)$log['total_cnt']);
echo json_encode(['ok'=>true,'succ'=>$c_s,'fail'=>$c_f,'done'=>$done,'finished'=>$finished]);

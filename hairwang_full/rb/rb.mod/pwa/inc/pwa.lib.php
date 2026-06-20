<?php
// /rb/rb.mod/pwa/inc/pwa.lib.php
// PWA 푸시: 단일 회원 대상 발송 + 공용 헬퍼만 유지 (불필요한 브로드캐스트 함수 제거)

if (!defined('G5_PATH')) exit;

// --- autoload (경로 주의: /rb/rb.lib/vendor/)
$rbRoot   = G5_PATH.'/rb';
$autoload = $rbRoot.'/rb.lib/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/* 공통 헬퍼들 --------------------------------------------------------------- */
// 절대/상대 URL → 서비스워커 스코프 기준 "상대 경로(/...)"로 보정
function rb_pwa_to_scope_path($u){
    if (!$u) return '';
    if (preg_match('~^https?://~i', $u)) {
        $p = parse_url($u);
        if (!empty($p['path'])) return $p['path'] . (isset($p['query']) ? '?'.$p['query'] : '');
        return '';
    }
    return ($u[0] === '/') ? $u : '/'.$u;
}

// 현재 호스트의 물리 파일 경로 (같은 호스트만 확인 가능)
function rb_pwa_url_to_file_on_this_host($urlOrPath){
    if (!$urlOrPath) return '';
    $path = $urlOrPath;
    if (preg_match('~^https?://~i', $urlOrPath)) {
        $parts = parse_url($urlOrPath);
        if (!$parts || empty($parts['path'])) return '';
        if (!empty($parts['host']) && isset($_SERVER['HTTP_HOST']) && strcasecmp($parts['host'], $_SERVER['HTTP_HOST']) !== 0) {
            return ''; // 다른 호스트면 확인 불가
        }
        $path = $parts['path'];
    }
    if ($path[0] !== '/') return '';
    return rtrim($_SERVER['DOCUMENT_ROOT'],'/').$path;
}

// 파일 수정시간 기반 캐시버스터(?v=) 부착
function rb_pwa_append_ver_to_scope_path($scopePath){
    if (!$scopePath || $scopePath[0] !== '/') return $scopePath;
    $abs = rtrim($_SERVER['DOCUMENT_ROOT'],'/').$scopePath;
    if (is_file($abs)) {
        $v = @filemtime($abs) ?: time();
        return $scopePath . (strpos($scopePath, '?')===false ? '?v='.$v : '&v='.$v);
    }
    return $scopePath;
}

// PWA 설정 체크 (push_yn)
function rb_pwa_can_push(){
    static $ok = null;
    if ($ok !== null) return $ok;
    $cfg = sql_fetch("SELECT push_yn FROM rb_pwa_config WHERE id=1");
    $ok = (!empty($cfg['push_yn']));
    return $ok;
}

// 아이콘/배지 경로 만들기 (스코프 상대 + 캐시버스터)
function rb_pwa_build_icon_paths(){
    $DATA_URL  = defined('G5_DATA_URL') ? G5_DATA_URL : (G5_URL.'/data');
    $cfg = sql_fetch("SELECT icon_192 FROM rb_pwa_config WHERE id=1");

    $iconSrc  = !empty($cfg['icon_192']) ? $cfg['icon_192'] : ($DATA_URL.'/pwa/icons/icon-192.png');
    $iconPath = rb_pwa_to_scope_path($iconSrc);
    $iconFile = rb_pwa_url_to_file_on_this_host($iconPath);
    if ($iconFile && !is_file($iconFile)) {
        $iconPath = rb_pwa_to_scope_path($DATA_URL.'/pwa/icons/icon-192.png');
    }
    $iconPath = rb_pwa_append_ver_to_scope_path($iconPath);

    $badge    = rb_pwa_to_scope_path($DATA_URL.'/pwa/icons/badge-72.png');
    $badgeFile= rb_pwa_url_to_file_on_this_host($badge);
    $hasBadge = ($badgeFile && is_file($badgeFile));
    $badge    = $hasBadge ? rb_pwa_append_ver_to_scope_path($badge) : '';

    return [$iconPath, $badge, $hasBadge];
}

// VAPID WebPush 인스턴스
function rb_pwa_webpush(){
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $v = sql_fetch("SELECT * FROM rb_pwa_vapid WHERE domain='".sql_real_escape_string($host)."'");
    if (!$v) return null;
    $auth = ['VAPID'=>[
        'subject'=>'mailto:support@'.$host,
        'publicKey'=>$v['pubkey'],
        'privateKey'=>$v['prikey']
    ]];
    return new WebPush($auth, ['TTL'=>3600, 'timeout'=>10]);
}

function rb_pwa_notify_member($mb_id, $title, $body, $url='/', $reg_mb_id='system'){
    if (!rb_pwa_can_push()) return 0;
    if (!$mb_id) return 0;
    if (!class_exists(WebPush::class)) return 0;

    // 구독 목록
    $res = sql_query("SELECT DISTINCT endpoint, p256dh, auth, ua
                      FROM rb_pwa_subscriptions
                      WHERE mb_id='".sql_real_escape_string($mb_id)."' AND installed=1 AND endpoint<>''");
    $subs = []; while($r=sql_fetch_array($res)) $subs[]=$r;

    $total = count($subs);


    $now       = G5_TIME_YMDHIS;
    $esc_title = sql_real_escape_string((string)$title);
    $esc_body  = sql_real_escape_string((string)$body);
    $esc_url   = sql_real_escape_string((string)$url);
    $esc_reg   = sql_real_escape_string((string)$reg_mb_id);
    $esc_memo  = sql_real_escape_string('ids='.$mb_id);

    // kind=post , target=member , target_memo=ids={아이디}
    sql_query("INSERT INTO rb_pwa_push_log
               (kind, target, target_memo, title, body, url, image, total_cnt, succ_cnt, fail_cnt, reg_mb_id, reg_dt)
               VALUES
               ('post','member','{$esc_memo}','{$esc_title}','{$esc_body}','{$esc_url}','', {$total}, 0, 0, '{$esc_reg}', '{$now}')");
    $log_id = sql_insert_id();


    if (!$total) {
        // 구독이 없으면 실패 0/성공 0 그대로 종료 (기록만 남김)
        return 0;
    }

    $webPush = rb_pwa_webpush();
    if (!$webPush) return 0;

    list($iconPathVer, $badgePathVer, $hasBadge) = rb_pwa_build_icon_paths();
    $scopeUrl = rb_pwa_to_scope_path($url ?: '/');

    $sent = 0;
    foreach ($subs as $s) {
        try{
            $ua = strtolower($s['ua'] ?? '');
            $isAndroid = (strpos($ua,'android') !== false);
            $isIOS     = (strpos($ua,'iphone') !== false) || (strpos($ua,'ipad') !== false) || (strpos($ua,'ipod') !== false);

            $sub = Subscription::create([
                'endpoint'=>$s['endpoint'],
                'keys'=>['p256dh'=>$s['p256dh'],'auth'=>$s['auth']]
            ]);

            $payload = [
                'title' => (string)$title,
                'body'  => (string)$body,
                'url'   => $scopeUrl,
                'icon'  => $iconPathVer,
            ];
            if ($isAndroid) {
                if ($hasBadge) $payload['badge'] = $badgePathVer;
            } else if (!$isIOS) {
                if ($hasBadge) $payload['badge'] = $badgePathVer;
            }

            $report = $webPush->sendOneNotification($sub, json_encode($payload));
            if ($report->isSuccess()) {
                $sent++;
            } else if (method_exists($report,'isSubscriptionExpired') && $report->isSubscriptionExpired()){
                sql_query("DELETE FROM rb_pwa_subscriptions WHERE endpoint='".sql_real_escape_string($s['endpoint'])."'");
            }
        }catch(Throwable $e){
            // skip
        }
    }

    $fail = $total - $sent;
    // --- 이력 마감 업데이트
    if ($log_id) {
        sql_query("UPDATE rb_pwa_push_log
                   SET succ_cnt={$sent}, fail_cnt={$fail}
                   WHERE id={$log_id}");
    }
    return $sent;
}
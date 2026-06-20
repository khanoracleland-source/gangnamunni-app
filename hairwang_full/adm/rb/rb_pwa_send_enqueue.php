<?php
// /adm/rb/rb_pwa_send_enqueue.php
include_once('./_common.php');
header('Content-Type: application/json; charset=utf-8');

if (!$is_admin || $is_admin !== 'super') {
    echo json_encode(['ok'=>false,'msg'=>'권한이 없습니다.']); exit;
}

// CSRF
function rb_pwa_admin_token_check() {
    if (function_exists('check_admin_token')) { check_admin_token(); return; }
    if (function_exists('check_token')) { check_token(); return; }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'잘못된 요청']); exit; }
rb_pwa_admin_token_check();

// 입력
$target = isset($_POST['target']) ? (string)$_POST['target'] : 'all';
$target = in_array($target, ['all','level','member'], true) ? $target : 'all';
$targetSave = $target; // ← 로그에 저장할 실제 target (레벨인 경우 'member'로 바꿔 저장)

$level_from = isset($_POST['level_from']) ? (int)$_POST['level_from'] : 0;
$title  = trim(isset($_POST['title']) ? $_POST['title'] : '');
$body   = trim(isset($_POST['body']) ? $_POST['body'] : '');
$url    = trim(isset($_POST['url']) ? $_POST['url'] : '/');
$mb_ids = trim(isset($_POST['mb_ids']) ? $_POST['mb_ids'] : '');
if ($title==='' || $body==='') { echo json_encode(['ok'=>false,'msg'=>'제목/내용을 입력하세요.']); exit; }

// 데이터 경로
$DATA_PATH = defined('G5_DATA_PATH') ? G5_DATA_PATH : (G5_PATH.'/data');
$DATA_URL  = defined('G5_DATA_URL')  ? G5_DATA_URL  : (G5_URL .'/data');
$pwa_dir   = $DATA_PATH.'/pwa';
$pwa_url   = $DATA_URL .'/pwa';
if (!is_dir($pwa_dir)) @mkdir($pwa_dir, 0775, true);
if (!is_file($pwa_dir.'/index.php')) @file_put_contents($pwa_dir.'/index.php', "<?php\n// silence\n");

// [RB-PATCH] 알림용 이미지 저장 폴더(/data/pwa/notice)
$notice_dir = $pwa_dir.'/notice';
$notice_url = $pwa_url.'/notice';
if (!is_dir($notice_dir)) @mkdir($notice_dir, 0775, true);
if (!is_file($notice_dir.'/index.php')) @file_put_contents($notice_dir.'/index.php', "<?php\n// silence\n");

// 대상 구독 조건
$where = "1";
$memo  = '';
if ($target === 'member' && $mb_ids !== '') {
    $ids = array_filter(array_map('trim', explode(',', $mb_ids)));
    if (!$ids){ echo json_encode(['ok'=>false,'msg'=>'회원 ID가 없습니다.']); exit; }
    $esc=[]; foreach($ids as $id){ $esc[]="'".sql_real_escape_string($id)."'"; }
    $where = "mb_id IN (".implode(',', $esc).")";
    $memo  = 'ids='.implode(',', $ids);

} else if ($target === 'level') {
    if ($level_from <= 0) { echo json_encode(['ok'=>false,'msg'=>'레벨을 선택하세요.']); exit; }
    $mtable = $g5['member_table']; // 예: g5_member
    $where  = "mb_id IN (SELECT mb_id FROM {$mtable} WHERE mb_level = {$level_from})"; // 같음
    $memo   = 'level='.$level_from;

    // ★ 로그 저장용 target은 'member'로 통일
    $targetSave = 'member';
}

// [RB-PATCH] 먼저 '임시 로그' 생성 → log_id 확보 후 이미지 리사이즈본을 log에 반영
sql_query("INSERT INTO rb_pwa_push_log (kind,target,target_memo,title,body,url,image,total_cnt,reg_mb_id,reg_dt)
VALUES ('manual','{$targetSave}','".sql_real_escape_string($memo)."',
        '".sql_real_escape_string($title)."','".sql_real_escape_string($body)."',
        '".sql_real_escape_string($url)."','', 0,
        '".sql_real_escape_string($member['mb_id'])."','".G5_TIME_YMDHIS."')");
$log_id = sql_insert_id();
if (!$log_id) { echo json_encode(['ok'=>false,'msg'=>'로그 생성 실패']); exit; }

// [RB-PATCH] 이미지 업로드 → 알림용 JPG(가로≤1200px)로 리사이즈 저장(/data/pwa/notice/noti-LOGID.jpg)
$image_url = '';
if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $f = $_FILES['image'];
    $ok = (strpos((string)$f['type'],'image/') === 0) || preg_match('/\.(png|jpe?g|gif|webp)$/i', (string)$f['name']);
    if ($ok) {
        $tmp  = $f['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info) {
            list($w,$h,$type) = $info;
            $maxW = 1200;
            $nw=$w; $nh=$h;
            if ($w > $maxW) { $nw=$maxW; $nh = (int)round($h*($maxW/$w)); }

            switch($type){
                case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($tmp); break;
                case IMAGETYPE_PNG:  $im = @imagecreatefrompng($tmp);  break;
                case IMAGETYPE_GIF:  $im = @imagecreatefromgif($tmp);  break;
                default: $im = null;
            }
            if ($im) {
                $save_abs = $notice_dir.'/noti-'.$log_id.'.jpg';
                $dst = imagecreatetruecolor($nw,$nh);
                imagecopyresampled($dst,$im,0,0,0,0,$nw,$nh,$w,$h);
                @imagejpeg($dst,$save_abs,85);
                imagedestroy($dst); imagedestroy($im);
                @chmod($save_abs,0644);
                $image_url = $notice_url.'/noti-'.$log_id.'.jpg';
                // 로그에 이미지 경로 반영
                sql_query("UPDATE rb_pwa_push_log SET image='".sql_real_escape_string($image_url)."' WHERE id={$log_id}");
            }
        }
    }
}

// [RB-PATCH] 구독 추출: 고유 endpoint 기준으로 dedupe
$res = sql_query("SELECT DISTINCT endpoint,p256dh,auth 
                  FROM rb_pwa_subscriptions 
                  WHERE {$where} AND installed=1 AND endpoint<>''");

$values=[]; $count=0;
while($s=sql_fetch_array($res)){
  $values[] = "({$log_id},'".sql_real_escape_string($s['endpoint'])."','".sql_real_escape_string($s['p256dh'])."','".sql_real_escape_string($s['auth'])."','NEW','".G5_TIME_YMDHIS."')";
  $count++;
  if (count($values)===500){
    sql_query("INSERT INTO rb_pwa_push_queue (log_id,endpoint,p256dh,auth,status,updated_at) VALUES ".implode(',', $values));
    $values=[];
  }
}
if ($values){
  sql_query("INSERT INTO rb_pwa_push_queue (log_id,endpoint,p256dh,auth,status,updated_at) VALUES ".implode(',', $values));
}

// 대상 없음 에러로 응답 (프론트에서 alert)
if ($count === 0) {
  sql_query("UPDATE rb_pwa_push_log 
             SET total_cnt=0, target='".sql_real_escape_string($targetSave)."', target_memo='".sql_real_escape_string($memo)."' 
             WHERE id={$log_id}");
  echo json_encode(['ok'=>false,'msg'=>'발송 대상이 없습니다.']);
  exit;
}

// [RB-PATCH] 실제 큐 건수로 total_cnt 확정 + 응답도 실제값
$actual_row = sql_fetch("SELECT COUNT(*) c FROM rb_pwa_push_queue WHERE log_id={$log_id}");
$actual = (int)($actual_row['c'] ?? 0);
sql_query("UPDATE rb_pwa_push_log 
           SET total_cnt={$count}, target='".sql_real_escape_string($targetSave)."', target_memo='".sql_real_escape_string($memo)."' 
           WHERE id={$log_id}");

echo json_encode(['ok'=>true,'log_id'=>$log_id,'total'=>$actual]);

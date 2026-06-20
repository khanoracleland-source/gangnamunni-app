<?php
// attend write without token
include_once(dirname(__FILE__).'/../../../common.php');
include_once(G5_PATH.'/rb/rb.mod/attendance/attend.lib.php');
header('Content-Type: application/json; charset=utf-8');
if (!defined('_GNUBOARD_')) exit;

// 로그인 확인
if (!$is_member) {
    echo json_encode(array('ok'=>0,'msg'=>'로그인이 필요합니다.')); exit;
}

// 동일 도메인 기본 체크
$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host    = $_SERVER['HTTP_HOST']    ?? '';
$ok_same = false;
if ($origin  && strpos($origin,  $host)!==false) $ok_same = true;
if ($referer && strpos($referer, $host)!==false) $ok_same = true;
if (!$ok_same) {
    echo json_encode(array('ok'=>0,'msg'=>'요청이 허용되지 않았습니다.')); exit;
}

// 입력 값
$raw = trim($_POST['content'] ?? '');
$content = substr($raw, 0, 1000);

// 날짜 정규화
$ymd8 = rb_attend_norm_ymd8(defined('G5_TIME_YMD') ? G5_TIME_YMD : date('Ymd'));
$now = defined('G5_TIME_YMDHIS') ? G5_TIME_YMDHIS : date('Y-m-d H:i:s');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

// 중복 체크
if (rb_attend_is_checked_today($member['mb_id'], $ymd8)) {
    echo json_encode(array('ok'=>0,'msg'=>'오늘은 이미 출석하셨어요!','ymd'=>$ymd8)); exit;
}

// 설정과 순위
$cf = rb_attend_get_config();
$rank = rb_attend_pick_rank($ymd8);

// 저장
sql_query("
INSERT INTO rb_attendance (mb_id, ymd, at_datetime, at_ip, at_content, at_rank)
VALUES (
  '".sql_real_escape_string($member['mb_id'])."',
  '{$ymd8}',
  '{$now}',
  '".sql_real_escape_string($ip)."',
  '".sql_real_escape_string($content)."',
  ".($rank ? (int)$rank : "NULL")."
)
");
$at_id = sql_insert_id();

// 참여 포인트 지급
$base = isset($cf['base_attend_point']) ? (int)$cf['base_attend_point'] : 0;
if ($base > 0) {
    rb_attend_insert_point($member['mb_id'], $base, '출석 참여', G5_TIME_YMD.'-base', $at_id);
}

// 순위 보너스 포인트 (1~5등)
if ($rank && $rank >= 1 && $rank <= 5) {
    $bonus = 0;
    if ($rank == 1) $bonus = (int)$cf['bonus_rank1'];
    if ($rank == 2) $bonus = (int)$cf['bonus_rank2'];
    if ($rank == 3) $bonus = (int)$cf['bonus_rank3'];
    if ($rank == 4) $bonus = (int)$cf['bonus_rank4'];
    if ($rank == 5) $bonus = (int)$cf['bonus_rank5'];
    if ($bonus > 0) rb_attend_insert_point($member['mb_id'], $bonus, '출석 순위 보너스', G5_TIME_YMD.'-rank', $at_id);
}

// 연속 처리
$st = rb_attend_update_streak_and_award($member['mb_id'], $ymd8, $cf);

// 응답
echo json_encode(array(
    'ok'=>1,
    'ymd'=>$ymd8,
    'rank'=>$rank,
    'rank_label'=>rb_attend_rank_label($rank),
    'cont_days'=>$st['cont'],
    'streak_bonus'=>$st['bonus']
));

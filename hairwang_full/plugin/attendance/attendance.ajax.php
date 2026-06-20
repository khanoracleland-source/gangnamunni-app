<?php
include_once('../../common.php');
include_once('./_config.php');
sql_query("SET NAMES utf8mb4");

header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

function jexit($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false,'msg'=>'Invalid method']);
    if (!$is_member) jexit(['ok'=>false,'msg'=>'로그인이 필요합니다.']);
    if (!check_token($_POST['token'] ?? '')) jexit(['ok'=>false,'msg'=>'Token error']);

    $mb_id = $member['mb_id'];

    // ① 이모지만 저장: "😄 최고" -> "😄" 로 변환
    $mood_raw = trim($_POST['mood'] ?? '');
    // 공백 기준 첫 토큰만 (이모지 단독 전송 전제, 혹시 "😄 최고" 오면 앞 이모지 추출)
    $mood_first = preg_replace('/\s.+$/u', '', $mood_raw);
    // 허용 이모지 화이트리스트(필요 시 추가)
    $allowed = ['🙂','😄','😌','😮','😴'];
    $mood = in_array($mood_first, $allowed, true) ? $mood_first : '';

    // 멀티바이트 안전 잘라내기 (복합 이모지 대비 여유 4~8)
    if (function_exists('mb_substr')) {
        $mood = mb_substr($mood, 0, 4, 'UTF-8');
    } else {
        $mood = substr($mood, 0, 16);
    }

    $message = trim($_POST['message'] ?? '');
    if ($message === '') jexit(['ok'=>false,'msg'=>'인사말을 입력해주세요.']);

    // 하루 1회 제한: (mb_id, att_date) 유니크 키 전제
    $date = G5_TIME_YMD;
    $dup = sql_fetch("SELECT COUNT(*) AS cnt FROM `".G5_ATTENDANCE_TABLE."` WHERE mb_id = '".sql_real_escape_string($mb_id)."' AND att_date = '".sql_real_escape_string($date)."'");
    if ((int)$dup['cnt'] > 0) jexit(['ok'=>false,'msg'=>'오늘은 이미 출석을 완료했습니다.']);

    $now  = G5_TIME_YMDHIS;
    $time = G5_TIME_HIS;

    $ip_bin = isset($_SERVER['REMOTE_ADDR']) ? @inet_pton($_SERVER['REMOTE_ADDR']) : null;

    $sql = "INSERT INTO `".G5_ATTENDANCE_TABLE."`
            SET mb_id        = '".sql_real_escape_string($mb_id)."',
                mood         = '".sql_real_escape_string($mood)."',
                message      = '".sql_real_escape_string($message)."',
                att_date     = '".sql_real_escape_string($date)."',
                att_time     = '".sql_real_escape_string($time)."',
                reg_datetime = '".sql_real_escape_string($now)."',
                ip           = ".($ip_bin ? "0x".bin2hex($ip_bin) : "NULL");
    sql_query($sql);

    // --- 포인트 적립 ---
    $settings = att_get_settings();

    if ($settings['daily_points'] > 0) {
        // 같은 날짜에 중복 적립 방지 (uniq_key: daily:YYYY-MM-DD)
        $uniq = 'daily:'.$date;
        $content = '일일 출석 보상';
        att_award_once($mb_id, 'daily', $uniq, $settings['daily_points'], $content, $date);
    }

    $totrow = sql_fetch("SELECT COUNT(*) AS cnt FROM `".G5_ATTENDANCE_TABLE."`
                         WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    $total = (int)$totrow['cnt'];
    if ($settings['every_days_n'] > 0 && $settings['every_days_points'] > 0) {
        if ($total % $settings['every_days_n'] === 0) {
            $uniq = 'total:'.$total; // 예: total:10
            $content = '출석 누적 '.$total.'일 달성 보상';
            att_award_once($mb_id, 'every', $uniq, $settings['every_days_points'], $content, $date);
        }
    }

    if ($settings['streak_days'] > 0 && $settings['streak_points'] > 0) {
        $streak = att_get_streak_today($mb_id, $date);
        if ($streak > 0 && $streak % $settings['streak_days'] === 0) {
            $uniq = 'streak:'.$streak; // 예: streak:7, streak:14 ...
            $content = '연속 '.$streak.'일 출석 보상';
            att_award_once($mb_id, 'streak', $uniq, $settings['streak_points'], $content, $date);
        }
    }

    att_handle_rewards_after_insert($mb_id, $date);

    jexit(['ok'=>true]);
}

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false, 'msg'=>'Invalid method']);
    if (!check_token($_POST['token'] ?? '')) jexit(['ok'=>false, 'msg'=>'Token error']);
    if (!$is_admin) jexit(['ok'=>false, 'msg'=>'관리자만 삭제할 수 있습니다.']);

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jexit(['ok'=>false, 'msg'=>'잘못된 요청입니다.']);

    sql_query("DELETE FROM `".G5_ATTENDANCE_TABLE."` WHERE id = {$id}", true);
    jexit(['ok'=>true]);
}

if ($action === 'list') {
    $scope = trim($_GET['scope'] ?? 'day'); // day | month
    $date  = preg_replace('/[^0-9\-]/', '', ($_GET['date'] ?? G5_TIME_YMD));
    $kw    = trim($_GET['kw'] ?? '');

    $where = [];

    if ($scope === 'day') {
        $where[] = "a.att_date = '".sql_real_escape_string($date)."'";
    } else if ($scope === 'month') {
        $y = (int)substr($date,0,4);
        $m = (int)substr($date,5,2);
        $start = sprintf('%04d-%02d-01', $y, $m);
        $end = date('Y-m-t', strtotime($start));
        $where[] = "a.att_date BETWEEN '".sql_real_escape_string($start)."' AND '".sql_real_escape_string($end)."'";
    }

    if ($kw !== '') {
        $kw_esc = sql_real_escape_string($kw);
        $where[] = "(m.mb_nick LIKE '%{$kw_esc}%' OR a.message LIKE '%{$kw_esc}%')";
    }

    global $g5;
    $member_table = $g5['member_table'];

    $sql = "SELECT a.id, a.mb_id, a.mood, a.message, a.att_date, a.att_time, m.mb_nick
            FROM `".G5_ATTENDANCE_TABLE."` a
            LEFT JOIN `{$member_table}` m ON m.mb_id = a.mb_id";
    if ($where) $sql .= " WHERE ".implode(' AND ', $where);
    $sql .= " ORDER BY a.att_date DESC, a.att_time DESC LIMIT 200";
    $res = sql_query($sql);
    $out = [];
    for ($i=0; $row = sql_fetch_array($res); $i++) {
        $out[] = [
            'id'   => (int)$row['id'],
            'mb_id'=> $row['mb_id'],
            'name' => $row['mb_nick'] ?: $row['mb_id'],
            'mood' => $row['mood'],
            'msg'  => $row['message'],
            'date' => $row['att_date'],
            'time' => substr($row['att_time'],0,5),
        ];
    }
    jexit(['ok'=>true, 'rows'=>$out, 'is_admin'=> (bool)$is_admin]);
}

if ($action === 'stats') {
    $y = (int)date('Y', G5_SERVER_TIME);
    $m = (int)date('m', G5_SERVER_TIME);
    $start = sprintf('%04d-%02d-01',$y,$m);
    $end = date('Y-m-t', strtotime($start));

    $mb_id = $member['mb_id'];

    $row = sql_fetch("SELECT COUNT(DISTINCT att_date) AS cnt FROM `".G5_ATTENDANCE_TABLE."` WHERE mb_id = '$mb_id' AND att_date BETWEEN '$start' AND '$end'");
    $month_cnt = (int)$row['cnt'];

    $res = sql_query("SELECT DISTINCT att_date FROM `".G5_ATTENDANCE_TABLE."` WHERE mb_id = '$mb_id' ORDER BY att_date ASC");
    $dates = [];
    for ($i=0; $r = sql_fetch_array($res); $i++) $dates[] = $r['att_date'];

    $best=0; $cur=0;
    if ($dates) {
        $best=1; $cur=1;
        for ($i=1; $i<count($dates); $i++) {
            $prev = strtotime($dates[$i-1]);
            $curr = strtotime($dates[$i]);
            $diff = ($curr - $prev)/86400;
            if ($diff == 1) { $cur++; } else { $cur = 1; }
            if ($cur > $best) $best = $cur;
        }
        $set = array_flip($dates);
        $d = date('Y-m-d', G5_SERVER_TIME);
        $cur2=0;
        while (isset($set[$d])) {
            $cur2++; $d = date('Y-m-d', strtotime($d.' -1 day'));
        }
        $cur = $cur2;
    }

    jexit(['ok'=>true, 'month'=>$month_cnt, 'best'=>$best, 'streak'=>$cur]);
}

// 내가 출석한 날짜(해당 월)만 반환
if ($action === 'days') {
    // 비회원이면 빈 배열 반환 (표시에만 쓰이므로 200 OK)
    if (!$is_member) jexit(['ok'=>true, 'days'=>[]]);

    $ym = preg_replace('/[^0-9\-]/', '', ($_GET['ym'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

    $start = $ym . '-01';
    $end   = date('Y-m-t', strtotime($start));

    $mb_id = $member['mb_id'];
    $res = sql_query(
        "SELECT DISTINCT att_date 
           FROM `".G5_ATTENDANCE_TABLE."`
          WHERE mb_id = '".sql_real_escape_string($mb_id)."'
            AND att_date BETWEEN '$start' AND '$end'
          ORDER BY att_date ASC"
    );

    $days = [];
    while ($row = sql_fetch_array($res)) $days[] = $row['att_date'];

    jexit(['ok'=>true, 'days'=>$days]);
}

// ===== 설정 로드 =====
function att_get_settings(){
    $row = sql_fetch("SELECT * FROM `".G5_ATTENDANCE_SETTINGS_TABLE."` WHERE id=1");
    if(!$row) return [
        'daily_points'       => 0,
        'every_days_n'       => 0,
        'every_days_points'  => 0,
        'streak_days'        => 7,
        'streak_points'      => 0,
    ];
    return [
        'daily_points'       => (int)$row['daily_points'],
        'every_days_n'       => (int)$row['every_days_n'],
        'every_days_points'  => (int)$row['every_days_points'],
        'streak_days'        => (int)$row['streak_days'],
        'streak_points'      => (int)$row['streak_points'],
    ];
}

// ===== 1회성 적립 보장 =====
function att_award_once($mb_id, $rule, $uniq_key, $points, $content, $att_date){
    if($points <= 0) return false;
    $exists = sql_fetch("SELECT 1 FROM `".G5_ATTENDANCE_REWARD_LOG_TABLE."` 
                         WHERE mb_id='".sql_real_escape_string($mb_id)."' 
                           AND rule='".sql_real_escape_string($rule)."'
                           AND uniq_key='".sql_real_escape_string($uniq_key)."'");
    if($exists) return false;

    // G5 표준 포인트 적립
    insert_point($mb_id, (int)$points, $content, 'attendance', $uniq_key, $rule);

    $now = G5_TIME_YMDHIS;
    $sql = "INSERT INTO `".G5_ATTENDANCE_REWARD_LOG_TABLE."`
            SET mb_id='".sql_real_escape_string($mb_id)."',
                rule='".sql_real_escape_string($rule)."',
                uniq_key='".sql_real_escape_string($uniq_key)."',
                points=".(int)$points.",
                att_date='".sql_real_escape_string($att_date)."',
                content='".sql_real_escape_string($content)."',
                reg_datetime='".sql_real_escape_string($now)."'";
    sql_query($sql, true);
    return true;
}

// ===== 연속 출석(해당 날짜 포함) 계산 =====
function att_get_streak_today($mb_id, $date){
    $streak = 0; $d = $date;
    while(true){
        $r = sql_fetch("SELECT 1 FROM `".G5_ATTENDANCE_TABLE."`
                        WHERE mb_id='".sql_real_escape_string($mb_id)."'
                          AND att_date='".sql_real_escape_string($d)."'");
        if(!$r) break;
        $streak++;
        $d = date('Y-m-d', strtotime($d.' -1 day'));
    }
    return $streak;
}

// ===== 저장 직후 포인트 처리 (핵심) =====
function att_handle_rewards_after_insert($mb_id, $date){
    $s = att_get_settings();

    // 0) 일일 보상 (중복 방지: daily:YYYY-MM-DD)
    if ($s['daily_points'] > 0) {
        att_award_once($mb_id, 'daily', 'daily:'.$date, $s['daily_points'], '일일 출석 보상', $date);
    }

    // 1) 누적 N일 마다 보상 (COUNT(DISTINCT)로 안전하게)
    if ($s['every_days_n'] > 0 && $s['every_days_points'] > 0) {
        $totrow = sql_fetch("SELECT COUNT(DISTINCT att_date) AS cnt 
                               FROM `".G5_ATTENDANCE_TABLE."`
                              WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        $total = (int)$totrow['cnt'];  // 오늘 INSERT 포함
        if ($total > 0 && ($total % $s['every_days_n']) === 0) {
            // 중복 방지 키: every:total:NN  (예: every:total:10)
            $uniq = 'every:total:'.$total;
            $content = '출석 누적 '.$total.'일 달성 보상';
            att_award_once($mb_id, 'every', $uniq, $s['every_days_points'], $content, $date);
        }
    }

    // 2) 연속 N일 보상
    if ($s['streak_days'] > 0 && $s['streak_points'] > 0) {
        $streak = att_get_streak_today($mb_id, $date);
        if ($streak > 0 && ($streak % $s['streak_days']) === 0) {
            // 중복 방지 키: streak:NN
            $uniq = 'streak:'.$streak;
            $content = '연속 '.$streak.'일 출석 보상';
            att_award_once($mb_id, 'streak', $uniq, $s['streak_points'], $content, $date);
        }
    }
}

// ===== 관리자: 설정 조회 =====
if ($action === 'get_settings') {
    if (!$is_admin) jexit(['ok'=>false,'msg'=>'관리자만 가능합니다.']);
    jexit(['ok'=>true] + att_get_settings());
}

// ===== 관리자: 설정 저장 =====
if ($action === 'save_settings') {
    if (!$is_admin) jexit(['ok'=>false,'msg'=>'관리자만 가능합니다.']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false,'msg'=>'Invalid method']);
    if (!check_token($_POST['token'] ?? '')) jexit(['ok'=>false,'msg'=>'Token error']);

    $every_n   = max(0, (int)($_POST['every_days_n'] ?? 0));
    $every_pt  = max(0, (int)($_POST['every_days_points'] ?? 0));
    $daily_pt  = max(0, (int)($_POST['daily_points'] ?? 0));      // ★ 추가
    $streak_n  = max(0, (int)($_POST['streak_days'] ?? 7));
    $streak_pt = max(0, (int)($_POST['streak_points'] ?? 0));

    $sql = "INSERT INTO `".G5_ATTENDANCE_SETTINGS_TABLE."`
            (id,every_days_n,every_days_points,daily_points,streak_days,streak_points,updated_by,updated_at)
            VALUES (1,$every_n,$every_pt,$daily_pt,$streak_n,$streak_pt,'".sql_real_escape_string($member['mb_id'])."',NOW())
            ON DUPLICATE KEY UPDATE
            every_days_n=VALUES(every_days_n),
            every_days_points=VALUES(every_days_points),
            daily_points=VALUES(daily_points),          -- ★ 추가
            streak_days=VALUES(streak_days),
            streak_points=VALUES(streak_points),
            updated_by=VALUES(updated_by),
            updated_at=VALUES(updated_at)";
    sql_query($sql, true);
    jexit(['ok'=>true]);
}

jexit(['ok'=>false, 'msg'=>'Unknown action']);
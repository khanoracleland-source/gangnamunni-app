<?php
if (!defined('_GNUBOARD_')) exit; // 가드

// 포인트 적립 헬퍼 (중복 방지 포함)
function rb_attend_insert_point($mb_id, $point, $content, $rel_act, $rel_id = 0) {
    if (!$point) return;
    if (!function_exists('insert_point')) return;

    // 동일 rel_action 중복지급 방지
    global $g5;
    $pt = isset($g5['point_table']) ? $g5['point_table'] : G5_TABLE_PREFIX.'point';
    $mb_id_esc = sql_real_escape_string($mb_id);
    $rel_act_esc = sql_real_escape_string($rel_act);

    $dup = sql_fetch("
        SELECT 1 FROM {$pt}
         WHERE mb_id = '{$mb_id_esc}'
           AND po_rel_table = '@attend'
           AND po_rel_action = '{$rel_act_esc}'
         LIMIT 1
    ");
    if ($dup) return;

    insert_point($mb_id, (int)$point, $content, '@attend', $rel_id, $rel_act);
}

// 설정 조회 (week/month/year 필드를 임계값 1/2/3로 사용)
function rb_attend_get_config() {
    $row = sql_fetch("SELECT * FROM rb_attend_config WHERE cf_id=1");
    if (!$row) $row = array(
        'week_streak_point'=>0,'month_streak_point'=>0,'year_streak_point'=>0,
        'bonus_rank1'=>0,'bonus_rank2'=>0,'bonus_rank3'=>0,'bonus_rank4'=>0,'bonus_rank5'=>0,
        'week_streak_len'=>7,'month_streak_len'=>30,'year_streak_len'=>365
    );
    return $row;
}

// ymd 8자리 정규화
function rb_attend_norm_ymd8($ymd_like) {
    $s = is_string($ymd_like) ? preg_replace('/[^0-9]/', '', $ymd_like) : '';
    if (strlen($s) === 8) return $s;
    return date('Ymd');
}

// 이미 오늘 출석했는지 확인
function rb_attend_is_checked_today($mb_id, $ymd8) {
    $ymd8 = rb_attend_norm_ymd8($ymd8);
    $sql = "SELECT at_id FROM rb_attendance WHERE mb_id='".sql_real_escape_string($mb_id)."' AND REPLACE(ymd,'-','')='{$ymd8}' LIMIT 1";
    $row = sql_fetch($sql);
    return !empty($row['at_id']);
}

// 순위 계산 (1~5등)
function rb_attend_pick_rank($ymd8) {
    $ymd8 = rb_attend_norm_ymd8($ymd8);
    $row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_attendance WHERE REPLACE(ymd,'-','')='{$ymd8}'");
    $n = (int)$row['cnt'];
    if ($n >= 5) return null;
    return $n + 1;
}

// 안전 파싱
function rb_attend_parse_ymd($ymd) {
    $s = rb_attend_norm_ymd8($ymd);
    $dt = DateTime::createFromFormat('Ymd', $s);
    if (!$dt) return false;
    $e = DateTime::getLastErrors();
    if (!empty($e['warning_count']) || !empty($e['error_count'])) return false;
    return $dt;
}

// 연속 출석 처리 및 보너스
// - 월이 바뀌면 cont=1로 리셋
// - 같은 달에서는 임계값들(week/month/year 필드에 지정, 예: 3/10/20)마다 반복 지급
function rb_attend_update_streak_and_award($mb_id, $ymd8, $cf) {
    $ymd8 = rb_attend_norm_ymd8($ymd8);

    // 오늘 날짜
    $today_dt = rb_attend_parse_ymd($ymd8);
    if (!$today_dt) $today_dt = rb_attend_parse_ymd(date('Ymd'));
    $today_Ymd = $today_dt->format('Ymd');
    $today_Ym  = $today_dt->format('Ym');

    // 현재 스냅샷
    $snap = sql_fetch("SELECT * FROM rb_attend_streak WHERE mb_id='".sql_real_escape_string($mb_id)."'");

    // 연속일 계산 (월 경계에서 리셋)
    $cont = 1;
    if ($snap) {
        $last_dt = rb_attend_parse_ymd($snap['last_ymd']);
        if ($last_dt) {
            $last_Ym = $last_dt->format('Ym');

            if ($last_Ym !== $today_Ym) {
                // 다른 달 → 리셋
                $cont = 1;
            } else {
                // 같은 달
                $diff = (int)$last_dt->diff($today_dt)->format('%a');
                if ($diff === 0) {
                    // 같은 날 재호출 → 증가 없음
                    $cont = (int)$snap['cont_days'];
                } else if ($diff === 1) {
                    // 하루 이어짐
                    $cont = max(1, (int)$snap['cont_days']) + 1;
                } else {
                    // 하루 이상 공백 → 리셋
                    $cont = 1;
                }
            }
        } else {
            $cont = 1;
        }

        sql_query("
            UPDATE rb_attend_streak
               SET last_ymd='".sql_real_escape_string($today_Ymd)."',
                   cont_days='{$cont}'
             WHERE mb_id='".sql_real_escape_string($mb_id)."'
        ");
    } else {
        // 최초 기록
        $cont = 1;
        sql_query("
            INSERT INTO rb_attend_streak (mb_id,last_ymd,cont_days)
            VALUES ('".sql_real_escape_string($mb_id)."','".sql_real_escape_string($today_Ymd)."',1)
        ");
    }

    // ===== 같은 달 안에서 임계값들(3종)마다 반복 지급 =====
    // 예) 3, 10, 20으로 설정했다면 3/6/9..., 10/20/30..., 20/40...일마다 지급
    $rules = [
        ['len' => (int)($cf['week_streak_len']   ?? 0), 'pt' => (int)($cf['week_streak_point'] ?? 0), 'code' => 'w'],
        ['len' => (int)($cf['month_streak_len']  ?? 0), 'pt' => (int)($cf['month_streak_point']?? 0), 'code' => 'm'],
        ['len' => (int)($cf['year_streak_len']   ?? 0), 'pt' => (int)($cf['year_streak_point'] ?? 0), 'code' => 'y'],
    ];

    $award_points = 0;
    $awarded_keys = []; // 예: ['w3','m10']

    if ($cont > 0) {
        foreach ($rules as $r) {
            if ($r['len'] > 0 && $r['pt'] > 0 && ($cont % $r['len'] === 0)) {
                $award_points += $r['pt'];
                $awarded_keys[] = $r['code'].$r['len'];
            }
        }
    }

    // 보너스 지급 (동일 날짜+임계키 조합으로 중복 방지)
    if ($award_points > 0) {
        $rel_act = $today_Ymd.'-streak-'.$mb_id.'-cont'.$cont.'-'.implode('+', $awarded_keys);
        rb_attend_insert_point($mb_id, $award_points, '연속 출석 보너스', $rel_act, 0);
    }

    return [
        'cont'    => $cont,                // 현재 연속일
        'bonus'   => $award_points,        // 오늘 지급된 총 포인트
        'awards'  => $awarded_keys,        // 오늘 충족한 임계값 코드 목록
        'month'   => $today_Ym,            // 기준 월 (YYYYMM)
        'today'   => $today_Ymd,
    ];
}

// 랭크 라벨
function rb_attend_rank_label($rank) {
    if ($rank === null) return '';
    if ($rank == 1) return '[1st]';
    if ($rank == 2) return '[2nd]';
    if ($rank == 3) return '[3rd]';
    if ($rank == 4) return '[4th]';
    if ($rank == 5) return '[5th]';
    return '';
}

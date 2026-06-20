<?php
include_once(dirname(__FILE__).'/../../../common.php');
include_once(G5_PATH.'/rb/rb.mod/attendance/attend.lib.php');
if (!defined('_GNUBOARD_')) exit;

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
$ymd8 = isset($_GET['ymd']) ? preg_replace('/[^0-9]/', '', $_GET['ymd']) : (defined('G5_TIME_YMD') ? G5_TIME_YMD : date('Ymd'));
if (strlen($ymd8) !== 8) $ymd8 = date('Ymd');

// 목록 쿼리
$mt = $g5['member_table'];
$q = sql_query("
    SELECT a.*, REPLACE(a.ymd,'-','') AS ymd8, m.mb_nick
    FROM rb_attendance a
    LEFT JOIN {$mt} m ON m.mb_id = a.mb_id
    WHERE REPLACE(a.ymd,'-','')='{$ymd8}'
    ORDER BY (a.at_rank IS NULL) ASC, a.at_rank ASC, a.at_datetime ASC, a.at_id ASC
");
$total_row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_attendance WHERE REPLACE(ymd,'-','')='{$ymd8}'");
$total_count = (int)$total_row['cnt'];

// 설정 로드 (등수 배지 활성)
$cf = rb_attend_get_config();
$rb_rank_enabled = array(
  1 => !empty($cf['bonus_rank1']),
  2 => !empty($cf['bonus_rank2']),
  3 => !empty($cf['bonus_rank3']),
  4 => !empty($cf['bonus_rank4']),
  5 => !empty($cf['bonus_rank5'])
);

// HTML 모드
if ($format === 'html') {
    // 사이드뷰는 서버사이드로 그대로 출력
    ob_start();
    echo '<div data-rb-list-count="'.(int)$total_count.'"></div>';
    $printed = 0;
    while ($r = sql_fetch_array($q)) {
        $printed++;
        $mbs = get_member($r['mb_id']);
        $nick_plain = get_text(isset($mbs['mb_nick']) ? $mbs['mb_nick'] : $r['mb_id']);
        $sv = get_sideview(
            $r['mb_id'],
            $nick_plain,
            isset($mbs['mb_email']) ? $mbs['mb_email'] : '',
            isset($mbs['mb_homepage']) ? $mbs['mb_homepage'] : ''
        );
        $content = nl2br(get_text($r['at_content']));
        $rank = (int)$r['at_rank'];
        $badge = '';
        if ($rank >= 1 && $rank <= 5 && !empty($rb_rank_enabled[$rank])) {
            $badge = '<div class="rb-att-badge r'.$rank.'">'.$rank.'등</div>';
        }
        
        // 등수 포인트
        $rank_point_html = '';
        if ($rank >= 1 && $rank <= 5 && !empty($rb_rank_enabled[$rank])) {
            $k = 'bonus_rank' . (string)$rank;
            // 설정값에서 포인트 읽기 (없으면 0)
            $p = (int)($cf[$k] ?? 0);
            if ($p > 0) {
                $rank_point_html = $badge.'<div class="rb-att-earned"><span class="font-B main_color">' . number_format($p) . 'P</span> 획득</div>';
            }
        }
        
        echo '<div class="rb-att-item">';
        echo '<div class="rb-att-body">';
        echo '<div class="rb-att-meta" title="미니홈" onclick="location.href=\''.G5_URL.'/rb/home.php?mb_id='.$r['mb_id'].'\';">'.$sv.'　'.$r['at_datetime'].'</div>';
        echo '<div class="rb-att-text">'.$content.'</div>';
        if ($rank_point_html) echo $rank_point_html;
        echo '</div>';
        echo '</div>';
    }
    if (!$printed) echo '<div class="rb-att-empty">출석 한마디가 없습니다.</div>';
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// JSON 모드(기존 유지 필요시)
header('Content-Type: application/json; charset=utf-8');
$list = array();
while ($r = sql_fetch_array($q)) {
    $mbs = get_member($r['mb_id']);
    $nick_plain = get_text(isset($mbs['mb_nick']) ? $mbs['mb_nick'] : $r['mb_id']);
    $sv = get_sideview(
        $r['mb_id'],
        $nick_plain,
        isset($mbs['mb_email']) ? $mbs['mb_email'] : '',
        isset($mbs['mb_homepage']) ? $mbs['mb_homepage'] : ''
    );
    $list[] = array(
        'id' => (int)$r['at_id'],
        'ymd' => $r['ymd8'],
        'rank' => isset($r['at_rank']) ? (int)$r['at_rank'] : null,
        'content' => (string)$r['at_content'],
        'datetime' => (string)$r['at_datetime'],
        'mb_id' => (string)$r['mb_id'],
        'mb_nick' => (string)$r['mb_nick'],
        'mb_sideview' => $sv
    );
}
echo json_encode(array('ok'=>1,'ymd'=>$ymd8,'count'=>count($list),'list'=>$list));
exit;

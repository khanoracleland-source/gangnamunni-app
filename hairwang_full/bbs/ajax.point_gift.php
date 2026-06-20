<?php
include_once('./_common.php');

// AJAX 요청 체크
$is_ajax = true;

// 회원 체크
if (!$is_member) {
    die(json_encode(array('error' => '회원만 포인트 선물이 가능합니다.')));
}

// 필수 파라미터 체크
if (!isset($_POST['wr_id']) || !isset($_POST['bo_table']) || !isset($_POST['point_amount'])) {
    die(json_encode(array('error' => '필수 항목이 누락되었습니다.')));
}

$bo_table = isset($_POST['bo_table']) ? clean_xss_tags($_POST['bo_table']) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$point_amount = isset($_POST['point_amount']) ? (int)$_POST['point_amount'] : 0;

// 게시물 정보 가져오기
$write_table = $g5['write_prefix'] . $bo_table;
$write = sql_fetch(" select * from $write_table where wr_id = '$wr_id' ");
if (!$write) {
    die(json_encode(array('error' => '존재하지 않는 게시물입니다.')));
}

// 자신의 글에는 포인트 선물 불가
if ($member['mb_id'] == $write['mb_id']) {
    die(json_encode(array('error' => '자신의 글에는 포인트를 선물할 수 없습니다.')));
}

// 포인트 선물 내역 확인 (중복 선물 방지)
$sql = " select count(*) as cnt from {$g5['point_table']}
          where mb_id = '{$member['mb_id']}'
            and po_rel_table = '$bo_table'
            and po_rel_id = '$wr_id'
            and po_rel_action = 'gift' ";
$row = sql_fetch($sql);
if ($row['cnt'] > 0) {
    die(json_encode(array('error' => '이미 이 게시물에 포인트를 선물하셨습니다.')));
}

// 보유 포인트 확인
if ($member['mb_point'] < $point_amount) {
    die(json_encode(array('error' => '보유하신 포인트가 부족합니다.')));
}

// 포인트 선물 (기부자 포인트 차감)
$content = $bo_table . ' ' . $wr_id . ' 게시물에 포인트 선물';
insert_point($member['mb_id'], $point_amount * (-1), $content, $bo_table, $wr_id, 'gift');

// 게시물 작성자에게 포인트 지급
$content = $member['mb_nick'] . '님으로부터 ' . $bo_table . ' ' . $wr_id . ' 게시물에 포인트 선물 받음';
insert_point($write['mb_id'], $point_amount, $content, $bo_table, $wr_id, 'gift_receive');

// 포인트 선물 내역 가져오기
$sql = " select a.po_id, a.mb_id, a.po_datetime, a.po_content, a.po_point, b.mb_nick
          from {$g5['point_table']} a
          left join {$g5['member_table']} b on a.mb_id = b.mb_id
          where a.po_rel_table = '$bo_table'
            and a.po_rel_id = '$wr_id'
            and a.po_rel_action = 'gift'
          order by a.po_id desc 
          limit 0, 5 ";
$gift_result = sql_query($sql);
$gift_count = sql_num_rows($gift_result);

// HTML 생성
$html = '';
if ($gift_count > 0) {
    $html .= '<div id="bo_v_gift_list">';
    $html .= '<h2>포인트 선물 내역</h2>';
    $html .= '<ul>';
    
    for ($i=0; $row=sql_fetch_array($gift_result); $i++) {
        $html .= '<li>';
        $html .= '<span class="gift_nick">'.get_text($row['mb_nick']).'</span>님이 ';
        $html .= '<span class="gift_point">'.number_format(abs($row['po_point'])).'</span>포인트를 선물하였습니다.';
        $html .= '<span class="gift_datetime">('.substr($row['po_datetime'], 2, 14).')</span>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
}

// 남은 포인트 계산
$remain_point = $member['mb_point'] - $point_amount;

// 성공 응답
die(json_encode(array(
    'success' => true,
    'message' => '포인트 선물이 완료되었습니다.',
    'html' => $html,
    'remain_point' => number_format($remain_point)
)));
?>

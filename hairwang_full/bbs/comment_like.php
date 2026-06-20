<?php
include_once('./_common.php');

// AJAX로 JSON 형태 반환
header('Content-Type: application/json; charset=utf-8');

// 1) 파라미터 (POST)
$bo_table   = isset($_POST['bo_table'])   ? trim($_POST['bo_table'])   : '';
$comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
$w          = isset($_POST['w'])          ? trim($_POST['w'])          : ''; // 'good' or 'nogood'

// 2) 파라미터 검증
if(!$bo_table || !$comment_id) {
    echo json_encode(array("error" => "잘못된 요청입니다.(파라미터 누락)"));
    exit;
}

// 3) 게시판 정보
$board = get_board_db($bo_table, true);
if(!$board['bo_table']) {
    echo json_encode(array("error" => "존재하지 않는 게시판입니다."));
    exit;
}

// 4) 로그인 회원만 허용
if(!$member['mb_id']) {
    echo json_encode(array("error" => "로그인 후 이용하실 수 있습니다."));
    exit;
}

// 5) 댓글 정보 (wr_is_comment=1)
$write_table = $g5['write_prefix'] . $bo_table;
$sql = " SELECT wr_id, wr_good, wr_nogood, mb_id AS cmt_mb_id
         FROM {$write_table}
         WHERE wr_id = '{$comment_id}'
           AND wr_is_comment = '1' ";
$comment = sql_fetch($sql);

if(!$comment['wr_id']) {
    echo json_encode(array("error" => "존재하지 않는 댓글입니다."));
    exit;
}

// (A) 본인 댓글이면 불가
if($comment['cmt_mb_id'] == $member['mb_id']) {
    echo json_encode(array("error" => "본인 댓글에는 좋아요/싫어요를 할 수 없습니다."));
    exit;
}

// 6) 좋아요 / 싫어요 구분
$act = '';
if($w === 'good') {
    $act = 'good';
} else if($w === 'nogood') {
    $act = 'nogood';
} else {
    echo json_encode(array("error" => "정상적인 요청이 아닙니다.(w 파라미터 오류)"));
    exit;
}

// (B) 이미 좋아요/싫어요 했는지 테이블 체크
//     한 댓글에 한 회원이 good/nogood 중 하나라도 눌렀다면 더 이상 불가
$sql = " SELECT cg_type
         FROM g5_comment_good
         WHERE bo_table = '{$bo_table}'
           AND wr_id    = '{$comment_id}'
           AND mb_id    = '{$member['mb_id']}' ";
$row = sql_fetch($sql);
//var_dump($row,$sql);
if(@$row['cg_type']) {
    // 이미 기록이 있음
    // 만약 “좋아요 누르면 싫어요도 절대 불가” 로 완전 배타라면
    // 이 시점에서 "이미 좋아요/싫어요를 누르셨습니다." 라고 막음
    echo json_encode(array("error" => "이미 좋아요/싫어요를 누르셨습니다."));
    exit;
}

// (C) 좋아요/싫어요 테이블 기록
$sql = " INSERT INTO g5_comment_good
         SET bo_table   = '{$bo_table}',
             wr_id      = '{$comment_id}',
             mb_id      = '{$member['mb_id']}',
             cg_type    = '{$act}',
             cg_datetime= '".G5_TIME_YMDHIS."' ";
sql_query($sql);

// (D) 실제 wr_good / wr_nogood +1
if($act === 'good') {
    $sql = " UPDATE {$write_table}
             SET wr_good = wr_good + 1
             WHERE wr_id = '{$comment_id}'
               AND wr_is_comment = '1' ";
    sql_query($sql);

    // 갱신된 값 다시 조회
    $sql = " SELECT wr_good
             FROM {$write_table}
             WHERE wr_id = '{$comment_id}'
               AND wr_is_comment = '1' ";
    $cmt_new = sql_fetch($sql);
    $count   = (int)$cmt_new['wr_good'];

} else {
    // nogood
    $sql = " UPDATE {$write_table}
             SET wr_nogood = wr_nogood + 1
             WHERE wr_id = '{$comment_id}'
               AND wr_is_comment = '1' ";
    sql_query($sql);

    // 갱신된 값 다시 조회
    $sql = " SELECT wr_nogood
             FROM {$write_table}
             WHERE wr_id = '{$comment_id}'
               AND wr_is_comment = '1' ";
    $cmt_new = sql_fetch($sql);
    $count   = (int)$cmt_new['wr_nogood'];
}

// 성공적으로 count까지 구했으면 JSON 리턴
echo json_encode(array("success" => true, "count" => $count));
exit;

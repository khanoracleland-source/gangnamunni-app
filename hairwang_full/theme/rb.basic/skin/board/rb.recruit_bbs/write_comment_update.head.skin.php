<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 신규 댓글 작성시에만 체크
if($w == 'c' && $is_member) {
    // 1. 오늘 댓글을 작성한 적이 있는지 체크 (하루에 첫 댓글만 포인트)
    $today_comment = sql_fetch("
        SELECT count(*) as cnt 
        FROM {$write_table} 
        WHERE wr_is_comment = '1' 
        AND mb_id = '{$member['mb_id']}' 
        AND substring(wr_datetime,1,10) = '".G5_TIME_YMD."'
    ");
    
    // 오늘 이미 댓글을 작성했다면 포인트 미지급
    if ($today_comment['cnt'] > 0) {
        $board['bo_comment_point'] = 0;
    }
    
    // 2. 본인 글에 본인이 댓글 작성하는지 체크
    $parent_mb = sql_fetch("SELECT mb_id FROM {$write_table} WHERE wr_id = '{$wr_id}' AND wr_is_comment = '0'");
    if ($parent_mb['mb_id'] == $member['mb_id']) {
        $board['bo_comment_point'] = 0;
    }
    
    // 3. 같은 게시글에 이미 댓글을 작성했는지 체크
    $exist_comment = sql_fetch("
        SELECT COUNT(*) as cnt 
        FROM {$write_table} 
        WHERE wr_parent = '{$wr_id}' 
        AND wr_is_comment = '1' 
        AND mb_id = '{$member['mb_id']}'
    ");
    
    if ($exist_comment['cnt'] > 0) {
        $board['bo_comment_point'] = 0;
    }
}
?>
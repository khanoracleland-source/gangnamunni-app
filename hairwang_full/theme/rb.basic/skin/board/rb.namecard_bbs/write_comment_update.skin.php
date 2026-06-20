<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

//원글작성자를 구함
$row = sql_fetch(" select mb_id from {$write_table} where wr_id = '{$wr_id}' ");
$mb_id_ms = $row['mb_id'];


//댓글 바로가기 주소&&#c_42

if($mb_id_ms && $w == "c") { // 신규댓글 이면,
    
    //글 작성자에게 발송
    if(isset($mb_id_ms) && isset($mb_id) && $mb_id_ms != $mb_id) { // 원글작성자와 댓글작성자가 같다면 통과
        memo_auto_send($board['bo_subject'].'의 게시물에 댓글이 등록 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $mb_id_ms, "system-msg");
    }
    
}

?> 

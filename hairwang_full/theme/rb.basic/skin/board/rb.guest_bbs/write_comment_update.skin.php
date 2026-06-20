<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$link_url = G5_BBS_URL."/board.php?bo_table=".$bo_table."&wr_id=".$wr_id."&".$qstr."#c_{$comment_id}";

if($w == "c") { // 신규댓글 이면,
    
    //댓글의 댓글 작성시 쪽지
    if (strlen($tmp_comment_reply) > 0) {
        //원글 작성자가 코멘트 입력이나 수정시 패스
        if ($reply_array['mb_id'] == $mb_id || !$reply_array['mb_id']) {
            // return 0;
        } else {
            if ($is_member){
                $smember_id = $mb_id;
            } else {
                //손님에게 코멘트 허용시 또는 테스트용 아이디
                $smember_id = "guest";
            }
            memo_auto_send($board['bo_subject'].'의 게시물의 댓글에 댓글이 등록 되었습니다.', $link_url, $reply_array['mb_id'], "system-msg");
        }
    }


    //게시물의 댓글 작성시 작성자에게 쪽지
    //원글 작성자가 코멘트 입력이나 수정시 또는 코멘트답변 입력시 패스
    if ($wr['mb_id'] == $member['mb_id'] || $wr['mb_id'] == $reply_array['mb_id']) {
        // return 0;
    } else {
        if ($is_member){
            $smember_id = $member['mb_id'];
        } else {
            //손님에게 코멘트 허용시 또는 테스트용 아이디
            $smember_id = "guest";
        }
        memo_auto_send($board['bo_subject'].'의 게시물에 댓글이 등록 되었습니다.', $link_url, $wr['mb_id'], "system-msg");
    }

}

if(!$is_admin) {
    if(isset($_POST['wr_name']) && $_POST['wr_name']) {

        $new_wr_name = sql_escape_string($_POST['wr_name']);

        $sqls = "UPDATE {$write_table} 
            SET wr_name = '{$new_wr_name}' 
         WHERE wr_id = '{$comment_id}' and wr_is_comment = '1' ";
        sql_query($sqls);
        
        //새글데이터 제거
        $sqls = "UPDATE {$g5['board_new_table']} 
            SET mb_id = '' 
         WHERE wr_id = '{$wr_id}' and bo_table = '{$bo_table}' ";
        sql_query($sqls);

    }
}

// 리다이렉트 처리 (모든 환경에서 동작하도록 수정)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // AJAX 요청인 경우 (XMLHttpRequest)
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => $link_url]);
        exit;
    }
    // 웹앱 요청인 경우
    if (isset($app['ap_title']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == $app['ap_title']) {
        goto_url($link_url);
    }
}

// 일반 폼 제출 또는 기타 모든 경우 - 반드시 리다이렉트
goto_url($link_url);

?>
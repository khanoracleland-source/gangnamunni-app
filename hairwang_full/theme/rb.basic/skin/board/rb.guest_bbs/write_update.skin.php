<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
    @include_once(G5_PATH.'/rb/rb.lib/ajax.upload_write_update.php'); // 파일첨부를 끌어오기 형태로 업로드 하는 경우만 추가해주세요.
    
    if(!$is_admin) {
        if(isset($_POST['wr_name']) && $_POST['wr_name']) {

            //게시물 데이터 변경
            $sqls = "UPDATE {$write_table} 
                SET wr_name = '{$_POST['wr_name']}' 
             WHERE wr_id = '{$wr_id}'";
            sql_query($sqls);
            
            //새글데이터 제거
            $sqls = "UPDATE {$g5['board_new_table']} 
                SET mb_id = '' 
             WHERE wr_id = '{$wr_id}' and bo_table = '{$bo_table}' ";
            sql_query($sqls);
        }
    }

    if($w == "") {
        //관리자에게 쪽지발송
        memo_auto_send($board['bo_subject'].'에 새글이 등록 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $config['cf_admin'], "system-msg");
    }
?> 

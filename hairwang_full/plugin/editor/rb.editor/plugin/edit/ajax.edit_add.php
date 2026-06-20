<?php
include_once('./_common.php');
//if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$ed_id = isset($_POST['ed_id']) ? $_POST['ed_id'] : '';
$mb_id = isset($_POST['mb_id']) ? $_POST['mb_id'] : '';
$wr_id = isset($_POST['wr_id']) ? $_POST['wr_id'] : '';
$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
$wr_subject = isset($_POST['wr_subject']) ? $_POST['wr_subject'] : '';
$write_id = isset($_POST['write_id']) ? $_POST['write_id'] : '';
$ed_type = isset($_POST['ed_type']) ? $_POST['ed_type'] : '';
?>

<?php

$rb_editor_edit_table = G5_TABLE_PREFIX . "rb_editor_edit"; //테이블명
$rb_editor_edit_table2 = G5_TABLE_PREFIX . "rb_editor_edit_content"; //테이블명
$write_tables = G5_TABLE_PREFIX . "write_" . $bo_table;

$mb = get_member($mb_id);

if($ed_type == "ps") { //게재
    
    if($mb_id && $wr_id && $bo_table) {

        $sql = " UPDATE {$rb_editor_edit_table2} SET ed_status = '1' WHERE ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' and ed_mb_id = '{$mb_id}' and ed_id = '{$ed_id}' ; ";
        sql_query($sql);
        
        $content = sql_fetch ("select ed_wr_content from {$rb_editor_edit_table2} where ed_id = '{$ed_id}' ");
        
        $sql2 = " UPDATE {$write_tables} SET wr_content = '{$content['ed_wr_content']}', wr_datetime_last = '".G5_TIME_YMDHIS."', wr_datetime_last_id = '{$mb_id}' WHERE wr_id = '{$wr_id}' and wr_is_comment = '0'; ";
        sql_query($sql2);

        memo_auto_send('[편집 승인] ['.$wr_subject.'] 게시물의 편집 내용이 반영 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $mb_id, "system-msg");
        
        echo 'alert( "승인 처리 되었습니다.");';

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
} else if($ed_type == "pc") { //요청취소
    
    if($mb_id && $wr_id && $bo_table) {
        
        $sql = "DELETE FROM {$rb_editor_edit_table2} WHERE ed_mb_id = '{$mb_id}' and ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' and ed_id = '{$ed_id}' ;";
        sql_query($sql);
            
        memo_auto_send('[편집 취소] ['.$wr_subject.'] 게시물의 편집 요청이 철회 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $mb_id, "system-msg");
            
        echo 'alert( "편집승인 요청을 취소 하였습니다.");';

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
    
    
} else if($ed_type == "confirm") { //승인
    
    if($mb_id && $wr_id && $bo_table) {

        $sql = " UPDATE {$rb_editor_edit_table} SET ed_status = '1' WHERE ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' and ed_mb_id = '{$mb_id}' and ed_id = '{$ed_id}' ; ";
        sql_query($sql);

        memo_auto_send('[편집권한 승인] ['.$wr_subject.'] 게시물의 편집권한이 승인 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $mb_id, "system-msg");
        
        echo 'alert( "승인 처리 되었습니다.");';

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
} else if($ed_type == "cancle") { //취소
    
    if($mb_id && $wr_id && $bo_table) {
        
        $sql = " UPDATE {$rb_editor_edit_table} SET ed_status = '0' WHERE ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' and ed_mb_id = '{$mb_id}' and ed_id = '{$ed_id}' ; ";
        sql_query($sql);

        memo_auto_send('[편집권한 취소] ['.$wr_subject.'] 게시물의 편집권한이 취소 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $mb_id, "system-msg");
        
        echo 'alert( "취소 처리 되었습니다.");';

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
    
} else if($ed_type == "del") { //요청취소
    
    if($mb_id && $wr_id && $bo_table) {
        
        $sql = "DELETE FROM {$rb_editor_edit_table} WHERE ed_mb_id = '{$mb_id}' and ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' and ed_id = '{$ed_id}' ;";
        sql_query($sql);
            
        memo_auto_send('[편집권한 요청취소] '.$mb['mb_nick'].'님 께서 ['.$wr_subject.'] 게시물의 편집권한 요청을 철회 하였습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $write_id, "system-msg");
            
        echo 'alert( "편집권한 요청을 취소 하였습니다.");';

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
    
    
} else { 
    
    if($mb_id && $wr_id && $bo_table && $write_id) {

        //편집권한이 있는지 조회
        $acc = 0;
        $edit = sql_fetch (" select COUNT(*) as cnt from {$rb_editor_edit_table} where ed_mb_id = '{$mb_id}' and ed_wr_id = '{$wr_id}' and ed_bo_table = '{$bo_table}' ed_status = '1' ");
        
        if(isset($edit['cnt']) && $edit['cnt'] > 0) {
            $acc = $edit['cnt'];
        } else { 
            $acc = 0;
        }

        //권힌이 없는 경우 요청
        if(isset($acc) && $acc > 0) {
            echo 'alert( "이미 편집 권한이 있습니다.");';
        } else { 

            $sql = " insert into {$rb_editor_edit_table} ( ed_bo_table, ed_mb_id, ed_wr_id, ed_wr_subject, ed_write_id, ed_status, ed_time ) values ( '{$bo_table}', '{$mb_id}', '{$wr_id}', '{$wr_subject}', '{$write_id}', '0', '".G5_TIME_YMDHIS."' ) ";
            sql_query($sql);
            
            memo_auto_send('[편집권한 요청] '.$mb['mb_nick'].'님 께서 ['.$wr_subject.'] 게시물에 편집권한 요청을 하였습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $write_id, "system-msg");
            
            echo 'alert( "편집권한 요청을 완료 하였습니다.\n원 저작자 승인 시 본 게시물을 편집하실 수 있습니다.");';
        }

    } else { 
        echo 'alert( "처리에 문제가 있습니다. 다시 시도해주세요.");';
    }
    
}

?>

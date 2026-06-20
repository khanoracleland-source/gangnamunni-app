<?php
$sub_menu = '000620';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");

check_admin_token();
@mkdir(G5_DATA_PATH . "/chat", G5_DIR_PERMISSION);
@chmod(G5_DATA_PATH . "/chat", G5_DIR_PERMISSION);


// 컬럼이 있는지 검사한다.
$cnt = sql_fetch("SELECT COUNT(*) as cnt FROM rb_chat_set");

            
if ($cnt['cnt'] > 0) {
    
    $sql = "UPDATE rb_chat_set
            SET ch_days_old = '{$_POST['ch_days_old']}',
                ch_max_file_size = '{$_POST['ch_max_file_size']}',
                ch_extension = '{$_POST['ch_extension']}',
                ch_position = '{$_POST['ch_position']}',
                ch_position_x = '{$_POST['ch_position_x']}',
                ch_position_y = '{$_POST['ch_position_y']}',
                ch_ref_1 = '{$_POST['ch_ref_1']}',
                ch_ref_2 = '{$_POST['ch_ref_2']}', 
                ch_ref_3 = '{$_POST['ch_ref_3']}', 
                ch_ref_4 = '{$_POST['ch_ref_4']}', 
                ch_push = '{$_POST['ch_push']}', 
                ch_level = '{$_POST['ch_level']}', 
                ch_use = '{$_POST['ch_use']}', 
                ch_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
    
} else {
    
    $sql = "INSERT INTO rb_chat_set 
            SET ch_days_old = '{$_POST['ch_days_old']}',
                ch_max_file_size = '{$_POST['ch_max_file_size']}',
                ch_extension = '{$_POST['ch_extension']}',
                ch_position = '{$_POST['ch_position']}',
                ch_position_x = '{$_POST['ch_position_x']}',
                ch_position_y = '{$_POST['ch_position_y']}',
                ch_ref_1 = '{$_POST['ch_ref_1']}',
                ch_ref_2 = '{$_POST['ch_ref_2']}', 
                ch_ref_3 = '{$_POST['ch_ref_3']}', 
                ch_ref_4 = '{$_POST['ch_ref_4']}', 
                ch_push = '{$_POST['ch_push']}', 
                ch_level = '{$_POST['ch_level']}', 
                ch_use = '{$_POST['ch_use']}', 
                ch_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
    
}


update_rewrite_rules();

goto_url('./chat_form.php', false);
?>
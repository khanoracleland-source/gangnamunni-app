<?php
$sub_menu = '000640';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");

check_admin_token();


// 컬럼이 있는지 검사한다.
$cnt = sql_fetch("SELECT COUNT(*) as cnt FROM rb_subscribe_set");

            
if ($cnt['cnt'] > 0) {
    
    $sql = "UPDATE rb_subscribe_set
            SET sb_use = '{$_POST['sb_use']}', 
                sb_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
    
} else {
    
    $sql = "INSERT INTO rb_subscribe_set 
            SET sb_use = '{$_POST['sb_use']}', 
                sb_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
    
}


update_rewrite_rules();

goto_url('./subscribe_form.php', false);
?>
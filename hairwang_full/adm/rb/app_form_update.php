<?php
$sub_menu = '000600';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");

check_admin_token();
@mkdir(G5_DATA_PATH . "/push", G5_DIR_PERMISSION);
@chmod(G5_DATA_PATH . "/push", G5_DIR_PERMISSION);


// 컬럼이 있는지 검사한다.
$cnt = sql_fetch("SELECT COUNT(*) as cnt FROM rb_app");

            
if ($cnt['cnt'] > 0) {
    $sql = "UPDATE rb_app
            SET ap_title = '{$_POST['ap_title']}',
                ap_pid = '{$_POST['ap_pid']}',
                ap_key = '{$_POST['ap_key']}',
                ap_systems_msg = '{$_POST['ap_systems_msg']}',
                ap_btn_is = '{$_POST['ap_btn_is']}', 
                ap_btn_url = '{$_POST['ap_btn_url']}', 
                ap_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
} else {
    $sql = "INSERT INTO rb_app 
            SET ap_title = '{$_POST['ap_title']}',
                ap_pid = '{$_POST['ap_pid']}',
                ap_key = '{$_POST['ap_key']}',
                ap_systems_msg = '{$_POST['ap_systems_msg']}', 
                ap_btn_is = '{$_POST['ap_btn_is']}', 
                ap_btn_url = '{$_POST['ap_btn_url']}', 
                ap_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
}

update_rewrite_rules();

goto_url('./app_form.php', false);
?>
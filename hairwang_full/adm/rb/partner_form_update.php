<?php
$sub_menu = '000650';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");
check_admin_token();


// 컬럼이 있는지 검사한다.
$cnt = sql_fetch("SELECT COUNT(*) as cnt FROM rb_partner");

     
if ($cnt['cnt'] > 0) {
    $sql = "UPDATE rb_partner
            SET pa_is = '{$_POST['pa_is']}',
                pa_use = '{$_POST['pa_use']}',
                pa_ssr = '{$_POST['pa_ssr']}',
                pa_ssr2 = '{$_POST['pa_ssr2']}',
                pa_day = '{$_POST['pa_day']}', 
                pa_add_use = '{$_POST['pa_add_use']}', 
                pa_item_use = '{$_POST['pa_item_use']}', 
                pa_level = '{$_POST['pa_level']}', 
                pa_point_use = '{$_POST['pa_point_use']}', 
                pa_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
} else {
    $sql = "INSERT INTO rb_partner 
            SET pa_is = '{$_POST['pa_is']}',
                pa_use = '{$_POST['pa_use']}',
                pa_ssr = '{$_POST['pa_ssr']}',
                pa_ssr2 = '{$_POST['pa_ssr2']}',
                pa_day = '{$_POST['pa_day']}', 
                pa_add_use = '{$_POST['pa_add_use']}', 
                pa_item_use = '{$_POST['pa_item_use']}', 
                pa_level = '{$_POST['pa_level']}', 
                pa_point_use = '{$_POST['pa_point_use']}', 
                pa_datetime = '" . G5_TIME_YMDHIS . "'";
    sql_query($sql);
}

update_rewrite_rules();

goto_url('./partner_form.php', false);
?>
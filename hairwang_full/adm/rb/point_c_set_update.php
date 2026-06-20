<?php
$sub_menu = '000690';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");

check_admin_token();

// 컬럼이 있는지 검사한다.
$cnt = sql_fetch("SELECT COUNT(*) as cnt FROM rb_point_c_set");

if($_POST['pnt_name'] == "") {
    $pnt_name = "예치금";
}

if($_POST['pnt_name_st'] == "") {
    $pnt_name_st = "C";
}

if ($cnt['cnt'] > 0) {
    $sql = "UPDATE rb_point_c_set
            SET pnt_name = '{$pnt_name}', 
                pnt_name_st = '{$pnt_name_st}', 
                pnt_acc_use = '{$_POST['pnt_acc_use']}', 
                pnt_add_use = '{$_POST['pnt_add_use']}', 
                pnt_point_acc_min = '{$_POST['pnt_point_acc_min']}', 
                pnt_point_acc_max = '{$_POST['pnt_point_acc_max']}',
                pnt_point_add_min = '{$_POST['pnt_point_add_min']}',
                pnt_point_add_max = '{$_POST['pnt_point_add_max']}',
                pnt_vat = '{$_POST['pnt_vat']}',
                pnt_bk = '{$_POST['pnt_bk']}',
                pnt_pay = '{$_POST['pnt_pay']}',
                pnt_agree = '{$_POST['pnt_agree']}',
                pnt_agree2 = '{$_POST['pnt_agree2']}',
                pnt_point_acc_ssr_p = '{$_POST['pnt_point_acc_ssr_p']}',
                pnt_point_acc_ssr_w = '{$_POST['pnt_point_acc_ssr_w']}' ";
    sql_query($sql);
} else {
    $sql = "INSERT INTO rb_point_c_set
            SET pnt_name = '{$pnt_name}', 
                pnt_name_st = '{$pnt_name_st}', 
                pnt_acc_use = '{$_POST['pnt_acc_use']}', 
                pnt_add_use = '{$_POST['pnt_add_use']}', 
                pnt_point_acc_min = '{$_POST['pnt_point_acc_min']}', 
                pnt_point_acc_max = '{$_POST['pnt_point_acc_max']}',
                pnt_point_add_min = '{$_POST['pnt_point_add_min']}',
                pnt_point_add_max = '{$_POST['pnt_point_add_max']}',
                pnt_vat = '{$_POST['pnt_vat']}',
                pnt_bk = '{$_POST['pnt_bk']}',
                pnt_pay = '{$_POST['pnt_pay']}',
                pnt_agree = '{$_POST['pnt_agree']}',
                pnt_agree2 = '{$_POST['pnt_agree2']}',
                pnt_point_acc_ssr_p = '{$_POST['pnt_point_acc_ssr_p']}',
                pnt_point_acc_ssr_w = '{$_POST['pnt_point_acc_ssr_w']}' ";
    sql_query($sql);
}


update_rewrite_rules();

goto_url('./point_c_set.php', false);
?>
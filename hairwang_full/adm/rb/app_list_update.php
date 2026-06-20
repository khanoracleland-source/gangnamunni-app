<?php
$sub_menu = '000600';
include_once('./_common.php');

check_demo();

check_admin_token();

if ($_POST['act_button'] == "선택삭제") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    auth_check_menu($auth, $sub_menu, 'd');

    for ($i = 0; $i < $count_post_chk; $i++) {
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $itk_id = isset($_POST['tk_id'][$k]) ? (int) $_POST['tk_id'][$k] : 0;
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';

        // 삭제
        sql_query("DELETE FROM rb_app_token WHERE tk_id = '{$itk_id}'");
        
    }
} else if ($_POST['act_button'] == "전체삭제") {

        // 삭제
        sql_query(" DELETE FROM rb_app_token ");
        alert('전체 데이터가 삭제 되었습니다.');
}



goto_url("./app_form.php?sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");
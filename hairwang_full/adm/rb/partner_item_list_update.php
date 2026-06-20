<?php
$sub_menu = '000652';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");
check_admin_token();



if ($_POST['act_button'] == "선택반려") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $iit_id = isset($_POST['it_id'][$k]) ? $_POST['it_id'][$k] : '';
        $iit_name = isset($_POST['it_name'][$k]) ? $_POST['it_name'][$k] : '';
        $iit_partner = isset($_POST['it_partner'][$k]) ? $_POST['it_partner'][$k] : '';
        
        $sql = "UPDATE {$g5['g5_shop_item_table']} 
            SET it_use = '0' 
                where it_id = '{$iit_id}' ";
        sql_query($sql);
        
        if(isset($iit_partner) && $iit_partner) {
            memo_auto_send($iit_name.' 상품이 등록 반려 되었습니다.', '', $iit_partner, "system-msg");
        }
        
    }
    
} else if ($_POST['act_button'] == "선택승인") {

    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $iit_id = isset($_POST['it_id'][$k]) ? $_POST['it_id'][$k] : '';
        $iit_name = isset($_POST['it_name'][$k]) ? $_POST['it_name'][$k] : '';
        $iit_partner = isset($_POST['it_partner'][$k]) ? $_POST['it_partner'][$k] : '';
        
        $sql = "UPDATE {$g5['g5_shop_item_table']} 
            SET it_use = '1' 
                where it_id = '{$iit_id}' ";
        sql_query($sql);
        
        if(isset($iit_partner) && $iit_partner) {
            memo_auto_send($iit_name.' 상품이 등록 승인 되었습니다.', '', $iit_partner, "system-msg");
        }
        
    }
    
} else if ($_POST['act_button'] == "선택수정") {

    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $iit_id = isset($_POST['it_id'][$k]) ? $_POST['it_id'][$k] : '';
        $iit_name = isset($_POST['it_name'][$k]) ? $_POST['it_name'][$k] : '';
        $iit_partner = isset($_POST['it_partner'][$k]) ? $_POST['it_partner'][$k] : '';
        $iit_ssr = isset($_POST['it_ssr'][$k]) ? $_POST['it_ssr'][$k] : '';
        $iit_ssr_w = isset($_POST['it_ssr_w'][$k]) ? $_POST['it_ssr_w'][$k] : '';

        
        $sql = "UPDATE {$g5['g5_shop_item_table']} SET it_ssr = '{$iit_ssr}', it_ssr_w = '{$iit_ssr_w}' where it_id = '{$iit_id}' ";
        sql_query($sql);
        
    }
    
}



goto_url("./partner_item_list.php?sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");
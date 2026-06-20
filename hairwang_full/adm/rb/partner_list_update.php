<?php
$sub_menu = '000650';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");
check_admin_token();

$pa_level = isset($pa['pa_level']) ? $pa['pa_level'] : '';


if ($_POST['act_button'] == "선택반려") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';
        
        $sql = "UPDATE {$g5['member_table']} 
            SET mb_partner = '0',
                mb_level = '{$config['cf_register_level']}',
                mb_partner_add_time = '0000-00-00 00:00:00' 
                where mb_id = '{$imb_id}' ";
        sql_query($sql);
        
        if(isset($imb_id) && $imb_id) {
            memo_auto_send('입점 신청이 반려 되었습니다.', '', $imb_id, "system-msg");
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
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';

        
        if($pa_level > 0) {
            $sql = "UPDATE {$g5['member_table']} SET mb_partner = '2', mb_level = '{$pa_level}' where mb_id = '{$imb_id}' ";
            sql_query($sql);
        } else {
            $sql = "UPDATE {$g5['member_table']} SET mb_partner = '2' where mb_id = '{$imb_id}' ";
            sql_query($sql);
        }
        
        if(isset($imb_id) && $imb_id) {
            memo_auto_send('입점 신청이 승인 되었습니다.', '', $imb_id, "system-msg");
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
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';
        $imb_ssr = isset($_POST['mb_ssr'][$k]) ? $_POST['mb_ssr'][$k] : '';

        
        $sql = "UPDATE {$g5['member_table']} SET mb_ssr = '{$imb_ssr}' where mb_id = '{$imb_id}' ";
        sql_query($sql);

        
    }
    
}



goto_url("./partner_list.php?sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");
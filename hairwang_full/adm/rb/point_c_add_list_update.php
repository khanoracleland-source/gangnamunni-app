<?php
$sub_menu = "000700";
require_once './_common.php';

check_demo();
auth_check_menu($auth, $sub_menu, 'w');
check_admin_token();


if ($_POST['act_button'] == "선택수정") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }
    

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $p_id         = isset($_POST['p_id'][$k]) ? $_POST['p_id'][$k] : '';
        $p_status       = isset($_POST['p_status'][$k]) ? strip_tags(clean_xss_attributes($_POST['p_status'][$k])) : '';
        $p_mb_id       = isset($_POST['p_mb_id'][$k]) ? strip_tags(clean_xss_attributes($_POST['p_mb_id'][$k])) : '';
        $p_point       = isset($_POST['p_point'][$k]) ? strip_tags(clean_xss_attributes($_POST['p_point'][$k])) : '0';
        $p_use       = isset($_POST['p_use'][$k]) ? strip_tags(clean_xss_attributes($_POST['p_use'][$k])) : '0'; //중복지급 방지
        
        
        $sql = " update rb_point_c_add
                    set p_status     = '" . sql_real_escape_string($p_status) . "',
                        p_y_time     = '" . G5_TIME_YMDHIS . "' 
                  where p_id         = '{$p_id}' ";
        sql_query($sql);
        
        if($p_status == "완료" && $p_use == 0 && $p_point > 0 && $p_mb_id) {
            
            $sql = " update rb_point_c_add set p_use = '1' where p_id = '{$p_id}' ";
            sql_query($sql);
            
            $d_times = date("YmdHis");
            insert_point_c($p_mb_id, $p_point, $pnt_c_name.' 충전', '@buy', 'acc_'.$d_times, G5_TIME_YMDHIS);
            
            memo_auto_send(number_format($p_point).''.$pnt_c_name_st.' 충전이 완료 되었습니다.', '', $p_mb_id, "system-msg");
            
        } else if($p_status == "취소" && $p_use == 0 && $p_mb_id) {
            
            $sql = " update rb_point_c_add set p_use = '1' where p_id = '{$p_id}' ";
            sql_query($sql);
            
            memo_auto_send($pnt_c_name.' 충전 신청이 '.$p_status.' 처리 되었습니다.', '', $p_mb_id, "system-msg");

        }

    }
    
    alert('변경 처리 되었습니다.');
    
} else if ($_POST['act_button'] == "선택삭제") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $p_id         = isset($_POST['p_id'][$k]) ? $_POST['p_id'][$k] : '';

        sql_query(" delete from rb_point_c_add where p_id = '$p_id' ");
    }
    
    alert('삭제처리 되었습니다.');
    
}

goto_url('./point_c_add_list.php?' . $qstr);

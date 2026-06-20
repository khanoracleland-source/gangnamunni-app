<?php
$sub_menu = '000653';
include_once('./_common.php');

check_demo();
auth_check_menu($auth, $sub_menu, "w");
check_admin_token();

if ($_POST['act_button'] == "선택취소") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $ict_id = isset($_POST['ct_id'][$k]) ? $_POST['ct_id'][$k] : '';
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';
        $iod_id = isset($_POST['od_id'][$k]) ? $_POST['od_id'][$k] : '';
        $ict_partner = isset($_POST['ct_partner'][$k]) ? $_POST['ct_partner'][$k] : '';
        $ict_option = isset($_POST['ct_option'][$k]) ? $_POST['ct_option'][$k] : '';
        $iit_name = isset($_POST['it_name'][$k]) ? $_POST['it_name'][$k] : '';
        
            
            $sql = "UPDATE {$g5['g5_shop_cart_table']} SET ct_js = '0', ct_total_price = '', ct_js_ssr = '', ct_js_cost = '', ct_js_price = '', ct_js_price_old = '', ct_js_use = '', ct_js_type = '', ct_js_time = '0000-00-00 00:00:00' where ct_id = '{$ict_id}' and ct_js = '1' ";
        
            sql_query($sql);
            
            if(isset($ict_partner) && $ict_partner) {
                $memos = "정산된 건에 대한 정산취소 처리가 있습니다. 관리자에게 문의해주세요.";
                memo_auto_send($memos, '', $ict_partner, "system-msg");
            }


 
    }

    
} else if ($_POST['act_button'] == "선택정산") {

    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    for ($i = 0; $i < $count_post_chk; $i++) {
        
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $ict_id = isset($_POST['ct_id'][$k]) ? $_POST['ct_id'][$k] : '';
        $imb_id = isset($_POST['mb_id'][$k]) ? $_POST['mb_id'][$k] : '';
        $iod_id = isset($_POST['od_id'][$k]) ? $_POST['od_id'][$k] : '';
        $ict_partner = isset($_POST['ct_partner'][$k]) ? $_POST['ct_partner'][$k] : '';
        $ict_option = isset($_POST['ct_option'][$k]) ? $_POST['ct_option'][$k] : '';
        $iit_name = isset($_POST['it_name'][$k]) ? $_POST['it_name'][$k] : '';
        
        $ict_total_price = isset($_POST['ct_total_price'][$k]) ? (int) $_POST['ct_total_price'][$k] : 0; //판매금액(수량포함)
        $ict_js_ssr = isset($_POST['ct_js_ssr'][$k]) ? (int) $_POST['ct_js_ssr'][$k] : 0; //수수료(설정적용)
        $ict_js_cost = isset($_POST['ct_js_cost'][$k]) ? (int) $_POST['ct_js_cost'][$k] : 0; //배송비합계(조건적용)
        $ict_js_price = isset($_POST['ct_js_price'][$k]) ? (int) $_POST['ct_js_price'][$k] : 0; //정산합계
        $ict_js_price_old = isset($_POST['ct_js_price_old'][$k]) ? (int) $_POST['ct_js_price_old'][$k] : 0; //정산소계
        $ict_js_use = isset($_POST['ct_js_use'][$k]) ? $_POST['ct_js_use'][$k] : ''; //정산방식
        $ict_js_type = isset($_POST['ct_js_type'][$k]) ? $_POST['ct_js_type'][$k] : ''; //정산유형
        

            $sql = "UPDATE {$g5['g5_shop_cart_table']} SET ct_js = '1', ct_total_price = '{$ict_total_price}', ct_js_ssr = '{$ict_js_ssr}', ct_js_cost = '{$ict_js_cost}', ct_js_price = '{$ict_js_price}', ct_js_price_old = '{$ict_js_price_old}', ct_js_use = '{$ict_js_use}', ct_js_type = '{$ict_js_type}', ct_js_time = '".G5_TIME_YMDHIS."' where ct_id = '{$ict_id}' and ct_js = '0' ";
            sql_query($sql);

            if(isset($ict_partner) && $ict_partner) {
                
                if(isset($pa['pa_point_use']) && $pa['pa_point_use'] == 1) { //예치금정산일 경우
                    insert_point_c($ict_partner, $ict_js_price, '판매대금 정산', '@items', $ict_partner, $iod_id . '-' . $ict_id, $expire); //중복지급을 막기위한 장치
                }

                $memos = "상품 판매에 대한 정산이 처리 되었습니다.
                
주문번호 : ".$iod_id."
상품명 : ".$iit_name." [".$ict_option."]

판매금액 : ".number_format($ict_total_price)."원
수수료 : ".number_format($ict_js_ssr)."원 (".$ict_js_type.")
배송비 : ".number_format($ict_js_cost)."원
정산금액 : ".number_format($ict_js_price)."원

노고에 감사드립니다.";
                memo_auto_send($memos, '', $ict_partner, "system-msg");
                
            }


 
    }
   
}


goto_url("./partner_js_list.php?sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");
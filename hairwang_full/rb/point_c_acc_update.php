<?php
include_once('./_common.php');

if ($is_guest)
    alert('회원만 이용하실 수 있습니다.');

$p_point = isset($_POST['p_point']) ? intval($_POST['p_point']) : '0';
$p_ssr = isset($_POST['p_ssr']) ? intval($_POST['p_ssr']) : '0';
$p_price = isset($_POST['p_price']) ? intval($_POST['p_price']) : '0';
$p_bank = isset($_POST['p_bank']) ? strip_tags(clean_xss_attributes($_POST['p_bank'])) : '';
$p_agree = isset($_POST['p_agree']) ? strip_tags(clean_xss_attributes($_POST['p_agree'])) : '';
$cn = isset($_POST['cn']) ? strip_tags(clean_xss_attributes($_POST['cn'])) : '';
$d_times = date("YmdHis");

if($cn == "취소") { 
    
    //신청내역이 있는지 한번 더 확인한다.
    $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_acc where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' ");
    $data = sql_fetch(" select * from rb_point_c_acc where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' limit 1 ");
    
    if($row['cnt'] > 0) {
        insert_point_c($member['mb_id'], $data['p_point'], '출금신청 취소', '@acc', 'acc_'.$d_times, G5_TIME_YMDHIS);
        memo_auto_send('출금 신청이 취소 되어 신청하신 '.$pnt_c_name.'이(가) 지급 되었습니다.', '', $member['mb_id'], "system-msg");
        sql_query(" delete from rb_point_c_acc where p_id = '{$data['p_id']}' ");
        alert('출금신청이 취소 되었습니다.');
    } else { 
        alert('출금신청 하신 내역이 없습니다. 고객지원으로 문의해주세요.');
    }
    
} else { 
    if($p_point && $p_bank && $p_agree) { 

        $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_acc where p_mb_id = '{$member['mb_id']}' and p_status = '접수' ");

        if($row['cnt'] > 0) {
            alert('이미 접수된 출금 신청건이 있습니다.');
        } else { 

            $sql = " insert into rb_point_c_acc ( p_point, p_ssr, p_price, p_bank, p_agree, p_mb_id, p_status, p_time ) values ( '$p_point', '$p_ssr', '$p_price', '".sql_escape_string($p_bank)."', '".sql_escape_string($p_agree)."', '{$member['mb_id']}', '접수', '".G5_TIME_YMDHIS."' ) ";
            sql_query($sql);
            
            $sql = " update {$g5['member_table']} SET mb_bank = '".sql_escape_string($p_bank)."' WHERE mb_id = '{$member['mb_id']}' ";
            sql_query($sql);

            //포인트 선차감
            insert_point_c($member['mb_id'], (int)$p_point * (-1), $pnt_c_name.' 출금신청', '@acc', 'acc_'.$d_times, G5_TIME_YMDHIS);

            //관리자에게 쪽지발송
            memo_auto_send( $pnt_c_name.' 출금 신청이 접수 되었습니다.', '', $config['cf_admin'], "system-msg");

            //작성자에게 쪽지발송
            $msg_cont = $pnt_c_name.' 출금 신청이 접수 되었습니다.
신청하신 금액 : '.number_format($p_point).$pnt_c_name_st.'
입금예정 금액 : '.number_format($p_price).'원';
            
            memo_auto_send( $msg_cont, '', $member['mb_id'], "system-msg");

            // SMS
            /*
            include_once(G5_LIB_PATH.'/icode.sms.lib.php');
            $sHp = $sms5['cf_phone']; // 발송번호
            $rHp = "01064663355"; // 수신번호
            $msg = $me_name." 님의 구매신청이 접수 되었습니다.";   // 발송내용
            smsSend($sHp, $rHp, $msg);
            */

            alert('출금 신청이 완료 되었습니다.');

        }

    } else { 
        alert('신청 정보가 누락되었습니다.');
    }
}
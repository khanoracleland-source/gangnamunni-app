<?php
include_once('./_common.php');

if ($is_guest)
    alert('회원만 이용하실 수 있습니다.');

$p_point = isset($_POST['p_point']) ? intval($_POST['p_point']) : '0';
$p_pay = isset($_POST['p_pay']) ? strip_tags(clean_xss_attributes($_POST['p_pay'])) : '';
$p_bk_name = isset($_POST['p_bk_name']) ? strip_tags(clean_xss_attributes($_POST['p_bk_name'])) : '';
$p_agree = isset($_POST['p_agree']) ? strip_tags(clean_xss_attributes($_POST['p_agree'])) : '';
$p_price = isset($_POST['p_price']) ? intval($_POST['p_price']) : '0';

$cn = isset($_POST['cn']) ? $_POST['cn'] : '';
$d_times = date("YmdHis");


if($cn == "취소") { 
    
    //신청내역이 있는지 한번 더 확인한다.
    $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_add where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' ");
    $data = sql_fetch(" select * from rb_point_c_add where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' limit 1 ");
    
    if($row['cnt'] > 0) {
        memo_auto_send('충전신청이 취소 되었습니다.', '', $member['mb_id'], "system-msg");
        sql_query(" delete from rb_point_c_add where p_id = '{$data['p_id']}' ");
        alert('충전신청이 취소 되었습니다.');
    } else { 
        alert('충전신청 하신 내역이 없습니다. 고객지원으로 문의해주세요.');
    }
    
} else {

    if($p_point && $p_pay && $p_bk_name && $p_agree && $p_price) { 

        $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_add where p_mb_id = '{$member['mb_id']}' and p_status = '접수' ");

        if($row['cnt'] > 0) {
            alert('이미 접수된 충전 신청건이 있습니다.');
        } else { 

            $sql = " insert into rb_point_c_add ( p_point, p_pay, p_bk_name, p_agree, p_price, p_mb_id, p_status, p_time ) values ( '$p_point', '".sql_escape_string($p_pay)."', '".sql_escape_string($p_bk_name)."', '".sql_escape_string($p_agree)."', '$p_price', '{$member['mb_id']}', '접수', '".G5_TIME_YMDHIS."' ) ";
            sql_query($sql);

            //관리자에게 쪽지발송
            memo_auto_send( $pnt_c_name.' 충전 신청이 접수 되었습니다.', '', $config['cf_admin'], "system-msg");

            //작성자에게 쪽지발송
            $bk = $pnt_c['pnt_bk'];
            $msg_cont = number_format($p_point).' '.$pnt_c_name.' 충전 신청이 접수 되었습니다.
입금계좌 : '.$bk.'
입금하실 금액 : '.number_format($p_price).'원';

            memo_auto_send( $msg_cont, '', $member['mb_id'], "system-msg");

            // SMS
            /*
            include_once(G5_LIB_PATH.'/icode.sms.lib.php');
            $sHp = $sms5['cf_phone']; // 발송번호
            $rHp = "01064663355"; // 수신번호
            $msg = $me_name." 님의 구매신청이 접수 되었습니다.";   // 발송내용
            smsSend($sHp, $rHp, $msg);
            */

            alert('충전 신청이 완료 되었습니다.');

        }

    } else { 
        alert('결제 정보가 누락되었습니다.');
    }
    
}
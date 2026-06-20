<?php
$sub_menu = "000720";
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'w');

check_admin_token();

$mb_id = isset($_POST['mb_id']) ? strip_tags(clean_xss_attributes($_POST['mb_id'])) : '';
$po_point = isset($_POST['po_point']) ? (int)strip_tags(clean_xss_attributes($_POST['po_point'])) : 0;
$po_content = isset($_POST['po_content']) ? strip_tags(clean_xss_attributes($_POST['po_content'])) : '';
$expire = isset($_POST['po_expire_term']) ? preg_replace('/[^0-9]/', '', $_POST['po_expire_term']) : '';

$mb = get_member($mb_id);

if (!$mb['mb_id']) {
    alert('존재하는 회원아이디가 아닙니다.', './point_c_list.php?' . $qstr);
}

if (($po_point < 0) && ($po_point * (-1) > $mb['rb_point'])) {
    alert($pnt_c_name.'을(를) 깎는 경우 현재 '.$pnt_c_name.'보다 작으면 안됩니다.', './point_c_list.php?' . $qstr);
}


insert_point_c($mb_id, $po_point, $po_content, '@passive', $mb_id, $member['mb_id'] . '-' . uniqid(''), $expire);

//쪽지발송
if($po_point < 0) {
    memo_auto_send(number_format($po_point).' '.$pnt_c_name.'이(가) 차감 되었습니다.', '', $mb_id, "system-msg");
} else { 
    memo_auto_send(number_format($po_point).' '.$pnt_c_name.'을(를) 지급 받았습니다.', '', $mb_id, "system-msg");
}



goto_url('./point_c_list.php?' . $qstr);

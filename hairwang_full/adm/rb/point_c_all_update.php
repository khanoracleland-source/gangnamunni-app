<?php
$sub_menu = "000720";
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'w');

check_admin_token();


$po_point = isset($_POST['po_point']) ? (int)strip_tags(clean_xss_attributes($_POST['po_point'])) : 0;
$po_content = isset($_POST['po_content']) ? strip_tags(clean_xss_attributes($_POST['po_content'])) : '';
$expire = isset($_POST['po_expire_term']) ? preg_replace('/[^0-9]/', '', $_POST['po_expire_term']) : '';

$sql = " select mb_id from {$g5['member_table']} where mb_id <> '{$config['cf_admin']}' and mb_leave_date = '' and mb_intercept_date = '' "; 
$result = sql_query($sql); 

while($row = sql_fetch_array($result)) { 
    $mb_id = $row['mb_id']; 
    insert_point_c($mb_id, $po_point, $po_content, '@event', $mb_id, $member['mb_id'] . '-' . uniqid(''), $expire);
} 

goto_url('./point_c_list.php?' . $qstr);
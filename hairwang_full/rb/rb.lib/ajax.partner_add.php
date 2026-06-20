<?php
include_once('../../common.php');

$mb_partner = isset($_POST['mb_partner']) ? $_POST['mb_partner'] : '';
$mb_id = isset($_POST['mb_id']) ? $_POST['mb_id'] : '';
$pa_level = isset($pa['pa_level']) ? $pa['pa_level'] : '';

    if ($mb_id && $mb_partner == 2) { //승인

        if($pa_level > 0) {
            $sql = " update {$g5['member_table']} set mb_partner = '{$mb_partner}', mb_level = '{$pa_level}' where mb_id = '{$mb_id}' ";
            sql_query($sql);
        } else { 
            $sql = " update {$g5['member_table']} set mb_partner = '{$mb_partner}' where mb_id = '{$mb_id}' ";
            sql_query($sql);
        }

        memo_auto_send('입점 신청이 승인 되었습니다.', '', $mb_id, "system-msg");

    } else { //반려
        $sql = " update {$g5['member_table']} set mb_partner = '{$mb_partner}', mb_level = '{$config['cf_register_level']}', mb_partner_add_time = '0000-00-00 00:00:00' where mb_id = '{$mb_id}' ";
        sql_query($sql);

        memo_auto_send('입점 신청이 반려 되었습니다.', '', $mb_id, "system-msg");
    }
?>
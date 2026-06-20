<?php
include_once('../../common.php');

$it_id = isset($_POST['it_id']) ? $_POST['it_id'] : '';
$it_use = isset($_POST['it_use']) ? $_POST['it_use'] : '';

$row = sql_fetch (" select it_partner, it_name, COUNT(*) as cnt from {$g5['g5_shop_item_table']} where it_id = '{$it_id}' ");

if(isset($row['cnt']) && $row['cnt'] > 0) {
    
    if ($it_id && $it_use == 1) { //승인

        $sql = "UPDATE {$g5['g5_shop_item_table']} 
            SET it_use = '{$it_use}' 
                where it_id = '{$it_id}' ";
        sql_query($sql);
        
        if(isset($row['it_partner']) && $row['it_partner']) {
            memo_auto_send($row['it_name'].' 상품이 등록 승인 되었습니다.', '', $row['it_partner'], "system-msg");
        }

    } else { //반려
        
        $sql = "UPDATE {$g5['g5_shop_item_table']} 
            SET it_use = '{$it_use}' 
                where it_id = '{$it_id}' ";
        sql_query($sql);
        
        if(isset($row['it_partner']) && $row['it_partner']) {
            memo_auto_send($row['it_name'].' 상품이 등록 반려 되었습니다.', '', $row['it_partner'], "system-msg");
        }
    }
    
}

    
?>
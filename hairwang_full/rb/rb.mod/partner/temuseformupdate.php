<?php
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$posts = array();
$check_keys = array('is_subject', 'is_content', 'is_confirm', 'is_reply_subject', 'is_reply_content', 'is_id');

foreach($check_keys as $key){

    if( in_array($key, array('is_content', 'is_reply_content')) ){
        $posts[$key] = isset($_POST[$key]) ? $_POST[$key] : '';
    } else {
        $posts[$key] = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : '';
    }
}

if ($w == "u")
{
    $sql = "update {$g5['g5_shop_item_use_table']}
               set is_confirm = '".$posts['is_confirm']."',
                   is_reply_subject = '".$posts['is_reply_subject']."',
                   is_reply_content = '".$posts['is_reply_content']."',
                   is_reply_name = '".$member['mb_nick']."'
             where is_id = '".$posts['is_id']."'";
    sql_query($sql);
    run_event('shop_admin_item_use_updated', $posts['is_id']);

    if( isset($_POST['it_id']) ) {
        update_use_cnt($_POST['it_id']);
        update_use_avg($_POST['it_id']);
    }
    
    if ($posts['is_confirm'] == 1) {
        
        if($_POST['mb_id']) {

            $od_al = "[".$_POST['it_name']."] 상품 구매후기에 답변이 등록 되었습니다."; 
            memo_auto_send($od_al, shop_item_url($_POST['it_id']), $_POST['mb_id'], "system-msg");

        }
        
    }

    goto_url("../../partner.php?w=$w&amp;is_id=$is_id&amp;sca=$sca&amp;main=$main&amp;sub=uselist");
}
else
{
    alert();
}
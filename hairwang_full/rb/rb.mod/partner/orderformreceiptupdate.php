<?php
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$main = isset($_POST['main']) ? $_POST['main'] : '';
$sub = isset($_POST['sub']) ? $_POST['sub'] : '';
include_once(G5_LIB_PATH.'/mailer.lib.php');

$od_id = isset($_POST['od_id']) ? safe_replace_regex($_POST['od_id'], 'od_id') : '';
$search = isset($_REQUEST['search']) ? get_search_string($_REQUEST['search']) : '';
$sort1 = isset($_REQUEST['sort1']) ? clean_xss_tags($_REQUEST['sort1'], 1, 1) : '';
$sort2 = isset($_REQUEST['sort2']) ? clean_xss_tags($_REQUEST['sort2'], 1, 1) : '';
$sel_field = isset($_REQUEST['sel_field']) ? clean_xss_tags($_REQUEST['sel_field'], 1, 1) : '';

$check_keys = array(
'od_deposit_name',
'od_bank_account',
'od_receipt_time',
'od_receipt_price',
'od_receipt_point',
'od_refund_price',
'od_delivery_company',
'od_invoice',
'od_invoice_time',
'od_send_cost',
'od_send_cost2',
'od_tno',
'od_escrow',
'od_send_mail'
);

$posts = array();

foreach($check_keys as $key){
    $posts[$key] = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : '';
}

$od_send_mail = $posts['od_send_mail'];

$sql = " select * from {$g5['g5_shop_order_table']} where od_id = '$od_id' ";
$od  = sql_fetch($sql);

if(! (isset($od['od_id']) && $od['od_id'])) {
    alert('주문자료가 존재하지 않습니다.');
}

if(isset($od['od_status']) && $od['od_status'] == "주문" || isset($od['od_status']) && $od['od_status'] == "완료") {
    alert('결제이전 및 완료 처리된 주문건의 정보는 변경하실 수 없습니다.');
}

// 배송정보 반영
/*
$sql = " update {$g5['g5_shop_order_table']}
            set od_delivery_company= '{$posts['od_delivery_company']}',
                od_invoice         = '{$posts['od_invoice']}',
                od_invoice_time    = '{$posts['od_invoice_time']}'
            where od_id = '$od_id' ";
sql_query($sql);
*/

if($is_admin == 'super') {
    
    $sql = " update {$g5['g5_shop_cart_table']}
                set ct_delivery_company= '{$posts['od_delivery_company']}',
                    ct_invoice         = '{$posts['od_invoice']}',
                    ct_invoice_time    = '{$posts['od_invoice_time']}'
                where od_id = '$od_id' ";
    sql_query($sql);
    
} else { 

    $sql = " update {$g5['g5_shop_cart_table']}
                set ct_delivery_company= '{$posts['od_delivery_company']}',
                    ct_invoice         = '{$posts['od_invoice']}',
                    ct_invoice_time    = '{$posts['od_invoice_time']}'
                where od_id = '$od_id' and ct_partner = '{$member['mb_id']}' ";
    sql_query($sql);
}

// 주문정보
$info = get_order_info($od_id);
if(!$info)
    alert('주문자료가 존재하지 않습니다.');

$od_status = $od['od_status'];
$cart_status = false;


// 장바구니 상태 변경
if($cart_status) {
    
    if($is_admin == 'super') {
        $sql = " update {$g5['g5_shop_cart_table']}
                set ct_status = '$od_status'
                where od_id = '$od_id' and ct_partner = '{$member['mb_id']}' ";
    } else { 
        $sql = " update {$g5['g5_shop_cart_table']}
                set ct_status = '$od_status'
                where od_id = '$od_id' ";
    }
    
    

    switch($od_status) {
        case '입금':
            $sql .= " and ct_status = '주문' ";
            break;
        case '배송':
            $sql .= " and ct_status IN ('".implode("', '", $order_status)."') ";
            break;
        default:
            ;
    }

    sql_query($sql);
}


// 배송때 재고반영
if($info['od_misu'] == 0 && $od_status == '배송') {
    
    if($is_admin == 'super') {
        $sql = " select * from {$g5['g5_shop_cart_table']} where od_id = '$od_id' ";
    } else { 
        $sql = " select * from {$g5['g5_shop_cart_table']} where od_id = '$od_id' and ct_partner = '{$member['mb_id']}' ";
    }
    
    $result = sql_query($sql);

    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        // 재고를 사용하지 않았다면
        $stock_use = $row['ct_stock_use'];

        if(!$row['ct_stock_use'])
        {
            // 재고에서 뺀다.
            subtract_io_stock($row['it_id'], $row['ct_qty'], $row['io_id'], $row['io_type']);
            $stock_use = 1;

            $sql = " update {$g5['g5_shop_cart_table']} set ct_stock_use  = '$stock_use' where ct_id = '{$row['ct_id']}' ";
            sql_query($sql);
        }
    }

    unset($sql);
    unset($result);
    unset($row);
}


// 메일발송
//define("_ORDERMAIL_", true);
//include "./ordermail.inc.php";


// SMS 문자전송
define("_ORDERSMS_", true);
include G5_PATH."/adm/shop_admin/ordersms.inc.php";


// 에스크로 배송처리
if($posts['od_tno'] && $posts['od_escrow'] == 1)
{
    $escrow_tno  = $posts['od_tno'];
    $escrow_corp = $posts['od_delivery_company'];
    $escrow_numb = $posts['od_invoice'];

    include(G5_SHOP_PATH.'/'.$od['od_pg'].'/escrow.register.php');
}


$qstr = "main=$main&amp;sub=$sub&amp;sort1=$sort1&amp;sort2=$sort2&amp;sel_field=$sel_field&amp;search=$search&amp;page=$page";
goto_url("../../partner.php?od_id=$od_id&amp;$qstr");
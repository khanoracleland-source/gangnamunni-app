<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

$cart_title3 = '주문번호';
$cart_title4 = '배송완료';

$g5['title'] = "주문 내역 수정";

$fr_date = isset($_REQUEST['fr_date']) ? preg_replace('/[^0-9 :\-]/i', '', $_REQUEST['fr_date']) : '';
$to_date = isset($_REQUEST['to_date']) ? preg_replace('/[^0-9 :\-]/i', '', $_REQUEST['to_date']) : '';
$od_status = isset($_REQUEST['od_status']) ? clean_xss_tags($_REQUEST['od_status'], 1, 1) : '';
$od_settle_case = isset($_REQUEST['od_settle_case']) ? clean_xss_tags($_REQUEST['od_settle_case'], 1, 1) : '';
$od_misu = isset($_REQUEST['od_misu']) ? clean_xss_tags($_REQUEST['od_misu'], 1, 1) : '';
$od_cancel_price = isset($_REQUEST['od_cancel_price']) ? clean_xss_tags($_REQUEST['od_cancel_price'], 1, 1) : '';
$od_refund_price = isset($_REQUEST['od_refund_price']) ? clean_xss_tags($_REQUEST['od_refund_price'], 1, 1) : '';
$od_receipt_point = isset($_REQUEST['od_receipt_point']) ? clean_xss_tags($_REQUEST['od_receipt_point'], 1, 1) : '';
$od_coupon = isset($_REQUEST['od_coupon']) ? clean_xss_tags($_REQUEST['od_coupon'], 1, 1) : '';
$od_id = isset($_REQUEST['od_id']) ? safe_replace_regex($_REQUEST['od_id'], 'od_id') : '';
$od_escrow = isset($_REQUEST['od_escrow']) ? clean_xss_tags($_REQUEST['od_escrow'], 1, 1) : ''; 

$sort1 = isset($_REQUEST['sort1']) ? clean_xss_tags($_REQUEST['sort1'], 1, 1) : '';
$sort2 = isset($_REQUEST['sort2']) ? clean_xss_tags($_REQUEST['sort2'], 1, 1) : '';
$sel_field = isset($_REQUEST['sel_field']) ? clean_xss_tags($_REQUEST['sel_field'], 1, 1) : '';
$search = isset($_REQUEST['search']) ? get_search_string($_REQUEST['search']) : '';


//------------------------------------------------------------------------------
// 주문서 정보
//------------------------------------------------------------------------------
$sql = " select * from {$g5['g5_shop_order_table']} where od_id = '$od_id' ";


$od = sql_fetch($sql);
if (! (isset($od['od_id']) && $od['od_id'])) {
    alert("해당 주문번호로 주문서가 존재하지 않습니다.");
}

$od['mb_id'] = $od['mb_id'] ? $od['mb_id'] : "비회원";
//------------------------------------------------------------------------------


$pg_anchor = '<ul class="anchor">
<li><a href="#anc_sodr_list">주문상품 목록</a></li>
<li><a href="#anc_sodr_pay">주문결제 내역</a></li>
<li><a href="#anc_sodr_chk">결제상세정보 확인</a></li>
<li><a href="#anc_sodr_paymo">결제상세정보 수정</a></li>
<li><a href="#anc_sodr_memo">상점메모</a></li>
<li><a href="#anc_sodr_orderer">주문하신 분</a></li>
<li><a href="#anc_sodr_taker">받으시는 분</a></li>
</ul>';

$html_receipt_chk = '<input type="checkbox" id="od_receipt_chk" value="'.$od['od_misu'].'" onclick="chk_receipt_price()">
<label for="od_receipt_chk">결제금액 입력</label><br>';

$qstr1 = "od_status=".urlencode($od_status)."&amp;od_settle_case=".urlencode($od_settle_case)."&amp;od_misu=$od_misu&amp;od_cancel_price=$od_cancel_price&amp;od_refund_price=$od_refund_price&amp;od_receipt_point=$od_receipt_point&amp;od_coupon=$od_coupon&amp;fr_date=$fr_date&amp;to_date=$to_date&amp;sel_field=$sel_field&amp;search=$search&amp;save_search=$search";
if($default['de_escrow_use'])
    $qstr1 .= "&amp;od_escrow=$od_escrow";
$qstr = "$qstr1&amp;main=$main&amp;sub=orderlist&amp;sort1=$sort1&amp;sort2=$sort2&amp;page=$page";

// 상품목록
$sql = " select it_id, it_name, cp_price, ct_notax, ct_send_cost, it_sc_type from {$g5['g5_shop_cart_table']} where od_id = '{$od['od_id']}' ";
if($is_admin == 'super') {
    $sql .= " group by it_id order by ct_id";
} else { 
    $sql .= " and ct_partner = '{$member['mb_id']}' group by it_id order by ct_id";
}

$result = sql_query($sql);

// 주소 참고항목 필드추가
if(!isset($od['od_addr3'])) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_order_table']}`
                    ADD `od_addr3` varchar(255) NOT NULL DEFAULT '' AFTER `od_addr2`,
                    ADD `od_b_addr3` varchar(255) NOT NULL DEFAULT '' AFTER `od_b_addr2` ", true);
}

// 배송목록에 참고항목 필드추가
if(!sql_query(" select ad_addr3 from {$g5['g5_shop_order_address_table']} limit 1", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_order_address_table']}`
                    ADD `ad_addr3` varchar(255) NOT NULL DEFAULT '' AFTER `ad_addr2` ", true);
}

// 결제 PG 필드 추가
if(!sql_query(" select od_pg from {$g5['g5_shop_order_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_order_table']}`
                    ADD `od_pg` varchar(255) NOT NULL DEFAULT '' AFTER `od_mobile`,
                    ADD `od_casseqno` varchar(255) NOT NULL DEFAULT '' AFTER `od_escrow` ", true);

    // 주문 결제 PG kcp로 설정
    sql_query(" update {$g5['g5_shop_order_table']} set od_pg = 'kcp' ");
}

// LG 현금영수증 JS
if($od['od_pg'] == 'lg') {
    if($default['de_card_test']) {
    echo '<script language="JavaScript" src="'.SHOP_TOSSPAYMENTS_CASHRECEIPT_TEST_JS.'"></script>'.PHP_EOL;
    } else {
        echo '<script language="JavaScript" src="'.SHOP_TOSSPAYMENTS_CASHRECEIPT_REAL_JS.'"></script>'.PHP_EOL;
    }
}

$print_od_deposit_name = $od['od_deposit_name'];
// nicepay 로 주문하고 가상계좌인 경우
if ($od['od_pg'] === 'nicepay' && $od['od_settle_case'] === '가상계좌' && $od['od_deposit_name']){
    $print_od_deposit_name .= '_NICE';
}

// add_javascript('js 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_javascript(G5_POSTCODE_JS, 0);    //다음 주소 js
?>

<section id="anc_sodr_list">
    <h2 class="h2_frm">주문상품 목록</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p>
            주문일시 <strong><?php echo substr($od['od_time'],0,16); ?> (<?php echo get_yoil($od['od_time']); ?>)</strong>
        </p>
        <?php if ($default['de_hope_date_use']) { ?><p>희망배송일은 <?php echo $od['od_hope_date']; ?> (<?php echo get_yoil($od['od_hope_date']); ?>) 입니다.</p><?php } ?>
    </div>

    <form name="frmorderform" method="post" action="./rb.mod/partner/orderformcartupdate.php" onsubmit="return form_submit(this);">
    <input type="hidden" name="od_id" value="<?php echo $od_id; ?>">
    <input type="hidden" name="mb_id" value="<?php echo $od['mb_id']; ?>">
    <input type="hidden" name="od_email" value="<?php echo $od['od_email']; ?>">
    <input type="hidden" name="sort1" value="<?php echo $sort1; ?>">
    <input type="hidden" name="sort2" value="<?php echo $sort2; ?>">
    <input type="hidden" name="sel_field" value="<?php echo $sel_field; ?>">
    <input type="hidden" name="search" value="<?php echo $search; ?>">
    <input type="hidden" name="page" value="<?php echo $page;?>">
    <input type="hidden" name="pg_cancel" value="0">
    
    <input type="hidden" name="main" value="<?php echo $main; ?>">
    <input type="hidden" name="sub" value="<?php echo $sub; ?>">

    <div class="tbl_head01 tbl_wrap tbl_pa_list tbl_pa_list_order">
        <table>
        <caption>주문 상품 목록</caption>
        <thead>
        <tr>
            <th scope="col">이미지</th>
            <th scope="col">상품명</th>
            <th scope="col">
                
                <input type="checkbox" id="sit_select_all">
                <label for="sit_select_all" class=""></label>
            </th>
            <th scope="col">옵션항목</th>
            <th scope="col">상태</th>
            <th scope="col">수량</th>
            <th scope="col">판매가</th>
            <th scope="col">소계</th>
            <th scope="col">배송정보</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $chk_cnt = 0;

        $ct_price_total = 0;
        $opt_price_total = 0;
        $opt_ct_qty_total = 0;
        $cost_rb_total = 0;
            
        for($i=0; $row=sql_fetch_array($result); $i++) {
            // 상품이미지
            $image = get_it_image($row['it_id'], 50, 50);

            // 상품의 옵션정보
            $sql = " select ct_id, it_id, ct_price, ct_point, ct_qty, ct_option, ct_status, cp_price, ct_stock_use, ct_point_use, ct_send_cost, io_type, io_price, ct_invoice, ct_delivery_company, ct_invoice_time
                        from {$g5['g5_shop_cart_table']}
                        where od_id = '{$od['od_id']}'
                          and it_id = '{$row['it_id']}'
                        order by io_type asc, ct_id asc ";
            $res = sql_query($sql);
            $rowspan = sql_num_rows($res);

            // 합계금액 계산
            $sql = " select SUM(IF(io_type = 1, (io_price * ct_qty), ((ct_price + io_price) * ct_qty))) as price,
                            SUM(ct_qty) as qty
                        from {$g5['g5_shop_cart_table']}
                        where it_id = '{$row['it_id']}'
                          and od_id = '{$od['od_id']}' ";
            $sum = sql_fetch($sql);

            // 배송비
            switch($row['ct_send_cost'])
            {
                case 1:
                    $ct_send_cost = '착불';
                    break;
                case 2:
                    $ct_send_cost = '무료';
                    break;
                default:
                    $ct_send_cost = '선불';
                    break;
            }

            // 조건부무료
            if($row['it_sc_type'] == 2) {
                $sendcost = get_item_sendcost($row['it_id'], $sum['price'], $sum['qty'], $od['od_id']);

                if($sendcost == 0)
                    $ct_send_cost = '무료';
            }

            for($k=0; $opt=sql_fetch_array($res); $k++) {
                if($opt['io_type'])
                    $opt_price = $opt['io_price'];
                else
                    $opt_price = $opt['ct_price'] + $opt['io_price'];

                // 소계
                $ct_price['stotal'] = $opt_price * $opt['ct_qty'];
                $ct_point['stotal'] = $opt['ct_point'] * $opt['ct_qty'];
                
                if($opt['ct_status'] == "주문") { 
                    $opt['ct_status'] = "<font style='color:#999'>주문</span>";
                } else if($opt['ct_status'] == "입금") { 
                    $opt['ct_status'] = "<font class='font-B main_color'>입금</span>";
                } else if($opt['ct_status'] == "취소") { 
                    $opt['ct_status'] = "<font style='color:#ff6666'>취소</span>";
                } else if($opt['ct_status'] == "반품") { 
                    $opt['ct_status'] = "<font style='color:#ff6666'>반품</span>";
                } else if($opt['ct_status'] == "품절") { 
                    $opt['ct_status'] = "<font style='color:#ff6666'>품절</span>";
                }
                

                $ct_price_total += $opt_price;
                $opt_price_total += $ct_price['stotal'];
                $opt_ct_qty_total += $opt['ct_qty'];
                
                $cost_rb = get_sendcost_rb($od['od_id'], $member['mb_id'], '1');
                
                
            ?>
            <tr>
                <?php if($k == 0) { ?>
                <td rowspan="<?php echo $rowspan; ?>" style="min-width:80px;"><?php echo $image; ?></td>
                <td rowspan="<?php echo $rowspan; ?>" class="td_left">
                    <a href="./itemform.php?w=u&amp;it_id=<?php echo $row['it_id']; ?>">
                        <?php echo stripslashes($row['it_name']); ?>
                    </a>
                    <?php if($od['od_tax_flag'] && $row['ct_notax']) echo '[비과세상품]'; ?>
                </td>
                <td rowspan="<?php echo $rowspan; ?>" class="td_chk" style="border-right:1px solid #eee;">
                    
                    <input type="checkbox" id="sit_sel_<?php echo $i; ?>" name="it_sel[]">
                    <label for="sit_sel_<?php echo $i; ?>" class=""></label>
                </td>
                <?php } ?>
                <td class="td_left opt_td_tiny" style="min-width:280px;">
                    
                    <input type="checkbox" name="ct_chk[<?php echo $chk_cnt; ?>]" id="ct_chk_<?php echo $chk_cnt; ?>" value="<?php echo $chk_cnt; ?>" class="sct_sel_<?php echo $i; ?>">
                    <label for="ct_chk_<?php echo $chk_cnt; ?>" class=""><?php echo get_text($opt['ct_option']); ?></label>
                    <input type="hidden" name="ct_id[<?php echo $chk_cnt; ?>]" value="<?php echo $opt['ct_id']; ?>">
                </td>
                <td class="td_mngsmall" nowrap><?php echo $opt['ct_status']; ?></td>
                <td class="td_num">
                    <?php echo $opt['ct_qty']; ?>
                    <input type="hidden" name="ct_qty[<?php echo $chk_cnt; ?>]" id="ct_qty_<?php echo $chk_cnt; ?>" value="<?php echo $opt['ct_qty']; ?>">
                </td>
                <td class="td_num_right" nowrap><?php echo number_format($opt_price); ?></td>
                <td class="td_num_right" nowrap><?php echo number_format($ct_price['stotal']); ?></td>
                <?php if($k == 0) { ?>
                <td class="td_sendcost_by" nowrap rowspan="<?php echo $rowspan; ?>" style="border-left:1px solid #eee;">
                <?php echo $ct_send_cost; ?>
                
                <?php if(isset($opt['ct_invoice']) && $opt['ct_invoice']) { ?>
                    <div class="mt-5 font-12">
                    <?php echo $opt['ct_invoice_time'] ?><br>
                    <?php echo $opt['ct_invoice'] ?><?php if(isset($opt['ct_delivery_company']) && $opt['ct_delivery_company']) { ?> (<?php echo $opt['ct_delivery_company'] ?>)<?php } ?>
                    </div>
                <?php } ?>
                
                </td>
                <?php } ?>
            </tr>
            <?php
                $chk_cnt++;
            }
            ?>
            
        <?php
        }
        ?>
        
        <tfoot>
            <tr>
                <td colspan="4" class="font-B">주문합계 (배송비 포함)</td>
                <td colspan="1" class="font-B main_color" nowrap><?php echo number_format($opt_price_total + $cost_rb); ?></td>
                <td class="font-B" nowrap><?php echo isset($opt_ct_qty_total) ? number_format($opt_ct_qty_total) : '0'; ?></td>
                <td class="font-B" nowrap><?php echo isset($ct_price_total) ? number_format($ct_price_total) : '0'; ?></td>
                <td class="font-B" nowrap><?php echo isset($opt_price_total) ? number_format($opt_price_total) : '0'; ?></td>
                <td class="font-B" nowrap><?php echo isset($cost_rb) ? number_format($cost_rb) : '0'; ?></td>
            </tr>
        </tfoot>
            
        </tbody>
        </table>
    </div>

    <div class="btn_list02 btn_list">
        <p>
            <input type="hidden" name="chk_cnt" value="<?php echo $chk_cnt; ?>">
            <strong>장바구니 상태 변경　</strong>
            <?php 
            if (isset($od['od_status']) && $od['od_status'] == "주문") { 
                $pressed_opt = "javascript:alert('결제이전의 주문건은 변경하실 수 없습니다.'); return false;";
            } else if (isset($od['od_status']) && $od['od_status'] == "완료") { 
                $pressed_opt = "javascript:alert('완료 처리된 주문건은 변경하실 수 없습니다.'); return false;";
            } else if (isset($od['od_status']) && $od['od_status'] == "취소" || isset($od['od_status']) && $od['od_status'] == "반품") { 
                $pressed_opt = "javascript:alert('취소 및 반품 처리된 주문건은 변경하실 수 없습니다.'); return false;";
            } else { 
                $pressed_opt = "document.pressed=this.value";
            }
                
            ?>
            <input type="submit" name="ct_status" value="준비" onclick="<?php echo $pressed_opt ?>" class="btn_frmline">
            <input type="submit" name="ct_status" value="배송" onclick="<?php echo $pressed_opt ?>" class="btn_frmline">
            <input type="submit" name="ct_status" value="취소" onclick="<?php echo $pressed_opt ?>" class="btn_frmline">
            <input type="submit" name="ct_status" value="반품" onclick="<?php echo $pressed_opt ?>" class="btn_frmline">
            <input type="submit" name="ct_status" value="품절" onclick="<?php echo $pressed_opt ?>" class="btn_frmline">
        </p>
    </div>

    <div class="local_desc02 local_desc mt-20">
    <p>
        관리자 검토 후 <strong>[완료]</strong> 처리시 판매대금이 정산 됩니다.<br>
        <strong>[주문]</strong> 은 아직 결제가 되지 않은 주문건이므로 배송하시면 안됩니다.<br>
        <strong>[입금]</strong> 은 입금이 완료된 건 입니다. 배송을 시작해주세요!
    </p>
    </div>

    </form>


</section>

<?php if($is_admin == 'super' || $od['od_test'] || $od['od_pg'] === 'inicis' && !$od['od_test']) { ?>
<div class="od_test_caution">
    <?php if($is_admin == 'super') { ?>
    <dd>주의) 관리자는 관리자모드를 이용해주세요.</dd>
    <?php } ?>
    <?php if($od['od_test']) { ?>
    <dd>주의) 이 주문은 테스트용으로 실제 결제가 이루어지지 않았으므로 절대 배송하시면 안됩니다.</dd>
    <?php } ?>
    <?php if($od['od_pg'] === 'inicis' && !$od['od_test']) {
        $sql = "select P_TID from {$g5['g5_shop_inicis_log_table']} where oid = '$od_id' and P_STATUS = 'cancel' ";
        $tmp_row = sql_fetch($sql);
        if(isset($tmp_row['P_TID']) && $tmp_row['P_TID']){
    ?>
    <dd>주의) 이 주문은 결제취소된 내역이 있습니다.</dd>
    <?php 
        }   //end if
    }   //end if
    ?>
</div>
<?php } ?>



<!--
<section id="anc_sodr_pay">
    <h2 class="h2_frm">주문결제 내역</h2>
    <?php echo $pg_anchor; ?>

    <?php
    // 주문금액 = 상품구입금액 + 배송비 + 추가배송비
    $amount['order'] = $od['od_cart_price'] + $od['od_send_cost'] + $od['od_send_cost2'];

    // 입금액 = 결제금액 + 포인트
    $amount['receipt'] = $od['od_receipt_price'] + $od['od_receipt_point'];

    // 쿠폰금액
    $amount['coupon'] = $od['od_cart_coupon'] + $od['od_coupon'] + $od['od_send_coupon'];

    // 취소금액
    $amount['cancel'] = $od['od_cancel_price'];

    // 미수금 = 주문금액 - 취소금액 - 입금금액 - 쿠폰금액
    //$amount['미수'] = $amount['order'] - $amount['receipt'] - $amount['coupon'];

    // 결제방법
    $s_receipt_way = check_pay_name_replace($od['od_settle_case'], $od);

    if ($od['od_receipt_point'] > 0)
        $s_receipt_way .= "+포인트";
    ?>

    <div class="tbl_head01 tbl_wrap tbl_pa_list tbl_pa_list_order">
        <strong class="sodr_nonpay">미수금 <?php echo display_price($od['od_misu']); ?></strong>

        <table>
        <caption>주문결제 내역</caption>
        <thead>
        <tr>
            <th scope="col">주문번호</th>
            <th scope="col">결제방법</th>
            <th scope="col">주문총액</th>
            <th scope="col">배송비</th>
            <th scope="col">포인트결제</th>
            <th scope="col">총결제액</th>
            <th scope="col">쿠폰</th>
            <th scope="col">주문취소</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><?php echo $od['od_id']; ?></td>
            <td class="td_paybybig"><?php echo $s_receipt_way; ?></td>
            <td class="td_numbig td_numsum" nowrap><?php echo display_price($amount['order']); ?></td>
            <td class="td_numbig" nowrap><?php echo display_price($od['od_send_cost'] + $od['od_send_cost2']); ?></td>
            <td class="td_numbig" nowrap><?php echo display_point($od['od_receipt_point']); ?></td>
            <td class="td_numbig td_numincome" nowrap><?php echo number_format($amount['receipt']); ?>원</td>
            <td class="td_numbig td_numcoupon" nowrap><?php echo display_price($amount['coupon']); ?></td>
            <td class="td_numbig td_numcancel" nowrap><?php echo number_format($amount['cancel']); ?>원</td>
        </tr>
        </tbody>
        </table>
    </div>
</section>
-->

<?php
    $sql_iv = sql_fetch ( " select * from {$g5['g5_shop_cart_table']} where od_id = '$od_id' and ct_partner = '{$member['mb_id']}' limit 1 " );
?>

<section class="">
    <h2 class="h2_frm">배송정보 일괄 등록/변경</h2>
    
    
    <div class="local_desc02 local_desc">
        <p>
            배송정보 등록 후 반드시 장바구니 상태를 [배송] 으로 변경해주세요.
            <?php if(isset($sql_iv['ct_invoice']) && $sql_iv['ct_invoice']) { ?>
            <br>배송정보 : <font class="font-B"><?php echo $sql_iv['ct_delivery_company'] ?> <?php echo $sql_iv['ct_invoice'] ?><?php if(isset($sql_iv['ct_invoice_time']) && $sql_iv['ct_invoice_time']) { ?> (<?php echo $sql_iv['ct_invoice_time'] ?>)</font> 등록됨<?php } ?>
            <?php } ?>
        </p>
    </div>
    

    <?php echo $pg_anchor; ?>

    <form name="frmorderreceiptform" action="./rb.mod/partner/orderformreceiptupdate.php" method="post" autocomplete="off">
    <input type="hidden" name="od_id" value="<?php echo $od_id; ?>">
    <input type="hidden" name="sort1" value="<?php echo $sort1; ?>">
    <input type="hidden" name="sort2" value="<?php echo $sort2; ?>">
    <input type="hidden" name="sel_field" value="<?php echo $sel_field; ?>">
    <input type="hidden" name="search" value="<?php echo $search; ?>">
    <input type="hidden" name="page" value="<?php echo $page; ?>">
    <input type="hidden" name="od_name" value="<?php echo $od['od_name']; ?>">
    <input type="hidden" name="od_hp" value="<?php echo $od['od_hp']; ?>">
    <input type="hidden" name="od_tno" value="<?php echo $od['od_tno']; ?>">
    <input type="hidden" name="od_escrow" value="<?php echo $od['od_escrow']; ?>">
    <input type="hidden" name="od_pg" value="<?php echo $od['od_pg']; ?>">
    
    <input type="hidden" name="main" value="<?php echo $main; ?>">
    <input type="hidden" name="sub" value="<?php echo $sub; ?>">

    <div class="compare_wrap">

        <section id="anc_sodr_paymo" class="compare_right">

            <div class="tbl_head01 tbl_wrap">
                <table>

                <colgroup>
                    <col class="grid_3">
                    <col>
                </colgroup>
                <tbody>


                <tr>
                    <th scope="row"><label for="od_invoice">운송장번호</label></th>
                    <td>
                        <input type="text" name="od_invoice" value="" id="od_invoice" class="frm_input">　
                        <input type="checkbox" name="od_sms_baesong_check" id="od_sms_baesong_check">
                        <label for="od_sms_baesong_check">배송알림 발송</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="od_delivery_company">배송사</label></th>
                    <td>
                        <input type="text" name="od_delivery_company" id="od_delivery_company" value="" class="frm_input">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="od_invoice_time">배송일시</label></th>
                    <td>
                        <input type="text" name="od_invoice_time" id="od_invoice_time" value="" class="frm_input" maxlength="19">　
                        <input type="checkbox" id="od_invoice_chk" value="<?php echo date("Y-m-d H:i:s", G5_SERVER_TIME); ?>" onclick="chk_invoice_time()">
                        <label for="od_invoice_chk">현재 시간으로 설정</label><br>
                    </td>
                </tr>


                </tbody>
                </table>
            </div>
        </section>

    </div>

    <div class="btn_confirm01 btn_confirm">
        <input type="submit" value="배송정보 수정" class="btn_submit btn">
        <a href="./partner.php?<?php echo $qstr; ?>" class="btn btn_02">목록</a>
    </div>
    </form>
</section>

<section>
    <h2 class="h2_frm">주문자 정보</h2>
    <?php echo $pg_anchor; ?>
    

    <div class="compare_wrap">

        <section id="anc_sodr_orderer" class="compare_left">
            <div class="local_desc02 local_desc">
                <p>
                    주문하신분 정보 입니다.<br>
                    배송은 아래 배송지 정보를 참고하여 배송해주세요.
                </p>
            </div>

            <div class="tbl_head01 tbl_wrap">
                <table>
                <caption>주문자/배송지 정보</caption>
                <colgroup>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                <tr>
                    <th scope="row">이름</th>
                    <td><?php echo get_text($od['od_name']); ?></td>
                </tr>
                <tr>
                    <th scope="row">일반전화</th>
                    <td><?php echo get_text($od['od_tel']); ?></td>
                </tr>
                <tr>
                    <th scope="row">휴대전화</th>
                    <td><?php echo get_text($od['od_hp']); ?></td>
                </tr>
                <tr>
                    <th scope="row"><span class="sound_only">주문하시는 분 </span>주소</th>
                    <td>
                        <?php if(isset($od['od_zip1']) && $od['od_zip1']) { ?>(<?php echo get_text($od['od_zip1']).get_text($od['od_zip2']); ?>)<?php } ?> <?php echo get_text($od['od_addr1']); ?> <?php echo get_text($od['od_addr2']); ?> <?php if(isset($od['od_addr3']) && $od['od_addr3']) { ?><?php echo get_text($od['od_addr3']); ?><?php } ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">이메일</th>
                    <td><?php echo $od['od_email']; ?></td>
                </tr>

                </tbody>
                </table>
            </div>
        </section>
        
        <section id="anc_sodr_taker" class="compare_right mt-30">
            <h2 class="h2_frm">배송지 정보</h2>
            <div class="local_desc02 local_desc">
                <p>
                    배송지 정보 입니다.<br>
                    배송 시 동/호수, 도로명, 지번 등 오탈자가 없도록 유의해주세요.
                </p>
            </div>

            <div class="tbl_head01 tbl_wrap">
                <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                <tr>
                    <th scope="row">이름</th>
                    <td><?php echo get_text($od['od_b_name']); ?></td>
                </tr>
                <tr>
                    <th scope="row">일반전화</th>
                    <td><?php echo get_text($od['od_b_tel']); ?></td>
                </tr>
                <tr>
                    <th scope="row">휴대전화</th>
                    <td><?php echo get_text($od['od_b_hp']); ?></td>
                </tr>
                <tr>
                    <th scope="row">주소</th>
                    <td>
                       
                        <?php if(isset($od['od_b_zip1']) && $od['od_b_zip1']) { ?>(<?php echo get_text($od['od_b_zip1']).get_text($od['od_b_zip2']); ?>)<?php } ?> <?php echo get_text($od['od_b_addr1']); ?> <?php echo get_text($od['od_b_addr2']); ?> <?php if(isset($od['od_b_addr3']) && $od['od_b_addr3']) { ?><?php echo get_text($od['od_b_addr3']); ?><?php } ?>
                    </td>
                </tr>

                <?php if ($default['de_hope_date_use']) { ?>
                <tr>
                    <th scope="row">희망배송일</th>
                    <td>
                        <?php echo $od['od_hope_date']; ?> (<?php echo get_yoil($od['od_hope_date']); ?>)
                    </td>
                </tr>
                <?php } ?>

                <tr>
                    <th scope="row">전달 메세지</th>
                    <td><?php if ($od['od_memo']) echo get_text($od['od_memo'], 1);else echo "없음";?></td>
                </tr>
                </tbody>
                </table>
            </div>
        </section>

    </div>


    <div class="btn_confirm01 btn_confirm">
        <a href="./partner.php?<?php echo $qstr; ?>" class="btn btn_02">목록</a>
    </div>

    </form>
</section>

<script>
$(function() {
    // 전체 옵션선택
    $("#sit_select_all").click(function() {
        if($(this).is(":checked")) {
            $("input[name='it_sel[]']").attr("checked", true);
            $("input[name^=ct_chk]").attr("checked", true);
        } else {
            $("input[name='it_sel[]']").attr("checked", false);
            $("input[name^=ct_chk]").attr("checked", false);
        }
    });

    // 상품의 옵션선택
    $("input[name='it_sel[]']").click(function() {
        var cls = $(this).attr("id").replace("sit_", "sct_");
        var $chk = $("input[name^=ct_chk]."+cls);
        if($(this).is(":checked"))
            $chk.attr("checked", true);
        else
            $chk.attr("checked", false);
    });

    // 개인결제추가
    $("#personalpay_add").on("click", function() {
        var href = this.href;
        window.open(href, "personalpaywin", "left=100, top=100, width=700, height=560, scrollbars=yes");
        return false;
    });

    // 부분취소창
    $("#orderpartcancel").on("click", function() {
        var href = this.href;
        window.open(href, "partcancelwin", "left=100, top=100, width=600, height=350, scrollbars=yes");
        return false;
    });
});

function form_submit(f)
{
    var check = false;
    var status = document.pressed;

    for (i=0; i<f.chk_cnt.value; i++) {
        if (document.getElementById('ct_chk_'+i).checked == true)
            check = true;
    }

    if (check == false) {
        alert("처리할 자료를 하나 이상 선택해 주십시오.");
        return false;
    }

    var msg = "";

    <?php if (is_cancel_shop_pg_order($od)) { ?>
    if(status == "취소" || status == "반품" || status == "품절") {
        var $ct_chk = $("input[name^=ct_chk]");
        var chk_cnt = $ct_chk.length;
        var chked_cnt = $ct_chk.filter(":checked").length;
        <?php if($od['od_pg'] == 'KAKAOPAY') { ?>
        var cancel_pg = "카카오페이";
        <?php } else { ?>
        var cancel_pg = "PG사의 <?php echo $od['od_settle_case']; ?>";
        <?php } ?>

        if(chk_cnt == chked_cnt) {
            if(confirm(cancel_pg+" 결제를 함께 취소하시겠습니까?\n\n한번 취소한 결제는 다시 복구할 수 없습니다.")) {
                f.pg_cancel.value = 1;
                msg = cancel_pg+" 결제 취소와 함께 ";
            } else {
                f.pg_cancel.value = 0;
                msg = "";
            }
        }
    }
    <?php } ?>

    if (confirm(msg+"\'" + status + "\' 상태를 선택하셨습니다.\n\n선택하신대로 처리하시겠습니까?")) {
        return true;
    } else {
        return false;
    }
}

function del_confirm()
{
    if(confirm("주문서를 삭제하시겠습니까?")) {
        return true;
    } else {
        return false;
    }
}

// 기본 배송회사로 설정
function chk_delivery_company()
{
    var chk = document.getElementById("od_delivery_chk");
    var company = document.getElementById("od_delivery_company");
    company.value = chk.checked ? chk.value : company.defaultValue;
}

// 현재 시간으로 배송일시 설정
function chk_invoice_time()
{
    var chk = document.getElementById("od_invoice_chk");
    var time = document.getElementById("od_invoice_time");
    time.value = chk.checked ? chk.value : time.defaultValue;
}

// 결제금액 수동 설정
function chk_receipt_price()
{
    var chk = document.getElementById("od_receipt_chk");
    var price = document.getElementById("od_receipt_price");
    price.value = chk.checked ? (parseInt(chk.value) + parseInt(price.defaultValue)) : price.defaultValue;
}
</script>
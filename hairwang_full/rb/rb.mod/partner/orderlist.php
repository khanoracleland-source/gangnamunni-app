<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$g5['title'] = '주문내역';
$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

$where = array();

$doc = isset($_GET['doc']) ? clean_xss_tags($_GET['doc'], 1, 1) : '';
$sort1 = (isset($_GET['sort1']) && in_array($_GET['sort1'], array('od_id', 'od_cart_price', 'od_receipt_price', 'od_cancel_price', 'od_misu', 'od_cash'))) ? $_GET['sort1'] : '';
$sort2 = (isset($_GET['sort2']) && in_array($_GET['sort2'], array('desc', 'asc'))) ? $_GET['sort2'] : 'desc';
$sel_field = (isset($_GET['sel_field']) && in_array($_GET['sel_field'], array('od_id', 'mb_id', 'od_name', 'od_tel', 'od_hp', 'od_b_name', 'od_b_tel', 'od_b_hp', 'od_deposit_name', 'od_invoice')) ) ? $_GET['sel_field'] : ''; 
$od_status = isset($_GET['od_status']) ? get_search_string($_GET['od_status']) : '';
$search = isset($_GET['search']) ? get_search_string($_GET['search']) : '';

$fr_date = (isset($_GET['fr_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['fr_date'])) ? $_GET['fr_date'] : '';
$to_date = (isset($_GET['to_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['to_date'])) ? $_GET['to_date'] : '';

$od_misu = isset($_GET['od_misu']) ? preg_replace('/[^0-9a-z]/i', '', $_GET['od_misu']) : '';
$od_cancel_price = isset($_GET['od_cancel_price']) ? preg_replace('/[^0-9a-z]/i', '', $_GET['od_cancel_price']) : '';
$od_refund_price = isset($_GET['od_refund_price']) ? preg_replace('/[^0-9a-z]/i', '', $_GET['od_refund_price']) : '';
$od_receipt_point = isset($_GET['od_receipt_point']) ? preg_replace('/[^0-9a-z]/i', '', $_GET['od_receipt_point']) : '';
$od_coupon = isset($_GET['od_coupon']) ? preg_replace('/[^0-9a-z]/i', '', $_GET['od_coupon']) : ''; 
$od_settle_case = isset($_GET['od_settle_case']) ? clean_xss_tags($_GET['od_settle_case'], 1, 1) : ''; 
$od_escrow = isset($_GET['od_escrow']) ? clean_xss_tags($_GET['od_escrow'], 1, 1) : ''; 

$tot_itemcount = $tot_orderprice = $tot_receiptprice = $tot_ordercancel = $tot_misu = $tot_couponprice = 0;
$sql_search = "";
if ($search != "") {
    if ($sel_field != "") {
        $where[] = " $sel_field like '%$search%' ";
    }

    if ($save_search != $search) {
        $page = 1;
    }
}

if ($od_status) {
    switch($od_status) {
        case '취소':
            $where[] = " a.od_status = '취소' ";
            break;
        case '부분취소':
            $where[] = " a.od_status IN('주문', '입금', '준비', '배송', '완료') and od_cancel_price > 0 ";
            break;
        default:
            $where[] = " a.od_status = '$od_status' ";
            break;
    }

    switch ($od_status) {
        case '주문' :
            $sort1 = "a.od_id";
            $sort2 = "desc";
            break;
        case '입금' :   // 결제완료
            $sort1 = "a.od_receipt_time";
            $sort2 = "desc";
            break;
        case '배송' :   // 배송중
            $sort1 = "a.od_invoice_time";
            $sort2 = "desc";
            break;
    }
}

if ($od_settle_case) {
    if( $od_settle_case === '간편결제' ) {
        $where[] = " a.od_settle_case in ('간편결제', '삼성페이', 'lpay', 'inicis_kakaopay') ";
    } else {
        $where[] = " a.od_settle_case = '$od_settle_case' ";
    }
}

if ($od_misu) {
    $where[] = " a.od_misu != 0 ";
}

if ($od_cancel_price) {
    $where[] = " a.od_cancel_price != 0 ";
}

if ($od_refund_price) {
    $where[] = " a.od_refund_price != 0 ";
}

if ($od_receipt_point) {
    $where[] = " a.od_receipt_point != 0 ";
}

if ($od_coupon) {
    $where[] = " ( a.od_cart_coupon > 0 or a.od_coupon > 0 or a.od_send_coupon > 0 ) ";
}

if ($od_escrow) {
    $where[] = " a.od_escrow = 1 ";
}

if ($fr_date && $to_date) {
    $where[] = " a.od_time between '$fr_date 00:00:00' and '$to_date 23:59:59' ";
}

if ($is_admin != 'super') {
    $where[] = " b.ct_partner = '{$member['mb_id']}' ";
}

if ($where) {
    $sql_search = ' where '.implode(' and ', $where);
}

if ($sel_field == "")  $sel_field = "a.od_id";
if ($sort1 == "") $sort1 = "a.od_id";
if ($sort2 == "") $sort2 = "desc";


//$sql_common = " from {$g5['g5_shop_order_table']} $sql_search ";
$sql_common = " from {$g5['g5_shop_order_table']} a left join {$g5['g5_shop_cart_table']} b on (a.od_id = b.od_id) $sql_search ";

$sql = " select count(a.od_id) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? $row['cnt'] : '0';

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql  = " select *,
            (a.od_cart_coupon + a.od_coupon + a.od_send_coupon) as couponprice
           $sql_common 
           group by a.od_id 
           order by $sort1 $sort2
           limit $from_record, $rows ";
$result = sql_query($sql);

$qstr1 = "od_status=".urlencode($od_status)."&amp;od_settle_case=".urlencode($od_settle_case)."&amp;od_misu=$od_misu&amp;od_cancel_price=$od_cancel_price&amp;od_refund_price=$od_refund_price&amp;od_receipt_point=$od_receipt_point&amp;od_coupon=$od_coupon&amp;fr_date=$fr_date&amp;to_date=$to_date&amp;sel_field=$sel_field&amp;search=$search&amp;save_search=$search";

if($default['de_escrow_use'])
    $qstr1 .= "&amp;od_escrow=$od_escrow";
$qstr = "$qstr1&amp;main=$main&amp;sub=$sub&amp;sort1=$sort1&amp;sort2=$sort2&amp;page=$page";
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'?main='.$main.'&sub='.$sub.'" class="ov_listall">전체목록</a>';

// 주문삭제 히스토리 테이블 필드 추가
if(!sql_query(" select mb_id from {$g5['g5_shop_order_delete_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_order_delete_table']}`
                    ADD `mb_id` varchar(20) NOT NULL DEFAULT '' AFTER `de_data`,
                    ADD `de_ip` varchar(255) NOT NULL DEFAULT '' AFTER `mb_id`,
                    ADD `de_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `de_ip` ", true);
}

if( function_exists('pg_setting_check') ){
	pg_setting_check(true);
}
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">전체 주문내역</span><span class="ov_num font-B main_color"> <?php echo number_format($total_count); ?>건</span></span>
    <?php /* if($od_status == '준비' && $total_count > 0) { ?>
    <a href="./orderdelivery.php" id="order_delivery" class="ov_a">엑셀배송처리</a>
    <?php } */ ?>
</div>

<form name="frmorderlist" class="local_sch01 local_sch">
<input type="hidden" name="doc" value="<?php echo $doc; ?>">
<input type="hidden" name="sort1" value="<?php echo $sort1; ?>">
<input type="hidden" name="sort2" value="<?php echo $sort2; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_search" value="<?php echo $search; ?>">
<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">

<label for="sel_field" class="sound_only">검색대상</label>
<select name="sel_field" id="sel_field" class="select">
    <option value="od_id" <?php echo get_selected($sel_field, 'od_id'); ?>>주문번호</option>
    <option value="mb_id" <?php echo get_selected($sel_field, 'mb_id'); ?>>회원 ID</option>
    <option value="od_name" <?php echo get_selected($sel_field, 'od_name'); ?>>주문자</option>
    <option value="od_tel" <?php echo get_selected($sel_field, 'od_tel'); ?>>주문자전화</option>
    <option value="od_hp" <?php echo get_selected($sel_field, 'od_hp'); ?>>주문자핸드폰</option>
    <option value="od_b_name" <?php echo get_selected($sel_field, 'od_b_name'); ?>>받는분</option>
    <option value="od_b_tel" <?php echo get_selected($sel_field, 'od_b_tel'); ?>>받는분전화</option>
    <option value="od_b_hp" <?php echo get_selected($sel_field, 'od_b_hp'); ?>>받는분핸드폰</option>
    <option value="od_deposit_name" <?php echo get_selected($sel_field, 'od_deposit_name'); ?>>입금자</option>
    <option value="od_invoice" <?php echo get_selected($sel_field, 'od_invoice'); ?>>운송장번호</option>
</select>

<label for="search" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="search" value="<?php echo $search; ?>" id="search" required class="required frm_input" autocomplete="off">
<input type="submit" value="검색" class="btn_submit">

</form>

<form class="local_sch03 local_sch bg_divs_sc">
<div class="bg_divs_gr">
<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">
<!--
<div>
    <strong>주문상태</strong>　
    <input type="radio" name="od_status" value="" id="od_status_all"    <?php echo get_checked($od_status, '');     ?>>
    <label for="od_status_all">전체</label>　
    <input type="radio" name="od_status" value="주문" id="od_status_odr" <?php echo get_checked($od_status, '주문'); ?>>
    <label for="od_status_odr">주문</label>　
    <input type="radio" name="od_status" value="입금" id="od_status_income" <?php echo get_checked($od_status, '입금'); ?>>
    <label for="od_status_income">입금</label>　
    <input type="radio" name="od_status" value="준비" id="od_status_rdy" <?php echo get_checked($od_status, '준비'); ?>>
    <label for="od_status_rdy">준비</label>　
    <input type="radio" name="od_status" value="배송" id="od_status_dvr" <?php echo get_checked($od_status, '배송'); ?>>
    <label for="od_status_dvr">배송</label>　
    <input type="radio" name="od_status" value="완료" id="od_status_done" <?php echo get_checked($od_status, '완료'); ?>>
    <label for="od_status_done">완료</label>　
    <input type="radio" name="od_status" value="취소" id="od_status_cancel" <?php echo get_checked($od_status, '취소'); ?>>
    <label for="od_status_cancel">취소</label>　
</div>
-->
<div class="sch_last">
    <strong>주문일자</strong>　
    <input type="text" id="fr_date"  name="fr_date" value="<?php echo $fr_date; ?>" class="frm_input btn_frmline_inp datepicker datepicker_inp" autocomplete="off" size="10" maxlength="10"> ~
    <input type="text" id="to_date"  name="to_date" value="<?php echo $to_date; ?>" class="frm_input btn_frmline_inp datepicker datepicker_inp" autocomplete="off" size="10" maxlength="10">
    <button type="button" onclick="javascript:set_date('오늘');" class="btn_frmline">오늘</button>
    <button type="button" onclick="javascript:set_date('어제');" class="btn_frmline">어제</button>
    <button type="button" onclick="javascript:set_date('이번주');" class="btn_frmline">이번주</button>
    <button type="button" onclick="javascript:set_date('이번달');" class="btn_frmline">이번달</button>
    <button type="button" onclick="javascript:set_date('지난주');" class="btn_frmline">지난주</button>
    <button type="button" onclick="javascript:set_date('지난달');" class="btn_frmline">지난달</button>
    <button type="button" onclick="javascript:set_date('전체');" class="btn_frmline">전체</button>
    <input type="submit" value="검색" class="btn_submit btn_frmline_submit">
</div>

</div>
</form>

<form name="forderlist" id="forderlist" onsubmit="return forderlist_submit(this);" method="post" autocomplete="off">
<input type="hidden" name="search_od_status" value="<?php echo $od_status; ?>">

<div class="tbl_head01 tbl_wrap tbl_pa_list tbl_pa_list_order">
    <table id="sodr_list">
    <caption>주문 내역 목록</caption>
    <thead>
    <tr>
        <th scope="col" id="th_ordnum">주문번호</th>
        
        <th scope="col" id="th_odrer">주문자</th>
        <th scope="col">주문자 휴대전화</th>
        <th scope="col">주문일시</th>
        <th scope="col">보기</th>
    </tr>

    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        // 결제 수단
        $s_receipt_way = $s_br = "";
        if ($row['od_settle_case'])
        {
            $s_receipt_way = check_pay_name_replace($row['od_settle_case'], $row);
            $s_br = '<br />';
        }
        else
        {
            $s_receipt_way = '결제수단없음';
            $s_br = '<br />';
        }

        if ($row['od_receipt_point'] > 0)
            $s_receipt_way .= $s_br."포인트";

        $mb_nick = get_sideview($row['mb_id'], get_text($row['od_name']), $row['od_email'], '');

        $od_cnt = 0;
        if ($row['mb_id'])
        {
            $sql2 = " select count(*) as cnt from {$g5['g5_shop_order_table']} where mb_id = '{$row['mb_id']}' ";
            $row2 = sql_fetch($sql2);
            $od_cnt = $row2['cnt'];
        }

        // 주문 번호에 device 표시
        $od_mobile = '';
        if($row['od_mobile'])
            $od_mobile = '(M)';

        // 주문번호에 - 추가
        switch(strlen($row['od_id'])) {
            case 16:
                $disp_od_id = substr($row['od_id'],0,8).''.substr($row['od_id'],8);
                break;
            default:
                $disp_od_id = substr($row['od_id'],0,6).''.substr($row['od_id'],6);
                break;
        }

        // 주문 번호에 에스크로 표시
        $od_paytype = '';
        if($row['od_test'])
            $od_paytype .= '<br><span class="list_test">테스트주문</span>';

        if($default['de_escrow_use'] && $row['od_escrow'])
            $od_paytype .= '<br><span class="list_escrow">에스크로</span>';

        $uid = md5($row['od_id'].$row['od_time'].$row['od_ip']);

        $invoice_time = is_null_time($row['od_invoice_time']) ? G5_TIME_YMDHIS : $row['od_invoice_time'];
        $delivery_company = $row['od_delivery_company'] ? $row['od_delivery_company'] : $default['de_delivery_company'];

        $bg = 'bg'.($i%2);
        $td_color = 0;
        if($row['od_cancel_price'] > 0) {
            $bg .= 'cancel';
            $td_color = 1;
        }
        
        if(isset($row['od_status']) && $row['od_status'] == "입금") {
            $od_status_txt = "<span class='font-B main_color'>입금</span>";
            $od_st_bg = "style='background-color:#fcfcfc !important;'";
        } else if(isset($row['od_status']) && $row['od_status'] == "취소") {
            $od_status_txt = "<span class='' style='color:#ff6666'>취소</span>";
            $od_st_bg = "";
        } else if(isset($row['od_status']) && $row['od_status'] == "주문") {
            $od_status_txt = "<span class='' style='color:#999'>주문</span>";
            $od_st_bg = "";
        } else { 
            $od_status_txt = $row['od_status'];
            $od_st_bg = "";
        }
    ?>
    
    
    

    
    <tr class="orderlist<?php echo ' '.$bg; ?>">

        <td nowrap <?php echo $od_st_bg ?>>
            <span class="font-B main_color"><?php echo $disp_od_id; ?></span></a>
            <?php echo $od_paytype; ?>
        </td>
        
        
        <td nowrap class="" <?php echo $od_st_bg ?>>
        <?php if ($row['mb_id']) { ?>
            <?php echo $mb_nick; ?> 
        <?php } else { ?>
            비회원
        <?php } ?>
        </td>
        
        <td nowrap <?php echo $od_st_bg ?>>
            <?php echo $row['od_hp'] ?>
        </td>
        
        <td nowrap <?php echo $od_st_bg ?>>
            <?php echo $row['od_time'] ?>
        </td>
        
        <td nowrap class="td_mng td_mng_s" <?php echo $od_st_bg ?>>
            <a href="./partner.php?main=<?php echo $main ?>&amp;sub=orderform&od_id=<?php echo $row['od_id']; ?>" class="mng_mod btn btn_02"><span class="sound_only"><?php echo $row['od_id']; ?> </span>보기</a>
        </td>
    </tr>
    

    <?php
        $tot_itemcount     += $row['od_cart_count'];
        $tot_orderprice    += ($row['od_cart_price'] + $row['od_send_cost'] + $row['od_send_cost2']);
        $tot_ordercancel   += $row['od_cancel_price'];
        $tot_receiptprice  += $row['od_receipt_price'];
        $tot_couponprice   += $row['couponprice'];
        $tot_misu          += $row['od_misu'];
    }
    sql_free_result($result);
    if ($i == 0)
        echo '<tr><td colspan="5" class="empty_table">자료가 없습니다.</td></tr>';
    ?>
    </tbody>

    </table>
</div>



<div class="local_desc02 local_desc mt-20">
<p>
    관리자 검토 후 <strong>[완료]</strong> 처리시 판매대금이 정산 됩니다.<br>
    <strong class="main_color">주의</strong> 주문번호를 클릭하여 나오는 주문상세내역의 주소를 외부에서 조회가 가능한곳에 올리지 마세요.
</p>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>

function set_date(today)
{
    <?php
    $date_term = date('w', G5_SERVER_TIME);
    $week_term = $date_term + 7;
    $last_term = strtotime(date('Y-m-01', G5_SERVER_TIME));
    ?>
    if (today == "오늘") {
        document.getElementById("fr_date").value = "<?php echo G5_TIME_YMD; ?>";
        document.getElementById("to_date").value = "<?php echo G5_TIME_YMD; ?>";
    } else if (today == "어제") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME - 86400); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME - 86400); ?>";
    } else if (today == "이번주") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', strtotime('-'.$date_term.' days', G5_SERVER_TIME)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME); ?>";
    } else if (today == "이번달") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-01', G5_SERVER_TIME); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME); ?>";
    } else if (today == "지난주") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', strtotime('-'.$week_term.' days', G5_SERVER_TIME)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', strtotime('-'.($week_term - 6).' days', G5_SERVER_TIME)); ?>";
    } else if (today == "지난달") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-01', strtotime('-1 Month', $last_term)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-t', strtotime('-1 Month', $last_term)); ?>";
    } else if (today == "전체") {
        document.getElementById("fr_date").value = "";
        document.getElementById("to_date").value = "";
    }
}
</script>


<?php
$sub_menu = '000653';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "r");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

$g5['title'] = '입점사 정산관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','od_id','mb_id','ct_partner'))) {
    $sfl = '';
}


$fr_date = (isset($_GET['fr_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['fr_date'])) ? $_GET['fr_date'] : '';
$to_date = (isset($_GET['to_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['to_date'])) ? $_GET['to_date'] : '';

$sql_common = " from {$g5['g5_shop_cart_table']} where ct_status = '완료' and ct_partner != '' ";

if($sfl && $stx) {
    $sql_common .= " and $sfl LIKE '%{$stx}%' ";
}

if ($fr_date && $to_date) {
    $sql_common .= " and ct_js_time between '$fr_date 00:00:00' and '$to_date 23:59:59' ";
}


// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql_order = "order by ct_id";

$sql  = " select *
           $sql_common
           $sql_order
           limit $from_record, $rows ";
$result = sql_query($sql);

//$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page;
$qstr  = $qstr.'&amp;page='.$page.'&amp;save_stx='.$stx.'&amp;fr_date='.$fr_date.'&amp;to_date='.$to_date;
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

?>


<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">총</span><span class="ov_num"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="od_id" <?php echo get_selected($sfl, 'od_id'); ?>>주문번호</option>
    <option value="mb_id" <?php echo get_selected($sfl, 'mb_id'); ?>>주문자ID</option>
    <option value="ct_partner" <?php echo get_selected($sfl, 'ct_partner'); ?>>판매자ID</option>
</select>

<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" class="frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>


<form class="local_sch03 local_sch">
<div class="sch_last">
    <strong>정산일자</strong>
    <input type="text" id="fr_date"  name="fr_date" value="<?php echo $fr_date; ?>" class="frm_input" size="10" maxlength="10"> ~
    <input type="text" id="to_date"  name="to_date" value="<?php echo $to_date; ?>" class="frm_input" size="10" maxlength="10">
    <button type="button" onclick="javascript:set_date('오늘');">오늘</button>
    <button type="button" onclick="javascript:set_date('어제');">어제</button>
    <button type="button" onclick="javascript:set_date('이번주');">이번주</button>
    <button type="button" onclick="javascript:set_date('이번달');">이번달</button>
    <button type="button" onclick="javascript:set_date('지난주');">지난주</button>
    <button type="button" onclick="javascript:set_date('지난달');">지난달</button>
    <button type="button" onclick="javascript:set_date('전체');">전체</button>
    <input type="submit" value="검색" class="btn_submit">
</div>
</form>


<form name="fitemqalist" method="post" action="./partner_js_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">



    
    
<div class="tbl_head01 tbl_wrap" id="itemqalist">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">판매자(아이디)</th>
        <th scope="col">주문자(아이디)</th>
        <th scope="col">주문번호</th>
        <th scope="col">상품</th>
        <th scope="col">주문일시</th>
        <th scope="col">판매금액</th>
        <th scope="col">수량</th>
        <th scope="col">수수료</th>
        <th scope="col">정산소계</th>
        <th scope="col">배송비</th>
        <th scope="col">정산합계</th>
        <th scope="col">수수료유형</th>
        <th scope="col">정산방법</th>
        <th scope="col">정산여부</th>
        <th scope="col">정산일시</th>
    </tr>
    </thead>
    <tbody>
    
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        
        if(isset($row['ct_partner']) && $row['ct_partner']) {
            $mbx = get_member($row['ct_partner']);
            $names = isset($mbx['mb_nick']) ? get_text($mbx['mb_nick']) : '';
        } else { 
            $names = "";
        }
        
        if(isset($row['mb_id']) && $row['mb_id']) {
            $mbx2 = get_member($row['mb_id']);
            $names2 = isset($mbx2['mb_nick']) ? get_text($mbx2['mb_nick']) : '';
        } else { 
            $names2 = "";
        }
        
        if($row['ct_js'] == 1) {
            $mb_js = "정산완료";
            $mb_js_bg = "style='background-color:#ffffff;'";
        } else { 
            $mb_js = "정산대기";
            $mb_js_bg = "style='background-color:#eff3f9;'";
        }
        
        $href = shop_item_url($row['it_id']);
        
        //상품금액을 구한다.
        if(isset($row['io_type']) && $row['io_type'] == 1) {
            $p_price = $row['io_price'] * $row['ct_qty'];
        } else { 
            $p_price = $row['ct_price'] * $row['ct_qty'];
        }
        
        
        //각 상품에 수수료가 있는지
        $item = sql_fetch (" select it_ssr, it_ssr_w from {$g5['g5_shop_item_table']} where it_id = '{$row['it_id']}' ");
        $it_ssr = isset($item['it_ssr']) ? $item['it_ssr'] : '0';
        $it_ssr_w = isset($item['it_ssr_w']) ? $item['it_ssr_w'] : '0';
        
        
        if($it_ssr > 0 || $it_ssr_w > 0) { //상품에 수수료가 있다면
            
            if($it_ssr_w > 0) { //원
                $p_ssr_pri = $it_ssr_w * $row['ct_qty'];
                $p_js_pri = $p_price - $p_ssr_pri;
                $p_price_ssr = "[상품] ".number_format($it_ssr_w)."원";
            } else { //%
                $p_ssr_pri = ($p_price * $it_ssr) / 100;
                $p_js_pri = $p_price - $p_ssr_pri;
                $p_price_ssr = "[상품] ".$it_ssr."%";
            }
            
        } else { 
            
            $pm = get_member($row['ct_partner']);
            
            if($pm['mb_ssr'] > 0) {
                $p_ssr_pri = ($p_price * $pm['mb_ssr']) / 100;
                $p_js_pri = $p_price - $p_ssr_pri;
                $p_price_ssr = "[회원] ".$pm['mb_ssr']."%";
            } else {
                $p_ssr_pri = ($p_price * $pa['pa_ssr']) / 100;
                $p_js_pri = $p_price - $p_ssr_pri;
                $p_price_ssr = "[공통] ".$pa['pa_ssr']."%";
            }
            
        }
        
        if(isset($pa['pa_point_use']) && $pa['pa_point_use'] == 1) {
            $pa_js_use = "예치금정산";
        } else { 
            $pa_js_use = "직접정산";
        }
        
        //it_sc_type 배송비세팅
        //0 (기본설정)
        //1 (무료배송)
        //2 (조건부)
        //3 (유료배송)
        //4 (수량별)
        
        if($row['it_sc_type'] == 4) {
            $p_cost = $row['it_sc_price'] * $row['ct_qty'];
        } else { 
            $p_cost = $row['it_sc_price'];
        }
        
        $p_js_pri_total = $p_js_pri + $p_cost;
        

        $bg = 'bg'.($i%2);
        
        
    ?>
    
    

    <tr <?php echo $mb_js_bg ?>>

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="ct_id[<?php echo $i; ?>]" value="<?php echo $row['ct_id']; ?>">
            <input type="hidden" name="mb_id[<?php echo $i; ?>]" value="<?php echo $row['mb_id']; ?>">
            <input type="hidden" name="od_id[<?php echo $i; ?>]" value="<?php echo $row['od_id']; ?>">
            <input type="hidden" name="it_name[<?php echo $i; ?>]" value="<?php echo $row['it_name']; ?>">
            <input type="hidden" name="ct_option[<?php echo $i; ?>]" value="<?php echo $row['ct_option']; ?>">
            <input type="hidden" name="ct_partner[<?php echo $i; ?>]" value="<?php echo $row['ct_partner']; ?>">
            
            <input type="hidden" name="ct_total_price[<?php echo $i; ?>]" value="<?php echo $p_price; ?>"><!--판매금액(수량포함)-->
            <input type="hidden" name="ct_js_ssr[<?php echo $i; ?>]" value="<?php echo $p_ssr_pri; ?>"><!--수수료(설정적용)-->
            <input type="hidden" name="ct_js_cost[<?php echo $i; ?>]" value="<?php echo $p_cost; ?>"><!--배송비합계(조건적용)-->
            <input type="hidden" name="ct_js_price_old[<?php echo $i; ?>]" value="<?php echo $p_js_pri; ?>"><!--정산소계-->
            <input type="hidden" name="ct_js_price[<?php echo $i; ?>]" value="<?php echo $p_js_pri_total; ?>"><!--정산합계-->
            <input type="hidden" name="ct_js_use[<?php echo $i; ?>]" value="<?php echo $pa_js_use; ?>"><!--정산방식-->
            <input type="hidden" name="ct_js_type[<?php echo $i; ?>]" value="<?php echo $p_price_ssr; ?>"><!--정산유형-->
        </td>
        <td class="" nowrap>
           <?php if($names) { ?>
           <a href="../member_form.php?w=u&mb_id=<?php echo $row['ct_partner'] ?>"><?php echo $names; ?> (<?php echo $row['ct_partner']; ?>)</a>
           <?php } ?>
        </td>
        <td class="" nowrap>
           <?php if($names2) { ?>
           <a href="../member_form.php?w=u&mb_id=<?php echo $row['mb_id'] ?>"><?php echo $names2; ?> (<?php echo $row['mb_id']; ?>)</a>
           <?php } ?>
        </td>
        <td class="">
            <a href="../shop_admin/orderform.php?od_id=<?php echo get_text($row['od_id']); ?>" target="_blank"><strong><?php echo get_text($row['od_id']); ?></strong></a>
        </td>
        <td class="td_left" nowrap>
        <a href="<?php echo $href ?>" target="_blank"><?php echo cut_str($row['it_name'], 20); ?></a>
        <dd style="font-size:11px; color:#777;"><?php echo $row['ct_option']; ?></dd>
        </td>
        <td class="" nowrap><?php echo $row['ct_time']; ?></td>
        <td class="" nowrap>
        
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_total_price']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_price) ? number_format($p_price) : '0'; ?>원
        <?php } ?>
        
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo $row['ct_qty']; ?></span>
        <?php } else { ?>
            <?php echo $row['ct_qty']; ?>
        <?php } ?>
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_ssr']); ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_ssr_pri) ? number_format($p_ssr_pri) : '0'; ?>원
        <?php } ?>
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_price_old']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_js_pri) ? number_format($p_js_pri) : '0'; ?>원
        <?php } ?>
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_cost']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_cost) ? number_format($p_cost) : '0'; ?>원
        <?php } ?>
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_price']) ?>원</span>
        <?php } else { ?>
            <strong><?php echo isset($p_js_pri_total) ? number_format($p_js_pri_total) : '0'; ?>원</strong>
        <?php } ?>
        
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo $row['ct_js_type'] ?></span>
        <?php } else { ?>
            <?php echo $p_price_ssr ?>
        <?php } ?>
        
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo $row['ct_js_use'] ?></span>
        <?php } else { ?>
            <?php echo $pa_js_use ?>
        <?php } ?>

        </td>
        
        <td class="" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo $mb_js ?></span>
        <?php } else { ?>
            <?php echo $mb_js; ?>
        <?php } ?>
        
        </td>
        <td class="" nowrap>
        <?php if($row['ct_js_time'] == "0000-00-00 00:00:00") { ?>
            -
        <?php } else { ?>
             <span style="color:#999"><?php echo $row['ct_js_time'] ?></span>
        <?php } ?>
        </td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="16" class="empty_table"><span>데이터가 없습니다.</span></td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" name="act_button" value="선택정산" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="선택취소" onclick="document.pressed=this.value" class="btn btn_02">
</div>

</form>

<div class="local_desc01 local_desc">
    <p>
        주문건의 상태가 <strong>[완료]</strong> 인 건만 정산 처리가 가능합니다.<br>
        정산처리시 현재의 수수료 설정에 따라 정산 됩니다.<br>
        <strong>[정산취소]</strong> 시 이미 지급된 예치금이 있는 경우 수동으로 회수 하셔야 합니다.<br><br>
        입점 설정에서 정산방법이 <strong>[예치금으로 정산]</strong> 에 체크되어있는 경우 <strong>[정산]</strong> 처리시 해당 정산건은 [정산완료] 로 표기되며 <strong>정산금액이 예치금으로 지급</strong> 됩니다.<br>
        입점 설정에서 정산방법이 <strong>[직접 정산]</strong> 에 체크되어있는 경우 해당 정산건은 [정산완료] 로만 표기 됩니다.
    </p>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>

$(function(){
    $("#fr_date, #to_date").datepicker({ changeMonth: true, changeYear: true, dateFormat: "yy-mm-dd", showButtonPanel: true, yearRange: "c-99:c+99", maxDate: "+0d" });
});

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

    
 
function fitemqalist_submit(f)
{


    if(document.pressed  == "선택정산") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 상품을 일괄 정산처리 합니다.\n계속 하시겠습니까?")) {
                return false;
            }
        }
    }
    
    if(document.pressed  == "선택취소") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 정산건을 일괄 취소처리 합니다.\n예치금이 지급된 건은 수동으로 회수하셔야 합니다.\n\n취소되는 시점의 수수료 설정이 새롭게 적용됩니다.계속 하시겠습니까?")) {
                return false;
            }
        }
    }

    return true;
}


</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>
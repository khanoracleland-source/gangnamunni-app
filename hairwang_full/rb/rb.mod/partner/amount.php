<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$g5['title'] = '정산내역';
$main = isset($_GET['main']) ? $_GET['main'] : '';

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','od_id','mb_id','ct_partner'))) {
    $sfl = '';
}

$fr_date = (isset($_GET['fr_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['fr_date'])) ? $_GET['fr_date'] : '';
$to_date = (isset($_GET['to_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['to_date'])) ? $_GET['to_date'] : '';

if($is_admin == 'super') {
    $sql_common = " from {$g5['g5_shop_cart_table']} where ct_status = '완료' and ct_partner != '' ";
} else { 
    $sql_common = " from {$g5['g5_shop_cart_table']} where ct_status = '완료' and ct_partner = '{$member['mb_id']}' ";
}

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

$qstr  = $qstr.'&amp;main='.$main.'&amp;page='.$page.'&amp;save_stx='.$stx.'&amp;fr_date='.$fr_date.'&amp;to_date='.$to_date;
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'?main='.$main.'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">정산내역</span><span class="ov_num main_color font-B"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
<input type="hidden" name="main" value="<?php echo $main; ?>">

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl" class="select">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="od_id" <?php echo get_selected($sfl, 'od_id'); ?>>주문번호</option>
    <?php if($is_admin == 'super') { ?>
    <option value="mb_id" <?php echo get_selected($sfl, 'mb_id'); ?>>주문자ID</option>
    <option value="ct_partner" <?php echo get_selected($sfl, 'ct_partner'); ?>>판매자ID</option>
    <?php } ?>
</select>

<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" class="frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>


<form class="local_sch03 local_sch bg_divs_sc">
<div class="bg_divs_gr">
<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">

<div class="sch_last">
    <strong>정산일자</strong>　
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


<form name="fitemlistupdate" method="post" action="./rb.mod/partner/amountupdate.php" onsubmit="return fitemlist_submit(this);" autocomplete="off" id="fitemlistupdate">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">

<input type="hidden" name="main" value="<?php echo $main; ?>">

<div class="tbl_head01 tbl_wrap tbl_pa_list">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    
    <tr>
        <th scope="col">주문번호</th>
        <th scope="col">상품</th>
        <th scope="col">판매금액</th>
        <th scope="col">수수료</th>
        <th scope="col">배송비</th>
        <th scope="col">정산합계</th>
        <th scope="col">정산여부</th>
        <th scope="col">정산일시</th>
    </tr>
    

    </thead>
    <tbody>
    
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        
        if($row['ct_js'] == 1) {
            $mb_js = "<span class='it_list_ico1 main_rb_bg'>정산완료</span>";
            $mb_js_bg = "style='background-color:#ffffff;'";
        } else { 
            $mb_js = "<span class='it_list_ico2'>정산대기</span>";
            $mb_js_bg = "style='background-color:#eff3f9;'";
        }
        
        $href = "./partner.php?main=item&sub=itemlist&sfl=it_id&stx=".$row['it_id'];
        
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


        <td nowrap class="font-B main_color font-12 td_img">
            <a href="./partner.php?main=order&sub=orderform&od_id=<?php echo get_text($row['od_id']); ?>"><strong><?php echo get_text($row['od_id']); ?></strong></a>
        </td>
        
        <td class="td_input th_titles">
            <a href="<?php echo $href ?>"><?php echo cut_str($row['it_name'], 20); ?></a>
            <dd style="font-size:11px; color:#777;"><?php echo $row['ct_option']; ?></dd>
        </td>
        

        
        <td class="td_numbig td_input" nowrap>
        
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_total_price']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_price) ? number_format($p_price) : '0'; ?>원
        <?php } ?>
        
        </td>
        

        <td class="td_numbig td_input" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_ssr']); ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_ssr_pri) ? number_format($p_ssr_pri) : '0'; ?>원
        <?php } ?>
        </td>

        <td class="td_numbig td_input" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_cost']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_cost) ? number_format($p_cost) : '0'; ?>원
        <?php } ?>
        </td>
        
        <td class="td_numbig td_input main_color font-B" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo number_format($row['ct_js_price']) ?>원</span>
        <?php } else { ?>
            <?php echo isset($p_js_pri_total) ? number_format($p_js_pri_total) : '0'; ?>원
        <?php } ?>
        
        </td>
        

        
        <td class="td_img" nowrap>
        <?php if($row['ct_js'] == 1) { ?>
            <span style="color:#999"><?php echo $mb_js ?></span>
        <?php } else { ?>
            <?php echo $mb_js; ?>
        <?php } ?>
        
        </td>
        
        <td class="td_img" nowrap>
        <?php if($row['ct_js_time'] == "0000-00-00 00:00:00") { ?>
            -
        <?php } else { ?>
             <span style="color:#999"><?php echo $row['ct_js_time'] ?></span>
        <?php } ?>
        </td>

    </tr>
    


    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="8" class="empty_table">내역이 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

</form>

<?php
//예치금 사용여부 판단
$table_rb_point_c_set = sql_query("DESCRIBE rb_point_c_set", false);
?>
<?php if ($table_rb_point_c_set) { ?>
<div class="btn_fixed_top">
    <a href="<?php echo G5_URL; ?>/rb/point_c.php" target="_blank" class="win_point btn btn_02">예치금내역</a>
    <a href="<?php echo G5_URL; ?>/rb/point_c.php?types=acc" target="_blank" class="win_point btn btn_submit">출금신청</a>
</div>
<?php } ?>



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

<script src="<?php echo G5_ADMIN_URL ?>/admin.js?ver=<?php echo G5_JS_VER; ?>"></script>
<script src="<?php echo G5_JS_URL ?>/jquery.anchorScroll.js?ver=<?php echo G5_JS_VER; ?>"></script>
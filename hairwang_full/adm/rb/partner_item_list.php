<?php
$sub_menu = '000652';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "r");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','it_id','it_maker','it_brand','it_model','it_origin','it_sell_email','it_partner'))) {
    $sfl = '';
}

$g5['title'] = '입점사 상품관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 분류
$ca_list  = '<option value="">선택</option>'.PHP_EOL;
$sql = " select * from {$g5['g5_shop_category_table']} ";
$sql .= " order by ca_order, ca_id ";

$result = sql_query($sql);
for ($i=0; $row=sql_fetch_array($result); $i++)
{
    $len = strlen($row['ca_id']) / 2 - 1;
    $nbsp = '';
    for ($i=0; $i<$len; $i++) {
        $nbsp .= '&nbsp;&nbsp;&nbsp;';
    }
    $ca_list .= '<option value="'.$row['ca_id'].'">'.$nbsp.$row['ca_name'].'</option>'.PHP_EOL;
}

$where = " and ";
$sql_search = "";
if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if ($save_stx != $stx)
        $page = 1;
}

if ($sca != "") {
    $sql_search .= " $where (a.ca_id like '$sca%' or a.ca_id2 like '$sca%' or a.ca_id3 like '$sca%') ";
}

if ($sfl == "")  $sfl = "it_name";

$sql_common = " from {$g5['g5_shop_item_table']} a ,
                     {$g5['g5_shop_category_table']} b
               where (a.ca_id = b.ca_id";

$sql_common .= " and a.it_partner != ''";
$sql_common .= ") ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

if (!$sst) {
    $sst  = "it_id";
    $sod = "desc";
}
$sql_order = "order by $sst $sod";


$sql  = " select *
           $sql_common
           $sql_order
           limit $from_record, $rows ";
$result = sql_query($sql);

//$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page;
$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page.'&amp;save_stx='.$stx;

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

?>


<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">등록된 상품</span><span class="ov_num"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">

<label for="sca" class="sound_only">분류선택</label>
<select name="sca" id="sca">
    <option value="">전체분류</option>
    <?php
    $sql1 = " select ca_id, ca_name from {$g5['g5_shop_category_table']} order by ca_order, ca_id ";
    $result1 = sql_query($sql1);
    for ($i=0; $row1=sql_fetch_array($result1); $i++) {
        $len = strlen($row1['ca_id']) / 2 - 1;
        $nbsp = '';
        for ($i=0; $i<$len; $i++) $nbsp .= '&nbsp;&nbsp;&nbsp;';
        echo '<option value="'.$row1['ca_id'].'" '.get_selected($sca, $row1['ca_id']).'>'.$nbsp.$row1['ca_name'].'</option>'.PHP_EOL;
    }
    ?>
</select>

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="it_id" <?php echo get_selected($sfl, 'it_id'); ?>>상품코드</option>
    <option value="it_partner" <?php echo get_selected($sfl, 'it_partner'); ?>>판매자ID</option>
</select>

<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" class="frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>


<form name="fitemqalist" method="post" action="./partner_item_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
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
        <th scope="col">닉네임(아이디)</th>
        <th scope="col">상품명</th>
        <th scope="col">수수료(%)</th>
        <th scope="col">수수료(원)</th>
        <th scope="col"><?php echo subject_sort_link('it_time'); ?>등록일시</a></th>
        <th scope="col"><?php echo subject_sort_link('it_use'); ?>승인여부</a></th>
        <th scope="col">보기</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        $href = shop_item_url($row['it_id']);
        
        if(isset($row['it_partner']) && $row['it_partner']) {
            $mbx = get_member($row['it_partner']);
            $names = isset($mbx['mb_nick']) ? get_text($mbx['mb_nick']) : '';
        } else { 
            $names = "";
        }
        
        if($row['it_use'] == 1) {
            $mb_use = "승인";
        } else { 
            $mb_use = "대기";
        }
        
        $bg = 'bg'.($i%2);
        
        
    ?>
    
    

    <tr style="background-color:#fff;">

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
            <input type="hidden" name="it_name[<?php echo $i; ?>]" value="<?php echo $row['it_name']; ?>">
            <input type="hidden" name="it_partner[<?php echo $i; ?>]" value="<?php echo $row['it_partner']; ?>">
        </td>
        <td class="" nowrap>
           <?php if($names) { ?>
           <a href="../member_form.php?w=u&mb_id=<?php echo $row['it_partner'] ?>"><?php echo $names; ?> (<?php echo $row['it_partner']; ?>)</a>
           <?php } ?>
        </td>
        <td class="">
            <a href="../shop_admin/itemform.php?w=u&it_id=<?php echo get_text($row['it_id']); ?>" target="_blank"><?php echo get_text($row['it_name']); ?></a>
        </td>
        <td class="td_mng td_mng_s" nowrap><input type="number" name="it_ssr[<?php echo $i; ?>]" value="<?php echo $row['it_ssr']; ?>" class="tbl_input sit_qty"></td>
        <td class="td_mng" nowrap><input type="number" name="it_ssr_w[<?php echo $i; ?>]" value="<?php echo $row['it_ssr_w']; ?>" class="tbl_input sit_qty"></td>
        <td class="" nowrap><?php echo $row['it_time']; ?></td>
        <td class="" nowrap><?php echo $mb_use; ?></td>
        <td class="td_datetime td_mng td_mng_s" nowrap>
            <a href="<?php echo $href ?>" target="_blank" class="btn btn_02">보기</a>
        </td>
        <td class="td_datetime td_mng td_mng_s" nowrap>
           
            <?php if($row['it_use'] == 1) { ?>
            <a id="data_btn_n" href="javascript:add_n('<?php echo $row['it_id']; ?>');" class="btn btn_03" data-type="반려">반려</a>
            <?php } else { ?>
            <a id="data_btn_y"  href="javascript:add_y('<?php echo $row['it_id']; ?>');" class="btn btn_01" data-type="승인">승인</a>
            <?php } ?>

        </td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="9" class="empty_table"><span>데이터가 없습니다.</span></td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" name="act_button" value="선택수정" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="선택승인" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="선택반려" onclick="document.pressed=this.value" class="btn btn_02">
</div>

</form>

<div class="local_desc01 local_desc">
    <p>
        <strong>판매수수료 적용 안내</strong><br><br>
        <strong>1순위 : </strong>상품 개별 수수료 (입점사 상품관리)<br>
        <strong>2순위 : </strong>입점사 개별 수수료 (입점사 관리)<br>
        <strong>3순위 : </strong>판매 수수료(공통) (입점 설정)<br><br>
        
        <strong>상품 개별 수수료 (%, 원) : </strong>각 상품별 수수료 설정이 가능합니다.<br>
        <strong>입점사 개별 수수료 (%) : </strong>각 회원별 수수료 설정이 가능합니다.<br>
        <strong>판매 수수료(공통) (%) : </strong>공통 수수료 설정이 가능합니다.<br><br>
        N% 의 경우 배송비를 제외한 상품 판매금액에서 설정된 N% 를 차감 후 정산 합니다.<br>
        N원 의 경우 상품 1개당 설정된 N원 을 차감 후 정산 합니다.
    </p>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
    

function add_y(it_id) {

    if(confirm('해당 상품을 등록승인 처리 합니다. 계속 하시겠습니까?')) {

        $.ajax({
            url:'<?php echo G5_URL ?>/rb/rb.lib/ajax.partner_item_add.php',
            type:'post', // 전송방식
            dataType:'json',
            data:{
                "it_use" : "1",
                "it_id" : it_id
            },
            success: function(res) {
                window.location.reload();
            },
            error: function(err) {
                alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
            }
        });

    } else {
        return false;
    }
    
}
    
    
function add_n(it_id) {

    if(confirm('해당 상품을 등록반려 처리 합니다. 계속 하시겠습니까?')) {

        $.ajax({
            url:'<?php echo G5_URL ?>/rb/rb.lib/ajax.partner_item_add.php',
            type:'post', // 전송방식
            dataType:'json',
            data:{
                "it_use" : "0",
                "it_id" : it_id
            },
            success: function(res) {
                window.location.reload();
            },
            error: function(err) {
                alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
            }
        });

    } else {
        return false;
    }
    
}

    
    
function fitemqalist_submit(f)
{
    
    if(document.pressed == "선택삭제") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        }
    }

    if(document.pressed  == "선택승인") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 상품을 일괄 승인처리 합니다.\n승인 처리된 상품은 판매가 가능해집니다.\n계속 하시겠습니까?")) {
                return false;
            }
        }
    }
    
    if(document.pressed  == "선택반려") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 상품을 일괄 반려처리 합니다.\n반려 처리된 상품은 상품정보 수정을 통해 다시 신청 될 수 있습니다.\n계속 하시겠습니까?")) {
                return false;
            }
        }
    }
    
    if(document.pressed  == "선택수정") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 상품의 수수료 정보를 변경합니다.\n계속 하시겠습니까?")) {
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
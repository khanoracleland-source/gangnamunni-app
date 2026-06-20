<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','it_id','it_maker','it_brand','it_model','it_origin','it_sell_email'))) {
    $sfl = '';
}

$g5['title'] = '상품관리';
$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

// 분류
$ca_list  = '<option value="">선택</option>'.PHP_EOL;
$sql = " select * from {$g5['g5_shop_category_table']} ";
/*
if ($is_admin != 'super')
    $sql .= " where ca_mb_id = '{$member['mb_id']}' ";
*/
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

if ($is_admin != 'super')
    $sql_common .= " and a.it_partner = '{$member['mb_id']}'";

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
$qstr  = $qstr.'&amp;main='.$main.'&amp;sub='.$sub.'&amp;sca='.$sca.'&amp;page='.$page.'&amp;save_stx='.$stx;

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'?main='.$main.'&sub='.$sub.'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">등록된 상품</span><span class="ov_num main_color font-B"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">
<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">
<label for="sca" class="sound_only">분류선택</label>
<select name="sca" id="sca" class="select">
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
<select name="sfl" id="sfl" class="select">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="it_id" <?php echo get_selected($sfl, 'it_id'); ?>>상품코드</option>
</select>

<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" class="frm_input">
<input type="submit" value="검색" class="btn_submit">

</form>

<form name="fitemlistupdate" method="post" action="./rb.mod/partner/itemlistupdate.php" onsubmit="return fitemlist_submit(this);" autocomplete="off" id="fitemlistupdate">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">

<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">

<div class="tbl_head01 tbl_wrap tbl_pa_list">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    
    <tr>
        <th scope="col">
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)" class="magic-checkbox">
            <label for="chkall" class=""></label>
        </th>
        <th scope="col"><?php echo subject_sort_link('it_id', 'sca='.$sca); ?>상품코드</a></th>
        <th scope="col" id="th_img">이미지</th>

        <th scope="col" id="th_pc_title"><?php echo subject_sort_link('it_name', 'sca='.$sca); ?>상품명</a></th>
        <th scope="col" id="th_amt"><?php echo subject_sort_link('it_price', 'sca='.$sca); ?>판매가격</a></th>
        <th scope="col" id="th_qty"><?php echo subject_sort_link('it_stock_qty', 'sca='.$sca); ?>재고</a></th>
        
        <th scope="col"><?php echo subject_sort_link('it_use', 'sca='.$sca, 1); ?>상태</a></th>
        <th scope="col"><?php echo subject_sort_link('it_soldout', 'sca='.$sca, 1); ?>품절</a></th>
        <th scope="col"><?php echo subject_sort_link('it_hit', 'sca='.$sca, 1); ?>조회</a></th>
        <th scope="col">관리</th>
    </tr>
    

    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {
        $href = shop_item_url($row['it_id']);
        $bg = 'bg'.($i%2);

        $it_point = $row['it_point'];
        if($row['it_point_type'])
            $it_point .= '%';
        
        if($row['it_use'] == 1) {
            $it_use = "<span class='it_list_ico1 main_rb_bg'>판매중</span>";
        } else { 
            $it_use = "<span class='it_list_ico2'>검토중</span>";
        }
        
        if($row['it_soldout'] == 1) {
            $it_soldout = "<span class='it_list_ico3'>품절</span>";
        } else { 
            $it_soldout = "　";
        }
        
    ?>
    
    
    <tr>
        <td class="">
            <input type="checkbox" name="chk[]" class="magic-checkbox" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <label for="chk_<?php echo $i; ?>"></label>
        </td>

        <td nowrap class="td_img">
        <input type="hidden" name="it_id[<?php echo $i; ?>]" value="<?php echo $row['it_id']; ?>">
            <a href="./partner.php?main=<?php echo $main ?>&amp;sub=itemform&amp;w=u&amp;it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>" class="font-B main_color font-12"><?php echo $row['it_id']; ?></a>
        </td>
        <td class="td_img"><a href="<?php echo $href; ?>" target="_blank" title="상품 보기" alt="상품 보기"><?php echo rb_it_image($row['it_id'], 50, 50); ?></a></td>
       
        <td headers="th_pc_title" class="td_input th_titles">
            <a href="./partner.php?main=<?php echo $main ?>&amp;sub=itemform&amp;w=u&amp;it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>" class=""><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?></a>
        </td>
        
        <td headers="th_amt" class="td_numbig td_input color-000" nowrap>
            <?php echo number_format($row['it_price']); ?>
        </td>
        
        <td headers="th_qty" class="td_numbig td_input" nowrap>
            <?php echo number_format($row['it_stock_qty']); ?>
        </td>
        
        <td nowrap class="td_num">
           <?php echo $it_use; ?>
        </td>
        <td nowrap class="td_num">
           <?php echo $it_soldout; ?>
        </td>
        <td nowrap class="td_num"><?php echo $row['it_hit']; ?></td>
        <td class="td_mng td_mng_s" nowrap>
            <a href="./partner.php?main=<?php echo $main ?>&amp;sub=itemform&amp;w=u&amp;it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>" class="btn btn_03"><span class="sound_only"><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?> </span>수정</a>
            <!-- 추후 고도화 {
            <a href="./itemcopy.php?it_id=<?php echo $row['it_id']; ?>&amp;ca_id=<?php echo $row['ca_id']; ?>" class="itemcopy btn btn_02" target="_blank"><span class="sound_only"><?php echo htmlspecialchars2(cut_str($row['it_name'],250, "")); ?> </span>복사</a>
            -->
        </td>
    </tr>
    

    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="10" class="empty_table">등록된 상품이 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">

    <a href="./partner.php?main=<?php echo $main ?>&amp;sub=itemform" class="btn btn_submit">상품등록</a>
    <!--
    <a href="./itemexcel.php" onclick="return excelform(this.href);" target="_blank" class="btn btn_02">상품일괄등록</a>
    -->
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
function fitemlist_submit(f)
{
    if (!is_checked("chk[]")) {
        alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
        return false;
    }

    if(document.pressed == "선택삭제") {
        if(!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
            return false;
        }
    }

    return true;
}

$(function() {
    $(".itemcopy").click(function() {
        var href = $(this).attr("href");
        window.open(href, "copywin", "left=100, top=100, width=300, height=200, scrollbars=0");
        return false;
    });
});

function excelform(url)
{
    var opt = "width=600,height=450,left=10,top=10";
    window.open(url, "win_excel", opt);
    return false;
}
</script>


<script src="<?php echo G5_ADMIN_URL ?>/admin.js?ver=<?php echo G5_JS_VER; ?>"></script>
<script src="<?php echo G5_JS_URL ?>/jquery.anchorScroll.js?ver=<?php echo G5_JS_VER; ?>"></script>
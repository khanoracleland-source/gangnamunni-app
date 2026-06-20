<?php
include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

if (isset($sfl) && $sfl && !in_array($sfl, array('it_name','a.it_id'))) {
    $sfl = '';
}

$g5['title'] = '상품문의';
$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

$where = " where ";

if ($is_admin != 'super') {
    $where .= " it_partner = '{$member['mb_id']}' and ";
}

$sql_search = "";

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
        
        if ($is_admin != 'super') {
            $sql_search .= " $where it_partner = '{$member['mb_id']}' ";
        }
    }
    if ($save_stx != $stx)
        $page = 1;
} else {
    if ($is_admin != 'super') {
        $sql_search .= " where it_partner = '{$member['mb_id']}' ";
    }
}

if ($sca != "") {
    $sql_search .= " and ca_id like '$sca%' ";
}

if ($sfl == "")  $sfl = "it_name";
if (!$sst) {
    $sst = "iq_id";
    $sod = "desc";
}

$sql_common = "  from {$g5['g5_shop_item_qa_table']} a
                 left join {$g5['g5_shop_item_table']} b on (a.it_id = b.it_id)
                 left join {$g5['member_table']} c on (a.mb_id = c.mb_id) ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql  = " select *
          $sql_common
          order by $sst $sod, iq_id desc
          limit $from_record, $rows ";
$result = sql_query($sql);

//$qstr = 'page='.$page.'&amp;sst='.$sst.'&amp;sod='.$sod.'&amp;stx='.$stx;
$qstr .= ($qstr ? '&amp;' : '').'sca='.$sca.'&amp;main='.$main.'&amp;save_stx='.$stx;
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'?main='.$main.'&amp;sub='.$sub.'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt"> 전체 문의내역</span><span class="ov_num font-B main_color"> <?php echo $total_count; ?>건</span></span>
</div>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="page" value="<?php echo $page; ?>">
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
        $nbsp = "";
        for ($i=0; $i<$len; $i++) $nbsp .= "&nbsp;&nbsp;&nbsp;";
        $selected = ($row1['ca_id'] == $sca) ? ' selected="selected"' : '';
        echo '<option value="'.$row1['ca_id'].'"'.$selected.'>'.$nbsp.$row1['ca_name'].'</option>'.PHP_EOL;
    }
    ?>
</select>

<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl" class="select">
    <option value="it_name" <?php echo get_selected($sfl, 'it_name'); ?>>상품명</option>
    <option value="a.it_id" <?php echo get_selected($sfl, 'a.it_id'); ?>>상품코드</option>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="frm_input required">
<input type="submit" value="검색" class="btn_submit">

</form>


<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod" value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx" value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">

<div class="tbl_head01 tbl_wrap tbl_pa_list tbl_pa_list_order" id="itemqalist">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">이미지</a></th>
        <th scope="col"><?php echo subject_sort_link('it_name'); ?>상품명</a></th>
        <th scope="col"><?php echo subject_sort_link('iq_subject'); ?>질문</a></th>
        <th scope="col"><?php echo subject_sort_link('mb_name'); ?>이름</a></th>
        <th scope="col"><?php echo subject_sort_link('iq_answer'); ?>답변</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $row['iq_subject'] = cut_str($row['iq_subject'], 30, "...");
        $href = shop_item_url($row['it_id']);
        $name = get_sideview($row['mb_id'], get_text($row['iq_name']), $row['mb_email'], $row['mb_homepage']);

        $answer = $row['iq_answer'] ? '<span class="font-B">답변완료</span>' : '<span class="font-B main_color">답변대기</span>';
        $iq_question = get_view_thumbnail(conv_content($row['iq_question'], 1), 768);
        $iq_answer = $row['iq_answer'] ? get_view_thumbnail(conv_content($row['iq_answer'], 1), 100) : "답변이 등록되지 않았습니다.";

        $bg = 'bg'.($i%2);
     ?>
    <tr class="<?php echo $bg; ?>">
        <td class="" style="width:10%;">
        <a href="<?php echo $href; ?>"><?php echo get_it_image($row['it_id'], 50, 50); ?></a>
        </td>
        <td class="td_left" style="width:20%;">
        <a href="<?php echo $href; ?>"><?php echo $row['it_name']; ?></a>
        </td>
        <td class="td_left" style="width:30%;">
            <a href="javascript:void(0);" class="qa_href font-B" onclick="return false;" target="<?php echo $i; ?>"><?php echo get_text($row['iq_subject']); ?></a>
        </td>
        <td class="td_name"><?php echo $name; ?></td>
        <td class="td_boolean"><?php echo $answer; ?></td>
        <td class="td_mng td_mng_s">
            <a href="./partner.php?w=u&amp;iq_id=<?php echo $row['iq_id']; ?>&amp;<?php echo $qstr; ?>&amp;sub=qaform" class="btn btn_03"><span class="sound_only"><?php echo get_text($row['iq_subject']); ?> </span>수정</a>
        </td>
    </tr>
    <tr id="qa_div<?php echo $i; ?>" class="qa_div" style="display:none;">
       <td colspan="6" class="td_left" style="background-color:#f9f9f9 !important;">
        <div>
                <div class="qa_q">
                    <?php echo $iq_question; ?>
                    <div class="cb"></div>
                </div>
                <div class="qa_a">
                <br>
                    <strong class="main_color">답변</strong><br>
                    <?php echo $iq_answer; ?>
                    <div class="cb"></div>
                </div>
            </div>
        </td>
    </tr>
    <?php
    }
    if ($i == 0) {
        echo '<tr><td colspan="5" class="empty_table"><span>자료가 없습니다.</span></td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>



<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
$(function(){
    $(".qa_href").click(function(){
        var $content = $("#qa_div"+$(this).attr("target"));
        $(".qa_div").each(function(index, value){
            if ($(this).get(0) == $content.get(0)) { // 객체의 비교시 .get(0) 를 사용한다.
                $(this).is(":hidden") ? $(this).show() : $(this).hide();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>
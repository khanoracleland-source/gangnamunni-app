<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

include_once('./_common.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.lib.php');
include_once(G5_PATH.'/rb/rb.mod/partner/partner.shop.lib.php');

$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

include_once(G5_EDITOR_LIB);
include_once(G5_LIB_PATH.'/iteminfo.lib.php');

$html_title = "상품 ";

$it = array(
'it_id'=>'',
'it_skin'=>'',
'it_mobile_skin'=>'',
'it_name'=>'',
'it_basic'=>'',
'it_order'=>0,
'it_type1'=>0,
'it_type2'=>0,
'it_type3'=>0,
'it_type4'=>0,
'it_type5'=>0,
'it_brand'=>'',
'it_model'=>'',
'it_tel_inq'=>0,
'it_use'=>0,
'it_nocoupon'=>0,
'ec_mall_pid'=>'',
'it_mobile_explan'=>'',
'it_sell_email'=>'',
'it_shop_memo'=>'',
'it_info_gubun'=>'',
'it_explan'=>'',
'it_point_type'=>0,
'it_cust_price'=>0,
'it_option_subject'=>'',
'it_price'=>0,
'it_point'=>0,
'it_supply_point'=>0,
'it_soldout'=>0,
'it_stock_sms'=>0,
'it_stock_qty'=>0,
'it_noti_qty'=>0,
'it_buy_min_qty'=>0,
'it_buy_max_qty'=>0,
'it_notax'=>0,
'it_supply_subject'=>'',
'it_sc_type'=>0,
'it_sc_method'=>0,
'it_sc_price'=>0,
'it_sc_minimum'=>0,
'it_sc_qty'=>0,
'it_img1'=>'',
'it_img2'=>'',
'it_img3'=>'',
'it_img4'=>'',
'it_img5'=>'',
'it_img6'=>'',
'it_img7'=>'',
'it_img8'=>'',
'it_img9'=>'',
'it_img10'=>'',
'it_head_html'=>'',
'it_tail_html'=>'',
'it_mobile_head_html'=>'',
'it_mobile_tail_html'=>'',
);

for($i=0;$i<=10;$i++){
    $it['it_'.$i.'_subj'] = '';
    $it['it_'.$i] = '';
}

if ($w == "")
{
    $html_title .= "입력";

    // 옵션은 쿠키에 저장된 값을 보여줌. 다음 입력을 위한것임
    //$it[ca_id] = _COOKIE[ck_ca_id];
    $it['ca_id'] = get_cookie("ck_ca_id");
    $it['ca_id2'] = get_cookie("ck_ca_id2");
    $it['ca_id3'] = get_cookie("ck_ca_id3");
    if (!$it['ca_id'])
    {
        $sql = " select ca_id from {$g5['g5_shop_category_table']} order by ca_order, ca_id limit 1 ";
        $row = sql_fetch($sql);
        if (! (isset($row['ca_id']) && $row['ca_id']))
            alert("등록된 분류가 없습니다.");
        $it['ca_id'] = $row['ca_id'];
    }
    //$it[it_maker]  = stripslashes($_COOKIE[ck_maker]);
    //$it[it_origin] = stripslashes($_COOKIE[ck_origin]);
    $it['it_maker']  = stripslashes(get_cookie("ck_maker"));
    $it['it_origin'] = stripslashes(get_cookie("ck_origin"));
}
else if ($w == "u")
{
    
    $html_title .= "수정";

    if ($is_admin != 'super')
    {
        $sql = " select it_id from {$g5['g5_shop_item_table']} a, {$g5['g5_shop_category_table']} b
                  where a.it_id = '$it_id'
                    and a.ca_id = b.ca_id
                    and a.it_partner = '{$member['mb_id']}' ";
        $row = sql_fetch($sql);
        if (!$row['it_id'])
            alert("수정 권한이 없는 상품입니다.");
    }

    $it = get_shop_item($it_id);
    /*
    if ($is_admin != 'super') {
        if(empty($it['it_use']) || $it['it_use'] == 0) {
            alert('승인 검토중인 상품은 수정하실 수 없습니다.');
        }
    }
    */

    if(!$it)
        alert('상품정보가 존재하지 않습니다.');
    
    if (function_exists('check_case_exist_title')) check_case_exist_title($it, G5_SHOP_DIR, false);

    if (! (isset($ca_id) && $ca_id))
        $ca_id = $it['ca_id'];

    $sql = " select * from {$g5['g5_shop_category_table']} where ca_id = '$ca_id' ";
    $ca = sql_fetch($sql);
}
else
{
    alert();
}

$qstr  = $qstr.'&amp;sca='.$sca.'&amp;page='.$page;

$g5['title'] = $html_title;

// 분류리스트
$category_select = '';
$script = '';
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

    $nbsp = "";
    for ($i=0; $i<$len; $i++)
        $nbsp .= "&nbsp;&nbsp;&nbsp;";

    $category_select .= "<option value=\"{$row['ca_id']}\">$nbsp{$row['ca_name']}</option>\n";

    $script .= "ca_use['{$row['ca_id']}'] = {$row['ca_use']};\n";
    $script .= "ca_stock_qty['{$row['ca_id']}'] = {$row['ca_stock_qty']};\n";
    //$script .= "ca_explan_html['$row[ca_id]'] = $row[ca_explan_html];\n";
    $script .= "ca_sell_email['{$row['ca_id']}'] = '{$row['ca_sell_email']}';\n";
}

// 재입고알림 설정 필드 추가
if(!sql_query(" select it_stock_sms from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_stock_sms` tinyint(4) NOT NULL DEFAULT '0' AFTER `it_stock_qty` ", true);
}

// 추가옵션 포인트 설정 필드 추가
if(!sql_query(" select it_supply_point from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_supply_point` int(11) NOT NULL DEFAULT '0' AFTER `it_point_type` ", true);
}

// 상품메모 필드 추가
if(!sql_query(" select it_shop_memo from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_shop_memo` text NOT NULL AFTER `it_use_avg` ", true);
}

// 지식쇼핑 PID 필드추가
// 상품메모 필드 추가
if(!sql_query(" select ec_mall_pid from {$g5['g5_shop_item_table']} limit 1 ", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `ec_mall_pid` varchar(255) NOT NULL AFTER `it_shop_memo` ", true);
}

$pg_anchor ='<ul class="anchor">
<li><a href="#anc_sitfrm_cate">상품분류</a></li>
<li><a href="#anc_sitfrm_skin">스킨설정</a></li>
<li><a href="#anc_sitfrm_ini">기본정보</a></li>
<li><a href="#anc_sitfrm_compact">요약정보</a></li>
<li><a href="#anc_sitfrm_cost">가격 및 재고</a></li>
<li><a href="#anc_sitfrm_sendcost">배송비</a></li>
<li><a href="#anc_sitfrm_img">상품이미지</a></li>
<li><a href="#anc_sitfrm_relation">관련상품</a></li>
<li><a href="#anc_sitfrm_event">관련이벤트</a></li>
<li><a href="#anc_sitfrm_optional">상세설명설정</a></li>
<li><a href="#anc_sitfrm_extra">여분필드</a></li>
</ul>
';


// 쿠폰적용안함 설정 필드 추가
if(!sql_query(" select it_nocoupon from {$g5['g5_shop_item_table']} limit 1", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_nocoupon` tinyint(4) NOT NULL DEFAULT '0' AFTER `it_use` ", true);
}

// 스킨필드 추가
if(!sql_query(" select it_skin from {$g5['g5_shop_item_table']} limit 1", false)) {
    sql_query(" ALTER TABLE `{$g5['g5_shop_item_table']}`
                    ADD `it_skin` varchar(255) NOT NULL DEFAULT '' AFTER `ca_id3`,
                    ADD `it_mobile_skin` varchar(255) NOT NULL DEFAULT '' AFTER `it_skin` ", true);
}
?>

<form name="fitemform" action="./rb.mod/partner/itemformupdate.php" method="post" enctype="MULTIPART/FORM-DATA" autocomplete="off" onsubmit="return fitemformcheck(this)">

<input type="hidden" name="w" value="<?php echo $w; ?>">
<input type="hidden" name="sca" value="<?php echo $sca; ?>">
<input type="hidden" name="sst" value="<?php echo $sst; ?>">
<input type="hidden" name="sod"  value="<?php echo $sod; ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
<input type="hidden" name="stx"  value="<?php echo $stx; ?>">
<input type="hidden" name="page" value="<?php echo $page; ?>">

<input type="hidden" name="main" value="<?php echo $main; ?>">
<input type="hidden" name="sub" value="<?php echo $sub; ?>">

<?php if($w == "") { ?>
<input type="hidden" name="it_skin" value="theme/basic"><!-- 스킨 -->
<input type="hidden" name="it_mobile_skin" value="theme/basic"><!-- 스킨 -->
<input type="hidden" name="it_notax" value="0"><!--상품과세 유형-->
<?php } else { ?>
<input type="hidden" name="it_skin" value="<?php echo $it['it_skin']; ?>">
<input type="hidden" name="it_mobile_skin" value="<?php echo $it['it_mobile_skin']; ?>">
<input type="hidden" name="it_notax" value="<?php echo $it['it_notax']; ?>">
<?php } ?>

<?php if(isset($pa['pa_item_use']) && $pa['pa_item_use'] == 1) { ?>
    <input type="hidden" name="it_use" value="1" id="it_use">
<?php } else { ?>
    <input type="hidden" name="it_use" value="0" id="it_use">
<?php } ?>

<?php if($w == "") { ?>
    <input type="hidden" name="it_partner" value="<?php echo $member['mb_id'] ?>"><!-- 판매자 -->
<?php } else { ?>
    <input type="hidden" name="it_partner" value="<?php echo isset($it['it_partner']) ? $it['it_partner'] : ''; ?>"><!-- 판매자 -->
<?php } ?>



<section id="anc_sitfrm_cate">
    <h2 class="h2_frm">상품분류</h2>

    <div class="local_desc02 local_desc">
        <p>기본분류는 반드시 선택하셔야 합니다. 하나의 상품에 최대 3개의 다른 분류를 지정할 수 있습니다.</p>
    </div>

    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>상품분류 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="ca_id">기본분류</label></th>
            <td>
                <?php if ($w == "") echo help("기본분류를 선택하면, 판매/재고/HTML사용/판매자 E-mail 등을, 선택한 분류의 기본값으로 설정합니다."); ?>
                <select name="ca_id" id="ca_id" onchange="categorychange(this.form)" class="select">
                    <option value="">선택하세요</option>
                    <?php echo conv_selected_option($category_select, $it['ca_id']); ?>
                </select>
                <script>
                    var ca_use = new Array();
                    var ca_stock_qty = new Array();
                    //var ca_explan_html = new Array();
                    var ca_sell_email = new Array();
                    var ca_opt1_subject = new Array();
                    var ca_opt2_subject = new Array();
                    var ca_opt3_subject = new Array();
                    var ca_opt4_subject = new Array();
                    var ca_opt5_subject = new Array();
                    var ca_opt6_subject = new Array();
                    <?php echo "\n$script"; ?>
                </script>
            </td>
        </tr>

        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_ini">
    <h2 class="h2_frm">기본정보</h2>
    <?php echo $pg_anchor; ?>
    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>기본정보 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">상품코드</th>
            <td colspan="2">
                <?php if ($w == '') { // 추가 ?>
                    <?php echo help("상품의 코드는 10자리 숫자로 자동생성합니다. <b>직접 상품코드를 입력할 수도 있습니다.</b>\n상품코드는 영문자, 숫자, - 만 입력 가능합니다."); ?>
                    <input type="text" name="it_id" value="<?php echo time(); ?>" id="it_id" required class="frm_input required" size="20" maxlength="20">
                <?php } else { ?>
                    <input type="hidden" name="it_id" value="<?php echo $it['it_id']; ?>">
                    <span class="frm_ca_id"><?php echo $it['it_id']; ?></span>　
                    <a href="<?php echo shop_item_url($it_id); ?>" class="btn_frmline">상품확인</a>
                    <a href="./partner.php?main=review&sub=uselist&sfl=a.it_id&stx=<?php echo $it_id; ?>" class="btn_frmline">사용후기</a>
                    <a href="./partner.php?main=qa&sub=qalist&sfl=a.it_id&amp;stx=<?php echo $it_id; ?>" class="btn_frmline">상품문의</a>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_name">상품명</label></th>
            <td colspan="2">
                <?php echo help("HTML 입력이 불가합니다."); ?>
                <input type="text" name="it_name" value="<?php echo get_text(cut_str($it['it_name'], 250, "")); ?>" id="it_name" required class="frm_input required" size="50">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_basic">기본설명</label></th>
            <td>
                <?php echo help("상품명 하단에 상품에 대한 추가적인 설명이 필요한 경우에 입력합니다. HTML 입력도 가능합니다."); ?>
                <input type="text" name="it_basic" value="<?php echo get_text(html_purifier($it['it_basic'])); ?>" id="it_basic" class="frm_input" size="50">
            </td>

        </tr>
        
        <tr>
            <th scope="row"><label for="it_maker">제조사</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_maker" value="<?php echo get_text($it['it_maker']); ?>" id="it_maker" class="frm_input" size="40">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_origin">원산지</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_origin" value="<?php echo get_text($it['it_origin']); ?>" id="it_origin" class="frm_input" size="40">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_brand">브랜드</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_brand" value="<?php echo get_text($it['it_brand']); ?>" id="it_brand" class="frm_input" size="40">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_model">모델</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_model" value="<?php echo get_text($it['it_model']); ?>" id="it_model" class="frm_input" size="40">
            </td>

        </tr>
        <tr>
            <th scope="row"><label for="it_tel_inq">전화문의</label></th>
            <td>
                <?php echo help("상품 금액 대신 전화문의로 표시됩니다."); ?>
                <input type="checkbox" name="it_tel_inq" value="1" id="it_tel_inq" <?php echo ($it['it_tel_inq']) ? "checked" : ""; ?>><label for="it_tel_inq">예</label>
            </td>
        </tr>

        <tr>
            <th scope="row">상품설명</th>
            <td colspan="2"> <?php echo editor_html('it_explan', get_text(html_purifier($it['it_explan']), 0)); ?></td>
        </tr>

        <tr>
            <th scope="row"><label for="it_sell_email">판매자이메일</label></th>
            <td>
                <?php echo help("주문서를 수신하실 이메일을 입력하세요."); ?>
                <input type="text" name="it_sell_email" value="<?php echo get_sanitize_input($it['it_sell_email']); ?>" id="it_sell_email" class="frm_input" size="40">
            </td>
        </tr>

        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_compact">
    <h2 class="h2_frm">상품요약정보</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p><strong>전자상거래 등에서의 상품 등의 정보제공에 관한 고시</strong>에 따라 총 35개 상품군에 대해 상품 특성 등을 양식에 따라 입력할 수 있습니다.</p>
    </div>

    <div id="sit_compact">
        <?php echo help("상품군을 선택하면 자동으로 항목이 변환됩니다."); ?>
        <select id="it_info_gubun" name="it_info_gubun" class="select">
            <option value="">상품군을 선택하세요.</option>
            <?php
            if(!$it['it_info_gubun']) $it['it_info_gubun'] = 'wear';
            foreach($item_info as $key=>$value) {
                $opt_value = $key;
                $opt_text  = $value['title'];
                echo '<option value="'.$opt_value.'" '.get_selected($opt_value, $it['it_info_gubun']).'>'.$opt_text.'</option>'.PHP_EOL;
            }
            ?>
        </select>
    </div>
    <div id="sit_compact_fields"><?php include_once(G5_PATH.'/rb/rb.mod/partner/iteminfo.php'); ?></div>
</section>


<script>
$(function(){
    $(document).on("change", "#it_info_gubun", function() {
        var gubun = $(this).val();
        $.post(
            "<?php echo G5_URL; ?>/rb/rb.mod/partner/iteminfo.php",
            { it_id: "<?php echo $it['it_id']; ?>", gubun: gubun },
            function(data) {
                $("#sit_compact_fields").empty().html(data);
            }
        );
    });
});
</script>

<section id="anc_sitfrm_cost">
    <h2 class="h2_frm">가격 및 재고</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>가격 및 재고 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="it_price">판매가격</label></th>
            <td>
                <input type="text" name="it_price" value="<?php echo $it['it_price']; ?>" id="it_price" class="frm_input" size="8"> 원
            </td>

        </tr>
        <tr>
            <th scope="row"><label for="it_cust_price">시중가격</label></th>
            <td>
                <?php echo help("입력하지 않으면 상품상세페이지에 출력하지 않습니다."); ?>
                <input type="text" name="it_cust_price" value="<?php echo $it['it_cust_price']; ?>" id="it_cust_price" class="frm_input" size="8"> 원
            </td>

        </tr>

        <tr>
            <th scope="row"><label for="it_soldout">상품품절</label></th>
            <td>
                <?php echo help("잠시 판매를 중단하거나 재고가 없을 경우에 체크해 놓으면 품절상품으로 표시됩니다."); ?>
                <input type="checkbox" name="it_soldout" value="1" id="it_soldout" <?php echo ($it['it_soldout']) ? "checked" : ""; ?>><label for="it_soldout">예</label>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="it_stock_qty">재고수량</label></th>
            <td>
                <?php echo help("<b>주문관리에서 상품별 상태 변경에 따라 자동으로 재고를 가감합니다.</b> 재고는 규격/색상별이 아닌, 상품별로만 관리됩니다.<br>재고수량을 0으로 설정하시면 품절상품으로 표시됩니다."); ?>
                <input type="text" name="it_stock_qty" value="<?php echo $it['it_stock_qty']; ?>" id="it_stock_qty" class="frm_input" size="8"> 개
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="it_buy_min_qty">최소구매수량</label></th>
            <td>
                <?php echo help("상품 구매시 최소 구매 수량을 설정합니다."); ?>
                <input type="text" name="it_buy_min_qty" value="<?php echo $it['it_buy_min_qty']; ?>" id="it_buy_min_qty" class="frm_input" size="8"> 개
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="it_buy_max_qty">최대구매수량</label></th>
            <td>
                <?php echo help("상품 구매시 최대 구매 수량을 설정합니다."); ?>
                <input type="text" name="it_buy_max_qty" value="<?php echo $it['it_buy_max_qty']; ?>" id="it_buy_max_qty" class="frm_input" size="8"> 개
            </td>
        </tr>
        
        <?php
        $opt_subject = explode(',', $it['it_option_subject']);
        ?>
        <tr>
            <th scope="row">상품선택옵션</th>
            <td colspan="2">
                <div class="sit_option tbl_frm01">
                    <?php echo help('옵션항목은 콤마(,) 로 구분하여 여러개를 입력할 수 있습니다. 옷을 예로 들어 [옵션1 : 사이즈 , 옵션1 항목 : XXL,XL,L,M,S] , [옵션2 : 색상 , 옵션2 항목 : 빨,파,노]<br><strong>옵션명과 옵션항목에 따옴표(\', ")는 입력할 수 없습니다.</strong>'); ?>
                    <table>
                    <caption>상품선택옵션 입력</caption>
                    <colgroup>
                        <col class="grid_4">
                        <col>
                    </colgroup>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="opt1_subject">옵션1</label>
                            <input type="text" name="opt1_subject" value="<?php echo isset($opt_subject[0]) ? $opt_subject[0] : ''; ?>" id="opt1_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt1"><b>옵션1 항목</b></label>
                            <input type="text" name="opt1" value="" id="opt1" class="frm_input" size="50">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="opt2_subject">옵션2</label>
                            <input type="text" name="opt2_subject" value="<?php echo isset($opt_subject[1]) ? $opt_subject[1] : ''; ?>" id="opt2_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt2"><b>옵션2 항목</b></label>
                            <input type="text" name="opt2" value="" id="opt2" class="frm_input" size="50">
                        </td>
                    </tr>
                     <tr>
                        <th scope="row">
                            <label for="opt3_subject">옵션3</label>
                            <input type="text" name="opt3_subject" value="<?php echo isset($opt_subject[2]) ? $opt_subject[2] : ''; ?>" id="opt3_subject" class="frm_input" size="15">
                        </th>
                        <td>
                            <label for="opt3"><b>옵션3 항목</b></label>
                            <input type="text" name="opt3" value="" id="opt3" class="frm_input" size="50">
                        </td>
                    </tr>
                    </tbody>
                    </table>
                    <div class="btn_confirm02 btn_confirm">
                        <br>
                        <button type="button" id="option_table_create" class="btn_frmline">옵션목록생성</button>
                    </div>
                </div>
                <div id="sit_option_frm"><?php include_once(G5_PATH.'/rb/rb.mod/partner/itemoption.php'); ?></div>

                <script>
                $(function() {
                    <?php if($it['it_id'] && $po_run) { ?>
                    //옵션항목설정
                    var arr_opt1 = new Array();
                    var arr_opt2 = new Array();
                    var arr_opt3 = new Array();
                    var opt1 = opt2 = opt3 = '';
                    var opt_val;

                    $(".opt-cell").each(function() {
                        opt_val = $(this).text().split(" > ");
                        opt1 = opt_val[0];
                        opt2 = opt_val[1];
                        opt3 = opt_val[2];

                        if(opt1 && $.inArray(opt1, arr_opt1) == -1)
                            arr_opt1.push(opt1);

                        if(opt2 && $.inArray(opt2, arr_opt2) == -1)
                            arr_opt2.push(opt2);

                        if(opt3 && $.inArray(opt3, arr_opt3) == -1)
                            arr_opt3.push(opt3);
                    });


                    $("input[name=opt1]").val(arr_opt1.join());
                    $("input[name=opt2]").val(arr_opt2.join());
                    $("input[name=opt3]").val(arr_opt3.join());
                    <?php } ?>
                    // 옵션목록생성
                    $("#option_table_create").click(function() {
                        var it_id = $.trim($("input[name=it_id]").val());
                        var opt1_subject = $.trim($("#opt1_subject").val());
                        var opt2_subject = $.trim($("#opt2_subject").val());
                        var opt3_subject = $.trim($("#opt3_subject").val());
                        var opt1 = $.trim($("#opt1").val());
                        var opt2 = $.trim($("#opt2").val());
                        var opt3 = $.trim($("#opt3").val());
                        var $option_table = $("#sit_option_frm");

                        if(!opt1_subject || !opt1) {
                            alert("옵션명과 옵션항목을 입력해 주십시오.");
                            return false;
                        }

                        $.post(
                            "<?php echo G5_URL; ?>/rb/rb.mod/partner/itemoption.php",
                            { it_id: it_id, w: "<?php echo $w; ?>", opt1_subject: opt1_subject, opt2_subject: opt2_subject, opt3_subject: opt3_subject, opt1: opt1, opt2: opt2, opt3: opt3 },
                            function(data) {
                                $option_table.empty().html(data);
                            }
                        );
                    });

                    // 모두선택
                    $(document).on("click", "input[name=opt_chk_all]", function() {
                        if($(this).is(":checked")) {
                            $("input[name='opt_chk[]']").attr("checked", true);
                        } else {
                            $("input[name='opt_chk[]']").attr("checked", false);
                        }
                    });

                    // 선택삭제
                    $(document).on("click", "#sel_option_delete", function() {
                        var $el = $("input[name='opt_chk[]']:checked");
                        if($el.length < 1) {
                            alert("삭제하려는 옵션을 하나 이상 선택해 주십시오.");
                            return false;
                        }

                        $el.closest("tr").remove();
                    });

                    // 일괄적용
                    $(document).on("click", "#opt_value_apply", function() {
                        if($(".opt_com_chk:checked").length < 1) {
                            alert("일괄 수정할 항목을 하나이상 체크해 주십시오.");
                            return false;
                        }

                        var opt_price = $.trim($("#opt_com_price").val());
                        var opt_stock = $.trim($("#opt_com_stock").val());
                        var opt_noti = $.trim($("#opt_com_noti").val());
                        var opt_use = $("#opt_com_use").val();
                        var $el = $("input[name='opt_chk[]']:checked");

                        // 체크된 옵션이 있으면 체크된 것만 적용
                        if($el.length > 0) {
                            var $tr;
                            $el.each(function() {
                                $tr = $(this).closest("tr");

                                if($("#opt_com_price_chk").is(":checked"))
                                    $tr.find("input[name='opt_price[]']").val(opt_price);

                                if($("#opt_com_stock_chk").is(":checked"))
                                    $tr.find("input[name='opt_stock_qty[]']").val(opt_stock);

                                if($("#opt_com_noti_chk").is(":checked"))
                                    $tr.find("input[name='opt_noti_qty[]']").val(opt_noti);

                                if($("#opt_com_use_chk").is(":checked"))
                                    $tr.find("select[name='opt_use[]']").val(opt_use);
                            });
                        } else {
                            if($("#opt_com_price_chk").is(":checked"))
                                $("input[name='opt_price[]']").val(opt_price);

                            if($("#opt_com_stock_chk").is(":checked"))
                                $("input[name='opt_stock_qty[]']").val(opt_stock);

                            if($("#opt_com_noti_chk").is(":checked"))
                                $("input[name='opt_noti_qty[]']").val(opt_noti);

                            if($("#opt_com_use_chk").is(":checked"))
                                $("select[name='opt_use[]']").val(opt_use);
                        }
                    });
                });
                </script>
            </td>
        </tr>
        <?php
        $spl_subject = explode(',', $it['it_supply_subject']);
        $spl_count = count($spl_subject);
        ?>
        <tr>
            <th scope="row">상품추가옵션</th>
            <td colspan="2">
                <div id="sit_supply_frm" class="sit_option tbl_frm01">
                    <?php echo help('옵션항목은 콤마(,) 로 구분하여 여러개를 입력할 수 있습니다. 스마트폰을 예로 들어 [추가1 : 추가구성상품 , 추가1 항목 : 액정보호필름,케이스,충전기]<br><strong>옵션명과 옵션항목에 따옴표(\', ")는 입력할 수 없습니다.</strong>'); ?>
                    <table>
                    <caption>상품추가옵션 입력</caption>
                    <colgroup>
                        <col class="grid_4">
                        <col>
                    </colgroup>
                    <tbody>
                    <?php
                    $i = 0;
                    do {
                        $seq = $i + 1;
                    ?>
                    <tr>
                        <th scope="row">
                            <span for="spl_subject_<?php echo $seq; ?>"></span>
                            <input type="text" name="spl_subject[]" id="spl_subject_<?php echo $seq; ?>" value="<?php echo $spl_subject[$i]; ?>" class="frm_input" size="15">
                        </th>
                        <td>
                            <span for="spl_item_<?php echo $seq; ?>"><b>추가<?php echo $seq; ?> 항목</b></span>
                            <input type="text" name="spl[]" id="spl_item_<?php echo $seq; ?>" value="" class="frm_input" size="40">
                            <?php
                            if($i > 0)
                                echo '<button type="button" id="del_supply_row" class="btn_frmline">삭제</button>';
                            ?>
                        </td>
                    </tr>
                    <?php
                        $i++;
                    } while($i < $spl_count);
                    ?>
                    </tbody>
                    </table>
                    <br>
                    <div id="sit_option_addfrm_btn"><button type="button" id="add_supply_row" class="btn_frmline">옵션추가</button></div>
                    <div class="btn_confirm02 btn_confirm">
                        <button type="button" id="supply_table_create" class="btn_frmline">옵션목록생성</button>
                    </div>
                </div>

                <div id="sit_option_addfrm"><?php include_once(G5_PATH.'/rb/rb.mod/partner/itemsupply.php'); ?></div>

                <script>
                $(function() {
                    <?php if($it['it_id'] && $ps_run) { ?>
                    // 추가옵션의 항목 설정
                    var arr_subj = new Array();
                    var subj, spl;

                    $("input[name='spl_subject[]']").each(function() {
                        subj = $.trim($(this).val());
                        if(subj && $.inArray(subj, arr_subj) == -1)
                            arr_subj.push(subj);
                    });

                    for(i=0; i<arr_subj.length; i++) {
                        var arr_spl = new Array();
                        $(".spl-subject-cell").each(function(index) {
                            subj = $(this).text();
                            if(subj == arr_subj[i]) {
                                spl = $(".spl-cell:eq("+index+")").text();
                                arr_spl.push(spl);
                            }
                        });

                        $("input[name='spl[]']:eq("+i+")").val(arr_spl.join());
                    }
                    <?php } ?>
                    // 입력필드추가
                    $("#add_supply_row").click(function() {
                        var $el = $("#sit_supply_frm tr:last");
                        var fld = "<tr>\n";
                        fld += "<th scope=\"row\">\n";
                        fld += "<input type=\"text\" name=\"spl_subject[]\" value=\"\" class=\"frm_input\" size=\"15\">\n";
                        fld += "</th>\n";
                        fld += "<td>\n";
                        fld += "<input type=\"text\" name=\"spl[]\" value=\"\" class=\"frm_input\" size=\"40\">\n";
                        fld += "<button type=\"button\" id=\"del_supply_row\" class=\"btn_frmline\">삭제</button>\n";
                        fld += "</td>\n";
                        fld += "</tr>";

                        $el.after(fld);

                        supply_sequence();
                    });

                    // 입력필드삭제
                    $(document).on("click", "#del_supply_row", function() {
                        $(this).closest("tr").remove();

                        supply_sequence();
                    });

                    // 옵션목록생성
                    $("#supply_table_create").click(function() {
                        var it_id = $.trim($("input[name=it_id]").val());
                        var subject = new Array();
                        var supply = new Array();
                        var subj, spl;
                        var count = 0;
                        var $el_subj = $("input[name='spl_subject[]']");
                        var $el_spl = $("input[name='spl[]']");
                        var $supply_table = $("#sit_option_addfrm");

                        $el_subj.each(function(index) {
                            subj = $.trim($(this).val());
                            spl = $.trim($el_spl.eq(index).val());

                            if(subj && spl) {
                                subject.push(subj);
                                supply.push(spl);
                                count++;
                            }
                        });

                        if(!count) {
                            alert("추가옵션명과 추가옵션항목을 입력해 주십시오.");
                            return false;
                        }

                        $.post(
                            "<?php echo G5_URL; ?>/rb/rb.mod/partner/itemsupply.php",
                            { it_id: it_id, w: "<?php echo $w; ?>", 'subject[]': subject, 'supply[]': supply },
                            function(data) {
                                $supply_table.empty().html(data);
                            }
                        );
                    });

                    // 모두선택
                    $(document).on("click", "input[name=spl_chk_all]", function() {
                        if($(this).is(":checked")) {
                            $("input[name='spl_chk[]']").attr("checked", true);
                        } else {
                            $("input[name='spl_chk[]']").attr("checked", false);
                        }
                    });

                    // 선택삭제
                    $(document).on("click", "#sel_supply_delete", function() {
                        var $el = $("input[name='spl_chk[]']:checked");
                        if($el.length < 1) {
                            alert("삭제하려는 옵션을 하나 이상 선택해 주십시오.");
                            return false;
                        }

                        $el.closest("tr").remove();
                    });

                    // 일괄적용
                    $(document).on("click", "#spl_value_apply", function() {
                        if($(".spl_com_chk:checked").length < 1) {
                            alert("일괄 수정할 항목을 하나이상 체크해 주십시오.");
                            return false;
                        }

                        var spl_price = $.trim($("#spl_com_price").val());
                        var spl_stock = $.trim($("#spl_com_stock").val());
                        var spl_noti = $.trim($("#spl_com_noti").val());
                        var spl_use = $("#spl_com_use").val();
                        var $el = $("input[name='spl_chk[]']:checked");

                        // 체크된 옵션이 있으면 체크된 것만 적용
                        if($el.length > 0) {
                            var $tr;
                            $el.each(function() {
                                $tr = $(this).closest("tr");

                                if($("#spl_com_price_chk").is(":checked"))
                                    $tr.find("input[name='spl_price[]']").val(spl_price);

                                if($("#spl_com_stock_chk").is(":checked"))
                                    $tr.find("input[name='spl_stock_qty[]']").val(spl_stock);

                                if($("#spl_com_noti_chk").is(":checked"))
                                    $tr.find("input[name='spl_noti_qty[]']").val(spl_noti);

                                if($("#spl_com_use_chk").is(":checked"))
                                    $tr.find("select[name='spl_use[]']").val(spl_use);
                            });
                        } else {
                            if($("#spl_com_price_chk").is(":checked"))
                                $("input[name='spl_price[]']").val(spl_price);

                            if($("#spl_com_stock_chk").is(":checked"))
                                $("input[name='spl_stock_qty[]']").val(spl_stock);

                            if($("#spl_com_noti_chk").is(":checked"))
                                $("input[name='spl_noti_qty[]']").val(spl_noti);

                            if($("#spl_com_use_chk").is(":checked"))
                                $("select[name='spl_use[]']").val(spl_use);
                        }
                    });
                });

                function supply_sequence()
                {
                    var $tr = $("#sit_supply_frm tr");
                    var seq;
                    var th_label, td_label;

                    $tr.each(function(index) {
                        seq = index + 1;
                        $(this).find("th label").attr("for", "spl_subject_"+seq).text('');
                        $(this).find("th input").attr("id", "spl_subject_"+seq);
                        $(this).find("td label").attr("for", "spl_item_"+seq);
                        $(this).find("td label b").text("추가"+seq+" 항목");
                        $(this).find("td input").attr("id", "spl_item_"+seq);
                    });
                }
                </script>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>


<section id="anc_sitfrm_sendcost">
    <h2 class="h2_frm">배송비</h2>
    <?php echo $pg_anchor; ?>
    <div class="local_desc02 local_desc">
        <p>쇼핑몰설정 &gt; 배송비유형 설정보다 <strong>개별상품 배송비설정이 우선</strong> 적용됩니다.</p>
    </div>

    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>배송비 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
            <tr>
                <th scope="row"><label for="it_sc_type">배송비 유형</label></th>
                <td>
                    <?php echo help("배송비 유형을 선택하면 자동으로 항목이 변환됩니다."); ?>
                    <select name="it_sc_type" id="it_sc_type" class="select">
                        <!--
                        <option value="0"<?php echo get_selected('0', $it['it_sc_type']); ?>>쇼핑몰 기본설정 사용</option>
                        <option value="2"<?php echo get_selected('2', $it['it_sc_type']); ?>>조건부 무료배송</option>
                        -->
                        <option value="1"<?php echo get_selected('1', $it['it_sc_type']); ?>>무료배송</option>
                        <option value="3"<?php echo get_selected('3', $it['it_sc_type']); ?>>유료배송</option>
                        <option value="4"<?php echo get_selected('4', $it['it_sc_type']); ?>>수량별 부과</option>
                    </select>
                </td>
            </tr>
            <tr id="sc_con_method">
                <th scope="row"><label for="it_sc_method">배송비 결제</label></th>
                <td>
                    <select name="it_sc_method" id="it_sc_method" class="select">
                        <option value="0"<?php echo get_selected('0', $it['it_sc_method']); ?>>선불</option>
                        <option value="1"<?php echo get_selected('1', $it['it_sc_method']); ?>>착불</option>
                        <option value="2"<?php echo get_selected('2', $it['it_sc_method']); ?>>사용자선택</option>
                    </select>
                </td>
            </tr>
            <tr id="sc_con_basic">
                <th scope="row"><label for="it_sc_price">기본배송비</label></th>
                <td>
                    <?php echo help("무료배송 이외의 설정에 적용되는 배송비 금액입니다."); ?>
                    <input type="text" name="it_sc_price" value="<?php echo $it['it_sc_price']; ?>" id="it_sc_price" class="frm_input" size="8"> 원
                </td>
            </tr>
            <tr id="sc_con_minimum">
                <th scope="row"><label for="it_sc_minimum">배송비 상세조건</label></th>
                <td>
                    주문금액 <input type="text" name="it_sc_minimum" value="<?php echo $it['it_sc_minimum']; ?>" id="it_sc_minimum" class="frm_input" size="8"> 이상 무료 배송
                </td>
            </tr>
            <tr id="sc_con_qty">
                <th scope="row"><label for="it_sc_qty">배송비 상세조건</label></th>
                <td>
                    <?php echo help("상품의 주문 수량에 따라 배송비가 부과됩니다. 예를 들어 기본배송비가 3,000원 수량을 3으로 설정했을 경우 상품의 주문수량이 5개이면 6,000원 배송비가 부과됩니다."); ?>
                    주문수량 <input type="text" name="it_sc_qty" value="<?php echo $it['it_sc_qty']; ?>" id="it_sc_qty" class="frm_input" size="8"> 마다 배송비 부과
                </td>
            </tr>
        </tbody>
        </table>
    </div>

    <script>
    $(function() {
        <?php
        switch($it['it_sc_type']) {
            case 1:
                echo '$("#sc_con_method").hide();'.PHP_EOL;
                echo '$("#sc_con_basic").hide();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","1");'.PHP_EOL;
                break;
            case 2:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").show();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","4");'.PHP_EOL;
                break;
            case 3:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","3");'.PHP_EOL;
                break;
            case 4:
                echo '$("#sc_con_method").show();'.PHP_EOL;
                echo '$("#sc_con_basic").show();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").show();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","4");'.PHP_EOL;
                break;
            default:
                echo '$("#sc_con_method").hide();'.PHP_EOL;
                echo '$("#sc_con_basic").hide();'.PHP_EOL;
                echo '$("#sc_con_minimum").hide();'.PHP_EOL;
                echo '$("#sc_con_qty").hide();'.PHP_EOL;
                echo '$("#sc_grp").attr("rowspan","2");'.PHP_EOL;
                break;
        }
        ?>
        $("#it_sc_type").change(function() {
            var type = $(this).val();

            switch(type) {
                case "1":
                    $("#sc_con_method").hide();
                    $("#sc_con_basic").hide();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","1");
                    break;
                case "2":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").show();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","4");
                    break;
                case "3":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","3");
                    break;
                case "4":
                    $("#sc_con_method").show();
                    $("#sc_con_basic").show();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").show();
                    $("#sc_grp").attr("rowspan","4");
                    break;
                default:
                    $("#sc_con_method").hide();
                    $("#sc_con_basic").hide();
                    $("#sc_con_minimum").hide();
                    $("#sc_con_qty").hide();
                    $("#sc_grp").attr("rowspan","1");
                    break;
            }
        });
    });
    </script>
</section>


<section id="anc_sitfrm_img">
    <h2 class="h2_frm">이미지</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>이미지 업로드</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <?php for($i=1; $i<=10; $i++) { ?>
        <tr>
            <th scope="row"><label for="it_img<?php echo $i; ?>">이미지 <?php echo $i; ?></label></th>
            <td>
                <input type="file" name="it_img<?php echo $i; ?>" id="it_img<?php echo $i; ?>">
                <?php
                $it_img = G5_DATA_PATH.'/item/'.$it['it_img'.$i];
                $it_img_exists = run_replace('shop_item_image_exists', (is_file($it_img) && file_exists($it_img)), $it, $i);

                if($it_img_exists) {
                    $thumb = get_it_thumbnail($it['it_img'.$i], 25, 25);
                    $img_tag = run_replace('shop_item_image_tag', '<img src="'.G5_DATA_URL.'/item/'.$it['it_img'.$i].'" class="shop_item_preview_image" >', $it, $i);
                ?>
                <span class="sound_only">이미지 <?php echo $i; ?> </span>
                <input type="checkbox" name="it_img<?php echo $i; ?>_del" id="it_img<?php echo $i; ?>_del" value="1">
                <label for="it_img<?php echo $i; ?>_del">파일삭제</label>
                <span class="sit_wimg_limg<?php echo $i; ?>"><?php echo $thumb; ?></span>
                <div id="limg<?php echo $i; ?>" class="banner_or_img">
                    <?php echo $img_tag; ?>
                    <button type="button" class="sit_wimg_close">닫기</button>
                </div>
                <script>
                $('<button type="button" id="it_limg<?php echo $i; ?>_view" class="btn_frmline sit_wimg_view">이미지<?php echo $i; ?> 확인</button>').appendTo('.sit_wimg_limg<?php echo $i; ?>');
                </script>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        </table>
    </div>
</section>






<section id="anc_sitfrm_optional">
    <h2 class="h2_frm">상세설명설정</h2>
    <?php echo $pg_anchor; ?>

    <div class="tbl_head01 tbl_wrap">
        <table>
        <caption>상세설명설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
            <col class="grid_3">
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">상품상단내용</th>
            <td><?php echo help("상품상세설명 페이지 상단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_head_html', get_text(html_purifier($it['it_head_html']), 0)); ?></td>
        </tr>
        <tr>
            <th scope="row">상품하단내용</th>
            <td><?php echo help("상품상세설명 페이지 하단에 출력하는 HTML 내용입니다."); ?><?php echo editor_html('it_tail_html', get_text(html_purifier($it['it_tail_html']), 0)); ?></td>
        </tr>

        </tbody>
        </table>
    </div>
</section>



<div class="btn_fixed_top">
    <a href="./partner.php?main=<?php echo $main ?>&sub=itemlist&<?php echo $qstr; ?>" class="btn btn_02">목록</a>
    <a href="<?php echo shop_item_url($it_id); ?>" class="btn_02  btn">상품보기</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
</div>
</form>


<script>
var f = document.fitemform;

<?php if ($w == 'u') { ?>
$(".banner_or_img").addClass("sit_wimg");
$(function() {
    $(".sit_wimg_view").bind("click", function() {
        var sit_wimg_id = $(this).attr("id").split("_");
        var $img_display = $("#"+sit_wimg_id[1]);

        $img_display.toggle();

        if($img_display.is(":visible")) {
            $(this).text($(this).text().replace("확인", "닫기"));
        } else {
            $(this).text($(this).text().replace("닫기", "확인"));
        }

        var $img = $("#"+sit_wimg_id[1]).children("img");
        var width = $img.width();
        var height = $img.height();
        if(width > 700) {
            var img_width = 700;
            var img_height = Math.round((img_width * height) / width);

            $img.width(img_width).height(img_height);
        }
    });
    $(".sit_wimg_close").bind("click", function() {
        var $img_display = $(this).parents(".banner_or_img");
        var id = $img_display.attr("id");
        $img_display.toggle();
        var $button = $("#it_"+id+"_view");
        $button.text($button.text().replace("닫기", "확인"));
    });
});
<?php } ?>

function fitemformcheck(f)
{
    if (!f.ca_id.value) {
        alert("기본분류를 선택하십시오.");
        f.ca_id.focus();
        return false;
    }

    if (f.w.value == "") {
        var error = "";
        $.ajax({
            url: "./ajax.it_id.php",
            type: "POST",
            data: {
                "it_id": f.it_id.value
            },
            dataType: "json",
            async: false,
            cache: false,
            success: function(data, textStatus) {
                error = data.error;
            }
        });

        if (error) {
            alert(error);
            return false;
        }
    }

    

    if(parseInt(f.it_sc_type.value) > 1) {
        if(!f.it_sc_price.value || f.it_sc_price.value == "0") {
            alert("기본배송비를 입력해 주십시오.");
            return false;
        }

        if(f.it_sc_type.value == "2" && (!f.it_sc_minimum.value || f.it_sc_minimum.value == "0")) {
            alert("배송비 상세조건의 주문금액을 입력해 주십시오.");
            return false;
        }

        if(f.it_sc_type.value == "4" && (!f.it_sc_qty.value || f.it_sc_qty.value == "0")) {
            alert("배송비 상세조건의 주문수량을 입력해 주십시오.");
            return false;
        }
    }



    <?php echo get_editor_js('it_explan'); ?>
    <?php echo get_editor_js('it_head_html'); ?>
    <?php echo get_editor_js('it_tail_html'); ?>

    return true;
}

function categorychange(f)
{
    var idx = f.ca_id.value;

    if (f.w.value == "" && idx)
    {
        f.it_use.checked = ca_use[idx] ? true : false;
        f.it_stock_qty.value = ca_stock_qty[idx];
        f.it_sell_email.value = ca_sell_email[idx];
    }
}

categorychange(document.fitemform);
</script>

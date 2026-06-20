<?php
$sub_menu = '000651';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "r");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

$sql = " select * from rb_partner limit 1";
$pa = sql_fetch($sql);

$g5['title'] = '입점사 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 목록처리
$where = " where mb_partner > 0 and ";
$sql_search = "";

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if ($save_stx != $stx)
        $page = 1;
} else { 
    $sql_search .= " where mb_partner > 0 ";
}

if (!$sst) {
    $sst = "mb_partner_add_time";
    $sod = "desc";
}


$sql_common = " from {$g5['member_table']} ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql_list = " select count(*) as cnt " . $sql_common;
$list = sql_fetch($sql_list);
$total_count = isset($list['cnt']) ? $list['cnt'] : 0;

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql_is  = " select *
          $sql_common 
          order by $sst $sod 
          limit $from_record, $rows ";
$result_is = sql_query($sql_is);

$qstr .= ($qstr ? '&amp;' : '').'save_stx='.$stx;
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

?>


<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">


<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="mb_id" <?php echo get_selected($sfl, 'mb_id'); ?>>아이디</option>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="frm_input required">
<input type="submit" value="검색" class="btn_submit"> <?php echo $listall ?> 
<span class="btn_ov01"><span class="ov_txt">총 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</form>


<form name="fitemqalist" method="post" action="./partner_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
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
        <th scope="col">레벨</th>
        <th scope="col">수수료(%)</th>
        <th scope="col">정산계좌</th>
        <th scope="col">연락처</th>
        <th scope="col">이메일</th>
        <th scope="col"><?php echo subject_sort_link('mb_partner_add_time'); ?>신청일시</a></th>
        <th scope="col"><?php echo subject_sort_link('mb_partner'); ?>승인여부</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $rowss=sql_fetch_array($result_is); $i++) {
        $mbx = get_member($rowss['mb_id']);
        $names = isset($mbx['mb_nick']) ? get_text($mbx['mb_nick']) : '';
        
        if($rowss['mb_partner'] == 2) {
            $mb_partner = "승인";
        } else { 
            $mb_partner = "대기";
        }
        

        $bg = 'bg'.($i%2);
     ?>
    <tr style="background-color:#fff;">

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="mb_id[<?php echo $i; ?>]" value="<?php echo $rowss['mb_id']; ?>">
        </td>
        <td class="" nowrap>
            <a href="../member_form.php?w=u&mb_id=<?php echo $rowss['mb_id'] ?>"><?php echo $names; ?> (<?php echo $rowss['mb_id']; ?>)</a>
        </td>
        <td class="" nowrap><?php echo $rowss['mb_level']; ?></td>
        <td class="td_mng td_mng_s" nowrap><input type="number" name="mb_ssr[<?php echo $i; ?>]" value="<?php echo $rowss['mb_ssr']; ?>" class="tbl_input sit_qty"></td>
        <td class="" nowrap>
        <?php 
        if(isset($rowss['mb_bank']) && $rowss['mb_bank']) { 
            echo $rowss['mb_bank'];
        } else {
            echo "<span style='color:#ff0000'>정산계좌 없음</span>";
        }
        ?>
        </td>
        <td class="" nowrap><?php echo $rowss['mb_hp']; ?></td>
        <td class="" nowrap><?php echo $rowss['mb_email']; ?></td>
        <td class="" nowrap><?php echo $rowss['mb_partner_add_time']; ?></td>
        <td class="" nowrap><?php echo $mb_partner; ?></td>
        <td class="td_datetime td_mng td_mng_s" nowrap>
           
            <?php if($rowss['mb_partner'] != 2) { ?>
            <a id="data_btn_y"  href="javascript:add_y('<?php echo $rowss['mb_id']; ?>');" class="btn btn_01" data-type="승인">승인</a>
            <?php } else { ?>
            <a id="data_btn_n" href="javascript:add_n('<?php echo $rowss['mb_id']; ?>');" class="btn btn_03" data-type="반려">반려</a>
            <?php } ?>

        </td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="10" class="empty_table"><span>데이터가 없습니다.</span></td></tr>';
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
    

function add_y(mb_id) {

    if(confirm('해당 신청건을 승인처리 합니다. 계속 하시겠습니까?')) {

        $.ajax({
            url:'<?php echo G5_URL ?>/rb/rb.lib/ajax.partner_add.php',
            type:'post', // 전송방식
            dataType:'json',
            data:{
                "mb_partner" : "2",
                "mb_id" : mb_id
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
    
    
function add_n(mb_id) {

    if(confirm('해당 신청건을 반려처리 합니다. 계속 하시겠습니까?\n반려 처리된 회원은 다시 신청할 수 있으며, 레벨 조정이 있는 경우 회원가입시 설정된 레벨로 되돌립니다.')) {

        $.ajax({
            url:'<?php echo G5_URL ?>/rb/rb.lib/ajax.partner_add.php',
            type:'post', // 전송방식
            dataType:'json',
            data:{
                "mb_partner" : "0",
                "mb_id" : mb_id
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
            if(!confirm("선택된 회원을 일괄 승인처리 합니다.\n승인 처리된 회원은 입점사 전용시스템을 이용할 수 있습니다.\n계속 하시겠습니까?")) {
                return false;
            }
        }
    }
    
    if(document.pressed  == "선택반려") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 회원을 일괄 반려처리 합니다.\n반려 처리된 회원은 다시 신청할 수 있으며, 레벨 조정이 있는 경우 회원가입시 설정된 레벨로 되돌립니다.\n계속 하시겠습니까?")) {
                return false;
            }
        }
    }
    
    if(document.pressed  == "선택수정") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        } else {
            if(!confirm("선택된 회원의 수수료 정보를 변경합니다.\n계속 하시겠습니까?")) {
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
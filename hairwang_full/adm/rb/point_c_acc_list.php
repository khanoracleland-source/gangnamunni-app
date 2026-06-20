<?php
$sub_menu = "000710";
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$sql_common = " from rb_point_c_acc ";
$sql_search = " where (1) ";

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        default:
            $sql_search .= " ({$sfl} like '%{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}


if ($sst) {
    $sql_order = " order by {$sst} {$sod} ";
} else {
    $sql_order = " order by p_id desc ";
}

$sql = " select count(*) as cnt {$sql_common} {$sql_search} {$sql_order} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$sql1 = " select count(*) as cnt {$sql_common} where p_status = '접수' {$sql_order} ";
$row1 = sql_fetch($sql1);
$total_count1 = $row1['cnt'];

$sql2 = " select count(*) as cnt {$sql_common} where p_status = '완료' {$sql_order} ";
$row2 = sql_fetch($sql2);
$total_count2 = $row2['cnt'];

$sql3 = " select count(*) as cnt {$sql_common} where p_status = '취소' {$sql_order} ";
$row3 = sql_fetch($sql3);
$total_count3 = $row3['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) {
    $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
}
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql = " select * {$sql_common} {$sql_search} {$sql_order} limit {$from_record}, {$rows} ";
$result = sql_query($sql);

$listall = '<a href="' . $_SERVER['SCRIPT_NAME'] . '" class="ov_listall">전체</a>';

$g5['title'] = $pnt_c_name.'출금 내역';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$colspan = 10;
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
    <a href="?sfl=p_status&stx=접수" class="btn_ov01"> <span class="ov_txt">접수 </span><span class="ov_num"><?php echo number_format($total_count1) ?>건</span></a>
    <a href="?sfl=p_status&stx=완료" class="btn_ov01"> <span class="ov_txt">완료 </span><span class="ov_num"><?php echo number_format($total_count2) ?>건</span></a>
    <a href="?sfl=p_status&stx=취소" class="btn_ov01"> <span class="ov_txt">취소 </span><span class="ov_num"><?php echo number_format($total_count3) ?>건</span></a>
    
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="p_mb_id" <?php echo get_selected($sfl, "p_mb_id"); ?>>아이디</option>
        <option value="p_status" <?php echo get_selected($sfl, "p_status"); ?>>처리현황</option>
    </select>
    <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
    <input type="text" name="stx" id="stx" value="<?php echo $stx ?>" required class="required frm_input">
    <input type="submit" value="검색" class="btn_submit">
</form>


<div class="local_desc01 local_desc">
<p>최초 <strong>완료</strong> 처리시 알림이 전송 됩니다.</p>
<p>완료된 건을 다시 취소처리 또는 접수처리 하는 경우 차감된 <?php echo $pnt_c_name ?>을(를) <strong>수동으로 지급</strong> 하셔야 합니다.</p>
<p><strong>알림 여부가 완료인 건</strong>의 처리현황 변경시 현황만 변경되며, 알림 처리는 되지 않습니다.</p>
<p>처리가 되지않은 신청건이 있다면 사용자는 출금 신청을 할 수 없습니다.</p>
</div>


<form name="fmembershiplist" id="fmembershiplist" action="./point_c_acc_list_update.php" onsubmit="return fmembershiplist_submit(this);" method="post">
    <input type="hidden" name="sst" value="<?php echo $sst ?>">
    <input type="hidden" name="sod" value="<?php echo $sod ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="token" value="">

    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?> 목록</caption>
            <thead>
                <tr>
                    <th scope="col">
                        <label for="chkall" class="sound_only">전체</label>
                        <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                    </th>
                    
                    <th scope="col"><?php echo subject_sort_link('p_mb_id') ?>신청자</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_point') ?>신청금액</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_ssr') ?>수수료</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_price') ?>입금금액</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_bank') ?>출금계좌</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_time') ?>신청일시</a></th>
                    <th scope="col"><?php echo subject_sort_link('p_status') ?>처리현황</a></th>
                    <th scope="col">처리일시</a></th>
                    <th scope="col">알림/지급</a></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tot_p_point = 0;
                $tot_p_ssr = 0;
                $tot_p_price = 0;
                        
                for ($i = 0; $row = sql_fetch_array($result); $i++) {

                    $bg = 'bg' . ($i % 2);
                    $mbs = get_member($row['p_mb_id']);
                    
                    $tot_p_point += $row['p_point'];
                    $tot_p_ssr += $row['p_ssr'];
                    $tot_p_price += $row['p_price'];
                    
                    if(isset($row['p_use']) && $row['p_use'] == 1) {
                        $p_use = "완료";
                    } else { 
                        $p_use = "-";
                    }
                    
                    
                ?>

                    <tr class="<?php echo $bg; ?>">
                        
                        <td class="td_chk">
                            <input type="hidden" name="p_id[<?php echo $i ?>]" value="<?php echo $row['p_id'] ?>">
                            <input type="hidden" name="p_mb_id[<?php echo $i ?>]" value="<?php echo $row['p_mb_id'] ?>">
                            <input type="hidden" name="p_point[<?php echo $i ?>]" value="<?php echo $row['p_point'] ?>">
                            <input type="hidden" name="p_ssr[<?php echo $i ?>]" value="<?php echo $row['p_ssr'] ?>">
                            <input type="hidden" name="p_price[<?php echo $i ?>]" value="<?php echo $row['p_price'] ?>">
                            <input type="hidden" name="p_use[<?php echo $i ?>]" value="<?php echo $row['p_use'] ?>">
                            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo get_text($row['p_id']); ?></label>
                            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
                        </td>

                        <td><a href="../member_form.php?w=u&mb_id=<?php echo $row['p_mb_id'] ?>"><?php echo $mbs['mb_nick'] ?> (<?php echo $row['p_mb_id'] ?>)</a></td>
                        <td class="td_input"><?php echo number_format($row['p_point']) ?><?php echo $pnt_c_name_st ?></td>
                        <td><?php echo number_format($row['p_ssr']) ?><?php echo $pnt_c_name_st ?></td>
                        <td><?php echo number_format($row['p_price']) ?>원</td>
                        <td class="td_input"><?php echo get_text($row['p_bank']) ?></td>
                        
                        <td><?php echo $row['p_time'] ?></td>
                        <td>
                            <select name="p_status[<?php echo $i ?>]">
                            <option value="접수" <?php if($row['p_status'] == "접수") { ?>selected<?php } ?>>접수</option>
                            <option value="완료" <?php if($row['p_status'] == "완료") { ?>selected<?php } ?>>완료</option>
                            <option value="취소" <?php if($row['p_status'] == "취소") { ?>selected<?php } ?>>취소</option>
                            </select>
                        </td>
                        <td class="" nowrap style="width:160px;">
                            <?php echo isset($row['p_y_time']) ? $row['p_y_time'] : date("Y-m-d 00:00:00", time()); ?>
                        </td>
                        <td class="" nowrap>
                            <?php echo $p_use; ?>
                        </td>
                    </tr>
                <?php
                }
                if ($i == 0) {
                    echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
                }
                ?>
                <tfoot>
                <tr>
                        
                        <th class="td_chk"></th>
                        <th></th>
                        <th class=""><?php echo number_format($tot_p_point); ?><?php echo $pnt_c_name_st ?></th>
                        <th class=""><?php echo number_format($tot_p_ssr); ?><?php echo $pnt_c_name_st ?></th>
                        <th><?php echo number_format($tot_p_price); ?>원</th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </tfoot>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="submit" name="act_button" onclick="document.pressed=this.value" value="선택수정" class="btn btn_02">
        <input type="submit" name="act_button" onclick="document.pressed=this.value" value="선택삭제" class="btn btn_02">
    </div>
    
</form>


<?php
$pagelist = get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'] . '?' . $qstr . '&amp;page=');
echo $pagelist;
?>

<script>
    function fmembershiplist_submit(f) {
        if (!is_checked("chk[]")) {
            alert(document.pressed + " 하실 항목을 하나 이상 선택하세요.");
            return false;
        }

        if (document.pressed == "선택삭제") {
            if (!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
                return false;
            }
        }

        return true;
    }
</script>

<?php
require_once G5_ADMIN_PATH.'/admin.tail.php';

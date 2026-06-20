<?php
$sub_menu = "300900";  // 메뉴 번호 적절히 설정
include_once('./_common.php');

auth_check($auth[$sub_menu], 'r');

$g5['title'] = '푸시 토큰 관리';
include_once('./admin.head.php');

$sql_common = " from g5_push_tokens ";
$sql_search = " where (1) ";

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        case 'mb_id' :
            $sql_search .= " ({$sfl} = '{$stx}') ";
            break;
        default :
            $sql_search .= " ({$sfl} like '%{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}

$sql = " select count(*) as cnt {$sql_common} {$sql_search} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

$sql = " select * {$sql_common} {$sql_search} order by pt_id desc limit {$from_record}, {$rows} ";
$result = sql_query($sql);
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01">
        <span class="ov_txt">전체 토큰</span>
        <span class="ov_num"><?php echo number_format($total_count) ?>개</span>
    </span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="mb_id"<?php echo get_selected($_GET['sfl'], "mb_id"); ?>>회원아이디</option>
    <option value="pt_platform"<?php echo get_selected($_GET['sfl'], "pt_platform"); ?>>플랫폼</option>
</select>
<label for="stx" class="sound_only">검색어</label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
<input type="submit" class="btn_submit" value="검색">
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">번호</th>
        <th scope="col">회원ID</th>
        <th scope="col">플랫폼</th>
        <th scope="col">토큰</th>
        <th scope="col">등록일시</th>
        <th scope="col">사용</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_num"><?php echo $row['pt_id'] ?></td>
        <td class="td_mbid"><?php echo get_text($row['mb_id']); ?></td>
        <td class="td_device"><?php echo $row['pt_platform'] ?></td>
        <td class="td_left"><?php echo substr($row['pt_token'], 0, 30) ?>...</td>
        <td class="td_datetime"><?php echo $row['pt_datetime'] ?></td>
        <td class="td_boolean"><?php echo $row['pt_use'] ? '사용' : '중지' ?></td>
        <td class="td_mng">
            <a href="./push_test.php?pt_id=<?php echo $row['pt_id'] ?>" class="btn btn_03">테스트</a>
        </td>
    </tr>
    <?php
    }
    if ($i == 0)
        echo "<tr><td colspan='7' class=\"empty_table\">자료가 없습니다.</td></tr>";
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<?php
include_once('./admin.tail.php');
?>
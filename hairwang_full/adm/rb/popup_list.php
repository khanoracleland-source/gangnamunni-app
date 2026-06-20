<?php
$sub_menu = '000730';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "r");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_popup ", false)) {
       $query_cp2 = sql_query(" CREATE TABLE IF NOT EXISTS `rb_popup` (
        `po_id` int(11) NOT NULL AUTO_INCREMENT,
        `po_division` varchar(20) NOT NULL COMMENT '구분',
        `po_device` varchar(20) NOT NULL COMMENT '접속기기',
        `po_title` varchar(255) NOT NULL COMMENT '그룹제목',
        `po_start` datetime NOT NULL COMMENT '그룹시작일시', 
        `po_end` datetime NOT NULL COMMENT '그룹종료일시', 
        `po_time` int(11) NOT NULL COMMENT '다시열지않음 시간',
        `po_auto` int(4) NOT NULL COMMENT '자동 슬라이드 사용여부',
        `po_p1_title` varchar(255) NOT NULL COMMENT '팝업1 제목',
        `po_p1_content` text NOT NULL COMMENT '팝업1 내용',
        `po_p1_content_html` tinyint(4) NOT NULL DEFAULT '0',
        `po_p2_title` varchar(255) NOT NULL COMMENT '팝업2 제목',
        `po_p2_content` text NOT NULL COMMENT '팝업2 내용',
        `po_p2_content_html` tinyint(4) NOT NULL DEFAULT '0',
        `po_p3_title` varchar(255) NOT NULL COMMENT '팝업3 제목',
        `po_p3_content` text NOT NULL COMMENT '팝업3 내용',
        `po_p3_content_html` tinyint(4) NOT NULL DEFAULT '0',
        `po_p4_title` varchar(255) NOT NULL COMMENT '팝업4 제목',
        `po_p4_content` text NOT NULL COMMENT '팝업4 내용',
        `po_p4_content_html` tinyint(4) NOT NULL DEFAULT '0',
        `po_p5_title` varchar(255) NOT NULL COMMENT '팝업5 제목',
        `po_p5_content` text NOT NULL COMMENT '팝업5 내용',
        `po_p5_content_html` tinyint(4) NOT NULL DEFAULT '0',
        `po_datetime` datetime NOT NULL COMMENT '등록일시(변경일시)', 
        `mb_id` varchar(255) NOT NULL COMMENT '등록자', 
        PRIMARY KEY (`po_id`) 
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}

$g5['title'] = '그룹팝업 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');



// 목록처리
$where = " where mb_id != '' and ";
$sql_search = "";

if ($stx != "") {
    if ($sfl != "") {
        $sql_search .= " $where $sfl like '%$stx%' ";
        $where = " and ";
    }
    if ($save_stx != $stx)
        $page = 1;
}

if (!$sst) {
    $sst = "po_id";
    $sod = "desc";
}


$sql_common = " from rb_popup ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql_list = " select count(*) as cnt " . $sql_common;
$list = sql_fetch($sql_list);
$total_count = isset($list['cnt']) ? $list['cnt'] : 0;

$rows = 10;
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql  = " select *
          $sql_common 
          order by $sst $sod 
          limit $from_record, $rows ";
$result = sql_query($sql);

$qstr .= ($qstr ? '&amp;' : '').'save_stx='.$stx;
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<form name="flist" class="local_sch01 local_sch">
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">


<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="po_title" <?php echo get_selected($sfl, 'po_title'); ?>>그룹제목</option>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="frm_input required">
<input type="submit" value="검색" class="btn_submit"> <?php echo $listall ?> 
<span class="btn_ov01"><span class="ov_txt">총 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>
</form>

<div class="btn_fixed_top">
    <a href="./popup_form.php" class="btn_01 btn">그룹추가</a>
</div>

<div class="local_desc02 local_desc">
<p>그룹 팝업은 환경설정 > 팝업레이어관리에 등록된 팝업 위로 출력됩니다. 주의해주세요.</p>
</div>

<form name="fitemlist" method="post">
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
        <th scope="col">그룹ID</th>
        <th scope="col"><?php echo subject_sort_link('po_title'); ?>그룹제목</a></th>
        <th scope="col"><?php echo subject_sort_link('po_division'); ?>구분</a></th>
        <th scope="col"><?php echo subject_sort_link('po_device'); ?>접속기기</a></th>
        <th scope="col"><?php echo subject_sort_link('po_start'); ?>시작일시</a></th>
        <th scope="col"><?php echo subject_sort_link('po_end'); ?>종료일시</a></th>
        <!--
        <th scope="col"><?php echo subject_sort_link('po_auto'); ?>오토슬라이드</a></th>
        -->
        <th scope="col"><?php echo subject_sort_link('po_time'); ?>쿠키시간</a></th>
        <th scope="col"><?php echo subject_sort_link('po_datetime'); ?>등록(수정) 일시</a></th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        /*
        switch ($row['po_auto']) {
            case '1':
                $po_auto = '사용';
                break;
            default:
                $po_auto = '사용안함';
                break;
        }
        */
        
        switch ($row['po_device']) {
            case 'pc':
                $po_device = 'PC';
                break;
            case 'mobile':
                $po_device = '모바일';
                break;
            default:
                $po_device = '모두';
                break;
        }
        
        switch ($row['po_division']) {
            case 'comm':
                $po_division = '커뮤니티';
                break;
            case 'shop':
                $po_division = '쇼핑몰';
                break;
            default:
                $po_division = '모두';
                break;
        }
     ?>
    <tr>
        <td class="td_mng td_mns_m">
            <?php echo $row['po_id']; ?>
        </td>
        <td class="td_left">
            <?php echo $row['po_title']; ?>
        </td>
        <td class="td_device"><?php echo $po_division; ?></td>
        <td class="td_device"><?php echo $po_device; ?></td>
        <td class="td_datetime" nowrap>
            <?php echo substr($row['po_start'], 2, 14); ?>
        </td>
        <td class="td_datetime" nowrap>
            <?php echo substr($row['po_end'], 2, 14); ?>
        </td>
        <!--
        <td class="td_datetime" nowrap>
            <?php echo $po_auto; ?>
        </td>
        -->
        <td class="td_datetime" nowrap>
            <?php echo $row['po_time']; ?>시간
        </td>
        <td class="td_datetime td_left" nowrap>
            <?php echo $row['po_datetime']; ?>
        </td>
        <td class="td_mng td_mns_m">
            <a href="./popup_form.php?w=u&amp;po_id=<?php echo $row['po_id']; ?>" class="btn btn_03">수정</a>
            <a href="./popup_form_update.php?w=d&amp;po_id=<?php echo $row['po_id']; ?>" onclick="return delete_confirm(this);" class="btn btn_02">삭제</a>
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


</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>


<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
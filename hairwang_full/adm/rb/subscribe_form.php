<?php
$sub_menu = '000640';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_subscribe ", false)) {
       $query_cp = sql_query(" CREATE TABLE IF NOT EXISTS `rb_subscribe` (
        `sb_id` int(11) NOT NULL AUTO_INCREMENT,
        `sb_mb_id` varchar(20) NOT NULL DEFAULT '',
        `sb_fw_id` varchar(20) NOT NULL DEFAULT '',
        `sb_push` varchar(20) NOT NULL DEFAULT '',
        `sb_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (`sb_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}


// rb_chat_set 테이블 존재 여부 확인
$table_exists = sql_query("DESCRIBE rb_subscribe_set", false);


// 테이블이 없는 경우 생성
if (!$table_exists) {
    $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_subscribe_set` (
        `sb_use` int(4) NOT NULL DEFAULT '0' COMMENT '사용여부',
        `sb_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '등록일시(변경일시)'
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8", true);

    // 기본값 삽입
    $sql = "INSERT INTO rb_subscribe_set
            SET sb_use = '1',
                sb_datetime = '".G5_TIME_YMDHIS."'";
    sql_query($sql);
}


$sql = " select * from rb_subscribe_set limit 1";
$sb = sql_fetch($sql);

$pg_anchor = '<ul class="anchor">
    <li><a href="#anc_sb1">기본설정</a></li>
</ul>';

$g5['title'] = '구독 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

?>


<?php
// 메세지 목록처리
$where = " where sb_mb_id != '' and sb_fw_id != '' and ";
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
    $sst = "sb_id";
    $sod = "desc";
}


$sql_common = " from rb_subscribe ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql_list = " select count(*) as cnt " . $sql_common;
$list = sql_fetch($sql_list);
$total_count = isset($list['cnt']) ? $list['cnt'] : 0;

$rows = 10;
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

<style>
    .chat_list_image img {height: 40px; width: auto; border-radius: 8px;}
    .view_image img {height: 40px; width: auto; border-radius: 8px;}
    .chat_list_video {border-radius: 8px;}
    .chat_list_audio {height:40px;}
    .chat_file_icos {margin-right: 5px;}
</style>


<form name="flist" class="local_sch01 local_sch">
<!--
<input type="hidden" name="page" value="<?php echo $page; ?>">
<input type="hidden" name="save_stx" value="<?php echo $stx; ?>">


<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="me_send_mb_id" <?php echo get_selected($sfl, 'me_send_mb_id'); ?>>발송인(ID)</option>
    <option value="me_recv_mb_id" <?php echo get_selected($sfl, 'me_recv_mb_id'); ?>>수신인(ID)</option>
    <option value="me_send_ip" <?php echo get_selected($sfl, 'me_send_ip'); ?>>발송 IP</option>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx; ?>" id="stx" required class="frm_input required">
-->
<!--<input type="submit" value="검색" class="btn_submit"> --><?php echo $listall ?> <span class="btn_ov01"><span class="ov_txt">총 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>

</form>

<form name="fitemqalist" method="post" action="./subscribe_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
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
        <th scope="col"><?php echo subject_sort_link('sb_mb_id'); ?>구독</a></th>
        <th scope="col"><?php echo subject_sort_link('sb_push'); ?>알림여부</a></th>
        <th scope="col"><?php echo subject_sort_link('sb_datetime'); ?>구독일시</a></th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $rowss=sql_fetch_array($result_is); $i++) {
        if (isset($rowss['sb_mb_id']) && $rowss['sb_mb_id']) {
            $mbx1 = get_member($rowss['sb_mb_id']);
            $name1 = isset($mbx1['mb_nick']) ? get_text($mbx1['mb_nick']) : '';
        } else {          
            $name1 = '';
        }
        
        if (isset($rowss['sb_fw_id']) && $rowss['sb_fw_id']) {
            $mbx2 = get_member($rowss['sb_fw_id']);
            $name2 = isset($mbx2['mb_nick']) ? get_text($mbx2['mb_nick']) : '';
        } else {
            $name2 = '';
        }
        
        if(isset($rowss['sb_push']) && $rowss['sb_push'] == 1) {
            $sb_push = "알림수신";
        } else {
            $sb_push = "-";
        }

        $bg = 'bg'.($i%2);
     ?>
    <tr style="background-color:#fff;">

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="sb_id[<?php echo $i; ?>]" value="<?php echo $rowss['sb_id']; ?>">
        </td>
        <td class="td_left">
            <a href="../member_form.php?w=u&mb_id=<?php echo $rowss['sb_mb_id'] ?>">[<?php echo $name1; ?>]</a> 님이 <a href="../member_form.php?w=u&mb_id=<?php echo $rowss['sb_fw_id'] ?>">[<?php echo $name2; ?>]</a> 님을 구독함
        </td>
        
        <td class="td_datetime" nowrap>
            <?php echo $sb_push ?>
        </td>
        <td class="td_datetime" nowrap><?php echo $rowss['sb_datetime']; ?></td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="7" class="empty_table"><span>데이터가 없습니다.</span></td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top" style="right:60px;">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="전체삭제" onclick="document.pressed=this.value" class="btn btn_02">
</div>
</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?$qstr&amp;page="); ?>

<script>
function fitemqalist_submit(f)
{
    
    if(document.pressed == "선택삭제") {
        if (!is_checked("chk[]")) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        }
    }

    if(document.pressed  == "선택삭제") {
        if(!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
            return false;
        }
    }
    
    if(document.pressed  == "전체삭제") {
        if(!confirm("전체 데이터 및 파일을 삭제하시겠습니까?\n삭제된 데이터는 복구되지 않습니다.")) {
            return false;
        }
    }

    return true;
}


</script>















<form name="chat_form" id="chat_form" action="./subscribe_form_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="" id="token">
    
    <section id="anc_sb1">
        <h2 class="h2_frm">기본설정</h2>
        <?php echo $pg_anchor ?>

        <div class="tbl_frm01 tbl_wrap">
            <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                    <tr>
                        <th scope="row">사용여부</th>
                        <td colspan="3">
                            <?php echo help('사용여부를 설정할 수 있습니다.<br>사용시 구독기능이 활성화 됩니다.') ?>
                            <input type="checkbox" name="sb_use" value="1" id="sb_use" <?php echo isset($sb['sb_use']) && $sb['sb_use'] == 1 ? 'checked' : ''; ?>> <label for="sb_use">구독 기능을 사용 합니다.</label>　
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    

    <div class="btn_fixed_top">
        <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
    </div>
    
</form>



<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
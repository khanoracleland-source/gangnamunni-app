<?php
$sub_menu = '000620';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_chat ", false)) {
       $query_cp = sql_query(" CREATE TABLE IF NOT EXISTS `rb_chat` (
        `me_id` int(11) NOT NULL AUTO_INCREMENT,
        `me_recv_mb_id` varchar(20) NOT NULL DEFAULT '',
        `me_send_mb_id` varchar(20) NOT NULL DEFAULT '',
        `me_send_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `me_read_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `me_memo` text NOT NULL,
        `me_send_id` int(11) NOT NULL DEFAULT 0,
        `me_type` enum('send','recv') NOT NULL DEFAULT 'recv',
        `me_send_ip` varchar(100) NOT NULL DEFAULT '',
        PRIMARY KEY (`me_id`),
        KEY `me_recv_mb_id` (`me_recv_mb_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}



// rb_chat_set 테이블 존재 여부 확인
$table_exists = sql_query("DESCRIBE rb_chat_set", false);


// 테이블이 없는 경우 생성
if (!$table_exists) {
    $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_chat_set` (
        `ch_days_old` int(11) NOT NULL DEFAULT '0' COMMENT '자동삭제기간',
        `ch_max_file_size` int(20) NOT NULL DEFAULT '0' COMMENT '파일용량',
        `ch_extension` varchar(255) NOT NULL DEFAULT '' COMMENT '허용확장자',
        `ch_position` varchar(20) NOT NULL DEFAULT 'left' COMMENT '버튼포지션',
        `ch_position_x` varchar(20) NOT NULL DEFAULT '40' COMMENT 'x',
        `ch_position_y` varchar(20) NOT NULL DEFAULT '40' COMMENT 'y',
        `ch_ref_1` int(20) NOT NULL DEFAULT '0' COMMENT '기본 폴링 주기',
        `ch_ref_2` int(20) NOT NULL DEFAULT '0' COMMENT '채팅 중일때 폴링주기',
        `ch_ref_3` int(20) NOT NULL DEFAULT '0' COMMENT '동작감지 (N초동안 동작이 없다면)',
        `ch_ref_4` int(20) NOT NULL DEFAULT '0' COMMENT '동작감지 N초로 변경',
        `ch_push` int(4) NOT NULL DEFAULT '0' COMMENT '푸시알림 사용여부',
        `ch_level` int(4) NOT NULL DEFAULT '0' COMMENT '대화 사용가능 레벨',
        `ch_use` int(4) NOT NULL DEFAULT '0' COMMENT '대화 사용여부',
        `ch_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '등록일시(변경일시)'
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8", true);

    // 기본값 삽입
    $sql = "INSERT INTO rb_chat_set
            SET ch_days_old = '90',
                ch_max_file_size = '1048576',
                ch_extension = 'jpg,jpeg,png,gif,mp4,pdf,doc,docx,svg,zip,mp3,xls,xlsx,txt,alz,hwp,ico,ppt,pptx,m4a',
                ch_position = 'left',
                ch_position_x = '40',
                ch_position_y = '40',
                ch_ref_1 = '10',
                ch_ref_2 = '3',
                ch_ref_3 = '10',
                ch_ref_4 = '3',
                ch_push = '1',
                ch_level = '2',
                ch_use = '1',
                ch_datetime = '".G5_TIME_YMDHIS."'";
    sql_query($sql);
} else {
    // 테이블이 있는 경우 컬럼 체크 및 추가
    $result = sql_query("DESCRIBE rb_chat_set");

    // ch_position, ch_position_x, ch_position_y 컬럼 존재 여부 확인
    $columns = array();
    while ($row = sql_fetch_array($result)) {
        $columns[] = $row['Field'];
    }

    $altered = false;
    if (!in_array('ch_position', $columns)) {
        sql_query("ALTER TABLE `rb_chat_set` ADD `ch_position` varchar(20) NOT NULL DEFAULT 'left' COMMENT '버튼포지션'", true);
        $altered = true;
    }
    if (!in_array('ch_position_x', $columns)) {
        sql_query("ALTER TABLE `rb_chat_set` ADD `ch_position_x` varchar(20) NOT NULL DEFAULT '40' COMMENT 'x'", true);
        $altered = true;
    }
    if (!in_array('ch_position_y', $columns)) {
        sql_query("ALTER TABLE `rb_chat_set` ADD `ch_position_y` varchar(20) NOT NULL DEFAULT '40' COMMENT 'y'", true);
        $altered = true;
    }

    // 컬럼이 추가된 경우 기본값 업데이트
    if ($altered) {
        $sql = "UPDATE rb_chat_set
                SET ch_position = 'left',
                    ch_position_x = '40',
                    ch_position_y = '40'
                WHERE 1";
        sql_query($sql);
    }
}


$sql = " select * from rb_chat_set limit 1";
$ch = sql_fetch($sql);

$pg_anchor = '<ul class="anchor">
    <li><a href="#anc_ch1">기본설정</a></li>
    <li><a href="#anc_ch2">폴링설정</a></li>
</ul>';

$g5['title'] = '메세지 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$max_upload = ini_get('upload_max_filesize');
$max_post = ini_get('post_max_size');

// ini_get으로 가져온 값은 'M', 'K'와 같은 단위가 붙어 있습니다. 이를 바이트로 변환합니다.
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int) $val;

    switch($last) {
        case 'g':
            $num *= 1024;
        case 'm':
            $num *= 1024;
        case 'k':
            $num *= 1024;
    }

    return $num;
}

$max_upload_bytes = return_bytes($max_upload);
$max_post_bytes = return_bytes($max_post);

// 두 값 중 더 작은 값을 사용합니다.
$max_file_size = min($max_upload_bytes, $max_post_bytes);

// 바이트 단위를 MB로 변환합니다.
$max_file_size_mb = $max_file_size / (1024 * 1024);
?>


<?php
// 메세지 목록처리
$where = " where me_recv_mb_id != '' and ";
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
    $sst = "me_id";
    $sod = "desc";
}


$sql_common = " from rb_chat ";
$sql_common .= $sql_search;

// 테이블의 전체 레코드수만 얻음
$sql_list = " select count(*) as cnt " . $sql_common;
$list = sql_fetch($sql_list);
$total_count = isset($list['cnt']) ? $list['cnt'] : 0;

$rows = 5;
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
<input type="submit" value="검색" class="btn_submit"> <?php echo $listall ?> <span class="btn_ov01"><span class="ov_txt">총 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건 </span></span>

</form>

<form name="fitemqalist" method="post" action="./chat_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
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
        <th scope="col"><?php echo subject_sort_link('me_send_mb_id'); ?>보냄</a></th>
        <th scope="col"><?php echo subject_sort_link('me_recv_mb_id'); ?>받음</a></th>
        <th scope="col"><?php echo subject_sort_link('me_memo'); ?>내용</a></th>
        <th scope="col"><?php echo subject_sort_link('me_send_datetime'); ?>발송시간</a></th>
        <th scope="col"><?php echo subject_sort_link('me_read_datetime'); ?>수신시간</a></th>
        <th scope="col"><?php echo subject_sort_link('me_send_ip'); ?>발송IP</a></th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $rowss=sql_fetch_array($result_is); $i++) {
        $mbx1 = get_member($rowss['me_send_mb_id']);
        $mbx2 = get_member($rowss['me_recv_mb_id']);
        $name1 = get_text($mbx1['mb_nick']);
        $name2 = get_text($mbx2['mb_nick']);

        $bg = 'bg'.($i%2);
     ?>
    <tr style="background-color:#fff;">

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="me_id[<?php echo $i; ?>]" value="<?php echo $rowss['me_id']; ?>">
        </td>
        <td class="td_datetime" nowrap><a href="../member_form.php?w=u&mb_id=<?php echo $rowss['me_send_mb_id'] ?>"><?php echo $name1; ?></a></td>
        <td class="td_datetime" nowrap><a href="../member_form.php?w=u&mb_id=<?php echo $rowss['me_recv_mb_id'] ?>"><?php echo $name2; ?></a></td>
        <td class="td_left">
            <?php echo $rowss['me_memo']; ?>
        </td>
        
        <td class="td_datetime" nowrap><?php echo $rowss['me_send_datetime']; ?></td>
        <td class="td_datetime" nowrap><?php echo $rowss['me_read_datetime']; ?></td>
        <td class="td_datetime" nowrap><?php echo $rowss['me_send_ip']; ?></td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="7" class="empty_table"><span>메세지가 없습니다.</span></td></tr>';
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















<form name="chat_form" id="chat_form" action="./chat_form_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="" id="token">
    
    <section id="anc_ch1">
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
                            <?php echo help('사용여부를 설정할 수 있습니다.<br>사용시 전체페이지 우측 하단에 버튼이 생성 됩니다.') ?>
                            <input type="checkbox" name="ch_use" value="1" id="ch_use" <?php echo isset($ch['ch_use']) && $ch['ch_use'] == 1 ? 'checked' : ''; ?>> <label for="ch_use">메세지 기능을 사용 합니다.</label>　
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">위치</th>
                        <td colspan="3">
                            <?php echo help('모바일 여백은 지정된 위치에서 x,y 20px 으로 고정 됩니다.') ?>
                           
                            <select name="ch_position" id="ch_position">
                                <option value="">위치 선택</option>
                                <option value="left" <?php echo get_selected(isset($ch['ch_position']) ? $ch['ch_position'] : '', 'left'); ?>>좌측하단</option>
                                <option value="right" <?php echo get_selected(isset($ch['ch_position']) ? $ch['ch_position'] : '', 'right'); ?>>우측하단</option>
                            </select>　X : 
                            <input type="number" name="ch_position_x" value="<?php echo isset($ch['ch_position_x']) ? get_sanitize_input($ch['ch_position_x']) : ''; ?>" id="ch_position_x" class="frm_input required" size="5" required style="width:50px;"> px　
                            Y : <input type="number" name="ch_position_y" value="<?php echo isset($ch['ch_position_y']) ? get_sanitize_input($ch['ch_position_y']) : ''; ?>" id="ch_position_y" class="frm_input required" size="5" required style="width:50px;"> px
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">사용가능 레벨</th>
                        <td colspan="3">
                            <?php echo help('사용가능한 레벨을 설정할 수 있습니다.') ?>
                            <?php echo get_member_level_select('ch_level', 1, $member['mb_level'], $ch['ch_level']) ?> 레벨 부터 사용가능
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">자동삭제</th>
                        <td colspan="3">
                            <?php echo help('오래된 메세지를 자동 삭제할 수 있습니다.<br>0일 경우 삭제하지 않습니다.') ?>
                            <input type="number" name="ch_days_old" value="<?php echo isset($ch['ch_days_old']) ? get_sanitize_input($ch['ch_days_old']) : ''; ?>" id="ch_days_old" class="frm_input required" size="10" required style="width:50px;"> 일이 지난 메세지는 자동 삭제합니다.
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">파일전송 크기</th>
                        <td colspan="3">
                            <?php echo help('1회 파일 전송시 허용할 파일 전송 크기를 설정할 수 있습니다. (1MB = 1048576)<br>0일 경우 파일 전송이 불가능 합니다.<br>현재 서버에서 허용하는 최대 크기는 <b>'.$max_file_size_mb.'Mb ('.$max_file_size.' Bytes)</b> 입니다.'); ?>
                            <input type="number" name="ch_max_file_size" value="<?php echo isset($ch['ch_max_file_size']) ? get_sanitize_input($ch['ch_max_file_size']) : ''; ?>" id="ch_max_file_size" class="frm_input required" size="10" required style="width:100px;"> Bytes
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">업로드 가능 확장자</th>
                        <td colspan="3">
                            <?php echo help('전송 가능한 파일의 확장자를 설정해주세요. 콤마(,)로 구분해주세요.<br><b>공백(스페이스바)이 들어가지 않도록 주의해주세요.</b>'); ?>
                            <input type="text" name="ch_extension" value="<?php echo isset($ch['ch_extension']) ? get_sanitize_input($ch['ch_extension']) : ''; ?>" id="ch_extension" class="frm_input required" size="100" required>
                        </td>
                    </tr>
                    <?php if(isset($app['ap_title']) && $app['ap_title'] && isset($app['ap_pid']) && $app['ap_pid'] && isset($app['ap_key']) && $app['ap_key']) { ?>
                    <tr>
                        <th scope="row">푸시알림 사용여부</th>
                        <td colspan="3">
                            <?php echo help('새로운 메세지를 받을때 푸시알림을 보낼 수 있습니다.') ?>
                            <input type="checkbox" name="ch_push" value="1" id="ch_push" <?php echo isset($ch['ch_push']) && $ch['ch_push'] == 1 ? 'checked' : ''; ?>> <label for="ch_push">푸시 알림을 사용 합니다.</label>　
                        </td>
                    </tr>
                    <?php } else { ?>
                    <tr>
                        <th scope="row">푸시알림 사용여부</th>
                        <td colspan="3">
                            앱 정보가 없습니다.
                        </td>
                    </tr>
                    <?php } ?>
                    
                </tbody>
            </table>
        </div>
    </section>
    
    
    <section id="anc_ch2">
        <h2 class="h2_frm">폴링 설정</h2>
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
                        <th scope="row">기본 폴링주기</th>
                        <td colspan="3">
                            <?php echo help('기본적인 폴링 주기를 설정 합니다.'); ?>
                            <input type="number" name="ch_ref_1" value="<?php echo isset($ch['ch_ref_1']) ? get_sanitize_input($ch['ch_ref_1']) : ''; ?>" id="ch_ref_1" class="frm_input required" size="5" required style="width:50px;"> 초
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">대화 사용중 폴링주기</th>
                        <td colspan="3">
                            <?php echo help('대화를 사용 중일때 폴링 주기를 설정 합니다.'); ?>
                            <input type="number" name="ch_ref_2" value="<?php echo isset($ch['ch_ref_2']) ? get_sanitize_input($ch['ch_ref_2']) : ''; ?>" id="ch_ref_2" class="frm_input required" size="5" required style="width:50px;"> 초
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">디바운싱</th>
                        <td colspan="3">
                            <?php echo help('동작감지를 통한 폴링 주기를 설정 합니다.'); ?>
                            <input type="number" name="ch_ref_3" value="<?php echo isset($ch['ch_ref_3']) ? get_sanitize_input($ch['ch_ref_3']) : ''; ?>" id="ch_ref_3" class="frm_input required" size="5" required style="width:50px;"> 초 동안 사용이 없을 경우 <input type="number" name="ch_ref_4" value="<?php echo isset($ch['ch_ref_4']) ? get_sanitize_input($ch['ch_ref_4']) : ''; ?>" id="ch_ref_4" class="frm_input required" size="5" required style="width:50px;"> 초로 폴링 주기를 변경 합니다.
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
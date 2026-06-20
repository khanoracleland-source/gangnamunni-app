<?php
$sub_menu = '000600';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_app ", false)) {
       $query_cp = sql_query(" CREATE TABLE IF NOT EXISTS `rb_app` (
        `ap_title` varchar(255) NOT NULL COMMENT 'FCM 앱 패키지명',
        `ap_pid` varchar(255) NOT NULL COMMENT 'FCM 프로젝트ID',
        `ap_key` varchar(255) NOT NULL COMMENT 'FCM 비공개키파일 파일명',
        `ap_systems_msg` varchar(20) NOT NULL COMMENT '시스템메세지 관리자 수신여부',
        `ap_btn_is` varchar(20) NOT NULL COMMENT '버튼출력여부',
        `ap_btn_url` varchar(255) NOT NULL COMMENT '버튼URL',
        `ap_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '등록일시(변경일시)'
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_app_token ", false)) {
       $query_cp2 = sql_query(" CREATE TABLE IF NOT EXISTS `rb_app_token` (
        `tk_id` int(11) NOT NULL AUTO_INCREMENT,
        `tk_token` varchar(255) NOT NULL COMMENT '토큰',
        `tk_and` varchar(255) NOT NULL COMMENT '',
        `mb_id` varchar(255) NOT NULL COMMENT '회원아이디',
        `tk_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '등록일시(변경일시)', 
        PRIMARY KEY (`tk_id`) 
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}

$sql = " select * from rb_app limit 1";
$ap = sql_fetch($sql);

$pg_anchor = '<ul class="anchor">
    <li><a href="#anc_ap1">FCM설정</a></li>
    <li><a href="#anc_ap2">기타설정</a></li>
</ul>';

$g5['title'] = '앱 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');




// 목록처리
$where = " where tk_token != '' and ";
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
    $sst = "tk_id";
    $sod = "desc";
}


$sql_common = " from rb_app_token ";
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


<form name="fitemqalist" method="post" action="./app_list_update.php" onsubmit="return fitemqalist_submit(this);" autocomplete="off">
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
        <th scope="col">토큰</th>
        <th scope="col"><?php echo subject_sort_link('tk_datetime'); ?>생성일시</a></th>
        <th scope="col">푸시 수신여부</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $rowss=sql_fetch_array($result_is); $i++) {
        $mbx = get_member($rowss['mb_id']);
        $names = isset($mbx['mb_nick']) ? get_text($mbx['mb_nick']) : '';

        $bg = 'bg'.($i%2);
     ?>
    <tr style="background-color:#fff;">

        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>"></label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i; ?>">
            <input type="hidden" name="tk_id[<?php echo $i; ?>]" value="<?php echo $rowss['tk_id']; ?>">
            <input type="hidden" name="mb_id[<?php echo $i; ?>]" value="<?php echo $rowss['mb_id']; ?>">
        </td>
        <td class="td_datetime td_left" nowrap>
            <a href="../member_form.php?w=u&mb_id=<?php echo $rowss['mb_id'] ?>"><?php echo $names; ?> (<?php echo $rowss['mb_id']; ?>)</a>
        </td>
        <td class="td_left" style="word-break: break-all;">
            <?php echo $rowss['tk_token']; ?>
        </td>
        <td class="td_datetime" nowrap><?php echo $rowss['tk_datetime']; ?></td>
        
        <td class="td_datetime" nowrap>
            <?php if(isset($mbx['mb_sms']) && $mbx['mb_sms'] == 1) { ?>
            수신
            <?php } else { ?>
            <span style="color:#ff4081;">수신거부</span>
            <?php } ?>
        </td>

    </tr>
    <?php
    }
    if ($i == 0) {
            echo '<tr><td colspan="5" class="empty_table"><span>생성된 토큰이 없습니다.</span></td></tr>';
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
        if(!confirm("전체 데이터를 삭제하시겠습니까?\n삭제된 데이터는 복구되지 않습니다.")) {
            return false;
        }
    }

    return true;
}


</script>



<form name="seo_form" id="seo_form" action="./app_form_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="" id="token">
    
    <section id="anc_ap1">
        <h2 class="h2_frm">FCM 설정</h2>
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
                        <th scope="row">FCM 패키지명</th>
                        <td colspan="3">
                            <?php echo help('FCM에 등록된 앱의 패키지명을 입력하세요. 예) com.webview.rebuilder') ?>
                            <input type="text" name="ap_title" value="<?php echo isset($ap['ap_title']) ? get_sanitize_input($ap['ap_title']) : ''; ?>" id="ap_title" class="frm_input required" size="40" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">FCM 프로젝트 ID</th>
                        <td colspan="3">
                            <?php echo help('FCM에 등록된 앱의 프로젝트 ID 를 입력하세요.') ?>
                            <input type="text" name="ap_pid" value="<?php echo isset($ap['ap_pid']) ? get_sanitize_input($ap['ap_pid']) : ''; ?>" id="ap_pid" class="frm_input required" size="100" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">FCM 비공개키파일</th>
                        <td colspan="3">
                            <?php echo help('FCM 에서 다운로드한 비공개키파일의 파일명을 입력하세요.<br>키파일은 /data/push/ 폴더로 업로드 해주세요. 예) 파일명.json') ?>
                            <input type="text" name="ap_key" value="<?php echo isset($ap['ap_key']) ? get_sanitize_input($ap['ap_key']) : ''; ?>" id="ap_key" class="frm_input required" size="40" required>
                        </td>
                    </tr>

                    
                </tbody>
            </table>
        </div>
    </section>
    
    
    <section id="anc_ap2">
        <h2 class="h2_frm">기타설정</h2>
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
                        <th scope="row">푸시알림 수신여부</th>
                        <td colspan="3">
                            <?php echo help('관리자전용 푸시알림 수신여부를 설정할 수 있습니다.<br>사용자의 푸시알림 수신여부는 회원정보수정 페이지에서 처리 합니다.') ?>
                            <input type="checkbox" name="ap_systems_msg" value="1" id="ap_systems_msg" <?php echo isset($ap['ap_systems_msg']) && $ap['ap_systems_msg'] == 1 ? 'checked' : ''; ?>> <label for="ap_systems_msg">수신함</label>　
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">하단 다운로드 버튼</th>
                        <td colspan="3">
                            <?php echo help('푸터 영역에 앱 다운로드 버튼을 출력합니다.<br>스토어배포시 : 스토어 링크를 입력하세요.<br>스토어 링크가 없는 경우 카카오로 파일 다운로드 경로를 전송 합니다.<br>전달 받으신 apk파일을 /app/app.apk 경로로 업로드해주세요.') ?>
                            <input type="checkbox" name="ap_btn_is" value="1" id="ap_btn_is" <?php echo isset($ap['ap_btn_is']) && $ap['ap_btn_is'] == 1 ? 'checked' : ''; ?>> <label for="ap_btn_is">노출</label>　
                            <input type="text" name="ap_btn_url" value="<?php echo isset($ap['ap_btn_url']) ? get_sanitize_input($ap['ap_btn_url']) : ''; ?>" id="ap_btn_url" class="frm_input" size="70" placeholder="플레이스토어 URL">
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



<section id="point_mng">
    <h2 class="h2_frm">푸시알림 발송</h2>

    <form name="fpush" method="post" id="fpush" action="./app_push_update.php" autocomplete="off">
        <input type="hidden" name="token" value="<?php echo isset($token) ? $token : ''; ?>">

        <div class="tbl_frm01 tbl_wrap">
            <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                   
                    <tr>
                        <th scope="row">참고사항</label></th>
                        <td>
                        FCM 패키지명, FCM 프로젝트 ID, FCM 비공개키파일 이 있는 경우만 발송 가능합니다.<br>
                        앱 설치 후 최초로그인 시 사용자의 토큰정보가 저장되며, 저장된 토큰이 있는 회원에게만 발송 됩니다.<br>
                        회원아이디를 입력하지 않는 경우 전체 회원에게 발송 됩니다.<br><br>
                        
                        <input type="text" name="mb_id" value="<?php echo isset($mb_id) ? get_sanitize_input($mb_id) : ''; ?>" id="mb_id" class="frm_input" size="20" placeholder="회원아이디">
                        
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="pu_title">푸시알림 제목<strong class="sound_only">필수</strong></label></th>
                        <td><input type="text" name="pu_title" id="pu_title" required class="required frm_input" size="40" placeholder="알림제목"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pu_body">푸시알림 내용<strong class="sound_only">필수</strong></label></th>
                        <td><input type="text" name="pu_body" id="pu_body" required class="required frm_input" size="80" placeholder="알림내용"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="btn_confirm01 btn_confirm">
            <input type="submit" value="발송하기" class="btn_submit btn">
        </div>

    </form>

</section>



<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
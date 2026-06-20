<?php
$sub_menu = '000730';
require_once './_common.php';
require_once G5_EDITOR_LIB;

auth_check_menu($auth, $sub_menu, "w");

$po_id = isset($_REQUEST['po_id']) ? (string)preg_replace('/[^0-9]/', '', $_REQUEST['po_id']) : 0;
$po = array(
    'po_start' => '',
    'po_end' => '',
    'po_time' => '',
    'po_title' => '',
    'po_p1_title' => '',
    'po_p1_content' => '',
    'po_p2_title' => '',
    'po_p2_content' => '',
    'po_p3_title' => '',
    'po_p3_content' => '',
    'po_p4_title' => '',
    'po_p4_content' => '',
    'po_p5_title' => '',
    'po_p5_content' => '',
    'po_division' => '',
    'po_device' => '',
);

$html_title = "그룹팝업";

if ($w == "u") {
    $html_title .= " 수정";
    $sql = " select * from rb_popup where po_id = '$po_id' ";
    $po = sql_fetch($sql);
    if (!(isset($po['po_id']) && $po['po_id'])) {
        alert("등록된 자료가 없습니다.");
    }
} else {
    $html_title .= " 등록";
    $po['po_device'] = 'both';
    $po['po_time'] = 24;
    $po['po_p1_content_html'] = 2;
    $po['po_p2_content_html'] = 2;
    $po['po_p3_content_html'] = 2;
    $po['po_p4_content_html'] = 2;
    $po['po_p5_content_html'] = 2;
}

$g5['title'] = $html_title;
require_once G5_ADMIN_PATH . '/admin.head.php';
?>

<form name="frmnewwin" action="./popup_form_update.php" onsubmit="return frmnewwin_check(this);" method="post">
    <input type="hidden" name="w" value="<?php echo $w; ?>">
    <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
    <input type="hidden" name="token" value="">

    <div class="local_desc01 local_desc">
        <p>그룹 팝업은 환경설정 > 팝업레이어관리에 등록된 팝업 위로 출력됩니다. 주의해주세요.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?></caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_division">구분</label></th>
                    <td>
                        <?php echo help("커뮤니티에 표시될 것인지 쇼핑몰에 표시될 것인지를 설정합니다."); ?>
                        <select name="po_division" id="po_division">
                            <option value="comm" <?php echo get_selected($po['po_division'], 'comm'); ?>>커뮤니티</option>
                            <?php if (defined('G5_USE_SHOP') && G5_USE_SHOP) { ?>
                                <option value="both" <?php echo get_selected($po['po_division'], 'both', true); ?>>커뮤니티와 쇼핑몰</option>
                                <option value="shop" <?php echo get_selected($po['po_division'], 'shop'); ?>>쇼핑몰</option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_device">접속기기</label></th>
                    <td>
                        <?php echo help("팝업레이어가 표시될 접속기기를 설정합니다."); ?>
                        <select name="po_device" id="po_device">
                            <option value="both" <?php echo get_selected($po['po_device'], 'both', true); ?>>PC와 모바일</option>
                            <option value="pc" <?php echo get_selected($po['po_device'], 'pc'); ?>>PC</option>
                            <option value="mobile" <?php echo get_selected($po['po_device'], 'mobile'); ?>>모바일</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_time">시간<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <?php echo help("고객이 다시 보지 않음을 선택할 시 몇 시간동안 팝업레이어를 보여주지 않을지 설정합니다."); ?>
                        <input type="text" name="po_time" value="<?php echo $po['po_time']; ?>" id="po_time" required class="frm_input required" size="5"> 시간
                    </td>
                </tr>
                <!--
                <tr>
                    <th scope="row"><label for="po_start">자동 슬라이드</label></th>
                    <td>
                        <?php echo help("체크시 해당그룹에 팝업이 1개 이상 이라면 자동 슬라이드를 적용 합니다."); ?>
                        <input type="checkbox" name="po_auto" value="1" id="po_auto" <?php if(isset($po['po_auto']) && $po['po_auto'] == 1) { ?>checked<?php } ?>>
                        <label for="po_auto">사용</label>
                    </td>
                </tr>
                -->
                <tr>
                    <th scope="row"><label for="po_start">시작일시<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_start" value="<?php echo $po['po_start']; ?>" id="po_start" required class="frm_input required" size="21" maxlength="19">
                        <input type="checkbox" name="nw_begin_chk" value="<?php echo date("Y-m-d 00:00:00", G5_SERVER_TIME); ?>" id="nw_begin_chk" onclick="if (this.checked == true) this.form.po_start.value=this.form.nw_begin_chk.value; else this.form.po_start.value = this.form.po_start.defaultValue;">
                        <label for="nw_begin_chk">시작일시를 오늘로</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nw_end_time">종료일시<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_end" value="<?php echo $po['po_end']; ?>" id="po_end" required class="frm_input required" size="21" maxlength="19">
                        <input type="checkbox" name="nw_end_chk" value="<?php echo date("Y-m-d 23:59:59", G5_SERVER_TIME + (60 * 60 * 24 * 7)); ?>" id="nw_end_chk" onclick="if (this.checked == true) this.form.po_end.value=this.form.nw_end_chk.value; else this.form.po_end.value = this.form.po_end.defaultValue;">
                        <label for="nw_end_chk">종료일시를 오늘로부터 7일 후로</label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="po_title">팝업그룹 제목<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_title" value="<?php echo get_sanitize_input($po['po_title']); ?>" id="po_title" required class="frm_input required" size="80">
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <br>
    
    <div class="local_desc01 local_desc">
        <p>해당 그룹의 첫번째 팝업을 등록할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_p1_title">팝업1 캡션<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_p1_title" value="<?php echo get_sanitize_input($po['po_p1_title']); ?>" id="po_p1_title" class="frm_input" size="30">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_p1_content">팝업1 내용</label></th>
                    <td><?php echo editor_html('po_p1_content', get_text(html_purifier($po['po_p1_content']), 0)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <br>
    <div class="local_desc01 local_desc">
        <p>해당 그룹의 두번째 팝업을 등록할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_p2_title">팝업2 캡션<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_p2_title" value="<?php echo get_sanitize_input($po['po_p2_title']); ?>" id="po_p2_title" class="frm_input" size="30">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_p2_content">팝업2 내용</label></th>
                    <td><?php echo editor_html('po_p2_content', get_text(html_purifier($po['po_p2_content']), 0)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    
    <br>
    <div class="local_desc01 local_desc">
        <p>해당 그룹의 세번째 팝업을 등록할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_p3_title">팝업3 캡션<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_p3_title" value="<?php echo get_sanitize_input($po['po_p3_title']); ?>" id="po_p3_title" class="frm_input" size="30">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_p3_content">팝업3 내용</label></th>
                    <td><?php echo editor_html('po_p3_content', get_text(html_purifier($po['po_p3_content']), 0)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <br>
    <div class="local_desc01 local_desc">
        <p>해당 그룹의 네번째 팝업을 등록할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_p4_title">팝업4 캡션<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_p4_title" value="<?php echo get_sanitize_input($po['po_p4_title']); ?>" id="po_p4_title" class="frm_input" size="30">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_p4_content">팝업4 내용</label></th>
                    <td><?php echo editor_html('po_p4_content', get_text(html_purifier($po['po_p4_content']), 0)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <br>
    <div class="local_desc01 local_desc">
        <p>해당 그룹의 다섯번째 팝업을 등록할 수 있습니다.</p>
    </div>

    <div class="tbl_frm01 tbl_wrap">
        <table>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="po_p5_title">팝업5 캡션<strong class="sound_only"> 필수</strong></label></th>
                    <td>
                        <input type="text" name="po_p5_title" value="<?php echo get_sanitize_input($po['po_p5_title']); ?>" id="po_p5_title" class="frm_input" size="30">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="po_p5_content">팝업5 내용</label></th>
                    <td><?php echo editor_html('po_p5_content', get_text(html_purifier($po['po_p5_content']), 0)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    

    <div class="btn_fixed_top">
        <a href="./popup_list.php" class=" btn btn_02">목록</a>
        <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
    </div>
</form>

<script>
    function frmnewwin_check(f) {
        errmsg = "";
        errfld = "";

        <?php echo get_editor_js('po_p1_content'); ?>
        <?php echo get_editor_js('po_p2_content'); ?>
        <?php echo get_editor_js('po_p3_content'); ?>
        <?php echo get_editor_js('po_p4_content'); ?>
        <?php echo get_editor_js('po_p5_content'); ?>

        check_field(f.po_title, "제목을 입력하세요.");

        if (errmsg != "") {
            alert(errmsg);
            errfld.focus();
            return false;
        }
        return true;
    }
</script>

<?php
require_once G5_ADMIN_PATH . '/admin.tail.php';

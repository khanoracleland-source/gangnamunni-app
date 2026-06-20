<?php
include_once(G5_ADMIN_PATH.'/admin.lib.php');


global $g5, $is_admin;

// 전체회원수
$sql = " SELECT COUNT(*) AS cnt FROM {$g5['member_table']} ";
$row = sql_fetch($sql);
$tot_cnt = $row['cnt'];

// 탈퇴회원수
$sql = " SELECT COUNT(*) AS cnt FROM {$g5['member_table']} WHERE mb_leave_date <> '' ";
$row = sql_fetch($sql);
$leave_count = $row['cnt'];

// 차단회원수
$sql = " SELECT COUNT(*) AS cnt FROM {$g5['member_table']} WHERE mb_intercept_date <> '' ";
$row = sql_fetch($sql);
$intercept_count = $row['cnt'];

$g5['title'] = '회원쪽지 발송';
include_once(G5_ADMIN_PATH.'/admin.head.php');
?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총회원수 </span><span class="ov_num"> <?php echo number_format($tot_cnt) ?>명 </span></span>
    <span class="btn_ov01"><span class="ov_txt">차단 </span><span class="ov_num"> <?php echo number_format($intercept_count) ?>명 </span></span>
    <span class="btn_ov01"><span class="ov_txt">탈퇴 </span><span class="ov_num"> <?php echo number_format($leave_count) ?>명 </span></span>
</div>

<form name="memo_form" id="memo_form" action="<?php echo G5_PLUGIN_URL; ?>/memo_send/memo_update.php" onsubmit="return memolist_submit(this);" method="post">
<input type="hidden" name="token" value="">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row">받는사람 권한(레벨)</th>
        <td>
            <input type="checkbox" id="chkall" onclick="if (this.checked) all_checked(true); else all_checked(false);"> 전체선택
            <?php for ($i=1; $i<=10; $i++) { 
                // 각 레벨별 회원 수 조회
                $sql = " SELECT COUNT(*) AS cnt FROM {$g5['member_table']} WHERE mb_level = {$i} AND mb_leave_date = '' AND mb_intercept_date = '' ";
                $row = sql_fetch($sql);
                $level_count = $row['cnt'];
            ?>
            <input type="checkbox" name="m_level[]" value="<?php echo $i; ?>" id="m_level_<?php echo $i; ?>" style="margin-left:12px"> 
            <?php echo $i; ?> <span class="level_count">(<?php echo number_format($level_count); ?>명)</span>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row">제외할 아이디</th>
        <td>
            <textarea name="exclude_mb_id" rows="3" class="frm_input" placeholder="제외할 회원 아이디를 쉼표(,)로 구분하여 입력하세요."></textarea>
            <div class="help-block">여러 아이디는 쉼표(,)로 구분하세요. 예: admin,test,test2</div>
        </td>
    </tr>
    <tr>
        <th scope="row">쪽지 내용</th>
        <td><textarea name="memo_content" required class="required" rows="5"></textarea></td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_confirm01 btn_confirm">
    <input type="submit" class="btn_submit" accesskey="s" value="발송하기">
</div>
</form>

<script>
function all_checked(sw) {
    var f = document.memo_form;

    for (var i=0; i<f.elements.length; i++) {
        if (f.elements[i].name.indexOf('m_level') !== -1)
            f.elements[i].checked = sw;
    }
}

function memolist_submit(f) {
    var chk_count = 0;
    var levels = document.getElementsByName('m_level[]');
    
    for (var i=0; i<levels.length; i++) {
        if (levels[i].checked)
            chk_count++;
    }

    if (!chk_count) {
        alert("쪽지 받는 대상을 하나 이상 선택하세요.");
        return false;
    }
    
    if (f.memo_content.value.trim() === '') {
        alert("쪽지 내용을 입력하세요.");
        f.memo_content.focus();
        return false;
    }
    
    return confirm("선택한 회원에게 쪽지를 발송하시겠습니까?");
}
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>
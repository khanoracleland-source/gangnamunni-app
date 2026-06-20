<?php
$sub_menu = "200100";
include_once('./_common.php');

auth_check($auth[$sub_menu], 'r');

$g5['title'] = '회원 레벨 아이콘 관리';
include_once('./admin.head.php');

// 레벨 아이콘 디렉토리 생성
$level_icon_dir = G5_DATA_PATH.'/member_level';
if(!is_dir($level_icon_dir)) {
    @mkdir($level_icon_dir, G5_DIR_PERMISSION);
    @chmod($level_icon_dir, G5_DIR_PERMISSION);
}

// 현재 회원 수 레벨별 통계
$level_stats = array();
for($i=1; $i<=10; $i++) {
    $row = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_level = '{$i}'");
    $level_stats[$i] = $row['cnt'];
}
?>

<div class="local_desc01 local_desc">
    <p>
        회원 레벨별 아이콘을 설정할 수 있습니다. 권장 크기는 16x16 픽셀입니다.<br>
        PNG 형식을 권장하며, 투명 배경을 사용할 수 있습니다.
    </p>
</div>

<form name="flevelform" id="flevelform" action="./member_level_config_update.php" method="post" enctype="multipart/form-data" onsubmit="return flevelform_submit(this);">
<input type="hidden" name="token" value="">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
        <col class="grid_4">
    </colgroup>
    <tbody>
    <?php 
    $level_names = array(
        1 => '일반회원',
        2 => '정회원',
        3 => '우수회원',
        4 => 'VIP회원',
        5 => 'VVIP회원',
        6 => '특별회원',
        7 => '명예회원',
        8 => '골드회원',
        9 => '다이아회원',
        10 => '최고관리자'
    );
    
    for($i=1; $i<=10; $i++) { 
        $icon_file = $level_icon_dir.'/level_'.$i.'.png';
        $icon_exists = file_exists($icon_file);
    ?>
    <tr>
        <th scope="row">
            레벨 <?php echo $i ?><br>
            <span style="font-weight:normal;color:#666;"><?php echo $level_names[$i] ?></span>
        </th>
        <td>
            <?php if($icon_exists) { ?>
            <div style="margin-bottom:5px;">
                <img src="<?php echo G5_DATA_URL ?>/member_level/level_<?php echo $i ?>.png?v=<?php echo time() ?>" alt="레벨<?php echo $i ?>" style="vertical-align:middle;">
                <label style="margin-left:10px;"><input type="checkbox" name="del_icon[<?php echo $i ?>]" value="1"> 삭제</label>
            </div>
            <?php } ?>
            <input type="file" name="level_icon[<?php echo $i ?>]" accept="image/*">
            <span class="frm_info">PNG, GIF, JPG 파일 (16x16 권장)</span>
        </td>
        <td class="td_num_c">
            회원수: <strong><?php echo number_format($level_stats[$i]) ?></strong>명
        </td>
    </tr>
    <?php } ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
</div>
</form>

<script>
function flevelform_submit(f) {
    return true;
}
</script>

<?php
include_once('./admin.tail.php');
?>
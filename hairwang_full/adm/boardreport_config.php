<?php
$sub_menu = "000910";
include_once('./_common.php');
include_once(G5_ADMIN_PATH.'/boardreport.lib.php');

auth_check_menu($auth, $sub_menu, 'w'); // 관리자 권한 체크

// 저장 처리 ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!check_admin_token()) alert('토큰 오류');

    // 안전한 값 파싱
    $get_int = function($name, $min=0, $max=1000000, $def=0){
        $v = isset($_POST[$name]) ? (int)$_POST[$name] : $def;
        if ($v < $min) $v = $min;
        if ($v > $max) $v = $max;
        return $v;
    };
    $get_str = function($name, $allow = ['emoji','fa4','fa5'], $def='emoji'){
        $v = trim($_POST[$name] ?? $def);
        return in_array($v, $allow, true) ? $v : $def;
    };
    $get_bool = function($name){ return isset($_POST[$name]) && $_POST[$name] === '1' ? 1 : 0; };

    // 값 수집
    $pairs = [
        "enabled"               => $get_bool('cf_report_enabled'),
        "hide_limit_post"       => $get_int('cf_report_hide_limit_post', 1, 9999, 5),
        "hide_limit_comment"    => $get_int('cf_report_hide_limit_comment', 1, 9999, 5),
        "lock_threshold"        => $get_int('cf_report_lock_threshold', 1, 9999, 5),
        "author_protect_level"  => $get_int('cf_report_author_protect_level', 1, 10, 9),
        "disallow_self"         => $get_bool('cf_report_disallow_self'),
        "icon_mode"             => $get_str('cf_report_icon_mode', ['emoji','fa4','fa5'], 'emoji'),
        "reason_custom_enabled" => $get_bool('cf_report_reason_custom_enabled'),
        "reason_custom_list"    => trim($_POST['cf_report_reason_custom_list'] ?? ''),
        "note"                  => trim($_POST['cf_report_note'] ?? '* 허위신고일 경우, 신고자의 서비스 활동이 제한될 수 있으니 유의하시어 신중하게 신고해주세요.')
    ];

    // UPDATE (id=1 고정)
    $set = [];
    foreach ($pairs as $k => $v) {
        $set[] = "`{$k}` = '".sql_real_escape_string($v)."'";
    }
    $sql = "UPDATE `{$report_config}` SET ".implode(',', $set)." WHERE id=1";
    sql_query($sql);

    alert('저장되었습니다.', $_SERVER['SCRIPT_NAME']);
}

// 값 로딩 -----------------------------------------------
$row = sql_fetch("SELECT * FROM `{$report_config}` WHERE id=1");

$cf = [
    'enabled'               => $row['enabled'] ?? 1,
    'hide_post'             => $row['hide_limit_post'] ?? 5,
    'hide_comment'          => $row['hide_limit_comment'] ?? 5,
    'lock_threshold'        => $row['lock_threshold'] ?? 5,
    'protect_level'         => $row['author_protect_level'] ?? 9,
    'disallow_self'         => $row['disallow_self'] ?? 1,
    'icon_mode'             => $row['icon_mode'] ?? 'emoji',
    'reason_custom_enabled' => $row['reason_custom_enabled'] ?? 0,
    'reason_custom_list'    => $row['reason_custom_list'] ?? "스팸\n욕설\n음란물\n허위정보\n기타",
    'note'                  => $row['note'] ?? '* 허위신고일 경우, 신고자의 서비스 활동이 제한될 수 있으니 유의하시어 신중하게 신고해주세요.',
];

$g5['title'] = '신고 설정';
include_once(G5_ADMIN_PATH.'/admin.head.php');
add_stylesheet('<link rel="stylesheet" href="'.G5_CSS_URL.'/report.css">', 0);
?>

<div class="report-config">
    <form method="post">
        <input type="hidden" name="token" value="<?php echo get_admin_token(); ?>">

        <div class="field grid">
            <label>신고 기능 사용</label>
            <div class="inline">
                <label><input type="checkbox" name="cf_report_enabled" value="1" <?php echo $cf['enabled']=='1'?'checked':'';?>> 사용</label>
            </div>
        </div>

        <div class="field grid">
            <label>게시글 가림 임계치</label>
            <div>
                <input type="number" name="cf_report_hide_limit_post" value="<?php echo $cf['hide_post'];?>" class="frm_input" min="1" style="width:120px"> 회
                <div class="desc">게시글에 대한 신고가 이 횟수 이상이면 일반 사용자에게 본문을 가립니다.</div>
            </div>
        </div>

        <div class="field grid">
            <label>댓글 가림 임계치</label>
            <div>
                <input type="number" name="cf_report_hide_limit_comment" value="<?php echo $cf['hide_comment'];?>" class="frm_input" min="1" style="width:120px"> 회
                <div class="desc">댓글에 대한 신고가 이 횟수 이상이면 일반 사용자에게 내용을 가립니다.</div>
            </div>
        </div>

        <div class="field grid">
            <label>자동 잠금 임계치</label>
            <div>
                <input type="number" name="cf_report_lock_threshold" value="<?php echo $cf['lock_threshold'];?>" class="frm_input" min="1" style="width:120px"> 회
                <div class="desc">도달 시 wr_report='잠금' 으로 설정됩니다.</div>
            </div>
        </div>

        <div class="field grid">
            <label>보호 레벨 (이상)</label>
            <div>
                <input type="number" name="cf_report_author_protect_level" value="<?php echo $cf['protect_level'];?>" class="frm_input" min="1" max="10" style="width:120px">
                <div class="desc">해당 레벨 이상의 작성자 글/댓글은 신고 폼 자체를 차단합니다. (예: 9)</div>
            </div>
        </div>

        <div class="field grid">
            <label>본인글 신고 금지</label>
            <div class="inline">
                <label><input type="checkbox" name="cf_report_disallow_self" value="1" <?php echo $cf['disallow_self']=='1'?'checked':'';?>> 사용</label>
                <div class="desc">작성자 본인이 자기 글/댓글을 신고하는 것을 막습니다.</div>
            </div>
        </div>

        <div class="field grid">
            <label>아이콘 모드</label>
            <div class="inline">
                <label><input type="radio" name="cf_report_icon_mode" value="emoji" <?php echo $cf['icon_mode']=='emoji'?'checked':'';?>> Emoji</label>
                <label><input type="radio" name="cf_report_icon_mode" value="fa4" <?php echo $cf['icon_mode']=='fa4'?'checked':'';?>> Font Awesome 4</label>
                <label><input type="radio" name="cf_report_icon_mode" value="fa5" <?php echo $cf['icon_mode']=='fa5'?'checked':'';?>> Font Awesome 5/6</label>
            </div>
        </div>

        <div class="field grid">
            <label>사유 커스텀 목록</label>
            <div>
                <label class="inline" style="margin-bottom:8px">
                    <input type="checkbox" name="cf_report_reason_custom_enabled" value="1" <?php echo $cf['reason_custom_enabled']=='1'?'checked':'';?>>
                    직접 입력 목록 사용
                </label>
                <textarea name="cf_report_reason_custom_list" placeholder="한 줄에 한 개씩 입력 (예: 스팸, 욕설, 음란물, 허위정보, 기타)"><?php echo get_text($cf['reason_custom_list']);?></textarea>
                <div class="desc">체크 시 아래 목록이 신고 사유 버튼으로 렌더링 됩니다. (최대 10개 권장)</div>
            </div>
        </div>

        <div class="field grid">
            <label>하단 안내문</label>
            <div>
                <textarea name="cf_report_note"><?php echo get_text($cf['note']);?></textarea>
                <div class="desc">신고 모달 하단에 노출되는 주의 문구입니다.</div>
            </div>
        </div>

        <div class="btn_fixed_top">
            <button type="submit" class="btn btn_submit">저장</button>
        </div>
    </form>
</div>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
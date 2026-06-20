<?php
include_once('./_common.php');
include_once(G5_ADMIN_PATH.'/boardreport.lib.php');

// 로그인 필수
if (!$is_member) alert_close("회원만 신고할 수 있습니다.");

// 설정 로드
$conf = g5_report_conf();

// 기능 비활성화면 즉시 종료
if (empty($conf['enabled'])) {
    ?>
    <div class="report-guard" data-guard="1">
        <h3 style="margin:0 0 10px;font-size:18px;">신고 불가</h3>
        <p style="margin:0 0 16px;line-height:1.6;color:#4b5563">
            신고 기능이 현재 비활성화되어 있습니다.
        </p>
        <div class="report-btns" style="display:flex;gap:10px;justify-content:center">
            <button type="button" class="report_btn_submit" onclick="closeReportModal()">확인</button>
        </div>
    </div>
    <?php
    exit;
}

// 파라미터
$bo_table   = trim($_GET['bo_table'] ?? '');
$wr_id      = (int) ($_GET['wr_id'] ?? 0);
$comment_id = (int) ($_GET['comment_id'] ?? 0);

// bo_table 화이트리스트
if (!preg_match('/^[A-Za-z0-9_]+$/', $bo_table)) alert_close("잘못된 요청입니다.(bo_table)");

// 게시판/글 확인
$board = sql_fetch("SELECT * FROM `{$g5['board_table']}` WHERE bo_table = '".sql_real_escape_string($bo_table)."'");
if (!$board['bo_table']) alert_close("존재하지 않는 게시판입니다.");

$write_table = $g5['write_prefix'] . $bo_table;
if (!sql_query("DESCRIBE `{$write_table}`", false)) alert_close("대상 테이블을 찾을 수 없습니다.");

$write = sql_fetch("SELECT * FROM `{$write_table}` WHERE wr_id = '{$wr_id}'");
if (!$write['wr_id']) alert_close("해당 글이 존재하지 않습니다.");

$is_comment = ($comment_id > 0);
if ($is_comment) {
    $comment = sql_fetch("SELECT * FROM `{$write_table}` WHERE wr_id = '{$comment_id}' AND wr_is_comment = 1");
    if (!$comment['wr_id']) alert_close("해당 댓글이 존재하지 않습니다.");
}

$target_name  = $is_comment ? "댓글" : "게시글";
$author_mb_id = $is_comment ? $comment['mb_id'] : $write['mb_id'];

// 본인글 신고 금지(설정에 따름)
if (!empty($conf['disallow_self']) && $author_mb_id && $author_mb_id === $member['mb_id']) {
    ?>
    <div class="report-guard" data-guard="1">
        <h3 style="margin:0 0 10px;font-size:18px;">신고 불가</h3>
        <p style="margin:0 0 16px;line-height:1.6;color:#4b5563">
            본인이 작성한 <?php echo $is_comment?'댓글':'게시글'; ?>은 신고할 수 없습니다.
        </p>
        <div class="report-btns" style="display:flex;gap:10px;justify-content:center">
            <button type="button" class="report_btn_submit" onclick="closeReportModal()">확인</button>
        </div>
    </div>
    <?php
    exit;
}

// 작성자 레벨 보호(설정에 따름)
$author_lv = 0;
if ($author_mb_id) {
    $row_lv = sql_fetch("SELECT mb_level FROM `{$g5['member_table']}` WHERE mb_id = '".sql_real_escape_string($author_mb_id)."'");
    $author_lv = (int)($row_lv['mb_level'] ?? 0);
}
if ($author_lv >= (int)$conf['author_protect_level']) {
    ?>
    <div class="report-guard" data-guard="1">
        <h3 style="margin:0 0 10px;font-size:18px;">신고 불가</h3>
        <p style="margin:0 0 16px;line-height:1.6;color:#4b5563">
            관리자/운영자 등 보호 대상의 <?php echo $is_comment?'댓글':'게시글'; ?>은 신고할 수 없습니다.
        </p>
        <div class="report-btns" style="display:flex;gap:10px;justify-content:center">
            <button type="button" class="report_btn_submit" onclick="closeReportModal()">확인</button>
        </div>
    </div>
    <?php
    exit;
}

// 아이콘 모드
$icon_mode = $conf['icon_mode'] ?: 'emoji';

// 사유 목록 구성 (설정 우선)
$reasons = [];
if (!empty($conf['reason_custom_enabled']) && trim((string)$conf['reason_custom_list']) !== '') {
    // 커스텀 목록 사용: 줄 단위로 분해
    $lines = preg_split('/\r\n|\r|\n/', $conf['reason_custom_list']);
    foreach ($lines as $line) {
        $text = trim($line);
        if ($text === '') continue;
        if ($icon_mode === 'fa4') {
            $reasons[] = [$text, 'fa-exclamation-circle']; // 기본 아이콘
        } elseif ($icon_mode === 'fa5') {
            $reasons[] = [$text, 'fa-solid fa-circle-exclamation'];
        } else {
            $reasons[] = [$text, '•']; // 이모지 모드, 아이콘 대용 점
        }
    }
} else {
    // 기본 사유 셋
    if ($icon_mode === 'fa4') {
        $reasons = [
            ['스팸', 'fa-exclamation-circle'],
            ['욕설', 'fa-commenting-o'],
            ['음란물', 'fa-eye-slash'],
            ['허위정보', 'fa-bug'],
            ['기타', 'fa-ellipsis-h'],
        ];
    } elseif ($icon_mode === 'fa5') {
        $reasons = [
            ['스팸', 'fa-solid fa-circle-exclamation'],
            ['욕설', 'fa-solid fa-user-slash'],
            ['음란물', 'fa-solid fa-eye-slash'],
            ['허위정보', 'fa-solid fa-triangle-exclamation'],
            ['기타', 'fa-solid fa-ellipsis'],
        ];
    } else { // emoji
        $reasons = [
            ['스팸', '📨'],
            ['욕설', '🤬'],
            ['음란물', '🔞'],
            ['허위정보', '🐞'],
            ['기타', '➕'],
        ];
    }
}

// 안내문
$notice = trim((string)($conf['note'] ?? ''));
?>
<meta charset="UTF-8">

<div id="report_form_wrap">
    <h2><i class="fa fa-flag"></i> <?php echo $target_name; ?> 신고하기</h2>
    <form id="report_form" action="<?php echo G5_BBS_URL; ?>/report_update.php" method="post" onsubmit="return validateReportForm();">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
        <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
        <input type="hidden" name="comment_id" value="<?php echo $comment_id; ?>">
		<input type="hidden" name="token" value="<?php echo get_token(); ?>">

		<div class="report-reason-group">
			<p class="report-label">신고 사유를 선택해주세요</p>
			<ul class="report-reason-icons">
                <?php foreach ($reasons as $r): list($text, $icon) = $r; ?>
                <li>
                    <label>
                        <input type="radio" name="reason" value="<?php echo get_text($text); ?>" required>
                        <div class="icon-wrap">
                            <?php if ($icon_mode === 'fa4'): ?>
                                <i class="fa <?php echo $icon; ?>"></i>
                            <?php elseif ($icon_mode === 'fa5'): ?>
                                <i class="<?php echo $icon; ?>"></i>
                            <?php else: ?>
                                <span class="emoji-icon"><?php echo $icon; ?></span>
                            <?php endif; ?>
                            <span><?php echo get_text($text); ?></span>
                        </div>
                    </label>
                </li>
                <?php endforeach; ?>
			</ul>
		</div>

        <div class="form-group reason-etc-box" style="display:none;">
            <label>기타 설명</label>
            <textarea name="memo" rows="4" placeholder="신고에 대한 추가 설명을 작성해주세요."></textarea>
        </div>

		<div class="report-btns">
			<button type="button" class="report_btn_cancel" onclick="closeReportModal()">취소</button>
			<button type="submit" class="report_btn_submit">신고하기</button>
		</div>
		
		<p class="report-notice" style="margin-top:12px;color:#6b7280;font-size:13px;line-height:1.5">
		  * 허위신고일 경우, 신고자의 서비스 활동이 제한될 수 있으니 유의하시어 신중하게 신고해주세요.
		</p>
    </form>
</div>

<script>
// "기타" 선택 시 추가 설명 노출 (텍스트가 "기타" 또는 포함되어 있으면 열기)
(function(){
  const box = document.querySelector('.reason-etc-box');
  if (!box) return;
  document.getElementById('report_form').addEventListener('change', function(e){
    if (e.target && e.target.name === 'reason') {
      const val = e.target.value || '';
      box.style.display = (/기타/i.test(val)) ? '' : 'none';
    }
  });
})();
</script>
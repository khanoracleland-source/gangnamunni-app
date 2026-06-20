<?php
include_once('./_common.php');
include_once(G5_ADMIN_PATH.'/boardreport.lib.php'); // 설정/테이블 보장 + 헬퍼

header("X-Content-Type-Options: nosniff");

if (!$is_member) {
    return_json(false, "로그인 후 이용 가능합니다.");
}

// CSRF 토큰 검사(사용자용)
if (function_exists('check_token')) {
    if (!check_token()) return_json(false, "유효하지 않은 요청입니다.(token)");
}

// 관리자 설정 로드
$conf = g5_report_conf();

// 신고 기능 비활성화 시 중단
if (empty($conf['enabled'])) {
    return_json(false, "신고 기능이 비활성화되어 있습니다.");
}

// 입력값
$bo_table    = clean_xss_tags($_POST['bo_table'] ?? '');
$wr_id       = (int) ($_POST['wr_id'] ?? 0);       // 원글 wr_id
$comment_id  = (int) ($_POST['comment_id'] ?? 0);  // 댓글 신고 시 댓글 wr_id, 게시글이면 0
$reason      = trim((string)($_POST['reason'] ?? ''));
$memo        = trim((string)($_POST['memo'] ?? ''));
$mb_id       = $member['mb_id'] ?? '';
$ip          = $_SERVER['REMOTE_ADDR'] ?? '';
$is_comment  = $comment_id > 0;

// bo_table 화이트리스트
if (!$bo_table || !preg_match('/^[A-Za-z0-9_]+$/', $bo_table)) {
    return_json(false, "잘못된 게시판 정보입니다.");
}
if (!$wr_id || !$mb_id) {
    return_json(false, "필수 항목이 누락되었습니다.");
}

// 사유/메모 유효성
if ($reason === '' || preg_replace('/\s+/u', '', $reason) === '') {
    return_json(false, "신고 사유를 입력해 주세요.");
}
$reason = mb_substr($reason, 0, 500, 'UTF-8');
$memo   = mb_substr($memo,   0, 1000, 'UTF-8');

// ----------------------
// 공통 (테이블 보장: boardreport.lib.php에서 수행됨)
// ----------------------
//$report_table = $g5['board_table']."_report";
//$write_table  = $g5['write_prefix'] . $bo_table;
$write_table  = $g5['write_prefix'] . $bo_table;

// 대상 글(원글) 존재 확인
if (!sql_query("DESCRIBE `{$write_table}`", false)) {
    return_json(false, "대상 게시판을 찾을 수 없습니다.");
}
$post = sql_fetch("SELECT wr_id, mb_id FROM `{$write_table}` WHERE wr_id = '{$wr_id}' AND wr_is_comment = 0");
if (empty($post['wr_id'])) return_json(false, "대상 글이 존재하지 않습니다.");

// 댓글 신고라면: 댓글 존재 + 부모 매칭 확인
$target_mb_id = $post['mb_id'] ?? ''; // 기본은 원글 작성자
if ($is_comment) {
    $comment = sql_fetch("SELECT wr_id, wr_parent, mb_id FROM `{$write_table}` WHERE wr_id = '{$comment_id}' AND wr_is_comment = 1");
    if (empty($comment['wr_id'])) return_json(false, "대상 댓글이 존재하지 않습니다.");
    if ((int)$comment['wr_parent'] !== (int)$wr_id) return_json(false, "원글과 댓글 정보가 일치하지 않습니다.");
    $target_mb_id = $comment['mb_id'] ?? '';
}

// 본인글/댓글 신고 금지 (설정값 반영)
if (!empty($conf['disallow_self']) && $target_mb_id && $target_mb_id === $mb_id) {
    return_json(false, "본인 글/댓글은 신고할 수 없습니다.");
}

// 작성자 레벨 보호 (설정값 반영)
if ($target_mb_id) {
    $row_lv = sql_fetch("SELECT mb_level FROM `{$g5['member_table']}` WHERE mb_id = '".g5_sql_esc($target_mb_id)."'");
    $author_lv = (int)($row_lv['mb_level'] ?? 0);
    if ($author_lv >= (int)($conf['author_protect_level'] ?? 9)) {
        return_json(false, "보호 대상의 글/댓글은 신고할 수 없습니다.");
    }
}

// 잠금 대상 신고 차단
$target_wr_id = $is_comment ? $comment_id : $wr_id;
$lock_row = sql_fetch("SELECT wr_report FROM `{$write_table}` WHERE wr_id = '{$target_wr_id}'");
if (!empty($lock_row['wr_report']) && $lock_row['wr_report'] === '잠금') {
    return_json(false, "잠금된 대상은 신고할 수 없습니다.");
}

// 도배 방지(같은 IP/회원이 동일 대상에 짧은 시간 연속 신고)
$cool_seconds = 15;
$recent = sql_fetch("
    SELECT rp_datetime
      FROM `{$report_table}`
     WHERE bo_table   = '".g5_sql_esc($bo_table)."'
       AND wr_id      = '{$wr_id}'
       AND comment_id = '{$comment_id}'
       AND (mb_id = '".g5_sql_esc($mb_id)."' OR rp_ip = '".g5_sql_esc($ip)."')
     ORDER BY rp_id DESC
     LIMIT 1
");
if (!empty($recent['rp_datetime'])) {
    $last = strtotime($recent['rp_datetime']);
    if ($last && (G5_SERVER_TIME - $last) < $cool_seconds) {
        return_json(false, "잠시 후 다시 시도해 주세요.");
    }
}

// (인덱스와 이중 방어) 같은 회원/같은 대상 중복 신고 선체크
$dup = sql_fetch("
    SELECT COUNT(*) AS cnt
      FROM `{$report_table}`
     WHERE bo_table   = '".g5_sql_esc($bo_table)."'
       AND wr_id      = '{$wr_id}'
       AND comment_id = '{$comment_id}'
       AND mb_id      = '".g5_sql_esc($mb_id)."'
");
if ((int)$dup['cnt'] > 0) {
    return_json(false, "이미 신고하셨습니다.");
}

// INSERT (게시글/댓글 공통)
$ins_ok = @sql_query("
    INSERT INTO `{$report_table}`
    SET bo_table     = '".g5_sql_esc($bo_table)."',
        wr_id        = '{$wr_id}',
        comment_id   = '{$comment_id}',
        mb_id        = '".g5_sql_esc($mb_id)."',
        rp_reason    = '".g5_sql_esc($reason)."',
        rp_memo      = '".g5_sql_esc($memo)."',
        rp_datetime  = '".G5_TIME_YMDHIS."',
        rp_ip        = '".g5_sql_esc($ip)."'
", false);

// 유니크 충돌(다중 클릭 등 경쟁조건)
if ($ins_ok === false) {
    return_json(false, "이미 신고하셨습니다.");
}

// 누적 신고수(대상별)
$cnt_row = sql_fetch("
    SELECT COUNT(*) AS cnt
      FROM `{$report_table}`
     WHERE bo_table   = '".g5_sql_esc($bo_table)."'
       AND wr_id      = '{$wr_id}'
       AND comment_id = '{$comment_id}'
");
$report_count = (int)$cnt_row['cnt'];

// 잠금 임계치(설정 반영: cf_report_lock_threshold)
$lock_threshold = max(1, (int)($conf['lock_threshold'] ?? 5));

// 임계치 도달 시 잠금(게시글/댓글 공통)
$locked = false;
if ($report_count >= $lock_threshold) {
    $target_wr_id = $is_comment ? $comment_id : $wr_id;
    $row_lock = sql_fetch("SELECT wr_report FROM `{$write_table}` WHERE wr_id = '{$target_wr_id}'");
    if (empty($row_lock['wr_report']) || $row_lock['wr_report'] !== '잠금') {
        sql_query("UPDATE `{$write_table}` SET wr_report = '잠금' WHERE wr_id = '{$target_wr_id}' ");
    }
    $locked = true;
}

return_json(true, '신고가 접수되었습니다.', [
    'report_count' => $report_count,
    'locked'       => $locked,
    'comment_id'   => $comment_id,
    'wr_id'        => $wr_id,
    'bo_table'     => $bo_table
]);

// ---------- helpers ----------
function return_json($ok, $message, $extra = []) {
    $data = array_merge(['ok' => (bool)$ok, 'message' => $message], $extra);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
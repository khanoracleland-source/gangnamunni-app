<?php
$sub_menu = "200100";
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'w');

// GET 파라미터 받기
$mb_id  = isset($_GET['mb_id']) ? trim($_GET['mb_id']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$token  = isset($_GET['token']) ? trim($_GET['token']) : '';

// 토큰 검증
if ($token !== get_admin_token()) {
    alert("유효하지 않은 토큰입니다.");
}

// 필수 파라미터 확인
if (!$mb_id) {
    alert("회원 아이디가 전달되지 않았습니다.");
}
if ($action !== 'approve' && $action !== 'reject') {
    alert("잘못된 액션입니다.");
}

// 회원 정보 조회
$mb = get_member($mb_id);
if (!$mb['mb_id']) {
    alert("해당 회원이 존재하지 않습니다.");
}

// 헤어디자이너 회원 승인 대상인지 확인 (mb_partner가 2이고 현재 레벨이 1)
if ($mb['mb_partner'] != 2 || $mb['mb_level'] != 1) {
    alert("해당 회원은 승인 대상이 아닙니다.");
}

if ($action === 'approve') {
    // 승인 시: mb_level을 4로 업데이트 (승인 완료)
    sql_query("UPDATE {$g5['member_table']} SET mb_level = '4' WHERE mb_id = '" . sql_real_escape_string($mb_id) . "' ");
    alert("회원 승인 완료되었습니다.", "./member_list.php");
} else if ($action === 'reject') {
    // 거절 시: mb_level을 1로 유지(또는 재설정)
    sql_query("UPDATE {$g5['member_table']} SET mb_level = '1' WHERE mb_id = '" . sql_real_escape_string($mb_id) . "' ");
    alert("회원 가입이 거절되었습니다.", "./member_list.php");
}
?>

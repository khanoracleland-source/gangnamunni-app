<?php
include_once('../../../../../common.php');

header('Content-Type: application/json');

// 로그인 체크
if (!$is_member) {
    echo json_encode(array('success' => false, 'message' => '로그인이 필요합니다.'));
    exit;
}

$bo_table = isset($_POST['bo_table']) ? clean_xss_tags($_POST['bo_table']) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$theme = isset($_POST['theme']) ? (int)$_POST['theme'] : 1;

// 테마 번호 유효성 검사
if ($theme < 1 || $theme > 5) {
    echo json_encode(array('success' => false, 'message' => '올바른 테마를 선택해주세요.'));
    exit;
}

// 게시글 정보 가져오기
$write = sql_fetch(" select * from {$write_table} where wr_id = '{$wr_id}' ");

if (!$write) {
    echo json_encode(array('success' => false, 'message' => '게시글을 찾을 수 없습니다.'));
    exit;
}

// 권한 체크 (본인 글이거나 관리자)
if ($write['mb_id'] != $member['mb_id'] && !$is_admin) {
    echo json_encode(array('success' => false, 'message' => '권한이 없습니다.'));
    exit;
}

// 테마 업데이트
$sql = " update {$write_table} 
            set wr_theme = '{$theme}' 
            where wr_id = '{$wr_id}' ";
sql_query($sql);

echo json_encode(array('success' => true, 'message' => '테마가 변경되었습니다.'));
?>
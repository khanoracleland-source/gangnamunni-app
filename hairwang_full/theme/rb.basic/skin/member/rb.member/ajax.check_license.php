<?php
include_once('./_common.php');

header('Content-Type: application/json');

$license_number = isset($_POST['license_number']) ? trim($_POST['license_number']) : '';

if (!$license_number) {
    echo json_encode(['success' => false, 'message' => '자격증 번호를 입력해주세요.']);
    exit;
}

// 자격증 번호 형식 검증 (00-00-00000)
if (!preg_match('/^\d{2}-\d{2}-\d{5}$/', $license_number)) {
    echo json_encode(['success' => false, 'message' => '올바른 자격증 번호 형식이 아닙니다. (예: 00-00-00000)']);
    exit;
}

// 이미 등록된 자격증 번호인지 확인
$sql = "SELECT mb_id FROM {$g5['member_table']} WHERE mb_7 = '$license_number' AND mb_partner = '2'";
$result = sql_fetch($sql);

if ($result) {
    echo json_encode(['success' => true, 'already_registered' => true]);
    exit;
}

// 자격증 번호 형식이 맞으면 통과 (별도 API 조회 없이)
echo json_encode(['success' => true, 'already_registered' => false]);
?>
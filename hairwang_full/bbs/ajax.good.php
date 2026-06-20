<?php
// 버퍼 초기화 (이전 출력 제거)
ob_clean();

include_once('./_common.php');

// JSON 헤더 설정 (반드시 출력 전에)
header('Content-Type: application/json; charset=utf-8');

// 응답 함수
function json_response($data) {
    echo json_encode($data);
    exit;
}

// 로그인 체크
if (!$is_member) {
    json_response(array('error' => '로그인 후 이용하실 수 있습니다.'));
}

$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', trim($_POST['bo_table'])) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$good = isset($_POST['good']) ? trim($_POST['good']) : '';

// 입력값 검증
if (!$bo_table || !$wr_id || ($good != 'good' && $good != 'nogood')) {
    json_response(array('error' => '올바른 값이 넘어오지 않았습니다.'));
}

// 게시판 테이블명
$write_table = $g5['write_prefix'] . $bo_table;

// 게시글 정보 조회
$sql = " select mb_id from {$write_table} where wr_id = '{$wr_id}' ";
$write = sql_fetch($sql);

if (!$write) {
    json_response(array('error' => '존재하지 않는 게시글입니다.'));
}

// 자기 글 체크
if ($write['mb_id'] && $write['mb_id'] == $member['mb_id']) {
    json_response(array('error' => '자신의 글에는 추천 또는 비추천 하실 수 없습니다.'));
}

// 이미 추천했는지 체크
$sql = " select bg_flag from {$g5['board_good_table']}
         where bo_table = '{$bo_table}'
         and wr_id = '{$wr_id}'
         and mb_id = '{$member['mb_id']}'
         and bg_flag in ('good', 'nogood') ";
$prev = sql_fetch($sql);

if ($prev && $prev['bg_flag']) {
    $msg = ($prev['bg_flag'] == 'good') ? '이미 추천하신 글입니다.' : '이미 비추천하신 글입니다.';
    json_response(array('error' => $msg));
}

// 추천/비추천 삽입
$sql = " insert into {$g5['board_good_table']} 
         set bo_table = '{$bo_table}', 
             wr_id = '{$wr_id}', 
             mb_id = '{$member['mb_id']}', 
             bg_flag = '{$good}', 
             bg_datetime = '".G5_TIME_YMDHIS."' ";
             
if (!sql_query($sql)) {
    json_response(array('error' => '처리 중 오류가 발생했습니다.'));
}

// 글의 추천/비추천 수 증가
$sql = " update {$write_table} 
         set wr_{$good} = wr_{$good} + 1 
         where wr_id = '{$wr_id}' ";
sql_query($sql);

// 최종 추천수 조회
$sql = " select wr_good, wr_nogood from {$write_table} where wr_id = '{$wr_id}' ";
$result = sql_fetch($sql);

// 성공 응답
json_response(array(
    'status' => 'ok',
    'good' => (int)$result['wr_good'],
    'nogood' => (int)$result['wr_nogood']
));
?>
<?php
include_once('./_common.php');

// AJAX 요청이 아니면 접근 금지  
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
    die('접근 권한이 없습니다.');
}

header('Content-Type: application/json');

// 로그인 체크
if (!$member['mb_id']) {
    die(json_encode(['error' => '로그인이 필요합니다.']));
}

$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', trim($_POST['bo_table'])) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$reaction_type = isset($_POST['reaction_type']) ? trim($_POST['reaction_type']) : '';

// 유효성 검사
if (!$bo_table || !$wr_id || !$comment_id) {
    die(json_encode(['error' => '잘못된 요청입니다.']));
}

// 반응 타입 검사
if (!in_array($reaction_type, ['like', 'dislike', 'sad'])) {
    die(json_encode(['error' => '잘못된 반응 타입입니다.']));
}

// 게시판 존재 여부 확인
$board = sql_fetch(" select * from {$g5['board_table']} where bo_table = '$bo_table' ");
if (!$board['bo_table']) {
    die(json_encode(['error' => '존재하지 않는 게시판입니다.']));
}

// 댓글 존재 여부 확인
$write_table = $g5['write_prefix'].$bo_table;
$comment = sql_fetch(" select wr_id from $write_table where wr_id = '$comment_id' and wr_parent = '$wr_id' and wr_is_comment = '1' ");
if (!$comment) {
    die(json_encode(['error' => '존재하지 않는 댓글입니다.']));
}

$reaction_table = 'g5_comment_reaction';

// 테이블이 없으면 생성
$table_check = sql_query("SHOW TABLES LIKE '{$reaction_table}'", false);
if(!sql_num_rows($table_check)) {
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS `{$reaction_table}` (
      `reaction_id` int(11) NOT NULL AUTO_INCREMENT,
      `bo_table` varchar(20) NOT NULL DEFAULT '',
      `wr_id` int(11) NOT NULL DEFAULT '0',
      `comment_id` int(11) NOT NULL DEFAULT '0',
      `mb_id` varchar(20) NOT NULL DEFAULT '',
      `reaction_type` varchar(20) NOT NULL DEFAULT '',
      `reaction_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
      `reaction_ip` varchar(50) NOT NULL DEFAULT '',
      PRIMARY KEY (`reaction_id`),
      UNIQUE KEY `unique_reaction` (`bo_table`, `wr_id`, `comment_id`, `mb_id`),
      KEY `idx_comment` (`bo_table`, `wr_id`, `comment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    sql_query($create_table_sql, false);
}

// 기존 반응 조회
$prev = sql_fetch("
    SELECT reaction_type 
    FROM `{$reaction_table}` 
    WHERE bo_table = '{$bo_table}' 
      AND wr_id = {$wr_id} 
      AND comment_id = {$comment_id} 
      AND mb_id = '{$member['mb_id']}'
");

$current_reaction = '';

if ($prev) {
    // 기존 반응이 있는 경우
    if ($prev['reaction_type'] == $reaction_type) {
        // 같은 반응 클릭 시 취소
        sql_query("
            DELETE FROM `{$reaction_table}` 
            WHERE bo_table = '{$bo_table}' 
              AND wr_id = {$wr_id} 
              AND comment_id = {$comment_id} 
              AND mb_id = '{$member['mb_id']}'
        ");
        $current_reaction = '';
    } else {
        // 다른 반응으로 변경
        sql_query("
            UPDATE `{$reaction_table}` 
            SET reaction_type = '{$reaction_type}',
                reaction_datetime = '".G5_TIME_YMDHIS."',
                reaction_ip = '{$_SERVER['REMOTE_ADDR']}'
            WHERE bo_table = '{$bo_table}' 
              AND wr_id = {$wr_id} 
              AND comment_id = {$comment_id} 
              AND mb_id = '{$member['mb_id']}'
        ");
        $current_reaction = $reaction_type;
    }
} else {
    // 새로운 반응 추가
    sql_query("
        INSERT INTO `{$reaction_table}` SET
            bo_table = '{$bo_table}',
            wr_id = {$wr_id},
            comment_id = {$comment_id},
            mb_id = '{$member['mb_id']}',
            reaction_type = '{$reaction_type}',
            reaction_datetime = '".G5_TIME_YMDHIS."',
            reaction_ip = '{$_SERVER['REMOTE_ADDR']}'
    ");
    $current_reaction = $reaction_type;
}

// 현재 반응 수 조회
$counts = ['like' => 0, 'dislike' => 0, 'sad' => 0];
$result = sql_query("
    SELECT reaction_type, COUNT(*) as cnt
    FROM `{$reaction_table}`
    WHERE bo_table = '{$bo_table}'
      AND wr_id = {$wr_id}
      AND comment_id = {$comment_id}
    GROUP BY reaction_type
");
while ($row = sql_fetch_array($result)) {
    $counts[$row['reaction_type']] = (int)$row['cnt'];
}

// 결과 반환
echo json_encode([
    'success' => true,
    'current_reaction' => $current_reaction,
    'counts' => $counts
]);
?>
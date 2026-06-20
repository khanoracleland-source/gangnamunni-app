<?php
// lib/member_level.lib.php
// 회원 활동 레벨 관련 함수 라이브러리

// 레벨별 조건 설정
function get_level_requirements() {
    return array(
        1 => array('name' => '헤린이', 'posts' => 0, 'comments' => 0, 'likes' => 0),
        2 => array('name' => '루키 스타', 'posts' => 10, 'comments' => 20, 'likes' => 20),
        3 => array('name' => '슈퍼 스타', 'posts' => 20, 'comments' => 40, 'likes' => 50),
        4 => array('name' => '빡고수', 'posts' => 30, 'comments' => 60, 'likes' => 70),
        5 => array('name' => '신의손', 'posts' => 40, 'comments' => 80, 'likes' => 100)
    );
}

// 회원의 활동 통계 가져오기
function get_member_activity_stats($mb_id) {
    global $g5;
    
    // 게시글 수 (삭제되지 않은 것만)
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['board_new_table']} 
            WHERE mb_id = '{$mb_id}' AND wr_id = wr_parent AND wr_is_comment = 0";
    $row = sql_fetch($sql);
    $posts = $row['cnt'];
    
    // 댓글 수
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['board_new_table']} 
            WHERE mb_id = '{$mb_id}' AND wr_is_comment = 1";
    $row = sql_fetch($sql);
    $comments = $row['cnt'];
    
    // 좋아요 수 (받은 좋아요)
    $likes = get_member_total_likes($mb_id);
    
    return array(
        'posts' => $posts,
        'comments' => $comments,
        'likes' => $likes
    );
}

// 회원이 받은 총 좋아요 수 계산
function get_member_total_likes($mb_id) {
    global $g5;
    
    $total_likes = 0;
    
    // 모든 게시판 테이블 조회
    $sql = "SELECT bo_table FROM {$g5['board_table']}";
    $result = sql_query($sql);
    
    while($board = sql_fetch_array($result)) {
        $write_table = $g5['write_prefix'] . $board['bo_table'];
        
        if(sql_table_exists($write_table)) {
            // 해당 회원이 작성한 글의 추천수 합계
            $sql2 = "SELECT SUM(wr_good) as good_sum FROM {$write_table} WHERE mb_id = '{$mb_id}'";
            $row = sql_fetch($sql2);
            $total_likes += (int)$row['good_sum'];
        }
    }
    
    return $total_likes;
}

// 회원의 적정 레벨 계산
function calculate_member_level($mb_id) {
    $stats = get_member_activity_stats($mb_id);
    $requirements = get_level_requirements();
    
    $appropriate_level = 1; // 기본 레벨
    
    // 높은 레벨부터 체크하여 조건을 만족하는 최고 레벨 찾기
    for($level = 5; $level >= 1; $level--) {
        $req = $requirements[$level];
        
        if($stats['posts'] >= $req['posts'] && 
           $stats['comments'] >= $req['comments'] && 
           $stats['likes'] >= $req['likes']) {
            $appropriate_level = $level;
            break;
        }
    }
    
    return $appropriate_level;
}

// 회원 레벨 자동 업데이트
function update_member_level($mb_id) {
    global $g5, $config;
    
    $new_level = calculate_member_level($mb_id);
    
    // 현재 레벨 조회
    $mb = get_member($mb_id);
    $current_level = $mb['mb_level'];
    
    // 레벨 6 이상은 수동 관리 (특별회원, 관리자 등)
    if($current_level >= 6) {
        return false;
    }
    
    // 레벨이 변경되었을 경우만 업데이트
    if($new_level != $current_level) {
        // 레벨은 자동으로 올라가기만 하고 내려가지 않도록 설정
        if($new_level > $current_level) {
            sql_query("UPDATE {$g5['member_table']} SET mb_level = '{$new_level}' WHERE mb_id = '{$mb_id}'");
            
            // 레벨 변경 로그 기록
            $requirements = get_level_requirements();
            $old_name = $requirements[$current_level]['name'];
            $new_name = $requirements[$new_level]['name'];
            
            // 알림 발송
            send_level_up_notification($mb_id, $old_name, $new_name);
            
            return true;
        }
    }
    
    return false;
}

// 레벨업 알림 발송
function send_level_up_notification($mb_id, $old_level_name, $new_level_name) {
    global $g5, $config;
    
    $mb = get_member($mb_id);
    
    // 쪽지 발송
    $content = "축하합니다! 회원님의 활동 등급이 [{$old_level_name}]에서 [{$new_level_name}](으)로 승급되었습니다.";
    
    sql_query("INSERT INTO {$g5['memo_table']} SET
        me_recv_mb_id = '{$mb_id}',
        me_send_mb_id = '{$config['cf_admin']}',
        me_send_datetime = '".G5_TIME_YMDHIS."',
        me_memo = '{$content}'
    ");
}

// 전체 회원 레벨 업데이트 (배치 처리용)
function update_all_member_levels() {
    global $g5;
    
    $updated_count = 0;
    
    // 탈퇴하지 않은 일반 회원만 대상 (레벨 6 미만)
    $sql = "SELECT mb_id FROM {$g5['member_table']} 
            WHERE mb_leave_date = '' AND mb_level < 6";
    $result = sql_query($sql);
    
    while($row = sql_fetch_array($result)) {
        if(update_member_level($row['mb_id'])) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

// 게시글/댓글 작성 후 자동 레벨 체크
function check_level_after_write($board, $wr_id, $w) {
    global $member;
    
    if($member['mb_id'] && $w == '') { // 새 글 작성시만
        update_member_level($member['mb_id']);
    }
}

// 댓글 작성 후 레벨 체크
function check_level_after_comment($board, $wr_id, $w) {
    global $member;
    
    if($member['mb_id']) {
        update_member_level($member['mb_id']);
    }
}

// 좋아요 후 레벨 체크
function check_level_after_good($bo_table, $wr_id) {
    global $g5;
    
    // 글 작성자 확인
    $write_table = $g5['write_prefix'] . $bo_table;
    $write = sql_fetch("SELECT mb_id FROM {$write_table} WHERE wr_id = '{$wr_id}'");
    
    if($write['mb_id']) {
        update_member_level($write['mb_id']);
    }
}
?>
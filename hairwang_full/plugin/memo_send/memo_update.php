<?php
include_once('./_common.php');
include_once(G5_ADMIN_PATH.'/admin.lib.php');

// 관리자 권한 체크
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    exit;
}

// 토큰 체크
check_admin_token();

// 폼 데이터 검증
if (empty($_POST['m_level'])) {
    alert('쪽지 받는 대상을 하나 이상 선택하세요.');
    exit;
}

if (empty($_POST['memo_content'])) {
    alert('쪽지 내용을 입력하세요.');
    exit;
}

// 제외할 회원 아이디 처리
$exclude_mb_ids = array();
if (!empty($_POST['exclude_mb_id'])) {
    $exclude_mb_ids = explode(',', $_POST['exclude_mb_id']);
    $exclude_mb_ids = array_map('trim', $exclude_mb_ids);
}

// 선택된 레벨 배열
$m_levels = $_POST['m_level'];
$memo_content = $_POST['memo_content'];

// 쪽지 발송 대상 회원 조회
$level_conditions = implode(',', $m_levels);
$sql = "SELECT mb_id, mb_nick FROM {$g5['member_table']} WHERE mb_level IN ({$level_conditions}) AND mb_leave_date = '' AND mb_intercept_date = ''";

// 제외할 회원 아이디가 있는 경우
if (!empty($exclude_mb_ids)) {
    $exclude_ids_str = implode("','", $exclude_mb_ids);
    $sql .= " AND mb_id NOT IN ('{$exclude_ids_str}')";
}

$result = sql_query($sql);

// 발송 카운트
$send_count = 0;
$member_list = array('id'=>array(), 'nick'=>array(), 'me_id'=>array());

// 쪽지 발송
while ($row = sql_fetch_array($result)) {
    $recv_mb_id = $row['mb_id'];
    $recv_mb_nick = $row['mb_nick'];
    
    $member_list['id'][] = $recv_mb_id;
    $member_list['nick'][] = $recv_mb_nick;
    
    $tmp_row = sql_fetch(" select max(me_id) as max_me_id from {$g5['memo_table']} ");
    $me_id = $tmp_row['max_me_id'] + 1;
    
    // 받는 회원 쪽지 INSERT
    $sql = " insert into {$g5['memo_table']} ( me_recv_mb_id, me_send_mb_id, me_send_datetime, me_memo, me_read_datetime, me_type, me_send_ip ) values ( '$recv_mb_id', '{$member['mb_id']}', '".G5_TIME_YMDHIS."', '{$memo_content}', '0000-00-00 00:00:00' , 'recv', '{$_SERVER['REMOTE_ADDR']}' ) ";
    sql_query($sql);
    
    if( $me_id = sql_insert_id() ){
        // 보내는 회원 쪽지 INSERT
        $sql = " insert into {$g5['memo_table']} ( me_recv_mb_id, me_send_mb_id, me_send_datetime, me_memo, me_read_datetime, me_send_id, me_type , me_send_ip ) values ( '$recv_mb_id', '{$member['mb_id']}', '".G5_TIME_YMDHIS."', '{$memo_content}', '0000-00-00 00:00:00', '$me_id', 'send', '{$_SERVER['REMOTE_ADDR']}' ) ";
        sql_query($sql);
        
        $member_list['me_id'][] = $me_id;
    }
    
    // 실시간 쪽지 알림 기능
    $sql = " update {$g5['member_table']} set mb_memo_call = '{$member['mb_id']}', mb_memo_cnt = '".get_memo_not_read($recv_mb_id)."' where mb_id = '$recv_mb_id' ";
    sql_query($sql);
    
    $send_count++;
}



if ($send_count > 0) {
    $str_nick_list = implode(',', $member_list['nick']);
    alert("총 {$send_count}명의 회원에게 쪽지를 발송했습니다.", '/adm/view.php?call=memo_send');
} else {
    alert("발송할 대상 회원이 없습니다.", '/adm/view.php?call=memo_send');
}
?>

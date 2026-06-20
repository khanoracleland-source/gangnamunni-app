<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_subscribe', 1, 1); // 관리자 메뉴를 추가함

function add_admin_bbs_menu_subscribe($admin_menu){ // 메뉴추가
    $admin_menu['menu000'][] = array(
        '000640', '구독 관리', G5_ADMIN_URL.'/rb/subscribe_form.php', 'rb_config'
    );
    return $admin_menu;
}

$sb = sql_fetch (" select * from rb_subscribe_set "); // 구독관리 테이블 조회

function sb_is($v_mb_id) { //구독중인지 여부체크
    global $is_member, $member;
    if ($v_mb_id && $is_member) { 
        $row = sql_fetch (" select COUNT(*) as cnt from rb_subscribe where sb_mb_id = '{$member['mb_id']}' and sb_fw_id = '{$v_mb_id}' ");
        return $row['cnt']; 
    } 
}

function sb_cnt($v_mb_id) { //구독인원
    if ($v_mb_id) { 
        $row = sql_fetch (" select COUNT(*) as cnt from rb_subscribe where sb_fw_id = '{$v_mb_id}' ");
        if($row['cnt'] > 0) {
            return '<span>구독 '.number_format($row['cnt']).'명</span>';
        }
    } 

}

//작성자의 구독자에게 알림을 보낸다.
add_event('write_update_after', 'sb_send', G5_HOOK_DEFAULT_PRIORITY, 5);

function sb_send($board, $wr_id, $w, $qstr, $redirect_url) {
    global $g5, $app;
    if($w == '') { //새글일때만
        $write_table = $g5['write_prefix'].$board['bo_table'];
        $bbs_id = $board['bo_table'];
        $bbs_name = $board['bo_subject'];
        $row = sql_fetch (" select mb_id from {$write_table} where wr_id = '{$wr_id}' and wr_is_comment = '0' ");
        $mb_idx = $row['mb_id'];
        $writer = get_member($mb_idx);
        
        //구독중인(알림수신인) 회원을 찾는다, 토큰정보도 함께 찾는다.
        $sql = " SELECT a.sb_mb_id, b.tk_token FROM rb_subscribe a LEFT JOIN rb_app_token b ON a.sb_mb_id = b.mb_id WHERE a.sb_fw_id = '{$mb_idx}' AND a.sb_push = '1' ";
        $sql_cnt = sql_fetch (" SELECT COUNT(*) AS cnt FROM rb_subscribe a LEFT JOIN rb_app_token b ON a.sb_mb_id = b.mb_id WHERE a.sb_fw_id = '{$mb_idx}' AND a.sb_push = '1' ");
        $result = sql_query($sql);
        
        if (isset($sql_cnt['cnt']) && $sql_cnt['cnt'] > 0) {
    
            $tokens = [];
            
            while ($row = sql_fetch_array($result)) {

                $rows_me = sql_fetch("SELECT MAX(me_id) AS new_me_id FROM {$g5['memo_table']}");
                $me_id = $rows_me['new_me_id'] + 1;
                $send_id = 'system-msg'; // 발송할 아이디
                $title = $writer['mb_nick']. '님 께서 '.$bbs_name.'에 새글을 등록했습니다.';
                $link_url = G5_BBS_URL.'/board.php?bo_table='.$bbs_id.'&wr_id='.$wr_id;
                    
                $memo_content = $title . "\n" . $link_url;
                $from_id = $row['sb_mb_id'];

                $sql_m1 = "INSERT INTO {$g5['memo_table']} SET me_id='$me_id', me_recv_mb_id='$from_id', me_send_mb_id='$send_id', me_type='recv', me_send_datetime='".G5_TIME_YMDHIS."', me_memo='$memo_content'";
                sql_query($sql_m1);

                $sql_m2 = "UPDATE {$g5['member_table']} SET mb_memo_call='$send_id', mb_memo_cnt='".get_memo_not_read($from_id)."' WHERE mb_id='$from_id'";
                sql_query($sql_m2);

                if (!empty($row['tk_token'])) {
                    $tokens[] = $row['tk_token'];
                }
                
            }
            
            if (!empty($tokens)) { //토큰이 있는 회원에게 푸시발송
                $jsonKeyFilePath = G5_DATA_PATH . '/push/' . $app['ap_key']; // 비공개키 파일 경로
                sendPushNotificationAsync($tokens, '구독알림', $title, $jsonKeyFilePath); // 비동기 발송 처리 함수
            }
            

        
        }
        
    }

}

// @미니님a 님 제안코드
add_event('member_leave', 'sb_member_leave', G5_HOOK_DEFAULT_PRIORITY, 1);

function sb_member_leave($member) {
    global $g5;
    $sql = "DELETE FROM rb_subscribe WHERE sb_mb_id = '{$member['mb_id']}'";
    sql_query($sql);
}
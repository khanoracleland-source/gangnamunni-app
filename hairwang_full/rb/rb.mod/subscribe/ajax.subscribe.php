<?php
include_once('../_common.php');

// 로그인 여부 확인
if ($is_member) {
    if (isset($_POST['sb_mb_id']) && !empty($_POST['sb_mb_id']) && isset($_POST['sb_fw_id']) && !empty($_POST['sb_fw_id'])) {
    
        $sb_mb_id = $_POST['sb_mb_id'];
        $sb_fw_id = $_POST['sb_fw_id'];
        
        // 데이터가 있는지 조회한다
        $rows = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_subscribe WHERE sb_mb_id = '{$sb_mb_id}' AND sb_fw_id = '{$sb_fw_id}'");

        if (isset($rows['cnt']) && $rows['cnt'] > 0) {
            // 데이터가 존재하면 구독 취소
            $sql = "DELETE FROM rb_subscribe WHERE sb_mb_id = '{$sb_mb_id}' AND sb_fw_id = '{$sb_fw_id}'";
            sql_query($sql);
            
            $data = array('status' => 'del');
            echo json_encode($data);
        } else {
            // 데이터가 존재하지 않으면 구독 추가
            $sql = "INSERT INTO rb_subscribe (sb_mb_id, sb_fw_id, sb_push, sb_datetime) VALUES ('{$sb_mb_id}', '{$sb_fw_id}', '1', '".G5_TIME_YMDHIS."')";
            sql_query($sql);
            
            //메세지발송
            $sb_mb_info = get_member($sb_mb_id);
            memo_auto_send($sb_mb_info['mb_nick'].'님 께서 회원님을 구독 했습니다.', G5_URL.'/rb/home.php?mb_id='.$sb_fw_id.'&ca=fn', $sb_fw_id, "system-msg");
            
            $data = array('status' => 'ok');
            echo json_encode($data);
        }
    }
}
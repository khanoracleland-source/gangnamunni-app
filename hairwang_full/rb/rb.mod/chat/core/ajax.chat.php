<?php
include_once('../../../../common.php');
header("Content-Type: application/json; charset=utf-8");

$data = array();
$data['data'] = array();
$data['max'] = array();
$data['from_record'] = array();

$act = isset($_POST['act']) ? $_POST['act'] : '';

//refresh
$mb_nick = isset($_POST['mb_nick']) ? $_POST['mb_nick'] : '';
$send_mb_id = isset($_POST['send_mb_id']) ? $_POST['send_mb_id'] : '';
$recv_mb_id = isset($_POST['recv_mb_id']) ? $_POST['recv_mb_id'] : '';
$max_id = isset($_POST['max_id']) ? intval($_POST['max_id']) : 0;

//history
$from_record = isset($_POST['from_record']) ? intval($_POST['from_record']) : 0;
$limit_record = isset($_POST['limit_record']) ? intval($_POST['limit_record']) : 0;
$from_record = $from_record - $limit_record;

if ($from_record < 0) {
    $limit_record = $limit_record + $from_record;
    $from_record = 0;
}

//update
$me_memo = isset($_POST['me_memo']) ? strip_tags(stripslashes($_POST['me_memo']), '<br><img><a><video><audio>') : '';

$msg = '';
$error_list  = array();
$data['error_msg'] = array();

if ($act == 'search_member') {

    if ($mb_nick == '') {
        echo json_encode(array('success' => false, 'error' => '닉네임을 입력하세요.'));
        exit;
    }

    $sql = "SELECT mb_id, mb_nick FROM {$g5['member_table']} WHERE mb_nick = '".sql_real_escape_string($mb_nick)."'";
    $result = sql_query($sql);

    $members = array();
    while ($row = sql_fetch_array($result)) {
        $members[] = array('mb_id' => $row['mb_id'], 'mb_nick' => $row['mb_nick']);
    }

    if (count($members) > 0) {
        echo json_encode(array('success' => true, 'data' => $members));
    } else {
        echo json_encode(array('success' => false, 'error' => '해당 닉네임을 찾을 수 없습니다.'));
    }
    exit;
}

if ($act == "recalculate_max_id") {
    if ($send_mb_id && $recv_mb_id) {
        $que = "SELECT MAX(me_id) AS max FROM rb_chat WHERE (me_recv_mb_id ='".sql_escape_string($recv_mb_id)."' AND me_send_mb_id = '".sql_escape_string($send_mb_id)."') OR (me_recv_mb_id = '".sql_escape_string($send_mb_id)."' AND me_send_mb_id = '".sql_escape_string($recv_mb_id)."')";
        $result = sql_fetch($que);
        $data['max_id'] = isset($result['max']) ? intval($result['max']) : 0;
        echo json_encode($data);
        exit;
    }

}

if ($act == "refresh") {
    if ($send_mb_id && $recv_mb_id) {
        // 읽지 않은 쪽지가 있으면 업데이트
        $aaa = "SELECT * FROM rb_chat WHERE me_recv_mb_id ='".sql_escape_string($send_mb_id)."' AND me_send_mb_id = '".sql_escape_string($recv_mb_id)."' AND me_read_datetime = '0000-00-00 00:00:00'";
        $bbb = sql_query($aaa);

        // 한개씩 읽은 날짜를 업데이트
        while ($ccc = sql_fetch_array($bbb)) {
            $up = "UPDATE rb_chat SET me_read_datetime = '".G5_TIME_YMDHIS."' WHERE me_id = '".intval($ccc['me_id'])."'";
            sql_query($up);
        }

        $sql = "SELECT * FROM rb_chat 
                WHERE ((me_recv_mb_id ='".sql_escape_string($recv_mb_id)."' AND me_send_mb_id = '".sql_escape_string($send_mb_id)."') 
                OR (me_recv_mb_id = '".sql_escape_string($send_mb_id)."' AND me_send_mb_id = '".sql_escape_string($recv_mb_id)."')) 
                AND me_id > ".intval($max_id)." ORDER BY me_send_datetime LIMIT 99999";
        $res = sql_query($sql);
        while ($row = sql_fetch_array($res)) {
            $row['me_memo'] = htmlspecialchars_decode($row['me_memo']);
            $data['data'][] = $row;
        }

        $que = "SELECT MAX(me_id) AS max FROM rb_chat 
                WHERE (me_recv_mb_id ='".sql_escape_string($recv_mb_id)."' AND me_send_mb_id = '".sql_escape_string($send_mb_id)."') 
                OR (me_recv_mb_id = '".sql_escape_string($send_mb_id)."' AND me_send_mb_id = '".sql_escape_string($recv_mb_id)."')";
        $data['max'] = sql_fetch($que);
    }
    
    echo json_encode($data);
    exit;
}

if($act == "history") {
    if ($send_mb_id && $recv_mb_id) {
        $sql = "SELECT * FROM rb_chat 
                WHERE (me_recv_mb_id ='".sql_escape_string($recv_mb_id)."' AND me_send_mb_id = '".sql_escape_string($send_mb_id)."') 
                OR (me_recv_mb_id = '".sql_escape_string($send_mb_id)."' AND me_send_mb_id = '".sql_escape_string($recv_mb_id)."') 
                ORDER BY me_send_datetime ASC LIMIT {$from_record}, {$limit_record}";
        $res = sql_query($sql);
        while ($row = sql_fetch_array($res)) {
            $data['data'][] = $row;
        }
        $data['from_record'] = $from_record;
    }
    
    echo json_encode($data);
    exit;
}

if ($act == "update") {
    $row = sql_fetch("SELECT mb_id, mb_nick, mb_open, mb_leave_date, mb_sms, mb_intercept_date FROM {$g5['member_table']} WHERE mb_id = '".sql_escape_string($recv_mb_id)."'");

    //관리자가 아니면서  탈퇴, 접근차단 인 회원일 때
    if ((!$row['mb_id'] || $row['mb_leave_date'] || $row['mb_intercept_date']) && !$is_admin) {
        $data['error_msg'] = '탈퇴하거나 접근차단된 회원입니다. 대화를 시작할 수 없습니다.';
    }

    /*
    if (!$is_admin) {
        $point = (int)$config['cf_memo_send_point'];
        //포인트 체크
        if ($point) {
            if ($member['mb_point'] - $point < 0) {
                $data['error_msg'] = '보유하신 포인트가 부족하여 대화를 시작할 수 없습니다.';
            }
        }
    }
    */

    if (empty($data['error_msg'])) {
        $tmp_row = sql_fetch("SELECT MAX(me_id) AS max_me_id FROM rb_chat");
        $me_id = intval($tmp_row['max_me_id']) + 1;

        $recv_mb_nick = get_text($row['mb_nick']);

        // INSERT
        $sql = "INSERT INTO rb_chat (me_id, me_recv_mb_id, me_send_mb_id, me_send_datetime, me_memo, me_read_datetime, me_type, me_send_ip) 
                VALUES ('$me_id', '".sql_escape_string($recv_mb_id)."', '".sql_escape_string($send_mb_id)."', '".G5_TIME_YMDHIS."', '".sql_escape_string($me_memo)."', '0000-00-00 00:00:00', 'recv', '".sql_escape_string($_SERVER['REMOTE_ADDR'])."')";
        sql_query($sql);
        
        //푸시알림 처리 //푸시알림사용시
        if(isset($chat_set['ch_push']) && $chat_set['ch_push'] == 1) {
            if (isset($row['mb_sms']) && $row['mb_sms'] == 1) { // Push 수신동의시
                
                // 푸시 알림을 보낼 토큰을 가져오기
                $tokens = [];
                $sql = "SELECT tk_token FROM rb_app_token WHERE tk_token != '' and mb_id = '{$recv_mb_id}'";
                $result = sql_query($sql);

                $title = "신규대화 알림";
                $body = "새로운 대화가 도착했습니다. 메세지를 확인 하세요.";
                
                while ($row = sql_fetch_array($result)) {
                    $tokens[] = $row['tk_token'];
                }

                if (!empty($tokens)) {
                    $jsonKeyFilePath = G5_DATA_PATH . '/push/' . $app['ap_key']; // 비공개키 파일 경로
                    sendPushNotificationAsync($tokens, $title, $body, $jsonKeyFilePath); // 비동기 발송 처리 함수
                }

            }
        }

        /*
        포인트 세팅
        
        if (!$is_admin) {
            insert_point($send_mb_id, (int)$config['cf_memo_send_point'] * (-1), $recv_mb_nick.'('.$recv_mb_id.')님께 쪽지 발송', '@memo', $recv_mb_id, $me_id);
        }
        */
    }
    
    echo json_encode($data);
    exit;
}

if ($act == "upload_image") {
    $upload_dir = G5_DATA_PATH . '/chat/';
    $upload_url = G5_DATA_URL . '/chat/';

    $max_file_size = $chat_set['ch_max_file_size']; // 100MB = 104857600 bytes
    
    $ch_extension = $chat_set['ch_extension'];
    $extensions_array = explode(',', $ch_extension);
    $allowed_extensions = $extensions_array;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0707, true);
        chmod($upload_dir, 0707);
    }

    $file = $_FILES['file'];
    $file_size = $file['size'];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($file_size > $max_file_size) { 
        $response = array('success' => false, 'error' => '파일 크기가 ' . ($max_file_size / 1048576) . 'Mb를 초과합니다.');
    } elseif (!in_array(strtolower($file_extension), $allowed_extensions)) {
        $response = array('success' => false, 'error' => '허용된 파일 형식이 아닙니다.');
    } else {
        $filename = $send_mb_id . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $response = array('success' => true, 'file_path' => $upload_url . $filename);
        } else {
            $response = array('success' => false, 'error' => '파일 이동에 실패했습니다.');
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

?>
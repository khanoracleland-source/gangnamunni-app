<?php
include_once('../../../../common.php');
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$me_id = isset($_POST['me_id']) ? $_POST['me_id'] : '';
$del_id = isset($_POST['del_id']) ? $_POST['del_id'] : '';
$delete_all = isset($_POST['delete_all']) ? $_POST['delete_all'] : '';

if ($member['mb_id']) {
    if ($me_id == "" && $delete_all == "") {
        alert('올바른 방법으로 이용해 주세요.');
    } else {
        if ($delete_all == "true") {
            // 내가 보낸 모든 메시지 삭제
            $sql = "SELECT * FROM rb_chat WHERE me_send_mb_id = '{$member['mb_id']}' AND me_recv_mb_id = '{$del_id}'";
            $result = sql_query($sql);
            while ($row = sql_fetch_array($result)) {
                // 파일이 있는 경우 파일 삭제
                if (preg_match('/<a.*href="([^"]*)"/i', $row['me_memo'], $matches) || 
                    preg_match('/<img.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
                    preg_match('/<video.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
                    preg_match('/<audio.*src="([^"]*)"/i', $row['me_memo'], $matches)) {
                    
                    $file_path = parse_url($matches[1], PHP_URL_PATH);
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                // 메시지 삭제
                $del_sql = "DELETE FROM rb_chat WHERE me_id = '{$row['me_id']}'";
                sql_query($del_sql);
            }
            echo json_encode(array('success' => true));
        } else {
            // 파일 삭제를 위해 메모 내용을 가져옴
            $sql = "SELECT * FROM rb_chat WHERE me_id = '{$me_id}'";
            $row = sql_fetch($sql);

            // 파일이 있는 경우 파일 삭제
            if (preg_match('/<a.*href="([^"]*)"/i', $row['me_memo'], $matches) || 
                preg_match('/<img.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
                preg_match('/<video.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
                preg_match('/<audio.*src="([^"]*)"/i', $row['me_memo'], $matches)) {
                
                $file_path = parse_url($matches[1], PHP_URL_PATH);
                $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            $sql = "DELETE FROM rb_chat WHERE me_id = '{$me_id}' AND me_send_mb_id = '{$member['mb_id']}' AND me_recv_mb_id = '{$del_id}'";
            sql_query($sql);

            echo json_encode(array('success' => true));
        }
    }
} else {
    alert_close('회원만 이용하실 수 있습니다.');
}
exit;
?>
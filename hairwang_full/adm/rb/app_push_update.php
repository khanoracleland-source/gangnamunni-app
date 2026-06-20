<?php
$sub_menu = "000600";
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'w');

check_admin_token();

$mb_id = isset($_POST['mb_id']) ? strip_tags($_POST['mb_id']) : '';
$pu_title = isset($_POST['pu_title']) ? strip_tags($_POST['pu_title']) : '';
$pu_body = isset($_POST['pu_body']) ? strip_tags($_POST['pu_body']) : '';

$title = $pu_title;
$body = $pu_body;


if ($mb_id) {

    $mb = get_member($mb_id);
    
    if (!$mb || !isset($mb['mb_id']) || !$mb['mb_id']) {
        alert('존재하는 회원아이디가 아닙니다.', './app_form.php');
    } else { 
        // 푸시 알림을 보낼 토큰을 가져오기
        $tokens = [];
        $sql = "SELECT tk_token FROM rb_app_token WHERE tk_token != '' and mb_id = '{$mb_id}'";
        $result = sql_query($sql);

        while ($row = sql_fetch_array($result)) {
            $tokens[] = $row['tk_token'];
        }
        
        if (!empty($tokens)) {
            $jsonKeyFilePath = G5_DATA_PATH . '/push/' . $app['ap_key']; // 비공개키 파일 경로
            sendPushNotificationAsync($tokens, $title, $body, $jsonKeyFilePath); // 비동기 발송 처리 함수
            alert('푸시알림 전송요청이 완료 되었습니다.', './app_form.php');
        } else {
            alert('토큰이 없는 회원 입니다. (앱 접속 > 로그인 필요)', './app_form.php');
        }
        
    }
    
} else { 
    
    //아이디가 없는 경우 전체발송
    // 푸시 알림을 보낼 토큰을 가져오기
    $tokens = [];
    $sql = "SELECT tk_token FROM rb_app_token WHERE tk_token != ''";
    $result = sql_query($sql);
    
    while ($row = sql_fetch_array($result)) {
        $tokens[] = $row['tk_token'];
    }
    
    if (!empty($tokens)) {
        $jsonKeyFilePath = G5_DATA_PATH . '/push/' . $app['ap_key']; // 비공개키 파일 경로
        sendPushNotificationAsync($tokens, $title, $body, $jsonKeyFilePath); // 비동기 발송 처리 함수
        alert('푸시알림을 정상적으로 발송 하였습니다.\n토큰이 있는 회원에게만 발송 됩니다.', './app_form.php');
    } else {
        alert('푸시알림을 보낼 토큰이 없습니다.', './app_form.php');
    }
}

goto_url('./app_form.php');
?>
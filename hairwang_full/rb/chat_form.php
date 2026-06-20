<?php
include_once('../common.php');
include_once(G5_CAPTCHA_PATH.'/captcha.lib.php');

if(isset($chat_set['ch_use']) && $chat_set['ch_use'] == 1) {
    
    if ($is_guest) {
        alert_close('회원만 이용하실 수 있습니다.');
    }

    if (isset($chat_set['ch_level']) && $member['mb_level'] < $chat_set['ch_level']) {
        alert_close('레벨이 부족하여 대화 기능을 사용할 수 없습니다.');
    }
    
} else { 
    alert_close('올바른 방법으로 이용해주세요.');
}

$mb_id = isset($mb_id) ? get_search_string($mb_id) : '';

$content = "";
$me_recv_mb_id = isset($_REQUEST['me_recv_mb_id']) ? clean_xss_tags($_REQUEST['me_recv_mb_id'], 1, 1) : '';
$me_id = isset($_REQUEST['me_id']) ? clean_xss_tags($_REQUEST['me_id'], 1, 1) : '';
$rev_mb = get_member($me_recv_mb_id);

if ($me_recv_mb_id == $member['mb_id']) {
    alert_close('자신과는 대화할 수 없습니다.');
}

if ($rev_mb['mb_level'] < $chat_set['ch_level']) {
    alert_close('상대방이 대화 사용 권한이 없습니다.');
}



// 탈퇴한 회원에게 보낼 수 없음
if ($me_recv_mb_id) {
    $mb = get_member($me_recv_mb_id);
    if (!$mb || !$mb['mb_id']) {
        alert_close('회원 정보가 존재하지 않습니다.');
    }

    // 4.00.15
    $row = sql_fetch("SELECT me_memo FROM rb_chat WHERE me_id = '".sql_escape_string($me_id)."' AND (me_recv_mb_id = '".sql_escape_string($member['mb_id'])."' OR me_send_mb_id = '".sql_escape_string($member['mb_id'])."')");
    if ($row && isset($row['me_memo']) && $row['me_memo']) {
        $content = "\n\n\n" . ' >' . "\n" . ' >' . "\n" . ' >' . str_replace("\n", "\n> ", get_text($row['me_memo'], 0)) . "\n" . ' >' . ' >';
    }
}

$g5['title'] = '1:1 대화';
include_once(G5_PATH.'/head.sub.php');

//$memo_action_url = G5_URL."/rb/chat_form_update.php";
include_once(G5_PATH.'/rb/rb.mod/chat/chat_form.skin.php');

include_once(G5_PATH.'/tail.sub.php');
?>
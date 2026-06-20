<?php
include_once('../../../../common.php');

$me_id = isset($_POST['me_id']) ? intval($_POST['me_id']) : 0;
$del_id = isset($_POST['del_id']) ? intval($_POST['del_id']) : 0;

if ($me_id > 0 && $del_id > 0) {
    // 대상 사용자에게 삭제 알림 전송
    // 클라이언트 측에서 주기적으로 chat_refresh를 호출하고 있으므로 여기서 특별히 다른 작업을 할 필요는 없습니다.
    // 클라이언트는 채팅 목록을 갱신할 때 삭제된 메시지를 자동으로 인식하고 처리합니다.

    echo json_encode(['status' => 'success', 'message' => '상대방에게 삭제 알림 전송 완료']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
?>
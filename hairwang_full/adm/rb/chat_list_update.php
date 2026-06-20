<?php
$sub_menu = '000620';
include_once('./_common.php');

check_demo();

check_admin_token();

if ($_POST['act_button'] == "선택삭제") {
    
    $count_post_chk = (isset($_POST['chk']) && is_array($_POST['chk'])) ? count($_POST['chk']) : 0;

    if (! $count_post_chk) {
        alert($_POST['act_button']." 하실 항목을 하나 이상 체크하세요.");
    }

    auth_check_menu($auth, $sub_menu, 'd');

    for ($i = 0; $i < $count_post_chk; $i++) {
        // 실제 번호를 넘김
        $k = isset($_POST['chk'][$i]) ? (int) $_POST['chk'][$i] : 0;
        $ime_id = isset($_POST['me_id'][$k]) ? (int) $_POST['me_id'][$k] : 0;

        // 파일 삭제를 위해 메모 내용을 가져옴
        $sqls = "SELECT * FROM rb_chat WHERE me_id = '{$ime_id}'";
        $rows = sql_fetch($sqls);

        // 파일이 있는 경우 파일 삭제
        if (preg_match('/<a.*href="([^"]*)"/i', $rows['me_memo'], $matches) ||
            preg_match('/<img.*src="([^"]*)"/i', $rows['me_memo'], $matches) ||
            preg_match('/<video.*src="([^"]*)"/i', $rows['me_memo'], $matches) ||
            preg_match('/<audio.*src="([^"]*)"/i', $rows['me_memo'], $matches)) {

            $file_path = parse_url($matches[1], PHP_URL_PATH);
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // 삭제
        sql_query("DELETE FROM rb_chat WHERE me_id = '{$ime_id}'");
    }
} else if ($_POST['act_button'] == "전체삭제") {

        auth_check_menu($auth, $sub_menu, 'd');
        $dir = G5_DATA_PATH.'/chat';
        // 폴더 내 모든 파일을 가져옵니다.
        $files = glob($dir . '/*'); 
    
        // 모든 파일을 반복하면서 삭제합니다.
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file); // 파일 삭제
            }
        }

        // 삭제
        sql_query(" DELETE FROM rb_chat ");
        alert('전체 데이터가 삭제 되었습니다.');
}



goto_url("./chat_form.php?sst=$sst&amp;sod=$sod&amp;sfl=$sfl&amp;stx=$stx&amp;page=$page");
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
    @include_once(G5_PATH.'/rb/rb.lib/ajax.upload_write_update.php'); // 파일첨부를 끌어오기 형태로 업로드 하는 경우만 추가해주세요.

    $wr_1_ex = isset($_POST['wr_1_ex']) ? $_POST['wr_1_ex'] : array();
    $wr_1 = [];
    for ($i = 0; $i < 2; $i++) {
        $wr_1[] = isset($wr_1_ex[$i]) ? $wr_1_ex[$i] : '';
    }
    $wr_1_str = implode("|", $wr_1);

    $wr_2_ex = isset($_POST['wr_2_ex']) ? $_POST['wr_2_ex'] : array();
    $wr_2 = [];
    for ($i = 0; $i < 3; $i++) {
        $wr_2[] = isset($wr_2_ex[$i]) ? $wr_2_ex[$i] : '';
    }
    $wr_2_str = implode("|", $wr_2);


    $wr_3_ex = isset($_POST['wr_3_ex']) ? $_POST['wr_3_ex'] : array();
    $wr_3 = [];
    for ($i = 0; $i < 10; $i++) {
        $wr_3[] = isset($wr_3_ex[$i]) ? $wr_3_ex[$i] : '';
    }
    $wr_3_str = implode("|", $wr_3);



    $sqls = " update $write_table set wr_1 = '{$wr_1_str}', wr_2 = '{$wr_2_str}', wr_3 = '{$wr_3_str}' where wr_id = '{$wr_id}' ";
    sql_query($sqls);

    if($w == "") {
        //관리자에게 쪽지발송
        memo_auto_send($board['bo_subject'].'에 새글이 등록 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $config['cf_admin'], "system-msg");
    }
?> 

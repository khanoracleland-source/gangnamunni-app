<?php
//if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
include_once('../../../../../common.php');

$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
$wr_id = isset($_POST['wr_id']) ? $_POST['wr_id'] : '';
$wr_status = isset($_POST['wr_status']) ? $_POST['wr_status'] : '';

if (!empty($bo_table) && !empty($wr_id) && !empty($wr_status)) {

    $write_table = $g5['write_prefix'] . $bo_table;

    // SQL 쿼리를 직접 실행
    $sql = "UPDATE {$write_table} SET wr_8 = '".sql_real_escape_string($wr_status)."' WHERE wr_id = '".sql_real_escape_string($wr_id)."' AND wr_is_comment = '0'";
    sql_query($sql);

    $data = array(
        'status' => 'ok',
        'wr_8' => $wr_status,
    );
    echo json_encode($data);

} else {
    $data = array(
        'status' => 'no',
    );
    echo json_encode($data);
}
    
?>
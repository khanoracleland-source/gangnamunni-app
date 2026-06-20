<?php
// 업로드된 파일이 있다면 파일삭제
$sql = " select * from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$comment_id}' ";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {

    $delete_file = run_replace('delete_file_path', G5_DATA_PATH.'/file/'.$bo_table.'/'.str_replace('../', '', $row['bf_file']), $row);
    if( file_exists($delete_file) ){
        @unlink($delete_file);
    }
    // 썸네일삭제
    if(preg_match("/\.({$config['cf_image_extension']})$/i", $row['bf_file'])) {
        delete_board_thumbnail($bo_table, $row['bf_file']);
    }
}


// 파일테이블 행 삭제
sql_query(" delete from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$comment_id}' ");

?>
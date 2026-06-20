<?php
//게시글에 속해있는 댓글의 첨부파일 삭제
$sql = " select * from {$write_table} where wr_parent = '{$write['wr_id']}' and wr_is_comment = '1' ";
$result = sql_query($sql);

while ($row = sql_fetch_array($result)) {
    
    $sql2 = " select * from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$row['wr_id']}' ";
    $result2 = sql_query($sql2);

    while ($row2 = sql_fetch_array($result2)) {
        
        $delete_file = run_replace('delete_file_path', G5_DATA_PATH.'/file/'.$row2['bo_table'].'/'.str_replace('../', '', $row2['bf_file']), $row2);
        if( file_exists($delete_file) ){
            @unlink($delete_file);
        }
        // 썸네일삭제
        if(preg_match("/\.({$config['cf_image_extension']})$/i", $row2['bf_file'])) {
            delete_board_thumbnail($row2['bo_table'], $row2['bf_file']);
        }
        
    }
    
    // 파일테이블 행 삭제
    sql_query(" delete from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '{$row['wr_id']}' ");

}
?>
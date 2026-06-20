<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 댓글 파일첨부 확장 플러그인 :: 파일조회
function get_file_comment($bo_table, $wr_id, $comment_id)
{
    global $g5, $qstr, $board;

    $write_table = $g5['write_prefix'].$bo_table;
    
    $file['count'] = 0;
    //$sql = " select * from {$g5['board_file_table']} where bo_table = '$bo_table' and wr_id = '$wr_id' and mb_id = '{$mb_id}' order by bf_no ";
    $sql = " SELECT a.*, b.mb_id FROM {$g5['board_file_table']} AS a LEFT JOIN {$write_table} AS b ON a.wr_id = b.wr_id WHERE a.bo_table = '$bo_table' AND a.wr_id = '$comment_id' ORDER BY a.bf_no ";
    
        
    $result = sql_query($sql);
    $nonce = download_file_nonce_key($bo_table, $comment_id);
    while ($row = sql_fetch_array($result))
    {
        $no = (int) $row['bf_no'];
        $bf_content = $row['bf_content'] ? html_purifier($row['bf_content']) : '';
        $file[$no]['href'] = G5_BBS_URL."/rb.download.php?bo_table=$bo_table&amp;wr_id=$comment_id&amp;w_id=$wr_id&amp;no=$no&amp;nonce=$nonce" . $qstr;
        $file[$no]['download'] = $row['bf_download'];
        // 4.00.11 - 파일 path 추가
        $file[$no]['path'] = G5_DATA_URL.'/file/'.$bo_table;
        $file[$no]['size'] = get_filesize($row['bf_filesize']);
        $file[$no]['datetime'] = $row['bf_datetime'];
        $file[$no]['source'] = addslashes($row['bf_source']);
        $file[$no]['bf_content'] = $bf_content;
        $file[$no]['content'] = get_text($bf_content);
        //$file[$no]['view'] = view_file_link($row['bf_file'], $file[$no]['content']);
        $file[$no]['view'] = view_file_link($row['bf_file'], $row['bf_width'], $row['bf_height'], $file[$no]['content']);
        $file[$no]['file'] = $row['bf_file'];
        $file[$no]['image_width'] = $row['bf_width'] ? $row['bf_width'] : 640;
        $file[$no]['image_height'] = $row['bf_height'] ? $row['bf_height'] : 480;
        $file[$no]['image_type'] = $row['bf_type'];
        $file[$no]['bf_fileurl'] = $row['bf_fileurl'];
        $file[$no]['bf_thumburl'] = $row['bf_thumburl'];
        $file[$no]['bf_storage'] = $row['bf_storage'];
        $file['count']++;
    }

    return run_replace('get_files', $file, $bo_table, $comment_id);
}
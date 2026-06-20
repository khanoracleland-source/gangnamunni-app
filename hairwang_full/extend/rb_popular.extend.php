<?php
if (!defined('_GNUBOARD_')) exit;

function popular_bbs_compare($a, $b)
{
    return $b['wr_hit'] - $a['wr_hit'];
}

function rb_latest_popular($skin_dir = '', $rows = 10, $subject_len = 40, $days = 7, $cache_time = 5)
{
    global $g5;

    $skin_dir = isset($skin_dir) ? $skin_dir : 'basic';
    $order_by = isset($order_by) ? $order_by : 'wr_hit desc';

    $popular_bbs_skin_path = G5_PATH . '/rb/rb.mod/popular/' . $skin_dir;
    $popular_bbs_skin_url = G5_URL . '/rb/rb.mod/popular/' . $skin_dir;

    $cache_fwrite = false;
    if(G5_USE_CACHE) {
        $cache_file = G5_DATA_PATH."/cache/popular-{$skin_dir}-{$rows}-{$subject_len}-{$days}.php";

        if(!file_exists($cache_file)) {
            $cache_fwrite = true;
        } else {
            if($cache_time > 0) {
                $filetime = filemtime($cache_file);
                if($filetime && $filetime < (G5_SERVER_TIME - 3600 * $cache_time)) {
                    @unlink($cache_file);
                    $cache_fwrite = true;
                }
            }

            if(!$cache_fwrite)
                include($cache_file);
        }
    }

    if (!G5_USE_CACHE || $cache_fwrite) {
        $list = array();

        $nowYmd = date('Ymd', G5_SERVER_TIME);
        $startYmd = date('Ymd', strtotime('-'.$days." day", G5_SERVER_TIME));

        $sql = " select * from {$g5['board_table']} where bo_use_search = '1' ";
        $board_list = sql_query($sql);
        for ($i = 0; $board = sql_fetch_array($board_list); $i++) {
            $tmp_write_table = $g5['write_prefix'] . $board['bo_table'];

            $sql = " select * from {$tmp_write_table} WHERE date_format(wr_datetime, '%Y%m%d') between '{$startYmd}' and '{$nowYmd}' and wr_is_comment = '0' limit 0, {$rows}";
            $result = sql_query($sql);
            for ($j = 0; $row = sql_fetch_array($result); $j++) {
                $tmp_list = get_list($row, $board, $popular_bbs_skin_url, $subject_len);
                $tmp_list['bo_table'] = $board['bo_table'];
                $tmp_list['bo_subject'] = $board['bo_subject'];
                array_push($list, $tmp_list);
            }
        }

        usort($list, 'popular_bbs_compare');
        $list = array_slice($list, 0, $rows);

        if($cache_fwrite) {
            $handle = fopen($cache_file, 'w');
            $cache_content = "<?php\nif (!defined('_GNUBOARD_')) exit;\n\$list=".var_export($list, true)."?>";
            fwrite($handle, $cache_content);
            fclose($handle);
        }
    }

    ob_start();
    include $popular_bbs_skin_path . '/popular.skin.php';
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}
<?php
include_once('./_common.php');

$types = isset($_GET['types']) ? $_GET['types'] : '';

if ($is_guest)
    alert_close('로그인 후 이용해주세요.');


if($types == "add") {
    
    $g5['title'] = $pnt_c_name.' 충전';
    include_once(G5_PATH.'/head.sub.php');
    include(G5_PATH.'/rb/rb.mod/point_c/point_c_add.skin.php');
    
} else if($types == "acc") {
    
    $g5['title'] = $pnt_c_name.' 출금';
    include_once(G5_PATH.'/head.sub.php');
    include(G5_PATH.'/rb/rb.mod/point_c/point_c_acc.skin.php');

    
} else { 
    

    $g5['title'] = get_text($member['mb_nick']).' 님의 '.$pnt_c_name.' 내역';
    include_once(G5_PATH.'/head.sub.php');

    $list = array();

    $sql_common = " from rb_point_c where mb_id = '".escape_trim($member['mb_id'])."' ";
    $sql_order = " order by po_id desc ";

    $sql = " select count(*) as cnt {$sql_common} ";
    $row = sql_fetch($sql);
    $total_count = $row['cnt'];

    $rows = $config['cf_page_rows'];
    $total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
    if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
    $from_record = ($page - 1) * $rows; // 시작 열을 구함

    $sql = " select *
                {$sql_common}
                {$sql_order}
                limit {$from_record}, {$rows} ";

    $result = sql_query($sql);

    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $list[] = $row;
    }

    include_once(G5_PATH.'/rb/rb.mod/point_c/point_c.skin.php');
    
}

include_once(G5_PATH.'/tail.sub.php');
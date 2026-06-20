<?php
// /adm/rb/rb_pwa_installs_update.php
include_once('./_common.php');

if (!$is_admin || $is_admin !== 'super') { alert('권한이 없습니다.'); }

function rb_admin_token_check(){
    if (function_exists('check_admin_token')) { check_admin_token(); return; }
    if (function_exists('check_token')) { check_token(); return; }
}
rb_admin_token_check();

$act = isset($_POST['act_button']) ? $_POST['act_button'] : '';
$qstr = isset($_POST['qstr']) ? $_POST['qstr'] : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

if ($act === '선택삭제') {
    $ids = isset($_POST['chk']) ? $_POST['chk'] : array();
    if (!$ids || !is_array($ids)) alert('선택된 항목이 없습니다.');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($v){ return $v>0; });
    if ($ids) {
        $in = implode(',', $ids);
        // 연관 구독/큐 정리 필요시 추가 삭제 로직 가능
        sql_query("DELETE FROM rb_pwa_subscriptions WHERE id IN ({$in})");
    }
    goto_url('./rb_pwa_installs.php?'.$qstr.'&page='.$page); exit;
    
} else if ($act === '전체삭제') {
    sql_query("DELETE FROM rb_pwa_subscriptions");
    goto_url('./rb_pwa_installs.php'); exit;
}

alert('잘못된 요청입니다.', './rb_pwa_installs.php');

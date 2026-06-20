<?php
// /adm/rb/rb_pwa_logs_update.php
include_once('./_common.php');

if (!$is_admin || $is_admin !== 'super') { alert('권한이 없습니다.'); }

function rb_admin_token_check(){
    if (function_exists('check_admin_token')) { check_admin_token(); return; }
    if (function_exists('check_token')) { check_token(); return; }
}
rb_admin_token_check();

$act  = isset($_POST['act_button']) ? $_POST['act_button'] : '';
$qstr = isset($_POST['qstr']) ? $_POST['qstr'] : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

// ★ 폼에서 넘어온 qstr은 &amp; 로 인코딩돼 있으므로 원복
if ($qstr !== '') $qstr = str_replace('&amp;', '&', $qstr);

if ($act === '선택삭제') {
    $ids = isset($_POST['chk']) ? $_POST['chk'] : array();
    if (!$ids || !is_array($ids)) alert('선택된 항목이 없습니다.');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($v){ return $v>0; });
    if ($ids) {
        $in = implode(',', $ids);

        $imgs = sql_query("SELECT image FROM rb_pwa_push_log WHERE id IN ({$in})");
        while($r = sql_fetch_array($imgs)){
            if (!empty($r['image'])) {
                $path = preg_replace('~^https?://'.preg_quote($_SERVER['HTTP_HOST'],'~').'~i','', $r['image']);
                $abs  = rtrim($_SERVER['DOCUMENT_ROOT'],'/').$path;
                if (is_file($abs)) @unlink($abs);
            }
        }

        // 큐 → 로그 순서대로 삭제
        sql_query("DELETE FROM rb_pwa_push_queue WHERE log_id IN ({$in})");
        sql_query("DELETE FROM rb_pwa_push_log  WHERE id IN ({$in})");
    }
    goto_url('./rb_pwa_logs.php?'.$qstr.'&page='.$page); exit;
}
else if ($act === '전체삭제') {

    // (선택) 전체 이미지 정리
    $imgs = sql_query("SELECT image FROM rb_pwa_push_log");
    while($r = sql_fetch_array($imgs)){
        if (!empty($r['image'])) {
            $path = preg_replace('~^https?://'.preg_quote($_SERVER['HTTP_HOST'],'~').'~i','', $r['image']);
            $abs  = rtrim($_SERVER['DOCUMENT_ROOT'],'/').$path;
            if (is_file($abs)) @unlink($abs);
        }
    }

    sql_query("TRUNCATE TABLE rb_pwa_push_queue");
    sql_query("TRUNCATE TABLE rb_pwa_push_log");
    goto_url('./rb_pwa_logs.php'); exit;
}

alert('잘못된 요청입니다.', './rb_pwa_logs.php');

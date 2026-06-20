<?php if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

memo_send_add_hook();

function memo_send_add_hook() {
    // 관리자 페이지에 경험치 관리 페이지 추가
    add_replace('admin_menu', 'add_memo_send_admin_menu', G5_HOOK_DEFAULT_PRIORITY, 1);
    add_event('admin_get_page_memo_send', 'add_memo_send_admin_page', 1 , 2);
}

function add_memo_send_admin_menu($admin_menu){
    // menu200(회원관리)에 회원쪽지발송 메뉴 추가
    $admin_menu['menu200'][] = array('200220', '회원쪽지발송', G5_ADMIN_URL.'/view.php?call=memo_send', 'memo_send');
    
    return $admin_menu;
}

function add_memo_send_admin_page($arr_query, $token){
    include_once(G5_PLUGIN_PATH.'/memo_send/index.php');
}
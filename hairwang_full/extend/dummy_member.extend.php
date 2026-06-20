<?php if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가


dummy_member_event_hook();
function dummy_member_event_hook() {
    add_replace('admin_menu', 'add_dummy_member_admin_menu', G5_HOOK_DEFAULT_PRIORITY, 1);
    add_event('admin_get_page_dummy_member', 'add_dummy_member_admin_page', 1 , 2);
}

function add_dummy_member_admin_menu($admin_menu){
    $admin_menu['menu200'][] = array('200330', '더미 회원 관리', G5_ADMIN_URL.'/view.php?call=dummy_member', 'dummy_member');
    return $admin_menu;
}

function add_dummy_member_admin_page($arr_query, $token){
    global $auth;
    include_once(G5_PLUGIN_PATH.'/dummy_member/index.php');

}
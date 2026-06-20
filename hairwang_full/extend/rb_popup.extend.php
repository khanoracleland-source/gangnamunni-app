<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_event('tail_sub', 'add_event_tail_popup'); // 하단부에 팝업을 추가함
add_replace('admin_menu', 'add_admin_bbs_menu_popup', 1, 1); // 관리자 메뉴를 추가함

function add_event_tail_popup() { // 팝업추가
    if(defined('_INDEX_')) { // index에서만 실행
        include_once(G5_PATH."/rb/popup.php");
    }
}

function add_admin_bbs_menu_popup($admin_menu){ // 메뉴추가
    $admin_menu['menu000'][] = array(
        '000730', '그룹팝업 관리', G5_ADMIN_URL.'/rb/popup_list.php', 'rb_config'
    );
    return $admin_menu;
}
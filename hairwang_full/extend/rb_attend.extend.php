<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_attend', 0, 1); // 관리자 메뉴를 추가함

function add_admin_bbs_menu_attend($admin_menu){ // 메뉴추가
    
    $admin_menu['menu000'][] = array('000771', '출석부 설정', G5_ADMIN_URL . '/rb/attend_config.php',   'rb_config');
    $admin_menu['menu000'][] = array('000000', '　', G5_ADMIN_URL, 'rb_config');
    return $admin_menu;
}

$att = sql_fetch (" select * from rb_attend_config "); // 테이블 조회
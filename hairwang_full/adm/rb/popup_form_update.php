<?php
$sub_menu = '000730';
require_once './_common.php';

$po_id = isset($_REQUEST['po_id']) ? (string)preg_replace('/[^0-9]/', '', $_REQUEST['po_id']) : 0;

if ($w == "u" || $w == "d") {
    check_demo();
}

if ($w == 'd') {
    auth_check_menu($auth, $sub_menu, "d");
} else {
    auth_check_menu($auth, $sub_menu, "w");
}

check_admin_token();

$po_subject = isset($_POST['po_title']) ? strip_tags(clean_xss_attributes($_POST['po_title'])) : '';
$posts = array();

$check_keys = array(
    'po_device' => 'str',
    'po_division' => 'str',
    'po_start' => 'str',
    'po_end' => 'str',
    'po_time' => 'int',
    'po_title' => 'str',
    'po_p1_title' => 'str',
    'po_p2_title' => 'str',
    'po_p3_title' => 'str',
    'po_p4_title' => 'str',
    'po_p5_title' => 'str',
    'po_p1_content' => 'text',
    'po_p1_content_html' => 'text',
    'po_p2_content' => 'text',
    'po_p2_content_html' => 'text',
    'po_p3_content' => 'text',
    'po_p3_content_html' => 'text',
    'po_p4_content' => 'text',
    'po_p4_content_html' => 'text',
    'po_p5_content' => 'text',
    'po_p5_content_html' => 'text',
    
);

foreach ($check_keys as $key => $val) {
    if ($val === 'int') {
        $posts[$key] = isset($_POST[$key]) ? (int) $_POST[$key] : 0;
    } elseif ($val === 'str') {
        $posts[$key] = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : 0;
    } else {
        $posts[$key] = isset($_POST[$key]) ? trim($_POST[$key]) : 0;
    }
}

$sql_common = " po_device = '{$posts['po_device']}',
                po_division = '{$posts['po_division']}',
                po_start = '{$posts['po_start']}',
                po_end = '{$posts['po_end']}',
                po_time = '{$posts['po_time']}',
                po_title = '{$posts['po_title']}',
                po_p1_title = '{$posts['po_p1_title']}',
                po_p2_title = '{$posts['po_p2_title']}',
                po_p3_title = '{$posts['po_p3_title']}',
                po_p4_title = '{$posts['po_p4_title']}',
                po_p5_title = '{$posts['po_p5_title']}',
                po_p1_content = '{$posts['po_p1_content']}',
                po_p2_content = '{$posts['po_p2_content']}',
                po_p3_content = '{$posts['po_p3_content']}',
                po_p4_content = '{$posts['po_p4_content']}',
                po_p5_content = '{$posts['po_p5_content']}',
                po_p1_content_html = '{$posts['po_p1_content_html']}',
                po_p2_content_html = '{$posts['po_p2_content_html']}',
                po_p3_content_html = '{$posts['po_p3_content_html']}',
                po_p4_content_html = '{$posts['po_p4_content_html']}',
                po_p5_content_html = '{$posts['po_p5_content_html']}',
                mb_id = '{$member['mb_id']}',
                po_datetime = '".G5_TIME_YMDHIS."' ";
                

if ($w == "") {
    $sql = " insert rb_popup set $sql_common ";
    sql_query($sql);
    $po_id = sql_insert_id();
    run_event('admin_popup_created', $po_id);
} elseif ($w == "u") {
    $sql = " update rb_popup set $sql_common where po_id = '$po_id' ";
    sql_query($sql);
    run_event('admin_popup_updated', $po_id);
} elseif ($w == "d") {
    $sql = " delete from rb_popup where po_id = '$po_id' ";
    sql_query($sql);
    run_event('admin_newwin_deleted', $po_id);
}

if ($w == "d") {
    goto_url('./popup_list.php');
} else {
    goto_url("./popup_form.php?w=u&amp;po_id=$po_id");
}

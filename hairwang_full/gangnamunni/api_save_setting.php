<?php
/**
 * AJAX 설정 저장 API
 * POST: key, value → gangnamunni_settings UPDATE
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$key = trim($_POST['key'] ?? '');
$value = trim($_POST['value'] ?? '');

// 허용된 키 화이트리스트
$allowed = [
    'cat_scale_pc_enabled', 'cat_scale_pc_factor', 'cat_scale_pc_adjust', 'cat_scale_pc_font', 'cat_scale_pc_pad', 'cat_scale_pc_chip', 'cat_scale_android_enabled', 'cat_scale_android_factor', 'cat_scale_android_adjust', 'cat_scale_android_font', 'cat_scale_android_pad', 'cat_scale_android_chip', 'cat_scale_ios_enabled', 'cat_scale_ios_factor', 'cat_scale_ios_adjust', 'cat_scale_ios_font', 'cat_scale_ios_pad', 'cat_scale_ios_chip',

    'booking_require_login', 'booking_phone_auth',
    'ad_show_index','ad_home_after_search','ad_home_after_events','promo_show_index',
    'ad_auto_scroll','ad_auto_scroll_speed','ad_infeed_interval',
    'ad_show_hospitals','ad_show_hospitals_infeed',
    'region_mix_enabled','region_mix_target_region','region_mix_target_count','region_mix_other_count',
    'ad_show_reviews','ad_show_events','ad_show_tips','ad_show_columns','ad_show_consultation',
    'fd_ad_banner_sticky','fd_ad_banner_title','fd_header_bg_color','fd_search_border_radius',
    'fd_region_btn_bg','fd_card_border_radius','fd_chip_border_radius',
    'hospital_view_default','hv_seoul_show','hv_card_show','hv_list_show',
];

if (!in_array($key, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid key']);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

require_once __DIR__ . '/includes/settings_helper.php';
$result = updateSetting($connect, $key, $value);
mysqli_close($connect);

echo json_encode(['success' => (bool)$result, 'key' => $key, 'value' => $value]);

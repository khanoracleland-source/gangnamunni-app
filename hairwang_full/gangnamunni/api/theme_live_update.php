<?php
/**
 * Theme Live Update API v2
 * Supports: theme_settings, settings, ai_settings
 * Logs all changes to gangnamunni_settings_log
 */
session_start();
require_once dirname(__DIR__) . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단

// Auth check (direct session or PHPSESSID file)
$is_admin = false;
$admin_id = 0;
$admin_user = '';
$admin_name = '';

if (!empty($_SESSION['ad_admin_logged_in'])) {
    $is_admin = true;
    $admin_id = $_SESSION['ad_admin_id'] ?? 0;
    $admin_user = $_SESSION['ad_admin_user'] ?? '';
    $admin_name = $_SESSION['ad_admin_name'] ?? '';
} elseif (isset($_COOKIE['PHPSESSID'])) {
    $save = session_save_path() ?: '/tmp';
    $sid = preg_replace('/[^a-zA-Z0-9,-]/', '', $_COOKIE['PHPSESSID']);
    $data = @file_get_contents($save . '/sess_' . $sid);
    if ($data && strpos($data, 'ad_admin_logged_in|b:1') !== false) {
        $is_admin = true;
        // Extract admin info from session data
        if (preg_match('/ad_admin_id\|i:(\d+)/', $data, $m)) $admin_id = (int)$m[1];
        if (preg_match('/ad_admin_user\|s:\d+:"([^"]+)"/', $data, $m)) $admin_user = $m[1];
        if (preg_match('/ad_admin_name\|s:\d+:"([^"]+)"/', $data, $m)) $admin_name = $m[1];
    }
}

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$input = json_decode(file_get_contents('php://input'), true);
$settings = $input['settings'] ?? [];
$source = $input['source'] ?? 'live_panel';

// Allowed keys per table
$allowed = [
    'theme_settings' => [
        'primary_color','secondary_color','background_color','text_color','border_color',
        'link_color','login_button_bg','border_radius','spacing_small','spacing_medium',
        'spacing_large','font_size_base','font_korean','font_english','site_name',
        'button_booking_text','button_consultation_text','button_more',
        'font_size_large','font_size_small','text_secondary_color','link_hover_color',
        'card_shadow','button_text_color','site_logo_path',
        'ad_auto_scroll_enabled','ad_auto_scroll_interval',
        'promo_auto_scroll_enabled','promo_auto_scroll_interval',
        'section_tips_title','section_hospitals_title',
        'default_hospital_view','default_tip_view',
    ],
    'settings' => [
        'ad_show_index','ad_show_hospitals','ad_show_hospitals_infeed','ad_show_events',
        'ad_show_reviews','ad_show_tips','ad_show_columns','ad_show_consultation',
        'promo_show_index',
        'ad_auto_scroll','ad_auto_scroll_speed','ad_infeed_interval',
        'hospital_view_default','hospital_view_seoul_enabled','hospital_view_card_enabled','hospital_view_list_enabled',
        'hv_card_btn_color','hv_card_tag_color','hv_card_name_size','hv_card_tag_size','hv_card_cta_size',
        'hv_list_btn_color','hv_list_name_size','hv_list_tag_size','hv_list_cta_size',
        'hv_seoul_btn_color','hv_seoul_name_size','hv_seoul_addr_size','hv_seoul_tag_color','hv_seoul_tag_size','hv_seoul_cta_size',
        'nav_top_show_mobile','nav_top_show_desktop','nav_bottom_show_mobile','nav_bottom_show_desktop',
        'debug_console_enabled',
    ],
    'ai_settings' => [
        'ai_provider','ai_model','review_ai_enabled','ad_ai_enabled','ai_max_tokens','ai_temperature',
    ],
];

// Map key to table
function getTableForKey($key, $allowed) {
    foreach ($allowed as $table => $keys) {
        if (in_array($key, $keys)) return $table;
    }
    return null;
}

// Table configs
$table_config = [
    'theme_settings' => ['table' => 'gangnamunni_theme_settings', 'key_col' => 'setting_key', 'val_col' => 'setting_value'],
    'settings' => ['table' => 'gangnamunni_settings', 'key_col' => 'setting_key', 'val_col' => 'setting_value'],
    'ai_settings' => ['table' => 'gangnamunni_ai_settings', 'key_col' => 'setting_key', 'val_col' => 'setting_value'],
];

$updated = 0;
$logged = 0;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

foreach ($settings as $item) {
    $key = $item['key'] ?? '';
    $value = $item['value'] ?? '';
    $tbl_name = getTableForKey($key, $allowed);
    if (!$tbl_name) continue;

    $cfg = $table_config[$tbl_name];

    // Get old value
    $old_value = null;
    $stmt = $connect->prepare("SELECT {$cfg['val_col']} FROM {$cfg['table']} WHERE {$cfg['key_col']} = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $old_value = $row[$cfg['val_col']];
    }
    $stmt->close();

    // Skip if value unchanged
    if ($old_value === $value) continue;

    // Update
    if ($old_value !== null) {
        $stmt = $connect->prepare("UPDATE {$cfg['table']} SET {$cfg['val_col']} = ? WHERE {$cfg['key_col']} = ?");
        $stmt->bind_param('ss', $value, $key);
    } else {
        // Insert if not exists (for settings table with setting_group)
        if ($tbl_name === 'settings') {
            $stmt = $connect->prepare("INSERT INTO {$cfg['table']} ({$cfg['key_col']}, {$cfg['val_col']}) VALUES (?, ?)");
            $stmt->bind_param('ss', $key, $value);
        } else {
            $stmt = $connect->prepare("INSERT INTO {$cfg['table']} ({$cfg['key_col']}, {$cfg['val_col']}) VALUES (?, ?)");
            $stmt->bind_param('ss', $key, $value);
        }
    }
    $stmt->execute();
    if ($stmt->affected_rows > 0) $updated++;
    $stmt->close();

    // Log change
    $log_stmt = $connect->prepare(
        "INSERT INTO gangnamunni_settings_log (admin_id, admin_user, admin_name, setting_table, setting_key, old_value, new_value, source, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $log_stmt->bind_param('issssssss' . 's', $admin_id, $admin_user, $admin_name, $tbl_name, $key, $old_value, $value, $source, $ip, $ua);
    $log_stmt->execute();
    if ($log_stmt->affected_rows > 0) $logged++;
    $log_stmt->close();
}

echo json_encode([
    'success' => true,
    'updated' => $updated,
    'logged' => $logged,
    'admin' => $admin_user,
]);

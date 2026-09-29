<?php
// No application database or browser session required.
// Run: php -n tests/regression/admin_menu_navigation.php
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/admin_menu_navigation.php';
function term($key, $default = null) { return $default ?? $key; }
function expect_menu($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$allow = static function ($key) { return true; };
$deny = static function ($key) { return false; };
$menus = admin_sidebar_menus();
expect_menu(admin_menu_contains_page('admin_hospital_treatments.php', $menus, $allow), 'Hospital treatment/image review page must be in the menu');
expect_menu(admin_menu_contains_page('admin_hospitals.php', $menus, $allow), 'Hospital page is blocked');
expect_menu(!admin_menu_contains_page('admin_hospitals.php', $menus, $deny), 'Invisible permission-filtered menu is navigable');
expect_menu(admin_menu_contains_page('admin_hospitals.php', [['url' => 'admin_hospitals.php?tab=pending', 'key' => 'hospitals']], $allow), 'Query-string menu not matched');
expect_menu(!admin_menu_contains_page('admin_hidden.php', [['url' => 'https://elsewhere.test/admin_hidden.php', 'key' => 'hidden']], $allow), 'External link allowed a local hidden page');
$restored = $menus;
$restored[] = ['url' => 'admin_hospital_treatments.php', 'key' => 'hospital_treatments'];
expect_menu(admin_menu_contains_page('admin_hospital_treatments.php', $restored, $allow), 'Restoring a menu item must restore navigation');
$server = ['SCRIPT_NAME' => '/hairwang/gangnamunni/admin_hospital_treatments.php', 'REQUEST_METHOD' => 'GET'];
expect_menu(admin_menu_navigation_is_page($server), 'Direct hidden page skipped');
expect_menu(admin_menu_navigation_is_page(array_replace($server, ['REQUEST_METHOD' => 'HEAD'])), 'HEAD skipped');
foreach ([
    ['REQUEST_METHOD' => 'POST'],
    ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    ['HTTP_ACCEPT' => 'application/json'],
    ['HTTP_SEC_FETCH_DEST' => 'image'],
    ['SCRIPT_NAME' => '/hairwang/gangnamunni/api_admin_notifications.php'],
    ['SCRIPT_NAME' => '/hairwang/gangnamunni/dashboard.php'],
] as $override) {
    expect_menu(!admin_menu_navigation_is_page(array_replace($server, $override)), 'Internal action/API or dashboard was redirected');
}
$embedded = array_replace($server, ['SCRIPT_NAME' => '/hairwang/gangnamunni/admin_hospitals.php']);
expect_menu(admin_menu_navigation_is_page($embedded) && admin_menu_contains_page(basename($embedded['SCRIPT_NAME']), $menus, $allow), 'Embedded treatment panel would be redirected');
expect_menu(admin_menu_navigation_is_page(array_replace($server, ['SCRIPT_NAME' => '/hairwang/gangnamunni/legacy_report.php']), true), 'Hidden page using admin layout skipped');
echo "PASS: menu visibility, permissions, direct GET/HEAD, panel inclusion, POST/AJAX/API exclusions and restoring menu entries\n";

<?php
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/admin_auth.php';
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/admin_menu_navigation.php';
function term($key, $default = null) { return $default ?? $key; }
function expect_landing($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
foreach (['hospital_super_admin', 'hospital_admin'] as $role) {
    foreach ([[], ['pages' => ['partner_hospital']], ['pages' => ['reviews']], ['pages' => ['nothing']]] as $permissions) {
        $_SESSION = ['ad_admin_logged_in' => true, 'ad_admin_role' => $role, 'ad_admin_permissions' => $permissions];
        $landing = hospital_admin_landing_page();
        expect_landing($landing !== null, 'Current menus must provide a permitted landing');
        expect_landing($landing !== 'dashboard.php', 'Landing must not redirect to itself');
        expect_landing(admin_menu_contains_page($landing, admin_sidebar_menus(), 'check_permission'), 'Landing would bounce back to dashboard: ' . $role);
        expect_landing(admin_login_destination('dashboard.php') === $landing, 'Login and dashboard disagree');
    }
}
$_SESSION = ['ad_admin_logged_in' => true, 'ad_admin_role' => 'super_admin'];
expect_landing(admin_login_destination('dashboard.php') === 'dashboard.php', 'Platform dashboard changed');
echo "PASS: 8 hospital role/permission combinations and platform dashboard\n";

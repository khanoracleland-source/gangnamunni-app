<?php
require_once __DIR__ . '/admin_menu_catalog.php';

/** Match real local pages, ignoring menu query strings and external links. */
function admin_menu_contains_page(string $page, array $menus, callable $can_access): bool {
    foreach ($menus as $menu) {
        if (isset($menu['children'])) {
            if (admin_menu_contains_page($page, $menu['children'], $can_access)) return true;
            continue;
        }
        $url = (string)($menu['url'] ?? '');
        if ($url === '' || strpos($url, '//') === 0 || parse_url($url, PHP_URL_SCHEME) !== null) continue;
        $path = (string)parse_url($url, PHP_URL_PATH);
        if ($path === $page && $can_access((string)($menu['key'] ?? ''))) return true;
    }
    return false;
}

/** Navigation restriction only; existing permission/CSRF checks still protect actions. */
function admin_menu_navigation_is_page(array $server, bool $has_admin_layout = false): bool {
    if (!in_array(strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true)) return false;
    if (strtolower((string)($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') return false;
    $destination = (string)($server['HTTP_SEC_FETCH_DEST'] ?? '');
    if ($destination !== '' && !in_array($destination, ['document', 'iframe'], true)) return false;
    $accept = strtolower((string)($server['HTTP_ACCEPT'] ?? ''));
    if (strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false) return false;
    // Use the actual entry script, so including treatments inside hospitals stays allowed.
    $page = basename((string)($server['SCRIPT_NAME'] ?? ''));
    if ($page === '' || $page === 'dashboard.php') return false;
    return $has_admin_layout || strpos($page, 'admin_') === 0;
}

function admin_enforce_menu_navigation(bool $has_admin_layout = false): void {
    if (!admin_menu_navigation_is_page($_SERVER, $has_admin_layout)) return;
    $page = basename((string)$_SERVER['SCRIPT_NAME']);
    if (admin_menu_contains_page($page, admin_sidebar_menus(), 'check_permission')) return;
    header('Location: dashboard.php', true, 302);
    exit;
}

<?php
/** Shared branding for public pages, authentication and administration. */
function seoulBeautyFaviconLinks() {
    $root = defined('G5_URL') ? rtrim(G5_URL, '/') : '';
    if (!defined('G5_URL') && defined('SITE_BASE_PATH')) {
        $root = preg_replace('~/gangnamunni/?$~', '', rtrim(SITE_BASE_PATH, '/'));
    }
    $directory = dirname(__DIR__) . '/assets/branding/';
    $links = '';
    foreach ([
        ['icon', 'image/x-icon', '32x32', 'favicon.ico'],
        ['icon', 'image/svg+xml', 'any', 'favicon.svg'],
        ['apple-touch-icon', 'image/png', '180x180', 'apple-touch-icon.png'],
    ] as $icon) {
        $version = substr(hash_file('sha256', $directory . $icon[3]), 0, 12);
        $url = htmlspecialchars($root . '/assets/branding/' . $icon[3] . '?v=' . $version, ENT_QUOTES, 'UTF-8');
        $links .= '<link rel="' . $icon[0] . '" type="' . $icon[1] . '" sizes="' . $icon[2] . '" href="' . $url . '">' . "\n";
    }
    return $links;
}

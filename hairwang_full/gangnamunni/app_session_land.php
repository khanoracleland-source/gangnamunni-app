<?php
/**
 * app_session_land.php?url=/next/... — patient app startup landing (Next.js pages).
 *
 * On a cold start the app injects its native session as the one-time `sb_sso_token`
 * cookie. Only a PHP request can turn it into a real Gnuboard session cookie for THIS
 * WebView (includes/sso_auto_login.php via init.php). Next.js pages never run PHP and
 * the NestJS server-side call to api/session_me.php would consume the token while its
 * Set-Cookie never reaches the WebView — so the app lands here first, then is sent on.
 * Same-site paths only (no scheme/host, no "//"), default "/".
 */
ob_start();
try {
    require_once __DIR__ . '/includes/init.php';
} catch (Throwable $e) {
    error_log('[app_session_land] init failed: ' . get_class($e));
}
ob_end_clean();

$url = trim((string)($_GET['url'] ?? ''));
$safe = '/';
if ($url !== '' && $url[0] === '/' && substr($url, 0, 2) !== '//'
    && !preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
    $safe = $url;
}
header('Cache-Control: no-store');
header('Location: ' . $safe, true, 302);
exit;

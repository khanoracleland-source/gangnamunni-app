<?php
include_once('./_common.php');

$request_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$is_public_site_request = in_array($request_host, ['seoulbeauty.app', 'www.seoulbeauty.app', 'hms.seoulbeauty.app'], true);
$canonical_password_lost_url = $is_public_site_request
    ? '/auth/password_lost.php'
    : G5_URL.'/gangnamunni/auth/password_lost.php';
if (function_exists('goto_url')) {
    goto_url($canonical_password_lost_url);
}
header('Location: '.$canonical_password_lost_url, true, 302);
exit;

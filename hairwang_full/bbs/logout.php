<?php
define('G5_CERT_IN_PROG', true);
include_once('./_common.php');

if(get_session('ss_user_mb_id')){
	$mb = get_member(get_session('ss_user_mb_id'));
	if(get_session('ss_mb_id') && get_session('ss_user_mb_id')!= get_session('ss_mb_id')){
		set_session('ss_mb_id', get_session('ss_user_mb_id'));
		set_session('ss_mb_key', get_session('ss_user_mb_key'));
		if(function_exists('update_auth_session_token')) update_auth_session_token($mb['mb_datetime']);
		alert('기존 아이디로 로그인합니다.', G5_ADMIN_URL."/member_list.php");
		exit;
	}
}

if(function_exists('social_provider_logout')){
    social_provider_logout();
}

if (isset($_COOKIE['sb_sso_token']) && !empty($_COOKIE['sb_sso_token'])) {
    $sso_token = $_COOKIE['sb_sso_token'];
    require_once dirname(__DIR__) . '/dbconfig.php';
    $sso_conn = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($sso_conn) {
        $sso_del = mysqli_prepare($sso_conn, "DELETE FROM sso_tokens WHERE token = ?");
        if ($sso_del) {
            mysqli_stmt_bind_param($sso_del, 's', $sso_token);
            mysqli_stmt_execute($sso_del);
            mysqli_stmt_close($sso_del);
        }
        mysqli_close($sso_conn);
    }
    setcookie('sb_sso_token', '', [
        'expires' => time() - 3600, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
}

session_unset(); 
session_destroy(); 

if (isset($member['mb_id']) && function_exists('revoke_remember_login_token')) {
    revoke_remember_login_token($member['mb_id']);
}
set_cookie('ck_mb_id', '', 0);
set_cookie('ck_auto', '', 0);

$app_native_auth_helper = dirname(__DIR__) . '/gangnamunni/includes/app_native_auth.php';
if (isset($member['mb_id']) && file_exists($app_native_auth_helper)) {
    require_once $app_native_auth_helper;
    $app_device_id = $_COOKIE['sb_app_device'] ?? '';
    if (app_native_auth_is_valid_device_id($app_device_id)) {
        app_revoke_native_auth_tokens($connect, $member['mb_id'], $app_device_id);
    }
}

if ($url) {
    if ( substr($url, 0, 2) == '//' )
        $url = 'http:' . $url;

    $p = @parse_url(urldecode($url));

    if (preg_match('/^https?:\/\//i', $url) || $p['scheme'] || $p['host']) {
        alert('url에 도메인을 지정할 수 없습니다.', G5_URL);
    }

    if($url == 'shop')
        $link = G5_SHOP_URL;
    else
        $link = $url;
} else if ($bo_table) {
    $link = get_pretty_url($bo_table);
} else {
    $link = "/hairwang/gangnamunni/";
}

run_event('member_logout', $link);


if (isset($_COOKIE['sb_patient_app']) && hash_equals('1', (string)$_COOKIE['sb_patient_app'])) {
    $link .= (strpos($link, '?') !== false ? '&' : '?') . 'app_logout=1';
}

goto_url($link);

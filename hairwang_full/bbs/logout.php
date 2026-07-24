<?php
define('G5_CERT_IN_PROG', true);
include_once('./_common.php');

/* 로그아웃 시 다시 관리자로 이동되도록 */
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
/* 로그아웃 시 다시 관리자로 이동되도록 */

if(function_exists('social_provider_logout')){
    social_provider_logout();
}

// Phase5: SSO 토큰 삭제 (통합 로그아웃)
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
        if (isset($member['mb_id']) && !empty($member['mb_id'])) {
            $sso_del2 = mysqli_prepare($sso_conn, "DELETE FROM sso_tokens WHERE g5_mb_id = ?");
            if ($sso_del2) {
                mysqli_stmt_bind_param($sso_del2, 's', $member['mb_id']);
                mysqli_stmt_execute($sso_del2);
                mysqli_stmt_close($sso_del2);
            }
        }
        mysqli_close($sso_conn);
    }
    setcookie('sb_sso_token', '', time() - 3600, '/', '', true, true);
}

// 이호경님 제안 코드
session_unset(); // 모든 세션변수를 언레지스터 시켜줌
session_destroy(); // 세션해제함

// 자동로그인 해제 --------------------------------
set_cookie('ck_mb_id', '', 0);
set_cookie('ck_auto', '', 0);
// 자동로그인 해제 end --------------------------------

if ($url) {
    if ( substr($url, 0, 2) == '//' )
        $url = 'http:' . $url;

    $p = @parse_url(urldecode($url));
    /*
        // OpenRediect 취약점관련, PHP 5.3 이하버전에서는 parse_url 버그가 있음 ( Safflower 님 제보 ) 아래 url 예제
        // http://localhost/bbs/logout.php?url=http://sir.kr%23@/
    */
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

// Redirect immediately after the session and social-provider state are cleared.
// A server-side redirect avoids flashing an intermediate logout message.
goto_url($link);

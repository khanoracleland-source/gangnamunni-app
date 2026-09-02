<?php
include_once('./_common.php');
//include_once(G5_CAPTCHA_PATH.'/captcha.lib.php');
include_once(G5_LIB_PATH.'/register.lib.php');

define('ASIDE_DISABLE', 1);

if( ! $config['cf_social_login_use'] ){
    alert('소셜 로그인을 사용하지 않습니다.');
}

if( $is_member ){
    alert('이미 회원가입 하였습니다.', G5_URL);
}

$provider_name = social_get_request_provider();
$user_profile = social_session_exists_check();
if( ! $user_profile ){
    alert( "소셜로그인을 하신 분만 접근할 수 있습니다.", G5_URL);
}

// 소셜 가입된 내역이 있는지 확인 상수 G5_SOCIAL_DELETE_DAY 관련
$is_exists_social_account = social_before_join_check($url);

$user_nick = social_relace_nick($user_profile->displayName);
$user_email = isset($user_profile->emailVerified) ? $user_profile->emailVerified : ($user_profile->email ?? '');
if (!$user_email && !empty($user_profile->sid)) {
    $user_email = 'kakao_' . preg_replace('/[^0-9a-z_-]/i', '', $user_profile->sid) . '@social.local';
}

// If Kakao verifies an email already owned by a local member, link the Kakao
// identity instead of failing registration on the duplicate email constraint.
if (strtolower($provider_name) === 'kakao'
    && !empty($user_profile->emailVerified)
    && filter_var($user_profile->emailVerified, FILTER_VALIDATE_EMAIL)) {
    $verified_email = strtolower(trim((string)$user_profile->emailVerified));
    $existing_member = sql_fetch(
        "SELECT * FROM {$g5['member_table']} WHERE LOWER(mb_email) = '"
        . sql_real_escape_string($verified_email) . "' LIMIT 1"
    );

    if (!empty($existing_member['mb_id'])) {
        $today = date('Ymd', G5_SERVER_TIME);
        $is_blocked = (!empty($existing_member['mb_intercept_date']) && $existing_member['mb_intercept_date'] <= $today)
            || (!empty($existing_member['mb_leave_date']) && $existing_member['mb_leave_date'] <= $today);
        $linked_kakao = sql_fetch(
            "SELECT identifier FROM {$g5['social_profile_table']} WHERE mb_id = '"
            . sql_real_escape_string($existing_member['mb_id']) . "' AND provider = 'kakao' LIMIT 1"
        );

        if (!$is_blocked && empty($linked_kakao['identifier'])) {
            social_user_profile_replace($existing_member['mb_id'], $provider_name, $user_profile);
            social_sync_kakao_member_profile($existing_member['mb_id'], $user_profile);

            echo social_return_from_provider_page(
                $provider_name,
                G5_HTTPS_BBS_URL . '/login_check.php',
                $existing_member['mb_id'],
                bin2hex(random_bytes(20)),
                isset($url) ? $url : G5_URL . '/gangnamunni/index.php',
                2
            );
            exit;
        }
    }
}
$user_id = $user_profile->sid ? preg_replace("/[^0-9a-z_]+/i", "", $user_profile->sid) : get_social_convert_id($user_profile->identifier, $provider_name);
$user_phone = strtolower($provider_name) === 'kakao'
    ? social_normalize_kakao_phone($user_profile->phone ?? '')
    : ($user_profile->phone ?? '');

if(! $user_nick) {
    $tmp = explode('_', $user_id);
    $user_nick = $tmp[1];
}

//$is_exists_id = exist_mb_id($user_id);
//$is_exists_name = exist_mb_nick($user_nick, '');
$user_id = exist_mb_id_recursive($user_id);
$user_nick = exist_mb_nick_recursive($user_nick, '');
$is_exists_email = $user_email ? exist_mb_email($user_email, '') : false;
$user_name = isset($user_profile->username) ? $user_profile->username : '';

// Kakao/Apple consent is enough for this service: create the local account
// on the first successful authentication instead of showing another form.
// (Apple's own consent screen already collects/confirms name and email —
// Apple's guidelines call for accepting that instead of re-asking for it.)
if (in_array(strtolower($provider_name), array('kakao', 'apple'), true)) {
    $_POST['mb_id'] = $user_id;
    $_POST['mb_password'] = '';
    $_POST['mb_password_re'] = '';
    $_POST['mb_nick'] = $user_nick;
    $_POST['mb_email'] = $user_email;
    $_POST['mb_name'] = $user_name ?: $user_nick;
    $_POST['mb_hp'] = $user_phone;
    $_POST['mb_mailling'] = 0;
    $_POST['mb_open'] = 0;
    $w = '';

    define('G5_SOCIAL_AUTO_REGISTER', true);
    include G5_SOCIAL_LOGIN_PATH . '/register_member_update.php';
    exit;
}

// 불법접근을 막도록 토큰생성
$token = md5(uniqid(rand(), true));
set_session("ss_token", $token);

$g5['title'] = '소셜 회원 가입 - '.social_get_provider_service_name($provider_name);

include_once(G5_BBS_PATH.'/_head.sub.php');

$register_action_url = https_url(G5_PLUGIN_DIR.'/'.G5_SOCIAL_LOGIN_DIR, true).'/register_member_update.php';
$login_action_url = G5_HTTPS_BBS_URL."/login_check.php";
$req_nick = !isset($member['mb_nick_date']) || (isset($member['mb_nick_date']) && $member['mb_nick_date'] <= date("Y-m-d", G5_SERVER_TIME - ($config['cf_nick_modify'] * 86400)));
$required = ($w=='') ? 'required' : '';
$readonly = ($w=='u') ? 'readonly' : '';
$login_url = '';

include_once(get_social_skin_path().'/social_register_member.skin.php');

include_once(G5_BBS_PATH.'/_tail.sub.php');

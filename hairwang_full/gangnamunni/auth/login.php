<?php
require_once __DIR__ . '/../includes/init.php';

global $config, $connect, $g5;

if (isset($_GET['provider']) || isset($_REQUEST['provider'])) {
    $social_query = array();
    parse_str(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '', $social_query);
    $raw_social_provider = isset($social_query['provider'])
        ? $social_query['provider']
        : (isset($_GET['provider']) ? $_GET['provider'] : $_REQUEST['provider']);
    $social_provider = ucfirst(strtolower(preg_replace(
        '/[^a-z0-9_-]/i',
        '',
        (string)$raw_social_provider
    )));

    $supported_social_providers = ['Kakao', 'Google', 'Apple'];

    if (in_array($social_provider, $supported_social_providers, true) && function_exists('social_check_login_before')) {
        $_GET['provider'] = $_REQUEST['provider'] = $social_provider;
        if (array_key_exists('redirect_to_idp', $social_query)) {
            $_GET['redirect_to_idp'] = $_REQUEST['redirect_to_idp'] = '1';
        }

        if (!defined('G5_SOCIAL_FORCE_SAME_WINDOW')) {
            define('G5_SOCIAL_FORCE_SAME_WINDOW', true);
        }

        error_log(
            '[' . $social_provider . ' flow] controller enter; callback='
            . (isset($_REQUEST['redirect_to_idp']) ? 'yes' : 'no')
        );
        social_check_login_before($social_provider);

        error_log('[' . $social_provider . ' flow] social_check_login_before() returned unexpectedly');
        http_response_code(500);
        exit($social_provider . ' login handler returned unexpectedly.');
    } elseif (in_array($social_provider, $supported_social_providers, true)) {
        error_log('[' . $social_provider . ' flow] social_check_login_before() is not loaded');
        http_response_code(500);
        exit($social_provider . ' login module is not loaded.');
    }
}

$bbs_url = defined('G5_BBS_URL') ? G5_BBS_URL : '/bbs';
$g5_url  = defined('G5_URL') ? G5_URL : '';
$member_table = $g5['member_table'] ?? 'g5_member';

if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$login_csrf_token = $_SESSION['login_csrf_token'];

function gnu_login_rate_hit($scope, $value, $limit, $window, $record = true) {
    $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir();
    $dir = $base . '/cache/login_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return ['allowed'=>false,'retry'=>60];
    $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower((string)$value)) . '.json';
    $fp = @fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return ['allowed'=>false,'retry'=>60]; }
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    $now = time();
    $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
    $hits = array_values(array_filter($hits, function ($ts) use ($now, $window) { return (int)$ts > $now - $window; }));
    $retry = count($hits) >= $limit ? max(1, $window - ($now - (int)$hits[0])) : 0;
    if (!$retry && $record) $hits[] = $now;
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode(['hits'=>$hits])); fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '/*.json') ?: [] as $old) {
            if (@filemtime($old) < $now - 86400 && ($old_fp = @fopen($old, 'c+'))) {
                if (@flock($old_fp, LOCK_EX | LOCK_NB)) { if (@filemtime($old) < $now - 86400) @unlink($old); @flock($old_fp, LOCK_UN); }
                @fclose($old_fp);
            }
        }
    }
    return ['allowed' => $retry === 0, 'retry' => $retry];
}

function gnu_auth_log($event, $email = '', $details = '') {
    $account = $email === '' ? '-' : substr(hash('sha256', strtolower(trim($email))), 0, 16);
    $ip = preg_replace('/[^0-9a-fA-F:.]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $details = preg_replace('/[\r\n\x00-\x1F\x7F]/', '', (string)$details);
    error_log('[auth][' . preg_replace('/[^a-z0-9_-]/i', '', $event) . '] account=' . $account . '; ip=' . $ip . ($details !== '' ? '; ' . substr($details, 0, 160) : ''));
}

if (!function_exists('gnu_load_env')) {
    function gnu_load_env($file) {
        $out = [];
        if (!is_readable($file)) return $out;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $out[trim(substr($line, 0, $pos))] = trim(trim(substr($line, $pos + 1)), "\"'");
        }
        return $out;
    }
}
$_env = gnu_load_env(dirname(__DIR__) . '/.env');
$recaptcha_site_key = $_env['RECAPTCHA_SITE_KEY'] ?? (getenv('RECAPTCHA_SITE_KEY') ?: '');
$recaptcha_secret   = $_env['RECAPTCHA_SECRET']   ?? (getenv('RECAPTCHA_SECRET')   ?: '');
$recaptcha_request_host = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$recaptcha_is_local = in_array($recaptcha_request_host, ['127.0.0.1', 'localhost', '::1'], true);
$recaptcha_enabled = !$recaptcha_is_local && $recaptcha_site_key !== '' && $recaptcha_secret !== '';
if (!function_exists('gnu_verify_recaptcha')) {
    function gnu_verify_recaptcha($secret, $token, $remote_ip = '', $expected_action = 'login', $expected_hosts = []) {
        if ($secret === '') return 'skip';
        if ($token === '') return 'fail';
        if (!is_array($expected_hosts)) $expected_hosts = [$expected_hosts];
        $expected_hosts = array_values(array_filter(array_map('strtolower', $expected_hosts)));
        $post = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $remote_ip]);
        $body = false;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $body = curl_exec($ch);
            curl_close($ch);
        }
        if ($body === false) {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => 5]]);
            $body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        }
        if (!$body) return 'skip';
        $r = json_decode($body, true);
        if (!is_array($r)) return 'skip';
        if (empty($r['success'])) return 'fail';
        if (isset($r['score']) && $r['score'] < 0.5) return 'fail';
        if (($r['action'] ?? '') !== $expected_action) return 'fail';
        if (!empty($expected_hosts) && !in_array(strtolower($r['hostname'] ?? ''), $expected_hosts, true)) return 'fail';
        return 'pass';
    }
}

function gnu_safe_after_url($candidate, $fallback) {
    $candidate = trim((string)$candidate);
    if ($candidate === '' || strpos($candidate, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $candidate)) return $fallback;
    if ($candidate[0] === '/' && substr($candidate, 0, 2) !== '//') return $candidate;

    $parts = @parse_url($candidate);
    if (!$parts || !isset($parts['scheme'], $parts['host'])) return $fallback;
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return $fallback;
    $request_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return $request_host !== '' && strtolower($parts['host']) === $request_host ? $candidate : $fallback;
}

$after_url = rtrim(SITE_BASE_PATH, '/') . '/index.php';
if (isset($_GET['url']) && $_GET['url'] !== '')  $after_url = gnu_safe_after_url($_GET['url'], $after_url);
if (isset($_POST['url']) && $_POST['url'] !== '') $after_url = gnu_safe_after_url($_POST['url'], $after_url);

if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    header('Location: ' . $after_url);
    exit;
}

$login_error = '';
$is_patient_app = isset($_COOKIE['sb_patient_app']) && hash_equals('1', (string)$_COOKIE['sb_patient_app']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'email') {
    $email      = strtolower(trim((string)($_POST['mb_email'] ?? '')));
    $password   = (string)($_POST['mb_password'] ?? '');
    $auto_login = ($is_patient_app || isset($_POST['auto_login'])) ? 1 : 0;

    $request_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_rate = ['allowed' => true, 'retry' => 0];
    $email_rate = ['allowed' => true, 'retry' => 0];
    $account_rate = ['allowed' => true, 'retry' => 0];
    $basic_request_valid = !empty($_POST['csrf_token'])
        && hash_equals($login_csrf_token, (string)$_POST['csrf_token'])
        && $email !== '' && $password !== '';
    if ($basic_request_valid) {
        $ip_rate = gnu_login_rate_hit('ip', $request_ip, 10, 900, false);
        $email_rate = gnu_login_rate_hit('ip_email', $request_ip . '|' . strtolower($email), 5, 900, false);
        $account_rate = gnu_login_rate_hit('account', strtolower($email), 20, 3600, false);
    }

    if (empty($_POST['csrf_token']) || !hash_equals($login_csrf_token, (string)$_POST['csrf_token'])) {
        $login_error = '요청이 만료되었습니다. 페이지를 새로고침해주세요.';
    } elseif ($email === '' || $password === '') {
        $login_error = '이메일과 비밀번호를 입력해주세요.';
    } elseif (!$ip_rate['allowed'] || !$email_rate['allowed'] || !$account_rate['allowed']) {
        $retry = max((int)$ip_rate['retry'], (int)$email_rate['retry'], (int)$account_rate['retry']);
        gnu_auth_log('rate_limited', $email, 'retry=' . $retry);
        $login_error = '로그인 시도가 너무 많습니다. ' . $retry . '초 후 다시 시도해주세요.';
    } else {
        $recaptcha_result = $recaptcha_enabled && ($_POST['recaptcha_token'] ?? '') !== ''
            ? gnu_verify_recaptcha($recaptcha_secret, $_POST['recaptcha_token'] ?? '', $request_ip, 'login', [
            $recaptcha_request_host,
            'seoulbeauty.app',
            'www.seoulbeauty.app',
            'hms.seoulbeauty.app',
            ])
            : ($recaptcha_enabled ? 'empty_token' : 'disabled');
        if ($recaptcha_result === 'fail' || $recaptcha_result === 'empty_token') {
            gnu_auth_log('recaptcha_soft_failed', $email, 'result=' . $recaptcha_result . '; host=' . $recaptcha_request_host);
        }

        $mb_id_found = '';
        if ($stmt = mysqli_prepare($connect, "SELECT mb_id FROM {$member_table} WHERE LOWER(mb_email) = LOWER(?) LIMIT 1")) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) $mb_id_found = $row['mb_id'];
            mysqli_stmt_close($stmt);
        }
        $login_mb = $mb_id_found !== '' ? get_member($mb_id_found) : [];
        if (function_exists('run_event')) run_event('member_login_check_before', $mb_id_found);

        $password_valid = $mb_id_found !== ''
            && !empty($login_mb['mb_id'])
            && login_password_check($login_mb, $password, $login_mb['mb_password']);

        if (!$password_valid && !empty($login_mb['mb_lost_certify'])) {
            $reset_value = (string)$login_mb['mb_lost_certify'];
            $reset_parts = explode(' ', trim($reset_value), 3);
            $pending_hash = '';
            if (count($reset_parts) === 3
                && strlen($reset_parts[0]) === 32 && ctype_xdigit($reset_parts[0])
                && ctype_digit($reset_parts[1]) && time() <= (int)$reset_parts[1]) {
                $pending_hash = $reset_parts[2];
            } elseif (count($reset_parts) === 2
                && strlen($reset_parts[0]) === 32 && ctype_xdigit($reset_parts[0])) {
                $pending_hash = $reset_parts[1];
            }

            if ($pending_hash !== '' && check_password($password, $pending_hash)) {
                $activate = mysqli_prepare(
                    $connect,
                    "UPDATE {$member_table}
                     SET mb_password = ?, mb_lost_certify = ''
                     WHERE mb_id = ? AND mb_lost_certify = ?"
                );
                if ($activate) {
                    mysqli_stmt_bind_param($activate, 'sss', $pending_hash, $mb_id_found, $reset_value);
                    mysqli_stmt_execute($activate);
                    $password_valid = mysqli_stmt_affected_rows($activate) === 1;
                    mysqli_stmt_close($activate);
                    if ($password_valid) {
                        $login_mb['mb_password'] = $pending_hash;
                        $login_mb['mb_lost_certify'] = '';
                        gnu_auth_log('password_reset_activated_on_login', $email);
                    }
                }
            }
        }

        if (!$password_valid) {
            gnu_login_rate_hit('ip', $request_ip, 10, 900, true);
            gnu_login_rate_hit('ip_email', $request_ip . '|' . strtolower($email), 5, 900, true);
            gnu_login_rate_hit('account', strtolower($email), 20, 3600, true);
            if (function_exists('run_event')) run_event('password_is_wrong', 'login', $login_mb);
            gnu_auth_log('password_failed', $email);
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (!empty($login_mb['mb_intercept_date']) && $login_mb['mb_intercept_date'] <= date('Ymd')) {
            gnu_login_rate_hit('account', strtolower($email), 20, 3600, true);
            gnu_auth_log('account_blocked', $email);
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (!empty($login_mb['mb_leave_date']) && $login_mb['mb_leave_date'] <= date('Ymd')) {
            gnu_login_rate_hit('account', strtolower($email), 20, 3600, true);
            gnu_auth_log('account_deleted', $email);
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (function_exists('is_use_email_certify') && is_use_email_certify() && !preg_match('/[1-9]/', $login_mb['mb_email_certify'] ?? '')) {
            gnu_login_rate_hit('account', strtolower($email), 20, 3600, true);
            gnu_auth_log('email_unverified', $email);
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } else {
            if (function_exists('run_event')) run_event('login_session_before', $login_mb, false);
            if (!(defined('SKIP_SESSION_REGENERATE_ID') && SKIP_SESSION_REGENERATE_ID)) {
                session_regenerate_id(false);
                if (function_exists('session_start_samesite')) session_start_samesite();
            }
            set_session('ss_mb_id', $login_mb['mb_id']);
            generate_mb_key($login_mb);
            if (function_exists('update_auth_session_token')) update_auth_session_token($login_mb['mb_datetime']);

            if ($auto_login) {
                $remember_lifetime = $is_patient_app ? $patient_app_lifetime : 86400 * 31;
                $remember_token = function_exists('create_remember_login_token')
                    ? create_remember_login_token($login_mb, $remember_lifetime)
                    : '';
                if ($remember_token !== '') {
                    set_cookie('ck_mb_id', $login_mb['mb_id'], $remember_lifetime);
                    set_cookie('ck_auto', $remember_token, $remember_lifetime);
                } else {
                    set_cookie('ck_mb_id', '', 0);
                    set_cookie('ck_auto', '', 0);
                    error_log('[login] remember token creation failed');
                }
            } else {
                if (function_exists('revoke_remember_login_token')) revoke_remember_login_token($login_mb['mb_id']);
                set_cookie('ck_mb_id', '', 0);
                set_cookie('ck_auto', '', 0);
            }
            if (function_exists('run_event')) run_event('member_login_check', $login_mb, $after_url, false);
            gnu_auth_log('login_success', $email, $auto_login ? 'remember=1' : 'remember=0');
            header('Location: ' . $after_url);
            exit;
        }
    }
}

global $rb_builder;
$_logo_ver = defined('G5_SERVER_TIME') ? G5_SERVER_TIME : time();
if (!empty($rb_builder['bu_logo_pc'])) {
    $logo_src = $g5_url . '/data/logos/pc?ver=' . $_logo_ver;
} else {
    $_theme_url = defined('G5_THEME_URL') ? G5_THEME_URL : ($g5_url . '/theme/rb.basic');
    $logo_src = $_theme_url . '/rb.img/logos/pc.png?ver=' . $_logo_ver;
}
$brand_name = function_exists('getTheme') ? getTheme('site_name', '서울미녀') : '서울미녀';
$primary    = function_exists('getTheme') ? getTheme('primary_color', '#AE1932') : '#AE1932';
$social_services = array_map('strtolower', array_map('trim', explode(',', (string)($config['cf_social_servicelist'] ?? ''))));
$kakao_enabled = !empty($config['cf_social_login_use'])
    && in_array('kakao', $social_services, true)
    && !empty($config['cf_kakao_rest_key']);
$google_enabled = !empty($config['cf_social_login_use'])
    && in_array('google', $social_services, true)
    && !empty($config['cf_google_clientid'])
    && !empty($config['cf_google_secret']);
$apple_enabled = !empty($config['cf_social_login_use'])
    && in_array('apple', $social_services, true)
    && !empty($config['cf_apple_team_id'])
    && !empty($config['cf_apple_key_id'])
    && !empty($config['cf_apple_bundle_id'])
    && !empty($config['cf_apple_key_file']);
$kakao_url = rtrim(SITE_BASE_PATH, '/') . '/auth/login.php?provider=kakao&url=' . urlencode($after_url);
$google_url = rtrim(SITE_BASE_PATH, '/') . '/auth/login.php?provider=google&url=' . urlencode($after_url);
$apple_url = rtrim(SITE_BASE_PATH, '/') . '/auth/login.php?provider=apple&url=' . urlencode($after_url);
$open_email = $login_error !== '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta name="robots" content="noindex, follow">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>로그인 | <?php echo htmlspecialchars($brand_name); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if ($recaptcha_enabled) { ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptcha_site_key, ENT_QUOTES); ?>"></script>
    <?php } ?>
    <style>
        :root { --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES); ?>; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body, html { margin: 0; padding: 0; background: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; color: #1a1a1a; }
        .login-wrap { max-width: 420px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: 20px 24px 36px; }
        .login-top { min-height: 24px; }
        .login-top a { color: #bbb; text-decoration: none; display: inline-flex; align-items: center; }
        .login-hero { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; text-align: center; padding: 24px 0 0; }
        .login-hero img { width: auto; height: auto; max-width: 66%; max-height: 78px; }
        .login-actions { flex: 0 0 auto; display: flex; flex-direction: column; margin-top: 130px; }
        .btn { width: 100%; height: 54px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn:active { transform: scale(.99); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-kakao { background: #FEE500; color: #191600; }
        .btn-google { background: #fff; color: #202124; border: 1px solid #dadce0; margin-top: 10px; }
        .btn-apple { background: #000; color: #fff; margin-top: 10px; }
        .btn-apple svg { flex: 0 0 auto; }
        .btn-email { background: #191919; color: #fff; margin-top: 10px; }
        .email-panel { max-height: 0; overflow: hidden; transition: max-height .32s ease; }
        .email-panel.open { max-height: 520px; }
        .email-inner { padding-top: 8px; display: flex; flex-direction: column; gap: 10px; }
        .field input { width: 100%; height: 52px; border: 1px solid #e9e9ee; border-radius: 12px; padding: 0 16px; font-size: 15px; background: #fafafb; }
        .field input:focus { outline: none; border-color: var(--primary); background: #fff; }
        .pw-wrap { position: relative; }
        .pw-wrap .toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #aaa; font-size: 13px; cursor: pointer; padding: 6px; }
        .row-between { display: flex; align-items: center; justify-content: space-between; margin: 2px 2px 4px; }
        .row-between label { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #666; cursor: pointer; }
        .row-between a { font-size: 13px; color: #999; text-decoration: none; }
        .err { background: #fdecef; color: #c0293f; font-size: 13px; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
        .foot-links { display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 80px; }
        .foot-links a { font-size: 14px; font-weight: 600; color: #666; text-decoration: none; }
        .foot-links a:active { color: #000; }
        .foot-links .sep { color: #dcdce1; font-size: 13px; }
        .recaptcha-notice { margin-top: 56px; text-align: left; font-size: 11px; line-height: 1.7; color: #000000; }
        .rc-more { background: none; border: none; color: #999; font-size: 12px; font-weight: 600; cursor: pointer; margin-top: 4px; text-decoration: underline; padding: 4px 6px; }
        .recaptcha-detail { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
        .recaptcha-detail.open { max-height: 120px; }
        .recaptcha-detail p { margin: 8px 0 0; font-size: 11px; line-height: 1.7; color: #b6b6bc; }
        .recaptcha-detail a { color: #888; text-decoration: underline; }
        .grecaptcha-badge { visibility: hidden; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-top">
            <a href="<?php echo htmlspecialchars(rtrim(SITE_BASE_PATH, '/'), ENT_QUOTES); ?>/index.php" aria-label="홈으로">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                </svg>
            </a>
        </div>

        <div class="login-hero">
            <img src="<?php echo htmlspecialchars($logo_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand_name); ?>">
        </div>

        <div class="login-actions">
            <a href="<?php echo $kakao_enabled ? htmlspecialchars($kakao_url, ENT_QUOTES) : '#'; ?>" class="btn btn-kakao" <?php if (!$kakao_enabled) echo 'onclick="alert(\'카카오 로그인이 아직 설정되지 않았습니다. 이메일로 시작하기를 이용해 주세요.\'); return false;"'; ?>>
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3C6.48 3 2 6.48 2 10.78c0 2.76 1.82 5.18 4.57 6.56l-.93 3.47c-.08.3.25.54.51.36l4.1-2.73c.57.08 1.16.12 1.75.12 5.52 0 10-3.48 10-7.78S17.52 3 12 3Z"/></svg> 카카오톡으로 시작하기
            </a>
            <a href="<?php echo $google_enabled ? htmlspecialchars($google_url, ENT_QUOTES) : '#'; ?>" class="btn btn-google" <?php if (!$google_enabled) echo 'onclick="alert(\'구글 로그인이 아직 설정되지 않았습니다. 이메일로 시작하기를 이용해 주세요.\'); return false;"'; ?>>
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.91h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.4Z"/>
                    <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"/>
                    <path fill="#FBBC05" d="M6.39 13.87A6 6 0 0 1 6.08 12c0-.65.11-1.28.31-1.87V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.62Z"/>
                    <path fill="#EA4335" d="M12 6c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.62C7.18 7.76 9.39 6 12 6Z"/>
                </svg>
                Google로 시작하기
            </a>
            <?php if ($apple_enabled): ?>
            <a href="<?php echo htmlspecialchars($apple_url, ENT_QUOTES); ?>" class="btn btn-apple" id="appleLoginButton">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.05 12.54c-.02-2.19 1.79-3.25 1.87-3.3a4.02 4.02 0 0 0-3.17-1.72c-1.33-.14-2.62.8-3.3.8-.7 0-1.74-.78-2.88-.76a4.2 4.2 0 0 0-3.54 2.16c-1.53 2.65-.39 6.54 1.08 8.68.74 1.05 1.6 2.23 2.74 2.19 1.11-.05 1.53-.7 2.87-.7 1.33 0 1.72.7 2.88.67 1.2-.02 1.95-1.05 2.66-2.11a8.6 8.6 0 0 0 1.22-2.48 3.77 3.77 0 0 1-2.43-3.43ZM14.88 6.1a3.83 3.83 0 0 0 .88-2.75 3.9 3.9 0 0 0-2.54 1.31 3.65 3.65 0 0 0-.9 2.65 3.22 3.22 0 0 0 2.56-1.21Z"/>
                </svg>
                Apple로 시작하기
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-email" id="emailToggle" aria-expanded="<?php echo $open_email ? 'true' : 'false'; ?>" aria-controls="emailPanel" onclick="toggleEmail()">
                <i class="bi bi-envelope-fill"></i> 이메일로 시작하기
            </button>
            <div class="email-panel <?php echo $open_email ? 'open' : ''; ?>" id="emailPanel">
                <div class="email-inner">
                    <?php if ($login_error): ?>
                    <div class="err"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>

                    <form id="emailForm" method="post" action="login.php" style="display:flex;flex-direction:column;gap:10px;">
                        <input type="hidden" name="mode" value="email">
                        <input type="hidden" name="url" value="<?php echo htmlspecialchars($after_url, ENT_QUOTES); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($login_csrf_token, ENT_QUOTES); ?>">
                        <input type="hidden" name="recaptcha_token" id="recaptchaToken">

                        <div class="field">
                            <input type="email" name="mb_email" id="mb_email" placeholder="이메일" required autocomplete="username"
                                   value="<?php echo htmlspecialchars($_POST['mb_email'] ?? '', ENT_QUOTES); ?>">
                        </div>

                        <div class="field pw-wrap">
                            <input type="password" name="mb_password" id="mb_password" placeholder="비밀번호" required autocomplete="current-password">
                            <button type="button" class="toggle" onclick="togglePw()" id="pwToggle">보기</button>
                        </div>

                        <div class="row-between">
                            <label><input type="checkbox" name="auto_login" id="auto_login"<?php echo (!empty($is_patient_app) ? ' checked' : ''); ?>> 자동 로그인</label>
                            <a href="password_lost.php">비밀번호 찾기</a>
                        </div>

                        <button type="submit" class="btn btn-primary">로그인</button>
                    </form>
                </div>
            </div>

            <div class="foot-links">
                <a href="<?php echo htmlspecialchars(rtrim(SITE_BASE_PATH, '/'), ENT_QUOTES); ?>/auth/password_lost.php">계정 찾기</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars(rtrim(SITE_BASE_PATH, '/'), ENT_QUOTES); ?>/qna.php">문의하기</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/bbs/register.php">회원가입</a>
            </div>

            <?php if ($recaptcha_enabled) { ?>
            <div class="recaptcha-notice">
                이 페이지는 Google reCAPTCHA의 보호를 받아<br>사용자가 로봇이 아님을 확인합니다.
                <div>
                    <button type="button" class="rc-more" id="rcMore" aria-expanded="false" aria-controls="rcDetail" onclick="toggleRc()">자세히 보기</button>
                </div>
                <div class="recaptcha-detail" id="rcDetail">
                    <p>Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">개인정보처리방침</a> 및 <a href="https://policies.google.com/terms" target="_blank" rel="noopener">서비스 약관</a>이 적용됩니다.</p>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <script>
    function toggleEmail() {
        var panel = document.getElementById('emailPanel');
        var btn = document.getElementById('emailToggle');
        var open = panel.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) setTimeout(function () { document.getElementById('mb_email').focus(); }, 320);
    }
    function togglePw() {
        var pw = document.getElementById('mb_password');
        var btn = document.getElementById('pwToggle');
        if (pw.type === 'password') { pw.type = 'text'; btn.textContent = '숨김'; }
        else { pw.type = 'password'; btn.textContent = '보기'; }
    }
    function toggleRc() {
        var d = document.getElementById('rcDetail');
        var b = document.getElementById('rcMore');
        var open = d.classList.toggle('open');
        b.setAttribute('aria-expanded', open ? 'true' : 'false');
        b.textContent = open ? '접기' : '자세히 보기';
    }
    <?php if ($recaptcha_enabled) { ?>
    (function () {
        var emailForm = document.getElementById('emailForm');
        if (!emailForm) return;
        emailForm.addEventListener('submit', function (e) {
            if (emailForm.dataset.rcDone === '1') return;
            if (typeof grecaptcha === 'undefined') return;
            e.preventDefault();
            var submitted = false;
            var go = function () { if (submitted) return; submitted = true; emailForm.dataset.rcDone = '1'; emailForm.submit(); };
            try {
                grecaptcha.ready(function () {
                    grecaptcha.execute('<?php echo htmlspecialchars($recaptcha_site_key, ENT_QUOTES); ?>', { action: 'login' })
                        .then(function (token) { document.getElementById('recaptchaToken').value = token; go(); })
                        .catch(function () { go(); });
                });
            } catch (err) { go(); }
            setTimeout(go, 6000);
        });
    })();
    <?php } ?>

    document.getElementById('auto_login').addEventListener('change', function () {
        if (this.checked) {
            this.checked = confirm('자동 로그인을 사용하면 다음부터 로그인 정보를 입력하지 않아도 됩니다.\n\n공용 기기에서는 개인정보가 유출될 수 있으니 주의해주세요.\n\n사용하시겠습니까?');
        }
    });
    </script>
</body>
</html>

<?php
/**
 * ============================================================================
 * 서울미녀 커스텀 이메일 로그인
 * ============================================================================
 * 위치 : gangnamunni/auth/login.php
 * 목적 : 그누보드 기본 로그인 스킨 대신, 서울미녀 전용 로그인 화면 제공.
 *        - 이메일 + 비밀번호 로그인 (서버에서 검증 후 그누보드 세션 생성)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/init.php';

global $is_member, $config, $connect, $g5;

// Handle the complete social-login flow on this custom login controller.
// Pass the validated provider explicitly: the callback request must not fall
// through to the ordinary email-login HTML when the request value is altered
// by bootstrap/filter code.
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

    if ($social_provider === 'Kakao' && function_exists('social_check_login_before')) {
        $_GET['provider'] = $_REQUEST['provider'] = $social_provider;
        if (array_key_exists('redirect_to_idp', $social_query)) {
            $_GET['redirect_to_idp'] = $_REQUEST['redirect_to_idp'] = '1';
        }

        if (!defined('G5_SOCIAL_FORCE_SAME_WINDOW')) {
            define('G5_SOCIAL_FORCE_SAME_WINDOW', true);
        }

        error_log(
            '[Kakao flow] controller enter; callback='
            . (isset($_REQUEST['redirect_to_idp']) ? 'yes' : 'no')
            . '; session=' . session_id()
        );
        social_check_login_before($social_provider);

        error_log('[Kakao flow] social_check_login_before() returned unexpectedly');
        http_response_code(500);
        exit('Kakao login handler returned unexpectedly.');
    } elseif ($social_provider === 'Kakao') {
        error_log('[Kakao flow] social_check_login_before() is not loaded');
        http_response_code(500);
        exit('Kakao login module is not loaded.');
    }
}

$bbs_url = defined('G5_BBS_URL') ? G5_BBS_URL : '/bbs';
$g5_url  = defined('G5_URL') ? G5_URL : '';
$member_table = $g5['member_table'] ?? 'g5_member';

// 이메일 로그인 POST CSRF 토큰 (세션 기반). reCAPTCHA 와 별개로 교차 사이트 위조 요청을 차단.
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$login_csrf_token = $_SESSION['login_csrf_token'];

// 로그인 브루트포스 방지: 세션과 무관하게 IP/이메일별 요청 횟수를 제한한다.
function gnu_login_rate_hit($scope, $value, $limit, $window, $record = true) {
    $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir();
    $dir = $base . '/cache/login_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['allowed' => false, 'retry' => 60];
    }
    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '/*.json') ?: [] as $old_file) {
            if (@filemtime($old_file) < time() - 86400 && ($old_fp = @fopen($old_file, 'c+'))) {
                if (@flock($old_fp, LOCK_EX | LOCK_NB)) {
                    if (@filemtime($old_file) < time() - 86400) @unlink($old_file);
                    @flock($old_fp, LOCK_UN);
                }
                @fclose($old_fp);
            }
        }
    }
    $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower((string)$value)) . '.json';
    $fp = @fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        return ['allowed' => false, 'retry' => 60];
    }
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    $now = time();
    $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
    $hits = array_values(array_filter($hits, function ($ts) use ($now, $window) { return (int)$ts > $now - $window; }));
    $retry = count($hits) >= $limit ? max(1, $window - ($now - (int)$hits[0])) : 0;
    if (!$retry && $record) $hits[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(['hits' => $hits]));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return ['allowed' => $retry === 0, 'retry' => $retry];
}

// ===== Google reCAPTCHA v3 — 키는 gangnamunni/.env 에서 로드 (git 에 커밋되지 않음) =====
if (!function_exists('gnu_load_env')) {
    // .env 파서 (# / ; 주석, KEY=VALUE 지원). parse_ini_file 은 # 주석을 못 읽어 직접 파싱.
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
if (!function_exists('gnu_verify_recaptcha')) {
    // reCAPTCHA v3 검증 결과를 상태 문자열로 반환한다.
    //   'skip' : 비밀키 미설정 / 구글 서버 도달 불가 (로그인 rate-limit은 계속 적용)
    //   'fail' : 구글이 토큰을 명시적으로 거부(success=false)하거나 점수가 낮음(봇 의심) → 차단.
    //   'pass' : 사람으로 판단.
    // v3는 비밀번호 인증을 보조하는 소프트 신호이므로, 하드 게이트로 두어 로그인을 막지 않는다.
    function gnu_verify_recaptcha($secret, $token, $remote_ip = '', $expected_action = 'login', $expected_host = '') {
        if ($secret === '') return 'skip';
        if ($token === '') return 'fail'; // 키가 설정된 환경에서 토큰 생략 우회 차단
        $post = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $remote_ip]);
        $body = false;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $body = curl_exec($ch);
            curl_close($ch);
        }
        if ($body === false) { // cURL 없거나 실패 시 폴백
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => 5]]);
            $body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        }
        if (!$body) return 'skip';                // 구글 서버 도달 불가 → 사용자 책임 아님 → 통과
        $r = json_decode($body, true);
        if (!is_array($r)) return 'skip';
        if (empty($r['success'])) return 'fail';  // 토큰 위조/만료 등 명시적 거부
        if (isset($r['score']) && $r['score'] < 0.5) return 'fail'; // v3 점수(0.0~1.0) 봇 의심
        if (($r['action'] ?? '') !== $expected_action) return 'fail';
        if ($expected_host !== '' && strtolower($r['hostname'] ?? '') !== strtolower($expected_host)) return 'fail';
        return 'pass';
    }
}

// 로그인 후 이동할 URL. 외부 URL/프로토콜 상대 URL은 피싱용 open redirect를
// 만들 수 있으므로 현재 호스트의 http(s) URL 또는 / 로 시작하는 로컬 경로만 허용한다.
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

// 로그인 후 이동할 URL (url 파라미터 우선, 없으면 강남언니 홈)
$after_url = $g5_url . '/gangnamunni/index.php';
if (isset($_GET['url']) && $_GET['url'] !== '')  $after_url = gnu_safe_after_url($_GET['url'], $after_url);
if (isset($_POST['url']) && $_POST['url'] !== '') $after_url = gnu_safe_after_url($_POST['url'], $after_url);

// 이미 로그인 상태면 바로 이동
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    header('Location: ' . $after_url);
    exit;
}

$login_error = '';

// ===== 이메일 로그인 처리 =====
// 이메일로 회원을 찾고 그누보드의 비밀번호/세션 함수로 서버 내에서 로그인한다.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'email') {
    $email      = strtolower(trim((string)($_POST['mb_email'] ?? '')));
    $password   = (string)($_POST['mb_password'] ?? '');
    $auto_login = isset($_POST['auto_login']) ? 1 : 0;

    $request_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_rate = ['allowed' => true, 'retry' => 0];
    $email_rate = ['allowed' => true, 'retry' => 0];
    $basic_request_valid = !empty($_POST['csrf_token'])
        && hash_equals($login_csrf_token, (string)$_POST['csrf_token'])
        && $email !== '' && $password !== '';
    if ($basic_request_valid) {
        // 먼저 기존 실패 횟수만 확인하고, 아래의 실제 실패 지점에서만 추가한다.
        $ip_rate = gnu_login_rate_hit('ip', $request_ip, 10, 900, false);
        $email_rate = gnu_login_rate_hit('ip_email', $request_ip . '|' . strtolower($email), 5, 900, false);
    }

    if (empty($_POST['csrf_token']) || !hash_equals($login_csrf_token, (string)$_POST['csrf_token'])) {
        $login_error = '요청이 만료되었습니다. 페이지를 새로고침해주세요.';
    } elseif ($email === '' || $password === '') {
        $login_error = '이메일과 비밀번호를 입력해주세요.';
    } elseif (!$ip_rate['allowed'] || !$email_rate['allowed']) {
        $retry = max((int)$ip_rate['retry'], (int)$email_rate['retry']);
        $login_error = '로그인 시도가 너무 많습니다. ' . $retry . '초 후 다시 시도해주세요.';
    } elseif (gnu_verify_recaptcha($recaptcha_secret, $_POST['recaptcha_token'] ?? '', $request_ip, 'login', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')) === 'fail') {
        gnu_login_rate_hit('ip', $request_ip, 10, 900, true);
        gnu_login_rate_hit('ip_email', $request_ip . '|' . strtolower($email), 5, 900, true);
        $login_error = '자동 방지 확인에 실패했습니다. 잠시 후 다시 시도해주세요.';
    } else {
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
        if (!$mb_id_found || empty($login_mb['mb_id']) || !login_password_check($login_mb, $password, $login_mb['mb_password'])) {
            // 이메일 존재 여부와 비밀번호 오류를 구분하지 않아 계정 수집을 막는다.
            gnu_login_rate_hit('ip', $request_ip, 10, 900, true);
            gnu_login_rate_hit('ip_email', $request_ip . '|' . strtolower($email), 5, 900, true);
            if (function_exists('run_event')) run_event('password_is_wrong', 'login', $login_mb);
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (!empty($login_mb['mb_intercept_date']) && $login_mb['mb_intercept_date'] <= date('Ymd')) {
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (!empty($login_mb['mb_leave_date']) && $login_mb['mb_leave_date'] <= date('Ymd')) {
            $login_error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (function_exists('is_use_email_certify') && is_use_email_certify() && !preg_match('/[1-9]/', $login_mb['mb_email_certify'] ?? '')) {
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
                $key = md5(($_SERVER['SERVER_ADDR'] ?? '') . ($_SERVER['SERVER_SOFTWARE'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '') . $login_mb['mb_password']);
                set_cookie('ck_mb_id', $login_mb['mb_id'], 86400 * 31);
                set_cookie('ck_auto', $key, 86400 * 31);
            } else {
                set_cookie('ck_mb_id', '', 0);
                set_cookie('ck_auto', '', 0);
            }
            if (function_exists('run_event')) run_event('member_login_check', $login_mb, $after_url, false);
            header('Location: ' . $after_url);
            exit;
        }
    }
}

// 로고 / 브랜드 — bbs/login.php(rb.member 스킨)와 동일한 로고 사용
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
// 카카오 버튼은 관리자 설정(소셜로그인 사용 + kakao 서비스 + REST 키)이 모두 갖춰졌을 때만 노출한다.
// 소셜 플러그인 로드 여부와 무관하게 g5_config 값만으로 판단(플러그인 미로드 페이지에서도 정확).
$kakao_services = array_map('trim', explode(',', (string)($config['cf_social_servicelist'] ?? '')));
$kakao_enabled = !empty($config['cf_social_login_use'])
    && in_array('kakao', $kakao_services, true)
    && !empty($config['cf_kakao_rest_key']);
$kakao_url = $g5_url . '/gangnamunni/auth/login.php?provider=kakao&url=' . urlencode($after_url);
// 카카오 미설정 시엔 이메일 폼을 바로 펼쳐, 깨진 카카오 버튼 없이 로그인할 수 있게 한다.
$open_email = $login_error !== '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>로그인 | <?php echo htmlspecialchars($brand_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptcha_site_key, ENT_QUOTES); ?>"></script>
    <style>
        :root { --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES); ?>; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body, html { margin: 0; padding: 0; background: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; color: #1a1a1a; }
        .login-wrap { max-width: 420px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: 20px 24px 36px; }
        .login-top { min-height: 24px; }
        .login-top a { color: #bbb; text-decoration: none; display: inline-flex; align-items: center; }
        /* 상단 로고 영역 — 화면 중앙 위쪽에 위치 */
        .login-hero { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; text-align: center; padding: 24px 0 0; }
        .login-hero img { width: auto; height: auto; max-width: 66%; max-height: 78px; }
        /* 하단 액션 영역 — 로고에서 130px 아래 */
        .login-actions { flex: 0 0 auto; display: flex; flex-direction: column; margin-top: 130px; }
        .btn { width: 100%; height: 54px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn:active { transform: scale(.99); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-kakao { background: #FEE500; color: #191600; }
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
        /* reCAPTCHA 고지 */
        .recaptcha-notice { margin-top: 56px; text-align: left; font-size: 11px; line-height: 1.7; color: #000000; }
        .rc-more { background: none; border: none; color: #999; font-size: 12px; font-weight: 600; cursor: pointer; margin-top: 4px; text-decoration: underline; padding: 4px 6px; }
        .recaptcha-detail { max-height: 0; overflow: hidden; transition: max-height .3s ease; }
        .recaptcha-detail.open { max-height: 120px; }
        .recaptcha-detail p { margin: 8px 0 0; font-size: 11px; line-height: 1.7; color: #b6b6bc; }
        .recaptcha-detail a { color: #888; text-decoration: underline; }
        /* v3 배지는 숨김(고지 문구로 대체 — 구글 정책 허용) */
        .grecaptcha-badge { visibility: hidden; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-top">
            <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/gangnamunni/index.php" aria-label="홈으로">
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
                            <label><input type="checkbox" name="auto_login" id="auto_login"> 자동 로그인</label>
                            <a href="password_lost.php">비밀번호 찾기</a>
                        </div>

                        <button type="submit" class="btn btn-primary">로그인</button>
                    </form>
                </div>
            </div>

            <div class="foot-links">
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/gangnamunni/auth/password_lost.php">계정 찾기</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/gangnamunni/qna.php">문의하기</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/bbs/register.php">회원가입</a>
            </div>

            <div class="recaptcha-notice">
                이 페이지는 Google reCAPTCHA의 보호를 받아<br>사용자가 로봇이 아님을 확인합니다.
                <div>
                    <button type="button" class="rc-more" id="rcMore" aria-expanded="false" aria-controls="rcDetail" onclick="toggleRc()">자세히 보기</button>
                </div>
                <div class="recaptcha-detail" id="rcDetail">
                    <p>Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">개인정보처리방침</a> 및 <a href="https://policies.google.com/terms" target="_blank" rel="noopener">서비스 약관</a>이 적용됩니다.</p>
                </div>
            </div>
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
    // reCAPTCHA v3: 이메일 로그인 제출 시 토큰 발급 후 실제 전송
    // 키/도메인 오류 등으로 토큰 발급이 실패해도 폼은 반드시 제출한다(서버가 검증·안내) → 버튼이 먹통되지 않음
    (function () {
        var emailForm = document.getElementById('emailForm');
        if (!emailForm) return;
        emailForm.addEventListener('submit', function (e) {
            if (emailForm.dataset.rcDone === '1') return;      // 토큰 처리 완료 → 그대로 제출
            if (typeof grecaptcha === 'undefined') return;     // reCAPTCHA 미로드 시 서버 검증에서 처리
            e.preventDefault();
            var submitted = false;
            var go = function () { if (submitted) return; submitted = true; emailForm.dataset.rcDone = '1'; emailForm.submit(); };
            try {
                grecaptcha.ready(function () {
                    grecaptcha.execute('<?php echo htmlspecialchars($recaptcha_site_key, ENT_QUOTES); ?>', { action: 'login' })
                        .then(function (token) { document.getElementById('recaptchaToken').value = token; go(); })
                        .catch(function () { go(); });   // 토큰 실패해도 제출 → 서버가 거부/안내
                });
            } catch (err) { go(); }
            setTimeout(go, 6000);                            // 안전장치: 6초 내 무응답이면 그대로 제출
        });
    })();

    document.getElementById('auto_login').addEventListener('change', function () {
        if (this.checked) {
            this.checked = confirm('자동 로그인을 사용하면 다음부터 로그인 정보를 입력하지 않아도 됩니다.\n\n공용 기기에서는 개인정보가 유출될 수 있으니 주의해주세요.\n\n사용하시겠습니까?');
        }
    });
    </script>
</body>
</html>

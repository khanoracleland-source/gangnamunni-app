<?php
/**
 * ============================================================================
 * 서울미녀 커스텀 로그인 (카카오 우선 + 이메일)
 * ============================================================================
 * 위치 : gangnamunni/auth/login.php
 * 목적 : 그누보드 기본 로그인 스킨 대신, 서울미녀 전용 로그인 화면 제공.
 *        - 카카오 간편 로그인 (그누보드 소셜 로그인 플로우 재사용 — 키 설정 후 동작)
 *        - 이메일 + 비밀번호 로그인 (이메일→mb_id 조회 후 bbs/login_check.php 로 릴레이)
 * 참고 : 소셜 로그인 키는 관리자 설정(g5_config)에 입력하고 cf_social_login_use=1 로
 *        활성화해야 카카오 버튼이 실제로 동작한다. (키 미설정 시 버튼은 노출되나 비동작)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/init.php';

global $is_member, $config, $connect;

$bbs_url = defined('G5_BBS_URL') ? G5_BBS_URL : '/bbs';
$g5_url  = defined('G5_URL') ? G5_URL : '';

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
    // 토큰을 Google 서버에 검증. 성공 + (v3 점수 0.5 이상)이면 true.
    function gnu_verify_recaptcha($secret, $token, $remote_ip = '') {
        if ($token === '' || $secret === '') return false;
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
        if (!$body) return false;
        $r = json_decode($body, true);
        if (empty($r['success'])) return false;
        if (isset($r['score']) && $r['score'] < 0.5) return false; // v3 점수(0.0~1.0)
        return true;
    }
}

// 로그인 후 이동할 URL (url 파라미터 우선, 없으면 강남언니 홈)
$after_url = $g5_url . '/gangnamunni/index.php';
if (isset($_GET['url']) && $_GET['url'] !== '')  $after_url = $_GET['url'];
if (isset($_POST['url']) && $_POST['url'] !== '') $after_url = $_POST['url'];

// 이미 로그인 상태면 바로 이동
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    header('Location: ' . $after_url);
    exit;
}

$login_error = '';
$relay = null; // 이메일→mb_id 변환 성공 시, login_check.php 로 릴레이할 데이터

// ===== 이메일 로그인 처리 =====
// 이메일로 회원 mb_id 를 찾아 그누보드 login_check.php 로 릴레이 → 로그인 로직 전부 재사용.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'email') {
    $email      = trim($_POST['mb_email'] ?? '');
    $password   = (string)($_POST['mb_password'] ?? '');
    $auto_login = isset($_POST['auto_login']) ? 1 : 0;

    if ($email === '' || $password === '') {
        $login_error = '이메일과 비밀번호를 입력해주세요.';
    } elseif (!gnu_verify_recaptcha($recaptcha_secret, $_POST['recaptcha_token'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '')) {
        $login_error = '자동 방지 확인에 실패했습니다. 잠시 후 다시 시도해주세요.';
    } else {
        $mb_id_found = '';
        if ($stmt = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_email = ? LIMIT 1")) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) $mb_id_found = $row['mb_id'];
            mysqli_stmt_close($stmt);
        }
        if ($mb_id_found === '') {
            $login_error = '가입되지 않은 이메일입니다. 이메일을 확인해주세요.';
        } else {
            $relay = [
                'mb_id'      => $mb_id_found,
                'mb_password'=> $password,
                'auto_login' => $auto_login,
                'url'        => $after_url,
            ];
        }
    }
}

// ===== 릴레이: login_check.php 로 자동 전송 (실제 로그인 처리 위임) =====
if ($relay) {
    ?>
    <!DOCTYPE html>
    <html lang="ko"><head><meta charset="utf-8"><title>로그인 중…</title></head>
    <body onload="document.forms[0].submit()">
        <form method="post" action="<?php echo htmlspecialchars($bbs_url, ENT_QUOTES); ?>/login_check.php">
            <input type="hidden" name="mb_id" value="<?php echo htmlspecialchars($relay['mb_id'], ENT_QUOTES); ?>">
            <input type="hidden" name="mb_password" value="<?php echo htmlspecialchars($relay['mb_password'], ENT_QUOTES); ?>">
            <?php if ($relay['auto_login']): ?><input type="hidden" name="auto_login" value="on"><?php endif; ?>
            <input type="hidden" name="url" value="<?php echo htmlspecialchars($relay['url'], ENT_QUOTES); ?>">
            <noscript><p>자동 이동이 되지 않으면 아래 버튼을 눌러주세요.</p><button type="submit">로그인 계속하기</button></noscript>
        </form>
    </body></html>
    <?php
    exit;
}

// 카카오 로그인 진입 URL (그누보드 소셜 플로우)
$kakao_url = $bbs_url . '/login.php?provider=kakao&url=' . urlencode($after_url);
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
// 오류가 있으면 이메일 폼을 처음부터 펼친다
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
        .btn-kakao { background: #FEE500; color: #191600; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-email { background: #191919; color: #fff; margin-top: 10px; }
        .btn-email .bi { font-size: 17px; }
        /* 접히는 이메일 폼 */
        .email-panel { max-height: 0; overflow: hidden; transition: max-height .32s ease; }
        .email-panel.open { max-height: 460px; }
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
        .kakao-ico { width: 19px; height: 19px; }
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
            <!-- 카카오 (기본) -->
            <a href="<?php echo htmlspecialchars($kakao_url, ENT_QUOTES); ?>" class="btn btn-kakao">
                <svg class="kakao-ico" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 1.5C4.86 1.5 1.5 4.14 1.5 7.39c0 2.1 1.4 3.94 3.5 4.98-.15.53-.56 1.97-.64 2.28-.1.38.14.38.29.28.12-.08 1.86-1.26 2.61-1.77.4.06.82.09 1.24.09 4.14 0 7.5-2.64 7.5-5.89S13.14 1.5 9 1.5Z" fill="#191600"/>
                </svg>
                카카오톡으로 시작하기
            </a>

            <!-- 이메일 로그인 토글 -->
            <button type="button" class="btn btn-email" id="emailToggle" aria-expanded="<?php echo $open_email ? 'true' : 'false'; ?>" aria-controls="emailPanel" onclick="toggleEmail()">
                <i class="bi bi-envelope-fill"></i>
                이메일로 로그인
            </button>

            <!-- 접히는 이메일 폼 -->
            <div class="email-panel <?php echo $open_email ? 'open' : ''; ?>" id="emailPanel">
                <div class="email-inner">
                    <?php if ($login_error): ?>
                    <div class="err"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>

                    <form id="emailForm" method="post" action="login.php" style="display:flex;flex-direction:column;gap:10px;">
                        <input type="hidden" name="mode" value="email">
                        <input type="hidden" name="url" value="<?php echo htmlspecialchars($after_url, ENT_QUOTES); ?>">
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
                            <a href="<?php echo htmlspecialchars($bbs_url, ENT_QUOTES); ?>/password_lost.php">비밀번호 찾기</a>
                        </div>

                        <button type="submit" class="btn btn-primary">로그인</button>
                    </form>
                </div>
            </div>

            <div class="foot-links">
                <a href="<?php echo htmlspecialchars($bbs_url, ENT_QUOTES); ?>/password_lost.php">계정 찾기</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/gangnamunni/qna.php">문의하기</a>
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

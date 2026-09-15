<?php
/**
 * ============================================================================
 * 서울미녀 커스텀 비밀번호 찾기 (이메일 재설정)
 * ============================================================================
 * 위치 : gangnamunni/auth/password_lost.php
 * 목적 : 그누보드 기본 password_lost.php(구 스킨) 대신, login/register 와 동일한
 *        서울미녀 전용 디자인 제공.
 * 흐름 : 이메일 입력 → 임시비밀번호를 Gnuboard/HMS 계정에 즉시 반영
 *        → 같은 임시비밀번호를 이메일로 발송.
 * 보안 : - CSRF 토큰 검증.
 *        - 가입 여부와 무관하게 항상 동일한 성공 메시지 노출(이메일 존재 여부 노출 방지).
 *        - 비밀번호 해시는 코어 get_encrypt_string() 사용 → login_check.php 검증과 일치.
 * ============================================================================
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mail_helper.php';

global $is_member, $config, $connect, $rb_builder, $g5;

$bbs_url = defined('G5_BBS_URL') ? G5_BBS_URL : '/bbs';
$g5_url  = defined('G5_URL') ? G5_URL : '';
$login_url = $g5_url . '/gangnamunni/auth/login.php';
$member_table = $g5['member_table'] ?? 'g5_member';

function gnu_reset_rate_hit($scope, $value, $limit = 3, $window = 600) {
    $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir();
    $dir = $base . '/cache/password_reset_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return ['allowed' => false, 'retry' => 60];
    $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower((string)$value)) . '.json';
    $fp = @fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return ['allowed' => false, 'retry' => 60]; }
    $data = json_decode(stream_get_contents($fp) ?: '', true);
    $now = time();
    $hits = array_values(array_filter(is_array($data['hits'] ?? null) ? $data['hits'] : [], function ($ts) use ($now, $window) { return (int)$ts > $now - $window; }));
    $retry = count($hits) >= $limit ? max(1, $window - ($now - (int)$hits[0])) : 0;
    if (!$retry) $hits[] = $now;
    ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode(['hits' => $hits])); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return ['allowed' => $retry === 0, 'retry' => $retry];
}

// 이미 로그인 상태면 홈으로
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    header('Location: ' . $g5_url . '/gangnamunni/index.php');
    exit;
}

// CSRF 토큰
if (empty($_SESSION['pwlost_csrf_token'])) {
    $_SESSION['pwlost_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['pwlost_csrf_token'];

$done = false;      // 처리 완료(성공 메시지 노출) 여부
$error = '';
$request_id = '';

function gnu_secure_temporary_password($length = 12) {
    $groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*-_'];
    $chars = [];
    foreach ($groups as $group) $chars[] = $group[random_int(0, strlen($group) - 1)];
    $all = implode('', $groups);
    while (count($chars) < $length) $chars[] = $all[random_int(0, strlen($all) - 1)];
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['mb_email'] ?? ''));

    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, (string)$_POST['csrf_token'])) {
        $error = '요청이 만료되었습니다. 페이지를 새로고침해주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '올바른 이메일 주소를 입력해주세요.';
    } else {
        if (time() < (int)($_SESSION['pwlost_last'] ?? 0) + 60) {
            $error = '잠시 후 다시 시도해주세요.';
        } else {
            $request_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ip_rate = gnu_reset_rate_hit('ip', $request_ip);
            $email_ip_rate = gnu_reset_rate_hit('email_ip', strtolower($email) . '|' . $request_ip);
            if (!$ip_rate['allowed'] || !$email_ip_rate['allowed']) {
                $error = '잠시 후 다시 시도해주세요.';
            } else {
                $_SESSION['pwlost_last'] = time();
                $request_id = bin2hex(random_bytes(6));

            // 해당 이메일의 활성 회원 조회(중복 이메일이면 안전하게 건너뜀)
            $rows = [];
            if ($st = mysqli_prepare($connect, "SELECT mb_no, mb_id, mb_name, mb_nick, mb_email, mb_leave_date FROM {$member_table} WHERE LOWER(mb_email) = ?")) {
                mysqli_stmt_bind_param($st, 's', $email);
                mysqli_stmt_execute($st);
                $res = mysqli_stmt_get_result($st);
                while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                mysqli_stmt_close($st);
            }

            // 정확히 1건 + 탈퇴 아님 + 관리자 아님인 경우에만 실제 발송한다.
            // 그 외(미가입/중복/탈퇴/관리자)에는 아무 것도 하지 않지만, 사용자에게는
            // 동일한 성공 메시지를 보여 이메일 가입 여부가 드러나지 않게 한다.
            if (count($rows) === 1 && empty($rows[0]['mb_leave_date'])
                && !(function_exists('is_admin') && is_admin($rows[0]['mb_id']))) {
                $mb = $rows[0];

                // Gnuboard va HMS turli password hash formatlaridan foydalanadi.
                $change_password = gnu_secure_temporary_password(12);
                $gnuboard_hash = get_encrypt_string($change_password);
                $hms_hash = password_hash($change_password, PASSWORD_BCRYPT);
                $brand = $config['cf_title'] ?? '서울미녀';
                $subject = '[' . $brand . '] 비밀번호 재설정 안내';
                $body = "<div style='font-family:sans-serif;font-size:15px;color:#1a1a1a;line-height:1.7;max-width:560px'>"
                      . htmlspecialchars($mb['mb_name']) . "님, 비밀번호 재설정을 요청하셨습니다.<br><br>"
                      . "아래 임시 비밀번호로 바로 로그인해주세요.<br><br>"
                      . "<div style='padding:16px 20px;background:#f7f7f7;border-radius:8px'>"
                      . "임시 비밀번호 : <strong style='font-size:20px;color:#AE1932;letter-spacing:2px'>" . $change_password . "</strong></div><br>"
                      . "<span style='font-size:13px;color:#999'>로그인 후 계정 설정에서 비밀번호를 다시 변경해주세요.</span>"
                      . "</div>";

                // Email yuborish muvaffaqiyatli bo'lsagina ikkala DB o'zgarishini saqlaymiz.
                $password_saved = false;
                mysqli_begin_transaction($connect);
                if ($up = mysqli_prepare($connect, "UPDATE {$member_table} SET mb_password = ?, mb_lost_certify = '' WHERE mb_no = ?")) {
                    mysqli_stmt_bind_param($up, 'si', $gnuboard_hash, $mb['mb_no']);
                    mysqli_stmt_execute($up);
                    $password_saved = mysqli_stmt_affected_rows($up) === 1;
                    mysqli_stmt_close($up);
                }

                if ($password_saved && ($hms = mysqli_prepare($connect, "UPDATE users SET password = ?, updated_at = NOW() WHERE LOWER(email) = LOWER(?)"))) {
                    mysqli_stmt_bind_param($hms, 'ss', $hms_hash, $mb['mb_email']);
                    mysqli_stmt_execute($hms);
                    mysqli_stmt_close($hms);
                }

                $result = $password_saved && function_exists('mail_send')
                    ? mail_send(0, $mb['mb_email'], $subject, $body)
                    : ['error' => $password_saved ? 'mail_send is unavailable' : 'password could not be saved'];

                // Mailcow helper ishlamasa, mavjud Gnuboard mailer yo'lini fallback sifatida sinaydi.
                // Birinchi SMTP qabul qilgan bo'lsa fallback ishlamaydi va dublikat email ketmaydi.
                if ($password_saved && !(is_array($result) && !empty($result['success']))) {
                    $primary_error = is_array($result) ? ($result['error'] ?? 'unknown') : 'no result';
                    if (defined('G5_LIB_PATH') && file_exists(G5_LIB_PATH . '/mailer.lib.php')) {
                        include_once G5_LIB_PATH . '/mailer.lib.php';
                    }
                    if (function_exists('mailer')) {
                        $from_name = $config['cf_admin_email_name'] ?? ($config['cf_title'] ?? '서울미녀');
                        $from_email = $config['cf_admin_email'] ?? '';
                        $fallback_ok = $from_email !== ''
                            ? mailer($from_name, $from_email, $mb['mb_email'], $subject, $body, 1)
                            : false;
                        if ($fallback_ok) {
                            $result = ['success' => true, 'transport' => 'gnuboard_mailer'];
                            error_log('[password_lost][' . $request_id . '] primary SMTP 실패, Gnuboard fallback 성공: ' . $primary_error);
                        } else {
                            $result = ['error' => 'primary=' . $primary_error . '; fallback failed'];
                        }
                    }
                }
                if (is_array($result) && !empty($result['success'])) {
                    mysqli_commit($connect);
                    error_log('[password_lost][' . $request_id . '] SMTP 접수 완료');
                } else {
                    mysqli_rollback($connect);
                    error_log('[password_lost][' . $request_id . '] 메일 발송 실패: ' . (is_array($result) ? ($result['error'] ?? 'unknown') : 'no result'));
                }
            } else {
                error_log('[password_lost][' . $request_id . '] 발송 대상 없음 또는 제외된 계정');
            }

            $done = true; // 존재 여부와 무관하게 동일 안내
            }
        }
    }
}

// 로고 / 브랜드 — login.php 와 동일
$_logo_ver = defined('G5_SERVER_TIME') ? G5_SERVER_TIME : time();
if (!empty($rb_builder['bu_logo_pc'])) {
    $logo_src = $g5_url . '/data/logos/pc?ver=' . $_logo_ver;
} else {
    $_theme_url = defined('G5_THEME_URL') ? G5_THEME_URL : ($g5_url . '/theme/rb.basic');
    $logo_src = $_theme_url . '/rb.img/logos/pc.png?ver=' . $_logo_ver;
}
$brand_name = function_exists('getTheme') ? getTheme('site_name', '서울미녀') : '서울미녀';
$primary    = function_exists('getTheme') ? getTheme('primary_color', '#AE1932') : '#AE1932';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>비밀번호 찾기 | <?php echo htmlspecialchars($brand_name); ?></title>
    <style>
        :root { --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES); ?>; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body, html { margin: 0; padding: 0; background: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; color: #1a1a1a; }
        .login-wrap { max-width: 420px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: 20px 24px 36px; }
        .login-top { min-height: 24px; }
        .login-top a { color: #bbb; text-decoration: none; display: inline-flex; align-items: center; }
        .login-hero { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 24px 0 0; }
        .login-hero img { width: auto; height: auto; max-width: 66%; max-height: 78px; }
        .login-actions { flex: 0 0 auto; display: flex; flex-direction: column; margin-top: 90px; }
        .page-title { font-size: 20px; font-weight: 800; margin: 0 0 6px; }
        .page-desc { font-size: 14px; color: #888; line-height: 1.6; margin: 0 0 22px; }
        .btn { width: 100%; height: 54px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border-radius: 12px; }
        .btn:active { transform: scale(.99); }
        .btn-primary { background: var(--primary); color: #fff; }
        .field input { width: 100%; height: 52px; border: 1px solid #e9e9ee; border-radius: 12px; padding: 0 16px; font-size: 15px; background: #fafafb; }
        .field input:focus { outline: none; border-color: var(--primary); background: #fff; }
        form { display: flex; flex-direction: column; gap: 10px; }
        .err { background: #fdecef; color: #c0293f; font-size: 13px; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
        .ok-box { background: #eef7f0; color: #1a7f37; font-size: 14px; line-height: 1.6; border-radius: 12px; padding: 18px 18px; margin-bottom: 18px; }
        .foot-links { display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 40px; }
        .foot-links a { font-size: 14px; font-weight: 600; color: #666; text-decoration: none; }
        .foot-links .sep { color: #dcdce1; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-top">
            <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES); ?>" aria-label="로그인으로">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                </svg>
            </a>
        </div>

        <div class="login-hero">
            <img src="<?php echo htmlspecialchars($logo_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand_name); ?>">
        </div>

        <div class="login-actions">
            <?php if ($done): ?>
                <div class="ok-box">
                    입력하신 이메일이 가입되어 있다면<br>비밀번호 재설정 안내 메일을 보내드렸습니다.<br>
                    메일함(스팸함 포함)을 확인해주세요.
                    <?php if ($request_id): ?><br><small style="color:#718079">요청 번호: <?php echo htmlspecialchars($request_id, ENT_QUOTES); ?></small><?php endif; ?>
                </div>
                <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES); ?>" class="btn btn-primary">로그인으로 돌아가기</a>
            <?php else: ?>
                <h1 class="page-title">비밀번호 찾기</h1>
                <p class="page-desc">가입하신 이메일을 입력하시면<br>비밀번호를 재설정할 수 있는 링크를 보내드립니다.</p>

                <?php if ($error): ?>
                <div class="err"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" action="password_lost.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                    <div class="field">
                        <input type="email" name="mb_email" placeholder="이메일" required autocomplete="email"
                               value="<?php echo htmlspecialchars($_POST['mb_email'] ?? '', ENT_QUOTES); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">재설정 메일 받기</button>
                </form>
            <?php endif; ?>

            <div class="foot-links">
                <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES); ?>">로그인</a>
                <span class="sep">|</span>
                <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/gangnamunni/auth/register.php">회원가입</a>
            </div>
        </div>
    </div>
</body>
</html>

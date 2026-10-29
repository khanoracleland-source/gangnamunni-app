<?php
/**
 * account_security.php — JSON for the Next.js 내 정보 수정 pages (called by seoulbeauty-api with the member's own cookie).
 *
 *   GET  ?action=csrf                                              -> {token}   (session CSRF token for withdraw.php / notification_settings.php)
 *   POST {action:'password', current_pw, new_pw, new_pw_confirm}   -> profile_edit.php action=pw
 *   POST {action:'email_send', new_email}                          -> profile_edit.php action=send_email_cert
 *   POST {action:'email_verify', code}                             -> profile_edit.php action=verify_email_cert
 *
 * Same rules and side effects as profile_edit.php. Differences (deliberate): the cert code comes from random_int and a wrong
 * code is allowed 5 times; every reply is JSON. Browsers cannot call this cross-site: it needs X-Requested-With
 * (a CORS preflight that bootstrap.php does not allow) and a same-host Origin when one is sent.
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once __DIR__ . '/bootstrap.php';
// Gnuboard's common.php may have left text/html behind; the reply must be JSON.
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') jsonError('잘못된 요청입니다.', 403);
$acct_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($acct_origin !== '') {
    $acct_origin_host = strtolower((string)parse_url($acct_origin, PHP_URL_HOST));
    $acct_self_host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($acct_origin_host === '' || $acct_origin_host !== $acct_self_host) jsonError('잘못된 요청입니다.', 403);
}

$authUser = getAuthUser(true);
$member_id = (string)$authUser['user_id'];
if ($member_id === '') jsonError('로그인이 필요합니다.', 401);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'csrf') jsonError('잘못된 요청입니다.', 400);
    jsonSuccess(['token' => generateCsrfToken()]);
}
requireMethod('POST');
$body = getJsonBody();
if (!is_array($body)) jsonError('잘못된 요청입니다.', 400);
$action = (string)($body['action'] ?? '');

function acct_fail($field, $message, $status = 422) {
    jsonResponse(['success' => false, 'field' => $field, 'message' => $message], $status);
}

if ($action === 'password') {
    $current_pw = (string)($body['current_pw'] ?? '');
    $new_pw = (string)($body['new_pw'] ?? '');
    $new_pw_confirm = (string)($body['new_pw_confirm'] ?? '');
    if ($new_pw !== $new_pw_confirm) acct_fail('new_pw_confirm', '새 비밀번호가 일치하지 않습니다.');
    if (strlen($new_pw) < 6) acct_fail('new_pw', '비밀번호는 6자 이상이어야 합니다.');

    $stmt = mysqli_prepare($connect, 'SELECT mb_password FROM g5_member WHERE mb_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $member_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) jsonError('회원 정보를 찾을 수 없습니다.', 404);
    $stored = (string)($row['mb_password'] ?? '');
    $ok = function_exists('check_password') ? check_password($current_pw, $stored) : password_verify($current_pw, $stored);
    if (!$ok) acct_fail('current_pw', '현재 비밀번호가 올바르지 않습니다. 비밀번호를 재설정했다면 이메일로 받은 12자리 임시 비밀번호를 입력해주세요.');

    $new_hash = function_exists('create_hash') ? create_hash($new_pw) : password_hash($new_pw, PASSWORD_DEFAULT);
    $ps = mysqli_prepare($connect, 'UPDATE g5_member SET mb_password = ? WHERE mb_id = ?');
    mysqli_stmt_bind_param($ps, 'ss', $new_hash, $member_id);
    if (!mysqli_stmt_execute($ps)) { mysqli_stmt_close($ps); jsonError('비밀번호를 저장하지 못했습니다.', 500); }
    mysqli_stmt_close($ps);

    // HMS(Laravel) password too, then drop every credential (this device included), exactly like profile_edit.php.
    if (function_exists('sync_hms_password_by_mapping')) sync_hms_password_by_mapping($connect, $member_id, $new_pw);
    if (function_exists('revoke_all_auth_credentials_for')) revoke_all_auth_credentials_for($member_id);
    jsonSuccess([], '비밀번호가 변경되었습니다. 새 비밀번호로 다시 로그인해주세요.');
}

if ($action === 'email_send') {
    $new_email = trim((string)($body['new_email'] ?? ''));
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) acct_fail('new_email', '올바른 이메일 주소를 입력해주세요.');
    $dup = mysqli_prepare($connect, 'SELECT mb_id FROM g5_member WHERE mb_email = ? AND mb_id != ?');
    mysqli_stmt_bind_param($dup, 'ss', $new_email, $member_id);
    mysqli_stmt_execute($dup);
    mysqli_stmt_store_result($dup);
    $is_dup = mysqli_stmt_num_rows($dup) > 0;
    mysqli_stmt_close($dup);
    if ($is_dup) acct_fail('new_email', '이미 사용 중인 이메일입니다.', 409);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['email_cert_code'] = $code;
    $_SESSION['email_cert_new'] = $new_email;
    $_SESSION['email_cert_expire'] = time() + 180;
    $_SESSION['email_cert_tries'] = 0;
    $subject = '[서울미녀] 이메일 인증 코드';
    $content = "이메일 변경 인증 코드: <b>{$code}</b><br><br>3분 이내에 입력해주세요.";
    $mailer_path = defined('G5_PHPMAILER_PATH') ? G5_PHPMAILER_PATH : dirname(__DIR__, 3) . '/plugin/PHPMailer';
    if (file_exists($mailer_path . '/PHPMailerAutoload.php')) {
        include_once $mailer_path . '/PHPMailerAutoload.php';
        $pm = new PHPMailer(true);
        try {
            $pm->IsSMTP();
            $pm->Host = defined('G5_SMTP') ? G5_SMTP : 'localhost';
            $pm->Port = defined('G5_SMTP_PORT') ? G5_SMTP_PORT : 587;
            $pm->SMTPAuth = defined('G5_SMTP_USER') && G5_SMTP_USER;
            $pm->Username = defined('G5_SMTP_USER') ? G5_SMTP_USER : '';
            $pm->Password = defined('G5_SMTP_PASS') ? G5_SMTP_PASS : '';
            $pm->SMTPSecure = 'tls';
            $pm->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $pm->CharSet = 'UTF-8';
            $pm->isHTML(true);
            $pm->setFrom($pm->Username, '서울미녀');
            $pm->addAddress($new_email);
            $pm->Subject = $subject;
            $pm->Body = $content;
            $pm->send();
        } catch (Exception $e) { /* same as profile_edit.php: the code stays valid in the session */ }
    }
    jsonSuccess(['email' => $new_email, 'expiresIn' => 180], '인증 코드를 발송했습니다.');
}

if ($action === 'email_verify') {
    $input_code = trim((string)($body['code'] ?? ''));
    $clear = function () { unset($_SESSION['email_cert_code'], $_SESSION['email_cert_new'], $_SESSION['email_cert_expire'], $_SESSION['email_cert_tries']); };
    if (empty($_SESSION['email_cert_code'])) acct_fail('code', '인증 코드를 먼저 발송해주세요.');
    if (time() > (int)($_SESSION['email_cert_expire'] ?? 0)) { $clear(); acct_fail('code', '인증 코드가 만료되었습니다. 다시 발송해주세요.'); }
    if (!hash_equals((string)$_SESSION['email_cert_code'], $input_code)) {
        $_SESSION['email_cert_tries'] = (int)($_SESSION['email_cert_tries'] ?? 0) + 1;
        if ($_SESSION['email_cert_tries'] >= 5) { $clear(); acct_fail('code', '인증 시도 횟수를 초과했습니다. 코드를 다시 발송해주세요.', 429); }
        acct_fail('code', '인증 코드가 올바르지 않습니다.');
    }
    $new_email = (string)($_SESSION['email_cert_new'] ?? '');
    // The address may have been taken since the code was sent.
    $dup = mysqli_prepare($connect, 'SELECT mb_id FROM g5_member WHERE mb_email = ? AND mb_id != ?');
    mysqli_stmt_bind_param($dup, 'ss', $new_email, $member_id);
    mysqli_stmt_execute($dup);
    mysqli_stmt_store_result($dup);
    $is_dup = mysqli_stmt_num_rows($dup) > 0;
    mysqli_stmt_close($dup);
    if ($is_dup) { $clear(); acct_fail('code', '이미 사용 중인 이메일입니다.', 409); }
    $stmt = mysqli_prepare($connect, 'UPDATE g5_member SET mb_email = ? WHERE mb_id = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $new_email, $member_id);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$saved) jsonError('이메일을 저장하지 못했습니다.', 500);
    $clear();
    jsonSuccess(['email' => $new_email], '이메일이 변경되었습니다.');
}

jsonError('알 수 없는 요청입니다.', 400);

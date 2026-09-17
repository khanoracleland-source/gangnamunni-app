<?php
/**
 * ============================================================================
 * 서울미녀 커스텀 회원가입 (이메일 인증)
 * ============================================================================
 * 위치 : gangnamunni/auth/register.php
 * 흐름 : 이메일 → 인증코드(메일 발송) → 비밀번호 → 비밀번호 확인 → 가입
 *   - 아이디는 입력받지 않고 내부적으로 자동 생성(sb_xxxx). 이메일이 로그인 식별자.
 *   - 이메일 인증코드 방식은 profile_edit.php 와 동일.
 *   - 가입 후 서버에서 그누보드 세션을 생성하여 자동 로그인 → 홈.
 * ============================================================================
 */
 
require_once __DIR__ . '/../includes/init.php';
require_once G5_LIB_PATH . '/register.lib.php';

global $is_member, $config, $connect, $rb_builder, $g5;

$bbs_url = defined('G5_BBS_URL') ? G5_BBS_URL : '/bbs';
$g5_url  = defined('G5_URL') ? G5_URL : '';
$home_url = $g5_url . '/app/index.php';
$member_table = $g5['member_table'] ?? 'g5_member';

// 회원가입 POST/AJAX CSRF 토큰
if (empty($_SESSION['reg_csrf_token'])) {
    $_SESSION['reg_csrf_token'] = bin2hex(random_bytes(32));
}
$reg_csrf_token = $_SESSION['reg_csrf_token'];

// 휴대폰 SMS 인증: 문자 발송은 요금이 발생하므로 결제/공급사 준비 전까지 기본 비활성.
// 준비가 끝나면 (1) 아래를 true 로 바꾸고 (2) gangnamunni/.env 에 SMS_* 키를 넣으면
// 즉시 SMS OTP(인증문자 발송+코드확인)가 가입 흐름에 적용된다. (헬퍼: includes/sms_helper.php)
// 비활성 상태에서도 휴대폰 번호는 필수로 입력·저장(mb_hp)되어 예약 알림 등에 사용할 수 있다.
$enable_sms_verify = false;

// 인증메일 남용 방지. 파일+flock을 사용해 세션/브라우저가 바뀌어도
// 동일 IP/이메일의 발송 제한이 서버 전체에 적용되게 한다.
function gnu_reg_rate_hit($scope, $value, $limit, $window, $cooldown = 0) {
    $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir();
    $dir = $base . '/cache/register_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['allowed' => false, 'retry' => 60]; // 저장 불가 시 fail closed
    }
    // 만료된 rate-limit 파일이 무한히 쌓이지 않도록 요청의 1%에서 24시간 이전 파일을 정리한다.
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
    $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower($value)) . '.json';
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
    $last = $hits ? (int)end($hits) : 0;
    $retry = $cooldown > 0 && $last > $now - $cooldown ? $cooldown - ($now - $last) : 0;
    if (!$retry && count($hits) >= $limit) $retry = max(1, $window - ($now - (int)$hits[0]));
    if (!$retry) $hits[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(['hits' => $hits]));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return ['allowed' => $retry === 0, 'retry' => $retry];
}

// 이미 로그인 상태면 홈으로
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    header('Location: ' . $home_url);
    exit;
}

// ===== 인증코드 메일 발송 헬퍼 =====
// 사이트에서 실제 운영 중인 mail_send()(Mailcow · mail.seoulbeauty.app STARTTLS)를 재사용한다.
// G5_SMTP(=localhost) 설정은 로컬/운영 모두 신뢰할 수 없으므로 사용하지 않는다.
require_once __DIR__ . '/../includes/mail_helper.php';
function gnu_send_cert_mail($to_email, $code) {
    if (!function_exists('mail_send')) return false;
    $subject = '[서울미녀] 회원가입 이메일 인증 코드';
    $body    = "<div style='font-family:sans-serif;font-size:15px;color:#1a1a1a;line-height:1.7'>"
             . "회원가입 인증 코드입니다.<br><br>"
             . "<div style='font-size:28px;font-weight:800;letter-spacing:4px;color:#AE1932'>{$code}</div><br>"
             . "10분 이내에 입력해주세요."
             . "</div>";
    // 계정 인덱스 0 = admin@seoulbeauty.app
    $result = mail_send(0, $to_email, $subject, $body);
    if (is_array($result) && !empty($result['success'])) return true;
    // 실패 사유 로깅 (코드는 세션에 저장돼 있으나, 호출부에서 사용자에게 실패를 알린다)
    error_log('[register] 인증코드 메일 발송 실패: ' . (is_array($result) ? ($result['error'] ?? 'unknown') : 'no result'));
    return false;
}

// ===== AJAX: 인증코드 발송 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'send_code') {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals($reg_csrf_token, $csrf)) {
        echo json_encode(['ok' => false, 'msg' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']); exit;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => '올바른 이메일 주소를 입력해주세요.']); exit;
    } elseif (function_exists('prohibit_mb_email') && ($email_policy_error = prohibit_mb_email($email))) {
        echo json_encode(['ok' => false, 'msg' => $email_policy_error]); exit;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_rate = gnu_reg_rate_hit('ip', $ip, 5, 600, 0);
    if (!$ip_rate['allowed']) {
        echo json_encode(['ok' => false, 'msg' => '인증 메일 요청이 너무 많습니다. ' . $ip_rate['retry'] . '초 후 다시 시도해주세요.']); exit;
    }
    $email_rate = gnu_reg_rate_hit('email', $email, 3, 600, 60);
    if (!$email_rate['allowed']) {
        echo json_encode(['ok' => false, 'msg' => '잠시 후 다시 요청해주세요. (' . $email_rate['retry'] . '초)']); exit;
    }
    // 계정 존재 여부와 관계없이 동일한 SMTP 경로를 탄다.
    // 이로써 응답 시간 차이로 가입 여부를 추측하는 timing enumeration을 막는다.
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // 메일 발송이 성공한 경우에만 세션에 코드를 저장한다.
    // (발송 실패 시 코드를 남기면 사용자가 받지도 못한 코드로 막혀버린다)
    // 새 코드를 발급하므로 이전 verified 플래그는 무효화한다(새 코드로 재검증 요구).
    unset($_SESSION['reg_cert_verified_email'], $_SESSION['reg_cert_verified_at']);
    if (!gnu_send_cert_mail($email, $code)) {
        unset($_SESSION['reg_cert_code'], $_SESSION['reg_cert_email'], $_SESSION['reg_cert_expire']);
        echo json_encode(['ok' => false, 'msg' => '인증코드 발송에 실패했습니다. 잠시 후 다시 시도해주세요.']); exit;
    }
    $_SESSION['reg_cert_code']   = $code;
    $_SESSION['reg_cert_email']  = $email;
    // 이 코드는(프로필 수정과 달리) 코드 입력 후에도 비밀번호 작성·전화번호 입력·
    // 약관 동의를 모두 마쳐야 최종 제출된다. 3분(profile_edit.php의 "코드만 즉시
    // 확인" 케이스에서 가져온 값)은 전체 가입 폼을 채우기엔 너무 짧아, 다수 사용자가
    // 마지막 제출 시점에 "인증코드가 만료되었습니다" 오류를 겪는 원인이었다.
    $_SESSION['reg_cert_expire'] = time() + 600;
    $_SESSION['reg_cert_attempts'] = 0;
    echo json_encode(['ok' => true, 'msg' => '인증코드를 이메일로 발송했습니다. (10분 내 입력)']);
    exit;
}

// ===== AJAX: 휴대폰 SMS 인증코드 발송 (요금 발생 → $enable_sms_verify 로 게이트) =====
// 결제/공급사 준비 전에는 비활성이며, 준비 후 $enable_sms_verify=true + .env SMS_* 설정만으로 동작.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'send_sms_code') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$enable_sms_verify) { echo json_encode(['ok' => false, 'msg' => '휴대폰 인증은 현재 준비 중입니다.']); exit; }
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals($reg_csrf_token, $csrf)) {
        echo json_encode(['ok' => false, 'msg' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']); exit;
    }
    $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
    if (!preg_match('/^01[0-9]{8,9}$/', $phone)) {
        echo json_encode(['ok' => false, 'msg' => '올바른 휴대폰 번호를 입력해주세요.']); exit;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!gnu_reg_rate_hit('sms_ip', $ip, 5, 600, 0)['allowed']) {
        echo json_encode(['ok' => false, 'msg' => '인증 요청이 너무 많습니다. 잠시 후 다시 시도해주세요.']); exit;
    }
    $ph_rate = gnu_reg_rate_hit('sms_phone', $phone, 3, 600, 60);
    if (!$ph_rate['allowed']) {
        echo json_encode(['ok' => false, 'msg' => '잠시 후 다시 요청해주세요. (' . $ph_rate['retry'] . '초)']); exit;
    }
    $sms_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    require_once __DIR__ . '/../includes/sms_helper.php';
    $sent = function_exists('sms_send')
        ? sms_send($phone, '[서울미녀] 인증번호 [' . $sms_code . '] 를 10분 이내에 입력해주세요.')
        : ['success' => false, 'error' => 'SMS helper 없음'];
    if (!(is_array($sent) && !empty($sent['success']))) {
        error_log('[register] SMS 발송 실패: ' . (is_array($sent) ? ($sent['error'] ?? 'unknown') : 'no result'));
        echo json_encode(['ok' => false, 'msg' => '인증문자 발송에 실패했습니다. 잠시 후 다시 시도해주세요.']); exit;
    }
    $_SESSION['reg_sms_code']   = $sms_code;
    $_SESSION['reg_sms_phone']  = $phone;
    // 이메일 코드와 동일한 이유(비밀번호·약관 동의까지 마쳐야 최종 제출)로 10분으로 통일.
    $_SESSION['reg_sms_expire'] = time() + 600;
    echo json_encode(['ok' => true, 'msg' => '인증번호를 문자로 발송했습니다. (10분 내 입력)']); exit;
}

$reg_error = '';
$reg_request_id = '';

if (!function_exists('gnu_register_duplicate_log')) {
    function gnu_register_duplicate_log($connect, $member_table, $email, $stage, $request_id) {
        $database = 'unknown';
        if ($db_result = @mysqli_query($connect, 'SELECT DATABASE() AS db_name')) {
            $db_row = mysqli_fetch_assoc($db_result);
            $database = (string)($db_row['db_name'] ?? 'unknown');
            mysqli_free_result($db_result);
        }
        $account_hash = substr(hash('sha256', strtolower(trim((string)$email))), 0, 16);
        error_log('[register][' . $request_id . '] duplicate gate; stage=' . $stage
            . '; database=' . preg_replace('/[^a-zA-Z0-9_-]/', '', $database)
            . '; table=' . preg_replace('/[^a-zA-Z0-9_]/', '', (string)$member_table)
            . '; account=' . $account_hash);
    }
}

if (!function_exists('reg_email_otp_gate')) {
    /**
     * 이메일 OTP 게이트 — verified/consumed 상태를 분리한다.
     *
     * 기존 문제: OTP 를 "DB insert 성공 후"에야 폐기 → insert/훅 실패 시 만료 전까지 재사용 가능.
     * 개선: 코드가 정확히 일치하는 즉시 원본 코드를 소비(폐기)하고, 별도의 verified 플래그를
     *       세팅한다. 이후 단계(비번/휴대폰 검증)에서 실패해 재제출하더라도 원본 OTP 는
     *       다시 쓸 수 없고, 30분짜리 verified 플래그(같은 이메일·같은 세션)로만 진행된다.
     *
     * @return string '' = 통과, 그 외 = 오류 메시지
     */
    function reg_email_otp_gate($email) {
        // 이미 검증됨(같은 이메일·30분 이내) → 코드 없이 통과
        if (!empty($_SESSION['reg_cert_verified_email'])
            && strtolower((string)$_SESSION['reg_cert_verified_email']) === $email
            && time() <= (int)($_SESSION['reg_cert_verified_at'] ?? 0) + 1800) {
            return '';
        }
        $code = trim((string)($_POST['cert_code'] ?? ''));
        if (empty($_SESSION['reg_cert_code']) || strtolower((string)($_SESSION['reg_cert_email'] ?? '')) !== $email) {
            return '이메일 인증을 먼저 진행해주세요.';
        }
        if (time() > (int)($_SESSION['reg_cert_expire'] ?? 0)) {
            return '인증코드가 만료되었습니다. 다시 발송해주세요.';
        }
        if (!hash_equals((string)$_SESSION['reg_cert_code'], $code)) {
            $_SESSION['reg_cert_attempts'] = (int)($_SESSION['reg_cert_attempts'] ?? 0) + 1;
            if ($_SESSION['reg_cert_attempts'] >= 5) {
                unset($_SESSION['reg_cert_code'], $_SESSION['reg_cert_email'], $_SESSION['reg_cert_expire'], $_SESSION['reg_cert_attempts']);
                return '인증코드 입력 횟수를 초과했습니다. 새 코드를 발송해주세요.';
            }
            return '인증코드가 올바르지 않습니다.';
        }
        // 성공: 원본 코드 즉시 소비(재사용·재생 방지) + verified 플래그 설정
        unset($_SESSION['reg_cert_code'], $_SESSION['reg_cert_expire'], $_SESSION['reg_cert_attempts']);
        $_SESSION['reg_cert_verified_email'] = $email;
        $_SESSION['reg_cert_verified_at'] = time();
        return '';
    }
}

// ===== 회원가입 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'register') {
    $reg_request_id = bin2hex(random_bytes(6));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $code      = trim($_POST['cert_code'] ?? '');
    $password  = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string)($_POST['name'] ?? '')));
    $birth_date = trim((string)($_POST['birth_date'] ?? ''));
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birth_date);
    $birth_valid = $birth instanceof DateTimeImmutable
        && $birth->format('Y-m-d') === $birth_date
        && $birth <= new DateTimeImmutable('today')
        && $birth >= new DateTimeImmutable('today -120 years');
    $age = $birth_valid ? $birth->diff(new DateTimeImmutable('today'))->y : null;
    // 마케팅 수신 동의(선택): 체크했을 때만 1. 가입 시 미체크면 마이페이지에서도 수신 꺼진 상태로 저장한다.
    $agree_marketing = (($_POST['agree_marketing'] ?? '') === '1') ? 1 : 0;
    // 휴대폰 번호(필수): 숫자만 남겨 저장. 예약 알림 등에 사용.
    $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));

    if (empty($_POST['csrf_token']) || !hash_equals($reg_csrf_token, (string)$_POST['csrf_token'])) {
        $reg_error = '요청이 만료되었습니다. 페이지를 새로고침해주세요.';
    } elseif (($_POST['agree_terms'] ?? '') !== '1' || ($_POST['agree_privacy'] ?? '') !== '1') {
        $reg_error = '회원가입 약관과 개인정보 수집·이용에 동의해주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reg_error = '올바른 이메일 주소를 입력해주세요.';
    } elseif ($name === '' || !preg_match("/^[\\p{L}\\p{M} .'-]{2,50}$/u", $name)) {
        $reg_error = '이름을 2~50자의 올바른 형식으로 입력해주세요.';
    } elseif (!$birth_valid || $age === null) {
        $reg_error = '올바른 생년월일을 입력해주세요.';
    } elseif (function_exists('prohibit_mb_email') && ($email_policy_error = prohibit_mb_email($email))) {
        $reg_error = $email_policy_error;
    } elseif (($__otp_err = reg_email_otp_gate($email)) !== '') {
        // 이메일 OTP: 정확히 일치하는 즉시 코드 소비 + verified 플래그(재사용 방지)
        $reg_error = $__otp_err;
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/', $password)) {
        $reg_error = '비밀번호는 영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요.';
    } elseif ($password !== $password2) {
        $reg_error = '비밀번호가 일치하지 않습니다.';
    } elseif (!preg_match('/^01[0-9]{8,9}$/', $phone)) {
        $reg_error = '휴대폰 번호를 정확히 입력해주세요. (예: 01012345678)';
    } elseif ($enable_sms_verify && (
            empty($_SESSION['reg_sms_code'])
            || ($_SESSION['reg_sms_phone'] ?? '') !== $phone
            || time() > (int)($_SESSION['reg_sms_expire'] ?? 0)
            || !hash_equals((string)$_SESSION['reg_sms_code'], trim((string)($_POST['sms_code'] ?? '')))
        )) {
        $reg_error = '휴대폰 인증을 완료해주세요.';
    } else {
        // 중복 재확인
        $dup = false;
        if ($st = mysqli_prepare($connect, "SELECT mb_no FROM {$member_table} WHERE LOWER(mb_email) = LOWER(?) LIMIT 1")) {
            mysqli_stmt_bind_param($st, 's', $email);
            mysqli_stmt_execute($st);
            mysqli_stmt_store_result($st);
            $dup = mysqli_stmt_num_rows($st) > 0;
            mysqli_stmt_close($st);
        }
        // 하나의 이메일 = 하나의 유형: 같은 이메일이 병원관리자 신청(대기/승인)에 있으면 일반가입 차단.
        $hosp_dup = false;
        if (!$dup && ($hs = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospitals WHERE LOWER(email) = LOWER(?) AND status IN ('pending','approved') LIMIT 1"))) {
            mysqli_stmt_bind_param($hs, 's', $email);
            mysqli_stmt_execute($hs);
            mysqli_stmt_store_result($hs);
            $hosp_dup = mysqli_stmt_num_rows($hs) > 0;
            mysqli_stmt_close($hs);
        }
        if ($dup) {
            gnu_register_duplicate_log($connect, $member_table, $email, 'precheck', $reg_request_id);
            $reg_error = '이미 가입된 이메일입니다. 로그인하거나 비밀번호 찾기를 이용해주세요. (' . $reg_request_id . ')';
        } elseif ($hosp_dup) {
            $reg_error = '이미 병원관리자로 신청/가입된 이메일입니다. 하나의 이메일은 일반회원 또는 병원관리자 중 하나로만 가입할 수 있습니다.';
        } else {
            // g5_member 생성 (아이디 자동 생성, 이메일 인증 완료 처리)
            $mb_id    = 'sb_' . substr(md5($email . microtime()), 0, 10);
            // 비밀번호 해시는 사이트 설정(G5_STRING_ENCRYPT_FUNCTION=create_hash · PBKDF2)을 그대로
            // 따르는 그누보드 코어 함수 get_encrypt_string() 으로 생성한다. login_check.php 의
            // 검증 로직(check_password/validate_password)과 항상 동일한 방식이 보장된다.
            // (bcrypt 등 다른 해시로 저장하면 검증이 항상 실패 → 가입 계정이 영영 로그인 불가)
            $mb_hash  = get_encrypt_string($password);
            $mb_name = $name;
            $mb_birth = $birth->format('Ymd');

            // mb_id의 난수 부분을 포함하는 영문/숫자 닉네임으로 만들어 동시 가입에서도 충돌하지 않게 한다.
            $nick_base = preg_replace('/[^\p{L}\p{N}]/u', '', $mb_name);
            $nick_base = $nick_base !== '' ? $nick_base : 'user';
            $nick_base = function_exists('mb_substr')
                ? mb_substr($nick_base, 0, 24, 'UTF-8')
                : substr($nick_base, 0, 24);
            $mb_nick = $nick_base . substr($mb_id, -8);
            if (function_exists('reserve_mb_nick') && reserve_mb_nick($mb_nick)) {
                $mb_nick = 'member' . substr(hash('sha256', $mb_id), 0, 10);
            }

            // 추천인 처리(#2): register.skin.php 가 쿠키/세션에 저장한 추천인 아이디를 읽어,
            // cf_use_recommend 가 켜져 있고 실제 존재하는 회원이며 본인이 아닐 때만 반영한다.
            // (오래된/잘못된 추천 쿠키가 있어도 가입 자체는 막지 않고 조용히 무시한다)
            $mb_recommend = '';
            if (!empty($config['cf_use_recommend'])) {
                $rec_raw = '';
                if (function_exists('get_cookie'))  $rec_raw = trim((string)get_cookie('reg_mb_recommend'));
                if ($rec_raw === '' && function_exists('get_session')) $rec_raw = trim((string)get_session('ss_mb_recommend'));
                $rec_raw = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$rec_raw);
                if ($rec_raw !== '' && strtolower($rec_raw) !== strtolower($mb_id)) {
                    $rec_ok = false; // exist_mb_id 는 register.lib.php 의존 → 직접 조회로 확인
                    if ($rc = mysqli_prepare($connect, "SELECT 1 FROM {$member_table} WHERE mb_id = ? LIMIT 1")) {
                        mysqli_stmt_bind_param($rc, 's', $rec_raw);
                        mysqli_stmt_execute($rc);
                        mysqli_stmt_store_result($rc);
                        $rec_ok = mysqli_stmt_num_rows($rc) > 0;
                        mysqli_stmt_close($rc);
                    }
                    if ($rec_ok) $mb_recommend = $rec_raw;
                }
            }

            $now      = date('Y-m-d H:i:s');
            $today    = date('Y-m-d');
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
            $register_level = max(1, (int)($config['cf_register_level'] ?? 2));

            // 표준 회원가입 훅과 스킨이 참조하는 핵심 POST 필드를 동일하게 공급한다.
            $_POST['w'] = '';
            $_POST['mb_id'] = $mb_id;
            $_POST['mb_email'] = $email;
            $_POST['mb_name'] = $mb_name;
            $_POST['mb_nick'] = $mb_nick;
            $_POST['mb_1'] = 'general';
            if (function_exists('run_event')) {
                run_event('register_form_update_before', $mb_id, '');
                run_event('register_form_update_valid', '', $mb_id, $mb_nick, $email);
            }
            if (isset($member_skin_path)) {
                @include $member_skin_path . '/register_form_update.head.skin.php';
            }

            // mb_email에 UNIQUE 인덱스가 없는 기존 스키마에서도 동시 가입을 막기 위해
            // 이메일별 MySQL advisory lock을 취득한 후 NOT EXISTS + INSERT를 실행한다.
            $lock_name = 'gnu_reg_' . substr(hash('sha256', strtolower($email)), 0, 40);
            $lock_acquired = 0;
            if ($lock_stmt = mysqli_prepare($connect, 'SELECT GET_LOCK(?, 5)')) {
                mysqli_stmt_bind_param($lock_stmt, 's', $lock_name);
                mysqli_stmt_execute($lock_stmt);
                mysqli_stmt_bind_result($lock_stmt, $lock_acquired);
                mysqli_stmt_fetch($lock_stmt);
                mysqli_stmt_close($lock_stmt);
            }
            if ((int)$lock_acquired !== 1) {
                $reg_error = '가입 처리가 지연되고 있습니다. 잠시 후 다시 시도해주세요.';
            } else {

            // 이메일 유일성 원자적 보장(#10): 위의 사전 중복 체크와 INSERT 사이의 경쟁 조건(TOCTOU)을
            // 없애기 위해, 동일 이메일이 없을 때에만 삽입되는 단일 문으로 처리한다. 동시 요청이 먼저
            // 선점하면 삽입 행이 0 → 중복으로 안내한다. (추가로 DB에 mb_email UNIQUE 인덱스를 두면 더 견고)
            $ins = mysqli_prepare($connect,
                "INSERT INTO {$member_table} (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_level, mb_datetime, mb_today_login, mb_ip, mb_login_ip, mb_email_certify, mb_open_date, mb_1, mb_recommend, mb_mailling, mb_sms, mb_hp, mb_birth, mb_2)
                 SELECT ?, ?, ?, ?, ?, ?, {$register_level}, ?, ?, ?, ?, ?, ?, 'general', ?, ?, ?, ?, ?, ?
                 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM {$member_table} WHERE LOWER(mb_email) = LOWER(?))");
            if ($ins) {
                $age_string = (string)$age;
                mysqli_stmt_bind_param($ins, 'sssssssssssssssssss', $mb_id, $mb_hash, $mb_name, $mb_nick, $today, $email, $now, $now, $ip, $ip, $now, $today, $mb_recommend, $agree_marketing, $agree_marketing, $phone, $mb_birth, $age_string, $email);
                $ok = mysqli_stmt_execute($ins);
                $affected = mysqli_stmt_affected_rows($ins);
                mysqli_stmt_close($ins);
                if ($unlock = mysqli_prepare($connect, 'SELECT RELEASE_LOCK(?)')) {
                    mysqli_stmt_bind_param($unlock, 's', $lock_name);
                    mysqli_stmt_execute($unlock);
                    mysqli_stmt_close($unlock);
                }
                if ($ok && $affected === 1) {
                    // 가입 완료: 이메일 OTP·verified 플래그·SMS 인증 세션을 모두 정리(재사용 방지)
                    unset(
                        $_SESSION['reg_cert_code'], $_SESSION['reg_cert_email'], $_SESSION['reg_cert_expire'], $_SESSION['reg_cert_attempts'],
                        $_SESSION['reg_cert_verified_email'], $_SESSION['reg_cert_verified_at'],
                        $_SESSION['reg_sms_code'], $_SESSION['reg_sms_phone'], $_SESSION['reg_sms_expire']
                    );

                    // 포인트 원장(#포인트제도): 회원가입 완료 시 고정 포인트 지급.
                    // 지급액/활성화는 admin_points.php 설정(signup_point, enabled, signup_enabled)으로 제어.
                    // 포인트 지급은 부가 기능이므로, 원장 미설치/오류가 있어도 절대 가입 자체를
                    // 실패시키지 않는다(계정은 이미 생성됨). 실패 시 로그만 남기고 진행한다.
                    try {
                        require_once dirname(__DIR__) . '/includes/points_ledger.php';
                        $new_mb_no = points_member_no($connect, $mb_id);
                        $pr = points_earn_signup($connect, $new_mb_no);
                        // 첫 화면에서 "N P 적립" 안내(토스트)를 1회 노출하기 위한 플래시
                        if (!empty($pr['ok']) && empty($pr['skipped'])) {
                            $_SESSION['reg_welcome_point'] = (int)$pr['amount'];
                        }
                    } catch (\Throwable $e) {
                        error_log('[register] signup point award failed: ' . $e->getMessage());
                    }

                    // 그누보드 메일 설정을 존중해 회원/관리자 가입 알림을 발송한다.
                    if (!empty($config['cf_email_mb_member']) && function_exists('mail_send')) {
                        $welcome_subject = '[' . ($config['cf_title'] ?? '서울미녀') . '] 회원가입을 환영합니다.';
                        $welcome_body = '<div style="font-family:sans-serif;line-height:1.7">'
                            . htmlspecialchars($mb_nick, ENT_QUOTES, 'UTF-8') . '님, 회원가입이 완료되었습니다.</div>';
                        mail_send(0, $email, $welcome_subject, $welcome_body);
                        if (function_exists('run_event')) run_event('register_form_update_send_mb_mail', $config['cf_admin_email_name'] ?? '', $config['cf_admin_email'] ?? '', $email, $welcome_subject, $welcome_body);
                    }
                    if (!empty($config['cf_email_mb_super_admin']) && !empty($config['cf_admin_email']) && function_exists('mail_send')) {
                        $admin_subject = '[' . ($config['cf_title'] ?? '서울미녀') . '] 새 회원이 가입했습니다.';
                        $admin_body = '<div style="font-family:sans-serif;line-height:1.7">회원 ID: '
                            . htmlspecialchars($mb_id, ENT_QUOTES, 'UTF-8') . '<br>이메일: '
                            . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</div>';
                        mail_send(0, $config['cf_admin_email'], $admin_subject, $admin_body);
                        if (function_exists('run_event')) run_event('register_form_update_send_admin_mail', $mb_nick, $email, $config['cf_admin_email'], $admin_subject, $admin_body);
                    }
                    // 사용한 추천 쿠키/세션은 다음 가입에 새어나가지 않도록 정리
                    if (function_exists('set_cookie'))  set_cookie('reg_mb_recommend', '', 0);
                    if (function_exists('set_session')) set_session('ss_mb_recommend', '');
                    // 비밀번호를 HTML hidden input에 다시 노출하지 않고 서버에서 바로 세션을 생성한다.
                    $new_member = get_member($mb_id);
                    if (!(defined('SKIP_SESSION_REGENERATE_ID') && SKIP_SESSION_REGENERATE_ID)) {
                        session_regenerate_id(false);
                        if (function_exists('session_start_samesite')) session_start_samesite();
                    }
                    set_session('ss_mb_id', $mb_id);
                    set_session('ss_mb_reg', $mb_id);
                    if (!empty($new_member)) generate_mb_key($new_member);
                    if (function_exists('update_auth_session_token')) update_auth_session_token($now);
                    if (function_exists('run_event')) run_event('register_form_update_after', $mb_id, '');
                    header('Location: ' . $home_url);
                    exit;
                } elseif ($ok && $affected === 0) {
                    gnu_register_duplicate_log($connect, $member_table, $email, 'atomic_insert', $reg_request_id);
                    $reg_error = '이미 가입된 이메일입니다. 로그인하거나 비밀번호 찾기를 이용해주세요. (' . $reg_request_id . ')';
                } else {
                    $reg_error = '가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
                }
            } else {
                if ($unlock = mysqli_prepare($connect, 'SELECT RELEASE_LOCK(?)')) {
                    mysqli_stmt_bind_param($unlock, 's', $lock_name);
                    mysqli_stmt_execute($unlock);
                    mysqli_stmt_close($unlock);
                }
                $reg_error = '가입 처리 중 오류가 발생했습니다.';
            }
            }
        }
    }
}

// 로고 — bbs/login.php(rb.member 스킨)와 동일
$_logo_ver = defined('G5_SERVER_TIME') ? G5_SERVER_TIME : time();
if (!empty($rb_builder['bu_logo_pc'])) {
    $logo_src = $g5_url . '/data/logos/pc?ver=' . $_logo_ver;
} else {
    $_theme_url = defined('G5_THEME_URL') ? G5_THEME_URL : ($g5_url . '/theme/rb.basic');
    $logo_src = $_theme_url . '/rb.img/logos/pc.png?ver=' . $_logo_ver;
}
$brand_name = function_exists('getTheme') ? getTheme('site_name', '서울미녀') : '서울미녀';
$primary    = function_exists('getTheme') ? getTheme('primary_color', '#AE1932') : '#AE1932';
// 오류 시 재입력을 위해 값 유지
$old_email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>회원가입 | <?php echo htmlspecialchars($brand_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES); ?>; --join-primary: #b61235; --join-active: #2b0000; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body, html { margin: 0; padding: 0; background: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; color: #050505; }
        .login-wrap { width: 100%; max-width: 430px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: 64px 34px 34px; }
        .login-top { flex: 0 0 auto; }
        .login-top a { color: #050505; text-decoration: none; display: inline-flex; align-items: center; width: 28px; height: 28px; }
        .login-top svg { width: 24px; height: 24px; }
        .login-hero { padding: 42px 0 32px; }
        .login-hero img { display: none; }
        .login-hero .t { margin: 0; font-size: 27px; line-height: 1.25; font-weight: 800; letter-spacing: 0; color: #050505; }
        form.reg { flex: 1; display: flex; flex-direction: column; gap: 40px; }
        .field label { display: block; font-size: 23px; line-height: 1.25; color: #050505; font-weight: 500; margin-bottom: 18px; }
        .field-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .field-head label { margin: 0; }
        .field input { width: 100%; height: 74px; border: 1px solid #222; border-radius: 0; padding: 0 32px; font-size: 24px; font-weight: 400; background: #fff; color: #050505; }
        .field input::placeholder { color: #a7a7a7; opacity: 1; }
        .field input:focus { outline: none; border-color: #050505; background: #fff; }
        .field input:disabled { background: #f0f0f0; color: #999; }
        .row { display: grid; grid-template-columns: minmax(0, 1fr) 154px; gap: 18px; align-items: stretch; }
        .row .field { min-width: 0; }
        .pw-wrap { position: relative; }
        .pw-wrap .toggle { appearance: none; background: none; border: none; color: #050505; font-size: 23px; line-height: 1.25; font-weight: 500; cursor: pointer; padding: 0; }
        .btn { width: 100%; height: 74px; border: none; border-radius: 0; font-size: 25px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; letter-spacing: 0; }
        .btn:disabled { cursor: default; }
        .btn-primary { background: var(--join-active); color: #fff; margin-top: auto; }
        .btn-primary:disabled { background: #ededed; color: #adadad; }
        .btn-side { width: 100%; height: 74px; padding: 0 18px; background: var(--join-primary); color: #fff; font-size: 23px; white-space: nowrap; }
        .btn-side:disabled { background: var(--join-primary); color: #fff; opacity: .7; }
        .hint { margin-top: 12px; font-size: 19px; line-height: 1.45; color: #a7a7a7; }
        .msg { font-size: 18px; line-height: 1.35; margin-top: 10px; min-height: 24px; }
        .msg:empty { display: none; }
        .msg.ok { color: #1a7f37; }
        .msg.bad { color: #c0293f; }
        .err { background: #fdecef; color: #c0293f; font-size: 15px; border-radius: 0; padding: 12px 14px; margin-bottom: 20px; }
        .agreements { display: flex; flex-direction: column; gap: 14px; padding: 20px 0; border-top: 1px solid #ededed; border-bottom: 1px solid #ededed; }
        .agreements label { display: flex; align-items: flex-start; gap: 10px; font-size: 16px; line-height: 1.45; cursor: pointer; }
        .agreements input { width: 18px; height: 18px; margin-top: 2px; flex: 0 0 auto; }
        .foot { text-align: center; margin-top: auto; padding: 80px 0 0px; font-size: 24px; line-height: 1.2; color: #050505; }
        .foot a { color: #050505; font-weight: 800; text-decoration: none; margin-left: 22px; }
        @media (max-width: 430px) {
            .login-wrap { max-width: none; padding: 44px 24px 24px; }
            .login-hero { padding: 34px 0 28px; }
            .login-hero .t { font-size: 28px; }
            form.reg { gap: 34px; }
            .field label, .pw-wrap .toggle, .btn-side { font-size: 20px; }
            .field input, .btn { height: 64px; font-size: 20px; }
            .field input { padding: 0 20px; }
            .row { grid-template-columns: minmax(0, 1fr) 112px; gap: 10px; }
            .hint { font-size: 15px; }
            .foot { font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-top">
            <a href="<?php echo htmlspecialchars($g5_url, ENT_QUOTES); ?>/app/auth/login.php" aria-label="뒤로">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                </svg>
            </a>
        </div>

        <div class="login-hero">
            <img src="<?php echo htmlspecialchars($logo_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand_name); ?>">
            <div class="t">이메일을 입력해주세요</div>
        </div>

        <?php if ($reg_error): ?>
        <div class="err" style="margin-bottom:12px;"><?php echo htmlspecialchars($reg_error); ?></div>
        <?php endif; ?>

        <form class="reg" method="post" action="register.php" id="regForm">
            <input type="hidden" name="mode" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($reg_csrf_token, ENT_QUOTES); ?>">

            <div class="field">
                <label for="email">이메일</label>
                <div class="row">
                    <div class="field">
                        <input type="email" name="email" id="email" placeholder="아이디로 사용될 이메일을 입력해주세요" required autocomplete="email" value="<?php echo $old_email; ?>">
                    </div>
                    <button type="button" class="btn btn-side" id="sendCodeBtn" onclick="sendCode()">인증하기</button>
                </div>
                <div class="hint">로그인 및 계정 분실방지를 위해 메일 인증이 필요합니다.</div>
                <div class="msg" id="emailMsg"></div>
            </div>

            <div class="field">
                <label for="cert_code">인증코드</label>
                <input type="text" name="cert_code" id="cert_code" inputmode="numeric" maxlength="6" placeholder="메일로 전송된 코드를 입력해주세요" autocomplete="one-time-code">
            </div>

            <div class="field pw-wrap">
                <div class="field-head">
                    <label for="password">비밀번호</label>
                    <button type="button" class="toggle" onclick="togglePw('password', this)">표시</button>
                </div>
                <input type="password" name="password" id="password" placeholder="비밀번호를 입력해주세요" required minlength="9" maxlength="20" pattern="(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}" title="영문, 숫자, 특수문자를 각각 포함한 9~20자" autocomplete="new-password">
                <div class="hint">비밀번호는 숫자, 영문자, 특수문자 포함 9~20자로 입력해주세요.</div>
            </div>

            <div class="field pw-wrap">
                <div class="field-head">
                    <label for="password2">비밀번호 확인</label>
                    <button type="button" class="toggle" onclick="togglePw('password2', this)">표시</button>
                </div>
                <input type="password" name="password2" id="password2" placeholder="비밀번호를 입력해주세요" required minlength="9" maxlength="20" autocomplete="new-password">
                <div class="msg" id="pwMsg"></div>
            </div>

            <div class="field">
                <label for="name">이름</label>
                <input type="text" name="name" id="name" minlength="2" maxlength="50"
                       placeholder="실명을 입력해주세요" required autocomplete="name"
                       value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>">
                <div class="hint">예약과 본인 확인에 사용할 이름입니다.</div>
            </div>

            <div class="field">
                <label for="birth_date">생년월일</label>
                <input type="date" name="birth_date" id="birth_date" required autocomplete="bday"
                       max="<?php echo date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d', strtotime('-120 years')); ?>"
                       value="<?php echo htmlspecialchars($_POST['birth_date'] ?? '', ENT_QUOTES); ?>">
            </div>

            <div class="field">
                <label for="age">나이</label>
                <input type="number" name="age" id="age" min="0" max="120"
                       placeholder="생년월일을 입력하면 자동 계산됩니다" readonly required>
                <div class="hint">만 나이 기준으로 자동 계산됩니다.</div>
            </div>

            <div class="field">
                <label for="phone">휴대폰 번호</label>
                <?php if ($enable_sms_verify): ?>
                <div class="row">
                    <div class="field">
                        <input type="tel" name="phone" id="phone" inputmode="numeric" maxlength="11" placeholder="- 없이 숫자만 입력" required autocomplete="tel" value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES); ?>">
                    </div>
                    <button type="button" class="btn btn-side" id="sendSmsBtn" onclick="sendSms()">인증하기</button>
                </div>
                <div class="hint">예약 알림 수신을 위해 휴대폰 인증이 필요합니다.</div>
                <div class="msg" id="phoneMsg"></div>
                <input type="text" name="sms_code" id="sms_code" inputmode="numeric" maxlength="6" placeholder="문자로 전송된 인증번호" autocomplete="one-time-code" style="margin-top:14px;">
                <?php else: ?>
                <input type="tel" name="phone" id="phone" inputmode="numeric" maxlength="11" placeholder="- 없이 숫자만 입력 (예: 01012345678)" required autocomplete="tel" value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES); ?>">
                <div class="hint">예약 알림 등을 위해 휴대폰 번호가 필요합니다.</div>
                <?php endif; ?>
            </div>

            <div class="agreements">
                <label><input type="checkbox" name="agree_terms" id="agreeTerms" value="1" required> <span>(필수) 회원가입 약관에 동의합니다. <a href="<?php echo htmlspecialchars($bbs_url, ENT_QUOTES); ?>/register.php?view=terms" target="_blank" rel="noopener">내용 보기</a></span></label>
                <label><input type="checkbox" name="agree_privacy" id="agreePrivacy" value="1" required> <span>(필수) 개인정보 수집 및 이용에 동의합니다. <a href="<?php echo htmlspecialchars($bbs_url, ENT_QUOTES); ?>/register.php?view=terms" target="_blank" rel="noopener">내용 보기</a></span></label>
                <label><input type="checkbox" name="agree_marketing" id="agreeMarketing" value="1"> <span>(선택) 마케팅 정보 수신에 동의합니다. (이벤트·혜택 알림)</span></label>
            </div>

            <div class="foot">
                이미 서울미녀 회원이신가요?<a href="login.php">로그인</a>
                <span aria-hidden="true">·</span><a href="password_lost.php">비밀번호 찾기</a>
            </div>

            <button type="submit" class="btn btn-primary" id="joinBtn" disabled>가입하기</button>
        </form>
    </div>

    <script>
    function togglePw(id, btn) {
        var el = document.getElementById(id);
        if (el.type === 'password') { el.type = 'text'; btn.textContent = '가리기'; }
        else { el.type = 'password'; btn.textContent = '표시'; }
    }
    var codeTimer = null;
    function sendCode() {
        var email = document.getElementById('email').value.trim();
        var msg = document.getElementById('emailMsg');
        var btn = document.getElementById('sendCodeBtn');
        if (!email) { msg.className = 'msg bad'; msg.textContent = '이메일을 입력해주세요.'; return; }
        btn.disabled = true; msg.className = 'msg'; msg.textContent = '발송 중…';
        var fd = new URLSearchParams(); fd.append('mode', 'send_code'); fd.append('email', email); fd.append('csrf_token', '<?php echo htmlspecialchars($reg_csrf_token, ENT_QUOTES); ?>');
        fetch('register.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                msg.className = 'msg ' + (d.ok ? 'ok' : 'bad');
                msg.textContent = d.msg || (d.ok ? '발송했습니다.' : '발송 실패');
                if (d.ok) {
                    document.getElementById('cert_code').focus();
                    var left = 600;
                    clearInterval(codeTimer);
                    codeTimer = setInterval(function () {
                        left--;
                        if (left <= 0) { clearInterval(codeTimer); btn.disabled = false; btn.textContent = '재인증하기'; updateJoinState(); return; }
                        btn.textContent = Math.floor(left/60) + ':' + ('0'+(left%60)).slice(-2);
                        updateJoinState();
                    }, 1000);
                } else { btn.disabled = false; updateJoinState(); }
            })
            .catch(function () { msg.className = 'msg bad'; msg.textContent = '네트워크 오류가 발생했습니다.'; btn.disabled = false; updateJoinState(); });
    }
    // 휴대폰 SMS 인증코드 발송 (SMS 기능 활성화 시에만 버튼이 렌더됨)
    var smsEnabled = <?php echo $enable_sms_verify ? 'true' : 'false'; ?>;
    var smsTimer = null;
    function sendSms() {
        var phone = (document.getElementById('phone').value || '').replace(/[^0-9]/g, '');
        var msg = document.getElementById('phoneMsg');
        var btn = document.getElementById('sendSmsBtn');
        if (!/^01[0-9]{8,9}$/.test(phone)) { msg.className = 'msg bad'; msg.textContent = '올바른 휴대폰 번호를 입력해주세요.'; return; }
        btn.disabled = true; msg.className = 'msg'; msg.textContent = '발송 중…';
        var fd = new URLSearchParams(); fd.append('mode', 'send_sms_code'); fd.append('phone', phone); fd.append('csrf_token', '<?php echo htmlspecialchars($reg_csrf_token, ENT_QUOTES); ?>');
        fetch('register.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                msg.className = 'msg ' + (d.ok ? 'ok' : 'bad');
                msg.textContent = d.msg || (d.ok ? '발송했습니다.' : '발송 실패');
                if (d.ok) {
                    document.getElementById('sms_code').focus();
                    var left = 600; clearInterval(smsTimer);
                    smsTimer = setInterval(function () {
                        left--;
                        if (left <= 0) { clearInterval(smsTimer); btn.disabled = false; btn.textContent = '재발송'; return; }
                        btn.textContent = Math.floor(left/60) + ':' + ('0'+(left%60)).slice(-2);
                    }, 1000);
                } else { btn.disabled = false; }
            })
            .catch(function () { msg.className = 'msg bad'; msg.textContent = '네트워크 오류가 발생했습니다.'; btn.disabled = false; });
    }
    // 비밀번호 일치 표시 + 제출 검증
    var p1 = document.getElementById('password'), p2 = document.getElementById('password2'), pm = document.getElementById('pwMsg');
    var emailInput = document.getElementById('email'), codeInput = document.getElementById('cert_code'), joinBtn = document.getElementById('joinBtn');
    var agreeTerms = document.getElementById('agreeTerms'), agreePrivacy = document.getElementById('agreePrivacy');
    var phoneInput = document.getElementById('phone'), smsCodeInput = document.getElementById('sms_code');
    var nameInput = document.getElementById('name'), birthInput = document.getElementById('birth_date'), ageInput = document.getElementById('age');
    function updateAge() {
        ageInput.value = '';
        if (!birthInput.value) return false;
        var birth = new Date(birthInput.value + 'T00:00:00');
        if (isNaN(birth.getTime())) return false;
        var today = new Date(), age = today.getFullYear() - birth.getFullYear();
        var beforeBirthday = today.getMonth() < birth.getMonth()
            || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate());
        if (beforeBirthday) age--;
        if (age < 0 || age > 120) return false;
        ageInput.value = age;
        return true;
    }
    function checkMatch() {
        if (!p2.value) { pm.textContent = ''; return true; }
        var ok = p1.value === p2.value;
        pm.className = 'msg ' + (ok ? 'ok' : 'bad');
        pm.textContent = ok ? '비밀번호가 일치합니다.' : '비밀번호가 일치하지 않습니다.';
        return ok;
    }
    function updateJoinState() {
        var phoneOk = /^01[0-9]{8,9}$/.test((phoneInput.value || '').replace(/[^0-9]/g, ''));
        var smsOk = !smsEnabled || (smsCodeInput && smsCodeInput.value.trim().length === 6);
        var nameOk = nameInput.value.trim().length >= 2;
        var ageOk = updateAge();
        var canJoin = emailInput.value.trim() && codeInput.value.trim() && p1.value && p2.value && p1.value === p2.value && nameOk && ageOk && agreeTerms.checked && agreePrivacy.checked && phoneOk && smsOk;
        joinBtn.disabled = !canJoin;
    }
    emailInput.addEventListener('input', updateJoinState);
    codeInput.addEventListener('input', updateJoinState);
    phoneInput.addEventListener('input', updateJoinState);
    nameInput.addEventListener('input', updateJoinState);
    birthInput.addEventListener('change', updateJoinState);
    if (smsCodeInput) smsCodeInput.addEventListener('input', updateJoinState);
    p1.addEventListener('input', function () { checkMatch(); updateJoinState(); });
    p2.addEventListener('input', function () { checkMatch(); updateJoinState(); });
    agreeTerms.addEventListener('change', updateJoinState);
    agreePrivacy.addEventListener('change', updateJoinState);
    document.getElementById('regForm').addEventListener('submit', function (e) {
        if (!checkMatch()) { e.preventDefault(); p2.focus(); }
    });
    updateJoinState();
    </script>
</body>
</html>

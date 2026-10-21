<?php
require_once __DIR__ . '/includes/init.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!is_gangnamunni_logged_in()) { header('Location: auth/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();
// CSRF: 모든 POST 폼에 세션 토큰을 실어 보내고 서버에서 검증한다.
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// 회원 정보 조회
$member = null;
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) include_once $g5_common;
if (defined('G5_PATH') && isset($member)) {
    // Gnuboard member variable
} else {
    $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
    if ($res) $member = mysqli_fetch_assoc($res);
}

// 저장된 생년월일(mb_birth: Ymd 또는 Y-m-d)을 <input type=date> 값(Y-m-d)으로 변환. 없거나 잘못되면 빈 문자열.
function profile_birth_to_input($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if (strlen($digits) !== 8 || $digits === '00000000') return '';
    $d = DateTimeImmutable::createFromFormat('!Ymd', $digits);
    return ($d && $d->format('Ymd') === $digits) ? $d->format('Y-m-d') : '';
}

$saved = false;
$photo_upload_error = '';
$pw_error = '';
$pw_saved = false;
$email_error = '';
$email_cert_sent = false;
$email_saved = false;
$nick_error = '';
$name_error = '';
$birth_error = '';
$save_error = '';
$profile_photo_dir  = __DIR__ . '/uploads/profile/';
$profile_photo_url  = null;

// 현재 프로필 사진 경로 확인
$photo_exts = ['jpg', 'jpeg', 'png', 'webp'];
foreach ($photo_exts as $ext) {
    $check = $profile_photo_dir . $member_id . '.' . $ext;
    if (file_exists($check)) {
        $profile_photo_url = 'uploads/profile/' . $member_id . '.' . $ext . '?v=' . filemtime($check);
        break;
    }
}

// 프로필 사진 검증(저장하지 않음). 통과하면 true, 아니면 $err 에 사유를 담고 false.
function profile_validate_photo($file, &$err) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $err = '업로드에 실패했습니다.'; return false;
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) { $err = '이미지 파일만 업로드 가능합니다.'; return false; }
    if ($file['size'] > 5 * 1024 * 1024) { $err = '파일 크기는 5MB 이하여야 합니다.'; return false; }
    return true;
}

// 검증된 사진 저장. 새 파일을 먼저 옮긴 뒤에 다른 확장자의 이전 파일을 지운다(실패해도 기존 사진 유지).
function profile_store_photo($file, $dir, $member_id, $exts, &$url, &$err) {
    if (!profile_validate_photo($file, $err)) return false;
    $mime     = mime_content_type($file['tmp_name']);
    $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];
    $save_ext = $ext_map[$mime] ?? 'jpg';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . $member_id . '.' . $save_ext)) { $err = '업로드에 실패했습니다.'; return false; }
    foreach ($exts as $ext) {
        if ($ext === $save_ext) continue;
        $old = $dir . $member_id . '.' . $ext;
        if (file_exists($old)) @unlink($old);
    }
    $url = 'uploads/profile/' . $member_id . '.' . $save_ext . '?v=' . time();
    return true;
}

// 생년월일 변경 이력 테이블(변경 시에만 호출되므로 요청 경로에 DDL 부담이 거의 없다).
function profile_ensure_birth_log($connect) {
    @mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_member_birth_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, mb_id VARCHAR(60) NOT NULL,
        old_birth VARCHAR(10) NOT NULL DEFAULT '', new_birth VARCHAR(10) NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_mb_created (mb_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
// 생년월일은 연령 제한(예약)에 쓰이므로 이미 등록된 값을 바꾸는 것은 30일에 1회로 제한한다.
const PROFILE_BIRTH_CHANGE_DAYS = 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!hash_equals($csrf_token, (string)($_POST['csrf_token'] ?? ''))) {
        $action = '';
        $save_error = '요청이 만료되었어요. 페이지를 새로고침한 뒤 다시 시도해주세요.';
    }

    // ── 1. 프로필 사진 (단독 요청용. 화면에서는 '프로필 저장' 때 함께 저장된다) ──
    if ($action === 'photo' && !empty($_FILES['profile_photo']['tmp_name'])) {
        if (profile_store_photo($_FILES['profile_photo'], $profile_photo_dir, $member_id, $photo_exts, $profile_photo_url, $photo_upload_error)) {
            $saved = true;
        }
    }

    // ── 2. 닉네임 변경 ──────────────────────────────────────
    if ($action === 'nick') {
        $nick = trim($_POST['nick'] ?? '');
        if ($nick === '') {
            $nick_error = '닉네임을 입력해주세요.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
            $nick_error = '닉네임은 영문, 숫자, 밑줄(_)만 사용할 수 있습니다.';
        } else {
            $dup = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_nick=? AND mb_id!=?");
            mysqli_stmt_bind_param($dup, 'ss', $nick, $member_id);
            mysqli_stmt_execute($dup);
            mysqli_stmt_store_result($dup);
            $is_dup = mysqli_stmt_num_rows($dup) > 0;
            mysqli_stmt_close($dup);
            if ($is_dup) {
                $nick_error = '이미 사용 중인 닉네임입니다.';
            } else {
                $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_nick=? WHERE mb_id=?");
                mysqli_stmt_bind_param($stmt, 'ss', $nick, $member_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $saved = true;
                $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
                if ($res) $member = mysqli_fetch_assoc($res);
            }
        }
    }

    // ── 3. 이름 변경 ────────────────────────────────────────
    if ($action === 'name') {
        $name = trim($_POST['mb_name'] ?? '');
        if ($name === '') {
            $name_error = '이름을 입력해주세요.';
        } else {
            $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_name=? WHERE mb_id=?");
            mysqli_stmt_bind_param($stmt, 'ss', $name, $member_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $saved = true;
            $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
            if ($res) $member = mysqli_fetch_assoc($res);
        }
    }

    // ── 4. 휴대폰 변경 ──────────────────────────────────────
    if ($action === 'hp') {
        $hp   = trim($_POST['hp'] ?? '');
        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_hp=? WHERE mb_id=?");
        mysqli_stmt_bind_param($stmt, 'ss', $hp, $member_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $saved = true;
        $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
        if ($res) $member = mysqli_fetch_assoc($res);
    }

    // ── 통합 저장 (프로필 '저장' 버튼) : 닉네임 + 이름 + 생년월일 + 연락처 + 사진 한 번에 ──
    if ($action === 'save_all') {
        $nick = trim($_POST['nick'] ?? '');
        $name = trim($_POST['mb_name'] ?? '');
        $hp   = trim($_POST['hp'] ?? '');
        // 생년월일(필수): 회원가입과 동일한 기준(오늘 이전, 최대 120년 전까지)으로 검증한다.
        $birth_input = trim($_POST['mb_birth'] ?? '');
        $birth_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $birth_input);
        $birth_ok = $birth_obj instanceof DateTimeImmutable
            && $birth_obj->format('Y-m-d') === $birth_input
            && $birth_obj <= new DateTimeImmutable('today')
            && $birth_obj >= new DateTimeImmutable('today -120 years');
        $has_photo = !empty($_FILES['profile_photo']['tmp_name']);
        if ($name === '') {
            $name_error = '이름을 입력해주세요.';
        } elseif (!$birth_ok) {
            $birth_error = '올바른 생년월일을 입력해주세요.';
        } elseif ($nick === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
            $nick_error = '닉네임은 영문, 숫자, 밑줄(_)만 사용할 수 있습니다.';
        } else {
            $dup = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_nick=? AND mb_id!=?");
            mysqli_stmt_bind_param($dup, 'ss', $nick, $member_id);
            mysqli_stmt_execute($dup);
            mysqli_stmt_store_result($dup);
            $is_dup = mysqli_stmt_num_rows($dup) > 0;
            mysqli_stmt_close($dup);
            $birth_db = $birth_obj->format('Ymd');
            $old_birth_db = preg_replace('/\D+/', '', (string)($member['mb_birth'] ?? ''));
            $had_birth = profile_birth_to_input($member['mb_birth'] ?? '') !== '';
            $birth_changed = !$had_birth || $old_birth_db !== $birth_db;
            if ($is_dup) {
                $nick_error = '이미 사용 중인 닉네임입니다.';
            } else {
                // 1) 모든 검증을 먼저 끝낸다(사진 형식·크기, 생년월일 변경 횟수). 하나라도 실패하면 아무것도 저장하지 않는다.
                $can_save = true;
                if ($has_photo && !profile_validate_photo($_FILES['profile_photo'], $photo_upload_error)) $can_save = false;
                if ($can_save && $had_birth && $birth_changed) {
                    profile_ensure_birth_log($connect);
                    $cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_member_birth_log WHERE mb_id=? AND old_birth<>'' AND created_at > (NOW() - INTERVAL " . (int)PROFILE_BIRTH_CHANGE_DAYS . " DAY)");
                    $recent = 0;
                    if ($cnt_stmt) {
                        mysqli_stmt_bind_param($cnt_stmt, 's', $member_id);
                        mysqli_stmt_execute($cnt_stmt);
                        mysqli_stmt_bind_result($cnt_stmt, $recent);
                        mysqli_stmt_fetch($cnt_stmt);
                        mysqli_stmt_close($cnt_stmt);
                    } else {
                        $recent = 1; // 이력을 확인할 수 없으면 변경을 허용하지 않는다.
                    }
                    if ((int)$recent >= 1) {
                        $birth_error = '생년월일은 ' . PROFILE_BIRTH_CHANGE_DAYS . '일에 한 번만 변경할 수 있어요. 변경이 필요하면 고객센터로 문의해주세요.';
                        $can_save = false;
                    }
                }
                // 2) 회원 정보 저장(실패하면 사진도 저장하지 않는다)
                if ($can_save) {
                    $updated = false;
                    try {
                        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_nick=?, mb_name=?, mb_hp=?, mb_birth=? WHERE mb_id=?");
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 'sssss', $nick, $name, $hp, $birth_db, $member_id);
                            $updated = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    } catch (Throwable $e) {
                        error_log('[profile_edit] 회원 정보 저장 실패: ' . $e->getMessage());
                        $updated = false;
                    }
                    if (!$updated) {
                        $save_error = '저장하지 못했어요. 잠시 후 다시 시도해주세요.';
                    } else {
                        if ($birth_changed) {
                            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
                            profile_ensure_birth_log($connect);
                            $log = mysqli_prepare($connect, "INSERT INTO gangnamunni_member_birth_log (mb_id, old_birth, new_birth, ip) VALUES (?,?,?,?)");
                            if ($log) {
                                $old_for_log = $had_birth ? $old_birth_db : '';
                                mysqli_stmt_bind_param($log, 'ssss', $member_id, $old_for_log, $birth_db, $ip);
                                mysqli_stmt_execute($log);
                                mysqli_stmt_close($log);
                            }
                        }
                        // 3) 사진 저장. 정보는 이미 저장됐으므로 실패하면 그 사실을 알린다.
                        if ($has_photo && !profile_store_photo($_FILES['profile_photo'], $profile_photo_dir, $member_id, $photo_exts, $profile_photo_url, $photo_upload_error)) {
                            $save_error = '정보는 저장되었지만 사진은 올리지 못했어요. 다시 시도해주세요.';
                        } else {
                            $saved = true;
                        }
                        $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
                        if ($res) $member = mysqli_fetch_assoc($res);
                    }
                }
            }
        }
    }

    // ── 4. 비밀번호 변경 ────────────────────────────────────
    if ($action === 'pw') {
        $current_pw     = $_POST['current_pw'] ?? '';
        $new_pw         = $_POST['new_pw'] ?? '';
        $new_pw_confirm = $_POST['new_pw_confirm'] ?? '';
        if ($new_pw !== $new_pw_confirm) {
            $pw_error = '새 비밀번호가 일치하지 않습니다.';
        } elseif (strlen($new_pw) < 6) {
            $pw_error = '비밀번호는 6자 이상이어야 합니다.';
        } else {
            $pw_res = mysqli_query($connect, "SELECT mb_password FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
            $pw_row = mysqli_fetch_assoc($pw_res);
            $stored = $pw_row['mb_password'] ?? '';
            $ok = function_exists('check_password') ? check_password($current_pw, $stored) : password_verify($current_pw, $stored);
            if (!$ok) {
                $pw_error = '현재 비밀번호가 올바르지 않습니다. 비밀번호를 재설정했다면 이메일로 받은 12자리 임시 비밀번호를 입력해주세요.';
            } else {
                $new_hash = function_exists('create_hash') ? create_hash($new_pw) : password_hash($new_pw, PASSWORD_DEFAULT);
                $ps = mysqli_prepare($connect, "UPDATE g5_member SET mb_password=? WHERE mb_id=?");
                mysqli_stmt_bind_param($ps, 'ss', $new_hash, $member_id);
                mysqli_stmt_execute($ps);
                mysqli_stmt_close($ps);

                // HMS(Laravel) 쪽 비밀번호도 함께 바꾼다. 여기서 빠뜨리면 사이트
                // 비밀번호만 바뀌고 HMS API(/api/login)는 옛 비밀번호로 계속
                // 로그인된다 — 바꾼 의미가 없다.
                if (function_exists('sync_hms_password_by_mapping')) {
                    sync_hms_password_by_mapping($connect, $member_id, $new_pw);
                }

                // 비밀번호가 바뀌었으므로 기존 자격증명을 전부 폐기한다 —
                // remember 쿠키, 앱 리프레시/액세스/브릿지 토큰, SSO 토큰,
                // 다른 기기의 세션, HMS API 토큰, 미사용 임시 비밀번호.
                // 이 기기도 포함해 모두 끊고 새 비밀번호로 다시 로그인하게 한다.
                if (function_exists('revoke_all_auth_credentials_for')) {
                    revoke_all_auth_credentials_for($member_id);
                }

                $pw_saved = true;
                header('Location: auth/login.php?pw_changed=1');
                exit;
            }
        }
    }

    // ── 5. 이메일 인증 ──────────────────────────────────────
    if ($action === 'send_email_cert') {
        $new_email = trim($_POST['new_email'] ?? '');
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $email_error = '올바른 이메일 주소를 입력해주세요.';
        } else {
            $dup = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_email=? AND mb_id!=?");
            mysqli_stmt_bind_param($dup, 'ss', $new_email, $member_id);
            mysqli_stmt_execute($dup);
            mysqli_stmt_store_result($dup);
            $is_dup = mysqli_stmt_num_rows($dup) > 0;
            mysqli_stmt_close($dup);
            if ($is_dup) {
                $email_error = '이미 사용 중인 이메일입니다.';
            } else {
                $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['email_cert_code']   = $code;
                $_SESSION['email_cert_new']    = $new_email;
                $_SESSION['email_cert_expire'] = time() + 180;
                $subject = '[서울미녀] 이메일 인증 코드';
                $content = "이메일 변경 인증 코드: <b>{$code}</b><br><br>3분 이내에 입력해주세요.";
                $mailer_path = defined('G5_PHPMAILER_PATH') ? G5_PHPMAILER_PATH : dirname(__DIR__,2).'/plugin/PHPMailer';
                if (file_exists($mailer_path.'/PHPMailerAutoload.php')) {
                    include_once $mailer_path.'/PHPMailerAutoload.php';
                    $pm = new PHPMailer(true);
                    try {
                        $pm->IsSMTP();
                        $pm->Host       = defined('G5_SMTP') ? G5_SMTP : 'localhost';
                        $pm->Port       = defined('G5_SMTP_PORT') ? G5_SMTP_PORT : 587;
                        $pm->SMTPAuth   = defined('G5_SMTP_USER') && G5_SMTP_USER;
                        $pm->Username   = defined('G5_SMTP_USER') ? G5_SMTP_USER : '';
                        $pm->Password   = defined('G5_SMTP_PASS') ? G5_SMTP_PASS : '';
                        $pm->SMTPSecure = 'tls';
                        $pm->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                        $pm->CharSet    = 'UTF-8';
                        $pm->isHTML(true);
                        $pm->setFrom($pm->Username, '서울미녀');
                        $pm->addAddress($new_email);
                        $pm->Subject = $subject;
                        $pm->Body    = $content;
                        $pm->send();
                    } catch (Exception $e) { /* mail error - code still saved in session */ }
                }
                $email_cert_sent = true;
            }
        }
    } elseif ($action === 'verify_email_cert') {
        $input_code = trim($_POST['cert_code'] ?? '');
        if (empty($_SESSION['email_cert_code'])) {
            $email_error = '인증 코드를 먼저 발송해주세요.';
        } elseif (time() > ($_SESSION['email_cert_expire'] ?? 0)) {
            $email_error = '인증 코드가 만료되었습니다. 다시 발송해주세요.';
            unset($_SESSION['email_cert_code'], $_SESSION['email_cert_new'], $_SESSION['email_cert_expire']);
        } elseif ($input_code !== $_SESSION['email_cert_code']) {
            $email_error = '인증 코드가 올바르지 않습니다.';
        } else {
            $new_email = $_SESSION['email_cert_new'];
            $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_email=? WHERE mb_id=?");
            mysqli_stmt_bind_param($stmt, 'ss', $new_email, $member_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            unset($_SESSION['email_cert_code'], $_SESSION['email_cert_new'], $_SESSION['email_cert_expire']);
            $email_saved = true;
            $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
            if ($res) $member = mysqli_fetch_assoc($res);
        }
    }
}
$_ea = $_POST['action'] ?? '';
$email_cert_pending = !empty($_SESSION['email_cert_code']);
$email_show_step2 = $email_cert_sent
    || ($email_cert_pending && $_ea !== 'send_email_cert')
    || ($_ea === 'verify_email_cert' && !empty($email_error));
$email_section_open = !$email_saved && (
    $email_show_step2 || (!empty($email_error) && $_ea === 'send_email_cert')
);

if ($saved || $pw_saved || $email_saved) {
    header('Location: mypage.php');
    exit;
}
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>프로필 수정 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AC1830;--profile-nav-height:77px;--profile-save-height:73px;}
html{scroll-padding-bottom:calc(var(--profile-nav-height) + var(--profile-save-height) + 24px);}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color,#202124);}
.header{position:relative;padding:14px 16px;display:flex;align-items:center;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;flex-shrink:0;}
.header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;white-space:nowrap;}
.form-area{padding:0 16px calc(var(--profile-nav-height) + var(--profile-save-height) + 24px);}
.field{margin-bottom:10px;background:var(--bs-body-bg,#fff);padding:12px 14px;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:12px;display:flex;align-items:center;gap:10px;}
.field-content{flex:1;min-width:0;}
.field label{display:block;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:inherit;margin-bottom:4px;}
.field input{width:100%;min-height:36px;padding:5px 0;border:none;font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1));background:transparent;color:var(--bs-body-color,#202124);}
.field:focus-within{border-color:var(--pri);}
.field input:focus-visible{outline:2px solid var(--pri);outline-offset:3px;border-radius:3px;}
button:focus-visible{outline:2px solid var(--pri);outline-offset:3px;}
.field-content{overflow-wrap:anywhere;}
.field-change-btn{flex-shrink:0;padding:6px 12px;background:none;border:none;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:#AC1830;cursor:pointer;white-space:nowrap;}
.save-btn{width:100%;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:pointer;margin-top:8px;}
.saved-msg{text-align:center;padding:10px;color:#22c55e;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
.save-bar{position:fixed;bottom:var(--profile-nav-height);left:50%;transform:translateX(-50%);width:100%;max-width:640px;padding:12px 16px;background:var(--bs-body-bg,#fff);border-top:1px solid var(--bs-border-color,#e5e7eb);z-index:9998;}
.save-bar.without-nav{padding-bottom:calc(12px + env(safe-area-inset-bottom,0px));}
.save-bar-btn{width:100%;min-height:48px;padding:12px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;cursor:pointer;}
.avatar-area{text-align:center;padding:20px;}
.avatar-wrap{position:relative;width:80px;margin:0 auto 8px;cursor:pointer;}
.avatar{width:80px;height:80px;border-radius:50%;background:#fff;border:2px solid #918f8f;display:flex;align-items:center;justify-content:center;font-size:calc(32px * var(--fs-scale,1) * var(--fs-page,1));color:var(--pri);overflow:hidden;}
.avatar img{width:100%;height:100%;object-fit:cover;}
.avatar-edit-btn{position:absolute;bottom:0;right:0;width:32px;height:32px;background:var(--pri);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;border:2px solid #fff;}
.photo-error{text-align:center;padding:6px 16px;color:#ef4444;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));}
.section-title{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:#888;padding:8px 0 6px;margin-bottom:4px;}
.pw-error{color:#ef4444;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));padding:6px 0 2px;}
.pw-saved{color:#22c55e;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));padding:6px 0 2px;}
.password-panel{padding:14px;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:12px;background:var(--bs-body-bg,#fff);}
.password-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;}
.password-heading h2{font-size:calc(16px * var(--fs-scale,1));}
.password-help{margin:0 0 14px;font-size:calc(13px * var(--fs-scale,1));line-height:1.55;color:var(--bs-secondary-color,#666);overflow-wrap:anywhere;}
#pw-section .field{padding:0;border:0;border-radius:0;margin-bottom:14px;}
#pw-section input{border:1px solid var(--bs-border-color,#d1d5db);border-radius:8px;padding:10px;min-height:44px;}
#pw-section .save-btn{margin-top:0;min-height:48px;}
.leave-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5);z-index:10000;padding:24px;}
.leave-modal.open{display:flex;}
.leave-modal-box{width:100%;max-width:340px;background:var(--bs-body-bg,#fff);border-radius:16px;padding:24px 20px 16px;text-align:center;}
.leave-modal-box h2{font-size:calc(17px * var(--fs-scale,1));font-weight:700;margin-bottom:8px;}
.leave-modal-box p{font-size:calc(14px * var(--fs-scale,1));line-height:1.55;color:var(--bs-secondary-color,#666);margin-bottom:20px;}
.leave-modal-actions{display:flex;flex-direction:column;gap:8px;}
.leave-modal-actions button{min-height:48px;border-radius:12px;font-size:calc(15px * var(--fs-scale,1));font-weight:700;cursor:pointer;font-family:inherit;}
.leave-modal-save{background:var(--pri);color:#fff;border:none;}
.leave-modal-discard{background:transparent;color:var(--pri);border:1px solid var(--pri);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header"><button aria-label="뒤로 가기" class="back" onclick="handleProfileBack()"><i class="bi bi-chevron-left"></i></button><h1>내 정보 수정</h1></div>
<?php if ($saved): ?><div class="saved-msg"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
<?php if ($save_error): ?><div class="photo-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($save_error); ?></div><?php endif; ?>
<?php if ($photo_upload_error): ?><div class="photo-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($photo_upload_error); ?></div><?php endif; ?>

    <!-- ── 1. 사진 업로드 폼 (avatar 포함) ── -->
    <form method="post" enctype="multipart/form-data" id="f-photo"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="photo">
        <input type="file" id="profile_photo_input" accept="image/*" style="display:none" onchange="previewProfilePhoto(this)">
        <div class="avatar-area">
            <div class="avatar-wrap" onclick="document.getElementById('profile_photo_input').click()">
                <div class="avatar" id="avatar-box">
                    <?php if ($profile_photo_url): ?>
                        <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="프로필" id="avatar-img">
                    <?php endif; ?>
                </div>
                <div class="avatar-edit-btn"><i class="bi bi-gear"></i></div>
            </div>
        </div>
    </form>

<div class="form-area">

    <!-- ── 2. 닉네임 폼 ── -->
    <form method="post" id="f-nick" onsubmit="saveAll();return false;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="nick">
        <div class="field">
            <div class="field-content">
                <label for="nick-input">닉네임</label>
                <input type="text" name="nick" required id="nick-input" value="<?php echo htmlspecialchars($member['mb_nick'] ?? ''); ?>" placeholder="영문, 숫자, _ 만 입력" pattern="[a-zA-Z0-9_]+" oninput="this.value=this.value.replace(/[^a-zA-Z0-9_]/g,'')">
                <?php if (!empty($nick_error)): ?><div class="pw-error" style="margin-top:4px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($nick_error); ?></div><?php endif; ?>
                <?php if ($saved && $action === 'nick'): ?><div class="pw-saved" style="margin-top:4px;"><i class="bi bi-check-circle"></i> 닉네임이 변경되었습니다</div><?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ── 이름 폼 ── -->
    <form method="post" id="f-name" onsubmit="saveAll();return false;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="name">
        <div class="field">
            <div class="field-content">
                <label for="mb_name">이름</label>
                <input type="text" name="mb_name" required id="mb_name" value="<?php echo htmlspecialchars($member['mb_name'] ?? ''); ?>" placeholder="이름 입력">
                <?php if (!empty($name_error)): ?><div class="pw-error" style="margin-top:4px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($name_error); ?></div><?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ── 생년월일 폼 ── -->
    <form method="post" id="f-birth" onsubmit="saveAll();return false;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <div class="field">
            <div class="field-content">
                <label for="mb_birth">생년월일</label>
                <?php $birth_value = ($birth_error !== '' && isset($_POST['mb_birth'])) ? (string)$_POST['mb_birth'] : profile_birth_to_input($member['mb_birth'] ?? ''); ?>
                <input type="date" name="mb_birth" id="mb_birth" required autocomplete="bday"
                       max="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d', strtotime('-120 years')); ?>"
                       value="<?php echo htmlspecialchars($birth_value, ENT_QUOTES); ?>">
                <?php if ($birth_error !== ''): ?><div class="pw-error" style="margin-top:4px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($birth_error); ?></div><?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ── 3. 이메일 폼 ── -->
    <div class="field" id="email-field">
        <div class="field-content">
            <label>이메일</label>
            <span style="font-size:calc(15px * var(--fs-scale,1));color:var(--bs-body-color,#202124);"><?php echo htmlspecialchars($member['mb_email'] ?? ''); ?></span>
            <?php if ($email_saved): ?><div class="pw-saved" style="margin-top:4px;"><i class="bi bi-check-circle"></i> 이메일이 변경되었습니다</div><?php endif; ?>
        </div>
        <button type="button" class="field-change-btn" onclick="toggleEmailSection()">변경</button>
    </div>
    <div id="email-section" style="display:<?php echo $email_section_open?'block':'none'; ?>;">
        <div id="email-step1" style="display:<?php echo $email_show_step2?'none':'block'; ?>;">
            <?php if (!empty($email_error) && $_ea === 'send_email_cert'): ?>
                <div class="pw-error" style="margin-bottom:8px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($email_error); ?></div>
            <?php endif; ?>
            <div class="field">
                <div class="field-content"><label for="new_email">새 이메일</label><input type="email" name="new_email" id="new_email" form="f-send-cert" value="<?php echo htmlspecialchars($_POST['new_email'] ?? ''); ?>" placeholder="새 이메일 입력" autocomplete="email"></div>
                <button type="submit" form="f-send-cert" class="field-change-btn">인증 받기</button>
            </div>
        </div>
        <div id="email-step2" style="display:<?php echo $email_show_step2?'block':'none'; ?>;">
            <div style="padding:10px 0 8px;font-size:calc(13px * var(--fs-scale,1));color:#555;"><i class="bi bi-envelope-check" style="color:var(--pri);"></i> <b><?php echo htmlspecialchars($_SESSION['email_cert_new'] ?? ''); ?></b>으로 인증 코드를 발송했습니다</div>
            <?php if (!empty($email_error) && $_ea === 'verify_email_cert'): ?>
                <div class="pw-error" style="margin-bottom:8px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($email_error); ?></div>
            <?php endif; ?>
            <div class="field"><div class="field-content"><label for="cert_code">인증 코드 (6자리)</label><input type="text" name="cert_code" form="f-verify-cert" maxlength="6" placeholder="123456" inputmode="numeric" autocomplete="one-time-code" id="cert_code"></div></div>
            <button type="submit" form="f-verify-cert" class="save-btn">확인</button>
            <button type="button" onclick="document.getElementById('email-step1').style.display='block';document.getElementById('email-step2').style.display='none';" style="width:100%;margin-top:8px;margin-bottom:8px;padding:10px;background:none;border:1px solid #ddd;border-radius:12px;font-size:calc(13px * var(--fs-scale,1));color:#666;cursor:pointer;">이메일 다시 입력</button>
        </div>
    </div>

    <!-- ── 4. 휴대폰 폼 ── -->
    <form method="post" id="f-hp" onsubmit="saveAll();return false;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="hp">
        <div class="field">
            <div class="field-content">
                <label for="hp">휴대폰 번호</label>
                <input type="tel" name="hp" id="hp" value="<?php echo htmlspecialchars($member['mb_hp'] ?? ''); ?>" placeholder="010-0000-0000">
                <?php if ($saved && $action === 'hp'): ?><div class="pw-saved" style="margin-top:4px;"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ── 5. 비밀번호 폼 ── -->
    <div class="field" id="pw-field" style="display:<?php echo ($pw_error||$pw_saved)?'none':'flex'; ?>;">
        <div class="field-content"><label>비밀번호</label><span style="color:var(--bs-secondary-color,#aaa);font-size:calc(14px * var(--fs-scale,1));">••••••••</span></div>
        <button type="button" class="field-change-btn" onclick="togglePwSection()">변경</button>
    </div>
    <form method="post" id="f-pw"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="pw">
        <div id="pw-section" class="password-panel" style="display:<?php echo ($pw_error||$pw_saved)?'block':'none'; ?>;">
            <div class="password-heading"><h2>비밀번호 변경</h2><button type="button" class="field-change-btn" onclick="togglePwSection()">닫기</button></div>
            <?php if ($pw_error): ?><div class="pw-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($pw_error); ?></div><?php endif; ?>
            <?php if ($pw_saved): ?><div class="pw-saved"><i class="bi bi-check-circle"></i> 비밀번호가 변경되었습니다</div><?php endif; ?>
            <div class="password-help" id="password-help">
                비밀번호를 재설정했다면 이메일로 받은 12자리 임시 비밀번호가 현재 비밀번호입니다.
            </div>
            <div class="field"><div class="field-content"><label for="current_pw">현재 비밀번호</label><input type="password" name="current_pw" placeholder="현재 비밀번호 입력" aria-describedby="password-help" autocomplete="current-password" required id="current_pw"></div></div>
            <div class="field"><div class="field-content"><label for="new_pw">새 비밀번호</label><input type="password" name="new_pw" placeholder="6자 이상 입력" autocomplete="new-password" required minlength="6" id="new_pw"></div></div>
            <div class="field"><div class="field-content"><label for="new_pw_confirm">새 비밀번호 확인</label><input type="password" name="new_pw_confirm" placeholder="새 비밀번호 재입력" autocomplete="new-password" required minlength="6" id="new_pw_confirm"></div></div>
            <button type="submit" class="save-btn">비밀번호 변경</button>
        </div>
    </form>

</div>
<form id="f-send-cert" method="post" style="display:none;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>"><input type="hidden" name="action" value="send_email_cert"></form>
<form id="f-verify-cert" method="post" style="display:none;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>"><input type="hidden" name="action" value="verify_email_cert"></form>
<script>
function toggleEmailSection(){
    var s=document.getElementById('email-section');
    s.style.display=s.style.display==='none'?'block':'none';
}
function togglePwSection(){
    var s=document.getElementById('pw-section');
    var f=document.getElementById('pw-field');
    var open=s.style.display==='none';
    s.style.display=open?'block':'none';
    f.style.display=open?'none':'flex';
    if(open) document.getElementById('current_pw').focus();
    else f.querySelector('button').focus();
}
</script>
<!-- 통합 저장 (닉네임/이름/연락처를 한 번에 저장) -->
<form id="f-save-all" method="post" enctype="multipart/form-data" style="display:none;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
    <input type="hidden" name="action" value="save_all">
    <input type="hidden" name="nick" id="sa-nick">
    <input type="hidden" name="mb_name" id="sa-name">
    <input type="hidden" name="hp" id="sa-hp">
    <input type="hidden" name="mb_birth" id="sa-birth">
</form>
<script>
// 프로필 수정 화면 변경 추적: 입력값은 화면에서만 바뀌고, '프로필 저장'을 눌러야 DB에 저장된다.
var profileAllowLeave = false;
var profileInitial = null;
function profileFieldValues(){
    function v(sel){ var el = document.querySelector(sel); return el ? el.value : ''; }
    var photo = document.getElementById('profile_photo_input');
    return {
        nick: v('#f-nick [name="nick"]'), name: v('#f-name [name="mb_name"]'),
        birth: v('#f-birth [name="mb_birth"]'), hp: v('#f-hp [name="hp"]'),
        photo: (photo && photo.files && photo.files.length) ? 'changed' : ''
    };
}
document.addEventListener('DOMContentLoaded', function(){ profileInitial = profileFieldValues(); });
function profileIsDirty(){
    if (!profileInitial) return false;
    var now = profileFieldValues();
    return Object.keys(now).some(function(k){ return now[k] !== profileInitial[k]; });
}
function previewProfilePhoto(input){
    var file = input.files && input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e){
        var box = document.getElementById('avatar-box');
        var img = document.getElementById('avatar-img');
        if (!img) { img = document.createElement('img'); img.id = 'avatar-img'; img.alt = '프로필'; box.appendChild(img); }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
var profileLeaveModalFocus = null;
function openLeaveModal(){
    var m = document.getElementById('leave-modal');
    if (m.classList.contains('open')) return;
    profileLeaveModalFocus = document.activeElement;
    m.classList.add('open'); m.setAttribute('aria-hidden', 'false');
    document.getElementById('leave-save-btn').focus();
}
function handleProfileBack(){
    if (!profileIsDirty()) { profileAllowLeave = true; history.back(); return; }
    openLeaveModal();
}
// 기기 뒤로가기(안드로이드 버튼, iOS 스와이프, 브라우저 뒤로)도 같은 모달로 막는다.
// 앱 WebView 의 뒤로가기는 history.back() 으로 처리되므로 popstate 로 가로챌 수 있다 (앱 업데이트 불필요).
// 수정이 시작되면 같은 주소의 기록을 하나 쌓아 두고, 뒤로가기가 그 기록을 소비할 때 모달을 띄운다.
var profileGuardPushed = false;
function pushProfileGuard(){
    if (profileGuardPushed) return;
    history.pushState({ profileGuard: 1 }, '', location.href);
    profileGuardPushed = true;
}
function syncProfileGuard(){ if (profileIsDirty()) pushProfileGuard(); }
document.addEventListener('input', syncProfileGuard);
document.addEventListener('change', syncProfileGuard);
window.addEventListener('popstate', function(){
    if (!profileGuardPushed) return;
    profileGuardPushed = false;                   // 쌓아 둔 기록이 소비됨
    if (profileAllowLeave) { history.back(); return; }   // 나가기 확정: 한 칸 더 뒤로
    if (profileIsDirty()) { pushProfileGuard(); openLeaveModal(); return; }
    profileAllowLeave = true;                     // 변경 없음: 그대로 나감
    history.back();
});
function closeLeaveModal(){
    var m = document.getElementById('leave-modal');
    m.classList.remove('open'); m.setAttribute('aria-hidden', 'true');
    if (profileLeaveModalFocus && profileLeaveModalFocus.focus) profileLeaveModalFocus.focus();
}
function leaveWithoutSaving(){
    profileAllowLeave = true;
    history.back();
}
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && document.getElementById('leave-modal').classList.contains('open')) closeLeaveModal();
});
function saveAll(){
    var nick = document.querySelector('#f-nick [name="nick"]');
    var name = document.querySelector('#f-name [name="mb_name"]');
    var hp   = document.querySelector('#f-hp [name="hp"]');
    var birth = document.querySelector('#f-birth [name="mb_birth"]');
    if (!nick.reportValidity() || !name.reportValidity() || !birth.reportValidity() || !hp.reportValidity()) return;
    document.getElementById('sa-nick').value = nick ? nick.value : '';
    document.getElementById('sa-name').value = name ? name.value : '';
    document.getElementById('sa-hp').value   = hp ? hp.value : '';
    document.getElementById('sa-birth').value = birth ? birth.value : '';
    var photoInput = document.getElementById('profile_photo_input');
    if (photoInput && photoInput.files && photoInput.files.length) {
        // 선택한 파일이 담긴 실제 입력 요소를 저장 폼으로 옮겨 함께 전송한다(files 대입을 지원하지 않는 WebView 대응).
        photoInput.name = 'profile_photo';
        document.getElementById('f-save-all').appendChild(photoInput);
    }
    profileAllowLeave = true;
    document.getElementById('f-save-all').submit();
}
</script>
<div class="leave-modal" id="leave-modal" aria-hidden="true">
    <div class="leave-modal-box" role="dialog" aria-modal="true" aria-labelledby="leave-modal-title">
        <h2 id="leave-modal-title">변경사항을 저장하지 않았어요</h2>
        <p>저장하지 않고 나가면 수정한 내용이 사라져요.</p>
        <div class="leave-modal-actions">
            <button type="button" class="leave-modal-save" id="leave-save-btn" onclick="closeLeaveModal();saveAll()">저장</button>
            <button type="button" class="leave-modal-discard" onclick="leaveWithoutSaving()">저장하지 않고 나가기</button>
        </div>
    </div>
</div>
<div class="save-bar">
    <button type="button" class="save-bar-btn" onclick="saveAll()">프로필 저장</button>
</div>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script>
(function(){
    var root = document.documentElement;
    var bar = document.querySelector('.save-bar');
    var nav = document.getElementById('bottomNav');
    function syncBottomSpacing(){
        var navHeight = nav ? nav.getBoundingClientRect().height : 0;
        bar.classList.toggle('without-nav', navHeight === 0);
        root.style.setProperty('--profile-nav-height', navHeight + 'px');
        root.style.setProperty('--profile-save-height', bar.getBoundingClientRect().height + 'px');
    }
    syncBottomSpacing();
    if (window.ResizeObserver) {
        var observer = new ResizeObserver(syncBottomSpacing);
        observer.observe(bar);
        if (nav) observer.observe(nav);
    }
    window.addEventListener('resize', syncBottomSpacing);
})();
</script>
</body></html>

<?php
require_once __DIR__ . '/includes/init.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!is_gangnamunni_logged_in()) { header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME'],2),'/').'/bbs/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

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

$saved = false;
$photo_upload_error = '';
$pw_error = '';
$pw_saved = false;
$email_error = '';
$email_cert_sent = false;
$email_saved = false;
$nick_error = '';
$name_error = '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. 프로필 사진 ──────────────────────────────────────
    if ($action === 'photo' && !empty($_FILES['profile_photo']['tmp_name'])) {
        $file    = $_FILES['profile_photo'];
        $mime    = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed)) {
            $photo_upload_error = '이미지 파일만 업로드 가능합니다.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $photo_upload_error = '파일 크기는 5MB 이하여야 합니다.';
        } else {
            if (!is_dir($profile_photo_dir)) {
                mkdir($profile_photo_dir, 0775, true);
            }
            foreach ($photo_exts as $ext) {
                $old = $profile_photo_dir . $member_id . '.' . $ext;
                if (file_exists($old)) unlink($old);
            }
            $ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];
            $save_ext  = $ext_map[$mime] ?? 'jpg';
            $save_path = $profile_photo_dir . $member_id . '.' . $save_ext;
            if (move_uploaded_file($file['tmp_name'], $save_path)) {
                $profile_photo_url = 'uploads/profile/' . $member_id . '.' . $save_ext . '?v=' . time();
                $saved = true;
            } else {
                $photo_upload_error = '업로드에 실패했습니다.';
            }
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

    // ── 통합 저장 (하단 고정 '저장' 버튼) : 닉네임 + 이름 + 연락처 한 번에 ──
    if ($action === 'save_all') {
        $nick = trim($_POST['nick'] ?? '');
        $name = trim($_POST['mb_name'] ?? '');
        $hp   = trim($_POST['hp'] ?? '');
        if ($name === '') {
            $name_error = '이름을 입력해주세요.';
        } elseif ($nick === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
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
                $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_nick=?, mb_name=?, mb_hp=? WHERE mb_id=?");
                mysqli_stmt_bind_param($stmt, 'ssss', $nick, $name, $hp, $member_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $saved = true;
                $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
                if ($res) $member = mysqli_fetch_assoc($res);
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
                $pw_error = '현재 비밀번호가 올바르지 않습니다.';
            } else {
                $new_hash = function_exists('create_hash') ? create_hash($new_pw) : password_hash($new_pw, PASSWORD_DEFAULT);
                $ps = mysqli_prepare($connect, "UPDATE g5_member SET mb_password=? WHERE mb_id=?");
                mysqli_stmt_bind_param($ps, 'ss', $new_hash, $member_id);
                mysqli_stmt_execute($ps);
                mysqli_stmt_close($ps);
                $pw_saved = true;
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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>프로필 수정 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{position:relative;padding:14px 16px;display:flex;align-items:center;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;flex-shrink:0;}
.header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;white-space:nowrap;}
.form-area{padding:20px 16px 150px;}
.field{margin-bottom:12px;background:#fff;padding:18px 14px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;align-items:center;gap:10px;}
.field-content{flex:1;min-width:0;}
.field label{display:block;font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:#000;margin-bottom:7px;}
.field input{width:100%;padding:5px 0;border:none;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));background:transparent;color:var(--bs-body-color);}
.field input:focus{outline:none;}
.field-change-btn{flex-shrink:0;padding:6px 12px;background:none;border:none;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:#AE1932;cursor:pointer;white-space:nowrap;}
.save-btn{width:100%;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:pointer;margin-top:8px;}
.saved-msg{text-align:center;padding:10px;color:#22c55e;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
/* 하단 고정 저장 바 — bottom_nav(77px, z-index:9999) 바로 위 */
.save-bar{position:fixed;bottom:77px;left:50%;transform:translateX(-50%);width:100%;max-width:640px;padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px));background:var(--bs-body-bg,#fff);border-top:1px solid var(--bs-border-color,#eee);z-index:9998;}
.save-bar-btn{width:100%;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;cursor:pointer;}
.save-bar-btn:active{filter:brightness(0.95);}
.avatar-area{text-align:center;padding:24px;}
.avatar-wrap{position:relative;width:80px;margin:0 auto 8px;cursor:pointer;}
.avatar{width:80px;height:80px;border-radius:50%;background:#fff;border:2px solid #918f8f;display:flex;align-items:center;justify-content:center;font-size:calc(32px * var(--fs-scale,1) * var(--fs-page,1));color:var(--pri);overflow:hidden;}
.avatar img{width:100%;height:100%;object-fit:cover;}
.avatar-edit-btn{position:absolute;bottom:0;right:0;width:32px;height:32px;background:var(--pri);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;border:2px solid #fff;}
.photo-error{text-align:center;padding:6px 16px;color:#ef4444;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));}
.section-title{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:#888;padding:8px 0 6px;margin-bottom:4px;}
.pw-error{color:#ef4444;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));padding:6px 0 2px;}
.pw-saved{color:#22c55e;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));padding:6px 0 2px;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>내 정보 수정</h1></div>
<?php if ($saved): ?><div class="saved-msg"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
<?php if ($photo_upload_error): ?><div class="photo-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($photo_upload_error); ?></div><?php endif; ?>

    <!-- ── 1. 사진 업로드 폼 (avatar 포함) ── -->
    <form method="post" enctype="multipart/form-data" id="f-photo">
        <input type="hidden" name="action" value="photo">
        <input type="file" id="profile_photo_input" name="profile_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
        <div class="avatar-area">
            <div class="avatar-wrap" onclick="document.getElementById('profile_photo_input').click()">
                <div class="avatar">
                    <?php if ($profile_photo_url): ?>
                        <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="프로필">
                    <?php endif; ?>
                </div>
                <div class="avatar-edit-btn"><i class="bi bi-gear"></i></div>
            </div>
        </div>
    </form>

<div class="form-area">

    <!-- ── 2. 닉네임 폼 ── -->
    <form method="post" id="f-nick">
        <input type="hidden" name="action" value="nick">
        <div class="field">
            <div class="field-content">
                <label>닉네임</label>
                <input type="text" name="nick" id="nick-input" value="<?php echo htmlspecialchars($member['mb_nick'] ?? ''); ?>" placeholder="영문, 숫자, _ 만 입력" pattern="[a-zA-Z0-9_]+" oninput="this.value=this.value.replace(/[^a-zA-Z0-9_]/g,'')">
                <?php if (!empty($nick_error)): ?><div class="pw-error" style="margin-top:4px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($nick_error); ?></div><?php endif; ?>
                <?php if ($saved && $action === 'nick'): ?><div class="pw-saved" style="margin-top:4px;"><i class="bi bi-check-circle"></i> 닉네임이 변경되었습니다</div><?php endif; ?>
            </div>
            <button type="submit" class="field-change-btn">변경</button>
        </div>
    </form>

    <!-- ── 이름 폼 ── -->
    <form method="post" id="f-name">
        <input type="hidden" name="action" value="name">
        <div class="field">
            <div class="field-content">
                <label>이름</label>
                <input type="text" name="mb_name" value="<?php echo htmlspecialchars($member['mb_name'] ?? ''); ?>" placeholder="이름 입력">
                <?php if (!empty($name_error)): ?><div class="pw-error" style="margin-top:4px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($name_error); ?></div><?php endif; ?>
            </div>
            <button type="submit" class="field-change-btn">변경</button>
        </div>
    </form>

    <!-- ── 3. 이메일 폼 ── -->
    <div class="field" id="email-field">
        <div class="field-content">
            <label>이메일</label>
            <span style="font-size:calc(15px * var(--fs-scale,1));color:var(--bs-body-color);"><?php echo htmlspecialchars($member['mb_email'] ?? ''); ?></span>
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
                <div class="field-content"><label>새 이메일</label><input type="email" name="new_email" form="f-send-cert" value="<?php echo htmlspecialchars($_POST['new_email'] ?? ''); ?>" placeholder="새 이메일 입력" autocomplete="email"></div>
                <button type="submit" form="f-send-cert" class="field-change-btn">인증 받기</button>
            </div>
        </div>
        <div id="email-step2" style="display:<?php echo $email_show_step2?'block':'none'; ?>;">
            <div style="padding:10px 0 8px;font-size:calc(13px * var(--fs-scale,1));color:#555;"><i class="bi bi-envelope-check" style="color:var(--pri);"></i> <b><?php echo htmlspecialchars($_SESSION['email_cert_new'] ?? ''); ?></b>으로 인증 코드를 발송했습니다</div>
            <?php if (!empty($email_error) && $_ea === 'verify_email_cert'): ?>
                <div class="pw-error" style="margin-bottom:8px;"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($email_error); ?></div>
            <?php endif; ?>
            <div class="field"><div class="field-content"><label>인증 코드 (6자리)</label><input type="text" name="cert_code" form="f-verify-cert" maxlength="6" placeholder="123456" inputmode="numeric" autocomplete="one-time-code"></div></div>
            <button type="submit" form="f-verify-cert" class="save-btn">확인</button>
            <button type="button" onclick="document.getElementById('email-step1').style.display='block';document.getElementById('email-step2').style.display='none';" style="width:100%;margin-top:8px;margin-bottom:8px;padding:10px;background:none;border:1px solid #ddd;border-radius:12px;font-size:calc(13px * var(--fs-scale,1));color:#666;cursor:pointer;">이메일 다시 입력</button>
        </div>
    </div>

    <!-- ── 4. 휴대폰 폼 ── -->
    <form method="post" id="f-hp">
        <input type="hidden" name="action" value="hp">
        <div class="field">
            <div class="field-content">
                <label>휴대폰 번호</label>
                <input type="tel" name="hp" value="<?php echo htmlspecialchars($member['mb_hp'] ?? ''); ?>" placeholder="010-0000-0000">
                <?php if ($saved && $action === 'hp'): ?><div class="pw-saved" style="margin-top:4px;"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
            </div>
            <button type="submit" class="field-change-btn">변경</button>
        </div>
    </form>

    <!-- ── 5. 비밀번호 폼 ── -->
    <div class="field" id="pw-field" style="display:<?php echo ($pw_error||$pw_saved)?'none':'flex'; ?>;">
        <div class="field-content"><label>비밀번호</label><span style="color:var(--bs-secondary-color,#aaa);font-size:calc(14px * var(--fs-scale,1));">••••••••</span></div>
        <button type="button" class="field-change-btn" onclick="togglePwSection()">변경</button>
    </div>
    <form method="post" id="f-pw">
        <input type="hidden" name="action" value="pw">
        <div id="pw-section" style="display:<?php echo ($pw_error||$pw_saved)?'block':'none'; ?>;">
            <?php if ($pw_error): ?><div class="pw-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($pw_error); ?></div><?php endif; ?>
            <?php if ($pw_saved): ?><div class="pw-saved"><i class="bi bi-check-circle"></i> 비밀번호가 변경되었습니다</div><?php endif; ?>
            <div class="field" style="margin-top:12px;"><div class="field-content"><label>현재 비밀번호</label><input type="password" name="current_pw" placeholder="현재 비밀번호 입력"></div></div>
            <div class="field"><div class="field-content"><label>새 비밀번호</label><input type="password" name="new_pw" placeholder="6자 이상 입력"></div></div>
            <div class="field"><div class="field-content"><label>새 비밀번호 확인</label><input type="password" name="new_pw_confirm" placeholder="새 비밀번호 재입력"></div></div>
            <button type="submit" class="save-btn">확인</button>
        </div>
    </form>

</div>
<form id="f-send-cert" method="post" style="display:none;"><input type="hidden" name="action" value="send_email_cert"></form>
<form id="f-verify-cert" method="post" style="display:none;"><input type="hidden" name="action" value="verify_email_cert"></form>
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
}
</script>
<!-- 통합 저장 (닉네임/이름/연락처를 한 번에 저장) — 개별 '변경' 버튼도 그대로 동작 -->
<form id="f-save-all" method="post" style="display:none;">
    <input type="hidden" name="action" value="save_all">
    <input type="hidden" name="nick" id="sa-nick">
    <input type="hidden" name="mb_name" id="sa-name">
    <input type="hidden" name="hp" id="sa-hp">
</form>
<div class="save-bar">
    <button type="button" class="save-bar-btn" onclick="saveAll()">저장</button>
</div>
<script>
function saveAll(){
    var nick = document.querySelector('#f-nick [name="nick"]');
    var name = document.querySelector('#f-name [name="mb_name"]');
    var hp   = document.querySelector('#f-hp [name="hp"]');
    document.getElementById('sa-nick').value = nick ? nick.value : '';
    document.getElementById('sa-name').value = name ? name.value : '';
    document.getElementById('sa-hp').value   = hp ? hp.value : '';
    document.getElementById('f-save-all').submit();
}
</script>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>

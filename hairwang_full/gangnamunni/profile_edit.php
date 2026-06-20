<?php
require_once __DIR__ . '/includes/init.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    $hp = trim($_POST['hp'] ?? '');
    $addr = trim($_POST['addr'] ?? '');
    if ($name || $nick) {
        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_name=?, mb_nick=?, mb_hp=?, mb_addr1=? WHERE mb_id=?");
        mysqli_stmt_bind_param($stmt, 'sssss', $name, $nick, $hp, $addr, $member_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $saved = true;
        $res = mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_id='".mysqli_real_escape_string($connect,$member_id)."'");
        if ($res) $member = mysqli_fetch_assoc($res);
    }
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
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.form-area{padding:20px 16px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:var(--bs-secondary-color,#888);margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--bs-border-color,#ddd);border-radius:10px;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));background:var(--bs-body-bg,#fff);color:var(--bs-body-color);}
.field input:focus{outline:none;border-color:var(--pri);}
.save-btn{width:100%;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:pointer;margin-top:8px;}
.saved-msg{text-align:center;padding:10px;color:#22c55e;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
.avatar-area{text-align:center;padding:24px;}
.avatar{width:80px;height:80px;border-radius:50%;background:var(--bs-tertiary-bg,#eee);display:flex;align-items:center;justify-content:center;font-size:calc(32px * var(--fs-scale,1) * var(--fs-page,1));color:var(--pri);margin:0 auto 8px;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>프로필 수정</h1></div>
<?php if ($saved): ?><div class="saved-msg"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
<div class="avatar-area"><div class="avatar"><i class="bi bi-person-fill"></i></div><div style="font-size:13px;color:var(--bs-secondary-color);"><?php echo htmlspecialchars($member_id); ?></div></div>
<form method="post" class="form-area">
    <div class="field"><label>이름</label><input type="text" name="name" value="<?php echo htmlspecialchars($member['mb_name'] ?? ''); ?>"></div>
    <div class="field"><label>닉네임</label><input type="text" name="nick" value="<?php echo htmlspecialchars($member['mb_nick'] ?? ''); ?>"></div>
    <div class="field"><label>연락처</label><input type="tel" name="hp" value="<?php echo htmlspecialchars($member['mb_hp'] ?? ''); ?>" placeholder="010-0000-0000"></div>
    <div class="field"><label>주소</label><input type="text" name="addr" value="<?php echo htmlspecialchars($member['mb_addr1'] ?? ''); ?>"></div>
    <div class="field"><label>이메일 (변경 불가)</label><input type="email" value="<?php echo htmlspecialchars($member['mb_email'] ?? ''); ?>" disabled></div>
    <button type="submit" class="save-btn">저장</button>
</form>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>

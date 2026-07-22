<?php
require_once __DIR__ . '/includes/init.php';
if (!is_gangnamunni_logged_in()) { header('Location: auth/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY, member_id VARCHAR(100) NOT NULL UNIQUE,
    noti_booking TINYINT(1) DEFAULT 1, noti_chat TINYINT(1) DEFAULT 1,
    noti_event TINYINT(1) DEFAULT 1, noti_promo TINYINT(1) DEFAULT 1,
    noti_review TINYINT(1) DEFAULT 1, noti_push TINYINT(1) DEFAULT 1,
    noti_general TINYINT(1) DEFAULT 1, noti_message TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($connect, "ALTER TABLE gangnamunni_notification_settings ADD COLUMN IF NOT EXISTS noti_general TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS noti_message TINYINT(1) DEFAULT 1");
mysqli_query($connect, "ALTER TABLE gangnamunni_notification_settings ADD COLUMN IF NOT EXISTS mkt_push TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS mkt_sms TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS mkt_email TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS mkt_night_push TINYINT(1) DEFAULT 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['noti_booking','noti_chat','noti_event','noti_promo','noti_review','noti_push','noti_general','noti_message','mkt_push','mkt_sms','mkt_email','mkt_night_push'];
    $vals = [];
    foreach ($fields as $f) $vals[$f] = isset($_POST[$f]) ? 1 : 0;
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_notification_settings (member_id,noti_booking,noti_chat,noti_event,noti_promo,noti_review,noti_push,noti_general,noti_message,mkt_push,mkt_sms,mkt_email,mkt_night_push) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE noti_booking=VALUES(noti_booking),noti_chat=VALUES(noti_chat),noti_event=VALUES(noti_event),noti_promo=VALUES(noti_promo),noti_review=VALUES(noti_review),noti_push=VALUES(noti_push),noti_general=VALUES(noti_general),noti_message=VALUES(noti_message),mkt_push=VALUES(mkt_push),mkt_sms=VALUES(mkt_sms),mkt_email=VALUES(mkt_email),mkt_night_push=VALUES(mkt_night_push)");
    mysqli_stmt_bind_param($stmt, 'siiiiiiiiiiii', $member_id, $vals['noti_booking'], $vals['noti_chat'], $vals['noti_event'], $vals['noti_promo'], $vals['noti_review'], $vals['noti_push'], $vals['noti_general'], $vals['noti_message'], $vals['mkt_push'], $vals['mkt_sms'], $vals['mkt_email'], $vals['mkt_night_push']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $saved = true;
}

$settings = ['noti_booking'=>1,'noti_chat'=>1,'noti_event'=>1,'noti_promo'=>1,'noti_review'=>1,'noti_push'=>1,'noti_general'=>1,'noti_message'=>1,'mkt_push'=>1,'mkt_sms'=>1,'mkt_email'=>1,'mkt_night_push'=>0];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_notification_settings WHERE member_id='".mysqli_real_escape_string($connect,$member_id)."'");
if ($res && $row = mysqli_fetch_assoc($res)) $settings = $row;
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>알림 설정 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{position:relative;padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.setting-item{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);}
.setting-item label{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.toggle{position:relative;width:44px;height:24px;}
.toggle input{opacity:0;width:0;height:0;}
.toggle .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;}
.toggle input:checked+.slider{background:var(--pri);}
.toggle .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
.toggle input:checked+.slider:before{transform:translateX(20px);}
.save-btn{margin:20px;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;width:calc(100% - 40px);cursor:pointer;}
.saved{text-align:center;padding:10px;color:#22c55e;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
.section-label{padding:16px 20px 8px;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:var(--bs-secondary-color,#888);}
.service-item{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);text-decoration:none;color:var(--bs-body-color);}
.service-item span{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.service-item.red span{color:var(--pri);}
.service-item i{color:var(--bs-secondary-color,#ccc);font-size:14px;}
.service-section{background:#fff;margin:10px 16px;overflow:hidden;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>알림</h1></div>
<?php if (!empty($saved)): ?><div class="saved"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>

<div class="service-section">
    <div class="section-label">서비스 안내</div>
    <a href="#" class="service-item red"><span>제휴 및 광고문의</span></a>
    <a href="#" class="service-item"><span>이용약관</span></a>
    <a href="#" class="service-item"><span>개인정보처리 방침</span></a>
    <a href="#" class="service-item" style="border-bottom:none;"><span>위치기반 서비스 이용약관</span></a>
</div>

<form method="post">

<div class="service-section">
    <div class="section-label">알림 설정</div>
    <div class="setting-item">
        <div><label>일반 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_general" value="1" <?php echo ($settings['noti_general'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item" style="border-bottom:none;">
        <div><label>쪽지 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_message" value="1" <?php echo ($settings['noti_message'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
</div>

<div class="service-section">
    <div class="section-label">마케팅 정보 수신</div>
    <div class="setting-item">
        <div><label>푸시</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_push" value="1" <?php echo ($settings['mkt_push'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>SMS</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_sms" value="1" <?php echo ($settings['mkt_sms'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>이메일</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_email" value="1" <?php echo ($settings['mkt_email'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item" style="border-bottom:none;">
        <div><label>야간 푸시 (오후 9시 - 오전 8시)</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_night_push" value="1" <?php echo ($settings['mkt_night_push'] ?? 0) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
</div>

</form>

<div id="toast" style="display:none;position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:8px 18px;border-radius:20px;font-size:13px;z-index:9999;">저장되었습니다</div>

<script>
document.querySelectorAll('form input[type="checkbox"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var form = document.querySelector('form');
        var data = new FormData(form);
        // checkbox o'chirilganda FormData ga qo'shilmaydi — 0 qiymat bilan qo'shamiz
        form.querySelectorAll('input[type="checkbox"]').forEach(function(c) {
            if (!c.checked) data.set(c.name, '0');
        });
        fetch('', { method: 'POST', body: data })
            .then(function() {
                var toast = document.getElementById('toast');
                toast.style.display = 'block';
                setTimeout(function() { toast.style.display = 'none'; }, 1500);
            });
    });
});
</script>

<div class="service-section" style="margin-top:10px;margin-bottom:30px;">
    <div class="section-label">계정 관리</div>
    <a href="#" class="service-item" style="border-bottom:none;color:#e53935;" onclick="return confirm('정말 탈퇴하시겠습니까?')">
        <span>회원 탈퇴</span>
    </a>
</div>
</body></html>

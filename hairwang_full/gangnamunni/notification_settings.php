<?php
require_once __DIR__ . '/includes/init.php';
if (!is_gangnamunni_logged_in()) { header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME'],2),'/').'/bbs/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY, member_id VARCHAR(100) NOT NULL UNIQUE,
    noti_booking TINYINT(1) DEFAULT 1, noti_chat TINYINT(1) DEFAULT 1,
    noti_event TINYINT(1) DEFAULT 1, noti_promo TINYINT(1) DEFAULT 1,
    noti_review TINYINT(1) DEFAULT 1, noti_push TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['noti_booking','noti_chat','noti_event','noti_promo','noti_review','noti_push'];
    $vals = [];
    foreach ($fields as $f) $vals[$f] = isset($_POST[$f]) ? 1 : 0;
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_notification_settings (member_id,noti_booking,noti_chat,noti_event,noti_promo,noti_review,noti_push) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE noti_booking=VALUES(noti_booking),noti_chat=VALUES(noti_chat),noti_event=VALUES(noti_event),noti_promo=VALUES(noti_promo),noti_review=VALUES(noti_review),noti_push=VALUES(noti_push)");
    mysqli_stmt_bind_param($stmt, 'siiiiii', $member_id, $vals['noti_booking'], $vals['noti_chat'], $vals['noti_event'], $vals['noti_promo'], $vals['noti_review'], $vals['noti_push']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $saved = true;
}

$settings = ['noti_booking'=>1,'noti_chat'=>1,'noti_event'=>1,'noti_promo'=>1,'noti_review'=>1,'noti_push'=>1];
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
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.setting-item{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);}
.setting-item label{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.setting-item .desc{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
.toggle{position:relative;width:44px;height:24px;}
.toggle input{opacity:0;width:0;height:0;}
.toggle .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;}
.toggle input:checked+.slider{background:var(--pri);}
.toggle .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
.toggle input:checked+.slider:before{transform:translateX(20px);}
.save-btn{margin:20px;padding:14px;background:var(--pri);color:#fff;border:none;border-radius:12px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;width:calc(100% - 40px);cursor:pointer;}
.saved{text-align:center;padding:10px;color:#22c55e;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>알림 설정</h1></div>
<?php if (!empty($saved)): ?><div class="saved"><i class="bi bi-check-circle"></i> 저장되었습니다</div><?php endif; ?>
<form method="post">
<?php
$items = [
    ['noti_push','푸시 알림','전체 푸시 알림 수신'],
    ['noti_booking','예약 알림','예약 확인, 변경, 리마인더'],
    ['noti_chat','채팅 알림','새 상담 메시지 알림'],
    ['noti_event','이벤트 알림','이벤트, 응모 결과 알림'],
    ['noti_promo','프로모션 알림','할인, 쿠폰 등 혜택 알림'],
    ['noti_review','후기 알림','후기 답변, 좋아요 알림'],
];
foreach ($items as $item): ?>
<div class="setting-item">
    <div><label><?php echo $item[1]; ?></label><div class="desc"><?php echo $item[2]; ?></div></div>
    <label class="toggle"><input type="checkbox" name="<?php echo $item[0]; ?>" value="1" <?php echo ($settings[$item[0]] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
</div>
<?php endforeach; ?>
<button type="submit" class="save-btn">저장</button>
</form>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>

<?php
require_once __DIR__ . '/includes/init.php';
$member_id = is_gangnamunni_logged_in() ? get_gangnamunni_member_id() : null;

// DB에서 온 아이콘/색상 값은 클래스·style 속성에 그대로 들어가므로, CSS/속성 주입을 막기 위해
// 화이트리스트(부트스트랩 아이콘 클래스 / 헥스 색상)로 검증한 뒤에만 출력한다.
function ms_icon($v) { $v = (string)$v; return preg_match('/^bi-[a-z0-9-]+$/', $v) ? $v : 'bi-shield'; }
function ms_color($v) { $v = (string)$v; return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : '#888888'; }

// 회원 등급 테이블은 배포 시 sql/points_ledger.sql 로만 생성한다.
// Web request에서 DDL을 실행하지 않고, 누락 시 명시적인 배포 오류로 중단한다.
try {
    $__ml = mysqli_query($connect, "SELECT COUNT(*) AS c FROM gangnamunni_membership_levels");
} catch (Throwable $e) {
    $__ml = false;
    error_log('[membership] membership schema check failed: ' . $e->getMessage());
}
if (!$__ml) {
    error_log('[membership] gangnamunni_membership_levels is missing; run gangnamunni/sql/points_ledger.sql');
    http_response_code(503);
    exit('서비스 준비 중입니다. 관리자에게 문의해주세요.');
}
$__ml_cnt = (int)(mysqli_fetch_assoc($__ml)['c'] ?? 0);

// 샘플 등급(비어 있을 때만 시드)
if ($__ml_cnt === 0) {
    $levels = [
        ['브론즈', 0, 'bi-shield', '#cd7f32', '기본 혜택, 후기 작성 포인트 적립', 0],
        ['실버', 1000, 'bi-shield-fill', '#c0c0c0', '5% 할인 쿠폰 매월 지급, 우선 예약', 5],
        ['골드', 5000, 'bi-trophy', '#ffd700', '10% 할인, 무료 상담, VIP 이벤트 초대', 10],
        ['다이아몬드', 20000, 'bi-gem', '#AE1932', '15% 할인, 전용 상담사, 무료 시술 쿠폰', 15],
    ];
    foreach ($levels as $i => $l) {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_membership_levels (name,min_points,icon,color,benefits,discount_rate,sort_order) VALUES (?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sisssdi', $l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $i);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$levels = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_membership_levels ORDER BY sort_order");
if ($res) while ($r = mysqli_fetch_assoc($res)) $levels[] = $r;

// 현재 사용자 포인트 — 원장(#포인트제도) 잔액 사용
$user_points = 0;
$user_level = $levels[0] ?? null;
if ($member_id) {
    require_once __DIR__ . '/includes/points_ledger.php';
    $user_points = points_balance_safe($connect, points_member_no($connect, $member_id)); // 원장 미설치 시 0
    foreach ($levels as $l) { if ($user_points >= $l['min_points']) $user_level = $l; }
}
// mysqli_close moved to end($connect);
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>회원 등급 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.my-level{padding:24px 16px;text-align:center;background:linear-gradient(135deg,#AE1932,#a855f7);color:#fff;margin:16px;border-radius:16px;}
.my-level .icon{font-size:calc(40px * var(--fs-scale,1) * var(--fs-page,1));margin-bottom:8px;}
.my-level .name{font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;}
.my-level .pts{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));opacity:0.85;margin-top:4px;}
.level-card{display:flex;align-items:center;gap:14px;padding:16px;margin:0 16px 10px;background:var(--bs-body-bg,#fff);border-radius:12px;border:1px solid var(--bs-border-color,#eee);}
.level-card.current{border-color:var(--pri);box-shadow:0 0 0 2px rgba(124,58,237,0.2);}
.level-icon{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1));}
.level-info{flex:1;}
.level-name{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.level-req{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-top:2px;}
.level-benefits{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));margin-top:4px;line-height:1.5;}
.level-discount{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:var(--pri);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>회원 등급</h1></div>
<?php if ($member_id && $user_level): ?>
<div class="my-level">
    <div class="icon"><i class="bi <?php echo ms_icon($user_level['icon']); ?>"></i></div>
    <div class="name"><?php echo htmlspecialchars($user_level['name']); ?></div>
    <div class="pts">누적 <?php echo number_format($user_points); ?>P</div>
</div>
<?php endif; ?>
<div style="padding:8px 16px;font-size:13px;font-weight:600;">등급 안내</div>
<?php foreach ($levels as $l): ?>
<div class="level-card <?php echo ($user_level && $user_level['id']==$l['id'])?'current':''; ?>">
    <?php $__c = ms_color($l['color']); ?>
    <div class="level-icon" style="background:<?php echo $__c; ?>20;color:<?php echo $__c; ?>;"><i class="bi <?php echo ms_icon($l['icon']); ?>"></i></div>
    <div class="level-info">
        <div class="level-name" style="color:<?php echo $__c; ?>;"><?php echo htmlspecialchars($l['name']); ?></div>
        <div class="level-req"><?php echo number_format($l['min_points']); ?>P 이상</div>
        <div class="level-benefits"><?php echo htmlspecialchars($l['benefits'] ?? ''); ?></div>
    </div>
    <?php if ($l['discount_rate'] > 0): ?><div class="level-discount"><?php echo number_format((float)$l['discount_rate'], 1); ?>%</div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
<?php mysqli_close($connect); ?>

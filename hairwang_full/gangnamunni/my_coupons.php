<?php
/**
 * 내 쿠폰 목록 페이지
 */
require_once __DIR__ . '/includes/init.php';

if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('/hairwang/gangnamunni/my_coupons.php'));
    exit;
}

$m = get_gangnamunni_member();
$mb_no = (int)($m['mb_no'] ?? 0);
$mb_nick = htmlspecialchars($m['mb_nick'] ?? $m['mb_id'] ?? '');

// 쿠폰 목록
$coupons = [];
$cq = mysqli_prepare($connect, "SELECT cc.*, c.name as coupon_name, c.discount_type, c.discount_value, c.min_order_amount, c.expires_at as coupon_expires
    FROM gangnamunni_coupon_claims cc
    LEFT JOIN gangnamunni_coupons c ON cc.coupon_id = c.id
    WHERE cc.member_id = ?
    ORDER BY cc.status ASC, cc.claimed_at DESC
    LIMIT 50");
if ($cq) {
    mysqli_stmt_bind_param($cq, 'i', $mb_no);
    mysqli_stmt_execute($cq);
    $r = mysqli_stmt_get_result($cq);
    while ($row = mysqli_fetch_assoc($r)) $coupons[] = $row;
    mysqli_stmt_close($cq);
}

$available = array_filter($coupons, fn($c) => ($c['status'] ?? '') === 'available');
$used = array_filter($coupons, fn($c) => ($c['status'] ?? '') !== 'available');

if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
require_once __DIR__ . '/includes/global_nav.php';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>내 쿠폰 — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);padding-bottom:90px;}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.tabs{display:flex;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.tab{flex:1;text-align:center;padding:12px;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;border:none;background:none;cursor:pointer;color:var(--bs-secondary-color,#888);border-bottom:2px solid transparent;}
.tab.active{color:var(--pri);border-bottom-color:var(--pri);}
.tab-content{display:none;}.tab-content.active{display:block;}
.coupon-card{margin:12px 16px;border:1px dashed var(--bs-border-color,#ddd);border-radius:12px;padding:16px;background:var(--bs-body-bg,#fff);position:relative;overflow:hidden;}
.coupon-card.used{opacity:0.5;}
.coupon-name{font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;margin-bottom:6px;}
.coupon-value{font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;color:var(--pri);}
.coupon-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-top:8px;}
.coupon-status{position:absolute;top:10px;right:10px;font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));padding:2px 8px;border-radius:8px;font-weight:600;}
.coupon-status.available{background:#dcfce7;color:#16a34a;}
.coupon-status.used{background:#fee2e2;color:#991b1b;}
.coupon-status.expired{background:#f3f4f6;color:#6b7280;}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color,#999);}
.empty i{font-size:calc(48px * var(--fs-scale,1) * var(--fs-page,1));display:block;margin-bottom:12px;opacity:0.5;}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <h1>내 쿠폰</h1>
</div>

<div class="tabs">
    <button class="tab active" onclick="switchTab('available')">사용 가능 (<?php echo count($available); ?>)</button>
    <button class="tab" onclick="switchTab('used')">사용완료/만료 (<?php echo count($used); ?>)</button>
</div>

<div class="tab-content active" id="tab-available">
<?php if (empty($available)): ?>
<div class="empty"><i class="bi bi-ticket-perforated"></i><p>사용 가능한 쿠폰이 없습니다.</p></div>
<?php else: ?>
<?php foreach ($available as $c): ?>
<div class="coupon-card">
    <span class="coupon-status available">사용가능</span>
    <div class="coupon-name"><?php echo htmlspecialchars($c['coupon_name'] ?? '쿠폰'); ?></div>
    <div class="coupon-value">
        <?php if (($c['discount_type'] ?? '') === 'percent'): ?>
            <?php echo intval($c['discount_value']); ?>% 할인
        <?php else: ?>
            <?php echo number_format($c['discount_value'] ?? 0); ?>원 할인
        <?php endif; ?>
    </div>
    <div class="coupon-meta">
        <?php if (!empty($c['min_order_amount'])): ?>최소주문 <?php echo number_format($c['min_order_amount']); ?>원 | <?php endif; ?>
        <?php echo !empty($c['coupon_expires']) ? date('Y.m.d', strtotime($c['coupon_expires'])) . '까지' : '무기한'; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<div class="tab-content" id="tab-used">
<?php if (empty($used)): ?>
<div class="empty"><i class="bi bi-ticket-perforated"></i><p>사용완료/만료 쿠폰이 없습니다.</p></div>
<?php else: ?>
<?php foreach ($used as $c): ?>
<div class="coupon-card used">
    <span class="coupon-status <?php echo $c['status']; ?>"><?php echo $c['status'] === 'used' ? '사용완료' : '만료'; ?></span>
    <div class="coupon-name"><?php echo htmlspecialchars($c['coupon_name'] ?? '쿠폰'); ?></div>
    <div class="coupon-value" style="color:var(--bs-secondary-color,#888);">
        <?php if (($c['discount_type'] ?? '') === 'percent'): ?>
            <?php echo intval($c['discount_value']); ?>% 할인
        <?php else: ?>
            <?php echo number_format($c['discount_value'] ?? 0); ?>원 할인
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<script>
function switchTab(t) {
    document.querySelectorAll('.tab').forEach(function(b,i){b.classList.toggle('active',t==='available'?i===0:i===1);});
    document.getElementById('tab-available').classList.toggle('active',t==='available');
    document.getElementById('tab-used').classList.toggle('active',t==='used');
}
</script>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
<?php mysqli_close($connect); ?>

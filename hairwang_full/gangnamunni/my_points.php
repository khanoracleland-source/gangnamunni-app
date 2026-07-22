<?php
/**
 * 내 포인트 내역 페이지
 */
require_once __DIR__ . '/includes/init.php';

if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('/hairwang/gangnamunni/my_points.php'));
    exit;
}

$m = get_gangnamunni_member();
$mb_no = (int)($m['mb_no'] ?? 0);
$mb_nick = htmlspecialchars($m['mb_nick'] ?? $m['mb_id'] ?? '');

// 포인트 잔액
$balance = 0;
$bq = mysqli_prepare($connect, "SELECT COALESCE(SUM(CASE WHEN type IN ('earn','admin') THEN amount WHEN type='use' THEN -amount ELSE 0 END),0) as bal FROM gangnamunni_points WHERE member_id=?");
if ($bq) { mysqli_stmt_bind_param($bq,'i',$mb_no); mysqli_stmt_execute($bq); $r=mysqli_stmt_get_result($bq); $balance=(int)($r?mysqli_fetch_assoc($r)['bal']:0); mysqli_stmt_close($bq); }

// 내역 목록
$logs = [];
$lq = mysqli_prepare($connect, "SELECT * FROM gangnamunni_points WHERE member_id=? ORDER BY created_at DESC LIMIT 50");
if ($lq) { mysqli_stmt_bind_param($lq,'i',$mb_no); mysqli_stmt_execute($lq); $r=mysqli_stmt_get_result($lq); while($row=mysqli_fetch_assoc($r)) $logs[]=$row; mysqli_stmt_close($lq); }

if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
require_once __DIR__ . '/includes/global_nav.php';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>내 포인트 — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);padding-bottom:90px;}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.balance-box{background:linear-gradient(135deg,#AE1932,#d4354a);color:#fff;margin:16px;border-radius:14px;padding:24px 20px;text-align:center;}
.balance-box .label{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));opacity:0.9;margin-bottom:4px;}
.balance-box .amount{font-size:calc(32px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;letter-spacing:1px;}
.log-list{margin:0 16px;}
.log-item{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--bs-border-color,#eee);}
.log-reason{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;color:var(--bs-body-color);}
.log-date{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-top:2px;}
.log-amount{font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;white-space:nowrap;}
.log-amount.earn{color:#16a34a;}
.log-amount.use{color:#dc2626;}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color,#999);}
.empty i{font-size:calc(48px * var(--fs-scale,1) * var(--fs-page,1));display:block;margin-bottom:12px;opacity:0.5;}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <h1>내 포인트</h1>
</div>

<div class="balance-box">
    <div class="label"><?php echo $mb_nick; ?>님의 보유 포인트</div>
    <div class="amount"><?php echo number_format($balance); ?>P</div>
</div>

<?php if (empty($logs)): ?>
<div class="empty"><i class="bi bi-coin"></i><p>포인트 내역이 없습니다.</p></div>
<?php else: ?>
<div class="log-list">
<?php foreach ($logs as $log):
    $is_earn = in_array($log['type'], ['earn','admin']);
    $sign = $is_earn ? '+' : '-';
    $cls = $is_earn ? 'earn' : 'use';
?>
<div class="log-item">
    <div>
        <div class="log-reason"><?php echo htmlspecialchars($log['reason'] ?? ($is_earn ? '적립' : '사용')); ?></div>
        <div class="log-date"><?php echo date('Y.m.d H:i', strtotime($log['created_at'])); ?></div>
    </div>
    <div class="log-amount <?php echo $cls; ?>"><?php echo $sign . number_format($log['amount']); ?>P</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
<?php mysqli_close($connect); ?>

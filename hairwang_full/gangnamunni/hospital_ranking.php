<?php
require_once __DIR__ . '/includes/init.php';
$period = $_GET['period'] ?? 'all';
$sort = $_GET['sort'] ?? 'rating';

$order = 'h.rating DESC, h.review_count DESC';
if ($sort === 'reviews') $order = 'h.review_count DESC, h.rating DESC';
elseif ($sort === 'bookings') $order = 'booking_count DESC, h.rating DESC';

$hospitals = [];
$sql = "SELECT h.*, (SELECT COUNT(*) FROM gangnamunni_bookings WHERE hospital_id=h.id) as booking_count FROM gangnamunni_hospitals h WHERE h.is_active=1 ORDER BY $order LIMIT 50";
$res = mysqli_query($connect, $sql);
if ($res) while ($r = mysqli_fetch_assoc($res)) $hospitals[] = $r;
// mysqli_close moved to end
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>병원 랭킹 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.sort-tabs{display:flex;gap:0;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.sort-tab{flex:1;padding:12px;text-align:center;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:pointer;text-decoration:none;color:var(--bs-secondary-color,#888);border-bottom:2px solid transparent;}
.sort-tab.active{color:var(--pri);border-color:var(--pri);}
.rank-item{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);text-decoration:none;color:inherit;}
.rank-num{width:28px;text-align:center;font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;color:var(--bs-secondary-color,#ccc);}
.rank-num.top3{color:var(--pri);}
.rank-img{width:56px;height:56px;border-radius:12px;object-fit:cover;background:var(--bs-tertiary-bg,#eee);}
.rank-info{flex:1;min-width:0;}
.rank-name{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rank-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-top:2px;}
.rank-score{text-align:right;}
.rank-score .num{font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:var(--pri);}
.rank-score .label{font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1><i class="bi bi-trophy" style="color:#f59e0b;"></i> 병원 랭킹</h1></div>
<div class="sort-tabs">
    <a href="hospital_ranking.php?sort=rating" class="sort-tab <?php echo $sort==='rating'?'active':''; ?>">평점순</a>
    <a href="hospital_ranking.php?sort=reviews" class="sort-tab <?php echo $sort==='reviews'?'active':''; ?>">후기순</a>
    <a href="hospital_ranking.php?sort=bookings" class="sort-tab <?php echo $sort==='bookings'?'active':''; ?>">예약순</a>
</div>
<?php foreach ($hospitals as $i => $h): $rank = $i + 1; ?>
<a href="detail.php?id=<?php echo $h['id']; ?>" class="rank-item">
    <div class="rank-num <?php echo $rank<=3?'top3':''; ?>"><?php echo $rank; ?></div>
    <?php if ($h['main_image_url']): ?><img src="<?php echo htmlspecialchars($h['main_image_url']); ?>" class="rank-img" alt="">
    <?php else: ?><div class="rank-img" style="display:flex;align-items:center;justify-content:center;font-size:20px;color:#ccc;"><i class="bi bi-building"></i></div><?php endif; ?>
    <div class="rank-info">
        <div class="rank-name"><?php echo htmlspecialchars($h['name']); ?></div>
        <div class="rank-meta"><?php echo htmlspecialchars($h['address'] ?? ''); ?></div>
    </div>
    <div class="rank-score">
        <?php if ($sort==='reviews'): ?><div class="num"><?php echo number_format($h['review_count']); ?></div><div class="label">후기</div>
        <?php elseif ($sort==='bookings'): ?><div class="num"><?php echo number_format($h['booking_count']); ?></div><div class="label">예약</div>
        <?php else: ?><div class="num"><?php echo number_format($h['rating'],1); ?></div><div class="label">★ 평점</div><?php endif; ?>
    </div>
</a>
<?php endforeach; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
mysqli_close($connect);

<?php
require_once __DIR__ . '/includes/init.php';
$category = trim($_GET['category'] ?? '');

$categories = [];
$cat_res = mysqli_query($connect, "SELECT category, COUNT(*) as cnt, MIN(CASE WHEN discount_price>0 THEN discount_price ELSE price END) as min_price FROM gangnamunni_treatments WHERE is_active=1 GROUP BY category ORDER BY cnt DESC");
if ($cat_res) while ($r = mysqli_fetch_assoc($cat_res)) if (!empty($r['category'])) $categories[] = $r;

$treatments = [];
if ($category) {
    $esc = mysqli_real_escape_string($connect, $category);
    $res = mysqli_query($connect, "SELECT t.*, h.name as hospital_name, h.rating as hospital_rating FROM gangnamunni_treatments t LEFT JOIN gangnamunni_hospitals h ON t.hospital_id=h.id WHERE t.is_active=1 AND t.category='$esc' ORDER BY t.is_popular DESC, CASE WHEN t.discount_price>0 THEN t.discount_price ELSE t.price END ASC");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $treatments[] = $r;
}
// mysqli_close moved($connect);
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>시술 카테고리 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.cat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:16px;}
.cat-card{padding:20px 16px;background:var(--bs-body-bg,#fff);border-radius:14px;border:1px solid var(--bs-border-color,#eee);text-decoration:none;color:inherit;text-align:center;transition:all 0.2s;}
.cat-card:hover{border-color:var(--pri);transform:translateY(-2px);}
.cat-card .cat-name{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;margin-bottom:4px;}
.cat-card .cat-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
.treat-list{padding:12px 16px;}
.treat-card{background:var(--bs-body-bg,#fff);border-radius:12px;border:1px solid var(--bs-border-color,#eee);padding:14px 16px;margin-bottom:10px;}
.treat-name{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;margin-bottom:4px;}
.treat-hospital{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-bottom:6px;}
.treat-price{display:flex;align-items:center;gap:8px;}
.treat-price .original{text-decoration:line-through;color:var(--bs-secondary-color,#999);font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
.treat-price .sale{font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:var(--pri);}
.popular-badge{display:inline-block;padding:2px 8px;background:#fef3c7;color:#d97706;border-radius:4px;font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;margin-left:6px;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="<?php echo $category ? "location.href='treatments.php'" : "history.back()"; ?>"><i class="bi bi-chevron-left"></i></button>
<h1><?php echo $category ? htmlspecialchars($category) : '시술 카테고리'; ?></h1></div>

<?php if (!$category): ?>
<div class="cat-grid">
<?php foreach ($categories as $c): ?>
<a href="treatments.php?category=<?php echo urlencode($c['category']); ?>" class="cat-card">
    <div class="cat-name"><?php echo htmlspecialchars($c['category']); ?></div>
    <div class="cat-meta"><?php echo $c['cnt']; ?>개 시술 · <?php echo number_format($c['min_price']); ?>원~</div>
</a>
<?php endforeach; ?>
</div>
<?php else: ?>
<div style="padding:12px 16px;font-size:13px;color:var(--bs-secondary-color);"><?php echo count($treatments); ?>개 시술</div>
<div class="treat-list">
<?php foreach ($treatments as $t): ?>
<a href="detail.php?id=<?php echo $t['hospital_id']; ?>" class="treat-card" style="display:block;text-decoration:none;color:inherit;">
    <div class="treat-name"><?php echo htmlspecialchars($t['name']); ?><?php if ($t['is_popular']): ?><span class="popular-badge">인기</span><?php endif; ?></div>
    <div class="treat-hospital"><i class="bi bi-building"></i> <?php echo htmlspecialchars($t['hospital_name'] ?? ''); ?> <?php if ($t['hospital_rating']>0): ?><span style="color:#f59e0b;">★ <?php echo number_format($t['hospital_rating'],1); ?></span><?php endif; ?></div>
    <div class="treat-price">
        <?php if ($t['discount_price'] > 0 && $t['discount_price'] < $t['price']): ?>
        <span class="original"><?php echo number_format($t['price']); ?>원</span>
        <span class="sale"><?php echo number_format($t['discount_price']); ?>원</span>
        <?php else: ?>
        <span class="sale"><?php echo number_format($t['price']); ?>원</span>
        <?php endif; ?>
        <?php if ($t['duration_minutes']): ?><span style="font-size:11px;color:var(--bs-secondary-color);margin-left:auto;"><?php echo $t['duration_minutes']; ?>분</span><?php endif; ?>
    </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
<?php mysqli_close($connect); ?>

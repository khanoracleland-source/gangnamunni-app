<?php
require_once __DIR__ . '/includes/init.php';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$photos = [];
$total = 0;
$res = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews WHERE photo_urls IS NOT NULL AND photo_urls != '' AND is_active=1");
if ($res) $total = mysqli_fetch_assoc($res)['cnt'];

$res2 = mysqli_query($connect, "SELECT r.id, r.hospital_id, r.photo_urls, r.rating, r.user_name, h.name as hospital_name FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id=h.id WHERE r.photo_urls IS NOT NULL AND r.photo_urls != '' AND r.is_active=1 ORDER BY r.created_at DESC LIMIT $limit OFFSET $offset");
if ($res2) while ($row = mysqli_fetch_assoc($res2)) {
    $urls = json_decode($row['photo_urls'], true);
    if (!$urls && $row['photo_urls']) $urls = explode(',', $row['photo_urls']);
    if ($urls) foreach ($urls as $url) {
        $photos[] = ['url' => trim($url), 'review_id' => $row['id'], 'hospital_id' => $row['hospital_id'], 'hospital_name' => $row['hospital_name'], 'rating' => $row['rating'], 'user_name' => $row['user_name']];
    }
}
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>후기 사진 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.photo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2px;padding:2px;}
.photo-item{position:relative;aspect-ratio:1;overflow:hidden;cursor:pointer;}
.photo-item img{width:100%;height:100%;object-fit:cover;}
.photo-item .overlay{position:absolute;bottom:0;left:0;right:0;padding:4px 6px;background:linear-gradient(transparent,rgba(0,0,0,0.6));color:#fff;font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1));}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color,#888);}
.load-more{display:block;margin:16px auto;padding:12px 32px;border:1px solid var(--bs-border-color,#ddd);border-radius:8px;background:none;cursor:pointer;color:inherit;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1><i class="bi bi-images"></i> 후기 사진</h1><span style="font-size:13px;color:var(--bs-secondary-color);"><?php echo number_format($total); ?>개</span></div>
<?php if (empty($photos)): ?>
<div class="empty"><i class="bi bi-images" style="font-size:48px;display:block;margin-bottom:12px;"></i>등록된 후기 사진이 없습니다</div>
<?php else: ?>
<div class="photo-grid">
<?php foreach ($photos as $p): ?>
<a href="review_detail.php?id=<?php echo $p['review_id']; ?>" class="photo-item">
    <img src="<?php echo htmlspecialchars($p['url']); ?>" alt="" loading="lazy">
    <div class="overlay"><?php echo htmlspecialchars($p['hospital_name'] ?? ''); ?></div>
</a>
<?php endforeach; ?>
</div>
<?php if ($total > $page * $limit): ?>
<a href="review_photos.php?page=<?php echo $page+1; ?>" class="load-more">더보기</a>
<?php endif; ?>
<?php endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php if (isset($connect) && $connect) mysqli_close($connect); ?>
</body></html>

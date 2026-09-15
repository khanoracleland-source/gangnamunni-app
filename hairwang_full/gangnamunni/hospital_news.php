<?php
require_once __DIR__ . '/includes/init.php';
$hospital_id = intval($_GET['hospital_id'] ?? 0);

// 테이블
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_hospital_news (
    id INT AUTO_INCREMENT PRIMARY KEY, hospital_id INT NOT NULL,
    title VARCHAR(300) NOT NULL, content TEXT, image_url VARCHAR(500),
    news_type ENUM('news','promo','notice') DEFAULT 'news',
    is_active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hospital (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$news = [];
$where = "n.is_active=1";
if ($hospital_id > 0) $where .= " AND n.hospital_id=$hospital_id";
$res = mysqli_query($connect, "SELECT n.*, h.name as hospital_name FROM gangnamunni_hospital_news n LEFT JOIN gangnamunni_hospitals h ON n.hospital_id=h.id WHERE $where ORDER BY n.created_at DESC LIMIT 50");
if ($res) while ($r = mysqli_fetch_assoc($res)) $news[] = $r;

$hospital_name = '';
if ($hospital_id > 0) {
    $hr = mysqli_query($connect, "SELECT name FROM gangnamunni_hospitals WHERE id=$hospital_id");
    if ($hr) $hospital_name = mysqli_fetch_assoc($hr)['name'] ?? '';
}
// mysqli_close moved($connect);
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title><?php echo $hospital_name ? htmlspecialchars($hospital_name).' 소식' : '병원 소식'; ?> - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.news-card{background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);padding:16px;display:flex;gap:12px;}
.news-img{width:80px;height:80px;border-radius:10px;object-fit:cover;flex-shrink:0;background:var(--bs-tertiary-bg,#eee);}
.news-info{flex:1;min-width:0;}
.news-type{display:inline-block;padding:2px 8px;border-radius:4px;font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;margin-bottom:4px;}
.news-type.news{background:#dbeafe;color:#2563eb;}
.news-type.promo{background:#fce7f3;color:#db2777;}
.news-type.notice{background:#fef3c7;color:#d97706;}
.news-title{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.news-meta{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1><i class="bi bi-megaphone"></i> <?php echo $hospital_name ? htmlspecialchars($hospital_name).' 소식' : '병원 소식'; ?></h1></div>
<?php if (empty($news)): ?>
<div class="empty"><i class="bi bi-megaphone" style="font-size:48px;display:block;margin-bottom:12px;"></i>아직 등록된 소식이 없습니다</div>
<?php else: foreach ($news as $n): ?>
<div class="news-card">
    <?php if ($n['image_url']): ?><img src="<?php echo htmlspecialchars($n['image_url']); ?>" class="news-img" alt=""><?php endif; ?>
    <div class="news-info">
        <span class="news-type <?php echo $n['news_type']; ?>"><?php echo $n['news_type']==='promo'?'프로모션':($n['news_type']==='notice'?'공지':'소식'); ?></span>
        <div class="news-title"><?php echo htmlspecialchars($n['title']); ?></div>
        <div class="news-meta"><?php if (!$hospital_id): ?><?php echo htmlspecialchars($n['hospital_name'] ?? ''); ?> · <?php endif; ?><?php echo date('Y.m.d', strtotime($n['created_at'])); ?></div>
    </div>
</div>
<?php endforeach; endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
<?php mysqli_close($connect); ?>
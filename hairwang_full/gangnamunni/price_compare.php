<?php
/**
 * 시술 가격 비교 페이지
 * 같은 시술을 여러 병원에서 가격 비교
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$category = $_GET['category'] ?? '';
$treatment = $_GET['treatment'] ?? '';
$sort = $_GET['sort'] ?? 'price'; // price, rating, reviews

// 카테고리 목록
$categories = [];
$cat_res = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_treatments WHERE is_active=1 AND category != '' ORDER BY category");
if ($cat_res) {
    while ($r = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $r['category'];
    }
}

// 시술명 목록 (선택된 카테고리 기준)
$treatment_names = [];
if ($category) {
    $tn_stmt = mysqli_prepare($connect, "SELECT DISTINCT name FROM gangnamunni_treatments WHERE is_active=1 AND category=? ORDER BY name");
    if ($tn_stmt) {
        mysqli_stmt_bind_param($tn_stmt, 's', $category);
        mysqli_stmt_execute($tn_stmt);
        $tn_res = mysqli_stmt_get_result($tn_stmt);
        while ($r = mysqli_fetch_assoc($tn_res)) {
            $treatment_names[] = $r['name'];
        }
        mysqli_stmt_close($tn_stmt);
    }
}

// 가격 비교 결과
$comparisons = [];
if ($treatment || $category) {
    $order = 'price_display ASC';
    if ($sort === 'rating') $order = 'h.rating DESC';
    elseif ($sort === 'reviews') $order = 'h.review_count DESC';

    $where = "t.is_active=1";
    $params = [];
    $types = '';

    if ($treatment) {
        $where .= " AND t.name = ?";
        $params[] = $treatment;
        $types .= 's';
    } elseif ($category) {
        $where .= " AND t.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    $sql = "SELECT t.id, t.name as treatment_name, t.category, t.price, t.discount_price, t.duration_minutes,
                   h.id as hospital_id, h.name as hospital_name, h.rating, h.review_count, h.address, h.main_image_url,
                   COALESCE(NULLIF(t.discount_price, 0), t.price) as price_display
            FROM gangnamunni_treatments t
            JOIN gangnamunni_hospitals h ON t.hospital_id = h.id
            WHERE {$where}
            ORDER BY {$order}
            LIMIT 50";

    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt && $types) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $comparisons[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// 통계
$min_price = PHP_INT_MAX;
$max_price = 0;
$avg_price = 0;
$total = 0;
foreach ($comparisons as $c) {
    $p = $c['price_display'];
    if ($p > 0) {
        $min_price = min($min_price, $p);
        $max_price = max($max_price, $p);
        $avg_price += $p;
        $total++;
    }
}
if ($total > 0) $avg_price = round($avg_price / $total);
if ($min_price === PHP_INT_MAX) $min_price = 0;

// 주의: mysqli_close()는 bottom_nav.php 등 include 파일에서 DB를 사용하므로
// HTML 출력 및 모든 include가 끝난 후에 호출해야 함
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo term('service', '시술'); ?> 가격 비교 - <?php echo site_name(); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root { --primary: #AC1830; --bg: #f8f9fa; --card: #fff; --text: #1a1a1a; --muted: #888; --border: #eee; }
[data-bs-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e5e5e5; --muted: #999; --border: #444; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); max-width:640px; margin:0 auto; padding-bottom:80px; }

.page-header { padding:14px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--border); background:var(--card); position:sticky; top:0; z-index:10; }
.page-header .back-btn { width:36px; height:36px; border:none; background:none; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer; color:var(--text); }
.page-header h1 { font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; }

.filter-section { padding:12px 16px; background:var(--card); border-bottom:1px solid var(--border); }
.filter-row { display:flex; gap:8px; margin-bottom:8px; }
.filter-select { flex:1; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); background:var(--card); color:var(--text); }

.sort-tabs { display:flex; gap:6px; padding:8px 16px; }
.sort-tab { padding:6px 14px; border-radius:16px; border:1px solid var(--border); background:var(--card); color:var(--muted); font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer; text-decoration:none; }
.sort-tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }

.stats-bar { padding:12px 16px; background:linear-gradient(135deg, #AC183011, #AC183005); display:flex; justify-content:space-around; text-align:center; }
.stat-item .stat-label { font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); }
.stat-item .stat-value { font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:var(--primary); }

.compare-card { display:flex; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border); background:var(--card); }
.compare-card img { width:70px; height:70px; border-radius:10px; object-fit:cover; flex-shrink:0; }
.compare-card .no-img { width:70px; height:70px; border-radius:10px; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:calc(24px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); flex-shrink:0; }
.compare-info { flex:1; min-width:0; }
.compare-info .hospital-name { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; margin-bottom:2px; }
.compare-info .hospital-name a { color:var(--text); text-decoration:none; }
.compare-info .treatment-name { font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); margin-bottom:4px; }
.compare-info .price-row { display:flex; align-items:baseline; gap:6px; }
.compare-info .price { font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:var(--primary); }
.compare-info .original-price { font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); text-decoration:line-through; }
.compare-info .meta { font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); margin-top:4px; }
.compare-info .rating { color:#f59e0b; }

.empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
.empty-state i { font-size:calc(48px * var(--fs-scale,1) * var(--fs-page,1)); display:block; margin-bottom:12px; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
<?php $turbo_pilot = function_exists('sb_turbo_active') && sb_turbo_active(); // Turbo is injected by includes/turbo_boot.php ?>
</head>
<body>

<div class="page-header">
    <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <h1><i class="bi bi-tags"></i> <?php echo term('service', '시술'); ?> 가격 비교</h1>
</div>

<div class="filter-section">
    <form method="GET" action="price_compare.php"<?php echo $turbo_pilot ? ' data-turbo="true"' : ''; ?>>
    <div class="filter-row">
        <select name="category" class="filter-select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            <option value="">카테고리 선택</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($treatment_names): ?>
        <select name="treatment" class="filter-select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            <option value="">전체 <?php echo term('service', '시술'); ?></option>
            <?php foreach ($treatment_names as $tn): ?>
            <option value="<?php echo htmlspecialchars($tn); ?>" <?php echo $treatment === $tn ? 'selected' : ''; ?>><?php echo htmlspecialchars($tn); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
    </div>
    </form>
</div>

<?php if ($comparisons): ?>
<div class="sort-tabs"<?php echo $turbo_pilot ? ' data-turbo="true"' : ''; ?>>
    <a href="?category=<?php echo urlencode($category); ?>&treatment=<?php echo urlencode($treatment); ?>&sort=price" class="sort-tab <?php echo $sort==='price'?'active':''; ?>">가격순</a>
    <a href="?category=<?php echo urlencode($category); ?>&treatment=<?php echo urlencode($treatment); ?>&sort=rating" class="sort-tab <?php echo $sort==='rating'?'active':''; ?>">평점순</a>
    <a href="?category=<?php echo urlencode($category); ?>&treatment=<?php echo urlencode($treatment); ?>&sort=reviews" class="sort-tab <?php echo $sort==='reviews'?'active':''; ?>"><?php echo term('review', '후기'); ?>순</a>
</div>

<div class="stats-bar">
    <div class="stat-item"><div class="stat-label">최저가</div><div class="stat-value"><?php echo number_format($min_price); ?>원</div></div>
    <div class="stat-item"><div class="stat-label">평균가</div><div class="stat-value"><?php echo number_format($avg_price); ?>원</div></div>
    <div class="stat-item"><div class="stat-label">최고가</div><div class="stat-value"><?php echo number_format($max_price); ?>원</div></div>
    <div class="stat-item"><div class="stat-label"><?php echo term('business', '병원'); ?>수</div><div class="stat-value"><?php echo $total; ?>개</div></div>
</div>

<?php foreach ($comparisons as $idx => $c): ?>
<div class="compare-card">
    <?php if ($c['main_image_url']): ?>
    <a href="detail.php?id=<?php echo $c['hospital_id']; ?>"><img src="<?php echo htmlspecialchars($c['main_image_url']); ?>" alt=""></a>
    <?php else: ?>
    <a href="detail.php?id=<?php echo $c['hospital_id']; ?>"><div class="no-img"><i class="bi bi-building"></i></div></a>
    <?php endif; ?>
    <div class="compare-info">
        <div class="hospital-name"><a href="detail.php?id=<?php echo $c['hospital_id']; ?>"><?php echo htmlspecialchars($c['hospital_name']); ?></a></div>
        <div class="treatment-name"><?php echo htmlspecialchars($c['treatment_name']); ?> <?php if ($c['duration_minutes']): ?>(<?php echo $c['duration_minutes']; ?>분)<?php endif; ?></div>
        <div class="price-row">
            <span class="price"><?php echo number_format($c['price_display']); ?>원</span>
            <?php if ($c['discount_price'] > 0 && $c['price'] > $c['discount_price']): ?>
            <span class="original-price"><?php echo number_format($c['price']); ?>원</span>
            <?php endif; ?>
        </div>
        <div class="meta">
            <?php if ($c['rating'] > 0): ?>
            <span class="rating"><i class="bi bi-star-fill"></i> <?php echo number_format($c['rating'], 1); ?></span>
            <span>(<?php echo $c['review_count']; ?>)</span> ·
            <?php endif; ?>
            <?php echo htmlspecialchars(mb_substr($c['address'] ?? '', 0, 20)); ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php else: ?>

<?php if ($category || $treatment): ?>
<div class="empty-state">
    <i class="bi bi-tags"></i>
    <p>해당 조건의 <?php echo term('service', '시술'); ?>이 없습니다</p>
</div>
<?php else: ?>
<?php
// 공통 카테고리 선택 컴포넌트
$cat_select_target = 'price_compare.php';
$cat_select_title = term('service', '시술') . ' 카테고리';
$cat_select_show_popular = true;
$cat_select_turbo = $turbo_pilot;
include __DIR__ . '/includes/treatment_category_select.php';
?>
<?php endif; ?>

<?php endif; ?>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php if (isset($connect) && $connect) mysqli_close($connect); ?>
</body>
</html>

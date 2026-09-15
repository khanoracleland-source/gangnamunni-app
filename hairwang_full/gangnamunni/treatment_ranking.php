<?php
/**
 * 시술별 병원 랭킹 페이지 (프론트엔드)
 * - 시술 카테고리 탭 + 시술 선택 → 병원 랭킹 리스트
 * - 정렬: 점수순(기본), 가격순, 리뷰순
 * - URL: treatment_ranking.php?treatment_id=X or ?category_id=Y
 * - 앱 모드: ?app=1 → global_nav/bottom_nav 숨김
 *
 * @file    gangnamunni/treatment_ranking.php
 * @created 2026-03-25
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';

// 앱 모드 감지
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';

// 파라미터
$treatment_id = isset($_GET['treatment_id']) ? intval($_GET['treatment_id']) : 0;
$category_id  = isset($_GET['category_id'])  ? intval($_GET['category_id'])  : 0;
$sort          = isset($_GET['sort']) ? $_GET['sort'] : 'score';
$valid_sorts   = ['score', 'price', 'reviews'];
if (!in_array($sort, $valid_sorts)) $sort = 'score';

// ===== 카테고리 목록 =====
$categories = [];
$cat_q = mysqli_query($connect, "SELECT id, name, slug, icon FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
if ($cat_q) {
    while ($r = mysqli_fetch_assoc($cat_q)) {
        $categories[] = $r;
    }
}

// ===== 시술 정보 (선택된 경우) =====
$treatment = null;
if ($treatment_id > 0) {
    $stmt = $connect->prepare("SELECT tm.*, tc.name AS category_name, tc.icon AS category_icon
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.id = ? AND tm.is_active = 1");
    if ($stmt) {
        $stmt->bind_param("i", $treatment_id);
        $stmt->execute();
        $treatment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($treatment) {
        $category_id = intval($treatment['category_id']);
    }
}

// ===== 시술 목록 (카테고리별 또는 전체) =====
$treatments = [];
if ($category_id > 0) {
    $stmt = $connect->prepare("SELECT id, name, hashtag, avg_price_min, avg_price_max, popularity_score
        FROM gangnamunni_treatment_master WHERE category_id = ? AND is_active = 1
        ORDER BY popularity_score DESC, name ASC");
    if ($stmt) {
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $treatments[] = $r;
        $stmt->close();
    }
} elseif ($treatment_id == 0) {
    // 전체 인기 시술
    $res = mysqli_query($connect, "SELECT tm.id, tm.name, tm.hashtag, tm.avg_price_min, tm.avg_price_max, tm.popularity_score, tc.name AS category_name,
        (SELECT COUNT(DISTINCT ht.hospital_id) FROM gangnamunni_hospital_treatments ht WHERE ht.treatment_id = tm.id AND ht.is_active = 1 AND ht.status = 'active') AS hospital_count
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.is_active = 1
        ORDER BY tm.popularity_score DESC, tm.name ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $treatments[] = $r;
    }
}

// ===== 병원 랭킹 (시술 선택된 경우) =====
$rankings = [];
if ($treatment_id > 0) {
    // 정렬 조건
    $order_clause = "ts.score DESC, ht.price_min ASC";
    if ($sort === 'price')   $order_clause = "ht.price_min ASC, ts.score DESC";
    if ($sort === 'reviews') $order_clause = "COALESCE(ts.total_reviews, 0) DESC, ts.score DESC";

    $sql = "SELECT h.id AS hospital_id, h.name AS hospital_name, h.address, h.rating, h.main_image_url, h.profile_image_url,
                ht.price_min, ht.price_max, ht.is_promoted,
                COALESCE(ts.score, 0) AS treatment_score,
                COALESCE(ts.total_reviews, 0) AS treatment_reviews,
                COALESCE(ts.avg_sentiment, 0) AS avg_sentiment,
                (SELECT COUNT(*) FROM hospital_reviews hr WHERE hr.hospital_id = h.id AND hr.is_active = 1) AS total_hospital_reviews
            FROM gangnamunni_hospital_treatments ht
            INNER JOIN gangnamunni_hospitals h ON ht.hospital_id = h.id AND h.is_active = 1
            LEFT JOIN gangnamunni_treatment_scores ts ON ts.hospital_id = ht.hospital_id AND ts.treatment_id = ht.treatment_id
            WHERE ht.treatment_id = ? AND ht.is_active = 1 AND ht.status = 'active'
            ORDER BY {$order_clause}";

    $stmt = $connect->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $treatment_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rankings[] = $r;
        $stmt->close();
    }
}

// 페이지 제목
$page_title = term('service', '시술') . '별 ' . term('business', '병원') . ' 랭킹';
if ($treatment) {
    $page_title = htmlspecialchars($treatment['name']) . ' ' . term('business', '병원') . ' 랭킹';
}

// industry term
if (!function_exists('term')) {
    require_once __DIR__ . '/includes/industry_terms.php';
}
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title><?php echo $page_title; ?> - <?php echo function_exists('site_name') ? site_name() : '서울미녀'; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();
</script>
<style>
:root {
    --pink: #FF6B9D;
    --pink-light: #FFF0F5;
    --pink-dark: #E0557D;
    --gold: #FFD700;
    --silver: #C0C0C0;
    --bronze: #CD7F32;
}
[data-bs-theme="dark"] {
    --pink-light: #2D1520;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    max-width: 640px;
    margin: 0 auto;
    padding-bottom: 80px;
}

/* 상단 헤더 */
.page-header {
    position: sticky; top: 0; z-index: 50;
    background: var(--bs-body-bg);
    border-bottom: 1px solid var(--bs-border-color);
    padding: 12px 16px;
    display: flex; align-items: center; gap: 10px;
}
.page-header .back-btn {
    width: 36px; height: 36px; border: none; background: none;
    font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; color: var(--bs-body-color);
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
}
.page-header .back-btn:hover { background: var(--bs-tertiary-bg); }
.page-header h1 { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; flex: 1; }

/* 카테고리 탭 */
.category-tabs {
    display: flex; gap: 8px; padding: 12px 16px;
    overflow-x: auto; white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    border-bottom: 1px solid var(--bs-border-color);
}
.category-tabs::-webkit-scrollbar { display: none; }
.cat-tab {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 8px 16px; border-radius: 20px;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500;
    cursor: pointer; text-decoration: none;
    flex-shrink: 0; transition: all .2s;
}
.cat-tab:hover, .cat-tab.active {
    background: var(--pink);
    color: #fff;
    border-color: var(--pink);
}
.cat-tab .cat-icon { font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); }

/* 시술 선택 리스트 */
.treatment-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    padding: 16px;
}
.treatment-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    text-decoration: none;
    color: var(--bs-body-color);
    transition: all .2s;
}
.treatment-card:hover {
    border-color: var(--pink);
    box-shadow: 0 2px 12px rgba(255,107,157,.15);
    color: var(--bs-body-color);
}
.treatment-card.active {
    border-color: var(--pink);
    background: var(--pink-light);
}
.treatment-card .t-name {
    font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
    margin-bottom: 4px;
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.treatment-card .t-hashtag {
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--pink); margin-bottom: 8px;
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.treatment-card .t-meta {
    display: flex; justify-content: space-between; align-items: center;
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color);
}
.treatment-card .t-hospital-count {
    display: inline-flex; align-items: center; gap: 2px;
    color: var(--pink); font-weight: 600;
}

/* 시술 정보 카드 */
.treatment-info {
    margin: 16px; padding: 16px 20px;
    background: linear-gradient(135deg, var(--pink), var(--pink-dark));
    border-radius: 16px; color: #fff;
}
.treatment-info .ti-category {
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); opacity: .85;
    display: inline-flex; align-items: center; gap: 4px;
    margin-bottom: 6px;
}
.treatment-info .ti-name { font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 4px; }
.treatment-info .ti-hashtag { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); opacity: .9; margin-bottom: 10px; }
.treatment-info .ti-price {
    display: inline-block; padding: 4px 12px;
    background: rgba(255,255,255,.2); border-radius: 20px;
    font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
}

/* 정렬 바 */
.sort-bar {
    display: flex; gap: 8px; padding: 12px 16px;
    border-bottom: 1px solid var(--bs-border-color);
}
.sort-btn {
    padding: 6px 14px; border-radius: 16px;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer;
    transition: all .2s;
}
.sort-btn:hover, .sort-btn.active {
    background: var(--pink);
    color: #fff; border-color: var(--pink);
}

/* 랭킹 리스트 */
.ranking-list { padding: 0 16px; }
.ranking-item {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 0;
    border-bottom: 1px solid var(--bs-border-color);
}
.ranking-item:last-child { border-bottom: none; }

.rank-badge {
    width: 32px; height: 32px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700;
    border-radius: 50%;
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
}
.rank-badge.gold   { background: linear-gradient(135deg, #FFD700, #FFA500); color: #fff; }
.rank-badge.silver { background: linear-gradient(135deg, #E8E8E8, #A8A8A8); color: #fff; }
.rank-badge.bronze { background: linear-gradient(135deg, #CD7F32, #A0522D); color: #fff; }

.rank-hospital-img {
    width: 56px; height: 56px; flex-shrink: 0;
    border-radius: 12px; object-fit: cover;
    background: var(--bs-tertiary-bg);
}

.rank-info { flex: 1; min-width: 0; }
.rank-info .h-name {
    font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
    margin-bottom: 2px;
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.rank-info .h-address {
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color);
    margin-bottom: 4px;
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.rank-info .h-stats {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.rank-info .h-stats span {
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); display: inline-flex; align-items: center; gap: 2px;
}
.rank-info .score-badge {
    background: var(--pink-light); color: var(--pink);
    padding: 2px 8px; border-radius: 10px; font-weight: 600;
}
.rank-info .star-rating { color: #FFB800; }
.rank-info .price-tag { color: var(--bs-secondary-color); font-weight: 500; }
.rank-info .review-count { color: var(--bs-secondary-color); }

.rank-cta {
    flex-shrink: 0;
}
.rank-cta .btn-book {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 8px 14px; border-radius: 20px;
    background: var(--pink); color: #fff;
    border: none; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: background .2s;
}
.rank-cta .btn-book:hover { background: var(--pink-dark); color: #fff; }

/* 프로모션 뱃지 */
.promoted-badge {
    display: inline-flex; align-items: center; gap: 2px;
    padding: 2px 6px; border-radius: 4px;
    background: #FFF3CD; color: #856404;
    font-size: calc(10px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
}
[data-bs-theme="dark"] .promoted-badge {
    background: #332B00; color: #FFD700;
}

/* 빈 상태 */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--bs-secondary-color);
}
.empty-state .bi { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--pink); opacity: .5; margin-bottom: 12px; }
.empty-state p { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-top: 8px; }

/* 섹션 제목 */
.section-title {
    font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700;
    padding: 16px 16px 8px;
}

/* 반응형 */
@media (max-width: 375px) {
    .treatment-grid { grid-template-columns: 1fr; }
    .rank-hospital-img { width: 48px; height: 48px; }
    .rank-cta .btn-book { padding: 6px 10px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); }
}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<?php if (!$is_app_mode): ?>
<?php include __DIR__ . '/includes/global_nav.php'; ?>
<?php endif; ?>

<!-- 페이지 헤더 -->
<div class="page-header">
    <button class="back-btn" onclick="history.back()" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1><?php echo $page_title; ?></h1>
</div>

<!-- 카테고리 탭 -->
<div class="category-tabs" role="tablist" aria-label="<?php echo term('service', '시술'); ?> 카테고리">
    <a href="treatment_ranking.php<?php echo $is_app_mode ? '?app=1' : ''; ?>"
       class="cat-tab <?php echo ($category_id == 0 && $treatment_id == 0) ? 'active' : ''; ?>"
       role="tab" aria-selected="<?php echo ($category_id == 0 && $treatment_id == 0) ? 'true' : 'false'; ?>">
        <i class="bi bi-grid-3x3-gap cat-icon"></i> 전체
    </a>
    <?php foreach ($categories as $cat): ?>
    <a href="treatment_ranking.php?category_id=<?php echo $cat['id']; ?><?php echo $is_app_mode ? '&app=1' : ''; ?>"
       class="cat-tab <?php echo ($category_id == intval($cat['id'])) ? 'active' : ''; ?>"
       role="tab" aria-selected="<?php echo ($category_id == intval($cat['id'])) ? 'true' : 'false'; ?>">
        <?php if (!empty($cat['icon'])): ?><span class="cat-icon"><?php echo htmlspecialchars($cat['icon']); ?></span><?php endif; ?>
        <?php echo htmlspecialchars($cat['name']); ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($treatment_id > 0 && $treatment): ?>
<!-- ===== 시술 상세 + 병원 랭킹 모드 ===== -->

<!-- 시술 정보 카드 -->
<div class="treatment-info">
    <div class="ti-category">
        <?php if (!empty($treatment['category_icon'])): ?><?php echo htmlspecialchars($treatment['category_icon']); ?> <?php endif; ?>
        <?php echo htmlspecialchars($treatment['category_name'] ?? ''); ?>
    </div>
    <div class="ti-name"><?php echo htmlspecialchars($treatment['name']); ?></div>
    <?php if (!empty($treatment['hashtag'])): ?>
    <div class="ti-hashtag"><?php echo htmlspecialchars($treatment['hashtag']); ?></div>
    <?php endif; ?>
    <?php if ($treatment['avg_price_min'] > 0 || $treatment['avg_price_max'] > 0): ?>
    <div class="ti-price">
        <i class="bi bi-tag"></i>
        평균 <?php echo number_format($treatment['avg_price_min'] / 10000); ?>만원
        <?php if ($treatment['avg_price_max'] > $treatment['avg_price_min']): ?>
        ~ <?php echo number_format($treatment['avg_price_max'] / 10000); ?>만원
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 정렬 바 -->
<div class="sort-bar" role="toolbar" aria-label="정렬 옵션">
    <a href="?treatment_id=<?php echo $treatment_id; ?>&sort=score<?php echo $is_app_mode ? '&app=1' : ''; ?>"
       class="sort-btn <?php echo $sort === 'score' ? 'active' : ''; ?>"><i class="bi bi-trophy"></i> 점수순</a>
    <a href="?treatment_id=<?php echo $treatment_id; ?>&sort=price<?php echo $is_app_mode ? '&app=1' : ''; ?>"
       class="sort-btn <?php echo $sort === 'price' ? 'active' : ''; ?>"><i class="bi bi-cash"></i> 가격순</a>
    <a href="?treatment_id=<?php echo $treatment_id; ?>&sort=reviews<?php echo $is_app_mode ? '&app=1' : ''; ?>"
       class="sort-btn <?php echo $sort === 'reviews' ? 'active' : ''; ?>"><i class="bi bi-chat-dots"></i> 리뷰순</a>
</div>

<!-- 병원 랭킹 리스트 -->
<div class="section-title">
    <i class="bi bi-trophy" style="color:var(--pink)"></i>
    <?php echo htmlspecialchars($treatment['name']); ?> <?php echo term('business', '병원'); ?> 랭킹
    <span style="font-size:13px;font-weight:400;color:var(--bs-secondary-color);">(<?php echo count($rankings); ?>곳)</span>
</div>

<?php if (empty($rankings)): ?>
<div class="empty-state">
    <div><i class="bi bi-hospital"></i></div>
    <strong>아직 랭킹 데이터가 없습니다</strong>
    <p>이 <?php echo term('service', '시술'); ?>을 제공하는 <?php echo term('business', '병원'); ?>이 등록되면 랭킹이 표시됩니다.</p>
</div>
<?php else: ?>
<div class="ranking-list" role="list" aria-label="<?php echo term('business', '병원'); ?> 랭킹">
    <?php foreach ($rankings as $idx => $rk):
        $rank = $idx + 1;
        $badge_class = '';
        if ($rank === 1) $badge_class = 'gold';
        elseif ($rank === 2) $badge_class = 'silver';
        elseif ($rank === 3) $badge_class = 'bronze';

        $img = '';
        if (!empty($rk['profile_image_url'])) $img = $rk['profile_image_url'];
        elseif (!empty($rk['main_image_url'])) $img = $rk['main_image_url'];

        $price_min_man = $rk['price_min'] > 0 ? number_format($rk['price_min'] / 10000) : '0';
        $price_max_man = $rk['price_max'] > 0 ? number_format($rk['price_max'] / 10000) : '';
        $price_text = $price_min_man . '만원';
        if ($price_max_man && $price_max_man !== $price_min_man) $price_text = $price_min_man . '~' . $price_max_man . '만원';

        $score_display = number_format(floatval($rk['treatment_score']), 1);
        $rating_display = number_format(floatval($rk['rating']), 1);
    ?>
    <div class="ranking-item" role="listitem">
        <div class="rank-badge <?php echo $badge_class; ?>" aria-label="<?php echo $rank; ?>위">
            <?php if ($rank <= 3): ?>
                <i class="bi bi-trophy-fill"></i>
            <?php else: ?>
                <?php echo $rank; ?>
            <?php endif; ?>
        </div>

        <?php if ($img): ?>
        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($rk['hospital_name']); ?>" class="rank-hospital-img" loading="lazy">
        <?php else: ?>
        <div class="rank-hospital-img d-flex align-items-center justify-content-center" style="font-size:24px;color:var(--bs-secondary-color);"><i class="bi bi-hospital"></i></div>
        <?php endif; ?>

        <div class="rank-info">
            <div class="h-name">
                <a href="detail.php?id=<?php echo $rk['hospital_id']; ?><?php echo $is_app_mode ? '&app=1' : ''; ?>" style="color:inherit;text-decoration:none;">
                    <?php echo htmlspecialchars($rk['hospital_name']); ?>
                </a>
                <?php if ($rk['is_promoted']): ?>
                <span class="promoted-badge"><i class="bi bi-megaphone-fill"></i> AD</span>
                <?php endif; ?>
            </div>
            <div class="h-address"><?php echo htmlspecialchars($rk['address'] ?? ''); ?></div>
            <div class="h-stats">
                <span class="score-badge"><i class="bi bi-graph-up-arrow"></i> <?php echo $score_display; ?>점</span>
                <?php if (floatval($rk['rating']) > 0): ?>
                <span class="star-rating"><i class="bi bi-star-fill"></i> <?php echo $rating_display; ?></span>
                <?php endif; ?>
                <span class="price-tag"><i class="bi bi-tag"></i> <?php echo $price_text; ?></span>
                <span class="review-count"><i class="bi bi-chat-left-text"></i> 리뷰 <?php echo intval($rk['treatment_reviews']); ?></span>
            </div>
        </div>

        <div class="rank-cta">
            <a href="booking.php?id=<?php echo $rk['hospital_id']; ?><?php echo $is_app_mode ? '&app=1' : ''; ?>" class="btn-book" aria-label="<?php echo htmlspecialchars($rk['hospital_name']); ?> <?php echo term('booking', '예약'); ?>하기">
                <i class="bi bi-calendar-check"></i> <?php echo term('booking', '예약'); ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($category_id > 0 && empty($treatment)): ?>
<!-- ===== 카테고리 선택 → 시술 목록 ===== -->

<?php
    // 카테고리 이름
    $cat_name = '';
    foreach ($categories as $c) { if (intval($c['id']) === $category_id) { $cat_name = $c['name']; break; } }
?>
<div class="section-title"><?php echo htmlspecialchars($cat_name); ?> <?php echo term('service', '시술'); ?> 목록</div>

<?php if (empty($treatments)): ?>
<div class="empty-state">
    <div><i class="bi bi-clipboard2-pulse"></i></div>
    <strong>등록된 <?php echo term('service', '시술'); ?>이 없습니다</strong>
</div>
<?php else: ?>
<div class="treatment-grid">
    <?php foreach ($treatments as $t): ?>
    <a href="treatment_ranking.php?treatment_id=<?php echo $t['id']; ?><?php echo $is_app_mode ? '&app=1' : ''; ?>" class="treatment-card">
        <div class="t-name"><?php echo htmlspecialchars($t['name']); ?></div>
        <?php if (!empty($t['hashtag'])): ?>
        <div class="t-hashtag"><?php echo htmlspecialchars($t['hashtag']); ?></div>
        <?php endif; ?>
        <div class="t-meta">
            <?php if ($t['avg_price_min'] > 0): ?>
            <span><?php echo number_format($t['avg_price_min'] / 10000); ?>만원~</span>
            <?php else: ?>
            <span>가격 미정</span>
            <?php endif; ?>
            <span style="color:var(--pink);font-weight:600;">
                <i class="bi bi-chevron-right"></i>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ===== 기본 뷰: 인기 시술 그리드 ===== -->

<div class="section-title"><i class="bi bi-fire" style="color:var(--pink)"></i> 인기 <?php echo term('service', '시술'); ?></div>

<?php if (empty($treatments)): ?>
<div class="empty-state">
    <div><i class="bi bi-clipboard2-pulse"></i></div>
    <strong>등록된 <?php echo term('service', '시술'); ?>이 없습니다</strong>
    <p><?php echo term('service', '시술'); ?> 데이터가 등록되면 랭킹이 표시됩니다.</p>
</div>
<?php else: ?>
<div class="treatment-grid">
    <?php foreach ($treatments as $t): ?>
    <a href="treatment_ranking.php?treatment_id=<?php echo $t['id']; ?><?php echo $is_app_mode ? '&app=1' : ''; ?>" class="treatment-card">
        <div class="t-name"><?php echo htmlspecialchars($t['name']); ?></div>
        <?php if (!empty($t['hashtag'])): ?>
        <div class="t-hashtag"><?php echo htmlspecialchars($t['hashtag']); ?></div>
        <?php endif; ?>
        <div class="t-meta">
            <?php if (!empty($t['category_name'])): ?>
            <span><?php echo htmlspecialchars($t['category_name']); ?></span>
            <?php else: ?>
            <span>&nbsp;</span>
            <?php endif; ?>
            <span class="t-hospital-count">
                <i class="bi bi-hospital"></i> <?php echo intval($t['hospital_count'] ?? 0); ?>곳
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php if (!$is_app_mode): ?>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
<?php endif; ?>

<?php if (isset($connect) && $connect) mysqli_close($connect); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

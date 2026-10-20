<?php
/**
 * ========================================
 * 서울미녀 스타일 이벤트 페이지
 * ========================================
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';

// 카테고리 필터
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);

// 응모 필터
$filter_raffle = isset($_GET['filter']) && $_GET['filter'] === 'raffle';

// 이벤트 데이터 가져오기
$events = [];
$where = "e.is_active = 1";
if (!empty($category)) {
    $where .= " AND e.category = '{$escaped_category}'";
}
if ($filter_raffle) {
    $where .= " AND e.has_raffle = 1";
}
$query = "SELECT e.*, h.name as hospital_name
    FROM gangnamunni_events e
    LEFT JOIN gangnamunni_hospitals h ON e.hospital_id = h.id
    WHERE {$where}
    ORDER BY e.display_order ASC, e.created_at DESC LIMIT 50";

$result = mysqli_query($connect, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
}

// 카테고리 목록
$categories_query = "SELECT DISTINCT category FROM gangnamunni_events WHERE is_active = 1 ORDER BY category";
$categories_result = mysqli_query($connect, $categories_query);
$categories = [];
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
}

// 헤더 설정
$page_title = term('event');
$show_search = false;

$page_css = '
/* 페이지 헤더 (글로벌 네비 아래) */
.page-header {
    padding: 12px 20px;
}

.page-header-content {
    max-width: 640px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-btn {
    font-size: 24px;
    cursor: pointer;
    text-decoration: none;
    color: #000;
}

.page-title {
    font-size: 18px;
    font-weight: bold;
}

/* 필터 탭 */
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}
.filter-tab {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    color: #666;
    background: #f5f5f5;
    border: none;
    cursor: pointer;
}
.filter-tab.active {
    background: #AC1830;
    color: white;
}
.filter-tab.raffle.active {
    background: #AC1830;
}

/* 카테고리 필터 */
.category-filter {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    margin-bottom: 20px;
    padding-bottom: 10px;
}

.filter-btn {
    padding: 10px 20px;
    border: 1px solid #F5F5F5;
    border-radius: 20px;
    background: white;
    font-size: 14px;
    white-space: nowrap;
    cursor: pointer;
    text-decoration: none;
    color: #000000;
}

.filter-btn.active {
    background: #AC1830;
    color: white;
    border-color: #AC1830;
}

/* 이벤트 그리드 */
.events-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 40px;
}

.event-card {
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
    display: block;
}

.event-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.event-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #FF6B35, #FFA45B);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: white;
    overflow: hidden;
}
.event-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.event-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #AC1830;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}
.raffle-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #AC1830;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.event-info {
    padding: 16px;
}

.event-category {
    font-size: 12px;
    color: #AC1830;
    font-weight: 600;
    margin-bottom: 8px;
}

.event-title {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #000000;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.event-subtitle {
    font-size: 13px;
    color: #666666;
    margin-bottom: 12px;
}
.event-hospital {
    font-size: 12px;
    color: #999;
    margin-bottom: 8px;
}

.price-section {
    display: flex;
    align-items: center;
    gap: 8px;
}

.original-price {
    font-size: 14px;
    color: #999999;
    text-decoration: line-through;
}

.discount-price {
    font-size: 18px;
    font-weight: bold;
    color: #AC1830;
}

.discount-rate {
    font-size: 14px;
    font-weight: bold;
    color: #AC1830;
}
.event-date {
    font-size: 11px;
    color: #bbb;
    margin-top: 6px;
}

@media (max-width: 768px) {
    .events-grid {
        grid-template-columns: 1fr;
    }
}
';

// 페이지 JS
$page_js = '
// 네비게이션 아이템 클릭
document.querySelectorAll(".nav-item").forEach(function(item) {
    item.addEventListener("click", function() {
        var page = this.getAttribute("data-page");
        if (page && page !== "#") {
            location.href = page;
        }
    });
});
';

// 헤더 include
include __DIR__ . '/includes/header.php';
?>
<?php $is_admin_user = isset($_SESSION["ad_admin_logged_in"]) && $_SESSION["ad_admin_logged_in"] === true; ?>
    <!-- 광고 배너 -->
    <?php $ad_page_key = 'events'; include __DIR__ . '/includes/ad_banner.php'; ?>
<?php if ($is_admin_user && !$is_seoul_beauty_app): ?>
<div style="padding:8px 16px;text-align:right;">
    <a href="admin_events.php?action=new" class="btn btn-sm btn-primary" style="text-decoration:none;">+ 새 이벤트 추가</a>
</div>
<?php endif; ?>


    <!-- 페이지 헤더 -->
    <div class="page-header">
        <div class="page-header-content">
            <a href="index.php" class="back-btn">&#8592;</a>
            <div class="page-title">특별 이벤트</div>
        </div>
    </div>

    <!-- 필터 탭 -->
    <div class="filter-tabs">
        <a href="events.php" class="filter-tab <?php echo !$filter_raffle ? 'active' : ''; ?>">전체 이벤트</a>
        <a href="events.php?filter=raffle" class="filter-tab raffle <?php echo $filter_raffle ? 'active' : ''; ?>">🎰 응모 이벤트</a>
    </div>

    <!-- 카테고리 필터 -->
    <div class="category-filter">
        <a href="events.php<?php echo $filter_raffle ? '?filter=raffle' : ''; ?>" class="filter-btn <?php echo empty($category) ? 'active' : ''; ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
        <a href="events.php?category=<?php echo urlencode($cat); ?><?php echo $filter_raffle ? '&filter=raffle' : ''; ?>"
           class="filter-btn <?php echo $category === $cat ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cat); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 이벤트 그리드 -->
    <div class="events-grid">
        <?php foreach ($events as $event): ?>
        <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="event-card">
            <?php if (!empty($event['discount_rate']) && $event['discount_rate'] > 0): ?>
            <div class="event-badge"><?php echo intval($event['discount_rate']); ?>% 할인</div>
            <?php endif; ?>
            <?php if (!empty($event['has_raffle']) && $event['has_raffle']): ?>
            <div class="raffle-badge">🎰 응모</div>
            <?php endif; ?>

            <div class="event-image">
                <?php if (!empty($event['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($event['image_url']); ?>"
                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                     onerror="this.style.display='none'; this.parentNode.textContent='🎁';">
                <?php else: ?>
                🎁
                <?php endif; ?>
            </div>

            <div class="event-info">
                <?php if (!empty($event['category'])): ?>
                <div class="event-category"><?php echo htmlspecialchars($event['category']); ?></div>
                <?php endif; ?>

                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>

                <?php if (!empty($event['hospital_name'])): ?>
                <div class="event-hospital"><?php echo htmlspecialchars($event['hospital_name']); ?></div>
                <?php endif; ?>

                <?php if (!empty($event['subtitle'])): ?>
                <div class="event-subtitle"><?php echo htmlspecialchars($event['subtitle']); ?></div>
                <?php endif; ?>

                <?php if (!empty($event['discount_price']) && !empty($event['original_price'])): ?>
                <div class="price-section">
                    <span class="original-price"><?php echo number_format($event['original_price']); ?>원</span>
                    <span class="discount-price"><?php echo number_format($event['discount_price']); ?>원</span>
                </div>
                <?php endif; ?>

                <?php if (!empty($event['end_date'])): ?>
                <div class="event-date">~<?php echo date('Y.m.d', strtotime($event['end_date'])); ?></div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($events)): ?>
    <div style="text-align: center; padding: 60px 20px; color: #999;">
        <div style="font-size: 48px; margin-bottom: 20px;">🎁</div>
        <?php if ($filter_raffle): ?>
        <p>진행 중인 응모 이벤트가 없습니다.</p>
        <?php else: ?>
        <p>진행 중인 이벤트가 없습니다.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php
// 푸터 include
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
?>

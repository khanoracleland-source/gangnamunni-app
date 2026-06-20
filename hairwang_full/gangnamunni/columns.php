<?php
/**
 * 전문가 칼럼 + 의학뉴스 페이지
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/settings_helper.php';

$is_admin_user = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

// 카테고리 필터
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);

// 외부 의학뉴스
$news = [];
$news_query = "SELECT * FROM gangnamunni_columns WHERE is_external = 1 AND is_active = 1 ORDER BY published_at DESC LIMIT 6";
$news_result = mysqli_query($connect, $news_query);
if ($news_result) { while ($row = mysqli_fetch_assoc($news_result)) { $news[] = $row; } }

// 전문가 칼럼
$columns = [];
$col_query = "SELECT * FROM gangnamunni_columns WHERE is_external = 0 AND is_active = 1";
if (!empty($category)) {
    $col_query .= " AND category = '{$escaped_category}'";
}
$col_query .= " ORDER BY published_at DESC LIMIT 50";
$col_result = mysqli_query($connect, $col_query);
if ($col_result) { while ($row = mysqli_fetch_assoc($col_result)) { $columns[] = $row; } }

// 기존 팁 데이터도 함께 표시
$tips = [];
$tips_query = "SELECT * FROM gangnamunni_tips";
if (!empty($category)) {
    $tips_query .= " WHERE category = '{$escaped_category}'";
}
$tips_query .= " ORDER BY created_at DESC LIMIT 20";
$tips_result = mysqli_query($connect, $tips_query);
if ($tips_result) { while ($row = mysqli_fetch_assoc($tips_result)) { $tips[] = $row; } }

// 카테고리 목록
$categories = [];
$cat_result = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_columns WHERE is_active = 1 ORDER BY category");
if ($cat_result) { while ($row = mysqli_fetch_assoc($cat_result)) { $categories[] = $row['category']; } }
$cat2_result = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_tips ORDER BY category");
if ($cat2_result) { while ($row = mysqli_fetch_assoc($cat2_result)) { if (!in_array($row['category'], $categories)) $categories[] = $row['category']; } }

$page_title = '칼럼';
$show_search = false;

$page_css = '
.page-header { padding: 12px 20px; }
.page-header-content { max-width: 640px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
.back-btn { font-size: 24px; text-decoration: none; color: #000; }
.filter-tabs { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; white-space: nowrap; }
.filter-tab { padding: 6px 16px; border: 1px solid #e0e0e0; border-radius: 20px; background: #fff; color: #666; font-size: 13px; cursor: pointer; text-decoration: none; }
.filter-tab.active { background: #AE1932; color: #fff; border-color: #AE1932; }
.news-section { padding: 0 16px 16px; }
.news-title { font-size: 16px; font-weight: bold; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
.news-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.news-card { background: #f8f9fa; border-radius: 10px; padding: 14px; text-decoration: none; color: #333; transition: all 0.2s; }
.news-card:hover { background: #eef; transform: translateY(-2px); }
.news-source { font-size: 11px; color: #999; margin-bottom: 4px; }
.news-card-title { font-size: 13px; font-weight: 600; line-height: 1.4; }
.column-section { padding: 0 16px; }
.column-card { display: flex; gap: 14px; padding: 16px 0; border-bottom: 1px solid #f0f0f0; position: relative; }
.column-thumb { width: 100px; height: 80px; border-radius: 8px; object-fit: cover; background: #f0f0f0; flex-shrink: 0; }
.column-info { flex: 1; }
.column-title { font-size: 14px; font-weight: 600; margin-bottom: 6px; line-height: 1.4; }
.column-meta { font-size: 12px; color: #999; }
.admin-float-btn { position: absolute; top: 12px; right: 0; background: rgba(0,0,0,0.7); color: #fff; border: none; border-radius: 4px; padding: 3px 10px; font-size: 11px; cursor: pointer; text-decoration: none; }
.admin-add-bar { padding: 8px 16px; text-align: right; }
.tip-card { background: #fff; border-radius: 10px; padding: 16px; border: 1px solid #f0f0f0; margin-bottom: 12px; }
.tip-title { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
.tip-desc { font-size: 13px; color: #666; line-height: 1.5; }
@media (max-width: 480px) { .news-grid { grid-template-columns: 1fr; } }
';

$page_js = '';

include __DIR__ . '/includes/header.php';
?>

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <div class="page-header-content">
            <a href="index.php" class="back-btn">&#8592;</a>
            <h2 style="font-size:18px;margin:0;">칼럼 &amp; 의학뉴스</h2>
        </div>
    </div>

    <!-- 광고 배너 -->
    <?php $ad_page_key = 'columns'; include __DIR__ . '/includes/ad_banner.php'; ?>

    <?php if ($is_admin_user && !$is_seoul_beauty_app): ?>
    <div class="admin-add-bar">
        <a href="admin_columns.php?action=new" class="btn btn-sm btn-primary" style="text-decoration:none;">+ 새 칼럼 작성</a>
    </div>
    <?php endif; ?>

    <!-- 카테고리 필터 -->
    <div class="filter-tabs">
        <a href="columns.php" class="filter-tab <?php echo empty($category) ? 'active' : ''; ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
        <a href="columns.php?category=<?php echo urlencode($cat); ?>" class="filter-tab <?php echo $category === $cat ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat); ?></a>
        <?php endforeach; ?>
    </div>

    <!-- 의학뉴스 섹션 -->
    <?php if (!empty($news) && empty($category)): ?>
    <div class="news-section">
        <div class="news-title">
            <span>의학뉴스</span>
        </div>
        <div class="news-grid">
            <?php foreach ($news as $n): ?>
            <a href="<?php echo !empty($n['source_url']) ? htmlspecialchars($n['source_url']) : '#'; ?>" class="news-card" target="_blank" rel="noopener">
                <div class="news-source"><?php echo htmlspecialchars($n['source_name'] ?? ''); ?></div>
                <div class="news-card-title"><?php echo htmlspecialchars($n['title']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 전문가 칼럼 -->
    <?php if (!empty($columns)): ?>
    <div class="column-section">
        <h3 style="font-size:16px;font-weight:bold;margin:16px 0 12px;">전문가 칼럼</h3>
        <?php foreach ($columns as $col): ?>
        <div class="column-card">
            <?php if (!empty($col['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($col['image_url']); ?>" class="column-thumb" alt="">
            <?php else: ?>
            <div class="column-thumb" style="display:flex;align-items:center;justify-content:center;font-size:24px;">&#128221;</div>
            <?php endif; ?>
            <div class="column-info">
                <div class="column-title"><?php echo htmlspecialchars($col['title']); ?></div>
                <div class="column-meta">
                    <?php echo htmlspecialchars($col['author'] ?? ''); ?>
                    <?php if (!empty($col['source_name'])): ?> · <?php echo htmlspecialchars($col['source_name']); ?><?php endif; ?>
                    <?php if (!empty($col['published_at'])): ?> · <?php echo date('Y.m.d', strtotime($col['published_at'])); ?><?php endif; ?>
                </div>
            </div>
            <?php if ($is_admin_user && !$is_seoul_beauty_app): ?>
            <a href="admin_columns.php?id=<?php echo $col['id']; ?>" class="admin-float-btn">편집</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 기존 꿀팁 콘텐츠 -->
    <?php if (!empty($tips)): ?>
    <div class="column-section" style="margin-top:20px;">
        <h3 style="font-size:16px;font-weight:bold;margin:16px 0 12px;">꿀팁</h3>
        <?php foreach ($tips as $tip): ?>
        <div class="tip-card">
            <div class="tip-title"><?php echo htmlspecialchars($tip['title'] ?? ''); ?></div>
            <div class="tip-desc"><?php echo htmlspecialchars(mb_substr($tip['content'] ?? '', 0, 100)); ?>...</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($columns) && empty($tips) && empty($news)): ?>
    <div style="text-align:center;padding:60px 20px;color:#999;">
        <div style="font-size:48px;margin-bottom:16px;">&#128221;</div>
        <div style="font-size:16px;">아직 등록된 칼럼이 없습니다.</div>
    </div>
    <?php endif; ?>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
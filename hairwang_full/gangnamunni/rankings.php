<?php
/**
 * 병원 랭킹 (공개 프론트엔드)
 * - gangnamunni_rankings + gangnamunni_hospitals 테이블 기반
 * - 정렬: 평점순, 후기순, 예약순, 종합점수순
 * - 카테고리/기간 필터
 * - 모바일 퍼스트 카드 레이아웃 (max-width 640px)
 * - Bootstrap 5.3 + 다크모드 지원
 *
 * @created 2026-03-24
 */

// 초기화 (세션 + DB 연결)
$init_loaded = false;
if (file_exists(__DIR__ . '/includes/init.php')) {
    require_once __DIR__ . '/includes/init.php';
    require_once __DIR__ . '/includes/settings_helper.php';
    $init_loaded = true;
}

// init.php가 없거나 $connect가 없으면 직접 DB 연결
if (!isset($connect) || !$connect) {
    if (file_exists(__DIR__ . '/../dbconfig.php')) {
        require_once __DIR__ . '/../dbconfig.php';
        $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
    }
    if (!$connect) {
        http_response_code(500);
        echo '<p>서버 오류가 발생했습니다.</p>';
        exit;
    }
    mysqli_set_charset($connect, 'utf8mb4');
}

$site_name = '서울미녀';

// ===== 테이블 존재 확인 =====
$rankings_table_exists = false;
$tbl_check = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_rankings'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $rankings_table_exists = true;
}

// ===== 카테고리 목록 (필터 탭용) =====
$categories = [];
if ($rankings_table_exists) {
    $cat_r = @mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_rankings ORDER BY category ASC");
    if ($cat_r) {
        while ($c = mysqli_fetch_assoc($cat_r)) {
            if (!empty($c['category']) && $c['category'] !== '전체') {
                $categories[] = $c['category'];
            }
        }
    }
}
// gangnamunni_categories에서도 가져옴
$cat_r2 = @mysqli_query($connect, "SELECT DISTINCT name FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
if ($cat_r2) {
    while ($c2 = mysqli_fetch_assoc($cat_r2)) {
        if (!empty($c2['name']) && !in_array($c2['name'], $categories)) {
            $categories[] = $c2['name'];
        }
    }
}
if (empty($categories)) {
    $categories = ['눈성형', '코성형', '리프팅', '피부관리', '보톡스/필러', '가슴성형', '지방흡입', '치아', '탈모'];
}

// ===== 필터 / 정렬 파라미터 =====
$filter_cat = trim($_GET['cat'] ?? '');
$sort = trim($_GET['sort'] ?? 'score');
$filter_period = trim($_GET['period'] ?? 'monthly');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 정렬 옵션
$sort_options = [
    'score'   => ['label' => '종합점수순', 'sql' => 'r.rank_score DESC, r.review_count DESC'],
    'rating'  => ['label' => '평점순',     'sql' => 'r.avg_rating DESC, r.rank_score DESC'],
    'review'  => ['label' => '후기순',     'sql' => 'r.review_count DESC, r.rank_score DESC'],
    'booking' => ['label' => '예약순',     'sql' => 'r.booking_count DESC, r.rank_score DESC'],
];
if (!isset($sort_options[$sort])) {
    $sort = 'score';
}
$order_sql = $sort_options[$sort]['sql'];

// 기간 옵션
$period_options = [
    'weekly'   => '주간',
    'monthly'  => '월간',
    'all-time' => '전체기간',
];
if (!isset($period_options[$filter_period])) {
    $filter_period = 'monthly';
}

// ===== 랭킹 조회 =====
$rankings = [];
$total_count = 0;
$total_pages = 1;

if ($rankings_table_exists) {
    // WHERE 조건
    $where_parts = ['1=1'];
    $bind_types = '';
    $bind_values = [];

    // 기간 필터
    $where_parts[] = 'r.period = ?';
    $bind_types .= 's';
    $bind_values[] = $filter_period;

    // 최신 날짜만 가져오기 (해당 기간의 가장 최근 rank_date)
    $latest_date_sql = "SELECT MAX(rank_date) FROM gangnamunni_rankings WHERE period = ?";
    $ld_stmt = @mysqli_prepare($connect, $latest_date_sql);
    $latest_date = null;
    if ($ld_stmt) {
        mysqli_stmt_bind_param($ld_stmt, 's', $filter_period);
        mysqli_stmt_execute($ld_stmt);
        $ld_result = mysqli_stmt_get_result($ld_stmt);
        if ($ld_result) {
            $ld_row = mysqli_fetch_row($ld_result);
            $latest_date = $ld_row[0] ?? null;
        }
        mysqli_stmt_close($ld_stmt);
    }

    if ($latest_date) {
        $where_parts[] = 'r.rank_date = ?';
        $bind_types .= 's';
        $bind_values[] = $latest_date;
    }

    // 카테고리 필터
    if ($filter_cat !== '') {
        $where_parts[] = 'r.category = ?';
        $bind_types .= 's';
        $bind_values[] = $filter_cat;
    }

    $where_sql = implode(' AND ', $where_parts);

    // 전체 건수
    $count_sql = "SELECT COUNT(*) AS cnt
                  FROM gangnamunni_rankings r
                  LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
                  WHERE {$where_sql}";
    $count_stmt = @mysqli_prepare($connect, $count_sql);
    if ($count_stmt) {
        if (!empty($bind_types)) {
            mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_values);
        }
        mysqli_stmt_execute($count_stmt);
        $cnt_result = mysqli_stmt_get_result($count_stmt);
        if ($cnt_result) {
            $total_count = intval(mysqli_fetch_assoc($cnt_result)['cnt']);
        }
        mysqli_stmt_close($count_stmt);
    }
    $total_pages = max(1, ceil($total_count / $per_page));

    // 랭킹 목록
    $list_sql = "SELECT r.*, h.name AS hospital_name, h.address, h.phone, h.categories, h.image_url,
                        h.is_advertiser
                 FROM gangnamunni_rankings r
                 LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
                 WHERE {$where_sql}
                 ORDER BY {$order_sql}
                 LIMIT ? OFFSET ?";

    // bind_types에 limit, offset 추가
    $list_bind_types = $bind_types . 'ii';
    $list_bind_values = array_merge($bind_values, [$per_page, $offset]);

    $list_stmt = @mysqli_prepare($connect, $list_sql);
    if ($list_stmt) {
        mysqli_stmt_bind_param($list_stmt, $list_bind_types, ...$list_bind_values);
        mysqli_stmt_execute($list_stmt);
        $list_result = mysqli_stmt_get_result($list_stmt);
        if ($list_result) {
            while ($row = mysqli_fetch_assoc($list_result)) {
                $rankings[] = $row;
            }
        }
        mysqli_stmt_close($list_stmt);
    }
}

// ===== URL 빌더 헬퍼 =====
function build_ranking_url($params = []) {
    $base = 'rankings.php';
    $query = [];
    if (!empty($params['cat'])) $query['cat'] = $params['cat'];
    if (!empty($params['sort']) && $params['sort'] !== 'score') $query['sort'] = $params['sort'];
    if (!empty($params['period']) && $params['period'] !== 'monthly') $query['period'] = $params['period'];
    if (!empty($params['page']) && $params['page'] > 1) $query['page'] = $params['page'];
    return $base . (!empty($query) ? '?' . http_build_query($query) : '');
}

$current_params = [
    'cat'    => $filter_cat,
    'sort'   => $sort,
    'period' => $filter_period,
];

// 글로벌 네비게이션 (존재하면 포함)
$has_global_nav = file_exists(__DIR__ . '/includes/global_nav.php');
$has_bottom_nav = file_exists(__DIR__ . '/includes/bottom_navigation.php');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>병원 랭킹 | <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="서울미녀 병원 랭킹 - 평점, 후기, 예약 수 기반 병원 순위를 확인하세요.">

    <!-- anti-flash (다크모드 깜박임 방지) -->
    <script>
    (function(){
        var t = localStorage.getItem('sb_theme');
        if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme:dark)').matches)) {
            document.documentElement.setAttribute('data-bs-theme','dark');
        }
    })();
    </script>
    <style>
    html{background:var(--bs-tertiary-bg, #f5f5f5);color:#212529}
    html[data-bs-theme="dark"]{background:#1a1a2e;color:#e0e0e0}
    body{background:transparent}
    </style>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
    /* ===== 기본 리셋 & 폰트 ===== */
    *, *::before, *::after { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        background: var(--bs-tertiary-bg, #f5f5f5);
        color: #222;
    }
    a { color: inherit; text-decoration: none; }

    /* ===== 컨테이너 ===== */
    .rk-container {
        max-width: 640px;
        margin: 0 auto;
        padding: 0 0 80px;
    }

    /* ===== 상단 헤더 ===== */
    .rk-header {
        background: var(--bs-body-bg, #fff);
        border-bottom: 1px solid var(--bs-border-color, #eee);
        padding: 14px 16px;
        position: sticky;
        top: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .rk-header .brand {
        font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 800;
        color: #AE1932;
    }
    .rk-header .header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rk-header .header-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid var(--bs-border-color, #eee);
        background: transparent;
        color: #555;
        cursor: pointer;
        font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
        transition: all 0.2s;
    }
    .rk-header .header-btn:hover { background: var(--bs-tertiary-bg, #f5f5f5); }

    /* ===== 페이지 타이틀 ===== */
    .rk-page-title {
        background: var(--bs-body-bg, #fff);
        padding: 20px 16px 12px;
    }
    .rk-page-title h1 {
        font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 800;
        margin: 0 0 4px;
        color: #111;
    }
    .rk-page-title .subtitle {
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        color: #888;
        margin: 0;
    }

    /* ===== 필터 영역 ===== */
    .rk-filters {
        background: var(--bs-body-bg, #fff);
        border-bottom: 1px solid var(--bs-border-color, #eee);
        padding: 12px 16px;
    }
    .rk-filter-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .rk-filter-btn {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid #ddd;
        background: var(--bs-body-bg, #fff);
        color: #555;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .rk-filter-btn:hover { border-color: #AE1932; color: #AE1932; }
    .rk-filter-btn.active {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }

    /* ===== 카테고리 탭 ===== */
    .rk-cat-wrap {
        background: var(--bs-body-bg, #fff);
        border-bottom: 1px solid var(--bs-border-color, #eee);
        position: sticky;
        top: 64px;
        z-index: 99;
        padding: 10px 0;
    }
    .rk-cat-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 0 16px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .rk-cat-tabs::-webkit-scrollbar { display: none; }
    .rk-cat-tab {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid var(--bs-border-color, #e0e0e0);
        background: var(--bs-body-bg, #fff);
        color: #555;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 500;
        white-space: nowrap;
        text-decoration: none;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .rk-cat-tab:hover { border-color: #AE1932; color: #AE1932; }
    .rk-cat-tab.active {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }

    /* ===== 랭킹 카드 ===== */
    .rk-list {
        padding: 8px 0;
    }
    .rk-card {
        background: var(--bs-body-bg, #fff);
        margin: 0 0 8px;
        padding: 16px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        transition: background 0.2s;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    .rk-card:hover { background: #fafafa; }
    .rk-card:active { background: #f0f0f0; }

    /* 순위 뱃지 */
    .rk-rank-badge {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 800;
        flex-shrink: 0;
        background: #f0f0f0;
        color: #888;
    }
    .rk-rank-badge.top1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: #fff; }
    .rk-rank-badge.top2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); color: #fff; }
    .rk-rank-badge.top3 { background: linear-gradient(135deg, #CD7F32, #B87333); color: #fff; }

    .rk-card-img {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        object-fit: cover;
        flex-shrink: 0;
        background: #eee;
    }

    .rk-card-body {
        flex: 1;
        min-width: 0;
    }
    .rk-card-name {
        font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        margin: 0 0 4px;
        color: #111;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .rk-card-name .featured-badge {
        display: inline-block;
        background: #AE1932;
        color: #fff;
        font-size: calc(10px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        margin-left: 6px;
        vertical-align: middle;
    }
    .rk-card-category {
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: #888;
        margin: 0 0 6px;
    }
    .rk-card-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .rk-stat {
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: var(--bs-secondary-color, #666);
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .rk-stat .star { color: #FFB800; }
    .rk-stat strong {
        font-weight: 700;
        color: var(--bs-body-color, #333);
    }

    /* 점수 바 */
    .rk-score-bar-wrap {
        margin-top: 8px;
    }
    .rk-score-bar {
        height: 4px;
        background: #eee;
        border-radius: 2px;
        overflow: hidden;
    }
    .rk-score-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #AE1932, #FF8A50);
        border-radius: 2px;
        transition: width 0.6s ease;
    }
    .rk-score-label {
        font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1));
        color: #999;
        margin-top: 2px;
        text-align: right;
    }

    /* ===== 빈 상태 ===== */
    .rk-empty {
        text-align: center;
        padding: 80px 20px;
        color: #999;
    }
    .rk-empty .empty-icon {
        font-size: calc(56px * var(--fs-scale,1) * var(--fs-page,1));
        display: block;
        margin-bottom: 12px;
        color: #ccc;
    }
    .rk-empty h3 {
        font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #555;
        margin: 0 0 8px;
    }
    .rk-empty p {
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        margin: 0 0 20px;
    }

    /* ===== 페이지네이션 ===== */
    .rk-pagination {
        display: flex;
        justify-content: center;
        gap: 4px;
        padding: 20px 16px;
    }
    .rk-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
        border-radius: 8px;
        border: 1px solid #ddd;
        background: var(--bs-body-bg, #fff);
        color: #555;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }
    .rk-page-btn:hover { border-color: #AE1932; color: #AE1932; }
    .rk-page-btn.active {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }
    .rk-page-btn.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    /* ===== 푸터 ===== */
    .rk-footer {
        background: var(--bs-body-bg, #fff);
        border-top: 1px solid var(--bs-border-color, #eee);
        padding: 20px 16px;
        text-align: center;
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: #999;
    }

    /* ===== 다크모드 ===== */
    html[data-bs-theme="dark"] body { background: #1a1a2e; color: #e0e0e0; }
    html[data-bs-theme="dark"] .rk-header { background: #16213e; border-color: #2a2a4a; }
    html[data-bs-theme="dark"] .rk-header .brand { color: #FF8A50; }
    html[data-bs-theme="dark"] .rk-header .header-btn { border-color: #2a2a4a; color: #aaa; }
    html[data-bs-theme="dark"] .rk-page-title { background: #16213e; }
    html[data-bs-theme="dark"] .rk-page-title h1 { color: #eee; }
    html[data-bs-theme="dark"] .rk-filters { background: #16213e; border-color: #2a2a4a; }
    html[data-bs-theme="dark"] .rk-filter-btn { background: #1a1a2e; border-color: #2a2a4a; color: #aaa; }
    html[data-bs-theme="dark"] .rk-filter-btn.active { background: #AE1932; color: #fff; border-color: #AE1932; }
    html[data-bs-theme="dark"] .rk-cat-wrap { background: #16213e; border-color: #2a2a4a; }
    html[data-bs-theme="dark"] .rk-cat-tab { background: #1a1a2e; border-color: #2a2a4a; color: #aaa; }
    html[data-bs-theme="dark"] .rk-cat-tab.active { background: #AE1932; color: #fff; border-color: #AE1932; }
    html[data-bs-theme="dark"] .rk-card { background: #16213e; }
    html[data-bs-theme="dark"] .rk-card:hover { background: #1e2a4a; }
    html[data-bs-theme="dark"] .rk-card-name { color: #eee; }
    html[data-bs-theme="dark"] .rk-stat { color: #aaa; }
    html[data-bs-theme="dark"] .rk-stat strong { color: #ddd; }
    html[data-bs-theme="dark"] .rk-rank-badge { background: #2a2a4a; color: #aaa; }
    html[data-bs-theme="dark"] .rk-score-bar { background: #2a2a4a; }
    html[data-bs-theme="dark"] .rk-page-btn { background: #16213e; border-color: #2a2a4a; color: #aaa; }
    html[data-bs-theme="dark"] .rk-page-btn.active { background: #AE1932; color: #fff; border-color: #AE1932; }
    html[data-bs-theme="dark"] .rk-footer { background: #16213e; border-color: #2a2a4a; }
    html[data-bs-theme="dark"] .rk-empty h3 { color: #ccc; }

    /* ===== 반응형 ===== */
    @media (max-width: 480px) {
        .rk-card { padding: 12px; gap: 10px; }
        .rk-card-img { width: 52px; height: 52px; border-radius: 10px; }
        .rk-card-name { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
        .rk-page-title h1 { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); }
        .rk-rank-badge { width: 32px; height: 32px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); }
    }
    </style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<?php if ($has_global_nav): ?>
    <?php include __DIR__ . '/includes/global_nav.php'; ?>
<?php endif; ?>

<div class="rk-container">

    <!-- 상단 헤더 -->
    <?php if (file_exists(__DIR__ . '/includes/ad_banner.php')): ?>
        <?php $ad_page_key = 'rankings'; include __DIR__ . '/includes/ad_banner.php'; ?>
    <?php endif; ?>
    <header class="rk-header">
        <a href="index.php" class="brand"><?php echo htmlspecialchars($site_name); ?></a>
        <div class="header-actions">
            <a href="index.php" class="header-btn" title="홈"><i class="bi bi-house"></i></a>
            <a href="hospitals.php" class="header-btn" title="병원 목록"><i class="bi bi-hospital"></i></a>
        </div>
    </header>

    <!-- 페이지 타이틀 -->
    <div class="rk-page-title">
        <h1><i class="bi bi-trophy"></i> 병원 랭킹</h1>
        <p class="subtitle">
            평점, 후기, 예약 수 기반 병원 순위
            <?php if ($total_count > 0): ?>
                &middot; 총 <?php echo number_format($total_count); ?>개 병원
            <?php endif; ?>
        </p>
    </div>

    <!-- 기간 필터 -->
    <div class="rk-filters">
        <div class="rk-filter-row" style="margin-bottom: 8px;">
            <span style="font-size:12px;color:#999;line-height:30px;margin-right:4px;">기간:</span>
            <?php foreach ($period_options as $pk => $plabel): ?>
            <a href="<?php echo build_ranking_url(array_merge($current_params, ['period' => $pk, 'page' => 1])); ?>"
               class="rk-filter-btn <?php echo $filter_period === $pk ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($plabel); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="rk-filter-row">
            <span style="font-size:12px;color:#999;line-height:30px;margin-right:4px;">정렬:</span>
            <?php foreach ($sort_options as $sk => $sopt): ?>
            <a href="<?php echo build_ranking_url(array_merge($current_params, ['sort' => $sk, 'page' => 1])); ?>"
               class="rk-filter-btn <?php echo $sort === $sk ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($sopt['label']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 카테고리 탭 -->
    <div class="rk-cat-wrap">
        <div class="rk-cat-tabs">
            <a href="<?php echo build_ranking_url(array_merge($current_params, ['cat' => '', 'page' => 1])); ?>"
               class="rk-cat-tab <?php echo $filter_cat === '' ? 'active' : ''; ?>">전체</a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo build_ranking_url(array_merge($current_params, ['cat' => $cat, 'page' => 1])); ?>"
               class="rk-cat-tab <?php echo $filter_cat === $cat ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 랭킹 목록 -->
    <?php if (empty($rankings)): ?>
    <div class="rk-empty">
        <span class="empty-icon"><i class="bi bi-trophy"></i></span>
        <h3>랭킹 데이터가 없습니다</h3>
        <p>아직 해당 조건의 랭킹이 집계되지 않았습니다.</p>
        <a href="rankings.php" class="rk-filter-btn active" style="display:inline-block;padding:10px 24px;border-radius:10px;font-weight:600;">
            전체 랭킹 보기
        </a>
    </div>
    <?php else: ?>
    <div class="rk-list">
        <?php
        // 최고 점수 (점수 바 비율 계산용)
        $max_score = 1;
        foreach ($rankings as $rk) {
            $s = floatval($rk['rank_score']);
            if ($s > $max_score) $max_score = $s;
        }

        $rank_num = $offset;
        foreach ($rankings as $rk):
            $rank_num++;
            $hospital_id = intval($rk['hospital_id']);
            $hospital_name = htmlspecialchars($rk['hospital_name'] ?? '병원');
            $category = htmlspecialchars($rk['category'] ?? '');
            $avg_rating = round(floatval($rk['avg_rating']), 1);
            $review_count = intval($rk['review_count']);
            $booking_count = intval($rk['booking_count']);
            $rank_score = floatval($rk['rank_score']);
            $is_featured = intval($rk['is_featured'] ?? 0);
            $image_url = $rk['image_url'] ?? '';
            $address = htmlspecialchars($rk['address'] ?? '');
            $score_pct = ($max_score > 0) ? round(($rank_score / $max_score) * 100, 1) : 0;

            // 순위 뱃지 클래스
            $badge_class = '';
            if ($rank_num === 1) $badge_class = 'top1';
            elseif ($rank_num === 2) $badge_class = 'top2';
            elseif ($rank_num === 3) $badge_class = 'top3';
        ?>
        <a href="detail.php?id=<?php echo $hospital_id; ?>" class="rk-card">
            <div class="rk-rank-badge <?php echo $badge_class; ?>">
                <?php echo $rank_num; ?>
            </div>

            <?php if (!empty($image_url)): ?>
            <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo $hospital_name; ?>" class="rk-card-img"
                 onerror="this.style.display='none'">
            <?php endif; ?>

            <div class="rk-card-body">
                <div class="rk-card-name">
                    <?php echo $hospital_name; ?>
                    <?php if ($is_featured): ?>
                        <span class="featured-badge">추천</span>
                    <?php endif; ?>
                </div>
                <?php if ($category || $address): ?>
                <div class="rk-card-category">
                    <?php if ($category): ?>
                        <span><?php echo $category; ?></span>
                    <?php endif; ?>
                    <?php if ($address): ?>
                        <?php if ($category): ?> &middot; <?php endif; ?>
                        <span><?php echo $address; ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="rk-card-stats">
                    <span class="rk-stat">
                        <span class="star"><i class="bi bi-star-fill"></i></span>
                        <strong><?php echo number_format($avg_rating, 1); ?></strong>
                    </span>
                    <span class="rk-stat">
                        <i class="bi bi-chat-dots"></i>
                        후기 <strong><?php echo number_format($review_count); ?></strong>
                    </span>
                    <span class="rk-stat">
                        <i class="bi bi-calendar-check"></i>
                        예약 <strong><?php echo number_format($booking_count); ?></strong>
                    </span>
                    <span class="rk-stat">
                        종합 <strong><?php echo number_format($rank_score, 1); ?>점</strong>
                    </span>
                </div>

                <div class="rk-score-bar-wrap">
                    <div class="rk-score-bar">
                        <div class="rk-score-bar-fill" style="width: <?php echo $score_pct; ?>%"></div>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="rk-pagination">
        <a href="<?php echo build_ranking_url(array_merge($current_params, ['page' => $page - 1])); ?>"
           class="rk-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <i class="bi bi-chevron-left"></i>
        </a>
        <?php
        $start_pg = max(1, $page - 3);
        $end_pg = min($total_pages, $page + 3);
        for ($p = $start_pg; $p <= $end_pg; $p++):
        ?>
        <a href="<?php echo build_ranking_url(array_merge($current_params, ['page' => $p])); ?>"
           class="rk-page-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
        <a href="<?php echo build_ranking_url(array_merge($current_params, ['page' => $page + 1])); ?>"
           class="rk-page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <div style="text-align:center;padding:0 16px 8px;">
        <small style="color:#999;font-size:12px;">
            <?php echo number_format($total_count); ?>개 병원 중 <?php echo $offset + 1; ?>~<?php echo min($offset + $per_page, $total_count); ?>
            (<?php echo $page; ?>/<?php echo $total_pages; ?>)
        </small>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<!-- 푸터 -->
<footer class="rk-footer">
    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
</footer>

<?php if ($has_bottom_nav): ?>
    <?php include __DIR__ . '/includes/bottom_navigation.php'; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    // 활성 카테고리 탭 자동 스크롤
    var activeTab = document.querySelector('.rk-cat-tab.active');
    if (activeTab) {
        var container = activeTab.parentElement;
        if (container) {
            var scrollLeft = activeTab.offsetLeft - (container.offsetWidth / 2) + (activeTab.offsetWidth / 2);
            container.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'smooth' });
        }
    }
})();
</script>
</body>
</html>
<?php
mysqli_close($connect);
?>

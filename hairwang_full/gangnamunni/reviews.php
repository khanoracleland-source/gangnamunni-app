<?php
/**
 * 후기 목록 (공개 프론트엔드)
 * - hospital_reviews 테이블에서 승인된 후기만 표시
 * - 카테고리 필터, 정렬, 검색, 더보기 페이지네이션
 * - 모바일 퍼스트 카드 레이아웃 (max-width 640px)
 *
 * @created 2026-03-04
 */

// 초기화 (세션 + DB 연결) — init.php가 $connect 제공
$init_loaded = false;
if (file_exists(__DIR__ . '/includes/init.php')) {
    require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_blocking.php';
require_once __DIR__ . '/includes/settings_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
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
$reviews_table_exists = false;
$tbl_check = @mysqli_query($connect, "SHOW TABLES LIKE 'hospital_reviews'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $reviews_table_exists = true;
}

// ===== 카테고리 목록 (필터 탭용) =====
$categories = [];
$cat_result = @mysqli_query($connect,
    "SELECT DISTINCT name FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC"
);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        if (!empty($row['name'])) {
            $categories[] = $row['name'];
        }
    }
}
// 카테고리 테이블이 비어있으면 기본값
if (empty($categories)) {
    $categories = ['눈성형', '코성형', '리프팅', '피부관리', '보톡스/필러', '가슴성형', '지방흡입', '치아', '탈모'];
}

// ===== 필터 / 정렬 파라미터 =====
$filter_cat = trim($_GET['cat'] ?? '');
$filter_search = trim($_GET['q'] ?? '');
$sort = trim($_GET['sort'] ?? 'latest');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 정렬 옵션
$sort_options = [
    'latest'  => ['label' => '최신순',    'sql' => 'r.created_at DESC'],
    'rating'  => ['label' => '별점 높은순', 'sql' => 'r.rating DESC, r.created_at DESC'],
    'views'   => ['label' => '조회수순',   'sql' => 'r.view_count DESC, r.created_at DESC'],
];
if (!isset($sort_options[$sort])) {
    $sort = 'latest';
}
$order_sql = $sort_options[$sort]['sql'];

// ===== 후기 조회 =====
$reviews = [];
$total_count = 0;
$total_pages = 1;

if ($reviews_table_exists) {
    // WHERE 절 구성
    $where_clauses = ["r.is_active = 1", community_content_condition($connect, "r.user_id")];
    $bind_types = '';
    $bind_values = [];

    // 카테고리 필터: hospital의 categories 또는 review의 category 컬럼 확인
    if (!empty($filter_cat)) {
        // hospital_reviews에 category 컬럼이 있을 수 있음
        $has_cat_col = false;
        $col_check = @mysqli_query($connect, "SHOW COLUMNS FROM hospital_reviews LIKE 'category'");
        if ($col_check && mysqli_num_rows($col_check) > 0) {
            $has_cat_col = true;
        }

        if ($has_cat_col) {
            $where_clauses[] = "(r.category = ? OR h.categories LIKE CONCAT('%', ?, '%'))";
            $bind_types .= 'ss';
            $bind_values[] = $filter_cat;
            $bind_values[] = $filter_cat;
        } else {
            $where_clauses[] = "h.categories LIKE CONCAT('%', ?, '%')";
            $bind_types .= 's';
            $bind_values[] = $filter_cat;
        }
    }

    // 검색: 병원명 또는 후기 내용
    if (!empty($filter_search)) {
        $where_clauses[] = "(h.name LIKE CONCAT('%', ?, '%') OR r.title LIKE CONCAT('%', ?, '%') OR r.content LIKE CONCAT('%', ?, '%'))";
        $bind_types .= 'sss';
        $bind_values[] = $filter_search;
        $bind_values[] = $filter_search;
        $bind_values[] = $filter_search;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // view_count 컬럼 존재 확인
    $has_view_count = false;
    $vc_check = @mysqli_query($connect, "SHOW COLUMNS FROM hospital_reviews LIKE 'view_count'");
    if ($vc_check && mysqli_num_rows($vc_check) > 0) {
        $has_view_count = true;
    }
    // view_count가 없으면 정렬 폴백
    if (!$has_view_count && $sort === 'views') {
        $order_sql = 'r.created_at DESC';
    }

    // 전체 건수
    $count_sql = "SELECT COUNT(*) as cnt
                  FROM hospital_reviews r
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

    // 후기 목록
    $select_cols = "r.id, r.hospital_id, r.user_name, COALESCE(r.mb_id, '') as mb_id, r.rating, r.title, r.content,
                    r.before_image, r.after_image, r.created_at,
                    h.name as hospital_name";
    if ($has_view_count) {
        $select_cols .= ", COALESCE(r.view_count, 0) as view_count";
    } else {
        $select_cols .= ", 0 as view_count";
    }

    $list_sql = "SELECT {$select_cols}
                 FROM hospital_reviews r
                 LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
                 WHERE {$where_sql}
                 ORDER BY {$order_sql}
                 LIMIT ?, ?";

    $list_stmt = @mysqli_prepare($connect, $list_sql);
    if ($list_stmt) {
        $list_bind_types = $bind_types . 'ii';
        $list_bind_values = array_merge($bind_values, [$offset, $per_page]);
        mysqli_stmt_bind_param($list_stmt, $list_bind_types, ...$list_bind_values);
        mysqli_stmt_execute($list_stmt);
        $list_result = mysqli_stmt_get_result($list_stmt);
        if ($list_result) {
            while ($row = mysqli_fetch_assoc($list_result)) {
                $reviews[] = $row;
            }
        }
        mysqli_stmt_close($list_stmt);
    }
}

// ===== 이름 마스킹 헬퍼 =====
function mask_name($name) {
    $name = trim($name);
    if (empty($name)) return '익명';
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 1) return $name;
    if ($len === 2) return mb_substr($name, 0, 1, 'UTF-8') . '*';
    // 3글자 이상: 첫 글자 + ** (나머지 모두 마스킹)
    return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', $len - 1);
}

// ===== 별점 HTML 헬퍼 =====
function render_stars($rating) {
    $rating = floatval($rating);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<span class="star filled">&#9733;</span>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<span class="star half">&#9733;</span>';
        } else {
            $html .= '<span class="star empty">&#9734;</span>';
        }
    }
    return $html;
}

// ===== 상대 시간 헬퍼 =====
function time_ago($datetime) {
    if (empty($datetime)) return '';
    $now = time();
    $diff = $now - strtotime($datetime);
    if ($diff < 60) return '방금 전';
    if ($diff < 3600) return floor($diff / 60) . '분 전';
    if ($diff < 86400) return floor($diff / 3600) . '시간 전';
    if ($diff < 604800) return floor($diff / 86400) . '일 전';
    if ($diff < 2592000) return floor($diff / 604800) . '주 전';
    return date('Y.m.d', strtotime($datetime));
}

// ===== 텍스트 미리보기 (HTML 제거 + 길이 제한) =====
function preview_text($text, $max_len = 80) {
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $max_len) {
        return mb_substr($text, 0, $max_len, 'UTF-8') . '...';
    }
    return $text;
}

// ===== URL 빌더 헬퍼 =====
function build_review_url($params = []) {
    $base = 'reviews.php';
    $query = [];
    if (!empty($params['cat'])) $query['cat'] = $params['cat'];
    if (!empty($params['q'])) $query['q'] = $params['q'];
    if (!empty($params['sort']) && $params['sort'] !== 'latest') $query['sort'] = $params['sort'];
    if (!empty($params['page']) && $params['page'] > 1) $query['page'] = $params['page'];
    return $base . (!empty($query) ? '?' . http_build_query($query) : '');
}

// 현재 필터 상태를 기본 파라미터로
$current_params = [
    'cat'  => $filter_cat,
    'q'    => $filter_search,
    'sort' => $sort,
];

// 글로벌 네비게이션 (존재하면 포함)
$has_global_nav = file_exists(__DIR__ . '/includes/global_nav.php');
$has_bottom_nav = file_exists(__DIR__ . '/includes/bottom_navigation.php');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo term('review', '후기'); ?> | <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="실제 <?php echo term('service', '시술'); ?> <?php echo term('review', '후기'); ?>를 확인하세요. 전/후 사진과 함께 진솔한 <?php echo term('review', '후기'); ?>를 만나보세요.">

    <!-- anti-flash (다크모드 깜박임 방지) -->
    <style>
    html{background:var(--bs-tertiary-bg, #f5f5f5);color:#212529}

    body{background:transparent}
    </style>


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
    .rv-container {
        max-width: 640px;
        margin: 0 auto;
        padding: 0 0 80px;
    }

    /* ===== 상단 헤더 ===== */
    .rv-header {
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

    .rv-header .brand {
        font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 800;
        color: #AE1932;
    }
    .rv-header .header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rv-header .header-btn {
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
    .rv-header .header-btn:hover { background: var(--bs-tertiary-bg, #f5f5f5); }

    /* ===== 페이지 타이틀 영역 ===== */
    .rv-page-title {
        background: var(--bs-body-bg, #fff);
        padding: 20px 16px 12px;
    }
    .rv-page-title h1 {
        font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 800;
        margin: 0 0 4px;
        color: #111;
    }
    .rv-page-title .subtitle {
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        color: #888;
        margin: 0;
    }

    .rv-write-btn {
        display: inline-block;
        background: var(--primary-color, #AE1932);
        color: #fff;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .rv-write-btn:hover { opacity: 0.9; color: #fff; }

    /* ===== 카테고리 필터 탭 ===== */
    .rv-cat-wrap {
        background: var(--bs-body-bg, #fff);
        border-bottom: 1px solid var(--bs-border-color, #eee);
        position: sticky;
        top: 64px;
        z-index: 99;
        padding: 10px 0;
    }

    .rv-cat-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 0 16px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .rv-cat-tabs::-webkit-scrollbar { display: none; }

    .rv-cat-tab {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        border: 1px solid var(--bs-border-color, #e0e0e0);
        color: #555;
        background: transparent;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .rv-cat-tab:hover { background: var(--bs-tertiary-bg, #f5f5f5); color: var(--bs-body-color, #333); }

    .rv-cat-tab.active {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }

    /* ===== 정렬 & 검색 바 ===== */
    .rv-toolbar {
        background: var(--bs-body-bg, #fff);
        padding: 12px 16px;
        border-bottom: 1px solid var(--bs-border-color, #eee);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .rv-sort-group {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }
    .rv-sort-btn {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        border: none;
        background: transparent;
        color: #888;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rv-sort-btn:hover { color: #AE1932; }
    .rv-sort-btn.active {
        background: #FFF0E8;
        color: #AE1932;
    }

    .rv-result-count {
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: #999;
        flex-shrink: 0;
    }

    /* ===== 검색 바 ===== */
    .rv-search-bar {
        background: var(--bs-body-bg, #fff);
        padding: 10px 16px 14px;
        border-bottom: 1px solid var(--bs-border-color, #eee);
    }

    .rv-search-form {
        display: flex;
        gap: 8px;
    }
    .rv-search-input {
        flex: 1;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid #ddd;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        outline: none;
        background: var(--bs-tertiary-bg, #f8f8f8);
        transition: border-color 0.2s, box-shadow 0.2s;
        color: var(--bs-body-color, #333);
    }
    .rv-search-input:focus {
        border-color: #AE1932;
        box-shadow: 0 0 0 3px rgba(174,25,50,0.1);
        background: var(--bs-body-bg, #fff);
    }
    .rv-search-input::placeholder { color: #bbb; }

    .rv-search-btn {
        padding: 10px 16px;
        border-radius: 10px;
        border: none;
        background: #AE1932;
        color: #fff;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .rv-search-btn:hover { background: #8A1428; }

    .rv-search-clear {
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #ddd;
        background: transparent;
        color: #888;
        cursor: pointer;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        flex-shrink: 0;
    }

    /* ===== 리뷰 카드 리스트 ===== */
    .rv-list {
        padding: 12px 16px;
    }

    .rv-card {
        background: var(--bs-body-bg, #fff);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .rv-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }

    .rv-card-body {
        padding: 16px;
    }

    /* 카드 상단: 사용자 + 병원 + 날짜 */
    .rv-card-top {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .rv-user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #AE1932, #FF8A50);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        flex-shrink: 0;
    }
    .rv-user-info {
        flex: 1;
        min-width: 0;
    }
    .rv-user-name {
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #222;
        line-height: 1.3;
    }
    .rv-hospital-link {
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: #888;
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .rv-hospital-link:hover { color: #AE1932; }
    .rv-card-date {
        font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1));
        color: #bbb;
        flex-shrink: 0;
        text-align: right;
    }

    /* 별점 */
    .rv-stars {
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .rv-stars .star {
        font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
        line-height: 1;
    }
    .rv-stars .star.filled { color: #FFB800; }
    .rv-stars .star.half { color: #FFB800; opacity: 0.6; }
    .rv-stars .star.empty { color: #ddd; }
    .rv-stars .rating-num {
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #FFB800;
        margin-left: 2px;
    }

    /* 제목 & 내용 */
    .rv-card-title {
        font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #111;
        margin: 0 0 6px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .rv-card-content {
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        color: #555;
        line-height: 1.6;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin: 0;
        word-break: keep-all;
    }

    /* 전/후 이미지 */
    .rv-ba-images {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    .rv-ba-item {
        flex: 1;
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        background: #f0f0f0;
    }

    .rv-ba-item img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }
    .rv-ba-label {
        position: absolute;
        top: 6px;
        left: 6px;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: calc(10px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #fff;
    }
    .rv-ba-label.before { background: rgba(0,0,0,0.5); }
    .rv-ba-label.after { background: #AE1932; }

    /* ===== 빈 상태 ===== */
    .rv-empty {
        text-align: center;
        padding: 80px 20px;
        color: #999;
    }
    .rv-empty .empty-icon {
        font-size: calc(56px * var(--fs-scale,1) * var(--fs-page,1));
        display: block;
        margin-bottom: 12px;
        color: #ccc;
    }
    .rv-empty h3 {
        font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 700;
        color: #555;
        margin: 0 0 8px;
    }
    .rv-empty p {
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        margin: 0 0 20px;
    }
    .rv-empty .btn-reset {
        display: inline-block;
        padding: 10px 24px;
        background: #AE1932;
        color: #fff;
        border-radius: 10px;
        font-weight: 600;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        transition: background 0.2s;
    }
    .rv-empty .btn-reset:hover { background: #8A1428; }

    /* ===== 더보기 버튼 ===== */
    .rv-load-more {
        text-align: center;
        padding: 16px;
    }
    .rv-load-more .btn-more {
        display: inline-block;
        padding: 12px 40px;
        border-radius: 12px;
        border: 1px solid #ddd;
        background: var(--bs-body-bg, #fff);
        color: #555;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rv-load-more .btn-more:hover {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }

    /* ===== 페이지네이션 ===== */
    .rv-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 4px;
        padding: 20px 16px;
    }
    .rv-pagination .pg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid var(--bs-border-color, #e0e0e0);
        background: var(--bs-body-bg, #fff);
        color: #555;
        font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        padding: 0 8px;
    }
    .rv-pagination .pg-btn:hover { background: #FFF0E8; color: #AE1932; border-color: #AE1932; }
    .rv-pagination .pg-btn.active {
        background: #AE1932;
        color: #fff;
        border-color: #AE1932;
    }
    .rv-pagination .pg-btn.disabled {
        opacity: 0.4;
        cursor: default;
        pointer-events: none;
    }

    /* ===== 푸터 ===== */
    .rv-footer {
        text-align: center;
        padding: 24px 16px;
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        color: #bbb;
        border-top: 1px solid var(--bs-border-color, #eee);
    }

    /* ===== 필터 태그 ===== */
    .rv-active-filters {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .rv-filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        background: #FFF0E8;
        color: #AE1932;
        font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        font-weight: 600;
        border-radius: 6px;
    }
    .rv-filter-tag .tag-close {
        cursor: pointer;
        font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        line-height: 1;
        opacity: 0.7;
    }
    .rv-filter-tag .tag-close:hover { opacity: 1; }

    /* ===== 반응형 ===== */
    @media (max-width: 400px) {
        .rv-ba-item img { height: 90px; }
        .rv-card-body { padding: 14px; }
        .rv-page-title h1 { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); }
    }
    </style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<?php if ($has_global_nav): ?>
    <?php include __DIR__ . '/includes/global_nav.php'; ?>
<?php endif; ?>

<div class="rv-container">

    <!-- 상단 헤더 -->
        <?php $ad_page_key = 'reviews'; include __DIR__ . '/includes/ad_banner.php'; ?>
    <header class="rv-header">
        <a href="index.php" class="brand"><?php echo htmlspecialchars($site_name); ?></a>
        <div class="header-actions">
            <a href="index.php" class="header-btn" title="홈">&#127968;</a>
            <a href="consultation.php" class="header-btn" title="<?php echo term('consultation', '상담'); ?> 신청">&#128172;</a>
        </div>
    </header>

    <!-- 페이지 타이틀 -->
    <div class="rv-page-title">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1>&#128221; <?php echo term('service', '시술'); ?> <?php echo term('review', '후기'); ?></h1>
                <p class="subtitle">실제 <?php echo term('service', '시술'); ?> 경험을 공유하는 진솔한 <?php echo term('review', '후기'); ?></p>
            </div>
            <a href="review_write.php" class="rv-write-btn"><?php echo term('review', '후기'); ?> 작성</a>
        </div>
    </div>

    <!-- 카테고리 필터 -->
    <div class="rv-cat-wrap">
        <div class="rv-cat-tabs">
            <a href="<?php echo build_review_url(array_merge($current_params, ['cat' => '', 'page' => 1])); ?>"
               class="rv-cat-tab <?php echo empty($filter_cat) ? 'active' : ''; ?>">
                전체
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo build_review_url(array_merge($current_params, ['cat' => $cat, 'page' => 1])); ?>"
               class="rv-cat-tab <?php echo ($filter_cat === $cat) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 검색 바 -->
    <div class="rv-search-bar">
        <form class="rv-search-form" method="GET" action="reviews.php">
            <?php if (!empty($filter_cat)): ?>
            <input type="hidden" name="cat" value="<?php echo htmlspecialchars($filter_cat); ?>">
            <?php endif; ?>
            <?php if ($sort !== 'latest'): ?>
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <?php endif; ?>
            <input type="text" name="q" class="rv-search-input"
                   placeholder="<?php echo term('business', '병원'); ?>명 또는 <?php echo term('review', '후기'); ?> 내용 검색..."
                   value="<?php echo htmlspecialchars($filter_search); ?>">
            <button type="submit" class="rv-search-btn">검색</button>
            <?php if (!empty($filter_search)): ?>
            <a href="<?php echo build_review_url(array_merge($current_params, ['q' => '', 'page' => 1])); ?>"
               class="rv-search-clear" title="검색 초기화">&#10005;</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 정렬 & 결과 수 -->
    <div class="rv-toolbar">
        <div class="rv-sort-group">
            <?php foreach ($sort_options as $key => $opt): ?>
            <a href="<?php echo build_review_url(array_merge($current_params, ['sort' => $key, 'page' => 1])); ?>"
               class="rv-sort-btn <?php echo ($sort === $key) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($opt['label']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="rv-result-count">
            <?php if (!empty($filter_cat) || !empty($filter_search)): ?>
            <div class="rv-active-filters">
                <?php if (!empty($filter_cat)): ?>
                <span class="rv-filter-tag">
                    <?php echo htmlspecialchars($filter_cat); ?>
                    <a href="<?php echo build_review_url(array_merge($current_params, ['cat' => '', 'page' => 1])); ?>"
                       class="tag-close">&#10005;</a>
                </span>
                <?php endif; ?>
                <?php if (!empty($filter_search)): ?>
                <span class="rv-filter-tag">
                    "<?php echo htmlspecialchars($filter_search); ?>"
                    <a href="<?php echo build_review_url(array_merge($current_params, ['q' => '', 'page' => 1])); ?>"
                       class="tag-close">&#10005;</a>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            총 <?php echo number_format($total_count); ?>건
        </div>
    </div>

    <!-- 후기 목록 -->
    <?php if (!$reviews_table_exists): ?>
    <!-- 테이블이 없는 경우: 안내 메시지 -->
    <div class="rv-empty">
        <span class="empty-icon">&#128196;</span>
        <h3><?php echo term('review', '후기'); ?> 서비스 준비 중</h3>
        <p>곧 다양한 <?php echo term('service', '시술'); ?> <?php echo term('review', '후기'); ?>를 만나보실 수 있습니다.</p>
        <a href="index.php" class="btn-reset"><?php echo term('business', '병원'); ?> 둘러보기</a>
    </div>

    <?php elseif (empty($reviews)): ?>
    <!-- 검색 결과 없음 -->
    <div class="rv-empty">
        <span class="empty-icon">&#128269;</span>
        <h3><?php echo term('review', '후기'); ?>가 없습니다</h3>
        <p>
            <?php if (!empty($filter_search) || !empty($filter_cat)): ?>
                다른 카테고리를 선택하거나 검색어를 변경해보세요.
            <?php else: ?>
                아직 등록된 <?php echo term('review', '후기'); ?>가 없습니다. 첫 번째 <?php echo term('review', '후기'); ?>를 작성해보세요!
            <?php endif; ?>
        </p>
        <a href="reviews.php" class="btn-reset">전체 <?php echo term('review', '후기'); ?> 보기</a>
    </div>

    <?php else: ?>
    <div class="rv-list">
        <?php foreach ($reviews as $rv):
            $rv_id = intval($rv['id']);
            $rv_user = htmlspecialchars(mask_name($rv['user_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rv_user_initial = mb_substr($rv['user_name'] ?? '?', 0, 1, 'UTF-8');
            $rv_hospital = htmlspecialchars($rv['hospital_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $rv_hospital_id = intval($rv['hospital_id'] ?? 0);
            $rv_rating = floatval($rv['rating'] ?? 0);
            $rv_title = htmlspecialchars($rv['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $rv_content = htmlspecialchars(preview_text($rv['content'] ?? '', 100), ENT_QUOTES, 'UTF-8');
            $rv_before = $rv['before_image'] ?? '';
            $rv_after = $rv['after_image'] ?? '';
            $rv_date = $rv['created_at'] ?? '';
            $rv_views = intval($rv['view_count'] ?? 0);
            $rv_mb_id = htmlspecialchars($rv['mb_id'] ?? '', ENT_QUOTES, 'UTF-8');
            $rv_raw_name = htmlspecialchars($rv['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>
        <div class="rv-card">
            <div class="rv-card-body">
                <!-- 사용자 정보 -->
                <div class="rv-card-top">
                    <div class="rv-user-avatar"><?php echo htmlspecialchars($rv_user_initial); ?></div>
                    <div class="rv-user-info">
                        <div class="rv-user-name"><?php echo $rv_user; ?></div>
                        <?php if (!empty($rv_hospital)): ?>
                        <a href="detail.php?id=<?php echo $rv_hospital_id; ?>" class="rv-hospital-link">
                            &#127973; <?php echo $rv_hospital; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="rv-card-date">
                        <?php echo time_ago($rv_date); ?>
                        <!-- 신고/차단 버튼 (Apple Guideline 1.2) -->
                        <button class="rv-report-btn" onclick="openReportModal('review', <?php echo $rv_id; ?>, '<?php echo $rv_mb_id; ?>', '<?php echo $rv_raw_name; ?>')" title="신고">&#9873;</button>
                        <?php if (!empty($rv_mb_id)): ?>
                        <button class="rv-report-btn" onclick="openBlockModal('<?php echo $rv_mb_id; ?>', '<?php echo $rv_raw_name; ?>')" title="차단">&#128683;</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 별점 -->
                <?php if ($rv_rating > 0): ?>
                <div class="rv-stars">
                    <?php echo render_stars($rv_rating); ?>
                    <span class="rating-num"><?php echo number_format($rv_rating, 1); ?></span>
                </div>
                <?php endif; ?>

                <!-- 제목 & 내용 -->
                <?php if (!empty($rv_title)): ?>
                <h3 class="rv-card-title"><?php echo $rv_title; ?></h3>
                <?php endif; ?>

                <?php if (!empty($rv_content)): ?>
                <p class="rv-card-content"><?php echo $rv_content; ?></p>
                <?php endif; ?>

                <!-- 전/후 이미지 -->
                <?php if (!empty($rv_before) || !empty($rv_after)): ?>
                <div class="rv-ba-images">
                    <?php if (!empty($rv_before)): ?>
                    <div class="rv-ba-item">
                        <img src="<?php echo htmlspecialchars($rv_before, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="시술 전" loading="lazy"
                             onerror="this.style.display='none'">
                        <span class="rv-ba-label before">BEFORE</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rv_after)): ?>
                    <div class="rv-ba-item">
                        <img src="<?php echo htmlspecialchars($rv_after, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="시술 후" loading="lazy"
                             onerror="this.style.display='none'">
                        <span class="rv-ba-label after">AFTER</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="rv-pagination">
        <?php
        // 이전 페이지
        if ($page > 1): ?>
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => $page - 1])); ?>"
           class="pg-btn">&#8249;</a>
        <?php else: ?>
        <span class="pg-btn disabled">&#8249;</span>
        <?php endif; ?>

        <?php
        // 페이지 번호
        $start_page = max(1, $page - 3);
        $end_page = min($total_pages, $page + 3);

        if ($start_page > 1): ?>
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => 1])); ?>"
           class="pg-btn">1</a>
        <?php if ($start_page > 2): ?>
        <span class="pg-btn disabled">...</span>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => $i])); ?>"
           class="pg-btn <?php echo ($i === $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
        <span class="pg-btn disabled">...</span>
        <?php endif; ?>
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => $total_pages])); ?>"
           class="pg-btn"><?php echo $total_pages; ?></a>
        <?php endif; ?>

        <?php
        // 다음 페이지
        if ($page < $total_pages): ?>
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => $page + 1])); ?>"
           class="pg-btn">&#8250;</a>
        <?php else: ?>
        <span class="pg-btn disabled">&#8250;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 더보기 버튼 (다음 페이지가 있을 때) -->
    <?php if ($page < $total_pages): ?>
    <div class="rv-load-more">
        <a href="<?php echo build_review_url(array_merge($current_params, ['page' => $page + 1])); ?>"
           class="btn-more">
            더보기 (<?php echo $page; ?>/<?php echo $total_pages; ?>)
        </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<!-- 푸터 -->
<footer class="rv-footer">
    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
</footer>

<?php if ($has_bottom_nav): ?>
    <?php include __DIR__ . '/includes/bottom_navigation.php'; ?>
<?php endif; ?>

<style>
.rv-report-btn { background: none; border: none; cursor: pointer; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); padding: 2px 4px; opacity: 0.5; transition: opacity 0.2s; vertical-align: middle; }
.rv-report-btn:hover { opacity: 1; }
</style>

<?php include __DIR__ . '/includes/report_block_modal.php'; ?>

<script>
(function(){
    // 활성 카테고리 탭 자동 스크롤 (화면 중앙으로)
    var activeTab = document.querySelector('.rv-cat-tab.active');
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

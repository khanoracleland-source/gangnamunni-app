<?php
/**
 * ========================================
 * 커뮤니티 게시판 (목록 페이지)
 * ========================================
 * Figma 디자인: 3탭(최신순/인기글/시술후기) + FAB + 좋아요 시스템
 *
 * @file    gangnamunni/community.php
 * @created 2026-03-23
 * @updated 2026-03-25 Figma 리디자인 + 좋아요 시스템
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';

$community_csrf_token = generateCsrfToken();

// --- 탭 / 정렬 ---
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'latest';
if (!in_array($tab, ['latest', 'popular', 'review'])) $tab = 'latest';

// 지역 필터
$region = isset($_GET['region']) ? trim($_GET['region']) : '';
$region_list = array_filter(array_map('trim', explode(',', $region)));

// 키워드 검색 (게시글 제목/내용에서 검색)
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

// 카테고리 필터 (시술후기 탭 = category '시술후기')
$category = '';
if ($tab === 'review') {
    $category = '시술후기';
} elseif (isset($_GET['category'])) {
    $category = trim($_GET['category']);
}

// 페이지네이션
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 좋아요 테이블 자동 생성
$like_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_likes'");
if (!$like_table_check || mysqli_num_rows($like_table_check) === 0) {
    mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `gangnamunni_community_likes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `post_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `user_id` VARCHAR(100) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_post_user` (`post_id`, `user_id`),
        KEY `idx_post_id` (`post_id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// 테이블 존재 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_posts'");
$table_exists = $table_check && mysqli_num_rows($table_check) > 0;

$posts = [];
$total_count = 0;

if ($tab === 'review') {
    // 시술후기 탭 = 승인(published)된 병원 후기(hospital_reviews) 노출
    //  → review_write.php 로 작성 후 관리자 승인된 후기가 여기에 표시된다.
    $rev_where = "r.is_active = 1 AND r.review_status = 'published'";
    $rev_types = '';
    $rev_vals = [];
    if ($keyword !== '') {
        $rev_where .= " AND (r.title LIKE ? OR r.content LIKE ?)";
        $rev_types .= 'ss';
        $kw_like = '%' . $keyword . '%';
        $rev_vals[] = $kw_like;
        $rev_vals[] = $kw_like;
    }

    // 총 개수
    $count_sql = "SELECT COUNT(*) as cnt FROM hospital_reviews r WHERE {$rev_where}";
    if (!empty($rev_vals)) {
        $stmt = mysqli_prepare($connect, $count_sql);
        mysqli_stmt_bind_param($stmt, $rev_types, ...$rev_vals);
        mysqli_stmt_execute($stmt);
        $total_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
        mysqli_stmt_close($stmt);
    } else {
        $cr = mysqli_query($connect, $count_sql);
        $total_count = ($cr ? mysqli_fetch_assoc($cr)['cnt'] : 0);
    }

    // 목록 (커뮤니티 게시글과 동일한 렌더 구조로 매핑)
    $list_sql = "SELECT r.id, r.title, r.content, r.created_at, r.updated_at,
            r.user_name AS author_name, r.user_avatar AS author_avatar, r.user_id AS mb_id,
            r.like_count, r.comment_count, r.view_count, r.rating, r.photo_urls AS photos
        FROM hospital_reviews r
        WHERE {$rev_where}
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";
    $rev_types_full = $rev_types . 'ii';
    $rev_vals_full = array_merge($rev_vals, [$per_page, $offset]);
    $stmt = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($stmt, $rev_types_full, ...$rev_vals_full);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rev_ids = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['category'] = '⭐ ' . number_format((float)($row['rating'] ?? 0), 1) . ' 시술후기';
            $row['detail_link'] = 'review_detail.php?id=' . (int)$row['id'];
            $posts[] = $row;               // photos = photo_urls(있으면) 그대로 유지
            $rev_ids[] = (int)$row['id'];
        }
    }
    mysqli_stmt_close($stmt);

    // 후기 사진은 review_images 테이블에 저장됨(시술 전/후) → 일괄 로드하여 photos(JSON) 로 매핑
    if (!empty($rev_ids)) {
        $img_ph = implode(',', array_fill(0, count($rev_ids), '?'));
        $img_stmt = mysqli_prepare($connect,
            "SELECT review_id, image_url FROM review_images
             WHERE review_id IN ($img_ph)
             ORDER BY review_id, FIELD(photo_category,'before','after'), image_order, id");
        if ($img_stmt) {
            mysqli_stmt_bind_param($img_stmt, str_repeat('i', count($rev_ids)), ...$rev_ids);
            mysqli_stmt_execute($img_stmt);
            $img_res = mysqli_stmt_get_result($img_stmt);
            $img_map = [];
            while ($ir = mysqli_fetch_assoc($img_res)) {
                $img_map[(int)$ir['review_id']][] = $ir['image_url'];
            }
            mysqli_stmt_close($img_stmt);
            // review_images 가 있으면 그 사진을, 없으면 기존 photo_urls 유지
            foreach ($posts as &$p) {
                if (!empty($img_map[(int)$p['id']])) {
                    $p['photos'] = json_encode($img_map[(int)$p['id']]);
                }
            }
            unset($p);
        }
    }
} elseif ($table_exists) {
    // 정렬 기준
    $order_by = 'p.created_at DESC';
    if ($tab === 'popular') {
        $order_by = 'like_count DESC, p.created_at DESC';
    }

    // WHERE 조건 구성
    $where = "p.status='active'";
    $bind_types = '';
    $bind_values = [];

    if (!empty($category)) {
        $where .= " AND p.category=?";
        $bind_types .= 's';
        $bind_values[] = $category;
    }

    if (!empty($region_list)) {
        $region_conditions = [];
        foreach ($region_list as $r) {
            $r_esc = mysqli_real_escape_string($connect, $r);
            $region_conditions[] = "(p.title LIKE '%{$r_esc}%' OR p.content LIKE '%{$r_esc}%')";
        }
        $where .= " AND (" . implode(' OR ', $region_conditions) . ")";
    }

    // 키워드 검색 — 게시글 제목/내용에서 검색
    if ($keyword !== '') {
        $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
        $bind_types .= 'ss';
        $kw_like = '%' . $keyword . '%';
        $bind_values[] = $kw_like;
        $bind_values[] = $kw_like;
    }

    // 총 개수
    $count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_community_posts p WHERE {$where}";
    if (!empty($bind_values)) {
        $stmt = mysqli_prepare($connect, $count_sql);
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
        mysqli_stmt_execute($stmt);
        $cr = mysqli_stmt_get_result($stmt);
        $total_count = mysqli_fetch_assoc($cr)['cnt'] ?? 0;
        mysqli_stmt_close($stmt);
    } else {
        $cr = mysqli_query($connect, $count_sql);
        $total_count = ($cr ? mysqli_fetch_assoc($cr)['cnt'] : 0);
    }

    // 게시글 목록 (like_count 서브쿼리 포함)
    $list_sql = "SELECT p.*,
        COALESCE((SELECT COUNT(*) FROM gangnamunni_community_likes l WHERE l.post_id = p.id), 0) as like_count
        FROM gangnamunni_community_posts p
        WHERE {$where}
        ORDER BY {$order_by}
        LIMIT ? OFFSET ?";

    $bind_types_full = $bind_types . 'ii';
    $bind_values_full = array_merge($bind_values, [$per_page, $offset]);

    $stmt = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($stmt, $bind_types_full, ...$bind_values_full);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// [Track A1 - 2026-06-12] 데모 데이터 폴백 제거 — 게시글 없으면 빈 상태 UI(.no-posts) 표시

$total_pages = max(1, ceil($total_count / $per_page));
$is_logged_in = is_gangnamunni_logged_in();

// 현재 사용자가 좋아요한 항목 집합 (좋아요한 것만 하트를 빨강으로 표시)
//  - 시술후기 탭 = review_likes / 그 외(커뮤니티 글) = gangnamunni_community_likes
$liked_set = [];
$cm_uid = function_exists('get_gangnamunni_member_id') ? get_gangnamunni_member_id() : null;
if ($cm_uid && !empty($posts)) {
    $liked_ids = implode(',', array_map(function ($p) { return (int)$p['id']; }, $posts));
    $uid_esc = mysqli_real_escape_string($connect, $cm_uid);
    if ($tab === 'review') {
        $lq = @mysqli_query($connect, "SELECT review_id AS lid FROM review_likes WHERE user_id = '{$uid_esc}' AND review_id IN ({$liked_ids})");
    } else {
        $lq = @mysqli_query($connect, "SELECT post_id AS lid FROM gangnamunni_community_likes WHERE user_id = '{$uid_esc}' AND post_id IN ({$liked_ids})");
    }
    if ($lq) { while ($lr = mysqli_fetch_assoc($lq)) { $liked_set[(int)$lr['lid']] = true; } }
}

$page_title = '커뮤니티';
$show_search = true; // 메인과 동일한 공용 헤더 검색바(includes/header.php) 사용
$search_target_page = 'community.php';       // 검색/지역 필터는 커뮤니티 게시글 대상으로 동작
$region_storage_key = 'sb_community_regions'; // 병원 목록과 지역 선택 저장소 분리 유지

// --- 시간 표시 헬퍼 ---
function community_time_display($created_at, $updated_at = null) {
    if (empty($created_at)) return '';
    $diff = time() - strtotime($created_at);
    if ($diff < 60) $text = '방금 전';
    elseif ($diff < 3600) $text = floor($diff/60) . '분 전';
    elseif ($diff < 86400) $text = floor($diff/3600) . '시간 전';
    elseif ($diff < 604800) $text = floor($diff/86400) . '일 전';
    else $text = date('m.d', strtotime($created_at));

    if (!empty($updated_at) && $updated_at !== $created_at) {
        $text .= ' (수정됨)';
    }
    return $text;
}

$page_css = '
@import url("https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap");

/* 커뮤니티 화면의 모든 일반 텍스트에 Noto Sans KR 적용 (아이콘 폰트 제외) */
body,
button,
input,
select,
textarea {
    font-family: "Noto Sans KR", sans-serif !important;
}

/* === 커뮤니티 Figma 디자인 === */
.community-wrap { width: 100%; min-width: 0; max-width: 640px; margin: 0 auto; padding: 0 0 70px; }

/* 섹션 타이틀 */
.community-section-title { font-size: 20px; font-weight: 700; color: #000000; padding: 16px 16px 8px; margin: 0; }

/* 3탭 — Figma: filled style, 200px equal thirds, 40px height */
.community-tabs { display: flex; background: var(--bs-body-bg, #fff); position: sticky; top: 0; z-index: 10; }
.community-tabs a { flex: 1; text-align: center; height: 40px; line-height: 40px; padding: 0; font-size: 18px; font-weight: 500; color: #000000; background: transparent; text-decoration: none; border-bottom: 2px solid #AAAAAA; transition: color 0.2s, border-color 0.2s; }
.community-tabs a.active { color: #AE1932; background: transparent; font-weight: 500; border-bottom: 2px solid #AE1932; }
[data-bs-theme="dark"] .community-tabs a { background: transparent; color: #ccc; border-bottom: 2px solid #AAAAAA; }
[data-bs-theme="dark"] .community-tabs a.active { background: transparent; color: #AE1932; border-bottom: 2px solid #AE1932; }

/* 게시글 카드 */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-item { padding: 16px; border-bottom: 1px solid var(--bs-border-color, #e0e0e0); }
.post-item > div { display: flex; gap: 14px; }
.post-content-area { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.post-cat-tag { display: inline-block; padding: 2px 8px; background: #FFEEEE; color: #AE1932; font-size: 13px; font-weight: 500; border-radius: 4px; margin-bottom: 6px; align-self: flex-start; }
[data-bs-theme="dark"] .post-cat-tag { background: #FFEEEE; color: #AE1932; }
.post-title { font-size: 18px; font-weight: 500; color: #000000; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.4; }
[data-bs-theme="dark"] .post-title { color: var(--bs-body-color, #eee); }
.post-preview { font-size: 15px; font-weight: 400; color: #000000; line-height: 1.5; margin-bottom: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.post-preview.expanded { display: block; -webkit-line-clamp: unset; overflow: visible; }
[data-bs-theme="dark"] .post-preview { color: var(--bs-body-color, #ddd); }
.post-readmore-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; margin-top: 0; }
.post-readmore { font-size: 13px; font-weight: 400; color: var(--color-inactive, #AAAAAA); }
.post-readmore-time { font-size: 13px; font-weight: 400; color: var(--color-inactive, #AAAAAA); margin-left: auto; }
/* 썸네일 그리드 (최대 2장, 본문 아래) */
.post-thumbs { width: 100%; display: grid; gap: 4px; border-radius: 8px; overflow: hidden; margin-bottom: 10px; }
.post-thumbs.thumbs-1 { grid-template-columns: 1fr; max-width: 50%; }
.post-thumbs.thumbs-2 { grid-template-columns: 1fr 1fr; }
.post-thumbs .thumb-item { position: relative; aspect-ratio: 1/1; overflow: hidden; background: var(--bs-tertiary-bg, #f5f5f5); }
.post-thumbs .thumb-item img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* 작성자 + 통계 */
.post-author-row { display: flex; align-items: center; gap: 8px; }
.post-avatar { width: 35px; height: 35px; border-radius: 50%; background: #AAAAAA; overflow: hidden; flex-shrink: 0; }
.post-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.post-stat-group { display: inline-flex; align-items: center; gap: 30px; font-size: 13px; color: #000000; margin-left: auto; }
[data-bs-theme="dark"] .post-stat-group { color: var(--bs-body-color, #ccc); }
.post-stat-group .stat { display: inline-flex; align-items: center; gap: 3px; }
.post-stat-group .stat i { font-size: 20px; }
/* 기본(미좋아요) 하트는 중립색(빈 하트), 좋아요 시에만 빨강(.stat-like.liked) */
.post-stat-group .heart-icon { color: inherit; }
.post-author-name { font-size: 15px; font-weight: 400; color: #000000; }
[data-bs-theme="dark"] .post-author-name { color: var(--bs-body-color, #ddd); }

/* FAB — Figma: 100x60 pill, bg #AE1932, icon 40x40 */
.fab-write { position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%); max-width: 100px; height: 60px; border-radius: 50px; background: #d81233; color: #fff; border: none; box-shadow: 0 4px 12px rgba(174,25,50,0.4); display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 18px; font-size: 14px; font-weight: 500; cursor: pointer; z-index: 100; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; white-space: nowrap; }
.fab-write i { font-size: 30px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; }

/* 빈 상태 */
.no-posts { text-align: center; padding: 80px 20px; color: var(--bs-secondary-color, #999); }
.no-posts .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }

/* 페이지네이션 */
.pagination-wrap { display: flex; justify-content: center; gap: 6px; padding: 24px 0 40px; }
.pagination-wrap a, .pagination-wrap span { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; font-size: 14px; text-decoration: none; border: 1px solid var(--bs-border-color, #e0e0e0); color: var(--bs-body-color, #333); background: var(--bs-body-bg, #fff); }
.pagination-wrap span.current { background: #AE1932; color: #fff; border-color: #AE1932; }

/* 게시글 수 */
.post-count-bar { font-size: 13px; color: var(--bs-secondary-color, #888); padding: 10px 16px 0; }

@media (min-width: 641px) {
    .fab-write { right: calc(50% - 300px); }
}
@media (max-width: 480px) {
    .post-thumbs { max-width: 35%; }
    .post-title { font-size: 16px; }
    .community-tabs a { font-size: 16px; }
}
';

$page_js = '
(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var regionParam = urlParams.get("region");
    if (regionParam) {
        localStorage.setItem("sb_community_regions", JSON.stringify(regionParam.split(",").filter(Boolean)));
    } else {
        try {
            var saved = JSON.parse(localStorage.getItem("sb_community_regions") || "[]");
            if (saved.length > 0) {
                var url = new URL(window.location.href);
                url.searchParams.set("region", saved.join(","));
                window.location.replace(url.toString());
            }
        } catch(e) {}
    }
})();
// 검색바/지역 선택 UI + 로직은 includes/header.php 공용 컴포넌트가 담당
';

// 메인과 동일한 헤더 검색바 스타일 적용 (지역 선택 버튼 pill + placeholder 정렬)
$_fs_css_file = __DIR__ . '/assets/css/figma_sync_index.css';
if (file_exists($_fs_css_file)) { $page_css .= "\n" . file_get_contents($_fs_css_file); }

include __DIR__ . '/includes/header.php';
?>

    <div class="community-wrap">
        <!-- 검색바/지역 선택은 공용 헤더(includes/header.php)에서 렌더링 -->

        <h2 class="community-section-title">커뮤니티</h2>

        <!-- 3탭: 최신순 / 인기글 / 시술 후기 -->
        <div class="community-tabs">
            <a href="community.php?tab=latest" class="<?php echo $tab === 'latest' ? 'active' : ''; ?>">최신순</a>
            <a href="community.php?tab=popular" class="<?php echo $tab === 'popular' ? 'active' : ''; ?>">인기순</a>
            <a href="community.php?tab=review" class="<?php echo $tab === 'review' ? 'active' : ''; ?>">시술 후기</a>
        </div>

        <!-- 게시글 수 -->
        <div class="post-count-bar">
            총 <strong><?php echo number_format($total_count); ?></strong>개의 게시글
        </div>

        <!-- 게시글 목록 -->
        <?php if (!empty($posts)): ?>
        <ul class="post-list">
            <?php foreach ($posts as $post):
                $photos = [];
                if (!empty($post['photos'])) {
                    $decoded = json_decode($post['photos'], true);
                    if (is_array($decoded)) $photos = $decoded;
                }
                $content_plain = strip_tags($post['content'] ?? '');
                $preview = mb_substr($content_plain, 0, 50);
                $link = !empty($post['detail_link']) ? $post['detail_link'] : (($post['id'] > 0) ? 'detail_community.php?id=' . $post['id'] : '#');
                $time_text = community_time_display($post['created_at'] ?? '', $post['updated_at'] ?? null);
                $author = htmlspecialchars($post['author_name'] ?? '익명');
            ?>
            <li class="post-item" onclick="location.href='<?php echo $link; ?>'" style="cursor:pointer;">
                    <div class="post-content-area">
                        <span class="post-cat-tag"><?php echo htmlspecialchars($post['category']); ?></span>
                        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        <?php $is_long = mb_strlen($content_plain) > 50; ?>
                        <div class="post-preview"><?php echo htmlspecialchars($preview); ?></div>
                        <div class="post-readmore-row">
                            <?php if ($is_long): ?>
                            <span class="post-readmore" data-full="<?php echo htmlspecialchars($content_plain, ENT_QUOTES); ?>" data-short="<?php echo htmlspecialchars($preview, ENT_QUOTES); ?>" onclick="event.stopPropagation();var p=this.closest('.post-content-area').querySelector('.post-preview');var expanded=p.classList.toggle('expanded');p.textContent=expanded?this.dataset.full:this.dataset.short;this.textContent=expanded?'접기':'...더보기';">...더보기</span>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                            <span class="post-readmore-time"><?php echo $time_text; ?></span>
                        </div>
                        <?php if (!empty($photos)):
                            $photo_count = count($photos);
                            $show_count = min(2, $photo_count);
                            $grid_class = 'thumbs-' . $show_count;
                        ?>
                        <div class="post-thumbs <?php echo $grid_class; ?>">
                            <?php for ($pi = 0; $pi < $show_count; $pi++): ?>
                            <div class="thumb-item">
                                <img src="<?php echo htmlspecialchars($photos[$pi]); ?>" alt="" loading="lazy">
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                        <div class="post-author-row">
                            <div class="post-avatar">
                                <?php if (!empty($post['author_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($post['author_avatar']); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <span class="post-author-name"><?php echo $author; ?></span>
                            <div class="post-stat-group">
                                <?php $__is_review = !empty($post['detail_link']); $__liked = isset($liked_set[(int)$post['id']]); ?>
                                <span class="stat stat-like <?php echo $__liked ? 'liked' : ''; ?>" role="button" onclick="event.stopPropagation(); cmToggleLike(this, <?php echo intval($post['id']); ?>, <?php echo $__is_review ? 'true' : 'false'; ?>);"><i class="bi <?php echo $__liked ? 'bi-heart-fill' : 'bi-heart'; ?> heart-icon"></i> <span class="like-cnt"><?php echo intval($post['like_count'] ?? 0); ?></span></span>
                                <span class="stat"><i class="bi bi-chat-square-text"></i> <?php echo intval($post['comment_count'] ?? 0); ?></span>
                                <span class="stat"><i class="bi bi-eye"></i> <?php echo intval($post['view_count'] ?? 0); ?></span>
                                <button type="button" class="community-more" aria-label="게시글 메뉴" aria-haspopup="dialog"
                                    data-id="<?php echo (int)$post['id']; ?>"
                                    data-type="<?php echo $__is_review ? 'review' : 'community'; ?>"
                                    data-user="<?php echo htmlspecialchars($post['mb_id'] ?? '', ENT_QUOTES); ?>"
                                    data-author="<?php echo htmlspecialchars($post['author_name'] ?? '익명', ENT_QUOTES); ?>"
                                    data-url="<?php echo htmlspecialchars($link, ENT_QUOTES); ?>"
                                    onclick="event.stopPropagation(); openCommunityActions(this)"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <?php
            $tab_param = ($tab !== 'latest') ? '&tab=' . urlencode($tab) : '';
            $cat_param = (!empty($_GET['category'])) ? '&category=' . urlencode($_GET['category']) : '';
            ?>
            <?php if ($page > 1): ?>
            <a href="community.php?page=<?php echo $page-1; ?><?php echo $tab_param . $cat_param; ?>">&laquo;</a>
            <?php endif; ?>
            <?php
            $start_p = max(1, $page - 2);
            $end_p = min($total_pages, $page + 2);
            for ($p = $start_p; $p <= $end_p; $p++):
            ?>
                <?php if ($p === $page): ?>
                <span class="current"><?php echo $p; ?></span>
                <?php else: ?>
                <a href="community.php?page=<?php echo $p; ?><?php echo $tab_param . $cat_param; ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <a href="community.php?page=<?php echo $page+1; ?><?php echo $tab_param . $cat_param; ?>">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-posts">
            <div class="icon"><i class="bi bi-chat-square-text"></i></div>
            <p>아직 게시글이 없습니다.<br>첫 번째 글을 작성해보세요!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- FAB 글쓰기 버튼 (Figma: 100x60 pill) -->
    <?php if ($is_logged_in): ?>
    <a href="community_write.php" class="fab-write" title="글쓰기"><i class="bi bi-pencil-square"></i></a>
    <?php else: ?>
    <button class="fab-write" title="글쓰기" onclick="alert('로그인이 필요합니다.'); location.href='auth/login.php?url=<?php echo urlencode('/hairwang/gangnamunni/community.php'); ?>';"><i class="bi bi-pencil-square"></i></button>
    <?php endif; ?>

<style>
.community-more { border: 0; background: transparent; color: inherit; padding: 4px; min-width: 28px; min-height: 36px; cursor: pointer; font-size: 22px; flex-shrink: 0; }
.community-action-sheet { position: fixed; inset: 0; width: 100%; max-width: none; max-height: none; margin: 0; padding: 0; border: 0; background: transparent; overflow: clip; color: var(--bs-body-color, #000); }
.community-action-sheet::backdrop { background: transparent; }
.community-sheet-backdrop { position: absolute; inset: 0 0 var(--community-nav-height, 77px); background: #000; opacity: 0; touch-action: none; }
.community-sheet-stage { position: absolute; inset: 0 0 var(--community-nav-height, 77px); overflow: clip; pointer-events: none; }
.community-sheet-panel { pointer-events: auto; position: absolute; bottom: 0; left: 0; right: 0; width: 100%; max-width: 640px; margin: 0 auto; border-radius: 0; background: var(--bs-body-bg, #fff); overflow: hidden; touch-action: none; will-change: transform; box-shadow: none; }
.community-sheet-handle { display: block; position: relative; width: 100%; height: 38px; margin: 0; padding: 0; border: 0; background: transparent; cursor: grab; touch-action: none; user-select: none; }
.community-sheet-handle::before { content: ""; position: absolute; top: 14px; left: 50%; transform: translateX(-50%); width: 50px; height: 5px; border-radius: 4px; background: #b81739; }
.community-sheet-handle:active { cursor: grabbing; }
.community-sheet-body { box-sizing: border-box; overflow-y: auto; overscroll-behavior: contain; padding: 0 max(20px, env(safe-area-inset-right)) max(20px, env(safe-area-inset-bottom)) max(20px, env(safe-area-inset-left)); }
.community-sheet-options { padding: 0; }
.community-sheet-action { display: flex; align-items: center; gap: 17px; width: 100%; min-height: 70px; padding: 10px 0; border: 0; background: none; color: inherit; font: inherit; font-size: 18px; text-align: left; cursor: pointer; }
.community-sheet-action i { width: 28px; text-align: center; font-size: 28px; font-weight: 400; }
.community-sheet-action.report { color: #c31336; }
.community-sheet-footer { position: absolute; bottom: 0; left: 0; right: 0; box-sizing: border-box; width: 100%; max-width: 640px; margin: 0 auto; padding: 14px 20px max(20px, env(safe-area-inset-bottom)); background: var(--bs-body-bg, #fff); pointer-events: auto; will-change: transform; }
.community-sheet-footer .community-sheet-cancel { margin-top: 0; }
.community-sheet-cancel { width: 100%; height: 50px; margin-top: 24px; border: 1px solid #d7183d; background: transparent; color: #c31336; font: inherit; font-size: 18px; cursor: pointer; }
.community-action-sheet button:focus-visible, .community-more:focus-visible { outline: 2px solid #b81739; outline-offset: 3px; }
@media(max-width:480px) { .post-stat-group { gap: 12px; } .community-sheet-action { min-height: 60px; font-size: 18px; } }
</style>
<dialog class="community-action-sheet" id="communityActionSheet" autofocus aria-label="게시글 메뉴" aria-modal="true">
    <div class="community-sheet-backdrop" aria-hidden="true"></div>
    <div class="community-sheet-stage">
    <div class="community-sheet-panel">
    <button type="button" class="community-sheet-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기" title="아래로 드래그하여 닫기"></button>
    <div class="community-sheet-body">
    <div class="community-sheet-options">
    <button type="button" class="community-sheet-action" id="communityCopyLink"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>링크 복사</button>
    <button type="button" class="community-sheet-action" id="communityBlockUser"><i class="bi bi-ban" aria-hidden="true"></i>차단</button>
    <button type="button" class="community-sheet-action report" id="communityReportPost"><i class="bi bi-bell" aria-hidden="true"></i>신고</button>
    </div>
    </div>
    </div>
    <div class="community-sheet-footer">
        <button type="button" class="community-sheet-cancel" onclick="closeCommunityActions()">취소</button>
    </div>
    </div>
</dialog>
<script>
const communityActionSheet = document.getElementById('communityActionSheet');
const communitySheetPanel = communityActionSheet.querySelector('.community-sheet-panel');
const communitySheetBackdrop = communityActionSheet.querySelector('.community-sheet-backdrop');
const communitySheetBody = communityActionSheet.querySelector('.community-sheet-body');
const communitySheetFooter = communityActionSheet.querySelector('.community-sheet-footer');
let communityActionTarget = null;
const communityMotion = (() => {
    const backdropOpacity = .15;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let screenHeight = 0, maxHeight = 0, y = 0;
    let panelAnimation, backdropAnimation, footerAnimation, closing = null, savedPage = null, pointer = null;
    let suppressClick = false;
    const translate = value => 'translate3d(0,' + value + 'px,0)';
    function freezePage() {
        const body = document.body;
        savedPage = { x: window.scrollX, y: window.scrollY, body: body.getAttribute('style'), root: document.documentElement.style.overflow };
        Object.assign(body.style, {position:'fixed', top:-savedPage.y+'px', left:-savedPage.x+'px', width:'100%', overflow:'hidden'});
        document.documentElement.style.overflow = 'hidden';
    }
    function restorePage() {
        if (!savedPage) return;
        const saved = savedPage;
        savedPage = null;
        if (saved.body === null) document.body.removeAttribute('style');
        else document.body.setAttribute('style', saved.body);
        document.documentElement.style.overflow = saved.root;
        const old = document.documentElement.style.scrollBehavior;
        document.documentElement.style.scrollBehavior = 'auto';
        window.scrollTo(saved.x, saved.y);
        document.documentElement.style.scrollBehavior = old;
    }
    function stopAnimation() {
        if (footerAnimation) {
            const transform = getComputedStyle(communitySheetFooter).transform;
            footerAnimation.cancel(); footerAnimation = null;
            communitySheetFooter.style.transform = transform;
        }
        if (panelAnimation) {
            const matrix = new DOMMatrixReadOnly(getComputedStyle(communitySheetPanel).transform);
            y = matrix.m42;
            panelAnimation.cancel(); panelAnimation = null;
            communitySheetPanel.style.transform = translate(y);
        }
        if (backdropAnimation) {
            const opacity = getComputedStyle(communitySheetBackdrop).opacity;
            backdropAnimation.cancel(); backdropAnimation = null;
            communitySheetBackdrop.style.opacity = opacity;
        }
    }
    function sizeBody(visibleHeight) {
        const handleHeight = communityActionSheet.querySelector('.community-sheet-handle').offsetHeight;
        communitySheetBody.style.maxHeight = Math.max(0, visibleHeight - handleHeight - communitySheetFooter.offsetHeight) + 'px';
    }
    function geometry() {
        const viewport = window.visualViewport;
        const viewportHeight = viewport ? viewport.height : window.innerHeight;
        const viewportTop = viewport ? viewport.offsetTop : 0;
        const nav = document.getElementById('bottomNav');
        const navRect = nav ? nav.getBoundingClientRect() : null;
        const navHeight = navRect ? Math.max(0, Math.min(navRect.bottom, viewportTop + viewportHeight) - Math.max(navRect.top, viewportTop)) : 0;
        screenHeight = Math.max(1, viewportHeight - navHeight);
        communityActionSheet.style.setProperty('--community-nav-height', navHeight + 'px');
        communityActionSheet.style.top = viewportTop + 'px';
        communityActionSheet.style.height = viewportHeight + 'px';
        // Use actual content height, never a large panel translated mostly offscreen.
        communitySheetBody.style.maxHeight = 'none';
        const contentHeight = communityActionSheet.querySelector('.community-sheet-handle').offsetHeight
            + communitySheetBody.scrollHeight + communitySheetFooter.offsetHeight;
        maxHeight = Math.min(contentHeight || 340, Math.max(1, screenHeight - 24));
        communitySheetPanel.style.height = maxHeight + 'px';
    }
    async function animateTo(target, duration, opening = false, dismiss = false) {
        stopAnimation();
        const from = y;
        const frames = opening && !reduced.matches
            ? [{transform:translate(from),offset:0,easing:'cubic-bezier(.16,1,.3,1)'},
               {transform:translate(target-3),offset:.86,easing:'ease-out'}, {transform:translate(target),offset:1}]
            : [{transform:translate(from)}, {transform:translate(target)}];
        const ms = reduced.matches ? 0 : duration;
        const animation = communitySheetPanel.animate(frames, {duration:ms, easing:opening?'linear':dismiss?'cubic-bezier(.4,0,1,1)':'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        panelAnimation = animation;
        const footerHeight = communitySheetFooter.offsetHeight;
        if (dismiss) {
            // The footer waits until the moving sheet top reaches its upper edge.
            const contact = maxHeight - footerHeight;
            const hold = Math.max(0, Math.min(.99, (contact - from) / Math.max(1, target - from)));
            const initial = Math.max(0, from - contact);
            footerAnimation = communitySheetFooter.animate([
                {transform:translate(initial), offset:0},
                {transform:translate(initial), offset:hold},
                {transform:translate(footerHeight + 32), offset:1}
            ], {duration:ms, easing:'cubic-bezier(.4,0,1,1)', fill:'forwards'});
        } else {
            footerAnimation = communitySheetFooter.animate([
                {transform:opening ? translate(footerHeight + 32) : getComputedStyle(communitySheetFooter).transform},
                {transform:translate(0)}
            ], {duration:ms, easing:'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        }
        backdropAnimation = communitySheetBackdrop.animate(
            [{opacity:getComputedStyle(communitySheetBackdrop).opacity}, {opacity:dismiss?0:backdropOpacity}],
            {duration:ms, easing:dismiss?'ease-in':'ease-out', fill:'forwards'});
        try { await animation.finished; } catch (_) { return false; }
        if (panelAnimation !== animation) return false;
        communitySheetFooter.style.transform = translate(dismiss ? footerHeight + 32 : 0);
        if (footerAnimation) {footerAnimation.cancel(); footerAnimation = null;}
        y = target;
        communitySheetPanel.style.transform = translate(y);
        animation.cancel(); panelAnimation = null;
        communitySheetBackdrop.style.opacity = dismiss ? '0' : String(backdropOpacity);
        if (backdropAnimation) { backdropAnimation.cancel(); backdropAnimation = null; }
        return true;
    }
    function open(button) {
        if (communityActionSheet.open || closing) return;
        communityActionTarget = {...button.dataset};
        geometry(); freezePage();
        y = maxHeight + 32;
        communitySheetPanel.style.transform = translate(y);
        communitySheetBackdrop.style.opacity = '0';
        communitySheetBody.scrollTop = 0;
        sizeBody(maxHeight);
        // Focus must not scroll the off-screen animated panel into view.
        communityActionSheet.showModal();
        communityActionSheet.focus({preventScroll:true});
        communityActionSheet.scrollTop = 0;
        communityActionSheet.querySelector('.community-sheet-stage').scrollTop = 0;
        geometry();
        sizeBody(maxHeight);
        animateTo(0, 320, true);
    }
    function releasePointer() {
        if (!pointer) return;
        const id = pointer.id; pointer = null;
        if (communitySheetPanel.hasPointerCapture(id)) communitySheetPanel.releasePointerCapture(id);
    }
    function close() {
        if (closing) return closing;
        if (!communityActionSheet.open) return Promise.resolve();
        releasePointer();
        closing = (async () => {
            await animateTo(maxHeight + 32, 240, false, true);
            communityActionSheet.close();
            restorePage();
            closing = null;
        })();
        return closing;
    }
    communityActionSheet.addEventListener('cancel', event => {event.preventDefault(); close();});
    communityActionSheet.addEventListener('click', event => {
        if (event.target !== communitySheetBackdrop && event.target !== communityActionSheet) return;
        event.preventDefault();
        event.stopPropagation();
        close();
    });
    communityActionSheet.addEventListener('close', () => {releasePointer(); restorePage();});
    communitySheetPanel.addEventListener('pointerdown', event => {
        if (closing || pointer || !event.isPrimary || event.button !== 0) return;
        stopAnimation();
        pointer = {id:event.pointerId, start:event.clientY, last:event.clientY, y, height:maxHeight-y,
            samples:[{y:event.clientY,t:event.timeStamp}], moved:false,
            inBody:communitySheetBody.contains(event.target)};
        suppressClick = false;
        // Capture immediately: transferring capture from the handle on the first
        // move emits a bubbling lostpointercapture event in mobile browsers.
        if (event.target.closest('.community-sheet-handle')) {
            communitySheetPanel.setPointerCapture(event.pointerId);
            event.preventDefault();
        }
    });
    communitySheetPanel.addEventListener('pointermove', event => {
        if (!pointer || pointer.id !== event.pointerId) return;
        const delta = event.clientY-pointer.start;
        if (!pointer.moved && Math.abs(delta)<4) return;
        if (!pointer.moved) {
            pointer.moved=true;
            communitySheetPanel.setPointerCapture(event.pointerId);
        }
        suppressClick = true;
        event.preventDefault();
        const step = event.clientY-pointer.last;
        pointer.last = event.clientY;
        // At the upper snap, scroll overflowing content before moving the sheet.
        if (pointer.inBody && (communitySheetBody.scrollTop>0 || (y<=.5 && step<0)) && communitySheetBody.scrollHeight>communitySheetBody.clientHeight) {
            const before = communitySheetBody.scrollTop;
            communitySheetBody.scrollTop -= step;
            if (communitySheetBody.scrollTop !== before) {
                pointer.start=event.clientY; pointer.y=y; pointer.height=maxHeight-y;
                pointer.samples=[{y:event.clientY,t:event.timeStamp}];
                return;
            }
        }
        const raw = pointer.y + event.clientY-pointer.start;
        y = raw<0 ? -Math.min(48, -raw*.18) : raw;
        communitySheetPanel.style.transform = translate(y);
        communitySheetFooter.style.transform = translate(Math.max(0, y - (maxHeight - communitySheetFooter.offsetHeight)));
        communitySheetBackdrop.style.opacity = String(backdropOpacity*Math.min(1,Math.max(0,(maxHeight-y)/pointer.height)));
        pointer.samples.push({y:event.clientY,t:event.timeStamp});
        while (pointer.samples.length > 2 && event.timeStamp-pointer.samples[1].t>100) pointer.samples.shift();
    }, {passive:false});
    function finish(event) {
        if (!pointer || pointer.id!==event.pointerId) return;
        const drag = pointer;
        releasePointer();
        if (!drag.moved) {
            animateTo(0, 180);
            return;
        }
        const sample=drag.samples[0];
        const velocity=event.timeStamp-drag.samples[drag.samples.length-1].t>100 ? 0
            : (event.clientY-sample.y)/Math.max(1,event.timeStamp-sample.t);
        const downward=event.clientY-drag.start;
        if (event.type==='pointerup' && (downward>drag.height*.25 || (downward>8 && velocity>.8))) {close(); return;}

        sizeBody(maxHeight);
        animateTo(0, 240);
    }
    communitySheetPanel.addEventListener('pointerup', finish);
    communitySheetPanel.addEventListener('pointercancel', finish);
    communitySheetPanel.addEventListener('lostpointercapture', event => {
        // Ignore capture changes bubbling from the handle or an action button.
        if (event.target !== communitySheetPanel) return;
        if (pointer && !communitySheetPanel.hasPointerCapture(pointer.id)) finish(event);
    });
    communitySheetPanel.addEventListener('click', event => {
        if (suppressClick) {event.preventDefault();event.stopImmediatePropagation();suppressClick=false;}
    }, true);
    communityActionSheet.querySelector('.community-sheet-handle').addEventListener('keydown', event => {
        if (!closing && event.key === 'ArrowDown') {event.preventDefault(); close();}
    });
    function resize() {
        if (!communityActionSheet.open || closing || pointer) return;
        stopAnimation(); geometry();
        y=0;
        communitySheetPanel.style.transform=translate(y);
        communitySheetFooter.style.transform=translate(0);
        sizeBody(maxHeight);
    }
    window.addEventListener('resize',resize);
    if(window.visualViewport) {window.visualViewport.addEventListener('resize',resize); window.visualViewport.addEventListener('scroll',resize);}
    return {open,close};
})();
function openCommunityActions(button) { communityMotion.open(button); }
function closeCommunityActions() { return communityMotion.close(); }
document.getElementById('communityCopyLink').addEventListener('click', async function() {
    const url = new URL(communityActionTarget.url, location.href).href;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
        } else {
            const field = document.createElement('textarea');
            field.value = url;
            field.style.position = 'absolute';
            field.style.opacity = '0';
            communityActionSheet.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();
            if (!copied) throw new Error('Clipboard unavailable');
        }
        await closeCommunityActions();
        showToast('링크가 복사되었습니다.', 'success');
    } catch (error) { await closeCommunityActions(); showToast('링크 복사에 실패했습니다. 다시 시도해주세요.', 'error'); }
});
document.getElementById('communityBlockUser').addEventListener('click', async function() {
    const target = communityActionTarget;
    await closeCommunityActions();
    if (!CM_USER_ID) { cmLoginPrompt(); return; }
    if (!target.user) { showToast('차단할 사용자 정보가 없습니다.', 'error'); return; }
    if (target.user === String(CM_USER_ID)) { showToast('자기 자신을 차단할 수 없습니다.', 'warning'); return; }
    openBlockModal(target.user, target.author);
});
document.getElementById('communityReportPost').addEventListener('click', async function() {
    const target = communityActionTarget;
    await closeCommunityActions();
    if (!CM_USER_ID) { cmLoginPrompt(); return; }
    openReportModal(target.type, target.id, target.user, target.author);
});
</script>
<style>
.stat-like { cursor: pointer; user-select: none; }
.stat-like:hover .heart-icon { opacity: 0.75; }
.stat-like.liked, .stat-like.liked .heart-icon { color: #AE1932; font-weight: 700; }
</style>
<script>
// 목록에서 바로 좋아요 토글 — 커뮤니티 글(detail_community.php) / 시술후기(api/like_review.php) 분기
var CM_USER_ID = <?php echo json_encode(function_exists('get_gangnamunni_member_id') ? (get_gangnamunni_member_id() ?? '') : ''); ?>;
var CM_CSRF_TOKEN = <?php echo json_encode($community_csrf_token); ?>;
function cmLoginPrompt() {
    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
        location.href = 'auth/login.php?url=' + encodeURIComponent(location.href);
    }
}
// 좋아요 상태 반영: 카운트 + .liked 클래스 + 하트 채움(빨강)/빈하트 전환
function cmApplyLikeState(el, liked, count) {
    var cntEl = el.querySelector('.like-cnt');
    if (cntEl && count !== undefined && count !== null) cntEl.textContent = count;
    el.classList.toggle('liked', !!liked);
    var icon = el.querySelector('i.heart-icon');
    if (icon) icon.className = 'bi ' + (liked ? 'bi-heart-fill' : 'bi-heart') + ' heart-icon';
}
function cmToggleLike(el, id, isReview) {
    if (!CM_USER_ID) { cmLoginPrompt(); return; }
    if (isReview) {
        fetch('api/like_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ review_id: id, csrf_token: CM_CSRF_TOKEN })
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d && d.success) cmApplyLikeState(el, d.liked, d.like_count);
        }).catch(function(){ alert('좋아요 처리 중 오류가 발생했습니다.'); });
    } else {
        var fd = new FormData();
        fd.append('action', 'toggle_like');
        fd.append('post_id', id);
        fd.append('csrf_token', CM_CSRF_TOKEN);
        fetch('detail_community.php?id=' + id + '&_ajax=1', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); }).then(function(d){
                if (d && d.login_required) { cmLoginPrompt(); return; }
                if (d && d.success) cmApplyLikeState(el, d.liked, d.count);
            }).catch(function(){ alert('좋아요 처리 중 오류가 발생했습니다.'); });
    }
}
</script>
<?php
include_once __DIR__ . '/includes/report_block_modal.php';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

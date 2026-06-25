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

// --- 탭 / 정렬 ---
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'latest';
if (!in_array($tab, ['latest', 'popular', 'review'])) $tab = 'latest';

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

if ($table_exists) {
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

$page_title = '커뮤니티';
$show_search = false;

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
/* === 커뮤니티 Figma 디자인 === */
.community-wrap { max-width: 640px; margin: 0 auto; padding: 0 0 70px; }

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
.post-item a { text-decoration: none; color: inherit; display: flex; gap: 14px; }
.post-content-area { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.post-cat-tag { display: inline-block; padding: 2px 8px; background: #FFEEEE; color: #AE1932; font-size: 13px; font-weight: 500; border-radius: 4px; margin-bottom: 6px; align-self: flex-start; }
[data-bs-theme="dark"] .post-cat-tag { background: #FFEEEE; color: #AE1932; }
.post-title { font-size: 18px; font-weight: 500; color: #000000; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.4; }
[data-bs-theme="dark"] .post-title { color: var(--bs-body-color, #eee); }
.post-preview { font-size: 15px; font-weight: 400; color: #000000; line-height: 1.5; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
[data-bs-theme="dark"] .post-preview { color: var(--bs-body-color, #ddd); }
.post-readmore-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
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
.post-stat-group .heart-icon { color: #AE1932; }
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
/* 검색바 */
.community-search-wrap { padding: 8px 16px; display: flex; align-items: center; }
.community-search-input { width: 439px; height: 60px; padding: 10px 14px; border-bottom: 2px solid #8f8d8d; font-size: 15px; outline: none; box-sizing: border-box; }
.community-search-input::placeholder { font-size: 15px; }
.community-search-btn { width: 190px; height: 60px; border: 2px solid #333; border-radius: 24px; background: #333; color: #ffffff; font-size: 18px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.community-search-btn i { color: #ffffff; }

/* 신고/차단 버튼 */
.post-report-row { display: flex; justify-content: flex-end; gap: 2px; margin-top: -4px; padding-right: 4px; }
.rv-report-btn { background: none; border: none; cursor: pointer; font-size: 14px; padding: 2px 4px; opacity: 0.5; transition: opacity 0.2s; vertical-align: middle; }
.rv-report-btn:hover { opacity: 1; }

@media (max-width: 480px) {
    .post-thumbs { max-width: 35%; }
    .post-thumbs .thumb-overlay { font-size: 13px; }
    .post-title { font-size: 16px; }
    .community-tabs a { font-size: 16px; }
}
';

$page_js = 'function applyCommunitySearch(){var q=document.getElementById("communityKeyword").value.trim();if(q)location.href="hospitals.php?q="+encodeURIComponent(q);}';

include __DIR__ . '/includes/header.php';
?>

    <div class="community-wrap">
        <!-- 섹션 타이틀 -->
        <!-- 검색바 + 검색 버튼 -->
        <div class="community-search-wrap">
            <input type="text" id="communityKeyword" class="community-search-input" value="" placeholder="<?php echo term('service', '시술'); ?>, <?php echo term('business', '병원'); ?> 이름을 검색해보세요" onkeypress="if(event.key==='Enter')applyCommunitySearch()">
            <button id="communitySearchBtn" class="community-search-btn" onclick="applyCommunitySearch()">
                <i class="bi bi-geo-alt"></i> 지역 선택
            </button>
        </div>

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
                $preview = mb_substr(strip_tags($post['content'] ?? ''), 0, 100);
                $link = ($post['id'] > 0) ? 'detail_community.php?id=' . $post['id'] : '#';
                $time_text = community_time_display($post['created_at'] ?? '', $post['updated_at'] ?? null);
                $author = htmlspecialchars($post['author_name'] ?? '익명');
                $raw_author = addslashes($post['author_name'] ?? '익명');
                $post_mb_id = addslashes($post['mb_id'] ?? '');
                $initial = mb_substr($author, 0, 1);
            ?>
            <li class="post-item">
                <a href="<?php echo $link; ?>">
                    <div class="post-content-area">
                        <span class="post-cat-tag"><?php echo htmlspecialchars($post['category']); ?></span>
                        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        <div class="post-preview"><?php echo htmlspecialchars($preview); ?></div>
                        <div class="post-readmore-row">
                            <?php if (mb_strlen(strip_tags($post['content'] ?? '')) > 100): ?>
                            <span class="post-readmore">...더보기</span>
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
                                <span class="stat"><i class="bi bi-heart-fill heart-icon"></i> <?php echo intval($post['like_count'] ?? 0); ?></span>
                                <span class="stat"><i class="bi bi-chat-square-text"></i> <?php echo intval($post['comment_count'] ?? 0); ?></span>
                                <span class="stat"><i class="bi bi-eye"></i> <?php echo intval($post['view_count'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <!-- <div class="post-report-row">
                    <button class="rv-report-btn" onclick="openReportModal('community', <?php echo intval($post['id']); ?>, '<?php echo $post_mb_id; ?>', '<?php echo $raw_author; ?>')" title="신고">&#9873;</button>
                    <?php if (!empty($post['mb_id'])): ?>
                    <button class="rv-report-btn" onclick="openBlockModal('<?php echo $post_mb_id; ?>', '<?php echo $raw_author; ?>')" title="차단">&#128683;</button>
                    <?php endif; ?>
                </div> -->
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
    <button class="fab-write" title="글쓰기" onclick="alert('로그인이 필요합니다.'); location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME'], 2),'/'); ?>/bbs/login.php?url=<?php echo urlencode('/hairwang/gangnamunni/community.php'); ?>';"><i class="bi bi-pencil-square"></i></button>
    <?php endif; ?>

<?php
include __DIR__ . '/includes/report_block_modal.php';
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

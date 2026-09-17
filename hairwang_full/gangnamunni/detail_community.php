<?php


// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_blocking.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/community_comment_likes.php';

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$requested_post_id = intval($request_method === 'POST' ? ($_POST['post_id'] ?? $_GET['id'] ?? 0) : ($_GET['id'] ?? 0));
$visibility_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_community_posts WHERE id=? AND status='active' AND " . community_content_condition($connect, "mb_id"));
mysqli_stmt_bind_param($visibility_stmt, 'i', $requested_post_id);
mysqli_stmt_execute($visibility_stmt);
$public_post = mysqli_fetch_assoc(mysqli_stmt_get_result($visibility_stmt));
mysqli_stmt_close($visibility_stmt);
if (!$public_post) {
    http_response_code(404);
    exit('공개된 게시글을 찾을 수 없습니다.');
}

if ($request_method === 'POST' && ($_POST['action'] ?? '') === 'set_comment_like') {
    community_comment_like_request($connect, $requested_post_id);
}

if ($request_method === 'POST' && in_array($_POST['action'] ?? '', ['delete', 'comment_submit'], true)
    && !validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('요청이 만료되었습니다. 새로고침 후 다시 시도해주세요.');
}


if ($request_method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = intval($_POST['post_id'] ?? 0);
    if ($del_id > 0 && is_gangnamunni_logged_in()) {
        $del_member = get_gangnamunni_member();
        $del_mb_id = $del_member['mb_id'] ?? '';
        if (!empty($del_mb_id)) {
            $del_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_community_posts SET status='deleted' WHERE id=? AND mb_id=?");
            mysqli_stmt_bind_param($del_stmt, 'is', $del_id, $del_mb_id);
            mysqli_stmt_execute($del_stmt);
            $del_affected = mysqli_stmt_affected_rows($del_stmt);
            mysqli_stmt_close($del_stmt);
            if ($del_affected > 0) {
                echo '<script>alert("게시글이 삭제되었습니다.");location.href="community.php";</script>';
                exit;
            }
        }
    }
    echo '<script>alert("삭제할 수 없습니다.");history.back();</script>';
    exit;
}

if ($request_method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    header('Content-Type: application/json');
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '유효하지 않은 요청입니다.']);
        exit;
    }
    $like_post_id = intval($_POST['post_id'] ?? 0);
    if ($like_post_id <= 0) { echo json_encode(['success' => false, 'message' => 'invalid post id']); exit; }
    if (!is_gangnamunni_logged_in()) { echo json_encode(['login_required' => true]); exit; }
    $lm = get_gangnamunni_member();
    $lm_id = $lm['mb_id'] ?? '';
    if (empty($lm_id)) { echo json_encode(['login_required' => true]); exit; }
    // 테이블 보장 — [버그픽스 2026-06-12] community.php가 생성한 실제 스키마(user_id)와 통일
    mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `gangnamunni_community_likes` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `post_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `user_id` VARCHAR(100) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_post_user` (`post_id`, `user_id`),
        KEY `idx_post_id` (`post_id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // 토글 — [버그픽스 2026-06-12] mb_id → user_id (실 테이블 컬럼 불일치로 prepare 실패하던 문제)
    $chk = mysqli_prepare($connect, "SELECT id FROM gangnamunni_community_likes WHERE post_id=? AND user_id=?");
    mysqli_stmt_bind_param($chk, 'is', $like_post_id, $lm_id);
    mysqli_stmt_execute($chk);
    $chk_res = mysqli_stmt_get_result($chk);
    $existed = $chk_res ? mysqli_fetch_assoc($chk_res) : null;
    mysqli_stmt_close($chk);
    if ($existed) {
        $del = mysqli_prepare($connect, "DELETE FROM gangnamunni_community_likes WHERE post_id=? AND user_id=?");
        mysqli_stmt_bind_param($del, 'is', $like_post_id, $lm_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        $liked = false;
    } else {
        $ins = mysqli_prepare($connect, "INSERT IGNORE INTO gangnamunni_community_likes (post_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, 'is', $like_post_id, $lm_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        $liked = true;
    }
    // 카운트
    $cnt_r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_community_likes WHERE post_id={$like_post_id}");
    $cnt = $cnt_r ? (int)mysqli_fetch_assoc($cnt_r)['c'] : 0;
    // [버그픽스 2026-06-12] 재로딩 시 표시 일관성을 위해 posts.like_count 동기화
    mysqli_query($connect, "UPDATE gangnamunni_community_posts SET like_count = {$cnt} WHERE id = {$like_post_id}");
    echo json_encode(['success' => true, 'liked' => $liked, 'count' => $cnt]);
    exit;
}

require_once __DIR__ . '/includes/community_comments.php';
if ($request_method === 'POST' && ($_POST['action'] ?? '') === 'comment_submit') {
    community_comment_submit($connect, $requested_post_id);
}

// 게시글 ID
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    header('Location: community.php');
    exit;
}

// DB 테이블 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_posts'");
$table_exists = $table_check && mysqli_num_rows($table_check) > 0;

$post = null;

// DB에서 게시글 조회
if ($table_exists) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_community_posts WHERE id = ? AND status='active'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $post = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}

// [Track A1 - 2026-06-12] 데모 데이터 폴백 제거 — 미존재 글은 아래에서 목록으로 리다이렉트

// 게시글 없으면 목록으로 리다이렉트
if (!$post) {
    header('Location: community.php');
    exit;
}

// 조회수 증가 (DB 게시글인 경우)
if ($table_exists && isset($post['view_count'])) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_community_posts SET view_count = view_count + 1 WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// 댓글 로드 (DB 테이블 있는 경우)
$comments = [];
$comments_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_comments'");
if ($comments_table_check && mysqli_num_rows($comments_table_check) > 0) {
    $thread_table = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_comment_threads'");
    $has_threads = $thread_table && mysqli_num_rows($thread_table) > 0;
    $sql = $has_threads
        ? "SELECT c.*, COALESCE(t.parent_id, 0) AS parent_id FROM gangnamunni_community_comments c LEFT JOIN gangnamunni_community_comment_threads t ON t.comment_id=c.id WHERE c.post_id=? ORDER BY c.created_at ASC, c.id ASC"
        : "SELECT c.*, 0 AS parent_id FROM gangnamunni_community_comments c WHERE c.post_id=? ORDER BY c.created_at ASC, c.id ASC";
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        $cr = mysqli_stmt_get_result($stmt);
        if ($cr) {
            while ($row = mysqli_fetch_assoc($cr)) {
                $comments[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$hidden_authors = community_blocked_ids($connect, (string)($_SESSION["ss_mb_id"] ?? ""));
$comments = array_values(array_filter($comments, fn($comment) => !in_array((string)($comment["mb_id"] ?? ""), $hidden_authors, true)));
$comment_total = count($comments);
$comments = community_comments_in_thread_order($comments);

// 헤더 변수 설정
$page_title = '커뮤니티';
$show_search = false;

$page_css = '
.main-content { padding: 0; }
.page-header { position: relative; top: 0; height: 62px; display: flex; align-items: center; justify-content: center; padding: 0 20px; background: var(--bs-body-bg, #fff); }
.back-btn { position: absolute; left: 8px; width: 36px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 23px; color: inherit; text-decoration: none; }
.page-title { font-size: 18px; font-weight: 600; }
.post-detail-container { box-sizing: border-box; width: 100%; max-width: 640px; margin: 0 auto; padding: 8px 20px 170px; color: var(--bs-body-color, #000); }
.post-category-badge { display: inline-block; padding: 2px 9px; background: #ffeeef; color: #b81739; font-size: 12px; margin-bottom: 14px; }
.post-detail-title { font-size: 18px; font-weight: 600; line-height: 1.4; margin: 0 0 8px; }
.post-detail-content { font-size: 15px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.post-detail-content.expanded { display: block; }
.post-readmore-row { display: flex; align-items: center; justify-content: space-between; margin: 6px 0; font-size: 12px; }
.post-readmore-row button { padding: 0; background: none; border: 0; color: inherit; cursor: pointer; }
.post-readmore-row time { margin-left: auto; }
.post-photo-grid { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; border-radius: 8px; overflow: hidden; margin-bottom: 10px; }
.post-photo-grid a { display: block; aspect-ratio: 1 / 1; background: var(--bs-tertiary-bg, #f5f5f5); overflow: hidden; }
.post-photo-grid a:only-child { width: 50%; grid-column: 1 / -1; border-radius: inherit; }
.post-photo-grid img { display: block; width: 100%; height: 100%; object-fit: cover; }
.post-detail-meta { display: flex; align-items: center; gap: 7px; padding: 0 0 22px; border-bottom: 1px solid #ddd; margin-bottom: 0; min-width: 0; }
.author-avatar { width: 35px; height: 35px; border-radius: 50%; background: #d9d9d9; flex-shrink: 0; overflow: hidden; }
.author-avatar img { width: 100%; height: 100%; object-fit: cover; }
.post-detail-author { font-size: 14px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.detail-stats { margin-left: auto; display: flex; align-items: center; gap: 28px; font-size: 12px; flex-shrink: 0; }
.detail-stat { display: inline-flex; align-items: center; gap: 9px; text-decoration: none; color: inherit; white-space: nowrap; }
button.detail-stat { border: 0; padding: 0; background: transparent; font: inherit; cursor: pointer; }
.detail-stat i { font-size: 23px; }
.detail-stat.liked { color: #b81739; }
.comments-title { font-size: 15px; font-weight: 500; padding: 15px 0; border-bottom: 1px solid #ddd; margin-bottom: 4px; }
.comment-item { padding: 14px 0 9px; }
.comment-author-row { display: flex; align-items: center; gap: 6px; min-width: 0; }
.comment-author { font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.comment-age { color: #aaa; font-size: 12px; white-space: nowrap; }
.comment-date { margin-left: auto; font-size: 12px; white-space: nowrap; }
.comment-text { margin: 8px 0 7px 40px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.comment-item { scroll-margin-top: 16px; }
.comment-item.is-reply { margin-left: 40px; }
.comment-item.is-reply .author-avatar { width: 25px; height: 25px; flex: 0 0 25px; }
.comment-item.is-reply .comment-text, .comment-item.is-reply .comment-tools { margin-left: 31px; }
.comment-reply-target { flex: 0 0 100%; display: flex; justify-content: space-between; align-items: center; color: #b81739; font-size: 12px; }
.comment-reply-target[hidden] { display: none; }
.comment-reply-target button { border: 0; background: transparent; color: inherit; padding: 6px; }
.comment-form { flex-wrap: wrap; }
.comment-tools { display: flex; align-items: center; gap: 20px; margin-left: 40px; }
.comment-like { display: inline-flex; align-items: center; gap: 8px; padding: 2px 0; border: 0; background: none; color: inherit; font-size: 12px; cursor: pointer; }
.comment-like i { font-size: 19px; }
.comment-like.liked { color: #b81739; }
.comment-like:disabled { opacity: .55; cursor: wait; }
.comment-reply { display: inline-flex; align-items: center; gap: 8px; padding: 0; border: 0; background: none; font-size: 12px; color: inherit; cursor: pointer; }
.comment-reply i { font-size: 16px; }
/* Keep the composer above the shared navigation, even on short threads. */
.comment-form { position: fixed; left: 50%; transform: translateX(-50%); bottom: var(--comment-bottom, 77px); box-sizing: border-box; width: 100%; max-width: 640px; margin: 0; display: flex; align-items: center; gap: 12px; padding: 12px 20px; background: var(--bs-body-bg, #fff); z-index: 1000; }
.comment-input { box-sizing: border-box; flex: 1; min-width: 0; height: 40px; padding: 8px 14px; border: 1px solid #aaa; border-radius: 10px; font-size: 14px; color: inherit; background: var(--bs-body-bg, #fff); }
.comment-input::placeholder { color: #aaa; }
.comment-submit { border: 0; border-radius: 10px; height: 40px; padding: 0 23px; background: #b81739; color: white; font-size: 14px; font-weight: 600; cursor: pointer; }
.no-comments { text-align: center; color: #999; padding: 28px 0; font-size: 14px; }
.post-detail-container :focus-visible, .back-btn:focus-visible { outline: 2px solid #b81739; outline-offset: 3px; }
.post-detail-container .comment-input:focus { border-color: #aaa; outline: none; box-shadow: none; }
.share-copy-toast { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%); padding: 10px 16px; border-radius: 20px; background: #222; color: #fff; opacity: 0; pointer-events: none; z-index: 10000; }
.share-copy-toast.is-visible { opacity: 1; }
@media (max-width: 480px) {
    .detail-stats { gap: 12px; font-size: 11px; }
    .detail-stat { gap: 4px; }
    .detail-stat i { font-size: 20px; }
    .post-detail-author { font-size: 13px; }
    .comment-author { max-width: 105px; font-size: 13px; }
    .comment-age, .comment-date { font-size: 10px; }
    .comment-input { font-size: 16px; }
}
@media (max-width: 360px) { .detail-stats { gap: 8px; } .author-avatar { width: 30px; height: 30px; } .comment-author { max-width: 80px; } }
';

// Escape JSON arguments for HTML event attributes as well as JavaScript.
function community_js_arg($value) {
    return htmlspecialchars(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
}
function community_comment_age($date) {
    $seconds = max(0, time() - (strtotime($date) ?: time()));
    if ($seconds < 60) return '방금 전';
    if ($seconds < 3600) return floor($seconds / 60) . '분 전';
    if ($seconds < 86400) return floor($seconds / 3600) . '시간 전';
    return floor($seconds / 86400) . '일 전';
}
$post_author_display = $post['author_name'] ?? $post['author'] ?? '익명';
$current_member = is_gangnamunni_logged_in() ? get_gangnamunni_member() : [];
$is_author = !empty($current_member['mb_id']) && $current_member['mb_id'] === ($post['mb_id'] ?? '');
$detail_csrf = generateCsrfToken();
$comment_likes = $comments ? community_comment_like_counts($connect, $post_id, (string)($current_member['mb_id'] ?? '')) : [];
$post_liked = false;
if (!empty($current_member['mb_id'])) {
    $likes_table = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_likes'");
    if ($likes_table && mysqli_num_rows($likes_table)) {
        $liked_stmt = mysqli_prepare($connect, 'SELECT id FROM gangnamunni_community_likes WHERE post_id=? AND user_id=?');
        mysqli_stmt_bind_param($liked_stmt, 'is', $post_id, $current_member['mb_id']);
        mysqli_stmt_execute($liked_stmt);
        $post_liked = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($liked_stmt));
        mysqli_stmt_close($liked_stmt);
    }
}

// 공통 헤더 include
$hide_admin_inline_edit = true; // 이 페이지에선 관리자 인라인 편집 상단바(ae-topbar) 숨김
$hide_global_nav = true;        // 이 페이지에선 글로벌 네비게이션(global_nav) 숨김
include __DIR__ . '/includes/header.php';
?>
    <!-- 페이지 헤더 -->
    <div class="page-header">
        <a href="community.php" class="back-btn"><i class="bi bi-chevron-left"></i></a>
        <div class="page-title">커뮤니티 댓글</div>
    </div>
    <div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite">링크가 복사되었습니다.</div>

    <div class="post-detail-container">
        <!-- 카테고리 -->
        <span class="post-category-badge"><?php echo htmlspecialchars($post['category']); ?></span>

        <!-- 제목 -->
        <h1 class="post-detail-title"><?php echo htmlspecialchars($post['title']); ?></h1>

        <div class="post-detail-content" id="postContent"><?php echo htmlspecialchars($post['content'] ?? ''); ?></div>
        <div class="post-readmore-row">
            <button type="button" id="postReadMore" hidden aria-expanded="false" aria-controls="postContent">...더보기</button>
            <time><?php echo date('y.m.d', strtotime($post['created_at'])); ?></time>
        </div>
        <?php $post_photos = json_decode($post['photos'] ?? '[]', true); if (is_array($post_photos) && $post_photos): ?>
        <div class="post-photo-grid">
            <?php foreach ($post_photos as $photo_url): ?>
            <?php if (!is_string($photo_url) || !preg_match('~^(?:/(?!/)|https?://)~i', $photo_url)) continue; ?>
            <a href="<?php echo htmlspecialchars($photo_url, ENT_QUOTES); ?>" target="_blank" rel="noopener"><img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES); ?>" alt="게시글 첨부 사진" loading="lazy"></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-detail-meta">
            <span class="author-avatar" aria-hidden="true"><?php if (!empty($post['author_avatar'])): ?><img src="<?php echo htmlspecialchars($post['author_avatar'], ENT_QUOTES); ?>" alt=""><?php endif; ?></span>
            <span class="post-detail-author"><?php echo htmlspecialchars($post_author_display); ?></span>
            <div class="detail-stats">
                <button type="button" class="detail-stat btn-like <?php echo $post_liked ? 'liked' : ''; ?>" aria-label="좋아요" aria-pressed="<?php echo $post_liked ? 'true' : 'false'; ?>" onclick="likePost(<?php echo $post_id; ?>)"><i class="bi <?php echo $post_liked ? 'bi-heart-fill' : 'bi-heart'; ?>"></i><span class="like-count"><?php echo number_format($post['like_count'] ?? 0); ?></span></button>
                <a href="#comments" class="detail-stat" aria-label="댓글"><i class="bi bi-chat-square-text"></i><?php echo number_format(count($comments)); ?></a>
                <span class="detail-stat" aria-label="조회수"><i class="bi bi-eye"></i><?php echo number_format($post['view_count'] ?? 0); ?></span>
                <button type="button" class="community-more" aria-label="게시글 메뉴" aria-haspopup="dialog"
                    data-id="<?php echo (int)$post_id; ?>" data-type="community"
                    data-user="<?php echo htmlspecialchars($post['mb_id'] ?? '', ENT_QUOTES); ?>"
                    data-author="<?php echo htmlspecialchars($post_author_display, ENT_QUOTES); ?>"
                    data-url="detail_community.php?id=<?php echo (int)$post_id; ?>"
                    data-owner="<?php echo $is_author ? 'true' : 'false'; ?>"
                    data-csrf="<?php echo htmlspecialchars($detail_csrf, ENT_QUOTES); ?>"
                    onclick="openCommunityActions(this)"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
            </div>
        </div>

        <!-- 댓글 섹션 -->
        <div class="comments-section" id="comments">
            <div class="comments-title">댓글 <?php echo $comment_total; ?></div>

            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment):
                    $cmt_author = htmlspecialchars($comment['author'] ?? $comment['author_name'] ?? '익명');
                    $cmt_id = intval($comment['id'] ?? 0);
                ?>
                <div class="comment-item<?php echo !empty($comment['parent_id']) ? ' is-reply' : ''; ?>" id="comment-<?php echo $cmt_id; ?>">
                    <div class="comment-author-row">
                        <span class="author-avatar" aria-hidden="true"></span>
                        <span class="comment-author"><?php echo $cmt_author; ?></span>
                        <span class="comment-age"><?php echo community_comment_age($comment['created_at']); ?></span>
                        <time class="comment-date"><?php echo date('y.m.d', strtotime($comment['created_at'])); ?></time>
                        <button type="button" class="community-more" aria-label="댓글 메뉴" aria-haspopup="dialog"
                            data-type="comment" data-id="<?php echo $cmt_id; ?>"
                            data-user="<?php echo htmlspecialchars($comment['mb_id'] ?? '', ENT_QUOTES); ?>"
                            data-author="<?php echo htmlspecialchars($comment['author'] ?? $comment['author_name'] ?? '익명', ENT_QUOTES); ?>"
                            data-url="detail_community.php?id=<?php echo (int)$post_id; ?>#comment-<?php echo $cmt_id; ?>"
                            onclick="openCommunityActions(this)"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                    </div>
                    <div class="comment-text"><?php echo htmlspecialchars($comment['content']); ?></div>
                    <div class="comment-tools">
                        <?php $comment_like = $comment_likes[$cmt_id] ?? ['liked' => false, 'count' => 0]; ?>
                        <button type="button" class="comment-like <?php echo $comment_like['liked'] ? 'liked' : ''; ?>"
                            aria-label="댓글 좋아요" aria-pressed="<?php echo $comment_like['liked'] ? 'true' : 'false'; ?>"
                            onclick="toggleCommentLike(this, <?php echo $cmt_id; ?>)">
                            <i class="bi <?php echo $comment_like['liked'] ? 'bi-heart-fill' : 'bi-heart'; ?>" aria-hidden="true"></i>
                            <span class="comment-like-count"><?php echo (int)$comment_like['count'] > 0 ? number_format((int)$comment_like['count']) : ''; ?></span>
                        </button>
                        <?php if (empty($comment['parent_id'])): ?>
                        <button type="button" class="comment-reply" onclick="focusComment(<?php echo $cmt_id; ?>, <?php echo community_js_arg($comment['author'] ?? $comment['author_name'] ?? '익명'); ?>)"><i class="bi bi-chat-square-text" aria-hidden="true"></i>댓글쓰기</button>
                        <?php endif; ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-comments">아직 댓글이 없습니다. 첫 댓글을 남겨보세요!</div>
            <?php endif; ?>

            <!-- [1차 검수 #21 - 2026-05-28] 댓글 작성 폼 -->
            <?php if (is_gangnamunni_logged_in()): ?>
            <form method="POST" action="detail_community.php?id=<?php echo $post_id; ?>" class="comment-form" id="comment-form">
                <div class="comment-reply-target" id="comment-reply-target" hidden><span></span><button type="button" onclick="cancelCommentReply()" aria-label="답글 취소">취소</button></div>
                <input type="hidden" name="parent_id" value="0" id="comment-parent-id">
                <input type="hidden" name="action" value="comment_submit">
                <input type="hidden" name="csrf_token" value="<?php echo $detail_csrf; ?>">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="text" name="content" class="comment-input" aria-label="댓글" placeholder="댓글을 입력하세요." required maxlength="500">
                <button type="submit" class="comment-submit">등록</button>
            </form>
            <?php else: ?>
            <div class="comment-form" style="justify-content:center;">
                <a href="auth/login.php?url=<?php echo urlencode('/hairwang/app/detail_community.php?id=' . $post_id); ?>" style="color:#b81739; font-weight:600; text-decoration:none;">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인하고 댓글 작성하기
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php
$page_js = '
var detailCsrf = ' . json_encode($detail_csrf) . ';
async function toggleCommentLike(button, commentId) {
    if (button.disabled) return;
    button.disabled = true;
    const data = new FormData();
    data.append("action", "set_comment_like");
    data.append("post_id", "' . (int)$post_id . '");
    data.append("comment_id", commentId);
    data.append("csrf_token", detailCsrf);
    data.append("liked", button.getAttribute("aria-pressed") === "true" ? "0" : "1");
    try {
        const response = await fetch("detail_community.php?id=' . (int)$post_id . '", {method:"POST", body:data, credentials:"same-origin"});
        const result = await response.json();
        if (result.login_required) {
            if (confirm("로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?")) location.href = "auth/login.php?url=" + encodeURIComponent(location.href);
            return;
        }
        if (!response.ok || !result.success) throw new Error(result.message || "좋아요 처리에 실패했습니다.");
        button.classList.toggle("liked", result.liked);
        button.setAttribute("aria-pressed", String(result.liked));
        button.querySelector("i").className = "bi " + (result.liked ? "bi-heart-fill" : "bi-heart");
        button.querySelector(".comment-like-count").textContent = result.count ? Number(result.count).toLocaleString("en-US") : "";
    } catch (error) {
        showToast("좋아요 처리에 실패했습니다. 다시 시도해주세요.", "error");
    } finally { button.disabled = false; }
}

// Show the toggle only when the rendered text exceeds the three-line preview.
(function() {
    var content = document.getElementById("postContent");
    var button = document.getElementById("postReadMore");
    if (!content || !button) return;
    function setExpanded(expanded) {
        content.classList.toggle("expanded", expanded);
        button.setAttribute("aria-expanded", String(expanded));
        button.textContent = expanded ? "접기" : "...더보기";
    }
    function updateReadMore() {
        var expanded = content.classList.contains("expanded");
        content.classList.remove("expanded");
        var overflowing = content.scrollHeight > content.clientHeight + 1;
        button.hidden = !overflowing;
        setExpanded(overflowing && expanded);
    }
    button.addEventListener("click", function() {
        setExpanded(!content.classList.contains("expanded"));
    });
    updateReadMore();
    window.addEventListener("resize", updateReadMore);
    if (document.fonts) document.fonts.ready.then(updateReadMore);
    if (window.ResizeObserver) {
        var previousWidth = content.getBoundingClientRect().width;
        new ResizeObserver(function(entries) {
            var width = entries[0].contentRect.width;
            if (width !== previousWidth) {
                previousWidth = width;
                updateReadMore();
            }
        }).observe(content);
    }
})();

// Follow the actual navigation height and the visible area above a mobile keyboard.
(function() {
    var composer = document.querySelector(".comment-form");
    var nav = document.getElementById("bottomNav");
    if (!composer) return;
    function positionComposer() {
        var navHeight = nav ? nav.getBoundingClientRect().height : 0;
        var viewport = window.visualViewport;
        var keyboardInset = viewport && viewport.scale === 1
            ? Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop) : 0;
        composer.style.setProperty("--comment-bottom", Math.max(navHeight, keyboardInset) + "px");
        var container = document.querySelector(".post-detail-container");
        if (container) container.style.paddingBottom = (navHeight + composer.offsetHeight + 24) + "px";
    }
    positionComposer();
    window.addEventListener("resize", positionComposer);
    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", positionComposer);
        window.visualViewport.addEventListener("scroll", positionComposer);
    }
    if (window.ResizeObserver) new ResizeObserver(positionComposer).observe(composer);
    if (nav && window.ResizeObserver) new ResizeObserver(positionComposer).observe(nav);
})();
function cancelCommentReply() {
    document.getElementById("comment-parent-id").value = "0";
    document.getElementById("comment-reply-target").hidden = true;
    document.querySelector(".comment-input").placeholder = "댓글을 입력하세요.";
}
function focusComment(commentId, author) {
    var input = document.querySelector(".comment-input");
    if (input) {
        document.getElementById("comment-parent-id").value = commentId;
        var target = document.getElementById("comment-reply-target");
        target.querySelector("span").textContent = author + "님에게 답글 작성 중";
        target.hidden = false;
        input.placeholder = "답글을 입력하세요.";
        input.focus();
    } else { location.href = "auth/login.php?url=" + encodeURIComponent(location.href); }
}
var commentForm = document.getElementById("comment-form");
if (commentForm) commentForm.addEventListener("submit", function(event) {
    var input = commentForm.querySelector(".comment-input");
    if (!input.value.trim() || commentForm.dataset.submitting) { event.preventDefault(); return; }
    commentForm.dataset.submitting = "true";
    commentForm.querySelector(".comment-submit").disabled = true;
});
window.addEventListener("pageshow", function() {
    if (!commentForm) return;
    delete commentForm.dataset.submitting;
    commentForm.querySelector(".comment-submit").disabled = false;
});
// [1차 검수 #17,#18 - 2026-05-28] 좋아요 카운트 미반영 + (수정됨) 표시 버그 픽스
// 기존: alert만 호출 -> DB 미반영 + button type 누락으로 form submit 발생 -> updated_at 변경 -> (수정됨)
function likePost(postId) {
    var fd = new FormData();
    fd.append("action", "toggle_like");
    fd.append("post_id", postId);
    fd.append("csrf_token", detailCsrf);
    fetch("detail_community.php?id=" + postId + "&_ajax=1", { method: "POST", body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res && res.login_required) {
                if (confirm("로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?")) {
                    location.href = "auth/login.php?url=" + encodeURIComponent(location.href);
                }
                return;
            }
            if (res && res.success) {
                var btns = document.querySelectorAll(".btn-like");
                btns.forEach(function(b){ b.classList.toggle("liked", !!res.liked); b.setAttribute("aria-pressed", !!res.liked); b.querySelector("i").className = "bi " + (res.liked ? "bi-heart-fill" : "bi-heart"); b.querySelector(".like-count").textContent = Number(res.count || 0).toLocaleString("en-US"); });
            }
        })
        .catch(function(){ alert("좋아요 처리 중 오류가 발생했습니다."); });
}
function sharePost() {
    var url = window.location.href;
    if (navigator.share) {
        navigator.share({ title: document.title, url: url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert("링크가 복사되었습니다!");
        });
    } else {
        alert("공유 기능을 지원하지 않는 브라우저입니다.");
    }
}
var pageShareToastTimer = null;
function showPageShareToast(message) {
    var toast = document.getElementById("pageShareCopyToast");
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(pageShareToastTimer);
    pageShareToastTimer = window.setTimeout(function () {
        toast.classList.remove("is-visible");
    }, 1800);
}
function fallbackPageShareCopy(text) {
    var textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "");
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.select();
    var copied = document.execCommand("copy");
    document.body.removeChild(textarea);
    return copied;
}
function copyPageShareLink() {
    var shareText = "이거 완전 괜찮은 앱이야!\\n서울미녀\\n" + window.location.href;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shareText).then(function () {
            showPageShareToast("링크가 복사되었습니다.");
        }).catch(function () {
            showPageShareToast(fallbackPageShareCopy(shareText) ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.");
        });
        return;
    }
    showPageShareToast(fallbackPageShareCopy(shareText) ? "링크가 복사되었습니다." : "링크 복사에 실패했습니다.");
}
';

// 신고/차단 모달
include __DIR__ . '/includes/report_block_modal.php';
include_once __DIR__ . '/includes/community_action_sheet.php';

// 공통 푸터 include

include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';

mysqli_close($connect);
?>

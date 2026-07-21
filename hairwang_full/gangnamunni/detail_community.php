<?php
/**
 * ========================================
 * 커뮤니티 게시글 상세 페이지
 * ========================================
 * community.php 에서 게시글 클릭 시 이동
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';

// 변경: 웹 요청은 기존 값 유지, CLI 검증에서는 GET 기본값 사용. 영향 범위: POST 분기 조건의 경고 제거만 수행.
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 삭제 처리 (POST)
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

// [1차 검수 #17 - 2026-05-28] 좋아요 토글 처리 (AJAX POST)
if ($request_method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    header('Content-Type: application/json');
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

// [1차 검수 #21 - 2026-05-28] 댓글 작성 처리 (POST)
if ($request_method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment_submit') {
    $c_post_id = intval($_POST['post_id'] ?? 0);
    $c_content = trim($_POST['content'] ?? '');
    if ($c_post_id > 0 && $c_content !== '' && is_gangnamunni_logged_in()) {
        $c_member = get_gangnamunni_member();
        $c_mb_id = $c_member['mb_id'] ?? '';
        $c_author = $c_member['mb_nick'] ?? $c_member['mb_name'] ?? $c_mb_id;
        if (!empty($c_mb_id)) {
            // 테이블 보장
            mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_community_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                mb_id VARCHAR(50) NOT NULL,
                author VARCHAR(100) DEFAULT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post_id (post_id),
                INDEX idx_mb_id (mb_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $c_stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_community_comments (post_id, mb_id, author, content) VALUES (?, ?, ?, ?)");
            if ($c_stmt) {
                mysqli_stmt_bind_param($c_stmt, 'isss', $c_post_id, $c_mb_id, $c_author, $c_content);
                mysqli_stmt_execute($c_stmt);
                mysqli_stmt_close($c_stmt);
                // 게시글 댓글 수 증가 (DB 게시글인 경우)
                mysqli_query($connect, "UPDATE gangnamunni_community_posts SET comment_count = comment_count + 1 WHERE id = $c_post_id");
            }
            header('Location: detail_community.php?id=' . $c_post_id . '#comments');
            exit;
        }
    }
    echo '<script>alert("댓글 작성에 실패했습니다. 로그인 후 다시 시도해주세요.");history.back();</script>';
    exit;
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
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_community_posts WHERE id = ?");
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
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_community_comments WHERE post_id = ? ORDER BY created_at ASC");
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

// 헤더 변수 설정
$page_title = '커뮤니티';
$show_search = false;

$page_css = '
/* 커뮤니티 상세 페이지 스타일 */
.page-header {
    position: sticky;
    top: 0;
    margin: 0 -16px;
    padding: 14px 16px;
    background: var(--bs-body-bg, #fff);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 99;
}
@media (max-width: 640px) {
    .page-header { margin: 0 -12px; }
}
.back-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
    color: inherit;
    cursor: pointer;
    text-decoration: none;
}
.page-title {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    max-width: 60%;
    text-align: center;
    font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.post-detail-container {
    max-width: 640px;
    margin: 0 auto;
    padding: 24px 16px;
}
.post-category-badge {
    display: inline-block;
    padding: 6px 14px;
    background: #F0EFFF;
    color: #5B5FC7;
    font-size: 13px;
    border-radius: 20px;
    font-weight: 500;
    margin-bottom: 16px;
}
.post-detail-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--bs-body-color, #222);
    line-height: 1.4;
    margin-bottom: 16px;
}
.post-detail-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 20px;
    border-bottom: 1px solid #F0F0F0;
    margin-bottom: 24px;
    font-size: 14px;
    color: #888;
}
.post-detail-author {
    color: #5B5FC7;
    font-weight: 600;
}
.post-detail-content {
    font-size: 16px;
    line-height: 1.8;
    color: var(--bs-body-color, #333);
    white-space: pre-wrap;
    margin-bottom: 32px;
}
.post-actions {
    display: flex;
    gap: 12px;
    padding: 20px 0;
    border-top: 1px solid #F0F0F0;
    border-bottom: 1px solid #F0F0F0;
    margin-bottom: 32px;
}
.post-action-btn {
    flex: 1;
    padding: 14px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.btn-like {
    background: #5B5FC7;
    color: white;
}
.btn-like:hover {
    background: #4A4EB5;
}
.btn-share {
    background: #F5F5F5;
    color: var(--bs-body-color, #333);
}
.btn-share:hover {
    background: #E8E8E8;
}

/* 댓글 섹션 */
.comments-section {
    margin-bottom: 32px;
}
.comments-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    color: #222;
}
.comment-item {
    padding: 16px 0;
    border-bottom: 1px solid #F5F5F5;
}
.comment-author {
    font-weight: 600;
    color: #5B5FC7;
    font-size: 14px;
    margin-bottom: 6px;
}
.comment-text {
    font-size: 15px;
    color: #444;
    line-height: 1.6;
}
.comment-time {
    font-size: 12px;
    color: #BBB;
    margin-top: 6px;
}
.no-comments {
    text-align: center;
    padding: 40px 20px;
    color: #999;
    font-size: 14px;
}

/* 댓글 작성 폼 */
.comment-form {
    display: flex;
    gap: 10px;
    padding: 16px 0;
}
.comment-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #E0E0E0;
    border-radius: 10px;
    font-size: 14px;
    outline: none;
}
.comment-input:focus {
    border-color: #5B5FC7;
}
.comment-submit {
    padding: 12px 20px;
    background: #5B5FC7;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.comment-submit:hover {
    background: #4A4EB5;
}

/* 신고/차단 버튼 */
.rv-report-btn { background: none; border: none; cursor: pointer; font-size: 14px; padding: 2px 4px; opacity: 0.5; transition: opacity 0.2s; vertical-align: middle; }
.rv-report-btn:hover { opacity: 1; }
.comment-author-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.comment-author-row .comment-author { margin-bottom: 0; }

@media (max-width: 480px) {
    .post-detail-title { font-size: 20px; }
    .post-detail-content { font-size: 15px; }
}
';

// 신고/차단용 변수 준비
$post_author_display = $post['author_name'] ?? $post['author'] ?? '익명';
$post_author_raw = addslashes($post_author_display);
$post_mb_id = addslashes($post['mb_id'] ?? '');

// 공통 헤더 include
$hide_admin_inline_edit = true; // 이 페이지에선 관리자 인라인 편집 상단바(ae-topbar) 숨김
$hide_global_nav = true;        // 이 페이지에선 글로벌 네비게이션(global_nav) 숨김
include __DIR__ . '/includes/header.php';
?>
    <!-- 페이지 헤더 -->
    <div class="page-header">
        <a href="community.php" class="back-btn"><i class="bi bi-chevron-left"></i></a>
        <div class="page-title">커뮤니티</div>
    </div>

    <div class="post-detail-container">
        <!-- 카테고리 -->
        <span class="post-category-badge"><?php echo htmlspecialchars($post['category']); ?></span>

        <!-- 제목 -->
        <h1 class="post-detail-title"><?php echo htmlspecialchars($post['title']); ?></h1>

        <!-- 메타 정보 -->
        <div class="post-detail-meta">
            <span class="post-detail-author"><?php echo htmlspecialchars($post_author_display); ?></span>
            <button class="rv-report-btn" onclick="openReportModal('community', <?php echo intval($post_id); ?>, '<?php echo $post_mb_id; ?>', '<?php echo $post_author_raw; ?>')" title="신고">&#9873;</button>
            <?php if (!empty($post['mb_id'])): ?>
            <button class="rv-report-btn" onclick="openBlockModal('<?php echo $post_mb_id; ?>', '<?php echo $post_author_raw; ?>')" title="차단">&#128683;</button>
            <?php endif; ?>
            <span>|</span>
            <span><?php echo htmlspecialchars($post['time_ago'] ?? date('Y-m-d', strtotime($post['created_at']))); ?></span>
            <?php if (isset($post['comments']) && $post['comments'] > 0): ?>
            <span>|</span>
            <span>댓글 <?php echo intval($post['comment_count'] ?? $post['comments'] ?? 0); ?></span>
            <?php endif; ?>
        </div>

        <!-- [1차 검수 #19 - 2026-05-28] 레이아웃 순서 변경: 내용 → 이미지 → 좋아요/공유 -->
        <!-- 내용 -->
        <div class="post-detail-content"><?php echo nl2br(htmlspecialchars($post['content'] ?? $post['preview'] ?? '')); ?></div>

        <?php
        // 사진 갤러리 (액션 버튼 위로 이동)
        $post_photos = [];
        if (!empty($post['photos'])) {
            $decoded_photos = json_decode($post['photos'], true);
            if (is_array($decoded_photos)) $post_photos = $decoded_photos;
        }
        if (!empty($post_photos)):
        ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;width:100vw;margin-left:calc(-50vw + 50%);margin-right:calc(-50vw + 50%);">
            <?php foreach ($post_photos as $photo_url): ?>
            <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="" style="width:100%;height:auto;display:block;cursor:pointer;" onclick="window.open(this.src)">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- [1차 검수 #20 - 2026-05-28] 시술 후기 데이터 항목 노출 -->
        <?php
        // 시술 후기일 때 추가 데이터 (community 카테고리에 시술/평점 등이 있을 경우)
        $is_review_post = (
            ($post['category'] ?? '') === '시술후기' ||
            !empty($post['treatment_category']) ||
            !empty($post['rating'])
        );
        if ($is_review_post):
        ?>
        <div class="review-meta-section" style="background:var(--bs-tertiary-bg);border-radius:10px;padding:12px 16px;margin-bottom:20px;">
            <?php if (!empty($post['treatment_category'])): ?>
            <div style="margin-bottom:6px;"><strong>🏥 시술 카테고리</strong> : <?php echo htmlspecialchars($post['treatment_category']); ?></div>
            <?php endif; ?>
            <?php if (!empty($post['rating'])): ?>
            <div style="margin-bottom:6px;"><strong>⭐ 평점</strong> : <?php echo str_repeat('★', intval($post['rating'])); ?><?php echo str_repeat('☆', 5 - intval($post['rating'])); ?> (<?php echo number_format(floatval($post['rating']), 1); ?>)</div>
            <?php endif; ?>
            <?php if (!empty($post['hospital_name'])): ?>
            <div style="margin-bottom:6px;"><strong>🏥 시술 병원</strong> :
                <?php if (!empty($post['hospital_id'])): ?>
                <a href="detail.php?id=<?php echo intval($post['hospital_id']); ?>"><?php echo htmlspecialchars($post['hospital_name']); ?></a>
                <?php else: ?>
                <?php echo htmlspecialchars($post['hospital_name']); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div style="font-size:.85rem;color:var(--bs-secondary-color);">
                👁 조회 <?php echo intval($post['view_count'] ?? 0); ?>
                · ❤️ 좋아요 <?php echo intval($post['like_count'] ?? 0); ?>
                · 💬 댓글 <?php echo intval($post['comment_count'] ?? $post['comments'] ?? 0); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 액션 버튼 (사진 다음으로 이동) -->
        <div class="post-actions">
            <button type="button" class="post-action-btn btn-like" onclick="likePost(<?php echo $post_id; ?>)">
                ❤️ 좋아요 <?php echo intval($post['like_count'] ?? 0); ?>
            </button>
            <button type="button" class="post-action-btn btn-share" onclick="sharePost()">
                🔗 공유하기
            </button>
        </div>

        <?php
        // 작성자 본인만 수정/삭제 버튼 표시
        $is_author = false;
        if (is_gangnamunni_logged_in() && !empty($post['mb_id'])) {
            $current_member = get_gangnamunni_member();
            $is_author = (($current_member['mb_id'] ?? '') === $post['mb_id']);
        }
        if ($is_author):
        ?>
        <div style="display:flex;gap:10px;margin-bottom:24px;">
            <a href="community_write.php?id=<?php echo $post_id; ?>" style="flex:1;padding:12px;text-align:center;background:var(--bs-tertiary-bg,#f5f5f5);border-radius:10px;text-decoration:none;color:var(--bs-body-color,#333);font-weight:600;font-size:14px;">
                <i class="bi bi-pencil"></i> 수정
            </a>
            <form method="POST" style="flex:1;" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <button type="submit" style="width:100%;padding:12px;text-align:center;background:#FFF0F0;border:none;border-radius:10px;color:#CC0000;font-weight:600;font-size:14px;cursor:pointer;">
                    <i class="bi bi-trash"></i> 삭제
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 댓글 섹션 -->
        <div class="comments-section">
            <div class="comments-title">댓글 <?php echo count($comments); ?>개</div>

            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment):
                    $cmt_author = htmlspecialchars($comment['author'] ?? $comment['author_name'] ?? '익명');
                    $cmt_author_raw = addslashes($comment['author'] ?? $comment['author_name'] ?? '익명');
                    $cmt_mb_id = addslashes($comment['mb_id'] ?? '');
                    $cmt_id = intval($comment['id'] ?? 0);
                ?>
                <div class="comment-item">
                    <div class="comment-author-row">
                        <div class="comment-author"><?php echo $cmt_author; ?></div>
                        <button class="rv-report-btn" onclick="openReportModal('community_comment', <?php echo $cmt_id; ?>, '<?php echo $cmt_mb_id; ?>', '<?php echo $cmt_author_raw; ?>')" title="신고">&#9873;</button>
                        <?php if (!empty($comment['mb_id'])): ?>
                        <button class="rv-report-btn" onclick="openBlockModal('<?php echo $cmt_mb_id; ?>', '<?php echo $cmt_author_raw; ?>')" title="차단">&#128683;</button>
                        <?php endif; ?>
                    </div>
                    <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
                    <div class="comment-time"><?php echo htmlspecialchars($comment['created_at']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-comments">아직 댓글이 없습니다. 첫 댓글을 남겨보세요!</div>
            <?php endif; ?>

            <!-- [1차 검수 #21 - 2026-05-28] 댓글 작성 폼 -->
            <?php if (is_gangnamunni_logged_in()): ?>
            <form method="POST" action="detail_community.php?id=<?php echo $post_id; ?>" class="comment-form" id="comment-form">
                <input type="hidden" name="action" value="comment_submit">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="text" name="content" class="comment-input" placeholder="댓글을 입력하세요..." required maxlength="500">
                <button type="submit" class="comment-submit">등록</button>
            </form>
            <?php else: ?>
            <div class="comment-form" style="justify-content:center;">
                <a href="/hairwang/bbs/login.php?url=<?php echo urlencode('/hairwang/gangnamunni/detail_community.php?id=' . $post_id); ?>" style="color:#5B5FC7; font-weight:600; text-decoration:none;">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인하고 댓글 작성하기
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php
$page_js = '
// [1차 검수 #17,#18 - 2026-05-28] 좋아요 카운트 미반영 + (수정됨) 표시 버그 픽스
// 기존: alert만 호출 -> DB 미반영 + button type 누락으로 form submit 발생 -> updated_at 변경 -> (수정됨)
function likePost(postId) {
    var fd = new FormData();
    fd.append("action", "toggle_like");
    fd.append("post_id", postId);
    fetch("detail_community.php?id=" + postId + "&_ajax=1", { method: "POST", body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res && res.login_required) {
                if (confirm("로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?")) {
                    location.href = "/hairwang/bbs/login.php?url=" + encodeURIComponent(location.href);
                }
                return;
            }
            if (res && res.success) {
                var btns = document.querySelectorAll(".btn-like");
                btns.forEach(function(b){ b.innerHTML = "❤️ 좋아요 " + (res.count || 0); });
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
';

// 신고/차단 모달
include __DIR__ . '/includes/report_block_modal.php';

// 공통 푸터 include
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';

mysqli_close($connect);
?>

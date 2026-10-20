<?php
/**
 * 후기 상세 페이지
 * URL: review_detail.php?id=1
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_blocking.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$review_id = intval($_GET['id'] ?? 0);
if ($review_id <= 0) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// 후기 조회
$review = $db->fetchOne(
    "SELECT r.*, r.rating as review_rating, h.name as hospital_name, h.profile_image_url as hospital_image, h.address as hospital_address, h.rating as hospital_rating, h.review_count as hospital_review_count
     FROM hospital_reviews r
     LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
     WHERE r.id = ? AND r.is_active = 1",
    [$review_id]
);

if ($review && in_array((string)$review["user_id"], community_blocked_ids($connect, (string)($_SESSION["ss_mb_id"] ?? "")), true)) { $review = null; http_response_code(404); }
if (!$review) {
    echo '<p style="text-align:center;padding:60px 20px;color:#999;">' . term('review', '후기') . '를 찾을 수 없습니다.</p>';
    exit;
}

// 이미지
$images = $db->fetchAll(
    "SELECT * FROM review_images WHERE review_id = ? ORDER BY image_order ASC",
    [$review_id]
);

// 댓글
$comments = $db->fetchAll(
    "SELECT * FROM review_comments WHERE review_id = ? AND is_active = 1 ORDER BY created_at ASC",
    [$review_id]
);

// 현재 사용자 좋아요 여부
$current_user_id = '';
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    $member = get_gangnamunni_member();
    $current_user_id = $member['mb_id'] ?? '';
}

$user_liked = false;
if ($current_user_id) {
    $user_liked = (bool)$db->fetchOne(
        "SELECT id FROM review_likes WHERE review_id = ? AND user_id = ?",
        [$review_id, $current_user_id]
    );
}

$base_url = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= term('review') ?> 상세 | <?= site_name() ?></title>
    <?php if (function_exists('generateFaviconLinks')) echo generateFaviconLinks(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <style>
        :root {
            --primary: #AC1830;
            --primary-light: #fce4ec;
            --text: #333;
            --text-sub: #666;
            --text-muted: #999;
            --bg: #f8f8f8;
            --border: #eee;
            --star: #fbbf24;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 640px; margin: 0 auto; background: var(--bs-body-bg, #fff); min-height: 100px; }

        /* 헤더 */
        .detail-header { position: sticky; top: 48px; height: 56px; display: flex; align-items: center; padding: 0 16px; background: var(--bs-body-bg, #fff); }
        @media (min-width: 768px) { .detail-header { top: 56px; } }
        .detail-header a { color: var(--text); text-decoration: none; font-size: calc(24px); margin-right: 12px; position: relative; z-index: 1; }
        .detail-header h1 { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; white-space: nowrap; }

        /* community.php post-content-area 스타일과 통일된 카드 */
        /* community.php .post-item 과 동일 (padding 16px, 하단 보더만, radius 없음) */
        .review-card-area { padding: 16px; border-bottom: 1px solid var(--bs-border-color, #e0e0e0); }

        /* 시술 태그 */
        .treatment-section { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .tag { display: inline-block; margin: 5px 0; background: #FFEEEE; color: #AC1830; border-radius: 4px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; }

        /* 이미지 — community.php .post-thumbs / .thumb-item 과 동일 */
        .post-thumbs { width: 100%; display: grid; gap: 4px; border-radius: 8px; overflow: hidden; margin-top: 10px; cursor: pointer; }
        .post-thumbs.thumbs-1 { grid-template-columns: 1fr; max-width: 50%; }
        .post-thumbs.thumbs-2 { grid-template-columns: 1fr 1fr; }
        .post-thumbs.thumbs-3plus { grid-template-columns: repeat(3, 1fr); }
        .post-thumbs .thumb-item { position: relative; aspect-ratio: 1/1; overflow: hidden; background: var(--bs-tertiary-bg, #f5f5f5); }
        .post-thumbs .thumb-item img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* 작성자/통계 행 — community.php .post-author-row 와 동일 */
        .post-meta-row { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .post-author-row { display: flex; align-items: center; gap: 8px; }
        .post-avatar { width: 35px; height: 35px; border-radius: 50%; background: #AAAAAA; overflow: hidden; flex-shrink: 0; }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .post-author-name { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 400; color: #000000; }
        .post-stat-group { display: inline-flex; align-items: center; gap: 30px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: #000000; margin-left: auto; }
        .post-stat-group .stat { display: inline-flex; align-items: center; gap: 3px; }
        .post-stat-group .stat i { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); }
        .post-stat-group .heart-icon { color: #AC1830; }
        /* 통계를 카드 하단에 배치 */
        .review-stat-bottom { margin-left: auto; margin-top: 0; padding-top: 0; border-top: none; display: inline-flex; }

        /* 콘텐츠 */
        .review-rating { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .review-rating-stars { display: inline-flex; gap: 2px; color: #f59e0b; font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); line-height: 1; }
        .review-rating-stars .empty { color: var(--bs-border-color, #d9d9d9); }
        .review-rating-score { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--text-sub); }
        .review-title { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; color: #000; margin-bottom: 4px; line-height: 1.4; }
        .review-body { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); line-height: 1.8; color: var(--text); }
        .review-body p { margin-bottom: 8px; }
        .review-body img { max-width: 100%; border-radius: 6px; margin: 8px 0; }

        /* 병원 정보 */
        .hospital-section { margin: 0; padding: 16px; display: flex; gap: 12px; text-decoration: none; color: inherit; border-bottom: 1px solid var(--bs-border-color, #e0e0e0); }
        .hospital-section:hover { opacity: 0.9; }
        .hospital-thumb, .hospital-thumb-empty { width: 64px; height: 64px; flex-shrink: 0; object-fit: cover; background: #d9d9d9; }
        .hospital-thumb-empty { display: flex; align-items: center; justify-content: center; color: var(--bs-secondary-color, #bbb); font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1)); }
        .hospital-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 3px; }
        .hospital-name { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hospital-address { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hospital-rating { display: flex; align-items: center; gap: 3px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; }
        .hospital-rating-icon { width: 14px; height: 14px; object-fit: contain; }

        /* 수정하기 */
        .edit-section { display: flex; align-items: center; justify-content: center; margin: 20px 20px; gap: 6px; padding: 14px 16px; text-decoration: none; color: #AC1830; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; border: 1px solid var(--bs-border-color, #AC1830); }

        /* 액션 */
        .action-bar { padding: 16px 20px; border-top: 1px solid var(--border); border-bottom: 8px solid var(--bg); display: flex; gap: 24px; }
        .action-btn { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-sub); }
        .action-btn.liked { color: #ef4444; }
        .action-btn svg { width: 20px; height: 20px; }

        /* 댓글 */
        .comments-section { padding: 20px; }
        .comments-title { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-bottom: 16px; }
        .comment-item { padding: 12px 0; border-bottom: 1px solid var(--border); }
        .comment-item:last-child { border-bottom: none; }
        .comment-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .comment-name { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; }
        .comment-time { font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); margin-left: auto; }
        .comment-body { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); line-height: 1.6; color: var(--text); }
        .reply-item { margin-left: 32px; padding-left: 12px; border-left: 2px solid var(--border); }

        /* 댓글 입력 */
        .comment-form { position: sticky; bottom: 0; background: var(--bs-body-bg, #fff); border-top: 1px solid var(--border); padding: 12px 16px; display: flex; gap: 8px; }
        .comment-input { flex: 1; padding: 10px 14px; border: 1px solid var(--border); border-radius: 20px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); outline: none; }
        .comment-input:focus { border-color: var(--primary); }
        .comment-submit { padding: 10px 18px; background: var(--primary); color: #fff; border: none; border-radius: 20px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; }
        .comment-submit:disabled { background: #ccc; }

        /* 이미지 모달 */
        .img-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; }
        .img-modal.show { display: flex; }
        .img-modal img { max-width: 95vw; max-height: 95vh; object-fit: contain; }
        .img-modal-close { position: absolute; top: 16px; right: 16px; background: none; border: none; color: #fff; font-size: calc(32px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; }

        /* 신고 모달 */
        .report-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .report-modal.show { display: flex; }
        .report-box { background: var(--bs-body-bg, #fff); border-radius: 12px; padding: 24px; width: 90%; max-width: 360px; }
        .report-box h3 { font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 16px; }
        .report-option { display: block; width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 8px; text-align: left; background: var(--bs-body-bg, #fff); cursor: pointer; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
        .report-option:hover { background: var(--bg); }
        .report-cancel { display: block; width: 100%; padding: 12px; border: none; background: none; color: var(--text-muted); cursor: pointer; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-top: 4px; }

        /* 내가 작성한 후기 상세 디자인 */
        html { background:#3f3f3f; }
        body { min-height:100vh; background:#3f3f3f; color:#111; }
        .container { width:100%; max-width:640px; min-height:100vh; background:#fff; }
        .detail-header { top:0; height:64px; padding:0 14px; background:#fff; z-index:20; }
        .detail-header a { width:40px; height:40px; margin:0; display:flex; align-items:center; justify-content:center; font-size:28px; }
        .detail-header h1 { font-size:18px; font-weight:500; }
        .review-card-area { padding:24px 20px 28px; border-bottom:1px solid #ddd; }
        .treatment-section { margin-bottom:8px; }
        .tag { margin:0 0 10px; padding:3px 9px; border-radius:0; font-size:12px; }
        .review-rating { gap:5px; margin-bottom:12px; }
        .review-rating-stars { gap:1px; font-size:16px; }
        .review-rating-score { color:#111; font-size:12px; }
        .review-title { margin-bottom:8px; font-size:17px; font-weight:700; }
        .review-body { font-size:14px; line-height:1.5; color:#111; }
        .post-thumbs { margin-top:26px; gap:0; border-radius:0; }
        .post-thumbs.thumbs-1 { max-width:100%; }
        .post-thumbs.thumbs-3plus { grid-template-columns:repeat(2,1fr); }
        .post-thumbs .thumb-item { aspect-ratio:1.55/1; }
        .post-meta-row { margin-top:16px; min-height:42px; }
        .post-avatar { width:34px; height:34px; background:#ddd; }
        .post-author-name { font-size:13px; }
        .post-stat-group { gap:34px; font-size:12px; }
        .post-stat-group .stat i { font-size:20px; }
        .hospital-section { margin:0 20px; padding:38px 0; gap:18px; border-bottom:1px solid #ddd; }
        .hospital-thumb,.hospital-thumb-empty { width:96px; height:96px; }
        .hospital-name { font-size:17px; font-weight:700; }
        .hospital-address { margin-top:6px; color:#111; font-size:13px; }
        .hospital-rating { margin-top:8px; font-size:12px; }
        .edit-section { height:50px; margin:38px 20px 0; padding:0; border-color:#AC1830; font-size:15px; }
        @media(max-width:420px){.post-stat-group{gap:16px}.hospital-thumb,.hospital-thumb-empty{width:82px;height:82px}.review-card-area{padding-left:16px;padding-right:16px}.hospital-section{margin-left:16px;margin-right:16px}}
    </style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<div class="container">
    <!-- 헤더 -->
    <header class="detail-header">
        <a href="javascript:history.back()"><i class="bi bi-chevron-left"></i></a>
        <h1>내가 작성한 후기 보기</h1>
    </header>

    <!-- community.php post-item 과 동일 구조의 카드 -->
    <div class="review-card-area">
        <?php if (!empty($review['treatments'])): ?>
        <?php foreach (explode(',', $review['treatments']) as $t): ?>
        <span class="tag"><?php echo htmlspecialchars(trim($t)); ?></span>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php $review_rating = max(0, min(5, (int)($review['review_rating'] ?? $review['rating'] ?? 0))); ?>
        <?php if ($review_rating > 0): ?>
        <div class="review-rating" aria-label="평점 <?php echo $review_rating; ?>점">
            <span class="review-rating-stars" aria-hidden="true">
                <?php for ($star = 1; $star <= 5; $star++): ?>
                <span class="<?php echo $star > $review_rating ? 'empty' : ''; ?>">&#9733;</span>
                <?php endfor; ?>
            </span>
            <span class="review-rating-score"><?php echo number_format($review_rating, 1); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($review['title'])): ?>
        <div class="review-title"><?php echo htmlspecialchars($review['title']); ?></div>
        <?php endif; ?>

        <div class="review-body">
            <?php if (!empty($review['editor_content']) && $review['editor_type'] === 'quill'): ?>
            <?php echo sanitizeHtml($review['editor_content']); // 저장 시점 정화와 별개로 출력 시점에도 정화 ?>
            <?php else: ?>
            <?php echo nl2br(htmlspecialchars($review['content'])); ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($images)):
            $img_count = count($images);
            $thumb_class = $img_count === 1 ? 'thumbs-1' : ($img_count === 2 ? 'thumbs-2' : 'thumbs-3plus');
        ?>
        <div class="post-thumbs <?php echo $thumb_class; ?>">
            <?php foreach ($images as $img):
                // 저장 시점과 별개로 출력 시점에도 URL 정화 (기존 데이터 방어)
                $img_url = sanitizeImageUrl($img['image_url'] ?? '');
                $img_thumb = sanitizeImageUrl($img['thumbnail_url'] ?? '');
                $img_src = $img_thumb !== '' ? $img_thumb : $img_url;
                if ($img_src === '') continue;
            ?>
            <?php /* URL 은 data 속성으로만 전달한다 — 인라인 핸들러 안에 넣으면
                     htmlspecialchars 로도 JS 문자열 탈출을 막을 수 없다 */ ?>
            <div class="thumb-item" data-img-url="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>" onclick="showImage(this.dataset.imgUrl)">
                <img src="<?php echo htmlspecialchars($img_src, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo term('review', '후기'); ?> 이미지" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-meta-row">
        <div class="post-author-row">
            <div class="post-avatar">
                <?php if (!empty($review['user_avatar'])): ?>
                <img src="<?php echo htmlspecialchars($review['user_avatar']); ?>" alt="">
                <?php endif; ?>
            </div>
            <span class="post-author-name"><?php echo htmlspecialchars($review['user_name']); ?></span>
        </div>

        <!-- 통계 (카드 하단) -->
        <div class="post-stat-group review-stat-bottom">
            <span class="stat" id="likeBtn" onclick="toggleLike()" style="cursor:pointer;<?php echo $user_liked ? 'color:#AC1830;font-weight:700;' : ''; ?>"><i class="bi <?php echo $user_liked ? 'bi-heart-fill' : 'bi-heart'; ?>"></i> <span id="likeCount"><?php echo intval($review['like_count']); ?></span></span>
            <span class="stat"><i class="bi bi-chat-square-text"></i> <?php echo count($comments); ?></span>
            <span class="stat"><i class="bi bi-eye"></i> <?php echo intval($review['view_count'] ?? 0); ?></span>
        </div>
        </div>
    </div>

    <!-- 병원 링크 -->
    <?php if ($review['hospital_id']): ?>
    <a href="detail.php?id=<?php echo $review['hospital_id']; ?>" class="hospital-section">
        <?php if (!empty($review['hospital_image'])): ?>
        <img class="hospital-thumb" src="<?php echo htmlspecialchars($review['hospital_image']); ?>" alt="">
        <?php else: ?>
        <div class="hospital-thumb-empty"></div>
        <?php endif; ?>
        <div class="hospital-info">
            <div class="hospital-name"><?php echo htmlspecialchars($review['hospital_name'] ?? ''); ?></div>
            <div class="hospital-address"><?php echo htmlspecialchars($review['hospital_address'] ?? ''); ?></div>
            <?php if (!empty($review['hospital_rating'])): ?>
            <div class="hospital-rating"><img src="assets/icons/categories/rating_2.svg" class="hospital-rating-icon" alt=""> <?php echo number_format($review['hospital_rating'], 1); ?> (<?php echo intval($review['hospital_review_count'] ?? 0); ?>)</div>
            <?php endif; ?>
        </div>
    </a>
    <?php endif; ?>

    <!-- 수정하기 (본인 후기만) -->
    <?php if ($current_user_id && $current_user_id === ($review['user_id'] ?? '')): ?>
    <a href="review_write.php?id=<?php echo $review_id; ?>&amp;return_url=<?php echo urlencode('review_detail.php?id=' . $review_id); ?>" class="edit-section">
         수정하기
    </a>
    <?php endif; ?>
</div>

<!-- 이미지 모달 -->
<div class="img-modal" id="imgModal" onclick="this.classList.remove('show')">
    <button class="img-modal-close" onclick="event.stopPropagation();document.getElementById('imgModal').classList.remove('show')">×</button>
    <img id="imgModalSrc" src="" alt="">
</div>

<!-- 신고 모달 -->
<div class="report-modal" id="reportModal">
    <div class="report-box">
        <h3>신고 사유 선택</h3>
        <button class="report-option" onclick="sendReport('spam')">스팸/광고</button>
        <button class="report-option" onclick="sendReport('abuse')">욕설/비방</button>
        <button class="report-option" onclick="sendReport('fake')">허위 <?php echo term('review', '후기'); ?></button>
        <button class="report-option" onclick="sendReport('inappropriate')">부적절한 내용</button>
        <button class="report-option" onclick="sendReport('other')">기타</button>
        <button class="report-cancel" onclick="document.getElementById('reportModal').classList.remove('show')">취소</button>
    </div>
</div>

<script>
const REVIEW_ID = <?php echo $review_id; ?>;
const USER_ID = '<?php echo addslashes($current_user_id); ?>';
const BASE_URL = '<?php echo $base_url; ?>';

// 업로드 경로/http(s) 이외의 URL(javascript:, data: ...)은 무시한다
function rdSafeImageUrl(u) {
    if (typeof u !== 'string') return '';
    u = u.trim();
    if (u === '' || u.indexOf('//') === 0) return '';
    if (/^https?:\/\//i.test(u)) return u;
    if (/^[a-z][a-z0-9+.\-]*:/i.test(u)) return '';
    return u;
}

function showImage(url) {
    var safe = rdSafeImageUrl(url);
    if (!safe) return;
    document.getElementById('imgModalSrc').src = safe;
    document.getElementById('imgModal').classList.add('show');
}

async function toggleLike() {
    if (!USER_ID) { alert('로그인이 필요합니다.'); return; }
    try {
        const res = await fetch(BASE_URL + '/api/like_review.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ review_id: REVIEW_ID, user_id: USER_ID })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('likeCount').textContent = data.like_count;
            const btn = document.getElementById('likeBtn');
            btn.classList.toggle('liked', data.liked);
            const icon = btn.querySelector('i');
            if (icon) icon.className = data.liked ? 'bi bi-heart-fill' : 'bi bi-heart';
            btn.style.color = data.liked ? '#AC1830' : '';
            btn.style.fontWeight = data.liked ? '700' : '';
        }
    } catch (e) { console.error(e); }
}

async function submitComment() {
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    if (!content) return;

    const userName = USER_ID || '익명';
    try {
        const res = await fetch(BASE_URL + '/api/save_comment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                review_id: REVIEW_ID,
                user_id: USER_ID,
                user_name: userName,
                content: content
            })
        });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            location.reload();
        } else {
            alert(data.message || '댓글 등록에 실패했습니다.');
        }
    } catch (e) { alert('댓글 등록 중 오류가 발생했습니다.'); }
}

// Enter 키로 댓글 등록
const commentInput = document.getElementById('commentInput');
if (commentInput) commentInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') submitComment();
});

function showReport() {
    if (!USER_ID) { alert('로그인이 필요합니다.'); return; }
    document.getElementById('reportModal').classList.add('show');
}

async function sendReport(reason) {
    try {
        const res = await fetch(BASE_URL + '/api/report_review.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                review_id: REVIEW_ID,
                reporter_id: USER_ID,
                reason: reason
            })
        });
        const data = await res.json();
        alert(data.message);
        document.getElementById('reportModal').classList.remove('show');
    } catch (e) { alert('신고 접수 중 오류가 발생했습니다.'); }
}

// 외부 클릭으로 모달 닫기
document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
</script>
</div>

<?php
// CPV 추적
$cpv_page_type = 'review';
$cpv_target_id = $review_id;
include __DIR__ . '/includes/cpv_tracker.php';
?>

</body>
</html>

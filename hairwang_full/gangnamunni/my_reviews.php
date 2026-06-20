<?php
require_once __DIR__ . '/includes/init.php';
if (!is_gangnamunni_logged_in()) { header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME'],2),'/').'/bbs/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

// 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $rid = intval($_POST['review_id'] ?? 0);
    if ($rid > 0) {
        $stmt = mysqli_prepare($connect, "UPDATE hospital_reviews SET is_active=0 WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'is', $rid, $member_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// [1차 검수 #22 - 2026-05-28] 마이페이지 내 후기 연동 — 커뮤니티 시술후기도 포함
$reviews = [];
// ① 병원 상세에서 작성한 후기 (hospital_reviews)
$safe_member = mysqli_real_escape_string($connect, $member_id);
$res = mysqli_query($connect, "SELECT r.id, r.hospital_id, r.content, r.rating, r.view_count, r.like_count, r.created_at, h.name as hospital_name, 'review' as source_type FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id=h.id WHERE r.user_id='{$safe_member}' AND r.is_active=1 ORDER BY r.created_at DESC");
if ($res) while ($row = mysqli_fetch_assoc($res)) $reviews[] = $row;
// ② 커뮤니티 시술후기 카테고리 게시글도 포함 (1차 검수 #22)
$res2 = mysqli_query($connect, "SELECT p.id, NULL as hospital_id, p.content, NULL as rating, p.view_count, p.like_count, p.created_at, p.title as hospital_name, 'community_review' as source_type FROM gangnamunni_community_posts p WHERE p.mb_id='{$safe_member}' AND p.status='active' AND (p.category='시술후기' OR p.category LIKE '%후기%') ORDER BY p.created_at DESC");
if ($res2) while ($row = mysqli_fetch_assoc($res2)) $reviews[] = $row;
// 최신순 정렬
usort($reviews, function($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>내 후기 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.review-card{background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);padding:16px;}
.rv-hospital{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);margin-bottom:4px;}
.rv-rating{color:#f59e0b;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));margin-bottom:6px;}
.rv-content{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));line-height:1.6;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.rv-footer{display:flex;justify-content:space-between;align-items:center;}
.rv-date{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
.rv-actions{display:flex;gap:8px;}
.rv-actions a,.rv-actions button{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));padding:4px 10px;border-radius:6px;text-decoration:none;cursor:pointer;border:1px solid var(--bs-border-color,#ddd);background:none;color:inherit;}
.rv-actions button.del{color:#ef4444;border-color:#ef4444;}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color);}
.write-btn{position:fixed;bottom:80px;right:calc(50% - 300px);width:52px;height:52px;border-radius:50%;background:var(--pri);color:#fff;border:none;font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1));box-shadow:0 4px 12px rgba(124,58,237,0.4);cursor:pointer;display:flex;align-items:center;justify-content:center;}
@media(max-width:640px){.write-btn{right:16px;}}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>내 후기 <span style="color:var(--pri);"><?php echo count($reviews); ?></span></h1></div>
<?php if (empty($reviews)): ?>
<div class="empty"><i class="bi bi-chat-square-text" style="font-size:48px;display:block;margin-bottom:12px;"></i>작성한 후기가 없습니다<br><a href="review_write.php" style="color:var(--pri);">후기 작성하기</a></div>
<?php else: foreach ($reviews as $r): ?>
<div class="review-card">
    <div class="rv-hospital"><i class="bi bi-building"></i> <?php echo htmlspecialchars($r['hospital_name'] ?? ''); ?></div>
    <div class="rv-rating"><?php echo str_repeat('★', $r['rating'] ?? 5) . str_repeat('☆', 5 - ($r['rating'] ?? 5)); ?></div>
    <div class="rv-content"><?php echo nl2br(htmlspecialchars($r['content'] ?? '')); ?></div>
    <div class="rv-footer">
        <div class="rv-date"><?php echo $r['created_at']; ?> · 조회 <?php echo $r['view_count'] ?? 0; ?> · 좋아요 <?php echo $r['like_count'] ?? 0; ?></div>
        <div class="rv-actions">
            <?php if (($r['source_type'] ?? '') === 'community_review'): ?>
            <a href="detail_community.php?id=<?php echo $r['id']; ?>">커뮤니티에서 보기</a>
            <?php else: ?>
            <a href="review_detail.php?id=<?php echo $r['id']; ?>">보기</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('삭제하시겠습니까?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?php echo $r['id']; ?>"><button type="submit" class="del">삭제</button></form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>
<a href="review_write.php" class="write-btn"><i class="bi bi-pencil-fill"></i></a>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>

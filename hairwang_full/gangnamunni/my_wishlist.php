<?php
/**
 * 내 찜 목록 페이지
 * 병원/시술 찜한 항목 조회 + 제거
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e) file_put_contents('/tmp/wl_errors.log', date('H:i:s')." FATAL: ".$e['message']." in ".$e['file'].":".$e['line']."
", FILE_APPEND);
});
// 로그인 체크
if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('/hairwang/gangnamunni/my_wishlist.php'));
    exit;
}

if (file_exists(__DIR__ . '/includes/wishlist_button.php')) {
    require_once __DIR__ . '/includes/wishlist_button.php';
}

$member_id = (int)($member['mb_no'] ?? 0);

// 찜 목록 조회 (병원 + 시술) — 탭 개수 표시를 위해 전체를 가져온 뒤 화면에서 필터링
$type_filter = $_GET['type'] ?? '';
if (!in_array($type_filter, ['hospital', 'treatment'])) $type_filter = 'hospital';

$sql = "SELECT w.*, w.target_type,
        CASE WHEN w.target_type='hospital' THEN h.name ELSE t.name END as target_name,
        CASE WHEN w.target_type='hospital' THEN h.main_image_url ELSE NULL END as target_image,
        CASE WHEN w.target_type='hospital' THEN h.address ELSE NULL END as target_address,
        CASE WHEN w.target_type='hospital' THEN h.rating ELSE NULL END as target_rating,
        CASE WHEN w.target_type='hospital' THEN h.review_count ELSE NULL END as target_review_count,
        CASE WHEN w.target_type='treatment' THEN t.hospital_id ELSE NULL END as treatment_hospital_id,
        CASE WHEN w.target_type='treatment' THEN th.name ELSE NULL END as treatment_hospital_name,
        CASE WHEN w.target_type='treatment' THEN COALESCE(NULLIF(t.discount_price,0), t.price) ELSE NULL END as treatment_price
        FROM gangnamunni_wishlist w
        LEFT JOIN gangnamunni_hospitals h ON w.target_type='hospital' AND w.target_id=h.id
        LEFT JOIN gangnamunni_treatments t ON w.target_type='treatment' AND w.target_id=t.id
        LEFT JOIN gangnamunni_hospitals th ON w.target_type='treatment' AND t.hospital_id=th.id
        WHERE w.member_id = ? AND w.target_type IN ('hospital','treatment')
        ORDER BY w.created_at DESC";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, 'i', $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $all_items[] = $row;
}
mysqli_stmt_close($stmt);

$hospital_count = 0;
$treatment_count = 0;
foreach ($all_items as $item) {
    if ($item['target_type'] === 'hospital') $hospital_count++;
    else $treatment_count++;
}

$items = array_values(array_filter($all_items, function($item) use ($type_filter) { return $item['target_type'] === $type_filter; }));
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>내 찜 목록 - <?php echo site_name(); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();
</script>
<style>
:root { --primary: #AE1932; --bg: #f8f9fa; --card: #fff; --text: #1a1a1a; --text-muted: #888; --border: #eee; }
[data-bs-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e5e5e5; --text-muted: #999; --border: #444; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); max-width: 640px; margin: 0 auto; padding-bottom: 80px; }

.page-header { padding: 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border); background: var(--card); position: sticky; top: 0; z-index: 10; }
.page-header .back-btn { width: 36px; height: 36px; border: none; background: none; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; color: var(--text); position: relative; z-index: 1; }
.page-header h1 { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; white-space: nowrap; }

.filter-tabs { display: flex; background: var(--card); border-bottom: 1px solid var(--border); }
.filter-tab { flex: 1; padding: 12px; text-align: center; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; color: var(--text-muted); border-bottom: 2px solid transparent; text-decoration: none; }
.filter-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state i { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; display: block; }

.wishlist-card { display: flex; gap: 12px; padding: 16px; border-bottom: 1px solid var(--border); background: var(--card); }
.wishlist-card .img-wrap { position: relative; flex-shrink: 0; }
.wishlist-card img { width: 80px; height: 80px; object-fit: cover; flex-shrink: 0; display: block; }
.wishlist-card .no-img { width: 80px; height: 80px; border-radius: 10px; background: var(--border); display: flex; align-items: center; justify-content: center; font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); flex-shrink: 0; }
.rank-badge { position: absolute; top: -6px; left: -6px; background: var(--primary); color: #fff; font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
.wishlist-info { flex: 1; min-width: 0; }
.wishlist-info h3 { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wishlist-info h3 a { color: var(--text); text-decoration: none; }
.wishlist-info .meta { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wishlist-info .rating { color: #f59e0b; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); margin-top: 4px; }
.wishlist-info .price { color: var(--primary); font-weight: 700; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-top: 4px; }
.wishlist-actions { display: flex; flex-direction: column; justify-content: center; align-items: center; }
.wishlist-actions .remove-btn { border: none; background: none; cursor: pointer; padding: 6px; font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1)); color: #e53e3e; line-height: 1; transition: transform 0.15s; }
.wishlist-actions .remove-btn:hover { transform: scale(1.1); }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="page-header">
    <button class="back-btn" onclick="location.href='mypage.php'"><i class="bi bi-chevron-left"></i></button>
    <h1>병원/시술 찜 목록</h1>
</div>

<div class="filter-tabs">
    <a href="my_wishlist.php?type=treatment" class="filter-tab <?php echo $type_filter === 'treatment' ? 'active' : ''; ?>"><?php echo term('service', '시술'); ?> </a>
    <a href="my_wishlist.php?type=hospital" class="filter-tab <?php echo $type_filter === 'hospital' ? 'active' : ''; ?>"><?php echo term('business', '병원'); ?></a>
</div>

<?php if (empty($items)): ?>
<div class="empty-state">
    <i class="bi bi-heart"></i>
    <p>찜한 항목이 없습니다</p>
    <p style="font-size:13px; margin-top:8px;">관심있는 <?php echo term('business', '병원'); ?>이나 <?php echo term('service', '시술'); ?>을 찜해보세요</p>
    <a href="hospitals.php" style="display:inline-block; margin-top:16px; padding:10px 24px; background:var(--primary); color:#fff; border-radius:8px; text-decoration:none; font-size:14px;"><?php echo term('business', '병원'); ?> 둘러보기</a>
</div>
<?php else: ?>
<?php $_hospital_rank = 0; ?>
<?php foreach ($items as $item): ?>
<div class="wishlist-card" id="wcard_<?php echo $item['id']; ?>">
    <?php
    $is_treatment = $item['target_type'] === 'treatment';
    $link = $is_treatment ? "detail.php?id={$item['treatment_hospital_id']}" : "detail.php?id={$item['target_id']}";
    if (!$is_treatment) $_hospital_rank++;
    ?>
    <div class="img-wrap">
        <?php // [TEST] 순위 뱃지 — 임시 테스트용, 실제 랭킹 데이터 연동 아님 ?>
        <?php if ($item['target_image']): ?>
        <a href="<?php echo $link; ?>"><img src="<?php echo htmlspecialchars($item['target_image']); ?>" alt=""></a>
        <?php else: ?>
        <a href="<?php echo $link; ?>"><div class="no-img"><i class="bi bi-<?php echo $is_treatment ? 'magic' : 'building'; ?>"></i></div></a>
        <?php endif; ?>
    </div>

    <div class="wishlist-info">
        <h3><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($item['target_name'] ?? '(삭제됨)'); ?></a></h3>
        <?php if ($is_treatment && $item['treatment_hospital_name']): ?>
        <div class="meta"><i class="bi bi-building"></i> <?php echo htmlspecialchars($item['treatment_hospital_name']); ?></div>
        <?php endif; ?>
        <?php if (!$is_treatment && $item['target_address']): ?>
        <div class="meta"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($item['target_address']); ?></div>
        <?php endif; ?>
        <?php if (!$is_treatment && $item['target_rating']): ?>
        <div class="rating"><i class="bi bi-star-fill"></i> <?php echo number_format($item['target_rating'], 1); ?> (<?php echo $item['target_review_count'] ?? 0; ?>)</div>
        <?php endif; ?>
    </div>

    <div class="wishlist-actions">
        <button class="remove-btn" onclick="removeWishlist(<?php echo $item['id']; ?>, '<?php echo $item['target_type']; ?>', <?php echo $item['target_id']; ?>)" title="찜 해제" aria-label="찜 해제"><i class="bi bi-heart-fill"></i></button>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>

<script>
function removeWishlist(wlId, type, targetId) {
    var basePath = window.location.pathname.replace(/\/[^\/]*$/, '');
    fetch(basePath + '/api_wishlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: type, target_id: targetId})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success && !res.wishlisted) {
            var card = document.getElementById('wcard_' + wlId);
            if (card) {
                card.style.transition = 'opacity 0.3s, max-height 0.3s';
                card.style.opacity = '0';
                card.style.maxHeight = '0';
                card.style.overflow = 'hidden';
                setTimeout(function() { card.remove(); }, 300);
            }
        }
    });
}
</script>
</body>
</html>
<?php mysqli_close($connect); ?>

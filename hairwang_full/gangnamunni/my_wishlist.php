<?php
/**
 * 내 찜 목록 페이지
 * 병원/의사 찜한 항목 조회 + 제거
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
    header('Location: /hairwang/bbs/login.php?url=' . urlencode('/hairwang/gangnamunni/my_wishlist.php'));
    exit;
}

if (file_exists(__DIR__ . '/includes/wishlist_button.php')) {
    require_once __DIR__ . '/includes/wishlist_button.php';
}

$member_id = (int)($member['mb_no'] ?? 0);

// 찜 목록 조회
$type_filter = $_GET['type'] ?? '';
$where = 'w.member_id = ?';
$params = [$member_id];
$types_str = 'i';

if ($type_filter && in_array($type_filter, ['hospital', 'doctor'])) {
    $where .= ' AND w.target_type = ?';
    $params[] = $type_filter;
    $types_str .= 's';
}

$sql = "SELECT w.*, w.target_type,
        CASE WHEN w.target_type='hospital' THEN h.name ELSE d.name END as target_name,
        CASE WHEN w.target_type='hospital' THEN h.main_image_url ELSE d.photo_url END as target_image,
        CASE WHEN w.target_type='hospital' THEN h.address ELSE NULL END as target_address,
        CASE WHEN w.target_type='hospital' THEN h.rating ELSE NULL END as target_rating,
        CASE WHEN w.target_type='hospital' THEN h.review_count ELSE NULL END as target_review_count,
        CASE WHEN w.target_type='doctor' THEN d.specialty ELSE NULL END as doctor_specialty
        FROM gangnamunni_wishlist w
        LEFT JOIN gangnamunni_hospitals h ON w.target_type='hospital' AND w.target_id=h.id
        LEFT JOIN gangnamunni_doctors d ON w.target_type='doctor' AND w.target_id=d.id
        WHERE {$where}
        ORDER BY w.created_at DESC";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, $types_str, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

$hospital_count = 0;
$doctor_count = 0;
foreach ($items as $item) {
    if ($item['target_type'] === 'hospital') $hospital_count++;
    else $doctor_count++;
}
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
.page-header .back-btn { width: 36px; height: 36px; border: none; background: none; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; color: var(--text); }
.page-header h1 { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; }

.filter-tabs { display: flex; padding: 12px 16px; gap: 8px; border-bottom: 1px solid var(--border); background: var(--card); }
.filter-tab { padding: 6px 16px; border-radius: 20px; border: 1px solid var(--border); background: var(--card); color: var(--text-muted); font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; text-decoration: none; }
.filter-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state i { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; display: block; }

.wishlist-card { display: flex; gap: 12px; padding: 16px; border-bottom: 1px solid var(--border); background: var(--card); }
.wishlist-card img { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
.wishlist-card .no-img { width: 80px; height: 80px; border-radius: 10px; background: var(--border); display: flex; align-items: center; justify-content: center; font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); flex-shrink: 0; }
.wishlist-info { flex: 1; min-width: 0; }
.wishlist-info .type-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-bottom: 4px; }
.wishlist-info .type-badge.hospital { background: #dbeafe; color: #1d4ed8; }
.wishlist-info .type-badge.doctor { background: #dcfce7; color: #15803d; }
[data-bs-theme="dark"] .wishlist-info .type-badge.hospital { background: #1e3a5f; color: #93c5fd; }
[data-bs-theme="dark"] .wishlist-info .type-badge.doctor { background: #1a3d2e; color: #86efac; }
.wishlist-info h3 { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wishlist-info h3 a { color: var(--text); text-decoration: none; }
.wishlist-info .meta { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text-muted); }
.wishlist-info .rating { color: #f59e0b; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); margin-top: 4px; }
.wishlist-actions { display: flex; flex-direction: column; justify-content: center; gap: 6px; }
.wishlist-actions .remove-btn { padding: 6px 14px; border: 1px solid #e53e3e; color: #e53e3e; background: none; border-radius: 8px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; white-space: nowrap; }
.wishlist-actions .remove-btn:hover { background: #e53e3e; color: #fff; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="page-header">
    <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <h1>내 찜 목록 (<?php echo count($items); ?>)</h1>
</div>

<div class="filter-tabs">
    <a href="my_wishlist.php" class="filter-tab <?php echo !$type_filter ? 'active' : ''; ?>">전체 (<?php echo count($items); ?>)</a>
    <a href="my_wishlist.php?type=hospital" class="filter-tab <?php echo $type_filter === 'hospital' ? 'active' : ''; ?>"><?php echo term('business', '병원'); ?> (<?php echo $hospital_count; ?>)</a>
    <a href="my_wishlist.php?type=doctor" class="filter-tab <?php echo $type_filter === 'doctor' ? 'active' : ''; ?>"><?php echo term('doctor', '의사'); ?> (<?php echo $doctor_count; ?>)</a>
</div>

<?php if (empty($items)): ?>
<div class="empty-state">
    <i class="bi bi-heart"></i>
    <p>찜한 항목이 없습니다</p>
    <p style="font-size:13px; margin-top:8px;">관심있는 <?php echo term('business', '병원'); ?>이나 <?php echo term('doctor', '의사'); ?>를 찜해보세요</p>
    <a href="hospitals.php" style="display:inline-block; margin-top:16px; padding:10px 24px; background:var(--primary); color:#fff; border-radius:8px; text-decoration:none; font-size:14px;"><?php echo term('business', '병원'); ?> 둘러보기</a>
</div>
<?php else: ?>
<?php foreach ($items as $item): ?>
<div class="wishlist-card" id="wcard_<?php echo $item['id']; ?>">
    <?php
    $link = $item['target_type'] === 'hospital' ? "detail.php?id={$item['target_id']}" : "doctor_detail.php?id={$item['target_id']}";
    if ($item['target_image']): ?>
    <a href="<?php echo $link; ?>"><img src="<?php echo htmlspecialchars($item['target_image']); ?>" alt=""></a>
    <?php else: ?>
    <a href="<?php echo $link; ?>"><div class="no-img"><i class="bi bi-<?php echo $item['target_type'] === 'hospital' ? 'building' : 'person'; ?>"></i></div></a>
    <?php endif; ?>

    <div class="wishlist-info">
        <span class="type-badge <?php echo $item['target_type']; ?>"><?php echo $item['target_type'] === 'hospital' ? term('business', '병원') : term('doctor', '의사'); ?></span>
        <h3><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($item['target_name'] ?? '(삭제됨)'); ?></a></h3>
        <?php if ($item['target_address']): ?>
        <div class="meta"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($item['target_address']); ?></div>
        <?php endif; ?>
        <?php if ($item['doctor_specialty']): ?>
        <div class="meta"><?php echo htmlspecialchars($item['doctor_specialty']); ?></div>
        <?php endif; ?>
        <?php if ($item['target_rating']): ?>
        <div class="rating"><i class="bi bi-star-fill"></i> <?php echo number_format($item['target_rating'], 1); ?> (<?php echo $item['target_review_count'] ?? 0; ?>)</div>
        <?php endif; ?>
    </div>

    <div class="wishlist-actions">
        <button class="remove-btn" onclick="removeWishlist(<?php echo $item['id']; ?>, '<?php echo $item['target_type']; ?>', <?php echo $item['target_id']; ?>)">제거</button>
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

<?php
/**
 * 내 찜 목록 페이지
 * 병원 찜한 항목 조회 + 제거
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

$sql = "SELECT w.*, w.target_type,
        h.name as target_name,
        h.main_image_url as target_image,
        h.address as target_address,
        h.rating as target_rating,
        h.review_count as target_review_count,
        CASE WHEN h.id IS NOT NULL
               AND h.is_active=1
               AND COALESCE(h.is_demo,0)=0
               AND (h.status='approved' OR h.status IS NULL)
             THEN 1 ELSE 0 END as hospital_available
        FROM gangnamunni_wishlist w
        LEFT JOIN gangnamunni_hospitals h ON w.target_id=h.id
        WHERE w.member_id = ? AND w.target_type='hospital'
        ORDER BY w.created_at DESC";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, 'i', $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// hospitals.php와 동일하게 관리자가 지정한 main/logo 이미지를 우선 사용한다.
$hospital_main_images = [];
$hospital_logos = [];
if (!empty($items)) {
    $hospital_ids = array_values(array_filter(array_map('intval', array_column($items, 'target_id'))));
    $hospital_ids_sql = implode(',', $hospital_ids);
    if ($hospital_ids_sql !== '') {
        $image_result = mysqli_query($connect,
            "SELECT hospital_id, image_url, image_type
             FROM hospital_images
             WHERE hospital_id IN ({$hospital_ids_sql})
               AND is_active=1 AND image_type IN ('main','logo')
             ORDER BY hospital_id ASC, sort_order ASC, id ASC");
        if ($image_result) {
            while ($image_row = mysqli_fetch_assoc($image_result)) {
                $hospital_id = (int)$image_row['hospital_id'];
                if (empty($image_row['image_url'])) continue;
                if (($image_row['image_type'] ?? '') === 'logo') {
                    if (!isset($hospital_logos[$hospital_id])) $hospital_logos[$hospital_id] = $image_row['image_url'];
                } elseif (!isset($hospital_main_images[$hospital_id])) {
                    $hospital_main_images[$hospital_id] = $image_row['image_url'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>내 찜 목록 - <?php echo site_name(); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();
</script>
<style>
:root { --primary: #AE1932; --bg: #fff; --card: #fff; --text: #111; --text-muted: #777; --border: #dedede; }
[data-bs-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e5e5e5; --text-muted: #999; --border: #444; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html { background:#3f3f3f; }
body { font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:var(--bg); color:var(--text); max-width:640px; min-height:100vh; margin:0 auto; padding-bottom:90px; }

.page-header { height:80px; padding:0 20px; display:flex; align-items:center; background:var(--card); position:sticky; top:0; z-index:10; }
.page-header .back-btn { width:40px; height:40px; border:0; background:none; font-size:27px; cursor:pointer; color:var(--text); display:flex; align-items:center; justify-content:flex-start; }
.page-header h1 { position:absolute; left:50%; transform:translateX(-50%); font-size:20px; font-weight:500; white-space:nowrap; letter-spacing:-.5px; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state i { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; display: block; }

.hospital-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:16px; width:100%; padding-inline:16px; margin-bottom:40px; box-sizing:border-box; }
.hospital-card { position:relative; display:flex; flex-direction:row; overflow:hidden; background:var(--card); }
.hospital-card .card-link { display:flex; flex-direction:row; flex:1; min-width:0; cursor:pointer; }
.hospital-card .hospital-image { width:100px; height:100px; flex-shrink:0; object-fit:cover; background:#d9d9d9; display:block; }
.hospital-card .hospital-info { flex:1; min-width:0; padding:10px 44px 10px 12px; }
.hospital-card .hospital-name { color:var(--text); font-size:15px; font-weight:500; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hospital-card .hospital-address { color:var(--text); font-size:14px; font-weight:400; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hospital-card .addr-district { font-weight:600; }
.hospital-card .addr-sep { color:#aaa; margin:0 5px; }
.wishlist-actions { position:absolute; top:8px; right:8px; z-index:3; background:rgba(255,255,255,.9); border-radius:999px; }
.wishlist-actions .remove-btn { border:0; background:none; cursor:pointer; padding:4px; font-size:20px; color:var(--primary); line-height:1; }
.unavailable-label { color:#999; font-size:14px; font-weight:600; margin-top:8px; }
.hospital-card.is-unavailable { opacity:.72; }
.hospital-card.is-unavailable .hospital-image { filter:grayscale(1); }
@media (max-width:480px) {
  .page-header { height:64px; }
  .page-header h1 { font-size:17px; }
}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="page-header">
    <button class="back-btn" onclick="location.href='mypage.php'"><i class="bi bi-chevron-left"></i></button>
    <h1>찜한 병원을 확인해보세요</h1>
</div>

<?php if (empty($items)): ?>
<div class="empty-state">
    <i class="bi bi-heart"></i>
    <p>찜한 병원이 없습니다</p>
    <p style="font-size:13px; margin-top:8px;">관심있는 <?php echo term('business', '병원'); ?>을 찜해보세요</p>
    <a href="hospitals.php" style="display:inline-block; margin-top:16px; padding:10px 24px; background:var(--primary); color:#fff; border-radius:8px; text-decoration:none; font-size:14px;"><?php echo term('business', '병원'); ?> 둘러보기</a>
</div>
<?php else: ?>
<div class="hospital-grid list-view">
<?php foreach ($items as $item): ?>
    <?php
    $is_available = !empty($item['hospital_available']);
    $link = $is_available ? "detail.php?id={$item['target_id']}&from=my_wishlist" : '';
    ?>
<div class="hospital-card<?php echo $is_available ? '' : ' is-unavailable'; ?>" id="wcard_<?php echo $item['id']; ?>"<?php if ($is_available): ?> onclick="location.href='<?php echo htmlspecialchars($link, ENT_QUOTES); ?>'" style="cursor:pointer;"<?php endif; ?>>
    <div class="wishlist-actions">
        <button class="remove-btn" onclick="event.stopPropagation();removeWishlist(<?php echo $item['id']; ?>, 'hospital', <?php echo (int)$item['target_id']; ?>)" title="찜 해제" aria-label="찜 해제"><i class="bi bi-star-fill"></i></button>
    </div>
    <div class="card-link">
        <?php $hospital_id=(int)$item['target_id']; $image_url=$hospital_main_images[$hospital_id] ?? $item['target_image'] ?? $hospital_logos[$hospital_id] ?? ''; $image_url=$image_url ?: 'assets/images/seoul_beauty_hospital_placeholder.jpeg'; ?>
        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($item['target_name'] ?? ''); ?>" class="hospital-image" onerror="this.onerror=null;this.src='assets/images/seoul_beauty_hospital_placeholder.jpeg'">
        <div class="hospital-info">
            <div class="hospital-name"><?php echo htmlspecialchars($item['target_name'] ?? '(삭제됨)'); ?></div>
            <?php if (!$is_available): ?><div class="unavailable-label">현재 이용할 수 없는 병원입니다</div><?php else: ?>
        <?php $_address = trim((string)($item['target_address'] ?? '')); $_address_parts = preg_split('/\s+/u', $_address); $_district = $_address_parts[1] ?? ($_address_parts[0] ?? ''); ?>
        <?php if ($_address !== ''): ?><div class="hospital-address"><span class="addr-district"><?php echo htmlspecialchars($_district); ?></span><span class="addr-sep">|</span><span><?php echo htmlspecialchars(mb_substr($_address,0,20,'UTF-8')).(mb_strlen($_address,'UTF-8')>20?'...':''); ?></span></div><?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
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

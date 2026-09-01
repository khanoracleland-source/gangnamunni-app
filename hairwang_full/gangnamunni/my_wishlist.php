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
             THEN 1 ELSE 0 END as hospital_available,
        (
            SELECT rt.name FROM gangnamunni_treatments rt
            WHERE rt.hospital_id=h.id AND rt.is_active=1
            ORDER BY rt.is_popular DESC, rt.sort_order ASC, rt.id ASC LIMIT 1
        ) as display_treatment_name,
        (
            SELECT rt.price FROM gangnamunni_treatments rt
            WHERE rt.hospital_id=h.id AND rt.is_active=1
            ORDER BY rt.is_popular DESC, rt.sort_order ASC, rt.id ASC LIMIT 1
        ) as treatment_regular_price,
        (
            SELECT COALESCE(NULLIF(rt.discount_price,0), rt.price) FROM gangnamunni_treatments rt
            WHERE rt.hospital_id=h.id AND rt.is_active=1
            ORDER BY rt.is_popular DESC, rt.sort_order ASC, rt.id ASC LIMIT 1
        ) as treatment_price
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
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
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

.wishlist-list { padding:0 20px; }
.wishlist-card { position:relative; display:flex; gap:20px; min-height:176px; padding:30px 0; border-bottom:1px solid var(--border); background:var(--card); cursor:pointer; }
.wishlist-card:last-child { border-bottom:0; }
.wishlist-card .img-wrap { position: relative; flex-shrink: 0; }
.wishlist-card img { width:120px; height:120px; object-fit:cover; display:block; border:1px solid #ddd; }
.wishlist-card .no-img { width:120px; height:120px; background:#f2f2f2; border:1px solid #ddd; display:flex; align-items:center; justify-content:center; font-size:34px; color:#aaa; }
.wishlist-info { flex:1; min-width:0; padding-right:42px; }
.wishlist-info h3 { font-size:20px; font-weight:700; line-height:1.35; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wishlist-info h3 a { color: var(--text); text-decoration: none; }
.wishlist-info .meta { font-size:15px; color:#222; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:10px; }
.wishlist-info .meta .district { font-weight:500; }
.wishlist-info .meta .divider { color:#bbb; margin:0 8px; }
.wishlist-info .treatment-name { font-size:22px; font-weight:700; line-height:1.35; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:7px; }
.unavailable-label { color:#999; font-size:15px; font-weight:600; margin-top:8px; }
.wishlist-card.is-unavailable { cursor:default; opacity:.72; }
.wishlist-card.is-unavailable .img-wrap { filter:grayscale(1); }
.price-row { display:flex; flex-wrap:wrap; align-items:baseline; gap:7px; line-height:1.35; }
.regular-price { color:#aaa; font-size:14px; text-decoration:line-through; }
.sale-price { color:#d50000; font-size:22px; font-weight:800; }
.price-unit { color:#222; font-size:13px; font-weight:500; margin-left:1px; }
.price-inquiry { color:#777; font-size:14px; }
.wishlist-actions { position:absolute; right:0; top:28px; }
.wishlist-actions .remove-btn { border:0; background:none; cursor:pointer; padding:4px; font-size:25px; color:var(--primary); line-height:1; transition:transform .15s; }
.wishlist-actions .remove-btn:hover { transform: scale(1.1); }
@media (max-width:480px) {
  .page-header { height:64px; }
  .page-header h1 { font-size:17px; }
  .wishlist-list { padding:0 16px; }
  .wishlist-card { min-height:142px; gap:14px; padding:22px 0; }
  .wishlist-card img,.wishlist-card .no-img { width:120px; height:120px; }
  .wishlist-info { padding-right:34px; }
  .wishlist-info h3 { font-size:20px; }
  .wishlist-info .meta { font-size:15px; margin-bottom:7px; }
  .wishlist-info .treatment-name { font-size:22px; margin-bottom:5px; }
  .sale-price { font-size:22px; }
  .regular-price { font-size:12px; }
  .wishlist-actions { top:19px; }
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
<div class="wishlist-list">
<?php foreach ($items as $item): ?>
    <?php
    $is_available = !empty($item['hospital_available']);
    $link = $is_available ? "detail.php?id={$item['target_id']}" : '';
    ?>
<div class="wishlist-card<?php echo $is_available ? '' : ' is-unavailable'; ?>" id="wcard_<?php echo $item['id']; ?>"<?php if ($is_available): ?> onclick="location.href='<?php echo htmlspecialchars($link, ENT_QUOTES); ?>'"<?php endif; ?>>
    <div class="img-wrap">
        <?php if ($item['target_image']): ?>
        <?php if ($is_available): ?><a href="<?php echo htmlspecialchars($link); ?>"><?php endif; ?><img src="<?php echo htmlspecialchars($item['target_image']); ?>" alt=""><?php if ($is_available): ?></a><?php endif; ?>
        <?php else: ?>
        <?php if ($is_available): ?><a href="<?php echo htmlspecialchars($link); ?>"><?php endif; ?><div class="no-img"><i class="bi bi-building"></i></div><?php if ($is_available): ?></a><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="wishlist-info">
        <h3><?php if ($is_available): ?><a href="<?php echo htmlspecialchars($link); ?>"><?php endif; ?><?php echo htmlspecialchars($item['target_name'] ?? '(삭제됨)'); ?><?php if ($is_available): ?></a><?php endif; ?></h3>
        <?php if (!$is_available): ?><div class="unavailable-label">현재 이용할 수 없는 병원입니다</div><?php else: ?>
        <?php $_address = trim((string)($item['target_address'] ?? '')); $_address_parts = preg_split('/\s+/u', $_address); $_district = $_address_parts[1] ?? ($_address_parts[0] ?? ''); ?>
        <?php if ($_address !== ''): ?><div class="meta"><span class="district"><?php echo htmlspecialchars($_district); ?></span><span class="divider">|</span><span><?php echo htmlspecialchars($_address); ?></span></div><?php endif; ?>
        <?php if (!empty($item['display_treatment_name'])): ?><div class="treatment-name"><?php echo htmlspecialchars($item['display_treatment_name']); ?></div><?php endif; ?>
        <?php $_regular = (int)($item['treatment_regular_price'] ?? 0); $_sale = (int)($item['treatment_price'] ?? 0); ?>
        <div class="price-row">
            <?php if ($_regular > $_sale && $_sale > 0): ?><span class="regular-price">VAT 포함&nbsp; <?php echo number_format($_regular); ?>원</span><?php endif; ?>
            <?php if ($_sale > 0): ?><span class="sale-price"><?php echo number_format($_sale); ?><span class="price-unit">원</span></span><?php else: ?><span class="price-inquiry">가격 문의</span><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="wishlist-actions">
        <button class="remove-btn" onclick="event.stopPropagation();removeWishlist(<?php echo $item['id']; ?>, '<?php echo $item['target_type']; ?>', <?php echo $item['target_id']; ?>)" title="찜 해제" aria-label="찜 해제"><i class="bi bi-star-fill"></i></button>
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

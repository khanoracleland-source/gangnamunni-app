<?php
/**
 * 병원 이름 검색 결과 (Claude 디자인 03-12 / 03-13)
 *  hospitals.php?q=이름&hid=병원ID[&tcat=카테고리ID][&sort=price|rating|popular|review]
 * 병원 카드 + "이 병원의 시술 N" + 카테고리 칩 + 정렬 칩 + 시술 행 목록
 * @var mysqli $connect
 */
if (!function_exists('hsv_load')) {
    function hsv_load($connect, $hid) {
        $hid = (int)$hid;
        if ($hid <= 0) return null;
        $r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = {$hid} AND is_active = 1 AND COALESCE(is_demo,0)=0 AND (status='approved' OR status IS NULL) LIMIT 1");
        return $r ? (mysqli_fetch_assoc($r) ?: null) : null;
    }
}

$hsv_h = hsv_load($connect, $_GET['hid'] ?? 0);
if (!$hsv_h) return false;

$hid = (int)$hsv_h['id'];
$tcat = (int)($_GET['tcat'] ?? 0);
$sort = (string)($_GET['sort'] ?? 'price');
if (!in_array($sort, ['price', 'rating', 'popular', 'review'], true)) $sort = 'price';

// 이 병원의 시술 + 최저가 옵션
$rows = [];
$sql = "SELECT ht.id AS htid, tm.id AS tmid, tm.name, tm.category_id, tc.name AS cat_name,
        (SELECT p.price FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS price,
        (SELECT p.dosage_value FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS dv,
        (SELECT p.dosage_unit FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0 ORDER BY p.price ASC LIMIT 1) AS du
    FROM gangnamunni_hospital_treatments ht
    INNER JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id AND tm.is_active = 1
    LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
    WHERE ht.hospital_id = {$hid} AND ht.status = 'active' AND ht.is_active = 1";
$res = mysqli_query($connect, $sql);
if ($res) { while ($r = mysqli_fetch_assoc($res)) $rows[] = $r; }

$cats = [];
foreach ($rows as $r) {
    $cid = (int)$r['category_id'];
    if ($cid && !isset($cats[$cid])) $cats[$cid] = ['name' => (string)$r['cat_name'], 'n' => 0];
    if ($cid) $cats[$cid]['n']++;
}
$total = count($rows);
if ($tcat) $rows = array_values(array_filter($rows, function ($r) use ($tcat) { return (int)$r['category_id'] === $tcat; }));
usort($rows, function ($a, $b) {
    $pa = (int)$a['price'] ?: PHP_INT_MAX; $pb = (int)$b['price'] ?: PHP_INT_MAX;
    return $pa <=> $pb ?: strcmp($a['name'], $b['name']);
});

$img = $hsv_h['main_image_url'] ?? ($hsv_h['profile_image_url'] ?? '');
$placeholder = 'assets/images/seoul_beauty_hospital_placeholder.jpeg';
$dist = function_exists('extract_addr_district') ? extract_addr_district((string)($hsv_h['address'] ?? '')) : '';
$rating = (float)($hsv_h['rating'] ?? 0);
$base = 'hospitals.php?q=' . rawurlencode((string)($_GET['q'] ?? $hsv_h['name'])) . '&hid=' . $hid;
$ret = rawurlencode($_SERVER['REQUEST_URI'] ?? 'hospitals.php');
?>
<style>
.hsv-card{margin:12px 20px 4px;padding:14px;border:1px solid #DCD6D8;display:flex;align-items:center;gap:12px;color:#1A1718;text-decoration:none}
.hsv-card img{width:56px;height:56px;object-fit:cover;border:1px solid #ECE9EA;flex:0 0 56px;background:#fff}
.hsv-card .nm{font-size:16px;font-weight:700}
.hsv-card .ad{font-size:12px;color:#4A4446;margin-top:3px}
.hsv-card .ad i{font-style:normal;color:#CFC8CB;margin:0 4px}
.hsv-card .rt{font-size:11px;margin-top:4px;display:flex;gap:10px;align-items:center}
.hsv-card .go{font-size:12px;color:#4A4446;display:flex;align-items:center;flex:0 0 auto}
.hsv-t{padding:14px 20px 8px;font-size:15px;font-weight:700}
.hsv-t span{color:#AC1830}
.hsv-cats{display:flex;gap:6px;padding:0 20px 10px;overflow-x:auto;scrollbar-width:none}
.hsv-cats::-webkit-scrollbar{display:none}
.hsv-row{margin:0 20px;padding:14px 0;display:flex;align-items:center;gap:12px;border-bottom:1px solid #ECE9EA;color:inherit;text-decoration:none}
.hsv-row .b{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
.hsv-row .n{font-size:16px;font-weight:500}
.hsv-row .p{display:flex;align-items:baseline;gap:8px}
.hsv-row .p b{font-size:18px;font-weight:700;color:#AC1830}
.hsv-row .p b small{font-size:12px;font-weight:400;color:#1A1718}
.hsv-row .p em{font-style:normal;font-size:12px;color:#6B6466}
</style>
<a class="hsv-card" href="detail.php?id=<?php echo $hid; ?>&amp;return_url=<?php echo $ret; ?>">
    <img src="<?php echo htmlspecialchars($img !== '' ? $img : $placeholder, ENT_QUOTES); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo $placeholder; ?>'">
    <div style="flex:1;min-width:0">
        <div class="nm"><?php echo htmlspecialchars($hsv_h['name']); ?></div>
        <div class="ad"><?php if ($dist !== ''): ?><?php echo htmlspecialchars($dist); ?><i>|</i><?php endif; ?><?php echo htmlspecialchars((string)($hsv_h['address'] ?? '')); ?></div>
        <?php if ($rating > 0): ?><div class="rt"><span><b><?php echo number_format($rating, 1); ?></b></span></div><?php endif; ?>
    </div>
    <span class="go">병원 보기<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4A4446" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg></span>
</a>
<div class="hsv-t">이 병원의 시술 <span><?php echo (int)$total; ?></span></div>
<div class="hsv-cats">
    <a class="sr-chip<?php echo $tcat ? '' : ' active'; ?>" style="display:inline-flex;align-items:center;text-decoration:none" href="<?php echo htmlspecialchars($base . '&sort=' . $sort); ?>">전체<?php echo !$tcat ? ' ' . (int)$total : ''; ?></a>
    <?php foreach ($cats as $cid => $c): $act = ($tcat === $cid); ?>
    <a class="sr-chip<?php echo $act ? ' active' : ''; ?>" style="display:inline-flex;align-items:center;text-decoration:none" href="<?php echo htmlspecialchars($base . '&tcat=' . $cid . '&sort=' . $sort); ?>"><?php echo htmlspecialchars($c['name']); ?><?php echo $act ? ' ' . (int)$c['n'] : ''; ?></a>
    <?php endforeach; ?>
</div>
<div style="padding:0 20px 14px;display:flex;gap:8px;border-bottom:1px solid #ECE9EA" id="sortChips">
    <?php $__s = ['price' => ['낮은가격', 'low_price'], 'rating' => ['평점', 'rating'], 'popular' => ['인기', null], 'review' => ['후기', null]];
    foreach ($__s as $k => $v): $on = ($sort === $k); ?>
    <a class="sort-chip<?php echo $on ? ' active' : ''; ?>" style="text-decoration:none" href="<?php echo htmlspecialchars($base . ($tcat ? '&tcat=' . $tcat : '') . '&sort=' . $k); ?>">
        <?php if ($k === 'price'): ?><img src="assets/icons/categories/<?php echo $on ? 'low_price_2.svg' : 'low_price_1.png'; ?>" class="sort-chip-icon" alt="">
        <?php elseif ($k === 'rating'): ?><img src="assets/icons/categories/<?php echo $on ? 'rating_2.svg' : 'rating_1.png'; ?>" class="sort-chip-icon" alt="">
        <?php elseif ($k === 'review'): ?><img src="assets/icons/icon-mypage-review.png" class="sort-chip-icon<?php echo $on ? '' : ' sort-chip-icon-dim'; ?>" alt="">
        <?php else: ?><i class="bi bi-trophy"></i><?php endif; ?><?php echo $v[0]; ?></a>
    <?php endforeach; ?>
</div>
<?php if (!$rows): ?>
<div class="sr-empty"><div class="sr-empty-title">등록된 시술이 없어요</div></div>
<?php else: foreach ($rows as $r):
    $price = (int)$r['price']; $dv = (float)$r['dv']; $du = trim((string)$r['du']);
    $lab = ($dv > 0 && $du !== '') ? ' ' . rtrim(rtrim(number_format($dv, 2, '.', ''), '0'), '.') . $du : '';
    $url = 'detail.php?id=' . $hid . '&treatment_id=' . (int)$r['tmid'] . '&treatment=' . urlencode($r['name']) . '&return_url=' . $ret; ?>
<a class="hsv-row" href="<?php echo htmlspecialchars($url); ?>">
    <div class="b">
        <div class="n"><?php echo htmlspecialchars($r['name'] . $lab); ?></div>
        <?php if ($price > 0): ?>
        <div class="p"><b><?php echo number_format($price); ?><small>원</small></b><?php if ($dv > 0 && $du !== '' && $du !== '회'): ?><em>1<?php echo htmlspecialchars($du); ?>당 <?php echo number_format((int)round($price / $dv)); ?>원</em><?php endif; ?></div>
        <?php endif; ?>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A9395" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 16px"><path d="M9 5l7 7-7 7"/></svg>
</a>
<?php endforeach; endif; ?>
<?php return true;

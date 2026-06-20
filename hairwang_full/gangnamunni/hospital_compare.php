<?php
/**
 * 병원 비교 페이지
 * 2~3개 병원을 나란히 비교
 * URL: hospital_compare.php?ids=8093,8094,8095
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$ids_param = $_GET['ids'] ?? '';
$hospital_ids = array_filter(array_map('intval', explode(',', $ids_param)));
$hospital_ids = array_slice($hospital_ids, 0, 3); // 최대 3개

$hospitals = [];
if (!empty($hospital_ids)) {
    $placeholders = implode(',', array_fill(0, count($hospital_ids), '?'));
    $types = str_repeat('i', count($hospital_ids));

    $sql = "SELECT h.*,
            (SELECT COUNT(*) FROM gangnamunni_doctors d WHERE d.hospital_id = h.id AND d.status = 'active') as doctor_count,
            (SELECT GROUP_CONCAT(DISTINCT t.category SEPARATOR ', ') FROM gangnamunni_treatments t WHERE t.hospital_id = h.id AND t.is_active = 1) as treatment_categories,
            (SELECT MIN(COALESCE(NULLIF(t.discount_price,0), t.price)) FROM gangnamunni_treatments t WHERE t.hospital_id = h.id AND t.is_active = 1 AND t.price > 0) as min_price
            FROM gangnamunni_hospitals h
            WHERE h.id IN ({$placeholders})";

    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$hospital_ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $hospitals[$row['id']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// 비교 항목 정의
$compare_items = [
    ['label' => '평점', 'key' => 'rating', 'format' => 'rating'],
    ['label' => term('review', '후기') . ' 수', 'key' => 'review_count', 'format' => 'number'],
    ['label' => term('doctor', '의사') . ' 수', 'key' => 'doctor_count', 'format' => 'number'],
    ['label' => '최저 ' . term('service', '시술') . '가', 'key' => 'min_price', 'format' => 'price'],
    ['label' => '진료 분야', 'key' => 'treatment_categories', 'format' => 'text'],
    ['label' => '주소', 'key' => 'address', 'format' => 'text'],
    ['label' => '전화', 'key' => 'phone', 'format' => 'text'],
    ['label' => '운영시간', 'key' => 'business_hours', 'format' => 'text'],
];

?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo term('business', '병원'); ?> 비교 - <?php echo site_name(); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root { --primary: #AE1932; --bg: #f8f9fa; --card: #fff; --text: #1a1a1a; --muted: #888; --border: #eee; }
[data-bs-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e5e5e5; --muted: #999; --border: #444; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); max-width:640px; margin:0 auto; padding-bottom:80px; }

.page-header { padding:14px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--border); background:var(--card); position:sticky; top:0; z-index:10; }
.page-header .back-btn { width:36px; height:36px; border:none; background:none; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer; color:var(--text); }
.page-header h1 { font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; }

/* 병원 헤더 카드 */
.hospital-headers { display:flex; border-bottom:1px solid var(--border); background:var(--card); }
.hospital-col { flex:1; padding:12px 8px; text-align:center; border-right:1px solid var(--border); }
.hospital-col:last-child { border-right:none; }
.hospital-col img { width:60px; height:60px; border-radius:12px; object-fit:cover; margin-bottom:6px; }
.hospital-col .no-img { width:60px; height:60px; border-radius:12px; background:var(--border); display:inline-flex; align-items:center; justify-content:center; font-size:calc(24px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); margin-bottom:6px; }
.hospital-col .h-name { font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hospital-col .h-name a { color:var(--text); text-decoration:none; }

/* 비교 테이블 */
.compare-row { display:flex; border-bottom:1px solid var(--border); }
.compare-row:nth-child(even) { background:var(--bg); }
.compare-label { width:90px; flex-shrink:0; padding:10px 12px; font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; color:var(--muted); display:flex; align-items:center; border-right:1px solid var(--border); }
.compare-values { flex:1; display:flex; }
.compare-val { flex:1; padding:10px 8px; font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); text-align:center; border-right:1px solid var(--border); word-break:break-all; }
.compare-val:last-child { border-right:none; }
.compare-val.best { color:var(--primary); font-weight:700; }
.compare-val .rating-stars { color:#f59e0b; }

/* 빈 상태: 병원 선택 UI */
.select-ui { padding:20px 16px; }
.select-ui h2 { font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom:12px; }
.select-ui .search-input { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:10px; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); background:var(--card); color:var(--text); margin-bottom:12px; }
.select-ui .selected-list { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.select-ui .selected-tag { padding:6px 12px; background:var(--primary); color:#fff; border-radius:20px; font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); display:flex; align-items:center; gap:4px; }
.select-ui .selected-tag .remove { cursor:pointer; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
.select-ui .hospital-option { padding:10px 12px; border:1px solid var(--border); border-radius:8px; margin-bottom:6px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
.select-ui .hospital-option:hover { border-color:var(--primary); }
.select-ui .hospital-option .opt-name { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:500; }
.select-ui .hospital-option .opt-rating { font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color:#f59e0b; }
.compare-btn { display:block; width:100%; padding:12px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; cursor:pointer; }
.compare-btn:disabled { opacity:0.5; cursor:not-allowed; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<div class="page-header">
    <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <h1><i class="bi bi-layout-three-columns"></i> <?php echo term('business', '병원'); ?> 비교</h1>
</div>

<?php if (count($hospitals) >= 2): ?>
<!-- 비교 결과 -->
<div class="hospital-headers">
    <?php foreach ($hospital_ids as $hid):
        $h = $hospitals[$hid] ?? null;
        if (!$h) continue;
    ?>
    <div class="hospital-col">
        <?php if ($h['main_image_url']): ?>
        <img src="<?php echo htmlspecialchars($h['main_image_url']); ?>" alt="">
        <?php else: ?>
        <div class="no-img"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <div class="h-name"><a href="detail.php?id=<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></a></div>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach ($compare_items as $item): ?>
<div class="compare-row">
    <div class="compare-label"><?php echo $item['label']; ?></div>
    <div class="compare-values">
        <?php
        $values = [];
        foreach ($hospital_ids as $hid) {
            $h = $hospitals[$hid] ?? null;
            $val = $h[$item['key']] ?? null;
            $values[] = $val;
        }

        // 최고값 찾기
        $best_idx = -1;
        if ($item['format'] === 'rating' || $item['format'] === 'number') {
            $max_v = -1;
            foreach ($values as $i => $v) {
                if ((float)$v > $max_v) { $max_v = (float)$v; $best_idx = $i; }
            }
        } elseif ($item['format'] === 'price') {
            $min_v = PHP_INT_MAX;
            foreach ($values as $i => $v) {
                if ($v > 0 && (float)$v < $min_v) { $min_v = (float)$v; $best_idx = $i; }
            }
        }

        foreach ($values as $i => $val):
            $is_best = ($i === $best_idx && $val > 0);
        ?>
        <div class="compare-val <?php echo $is_best ? 'best' : ''; ?>">
            <?php
            if ($val === null || $val === '') {
                echo '-';
            } elseif ($item['format'] === 'rating') {
                echo '<span class="rating-stars">★</span> ' . number_format((float)$val, 1);
            } elseif ($item['format'] === 'number') {
                echo number_format((int)$val);
            } elseif ($item['format'] === 'price') {
                echo number_format((int)$val) . '원~';
            } else {
                echo htmlspecialchars(mb_substr($val, 0, 30));
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div style="padding:16px;text-align:center;">
    <a href="hospital_compare.php" style="color:var(--primary);font-size:14px;text-decoration:none;"><i class="bi bi-arrow-repeat"></i> 다른 <?php echo term('business', '병원'); ?> 비교하기</a>
</div>

<?php else: ?>
<!-- 병원 선택 UI -->
<div class="select-ui">
    <h2>비교할 <?php echo term('business', '병원'); ?>을 선택하세요 (2~3개)</h2>
    <input type="text" id="compare-search" class="search-input" placeholder="<?php echo term('business', '병원'); ?>명으로 검색...">
    <div class="selected-list" id="selected-list"></div>
    <div id="hospital-options"></div>
    <button class="compare-btn" id="compare-btn" disabled onclick="doCompare()">비교하기</button>
</div>

<script>
var allHospitals = <?php
    $all = [];
    // Re-connect briefly for list
    $c2 = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
    if ($c2) {
        mysqli_set_charset($c2, 'utf8mb4');
        $r2 = mysqli_query($c2, "SELECT id, name, rating, review_count FROM gangnamunni_hospitals ORDER BY rating DESC LIMIT 100");
        if ($r2) while ($row = mysqli_fetch_assoc($r2)) $all[] = $row;
        mysqli_close($c2);
    }
    echo json_encode($all, JSON_UNESCAPED_UNICODE);
?>;

var selected = [];

function render() {
    var listEl = document.getElementById('selected-list');
    listEl.innerHTML = '';
    selected.forEach(function(h) {
        var tag = document.createElement('span');
        tag.className = 'selected-tag';
        tag.innerHTML = escHtml(h.name) + ' <span class="remove" onclick="removeHospital(' + h.id + ')">&times;</span>';
        listEl.appendChild(tag);
    });
    document.getElementById('compare-btn').disabled = selected.length < 2;
    renderOptions();
}

function renderOptions() {
    var q = document.getElementById('compare-search').value.toLowerCase();
    var optEl = document.getElementById('hospital-options');
    optEl.innerHTML = '';

    var filtered = allHospitals.filter(function(h) {
        if (selected.find(function(s) { return s.id == h.id; })) return false;
        if (q && h.name.toLowerCase().indexOf(q) < 0) return false;
        return true;
    }).slice(0, 20);

    filtered.forEach(function(h) {
        var div = document.createElement('div');
        div.className = 'hospital-option';
        div.onclick = function() { addHospital(h); };
        div.innerHTML = '<span class="opt-name">' + escHtml(h.name) + '</span>' +
            '<span class="opt-rating">★ ' + Number(h.rating).toFixed(1) + ' (' + h.review_count + ')</span>';
        optEl.appendChild(div);
    });
}

function addHospital(h) {
    if (selected.length >= 3) { alert('최대 3개까지 비교 가능합니다.'); return; }
    selected.push(h);
    render();
}

function removeHospital(id) {
    selected = selected.filter(function(h) { return h.id != id; });
    render();
}

function doCompare() {
    var ids = selected.map(function(h) { return h.id; }).join(',');
    window.location.href = 'hospital_compare.php?ids=' + ids;
}

function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

document.getElementById('compare-search').addEventListener('input', renderOptions);
render();
</script>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php mysqli_close($connect); ?>
</body>
</html>

<?php
/**
 * ========================================
 * 병원 목록 - 3뷰 모드 (카드형/리스트형/서울미녀형)
 * ========================================
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/settings_helper.php';

$db_categories = loadCategoriesFromDB($connect);

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$escaped_keyword = mysqli_real_escape_string($connect, $keyword);
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);
$region = isset($_GET['region']) ? trim($_GET['region']) : '';
$escaped_region = mysqli_real_escape_string($connect, $region);

// 뷰 설정
$view_card_enabled = getSettingValue($connect, 'hospital_view_card_enabled', '1');
$view_list_enabled = getSettingValue($connect, 'hospital_view_list_enabled', '1');
$view_seoul_enabled = getSettingValue($connect, 'hospital_view_seoul_enabled', '1');
$view_default = getSettingValue($connect, 'hospital_view_default', 'seoul');

// 스타일 설정
$s = [];
$style_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='hospital_view_style'");
if ($style_q) { while ($sr = mysqli_fetch_assoc($style_q)) $s[$sr['setting_key']] = $sr['setting_value']; }
$sv = function($key, $default) use ($s) { return $s[$key] ?? $default; };

// 스타일 변수
$seoul_btn   = $sv('hv_seoul_btn_color', '#AE1932');
$seoul_btxt  = $sv('hv_seoul_btn_text', '#ffffff');
$seoul_name  = $sv('hv_seoul_name_size', '18');
$seoul_addr  = $sv('hv_seoul_addr_size', '13');
$seoul_tag_s = $sv('hv_seoul_tag_size', '12');
$seoul_tag_c = $sv('hv_seoul_tag_color', '#AE1932');
$seoul_cta   = $sv('hv_seoul_cta_size', '14');
$card_btn    = $sv('hv_card_btn_color', '#AE1932');
$card_btxt   = $sv('hv_card_btn_text', '#ffffff');
$card_name   = $sv('hv_card_name_size', '15');
$card_tag_s  = $sv('hv_card_tag_size', '11');
$card_tag_c  = $sv('hv_card_tag_color', '#AE1932');
$card_cta    = $sv('hv_card_cta_size', '13');
$list_btn    = $sv('hv_list_btn_color', '#AE1932');
$list_btxt   = $sv('hv_list_btn_text', '#ffffff');
$list_name   = $sv('hv_list_name_size', '15');
$list_tag_s  = $sv('hv_list_tag_size', '11');
$list_tag_c  = $sv('hv_list_tag_color', '#AE1932');
$list_cta    = $sv('hv_list_cta_size', '12');

// 병원 검색
$hospitals = [];
$query = "SELECT * FROM gangnamunni_hospitals WHERE is_active=1 AND (status='approved' OR status IS NULL)";
if (!empty($keyword)) {
    $query .= " AND (name LIKE '%{$escaped_keyword}%' OR address LIKE '%{$escaped_keyword}%' OR treatment_tags LIKE '%{$escaped_keyword}%')";
}
if (!empty($category)) { $query .= " AND treatment_tags LIKE '%{$escaped_category}%'"; }
if (!empty($region)) { $query .= " AND address LIKE '%{$escaped_region}%'"; }
$query .= " ORDER BY is_featured DESC, rating DESC, review_count DESC LIMIT 100";
$result = mysqli_query($connect, $query);
if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }

$page_title = term('business');
$show_search = false;

$page_css = '
    .search-header { padding: 12px 20px; }
    .search-header-content { max-width: 640px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
    .back-btn { font-size: 24px; cursor: pointer; text-decoration: none; color: inherit; }
    .search-bar { flex: 1; }
    .search-input { width: 100%; padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 14px; background: var(--bs-tertiary-bg, #F5F5F5); color: inherit; }
    .search-input:focus { outline: none; border-color: #AE1932; }
    .results-header { margin-bottom: 16px; }
    .results-header h1 { font-size: 20px; margin-bottom: 8px; }
    .search-count { color: var(--bs-secondary-color, #666); font-size: 14px; }
    .search-keyword { color: #AE1932; font-weight: bold; }

    /* 그리드 */
    .hospital-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 40px; }
    .hospital-card { background: var(--bs-body-bg, #fff); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s; position: relative; display: flex; flex-direction: column; }
    .hospital-card:hover { transform: translateY(-4px); box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
    .card-link { cursor: pointer; flex: 1; }
    .hospital-image { width: 100%; height: 160px; object-fit: cover; background: var(--bs-tertiary-bg, #F5F5F5); display: block; overflow: hidden; }
    .hospital-info { padding: 12px 16px 6px; }
    .hospital-name { font-weight: 700; margin-bottom: 4px; color: var(--bs-body-color, #000); }
    .hospital-address { font-size: 12px; color: var(--bs-secondary-color, #999); margin-bottom: 6px; }
    .rating-section { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
    .rating-score { font-weight: 700; color: #AE1932; }
    .review-count { font-size: 12px; color: var(--bs-secondary-color, #666); }

    /* 태그 - 가로 스크롤 */
    .badge-group { display: flex; gap: 6px; margin-top: 6px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 4px; scrollbar-width: none; }
    .badge-group::-webkit-scrollbar { display: none; }
    .tag-badge { display: inline-block; padding: 3px 10px; border: 1px solid currentColor; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }

    /* CTA 버튼 - 중앙 균등 */
    .card-cta { display: flex; gap: 10px; padding: 10px 16px 14px; }
    .card-cta .btn { flex: 1; text-align: center; border-radius: 8px; font-weight: 500; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 4px; }
    .btn-consult { border: none; }
    .btn-consult:hover { filter: brightness(0.9); }
    .btn-detail { background: transparent; border: 1px solid var(--bs-border-color, #ddd); color: var(--bs-body-color, #666); }
    .btn-detail:hover { background: var(--bs-tertiary-bg, #f0f0f0); }

    /* 필터 */
    .filter-section { border-bottom: 1px solid var(--bs-border-color, #F5F5F5); padding: 12px 16px; display: flex; gap: 8px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
    .filter-section::-webkit-scrollbar { display: none; }
    .filter-btn { padding: 8px 16px; border: 1px solid var(--bs-border-color, #E0E0E0); border-radius: 20px; background: var(--bs-body-bg, #fff); color: var(--bs-secondary-color, #666); font-size: 14px; cursor: pointer; transition: all 0.3s; white-space: nowrap; }
    .filter-btn:hover { border-color: #AE1932; color: #AE1932; }
    .filter-btn.active { background: #AE1932; color: white; border-color: #AE1932; }
    .region-btn { display: inline-block; padding: 6px 14px; border: 1px solid var(--bs-border-color, #e0e0e0); border-radius: 20px; background: var(--bs-body-bg, #fff); color: var(--bs-secondary-color, #666); font-size: 13px; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
    .region-btn:hover { border-color: #AE1932; color: #AE1932; }
    .region-btn.active { background: #AE1932; color: #fff; border-color: #AE1932; }
    .view-toggle { display: flex; gap: 8px; margin-bottom: 16px; }
    .view-btn { flex: 1; padding: 10px; border: 1px solid var(--bs-border-color, #E0E0E0); border-radius: 8px; background: var(--bs-body-bg, #fff); color: var(--bs-secondary-color, #666); font-size: 13px; cursor: pointer; transition: all 0.3s; text-align: center; }
    .view-btn:hover { border-color: #AE1932; color: #AE1932; }
    .view-btn.active { background: #AE1932; color: white; border-color: #AE1932; }

    /* === 카드형 (DB 스타일) === */
    .hospital-grid:not(.list-view):not(.seoul-view) .hospital-name { font-size: ' . $card_name . 'px; }
    .hospital-grid:not(.list-view):not(.seoul-view) .tag-badge { font-size: ' . $card_tag_s . 'px; color: ' . $card_tag_c . '; border-color: ' . $card_tag_c . '; }
    .hospital-grid:not(.list-view):not(.seoul-view) .btn-consult { background: ' . $card_btn . '; color: ' . $card_btxt . '; font-size: ' . $card_cta . 'px; padding: 8px 0; }

    /* === 리스트형 === */
    .hospital-grid.list-view { grid-template-columns: 1fr; }
    .hospital-grid.list-view .hospital-card { flex-direction: row; }
    .hospital-grid.list-view .hospital-image { width: 120px; height: 120px; flex-shrink: 0; }
    .hospital-grid.list-view .card-link { display: flex; flex-direction: row; flex: 1; min-width: 0; }
    .hospital-grid.list-view .hospital-info { flex: 1; padding: 10px 12px; min-width: 0; }
    .hospital-grid.list-view .card-cta { flex-direction: column; width: auto; padding: 10px 12px; justify-content: center; min-width: 90px; }
    .hospital-grid.list-view .btn-detail { display: none; }
    .hospital-grid.list-view .hospital-name { font-size: ' . $list_name . 'px; }
    .hospital-grid.list-view .tag-badge { font-size: ' . $list_tag_s . 'px; color: ' . $list_tag_c . '; border-color: ' . $list_tag_c . '; }
    .hospital-grid.list-view .btn-consult { background: ' . $list_btn . '; color: ' . $list_btxt . '; font-size: ' . $list_cta . 'px; padding: 8px 12px; }

    /* === 서울미녀형 === */
    .hospital-grid.seoul-view { grid-template-columns: 1fr; gap: 20px; max-width: 640px; margin: 0 auto; }
    .hospital-grid.seoul-view .hospital-card { border-radius: 16px; }
    .hospital-grid.seoul-view .hospital-image { width: 100%; height: unset; aspect-ratio: 16/9; object-fit: cover; border-radius: 0; }
    .hospital-grid.seoul-view .hospital-info { padding: 14px 16px 6px; }
    .hospital-grid.seoul-view .hospital-name { font-size: ' . $seoul_name . 'px; }
    .hospital-grid.seoul-view .hospital-address { font-size: ' . $seoul_addr . 'px; }
    .hospital-grid.seoul-view .addr-full { display: inline !important; }
    .hospital-grid.seoul-view .addr-short { display: none !important; }
    .hospital-grid.seoul-view .tag-badge { font-size: ' . $seoul_tag_s . 'px; color: ' . $seoul_tag_c . '; border-color: ' . $seoul_tag_c . '; }
    .hospital-grid.seoul-view .btn-consult { background: ' . $seoul_btn . '; color: ' . $seoul_btxt . '; font-size: ' . $seoul_cta . 'px; padding: 10px 0; }
    .hospital-grid.seoul-view .card-cta { padding: 10px 16px 16px; }

    .addr-full { display: none; }
    .addr-short { display: inline; }
    .no-result { text-align: center; padding: 80px 20px; color: var(--bs-secondary-color, #999); }
    .ad-card { text-decoration: none !important; }
    .ad-badge { position: absolute; top: 8px; right: 8px; background: #2E75B6; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; z-index: 5; }

    @media (max-width: 768px) {
        .hospital-grid { grid-template-columns: 1fr; }
        .hospital-grid.seoul-view { max-width: 100%; }
    }
';

$page_js = '';
include __DIR__ . '/includes/header.php';
?>

    <?php $ad_page_key = 'hospitals'; include __DIR__ . '/includes/ad_banner.php'; ?>

    <div class="search-header">
        <div class="search-header-content">
            <a href="index.php" class="back-btn">&#8592;</a>
            <div class="search-bar">
                <form method="GET" action="hospitals.php">
                    <input type="text" name="q" class="search-input" placeholder="병원명, 주소 검색..." value="<?php echo htmlspecialchars($keyword); ?>">
                </form>
            </div>
        </div>
    </div>

    <div class="filter-section">
        <button class="filter-btn <?php echo empty($category) ? 'active' : ''; ?>" onclick="filterCategory('')">전체</button>
        <?php if (!empty($db_categories)): foreach ($db_categories as $cat): ?>
        <button class="filter-btn <?php echo $category == $cat['name'] ? 'active' : ''; ?>" onclick="filterCategory('<?php echo htmlspecialchars($cat['name']); ?>')"><?php echo htmlspecialchars($cat['name']); ?></button>
        <?php endforeach; endif; ?>
    </div>

    <div style="padding: 0 16px; margin-bottom: 12px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch;">
        <div style="display: inline-flex; gap: 8px; padding: 4px 0;">
            <button type="button" class="region-btn active" onclick="filterRegion('')" data-region="">전체</button>
            <button type="button" class="region-btn" onclick="filterRegion('강남')" data-region="강남">강남/서초</button>
            <button type="button" class="region-btn" onclick="filterRegion('신사')" data-region="신사">신사/압구정</button>
            <button type="button" class="region-btn" onclick="filterRegion('잠실')" data-region="잠실">잠실/송파</button>
            <button type="button" class="region-btn" onclick="filterRegion('여의도')" data-region="여의도">여의도/마포</button>
            <button type="button" class="region-btn" onclick="filterRegion('홍대')" data-region="홍대">홍대/신촌</button>
            <button type="button" class="region-btn" onclick="filterRegion('종로')" data-region="종로">종로/을지로</button>
            <button type="button" class="region-btn" onclick="filterRegion('수원')" data-region="수원">수원/분당</button>
        </div>
    </div>

    <div class="results-header" style="padding: 0 16px;">
        <?php if (!empty($keyword)): ?>
        <h1><span class="search-keyword">'<?php echo htmlspecialchars($keyword); ?>'</span> 검색 결과</h1>
        <?php endif; ?>
        <?php if (!empty($category)): ?>
        <span style="display:inline-block;background:#AE1932;color:#fff;font-size:13px;padding:4px 12px;border-radius:12px;"><?php echo htmlspecialchars($category); ?></span>
        <?php endif; ?>
        <p class="search-count">총 <strong><?php echo count($hospitals); ?></strong>개 병원</p>
    </div>

    <?php if (!empty($hospitals)): ?>
    <div class="view-toggle" style="padding: 0 16px;">
        <?php if ($view_card_enabled === '1'): ?>
        <button class="view-btn" onclick="switchView('card')" id="view-card"><i class="bi bi-grid"></i> 카드형</button>
        <?php endif; ?>
        <?php if ($view_list_enabled === '1'): ?>
        <button class="view-btn" onclick="switchView('list')" id="view-list"><i class="bi bi-list-ul"></i> 리스트형</button>
        <?php endif; ?>
        <?php if ($view_seoul_enabled === '1'): ?>
        <button class="view-btn" onclick="switchView('seoul')" id="view-seoul"><i class="bi bi-star"></i> 서울미녀형</button>
        <?php endif; ?>
    </div>

<?php
require_once __DIR__ . '/includes/settings_helper.php';
$_infeed_enabled = getSettingValue($connect, 'ad_show_hospitals_infeed', '1');
$_infeed_interval = (int)getSettingValue($connect, 'ad_infeed_interval', '4');
$_infeed_ads = [];
if ($_infeed_enabled === '1') {
    $_iq = mysqli_query($connect, "SELECT a.* FROM gangnamunni_ads a
        WHERE a.status = 'approved' AND a.ad_type = 'infeed'
          AND EXISTS (
              SELECT 1 FROM gangnamunni_ad_slot_bookings b
              WHERE b.advertiser_id = a.advertiser_id
                AND b.status = 'active'
                AND b.start_date <= CURDATE()
                AND (b.end_date IS NULL OR b.end_date >= CURDATE())
          )
        ORDER BY a.display_order ASC LIMIT 10");
    if ($_iq) { while ($_ir = mysqli_fetch_assoc($_iq)) $_infeed_ads[] = $_ir; }
}
$_ad_idx = 0;
?>
    <div class="hospital-grid" id="hospital-grid" style="padding: 0 16px;">
<?php
$__hi = 0;
foreach ($hospitals as $hospital):
    if ($__hi > 0 && $_infeed_enabled === '1' && $_infeed_interval > 0 && $__hi % $_infeed_interval === 0 && isset($_infeed_ads[$_ad_idx])):
        $_iad = $_infeed_ads[$_ad_idx];
?>
        <a href="<?php echo htmlspecialchars($_iad['link_url'] ?? '#'); ?>" class="hospital-card ad-card" data-ad-id="<?php echo $_iad['id']; ?>">
            <span class="ad-badge">AD</span>
            <?php if (!empty($_iad['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($_iad['image_url']); ?>" class="hospital-image" alt="광고">
            <?php else: ?>
            <div class="hospital-image" style="background:linear-gradient(135deg,#AE1932,#6bb5ff);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;font-weight:bold;"><?php echo htmlspecialchars($_iad['title']); ?></div>
            <?php endif; ?>
            <div class="hospital-info">
                <div class="hospital-name"><?php echo htmlspecialchars($_iad['title']); ?></div>
                <div class="hospital-address"><?php echo htmlspecialchars($_iad['subtitle'] ?? ''); ?></div>
            </div>
        </a>
<?php
        $_ad_idx++;
    endif;
    $__hi++;

    $tags = [];
    if (!empty($hospital['treatment_tags'])) {
        $raw = json_decode($hospital['treatment_tags'], true);
        if (is_array($raw)) {
            foreach ($raw as $t) {
                if (is_array($t) && isset($t['name'])) $tags[] = $t['name'];
                elseif (is_string($t) && !empty($t)) $tags[] = $t;
            }
        }
    }
    $has_rating = (int)($hospital['review_count'] ?? 0) > 0;
    $rating_val = number_format((float)($hospital['rating'] ?? 0), 1);
    $review_cnt = number_format((int)($hospital['review_count'] ?? 0));
?>
        <div class="hospital-card" data-lat="<?php echo floatval($hospital['latitude'] ?? 0); ?>" data-lng="<?php echo floatval($hospital['longitude'] ?? 0); ?>">
            <div class="card-link" onclick="location.href='detail.php?id=<?php echo $hospital['id']; ?>'">
                <?php
                $h_img = $hospital['main_image_url'] ?? $hospital['profile_image_url'] ?? '';
                $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360'%3E%3Crect fill='%23e9ecef' width='640' height='360'/%3E%3Ctext x='50%25' y='45%25' fill='%23adb5bd' text-anchor='middle' font-size='18' font-family='sans-serif'%3E" . urlencode(htmlspecialchars($hospital['name'])) . "%3C/text%3E%3Ctext x='50%25' y='58%25' fill='%23ced4da' text-anchor='middle' font-size='13' font-family='sans-serif'%3ENo Image%3C/text%3E%3C/svg%3E";
                $img_src = !empty($h_img) ? htmlspecialchars($h_img) : $placeholder;
                ?>
                <img src="<?php echo $img_src; ?>"
                     alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                     class="hospital-image"
                     onerror="this.src='<?php echo $placeholder; ?>'">
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
                    <div class="hospital-address">
                        <i class="bi bi-geo-alt" style="font-size:11px;"></i>
                        <span class="addr-full"><?php echo htmlspecialchars($hospital['address'] ?? ''); ?></span>
                        <span class="addr-short"><?php echo htmlspecialchars(mb_substr($hospital['address'] ?? '', 0, 30)); ?>...</span>
                    </div>
                    <?php if ($has_rating): ?>
                    <div class="rating-section">
                        <span class="rating-score" style="font-size:14px;"><?php echo $rating_val; ?></span>
                        <span class="review-count">(평가 <?php echo $review_cnt; ?>개)</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($tags)): ?>
                    <div class="badge-group">
                        <?php foreach ($tags as $tname): ?>
                        <span class="tag-badge"><?php echo htmlspecialchars($tname); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-cta">
                <a href="consultation.php?hospital_id=<?php echo $hospital['id']; ?>&hospital_name=<?php echo urlencode($hospital['name']); ?>" class="btn btn-consult" onclick="event.stopPropagation();">
                    <i class="bi bi-chat-dots"></i> 상담 신청
                </a>
                <a href="detail.php?id=<?php echo $hospital['id']; ?>" class="btn btn-detail" onclick="event.stopPropagation();">
                    <i class="bi bi-info-circle"></i> 상세보기
                </a>
            </div>
        </div>
<?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-result">
        <div style="font-size:64px;margin-bottom:20px;">&#128269;</div>
        <div style="font-size:18px;margin-bottom:12px;">검색 결과가 없습니다</div>
        <div style="font-size:14px;">다른 검색어로 시도해보세요.</div>
    </div>
    <?php endif; ?>

<script>
function switchView(vt) {
    var g = document.getElementById("hospital-grid");
    if (!g) return;
    g.classList.remove("list-view", "seoul-view");
    if (vt === "list") g.classList.add("list-view");
    else if (vt === "seoul") g.classList.add("seoul-view");
    document.querySelectorAll(".view-btn").forEach(function(b) { b.classList.remove("active"); });
    var btn = document.getElementById("view-" + vt);
    if (btn) btn.classList.add("active");
    localStorage.setItem("hospital_view", vt);
}
function filterCategory(c) {
    var p = new URLSearchParams(window.location.search);
    if (c) p.set("category", c); else p.delete("category");
    window.location.href = "hospitals.php?" + p.toString();
}
function filterRegion(r) {
    var p = new URLSearchParams(window.location.search);
    if (r) p.set("region", r); else p.delete("region");
    window.location.href = "hospitals.php?" + p.toString();
}
document.addEventListener("DOMContentLoaded", function() {
    var dbDefault = "<?php echo $view_default; ?>";
    var saved = localStorage.getItem("hospital_view");
    var vt = saved || dbDefault || "seoul";
    if (!document.getElementById("view-" + vt)) {
        var first = document.querySelector(".view-btn");
        if (first) vt = first.id.replace("view-", "");
    }
    switchView(vt);
    var params = new URLSearchParams(window.location.search);
    var cr = params.get("region") || "";
    document.querySelectorAll(".region-btn").forEach(function(b) {
        b.classList.remove("active");
        if (b.getAttribute("data-region") === cr) b.classList.add("active");
    });
});
</script>

<?php
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

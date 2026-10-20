<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/page_cache.php';
gangnamunni_page_cache(1800, is_gangnamunni_logged_in() ? 'private' : 'public');
$hospital_return_url = 'hospitals.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
if (file_exists(__DIR__ . '/includes/design_tokens.php')) { include_once __DIR__ . '/includes/design_tokens.php'; }
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/ad_slot_performance.php';
require_once __DIR__ . '/includes/wishlist_button.php';

loadCategoriesFromDB($connect); 

require_once __DIR__ . '/includes/user_content_cache.php';
if (($_SERVER['HTTP_X_SB_CONTENT'] ?? '') === 'hospitals') {
    user_content_cache_reply(function () use ($connect, $hospital_return_url) {
        require __DIR__ . '/includes/hospital_list_data.php';
        require __DIR__ . '/includes/hospital_list_content.php';
    });
}
$use_content_cache = user_content_cache_enabled();
if (!$use_content_cache) {
    require __DIR__ . '/includes/hospital_list_data.php';
    $should_default_price_sort = !isset($_GET['sort']) && !empty($effective_treatment_id);
} else {
    $keyword = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $treatment = trim((string)($_GET['treatment'] ?? ''));
    $resolved_keyword_treatment = $treatment === '' && $keyword !== ''
        ? adSlotResolveTreatment($connect, $keyword)
        : null;
    $should_default_price_sort = $treatment !== '' || !empty($resolved_keyword_treatment);
    require_once __DIR__ . '/includes/hospital_dosage_filter.php';
    $effective_treatment_id = $resolved_keyword_treatment
        ? (int)$resolved_keyword_treatment['id']
        : hospital_dosage_resolve_treatment_id($connect, $treatment);
    $dosage_options = hospital_dosage_options($connect, $effective_treatment_id);
    $selected_dosage = hospital_dosage_selected($dosage_options, $_GET['dosage'] ?? '');
    $sort = (string)($_GET['sort'] ?? ($should_default_price_sort ? 'price' : 'manual'));
    if (!in_array($sort, ['manual', 'price', 'rating', 'popular', 'review'], true)) $sort = 'manual';
    $s = [];
    $style_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='hospital_view_style'");
    if ($style_q) { while ($sr = mysqli_fetch_assoc($style_q)) $s[$sr['setting_key']] = $sr['setting_value']; }
    $list_name = $s['hv_list_name_size'] ?? '15';
    $list_tag_s = $s['hv_list_tag_size'] ?? '11';
    $list_tag_c = $s['hv_list_tag_color'] ?? '#AC1830';
}

$page_title = term('business');
$show_search = true; 
// Claude 디자인 03-09: 검색 결과 화면에는 글로벌 네비게이션(로고 줄)이 없고 뒤로가기+검색어 바가 맨 위
if (trim((string)($_GET['q'] ?? '')) !== '') { $hide_global_nav = true; }

$page_css = '
    .results-header { margin-bottom: 16px; }
    .search-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #000; font-size: 12px; padding: 4px 10px; border: 1px solid #AC1830 }
    .search-filter-chip .chip-remove { cursor: pointer; font-size: 15px; line-height: 1; font-weight: 700; opacity: 0.9; }
    .search-filter-chip .chip-remove:hover { opacity: 1; }

    .hospital-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; width: 100%; padding-inline: 16px; margin-bottom: 40px; box-sizing: border-box; }
    .hospital-card { background: var(--bs-body-bg, #fff); border-radius: 0px; overflow: hidden; box-shadow: none; position: relative; display: flex; flex-direction: column; }
    .card-link { cursor: pointer; flex: 1; }
    .hospital-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #D9D9D9; display: block; }
    .hospital-info { padding: 12px 16px 6px; }
    .hospital-name { font-size: 16px; font-weight: 500; margin-bottom: 4px; color: var(--bs-body-color, #000000); }
    .hospital-address { font-size: 14px; font-weight: 400; color: var(--bs-body-color, #000000); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hospital-address * { font-size: 14px; }

    .hospital-grid.list-view { grid-template-columns: 1fr; }
    .hospital-grid.list-view .hospital-card { flex-direction: row; border-top: 1px solid #ddd; }
    .hospital-grid.list-view .hospital-card:last-child { border-bottom: 1px solid #ddd; }
    .card-wishlist { position: absolute; top: 8px; right: 8px; z-index: 3; }
    .hospital-grid.list-view .hospital-image { width: 100px; height: 100px; flex-shrink: 0; }
    .hospital-grid.list-view .card-link { display: flex; flex-direction: row; flex: 1; min-width: 0; }
    .hospital-grid.list-view .hospital-info { flex: 1; padding: 10px 12px; min-width: 0; }
    .hospital-grid.list-view .hospital-name { font-size: ' . $list_name . 'px; }

    /* 리스트 카드: 썸네일을 카드 안쪽(좌우 여백)에 테두리 있는 정사각형으로 배치 */
    .hospital-grid.list-view { gap: 0; padding-inline: 20px; }
    .hospital-grid.list-view .hospital-card { align-items: flex-start; padding: 22px 0; border-top: 1px solid #ddd; overflow: visible; }
    .hospital-grid.list-view .card-link { align-items: flex-start; }
    .hospital-grid.list-view .hospital-image { width: 100px; height: 100px; aspect-ratio: 1 / 1; object-fit: cover; border: 1px solid #c8c8c8; box-sizing: border-box; background: #fff; }
    .hospital-grid.list-view .hospital-info { padding: 0 36px 0 16px; }
    .hospital-grid.list-view .hospital-name { font-weight: 500; line-height: 1.35; margin-bottom: 6px; }
    .hospital-grid.list-view .card-wishlist { top: 20px; right: 0; }
    .hospital-grid.list-view .grade-badge { top: 26px; left: 4px; }
    .hospital-grid.list-view .addr-sep { color: #c8c8c8; margin: 0 6px; }
    .hospital-grid.list-view { grid-template-columns: minmax(0, 1fr); }
    .hospital-grid.list-view .hospital-card { min-width: 0; }
    .hospital-grid.list-view .hospital-address { white-space: normal; overflow: visible; text-overflow: clip; word-break: keep-all; overflow-wrap: anywhere; line-height: 1.45; }

    .addr-full { display: none; }
    .addr-short { display: inline; }
    .no-result { text-align: center; padding: 80px 20px; color: var(--bs-secondary-color, #999); }
    .ad-card { text-decoration: none !important; }
    .grade-badge { position: absolute; top: 8px; left: 8px; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; z-index: 5; line-height: 1.4; }
    .grade-badge-sa { background: #DC3545; color: #fff; }

    @media (max-width: 768px) {
        .hospital-grid { grid-template-columns: 1fr; }
    }

.card-tag-price { display: flex; flex-direction: column; gap: 0; margin-top: 6px; }
.card-tag-item { font-size: 18px; font-weight: 700; color: #000; padding: 2px 0; text-align: left; }
.card-price { width: 100%; text-align: left; font-size: 22px; line-height: 1; }
.card-price-num { font-size: 20px; font-weight: 700; color: #AC1830; line-height: 1; }
.card-price-unit { font-size: 20px; font-weight: 400; color: #000000; }
.card-price-sale { display: block; }
.card-price-max { font-size: 22px; color: #858484; text-decoration: line-through; margin-right: 2px; }
';

$_fs_css_file = __DIR__ . '/assets/css/figma_sync_index.css';
if (file_exists($_fs_css_file)) { $page_css .= "\n" . file_get_contents($_fs_css_file); }

include __DIR__ . '/includes/header.php';
?>
<?php
// 병원 이름 검색 결과 (hid=병원ID) — Claude 디자인 03-12/03-13
if (isset($_GET['hid']) && (int)$_GET['hid'] > 0) {
    ob_start();
    $__hsv = include __DIR__ . '/includes/hospital_search_view.php';
    $__hsv_html = ob_get_clean();
    if ($__hsv === true) {
        echo $__hsv_html;
        echo '<div style="height:70px;"></div>';
        include_once __DIR__ . '/includes/bottom_nav.php';
        include __DIR__ . '/includes/footer.php';
        mysqli_close($connect);
        exit;
    }
}
?>
    <script>
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var url = new URL(window.location.href);
        var needsRedirect = false;

        var regionParam = urlParams.get('region');
        if (regionParam) {
            localStorage.setItem('sb_selected_regions', JSON.stringify(regionParam.split(',').filter(Boolean)));
        } else {
            try {
                var savedRegions = JSON.parse(localStorage.getItem('sb_selected_regions') || '[]');
                if (savedRegions.length > 0) {
                    url.searchParams.set('region', savedRegions.join(','));
                    needsRedirect = true;
                }
            } catch(e) {}
        }

        var sortParam = urlParams.get('sort');
        if (sortParam) {
            localStorage.setItem('sb_selected_sort', sortParam);
        } else if (<?php echo $should_default_price_sort ? 'true' : 'false'; ?>) {
            url.searchParams.set('sort', 'price');
            localStorage.setItem('sb_selected_sort', 'price');
            needsRedirect = true;
        }

        if (needsRedirect) {
            window.location.replace(url.toString());
        }
    })();
    </script>

    <?php if (!empty($effective_treatment_id) && count($dosage_options) > 1): /* 용량 1개뿐이면 선택할 의미가 없으므로 숨김 */ ?>
    <div class="sr-dosage" id="dosageChips" role="group" aria-label="용량 선택">
        <span class="sr-dosage-t">용량</span>
        <button type="button" class="sr-chip<?php echo empty($selected_dosage) ? ' active' : ''; ?>" onclick="setDosage('')">전체</button>
        <?php foreach ($dosage_options as $_dopt):
            $_is_active = !empty($selected_dosage) && $selected_dosage['label'] === $_dopt['label']; ?>
        <button type="button" class="sr-chip<?php echo $_is_active ? ' active' : ''; ?>" onclick="setDosage(<?php echo htmlspecialchars(json_encode($_dopt['label'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo htmlspecialchars($_dopt['label']); ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="padding: 0 16px; margin-bottom: 8px; display: flex; justify-content: flex-start; align-items: center; gap: 8px;">
        <div id="sortChips" style="display:flex; gap:6px;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
            <?php if (!empty($effective_treatment_id)): /* 시술 기준 정렬일 때만 낮은가격 노출 */ ?>
            <button onclick="setSort('price')" class="sort-chip<?php echo $sort==='price' ? ' active' : ''; ?>"><img src="assets/icons/categories/<?php echo $sort==='price' ? 'low_price_2.svg' : 'low_price_1.png'; ?>" class="sort-chip-icon"> 낮은가격</button>
            <?php endif; ?>
            <button onclick="setSort('rating')" class="sort-chip<?php echo $sort==='rating' ? ' active' : ''; ?>"><img src="assets/icons/categories/<?php echo $sort==='rating' ? 'rating_2.svg' : 'rating_1.png'; ?>" class="sort-chip-icon"> 평점</button>
            <button onclick="setSort('popular')" class="sort-chip<?php echo $sort==='popular' ? ' active' : ''; ?>"><i class="bi bi-trophy"></i> 인기</button>
            <button onclick="setSort('review')" class="sort-chip<?php echo $sort==='review' ? ' active' : ''; ?>"><img src="assets/icons/icon-mypage-review.png" class="sort-chip-icon<?php echo $sort==='review' ? '' : ' sort-chip-icon-dim'; ?>" alt=""> <?php echo term('review', '후기'); ?></button>

        </div>
        <style>
        .sort-chip { padding:5px 10px;border:1px solid #AAAAAA;border-radius:5px;background:var(--bs-body-bg, #fff);font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:400;cursor:pointer;color:#AAAAAA;transition:all 0.2s;white-space:nowrap; line-height:1.2; display:inline-flex;align-items:center;gap:7px; }
        .sort-chip i { font-size:calc(25px * var(--fs-scale,1) * var(--fs-page,1));width:25px;height:25px;display:inline-flex;align-items:center;justify-content:center; }
        .sort-chip-icon { width:22px;height:22px;object-fit:contain;display:inline-block;vertical-align:middle; }
        .sort-chip-icon-dim { opacity:.33; }
        .sort-chip.active { background:#FFFFFF;color:#000000;border:1px solid #000000;font-weight:600; }
        .sort-chip:hover:not(.active) { border-color:#000000;color:#000000; }
        #sortChips::-webkit-scrollbar { display:none; } 
        @media (max-width:480px){
          .sort-chip { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); padding:5px 9px; }
          .sort-chip i { font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));width:18px;height:18px; }
        }
        </style>
    </div>

    <div class="results-header" style="padding: 0 16px;">
        <?php if (!empty($category)): ?>
        <span class="search-filter-chip"><?php echo htmlspecialchars($category); ?><span class="chip-remove" onclick="removeSearchParam('category')" role="button" aria-label="필터 제거">&times;</span></span>
        <?php endif; ?>
        <?php if (!empty($treatment)): ?>
        <span class="search-filter-chip"><?php echo htmlspecialchars($treatment); ?><span class="chip-remove" onclick="removeSearchParam('treatment')" role="button" aria-label="필터 제거">&times;</span></span>
        <?php endif; ?>
    </div>
    <script>
    function removeSearchParam(name) {
        var u = new URL(window.location.href);
        u.searchParams.delete(name);
        if (name === 'treatment' || name === 'q') u.searchParams.delete('dosage');
        window.location.href = u.pathname + u.search;
    }
    </script>

<?php
if ($use_content_cache) {
    user_content_cache_mount('hospitals');
} else {
    require __DIR__ . '/includes/hospital_list_content.php';
}
?>

<script>
// URL의 dosage가 현재 시술의 유효한 용량이 아니면 URL에서 제거 (UI와 URL 일치)
(function() {
    var u = new URL(window.location.href);
    if (u.searchParams.has('dosage') && <?php echo empty($selected_dosage) ? 'true' : 'false'; ?>) {
        u.searchParams.delete('dosage');
        history.replaceState(null, '', u.pathname + u.search + u.hash);
    }
})();
function toggleDosageUnit(btn) {
    var group = btn.parentNode;
    // 열린 상태의 '전체' 클릭: 선택된 용량이 있으면 해제(전체 보기), 없으면 접기
    if (group.classList.contains('open') && btn.getAttribute('data-has-selected') === '1') {
        setDosage('');
        return;
    }
    var open = !group.classList.contains('open');
    // 아코디언: 하나의 단위를 열면 다른 단위는 접기 (선택된 용량 필터는 그대로 유지)
    if (open) {
        document.querySelectorAll('#dosageChips .dosage-group.open').forEach(function(other) {
            if (other === group) return;
            other.classList.add('touched');
            other.classList.remove('open');
            var ob = other.querySelector('.dosage-unit-btn');
            if (ob) ob.setAttribute('aria-expanded', 'false');
            other.querySelectorAll('.dosage-chip').forEach(function(c) { c.tabIndex = -1; });
        });
    }
    group.classList.add('touched');
    group.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    group.querySelectorAll('.dosage-chip').forEach(function(c) { c.tabIndex = open ? 0 : -1; });
    if (open) {
        // 열린 단위가 화면 밖이면 가로 스크롤로 맞춤
        var box = document.getElementById('dosageChips');
        if (box && box.scrollWidth > box.clientWidth) {
            setTimeout(function() { box.scrollTo({ left: Math.max(0, group.offsetLeft - box.offsetLeft - 4), behavior: 'smooth' }); }, 50);
        }
    }
}
function srResetFilters() {
    try { localStorage.removeItem('sb_selected_regions'); } catch(e) {}
    var u = new URL(window.location);
    u.searchParams.delete('region'); u.searchParams.delete('dosage');
    window.location = u;
}
function setDosage(val) {
    var url = new URL(window.location);
    if (val) url.searchParams.set('dosage', val); else url.searchParams.delete('dosage');
    window.location = url;
}
function setSort(val) {
    var url = new URL(window.location);
    url.searchParams.set('sort', val);
    window.location = url;
}
</script>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
renderWishlistScript();
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

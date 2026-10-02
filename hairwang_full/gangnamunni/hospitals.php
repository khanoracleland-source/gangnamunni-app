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
    $list_tag_c = $s['hv_list_tag_color'] ?? '#AE1932';
}

$page_title = term('business');
$show_search = true; 

$page_css = '
    .results-header { margin-bottom: 16px; }
    .search-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #000; font-size: 12px; padding: 4px 10px; border: 1px solid #AE1932 }
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
.card-price-num { font-size: 20px; font-weight: 700; color: #AE1932; line-height: 1; }
.card-price-unit { font-size: 20px; font-weight: 400; color: #000000; }
.card-price-sale { display: block; }
.card-price-max { font-size: 22px; color: #858484; text-decoration: line-through; margin-right: 2px; }
';

$_fs_css_file = __DIR__ . '/assets/css/figma_sync_index.css';
if (file_exists($_fs_css_file)) { $page_css .= "\n" . file_get_contents($_fs_css_file); }

include __DIR__ . '/includes/header.php';
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

    <div style="padding: 0 16px; margin-bottom: 8px; display: flex; justify-content: flex-start; align-items: center; gap: 8px;">
        <div id="sortChips" style="display:flex; gap:6px;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
            <button onclick="setSort('price')" class="sort-chip<?php echo $sort==='price' ? ' active' : ''; ?>"><img src="assets/icons/categories/<?php echo $sort==='price' ? 'low_price_2.svg' : 'low_price_1.png'; ?>" class="sort-chip-icon"> 낮은가격</button>
            <button onclick="setSort('rating')" class="sort-chip<?php echo $sort==='rating' ? ' active' : ''; ?>"><img src="assets/icons/categories/<?php echo $sort==='rating' ? 'rating_2.svg' : 'rating_1.png'; ?>" class="sort-chip-icon"> 평점</button>
            <button onclick="setSort('popular')" class="sort-chip<?php echo $sort==='popular' ? ' active' : ''; ?>"><i class="bi bi-trophy"></i> 인기</button>
            <button onclick="setSort('review')" class="sort-chip<?php echo $sort==='review' ? ' active' : ''; ?>"><i class="bi bi-chat-square-text"></i> <?php echo term('review', '후기'); ?></button>

        </div>
        <style>
        .sort-chip { padding:5px 10px;border:1px solid #AAAAAA;border-radius:5px;background:var(--bs-body-bg, #fff);font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:400;cursor:pointer;color:#AAAAAA;transition:all 0.2s;white-space:nowrap; line-height:1.2; display:inline-flex;align-items:center;gap:7px; }
        .sort-chip i { font-size:calc(25px * var(--fs-scale,1) * var(--fs-page,1));width:25px;height:25px;display:inline-flex;align-items:center;justify-content:center; }
        .sort-chip-icon { width:22px;height:22px;object-fit:contain;display:inline-block;vertical-align:middle; }
        .sort-chip.active { background:#FFFFFF;color:#000000;border:1px solid #000000;font-weight:600; }
        .sort-chip:hover:not(.active) { border-color:#000000;color:#000000; }
        #sortChips::-webkit-scrollbar { display:none; } 
        @media (max-width:480px){
          .sort-chip { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); padding:5px 9px; }
          .sort-chip i { font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));width:18px;height:18px; }
        }
        </style>
    </div>

    <?php if (!empty($effective_treatment_id) && !empty($dosage_options)): ?>
    <?php
    // 단위별 그룹: ['cc' => [opt, opt...], '샷' => [...]]
    $_dosage_groups = [];
    foreach ($dosage_options as $_dopt) { $_dosage_groups[$_dopt['unit']][] = $_dopt; }
    ?>
    <div style="padding: 0 16px; margin-bottom: 10px;">
        <div id="dosageChips" role="group" aria-label="용량 선택">
            <?php $_gi = 0; foreach ($_dosage_groups as $_unit => $_opts): $_gi++;
                $_group_open = !empty($selected_dosage) && $selected_dosage['unit'] === $_unit; ?>
            <div class="dosage-group<?php echo $_group_open ? ' open' : ''; ?>" id="dosageGroup<?php echo $_gi; ?>">
                <button type="button" class="dosage-unit-btn" aria-expanded="<?php echo $_group_open ? 'true' : 'false'; ?>" aria-controls="dosageGroup<?php echo $_gi; ?>" data-has-selected="<?php echo $_group_open ? '1' : '0'; ?>" onclick="toggleDosageUnit(this)">
                    <span class="dosage-unit-label"><?php echo htmlspecialchars($_unit); ?></span><span class="dosage-all-label">전체</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
                <?php foreach ($_opts as $_ci => $_dopt):
                    $_is_active = !empty($selected_dosage) && $selected_dosage['label'] === $_dopt['label']; ?>
                <button type="button" style="--i:<?php echo $_ci; ?>" tabindex="<?php echo $_group_open ? '0' : '-1'; ?>" onclick="setDosage(<?php echo $_is_active ? "''" : htmlspecialchars(json_encode($_dopt['label'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)" class="dosage-chip<?php echo $_is_active ? ' active' : ''; ?>" aria-pressed="<?php echo $_is_active ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($_dopt['label']); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <style>
        #dosageChips { display:flex; align-items:center; gap:8px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding:2px 0; }
        #dosageChips::-webkit-scrollbar { display:none; }
        .dosage-group { display:flex; align-items:center; flex-shrink:0; }
        .dosage-unit-btn { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border:1px solid #AAAAAA; border-radius:20px; background:var(--bs-body-bg, #fff); color:#000; font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; line-height:1.3; cursor:pointer; white-space:nowrap; transition:border-color .2s; }
        .dosage-unit-btn i { font-size:12px; transition:transform .3s ease; }
        .dosage-group.open .dosage-unit-btn { border-color:#000; }
        .dosage-all-label { display:none; }
        .dosage-group.open .dosage-unit-label { display:none; }
        .dosage-group.open .dosage-all-label { display:inline; }
        .dosage-group.touched .dosage-unit-btn span { animation:dosageLabelIn .25s ease; }
        @keyframes dosageLabelIn { from { opacity:0; transform:translateY(3px); } to { opacity:1; transform:none; } }
        .dosage-group.open .dosage-unit-btn i { transform:rotate(180deg); }
        .dosage-chip { flex-shrink:0; max-width:0; margin-left:0; padding:4px 0; border:1px solid transparent; border-radius:20px; background:transparent; font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1)); color:#666; cursor:pointer; white-space:nowrap; line-height:1.3; overflow:hidden; opacity:0; transform:translateX(-12px); pointer-events:none;
            transition:max-width .3s ease, padding .3s ease, margin .3s ease, opacity .2s ease, transform .3s ease; transition-delay:0s; }
        .dosage-group.open .dosage-chip { max-width:120px; margin-left:6px; padding:4px 12px; opacity:1; transform:translateX(0); pointer-events:auto; transition-delay:calc(var(--i, 0) * 40ms); }
        .dosage-chip.active { border-color:#000; color:#000; font-weight:600; background:var(--bs-body-bg, #fff); }
        .dosage-chip:hover:not(.active) { color:#000; }
        @media (prefers-reduced-motion: reduce) { .dosage-chip, .dosage-unit-btn i { transition:none !important; } .dosage-unit-btn span { animation:none !important; } }
        </style>
    </div>
    <?php endif; ?>

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
    group.classList.add('touched');
    group.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    group.querySelectorAll('.dosage-chip').forEach(function(c) { c.tabIndex = open ? 0 : -1; });
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

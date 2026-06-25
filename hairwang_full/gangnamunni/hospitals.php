<?php
/**
 * ========================================
 * 병원 목록 - 3뷰 모드 (카드형/리스트형/서울미녀형)
 * ========================================
 */
require_once __DIR__ . '/includes/init.php';
if (file_exists(__DIR__ . '/includes/design_tokens.php')) { include_once __DIR__ . '/includes/design_tokens.php'; }
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/settings_helper.php';

$db_categories = loadCategoriesFromDB($connect);

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$escaped_keyword = mysqli_real_escape_string($connect, $keyword);
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);
$region = isset($_GET['region']) ? trim($_GET['region']) : '';
// 다중 지역 지원 (콤마 구분)
$region_list = array_filter(array_map('trim', explode(',', $region)));
$escaped_region = mysqli_real_escape_string($connect, $region);

// 정렬 옵션 (피그마: 낮은가격순/평점순/인기순/후기순)
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'price';
$allowed_sorts = ['price', 'rating', 'popular', 'review'];
if (!in_array($sort, $allowed_sorts)) $sort = 'price';
switch($sort) {
    case 'rating': $order_clause = 'rating DESC, review_count DESC'; break;
    case 'popular': $order_clause = 'wishlist_count DESC, rating DESC'; break;
    case 'review': $order_clause = 'review_count DESC, rating DESC'; break;
    default: $order_clause = 'min_price ASC, rating DESC'; // 낮은 가격순
}
// 광고 등급 우선 정렬 (S>A>B>C>D>E>none)
$order_clause = "FIELD(ad_grade,'S','A','B','C','D','E','none') ASC, " . $order_clause;

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
$card_name   = $sv('hv_card_name_size', '18');
$card_tag_s  = $sv('hv_card_tag_size', '13');
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
$query = "SELECT * FROM gangnamunni_hospitals
          WHERE is_active=1
            AND COALESCE(is_demo, 0)=0
            AND (status='approved' OR status IS NULL)";
if (!empty($keyword)) {
    $query .= " AND (name LIKE '%{$escaped_keyword}%' OR address LIKE '%{$escaped_keyword}%' OR treatment_tags LIKE '%{$escaped_keyword}%')";
}
if (!empty($category)) { $query .= " AND treatment_tags LIKE '%{$escaped_category}%'"; }
if (!empty($region_list)) {
    $region_conditions = [];
    foreach ($region_list as $r) {
        $r_esc = mysqli_real_escape_string($connect, $r);
        $region_conditions[] = "address LIKE '%{$r_esc}%'";
    }
    $query .= " AND (" . implode(' OR ', $region_conditions) . ")";
}
$query .= " ORDER BY " . $order_clause . " LIMIT 100";
$result = mysqli_query($connect, $query);
if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }

// 병원별 대표 시술 설명 조회 (가장 저렴한 활성 시술 1건)
$hospital_treatment_descs = [];
if (!empty($hospitals)) {
    $h_ids = array_column($hospitals, 'id');
    $h_ids_str = implode(',', array_map('intval', $h_ids));
    // [1차 검수 #5 - 2026-05-28] 대표 시술 가격(price_min) 함께 조회
    $_td_sql = "
        SELECT sub.hospital_id, sub.treatment_name, sub.description, sub.price_min, sub.price_max
        FROM (
            SELECT ht.hospital_id, tm.name AS treatment_name, tm.description, ht.price_min, ht.price_max,
                   ROW_NUMBER() OVER (PARTITION BY ht.hospital_id ORDER BY ht.is_promoted DESC, ht.price_min ASC, tm.popularity_score DESC) AS rn
            FROM gangnamunni_hospital_treatments ht
            INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
            WHERE ht.hospital_id IN ({$h_ids_str})
              AND ht.status = 'active'
              AND tm.is_active = 1
              AND tm.description IS NOT NULL
              AND tm.description != ''
        ) sub WHERE sub.rn = 1
    ";
    $_td_result = mysqli_query($connect, $_td_sql);
    if ($_td_result) {
        while ($_td_row = mysqli_fetch_assoc($_td_result)) {
            $hospital_treatment_descs[(int)$_td_row['hospital_id']] = [
                'name' => $_td_row['treatment_name'],
                'desc' => $_td_row['description'],
                'price_min' => $_td_row['price_min'] ?? null,
                'price_max' => $_td_row['price_max'] ?? null,
            ];
        }
    }
}

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
    .hospital-card { background: var(--bs-body-bg, #fff); border-radius: 0px; overflow: hidden; box-shadow: none; position: relative; display: flex; flex-direction: column; }
    .hospital-card:hover { }
    .card-link { cursor: pointer; flex: 1; }
    .hospital-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #D9D9D9; display: block; }
    .hospital-info { padding: 12px 16px 6px; }
    .hospital-name { font-size: 16px; font-weight: 500; margin-bottom: 4px; color: var(--bs-body-color, #000000); }
    .hospital-address { font-size: 14px; font-weight: 400; color: var(--bs-body-color, #000000); margin-bottom: 6px; }
    .hospital-address * { font-size: 14px; }
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
    .hospital-grid.seoul-view .hospital-card { border-radius: 0px; }
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
    /* 광고 등급 뱃지 (이미지 좌측 상단) */
    .grade-badge { position: absolute; top: 8px; left: 8px; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; z-index: 5; line-height: 1.4; }
    .grade-badge-sa { background: #DC3545; color: #fff; }
    .grade-badge-bc { background: #2E75B6; color: #fff; }
    .grade-badge-de { background: transparent; color: #999; font-size: 10px; font-weight: 500; padding: 0; position: absolute; top: 8px; left: 8px; }

    @media (max-width: 768px) {
        .hospital-grid { grid-template-columns: 1fr; }
        .hospital-grid.seoul-view { max-width: 100%; }
    }

/* 검색바 */
.hospital-search-wrap { padding: 8px 16px; display: flex; align-items: center; }
.hospital-search-input { width: 439px; height: 60px; padding: 10px 14px 10px 0; border-bottom: 2px solid #8f8d8d; font-size: 15px; outline: none; box-sizing: border-box; text-align: left; }
.hospital-search-input::placeholder { font-size: 13px; text-align: left; }
.hospital-search-btn { width: 230px; height: 60px; border: 2px solid #333; border-radius: 24px; background: #333; color: #ffffff; font-size: 15px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.hospital-search-btn i { color: #ffffff; }

/* 지역 선택 패널 */
#hRegionPanel { padding: 12px 0; background: var(--bs-body-bg, #fff); border-bottom: 1px solid var(--bs-border-color, #eee); }
#hRegionPanel .region-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px 8px; margin-bottom: 10px; padding: 0 16px; }
#hRegionPanel label { display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer; color: var(--bs-body-color, #333); transition: color 0.15s; }
#hRegionPanel label:has(.h-region-cb:checked) { color: #AE1932; font-weight: 600; }
#hRegionPanel .h-region-cb { accent-color: #AE1932; width: 16px; height: 16px; }
#hRegionPanel .region-btn-wrap { display: flex; align-items: center; justify-content: space-between;}
#hRegionPanel .region-selected-tags { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }
#hRegionPanel .region-tag { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border: 1px solid #AE1932; color: #000; border-radius: 3px; font-size: 12px; font-weight: 500; cursor: pointer; }
#hRegionPanel .btn-reset { flex-shrink: 0; font-size: 15px; cursor: pointer; padding-left: 15px; }
#hRegionPanel .btn-apply { flex-shrink: 0; padding: 8px; background: #AE1932; color: #fff; font-size: 15px; font-weight: 500; cursor: pointer; height: 40px; width: 190px;}
.card-tag-price { display: flex; align-items: center; margin-top: 6px; }
.card-tag-item { font-size: 16px; color: #000; padding: 2px 0; }
.card-price { flex: 1; text-align: center; }
.card-price-num { font-size: 16px; font-weight: 700; color: #AE1932; }
.card-price-unit { font-size: 12px; font-weight: 400; color: #000000; }
.card-price-max { font-size: 12px; color: #858484; text-decoration: line-through; margin-right: 2px; }
.region-active-bar { display: flex; align-items: center; gap: 8px; padding: 6px 16px; border-bottom: 1px solid var(--bs-border-color, #eee); }
.region-active-bar .btn-reset-bar { flex-shrink: 0; background: none; border: none; font-size: 15px; cursor: pointer; color: #000; padding: 0; }
.region-active-bar .region-active-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.region-active-bar .region-tag { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border: 1px solid #AE1932; color: #000; border-radius: 3px; font-size: 12px; font-weight: 500; }
';

$page_js = '';

// [Figma Sync - 2026-06-06] hospitals 검색바 매칭 CSS
$_fs_css_file = __DIR__ . '/assets/css/figma_sync_hospitals.css';
if (file_exists($_fs_css_file)) { $page_css .= "
" . file_get_contents($_fs_css_file); }

include __DIR__ . '/includes/header.php';
?>
    <!-- 피그마: 검색바 + 지역 선택 버튼 -->
    <div class="hospital-search-wrap">
        <input type="text" id="hospitalKeyword" class="hospital-search-input" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="<?php echo term('service', '시술'); ?>, <?php echo term('business', '병원'); ?> 이름을 검색해보세요" onkeypress="if(event.key==='Enter')applyHospitalFilter()">
        <button id="hRegionToggle" class="hospital-search-btn" onclick="var p=document.getElementById('hRegionPanel'),b=document.querySelector('.region-active-bar');var open=p.style.display==='none';p.style.display=open?'block':'none';if(b)b.style.display=open?'none':'flex';"">
            <i class="bi bi-geo-alt"></i> 지역 선택
        </button>
    </div>
    <!-- 선택된 지역 태그 (검색바 아래 고정) -->
    <?php if (!empty($region_list)): ?>
    <div class="region-active-bar">
        <button class="btn-reset-bar" onclick="document.querySelectorAll('.h-region-cb').forEach(c=>c.checked=false);applyHospitalFilter()"><i class="bi bi-arrow-counterclockwise"></i> 초기화</button>
        <div class="region-active-tags">
            <?php foreach ($region_list as $r): ?>
            <span class="region-tag"><?php echo htmlspecialchars($r); ?> <span onclick="removeRegion('<?php echo htmlspecialchars($r); ?>')" style="cursor:pointer;">&times;</span></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <!-- 지역 선택 패널 -->
    <div id="hRegionPanel" style="display:none;">
        <div class="region-grid">
            <?php
            $all_regions = ['강남','신사','압구정','청담','교대','삼성','송파','천호','목동','강북','노원','인천','성남','동탄','일산','의정부','부산','대구','대전','청주','광주','전주','춘천','제주'];
            foreach ($all_regions as $ar): ?>
            <label>
                <input type="checkbox" class="h-region-cb" value="<?php echo $ar; ?>"<?php echo in_array($ar, $region_list) ? ' checked' : ''; ?>>
                <?php echo $ar; ?>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="region-btn-wrap">
            <button class="btn-reset" onclick="document.querySelectorAll('.h-region-cb').forEach(c=>c.checked=false);updateSelectedTags()"><i class="bi bi-arrow-counterclockwise"></i> 초기화</button>
            <div class="region-selected-tags">
                <?php foreach ($region_list as $r): ?>
                <span class="region-tag"><?php echo htmlspecialchars($r); ?> <span onclick="removeRegion('<?php echo htmlspecialchars($r); ?>')">&times;</span></span>
                <?php endforeach; ?>
            </div>
            <button class="btn-apply" onclick="applyHospitalFilter()">필터 선택 완료</button>
        </div>
    </div>
    <script>
    function applyHospitalFilter() {
        var regions = [];
        document.querySelectorAll('.h-region-cb:checked').forEach(function(cb) { regions.push(cb.value); });
        var kw = document.getElementById('hospitalKeyword').value.trim();
        var url = new URL(window.location);
        if (regions.length > 0) url.searchParams.set('region', regions.join(','));
        else url.searchParams.delete('region');
        if (kw) url.searchParams.set('q', kw);
        else url.searchParams.delete('q');
        window.location = url;
    }
    function updateSelectedTags() {
        var wrap = document.querySelector('.region-selected-tags');
        if (!wrap) return;
        wrap.innerHTML = '';
        document.querySelectorAll('.h-region-cb:checked').forEach(function(cb) {
            var span = document.createElement('span');
            span.className = 'region-tag';
            span.innerHTML = cb.value + ' <span style="cursor:pointer;" onclick="uncheckedRegion(\''+cb.value+'\')\">&times;</span>';
            wrap.appendChild(span);
        });
    }
    function uncheckedRegion(val) {
        var cb = document.querySelector('.h-region-cb[value="'+val+'"]');
        if (cb) { cb.checked = false; updateSelectedTags(); }
    }
    document.querySelectorAll('.h-region-cb').forEach(function(cb) {
        cb.addEventListener('change', updateSelectedTags);
    });
    updateSelectedTags();
    function removeRegion(val) {
        var url = new URL(window.location);
        var current = (url.searchParams.get('region') || '').split(',').filter(function(r) { return r && r !== val; });
        if (current.length > 0) url.searchParams.set('region', current.join(','));
        else url.searchParams.delete('region');
        window.location = url;
    }
    </script>

    <!-- 정렬 옵션 -->
    <div style="padding: 0 16px; margin-bottom: 8px; display: flex; justify-content: flex-end; align-items: center; gap: 8px;">
        <!-- <label style="font-size:13px; color:var(--bs-secondary-color,#888);">정렬:</label> -->
        <div id="sortChips" style="display:flex;gap:6px;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
            <button onclick="setSort('price')" class="sort-chip<?php echo $sort==='price' ? ' active' : ''; ?>"><i class="bi "></i> 낮은가격</button>
            <button onclick="setSort('rating')" class="sort-chip<?php echo $sort==='rating' ? ' active' : ''; ?>"><i class="bi bi-star"></i> 평점</button>
            <button onclick="setSort('popular')" class="sort-chip<?php echo $sort==='popular' ? ' active' : ''; ?>"><i class="bi bi-heart"></i> 인기</button>
            <button onclick="setSort('review')" class="sort-chip<?php echo $sort==='review' ? ' active' : ''; ?>"><i class="bi bi-chat-dots"></i> <?php echo term('review', '후기'); ?></button>
        </div>
        <style>
        .sort-chip { padding:5px 10px;border:1px solid #AAAAAA;border-radius:5px;background:var(--bs-body-bg, #fff);font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:400;cursor:pointer;color:#AAAAAA;transition:all 0.2s;white-space:nowrap; line-height:1.2; display:inline-flex;align-items:center;gap:7px; }
        .sort-chip i { font-size:calc(25px * var(--fs-scale,1) * var(--fs-page,1));width:25px;height:25px;display:inline-flex;align-items:center;justify-content:center; }
        .sort-chip.active { background:#FFFFFF;color:#000000;border:1px solid #000000;font-weight:600; }
        .sort-chip:hover:not(.active) { border-color:#000000;color:#000000; }
        #sortChips::-webkit-scrollbar { display:none; } /* sortChips 가로스크롤 바 숨김 */
        @media (max-width:480px){
          .sort-chip { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); padding:5px 9px; }
          .sort-chip i { font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));width:18px;height:18px; }
        }
        </style>
        <select id="sortOrder" style="display:none;" onchange="applySort()">
            <option value="price"<?php echo $sort==='price' ? ' selected' : ''; ?>>낮은가격순</option>
            <option value="rating"<?php echo $sort==='rating' ? ' selected' : ''; ?>>평점순</option>
            <option value="popular"<?php echo $sort==='popular' ? ' selected' : ''; ?>>인기순</option>
            <option value="review"<?php echo $sort==='review' ? ' selected' : ''; ?>><?php echo term('review', '후기'); ?>순</option>
        </select>
    </div>

    <div class="results-header" style="padding: 0 16px;">
        <?php if (!empty($category)): ?>
        <span style="display:inline-block;background:#AE1932;color:#fff;font-size:12px;padding:4px 10px;border-radius:12px;"><?php echo htmlspecialchars($category); ?></span>
        <?php endif; ?>
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
    $_iq = mysqli_query($connect, "SELECT * FROM gangnamunni_ads WHERE is_active = 1 AND ad_type = 'infeed' ORDER BY display_order ASC LIMIT 10");
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
        <div class="hospital-card ad-card" data-ad-id="<?php echo $_iad['id']; ?>">
            <div class="card-link" onclick="location.href='<?php echo htmlspecialchars($_iad['link_url'] ?? '#'); ?>'">
                <?php if (!empty($_iad['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($_iad['image_url']); ?>" class="hospital-image" alt="광고">
                <?php else: ?>
                <div class="hospital-image" style="background:#D9D9D9;"></div>
                <?php endif; ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($_iad['title']); ?></div>
                    <div class="hospital-address">
                        <span class="addr-district">강남</span>
                        <span class="addr-sep"> | </span>
                        <span><?php echo htmlspecialchars($_iad['subtitle'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
            <div class="card-cta">
                <a href="<?php echo htmlspecialchars($_iad['link_url'] ?? '#'); ?>" class="btn btn-detail" style="flex:1;">
                    <i class="bi bi-info-circle"></i> 상세보기
                </a>
            </div>
        </div>
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
        <div class="hospital-card" onclick="location.href='detail.php?id=<?php echo $hospital['id']; ?>'" style="cursor:pointer;">
            <div class="card-link">
                <?php
                $h_img = $hospital['main_image_url'] ?? $hospital['profile_image_url'] ?? '';
                $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360'%3E%3Crect fill='%23e9ecef' width='640' height='360'/%3E%3Ctext x='50%25' y='45%25' fill='%23adb5bd' text-anchor='middle' font-size='18' font-family='sans-serif'%3E" . urlencode(htmlspecialchars($hospital['name'])) . "%3C/text%3E%3Ctext x='50%25' y='58%25' fill='%23ced4da' text-anchor='middle' font-size='13' font-family='sans-serif'%3ENo Image%3C/text%3E%3C/svg%3E";
                $img_src = !empty($h_img) ? htmlspecialchars($h_img) : $placeholder;
                ?>
                <img src="<?php echo $img_src; ?>"
                     alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                     class="hospital-image"
                     onerror="this.src='<?php echo $placeholder; ?>'">
                <?php
                $ad_grade = $hospital['ad_grade'] ?? 'none';
                if (in_array($ad_grade, ['S', 'A'])): ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <?php endif; ?>
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
                    <div class="hospital-address">
                        <span class="addr-district">강남</span>
                        <span class="addr-sep"> | </span>
                        <span class="addr-full"><?php echo htmlspecialchars($hospital['address'] ?? ''); ?></span>
                        <span class="addr-short"><?php echo htmlspecialchars(mb_substr($hospital['address'] ?? '', 0, 30)); ?>...</span>
                    </div>
                    <?php
                    $_td = $hospital_treatment_descs[(int)$hospital['id']] ?? null;
                    $_tag_name = $_td['name'] ?? ($tags[0] ?? null);
                    $_price = !empty($_td['price_min']) && (int)$_td['price_min'] > 0 ? (int)$_td['price_min'] : (int)($hospital['min_price'] ?? 0);
                    $_price_max = !empty($_td['price_max']) && (int)$_td['price_max'] > 0 ? (int)$_td['price_max'] : 0;
                    if ($_tag_name || $_price > 0):
                    ?>
                    <div class="card-tag-price">
                        <?php if ($_tag_name): ?>
                        <span class="card-tag-item"><?php echo htmlspecialchars($_tag_name); ?></span>
                        <?php endif; ?>
                        <?php if ($_price > 0): ?>
                        <span class="card-price">
                            <?php if ($_price_max > 0): ?>
                            <span class="card-price-max"><?php echo number_format($_price_max * 10000); ?>원</span>
                            <?php endif; ?>
                            <span class="card-price-num"><?php echo number_format($_price * 10000); ?></span><span class="card-price-unit">원~</span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
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
function setSort(val) {
    document.getElementById('sortOrder').value = val;
    applySort();
}
function applySort() {
    var url = new URL(window.location);
    url.searchParams.set('sort', document.getElementById('sortOrder').value);
    window.location = url;
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
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

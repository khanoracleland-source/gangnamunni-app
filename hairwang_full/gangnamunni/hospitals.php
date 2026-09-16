<?php
require_once __DIR__ . '/includes/init.php';
$hospital_return_url = 'hospitals.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
if (file_exists(__DIR__ . '/includes/design_tokens.php')) { include_once __DIR__ . '/includes/design_tokens.php'; }
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/ad_slot_performance.php';
require_once __DIR__ . '/includes/wishlist_button.php';

loadCategoriesFromDB($connect); 

$hospital_regular_price_column = false;
$_regular_col_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
if ($_regular_col_result && mysqli_num_rows($_regular_col_result) > 0) {
    $hospital_regular_price_column = true;
}
$hospital_representative_price_column = false;
$_representative_col_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'");
if ($_representative_col_result && mysqli_num_rows($_representative_col_result) > 0) {
    $hospital_representative_price_column = true;
}
$hospital_price_option_order = $hospital_representative_price_column
    ? 'p.is_representative DESC, p.price ASC, p.sort_order ASC, p.id ASC'
    : 'p.price ASC, p.sort_order ASC, p.id ASC';

$wishlist_table_exists = false;
$_wishlist_table_result = @mysqli_query(
    $connect,
    "SELECT 1 FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'gangnamunni_wishlist' LIMIT 1"
);
if ($_wishlist_table_result && mysqli_num_rows($_wishlist_table_result) > 0) {
    $wishlist_table_exists = true;
}
$wishlist_count_select = $wishlist_table_exists
    ? "(SELECT COUNT(*) FROM gangnamunni_wishlist gw
         WHERE gw.target_type = 'hospital' AND gw.target_id = gangnamunni_hospitals.id) AS wishlist_count"
    : '0 AS wishlist_count';
$hospital_select = "SELECT gangnamunni_hospitals.*, {$wishlist_count_select} FROM gangnamunni_hospitals";


if (!function_exists('extract_addr_district')) {
    function extract_addr_district($addr) {
        $addr = trim((string)$addr);
        if ($addr === '') return '';
        $parts = preg_split('/\s+/u', $addr);
        $order = [];
        if (isset($parts[1])) $order[] = $parts[1];
        foreach ($parts as $p) $order[] = $p;
        foreach ($order as $p) {
            if ($p !== '' && mb_substr($p, -1, 1, 'UTF-8') === '구') {
                return mb_substr($p, 0, mb_strlen($p, 'UTF-8') - 1, 'UTF-8');
            }
        }
        return '';
    }
}

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$escaped_keyword = mysqli_real_escape_string($connect, $keyword);
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);
$treatment = isset($_GET['treatment']) ? trim($_GET['treatment']) : '';
$escaped_treatment = mysqli_real_escape_string($connect, $treatment);
$resolved_keyword_treatment = null;
if ($treatment === '' && $keyword !== '') {
    $resolved_keyword_treatment = adSlotResolveTreatment($connect, $keyword);
}
$effective_treatment = $treatment !== ''
    ? $treatment
    : ($resolved_keyword_treatment['name'] ?? '');
$effective_treatment_id = $resolved_keyword_treatment
    ? (int) $resolved_keyword_treatment['id']
    : 0;
$escaped_effective_treatment = mysqli_real_escape_string($connect, $effective_treatment);
if ($effective_treatment_id <= 0 && $effective_treatment !== '') {
    $_effective_tid_stmt = mysqli_prepare($connect,
        "SELECT id FROM gangnamunni_treatment_master WHERE name = ? AND is_active = 1 LIMIT 1");
    if ($_effective_tid_stmt) {
        mysqli_stmt_bind_param($_effective_tid_stmt, 's', $effective_treatment);
        mysqli_stmt_execute($_effective_tid_stmt);
        mysqli_stmt_bind_result($_effective_tid_stmt, $effective_treatment_id);
        mysqli_stmt_fetch($_effective_tid_stmt);
        mysqli_stmt_close($_effective_tid_stmt);
        $effective_treatment_id = intval($effective_treatment_id);
    }
}
$region = isset($_GET['region']) ? trim($_GET['region']) : '';
$region_list = array_filter(array_map('trim', explode(',', $region)));

$hospital_display_order_column = false;
$_hospital_order_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospitals LIKE 'display_order'");
if ($_hospital_order_col && mysqli_num_rows($_hospital_order_col) > 0) $hospital_display_order_column = true;

$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'manual';
$allowed_sorts = ['manual', 'price', 'rating', 'popular', 'review'];
if (!in_array($sort, $allowed_sorts, true)) $sort = 'manual';
switch($sort) {
    case 'rating': $order_clause = 'rating DESC, review_count DESC'; break;
    case 'popular': $order_clause = 'wishlist_count DESC, rating DESC, review_count DESC'; break;
    case 'review': $order_clause = 'review_count DESC, rating DESC'; break;
    case 'price':
        if ($effective_treatment_id > 0) {
            $price_sort_value = "COALESCE(
                NULLIF((
                    SELECT tp.price
                    FROM gangnamunni_hospital_treatments sht
                    INNER JOIN gangnamunni_hospital_treatment_prices tp
                        ON tp.hospital_treatment_id = sht.id AND tp.price > 0
                    WHERE sht.hospital_id = gangnamunni_hospitals.id
                      AND sht.treatment_id = {$effective_treatment_id}
                      AND sht.status = 'active' AND sht.is_active = 1
                    ORDER BY " . ($hospital_representative_price_column ? "tp.is_representative DESC, " : "") . "tp.price ASC, tp.sort_order ASC, tp.id ASC
                    LIMIT 1
                ), 0),
                NULLIF((
                    SELECT COALESCE(NULLIF(stm.avg_price_min, 0), NULLIF(stm.avg_price_max, 0))
                    FROM gangnamunni_treatment_master stm
                    WHERE stm.id = {$effective_treatment_id} AND stm.is_active = 1
                    LIMIT 1
                ), 0),
                2147483647
            )";
        } else {
            $price_sort_value = 'COALESCE(NULLIF(min_price, 0), 2147483647)';
        }
        $order_clause = $price_sort_value . ' ASC, rating DESC';
        break;
    default:
        $order_clause = $hospital_display_order_column
            ? 'CASE WHEN display_order IS NULL OR display_order < 1 THEN 1 ELSE 0 END, display_order ASC, id ASC'
            : 'id ASC';
        break;
}

$s = [];
$style_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='hospital_view_style'");
if ($style_q) { while ($sr = mysqli_fetch_assoc($style_q)) $s[$sr['setting_key']] = $sr['setting_value']; }
$sv = function($key, $default) use ($s) { return $s[$key] ?? $default; };

$list_name   = $sv('hv_list_name_size', '15');
$list_tag_s  = $sv('hv_list_tag_size', '11');
$list_tag_c  = $sv('hv_list_tag_color', '#AE1932');

$hospitals = [];
$base_where = "
    WHERE is_active=1
      AND COALESCE(is_demo, 0)=0
      AND (status='approved' OR status IS NULL)";
if (!empty($keyword)) {
    $resolved_treatment_condition = '';
    if ($effective_treatment_id > 0) {
        $resolved_treatment_condition = " OR id IN (
            SELECT ht.hospital_id
            FROM gangnamunni_hospital_treatments ht
            WHERE ht.treatment_id = {$effective_treatment_id} AND ht.status = 'active' AND ht.is_active = 1
        )";
    }
    $base_where .= " AND (
        name LIKE '%{$escaped_keyword}%'
        OR address LIKE '%{$escaped_keyword}%'
        OR treatment_tags LIKE '%{$escaped_keyword}%'
        OR id IN (
            SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
            INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
            WHERE tm.name LIKE '%{$escaped_keyword}%' AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
        )
        {$resolved_treatment_condition}
    )";
}
if (!empty($category)) { $base_where .= " AND treatment_tags LIKE '%{$escaped_category}%'"; }
if (!empty($treatment)) {
    $base_where .= " AND id IN (
        SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        WHERE tm.name = '{$escaped_treatment}' AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
    )";
}

if (!empty($region_list)) {
    $region_conditions = [];
    foreach ($region_list as $r) {
        $r_esc = mysqli_real_escape_string($connect, $r);
        $region_conditions[] = "address LIKE '%{$r_esc}%'";
    }
    $query = $hospital_select . $base_where
        . " AND (" . implode(' OR ', $region_conditions) . ")"
        . " ORDER BY " . $order_clause . " LIMIT 100";
    $result = mysqli_query($connect, $query);
    if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }
} else {
    $region_mix_enabled = false;
    $region_mix_target = trim(getSettingValue($connect, 'region_mix_target_region', ''));
    $region_mix_target_count = max(1, (int) getSettingValue($connect, 'region_mix_target_count', '10'));
    $region_mix_other_count = max(0, (int) getSettingValue($connect, 'region_mix_other_count', '5'));

    if ($region_mix_enabled && $region_mix_target !== '') {
        $target_esc = mysqli_real_escape_string($connect, $region_mix_target);

        $target_query = $hospital_select . $base_where
            . " AND address LIKE '%{$target_esc}%'"
            . " ORDER BY " . $order_clause . " LIMIT 100";
        $target_list = [];
        $tr_result = mysqli_query($connect, $target_query);
        if ($tr_result) { while ($row = mysqli_fetch_assoc($tr_result)) $target_list[] = $row; }

        $other_query = $hospital_select . $base_where
            . " AND address NOT LIKE '%{$target_esc}%'"
            . " ORDER BY " . $order_clause . " LIMIT 100";
        $other_list = [];
        $or_result = mysqli_query($connect, $other_query);
        if ($or_result) { while ($row = mysqli_fetch_assoc($or_result)) $other_list[] = $row; }

        $ti = 0; $oi = 0;
        while (($ti < count($target_list) || $oi < count($other_list)) && count($hospitals) < 100) {
            for ($i = 0; $i < $region_mix_target_count && $ti < count($target_list) && count($hospitals) < 100; $i++) {
                $hospitals[] = $target_list[$ti++];
            }
            for ($i = 0; $i < $region_mix_other_count && $oi < count($other_list) && count($hospitals) < 100; $i++) {
                $hospitals[] = $other_list[$oi++];
            }
            if ($ti >= count($target_list) && $oi >= count($other_list)) break;
        }
    } else {
        $query = $hospital_select . $base_where . " ORDER BY " . $order_clause . " LIMIT 100";
        $result = mysqli_query($connect, $query);
        if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }
    }
}

$slot_hospital_context = [];
if (!empty($effective_treatment)) {
    $treatment_id_for_slot = $effective_treatment_id;
    if ($treatment_id_for_slot <= 0) {
        $_tid_r = mysqli_query($connect, "SELECT id FROM gangnamunni_treatment_master WHERE name = '{$escaped_effective_treatment}' AND is_active = 1 LIMIT 1");
        if ($_tid_r && $_tid_row = mysqli_fetch_assoc($_tid_r)) {
            $treatment_id_for_slot = (int)$_tid_row['id'];
        }
    }

    $slot_hospital_ranks = []; 
    if ($treatment_id_for_slot > 0) {
        $_slot_sql = "
            SELECT b.id AS booking_id, b.hospital_id, p.id AS product_id,
                   p.rank_position, p.billing_model
            FROM gangnamunni_ad_slot_bookings b
            INNER JOIN gangnamunni_ad_slot_products p ON b.product_id = p.id
            WHERE p.keyword_type = 'treatment'
              AND p.treatment_id = {$treatment_id_for_slot}
              AND p.is_active = 1
              AND b.status = 'active'
              AND b.start_date <= CURDATE()
              AND (b.end_date IS NULL OR b.end_date >= CURDATE())
            ORDER BY p.rank_position ASC
        ";
        $_slot_r = mysqli_query($connect, $_slot_sql);
        if ($_slot_r) {
            while ($_slot_row = mysqli_fetch_assoc($_slot_r)) {
                $_slot_hospital_id = (int) $_slot_row['hospital_id'];
                $slot_hospital_ranks[$_slot_hospital_id] = (int) $_slot_row['rank_position'];
                $slot_hospital_context[$_slot_hospital_id] = [
                    'booking_id' => (int) $_slot_row['booking_id'],
                    'product_id' => (int) $_slot_row['product_id'],
                    'rank_position' => (int) $_slot_row['rank_position'],
                    'billing_model' => $_slot_row['billing_model'] ?? 'monthly',
                ];
            }
        }
    }

    if (!empty($slot_hospital_ranks)) {
        $_slot_id_list = implode(',', array_keys($slot_hospital_ranks));
        $_slot_h_r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id IN ({$_slot_id_list}) AND is_active=1 AND COALESCE(is_demo,0)=0 AND (status='approved' OR status IS NULL)");
        $_slot_h_rows = [];
        if ($_slot_h_r) { while ($_slot_h_row = mysqli_fetch_assoc($_slot_h_r)) { $_slot_h_rows[(int)$_slot_h_row['id']] = $_slot_h_row; } }

        $ad_slot_hospitals = [];
        foreach (array_keys($slot_hospital_ranks) as $_hid) {
            if (isset($_slot_h_rows[$_hid])) {
                $ad_slot_hospitals[] = $_slot_h_rows[$_hid];
            }
        }

        if (!empty($ad_slot_hospitals)) {
            $hospitals = array_values(array_filter($hospitals, function($h) use ($slot_hospital_ranks) {
                return !isset($slot_hospital_ranks[(int)$h['id']]);
            }));
            $hospitals = array_merge($ad_slot_hospitals, $hospitals);
        }
    }
}

$hospital_main_images = [];
$hospital_logos = [];       
if (!empty($hospitals)) {
    $thumb_ids = array_column($hospitals, 'id');
    $thumb_ids_str = implode(',', array_map('intval', $thumb_ids));
    if ($thumb_ids_str !== '') {
        $_thumb_sql = "SELECT hospital_id, image_url, image_type
                       FROM hospital_images
                       WHERE hospital_id IN ({$thumb_ids_str})
                         AND is_active = 1 AND image_type IN ('main', 'logo')
                       ORDER BY hospital_id ASC, sort_order ASC, id ASC";
        $_thumb_r = mysqli_query($connect, $_thumb_sql);
        if ($_thumb_r) {
            while ($_thumb_row = mysqli_fetch_assoc($_thumb_r)) {
                $_thid = (int) $_thumb_row['hospital_id'];
                if (empty($_thumb_row['image_url'])) continue;
                if (($_thumb_row['image_type'] ?? '') === 'logo') {
                    if (!isset($hospital_logos[$_thid])) $hospital_logos[$_thid] = $_thumb_row['image_url'];
                } else {
                    if (!isset($hospital_main_images[$_thid])) $hospital_main_images[$_thid] = $_thumb_row['image_url'];
                }
            }
        }
    }
}

$hospital_treatment_descs = [];
if (!empty($hospitals) && !empty($effective_treatment)) {
    $h_ids = array_column($hospitals, 'id');
    $h_ids_str = implode(',', array_map('intval', $h_ids));
    $_td_sql = "
        SELECT ht.hospital_id, tm.name AS treatment_name, tm.description,
               ht.dosage_value, ht.dosage_unit,
               (SELECT p.dosage_value FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0
                  ORDER BY {$hospital_price_option_order} LIMIT 1) AS display_dosage_value,
               (SELECT p.dosage_unit FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0
                  ORDER BY {$hospital_price_option_order} LIMIT 1) AS display_dosage_unit,
               tm.avg_price_min AS default_min, tm.avg_price_max AS default_max,
               (SELECT p.price FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0
                  ORDER BY {$hospital_price_option_order} LIMIT 1) AS display_sale_price,
               (SELECT " . ($hospital_regular_price_column ? "p.regular_price" : "p.price") . " FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0
                  ORDER BY {$hospital_price_option_order} LIMIT 1) AS display_regular_price
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        WHERE ht.hospital_id IN ({$h_ids_str})
          AND ht.status = 'active' AND ht.is_active = 1
          AND tm.is_active = 1
          AND tm.name = '{$escaped_effective_treatment}'
    ";
    $_td_result = mysqli_query($connect, $_td_sql);
    if ($_td_result) {
        while ($_td_row = mysqli_fetch_assoc($_td_result)) {
            $display_sale_price = (int)($_td_row['display_sale_price'] ?? 0);
            $display_regular_price = (int)($_td_row['display_regular_price'] ?? 0);
            $def_min    = (int)($_td_row['default_min'] ?? 0);
            $def_max    = (int)($_td_row['default_max'] ?? 0);

            if ($display_sale_price > 0) {
                $final_min = $display_sale_price;
                $final_max = $display_regular_price > $display_sale_price ? $display_regular_price : 0;
            } else {
                $final_min = $def_min > 0 ? $def_min : $def_max;
                $final_max = ($def_min > 0 && $def_max > $def_min) ? $def_max : 0;
            }

            $hospital_treatment_descs[(int)$_td_row['hospital_id']] = [
                'name' => $_td_row['treatment_name'],
                'desc' => $_td_row['description'],
                'price_min' => $final_min,
                'price_max' => $final_max,
                'dosage_value' => $display_sale_price > 0
                    ? ($_td_row['display_dosage_value'] ?? null) : ($_td_row['dosage_value'] ?? null),
                'dosage_unit' => $display_sale_price > 0
                    ? ($_td_row['display_dosage_unit'] ?? null) : ($_td_row['dosage_unit'] ?? null),
            ];
        }
    }
}

$page_title = term('business');
$show_search = true; 

$page_css = '
    .results-header { margin-bottom: 16px; }
    .results-header h1 { font-size: 20px; margin-bottom: 8px; }
    .search-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #000; font-size: 12px; padding: 4px 10px; border: 1px solid #AE1932 }
    .search-filter-chip .chip-remove { cursor: pointer; font-size: 15px; line-height: 1; font-weight: 700; opacity: 0.9; }
    .search-filter-chip .chip-remove:hover { opacity: 1; }

    .hospital-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; width: 100%; padding-inline: 16px; margin-bottom: 40px; box-sizing: border-box; }
    .hospital-card { background: var(--bs-body-bg, #fff); border-radius: 0px; overflow: hidden; box-shadow: none; position: relative; display: flex; flex-direction: column; }
    .hospital-card:hover { }
    .card-link { cursor: pointer; flex: 1; }
    .hospital-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #D9D9D9; display: block; }
    .hospital-info { padding: 12px 16px 6px; }
    .hospital-name { font-size: 16px; font-weight: 500; margin-bottom: 4px; color: var(--bs-body-color, #000000); }
    .hospital-address { font-size: 14px; font-weight: 400; color: var(--bs-body-color, #000000); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hospital-address * { font-size: 14px; }

    .badge-group { display: flex; gap: 6px; margin-top: 6px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 4px; scrollbar-width: none; }
    .badge-group::-webkit-scrollbar { display: none; }
    .tag-badge { display: inline-block; padding: 3px 10px; border: 1px solid currentColor; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }

    .hospital-grid.list-view { grid-template-columns: 1fr; }
    .hospital-grid.list-view .hospital-card { flex-direction: row; border-top: 1px solid #ddd; }
    .hospital-grid.list-view .hospital-card:last-child { border-bottom: 1px solid #ddd; }
    .card-wishlist { position: absolute; top: 8px; right: 8px; z-index: 3; }
    .hospital-grid.list-view .hospital-image { width: 100px; height: 100px; flex-shrink: 0; }
    .hospital-grid.list-view .card-link { display: flex; flex-direction: row; flex: 1; min-width: 0; }
    .hospital-grid.list-view .hospital-info { flex: 1; padding: 10px 12px; min-width: 0; }
    .hospital-grid.list-view .hospital-name { font-size: ' . $list_name . 'px; }
    .hospital-grid.list-view .tag-badge { font-size: ' . $list_tag_s . 'px; color: ' . $list_tag_c . '; border-color: ' . $list_tag_c . '; }

    .addr-full { display: none; }
    .addr-short { display: inline; }
    .no-result { text-align: center; padding: 80px 20px; color: var(--bs-secondary-color, #999); }
    .ad-card { text-decoration: none !important; }
    .ad-badge { position: absolute; top: 8px; right: 8px; background: #2E75B6; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; z-index: 5; }
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

$page_js = '';

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
        window.location.href = u.pathname + u.search;
    }
    </script>

    <?php if (!empty($hospitals)): ?>
<?php
$_infeed_enabled = getSettingValue($connect, 'ad_show_hospitals_infeed', '1');
$_infeed_interval = (int)getSettingValue($connect, 'ad_infeed_interval', '4');
$_infeed_ads = [];
if ($_infeed_enabled === '1') {
    $_iq = mysqli_query($connect, "SELECT a.*, h.id as hospital_id
        FROM gangnamunni_ads a
        LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
        LEFT JOIN gangnamunni_hospitals h ON h.name = adv.hospital_name
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
    <div class="hospital-grid list-view" id="hospital-grid">
<?php
$__hi = 0;
foreach ($hospitals as $hospital):
    if ($__hi > 0 && $_infeed_enabled === '1' && $_infeed_interval > 0 && $__hi % $_infeed_interval === 0 && isset($_infeed_ads[$_ad_idx])):
        $_iad = $_infeed_ads[$_ad_idx];
?>
        <a class="hospital-card ad-card" href="<?php echo !empty($_iad['hospital_id']) ? 'detail.php?id=' . (int)$_iad['hospital_id'] . '&amp;return_url=' . rawurlencode($hospital_return_url) : htmlspecialchars($_iad['link_url'] ?? '#'); ?>" data-ad-id="<?php echo $_iad['id']; ?>">
                <?php if (!empty($_iad['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($_iad['image_url']); ?>" class="hospital-image" alt="광고">
                <?php else: ?>
                <div class="hospital-image" style="background:#D9D9D9;"></div>
                <?php endif; ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($_iad['title']); ?></div>
                    <?php $_iad_addr = $_iad['subtitle'] ?? ''; $_iad_dist = extract_addr_district($_iad_addr); ?>
                    <div class="hospital-address">
                        <?php if ($_iad_dist !== ''): ?>
                        <span class="addr-district"><?php echo htmlspecialchars($_iad_dist); ?></span>
                        <span class="addr-sep"> | </span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars(mb_substr($_iad_addr, 0, 15, 'UTF-8')); ?></span>
                    </div>
                </div>
        </a>
<?php
        $_ad_idx++;
    endif;
    $__hi++;

    $_detail_url = 'detail.php?id=' . (int)$hospital['id']
        . '&return_url=' . rawurlencode($hospital_return_url);
    if (!empty($effective_treatment) && $effective_treatment_id > 0) {
        $_detail_url .= '&treatment_id=' . $effective_treatment_id . '&treatment=' . urlencode($effective_treatment);
    }
    $_slot_context = $slot_hospital_context[(int)$hospital['id']] ?? null;
?>
        <div class="hospital-card"
             onclick="location.href='<?php echo htmlspecialchars($_detail_url); ?>'"
             style="cursor:pointer;"
             <?php if ($_slot_context): ?>
             data-ad-slot-booking="<?php echo (int) $_slot_context['booking_id']; ?>"
             data-ad-slot-keyword="<?php echo htmlspecialchars($keyword !== '' ? $keyword : $effective_treatment, ENT_QUOTES, 'UTF-8'); ?>"
             data-ad-slot-rank="<?php echo (int) $_slot_context['rank_position']; ?>"
             data-target-url="<?php echo htmlspecialchars($_detail_url, ENT_QUOTES, 'UTF-8'); ?>"
             <?php endif; ?>>
            <div class="card-wishlist">
                <?php renderWishlistButton('hospital', (int)$hospital['id'], 'sm', 'star'); ?>
            </div>
            <div class="card-link">
                <?php
                $h_img = $hospital_main_images[(int)$hospital['id']]
                         ?? $hospital['main_image_url']
                         ?? $hospital_logos[(int)$hospital['id']]
                         ?? $hospital['profile_image_url'] ?? '';
                $placeholder = 'assets/images/seoul_beauty_hospital_placeholder.jpeg';
                $img_src = !empty($h_img) ? htmlspecialchars($h_img) : $placeholder;
                ?>
                <img src="<?php echo $img_src; ?>"
                     alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                     class="hospital-image"
                     onerror="this.onerror=null;this.src='<?php echo $placeholder; ?>'">
                <?php
                $ad_grade = $hospital['ad_grade'] ?? 'none';
                if ($_slot_context || in_array($ad_grade, ['S', 'A'])): ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <?php endif; ?>
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
                    <?php $_a=($hospital['address']??''); $_dist = extract_addr_district($_a); ?>
                    <div class="hospital-address">
                        <?php if ($_dist !== ''): ?>
                        <span class="addr-district"><?php echo htmlspecialchars($_dist); ?></span>
                        <span class="addr-sep"> | </span>
                        <?php endif; ?>
                        <span class="addr-full"><?php echo htmlspecialchars($_a); ?></span>
                        <span class="addr-short"><?php $_a=($hospital['address']??''); echo htmlspecialchars($_a); ?></span>
                    </div>
                    <?php
                    $_td = $hospital_treatment_descs[(int)$hospital['id']] ?? null;
                    $_tag_name = $_td['name'] ?? null;
                    $_price = !empty($_td['price_min']) && (int)$_td['price_min'] > 0 ? (int)$_td['price_min'] : 0;
                    $_price_max = !empty($_td['price_max']) && (int)$_td['price_max'] > 0 ? (int)$_td['price_max'] : 0;
                    $_price_won = $_price;
                    $_price_max_won = $_price_max;
                    $_dosage_label = '';
                    if ($_td && (float)($_td['dosage_value'] ?? 0) > 0 && !empty($_td['dosage_unit'])) {
                        $_dosage_label = ' · ' . rtrim(rtrim(number_format((float)$_td['dosage_value'], 2), '0'), '.') . $_td['dosage_unit'];
                    }
                    if ($_tag_name || $_price > 0):
                    ?>
                    <div class="card-tag-price">
                        <?php if ($_tag_name): ?>
                        <span class="card-tag-item"><?php echo htmlspecialchars($_tag_name . $_dosage_label); ?></span>
                        <?php endif; ?>
                        <?php if ($_price > 0): ?>
                        <span class="card-price">
                            <?php if ($_price_max > 0): ?>
                            <span class="card-price-max"><?php echo number_format($_price_max_won); ?>원</span>
                            <?php endif; ?>
                            <span class="card-price-sale"><span class="card-price-num"><?php echo number_format($_price_won); ?></span><span class="card-price-unit">원</span></span>
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

<?php
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
$hospital_lowest_price_option_order = 'p.price ASC, p.sort_order ASC, p.id ASC';

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
require_once __DIR__ . '/hospital_dosage_filter.php';
$dosage_options = hospital_dosage_options($connect, $effective_treatment_id);
$selected_dosage = hospital_dosage_selected($dosage_options, $_GET['dosage'] ?? '');
$dosage_cond_tp = hospital_dosage_sql_condition($connect, $selected_dosage, 'tp');
$dosage_cond_p = hospital_dosage_sql_condition($connect, $selected_dosage, 'p');

$region = isset($_GET['region']) ? trim($_GET['region']) : '';
$region_list = array_filter(array_map('trim', explode(',', $region)));

$hospital_display_order_column = false;
$_hospital_order_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospitals LIKE 'display_order'");
if ($_hospital_order_col && mysqli_num_rows($_hospital_order_col) > 0) $hospital_display_order_column = true;

$sort = isset($_GET['sort']) ? trim($_GET['sort']) : ($effective_treatment_id > 0 ? 'price' : 'manual');
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
                        ON tp.hospital_treatment_id = sht.id AND tp.price > 0{$dosage_cond_tp}
                    WHERE sht.hospital_id = gangnamunni_hospitals.id
                      AND sht.treatment_id = {$effective_treatment_id}
                      AND sht.status = 'active' AND sht.is_active = 1
                    ORDER BY tp.price ASC, tp.sort_order ASC, tp.id ASC
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
$list_tag_c  = $sv('hv_list_tag_color', '#AC1830');

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
if ($selected_dosage && $effective_treatment_id > 0) {
    $base_where .= " AND id IN (
        SELECT ht.hospital_id
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
        WHERE ht.treatment_id = {$effective_treatment_id} AND ht.status = 'active' AND ht.is_active = 1
    )";
}
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
    if (!$result) throw new RuntimeException('Hospital list query failed');
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
        if (!$result) throw new RuntimeException('Hospital list query failed');
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

    if (!empty($slot_hospital_ranks) && $sort !== 'price') {
        $_slot_id_list = implode(',', array_keys($slot_hospital_ranks));
        $_slot_dosage_where = '';
        if ($selected_dosage) {
            $_slot_dosage_where = " AND id IN (
                SELECT ht.hospital_id FROM gangnamunni_hospital_treatments ht
                INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
                WHERE ht.treatment_id = {$treatment_id_for_slot} AND ht.status = 'active' AND ht.is_active = 1
            )";
        }
        $_slot_h_r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id IN ({$_slot_id_list}) AND is_active=1 AND COALESCE(is_demo,0)=0 AND (status='approved' OR status IS NULL){$_slot_dosage_where}");
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
    $hospital_display_price_option_order = $sort === 'price'
        ? $hospital_lowest_price_option_order
        : $hospital_price_option_order;
    $h_ids = array_column($hospitals, 'id');
    $h_ids_str = implode(',', array_map('intval', $h_ids));
    $_td_sql = "
        SELECT ht.hospital_id, tm.name AS treatment_name, tm.description,
               ht.dosage_value, ht.dosage_unit,
               (SELECT p.dosage_value FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
                  ORDER BY {$hospital_display_price_option_order} LIMIT 1) AS display_dosage_value,
               (SELECT p.dosage_unit FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
                  ORDER BY {$hospital_display_price_option_order} LIMIT 1) AS display_dosage_unit,
               tm.avg_price_min AS default_min, tm.avg_price_max AS default_max,
               (SELECT p.price FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
                  ORDER BY {$hospital_display_price_option_order} LIMIT 1) AS display_sale_price,
               (SELECT " . ($hospital_regular_price_column ? "p.regular_price" : "p.price") . " FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0{$dosage_cond_p}
                  ORDER BY {$hospital_display_price_option_order} LIMIT 1) AS display_regular_price
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

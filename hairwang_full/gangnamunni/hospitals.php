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
require_once __DIR__ . '/includes/ad_slot_performance.php';

loadCategoriesFromDB($connect); // 반환값 미사용 — $category_icons 전역 초기화 목적

/**
 * 주소에서 '구'(district)를 추출한다.
 * 예) "서울특별시 강남구 테헤란로 123" → "강남"
 * 한국 주소는 보통 "시/도 구/군 ..." 순서라 두 번째 토큰을 먼저 확인하고,
 * 두 번째 토큰이 '구'로 끝나지 않으면 나머지 토큰을 검사한다.
 */
if (!function_exists('extract_addr_district')) {
    function extract_addr_district($addr) {
        $addr = trim((string)$addr);
        if ($addr === '') return '';
        $parts = preg_split('/\s+/u', $addr);
        // 두 번째 토큰을 먼저 검사, 그 다음 전체 토큰
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
// 특정 시술(gangnamunni_treatment_master, 103개) 기준 검색 — gangnamunni_hospital_treatments로 실제 배정된 병원만 매칭
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
// index.php 의 ae-wrap 시술 필터로 들어온 경우에도 이름이 아니라 고유 ID를 함께 전달한다.
// 목록의 가격과 상세페이지의 시술 이미지가 반드시 같은 시술을 가리키게 하기 위함이다.
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
// 다중 지역 지원 (콤마 구분)
$region_list = array_filter(array_map('trim', explode(',', $region)));

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

// 스타일 설정
$s = [];
$style_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='hospital_view_style'");
if ($style_q) { while ($sr = mysqli_fetch_assoc($style_q)) $s[$sr['setting_key']] = $sr['setting_value']; }
$sv = function($key, $default) use ($s) { return $s[$key] ?? $default; };

// 스타일 변수
$list_btn    = $sv('hv_list_btn_color', '#AE1932');
$list_btxt   = $sv('hv_list_btn_text', '#ffffff');
$list_name   = $sv('hv_list_name_size', '15');
$list_tag_s  = $sv('hv_list_tag_size', '11');
$list_tag_c  = $sv('hv_list_tag_color', '#AE1932');
$list_cta    = $sv('hv_list_cta_size', '12');

// 병원 검색
$hospitals = [];
$base_where = "
    WHERE is_active=1
      AND COALESCE(is_demo, 0)=0
      AND (status='approved' OR status IS NULL)";
if (!empty($keyword)) {
    // 병원명/주소/태그뿐 아니라, 실제 배정된 시술명(gangnamunni_treatment_master)으로도 검색
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
    // 사용자가 직접 지역을 선택한 경우 — 해당 지역만, 지역 혼합 비율 미적용
    $region_conditions = [];
    foreach ($region_list as $r) {
        $r_esc = mysqli_real_escape_string($connect, $r);
        $region_conditions[] = "address LIKE '%{$r_esc}%'";
    }
    $query = "SELECT * FROM gangnamunni_hospitals" . $base_where
        . " AND (" . implode(' OR ', $region_conditions) . ")"
        . " ORDER BY " . $order_clause . " LIMIT 100";
    $result = mysqli_query($connect, $query);
    if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }
} else {
    // 지역을 직접 선택하지 않은 경우 — 설정에 따라 대상 지역/그 외 지역 비율 혼합
    $region_mix_enabled = getSettingValue($connect, 'region_mix_enabled', '0') === '1';
    $region_mix_target = trim(getSettingValue($connect, 'region_mix_target_region', ''));
    $region_mix_target_count = max(1, (int) getSettingValue($connect, 'region_mix_target_count', '10'));
    $region_mix_other_count = max(0, (int) getSettingValue($connect, 'region_mix_other_count', '5'));

    if ($region_mix_enabled && $region_mix_target !== '') {
        $target_esc = mysqli_real_escape_string($connect, $region_mix_target);

        $target_query = "SELECT * FROM gangnamunni_hospitals" . $base_where
            . " AND address LIKE '%{$target_esc}%'"
            . " ORDER BY " . $order_clause . " LIMIT 100";
        $target_list = [];
        $tr_result = mysqli_query($connect, $target_query);
        if ($tr_result) { while ($row = mysqli_fetch_assoc($tr_result)) $target_list[] = $row; }

        $other_query = "SELECT * FROM gangnamunni_hospitals" . $base_where
            . " AND address NOT LIKE '%{$target_esc}%'"
            . " ORDER BY " . $order_clause . " LIMIT 100";
        $other_list = [];
        $or_result = mysqli_query($connect, $other_query);
        if ($or_result) { while ($row = mysqli_fetch_assoc($or_result)) $other_list[] = $row; }

        // 대상 지역 N개 + 그 외 지역 M개 블록을 반복하며 인터리빙, 한쪽이 소진되면 남은 쪽으로 채움
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
        $query = "SELECT * FROM gangnamunni_hospitals" . $base_where . " ORDER BY " . $order_clause . " LIMIT 100";
        $result = mysqli_query($connect, $query);
        if ($result) { while ($row = mysqli_fetch_assoc($result)) $hospitals[] = $row; }
    }
}

// 시술 키워드 광고 슬롯: 이 시술에 대해 유료로 순위를 산 병원을 리스트 최상단에 고정
$slot_hospital_context = []; // hospital_id => tracking context
if (!empty($effective_treatment)) {
    $treatment_id_for_slot = $effective_treatment_id;
    if ($treatment_id_for_slot <= 0) {
        $_tid_r = mysqli_query($connect, "SELECT id FROM gangnamunni_treatment_master WHERE name = '{$escaped_effective_treatment}' AND is_active = 1 LIMIT 1");
        if ($_tid_r && $_tid_row = mysqli_fetch_assoc($_tid_r)) {
            $treatment_id_for_slot = (int)$_tid_row['id'];
        }
    }

    $slot_hospital_ranks = []; // hospital_id => rank_position
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

        // rank_position 순서(ORDER BY)대로 상단 고정 — 뱃지는 표시하지 않음
        $ad_slot_hospitals = [];
        foreach (array_keys($slot_hospital_ranks) as $_hid) {
            if (isset($_slot_h_rows[$_hid])) {
                $ad_slot_hospitals[] = $_slot_h_rows[$_hid];
            }
        }

        if (!empty($ad_slot_hospitals)) {
            // 일반 목록에서 슬롯에 이미 있는 병원을 제외해 중복 카드 방지
            $hospitals = array_values(array_filter($hospitals, function($h) use ($slot_hospital_ranks) {
                return !isset($slot_hospital_ranks[(int)$h['id']]);
            }));
            $hospitals = array_merge($ad_slot_hospitals, $hospitals);
        }
    }
}

// 병원별 대표 썸네일 조회 (hospital_images 의 '메인' 이미지 우선)
// gangnamunni_hospitals.main_image_url 컬럼은 최초 등록 시점 값에 고정되어 있어
// 관리자가 admin_hospital_images.php 에서 이미지를 바꿔도 반영되지 않는다.
// hospital_images 에 image_type='main' 인 활성 이미지가 있으면 그것을 대표로 사용하고,
// 없을 때만 기존 main_image_url/profile_image_url 로 폴백한다.
$hospital_main_images = []; // hospital_id => image_url
$hospital_logos = [];       // hospital_id => image_url (image_type='logo', 목록 전용)
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
                // 병원당 첫(가장 낮은 sort_order) 이미지만 사용
                if (($_thumb_row['image_type'] ?? '') === 'logo') {
                    if (!isset($hospital_logos[$_thid])) $hospital_logos[$_thid] = $_thumb_row['image_url'];
                } else {
                    if (!isset($hospital_main_images[$_thid])) $hospital_main_images[$_thid] = $_thumb_row['image_url'];
                }
            }
        }
    }
}

// 병원별 대표 시술 설명 조회
// [QA #2] ?treatment= 로 특정 시술 검색 중일 때만 그 시술의 가격을 보여준다.
// 지역 검색이나 필터 없는 단순 목록에서는 어떤 시술을 대표로 보여줘야 할지 알 수 없으므로 표시하지 않는다.
$hospital_treatment_descs = [];
if (!empty($hospitals) && !empty($effective_treatment)) {
    $h_ids = array_column($hospitals, 'id');
    $h_ids_str = implode(',', array_map('intval', $h_ids));
    // 병원이 admin_hospital_treatments 에서 설정한 가격(옵션)을 우선 사용한다.
    //  - 옵션에 가격(>0)이 있으면: 최저~최고 (옵션이 1개이거나 최저=최고면 최고 숨김 → 단일가)
    //  - 설정된 가격이 없으면: 기본 시술(마스터) 가격(avg_price_min~avg_price_max) 로 폴백
    $_td_sql = "
        SELECT ht.hospital_id, tm.name AS treatment_name, tm.description,
               ht.dosage_value, ht.dosage_unit,
               tm.avg_price_min AS default_min, tm.avg_price_max AS default_max,
               (SELECT COUNT(*) FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_priced_count,
               (SELECT MIN(p.price) FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_min,
               (SELECT MAX(p.price) FROM gangnamunni_hospital_treatment_prices p
                  WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_max
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
            $opt_priced = (int)($_td_row['opt_priced_count'] ?? 0);
            $opt_min    = (int)($_td_row['opt_min'] ?? 0);
            $opt_max    = (int)($_td_row['opt_max'] ?? 0);
            $def_min    = (int)($_td_row['default_min'] ?? 0);
            $def_max    = (int)($_td_row['default_max'] ?? 0);

            if ($opt_priced >= 1 && $opt_max > 0) {
                // 병원이 직접 설정한 가격
                $final_min = $opt_min;
                $final_max = ($opt_priced <= 1 || $opt_min >= $opt_max) ? 0 : $opt_max; // 단일이면 최고가 숨김
            } else {
                // 병원 미설정 → 기본 시술(마스터) 가격
                $final_min = $def_min > 0 ? $def_min : $def_max;
                $final_max = ($def_min > 0 && $def_max > $def_min) ? $def_max : 0;
            }

            $hospital_treatment_descs[(int)$_td_row['hospital_id']] = [
                'name' => $_td_row['treatment_name'],
                'desc' => $_td_row['description'],
                'price_min' => $final_min,
                'price_max' => $final_max,
                'dosage_value' => $_td_row['dosage_value'] ?? null,
                'dosage_unit' => $_td_row['dosage_unit'] ?? null,
            ];
        }
    }
}

$page_title = term('business');
$show_search = true; // 메인과 동일한 공용 헤더 검색바(includes/header.php) 사용

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
    .search-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #000; font-size: 12px; padding: 4px 10px; border: 1px solid #AE1932 }
    .search-filter-chip .chip-remove { cursor: pointer; font-size: 15px; line-height: 1; font-weight: 700; opacity: 0.9; }
    .search-filter-chip .chip-remove:hover { opacity: 1; }

    /* 그리드 */
    .hospital-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 40px; }
    .hospital-card { background: var(--bs-body-bg, #fff); border-radius: 0px; overflow: hidden; box-shadow: none; position: relative; display: flex; flex-direction: column; }
    .hospital-card:hover { }
    .card-link { cursor: pointer; flex: 1; }
    .hospital-image { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #D9D9D9; display: block; }
    .hospital-info { padding: 12px 16px 6px; }
    .hospital-name { font-size: 16px; font-weight: 500; margin-bottom: 4px; color: var(--bs-body-color, #000000); }
    .hospital-address { font-size: 14px; font-weight: 400; color: var(--bs-body-color, #000000); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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

    /* === 리스트형 (고정) === */
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
    }

.card-tag-price { display: flex; flex-direction: column; gap: 0; margin-top: 6px; }
.card-tag-item { font-size: 18px; font-weight: 700; color: #000; padding: 2px 0; text-align: left; }
.card-price { width: 100%; text-align: left; font-size: 22px; line-height: 1; }
.card-price-num { font-size: 22px; font-weight: 700; color: #AE1932; line-height: 1; }
.card-price-unit { font-size: 22px; font-weight: 400; color: #000000; }
.card-price-max { font-size: 22px; color: #858484; text-decoration: line-through; margin-right: 2px; }
';

$page_js = '';

// 메인과 동일한 헤더 검색바 스타일 적용 (지역 선택 버튼 pill + placeholder 정렬)
$_fs_css_file = __DIR__ . '/assets/css/figma_sync_index.css';
if (file_exists($_fs_css_file)) { $page_css .= "\n" . file_get_contents($_fs_css_file); }

include __DIR__ . '/includes/header.php';
?>
    <script>
    // localStorage 동기화: URL ↔ storage (지역, 정렬 — 뒤로가기로 돌아왔을 때 이전 선택 복원)
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var url = new URL(window.location.href);
        var needsRedirect = false;

        var regionParam = urlParams.get('region');
        if (regionParam) {
            // URL에 region 있으면 localStorage에 저장
            localStorage.setItem('sb_selected_regions', JSON.stringify(regionParam.split(',').filter(Boolean)));
        } else {
            // URL에 region 없으면 localStorage에서 복원
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
        } else {
            var savedSort = localStorage.getItem('sb_selected_sort');
            if (savedSort) {
                url.searchParams.set('sort', savedSort);
                needsRedirect = true;
            }
        }

        if (needsRedirect) {
            window.location.replace(url.toString());
        }
    })();
    // 검색바/지역 선택 UI + 로직은 includes/header.php 공용 컴포넌트가 담당
    </script>

    <!-- 정렬 옵션 -->
    <div style="padding: 0 16px; margin-bottom: 8px; display: flex; justify-content: flex-start; align-items: center; gap: 8px;">
        <!-- <label style="font-size:13px; color:var(--bs-secondary-color,#888);">정렬:</label> -->
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
// 리스트형 고정 — 뷰 전환 토글 제거 (카드형/리스트형 버튼 삭제)
require_once __DIR__ . '/includes/settings_helper.php';
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
    <div class="hospital-grid list-view" id="hospital-grid" style="padding: 0 16px;">
<?php
$__hi = 0;
foreach ($hospitals as $hospital):
    if ($__hi > 0 && $_infeed_enabled === '1' && $_infeed_interval > 0 && $__hi % $_infeed_interval === 0 && isset($_infeed_ads[$_ad_idx])):
        $_iad = $_infeed_ads[$_ad_idx];
?>
        <a class="hospital-card ad-card" href="<?php echo !empty($_iad['hospital_id']) ? 'detail.php?id=' . (int)$_iad['hospital_id'] : htmlspecialchars($_iad['link_url'] ?? '#'); ?>" data-ad-id="<?php echo $_iad['id']; ?>">
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

    $has_rating = (int)($hospital['review_count'] ?? 0) > 0;
    $rating_val = number_format((float)($hospital['rating'] ?? 0), 1);
    $review_cnt = number_format((int)($hospital['review_count'] ?? 0));
    // 특정 시술로 필터링해서 들어온 경우, 상세 페이지에서도 그 시술 정보로 바로 이동
    $_detail_url = 'detail.php?id=' . (int)$hospital['id'];
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
            <div class="card-link">
                <?php
                $h_img = $hospital_main_images[(int)$hospital['id']]
                         ?? $hospital['main_image_url']
                         ?? $hospital_logos[(int)$hospital['id']]
                         ?? $hospital['profile_image_url'] ?? '';
                $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360'%3E%3Crect fill='%23e9ecef' width='640' height='360'/%3E%3Ctext x='50%25' y='45%25' fill='%23adb5bd' text-anchor='middle' font-size='18' font-family='sans-serif'%3E" . urlencode(htmlspecialchars($hospital['name'])) . "%3C/text%3E%3Ctext x='50%25' y='58%25' fill='%23ced4da' text-anchor='middle' font-size='13' font-family='sans-serif'%3ENo Image%3C/text%3E%3C/svg%3E";
                $img_src = !empty($h_img) ? htmlspecialchars($h_img) : $placeholder;
                ?>
                <img src="<?php echo $img_src; ?>"
                     alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                     class="hospital-image"
                     onerror="this.src='<?php echo $placeholder; ?>'">
                <?php
                // 광고 슬롯(시술/지역 검색 상단 고정) 병원도 일반 광고 등급 뱃지만 표시 — 'AD N위' 노출 안 함
                $ad_grade = $hospital['ad_grade'] ?? 'none';
                if (in_array($ad_grade, ['S', 'A'])): ?>
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
                        <span class="addr-full"><?php echo htmlspecialchars(mb_substr($_a,0,20,'UTF-8')).(mb_strlen($_a,'UTF-8')>20?'...':''); ?></span>
                        <span class="addr-short"><?php $_a=($hospital['address']??''); echo htmlspecialchars(mb_substr($_a,0,20,'UTF-8')).(mb_strlen($_a,'UTF-8')>20?'...':''); ?></span>
                    </div>
                    <?php
                    $_td = $hospital_treatment_descs[(int)$hospital['id']] ?? null;
                    $_tag_name = $_td['name'] ?? null;
                    $_price = !empty($_td['price_min']) && (int)$_td['price_min'] > 0 ? (int)$_td['price_min'] : 0;
                    $_price_max = !empty($_td['price_max']) && (int)$_td['price_max'] > 0 ? (int)$_td['price_max'] : 0;
                    $_price_won = $_price;
                    $_price_max_won = $_price_max;
                    $_dosage_label = '';
                    if ($_td && $_td['dosage_value'] !== null && $_td['dosage_unit']) {
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
                            <span class="card-price-num"><?php echo number_format($_price_won); ?></span><span class="card-price-unit">원~</span>
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
    document.getElementById('sortOrder').value = val;
    applySort();
}
function applySort() {
    var url = new URL(window.location);
    url.searchParams.set('sort', document.getElementById('sortOrder').value);
    window.location = url;
}
document.addEventListener("DOMContentLoaded", function() {
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

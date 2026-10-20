<?php
/**
 * 바비톡 스타일 병원 상세 페이지
 * 모든 데이터는 DB에서 동적으로 로드
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/page_cache.php';
gangnamunni_page_cache(900, is_gangnamunni_logged_in() ? 'private' : 'public');
// 앱 모드 감지 (?app=1 파라미터)
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/wishlist_button.php';
require_once __DIR__ . '/includes/functions.php';

// 시술 부위는 기존 괄호를 제거한 뒤 "[부위1, 부위2]" 형식으로 표시한다.
function format_treatment_body_parts($body_part) {
    $raw_parts = preg_split('/\s*\|\|\s*/', trim((string)$body_part));
    $parts = [];

    foreach ($raw_parts as $raw_part) {
        $part = trim($raw_part);
        // 이전에 저장된 (), []가 중첩되어 있어도 한 번에 정리한다.
        while (preg_match('/^[\(\[]\s*(.*?)\s*[\)\]]$/u', $part, $matches)) {
            $part = trim($matches[1]);
        }
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return empty($parts) ? '' : '[' . implode(', ', $parts) . ']';
}

if (empty($_SESSION['consultation_csrf_token'])) {
    $_SESSION['consultation_csrf_token'] = bin2hex(random_bytes(32));
}
$consultation_csrf_token = $_SESSION['consultation_csrf_token'];

$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$detail_from_wishlist = isset($_GET['from']) && $_GET['from'] === 'my_wishlist';
require_once __DIR__ . '/includes/hospital_return_url.php';
$detail_return_url = hospital_return_url($_GET['return_url'] ?? '');
$detail_source_qs = $detail_from_wishlist ? '&from=my_wishlist' : '';
if (!$detail_from_wishlist && $detail_return_url !== '') {
    $detail_source_qs .= '&return_url=' . rawurlencode($detail_return_url);
}

// 시술/가격 데이터 로드
$treatments = [];
if (!empty($hospital_id)) {
    $stmt = $connect->prepare("SELECT * FROM gangnamunni_treatments WHERE hospital_id = ? AND is_active = 1 ORDER BY category, sort_order, name");
    if ($stmt) {
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $result_treat = $stmt->get_result();
        while ($row = $result_treat->fetch_assoc()) {
            $treatments[] = $row;
        }
        $stmt->close();
    }
}

// hospitals.php에서 특정 시술(gangnamunni_treatment_master) 검색 후 넘어온 경우
// 해당 병원의 그 시술 정보를 정확히 찾아 홈 탭 상단에 강조 표시
$matched_treatment = null;
$matched_treatment_options = [];
$matched_treatment_images = []; // 이 병원 · 이 시술 전용 상세 이미지 (#시술이미지)
$matched_treatment_query = isset($_GET['treatment']) ? trim($_GET['treatment']) : '';
$matched_treatment_id = isset($_GET['treatment_id']) ? intval($_GET['treatment_id']) : 0;
// 목록의 시술 필터 컨텍스트가 있을 때만 해당 시술 설명/이미지를 노출한다.
// ID를 우선 사용해 목록에 표시된 가격과 상세 이미지가 정확히 같은 시술에 연결되게 한다.
if (!empty($hospital_id) && ($matched_treatment_id > 0 || $matched_treatment_query !== '')) {
    $mt_stmt = mysqli_prepare($connect, "
        SELECT ht.id AS hospital_treatment_id, tm.id AS treatment_id, tm.name, tm.description
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        WHERE ht.hospital_id = ?
          AND ht.status = 'active' AND ht.is_active = 1
          AND tm.is_active = 1
          AND ((? > 0 AND tm.id = ?) OR (? = 0 AND tm.name = ?))
        LIMIT 1
    ");
    if ($mt_stmt) {
        mysqli_stmt_bind_param($mt_stmt, 'iiiis',
            $hospital_id, $matched_treatment_id, $matched_treatment_id,
            $matched_treatment_id, $matched_treatment_query);
        mysqli_stmt_execute($mt_stmt);
        $mt_result = mysqli_stmt_get_result($mt_stmt);
        $matched_treatment = mysqli_fetch_assoc($mt_result) ?: null;
        mysqli_stmt_close($mt_stmt);
    }
    // 선택한 시술의 가격 옵션을 모두 노출한다. 옵션명 컬럼이 없는 기존 DB도 호환한다.
    if ($matched_treatment) {
        $mt_option_name_column = false;
        $mt_option_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
        if ($mt_option_column_result && mysqli_num_rows($mt_option_column_result) > 0) {
            $mt_option_name_column = true;
        }
        $mt_body_part_column = false;
        $mt_body_part_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'body_part'");
        if ($mt_body_part_column_result && mysqli_num_rows($mt_body_part_column_result) > 0) {
            $mt_body_part_column = true;
        }
        $mt_representative_column = false;
        $mt_representative_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'");
        if ($mt_representative_column_result && mysqli_num_rows($mt_representative_column_result) > 0) {
            $mt_representative_column = true;
        }
        $mt_regular_price_column = false;
        $mt_regular_price_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
        if ($mt_regular_price_column_result && mysqli_num_rows($mt_regular_price_column_result) > 0) {
            $mt_regular_price_column = true;
        }
        $mt_option_name_select = $mt_option_name_column ? 'p.option_name' : "'' AS option_name";
        $mt_body_part_select = $mt_body_part_column ? 'p.body_part' : "'' AS body_part";
        $mt_regular_price_select = $mt_regular_price_column ? 'p.regular_price' : 'p.price AS regular_price';
        $mt_options_stmt = mysqli_prepare($connect, "
            SELECT p.id AS option_id, {$mt_option_name_select}, {$mt_body_part_select}, p.dosage_value, p.dosage_unit,
                   {$mt_regular_price_select}, p.price
            FROM gangnamunni_hospital_treatment_prices p
            WHERE p.hospital_treatment_id = ? AND p.price > 0
            ORDER BY " . ($mt_representative_column ? "p.is_representative DESC, " : "") . "p.price ASC, p.sort_order ASC, p.id ASC
        ");
        if ($mt_options_stmt) {
            mysqli_stmt_bind_param($mt_options_stmt, 'i', $matched_treatment['hospital_treatment_id']);
            mysqli_stmt_execute($mt_options_stmt);
            $mt_options_result = mysqli_stmt_get_result($mt_options_stmt);
            while ($mt_option = mysqli_fetch_assoc($mt_options_result)) {
                $dosage_value = (float)($mt_option['dosage_value'] ?? 0);
                $dosage_text = $dosage_value > 0
                    ? rtrim(rtrim(number_format($dosage_value, 2, '.', ''), '0'), '.') . trim((string)($mt_option['dosage_unit'] ?? ''))
                    : '';
                $option_name = trim((string)($mt_option['option_name'] ?? ''));
                $body_part = format_treatment_body_parts($mt_option['body_part'] ?? '');
                $display_name_parts = array_values(array_filter([$matched_treatment['name'], $option_name, $body_part, $dosage_text], static function ($part) {
                    return $part !== '';
                }));
                $mt_option['display_name'] = implode(' · ', $display_name_parts);
                if ($mt_option['display_name'] === '') {
                    $mt_option['display_name'] = $matched_treatment['name'];
                }
                $regular_price = (int)($mt_option['regular_price'] ?? 0);
                $sale_price = (int)($mt_option['price'] ?? 0);
                $mt_option['discount_rate'] = ($regular_price > $sale_price && $sale_price > 0)
                    ? (int)round((($regular_price - $sale_price) / $regular_price) * 100)
                    : 0;
                $matched_treatment_options[] = $mt_option;
            }
            mysqli_stmt_close($mt_options_stmt);
        }
    }
    // 병원이 이 시술에 대해 올려둔 상세페이지 이미지 (없으면 빈 배열 → 기존 안내 문구 유지)
    if ($matched_treatment) {
        require_once __DIR__ . '/includes/hospital_treatment_images.php';
        $matched_treatment_images = hti_images_for($connect, $hospital_id, $matched_treatment['treatment_id']);
    }
}


if ($hospital_id <= 0) {
    header('Location: index.php');
    exit;
}

// 병원 정보 조회
$query = "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die(term("business") . " 정보를 찾을 수 없습니다.");
}

$hospital = mysqli_fetch_assoc($result);

$image_url_keys = function ($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return [];
    }

    $path = parse_url($url, PHP_URL_PATH);
    $keys = [$url, ltrim($url, '/')];
    if ($path) {
        $keys[] = $path;
        $keys[] = ltrim($path, '/');
        $keys[] = basename($path);
    } else {
        $keys[] = basename($url);
    }

    return array_values(array_unique(array_filter($keys)));
};

$hospital_logo_keys = [];
$add_logo_url = function ($url) use (&$hospital_logo_keys, $image_url_keys) {
    $url = trim((string) $url);
    if ($url === '') {
        return;
    }

    foreach ($image_url_keys($url) as $key) {
        $hospital_logo_keys[$key] = true;
    }
};

// 이 프로젝트에서는 profile_image_url 이 병원 로고 저장값으로 쓰인 데이터가 있어 상세 갤러리에서 제외한다.
$add_logo_url($hospital['profile_image_url'] ?? '');
$logo_result = mysqli_query($connect, "SELECT image_url FROM hospital_images WHERE hospital_id = {$hospital_id} AND is_active = 1 AND image_type = 'logo'");
while ($logo_row = mysqli_fetch_assoc($logo_result)) {
    $add_logo_url($logo_row['image_url'] ?? '');
}

$is_logo_image_url = function ($url) use ($hospital_logo_keys, $image_url_keys) {
    foreach ($image_url_keys($url) as $key) {
        if (isset($hospital_logo_keys[$key])) {
            return true;
        }
    }

    return false;
};

// 병원 이미지 갤러리 조회
// 상세 갤러리에는 내부 사진(image_type='interior' 등)만 노출한다.
// - 로고(image_type='logo' / profile_image_url)는 제외
// - 대표 이미지(image_type='main' / main_image_url)도 제외 — 상세에서는 노출하지 않는다.
$images_query = "SELECT * FROM hospital_images WHERE hospital_id = {$hospital_id} AND is_active = 1 AND image_type <> 'logo' AND image_type <> 'main' ORDER BY sort_order ASC, id ASC";
$images_result = mysqli_query($connect, $images_query);
$hospital_images = [];
while ($img_row = mysqli_fetch_assoc($images_result)) {
    // 로고 URL이거나 대표 이미지(main_image_url)와 같은 URL이면 제외
    if ($is_logo_image_url($img_row['image_url'] ?? '')) {
        continue;
    }
    if (!empty($hospital['main_image_url']) && ($img_row['image_url'] ?? '') === $hospital['main_image_url']) {
        continue;
    }
    $hospital_images[] = $img_row;
}

// 병원 특징 조회
$features_query = "SELECT * FROM hospital_features WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY sort_order ASC";
$features_result = mysqli_query($connect, $features_query);
$hospital_features = [];
while ($feature = mysqli_fetch_assoc($features_result)) {
    $hospital_features[] = $feature;
}

// JSON 데이터 파싱
// categories는 저장 경로에 따라 JSON 배열 또는 콤마구분 문자열로 저장될 수 있음 (admin_hospitals.php와 동일한 방어 로직)
$categories = json_decode($hospital['categories'] ?? '[]', true);
if (!is_array($categories)) {
    $categories = array_filter(array_map('trim', explode(',', $hospital['categories'] ?? '')));
}

// gangnamunni_treatment_categories(19개 대분류) 이름 목록 — hospital-specialty 각 단계 매칭에 재사용
$tc_names = [];
$tc_r = mysqli_query($connect, "SELECT name FROM gangnamunni_treatment_categories WHERE is_active = 1");
if ($tc_r) {
    while ($tc_row = mysqli_fetch_assoc($tc_r)) {
        $tc_names[] = $tc_row['name'];
    }
}

// $categories 값을 treatment_categories와 매칭 — 정확히 일치하는 이름만 사용
$matched_treatment_categories = !empty($categories) ? array_values(array_intersect($categories, $tc_names)) : [];

$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true) ?: [];
$business_hours_raw = json_decode($hospital['business_hours'] ?? '{}', true) ?: [];

// 기본 영업시간 설정
$default_hours = [
    '월' => '10:00~19:00',
    '화' => '10:00~19:00',
    '수' => '10:00~19:00',
    '목' => '10:00~19:00',
    '금' => '10:00~20:00',
    '토' => '10:00~18:00',
    '일' => '진료시간 문의 필요',
    'lunch_start' => '13:00',
    'lunch_end' => '14:00'
];

// officeHours 크롤링 포맷 → 한국어 키 변환
$business_hours = [];
if (!empty($business_hours_raw['officeHours']) && is_array($business_hours_raw['officeHours'])) {
    $eng_to_kr = ['MON'=>'월','TUE'=>'화','WED'=>'수','THU'=>'목','FRI'=>'금','SAT'=>'토','SUN'=>'일'];
    foreach ($eng_to_kr as $eng => $kr) {
        if (isset($business_hours_raw['officeHours'][$eng])) {
            // 하이픈 구분자(10:00-19:00) → 물결표(10:00~19:00) 통일
            $business_hours[$kr] = str_replace('-', '~', $business_hours_raw['officeHours'][$eng]);
        } else {
            $business_hours[$kr] = '휴무';
        }
    }
    // officeHoursComment에서 점심시간 파싱
    $comment = $business_hours_raw['officeHoursComment'] ?? '';
    if (!empty($comment)) {
        $business_hours['영업시간_비고'] = $comment;
        // 점심시간 패턴 추출: "13:00~14:00", "1시-2시", "PM 1:00 ~ 2:00" 등
        if (preg_match('/점심[^0-9]*(\d{1,2})[시:](\d{0,2})\s*[~\-–]\s*(\d{1,2})[시:](\d{0,2})/', $comment, $m)) {
            $lh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $lm = !empty($m[2]) ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : '00';
            $eh = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            $em = !empty($m[4]) ? str_pad($m[4], 2, '0', STR_PAD_LEFT) : '00';
            $business_hours['lunch_start'] = "{$lh}:{$lm}";
            $business_hours['lunch_end'] = "{$eh}:{$em}";
        } elseif (preg_match('/(\d{1,2}:\d{2})\s*[~\-–]\s*(\d{1,2}:\d{2})\s*점심/', $comment, $m)) {
            $business_hours['lunch_start'] = $m[1];
            $business_hours['lunch_end'] = $m[2];
        } elseif (preg_match('/PM\s*(\d{1,2}):(\d{2})\s*[~\-–]\s*(\d{1,2}):(\d{2})/', $comment, $m)) {
            $lh = intval($m[1]) + 12;
            $eh = intval($m[3]) + 12;
            $business_hours['lunch_start'] = "{$lh}:{$m[2]}";
            $business_hours['lunch_end'] = "{$eh}:{$m[4]}";
        }
        // "점심시간 없" 또는 "점심시간X" 패턴 감지 시 점심시간 미표시
        if (preg_match('/점심[시간\s]*없|점심[시간\s]*[Xx×]|점심[시간\s]*따로\s*없/', $comment)) {
            unset($business_hours['lunch_start'], $business_hours['lunch_end']);
            $business_hours['no_lunch_break'] = true;
        }
    }
} elseif (!empty($business_hours_raw)) {
    // 이미 한국어 키 형태인 경우 그대로 사용
    $business_hours = $business_hours_raw;
}

// 영업시간이 비어있으면 기본값 사용
if (empty($business_hours) || !is_array($business_hours)) {
    $business_hours = $default_hours;
}

// 관리자 JSON의 점심 표현을 표준 키로 정규화한다 (기존 '점심시간' 키도 지원).
if (!empty($business_hours['no_lunch_break'])) {
    unset($business_hours['점심'], $business_hours['점심시간'], $business_hours['lunch_start'], $business_hours['lunch_end']);
} else {
    $normalized_lunch = trim((string)($business_hours['점심'] ?? $business_hours['점심시간'] ?? ''));
    if (preg_match('/^(\d{1,2}:\d{2})\s*[~\-]\s*(\d{1,2}:\d{2})$/u', $normalized_lunch, $normalized_lunch_match)) {
        $business_hours['lunch_start'] = $normalized_lunch_match[1];
        $business_hours['lunch_end'] = $normalized_lunch_match[2];
    }
}

// 오늘 영업시간 표시 문자열 생성
$today = date('N'); // 1(월) ~ 7(일)
$days = ['월', '화', '수', '목', '금', '토', '일'];
$today_name = $days[$today - 1];
$business_hours_text = ($business_hours[$today_name] ?? $default_hours[$today_name]);

// 현재 탭
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
// 의료진/시술목록/후기 탭: 병원 정보를 영업시간까지만 노출 (주소/지도/특징 숨김)
$minimal_info_tab = in_array($current_tab, ['doctors', 'events', 'reviews'], true);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 20;

// 시술목록: 병원에 활성 배정된 시술과 실제 판매가를 노출한다.
$procedure_count = 0;
$procedure_data = [];
if ($current_tab === 'events') { // 기존 URL 호환성을 위해 tab=events를 유지
    $offset_val = ($page - 1) * $items_per_page;
    $procedure_option_name_column = false;
    $procedure_option_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
    if ($procedure_option_column_result && mysqli_num_rows($procedure_option_column_result) > 0) {
        $procedure_option_name_column = true;
    }
    $procedure_body_part_column = false;
    $procedure_body_part_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'body_part'");
    if ($procedure_body_part_column_result && mysqli_num_rows($procedure_body_part_column_result) > 0) {
        $procedure_body_part_column = true;
    }
    $procedure_option_name_select = $procedure_option_name_column ? 'p.option_name' : "'' AS option_name";
    $procedure_body_part_select = $procedure_body_part_column ? 'p.body_part' : "'' AS body_part";
    $procedure_regular_price_column = false;
    $procedure_regular_price_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
    if ($procedure_regular_price_column_result && mysqli_num_rows($procedure_regular_price_column_result) > 0) {
        $procedure_regular_price_column = true;
    }
    $procedure_regular_price_select = $procedure_regular_price_column ? 'p.regular_price' : 'p.price AS regular_price';

    $pc_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS cnt
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
        INNER JOIN gangnamunni_hospital_treatment_prices p
          ON p.hospital_treatment_id = ht.id AND p.price > 0
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1");
    if ($pc_stmt) {
        mysqli_stmt_bind_param($pc_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($pc_stmt);
        $procedure_count = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($pc_stmt))['cnt'] ?? 0);
        mysqli_stmt_close($pc_stmt);
    }

    $procedure_sql = "SELECT ht.id AS hospital_treatment_id, tm.id AS treatment_id,
            tm.name, tm.description,
            p.id AS option_id, {$procedure_option_name_select}, {$procedure_body_part_select},
            p.dosage_value AS option_dosage_value, p.dosage_unit AS option_dosage_unit,
            {$procedure_regular_price_select}, p.price AS option_price
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
        INNER JOIN gangnamunni_hospital_treatment_prices p
          ON p.hospital_treatment_id = ht.id AND p.price > 0
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
        ORDER BY ht.is_promoted DESC, tm.name ASC, p.sort_order ASC, p.id ASC
        LIMIT ?, ?";
    $procedure_stmt = mysqli_prepare($connect, $procedure_sql);
    if ($procedure_stmt) {
        mysqli_stmt_bind_param($procedure_stmt, 'iii', $hospital_id, $offset_val, $items_per_page);
        mysqli_stmt_execute($procedure_stmt);
        $procedure_result = mysqli_stmt_get_result($procedure_stmt);
        while ($row = mysqli_fetch_assoc($procedure_result)) {
            $option_name = trim((string)($row['option_name'] ?? ''));
            $dosage_value = (float)($row['option_dosage_value'] ?? 0);
            $dosage_text = $dosage_value > 0
                ? rtrim(rtrim(number_format($dosage_value, 2, '.', ''), '0'), '.') . trim((string)($row['option_dosage_unit'] ?? ''))
                : '';
            $body_part = format_treatment_body_parts($row['body_part'] ?? '');
            $display_name_parts = array_values(array_filter([$row['name'], $option_name, $body_part, $dosage_text], static function ($part) {
                return $part !== '';
            }));
            $row['display_name'] = implode(' · ', $display_name_parts);
            if ($row['display_name'] === '') $row['display_name'] = $row['name'];
            $row['display_price'] = (int)$row['option_price'];
            $row['display_regular_price'] = (int)($row['regular_price'] ?? 0);
            $row['discount_rate'] = ($row['display_regular_price'] > $row['display_price'] && $row['display_price'] > 0)
                ? (int)round((($row['display_regular_price'] - $row['display_price']) / $row['display_regular_price']) * 100)
                : 0;
            $procedure_data[] = $row;
        }
        mysqli_stmt_close($procedure_stmt);
    }
}

// 의사 카운트 및 데이터 (gangnamunni_doctors 우선, 없으면 crawled_doctors 폴백)
$doctors_count = 0;
$doctors_data = [];
$doctors_source = 'gangnamunni';

$doc_cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active'");
if ($doc_cnt_stmt) {
    mysqli_stmt_bind_param($doc_cnt_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($doc_cnt_stmt);
    $doctors_count = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_cnt_stmt))['cnt'] ?? 0;
    mysqli_stmt_close($doc_cnt_stmt);
}
if ($doctors_count == 0) {
    $doctors_source = 'crawled';
    $doc_cnt_stmt2 = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM crawled_doctors WHERE hospital_id = ?");
    if ($doc_cnt_stmt2) {
        mysqli_stmt_bind_param($doc_cnt_stmt2, 'i', $hospital_id);
        mysqli_stmt_execute($doc_cnt_stmt2);
        $doctors_count = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_cnt_stmt2))['cnt'] ?? 0;
        mysqli_stmt_close($doc_cnt_stmt2);
    }
}

if ($current_tab === 'doctors' || $current_tab === 'home') {
    if ($doctors_source === 'gangnamunni') {
        $display_order_exists = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_doctors LIKE 'display_order'");
        $doctor_order_sql = ($display_order_exists && mysqli_num_rows($display_order_exists) > 0)
            ? "CASE WHEN display_order IS NULL THEN 1 ELSE 0 END ASC, display_order ASC, name COLLATE utf8mb4_unicode_ci ASC, id ASC"
            : "name COLLATE utf8mb4_unicode_ci ASC, id ASC";
        $doc_stmt = mysqli_prepare($connect, "SELECT id, name as doctor_name, specialty, experience, bio as description, qualification, photo_url, status, schedule FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY {$doctor_order_sql} LIMIT 20");
    } else {
        $doc_stmt = mysqli_prepare($connect, "SELECT id, doctor_name, specialty, description, NULL as experience, NULL as qualification, NULL as photo_url, 'active' as status, NULL as schedule FROM crawled_doctors WHERE hospital_id = ? ORDER BY id ASC LIMIT 20");
    }
    if ($doc_stmt) {
        mysqli_stmt_bind_param($doc_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($doc_stmt);
        $doc_result = mysqli_stmt_get_result($doc_stmt);
        while ($doctor = mysqli_fetch_assoc($doc_result)) {
            $doctors_data[] = $doctor;
        }
        mysqli_stmt_close($doc_stmt);
    }
}

// #21 fix: review count with fallback
$reviews_data = [];
$reviews_count = 0;
$rev_cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1 AND review_status = 'published'");
if ($rev_cnt_stmt) {
    mysqli_stmt_bind_param($rev_cnt_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($rev_cnt_stmt);
    $rev_cnt_result = mysqli_stmt_get_result($rev_cnt_stmt);
    $reviews_count = intval(mysqli_fetch_assoc($rev_cnt_result)['cnt'] ?? 0);
    mysqli_stmt_close($rev_cnt_stmt);
}
if ($reviews_count == 0 && !empty($hospital['review_count'])) {
    $reviews_count = intval($hospital['review_count']);
}

if ($current_tab === 'reviews') {
    $reviews_query = "SELECT * FROM hospital_reviews WHERE hospital_id = {$hospital_id} AND is_active = 1 AND review_status = 'published' ORDER BY created_at DESC LIMIT 20";
    $reviews_result = mysqli_query($connect, $reviews_query);
    while ($review = mysqli_fetch_assoc($reviews_result)) {
        $reviews_data[] = $review;
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($hospital['name']); ?> | <?= site_name() ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #AC1830;
            --color-primary-light: #fce4ec;
            --color-primary-dark: #8A1428;
            --color-text-primary: #333333;
            --color-text-secondary: #666666;
            --color-text-muted: #999999;
            --color-bg-white: #ffffff;
            --color-bg-light: #f8f8f8;
            --color-bg-gray: #eeeeee;
            --color-border: #f0f0f0;
            --color-star: #fbbf24;
            --color-tag-bg: #F0F4FF;
            --color-tag-text: #4A90E2;
            --color-kakao: #fee500;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body,
        button,
        input,
        select,
        textarea {
            font-family: 'Noto Sans KR', sans-serif !important;
        }

        body {
            background: var(--color-bg-light);
            color: var(--color-text-primary);
            line-height: 1.6;
        }

        html {
            -webkit-tap-highlight-color: transparent;
        }

        a,
        button,
        input,
        textarea,
        select,
        [role="button"],
        .btn,
        .modal-btn,
        .tab-item {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }

        a:focus:not(:focus-visible),
        button:focus:not(:focus-visible),
        [role="button"]:focus:not(:focus-visible) {
            outline: none;
        }

        /* PC 중앙 정렬 컨테이너 */
        .page-container {
            max-width: 640px;
            margin: 0 auto;
            background: var(--color-bg-white);
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        /* 페이지 헤더 — #14 수정: sticky로 변경, 빈 공간 제거 */
        .header {
            position: sticky;
            top: 0;
            width: 100%;
            max-width: 640px;
            padding: 14px 16px;
            background: var(--bs-body-bg, #fff);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 99;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: none;
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            color: inherit;
            cursor: pointer;
            text-decoration: none;
        }

        .global-nav-share {
            width: 36px;
            height: 36px;
            margin-left: auto;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            color: var(--color-text-primary, #111);
            font-size: 22px;
            cursor: pointer;
        }

        .global-nav-share:hover,
        .global-nav-share:focus-visible {
            color: var(--color-primary, #AC1830);
        }

        .share-copy-toast {
            position: fixed;
            left: 50%;
            bottom: 84px;
            z-index: 2000;
            transform: translate(-50%, 12px);
            padding: 10px 16px;
            border-radius: 20px;
            background: rgba(20, 20, 20, 0.9);
            color: #fff;
            font-size: 13px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .share-copy-toast.is-visible {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        .hospital-name-header {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            max-width: 60%;
            text-align: center;
            font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 이미지 갤러리 */
        .image-gallery-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: var(--color-bg-light);
            overflow: hidden;
            margin-top: 0; /* #14 fix */
        }

        .image-gallery {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .gallery-item {
            min-width: 100%;
            height: 100%;
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-counter {
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-primary);
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .image-gallery-container:hover .gallery-nav-btn {
            opacity: 1;
        }

        .gallery-nav-btn.prev { left: 12px; }
        .gallery-nav-btn.next { right: 12px; }
        .gallery-nav-btn:disabled {
            opacity: 0.2;
            cursor: not-allowed;
        }

        /* 갤러리 도트 인디케이터 */
        .gallery-dots {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 6px;
            z-index: 10;
        }
        .gallery-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            border: none; padding: 0; cursor: pointer;
            transition: background 0.3s, transform 0.3s;
        }
        .gallery-dot.active {
            background: var(--bs-body-bg, #fff);
            transform: scale(1.3);
        }

        /* 시술 상세 이미지 확대 모달 */
        #treatment-images,
        .treatment-image-empty {
            width: calc(100% + 40px);
            margin-left: -20px;
        }
        .treatment-image {
            width: 100%;
        }
        .treatment-image img {
            width: 100%;
            height: auto;
            display: block;
            cursor: zoom-in;
        }
        .treatment-image-modal {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 56px 16px 24px;
            background: rgba(0, 0, 0, 0.92);
            overflow: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            touch-action: none;
        }
        .treatment-image-modal.show {
            display: flex;
        }
        .treatment-image-modal.image-zoomed {
            align-items: flex-start;
            justify-content: flex-start;
        }
        .treatment-image-modal img {
            display: block;
            max-width: 100%;
            max-height: calc(100vh - 80px);
            object-fit: contain;
            cursor: zoom-in;
            touch-action: none;
            user-select: none;
            -webkit-user-drag: none;
            transition: width 0.2s ease;
        }
        .treatment-image-modal img.zoomed {
            max-width: none;
            max-height: none;
            width: auto;
            cursor: zoom-out;
        }
        .treatment-image-zoom-controls {
            position: fixed;
            left: 50%;
            bottom: max(20px, env(safe-area-inset-bottom));
            z-index: 10001;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 5px;
            border-radius: 24px;
            background: rgba(20, 20, 20, 0.78);
            backdrop-filter: blur(6px);
            transform: translateX(-50%);
        }
        .treatment-image-zoom-controls button {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }
        .treatment-image-zoom-controls button:disabled {
            opacity: 0.35;
            cursor: default;
        }
        .treatment-image-zoom-value {
            width: 54px;
            color: #fff;
            font-size: 13px;
            text-align: center;
        }
        .treatment-image-modal-close {
            position: fixed;
            top: max(14px, env(safe-area-inset-top));
            right: 16px;
            z-index: 10001;
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
        }
        body.treatment-image-modal-open {
            overflow: hidden;
        }

        /* 병원 정보 */
        .hospital-info-section {
            padding: 10px 20px;
            background: var(--color-bg-white, #fff);
        }

        .hospital-name {
            font-size: calc(30px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--color-text-primary);
        }

        .hospital-specialty {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
            margin-bottom: 12px;
        }

        .rating-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .star-icon {
            color: var(--color-star);
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .rating-score {
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: bold;
            color: var(--color-text-primary);
        }

        .review-count {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
        }

        /* 태그 */
        .tags-section {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .tag {
            padding: 6px 12px;
            background: var(--color-tag-bg);
            color: var(--color-tag-text);
            border-radius: 4px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
        }

        /* 영업시간 & 주소 */
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--color-border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 24px;
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            margin-right: 12px;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
        }

        .info-content strong {
            color: var(--color-text-primary);
        }

        /* 영업시간 확장 */
        .business-hours-expand {
            margin-top: 12px;
            display: none;
        }

        .business-hours-expand.show {
            display: block;
        }

        .hours-day-row {
            display: flex;
            padding: 8px 0;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .hours-day-name {
            width: 30px;
            color: var(--color-text-secondary);
        }

        .hours-day-time {
            flex: 1;
            color: var(--color-text-primary);
        }

        .hours-toggle-btn {
            background: none;
            border: none;
            color: var(--color-text-muted);
            cursor: pointer;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
            padding: 4px;
        }

        .hours-toggle-icon {
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            transition: transform 0.3s;
        }

        .hours-toggle-icon.expanded {
            transform: rotate(180deg);
        }

        /* 병원 특징 아이콘 */
        .features-section {
            display: flex;
            padding: 16px 20px;
            background: var(--color-bg-white, #fff);
            border-top: 8px solid var(--color-bg-light);
            gap: 16px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .features-section::-webkit-scrollbar {
            display: none;
        }

        .feature-item {
            text-align: center;
            min-width: 60px;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: var(--color-bg-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1));
            margin: 0 auto 6px;
        }

        .feature-label {
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
        }

        /* 탭 메뉴 */
        /* #14 fix: tab-menu under header (global_nav 제거됨) */
        .tab-menu {
            position: sticky;
            top: 64px;
            background: var(--color-bg-white, #fff);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            z-index: 90;
        }

        .tab-item {
            flex: 1;
            padding: 16px 12px;
            text-align: center;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            color: var(--color-text-muted);
            cursor: pointer;
            border-bottom: 2px solid #AAAAAA;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            user-select: none;
        }

        .tab-item:active {
            background: rgba(172, 24, 48, 0.06);
            color: var(--color-primary);
        }

        .tab-item:focus-visible {
            outline: 2px solid rgba(172, 24, 48, 0.28);
            outline-offset: -2px;
        }

        .tab-item.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .tab-count {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-muted);
            margin-left: 4px;
        }

        .tab-item.active .tab-count {
            color: var(--color-primary);
        }

        /* 탭 컨텐츠 */
        .tab-content {
            padding: 0 20px;
            background: var(--color-bg-white, #fff);
            min-height: 400px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* 시술목록: 병원 상세 시안의 단순한 가격 카드 */
        .procedure-list {
            display: grid;
            gap: 16px;
        }

        .procedure-card {
            display: block;
            padding: 22px 20px 20px;
            color: var(--color-text-primary, #111);
            text-decoration: none;
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border, #ddd);
            border-radius: 0;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .procedure-card:hover {
            color: var(--color-text-primary, #111);
            border-color: var(--color-primary, #c41432);
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .procedure-name {
            margin: 0 0 28px;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
            word-break: keep-all;
        }

        .procedure-vat {
            display: block;
            margin-bottom: 3px;
            color: var(--color-text-muted, #aaa);
            font-size: 13px;
        }

        .procedure-price {
            color: #d00000;
            font-size: 22px;
            line-height: 1.25;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .procedure-price-unit {
            margin-left: 2px;
            color: var(--color-text-primary, #111);
            font-size: 22px;
            font-weight: 400;
        }

        .procedure-price-row {
            display: flex;
            align-items: baseline;
            justify-content: flex-start;
            width: 100%;
            flex-wrap: wrap;
            gap: 12px;
            text-align: left;
        }

        .procedure-price-value {
            margin-left: 0;
            white-space: nowrap;
        }

        .procedure-card > .procedure-price {
            display: block;
            width: 100%;
            text-align: left;
        }

        .procedure-discount-rate {
            color: var(--color-primary, #AC1830);
            font-size: 22px;
            line-height: 1.25;
            font-weight: 800;
        }

        .procedure-regular-price {
            color: var(--color-text-muted, #aaa);
            font-size: 16px;
            text-decoration: line-through;
            white-space: nowrap;
        }

        .matched-option-card {
            display: flex;
            min-height: 122px;
            padding: 20px 24px 18px;
            border: 1px solid var(--color-border, #ddd);
            margin-bottom: 16px;
            flex-direction: column;
            color: inherit;
            text-decoration: none;
            background: var(--color-bg-white, #fff);
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .matched-option-card:hover {
            color: inherit;
            border-color: var(--color-primary, #AC1830);
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .matched-option-name {
            margin-bottom: 14px;
            font-size: 16px;
            line-height: 1.4;
            font-weight: 700;
            color: var(--color-text-primary, #111);
            word-break: keep-all;
        }

        .matched-option-card .procedure-price-row {
            justify-content: flex-end;
            margin-top: 0;
            gap: 18px;
            text-align: right;
        }

        .matched-option-card .procedure-vat {
            margin-top: auto;
            margin-bottom: 6px;
            text-align: right;
        }

        .matched-option-card .procedure-price {
            font-size: 22px;
        }

        .matched-option-card .procedure-price-unit {
            font-size: 14px;
            font-weight: 500;
        }

        .matched-option-card .procedure-regular-price {
            font-size: 13px;
        }

        @media (max-width: 480px) {
            .procedure-price-row { gap: 7px; }
            .procedure-discount-rate { font-size: 18px; }
            .procedure-price, .procedure-price-unit { font-size: 22px; }
            .procedure-regular-price { font-size: 14px; }
            .matched-option-card { min-height: 112px; padding: 18px 20px 16px; }
            .matched-option-name { font-size: 15px; margin-bottom: 12px; }
            .matched-option-card .procedure-price-row { gap: 14px; }
            .matched-option-card .procedure-price { font-size: 22px; }
            .matched-option-card .procedure-price-unit,
            .matched-option-card .procedure-regular-price { font-size: 13px; }
        }

        /* 후기 탭 */
        .review-list {
            display: block;
        }

        .review-card {
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 20px;
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .reviewer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--color-bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-muted);
        }

        .reviewer-name {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
        }

        .review-time {
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-muted);
            margin-left: auto;
        }

        .review-rating {
            display: flex;
            gap: 4px;
            margin-bottom: 12px;
        }

        .star {
            color: var(--color-text-muted);
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .star.filled {
            color: var(--color-star);
        }

        .review-content {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            line-height: 1.6;
            color: var(--color-text-primary);
        }

        .treatment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .treatment-tag {
            padding: 4px 8px;
            background: var(--color-tag-bg);
            color: var(--color-tag-text);
            border-radius: 4px;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        }

        /* 후기 카드 — review_detail.php .review-card-area 와 동일 구성 */
        .review-card-area { padding: 16px 0; border-bottom: 1px solid var(--color-border, #e0e0e0); }
        .review-card-area .tag { display: inline-block; margin: 5px 4px 5px 0; padding: 3px 8px; background: #FFEEEE; color: #AC1830; border-radius: 4px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; }
        .review-card-area .review-title { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; color: var(--color-text-primary, #000); margin: 6px 0 4px; line-height: 1.4; }
        .review-card-area .review-body { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); line-height: 1.8; color: var(--color-text-primary); }
        .review-card-area .review-body p { margin-bottom: 8px; }
        .review-card-area .review-body img { max-width: 100%; border-radius: 6px; margin: 8px 0; }
        .post-thumbs { width: 100%; display: grid; gap: 4px; border-radius: 8px; overflow: hidden; margin-top: 10px; }
        .post-thumbs.thumbs-1 { grid-template-columns: 1fr; max-width: 50%; }
        .post-thumbs.thumbs-2 { grid-template-columns: 1fr 1fr; }
        .post-thumbs.thumbs-3plus { grid-template-columns: repeat(3, 1fr); }
        .post-thumbs .thumb-item { position: relative; aspect-ratio: 1/1; overflow: hidden; background: var(--color-bg-light, #f5f5f5); cursor: pointer; }
        .post-thumbs .thumb-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .post-author-row { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
        .post-avatar { width: 35px; height: 35px; border-radius: 50%; background: #AAAAAA; overflow: hidden; flex-shrink: 0; }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .post-author-name { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 400; color: var(--color-text-primary, #000); }
        .post-stat-group { display: inline-flex; align-items: center; gap: 20px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-text-primary, #000); margin-left: auto; }
        .post-stat-group .stat { display: inline-flex; align-items: center; gap: 3px; }
        .post-stat-group .stat i { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); }
        .post-stat-group .heart-icon { color: #AC1830; }

        /* 의사 탭 */
        .doctor-list {
            display: grid;
            gap: 16px;
        }

        .doctor-card {
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            gap: 16px;
        }

        .doctor-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--color-bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(32px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-muted);
        }

        .doctor-info {
            flex: 1;
        }

        .doctor-name {
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            margin-bottom: 4px;
        }

        .doctor-description {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* 페이지네이션 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
        }

        .page-btn {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border: 1px solid var(--color-border);
            background: var(--color-bg-white, #fff);
            border-radius: 6px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-primary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-btn:hover {
            background: var(--color-bg-light);
        }

        .page-btn.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* [1차 검수 #12 - 2026-05-28] 하단 상담/예약 버튼 가시성 강화
           기존 z-index:100 은 bottom_nav.php 와 충돌하여 가려지는 케이스 있어 9999로 상향.
           bottom_nav(5탭)는 60~70px 높이이므로 그 위에 위치시킴 (bottom: 70px) */
        .bottom-bar {
            position: fixed;
            bottom: 70px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 640px;
            background: var(--color-bg-white, #fff);
            border-top: 1px solid var(--color-border);
            padding: 10px 16px;
            display: flex;
            gap: 8px;
            z-index: 9999;
            box-sizing: border-box;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
        }
        .bottom-bar .btn { flex: 1; min-width: 0; }
        .bottom-bar a.btn { text-decoration: none; }

        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 8px;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }

        .btn:active,
        .modal-btn:active,
        .global-nav-share:active,
        .back-btn:active {
            transform: scale(0.98);
        }

        .btn[aria-busy="true"],
        .modal-btn:disabled {
            cursor: wait;
            opacity: 0.78;
        }

        .btn-outline {
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border);
            color: var(--color-text-primary);
        }

        .btn-outline:hover {
            background: var(--color-bg-light);
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
        }

        /* 컨텐츠 여백 */
        .content-spacer {
            height: 80px;
        }

        /* 빈 상태 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-muted);
        }

        .empty-state-icon {
            font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1));
            margin-bottom: 16px;
        }

        .empty-state-text {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        }

        /* 상담 모달 */
        .consultation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10020;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
        }

        .consultation-modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--color-bg-white, #fff);
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            transform: translateY(8px);
            transition: transform 0.18s ease;
        }

        .consultation-modal.show .modal-content {
            transform: translateY(0);
        }

        body.consultation-modal-open {
            overflow: hidden;
        }

        .modal-header {
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            margin-bottom: 20px;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            margin-bottom: 12px;
        }

        .modal-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            margin-bottom: 12px;
            min-height: 100px;
            resize: vertical;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 6px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .modal-btn-cancel {
            background: var(--color-bg-light);
            color: var(--color-text-primary);
        }

        .modal-btn-submit {
            background: var(--color-primary);
            color: white;
        }

        /* 사진 첨부 영역 */
        .photo-upload-section {
            margin-bottom: 14px;
        }
        .photo-upload-label {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
            margin-bottom: 8px;
            display: block;
        }
        .photo-grid {
            display: flex;
            gap: 8px;
        }
        .photo-slot {
            width: 72px;
            height: 72px;
            border: 2px dashed var(--color-border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: var(--color-bg-light);
            flex-shrink: 0;
        }
        .photo-slot.filled {
            border-style: solid;
            border-color: var(--color-primary);
        }
        .photo-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-slot .add-icon {
            font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
        }
        .photo-slot .remove-btn {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            line-height: 20px;
            text-align: center;
            cursor: pointer;
            padding: 0;
            z-index: 2;
        }
        .consent-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 14px;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-secondary);
            line-height: 1.4;
        }
        .consent-row input[type="checkbox"] {
            margin-top: 2px;
            flex-shrink: 0;
        }
    

        /* ===== 04-01~04-04 design sync ===== */
        .tab-menu{border-bottom:1px solid #DCD6D8;gap:0}
        .tab-menu .tab-item{font-size:18px;font-weight:400;color:#1A1718;background:none;border:0;border-bottom:2px solid transparent;border-radius:0;height:46px;padding:0;display:flex;align-items:center;justify-content:center;flex:1;margin-bottom:-1px}
        .tab-menu .tab-item.active{color:#AC1830;font-weight:700;border-bottom-color:#AC1830;background:none}
        .tab-pane h3[id$="Intro"]{font-size:21px!important;font-weight:700;color:#1A1718;margin:20px 0 12px!important}
        .doctor-list{display:flex;flex-direction:column;gap:0}
        .doctor-card{display:flex;align-items:center;gap:18px;padding:16px;margin:0 0 10px;border:1px solid #DCD6D8;border-radius:0;background:#fff;box-shadow:none}
        .doctor-card .doctor-photo{width:64px;height:64px;border-radius:50%;background:#F4F2F3;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center}
        .doctor-card .doctor-photo img{border-radius:50%!important}
        .doctor-card .doctor-info{display:flex;flex-direction:column;gap:6px}
        .doctor-card .doctor-name{font-size:18px;font-weight:500;color:#1A1718;margin:0}
        .doc-specialty{display:flex;align-items:center;gap:5px;font-size:15px;color:#AC1830}
        .doc-treat-row{margin-top:0!important;flex-wrap:wrap!important;gap:6px!important}
        .doc-treat-row span{background:#F1EFEF!important;color:#1A1718!important;font-size:14px!important;font-weight:500;border-radius:0!important;padding:3px 7px!important}
        .procedure-list{display:flex;flex-direction:column}
        .procedure-card{display:flex;gap:12px;padding:16px 0;border:0;border-bottom:1px solid #ECE9EA;border-radius:0;background:#fff;box-shadow:none;text-decoration:none}
        .procedure-thumb{width:64px;height:84px;background:#F4F2F3;border-radius:6px;flex-shrink:0}
        .procedure-body{flex:1;min-width:0}
        .procedure-card .procedure-name{font-size:18px;font-weight:600;color:#1A1718;margin:0 0 4px}
        .procedure-addr{font-size:15px;color:#9A9395;margin-bottom:6px}
        .procedure-vat{font-size:14px;color:#9A9395;margin-left:6px;display:inline}
        .procedure-discount-rate,.procedure-card .procedure-price,.procedure-price-unit{color:#AC1830}
        .procedure-regular-price{color:#9A9395;text-decoration:line-through;font-size:15px}
        .rv-stars{color:#AC1830;font-size:17px;margin-bottom:6px}.rv-stars b{color:#1A1718;margin-left:4px}
        .review-card-area .tag{background:#FBEFF1;color:#AC1830;border-radius:4px}
        .review-card-area{border-bottom:1px solid #ECE9EA}

        /* 04-03 card exact */
        #tab-events h3#eventsIntro{font-size:18px!important;font-weight:700;margin:20px 0 4px!important}
        #tab-events .procedure-card{display:flex;gap:12px;padding:14px 0;margin:0}
        #tab-events .procedure-thumb{width:64px;height:84px;background:#DAD7D8;border-radius:0;flex-shrink:0}
        #tab-events .procedure-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
        .pc-title-row{display:flex;align-items:flex-start;gap:6px}
        .pc-title-row .procedure-name{flex:1;font-size:17px;font-weight:500;line-height:1.4;margin:0;color:#1A1718}
        .pc-wish{flex-shrink:0;line-height:0}.pc-wish .wishlist-btn{padding:0!important}
        .pc-addr{font-size:14px;color:#4A4446;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pc-sep{color:#CFC8CB}
        .pc-meta{display:flex;gap:10px;font-size:13px;color:#1A1718}
        .pc-meta span{display:flex;align-items:center;gap:2px}.pc-meta i{font-style:normal;color:#9A9395}
        .pc-vat{text-align:right;font-size:13px;color:#9A9395;margin-top:2px}
        #tab-events .procedure-price-row{display:flex;align-items:baseline;justify-content:flex-end;gap:8px;margin:0}
        #tab-events .procedure-discount-rate{font-size:18px;font-weight:700;color:#AC1830}
        #tab-events .procedure-regular-price{font-size:13px;color:#9A9395;text-decoration:line-through}
        #tab-events .procedure-price-value{display:inline-flex;align-items:baseline}
        #tab-events .procedure-price{font-size:20px;font-weight:700;color:#AC1830}
        #tab-events .procedure-price-unit{font-size:14px;font-weight:400;color:#1A1718;margin-left:1px}

        /* 04 font +6 (review tab) */
        .review-card-area .tag{font-size:16px}
        .review-card-area .review-title{font-size:21px}
        .review-card-area .review-body{font-size:18px}
        .post-author-name{font-size:18px}
        .post-stat-group{font-size:16px}
        .post-stat-group .stat i{font-size:21px}
        .doc-treat-row span{font-size:14px!important}

        /* ===== 05-05 상담 신청서 바텀시트 ===== */
        .consultation-modal{align-items:flex-end!important;justify-content:center;padding:0!important;background:rgba(26,23,24,.45)!important}
        .consultation-modal .modal-content.cm-sheet{width:100%!important;max-width:640px;max-height:92vh;border-radius:16px 16px 0 0!important;padding:0 0 34px!important;transform:translateY(40px);transition:transform .22s ease;overflow-y:auto;overscroll-behavior:contain}
        .consultation-modal.show .modal-content.cm-sheet{transform:translateY(0)}
        .cm-handle{width:100%;height:28px;position:relative;touch-action:none;cursor:grab}
        .cm-handle::before{content:"";position:absolute;left:50%;top:10px;width:40px;height:4px;margin-left:-20px;border-radius:2px;background:#AC1830}
        .cm-handle:active{cursor:grabbing}
        .cm-grab{touch-action:none}
        .consultation-modal .cm-sheet{touch-action:pan-y}
        .cm-body{padding:18px 20px 0;color:#1A1718}
        .cm-title{font-size:19px;font-weight:700}
        .cm-sub{font-size:13px;color:#4A4446;margin-top:6px}
        .cm-hosp{margin-top:14px;padding:12px 14px;background:#F7F5F5;display:flex;align-items:center;gap:8px;font-size:14px}
        .cm-hosp i{color:#AC1830;font-size:16px}.cm-hosp b{font-weight:700}.cm-hosp span{color:#6B6466;font-size:12px}
        .cm-label{padding:16px 0 8px;font-size:13px;font-weight:700}
        .cm-label em{font-style:normal;color:#AC1830}.cm-opt{font-weight:400;color:#9A9395}
        .cm-chips{display:flex;flex-wrap:wrap;gap:6px}
        .cm-chip{height:32px;padding:0 12px;display:flex;align-items:center;border-radius:16px;font-size:13px;white-space:nowrap;border:1px solid #DCD6D8;color:#1A1718;background:#fff;cursor:pointer}
        .cm-chip.on{border-color:#AC1830;color:#AC1830;background:#FBEFF1;font-weight:700}
        .cm-note{margin-top:8px;font-size:12px;line-height:1.6;color:#6B6466}
        .cm-row{display:flex;gap:8px}
        .cm-input{height:44px;box-sizing:border-box;border:1px solid #DCD6D8;border-radius:0;padding:0 12px;font-size:14px;flex:1;min-width:0;background:#fff;color:#1A1718}
        .cm-input.cm-name{flex:0 0 110px}
        .cm-textarea{width:100%;height:64px;box-sizing:border-box;border:1px solid #DCD6D8;border-radius:0;padding:10px 12px;font-size:13px;resize:none;background:#fff;color:#1A1718;font-family:inherit}
        .cm-input:focus,.cm-textarea:focus{outline:none;border-color:#1A1718}
        .cm-agree{display:flex;align-items:center;gap:8px;padding:14px 0 0;font-size:13px;cursor:pointer}
        .cm-agree input{position:absolute;opacity:0;pointer-events:none}
        .cm-box{width:18px;height:18px;box-sizing:border-box;border:1px solid #CFC8CB;border-radius:2px;flex-shrink:0;background:#fff center/13px no-repeat}
        .cm-agree input:checked + .cm-box{background-color:#AC1830;border-color:#AC1830;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E")}
        .cm-agree-t{flex:1}.cm-agree-t em{font-style:normal;color:#AC1830}
        .cm-view{font-size:12px;color:#6B6466;text-decoration:underline}
        .cm-submit{display:flex;align-items:center;justify-content:center;width:100%;height:52px;margin-top:16px;border:1px solid #AC1830;border-radius:4px;background:#AC1830;color:#fff;font-size:16px;font-weight:500;cursor:pointer}
        .cm-submit:disabled{opacity:.6}
        .cm-cancel{display:block;margin:10px auto 0;background:none;border:0;color:#6B6466;font-size:13px;padding:6px 12px;cursor:pointer}

        /* compact: 한 화면에 모두 표시 */
        .consultation-modal .modal-content.cm-sheet{max-height:100dvh;padding-bottom:max(12px,env(safe-area-inset-bottom))!important}
        .cm-handle{height:24px}.cm-handle::before{top:8px}
        .cm-body{padding:4px 20px 0}
        .cm-title{display:flex;align-items:center;justify-content:space-between;font-size:18px;line-height:1.3}
        .cm-x{background:none;border:0;font-size:26px;line-height:1;color:#6B6466;padding:0 2px;cursor:pointer}
        .cm-sub{font-size:12px;margin-top:2px}
        .cm-hosp{margin-top:10px;padding:9px 12px;font-size:13px}
        .cm-label{padding:10px 0 6px;font-size:12px}
        .cm-chip{height:30px;padding:0 11px;font-size:12px}
        .cm-chips{gap:5px}
        .cm-note{margin-top:4px;font-size:11px;line-height:1.45}
        .cm-input{height:40px}
        .cm-msgrow{display:flex;gap:8px;align-items:stretch}
        .cm-textarea{flex:1;height:44px!important;padding:8px 10px;min-width:0}
        .cm-photo{margin:0!important}
        .cm-photo .photo-grid{gap:6px;height:100%}
        .cm-photo .photo-slot{width:44px;height:44px;border-width:1px;border-radius:4px}
        .cm-photo .photo-slot .add-icon{font-size:18px}
        .cm-agree{padding-top:10px}
        .consent-row{margin:8px 0 0;font-size:11px}
        .cm-submit{height:48px;margin-top:12px}
        @media (max-height:700px){.cm-hosp{display:none}.cm-sub{display:none}.cm-note{display:none}}

        /* 푸터 분리: 신청하기 버튼은 시트와 따로 움직인다 */
        .cm-wrap{position:relative;width:100%;max-width:640px;display:flex;flex-direction:column;max-height:100dvh}
        .consultation-modal .modal-content.cm-sheet{padding-bottom:calc(72px + env(safe-area-inset-bottom))!important;touch-action:pan-y}
        .cm-footer{position:absolute;left:0;right:0;bottom:0;z-index:2;background:#fff;padding:12px 20px max(12px,env(safe-area-inset-bottom));box-sizing:border-box}
        .cm-footer .cm-submit{margin-top:0}

        /* 05-06 완료 화면 */
        .consultation-modal.is-done form.cm-body{display:none!important}
        .consultation-modal .cm-ok{display:none}
        .consultation-modal.is-done .cm-ok{display:flex}
        .consultation-modal.is-done .modal-btn-submit:not(.cm-ok){display:none}
        .cm-done{padding:16px 20px 4px;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center}
        .cm-done[hidden]{display:none}
        .cm-done-title{font-size:20px;font-weight:700;line-height:1.45}
        .cm-done-msg{font-size:15px;line-height:1.6;color:#4A4446}
        .cm-done-phone{align-self:stretch;margin-top:4px;padding:12px 14px;background:#F7F5F5;display:flex;align-items:center;gap:10px;font-size:14px;text-align:left}
        .cm-done-phone-t{flex:1}.cm-done-phone-t b{font-weight:700}
        .cm-done-phone-t em{display:block;font-style:normal;font-size:12px;color:#6B6466;margin-top:2px}
        .cm-done-save{height:32px;padding:0 12px;border:1px solid #AC1830;background:#fff;color:#AC1830;font-size:13px;font-weight:700;cursor:pointer}
        .cm-done-note{margin-top:2px;font-size:12px;color:#6B6466}
        .cm-done-note a{color:#1A1718;text-decoration:underline}
        /* 토스트가 모달 뒤에 가려지던 문제 */
        .share-copy-toast{z-index:10100!important}
        .consultation-modal.show ~ .share-copy-toast,.share-copy-toast.show{bottom:96px}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
    <div class="page-container">
    <!-- 페이지 헤더 -->
    <div class="header">
        <a href="<?php echo htmlspecialchars($detail_from_wishlist ? 'my_wishlist.php' : ($detail_return_url ?: 'hospitals.php'), ENT_QUOTES, 'UTF-8'); ?>" id="detailBackBtn" class="back-btn"><i class="bi bi-chevron-left"></i></a>
        <div class="hospital-name-header"><?php echo htmlspecialchars($hospital['name']); ?></div>
        <button type="button" class="global-nav-share" id="pageShareCopyBtn" aria-label="공유하기" title="공유하기">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </button>
    </div>
    <div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite">링크가 복사되었습니다.</div>
    <script>
    (function () {
        var explicitReturn = <?php echo json_encode($detail_return_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var backBtn = document.getElementById('detailBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function (e) {
                // 찜 목록에서 진입한 상세 페이지는 항상 찜 목록으로 복귀한다.
                if (new URLSearchParams(window.location.search).get('from') === 'my_wishlist') {
                    e.preventDefault();
                    location.href = 'my_wishlist.php';
                    return;
                }
                if (explicitReturn) {
                    e.preventDefault();
                    location.href = explicitReturn;
                    return;
                }
                // Return to the list explicitly; booking may be the previous history entry.
                e.preventDefault();
                var target = new URL('hospitals.php', location.href);
                try {
                    var sort = localStorage.getItem('sb_selected_sort');
                    if (['manual', 'price', 'rating', 'popular', 'review'].indexOf(sort) !== -1) {
                        target.searchParams.set('sort', sort);
                    }
                } catch (ignored) {}
                location.href = target.href;
            });
        }

        var shareBtn = document.getElementById('pageShareCopyBtn');
        var shareToastTimer = null;

        function fallbackCopy(text, onSuccess) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            var copied = false;
            try {
                copied = document.execCommand('copy');
                if (copied && typeof onSuccess === 'function') onSuccess();
            } catch (e) {
                copied = false;
            } finally {
                document.body.removeChild(textarea);
            }
            return copied;
        }

        window.detailFallbackCopy = fallbackCopy;

        function showShareToast(message) {
            var toast = document.getElementById('pageShareCopyToast');
            if (!toast) return;
            toast.textContent = message;
            toast.classList.add('is-visible');
            window.clearTimeout(shareToastTimer);
            shareToastTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
            }, 1800);
        }

        window.showDetailToast = showShareToast;

        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                var shareText = '이거 완전 괜찮은 앱이야!\n서울미녀\n' + window.location.href;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(shareText).then(function () {
                        showShareToast('링크가 복사되었습니다.');
                    }).catch(function () {
                        showShareToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
                    });
                    return;
                }
                showShareToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
            });
        }
    })();
    </script>

    <!-- 탭 메뉴 -->
    <!-- [1차 검수 #9 - 2026-05-28] 상세 탭 재정의 [홈 / 의료진 / 시술목록 / 시술후기] -->
    <?php
    // 탭 전환 시에도 검색 시술 컨텍스트 유지 — 같은 시술의 설명/이미지를 계속 표시한다.
    $tab_qs = '';
    if ($matched_treatment_id > 0) $tab_qs .= '&treatment_id=' . $matched_treatment_id;
    if ($matched_treatment_query !== '') $tab_qs .= '&treatment=' . urlencode($matched_treatment_query);
    $tab_qs .= $detail_source_qs;
    ?>
    <div class="tab-menu">
        <a href="?id=<?php echo $hospital_id; ?>&tab=home<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'home' ? 'active' : ''; ?>">
            홈
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=doctors<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>">
            의료진
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=events<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'events' ? 'active' : ''; ?>">
            시술목록
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=reviews<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>">
            시술후기
        </a>
    </div>

    <!-- 이미지 갤러리 -->
    <?php if (!empty($hospital_images)): ?>
    <div class="image-gallery-container" id="galleryContainer">
        <div class="image-gallery" id="imageGallery">
            <?php if (empty($hospital_images)): ?>
            <div class="gallery-item">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='420'%3E%3Crect fill='%23f5f5f5' width='800' height='420'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' text-anchor='middle' dominant-baseline='middle' font-size='24'%3E<?php echo term('business', '병원'); ?> 이미지%3C/text%3E%3C/svg%3E"
                     alt="이미지 없음"
                     class="gallery-image">
            </div>
            <?php else: ?>
                <?php foreach ($hospital_images as $index => $image): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                         class="gallery-image"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (count($hospital_images) > 1): ?>
        <div class="gallery-counter">
            <span id="currentIndex">1</span>/<span id="totalImages"><?php echo count($hospital_images); ?></span>
        </div>

        <button class="gallery-nav-btn prev" id="prevBtn" onclick="navigateGallery(-1)">‹</button>
        <button class="gallery-nav-btn next" id="nextBtn" onclick="navigateGallery(1)">›</button>

        <div class="gallery-dots" id="galleryDots">
            <?php for ($di = 0; $di < min(count($hospital_images), 10); $di++): ?>
            <button class="gallery-dot<?php echo $di === 0 ? ' active' : ''; ?>" onclick="goToSlide(<?php echo $di; ?>)"></button>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 병원 정보 -->
    <div class="hospital-info-section">
        <div style="display:flex; align-items:center; gap:12px;">
            <h1 class="hospital-name" style="flex:1; margin-bottom:0;"><?php echo htmlspecialchars($hospital['name']); ?></h1>
            <div style="flex-shrink:0;"><?php renderWishlistButton('hospital', $hospital_id, 'lg', 'star'); ?></div>
        </div>
        <?php
        // #16 fix: dynamic specialty (gangnamunni_doctors -> crawled -> categories -> desc)
        $spec_text = '';
        $spec_stmt1 = mysqli_prepare($connect, "SELECT DISTINCT specialty FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' AND specialty IS NOT NULL AND specialty != '' LIMIT 5");
        if ($spec_stmt1) {
            mysqli_stmt_bind_param($spec_stmt1, 'i', $hospital_id);
            mysqli_stmt_execute($spec_stmt1);
            $spec_result1 = mysqli_stmt_get_result($spec_stmt1);
            $specs = [];
            $spec_map = [
                'surgery' => '성형외과', 'dermatology' => '피부과', 'plastic_surgery' => '성형외과',
                'orthopedics' => '정형외과', 'ophthalmology' => '안과', 'dentistry' => '치과',
                'internal_medicine' => '내과', 'obstetrics' => '산부인과', 'pediatrics' => '소아과',
                'psychiatry' => '정신건강의학과', 'ent' => '이비인후과', 'urology' => '비뇨의학과',
                'neurology' => '신경과', 'general' => '일반의', 'family_medicine' => '가정의학과',
                'rehabilitation' => '재활의학과',
            ];
            while ($sp = mysqli_fetch_assoc($spec_result1)) {
                $raw = trim($sp['specialty']);
                $specs[] = $spec_map[strtolower($raw)] ?? $raw;
            }
            mysqli_stmt_close($spec_stmt1);
            $specs = array_values(array_intersect(array_unique($specs), $tc_names));
            $spec_text = implode(', ', array_slice($specs, 0, 3));
        }
        if (empty($spec_text)) {
            $spec_stmt2 = mysqli_prepare($connect, "SELECT DISTINCT specialty FROM crawled_doctors WHERE hospital_id = ? AND specialty IS NOT NULL AND specialty != '' LIMIT 3");
            if ($spec_stmt2) {
                mysqli_stmt_bind_param($spec_stmt2, 'i', $hospital_id);
                mysqli_stmt_execute($spec_stmt2);
                $spec_result2 = mysqli_stmt_get_result($spec_stmt2);
                $specs2 = [];
                while ($sp = mysqli_fetch_assoc($spec_result2)) { $specs2[] = $sp['specialty']; }
                mysqli_stmt_close($spec_stmt2);
                $specs2 = array_values(array_intersect($specs2, $tc_names));
                $spec_text = implode(', ', $specs2);
            }
        }
        if (empty($spec_text) && !empty($matched_treatment_categories)) {
            $spec_text = implode(', ', array_slice($matched_treatment_categories, 0, 3));
        }
        if (empty($spec_text) && !empty($hospital['introduction'])) {
            if (preg_match_all('/([가-힣]+과)/u', $hospital['introduction'], $m)) {
                $filtered = array_filter(array_unique($m[1]), function($v) { return mb_strlen($v) >= 3 && mb_strlen($v) <= 8; });
                $filtered = array_intersect(array_values($filtered), $tc_names);
                if (!empty($filtered)) $spec_text = implode(', ', array_slice(array_values($filtered), 0, 3));
            }
        }
        if (!empty($spec_text)):
        ?>
        <p class="hospital-specialty"><?php echo htmlspecialchars($spec_text); ?> 전문</p>
        <?php endif; ?>

        <?php if (($hospital['show_rating'] ?? 1) == 1): ?>
        <div class="rating-row">
            <span class="star-icon"><img src="assets/icons/categories/rating_2.svg" alt="평점" style="width:calc(18px * var(--fs-scale,1) * var(--fs-page,1));height:calc(18px * var(--fs-scale,1) * var(--fs-page,1));display:block;"></span>
            <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 4.8, 1); ?></span>
            <span class="review-count">/ 5.0</span>
        </div>
        <?php endif; ?>

        <!-- 태그 -->
        <?php if (!empty($treatment_tags)): ?>
        <div class="tags-section">
            <?php foreach (array_slice($treatment_tags, 0, 10) as $tag): ?>
            <?php
            $tag_text = is_array($tag) ? ($tag[0] ?? '') : $tag;
            if (!empty($tag_text) && is_string($tag_text)):
            ?>
            <span class="tag"><?php echo htmlspecialchars($tag_text); ?></span>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- rating/태그 와 영업시간 사이 구분선 -->
        <hr style="border:none; border-top:1px solid var(--color-border);">

        <!-- 영업시간 -->
        <?php
        // 현재 진료중 여부 계산 (오늘 영업시간 범위 내 + 점심시간 제외)
        $is_open_now = false;
        $today_hours_str = $business_hours[$today_name] ?? $default_hours[$today_name] ?? '';
        if (preg_match('/(\d{1,2}):(\d{2})\s*[~\-–]\s*(\d{1,2}):(\d{2})/', $today_hours_str, $oh)) {
            $now_min = intval(date('H')) * 60 + intval(date('i'));
            $open_min = intval($oh[1]) * 60 + intval($oh[2]);
            $close_min = intval($oh[3]) * 60 + intval($oh[4]);
            if ($now_min >= $open_min && $now_min < $close_min) {
                $is_open_now = true;
                if (empty($business_hours['no_lunch_break'])
                    && !empty($business_hours['lunch_start']) && !empty($business_hours['lunch_end'])
                    && preg_match('/(\d{1,2}):(\d{2})/', $business_hours['lunch_start'], $ls)
                    && preg_match('/(\d{1,2}):(\d{2})/', $business_hours['lunch_end'], $le)) {
                    $lunch_s = intval($ls[1]) * 60 + intval($ls[2]);
                    $lunch_e = intval($le[1]) * 60 + intval($le[2]);
                    if ($now_min >= $lunch_s && $now_min < $lunch_e) $is_open_now = false;
                }
            }
        }
        ?>
        <div class="info-row" style="cursor: pointer; user-select: none;<?php if ($minimal_info_tab): ?> border-bottom:1px solid var(--color-border);<?php endif; ?>" onclick="toggleBusinessHours()">
            <span class="info-icon" style="margin-right:0;"><i class="bi bi-calendar2-check"></i></span>
            <div class="info-content">
                <div style="display: flex; align-items: center;">
                    <strong><?php if ($is_open_now): ?><span style="color:var(--color-primary,#AC1830);font-weight:700;">진료중</span> <?php endif; ?><?php echo htmlspecialchars($business_hours_text); ?> <span style="font-weight:400;color:var(--color-text-muted,#999);font-size:0.9em;">(공휴일/일요일 휴무)</span></strong>
                    <button class="hours-toggle-btn" style="margin-left:4px;" type="button">
                        <span class="hours-toggle-icon" id="hoursToggleIcon"><i class="bi bi-chevron-down"></i></span>
                    </button>
                </div>

                <div class="business-hours-expand" id="businessHoursExpand" onclick="event.stopPropagation();">
                    <?php foreach ($days as $day):
                        $day_time = $business_hours[$day] ?? $default_hours[$day];
                        $day_style = '';
                        if ($day_time === '휴무') $day_style = 'color:#ef4444;';
                        elseif ($day_time === '공사중') $day_style = 'color:#f59e0b;';
                        elseif ($day_time === '임시휴업') $day_style = 'color:#f97316;';
                        elseif (strpos($day_time, '문의') !== false) $day_style = 'color:#888;';
                    ?>
                    <div class="hours-day-row">
                        <span class="hours-day-name"><?php echo $day; ?></span>
                        <span class="hours-day-time" <?php if ($day_style): ?>style="<?php echo $day_style; ?>"<?php endif; ?>><?php echo htmlspecialchars($day_time); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php
                    $lunch_val = $business_hours['점심'] ?? '';
                    $lunch_start = $business_hours['lunch_start'] ?? $business_hours['점심시작'] ?? '';
                    $lunch_end = $business_hours['lunch_end'] ?? $business_hours['점심종료'] ?? '';
                    if (!empty($lunch_val) && strpos($lunch_val, '~') !== false) {
                        $lp = explode('~', $lunch_val);
                        $lunch_start = trim($lp[0]);
                        $lunch_end = trim($lp[1] ?? '');
                    }
                    if (empty($lunch_start) && !empty($lunch_val) && strpos($lunch_val, '-') !== false) {
                        $lp = explode('-', $lunch_val);
                        $lunch_start = trim($lp[0]);
                        $lunch_end = trim($lp[1] ?? '');
                    }
                    $no_lunch = $business_hours['no_lunch_break'] ?? false;
                    if (!$no_lunch && !empty($lunch_start) && !empty($lunch_end)):
                    ?>
                    <div class="hours-day-row" style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--bs-border-color, #eee);">
                        <span class="hours-day-name" style="color:#e67e22; width:auto; white-space:nowrap; margin-right:12px;">🍽 점심시간</span>
                        <span class="hours-day-time" style="color:#e67e22;"><?php echo htmlspecialchars($lunch_start . ' ~ ' . $lunch_end); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    $bh_comment = $business_hours['영업시간_비고'] ?? '';
                    if (!empty($bh_comment)):
                    ?>
                    <div class="hours-day-row" style="margin-top:6px; padding-top:6px; border-top:1px dashed var(--bs-border-color, #eee);">
                        <span class="hours-day-time" style="color:var(--color-text-secondary, #888); font-size:12px;">📋 <?php echo htmlspecialchars($bh_comment); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 검색하신 시술 (hospitals.php 시술 검색에서 넘어온 경우) — 홈 탭에서만 영업시간 아래 배치 -->
        <?php if ($matched_treatment && $current_tab === 'home'): ?>
        <div id="matched-treatment" style="scroll-margin-top:160px; margin:16px 0 4px; overflow:visible; background:var(--color-bg-white);">
            <!-- 선택한 시술에 등록된 모든 독립 가격 옵션 -->
            <?php if (!empty($matched_treatment_options)): ?>
                <?php foreach ($matched_treatment_options as $matched_option): ?>
                <?php
                $matched_option_booking_url = 'booking.php?id=' . (int)$hospital_id
                    . '&treatment_id=' . (int)$matched_treatment['treatment_id']
                    . '&option_id=' . (int)$matched_option['option_id']
                    . '&treatment=' . urlencode($matched_treatment['name'])
                    . $detail_source_qs;
                ?>
                <?php
                $matched_sale_price = (int)($matched_option['price'] ?? 0);
                $matched_regular_price = (int)($matched_option['regular_price'] ?? 0);
                $matched_discount_rate = (int)($matched_option['discount_rate'] ?? 0);
                ?>
                <a href="<?php echo htmlspecialchars($matched_option_booking_url, ENT_QUOTES, 'UTF-8'); ?>" class="matched-option-card">
                    <div class="matched-option-name">
                        <?php echo htmlspecialchars($matched_option['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <span class="procedure-vat">부가세 포함</span>
                    <div class="procedure-price-row">
                        <?php if ($matched_discount_rate > 0): ?>
                            <span class="procedure-regular-price"><?php echo number_format($matched_regular_price); ?>원</span>
                        <?php endif; ?>
                        <span class="procedure-price-value"><span class="procedure-price"><?php echo number_format($matched_sale_price); ?></span><span class="procedure-price-unit">원</span></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="padding:16px; border-bottom:1px solid var(--color-border); display:flex; align-items:baseline; justify-content:space-between; gap:16px;">
                <div style="min-width:0; font-size:20px; line-height:1.4; font-weight:700; color:#000; word-break:keep-all;">
                    <?php echo htmlspecialchars($matched_treatment['name'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div style="flex-shrink:0; color:var(--color-text-muted); font-size:14px; white-space:nowrap;">가격 미등록</div>
            </div>
            <?php endif; ?>

            <!-- 시술 설명 -->
            <div style="padding:16px 0;">
                <div style="font-size:18px; font-weight:700; color: #000000; margin-bottom:8px;">시술 설명</div>
                <?php if (!empty($matched_treatment['description'])): ?>
                <div style="font-size:13px; color:var(--color-text-secondary); line-height:1.6;"><?php echo nl2br(htmlspecialchars($matched_treatment['description'])); ?></div>
                <?php else: ?>
                <div style="font-size:13px; color:var(--color-text-muted);">등록된 시술 설명이 없습니다.</div>
                <?php endif; ?>
            </div>

            <!-- 상세 정보 — 병원이 이 시술에 대해 등록한 상세페이지 이미지 (#시술이미지) -->
            <div style="padding:16px 0; border-top:1px solid var(--color-border);">
                <div style="font-size:18px; font-weight:700; color: #000000; margin-bottom:10px;">상세 정보</div>
                <?php if (!empty($matched_treatment_images)): ?>
                <div id="treatment-images">
                    <?php foreach ($matched_treatment_images as $ti_idx => $ti): ?>
                    <figure class="treatment-image<?php echo $ti_idx > 0 ? ' treatment-image-more' : ''; ?>"
                            style="margin:0 0 10px;<?php echo $ti_idx > 0 ? ' display:none;' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($ti['image_url'], ENT_QUOTES); ?>"
                             alt="<?php echo htmlspecialchars($matched_treatment['name'] . ' 상세 이미지 ' . ($ti_idx + 1), ENT_QUOTES); ?>"
                             loading="lazy"
                             role="button"
                             tabindex="0"
                             onclick="openTreatmentImageModal(this)"
                             onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); openTreatmentImageModal(this); }"
                             style="width:100%; display:block; background:var(--color-bg-light);">
                        <?php if (!empty($ti['caption'])): ?>
                        <figcaption style="padding:6px 2px 0; font-size:12px; color:var(--color-text-secondary);"><?php echo htmlspecialchars($ti['caption']); ?></figcaption>
                        <?php endif; ?>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <?php if (count($matched_treatment_images) > 1): ?>
                <button type="button" id="treatment-images-more" onclick="moreTreatmentImages(this)" style="width:100%; margin-top:12px; border:1px solid #000; background:var(--color-bg-white); color:var(--color-text-primary); padding:11px; font-size:14px; font-weight:700; cursor:pointer;">이미지 더보기 (<?php echo count($matched_treatment_images) - 1; ?>)</button>
                <?php endif; ?>
                <?php else: ?>
                <div class="treatment-image-empty" style="aspect-ratio:4/3; background:var(--color-bg-light); border:1px dashed var(--color-border); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; color:var(--color-text-muted);">
                    <i class="bi bi-image" style="font-size:34px;"></i>
                    <span style="font-size:12px;">이미지 준비 중</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function(){ var el = document.getElementById('matched-treatment'); if (el) el.scrollIntoView({behavior:'smooth', block:'start'}); });
        // 첫 장만 보여주고 나머지는 "이미지 더보기"로 펼친다. (한글 주석)
        function moreTreatmentImages(btn){
            document.querySelectorAll('.treatment-image-more').forEach(function(el){ el.style.display = ''; });
            if (btn) btn.style.display = 'none';
        }
        </script>
        <?php endif; ?>

        <!-- 주소 (의료진/이벤트 탭에서는 숨김) -->
        <?php if (!$minimal_info_tab): ?>
        <div class="info-row" style="border-bottom:none;">
            <span class="info-icon" style="margin-right:0;"><i class="bi bi-geo-alt"></i></span>
            <div class="info-content" style="display:flex; align-items:center; gap:8px;">
                <span style="flex:1;"><?php echo htmlspecialchars($hospital['address'] ?? '주소 정보 없음'); ?></span>
                <?php if (!empty($hospital['address'])): ?>
                <button type="button" onclick="copyAddress(this)" data-address="<?php echo htmlspecialchars($hospital['address'], ENT_QUOTES); ?>" style="flex-shrink:0; display:inline-flex; align-items:center; gap:4px; padding:5px 10px; font-size:12px; border:1px solid var(--color-border,#eee); background:var(--color-bg-white,#fff); border-radius:6px; color:var(--color-text-secondary,#666); cursor:pointer; white-space:nowrap;">
                     주소복사
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 카카오 지도 (의료진/이벤트 탭에서는 숨김) -->
        <?php if (!$minimal_info_tab): ?>
        <?php if (!empty($is_app_mode)): ?>
        <!-- 앱 모드: 임베디드 지도 대신 네비 버튼만 표시 (WebView 내 지도 충돌 방지) -->
        <?php
        $map_lat = floatval($hospital['latitude'] ?? 0);
        $map_lng = floatval($hospital['longitude'] ?? 0);
        $map_addr = $hospital['address'] ?? '';
        $map_nm = $hospital['name'] ?? '';
        $has_loc = ($map_lat != 0 && $map_lng != 0) || !empty($map_addr);
        if ($has_loc):
        ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 4px;">
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://map.kakao.com/link/to/<?php echo urlencode($map_nm); ?>,<?php echo $map_lat; ?>,<?php echo $map_lng; ?>" target="_blank">🗺 카카오맵</a>
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://map.naver.com/v5/search/<?php echo urlencode($map_addr ?: $map_nm); ?>" target="_blank">🧭 네이버지도</a>
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $map_lat; ?>,<?php echo $map_lng; ?>&travelmode=driving" target="_blank">🌐 구글맵</a>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <!-- 웹 모드: 카카오 임베디드 지도 -->
        <?php
        $map_config = [
            'container_id' => 'detailMap',
            'latitude'     => $hospital['latitude'] ?? 0,
            'longitude'    => $hospital['longitude'] ?? 0,
            'address'      => $hospital['address'] ?? '',
            'name'         => $hospital['name'] ?? '',
            'height'       => '220px',
            'show_title'   => false,
            // 표시되는 주소와 지도 마커 위치를 일치시킨다(저장 좌표가 주소와 어긋난 경우 대응).
            'prefer_address' => true,
        ];
        include __DIR__ . '/includes/kakao_map.php';
        ?>
        <?php endif; ?>
        <?php endif; ?>

    </div>


    <!-- 병원 특징 (의료진/이벤트 탭에서는 숨김) -->
    <?php if (!empty($hospital_features) && !$minimal_info_tab): ?>
    <div class="features-section">
        <?php foreach ($hospital_features as $feature): ?>
        <div class="feature-item">
            <div class="feature-icon"><?php echo htmlspecialchars((string)$feature['feature_icon'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="feature-label"><?php echo htmlspecialchars($feature['feature_label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 탭 컨텐츠 -->
    <div class="tab-content">
        <!-- 홈 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'home' ? 'active' : ''; ?>" id="tab-home">
            <?php
            $has_intro = !empty(trim($hospital['introduction'] ?? ''));
            ?>
            <?php if ($has_intro): ?>
            <h3 style="font-size: 18px; margin-bottom: 16px;"><?= term("business") ?> 소개</h3>
            <div style="line-height: 1.8; color: var(--color-text-secondary);">
                <?php echo nl2br(htmlspecialchars($hospital['introduction'])); ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">??</div>
                <div class="empty-state-text">등록된 소개 정보가 없습니다.</div>
            </div>
            <?php endif; ?>

            <!-- 의료진 소개 (홈 탭 · 최대 2명) -->
            <?php if (!empty($doctors_data)): ?>
            <hr style="border:none; border-top:1px solid var(--color-border); margin:24px 0;">
            <h3 style="font-size: 18px; margin-bottom: 16px;">의료진 소개</h3>
            <div class="doctor-list">
                <?php foreach (array_slice($doctors_data, 0, 2) as $doctor):
                    $doc_link = ($doctors_source === 'gangnamunni') ? "doctor_detail.php?id={$doctor['id']}" : '#';
                    $doc_clickable = ($doctors_source === 'gangnamunni');
                    $doc_status = $doctor['status'] ?? 'active';
                    $doc_status_label = ['active'=>term('appointment', '진료') . ' 중','vacation'=>'휴가','leave'=>'휴직','resigned'=>'퇴직'][$doc_status] ?? '';
                ?>
                <article class="doctor-card" <?php if ($doc_clickable): ?>onclick="location.href='<?php echo $doc_link; ?>'" style="cursor:pointer;"<?php endif; ?>>
                    <div class="doctor-photo">
                        <?php if (!empty($doctor['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                        👨‍⚕️
                        <?php endif; ?>
                    </div>
                    <div class="doctor-info">
                        <h3 class="doctor-name">
                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                            <?php if ($doc_status !== 'active'): ?>
                            <span style="font-size:11px;color:#ef4444;font-weight:400;margin-left:6px;"><?php echo $doc_status_label; ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($doctor['specialty'])): ?>
                        <div class="doc-specialty"><i class="bi bi-file-medical"></i><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                        <?php endif; ?>
                        <?php
                        // 이 의사가 시술하는 항목: doctor_treatments → 병원 시술 → 기본값(임시)
                        $doc_treat_names = [];
                        $dt_stmt = mysqli_prepare($connect, "SELECT tm.name FROM gangnamunni_doctor_treatments dt JOIN gangnamunni_treatment_master tm ON dt.treatment_id = tm.id WHERE dt.doctor_id = ? AND dt.is_active = 1 ORDER BY dt.case_count DESC LIMIT 6");
                        if ($dt_stmt) {
                            mysqli_stmt_bind_param($dt_stmt, 'i', $doctor['id']);
                            mysqli_stmt_execute($dt_stmt);
                            $dt_res = mysqli_stmt_get_result($dt_stmt);
                            while ($dt_row = mysqli_fetch_assoc($dt_res)) $doc_treat_names[] = $dt_row['name'];
                            mysqli_stmt_close($dt_stmt);
                        }
                        if (empty($doc_treat_names) && !empty($treatments)) {
                            foreach (array_slice($treatments, 0, 5) as $_ht) {
                                if (!empty($_ht['name'])) $doc_treat_names[] = $_ht['name'];
                            }
                        }
                        if (empty($doc_treat_names)) {
                            $doc_treat_names = ['보톡스', '필러', '리프팅', '스킨부스터'];
                        }
                        ?>
                        <div class="doc-treat-row" style="display:flex;flex-wrap:nowrap;gap:5px;margin-top:8px;overflow:hidden;">
                            <?php foreach ($doc_treat_names as $tn): ?>
                            <span style="font-size:11px;color:#000;background:#f2f2f2;border-radius:4px;padding:3px 8px;white-space:nowrap;flex-shrink:0;"><?php echo htmlspecialchars($tn); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ($doctors_count > 2): ?>
            <div style="text-align:center;margin-top:12px;">
                <a href="?id=<?php echo $hospital_id; ?>&tab=doctors<?php echo $detail_source_qs; ?>" style="font-size:13px;color:var(--color-primary,#AC1830);text-decoration:none;"><?= term("staff", "의료진") ?> 전체 보기 →</a>
            </div>
            <?php endif; ?>
            <script>
            // 시술 태그: 한 줄에 안 들어가는 태그는 숨김
            (function(){
                function trimTreatTags(){
                    document.querySelectorAll('.doc-treat-row').forEach(function(row){
                        var max = row.clientWidth;
                        row.querySelectorAll('span').forEach(function(tag){
                            tag.style.display = '';
                            if (tag.offsetLeft + tag.offsetWidth > max) tag.style.display = 'none';
                        });
                    });
                }
                if (document.readyState !== 'loading') trimTreatTags();
                else document.addEventListener('DOMContentLoaded', trimTreatTags);
                window.addEventListener('resize', trimTreatTags);
            })();
            </script>
            <?php endif; ?>

        </div>

        <!-- 시술목록 탭 (URL 호환성을 위해 tab=events 유지) -->
        <div class="tab-pane <?php echo $current_tab === 'events' ? 'active' : ''; ?>" id="tab-events">
            <h3 id="eventsIntro" style="font-size: 18px; margin-bottom: 16px; scroll-margin-top: 130px;">시술목록</h3>
            <?php if (!empty($procedure_data)): ?>
            <div class="procedure-list">
                <?php foreach ($procedure_data as $procedure): ?>
                <?php
                $procedure_price = (int)($procedure['display_price'] ?? 0);
                $procedure_regular_price = (int)($procedure['display_regular_price'] ?? 0);
                $procedure_discount_rate = (int)($procedure['discount_rate'] ?? 0);
                $procedure_url = 'detail.php?id=' . $hospital_id
                    . '&treatment_id=' . (int)($procedure['treatment_id'] ?? 0)
                    . '&treatment=' . urlencode($procedure['name'] ?? '')
                    . $detail_source_qs;
                ?>
                <?php
                    $_pn = (string)($procedure['display_name'] ?? $procedure['name'] ?? '');
                    $_pp = preg_split('/\s*[\/|]\s*/u', $_pn);
                    if (count($_pp) > 1) {
                        $_last = '';
                        foreach (array_reverse($_pp) as $_x) { if (preg_match('/\d/', $_x)) { $_last = $_x; break; } }
                        $_pn_show = trim($_pp[0]) . ($_last !== '' && $_last !== $_pp[0] ? ' ' . trim($_last) : '');
                    } else { $_pn_show = $_pn; }
                    $_pa = (string)($hospital['address'] ?? '');
                    $_pd = function_exists('extract_addr_district') ? extract_addr_district($_pa) : '';
                    $_prt = (float)($hospital['rating'] ?? 0);
                    $_prc = (int)($hospital['review_count'] ?? 0);
                    ?>
                <a class="procedure-card" href="<?php echo htmlspecialchars($procedure_url, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="procedure-body">
                        <div class="pc-title-row">
                            <h4 class="procedure-name"><?php echo htmlspecialchars($_pn_show, ENT_QUOTES, 'UTF-8'); ?></h4>
                            <span class="pc-wish"><?php renderWishlistButton('hospital', (int)$hospital_id, 'sm', 'star'); ?></span>
                        </div>
                        <?php if ($_pa !== ''): ?>
                        <div class="pc-addr"><?php if ($_pd !== ''): ?><?php echo htmlspecialchars($_pd); ?> <span class="pc-sep">|</span> <?php endif; ?><?php echo htmlspecialchars($_pa); ?></div>
                        <?php endif; ?>
                        <div class="pc-meta">
                            <?php if ($_prt > 0): ?><span><img src="assets/icons/categories/rating_2.svg" alt="" width="12" height="12"><b><?php echo number_format($_prt, 1); ?></b><i>(<?php echo number_format($_prc); ?>)</i></span><?php endif; ?>
                            <?php if (!empty($reviews_count)): ?><span>병원후기<i>(<?php echo number_format((int)$reviews_count); ?>)</i></span><?php endif; ?>
                            <?php if ($_prt >= 4.8): ?><span>인기병원</span><?php endif; ?>
                        </div>
                        <?php if ($procedure_price > 0): ?>
                        <div class="pc-vat">VAT 포함</div>
                        <div class="procedure-price-row">
                            <?php if ($procedure_discount_rate > 0): ?>
                                <span class="procedure-discount-rate"><?php echo $procedure_discount_rate; ?>%</span>
                                <span class="procedure-regular-price"><?php echo number_format($procedure_regular_price); ?>원</span>
                            <?php endif; ?>
                            <span class="procedure-price-value"><span class="procedure-price"><?php echo number_format($procedure_price); ?></span><span class="procedure-price-unit">원</span></span>
                        </div>
                        <?php else: ?>
                        <div class="pc-vat">&nbsp;</div>
                        <div class="procedure-price-row"><span class="procedure-price-value"><span class="procedure-price" style="font-size:18px;">가격 문의</span></span></div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- 페이지네이션 -->
            <?php if ($procedure_count > $items_per_page): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($procedure_count / $items_per_page);
                for ($i = 1; $i <= min($total_pages, 10); $i++):
                ?>
                <a href="?id=<?php echo $hospital_id; ?>&tab=events&page=<?php echo $i; ?><?php echo $detail_source_qs; ?>"
                   class="page-btn <?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">등록된 시술이 없습니다.</div>
            </div>
            <?php endif; ?>
        </div>
        <!-- 후기 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>" id="tab-reviews">
            <h3 id="reviewsIntro" style="font-size: 18px; margin-bottom: 16px; scroll-margin-top: 130px;"><?php echo term('review', '시술후기'); ?></h3>
            <?php if (!empty($reviews_data)): ?>
            <div class="review-list">
                <?php foreach ($reviews_data as $review): ?>
                <?php
                // 후기 이미지 조회
                $review_imgs = [];
                $img_query = mysqli_prepare($connect, "SELECT image_url, thumbnail_url FROM review_images WHERE review_id = ? ORDER BY image_order ASC LIMIT 5");
                if ($img_query) {
                    mysqli_stmt_bind_param($img_query, 'i', $review['id']);
                    mysqli_stmt_execute($img_query);
                    $img_result = mysqli_stmt_get_result($img_query);
                    while ($ri = mysqli_fetch_assoc($img_result)) {
                        $review_imgs[] = $ri;
                    }
                    mysqli_stmt_close($img_query);
                }
                ?>
                <div class="review-card-area" onclick="location.href='review_detail.php?id=<?php echo intval($review['id']); ?>'" style="cursor:pointer;">
                    <?php if (!empty($review['rating'])): $_rt=(int)round($review['rating']); ?>
                    <div class="rv-stars"><?php echo str_repeat('★',$_rt).str_repeat('☆',max(0,5-$_rt)); ?> <b><?php echo number_format((float)$review['rating'],1); ?></b></div>
                    <?php endif; ?>
                    <?php if (!empty($review['treatments'])): ?>
                    <?php foreach (explode(',', $review['treatments']) as $t): ?>
                    <span class="tag"><?php echo htmlspecialchars(trim($t)); ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($review['title'])): ?>
                    <div class="review-title"><?php echo htmlspecialchars($review['title']); ?></div>
                    <?php endif; ?>

                    <div class="review-body">
                        <?php if (!empty($review['editor_content']) && ($review['editor_type'] ?? '') === 'quill'): ?>
                        <?php echo sanitizeHtml($review['editor_content']); ?>
                        <?php else: ?>
                        <?php echo nl2br(htmlspecialchars($review['content'])); ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($review_imgs)):
                        $img_count = count($review_imgs);
                        $thumb_class = $img_count === 1 ? 'thumbs-1' : ($img_count === 2 ? 'thumbs-2' : 'thumbs-3plus');
                    ?>
                    <div class="post-thumbs <?php echo $thumb_class; ?>" onclick="event.stopPropagation();">
                        <?php foreach ($review_imgs as $ri):
                            // 저장 시점과 별개로 출력 시점에도 URL 정화 (기존 데이터 방어)
                            $ri_url = sanitizeImageUrl($ri['image_url'] ?? '');
                            $ri_thumb = sanitizeImageUrl($ri['thumbnail_url'] ?? '');
                            $ri_src = $ri_thumb !== '' ? $ri_thumb : $ri_url;
                            if ($ri_src === '') continue;
                        ?>
                        <?php /* URL 은 data 속성으로만 전달한다 — 인라인 핸들러 안에 넣으면
                                 htmlspecialchars 로도 JS 문자열 탈출을 막을 수 없다 */ ?>
                        <div class="thumb-item" data-img-url="<?php echo htmlspecialchars($ri_url, ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation(); if (this.dataset.imgUrl) window.open(this.dataset.imgUrl, '_blank', 'noopener');">
                            <img src="<?php echo htmlspecialchars($ri_src, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo term('review', '후기'); ?> 이미지" loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="post-author-row">
                        <div class="post-avatar"><?php if (!empty($review['user_avatar'])): ?><img src="<?php echo htmlspecialchars($review['user_avatar']); ?>" alt=""><?php endif; ?></div>
                        <span class="post-author-name"><?php echo htmlspecialchars($review['user_name']); ?></span>
                        <?php if (!empty($review['is_verified'])): ?>
                        <span style="font-size:11px; color:#16a34a;">인증됨</span>
                        <?php endif; ?>
                        <div class="post-stat-group">
                            <span class="stat"><i class="bi bi-heart heart-icon"></i> <?php echo intval($review['like_count'] ?? 0); ?></span>
                            <span class="stat"><i class="bi bi-chat-square-text"></i> <?php echo intval($review['comment_count'] ?? 0); ?></span>
                            <span class="stat"><i class="bi bi-eye"></i> <?php echo intval($review['view_count'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">💬</div>
                <div class="empty-state-text">등록된 <?= term("review") ?>가 없습니다.<br>첫 번째 <?php echo term('review', '후기'); ?>를 작성해보세요!</div>
            </div>
            <?php endif; ?>
        </div>
        <!-- 의사 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>" id="tab-doctors">
            <h3 id="doctorsIntro" style="font-size: 18px; margin-bottom: 16px; scroll-margin-top: 130px;">의료진 소개</h3>
            <?php if (!empty($doctors_data)): ?>
            <div class="doctor-list">
                <?php foreach ($doctors_data as $doctor):
                    $doc_link = ($doctors_source === 'gangnamunni') ? "doctor_detail.php?id={$doctor['id']}" : '#';
                    $doc_clickable = ($doctors_source === 'gangnamunni');
                    $doc_status = $doctor['status'] ?? 'active';
                    $doc_status_label = ['active'=>term('appointment', '진료') . ' 중','vacation'=>'휴가','leave'=>'휴직','resigned'=>'퇴직'][$doc_status] ?? '';
                ?>
                <article class="doctor-card" <?php if ($doc_clickable): ?>onclick="location.href='<?php echo $doc_link; ?>'" style="cursor:pointer;"<?php endif; ?>>
                    <div class="doctor-photo">
                        <?php if (!empty($doctor['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                        👨‍⚕️
                        <?php endif; ?>
                    </div>
                    <div class="doctor-info">
                        <h3 class="doctor-name">
                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                            <?php if ($doc_status !== 'active'): ?>
                            <span style="font-size:11px;color:#ef4444;font-weight:400;margin-left:6px;"><?php echo $doc_status_label; ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($doctor['specialty'])): ?>
                        <div class="doc-specialty"><i class="bi bi-file-medical"></i><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                        <?php endif; ?>
                        <?php
                        // 이 의사가 시술하는 항목: doctor_treatments → 병원 시술 → 기본값(임시)
                        $doc_treat_names = [];
                        $dt_stmt = mysqli_prepare($connect, "SELECT tm.name FROM gangnamunni_doctor_treatments dt JOIN gangnamunni_treatment_master tm ON dt.treatment_id = tm.id WHERE dt.doctor_id = ? AND dt.is_active = 1 ORDER BY dt.case_count DESC LIMIT 6");
                        if ($dt_stmt) {
                            mysqli_stmt_bind_param($dt_stmt, 'i', $doctor['id']);
                            mysqli_stmt_execute($dt_stmt);
                            $dt_res = mysqli_stmt_get_result($dt_stmt);
                            while ($dt_row = mysqli_fetch_assoc($dt_res)) $doc_treat_names[] = $dt_row['name'];
                            mysqli_stmt_close($dt_stmt);
                        }
                        if (empty($doc_treat_names) && !empty($treatments)) {
                            foreach (array_slice($treatments, 0, 5) as $_ht) {
                                if (!empty($_ht['name'])) $doc_treat_names[] = $_ht['name'];
                            }
                        }
                        if (empty($doc_treat_names)) {
                            $doc_treat_names = ['보톡스', '필러', '리프팅', '스킨부스터'];
                        }
                        ?>
                        <div class="doc-treat-row" style="display:flex;flex-wrap:nowrap;gap:5px;margin-top:8px;overflow:hidden;">
                            <?php foreach ($doc_treat_names as $tn): ?>
                            <span style="font-size:11px;color:#000;background:#f2f2f2;border-radius:4px;padding:3px 8px;white-space:nowrap;flex-shrink:0;"><?php echo htmlspecialchars($tn); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">👨‍⚕️</div>
                <div class="empty-state-text">등록된 <?= term("staff") ?> 정보가 없습니다.</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $tab_intro_ids = [
            'events' => 'eventsIntro',
            'reviews' => 'reviewsIntro',
            'doctors' => 'doctorsIntro',
        ];
        $tab_intro_id = $tab_intro_ids[$current_tab] ?? '';
        if ($tab_intro_id !== ''):
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            if (!window.matchMedia('(max-width: 640px)').matches) return;
            var el = document.getElementById(<?php echo json_encode($tab_intro_id); ?>);
            if (!el) return;
            setTimeout(function(){
                var tabMenu = document.querySelector('.tab-menu');
                var stickyBottom = 160;
                if (tabMenu) {
                    var topPx = parseInt(getComputedStyle(tabMenu).top, 10) || 112;
                    stickyBottom = topPx + tabMenu.offsetHeight;
                }
                var y = el.getBoundingClientRect().top + window.pageYOffset - stickyBottom - 12;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }, 150);
        });
        </script>
        <?php endif; ?>

    </div>

    <div class="content-spacer"></div>

    <!-- #24 fix: bottom buttons no overlap -->
    <div class="bottom-bar">
        <button class="btn btn-primary" onclick="openDoctorConsult()" style="flex:1;min-width:0;border:none;border-radius:0;color:#fff;background:var(--color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?php echo term('consultation', '상담'); ?> 신청
        </button>
        <?php
        // Keep the treatment context that brought the user to this hospital.
        // booking.php validates it against the hospital's active treatments before preselecting it.
        $booking_url = 'booking.php?id=' . (int)$hospital['id'];
        if ($matched_treatment) {
            $booking_url .= '&treatment_id=' . (int)$matched_treatment['treatment_id']
                . '&treatment=' . urlencode($matched_treatment['name']);
        }
        $booking_url .= $detail_source_qs;
        ?>
        <a href="<?php echo htmlspecialchars($booking_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline" style="flex:1;min-width:0;border:1px solid var(--color-primary);border-radius:0;color:var(--color-primary);background:var(--bs-body-bg, #fff);text-decoration:none;display:flex;align-items:center;justify-content:center;white-space:nowrap;">
            <?php echo term('booking', '예약'); ?>하기
        </a>
    </div>

    <!-- 상담 신청 모달 (05-05 바텀시트) -->
    <?php
    $cm_chips_treat = [];
    foreach ($treatments as $_t) {
        $_n = trim((string)($_t['name'] ?? ''));
        if ($_n !== '' && !in_array($_n, $cm_chips_treat, true)) $cm_chips_treat[] = $_n;
        if (count($cm_chips_treat) >= 8) break;
    }
    if (empty($cm_chips_treat) && !empty($hospital_id)) {
        $_cmq = $connect->prepare("SELECT DISTINCT tm.name FROM gangnamunni_hospital_treatments ht INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1 ORDER BY tm.name LIMIT 8");
        if ($_cmq) {
            $_cmq->bind_param('i', $hospital_id);
            $_cmq->execute();
            $_cmr = $_cmq->get_result();
            while ($_cmrow = $_cmr->fetch_assoc()) { $cm_chips_treat[] = $_cmrow['name']; }
            $_cmq->close();
        }
    }
    $cm_chips_treat[] = '기타';
    $cm_addr = (string)($hospital['address'] ?? '');
    $cm_dist = '';
    foreach (preg_split('/\s+/u', trim($cm_addr)) as $_ap) {
        if ($_ap !== '' && mb_substr($_ap, -1, 1, 'UTF-8') === '구') { $cm_dist = mb_substr($_ap, 0, -1, 'UTF-8'); break; }
    }
    $cm_phone = trim((string)($hospital['phone'] ?? ''));
    ?>
    <div class="consultation-modal" id="consultModal">
        <div class="cm-wrap">
        <div class="modal-content cm-sheet">
            <div class="cm-handle"></div>
            <form id="consultForm" onsubmit="submitConsultation(event)" class="cm-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($consultation_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                <input type="hidden" name="type" id="consultType" value="doctor">
                <?php
                $logged_member = get_gangnamunni_member();
                $auto_name = !empty($logged_member['mb_name']) ? htmlspecialchars($logged_member['mb_name']) : '';
                $auto_phone = !empty($logged_member['mb_hp']) ? htmlspecialchars($logged_member['mb_hp']) : '';
                ?>
                <div class="cm-title cm-grab"><span><?php echo term('consultation', '상담'); ?> 신청서</span></div>
                <div class="cm-sub cm-grab">신청하면 병원에서 확인 후 연락드려요</div>
                <div class="cm-hosp"><i class="bi bi-hospital"></i><b><?php echo htmlspecialchars($hospital['name'] ?? ''); ?></b><?php if ($cm_dist !== ''): ?><span><?php echo htmlspecialchars($cm_dist); ?></span><?php endif; ?></div>

                <div class="cm-label">관심 시술 <em>*</em></div>
                <div class="cm-chips" data-group="treat">
                    <?php foreach ($cm_chips_treat as $_ci => $_c): ?><button type="button" class="cm-chip<?php echo $_ci === 0 ? ' on' : ''; ?>" data-v="<?php echo htmlspecialchars($_c, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($_c); ?></button><?php endforeach; ?>
                </div>

                <div class="cm-label">상담 방법 <em>*</em></div>
                <div class="cm-chips" data-group="method">
                    <button type="button" class="cm-chip on" data-v="전화 상담">전화 상담</button>
                    <button type="button" class="cm-chip" data-v="카카오톡 채널">카카오톡 채널</button>
                    <button type="button" class="cm-chip" data-v="방문 상담">방문 상담</button>
                </div>
                <div class="cm-note"><?php echo $cm_phone !== '' ? '병원 번호(' . htmlspecialchars($cm_phone) . ')로 전화드려요. ' : '병원에서 전화드려요. '; ?>모르는 번호 · 스팸 차단 중이면 번호를 저장해주세요</div>

                <div class="cm-label">연락 가능한 시간 <em>*</em></div>
                <div class="cm-chips" data-group="time">
                    <button type="button" class="cm-chip" data-v="오전">오전</button>
                    <button type="button" class="cm-chip on" data-v="오후">오후</button>
                    <button type="button" class="cm-chip" data-v="저녁">저녁</button>
                    <button type="button" class="cm-chip" data-v="언제든 괜찮아요">언제든 괜찮아요</button>
                </div>

                <div class="cm-label">이름 · 연락처 <em>*</em></div>
                <div class="cm-row">
                    <input type="text" name="name" class="cm-input cm-name" placeholder="이름" value="<?php echo $auto_name; ?>" required>
                    <input type="tel" name="phone" class="cm-input" placeholder="연락처" value="<?php echo $auto_phone; ?>" required>
                </div>

                <div class="cm-label"><?php echo term('consultation', '상담'); ?> 내용 <span class="cm-opt">(선택)</span></div>
                <div class="cm-msgrow">
                    <textarea name="message" class="cm-textarea" placeholder="궁금한 점이나 원하는 부위를 적어주세요"></textarea>
                    <div class="photo-upload-section cm-photo">
                        <input type="file" id="photoInput" accept="image/*" multiple style="display:none;" onchange="handlePhotoSelect(this)">
                        <div class="photo-grid" id="photoGrid">
                            <div class="photo-slot" id="addPhotoSlot" onclick="document.getElementById('photoInput').click()" title="사진 첨부 (선택, 최대 3장)">
                                <span class="add-icon">📷</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="consent-row" id="consentRow" style="display:none;">
                    <input type="checkbox" name="photo_consent" id="photoConsent" value="1">
                    <label for="photoConsent">상담 참고용 사진을 첨부합니다. 제공된 사진은 상담 목적으로만 사용되며, 상담 완료 후 30일 이내에 삭제됩니다.</label>
                </div>

                <label class="cm-agree"><input type="checkbox" id="cmAgree"><span class="cm-box"></span><span class="cm-agree-t"><em>(필수)</em> 개인정보 병원 제공 동의</span><a href="privacy_policy.php" target="_blank" rel="noopener" class="cm-view" onclick="event.stopPropagation()">보기</a></label>

            </form>
            <div class="cm-done" id="cmDone" hidden>
                <svg class="cm-done-ico" width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#AC1830" stroke-width="2.5"/><path d="M14 25l7 7 13-14" stroke="#AC1830" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="cm-done-title"><?php echo term('consultation', '상담'); ?> 신청이 완료되었어요</div>
                <div class="cm-done-msg"><?php echo htmlspecialchars($hospital['name'] ?? ''); ?>에서 확인 후<br><span id="cmDoneWhen">연락드려요</span></div>
                <?php if ($cm_phone !== ''): ?>
                <div class="cm-done-phone">
                    <span class="cm-done-phone-t">연락 번호 <b><?php echo htmlspecialchars($cm_phone); ?></b><em>모르는 번호 차단 중이면 저장해주세요</em></span>
                    <button type="button" class="cm-done-save" onclick="cmSaveContact()">번호 저장</button>
                </div>
                <?php endif; ?>
                <div class="cm-done-note">연락이 닿지 않으면 앱 알림과 <a href="mypage.php">마이페이지 &gt; 상담 내역</a>에서 알려드려요</div>
            </div>
        </div>
        <div class="cm-footer"><button type="submit" form="consultForm" class="modal-btn-submit cm-submit">신청하기</button><button type="button" class="cm-submit cm-ok" onclick="closeConsultModal()">확인</button></div>
        </div>
    </div>

    <!-- 시술 상세 이미지 확대 모달 -->
    <div class="treatment-image-modal" id="treatmentImageModal" role="dialog" aria-modal="true" aria-label="시술 상세 이미지" onclick="handleTreatmentImageModalBackdrop(event)">
        <button type="button" class="treatment-image-modal-close" aria-label="닫기" onclick="closeTreatmentImageModal()">×</button>
        <img id="treatmentImageModalImage" src="" alt="">
        <div class="treatment-image-zoom-controls" onclick="event.stopPropagation()">
            <button type="button" id="treatmentZoomOut" aria-label="축소" onclick="changeTreatmentImageZoom(-0.5)">−</button>
            <span class="treatment-image-zoom-value" id="treatmentZoomValue">100%</span>
            <button type="button" id="treatmentZoomIn" aria-label="확대" onclick="changeTreatmentImageZoom(0.5)">+</button>
        </div>
    </div>

    <script>
        // 이미지 갤러리 (터치 스와이프 + 자동 슬라이드)
        const gallery = document.getElementById('imageGallery');
        const currentIndexEl = document.getElementById('currentIndex');
        const galleryContainer = document.getElementById('galleryContainer');

        let currentIndex = 0;
        let totalImages = <?php echo count($hospital_images); ?>;
        let autoSlideTimer = null;
        let touchStartX = 0, touchEndX = 0, touchStartY = 0, isDragging = false;

        function navigateGallery(direction) {
            currentIndex += direction;
            // 무한 루프 (마지막→첫번째, 첫번째→마지막)
            if (currentIndex < 0) currentIndex = totalImages - 1;
            if (currentIndex >= totalImages) currentIndex = 0;
            updateGallery();
            resetAutoSlide();
        }

        function goToSlide(idx) {
            currentIndex = idx;
            updateGallery();
            resetAutoSlide();
        }

        function updateGallery() {
            if (gallery) {
                gallery.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';
            }
            if (currentIndexEl) currentIndexEl.textContent = currentIndex + 1;
            // 도트 업데이트
            var dots = document.querySelectorAll('.gallery-dot');
            dots.forEach(function(d, i) { d.classList.toggle('active', i === currentIndex); });
        }

        // 자동 슬라이드 (3초 간격)
        function startAutoSlide() {
            if (totalImages <= 1) return;
            autoSlideTimer = setInterval(function() { navigateGallery(1); }, 3000);
        }
        function resetAutoSlide() {
            clearInterval(autoSlideTimer);
            startAutoSlide();
        }
        startAutoSlide();

        // 터치 스와이프
        if (galleryContainer) {
            galleryContainer.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
                isDragging = true;
                clearInterval(autoSlideTimer);
            }, { passive: true });

            galleryContainer.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                isDragging = false;
                touchEndX = e.changedTouches[0].screenX;
                var diffX = touchStartX - touchEndX;
                var diffY = Math.abs(touchStartY - e.changedTouches[0].screenY);
                // 수평 스와이프만 (수직 스크롤과 구분)
                if (Math.abs(diffX) > 40 && diffY < 80) {
                    if (diffX > 0) navigateGallery(1);  // 왼쪽 스와이프 → 다음
                    else navigateGallery(-1);             // 오른쪽 스와이프 → 이전
                }
                resetAutoSlide();
            }, { passive: true });

            // 마우스 드래그 (PC)
            galleryContainer.addEventListener('mousedown', function(e) {
                touchStartX = e.clientX;
                isDragging = true;
                clearInterval(autoSlideTimer);
            });
            galleryContainer.addEventListener('mouseup', function(e) {
                if (!isDragging) return;
                isDragging = false;
                var diff = touchStartX - e.clientX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) navigateGallery(1);
                    else navigateGallery(-1);
                }
                resetAutoSlide();
            });
            galleryContainer.addEventListener('mouseleave', function() { isDragging = false; });
        }

        // 의사 상담
        function openDoctorConsult() {
            document.getElementById('consultType').value = 'doctor';
            const modal = document.getElementById('consultModal');
            const _sh = modal.querySelector('.cm-sheet');
            if (_sh) { _sh.style.transition = ''; _sh.style.transform = ''; }
            const _ft = modal.querySelector('.cm-footer'); if (_ft) { _ft.style.transition = ''; _ft.style.transform = ''; }
            modal.style.transition = ''; modal.style.background = ''; delete modal.dataset.closing;
            modal.classList.remove('is-done'); const _dn = document.getElementById('cmDone'); if (_dn) _dn.hidden = true;
            modal.classList.add('show');
            document.body.classList.add('consultation-modal-open');
            window.setTimeout(function() {
                modal.querySelector('input[name="name"]')?.focus();
            }, 80);
        }

        // 상담 모달 닫기
        // 05-06 상담 신청 완료 화면 (같은 시트 안에서 내용 교체)
        function cmShowDone(method, time) {
            const modal = document.getElementById('consultModal');
            const way = method === '카카오톡 채널' ? '카카오톡 채널로' : (method === '방문 상담' ? '방문 상담 일정으로' : '전화로');
            const when = time === '언제든 괜찮아요' ? '편하신 시간에' : time + ' 시간에';
            document.getElementById('cmDoneWhen').textContent = when + ' ' + way + ' 연락드려요';
            document.getElementById('cmDone').hidden = false;
            modal.classList.add('is-done');
            modal.querySelector('.cm-sheet').scrollTop = 0;
        }
        function cmSaveContact() {
            const name = <?php echo json_encode((string)($hospital['name'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
            const tel = <?php echo json_encode($cm_phone, JSON_UNESCAPED_UNICODE); ?>;
            const vcf = 'BEGIN:VCARD\nVERSION:3.0\nFN:' + name + '\nORG:' + name + '\nTEL;TYPE=WORK,VOICE:' + tel + '\nEND:VCARD\n';
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([vcf], {type: 'text/vcard'}));
            a.download = name + '.vcf';
            document.body.appendChild(a); a.click(); a.remove();
            window.showDetailToast?.('연락처 파일을 내려받았어요. 열어서 저장해주세요.');
        }

        function closeConsultModal() {
            const modal = document.getElementById('consultModal');
            const sheet = modal.querySelector('.cm-sheet');
            const foot = modal.querySelector('.cm-footer');
            if (!modal.classList.contains('show') || modal.dataset.closing === '1') return;
            modal.dataset.closing = '1';
            const H = sheet.offsetHeight, F = foot.offsetHeight;
            const cur = (new DOMMatrixReadOnly(getComputedStyle(sheet).transform)).m42 || 0;
            const fcur = (new DOMMatrixReadOnly(getComputedStyle(foot).transform)).m42 || 0;
            const target = H + 20, ms = 240;
            sheet.style.transition = 'none';
            sheet.animate([{transform: 'translateY(' + cur + 'px)'}, {transform: 'translateY(' + target + 'px)'}], {duration: ms, easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards'});
            // 푸터(신청하기)는 시트 윗면이 푸터에 닿을 때까지 제자리에 있다가 함께 내려간다
            const contact = Math.max(0, H - F);
            const hold = Math.max(0, Math.min(.99, (contact - cur) / Math.max(1, target - cur)));
            foot.animate([{transform: 'translateY(' + fcur + 'px)', offset: 0}, {transform: 'translateY(' + fcur + 'px)', offset: hold}, {transform: 'translateY(' + (F + 20) + 'px)', offset: 1}], {duration: ms, easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards'});
            modal.style.transition = 'background ' + ms + 'ms ease';
            modal.style.background = 'rgba(26,23,24,0)';
            window.setTimeout(function() {
                modal.classList.remove('show');
                document.body.classList.remove('consultation-modal-open');
                sheet.getAnimations().forEach(function(a) { a.cancel(); });
                foot.getAnimations().forEach(function(a) { a.cancel(); });
                sheet.style.transition = ''; sheet.style.transform = '';
                foot.style.transition = ''; foot.style.transform = '';
                modal.style.transition = ''; modal.style.background = '';
                delete modal.dataset.closing;
            }, ms + 20);
        }

        // 05-05: 시트를 아래로 끌어 닫기 (community 시트와 동일: 푸터 버튼은 시트가 닿을 때까지 고정)
        (function() {
            const modal = document.getElementById('consultModal');
            if (!modal) return;
            const wrap = modal.querySelector('.cm-wrap');
            const sheet = modal.querySelector('.cm-sheet');
            const foot = modal.querySelector('.cm-footer');
            let d = null;
            function moveTo(dy) {
                sheet.style.transform = 'translateY(' + dy + 'px)';
                foot.style.transform = 'translateY(' + Math.max(0, dy - (d.h - d.f)) + 'px)';
                modal.style.background = 'rgba(26,23,24,' + (0.45 * Math.max(0, 1 - dy / d.h)) + ')';
            }
            function begin(y, t) {
                d = {y0: y, last: y, t: t, v: 0, dy: 0, h: sheet.offsetHeight, f: foot.offsetHeight, active: false};
            }
            function track(y, t) {
                if (!d.active) {
                    if (y - d.y0 <= 4 || sheet.scrollTop > 0) return false;
                    d.active = true; d.y0 = y;
                    sheet.style.transition = 'none'; foot.style.transition = 'none'; modal.style.transition = 'none';
                    return true;
                }
                const dd = Math.max(0, y - d.y0);
                d.v = (y - d.last) / Math.max(1, t - d.t);
                d.last = y; d.t = t; d.dy = dd;
                moveTo(dd);
                return true;
            }
            function finish() {
                const x = d; d = null;
                if (!x || !x.active) return;
                if (x.dy > x.h * 0.25 || (x.dy > 8 && x.v > 0.8)) {
                    closeConsultModal();
                } else {
                    sheet.style.transition = 'transform .2s ease';
                    foot.style.transition = 'transform .2s ease';
                    modal.style.transition = 'background .2s ease';
                    sheet.style.transform = 'translateY(0)'; foot.style.transform = 'translateY(0)';
                    modal.style.background = '';
                    window.setTimeout(function() { sheet.style.transition = ''; sheet.style.transform = ''; foot.style.transition = ''; foot.style.transform = ''; modal.style.transition = ''; }, 210);
                }
            }
            wrap.addEventListener('touchstart', function(e) {
                if (modal.dataset.closing === '1' || e.touches.length !== 1) { d = null; return; }
                begin(e.touches[0].clientY, e.timeStamp);
            }, {passive: true});
            wrap.addEventListener('touchmove', function(e) {
                if (!d) return;
                if (track(e.touches[0].clientY, e.timeStamp) && e.cancelable) e.preventDefault();
            }, {passive: false});
            wrap.addEventListener('touchend', finish);
            wrap.addEventListener('touchcancel', finish);
            wrap.addEventListener('pointerdown', function(e) {
                if (e.pointerType === 'touch' || !e.isPrimary || e.button !== 0 || modal.dataset.closing === '1') return;
                if (!e.target.closest('.cm-handle, .cm-grab')) return;
                begin(e.clientY, e.timeStamp); d.mouse = e.pointerId;
                wrap.setPointerCapture(e.pointerId);
            });
            wrap.addEventListener('pointermove', function(e) {
                if (!d || d.mouse !== e.pointerId) return;
                track(e.clientY, e.timeStamp);
            });
            function pend(e) {
                if (!d || d.mouse !== e.pointerId) return;
                try { wrap.releasePointerCapture(e.pointerId); } catch (_) {}
                finish();
            }
            wrap.addEventListener('pointerup', pend);
            wrap.addEventListener('pointercancel', pend);
        })();

        // 시술 상세 이미지 확대/축소 (100% ~ 400%)
        let treatmentImageZoom = 1;
        let treatmentImageBaseWidth = 0;
        let treatmentTouchMode = '';
        let treatmentTouchStartDistance = 0;
        let treatmentTouchStartZoom = 1;
        let treatmentTouchStartX = 0;
        let treatmentTouchStartY = 0;
        let treatmentTouchStartScrollLeft = 0;
        let treatmentTouchStartScrollTop = 0;
        let treatmentImageGestureMoved = false;

        function openTreatmentImageModal(sourceImage) {
            const modal = document.getElementById('treatmentImageModal');
            const modalImage = document.getElementById('treatmentImageModalImage');
            if (!modal || !modalImage || !sourceImage) return;

            modalImage.src = sourceImage.currentSrc || sourceImage.src;
            modalImage.alt = sourceImage.alt || '시술 상세 이미지';
            modalImage.classList.remove('zoomed');
            modalImage.style.width = '';
            modal.classList.remove('image-zoomed');
            modal.classList.add('show');
            document.body.classList.add('treatment-image-modal-open');
            treatmentImageZoom = 1;
            const captureBaseWidth = function() {
                treatmentImageBaseWidth = modalImage.getBoundingClientRect().width;
                updateTreatmentImageZoom();
            };
            modalImage.onload = captureBaseWidth;
            requestAnimationFrame(captureBaseWidth);
            modal.querySelector('.treatment-image-modal-close')?.focus();
        }

        function changeTreatmentImageZoom(step) {
            treatmentImageZoom = Math.min(4, Math.max(1, treatmentImageZoom + step));
            updateTreatmentImageZoom();
        }

        function updateTreatmentImageZoom() {
            const modal = document.getElementById('treatmentImageModal');
            const modalImage = document.getElementById('treatmentImageModalImage');
            const zoomValue = document.getElementById('treatmentZoomValue');
            if (!modal || !modalImage) return;

            const isZoomed = treatmentImageZoom > 1;
            modalImage.classList.toggle('zoomed', isZoomed);
            modal.classList.toggle('image-zoomed', isZoomed);
            modalImage.style.width = isZoomed && treatmentImageBaseWidth
                ? Math.round(treatmentImageBaseWidth * treatmentImageZoom) + 'px'
                : '';
            if (zoomValue) zoomValue.textContent = Math.round(treatmentImageZoom * 100) + '%';
            const zoomOut = document.getElementById('treatmentZoomOut');
            const zoomIn = document.getElementById('treatmentZoomIn');
            if (zoomOut) zoomOut.disabled = treatmentImageZoom <= 1;
            if (zoomIn) zoomIn.disabled = treatmentImageZoom >= 4;
        }

        function closeTreatmentImageModal() {
            const modal = document.getElementById('treatmentImageModal');
            const modalImage = document.getElementById('treatmentImageModalImage');
            if (!modal) return;

            modal.classList.remove('show');
            modal.classList.remove('image-zoomed');
            document.body.classList.remove('treatment-image-modal-open');
            if (modalImage) {
                modalImage.classList.remove('zoomed');
                modalImage.style.width = '';
                modalImage.src = '';
            }
            treatmentImageZoom = 1;
            treatmentImageBaseWidth = 0;
            treatmentTouchMode = '';
            treatmentImageGestureMoved = false;
        }

        function handleTreatmentImageModalBackdrop(event) {
            if (event.target === event.currentTarget) closeTreatmentImageModal();
        }

        document.getElementById('treatmentImageModalImage')?.addEventListener('click', function(event) {
            event.stopPropagation();
            if (treatmentImageGestureMoved) {
                treatmentImageGestureMoved = false;
                return;
            }
            changeTreatmentImageZoom(treatmentImageZoom >= 4 ? -3 : 0.5);
        });

        function treatmentTouchDistance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;
            return Math.hypot(dx, dy);
        }

        const treatmentImageModal = document.getElementById('treatmentImageModal');
        treatmentImageModal?.addEventListener('touchstart', function(event) {
            if (event.target.closest('.treatment-image-modal-close, .treatment-image-zoom-controls')) return;
            treatmentImageGestureMoved = false;
            if (event.touches.length === 2) {
                treatmentTouchMode = 'pinch';
                treatmentTouchStartDistance = treatmentTouchDistance(event.touches);
                treatmentTouchStartZoom = treatmentImageZoom;
                event.preventDefault();
            } else if (event.touches.length === 1 && treatmentImageZoom > 1) {
                treatmentTouchMode = 'pan';
                treatmentTouchStartX = event.touches[0].clientX;
                treatmentTouchStartY = event.touches[0].clientY;
                treatmentTouchStartScrollLeft = treatmentImageModal.scrollLeft;
                treatmentTouchStartScrollTop = treatmentImageModal.scrollTop;
                event.preventDefault();
            }
        }, { passive: false });

        treatmentImageModal?.addEventListener('touchmove', function(event) {
            if (treatmentTouchMode === 'pinch' && event.touches.length === 2) {
                const distance = treatmentTouchDistance(event.touches);
                if (!treatmentTouchStartDistance) return;
                treatmentImageZoom = Math.min(4, Math.max(1, treatmentTouchStartZoom * distance / treatmentTouchStartDistance));
                treatmentImageGestureMoved = true;
                updateTreatmentImageZoom();
                event.preventDefault();
            } else if (treatmentTouchMode === 'pan' && event.touches.length === 1) {
                const deltaX = event.touches[0].clientX - treatmentTouchStartX;
                const deltaY = event.touches[0].clientY - treatmentTouchStartY;
                if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) treatmentImageGestureMoved = true;
                treatmentImageModal.scrollLeft = treatmentTouchStartScrollLeft - deltaX;
                treatmentImageModal.scrollTop = treatmentTouchStartScrollTop - deltaY;
                event.preventDefault();
            }
        }, { passive: false });

        treatmentImageModal?.addEventListener('touchend', function(event) {
            if (event.touches.length === 1 && treatmentImageZoom > 1) {
                treatmentTouchMode = 'pan';
                treatmentTouchStartX = event.touches[0].clientX;
                treatmentTouchStartY = event.touches[0].clientY;
                treatmentTouchStartScrollLeft = treatmentImageModal.scrollLeft;
                treatmentTouchStartScrollTop = treatmentImageModal.scrollTop;
            } else if (event.touches.length === 0) {
                treatmentTouchMode = '';
                window.setTimeout(function() { treatmentImageGestureMoved = false; }, 250);
            }
        }, { passive: true });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('treatmentImageModal')?.classList.contains('show')) {
                closeTreatmentImageModal();
            }
            if (document.getElementById('treatmentImageModal')?.classList.contains('show')) {
                if (event.key === '+' || event.key === '=') changeTreatmentImageZoom(0.5);
                if (event.key === '-') changeTreatmentImageZoom(-0.5);
            }
        });

        // === 사진 첨부 ===
        let selectedPhotos = []; // {file, dataUrl}
        const MAX_PHOTOS = 3;
        const MAX_SIZE = 5 * 1024 * 1024; // 5MB
        const MAX_DIM = 1280; // 리사이즈 최대 px

        function handlePhotoSelect(input) {
            const files = Array.from(input.files);
            for (const f of files) {
                if (selectedPhotos.length >= MAX_PHOTOS) { alert('최대 ' + MAX_PHOTOS + '장까지 첨부 가능합니다.'); break; }
                if (!f.type.startsWith('image/')) continue;
                if (f.size > MAX_SIZE) { alert(f.name + ': 5MB 이하 파일만 가능합니다.'); continue; }
                resizeAndAdd(f);
            }
            input.value = '';
        }

        function resizeAndAdd(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let w = img.width, h = img.height;
                    if (w > MAX_DIM || h > MAX_DIM) {
                        if (w > h) { h = Math.round(h * MAX_DIM / w); w = MAX_DIM; }
                        else { w = Math.round(w * MAX_DIM / h); h = MAX_DIM; }
                    }
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    canvas.toBlob(function(blob) {
                        const dataUrl = URL.createObjectURL(blob);
                        selectedPhotos.push({ blob: blob, dataUrl: dataUrl, name: file.name });
                        renderPhotoGrid();
                    }, 'image/jpeg', 0.85);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function removePhoto(idx) {
            URL.revokeObjectURL(selectedPhotos[idx].dataUrl);
            selectedPhotos.splice(idx, 1);
            renderPhotoGrid();
        }

        function renderPhotoGrid() {
            const grid = document.getElementById('photoGrid');
            grid.innerHTML = '';
            selectedPhotos.forEach(function(p, i) {
                const slot = document.createElement('div');
                slot.className = 'photo-slot filled';
                slot.innerHTML = '<img src="' + p.dataUrl + '" alt="photo"><button type="button" class="remove-btn" onclick="removePhoto(' + i + ')">×</button>';
                grid.appendChild(slot);
            });
            if (selectedPhotos.length < MAX_PHOTOS) {
                const addSlot = document.createElement('div');
                addSlot.className = 'photo-slot';
                addSlot.innerHTML = '<span class="add-icon">+</span>';
                addSlot.onclick = function() { document.getElementById('photoInput').click(); };
                grid.appendChild(addSlot);
            }
            // 동의 체크박스 표시/숨김
            document.getElementById('consentRow').style.display = selectedPhotos.length > 0 ? 'flex' : 'none';
            if (selectedPhotos.length === 0) document.getElementById('photoConsent').checked = false;
        }

        // 상담 신청 제출
        function submitConsultation(e) {
            e.preventDefault();

            // 사진 있을 때 동의 체크 검증
            if (selectedPhotos.length > 0 && !document.getElementById('photoConsent').checked) {
                window.showDetailToast?.('사진 첨부 시 동의가 필요합니다.');
                document.getElementById('photoConsent').focus();
                return;
            }

            // 05-05: 선택 칩 검증 + 메시지 합성 (서버 필드는 그대로 유지)
            const cmPick = function(g) { const on = document.querySelector('#consultForm .cm-chips[data-group="' + g + '"] .cm-chip.on'); return on ? on.dataset.v : ''; };
            const cmTreat = cmPick('treat'), cmMethod = cmPick('method'), cmTime = cmPick('time');
            if (!cmTreat) { window.showDetailToast?.('관심 시술을 선택해주세요.'); return; }
            if (!cmMethod || !cmTime) { window.showDetailToast?.('상담 방법과 연락 가능한 시간을 선택해주세요.'); return; }
            if (!document.getElementById('cmAgree').checked) { window.showDetailToast?.('개인정보 병원 제공 동의가 필요합니다.'); return; }

            const formData = new FormData(e.target);
            const cmUserMsg = (formData.get('message') || '').toString().trim();
            formData.set('message', '[관심 시술] ' + cmTreat + '\n[상담 방법] ' + cmMethod + '\n[연락 가능 시간] ' + cmTime + (cmUserMsg ? '\n\n' + cmUserMsg : ''));
            // 사진 blob 추가
            selectedPhotos.forEach(function(p, i) {
                formData.append('photos[]', p.blob, 'photo_' + i + '.jpg');
            });
            if (selectedPhotos.length > 0) {
                formData.set('photo_consent', '1');
            }

            const submitBtn = document.querySelector('#consultModal .modal-btn-submit');
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.textContent = '전송중...';

            const resetSubmitButton = function() {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('aria-busy');
                submitBtn.textContent = '신청하기';
            };

            fetch('consultation_submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resetSubmitButton();
                if (data.success) {
                    const csrfInput = e.target.querySelector('input[name="csrf_token"]');
                    if (csrfInput && data.csrf_token) csrfInput.value = data.csrf_token;
                    cmShowDone(cmMethod, cmTime);
                    e.target.reset();
                    document.querySelectorAll('#consultForm .cm-chips[data-group="treat"] .cm-chip').forEach(function(c, i){ c.classList.toggle('on', i === 0); });
                    selectedPhotos = [];
                    renderPhotoGrid();
                } else {
                    window.showDetailToast?.(data.message || '<?php echo term('consultation', '상담'); ?> 신청 중 오류가 발생했습니다.');
                }
            })
            .catch(error => {
                resetSubmitButton();
                console.error('Consultation error:', error);
                window.showDetailToast?.('네트워크 오류가 발생했습니다. 인터넷 연결을 확인해주세요.');
            });
        }

        // 05-05 칩 단일 선택
        document.querySelectorAll('#consultForm .cm-chips').forEach(function(g) {
            g.addEventListener('click', function(ev) {
                const c = ev.target.closest('.cm-chip'); if (!c) return;
                g.querySelectorAll('.cm-chip').forEach(function(x) { x.classList.remove('on'); });
                c.classList.add('on');
            });
        });

        // 모달 외부 클릭시 닫기
        document.getElementById('consultModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConsultModal();
            }
        });

        // 영업시간 확장/축소
        function copyAddress(btn) {
            const addr = btn.getAttribute('data-address') || '';
            if (!addr) return;
            const done = function() {
                const original = btn.innerHTML;
                btn.innerHTML = '복사됨';
                btn.style.color = 'var(--color-primary, #AC1830)';
                setTimeout(function() { btn.innerHTML = original; btn.style.color = ''; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(addr).then(done).catch(function() { window.detailFallbackCopy?.(addr, done); });
            } else {
                window.detailFallbackCopy?.(addr, done);
            }
        }
        function toggleBusinessHours() {
            const expand = document.getElementById('businessHoursExpand');
            const icon = document.getElementById('hoursToggleIcon');

            if (expand.classList.contains('show')) {
                expand.classList.remove('show');
                icon.classList.remove('expanded');
            } else {
                expand.classList.add('show');
                icon.classList.add('expanded');
            }
        }

        // 초기화
        updateGallery();
    </script>
    </div>

<?php
// CPV 추적 (페이지 로드 완료 시 과금 API 호출)
$cpv_page_type = 'hospital';
$cpv_target_id = $hospital_id;
include __DIR__ . '/includes/cpv_tracker.php';
?>

<div style="height:70px;"></div>


<?php
if ($detail_from_wishlist) $bottom_nav_active = 'my';
include_once __DIR__ . '/includes/bottom_nav.php';
?>
<?php renderWishlistScript(); ?>
<?php include __DIR__ . '/includes/report_block_modal.php'; ?>
<?php
// 최근 본 병원 저장
if (!empty($hospital_id) && isset($hospital)) {
    renderRecentlyViewedSave(
        $hospital_id,
        $hospital['name'] ?? '',
        $hospital['main_image_url'] ?? '',
        $hospital['rating'] ?? 0,
        $hospital['review_count'] ?? 0,
        $hospital['address'] ?? ''
    );
}
?>
</body>
</html>
<?php mysqli_close($connect); ?>

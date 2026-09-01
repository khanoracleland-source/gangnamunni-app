<?php



require_once __DIR__ . '/includes/init.php';

$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/wishlist_button.php';
require_once __DIR__ . '/includes/functions.php';


function format_treatment_body_parts($body_part) {
    $raw_parts = preg_split('/\s*\|\|\s*/', trim((string)$body_part));
    $parts = [];

    foreach ($raw_parts as $raw_part) {
        $part = trim($raw_part);
        
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
$detail_source_qs = $detail_from_wishlist ? '&from=my_wishlist' : '';


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



$matched_treatment = null;
$matched_treatment_options = [];
$matched_treatment_images = []; 
$matched_treatment_query = isset($_GET['treatment']) ? trim($_GET['treatment']) : '';
$matched_treatment_id = isset($_GET['treatment_id']) ? intval($_GET['treatment_id']) : 0;


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
                $display_name_parts = array_values(array_filter([$option_name, $body_part, $dosage_text], static function ($part) {
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
    
    if ($matched_treatment) {
        require_once __DIR__ . '/includes/hospital_treatment_images.php';
        $matched_treatment_images = hti_images_for($connect, $hospital_id, $matched_treatment['treatment_id']);
    }
}


if ($hospital_id <= 0) {
    header('Location: index.php');
    exit;
}


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





$images_query = "SELECT * FROM hospital_images WHERE hospital_id = {$hospital_id} AND is_active = 1 AND image_type <> 'logo' AND image_type <> 'main' ORDER BY sort_order ASC, id ASC";
$images_result = mysqli_query($connect, $images_query);
$hospital_images = [];
while ($img_row = mysqli_fetch_assoc($images_result)) {
    
    if ($is_logo_image_url($img_row['image_url'] ?? '')) {
        continue;
    }
    if (!empty($hospital['main_image_url']) && ($img_row['image_url'] ?? '') === $hospital['main_image_url']) {
        continue;
    }
    $hospital_images[] = $img_row;
}


$features_query = "SELECT * FROM hospital_features WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY sort_order ASC";
$features_result = mysqli_query($connect, $features_query);
$hospital_features = [];
while ($feature = mysqli_fetch_assoc($features_result)) {
    $hospital_features[] = $feature;
}



$categories = json_decode($hospital['categories'] ?? '[]', true);
if (!is_array($categories)) {
    $categories = array_filter(array_map('trim', explode(',', $hospital['categories'] ?? '')));
}


$tc_names = [];
$tc_r = mysqli_query($connect, "SELECT name FROM gangnamunni_treatment_categories WHERE is_active = 1");
if ($tc_r) {
    while ($tc_row = mysqli_fetch_assoc($tc_r)) {
        $tc_names[] = $tc_row['name'];
    }
}


$matched_treatment_categories = !empty($categories) ? array_values(array_intersect($categories, $tc_names)) : [];

$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true) ?: [];
$business_hours_raw = json_decode($hospital['business_hours'] ?? '{}', true) ?: [];


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


$business_hours = [];
if (!empty($business_hours_raw['officeHours']) && is_array($business_hours_raw['officeHours'])) {
    $eng_to_kr = ['MON'=>'월','TUE'=>'화','WED'=>'수','THU'=>'목','FRI'=>'금','SAT'=>'토','SUN'=>'일'];
    foreach ($eng_to_kr as $eng => $kr) {
        if (isset($business_hours_raw['officeHours'][$eng])) {
            
            $business_hours[$kr] = str_replace('-', '~', $business_hours_raw['officeHours'][$eng]);
        } else {
            $business_hours[$kr] = '휴무';
        }
    }
    
    $comment = $business_hours_raw['officeHoursComment'] ?? '';
    if (!empty($comment)) {
        $business_hours['영업시간_비고'] = $comment;
        
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
        
        if (preg_match('/점심[시간\s]*없|점심[시간\s]*[Xx×]|점심[시간\s]*따로\s*없/', $comment)) {
            unset($business_hours['lunch_start'], $business_hours['lunch_end']);
            $business_hours['no_lunch_break'] = true;
        }
    }
} elseif (!empty($business_hours_raw)) {
    
    $business_hours = $business_hours_raw;
}


if (empty($business_hours) || !is_array($business_hours)) {
    $business_hours = $default_hours;
}


if (!empty($business_hours['no_lunch_break'])) {
    unset($business_hours['점심'], $business_hours['점심시간'], $business_hours['lunch_start'], $business_hours['lunch_end']);
} else {
    $normalized_lunch = trim((string)($business_hours['점심'] ?? $business_hours['점심시간'] ?? ''));
    if (preg_match('/^(\d{1,2}:\d{2})\s*[~\-]\s*(\d{1,2}:\d{2})$/u', $normalized_lunch, $normalized_lunch_match)) {
        $business_hours['lunch_start'] = $normalized_lunch_match[1];
        $business_hours['lunch_end'] = $normalized_lunch_match[2];
    }
}


$today = date('N'); 
$days = ['월', '화', '수', '목', '금', '토', '일'];
$today_name = $days[$today - 1];
$business_hours_text = ($business_hours[$today_name] ?? $default_hours[$today_name]);


$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';

$minimal_info_tab = in_array($current_tab, ['doctors', 'events', 'reviews'], true);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 20;


$procedure_count = 0;
$procedure_data = [];
if ($current_tab === 'events') { 
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
            $display_name_parts = array_values(array_filter([$option_name, $body_part, $dosage_text], static function ($part) {
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
        $doc_stmt = mysqli_prepare($connect, "SELECT id, name as doctor_name, experience, bio as description, qualification, photo_url, status, schedule FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY {$doctor_order_sql} LIMIT 20");
    } else {
        $doc_stmt = mysqli_prepare($connect, "SELECT id, doctor_name, description, NULL as experience, NULL as qualification, NULL as photo_url, 'active' as status, NULL as schedule FROM crawled_doctors WHERE hospital_id = ? ORDER BY id ASC LIMIT 20");
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
            --color-primary: #AE1932;
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

        
        .page-container {
            max-width: 640px;
            margin: 0 auto;
            background: var(--color-bg-white);
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        
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
            color: var(--color-primary, #AE1932);
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

        
        .image-gallery-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: var(--color-bg-light);
            overflow: hidden;
            margin-top: 0; 
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
            color: var(--color-primary, #AE1932);
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
            border-color: var(--color-primary, #AE1932);
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .matched-option-name {
            margin-bottom: 22px;
            font-size: 16px;
            line-height: 1.4;
            font-weight: 700;
            color: var(--color-text-primary, #111);
            word-break: keep-all;
        }

        .matched-option-card .procedure-price-row {
            justify-content: flex-end;
            margin-top: auto;
            gap: 18px;
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
            .matched-option-name { font-size: 15px; margin-bottom: 18px; }
            .matched-option-card .procedure-price-row { gap: 14px; }
            .matched-option-card .procedure-price { font-size: 22px; }
            .matched-option-card .procedure-price-unit,
            .matched-option-card .procedure-regular-price { font-size: 13px; }
        }

        
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

        
        .review-card-area { padding: 16px 0; border-bottom: 1px solid var(--color-border, #e0e0e0); }
        .review-card-area .tag { display: inline-block; margin: 5px 4px 5px 0; padding: 3px 8px; background: #FFEEEE; color: #AE1932; border-radius: 4px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 500; }
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
        .post-stat-group .heart-icon { color: #AE1932; }

        
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

        
        .content-spacer {
            height: 80px;
        }

        
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

        
        .consultation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
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
    
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
    <div class="page-container">
    
    <div class="header">
        <a href="<?php echo $detail_from_wishlist ? 'my_wishlist.php' : 'hospitals.php'; ?>" id="detailBackBtn" class="back-btn"><i class="bi bi-chevron-left"></i></a>
        <div class="hospital-name-header"><?php echo htmlspecialchars($hospital['name']); ?></div>
        <button type="button" class="global-nav-share" id="pageShareCopyBtn" aria-label="공유하기" title="공유하기">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </button>
    </div>
    <div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite">링크가 복사되었습니다.</div>
    <script>
    (function () {
        
        
        try {
            if (document.referrer.indexOf('hospitals.php') !== -1) {
                sessionStorage.setItem('hospitalListReturnUrl', document.referrer);
            }
        } catch (e) {}
        var backBtn = document.getElementById('detailBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function (e) {
                
                if (new URLSearchParams(window.location.search).get('from') === 'my_wishlist') {
                    e.preventDefault();
                    location.href = 'my_wishlist.php';
                    return;
                }
                
                if (document.referrer.indexOf('hospitals.php') !== -1) {
                    e.preventDefault();
                    history.back();
                    return;
                }
                
                var listUrl = null;
                try { listUrl = sessionStorage.getItem('hospitalListReturnUrl'); } catch (e2) {}
                if (listUrl) {
                    e.preventDefault();
                    location.href = listUrl;
                }
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

    
    
    <?php
    
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

    
    <div class="hospital-info-section">
        <div style="display:flex; align-items:center; gap:12px;">
            <h1 class="hospital-name" style="flex:1; margin-bottom:0;"><?php echo htmlspecialchars($hospital['name']); ?></h1>
            <div style="flex-shrink:0;"><?php renderWishlistButton('hospital', $hospital_id, 'lg', 'star'); ?></div>
        </div>
        <?php
        
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

        
        <hr style="border:none; border-top:1px solid var(--color-border);">

        
        <?php
        
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
                    <strong><?php if ($is_open_now): ?><span style="color:var(--color-primary,#AE1932);font-weight:700;">진료중</span> <?php endif; ?><?php echo htmlspecialchars($business_hours_text); ?> <span style="font-weight:400;color:var(--color-text-muted,#999);font-size:0.9em;">(공휴일/일요일 휴무)</span></strong>
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

        
        <?php if ($matched_treatment && $current_tab === 'home'): ?>
        <div id="matched-treatment" style="scroll-margin-top:160px; margin:16px 0 4px; overflow:visible; background:var(--color-bg-white);">
            
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

            
            <div style="padding:16px 0;">
                <div style="font-size:18px; font-weight:700; color: #000000; margin-bottom:8px;">시술 설명</div>
                <?php if (!empty($matched_treatment['description'])): ?>
                <div style="font-size:13px; color:var(--color-text-secondary); line-height:1.6;"><?php echo nl2br(htmlspecialchars($matched_treatment['description'])); ?></div>
                <?php else: ?>
                <div style="font-size:13px; color:var(--color-text-muted);">등록된 시술 설명이 없습니다.</div>
                <?php endif; ?>
            </div>

            
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
        
        function moreTreatmentImages(btn){
            document.querySelectorAll('.treatment-image-more').forEach(function(el){ el.style.display = ''; });
            if (btn) btn.style.display = 'none';
        }
        </script>
        <?php endif; ?>

        
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

        
        <?php if (!$minimal_info_tab): ?>
        <?php if (!empty($is_app_mode)): ?>
        
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
        
        <?php
        $map_config = [
            'container_id' => 'detailMap',
            'latitude'     => $hospital['latitude'] ?? 0,
            'longitude'    => $hospital['longitude'] ?? 0,
            'address'      => $hospital['address'] ?? '',
            'name'         => $hospital['name'] ?? '',
            'height'       => '220px',
            'show_title'   => false,
            
            'prefer_address' => true,
        ];
        include __DIR__ . '/includes/kakao_map.php';
        ?>
        <?php endif; ?>
        <?php endif; ?>

    </div>


    
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

    
    <div class="tab-content">
        
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
                        <?php
                        
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
                <a href="?id=<?php echo $hospital_id; ?>&tab=doctors<?php echo $detail_source_qs; ?>" style="font-size:13px;color:var(--color-primary,#AE1932);text-decoration:none;"><?= term("staff", "의료진") ?> 전체 보기 →</a>
            </div>
            <?php endif; ?>
            <script>
            
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
                <a class="procedure-card" href="<?php echo htmlspecialchars($procedure_url, ENT_QUOTES, 'UTF-8'); ?>">
                    <h4 class="procedure-name"><?php echo htmlspecialchars($procedure['display_name'] ?? $procedure['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                    <span class="procedure-vat">VAT 포함</span>
                    <?php if ($procedure_price > 0): ?>
                    <div class="procedure-price-row">
                        <?php if ($procedure_discount_rate > 0): ?>
                            <span class="procedure-discount-rate"><?php echo $procedure_discount_rate; ?>%</span>
                            <span class="procedure-regular-price"><?php echo number_format($procedure_regular_price); ?>원</span>
                        <?php endif; ?>
                        <span class="procedure-price-value"><span class="procedure-price"><?php echo number_format($procedure_price); ?></span><span class="procedure-price-unit">원</span></span>
                    </div>
                    <?php else: ?>
                    <div class="procedure-price" style="font-size:18px;">가격 문의</div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            
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
        
        <div class="tab-pane <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>" id="tab-reviews">
            <h3 id="reviewsIntro" style="font-size: 18px; margin-bottom: 16px; scroll-margin-top: 130px;"><?php echo term('review', '시술후기'); ?></h3>
            <?php if (!empty($reviews_data)): ?>
            <div class="review-list">
                <?php foreach ($reviews_data as $review): ?>
                <?php
                
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
                        <?php foreach ($review_imgs as $ri): ?>
                        <div class="thumb-item" onclick="event.stopPropagation(); window.open('<?php echo htmlspecialchars($ri['image_url']); ?>', '_blank')">
                            <img src="<?php echo htmlspecialchars($ri['thumbnail_url'] ?: $ri['image_url']); ?>" alt="<?php echo term('review', '후기'); ?> 이미지" loading="lazy">
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
                        <?php
                        
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

    
    <div class="bottom-bar">
        <button class="btn btn-primary" onclick="openDoctorConsult()" style="flex:1;min-width:0;border:none;border-radius:0;color:#fff;background:var(--color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?php echo term('consultation', '상담'); ?> 신청
        </button>
        <?php
        
        
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

    <?php
    
    $bk_sheet_treatments = [];
    if (!empty($hospital['id'])) {
        $bk_regular_col = false;
        $bk_col_res = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
        if ($bk_col_res && mysqli_num_rows($bk_col_res) > 0) $bk_regular_col = true;
        $bk_repr_col = false;
        $bk_repr_res = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'");
        if ($bk_repr_res && mysqli_num_rows($bk_repr_res) > 0) $bk_repr_col = true;
        $bk_order = $bk_repr_col ? 'p.is_representative DESC, p.price ASC, p.sort_order ASC, p.id ASC' : 'p.price ASC, p.sort_order ASC, p.id ASC';
        $bk_regular_expr = $bk_regular_col ? 'p.regular_price' : 'p.price';
        $bk_sql = "SELECT tm.id AS treatment_id, tm.name, ht.price_min, ht.price_max,
            (SELECT p.id FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id=ht.id AND p.price>0 ORDER BY {$bk_order} LIMIT 1) AS option_id,
            (SELECT p.price FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id=ht.id AND p.price>0 ORDER BY {$bk_order} LIMIT 1) AS sale_price,
            (SELECT {$bk_regular_expr} FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id=ht.id AND p.price>0 ORDER BY {$bk_order} LIMIT 1) AS regular_price
            FROM gangnamunni_hospital_treatments ht
            JOIN gangnamunni_treatment_master tm ON tm.id=ht.treatment_id
            WHERE ht.hospital_id=? AND ht.status='active' AND ht.is_active=1
            ORDER BY ht.is_promoted DESC, tm.name";
        $bk_stmt = mysqli_prepare($connect, $bk_sql);
        if ($bk_stmt) {
            $bk_hid = (int)$hospital['id'];
            mysqli_stmt_bind_param($bk_stmt, 'i', $bk_hid);
            mysqli_stmt_execute($bk_stmt);
            $bk_res = mysqli_stmt_get_result($bk_stmt);
            while ($bk_row = mysqli_fetch_assoc($bk_res)) {
                $bk_sale = max(0, (int)($bk_row['sale_price'] ?? 0));
                $bk_regular = max(0, (int)($bk_row['regular_price'] ?? 0));
                $bk_min = max(0, (int)($bk_row['price_min'] ?? 0));
                if ($bk_sale <= 0) $bk_sale = $bk_min;
                $bk_has_disc = $bk_sale > 0 && $bk_regular > $bk_sale;
                $bk_sheet_treatments[] = [
                    'treatment_id' => (int)$bk_row['treatment_id'],
                    'option_id' => (int)($bk_row['option_id'] ?? 0),
                    'name' => (string)$bk_row['name'],
                    'sale_price' => $bk_sale,
                    'regular_price' => $bk_has_disc ? $bk_regular : 0,
                    'discount_rate' => $bk_has_disc ? (int)round((1 - ($bk_sale / $bk_regular)) * 100) : 0,
                ];
            }
            mysqli_stmt_close($bk_stmt);
        }
    }
    $bk_initial_index = 0;
    if ($matched_treatment) {
        foreach ($bk_sheet_treatments as $bk_i => $bk_t) {
            if ($bk_t['treatment_id'] === (int)$matched_treatment['treatment_id']) { $bk_initial_index = $bk_i; break; }
        }
    }
    ?>
    <?php if (!empty($bk_sheet_treatments)): ?>
    <style>
    
    .bk-sheet-wrap{position:fixed;inset:0;z-index:1200;background:rgba(0,0,0,.4);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
    .bk-sheet-wrap.active{opacity:1;visibility:visible}
    .bk-sheet{position:absolute;inset:0;margin:0 auto;max-width:640px;background:#f3f3f3;display:flex;flex-direction:column;transform:translateY(100%);transition:transform .28s ease;overflow:hidden}
    .bk-sheet-wrap.active .bk-sheet{transform:translateY(0)}
    .bk-head{flex:none;height:64px;padding:0 18px;display:grid;grid-template-columns:44px 1fr 44px;align-items:center;background:#fff;border-bottom:1px solid #f0f0f0}
    .bk-back{border:0;background:none;font-size:30px;line-height:1;text-align:left;cursor:pointer;color:#111}
    .bk-head h1{margin:0;text-align:center;font-size:18px;font-weight:700;color:#111}
    .bk-body{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch}
    .bk-section{padding:32px 20px;background:#fff}
    .bk-section + .bk-section{margin-top:10px}
    .bk-intro{display:flex;align-items:center;justify-content:space-between;padding-bottom:20px;border-bottom:1px solid #e4e4e4}
    .bk-intro h2,.bk-title{margin:0;font-size:22px;font-weight:700;letter-spacing:-.04em;color:#111}
    .bk-option-btn{border:0;background:var(--color-primary,#AE1932);color:#fff;padding:10px 20px;font-size:17px;font-weight:650;cursor:pointer}
    .bk-treatment{padding:22px 0;border-bottom:1px solid #e4e4e4}
    .bk-treatment-top{display:flex;justify-content:space-between;gap:16px}
    .bk-treatment-name{font-size:18px;font-weight:700;color:#111}
    .bk-remove{border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:#111}
    .bk-detail-toggle{margin-top:10px;border:0;background:none;font-size:15px;font-weight:650;cursor:pointer;color:#111}
    .bk-qty-price{margin-top:20px;display:flex;align-items:center;justify-content:space-between}
    .bk-qty{display:flex;border:1px solid #e4e4e4}
    .bk-qty button{width:40px;height:34px;display:grid;place-items:center;border:0;background:#fff;font-size:18px;cursor:pointer;color:#111}
    .bk-qty span{width:40px;height:34px;display:grid;place-items:center;font-size:16px;color:#111}
    .bk-price{font-size:18px;font-weight:650;color:#111}
    .bk-empty{padding:26px 0;color:#999;font-size:15px}
    .bk-months{display:flex;gap:28px;margin-top:20px;padding-bottom:16px;border-bottom:1px solid #bbb}
    .bk-month-btn{border:0;background:none;color:#aaa;font-size:16px;cursor:pointer}
    .bk-month-btn.active{color:#111;font-weight:700}
    .bk-weekdays,.bk-days{display:grid;grid-template-columns:repeat(7,1fr);text-align:center}
    .bk-weekdays{padding:14px 0 8px;color:#aaa;font-size:13px}
    .bk-day{height:38px;border:0;background:none;font-size:16px;cursor:pointer;color:#111}
    .bk-day.selected{background:var(--color-primary,#AE1932);color:#fff}
    .bk-day:disabled{color:#ccc;cursor:default}
    .bk-today-label{height:28px;padding:4px;color:#aaa;font-size:13px;border-bottom:1px solid #bbb}
    .bk-method{margin-top:20px;font-size:16px;font-weight:650;color:#111}
    .bk-method-note{margin-top:8px;color:var(--color-primary,#AE1932);font-size:13px}
    .bk-times{display:grid;grid-template-columns:repeat(4,1fr);gap:22px 38px;margin-top:26px}
    .bk-time{height:38px;border:1px solid #e4e4e4;background:#fff;font-size:16px;cursor:pointer;color:#111}
    .bk-time.selected{border-color:var(--color-primary,#AE1932);color:var(--color-primary,#AE1932)}
    .bk-submit{width:100%;height:54px;margin-top:38px;border:0;background:#eee;color:#aaa;font-size:18px;font-weight:700;cursor:pointer}
    .bk-submit.ready{background:var(--color-primary,#AE1932);color:#fff}
    .bk-option-wrap{position:fixed;inset:0;z-index:1300;display:flex;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.55);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
    .bk-option-wrap.active{opacity:1;visibility:visible}
    .bk-option-sheet{width:min(640px,100%);height:min(72vh,760px);padding:14px 20px 0;background:#fff;display:flex;flex-direction:column;overflow:hidden;transform:translateY(100%);transition:transform .25s ease}
    .bk-option-wrap.active .bk-option-sheet{transform:translateY(0)}
    .bk-handle{width:44px;height:4px;margin:0 auto 12px;border-radius:4px;background:#d1d1d1;flex:none}
    .bk-sheet-title{margin:0;font-size:18px;font-weight:700;flex:none;color:#111}
    .bk-option-list{display:grid;gap:16px;margin:18px -4px 0;padding:0 4px 18px;overflow-y:auto;min-height:0;flex:1;align-content:start}
    .bk-option-card{width:100%;padding:18px 14px;border:2px solid #eee;border-radius:4px;background:#fff;color:#111;text-align:left;cursor:pointer}
    .bk-option-card.selected{border-color:var(--color-primary,#AE1932)}
    .bk-option-card-name{font-size:17px;font-weight:700;line-height:1.45}
    .bk-option-card-detail{margin-top:12px;font-size:14px;line-height:1.55;color:#333}
    .bk-option-card-price{display:flex;align-items:baseline;justify-content:flex-end;gap:12px;margin-top:7px}
    .bk-old-price{color:#aaa;font-size:13px;text-decoration:line-through}
    .bk-discount{font-size:17px}
    .bk-sale-price{color:#d00;font-size:17px;font-weight:800}
    .bk-no-price{color:#999;font-size:14px}
    .bk-complete{width:calc(100% + 40px);height:58px;margin:0 -20px;padding-bottom:env(safe-area-inset-bottom,0);border:0;background:var(--color-primary,#AE1932);color:#fff;font-size:18px;font-weight:700;flex:none;cursor:pointer}
    @media(max-width:420px){.bk-times{gap:18px 12px}.bk-section{padding-left:16px;padding-right:16px}}
    </style>
    <div class="bk-sheet-wrap" id="bkSheetWrap" role="dialog" aria-modal="true" aria-labelledby="bkSheetHeading">
      <div class="bk-sheet">
        <header class="bk-head">
          <button class="bk-back" type="button" onclick="closeBookingSheet()" aria-label="닫기">&#8249;</button>
          <h1 id="bkSheetHeading"><?php echo term('booking', '예약'); ?>하기</h1>
          <span></span>
        </header>
        <div class="bk-body">
          <section class="bk-section">
            <div class="bk-intro">
              <h2>예약을 진행해볼까요?</h2>
              <button class="bk-option-btn" type="button" onclick="bkOpenOptions()">옵션 변경</button>
            </div>
            <div id="bkCart"></div>
          </section>
          <section class="bk-section">
            <h2 class="bk-title">언제 방문하시겠어요?</h2>
            <div class="bk-months" id="bkMonths"></div>
            <div class="bk-weekdays"><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span><span>일</span></div>
            <div class="bk-days" id="bkDays"></div>
            <div class="bk-today-label">오늘</div>
          </section>
          <section class="bk-section">
            <h2 class="bk-title">예약 시간을 선택해주세요</h2>
            <div class="bk-method">예약 확정 방식</div>
            <div class="bk-method-note">병원에서 확인 후 일정이 확정돼요</div>
            <div class="bk-times" id="bkTimes"></div>
            <button class="bk-submit" id="bkSubmit" type="button" onclick="bkSubmit()"><?php echo term('booking', '예약'); ?>하기</button>
          </section>
        </div>
      </div>
      <div class="bk-option-wrap" id="bkOptionWrap" onclick="bkCloseOptions(event)">
        <section class="bk-option-sheet" role="dialog" aria-modal="true" aria-labelledby="bkOptionTitle">
          <div class="bk-handle"></div>
          <h2 class="bk-sheet-title" id="bkOptionTitle">옵션 선택</h2>
          <div class="bk-option-list" id="bkOptionList"></div>
          <button class="bk-complete" type="button" onclick="bkCompleteOption()">선택 완료</button>
        </section>
      </div>
    </div>
    <script>
    (function(){
      var BK_TREATMENTS = <?php echo json_encode($bk_sheet_treatments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      var BK_HOSPITAL_ID = <?php echo (int)$hospital['id']; ?>;
      var BK_FROM = <?php echo json_encode($detail_from_wishlist ? '&from=my_wishlist' : ''); ?>;
      var bkSelectedIndex = <?php echo (int)$bk_initial_index; ?>;
      var bkPendingIndex = bkSelectedIndex;
      var bkCartItems = [{ index: bkSelectedIndex, qty: 1 }];
      var bkDate = '', bkTime = '';
      var bkShownMonth = new Date();

      function fmtWon(n){ return n > 0 ? n.toLocaleString('ko-KR') : ''; }

      window.openBookingSheet = function(e){
        if (e) e.preventDefault();
        document.getElementById('bkSheetWrap').classList.add('active');
        document.body.style.overflow = 'hidden';
        return false;
      };
      window.closeBookingSheet = function(){
        document.getElementById('bkSheetWrap').classList.remove('active');
        document.body.style.overflow = '';
      };

      function renderCart(){
        var box = document.getElementById('bkCart');
        box.innerHTML = '';
        if (!bkCartItems.length) {
          box.innerHTML = '<div class="bk-empty">선택된 시술이 없습니다. 옵션 변경을 눌러 시술을 선택해주세요.</div>';
          updateSubmit();
          return;
        }
        bkCartItems.forEach(function(item, i){
          var t = BK_TREATMENTS[item.index];
          var art = document.createElement('article');
          art.className = 'bk-treatment';
          var price = t.sale_price > 0 ? fmtWon(t.sale_price * item.qty) : '가격 문의';
          art.innerHTML =
            '<div class="bk-treatment-top"><span class="bk-treatment-name"></span>' +
            '<button class="bk-remove" type="button" aria-label="삭제">&times;</button></div>' +
            '<button class="bk-detail-toggle" type="button">자세히 &#x2304;</button>' +
            '<div class="bk-qty-price"><div class="bk-qty">' +
            '<button type="button" class="bk-minus">&minus;</button><span>' + item.qty + '</span><button type="button" class="bk-plus">+</button>' +
            '</div><span class="bk-price">' + price + '</span></div>';
          art.querySelector('.bk-treatment-name').textContent = t.name;
          art.querySelector('.bk-remove').onclick = function(){ bkCartItems.splice(i,1); renderCart(); };
          art.querySelector('.bk-minus').onclick = function(){ if(item.qty>1){item.qty--; renderCart();} };
          art.querySelector('.bk-plus').onclick = function(){ item.qty++; renderCart(); };
          box.appendChild(art);
        });
        updateSubmit();
      }

      window.bkOpenOptions = function(){
        bkPendingIndex = bkCartItems.length ? bkCartItems[0].index : bkSelectedIndex;
        renderOptions();
        document.getElementById('bkOptionWrap').classList.add('active');
      };
      window.bkCloseOptions = function(e){
        if (e && e.target !== document.getElementById('bkOptionWrap')) return;
        document.getElementById('bkOptionWrap').classList.remove('active');
      };
      function renderOptions(){
        var list = document.getElementById('bkOptionList');
        list.innerHTML = '';
        BK_TREATMENTS.forEach(function(t, i){
          var card = document.createElement('button');
          card.type = 'button';
          card.className = 'bk-option-card' + (i === bkPendingIndex ? ' selected' : '');
          var priceHtml;
          if (t.regular_price > 0) {
            priceHtml = '<span class="bk-old-price">' + fmtWon(t.regular_price) + '원</span>' +
                        '<span class="bk-discount">' + t.discount_rate + '%</span>' +
                        '<span class="bk-sale-price">' + fmtWon(t.sale_price) + '원</span>';
          } else if (t.sale_price > 0) {
            priceHtml = '<span class="bk-sale-price" style="color:#111">' + fmtWon(t.sale_price) + '원</span>';
          } else {
            priceHtml = '<span class="bk-no-price">가격 문의</span>';
          }
          card.innerHTML = '<div class="bk-option-card-name"></div>' +
                           '<div class="bk-option-card-detail"></div>' +
                           '<div class="bk-option-card-price">' + priceHtml + '</div>';
          card.querySelector('.bk-option-card-name').textContent = t.name;
          card.querySelector('.bk-option-card-detail').textContent = t.name + ' 1회';
          card.onclick = function(){
            bkPendingIndex = i;
            list.querySelectorAll('.bk-option-card').forEach(function(c, j){ c.classList.toggle('selected', j === i); });
          };
          list.appendChild(card);
        });
      }
      window.bkCompleteOption = function(){
        bkSelectedIndex = bkPendingIndex;
        if (bkCartItems.length) { bkCartItems[0].index = bkSelectedIndex; }
        else { bkCartItems = [{ index: bkSelectedIndex, qty: 1 }]; }
        renderCart();
        document.getElementById('bkOptionWrap').classList.remove('active');
      };

      function iso(d){ return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
      function renderMonths(){
        var box = document.getElementById('bkMonths');
        box.innerHTML = '';
        for (var i = 0; i < 2; i++) {
          (function(){
            var d = new Date(); d.setDate(1); d.setMonth(d.getMonth() + i);
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'bk-month-btn' + (d.getMonth() === bkShownMonth.getMonth() && d.getFullYear() === bkShownMonth.getFullYear() ? ' active' : '');
            b.textContent = (d.getMonth() + 1) + '월';
            b.onclick = function(){ bkShownMonth = d; renderMonths(); renderDays(); };
            box.appendChild(b);
          })();
        }
      }
      function renderDays(){
        var box = document.getElementById('bkDays');
        box.innerHTML = '';
        var y = bkShownMonth.getFullYear(), m = bkShownMonth.getMonth();
        var first = new Date(y, m, 1), offset = (first.getDay() + 6) % 7, last = new Date(y, m + 1, 0).getDate();
        for (var i = 0; i < offset; i++) box.appendChild(document.createElement('span'));
        for (var n = 1; n <= last; n++) {
          (function(){
            var d = new Date(y, m, n), b = document.createElement('button'), v = iso(d);
            b.type = 'button';
            b.className = 'bk-day' + (v === bkDate ? ' selected' : '');
            b.textContent = n;
            b.disabled = d < new Date(new Date().toDateString());
            b.onclick = function(){ bkDate = v; renderDays(); updateSubmit(); };
            box.appendChild(b);
          })();
        }
      }
      function renderTimes(){
        var box = document.getElementById('bkTimes');
        box.innerHTML = '';
        for (var h = 10; h <= 20; h++) {
          [0, 30].forEach(function(min){
            if (h === 20 && min > 30) return;
            var v = String(h).padStart(2,'0') + ':' + String(min).padStart(2,'0');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'bk-time' + (v === bkTime ? ' selected' : '');
            b.textContent = v;
            b.onclick = function(){
              bkTime = v;
              box.querySelectorAll('.bk-time').forEach(function(x){ x.classList.remove('selected'); });
              b.classList.add('selected');
              updateSubmit();
            };
            box.appendChild(b);
          });
        }
      }
      function updateSubmit(){
        document.getElementById('bkSubmit').classList.toggle('ready', !!bkDate && !!bkTime && bkCartItems.length > 0);
      }
      window.bkSubmit = function(){
        if (!bkDate || !bkTime || !bkCartItems.length) return;
        var item = bkCartItems[0], t = BK_TREATMENTS[item.index];
        var url = 'booking.php?id=' + BK_HOSPITAL_ID +
          '&treatment_id=' + t.treatment_id +
          '&treatment=' + encodeURIComponent(t.name) +
          (t.option_id ? '&option_id=' + t.option_id : '') +
          '&quantity=' + item.qty +
          '&date=' + bkDate +
          '&time=' + encodeURIComponent(bkTime) + BK_FROM;
        location.href = url;
      };

      document.addEventListener('keydown', function(e){
        if (e.key !== 'Escape') return;
        var opt = document.getElementById('bkOptionWrap');
        if (opt.classList.contains('active')) { opt.classList.remove('active'); return; }
        closeBookingSheet();
      });
      var bkAnchor = document.querySelector('.bottom-bar a[href^="booking.php"]');
      if (bkAnchor) bkAnchor.addEventListener('click', openBookingSheet);

      renderCart(); renderMonths(); renderDays(); renderTimes(); updateSubmit();
    })();
    </script>
    <?php endif; ?>

    
    <div class="consultation-modal" id="consultModal">
        <div class="modal-content">
            <div class="modal-header"><?= term("consultation") ?> 신청</div>
            <form id="consultForm" onsubmit="submitConsultation(event)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($consultation_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                <input type="hidden" name="type" id="consultType" value="doctor">
                <?php
                $logged_member = get_gangnamunni_member();
                $auto_name = !empty($logged_member['mb_name']) ? htmlspecialchars($logged_member['mb_name']) : '';
                $auto_phone = !empty($logged_member['mb_hp']) ? htmlspecialchars($logged_member['mb_hp']) : '';
                ?>
                <input type="text" name="name" class="modal-input" placeholder="이름" value="<?php echo $auto_name; ?>" required>
                <input type="tel" name="phone" class="modal-input" placeholder="연락처" value="<?php echo $auto_phone; ?>" required>
                <textarea name="message" class="modal-textarea" placeholder="<?php echo term('consultation', '상담'); ?> 내용을 입력해주세요"></textarea>

                
                <div class="photo-upload-section">
                    <span class="photo-upload-label">📷 사진 첨부 (선택, 최대 3장)</span>
                    <input type="file" id="photoInput" accept="image/*" multiple style="display:none;" onchange="handlePhotoSelect(this)">
                    <div class="photo-grid" id="photoGrid">
                        <div class="photo-slot" id="addPhotoSlot" onclick="document.getElementById('photoInput').click()">
                            <span class="add-icon">+</span>
                        </div>
                    </div>
                </div>

                
                <div class="consent-row" id="consentRow" style="display:none;">
                    <input type="checkbox" name="photo_consent" id="photoConsent" value="1">
                    <label for="photoConsent">상담 참고용 사진을 첨부합니다. 제공된 사진은 상담 목적으로만 사용되며, 상담 완료 후 30일 이내에 삭제됩니다.</label>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeConsultModal()">취소</button>
                    <button type="submit" class="modal-btn modal-btn-submit">신청하기</button>
                </div>
            </form>
        </div>
    </div>

    
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
        
        const gallery = document.getElementById('imageGallery');
        const currentIndexEl = document.getElementById('currentIndex');
        const galleryContainer = document.getElementById('galleryContainer');

        let currentIndex = 0;
        let totalImages = <?php echo count($hospital_images); ?>;
        let autoSlideTimer = null;
        let touchStartX = 0, touchEndX = 0, touchStartY = 0, isDragging = false;

        function navigateGallery(direction) {
            currentIndex += direction;
            
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
            
            var dots = document.querySelectorAll('.gallery-dot');
            dots.forEach(function(d, i) { d.classList.toggle('active', i === currentIndex); });
        }

        
        function startAutoSlide() {
            if (totalImages <= 1) return;
            autoSlideTimer = setInterval(function() { navigateGallery(1); }, 3000);
        }
        function resetAutoSlide() {
            clearInterval(autoSlideTimer);
            startAutoSlide();
        }
        startAutoSlide();

        
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
                
                if (Math.abs(diffX) > 40 && diffY < 80) {
                    if (diffX > 0) navigateGallery(1);
                    else navigateGallery(-1);
                }
                resetAutoSlide();
            }, { passive: true });

            
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

        
        function openDoctorConsult() {
            document.getElementById('consultType').value = 'doctor';
            document.getElementById('consultModal').classList.add('show');
        }

        
        function closeConsultModal() {
            document.getElementById('consultModal').classList.remove('show');
        }

        
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

        
        let selectedPhotos = [];
        const MAX_PHOTOS = 3;
        const MAX_SIZE = 5 * 1024 * 1024;
        const MAX_DIM = 1280;

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
            
            document.getElementById('consentRow').style.display = selectedPhotos.length > 0 ? 'flex' : 'none';
            if (selectedPhotos.length === 0) document.getElementById('photoConsent').checked = false;
        }

        
        function submitConsultation(e) {
            e.preventDefault();

            
            if (selectedPhotos.length > 0 && !document.getElementById('photoConsent').checked) {
                alert('사진 첨부 시 동의가 필요합니다.');
                return;
            }

            const formData = new FormData(e.target);
            
            selectedPhotos.forEach(function(p, i) {
                formData.append('photos[]', p.blob, 'photo_' + i + '.jpg');
            });
            if (selectedPhotos.length > 0) {
                formData.set('photo_consent', '1');
            }

            const submitBtn = e.target.querySelector('.modal-btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = '전송중...';

            fetch('consultation_submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = '신청하기';
                if (data.success) {
                    const csrfInput = e.target.querySelector('input[name="csrf_token"]');
                    if (csrfInput && data.csrf_token) csrfInput.value = data.csrf_token;
                    alert('<?php echo term('consultation', '상담'); ?> 신청이 완료되었습니다. 빠른 시일 내에 연락드리겠습니다.');
                    closeConsultModal();
                    e.target.reset();
                    selectedPhotos = [];
                    renderPhotoGrid();
                } else {
                    alert(data.message || '<?php echo term('consultation', '상담'); ?> 신청 중 오류가 발생했습니다. 다시 시도해주세요.');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.textContent = '신청하기';
                console.error('Consultation error:', error);
                alert('네트워크 오류가 발생했습니다. 인터넷 연결을 확인해주세요.');
            });
        }

        
        document.getElementById('consultModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConsultModal();
            }
        });

        
        function copyAddress(btn) {
            const addr = btn.getAttribute('data-address') || '';
            if (!addr) return;
            const done = function() {
                const original = btn.innerHTML;
                btn.innerHTML = '복사됨';
                btn.style.color = 'var(--color-primary, #AE1932)';
                setTimeout(function() { btn.innerHTML = original; btn.style.color = ''; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(addr).then(done).catch(function() { fallbackCopy(addr, done); });
            } else {
                fallbackCopy(addr, done);
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

        
        updateGallery();
    </script>
    </div>

<?php

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

<?php
/**
 * 바비톡 스타일 병원 상세 페이지
 * 모든 데이터는 DB에서 동적으로 로드
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
// 앱 모드 감지 (?app=1 파라미터)
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';
require_once __DIR__ . '/includes/recently_viewed.php';
require_once __DIR__ . '/includes/before_after_gallery.php';
require_once __DIR__ . '/includes/social_share.php';
require_once __DIR__ . '/includes/wishlist_button.php';

$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 시술/가격 데이터 로드
$treatments = [];
$treatment_categories = [];
if (!empty($hospital_id)) {
    $stmt = $connect->prepare("SELECT * FROM gangnamunni_treatments WHERE hospital_id = ? AND is_active = 1 ORDER BY category, sort_order, name");
    if ($stmt) {
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $result_treat = $stmt->get_result();
        while ($row = $result_treat->fetch_assoc()) {
            $treatments[] = $row;
            $cat = $row['category'] ?: '기타';
            if (!in_array($cat, $treatment_categories)) {
                $treatment_categories[] = $cat;
            }
        }
        $stmt->close();
    }
}

// hospitals.php에서 특정 시술(gangnamunni_treatment_master) 검색 후 넘어온 경우
// 해당 병원의 그 시술 정보를 정확히 찾아 홈 탭 상단에 강조 표시
$matched_treatment = null;
$matched_treatment_query = isset($_GET['treatment']) ? trim($_GET['treatment']) : '';
if (!empty($hospital_id) && $matched_treatment_query !== '') {
    $mt_stmt = mysqli_prepare($connect, "
        SELECT tm.name, tm.description
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND tm.is_active = 1 AND tm.name = ?
        LIMIT 1
    ");
    if ($mt_stmt) {
        mysqli_stmt_bind_param($mt_stmt, 'is', $hospital_id, $matched_treatment_query);
        mysqli_stmt_execute($mt_stmt);
        $mt_result = mysqli_stmt_get_result($mt_stmt);
        $matched_treatment = mysqli_fetch_assoc($mt_result) ?: null;
        mysqli_stmt_close($mt_stmt);
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

// 병원 이미지 갤러리 조회
$images_query = "SELECT * FROM hospital_images WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY sort_order ASC, id ASC";
$images_result = mysqli_query($connect, $images_query);
$hospital_images = [];
while ($img_row = mysqli_fetch_assoc($images_result)) {
    $hospital_images[] = $img_row;
}

// #15 fix: main image relative path -> absolute path
if (!empty($hospital['main_image_url'])) {
    $main_img_url = $hospital['main_image_url'];
    if (strpos($main_img_url, 'http') !== 0 && strpos($main_img_url, '//') !== 0) {
        $main_img_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . ltrim($main_img_url, '/');
    }
    $has_main = false;
    foreach ($hospital_images as $img) {
        $img_url = $img['image_url'] ?? '';
        if ($img_url === $hospital['main_image_url'] || $img_url === $main_img_url || ($img['image_type'] ?? '') === 'main') {
            $has_main = true;
            break;
        }
    }
    if (!$has_main) {
        array_unshift($hospital_images, ['image_url' => $main_img_url, 'image_type' => 'main']);
    }
}
// 이미지가 여전히 없으면 프로필 이미지 사용
if (empty($hospital_images) && !empty($hospital['profile_image_url'])) {
    $hospital_images[] = ['image_url' => $hospital['profile_image_url'], 'image_type' => 'profile'];
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

// 오늘 영업시간 표시 문자열 생성
$today = date('N'); // 1(월) ~ 7(일)
$days = ['월', '화', '수', '목', '금', '토', '일'];
$today_name = $days[$today - 1];
$business_hours_text = ($business_hours[$today_name] ?? $default_hours[$today_name]);

// 현재 탭
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
// 의료진/이벤트/후기 탭: 병원 정보를 영업시간까지만 노출 (주소/지도/언어/특징 숨김)
$minimal_info_tab = in_array($current_tab, ['doctors', 'events', 'reviews'], true);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 20;

// 이벤트 카운트 및 데이터 (해당 병원 이벤트만)
$events_count = 0;
$events_data = [];

// gangnamunni_events 테이블에서 해당 병원 이벤트 조회
$evt_cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_events WHERE hospital_id = ? AND is_active = 1");
if ($evt_cnt_stmt) {
    mysqli_stmt_bind_param($evt_cnt_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($evt_cnt_stmt);
    $evt_cnt_result = mysqli_stmt_get_result($evt_cnt_stmt);
    $events_count = mysqli_fetch_assoc($evt_cnt_result)['cnt'] ?? 0;
    mysqli_stmt_close($evt_cnt_stmt);
}

// 이벤트 없으면 crawled_events fallback (hospital_name 매칭)
if ($events_count == 0 && !empty(trim($hospital['name'] ?? ''))) {
    $site_id_query = "SELECT id FROM crawled_sites WHERE site_name = 'www.gangnamunni.com' LIMIT 1";
    $site_id_result = mysqli_query($connect, $site_id_query);
    if ($site_id_row = mysqli_fetch_assoc($site_id_result)) {
        $site_id = $site_id_row['id'];
        $evt_fb_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM crawled_events WHERE site_id = ? AND hospital_name = ?");
        if ($evt_fb_stmt) {
            mysqli_stmt_bind_param($evt_fb_stmt, 'is', $site_id, $hospital['name']);
            mysqli_stmt_execute($evt_fb_stmt);
            $events_count = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($evt_fb_stmt))['cnt'] ?? 0);
            mysqli_stmt_close($evt_fb_stmt);
        }
    }
}

// 이벤트 탭일 때 데이터 조회
if ($current_tab === 'events') {
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';
    $offset_val = ($page - 1) * $items_per_page;

    // gangnamunni_events에서 먼저 조회
    $evt_stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_events WHERE hospital_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT ?, ?");
    if ($evt_stmt) {
        mysqli_stmt_bind_param($evt_stmt, 'iii', $hospital_id, $offset_val, $items_per_page);
        mysqli_stmt_execute($evt_stmt);
        $evt_result = mysqli_stmt_get_result($evt_stmt);
        while ($event = mysqli_fetch_assoc($evt_result)) {
            $events_data[] = $event;
        }
        mysqli_stmt_close($evt_stmt);
    }

    // fallback: crawled_events (hospital_name 기반)
    if (empty($events_data) && isset($site_id) && !empty(trim($hospital['name'] ?? ''))) {
        $sort_sql = "ce.id DESC";
        switch($sort) {
            case 'latest': $sort_sql = "ce.created_at DESC"; break;
            case 'popular': $sort_sql = "ce.review_count DESC, ce.id DESC"; break;
            case 'review': $sort_sql = "ce.review_count DESC"; break;
            case 'low_price': $sort_sql = "ce.discount_price ASC, ce.original_price ASC"; break;
            case 'high_price': $sort_sql = "ce.discount_price DESC, ce.original_price DESC"; break;
            case 'rating': $sort_sql = "ce.rating DESC"; break;
        }
        $events_query = "SELECT ce.* FROM crawled_events ce
                         WHERE ce.site_id = ? AND ce.hospital_name = ?
                         ORDER BY {$sort_sql}
                         LIMIT ?, ?";
        $evt_fb_data_stmt = mysqli_prepare($connect, $events_query);
        if ($evt_fb_data_stmt) {
            mysqli_stmt_bind_param($evt_fb_data_stmt, 'isii', $site_id, $hospital['name'], $offset_val, $items_per_page);
            mysqli_stmt_execute($evt_fb_data_stmt);
            $evt_fb_result = mysqli_stmt_get_result($evt_fb_data_stmt);
            while ($event = mysqli_fetch_assoc($evt_fb_result)) {
                $events_data[] = $event;
            }
            mysqli_stmt_close($evt_fb_data_stmt);
        }
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
        $doc_stmt = mysqli_prepare($connect, "SELECT id, name as doctor_name, specialty, experience, bio as description, qualification, photo_url, status, schedule FROM gangnamunni_doctors WHERE hospital_id = ? AND status = 'active' ORDER BY id ASC LIMIT 20");
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
$rev_cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1");
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
    $reviews_query = "SELECT * FROM hospital_reviews WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY created_at DESC LIMIT 20";
    $reviews_result = mysqli_query($connect, $reviews_query);
    while ($review = mysqli_fetch_assoc($reviews_result)) {
        $reviews_data[] = $review;
    }
}

// #23: YouTube tab removed per client request 2026-03-16

// 이미지 추출 함수
function extractImageFromHtml($file_path) {
    if (!file_exists($file_path)) return null;

    $html = file_get_contents($file_path);

    // og:image 메타 태그에서 이미지 추출
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']/i', $html, $matches)) {
        return $matches[1];
    }

    // 첫 번째 img 태그에서 이미지 추출
    if (preg_match('/<img[^>]+src=["\'](.*?)["\']/i', $html, $matches)) {
        return $matches[1];
    }

    return null;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hospital['name']); ?> | <?= site_name() ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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

        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            background: var(--color-bg-light);
            color: var(--color-text-primary);
            line-height: 1.6;
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

        /* 이벤트 탭 */
        .sort-filter {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .sort-dropdown {
            position: relative;
        }

        .sort-btn {
            padding: 8px 16px;
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sort-options {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 4px;
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 150px;
            display: none;
            z-index: 10;
        }

        .sort-options.show {
            display: block;
        }

        .sort-option {
            display: block;
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            text-align: left;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            cursor: pointer;
            transition: background 0.2s;
        }

        .sort-option:hover {
            background: var(--color-bg-light);
        }

        .sort-option.active {
            color: var(--color-primary);
            font-weight: 600;
        }

        .event-list {
            display: grid;
            gap: 20px;
        }

        .event-card {
            position: relative;
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border, #f0f0f0);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }

        .event-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }

        .event-card-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: stretch;
        }

        .event-thumbnail {
            position: relative;
            width: 130px;
            flex-shrink: 0;
            align-self: stretch;
            min-height: 130px;
            background: var(--color-bg-light);
            overflow: hidden;
        }

        .event-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.55);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            color: white;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--color-primary, #AE1932);
            color: white;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(174,25,50,0.35);
        }

        .event-info {
            flex: 1;
            min-width: 0;
            padding: 14px;
        }

        .event-title {
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 700;
            margin-bottom: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-price-row {
            display: flex;
            align-items: baseline;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 6px;
        }

        .event-price {
            font-size: 14px ;
            font-weight: 800;
            color: var(--color-primary);
        }

        .original-price {
            font-size: 12px;
            color: var(--color-text-muted);
            text-decoration: line-through;
        }

        .event-location {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-muted);
            margin-bottom: 12px;
        }

        .event-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .kakao-chat-btn {
            width: 100%;
            padding: 12px;
            background: var(--color-kakao);
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
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

        .doctor-specialty {
            font-size: 15px ;
            color: var(--color-text-secondary);
            margin-bottom: 8px;
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
    
    /* [1차 검수 #11 - 2026-05-28] 작동하지 않는 플로팅 채팅 버튼 숨김 처리 */
    .chat-float-btn { display: none !important; }
    /* 플로팅 채팅 버튼 - 기존 (참고용으로만 보존) */
    .chat-float-btn-legacy { position:fixed; bottom:80px; right:calc(50% - 300px); z-index:90; width:52px; height:52px; border-radius:50%; background:#AE1932; color:#fff; border:none; box-shadow:0 4px 12px rgba(124,58,237,0.4); cursor:pointer; font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1)); display:flex; align-items:center; justify-content:center; transition:transform 0.2s; }
    .chat-float-btn:hover { transform:scale(1.1); }
    @media(max-width:640px) { .chat-float-btn { right:16px; } }
    /* AI 리뷰 요약 */
    .ai-review-section { padding:20px; margin:16px 0; background:var(--bs-tertiary-bg,#f8f9fa); border-radius:14px; }
    .ai-review-section h3 { font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; margin-bottom:12px; }
    .ai-keywords { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
    .ai-keyword { padding:4px 12px; border-radius:20px; background:var(--bs-body-bg,#fff); border:1px solid var(--bs-border-color,#ddd); font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--bs-body-color); }
    .ai-summary-text { font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); line-height:1.6; color:var(--bs-secondary-color,#666); }
    .ai-stats { display:flex; gap:16px; margin-top:10px; font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); }
    .ai-stats span { display:flex; align-items:center; gap:4px; }
    /* 쿠폰 배너 */
    .coupon-banner { margin:16px 0; padding:14px 20px; background:linear-gradient(135deg,#AE1932,#a855f7); border-radius:12px; color:#fff; display:flex; align-items:center; justify-content:space-between; cursor:pointer; }
    .coupon-banner:hover { opacity:0.95; }
    .coupon-banner .coupon-text { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; }
    .coupon-banner .coupon-sub { font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1)); opacity:0.85; margin-top:2px; }
    .coupon-banner .coupon-icon { font-size:calc(28px * var(--fs-scale,1) * var(--fs-page,1)); }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
    <div class="page-container">
    <!-- 페이지 헤더 -->
    <div class="header">
        <a href="hospitals.php" id="detailBackBtn" class="back-btn"><i class="bi bi-chevron-left"></i></a>
        <div class="hospital-name-header"><?php echo htmlspecialchars($hospital['name']); ?></div>
    </div>
    <script>
    (function () {
        // 병원 목록에서 진입하면 그 전체 URL(정렬/시술/지역 포함)을 저장 —
        // 상세 내 탭 이동으로 referrer가 detail.php로 바뀌어도 목록 복귀 상태를 유지한다.
        try {
            if (document.referrer.indexOf('hospitals.php') !== -1) {
                sessionStorage.setItem('hospitalListReturnUrl', document.referrer);
            }
        } catch (e) {}
        var backBtn = document.getElementById('detailBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function (e) {
                // 목록에서 바로 진입(탭 이동 없음) → history.back()으로 스크롤 위치까지 보존
                if (document.referrer.indexOf('hospitals.php') !== -1) {
                    e.preventDefault();
                    history.back();
                    return;
                }
                // 탭 이동으로 referrer가 detail.php로 바뀐 경우 → 저장해둔 목록 URL로 복귀
                var listUrl = null;
                try { listUrl = sessionStorage.getItem('hospitalListReturnUrl'); } catch (e2) {}
                if (listUrl) {
                    e.preventDefault();
                    location.href = listUrl;
                }
            });
        }
    })();
    </script>

    <!-- 탭 메뉴 -->
    <!-- [1차 검수 #9 - 2026-05-28] 상세 탭 재정의 [홈 / 의료진 / 이벤트 / 시술후기] -->
    <?php
    // 탭 전환 시에도 검색 시술(?treatment=) 컨텍스트 유지 — 목록 복귀 시 정렬/시술 보존
    $tab_qs = $matched_treatment_query !== '' ? '&treatment=' . urlencode($matched_treatment_query) : '';
    ?>
    <div class="tab-menu">
        <a href="?id=<?php echo $hospital_id; ?>&tab=home<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'home' ? 'active' : ''; ?>">
            홈
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=doctors<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>">
            의료진
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=events<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'events' ? 'active' : ''; ?>">
            이벤트
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=reviews<?php echo $tab_qs; ?>" class="tab-item <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>">
            시술후기
        </a>
    </div>

    <!-- 이미지 갤러리 -->
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

    <!-- 병원 정보 -->
    <div class="hospital-info-section">
        <div style="display:flex; align-items:center; gap:12px;">
            <h1 class="hospital-name" style="flex:1; margin-bottom:0;"><?php echo htmlspecialchars($hospital['name']); ?></h1>
            <div style="flex-shrink:0;"><?php renderWishlistButton('hospital', $hospital_id, 'lg', true, 'star'); ?></div>
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
        if (empty($spec_text) && !empty($hospital['description'])) {
            if (preg_match_all('/([가-힣]+과)/u', $hospital['description'], $m)) {
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
                if (!empty($business_hours['lunch_start']) && !empty($business_hours['lunch_end'])
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
                    // 점심시간 표시 (점심 키 또는 lunch_start/lunch_end)
                    $lunch_val = $business_hours['점심'] ?? '';
                    $lunch_start = $business_hours['lunch_start'] ?? $business_hours['점심시작'] ?? '';
                    $lunch_end = $business_hours['lunch_end'] ?? $business_hours['점심종료'] ?? '';
                    // "점심" 키에서 시작~종료 파싱
                    if (!empty($lunch_val) && strpos($lunch_val, '~') !== false) {
                        $lp = explode('~', $lunch_val);
                        $lunch_start = trim($lp[0]);
                        $lunch_end = trim($lp[1] ?? '');
                    }
                    // 하이픈 구분자 파싱 (13:00-14:00)
                    if (empty($lunch_start) && !empty($lunch_val) && strpos($lunch_val, '-') !== false) {
                        $lp = explode('-', $lunch_val);
                        $lunch_start = trim($lp[0]);
                        $lunch_end = trim($lp[1] ?? '');
                    }
                    // 점심시간 없음 표시가 아닌 경우에만 표시
                    $no_lunch = $business_hours['no_lunch_break'] ?? false;
                    if (!$no_lunch && !empty($lunch_start) && !empty($lunch_end)):
                    ?>
                    <div class="hours-day-row" style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--bs-border-color, #eee);">
                        <span class="hours-day-name" style="color:#e67e22; width:auto; white-space:nowrap; margin-right:12px;">🍽 점심시간</span>
                        <span class="hours-day-time" style="color:#e67e22;"><?php echo htmlspecialchars($lunch_start . ' ~ ' . $lunch_end); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    // 영업시간 비고 표시 (크롤링 데이터의 officeHoursComment)
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
        <div id="matched-treatment" style="scroll-margin-top:160px; margin:16px 0 4px; overflow:hidden; background:var(--color-bg-white);">
            <!-- 시술 설명 -->
            <div style="padding:16px;">
                <div style="font-size:18px; font-weight:700; color: #000000; margin-bottom:8px;">시술 설명</div>
                <?php if (!empty($matched_treatment['description'])): ?>
                <div style="font-size:13px; color:var(--color-text-secondary); line-height:1.6;"><?php echo nl2br(htmlspecialchars($matched_treatment['description'])); ?></div>
                <?php else: ?>
                <div style="font-size:13px; color:var(--color-text-muted);">등록된 시술 설명이 없습니다.</div>
                <?php endif; ?>
            </div>

            <!-- 상세 정보 -->
            <div style="padding:16px; border-top:1px solid var(--color-border);">
                <div style="font-size:18px; font-weight:700; color: #000000; margin-bottom:10px;">상세 정보</div>
                <div style="aspect-ratio:4/3; background:var(--color-bg-light); border:1px dashed var(--color-border); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; color:var(--color-text-muted);">
                    <i class="bi bi-image" style="font-size:34px;"></i>
                    <span style="font-size:12px;">이미지 준비 중</span>
                </div>
                <button type="button" onclick="moreTreatmentImages(this)" style="width:100%; margin-top:12px; border:1px solid #000; background:var(--color-bg-white); color:var(--color-text-primary); padding:11px; font-size:14px; font-weight:700; cursor:pointer;">이미지 더보기</button>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function(){ var el = document.getElementById('matched-treatment'); if (el) el.scrollIntoView({behavior:'smooth', block:'start'}); });
        function moreTreatmentImages(btn){ /* TODO: 시술 이미지 소스 연결 후 구현 */ }
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
        ];
        include __DIR__ . '/includes/kakao_map.php';
        ?>
        <?php endif; ?>
        <?php endif; ?>

        <!-- 전화번호 -->
    

        <!-- 소통 가능 언어 (의료진/이벤트 탭에서는 숨김 · DB 컬럼 없어 임시 기본값) -->
        <?php if (!$minimal_info_tab): ?>
        <?php
        $hospital_languages = !empty($hospital['languages']) ? $hospital['languages'] : 'English, 中文, 日本語, Español, Tiếng Việt, Монгол хэл, Bahasa Indonesia';
        $lang_list = array_filter(array_map('trim', explode(',', $hospital_languages)));
        ?>
        <div class="info-row" style="border-top:1px solid var(--color-border); border-bottom:1px solid var(--color-border);">
            <div class="info-content">
                <h3 style="font-size: 18px; margin-bottom: 16px; color: var(--color-text-primary);">소통 가능 언어</h3>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach ($lang_list as $lang): ?>
                    <span style="background:#f2f2f2; color:#000; padding:4px 10px; border-radius:4px; font-size:13px;"><?php echo htmlspecialchars($lang); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>


    <!-- 병원 특징 (의료진/이벤트 탭에서는 숨김) -->
    <?php if (!empty($hospital_features) && !$minimal_info_tab): ?>
    <div class="features-section">
        <?php foreach ($hospital_features as $feature): ?>
        <div class="feature-item">
            <div class="feature-icon"><?php echo $feature['feature_icon']; ?></div>
            <div class="feature-label"><?php echo htmlspecialchars($feature['feature_label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 탭 컨텐츠 -->
    <div class="tab-content">
        <!-- 홈 탭 -->
        <!-- #19 fix: description(required) first, introduction(optional) second -->
        <div class="tab-pane <?php echo $current_tab === 'home' ? 'active' : ''; ?>" id="tab-home">
            <?php
            $has_desc = !empty(trim($hospital['description'] ?? ''));
            $has_intro = !empty(trim($hospital['introduction'] ?? '')) && ($hospital['introduction'] !== ($hospital['description'] ?? ''));
            ?>
            <?php if ($has_desc || $has_intro): ?>
            <h3 style="font-size: 18px; margin-bottom: 16px;"><?= term("business") ?> 소개</h3>
            <div style="line-height: 1.8; color: var(--color-text-secondary);">
                <?php if ($has_desc): ?><?php echo nl2br(htmlspecialchars($hospital['description'])); ?><?php endif; ?>
                <?php if ($has_desc && $has_intro): ?><br><br><?php endif; ?>
                <?php if ($has_intro): ?><?php echo nl2br(htmlspecialchars($hospital['introduction'])); ?><?php endif; ?>
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
                        <p class="doctor-specialty" style="display:flex;align-items:center;gap:6px;">
                            <img src="assets/icons/categories/doctor.jpeg" alt="" style="width:auto;height:16px;object-fit:contain;">
                            <span style="color:var(--color-primary,#AE1932);">피부과 전문의</span>
                        </p>
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
                <a href="?id=<?php echo $hospital_id; ?>&tab=doctors" style="font-size:13px;color:var(--color-primary,#AE1932);text-decoration:none;"><?= term("staff", "의료진") ?> 전체 보기 →</a>
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

        <!-- 이벤트 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'events' ? 'active' : ''; ?>" id="tab-events">
            <h3 id="eventsIntro" style="font-size: 18px; margin-bottom: 16px; scroll-margin-top: 130px;">이벤트</h3>
            <?php if (!empty($events_data)): ?>
            <?php
            // 주소에서 '구'로 끝나는 토큰(자치구) 추출 — 예: "서울 강남구 …" → "강남구"
            $hospital_gu = '';
            foreach (preg_split('/\s+/', trim($hospital['address'] ?? '')) as $_ap) {
                if ($_ap !== '' && mb_substr($_ap, -1) === '구') { $hospital_gu = $_ap; break; }
            }
            ?>
            <!-- 이벤트 리스트 -->
            <div class="event-list">
                <?php foreach ($events_data as $event): ?>
                <?php
                $event_image = null;
                if (!empty($event['image_url'])) {
                    $event_image = $event['image_url'];
                } elseif (!empty($event['file_path'])) {
                    $event_image = extractImageFromHtml($event['file_path']);
                }
                ?>
                <article class="event-card">
                    <span class="event-wishlist" style="position:absolute; top:8px; right:8px; z-index:2; background:rgba(255,255,255,0.9); border-radius:50%; display:inline-flex;"><?php renderWishlistButton('event', (int)$event['id'], 'sm', false, 'star'); ?></span>
                    <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="event-card-link">
                        <div class="event-thumbnail">
                            <?php if ($event_image): ?>
                            <img src="<?php echo htmlspecialchars($event_image); ?>"
                                 alt="<?php echo htmlspecialchars($event['title'] ?? $event['event_name'] ?? ''); ?>"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'200\'%3E%3Crect fill=\'%23e0e0e0\' width=\'400\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'16\'%3E이벤트 이미지%3C/text%3E%3C/svg%3E'">
                            <?php else: ?>
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='200'%3E%3Crect fill='%23e0e0e0' width='400' height='200'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' text-anchor='middle' dominant-baseline='middle' font-size='16'%3E이벤트 이미지%3C/text%3E%3C/svg%3E"
                                 alt="이미지 없음">
                            <?php endif; ?>
                        </div>
                        <div class="event-info">
                            <h3 class="event-title" style="padding-right:28px;"><?php echo htmlspecialchars($event['title'] ?? $event['event_name'] ?? ''); ?></h3>

                            <?php if (!empty($hospital['address'])): ?>
                            <p style="font-size:12px; color:var(--color-text-muted,#999); margin:2px 0 8px; display:flex; align-items:center; gap:4px;">
                                <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php if (!empty($hospital_gu)): ?><strong style="color:var(--color-text-secondary,#666); font-weight:600;"><?php echo htmlspecialchars($hospital_gu); ?></strong> | <?php endif; ?><?php echo htmlspecialchars($hospital['address']); ?></span>
                            </p>
                            <?php endif; ?>

                            <?php if (($hospital['show_rating'] ?? 1) == 1): ?>
                            <div style="display:flex; align-items:center; gap:5px; font-size:12px; margin-bottom:8px;">
                                <img src="assets/icons/categories/rating_2.svg" alt="" style="width:14px; height:14px; object-fit:contain; flex-shrink:0;">
                                <span style="font-weight:700; color:var(--color-text-primary,#333);"><?php echo number_format(!empty($hospital['rating']) ? $hospital['rating'] : 4.8, 1); ?>/5.0</span>
                                <span style="color:var(--color-text-muted,#999); display:inline-flex; align-items:center; gap:4px;"><i class="bi bi-chat-square-text"></i>병원후기 <?php echo number_format($reviews_count); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($event['discount_price'] || $event['original_price']): ?>
                            <div style="text-align:right; font-size:11px; color:var(--color-text-muted,#999); margin-bottom:2px;">VAT 포함</div>
                            <div class="event-price-row">
                                <?php if ($event['original_price'] && $event['discount_price'] < $event['original_price']): ?>
                                <span class="original-price"><?php echo number_format($event['original_price']); ?>원</span>
                                <?php endif; ?>
                                <?php if ($event['discount_price']): ?>
                                <span class="event-price"><?php echo number_format($event['discount_price']); ?>원</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($event['hospital_name'] || $event['brand_name']): ?>
                            <p class="event-location">
                                <?php echo htmlspecialchars($event['hospital_name'] ?? $event['brand_name'] ?? ''); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($event['rating'] || $event['review_count']): ?>
                            <div class="event-rating">
                                <span class="star-icon">★</span>
                                <span><?php echo number_format($event['rating'] ?? 0, 1); ?></span>
                                <span class="review-count">(<?php echo number_format($event['review_count'] ?? 0); ?>)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php if ($hospital['active_chat_consultation']): ?>
                    <button class="kakao-chat-btn" onclick="openKakaoChat(<?php echo $event['id']; ?>)">
                        <span>💬</span> 카톡<?php echo term('consultation', '상담'); ?>
                    </button>
                    <?php endif;?>
                </article>
                <?php endforeach;?>
            </div>

            <!-- 페이지네이션 -->
            <?php if ($events_count > $items_per_page): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($events_count / $items_per_page);
                for ($i = 1; $i <= min($total_pages, 10); $i++):
                ?>
                <a href="?id=<?php echo $hospital_id; ?>&tab=events&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>"
                   class="page-btn <?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">등록된 이벤트가 없습니다.</div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($current_tab === 'events'): ?>
        <script>
        // 이벤트 탭 진입 시 '이벤트' 제목 위치로 스크롤
        document.addEventListener('DOMContentLoaded', function(){
            var el = document.getElementById('eventsIntro');
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
                        <?php echo $review['editor_content']; ?>
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
        <?php if ($current_tab === 'reviews'): ?>
        <script>
        // 시술후기 탭 진입 시 '시술후기' 제목 위치로 스크롤
        document.addEventListener('DOMContentLoaded', function(){
            var el = document.getElementById('reviewsIntro');
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
                        <p class="doctor-specialty" style="display:flex;align-items:center;gap:6px;">
                            <img src="assets/icons/categories/doctor.jpeg" alt="" style="width:auto;height:16px;object-fit:contain;">
                            <span style="color:var(--color-primary,#AE1932);">피부과 전문의</span>
                        </p>
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
        <?php if ($current_tab === 'doctors'): ?>
        <script>
        // 의료진 탭 진입 시 '의료진 소개' 제목 위치로 스크롤
        // sticky 탭메뉴(가장 아래 고정요소)의 실제 하단을 측정해 그만큼 여백 확보
        document.addEventListener('DOMContentLoaded', function(){
            var el = document.getElementById('doctorsIntro');
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
        <a href="booking.php?id=<?php echo $hospital['id']; ?>" class="btn btn-outline" style="flex:1;min-width:0;border:1px solid var(--color-primary);border-radius:0;color:var(--color-primary);background:var(--bs-body-bg, #fff);text-decoration:none;display:flex;align-items:center;justify-content:center;white-space:nowrap;">
            <?php echo term('booking', '예약'); ?>하기
        </a>
    </div>

    <!-- 상담 신청 모달 -->
    <div class="consultation-modal" id="consultModal">
        <div class="modal-content">
            <div class="modal-header"><?= term("consultation") ?> 신청</div>
            <form id="consultForm" onsubmit="submitConsultation(event)">
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

                <!-- 사진 첨부 -->
                <div class="photo-upload-section">
                    <span class="photo-upload-label">📷 사진 첨부 (선택, 최대 3장)</span>
                    <input type="file" id="photoInput" accept="image/*" multiple style="display:none;" onchange="handlePhotoSelect(this)">
                    <div class="photo-grid" id="photoGrid">
                        <div class="photo-slot" id="addPhotoSlot" onclick="document.getElementById('photoInput').click()">
                            <span class="add-icon">+</span>
                        </div>
                    </div>
                </div>

                <!-- 동의 (사진 첨부 시 표시) -->
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

    <script>
        // 이미지 갤러리 (터치 스와이프 + 자동 슬라이드)
        const gallery = document.getElementById('imageGallery');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
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

        // 정렬 드롭다운
        function toggleSortDropdown() {
            const dropdown = document.getElementById('sortOptions');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function changeSort(sort) {
            location.href = '?id=<?php echo $hospital_id; ?>&tab=events&sort=' + sort;
        }

        // 카카오톡 상담
        function openKakaoChat(eventId) {
            document.getElementById('consultType').value = 'kakao';
            document.getElementById('consultModal').classList.add('show');
        }

        // 의사 상담
        function openDoctorConsult() {
            document.getElementById('consultType').value = 'doctor';
            document.getElementById('consultModal').classList.add('show');
        }

        // 상담 모달 닫기
        function closeConsultModal() {
            document.getElementById('consultModal').classList.remove('show');
        }

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
                alert('사진 첨부 시 동의가 필요합니다.');
                return;
            }

            const formData = new FormData(e.target);
            // 사진 blob 추가
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

        // 외부 클릭시 드롭다운 닫기
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sort-dropdown')) {
                const dropdown = document.getElementById('sortOptions');
                if (dropdown) {
                    dropdown.classList.remove('show');
                }
            }
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
                btn.style.color = 'var(--color-primary, #AE1932)';
                setTimeout(function() { btn.innerHTML = original; btn.style.color = ''; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(addr).then(done).catch(function() { fallbackCopy(addr, done); });
            } else {
                fallbackCopy(addr, done);
            }
        }
        function fallbackCopy(text, cb) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); cb(); } catch (e) {}
            document.body.removeChild(ta);
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

<script>
function switchTab(tabName) {
    // 모든 탭 콘텐츠 숨기기
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    // 모든 탭 버튼 비활성
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    // 선택 탭 표시
    var target = document.getElementById('tab-' + tabName);
    if (target) target.style.display = 'block';
    // 선택 버튼 활성
    var btn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
    if (btn) btn.classList.add('active');
}
</script>


<!-- 예약하기 버튼은 bottom-bar에 통합됨 (#24 수정) -->

<?php
// CPV 추적 (페이지 로드 완료 시 과금 API 호출)
$cpv_page_type = 'hospital';
$cpv_target_id = $hospital_id;
include __DIR__ . '/includes/cpv_tracker.php';
?>

<div style="height:70px;"></div>


<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php renderWishlistScript(); ?>
<?php include __DIR__ . '/includes/report_block_modal.php'; ?>
</body>
</html>
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
<?php mysqli_close($connect); ?>



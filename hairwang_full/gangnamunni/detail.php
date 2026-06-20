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
$categories = json_decode($hospital['categories'] ?? '[]', true) ?: [];
$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true) ?: [];
$subway_names = json_decode($hospital['subway_names'] ?? '[]', true) ?: [];
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
$business_hours_text = $today_name . ' ' . ($business_hours[$today_name] ?? $default_hours[$today_name]);

// 현재 탭
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
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
        $hospital_name_esc = mysqli_real_escape_string($connect, $hospital['name']);
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

if ($current_tab === 'doctors') {
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
/*
$youtube_count = 0;
$youtube_data = [];
$youtube_count_query = "SELECT COUNT(*) as cnt FROM hospital_youtube WHERE hospital_id = {$hospital_id} AND is_active = 1";
$youtube_count_result = mysqli_query($connect, $youtube_count_query);
$youtube_count = $youtube_count_result ? mysqli_fetch_assoc($youtube_count_result)['cnt'] : 0;

if ($current_tab === 'youtube') {
    $youtube_query = "SELECT * FROM hospital_youtube WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY sort_order ASC, created_at DESC";
    $youtube_result = mysqli_query($connect, $youtube_query);
    while ($video = mysqli_fetch_assoc($youtube_result)) {
        $youtube_data[] = $video;
    }
}
*/

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
            top: 48px;
            width: 100%;
            max-width: 640px;
            height: 52px;
            background: var(--color-bg-white, #fff);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            padding: 0 16px;
            z-index: 99;
        }
        @media (min-width: 768px) {
            .header { top: 56px; }
        }

        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--color-text-primary);
            text-decoration: none;
            margin-right: 8px;
        }

        .hospital-name-header {
            flex: 1;
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 이미지 갤러리 */
        .image-gallery-container {
            position: relative;
            width: 100%;
            height: 420px;
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
            padding: 20px;
            background: var(--color-bg-white, #fff);
        }

        .hospital-name {
            font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1));
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
        /* #14 fix: tab-menu under global_nav + header */
        .tab-menu {
            position: sticky;
            top: 100px;
            background: var(--color-bg-white, #fff);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            z-index: 90;
        }
        @media (min-width: 768px) {
            .tab-menu { top: 108px; }
        }

        .tab-item {
            flex: 1;
            padding: 16px 12px;
            text-align: center;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            color: var(--color-text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
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
            padding: 20px;
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
            background: var(--color-bg-white, #fff);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .event-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .event-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .event-thumbnail {
            position: relative;
            width: 100%;
            height: 200px;
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
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--color-primary, #AE1932);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
        }

        .event-info {
            padding: 16px;
        }

        .event-title {
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            margin-bottom: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-price-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .event-price {
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: bold;
            color: var(--color-primary);
        }

        .original-price {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
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
            display: grid;
            gap: 24px;
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
            width: 80px;
            height: 80px;
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
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
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

        /* 유튜브 탭 */
        .youtube-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .youtube-card {
            text-decoration: none;
            color: inherit;
        }

        .youtube-thumbnail {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            background: var(--color-bg-light);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .youtube-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
        }

        .play-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            padding-left: 4px;
        }

        .video-duration {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .youtube-title {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
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

        /* 반응형 */
        @media (max-width: 640px) {
            .youtube-grid {
                grid-template-columns: 1fr;
            }
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
    <!-- 글로벌 네비게이션 -->
    <?php include __DIR__ . '/includes/global_nav.php'; ?>

    <!-- 페이지 헤더 -->
    <div class="header">
        <a href="hospitals.php" class="back-btn">←</a>
        <div class="hospital-name-header"><?php echo htmlspecialchars($hospital['name']); ?></div>
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
        <h1 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h1>
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
            $specs = array_unique($specs);
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
                $spec_text = implode(', ', $specs2);
            }
        }
        if (empty($spec_text)) {
            $cats = json_decode($hospital['categories'] ?? '[]', true);
            if (!empty($cats) && is_array($cats)) {
                $spec_text = implode(', ', array_slice($cats, 0, 3));
            }
        }
        if (empty($spec_text) && !empty($hospital['description'])) {
            if (preg_match_all('/([가-힣]+과)/u', $hospital['description'], $m)) {
                $filtered = array_filter(array_unique($m[1]), function($v) { return mb_strlen($v) >= 3 && mb_strlen($v) <= 8; });
                if (!empty($filtered)) $spec_text = implode(', ', array_slice(array_values($filtered), 0, 3));
            }
        }
        if (!empty($spec_text)):
        ?>
        <p class="hospital-specialty"><?php echo htmlspecialchars($spec_text); ?> 전문</p>
        <?php endif; ?>

        <?php if (($hospital['show_rating'] ?? 1) == 1): ?>
        <div class="rating-row">
            <span class="star-icon">★</span>
            <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 4.8, 1); ?></span>
            <span class="review-count">(<?php echo number_format($reviews_count); ?>)</span>
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

        <!-- 영업시간 -->
        <div class="info-row">
            <span class="info-icon">🕐</span>
            <div class="info-content">
                <div style="display: flex; align-items: center;">
                    <strong style="flex: 1;"><?php echo htmlspecialchars($business_hours_text); ?></strong>
                    <button class="hours-toggle-btn" onclick="toggleBusinessHours()">
                        <span class="hours-toggle-icon" id="hoursToggleIcon">▼</span>
                    </button>
                </div>

                <div class="business-hours-expand" id="businessHoursExpand">
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

        <!-- 주소 -->
        <div class="info-row">
            <span class="info-icon">📍</span>
            <div class="info-content">
                <?php echo htmlspecialchars($hospital['address'] ?? '주소 정보 없음'); ?>
            </div>
        </div>

        <!-- 전화번호 -->
        <?php if (!empty($hospital['phone'])): ?>
        <div class="info-row">
            <span class="info-icon">📞</span>
            <div class="info-content">
                <a href="tel:<?php echo htmlspecialchars($hospital['phone']); ?>" style="color:var(--color-primary, #4A90D9); text-decoration:none; font-weight:500;">
                    <?php echo htmlspecialchars($hospital['phone']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 카카오 지도 -->
    <?php if (!empty($is_app_mode)): ?>
    <!-- 앱 모드: 임베디드 지도 대신 주소 + 네비 버튼만 표시 (WebView 내 지도 충돌 방지) -->
    <?php
    $map_lat = floatval($hospital['latitude'] ?? 0);
    $map_lng = floatval($hospital['longitude'] ?? 0);
    $map_addr = $hospital['address'] ?? '';
    $map_nm = $hospital['name'] ?? '';
    $has_loc = ($map_lat != 0 && $map_lng != 0) || !empty($map_addr);
    if ($has_loc):
    ?>
    <div class="km-section" style="margin:16px 0;">
        <div style="font-size:15px;font-weight:700;margin-bottom:10px;">📍 오시는 길</div>
        <?php if (!empty($map_addr)): ?>
        <div style="font-size:13px;color:var(--color-text-secondary,#666);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <span><?php echo htmlspecialchars($map_addr); ?></span>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://map.kakao.com/link/to/<?php echo urlencode($map_nm); ?>,<?php echo $map_lat; ?>,<?php echo $map_lng; ?>" target="_blank">🗺 카카오맵</a>
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://map.naver.com/v5/search/<?php echo urlencode($map_addr ?: $map_nm); ?>" target="_blank">🧭 네이버지도</a>
            <a style="flex:1;min-width:70px;padding:10px 4px;border-radius:8px;border:1px solid #ddd;background:#f8f9fa;font-size:12px;text-align:center;text-decoration:none;color:inherit;" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $map_lat; ?>,<?php echo $map_lng; ?>&travelmode=driving" target="_blank">🌐 구글맵</a>
        </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <!-- 웹 모드: 임베디드 지도 표시 -->
    <?php
    $map_config = [
        'container_id' => 'detailMap',
        'latitude'     => $hospital['latitude'] ?? 0,
        'longitude'    => $hospital['longitude'] ?? 0,
        'address'      => $hospital['address'] ?? '',
        'name'         => $hospital['name'] ?? '',
        'height'       => '220px',
    ];
    include __DIR__ . '/includes/kakao_map.php';
    ?>
    <?php endif; ?>

    <!-- 병원 특징 -->
    <?php if (!empty($hospital_features)): ?>
    <div class="features-section">
        <?php foreach ($hospital_features as $feature): ?>
        <div class="feature-item">
            <div class="feature-icon"><?php echo $feature['feature_icon']; ?></div>
            <div class="feature-label"><?php echo htmlspecialchars($feature['feature_label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 탭 메뉴 -->
    <!-- [1차 검수 #9 - 2026-05-28] 상세 탭 재정의 [홈 / 의료진 / 이벤트 / 시술후기] -->
    <div class="tab-menu">
        <a href="?id=<?php echo $hospital_id; ?>&tab=home" class="tab-item <?php echo $current_tab === 'home' ? 'active' : ''; ?>">
            홈
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=doctors" class="tab-item <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>">
            의료진<span class="tab-count">(<?php echo $doctors_count; ?>)</span>
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=events" class="tab-item <?php echo $current_tab === 'events' ? 'active' : ''; ?>">
            이벤트<span class="tab-count">(<?php echo number_format($events_count); ?>)</span>
        </a>
        <a href="?id=<?php echo $hospital_id; ?>&tab=reviews" class="tab-item <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>">
            시술후기<span class="tab-count">(<?php echo number_format($reviews_count); ?>)</span>
        </a>
    </div>

    <!-- 탭 컨텐츠 -->
    <div class="tab-content">
        <!-- 홈 탭 -->
        <!-- #19 fix: description(required) first, introduction(optional) second -->
        <div class="tab-pane <?php echo $current_tab === 'home' ? 'active' : ''; ?>" id="tab-home">
            <?php
            $has_desc = !empty(trim($hospital['description'] ?? ''));
            $has_intro = !empty(trim($hospital['introduction'] ?? ''));
            ?>
            <?php if ($has_desc): ?>
            <h3 style="font-size: 18px; margin-bottom: 16px;"><?= term("business") ?> 소개</h3>
            <div style="line-height: 1.8; color: var(--color-text-secondary);">
                <?php echo nl2br(htmlspecialchars($hospital['description'])); ?>
            </div>
            <?php endif; ?>
            <?php if ($has_intro && ($hospital['introduction'] !== ($hospital['description'] ?? ''))): ?>
            <h3 style="font-size: 18px; margin: <?php echo $has_desc ? '24px' : '0'; ?> 0 16px;">상세 소개</h3>
            <div style="line-height: 1.8; color: var(--color-text-secondary);">
                <?php echo nl2br(htmlspecialchars($hospital['introduction'])); ?>
            </div>
            <?php endif; ?>
            <?php if (!$has_desc && !$has_intro): ?>
            <div class="empty-state">
                <div class="empty-state-icon">??</div>
                <div class="empty-state-text">등록된 소개 정보가 없습니다.</div>
            </div>
            <?php endif; ?>

            <!-- [1차 검수 #10 - 2026-05-28] 홈 탭에 주요 시술/가격 노출 강화 -->
            <?php if (!empty($treatments)): ?>
            <div style="margin-top:24px;">
                <h3 style="font-size:18px;margin-bottom:12px;">💰 주요 <?= term('service', '시술') ?> 가격</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:10px;">
                <?php foreach (array_slice($treatments, 0, 6) as $_tp): ?>
                    <div style="border:1px solid var(--bs-border-color);border-radius:8px;padding:12px;background:var(--bs-tertiary-bg);">
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px;"><?php echo htmlspecialchars($_tp['name'] ?? ''); ?></div>
                        <?php if (!empty($_tp['category'])): ?>
                        <div style="font-size:11px;color:var(--bs-secondary-color);margin-bottom:6px;"><?php echo htmlspecialchars($_tp['category']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($_tp['price_min']) && (int)$_tp['price_min'] > 0): ?>
                        <div style="font-size:15px;font-weight:700;color:#AE1932;">
                            <?php
                            $_pm = (int)$_tp['price_min'];
                            $_pM = !empty($_tp['price_max']) ? (int)$_tp['price_max'] : 0;
                            if ($_pM > 0 && $_pM !== $_pm) echo number_format($_pm) . '~' . number_format($_pM) . '원';
                            else echo number_format($_pm) . '원~';
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if (count($treatments) > 6): ?>
                <div style="text-align:center;margin-top:12px;">
                    <a href="?id=<?php echo $hospital_id; ?>&tab=events" style="font-size:13px;color:#AE1932;text-decoration:none;">+ <?php echo count($treatments) - 6; ?>개 더보기 →</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 이벤트 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'events' ? 'active' : ''; ?>" id="tab-events">
            <?php if (!empty($events_data)): ?>
            <!-- 정렬 드롭다운 -->
            <div class="sort-filter">
                <div class="sort-dropdown">
                    <button class="sort-btn" onclick="toggleSortDropdown()">
                        <span class="sort-icon">↕</span>
                        <span id="currentSort">
                            <?php
                            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';
                            $sort_labels = [
                                'popular' => '인기순',
                                'review' => term('review', '후기') . ' 많은순',
                                'latest' => '최신순',
                                'low_price' => '저가순',
                                'high_price' => '고가순',
                                'rating' => '별점 높은순'
                            ];
                            echo $sort_labels[$sort] ?? '인기순';
                            ?>
                        </span>
                    </button>
                    <div class="sort-options" id="sortOptions">
                        <?php foreach ($sort_labels as $key => $label): ?>
                        <button class="sort-option <?php echo $sort === $key ? 'active' : ''; ?>"
                                onclick="changeSort('<?php echo $key; ?>')">
                            <?php echo $label; ?>
                            <?php if ($sort === $key): ?><span class="check">✓</span><?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

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
                            <span class="event-badge">이벤트</span>
                            <?php if ($event['discount_price'] && $event['original_price']): ?>
                            <?php $discount_rate = round((1 - $event['discount_price'] / $event['original_price']) * 100); ?>
                            <span class="discount-badge"><?php echo $discount_rate; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="event-info">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title'] ?? $event['event_name'] ?? ''); ?></h3>

                            <?php if ($event['discount_price'] || $event['original_price']): ?>
                            <div class="event-price-row">
                                <?php if ($event['discount_price']): ?>
                                <span class="event-price"><?php echo number_format($event['discount_price']); ?>원</span>
                                <?php endif; ?>
                                <?php if ($event['original_price'] && $event['discount_price'] < $event['original_price']): ?>
                                <span class="original-price"><?php echo number_format($event['original_price']); ?>원</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.65rem;color:var(--bs-secondary-color,#6c757d);margin-top:2px;line-height:1.3;">부가세 포함 · 마취비 없음 · 사후관리비 없음</div>
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
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
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

        <!-- 후기 탭 -->
        <div class="tab-pane <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>" id="tab-reviews">
            <!-- 후기 작성 버튼 -->
            <div style="margin-bottom: 20px;">
                <a href="editor/review_editor.php?hospital_id=<?php echo $hospital_id; ?>"
                   style="display: block; padding: 14px; background: var(--color-primary); color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px;">
                    <?php echo term('review', '후기'); ?> 작성하기
                </a>
            </div>

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
                <article class="review-card">
                    <div class="review-header">
                        <div class="reviewer-avatar">👤</div>
                        <div style="flex:1;">
                            <span class="reviewer-name"><?php echo htmlspecialchars($review['user_name']); ?></span>
                            <?php if (!empty($review['is_verified'])): ?>
                            <span style="font-size:11px; color:#16a34a; margin-left:4px;">인증됨</span>
                            <?php endif; ?>
                        </div>
                        <span class="review-time">
                            <?php
                            $diff = time() - strtotime($review['created_at']);
                            if ($diff < 3600) echo floor($diff / 60) . '분 전';
                            elseif ($diff < 86400) echo floor($diff / 3600) . '시간 전';
                            else { $days = floor($diff / 86400); echo $days . '일 전'; }
                            ?>
                            <button class="rv-report-btn" onclick="openReportModal('review', <?php echo intval($review['id']); ?>, '<?php echo htmlspecialchars($review['mb_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($review['user_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')" title="신고" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px;opacity:0.5;">&#9873;</button>
                        </span>
                    </div>

                    <?php if (!empty($review['treatments'])): ?>
                    <div class="treatment-tags">
                        <?php foreach (explode(',', $review['treatments']) as $treatment): ?>
                        <span class="treatment-tag"><?php echo htmlspecialchars(trim($treatment)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="review-rating">
                        <?php for ($j = 1; $j <= 5; $j++): ?>
                        <span class="star <?php echo $j <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>

                    <?php if (!empty($review['title'])): ?>
                    <div style="font-weight: 600; font-size: 15px; margin-bottom: 8px;">
                        <?php echo htmlspecialchars($review['title']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="review-content">
                        <?php if (!empty($review['editor_content']) && ($review['editor_type'] ?? '') === 'quill'): ?>
                        <?php echo $review['editor_content']; ?>
                        <?php else: ?>
                        <p><?php echo nl2br(htmlspecialchars($review['content'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($review_imgs)): ?>
                    <div style="display: flex; gap: 6px; margin-top: 12px; overflow-x: auto;">
                        <?php foreach ($review_imgs as $ri): ?>
                        <img src="<?php echo htmlspecialchars($ri['thumbnail_url'] ?: $ri['image_url']); ?>"
                             alt="<?php echo term('review', '후기'); ?> 이미지"
                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; flex-shrink: 0; cursor: pointer;"
                             onclick="window.open('<?php echo htmlspecialchars($ri['image_url']); ?>', '_blank')">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- 좋아요/댓글 카운트 -->
                    <div style="display: flex; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-border); font-size: 13px; color: var(--color-text-muted);">
                        <span>좋아요 <?php echo intval($review['like_count'] ?? 0); ?></span>
                        <span>댓글 <?php echo intval($review['comment_count'] ?? 0); ?></span>
                    </div>
                </article>
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
                        <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($doctor['experience'])): ?>
                        <p class="doctor-description" style="font-size:12px;color:var(--color-text-secondary,#888);">경력 <?php echo $doctor['experience']; ?>년<?php echo !empty($doctor['qualification']) ? ' · ' . htmlspecialchars($doctor['qualification']) : ''; ?></p>
                        <?php elseif (!empty($doctor['description'])): ?>
                        <p class="doctor-description"><?php echo htmlspecialchars(mb_substr($doctor['description'], 0, 50)); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($doc_clickable): ?>
                    <div style="display:flex;align-items:center;color:var(--color-text-secondary,#999);font-size:18px;"><i class="bi bi-chevron-right"></i></div>
                    <?php endif; ?>
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

        <!-- YouTube tab removed per client request 2026-03-16 (#23) -->
        <!--
        <div class="tab-pane <?php echo $current_tab === 'youtube' ? 'active' : ''; ?>" id="tab-youtube">
            <?php if (!empty($youtube_data)): ?>
            <div class="youtube-grid">
                <?php foreach ($youtube_data as $video): ?>
                <article class="youtube-card">
                    <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="youtube-link">
                        <div class="youtube-thumbnail">
                            <?php if ($video['thumbnail_url']): ?>
                            <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                            <?php endif; ?>
                            <div class="play-overlay">
                                <span class="play-icon">▶</span>
                            </div>
                            <?php if ($video['duration']): ?>
                            <span class="video-duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="youtube-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📺</div>
                <div class="empty-state-text">등록된 유튜브 영상이 없습니다.</div>
            </div>
            <?php endif; ?>
        </div>
        -->
    </div>

    <div class="content-spacer"></div>

    <!-- #24 fix: bottom buttons no overlap -->
    <div class="bottom-bar">
        <button class="btn btn-outline" onclick="openDoctorConsult()" style="flex:1;min-width:0;border:1px solid var(--color-primary);color:var(--color-primary);background:var(--bs-body-bg, #fff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <i class="bi bi-chat-dots me-1"></i><?php echo term('consultation', '상담'); ?> 신청
        </button>
        <a href="booking.php?id=<?php echo $hospital['id']; ?>" class="btn btn-primary" style="flex:1;min-width:0;text-decoration:none;display:flex;align-items:center;justify-content:center;white-space:nowrap;">
            <i class="bi bi-calendar-plus me-1"></i><?php echo term('booking', '예약'); ?>하기
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

<!-- [1차 검수 #8 - 2026-05-28] 찜하기 UI 가시성 강화 — 흰 배경 + 그림자로 잘 보이게 -->
<div style="position:fixed;top:70px;right:16px;z-index:1000;background:#fff;border-radius:50%;box-shadow:0 4px 12px rgba(0,0,0,0.18);padding:6px;">
<?php renderWishlistButton('hospital', $hospital_id, 'lg'); ?>
</div>
<div style="padding:16px;">
<?php renderShareButtons($hospital["name"] ?? "", $hospital["description"] ?? "", "", $hospital["main_image_url"] ?? ""); renderShareScript(); ?>
</div>
<div style="height:70px;"></div>


<!-- 병원 소식 링크 -->
<?php if (!empty($hospital_id)): ?>
<a href="hospital_news.php?hospital_id=<?php echo $hospital_id; ?>" style="display:block;margin:12px 0;padding:12px 16px;background:var(--bs-tertiary-bg,#f5f5f5);border-radius:10px;text-decoration:none;color:inherit;font-size:14px;">
    <i class="bi bi-megaphone" style="color:#AE1932;"></i> 병원 소식 <i class="bi bi-chevron-right" style="float:right;color:var(--bs-secondary-color);"></i>
</a>
<?php endif; ?>
<!-- 플로팅 채팅 버튼 -->
<?php if (!empty($hospital_id)): ?>
<a href="chat.php?hospital_id=<?php echo $hospital_id; ?>" class="chat-float-btn" title="<?php echo term('consultation', '상담'); ?> 채팅">
    <i class="bi bi-chat-dots-fill"></i>
</a>
<?php endif; ?>

<!-- AI 리뷰 요약 -->
<div class="ai-review-section" id="ai-review-box" style="display:none;">
    <h3><i class="bi bi-robot" style="color:#AE1932;"></i> AI 리뷰 분석</h3>
    <div class="ai-keywords" id="ai-keywords"></div>
    <div class="ai-summary-text" id="ai-summary"></div>
    <div class="ai-stats" id="ai-stats"></div>
</div>

<!-- 쿠폰 배너 -->
<div class="coupon-banner" id="coupon-banner" style="display:none;" onclick="location.href='mypage.php'">
    <div>
        <div class="coupon-text" id="coupon-text">사용 가능한 쿠폰이 있어요!</div>
        <div class="coupon-sub">탭하여 확인하기</div>
    </div>
    <div class="coupon-icon"><i class="bi bi-ticket-perforated"></i></div>
</div>

<script>
// AI 리뷰 로드
(function(){
    var hid = <?php echo intval($hospital_id); ?>;
    if (!hid) return;
    var API = window.location.pathname.replace(/\/[^\/]*$/, '');

    fetch(API + '/api_ai_review.php?hospital_id=' + hid)
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success || res.total_reviews === 0) return;
        document.getElementById('ai-review-box').style.display = '';
        var kwEl = document.getElementById('ai-keywords');
        (res.keywords || []).forEach(function(kw) {
            kwEl.innerHTML += '<span class="ai-keyword">' + kw + '</span>';
        });
        document.getElementById('ai-summary').textContent = res.summary || '';
        document.getElementById('ai-stats').innerHTML =
            '<span style="color:#22c55e;"><i class="bi bi-emoji-smile"></i> 긍정 ' + (res.positive_count||0) + '</span>' +
            '<span style="color:#ef4444;"><i class="bi bi-emoji-frown"></i> 부정 ' + (res.negative_count||0) + '</span>' +
            '<span><i class="bi bi-chat-square-text"></i> 전체 ' + (res.total_reviews||0) + '개</span>';
    }).catch(function(){});

    // 쿠폰 체크
    fetch(API + '/api_coupon.php?action=list')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success && res.coupons && res.coupons.length > 0) {
            document.getElementById('coupon-banner').style.display = '';
            document.getElementById('coupon-text').textContent = res.coupons.length + '개의 쿠폰을 받을 수 있어요!';
        }
    }).catch(function(){});
})();
</script>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php renderWishlistScript(); ?>
<?php include __DIR__ . '/includes/report_block_modal.php'; ?>
</body>
</html>
<?php renderBeforeAfterGallery($hospital_id, $connect); ?>
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

<!-- 시술/가격 탭 -->
<div class="tab-content" id="tab-treatments" style="display:none;">
    <?php if (empty($treatments)): ?>
    <div style="text-align:center;padding:40px 16px;color:#999;">
        <div style="font-size:40px;margin-bottom:12px;">💊</div>
        <p style="font-size:14px;">등록된 <?php echo term('service', '시술'); ?> 정보가 없습니다.</p>
    </div>
    <?php else: ?>
    <div style="padding:16px;">
        <?php
        $current_cat = '';
        foreach ($treatments as $treat):
            $cat = $treat['category'] ?: '기타';
            if ($cat !== $current_cat):
                if ($current_cat !== '') echo '</div>'; // 이전 카테고리 그룹 닫기
                $current_cat = $cat;
        ?>
        <div class="treatment-category-group" style="margin-bottom:20px;">
            <h4 style="font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #AE1932;">
                <?php echo htmlspecialchars($cat); ?>
            </h4>
        <?php endif; ?>

            <div class="treatment-card" style="background:var(--bs-body-bg, #fff);border:1px solid var(--bs-border-color, #eee);border-radius:12px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                        <?php if ($treat['is_popular']): ?>
                        <span style="display:inline-block;background:#ff6b6b;color:#fff;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;">인기</span>
                        <?php endif; ?>
                        <span style="font-size:15px;font-weight:600;color:#1a1a1a;"><?php echo htmlspecialchars($treat['name']); ?></span>
                        <?php if (!empty($treat['unit_label'])): ?>
                        <span style="display:inline-block;background:#e8f4fd;color:#1976d2;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;"><?php echo htmlspecialchars($treat['unit_label']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($treat['description'])): ?>
                    <p style="font-size:12px;color:#888;margin:4px 0 0;line-height:1.4;"><?php echo htmlspecialchars($treat['description']); ?></p>
                    <?php endif; ?>
                    <?php if ($treat['duration_minutes'] > 0): ?>
                    <span style="font-size:11px;color:#aaa;margin-top:4px;display:inline-block;">⏱ <?php echo (int)$treat['duration_minutes']; ?>분</span>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;min-width:90px;">
                    <?php if ($treat['discount_price'] > 0 && $treat['discount_price'] < $treat['price']): ?>
                    <div style="font-size:12px;color:#aaa;text-decoration:line-through;"><?php echo number_format($treat['price']); ?>원</div>
                    <div style="font-size:16px;font-weight:700;color:#e53e3e;"><?php echo number_format($treat['discount_price']); ?>원</div>
                    <?php elseif ($treat['price'] > 0): ?>
                    <div style="font-size:16px;font-weight:700;color:#1a1a1a;"><?php echo number_format($treat['price']); ?>원</div>
                    <?php else: ?>
                    <div style="font-size:14px;color:#888;"><?php echo term('consultation', '상담'); ?> 후 결정</div>
                    <?php endif; ?>
                    <div style="font-size:0.65rem;color:var(--bs-secondary-color,#6c757d);margin-top:3px;line-height:1.3;">부가세 포함 · 마취비 없음</div>
                </div>
            </div>

        <?php endforeach; ?>
        <?php if ($current_cat !== '') echo '</div>'; // 마지막 카테고리 그룹 닫기 ?>
    </div>
    <?php endif; ?>
</div>



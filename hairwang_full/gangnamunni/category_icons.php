<?php
/**
 * 카테고리 아이콘 설정 (v2 - DB 연동)
 * - DB gangnamunni_categories.icon_svg 가 있으면 우선 사용
 * - 없으면 하드코딩 매핑 폴백
 * - 최종 폴백: 이모지
 */

// 하드코딩 폴백 매핑 (DB에 icon_svg가 없을 때 사용)
$category_icons_fallback = [
    // 병원 treatment_tags와 일치하는 12개 카테고리 (display_order순)
    '눈성형' => ['image' => 'assets/icons/categories/02_눈.svg', 'emoji' => '👁️', 'color' => 'bg-purple-100'],
    '코성형' => ['image' => 'assets/icons/categories/03_코.svg', 'emoji' => '👃', 'color' => 'bg-pink-100'],
    '안면윤곽/양악' => ['image' => 'assets/icons/categories/05_안면윤곽양악.svg', 'emoji' => '😊', 'color' => 'bg-indigo-100'],
    '가슴성형' => ['image' => 'assets/icons/categories/06_가슴.svg', 'emoji' => '💝', 'color' => 'bg-rose-100'],
    '지방성형' => ['image' => 'assets/icons/categories/04_지방흡입이식.svg', 'emoji' => '💪', 'color' => 'bg-blue-100'],
    '리프팅' => ['image' => 'assets/icons/categories/10_얼굴.svg', 'emoji' => '✨', 'color' => 'bg-yellow-100'],
    '피부' => ['image' => 'assets/icons/categories/12_피부.svg', 'emoji' => '🌸', 'color' => 'bg-pink-200'],
    '필러' => ['image' => 'assets/icons/categories/08_기타.svg', 'emoji' => '💉', 'color' => 'bg-cyan-100'],
    '보톡스' => ['image' => 'assets/icons/categories/08_피부.svg', 'emoji' => '💉', 'color' => 'bg-teal-100'],
    '제모' => ['image' => 'assets/icons/categories/14_두피탈모제모.svg', 'emoji' => '🪒', 'color' => 'bg-orange-100'],
    '모발이식' => ['image' => 'assets/icons/categories/14_두피탈모제모.svg', 'emoji' => '👨‍🦰', 'color' => 'bg-gray-100'],
    '치아' => ['image' => 'assets/icons/categories/13_치과.svg', 'emoji' => '🦷', 'color' => 'bg-violet-100'],
];

// category_icons 변수: DB 데이터 + 폴백 합침
$category_icons = $category_icons_fallback;

/**
 * DB에서 활성 카테고리를 로드하여 $category_icons에 병합
 * @param mysqli $connect DB 연결
 * @return array 활성 카테고리 목록 (display_order 순)
 */
function loadCategoriesFromDB($connect) {
    global $category_icons, $category_icons_fallback;

    $db_categories = [];
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $db_categories[] = $row;
            // DB icon_svg가 있으면 category_icons에 반영
            $name = $row['name'];
            if (!empty($row['icon_svg'])) {
                $category_icons[$name] = [
                    'image' => $row['icon_svg'],
                    'emoji' => $row['icon'] ?: '🏥',
                    'color' => 'bg-gray-100'
                ];
            } elseif (!isset($category_icons[$name])) {
                // 폴백에도 없으면 이모지만 사용
                $category_icons[$name] = [
                    'emoji' => $row['icon'] ?: '🏥',
                    'color' => 'bg-gray-100'
                ];
            }
        }
    }
    return $db_categories;
}

/**
 * 카테고리 아이콘 HTML 생성
 * SVG 파일 우선, 없으면 이모지 폴백
 */
function renderCategoryIcon($category, $icons) {
    $icon_data = $icons[$category] ?? ['emoji' => '🏥', 'color' => 'bg-gray-100'];

    $html = '<div class="category-icon flex items-center justify-center" style="background:#ffffff;border-radius:50%;width:60px;height:60px;box-shadow:0 2px 4px rgba(0,0,0,0.06);">';

    if (isset($icon_data['image'])) {
        $html .= '<img src="' . htmlspecialchars($icon_data['image']) . '" ';
        $html .= 'alt="' . htmlspecialchars($category) . '" ';
        $html .= 'class="w-42 h-42 object-contain" ';
        $html .= 'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">';
        $html .= '<span class="text-4xl" style="display:none;">' . ($icon_data['emoji'] ?? '🏥') . '</span>';
    } else {
        $html .= '<span class="text-4xl">' . ($icon_data['emoji'] ?? '🏥') . '</span>';
    }

    $html .= '</div>';

    return $html;
}

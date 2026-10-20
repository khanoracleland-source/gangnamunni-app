<?php
/**
 * 피그마 디자인 토큰 헬퍼
 * gangnamunni_settings (setting_group='figma_design')에서 토큰 로드
 * theme_helper.php의 CSS 변수 생성에 사용
 */

$_design_tokens_cache = null;

/**
 * 디자인 토큰 로드
 */
function loadDesignTokens($connect) {
    global $_design_tokens_cache;
    if ($_design_tokens_cache !== null) return $_design_tokens_cache;

    $_design_tokens_cache = [];
    $q = @mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='figma_design'");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $_design_tokens_cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $_design_tokens_cache;
}

/**
 * 디자인 토큰 값 가져오기
 */
function getDesignToken($key, $default = '') {
    global $_design_tokens_cache;
    return $_design_tokens_cache[$key] ?? $default;
}

/**
 * 디자인 토큰 기본값 목록
 */
function getDesignTokenDefaults() {
    return [
        // 색상
        'fd_primary_color'        => ['#AC1830', 'color', '메인 브랜드 색상'],
        'fd_primary_light'        => ['#FFEBEE', 'color', '메인 색상 밝은 버전'],
        'fd_primary_dark'         => ['#8A1428', 'color', '메인 색상 어두운 버전'],
        'fd_inactive_color'       => ['#AAAAAA', 'color', '비활성 아이콘/텍스트'],
        'fd_subtitle_color'       => ['#888888', 'color', '부제목/설명 텍스트'],
        'fd_placeholder_color'    => ['#CACACA', 'color', '입력 플레이스홀더'],
        'fd_price_strike_color'   => ['#A9A9A9', 'color', '취소선 원가 텍스트'],
        'fd_page_bg_color'        => ['#F8F8F8', 'color', '페이지 배경색'],
        'fd_header_bg_color'      => ['#F1F1F1', 'color', '헤더/테이블 배경'],
        'fd_border_light_color'   => ['#D5D5D5', 'color', '테이블/구분선 색상'],
        'fd_dark_color'           => ['#1A0000', 'color', '검색바/버튼 진한색'],
        'fd_login_btn_bg'         => ['#AC1830', 'color', '로그인 버튼 배경'],
        'fd_region_btn_bg'        => ['#1A0000', 'color', '지역선택 버튼 배경'],
        'fd_bottom_nav_active'    => ['#AC1830', 'color', '하단 네비 활성 색상'],
        'fd_hospital_price_color' => ['#AC1830', 'color', '병원 가격 강조 색상'],
        'fd_community_tab_active' => ['#AC1830', 'color', '커뮤니티 활성 탭 배경'],
        'fd_community_tab_inactive' => ['#F1F1F1', 'color', '커뮤니티 비활성 탭 배경'],

        // 텍스트
        'fd_login_btn_text'       => ['로그인 / 회원가입', 'text', '로그인 버튼 텍스트'],
        'fd_region_btn_text'      => ['지역 선택', 'text', '지역선택 버튼 텍스트'],
        'fd_search_placeholder'   => ['시술, 병원 이름을 검색해보세요', 'text', '검색창 플레이스홀더'],
        'fd_section_tips_title'   => ['서울미녀 꿀팁', 'text', '꿀팁 섹션 제목'],
        'fd_section_hospitals_title' => ['추천 병원', 'text', '추천 병원 섹션 제목'],
        'fd_section_events_title' => ['진행중인 이벤트', 'text', '이벤트 섹션 제목'],
        'fd_sort_chips'           => ['추천순,거리순,리뷰순,가격순', 'text', '정렬 칩 목록 (쉼표 구분)'],

        // 표시/숨김
        'fd_treatment_table_visible'  => ['1', 'toggle', '시술 테이블 표시'],
        'fd_old_grid_visible'         => ['0', 'toggle', '구버전 그리드 표시'],
        'fd_quick_menu_visible'       => ['1', 'toggle', '퀵메뉴 표시'],
        'fd_category_filter_visible'  => ['1', 'toggle', '카테고리 필터 표시'],
        'fd_ad_banner_sticky'         => ['0', 'toggle', 'AD 배너 고정'],

        // 하단 탭
        'fd_bottom_nav_tab1_label' => ['홈', 'text', '하단탭1 라벨'],
        'fd_bottom_nav_tab1_url'   => ['index.php', 'text', '하단탭1 URL'],
        'fd_bottom_nav_tab1_icon'  => ['nav_home.png', 'text', '하단탭1 아이콘'],
        'fd_bottom_nav_tab2_label' => ['병원', 'text', '하단탭2 라벨'],
        'fd_bottom_nav_tab2_url'   => ['hospitals.php', 'text', '하단탭2 URL'],
        'fd_bottom_nav_tab2_icon'  => ['nav_hospital.png', 'text', '하단탭2 아이콘'],
        'fd_bottom_nav_tab3_label' => ['커뮤니티', 'text', '하단탭3 라벨'],
        'fd_bottom_nav_tab3_url'   => ['community.php', 'text', '하단탭3 URL'],
        'fd_bottom_nav_tab3_icon'  => ['nav_community.png', 'text', '하단탭3 아이콘'],
        'fd_bottom_nav_tab4_label' => ['마이페이지', 'text', '하단탭4 라벨'],
        'fd_bottom_nav_tab4_url'   => ['mypage.php', 'text', '하단탭4 URL'],
        'fd_bottom_nav_tab4_icon'  => ['nav_mypage.png', 'text', '하단탭4 아이콘'],

        // 레이아웃
        'fd_search_border_radius'  => ['0', 'number', '검색창 border-radius (px)'],
        'fd_card_border_radius'    => ['0', 'number', '카드 border-radius (px)'],
        'fd_chip_border_radius'    => ['5', 'number', '정렬 칩 border-radius (px)'],
        'fd_bottom_nav_height'     => ['77', 'number', '하단 네비 높이 (px)'],

        // 시술 카테고리 JSON
        'fd_treatment_categories'  => ['[{"name":"눈","icon":"👁"},{"name":"코","icon":"👃"},{"name":"입술","icon":"👄"},{"name":"피부","icon":"✨"},{"name":"체형","icon":"💪"},{"name":"치아","icon":"🦷"}]', 'json', '시술 카테고리 (JSON)'],
    ];
}

/**
 * DB에 기본 토큰이 없으면 삽입
 */
function ensureDesignTokensExist($connect) {
    $defaults = getDesignTokenDefaults();
    $existing = [];

    $q = @mysqli_query($connect, "SELECT setting_key FROM gangnamunni_settings WHERE setting_group='figma_design'");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $existing[] = $row['setting_key'];
        }
    }

    foreach ($defaults as $key => [$value, $type, $desc]) {
        if (!in_array($key, $existing)) {
            $ek = mysqli_real_escape_string($connect, $key);
            $ev = mysqli_real_escape_string($connect, $value);
            $et = mysqli_real_escape_string($connect, $type);
            $ed = mysqli_real_escape_string($connect, $desc);
            mysqli_query($connect, "INSERT INTO gangnamunni_settings (setting_key, setting_value, setting_type, description, setting_group) VALUES ('{$ek}', '{$ev}', '{$et}', '{$ed}', 'figma_design')");
        }
    }
}

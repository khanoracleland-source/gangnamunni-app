<?php
/**
 * ========================================
 * 테마 설정 헬퍼 함수
 * ========================================
 */

// 테마 설정 전역 변수
$THEME_SETTINGS = [];
require_once __DIR__ . '/seo.php';

// 디자인 토큰 헬퍼 로드
if (file_exists(__DIR__ . '/includes/design_tokens.php')) {
    require_once __DIR__ . '/includes/design_tokens.php';
} else {
    // fallback: 함수가 없으면 기본값 반환
    if (!function_exists('getDesignToken')) {
        function getDesignToken($key, $default = '') { return $default; }
    }
    if (!function_exists('loadDesignTokens')) {
        function loadDesignTokens($c) { return []; }
    }
}

/**
 * 테마 설정 로드
 */
function loadThemeSettings($connect) {
    global $THEME_SETTINGS;

    $query = "SELECT setting_key, setting_value FROM gangnamunni_theme_settings";
    $result = mysqli_query($connect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $THEME_SETTINGS[$row['setting_key']] = $row['setting_value'];
        }
    }

    // 기본값 설정 — 업종 프리셋 연동
    $_th_site_name = (function_exists('site_name') && class_exists('IndustryPreset')) ? IndustryPreset::getInstance()->getDisplayName() : '서울미녀';
    $_th_primary = (function_exists('industry_config') ? industry_config('default_theme.primary_color', '#AE1932') : '#AE1932');
    $_th_accent = (function_exists('industry_config') ? industry_config('default_theme.accent_color', '#8A1428') : '#8A1428');
    $defaults = [
        'site_name' => $_th_site_name,
        'site_logo_path' => 'assets/images/logo_128.png',
        'primary_color' => $_th_primary,
        'secondary_color' => $_th_accent,
        'text_color' => '#000000',
        'text_secondary_color' => '#666666',
        'background_color' => '#FFFFFF',
        'border_color' => '#F5F5F5',
        'button_text_color' => '#FFFFFF',
        'link_color' => '#AE1932',
        'link_hover_color' => '#8A1428',
        'font_korean' => 'Noto Sans KR',
        'font_english' => 'Roboto',
        'font_size_base' => '14px',
        'font_size_large' => '18px',
        'font_size_small' => '12px',
        'font_weight_normal' => '400',
        'font_weight_bold' => '700',
        'section_tips_title' => $_th_site_name . ' ' . (function_exists('term') ? term('tips', '꿀팁') : '꿀팁'),
        'section_hospitals_title' => '추천 ' . (function_exists('term') ? term('business', '병원') : '병원'),
        'section_categories_title' => '관심있는 ' . (function_exists('term') ? term('service', '시술') : '시술') . '을 선택해보세요.',
        'button_booking' => '예약하기',
        'button_consultation' => '상담하기',
        'button_more' => '전체보기',
        'border_radius' => '12px',
        'card_shadow' => '0 2px 8px rgba(0,0,0,0.08)',
        'spacing_small' => '8px',
        'spacing_medium' => '16px',
        'spacing_large' => '24px',
        'login_button_bg' => '#AE1932',
        'login_button_text' => '#FFFFFF'
    ];

    foreach ($defaults as $key => $value) {
        if (!isset($THEME_SETTINGS[$key])) {
            $THEME_SETTINGS[$key] = $value;
        }
    }

    // 디자인 토큰도 함께 로드
    if (function_exists('loadDesignTokens')) {
        loadDesignTokens($connect);
    }

    return $THEME_SETTINGS;
}

/**
 * 테마 설정 값 가져오기
 */
function getTheme($key, $default = '') {
    global $THEME_SETTINGS;
    return $THEME_SETTINGS[$key] ?? $default;
}

/**
 * CSS 변수 생성
 */
function generateThemeCSS() {
    global $THEME_SETTINGS;

    $css = "<style>\n";
    $css .= ":root {\n";
    $css .= "    --primary-color: " . getTheme('primary_color', '#AE1932') . ";\n";
    $css .= "    --secondary-color: " . getTheme('secondary_color', '#8A1428') . ";\n";
    $css .= "    --text-color: " . getTheme('text_color', '#000000') . ";\n";
    $css .= "    --text-secondary-color: " . getTheme('text_secondary_color', '#666666') . ";\n";
    $css .= "    --background-color: " . getTheme('background_color', '#FFFFFF') . ";\n";
    $css .= "    --border-color: " . getTheme('border_color', '#F5F5F5') . ";\n";
    $css .= "    --button-text-color: " . getTheme('button_text_color', '#FFFFFF') . ";\n";
    $css .= "    --link-color: " . getTheme('link_color', '#AE1932') . ";\n";
    $css .= "    --link-hover-color: " . getTheme('link_hover_color', '#8A1428') . ";\n";
    $css .= "    --border-radius: " . getTheme('border_radius', '12px') . ";\n";
    $css .= "    --card-shadow: " . getTheme('card_shadow', '0 2px 8px rgba(0,0,0,0.08)') . ";\n";
    $css .= "    --spacing-small: " . getTheme('spacing_small', '8px') . ";\n";
    $css .= "    --spacing-medium: " . getTheme('spacing_medium', '16px') . ";\n";
    $css .= "    --spacing-large: " . getTheme('spacing_large', '24px') . ";\n";
    $css .= "    --font-size-base: " . getTheme('font_size_base', '14px') . ";\n";
    $css .= "    --font-size-large: " . getTheme('font_size_large', '18px') . ";\n";
    $css .= "    --font-size-small: " . getTheme('font_size_small', '12px') . ";\n";
    $css .= "    --font-weight-normal: " . getTheme('font_weight_normal', '400') . ";\n";
    $css .= "    --font-weight-bold: " . getTheme('font_weight_bold', '700') . ";\n";
    $css .= "    --login-button-bg: " . getTheme('login_button_bg', '#AE1932') . ";\n";
    $css .= "    --login-button-text: " . getTheme('login_button_text', '#FFFFFF') . ";\n";
    // 피그마 디자인 토큰 (DB에서 관리, admin_design_tokens.php)
    $css .= "    --color-inactive: " . getDesignToken('fd_inactive_color', '#AAAAAA') . ";\n";
    $css .= "    --color-subtitle: " . getDesignToken('fd_subtitle_color', '#888888') . ";\n";
    $css .= "    --color-price-strike: " . getDesignToken('fd_price_strike_color', '#A9A9A9') . ";\n";
    $css .= "    --color-placeholder: " . getDesignToken('fd_placeholder_color', '#CACACA') . ";\n";
    $css .= "    --color-page-bg: " . getDesignToken('fd_page_bg_color', '#F8F8F8') . ";\n";
    $css .= "    --color-header-bg: " . getDesignToken('fd_header_bg_color', '#F1F1F1') . ";\n";
    $css .= "    --color-border-light: " . getDesignToken('fd_border_light_color', '#D5D5D5') . ";\n";
    $css .= "    --color-dark: " . getDesignToken('fd_dark_color', '#1A0000') . ";\n";
    $css .= "    --color-community-tab-active: " . getDesignToken('fd_community_tab_active', '#AE1932') . ";\n";
    $css .= "    --color-community-tab-inactive: " . getDesignToken('fd_community_tab_inactive', '#F1F1F1') . ";\n";
    // ===== [폰트 스케일 시스템 2026-06-16] 기기/페이지 보정 + 시맨틱 토큰 =====
    $css .= "    --fs-os: " . getTheme('fs_os_scale', '1') . ";
";
    $css .= "    --fs-page: " . getTheme('fs_page_scale', '1') . ";
";
    $css .= "    --fs-xs: calc(11px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-sm: calc(12px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-md: calc(14px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-lg: calc(16px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-xl: calc(18px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-2xl: calc(20px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "    --fs-3xl: calc(24px * var(--fs-scale,1) * var(--fs-os,1) * var(--fs-page,1));
";
    $css .= "}\n\n";
    // ===== [폰트 스케일] --fs-scale: PC=1 고정, 모바일만 화면폭 비례 축소 (clamp=깜빡임0) =====
    $fs_min   = getTheme('fs_mobile_min', '0.82');
    $fs_slope = getTheme('fs_mobile_slope', '0.092');
    $fs_base  = getTheme('fs_mobile_base', '0.62');
    $css .= ":root { --fs-scale: 1; }
";
    $css .= "@media (max-width:767px){ :root{ --fs-scale: clamp({$fs_min}, calc({$fs_base} + {$fs_slope}vw), 1.0); } }

";

    // 폰트 적용
    $font_korean = getTheme('font_korean', 'Noto Sans KR');
    $font_english = getTheme('font_english', 'Roboto');

    $css .= "body {\n";
    $css .= "    font-family: '{$font_korean}', '{$font_english}', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;\n";
    $css .= "    background: var(--background-color);\n";
    $css .= "    color: var(--text-color);\n";
    $css .= "    font-size: var(--font-size-base);\n";
    $css .= "}\n\n";

    // 공통 클래스 스타일
    $css .= ".logo { color: var(--primary-color); }\n";
    $css .= ".btn-primary { background: var(--primary-color); color: var(--button-text-color); }\n";
    $css .= ".btn-primary:hover { background: var(--secondary-color); }\n";
    $css .= ".filter-btn.active { background: var(--primary-color); color: var(--button-text-color); border-color: var(--primary-color); }\n";
    $css .= ".rating-score { color: var(--primary-color); }\n";
    $css .= ".badge { background: #FFF3E0; color: var(--primary-color); }\n";
    $css .= ".nav-item.active { color: var(--primary-color); }\n";
    $css .= ".hospital-card, .tip-card, .booking-form, .consultation-form { border-radius: var(--border-radius); box-shadow: var(--card-shadow); }\n";
    $css .= "a { color: var(--link-color); }\n";
    $css .= "a:hover { color: var(--link-hover-color); }\n";
    $css .= ".search-input:focus { border-color: var(--primary-color); }\n";
    $css .= ".form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary-color); }\n";
    $css .= ".btn-submit { background: var(--primary-color); }\n";
    $css .= ".btn-submit:hover { background: var(--secondary-color); }\n";
    $css .= ".type-badge { background: var(--primary-color); }\n";
    $css .= ".meta-badge { background: rgba(0,0,0,0.6); }\n";

    $css .= "</style>\n";

    $GLOBALS['__fs_scale_emitted'] = true; // 폰트스케일 중복출력 가드
    return $css;
}

/**
 * Google Fonts 링크 생성
 */
function generateGoogleFontsLink() {
    $font_korean = urlencode(getTheme('font_korean', 'Noto Sans KR'));
    $font_english = urlencode(getTheme('font_english', 'Roboto'));

    $fonts = [];
    if ($font_korean) $fonts[] = $font_korean . ':wght@400;700';
    if ($font_english) $fonts[] = $font_english . ':wght@400;700';

    if (empty($fonts)) {
        return '';
    }

    $fonts_param = implode('&family=', $fonts);

    $html = "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    $html .= "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    $html .= "<link href=\"https://fonts.googleapis.com/css2?family={$fonts_param}&display=swap\" rel=\"stylesheet\">\n";

    return $html;
}

/**
 * 파비콘 링크 생성
 */
function generateFaviconLinks() {
    $base = htmlspecialchars(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/gangnamunni/', ENT_QUOTES, 'UTF-8');
    return '<link rel="shortcut icon" type="image/x-icon" sizes="16x16" href="' . $base . 'favicon.ico">' . "\n"
        . '<link rel="icon" type="image/png" sizes="1024x1024" href="' . $base . 'assets/images/favicon.png">' . "\n"
        . '<link rel="apple-touch-icon" sizes="1024x1024" href="' . $base . 'assets/images/favicon.png">' . "\n";
}

/**
 * [폰트 스케일] --fs-scale + 시맨틱 토큰만 출력하는 경량 CSS
 *   header.php(generateThemeCSS) 를 안 쓰는 페이지가 <head> 에서 호출.
 *   중복 출력 가드: 한 페이지에서 1회만.
 */
function generateFontScaleCSS() {
    if (!empty($GLOBALS['__fs_scale_emitted'])) return '';
    $GLOBALS['__fs_scale_emitted'] = true;
    $fs_min   = getTheme('fs_mobile_min', '0.82');
    $fs_slope = getTheme('fs_mobile_slope', '0.092');
    $fs_base  = getTheme('fs_mobile_base', '0.62');
    $os       = getTheme('fs_os_scale', '1');
    $page     = getTheme('fs_page_scale', '1');
    $css  = "<style>
";
    $css .= ":root{ --fs-scale:1; --fs-os:{$os}; --fs-page:{$page};";
    $css .= " --fs-xs:calc(11px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-sm:calc(12px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-md:calc(14px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-lg:calc(16px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-xl:calc(18px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-2xl:calc(20px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1));";
    $css .= " --fs-3xl:calc(24px*var(--fs-scale,1)*var(--fs-os,1)*var(--fs-page,1)); }
";
    $css .= "@media (max-width:767px){ :root{ --fs-scale:clamp({$fs_min}, calc({$fs_base} + {$fs_slope}vw), 1.0); } }
";
    $css .= "</style>
";
    return $css;
}

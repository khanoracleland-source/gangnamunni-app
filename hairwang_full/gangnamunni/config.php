<?php
/**
 * ========================================
 * 서울미녀 페이지 환경 설정
 * ========================================
 */

// 데모 데이터 사용 여부
// true: 임시 그라데이션 배경 사용
// false: 실제 데이터베이스에서 이미지 URL 가져오기
define('USE_DEMO_DATA', false);

// 한 페이지당 표시할 병원 수
define('HOSPITALS_PER_PAGE', 20);

// 한 페이지당 표시할 꿀팁 수
define('TIPS_PER_PAGE', 4);

// 카테고리 목록 — 업종 프리셋에서 자동 로드, 없으면 기본값 사용
if (class_exists('IndustryPreset')) {
    $preset_cats = IndustryPreset::getInstance()->getDefaultCategories();
    if (!empty($preset_cats)) {
        define('CATEGORIES', array_column($preset_cats, 'name'));
    }
}
if (!defined('CATEGORIES')) {
    define('CATEGORIES', ['코','눈','지방흡입','가슴성형','치아','리프팅','주름/미백','쁘띠','모발이식','제모','남성','눈성형']);
}

// 사이트 도메인/경로 (멀티 산업 배포 시 변경)
if (!defined('SITE_DOMAIN')) define('SITE_DOMAIN', 'hms.seoulbeauty.app');
$request_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$is_local_request = in_array($request_host, ['127.0.0.1', 'localhost', '::1'], true);
$is_public_site_request = in_array($request_host, ['seoulbeauty.app', 'www.seoulbeauty.app', 'hms.seoulbeauty.app'], true);
if (!defined('SITE_BASE_PATH')) {
    // 공개 URL 세그먼트는 'app' (실제 디스크상의 폴더명은 여전히 'gangnamunni')
    define('SITE_BASE_PATH', ($is_local_request || $is_public_site_request) ? '/' : '/hairwang/app/');
}
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://' . ($is_public_site_request ? 'seoulbeauty.app' : SITE_DOMAIN) . SITE_BASE_PATH);

// 이미지 업로드 설정
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_MAX_IMAGES', 10);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', __DIR__ . '/uploads/reviews/');
define('UPLOAD_THUMB_DIR', __DIR__ . '/uploads/reviews/thumbs/');
define('IMAGE_MAX_WIDTH', 1920);
define('THUMB_WIDTH', 300);

// 한 페이지당 표시할 후기 수
define('REVIEWS_PER_PAGE', 20);

// Rate Limiting
define('RATE_LIMIT_MAX', 60);
define('RATE_LIMIT_WINDOW', 60);

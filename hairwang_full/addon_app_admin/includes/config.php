<?php
/**
 * 앱 관리자 설정 파일
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

// config.php는 _common.php를 통해서만 로드되므로 G5_PATH 체크
if (!defined('G5_PATH')) exit; // 개별 페이지 접근 불가

// ================================================================
// 기본 설정
// ================================================================

// 애드온 버전
define('ADDON_APP_ADMIN_VERSION', '1.0.0');

// 애드온 경로
define('ADDON_APP_ADMIN_PATH', G5_PATH . '/addon_app_admin');
define('ADDON_APP_ADMIN_URL', G5_URL . '/addon_app_admin');

// 업로드 경로
define('ADDON_APP_ADMIN_DATA_PATH', G5_DATA_PATH . '/addon_app_admin');
define('ADDON_APP_ADMIN_DATA_URL', G5_DATA_URL . '/addon_app_admin');

// 파일 업로드 설정
define('ADDON_APP_ADMIN_UPLOAD_PATH', ADDON_APP_ADMIN_DATA_PATH . '/uploads');
define('ADDON_APP_ADMIN_UPLOAD_URL', ADDON_APP_ADMIN_DATA_URL . '/uploads');

// 앱 소스 업로드 경로
define('ADDON_APP_ADMIN_APP_PATH', ADDON_APP_ADMIN_DATA_PATH . '/apps');
define('ADDON_APP_ADMIN_APP_URL', ADDON_APP_ADMIN_DATA_URL . '/apps');

// 최대 업로드 크기 (10MB)
define('ADDON_APP_ADMIN_MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// 허용 이미지 확장자
define('ADDON_APP_ADMIN_ALLOWED_IMAGE_EXTS', 'jpg,jpeg,png,gif,svg,webp');

// 허용 앱 소스 확장자
define('ADDON_APP_ADMIN_ALLOWED_APP_EXTS', 'zip,tar,gz');

// ================================================================
// 테이블 정의
// ================================================================

// 테이블 접두사
define('ADDON_APP_ADMIN_TABLE_PREFIX', 'addon_app_admin_');

// 테이블 이름
$addon_app_admin_tables = array(
    'boards'        => ADDON_APP_ADMIN_TABLE_PREFIX . 'boards',
    'site_config'   => ADDON_APP_ADMIN_TABLE_PREFIX . 'site_config',
    'media'         => ADDON_APP_ADMIN_TABLE_PREFIX . 'media',
    'main_content'  => ADDON_APP_ADMIN_TABLE_PREFIX . 'main_content',
    'app_sources'   => ADDON_APP_ADMIN_TABLE_PREFIX . 'app_sources',
    'app_images'    => ADDON_APP_ADMIN_TABLE_PREFIX . 'app_images',
    'footer'        => ADDON_APP_ADMIN_TABLE_PREFIX . 'footer',
    'logs'          => ADDON_APP_ADMIN_TABLE_PREFIX . 'logs',
    'system'        => ADDON_APP_ADMIN_TABLE_PREFIX . 'system',
);

// 전역 변수로 등록
foreach ($addon_app_admin_tables as $key => $table) {
    $GLOBALS['addon_app_admin_table_' . $key] = $table;
}

// ================================================================
// 권한 설정
// ================================================================

// 최고 관리자만 접근 가능한 메뉴
// true: 최고 관리자만 / false: 일반 관리자도 가능
define('ADDON_APP_ADMIN_SUPER_ONLY', false);

// 접근 권한 레벨
define('ADDON_APP_ADMIN_LEVEL_SUPER', 10);  // 최고 관리자
define('ADDON_APP_ADMIN_LEVEL_ADMIN', 9);   // 관리자
define('ADDON_APP_ADMIN_LEVEL_MANAGER', 8); // 매니저

// ================================================================
// 로그 설정
// ================================================================

// 로그 활성화
define('ADDON_APP_ADMIN_LOG_ENABLED', true);

// 로그 저장 기간 (일)
define('ADDON_APP_ADMIN_LOG_RETENTION_DAYS', 90);

// ================================================================
// 디렉토리 생성
// ================================================================

/**
 * 필요한 디렉토리 생성
 */
function addon_app_admin_create_directories() {
    $directories = array(
        ADDON_APP_ADMIN_DATA_PATH,
        ADDON_APP_ADMIN_UPLOAD_PATH,
        ADDON_APP_ADMIN_APP_PATH,
        ADDON_APP_ADMIN_UPLOAD_PATH . '/logos',
        ADDON_APP_ADMIN_UPLOAD_PATH . '/icons',
        ADDON_APP_ADMIN_UPLOAD_PATH . '/banners',
        ADDON_APP_ADMIN_UPLOAD_PATH . '/backgrounds',
        ADDON_APP_ADMIN_APP_PATH . '/android',
        ADDON_APP_ADMIN_APP_PATH . '/ios',
    );

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, G5_DIR_PERMISSION, true);
            @chmod($dir, G5_DIR_PERMISSION);
        }
    }
}

// ================================================================
// 자동 실행
// ================================================================

// 디렉토리 생성
if (!defined('G5_INSTALL_MODE')) {
    addon_app_admin_create_directories();
}

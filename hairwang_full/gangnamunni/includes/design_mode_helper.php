<?php
/**
 * 디자인 모드 헬퍼
 * 피그마 원본 / 확장 모드 / 커스텀 모드 전환
 *
 * 의존: includes/settings_helper.php (getSettingValue 함수)
 *
 * @file includes/design_mode_helper.php
 * @created 2026-03-25
 */

/**
 * 디자인 기능 활성화 여부 확인
 *
 * dm_mode 설정에 따라:
 *   'figma'    → 항상 false (피그마 원본, 추가 컴포넌트 숨김)
 *   'extended' → 항상 true  (확장 모드, 추가 컴포넌트 표시)
 *   'custom'   → 개별 설정값에 따름
 *
 * @param mysqli $connect     DB 연결
 * @param string $feature_key 개별 설정 키 (dm_detail_treatment_chips 등)
 * @return bool 해당 기능 표시 여부
 */
function isDesignFeatureEnabled($connect, $feature_key) {
    static $mode_cache = null;

    // settings_helper.php가 아직 로드되지 않았으면 로드
    if (!function_exists('getSettingValue')) {
        $helper_path = __DIR__ . '/settings_helper.php';
        if (file_exists($helper_path)) {
            require_once $helper_path;
        } else {
            return false; // 헬퍼 없으면 피그마 모드로 동작
        }
    }

    // dm_mode 캐시
    if ($mode_cache === null) {
        $mode_cache = getSettingValue($connect, 'dm_mode', 'figma');
    }

    // 모드별 분기
    if ($mode_cache === 'figma') return false;
    if ($mode_cache === 'extended') return true;

    // custom 모드 → 개별 설정 확인
    return getSettingValue($connect, $feature_key, '0') === '1';
}

/**
 * 현재 디자인 모드 반환
 *
 * @param mysqli $connect DB 연결
 * @return string 'figma' | 'extended' | 'custom'
 */
function getDesignMode($connect) {
    if (!function_exists('getSettingValue')) {
        require_once __DIR__ . '/settings_helper.php';
    }
    return getSettingValue($connect, 'dm_mode', 'figma');
}

<?php
/**
 * 에디터 설정 API
 * GET - 현재 에디터 설정 반환
 * 관리자가 admin_editor_settings.php에서 설정한 값
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');

$db = Database::getInstance();

// editor_settings 테이블에서 설정 로드
$settings = [
    'camera_only' => false,
    'require_rating' => true,
    'min_content_length' => 10,
    'max_images' => 10,
    'max_ba_images' => 5,
    'hide_ba_photos' => false,
    'hide_procedure_info' => false,
    'auto_approve' => false,
    'ai_check_enabled' => true,
    'allowed_image_types' => 'jpeg,png,gif,webp'
];

try {
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM editor_settings WHERE is_active = 1");
    foreach ($rows as $row) {
        $key = $row['setting_key'];
        $val = $row['setting_value'];
        if (isset($settings[$key])) {
            // 불리언 변환
            if (is_bool($settings[$key])) {
                $settings[$key] = ($val === '1' || $val === 'true');
            } elseif (is_int($settings[$key])) {
                $settings[$key] = intval($val);
            } else {
                $settings[$key] = $val;
            }
        }
    }
} catch (Exception $e) {
    // 테이블 없으면 기본값 사용
}

jsonSuccess(['settings' => $settings]);

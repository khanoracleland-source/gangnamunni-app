<?php
/**
 * 캐시 초기화 처리 (AJAX)
 * 서버 측 캐시 클리어: PHP OpCache, 임시 파일 등
 */

header('Content-Type: application/json; charset=utf-8');

// AJAX 요청 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

// 관리자 권한 체크 (인수인계 정비: 활성화)
require_once __DIR__ . '/includes/admin_auth.php';
if (function_exists('require_admin_login')) {
    // 비로그인 시 JSON 에러 반환 (AJAX 엔드포인트이므로 리다이렉트 대신)
    @session_start();
    require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
    if (empty($_SESSION['ad_admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }
}

$result = [
    'success' => true,
    'message' => '캐시 초기화 완료',
    'cleared' => []
];

try {
    // 1. PHP OpCache 클리어 (PHP 5.5+)
    if (function_exists('opcache_reset')) {
        if (opcache_reset()) {
            $result['cleared'][] = 'PHP OpCache';
        } else {
            $result['warnings'][] = 'OpCache 클리어 실패 (권한 부족 가능)';
        }
    }

    // 2. APCu 캐시 클리어 (사용 중인 경우)
    if (function_exists('apcu_clear_cache')) {
        if (apcu_clear_cache()) {
            $result['cleared'][] = 'APCu Cache';
        }
    }

    // 3. 임시 파일 캐시 클리어 (있는 경우)
    $temp_cache_dirs = [
        __DIR__ . '/cache',
        __DIR__ . '/tmp',
        __DIR__ . '/temp'
    ];

    foreach ($temp_cache_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*.cache');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                $result['cleared'][] = basename($dir) . ' 디렉토리 캐시 파일';
            }
        }
    }

    // 4. Gnuboard 데이터 캐시 클리어 (있는 경우)
    $gnuboard_cache = __DIR__ . '/data/cache';
    if (is_dir($gnuboard_cache)) {
        $cache_files = glob($gnuboard_cache . '/*');
        if ($cache_files) {
            $cleared_count = 0;
            foreach ($cache_files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess') {
                    if (@unlink($file)) {
                        $cleared_count++;
                    }
                }
            }
            if ($cleared_count > 0) {
                $result['cleared'][] = "Gnuboard 캐시 파일 ({$cleared_count}개)";
            }
        }
    }

    // 캐시 클리어 항목이 없는 경우
    if (empty($result['cleared'])) {
        $result['cleared'][] = '클리어할 캐시가 없습니다 (이미 깨끗함)';
    }

} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = '캐시 클리어 중 오류 발생: ' . $e->getMessage();
    error_log('Cache clear error: ' . $e->getMessage());
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

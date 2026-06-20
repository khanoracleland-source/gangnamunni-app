<?php
/**
 * DB 전환 API
 * POST action=switch_to_test  → 테스트 DB로 전환
 * POST action=switch_to_prod  → 운영 DB로 복원
 * GET  action=current          → 현재 DB 확인
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// 관리자 인증 체크
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true)) {
    echo json_encode(['success' => false, 'error' => '관리자 인증 필요']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$override_file = __DIR__ . '/dbconfig.override.php';
$test_file = __DIR__ . '/dbconfig.override.test.php';

switch ($action) {
    case 'switch_to_test':
        if (file_exists($test_file)) {
            copy($test_file, $override_file);
            echo json_encode(['success' => true, 'db' => 'hms_saas_test', 'message' => '테스트 DB로 전환됨']);
        } else {
            echo json_encode(['success' => false, 'error' => 'test override 파일 없음']);
        }
        break;

    case 'switch_to_prod':
        if (file_exists($override_file)) {
            unlink($override_file);
        }
        echo json_encode(['success' => true, 'db' => 'hms_saas', 'message' => '운영 DB로 복원됨']);
        break;

    case 'current':
        if (file_exists($override_file)) {
            $_DB_NAME = 'unknown';
            include $override_file;
            echo json_encode(['success' => true, 'db' => $_DB_NAME, 'mode' => 'override']);
        } else {
            echo json_encode(['success' => true, 'db' => 'hms_saas', 'mode' => 'production']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'action 파라미터 필요 (switch_to_test|switch_to_prod|current)']);
}

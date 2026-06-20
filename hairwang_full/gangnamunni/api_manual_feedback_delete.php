<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
/**
 * 매뉴얼 피드백 일괄 삭제 API
 * POST /api_manual_feedback_delete.php
 * Body: {"ids": [1,2,3]}
 * 관리자 인증 필요
 */
header('Content-Type: application/json; charset=utf-8');

// 관리자 세션 확인
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '권한 없음']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$ids  = $body['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'ids 필수']); exit;
}

// 정수 검증
$ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => '유효한 id 없음']); exit;
}

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, 'hms_saas', 3306);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']); exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

$stmt = mysqli_prepare($connect, "DELETE FROM manual_feedback_items WHERE id IN ($placeholders)");
mysqli_stmt_bind_param($stmt, $types, ...$ids);
$ok      = mysqli_stmt_execute($stmt);
$deleted = mysqli_affected_rows($connect);
mysqli_stmt_close($stmt);
mysqli_close($connect);

if ($ok) {
    echo json_encode(['success' => true, 'deleted' => $deleted]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 삭제 실패']);
}

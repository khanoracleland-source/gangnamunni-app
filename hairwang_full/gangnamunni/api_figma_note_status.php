<?php
// ============================================================================
// [Figma Review - 2026-06-05] 피그마 검수 메모 상태 업데이트 API
// 원인 : Claude가 작업 완료 후 status='done' + claude_response 기록 필요
// 변경 : POST status / claude_response / assigned_to
// 영향 범위 : Claude 작업 종료 후 호출 + admin_figma_review.php
// ============================================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['ok' => false, 'error' => 'DB connect failed: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id              = (int)($input['id'] ?? 0);
$figma_filename  = trim($input['figma_filename'] ?? '');
$status          = trim($input['status'] ?? '');
$claude_response = trim($input['claude_response'] ?? '');
$assigned_to     = trim($input['assigned_to'] ?? '');

if ($id <= 0 && $figma_filename === '') {
    echo json_encode(['ok' => false, 'error' => 'id or figma_filename required']);
    exit;
}
if ($status !== '' && !in_array($status, ['pending','in_progress','done','rejected'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid status']);
    exit;
}

$updates = [];
$params = [];
$types = '';
if ($status !== '') {
    $updates[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
    if ($status === 'done') {
        $updates[] = 'completed_at = CURRENT_TIMESTAMP';
    }
}
if ($claude_response !== '') {
    $updates[] = 'claude_response = ?';
    $params[] = $claude_response;
    $types .= 's';
}
if ($assigned_to !== '') {
    $updates[] = 'assigned_to = ?';
    $params[] = $assigned_to;
    $types .= 's';
}
if (empty($updates)) {
    echo json_encode(['ok' => false, 'error' => 'no fields to update']);
    exit;
}

if ($id > 0) {
    $where_clause = 'id = ?';
    $params[] = $id;
    $types .= 'i';
} else {
    $where_clause = 'figma_filename = ?';
    $params[] = $figma_filename;
    $types .= 's';
}

$sql = "UPDATE figma_review_notes SET " . implode(', ', $updates) . " WHERE $where_clause";
$stmt = mysqli_prepare($connect, $sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'prepare failed: ' . mysqli_error($connect)]);
    exit;
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => false, 'error' => 'execute failed: ' . mysqli_stmt_error($stmt)]);
    exit;
}
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

echo json_encode([
    'ok' => true,
    'affected' => $affected,
    'message' => '상태가 업데이트되었습니다.',
]);

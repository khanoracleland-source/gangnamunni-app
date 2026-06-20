<?php
// ============================================================================
// [Figma Review - 2026-06-05] 피그마 검수 메모 저장 API
// 원인 : 피그마 vs 구현 페이지 검수 메모 + 수정 프롬프트 DB 저장 필요
// 변경 : POST 요청으로 figma_review_notes UPSERT
// 영향 범위 : figma_compare.html + admin_figma_review.php
// ============================================================================
session_start();
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

$figma_filename     = trim($input['figma_filename'] ?? '');
$figma_group        = trim($input['figma_group'] ?? '');
$figma_index        = (int)($input['figma_index'] ?? 1);
$current_impl_url   = trim($input['current_impl_url'] ?? '');
$is_wrong_match     = !empty($input['is_wrong_match']) ? 1 : 0;
$suggested_impl_url = trim($input['suggested_impl_url'] ?? '');
$fix_prompt         = trim($input['fix_prompt'] ?? '');
$priority           = in_array($input['priority'] ?? '', ['high','medium','low']) ? $input['priority'] : 'medium';

if ($figma_filename === '' || $figma_group === '') {
    echo json_encode(['ok' => false, 'error' => 'figma_filename, figma_group required']);
    exit;
}

$sql = "INSERT INTO figma_review_notes
        (figma_filename, figma_group, figma_index, current_impl_url,
         is_wrong_match, suggested_impl_url, fix_prompt, priority, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
            figma_group         = VALUES(figma_group),
            figma_index         = VALUES(figma_index),
            current_impl_url    = VALUES(current_impl_url),
            is_wrong_match      = VALUES(is_wrong_match),
            suggested_impl_url  = VALUES(suggested_impl_url),
            fix_prompt          = VALUES(fix_prompt),
            priority            = VALUES(priority),
            status              = IF(status='done', 'done', 'pending'),
            updated_at          = CURRENT_TIMESTAMP";

$stmt = mysqli_prepare($connect, $sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'prepare failed: ' . mysqli_error($connect)]);
    exit;
}

// 타입: s,s,i,s,i,s,s,s (filename, group, index, url, is_wrong, suggested, prompt, priority)
mysqli_stmt_bind_param($stmt, 'ssisisss',
    $figma_filename, $figma_group, $figma_index, $current_impl_url,
    $is_wrong_match, $suggested_impl_url, $fix_prompt, $priority);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => false, 'error' => 'execute failed: ' . mysqli_stmt_error($stmt)]);
    exit;
}

$id = mysqli_insert_id($connect);
if (!$id) {
    $r = mysqli_query($connect, "SELECT id FROM figma_review_notes WHERE figma_filename = '" . mysqli_real_escape_string($connect, $figma_filename) . "'");
    if ($r && ($row = mysqli_fetch_assoc($r))) $id = $row['id'];
}

mysqli_stmt_close($stmt);

echo json_encode([
    'ok' => true,
    'id' => (int)$id,
    'figma_filename' => $figma_filename,
    'message' => '메모가 저장되었습니다.',
]);

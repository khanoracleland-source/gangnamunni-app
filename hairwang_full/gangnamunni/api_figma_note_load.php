<?php
// ============================================================================
// [Figma Review - 2026-06-05] 피그마 검수 메모 조회 API
// 원인 : figma_compare.html 페이지 로드 시 기존 메모 표시
// 변경 : GET ?filename=... 또는 GET all=1
// 영향 범위 : figma_compare.html (페이지 로드)
// ============================================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once __DIR__ . '/dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['ok' => false, 'error' => 'DB connect failed: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$filename = trim($_GET['filename'] ?? '');
$all = !empty($_GET['all']);

if ($all) {
    // 전체 조회 (figma_compare 페이지 초기 로드용)
    $r = mysqli_query($connect, "SELECT * FROM figma_review_notes ORDER BY updated_at DESC");
    $notes = [];
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $notes[$row['figma_filename']] = $row;
    }
    echo json_encode(['ok' => true, 'count' => count($notes), 'notes' => $notes]);
    exit;
}

if ($filename === '') {
    echo json_encode(['ok' => false, 'error' => 'filename or all required']);
    exit;
}

$esc = mysqli_real_escape_string($connect, $filename);
$r = mysqli_query($connect, "SELECT * FROM figma_review_notes WHERE figma_filename = '$esc' LIMIT 1");
if ($r && ($row = mysqli_fetch_assoc($r))) {
    echo json_encode(['ok' => true, 'note' => $row]);
} else {
    echo json_encode(['ok' => true, 'note' => null]);
}

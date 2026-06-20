<?php
/**
 * 디자인 피드백 API — 피그마 비교 보고서용
 * GET: 전체 피드백 로드
 * POST: 피드백 저장/수정
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../dbconfig.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) { echo json_encode(['error' => 'DB connection failed']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = [];
    $q = mysqli_query($connect, "SELECT page_name, item_key, feedback, status FROM gangnamunni_design_feedback ORDER BY page_name, id");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $rows[$r['page_name'] . '::' . $r['item_key']] = $r;
        }
    }
    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['page_name']) || empty($input['item_key'])) {
        echo json_encode(['error' => 'Missing page_name or item_key']);
        exit;
    }
    $page = mysqli_real_escape_string($connect, $input['page_name']);
    $item = mysqli_real_escape_string($connect, $input['item_key']);
    $feedback = mysqli_real_escape_string($connect, $input['feedback'] ?? '');
    $status = in_array($input['status'] ?? '', ['pending','done','skip']) ? $input['status'] : 'pending';

    $sql = "INSERT INTO gangnamunni_design_feedback (page_name, item_key, feedback, status)
            VALUES ('{$page}', '{$item}', '{$feedback}', '{$status}')
            ON DUPLICATE KEY UPDATE feedback='{$feedback}', status='{$status}'";
    $ok = mysqli_query($connect, $sql);
    echo json_encode(['ok' => (bool)$ok]);
    exit;
}

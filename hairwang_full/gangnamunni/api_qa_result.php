<?php
/**
 * QA 결과 저장/조회 API
 * POST: { issue_no: '12', result_text: '수정 완료...' }
 * GET: 전체 결과 반환
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = [];
    $r = mysqli_query($connect, "SELECT issue_no, result_text, updated_at FROM gangnamunni_qa_results ORDER BY issue_no ASC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[$row['issue_no']] = $row;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $issue_no = trim($input['issue_no'] ?? '');
    $result_text = trim($input['result_text'] ?? '');

    if (empty($issue_no)) {
        echo json_encode(['success' => false, 'message' => 'issue_no 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_qa_results (issue_no, result_text) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE result_text = VALUES(result_text), updated_at = NOW()"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $issue_no, $result_text);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '저장 완료']);
        } else {
            echo json_encode(['success' => false, 'message' => '저장 실패']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL 오류']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => '지원하지 않는 메서드']);

<?php
/**
 * 피그마 디자인 질문 답변 API
 * GET  — 답변 전체 로드
 * POST — 답변 저장 (개별 또는 전체)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "
    CREATE TABLE IF NOT EXISTS gangnamunni_figma_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id VARCHAR(20) NOT NULL UNIQUE,
        answer_text TEXT,
        answered_by VARCHAR(100) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_qid (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 전체 답변 로드
    $result = mysqli_query($connect, "SELECT question_id, answer_text, answered_by, updated_at FROM gangnamunni_figma_answers ORDER BY question_id");
    $answers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $answers[$row['question_id']] = [
            'text' => $row['answer_text'],
            'by' => $row['answered_by'],
            'updated' => $row['updated_at']
        ];
    }
    echo json_encode(['success' => true, 'answers' => $answers, 'count' => count($answers)]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    // 전체 저장 (bulk)
    if (isset($input['answers']) && is_array($input['answers'])) {
        $by = mysqli_real_escape_string($connect, $input['answered_by'] ?? '');
        $saved = 0;

        $stmt = mysqli_prepare($connect, "
            INSERT INTO gangnamunni_figma_answers (question_id, answer_text, answered_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), answered_by = VALUES(answered_by)
        ");

        foreach ($input['answers'] as $qid => $text) {
            $qid = substr($qid, 0, 20);
            mysqli_stmt_bind_param($stmt, 'sss', $qid, $text, $by);
            mysqli_stmt_execute($stmt);
            $saved++;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'saved' => $saved]);

    // 개별 저장
    } elseif (isset($input['question_id']) && isset($input['answer_text'])) {
        $qid = substr($input['question_id'], 0, 20);
        $text = $input['answer_text'];
        $by = $input['answered_by'] ?? '';

        $stmt = mysqli_prepare($connect, "
            INSERT INTO gangnamunni_figma_answers (question_id, answer_text, answered_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), answered_by = VALUES(answered_by)
        ");
        mysqli_stmt_bind_param($stmt, 'sss', $qid, $text, $by);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'question_id' => $qid]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Missing question_id or answers']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

mysqli_close($connect);

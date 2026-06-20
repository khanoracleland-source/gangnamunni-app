<?php
/**
 * QA 피드백 / 수정 요청 API
 * 클라이언트 + 개발팀이 각 기능별로 피드백을 입력/조회/수정
 * DB: gangnamunni_qa_feedback (자동 생성)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_qa_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id VARCHAR(20) NOT NULL COMMENT '페이지 식별자 (A1, B2 등)',
    page_name VARCHAR(100) DEFAULT '' COMMENT '페이지 이름',
    page_file VARCHAR(100) DEFAULT '' COMMENT '파일명',
    feedback_type ENUM('bug','feature','improvement','question','other') DEFAULT 'bug' COMMENT '피드백 유형',
    content TEXT NOT NULL COMMENT '피드백 내용',
    author VARCHAR(50) DEFAULT '' COMMENT '작성자 (클라이언트/개발팀)',
    author_role ENUM('client','dev','qa') DEFAULT 'client' COMMENT '작성자 역할',
    priority ENUM('low','medium','high','critical') DEFAULT 'medium' COMMENT '우선순위',
    status ENUM('pending','in_progress','resolved','rejected','deferred') DEFAULT 'pending' COMMENT '처리 상태',
    dev_note TEXT COMMENT '개발팀 답변/메모',
    screenshot_url VARCHAR(500) DEFAULT '' COMMENT '스크린샷 URL',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    INDEX idx_page (page_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: 목록 조회
if ($method === 'GET') {
    $page_id = $_GET['page_id'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));

    $where = '1=1';
    $params = [];
    $types = '';

    if ($page_id) {
        $where .= ' AND page_id = ?';
        $params[] = $page_id;
        $types .= 's';
    }
    if ($status_filter) {
        $where .= ' AND status = ?';
        $params[] = $status_filter;
        $types .= 's';
    }

    $sql = "SELECT * FROM gangnamunni_qa_feedback WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}";
    $stmt = mysqli_prepare($connect, $sql);
    if ($types) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    // 통계
    $stats_sql = "SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='in_progress') as in_progress,
        SUM(status='resolved') as resolved,
        SUM(priority='critical') as critical,
        SUM(priority='high') as high
        FROM gangnamunni_qa_feedback";
    $stats = mysqli_fetch_assoc(mysqli_query($connect, $stats_sql));

    echo json_encode(['success'=>true, 'data'=>$items, 'stats'=>$stats], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: 신규 등록
if ($method === 'POST') {
    $page_id = trim($input['page_id'] ?? '');
    $content = trim($input['content'] ?? '');

    if (empty($page_id) || empty($content)) {
        echo json_encode(['success'=>false, 'message'=>'page_id, content 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_qa_feedback (page_id, page_name, page_file, feedback_type, content, author, author_role, priority, screenshot_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $page_name = $input['page_name'] ?? '';
    $page_file = $input['page_file'] ?? '';
    $type = in_array($input['feedback_type'] ?? '', ['bug','feature','improvement','question','other']) ? $input['feedback_type'] : 'bug';
    $author = $input['author'] ?? '';
    $role = in_array($input['author_role'] ?? '', ['client','dev','qa']) ? $input['author_role'] : 'client';
    $priority = in_array($input['priority'] ?? '', ['low','medium','high','critical']) ? $input['priority'] : 'medium';
    $screenshot = $input['screenshot_url'] ?? '';

    mysqli_stmt_bind_param($stmt, 'sssssssss', $page_id, $page_name, $page_file, $type, $content, $author, $role, $priority, $screenshot);

    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($connect);
        echo json_encode(['success'=>true, 'id'=>$id, 'message'=>'피드백 등록 완료']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'등록 실패: ' . mysqli_error($connect)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// PUT: 상태/메모 수정
if ($method === 'PUT') {
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success'=>false, 'message'=>'id 필수']);
        exit;
    }

    $updates = [];
    $params = [];
    $types = '';

    if (isset($input['status'])) {
        $valid = ['pending','in_progress','resolved','rejected','deferred'];
        if (in_array($input['status'], $valid)) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
            $types .= 's';
            if ($input['status'] === 'resolved') {
                $updates[] = 'resolved_at = NOW()';
            }
        }
    }
    if (isset($input['dev_note'])) {
        $updates[] = 'dev_note = ?';
        $params[] = $input['dev_note'];
        $types .= 's';
    }
    if (isset($input['priority'])) {
        $updates[] = 'priority = ?';
        $params[] = $input['priority'];
        $types .= 's';
    }

    if (empty($updates)) {
        echo json_encode(['success'=>false, 'message'=>'변경 사항 없음']);
        exit;
    }

    $sql = "UPDATE gangnamunni_qa_feedback SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';

    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true, 'message'=>'수정 완료']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'수정 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// DELETE: 삭제
if ($method === 'DELETE') {
    $id = intval($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success'=>false, 'message'=>'id 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_qa_feedback WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true, 'message'=>'삭제 완료']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'삭제 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success'=>false, 'message'=>'Invalid method']);

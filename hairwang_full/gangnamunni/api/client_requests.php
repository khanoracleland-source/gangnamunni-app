<?php
/**
 * 클라이언트 요구사항 REST API
 * project_status_report.html에서 호출
 * DB: gangnamunni_client_requests (auto-create)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_client_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    file_name VARCHAR(500),
    url VARCHAR(500),
    progress INT DEFAULT 0,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    dev_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET: 목록 조회
if ($method === 'GET') {
    $status_filter = $_GET['status'] ?? '';
    $where = '1=1';
    $params = [];
    $types = '';

    if ($status_filter) {
        $where .= ' AND status = ?';
        $params[] = $status_filter;
        $types .= 's';
    }

    $sql = "SELECT * FROM gangnamunni_client_requests WHERE {$where} ORDER BY created_at DESC";
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
    $stats = mysqli_fetch_assoc(mysqli_query($connect, "SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='in_progress') as in_progress,
        SUM(status='completed') as completed
        FROM gangnamunni_client_requests"));

    echo json_encode(['success' => true, 'data' => $items, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: 신규 등록
if ($method === 'POST') {
    $title = trim($input['title'] ?? '');
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'title 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_client_requests (title, content, file_name, url) VALUES (?, ?, ?, ?)"
    );
    $content = $input['content'] ?? '';
    $file_name = $input['file_name'] ?? '';
    $url = $input['url'] ?? '';

    mysqli_stmt_bind_param($stmt, 'ssss', $title, $content, $file_name, $url);

    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($connect);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => '등록 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// PUT: 수정
if ($method === 'PUT') {
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'id 필수']);
        exit;
    }

    $updates = [];
    $params = [];
    $types = '';

    if (isset($input['status'])) {
        $valid = ['pending', 'in_progress', 'completed'];
        if (in_array($input['status'], $valid)) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
            $types .= 's';
            if ($input['status'] === 'completed') {
                $updates[] = 'completed_at = NOW()';
            }
        }
    }
    if (isset($input['progress'])) {
        $updates[] = 'progress = ?';
        $params[] = max(0, min(100, intval($input['progress'])));
        $types .= 'i';
    }
    if (isset($input['dev_note'])) {
        $updates[] = 'dev_note = ?';
        $params[] = $input['dev_note'];
        $types .= 's';
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => '변경 사항 없음']);
        exit;
    }

    $sql = "UPDATE gangnamunni_client_requests SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';

    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '수정 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// DELETE: 삭제
if ($method === 'DELETE') {
    $id = intval($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'id 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_client_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '삭제 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);

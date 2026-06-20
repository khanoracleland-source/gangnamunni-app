<?php
/**
 * 비포/애프터 갤러리 API
 * 병원별 비포/애프터 사진 조회 + 등록
 * DB: gangnamunni_before_after (자동 생성)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_before_after (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    review_id INT DEFAULT NULL COMMENT '연결된 후기 ID',
    treatment_name VARCHAR(100) DEFAULT '' COMMENT '시술명',
    before_image_url VARCHAR(500) NOT NULL COMMENT '비포 이미지',
    after_image_url VARCHAR(500) NOT NULL COMMENT '애프터 이미지',
    description TEXT COMMENT '설명',
    author_name VARCHAR(50) DEFAULT '' COMMENT '작성자',
    duration_text VARCHAR(50) DEFAULT '' COMMENT '시술 후 경과 (예: 3개월 후)',
    is_verified TINYINT(1) DEFAULT 0 COMMENT '인증 여부',
    is_active TINYINT(1) DEFAULT 1,
    view_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospital (hospital_id),
    INDEX idx_treatment (treatment_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// treatment_id 컬럼 추가 (기존 테이블에 없으면)
$col_chk = mysqli_query($connect, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gangnamunni_before_after' AND COLUMN_NAME='treatment_id' LIMIT 1");
if ($col_chk && mysqli_num_rows($col_chk) === 0) {
    mysqli_query($connect, "ALTER TABLE gangnamunni_before_after ADD COLUMN treatment_id INT DEFAULT NULL AFTER treatment_name, ADD INDEX idx_treatment_id (treatment_id)");
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET: 목록 조회
if ($method === 'GET') {
    $hospital_id = intval($_GET['hospital_id'] ?? 0);
    $treatment = $_GET['treatment'] ?? '';
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $where = 'is_active = 1';
    $params = [];
    $types = '';

    if ($hospital_id > 0) {
        $where .= ' AND hospital_id = ?';
        $params[] = $hospital_id;
        $types .= 'i';
    }
    if ($treatment) {
        $where .= ' AND treatment_name = ?';
        $params[] = $treatment;
        $types .= 's';
    }
    $treatment_id = intval($_GET['treatment_id'] ?? 0);
    if ($treatment_id > 0) {
        $where .= ' AND treatment_id = ?';
        $params[] = $treatment_id;
        $types .= 'i';
    }

    // 카운트
    $count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_before_after WHERE {$where}";
    $count_stmt = mysqli_prepare($connect, $count_sql);
    if ($types) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['cnt'];
    mysqli_stmt_close($count_stmt);

    // 목록
    $sql = "SELECT ba.*, h.name as hospital_name, tm.name as master_treatment_name, tm.hashtag as treatment_hashtag, tc.name as category_name
            FROM gangnamunni_before_after ba
            LEFT JOIN gangnamunni_hospitals h ON ba.hospital_id = h.id
            LEFT JOIN gangnamunni_treatment_master tm ON ba.treatment_id = tm.id
            LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
            WHERE ba.{$where}
            ORDER BY ba.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    $stmt = mysqli_prepare($connect, $sql);
    if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    // 시술 카테고리 목록
    $cat_sql = "SELECT DISTINCT treatment_name FROM gangnamunni_before_after WHERE is_active=1 AND treatment_name != '' ORDER BY treatment_name";
    $cat_result = mysqli_query($connect, $cat_sql);
    $categories = [];
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row['treatment_name'];
    }

    echo json_encode([
        'success' => true,
        'data' => $items,
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: 등록 (관리자/인증 사용자)
if ($method === 'POST') {
    $hospital_id = intval($input['hospital_id'] ?? 0);
    $before_url = trim($input['before_image_url'] ?? '');
    $after_url = trim($input['after_image_url'] ?? '');

    if ($hospital_id <= 0 || empty($before_url) || empty($after_url)) {
        echo json_encode(['success' => false, 'message' => 'hospital_id, before_image_url, after_image_url 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_before_after (hospital_id, review_id, treatment_name, treatment_id, before_image_url, after_image_url, description, author_name, duration_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $review_id = intval($input['review_id'] ?? 0) ?: null;
    $treatment = $input['treatment_name'] ?? '';
    $tid = intval($input['treatment_id'] ?? 0) ?: null;
    $desc = $input['description'] ?? '';
    $author = $input['author_name'] ?? '';
    $duration = $input['duration_text'] ?? '';

    mysqli_stmt_bind_param($stmt, 'iisisssss', $hospital_id, $review_id, $treatment, $tid, $before_url, $after_url, $desc, $author, $duration);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'id' => mysqli_insert_id($connect)]);
    } else {
        echo json_encode(['success' => false, 'message' => '등록 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);

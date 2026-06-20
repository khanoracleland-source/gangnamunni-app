<?php
/**
 * 찜하기 (즐겨찾기) API
 * 병원/의사 찜 토글 + 목록 조회
 * DB: gangnamunni_wishlist (자동 생성)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL COMMENT '회원 ID (g5_member.mb_no)',
    target_type ENUM('hospital','doctor') NOT NULL DEFAULT 'hospital',
    target_id INT NOT NULL COMMENT '대상 ID (hospital.id 또는 doctor.id)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_member_target (member_id, target_type, target_id),
    INDEX idx_member (member_id),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Gnuboard 회원 확인
$member_id = 0;
session_start();
// Gnuboard 세션에서 회원 ID 가져오기
if (!empty($_SESSION['ss_mb_id'])) {
    $mb_id = mysqli_real_escape_string($connect, $_SESSION['ss_mb_id']);
    $r = mysqli_query($connect, "SELECT mb_no FROM g5_member WHERE mb_id='{$mb_id}' LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        $member_id = (int)$row['mb_no'];
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET: 찜 목록 조회 + 단일 체크
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // 단일 체크 (로그인 불필요 시 false 반환)
    if ($action === 'check') {
        $type = $_GET['type'] ?? 'hospital';
        $tid = intval($_GET['target_id'] ?? 0);
        if ($member_id <= 0 || $tid <= 0) {
            echo json_encode(['success' => true, 'wishlisted' => false, 'count' => 0]);
            exit;
        }
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_wishlist WHERE member_id=? AND target_type=? AND target_id=?");
        mysqli_stmt_bind_param($stmt, 'isi', $member_id, $type, $tid);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        mysqli_stmt_close($stmt);

        // 해당 대상의 총 찜 수
        $stmt2 = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_wishlist WHERE target_type=? AND target_id=?");
        mysqli_stmt_bind_param($stmt2, 'si', $type, $tid);
        mysqli_stmt_execute($stmt2);
        $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['cnt'];
        mysqli_stmt_close($stmt2);

        echo json_encode(['success' => true, 'wishlisted' => $exists, 'count' => (int)$cnt]);
        exit;
    }

    // 목록 조회
    if ($member_id <= 0) {
        echo json_encode(['success' => true, 'data' => [], 'message' => '로그인 필요']);
        exit;
    }

    $type_filter = $_GET['type'] ?? '';
    $where = 'w.member_id = ?';
    $params = [$member_id];
    $types = 'i';

    if ($type_filter && in_array($type_filter, ['hospital', 'doctor'])) {
        $where .= ' AND w.target_type = ?';
        $params[] = $type_filter;
        $types .= 's';
    }

    // 병원 찜 목록 (JOIN)
    $sql = "SELECT w.*, w.target_type,
            CASE WHEN w.target_type='hospital' THEN h.name ELSE d.name END as target_name,
            CASE WHEN w.target_type='hospital' THEN h.main_image_url ELSE d.profile_image_url END as target_image,
            CASE WHEN w.target_type='hospital' THEN h.address ELSE NULL END as target_address,
            CASE WHEN w.target_type='hospital' THEN h.rating ELSE NULL END as target_rating,
            CASE WHEN w.target_type='hospital' THEN h.review_count ELSE NULL END as target_review_count
            FROM gangnamunni_wishlist w
            LEFT JOIN gangnamunni_hospitals h ON w.target_type='hospital' AND w.target_id=h.id
            LEFT JOIN gangnamunni_doctors d ON w.target_type='doctor' AND w.target_id=d.id
            WHERE {$where}
            ORDER BY w.created_at DESC";

    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'data' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: 찜 토글 (추가/제거)
if ($method === 'POST') {
    if ($member_id <= 0) {
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다', 'login_required' => true]);
        exit;
    }

    $type = $input['type'] ?? 'hospital';
    $tid = intval($input['target_id'] ?? 0);

    if (!in_array($type, ['hospital', 'doctor']) || $tid <= 0) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청']);
        exit;
    }

    // 이미 찜 여부 확인
    $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_wishlist WHERE member_id=? AND target_type=? AND target_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $member_id, $type, $tid);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt);

    if ($existing->num_rows > 0) {
        // 이미 있으면 제거
        mysqli_stmt_close($stmt);
        $del = mysqli_prepare($connect, "DELETE FROM gangnamunni_wishlist WHERE member_id=? AND target_type=? AND target_id=?");
        mysqli_stmt_bind_param($del, 'isi', $member_id, $type, $tid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $action_done = 'removed';
    } else {
        // 없으면 추가
        mysqli_stmt_close($stmt);
        $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_wishlist (member_id, target_type, target_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'isi', $member_id, $type, $tid);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        $action_done = 'added';
    }

    // 현재 총 찜 수
    $stmt2 = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_wishlist WHERE target_type=? AND target_id=?");
    mysqli_stmt_bind_param($stmt2, 'si', $type, $tid);
    mysqli_stmt_execute($stmt2);
    $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['cnt'];
    mysqli_stmt_close($stmt2);

    echo json_encode([
        'success' => true,
        'action' => $action_done,
        'wishlisted' => ($action_done === 'added'),
        'count' => (int)$cnt,
        'message' => $action_done === 'added' ? '찜 목록에 추가했습니다' : '찜 목록에서 제거했습니다'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);

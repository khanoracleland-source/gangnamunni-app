<?php
/**
 * 사용자 차단/해제 REST API
 * Apple App Store Guideline 1.2 준수 - 악성 사용자 차단 기능
 *
 * POST   : 사용자 차단
 * DELETE : 차단 해제
 * GET    : 차단 목록 / 단건 확인 / 차단 ID 목록
 *
 * DB: gangnamunni_blocked_users
 * @created 2026-03-17
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/dbconfig.php';

$connect = @mysqli_connect($_DB_HOST, $_DB_USER, $_DB_PASSWORD, $_DB_NAME);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패'], JSON_UNESCAPED_UNICODE);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// --- 공통 응답 헬퍼 ---
function jsonSuccess($data = []) {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 테이블 자동 생성 ---
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id VARCHAR(100) NOT NULL COMMENT '차단한 사용자 (ss_mb_id)',
    blocked_id VARCHAR(100) NOT NULL COMMENT '차단당한 사용자 (ss_mb_id)',
    blocked_name VARCHAR(200) DEFAULT NULL COMMENT '차단 대상 표시명',
    reason VARCHAR(500) DEFAULT NULL COMMENT '차단 사유',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_block_pair (blocker_id, blocked_id),
    INDEX idx_blocker (blocker_id),
    INDEX idx_blocked (blocked_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// --- 로그인 확인 ---
$blocker_id = '';
if (!empty($_SESSION['ss_mb_id'])) {
    $blocker_id = $_SESSION['ss_mb_id'];
}

$method = $_SERVER['REQUEST_METHOD'];

// ========================================
// POST: 사용자 차단
// ========================================
if ($method === 'POST') {
    // 로그인 필수
    if (empty($blocker_id)) {
        jsonError('로그인이 필요합니다.', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // fallback: form data
        $input = $_POST;
    }

    $blocked_id   = trim($input['blocked_id'] ?? '');
    $blocked_name = trim($input['blocked_name'] ?? '');
    $reason       = trim($input['reason'] ?? '');

    // 필수값 검증
    if (empty($blocked_id)) {
        jsonError('차단할 사용자 ID(blocked_id)가 필요합니다.');
    }

    // 자기 자신 차단 방지
    if ($blocker_id === $blocked_id) {
        jsonError('자기 자신을 차단할 수 없습니다.');
    }

    // INSERT ... ON DUPLICATE KEY — 이미 차단된 경우 사유/이름만 업데이트
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_blocked_users (blocker_id, blocked_id, blocked_name, reason)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE blocked_name = VALUES(blocked_name), reason = VALUES(reason)"
    );
    if (!$stmt) {
        jsonError('SQL 준비 실패: ' . mysqli_error($connect), 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssss', $blocker_id, $blocked_id, $blocked_name, $reason);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        jsonSuccess(['message' => '사용자를 차단했습니다']);
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        jsonError('차단 처리 실패: ' . $err, 500);
    }
}

// ========================================
// DELETE: 차단 해제
// ========================================
if ($method === 'DELETE') {
    // 로그인 필수
    if (empty($blocker_id)) {
        jsonError('로그인이 필요합니다.', 401);
    }

    // JSON body 또는 query param 에서 blocked_id 추출
    $blocked_id = '';
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['blocked_id'])) {
        $blocked_id = trim($input['blocked_id']);
    } elseif (!empty($_GET['blocked_id'])) {
        $blocked_id = trim($_GET['blocked_id']);
    }

    if (empty($blocked_id)) {
        jsonError('해제할 사용자 ID(blocked_id)가 필요합니다.');
    }

    $stmt = mysqli_prepare($connect,
        "DELETE FROM gangnamunni_blocked_users WHERE blocker_id = ? AND blocked_id = ?"
    );
    if (!$stmt) {
        jsonError('SQL 준비 실패', 500);
    }
    mysqli_stmt_bind_param($stmt, 'ss', $blocker_id, $blocked_id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected > 0) {
        jsonSuccess(['message' => '차단을 해제했습니다']);
    } else {
        jsonError('차단 내역을 찾을 수 없습니다.', 404);
    }
}

// ========================================
// GET: 차단 목록 / 단건 확인 / ID 목록
// ========================================
if ($method === 'GET') {
    // 로그인 필수
    if (empty($blocker_id)) {
        jsonError('로그인이 필요합니다.', 401);
    }

    $action = $_GET['action'] ?? 'list';

    // --- 내 차단 목록 ---
    if ($action === 'list') {
        $stmt = mysqli_prepare($connect,
            "SELECT id, blocker_id, blocked_id, blocked_name, reason, created_at
             FROM gangnamunni_blocked_users
             WHERE blocker_id = ?
             ORDER BY created_at DESC"
        );
        if (!$stmt) {
            jsonError('SQL 준비 실패', 500);
        }
        mysqli_stmt_bind_param($stmt, 's', $blocker_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);

        jsonSuccess($items);
    }

    // --- 단건 차단 여부 확인 ---
    if ($action === 'check') {
        $user_id = trim($_GET['user_id'] ?? '');
        if (empty($user_id)) {
            jsonError('확인할 사용자 ID(user_id)가 필요합니다.');
        }

        $stmt = mysqli_prepare($connect,
            "SELECT id FROM gangnamunni_blocked_users WHERE blocker_id = ? AND blocked_id = ? LIMIT 1"
        );
        if (!$stmt) {
            jsonError('SQL 준비 실패', 500);
        }
        mysqli_stmt_bind_param($stmt, 'ss', $blocker_id, $user_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        mysqli_stmt_close($stmt);

        jsonSuccess(['blocked' => $exists]);
    }

    // --- 차단된 사용자 ID 전체 목록 (피드 필터링용) ---
    if ($action === 'blocked_ids') {
        $stmt = mysqli_prepare($connect,
            "SELECT blocked_id FROM gangnamunni_blocked_users WHERE blocker_id = ?"
        );
        if (!$stmt) {
            jsonError('SQL 준비 실패', 500);
        }
        mysqli_stmt_bind_param($stmt, 's', $blocker_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $ids = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = $row['blocked_id'];
        }
        mysqli_stmt_close($stmt);

        jsonSuccess(['blocked_ids' => $ids]);
    }

    // 알 수 없는 action
    jsonError('지원하지 않는 action입니다: ' . $action);
}

// 지원하지 않는 메서드
jsonError('허용되지 않는 메서드입니다.', 405);

<?php
/**
 * 콘텐츠 신고 REST API
 * Apple App Store Guideline 1.2 준수 - 부적절한 사용자 생성 콘텐츠 신고 기능
 *
 * POST              : 신고 접수
 * GET ?action=my_reports  : 내 신고 이력 조회
 * GET ?action=check       : 이미 신고했는지 확인
 * GET ?action=stats       : 신고 통계 (관리자 전용)
 *
 * DB: gangnamunni_content_reports
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
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_content_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL COMMENT '신고자 회원번호 (g5_member.mb_no)',
    reporter_name VARCHAR(100) NOT NULL DEFAULT '' COMMENT '신고자 이름/닉네임',
    content_type ENUM('review','qna','community','chat','comment','tip','event') NOT NULL COMMENT '콘텐츠 유형',
    content_id INT NOT NULL COMMENT '신고 대상 콘텐츠 ID',
    content_url VARCHAR(500) DEFAULT '' COMMENT '콘텐츠 URL (참고용)',
    content_snippet VARCHAR(500) DEFAULT '' COMMENT '콘텐츠 일부 발췌',
    reported_user_id INT DEFAULT NULL COMMENT '피신고자 회원번호',
    reported_user_name VARCHAR(100) DEFAULT '' COMMENT '피신고자 이름/닉네임',
    reason ENUM('spam','harassment','hate_speech','sexual','violence','misinformation','copyright','other') NOT NULL COMMENT '신고 사유',
    reason_detail TEXT COMMENT '상세 사유 (자유 입력)',
    status ENUM('pending','investigating','resolved','dismissed') NOT NULL DEFAULT 'pending' COMMENT '처리 상태',
    action_taken VARCHAR(200) DEFAULT '' COMMENT '조치 내용 (숨김/삭제/계정정지 등)',
    action_note TEXT COMMENT '관리자 처리 메모',
    admin_id INT DEFAULT NULL COMMENT '처리 관리자 ID',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '신고 접수일시',
    resolved_at DATETIME DEFAULT NULL COMMENT '처리 완료일시',
    INDEX idx_reporter (reporter_id),
    INDEX idx_content (content_type, content_id),
    INDEX idx_status (status),
    INDEX idx_reported_user (reported_user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='콘텐츠 신고 (App Store 1.2 준수)'");

// --- 로그인 사용자 정보 확인 ---
$reporter_id = 0;
$reporter_name = '';
$is_admin = false;

// 관리자 세션 확인
if (!empty($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
    $is_admin = true;
    $reporter_id = intval($_SESSION['ad_admin_id'] ?? 0);
    $reporter_name = $_SESSION['ad_admin_name'] ?? 'admin';
}

// Gnuboard 회원 세션 확인
if ($reporter_id === 0 && !empty($_SESSION['ss_mb_id'])) {
    $mb_id_escaped = mysqli_real_escape_string($connect, $_SESSION['ss_mb_id']);
    $mb_result = mysqli_query($connect, "SELECT mb_no, mb_nick, mb_name FROM g5_member WHERE mb_id='{$mb_id_escaped}' LIMIT 1");
    if ($mb_result && $mb_row = mysqli_fetch_assoc($mb_result)) {
        $reporter_id = (int)$mb_row['mb_no'];
        $reporter_name = $mb_row['mb_nick'] ?: $mb_row['mb_name'] ?: $_SESSION['ss_mb_id'];
    }
}

// --- 유효한 ENUM 값 ---
$valid_content_types = ['review', 'qna', 'community', 'chat', 'comment', 'tip', 'event'];
$valid_reasons = ['spam', 'harassment', 'hate_speech', 'sexual', 'violence', 'misinformation', 'copyright', 'other'];

// --- 자동 숨김 임계치 (동일 콘텐츠 신고 N건 이상 시 자동 비활성화) ---
$auto_hide_threshold = 3;

/**
 * 자동 숨김 처리
 * 동일 콘텐츠에 대한 신고가 임계치 이상이면 원본 콘텐츠를 비활성화
 */
function autoHideContent($connect, $content_type, $content_id, $threshold) {
    // 현재 신고 건수 조회
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_content_reports WHERE content_type = ? AND content_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $content_type, $content_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $report_count = (int)($row['cnt'] ?? 0);

    if ($report_count < $threshold) {
        return false; // 임계치 미달
    }

    // 콘텐츠 유형별 비활성화 쿼리
    $hide_stmt = null;
    switch ($content_type) {
        case 'review':
            $hide_stmt = mysqli_prepare($connect, "UPDATE hospital_reviews SET is_active = 0 WHERE id = ?");
            break;
        case 'qna':
            $hide_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_qna SET is_public = 0 WHERE id = ?");
            break;
        case 'community':
            $hide_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_community_posts SET is_active = 0 WHERE id = ?");
            break;
        case 'chat':
            $hide_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_chat_messages SET is_hidden = 1 WHERE id = ?");
            break;
        default:
            // comment, tip, event 등은 자동 숨김 미지원 (관리자 수동 처리)
            return false;
    }

    if ($hide_stmt) {
        mysqli_stmt_bind_param($hide_stmt, 'i', $content_id);
        $result = mysqli_stmt_execute($hide_stmt);
        mysqli_stmt_close($hide_stmt);
        return $result;
    }

    return false;
}

// --- 라우팅 ---
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// ============================================================
// POST: 신고 접수
// ============================================================
if ($method === 'POST' && $action === '') {
    // 로그인 필수
    if ($reporter_id <= 0) {
        jsonError('로그인이 필요합니다', 401);
    }

    // 필수 파라미터 검증
    $content_type = trim($input['content_type'] ?? '');
    $content_id = intval($input['content_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if (!in_array($content_type, $valid_content_types)) {
        jsonError('유효하지 않은 콘텐츠 유형입니다. 허용: ' . implode(', ', $valid_content_types));
    }
    if ($content_id <= 0) {
        jsonError('content_id는 1 이상의 정수여야 합니다');
    }
    if (!in_array($reason, $valid_reasons)) {
        jsonError('유효하지 않은 신고 사유입니다. 허용: ' . implode(', ', $valid_reasons));
    }

    // 중복 신고 방지: 동일 사용자 + 동일 콘텐츠
    $dup_stmt = mysqli_prepare($connect,
        "SELECT id FROM gangnamunni_content_reports WHERE reporter_id = ? AND content_type = ? AND content_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($dup_stmt, 'isi', $reporter_id, $content_type, $content_id);
    mysqli_stmt_execute($dup_stmt);
    $dup_result = mysqli_stmt_get_result($dup_stmt);
    if (mysqli_num_rows($dup_result) > 0) {
        mysqli_stmt_close($dup_stmt);
        jsonError('이미 신고한 콘텐츠입니다', 409);
    }
    mysqli_stmt_close($dup_stmt);

    // 선택 파라미터
    $reason_detail = trim($input['reason_detail'] ?? '');
    $content_url = trim($input['content_url'] ?? '');
    $content_snippet = trim($input['content_snippet'] ?? '');
    $reported_user_id = !empty($input['reported_user_id']) ? intval($input['reported_user_id']) : null;
    $reported_user_name = trim($input['reported_user_name'] ?? '');

    // content_snippet 길이 제한 (DB VARCHAR 500)
    if (mb_strlen($content_snippet) > 500) {
        $content_snippet = mb_substr($content_snippet, 0, 497) . '...';
    }

    // INSERT
    $ins_stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_content_reports
            (reporter_id, reporter_name, content_type, content_id, content_url, content_snippet,
             reported_user_id, reported_user_name, reason, reason_detail)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($ins_stmt, 'issississs',
        $reporter_id, $reporter_name, $content_type, $content_id,
        $content_url, $content_snippet,
        $reported_user_id, $reported_user_name,
        $reason, $reason_detail
    );

    if (!mysqli_stmt_execute($ins_stmt)) {
        mysqli_stmt_close($ins_stmt);
        jsonError('신고 접수에 실패했습니다: ' . mysqli_error($connect), 500);
    }

    $report_id = mysqli_insert_id($connect);
    mysqli_stmt_close($ins_stmt);

    // 자동 숨김 처리 (임계치 도달 시)
    $auto_hidden = autoHideContent($connect, $content_type, $content_id, $auto_hide_threshold);

    mysqli_close($connect);

    $response = [
        'report_id' => $report_id,
        'message' => '신고가 접수되었습니다'
    ];
    if ($auto_hidden) {
        $response['auto_hidden'] = true;
        $response['message'] = '신고가 접수되었습니다. 다수의 신고로 해당 콘텐츠가 자동 숨김 처리되었습니다.';
    }

    jsonSuccess($response);
}

// ============================================================
// GET ?action=my_reports : 내 신고 이력 조회
// ============================================================
if ($method === 'GET' && $action === 'my_reports') {
    // 로그인 필수
    if ($reporter_id <= 0) {
        jsonError('로그인이 필요합니다', 401);
    }

    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // 총 건수
    $cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_content_reports WHERE reporter_id = ?");
    mysqli_stmt_bind_param($cnt_stmt, 'i', $reporter_id);
    mysqli_stmt_execute($cnt_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['cnt'];
    mysqli_stmt_close($cnt_stmt);

    // 목록 조회
    $list_stmt = mysqli_prepare($connect,
        "SELECT id, content_type, content_id, content_url, content_snippet,
                reported_user_name, reason, reason_detail, status, action_taken, created_at, resolved_at
         FROM gangnamunni_content_reports
         WHERE reporter_id = ?
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    mysqli_stmt_bind_param($list_stmt, 'iii', $reporter_id, $limit, $offset);
    mysqli_stmt_execute($list_stmt);
    $result = mysqli_stmt_get_result($list_stmt);

    $reports = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }
    mysqli_stmt_close($list_stmt);
    mysqli_close($connect);

    jsonSuccess([
        'reports' => $reports,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

// ============================================================
// GET ?action=check&content_type=X&content_id=Y : 이미 신고했는지 확인
// ============================================================
if ($method === 'GET' && $action === 'check') {
    // 로그인 필수
    if ($reporter_id <= 0) {
        jsonError('로그인이 필요합니다', 401);
    }

    $content_type = trim($_GET['content_type'] ?? '');
    $content_id = intval($_GET['content_id'] ?? 0);

    if (!in_array($content_type, $valid_content_types) || $content_id <= 0) {
        jsonError('content_type과 content_id가 필요합니다');
    }

    // 내가 이미 신고했는지 확인
    $chk_stmt = mysqli_prepare($connect,
        "SELECT id FROM gangnamunni_content_reports WHERE reporter_id = ? AND content_type = ? AND content_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($chk_stmt, 'isi', $reporter_id, $content_type, $content_id);
    mysqli_stmt_execute($chk_stmt);
    $reported = mysqli_num_rows(mysqli_stmt_get_result($chk_stmt)) > 0;
    mysqli_stmt_close($chk_stmt);

    // 해당 콘텐츠의 총 신고 건수
    $cnt_stmt = mysqli_prepare($connect,
        "SELECT COUNT(*) as cnt FROM gangnamunni_content_reports WHERE content_type = ? AND content_id = ?"
    );
    mysqli_stmt_bind_param($cnt_stmt, 'si', $content_type, $content_id);
    mysqli_stmt_execute($cnt_stmt);
    $report_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['cnt'];
    mysqli_stmt_close($cnt_stmt);
    mysqli_close($connect);

    jsonSuccess([
        'reported' => $reported,
        'report_count' => $report_count
    ]);
}

// ============================================================
// GET ?action=stats : 신고 통계 (관리자 전용)
// ============================================================
if ($method === 'GET' && $action === 'stats') {
    // 관리자 전용
    if (!$is_admin) {
        jsonError('관리자 권한이 필요합니다', 403);
    }

    // 상태별 건수
    $status_sql = "SELECT
        COUNT(*) as total,
        SUM(status = 'pending') as pending,
        SUM(status = 'investigating') as investigating,
        SUM(status = 'resolved') as resolved,
        SUM(status = 'dismissed') as dismissed
        FROM gangnamunni_content_reports";
    $status_result = mysqli_query($connect, $status_sql);
    $by_status = mysqli_fetch_assoc($status_result);

    // 콘텐츠 유형별 건수
    $type_sql = "SELECT content_type, COUNT(*) as cnt
        FROM gangnamunni_content_reports
        GROUP BY content_type
        ORDER BY cnt DESC";
    $type_result = mysqli_query($connect, $type_sql);
    $by_type = [];
    while ($row = mysqli_fetch_assoc($type_result)) {
        $by_type[$row['content_type']] = (int)$row['cnt'];
    }

    // 신고 사유별 건수
    $reason_sql = "SELECT reason, COUNT(*) as cnt
        FROM gangnamunni_content_reports
        GROUP BY reason
        ORDER BY cnt DESC";
    $reason_result = mysqli_query($connect, $reason_sql);
    $by_reason = [];
    while ($row = mysqli_fetch_assoc($reason_result)) {
        $by_reason[$row['reason']] = (int)$row['cnt'];
    }

    // 24시간 초과 미처리 건수
    $overdue_sql = "SELECT COUNT(*) as cnt
        FROM gangnamunni_content_reports
        WHERE status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $overdue_result = mysqli_query($connect, $overdue_sql);
    $overdue_count = (int)mysqli_fetch_assoc($overdue_result)['cnt'];

    // 최근 7일 일별 신고 추이
    $daily_sql = "SELECT DATE(created_at) as report_date, COUNT(*) as cnt
        FROM gangnamunni_content_reports
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY report_date ASC";
    $daily_result = mysqli_query($connect, $daily_sql);
    $daily_trend = [];
    while ($row = mysqli_fetch_assoc($daily_result)) {
        $daily_trend[] = [
            'date' => $row['report_date'],
            'count' => (int)$row['cnt']
        ];
    }

    mysqli_close($connect);

    jsonSuccess([
        'by_status' => [
            'total' => (int)$by_status['total'],
            'pending' => (int)$by_status['pending'],
            'investigating' => (int)$by_status['investigating'],
            'resolved' => (int)$by_status['resolved'],
            'dismissed' => (int)$by_status['dismissed']
        ],
        'by_content_type' => $by_type,
        'by_reason' => $by_reason,
        'overdue_24h' => $overdue_count,
        'daily_trend_7d' => $daily_trend
    ]);
}

// ============================================================
// 일치하는 라우트 없음
// ============================================================
mysqli_close($connect);
jsonError('잘못된 요청입니다. 지원 엔드포인트: POST(신고접수), GET ?action=my_reports|check|stats');

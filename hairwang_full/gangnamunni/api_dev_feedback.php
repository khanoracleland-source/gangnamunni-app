<?php
/**
 * 개발 피드백 API
 * - GET: 피드백 목록 조회
 * - POST: 피드백 저장/수정
 * - DELETE: 피드백 삭제
 * - GET ?action=prompt: 미처리 피드백 → Claude 프롬프트 생성
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'message' => 'DB 연결 실패']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_dev_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id VARCHAR(100) NOT NULL COMMENT '피드백 대상 페이지 식별자',
    section VARCHAR(200) DEFAULT NULL COMMENT '관련 섹션/항목',
    feedback_type ENUM('bug','improve','feature','question','other') DEFAULT 'improve' COMMENT '유형',
    priority ENUM('urgent','high','medium','low') DEFAULT 'medium',
    content TEXT NOT NULL COMMENT '피드백 내용',
    status ENUM('open','in_progress','done','rejected') DEFAULT 'open',
    dev_note TEXT DEFAULT NULL COMMENT '개발자 메모 (처리 결과)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_page (page_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='개발 피드백 수집'");

$action = $_GET['action'] ?? '';

// ========== GET ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // 프롬프트 생성
    if ($action === 'prompt') {
        $page_filter = isset($_GET['page_id']) ? $_GET['page_id'] : null;
        $status_filter = isset($_GET['status']) ? $_GET['status'] : 'open';

        $sql = "SELECT * FROM gangnamunni_dev_feedback WHERE status = ?";
        $params = [$status_filter];
        $types = 's';

        if ($page_filter) {
            $sql .= " AND page_id = ?";
            $params[] = $page_filter;
            $types .= 's';
        }
        $sql .= " ORDER BY FIELD(priority, 'urgent','high','medium','low'), created_at ASC";

        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
        mysqli_stmt_close($stmt);

        if (empty($items)) {
            echo json_encode(['success' => true, 'prompt' => '', 'count' => 0, 'message' => '처리할 피드백이 없습니다.']);
            exit;
        }

        // 프롬프트 생성
        $type_labels = ['bug' => '버그 수정', 'improve' => '개선', 'feature' => '신규 기능', 'question' => '질문/확인', 'other' => '기타'];
        $priority_labels = ['urgent' => '긴급', 'high' => '높음', 'medium' => '보통', 'low' => '낮음'];

        $prompt = "# 개발 피드백 처리 요청\n\n";
        $prompt .= "> 자동 생성: " . date('Y-m-d H:i') . "\n";
        $prompt .= "> 미처리 피드백: " . count($items) . "건\n\n";
        $prompt .= "아래 피드백을 확인하고 각 항목별로 수정/개발을 진행해 주세요.\n";
        $prompt .= "완료 후 api_dev_feedback.php에 status를 'done'으로 업데이트하고 dev_note에 처리 결과를 기록해 주세요.\n\n";
        $prompt .= "---\n\n";

        foreach ($items as $i => $item) {
            $num = $i + 1;
            $type = $type_labels[$item['feedback_type']] ?? $item['feedback_type'];
            $pri = $priority_labels[$item['priority']] ?? $item['priority'];
            $prompt .= "## [{$num}] [{$pri}] {$type}\n";
            $prompt .= "- **ID**: {$item['id']}\n";
            $prompt .= "- **페이지**: {$item['page_id']}\n";
            if ($item['section']) $prompt .= "- **섹션**: {$item['section']}\n";
            $prompt .= "- **내용**: {$item['content']}\n";
            $prompt .= "- **등록일**: {$item['created_at']}\n\n";
        }

        $prompt .= "---\n\n";
        $prompt .= "## 처리 방법\n";
        $prompt .= "1. 각 항목의 내용을 분석하여 수정/개발 진행\n";
        $prompt .= "2. 서버 배포 (SFTP + php -l 검증)\n";
        $prompt .= "3. 처리 완료 후 API로 상태 업데이트:\n";
        $prompt .= "```\n";
        $prompt .= "POST api_dev_feedback.php\n";
        $prompt .= "{\"id\": <ID>, \"status\": \"done\", \"dev_note\": \"처리 내용\"}\n";
        $prompt .= "```\n";

        echo json_encode(['success' => true, 'prompt' => $prompt, 'count' => count($items), 'items' => $items]);
        exit;
    }

    // 목록 조회
    $page_filter = isset($_GET['page_id']) ? $_GET['page_id'] : null;
    $sql = "SELECT * FROM gangnamunni_dev_feedback";
    if ($page_filter) {
        $stmt = mysqli_prepare($connect, $sql . " WHERE page_id = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($stmt, 's', $page_filter);
    } else {
        $stmt = mysqli_prepare($connect, $sql . " ORDER BY created_at DESC");
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
    exit;
}

// ========== POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) { echo json_encode(['success' => false, 'message' => 'JSON 파싱 실패']); exit; }

    // 기존 피드백 상태 업데이트 (id + status)
    if (!empty($input['id']) && !empty($input['status'])) {
        $id = intval($input['id']);
        $status = $input['status'];
        $dev_note = $input['dev_note'] ?? null;

        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_dev_feedback SET status = ?, dev_note = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $dev_note, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => $ok, 'message' => $ok ? '업데이트 완료' : '업데이트 실패']);
        exit;
    }

    // 새 피드백 저장
    $page_id = trim($input['page_id'] ?? '');
    $content = trim($input['content'] ?? '');
    if (empty($page_id) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'page_id, content 필수']);
        exit;
    }

    $section = $input['section'] ?? null;
    $feedback_type = $input['feedback_type'] ?? 'improve';
    $priority = $input['priority'] ?? 'medium';

    $page_url = trim($input['page_url'] ?? '');
    $page_title = trim($input['page_title'] ?? '');
    $submitted_by = trim($input['submitted_by'] ?? 'anonymous');

    // 스크린샷 저장 (Base64 → 파일)
    $screenshot_url = null;
    $screenshot_data = $input['screenshot'] ?? null;
    if ($screenshot_data && strpos($screenshot_data, 'data:image/') === 0) {
        $upload_dir = __DIR__ . '/uploads/feedback/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        // Base64 디코딩
        $parts = explode(',', $screenshot_data, 2);
        $img_data = base64_decode($parts[1] ?? '');
        if ($img_data && strlen($img_data) > 100) {
            $ext = (strpos($parts[0], 'png') !== false) ? 'png' : 'jpg';
            $fname = 'fb_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            if (file_put_contents($upload_dir . $fname, $img_data)) {
                $screenshot_url = 'uploads/feedback/' . $fname;
            }
        }
    }

    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_dev_feedback (page_id, page_url, page_title, section, feedback_type, priority, content, screenshot_url, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sssssssss', $page_id, $page_url, $page_title, $section, $feedback_type, $priority, $content, $screenshot_url, $submitted_by);
    $ok = mysqli_stmt_execute($stmt);
    $new_id = mysqli_insert_id($connect);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'id' => $new_id, 'screenshot_url' => $screenshot_url, 'message' => $ok ? '피드백 저장 완료' : '저장 실패']);
    exit;
}

// ========== DELETE ==========
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'id 필수']); exit; }

    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_dev_feedback WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? '삭제 완료' : '삭제 실패']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);

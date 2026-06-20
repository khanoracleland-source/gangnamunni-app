<?php
/**
 * 매뉴얼 피드백 상태/진행노트/내용 업데이트 API
 * POST /api_manual_feedback_update.php
 * 관리자 로그인 필요
 * @created 2026-04-08
 * @updated 2026-04-09 내용 편집 필드 추가
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인 필요']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'error'=>'POST only']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$id         = intval($body['id'] ?? 0);
$handled_by = $_SESSION['ad_admin_user'] ?? 'admin';

if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'id 없음']); exit; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'error'=>'DB 오류']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 기존 상태 조회
$r = mysqli_query($connect, "SELECT status FROM manual_feedback_items WHERE id=" . intval($id));
$row = mysqli_fetch_assoc($r);
if (!$row) { echo json_encode(['success'=>false,'error'=>'해당 항목 없음']); exit; }
$old_status = $row['status'];

// 허용 enum 값
$allowed_status   = ['requested','prompt_ready','in_progress','done','hold','rejected'];
$allowed_priority = ['low','normal','high','urgent'];
$allowed_type     = ['bug_fix','feature_add','content_edit','update','other'];
$allowed_role     = ['client','developer','manager','other'];

$sets  = ['updated_at = NOW()'];
$types = '';
$vals  = [];
$new_status  = '';
$content_edited = false;

// progress_note
if (array_key_exists('progress_note', $body)) {
    $sets[] = 'progress_note = ?';
    $types .= 's';
    $vals[] = trim($body['progress_note']);
}

// status
if (!empty($body['status'])) {
    $ns = trim($body['status']);
    if (!in_array($ns, $allowed_status)) {
        echo json_encode(['success'=>false,'error'=>'잘못된 상태값']); exit;
    }
    $new_status = $ns;
    $sets[]  = 'status = ?';
    $types  .= 's';
    $vals[]  = $new_status;
    if (in_array($new_status, ['done','rejected'])) {
        $sets[]  = 'handled_by = ?';
        $sets[]  = 'handled_at = NOW()';
        $types  .= 's';
        $vals[]  = $handled_by;
    }
}

// request_title
if (array_key_exists('request_title', $body)) {
    $sets[] = 'request_title = ?';
    $types .= 's';
    $vals[] = trim($body['request_title']);
    $content_edited = true;
}

// request_text
if (array_key_exists('request_text', $body)) {
    $sets[] = 'request_text = ?';
    $types .= 's';
    $vals[] = trim($body['request_text']);
    $content_edited = true;
}

// priority
if (!empty($body['priority'])) {
    $pv = trim($body['priority']);
    if (in_array($pv, $allowed_priority)) {
        $sets[] = 'priority = ?';
        $types .= 's';
        $vals[] = $pv;
        $content_edited = true;
    }
}

// request_type
if (!empty($body['request_type'])) {
    $tv = trim($body['request_type']);
    if (in_array($tv, $allowed_type)) {
        $sets[] = 'request_type = ?';
        $types .= 's';
        $vals[] = $tv;
        $content_edited = true;
    }
}

// created_by_name
if (array_key_exists('created_by_name', $body)) {
    $sets[] = 'created_by_name = ?';
    $types .= 's';
    $vals[] = trim($body['created_by_name']);
    $content_edited = true;
}

// created_by_role
if (!empty($body['created_by_role'])) {
    $rv = trim($body['created_by_role']);
    if (in_array($rv, $allowed_role)) {
        $sets[] = 'created_by_role = ?';
        $types .= 's';
        $vals[] = $rv;
        $content_edited = true;
    }
}

// 소스 위치 필드들
foreach (['page_url','page_title','page_path','section_title','menu_label','source_location_desc'] as $f) {
    if (array_key_exists($f, $body)) {
        $sets[] = "$f = ?";
        $types .= 's';
        $vals[] = trim($body[$f]);
        $content_edited = true;
    }
}

if (count($sets) === 1) {
    echo json_encode(['success'=>false,'error'=>'변경 필드 없음']); exit;
}

$sql   = "UPDATE manual_feedback_items SET " . implode(', ', $sets) . " WHERE id = ?";
$types .= 'i';
$vals[] = $id;

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$vals);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// 상태 변경 로그
if ($ok && !empty($new_status) && $new_status !== $old_status) {
    $log_text = "상태 변경: {$old_status} → {$new_status}";
    $ls = mysqli_prepare($connect,
        "INSERT INTO manual_feedback_logs (feedback_item_id, action_type, old_status, new_status, log_text, created_by)
         VALUES (?, 'status_change', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($ls, 'issss', $id, $old_status, $new_status, $log_text, $handled_by);
    mysqli_stmt_execute($ls);
    mysqli_stmt_close($ls);
}

// 내용 편집 로그
if ($ok && $content_edited) {
    $log_text = '내용 수정';
    $ls = mysqli_prepare($connect,
        "INSERT INTO manual_feedback_logs (feedback_item_id, action_type, log_text, created_by)
         VALUES (?, 'edit', ?, ?)");
    mysqli_stmt_bind_param($ls, 'iss', $id, $log_text, $handled_by);
    mysqli_stmt_execute($ls);
    mysqli_stmt_close($ls);
}

mysqli_close($connect);
echo json_encode(['success' => $ok]);

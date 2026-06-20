<?php
/**
 * 매뉴얼 피드백 상태/진행노트 업데이트 API
 * POST /api/manual_feedback_update.php
 * 관리자 로그인 필요
 * @created 2026-04-08
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

$id           = intval($body['id'] ?? 0);
$progress     = trim($body['progress_note'] ?? '');
$new_status   = trim($body['status']        ?? '');
$handled_by   = $_SESSION['ad_admin_user']  ?? 'admin';

$allowed_status = ['requested','prompt_ready','in_progress','done','hold','rejected'];
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'id 없음']); exit; }
if (!empty($new_status) && !in_array($new_status, $allowed_status)) {
    echo json_encode(['success'=>false,'error'=>'잘못된 상태값']); exit;
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'error'=>'DB 오류']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 기존 상태 조회
$r = mysqli_query($connect, "SELECT status FROM manual_feedback_items WHERE id=$id");
$row = mysqli_fetch_assoc($r);
if (!$row) { echo json_encode(['success'=>false,'error'=>'해당 항목 없음']); exit; }
$old_status = $row['status'];

// 업데이트
$sets  = ['progress_note = ?', 'updated_at = NOW()'];
$types = 's';
$vals  = [$progress];

if (!empty($new_status)) {
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

$sql  = "UPDATE manual_feedback_items SET " . implode(', ', $sets) . " WHERE id = ?";
$types .= 'i';
$vals[] = $id;

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$vals);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// 로그 기록
if ($ok && !empty($new_status) && $new_status !== $old_status) {
    $log_text = "상태 변경: {$old_status} → {$new_status}";
    $ls = mysqli_prepare($connect,
        "INSERT INTO manual_feedback_logs (feedback_item_id, action_type, old_status, new_status, log_text, created_by)
         VALUES (?, 'status_change', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($ls, 'issss', $id, $old_status, $new_status, $log_text, $handled_by);
    mysqli_stmt_execute($ls);
    mysqli_stmt_close($ls);
}

mysqli_close($connect);
echo json_encode(['success' => $ok]);

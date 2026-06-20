<?php
/**
 * 매뉴얼 피드백 등록 API
 * POST /api/manual_feedback_create.php
 * 로그인 불필요 (공개 API)
 * @created 2026-04-08
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/includes/init.php';

// ─── rate limit: 같은 IP 5분 내 10건 ───
$rate_key = 'mf_rate_' . preg_replace('/[^0-9a-f:]/', '', $_SERVER['REMOTE_ADDR'] ?? '');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION[$rate_key])) $_SESSION[$rate_key] = 0;
$_SESSION[$rate_key]++;
if ($_SESSION[$rate_key] > 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => '잠시 후 다시 시도해주세요.']);
    exit;
}

// ─── honeypot ───
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!empty($body['website'])) {  // hidden honeypot field
    echo json_encode(['success' => true, 'id' => 0]); exit;
}

// ─── 입력 검증 ───
$name  = trim($body['created_by_name'] ?? '');
$title = trim($body['request_title']   ?? '');
$text  = trim($body['request_text']    ?? '');

if (empty($name))  { echo json_encode(['success'=>false,'error'=>'작성자명은 필수입니다.']); exit; }
if (empty($title)) { echo json_encode(['success'=>false,'error'=>'요청 제목은 필수입니다.']); exit; }
if (empty($text))  { echo json_encode(['success'=>false,'error'=>'요청 내용은 필수입니다.']); exit; }

// ─── 허용값 필터 ───
$allowed_roles  = ['client','developer','manager','other'];
$allowed_types  = ['bug_fix','feature_add','content_edit','update','other'];
$allowed_prio   = ['low','normal','high','urgent'];

$role     = in_array($body['created_by_role'] ?? '', $allowed_roles) ? $body['created_by_role'] : 'client';
$req_type = in_array($body['request_type']    ?? '', $allowed_types) ? $body['request_type']    : 'other';
$priority = in_array($body['priority']        ?? '', $allowed_prio)  ? $body['priority']        : 'normal';

// 문자열 필드 — XSS 방어
function mf_clean($v, $max = 500) {
    return mb_substr(htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'), 0, $max);
}

$page_url    = mf_clean($body['page_url']             ?? '', 1000);
$page_title  = mf_clean($body['page_title']           ?? '');
$page_path   = mf_clean($body['page_path']            ?? '');
$section_ttl = mf_clean($body['section_title']        ?? '');
$menu_label  = mf_clean($body['menu_label']           ?? '');
$loc_desc    = mf_clean($body['source_location_desc'] ?? '', 2000);

// ─── DB 저장 ───
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'error'=>'DB 오류']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$sql = "INSERT INTO manual_feedback_items
    (created_by_name, created_by_role, request_type, priority,
     request_title, request_text,
     page_url, page_title, page_path, section_title, menu_label, source_location_desc)
    VALUES (?,?,?,?, ?,?, ?,?,?,?,?,?)";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, 'ssssss ssssss',
    $name, $role, $req_type, $priority,
    $title, $text,
    $page_url, $page_title, $page_path, $section_ttl, $menu_label, $loc_desc
);
$ok = mysqli_stmt_execute($stmt);
$new_id = mysqli_insert_id($connect);
mysqli_stmt_close($stmt);
mysqli_close($connect);

if ($ok) {
    echo json_encode(['success' => true, 'id' => $new_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 저장 실패']);
}

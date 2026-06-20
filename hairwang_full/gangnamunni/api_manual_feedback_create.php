<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
/**
 * 매뉴얼 피드백 등록 API (FormData 기반, 이미지 업로드 지원)
 * POST /api_manual_feedback_create.php
 * 로그인 불필요 (공개 API)
 * @updated 2026-04-09 v2
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']); exit;
}

// ─── 직접 DB 연결 (Gnuboard init.php 미사용 — 출력 간섭 방지) ───
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, 'hms_saas', 3306);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']); exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// ─── rate limit (세션 기반) ───
if (session_status() === PHP_SESSION_NONE) session_start();
$rate_key = 'mf_rate_' . preg_replace('/[^0-9a-f:.]/', '', $_SERVER['REMOTE_ADDR'] ?? '');
if (!isset($_SESSION[$rate_key])) $_SESSION[$rate_key] = 0;
$_SESSION[$rate_key]++;
if ($_SESSION[$rate_key] > 10) {
    mysqli_close($connect);
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => '잠시 후 다시 시도해주세요.']);
    exit;
}

// ─── 입력 정제 함수 ───
function mf_clean($v, $max = 500) {
    return mb_substr(htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'), 0, $max);
}

// ─── FormData 필드 읽기 ───
$content  = mf_clean($_POST['content'] ?? '', 5000);
$req_type = in_array($_POST['request_type'] ?? '', ['bug_fix', 'feature_add', 'content_edit', 'update', 'other'])
            ? $_POST['request_type'] : 'other';
$priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high', 'urgent'])
            ? $_POST['priority'] : 'normal';

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => '요청 내용은 필수입니다.']); exit;
}

// ─── 제목: title 필드 우선, 없으면 첫 줄 추출 ───
$title = mf_clean($_POST['title'] ?? '', 100);
if (empty($title)) {
    $lines = explode("\n", $content);
    $title = trim($lines[0]);
    if (mb_strlen($title) > 100) $title = mb_substr($title, 0, 97) . '...';
}
if (empty($title)) $title = '수정 요청';

// ─── 자동 채워지는 필드 ───
$name      = '익명';
$role      = 'other';
$page_url  = mf_clean($_POST['page_url']      ?? '', 1000);
$page_title= mf_clean($_POST['page_title']    ?? '');
$page_path = mf_clean($_POST['page_path']     ?? '');
$section   = mf_clean($_POST['section_title'] ?? '');
$loc_desc  = mf_clean($_POST['page_url']      ?? '', 2000);
$menu      = '';

// ─── 다중 이미지 업로드 처리 ───
$image_path = '';
$upload_dir = __DIR__ . '/uploads/feedback/';
if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$saved_paths = [];

// images[] 배열 방식
if (!empty($_FILES['images']['tmp_name'])) {
    $files = $_FILES['images'];
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
    for ($i = 0; $i < $count; $i++) {
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
        $sz   = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
        $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        if ($err !== 0 || empty($tmp)) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext) || $sz > 5 * 1024 * 1024) continue;
        $fname = 'mf_' . time() . '_' . mt_rand(1000, 9999) . '_' . $i . '.' . $ext;
        if (@move_uploaded_file($tmp, $upload_dir . $fname)) {
            $saved_paths[] = 'uploads/feedback/' . $fname;
        }
    }
}
// 단일 image (하위 호환)
if (empty($saved_paths) && !empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed_ext) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
        $fname = 'mf_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        if (@move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $fname)) {
            $saved_paths[] = 'uploads/feedback/' . $fname;
        }
    }
}
$image_path = count($saved_paths) === 1 ? $saved_paths[0] : (count($saved_paths) > 1 ? json_encode($saved_paths) : '');

// ─── DB 저장 ───
$sql = "INSERT INTO manual_feedback_items
    (created_by_name, created_by_role, request_type, priority,
     request_title, request_text,
     page_url, page_title, page_path, section_title, menu_label, source_location_desc, image_path)
    VALUES (?,?,?,?, ?,?, ?,?,?,?,?,?,?)";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, 'sssssssssssss',
    $name, $role, $req_type, $priority,
    $title, $content,
    $page_url, $page_title, $page_path, $section, $menu, $loc_desc, $image_path
);
$ok     = mysqli_stmt_execute($stmt);
$new_id = mysqli_insert_id($connect);
mysqli_stmt_close($stmt);
mysqli_close($connect);

if ($ok) {
    echo json_encode(['success' => true, 'id' => $new_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 저장 실패']);
}

<?php
/**
 * 병원 입점 신청 서류(사업자등록증·의료기관개설허가증) 열람 — 관리자 전용
 *
 * 서류 파일은 uploads/hospital_apply/ 아래에 저장되지만 직접 URL 접근은 .htaccess 로 막혀 있다.
 * 관리자 화면(admin_hospitals.php)은 이 스크립트를 통해서만 서류를 연다.
 *
 * GET hospital_id=<int>&doc=business|medical[&download=1]
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

function hospital_document_fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit($msg);
}

if (!check_admin_login() || !check_permission('hospitals')) {
    hospital_document_fail(403, '접근 권한이 없습니다.');
}

$hospital_id = (int) ($_GET['hospital_id'] ?? 0);
$doc = (string) ($_GET['doc'] ?? '');
$columns = ['business' => 'cert_business', 'medical' => 'cert_medical'];
if ($hospital_id <= 0 || !isset($columns[$doc])) {
    hospital_document_fail(400, '잘못된 요청입니다.');
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) hospital_document_fail(500, 'DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');

// admin_hospitals.php 와 같은 병원 범위 규칙
$my_ids = get_hospital_filter_ids($connect);
if (($_SESSION['ad_admin_role'] ?? '') === 'editor' && $my_ids === null) {
    $ed_hid = (int) ($_SESSION['ad_admin_hospital_id'] ?? 0);
    $my_ids = $ed_hid > 0 ? [$ed_hid] : [];
}
if ($my_ids !== null && !in_array($hospital_id, $my_ids, true)) {
    hospital_document_fail(403, '접근 권한이 없습니다.');
}

$column = $columns[$doc];
$stmt = mysqli_prepare($connect, "SELECT {$column} AS path FROM gangnamunni_hospitals WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($connect);

$rel = trim((string) ($row['path'] ?? ''));
if ($rel === '') hospital_document_fail(404, '제출된 서류가 없습니다.');

// 저장 경로 검증: uploads/hospital_apply/<디렉토리>/cert_(business|medical)_*.{jpg,jpeg,png,pdf} 만 허용
if (!preg_match('#^uploads/hospital_apply/[A-Za-z0-9_\-]+/(cert_(?:business|medical)_[A-Za-z0-9]+\.(jpg|jpeg|png|pdf))$#', $rel, $m)) {
    hospital_document_fail(404, '서류 경로가 올바르지 않습니다.');
}
$base = realpath(__DIR__ . '/uploads/hospital_apply');
$file = realpath(__DIR__ . '/' . $rel);
if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    hospital_document_fail(404, '서류 파일을 찾을 수 없습니다.');
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . $types[strtolower($m[2])]);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: ' . $disposition . '; filename="' . $m[1] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($file);

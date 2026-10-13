<?php
/**
 * 상담 첨부 사진 열람 — 관리자 전용 (병원 범위 검사)
 *
 * 사진 파일은 uploads/consultations/<상담ID>/ 아래에 저장되지만 직접 URL 접근은
 * uploads/.htaccess 로 막혀 있다. admin_consultations.php 는 이 스크립트를 통해서만 사진을 연다.
 *
 * GET id=<상담 ID>&i=<photo_urls 인덱스>
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

function consultation_photo_fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit($msg);
}

if (!check_admin_login() || !check_permission('consultations')) {
    consultation_photo_fail(403, '접근 권한이 없습니다.');
}

$id = (int) ($_GET['id'] ?? 0);
$index = filter_var($_GET['i'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9]]);
if ($id <= 0 || $index === false) {
    consultation_photo_fail(400, '잘못된 요청입니다.');
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) consultation_photo_fail(500, 'DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');

// admin_consultations.php 와 같은 병원 범위 규칙 (super_admin 만 전체, 그 외 소속 병원만)
$allowed_ids = get_strict_admin_hospital_ids($connect);

$stmt = mysqli_prepare($connect, "SELECT hospital_id, photo_urls FROM gangnamunni_consultations WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($connect);

// 다른 병원 상담이면 존재 여부를 드러내지 않도록 404 와 같은 응답
if (!$row || ($allowed_ids !== null && !in_array((int) $row['hospital_id'], $allowed_ids, true))) {
    consultation_photo_fail(404, '사진을 찾을 수 없습니다.');
}

$photos = json_decode((string) ($row['photo_urls'] ?? ''), true);
$rel = is_array($photos) ? (string) (array_values($photos)[$index] ?? '') : '';

// 저장 경로 검증: 반드시 이 상담의 디렉토리 안 파일만
if (!preg_match('#^uploads/consultations/' . $id . '/([A-Za-z0-9_\-]+\.(jpg|jpeg|png|gif|webp))$#', $rel, $m)) {
    consultation_photo_fail(404, '사진을 찾을 수 없습니다.');
}
$base = realpath(__DIR__ . '/uploads/consultations/' . $id);
$file = realpath(__DIR__ . '/' . $rel);
if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    consultation_photo_fail(404, '사진을 찾을 수 없습니다.');
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[strtolower($m[2])]);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $m[1] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($file);

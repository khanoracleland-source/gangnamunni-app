<?php
/**
 * 경과 후기 사진 열람 (#포인트제도 v2)
 *
 * 사진 파일은 uploads/progress/<entry_id>/ 아래에 저장되지만 직접 URL 접근은
 * uploads/.htaccess 로 막혀 있다. 이 스크립트만 사진을 제공한다.
 *
 *   GET entry=<경과 기록 ID>&i=<photo_urls 인덱스>            → 작성자 본인 (사용자 화면 progress_review.php)
 *   GET for=admin&entry=<경과 기록 ID>&i=<photo_urls 인덱스>  → 관리자 (reviews 권한 + 병원 범위, admin_progress_reviews.php)
 *
 * 관리자 세션과 사용자(그누보드) 세션은 부트스트랩이 달라 분기별로 각각만 로드한다.
 */

function progress_photo_fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit($msg);
}

$entry_id = (int) ($_GET['entry'] ?? 0);
$index = filter_var($_GET['i'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9]]);
if ($entry_id <= 0 || $index === false) {
    progress_photo_fail(400, '잘못된 요청입니다.');
}

$for_admin = (($_GET['for'] ?? '') === 'admin');

if ($for_admin) {
    session_start();
    require_once __DIR__ . '/../dbconfig.php';
    require_once __DIR__ . '/includes/admin_auth.php';
    require_once __DIR__ . '/includes/hospital_filter.php';
    if (!check_admin_login() || !check_permission('reviews')) {
        progress_photo_fail(403, '접근 권한이 없습니다.');
    }
    $connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if (!$connect) progress_photo_fail(500, 'DB 연결 실패');
    mysqli_set_charset($connect, 'utf8mb4');
    // admin_progress_reviews.php 와 같은 병원 범위 규칙
    $allowed_hospital_ids = get_strict_admin_hospital_ids($connect);
    $member_no = null;
} else {
    require_once __DIR__ . '/includes/init.php';
    require_once __DIR__ . '/includes/points_ledger.php';
    if (!is_gangnamunni_logged_in() || !isset($connect) || !$connect) {
        progress_photo_fail(403, '로그인이 필요합니다.');
    }
    $m = get_gangnamunni_member();
    $member_no = (int) points_member_no($connect, (string) ($m['mb_id'] ?? ''));
    if ($member_no <= 0) progress_photo_fail(403, '접근 권한이 없습니다.');
    $allowed_hospital_ids = null;
}

$stmt = mysqli_prepare($connect,
    "SELECT e.photo_urls, p.hospital_id, p.member_id, p.status
       FROM gangnamunni_progress_entries e
       JOIN gangnamunni_progress_reviews p ON p.id = e.progress_id
      WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $entry_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// 권한 없는 경우 존재 여부를 드러내지 않도록 404 와 같은 응답
$allowed = false;
if ($row) {
    if ($for_admin) {
        $allowed = $allowed_hospital_ids === null
            || in_array((int) $row['hospital_id'], $allowed_hospital_ids, true);
    } else {
        $allowed = (int) $row['member_id'] === $member_no && $row['status'] !== 'deleted';
    }
}
if (!$allowed) progress_photo_fail(404, '사진을 찾을 수 없습니다.');

$photos = json_decode((string) ($row['photo_urls'] ?? ''), true);
$rel = is_array($photos) ? (string) (array_values($photos)[$index] ?? '') : '';

// 저장 경로 검증: 반드시 이 경과 기록의 디렉토리 안 파일만
if (!preg_match('#^uploads/progress/' . $entry_id . '/([A-Za-z0-9_\-]+\.(jpg|jpeg|png|gif|webp))$#', $rel, $mm)) {
    progress_photo_fail(404, '사진을 찾을 수 없습니다.');
}
$base = realpath(__DIR__ . '/uploads/progress/' . $entry_id);
$file = realpath(__DIR__ . '/' . $rel);
if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    progress_photo_fail(404, '사진을 찾을 수 없습니다.');
}

// 실제 내용으로 MIME 결정 (업로드 시 클라이언트 MIME 만 검사하므로 확장자를 신뢰하지 않음)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? (string) finfo_file($finfo, $file) : '';
if ($finfo) finfo_close($finfo);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    progress_photo_fail(404, '사진을 찾을 수 없습니다.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $mm[1] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($file);

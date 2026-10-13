<?php
/**
 * 광고 배너 페이지별 이미지 헬퍼
 *
 *  - 메인(index.php)      : gangnamunni_ads.image_url
 *  - 마이페이지(mypage.php): gangnamunni_ads.mypage_image_url (비어 있으면 image_url 사용)
 *
 * 이미지는 admin_ads.php 의 광고 추가/수정 폼에서 관리한다.
 */

/** mypage_image_url 컬럼이 없으면 추가한다 (sql/ad_mypage_image.sql 과 동일). */
function ad_banner_ensure_schema($connect) {
    static $done = false;
    if ($done) return true;
    $r = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_ads LIKE 'mypage_image_url'");
    if ($r && mysqli_num_rows($r) > 0) { $done = true; return true; }
    $ok = (bool)mysqli_query($connect, "ALTER TABLE gangnamunni_ads ADD COLUMN mypage_image_url VARCHAR(500) DEFAULT NULL AFTER image_url");
    if ($ok) $done = true;
    return $ok;
}

/** 페이지에 맞는 배너 이미지 경로 (mypage 는 전용 이미지가 없으면 메인 이미지로 대체). */
function ad_banner_image_for_page(array $ad, $page = 'index') {
    if ($page === 'mypage' && !empty($ad['mypage_image_url'])) return (string)$ad['mypage_image_url'];
    return (string)($ad['image_url'] ?? '');
}

/**
 * 더 이상 어떤 광고도 참조하지 않는 uploads/ads/ 파일을 삭제한다.
 * 외부 URL 이나 다른 폴더의 파일은 건드리지 않는다.
 */
function ad_banner_delete_if_unused($connect, $path) {
    $path = (string)$path;
    if (!preg_match('#^uploads/ads/[A-Za-z0-9_.-]+$#', $path)) return;
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_ads WHERE image_url = ? OR mypage_image_url = ?");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'ss', $path, $path);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $cnt);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ((int)$cnt > 0) return;
    $full = dirname(__DIR__) . '/' . $path;
    if (is_file($full)) @unlink($full);
}

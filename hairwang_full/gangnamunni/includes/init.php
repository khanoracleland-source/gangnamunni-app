<?php
/**
 * ========================================
 * 강남언니 초기화 파일
 * ========================================
 *
 * 모든 페이지에서 가장 먼저 include 해야 합니다.
 * 그누보드 common.php를 로드하여 세션, 로그인 정보를 공유합니다.
 *
 * @file    gangnamunni/includes/init.php
 * @created 2026-02-06
 */

// 그누보드 common.php 로드 (세션, 로그인, DB 연결)
// rb_core.extend.php의 QUERY_STRING undefined warning 방지
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$g5_common_path = dirname(__DIR__, 2) . '/common.php';
if (file_exists($g5_common_path) && !defined('G5_PATH')) {
    @include_once $g5_common_path;
}


// 강남언니 설정 로드
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/theme_helper.php';
require_once dirname(__DIR__) . '/category_icons.php';
require_once __DIR__ . '/figma_block_helper.php';
if (!function_exists('term')) { require_once dirname(__DIR__) . '/includes/industry_terms.php'; }

// MySQL 연결 (그누보드에서 이미 연결되어 있으면 재사용)
if (!isset($connect) || !$connect) {
    if (defined('G5_MYSQL_HOST')) {
        $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
        if ($connect) {
            mysqli_set_charset($connect, 'utf8mb4');
        }
    }
}

// [버그수정 2026-06-15] SSO 자동로그인 — $connect 생성 후 실행해야 동작함 (자동실행 블록이 $connect 필요)
//   누락/순서오류 시 로그인 상태인데도 $member 미설정 → my_bookings 등에서 비로그인으로 인식되던 버그
if (isset($connect) && $connect && file_exists(__DIR__ . '/sso_auto_login.php')) {
    @include_once __DIR__ . '/sso_auto_login.php';
}

// 테마 설정 로드
if (isset($connect) && $connect) {
    loadThemeSettings($connect);
}

// 로그인 상태 체크 헬퍼 함수
function is_gangnamunni_logged_in() {
    global $is_member, $member;
    return isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
}

// 로그인 회원 정보 가져오기
function get_gangnamunni_member() {
    global $member;
    return $member ?? [];
}

// 로그인 회원 ID (mb_id) 가져오기
function get_gangnamunni_member_id() {
    global $member;
    return (isset($member) && isset($member['mb_id'])) ? $member['mb_id'] : null;
}

// 로그인 필요 페이지용 리다이렉트
function require_login($redirect_url = null) {
    if (!is_gangnamunni_logged_in()) {
        $login_url = 'auth/login.php';
        if ($redirect_url) {
            $login_url .= '?url=' . urlencode($redirect_url);
        }
        header('Location: ' . $login_url);
        exit;
    }
}


// 일반화된 함수명 alias
function is_platform_logged_in() { return is_gangnamunni_logged_in(); }
function get_platform_member() { return get_gangnamunni_member(); }
function get_platform_member_id() { return get_gangnamunni_member_id(); }

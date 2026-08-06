<?php
/**
 * SSO Auto-Login for WebView pages
 * Phase5: 앱 WebView에서 SSO 토큰으로 자동 로그인
 *
 * 사용법: 각 페이지에서 Gnuboard common.php 로드 후, 로그인 체크 전에 include
 *   include_once __DIR__ . '/includes/sso_auto_login.php';
 *
 * 동작:
 * 1. 이미 Gnuboard 세션 로그인 상태면 아무것도 안 함
 * 2. sb_sso_token Secure 쿠키 또는 Authorization Bearer 헤더 확인
 * 3. sso_tokens 테이블에서 유효한 토큰 조회
 * 4. g5_member 정보로 Gnuboard 세션 변수 설정 (init.php 사용하지 않음)
 *
 * @created 2026-03-22
 */

function sso_try_auto_login($connect) {
    global $is_member, $member;

    // 이미 로그인되어 있으면 스킵
    if (isset($is_member) && $is_member && !empty($member['mb_id'])) {
        return true;
    }

    // SSO 토큰은 URL query string에서 절대 읽지 않는다.
    // URL은 access log, browser history, analytics, Referer를 통해 누출될 수 있다.
    // WebView와 브라우저는 HttpOnly 쿠키, API 클라이언트는 Bearer 헤더를 사용한다.
    $sso_token = $_COOKIE['sb_sso_token'] ?? '';
    if (empty($sso_token)) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', trim($auth), $m)) {
            $sso_token = $m[1];
        }
    }

    // 발급되는 SSO 토큰은 random_bytes(32)의 64자 hex 문자열이어야 한다.
    if (!is_string($sso_token) || !preg_match('/^[A-Fa-f0-9]{64}$/', $sso_token)) {
        return false;
    }

    // DB에서 토큰 확인
    $stmt = mysqli_prepare($connect, "SELECT g5_mb_id, expires_at FROM sso_tokens WHERE token = ? LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 's', $sso_token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sso = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$sso || empty($sso['g5_mb_id'])) {
        return false;
    }

    // 만료 확인
    if (strtotime($sso['expires_at']) < time()) {
        return false;
    }

    // g5_member 조회
    $mb_id = $sso['g5_mb_id'];
    $member_table = isset($GLOBALS['g5']['member_table']) ? $GLOBALS['g5']['member_table'] : 'g5_member';
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM {$member_table} WHERE mb_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $mb_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $mb = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$mb
        || (!empty($mb['mb_leave_date']) && $mb['mb_leave_date'] <= date('Ymd'))
        || (!empty($mb['mb_intercept_date']) && $mb['mb_intercept_date'] <= date('Ymd'))
        || (function_exists('is_use_email_certify')
            && is_use_email_certify()
            && !preg_match('/[1-9]/', $mb['mb_email_certify'] ?? ''))) {
        return false;
    }

    // 인증 전·후 같은 session id를 재사용하지 않도록 fixation을 차단한다.
    if (!session_id()) @session_start();
    if (!session_regenerate_id(true)) return false;

    if (function_exists('set_session')) {
        set_session('ss_mb_id', $mb['mb_id']);
    } else {
        $_SESSION['ss_mb_id'] = $mb['mb_id'];
    }
    if (function_exists('generate_mb_key')) {
        generate_mb_key($mb);
    }
    if (function_exists('update_auth_session_token')) {
        update_auth_session_token($mb['mb_datetime']);
    }

    // Gnuboard 글로벌 변수 설정
    $is_member = true;
    $member = $mb;

    return true;
}

// 자동 실행: $connect가 이미 있으면 바로 시도
if (isset($connect) && $connect) {
    sso_try_auto_login($connect);
}

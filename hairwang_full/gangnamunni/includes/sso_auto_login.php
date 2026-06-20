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
 * 2. sb_sso_token 쿠키 또는 ?sso_token= URL 파라미터 확인
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

    // SSO 토큰 확인: 쿠키 > URL 파라미터 > Authorization 헤더
    $sso_token = $_COOKIE['sb_sso_token'] ?? '';
    if (empty($sso_token)) {
        $sso_token = $_GET['sso_token'] ?? $_GET['token'] ?? '';
    }
    if (empty($sso_token)) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            $sso_token = $m[1];
        }
    }

    if (empty($sso_token)) {
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
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM g5_member WHERE mb_id = ? AND mb_leave_date = '' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $mb_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $mb = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$mb) {
        return false;
    }

    // Gnuboard 세션 변수 설정 (common.php가 이미 세션을 시작했을 수 있음)
    if (!session_id()) @session_start();

    $_SESSION['ss_mb_id'] = $mb['mb_id'];
    $_SESSION['ss_mb_key'] = md5($mb['mb_datetime'] . $mb['mb_ip'] . $mb['mb_id']);

    // Gnuboard 글로벌 변수 설정
    $is_member = true;
    $member = $mb;

    return true;
}

// 자동 실행: $connect가 이미 있으면 바로 시도
if (isset($connect) && $connect) {
    sso_try_auto_login($connect);
}

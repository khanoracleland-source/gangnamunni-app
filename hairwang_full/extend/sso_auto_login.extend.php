<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * SSO 자동 로그인 (Gnuboard 측)
 * sb_sso_token 쿠키로 다른 시스템에서 로그인한 사용자 자동 인식
 *
 * @created 2026-02-11
 */

// 로그아웃 시 SSO 토큰 삭제
add_event('member_logout', '_sso_on_gnuboard_logout', 10, 1);

// 아직 로그인되지 않은 경우 SSO 토큰으로 자동 로그인
if (!get_session('ss_mb_id')) {
    _sso_try_auto_login();
}

/**
 * SSO 자동 로그인 시도
 */
function _sso_try_auto_login() {
    global $connect;

    $token = $_COOKIE['sb_sso_token'] ?? '';
    if (!is_string($token) || !preg_match('/^[A-Fa-f0-9]{64}$/', $token)) return;
    if (!$connect) return;

    // 토큰 검증
    $stmt = mysqli_prepare($connect, "SELECT * FROM sso_tokens WHERE token = ? AND expires_at > NOW()");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sso = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$sso || empty($sso['g5_mb_id'])) return;

    // 회원 조회
    $mb = get_member($sso['g5_mb_id']);
    if (!$mb || empty($mb['mb_id'])) return;

    // 탈퇴/차단/이메일 미인증 회원은 일반 로그인과 동일하게 제외한다.
    if ((!empty($mb['mb_leave_date']) && $mb['mb_leave_date'] <= date('Ymd'))
        || (!empty($mb['mb_intercept_date']) && $mb['mb_intercept_date'] <= date('Ymd'))
        || (function_exists('is_use_email_certify')
            && is_use_email_certify()
            && !preg_match('/[1-9]/', $mb['mb_email_certify'] ?? ''))) return;

    // 인증 전의 session id를 폐기해 session fixation을 차단한다.
    if (!session_regenerate_id(true)) return;

    // 자동 로그인 세션 설정
    set_session('ss_mb_id', $mb['mb_id']);
    generate_mb_key($mb);
    if (function_exists('update_auth_session_token')) {
        update_auth_session_token($mb['mb_datetime']);
    }
}

/**
 * 로그아웃 시 SSO 토큰 삭제
 */
function _sso_on_gnuboard_logout($link = '') {
    global $connect;

    $token = $_COOKIE['sb_sso_token'] ?? '';
    if (empty($token)) return;
    if (!$connect) return;

    $stmt = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    setcookie('sb_sso_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

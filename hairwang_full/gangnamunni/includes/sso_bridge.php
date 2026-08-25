<?php
/**
 * SSO Bridge - 통합 로그인 브릿지
 * HMS / Gnuboard / Gangnamunni 간 세션 공유
 *
 * sso_tokens 테이블 + sb_sso_token 쿠키 기반
 * @created 2026-02-11
 */

define('SSO_COOKIE_NAME', 'sb_sso_token');
define('SSO_TOKEN_LIFETIME', 86400); // 24시간

/**
 * SSO 토큰 생성 및 쿠키 설정
 */
function sso_create_token($connect, $user_type, $g5_mb_id = null, $hms_user_id = null) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SSO_TOKEN_LIFETIME);

    // Replace only this SSO channel's token. Remember-login tokens use the
    // same table and must survive this post-login synchronization hook.
    if ($g5_mb_id) {
        $stmt = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $g5_mb_id, $user_type);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    if ($hms_user_id) {
        $stmt = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE hms_user_id = ? AND user_type = ?");
        mysqli_stmt_bind_param($stmt, 'is', $hms_user_id, $user_type);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, hms_user_id, ip_address, user_agent, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'sssisss', $token, $user_type, $g5_mb_id, $hms_user_id, $ip, $ua, $expires);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 쿠키 설정
    setcookie(SSO_COOKIE_NAME, $token, [
        'expires' => time() + SSO_TOKEN_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SSO_COOKIE_NAME] = $token;

    return $token;
}

/**
 * SSO 토큰 검증
 */
function sso_verify_token($connect) {
    $token = $_COOKIE[SSO_COOKIE_NAME] ?? '';
    if (empty($token) || strlen($token) !== 64) return null;

    $stmt = mysqli_prepare($connect,
        "SELECT * FROM sso_tokens WHERE token = ? AND expires_at > NOW()");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row;
}

/**
 * SSO 토큰 삭제 + 쿠키 제거
 */
function sso_delete_token($connect) {
    $token = $_COOKIE[SSO_COOKIE_NAME] ?? '';
    if (!empty($token)) {
        $stmt = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $token);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    setcookie(SSO_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[SSO_COOKIE_NAME]);
}

/**
 * 만료된 토큰 정리
 */
function sso_cleanup_expired($connect) {
    mysqli_query($connect, "DELETE FROM sso_tokens WHERE expires_at < NOW()");
}

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

    require_once dirname(__DIR__) . '/gangnamunni/includes/sso_session_token.php';
    $g5_mb_id = sso_claim_session_token($connect, $token, $_COOKIE['sb_app_device'] ?? '');
    if ($g5_mb_id === '') return;

    // 회원 조회는 트랜잭션 밖에서 — 잠글 필요가 없다.
    $mb = get_member($g5_mb_id);
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

    // 이 훅은 common.php가 $is_member/$member/$is_admin을 이미 확정한
    // "회원, 비회원 구분" 블록보다 뒤(extend/ 로드 시점)에 실행된다. 세션만
    // 설정하고 이 전역 변수들을 갱신하지 않으면, 방금 로그인이 성공했는데도
    // 이번 요청은 여전히 비회원으로 렌더링되고(첫 페이지가 게스트로 보임),
    // 다음 요청에서야(세션 쿠키로) 로그인 상태가 반영된다. common.php의
    // 해당 블록과 동일한 로직으로 지금 바로 채워서 이번 요청부터 맞게 한다.
    global $is_member, $member, $is_admin, $is_guest;
    $member = $mb;
    $member['mb_dir'] = substr($mb['mb_id'], 0, 2);
    $is_member = true;
    $is_guest = false;
    $is_admin = is_admin($mb['mb_id']);
}

/**
 * 로그아웃 시 SSO 토큰 삭제
 */
function _sso_on_gnuboard_logout($link = '') {
    global $connect;

    $token = $_COOKIE['sb_sso_token'] ?? '';
    try {
        if ($connect && is_string($token) && preg_match('/^[a-f0-9]{64}$/i', $token)) {
            $stmt = mysqli_prepare($connect,
                "DELETE FROM sso_tokens WHERE token = ? AND user_type IN ('gnuboard', 'hms', 'admin', 'patient_app_bridge')");
            if (!$stmt) throw new RuntimeException('SSO revocation prepare failed');
            mysqli_stmt_bind_param($stmt, 's', $token);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('SSO revocation failed');
            mysqli_stmt_close($stmt);
        }
    } catch (Throwable $e) {
        error_log('[sso-session] logout failed: ' . get_class($e));
    }
    // Cookie cleanup and the following logout hooks must still run on DB failure.
    setcookie('sb_sso_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

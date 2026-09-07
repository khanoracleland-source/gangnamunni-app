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

    // 이 훅은 common.php 부트스트랩 중에 실행되므로, gangnamunni/includes/
    // sso_auto_login.php의 (기기 검증이 있는) 버전보다 먼저 sb_sso_token을
    // 소비해버릴 수 있었다 — 네이티브 앱의 기기 바인딩 1회용 브릿지 토큰이
    // 여기서 그 검증을 완전히 건너뛰고 세션으로 승격될 수 있었다는 뜻이다.
    // 아래는 그 토큰 타입에 한해 같은 기기 검증 + 원자적 1회 소비를 적용한다
    // ('gnuboard' 등 다른 SSO 채널의 기존 동작은 그대로 유지한다).
    //
    // SELECT ... FOR UPDATE로 잠그고 같은 트랜잭션 안에서 즉시 삭제해야
    // 두 요청이 동시에 같은 토큰을 제출해도 하나만 성공한다 — 세션을 먼저
    // 만들고 나중에 지우면 그 사이의 창에서 양쪽 다 통과할 수 있다.
    $g5_mb_id = null;
    $is_native_bridge_token = false;
    mysqli_begin_transaction($connect);
    try {
        $stmt = mysqli_prepare($connect,
            "SELECT g5_mb_id, expires_at, user_type, user_agent FROM sso_tokens WHERE token = ? LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('prepare failed: ' . mysqli_error($connect));
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $sso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$sso || empty($sso['g5_mb_id']) || strtotime($sso['expires_at']) < time()) {
            mysqli_rollback($connect);
            return;
        }

        $is_native_bridge_token = $sso['user_type'] === 'patient_app_bridge';
        if ($is_native_bridge_token) {
            $device_id = $_COOKIE['sb_app_device'] ?? '';
            $expected_ua = (is_string($device_id) && preg_match('/^[a-f0-9]{64}$/i', $device_id))
                ? 'patient-app-device:' . hash('sha256', strtolower($device_id))
                : null;
            if ($expected_ua === null || !hash_equals((string)$sso['user_agent'], $expected_ua)) {
                mysqli_rollback($connect);
                return;
            }

            $del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge'");
            if (!$del) throw new RuntimeException('delete prepare failed: ' . mysqli_error($connect));
            mysqli_stmt_bind_param($del, 's', $token);
            mysqli_stmt_execute($del);
            $affected = mysqli_stmt_affected_rows($del);
            mysqli_stmt_close($del);
            if ($affected !== 1) {
                mysqli_rollback($connect);
                return; // 다른 요청이 먼저 이 토큰을 소비했다
            }
        }

        $g5_mb_id = $sso['g5_mb_id'];
        mysqli_commit($connect);
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        error_log('[sso_auto_login.extend] token claim failed: ' . $e->getMessage());
        return;
    }

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

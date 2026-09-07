<?php


function sso_try_auto_login($connect) {
    global $is_member, $member;

    if (isset($is_member) && $is_member && !empty($member['mb_id'])) {
        return true;
    }

    $sso_token = $_COOKIE['sb_sso_token'] ?? '';
    if (empty($sso_token)) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', trim($auth), $m)) {
            $sso_token = $m[1];
        }
    }

    if (!is_string($sso_token) || !preg_match('/^[A-Fa-f0-9]{64}$/', $sso_token)) {
        return false;
    }

    // 잠그고(FOR UPDATE) 같은 트랜잭션 안에서 즉시 소비(삭제)한다 — 세션을
    // 먼저 만들고 나중에 지우면, 두 요청이 동시에 같은 1회용 브릿지 토큰을
    // 제출했을 때 삭제 전까지의 창에서 둘 다 통과할 수 있었다.
    $mb_id = null;
    $is_native_bridge_token = false;
    mysqli_begin_transaction($connect);
    try {
        $stmt = mysqli_prepare($connect,
            "SELECT g5_mb_id, expires_at, user_type, user_agent FROM sso_tokens WHERE token = ? LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('prepare failed: ' . mysqli_error($connect));
        mysqli_stmt_bind_param($stmt, 's', $sso_token);
        mysqli_stmt_execute($stmt);
        $sso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$sso || empty($sso['g5_mb_id']) || strtotime($sso['expires_at']) < time()) {
            mysqli_rollback($connect);
            return false;
        }

        $is_native_bridge_token = $sso['user_type'] === 'patient_app_bridge';
        if ($is_native_bridge_token) {
            $device_id = $_COOKIE['sb_app_device'] ?? '';
            $expected_ua = (is_string($device_id) && preg_match('/^[a-f0-9]{64}$/i', $device_id))
                ? 'patient-app-device:' . hash('sha256', strtolower($device_id))
                : null;
            if ($expected_ua === null || !hash_equals((string)$sso['user_agent'], $expected_ua)) {
                mysqli_rollback($connect);
                return false;
            }

            $del = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge'");
            if (!$del) throw new RuntimeException('delete prepare failed: ' . mysqli_error($connect));
            mysqli_stmt_bind_param($del, 's', $sso_token);
            mysqli_stmt_execute($del);
            $affected = mysqli_stmt_affected_rows($del);
            mysqli_stmt_close($del);
            if ($affected !== 1) {
                mysqli_rollback($connect);
                return false; // 다른 요청이 먼저 소비했다
            }
        }

        $mb_id = $sso['g5_mb_id'];
        mysqli_commit($connect);
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        error_log('[sso_auto_login] token claim failed: ' . $e->getMessage());
        return false;
    }
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

    // common.php의 "회원, 비회원 구분" 블록과 동일한 형태로 채운다 — 이
    // 요청의 나머지 부분(mb_dir/is_admin을 참조하는 다른 코드 포함)이
    // 일반 로그인과 똑같이 동작하게 하기 위함이다.
    global $is_admin;
    $mb['mb_dir'] = substr($mb['mb_id'], 0, 2);
    $member = $mb;
    $is_member = true;
    $is_admin = function_exists('is_admin') ? is_admin($mb['mb_id']) : '';

    // patient_app_bridge 토큰은 위 트랜잭션에서 이미 원자적으로 소비(삭제)됐다.
    // 'gnuboard' 등 다른 SSO 채널은 여러 요청에 걸쳐 쓰이는 기존 동작을 유지한다.

    return true;
}

if (isset($connect) && $connect) {
    sso_try_auto_login($connect);
}

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

    require_once __DIR__ . '/sso_session_token.php';
    $mb_id = sso_claim_session_token($connect, $sso_token, $_COOKIE['sb_app_device'] ?? '');
    if ($mb_id === '') return false;

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
    global $is_admin, $is_guest;
    $mb['mb_dir'] = substr($mb['mb_id'], 0, 2);
    $member = $mb;
    $is_member = true;
    $is_guest = false;
    $is_admin = function_exists('is_admin') ? is_admin($mb['mb_id']) : '';

    // patient_app_bridge 토큰은 위 트랜잭션에서 이미 원자적으로 소비(삭제)됐다.
    // 'gnuboard' 등 다른 SSO 채널은 여러 요청에 걸쳐 쓰이는 기존 동작을 유지한다.

    return true;
}

if (isset($connect) && $connect) {
    sso_try_auto_login($connect);
}

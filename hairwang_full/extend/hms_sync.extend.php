<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * HMS 회원 동기화 + SSO 자동 훅
 * Gnuboard 로그인/회원가입 시 HMS 동기화 + SSO 토큰 발급
 *
 * @created 2026-02-11
 */

// 로그인 성공 후 HMS 동기화 + SSO 토큰
add_event('member_login_check', 'hms_sync_on_login', 10, 3);

// 회원가입 완료 후 HMS 동기화 + SSO 토큰
add_event('register_form_update_after', 'hms_sync_on_register', 10, 2);

/**
 * 로그인 시 HMS 동기화 + SSO 토큰 발급
 */
function hms_sync_on_login($mb, $link = '', $is_social = false) {
    if (empty($mb['mb_no'])) return;

    // HMS 동기화
    _hms_auto_sync($mb['mb_no']);

    // SSO 토큰 발급
    _sso_create_on_login($mb);
}

/**
 * 회원가입 시 HMS 동기화 + SSO 토큰 발급
 */
function hms_sync_on_register($mb_id, $w = '') {
    if ($w !== '') return;
    if (empty($mb_id)) return;

    $mb = get_member($mb_id);
    if (empty($mb['mb_no'])) return;

    // HMS 동기화
    _hms_auto_sync($mb['mb_no']);

    // SSO 토큰 발급
    _sso_create_on_login($mb);
}

/**
 * HMS 자동 동기화 실행
 */
function _hms_auto_sync($mb_no) {
    $sync_file = G5_PATH . '/gangnamunni/includes/member_sync.php';
    if (!file_exists($sync_file)) return;

    if (!function_exists('sync_member_to_hms')) {
        include_once($sync_file);
    }

    global $connect;
    if (!$connect) return;

    $existing = get_sync_status($mb_no, $connect);
    if ($existing && $existing['sync_status'] === 'synced') {
        _hms_sync_password($mb_no, $connect, $existing);
        return;
    }

    sync_member_to_hms($mb_no, $connect);
}

/**
 * 비밀번호 동기화 (Gnuboard → HMS)
 */
function _hms_sync_password($mb_no, $connect, $sync_data) {
    if (empty($sync_data['hms_user_id'])) return;
    // 환자 전용 HMS 계정에만 비밀번호를 동기화한다 (직원/관리자 계정 비밀번호 덮어쓰기 방지)
    if (!function_exists('hms_user_is_patient_only') || !hms_user_is_patient_only($sync_data['hms_user_id'], $connect)) {
        error_log('[hms_sync] mb_no=' . intval($mb_no) . ' → HMS user #' . intval($sync_data['hms_user_id']) . ' 는 환자 전용 계정이 아니어서 비밀번호 동기화 생략');
        return;
    }

    $mb = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT mb_password FROM g5_member WHERE mb_no = " . intval($mb_no)));
    if (!$mb) return;

    $stmt = mysqli_prepare($connect,
        "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'si', $mb['mb_password'], $sync_data['hms_user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * SSO 토큰 발급
 */
function _sso_create_on_login($mb) {
    global $connect;
    if (!$connect) return;

    $sso_file = G5_PATH . '/gangnamunni/includes/sso_bridge.php';
    if (!file_exists($sso_file)) return;

    if (!function_exists('sso_create_token')) {
        include_once($sso_file);
    }

    // member_sync에서 hms_user_id 조회
    // 정상 연결(synced)된 환자 전용 HMS 계정만 SSO 에 싣는다. 그 외에는 그누보드 전용 토큰.
    $hms_user_id = null;
    $sync = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT hms_user_id, sync_status FROM member_sync WHERE mb_no = " . intval($mb['mb_no'])));
    if ($sync && !empty($sync['hms_user_id']) && $sync['sync_status'] === 'synced') {
        $sync_file = G5_PATH . '/gangnamunni/includes/member_sync.php';
        if (!function_exists('hms_user_is_patient_only') && file_exists($sync_file)) include_once($sync_file);
        if (function_exists('hms_user_is_patient_only') && hms_user_is_patient_only($sync['hms_user_id'], $connect)) {
            $hms_user_id = (int)$sync['hms_user_id'];
        }
    }

    sso_create_token($connect, 'gnuboard', $mb['mb_id'], $hms_user_id);
}

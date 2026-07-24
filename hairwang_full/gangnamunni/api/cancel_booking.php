<?php
/**
 * 예약 취소 API
 * POST: { booking_id, phone, cancel_reason }
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST만 허용']);
    exit;
}

// common.php를 거쳐야 그누보드의 실제 세션명(session_name)과 동일하게 세션을 읽을 수 있음
// (dbconfig.php만 사용하면 커스텀 세션명 환경에서 로그인 세션을 못 찾는 문제 발생)
require_once dirname(__DIR__) . '/includes/init.php';
if (!function_exists('getSettingValue') && file_exists(dirname(__DIR__).'/includes/settings_helper.php')) require_once dirname(__DIR__).'/includes/settings_helper.php';
if (!isset($connect) || !$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['my_bookings_csrf']) || !hash_equals($_SESSION['my_bookings_csrf'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');
$cancel_reason = trim($_POST['cancel_reason'] ?? '');

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => '필수 항목을 입력해주세요.']);
    exit;
}

// 예약 조회 + 전화번호 일치 확인
$stmt = mysqli_prepare($connect, "SELECT id, phone, mb_id, status, hms_appointment_id, hospital_id FROM gangnamunni_bookings WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => '예약을 찾을 수 없습니다.']);
    exit;
}

// 전화번호 비교 (하이픈 제거 후)
// [2026-06-15] 본인확인: 로그인필수 모드면 회원ID, 아니면 전화번호 (booking_require_login 설정)
$_req_login = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_require_login','1')==='1') : true;
$_phone_auth = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_phone_auth','0')==='1') : false;
$_sess_mb_id = $_SESSION['ss_mb_id'] ?? '';
if ($_req_login && !$_phone_auth) {
    if (empty($_sess_mb_id) || empty($booking['mb_id']) || $_sess_mb_id !== $booking['mb_id']) {
        echo json_encode(['success' => false, 'message' => '로그인 후 본인 예약만 취소할 수 있습니다.']);
        exit;
    }
} else {
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => '필수 항목을 입력해주세요.']);
        exit;
    }
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $clean_db_phone = preg_replace('/[^0-9]/', '', $booking['phone']);
    if ($clean_phone !== $clean_db_phone) {
        echo json_encode(['success' => false, 'message' => '본인 예약만 취소할 수 있습니다.']);
        exit;
    }
}

// 이미 취소/완료된 예약 체크
if (in_array($booking['status'], ['cancelled', 'completed', 'no_show'])) {
    echo json_encode(['success' => false, 'message' => '이미 ' . ($booking['status'] === 'cancelled' ? '취소' : '완료') . '된 예약입니다.']);
    exit;
}

// 예약 취소(핵심) + 로컬 HMS 상태를 하나의 DB transaction으로 원자 처리한다.
require_once dirname(__DIR__) . '/includes/points_ledger.php';
mysqli_begin_transaction($connect);
try {
    $stmt2 = mysqli_prepare($connect, "UPDATE gangnamunni_bookings
        SET status='cancelled', cancel_reason=?, cancelled_at=NOW(), updated_at=NOW()
        WHERE id=? AND status IN ('pending','confirmed')");
    if (!$stmt2) throw new RuntimeException('booking_update_prepare_failed');
    mysqli_stmt_bind_param($stmt2, 'si', $cancel_reason, $booking_id);
    $ok = mysqli_stmt_execute($stmt2);
    $affected = mysqli_stmt_affected_rows($stmt2);
    mysqli_stmt_close($stmt2);
    if (!$ok || $affected !== 1) throw new RuntimeException('booking_update_failed');

    if (!empty($booking['hms_appointment_id'])) {
        $hms_stmt = mysqli_prepare($connect, "UPDATE appointments SET is_completed=2, updated_at=NOW() WHERE id=?");
        if (!$hms_stmt) throw new RuntimeException('hms_update_prepare_failed');
        mysqli_stmt_bind_param($hms_stmt, 'i', $booking['hms_appointment_id']);
        $hms_ok = mysqli_stmt_execute($hms_stmt);
        mysqli_stmt_close($hms_stmt);
        if (!$hms_ok) throw new RuntimeException('hms_update_failed');
    }

    mysqli_commit($connect);
} catch (Throwable $e) {
    mysqli_rollback($connect);
    error_log('[cancel_booking] atomic cancel failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '취소 처리 중 오류가 발생했습니다. 다시 시도해주세요.']);
    exit;
}

// 포인트 회수(#포인트제도): 취소가 커밋된 뒤 best-effort 로 처리한다.
//   - 부가 기능이므로 실패해도 취소(핵심 작업)는 유지한다(로그만 기록).
//   - autocommit 모드에서 실행되어 GET_LOCK 이 잔액 스냅샷을 정확히 직렬화한다.
if (!empty($booking['mb_id'])) {
    try {
        $member_no = points_member_no($connect, $booking['mb_id']);
        if ($member_no > 0) points_cancel_booking($connect, $member_no, $booking_id);
    } catch (\Throwable $e) {
        error_log('[cancel_booking] point clawback failed (cancel already committed): ' . $e->getMessage());
    }
}

mysqli_close($connect);
echo json_encode(['success' => true, 'message' => '예약이 취소되었습니다.']);

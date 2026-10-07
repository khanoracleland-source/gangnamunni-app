<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST만 허용']);
    exit;
}

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

require_once dirname(__DIR__) . '/includes/booking_payment_access.php';
if (!booking_payment_owned($connect, $booking_id)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'본인 예약만 취소할 수 있습니다.']);
    exit;
}

if (in_array($booking['status'], ['cancelled', 'completed', 'no_show'])) {
    echo json_encode(['success' => false, 'message' => '이미 ' . ($booking['status'] === 'cancelled' ? '취소' : '완료') . '된 예약입니다.']);
    exit;
}

require_once dirname(__DIR__) . '/includes/points_ledger.php';
mysqli_begin_transaction($connect);
try {
    $stmt2 = mysqli_prepare($connect, "UPDATE gangnamunni_bookings
        SET status='cancelled', cancel_reason=?, cancelled_at=NOW(), updated_at=NOW()
        WHERE id=? AND status IN ('pending','hospital_checking','confirmed')");
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

$refund_status = 'not_applicable';
$refund_message = '';
try {
    $order = booking_payment_order($connect, $booking_id);
    if ($order && in_array($order['provider'], ['portone', 'portone_test'], true)
        && in_array($order['status'], ['paid', 'partially_refunded'], true)) {
        $refund_status = 'not_refunded';
        $refund_message = '예약이 취소되었습니다. 환불은 관리자 확인 후 처리됩니다.';
    }
} catch (Throwable $e) {
    error_log('[cancel_booking] payment status lookup failed after cancel: ' . $e->getMessage());
    $refund_status = 'review_required';
    $refund_message = '예약은 취소되었으며, 환불 상태 확인이 필요합니다.';
}

try {
    $refund_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_bookings SET refund_status=?, updated_at=NOW() WHERE id=?");
    if (!$refund_stmt) throw new RuntimeException('refund_status_prepare_failed');
    mysqli_stmt_bind_param($refund_stmt, 'si', $refund_status, $booking_id);
    if (!mysqli_stmt_execute($refund_stmt)) throw new RuntimeException('refund_status_update_failed');
    mysqli_stmt_close($refund_stmt);
} catch (Throwable $e) {
    error_log('[cancel_booking] refund status update failed after cancel: ' . $e->getMessage());
}

// 포인트 결제(#포인트제도 v2): 결제 전 취소 → 결제에 걸어 둔 포인트 복구
try {
    require_once dirname(__DIR__) . '/includes/points_checkout.php';
    points_checkout_release_for_booking($connect, $booking_id, '예약 취소 포인트 복구');
} catch (\Throwable $e) {
    error_log('[cancel_booking] point hold release failed: ' . $e->getMessage());
}

if (!empty($booking['mb_id'])) {
    try {
        $member_no = points_member_no($connect, $booking['mb_id']);
        if ($member_no > 0) points_cancel_booking($connect, $member_no, $booking_id);
    } catch (\Throwable $e) {
        error_log('[cancel_booking] point clawback failed (cancel already committed): ' . $e->getMessage());
    }
}

mysqli_close($connect);
echo json_encode([
    'success' => true,
    'message' => $refund_message ?: '예약이 취소되었습니다.',
    'refund_status' => $refund_status
], JSON_UNESCAPED_UNICODE);

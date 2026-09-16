<?php
/**
 * 임시 예약 결제 완료 처리.
 * 금융 원장을 변경하지 않고 별도 테이블에 결제 없는 미리보기를 기록한다.
 */
require_once __DIR__ . '/includes/init.php';
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
require_once __DIR__ . '/includes/payment_core.php';
require_once __DIR__ . '/includes/booking_payment_access.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bpm_fail($message, $code, $http = 400) {
    http_response_code($http);
    echo json_encode(['success' => false, 'code' => $code, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bpm_fail('POST 요청만 허용됩니다.', 'METHOD_NOT_ALLOWED', 405);
}

$csrf = (string)($_SESSION['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
    bpm_fail('요청이 만료되었습니다. 페이지를 새로고침해주세요.', 'INVALID_CSRF', 403);
}

global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_mb_id = $is_user_logged_in ? (string)$member['mb_id'] : '';
$session_phone = (string)($_SESSION['user_phone'] ?? '');
$owner_phone = $is_user_logged_in ? (string)($member['mb_hp'] ?? $session_phone) : $session_phone;
$owner_phone_digits = preg_replace('/[^0-9]/', '', $owner_phone);

if (!$is_user_logged_in && $owner_phone_digits === '') {
    bpm_fail('로그인이 필요합니다.', 'AUTH_REQUIRED', 401);
}

$booking_id = max(0, (int)($_POST['booking_id'] ?? 0));
if ($booking_id <= 0) bpm_fail('잘못된 예약입니다.', 'INVALID_BOOKING');

$stmt = mysqli_prepare($connect,
    "SELECT id, status
     FROM gangnamunni_bookings
     WHERE id = ?
       AND (
            (? <> '' AND mb_id = ?)
         OR ((mb_id IS NULL OR mb_id = '') AND ? <> ''
             AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)
       )
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'issss', $booking_id, $logged_mb_id, $logged_mb_id, $owner_phone_digits, $owner_phone_digits);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) bpm_fail('예약을 찾을 수 없거나 접근 권한이 없습니다.', 'BOOKING_NOT_FOUND', 404);
if (($booking['status'] ?? '') !== 'confirmed') {
    bpm_fail('병원이 예약을 확정한 뒤에 결제할 수 있습니다.', 'BOOKING_NOT_CONFIRMED', 409);
}

try {
    $order = platform_payment_create_booking($connect, $booking_id);
    $merchant_uid = (string)($order['merchant_uid'] ?? '');
    $ostmt = mysqli_prepare($connect,
        "SELECT merchant_uid, amount, status
         FROM gangnamunni_platform_payments
         WHERE payment_type='booking' AND booking_id=? LIMIT 1");
    mysqli_stmt_bind_param($ostmt, 'i', $booking_id);
    mysqli_stmt_execute($ostmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
    mysqli_stmt_close($ostmt);

    if (!$order || empty($order['merchant_uid'])) {
        throw new RuntimeException('Payment order not found.');
    }
    if (in_array($order['status'], ['paid', 'partially_refunded'], true)) {
        echo json_encode(['success' => true, 'redirect_url' => 'booking_payment_success.php?booking_id=' . $booking_id], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (($order['status'] ?? '') !== 'pending') {
        bpm_fail('결제할 수 없는 주문 상태입니다.', 'INVALID_ORDER_STATE', 409);
    }

    if (!booking_payment_preview_enabled()) bpm_fail('결제 없이 계속하기가 비활성화되었습니다.', 'PREVIEW_DISABLED', 403);
    // Preview evidence lives outside the financial ledger. It cannot trigger payout.
    booking_payment_record_preview($connect, $booking_id);

    echo json_encode(['success' => true, 'redirect_url' => 'booking_payment_success.php?booking_id=' . $booking_id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[booking_payment_mock_complete] ' . $e->getMessage());
    bpm_fail('임시 결제 완료 처리에 실패했습니다. 잠시 후 다시 시도해주세요.', 'MOCK_PAYMENT_FAILED', 500);
}

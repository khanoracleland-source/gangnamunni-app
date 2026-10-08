<?php
/** Prepare a server-priced PG checkout. Never mark an order paid here. */
require_once __DIR__ . '/includes/init.php';
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bp_fail($message, $code, $http = 400) {
    http_response_code($http);
    echo json_encode(['success' => false, 'code' => $code, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bp_fail('POST 요청만 허용됩니다.', 'METHOD_NOT_ALLOWED', 405);
}

// CSRF — booking.php 등과 동일한 세션 토큰 사용
$csrf = (string)($_SESSION['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
    bp_fail('요청이 만료되었습니다. 페이지를 새로고침해주세요.', 'INVALID_CSRF', 403);
}

// ── 본인 확인 (booking_payment.php / my_bookings.php 와 동일 규칙) ──────
global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_mb_id = $is_user_logged_in ? (string)$member['mb_id'] : '';
$session_phone = (string)($_SESSION['user_phone'] ?? '');
$owner_phone = $is_user_logged_in ? (string)($member['mb_hp'] ?? $session_phone) : $session_phone;
$owner_phone_digits = preg_replace('/[^0-9]/', '', $owner_phone);

if (!$is_user_logged_in && $owner_phone_digits === '') {
    bp_fail('로그인이 필요합니다.', 'AUTH_REQUIRED', 401);
}

$booking_id = max(0, (int)($_POST['booking_id'] ?? 0));
if ($booking_id <= 0) bp_fail('잘못된 예약입니다.', 'INVALID_BOOKING');

$provider = (string)($_POST['provider'] ?? '');
if (!in_array($provider, ['bank', 'card', 'naverpay', 'kakaopay', 'tosspay'], true)) {
    bp_fail('지원하지 않는 결제 수단입니다.', 'UNSUPPORTED_PROVIDER');
}

$point_use = max(0, (int)($_POST['point_use'] ?? 0));
// 포인트 결제(#포인트제도 v2): 스위치(v2_point_use_enabled)가 꺼져 있으면 기존처럼 거부한다.
require_once __DIR__ . '/includes/points_checkout.php';
if ($point_use > 0 && !points_checkout_ready($connect)) {
    bp_fail('포인트 차감 결제는 아직 구성되지 않았습니다. 포인트를 0원으로 변경한 뒤 결제해주세요.', 'POINT_PAYMENT_NOT_CONFIGURED', 503);
}

// 예약 소유권 확인
$stmt = mysqli_prepare($connect,
    "SELECT id, status FROM gangnamunni_bookings
     WHERE id = ?
       AND (
            (? <> '' AND mb_id = ?)
         OR ((mb_id IS NULL OR mb_id = '') AND ? <> ''
             AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)
       )
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'sssss', $booking_id, $logged_mb_id, $logged_mb_id,
    $owner_phone_digits, $owner_phone_digits);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) bp_fail('예약을 찾을 수 없거나 접근 권한이 없습니다.', 'BOOKING_NOT_FOUND', 404);
if (($booking['status'] ?? '') !== 'confirmed') {
    bp_fail('병원이 예약을 확정한 뒤에 결제할 수 있습니다.', 'BOOKING_NOT_CONFIRMED', 409);
}

// 주문 확인 — 금액은 원장에서만 읽는다(클라이언트가 보낸 금액은 신뢰하지 않는다)
$ostmt = mysqli_prepare($connect,
    "SELECT *
     FROM gangnamunni_platform_payments
     WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1");
if (!$ostmt) bp_fail('결제 원장이 설치되지 않았습니다.', 'LEDGER_NOT_INSTALLED', 503);
mysqli_stmt_bind_param($ostmt, 'i', $booking_id);
mysqli_stmt_execute($ostmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
mysqli_stmt_close($ostmt);

if (!$order) bp_fail('결제 주문이 없습니다. 병원에 문의해주세요.', 'ORDER_NOT_FOUND', 404);
if (in_array($order['status'], ['paid', 'partially_refunded', 'refunded'], true)) {
    bp_fail('이미 결제된 예약입니다.', 'ALREADY_PAID', 409);
}
if ($order['status'] !== 'pending') {
    bp_fail('결제할 수 없는 주문 상태입니다.', 'INVALID_ORDER_STATE', 409);
}

require_once __DIR__ . '/includes/booking_payment_config.php';
// 결제가 불가능하면 포인트 홀드 등 어떤 상태도 바꾸기 전에 멈춘다.
if (!booking_payment_checkout_ready() || !isset(booking_payment_methods()[$provider])) {
    bp_fail('현재 이 결제 수단을 이용할 수 없습니다. 잠시 후 다시 시도해주세요.', 'PAYMENT_UNAVAILABLE', 503);
}

// 포인트 적용/변경/해제 — 결제 잠금 안에서, PG 에 결제가 진행 중이지 않을 때만 금액을 바꾼다.
if (points_v2_schema_ready($connect) && ($point_use > 0 || (int)($order['points_used'] ?? 0) > 0)) {
    require_once __DIR__ . '/includes/booking_payment_service.php';
    $pt_member_no = $is_user_logged_in ? points_member_no($connect, $logged_mb_id) : 0;
    try {
        $order = booking_payment_lock($connect, (string)$order['merchant_uid'], function () use ($connect, $order, $pt_member_no, $point_use) {
            $fresh = booking_payment_by_merchant($connect, (string)$order['merchant_uid']);
            if (($fresh['status'] ?? '') !== 'pending') throw new RuntimeException('결제할 수 없는 주문 상태입니다.');
            $gateway = booking_payment_gateway();
            if ($gateway) {
                try {
                    $remote = $gateway->payment((string)$fresh['merchant_uid']);
                    if (!in_array($remote['status'] ?? '', ['ready', 'failed'], true)) {
                        throw new RuntimeException('진행 중인 결제가 있어 포인트를 변경할 수 없습니다. 결제 상태를 먼저 확인해주세요.');
                    }
                } catch (BookingPaymentRejectedException $e) {
                    // 아직 PG 에 결제 건이 없음 → 변경 가능
                }
            }
            points_checkout_apply($connect, $fresh, $pt_member_no, $point_use);
            return booking_payment_by_merchant($connect, (string)$order['merchant_uid']);
        });
    } catch (Throwable $e) {
        error_log('[booking_payment_request] point apply: ' . $e->getMessage());
        $msg = preg_match('/[가-힣]/u', $e->getMessage()) ? $e->getMessage() : '포인트를 적용하지 못했습니다. 잠시 후 다시 시도해주세요.';
        bp_fail($msg, 'POINT_APPLY_FAILED', 409);
    }
}

// PG 결제창 파라미터는 PG 연동(Toss Payments) 단계에서 여기서 만든다.
bp_fail('현재 온라인 결제를 준비 중입니다. 잠시 후 다시 시도해주세요.', 'PAYMENT_UNAVAILABLE', 503);

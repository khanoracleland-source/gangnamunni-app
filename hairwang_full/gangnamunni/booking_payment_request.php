<?php
/** Prepare a server-priced PortOne V1 checkout. Never mark an order paid here. */
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
if ($point_use > 0) {
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
    "SELECT id, merchant_uid, amount, status, patient_name, patient_phone
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

require_once __DIR__ . '/includes/portone_client.php';
try {
    if (!booking_payment_live_enabled()) throw new RuntimeException('Payments disabled.');
    $channels = booking_payment_channels();
    if (empty($channels[$provider])) throw new RuntimeException('Payment channel is not configured.');
    $imp_code = booking_payment_setting('PORTONE_IMP_CODE');
    if ($imp_code === '') throw new RuntimeException('Store is not configured.');
    $base = booking_payment_base_url();
    $client = new BookingPortOneClient();
    $client->prepare($order['merchant_uid'], (int)$order['amount']);
    $methods = ['card'=>'card', 'bank'=>'trans', 'naverpay'=>'naverpay', 'kakaopay'=>'kakaopay', 'tosspay'=>'tosspay'];
    echo json_encode(['success'=>true, 'imp_code'=>$imp_code, 'payment'=>[
        'channelKey'=>$channels[$provider], 'merchant_uid'=>$order['merchant_uid'],
        'amount'=>(int)$order['amount'], 'currency'=>'KRW',
        'buyer_name'=>$order['patient_name'], 'buyer_tel'=>$order['patient_phone'],
        'buyer_email'=>(string)($member['mb_email'] ?? ''),
        'pay_method'=>$methods[$provider], 'name'=>'예약 #' . $booking_id,
        'm_redirect_url'=>$base . '/booking_payment_return.php?booking_id=' . $booking_id,
        'notice_url'=>$base . '/payment/portone_webhook.php',
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[booking_payment_request] ' . $e->getMessage());
    bp_fail('현재 이 결제 수단을 이용할 수 없습니다. 잠시 후 다시 시도해주세요.', 'PAYMENT_UNAVAILABLE', 503);
}

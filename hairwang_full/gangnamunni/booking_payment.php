<?php
/**
 * 예약 결제 페이지 (booking_payment.php)
 *
 * 병원이 예약을 확정한 뒤 사용자가 결제를 진행하는 화면.
 * 금액은 URL/폼이 아니라 gangnamunni_platform_payments 주문에서만 읽는다.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_access.php';
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (file_exists(__DIR__ . '/includes/points_ledger.php')) { require_once __DIR__ . '/includes/points_ledger.php'; }

global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_mb_id = $is_user_logged_in ? (string)$member['mb_id'] : '';
$session_phone = (string)($_SESSION['user_phone'] ?? '');
$owner_phone = $is_user_logged_in ? (string)($member['mb_hp'] ?? $session_phone) : $session_phone;
$owner_phone_digits = preg_replace('/[^0-9]/', '', $owner_phone);

if (!$is_user_logged_in && $owner_phone_digits === '') {
    header('Location: auth/login.php?url=' . urlencode(rtrim(SITE_BASE_PATH, '/') . '/my_bookings.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function bp_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function bp_money($amount) { return number_format(max(0, (int)$amount)); }
function bp_digits($value) { return preg_replace('/[^0-9]/', '', (string)$value); }
function bp_phone($value) {
    $d = bp_digits($value);
    if (strlen($d) === 11) return substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7);
    if (strlen($d) === 10) return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
    return (string)$value;
}
function bp_korean_date($date, $time) {
    $ts = strtotime(trim((string)$date . ' ' . (string)$time));
    if (!$ts) return trim((string)$date . ' ' . substr((string)$time, 0, 5));
    $week = ['일','월','화','수','목','금','토'][(int)date('w', $ts)];
    $hour = (int)date('G', $ts);
    $minute = date('i', $ts);
    $ampm = $hour < 12 ? '오전' : '오후';
    $display_hour = $hour % 12;
    if ($display_hour === 0) $display_hour = 12;
    return date('n월 j일', $ts) . ' (' . $week . ') ' . $ampm . ' ' . $display_hour . ':' . $minute;
}
function bp_short_address($address) {
    $address = trim((string)$address);
    if ($address === '') return '';
    $parts = preg_split('/\s+/', $address);
    if (!$parts || count($parts) < 2) return $address;
    return $parts[0] . ' ' . $parts[1];
}

$booking_id = max(0, (int)($_GET['booking_id'] ?? 0));
if ($booking_id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

$stmt = mysqli_prepare($connect,
    "SELECT b.*, h.name AS h_name, h.profile_image_url, h.address AS hospital_address
     FROM gangnamunni_bookings b
     LEFT JOIN gangnamunni_hospitals h ON h.id = b.hospital_id
     WHERE b.id = ?
       AND (
            (? <> '' AND b.mb_id = ?)
         OR ((b.mb_id IS NULL OR b.mb_id = '') AND ? <> ''
             AND REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?)
       )
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'issss', $booking_id, $logged_mb_id, $logged_mb_id,
    $owner_phone_digits, $owner_phone_digits);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$fatal = '';
if (!$booking) {
    http_response_code(404);
    $fatal = '예약을 찾을 수 없거나 접근 권한이 없습니다.';
}

$ledger_ready = false;
$order = null;
$order_error = '';

if ($fatal === '') {
    $chk = mysqli_query($connect,
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments' LIMIT 1");
    $ledger_ready = $chk && mysqli_num_rows($chk) > 0;
}

if ($ledger_ready) {
    $ostmt = mysqli_prepare($connect,
        "SELECT *
         FROM gangnamunni_platform_payments
         WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($ostmt, 'i', $booking_id);
    mysqli_stmt_execute($ostmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
    mysqli_stmt_close($ostmt);

    if (!$order && ($booking['status'] ?? '') === 'confirmed') {
        try {
            require_once __DIR__ . '/includes/payment_core.php';
            platform_payment_create_booking($connect, $booking_id);
            $ostmt = mysqli_prepare($connect,
                "SELECT *
                 FROM gangnamunni_platform_payments
                 WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1");
            mysqli_stmt_bind_param($ostmt, 'i', $booking_id);
            mysqli_stmt_execute($ostmt);
            $order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
            mysqli_stmt_close($ostmt);
        } catch (\Throwable $e) {
            error_log('[booking_payment] order create failed: ' . $e->getMessage());
            $order_error = '결제 주문을 만들지 못했습니다. 병원에 문의해주세요.';
        }
    }
}

$status = $booking['status'] ?? '';
$is_confirmed = ($status === 'confirmed');
$order_status = $order['status'] ?? '';
$is_paid = in_array($order_status, ['paid', 'partially_refunded'], true);
$amount = (int)($order['amount'] ?? ($booking['price'] ?? 0));
// 포인트 결제(#포인트제도 v2): 화면 기준 금액은 포인트 차감 전 총액(gross)
$points_applied = (int)($order['points_used'] ?? 0);
$gross_amount = (int)($order['gross_amount'] ?? 0);
if ($gross_amount <= 0) $gross_amount = $amount + $points_applied;
$display_amount = max(0, $gross_amount);
$point_balance = 0;
if ($is_user_logged_in && $logged_mb_id !== '' && function_exists('points_member_no') && function_exists('points_balance_safe')) {
    try {
        $member_no = points_member_no($connect, $logged_mb_id);
        if ($member_no > 0) $point_balance = max(0, (int)points_balance_safe($connect, $member_no));
    } catch (Throwable $e) {
        error_log('[booking_payment] optional point balance unavailable');
    }
}
$max_point_use = 0;
$expected_points = 0;
$point_use_ready = false;
$point_policy_v2 = false;
if (file_exists(__DIR__ . '/includes/points_checkout.php')) {
    require_once __DIR__ . '/includes/points_checkout.php';
    require_once __DIR__ . '/includes/points_policy_engine.php';
    try {
        $point_policy_v2 = points_v2_on($connect, 'v2_policy_enabled');
        if ($point_policy_v2) $expected_points = points_v2_payment_tier_point($connect, $display_amount);
        $point_use_ready = $is_user_logged_in && !$is_paid && $order_status === 'pending' && points_checkout_ready($connect);
        if ($point_use_ready && !empty($member_no)) {
            $max_point_use = points_checkout_max($connect, $member_no, $display_amount, $points_applied);
        }
    } catch (Throwable $e) {
        error_log('[booking_payment] point v2 unavailable: ' . $e->getMessage());
        $point_use_ready = false; $max_point_use = 0;
    }
}
$preview = booking_payment_previews($connect, [$booking_id]);
$channels = booking_payment_methods();
$live_available = booking_payment_checkout_ready();
// Toss 성공/실패 URL 에서 돌아왔을 때의 안내. 코드만 받고 문구는 서버가 정한다.
$pay_notice = '';
$pay_error = preg_replace('/[^A-Z_]/', '', (string)($_GET['pay_error'] ?? ''));
if ($pay_error !== '') {
    $pay_notices = [
        'PAY_PROCESS_CANCELED' => '결제를 취소했습니다. 청구된 금액은 없습니다.',
        'USER_CANCEL' => '결제를 취소했습니다. 청구된 금액은 없습니다.',
        'PAY_PROCESS_ABORTED' => '결제가 중단되었습니다. 청구된 금액은 없으니 다시 시도해주세요.',
        'REJECT_CARD_COMPANY' => '카드사에서 결제를 거절했습니다. 다른 카드나 결제 수단을 이용해주세요.',
        'NOT_FOUND_PAYMENT_SESSION' => '결제 시간이 초과되었습니다. 청구된 금액은 없으니 다시 결제해주세요.',
        'AMOUNT_MISMATCH' => '결제 금액이 변경되어 결제를 진행하지 않았습니다. 청구된 금액은 없으니 금액을 확인한 뒤 다시 결제해주세요.',
        'POINT_HOLD_EXPIRED' => '포인트 적용 시간이 지나 결제를 진행하지 않았습니다. 청구된 금액은 없으니 다시 결제해주세요.',
        'BOOKING_NOT_CONFIRMED' => '예약 상태가 변경되어 결제를 진행하지 않았습니다. 청구된 금액은 없습니다.',
        'ORDER_STATE' => '이 주문은 지금 결제할 수 없습니다. 청구된 금액은 없습니다.',
        'UNRESOLVED' => '결제 결과를 확인하고 있습니다. 다시 결제하지 말고 잠시 후 ‘이미 결제했다면 상태 확인’을 눌러주세요.',
    ];
    $pay_notice = $pay_notices[$pay_error] ?? '결제가 승인되지 않았습니다. 청구된 금액은 없으니 다시 시도하거나 다른 결제 수단을 이용해주세요.';
}
$preview_available = booking_payment_preview_enabled();

$hospital_name = ($booking['h_name'] ?? '') ?: ($booking['hospital_name'] ?? '');
$hospital_address = $booking['hospital_address'] ?? '';
$hospital_area = bp_short_address($hospital_address);
$treatment_name = ($booking['treatment'] ?? '') ?: '-';
$booker_name = ($booking['name'] ?? '') ?: ($member['mb_name'] ?? '');
$booker_phone = bp_phone(($booking['phone'] ?? '') ?: $owner_phone);
$booking_datetime = bp_korean_date($booking['booking_date'] ?? '', $booking['booking_time'] ?? '');
$logo_url = trim((string)($booking['profile_image_url'] ?? ''));
$status_labels = [
    'pending' => '예약 요청 접수',
    'hospital_checking' => '병원 확인 중',
    'confirmed' => '결제 대기',
    'completed' => '시술완료',
    'cancelled' => '예약취소',
    'no_show' => '노쇼',
];
$status_text = $status_labels[$status] ?? $status;

?><!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>결제를 진행해볼까요?</title>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #f3f3f3; color: #111; font-family: "Noto Sans KR", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
button, input, textarea { font: inherit; }
button { cursor: pointer; }
a { color: inherit; text-decoration: none; }
.bp-page { max-width: 640px; min-height: 100vh; margin: 0 auto; background: #fff; }
.bp-header { height: 72px; display: flex; align-items: center; justify-content: center; position: sticky; top: 0; z-index: 10; background: #fff; }
.bp-back { position: absolute; left: 22px; top: 50%; width: 28px; height: 28px; transform: translateY(-50%); border: 0; background: transparent; padding: 0; }
.bp-back::before { content: ""; position: absolute; left: 8px; top: 5px; width: 16px; height: 16px; border-left: 2px solid #111; border-bottom: 2px solid #111; transform: rotate(45deg); }
.bp-title { font-size: 20px; font-weight: 500; letter-spacing: -.04em; }
.bp-content { padding-bottom: 22px; }
.bp-section { padding: 28px 28px 26px; background: #fff; }
.bp-section + .bp-section { border-top: 10px solid #f3f3f3; }
.bp-section-title { margin: 0 0 26px; font-size: 18px; font-weight: 600; letter-spacing: -.04em; }
.bp-summary { display: grid; grid-template-columns: 108px 1fr; gap: 22px; align-items: start; margin-bottom: 28px; }
.bp-logo { width: 108px; height: 108px; background: #d72f34; color: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.bp-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bp-logo span { font-size: 18px; letter-spacing: .24em; margin-left: .24em; }
.bp-hospital { font-size: 20px; line-height: 1.25; font-weight: 600; margin: 2px 0 8px; letter-spacing: -.04em; }
.bp-address { font-size: 15px; line-height: 1.45; font-weight: 600; margin: 0 0 12px; color: #222; }
.bp-address .bar { color: #aaa; padding: 0 10px; }
.bp-treatment { font-size: 22px; line-height: 1.3; font-weight: 600; margin: 0 0 8px; letter-spacing: -.04em; }
.bp-vat { color: #a9a9a9; font-size: 15px; font-weight: 600; margin-bottom: 2px; }
.bp-vat .old { text-decoration: line-through; margin-left: 6px; }
.bp-price { color: #d40000; font-size: 22px; font-weight: 600; letter-spacing: -.04em; }
.bp-price small { color: #111; font-size: 15px; font-weight: 600; margin-left: 2px; }
.bp-line { border: 0; border-top: 1px solid #ddd; margin: 0; }
.bp-info-row { display: grid; grid-template-columns: 116px 1fr; gap: 16px; align-items: center; min-height: 66px; border-bottom: 1px solid #e1e1e1; }
.bp-label { color: #b0b0b0; font-size: 18px; font-weight: 600; letter-spacing: -.04em; }
.bp-value { color: #111; font-size: 18px; font-weight: 600; letter-spacing: -.04em; }
.bp-red-note { color: #be1838; font-size: 15px; font-weight: 600; margin-top: 8px; }
.bp-check-row { display: flex; align-items: center; gap: 10px; min-height: 58px; border-bottom: 0; font-size: 18px; font-weight: 600; }
.bp-checkbox { width: 14px; height: 14px; accent-color: #df0000; }
.bp-visitor-fields { display: none; padding-top: 18px; }
.bp-visitor-fields.active { display: block; }
.bp-field { margin-bottom: 28px; }
.bp-field label { display: block; font-size: 15px; font-weight: 600; margin-bottom: 10px; }
.bp-field input, .bp-message { width: 100%; height: 44px; border: 1px solid #d9d9d9; padding: 0 16px; font-size: 15px; outline: none; }
.bp-field input:focus, .bp-message:focus { border-color: #be1838; }
.bp-field input::placeholder, .bp-message::placeholder { color: #bbb; }
.bp-visitor-confirm { display: block; min-width: 190px; height: 48px; border: 0; background: #be1838; color: #fff; font-size: 18px; font-weight: 600; margin: 8px auto 30px; }
.bp-visitor-summary { display: none; }
.bp-visitor-summary.active { display: grid; }
.bp-point-box { display: flex; align-items: center; justify-content: space-between; gap: 12px; height: 42px; border: 1px solid #d8d8d8; padding: 0 12px; }
.bp-point-label { font-size: 15px; font-weight: 600; margin-bottom: 10px; }
.bp-point-input { flex: 1; border: 0; outline: none; text-align: right; font-size: 18px; font-weight: 600; min-width: 0; }
.bp-point-unit { font-size: 18px; font-weight: 600; }
.bp-point-max { border: 0; background: transparent; color: #be1838; font-size: 18px; font-weight: 600; padding: 0; white-space: nowrap; }
.bp-point-help { margin-top: 12px; text-align: right; font-size: 13px; font-weight: 600; color: #111; }
.bp-point-help strong { font-weight: 600; }
.bp-point-note { margin-top: 14px; color: #777; font-size: 13px; line-height: 1.55; font-weight: 600; }
.bp-discount-section .bp-section-title { font-size: 18px; font-weight: 600; }
.bp-discount-section .bp-point-label { font-size: 15px; font-weight: 600; }
.bp-discount-section .bp-point-input,
.bp-discount-section .bp-point-unit,
.bp-discount-section .bp-point-max { font-size: 18px; font-weight: 600; }
.bp-discount-section .bp-point-help,
.bp-discount-section .bp-point-help strong,
.bp-discount-section .bp-point-note { font-size: 13px; font-weight: 600; }
.bp-payment-method-section .bp-section-title { font-size: 18px; font-weight: 600; }
.bp-payment-method-section .bp-method { font-size: 18px; font-weight: 600; }
.bp-payment-method-section .bp-installment { font-size: 15px; font-weight: 600; }
.bp-amount-section .bp-section-title { font-size: 18px; font-weight: 600; }
.bp-amount-section .bp-amount-row,
.bp-amount-section .bp-amount-row .right,
.bp-amount-section .bp-total-row .left,
.bp-amount-section .bp-total-row .left small,
.bp-amount-section .bp-total-row .right,
.bp-amount-section .bp-discount-badge { font-size: 15px; font-weight: 600; }
.bp-rules .bp-section-title { font-size: 18px; font-weight: 600; }
.bp-rules .bp-rules-body,
.bp-rules .bp-rules-body p,
.bp-rules .bp-rules-body li,
.bp-rules .bp-rules-body strong { font-size: 13px; font-weight: 600; }
.bp-rules .bp-agree { font-size: 15px; font-weight: 600; }
.bp-rules .bp-legal { font-size: 13px; font-weight: 600; }
.bp-schedule-row { border-top: 1px solid #e1e1e1; border-bottom: 0; min-height: 84px; }
.bp-schedule-row .bp-label, .bp-schedule-row .bp-value { font-size: 18px; font-weight: 600; }
.bp-schedule-row .bp-red-note { font-size: 15px; font-weight: 600; }
.bp-method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.bp-method { height: 76px; border: 1px solid #d8d8d8; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; letter-spacing: -.04em; }
.bp-method.selected { border-color: #be1838; color: #111; }
.bp-method:disabled { color:#999; background:#fafafa; cursor:not-allowed; }
.bp-method-notice { margin:0 0 20px; padding:14px 16px; background:#fff7e6; color:#805400; font-size:14px; line-height:1.65; }
.bp-method-notice p { margin:0; }
.bp-method-notice p + p { margin-top:6px; }
.bp-npay { color: #1ec800; font-size: 25px; font-weight: 900; letter-spacing: -.07em; }
.bp-npay .n { display:inline-flex; width: 24px; height: 24px; align-items:center; justify-content:center; border-radius:50%; background:#1ec800; color:#fff; font-size:17px; margin-right:4px; }
.bp-kakao { display:inline-flex; align-items:center; justify-content:center; min-width: 80px; height: 30px; border-radius: 18px; background:#ffdf00; color:#111; font-size:22px; font-weight:900; letter-spacing:-.08em; }
.bp-kakao::before { content:""; width: 16px; height: 13px; border-radius: 50%; background:#111; margin-right: 2px; }
.bp-toss { color:#2b2f45; font-size:26px; font-weight:800; letter-spacing:-.08em; }
.bp-toss::before { content:""; display:inline-block; width:24px; height:24px; border-radius:50%; background:linear-gradient(135deg,#1258ff 0%,#1258ff 50%,#20a7ff 51%,#20a7ff 100%); margin-right:4px; vertical-align:-3px; }
.bp-installment { display:inline-flex; align-items:center; gap:10px; margin-top:20px; font-size:15px; color:#333; }
.bp-installment::after { content:"›"; font-size:28px; line-height:1; }
.bp-amount-row { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; min-height:32px; font-size:16px; color:#4e4e4e; }
.bp-amount-row + .bp-amount-row { margin-top: 8px; }
.bp-amount-row .right { text-align:right; color:#111; font-weight:700; }
.bp-amount-row.muted .left { padding-left: 14px; position: relative; }
.bp-amount-row.muted .left::before { content:"•"; position:absolute; left:0; top:0; }
.bp-amount-row.muted .right { color:#b5b5b5; font-weight:500; }
.bp-discount-badge { display:inline-block; color:#be1838; background:#fff0f2; padding:2px 10px; margin-right:8px; font-size:13px; }
.bp-total-row { border-top:1px solid #d7d7d7; margin-top:12px; padding-top:12px; display:flex; align-items:baseline; justify-content:space-between; gap:12px; }
.bp-total-row .left { font-size:17px; font-weight:900; }
.bp-total-row .left small { font-size:13px; color:#555; font-weight:500; margin-left:4px; }
.bp-total-row .right { color:#be1838; font-size:19px; font-weight:900; }
.bp-message { min-height: 84px; padding: 13px 16px; resize: vertical; line-height: 1.5; }
.bp-rules-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
.bp-rules-head .bp-section-title { margin:0; }
.bp-chevron { width:28px; height:28px; border:0; background:transparent; position:relative; }
.bp-chevron::before { content:""; position:absolute; left:8px; top:10px; width:12px; height:12px; border-left:2px solid #111; border-top:2px solid #111; transform: rotate(45deg); }
.bp-rules.collapsed .bp-chevron::before { top:5px; transform: rotate(225deg); }
.bp-rules-body { background:#f5f5f5; padding:24px 30px; font-size:13px; line-height:1.85; font-weight:600; }
.bp-rules.collapsed .bp-rules-body { display:none; }
.bp-rules-body p { margin:0 0 18px; }
.bp-rules-body ul { margin: 0 0 22px 18px; padding:0; }
.bp-rules-body li { margin-bottom:8px; }
.bp-rules-body strong { display:block; margin: 22px 0 10px; }
.bp-agree { margin-top:18px; display:flex; align-items:center; gap:10px; font-size:15px; font-weight:600; }
.bp-agree { scroll-margin-top:90px; scroll-margin-bottom:250px; }
.bp-agreement-error { margin:10px 0 0; color:#be1838; font-size:14px; }
.bp-legal { color:#aaa; font-size:13px; line-height:1.8; font-weight:600; margin-top:18px; }
.bp-sticky { position: sticky; bottom: 0; background:#fff; border-top:1px solid #e3e3e3; padding: 18px 28px calc(18px + env(safe-area-inset-bottom)); }
.bp-sticky-total { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin-bottom:16px; }
.bp-sticky-total .left { font-size:15px; font-weight:600; }
.bp-sticky-total .left small { color:#555; font-size:15px; font-weight:600; margin-left:4px; }
.bp-sticky-total .right { display:flex; align-items:baseline; gap:14px; }
.bp-sticky-total .old { color:#b7b7b7; text-decoration:line-through; font-size:16px; }
.bp-sticky-total .new { color:#be1838; font-size:20px; font-weight:600; }
.bp-pay-btn { width:100%; height:56px; border:0; background:#be1838; color:#fff; font-size:18px; font-weight:600; }
.bp-pay-btn:disabled { background:#ddd; color:#999; cursor:not-allowed; }
.bp-preview-btn { width:100%; min-height:48px; margin-top:10px; border:1px solid #be1838; background:#fff; color:#be1838; font-size:16px; font-weight:600; }
.bp-preview-btn.primary { margin-top:0; background:#be1838; color:#fff; }
.bp-preview-btn:disabled { opacity:.55; cursor:wait; }
.bp-checkout-note { margin:8px 0 0; color:#666; text-align:center; font-size:12px; line-height:1.5; }
.bp-alert { margin:0 28px 22px; padding:14px 16px; border-radius:0; font-size:14px; line-height:1.65; }
.bp-alert.warn { background:#fff7e6; color:#8a5a00; border:1px solid #ffe1a6; }
.bp-alert.done { background:#e9f7ef; color:#1b6b3a; border:1px solid #bfe7ce; }
.bp-alert.err { background:#fdecec; color:#9b1c1c; border:1px solid #f7c7c7; }
.bp-status-link { display:block; margin: 12px 28px 28px; height:54px; line-height:54px; text-align:center; background:#be1838; color:#fff; font-weight:900; }
.bp-status-link.disabled { background:#eee; color:#999; }
.bp-result { margin: 12px 28px 0; }
@media (max-width: 420px) {
    .bp-header { height: 64px; }
    .bp-section { padding: 24px 20px 24px; }
    .bp-summary { grid-template-columns: 86px 1fr; gap: 18px; }
    .bp-logo { width: 86px; height: 86px; }
    .bp-logo span { font-size: 15px; }
    .bp-hospital { font-size: 20px; }
    .bp-treatment { font-size: 22px; }
    .bp-price { font-size: 22px; }
    .bp-info-row { grid-template-columns: 96px 1fr; }
    .bp-method-grid { gap: 10px; }
    .bp-method { height: 58px; font-size: 15px; }
    .bp-npay { font-size: 20px; }
    .bp-kakao { transform: scale(.85); }
    .bp-toss { font-size: 20px; }
    .bp-rules-body { padding: 20px 18px; }
    .bp-sticky { padding-left: 20px; padding-right: 20px; }
}
</style>
</head>
<body>
<div class="bp-page">
    <header class="bp-header">
        <a href="my_bookings.php" class="bp-back" aria-label="뒤로"></a>
        <div class="bp-title">결제를 진행해볼까요?</div>
    </header>

    <main class="bp-content">
        <?php if ($fatal !== ''): ?>
            <div class="bp-alert err"><?php echo bp_h($fatal); ?></div>
            <a class="bp-status-link" href="my_bookings.php">내 예약으로</a>
        <?php else: ?>
            <section class="bp-section">
                <h1 class="bp-section-title">예약 정보를 확인해보세요</h1>
                <div class="bp-summary">
                    <div class="bp-logo">
                        <?php if ($logo_url !== ''): ?>
                            <img src="<?php echo bp_h($logo_url); ?>" alt="<?php echo bp_h($hospital_name); ?>">
                        <?php else: ?>
                            <span>toxnfill</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="bp-hospital"><?php echo bp_h($hospital_name ?: '병원명'); ?></div>
                        <div class="bp-address">
                            <?php if ($hospital_area !== ''): ?><span><?php echo bp_h($hospital_area); ?></span><span class="bar">|</span><?php endif; ?>
                            <span><?php echo bp_h($hospital_address ?: '주소 정보 없음'); ?></span>
                        </div>
                        <div class="bp-treatment"><?php echo bp_h($treatment_name); ?></div>
                        <div class="bp-vat">VAT 포함 <span class="old"><?php echo bp_money($display_amount); ?>원</span></div>
                        <div class="bp-price"><?php echo bp_money($display_amount); ?><small>원</small></div>
                    </div>
                </div>

                <hr class="bp-line">
                <div class="bp-info-row">
                    <div class="bp-label">예약자</div>
                    <div class="bp-value"><?php echo bp_h(trim($booker_name . ' / ' . $booker_phone, ' /')); ?></div>
                </div>

                <label class="bp-check-row">
                    <input type="checkbox" class="bp-checkbox" id="visitorToggle" checked>
                    <span>다른 사람이 방문해요</span>
                </label>

                <div class="bp-visitor-fields active" id="visitorFields">
                    <div class="bp-field">
                        <label for="visitorName">방문자 성함이 어떻게 되시나요?</label>
                        <input type="text" id="visitorName" placeholder="방문자 이름을 입력해주세요" autocomplete="name">
                    </div>
                    <div class="bp-field">
                        <label for="visitorPhone">방문하실 분의 휴대폰 번호를 알려주세요</label>
                        <input type="tel" id="visitorPhone" placeholder="010-0000-0000" autocomplete="tel" inputmode="numeric" maxlength="13" pattern="010-[0-9]{4}-[0-9]{4}">
                    </div>
                    <button type="button" class="bp-visitor-confirm" id="visitorConfirm">선택 완료</button>
                </div>

                <div class="bp-info-row bp-visitor-summary" id="visitorSummary">
                    <div class="bp-label">방문자</div>
                    <div class="bp-value" id="visitorSummaryText"></div>
                </div>

                <div class="bp-info-row bp-schedule-row">
                    <div class="bp-label">예약 일정</div>
                    <div>
                        <div class="bp-value"><?php echo bp_h($booking_datetime); ?></div>
                        <div class="bp-red-note">병원에서 확인 후 일정이 확정돼요</div>
                    </div>
                </div>
            </section>

            <?php if ($is_paid): ?>
                <div class="bp-alert done"><?php echo booking_payment_is_test($order['provider'] ?? null) ? '테스트 결제가 완료되었습니다. 실제 결제가 아닙니다.' : '결제가 완료되었습니다.'; ?><?php if (!empty($order['paid_at'])): ?> <?php echo bp_h($order['paid_at']); ?><?php endif; ?></div>
                <a class="bp-status-link" href="my_bookings.php">내 예약으로</a>
            <?php elseif ($order && in_array($order_status, ['refunded','cancelled','failed'], true)): ?>
                <div class="bp-alert warn"><?php echo $order_status === 'refunded' ? '환불이 완료된 결제입니다.' : '이 주문으로 결제를 진행할 수 없습니다.'; ?></div>
                <a class="bp-status-link" href="my_bookings.php">내 예약으로</a>
            <?php elseif (!$is_confirmed): ?>
                <div class="bp-alert warn">병원이 예약을 확정한 뒤에 결제할 수 있습니다. 확정되면 다시 결제를 진행해주세요.</div>
                <a class="bp-status-link disabled" href="my_bookings.php">내 예약으로</a>
            <?php elseif (!$ledger_ready || $order_error || !$order): ?>
                <div class="bp-alert err"><?php echo bp_h($order_error ?: '결제 준비가 완료되지 않았습니다. 잠시 후 다시 시도하거나 병원에 문의해주세요.'); ?></div>
                <a class="bp-status-link disabled" href="my_bookings.php">내 예약으로</a>
            <?php else: ?>
                <section class="bp-section bp-discount-section">
                    <h2 class="bp-section-title">적용 가능한 할인을 확인해보세요</h2>
                    <div class="bp-point-label">포인트</div>
                    <div class="bp-point-box">
                        <input class="bp-point-input" id="pointInput" <?php echo ($point_use_ready && $max_point_use > 0) ? '' : 'disabled'; ?> inputmode="numeric" value="<?php echo bp_money($point_use_ready ? $points_applied : 0); ?>" aria-label="사용할 포인트">
                        <span class="bp-point-unit">P</span>
                        <button type="button" class="bp-point-max" id="pointMaxBtn" <?php echo ($point_use_ready && $max_point_use > 0) ? '' : 'disabled'; ?>>최대적용</button>
                    </div>
                    <div class="bp-point-help">사용가능 <strong><?php echo bp_money($max_point_use); ?>P</strong> / 보유 <?php echo bp_money($point_balance); ?>P</div>
                    <?php if ($point_use_ready): ?>
                    <div class="bp-point-note">1P = 1원으로 사용되며, 최소 <?php echo bp_money(points_v2_int($connect, 'v2_point_use_min_pay', 1000)); ?>원은 결제해야 합니다. 결제를 취소하면 사용한 포인트는 복구됩니다.</div>
                    <?php else: ?>
                    <div class="bp-point-note">포인트 사용 및 결제 적립은 준비 중입니다. 현재 결제 금액에는 적용되지 않습니다.</div>
                    <?php endif; ?>
                </section>

                <section class="bp-section bp-payment-method-section">
                    <h2 class="bp-section-title">어떤 결제 수단을 이용하시겠어요?</h2>
                    <?php if (!$live_available): ?>
                    <div class="bp-method-notice" id="paymentMethodNotice">
                        <p>실제 결제는 준비 중입니다. 현재 결제 수단을 선택할 수 없습니다.</p>
                        <?php if ($preview_available): ?>
                        <p>결제 수단을 선택하지 않아도 아래 ‘결제 없이 계속하기’로 진행할 수 있어요.</p>
                        <?php else: ?>
                        <p>결제 관련 문의는 병원으로 연락해주세요.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="bp-method-grid" id="methodGrid">
                        <?php foreach (['card'=>'신용/체크카드', 'bank'=>'계좌이체', 'naverpay'=>'네이버페이', 'kakaopay'=>'카카오페이', 'tosspay'=>'토스페이'] as $method => $label): ?>
                        <button type="button" class="bp-method<?php echo $live_available && $method === array_key_first($channels) ? ' selected' : ''; ?>" data-provider="<?php echo bp_h($method); ?>" <?php echo !$live_available || !isset($channels[$method]) ? 'disabled' : ''; ?>><?php echo bp_h($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <a class="bp-installment" href="javascript:void(0)">신용카드 무이자 할부 안내</a>
                </section>

                <section class="bp-section bp-amount-section">
                    <h2 class="bp-section-title">결제 금액을 확인해보세요</h2>
                    <div class="bp-amount-row"><div class="left">시술 금액</div><div class="right"><?php echo bp_money($display_amount); ?>원</div></div>
                    <div class="bp-amount-row muted"><div class="left">할인</div><div class="right"><span class="bp-discount-badge">0%</span> -0원</div></div>
                    <div class="bp-amount-row muted"><div class="left">포인트 사용 금액</div><div class="right" id="pointUseSummary">-0원</div></div>
                    <div class="bp-total-row"><div class="left">총 결제 금액 <small>(VAT포함)</small></div><div class="right" id="totalSummary"><?php echo bp_money($display_amount); ?>원</div></div>
                    <div class="bp-amount-row" style="margin-top:14px"><div class="left">예상 적립 포인트</div><div class="right" id="expectedPoints"><?php echo bp_money($expected_points); ?> P</div></div>
                </section>

                <section class="bp-section">
                    <h2 class="bp-section-title">병원에 전달할 내용이 있으신가요?</h2>
                    <textarea class="bp-message" id="memo" placeholder="전달할 내용을 입력해주세요"></textarea>
                </section>

                <section class="bp-section bp-rules" id="rulesSection">
                    <div class="bp-rules-head">
                        <h2 class="bp-section-title">취소 및 환불 규정을 확인해주세요</h2>
                        <button type="button" class="bp-chevron" id="rulesToggle" aria-label="규정 접기"></button>
                    </div>
                    <div class="bp-rules-body">
                        <p>예약 취소와 결제 환불은 별도로 처리됩니다.</p>
                        <ul>
                            <li>예약 변경·취소는 예약 내역에서 요청할 수 있습니다.</li>
                            <li>이미 결제한 예약의 환불은 운영자가 결제 내역과 병원의 환불 조건을 확인한 후 처리합니다.</li>
                            <li>실제 환불이 확인되면 결제 내역에 환불 금액과 상태가 반영됩니다.</li>
                            <li>결제 없이 계속한 예약은 실제 청구가 없어 환불 대상 금액이 없습니다.</li>
                        </ul>
                    </div>
                    <label class="bp-agree" id="termsAgreeRow"><input type="checkbox" class="bp-checkbox" id="termsAgree" aria-describedby="termsError"> 위 내용을 모두 확인했어요</label>
                    <p class="bp-agreement-error" id="termsError" role="alert" hidden>취소 및 환불 규정을 확인하고 동의해주세요.</p>
                    <div class="bp-legal">
                        서비스 이용에 관한 개인정보 수집 동의<br>
                        개인정보 및 민감정보 수집 이용, 개인정보 제3자 제공 동의<br><br>
                        서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공합니다. 이 기능에 대해 의료기관으로부터 대가를 받지 않습니다.<br>
                        거래의 당사자는 고객과 병원이며, 서울미녀는 이를 중개하지 않습니다. 위 내용을 확인하였으며, 결제에 동의합니다.
                    </div>
                </section>

                <?php if ($preview): ?><div class="bp-alert warn">결제 없이 계속한 예약입니다. 실제 결제 내역은 없습니다.</div><?php endif; ?>
                <?php if ($pay_notice !== ''): ?><div class="bp-alert warn" role="alert"><?php echo bp_h($pay_notice); ?></div><?php endif; ?>
                <div id="payResult" class="bp-result"></div>
                <button type="button" class="bp-visitor-confirm" id="checkPayBtn">이미 결제했다면 상태 확인</button>

                <div class="bp-sticky">
                    <div class="bp-sticky-total">
                        <div class="left">총 결제 금액 <small>(VAT포함)</small></div>
                        <div class="right"><span class="old"><?php echo bp_money($display_amount); ?>원</span><span class="new" id="stickyTotal"><?php echo bp_money($display_amount); ?>원</span></div>
                    </div>
                    <?php if ($live_available || !$preview_available): ?>
                    <button type="button" class="bp-pay-btn" id="payBtn" <?php echo $live_available ? '' : 'disabled'; ?>><?php echo $live_available ? '결제하기' : '온라인 결제 준비 중'; ?></button>
                    <?php endif; ?>
                    <?php if ($preview_available): ?>
                    <button type="button" class="bp-preview-btn<?php echo $live_available ? '' : ' primary'; ?>" id="previewPayBtn">결제 없이 계속하기</button>
                    <p class="bp-checkout-note">실제 청구 없이 진행하며, 결제·정산 내역에 포함되지 않습니다.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($live_available): ?><script src="https://js.tosspayments.com/v2/standard"></script><?php endif; ?>
<script>
(function() {
    var baseAmount = <?php echo (int)$display_amount; ?>;
    var maxPoint = <?php echo (int)$max_point_use; ?>;
    var expectedPoints = <?php echo (int)$expected_points; ?>;
    var bookingId = <?php echo (int)$booking_id; ?>;
    var csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
    var selectedProvider = <?php echo json_encode(array_key_first($channels) ?: 'card'); ?>;

    function money(n) {
        n = Math.max(0, parseInt(n || 0, 10));
        return n.toLocaleString('ko-KR');
    }
    function cleanNumber(v) {
        return String(v || '').replace(/[^0-9]/g, '');
    }
    function escapeHtml(v) {
        return String(v || '').replace(/[&<>"]/g, function(ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]);
        });
    }
    function formatPhone(v) {
        var d = cleanNumber(v);
        if (d.length === 11) return d.slice(0,3) + '-' + d.slice(3,7) + '-' + d.slice(7);
        if (d.length === 10) return d.slice(0,3) + '-' + d.slice(3,6) + '-' + d.slice(6);
        return v;
    }
    function normalizeVisitorPhone(v) {
        var d = cleanNumber(v);
        if (d === '') return '010-';
        if (d.indexOf('010') !== 0) {
            d = '010' + d.replace(/^0+/, '');
        }
        d = d.slice(0, 11);
        if (d.length <= 3) return '010-';
        if (d.length <= 7) return d.slice(0, 3) + '-' + d.slice(3);
        return d.slice(0, 3) + '-' + d.slice(3, 7) + '-' + d.slice(7);
    }
    function isValidVisitorPhone(v) {
        return /^010-[0-9]{4}-[0-9]{4}$/.test(String(v || ''));
    }
    function currentPointUse() {
        var input = document.getElementById('pointInput');
        if (!input) return 0;
        var n = parseInt(cleanNumber(input.value), 10) || 0;
        if (n > maxPoint) n = maxPoint;
        if (n > baseAmount) n = baseAmount;
        if (String(input.value) !== money(n)) input.value = money(n);
        return n;
    }
    function updateTotals() {
        var used = currentPointUse();
        var total = Math.max(0, baseAmount - used);
        var pointUseSummary = document.getElementById('pointUseSummary');
        var totalSummary = document.getElementById('totalSummary');
        var stickyTotal = document.getElementById('stickyTotal');
        var expected = document.getElementById('expectedPoints');
        if (pointUseSummary) pointUseSummary.textContent = '-' + money(used) + '원';
        if (totalSummary) totalSummary.textContent = money(total) + '원';
        if (stickyTotal) stickyTotal.textContent = money(total) + '원';
        if (expected) expected.textContent = expectedPoints > 0 ? money(expectedPoints) + ' P' : '준비 중';
    }
    function showResult(message, type) {
        var box = document.getElementById('payResult');
        if (!box) return;
        box.innerHTML = '<div class="bp-alert ' + (type || 'warn') + '">' + escapeHtml(message) + '</div>';
        box.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    }

    var visitorToggle = document.getElementById('visitorToggle');
    var visitorFields = document.getElementById('visitorFields');
    var visitorSummary = document.getElementById('visitorSummary');
    var visitorSummaryText = document.getElementById('visitorSummaryText');
    var visitorConfirm = document.getElementById('visitorConfirm');
    var visitorPhoneInput = document.getElementById('visitorPhone');
    if (visitorToggle && visitorFields && visitorSummary) {
        visitorToggle.addEventListener('change', function() {
            visitorFields.classList.toggle('active', visitorToggle.checked);
            visitorSummary.classList.remove('active');
            if (visitorToggle.checked && visitorPhoneInput && visitorPhoneInput.value.trim() === '') {
                visitorPhoneInput.value = '010-';
            }
            if (!visitorToggle.checked) {
                document.getElementById('visitorName').value = '';
                if (visitorPhoneInput) visitorPhoneInput.value = '';
            }
        });
    }
    if (visitorPhoneInput) {
        visitorPhoneInput.addEventListener('focus', function() {
            if (visitorPhoneInput.value.trim() === '') visitorPhoneInput.value = '010-';
        });
        visitorPhoneInput.addEventListener('input', function() {
            visitorPhoneInput.value = normalizeVisitorPhone(visitorPhoneInput.value);
        });
        visitorPhoneInput.addEventListener('blur', function() {
            if (visitorPhoneInput.value === '010-') visitorPhoneInput.value = '';
        });
    }
    if (visitorConfirm) {
        visitorConfirm.addEventListener('click', function() {
            var name = document.getElementById('visitorName').value.trim();
            var phone = visitorPhoneInput ? normalizeVisitorPhone(visitorPhoneInput.value) : '';
            if (visitorPhoneInput) visitorPhoneInput.value = phone;
            if (!name || !isValidVisitorPhone(phone)) {
                showResult('방문자 이름과 010-0000-0000 형식의 휴대폰 번호를 입력해주세요.', 'warn');
                return;
            }
            visitorSummaryText.textContent = name + ' / ' + phone;
            visitorSummary.classList.add('active');
            visitorFields.classList.remove('active');
        });
    }

    var pointInput = document.getElementById('pointInput');
    if (pointInput) {
        pointInput.addEventListener('input', updateTotals);
        pointInput.addEventListener('blur', updateTotals);
    }
    var pointMaxBtn = document.getElementById('pointMaxBtn');
    if (pointMaxBtn) {
        pointMaxBtn.addEventListener('click', function() {
            document.getElementById('pointInput').value = money(maxPoint);
            updateTotals();
        });
    }

    document.querySelectorAll('.bp-method').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.bp-method').forEach(function(other) { other.classList.remove('selected'); });
            btn.classList.add('selected');
            selectedProvider = btn.getAttribute('data-provider') || 'bank';
        });
    });

    var rulesToggle = document.getElementById('rulesToggle');
    if (rulesToggle) {
        rulesToggle.addEventListener('click', function() {
            document.getElementById('rulesSection').classList.toggle('collapsed');
        });
    }

    var payBtn = document.getElementById('payBtn');
    var previewBtn = document.getElementById('previewPayBtn');
    var checkBtn = document.getElementById('checkPayBtn');
    var busy = false;
    var liveAvailable = <?php echo $live_available ? 'true' : 'false'; ?>;
    function setBusy(value) {
        busy = value;
        if (payBtn) payBtn.disabled = value || !liveAvailable;
        if (previewBtn) previewBtn.disabled = value;
        if (checkBtn) checkBtn.disabled = value;
    }
    function post(url, data) {
        var body = new URLSearchParams(Object.assign({booking_id:String(bookingId), csrf_token:csrfToken}, data));
        return fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:body.toString(), credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
                if (!d.success) throw new Error(d.message || '요청을 처리하지 못했습니다.');
                return d;
            });
    }
    function failed(error) {
        showResult(error.message || '연결이 끊겼습니다. 다시 결제하기 전에 결제 상태를 확인해주세요.', 'warn');
        setBusy(false);
    }
    function agreed() {
        var terms = document.getElementById('termsAgree');
        if (!terms || !terms.checked) {
            var error = document.getElementById('termsError');
            if (error) error.hidden = false;
            if (terms) {
                terms.setAttribute('aria-invalid', 'true');
                terms.focus({preventScroll: true});
                document.getElementById('termsAgreeRow').scrollIntoView({block:'center', behavior:'smooth'});
            }
            return false;
        }
        return true;
    }
    var termsAgree = document.getElementById('termsAgree');
    if (termsAgree) termsAgree.addEventListener('change', function() {
        if (termsAgree.checked) {
            termsAgree.removeAttribute('aria-invalid');
            document.getElementById('termsError').hidden = true;
        }
    });
    function verify() {
        return post('booking_payment_verify.php', {}).then(function(d) { location.href = d.redirect_url; });
    }
    if (payBtn) payBtn.addEventListener('click', function() {
        if (busy || !agreed()) return;
        setBusy(true);
        // 서버가 금액·주문번호를 정하고, Toss 결제창은 successUrl/failUrl 로 돌아온다.
        // 실제 승인은 booking_payment_toss_success.php 에서 서버가 한다.
        post('booking_payment_request.php', {provider:selectedProvider, point_use:String(currentPointUse())}).then(function(d) {
            if (typeof window.TossPayments !== 'function') throw new Error('결제창을 불러오지 못했습니다. 새로고침해주세요.');
            var payment = window.TossPayments(d.payment.clientKey).payment({customerKey: window.TossPayments.ANONYMOUS});
            return payment.requestPayment(d.payment.request);
        }).catch(function(error) {
            if (error && error.code === 'USER_CANCEL') {
                showResult('결제를 취소했습니다. 다시 결제하려면 결제하기를 눌러주세요.', 'warn');
                setBusy(false);
                return;
            }
            failed(error);
        });
    });
    if (previewBtn) previewBtn.addEventListener('click', function() {
        if (busy || !agreed()) return;
        setBusy(true);
        post('booking_payment_mock_complete.php', {}).then(function(d) { location.href = d.redirect_url; }).catch(failed);
    });
    if (checkBtn) checkBtn.addEventListener('click', function() {
        if (busy) return;
        setBusy(true);
        verify().catch(failed);
    });
    updateTotals();
})();
</script>
</body>
</html>

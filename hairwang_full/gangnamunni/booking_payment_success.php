<?php
/**
 * 예약 결제 완료 후 확정 안내 화면.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_access.php';
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

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

function bps_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function bps_digits($value) { return preg_replace('/[^0-9]/', '', (string)$value); }
function bps_phone_plain($value) { return bps_digits($value) ?: (string)$value; }
function bps_date($date, $time) {
    $ts = strtotime(trim((string)$date . ' ' . (string)$time));
    if (!$ts) return trim((string)$date . ' ' . substr((string)$time, 0, 5));
    $week = ['일','월','화','수','목','금','토'][(int)date('w', $ts)];
    $hour = (int)date('G', $ts);
    $minute = date('i', $ts);
    $ampm = $hour < 12 ? '오전' : '오후';
    $display_hour = $hour % 12;
    if ($display_hour === 0) $display_hour = 12;
    return date('y.m.d', $ts) . ' (' . $week . ') ' . $ampm . ' ' . $display_hour . ':' . $minute;
}
function bps_truncate($value, $limit = 22) {
    $value = trim((string)$value);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') . '...' : $value;
    }
    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}

$booking_id = max(0, (int)($_GET['booking_id'] ?? 0));
if ($booking_id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

$stmt = mysqli_prepare($connect,
    "SELECT b.*, h.name AS h_name
     FROM gangnamunni_bookings b
     LEFT JOIN gangnamunni_hospitals h ON h.id = b.hospital_id
     WHERE b.id = ?
       AND (
            (? <> '' AND b.mb_id = ?)
         OR ((b.mb_id IS NULL OR b.mb_id = '') AND ? <> ''
             AND REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?)
       )
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'issss', $booking_id, $logged_mb_id, $logged_mb_id, $owner_phone_digits, $owner_phone_digits);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$fatal = '';
if (!$booking) {
    http_response_code(404);
    $fatal = '예약을 찾을 수 없거나 접근 권한이 없습니다.';
}

$payment_title = '결제 확인 중';
$payment_description = '결제가 아직 확인되지 않았습니다. 결제 화면에서 상태를 다시 확인해주세요.';
if ($booking) {
    try {
        $order = booking_payment_order($connect, $booking_id);
        $preview = booking_payment_previews($connect, [$booking_id]);
        if ($order && in_array($order['status'], ['paid', 'partially_refunded'], true)) {
            $test = booking_payment_is_test($order['provider']);
            $payment_title = $test ? '테스트 결제 완료' : '결제가 완료되었어요!';
            $payment_description = $test ? '테스트 결과이며 실제 결제·정산에 포함되지 않습니다.' : '결제가 확인되었습니다. 예약 내역에서 상세 정보를 확인하세요.';
        } elseif ($order && $order['status'] === 'refunded') {
            $payment_title = '환불 완료';
            $payment_description = '환불된 결제입니다. 예약 내역을 확인해주세요.';
        } elseif ($preview) {
            $payment_title = '결제 없이 계속했어요';
            $payment_description = '실제 결제는 이루어지지 않았으며 결제·정산 내역에 포함되지 않습니다.';
        }
    } catch (Throwable $e) { error_log('[payment_success] ' . $e->getMessage()); }
}

$hospital_name = $booking ? ($booking['h_name'] ?: ($booking['hospital_name'] ?? '')) : '';
$treatment = $booking ? bps_truncate($booking['treatment'] ?? '-', 24) : '';
$booking_date = $booking ? bps_date($booking['booking_date'] ?? '', $booking['booking_time'] ?? '') : '';
$booker = $booking ? trim(($booking['name'] ?? '') . ' / ' . bps_phone_plain($booking['phone'] ?? ''), ' /') : '';
$booking_no = $booking ? (string)($booking['booking_no'] ?? $booking['reservation_no'] ?? $booking['id']) : '';
$bottom_nav_active = 'my';
?><!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>예약 확정</title>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #3f3f3f; color: #111; font-family: "Noto Sans KR", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
a { color: inherit; text-decoration: none; }
.bps-page { max-width: 640px; min-height: 100vh; margin: 0 auto; background: #fff; padding-bottom: 96px; position: relative; }
.bps-header { height: 72px; display:flex; align-items:center; justify-content:center; position:relative; }
.bps-back { position:absolute; left:22px; top:50%; width:28px; height:28px; transform:translateY(-50%); }
.bps-back::before { content:""; position:absolute; left:8px; top:5px; width:16px; height:16px; border-left:2px solid #111; border-bottom:2px solid #111; transform:rotate(45deg); }
.bps-title { font-size:20px; font-weight:500; letter-spacing:-.04em; }
.bps-main { padding: 12px 28px 0; text-align:center; }
.bps-check { width:58px; height:58px; margin:0 auto 22px; border-radius:50%; background:#be1838; position:relative; }
.bps-check::after { content:""; position:absolute; left:17px; top:14px; width:25px; height:15px; border-left:4px solid #fff; border-bottom:4px solid #fff; transform:rotate(-45deg); }
.bps-heading { margin:0 0 22px; font-size:24px; line-height:1.25; font-weight:700; letter-spacing:-.05em; }
.bps-desc { margin:0; font-size:18px; line-height:1.65; font-weight:600; letter-spacing:-.04em; }
.bps-info { width:100%; max-width:420px; margin:48px auto 20px; background:#fff1f3; padding:28px 30px; text-align:left; }
.bps-info-title { margin:0 0 24px; font-size:18px; font-weight:700; letter-spacing:-.04em; }
.bps-row { display:grid; grid-template-columns:86px 1fr; gap:14px; align-items:start; font-size:15px; line-height:1.45; margin-top:18px; font-weight:600; }
.bps-label { color:#111; }
.bps-value { color:#111; min-width:0; }
.bps-button { display:inline-flex; align-items:center; justify-content:center; width:218px; height:54px; border:1px solid #be1838; color:#be1838; background:#fff; font-size:18px; font-weight:600; letter-spacing:-.04em; }
.bps-alert { max-width:420px; margin:40px auto; padding:18px; background:#fdecec; color:#9b1c1c; font-size:15px; line-height:1.6; text-align:left; }
@media (max-width:420px) {
    .bps-header { height:64px; }
    .bps-info { margin-top:42px; margin-bottom:20px; padding:24px 24px; }
    .bps-heading { font-size:22px; }
    .bps-desc { font-size:16px; }
    .bps-row { grid-template-columns:78px 1fr; }
}
</style>
</head>
<body>
<div class="bps-page">
    <header class="bps-header">
        <a class="bps-back" href="my_bookings.php" aria-label="뒤로"></a>
        <div class="bps-title">예약 확정</div>
    </header>

    <main class="bps-main">
        <?php if ($fatal !== ''): ?>
            <div class="bps-alert"><?php echo bps_h($fatal); ?></div>
            <a class="bps-button" href="my_bookings.php">예약 내역 보기</a>
        <?php else: ?>
            <div class="bps-check" aria-hidden="true"></div>
            <h1 class="bps-heading"><?php echo bps_h($payment_title); ?></h1>
            <p class="bps-desc"><?php echo bps_h($payment_description); ?></p>

            <section class="bps-info" aria-label="예약 정보">
                <h2 class="bps-info-title">예약 정보</h2>
                <div class="bps-row"><div class="bps-label">병원</div><div class="bps-value"><?php echo bps_h($hospital_name); ?></div></div>
                <div class="bps-row"><div class="bps-label">시술</div><div class="bps-value"><?php echo bps_h($treatment); ?></div></div>
                <div class="bps-row"><div class="bps-label">날짜/시간</div><div class="bps-value"><?php echo bps_h($booking_date); ?></div></div>
                <div class="bps-row"><div class="bps-label">예약자</div><div class="bps-value"><?php echo bps_h($booker); ?></div></div>
                <div class="bps-row"><div class="bps-label">예약번호</div><div class="bps-value"><?php echo bps_h($booking_no); ?></div></div>
            </section>

            <a class="bps-button" href="booking_payment.php?booking_id=<?php echo $booking_id; ?>">결제 상태 확인</a>
            <a class="bps-button" href="my_bookings.php">예약 내역 보기</a>
        <?php endif; ?>
    </main>
</div>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>

<?php
require_once __DIR__ . '/includes/init.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
global $is_member, $member;

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) { header('Location: my_bookings.php'); exit; }
$stmt = mysqli_prepare($connect, "SELECT id,mb_id,phone,status FROM gangnamunni_bookings WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$booking || $booking['status'] !== 'cancelled') { header('Location: my_bookings.php'); exit; }

$logged_in = isset($is_member) && $is_member && !empty($member['mb_id']);
$require_login = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_require_login', '1') === '1' : true;
$phone_auth = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_phone_auth', '0') === '1' : false;
if ($require_login && !$phone_auth) {
    $is_owner = $logged_in && hash_equals((string)$booking['mb_id'], (string)$member['mb_id']);
} else {
    $my_phone = preg_replace('/[^0-9]/', '', (string)($member['mb_hp'] ?? ($_SESSION['user_phone'] ?? '')));
    $booking_phone = preg_replace('/[^0-9]/', '', (string)$booking['phone']);
    $is_owner = $my_phone !== '' && hash_equals($booking_phone, $my_phone);
}
if (!$is_owner) { header('Location: my_bookings.php'); exit; }
unset($_SESSION['booking_cancel_reason'][$booking_id]);
?>
<!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>취소 완료</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--color-primary:#AC1830;--color-bg:#fff;--color-text:#1A1718;--color-inactive:#9A9395}*{box-sizing:border-box}html{background:#414141}body{max-width:640px;min-height:100vh;margin:0 auto;padding-bottom:83px;background:#fff;color:#1A1718;letter-spacing:-.01em;font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}.header{height:52px;display:grid;grid-template-columns:52px 1fr 52px;align-items:center;padding:0 8px}.back{width:44px;height:44px;border:0;background:none;color:#1A1718;font-size:26px;cursor:pointer}.title{text-align:center;font-size:20px;font-weight:500;white-space:nowrap}.complete{padding:70px 20px 40px;text-align:center}.check{display:block;width:48px;height:48px;margin:0 auto;object-fit:contain}h1{margin:16px 0 0;font-size:24px;font-weight:700;line-height:1.4;letter-spacing:-.04em}.sub{margin:10px 0 0;font-size:18px;font-weight:400;color:#4A4446}.notice{margin:28px 20px 0;padding:18px 16px 16px;background:#FBEFF1}.notice-title{font-size:18px;font-weight:700}.notice-time{margin:4px 0 12px;font-size:16px;font-weight:400;line-height:1.6;color:#4A4446}.notice-inner{padding:14px 10px;background:#fff;font-size:16px;font-weight:400;line-height:1.6;color:#4A4446}.notice-inner strong{display:block;margin-bottom:4px;color:#AC1830;font-size:18px;font-weight:700}.history-link{width:180px;height:52px;margin:40px auto 0;border:1px solid #AC1830;border-radius:4px;background:#fff;display:flex;align-items:center;justify-content:center;color:#AC1830;font-size:19px;font-weight:500;text-decoration:none}@media(max-width:420px){.notice{margin-left:20px;margin-right:20px}}
</style>
</head>
<body>
<header class="header"><button type="button" class="back" onclick="location.href='my_bookings.php?tab=cancelled'" aria-label="뒤로"><i class="bi bi-chevron-left"></i></button><div class="title">취소 완료</div><span></span></header>
<main class="complete">
 <img class="check" src="assets/icons/icon-change-requested.png?v=1" alt="">
 <h1>예약 취소 요청이 접수되었어요!</h1>
 <p class="sub">취소가 완료되면 푸시로 알려드릴게요</p>
 <section class="notice"><div class="notice-title">예상 확인 시간</div><div class="notice-time">평균 1~3시간 이내<br>(병원 상황에 따라 달라질 수 있어요)</div><div class="notice-inner"><strong>알림을 꼭 확인해주세요!</strong>예약 진행 상황은 알림(푸시)으로 안내해드려요<br>알림을 허용해두시면 빠르게 확인하실 수 있어요</div></section>
 <a class="history-link" href="my_bookings.php?tab=cancelled">예약 내역 보기</a>
</main>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>

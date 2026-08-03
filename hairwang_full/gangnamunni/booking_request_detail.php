<?php
/**
 * 예약 요청 상세 — my_bookings.php 의 예약상세 전용 읽기 화면.
 * 예약 변경/취소 기능이 있는 booking_detail.php 와 분리한다.
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}

global $is_member, $member;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) { header('Location: my_bookings.php'); exit; }

$stmt = mysqli_prepare($connect,
    "SELECT b.* FROM gangnamunni_bookings b WHERE b.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$bk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$bk) { header('Location: my_bookings.php'); exit; }

// booking_detail.php/API와 같은 본인 예약 검증.
$is_logged_in = isset($is_member) && $is_member && !empty($member['mb_id']);
$require_login = function_exists('getSettingValue')
    ? getSettingValue($connect, 'booking_require_login', '1') === '1'
    : true;
$phone_auth = function_exists('getSettingValue')
    ? getSettingValue($connect, 'booking_phone_auth', '0') === '1'
    : false;
$is_owner = false;
if ($require_login && !$phone_auth) {
    $is_owner = $is_logged_in && hash_equals((string)($bk['mb_id'] ?? ''), (string)$member['mb_id']);
} else {
    $my_phone = preg_replace('/[^0-9]/', '', (string)($member['mb_hp'] ?? ($_SESSION['user_phone'] ?? '')));
    $booking_phone = preg_replace('/[^0-9]/', '', (string)($bk['phone'] ?? ''));
    $is_owner = $my_phone !== '' && hash_equals($booking_phone, $my_phone);
}
if (!$is_owner) { header('Location: my_bookings.php'); exit; }

$is_active = in_array($bk['status'] ?? '', ['pending', 'confirmed'], true);
$status_labels = [
    'pending' => '예약대기', 'confirmed' => '예약확정', 'completed' => '시술완료',
    'cancelled' => '예약취소', 'no_show' => '노쇼',
];
$status_label = $status_labels[$bk['status'] ?? ''] ?? ($bk['status'] ?? '');
$dday = '';
if ($is_active && !empty($bk['booking_date'])) {
    $diff = (int)(new DateTime('today'))->diff(new DateTime($bk['booking_date']))->format('%r%a');
    $dday = $diff > 0 ? 'D-' . $diff : ($diff === 0 ? 'D-0' : 'D+' . abs($diff));
}

function request_detail_date($date, $time) {
    if (!$date || !$time) return '-';
    $days = ['일','월','화','수','목','금','토'];
    $ts = strtotime($date . ' ' . $time);
    $hour = (int)date('G', $ts);
    $ampm = $hour < 12 ? '오전' : '오후';
    $hour12 = $hour % 12 ?: 12;
    return date('n', $ts) . '월 ' . date('j', $ts) . '일 (' . $days[(int)date('w', $ts)] . ') '
        . $ampm . ' ' . $hour12 . ':' . date('i', $ts);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>예약 내용을 확인해보세요</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root { --primary:#AE1932; }
* { box-sizing:border-box; margin:0; padding:0; }
html { background:#3f3f3f; }
body { max-width:640px; min-height:100vh; margin:0 auto; padding-bottom:24px; background:#fff; color:#111; font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif; }
.request-header { height:64px; display:grid; grid-template-columns:44px 1fr 44px; align-items:center; padding:0 16px; }
.request-back { width:44px; height:44px; border:0; background:none; font-size:27px; color:#111; cursor:pointer; }
.request-title { text-align:center; font-size:18px; font-weight:650; letter-spacing:-.04em; white-space:nowrap; }
.request-body { padding:0 20px; }
.status-badge { display:inline-flex; align-items:center; gap:28px; margin:22px 0 18px; padding:11px 16px; background:var(--primary); color:#fff; font-size:18px; font-weight:700; }
.detail-list { border-bottom:1px solid #ddd; }
.detail-row { min-height:62px; display:grid; grid-template-columns:102px minmax(0,1fr); align-items:center; border-top:1px solid #ddd; }
.detail-row:first-child { border-top:0; }
.detail-label { color:#aaa; font-size:16px; font-weight:600; }
.detail-value { min-width:0; overflow:hidden; color:#111; font-size:16px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.person-card { margin-top:20px; padding:17px 20px; background:#f3f3f3; display:grid; grid-template-columns:88px 1fr; row-gap:8px; }
.person-label { color:#aaa; font-size:16px; font-weight:600; }
.person-value { font-size:16px; font-weight:700; }
.notice { margin-top:24px; padding:24px 28px; background:#fff1f3; }
.notice-title { margin-bottom:10px; color:var(--primary); font-size:17px; font-weight:700; }
.notice ul { padding-left:18px; font-size:13px; line-height:1.9; }
.notice em { color:var(--primary); font-style:normal; }
.confirm-wrap { margin-top:24px; }
.confirm-btn { width:100%; height:54px; border:0; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; text-decoration:none; }
@media (max-width:380px) {
    .request-body { padding-left:16px; padding-right:16px; }
    .detail-row { grid-template-columns:88px minmax(0,1fr); }
    .detail-label,.detail-value,.person-label,.person-value { font-size:15px; }
    .notice { padding-left:20px; padding-right:20px; }
}
</style>
</head>
<body>
<header class="request-header">
    <button type="button" class="request-back" onclick="location.href='my_bookings.php'" aria-label="뒤로"><i class="bi bi-chevron-left"></i></button>
    <h1 class="request-title">예약 내용을 확인해보세요</h1>
    <span aria-hidden="true"></span>
</header>
<main class="request-body">
    <div class="status-badge">
        <span><?php echo $is_active ? '시술예정' : htmlspecialchars($status_label); ?></span>
        <?php if ($dday !== ''): ?><span><?php echo htmlspecialchars($dday); ?></span><?php endif; ?>
    </div>
    <div class="detail-list">
        <div class="detail-row"><span class="detail-label">예약번호</span><span class="detail-value"><?php echo (int)$bk['id']; ?></span></div>
        <div class="detail-row"><span class="detail-label">날짜/시간</span><span class="detail-value"><?php echo htmlspecialchars(request_detail_date($bk['booking_date'], $bk['booking_time'])); ?></span></div>
        <div class="detail-row"><span class="detail-label">선택시술</span><span class="detail-value"><?php echo htmlspecialchars($bk['treatment'] ?: '-'); ?></span></div>
        <div class="detail-row"><span class="detail-label">병원</span><span class="detail-value"><?php echo htmlspecialchars($bk['hospital_name'] ?: '-'); ?></span></div>
    </div>
    <div class="person-card">
        <span class="person-label">예약자</span><span class="person-value"><?php echo htmlspecialchars($bk['name'] ?: '-'); ?></span>
        <span class="person-label">연락처</span><span class="person-value"><?php echo htmlspecialchars($bk['phone'] ?: '-'); ?></span>
    </div>
    <?php if ($is_active): ?>
    <section class="notice">
        <h2 class="notice-title">고객님 꼭 알아두세요!</h2>
        <ul>
            <li>예약 가능 여부는 병원 상황에 따라 달라질 수 있어요</li>
            <li>예약이 불가능한 경우, 상담원이 다른 일정으로 안내해드려요</li>
            <li>예약 확정 전까지는 결제가 진행되지 않아요</li>
            <li>결제는 예약이 가능하다고 확인된 후에 진행됩니다</li>
            <li>예약 현황 / 예약 변경 / 취소는 <em>내 예약 결제내역</em>에서 직접 관리할 수 있어요</li>
        </ul>
    </section>
    <?php endif; ?>
    <div class="confirm-wrap"><a href="my_bookings.php" class="confirm-btn">확인했어요</a></div>
</main>
</body>
</html>

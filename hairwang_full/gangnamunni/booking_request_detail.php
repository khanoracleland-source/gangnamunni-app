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

// Payment summary: use the verified booking payment when available, while
// keeping the booked treatment price as a safe fallback before payment.
$treatment_price = max(0, (int)($bk['price'] ?? 0));
$total_payment = $treatment_price;
$payment_table = mysqli_query($connect,
    "SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments' LIMIT 1");
if ($payment_table && mysqli_num_rows($payment_table) > 0) {
    $payment_stmt = mysqli_prepare($connect,
        "SELECT amount FROM gangnamunni_platform_payments
         WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1");
    if ($payment_stmt) {
        mysqli_stmt_bind_param($payment_stmt, 'i', $booking_id);
        mysqli_stmt_execute($payment_stmt);
        $payment_row = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_stmt));
        mysqli_stmt_close($payment_stmt);
        if ($payment_row) $total_payment = max(0, (int)$payment_row['amount']);
    }
}

$is_active = in_array($bk['status'] ?? '', ['pending', 'hospital_checking', 'confirmed'], true);
$is_completed = ($bk['status'] ?? '') === 'completed';
$completed_review = null;
$completed_review_state = ['state' => 'none', 'label' => '후기작성하기'];
if ($is_completed) {
    require_once __DIR__ . '/includes/review_booking.php';
    $completed_reviews = getBookingReviewsMap($connect, [$booking_id]);
    $completed_review = $completed_reviews[$booking_id] ?? null;
    $completed_review_state = bookingReviewState($completed_review);
}
$status_labels = [
    'pending' => '예약대기', 'hospital_checking' => '병원 확인 중', 'confirmed' => '예약확정', 'completed' => '시술완료',
    'cancelled' => '예약취소', 'no_show' => '노쇼',
];
$status_label = $status_labels[$bk['status'] ?? ''] ?? ($bk['status'] ?? '');
$dday = '';
if ($is_active && !empty($bk['booking_date'])) {
    $diff = (int)(new DateTime('today'))->diff(new DateTime($bk['booking_date']))->format('%r%a');
    $dday = $diff > 0 ? 'D-' . $diff : ($diff === 0 ? 'D-0' : 'D+' . abs($diff));
}

$notification_history = [];
$log_table = mysqli_query($connect,
    "SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_notification_sends' LIMIT 1");
if ($log_table && mysqli_num_rows($log_table) > 0) {
    $memberIdForLog = (string)($bk['mb_id'] ?? '');
    $linkNeedle = '%booking_id=' . $booking_id . '%';
    $detailsNeedle = '%\"booking_id\":' . $booking_id . '%';
    $targetNeedle = $memberIdForLog !== '' ? 'booking:' . $memberIdForLog : '__none__';
    $noti_stmt = mysqli_prepare(
        $connect,
        "SELECT created_at, notification_type, title, message, link,
                in_app_count, push_sent, push_failed, push_skipped, push_error, push_details
         FROM gangnamunni_notification_sends
         WHERE notification_type LIKE 'booking_%'
           AND (link LIKE ? OR push_details LIKE ? OR target_type = ?)
         ORDER BY created_at DESC
         LIMIT 8"
    );
    if ($noti_stmt) {
        mysqli_stmt_bind_param($noti_stmt, 'sss', $linkNeedle, $detailsNeedle, $targetNeedle);
        mysqli_stmt_execute($noti_stmt);
        $noti_result = mysqli_stmt_get_result($noti_stmt);
        while ($row = mysqli_fetch_assoc($noti_result)) {
            $notification_history[] = $row;
        }
        mysqli_stmt_close($noti_stmt);
    }
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
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?php echo $is_active ? '예약 내용 확인' : ($is_completed ? '시술완료 내역' : '예약 상세'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root { --primary:#AC1830; }
* { box-sizing:border-box; margin:0; padding:0; }
html { background:#3f3f3f; }
body { max-width:640px; min-height:100vh; margin:0 auto; padding-bottom:24px; background:#fff; color:#111; font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif; }
.request-header { height:82px; display:grid; grid-template-columns:48px 1fr 48px; align-items:center; padding:0 20px; }
.request-back { width:44px; height:44px; border:0; background:none; font-size:31px; color:#111; cursor:pointer; }
.request-title { text-align:center; font-size:20px; font-weight:500; letter-spacing:-.04em; white-space:nowrap; }
.request-body { padding:0 20px; }
.status-badge { display:inline-flex; align-items:center; gap:28px; margin:18px 0 22px; padding:12px 18px; background:var(--primary); color:#fff; font-size:22px; font-weight:700; }
.status-badge.completed { padding:10px 16px; background:#f2f2f2; color:#111; font-size:22px; }
.detail-list { border-bottom:1px solid #ddd; }
.detail-row { min-height:64px; display:grid; grid-template-columns:118px minmax(0,1fr); align-items:center; border-top:1px solid #ddd; }
.detail-row:first-child { border-top:0; }
.detail-label { color:#aaa; font-size:18px; font-weight:600; }
.detail-value { min-width:0; overflow:hidden; color:#111; font-size:18px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.person-card { margin-top:28px; padding:20px; background:#f3f3f3; display:grid; grid-template-columns:98px 1fr; row-gap:10px; }
.person-label { color:#aaa; font-size:18px; font-weight:600; }
.person-value { font-size:18px; font-weight:700; }
.payment-section { margin-top:38px; }
.payment-title { margin-bottom:12px; font-size:22px; font-weight:700; letter-spacing:-.04em; }
.payment-row { min-height:68px; display:flex; align-items:center; justify-content:space-between; gap:20px; border-bottom:1px solid #ddd; }
.payment-label { color:#aaa; font-size:18px; font-weight:600; }
.payment-value { color:#111; font-size:18px; font-weight:500; white-space:nowrap; }
.payment-row.total .payment-value { font-weight:750; }
.notice { margin-top:22px; padding:36px 28px 28px; background:#fff1f3; }
.notice-title { margin-bottom:10px; color:var(--primary); font-size:17px; font-weight:700; }
.notice ul { padding-left:18px; font-size:14px; line-height:1.8; }
.notice em { color:var(--primary); font-style:normal; }
.notification-audit { margin-top:28px; padding:20px; border:1px solid #e4e4e4; background:#fafafa; }
.notification-audit-title { margin-bottom:14px; font-size:18px; font-weight:750; letter-spacing:-.035em; }
.notification-audit-list { display:grid; gap:12px; }
.notification-audit-item { padding:14px; background:#fff; border:1px solid #eee; }
.notification-audit-meta { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px; color:#777; font-size:12px; }
.notification-audit-name { color:#111; font-size:14px; font-weight:750; }
.notification-audit-body { color:#555; font-size:13px; line-height:1.45; white-space:pre-wrap; }
.notification-audit-state { margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; }
.notification-audit-badge { display:inline-flex; align-items:center; padding:4px 7px; border-radius:999px; background:#eee; color:#555; font-size:11px; font-weight:700; }
.notification-audit-badge.ok { background:#e8f6ee; color:#16834a; }
.notification-audit-badge.fail { background:#fdecef; color:#b4233b; }
.detail-actions { max-width:430px; margin:40px auto 0; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.detail-action { height:56px; border:1px solid var(--primary); background:#fff; color:var(--primary); display:flex; align-items:center; justify-content:center; font-family:inherit; font-size:22px; font-weight:700; text-decoration:none; cursor:pointer; }
.detail-action.primary { background:var(--primary); color:#fff; }
.confirm-action { width:100%; height:52px; margin-top:22px; border:0; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; text-decoration:none; }
.completed-page .request-body { padding-bottom:30px; }
.completed-page .detail-actions { margin-top:38px; }
.completed-page .detail-action { font-size:18px; }
.completed-page .detail-label,.completed-page .detail-value,.completed-page .person-label,.completed-page .person-value { font-size:18px; }
.completed-page .detail-row { min-height:62px; grid-template-columns:116px minmax(0,1fr); }
.completed-page .person-card { grid-template-columns:96px 1fr; padding:20px; }
.change-modal { display:none; position:fixed; inset:0; z-index:12000; padding:20px; align-items:center; justify-content:center; background:rgba(0,0,0,.5); }
.change-modal.active { display:flex; }
.change-dialog { width:100%; max-width:520px; padding:42px 42px 38px; background:#fff; text-align:center; }
.change-question { font-size:22px; font-weight:500; line-height:1.65; letter-spacing:-.035em; }
.change-modal-actions { margin-top:36px; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.change-modal-btn { height:58px; border:1px solid var(--primary); background:#fff; color:var(--primary); font-size:18px; font-weight:700; cursor:pointer; }
.change-modal-btn.primary { background:var(--primary); color:#fff; }
@media (max-width:380px) {
    .request-body { padding-left:16px; padding-right:16px; }
    .detail-row { grid-template-columns:118px minmax(0,1fr); }
    .detail-label,.detail-value { font-size:18px; }
    .person-label,.person-value { font-size:18px; }
    .person-card { grid-template-columns:104px 1fr; }
    .detail-actions { gap:12px; }
    .change-dialog { padding:34px 20px 28px; }
    .change-question { font-size:22px; }
    .change-modal-actions { gap:12px; }
    .notice { padding-left:20px; padding-right:20px; }
}
</style>
</head>
<body class="<?php echo $is_active ? 'active-page' : ($is_completed ? 'completed-page' : ''); ?>">
<header class="request-header">
    <button type="button" class="request-back" onclick="location.href='my_bookings.php'" aria-label="뒤로"><i class="bi bi-chevron-left"></i></button>
    <h1 class="request-title"><?php echo $is_active ? '예약 내용을 확인해보세요' : ($is_completed ? '시술완료 내역' : '예약 상세'); ?></h1>
    <span aria-hidden="true"></span>
</header>
<main class="request-body">
    <div class="status-badge<?php echo $is_completed ? ' completed' : ''; ?>">
        <span><?php echo $is_active ? '시술예정' : htmlspecialchars($status_label); ?></span>
        <?php if ($dday !== ''): ?><span><?php echo htmlspecialchars($dday); ?></span><?php endif; ?>
    </div>
    <div class="detail-list">
        <div class="detail-row"><span class="detail-label">예약번호</span><span class="detail-value"><?php echo (int)$bk['id']; ?></span></div>
        <div class="detail-row"><span class="detail-label">날짜/시간</span><span class="detail-value"><?php echo htmlspecialchars(request_detail_date($bk['booking_date'], $bk['booking_time'])); ?></span></div>
        <div class="detail-row"><span class="detail-label">선택시술</span><span class="detail-value"><?php echo htmlspecialchars($bk['treatment'] ?: '-'); ?></span></div>
        <div class="detail-row"><span class="detail-label">병원</span><span class="detail-value"><?php echo htmlspecialchars($bk['hospital_name'] ?: '-'); ?></span></div>
        <?php if (!$is_active): ?>
        <div class="detail-row"><span class="detail-label">담당의사</span><span class="detail-value"><?php echo htmlspecialchars(trim($bk['doctor_name'] ?? '') ?: '-'); ?></span></div>
        <?php endif; ?>
    </div>
    <div class="person-card">
        <span class="person-label">예약자</span><span class="person-value"><?php echo htmlspecialchars($bk['name'] ?: '-'); ?></span>
        <span class="person-label">연락처</span><span class="person-value"><?php echo htmlspecialchars($bk['phone'] ?: '-'); ?></span>
    </div>
    <?php if (!empty($notification_history)): ?>
    <section class="notification-audit" aria-labelledby="notificationAuditTitle">
        <h2 class="notification-audit-title" id="notificationAuditTitle">알림 이력</h2>
        <div class="notification-audit-list">
            <?php foreach ($notification_history as $noti): ?>
            <?php
                $details = json_decode((string)($noti['push_details'] ?? ''), true);
                $event = is_array($details) ? trim((string)($details['event'] ?? '')) : '';
                $pushSent = (int)($noti['push_sent'] ?? 0);
                $pushFailed = (int)($noti['push_failed'] ?? 0);
                $pushSkipped = (int)($noti['push_skipped'] ?? 0);
                $sentAt = trim((string)($noti['created_at'] ?? ''));
                $sentAtLabel = $sentAt !== '' ? date('Y.m.d H:i', strtotime($sentAt)) : '-';
                $bodyText = preg_replace('/\n\[push(?:_error)?\].*$/s', '', (string)($noti['message'] ?? ''));
            ?>
            <article class="notification-audit-item">
                <div class="notification-audit-meta">
                    <span><?php echo htmlspecialchars($sentAtLabel); ?></span>
                    <?php if ($event !== ''): ?><span><?php echo htmlspecialchars($event); ?></span><?php endif; ?>
                </div>
                <div class="notification-audit-name"><?php echo htmlspecialchars($noti['title'] ?: '-'); ?></div>
                <?php if (trim($bodyText) !== ''): ?>
                <div class="notification-audit-body"><?php echo htmlspecialchars(trim($bodyText)); ?></div>
                <?php endif; ?>
                <div class="notification-audit-state">
                    <span class="notification-audit-badge ok">인앱 <?php echo (int)($noti['in_app_count'] ?? 0); ?></span>
                    <?php if ($pushSent > 0): ?><span class="notification-audit-badge ok">푸시 성공 <?php echo $pushSent; ?></span><?php endif; ?>
                    <?php if ($pushFailed > 0): ?><span class="notification-audit-badge fail">푸시 실패 <?php echo $pushFailed; ?></span><?php endif; ?>
                    <?php if ($pushSkipped > 0): ?><span class="notification-audit-badge">푸시 제외</span><?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($is_active): ?>
    <div class="notice">
        <div class="notice-title">고객님 꼭 알아두세요!</div>
        <ul>
            <li>예약 가능 여부는 병원 상황에 따라 달라질 수 있어요</li>
            <li>예약이 불가능한 경우, 상담원이 다른 일정으로 안내해드려요</li>
            <li>예약 확정 전까지는 결제가 진행되지 않아요</li>
            <li>결제는 예약이 가능하다고 확인된 후에 진행됩니다</li>
            <li>예약 현황 / 예약 변경 / 취소는 <em>내 예약 결제내역</em>에서 직접 관리 할 수 있어요</li>
        </ul>
    </div>
    <a class="confirm-action" href="my_bookings.php">확인했어요</a>
    <?php else: ?>
    <section class="payment-section" aria-labelledby="paymentInfoTitle">
        <h2 class="payment-title" id="paymentInfoTitle">결제정보</h2>
        <div class="payment-row"><span class="payment-label">시술가격</span><span class="payment-value"><?php echo number_format($treatment_price); ?></span></div>
        <div class="payment-row total"><span class="payment-label">총 결제금액</span><span class="payment-value"><?php echo number_format($total_payment); ?></span></div>
    </section>
    <?php if ($is_completed): ?>
    <div class="detail-actions">
        <a class="detail-action primary" href="booking.php?id=<?php echo (int)$bk['hospital_id']; ?>&amp;rebook=1&amp;source_booking_id=<?php echo $booking_id; ?>&amp;treatment=<?php echo urlencode((string)$bk['treatment']); ?>">다시예약</a>
        <?php if (($completed_review_state['state'] ?? 'none') === 'none'): ?>
        <a class="detail-action" href="review_write.php?booking_id=<?php echo $booking_id; ?>">후기작성하기</a>
        <?php elseif (($completed_review_state['state'] ?? '') === 'rejected'): ?>
        <a class="detail-action" href="review_write.php?id=<?php echo (int)($completed_review['id'] ?? 0); ?>">후기 다시 작성</a>
        <?php elseif (($completed_review_state['state'] ?? '') === 'published'): ?>
        <a class="detail-action" href="review_detail.php?id=<?php echo (int)($completed_review['id'] ?? 0); ?>">후기보기</a>
        <?php else: ?>
        <span class="detail-action" aria-disabled="true"><?php echo htmlspecialchars($completed_review_state['label'] ?? '후기 확인중'); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>
<div class="change-modal" id="changeModal" aria-hidden="true" onclick="if(event.target===this) closeChangeModal()">
    <section class="change-dialog" role="dialog" aria-modal="true" aria-labelledby="changeQuestion">
        <p class="change-question" id="changeQuestion">병원의 예약 현황에 따라 일정 변경이 불가할 수도 있어요<br>변경하시겠어요?</p>
        <div class="change-modal-actions">
            <button type="button" class="change-modal-btn" onclick="closeChangeModal()">취소</button>
            <button type="button" class="change-modal-btn primary" onclick="continueChange()">변경</button>
        </div>
    </section>
</div>
<div class="change-modal" id="cancelModal" aria-hidden="true" onclick="if(event.target===this) closeCancelModal()">
    <section class="change-dialog" role="dialog" aria-modal="true" aria-labelledby="cancelQuestion">
        <p class="change-question" id="cancelQuestion">예약을 취소하시겠어요?</p>
        <div class="change-modal-actions">
            <button type="button" class="change-modal-btn primary" onclick="closeCancelModal()">아니오</button>
            <button type="button" class="change-modal-btn" onclick="continueCancel()">네</button>
        </div>
    </section>
</div>
<script>
function openChangeModal() {
    var modal = document.getElementById('changeModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeChangeModal() {
    var modal = document.getElementById('changeModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function continueChange() {
    location.href = 'booking_change.php?booking_id=<?php echo (int)$bk['id']; ?>';
}
function openCancelModal() {
    var modal = document.getElementById('cancelModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeCancelModal() {
    var modal = document.getElementById('cancelModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function continueCancel() {
    location.href = 'my_bookings.php?action=cancel&booking_id=<?php echo (int)$bk['id']; ?>';
}
// booking_change.php dan brauzer Back orqali qaytilganda bfcache modalning
// oldingi ochiq holatini tiklashi mumkin. Sahifa qayta ko'ringanda tozalaymiz.
window.addEventListener('pageshow', function () {
    ['changeModal', 'cancelModal'].forEach(function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    });
    document.body.style.overflow = '';
});
</script>
</body>
</html>

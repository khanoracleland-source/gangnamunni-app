<?php
/**
 * 예약 결제 페이지 (booking_payment.php)
 *
 * 흐름: 환자 예약 신청 → 병원 확정 → 이 페이지에서 결제
 *
 * - 금액은 URL/폼이 아니라 gangnamunni_platform_payments 원장의 pending 주문에서만 읽는다.
 * - 주문은 병원이 예약을 확정할 때 생성된다(admin_bookings.php). 누락된 경우에만
 *   payment_core 로 재생성하며, 금액은 서버가 DB 단가로 계산한 bookings.price 를 쓴다.
 * - 실제 paid 전이는 PG 서버 검증을 거친 payment_core 만 수행한다.
 *   제공자 어댑터가 붙기 전까지 결제 요청은 fail-closed 로 막는다.
 */
require_once __DIR__ . '/includes/init.php';
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// ── 본인 확인 (my_bookings.php 와 동일 규칙) ───────────────────────────
//   회원: mb_id 일치, 또는 mb_id 가 비어있는 과거 예약 + 본인 전화번호 일치
//   비회원: 별도 인증 흐름에서 세션에 저장된 검증 완료 전화번호만 사용
global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_mb_id = $is_user_logged_in ? (string)$member['mb_id'] : '';
$session_phone = (string)($_SESSION['user_phone'] ?? '');
$owner_phone = $is_user_logged_in ? (string)($member['mb_hp'] ?? $session_phone) : $session_phone;
$owner_phone_digits = preg_replace('/[^0-9]/', '', $owner_phone);

if (!$is_user_logged_in && $owner_phone_digits === '') {
    header('Location: auth/login.php?url=' . urlencode('/gangnamunni/my_bookings.php'));
    exit;
}

// booking.php 와 동일한 세션 CSRF 토큰. 이 페이지로 직접 들어온 경우에도
// 토큰이 있어야 결제 요청(booking_payment_request.php)이 통과한다.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$booking_id = max(0, (int)($_GET['booking_id'] ?? 0));
if ($booking_id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

$stmt = mysqli_prepare($connect,
    "SELECT b.*, h.name AS h_name, h.profile_image_url
     FROM gangnamunni_bookings b
     LEFT JOIN gangnamunni_hospitals h ON h.id = b.hospital_id
     WHERE b.id = ?
       AND (
            (? <> '' AND b.mb_id = ?)
         OR ((b.mb_id IS NULL OR b.mb_id = '') AND ? <> ''
             AND REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?)
       )
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'sssss', $booking_id, $logged_mb_id, $logged_mb_id,
    $owner_phone_digits, $owner_phone_digits);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) {
    http_response_code(404);
    $fatal = '예약을 찾을 수 없거나 접근 권한이 없습니다.';
}

// ── 원장/주문 조회 ────────────────────────────────────────────────────
$ledger_ready = false;
$order = null;
$order_error = '';

if (empty($fatal)) {
    $chk = mysqli_query($connect,
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments' LIMIT 1");
    $ledger_ready = $chk && mysqli_num_rows($chk) > 0;
}

if ($ledger_ready) {
    $ostmt = mysqli_prepare($connect,
        "SELECT id, merchant_uid, amount, refunded_amount, status, paid_at, provider
         FROM gangnamunni_platform_payments
         WHERE payment_type = 'booking' AND booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($ostmt, 'i', $booking_id);
    mysqli_stmt_execute($ostmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
    mysqli_stmt_close($ostmt);

    // 확정된 예약인데 주문이 없으면(확정 시점에 원장이 없었던 경우 등) 여기서 만든다.
    if (!$order && ($booking['status'] ?? '') === 'confirmed') {
        try {
            require_once __DIR__ . '/includes/payment_core.php';
            $order = platform_payment_create_booking($connect, $booking_id);
            $ostmt = mysqli_prepare($connect,
                "SELECT id, merchant_uid, amount, refunded_amount, status, paid_at, provider
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

$page_title = term('booking', '예약') . ' 결제';
$hide_global_nav = true;
$hide_admin_inline_edit = true;

$page_css = '
.page-header { padding: 12px 20px; top: 0; }
@media (min-width: 768px) { .page-header { top: 0; } }
.bp-wrap { max-width: 640px; margin: 0 auto; padding: 16px 20px 100px; }
.bp-card {
    border: 1px solid #E4E4E4; border-radius: 12px; padding: 16px; margin-bottom: 14px;
}
.bp-card h2 { font-size: 15px; font-weight: 600; letter-spacing: -.02em; margin: 0 0 12px; }
.bp-row { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; padding: 5px 0; }
.bp-row .k { color: #5A5A5A; }
.bp-row .v { font-weight: 600; text-align: right; }
.bp-total {
    display: flex; justify-content: space-between; align-items: baseline;
    border-top: 2px solid #E4E4E4; margin-top: 12px; padding-top: 12px;
}
.bp-total .k { font-size: 15px; font-weight: 700; }
.bp-total .v { font-size: 22px; font-weight: 800; color: #AE1932; }
.bp-total .v small { font-size: 15px; }
.bp-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.bp-method {
    border: 1px solid #E4E4E4; border-radius: 10px; padding: 14px; text-align: center;
    font-size: 14px; font-weight: 600; background: none; cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.bp-method:hover { border-color: #BDBDBD; }
.bp-method.selected { border-color: #AE1932; box-shadow: inset 0 0 0 1px #AE1932; }
.bp-pay {
    width: 100%; padding: 16px; border: none; border-radius: 10px;
    background: #AE1932; color: #fff; font-size: 16px; font-weight: 600;
    letter-spacing: -.02em; cursor: pointer; transition: background .15s ease;
}
.bp-pay:hover { background: #822327; }
.bp-pay.is-incomplete, .bp-pay.is-incomplete:hover { background: #F2F2F2; color: #BBBBBB; }
.bp-note { font-size: 12px; color: #9A9A9A; line-height: 1.6; margin-top: 10px; }
.bp-alert {
    border-radius: 10px; padding: 14px; font-size: 13px; line-height: 1.6; margin-bottom: 14px;
}
.bp-alert.warn { background: #FFF7E6; color: #8A5A00; border: 1px solid #FFE1A6; }
.bp-alert.done { background: #E9F7EF; color: #1B6B3A; border: 1px solid #BFE7CE; }
.bp-alert.err  { background: #FDECEC; color: #9B1C1C; border: 1px solid #F7C7C7; }
.bp-badge {
    display: inline-block; font-size: 12px; font-weight: 600;
    padding: 3px 10px; border-radius: 10px; background: #F2F2F2; color: #5A5A5A;
}
';

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="my_bookings.php" class="back-btn" aria-label="뒤로"><i class="bi bi-chevron-left"></i></a>
    <div class="page-title"><?php echo htmlspecialchars($page_title); ?></div>
</div>

<div class="bp-wrap">

<?php if (!empty($fatal)): ?>
    <div class="bp-alert err"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($fatal); ?></div>
    <a href="my_bookings.php" class="bp-pay" style="display:block;text-align:center;text-decoration:none;">내 예약으로</a>

<?php else: ?>

    <!-- 예약 요약 -->
    <div class="bp-card">
        <h2><i class="bi bi-calendar-check"></i> <?php echo htmlspecialchars(term('booking', '예약')); ?> 내용</h2>
        <div class="bp-row">
            <span class="k"><?php echo htmlspecialchars(term('business', '병원')); ?></span>
            <span class="v"><?php echo htmlspecialchars($booking['h_name'] ?: $booking['hospital_name']); ?></span>
        </div>
        <div class="bp-row">
            <span class="k"><?php echo htmlspecialchars(term('service', '시술')); ?></span>
            <span class="v"><?php echo htmlspecialchars($booking['treatment'] ?: '-'); ?></span>
        </div>
        <div class="bp-row">
            <span class="k">일시</span>
            <span class="v"><?php
                echo htmlspecialchars($booking['booking_date'] . ' ' . substr((string)$booking['booking_time'], 0, 5));
            ?></span>
        </div>
        <div class="bp-row">
            <span class="k">예약자</span>
            <span class="v"><?php echo htmlspecialchars($booking['name']); ?></span>
        </div>
        <div class="bp-row">
            <span class="k">상태</span>
            <span class="v"><span class="bp-badge"><?php
                $labels = ['pending'=>'승인 대기','confirmed'=>'확정','completed'=>'완료','cancelled'=>'취소','no_show'=>'노쇼'];
                echo htmlspecialchars($labels[$status] ?? $status);
            ?></span></span>
        </div>
        <div class="bp-total">
            <span class="k">결제 금액</span>
            <span class="v"><?php echo number_format($amount); ?><small>원</small></span>
        </div>
    </div>

    <?php if ($is_paid): ?>
        <div class="bp-alert done">
            <i class="bi bi-check-circle"></i> 결제가 완료되었습니다.
            <?php if (!empty($order['paid_at'])): ?>
            <br><?php echo htmlspecialchars($order['paid_at']); ?>
            <?php if (!empty($order['provider'])): ?> · <?php echo htmlspecialchars($order['provider']); ?><?php endif; ?>
            <?php endif; ?>
        </div>
        <a href="my_bookings.php" class="bp-pay" style="display:block;text-align:center;text-decoration:none;">내 예약으로</a>

    <?php elseif (!$is_confirmed): ?>
        <div class="bp-alert warn">
            <i class="bi bi-hourglass-split"></i>
            <?php echo htmlspecialchars(term('business', '병원')); ?>이 <?php echo htmlspecialchars(term('booking', '예약')); ?>을 확정한 뒤에 결제할 수 있습니다.
            확정되면 알려드릴게요.
        </div>
        <a href="my_bookings.php" class="bp-pay is-incomplete" style="display:block;text-align:center;text-decoration:none;">내 예약으로</a>

    <?php elseif (!$ledger_ready || $order_error || !$order): ?>
        <div class="bp-alert err">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo htmlspecialchars($order_error ?: '결제 준비가 완료되지 않았습니다. 잠시 후 다시 시도하거나 병원에 문의해주세요.'); ?>
        </div>
        <a href="my_bookings.php" class="bp-pay is-incomplete" style="display:block;text-align:center;text-decoration:none;">내 예약으로</a>

    <?php else: ?>
        <!-- 결제 수단 -->
        <div class="bp-card">
            <h2><i class="bi bi-credit-card"></i> 결제 수단</h2>
            <div class="bp-methods">
                <button type="button" class="bp-method" data-provider="kakaopay" onclick="selectMethod(this)">카카오페이</button>
                <button type="button" class="bp-method" data-provider="tosspay" onclick="selectMethod(this)">토스페이</button>
            </div>
            <div class="bp-note">
                주문번호 <?php echo htmlspecialchars($order['merchant_uid']); ?>
            </div>
        </div>

        <button type="button" id="payBtn" class="bp-pay is-incomplete" onclick="requestPayment()">
            <?php echo number_format($amount); ?>원 결제하기
        </button>
        <div class="bp-note">
            결제는 제공자 서버 검증을 통과한 경우에만 완료 처리됩니다.
            결제 후 예약 취소 시 환불 규정은 <?php echo htmlspecialchars(term('business', '병원')); ?> 정책을 따릅니다.
        </div>
        <div id="payResult" style="margin-top:12px;"></div>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php
$page_js = '
var selectedProvider = "";

function selectMethod(el) {
    document.querySelectorAll(".bp-method").forEach(function(b) { b.classList.remove("selected"); });
    el.classList.add("selected");
    selectedProvider = el.getAttribute("data-provider") || "";
    var btn = document.getElementById("payBtn");
    if (btn) btn.classList.toggle("is-incomplete", selectedProvider === "");
}

function requestPayment() {
    var box = document.getElementById("payResult");
    if (!selectedProvider) {
        box.innerHTML = "<div class=\'bp-alert warn\'>결제 수단을 선택해주세요.</div>";
        return;
    }
    var body = new URLSearchParams();
    body.set("booking_id", ' . (int)$booking_id . ');
    body.set("provider", selectedProvider);
    body.set("csrf_token", ' . json_encode($_SESSION['csrf_token'] ?? '') . ');

    fetch("booking_payment_request.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: body.toString(),
        credentials: "same-origin"
    }).then(function(r) {
        return r.json().catch(function() { return {success: false, message: "서버 응답을 읽을 수 없습니다."}; });
    }).then(function(d) {
        if (d && d.success && d.redirect_url) { location.href = d.redirect_url; return; }
        var msg = (d && d.message) ? d.message : "결제를 시작할 수 없습니다.";
        box.innerHTML = "<div class=\'bp-alert warn\'>" + msg.replace(/</g, "&lt;") + "</div>";
    }).catch(function() {
        box.innerHTML = "<div class=\'bp-alert err\'>네트워크 오류가 발생했습니다.</div>";
    });
}
';

include __DIR__ . '/includes/footer.php';

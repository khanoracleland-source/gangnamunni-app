<?php
require_once __DIR__ . '/includes/init.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
global $is_member, $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = (string)($_POST['csrf_token'] ?? '');
    $posted_id = (int)($_POST['booking_id'] ?? 0);
    if ($posted_csrf === '' || empty($_SESSION['my_bookings_csrf']) || !hash_equals($_SESSION['my_bookings_csrf'], $posted_csrf) || $posted_id <= 0) {
        header('Location: my_bookings.php'); exit;
    }
    $_SESSION['booking_cancel_reason'][$posted_id] = mb_substr(trim((string)($_POST['cancel_reason'] ?? '')), 0, 255);
    header('Location: booking_cancel_request.php?booking_id=' . $posted_id); exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0 || empty($_SESSION['booking_cancel_reason'][$booking_id])) {
    header('Location: my_bookings.php'); exit;
}
$stmt = mysqli_prepare($connect, 'SELECT b.* FROM gangnamunni_bookings b WHERE b.id=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$booking || !in_array($booking['status'], ['pending', 'confirmed'], true)) {
    unset($_SESSION['booking_cancel_reason'][$booking_id]);
    header('Location: my_bookings.php'); exit;
}

$logged_in = isset($is_member) && $is_member && !empty($member['mb_id']);
$require_login = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_require_login', '1') === '1' : true;
$phone_auth = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_phone_auth', '0') === '1' : false;
if ($require_login && !$phone_auth) {
    $is_owner = $logged_in && hash_equals((string)($booking['mb_id'] ?? ''), (string)$member['mb_id']);
} else {
    $my_phone = preg_replace('/[^0-9]/', '', (string)($member['mb_hp'] ?? ($_SESSION['user_phone'] ?? '')));
    $booking_phone = preg_replace('/[^0-9]/', '', (string)($booking['phone'] ?? ''));
    $is_owner = $my_phone !== '' && hash_equals($booking_phone, $my_phone);
}
if (!$is_owner) { header('Location: my_bookings.php'); exit; }

$cancel_reason = (string)$_SESSION['booking_cancel_reason'][$booking_id];
$cancel_reason_parts = explode(': ', $cancel_reason, 2);
$cancel_reason_choice = $cancel_reason_parts[0];
$cancel_reason_custom = $cancel_reason_parts[1] ?? '';
$amount = 0; // A booking price or preview is not money received.
$payment_status = '';
$table_check = mysqli_query($connect, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gangnamunni_platform_payments' LIMIT 1");
if ($table_check && mysqli_num_rows($table_check)) {
    $pay_stmt = mysqli_prepare($connect, "SELECT amount,refunded_amount,status,provider FROM gangnamunni_platform_payments WHERE payment_type='booking' AND booking_id=? LIMIT 1");
    mysqli_stmt_bind_param($pay_stmt, 'i', $booking_id);
    mysqli_stmt_execute($pay_stmt);
    $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($pay_stmt));
    mysqli_stmt_close($pay_stmt);
    if ($payment && $payment['provider'] === 'portone' && in_array($payment['status'], ['paid','partially_refunded'], true)) {
        $amount = max(0, (int)$payment['amount'] - (int)$payment['refunded_amount']);
        $payment_status = (string)$payment['status'];
    }
}
$refund_amount = $amount;
$csrf = (string)$_SESSION['my_bookings_csrf'];

function cancel_page_date($date, $time) {
    $days = ['일','월','화','수','목','금','토'];
    $ts = strtotime($date . ' ' . $time);
    $hour = (int)date('G', $ts); $hour12 = $hour % 12 ?: 12;
    return date('n월 j일', $ts) . ' (' . $days[(int)date('w', $ts)] . ') ' . ($hour < 12 ? '오전 ' : '오후 ') . $hour12 . ':' . date('i', $ts);
}
function cancel_page_now() {
    $days = ['일','월','화','수','목','금','토'];
    $hour = (int)date('G'); $hour12 = $hour % 12 ?: 12;
    return date('n월 j일') . ' (' . $days[(int)date('w')] . ') ' . ($hour < 12 ? '오전 ' : '오후 ') . $hour12 . ':' . date('i');
}
?>
<!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>예약 취소 요청</title>
<style>
:root{--color-primary:#b61935;--color-bg:#fff;--color-text:#111;--color-inactive:#aaa}*{box-sizing:border-box}html{background:#414141}body{margin:0 auto;max-width:640px;min-height:100vh;padding-bottom:92px;background:#f3f3f3;color:#111;font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}.sheet{background:#fff}.reason-section{padding:16px 28px 42px;text-align:center}.handle{width:48px;height:5px;margin:0 auto 54px;border-radius:3px;background:var(--color-primary)}h1{margin:0;font-size:24px;letter-spacing:-.045em}.reason-sub{margin:18px 0 32px;font-size:17px;font-weight:650}.reason-grid{max-width:430px;margin:auto;display:grid;grid-template-columns:1fr 1fr;gap:24px 42px;text-align:left}.reason{display:flex;align-items:center;gap:12px;font-size:17px;font-weight:650}.reason input{appearance:none;width:16px;height:16px;margin:0;border:1px solid #ccc}.reason input:checked{border-color:#d40020;background:#d40020}.reason input:checked:after{content:'✓';display:block;color:#fff;font-size:12px;line-height:14px;text-align:center}.reason-custom{width:100%;max-width:430px;height:54px;margin-top:22px;padding:0 16px;border:1px solid #ddd;font-size:15px}.content{margin-top:10px;padding:40px 28px 30px;background:#fff}.rows{border-bottom:1px solid #ddd}.row{min-height:64px;display:grid;grid-template-columns:120px minmax(0,1fr);align-items:center;border-top:1px solid #ddd}.row:first-child{border-top:0}.label{color:#aaa;font-size:17px;font-weight:650}.value{overflow:hidden;font-size:17px;font-weight:650;white-space:nowrap;text-overflow:ellipsis}.person{margin:24px 0 38px;padding:20px 24px;display:grid;grid-template-columns:100px 1fr;gap:10px;background:#f4f4f4}.section-title{margin:30px 0 14px;font-size:20px}.money-row{min-height:58px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #ddd}.money-row .value{font-weight:500}.money-row.total .value{font-weight:750}.money-row.refund .value{color:var(--color-primary);font-weight:750}.policy-notes{margin:18px 0 38px;padding-left:16px;color:#aaa;font-size:11px;line-height:1.9}.warning{padding:26px;background:#fff0f2}.warning h2{margin:0 0 10px;color:var(--color-primary);font-size:17px}.warning ul{margin:0;padding-left:18px;font-size:13px;line-height:1.85}.agreement-title{width:100%;margin-top:34px;padding:0 0 12px;border:0;background:none;display:flex;align-items:center;justify-content:space-between;color:#111;font:inherit;font-size:16px;font-weight:700;text-align:left;cursor:pointer}.agreement-title .chevron{font-size:24px;line-height:1;transition:transform .2s}.agreement-box{margin-top:2px;padding:22px;border:1px solid #ddd;font-size:13px;line-height:1.8}.agree{display:flex;align-items:center;gap:9px;margin-top:20px;font-weight:650}.agree input{accent-color:#d40020}.legal{margin:20px 0;color:#aaa;font-size:10px;line-height:1.7}.final-line{padding-top:18px;border-top:1px solid #ddd;display:flex;justify-content:space-between;font-size:15px;font-weight:700}.final-line strong{color:var(--color-primary);font-size:18px}.final-btn{display:block;width:220px;height:54px;margin:18px auto 0;border:0;background:var(--color-primary);color:#fff;font-size:17px;font-weight:700}.final-btn:disabled{opacity:.55}.error{display:none;margin-top:12px;color:var(--color-primary);text-align:center;font-size:13px}.policy-modal{display:none;position:fixed;inset:0;z-index:20000;padding:8vh 20px;background:rgba(0,0,0,.62);align-items:flex-start;justify-content:center;overflow:auto}.policy-modal.active{display:flex}.policy-dialog{width:100%;max-width:600px;padding:40px 38px;background:#fff}.policy-dialog-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}.policy-dialog-title{font-size:19px;font-weight:750}.policy-close{width:38px;height:38px;border:0;background:none;font-size:28px;cursor:pointer}.policy-body{padding:24px;background:#f5f5f5;font-size:13px;line-height:1.75}.policy-body p{margin:0 0 18px}.policy-body ul{margin:0 0 20px;padding-left:20px}.policy-body li{margin-bottom:10px}.policy-body h3{margin:22px 0 10px;font-size:14px}
@media(max-width:420px){.reason-section,.content{padding-left:20px;padding-right:20px}.handle{margin-bottom:44px}h1{font-size:21px}.reason-sub{font-size:15px}.reason-grid{gap:20px 18px}.reason{font-size:15px}.row{grid-template-columns:100px minmax(0,1fr)}.label,.value{font-size:15px}}
@media(max-width:420px){.policy-modal{padding:20px 12px 92px}.policy-dialog{padding:26px 18px}.policy-dialog-title{font-size:17px}.policy-body{padding:20px 16px;font-size:12px}}
</style>
</head>
<body>
<section class="sheet reason-section">
 <div class="handle"></div><h1>예약을 취소하시는 이유가 있으신가요?</h1><p class="reason-sub">보다 나은 서비스를 위해 의견을 들려주세요</p>
 <div class="reason-grid">
 <?php foreach (['일정 변경','개인 사정','다른 병원 예약','금액 / 시술 변경','기타'] as $reason): ?>
  <label class="reason"><input type="radio" name="reason_display" <?php echo str_starts_with($cancel_reason, $reason) ? 'checked' : ''; ?> disabled><span><?php echo htmlspecialchars($reason); ?></span></label>
 <?php endforeach; ?>
 </div>
 <input class="reason-custom" id="cancelReasonCustom" type="text" maxlength="255" value="<?php echo htmlspecialchars($cancel_reason_custom); ?>" placeholder="직접 입력할게요 (선택)">
</section>
<main class="content">
 <div class="rows">
  <div class="row"><span class="label">예약번호</span><span class="value"><?php echo $booking_id; ?></span></div>
  <div class="row"><span class="label">날짜/시간</span><span class="value"><?php echo htmlspecialchars(cancel_page_date($booking['booking_date'], $booking['booking_time'])); ?></span></div>
  <div class="row"><span class="label">선택시술</span><span class="value"><?php echo htmlspecialchars($booking['treatment'] ?: '-'); ?></span></div>
  <div class="row"><span class="label">병원</span><span class="value"><?php echo htmlspecialchars($booking['hospital_name'] ?: '-'); ?></span></div>
  <div class="row"><span class="label">담당의사</span><span class="value"><?php echo htmlspecialchars($booking['doctor_name'] ?: '-'); ?></span></div>
 </div>
 <div class="person"><span class="label">예약자</span><span class="value"><?php echo htmlspecialchars($booking['name'] ?: '-'); ?></span><span class="label">연락처</span><span class="value"><?php echo htmlspecialchars($booking['phone'] ?: '-'); ?></span></div>
 <h2 class="section-title">결제정보</h2>
 <div class="money-row"><span class="label">시술가격</span><span class="value"><?php echo number_format($amount); ?></span></div>
 <div class="money-row total"><span class="label">총 결제금액</span><span class="value"><?php echo number_format($amount); ?></span></div>
 <h2 class="section-title">취소/환불 정보</h2>
 <div class="money-row"><span class="label">취소날짜/시간</span><span class="value"><?php echo htmlspecialchars(cancel_page_now()); ?></span></div>
 <div class="money-row"><span class="label">취소 수수료</span><span class="value">0</span></div>
 <div class="money-row refund"><span>총 금액(취소/환불)</span><span class="value"><?php echo number_format($refund_amount); ?></span></div>
 <ul class="policy-notes"><li>신용카드 결제 예약 내역은 취소 즉시 카드 승인취소 처리됩니다.</li><li>카드사 승인취소 확인은 영업일 기준 3~5일이 소요될 수 있습니다.</li><li>정확한 환불기간은 해당 카드사로 문의해 주시기 바랍니다.</li></ul>
 <section class="warning"><h2>유의사항</h2><ul><li>예약이 확정된 후 취소 시, 병원 정책에 따라 위약금이 발생할 수 있어요</li><li>포인트 할인 / 이벤트가 적용된 예약은 취소 시 할인 금액이 환수될 수 있어요</li><li>당일 예약 또는 예약 시간 임박 시 취소가 제한될 수 있어요</li><li>예약 취소 후 재예약을 원하시면 다시 시술과 시간을 선택해 주세요</li></ul></section>
 <button type="button" class="agreement-title" onclick="openPolicyModal()"><span>취소 및 환불 규정을 확인해주세요</span><span class="chevron">⌄</span></button>
 <div class="agreement-box">방문 하루 전 오후 5시까지 앱에서 취소하면 100% 환불돼요<br>당일 취소는 병원에 직접 연락해 주세요<label class="agree"><input type="checkbox" id="cancelAgree">위 내용을 모두 확인하였으며, 취소에 동의해요</label></div>
 <p class="legal">서비스 이용에 관한 개인정보 수집 동의<br>서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공하며, 위 내용에 동의합니다.</p>
 <div class="final-line"><span>환불 검토 대상 금액</span><strong><?php echo number_format($refund_amount); ?>원</strong></div>
 <button type="button" class="final-btn" id="finalCancelButton" onclick="finalizeCancel()">예약 취소 요청</button><p class="error" id="cancelError"></p>
</main>
<div class="policy-modal" id="policyModal" aria-hidden="true" onclick="if(event.target===this) closePolicyModal()">
 <section class="policy-dialog" role="dialog" aria-modal="true" aria-labelledby="policyTitle">
  <div class="policy-dialog-head"><h2 class="policy-dialog-title" id="policyTitle">취소 및 환불 규정을 확인해주세요</h2><button type="button" class="policy-close" onclick="closePolicyModal()" aria-label="닫기">⌃</button></div>
  <div class="policy-body">
   <p>예약 취소와 결제 환불은 별도로 처리됩니다.</p>
   <ul><li>운영자가 실제 결제 내역과 병원의 환불 조건을 확인한 후 환불합니다.</li><li>실제 환불이 확인되면 결제 내역에 반영됩니다.</li><li>결제 없이 계속한 예약에는 환불할 결제 금액이 없습니다.</li></ul>
  </div>
 </section>
</div>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
<script>
function openPolicyModal(){var modal=document.getElementById('policyModal');modal.classList.add('active');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closePolicyModal(){var modal=document.getElementById('policyModal');modal.classList.remove('active');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
function finalizeCancel(){
 var agree=document.getElementById('cancelAgree'), error=document.getElementById('cancelError'), button=document.getElementById('finalCancelButton');
 if(!agree.checked){error.textContent='취소 및 환불 규정에 동의해주세요.';error.style.display='block';return}
 error.style.display='none';button.disabled=true;button.textContent='요청 중...';
 var customReason=document.getElementById('cancelReasonCustom').value.trim();
 var cancelReason=<?php echo json_encode($cancel_reason_choice); ?>;
 if(customReason) cancelReason+=(cancelReason ? ': ' : '')+customReason;
 var fd=new FormData();fd.append('csrf_token',<?php echo json_encode($csrf); ?>);fd.append('booking_id',<?php echo $booking_id; ?>);fd.append('phone',<?php echo json_encode((string)($booking['phone'] ?? '')); ?>);fd.append('cancel_reason',cancelReason.slice(0,255));
 fetch('api/cancel_booking.php',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(data){if(data.success){location.replace('booking_cancel_complete.php?booking_id=<?php echo $booking_id; ?>')}else{error.textContent=data.message||'취소 처리 중 오류가 발생했습니다.';error.style.display='block';button.disabled=false;button.textContent='예약 취소 요청'}}).catch(function(){error.textContent='네트워크 오류가 발생했습니다.';error.style.display='block';button.disabled=false;button.textContent='예약 취소 요청'});
}
</script>
</body></html>

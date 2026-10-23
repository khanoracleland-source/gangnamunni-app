<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_config.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
require_once __DIR__ . '/includes/booking_noshow_policy.php';
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
$amount = 0;
$payment_status = '';
$table_check = mysqli_query($connect, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gangnamunni_platform_payments' LIMIT 1");
if ($table_check && mysqli_num_rows($table_check)) {
    $pay_stmt = mysqli_prepare($connect, "SELECT amount,refunded_amount,status,provider FROM gangnamunni_platform_payments WHERE payment_type='booking' AND booking_id=? LIMIT 1");
    mysqli_stmt_bind_param($pay_stmt, 'i', $booking_id);
    mysqli_stmt_execute($pay_stmt);
    $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($pay_stmt));
    mysqli_stmt_close($pay_stmt);
    if ($payment && booking_payment_is_pg($payment['provider']) && in_array($payment['status'], ['paid','partially_refunded'], true)) {
        $amount = max(0, (int)$payment['amount'] - (int)$payment['refunded_amount']);
        $payment_status = (string)$payment['status'];
    }
}
$refund_amount = $amount;
// [05-17] 이번 취소로 노쇼·취소 누적 제한에 걸리거나 가까워지면 최종 요청 전에 모달로 알린다.
$cancel_warn = booking_noshow_cancel_preview($connect, (string)($booking['mb_id'] ?? ''));
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
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--color-primary:#AC1830;--color-bg:#fff;--color-text:#1A1718;--color-inactive:#aaa}*{box-sizing:border-box}
html{background:#414141}
body{margin:0 auto;max-width:640px;min-height:100vh;padding-bottom:92px;background:#fff;color:#1A1718;letter-spacing:-.01em;font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}
/* 05-48 (폰트 = 디자인 +3px) */
.sheet{background:#fff;border-radius:16px 16px 0 0}
.reason-section{padding:10px 20px 26px;text-align:center}
.handle{width:40px;height:4px;margin:0 auto;border-radius:2px;background:#AC1830}
h1{margin:24px 0 0;font-size:23px;font-weight:700;letter-spacing:-.04em;line-height:1.4}
.reason-sub{margin:6px 0 0;font-size:17px;font-weight:400;color:#4A4446}
.reason-grid{max-width:430px;margin:22px auto 0;padding:0 24px;display:grid;grid-template-columns:1fr 1fr;row-gap:16px;column-gap:12px;text-align:left}
.reason{display:flex;align-items:center;gap:8px;font-size:18px;font-weight:500;white-space:nowrap;letter-spacing:-.04em}
.reason input{appearance:none;-webkit-appearance:none;width:20px;height:20px;margin:0;border:0;border-radius:0;flex-shrink:0;background:url(assets/icons/icon-radio-off.png?v=1) center/contain no-repeat}
.reason input:checked{background-image:url(assets/icons/icon-radio-on.png?v=1)}
.reason-custom{display:block;width:calc(100% - 40px);max-width:430px;height:46px;margin:14px auto 0;padding:0 12px;border:1px solid #DCD6D8;border-radius:0;font-size:16px;font-family:inherit;color:#1A1718}
.reason-custom::placeholder{color:#9A9395}
.content{margin-top:8px;padding:6px 0 30px;background:#fff;border-top:8px solid #F4F2F3}
.rows{margin:0 20px}
.row{display:flex;gap:16px;padding:14px 0;border-top:0;border-bottom:1px solid #ECE9EA;align-items:center}
.row:last-child{border-bottom:0}
.label{width:76px;flex-shrink:0;color:#9A9395;font-size:17px;font-weight:400}
.value{flex-grow:1;min-width:0;overflow:hidden;font-size:18px;font-weight:400;white-space:nowrap;text-overflow:ellipsis}
.person{margin:6px 20px 0;padding:14px 16px;display:grid;grid-template-columns:60px 1fr;gap:8px 16px;background:#F1EFEF;align-items:center}
.person .value{font-weight:700}
.section-title{margin:0;padding:24px 20px 4px;font-size:20px;font-weight:700}
.money-row{display:flex;align-items:center;justify-content:space-between;padding:9px 20px;border-bottom:0}
.money-row .label{width:auto;flex-grow:1;font-size:17px}
.money-row .value{flex-grow:0;font-size:18px;font-weight:400;color:#1A1718}
.money-row.total{border-top:1px solid #ECE9EA;margin:0 20px;padding-left:0;padding-right:0}
.money-row.total .label{color:#1A1718;font-weight:700}
.money-row.total .value{font-weight:700}
.money-row.refund{border-top:1px solid #ECE9EA;margin:4px 20px 0;padding-left:0;padding-right:0;font-size:17px;font-weight:700}
.money-row.refund .value{color:#AC1830;font-weight:700}
.policy-notes{margin:6px 20px 0;padding-left:16px;color:#9A9395;font-size:14px;line-height:1.6}
.warning{margin:24px 20px 0;padding:18px;background:#FBEFF1}
.warning h2{margin:0 0 8px;color:#AC1830;font-size:18px;font-weight:700}
.warning ul{margin:0;padding-left:16px;font-size:16px;line-height:1.7;color:#1A1718}
.agreement-title{width:calc(100% - 40px);margin:22px 20px 0;padding:0 0 12px;border:0;background:none;display:flex;align-items:center;justify-content:space-between;color:#1A1718;font:inherit;font-size:19px;font-weight:700;text-align:left;cursor:pointer}
.agreement-title .chevron{font-size:24px;line-height:1;transition:transform .2s}
.agreement-box{margin:0 20px;padding:16px;border:1px solid #DCD6D8;font-size:16px;line-height:1.7;color:#4A4446}
.agree{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:16px;font-weight:500;color:#1A1718}
.agree input{appearance:none;-webkit-appearance:none;width:18px;height:18px;margin:0;border:0;flex-shrink:0;background:url(assets/icons/icon-radio-off.png?v=1) center/contain no-repeat}
.agree input:checked{background-image:url(assets/icons/icon-radio-on.png?v=1)}
.legal{margin:14px 20px 0;color:#9A9395;font-size:14px;line-height:1.6}
.final-line{margin:18px 20px 0;padding-top:16px;border-top:1px solid #ECE9EA;display:flex;align-items:baseline;justify-content:space-between;font-size:16px;font-weight:700}
.final-line strong{color:#AC1830;font-size:22px}
.final-btn{display:block;width:180px;height:52px;margin:14px auto 0;border:1px solid #AC1830;border-radius:4px;background:#AC1830;color:#fff;font-size:19px;font-weight:500;font-family:inherit;cursor:pointer}
.final-btn:disabled{opacity:.55}
.error{display:none;margin-top:12px;color:#AC1830;text-align:center;font-size:16px}
.warn-modal{display:none;position:fixed;inset:0;z-index:21000;background:rgba(0,0,0,.62);align-items:flex-end;justify-content:center}.warn-modal.active{display:flex}.warn-sheet{width:100%;max-width:640px;padding:34px 28px 28px;background:#fff;border-radius:16px 16px 0 0;text-align:center}.warn-icon{width:44px;height:44px;margin:0 auto 14px;border-radius:50%;background:#fff0f2;color:var(--color-primary);font-size:26px;font-weight:800;line-height:44px}.warn-title{margin:0 0 10px;font-size:20px;font-weight:750;letter-spacing:-.04em;line-height:1.4}.warn-desc{margin:0 0 24px;font-size:14px;line-height:1.7;color:#444}.warn-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.warn-btn{height:52px;border:1px solid var(--color-primary);background:#fff;color:var(--color-primary);font-size:16px;font-weight:700;font-family:inherit;cursor:pointer}.warn-btn.primary{background:var(--color-primary);color:#fff}.policy-modal{display:none;position:fixed;inset:0;z-index:20000;padding:8vh 20px;background:rgba(0,0,0,.62);align-items:flex-start;justify-content:center;overflow:auto}.policy-modal.active{display:flex}.policy-dialog{width:100%;max-width:600px;padding:40px 38px;background:#fff}.policy-dialog-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}.policy-dialog-title{font-size:19px;font-weight:750}.policy-close{width:38px;height:38px;border:0;background:none;font-size:28px;cursor:pointer}.policy-body{padding:24px;background:#f5f5f5;font-size:13px;line-height:1.75}.policy-body p{margin:0 0 18px}.policy-body ul{margin:0 0 20px;padding-left:20px}.policy-body li{margin-bottom:10px}.policy-body h3{margin:22px 0 10px;font-size:14px}

@media(max-width:420px){.reason-grid{padding:0 8px}.label{width:68px}.policy-modal{padding:20px 12px 92px}.policy-dialog{padding:26px 18px}.policy-dialog-title{font-size:17px}.policy-body{padding:20px 16px;font-size:12px}}
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
 <h2 class="section-title">결제 금액을 확인해보세요</h2>
 <div class="money-row"><span class="label">시술가격</span><span class="value"><?php echo number_format($amount); ?></span></div>
 <div class="money-row total"><span class="label">총 결제금액</span><span class="value"><?php echo number_format($amount); ?></span></div>
 <h2 class="section-title">취소/환불 정보를 확인해보세요</h2>
 <div class="money-row"><span class="label">취소날짜/시간</span><span class="value"><?php echo htmlspecialchars(cancel_page_now()); ?></span></div>
 <div class="money-row"><span class="label">취소 수수료</span><span class="value">0</span></div>
 <div class="money-row refund"><span>총 금액(취소/환불)</span><span class="value"><?php echo number_format($refund_amount); ?></span></div>
 <ul class="policy-notes"><li>결제된 예약은 취소 후 관리자 확인을 거쳐 환불 처리됩니다.</li><li>환불 완료 전까지 관리자 화면에는 환불 안됨 상태로 표시됩니다.</li><li>정확한 환불기간은 해당 카드사로 문의해 주시기 바랍니다.</li></ul>
 <section class="warning"><h2>고객님 꼭 알아두세요!</h2><ul><li>예약이 확정된 후 취소 시, 병원 정책에 따라 위약금이 발생할 수 있어요</li><li>포인트 할인 / 이벤트가 적용된 예약은 취소 시 할인 금액이 환수될 수 있어요</li><li>당일 예약 또는 예약 시간 임박 시 취소가 제한될 수 있어요</li><li>예약 취소 후 재예약을 원하시면 다시 시술과 시간을 선택해 주세요</li></ul></section>
 <button type="button" class="agreement-title" onclick="openPolicyModal()"><span>취소 및 환불 규정을 확인해주세요</span><span class="chevron">⌄</span></button>
 <div class="agreement-box">결제된 예약은 취소 후 관리자 확인을 거쳐 환불 처리됩니다<br>당일 취소는 병원에 직접 연락해 주세요<label class="agree"><input type="checkbox" id="cancelAgree">위 내용을 모두 확인하였으며, 취소에 동의해요</label></div>
 <p class="legal">서비스 이용에 관한 개인정보 수집 동의<br>서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공하며, 위 내용에 동의합니다.</p>
 <div class="final-line"><span>환불 검토 대상 금액</span><strong><?php echo number_format($refund_amount); ?>원</strong></div>
 <button type="button" class="final-btn" id="finalCancelButton" onclick="finalizeCancel()">예약 취소 요청</button><p class="error" id="cancelError"></p>
</main>
<?php if ($cancel_warn): ?>
<div class="warn-modal" id="cancelWarnModal" aria-hidden="true">
 <section class="warn-sheet" role="dialog" aria-modal="true" aria-labelledby="cancelWarnTitle">
  <div class="warn-icon">!</div>
  <h2 class="warn-title" id="cancelWarnTitle"></h2>
  <p class="warn-desc" id="cancelWarnDesc"></p>
  <div class="warn-actions">
   <button type="button" class="warn-btn" onclick="confirmCancelWarn()">계속 취소</button>
   <button type="button" class="warn-btn primary" onclick="closeCancelWarn()">돌아가기</button>
  </div>
 </section>
</div>
<?php endif; ?>
<div class="policy-modal" id="policyModal" aria-hidden="true" onclick="if(event.target===this) closePolicyModal()">
 <section class="policy-dialog" role="dialog" aria-modal="true" aria-labelledby="policyTitle">
  <div class="policy-dialog-head"><h2 class="policy-dialog-title" id="policyTitle">취소 및 환불 규정을 확인해주세요</h2><button type="button" class="policy-close" onclick="closePolicyModal()" aria-label="닫기">⌃</button></div>
  <div class="policy-body">
   <p>결제된 예약은 취소 완료 후 관리자 확인을 거쳐 환불 처리됩니다.</p>
   <ul><li>환불 가능 금액은 실제 결제 내역 기준으로 계산됩니다.</li><li>환불 완료 전까지 관리자 화면에는 환불 안됨 상태로 표시됩니다.</li><li>결제 없이 계속한 예약에는 환불할 결제 금액이 없습니다.</li></ul>
  </div>
 </section>
</div>
<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
<script>
function openPolicyModal(){var modal=document.getElementById('policyModal');modal.classList.add('active');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closePolicyModal(){var modal=document.getElementById('policyModal');modal.classList.remove('active');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
var CANCEL_WARN=<?php echo json_encode($cancel_warn, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, cancelWarnAck=false;
function openCancelWarn(){
 var w=CANCEL_WARN, m=document.getElementById('cancelWarnModal'); if(!w||!m) return false;
 var months=parseInt(w.months,10)||3, after=parseInt(w.after,10)||0, limit=parseInt(w.limit,10)||3;
 var title, desc;
 if(w.will_block){
  title='이번 취소로 예약이 제한돼요';
  desc='최근 '+months+'개월 동안 방문하지 않았거나 취소한 예약이 '+after+'회가 돼요. '+(w.available_from||'')+'부터 다시 예약할 수 있어요.';
 } else {
  title='취소 횟수를 확인해 주세요';
  desc='최근 '+months+'개월 동안 방문하지 않았거나 취소한 예약이 '+after+'회가 돼요. '+limit+'회가 되면 일정 기간 새 예약을 할 수 없어요. 일정만 바꾸실 거라면 취소 대신 예약 변경을 이용해 주세요.';
 }
 document.getElementById('cancelWarnTitle').textContent=title;
 document.getElementById('cancelWarnDesc').textContent=desc;
 m.classList.add('active');m.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';
 return true;
}
function closeCancelWarn(){var m=document.getElementById('cancelWarnModal');if(!m) return;m.classList.remove('active');m.setAttribute('aria-hidden','true');document.body.style.overflow=''}
function confirmCancelWarn(){cancelWarnAck=true;closeCancelWarn();finalizeCancel()}
function finalizeCancel(){
 var agree=document.getElementById('cancelAgree'), error=document.getElementById('cancelError'), button=document.getElementById('finalCancelButton');
 if(!agree.checked){error.textContent='취소 및 환불 규정에 동의해주세요.';error.style.display='block';return}
 if(CANCEL_WARN && !cancelWarnAck){error.style.display='none';openCancelWarn();return}
 error.style.display='none';button.disabled=true;button.textContent='요청 중...';
 var customReason=document.getElementById('cancelReasonCustom').value.trim();
 var cancelReason=<?php echo json_encode($cancel_reason_choice); ?>;
 if(customReason) cancelReason+=(cancelReason ? ': ' : '')+customReason;
 var fd=new FormData();fd.append('csrf_token',<?php echo json_encode($csrf); ?>);fd.append('booking_id',<?php echo $booking_id; ?>);fd.append('phone',<?php echo json_encode((string)($booking['phone'] ?? '')); ?>);fd.append('cancel_reason',cancelReason.slice(0,255));
 fetch('api/cancel_booking.php',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(data){if(data.success){location.replace('booking_cancel_complete.php?booking_id=<?php echo $booking_id; ?>')}else{error.textContent=data.message||'취소 처리 중 오류가 발생했습니다.';error.style.display='block';button.disabled=false;button.textContent='예약 취소 요청'}}).catch(function(){error.textContent='네트워크 오류가 발생했습니다.';error.style.display='block';button.disabled=false;button.textContent='예약 취소 요청'});
}
</script>
</body></html>

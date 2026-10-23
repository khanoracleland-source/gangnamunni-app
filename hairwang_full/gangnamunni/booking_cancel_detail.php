<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_payment_config.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) require_once __DIR__ . '/includes/settings_helper.php';
global $is_member, $member;
$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) { header('Location: my_bookings.php?tab=cancelled'); exit; }
$stmt = mysqli_prepare($connect, "SELECT b.* FROM gangnamunni_bookings b WHERE b.id=? AND b.status IN ('cancelled','no_show') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id); mysqli_stmt_execute($stmt); $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
if (!$booking) { header('Location: my_bookings.php?tab=cancelled'); exit; }
$logged_in = isset($is_member) && $is_member && !empty($member['mb_id']);
$require_login = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_require_login', '1') === '1' : true;
$phone_auth = function_exists('getSettingValue') ? getSettingValue($connect, 'booking_phone_auth', '0') === '1' : false;
if ($require_login && !$phone_auth) $is_owner = $logged_in && hash_equals((string)$booking['mb_id'], (string)$member['mb_id']);
else { $my_phone=preg_replace('/[^0-9]/','',(string)($member['mb_hp']??($_SESSION['user_phone']??''))); $booking_phone=preg_replace('/[^0-9]/','',(string)$booking['phone']); $is_owner=$my_phone!==''&&hash_equals($booking_phone,$my_phone); }
if (!$is_owner) { header('Location: my_bookings.php?tab=cancelled'); exit; }
$refund_complete = true;
$check = mysqli_query($connect, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gangnamunni_platform_payments' LIMIT 1");
if ($check && mysqli_num_rows($check)) { $ps=mysqli_prepare($connect,"SELECT status,provider FROM gangnamunni_platform_payments WHERE payment_type='booking' AND booking_id=? LIMIT 1"); mysqli_stmt_bind_param($ps,'i',$booking_id); mysqli_stmt_execute($ps); $payment=mysqli_fetch_assoc(mysqli_stmt_get_result($ps)); mysqli_stmt_close($ps); if($payment)$refund_complete=(in_array($payment['status'],['pending','failed','refunded','cancelled'],true) || booking_payment_is_test($payment['provider'])); }
function cancel_detail_date($date,$time){$d=['일','월','화','수','목','금','토'];$ts=strtotime($date.' '.$time);$h=(int)date('G',$ts);return date('n월 j일',$ts).' ('.$d[(int)date('w',$ts)].') '.($h<12?'오전 ':'오후 ').($h%12?:12).':'.date('i',$ts);}
?>
<!doctype html><html lang="ko"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>취소·환불 내역</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>:root{--primary:#AC1830}*{box-sizing:border-box}html{background:#414141}
body{max-width:640px;min-height:100vh;margin:0 auto;background:#fff;color:#1A1718;letter-spacing:-.01em;display:flex;flex-direction:column;font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}
/* 05-50 (폰트 = 디자인 +3px) */
.header{height:52px;flex-shrink:0;display:grid;grid-template-columns:44px 1fr 44px;align-items:center;padding:0 8px}
.back{width:44px;height:44px;border:0;background:none;font-size:24px;color:#1A1718;display:flex;align-items:center;justify-content:center;cursor:pointer}
.title{text-align:center;font-size:20px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.content{flex:1;display:flex;flex-direction:column;padding:16px 0 0}
.badge{align-self:flex-start;height:37px;margin:0 20px 6px;padding:0 12px;display:flex;align-items:center;background:#F1EFEF;color:#AC1830;font-size:20px;font-weight:700}
.rows{margin:0 20px}
.row{display:flex;gap:16px;align-items:center;padding:14px 0;border-top:0;border-bottom:1px solid #ECE9EA}
.row:last-child{border-bottom:0}
.label{width:76px;flex-shrink:0;color:#9A9395;font-size:17px;font-weight:400}
.value{flex-grow:1;min-width:0;overflow:hidden;font-size:18px;font-weight:400;white-space:nowrap;text-overflow:ellipsis}
.person{margin:6px 20px 0;padding:14px 16px;display:grid;grid-template-columns:60px 1fr;gap:8px 16px;align-items:center;background:#F1EFEF}
.person .value{font-weight:700}
.value.reason{color:#AC1830;font-weight:500}
.foot{margin-top:auto;padding:16px 20px max(34px,env(safe-area-inset-bottom))}
.confirm{display:flex;align-items:center;justify-content:center;width:100%;height:52px;border:1px solid #AC1830;border-radius:4px;background:#AC1830;color:#fff;font-size:19px;font-weight:500;font-family:inherit;cursor:pointer}
@media(max-width:380px){.label{width:68px}}
</style></head><body><header class="header"><button class="back" onclick="location.href='my_bookings.php?tab=cancelled'" aria-label="뒤로"><i class="bi bi-chevron-left"></i></button><div class="title">취소·환불 내역을 확인해보세요</div><span></span></header><main class="content"><?php
// [05-51] 취소 주체: 병원 취소(cancelled_by='hospital')와 결제 기한 자동 취소('system')는 사유를 함께 보여준다.
$cancel_by = (string)($booking['cancelled_by'] ?? '');
$badge_text = $cancel_by === 'hospital' ? '병원 취소' : ($cancel_by === 'system' ? '자동 취소' : ($refund_complete ? '취소 완료' : '취소 접수'));
$show_reason = in_array($cancel_by, ['hospital', 'system'], true) && trim((string)($booking['cancel_reason'] ?? '')) !== '';
?><div class="badge"><?php echo htmlspecialchars($badge_text); ?></div><div class="rows"><div class="row"><span class="label">예약번호</span><span class="value"><?php echo $booking_id; ?></span></div><div class="row"><span class="label">날짜/시간</span><span class="value"><?php echo htmlspecialchars(cancel_detail_date($booking['booking_date'],$booking['booking_time'])); ?></span></div><div class="row"><span class="label">선택시술</span><span class="value"><?php echo htmlspecialchars($booking['treatment']?:'-'); ?></span></div><?php if ($show_reason): ?><div class="row"><span class="label">취소사유</span><span class="value reason"><?php echo htmlspecialchars((string)$booking['cancel_reason']); ?></span></div><?php endif; ?><div class="row"><span class="label">병원</span><span class="value"><?php echo htmlspecialchars($booking['hospital_name']?:'-'); ?></span></div></div><div class="person"><span class="label">예약자</span><span class="value"><?php echo htmlspecialchars($booking['name']?:'-'); ?></span><span class="label">연락처</span><span class="value"><?php echo htmlspecialchars($booking['phone']?:'-'); ?></span></div></main><div class="foot"><button class="confirm" onclick="location.href='my_bookings.php?tab=cancelled'">확인했어요</button></div></body></html>

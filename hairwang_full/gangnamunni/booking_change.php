<?php
require_once __DIR__ . '/includes/init.php';
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) require_once __DIR__ . '/includes/settings_helper.php';
global $is_member, $member;
$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) { header('Location: my_bookings.php'); exit; }
$stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_bookings WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id); mysqli_stmt_execute($stmt);
$bk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
if (!$bk || !in_array($bk['status'] ?? '', ['pending','hospital_checking','confirmed'], true)) { header('Location: my_bookings.php'); exit; }
$logged_in = isset($is_member) && $is_member && !empty($member['mb_id']);
$require_login = function_exists('getSettingValue') ? getSettingValue($connect,'booking_require_login','1') === '1' : true;
$phone_auth = function_exists('getSettingValue') ? getSettingValue($connect,'booking_phone_auth','0') === '1' : false;
if ($require_login && !$phone_auth) $owner = $logged_in && hash_equals((string)($bk['mb_id'] ?? ''), (string)$member['mb_id']);
else {
    $mine = preg_replace('/[^0-9]/','',(string)($member['mb_hp'] ?? ($_SESSION['user_phone'] ?? '')));
    $owner = $mine !== '' && hash_equals(preg_replace('/[^0-9]/','',(string)$bk['phone']), $mine);
}
if (!$owner) { header('Location: my_bookings.php'); exit; }
if (empty($_SESSION['my_bookings_csrf'])) $_SESSION['my_bookings_csrf'] = bin2hex(random_bytes(32));
$treatments = [];
$hid = (int)$bk['hospital_id'];
$regular_price_column = false;
$regular_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
if ($regular_col && mysqli_num_rows($regular_col) > 0) $regular_price_column = true;
$representative_price_column = false;
$representative_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'");
if ($representative_col && mysqli_num_rows($representative_col) > 0) $representative_price_column = true;
$option_order = $representative_price_column ? 'p.is_representative DESC, p.price ASC, p.sort_order ASC, p.id ASC' : 'p.price ASC, p.sort_order ASC, p.id ASC';
$regular_expr = $regular_price_column ? 'p.regular_price' : 'p.price';
$tr_sql = "SELECT tm.name, ht.price_min, ht.price_max,
    (SELECT p.price FROM gangnamunni_hospital_treatment_prices p
      WHERE p.hospital_treatment_id=ht.id AND p.price>0 ORDER BY {$option_order} LIMIT 1) AS sale_price,
    (SELECT {$regular_expr} FROM gangnamunni_hospital_treatment_prices p
      WHERE p.hospital_treatment_id=ht.id AND p.price>0 ORDER BY {$option_order} LIMIT 1) AS regular_price
    FROM gangnamunni_hospital_treatments ht
    JOIN gangnamunni_treatment_master tm ON tm.id=ht.treatment_id
    WHERE ht.hospital_id=? AND ht.status='active' AND ht.is_active=1
    ORDER BY ht.is_promoted DESC, tm.name";
$tr = mysqli_prepare($connect, $tr_sql);
if ($tr) {
    mysqli_stmt_bind_param($tr,'i',$hid); mysqli_stmt_execute($tr); $rr=mysqli_stmt_get_result($tr);
    while($row=mysqli_fetch_assoc($rr)) {
        $sale = max(0, (int)($row['sale_price'] ?? 0));
        $regular = max(0, (int)($row['regular_price'] ?? 0));
        $min = max(0, (int)($row['price_min'] ?? 0));
        $max = max(0, (int)($row['price_max'] ?? 0));
        if ($sale <= 0) $sale = $min > 0 ? $min : $max;
        $has_discount = $sale > 0 && $regular > $sale;
        $treatments[] = [
            'name' => (string)$row['name'], 'sale_price' => $sale,
            'regular_price' => $has_discount ? $regular : 0,
            'discount_rate' => $has_discount ? (int)round((1 - ($sale / $regular)) * 100) : 0,
            'range_max' => (!$has_discount && empty($row['sale_price']) && $max > $sale) ? $max : 0,
        ];
    }
    mysqli_stmt_close($tr);
}
$price = max(0,(int)($bk['price'] ?? 0));
?>
<!doctype html><html lang="ko"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>예약 변경</title><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--p:#AE1932;--line:#e4e4e4}*{box-sizing:border-box;margin:0;padding:0}html{background:#3f3f3f}body{max-width:640px;min-height:100vh;margin:auto;background:#f3f3f3;color:#111;font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,sans-serif}.head{height:64px;padding:0 18px;display:grid;grid-template-columns:44px 1fr 44px;align-items:center;background:#fff}.back{border:0;background:none;font-size:26px;text-align:left}.head h1{text-align:center;font-size:18px}.section{padding:32px 20px;background:#fff}.section+.section{margin-top:10px}.intro{display:flex;align-items:center;justify-content:space-between;padding-bottom:20px;border-bottom:1px solid var(--line)}.intro h2,.visit-title{font-size:22px;letter-spacing:-.04em}.option-btn{border:0;background:var(--p);color:#fff;padding:10px 20px;font-size:18px;font-weight:650}.treatment{padding:22px 0;border-bottom:1px solid var(--line)}.treatment-top{display:flex;justify-content:space-between;gap:16px}.treatment-name{font-size:18px;font-weight:700}.remove{font-size:22px}.detail-toggle{margin-top:10px;border:0;background:none;font-size:15px;font-weight:650}.qty-price{margin-top:20px;display:flex;align-items:center;justify-content:space-between}.qty{display:flex;border:1px solid var(--line)}.qty span{width:40px;height:34px;display:grid;place-items:center;font-size:18px}.price{font-size:18px;font-weight:650}.option-panel{display:none;margin-top:16px}.option-panel.active{display:block}.option-panel select{width:100%;height:48px;padding:0 12px;border:1px solid var(--line);background:#fff;font-size:16px}.months{display:flex;gap:28px;margin-top:20px;padding-bottom:16px;border-bottom:1px solid #bbb}.month-btn{border:0;background:none;color:#aaa;font-size:16px}.month-btn.active{color:#111;font-weight:700}.weekdays,.days{display:grid;grid-template-columns:repeat(7,1fr);text-align:center}.weekdays{padding:14px 0 8px;color:#aaa;font-size:13px}.day{height:38px;border:0;background:none;font-size:16px}.day.selected{background:var(--p);color:#fff}.day:disabled{color:#ccc}.today-label{height:28px;padding:4px;color:#aaa;font-size:13px;border-bottom:1px solid #bbb}.time-title{font-size:22px}.method{margin-top:20px;font-size:16px;font-weight:650}.method-note{margin-top:8px;color:var(--p);font-size:13px}.times{display:grid;grid-template-columns:repeat(4,1fr);gap:22px 38px;margin-top:26px}.time{height:38px;border:1px solid var(--line);background:#fff;font-size:16px}.time.selected{border-color:var(--p);color:var(--p)}.submit{width:100%;height:54px;margin-top:38px;border:0;background:#eee;color:#aaa;font-size:18px;font-weight:700}.submit.ready{background:var(--p);color:#fff}@media(max-width:420px){.times{gap:18px 12px}.section{padding-left:16px;padding-right:16px}}
.option-sheet-wrap{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.55);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}.option-sheet-wrap.active{opacity:1;visibility:visible}.option-sheet{width:min(640px,100%);height:min(72vh,760px);padding:14px 20px 0;background:#fff;display:flex;flex-direction:column;overflow:hidden;transform:translateY(100%);transition:transform .25s ease}.option-sheet-wrap.active .option-sheet{transform:translateY(0)}.option-sheet.dragging{transition:none}.sheet-handle{width:44px;height:4px;margin:0 auto 12px;border-radius:4px;background:#d1d1d1;flex:none;cursor:grab;touch-action:none}.sheet-handle:active{cursor:grabbing}.sheet-title{font-size:18px;font-weight:700;flex:none}.option-list{display:grid;gap:16px;margin:18px -4px 0;padding:0 4px 18px;overflow-y:auto;min-height:0;flex:1}.option-card{width:100%;padding:18px 14px;border:2px solid #eee;border-radius:4px;background:#fff;color:#111;text-align:left}.option-card.selected{border-color:var(--p)}.option-card-name{font-size:18px;font-weight:700;line-height:1.45}.option-card-detail{margin-top:14px;font-size:15px;line-height:1.55}.option-card-price{display:flex;align-items:baseline;justify-content:flex-end;gap:14px;margin-top:7px}.old-price{color:#aaa;font-size:13px;text-decoration:line-through}.discount{font-size:18px}.sale-price{color:#d00;font-size:18px;font-weight:800}.regular-price{color:#111}.no-price{color:#999;font-size:15px}.sheet-complete{width:calc(100% + 40px);height:58px;margin:0 -20px;padding-bottom:env(safe-area-inset-bottom,0);border:0;background:var(--p);color:#fff;font-size:18px;font-weight:700;flex:none;position:sticky;bottom:0;z-index:2}
</style></head><body>
<header class="head"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>예약 변경</h1><span></span></header>
<main><section class="section"><div class="intro"><h2>예약을 진행해볼까요?</h2><button class="option-btn" type="button" onclick="openOptions()">옵션 변경</button></div>
<input type="hidden" id="treatmentSelect" value="<?php echo htmlspecialchars($bk['treatment'],ENT_QUOTES); ?>">
<article class="treatment"><div class="treatment-top"><span class="treatment-name" id="treatmentName"><?php echo htmlspecialchars($bk['treatment'] ?: '-'); ?></span><span class="remove">×</span></div><button class="detail-toggle">자세히 <i class="bi bi-chevron-down"></i></button><div class="qty-price"><div class="qty"><span>−</span><span>1</span><span>+</span></div><span class="price"><?php echo number_format($price); ?></span></div></article></section>
<section class="section"><h2 class="visit-title">언제 방문하시겠어요?</h2><div class="months" id="months"></div><div class="weekdays"><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span><span>일</span></div><div class="days" id="days"></div><div class="today-label">오늘</div></section>
<section class="section"><h2 class="time-title">예약 시간을 선택해주세요</h2><div class="method">예약 확정 방식</div><div class="method-note">병원에서 확인 후 일정이 확정돼요</div><div class="times" id="times"></div><button class="submit" id="submitBtn" onclick="submitChange()">예약변경</button></section></main>
<div class="option-sheet-wrap" id="optionSheet" role="dialog" aria-modal="true" aria-labelledby="optionSheetTitle" onclick="closeOptions(event)"><section class="option-sheet"><div class="sheet-handle"></div><h2 class="sheet-title" id="optionSheetTitle">옵션 선택</h2><div class="option-list">
<?php foreach($treatments as $tn):
    $is_selected = $tn['name'] === $bk['treatment'];
    $display_price = $tn['sale_price'] > 0
        ? number_format($tn['sale_price']) . ($tn['range_max'] > 0 ? '~' . number_format($tn['range_max']) : '') . '원'
        : '가격 문의';
?>
<button type="button" class="option-card<?php echo $is_selected?' selected':''; ?>" data-value="<?php echo htmlspecialchars($tn['name'],ENT_QUOTES); ?>" data-price="<?php echo htmlspecialchars($display_price,ENT_QUOTES); ?>" onclick="chooseOption(this)"><div class="option-card-name"><?php echo htmlspecialchars($tn['name']); ?></div><div class="option-card-detail"><?php echo htmlspecialchars($tn['name']); ?> 1회</div><div class="option-card-price"><?php if($tn['regular_price']>0): ?><span class="old-price"><?php echo number_format($tn['regular_price']); ?>원</span><span class="discount"><?php echo (int)$tn['discount_rate']; ?>%</span><span class="sale-price"><?php echo number_format($tn['sale_price']); ?>원</span><?php elseif($tn['sale_price']>0): ?><span class="sale-price regular-price"><?php echo htmlspecialchars($display_price); ?></span><?php else: ?><span class="no-price">가격 문의</span><?php endif; ?></div></button>
<?php endforeach; ?>
</div><button type="button" class="sheet-complete" onclick="completeOption()">선택 완료</button></section></div>
<script>
const BOOKING_ID=<?php echo $booking_id; ?>, PHONE=<?php echo json_encode((string)$bk['phone']); ?>, CSRF=<?php echo json_encode($_SESSION['my_bookings_csrf']); ?>;
let selectedDate=<?php echo json_encode((string)$bk['booking_date']); ?>, selectedTime=<?php echo json_encode(substr((string)$bk['booking_time'],0,5)); ?>, shownMonth=new Date(selectedDate+'T00:00:00'), pendingTreatment=document.getElementById('treatmentSelect').value;
function openOptions(){document.querySelector('.option-sheet').style.transform='';document.getElementById('optionSheet').classList.add('active');document.body.style.overflow='hidden'}
function closeOptions(e){if(e&&e.target!==document.getElementById('optionSheet'))return;document.getElementById('optionSheet').classList.remove('active');document.body.style.overflow=''}
function chooseOption(card){pendingTreatment=card.dataset.value;document.querySelectorAll('.option-card').forEach(x=>x.classList.toggle('selected',x===card))}
function completeOption(){let selected=document.querySelector('.option-card.selected');document.getElementById('treatmentSelect').value=pendingTreatment;document.getElementById('treatmentName').textContent=pendingTreatment;if(selected&&selected.dataset.price)document.querySelector('.price').textContent=selected.dataset.price;closeOptions()}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeOptions()});
const optionSheet=document.querySelector('.option-sheet'),sheetHandle=document.querySelector('.sheet-handle');
let dragStartY=0,dragDistance=0,dragStartedAt=0,draggingSheet=false;
sheetHandle.addEventListener('pointerdown',e=>{draggingSheet=true;dragStartY=e.clientY;dragDistance=0;dragStartedAt=performance.now();optionSheet.classList.add('dragging');sheetHandle.setPointerCapture(e.pointerId)});
sheetHandle.addEventListener('pointermove',e=>{if(!draggingSheet)return;dragDistance=Math.max(0,e.clientY-dragStartY);optionSheet.style.transform='translateY('+dragDistance+'px)'});
function finishSheetDrag(e){if(!draggingSheet)return;draggingSheet=false;optionSheet.classList.remove('dragging');if(sheetHandle.hasPointerCapture(e.pointerId))sheetHandle.releasePointerCapture(e.pointerId);let speed=dragDistance/Math.max(1,performance.now()-dragStartedAt);if(dragDistance>80||speed>.65){optionSheet.style.transform='translateY(100%)';document.getElementById('optionSheet').classList.remove('active');document.body.style.overflow='';setTimeout(()=>{optionSheet.style.transform=''},260)}else{optionSheet.style.transform='';}}
sheetHandle.addEventListener('pointerup',finishSheetDrag);sheetHandle.addEventListener('pointercancel',finishSheetDrag);
function renderMonths(){let box=document.getElementById('months');box.innerHTML='';for(let i=0;i<2;i++){let d=new Date();d.setDate(1);d.setMonth(d.getMonth()+i);let b=document.createElement('button');b.className='month-btn'+(d.getMonth()===shownMonth.getMonth()&&d.getFullYear()===shownMonth.getFullYear()?' active':'');b.textContent=(d.getMonth()+1)+'월';b.onclick=()=>{shownMonth=d;renderMonths();renderDays()};box.appendChild(b)}}
function iso(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function renderDays(){let box=document.getElementById('days');box.innerHTML='';let y=shownMonth.getFullYear(),m=shownMonth.getMonth(),first=new Date(y,m,1),offset=(first.getDay()+6)%7,last=new Date(y,m+1,0).getDate();for(let i=0;i<offset;i++)box.appendChild(document.createElement('span'));for(let n=1;n<=last;n++){let d=new Date(y,m,n),b=document.createElement('button'),v=iso(d);b.className='day'+(v===selectedDate?' selected':'');b.textContent=n;b.disabled=d<new Date(new Date().toDateString());b.onclick=()=>{selectedDate=v;renderDays();update()};box.appendChild(b)}}
function renderTimes(){let box=document.getElementById('times');for(let h=10;h<=20;h++)for(let min of [0,30]){if(h===20&&min>30)continue;let v=String(h).padStart(2,'0')+':'+String(min).padStart(2,'0'),b=document.createElement('button');b.className='time'+(v===selectedTime?' selected':'');b.textContent=v;b.onclick=()=>{selectedTime=v;document.querySelectorAll('.time').forEach(x=>x.classList.remove('selected'));b.classList.add('selected');update()};box.appendChild(b)}}
function update(){document.getElementById('submitBtn').classList.toggle('ready',!!selectedDate&&!!selectedTime)}
function submitChange(){if(!selectedDate||!selectedTime)return;let fd=new FormData();fd.append('csrf_token',CSRF);fd.append('booking_id',BOOKING_ID);fd.append('phone',PHONE);fd.append('new_date',selectedDate);fd.append('new_time',selectedTime);fd.append('new_treatment',document.getElementById('treatmentSelect').value);fd.append('edit_reason','');fetch('api/edit_booking.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{alert(d.message);if(d.success)location.href='booking_request_detail.php?booking_id='+BOOKING_ID}).catch(()=>alert('네트워크 오류가 발생했습니다.'))}
renderMonths();renderDays();renderTimes();update();
</script></body></html>

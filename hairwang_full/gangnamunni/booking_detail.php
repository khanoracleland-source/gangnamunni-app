<?php
/**
 * ========================================
 * 서울미녀 예약 상세 (시술예정 카드 → 예약변경 진입)
 * ========================================
 * my_bookings.php 의 '예약변경' 버튼에서 booking_id 로 진입.
 * 예약 상세 표시 + 날짜변경/예약취소(api/edit_booking.php, api/cancel_booking.php 재사용).
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
require_once __DIR__ . '/includes/review_booking.php';

global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$my_mb_id = $member['mb_id'] ?? ($_SESSION['ss_mb_id'] ?? '');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if ($booking_id <= 0) { header('Location: my_bookings.php'); exit; }

// 방금 예약 신청 완료 상태(booking.php → new=1) — 신청 직후에만 보여줄 추가 요소용
$just_booked = isset($_GET['new']) && $_GET['new'] == '1';

// 예약 조회
$stmt = $connect->prepare("SELECT b.*, h.profile_image_url AS hospital_image
    FROM gangnamunni_bookings b
    LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
    WHERE b.id = ? LIMIT 1");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$bk = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$bk) { header('Location: my_bookings.php'); exit; }

// 본인 예약 확인 (로그인 필수 모드면 회원ID, 아니면 전화번호 — api/edit_booking.php 와 동일 기준)
$_req_login  = function_exists('getSettingValue') ? (getSettingValue($connect, 'booking_require_login', '1') === '1') : true;
$_phone_auth = function_exists('getSettingValue') ? (getSettingValue($connect, 'booking_phone_auth', '0') === '1') : false;
$_owner = false;
if ($_req_login && !$_phone_auth) {
    $_owner = !empty($my_mb_id) && $my_mb_id === ($bk['mb_id'] ?? '');
} else {
    $_my_phone = preg_replace('/[^0-9]/', '', $member['mb_hp'] ?? ($_SESSION['user_phone'] ?? ''));
    $_owner = $_my_phone !== '' && $_my_phone === preg_replace('/[^0-9]/', '', $bk['phone'] ?? '');
}
if (!$_owner) { header('Location: my_bookings.php'); exit; }

// 담당의사 — admin_bookings.php 에서 관리자가 직접 배정(gangnamunni_bookings.doctor_name).
// 배정된 값이 있으면 표시, 없으면 '미배정'.
$doctor_display = trim($bk['doctor_name'] ?? '');

// 예약변경 모달의 시술 선택용: 병원의 활성 시술 목록
$edit_treatments = [];
if (!empty($bk['hospital_id'])) {
    $_hid_t = (int)$bk['hospital_id'];
    if ($tstmt = mysqli_prepare($connect, "SELECT tm.name FROM gangnamunni_hospital_treatments ht JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 ORDER BY tm.name ASC")) {
        mysqli_stmt_bind_param($tstmt, 'i', $_hid_t);
        mysqli_stmt_execute($tstmt);
        $tres = mysqli_stmt_get_result($tstmt);
        while ($trow = $tres->fetch_assoc()) { if (!empty($trow['name'])) $edit_treatments[] = $trow['name']; }
        mysqli_stmt_close($tstmt);
    }
}

// 예약 변경 가능 횟수 (예약당 최대 3회) — 남은 횟수 계산
$MAX_EDITS = 3;
$edits_left = max(0, $MAX_EDITS - (int)($bk['edit_count'] ?? 0));

// 상태 라벨
$status_labels = ['pending' => '대기중', 'hospital_checking' => '병원 확인 중', 'confirmed' => '확정', 'completed' => '완료', 'cancelled' => '취소완료', 'no_show' => '노쇼'];
$status_label = $status_labels[$bk['status']] ?? $bk['status'];
$is_active = in_array($bk['status'], ['pending', 'hospital_checking', 'confirmed']); // 변경/취소 가능 상태

// 시술완료(completed) → 후기 작성/상태 (예약당 1회)
$is_completed = ($bk['status'] === 'completed');
$booking_review = $is_completed ? getBookingReview($connect, $bk['id']) : null;
$review_state = bookingReviewState($booking_review);

// 시술까지 남은 일수 (D-day) — 시술예정 상태에서 상태뱃지 대신 표시
$dday_text = '';
if ($is_active && !empty($bk['booking_date'])) {
    $_diff = (int)(new DateTime('today'))->diff(new DateTime($bk['booking_date']))->format('%r%a');
    if     ($_diff > 0)  $dday_text = 'D-' . $_diff;
    elseif ($_diff === 0) $dday_text = 'D-0';
    else                  $dday_text = 'D+' . abs($_diff);
}

// 날짜/시간 표기 (오전/오후)
function fmt_kdate($date, $time) {
    if (empty($date) || empty($time)) return '-';
    $days = ['일','월','화','수','목','금','토'];
    $ts = strtotime($date . ' ' . $time);
    $dow = $days[(int)date('w', $ts)];
    $h = (int)date('G', $ts);
    $ampm = $h < 12 ? '오전' : '오후';
    $h12 = $h % 12; if ($h12 === 0) $h12 = 12;
    return date('n', $ts) . '월 ' . date('j', $ts) . '일(' . $dow . ') ' . $ampm . ' ' . $h12 . ':' . date('i', $ts);
}

$page_title = term('booking', '예약') . ' 상세';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --color-primary:#AE1932; --color-bg:#fff; --color-bg-light:#f8f9fa; --color-text:#222; --color-text-secondary:#666; --color-border:#eee; }
        [data-bs-theme="dark"] { --color-bg:#1a1a2e; --color-bg-light:#16213e; --color-text:#e0e0e0; --color-text-secondary:#aaa; --color-border:#333; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:var(--color-bg); color:var(--color-text); padding-bottom:97px; }
        .container { max-width:640px; margin:0 auto; }
        .header { position:relative; padding:14px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--color-border); background:var(--color-bg); }
        .header .back { width:36px; height:36px; border:none; background:none; font-size:20px; cursor:pointer; color:inherit; }
        .header h1 { position:absolute; left:50%; transform:translateX(-50%); font-size:17px; font-weight:700; }

        .status-top { padding:20px 24px 0; }
        .status-badge { display:inline-block; font-size:13px; font-weight:700; padding:5px 12px; border-radius:20px; }
        .status-pending { background:#fef3c7; color:#92400e; }
        .status-confirmed { background:#dbeafe; color:#1e40af; }
        .status-completed { background:#d1fae5; color:#065f46; }
        .status-cancelled { background:#fef2f2; color:#991b1b; }
        .status-no_show { background:#f3f4f6; color:#6b7280; }
        .dday-label { display:inline-flex; align-items:baseline; gap:8px; font-size:16px; font-weight:600; color:#fff; background:var(--color-primary); padding:8px 16px; }
        .dday-label strong { font-size:22px; font-weight:800; color:#fff; }

        .section { margin:20px 24px; }
        .section-booker { margin:20px 0 0; padding:4px 24px; background:#f2f2f2; }
        [data-bs-theme="dark"] .section-booker { background:#16213e; }
        .detail-row { display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--color-border); padding:16px 0; }
        .detail-row:last-child { border-bottom:none; }
        .detail-row label { font-size:15px; font-weight:600; color:var(--color-text-secondary); width:34%; flex-shrink:0; }
        .detail-row .value { font-size:15px; font-weight:600; color:var(--color-text); }

        .booked-notice { margin:22px 24px 0; font-size:14px; line-height:1.7; color:#000000; font-weight: 700px
     }
        .actions { margin:24px; display:flex; gap:10px; }
        .btn { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; padding:15px; font-size:15px; font-weight:700; border:none; cursor:pointer; text-decoration:none; }
        .btn-primary { background:var(--color-primary); color:#fff; }
        .btn-outline { background:var(--color-bg); color:var(--color-primary); border:1px solid var(--color-primary); }
        .btn-danger { background:var(--color-bg); color:#991b1b; border:1px solid #f0c0c0; }
        .btn-ghost { background:var(--color-bg-light); color:var(--color-text); }

        /* 모달 (my_bookings.php 와 동일 패턴) */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:10000; align-items:center; justify-content:center; padding:24px; }
        .modal-overlay.active { display:flex; }
        .modal-box { width:100%; max-width:400px; background:var(--color-bg); border-radius:16px; padding:22px; }
        .modal-title { font-size:17px; font-weight:700; margin-bottom:14px; }
        .modal-input { width:100%; padding:11px 13px; border:1px solid var(--color-border); border-radius:10px; font-size:15px; margin:6px 0 12px; background:var(--color-bg); color:var(--color-text); }
        .modal-btns { display:flex; gap:10px; margin-top:6px; }
        .modal-btn { flex:1; padding:13px; border:none; font-size:15px; font-weight:700; cursor:pointer; }
        .modal-btn-cancel { background:var(--color-bg-light); color:var(--color-text); }
        .modal-btn-confirm { background:var(--color-primary); color:#fff; }
        .modal-btn-danger { background:#991b1b; color:#fff; }
    </style>
    <script>(function(){ var t=localStorage.getItem('theme'); if(t==='dark') document.documentElement.setAttribute('data-bs-theme','dark'); })();</script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="container">
    <div class="header">
        <button class="back" onclick="goBack()"><i class="bi bi-chevron-left"></i></button>
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <div class="status-top">
        <?php if ($is_active): ?>
        <span class="dday-label"><?php echo term('service', '시술'); ?>예정 <strong><?php echo htmlspecialchars($dday_text); ?></strong></span>
        <?php else: ?>
        <span class="status-badge status-<?php echo htmlspecialchars($bk['status']); ?>"><?php echo htmlspecialchars($status_label); ?></span>
        <?php endif; ?>
    </div>

    <!-- 예약 정보 -->
    <div class="section">
        <div class="detail-row">
            <label><?php echo term('booking', '예약'); ?>번호</label>
            <div class="value">#<?php echo (int)$bk['id']; ?></div>
        </div>
        <div class="detail-row">
            <label><?php echo term('booking', '예약'); ?>일시</label>
            <div class="value"><?php echo htmlspecialchars(fmt_kdate($bk['booking_date'], $bk['booking_time'])); ?></div>
        </div>
        <div class="detail-row">
            <label>선택<?php echo term('service', '시술'); ?></label>
            <div class="value"><?php echo !empty($bk['treatment']) ? htmlspecialchars($bk['treatment']) : '-'; ?></div>
        </div>
        <div class="detail-row">
            <label><?php echo term('business', '병원'); ?></label>
            <div class="value"><?php echo !empty($bk['hospital_name']) ? htmlspecialchars($bk['hospital_name']) : '-'; ?></div>
        </div>
        <div class="detail-row">
            <label>담당의사</label>
            <div class="value"><?php echo $doctor_display !== '' ? htmlspecialchars($doctor_display) : '미배정'; ?></div>
        </div>
    </div>

    <!-- 예약자 / 연락처 (하단, 회색 배경) -->
    <div class="section section-booker">
        <div class="detail-row">
            <label>예약자</label>
            <div class="value"><?php echo !empty($bk['name']) ? htmlspecialchars($bk['name']) : '-'; ?></div>
        </div>
        <div class="detail-row" style="border-bottom:none;">
            <label>연락처</label>
            <div class="value"><?php echo !empty($bk['phone']) ? htmlspecialchars($bk['phone']) : '-'; ?></div>
        </div>
    </div>

    <?php if ($just_booked): ?>
    <!-- 예약 신청 직후 안내 문구 -->
    <div class="booked-notice">
        병원 예약 시스템에 자동 접수되었습니다. 병원에서 확인 후 연락드립니다.<br>
        예약 변경 / 취소는 내 예약 / 결제내역 에서 직접 관리할 수 있습니다.
    </div>
    <?php endif; ?>

    <!-- 액션 버튼 -->
    <div class="actions">
        <?php if ($just_booked): // 예약 신청 직후: 확인 / 내 예약 보기 ?>
        <a class="btn btn-primary" href="#" onclick="goBack(); return false;">확인</a>
        <a class="btn btn-outline" href="my_bookings.php">내 <?php echo term('booking', '예약'); ?> 보기</a>
        <?php elseif ($is_active): ?>
        <button class="btn btn-primary" onclick="openEditModal()"> <?php echo term('booking', '예약'); ?>변경</button>
        <button class="btn btn-danger" onclick="openCancelModal()"> <?php echo term('booking', '예약'); ?> 취소</button>
        <?php elseif ($is_completed): // 시술완료 → 후기 작성/상태 ?>
            <?php if ($review_state['state'] === 'none'): ?>
            <a class="btn btn-primary" href="review_write.php?booking_id=<?php echo (int)$bk['id']; ?>">★ <?php echo term('review', '후기'); ?> 작성</a>
            <?php elseif ($review_state['state'] === 'published'): ?>
            <a class="btn btn-outline" href="review_detail.php?id=<?php echo (int)$booking_review['id']; ?>"><?php echo htmlspecialchars($review_state['label']); ?></a>
            <?php elseif ($review_state['state'] === 'rejected'): ?>
            <a class="btn btn-primary" href="review_write.php?id=<?php echo (int)$booking_review['id']; ?>">★ <?php echo term('review', '후기'); ?> 다시 작성</a>
            <?php else: // pending ?>
            <button class="btn btn-outline" disabled style="opacity:.6;cursor:default;">⏳ <?php echo htmlspecialchars($review_state['label']); ?></button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 예약변경 모달 (시술 / 날짜 / 시간) -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title"><?php echo term('booking', '예약'); ?> 변경</div>
        <label style="font-size:13px;font-weight:600;"><?php echo term('service', '시술'); ?></label>
        <select class="modal-input" id="editTreatment">
            <?php $_cur_t = trim($bk['treatment'] ?? ''); ?>
            <?php if ($_cur_t !== ''): ?><option value="<?php echo htmlspecialchars($_cur_t); ?>" selected><?php echo htmlspecialchars($_cur_t); ?></option><?php endif; ?>
            <?php foreach ($edit_treatments as $tn): if ($tn === $_cur_t) continue; ?>
            <option value="<?php echo htmlspecialchars($tn); ?>"><?php echo htmlspecialchars($tn); ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-size:13px;font-weight:600;">새 날짜</label>
        <input type="date" class="modal-input" id="editDate" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($bk['booking_date']); ?>">
        <label style="font-size:13px;font-weight:600;">새 시간</label>
        <input type="time" class="modal-input" id="editTime" value="<?php echo htmlspecialchars(substr($bk['booking_time'], 0, 5)); ?>">
        <label style="font-size:13px;font-weight:600;">변경 사유</label>
        <input type="text" class="modal-input" id="editReason" placeholder="변경 사유 (선택)">
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('editModal')">닫기</button>
            <button class="modal-btn modal-btn-confirm" onclick="submitEdit()">변경하기</button>
        </div>
    </div>
</div>

<!-- 취소 모달 -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <div class="modal-title"><?php echo term('booking', '예약'); ?> 취소</div>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:12px;">취소 사유를 입력해주세요.</p>
        <textarea class="modal-input" id="cancelReason" rows="3" placeholder="취소 사유 (선택)"></textarea>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('cancelModal')">닫기</button>
            <button class="modal-btn modal-btn-danger" onclick="submitCancel()">취소하기</button>
        </div>
    </div>
</div>

<!-- 예약변경 확인 모달 -->
<div class="modal-overlay" id="editConfirmModal">
    <div class="modal-box">
        <div class="modal-title"><?php echo term('booking', '예약'); ?> 변경</div>
        <p style="font-size:14px;color:var(--color-text);line-height:1.6;margin-bottom:18px;">
            <?php echo term('booking', '예약'); ?> 변경 횟수가 <strong style="color:var(--color-primary);"><?php echo (int)$edits_left; ?>번</strong> 남았습니다.<br>변경하시겠습니까?
        </p>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('editConfirmModal')">취소</button>
            <button class="modal-btn modal-btn-confirm" onclick="confirmEdit()">변경</button>
        </div>
    </div>
</div>

<script>
var BOOKING_ID = <?php echo (int)$bk['id']; ?>;
var MY_PHONE = '<?php echo addslashes($bk['phone'] ?? ''); ?>';
var EDITS_LEFT = <?php echo (int)$edits_left; ?>;

// 뒤로/확인 → 진입한 페이지로 복귀 (referrer → 브라우저 history → my_bookings 폴백)
function goBack() {
    var ref = document.referrer;
    if (ref && ref.indexOf(location.host) !== -1 && ref !== location.href) { location.href = ref; }
    else if (history.length > 1) { history.back(); }
    else { location.href = 'my_bookings.php'; }
}

function openEditModal() { document.getElementById('editModal').classList.add('active'); }
function openCancelModal() { document.getElementById('cancelModal').classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// 변경하기 → 확인 팝업 표시 (유효성 검사 후)
function submitEdit() {
    if (EDITS_LEFT <= 0) { alert('예약 변경 가능 횟수(3회)를 모두 사용했습니다.'); return; }
    var newDate = document.getElementById('editDate').value;
    var newTime = document.getElementById('editTime').value;
    if (!newDate || !newTime) { alert('날짜와 시간을 선택해주세요.'); return; }
    closeModal('editModal');
    document.getElementById('editConfirmModal').classList.add('active');
}

// 확인 팝업의 '변경' → 실제 예약변경 요청
function confirmEdit() {
    var newDate = document.getElementById('editDate').value;
    var newTime = document.getElementById('editTime').value;
    var reason = document.getElementById('editReason').value;
    var tEl = document.getElementById('editTreatment');
    var newTreatment = tEl ? tEl.value : '';
    var fd = new FormData();
    fd.append('booking_id', BOOKING_ID);
    fd.append('phone', MY_PHONE);
    fd.append('new_date', newDate);
    fd.append('new_time', newTime);
    fd.append('new_treatment', newTreatment);
    fd.append('edit_reason', reason);
    fetch('api/edit_booking.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){ alert(data.message); if (data.success) location.reload(); })
        .catch(function(){ alert('네트워크 오류가 발생했습니다.'); });
    closeModal('editConfirmModal');
}

function submitCancel() {
    var reason = document.getElementById('cancelReason').value;
    var fd = new FormData();
    fd.append('booking_id', BOOKING_ID);
    fd.append('phone', MY_PHONE);
    fd.append('cancel_reason', reason);
    fetch('api/cancel_booking.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){ alert(data.message); if (data.success) location.href = 'my_bookings.php'; })
        .catch(function(){ alert('네트워크 오류가 발생했습니다.'); });
    closeModal('cancelModal');
}
</script>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>

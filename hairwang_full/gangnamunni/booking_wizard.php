<?php
/**
 * 예약 마법사 - 5단계 (병원확인 → 진료과+의사 → 날짜+시간 → 환자정보 → 확인)
 */
require_once __DIR__ . '/includes/init.php';

$hospital_id = intval($_GET['hospital_id'] ?? $_GET['id'] ?? 0);
if (!$hospital_id) { header('Location: hospitals.php'); exit; }

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 병원 정보
$hospital = null;
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}");
if ($r) $hospital = mysqli_fetch_assoc($r);
if (!$hospital) { header('Location: hospitals.php'); exit; }

// 진료과 목록
$departments = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_departments WHERE hospital_id = {$hospital_id} ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $departments[] = $row;

// 의사 목록
$doctors = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_doctors WHERE hospital_id = {$hospital_id} ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $doctors[] = $row;

// AJAX 처리: 예약 제출
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_booking') {
    header('Content-Type: application/json');
    $data = $_POST;
    try {
        $patientName = $esc($data['patient_name'] ?? '');
        $patientPhone = $esc($data['patient_phone'] ?? '');
        $patientEmail = $esc($data['patient_email'] ?? '');
        $symptoms = $esc($data['symptoms'] ?? '');
        $deptId = intval($data['department_id'] ?? 0);
        $doctorId = intval($data['doctor_id'] ?? 0);
        $bookingDate = $esc($data['booking_date'] ?? '');
        $bookingTime = $esc($data['booking_time'] ?? '');

        if (!$patientName || !$patientPhone || !$bookingDate || !$bookingTime) {
            throw new Exception('필수 정보를 입력해주세요.');
        }

        // HMS appointments 테이블에 등록 시도
        // 실제 gangnamunni_bookings 스키마에 맞게 INSERT
        $hospitalName = $esc($hospital['name'] ?? '');
        $treatmentText = $esc($data['symptoms'] ?? '');
        $memoText = '';
        if ($deptId > 0 || $doctorId > 0) {
            $memoText = $esc("진료과ID: {$deptId}, 의사ID: {$doctorId}, 이메일: " . ($data['patient_email'] ?? ''));
        }
        $sql = "INSERT INTO gangnamunni_bookings (hospital_id, hospital_name, name, phone, treatment, booking_date, booking_time, memo, status, created_at)
                VALUES ({$hospital_id}, '{$hospitalName}', '{$patientName}', '{$patientPhone}', '{$treatmentText}', '{$bookingDate}', '{$bookingTime}', '{$memoText}', 'pending', NOW())";

        // gangnamunni_bookings 테이블은 이미 존재

        $result = mysqli_query($connect, $sql);
        if (!$result) throw new Exception(mysqli_error($connect));
        $bookingId = mysqli_insert_id($connect);

        echo json_encode(['success' => true, 'booking_id' => $bookingId, 'message' => '예약이 접수되었습니다. 병원에서 확인 후 연락드립니다.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$page_title = htmlspecialchars($hospital['name']) . ' 예약';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> | 서울미녀</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="includes/wizard_base.css" rel="stylesheet">
<style>
body { background: var(--bs-body-bg); padding-bottom: 80px; }
.hospital-banner { border-radius: 12px; overflow: hidden; margin-bottom: 0; position: relative; }
.hospital-banner img { width: 100%; height: 160px; object-fit: cover; }
.hospital-banner .overlay { position: absolute; bottom: 0; left: 0; right: 0; padding: 12px 16px; background: linear-gradient(transparent, rgba(0,0,0,.7)); color: #fff; }
.dept-card { padding: 16px; text-align: center; }
.dept-card .dept-icon { font-size: calc(32px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 6px; }
.doctor-card { display: flex; align-items: center; gap: 12px; padding: 14px; text-align: left; }
.doctor-card .doc-photo { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; background: var(--bs-secondary-bg); display: flex; align-items: center; justify-content: center; font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1)); }
.doctor-card .doc-name { font-weight: 700; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
.doctor-card .doc-spec { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color); }
.date-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
.date-cell { padding: 8px 4px; border-radius: 8px; cursor: pointer; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); transition: all .15s; }
.date-cell:hover { background: rgba(255,107,53,.1); }
.date-cell.selected { background: #ff6b35; color: #fff; }
.date-cell.today { font-weight: 700; color: #ff6b35; }
.date-cell.disabled { opacity: .3; cursor: not-allowed; pointer-events: none; }
.date-header { font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--bs-secondary-color); padding: 4px; }
.time-grid { display: flex; flex-wrap: wrap; gap: 6px; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<!-- 병원 배너 -->
<div class="hospital-banner mx-2 mt-2">
    <img src="<?= htmlspecialchars($hospital['main_image_url'] ?: '/hairwang/gangnamunni/img/default_hospital.jpg') ?>" alt="">
    <div class="overlay">
        <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($hospital['name']) ?></div>
        <div style="font-size:12px;opacity:.8"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(mb_substr($hospital['address'] ?? '', 0, 30)) ?></div>
    </div>
</div>

<!-- 마법사 스텝 -->
<div class="wz-steps mt-3">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">병원확인</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">진료과</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">날짜/시간</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="4"><div class="wz-circle">4</div><span class="wz-label">환자정보</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="5"><div class="wz-circle">5</div><span class="wz-label">확인</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 병원 확인 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-building"></i> 병원 정보 확인</h5>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold"><?= htmlspecialchars($hospital['name']) ?></h6>
                <p class="small text-muted mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($hospital['address'] ?? '주소 미등록') ?></p>
                <p class="small text-muted mb-1"><i class="bi bi-telephone"></i> <?= htmlspecialchars($hospital['phone'] ?? '-') ?></p>
                <p class="small text-muted mb-0"><i class="bi bi-envelope"></i> <?= htmlspecialchars($hospital['email'] ?? '-') ?></p>
            </div>
        </div>
        <?php if ($hospital['description']): ?>
        <div class="mt-3 small text-muted"><?= nl2br(htmlspecialchars(mb_substr($hospital['description'], 0, 200))) ?></div>
        <?php endif; ?>
        <div class="wz-actions">
            <a href="hospital_detail.php?id=<?= $hospital_id ?>" class="btn-wz-prev"><i class="bi bi-arrow-left"></i> 돌아가기</a>
            <button class="btn-wz-next" onclick="wz.next()">진료과 선택 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 진료과 & 의사 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-clipboard2-pulse"></i> 진료과 & 의사 선택</h5>

        <?php if (count($departments) > 0): ?>
        <h6 class="small fw-bold text-muted mb-2">진료과 선택</h6>
        <div class="row g-2 mb-3" id="deptGrid">
            <?php foreach ($departments as $d): ?>
            <div class="col-4 col-md-3">
                <div class="wz-card dept-card" data-value="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['name']) ?>">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="dept-icon"><i class="bi bi-heart-pulse"></i></div>
                    <div class="wz-card-name" style="font-size:13px"><?= htmlspecialchars($d['name']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-light small">등록된 진료과가 없습니다. 의사를 직접 선택하세요.</div>
        <?php endif; ?>

        <h6 class="small fw-bold text-muted mb-2">담당 의사 선택 <span class="text-secondary">(선택사항)</span></h6>
        <div class="row g-2" id="doctorGrid">
            <?php if (count($doctors) > 0): foreach ($doctors as $doc): ?>
            <div class="col-12 col-md-6">
                <div class="wz-card doctor-card" data-value="<?= $doc['id'] ?? $doc['hms_doctor_id'] ?>" data-dept="<?= $doc['department_id'] ?? '' ?>">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="doc-photo">
                        <?php if ($doc['photo_url']): ?>
                            <img src="<?= htmlspecialchars($doc['photo_url']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover">
                        <?php else: ?>
                            <i class="bi bi-person"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="doc-name"><?= htmlspecialchars($doc['name']) ?></div>
                        <div class="doc-spec"><?= htmlspecialchars($doc['specialty'] ?? '') ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="col-12"><div class="alert alert-light small">등록된 의사가 없습니다.</div></div>
            <?php endif; ?>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">날짜 선택 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 날짜 & 시간 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-calendar-check"></i> 날짜 & 시간 선택</h5>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
            <strong id="calMonth">2026년 2월</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="date-grid mb-3" id="calGrid">
            <div class="date-header">일</div><div class="date-header">월</div><div class="date-header">화</div>
            <div class="date-header">수</div><div class="date-header">목</div><div class="date-header">금</div><div class="date-header">토</div>
        </div>

        <div id="timeSection" style="display:none">
            <h6 class="small fw-bold text-muted mb-2">시간 선택</h6>
            <div class="time-grid" id="timeGrid"></div>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">환자정보 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 4: 환자 정보 -->
    <div class="wz-panel" id="wzPanel4">
        <h5 class="mb-3"><i class="bi bi-person-vcard"></i> 환자 정보</h5>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold">이름 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="bk_name" placeholder="환자 이름" value="<?= htmlspecialchars($member['mb_name'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">전화번호 <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="bk_phone" placeholder="010-0000-0000" value="<?= htmlspecialchars($member['mb_hp'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">이메일</label>
                <input type="email" class="form-control" id="bk_email" placeholder="email@example.com" value="<?= htmlspecialchars($member['mb_email'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">증상 / 요청사항</label>
                <textarea class="form-control" id="bk_symptoms" rows="3" placeholder="간단한 증상이나 요청사항을 적어주세요"></textarea>
            </div>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">예약 확인 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 5: 최종 확인 -->
    <div class="wz-panel" id="wzPanel5">
        <h5 class="mb-3"><i class="bi bi-check-circle"></i> 예약 확인</h5>
        <div id="bookingSummary"></div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnBookSubmit" onclick="submitBooking()"><i class="bi bi-check-lg"></i> 예약하기</button>
        </div>
    </div>

    <!-- 완료 화면 -->
    <div class="wz-panel" id="wzPanelDone" style="display:none;text-align:center;padding:40px 0">
        <div style="font-size:64px;color:#28a745">✅</div>
        <h4 class="mt-3">예약이 접수되었습니다!</h4>
        <p class="text-muted">병원에서 확인 후 연락드립니다.</p>
        <div class="mt-4">
            <a href="hospital_detail.php?id=<?= $hospital_id ?>" class="btn btn-outline-secondary me-2">병원으로 돌아가기</a>
            <a href="my_appointments.php" class="btn btn-primary">내 예약 보기</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/bottom_navigation.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(5, { storageKey: 'booking_<?= $hospital_id ?>' });

// 데이터
const bookingData = { hospitalId: <?= $hospital_id ?>, departmentId: 0, departmentName: '', doctorId: 0, doctorName: '', date: '', time: '' };
const departments = <?= json_encode($departments, JSON_UNESCAPED_UNICODE) ?>;
const doctors = <?= json_encode($doctors, JSON_UNESCAPED_UNICODE) ?>;

// Step 2: 진료과 선택
const deptSelect = initCardSelect(document.getElementById('deptGrid') || document.createElement('div'), {
    onChange: (vals) => {
        bookingData.departmentId = parseInt(vals[0] || 0);
        const dept = departments.find(d => d.id == bookingData.departmentId);
        bookingData.departmentName = dept?.name || '';
        // 의사 필터링
        document.querySelectorAll('#doctorGrid .wz-card').forEach(c => {
            const deptId = c.dataset.dept;
            c.closest('.col-12,.col-md-6').style.display = (!deptId || deptId == bookingData.departmentId) ? '' : 'none';
        });
    }
});

const docSelect = initCardSelect(document.getElementById('doctorGrid') || document.createElement('div'), {
    onChange: (vals) => {
        bookingData.doctorId = parseInt(vals[0] || 0);
        const doc = doctors.find(d => (d.id || d.hms_doctor_id) == bookingData.doctorId);
        bookingData.doctorName = doc?.name || '';
    }
});

// Step 3: 캘린더
let calYear, calMonth;
const today = new Date();
calYear = today.getFullYear();
calMonth = today.getMonth();

function renderCalendar() {
    const grid = document.getElementById('calGrid');
    // 기존 날짜 셀 제거 (헤더 7개 유지)
    while (grid.children.length > 7) grid.removeChild(grid.lastChild);

    document.getElementById('calMonth').textContent = `${calYear}년 ${calMonth + 1}월`;
    const firstDay = new Date(calYear, calMonth, 1).getDay();
    const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += '<div class="date-cell disabled"></div>';
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isPast = new Date(calYear, calMonth, d) < new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const isToday = dateStr === todayStr;
        const isSelected = dateStr === bookingData.date;
        const cls = ['date-cell'];
        if (isPast) cls.push('disabled');
        if (isToday) cls.push('today');
        if (isSelected) cls.push('selected');
        grid.innerHTML += `<div class="${cls.join(' ')}" data-date="${dateStr}" onclick="selectDate('${dateStr}')">${d}</div>`;
    }
}

function changeMonth(delta) {
    calMonth += delta;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
}

function selectDate(dateStr) {
    bookingData.date = dateStr;
    document.querySelectorAll('.date-cell').forEach(c => c.classList.toggle('selected', c.dataset.date === dateStr));
    // 시간 슬롯 표시
    document.getElementById('timeSection').style.display = '';
    renderTimeSlots();
}

function renderTimeSlots() {
    const grid = document.getElementById('timeGrid');
    grid.innerHTML = '';
    const slots = ['09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30'];
    slots.forEach(t => {
        const sel = t === bookingData.time ? 'selected' : '';
        grid.innerHTML += `<div class="wz-slot ${sel}" onclick="selectTime('${t}', this)">${t}</div>`;
    });
}

function selectTime(time, el) {
    bookingData.time = time;
    document.querySelectorAll('#timeGrid .wz-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
}

renderCalendar();

// 유효성 검사
wz.setValidator(2, () => true); // 진료과/의사 선택사항
wz.setValidator(3, () => {
    if (!bookingData.date) { alert('날짜를 선택해주세요.'); return false; }
    if (!bookingData.time) { alert('시간을 선택해주세요.'); return false; }
    return true;
});
wz.setValidator(4, () => {
    if (!document.getElementById('bk_name').value.trim()) { alert('이름을 입력해주세요.'); return false; }
    if (!document.getElementById('bk_phone').value.trim()) { alert('전화번호를 입력해주세요.'); return false; }
    return true;
});

// Step 5: 요약 렌더링
wz.onStepChange = (step) => {
    if (step === 5) renderSummary();
};

function renderSummary() {
    const name = document.getElementById('bk_name').value;
    const phone = document.getElementById('bk_phone').value;
    const email = document.getElementById('bk_email').value;
    const symptoms = document.getElementById('bk_symptoms').value;

    document.getElementById('bookingSummary').innerHTML = `
        <div class="wz-summary"><h6 onclick="wz.goTo(1)"><i class="bi bi-building"></i> 병원</h6>
            <div class="fw-bold"><?= htmlspecialchars($hospital['name']) ?></div>
        </div>
        <div class="wz-summary"><h6 onclick="wz.goTo(2)"><i class="bi bi-clipboard2-pulse"></i> 진료과 / 의사</h6>
            <div class="row"><div class="col-6"><strong>진료과:</strong> ${wzEsc(bookingData.departmentName || '미선택')}</div>
            <div class="col-6"><strong>의사:</strong> ${wzEsc(bookingData.doctorName || '미선택')}</div></div>
        </div>
        <div class="wz-summary"><h6 onclick="wz.goTo(3)"><i class="bi bi-calendar-check"></i> 예약 일시</h6>
            <div class="fw-bold" style="font-size:18px;color:#ff6b35">${wzEsc(bookingData.date)} ${wzEsc(bookingData.time)}</div>
        </div>
        <div class="wz-summary"><h6 onclick="wz.goTo(4)"><i class="bi bi-person-vcard"></i> 환자 정보</h6>
            <div class="row">
                <div class="col-6"><strong>이름:</strong> ${wzEsc(name)}</div>
                <div class="col-6"><strong>전화:</strong> ${wzEsc(phone)}</div>
                ${email ? `<div class="col-6"><strong>이메일:</strong> ${wzEsc(email)}</div>` : ''}
                ${symptoms ? `<div class="col-12 mt-1"><strong>증상:</strong> ${wzEsc(symptoms)}</div>` : ''}
            </div>
        </div>`;
}

// 제출
async function submitBooking() {
    const btn = document.getElementById('btnBookSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 접수 중...';

    const fd = new FormData();
    fd.append('action', 'submit_booking');
    fd.append('hospital_id', bookingData.hospitalId);
    fd.append('department_id', bookingData.departmentId);
    fd.append('doctor_id', bookingData.doctorId);
    fd.append('booking_date', bookingData.date);
    fd.append('booking_time', bookingData.time);
    fd.append('patient_name', document.getElementById('bk_name').value.trim());
    fd.append('patient_phone', document.getElementById('bk_phone').value.trim());
    fd.append('patient_email', document.getElementById('bk_email').value.trim());
    fd.append('symptoms', document.getElementById('bk_symptoms').value.trim());

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            wz.clearStorage();
            document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('wzPanelDone').style.display = 'block';
            document.querySelector('.wz-steps').style.display = 'none';
        } else {
            alert(data.error || '오류가 발생했습니다.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> 예약하기';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> 예약하기';
    }
}
</script>
</body>
</html>

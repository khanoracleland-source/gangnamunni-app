<?php
/**
 * admin_staff_wizard.php - 직원 등록 마법사
 * 4단계: 기본 정보 -> 직종/소속 -> 주소/계정 -> 검토 완료
 * 접수원/회계사/검사기사/케이스매니저 통합 등록
 *
 * @since 2026-02-23
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 이메일 중복 체크
    if ($action === 'check_email') {
        $email = $esc(trim($_POST['email'] ?? ''));
        $r = mysqli_query($connect, "SELECT id FROM users WHERE email = '{$email}' LIMIT 1");
        echo json_encode(['exists' => mysqli_num_rows($r) > 0]);
        exit;
    }

    // 프로필 사진 업로드
    if ($action === 'upload_photo') {
        $uploadDir = __DIR__ . '/uploads/staff/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => '파일 업로드 실패']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'error' => '이미지 파일만 가능합니다']);
            exit;
        }
        $fname = 'staff_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            echo json_encode(['success' => true, 'url' => 'uploads/staff/' . $fname, 'filename' => $fname]);
        } else {
            echo json_encode(['success' => false, 'error' => '저장 실패']);
        }
        exit;
    }

    // 병원(tenants) 목록
    if ($action === 'load_hospitals') {
        $hospitals = [];
        $r = mysqli_query($connect, "SELECT id, hospital_name FROM tenants ORDER BY hospital_name");
        while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;
        echo json_encode(['success' => true, 'data' => $hospitals]);
        exit;
    }

    // 최종 등록
    if ($action === 'submit') {
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => '데이터 없음']);
            exit;
        }

        // 직종별 설정
        $staffTypes = [
            'receptionist'   => ['dept' => 5, 'role' => 5, 'table' => 'receptionists',   'owner_type' => 'App\\Models\\Receptionist'],
            'accountant'     => ['dept' => 7, 'role' => 7, 'table' => 'accountants',      'owner_type' => 'App\\Models\\Accountant'],
            'lab_technician' => ['dept' => 9, 'role' => 9, 'table' => 'lab_technicians',  'owner_type' => 'App\\Models\\LabTechnician'],
            'case_handler'   => ['dept' => 8, 'role' => 8, 'table' => 'case_handlers',    'owner_type' => 'App\\Models\\CaseHandler'],
        ];

        $staffType = $data['staff_type'] ?? '';
        if (!isset($staffTypes[$staffType])) {
            echo json_encode(['success' => false, 'error' => '유효하지 않은 직종입니다']);
            exit;
        }

        $type = $staffTypes[$staffType];

        mysqli_begin_transaction($connect);
        try {
            $firstName  = $esc($data['first_name'] ?? '');
            $lastName   = $esc($data['last_name'] ?? '');
            $email      = $esc($data['email'] ?? '');
            $phone      = $esc($data['phone'] ?? '');
            $gender     = intval($data['gender'] ?? 1);
            $dob        = $esc($data['dob'] ?? '');
            $photo      = $esc($data['photo'] ?? '');
            $tenantId   = intval($data['tenant_id'] ?? 0);
            $qualification = $esc($data['qualification'] ?? '');
            $address1   = $esc($data['address1'] ?? '');
            $address2   = $esc($data['address2'] ?? '');
            $city       = $esc($data['city'] ?? '');
            $zip        = $esc($data['zip'] ?? '');
            $password   = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
            $ownerType  = $esc($type['owner_type']);
            $deptId     = $type['dept'];
            $roleId     = $type['role'];
            $tableName  = $type['table'];
            $now        = date('Y-m-d H:i:s');

            // 1) users INSERT
            $sql = "INSERT INTO users (first_name, last_name, email, phone, gender, dob, password, department_id,
                    owner_type, tenant_id, email_verified_at, created_at, updated_at, status, language)
                    VALUES ('{$firstName}', '{$lastName}', '{$email}', '{$phone}', {$gender},
                    " . ($dob ? "'{$dob}'" : "NULL") . ",
                    '{$password}', {$deptId}, '{$ownerType}', {$tenantId}, '{$now}', '{$now}', '{$now}', 1, 'ko')";
            mysqli_query($connect, $sql);
            if (mysqli_error($connect)) throw new Exception('users INSERT 실패: ' . mysqli_error($connect));
            $userId = mysqli_insert_id($connect);

            // 프로필 이미지 저장 (media 테이블)
            if ($photo) {
                $photoEsc = $esc($photo);
                mysqli_query($connect, "INSERT INTO media (model_type, model_id, collection_name, name, file_name, mime_type, disk, size, created_at, updated_at)
                    VALUES ('{$ownerType}', {$userId}, 'profile', 'profile', '{$photoEsc}', 'image/jpeg', 'public', 0, '{$now}', '{$now}')");
            }

            // 2) 직종 테이블 INSERT
            $sql2 = "INSERT INTO {$tableName} (user_id, tenant_id, qualification, created_at, updated_at)
                     VALUES ({$userId}, {$tenantId}, '{$qualification}', '{$now}', '{$now}')";
            mysqli_query($connect, $sql2);
            if (mysqli_error($connect)) throw new Exception("{$tableName} INSERT 실패: " . mysqli_error($connect));
            $ownerId = mysqli_insert_id($connect);

            // 3) users owner_id 업데이트
            mysqli_query($connect, "UPDATE users SET owner_id = {$ownerId} WHERE id = {$userId}");

            // 4) addresses INSERT
            if ($address1 || $city || $zip) {
                mysqli_query($connect, "INSERT INTO addresses (owner_id, owner_type, address1, address2, city, zip, created_at, updated_at)
                    VALUES ({$ownerId}, '{$ownerType}', '{$address1}', '{$address2}', '{$city}', '{$zip}', '{$now}', '{$now}')");
            }

            // 5) model_has_roles INSERT
            mysqli_query($connect, "INSERT INTO model_has_roles (role_id, model_type, model_id)
                VALUES ({$roleId}, 'App\\\\Models\\\\User', {$userId})");

            mysqli_commit($connect);

            $staffLabels = [
                'receptionist' => '접수원',
                'accountant' => '회계사',
                'lab_technician' => '검사기사',
                'case_handler' => '케이스매니저',
            ];
            $label = $staffLabels[$staffType] ?? $staffType;

            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'message' => "{$label} '{$firstName} {$lastName}'님이 성공적으로 등록되었습니다."
            ]);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$admin_page_title = '직원 등록 마법사';
$admin_current = 'staff_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 테마색: #795548 (갈색) ── */
.wizard-header {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 20px 0 30px;
    gap: 0;
    overflow-x: auto;
}
.wizard-step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 80px;
}
.wizard-step-row {
    display: flex;
    align-items: center;
}
.wizard-circle {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 3px solid transparent;
    position: relative;
    flex-shrink: 0;
}
.wizard-circle.pending { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border-color: var(--bs-border-color); }
.wizard-circle.active { background: #795548; color: #fff; border-color: #795548; box-shadow: 0 0 0 5px rgba(121,85,72,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #28a745; color: #fff; border-color: #28a745; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line {
    width: 60px; height: 3px;
    background: var(--bs-border-color);
    transition: background 0.3s;
    flex-shrink: 0;
}
.wizard-line.completed { background: #28a745; }
.wizard-step-label {
    font-size: 11px;
    text-align: center;
    margin-top: 8px;
    color: var(--bs-secondary-color);
    white-space: nowrap;
    transition: all 0.3s;
}
.wizard-step-label.active { color: #795548; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 직종 카드 ── */
.staff-type-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 24px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 140px;
}
.staff-type-card:hover { border-color: #795548; background: rgba(121,85,72,0.05); }
.staff-type-card.selected { border-color: #795548; background: rgba(121,85,72,0.1); box-shadow: 0 0 0 3px rgba(121,85,72,0.2); }
.staff-type-card .staff-icon { font-size: 36px; display: block; margin-bottom: 8px; color: #795548; }
.staff-type-card .staff-name { font-size: 14px; font-weight: 700; }
.staff-type-card .staff-sub { font-size: 11px; color: var(--bs-secondary-color); }

/* ── 이미지 업로드 ── */
.upload-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 140px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.upload-zone:hover { border-color: #795548; background: rgba(121,85,72,0.03); }
.upload-zone img { max-height: 120px; max-width: 100%; border-radius: 8px; }
.upload-zone .upload-icon { font-size: 36px; color: var(--bs-secondary-color); }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #795548; }
.review-section h6:hover { text-decoration: underline; }

/* ── 버튼 영역 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

.btn-staff { background: #795548; border-color: #795548; color: #fff; }
.btn-staff:hover { background: #5d4037; border-color: #5d4037; color: #fff; }
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-plus" style="color:#795548"></i> 직원 등록 마법사</h4>
        <small class="text-muted">접수원/회계사/검사기사/케이스매니저를 단계별로 등록합니다 (4단계)</small>
    </div>
</div>

<!-- 원형 스텝 인디케이터 -->
<div class="card mb-4">
    <div class="card-body p-2">
        <div class="wizard-header" id="wizardHeader"></div>
    </div>
</div>

<!-- 스텝 콘텐츠 -->
<div class="card">
    <div class="card-body">

        <!-- Step 1: 기본 정보 -->
        <div class="wizard-content" data-step="1">
            <h5 class="mb-3"><i class="bi bi-person" style="color:#795548"></i> 기본 정보</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">이름 (First Name) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_first_name" required placeholder="길동">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">성 (Last Name) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_last_name" required placeholder="홍">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">이메일 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="w_email" required placeholder="staff@example.com" oninput="checkEmail()">
                        <span class="input-group-text" id="emailStatus"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">전화번호 <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="w_phone" required placeholder="010-1234-5678">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">성별 <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" value="1" id="genderM" checked>
                            <label class="form-check-label" for="genderM">남성</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" value="2" id="genderF">
                            <label class="form-check-label" for="genderF">여성</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">생년월일</label>
                    <input type="date" class="form-control" id="w_dob">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">프로필 사진</label>
                    <div class="upload-zone" id="photoZone" onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭 또는 드래그하여 업로드 (JPG, PNG)</span>
                    </div>
                    <input type="file" id="photoInput" accept="image/*" class="d-none" onchange="uploadPhoto(this)">
                </div>
            </div>
        </div>

        <!-- Step 2: 직종/소속 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-briefcase" style="color:#795548"></i> 직종 / 소속</h5>
            <p class="text-muted small mb-3">등록할 직종을 선택하세요 <span class="text-danger">*</span></p>
            <div class="row g-3 mb-4" id="staffTypeGrid">
                <div class="col-6 col-md-3">
                    <div class="staff-type-card" data-type="receptionist" onclick="selectStaffType(this)">
                        <i class="bi bi-headset staff-icon"></i>
                        <div class="staff-name">접수원</div>
                        <div class="staff-sub">Receptionist</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="staff-type-card" data-type="accountant" onclick="selectStaffType(this)">
                        <i class="bi bi-calculator staff-icon"></i>
                        <div class="staff-name">회계사</div>
                        <div class="staff-sub">Accountant</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="staff-type-card" data-type="lab_technician" onclick="selectStaffType(this)">
                        <i class="bi bi-eyedropper staff-icon"></i>
                        <div class="staff-name">검사기사</div>
                        <div class="staff-sub">Lab Technician</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="staff-type-card" data-type="case_handler" onclick="selectStaffType(this)">
                        <i class="bi bi-clipboard2-data staff-icon"></i>
                        <div class="staff-name">케이스매니저</div>
                        <div class="staff-sub">Case Manager</div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">소속 병원 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_hospital">
                        <option value="">-- 병원 선택 --</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">자격/학력</label>
                    <input type="text" class="form-control" id="w_qualification" placeholder="예: 간호조무사, 회계학 학사">
                </div>
            </div>
        </div>

        <!-- Step 3: 주소/계정 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-geo-alt" style="color:#795548"></i> 주소 / 계정 설정</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">주소 1</label>
                    <input type="text" class="form-control" id="w_address1" placeholder="도로명 주소">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">주소 2</label>
                    <input type="text" class="form-control" id="w_address2" placeholder="상세 주소 (동/호)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">시/군/구</label>
                    <input type="text" class="form-control" id="w_city" placeholder="서울특별시 강남구">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">우편번호</label>
                    <input type="text" class="form-control" id="w_zip" placeholder="06000" maxlength="10">
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="w_password" required minlength="6" placeholder="6자 이상">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePw('w_password', this)"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 확인 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="w_password2" required placeholder="비밀번호 재입력">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePw('w_password2', this)"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: 검토 완료 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-check-circle" style="color:#795548"></i> 검토 및 등록</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 등록 버튼을 눌러주세요. 항목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary"></div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-staff" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 직원 등록
                </button>
            </div>
        </div>

    </div>
</div>

<!-- 결과 모달 -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div id="resultIcon" style="font-size:64px"></div>
                <h4 id="resultTitle" class="mt-3"></h4>
                <p id="resultMsg" class="text-muted"></p>
                <a href="admin_staff_wizard.php" class="btn btn-staff mt-3">새 직원 등록</a>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">대시보드</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '기본 정보', icon: 'bi-person' },
    { num: 2, label: '직종/소속', icon: 'bi-briefcase' },
    { num: 3, label: '주소/계정', icon: 'bi-geo-alt' },
    { num: 4, label: '검토 완료', icon: 'bi-check-circle' }
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = {
    photo: '',
    staff_type: ''
};

// ── XSS 방지 ──
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ── 스텝 인디케이터 렌더링 ──
function renderStepHeader() {
    const header = document.getElementById('wizardHeader');
    header.innerHTML = STEPS.map((s, i) => {
        let state = 'pending';
        if (s.num === currentStep) state = 'active';
        else if (completedSteps.has(s.num)) state = 'completed';
        const line = i < STEPS.length - 1
            ? `<div class="wizard-line ${completedSteps.has(s.num) ? 'completed' : ''}"></div>` : '';
        return `
            <div class="wizard-step-item">
                <div class="wizard-step-row">
                    <div class="wizard-circle ${state}" onclick="goToStep(${s.num})" title="${s.label}">
                        <span>${s.num}</span>
                    </div>
                    ${line}
                </div>
                <div class="wizard-step-label ${state}">${s.label}</div>
            </div>`;
    }).join('');
}

// ── 스텝 이동 ──
function showStep(step) {
    document.querySelectorAll('.wizard-content').forEach(el => el.classList.remove('active'));
    const target = document.querySelector(`.wizard-content[data-step="${step}"]`);
    if (target) target.classList.add('active');
    currentStep = step;
    renderStepHeader();
    updateButtons();
    if (step === 4) renderReview();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function goToStep(step) {
    if (step <= currentStep || completedSteps.has(step) || step === currentStep + 1) {
        if (currentStep !== step && currentStep < step) {
            if (!validateStep(currentStep)) return;
            completedSteps.add(currentStep);
        }
        showStep(step);
    }
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    completedSteps.add(currentStep);
    if (currentStep < STEPS.length) showStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function updateButtons() {
    document.getElementById('btnPrev').style.display = currentStep > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display = currentStep < STEPS.length ? '' : 'none';
    document.getElementById('btnSubmit').style.display = currentStep === STEPS.length ? '' : 'none';
}

// ── 유효성 검사 ──
function validateStep(step) {
    if (step === 1) {
        if (!document.getElementById('w_first_name').value.trim()) { alert('이름을 입력해주세요.'); return false; }
        if (!document.getElementById('w_last_name').value.trim()) { alert('성을 입력해주세요.'); return false; }
        if (!document.getElementById('w_email').value.trim()) { alert('이메일을 입력해주세요.'); return false; }
        if (!document.getElementById('w_phone').value.trim()) { alert('전화번호를 입력해주세요.'); return false; }
    }
    if (step === 2) {
        if (!wizardData.staff_type) { alert('직종을 선택해주세요.'); return false; }
        if (!document.getElementById('w_hospital').value) { alert('소속 병원을 선택해주세요.'); return false; }
    }
    if (step === 3) {
        const pw = document.getElementById('w_password').value;
        const pw2 = document.getElementById('w_password2').value;
        if (pw.length < 6) { alert('비밀번호는 6자 이상이어야 합니다.'); return false; }
        if (pw !== pw2) { alert('비밀번호가 일치하지 않습니다.'); return false; }
    }
    return true;
}

// ── 직종 선택 ──
function selectStaffType(el) {
    document.querySelectorAll('.staff-type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    wizardData.staff_type = el.dataset.type;
}

// ── 이메일 중복 체크 (debounce 500ms) ──
let emailTimer;
function checkEmail() {
    clearTimeout(emailTimer);
    const val = document.getElementById('w_email').value.trim();
    const st = document.getElementById('emailStatus');
    if (!val) { st.innerHTML = ''; return; }
    emailTimer = setTimeout(async () => {
        const fd = new FormData();
        fd.append('action', 'check_email');
        fd.append('email', val);
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        st.innerHTML = d.exists
            ? '<i class="bi bi-x-circle text-danger" title="이미 사용 중"></i>'
            : '<i class="bi bi-check-circle text-success" title="사용 가능"></i>';
    }, 500);
}

// ── 프로필 사진 업로드 ──
async function uploadPhoto(input) {
    if (!input.files[0]) return;
    const zone = document.getElementById('photoZone');
    zone.innerHTML = '<div class="spinner-border text-secondary"></div>';

    const fd = new FormData();
    fd.append('action', 'upload_photo');
    fd.append('file', input.files[0]);
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();

    if (d.success) {
        zone.innerHTML = `<img src="${d.url}" alt="profile"><br><small class="text-success mt-1">업로드 완료</small>`;
        wizardData.photo = d.url;
    } else {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${esc(d.error)}</small>`;
    }
}

// 드래그 앤 드롭
(function() {
    const zone = document.getElementById('photoZone');
    if (!zone) return;
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#795548'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        const input = document.getElementById('photoInput');
        input.files = e.dataTransfer.files;
        uploadPhoto(input);
    });
})();

// ── 병원 목록 로드 ──
async function loadHospitals() {
    const fd = new FormData();
    fd.append('action', 'load_hospitals');
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            const sel = document.getElementById('w_hospital');
            d.data.forEach(h => {
                const opt = document.createElement('option');
                opt.value = h.id;
                opt.textContent = h.hospital_name;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('병원 목록 로드 실패:', e); }
}

// ── 비밀번호 보기/숨기기 ──
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ── 검토 화면 ──
function renderReview() {
    const staffLabels = {
        'receptionist': '접수원 (Receptionist)',
        'accountant': '회계사 (Accountant)',
        'lab_technician': '검사기사 (Lab Technician)',
        'case_handler': '케이스매니저 (Case Manager)'
    };
    const staffIcons = {
        'receptionist': 'bi-headset',
        'accountant': 'bi-calculator',
        'lab_technician': 'bi-eyedropper',
        'case_handler': 'bi-clipboard2-data'
    };
    const genderLabel = document.querySelector('input[name="gender"]:checked')?.value === '2' ? '여성' : '남성';
    const hospitalSel = document.getElementById('w_hospital');
    const hospitalName = hospitalSel.options[hospitalSel.selectedIndex]?.text || '-';

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-person"></i> 1. 기본 정보</h6>
            <div class="row small">
                <div class="col-6"><strong>이름:</strong> ${esc(document.getElementById('w_first_name').value)}</div>
                <div class="col-6"><strong>성:</strong> ${esc(document.getElementById('w_last_name').value)}</div>
                <div class="col-6"><strong>이메일:</strong> ${esc(document.getElementById('w_email').value)}</div>
                <div class="col-6"><strong>전화:</strong> ${esc(document.getElementById('w_phone').value)}</div>
                <div class="col-6"><strong>성별:</strong> ${genderLabel}</div>
                <div class="col-6"><strong>생년월일:</strong> ${esc(document.getElementById('w_dob').value) || '-'}</div>
                ${wizardData.photo ? `<div class="col-12 mt-2"><img src="${wizardData.photo}" style="height:60px;border-radius:8px"></div>` : ''}
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-briefcase"></i> 2. 직종/소속</h6>
            <div class="row small">
                <div class="col-12 mb-2">
                    <span class="badge bg-secondary fs-6 p-2">
                        <i class="bi ${staffIcons[wizardData.staff_type] || 'bi-person'}"></i>
                        ${esc(staffLabels[wizardData.staff_type] || wizardData.staff_type)}
                    </span>
                </div>
                <div class="col-6"><strong>소속 병원:</strong> ${esc(hospitalName)}</div>
                <div class="col-6"><strong>자격/학력:</strong> ${esc(document.getElementById('w_qualification').value) || '-'}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-geo-alt"></i> 3. 주소/계정</h6>
            <div class="row small">
                <div class="col-6"><strong>주소:</strong> ${esc(document.getElementById('w_address1').value) || '-'}</div>
                <div class="col-6"><strong>상세:</strong> ${esc(document.getElementById('w_address2').value) || '-'}</div>
                <div class="col-6"><strong>시/구:</strong> ${esc(document.getElementById('w_city').value) || '-'}</div>
                <div class="col-6"><strong>우편번호:</strong> ${esc(document.getElementById('w_zip').value) || '-'}</div>
                <div class="col-6"><strong>비밀번호:</strong> ******</div>
            </div>
        </div>
    `;
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('직원 등록을 진행하시겠습니까?')) return;

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const data = {
        first_name: document.getElementById('w_first_name').value.trim(),
        last_name: document.getElementById('w_last_name').value.trim(),
        email: document.getElementById('w_email').value.trim(),
        phone: document.getElementById('w_phone').value.trim(),
        gender: document.querySelector('input[name="gender"]:checked')?.value || '1',
        dob: document.getElementById('w_dob').value,
        photo: wizardData.photo,
        staff_type: wizardData.staff_type,
        tenant_id: document.getElementById('w_hospital').value,
        qualification: document.getElementById('w_qualification').value.trim(),
        address1: document.getElementById('w_address1').value.trim(),
        address2: document.getElementById('w_address2').value.trim(),
        city: document.getElementById('w_city').value.trim(),
        zip: document.getElementById('w_zip').value.trim(),
        password: document.getElementById('w_password').value
    };

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('data', JSON.stringify(data));

    try {
        const r = await fetch('', {method:'POST', body:fd});
        const result = await r.json();
        const modal = new bootstrap.Modal(document.getElementById('resultModal'));
        if (result.success) {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            document.getElementById('resultTitle').textContent = '등록 완료!';
            document.getElementById('resultMsg').textContent = result.message;
        } else {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            document.getElementById('resultTitle').textContent = '등록 실패';
            document.getElementById('resultMsg').textContent = result.error;
        }
        modal.show();
    } catch (e) {
        alert('오류: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 직원 등록';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);
loadHospitals();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

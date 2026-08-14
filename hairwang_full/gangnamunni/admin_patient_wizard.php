<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * admin_patient_wizard.php - 환자 등록 마법사
 * 4단계 원형 스텝 프로세서로 환자 등록 처리
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
require_permission('patients');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'check_email') {
        $email = $esc(trim($_POST['email'] ?? ''));
        $r = mysqli_query($connect, "SELECT id FROM users WHERE email = '{$email}' LIMIT 1");
        echo json_encode(['exists' => mysqli_num_rows($r) > 0]);
        exit;
    }

    if ($action === 'upload_photo') {
        $uploadDir = __DIR__ . '/uploads/patients/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => '파일 업로드 실패']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'error' => '이미지 파일만 가능']);
            exit;
        }
        $fname = 'patient_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            echo json_encode(['success' => true, 'url' => 'uploads/patients/' . $fname, 'filename' => $fname]);
        } else {
            echo json_encode(['success' => false, 'error' => '저장 실패']);
        }
        exit;
    }

    if ($action === 'load_hospitals') {
        $hospitals = [];
        $r = mysqli_query($connect, "SELECT id, hospital_name FROM tenants ORDER BY hospital_name");
        while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;
        echo json_encode(['success' => true, 'hospitals' => $hospitals]);
        exit;
    }

    if ($action === 'submit') {
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => '데이터 없음']);
            exit;
        }
        mysqli_begin_transaction($connect);
        try {
            $firstName  = $esc($data['first_name'] ?? '');
            $lastName   = $esc($data['last_name'] ?? '');
            $email      = $esc($data['email'] ?? '');
            $phone      = $esc($data['phone'] ?? '');
            $gender     = intval($data['gender'] ?? 1);
            $dob        = $esc($data['dob'] ?? '');
            $bloodGroup = $esc($data['blood_group'] ?? '');
            $photo      = $esc($data['photo'] ?? '');
            $tenantId   = intval($data['tenant_id'] ?? 0);
            $insurance  = $esc($data['insurance'] ?? '');
            $allergies  = $esc($data['allergies'] ?? '');
            $conditions = $esc($data['conditions'] ?? '');
            $emergencyContact = $esc($data['emergency_contact'] ?? '');
            $emergencyName    = $esc($data['emergency_name'] ?? '');
            $address1   = $esc($data['address1'] ?? '');
            $address2   = $esc($data['address2'] ?? '');
            $city       = $esc($data['city'] ?? '');
            $zip        = $esc($data['zip'] ?? '');
            $password   = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);

            // 1) users INSERT (department_id=3, owner_type=Patient)
            $sql = "INSERT INTO users (first_name, last_name, email, phone, gender, dob, blood_group,
                    department_id, password, owner_type, tenant_id, email_verified_at, status, hospital_name, created_at, updated_at)
                    VALUES ('{$firstName}', '{$lastName}', '{$email}', '{$phone}', {$gender},
                    " . ($dob ? "'{$dob}'" : "NULL") . ", '{$bloodGroup}',
                    3, '{$password}', 'App\\\\Models\\\\Patient', " . ($tenantId ? "'{$tenantId}'" : "NULL") . ",
                    NOW(), 1, '', NOW(), NOW())";
            mysqli_query($connect, $sql);
            if (mysqli_error($connect)) throw new Exception('users INSERT 실패: ' . mysqli_error($connect));
            $userId = mysqli_insert_id($connect);

            // 프로필 사진 저장
            if ($photo) {
                mysqli_query($connect, "INSERT INTO media (model_type, model_id, collection_name, name, file_name, mime_type, disk, size, manipulations, custom_properties, responsive_images, generated_conversions, created_at, updated_at)
                    VALUES ('App\\\\Models\\\\User', {$userId}, 'profile', 'profile', '{$photo}', 'image/jpeg', 'public', 0, '[]', '[]', '[]', '[]', NOW(), NOW())");
            }

            // 2) patients INSERT (patient_unique_id 자동생성)
            $sql2 = "INSERT INTO patients (user_id, tenant_id, created_at, updated_at)
                     VALUES ({$userId}, " . ($tenantId ? "'{$tenantId}'" : "NULL") . ", NOW(), NOW())";
            mysqli_query($connect, $sql2);
            if (mysqli_error($connect)) throw new Exception('patients INSERT 실패: ' . mysqli_error($connect));
            $patientId = mysqli_insert_id($connect);

            // patient_unique_id 생성: PAT-000001 형식
            $patUniqueId = 'PAT-' . str_pad($patientId, 6, '0', STR_PAD_LEFT);
            mysqli_query($connect, "UPDATE patients SET patient_unique_id = '{$patUniqueId}' WHERE id = {$patientId}");

            // 3) UPDATE users SET owner_id
            mysqli_query($connect, "UPDATE users SET owner_id = {$patientId} WHERE id = {$userId}");

            // 4) addresses INSERT
            if ($address1 || $city || $zip) {
                mysqli_query($connect, "INSERT INTO addresses (owner_id, owner_type, address1, address2, city, zip, created_at, updated_at)
                    VALUES ({$patientId}, 'App\\\\Models\\\\Patient', '{$address1}', '{$address2}', '{$city}', '{$zip}', NOW(), NOW())");
            }

            // 5) model_has_roles INSERT (role_id=3 for patient)
            mysqli_query($connect, "INSERT INTO model_has_roles (role_id, model_type, model_id)
                VALUES (3, 'App\\\\Models\\\\User', {$userId})");

            // 6) member_sync INSERT - Gnuboard 동기화 매핑
            mysqli_query($connect, "INSERT IGNORE INTO member_sync (mb_no, mb_id, hms_user_id, hms_patient_id, sync_status, created_at, updated_at)
                VALUES (0, '', {$userId}, {$patientId}, 'pending', NOW(), NOW())");

            // 의료 정보 저장 (patients 테이블에 관련 컬럼이 있으면)
            if ($insurance || $allergies || $conditions || $emergencyContact || $emergencyName) {
                @mysqli_query($connect, "UPDATE patients SET
                    insurance = '{$insurance}',
                    allergies = '{$allergies}',
                    conditions_note = '{$conditions}',
                    emergency_contact = '{$emergencyContact}',
                    emergency_name = '{$emergencyName}'
                    WHERE id = {$patientId}");
            }

            mysqli_commit($connect);
            echo json_encode([
                'success' => true,
                'patient_id' => $patientId,
                'user_id' => $userId,
                'patient_unique_id' => $patUniqueId,
                'message' => "환자 '{$firstName} {$lastName}'이(가) 등록되었습니다. (ID: {$patUniqueId})"
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

$admin_page_title = '환자 등록 마법사';
$admin_current = 'patient_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 테마 변수 ── */
:root { --wizard-color: #dc3545; --wizard-color-rgb: 220,53,69; }

/* ── 원형 스텝 프로세서 ── */
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
    min-width: 70px;
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
.wizard-circle.active { background: var(--wizard-color); color: #fff; border-color: var(--wizard-color); box-shadow: 0 0 0 5px rgba(var(--wizard-color-rgb),0.25); transform: scale(1.1); }
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
.wizard-step-label.active { color: var(--wizard-color); font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

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
.upload-zone:hover { border-color: var(--wizard-color); background: rgba(var(--wizard-color-rgb),0.03); }
.upload-zone img { max-height: 120px; max-width: 100%; border-radius: 8px; }
.upload-zone .upload-icon { font-size: 36px; color: var(--bs-secondary-color); }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: var(--wizard-color); }
.review-section h6:hover { text-decoration: underline; }

/* ── 버튼 영역 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

/* ── 병원 검색 드롭다운 ── */
.hospital-search-wrap { position: relative; }
.hospital-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 10;
    background: var(--bs-body-bg); border: 1px solid var(--bs-border-color);
    border-radius: 8px; max-height: 200px; overflow-y: auto;
    display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.hospital-dropdown.show { display: block; }
.hospital-dropdown .hospital-item {
    padding: 8px 12px; cursor: pointer; font-size: 14px;
    border-bottom: 1px solid var(--bs-border-color);
}
.hospital-dropdown .hospital-item:hover { background: rgba(var(--wizard-color-rgb),0.08); }
.hospital-dropdown .hospital-item:last-child { border-bottom: none; }

/* ── 의료 정보 카드 ── */
.medical-info-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 16px;
    background: rgba(var(--wizard-color-rgb), 0.02);
}
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-plus" style="color:var(--wizard-color)"></i> 환자 등록 마법사</h4>
        <small class="text-muted">새 환자를 단계별로 등록합니다 (4단계)</small>
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
            <h5 class="mb-3"><i class="bi bi-person"></i> 기본 정보</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">이름 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_first_name" required placeholder="길동">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">성 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_last_name" required placeholder="홍">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">이메일 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="w_email" required placeholder="patient@example.com" oninput="checkEmail()">
                        <span class="input-group-text" id="emailStatus"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">전화번호 <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="w_phone" required placeholder="010-1234-5678">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">성별 <span class="text-danger">*</span></label>
                    <div class="mt-1">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="genderM" value="1" checked>
                            <label class="form-check-label" for="genderM">남성</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="genderF" value="2">
                            <label class="form-check-label" for="genderF">여성</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">생년월일 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="w_dob" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">혈액형</label>
                    <select class="form-select" id="w_blood_group">
                        <option value="">선택</option>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">프로필 사진</label>
                    <div class="upload-zone" id="photoZone" onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭하여 업로드</span>
                    </div>
                    <input type="file" id="photoInput" accept="image/*" class="d-none" onchange="uploadPhoto(this)">
                </div>
            </div>
        </div>

        <!-- Step 2: 의료 정보 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-heart-pulse"></i> 의료 정보</h5>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">소속 병원 <span class="text-danger">*</span></label>
                    <div class="hospital-search-wrap">
                        <input type="text" class="form-control" id="w_hospital_search" placeholder="병원명을 입력하여 검색..." autocomplete="off" oninput="filterHospitals()" onfocus="showHospitalDropdown()">
                        <input type="hidden" id="w_tenant_id">
                        <div class="hospital-dropdown" id="hospitalDropdown"></div>
                    </div>
                    <small class="text-muted" id="selectedHospitalInfo"></small>
                </div>
                <div class="col-12">
                    <div class="medical-info-card">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clipboard2-pulse"></i> 의료 기록</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">보험 정보</label>
                                <input type="text" class="form-control" id="w_insurance" placeholder="예: 국민건강보험, 실손보험 가입">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">알레르기</label>
                                <textarea class="form-control" id="w_allergies" rows="2" placeholder="약물 알레르기, 식품 알레르기 등을 입력하세요..."></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">기저질환</label>
                                <textarea class="form-control" id="w_conditions" rows="2" placeholder="고혈압, 당뇨, 심장질환 등 기존 질환을 입력하세요..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비상 연락처</label>
                    <input type="tel" class="form-control" id="w_emergency_contact" placeholder="010-0000-0000">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비상 연락인</label>
                    <input type="text" class="form-control" id="w_emergency_name" placeholder="관계 + 이름 (예: 배우자 김영희)">
                </div>
            </div>
        </div>

        <!-- Step 3: 주소/계정 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-geo-alt"></i> 주소/계정</h5>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">주소 1</label>
                    <input type="text" class="form-control" id="w_address1" placeholder="도로명 주소">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">주소 2</label>
                    <input type="text" class="form-control" id="w_address2" placeholder="상세 주소 (동/호수 등)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">시/도</label>
                    <input type="text" class="form-control" id="w_city" placeholder="서울특별시">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">우편번호</label>
                    <input type="text" class="form-control" id="w_zip" placeholder="12345" maxlength="10">
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="w_password" required minlength="6" placeholder="6자 이상">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 확인 <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="w_password2" required placeholder="비밀번호 재입력">
                </div>
            </div>
        </div>

        <!-- Step 4: 검토 완료 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-check-circle"></i> 검토 및 제출</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 등록 버튼을 눌러주세요. 항목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary"></div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-primary" id="btnNext" onclick="nextStep()" style="background:var(--wizard-color);border-color:var(--wizard-color)">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 환자 등록
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
                <div id="resultPatientId" class="mb-3"></div>
                <a href="admin_patient_wizard.php" class="btn btn-danger mt-3 text-white">새 환자 등록</a>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">대시보드</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '기본 정보', icon: 'bi-person' },
    { num: 2, label: '의료 정보', icon: 'bi-heart-pulse' },
    { num: 3, label: '주소/계정', icon: 'bi-geo-alt' },
    { num: 4, label: '검토 완료', icon: 'bi-check-circle' }
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = { photo: '' };
let hospitalList = [];

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
    if (step === 4) buildReview();
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
        const fn = document.getElementById('w_first_name').value.trim();
        const ln = document.getElementById('w_last_name').value.trim();
        const em = document.getElementById('w_email').value.trim();
        const ph = document.getElementById('w_phone').value.trim();
        const dob = document.getElementById('w_dob').value;
        if (!fn) { alert('이름을 입력해주세요.'); return false; }
        if (!ln) { alert('성을 입력해주세요.'); return false; }
        if (!em) { alert('이메일을 입력해주세요.'); return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { alert('올바른 이메일 형식을 입력해주세요.'); return false; }
        if (!ph) { alert('전화번호를 입력해주세요.'); return false; }
        if (!dob) { alert('생년월일을 입력해주세요.'); return false; }
    }
    if (step === 2) {
        const tid = document.getElementById('w_tenant_id').value;
        if (!tid) { alert('소속 병원을 선택해주세요.'); return false; }
    }
    if (step === 3) {
        const pw = document.getElementById('w_password').value;
        const pw2 = document.getElementById('w_password2').value;
        if (pw.length < 6) { alert('비밀번호는 6자 이상이어야 합니다.'); return false; }
        if (pw !== pw2) { alert('비밀번호가 일치하지 않습니다.'); return false; }
    }
    return true;
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
    zone.innerHTML = '<div class="spinner-border text-danger"></div>';

    const fd = new FormData();
    fd.append('action', 'upload_photo');
    fd.append('file', input.files[0]);
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();

    if (d.success) {
        zone.innerHTML = `<img src="${d.url}" alt="photo"><br><small class="text-success mt-1">업로드 완료</small>`;
        wizardData.photo = d.url;
    } else {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${d.error}</small>`;
    }
}

// ── 병원 검색 드롭다운 ──
async function loadHospitals() {
    const fd = new FormData();
    fd.append('action', 'load_hospitals');
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) hospitalList = d.hospitals;
}

function showHospitalDropdown() {
    filterHospitals();
    document.getElementById('hospitalDropdown').classList.add('show');
}

function filterHospitals() {
    const q = document.getElementById('w_hospital_search').value.trim().toLowerCase();
    const dropdown = document.getElementById('hospitalDropdown');
    const filtered = hospitalList.filter(h => !q || h.hospital_name.toLowerCase().includes(q));
    if (filtered.length === 0) {
        dropdown.innerHTML = '<div class="hospital-item text-muted">검색 결과 없음</div>';
    } else {
        dropdown.innerHTML = filtered.map(h =>
            `<div class="hospital-item" onclick="selectHospital(${h.id}, '${esc(h.hospital_name)}')">${esc(h.hospital_name)}</div>`
        ).join('');
    }
    dropdown.classList.add('show');
}

function selectHospital(id, name) {
    document.getElementById('w_tenant_id').value = id;
    document.getElementById('w_hospital_search').value = name;
    document.getElementById('selectedHospitalInfo').innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> 선택됨: ${esc(name)} (ID: ${id})</span>`;
    document.getElementById('hospitalDropdown').classList.remove('show');
}

// 외부 클릭 시 드롭다운 닫기
document.addEventListener('click', function(e) {
    if (!e.target.closest('.hospital-search-wrap')) {
        document.getElementById('hospitalDropdown').classList.remove('show');
    }
});

// ── XSS 방지 ──
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ── 검토 화면 생성 ──
function buildReview() {
    const gender = document.querySelector('input[name="gender"]:checked')?.value === '1' ? '남성' : '여성';
    const hospitalName = document.getElementById('w_hospital_search').value || '-';

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-person"></i> 1. 기본 정보</h6>
            <div class="row small">
                <div class="col-6"><strong>이름:</strong> ${esc(document.getElementById('w_first_name').value)}</div>
                <div class="col-6"><strong>성:</strong> ${esc(document.getElementById('w_last_name').value)}</div>
                <div class="col-6"><strong>이메일:</strong> ${esc(document.getElementById('w_email').value)}</div>
                <div class="col-6"><strong>전화:</strong> ${esc(document.getElementById('w_phone').value)}</div>
                <div class="col-4"><strong>성별:</strong> ${gender}</div>
                <div class="col-4"><strong>생년월일:</strong> ${esc(document.getElementById('w_dob').value) || '-'}</div>
                <div class="col-4"><strong>혈액형:</strong> ${esc(document.getElementById('w_blood_group').value) || '-'}</div>
                ${wizardData.photo ? `<div class="col-12 mt-2"><img src="${wizardData.photo}" style="height:60px;border-radius:4px"></div>` : ''}
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-heart-pulse"></i> 2. 의료 정보</h6>
            <div class="row small">
                <div class="col-12"><strong>소속 병원:</strong> <span class="badge bg-danger">${esc(hospitalName)}</span></div>
                <div class="col-12"><strong>보험 정보:</strong> ${esc(document.getElementById('w_insurance').value) || '-'}</div>
                <div class="col-12"><strong>알레르기:</strong> ${esc(document.getElementById('w_allergies').value) || '없음'}</div>
                <div class="col-12"><strong>기저질환:</strong> ${esc(document.getElementById('w_conditions').value) || '없음'}</div>
                <div class="col-6"><strong>비상 연락처:</strong> ${esc(document.getElementById('w_emergency_contact').value) || '-'}</div>
                <div class="col-6"><strong>비상 연락인:</strong> ${esc(document.getElementById('w_emergency_name').value) || '-'}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-geo-alt"></i> 3. 주소/계정</h6>
            <div class="row small">
                <div class="col-12"><strong>주소:</strong> ${esc(document.getElementById('w_address1').value)} ${esc(document.getElementById('w_address2').value)}</div>
                <div class="col-6"><strong>시/도:</strong> ${esc(document.getElementById('w_city').value) || '-'}</div>
                <div class="col-6"><strong>우편번호:</strong> ${esc(document.getElementById('w_zip').value) || '-'}</div>
                <div class="col-12"><strong>비밀번호:</strong> ${'*'.repeat(document.getElementById('w_password').value.length)}</div>
            </div>
        </div>
    `;
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('환자를 등록하시겠습니까?')) return;

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
        blood_group: document.getElementById('w_blood_group').value,
        photo: wizardData.photo,
        tenant_id: document.getElementById('w_tenant_id').value,
        insurance: document.getElementById('w_insurance').value.trim(),
        allergies: document.getElementById('w_allergies').value.trim(),
        conditions: document.getElementById('w_conditions').value.trim(),
        emergency_contact: document.getElementById('w_emergency_contact').value.trim(),
        emergency_name: document.getElementById('w_emergency_name').value.trim(),
        address1: document.getElementById('w_address1').value.trim(),
        address2: document.getElementById('w_address2').value.trim(),
        city: document.getElementById('w_city').value.trim(),
        zip: document.getElementById('w_zip').value.trim(),
        password: document.getElementById('w_password').value,
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
            if (result.patient_unique_id) {
                document.getElementById('resultPatientId').innerHTML = `<span class="badge bg-danger fs-5 p-2">${esc(result.patient_unique_id)}</span>`;
            }
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
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 환자 등록';
}

// ── 초기화 ──
loadHospitals();
renderStepHeader();
showStep(1);
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

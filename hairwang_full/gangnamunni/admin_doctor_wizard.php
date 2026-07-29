<?php
/**
 * admin_doctor_wizard.php - 의사 등록 마법사
 * 5단계 원형 스텝 프로세서로 의사 등록 처리
 * (admin_hospital_wizard.php 패턴 동일)
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
require_permission('doctors');
require_once __DIR__ . '/includes/hospital_filter.php'; // 병원 격리(super_admin=전체, 그 외=소속 병원)

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 병원 격리: 현재 관리자가 의사를 등록할 수 있는 tenants.id(=hms_hospital_id) 목록.
//   반환: null=전체(super_admin 등), []=소속 병원 없음(선택 불가), [1,2..]=소속 병원의 tenant id
function wizard_allowed_tenant_ids($connect) {
    if (!function_exists('get_hospital_filter_in')) return null;
    $hf_in = get_hospital_filter_in($connect);   // gangnamunni 병원 id CSV, null(전체), '0'(없음)
    if ($hf_in === null) return null;
    if ($hf_in === '0')  return [];
    $ids = [];
    // tenants.id(=hms_hospital_id) 는 UUID 문자열 → 문자열로 반환
    $r = mysqli_query($connect, "SELECT hms_hospital_id FROM gangnamunni_hospitals WHERE id IN ({$hf_in}) AND hms_hospital_id IS NOT NULL AND hms_hospital_id <> ''");
    while ($r && $row = mysqli_fetch_assoc($r)) { $ids[] = (string)$row['hms_hospital_id']; }
    return array_values(array_unique($ids));
}

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 이메일 중복 체크
    if ($action === 'check_email') {
        $email = mysqli_real_escape_string($connect, trim($_POST['email'] ?? ''));
        $r = mysqli_query($connect, "SELECT id FROM users WHERE email = '{$email}' LIMIT 1");
        echo json_encode(['exists' => mysqli_num_rows($r) > 0]);
        exit;
    }

    // 프로필 사진 업로드
    if ($action === 'upload_photo') {
        $uploadDir = __DIR__ . '/uploads/doctors/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => '파일 업로드 실패']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'error' => '이미지 파일만 가능합니다 (jpg, png, gif, webp)']);
            exit;
        }
        $fname = 'doctor_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            echo json_encode(['success' => true, 'url' => 'uploads/doctors/' . $fname, 'filename' => $fname]);
        } else {
            echo json_encode(['success' => false, 'error' => '파일 저장 실패']);
        }
        exit;
    }

    // 진료과 목록 조회
    if ($action === 'load_departments') {
        $tenantId = mysqli_real_escape_string($connect, $_POST['tenant_id'] ?? '');
        $where = $tenantId ? "WHERE tenant_id = '{$tenantId}'" : '';
        $r = mysqli_query($connect, "SELECT id, title FROM doctor_departments {$where} ORDER BY title ASC");
        $departments = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $departments[] = $row;
        }
        echo json_encode(['success' => true, 'departments' => $departments]);
        exit;
    }

    // 병원(tenants) 목록 조회 — 소속 병원만 (super_admin 은 전체)
    if ($action === 'load_hospitals') {
        $allowed_t = wizard_allowed_tenant_ids($connect);
        $tenant_where = '';
        if (is_array($allowed_t)) {
            if (empty($allowed_t)) { echo json_encode(['success' => true, 'hospitals' => []]); exit; }
            $quoted_t = array_map(function($t) use ($connect) { return "'" . mysqli_real_escape_string($connect, $t) . "'"; }, $allowed_t);
            $tenant_where = " WHERE id IN (" . implode(',', $quoted_t) . ")";
        }
        $r = mysqli_query($connect, "SELECT id, hospital_name FROM tenants{$tenant_where} ORDER BY hospital_name ASC");
        $hospitals = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $hospitals[] = $row;
        }
        echo json_encode(['success' => true, 'hospitals' => $hospitals]);
        exit;
    }

    // 선택한 병원의 스케줄 조회
    if ($action === 'load_schedules') {
        $tenantId = mysqli_real_escape_string($connect, $_POST['tenant_id'] ?? '');
        $r = mysqli_query($connect, "SELECT * FROM hospital_schedules WHERE tenant_id = '{$tenantId}' ORDER BY id ASC");
        $schedules = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $schedules[] = $row;
        }
        echo json_encode(['success' => true, 'schedules' => $schedules]);
        exit;
    }

    // 최종 등록 트랜잭션
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
            $password   = password_hash($data['password'] ?? '', PASSWORD_BCRYPT);
            $tenantId   = $esc($data['hospital'] ?? '');
            // 병원 격리: 소속 병원(tenant)만 등록 가능 (POST 로 임의 병원 지정 차단)
            //   tenant id 는 UUID 문자열이므로 원본값으로 비교
            $allowed_t = wizard_allowed_tenant_ids($connect);
            if (is_array($allowed_t) && !in_array((string)($data['hospital'] ?? ''), $allowed_t, true)) {
                throw new Exception('선택한 병원에 대한 권한이 없습니다.');
            }
            $deptId     = intval($data['department_id'] ?? 0);
            $specialist = $esc($data['specialist'] ?? '');
            $qualification = $esc($data['qualification'] ?? '');
            $description = $esc($data['description'] ?? '');
            $charge     = floatval($data['appointment_charge'] ?? 0);
            $address1   = $esc($data['address1'] ?? '');
            $address2   = $esc($data['address2'] ?? '');
            $city       = $esc($data['city'] ?? '');
            $zip        = $esc($data['zip'] ?? '');
            $photoUrl   = $esc($data['photo_url'] ?? '');

            // 1) users INSERT
            $ownerType = 'App\\\\Models\\\\Doctor';
            $sql = "INSERT INTO users (department_id, first_name, last_name, email, password, phone, gender, status, qualification, dob, blood_group, owner_type, tenant_id, created_at, updated_at)
                    VALUES (2, '{$firstName}', '{$lastName}', '{$email}', '{$password}', '{$phone}', {$gender}, 1, '{$qualification}', " . ($dob ? "'{$dob}'" : "NULL") . ", '{$bloodGroup}', '{$ownerType}', '{$tenantId}', NOW(), NOW())";
            $res = mysqli_query($connect, $sql);
            if (!$res) throw new Exception('users INSERT 실패: ' . mysqli_error($connect));
            $userId = mysqli_insert_id($connect);

            // 2) doctors INSERT
            $sql = "INSERT INTO doctors (user_id, doctor_department_id, specialist, appointment_charge, description, tenant_id, created_at, updated_at)
                    VALUES ({$userId}, {$deptId}, '{$specialist}', {$charge}, '{$description}', '{$tenantId}', NOW(), NOW())";
            $res = mysqli_query($connect, $sql);
            if (!$res) throw new Exception('doctors INSERT 실패: ' . mysqli_error($connect));
            $doctorId = mysqli_insert_id($connect);

            // 3) users owner_id 업데이트
            $res = mysqli_query($connect, "UPDATE users SET owner_id={$doctorId} WHERE id={$userId}");
            if (!$res) throw new Exception('users UPDATE 실패: ' . mysqli_error($connect));

            // 4) addresses INSERT
            $addrOwnerType = 'App\\\\Models\\\\Doctor';
            $sql = "INSERT INTO addresses (owner_id, owner_type, address1, address2, city, zip, tenant_id, created_at, updated_at)
                    VALUES ({$doctorId}, '{$addrOwnerType}', '{$address1}', '{$address2}', '{$city}', '{$zip}', '{$tenantId}', NOW(), NOW())";
            $res = mysqli_query($connect, $sql);
            if (!$res) throw new Exception('addresses INSERT 실패: ' . mysqli_error($connect));

            // 5) model_has_roles INSERT (role_id=2 -> Doctor)
            $modelType = 'App\\\\Models\\\\User';
            $res = mysqli_query($connect, "INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES (2, '{$modelType}', {$userId})");
            if (!$res) throw new Exception('model_has_roles INSERT 실패: ' . mysqli_error($connect));

            // 6) 프로필 사진 저장 (media 테이블 또는 users.profile_image)
            if ($photoUrl) {
                mysqli_query($connect, "UPDATE users SET profile_image='{$photoUrl}' WHERE id={$userId}");
            }

            // 7) gangnamunni_doctors INSERT (동기화)
            $ghRes = mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals WHERE hms_hospital_id='{$tenantId}' LIMIT 1");
            $ghRow = mysqli_fetch_assoc($ghRes);
            if ($ghRow) {
                $fullName = $esc(trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
                $sql = "INSERT INTO gangnamunni_doctors (hospital_id, hms_doctor_id, name, specialty, bio, photo_url, created_at)
                        VALUES ({$ghRow['id']}, {$doctorId}, '{$fullName}', '{$specialist}', '{$description}', '{$photoUrl}', NOW())";
                mysqli_query($connect, $sql);
            }

            // 8) 근무 스케줄 저장 (schedules 데이터가 있으면)
            $schedules = $data['schedules'] ?? [];
            if (!empty($schedules)) {
                foreach ($schedules as $sched) {
                    $dayOfWeek = intval($sched['day'] ?? 0);
                    $startTime = $esc($sched['start'] ?? '09:00:00');
                    $endTime   = $esc($sched['end'] ?? '18:00:00');
                    if ($dayOfWeek >= 0 && $dayOfWeek <= 6) {
                        mysqli_query($connect, "INSERT INTO doctor_sessions (doctor_id, day_of_week, start_time, end_time, tenant_id, created_at, updated_at)
                                VALUES ({$doctorId}, {$dayOfWeek}, '{$startTime}', '{$endTime}', '{$tenantId}', NOW(), NOW())");
                    }
                }
            }

            mysqli_commit($connect);
            echo json_encode([
                'success' => true,
                'doctor_id' => $doctorId,
                'user_id' => $userId,
                'message' => '의사가 성공적으로 등록되었습니다.'
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

$admin_page_title = '의사 등록 마법사';
$admin_current = 'doctor_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 원형 스텝 프로세서 (녹색 테마: #28a745) ── */
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
.wizard-circle.active { background: #28a745; color: #fff; border-color: #28a745; box-shadow: 0 0 0 5px rgba(40,167,69,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #28a745; color: #fff; border-color: #28a745; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line {
    width: 50px; height: 3px;
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
.wizard-step-label.active { color: #28a745; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 이미지 업로드 드래그존 ── */
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
.upload-zone:hover { border-color: #28a745; background: rgba(40,167,69,0.03); }
.upload-zone.dragover { border-color: #28a745; background: rgba(40,167,69,0.08); }
.upload-zone img { max-height: 120px; max-width: 100%; border-radius: 8px; }
.upload-zone .upload-icon { font-size: 36px; color: var(--bs-secondary-color); }

/* ── 요일 스케줄 ── */
.schedule-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.schedule-row label.day-label { width: 60px; font-weight: 600; font-size: 13px; flex-shrink: 0; }
.schedule-row input[type=time] { width: 120px; }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #28a745; }
.review-section h6:hover { text-decoration: underline; }

/* ── 하단 버튼 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

/* ── 병원 검색 드롭다운 ── */
.hospital-search-wrap { position: relative; }
.hospital-search-wrap .dropdown-list {
    position: absolute; top: 100%; left: 0; right: 0;
    max-height: 250px; overflow-y: auto;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0 0 8px 8px;
    z-index: 100;
    display: none;
}
.hospital-search-wrap .dropdown-list.show { display: block; }
.hospital-search-wrap .dropdown-item {
    padding: 8px 12px; cursor: pointer; font-size: 14px;
    border-bottom: 1px solid var(--bs-border-color);
}
.hospital-search-wrap .dropdown-item:hover { background: rgba(40,167,69,0.08); }
.hospital-search-wrap .dropdown-item.selected { background: rgba(40,167,69,0.15); font-weight: 700; }

/* ── 이메일 상태 ── */
.email-status-icon { font-size: 18px; }
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-plus-fill text-success"></i> 의사 등록 마법사</h4>
        <small class="text-muted">새 의사를 단계별로 등록합니다 (5단계)</small>
    </div>
</div>
<!-- [추가 2026-06-19] 이중 스키마 안내 -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        <strong>안내:</strong> 이 마법사는 HMS <code>doctors</code> 테이블과 강남언니 <code>gangnamunni_doctors</code> 테이블에 <u>동시에 저장</u>하며,
        연결키 <code>hms_doctor_id</code>로 매핑됩니다. 등록 즉시 "의사 관리" 목록에 표시됩니다.
        (HMS에 직접 등록된 의사는 5분 주기 동기화 크론으로 보정됩니다.)
    </div>
</div>

<!-- 원형 스텝 인디케이터 -->
<div class="card mb-4">
    <div class="card-body p-2">
        <div class="wizard-header" id="wizardHeader">
            <!-- JS로 생성 -->
        </div>
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
                    <label class="form-label fw-bold">이름 (First Name) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_first_name" required placeholder="예: 길동">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">성 (Last Name) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_last_name" required placeholder="예: 홍">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">이메일 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="w_email" required placeholder="doctor@example.com" oninput="checkEmail()">
                        <span class="input-group-text" id="emailStatus"></span>
                    </div>
                    <small class="text-muted">로그인에 사용될 이메일입니다</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">전화번호 <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="w_phone" required placeholder="010-1234-5678">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">성별 <span class="text-danger">*</span></label>
                    <div class="d-flex gap-4 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="w_gender" id="genderMale" value="1" checked>
                            <label class="form-check-label" for="genderMale">남성</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="w_gender" id="genderFemale" value="2">
                            <label class="form-check-label" for="genderFemale">여성</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">생년월일</label>
                    <input type="date" class="form-control" id="w_dob">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">혈액형</label>
                    <select class="form-select" id="w_blood_group">
                        <option value="">선택안함</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">프로필 사진</label>
                    <div class="upload-zone" id="photoZone" onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭 또는 드래그하여 업로드 (1:1 비율 권장)</span>
                    </div>
                    <input type="file" id="photoInput" accept="image/*" class="d-none" onchange="uploadPhoto(this)">
                </div>
            </div>
        </div>

        <!-- Step 2: 전문 분야 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-hospital"></i> 전문 분야</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">진료과 선택 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_department">
                        <option value="">-- 진료과를 선택하세요 --</option>
                        <!-- AJAX로 로드 -->
                    </select>
                    <small class="text-muted">병원 선택 후 해당 병원의 진료과가 로드됩니다</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">전문분야 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_specialist" required placeholder="예: 성형외과 전문의, 피부과 전문의">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">자격/학력</label>
                    <input type="text" class="form-control" id="w_qualification" placeholder="예: 서울대 의대 졸업, 전문의 자격증">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">진료비 (원)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="w_charge" min="0" value="0" placeholder="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">소개글</label>
                    <textarea class="form-control" id="w_description" rows="4" placeholder="의사의 경력, 전문 시술, 특기 등을 소개해주세요..."></textarea>
                </div>
            </div>
        </div>

        <!-- Step 3: 소속 병원 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-building"></i> 소속 병원</h5>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold">병원 선택 <span class="text-danger">*</span></label>
                    <div class="hospital-search-wrap">
                        <input type="text" class="form-control" id="w_hospital_search" placeholder="병원명을 검색하세요..." autocomplete="off"
                               oninput="filterHospitals()" onfocus="showHospitalDropdown()">
                        <input type="hidden" id="w_hospital_id">
                        <div class="dropdown-list" id="hospitalDropdown">
                            <!-- AJAX로 로드 -->
                        </div>
                    </div>
                    <small class="text-muted" id="hospitalSelected"></small>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">근무 요일 / 시간</label>
                    <p class="text-muted small mb-2">근무하는 요일을 선택하고 시간을 설정하세요.</p>
                    <div id="scheduleRows">
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_mon" data-day="0" checked>
                                <label class="form-check-label fw-bold small" for="sched_mon">월요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="0" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="0" value="18:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_tue" data-day="1" checked>
                                <label class="form-check-label fw-bold small" for="sched_tue">화요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="1" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="1" value="18:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_wed" data-day="2" checked>
                                <label class="form-check-label fw-bold small" for="sched_wed">수요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="2" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="2" value="18:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_thu" data-day="3" checked>
                                <label class="form-check-label fw-bold small" for="sched_thu">목요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="3" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="3" value="18:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_fri" data-day="4" checked>
                                <label class="form-check-label fw-bold small" for="sched_fri">금요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="4" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="4" value="18:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_sat" data-day="5">
                                <label class="form-check-label fw-bold small" for="sched_sat">토요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="5" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="5" value="14:00">
                        </div>
                        <div class="schedule-row">
                            <div class="form-check" style="width:80px">
                                <input class="form-check-input sched-check" type="checkbox" id="sched_sun" data-day="6">
                                <label class="form-check-label fw-bold small" for="sched_sun">일요일</label>
                            </div>
                            <input type="time" class="form-control form-control-sm sched-start" data-day="6" value="09:00">
                            <span class="text-muted">~</span>
                            <input type="time" class="form-control form-control-sm sched-end" data-day="6" value="13:00">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: 주소/계정 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-geo-alt"></i> 주소 / 계정 설정</h5>
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-muted"><i class="bi bi-house"></i> 주소 정보</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">주소1</label>
                    <input type="text" class="form-control" id="w_address1" placeholder="도로명 주소">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">주소2</label>
                    <input type="text" class="form-control" id="w_address2" placeholder="상세 주소 (동/호)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">시/구</label>
                    <input type="text" class="form-control" id="w_city" placeholder="예: 서울특별시 강남구">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">우편번호</label>
                    <input type="text" class="form-control" id="w_zip" placeholder="예: 06241" maxlength="10">
                </div>
                <div class="col-12 mt-4">
                    <h6 class="text-muted"><i class="bi bi-key"></i> 로그인 계정 설정</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="w_password" required minlength="6" placeholder="6자 이상">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('w_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">의사 로그인에 사용될 비밀번호</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 확인 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="w_password2" required placeholder="비밀번호 재입력">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('w_password2', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: 검토 완료 -->
        <div class="wizard-content" data-step="5">
            <h5 class="mb-3"><i class="bi bi-check-circle"></i> 검토 및 제출</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 등록 버튼을 눌러주세요. 각 섹션 제목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary">
                <!-- JS로 채움 -->
            </div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-success" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 의사 등록
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
                <a href="admin_doctor_wizard.php" class="btn btn-success mt-3">새 의사 등록</a>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">대시보드로</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '기본 정보', icon: 'bi-person' },
    { num: 2, label: '전문 분야', icon: 'bi-hospital' },
    { num: 3, label: '소속 병원', icon: 'bi-building' },
    { num: 4, label: '주소/계정', icon: 'bi-geo-alt' },
    { num: 5, label: '검토 완료', icon: 'bi-check-circle' }
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = {
    photo_url: '',
    hospital_id: '',
    hospital_name: ''
};
let allHospitals = [];
let emailCheckOk = false;

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
    if (step === 5) renderReview();
    // Step 3 진입 시 병원 목록 로드
    if (step === 3 && allHospitals.length === 0) loadHospitals();
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
        const firstName = document.getElementById('w_first_name').value.trim();
        const lastName = document.getElementById('w_last_name').value.trim();
        const email = document.getElementById('w_email').value.trim();
        const phone = document.getElementById('w_phone').value.trim();
        const gender = document.querySelector('input[name="w_gender"]:checked');

        if (!firstName) { alert('이름을 입력해주세요.'); document.getElementById('w_first_name').focus(); return false; }
        if (!lastName) { alert('성을 입력해주세요.'); document.getElementById('w_last_name').focus(); return false; }
        if (!email) { alert('이메일을 입력해주세요.'); document.getElementById('w_email').focus(); return false; }
        // 이메일 형식 체크
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) { alert('올바른 이메일 형식이 아닙니다.'); document.getElementById('w_email').focus(); return false; }
        // 이메일 중복 여부 (마지막 체크 결과 확인)
        const statusEl = document.getElementById('emailStatus');
        if (statusEl.querySelector('.text-danger')) {
            alert('이미 사용 중인 이메일입니다. 다른 이메일을 입력해주세요.');
            document.getElementById('w_email').focus();
            return false;
        }
        if (!phone) { alert('전화번호를 입력해주세요.'); document.getElementById('w_phone').focus(); return false; }
        if (!gender) { alert('성별을 선택해주세요.'); return false; }
        return true;
    }
    if (step === 2) {
        const dept = document.getElementById('w_department').value;
        const specialist = document.getElementById('w_specialist').value.trim();
        if (!dept) { alert('진료과를 선택해주세요.'); document.getElementById('w_department').focus(); return false; }
        if (!specialist) { alert('전문분야를 입력해주세요.'); document.getElementById('w_specialist').focus(); return false; }
        return true;
    }
    if (step === 3) {
        if (!wizardData.hospital_id) { alert('병원을 선택해주세요.'); document.getElementById('w_hospital_search').focus(); return false; }
        return true;
    }
    if (step === 4) {
        const pw = document.getElementById('w_password').value;
        const pw2 = document.getElementById('w_password2').value;
        if (pw.length < 6) { alert('비밀번호는 6자 이상이어야 합니다.'); document.getElementById('w_password').focus(); return false; }
        if (pw !== pw2) { alert('비밀번호가 일치하지 않습니다.'); document.getElementById('w_password2').focus(); return false; }
        return true;
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
    // 형식 먼저 확인
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(val)) {
        st.innerHTML = '<i class="bi bi-exclamation-circle text-warning email-status-icon" title="이메일 형식 확인"></i>';
        return;
    }
    st.innerHTML = '<span class="spinner-border spinner-border-sm text-muted"></span>';
    emailTimer = setTimeout(async () => {
        try {
            const fd = new FormData();
            fd.append('action', 'check_email');
            fd.append('email', val);
            const r = await fetch('', {method:'POST', body:fd});
            const d = await r.json();
            st.innerHTML = d.exists
                ? '<i class="bi bi-x-circle text-danger email-status-icon" title="이미 사용 중"></i>'
                : '<i class="bi bi-check-circle text-success email-status-icon" title="사용 가능"></i>';
        } catch(e) {
            st.innerHTML = '<i class="bi bi-question-circle text-muted email-status-icon"></i>';
        }
    }, 500);
}

// ── 프로필 사진 업로드 ──
async function uploadPhoto(input) {
    if (!input.files[0]) return;
    const zone = document.getElementById('photoZone');
    zone.innerHTML = '<div class="spinner-border text-success"></div><br><small class="text-muted mt-1">업로드 중...</small>';

    const fd = new FormData();
    fd.append('action', 'upload_photo');
    fd.append('file', input.files[0]);
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            zone.innerHTML = `<img src="${d.url}" alt="프로필"><br><small class="text-success mt-1">업로드 완료</small>`;
            wizardData.photo_url = d.url;
        } else {
            zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${d.error}</small>`;
        }
    } catch(e) {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">업로드 오류</small>`;
    }
}

// 드래그 앤 드롭 지원
(function() {
    const zone = document.getElementById('photoZone');
    if (!zone) return;
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function(e) { e.preventDefault(); zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            const input = document.getElementById('photoInput');
            input.files = e.dataTransfer.files;
            uploadPhoto(input);
        }
    });
})();

// ── 비밀번호 토글 ──
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ── 병원 목록 로드 (AJAX) ──
async function loadHospitals() {
    try {
        const fd = new FormData();
        fd.append('action', 'load_hospitals');
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            allHospitals = d.hospitals;
            renderHospitalDropdown(allHospitals);
        }
    } catch(e) {
        console.error('병원 목록 로드 실패:', e);
    }
}

function renderHospitalDropdown(list) {
    const dd = document.getElementById('hospitalDropdown');
    dd.innerHTML = list.map(h => `<div class="dropdown-item ${wizardData.hospital_id == h.id ? 'selected' : ''}" data-id="${h.id}" data-name="${escAttr(h.hospital_name)}" onclick="selectHospital(this)">${escHtml(h.hospital_name)}</div>`).join('');
}

function filterHospitals() {
    const q = document.getElementById('w_hospital_search').value.trim().toLowerCase();
    const dd = document.getElementById('hospitalDropdown');
    dd.classList.add('show');
    if (!q) {
        renderHospitalDropdown(allHospitals);
        return;
    }
    const filtered = allHospitals.filter(h => (h.hospital_name || '').toLowerCase().includes(q));
    renderHospitalDropdown(filtered);
}

function showHospitalDropdown() {
    const dd = document.getElementById('hospitalDropdown');
    dd.classList.add('show');
    if (allHospitals.length === 0) loadHospitals();
}

function selectHospital(el) {
    const id = el.dataset.id;
    const name = el.dataset.name;
    wizardData.hospital_id = id;
    wizardData.hospital_name = name;
    document.getElementById('w_hospital_search').value = name;
    document.getElementById('w_hospital_id').value = id;
    document.getElementById('hospitalSelected').innerHTML = `<i class="bi bi-check-circle text-success"></i> 선택됨: <strong>${escHtml(name)}</strong> (ID: ${id})`;
    document.getElementById('hospitalDropdown').classList.remove('show');

    // 진료과 로드 (해당 병원 기준)
    loadDepartments(id);
}

// 드롭다운 외부 클릭 시 닫기
document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.hospital-search-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('hospitalDropdown').classList.remove('show');
    }
});

// ── 진료과 로드 ──
async function loadDepartments(tenantId) {
    try {
        const fd = new FormData();
        fd.append('action', 'load_departments');
        fd.append('tenant_id', tenantId || '');
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            const sel = document.getElementById('w_department');
            sel.innerHTML = '<option value="">-- 진료과를 선택하세요 --</option>';
            d.departments.forEach(dept => {
                sel.innerHTML += `<option value="${dept.id}">${escHtml(dept.title)}</option>`;
            });
        }
    } catch(e) {
        console.error('진료과 로드 실패:', e);
    }
}

// ── 근무 스케줄 수집 ──
function getSchedules() {
    const schedules = [];
    const dayLabels = ['월요일','화요일','수요일','목요일','금요일','토요일','일요일'];
    document.querySelectorAll('.sched-check').forEach(chk => {
        const day = parseInt(chk.dataset.day);
        if (chk.checked) {
            const start = document.querySelector(`.sched-start[data-day="${day}"]`).value;
            const end = document.querySelector(`.sched-end[data-day="${day}"]`).value;
            schedules.push({ day: day, start: start + ':00', end: end + ':00', label: dayLabels[day] });
        }
    });
    return schedules;
}

// ── 검토 화면 렌더링 ──
function renderReview() {
    const firstName = document.getElementById('w_first_name').value.trim();
    const lastName = document.getElementById('w_last_name').value.trim();
    const email = document.getElementById('w_email').value.trim();
    const phone = document.getElementById('w_phone').value.trim();
    const gender = document.querySelector('input[name="w_gender"]:checked');
    const genderText = gender ? (gender.value === '1' ? '남성' : '여성') : '-';
    const dob = document.getElementById('w_dob').value || '-';
    const bloodGroup = document.getElementById('w_blood_group').value || '-';

    const deptEl = document.getElementById('w_department');
    const deptText = deptEl.options[deptEl.selectedIndex]?.text || '-';
    const specialist = document.getElementById('w_specialist').value.trim() || '-';
    const qualification = document.getElementById('w_qualification').value.trim() || '-';
    const charge = document.getElementById('w_charge').value || '0';
    const description = document.getElementById('w_description').value.trim();

    const hospitalName = wizardData.hospital_name || '-';
    const schedules = getSchedules();
    const scheduleText = schedules.length > 0
        ? schedules.map(s => `${s.label}: ${s.start.substring(0,5)}~${s.end.substring(0,5)}`).join(' / ')
        : '미설정';

    const address1 = document.getElementById('w_address1').value.trim() || '-';
    const address2 = document.getElementById('w_address2').value.trim() || '';
    const city = document.getElementById('w_city').value.trim() || '-';
    const zip = document.getElementById('w_zip').value.trim() || '-';

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-person"></i> 1. 기본 정보 <i class="bi bi-pencil-square small"></i></h6>
            <div class="row small">
                <div class="col-6"><strong>이름:</strong> ${escHtml(firstName)}</div>
                <div class="col-6"><strong>성:</strong> ${escHtml(lastName)}</div>
                <div class="col-6"><strong>이메일:</strong> ${escHtml(email)}</div>
                <div class="col-6"><strong>전화:</strong> ${escHtml(phone)}</div>
                <div class="col-4"><strong>성별:</strong> ${genderText}</div>
                <div class="col-4"><strong>생년월일:</strong> ${escHtml(dob)}</div>
                <div class="col-4"><strong>혈액형:</strong> ${escHtml(bloodGroup)}</div>
            </div>
            ${wizardData.photo_url ? `<div class="mt-2"><img src="${wizardData.photo_url}" style="height:60px;border-radius:50%"></div>` : ''}
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-hospital"></i> 2. 전문 분야 <i class="bi bi-pencil-square small"></i></h6>
            <div class="row small">
                <div class="col-6"><strong>진료과:</strong> <span class="badge bg-success">${escHtml(deptText)}</span></div>
                <div class="col-6"><strong>전문분야:</strong> ${escHtml(specialist)}</div>
                <div class="col-6"><strong>자격/학력:</strong> ${escHtml(qualification)}</div>
                <div class="col-6"><strong>진료비:</strong> ${Number(charge).toLocaleString()}원</div>
                ${description ? `<div class="col-12 mt-1"><strong>소개:</strong> ${escHtml(description).substring(0, 200)}${description.length > 200 ? '...' : ''}</div>` : ''}
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-building"></i> 3. 소속 병원 <i class="bi bi-pencil-square small"></i></h6>
            <div class="small">
                <div><strong>병원:</strong> <span class="badge bg-info text-dark">${escHtml(hospitalName)}</span></div>
                <div class="mt-1"><strong>근무시간:</strong> ${scheduleText}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(4)"><i class="bi bi-geo-alt"></i> 4. 주소/계정 <i class="bi bi-pencil-square small"></i></h6>
            <div class="row small">
                <div class="col-12"><strong>주소:</strong> ${escHtml(address1)} ${escHtml(address2)}</div>
                <div class="col-6"><strong>시/구:</strong> ${escHtml(city)}</div>
                <div class="col-6"><strong>우편번호:</strong> ${escHtml(zip)}</div>
                <div class="col-12"><strong>비밀번호:</strong> ${'*'.repeat(document.getElementById('w_password').value.length)}</div>
            </div>
        </div>
    `;
}

// ── HTML 이스케이프 헬퍼 ──
function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
function escAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('의사를 등록하시겠습니까?')) return;

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const schedules = getSchedules();

    const data = {
        first_name: document.getElementById('w_first_name').value.trim(),
        last_name: document.getElementById('w_last_name').value.trim(),
        email: document.getElementById('w_email').value.trim(),
        phone: document.getElementById('w_phone').value.trim(),
        gender: parseInt(document.querySelector('input[name="w_gender"]:checked')?.value || '1'),
        dob: document.getElementById('w_dob').value || '',
        blood_group: document.getElementById('w_blood_group').value || '',
        department_id: parseInt(document.getElementById('w_department').value) || 0,
        specialist: document.getElementById('w_specialist').value.trim(),
        qualification: document.getElementById('w_qualification').value.trim(),
        description: document.getElementById('w_description').value.trim(),
        appointment_charge: parseFloat(document.getElementById('w_charge').value) || 0,
        hospital: wizardData.hospital_id,
        address1: document.getElementById('w_address1').value.trim(),
        address2: document.getElementById('w_address2').value.trim(),
        city: document.getElementById('w_city').value.trim(),
        zip: document.getElementById('w_zip').value.trim(),
        password: document.getElementById('w_password').value,
        photo_url: wizardData.photo_url,
        schedules: schedules
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
            document.getElementById('resultTitle').textContent = '의사 등록 완료!';
            document.getElementById('resultMsg').textContent = result.message;
        } else {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            document.getElementById('resultTitle').textContent = '등록 실패';
            document.getElementById('resultMsg').textContent = result.error || '알 수 없는 오류';
        }
        modal.show();
    } catch(e) {
        alert('오류: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 의사 등록';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);

// 페이지 로드 시 진료과 미리 로드 (tenant_id 없이 전체)
loadDepartments('');
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

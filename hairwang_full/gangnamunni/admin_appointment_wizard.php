<?php
/**
 * admin_appointment_wizard.php - 예약 등록 마법사
 * 4단계: 환자 선택 -> 의사/진료과 -> 일시/증상 -> 검토 완료
 * 의사 스케줄 기반 시간 슬롯 자동 계산
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
require_once __DIR__ . '/includes/hospital_filter.php';
require_permission('bookings');

// 수동 예약 등록은 최고관리자만 허용한다.
// 일반 관리자/편집자는 예약 목록 조회·관리만 가능하다.
if (($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>권한 없음</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa}h2{color:#333}p{color:#666}a{color:#667eea}</style></head><body>';
    echo '<h2>접근 권한이 없습니다</h2><p>수동 예약 등록은 최고관리자만 사용할 수 있습니다.</p>';
    echo '<a href="admin_bookings.php">예약 관리로 돌아가기</a></body></html>';
    exit;
}

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// super_admin만 전체 병원. 그 외 관리자/편집자는 세션에 명시적으로
// 배정된 hospital_id의 HMS tenant만 사용한다.
$is_super_admin = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$assigned_hospital_ids = get_strict_admin_hospital_ids($connect);
$allowed_tenant_ids = null;
if (!$is_super_admin) {
    $allowed_tenant_ids = [];
    foreach ($assigned_hospital_ids ?: [] as $assigned_hospital_id) {
        $scope_stmt = mysqli_prepare($connect, "SELECT hms_hospital_id FROM gangnamunni_hospitals WHERE id = ? LIMIT 1");
        if (!$scope_stmt) continue;
        mysqli_stmt_bind_param($scope_stmt, 'i', $assigned_hospital_id);
        mysqli_stmt_execute($scope_stmt);
        $scope_row = mysqli_fetch_assoc(mysqli_stmt_get_result($scope_stmt));
        mysqli_stmt_close($scope_stmt);
        $tenant_id = trim((string)($scope_row['hms_hospital_id'] ?? ''));
        if ($tenant_id === '') {
            // 예전 연동 데이터는 별도 mapping 테이블에 저장되어 있을 수 있다.
            try {
                $mapping_stmt = mysqli_prepare($connect, "SELECT hms_tenant_id FROM gangnamunni_hospital_mapping WHERE hospital_id = ? LIMIT 1");
                if ($mapping_stmt) {
                    mysqli_stmt_bind_param($mapping_stmt, 'i', $assigned_hospital_id);
                    mysqli_stmt_execute($mapping_stmt);
                    $mapping_row = mysqli_fetch_assoc(mysqli_stmt_get_result($mapping_stmt));
                    mysqli_stmt_close($mapping_stmt);
                    $tenant_id = trim((string)($mapping_row['hms_tenant_id'] ?? ''));
                }
            } catch (Throwable $e) {
                error_log('[admin_appointment_wizard] HMS tenant mapping lookup failed: ' . $e->getMessage());
            }
        }
        if ($tenant_id !== '' && preg_match('/^[a-zA-Z0-9-]{1,64}$/', $tenant_id)) {
            $allowed_tenant_ids[] = $tenant_id;
        }
    }
    $allowed_tenant_ids = array_values(array_unique($allowed_tenant_ids));
}

function appointment_tenant_allowed($tenant_id, $allowed_tenant_ids) {
    $tenant_id = trim((string)$tenant_id);
    return $tenant_id !== ''
        && preg_match('/^[a-zA-Z0-9-]{1,64}$/', $tenant_id)
        && ($allowed_tenant_ids === null || in_array($tenant_id, $allowed_tenant_ids, true));
}

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 환자 검색
    if ($action === 'search_patients') {
        $q = $esc(trim($_POST['query'] ?? ''));
        $tenantId = trim((string)($_POST['tenant_id'] ?? ''));
        if (!appointment_tenant_allowed($tenantId, $allowed_tenant_ids)) {
            echo json_encode(['success' => false, 'error' => '해당 병원에 대한 권한이 없습니다.']); exit;
        }
        if (strlen($q) < 1) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $where = "WHERE (u.first_name LIKE '%{$q}%' OR u.last_name LIKE '%{$q}%' OR u.email LIKE '%{$q}%' OR u.phone LIKE '%{$q}%')";
        $tenantIdEsc = $esc($tenantId);
        $where .= " AND p.tenant_id = '{$tenantIdEsc}'";
        $sql = "SELECT p.id AS patient_id, u.id AS user_id, u.first_name, u.last_name, u.email, u.phone
                FROM patients p
                JOIN users u ON u.id = p.user_id
                {$where}
                ORDER BY u.first_name
                LIMIT 20";
        $r = mysqli_query($connect, $sql);
        $patients = [];
        while ($row = mysqli_fetch_assoc($r)) $patients[] = $row;
        echo json_encode(['success' => true, 'data' => $patients]);
        exit;
    }

    // 진료과 목록
    if ($action === 'load_departments') {
        $tenantId = trim((string)($_POST['tenant_id'] ?? ''));
        if (!appointment_tenant_allowed($tenantId, $allowed_tenant_ids)) {
            echo json_encode(['success' => false, 'error' => '해당 병원에 대한 권한이 없습니다.']); exit;
        }
        $tenantIdEsc = $esc($tenantId);
        $where = "WHERE tenant_id = '{$tenantIdEsc}'";
        $r = mysqli_query($connect, "SELECT id, title FROM doctor_departments {$where} ORDER BY title");
        $depts = [];
        while ($row = mysqli_fetch_assoc($r)) $depts[] = $row;
        echo json_encode(['success' => true, 'data' => $depts]);
        exit;
    }

    // 의사 목록 (진료과별)
    if ($action === 'load_doctors') {
        $deptId = intval($_POST['department_id'] ?? 0);
        $tenantId = trim((string)($_POST['tenant_id'] ?? ''));
        if (!appointment_tenant_allowed($tenantId, $allowed_tenant_ids)) {
            echo json_encode(['success' => false, 'error' => '해당 병원에 대한 권한이 없습니다.']); exit;
        }
        $tenantIdEsc = $esc($tenantId);
        $where = "WHERE d.doctor_department_id = {$deptId} AND d.tenant_id = '{$tenantIdEsc}'";
        $sql = "SELECT d.id AS doctor_id, u.first_name, u.last_name, u.email,
                       d.specialist, d.appointment_charge
                FROM doctors d
                JOIN users u ON u.owner_id = d.id AND u.owner_type = 'App\\\\Models\\\\Doctor'
                {$where}
                ORDER BY u.first_name";
        $r = mysqli_query($connect, $sql);
        $doctors = [];
        while ($row = mysqli_fetch_assoc($r)) $doctors[] = $row;
        echo json_encode(['success' => true, 'data' => $doctors]);
        exit;
    }

    // 시간 슬롯 계산
    if ($action === 'load_schedule_slots') {
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $date = $esc(trim($_POST['date'] ?? ''));
        $tenantId = trim((string)($_POST['tenant_id'] ?? ''));

        if (!appointment_tenant_allowed($tenantId, $allowed_tenant_ids)) {
            echo json_encode(['success' => false, 'error' => '해당 병원에 대한 권한이 없습니다.']); exit;
        }

        if (!$doctorId || !$date) {
            echo json_encode(['success' => false, 'error' => '의사/날짜 필요']);
            exit;
        }

        // 요일 계산 (PHP: 0=Sun, 1=Mon ... 6=Sat)
        $dayOfWeek = date('w', strtotime($date));
        $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        $dayName = $dayNames[$dayOfWeek];

        // 의사 스케줄 조회
        $tenantIdEsc = $esc($tenantId);
        $sql = "SELECT s.per_patient_time, sd.available_from, sd.available_to
                FROM schedules s
                JOIN schedule_days sd ON sd.schedule_id = s.id
                JOIN doctors d ON d.id = s.doctor_id
                WHERE s.doctor_id = {$doctorId}
                AND d.tenant_id = '{$tenantIdEsc}'
                AND sd.available_on = '{$dayName}'
                LIMIT 1";
        $r = mysqli_query($connect, $sql);
        $schedule = mysqli_fetch_assoc($r);

        if (!$schedule) {
            echo json_encode(['success' => true, 'data' => [], 'message' => '해당 요일에 진료 스케줄이 없습니다.']);
            exit;
        }

        $from = strtotime($date . ' ' . $schedule['available_from']);
        $to = strtotime($date . ' ' . $schedule['available_to']);
        $perPatient = intval($schedule['per_patient_time'] ?: 30); // 분 단위, 기본 30분

        // 이미 예약된 시간 조회
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';
        $bookedSql = "SELECT opd_date FROM appointments
                      WHERE doctor_id = {$doctorId}
                      AND opd_date BETWEEN '{$dateStart}' AND '{$dateEnd}'
                      AND is_completed != 2"; // 2=취소 제외
        $bookedR = mysqli_query($connect, $bookedSql);
        $booked = [];
        while ($row = mysqli_fetch_assoc($bookedR)) {
            $booked[] = date('H:i', strtotime($row['opd_date']));
        }

        // 슬롯 생성
        $slots = [];
        $current = $from;
        while ($current < $to) {
            $timeStr = date('H:i', $current);
            $slots[] = [
                'time' => $timeStr,
                'available' => !in_array($timeStr, $booked)
            ];
            $current += $perPatient * 60;
        }

        echo json_encode(['success' => true, 'data' => $slots, 'per_patient' => $perPatient]);
        exit;
    }

    // 병원(tenants) 목록
    if ($action === 'load_hospitals') {
        $hospitals = [];
        if ($assigned_hospital_ids === null) {
            $r = mysqli_query($connect, "SELECT id, hospital_name, 1 AS linked FROM tenants ORDER BY hospital_name");
            while ($r && $row = mysqli_fetch_assoc($r)) $hospitals[] = $row;
        } elseif (!empty($assigned_hospital_ids)) {
            $hospital_id_list = implode(',', array_map('intval', $assigned_hospital_ids));
            $mapping_available = false;
            try {
                $mapping_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_hospital_mapping'");
                $mapping_available = $mapping_check && mysqli_num_rows($mapping_check) > 0;
            } catch (Throwable $e) {
                $mapping_available = false;
            }
            $mapping_select = $mapping_available
                ? "COALESCE(NULLIF(h.hms_hospital_id,''), (SELECT m.hms_tenant_id FROM gangnamunni_hospital_mapping m WHERE m.hospital_id=h.id LIMIT 1))"
                : "NULLIF(h.hms_hospital_id,'')";
            $r = mysqli_query($connect,
                "SELECT {$mapping_select} AS id, h.name AS hospital_name
                   FROM gangnamunni_hospitals h
                  WHERE h.id IN ({$hospital_id_list})
                  ORDER BY h.name"
            );
            while ($r && $row = mysqli_fetch_assoc($r)) {
                $tenant_id = trim((string)($row['id'] ?? ''));
                $hospitals[] = [
                    'id' => $tenant_id,
                    'hospital_name' => $row['hospital_name'],
                    'linked' => $tenant_id !== '',
                ];
            }
        }
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

        mysqli_begin_transaction($connect);
        try {
            $patientId    = intval($data['patient_id'] ?? 0);
            $doctorId     = intval($data['doctor_id'] ?? 0);
            $departmentId = intval($data['department_id'] ?? 0);
            $tenantId     = trim((string)($data['tenant_id'] ?? ''));
            if (!appointment_tenant_allowed($tenantId, $allowed_tenant_ids)) {
                throw new Exception('해당 병원에 대한 예약 등록 권한이 없습니다.');
            }
            $tenantIdEsc = $esc($tenantId);
            $opdDate      = $esc($data['opd_date'] ?? ''); // 날짜+시간 yyyy-mm-dd HH:ii:ss
            $problem      = $esc($data['problem'] ?? '');
            $paymentType  = $esc($data['payment_type'] ?? '');
            $paymentStatus = intval($data['payment_type'] !== '' && $data['payment_type'] !== 'unpaid' ? 1 : 0);
            $now          = date('Y-m-d H:i:s');

            // 보낸 ID들도 모두 선택한/배정된 tenant 소속인지 서버에서 재검증한다.
            $patient_ok = mysqli_query($connect, "SELECT 1 FROM patients WHERE id = {$patientId} AND tenant_id = '{$tenantIdEsc}' LIMIT 1");
            $doctor_ok = mysqli_query($connect, "SELECT 1 FROM doctors WHERE id = {$doctorId} AND tenant_id = '{$tenantIdEsc}' LIMIT 1");
            $department_ok = mysqli_query($connect, "SELECT 1 FROM doctor_departments WHERE id = {$departmentId} AND tenant_id = '{$tenantIdEsc}' LIMIT 1");
            if (!$patient_ok || mysqli_num_rows($patient_ok) !== 1
                || !$doctor_ok || mysqli_num_rows($doctor_ok) !== 1
                || !$department_ok || mysqli_num_rows($department_ok) !== 1) {
                throw new Exception('환자, 의사 또는 진료과의 병원 소속이 일치하지 않습니다.');
            }

            // 1) appointments INSERT
            $sql = "INSERT INTO appointments (patient_id, doctor_id, department_id, opd_date, problem,
                    is_completed, payment_type, tenant_id, created_at, updated_at)
                    VALUES ({$patientId}, {$doctorId}, {$departmentId}, '{$opdDate}',
                    '{$problem}', 0, '{$paymentType}', '{$tenantIdEsc}', '{$now}', '{$now}')";
            mysqli_query($connect, $sql);
            if (mysqli_error($connect)) throw new Exception('appointments INSERT 실패: ' . mysqli_error($connect));
            $appointmentId = mysqli_insert_id($connect);

            // 2) gangnamunni_bookings INSERT (서울미녀 연동)
            $patientName = '';
            $patientPhone = '';
            $doctorName = '';
            $deptName = '';

            // 환자 정보
            $pr = mysqli_query($connect, "SELECT u.first_name, u.last_name, u.phone
                FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = {$patientId} LIMIT 1");
            if ($prow = mysqli_fetch_assoc($pr)) {
                $patientName = $prow['first_name'] . ' ' . $prow['last_name'];
                $patientPhone = $prow['phone'];
            }

            // 의사 정보
            $dr = mysqli_query($connect, "SELECT u.first_name, u.last_name
                FROM doctors d JOIN users u ON u.owner_id = d.id AND u.owner_type = 'App\\\\Models\\\\Doctor'
                WHERE d.id = {$doctorId} LIMIT 1");
            if ($drow = mysqli_fetch_assoc($dr)) {
                $doctorName = $drow['first_name'] . ' ' . $drow['last_name'];
            }

            // 진료과 정보
            $depr = mysqli_query($connect, "SELECT title FROM doctor_departments WHERE id = {$departmentId} LIMIT 1");
            if ($deprow = mysqli_fetch_assoc($depr)) $deptName = $deprow['title'];

            $bookingDate = $esc(substr($data['opd_date'], 0, 10));
            $bookingTime = $esc(substr($data['opd_date'], 11, 5));
            $patientNameEsc = $esc($patientName);
            $patientPhoneEsc = $esc($patientPhone);
            $doctorNameEsc = $esc($doctorName);
            $deptNameEsc = $esc($deptName);

            // gangnamunni_hospitals에서 hms_hospital_id로 hospital_id 조회
            $hospitalId = 0;
            $hr = mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals WHERE hms_hospital_id = '{$tenantIdEsc}' LIMIT 1");
            if ($hrow = mysqli_fetch_assoc($hr)) $hospitalId = $hrow['id'];

            if ($hospitalId > 0) {
                $sql2 = "INSERT INTO gangnamunni_bookings (hospital_id, patient_name, phone, booking_date, booking_time, doctor_name, department_name, status, created_at)
                         VALUES ({$hospitalId}, '{$patientNameEsc}', '{$patientPhoneEsc}', '{$bookingDate}', '{$bookingTime}', '{$doctorNameEsc}', '{$deptNameEsc}', 'confirmed', '{$now}')";
                mysqli_query($connect, $sql2);
                // gangnamunni_bookings 실패는 무시 (테이블 없을 수 있음)
            }

            mysqli_commit($connect);

            echo json_encode([
                'success' => true,
                'appointment_id' => $appointmentId,
                'message' => "예약이 성공적으로 등록되었습니다. (ID: {$appointmentId})"
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

$admin_page_title = '예약 등록 마법사';
$admin_current = 'appointment_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 테마색: #fd7e14 (주황색) ── */
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
.wizard-circle.active { background: #fd7e14; color: #fff; border-color: #fd7e14; box-shadow: 0 0 0 5px rgba(253,126,20,0.25); transform: scale(1.1); }
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
.wizard-step-label.active { color: #fd7e14; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 환자 검색 ── */
.patient-search-results {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    display: none;
}
.patient-search-results.show { display: block; }
.patient-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--bs-border-color);
    transition: background 0.15s;
}
.patient-item:last-child { border-bottom: none; }
.patient-item:hover { background: rgba(253,126,20,0.08); }
.patient-item.selected { background: rgba(253,126,20,0.15); font-weight: 600; }

/* ── 선택된 환자 카드 ── */
.patient-info-card {
    border: 2px solid #fd7e14;
    border-radius: 12px;
    padding: 16px;
    background: rgba(253,126,20,0.05);
    display: none;
}
.patient-info-card.show { display: block; }

/* ── 의사 카드 ── */
.doctor-select-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.doctor-select-card:hover { border-color: #fd7e14; background: rgba(253,126,20,0.05); }
.doctor-select-card.selected { border-color: #fd7e14; background: rgba(253,126,20,0.1); box-shadow: 0 0 0 3px rgba(253,126,20,0.2); }
.doctor-select-card .doctor-icon { font-size: 36px; color: #fd7e14; }

/* ── 시간 슬롯 ── */
.time-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.time-slot {
    padding: 8px 16px;
    border: 2px solid var(--bs-border-color);
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
    min-width: 70px;
    text-align: center;
}
.time-slot:hover:not(.disabled) { border-color: #fd7e14; background: rgba(253,126,20,0.08); }
.time-slot.selected { border-color: #fd7e14; background: #fd7e14; color: #fff; }
.time-slot.disabled { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); cursor: not-allowed; text-decoration: line-through; opacity: 0.5; }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #fd7e14; }
.review-section h6:hover { text-decoration: underline; }

/* ── 버튼 영역 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

.btn-appt { background: #fd7e14; border-color: #fd7e14; color: #fff; }
.btn-appt:hover { background: #e8690b; border-color: #e8690b; color: #fff; }
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-calendar-plus" style="color:#fd7e14"></i> 예약 등록 마법사</h4>
        <small class="text-muted">새 예약을 단계별로 등록합니다 (4단계)</small>
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

        <!-- Step 1: 환자 선택 -->
        <div class="wizard-content" data-step="1">
            <h5 class="mb-3"><i class="bi bi-person-check" style="color:#fd7e14"></i> 환자 선택</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">소속 병원 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_hospital" onchange="onHospitalChange()">
                        <option value="">-- 병원 선택 --</option>
                    </select>
                </div>
                <div class="col-md-6">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">환자 검색 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="w_patient_search" placeholder="이름, 전화번호, 이메일로 검색..." oninput="searchPatients()">
                    </div>
                    <div class="patient-search-results mt-1" id="patientResults"></div>
                </div>
                <div class="col-md-12">
                    <div class="patient-info-card" id="selectedPatientCard">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="selPatientName" style="font-size:16px"></strong>
                                <div class="small text-muted mt-1">
                                    <span id="selPatientEmail"></span> &bull; <span id="selPatientPhone"></span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearPatient()"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: 의사/진료과 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-hospital" style="color:#fd7e14"></i> 의사 / 진료과</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">진료과 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_department" onchange="onDepartmentChange()">
                        <option value="">-- 진료과 선택 --</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">진료비</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="w_charge" readonly placeholder="의사 선택 시 자동 표시">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">의사 선택 <span class="text-danger">*</span></label>
                    <div class="row g-3" id="doctorGrid">
                        <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-arrow-up" style="font-size:24px"></i><br>
                            진료과를 먼저 선택해주세요
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: 일시/증상 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-calendar-event" style="color:#fd7e14"></i> 예약 일시 / 증상</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">예약일 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="w_date" onchange="onDateChange()">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">결제 방법</label>
                    <select class="form-select" id="w_payment_type">
                        <option value="unpaid">미결제</option>
                        <option value="card">카드</option>
                        <option value="transfer">계좌이체</option>
                        <option value="cash">현금</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">예약 시간 <span class="text-danger">*</span></label>
                    <div class="time-slots" id="timeSlots">
                        <div class="text-muted py-3">예약일을 선택하면 가용 시간이 표시됩니다</div>
                    </div>
                    <div id="slotMessage" class="small text-muted mt-2"></div>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">증상/메모</label>
                    <textarea class="form-control" id="w_problem" rows="3" placeholder="환자 증상이나 예약 메모를 입력하세요..."></textarea>
                </div>
            </div>
        </div>

        <!-- Step 4: 검토 완료 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-check-circle" style="color:#fd7e14"></i> 검토 및 등록</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 등록 버튼을 눌러주세요. 항목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary"></div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-appt" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 예약 등록
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
                <a href="admin_appointment_wizard.php" class="btn btn-appt mt-3">새 예약 등록</a>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">대시보드</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '환자 선택', icon: 'bi-person-check' },
    { num: 2, label: '의사/진료과', icon: 'bi-hospital' },
    { num: 3, label: '일시/증상', icon: 'bi-calendar-event' },
    { num: 4, label: '검토 완료', icon: 'bi-check-circle' }
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = {
    patient_id: 0,
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    doctor_id: 0,
    doctor_name: '',
    doctor_charge: '',
    department_id: 0,
    department_name: '',
    selected_time: ''
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
        if (!document.getElementById('w_hospital').value) { alert('소속 병원을 선택해주세요.'); return false; }
        if (!wizardData.patient_id) { alert('환자를 선택해주세요.'); return false; }
    }
    if (step === 2) {
        if (!document.getElementById('w_department').value) { alert('진료과를 선택해주세요.'); return false; }
        if (!wizardData.doctor_id) { alert('의사를 선택해주세요.'); return false; }
    }
    if (step === 3) {
        if (!document.getElementById('w_date').value) { alert('예약일을 선택해주세요.'); return false; }
        if (!wizardData.selected_time) { alert('예약 시간을 선택해주세요.'); return false; }
    }
    return true;
}

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
                opt.textContent = h.hospital_name + (h.linked === false ? ' (HMS 연결 필요)' : '');
                opt.disabled = h.linked === false;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('병원 목록 로드 실패:', e); }
}

// ── 병원 선택 변경 ──
async function onHospitalChange() {
    const tenantId = document.getElementById('w_hospital').value;
    if (!tenantId) return;
    // 진료과 로드
    const fd = new FormData();
    fd.append('action', 'load_departments');
    fd.append('tenant_id', tenantId);
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        const sel = document.getElementById('w_department');
        while (sel.options.length > 1) sel.remove(1);
        if (d.success) {
            d.data.forEach(dept => {
                const opt = document.createElement('option');
                opt.value = dept.id;
                opt.textContent = dept.title;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('진료과 로드 실패:', e); }
}

// ── 환자 검색 (debounce) ──
let searchTimer;
function searchPatients() {
    clearTimeout(searchTimer);
    const q = document.getElementById('w_patient_search').value.trim();
    const results = document.getElementById('patientResults');
    if (q.length < 1) {
        results.classList.remove('show');
        return;
    }
    searchTimer = setTimeout(async () => {
        const tenantId = document.getElementById('w_hospital').value || 0;
        const fd = new FormData();
        fd.append('action', 'search_patients');
        fd.append('query', q);
        fd.append('tenant_id', tenantId);
        try {
            const r = await fetch('', {method:'POST', body:fd});
            const d = await r.json();
            if (d.success && d.data.length > 0) {
                results.innerHTML = d.data.map(p => `
                    <div class="patient-item" onclick="selectPatient(${p.patient_id}, '${esc(p.first_name + ' ' + p.last_name)}', '${esc(p.email)}', '${esc(p.phone)}')">
                        <div class="fw-bold">${esc(p.first_name)} ${esc(p.last_name)}</div>
                        <div class="small text-muted">${esc(p.email)} &bull; ${esc(p.phone)}</div>
                    </div>
                `).join('');
                results.classList.add('show');
            } else {
                results.innerHTML = '<div class="patient-item text-muted">검색 결과 없음</div>';
                results.classList.add('show');
            }
        } catch(e) {
            console.error('환자 검색 실패:', e);
        }
    }, 300);
}

// ── 환자 선택 ──
function selectPatient(id, name, email, phone) {
    wizardData.patient_id = id;
    wizardData.patient_name = name;
    wizardData.patient_email = email;
    wizardData.patient_phone = phone;

    document.getElementById('selPatientName').textContent = name;
    document.getElementById('selPatientEmail').textContent = email;
    document.getElementById('selPatientPhone').textContent = phone;
    document.getElementById('selectedPatientCard').classList.add('show');
    document.getElementById('patientResults').classList.remove('show');
    document.getElementById('w_patient_search').value = name;
}

function clearPatient() {
    wizardData.patient_id = 0;
    wizardData.patient_name = '';
    wizardData.patient_email = '';
    wizardData.patient_phone = '';
    document.getElementById('selectedPatientCard').classList.remove('show');
    document.getElementById('w_patient_search').value = '';
}

// ── 진료과 변경 -> 의사 로드 ──
async function onDepartmentChange() {
    const deptId = document.getElementById('w_department').value;
    const tenantId = document.getElementById('w_hospital').value;
    const grid = document.getElementById('doctorGrid');

    if (!deptId) {
        grid.innerHTML = '<div class="col-12 text-center text-muted py-4"><i class="bi bi-arrow-up" style="font-size:24px"></i><br>진료과를 먼저 선택해주세요</div>';
        return;
    }

    // 진료과명 저장
    const deptSel = document.getElementById('w_department');
    wizardData.department_id = parseInt(deptId);
    wizardData.department_name = deptSel.options[deptSel.selectedIndex]?.text || '';

    grid.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-warning"></div></div>';

    const fd = new FormData();
    fd.append('action', 'load_doctors');
    fd.append('department_id', deptId);
    fd.append('tenant_id', tenantId);

    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success && d.data.length > 0) {
            grid.innerHTML = d.data.map(doc => `
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="doctor-select-card" data-doctor-id="${doc.doctor_id}" data-charge="${doc.appointment_charge || 0}"
                         data-name="${esc(doc.first_name + ' ' + doc.last_name)}" data-specialist="${esc(doc.specialist || '')}"
                         onclick="selectDoctor(this)">
                        <i class="bi bi-person-circle doctor-icon d-block mb-2"></i>
                        <div class="fw-bold">${esc(doc.first_name)} ${esc(doc.last_name)}</div>
                        <div class="small text-muted">${esc(doc.specialist || '일반의')}</div>
                        <div class="small fw-bold mt-1" style="color:#fd7e14">${Number(doc.appointment_charge || 0).toLocaleString()}원</div>
                    </div>
                </div>
            `).join('');
        } else {
            grid.innerHTML = '<div class="col-12 text-center text-muted py-4">해당 진료과에 등록된 의사가 없습니다</div>';
        }
    } catch(e) {
        grid.innerHTML = '<div class="col-12 text-center text-danger py-4">의사 목록 로드 실패</div>';
    }
}

// ── 의사 선택 ──
function selectDoctor(el) {
    document.querySelectorAll('.doctor-select-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    wizardData.doctor_id = parseInt(el.dataset.doctorId);
    wizardData.doctor_name = el.dataset.name;
    wizardData.doctor_charge = el.dataset.charge;
    document.getElementById('w_charge').value = Number(el.dataset.charge).toLocaleString();

    // 날짜가 이미 선택되어 있으면 슬롯 다시 로드
    const dateVal = document.getElementById('w_date').value;
    if (dateVal) loadTimeSlots(wizardData.doctor_id, dateVal);
}

// ── 예약일 변경 ──
function onDateChange() {
    const dateVal = document.getElementById('w_date').value;
    if (!dateVal || !wizardData.doctor_id) {
        document.getElementById('timeSlots').innerHTML = '<div class="text-muted py-3">의사를 선택한 후 예약일을 선택해주세요</div>';
        return;
    }
    loadTimeSlots(wizardData.doctor_id, dateVal);
}

// ── 시간 슬롯 로드 ──
async function loadTimeSlots(doctorId, date) {
    const container = document.getElementById('timeSlots');
    const msg = document.getElementById('slotMessage');
    container.innerHTML = '<div class="spinner-border spinner-border-sm text-warning"></div>';
    msg.textContent = '';
    wizardData.selected_time = '';

    const tenantId = document.getElementById('w_hospital').value;
    const fd = new FormData();
    fd.append('action', 'load_schedule_slots');
    fd.append('doctor_id', doctorId);
    fd.append('date', date);
    fd.append('tenant_id', tenantId);

    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success && d.data.length > 0) {
            container.innerHTML = d.data.map(slot => `
                <div class="time-slot ${slot.available ? '' : 'disabled'}"
                     onclick="${slot.available ? 'selectTimeSlot(this)' : ''}"
                     data-time="${slot.time}">
                    ${slot.time}
                </div>
            `).join('');
            const avail = d.data.filter(s => s.available).length;
            msg.textContent = `${d.per_patient || 30}분 간격, ${d.data.length}개 슬롯 중 ${avail}개 예약 가능`;
        } else {
            container.innerHTML = '<div class="text-muted py-3">' + (d.message || '가용 시간이 없습니다') + '</div>';
        }
    } catch(e) {
        container.innerHTML = '<div class="text-danger py-3">시간 슬롯 로드 실패</div>';
    }
}

// ── 시간 선택 ──
function selectTimeSlot(el) {
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    wizardData.selected_time = el.dataset.time;
}

// ── 날짜 최소값 설정 (오늘 이후) ──
function setMinDate() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    document.getElementById('w_date').min = `${yyyy}-${mm}-${dd}`;
}

// ── 검토 화면 ──
function renderReview() {
    const hospitalSel = document.getElementById('w_hospital');
    const hospitalName = hospitalSel.options[hospitalSel.selectedIndex]?.text || '-';
    const dateVal = document.getElementById('w_date').value;
    const payType = document.getElementById('w_payment_type');
    const payLabel = payType.options[payType.selectedIndex]?.text || '-';

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-person-check"></i> 1. 환자</h6>
            <div class="row small">
                <div class="col-6"><strong>병원:</strong> ${esc(hospitalName)}</div>
                <div class="col-6"><strong>환자명:</strong> ${esc(wizardData.patient_name)}</div>
                <div class="col-6"><strong>이메일:</strong> ${esc(wizardData.patient_email)}</div>
                <div class="col-6"><strong>전화:</strong> ${esc(wizardData.patient_phone)}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-hospital"></i> 2. 의사/진료과</h6>
            <div class="row small">
                <div class="col-6"><strong>진료과:</strong> ${esc(wizardData.department_name)}</div>
                <div class="col-6"><strong>의사:</strong> ${esc(wizardData.doctor_name)}</div>
                <div class="col-6"><strong>진료비:</strong> ${Number(wizardData.doctor_charge || 0).toLocaleString()}원</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-calendar-event"></i> 3. 일시/증상</h6>
            <div class="row small">
                <div class="col-4"><strong>예약일:</strong> ${esc(dateVal)}</div>
                <div class="col-4"><strong>시간:</strong> <span class="badge bg-warning text-dark">${esc(wizardData.selected_time)}</span></div>
                <div class="col-4"><strong>결제:</strong> ${esc(payLabel)}</div>
                <div class="col-12 mt-1"><strong>증상/메모:</strong> ${esc(document.getElementById('w_problem').value) || '-'}</div>
            </div>
        </div>
    `;
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('예약 등록을 진행하시겠습니까?')) return;

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const dateVal = document.getElementById('w_date').value;
    const timeVal = wizardData.selected_time;
    const opdDate = dateVal + ' ' + timeVal + ':00'; // yyyy-mm-dd HH:ii:ss

    const data = {
        patient_id: wizardData.patient_id,
        doctor_id: wizardData.doctor_id,
        department_id: wizardData.department_id,
        tenant_id: document.getElementById('w_hospital').value,
        opd_date: opdDate,
        problem: document.getElementById('w_problem').value.trim(),
        payment_type: document.getElementById('w_payment_type').value
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
            document.getElementById('resultTitle').textContent = '예약 등록 완료!';
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
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 예약 등록';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);
loadHospitals();
setMinDate();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

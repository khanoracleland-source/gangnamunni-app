<?php
/**
 * ========================================
 * 서울미녀 스타일 예약 페이지
 * ========================================
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// HMS API 클라이언트 (실시간 예약 전송용)
require_once __DIR__ . '/includes/infyhms_api.php';

// 사진 업로드 공용 헬퍼
require_once __DIR__ . '/includes/photo_upload_helper.php';

// 관리자 로그인 체크
$is_admin = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

// [2026-06-15] 예약 로그인 필수 (관리 대시보드 booking_require_login 설정으로 ON/OFF, 기본 ON)
//   booking_phone_auth=1 이면 비로그인 전화번호 예약도 허용 (다른 채널 대비)
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
if (function_exists('getSettingValue')) {
    $_require_login = getSettingValue($connect, 'booking_require_login', '1') === '1';
    $_phone_auth    = getSettingValue($connect, 'booking_phone_auth', '0') === '1';
    $_is_member = function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in();
    if ($_require_login && !$_phone_auth && !$_is_member && !$is_admin) {
        header('Location: /hairwang/bbs/login.php?url=' . urlencode('/hairwang/gangnamunni/booking.php?id=' . (isset($_GET['id']) ? intval($_GET['id']) : 0)));
        exit;
    }
}

// 병원 ID 가져오기
$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hospital_id <= 0) {
    header('Location: index.php');
    exit;
}

// 병원 정보 조회
$query = "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die(term("business") . " 정보를 찾을 수 없습니다.");
}

$hospital = mysqli_fetch_assoc($result);

// 병원 이미지 로드 (최대 10개)
$images = [];
$images_query = "SELECT image_url FROM gangnamunni_hospital_images WHERE hospital_id = {$hospital_id} ORDER BY display_order ASC LIMIT 10";
$images_result = mysqli_query($connect, $images_query);
if ($images_result) {
    while ($img = mysqli_fetch_assoc($images_result)) {
        if (!empty($img['image_url'])) {
            $images[] = $img['image_url'];
        }
    }
}

// 이미지가 없으면 기본 이미지 URL 사용
if (empty($images) && !empty($hospital['image_url'])) {
    $images[] = $hospital['image_url'];
}

// 예약 처리
$booking_submitted = false;
$booking_error = '';
$hms_appointment_id = null;  // HMS 예약 번호 (실시간 연동 시)
$hms_api_success = false;    // HMS API 전송 성공 여부

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $treatment = isset($_POST['treatment']) ? trim($_POST['treatment']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $time = isset($_POST['time']) ? trim($_POST['time']) : '';
    $memo = isset($_POST['memo']) ? trim($_POST['memo']) : '';

    if (empty($name) || empty($phone) || empty($treatment) || empty($date) || empty($time)) {
        $booking_error = '필수 항목을 모두 입력해주세요.';
    } else {
        // 1단계: 서울미녀 로컬 DB에 저장 (항상 실행)
        $booking_mb_id = '';
        if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
            $bm = get_gangnamunni_member();
            $booking_mb_id = $bm['mb_id'] ?? '';
        }
        $insert_query = "INSERT INTO gangnamunni_bookings
                        (hospital_id, hospital_name, name, phone, mb_id, treatment, booking_date, booking_time, memo, created_at)
                        VALUES (
                            {$hospital_id},
                            '" . mysqli_real_escape_string($connect, $hospital['name']) . "',
                            '" . mysqli_real_escape_string($connect, $name) . "',
                            '" . mysqli_real_escape_string($connect, $phone) . "',
                            '" . mysqli_real_escape_string($connect, $booking_mb_id) . "',
                            '" . mysqli_real_escape_string($connect, $treatment) . "',
                            '" . mysqli_real_escape_string($connect, $date) . "',
                            '" . mysqli_real_escape_string($connect, $time) . "',
                            '" . mysqli_real_escape_string($connect, $memo) . "',
                            NOW()
                        )";

        if (mysqli_query($connect, $insert_query)) {
            $booking_id = mysqli_insert_id($connect);
            $booking_submitted = true;

            // 관리자 FCM + 인앱 알림
            notifyAdmins($connect, 'booking',
                '새 예약 접수',
                htmlspecialchars($hospital['name']) . ' — ' . htmlspecialchars($name) . ' (' . $date . ' ' . $time . ')',
                'admin_bookings.php',
                ['booking_id' => $booking_id, 'hospital_id' => $hospital_id]
            );

            // 2단계: HMS API로 실시간 예약 전송 (HMS 연동 병원만)
            // hms_hospital_id가 있는 병원 = HMS에 등록된 병원
            if (!empty($hospital['hms_hospital_id'])) {
                try {
                    $hms_api = get_infyhms_api();
                    // 사용자가 선택한 의사/진료과 ID (있으면 포함)
                    $selected_doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
                    $selected_department = isset($_POST['department']) ? intval($_POST['department']) : null;

                    $appointment_data = [
                        'patient_name'  => $name,
                        'patient_phone' => $phone,
                        'appointment_datetime' => $date . ' ' . $time . ':00',
                        'problem'       => "[서울미녀 예약] 시술: {$treatment}"
                                         . ($memo ? "\n메모: {$memo}" : ''),
                        'tenant_id'     => $hospital['hms_hospital_id'],
                    ];

                    // 의사/진료과 선택이 있으면 포함 (없으면 서버가 자동 배정)
                    if ($selected_doctor_id) {
                        $appointment_data['doctor_id'] = $selected_doctor_id;
                    }
                    if ($selected_department) {
                        // gangnamunni_departments의 hms_department_id 조회
                        $dept_result = mysqli_query($connect,
                            "SELECT hms_department_id FROM gangnamunni_departments WHERE id = {$selected_department}");
                        $dept_row = $dept_result ? mysqli_fetch_assoc($dept_result) : null;
                        if ($dept_row) {
                            $appointment_data['department_id'] = (int)$dept_row['hms_department_id'];
                        }
                    }

                    $hms_result = $hms_api->createAppointment($appointment_data);

                    // HMS API 성공 시 예약 번호 저장
                    if (!empty($hms_result['success']) && !empty($hms_result['data']['id'])) {
                        $hms_appointment_id = (int) $hms_result['data']['id'];
                        $hms_api_success = true;

                        // gangnamunni_bookings에 HMS 예약 ID 저장 (컬럼 있으면)
                        @mysqli_query($connect,
                            "UPDATE gangnamunni_bookings
                             SET hms_appointment_id = {$hms_appointment_id}
                             WHERE id = {$booking_id}");
                    } else {
                        // API 반환은 있지만 실패한 경우 로그
                        error_log("[서울미녀] HMS 예약 API 실패: "
                            . json_encode($hms_result, JSON_UNESCAPED_UNICODE));
                    }
                } catch (Exception $e) {
                    // HMS API 장애 시에도 로컬 저장은 완료 (폴백)
                    // 크론잡 배치 동기화가 나중에 처리함
                    error_log("[서울미녀] HMS API 예약 전송 실패 (booking #{$booking_id}): "
                        . $e->getMessage());
                }
            }

            // 3단계: 사진 업로드 처리
            handle_photo_upload($connect, [
                'type'      => 'bookings',
                'record_id' => $booking_id,
                'table'     => 'gangnamunni_bookings',
                'max_files' => 3,
            ]);
        } else {
            $booking_error = term('booking', '예약') . ' 중 오류가 발생했습니다. 다시 시도해주세요.';
        }
    }
}

$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true);

// [1차 검수 #14 - 2026-05-28] 시술 선택 병원 데이터 미연동 픽스
// 신 테이블 gangnamunni_treatment_master 우선 조회, 없으면 구 테이블 fallback
if (empty($treatment_tags) || !is_array($treatment_tags)) {
    $treatment_tags = [];
    // 1순위: 신 테이블 gangnamunni_treatment_master
    $master_check = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_master'");
    if ($master_check && mysqli_num_rows($master_check) > 0) {
        $master_stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_treatment_master WHERE hospital_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY name ASC");
        if ($master_stmt) {
            mysqli_stmt_bind_param($master_stmt, 'i', $hospital_id);
            mysqli_stmt_execute($master_stmt);
            $mr = mysqli_stmt_get_result($master_stmt);
            if ($mr) {
                while ($t = mysqli_fetch_assoc($mr)) {
                    $treatment_tags[] = $t['name'];
                }
            }
            mysqli_stmt_close($master_stmt);
        }
    }
    // 2순위 (fallback): 구 테이블
    if (empty($treatment_tags)) {
        $treat_stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_treatments WHERE hospital_id = ? AND is_active = 1 ORDER BY is_popular DESC, sort_order ASC, name ASC");
        if ($treat_stmt) {
            mysqli_stmt_bind_param($treat_stmt, 'i', $hospital_id);
            mysqli_stmt_execute($treat_stmt);
            $treat_result = mysqli_stmt_get_result($treat_stmt);
            while ($t = mysqli_fetch_assoc($treat_result)) {
                $treatment_tags[] = $t['name'];
            }
            mysqli_stmt_close($treat_stmt);
        }
    }
}

// ========================================
// 공유 레이아웃 변수 설정
// ========================================
$page_title = htmlspecialchars($hospital['name']) . ' ' . term('appointment');
$show_search = false;

$page_css = '
    /* 페이지 헤더 (글로벌 네비 아래) */
    .page-header {
        padding: 12px 20px;
    }

    .page-header-content {
        max-width: 640px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .back-btn {
        font-size: 24px;
        cursor: pointer;
        text-decoration: none;
        color: #000;
    }

    .page-title {
        font-size: 18px;
        font-weight: bold;
        flex: 1;
    }

    .admin-gear {
        font-size: 22px;
        cursor: pointer;
        text-decoration: none;
        color: var(--bs-secondary-color, #666);
        transition: color 0.3s;
    }

    .admin-gear:hover {
        color: #AE1932;
    }

    /* 병원 이미지 슬라이더 */
    .hospital-images-section {
        background: var(--bs-body-bg, #fff);
        margin-bottom: 0;
        position: relative;
    }

    .hospital-images-wrapper {
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .hospital-images-wrapper::-webkit-scrollbar {
        display: none;
    }

    .hospital-images-scroll {
        display: flex;
        gap: 0;
    }

    .hospital-image-item {
        min-width: 100%;
        width: 100%;
        flex-shrink: 0;
        scroll-snap-align: start;
        height: 280px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .image-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0,0,0,0.5);
        color: white;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 18px;
        z-index: 10;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .hospital-images-section:hover .image-nav-btn {
        opacity: 1;
    }

    .image-nav-btn.left {
        left: 12px;
    }

    .image-nav-btn.right {
        right: 12px;
    }

    .image-counter {
        position: absolute;
        bottom: 12px;
        right: 12px;
        background: rgba(0,0,0,0.6);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        z-index: 10;
    }

    /* 병원 정보 카드 */
    .hospital-card {
        background: var(--bs-body-bg, #fff);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .hospital-name {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 8px;
    }

    .hospital-address {
        font-size: 14px;
        color: var(--bs-secondary-color, #666);
    }

    /* 성공 메시지 */
    .success-message {
        background: #E8F5E9;
        border-left: 4px solid #4CAF50;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .success-message h2 {
        color: #2E7D32;
        font-size: 20px;
        margin-bottom: 12px;
    }

    .success-message p {
        color: #555;
        line-height: 1.6;
        margin-bottom: 8px;
    }

    .success-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .success-buttons button {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-primary-outline {
        background: var(--bs-body-bg, #fff);
        color: #AE1932;
        border: 1px solid #AE1932;
    }

    .btn-primary-outline:hover {
        background: #FFF3E0;
    }

    /* 에러 메시지 */
    .error-message {
        background: #FFEBEE;
        border-left: 4px solid #F44336;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #C62828;
    }

    /* 폼 */
    .booking-form {
        background: var(--bs-body-bg, #fff);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 8px;
        color: var(--bs-body-color, #333);
    }

    .form-label.required::after {
        content: " *";
        color: #AE1932;
    }

    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        font-size: 14px;
        background: #FFFFFF;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #AE1932;
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-help {
        font-size: 12px;
        color: #999999;
        margin-top: 6px;
    }

    /* 시술 선택 그리드 */
    .treatment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 12px;
    }

    .treatment-option {
        padding: 12px;
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
    }

    .treatment-option:hover {
        border-color: #AE1932;
        background: #FFF3E0;
    }

    .treatment-option.selected {
        border-color: #AE1932;
        background: #AE1932;
        color: white;
    }

    /* 제출 버튼 */
    .submit-section {
        margin-top: 30px;
    }

    .btn-submit {
        width: 100%;
        padding: 16px;
        background: #AE1932;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-submit:hover {
        background: #8A1428;
    }

    .btn-submit:disabled {
        background: #CCCCCC;
        cursor: not-allowed;
    }

    /* 안내 사항 */
    .notice-box {
        background: #FFF3E0;
        border-radius: 8px;
        padding: 16px;
        margin-top: 20px;
    }

    .notice-title {
        font-size: 14px;
        font-weight: bold;
        color: #E65100;
        margin-bottom: 12px;
    }

    .notice-list {
        font-size: 13px;
        color: var(--bs-secondary-color, #666);
        line-height: 1.8;
        padding-left: 20px;
    }

    /* 지도 섹션 */
    .map-section {
        background: var(--bs-body-bg, #fff);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .map-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 12px;
        color: var(--bs-body-color, #333);
    }

    .map-container {
        width: 100%;
        height: 300px;
        border-radius: 8px;
        overflow: hidden;
        background: var(--bs-tertiary-bg, #f5f5f5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
    }

    /* 병원 정보 섹션 */
    .hospital-info-section {
        background: var(--bs-body-bg, #fff);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        margin-bottom: 40px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .info-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 16px;
        color: var(--bs-body-color, #333);
    }

    .info-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #f5f5f5;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 14px;
        color: var(--bs-secondary-color, #666);
        min-width: 80px;
        flex-shrink: 0;
    }

    .info-value {
        font-size: 14px;
        color: var(--bs-body-color, #333);
        flex: 1;
    }

    .info-value a {
        color: #AE1932;
        text-decoration: none;
    }

    @media (max-width: 768px) {
        .treatment-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
';

// ========================================
// 페이지별 JS (script 태그 없이)
// ========================================
$page_js = '
    // 진료과/의사 AJAX 로딩 (HMS 연동 병원용)
    function loadDepartments() {
        var deptSelect = document.getElementById("departmentSelect");
        if (!deptSelect) return;
        var hospitalId = ' . $hospital_id . ';

        fetch("api_departments.php?action=departments&hospital_id=" + hospitalId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    data.data.forEach(function(dept) {
                        var opt = document.createElement("option");
                        opt.value = dept.id;
                        opt.textContent = dept.title + (dept.doctor_count ? " (" + dept.doctor_count + "명)" : "");
                        deptSelect.appendChild(opt);
                    });
                }
            })
            .catch(function(e) { console.log("진료과 로딩 실패:", e); });
    }

    function loadDoctors(departmentId) {
        var docSelect = document.getElementById("doctorSelect");
        if (!docSelect) return;
        docSelect.innerHTML = "<option value=\"\">자동 배정</option>";

        var url = "api_departments.php?action=doctors&hospital_id=' . $hospital_id . '";
        if (departmentId) url += "&department_id=" + departmentId;

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    data.data.forEach(function(doc) {
                        var opt = document.createElement("option");
                        opt.value = doc.hms_doctor_id || doc.id;
                        opt.textContent = doc.name + (doc.specialty ? " (" + doc.specialty + ")" : "");
                        docSelect.appendChild(opt);
                    });
                }
            })
            .catch(function(e) { console.log("의사 로딩 실패:", e); });
    }

    // 의사 스케줄 기반 시간대 로딩 (HMS 연동 병원용)
    function loadSchedule() {
        var docSelect = document.getElementById("doctorSelect");
        var dateInput = document.getElementById("bookingDate");
        var timeSelect = document.getElementById("timeSelect");
        var schedMsg = document.getElementById("scheduleMessage");

        // HMS 연동 병원이 아니거나, 의사 미선택이면 기본 시간대 유지
        if (!docSelect || !docSelect.value || !dateInput || !dateInput.value) {
            if (schedMsg) schedMsg.style.display = "none";
            return;
        }

        var doctorId = docSelect.value;
        var date = dateInput.value;

        fetch("api_schedule.php?doctor_id=" + doctorId + "&date=" + date)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data) return;

                var schedData = data.data;

                // 휴일인 경우
                if (schedData.is_holiday) {
                    if (schedMsg) {
                        schedMsg.textContent = "해당 날짜는 휴일입니다. 다른 날짜를 선택해주세요.";
                        schedMsg.style.display = "block";
                    }
                    timeSelect.innerHTML = "<option value=\"\">휴일 - 예약 불가</option>";
                    return;
                }

                if (schedMsg) schedMsg.style.display = "none";

                // 시간대 옵션 업데이트
                var slots = schedData.available_slots || [];
                if (slots.length > 0) {
                    timeSelect.innerHTML = "<option value=\"\">시간을 선택하세요</option>";
                    slots.forEach(function(slot) {
                        var opt = document.createElement("option");
                        opt.value = slot.time;
                        if (slot.is_booked) {
                            opt.textContent = slot.label + " (예약됨)";
                            opt.disabled = true;
                            opt.style.color = "#999";
                        } else if (slot.is_past) {
                            opt.textContent = slot.label + " (지남)";
                            opt.disabled = true;
                            opt.style.color = "#999";
                        } else {
                            opt.textContent = slot.label;
                        }
                        timeSelect.appendChild(opt);
                    });
                }
            })
            .catch(function(e) {
                console.log("스케줄 로딩 실패:", e);
            });
    }

    // 의사 선택 변경 시 스케줄 재로딩
    var doctorSelectEl = document.getElementById("doctorSelect");
    if (doctorSelectEl) {
        doctorSelectEl.addEventListener("change", loadSchedule);
    }

    // [1차 검수 #13 - 2026-05-28] 의사 5명 개별 선택 불가 픽스
    // 페이지 로드 시 진료과 + 의사 목록 동시 로딩
    loadDepartments();
    loadDoctors(""); // 진료과 미선택 시에도 전체 의사 목록 로딩

    // 진료과 select 변경 시 의사 자동 재로딩
    var deptSelEl = document.getElementById("departmentSelect");
    if (deptSelEl) {
        deptSelEl.addEventListener("change", function() {
            loadDoctors(this.value);
        });
    }

    // 시술 선택
    function selectTreatment(element, treatment) {
        // 기존 선택 해제
        document.querySelectorAll(".treatment-option").forEach(function(opt) {
            opt.classList.remove("selected");
        });

        // 새로운 선택
        element.classList.add("selected");
        document.getElementById("treatmentInput").value = treatment;
    }

    // 폼 유효성 검사
    var bookingForm = document.getElementById("bookingForm");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function(e) {
            var treatment = document.getElementById("treatmentInput").value;
            if (!treatment) {
                e.preventDefault();
                alert("' . term('service', '시술') . '을 선택해주세요.");
                return false;
            }
        });
    }

    // 병원 이미지 슬라이더
    var currentImageIndex = 0;
    var imageWrapper = document.getElementById("hospitalImagesWrapper");

    if (imageWrapper) {
        var imageItems = imageWrapper.querySelectorAll(".hospital-image-item");
        var totalImages = imageItems.length;

        function updateImageCounter() {
            var counter = document.getElementById("currentImageIndex");
            if (counter) {
                counter.textContent = currentImageIndex + 1;
            }
        }

        function scrollImageContainer(direction) {
            if (!imageWrapper || totalImages === 0) return;

            currentImageIndex += direction;

            if (currentImageIndex < 0) {
                currentImageIndex = totalImages - 1;
            } else if (currentImageIndex >= totalImages) {
                currentImageIndex = 0;
            }

            var scrollAmount = imageWrapper.offsetWidth * currentImageIndex;

            var startPosition = imageWrapper.scrollLeft;
            var distance = scrollAmount - startPosition;
            var duration = 500;
            var startTime = null;

            function animation(currentTime) {
                if (startTime === null) startTime = currentTime;
                var timeElapsed = currentTime - startTime;
                var progress = Math.min(timeElapsed / duration, 1);

                var ease = progress < 0.5
                    ? 2 * progress * progress
                    : 1 - Math.pow(-2 * progress + 2, 2) / 2;

                imageWrapper.scrollLeft = startPosition + (distance * ease);

                if (timeElapsed < duration) {
                    requestAnimationFrame(animation);
                }
            }

            requestAnimationFrame(animation);
            updateImageCounter();
        }

        // 스크롤 이벤트로 현재 인덱스 업데이트
        imageWrapper.addEventListener("scroll", function() {
            var scrollPosition = imageWrapper.scrollLeft;
            var itemWidth = imageWrapper.offsetWidth;
            var newIndex = Math.round(scrollPosition / itemWidth);

            if (newIndex !== currentImageIndex) {
                currentImageIndex = newIndex;
                updateImageCounter();
            }
        });

        // 전역 함수로 노출
        window.scrollImageContainer = scrollImageContainer;
    }
';

// ========================================
// 공유 헤더 포함 (HTML 시작, Tailwind, global_nav, main-content 열기)
// ========================================
include __DIR__ . '/includes/header.php';
?>

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <div class="page-header-content">
            <a href="detail.php?id=<?php echo $hospital_id; ?>" class="back-btn">←</a>
            <div class="page-title"><?php echo term('booking', '예약'); ?>하기</div>
            <?php if ($is_admin): ?>
            <a href="admin_ads.php" class="admin-gear" title="관리자 페이지">⚙️</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 병원 이미지 슬라이더 -->
    <?php if (!empty($images)): ?>
    <div class="hospital-images-section">
        <button class="image-nav-btn left" onclick="scrollImageContainer(-1)">‹</button>
        <button class="image-nav-btn right" onclick="scrollImageContainer(1)">›</button>
        <div class="image-counter">
            <span id="currentImageIndex">1</span> / <?php echo count($images); ?>
        </div>
        <div class="hospital-images-wrapper" id="hospitalImagesWrapper">
            <div class="hospital-images-scroll">
                <?php foreach ($images as $image): ?>
                <div class="hospital-image-item" style="background-image: url('<?php echo htmlspecialchars($image); ?>');"></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 병원 정보 -->
    <div class="hospital-card">
        <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
        <div class="hospital-address"><?php echo htmlspecialchars($hospital['address'] ?? ''); ?></div>
    </div>

    <?php if ($booking_submitted): ?>
    <!-- 예약 성공 메시지 -->
    <div class="success-message">
        <h2>&#x2705; <?php echo term('booking', '예약'); ?> 신청이 완료되었습니다</h2>
        <?php if ($hms_appointment_id): ?>
        <p style="background:#C8E6C9; padding:8px 12px; border-radius:6px; font-size:15px; margin-bottom:12px;">
            <strong><?php echo term('booking', '예약'); ?> 확인번호:</strong> #<?php echo $hms_appointment_id; ?>
        </p>
        <?php endif; ?>
        <p><strong><?php echo term('booking', '예약'); ?>자:</strong> <?php echo htmlspecialchars($_POST['name']); ?></p>
        <p><strong>연락처:</strong> <?php echo htmlspecialchars($_POST['phone']); ?></p>
        <p><strong><?php echo term('service', '시술'); ?>:</strong> <?php echo htmlspecialchars($_POST['treatment']); ?></p>
        <p><strong><?php echo term('booking', '예약'); ?>일시:</strong> <?php echo htmlspecialchars($_POST['date'] . ' ' . $_POST['time']); ?></p>
        <p style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #C8E6C9;">
            <?php if ($hms_api_success): ?>
            <?php echo term('business', '병원'); ?> <?php echo term('booking', '예약'); ?> 시스템에 자동 접수되었습니다. <?php echo term('business', '병원'); ?>에서 확인 후 연락드립니다.<br>
            <?php else: ?>
            <?php echo term('business', '병원'); ?>에서 <?php echo term('booking', '예약'); ?> 확인 후 연락드릴 예정입니다.<br>
            <?php endif; ?>
            <?php echo term('booking', '예약'); ?> 변경/취소는 내 <?php echo term('booking', '예약'); ?>에서 직접 관리할 수 있습니다.
        </p>
        <div class="success-buttons">
            <button class="btn-primary-outline" onclick="location.href='my_bookings.php'">내 <?php echo term('booking', '예약'); ?> 보기</button>
            <button class="btn-primary-outline" onclick="location.href='detail.php?id=<?php echo $hospital_id; ?>'"><?php echo term('business', '병원'); ?> 상세보기</button>
            <button class="btn-submit" onclick="location.href='index.php'">홈으로</button>
        </div>
    </div>

    <?php else: ?>

    <?php if ($booking_error): ?>
    <!-- 에러 메시지 -->
    <div class="error-message">
        <?php echo htmlspecialchars($booking_error); ?>
    </div>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('booking'); ?>
    <?php endif; ?>

    <!-- 예약 폼 -->
    <form method="POST" class="booking-form" id="bookingForm" enctype="multipart/form-data">
        <!-- 이름 -->
        <div class="form-group">
            <label class="form-label required">이름</label>
            <?php
            $bk_member = get_gangnamunni_member();
            $bk_name = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : (!empty($bk_member['mb_name']) ? $bk_member['mb_name'] : '');
            $bk_phone = !empty($_SESSION['user_phone']) ? $_SESSION['user_phone'] : (!empty($bk_member['mb_hp']) ? $bk_member['mb_hp'] : '');
            ?>
            <input type="text" name="name" class="form-input" placeholder="이름을 입력하세요"
                   value="<?php echo htmlspecialchars($bk_name); ?>" required>
        </div>

        <!-- 연락처 -->
        <div class="form-group">
            <label class="form-label required">연락처</label>
            <input type="tel" name="phone" class="form-input" placeholder="010-0000-0000"
                   value="<?php echo htmlspecialchars($bk_phone); ?>" required>
            <div class="form-help"><?php echo term('booking', '예약'); ?> 확인을 위해 연락처가 필요합니다.</div>
        </div>

        <!-- 진료과 선택 (HMS 연동 병원만 표시) -->
        <?php if (!empty($hospital['hms_hospital_id'])): ?>
        <div class="form-group">
            <label class="form-label"><?php echo term('appointment', '진료'); ?>과</label>
            <select name="department" id="departmentSelect" class="form-select" onchange="loadDoctors(this.value)">
                <option value="">전체 (자동 배정)</option>
            </select>
            <div class="form-help"><?php echo term('appointment', '진료'); ?>과를 선택하면 해당 과 <?php echo term('doctor', '의사'); ?>가 표시됩니다.</div>
        </div>

        <!-- 의사 선택 (HMS 연동 병원만 표시) -->
        <div class="form-group">
            <label class="form-label">담당 <?php echo term('doctor', '의사'); ?></label>
            <select name="doctor_id" id="doctorSelect" class="form-select">
                <option value="">자동 배정</option>
            </select>
        </div>
        <?php endif; ?>

        <!-- 시술 선택 -->
        <div class="form-group">
            <label class="form-label required"><?php echo term('service', '시술'); ?> 선택</label>
            <input type="hidden" name="treatment" id="treatmentInput" required>
            <div class="treatment-grid">
                <?php if (!empty($treatment_tags) && is_array($treatment_tags)): ?>
                    <?php foreach (array_slice($treatment_tags, 0, 9) as $tag): ?>
                        <?php
                        $tag_text = is_array($tag) ? (isset($tag[0]) ? $tag[0] : '') : $tag;
                        if (!empty($tag_text) && is_string($tag_text)):
                        ?>
                        <div class="treatment-option" onclick="selectTreatment(this, '<?php echo htmlspecialchars($tag_text); ?>')">
                            <?php echo htmlspecialchars($tag_text); ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="treatment-option" onclick="selectTreatment(this, '코성형')">코성형</div>
                    <div class="treatment-option" onclick="selectTreatment(this, '눈성형')">눈성형</div>
                    <div class="treatment-option" onclick="selectTreatment(this, '지방흡입')">지방흡입</div>
                    <div class="treatment-option" onclick="selectTreatment(this, '가슴성형')">가슴성형</div>
                    <div class="treatment-option" onclick="selectTreatment(this, '리프팅')">리프팅</div>
                    <div class="treatment-option" onclick="selectTreatment(this, '보톡스')">보톡스</div>
                <?php endif; ?>
            </div>
            <div class="form-help">원하시는 <?php echo term('service', '시술'); ?>을 선택해주세요.</div>
        </div>

        <!-- 예약 날짜 -->
        <div class="form-group">
            <label class="form-label required"><?php echo term('booking', '예약'); ?> 날짜</label>
            <input type="date" name="date" id="bookingDate" class="form-input"
                   min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"
                   onchange="loadSchedule()" required>
        </div>

        <!-- 예약 시간 (HMS 연동 병원: 스케줄 기반 동적 로딩) -->
        <div class="form-group">
            <label class="form-label required"><?php echo term('booking', '예약'); ?> 시간</label>
            <div id="scheduleMessage" style="display:none; padding:8px 12px; background:#FFF3E0; border-radius:6px; margin-bottom:8px; font-size:13px; color:#E65100;"></div>
            <!-- [1차 검수 #15 - 2026-05-28] 예약 시간 선택 30분 단위로 세분화 -->
            <select name="time" id="timeSelect" class="form-select" required>
                <option value="">시간을 선택하세요</option>
                <option value="09:00">오전 09:00</option>
                <option value="09:30">오전 09:30</option>
                <option value="10:00">오전 10:00</option>
                <option value="10:30">오전 10:30</option>
                <option value="11:00">오전 11:00</option>
                <option value="11:30">오전 11:30</option>
                <option value="12:00">오후 12:00</option>
                <option value="12:30">오후 12:30</option>
                <option value="13:00">오후 01:00</option>
                <option value="13:30">오후 01:30</option>
                <option value="14:00">오후 02:00</option>
                <option value="14:30">오후 02:30</option>
                <option value="15:00">오후 03:00</option>
                <option value="15:30">오후 03:30</option>
                <option value="16:00">오후 04:00</option>
                <option value="16:30">오후 04:30</option>
                <option value="17:00">오후 05:00</option>
                <option value="17:30">오후 05:30</option>
                <option value="18:00">오후 06:00</option>
            </select>
        </div>

        <!-- 상담 내용 -->
        <div class="form-group">
            <label class="form-label"><?php echo term('consultation', '상담'); ?> 내용</label>
            <textarea name="memo" class="form-textarea" placeholder="궁금하신 사항이나 요청사항을 입력해주세요 (선택사항)"></textarea>
        </div>

        <!-- 사진 첨부 -->
        <?php
        $photo_config = [
            'prefix'     => 'booking',
            'max_photos' => 3,
            'label'      => '📷 사진 첨부 (선택, 최대 3장)',
            'consent_text' => '상담 참고용 사진을 첨부합니다. 제공된 사진은 상담 목적으로만 사용되며, 상담 완료 후 30일 이내에 삭제됩니다.',
            'show_medical_notice' => true,
        ];
        include __DIR__ . '/includes/photo_upload_ui.php';
        ?>

        <!-- 제출 버튼 -->
        <div class="submit-section">
            <button type="submit" class="btn-submit"><?php echo term('booking', '예약'); ?> 신청하기</button>
        </div>
    </form>


    <!-- 안내 사항 -->
    <div class="notice-box">
        <div class="notice-title"><?php echo term('booking', '예약'); ?> 안내사항</div>
        <ul class="notice-list">
            <li><?php echo term('booking', '예약'); ?>은 <?php echo term('business', '병원'); ?> 확인 후 확정됩니다.</li>
            <li><?php echo term('booking', '예약'); ?> 확정 시 연락처로 연락드립니다.</li>
            <li><?php echo term('booking', '예약'); ?> 변경/취소는 <?php echo term('business', '병원'); ?>으로 직접 연락 부탁드립니다.</li>
            <li><?php echo term('consultation', '상담'); ?> 후 <?php echo term('service', '시술'); ?>이 확정되는 점 참고 부탁드립니다.</li>
        </ul>
    </div>

    <?php endif; ?>

    <!-- 카카오 지도 -->
    <?php
    $map_config = [
        'container_id' => 'bookingMap',
        'latitude'     => $hospital['latitude'] ?? 0,
        'longitude'    => $hospital['longitude'] ?? 0,
        'address'      => $hospital['address'] ?? '',
        'name'         => $hospital['name'] ?? '',
        'height'       => '220px',
    ];
    include __DIR__ . '/includes/kakao_map.php';
    ?>

    <!-- 병원 정보 섹션 -->
    <div class="hospital-info-section">
        <div class="info-title"><?php echo term('business', '병원'); ?> 정보</div>

        <?php if (!empty($hospital['name'])): ?>
        <div class="info-row">
            <div class="info-label"><?php echo term('business', '병원'); ?>명</div>
            <div class="info-value"><?php echo htmlspecialchars($hospital['name']); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($hospital['address'])): ?>
        <div class="info-row">
            <div class="info-label">주소</div>
            <div class="info-value"><?php echo htmlspecialchars($hospital['address']); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($hospital['phone'])): ?>
        <div class="info-row">
            <div class="info-label">전화번호</div>
            <div class="info-value">
                <a href="tel:<?php echo htmlspecialchars($hospital['phone']); ?>">
                    <?php echo htmlspecialchars($hospital['phone']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($hospital['hours'])): ?>
        <div class="info-row">
            <div class="info-label"><?php echo term('appointment', '진료'); ?>시간</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($hospital['hours'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($hospital['description'])): ?>
        <div class="info-row">
            <div class="info-label">소개</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($hospital['description'])); ?></div>
        </div>
        <?php endif; ?>
    </div>

<?php
// ========================================
// 공유 푸터 포함 (main-content 닫기, bottom_navigation, closing tags)
// ========================================
include __DIR__ . '/includes/footer.php';

if (isDevMode($connect)) { echo getDevModeJS_booking(); }

mysqli_close($connect);
?>

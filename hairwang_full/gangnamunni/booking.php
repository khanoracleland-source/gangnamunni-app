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
        header('Location: auth/login.php?url=' . urlencode('/hairwang/gangnamunni/booking.php?id=' . (isset($_GET['id']) ? intval($_GET['id']) : 0)));
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

// CSRF 토큰 (consultation.php 와 동일 패턴) — 폼 hidden 필드 + POST 검증에 사용
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 예약 처리
$booking_submitted = false;
$booking_error = '';
$hms_appointment_id = null;  // HMS 예약 번호 (실시간 연동 시)
$hms_api_success = false;    // HMS API 전송 성공 여부
$is_ajax_booking = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['response_mode'] ?? '') === 'ajax';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // init.php(그누보드)가 $_POST 에 슬래시를 추가하므로 stripslashes 후 사용해야
    // mysqli_real_escape_string 과 이중 이스케이프되지 않는다. (treatments_json 과 동일 처리)
    $name = isset($_POST['name']) ? trim(stripslashes($_POST['name'])) : '';
    $phone = isset($_POST['phone']) ? trim(stripslashes($_POST['phone'])) : '';
    $treatment = isset($_POST['treatment']) ? trim(stripslashes($_POST['treatment'])) : '';
    $date = isset($_POST['date']) ? trim(stripslashes($_POST['date'])) : '';
    $time = isset($_POST['time']) ? trim(stripslashes($_POST['time'])) : '';
    $memo = isset($_POST['memo']) ? trim(stripslashes($_POST['memo'])) : '';
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    // 이름/연락처: 폼에 입력란이 없으므로(예약은 로그인 필수) 로그인 회원 정보에서 자동 사용.
    if (($name === '' || $phone === '') && function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
        $lm = get_gangnamunni_member();
        if ($name === '')  $name  = trim($lm['mb_name'] ?? '');
        if ($phone === '') $phone = trim($lm['mb_hp'] ?? ($_SESSION['user_phone'] ?? ''));
    }

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $booking_error = '잘못된 요청입니다. 페이지를 새로고침 후 다시 시도해주세요.';
    } elseif (empty($name) || empty($phone) || empty($treatment) || empty($date) || empty($time)) {
        $booking_error = '필수 항목을 모두 입력해주세요.';
    } elseif (($booking_ts = strtotime($date . ' ' . $time)) === false || $booking_ts < time()) {
        // 클라이언트 검증(지난 시간 비활성)을 우회한 과거 시각 예약 차단
        $booking_error = '지난 시간은 ' . term('booking', '예약') . '할 수 없습니다. 다른 시간을 선택해주세요.';
    } else {
        // 1단계: 서울미녀 로컬 DB에 저장 (항상 실행)
        $booking_mb_id = '';
        if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
            $bm = get_gangnamunni_member();
            $booking_mb_id = $bm['mb_id'] ?? '';
        }

        // 여러 시술 선택 지원: treatments_json = [{name, price, qty}, ...]
        // init.php(그누보드)가 $_POST 에 슬래시를 추가하므로 stripslashes 후 파싱해야 JSON 이 유효.
        $treatments_json = stripslashes($_POST['treatments_json'] ?? '[]');
        $treatments_arr = json_decode($treatments_json, true);
        if (!is_array($treatments_arr)) $treatments_arr = [];

        // 총 가격 = 각 시술 단가(옵션 MIN 또는 price_min) × 수량 합계 (신 시스템)
        // 주의: price_min/opt_min 은 '만원' 단위로 저장돼 있으나, bookings.price 는 '원' 단위로
        //   표시(again_booking.php 등: number_format($price).'원')되므로 마지막에 ×10000 해서 원으로 저장한다.
        $booking_price = null;
        if (!empty($treatments_arr)) {
            $sum = 0; $found = false;
            $unit_stmt = mysqli_prepare($connect,
                "SELECT ht.price_min AS ht_min,
                        (SELECT MIN(p.price) FROM gangnamunni_hospital_treatment_prices p
                           WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_min
                 FROM gangnamunni_hospital_treatments ht
                 JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
                 WHERE ht.hospital_id = ? AND tm.name = ? AND ht.status = 'active' AND ht.is_active = 1
                 LIMIT 1");
            if ($unit_stmt) {
                foreach ($treatments_arr as $it) {
                    $tn = trim($it['name'] ?? '');
                    $qty = max(1, (int)($it['qty'] ?? 1));
                    if ($tn === '') continue;
                    mysqli_stmt_bind_param($unit_stmt, 'is', $hospital_id, $tn);
                    mysqli_stmt_execute($unit_stmt);
                    // get_result() 는 실패 시 false, 조회 결과 없으면 fetch 가 null 이므로 모두 방어
                    $res = mysqli_stmt_get_result($unit_stmt);
                    $r = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
                    if (!is_array($r)) continue;
                    $unit = (int)(($r['opt_min'] ?? 0) ?: ($r['ht_min'] ?? 0));
                    if ($unit > 0) { $sum += $unit * $qty; $found = true; }
                }
                mysqli_stmt_close($unit_stmt);
            }
            if ($found) $booking_price = $sum * 10000; // 만원 → 원
        }
        // treatment 컬럼(varchar 100)엔 요약(쉼표 이름) 저장 — 초과 시 잘라냄
        $treatment = mb_substr($treatment, 0, 100);

        // [예약 저장 안정화] 재설계(다중 시술/수량)로 추가된 컬럼이 없는 DB(운영 등)에서도
        // INSERT 가 'Unknown column' 으로 실패하지 않도록, 없으면 즉시 추가 (idempotent).
        $_ensure_cols = [
            'treatments_json' => "ADD COLUMN treatments_json TEXT NULL AFTER treatment",
            'quantity'        => "ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER treatments_json",
            'price'           => "ADD COLUMN price INT NULL AFTER memo",
        ];
        foreach ($_ensure_cols as $_col => $_ddl) {
            $_chk = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_bookings LIKE '{$_col}'");
            if ($_chk && mysqli_num_rows($_chk) === 0) {
                @mysqli_query($connect, "ALTER TABLE gangnamunni_bookings {$_ddl}");
            }
        }

        $insert_query = "INSERT INTO gangnamunni_bookings
                        (hospital_id, hospital_name, name, phone, mb_id, treatment, treatments_json, quantity, booking_date, booking_time, memo, price, created_at)
                        VALUES (
                            {$hospital_id},
                            '" . mysqli_real_escape_string($connect, $hospital['name']) . "',
                            '" . mysqli_real_escape_string($connect, $name) . "',
                            '" . mysqli_real_escape_string($connect, $phone) . "',
                            '" . mysqli_real_escape_string($connect, $booking_mb_id) . "',
                            '" . mysqli_real_escape_string($connect, $treatment) . "',
                            '" . mysqli_real_escape_string($connect, json_encode($treatments_arr, JSON_UNESCAPED_UNICODE)) . "',
                            {$quantity},
                            '" . mysqli_real_escape_string($connect, $date) . "',
                            '" . mysqli_real_escape_string($connect, $time) . "',
                            '" . mysqli_real_escape_string($connect, $memo) . "',
                            " . ($booking_price !== null ? $booking_price : 'NULL') . ",
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

            if ($is_ajax_booking) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => true,
                    'booking_id' => (int)$booking_id,
                    'hms_appointment_id' => $hms_appointment_id ? (int)$hms_appointment_id : null,
                    'detail_url' => 'booking_detail.php?booking_id=' . (int)$booking_id . '&new=1',
                    'bookings_url' => 'my_bookings.php',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            // 예약 신청 완료 → 예약 상세(booking_detail.php)로 이동 : 신청 후 화면을 상세 뷰로 통일
            // new=1 : 방금 신청 완료 상태 → booking_detail.php 에서 추가 요소 표시용
            // (출력 전이므로 header 안전. 본인 예약이므로 booking_detail 소유자 검증 통과)
            header('Location: booking_detail.php?booking_id=' . (int)$booking_id . '&new=1');
            exit;
        } else {
            error_log("[서울미녀] 예약 INSERT 실패: " . mysqli_error($connect) . " | query=" . $insert_query);
            $booking_error = term('booking', '예약') . ' 중 오류가 발생했습니다. 다시 시도해주세요.';
        }
    }
}

if ($is_ajax_booking && !$booking_submitted) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $booking_error !== '' ? $booking_error : term('booking', '예약') . ' 요청을 처리하지 못했습니다.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$booking_member = (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in())
    ? get_gangnamunni_member()
    : [];
$booking_member_name = trim((string)($booking_member['mb_name'] ?? ''));
$booking_member_phone = trim((string)($booking_member['mb_hp'] ?? ($_SESSION['user_phone'] ?? '')));

$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true);

// 병원에 실제 배정된 시술 + 가격(신 시스템: gangnamunni_hospital_treatments +
// gangnamunni_hospital_treatment_prices) 로드 → 예약 시술 선택/가격 연동.
// 옵션 가격(샷수/용량)이 있으면 그 MIN~MAX, 없으면 배정 행의 price_min/max 사용.
$booking_treatments = [];
$bt_sql = "SELECT tm.name AS name, ht.price_min, ht.price_max,
             (SELECT MIN(p.price) FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_min,
             (SELECT MAX(p.price) FROM gangnamunni_hospital_treatment_prices p WHERE p.hospital_treatment_id = ht.id AND p.price > 0) AS opt_max
           FROM gangnamunni_hospital_treatments ht
           JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
           WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1
           ORDER BY ht.is_promoted DESC, tm.name ASC";
if ($bt_stmt = mysqli_prepare($connect, $bt_sql)) {
    mysqli_stmt_bind_param($bt_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($bt_stmt);
    $bt_r = mysqli_stmt_get_result($bt_stmt);
    while ($row = mysqli_fetch_assoc($bt_r)) {
        $pmin = (int)(($row['opt_min'] ?? 0) ?: ($row['price_min'] ?? 0));
        $pmax = (int)(($row['opt_max'] ?? 0) ?: ($row['price_max'] ?? 0));
        $booking_treatments[] = ['name' => $row['name'], 'price_min' => $pmin, 'price_max' => $pmax];
    }
    mysqli_stmt_close($bt_stmt);
}

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
        /* common.css 는 sticky 헤더를 global_nav 높이(모바일 48px / PC 56px)만큼
           내려놓는다. 이 페이지는 global_nav 를 숨기므로 오프셋을 0 으로 되돌린다.
           (그대로 두면 헤더가 아래로 떠서 옵션 변경 버튼을 덮는다) */
        top: 0;
    }
    @media (min-width: 768px) {
        .page-header { top: 0; }
    }

    /* 뒤로가기 버튼 — 좌측 고정 (bi-chevron-left) */
    .back-btn {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        text-decoration: none;
        color: #000;
    }

    /* 페이지 제목 — 폭 기준 정중앙 */
    .page-title {
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        width: 100%;
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

    /* 폼 — theme_helper.php 가 주입하는 카드 스타일(border-radius/box-shadow)을 명시적으로 해제 */
    .booking-form {
        padding: 15px;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    .form-group {
        margin-bottom: 24px;
    }

    /* 예약하기 헤더 그룹 — 아래 여백 */
    .form-group.head-group {
        margin-bottom: 32px;
    }

    /* 커스텀 날짜 선택 (월 → 날짜) */
    .month-selector {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        scrollbar-width: none;
        margin: 12px 0 14px;
    }
    .month-selector::-webkit-scrollbar { display: none; }
    .month-item {
        flex-shrink: 0;
        font-size: 16px;
        font-weight: 400;
        color: #aaaaaa;
        white-space: nowrap;
        user-select: none;
    }
    .month-item.active {
        color: #000;
        font-weight: 700;
    }

    /* 날짜 선택 라벨 — 크게 */
    /* 날짜/시간 선택 라벨 — 예약하기(section-head-title)와 동일 크기.
       주의: 아래에 .form-label(577줄)이 뒤에 정의돼 같은 우선순위면 덮어쓴다.
       그래서 반드시 클래스 2개(.form-label.date-select-label)로 우선순위를 높여야 적용됨. */
    .form-label.date-select-label {
        font-size: 17px;
        font-weight: 700;
    }

    .date-selector {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        scrollbar-width: none;
        padding: 12px 0;
        border-top: 1px solid var(--bs-border-color, #eee);
        border-bottom: 1px solid var(--bs-border-color, #eee);
    }
    .date-selector::-webkit-scrollbar { display: none; }
    .date-chip {
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        width: 46px;
        padding: 2px 0;
        cursor: pointer;
        background: none;
        border: none;
    }
    /* 요일 (위) — 선택해도 바뀌지 않음 */
    .date-chip .dc-wd { font-size: 12px; color: #9aa0a6; }
    /* 날짜 (아래) — 선택 시 이것만 배경 빨강 + 글자 흰색 */
    .date-chip .dc-day {
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; font-weight: 700;
        color: var(--bs-body-color, #1f2937);
        transition: background 0.15s, color 0.15s;
    }
    .date-chip:hover .dc-day { background: #f2f2f2; }
    .date-chip.active .dc-day { background: var(--color-primary, #AE1932); color: #fff; }
    /* 오늘 라벨 (날짜 아래) */
    .date-chip .dc-today { font-size: 12px; font-weight: 600; color: #aaaaaa; margin-top: -1px; }

    /* 시간대 그리드 — 빨강 테두리 박스, 한 줄에 4개 */
    .time-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-top: 12px;
    }
    .time-slot {
        text-align: center;
        padding: 11px 4px;
        border: 1px solid #e5e7eb;
        color: #000000;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        background: var(--bs-body-bg, #fff);
        transition: background 0.15s, color 0.15s;
        white-space: nowrap;
    }
    .time-slot.active {
        color: #AE1932;
        border-color: #AE1932;
    }
    /* HMS 스케줄: 예약됨/지난 시간 — 흐리게, 클릭 불가 */
    .time-slot.disabled {
        color: #bbbbbb;
        background: #f5f5f5;
        border-color: #eeeeee;
        cursor: not-allowed;
        font-size: 12px;
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

    /* 예약하기 헤더 + 옵션 변경 */
    .section-head-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--bs-border-color, #eee);
    }
    .section-head-title {
        font-size: 17px;
        font-weight: 700;
        color: var(--bs-body-color, #1f2937);
    }
    .option-change-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #AE1932;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        padding: 8px 14px;
        cursor: pointer;
    }

    /* 선택된 시술 표시 */
    .selected-treatment {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 12px;
    }
    /* 각 선택 시술 (여러 개) — 항목 사이는 여백으로만 구분 (선 없음) */
    .st-item {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .selected-treatment .st-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .st-remove {
        border: none;
        background: none;
        font-size: 18px;
        line-height: 1;
        color: #bbb;
        cursor: pointer;
        padding: 0 2px;
    }
    .st-remove:hover { color: #AE1932; }
    .selected-treatment .st-name { font-weight: 700; color: #1f2937; }
    .selected-treatment .st-price { font-size: 13px; font-weight: 600; color: #1A1A1A; }
    /* 수량 스테퍼 */
    .st-qty-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .qty-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid #E0E0E0;
        overflow: hidden;
    }
    .qty-btn {
        width: 34px; height: 34px;
        border: none;
        background: var(--bs-body-bg, #fff);
        font-size: 18px; line-height: 1;
        color: #AE1932;
        cursor: pointer;
    }
    .qty-btn:hover { background: #faf0f2; }
    .qty-btn--plus { color: #1A1A1A; }
    /* 수량이 1이면 더 줄일 수 없으므로 − 를 비활성 색으로 표시 */
    .qty-btn--minus.is-off { color: #BBBBBB; }
    .qty-btn--minus.is-off:hover { background: var(--bs-body-bg, #fff); }
    .qty-num { min-width: 40px; text-align: center; font-weight: 700; font-size: 15px; }

    /* 시술 선택 바텀시트 */
    .treatment-sheet-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.45);
        /* 하단 고정 네비(.bottom-nav)의 z-index:9999 보다 위여야 시트 하단
           "시술 선택 완료" 버튼이 네비에 가리지 않는다. */
        z-index: 10000;
        align-items: flex-end;
    }
    .treatment-sheet-overlay.open { display: flex; }
    .treatment-sheet {
        width: 100%;
        max-width: 640px;
        margin: 0 auto;
        background: var(--bs-body-bg, #fff);
        border-radius: 16px 16px 0 0;
        max-height: 88%;
        overflow: hidden;
        padding: 10px 20px calc(20px + env(safe-area-inset-bottom, 0px));
        display: flex;
        flex-direction: column;
        animation: sheetUp 0.34s cubic-bezier(.32,.72,0,1);
    }
    @keyframes sheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

    /* 상단 손잡이 + 제목 */
    .sheet-handle {
        width: 52px; height: 4px; border-radius: 2px;
        background: #AE1932;
        margin: 6px auto 18px;
        flex-shrink: 0;
    }
    .sheet-title {
        font-size: 15px; font-weight: 600; letter-spacing: -.02em;
        margin: 0 0 12px;
        flex-shrink: 0;
    }

    .sheet-close { border: none; background: none; font-size: 18px; cursor: pointer; color: #666; }
    /* flex:1 + min-height:0 → 목록이 남은 공간만 차지하고 스크롤되어 하단 완료 버튼이 항상 보임 */
    .sheet-list {
        flex: 1 1 auto; min-height: 0; overflow-y: auto;
        display: flex; flex-direction: column; gap: 10px;
        margin: 0 -4px 18px; padding: 2px 4px;
    }
    /* 옵션 카드 — 다중 선택(토글)이므로 라디오가 아니라 버튼을 쓴다 */
    .sheet-item {
        width: 100%;
        display: block;
        text-align: left;
        padding: 16px;
        border: 1px solid #E4E4E4;
        border-radius: 12px;
        background: none;
        cursor: pointer;
        font: inherit;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .sheet-item:hover { border-color: #BDBDBD; }
    .sheet-item.selected {
        border-color: #AE1932;
        box-shadow: inset 0 0 0 1px #AE1932;
    }
    .sheet-item-name {
        display: block;
        font-size: 15px; font-weight: 600; line-height: 1.45;
        letter-spacing: -.02em;
        margin: 0 0 10px;
    }
    .sheet-item-foot {
        display: flex; align-items: flex-end; justify-content: space-between; gap: 12px;
    }
    .sheet-item-desc {
        font-size: 13px; color: #5A5A5A; line-height: 1.5; margin: 0;
    }
    .sheet-item-price {
        display: flex; align-items: baseline; gap: 7px;
        margin-left: auto;
        white-space: nowrap;
    }
    /* 정가/할인율은 할인 데이터가 있을 때만 출력된다 */
    .price-was { font-size: 12px; color: #9A9A9A; text-decoration: line-through; }
    .price-off { font-size: 14px; font-weight: 700; color: #1A1A1A; }
    .price-now { font-size: 17px; font-weight: 700; color: #AE1932; }
    .price-now .price-unit { font-size: 14px; }

    /* 시트 하단 선택 완료 버튼 */
    .sheet-footer {
        flex-shrink: 0;
        background: var(--bs-body-bg, #fff);
    }
    .sheet-confirm {
        width: 100%;
        padding: 16px;
        border: none;
        border-radius: 10px;
        background: #AE1932;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        letter-spacing: -.02em;
        cursor: pointer;
        transition: background .15s ease, transform .1s ease;
    }
    .sheet-confirm:hover { background: #822327; }
    .sheet-confirm:active { transform: scale(.985); }

    @media (prefers-reduced-motion: reduce) {
        .treatment-sheet { animation-duration: .01ms; }
        .sheet-item, .sheet-confirm { transition-duration: .01ms; }
    }

    /* 제출 버튼 */
    .submit-section {
        margin-top: 30px;
        /* 고정 하단 네비(77px) 에 가리지 않도록 여유 공간 확보 */
        padding-bottom: 45px;
    }

    .btn-submit {
        width: 100%;
        padding: 16px;
        background: #AE1932;
        color: white;
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

    /* 날짜/시간/시술 중 하나라도 미선택 → 비활성 색상.
       클릭은 막지 않는다(기존 submit 검증이 무엇이 빠졌는지 알려주기 위함). */
    .btn-submit.is-incomplete,
    .btn-submit.is-incomplete:hover {
        background: #F2F2F2;
        color: #BBBBBB;
    }

    /* 예약 최종 확인 화면 */
    .booking-confirm-overlay,
    .booking-success-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 12000;
        justify-content: center;
    }
    .booking-confirm-overlay.open { display: flex; background: rgba(0,0,0,.72); }
    .booking-confirm-page {
        width: 100%; max-width: 640px; min-height: 100%; overflow-y: auto;
        background: #fff; padding: 0 20px calc(24px + env(safe-area-inset-bottom, 0px));
    }
    .confirm-page-head {
        height: 64px; display: grid; grid-template-columns: 1fr;
        align-items: center; text-align: center;
    }
    .confirm-page-title { font-size: 19px; font-weight: 600; letter-spacing: -.04em; }
    .booking-state-badge {
        display: inline-flex; align-items: center; gap: 24px; margin: 18px 0 24px;
        padding: 10px 16px; background: #BE1935; color: #fff; font-size: 18px; font-weight: 700;
    }
    .confirm-info-list { border-bottom: 1px solid #ddd; }
    .confirm-info-row {
        min-height: 58px; display: grid; grid-template-columns: 102px minmax(0,1fr);
        align-items: center; border-top: 1px solid #ddd; font-size: 16px;
    }
    .confirm-info-row:first-child { border-top: 0; }
    .confirm-label { color: #aaa; font-weight: 600; }
    .confirm-value { min-width: 0; font-weight: 650; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .confirm-person {
        margin: 18px 0; padding: 17px 20px; background: #f4f4f4;
        display: grid; grid-template-columns: 88px 1fr; row-gap: 8px; font-size: 16px;
    }
    .confirm-person-label { color: #aaa; font-weight: 600; }
    .confirm-person-value { font-weight: 700; }
    .confirm-notice { background: #fff1f3; padding: 24px 24px 20px; margin-bottom: 20px; }
    .confirm-notice-title { color: #BE1935; font-size: 16px; font-weight: 700; margin-bottom: 10px; }
    .confirm-notice ul { margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.85; }
    .confirm-notice em { color: #BE1935; font-style: normal; }
    .confirm-submit {
        width: 100%; height: 54px; border: 0; background: #BE1935; color: #fff;
        font-size: 17px; font-weight: 700; cursor: pointer;
    }
    .confirm-submit:disabled { opacity: .55; cursor: wait; }

    /* 접수 완료 바텀시트 */
    .booking-success-overlay { align-items: flex-end; background: rgba(0,0,0,.58); }
    .booking-success-overlay.open { display: flex; }
    .booking-success-sheet {
        width: 100%; max-width: 640px; max-height: 88vh; overflow-y: auto; background: #fff;
        padding: 12px 28px calc(28px + env(safe-area-inset-bottom, 0px));
        animation: bookingSheetUp .36s cubic-bezier(.32,.72,0,1);
    }
    @keyframes bookingSheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    .success-sheet-handle { width: 48px; height: 5px; border-radius: 3px; background: #BE1935; margin: 0 auto 46px; }
    .success-check {
        width: 54px; height: 54px; border-radius: 50%; margin: 0 auto 22px;
        background: #BE1935; color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: 32px;
    }
    .success-sheet-title { text-align: center; font-size: 24px; font-weight: 800; letter-spacing: -.04em; }
    .success-sheet-desc { text-align: center; font-size: 16px; line-height: 1.7; margin: 14px 0 30px; }
    .success-wait-box { background: #fff1f3; padding: 20px; text-align: center; }
    .success-wait-title { font-size: 17px; font-weight: 700; color: #23405b; }
    .success-wait-time { margin: 4px 0 18px; font-size: 14px; line-height: 1.55; }
    .success-alert-box { background: #fff; padding: 20px 14px; }
    .success-alert-title { color: #BE1935; font-weight: 700; margin-bottom: 9px; }
    .success-alert-copy { font-size: 14px; line-height: 1.75; }
    .success-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 28px; }
    .success-action {
        height: 52px; border: 1px solid #BE1935; background: #fff; color: #BE1935;
        display: flex; align-items: center; justify-content: center; text-decoration: none;
        font-size: 16px; font-weight: 700;
    }
    .success-action.primary { background: #BE1935; color: #fff; }

    @media (max-width: 380px) {
        .booking-confirm-page { padding-left: 16px; padding-right: 16px; }
        .confirm-info-row { grid-template-columns: 86px minmax(0,1fr); font-size: 15px; }
        .booking-success-sheet { padding-left: 20px; padding-right: 20px; }
        .success-sheet-title { font-size: 22px; }
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
    // 커스텀 날짜 선택 (월 → 날짜). hidden #bookingDate 에 YYYY-MM-DD 저장.
    function initDatePicker() {
        var hidden = document.getElementById("bookingDate");
        var monthSel = document.getElementById("monthSelector");
        var dateSel = document.getElementById("dateSelector");
        if (!hidden || !monthSel || !dateSel) return;

        var weekdays = ["일","월","화","수","목","금","토"];
        // 예약 가능 기간: 오늘부터 정확히 1개월 (오늘 7/13 이면 8/12 까지 — 다음 달 같은 날 전까지)
        // [timezone 픽스] 클라이언트 new Date() 대신 서버(KST) 기준 오늘로 목록을 구성해야
        //   hidden #bookingDate 기본값(서버 date(\'Y-m-d\'))과 활성 칩/제출 날짜가 항상 일치한다.
        var today = new Date(' . date('Y') . ', ' . (date('n') - 1) . ', ' . date('j') . '); today.setHours(0,0,0,0);
        var endDate = new Date(today); endDate.setMonth(endDate.getMonth() + 1);
        var dates = [];
        var cur = new Date(today);
        while (cur < endDate) { dates.push(new Date(cur)); cur.setDate(cur.getDate() + 1); }
        var months = [];
        dates.forEach(function(d) { var k = d.getFullYear() + "-" + (d.getMonth() + 1); if (months.indexOf(k) < 0) months.push(k); });
        var selectedMonth = months[0];

        function pad(n) { return n < 10 ? "0" + n : "" + n; }
        function fmt(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }

        // 월 표시(클릭 불가) — 스와이프로 보이는 날짜의 월이 자동 활성화된다.
        function renderMonths() {
            monthSel.innerHTML = "";
            months.forEach(function(k) {
                var m = parseInt(k.split("-")[1], 10);
                var el = document.createElement("span");
                el.className = "month-item" + (k === selectedMonth ? " active" : "");
                el.textContent = m + "월";
                monthSel.appendChild(el);
            });
        }
        // 모든 날짜를 하나의 스크롤 목록으로 렌더 (월 필터 없음)
        function renderDates() {
            dateSel.innerHTML = "";
            dates.forEach(function(d) {
                var k = d.getFullYear() + "-" + (d.getMonth() + 1);
                var val = fmt(d);
                var chip = document.createElement("div");
                chip.className = "date-chip" + (hidden.value === val ? " active" : "");
                chip.setAttribute("data-month", k);
                var wd = document.createElement("span"); wd.className = "dc-wd"; wd.textContent = weekdays[d.getDay()];
                var day = document.createElement("span"); day.className = "dc-day"; day.textContent = d.getDate();
                chip.appendChild(wd);   // 요일 위
                chip.appendChild(day);  // 날짜 아래
                if (val === fmt(today)) { // 오늘 날짜: 아래에 오늘 라벨 추가
                    var todayLbl = document.createElement("span");
                    todayLbl.className = "dc-today";
                    todayLbl.textContent = "오늘";
                    chip.appendChild(todayLbl);
                }
                chip.onclick = function() {
                    hidden.value = val;
                    dateSel.querySelectorAll(".date-chip").forEach(function(c){ c.classList.remove("active"); });
                    chip.classList.add("active");
                    // 날짜가 바뀌면 시간 그리드 재생성(오늘 지난 시간 재평가).
                    if (window.buildTimeGrid) window.buildTimeGrid();
                    if (window.updateSubmitState) window.updateSubmitState();
                };
                dateSel.appendChild(chip);
            });
        }
        // 스와이프(스크롤) 시 왼쪽에 보이는 날짜의 월로 활성 월 자동 변경
        function updateActiveMonthFromScroll() {
            var chips = dateSel.querySelectorAll(".date-chip");
            var sl = dateSel.scrollLeft;
            var current = null;
            for (var i = 0; i < chips.length; i++) {
                if (chips[i].offsetLeft + chips[i].offsetWidth > sl + 4) { current = chips[i].getAttribute("data-month"); break; }
            }
            if (current && current !== selectedMonth) { selectedMonth = current; renderMonths(); }
        }
        dateSel.addEventListener("scroll", updateActiveMonthFromScroll);

        renderMonths();
        renderDates();
    }
    initDatePicker();

    // [오늘 지난 시간 픽스] 서버(KST) 기준 오늘 날짜/현재시각(자정 이후 분) — 지난 시간대 비활성용
    var SERVER_TODAY = "' . date('Y-m-d') . '";
    var SERVER_NOW_MIN = ' . (date('G') * 60 + (int)date('i')) . ';

    // 시간대 그리드 (숨긴 select 옵션 → 빨강 박스 칩, 한 줄 4개). 클릭 시 select 값 동기화.
    function buildTimeGrid() {
        var sel = document.getElementById("timeSelect");
        var grid = document.getElementById("timeGrid");
        if (!sel || !grid) return;
        grid.innerHTML = "";
        // 선택된 날짜가 오늘이면 이미 지난 시간대는 클릭 불가로 만든다.
        var selDate = (document.getElementById("bookingDate") || {}).value || "";
        var isToday = (selDate === SERVER_TODAY);
        for (var i = 0; i < sel.options.length; i++) {
            var value = sel.options[i].value;
            if (!value) continue;
            // HMS 스케줄 로딩 시 예약됨/지난 시간은 disabled 로 온다 → 표시는 하되 클릭 불가
            var disabled = sel.options[i].disabled;
            var label = sel.options[i].textContent || value;
            // 오늘 && 지난 시간대 → 비활성 (정적 그리드/HMS 공통)
            if (!disabled && isToday) {
                var hm = value.split(":");
                var slotMin = parseInt(hm[0], 10) * 60 + parseInt(hm[1], 10);
                if (slotMin <= SERVER_NOW_MIN) { disabled = true; label = value + " (지남)"; }
            }
            (function(v, isDisabled, text) {
                var cell = document.createElement("div");
                cell.className = "time-slot" + (sel.value === v ? " active" : "") + (isDisabled ? " disabled" : "");
                cell.textContent = isDisabled ? text : v;
                if (!isDisabled) {
                    cell.onclick = function() {
                        sel.value = v;
                        grid.querySelectorAll(".time-slot").forEach(function(c) { c.classList.remove("active"); });
                        cell.classList.add("active");
                        if (window.updateSubmitState) window.updateSubmitState();
                    };
                }
                grid.appendChild(cell);
            })(value, disabled, label);
        }
    }
    buildTimeGrid();
    window.buildTimeGrid = buildTimeGrid; // 날짜 변경 시 재생성용(오늘 지난 시간 재평가)

    // 시술 선택 시트(옵션 변경)
    function openTreatmentSheet() {
        var s = document.getElementById("treatmentSheet");
        if (!s) return;
        // 이미 선택된 항목을 시트에 표시 동기화
        s.querySelectorAll(".sheet-item").forEach(function(el) {
            markSheetItem(el, !!findTreatment(el.getAttribute("data-name")));
        });
        s.classList.add("open"); document.body.style.overflow = "hidden";
    }
    function closeTreatmentSheet() {
        var s = document.getElementById("treatmentSheet");
        if (s) { s.classList.remove("open"); document.body.style.overflow = ""; }
    }
    // 시트 카드의 선택 표시(테두리 강조)와 스크린리더 상태를 함께 갱신한다.
    function markSheetItem(el, on) {
        if (!el) return;
        el.classList.toggle("selected", !!on);
        el.setAttribute("aria-pressed", on ? "true" : "false");
    }

    // 여러 시술 선택 지원: 각 항목 {name, price, qty}
    var selectedTreatments = [];

    function findTreatment(name) {
        for (var i = 0; i < selectedTreatments.length; i++) { if (selectedTreatments[i].name === name) return selectedTreatments[i]; }
        return null;
    }
    // 시트 항목 탭 → 목록에 추가/제거 (토글). 시트는 닫지 않아 여러 개 선택 가능.
    function pickTreatment(btn) {
        var name = btn.getAttribute("data-name") || "";
        var price = btn.getAttribute("data-price") || "";
        var idx = -1;
        for (var i = 0; i < selectedTreatments.length; i++) { if (selectedTreatments[i].name === name) { idx = i; break; } }
        if (idx >= 0) { selectedTreatments.splice(idx, 1); markSheetItem(btn, false); }
        else { selectedTreatments.push({ name: name, price: price, qty: 1 }); markSheetItem(btn, true); }
        renderSelectedTreatments();
    }
    function changeItemQty(name, delta) {
        var t = findTreatment(name);
        if (!t) return;
        t.qty = Math.max(1, Math.min(99, t.qty + delta));
        renderSelectedTreatments();
    }
    function removeTreatment(name) {
        for (var i = 0; i < selectedTreatments.length; i++) { if (selectedTreatments[i].name === name) { selectedTreatments.splice(i, 1); break; } }
        document.querySelectorAll("#treatmentSheet .sheet-item").forEach(function(el) {
            if (el.getAttribute("data-name") === name) markSheetItem(el, false);
        });
        renderSelectedTreatments();
    }
    function renderSelectedTreatments() {
        // 폼 필드 동기화
        document.getElementById("treatmentsJson").value = JSON.stringify(selectedTreatments);
        document.getElementById("treatmentInput").value = selectedTreatments.map(function(t) { return t.name; }).join(", ");
        var totalQty = 0; selectedTreatments.forEach(function(t) { totalQty += t.qty; });
        document.getElementById("quantityInput").value = totalQty || 1;

        if (window.updateSubmitState) window.updateSubmitState();

        var disp = document.getElementById("selectedTreatmentDisplay");
        var ph = document.getElementById("treatmentPlaceholder");
        if (!disp) return;
        disp.innerHTML = "";
        if (selectedTreatments.length === 0) {
            disp.style.display = "none";
            if (ph) ph.style.display = "";
            return;
        }
        if (ph) ph.style.display = "none";
        disp.style.display = "flex";
        selectedTreatments.forEach(function(t) {
            var item = document.createElement("div"); item.className = "st-item";
            // 윗줄: 시술명 + 삭제(×)
            var top = document.createElement("div"); top.className = "st-top";
            var nameEl = document.createElement("span"); nameEl.className = "st-name"; nameEl.textContent = t.name;
            top.appendChild(nameEl);
            var rm = document.createElement("button"); rm.type = "button"; rm.className = "st-remove"; rm.textContent = "×";
            rm.onclick = function() { removeTreatment(t.name); };
            top.appendChild(rm);
            item.appendChild(top);
            // 아랫줄: 수량 스테퍼(왼쪽) + 가격(오른쪽 끝)
            var qrow = document.createElement("div"); qrow.className = "st-qty-row";
            var stepper = document.createElement("div"); stepper.className = "qty-stepper";
            var minus = document.createElement("button"); minus.type = "button";
            minus.className = "qty-btn qty-btn--minus" + (t.qty <= 1 ? " is-off" : "");
            minus.textContent = "−"; minus.onclick = function() { changeItemQty(t.name, -1); };
            var num = document.createElement("span"); num.className = "qty-num"; num.textContent = t.qty;
            var plus = document.createElement("button"); plus.type = "button"; plus.className = "qty-btn qty-btn--plus"; plus.textContent = "+"; plus.onclick = function() { changeItemQty(t.name, 1); };
            stepper.appendChild(minus); stepper.appendChild(num); stepper.appendChild(plus);
            qrow.appendChild(stepper);
            if (t.price) { var pEl = document.createElement("span"); pEl.className = "st-price"; pEl.textContent = t.price; qrow.appendChild(pEl); }
            item.appendChild(qrow);
            disp.appendChild(item);
        });
    }

    // 날짜/시간/시술이 모두 선택돼야 제출 버튼이 활성 색상이 된다.
    function updateSubmitState() {
        var btn = document.querySelector(".btn-submit[type=submit]");
        if (!btn) return;
        var dateEl = document.getElementById("bookingDate");
        var timeEl = document.getElementById("timeSelect");
        var treatEl = document.getElementById("treatmentInput");
        var ready = !!(dateEl && dateEl.value)
                 && !!(timeEl && timeEl.value)
                 && !!(treatEl && treatEl.value);
        btn.classList.toggle("is-incomplete", !ready);
    }
    window.updateSubmitState = updateSubmitState;
    updateSubmitState();

    function formatBookingDateTime(dateValue, timeValue) {
        var d = new Date(dateValue + "T00:00:00");
        var weekdays = ["일", "월", "화", "수", "목", "금", "토"];
        var hour = parseInt(timeValue.split(":")[0], 10);
        var minute = timeValue.split(":")[1] || "00";
        var ap = hour < 12 ? "오전" : "오후";
        var displayHour = hour % 12 || 12;
        return (d.getMonth() + 1) + "월 " + d.getDate() + "일 (" + weekdays[d.getDay()] + ") " + ap + " " + displayHour + ":" + minute;
    }

    function openBookingConfirm() {
        var overlay = document.getElementById("bookingConfirmOverlay");
        var dateValue = document.getElementById("bookingDate").value;
        var timeValue = document.getElementById("timeSelect").value;
        var treatment = document.getElementById("treatmentInput").value;
        document.getElementById("confirmDateTime").textContent = formatBookingDateTime(dateValue, timeValue);
        document.getElementById("confirmTreatment").textContent = treatment;

        var target = new Date(dateValue + "T00:00:00");
        var today = new Date(SERVER_TODAY + "T00:00:00");
        var days = Math.max(0, Math.round((target - today) / 86400000));
        document.getElementById("confirmDday").textContent = "D-" + days;
        overlay.classList.add("open");
        overlay.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }
    function closeBookingConfirm() {
        var overlay = document.getElementById("bookingConfirmOverlay");
        overlay.classList.remove("open");
        overlay.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }
    async function submitConfirmedBooking() {
        var form = document.getElementById("bookingForm");
        var button = document.getElementById("confirmBookingButton");
        if (!form || button.disabled) return;
        button.disabled = true;
        button.textContent = "접수 중...";
        try {
            var response = await fetch(form.getAttribute("action") || window.location.href, {
                method: "POST",
                body: new FormData(form),
                credentials: "same-origin",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            var data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "예약 요청을 처리하지 못했습니다.");

            closeBookingConfirm();
            var bookingsLink = document.getElementById("successBookingsLink");
            if (bookingsLink && data.bookings_url) bookingsLink.href = data.bookings_url;
            var successOverlay = document.getElementById("bookingSuccessOverlay");
            successOverlay.classList.add("open");
            successOverlay.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
            lockSuccessScreen();
        } catch (error) {
            alert(error.message || "예약 중 오류가 발생했습니다. 다시 시도해주세요.");
            button.disabled = false;
            button.textContent = "확인했어요";
        }
    }
    window.closeBookingConfirm = closeBookingConfirm;
    window.submitConfirmedBooking = submitConfirmedBooking;

    var successExitAllowed = false;
    var successLockedElements = [];
    function lockSuccessScreen() {
        var overlay = document.getElementById("bookingSuccessOverlay");
        if (!overlay) return;

        // 바텀시트 밖의 모든 포커스 가능한 요소를 잠가 키보드/보조기기에서도
        // 아래 두 링크 외에는 조작할 수 없게 한다.
        successLockedElements = [];
        document.querySelectorAll("a,button,input,select,textarea,[tabindex]").forEach(function(el) {
            if (overlay.contains(el)) return;
            successLockedElements.push({ el: el, tabindex: el.getAttribute("tabindex") });
            el.setAttribute("tabindex", "-1");
        });

        overlay.querySelectorAll("a").forEach(function(link) {
            link.addEventListener("click", function() { successExitAllowed = true; }, { once: true });
        });

        // 모바일 하드웨어 Back/브라우저 Back도 완료 화면을 닫지 못하게 한다.
        history.pushState({ bookingSuccessLocked: true }, "", window.location.href);
        window.addEventListener("popstate", keepBookingSuccessOpen);
        var firstAction = overlay.querySelector(".success-action.primary");
        if (firstAction) firstAction.focus();
    }
    function keepBookingSuccessOpen() {
        if (successExitAllowed) return;
        var overlay = document.getElementById("bookingSuccessOverlay");
        if (overlay && overlay.classList.contains("open")) {
            history.pushState({ bookingSuccessLocked: true }, "", window.location.href);
        }
    }

    // 폼 유효성 검사 후 서버 제출 대신 최종 확인 화면을 먼저 연다.
    var bookingForm = document.getElementById("bookingForm");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function(e) {
            e.preventDefault();
            var treatment = document.getElementById("treatmentInput").value;
            if (!treatment) {
                alert("' . term('service', '시술') . '을 선택해주세요.");
                return false;
            }
            var timeSel = document.getElementById("timeSelect");
            if (timeSel && !timeSel.value) {
                alert("시간을 선택해주세요.");
                return false;
            }
            openBookingConfirm();
            return false;
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
// 공유 헤더 포함 (HTML 시작, Tailwind, main-content 열기)
// ========================================
$hide_global_nav = true;         // 예약 화면에선 글로벌 네비게이션(로고 포함) 숨김
// global_nav 가 사라지면 fixed 인 관리자 인라인 편집 상단바(.ae-topbar, z-index 9999)가
// 페이지 헤더(예약하기 / 옵션 변경)를 덮어 클릭할 수 없게 된다.
// detail_community.php 와 동일하게 두 상단바를 함께 숨긴다.
$hide_admin_inline_edit = true;  // 관리자 인라인 편집 상단바(ae-topbar) 숨김
include __DIR__ . '/includes/header.php';
?>

    <!-- 페이지 헤더 -->
    <div class="page-header">
            <a href="detail.php?id=<?php echo $hospital_id; ?>" class="back-btn" aria-label="뒤로"><i class="bi bi-chevron-left"></i></a>
            <div class="page-title"><?php echo term('booking', '예약'); ?>하기</div>
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

    <?php if ($booking_submitted): ?>
    <!-- 예약 성공 메시지 -->
    <div class="success-message">
        <h2>&#x2705; <?php echo term('booking', '예약'); ?> 신청이 완료되었습니다</h2>
        <?php if ($hms_appointment_id): ?>
        <p style="background:#C8E6C9; padding:8px 12px; border-radius:6px; font-size:15px; margin-bottom:12px;">
            <strong><?php echo term('booking', '예약'); ?> 확인번호:</strong> #<?php echo $hms_appointment_id; ?>
        </p>
        <?php endif; ?>
        <p><strong><?php echo term('booking', '예약'); ?>자:</strong> <?php echo htmlspecialchars($name); ?></p>
        <p><strong>연락처:</strong> <?php echo htmlspecialchars($phone); ?></p>
        <p><strong><?php echo term('service', '시술'); ?>:</strong> <?php echo htmlspecialchars($treatment); ?></p>
        <p><strong><?php echo term('booking', '예약'); ?>일시:</strong> <?php echo htmlspecialchars($date . ' ' . $time); ?></p>
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
    <form method="POST" class="booking-form" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="response_mode" value="ajax">
        <!-- 예약하기 헤더 + 옵션 변경(시술 선택 시트) -->
        <div class="form-group head-group">
            <div class="section-head-row">
                <span class="section-head-title"><?php echo term('booking', '예약'); ?>하기</span>
                <button type="button" class="option-change-btn" onclick="openTreatmentSheet()">
                    옵션 변경
                </button>
            </div>
            <!-- hidden 필드엔 HTML5 required 가 적용되지 않으므로 제거 — 실제 검증은
                 아래 submit 핸들러(JS)와 서버측 empty() 체크가 담당한다. -->
            <input type="hidden" name="treatment" id="treatmentInput">
            <input type="hidden" name="quantity" id="quantityInput" value="1">
            <input type="hidden" name="treatments_json" id="treatmentsJson" value="[]">
            <!-- 선택된 시술 표시 -->
            <div id="selectedTreatmentDisplay" class="selected-treatment" style="display:none;"></div>
            <div id="treatmentPlaceholder" class="form-help">‘옵션 변경’을 눌러 <?php echo term('service', '시술'); ?>을 선택하세요.</div>
        </div>

        <!-- 예약 날짜 (커스텀: 월 선택 → 날짜 선택) -->
        <div class="form-group">
            <label class="form-label date-select-label">날짜 선택</label>
            <input type="hidden" name="date" id="bookingDate" value="<?php echo date('Y-m-d'); ?>">
            <div class="month-selector" id="monthSelector"></div>
            <div class="date-selector" id="dateSelector"></div>
        </div>

        <!-- 예약 시간 -->
        <div class="form-group">
            <label class="form-label date-select-label">시간 선택</label>
            <!-- [1차 검수 #15 - 2026-05-28] 예약 시간 선택 30분 단위로 세분화 -->
            <!-- select 는 폼 제출/HMS 스케줄용으로 숨김 유지, 실제 표시는 아래 time-grid -->
            <select name="time" id="timeSelect" style="display:none;">
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
            <!-- 병원 운영 시간대 (빨강 테두리 박스, 한 줄에 4개) -->
            <div class="time-grid" id="timeGrid"></div>
        </div>

        <!-- 제출 버튼 -->
        <div class="submit-section">
            <button type="submit" class="btn-submit"><?php echo term('booking', '예약'); ?> 신청하기</button>
        </div>
    </form>

    <!-- 1단계: 예약 내용 최종 확인 -->
    <div class="booking-confirm-overlay" id="bookingConfirmOverlay" aria-hidden="true">
        <section class="booking-confirm-page" role="dialog" aria-modal="true" aria-labelledby="bookingConfirmTitle">
            <header class="confirm-page-head">
                <h2 class="confirm-page-title" id="bookingConfirmTitle">이대로 예약을 진행할까요?</h2>
            </header>
            <div class="booking-state-badge"><span>시술예정</span><span id="confirmDday">D-0</span></div>
            <div class="confirm-info-list">
                <div class="confirm-info-row"><span class="confirm-label">예약번호</span><span class="confirm-value">접수 후 발급</span></div>
                <div class="confirm-info-row"><span class="confirm-label">날짜/시간</span><span class="confirm-value" id="confirmDateTime"></span></div>
                <div class="confirm-info-row"><span class="confirm-label">선택시술</span><span class="confirm-value" id="confirmTreatment"></span></div>
                <div class="confirm-info-row"><span class="confirm-label">병원</span><span class="confirm-value"><?php echo htmlspecialchars($hospital['name']); ?></span></div>
            </div>
            <div class="confirm-person">
                <span class="confirm-person-label">예약자</span><span class="confirm-person-value"><?php echo htmlspecialchars($booking_member_name); ?></span>
                <span class="confirm-person-label">연락처</span><span class="confirm-person-value"><?php echo htmlspecialchars($booking_member_phone); ?></span>
            </div>
            <div class="confirm-notice">
                <div class="confirm-notice-title">고객님 꼭 알아두세요!</div>
                <ul>
                    <li>예약 가능 여부는 병원 상황에 따라 달라질 수 있어요</li>
                    <li>예약이 불가능한 경우, 상담원이 다른 일정으로 안내해드려요</li>
                    <li>예약 현황 / 예약 변경 / 취소는 <em>내 예약 결제내역</em>에서 직접 관리할 수 있어요</li>
                </ul>
            </div>
            <button type="button" class="confirm-submit" id="confirmBookingButton" onclick="submitConfirmedBooking()">확인했어요</button>
        </section>
    </div>

    <!-- 2단계: 예약 요청 접수 완료 (아래에서 올라오는 바텀시트) -->
    <div class="booking-success-overlay" id="bookingSuccessOverlay" aria-hidden="true">
        <section class="booking-success-sheet" role="dialog" aria-modal="true" aria-labelledby="bookingSuccessTitle">
            <div class="success-sheet-handle" aria-hidden="true"></div>
            <div class="success-check"><i class="bi bi-check-lg"></i></div>
            <h2 class="success-sheet-title" id="bookingSuccessTitle">예약 요청이 접수되었어요!</h2>
            <p class="success-sheet-desc">원하시는 일정으로<br>병원에 예약 가능 여부를 확인하고 있어요</p>
            <div class="success-wait-box">
                <div class="success-wait-title">예상 확인 시간</div>
                <div class="success-wait-time">평균 1~3시간 이내<br>(병원 상황에 따라 달라질 수 있어요)</div>
                <div class="success-alert-box">
                    <div class="success-alert-title">알림을 꼭 확인해주세요!</div>
                    <div class="success-alert-copy">예약 진행 상황은 알림(푸시)으로 안내해드려요<br>알림을 허용해두시면 빠르게 확인하실 수 있어요</div>
                </div>
            </div>
            <div class="success-actions">
                <a href="detail.php?id=<?php echo (int)$hospital_id; ?>" class="success-action primary">확인했어요</a>
                <a href="my_bookings.php" class="success-action" id="successBookingsLink">내 예약 현황</a>
            </div>
        </section>
    </div>

    <!-- 시술 선택 시트 (옵션 변경) -->
    <?php
    // 시트에 표시할 시술 목록 구성 (신 시스템 우선 → 태그 → 기본값)
    $sheet_items = [];
    if (!empty($booking_treatments)) {
        foreach ($booking_treatments as $bt) {
            // price_min/max 는 '만원' 단위로 저장돼 있다. 시트에는 축약 없이
            // 원 단위 전체 금액으로 표시한다(예: 90만원 → 900,000원).
            $pl = '';
            if (!empty($bt['price_min']) && $bt['price_min'] > 0) {
                $pl = ($bt['price_max'] > $bt['price_min'])
                    ? number_format($bt['price_min'] * 10000) . '~' . number_format($bt['price_max'] * 10000) . '원'
                    : number_format($bt['price_min'] * 10000) . '원';
            }
            $sheet_items[] = ['name' => $bt['name'], 'price' => $pl];
        }
    } elseif (!empty($treatment_tags) && is_array($treatment_tags)) {
        foreach (array_slice($treatment_tags, 0, 20) as $tag) {
            $tx = is_array($tag) ? ($tag[0] ?? '') : $tag;
            if (!empty($tx) && is_string($tx)) $sheet_items[] = ['name' => $tx, 'price' => ''];
        }
    }
    if (empty($sheet_items)) {
        foreach (['코성형','눈성형','지방흡입','가슴성형','리프팅','보톡스'] as $d) $sheet_items[] = ['name' => $d, 'price' => ''];
    }
    ?>
    <div class="treatment-sheet-overlay" id="treatmentSheet" onclick="if(event.target===this)closeTreatmentSheet()">
        <div class="treatment-sheet" role="dialog" aria-modal="true" aria-labelledby="treatmentSheetTitle">
            <div class="sheet-handle" aria-hidden="true"></div>
            <h2 class="sheet-title" id="treatmentSheetTitle">원하는 옵션으로 변경해주세요</h2>
            <div class="sheet-list" role="group" aria-labelledby="treatmentSheetTitle">
                <?php foreach ($sheet_items as $it): ?>
                <button type="button" class="sheet-item"
                        data-name="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($it['price'], ENT_QUOTES); ?>"
                        aria-pressed="false"
                        onclick="pickTreatment(this)">
                    <span class="sheet-item-name"><?php echo htmlspecialchars($it['name']); ?></span>
                    <span class="sheet-item-foot">
                        <?php if (!empty($it['desc'])): ?>
                        <span class="sheet-item-desc"><?php echo htmlspecialchars($it['desc']); ?></span>
                        <?php endif; ?>
                        <?php if ($it['price']): ?>
                        <span class="sheet-item-price">
                            <?php if (!empty($it['price_was'])): ?>
                            <span class="price-was"><?php echo htmlspecialchars($it['price_was']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($it['price_off'])): ?>
                            <span class="price-off"><?php echo htmlspecialchars($it['price_off']); ?></span>
                            <?php endif; ?>
                            <?php
                            // 숫자와 단위(만원/원)를 나눠 단위만 작게 표시한다.
                            $_pnum = $it['price']; $_punit = '';
                            if (preg_match('/^(.*?)(만원|원)$/u', $it['price'], $_pm)) {
                                $_pnum = $_pm[1]; $_punit = $_pm[2];
                            }
                            ?>
                            <span class="price-now"><?php echo htmlspecialchars($_pnum); ?><?php if ($_punit !== ''): ?><span class="price-unit"><?php echo htmlspecialchars($_punit); ?></span><?php endif; ?></span>
                        </span>
                        <?php endif; ?>
                    </span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="sheet-footer">
                <button type="button" class="sheet-confirm" onclick="closeTreatmentSheet()">선택 완료</button>
            </div>
        </div>
    </div>


    <?php endif; ?>

<?php
// ========================================
// 공유 푸터 포함 (main-content 닫기, bottom_navigation, closing tags)
// ========================================
include __DIR__ . '/includes/footer.php';

if (isDevMode($connect)) { echo getDevModeJS_booking(); }

mysqli_close($connect);
?>

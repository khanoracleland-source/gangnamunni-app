<?php
/**
 * ========================================
 * 서울미녀 스타일 예약 페이지
 * ========================================
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/page_cache.php';
gangnamunni_page_cache(600, 'private');
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/hospital_return_url.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
$booking_from_wishlist = isset($_GET['from']) && $_GET['from'] === 'my_wishlist';
$booking_return_url = hospital_return_url($_GET['return_url'] ?? '');

// 시술 가격은 변환 없이 원 단위 정수 그대로 사용한다.
/**
 * @param int|float|string|null $price DB 에서 읽은 원 단위 가격(문자열 또는 숫자)
 * @return int
 */
function booking_price_won($price): int {
    return intval($price);
}

// HMS API 클라이언트 (실시간 예약 전송용)
require_once __DIR__ . '/includes/infyhms_api.php';

// 관리자 로그인 체크
$is_admin = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

// [2026-06-15] 예약 로그인 필수 (관리 대시보드 booking_require_login 설정으로 ON/OFF, 기본 ON)
//   booking_phone_auth=1 이면 비로그인 전화번호 예약도 허용 (다른 채널 대비)
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
require_once __DIR__ . '/includes/booking_noshow_policy.php';
require_once __DIR__ . '/includes/booking_treatment_conflicts.php';
if (function_exists('getSettingValue')) {
    $_require_login = getSettingValue($connect, 'booking_require_login', '1') === '1';
    $_phone_auth    = getSettingValue($connect, 'booking_phone_auth', '0') === '1';
    $_is_member = function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in();
    if ($_require_login && !$_phone_auth && !$_is_member && !$is_admin) {
        $login_return_url = '/hairwang/app/booking.php?id=' . (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (isset($_GET['treatment_id'])) $login_return_url .= '&treatment_id=' . max(0, (int)$_GET['treatment_id']);
        if (isset($_GET['option_id'])) $login_return_url .= '&option_id=' . max(0, (int)$_GET['option_id']);
        if (isset($_GET['treatment'])) $login_return_url .= '&treatment=' . urlencode(trim((string)$_GET['treatment']));
        if ($booking_return_url !== '') $login_return_url .= '&return_url=' . rawurlencode($booking_return_url);
        if (isset($_GET['rebook']) && $_GET['rebook'] === '1') $login_return_url .= '&rebook=1';
        if (isset($_GET['via']) && $_GET['via'] === 'again') $login_return_url .= '&via=again';
        if (isset($_GET['source_booking_id'])) $login_return_url .= '&source_booking_id=' . max(0, (int)$_GET['source_booking_id']);
        if ($booking_from_wishlist) $login_return_url .= '&from=my_wishlist';
        header('Location: auth/login.php?url=' . urlencode($login_return_url));
        exit;
    }
}

// 병원 ID 가져오기
$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_rebooking = isset($_GET['rebook']) && $_GET['rebook'] === '1';
$source_booking_id = max(0, (int)($_GET['source_booking_id'] ?? 0));
// [05-42] 예약 변경 모드: booking_change.php 가 $change_booking 을 채운 뒤 이 파일을 include 한다.
if (!isset($change_booking) || !is_array($change_booking)) { $change_booking = []; }
$is_changing = !empty($change_booking['id']);
if ($is_changing) { $is_rebooking = true; }

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

// 병원 점심시간. no_lunch_break=true 이면 점심시간 제한을 적용하지 않는다.
$booking_business_hours = json_decode($hospital['business_hours'] ?? '{}', true);
if (!is_array($booking_business_hours)) $booking_business_hours = [];
$booking_special_closed_dates = array_values(array_filter(
    is_array($booking_business_hours['special_closed_dates'] ?? null) ? $booking_business_hours['special_closed_dates'] : [],
    static function($date) { return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date); }
));

// 특별 근무일: 정기 휴무 요일(예: 토/일)이라도 관리자가 지정한 특정 날짜는 지정된 시간대에 예약을 허용한다.
$booking_special_open_dates = [];
foreach ((is_array($booking_business_hours['special_open_dates'] ?? null) ? $booking_business_hours['special_open_dates'] : []) as $booking_open_date => $booking_open_range) {
    if (!is_string($booking_open_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_open_date)) continue;
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*[~\-]\s*(\d{1,2}):(\d{2})$/u', trim((string)$booking_open_range), $booking_open_match)) continue;
    $booking_open_min = (int)$booking_open_match[1] * 60 + (int)$booking_open_match[2];
    $booking_close_min = (int)$booking_open_match[3] * 60 + (int)$booking_open_match[4];
    if ($booking_open_min < $booking_close_min) {
        $booking_special_open_dates[$booking_open_date] = ['open' => $booking_open_min, 'close' => $booking_close_min];
    }
}

// 관리자에서 저장한 요일별 운영시간을 예약 검증/시간 선택에서 공통 사용한다.
// 크롤링 데이터의 officeHours(영문 요일) 형식도 기존 병원 호환을 위해 지원한다.
$booking_day_keys = [0 => '일', 1 => '월', 2 => '화', 3 => '수', 4 => '목', 5 => '금', 6 => '토'];
$booking_office_day_keys = [0 => 'SUN', 1 => 'MON', 2 => 'TUE', 3 => 'WED', 4 => 'THU', 5 => 'FRI', 6 => 'SAT'];
$booking_weekly_hours = [];
$booking_has_weekly_hours = false;
foreach ($booking_day_keys as $booking_day_number => $booking_day_key) {
    $booking_day_value = $booking_business_hours[$booking_day_key] ?? null;
    if ($booking_day_value === null && isset($booking_business_hours['officeHours']) && is_array($booking_business_hours['officeHours'])) {
        $booking_office_key = $booking_office_day_keys[$booking_day_number];
        $booking_day_value = $booking_business_hours['officeHours'][$booking_office_key]
            ?? $booking_business_hours['officeHours'][strtolower($booking_office_key)]
            ?? null;
    }
    if ($booking_day_value !== null) $booking_has_weekly_hours = true;
    $booking_day_value = trim((string)($booking_day_value ?? ''));
    // 격주 진료: '09:00~18:00#biweekly:even|odd' 형식 — 매월 1일 기준 해당 요일의 몇 번째
    // 발생인지(0번째부터 시작)를 짝/홀수로 판정한다. 매월 1일에 새로 0부터 시작한다(연도 전체를
    // 관통하는 ISO 주차가 아니라 월 단위로 리셋됨).
    $booking_day_biweekly = null;
    if (preg_match('/^(.*)#biweekly:(even|odd)$/', $booking_day_value, $booking_biweekly_match)) {
        $booking_day_value = trim($booking_biweekly_match[1]);
        $booking_day_biweekly = $booking_biweekly_match[2];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})\s*[~\-]\s*(\d{1,2}):(\d{2})$/u', $booking_day_value, $booking_hours_match)) {
        $booking_open_min = (int)$booking_hours_match[1] * 60 + (int)$booking_hours_match[2];
        $booking_close_min = (int)$booking_hours_match[3] * 60 + (int)$booking_hours_match[4];
        $booking_weekly_hours[$booking_day_number] = ($booking_open_min < $booking_close_min)
            ? ['open' => $booking_open_min, 'close' => $booking_close_min, 'biweekly' => $booking_day_biweekly]
            : null;
    } else {
        $booking_weekly_hours[$booking_day_number] = null;
    }
}
$booking_no_lunch = !empty($booking_business_hours['no_lunch_break']);
$booking_lunch_start = '';
$booking_lunch_end = '';
if (!$booking_no_lunch) {
    $booking_lunch_value = trim((string)($booking_business_hours['점심'] ?? $booking_business_hours['점심시간'] ?? ''));
    if (preg_match('/^(\d{1,2}:\d{2})\s*[~\-]\s*(\d{1,2}:\d{2})$/u', $booking_lunch_value, $booking_lunch_match)) {
        $booking_lunch_start = $booking_lunch_match[1];
        $booking_lunch_end = $booking_lunch_match[2];
    } else {
        $booking_lunch_start = trim((string)($booking_business_hours['lunch_start'] ?? $booking_business_hours['점심시작'] ?? ''));
        $booking_lunch_end = trim((string)($booking_business_hours['lunch_end'] ?? $booking_business_hours['점심종료'] ?? ''));
    }
}
$booking_lunch_start_min = preg_match('/^(\d{1,2}):(\d{2})$/', $booking_lunch_start, $booking_ls_match)
    ? ((int)$booking_ls_match[1] * 60 + (int)$booking_ls_match[2]) : null;
$booking_lunch_end_min = preg_match('/^(\d{1,2}):(\d{2})$/', $booking_lunch_end, $booking_le_match)
    ? ((int)$booking_le_match[1] * 60 + (int)$booking_le_match[2]) : null;

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

$booking_option_name_column = false;
$booking_option_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
if ($booking_option_col && mysqli_num_rows($booking_option_col) > 0) $booking_option_name_column = true;
$booking_body_part_column = false;
$booking_body_part_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'body_part'");
if ($booking_body_part_col && mysqli_num_rows($booking_body_part_col) > 0) $booking_body_part_column = true;

// [05-12] 시술별 최소 연령(만 나이). NULL/0 이면 제한 없음.
// 컬럼은 sql/hospital_treatment_min_age_20261021.sql 로 추가한다(요청 중 DDL 실행 안 함).
// 컬럼이 없으면 연령 제한을 적용할 수 없으므로 로그를 남기고 기능만 건너뛴다.
$booking_age_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatments LIKE 'min_age'");
$booking_has_min_age = $booking_age_col && mysqli_num_rows($booking_age_col) > 0;
if (!$booking_has_min_age) {
    error_log('[booking] gangnamunni_hospital_treatments.min_age 컬럼이 없어 연령 제한이 적용되지 않습니다. sql/hospital_treatment_min_age_20261021.sql 을 실행하세요.');
}

// [05-15/05-16] 시술별 재시술 간격(일)과 필수 여부는 시술 마스터(admin_treatments.php)에서 관리한다. 컬럼은 sql/treatment_master_revisit_20261021.sql 로 추가한다.
$booking_revisit_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_treatment_master LIKE 'revisit_days'");
$booking_has_revisit = $booking_revisit_col && mysqli_num_rows($booking_revisit_col) > 0;
if (!$booking_has_revisit) {
    error_log('[booking] gangnamunni_treatment_master.revisit_days 컬럼이 없어 재시술 간격 확인이 적용되지 않습니다. sql/treatment_master_revisit_20261021.sql 을 실행하세요.');
}

// 재시술 간격 표기: 30의 배수면 "N개월", 아니면 "N일".
/**
 * @param int|string $days 재시술 간격(일)
 * @return string
 */
function booking_interval_label($days): string {
    $days = (int)$days;
    return ($days > 0 && $days % 30 === 0) ? ($days / 30) . '개월' : $days . '일';
}

// [05-15/05-16] 선택한 시술의 재시술 간격 규칙과 회원의 마지막 완료 시술일을 비교한다.
// 반환: ['strict' => 예약 불가 정보|null, 'warn' => 권장 시기 아님 정보|null]
/**
 * @param \mysqli $connect
 * @param string  $mb_id
 * @param int     $hospital_id
 * @param string[] $names 선택한 시술명 목록
 * @return array{strict: array|null, warn: array|null}
 */
function booking_revisit_status($connect, $mb_id, $hospital_id, array $names): array {
    $out = ['strict' => null, 'warn' => null];
    if ($mb_id === '' || empty($names)) return $out;
    $in = implode(',', array_fill(0, count($names), '?'));
    $stmt = mysqli_prepare($connect,
        "SELECT tm.name AS name, tm.revisit_days, tm.revisit_strict
         FROM gangnamunni_treatment_master tm
         WHERE tm.revisit_days IS NOT NULL AND tm.revisit_days > 0 AND tm.name IN ({$in})");
    if (!$stmt) return $out;
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($names)), ...$names);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rules = [];
    while ($res && ($r = $res->fetch_assoc())) $rules[$r['name']] = ['days' => (int)$r['revisit_days'], 'strict' => (int)$r['revisit_strict'] === 1];
    mysqli_stmt_close($stmt);
    if (empty($rules)) return $out;

    // 회원의 완료된 예약에서 시술별 마지막 시술일(어느 병원이든)
    $stmt2 = mysqli_prepare($connect,
        "SELECT booking_date, treatment, treatments_json FROM gangnamunni_bookings
         WHERE mb_id = ? AND status = 'completed' AND booking_date <= CURDATE()
         ORDER BY booking_date DESC LIMIT 300");
    if (!$stmt2) return $out;
    mysqli_stmt_bind_param($stmt2, 's', $mb_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $last = [];
    while ($res2 && ($row = $res2->fetch_assoc())) {
        $row_names = [];
        $items = json_decode((string)($row['treatments_json'] ?? ''), true);
        if (is_array($items)) foreach ($items as $it) { if (is_array($it) && !empty($it['name'])) $row_names[] = trim((string)$it['name']); }
        foreach ($rules as $n => $_) {
            if (isset($last[$n])) continue;
            $hit = in_array($n, $row_names, true) || (empty($row_names) && $n !== '' && mb_strpos((string)$row['treatment'], $n) !== false);
            if ($hit) $last[$n] = $row['booking_date'];
        }
    }
    mysqli_stmt_close($stmt2);

    $today = new DateTimeImmutable('today');
    $strict_latest = null;
    foreach ($rules as $n => $rule) {
        if (!isset($last[$n])) continue;
        $ld = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$last[$n]);
        if (!$ld) continue;
        $since = (int)$ld->diff($today)->days;
        if ($since >= $rule['days']) continue;
        $avail = $ld->modify('+' . $rule['days'] . ' days');
        $info = ['treatment' => $n, 'interval_label' => booking_interval_label($rule['days']),
                 'days_since' => $since, 'available_from' => $avail->format('n월 j일')];
        if ($rule['strict']) {
            if ($strict_latest === null || $avail > $strict_latest) { $strict_latest = $avail; $out['strict'] = $info; }
        } elseif ($out['warn'] === null) {
            $out['warn'] = $info;
        }
    }
    return $out;
}

// [05-17] 노쇼·잦은 취소 제한은 includes/booking_noshow_policy.php 의 booking_noshow_status() 를 사용한다.

// 로그인 회원의 만 나이. 생년월일이 없거나 형식이 올바르지 않으면 null (판단 불가 → 막지 않음).
/**
 * @param array|null $member 회원 정보(mb_birth 포함)
 * @return int|null
 */
function booking_member_age($member): ?int {
    $digits = preg_replace('/\D+/', '', (string)($member['mb_birth'] ?? ''));
    if (strlen($digits) !== 8 || $digits === '00000000') return null;
    $birth = DateTimeImmutable::createFromFormat('!Ymd', $digits);
    if (!$birth || $birth->format('Ymd') !== $digits) return null;
    $today = new DateTimeImmutable('today');
    if ($birth > $today) return null;
    return $birth->diff($today)->y;
}

// [05-13/05-14] 회원의 진행 중(접수·병원 확인 중·확정, 아직 지나지 않은) 예약 중 선택한 시술과 같은 시술이 담긴 것을 찾는다.
// $same_hospital=true 면 해당 병원 안에서, false 면 다른 병원들에서 찾는다. 없으면 null.
/**
 * @param \mysqli $connect
 * @param string  $mb_id
 * @param int     $hospital_id
 * @param string[] $names 선택한 시술명 목록
 * @param bool    $same_hospital
 * @return array|null
 */
function booking_find_active_same_treatment($connect, $mb_id, $hospital_id, array $names, $same_hospital): ?array {
    if ($mb_id === '' || empty($names)) return null;
    $op = $same_hospital ? '=' : '<>';
    $stmt = mysqli_prepare($connect,
        "SELECT id, hospital_name, treatment, treatments_json, booking_date, booking_time, status
         FROM gangnamunni_bookings
         WHERE mb_id = ? AND hospital_id {$op} ? AND status IN ('pending','hospital_checking','confirmed')
           AND booking_date >= CURDATE()
         ORDER BY booking_date ASC, booking_time ASC LIMIT 50");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'si', $mb_id, $hospital_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $found = null;
    while ($res && ($row = $res->fetch_assoc())) {
        $ts = strtotime(trim($row['booking_date'] . ' ' . $row['booking_time']));
        if ($ts !== false && $ts <= time()) continue; // 이미 지난 예약
        $existing_names = [];
        $items = json_decode((string)($row['treatments_json'] ?? ''), true);
        if (is_array($items)) {
            foreach ($items as $it) { if (is_array($it) && !empty($it['name'])) $existing_names[] = trim((string)$it['name']); }
        }
        $same = !empty(array_intersect($names, $existing_names));
        if (!$same && empty($existing_names)) { // 예전 형식(요약 문자열만 있는 예약)
            foreach ($names as $n) { if ($n !== '' && mb_strpos((string)$row['treatment'], $n) !== false) { $same = true; break; } }
        }
        if (!$same) continue;
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        $h = (int)date('G', $ts);
        $labels = ['pending' => '예약 요청 접수', 'hospital_checking' => '병원 확인 중', 'confirmed' => '예약 확정'];
        $found = [
            'id' => (int)$row['id'],
            'treatment' => mb_substr((string)$row['treatment'], 0, 100),
            'hospital_name' => (string)($row['hospital_name'] ?? ''),
            'schedule' => date('n월 j일', $ts) . ' (' . $weekdays[(int)date('w', $ts)] . ') '
                . ($h < 12 ? '오전' : '오후') . ' ' . ($h % 12 ?: 12) . ':' . date('i', $ts),
            'status' => $labels[$row['status']] ?? '',
        ];
        break;
    }
    mysqli_stmt_close($stmt);
    return $found;
}

// 예약 처리
$booking_submitted = false;
$booking_error = '';
$booking_error_code = '';
$booking_error_meta = [];
$hms_appointment_id = null;  // HMS 예약 번호 (실시간 연동 시)
$hms_api_success = false;    // HMS API 전송 성공 여부
$is_ajax_booking = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['response_mode'] ?? '') === 'ajax';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_string = static function (string $key, string $default = ''): string {
        $value = $_POST[$key] ?? $default;
        return is_string($value) || is_numeric($value) ? trim(stripslashes((string)$value)) : $default;
    };
    // init.php(그누보드)가 $_POST 에 슬래시를 추가하므로 stripslashes 후 사용해야
    // mysqli_real_escape_string 과 이중 이스케이프되지 않는다. (treatments_json 과 동일 처리)
    $name = $post_string('name');
    $phone = $post_string('phone');
    $treatment = $post_string('treatment');
    $date = $post_string('date');
    $time = $post_string('time');
    $memo = $post_string('memo');
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    // Bound public input before any DB/API work. The visible UI only emits these
    // 30-minute slots, so accepting arbitrary times server-side is unnecessary.
    $valid_booking_time_format = preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $time) === 1;
    $booking_time_parts = $valid_booking_time_format ? explode(':', $time) : [];
    $booking_time_min = $valid_booking_time_format ? ((int)$booking_time_parts[0] * 60 + (int)$booking_time_parts[1]) : null;
    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $valid_date = $date_object && $date_object->format('Y-m-d') === $date;
    $max_booking_date = new DateTimeImmutable('+1 year');

    // 이름은 로그인 회원 정보를 사용하고, 연락처는 확인 화면에서 전송된 값을 우선 사용한다.
    if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
        $lm = get_gangnamunni_member();
        // A logged-in booking must always belong to the authenticated member;
        // never trust a forged hidden `name` field.
        $name = trim((string)($lm['mb_name'] ?? ''));
        // 예약 확인 화면에서 사용자가 직접 비운 연락처는 회원 정보로 다시 채우지 않는다.
        if (!array_key_exists('phone', $_POST)) {
            $phone = trim((string)($lm['mb_hp'] ?? ''));
            if ($phone === '') $phone = trim((string)($lm['mb_tel'] ?? ''));
            if ($phone === '') $phone = trim((string)($_SESSION['user_phone'] ?? ''));
        }
    }

    $posted_csrf = $post_string('csrf_token');
    if ($posted_csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $posted_csrf)) {
        $booking_error = '잘못된 요청입니다. 페이지를 새로고침 후 다시 시도해주세요.';
    } elseif (mb_strlen($name) > 100 || mb_strlen($phone) > 30 || mb_strlen($memo) > 2000) {
        $booking_error = '입력한 내용이 허용 길이를 초과했습니다.';
    } elseif ($phone === '') {
        $booking_error = '연락처를 입력해주세요.';
    } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        $booking_error = '연락처 형식을 확인해주세요.';
    } elseif (empty($name) || empty($treatment) || empty($date) || empty($time)) {
        $booking_error = '필수 항목을 모두 입력해주세요.';
    } elseif (!$valid_date || $date_object > $max_booking_date) {
        $booking_error = '예약 날짜를 확인해주세요.';
    } elseif (!$valid_booking_time_format) {
        $booking_error = '선택할 수 없는 예약 시간입니다.';
    } elseif (!$booking_has_weekly_hours && ($booking_time_min < 540 || $booking_time_min > 1080)) {
        // 운영시간 데이터가 전혀 없는 기존 병원은 종전 09:00~18:00 정책을 유지한다.
        $booking_error = '선택할 수 없는 예약 시간입니다.';
    } elseif (in_array($date, $booking_special_closed_dates, true)) {
        $booking_error = '선택한 날짜는 임시 휴무일입니다. 다른 날짜를 선택해주세요.';
    } elseif (isset($booking_special_open_dates[$date])) {
        // 특별 근무일: 정기 휴무 요일 여부와 무관하게 지정된 시간대만 확인한다.
        if ($booking_time_min < $booking_special_open_dates[$date]['open'] || $booking_time_min >= $booking_special_open_dates[$date]['close']) {
            $booking_error = '선택한 시간은 병원 진료시간이 아닙니다. 다른 시간을 선택해주세요.';
        }
    } elseif ($booking_has_weekly_hours && empty($booking_weekly_hours[(int)$date_object->format('w')])) {
        $booking_error = '선택한 날짜는 휴무일입니다. 다른 날짜를 선택해주세요.';
    } elseif ($booking_has_weekly_hours
        && !empty($booking_weekly_hours[(int)$date_object->format('w')]['biweekly'])
        && $booking_weekly_hours[(int)$date_object->format('w')]['biweekly'] !== (intdiv((int)$date_object->format('j') - 1, 7) % 2 === 0 ? 'even' : 'odd')) {
        // 격주 진료: 해당 요일이 이번 달의 짝/홀수 번째 발생일이 아니면 휴무 처리한다.
        $booking_error = '선택한 날짜는 휴무일입니다. 다른 날짜를 선택해주세요.';
    } elseif ($booking_has_weekly_hours
        && (($booking_selected_hours = $booking_weekly_hours[(int)$date_object->format('w')] ?? null) !== null)
        && ($booking_time_min < $booking_selected_hours['open'] || $booking_time_min >= $booking_selected_hours['close'])) {
        $booking_error = '선택한 시간은 병원 진료시간이 아닙니다. 다른 시간을 선택해주세요.';
    } elseif (!$booking_no_lunch && $booking_lunch_start_min !== null && $booking_lunch_end_min !== null
        && $booking_time_min >= $booking_lunch_start_min && $booking_time_min < $booking_lunch_end_min) {
        $booking_error = '점심시간에는 ' . term('booking', '예약') . '할 수 없습니다. 다른 시간을 선택해주세요.';
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

        // 선택 방식: 개별 시술 다중 선택 또는 패키지 1개
        $selection_type = $post_string('selection_type', 'treatments') === 'package' ? 'package' : 'treatments';
        $selected_package_id = max(0, (int)($_POST['package_id'] ?? 0));
        // init.php(그누보드)가 $_POST 에 슬래시를 추가하므로 stripslashes 후 파싱해야 JSON 이 유효.
        $treatments_json = $post_string('treatments_json', '[]');
        $treatments_arr = json_decode($treatments_json, true);
        if (!is_array($treatments_arr)) $treatments_arr = [];
        if (count($treatments_arr) > 20) {
            $booking_error = '시술은 한 번에 최대 20개까지 선택할 수 있습니다.';
            $treatments_arr = [];
        }
        $requested_individual_treatments = $treatments_arr;
        $package_treatments = [];

        // 총 가격은 클라이언트 값을 믿지 않고 DB에서 다시 계산한다.
        // bookings.price 는 항상 원 단위로 저장한다. 과거 만원 가격은 호환 변환한다.
        $booking_price = null;
        if ($selection_type === 'package') {
            $package_stmt = mysqli_prepare($connect,
                "SELECT p.id, p.name, p.package_price
                 FROM gangnamunni_treatment_packages p
                 WHERE p.id = ? AND p.hospital_id = ? AND p.is_active = 1
                   AND p.package_price > 0 AND p.package_price <= p.original_price
                   AND NOT EXISTS (
                       SELECT 1 FROM gangnamunni_treatment_package_items pi_bad
                       LEFT JOIN gangnamunni_hospital_treatments ht_bad
                         ON ht_bad.hospital_id = p.hospital_id AND ht_bad.treatment_id = pi_bad.treatment_id
                        AND ht_bad.status = 'active' AND ht_bad.is_active = 1
                       WHERE pi_bad.package_id = p.id AND ht_bad.id IS NULL
                   )
                 LIMIT 1");
            if ($selected_package_id <= 0 || !$package_stmt) {
                $booking_error = '유효한 패키지를 선택해주세요.';
            } else {
                mysqli_stmt_bind_param($package_stmt, 'ii', $selected_package_id, $hospital_id);
                mysqli_stmt_execute($package_stmt);
                $package_row = mysqli_fetch_assoc(mysqli_stmt_get_result($package_stmt));
                mysqli_stmt_close($package_stmt);
                if (!$package_row) {
                    $booking_error = '선택한 패키지는 현재 예약할 수 없습니다.';
                } else {
                    $package_items_stmt = mysqli_prepare($connect,
                        "SELECT DISTINCT tm.name, pi.sort_order
                         FROM gangnamunni_treatment_package_items pi
                         JOIN gangnamunni_treatment_master tm ON tm.id = pi.treatment_id
                         JOIN gangnamunni_hospital_treatments ht
                           ON ht.hospital_id = ? AND ht.treatment_id = pi.treatment_id
                          AND ht.status = 'active' AND ht.is_active = 1
                         WHERE pi.package_id = ? ORDER BY pi.sort_order ASC");
                    if ($package_items_stmt) {
                        mysqli_stmt_bind_param($package_items_stmt, 'ii', $hospital_id, $selected_package_id);
                        mysqli_stmt_execute($package_items_stmt);
                        $package_items_result = mysqli_stmt_get_result($package_items_stmt);
                        while ($package_item = mysqli_fetch_assoc($package_items_result)) {
                            $package_treatments[] = [
                                'name' => $package_item['name'], 'qty' => 1,
                                'package_id' => $selected_package_id,
                                'package_name' => $package_row['name'],
                            ];
                        }
                        mysqli_stmt_close($package_items_stmt);
                    }
                    if (empty($package_treatments)) {
                        $booking_error = '패키지에 예약 가능한 시술이 없습니다.';
                    } else {
                        $treatment = '[패키지] ' . $package_row['name'];
                        $quantity = 1;
                        $booking_price = (int)$package_row['package_price'] * 10000;
                    }
                }
            }
        }

        // 패키지와 별개로 선택한 시술을 함께 검증·합산한다.
        if ($booking_error === '' && !empty($requested_individual_treatments)) {
            $sum = 0; $found = false; $verified_treatments = []; $seen_treatment_names = [];
            $unit_stmt = mysqli_prepare($connect,
                "SELECT ht.price_min AS ht_min, p.id AS option_id, p.price AS option_price,
                        " . ($booking_option_name_column ? "p.option_name" : "'' AS option_name") . ",
                        p.dosage_value, p.dosage_unit,
                        (SELECT COUNT(*) FROM gangnamunni_hospital_treatment_prices pc
                           WHERE pc.hospital_treatment_id = ht.id AND pc.price > 0) AS option_count
                 FROM gangnamunni_hospital_treatments ht
                 JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
                 LEFT JOIN gangnamunni_hospital_treatment_prices p
                   ON p.hospital_treatment_id = ht.id AND p.price > 0 AND (p.id = ? OR ? = 0)
                 WHERE ht.hospital_id = ? AND tm.name = ? AND ht.status = 'active' AND ht.is_active = 1
                 ORDER BY p.sort_order ASC, p.id ASC LIMIT 1");
            if ($unit_stmt) {
                foreach ($requested_individual_treatments as $it) {
                    $tn = trim($it['name'] ?? '');
                    $qty = 1;
                    $option_id = max(0, (int)($it['option_id'] ?? 0));
                    if ($tn === '') continue;
                    if (isset($seen_treatment_names[$tn])) continue;
                    $seen_treatment_names[$tn] = true;
                    mysqli_stmt_bind_param($unit_stmt, 'iiis', $option_id, $option_id, $hospital_id, $tn);
                    mysqli_stmt_execute($unit_stmt);
                    // get_result() 는 실패 시 false, 조회 결과 없으면 fetch 가 null 이므로 모두 방어
                    $res = mysqli_stmt_get_result($unit_stmt);
                    $r = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
                    if (!is_array($r)) continue;
                    if ((int)($r['option_count'] ?? 0) > 1 && $option_id <= 0) continue;
                    if ($option_id > 0 && (int)($r['option_id'] ?? 0) !== $option_id) continue;
                    $unit = booking_price_won(($r['option_price'] ?? 0) ?: ($r['ht_min'] ?? 0));
                    if ($unit > 0) {
                        $sum += $unit * $qty; $found = true;
                        $option_label = trim((string)($r['option_name'] ?? ''));
                        if ($option_label === '') $option_label = rtrim(rtrim(number_format((float)($r['dosage_value'] ?? 0), 2, '.', ''), '0'), '.') . ($r['dosage_unit'] ?? '');
                        $verified_treatments[] = [
                            'name' => $tn, 'qty' => $qty,
                            'option_id' => (int)($r['option_id'] ?? 0),
                            'option_name' => mb_substr($option_label, 0, 100),
                            'dosage' => rtrim(rtrim(number_format((float)($r['dosage_value'] ?? 0), 2, '.', ''), '0'), '.') . ($r['dosage_unit'] ?? ''),
                        ];
                    }
                }
                mysqli_stmt_close($unit_stmt);
            }
            if ($found && count($verified_treatments) === count($requested_individual_treatments)) {
                $individual_summary = implode(', ', array_map(static function ($item) {
                    return $item['name'] . (!empty($item['option_name']) ? ' · ' . $item['option_name'] : '');
                }, $verified_treatments));
                $individual_quantity = array_sum(array_column($verified_treatments, 'qty'));
                $treatments_arr = array_merge($package_treatments, $verified_treatments);
                $quantity = ($selection_type === 'package' ? 1 : 0) + $individual_quantity;
                $treatment = ($selection_type === 'package' ? $treatment . ', ' : '') . $individual_summary;
                $booking_price = ($booking_price ?? 0) + $sum;
            } else {
                $booking_error = '선택한 시술 중 현재 예약할 수 없는 항목이 있습니다.';
            }
        } elseif ($booking_error === '' && $selection_type === 'package') {
            $treatments_arr = $package_treatments;
        } elseif ($booking_error === '') {
            $booking_error = '최소 1개 이상의 시술 또는 패키지를 선택해주세요.';
        }
        // treatment 컬럼(varchar 100)엔 요약(쉼표 이름) 저장 — 초과 시 잘라냄
        $treatment = mb_substr($treatment, 0, 100);

        // [05-12] 나이 제한 시술: 선택한 시술 중 최소 연령이 있는 것이 있으면
        //  - 생년월일을 알 수 없으면(비회원·전화 인증·생년월일 미등록) 접수하지 않고(age_unknown),
        //  - 회원 나이가 최소 연령에 못 미치면 접수하지 않는다(age_restricted).
        if ($booking_error === '' && $booking_has_min_age) {
            $age_member_age = (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in())
                ? booking_member_age(get_gangnamunni_member()) : null;
            $age_names = array_values(array_unique(array_filter(array_map(static function ($t) {
                return trim((string)($t['name'] ?? ''));
            }, $treatments_arr))));
            if (!empty($age_names)) {
                $age_in = implode(',', array_fill(0, count($age_names), '?'));
                $age_stmt = mysqli_prepare($connect,
                    "SELECT MAX(ht.min_age) AS max_min_age
                     FROM gangnamunni_hospital_treatments ht
                     JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
                     WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1
                       AND ht.min_age IS NOT NULL AND ht.min_age > 0 AND tm.name IN ({$age_in})");
                if (!$age_stmt) {
                    // 검증을 수행할 수 없으면 통과시키지 않는다.
                    error_log('[booking] 연령 제한 조회 준비 실패: ' . mysqli_error($connect));
                    $booking_error = '예약 가능 여부를 확인하지 못했어요. 잠시 후 다시 시도해주세요.';
                } else {
                    $age_types = 'i' . str_repeat('s', count($age_names));
                    $age_params = array_merge([$hospital_id], $age_names);
                    mysqli_stmt_bind_param($age_stmt, $age_types, ...$age_params);
                    mysqli_stmt_execute($age_stmt);
                    $age_res = mysqli_stmt_get_result($age_stmt);
                    $age_row = ($age_res instanceof mysqli_result) ? $age_res->fetch_assoc() : null;
                    mysqli_stmt_close($age_stmt);
                    $age_required = (int)($age_row['max_min_age'] ?? 0);
                    if ($age_required > 0 && $age_member_age === null) {
                        $booking_error = '나이 제한이 있는 시술이라 생년월일 등록이 필요해요.';
                        $booking_error_code = 'age_unknown';
                        $booking_error_meta = ['min_age' => $age_required];
                    } elseif ($age_required > 0 && $age_member_age < $age_required) {
                        $booking_error = '만 ' . $age_required . '세 미만은 이 시술을 앱에서 예약할 수 없어요.';
                        $booking_error_code = 'age_restricted';
                        $booking_error_meta = ['min_age' => $age_required];
                    }
                }
            }
        }

        // 예약 전 확인(위에서부터 차례로). 하나라도 걸리면 접수하지 않고 코드를 돌려준다.
        //  1) [05-14] 다른 병원에 같은 시술의 진행 중인 예약 → 불가(우회 불가)
        //  2) [05-16] 재시술 간격이 "필수"인 시술의 간격 미충족 → 불가(우회 불가)
        //  0) [05-17] 최근 노쇼 누적 → 예약 불가(우회 없음)
        //  3) [05-13] 같은 병원에 같은 시술의 진행 중인 예약 → 경고("새로 예약"으로 우회: confirm_duplicate=1)
        //  4) [05-15] 재시술 "권장" 간격 미충족 → 경고("그래도 예약"으로 우회: confirm_revisit=1)
        if ($booking_error === '' && $booking_mb_id !== '') {
            $dup_names = array_values(array_unique(array_filter(array_map(static function ($t) {
                return trim((string)($t['name'] ?? ''));
            }, $treatments_arr))));
            $revisit = $booking_has_revisit ? booking_revisit_status($connect, $booking_mb_id, $hospital_id, $dup_names)
                                            : ['strict' => null, 'warn' => null];
            $other_existing = booking_find_active_same_treatment($connect, $booking_mb_id, $hospital_id, $dup_names, false);
            $noshow = booking_noshow_status($connect, $booking_mb_id);
            if ($noshow) {
                $booking_error = '지금은 예약할 수 없어요.';
                $booking_error_code = 'noshow_blocked';
                $booking_error_meta = ['noshow' => $noshow];
            } elseif ($other_existing) {
                $booking_error = '다른 병원에 같은 시술 예약이 있어요.';
                $booking_error_code = 'other_hospital_duplicate';
                $booking_error_meta = ['existing' => $other_existing];
            } elseif ($revisit['strict']) {
                $booking_error = '아직 예약할 수 없어요.';
                $booking_error_code = 'revisit_blocked';
                $booking_error_meta = ['revisit' => $revisit['strict']];
            } else {
                if (($_POST['confirm_duplicate'] ?? '') !== '1') {
                    $same_existing = booking_find_active_same_treatment($connect, $booking_mb_id, $hospital_id, $dup_names, true);
                    if ($same_existing) {
                        $booking_error = '이미 예약된 시술이 있어요.';
                        $booking_error_code = 'duplicate_booking';
                        $booking_error_meta = ['existing' => $same_existing];
                    }
                }
                if ($booking_error === '' && $revisit['warn'] && ($_POST['confirm_revisit'] ?? '') !== '1') {
                    $booking_error = '아직 재시술 권장 시기가 아니에요.';
                    $booking_error_code = 'revisit_warning';
                    $booking_error_meta = ['revisit' => $revisit['warn']];
                }
            }
        }

        // [05-18] 관리자가 "같은 날 함께 예약하기 어렵다"고 지정한 시술 조합이면 막는다(하나 빼기 / 날짜 나누기 안내).
        if ($booking_error === '' && count($treatments_arr) > 1) {
            $conflict_pairs = [];
            $conflict_names = booking_treatment_conflict_names($connect, array_values(array_unique(array_filter(array_map(static function ($t) {
                return trim((string)($t['name'] ?? ''));
            }, $treatments_arr)))), $conflict_pairs);
            if (count($conflict_names) > 1) {
                $booking_error = '같은 날 함께 예약하기 어려운 시술이 있어요.';
                $booking_error_code = 'treatment_conflict';
                $booking_error_meta = ['conflict' => ['names' => $conflict_names, 'pairs' => $conflict_pairs]];
            }
        }

        // 사전 확인 요청(확인 화면을 열기 전): 제한에 걸리지 않으면 저장 없이 통과만 알린다.
        if ($booking_error === '' && $is_ajax_booking && ($_POST['precheck'] ?? '') === '1') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'precheck' => true]);
            exit;
        }

        if ($booking_error === '') {
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
        @mysqli_query($connect, "ALTER TABLE gangnamunni_bookings MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");

        $insert_query = "INSERT INTO gangnamunni_bookings
                        (hospital_id, hospital_name, name, phone, mb_id, treatment, treatments_json, quantity, booking_date, booking_time, memo, price, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $insert_stmt = mysqli_prepare($connect, $insert_query);
        $treatments_json_for_db = json_encode($treatments_arr, JSON_UNESCAPED_UNICODE);
        $price_for_db = $booking_price !== null ? (int)$booking_price : null;
        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, 'issssssisssi',
                $hospital_id, $hospital['name'], $name, $phone, $booking_mb_id,
                $treatment, $treatments_json_for_db, $quantity, $date, $time, $memo, $price_for_db
            );
        }

        if ($insert_stmt && mysqli_stmt_execute($insert_stmt)) {
            $booking_id = mysqli_insert_id($connect);
            mysqli_stmt_close($insert_stmt);
            $booking_submitted = true;

            // [05-19] 시술 전 확인 답변 저장: 이 병원에 실제로 등록된 질문과 일치하는 답만, 답한 문항만 저장한다(실패해도 예약은 유지).
            try {
                $pre_raw = json_decode((string)($_POST['pre_answers'] ?? '[]'), true);
                $ba_tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_booking_answers'");
                if (is_array($pre_raw) && $pre_raw && $ba_tbl && mysqli_num_rows($ba_tbl) > 0) {
                    $valid_q = [];
                    $vq = mysqli_prepare($connect, "SELECT q.question FROM gangnamunni_treatment_questions q JOIN gangnamunni_hospital_treatments ht ON ht.id = q.hospital_treatment_id WHERE ht.hospital_id = ?");
                    if ($vq) {
                        mysqli_stmt_bind_param($vq, 'i', $hospital_id);
                        mysqli_stmt_execute($vq);
                        $vr = mysqli_stmt_get_result($vq);
                        while ($vr && ($vrow = $vr->fetch_assoc())) $valid_q[(string)$vrow['question']] = true;
                        mysqli_stmt_close($vq);
                    }
                    $ba = mysqli_prepare($connect, "INSERT INTO gangnamunni_booking_answers (booking_id, question, answer) VALUES (?, ?, ?)");
                    if ($ba) {
                        $seen = [];
                        foreach (array_slice($pre_raw, 0, 30) as $pa) {
                            $pq = (string)($pa['q'] ?? '');
                            $pv = $pa['a'] ?? null;
                            if (!isset($valid_q[$pq]) || isset($seen[$pq]) || ($pv !== 0 && $pv !== 1)) continue;
                            $seen[$pq] = true;
                            $pv_int = (int)$pv;
                            mysqli_stmt_bind_param($ba, 'isi', $booking_id, $pq, $pv_int);
                            mysqli_stmt_execute($ba);
                        }
                        mysqli_stmt_close($ba);
                    }
                }
            } catch (Throwable $e) {
                error_log('[booking] pre_answers save failed: ' . $e->getMessage());
            }

            // 관리자 FCM + 인앱 알림
            notifyAdmins($connect, 'booking',
                '새 예약 접수',
                htmlspecialchars($hospital['name']) . ' — ' . htmlspecialchars($name) . ' (' . $date . ' ' . $time . ')',
                'admin_bookings.php?booking_id=' . (int)$booking_id,
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
                        $doctor_stmt = mysqli_prepare($connect,
                            "SELECT hms_doctor_id FROM gangnamunni_doctors
                             WHERE id = ? AND hospital_id = ? AND status = 'active' LIMIT 1");
                        if ($doctor_stmt) {
                            mysqli_stmt_bind_param($doctor_stmt, 'ii', $selected_doctor_id, $hospital_id);
                            mysqli_stmt_execute($doctor_stmt);
                            $doctor_row = mysqli_fetch_assoc(mysqli_stmt_get_result($doctor_stmt));
                            mysqli_stmt_close($doctor_stmt);
                            if ($doctor_row && !empty($doctor_row['hms_doctor_id'])) {
                                $appointment_data['doctor_id'] = (int)$doctor_row['hms_doctor_id'];
                            }
                        }
                    }
                    if ($selected_department) {
                        // Scope the department to the selected hospital's HMS tenant.
                        $dept_stmt = mysqli_prepare($connect,
                            "SELECT hms_department_id FROM gangnamunni_departments
                             WHERE id = ? AND hms_tenant_id = ? LIMIT 1");
                        $tenant_id = (string)$hospital['hms_hospital_id'];
                        $dept_row = null;
                        if ($dept_stmt) {
                            mysqli_stmt_bind_param($dept_stmt, 'is', $selected_department, $tenant_id);
                            mysqli_stmt_execute($dept_stmt);
                            $dept_row = mysqli_fetch_assoc(mysqli_stmt_get_result($dept_stmt));
                            mysqli_stmt_close($dept_stmt);
                        }
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
            $insert_error = $insert_stmt ? mysqli_stmt_error($insert_stmt) : mysqli_error($connect);
            error_log("[서울미녀] 예약 INSERT 실패: " . $insert_error);
            if ($insert_stmt) mysqli_stmt_close($insert_stmt);
            $booking_error = term('booking', '예약') . ' 중 오류가 발생했습니다. 다시 시도해주세요.';
        }
        }
    }
}

if ($is_ajax_booking && !$booking_submitted) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'code' => $booking_error_code,
        'message' => $booking_error !== '' ? $booking_error : term('booking', '예약') . ' 요청을 처리하지 못했습니다.',
    ] + $booking_error_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$booking_member = (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in())
    ? get_gangnamunni_member()
    : [];
$booking_member_name = trim((string)($booking_member['mb_name'] ?? ''));
$booking_member_phone = trim((string)($booking_member['mb_hp'] ?? ''));
if ($booking_member_phone === '') $booking_member_phone = trim((string)($booking_member['mb_tel'] ?? ''));
if ($booking_member_phone === '') $booking_member_phone = trim((string)($_SESSION['user_phone'] ?? ''));
$booking_member_has_saved_phone = trim((string)($booking_member['mb_hp'] ?? '')) !== ''
    || trim((string)($booking_member['mb_tel'] ?? '')) !== '';

$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true);

// 병원에 실제 배정된 시술 + 가격(신 시스템: gangnamunni_hospital_treatments +
// gangnamunni_hospital_treatment_prices) 로드 → 예약 시술 선택/가격 연동.
// 옵션 가격(샷수/용량)이 있으면 그 MIN~MAX, 없으면 배정 행의 price_min/max 사용.
$booking_treatments = [];
$bt_sql = "SELECT ht.id AS hospital_treatment_id, tm.id AS treatment_id, tm.name AS name, ht.price_min, ht.price_max, " . ($booking_has_min_age ? "ht.min_age" : "NULL AS min_age") . ",
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
        $booking_treatments[] = [
            'hospital_treatment_id' => (int)$row['hospital_treatment_id'],
            'treatment_id' => (int)$row['treatment_id'],
            'name' => $row['name'],
            'price_min' => $pmin,
            'price_max' => $pmax,
            'min_age' => (int)($row['min_age'] ?? 0),
        ];
    }
    mysqli_stmt_close($bt_stmt);
}

// [05-19] 시술 전 확인 질문(시술 이름 => 질문 목록). 병원이 시술별로 직접 적은 예/아니오 질문이며 모두 선택 사항이다.
$pre_questions = [];
$pq_tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_questions'");
if ($pq_tbl && mysqli_num_rows($pq_tbl) > 0 && !$is_changing) {
    if ($pq_stmt = mysqli_prepare($connect, "SELECT tm.name, q.question FROM gangnamunni_treatment_questions q
            JOIN gangnamunni_hospital_treatments ht ON ht.id = q.hospital_treatment_id
            JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
            WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 ORDER BY q.sort_order, q.id")) {
        mysqli_stmt_bind_param($pq_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($pq_stmt);
        $pq_res = mysqli_stmt_get_result($pq_stmt);
        while ($pq_res && ($pq_row = $pq_res->fetch_assoc())) { $pre_questions[(string)$pq_row['name']][] = (string)$pq_row['question']; }
        mysqli_stmt_close($pq_stmt);
    }
}

// 각 시술의 선택 가능한 세부 옵션. 2개 이상일 때 예약 화면에서 반드시 하나를 고른다.
$booking_options = [];
if (!empty($booking_treatments)) {
    $bt_ids = implode(',', array_map(static function ($t) { return (int)$t['hospital_treatment_id']; }, $booking_treatments));
    $bo_sql = "SELECT id, hospital_treatment_id, " . ($booking_option_name_column ? "option_name" : "'' AS option_name") . ", " . ($booking_body_part_column ? "body_part" : "'' AS body_part") . ", dosage_value, dosage_unit, price
               FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id IN ({$bt_ids}) AND price > 0
               ORDER BY sort_order ASC, id ASC";
    if ($bo_r = mysqli_query($connect, $bo_sql)) while ($bo = mysqli_fetch_assoc($bo_r)) {
        $option_name = trim((string)$bo['option_name']);
        $body_part = trim(str_replace('||', ', ', (string)($bo['body_part'] ?? '')));
        $body_part_display = $body_part !== '' ? '(' . $body_part . ')' : '';
        $dosage = rtrim(rtrim(number_format((float)$bo['dosage_value'], 2, '.', ''), '0'), '.') . $bo['dosage_unit'];
        $label_parts = array_values(array_filter([$option_name, $body_part_display], static function ($part) { return $part !== ''; }));
        $label = implode(' · ', $label_parts);
        if ($label === '') $label = $dosage;
        $booking_options[(int)$bo['hospital_treatment_id']][] = [
            'id' => (int)$bo['id'],
            'name' => $label,
            'option_name' => $option_name,
            'body_part' => $body_part_display,
            'dosage' => $dosage,
            'price' => (int)$bo['price'],
        ];
    }
}
foreach ($booking_treatments as &$booking_treatment) {
    $booking_treatment['options'] = $booking_options[$booking_treatment['hospital_treatment_id']] ?? [];
}
unset($booking_treatment);

// A treatment coming from index -> hospitals -> detail is preselected only when
// it is still an active, bookable treatment of this hospital.
$requested_treatment_id = max(0, (int)($_GET['treatment_id'] ?? 0));
$requested_option_id = max(0, (int)($_GET['option_id'] ?? 0));
$requested_treatment_name = trim((string)($_GET['treatment'] ?? ''));
$preselected_booking_treatment = null;
$preselected_booking_option_id = 0;
foreach ($booking_treatments as $bookable_treatment) {
    $id_matches = $requested_treatment_id > 0
        && $bookable_treatment['treatment_id'] === $requested_treatment_id;
    $name_matches = $requested_treatment_id === 0
        && $requested_treatment_name !== ''
        && hash_equals($bookable_treatment['name'], $requested_treatment_name);
    if ($id_matches || $name_matches) {
        $preselected_booking_treatment = $bookable_treatment;
        foreach ($bookable_treatment['options'] as $bookable_option) {
            if ((int)$bookable_option['id'] === $requested_option_id) {
                $preselected_booking_option_id = $requested_option_id;
                break;
            }
        }
        // [05-42] 예약 변경: 기존 예약의 옵션명으로 세부 옵션도 미리 선택
        if ($is_changing && $preselected_booking_option_id === 0 && !empty($change_booking['option_name'])) {
            // 저장 시 옵션명(raw)을 쓰고, 옵션명이 비면 용량을 쓴다 — 표시용 name 에는 '(부위)' 가 붙을 수 있어 세 가지 모두 비교
            foreach ($bookable_treatment['options'] as $bookable_option) {
                $_cands = [(string)($bookable_option['option_name'] ?? ''), (string)$bookable_option['name'], (string)($bookable_option['dosage'] ?? '')];
                if (in_array($change_booking['option_name'], array_map('trim', $_cands), true)) {
                    $preselected_booking_option_id = (int)$bookable_option['id'];
                    break;
                }
            }
        }
        break;
    }
}

// 예약 화면에서 뒤로 이동할 때 사용자가 보고 있던 시술 상세 컨텍스트를 유지한다.
// 요청값을 그대로 사용하지 않고, 현재 병원의 활성 시술로 검증된 값만 URL에 포함한다.
$booking_back_url = 'detail.php?id=' . (int)$hospital_id;
if ($booking_from_wishlist) {
    $booking_back_url .= '&from=my_wishlist';
}
if ($is_rebooking && $source_booking_id > 0) {
    $booking_back_url = 'booking_request_detail.php?booking_id=' . $source_booking_id;
}
if ($is_changing) {
    $booking_back_url = 'my_bookings.php';
}
if ($preselected_booking_treatment !== null) {
    if (!$is_rebooking) {
        $booking_back_url .= '&treatment_id=' . (int)$preselected_booking_treatment['treatment_id']
            . '&treatment=' . urlencode($preselected_booking_treatment['name'])
            . '#matched-treatment';
    }
}
$booking_success_done_url = $booking_back_url;

// [05-17] 노쇼·잦은 취소로 예약이 제한된 회원은 예약 화면에 들어오자마자 안내 시트를 본다(서버에서도 제출 시 다시 막는다).
$noshow_page_block = null;
$noshow_page_mb = (string)($_SESSION['ss_mb_id'] ?? '');
if (!$is_changing && $noshow_page_mb !== '' && function_exists('booking_noshow_status')) {
    try { $noshow_page_block = booking_noshow_status($connect, $noshow_page_mb); } catch (Throwable $e) { $noshow_page_block = null; }
}

// 현재 병원에서 예약 가능한 패키지. 구성 시술이 모두 활성인 패키지만 노출한다.
$booking_packages = [];
$package_table_check = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_packages'");
if ($package_table_check && mysqli_num_rows($package_table_check) > 0) {
    $bp_stmt = mysqli_prepare($connect,
        "SELECT p.id, p.name, p.description, p.package_price, p.original_price,
                GROUP_CONCAT(DISTINCT tm.name ORDER BY pi.sort_order SEPARATOR ', ') AS item_names
         FROM gangnamunni_treatment_packages p
         JOIN gangnamunni_treatment_package_items pi ON pi.package_id = p.id
         JOIN gangnamunni_treatment_master tm ON tm.id = pi.treatment_id
         JOIN gangnamunni_hospital_treatments ht
           ON ht.hospital_id = p.hospital_id AND ht.treatment_id = pi.treatment_id
          AND ht.status = 'active' AND ht.is_active = 1
         WHERE p.hospital_id = ? AND p.is_active = 1
           AND p.package_price > 0 AND p.package_price <= p.original_price
           AND NOT EXISTS (
               SELECT 1 FROM gangnamunni_treatment_package_items pi_bad
               LEFT JOIN gangnamunni_hospital_treatments ht_bad
                 ON ht_bad.hospital_id = p.hospital_id AND ht_bad.treatment_id = pi_bad.treatment_id
                AND ht_bad.status = 'active' AND ht_bad.is_active = 1
               WHERE pi_bad.package_id = p.id AND ht_bad.id IS NULL
           )
         GROUP BY p.id, p.name, p.description, p.package_price, p.original_price
         ORDER BY p.created_at DESC");
    if ($bp_stmt) {
        mysqli_stmt_bind_param($bp_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($bp_stmt);
        $bp_result = mysqli_stmt_get_result($bp_stmt);
        while ($bp = mysqli_fetch_assoc($bp_result)) $booking_packages[] = $bp;
        mysqli_stmt_close($bp_stmt);
    }
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
    @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap");

    /* 예약 화면의 모든 일반 텍스트에 Noto Sans KR 적용 (아이콘 폰트 제외) */
    body,
    button,
    input,
    select,
    textarea {
        font-family: "Noto Sans KR", sans-serif !important;
    }

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
        color: #AC1830;
        border: 1px solid #AC1830;
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
        cursor: pointer;
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
    .rebook-method { display:flex; flex-direction:column; gap:7px; margin-top:14px; }
    .rebook-method strong { font-size:15px; }
    .rebook-method span { color:#AC1830; font-size:13px; }
    .booking-form.rebooking { padding-top:22px; }
    .booking-form.rebooking .section-head-title,
    .booking-form.rebooking .form-label.date-select-label { font-size:18px; }
    .booking-form.rebooking .form-group { padding:0 4px 28px; margin:0 0 8px; border-bottom:8px solid #f4f4f4; }
    .booking-form.rebooking .form-group:last-of-type { border-bottom:0; }
    .booking-form.rebooking .submit-section { padding:0 4px 18px; }
    /* [05-54] 다시 예약 — 디자인 시안(+3px) */
    .booking-form.rebooking .form-group { border-bottom-color:#F4F2F3; }
    .booking-form.rebooking .section-head-title,
    .booking-form.rebooking .form-label.date-select-label { font-size:21px; }
    .booking-form.rebooking .st-name { font-size:18px; font-weight:400; color:#1A1718; }
    .booking-form.rebooking .st-price { font-size:18px; font-weight:500; color:#1A1718; }
    .booking-form.rebooking .st-remove { color:#1A1718; font-size:22px; }
    .booking-form.rebooking .month-selector { gap:18px; margin:6px 0 10px; }
    .booking-form.rebooking .month-item { font-size:18px; color:#9A9395; }
    .booking-form.rebooking .month-item.active { color:#1A1718; font-weight:700; }
    .booking-form.rebooking .date-selector { gap:0; padding:12px 0 10px; border-color:#ECE9EA; }
    .booking-form.rebooking .date-chip { width:calc(100% / 7); }
    .booking-form.rebooking .date-chip .dc-wd { font-size:15px; color:#9A9395; }
    .booking-form.rebooking .date-chip .dc-day { width:34px; height:34px; font-size:18px; font-weight:400; color:#1A1718; }
    .booking-form.rebooking .date-chip.active .dc-day { font-weight:700; color:#fff; }
    .booking-form.rebooking .date-chip .dc-today { font-size:14px; font-weight:400; color:#9A9395; }
    .booking-form.rebooking .rebook-method strong { font-size:17px; font-weight:500; }
    .booking-form.rebooking .rebook-method span { font-size:15px; }
    .booking-form.rebooking .time-grid { margin-top:14px; }
    .booking-form.rebooking .time-slot { display:flex; align-items:center; justify-content:center; height:40px; padding:0; border-color:#DCD6D8; color:#1A1718; font-size:17px; font-weight:400; }
    .booking-form.rebooking .time-slot.active { border-color:#AC1830; color:#AC1830; }
    .booking-form.rebooking .time-slot.disabled { color:#B5AEB0; background:#F7F5F6; border-color:#EEE9EB; font-size:15px; }
    .booking-form.rebooking .btn-submit { height:52px; padding:0; border-radius:4px; font-size:19px; font-weight:500; }

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
    .date-chip.active .dc-day { background: var(--color-primary, #AC1830); color: #fff; }
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
        color: #AC1830;
        border-color: #AC1830;
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
        color: #AC1830;
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
        border-color: #AC1830;
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
        background: #AC1830;
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
    .st-remove:hover { color: #AC1830; }
    .selected-treatment .st-name { font-weight: 700; color: #1f2937; }
    .selected-treatment .st-price { font-size: 13px; font-weight: 600; color: #1A1A1A; }
    /* 수량 스테퍼 */
    .st-qty-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 0;
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
        color: #AC1830;
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
        animation: none;
    }
    @keyframes sheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

    /* 상단 손잡이 + 제목 */
    .sheet-handle {
        width: 52px; height: 4px; border-radius: 2px;
        background: #AC1830;
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
    .sheet-section-title {
        font-size: 13px; font-weight: 700; color: #555; padding: 4px 2px 0;
    }
    .sheet-choice-divider {
        display: flex; align-items: center; gap: 12px; color: #999; font-size: 12px; margin: 4px 0;
    }
    .sheet-choice-divider::before, .sheet-choice-divider::after {
        content: ""; flex: 1; height: 1px; background: #e8e8e8;
    }
    .package-item { background: rgba(172, 24, 48, .025); }
    .detail-option-list { display:grid; gap:10px; width:100%; margin-top:2px; }
    .detail-option-item {
        display:grid;
        grid-template-columns:20px minmax(0, 1fr) auto;
        align-items:center;
        gap:12px;
        min-height:58px;
        padding:12px 16px;
        border:1px solid #e5e7eb;
        border-radius:12px;
        cursor:pointer;
        background:#fff;
        transition:border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
    }
    .detail-option-item:hover { border-color:#c9cdd3; }
    .detail-option-item.is-selected {
        border-color:#AC1830;
        background:#fff8f9;
        box-shadow:0 0 0 1px rgba(172, 24, 48,.06);
    }
    .detail-option-item input {
        width:18px;
        height:18px;
        margin:0;
        accent-color:#AC1830;
    }
    .detail-option-copy { min-width:0; display:flex; align-items:baseline; gap:6px; }
    .detail-option-name { color:#20242b; font-size:15px; font-weight:600; }
    .detail-option-dosage { color:#8b9199; font-size:12px; }
    .detail-option-price { color:#20242b; font-size:15px; font-weight:700; white-space:nowrap; }
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
        border-color: #AC1830;
        box-shadow: inset 0 0 0 1px #AC1830;
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
    .price-now { font-size: 17px; font-weight: 700; color: #AC1830; }
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
        background: #AC1830;
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
        background: #AC1830;
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
        height: 64px; display: grid; grid-template-columns: 40px minmax(0, 1fr) 40px;
        align-items: center; text-align: center;
    }
    .confirm-back-button {
        width: 40px; height: 40px; padding: 0; border: 0; background: transparent;
        color: #111; display: inline-flex; align-items: center; justify-content: flex-start;
        font-size: 26px; line-height: 1; cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    .confirm-back-button:active { opacity: .55; }
    .confirm-page-head-spacer { width: 40px; height: 40px; }
    .confirm-page-title { font-size: 19px; font-weight: 600; letter-spacing: -.04em; }
    .booking-state-badge {
        display: inline-flex; align-items: center; gap: 24px; margin: 18px 0 24px;
        padding: 10px 16px; background: #BE1935; color: #fff; font-size:18px; font-weight: 700;
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
    .confirm-phone-field { min-width: 0; }
    .confirm-phone-input {
        width: 100%; min-width: 0; height: 38px; padding: 7px 10px;
        border: 1px solid #ccc; border-radius: 4px; background: #fff;
        color: #111; font: inherit; font-weight: 700; box-sizing: border-box;
    }
    .confirm-phone-input:focus { border-color: #BE1935; outline: 2px solid rgba(190,25,53,.12); }
    .confirm-phone-input[aria-invalid="true"] { border-color: #BE1935; }
    .confirm-phone-error { display: none; margin-top: 5px; color: #BE1935; font-size: 12px; font-weight: 600; }
    .confirm-phone-error.show { display: block; }
    .confirm-phone-help {
        grid-column: 2; margin-top: -6px; color: #777;
        font-size: 12px; line-height: 1.55; word-break: keep-all;
    }
    .confirm-phone-help a { color: #BE1935; font-weight: 700; text-decoration: underline; }
    .confirm-notice { background: #fff1f3; padding: 24px 24px 20px; margin-bottom: 20px; }
    .confirm-notice-title { color: #BE1935; font-size: 16px; font-weight: 700; margin-bottom: 10px; }
    .confirm-notice ul { margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.85; }
    .confirm-notice em { color: #BE1935; font-style: normal; }
    .confirm-submit {
        width: 100%; height: 54px; border: 0; background: #BE1935; color: #fff;
        font-size: 17px; font-weight: 700; cursor: pointer;
    }
    .confirm-submit:disabled { opacity: .55; cursor: not-allowed; }

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
    /* [05-19] 시술 전 확인 (전체 화면, 반응형, 폰트 = 디자인 +3px) */
    #preCheckPage { position: fixed; inset: 0; z-index: 12500; display: none; flex-direction: column; background: #fff; color: #1A1718; letter-spacing: -.01em; }
    #preCheckPage.open { display: flex; }
    #preCheckPage .pc-wrap { width: 100%; max-width: 640px; margin: 0 auto; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
    #preCheckPage .pc-head { height: 52px; flex-shrink: 0; padding: 0 8px; display: grid; grid-template-columns: 44px 1fr 44px; align-items: center; }
    #preCheckPage .pc-back { width: 44px; height: 44px; border: 0; background: none; display: flex; align-items: center; justify-content: center; color: #1A1718; cursor: pointer; }
    #preCheckPage .pc-head-title { text-align: center; font-size: 20px; font-weight: 500; white-space: nowrap; }
    #preCheckPage .pc-scroll { flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 8px 20px 20px; }
    #preCheckPage .pc-title { font-size: 22px; font-weight: 700; line-height: 1.4; letter-spacing: -.04em; }
    #preCheckPage .pc-desc { margin-top: 6px; font-size: 16px; line-height: 1.6; color: #4A4446; }
    #preCheckPage .pc-list { margin-top: 12px; }
    #preCheckPage .pc-item { padding: 14px 0; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #ECE9EA; }
    #preCheckPage .pc-q { flex-grow: 1; min-width: 0; font-size: 17px; line-height: 1.5; word-break: keep-all; overflow-wrap: anywhere; }
    #preCheckPage .pc-btns { display: flex; gap: 6px; flex-shrink: 0; }
    #preCheckPage .pc-btn { width: 60px; height: 38px; display: flex; align-items: center; justify-content: center; border: 1px solid #DCD6D8; background: #fff; color: #4A4446; font-size: 16px; font-family: inherit; cursor: pointer; }
    #preCheckPage .pc-btn.on { border-color: #AC1830; background: #FBEFF1; color: #AC1830; }
    #preCheckPage .pc-truth { margin-top: 16px; display: flex; align-items: center; gap: 8px; font-size: 16px; cursor: pointer; border: 0; background: none; padding: 0; font-family: inherit; color: inherit; text-align: left; }
    #preCheckPage .pc-truth i { width: 20px; height: 20px; flex-shrink: 0; background: url(assets/icons/icon-radio-off.png?v=1) center/contain no-repeat; }
    #preCheckPage .pc-truth.on i { background-image: url(assets/icons/icon-radio-on.png?v=1); }
    #preCheckPage .pc-foot { flex-shrink: 0; padding: 16px 20px max(34px, env(safe-area-inset-bottom)); background: #fff; }
    #preCheckPage .pc-next { width: 100%; height: 52px; border: 1px solid #AC1830; border-radius: 4px; background: #AC1830; color: #fff; font-size: 19px; font-weight: 500; font-family: inherit; cursor: pointer; }
    @media (max-width: 360px) {
        #preCheckPage .pc-item { flex-wrap: wrap; }
        #preCheckPage .pc-q { flex-basis: 100%; }
        #preCheckPage .pc-btns { width: 100%; }
        #preCheckPage .pc-btn { flex: 1 1 0; width: auto; }
    }
    /* [05-20] 의료진 상담 안내 / [05-21] 시술 전 확인 동의 시트 */
    #medConsultOverlay .booking-motion-panel, #consentOverlay .booking-motion-panel { border-radius: 16px 16px 0 0; }
    #medConsultOverlay .booking-motion-handle::before, #consentOverlay .booking-motion-handle::before { top: 10px; width: 40px; height: 4px; border-radius: 2px; background: #AC1830; }
    #medConsultOverlay .booking-motion-handle, #consentOverlay .booking-motion-handle { height: 24px; }
    #medConsultOverlay .booking-motion-body, #consentOverlay .booking-motion-body { padding: 18px 20px 8px; }
    #medConsultOverlay .ns-icon { display: block; width: 48px; height: 48px; margin: 0 auto 14px; object-fit: contain; }
    #medConsultOverlay .success-sheet-title, #consentOverlay .success-sheet-title { font-size: 22px; font-weight: 700; line-height: 1.45; }
    #medConsultOverlay .success-sheet-desc { margin: 12px 0 0; font-size: 17px; line-height: 1.6; color: #4A4446; }
    #medConsultOverlay .mc-label { margin-top: 16px; font-size: 15px; font-weight: 700; color: #AC1830; }
    #medConsultOverlay .mc-item { margin-top: 8px; padding: 12px 14px; background: #FBEFF1; border-radius: 4px; font-size: 17px; line-height: 1.5; word-break: keep-all; overflow-wrap: anywhere; }
    #consentOverlay .cs-list { margin-top: 12px; display: flex; flex-direction: column; gap: 16px; }
    #consentOverlay .cs-row { display: flex; align-items: flex-start; gap: 8px; font-size: 16px; line-height: 1.5; color: #4A4446; border: 0; background: none; padding: 0; width: 100%; text-align: left; font-family: inherit; cursor: pointer; }
    #consentOverlay .cs-row i { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; background: url(assets/icons/icon-radio-off.png?v=1) center/contain no-repeat; }
    #consentOverlay .cs-row.on i { background-image: url(assets/icons/icon-radio-on.png?v=1); }
    #consentOverlay .cs-row.all { font-size: 18px; font-weight: 700; color: #1A1718; }
    #consentOverlay .cs-req { color: #AC1830; }
    #consentOverlay .cs-view { flex-shrink: 0; margin-left: auto; font-size: 16px; color: #1A1718; text-decoration: underline; background: none; border: 0; padding: 0; font-family: inherit; cursor: pointer; }
    #consentOverlay .cs-note { display: none; margin: 10px 0 0 28px; font-size: 15px; line-height: 1.6; color: #4A4446; }
    #consentOverlay .cs-note.open { display: block; }
    #medConsultOverlay .booking-motion-footer, #consentOverlay .booking-motion-footer { padding: 14px 20px max(34px, env(safe-area-inset-bottom)); }
    #medConsultOverlay .success-actions, #consentOverlay .success-actions { display: flex; gap: 10px; margin-top: 0; }
    #medConsultOverlay .success-action, #consentOverlay .success-action { flex: 1 1 0; height: 52px; border-radius: 4px; border-color: #AC1830; background: #fff; color: #AC1830; font-size: 19px; font-weight: 500; }
    #medConsultOverlay .success-action.primary, #consentOverlay .success-action.primary { background: #AC1830; color: #fff; }
    #consentOverlay .success-action.primary:disabled { opacity: .4; cursor: not-allowed; }
    /* [05-18] 같은 날 함께 예약하기 어려운 시술 시트 */
    #conflictOverlay .booking-motion-panel { border-radius: 16px 16px 0 0; }
    #conflictOverlay .booking-motion-handle::before { top: 10px; width: 40px; height: 4px; border-radius: 2px; background: #AC1830; }
    #conflictOverlay .booking-motion-handle { height: 24px; }
    #conflictOverlay .booking-motion-body { padding: 18px 20px 8px; }
    #conflictOverlay .ns-icon { display: block; width: 48px; height: 48px; margin: 0 auto 14px; object-fit: contain; }
    #conflictOverlay .success-sheet-title { font-size: 23px; font-weight: 700; line-height: 1.45; }
    #conflictOverlay .success-sheet-desc { margin: 14px 0 0; font-size: 18px; line-height: 1.6; color: #4A4446; }
    #conflictList { display: flex; flex-direction: column; gap: 6px; margin-top: 18px; }
    #conflictList .cf-row { min-height: 46px; padding: 0 14px; display: flex; align-items: center; gap: 8px; border: 1px solid #DCD6D8; font-size: 17px; text-align: left; }
    #conflictList .cf-row img { width: 18px; height: 18px; flex-shrink: 0; object-fit: contain; }
    #conflictOverlay .booking-motion-footer { padding: 14px 20px max(34px, env(safe-area-inset-bottom)); }
    #conflictOverlay .success-actions { display: flex; gap: 10px; margin-top: 0; }
    #conflictOverlay .success-action { flex: 1 1 0; height: 52px; border-radius: 4px; border-color: #AC1830; background: #fff; color: #AC1830; font-size: 19px; font-weight: 500; }
    #conflictOverlay .success-action.primary { background: #AC1830; color: #fff; }
    .split-hint { margin-top: 8px; padding: 10px 12px; background: #FBEFF1; color: #AC1830; font-size: 16px; line-height: 1.5; }
    #noshowOverlay .success-actions { display: flex; justify-content: center; }
    /* [05-17] 예약 불가 시트: 디자인(폰트 +3px, #AC1830) */
    #noshowOverlay .booking-motion-panel { border-radius: 16px 16px 0 0; }
    #noshowOverlay .booking-motion-handle::before { top: 10px; width: 40px; height: 4px; border-radius: 2px; background: #AC1830; }
    #noshowOverlay .booking-motion-handle { height: 24px; }
    #noshowOverlay .booking-motion-body { padding: 18px 20px 8px; }
    #noshowOverlay .ns-icon { display: block; width: 48px; height: 48px; margin: 0 auto 14px; object-fit: contain; }
    #noshowOverlay .success-sheet-title { font-size: 23px; font-weight: 700; line-height: 1.45; }
    #noshowOverlay .success-sheet-desc { margin: 14px 0 0; font-size: 18px; line-height: 1.6; color: #4A4446; }
    #noshowList { display: flex; flex-direction: column; gap: 6px; margin-top: 18px; }
    #noshowList .ns-row { min-height: 48px; padding: 0 14px; display: flex; align-items: center; gap: 8px; border: 1px solid #DCD6D8; font-size: 18px; color: #1A1718; }
    #noshowList .ns-row span:first-child { flex-grow: 1; min-width: 0; letter-spacing: -.04em; }
    #noshowList .ns-row .ns-tag { flex-shrink: 0; color: #AC1830; font-weight: 500; }
    #noshowOverlay .booking-motion-footer { padding: 14px 20px max(34px, env(safe-area-inset-bottom)); }
    #noshowOverlay .success-action { flex: none; width: 180px; height: 52px; border-radius: 4px; border-color: #AC1830; background: #AC1830; color: #fff; font-size: 19px; font-weight: 500; }
    .success-action {
        height: 52px; border: 1px solid #BE1935; background: #fff; color: #BE1935;
        display: flex; align-items: center; justify-content: center; text-decoration: none;
        font-size: 16px; font-weight: 700;
    }
    .success-action.primary { background: #BE1935; color: #fff; }
    button.success-action { font-family: inherit; cursor: pointer; }
    .dup-booking-card { margin: 0 0 8px; padding: 16px; background: #fff1f3; border-radius: 8px; text-align: left; }
    .dup-booking-badges { display: flex; gap: 6px; margin-bottom: 8px; }
    .dup-badge { padding: 2px 8px; border-radius: 10px; background: #fff; border: 1px solid #ddd; font-size: 12px; font-weight: 600; color: #666; }
    .dup-badge.on { border-color: #BE1935; color: #BE1935; }
    .dup-booking-name { font-size: 15px; font-weight: 700; color: #111; word-break: keep-all; }
    .dup-booking-when { margin-top: 6px; font-size: 13px; color: #BE1935; font-weight: 600; }

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
        color: #AC1830;
        text-decoration: none;
    }

    @media (max-width: 768px) {
        .treatment-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .treatment-sheet-overlay { background: rgba(0,0,0,.58); }
    .treatment-sheet {
        /* max-height는 지정하지 않는다 — 손잡이/제목/완료 버튼 높이까지 합치면
           54vh로는 시술 카드 3개(.sheet-list max-height:342px)가 다 안 들어가
           잘려 보이는 문제가 있었다. 기본 규칙의 max-height:88%가 안전망 역할. */
        padding: 10px 20px calc(20px + env(safe-area-inset-bottom, 0px));
        border-radius: 0;
        will-change: transform;
    }
    .sheet-handle {
        position: relative;
        width: 64px; height: 28px; margin: -6px auto 0;
        background: transparent; border-radius: 0;
        cursor: grab; touch-action: none; user-select: none;
    }
    .sheet-handle::before {
        content: ""; position: absolute; left: 50%; top: 10px;
        width: 34px; height: 4px; transform: translateX(-50%);
        border-radius: 2px; background: #AC1830;
    }
    .sheet-handle:active { cursor: grabbing; }
    .treatment-sheet.is-opening { animation: sheetUp 0.22s ease-out; }
    .treatment-sheet.is-dragging { animation: none; transition: none; }
    .treatment-sheet.is-closing { animation: none; transform: translateY(110%); transition: transform .22s ease-out; }
    .treatment-sheet.is-snapping { animation: none; transition: transform .22s ease-out; }
    .sheet-title { margin-bottom: 12px; font-size: 14px; font-weight: 600; }
    .sheet-list {
        flex: 0 1 auto; gap: 14px; margin-bottom: 14px;
        max-height: 342px; overflow-y: auto;
        overscroll-behavior: contain; touch-action: pan-y;
        -webkit-overflow-scrolling: touch;
    }
    .sheet-section-title, .sheet-choice-divider { display: none; }
    .sheet-item { padding: 13px 10px; border-radius: 3px; background: #fff; }
    .sheet-item.selected { border: 2px solid #AC1830; box-shadow: none; padding: 12px 9px; }
    .sheet-item-name { margin-bottom: 8px; font-size: 14px; line-height: 1.45; }
    .sheet-item-desc { font-size: 12px; }
    .sheet-item-price { gap: 8px; }
    .price-now { font-size: 15px; }
    .sheet-confirm { height: 48px; padding: 0 16px; border-radius: 0; font-size: 14px; }

';

// ========================================
// 페이지별 JS (script 태그 없이)
// ========================================
$page_js = '
    var HAS_WEEKLY_HOURS = ' . ($booking_has_weekly_hours ? 'true' : 'false') . ';
    var WEEKLY_HOURS = ' . json_encode($booking_weekly_hours, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    var SPECIAL_CLOSED_DATES = ' . json_encode($booking_special_closed_dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    var SPECIAL_OPEN_DATES = ' . json_encode($booking_special_open_dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';

    // 격주 진료 짝/홀수 판정: 매월 1일부터 리셋되는 "이번 달 몇 번째 발생인지"(0번째부터) 기준.
    // 연도 전체를 관통하는 ISO 주차가 아니라, 달이 바뀌면 항상 0부터 다시 센다.
    function monthOccurrenceParity(d) {
        return (Math.floor((d.getDate() - 1) / 7) % 2 === 0) ? "even" : "odd";
    }
    // 격주 진료 요일이 해당 날짜의 짝/홀수 발생 순번과 맞지 않으면 null(휴무)로 취급한다.
    function resolveDayHours(dayHours, d) {
        if (!dayHours) return null;
        if (dayHours.biweekly && dayHours.biweekly !== monthOccurrenceParity(d)) return null;
        return dayHours;
    }

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
        while (cur < endDate) {
            // 관리자에서 휴무로 설정한 요일은 날짜 선택 목록에서 제외한다.
            var curValue = cur.getFullYear() + "-" + (cur.getMonth() + 1 < 10 ? "0" : "") + (cur.getMonth() + 1) + "-" + (cur.getDate() < 10 ? "0" : "") + cur.getDate();
            var curWeeklyOpen = !HAS_WEEKLY_HOURS || resolveDayHours(WEEKLY_HOURS[cur.getDay()], cur);
            if ((curWeeklyOpen || SPECIAL_OPEN_DATES.hasOwnProperty(curValue)) && SPECIAL_CLOSED_DATES.indexOf(curValue) < 0) dates.push(new Date(cur));
            cur.setDate(cur.getDate() + 1);
        }
        var months = [];
        dates.forEach(function(d) { var k = d.getFullYear() + "-" + (d.getMonth() + 1); if (months.indexOf(k) < 0) months.push(k); });
        var selectedMonth = months[0];

        function pad(n) { return n < 10 ? "0" + n : "" + n; }
        function fmt(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }

        // 오늘이 휴무라 목록에서 빠진 경우 첫 번째 영업일을 기본 선택한다.
        if (dates.length && !dates.some(function(d) { return fmt(d) === hidden.value; })) hidden.value = fmt(dates[0]);

        // 월 클릭 시 첫 달은 오늘(목록의 첫 날짜), 다음 달은 해당 월의 첫 예약 가능 날짜를 왼쪽에 맞춘다.
        function renderMonths() {
            monthSel.innerHTML = "";
            months.forEach(function(k) {
                var m = parseInt(k.split("-")[1], 10);
                var el = document.createElement("span");
                el.className = "month-item" + (k === selectedMonth ? " active" : "");
                el.textContent = m + "월";
                el.setAttribute("role", "button");
                el.setAttribute("tabindex", "0");
                el.setAttribute("aria-pressed", k === selectedMonth ? "true" : "false");
                el.onclick = function() { scrollToMonth(k); };
                el.onkeydown = function(event) {
                    if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        scrollToMonth(k);
                    }
                };
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
        function scrollToMonth(monthKey) {
            var target = dateSel.querySelector(".date-chip[data-month=\"" + monthKey + "\"]");
            if (!target) return;
            selectedMonth = monthKey;
            renderMonths();
            var targetLeft = target.getBoundingClientRect().left - dateSel.getBoundingClientRect().left + dateSel.scrollLeft;
            dateSel.scrollTo({ left: targetLeft, behavior: "auto" });
        }
        // 스와이프(스크롤) 시 왼쪽에 보이는 날짜의 월로 활성 월 자동 변경
        function updateActiveMonthFromScroll() {
            var chips = dateSel.querySelectorAll(".date-chip");
            var selectorLeft = dateSel.getBoundingClientRect().left;
            var current = null;
            for (var i = 0; i < chips.length; i++) {
                if (chips[i].getBoundingClientRect().right > selectorLeft + 4) {
                    current = chips[i].getAttribute("data-month");
                    break;
                }
            }
            if (current && current !== selectedMonth) { selectedMonth = current; renderMonths(); }
        }
        dateSel.addEventListener("scroll", updateActiveMonthFromScroll);

        renderMonths();
        renderDates();
        // [05-42] 예약 변경: 선택된 날짜 칩이 보이도록 스크롤
        var activeChip = dateSel.querySelector(".date-chip.active");
        if (activeChip && hidden.value !== fmt(dates[0] || today)) {
            dateSel.scrollTo({ left: Math.max(0, activeChip.getBoundingClientRect().left - dateSel.getBoundingClientRect().left + dateSel.scrollLeft - 8), behavior: "auto" });
        }
    }
    initDatePicker();

    // [오늘 지난 시간 픽스] 서버(KST) 기준 오늘 날짜/현재시각(자정 이후 분) — 지난 시간대 비활성용
    var SERVER_TODAY = "' . date('Y-m-d') . '";
    var MEMBER_AGE = ' . (($__ma = booking_member_age($booking_member)) === null ? 'null' : (int)$__ma) . ';
    var SERVER_NOW_MIN = ' . (date('G') * 60 + (int)date('i')) . ';
    var LUNCH_START_MIN = ' . ($booking_lunch_start_min === null ? 'null' : (int)$booking_lunch_start_min) . ';
    var LUNCH_END_MIN = ' . ($booking_lunch_end_min === null ? 'null' : (int)$booking_lunch_end_min) . ';

    // 시간대 그리드 (숨긴 select 옵션 → 빨강 박스 칩, 한 줄 4개). 클릭 시 select 값 동기화.
    function buildTimeGrid() {
        var sel = document.getElementById("timeSelect");
        var grid = document.getElementById("timeGrid");
        if (!sel || !grid) return;
        grid.innerHTML = "";
        // 선택된 날짜가 오늘이면 이미 지난 시간대는 클릭 불가로 만든다.
        var selDate = (document.getElementById("bookingDate") || {}).value || "";
        var isToday = (selDate === SERVER_TODAY);
        var selectedDay = selDate ? new Date(selDate + "T00:00:00").getDay() : -1;
        var weeklyDayHours = HAS_WEEKLY_HOURS && selectedDay >= 0
            ? resolveDayHours(WEEKLY_HOURS[selectedDay], new Date(selDate + "T00:00:00"))
            : null;
        var dayHours = SPECIAL_OPEN_DATES[selDate] || weeklyDayHours;
        // 날짜 변경 뒤 이전 날짜에서 고른 시간이 남지 않도록 먼저 초기화한다.
        sel.value = "";
        if (HAS_WEEKLY_HOURS && !dayHours) {
            var closed = document.createElement("div");
            closed.className = "time-slot disabled";
            closed.style.gridColumn = "1 / -1";
            closed.textContent = "선택한 날짜는 휴무일입니다.";
            grid.appendChild(closed);
            return;
        }
        for (var i = 0; i < sel.options.length; i++) {
            var value = sel.options[i].value;
            if (!value) continue;
            // HMS 스케줄 로딩 시 예약됨/지난 시간은 disabled 로 온다 → 표시는 하되 클릭 불가
            var disabled = sel.options[i].disabled;
            var label = sel.options[i].textContent || value;
            var hm = value.split(":");
            var slotMin = parseInt(hm[0], 10) * 60 + parseInt(hm[1], 10);
            // 종료 시각은 진료가 끝나는 시각이므로 해당 시각 슬롯은 포함하지 않는다.
            if (dayHours && (slotMin < dayHours.open || slotMin >= dayHours.close)) continue;
            // 오늘 && 지난 시간대 → 비활성 (정적 그리드/HMS 공통)
            if (!disabled && isToday) {
                if (slotMin <= SERVER_NOW_MIN) { disabled = true; }
            }
            // 점심시간 슬롯은 비활성 표시하지 않고 예약 시간표에서 완전히 제외한다.
            if (LUNCH_START_MIN !== null && LUNCH_END_MIN !== null
                && slotMin >= LUNCH_START_MIN && slotMin < LUNCH_END_MIN) continue;
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
        if (!grid.children.length) {
            var empty = document.createElement("div");
            empty.className = "time-slot disabled";
            empty.style.gridColumn = "1 / -1";
            empty.textContent = "선택 가능한 시간이 없습니다.";
            grid.appendChild(empty);
        }
    }
    buildTimeGrid();
    // [05-42] 예약 변경: 들어오면 기존 예약의 날짜(서버가 hidden 에 채움)와 시간이 이미 선택돼 있어야 한다.
    var CHANGE_MODE = ' . ($is_changing ? 'true' : 'false') . ';
    var CHANGE_BOOKING = ' . ($is_changing ? json_encode(['id' => (int)$change_booking['id'], 'time' => $change_booking['time'], 'phone' => $change_booking['phone'], 'csrf' => $_SESSION['my_bookings_csrf'] ?? ''], JSON_UNESCAPED_UNICODE) : 'null') . ';
    function selectChangeTime() {
        if (!CHANGE_MODE) return;
        var grid = document.getElementById("timeGrid");
        if (!grid) return;
        var slots = grid.querySelectorAll(".time-slot:not(.disabled)");
        for (var i = 0; i < slots.length; i++) {
            if ((slots[i].textContent || "").trim() === CHANGE_BOOKING.time) { slots[i].click(); break; }
        }
    }
    selectChangeTime();
    window.buildTimeGrid = buildTimeGrid; // 날짜 변경 시 재생성용(오늘 지난 시간 재평가)

    // 시술 선택 시트(옵션 변경)
    function openTreatmentSheet() {
        var s = document.getElementById("treatmentSheet");
        if (!s) return;
        // 이미 선택된 항목을 시트에 표시 동기화
        s.querySelectorAll(".sheet-item:not(.package-item)").forEach(function(el) {
            markSheetItem(el, !!findTreatment(el.getAttribute("data-name")));
        });
        s.querySelectorAll(".package-item").forEach(function(el) {
            markSheetItem(el, !!selectedPackage && parseInt(el.getAttribute("data-package-id"), 10) === selectedPackage.id);
        });
        window.bookingTreatmentMotion.open();
    }
    function closeTreatmentSheet() {
        return window.bookingTreatmentMotion.close();
    }

    // 시트 카드의 선택 표시(테두리 강조)와 스크린리더 상태를 함께 갱신한다.
    function markSheetItem(el, on) {
        if (!el) return;
        el.classList.toggle("selected", !!on);
        el.setAttribute("aria-pressed", on ? "true" : "false");
    }

    // 개별 시술은 여러 개를 선택할 수 있고, 각 시술별 세부 옵션은 1개를 선택한다.
    var selectedTreatments = [];
    var selectedPackage = null;
    function bookingPriceWon(price) {
        return Number(price) || 0;
    }
    var initialTreatmentName = ' . json_encode(
        $preselected_booking_treatment['name'] ?? '',
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . ';
    var initialOptionId = ' . (int)$preselected_booking_option_id . ';

    function findTreatment(name) {
        for (var i = 0; i < selectedTreatments.length; i++) { if (selectedTreatments[i].name === name) return selectedTreatments[i]; }
        return null;
    }
    // 시트 항목 탭 → 목록에 추가/제거 (토글). 시트는 닫지 않아 여러 개 선택 가능.
    function pickTreatment(btn) {
        var name = btn.getAttribute("data-name") || "";
        var price = btn.getAttribute("data-price") || "";
        var options = [];
        try { options = JSON.parse(btn.getAttribute("data-options") || "[]"); } catch (e) {}
        var alreadySelected = !!findTreatment(name);
        if (alreadySelected) {
            selectedTreatments = selectedTreatments.filter(function(t) { return t.name !== name; });
        } else {
            if (selectedTreatments.length >= 20) {
                alert("시술은 한 번에 최대 20개까지 선택할 수 있습니다.");
                return;
            }
            selectedTreatments.push({
                name: name, price: price, qty: 1, options: options,
                min_age: parseInt(btn.getAttribute("data-min-age") || "0", 10) || 0,
                option_id: options.length === 1 ? parseInt(options[0].id, 10) : 0,
                option_name: options.length === 1 ? options[0].name : ""
            });
        }
        document.querySelectorAll("[data-treatment-choice]").forEach(function(el) {
            var on = !!findTreatment(el.getAttribute("data-name"));
            markSheetItem(el, on);
            var cb = el.querySelector("input[type=checkbox]"); if (cb) cb.checked = on;
        });
        renderSelectedTreatments();
    }
    function pickDetailOption(treatmentName, optionId) {
        var t = findTreatment(treatmentName); if (!t) return;
        t.option_id = parseInt(optionId, 10) || 0;
        for (var i = 0; i < (t.options || []).length; i++) if (parseInt(t.options[i].id, 10) === t.option_id) {
            t.option_name = t.options[i].name || ""; t.price = bookingPriceWon(t.options[i].price).toLocaleString("ko-KR") + "원"; break;
        }
        renderSelectedTreatments();
    }
    function pickPackage(btn) {
        var packageId = parseInt(btn.getAttribute("data-package-id"), 10) || 0;
        if (selectedPackage && selectedPackage.id === packageId) {
            selectedPackage = null;
            markSheetItem(btn, false);
        } else {
            document.querySelectorAll("#treatmentSheet .package-item").forEach(function(el) { markSheetItem(el, el === btn); });
            selectedPackage = {
                id: packageId,
                name: btn.getAttribute("data-name") || "",
                price: btn.getAttribute("data-price") || "",
                items: btn.getAttribute("data-items") || ""
            };
        }
        renderSelectedTreatments();
    }
    function removeTreatment(name) {
        for (var i = 0; i < selectedTreatments.length; i++) { if (selectedTreatments[i].name === name) { selectedTreatments.splice(i, 1); break; } }
        document.querySelectorAll("[data-treatment-choice]").forEach(function(el) {
            if (el.getAttribute("data-name") === name) {
                markSheetItem(el, false);
                var cb = el.querySelector("input[type=checkbox]"); if (cb) cb.checked = false;
            }
        });
        renderSelectedTreatments();
    }
    function renderSelectedTreatments() {
        // 폼 필드 동기화
        document.getElementById("selectionTypeInput").value = selectedPackage ? "package" : "treatments";
        document.getElementById("packageIdInput").value = selectedPackage ? selectedPackage.id : 0;
        document.getElementById("treatmentsJson").value = JSON.stringify(selectedTreatments);
        document.getElementById("treatmentInput").value = selectedPackage
            ? "[패키지] " + selectedPackage.name + (selectedTreatments.length ? ", " + selectedTreatments.map(function(t) { return t.name; }).join(", ") : "")
            : selectedTreatments.map(function(t) { return t.name; }).join(", ");
        var totalQty = 0; selectedTreatments.forEach(function(t) { totalQty += t.qty; });
        document.getElementById("quantityInput").value = (selectedPackage ? 1 : 0) + totalQty || 1;

        if (window.updateSubmitState) window.updateSubmitState();

        var disp = document.getElementById("selectedTreatmentDisplay");
        var ph = document.getElementById("treatmentPlaceholder");
        if (!disp) return;
        disp.innerHTML = "";
        if (!selectedPackage && selectedTreatments.length === 0) {
            disp.style.display = "none";
            if (ph) ph.style.display = "";
            return;
        }
        if (ph) ph.style.display = "none";
        disp.style.display = "flex";
        if (selectedPackage) {
            var packageCard = document.createElement("div"); packageCard.className = "st-item";
            var packageTop = document.createElement("div"); packageTop.className = "st-top";
            var packageName = document.createElement("span"); packageName.className = "st-name"; packageName.textContent = "[패키지] " + selectedPackage.name;
            var packageRemove = document.createElement("button"); packageRemove.type = "button"; packageRemove.className = "st-remove"; packageRemove.textContent = "×";
            packageRemove.onclick = function() { selectedPackage = null; renderSelectedTreatments(); };
            packageTop.appendChild(packageName); packageTop.appendChild(packageRemove); packageCard.appendChild(packageTop);
            var packageInfo = document.createElement("div"); packageInfo.className = "st-qty-row";
            var packageItems = document.createElement("span"); packageItems.className = "form-help"; packageItems.textContent = selectedPackage.items;
            var packagePrice = document.createElement("span"); packagePrice.className = "st-price"; packagePrice.textContent = selectedPackage.price;
            packageInfo.appendChild(packageItems); packageInfo.appendChild(packagePrice); packageCard.appendChild(packageInfo);
            disp.appendChild(packageCard);
        }
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
            if ((t.options || []).length > 1) {
                var optionBox = document.createElement("div"); optionBox.className = "detail-option-list";
                t.options.forEach(function(o) {
                    var label = document.createElement("label"); label.className = "detail-option-item";
                    var radio = document.createElement("input"); radio.type = "radio"; radio.name = "detail_option_" + t.name;
                    radio.checked = parseInt(t.option_id, 10) === parseInt(o.id, 10);
                    if (radio.checked) label.classList.add("is-selected");
                    radio.onchange = function() { pickDetailOption(t.name, o.id); };
                    var copy = document.createElement("span"); copy.className = "detail-option-copy";
                    var optionName = document.createElement("span");
                    optionName.className = "detail-option-name";
                    optionName.textContent = o.option_name || (!o.body_part && !o.dosage ? (o.name || "") : "");
                    if (optionName.textContent) copy.appendChild(optionName);
                    if (o.body_part) {
                        var bodyPart = document.createElement("span");
                        bodyPart.className = "detail-option-dosage";
                        bodyPart.textContent = o.body_part;
                        copy.appendChild(bodyPart);
                    }
                    if (o.dosage) {
                        var dosage = document.createElement("span"); dosage.className = "detail-option-dosage"; dosage.textContent = o.dosage;
                        copy.appendChild(dosage);
                    }
                    var optionPrice = document.createElement("span"); optionPrice.className = "detail-option-price";
                    optionPrice.textContent = bookingPriceWon(o.price).toLocaleString("ko-KR") + "원";
                    label.appendChild(radio); label.appendChild(copy); label.appendChild(optionPrice); optionBox.appendChild(label);
                });
                qrow.appendChild(optionBox);
            }
            if (t.price && (t.options || []).length <= 1) { var pEl = document.createElement("span"); pEl.className = "st-price"; pEl.textContent = t.price; qrow.appendChild(pEl); }
            item.appendChild(qrow);
            disp.appendChild(item);
        });
    }

    // The sheet is rendered later in the document, so initialize after parsing.
    // Users may still open "옵션 변경" and add any other treatments.
    document.addEventListener("DOMContentLoaded", function() {
        if (!initialTreatmentName || findTreatment(initialTreatmentName)) return;
        var matchedButton = null;
        document.querySelectorAll("#treatmentSheet .sheet-item:not(.package-item)").forEach(function(el) {
            if (el.getAttribute("data-name") === initialTreatmentName) matchedButton = el;
        });
        if (!matchedButton) return;
        selectedTreatments.push({
            name: initialTreatmentName,
            price: matchedButton.getAttribute("data-price") || "",
            qty: 1,
            min_age: parseInt(matchedButton.getAttribute("data-min-age") || "0", 10) || 0,
            options: JSON.parse(matchedButton.getAttribute("data-options") || "[]")
        });
        var initial = selectedTreatments[0];
        if (initialOptionId > 0) {
            for (var i = 0; i < initial.options.length; i++) {
                if (parseInt(initial.options[i].id, 10) === initialOptionId) {
                    initial.option_id = initial.options[i].id;
                    initial.option_name = initial.options[i].name;
                    initial.price = bookingPriceWon(initial.options[i].price).toLocaleString("ko-KR") + "원";
                    break;
                }
            }
        } else if (initial.options.length === 1) {
            initial.option_id = initial.options[0].id;
            initial.option_name = initial.options[0].name;
        }
        markSheetItem(matchedButton, true);
        renderSelectedTreatments();
    });

    // 날짜/시간/시술이 모두 선택돼야 제출 버튼이 활성 색상이 된다.
    function updateSubmitState() {
        var btn = document.querySelector(".btn-submit[type=submit]");
        if (!btn) return;
        var dateEl = document.getElementById("bookingDate");
        var timeEl = document.getElementById("timeSelect");
        var treatEl = document.getElementById("treatmentInput");
        var detailOptionReady = selectedTreatments.every(function(t) { return !(t.options || []).length || (t.options || []).length === 1 || parseInt(t.option_id, 10) > 0; });
        var ready = !!(dateEl && dateEl.value)
                 && !!(timeEl && timeEl.value)
                 && !!(treatEl && treatEl.value)
                 && detailOptionReady;
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
        var treatment = (selectedPackage ? ["패키지 " + selectedPackage.name] : []).concat(selectedTreatments.map(function(t) {
            return t.name + (t.option_name ? " · " + t.option_name : "");
        })).join(", ");
        document.getElementById("confirmDateTime").textContent = formatBookingDateTime(dateValue, timeValue);
        document.getElementById("confirmTreatment").textContent = treatment;

        var target = new Date(dateValue + "T00:00:00");
        var today = new Date(SERVER_TODAY + "T00:00:00");
        var days = Math.max(0, Math.round((target - today) / 86400000));
        document.getElementById("confirmDday").textContent = "D-" + days;
        validateConfirmPhone(false);
        overlay.classList.add("open");
        overlay.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }
    function closeBookingConfirm() {
        var dupFlag = document.getElementById("confirmDuplicate");
        if (dupFlag) dupFlag.value = "";
        var overlay = document.getElementById("bookingConfirmOverlay");
        overlay.classList.remove("open");
        overlay.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }
    async function submitConfirmedBooking() {
        var form = document.getElementById("bookingForm");
        var button = document.getElementById("confirmBookingButton");
        if (!form || button.disabled) return;
        if (!validateConfirmPhone(true)) return;
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
            if (data && data.code === "noshow_blocked" && data.noshow) {
                closeBookingConfirm();
                button.disabled = false;
                button.textContent = "확인했어요";
                openNoshowSheet(data.noshow);
                return;
            }
            if (data && data.code === "treatment_conflict" && data.conflict) {
                closeBookingConfirm();
                button.disabled = false;
                button.textContent = "확인했어요";
                openConflictSheet(data.conflict);
                return;
            }
            if (data && (data.code === "revisit_blocked" || data.code === "revisit_warning") && data.revisit) {
                closeBookingConfirm();
                button.disabled = false;
                button.textContent = "확인했어요";
                openRevisitSheet(data.revisit, data.code === "revisit_blocked");
                return;
            }
            if (data && (data.code === "duplicate_booking" || data.code === "other_hospital_duplicate") && data.existing) {
                closeBookingConfirm();
                button.disabled = false;
                button.textContent = "확인했어요";
                if (data.code === "duplicate_booking") openDuplicateSheet(data.existing); else openOtherHospitalSheet(data.existing);
                return;
            }
            if (data && (data.code === "age_restricted" || data.code === "age_unknown")) {
                closeBookingConfirm();
                button.disabled = false;
                button.textContent = "확인했어요";
                openAgeRestrictedSheet(parseInt(data.min_age, 10) || 0, data.code === "age_unknown");
                return;
            }
            if (!response.ok || !data.success) throw new Error(data.message || "예약 요청을 처리하지 못했습니다.");

            closeBookingConfirm();
            var bookingsLink = document.getElementById("successBookingsLink");
            if (bookingsLink && data.bookings_url) bookingsLink.href = data.bookings_url;
            window.bookingSuccessMotion.open();
            lockSuccessScreen();
        } catch (error) {
            alert(error.message || "예약 중 오류가 발생했습니다. 다시 시도해주세요.");
            button.disabled = false;
            button.textContent = "확인했어요";
        }
    }
    // [05-13] 확인 화면을 열기 전에 서버에 사전 확인(같은 시술 중복 예약·연령 제한)을 한다.
    // 네트워크 오류 등으로 확인하지 못해도 확인 화면은 연다(최종 접수 때 서버가 다시 검증한다).
    var precheckBusy = false;
    async function precheckThenConfirm() {
        if (precheckBusy) return;
        precheckBusy = true;
        try {
            var form = document.getElementById("bookingForm");
            var fd = new FormData(form);
            fd.set("precheck", "1");
            var response = await fetch(form.getAttribute("action") || window.location.href, {
                method: "POST", body: fd, credentials: "same-origin",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            var data = await response.json();
            if (data && data.code === "noshow_blocked" && data.noshow) { openNoshowSheet(data.noshow); return; }
            if (data && data.code === "treatment_conflict" && data.conflict) { openConflictSheet(data.conflict); return; }
            if (data && data.code === "duplicate_booking" && data.existing) { openDuplicateSheet(data.existing); return; }
            if (data && data.code === "other_hospital_duplicate" && data.existing) { openOtherHospitalSheet(data.existing); return; }
            if (data && data.code === "revisit_blocked" && data.revisit) { openRevisitSheet(data.revisit, true); return; }
            if (data && data.code === "revisit_warning" && data.revisit) { openRevisitSheet(data.revisit, false); return; }
            if (data && (data.code === "age_restricted" || data.code === "age_unknown")) {
                openAgeRestrictedSheet(parseInt(data.min_age, 10) || 0, data.code === "age_unknown");
                return;
            }
        } catch (e) {
        } finally {
            precheckBusy = false;
        }
        if (typeof openPreCheckIfNeeded === "function" && openPreCheckIfNeeded()) return;
        if (typeof afterPreCheck === "function") { afterPreCheck(); return; }
        openBookingConfirm();
    }
    function openDuplicateSheet(existing) {
        document.getElementById("dupTreatment").textContent = (existing.treatment || "") + (existing.hospital_name ? " · " + existing.hospital_name : "");
        document.getElementById("dupSchedule").textContent = (existing.schedule || "") + (existing.status ? " · " + existing.status : "");
        document.getElementById("dupChangeLink").setAttribute("href", "booking_change.php?booking_id=" + (parseInt(existing.id, 10) || 0));
        if (window.bookingDuplicateMotion) window.bookingDuplicateMotion.open();
    }
    function openOtherHospitalSheet(existing) {
        document.getElementById("otherStatus").textContent = existing.status || "";
        document.getElementById("otherTreatment").textContent = (existing.treatment || "") + (existing.hospital_name ? " · " + existing.hospital_name : "");
        document.getElementById("otherSchedule").textContent = existing.schedule || "";
        document.getElementById("otherViewLink").setAttribute("href", "booking_detail.php?booking_id=" + (parseInt(existing.id, 10) || 0));
        if (window.bookingOtherHospitalMotion) window.bookingOtherHospitalMotion.open();
    }
    function bookAnyway() {
        document.getElementById("confirmDuplicate").value = "1";
        var done = function() { precheckThenConfirm(); }; // 다음 확인(재시술 권장 등)이 남았는지 다시 본다
        if (window.bookingDuplicateMotion) window.bookingDuplicateMotion.close().then(done); else done();
    }
    window.bookAnyway = bookAnyway;
    // [05-15/05-16] 재시술 간격 안내. blocked=true 면 예약 불가(확인 버튼만), false 면 권장 시기 아님(취소 / 그래도 예약).
    function koTopic(word) {
        var c = word ? word.charCodeAt(word.length - 1) : 0;
        if (c >= 0xAC00 && c <= 0xD7A3) return word + (((c - 0xAC00) % 28) ? "은" : "는");
        return word + "은(는)";
    }
    function openRevisitSheet(info, blocked) {
        var title = document.getElementById("revisitTitle");
        var desc = document.getElementById("revisitDesc");
        var actions = document.getElementById("revisitActions");
        var proceed = document.getElementById("revisitProceed");
        var cancel = document.getElementById("revisitCancel");
        var interval = info.interval_label || "";
        if (blocked) {
            title.textContent = "아직 예약할 수 없어요";
            desc.innerHTML = "이 시술은 안전을 위해 최소 " + interval + " 간격이 필요해요.<br>" + (info.available_from || "") + "부터 예약할 수 있어요.";
            proceed.style.display = "none";
            cancel.textContent = "확인했어요";
            actions.style.gridTemplateColumns = "1fr";
        } else {
            title.textContent = "아직 재시술 권장 시기가 아니에요";
            desc.innerHTML = koTopic(info.treatment || "이 시술") + " " + interval + " 이상 간격을 권장해요.<br>마지막 시술일로부터 " + (parseInt(info.days_since, 10) || 0) + "일 지났습니다.";
            proceed.style.display = "";
            cancel.textContent = "취소";
            actions.style.gridTemplateColumns = "";
        }
        if (window.bookingRevisitMotion) window.bookingRevisitMotion.open();
    }
    function revisitProceed() {
        document.getElementById("confirmRevisit").value = "1";
        var done = function() { precheckThenConfirm(); };
        if (window.bookingRevisitMotion) window.bookingRevisitMotion.close().then(done); else done();
    }
    window.revisitProceed = revisitProceed;
    function openNoshowSheet(info) {
        var desc = document.getElementById("noshowDesc");
        var list = document.getElementById("noshowList");
        desc.innerHTML = "";
        desc.appendChild(document.createTextNode("최근 " + (parseInt(info.months, 10) || 3) + "개월 동안 " + (info.has_cancel ? "방문하지 않았거나 취소한" : "방문하지 않은") + " 예약이 " + (parseInt(info.count, 10) || 0) + "회 있어요."));
        desc.appendChild(document.createElement("br"));
        desc.appendChild(document.createTextNode((info.available_from || "") + "부터 다시 예약할 수 있어요."));
        list.innerHTML = "";
        (info.items || []).forEach(function (it) {
            var row = document.createElement("div");
            row.className = "ns-row";
            var name = document.createElement("span");
            name.textContent = (it.when || "") + (it.hospital ? " · " + it.hospital : "");
            var tag = document.createElement("span");
            tag.className = "ns-tag";
            tag.textContent = it.kind === "cancel" ? "취소" : "미방문";
            row.appendChild(name);
            row.appendChild(tag);
            list.appendChild(row);
        });
        if (window.bookingNoshowMotion) window.bookingNoshowMotion.open();
    }
    // [05-17] 예약 화면에 들어오자마자 제한 안내를 띄우고, 확인하면 이전 화면으로 돌아간다.
    var NOSHOW_PAGE_BLOCK = ' . json_encode($noshow_page_block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    var NOSHOW_BACK_URL = ' . json_encode($booking_back_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    function noshowConfirm() {
        var go = function() { if (NOSHOW_PAGE_BLOCK) location.href = NOSHOW_BACK_URL; };
        if (window.bookingNoshowMotion) window.bookingNoshowMotion.close().then(go); else go();
    }
    window.noshowConfirm = noshowConfirm;
    if (NOSHOW_PAGE_BLOCK) {
        var showNoshowOnLoad = function() { openNoshowSheet(NOSHOW_PAGE_BLOCK); };
        if (document.readyState === "complete") setTimeout(showNoshowOnLoad, 0);
        else window.addEventListener("load", function() { setTimeout(showNoshowOnLoad, 0); });
    }
    // [05-18] 함께 예약하기 어려운 시술 안내: "하나 빼기" = 시술 선택 시트를 열어 직접 고르게 하고,
    //   "날짜 나누기" = 겹치는 시술 중 첫 번째만 이번 예약에 남기고 나머지는 다른 날짜에 따로 예약하도록 안내한다.
    // [05-19] 시술 전 확인: 선택한 시술에 병원이 적어 둔 질문이 있으면 확인 화면 전에 한 번 보여준다.
    //   모두 선택 사항이다. 답하지 않고 "다음"을 눌러도 되고, 답한 문항만 서버로 보낸다.
    var PRE_QUESTIONS = ' . json_encode((object)$pre_questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    var preAnswers = {};
    var preAskedKey = null;
    function preQuestionList() {
        var out = [];
        selectedTreatments.forEach(function(t) {
            (PRE_QUESTIONS[t.name] || []).forEach(function(q) { if (out.indexOf(q) < 0) out.push(q); });
        });
        return out;
    }
    function openPreCheckIfNeeded() {
        if (CHANGE_MODE) return false;
        var qs = preQuestionList();
        if (!qs.length) { document.getElementById("preAnswers").value = "[]"; return false; }
        var key = JSON.stringify(qs);
        if (preAskedKey === key) return false;
        var list = document.getElementById("preCheckList");
        list.innerHTML = "";
        qs.forEach(function(q) {
            var row = document.createElement("div");
            row.className = "pc-item";
            var text = document.createElement("span");
            text.className = "pc-q";
            text.textContent = q;
            var btns = document.createElement("div");
            btns.className = "pc-btns";
            var yes = document.createElement("button");
            var no = document.createElement("button");
            yes.type = no.type = "button";
            yes.className = no.className = "pc-btn";
            yes.textContent = "예";
            no.textContent = "아니오";
            var paint = function() {
                yes.classList.toggle("on", preAnswers[q] === 1);
                no.classList.toggle("on", preAnswers[q] === 0);
            };
            // 같은 버튼을 다시 누르면 선택이 해제된다(답하지 않음).
            yes.onclick = function() { if (preAnswers[q] === 1) delete preAnswers[q]; else preAnswers[q] = 1; paint(); };
            no.onclick = function() { if (preAnswers[q] === 0) delete preAnswers[q]; else preAnswers[q] = 0; paint(); };
            paint();
            btns.appendChild(yes);
            btns.appendChild(no);
            row.appendChild(text);
            row.appendChild(btns);
            list.appendChild(row);
        });
        var page = document.getElementById("preCheckPage");
        page.classList.add("open");
        page.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        return true;
    }
    function closePreCheckPage() {
        var page = document.getElementById("preCheckPage");
        page.classList.remove("open");
        page.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }
    function preCheckBack() { closePreCheckPage(); }
    function preCheckNext() {
        var qs = preQuestionList();
        var out = [];
        qs.forEach(function(q) { if (preAnswers[q] === 0 || preAnswers[q] === 1) out.push({ q: q, a: preAnswers[q] }); });
        document.getElementById("preAnswers").value = JSON.stringify(out);
        preAskedKey = JSON.stringify(qs);
        closePreCheckPage();
        // [05-20] 하나라도 "예"로 답했으면 의료진 상담 안내를 먼저 보여준다.
        var flagged = out.filter(function(x) { return x.a === 1; });
        if (flagged.length) { openMedConsult(flagged); return; }
        afterPreCheck();
    }
    function openMedConsult(flagged) {
        var list = document.getElementById("medConsultList");
        list.innerHTML = "";
        flagged.forEach(function(x) {
            var d = document.createElement("div");
            d.className = "mc-item";
            d.textContent = x.q;
            list.appendChild(d);
        });
        if (window.bookingMedConsultMotion) window.bookingMedConsultMotion.open();
    }
    function closeMedConsult() { return window.bookingMedConsultMotion ? window.bookingMedConsultMotion.close() : Promise.resolve(); }
    function medConsultOther() {
        preAskedKey = null;
        closeMedConsult().then(function() { openTreatmentSheet(); });
    }
    // "예"로 답한 경우는 의료진 상담 신청이므로, "해당 없음"을 전제로 한 05-21 동의는 건너뛰고 바로 확인 단계로 간다.
    function medConsultProceed() { closeMedConsult().then(openBookingConfirm); }
    window.medConsultOther = medConsultOther;
    window.medConsultProceed = medConsultProceed;
    // [05-21] 시술 전 확인 동의 (예약 1회당 한 번)
    var consentDone = false;
    function afterPreCheck() {
        if (CHANGE_MODE || consentDone) { openBookingConfirm(); return; }
        var rows = document.querySelectorAll("#consentOverlay .cs-row");
        rows.forEach(function(r) { r.classList.remove("on"); });
        document.getElementById("consentGo").disabled = true;
        if (window.bookingConsentMotion) window.bookingConsentMotion.open(); else openBookingConfirm();
    }
    function consentSync() {
        var items = document.querySelectorAll("#consentOverlay .cs-item");
        var all = true;
        items.forEach(function(r) { if (!r.classList.contains("on")) all = false; });
        document.getElementById("consentAll").classList.toggle("on", all);
        document.getElementById("consentGo").disabled = !all;
    }
    function consentToggle(el) { el.classList.toggle("on"); consentSync(); }
    function consentToggleAll() {
        var on = !document.getElementById("consentAll").classList.contains("on");
        document.querySelectorAll("#consentOverlay .cs-item").forEach(function(r) { r.classList.toggle("on", on); });
        consentSync();
    }
    function consentProceed() {
        if (document.getElementById("consentGo").disabled) return;
        consentDone = true;
        var done = window.bookingConsentMotion ? window.bookingConsentMotion.close() : Promise.resolve();
        done.then(openBookingConfirm);
    }
    window.consentToggle = consentToggle;
    window.consentToggleAll = consentToggleAll;
    window.consentProceed = consentProceed;
    window.preCheckBack = preCheckBack;
    window.preCheckNext = preCheckNext;
    var conflictNames = [];
    var conflictPairs = [];
    function conflictsWith(a, b) {
        for (var i = 0; i < conflictPairs.length; i++) {
            var p = conflictPairs[i];
            if ((p[0] === a && p[1] === b) || (p[0] === b && p[1] === a)) return true;
        }
        return false;
    }
    function openConflictSheet(info) {
        conflictNames = (info && info.names) ? info.names.slice() : [];
        conflictPairs = (info && info.pairs) ? info.pairs : [];
        var list = document.getElementById("conflictList");
        list.innerHTML = "";
        conflictNames.forEach(function(n) {
            var row = document.createElement("div");
            row.className = "cf-row";
            var img = document.createElement("img");
            img.src = "assets/icons/icon-booking-blocked.png?v=1";
            img.alt = "";
            var t = document.createElement("span");
            t.style.flexGrow = "1";
            t.textContent = n;
            row.appendChild(img);
            row.appendChild(t);
            list.appendChild(row);
        });
        if (window.bookingConflictMotion) window.bookingConflictMotion.open();
    }
    function closeConflictSheet() {
        return window.bookingConflictMotion ? window.bookingConflictMotion.close() : Promise.resolve();
    }
    function conflictPickOne() {
        closeConflictSheet().then(function() { openTreatmentSheet(); });
    }
    function conflictSplitDates() {
        var names = conflictNames.slice();
        closeConflictSheet().then(function() {
            // 먼저 고른 시술부터 남기고, 이미 남긴 시술과 서로 충돌하는 시술만 이번 예약에서 뺀다.
            var kept = [];
            var removed = [];
            names.forEach(function(n) {
                var clash = kept.some(function(k) { return conflictsWith(k, n); });
                if (clash) removed.push(n); else kept.push(n);
            });
            var keep = kept.join(", ");
            removed.forEach(function(n) { removeTreatment(n); });
            var hint = document.getElementById("splitHint");
            if (!hint) {
                hint = document.createElement("div");
                hint.id = "splitHint";
                hint.className = "split-hint";
                var disp = document.getElementById("selectedTreatmentDisplay");
                disp.parentNode.insertBefore(hint, disp.nextSibling);
            }
            hint.textContent = "\"" + removed.join("\", \"") + "\" 시술은 다른 날짜로 따로 예약해주세요. 이번 예약은 \"" + keep + "\" 으로 진행돼요.";
            if (window.updateSubmitState) window.updateSubmitState();
        });
    }
    window.conflictPickOne = conflictPickOne;
    window.conflictSplitDates = conflictSplitDates;
    function openAgeRestrictedSheet(minAge, unknownAge) {
        var title = document.getElementById("ageRestrictedTitle");
        var desc = document.getElementById("ageRestrictedDesc");
        var link = document.getElementById("ageRestrictedLink");
        if (unknownAge) {
            title.innerHTML = "나이 제한이 있는 시술이에요<br>생년월일 등록이 필요해요";
            desc.innerHTML = (minAge > 0 ? "만 " + minAge + "세 이상만 앱에서 예약할 수 있어요.<br>" : "") + "내 정보에서 생년월일을 등록해주세요.";
            link.textContent = "생년월일 등록";
            link.setAttribute("href", "profile_edit.php");
        } else {
            title.innerHTML = (minAge > 0 ? "만 " + minAge + "세 미만은" : "이 시술은") + "<br>이 시술을 앱에서 예약할 수 없어요";
            desc.innerHTML = "병원 기준에 따라 보호자와 함께<br>병원에서 직접 상담을 받아주세요.";
            link.textContent = "병원에 문의하기";
            link.setAttribute("href", link.getAttribute("data-contact-href") || "#");
        }
        if (window.bookingAgeMotion) window.bookingAgeMotion.open();
    }
    window.closeBookingConfirm = closeBookingConfirm;
    window.submitConfirmedBooking = submitConfirmedBooking;

    function validateConfirmPhone(showError) {
        var input = document.getElementById("confirmPhoneInput");
        var nameInput = document.getElementById("confirmNameInput");
        var error = document.getElementById("confirmPhoneError");
        var button = document.getElementById("confirmBookingButton");
        if (!input) return false;
        var valid = input.value.trim() !== "" && (!nameInput || nameInput.value.trim() !== "");
        input.setAttribute("aria-invalid", valid ? "false" : "true");
        if (error) error.classList.toggle("show", !valid && !!showError);
        if (button) button.disabled = !valid;
        return valid;
    }
    window.validateConfirmPhone = validateConfirmPhone;

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
            link.addEventListener("click", unlockSuccessScreen, { once: true });
        });

        // 모바일 하드웨어 Back/브라우저 Back도 완료 화면을 닫지 못하게 한다.
        history.pushState({ bookingSuccessLocked: true }, "", window.location.href);
        window.addEventListener("popstate", keepBookingSuccessOpen);
        var firstAction = overlay.querySelector(".success-action.primary");
        if (firstAction) firstAction.focus({ preventScroll: true });
    }
    function unlockSuccessScreen() {
        successExitAllowed = true;
        window.removeEventListener("popstate", keepBookingSuccessOpen);
        successLockedElements.forEach(function(item) {
            if (!item.el || !item.el.isConnected) return;
            if (item.tabindex === null) item.el.removeAttribute("tabindex");
            else item.el.setAttribute("tabindex", item.tabindex);
        });
        successLockedElements = [];
    }
    window.unlockSuccessScreen = unlockSuccessScreen;
    function keepBookingSuccessOpen() {
        if (successExitAllowed) return;
        var overlay = document.getElementById("bookingSuccessOverlay");
        if (overlay && overlay.classList.contains("open")) {
            history.pushState({ bookingSuccessLocked: true }, "", window.location.href);
        }
    }

    // [05-42] 예약 변경: 시술 선택 초기 상태 기억(변경했을 때만 new_treatment 전송)
    var changeOrigTreatment = "";
    document.addEventListener("DOMContentLoaded", function() {
        var ti = document.getElementById("treatmentInput");
        changeOrigTreatment = ti ? ti.value : "";
    });
    var changeSubmitting = false;
    function submitBookingChange(treatment) {
        if (changeSubmitting) return;
        changeSubmitting = true;
        var btn = document.querySelector(".btn-submit[type=submit]");
        if (btn) btn.disabled = true;
        var fd = new FormData();
        fd.append("csrf_token", CHANGE_BOOKING.csrf);
        fd.append("booking_id", CHANGE_BOOKING.id);
        fd.append("phone", CHANGE_BOOKING.phone || "");
        fd.append("new_date", document.getElementById("bookingDate").value);
        fd.append("new_time", document.getElementById("timeSelect").value);
        if (treatment && treatment !== changeOrigTreatment) fd.append("new_treatment", treatment);
        fetch("api/edit_booking.php", { method: "POST", body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // [05-43] 성공하면 "예약 변경 요청이 접수되었어요" 시트가 있는 예약 확인 화면으로 이동
                if (data.success) { window.location.replace("booking_request_detail.php?booking_id=" + CHANGE_BOOKING.id + "&changed=1"); return; }
                alert(data.message || "변경 처리 중 오류가 발생했습니다.");
                changeSubmitting = false;
                if (btn) btn.disabled = false;
            })
            .catch(function() {
                alert("네트워크 오류가 발생했습니다. 다시 시도해주세요.");
                changeSubmitting = false;
                if (btn) btn.disabled = false;
            });
    }

    // 폼 유효성 검사 후 서버 제출 대신 최종 확인 화면을 먼저 연다.
    var bookingForm = document.getElementById("bookingForm");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function(e) {
            e.preventDefault();
            var treatment = document.getElementById("treatmentInput").value;
            if (!treatment && !CHANGE_MODE) {
                alert("' . term('service', '시술') . '을 선택해주세요.");
                return false;
            }
            if (selectedTreatments.some(function(t) { return (t.options || []).length > 1 && !parseInt(t.option_id, 10); })) {
                alert("세부 옵션을 선택해주세요.");
                return false;
            }
            var timeSel = document.getElementById("timeSelect");
            if (timeSel && !timeSel.value) {
                alert("시간을 선택해주세요.");
                return false;
            }
            if (CHANGE_MODE) { submitBookingChange(treatment); return false; }
            // [05-12] 나이 제한 시술이면 확인 화면 대신 안내 시트를 보여준다 (서버에서도 다시 검증).
            var ageLimit = 0;
            selectedTreatments.forEach(function(t) {
                if ((t.min_age || 0) > ageLimit && (MEMBER_AGE === null || t.min_age > MEMBER_AGE)) ageLimit = t.min_age;
            });
            if (ageLimit > 0) {
                openAgeRestrictedSheet(ageLimit, MEMBER_AGE === null);
                return false;
            }
            // 새로 누른 예약 신청이므로 이전에 고른 "새로 예약/그래도 예약"은 초기화한다.
            document.getElementById("confirmDuplicate").value = "";
            document.getElementById("confirmRevisit").value = "";
            precheckThenConfirm();
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
            <a href="<?php echo htmlspecialchars($booking_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="back-btn" aria-label="뒤로"><i class="bi bi-chevron-left"></i></a>
            <div class="page-title"><?php echo $is_changing ? '예약 변경' : ($is_rebooking ? ((isset($_GET['via']) && $_GET['via'] === 'again') ? '예약 선택' : '다시 예약') : term('booking', '예약') . '하기'); ?></div>
    </div>

    <!-- 병원 이미지 슬라이더 -->
    <?php if (!$is_rebooking && !empty($images)): ?>
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
            <button class="btn-primary-outline" onclick="location.href='detail.php?id=<?php echo $hospital_id; ?><?php echo $booking_from_wishlist ? '&from=my_wishlist' : ''; ?>'"><?php echo term('business', '병원'); ?> 상세보기</button>
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
    <form method="POST" class="booking-form<?php echo $is_rebooking ? ' rebooking' : ''; ?>" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="response_mode" value="ajax">
        <input type="hidden" name="confirm_duplicate" id="confirmDuplicate" value="">
        <input type="hidden" name="confirm_revisit" id="confirmRevisit" value="">
        <input type="hidden" name="pre_answers" id="preAnswers" value="[]">
        <!-- 예약하기 헤더 + 옵션 변경(시술 선택 시트) -->
        <div class="form-group head-group">
            <div class="section-head-row">
                <span class="section-head-title"><?php echo $is_rebooking ? '예약을 진행해볼까요?' : term('booking', '예약') . '하기'; ?></span>
                <button type="button" class="option-change-btn" onclick="openTreatmentSheet()">
                    옵션 변경
                </button>
            </div>
            <!-- hidden 필드엔 HTML5 required 가 적용되지 않으므로 제거 — 실제 검증은
                 아래 submit 핸들러(JS)와 서버측 empty() 체크가 담당한다. -->
            <input type="hidden" name="treatment" id="treatmentInput">
            <input type="hidden" name="quantity" id="quantityInput" value="1">
            <input type="hidden" name="treatments_json" id="treatmentsJson" value="[]">
            <input type="hidden" name="selection_type" id="selectionTypeInput" value="treatments">
            <input type="hidden" name="package_id" id="packageIdInput" value="0">
            <!-- 선택된 시술 표시 -->
            <div id="selectedTreatmentDisplay" class="selected-treatment" style="display:none;"></div>
            <div id="treatmentPlaceholder" class="form-help"><?php echo $preselected_booking_treatment === null ? '원하는 시술 1개를 선택해주세요.' : '선택한 시술의 세부 옵션을 확인해주세요.'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label date-select-label"><?php echo $is_rebooking ? '언제 방문하시겠어요?' : '날짜 선택'; ?></label>
            <input type="hidden" name="date" id="bookingDate" value="<?php echo $is_changing ? htmlspecialchars($change_booking['date'], ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>">
            <div class="month-selector" id="monthSelector"></div>
            <div class="date-selector" id="dateSelector"></div>
        </div>

        <!-- 예약 시간 -->
        <div class="form-group">
            <label class="form-label date-select-label"><?php echo $is_rebooking ? '예약 시간을 선택해주세요' : '시간 선택'; ?></label>
            <?php if ($is_rebooking): ?><div class="rebook-method"><strong>예약 확정 방식</strong><span>병원에서 확인 후 일정이 확정돼요</span></div><?php endif; ?>
            <!-- [1차 검수 #15 - 2026-05-28] 예약 시간 선택 30분 단위로 세분화 -->
            <!-- select 는 폼 제출/HMS 스케줄용으로 숨김 유지, 실제 표시는 아래 time-grid -->
            <select name="time" id="timeSelect" style="display:none;">
                <option value="">시간을 선택하세요</option>
                <?php for ($slot_minute = 0; $slot_minute < 24 * 60; $slot_minute += 30):
                    $slot_hour = intdiv($slot_minute, 60);
                    $slot_value = sprintf('%02d:%02d', $slot_hour, $slot_minute % 60);
                    $slot_period = $slot_hour < 12 ? '오전' : '오후';
                    $slot_display_hour = $slot_hour % 12 ?: 12;
                ?>
                <option value="<?php echo $slot_value; ?>"><?php echo $slot_period . ' ' . sprintf('%02d:%02d', $slot_display_hour, $slot_minute % 60); ?></option>
                <?php endfor; ?>
            </select>
            <!-- 병원 운영 시간대 (빨강 테두리 박스, 한 줄에 4개) -->
            <div class="time-grid" id="timeGrid"></div>
        </div>

        <!-- 제출 버튼 -->
        <div class="submit-section">
            <button type="submit" class="btn-submit"><?php echo $is_changing ? '예약하기' : ($is_rebooking ? '예약하기' : term('booking', '예약') . ' 신청하기'); ?></button>
        </div>
    </form>

    <!-- [05-19] 시술 전 확인 (선택 사항: 답하지 않고 "다음"으로 넘어갈 수 있다) -->
    <div id="preCheckPage" role="dialog" aria-modal="true" aria-labelledby="preCheckTitle" aria-hidden="true">
        <div class="pc-wrap">
            <div class="pc-head">
                <button type="button" class="pc-back" aria-label="뒤로 가기" onclick="preCheckBack()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></button>
                <div class="pc-head-title">시술 전 확인</div>
                <span></span>
            </div>
            <div class="pc-scroll">
                <h2 class="pc-title" id="preCheckTitle">시술 전에 몇 가지만 확인할게요</h2>
                <p class="pc-desc">답변은 병원 의료진에게만 전달돼요.<br>해당하는 항목이 있으면 의료진 상담 후 예약할 수 있어요.<br>답하기 어려우면 그대로 다음으로 넘어가도 괜찮아요.</p>
                <div class="pc-list" id="preCheckList"></div>
                <button type="button" class="pc-truth" id="preCheckTruth" onclick="this.classList.toggle('on')"><i aria-hidden="true"></i><span>위 내용을 사실대로 답했어요</span></button>
            </div>
            <div class="pc-foot"><button type="button" class="pc-next" onclick="preCheckNext()">다음</button></div>
        </div>
    </div>

    <!-- 1단계: 예약 내용 최종 확인 -->
    <div class="booking-confirm-overlay" id="bookingConfirmOverlay" aria-hidden="true">
        <section class="booking-confirm-page" role="dialog" aria-modal="true" aria-labelledby="bookingConfirmTitle">
            <header class="confirm-page-head">
                <button type="button" class="confirm-back-button" onclick="closeBookingConfirm()" aria-label="뒤로가기">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <h2 class="confirm-page-title" id="bookingConfirmTitle">이대로 예약을 진행할까요?</h2>
                <span class="confirm-page-head-spacer" aria-hidden="true"></span>
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
                <?php if ($booking_member_name === ''): ?>
                <label class="confirm-person-label" for="confirmNameInput">예약자명</label>
                <span class="confirm-phone-field">
                    <input type="text" id="confirmNameInput" name="name" form="bookingForm"
                           class="confirm-phone-input" maxlength="100" required
                           placeholder="예약자명을 입력해주세요" autocomplete="name">
                </span>
                <?php endif; ?>
                <label class="confirm-person-label" for="confirmPhoneInput">연락처</label>
                <span class="confirm-phone-field">
                    <input type="tel" id="confirmPhoneInput" name="phone" form="bookingForm"
                           class="confirm-phone-input" value="<?php echo htmlspecialchars($booking_member_phone); ?>"
                           placeholder="연락처를 입력해주세요" autocomplete="tel" inputmode="tel"
                           aria-describedby="confirmPhoneError" oninput="validateConfirmPhone(false)">
                    <span class="confirm-phone-error" id="confirmPhoneError" role="alert">연락처를 입력해주세요.</span>
                </span>
                <?php if (!$booking_member_has_saved_phone): ?>
                <p class="confirm-phone-help">
                    매번 예약할 때마다 연락처를 입력하고 싶지 않으시면
                    <a href="profile_edit.php">내 정보 수정</a>에서 휴대폰 번호를 미리 등록해 두세요.
                </p>
                <?php endif; ?>
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
    <div class="booking-success-overlay booking-motion-overlay" id="bookingSuccessOverlay" tabindex="-1" aria-hidden="true">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="bookingSuccessTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
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
            </div>
            <div class="success-actions booking-motion-footer">
                <a href="<?php echo htmlspecialchars($booking_success_done_url, ENT_QUOTES, 'UTF-8'); ?>" class="success-action primary">확인했어요</a>
                <a href="my_bookings.php" class="success-action" id="successBookingsLink">내 예약 현황</a>
            </div>
        </section>
    </div>

    <!-- [05-13] 이미 예약된 시술 안내 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="duplicateBookingOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingDuplicateMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="duplicateBookingTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="success-sheet-title" id="duplicateBookingTitle">이미 예약된 시술이 있어요</h2>
            <p class="success-sheet-desc">같은 시술을 한 번 더 예약하시려면<br>“새로 예약”을 선택해 주세요.</p>
            <div class="dup-booking-card">
                <div class="dup-booking-name" id="dupTreatment"></div>
                <div class="dup-booking-when" id="dupSchedule"></div>
            </div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action primary" onclick="bookAnyway()">새로 예약</button>
                <a href="my_bookings.php" id="dupChangeLink" class="success-action">기존 예약 변경</a>
            </div>
        </section>
    </div>

    <!-- [05-15/05-16] 재시술 간격 안내 시트 (권장: 취소/그래도 예약, 필수: 확인했어요) -->
    <div class="booking-success-overlay booking-motion-overlay" id="revisitOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingRevisitMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="revisitTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="success-sheet-title" id="revisitTitle">아직 재시술 권장 시기가 아니에요</h2>
            <p class="success-sheet-desc" id="revisitDesc"></p>
            </div>
            <div class="success-actions booking-motion-footer" id="revisitActions">
                <button type="button" class="success-action" id="revisitCancel" onclick="window.bookingRevisitMotion && window.bookingRevisitMotion.close()">취소</button>
                <button type="button" class="success-action primary" id="revisitProceed" onclick="revisitProceed()">그래도 예약</button>
            </div>
        </section>
    </div>

    <!-- [05-14] 다른 병원에 같은 시술 예약이 있는 경우 안내 시트(예약 불가) -->
    <div class="booking-success-overlay booking-motion-overlay" id="otherHospitalOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingOtherHospitalMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="otherHospitalTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="success-sheet-title" id="otherHospitalTitle">다른 병원에 같은 시술<br>예약이 있어요</h2>
            <p class="success-sheet-desc">같은 시술은 한 번에 한 병원만 예약할 수 있어요.<br>기존 예약을 취소한 뒤 다시 예약해주세요.</p>
            <div class="dup-booking-card">
                <div class="dup-booking-badges"><span class="dup-badge on" id="otherStatus"></span><span class="dup-badge">다른 병원</span></div>
                <div class="dup-booking-name" id="otherTreatment"></div>
                <div class="dup-booking-when" id="otherSchedule"></div>
            </div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action primary" onclick="window.bookingOtherHospitalMotion && window.bookingOtherHospitalMotion.close()">확인했어요</button>
                <a href="my_bookings.php" id="otherViewLink" class="success-action">기존 예약 보기</a>
            </div>
        </section>
    </div>

    <!-- [05-18] 같은 날 함께 예약하기 어려운 시술 안내 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="conflictOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingConflictMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="conflictTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <img class="ns-icon" src="assets/icons/icon-booking-blocked.png?v=1" alt="">
            <h2 class="success-sheet-title" id="conflictTitle">같은 날 함께 예약하기<br>어려운 시술이 있어요</h2>
            <p class="success-sheet-desc">병원 기준에 따라 한 가지만 예약하거나<br>날짜를 나눠서 예약해주세요.</p>
            <div id="conflictList"></div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action" onclick="conflictPickOne()">하나 빼기</button>
                <button type="button" class="success-action primary" onclick="conflictSplitDates()">날짜 나누기</button>
            </div>
        </section>
    </div>

    <!-- [05-20] 의료진 상담 후 예약 안내 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="medConsultOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingMedConsultMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="medConsultTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <img class="ns-icon" src="assets/icons/icon-booking-blocked.png?v=1" alt="">
            <h2 class="success-sheet-title" id="medConsultTitle">의료진 상담 후<br>예약할 수 있어요</h2>
            <p class="success-sheet-desc">답변 내용을 병원에 먼저 전달할게요.<br>상담 후 병원에서 예약 가능 여부를 알려드려요.</p>
            <div class="mc-label">해당 항목</div>
            <div id="medConsultList"></div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action" onclick="medConsultOther()">다른 시술 보기</button>
                <button type="button" class="success-action primary" onclick="medConsultProceed()">상담 신청하기</button>
            </div>
        </section>
    </div>

    <!-- [05-21] 시술 전 확인 동의 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="consentOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingConsentMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="consentTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="success-sheet-title" id="consentTitle">시술 전 확인해 주세요</h2>
            <div class="cs-list">
                <button type="button" class="cs-row all" id="consentAll" onclick="consentToggleAll()"><i aria-hidden="true"></i><span>전체동의</span></button>
                <button type="button" class="cs-row cs-item" onclick="consentToggle(this)"><i aria-hidden="true"></i><span>임신·수유 중이 아니며, 최근 2주 내 관련 시술을 받지 않았습니다. <span class="cs-req">(필수)</span></span></button>
                <button type="button" class="cs-row cs-item" onclick="consentToggle(this)"><i aria-hidden="true"></i><span>복용 중인 약물·알레르기를 상담 시 알리겠습니다. <span class="cs-req">(필수)</span></span></button>
                <div>
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <button type="button" class="cs-row cs-item" onclick="consentToggle(this)"><i aria-hidden="true"></i><span>시술 전후 주의사항을 확인했습니다. <span class="cs-req">(필수)</span></span></button>
                        <button type="button" class="cs-view" onclick="document.getElementById('consentNote').classList.toggle('open')">보기</button>
                    </div>
                    <p class="cs-note" id="consentNote">시술 전후 주의사항은 상담 시 병원에서 자세히 안내해 드려요.</p>
                </div>
            </div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action primary" id="consentGo" disabled onclick="consentProceed()">동의하고 예약하기</button>
            </div>
        </section>
    </div>

    <!-- [05-17] 노쇼 누적으로 예약 불가 안내 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="noshowOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingNoshowMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="noshowTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <img class="ns-icon" src="assets/icons/icon-booking-blocked.png?v=1" alt="">
            <h2 class="success-sheet-title" id="noshowTitle">지금은 예약할 수 없어요</h2>
            <p class="success-sheet-desc" id="noshowDesc"></p>
            <div id="noshowList"></div>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action primary" onclick="noshowConfirm()">확인했어요</button>
            </div>
        </section>
    </div>

    <!-- [05-12] 나이 제한 시술 안내 시트 -->
    <div class="booking-success-overlay booking-motion-overlay" id="ageRestrictedOverlay" tabindex="-1" aria-hidden="true"
         data-booking-motion-sheet data-booking-motion-name="bookingAgeMotion">
        <section class="booking-success-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="ageRestrictedTitle">
            <button type="button" class="success-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="success-sheet-title" id="ageRestrictedTitle">만 19세 미만은<br>이 시술을 앱에서 예약할 수 없어요</h2>
            <p class="success-sheet-desc" id="ageRestrictedDesc">병원 기준에 따라 보호자와 함께<br>병원에서 직접 상담을 받아주세요.</p>
            </div>
            <div class="success-actions booking-motion-footer">
                <button type="button" class="success-action primary" onclick="window.bookingAgeMotion && window.bookingAgeMotion.close()">확인했어요</button>
                <a href="detail.php?id=<?php echo (int)$hospital_id; ?>" data-contact-href="detail.php?id=<?php echo (int)$hospital_id; ?>" id="ageRestrictedLink" class="success-action">병원에 문의하기</a>
            </div>
        </section>
    </div>

    <!-- 시술 선택 시트 (옵션 변경) -->
    <?php
    // 시트에 표시할 시술 목록 구성 (신 시스템 우선 → 태그 → 기본값)
    $sheet_items = [];
    if (!empty($booking_treatments)) {
        foreach ($booking_treatments as $bt) {
            // 과거 만원 가격과 신규 원 가격을 모두 원 단위로 표시한다.
            $pl = '';
            if (!empty($bt['price_min']) && $bt['price_min'] > 0) {
                $bt_min_won = booking_price_won($bt['price_min']);
                $bt_max_won = booking_price_won($bt['price_max']);
                $pl = ($bt['price_max'] > $bt['price_min'])
                    ? number_format($bt_min_won) . '~' . number_format($bt_max_won) . '원'
                    : number_format($bt_min_won) . '원';
            }
            $sheet_items[] = ['name' => $bt['name'], 'price' => $pl, 'options' => $bt['options'] ?? [], 'min_age' => (int)($bt['min_age'] ?? 0)];
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
    $preselected_sheet_item = null;
    if ($preselected_booking_treatment !== null) {
        $preselected_name = (string)($preselected_booking_treatment['name'] ?? '');
        foreach ($sheet_items as $sheet_item_index => $sheet_item) {
            if ((string)($sheet_item['name'] ?? '') === $preselected_name) {
                $preselected_sheet_item = $sheet_item;
                unset($sheet_items[$sheet_item_index]);
                break;
            }
        }
        $sheet_items = array_values($sheet_items);
    }
    if ($preselected_sheet_item !== null) array_unshift($sheet_items, $preselected_sheet_item);
    $sheet_packages = $booking_packages;
    ?>
    <div class="treatment-sheet-overlay booking-motion-overlay" id="treatmentSheet" tabindex="-1">
        <div class="treatment-sheet booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="treatmentSheetTitle">
            <button type="button" class="sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
            <div class="booking-motion-body">
            <h2 class="sheet-title" id="treatmentSheetTitle">옵션 선택</h2>
            <div class="sheet-list" role="group" aria-labelledby="treatmentSheetTitle">
                <?php if (!empty($sheet_packages)): ?>
                <div class="sheet-section-title">패키지 중 1개 선택</div>
                <?php foreach ($sheet_packages as $bp): ?>
                <button type="button" class="sheet-item package-item"
                        data-package-id="<?php echo (int)$bp['id']; ?>"
                        data-name="<?php echo htmlspecialchars($bp['name'], ENT_QUOTES); ?>"
                        data-price="<?php echo number_format((int)$bp['package_price'] * 10000); ?>원"
                        data-items="<?php echo htmlspecialchars($bp['item_names'] ?? '', ENT_QUOTES); ?>"
                        aria-pressed="false"
                        onclick="pickPackage(this)">
                    <span class="sheet-item-name"><?php echo htmlspecialchars($bp['name']); ?></span>
                    <span class="sheet-item-foot">
                        <span class="sheet-item-desc"><?php echo htmlspecialchars(($bp['item_names'] ?? '') ?: ($bp['description'] ?? '')); ?></span>
                        <span class="sheet-item-price">
                            <?php if ((int)$bp['original_price'] > (int)$bp['package_price']): ?>
                            <span class="price-was"><?php echo number_format((int)$bp['original_price'] * 10000); ?>원</span>
                            <?php endif; ?>
                            <span class="price-now"><?php echo number_format((int)$bp['package_price'] * 10000); ?><span class="price-unit">원</span></span>
                        </span>
                    </span>
                </button>
                <?php endforeach; ?>
                <div class="sheet-choice-divider"><span>개별 시술도 추가할 수 있어요</span></div>
                <?php endif; ?>
                <div class="sheet-section-title">개별 시술 선택</div>
                <?php foreach ($sheet_items as $it): ?>
                <button type="button" class="sheet-item" data-treatment-choice
                        data-name="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"
                        data-price="<?php echo htmlspecialchars($it['price'], ENT_QUOTES); ?>"
                        data-options="<?php echo htmlspecialchars(json_encode($it['options'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
                        data-min-age="<?php echo (int)($it['min_age'] ?? 0); ?>"
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
            </div>
            <div class="sheet-footer booking-motion-footer">
                <button type="button" class="sheet-confirm" onclick="closeTreatmentSheet()">선택 완료</button>
            </div>
        </div>
    </div>


    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/booking-sheets.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/booking-sheets.css'); ?>">
    <script src="assets/js/booking-sheets.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/js/booking-sheets.js'); ?>"></script>

<?php
// ========================================
// 공유 푸터 포함 (main-content 닫기, bottom_navigation, closing tags)
// ========================================
if ($booking_from_wishlist) $bottom_nav_active = 'my';
include __DIR__ . '/includes/footer.php';

if (isDevMode($connect)) { echo getDevModeJS_booking(); }

mysqli_close($connect);
?>

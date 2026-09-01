<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
$booking_from_wishlist = isset($_GET['from']) && $_GET['from'] === 'my_wishlist';

function booking_price_won($price) {
    return intval($price);
}

require_once __DIR__ . '/includes/infyhms_api.php';

$is_admin = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
if (function_exists('getSettingValue')) {
    $_require_login = getSettingValue($connect, 'booking_require_login', '1') === '1';
    $_phone_auth    = getSettingValue($connect, 'booking_phone_auth', '0') === '1';
    $_is_member = function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in();
    if ($_require_login && !$_phone_auth && !$_is_member && !$is_admin) {
        $login_return_url = '/hairwang/gangnamunni/booking.php?id=' . (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (isset($_GET['treatment_id'])) $login_return_url .= '&treatment_id=' . max(0, (int)$_GET['treatment_id']);
        if (isset($_GET['option_id'])) $login_return_url .= '&option_id=' . max(0, (int)$_GET['option_id']);
        if (isset($_GET['date'])) $login_return_url .= '&date=' . urlencode((string)$_GET['date']);
        if (isset($_GET['time'])) $login_return_url .= '&time=' . urlencode((string)$_GET['time']);
        if (isset($_GET['quantity'])) $login_return_url .= '&quantity=' . max(1, (int)$_GET['quantity']);
        if (isset($_GET['treatment'])) $login_return_url .= '&treatment=' . urlencode(trim((string)$_GET['treatment']));
        if (isset($_GET['rebook']) && $_GET['rebook'] === '1') $login_return_url .= '&rebook=1';
        if (isset($_GET['source_booking_id'])) $login_return_url .= '&source_booking_id=' . max(0, (int)$_GET['source_booking_id']);
        if ($booking_from_wishlist) $login_return_url .= '&from=my_wishlist';
        header('Location: auth/login.php?url=' . urlencode($login_return_url));
        exit;
    }
}

$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_rebooking = isset($_GET['rebook']) && $_GET['rebook'] === '1';
$source_booking_id = max(0, (int)($_GET['source_booking_id'] ?? 0));

if ($hospital_id <= 0) {
    header('Location: index.php');
    exit;
}

$query = "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die(term("business") . " 정보를 찾을 수 없습니다.");
}

$hospital = mysqli_fetch_assoc($result);

$booking_business_hours = json_decode($hospital['business_hours'] ?? '{}', true);
if (!is_array($booking_business_hours)) $booking_business_hours = [];
$booking_special_closed_dates = array_values(array_filter(
    is_array($booking_business_hours['special_closed_dates'] ?? null) ? $booking_business_hours['special_closed_dates'] : [],
    static function($date) { return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date); }
));

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
    if (preg_match('/^(\d{1,2}):(\d{2})\s*[~\-]\s*(\d{1,2}):(\d{2})$/u', $booking_day_value, $booking_hours_match)) {
        $booking_open_min = (int)$booking_hours_match[1] * 60 + (int)$booking_hours_match[2];
        $booking_close_min = (int)$booking_hours_match[3] * 60 + (int)$booking_hours_match[4];
        $booking_weekly_hours[$booking_day_number] = ($booking_open_min < $booking_close_min)
            ? ['open' => $booking_open_min, 'close' => $booking_close_min]
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

if (empty($images) && !empty($hospital['image_url'])) {
    $images[] = $hospital['image_url'];
}

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

$booking_submitted = false;
$booking_error = '';
$hms_appointment_id = null;  
$hms_api_success = false;    
$is_ajax_booking = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['response_mode'] ?? '') === 'ajax';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_string = static function (string $key, string $default = ''): string {
        $value = $_POST[$key] ?? $default;
        return is_string($value) || is_numeric($value) ? trim(stripslashes((string)$value)) : $default;
    };
    $name = $post_string('name');
    $phone = $post_string('phone');
    $treatment = $post_string('treatment');
    $date = $post_string('date');
    $time = $post_string('time');
    $memo = $post_string('memo');
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    $valid_booking_time_format = preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $time) === 1;
    $booking_time_parts = $valid_booking_time_format ? explode(':', $time) : [];
    $booking_time_min = $valid_booking_time_format ? ((int)$booking_time_parts[0] * 60 + (int)$booking_time_parts[1]) : null;
    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $valid_date = $date_object && $date_object->format('Y-m-d') === $date;
    $max_booking_date = new DateTimeImmutable('+1 year');

    if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
        $lm = get_gangnamunni_member();
        $name = trim((string)($lm['mb_name'] ?? ''));
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
        $booking_error = '선택할 수 없는 예약 시간입니다.';
    } elseif (in_array($date, $booking_special_closed_dates, true)) {
        $booking_error = '선택한 날짜는 임시 휴무일입니다. 다른 날짜를 선택해주세요.';
    } elseif ($booking_has_weekly_hours && empty($booking_weekly_hours[(int)$date_object->format('w')])) {
        $booking_error = '선택한 날짜는 휴무일입니다. 다른 날짜를 선택해주세요.';
    } elseif ($booking_has_weekly_hours
        && (($booking_selected_hours = $booking_weekly_hours[(int)$date_object->format('w')] ?? null) !== null)
        && ($booking_time_min < $booking_selected_hours['open'] || $booking_time_min >= $booking_selected_hours['close'])) {
        $booking_error = '선택한 시간은 병원 진료시간이 아닙니다. 다른 시간을 선택해주세요.';
    } elseif (!$booking_no_lunch && $booking_lunch_start_min !== null && $booking_lunch_end_min !== null
        && $booking_time_min >= $booking_lunch_start_min && $booking_time_min < $booking_lunch_end_min) {
        $booking_error = '점심시간에는 ' . term('booking', '예약') . '할 수 없습니다. 다른 시간을 선택해주세요.';
    } elseif (($booking_ts = strtotime($date . ' ' . $time)) === false || $booking_ts < time()) {
        $booking_error = '지난 시간은 ' . term('booking', '예약') . '할 수 없습니다. 다른 시간을 선택해주세요.';
    } else {
        $booking_mb_id = '';
        if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
            $bm = get_gangnamunni_member();
            $booking_mb_id = $bm['mb_id'] ?? '';
        }

        $selection_type = $post_string('selection_type', 'treatments') === 'package' ? 'package' : 'treatments';
        $selected_package_id = max(0, (int)($_POST['package_id'] ?? 0));
        $treatments_json = $post_string('treatments_json', '[]');
        $treatments_arr = json_decode($treatments_json, true);
        if (!is_array($treatments_arr)) $treatments_arr = [];
        if (count($treatments_arr) > 20) {
            $booking_error = '시술은 한 번에 최대 20개까지 선택할 수 있습니다.';
            $treatments_arr = [];
        }
        $requested_individual_treatments = $treatments_arr;
        $package_treatments = [];

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
        $treatment = mb_substr($treatment, 0, 100);

        if ($booking_error === '') {
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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
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

            notifyAdmins($connect, 'booking',
                '새 예약 접수',
                htmlspecialchars($hospital['name']) . ' — ' . htmlspecialchars($name) . ' (' . $date . ' ' . $time . ')',
                'admin_bookings.php',
                ['booking_id' => $booking_id, 'hospital_id' => $hospital_id]
            );

            if (!empty($hospital['hms_hospital_id'])) {
                try {
                    $hms_api = get_infyhms_api();
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

                    if (!empty($hms_result['success']) && !empty($hms_result['data']['id'])) {
                        $hms_appointment_id = (int) $hms_result['data']['id'];
                        $hms_api_success = true;
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
        'message' => $booking_error !== '' ? $booking_error : term('booking', '예약') . ' 요청을 처리하지 못했습니다.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$booking_treatments = [];
$bt_sql = "SELECT ht.id AS hospital_treatment_id, tm.id AS treatment_id, tm.name AS name, ht.price_min, ht.price_max,
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
        ];
    }
    mysqli_stmt_close($bt_stmt);
}

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
        break;
    }
}

$booking_back_url = 'detail.php?id=' . (int)$hospital_id;
if ($booking_from_wishlist) {
    $booking_back_url .= '&from=my_wishlist';
}
if ($is_rebooking && $source_booking_id > 0) {
    $booking_back_url = 'booking_request_detail.php?booking_id=' . $source_booking_id;
}
if ($preselected_booking_treatment !== null) {
    if (!$is_rebooking) {
        $booking_back_url .= '&treatment_id=' . (int)$preselected_booking_treatment['treatment_id']
            . '&treatment=' . urlencode($preselected_booking_treatment['name'])
            . '#matched-treatment';
    }
}

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

if (empty($treatment_tags) || !is_array($treatment_tags)) {
    $treatment_tags = [];
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

$page_title = htmlspecialchars($hospital['name']) . ' ' . term('appointment');
$show_search = false;

$page_css = '
    @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap");

    body,
    button,
    input,
    select,
    textarea {
        font-family: "Noto Sans KR", sans-serif !important;
    }

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

    .error-message {
        background: #FFEBEE;
        border-left: 4px solid #F44336;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #C62828;
    }

    .booking-form {
        padding: 15px;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group.head-group {
        margin-bottom: 32px;
    }

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

    .form-label.date-select-label {
        font-size: 17px;
        font-weight: 700;
    }
    .rebook-method { display:flex; flex-direction:column; gap:7px; margin-top:14px; }
    .rebook-method strong { font-size:15px; }
    .rebook-method span { color:#AE1932; font-size:13px; }
    .booking-form.rebooking { padding-top:22px; }
    .booking-form.rebooking .section-head-title,
    .booking-form.rebooking .form-label.date-select-label { font-size:18px; }
    .booking-form.rebooking .form-group { padding:0 4px 28px; margin:0 0 8px; border-bottom:8px solid #f4f4f4; }
    .booking-form.rebooking .form-group:last-of-type { border-bottom:0; }
    .booking-form.rebooking .submit-section { padding:0 4px 18px; }

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
    .date-selector-tail {
        flex: 0 0 calc(100% - 46px);
        width: 0;
        height: 1px;
        pointer-events: none;
    }
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

    .date-chip .dc-wd { font-size: 12px; color: #9aa0a6; }
    .date-chip .dc-day {
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; font-weight: 700;
        color: var(--bs-body-color, #1f2937);
        transition: background 0.15s, color 0.15s;
    }
    .date-chip:hover .dc-day { background: #f2f2f2; }
    .date-chip.active .dc-day { background: var(--color-primary, #AE1932); color: #fff; }
    .date-chip .dc-today { font-size: 12px; font-weight: 600; color: #aaaaaa; margin-top: -1px; }

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

    .selected-treatment {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 12px;
    }
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
        color: #AE1932;
        cursor: pointer;
    }
    .qty-btn:hover { background: #faf0f2; }
    .qty-btn--plus { color: #1A1A1A; }
    .qty-btn--minus.is-off { color: #BBBBBB; }
    .qty-btn--minus.is-off:hover { background: var(--bs-body-bg, #fff); }
    .qty-num { min-width: 40px; text-align: center; font-weight: 700; font-size: 15px; }

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
    .package-item { background: rgba(174, 25, 50, .025); }
    .inline-treatment-list { display:grid; gap:10px; margin-top:12px; }
    .inline-treatment-choice { display:flex; align-items:center; gap:10px; padding:14px; border:1px solid #e6e6e6; border-radius:12px; cursor:pointer; background:#fff; }
    .inline-treatment-choice.selected { border-color:#ae1932; background:#fff7f8; }
    .inline-treatment-choice input { accent-color:#ae1932; width:18px; height:18px; }
    .inline-treatment-choice .choice-price { margin-left:auto; font-weight:700; }
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
        border-color:#ae1932;
        background:#fff8f9;
        box-shadow:0 0 0 1px rgba(174,25,50,.06);
    }
    .detail-option-item input {
        width:18px;
        height:18px;
        margin:0;
        accent-color:#ae1932;
    }
    .detail-option-copy { min-width:0; display:flex; align-items:baseline; gap:6px; }
    .detail-option-name { color:#20242b; font-size:15px; font-weight:600; }
    .detail-option-dosage { color:#8b9199; font-size:12px; }
    .detail-option-price { color:#20242b; font-size:15px; font-weight:700; white-space:nowrap; }
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
    .price-was { font-size: 12px; color: #9A9A9A; text-decoration: line-through; }
    .price-off { font-size: 14px; font-weight: 700; color: #1A1A1A; }
    .price-now { font-size: 17px; font-weight: 700; color: #AE1932; }
    .price-now .price-unit { font-size: 14px; }

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


    .btn-submit.is-incomplete,
    .btn-submit.is-incomplete:hover {
        background: #F2F2F2;
        color: #BBBBBB;
    }

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

    .main-content { background: #f2f2f2; }
    .page-header {
        height: 54px; min-height: 54px; padding: 0 20px;
        background: #fff; border-bottom: 1px solid #ededed;
        box-shadow: none;
    }
    .page-title { font-size: 16px; font-weight: 500; }
    .hospital-images-section { display: none; }
    .booking-form { padding: 0; background: #f2f2f2; }
    .booking-form .form-group {
        margin: 0; padding: 20px;
        background: #fff; border-bottom: 8px solid #f2f2f2;
    }
    .booking-form .form-group.head-group { margin: 0; padding-top: 14px; padding-bottom: 22px; }
    .section-head-row { padding-bottom: 14px; }
    .section-head-title, .form-label.date-select-label { font-size: 17px; font-weight: 700; }
    .option-change-btn { min-width: 78px; justify-content: center; padding: 8px 13px; border: 0; }
    .selected-treatment { gap: 0; margin-top: 0; }
    .st-item { gap: 8px; padding: 17px 0; border-bottom: 1px solid #e5e5e5; }
    .st-item:last-child { border-bottom: 0; padding-bottom: 0; }
    .selected-treatment .st-name { font-size: 14px; color: #111; }
    .selected-treatment .st-price { font-size: 14px; }
    .qty-stepper { height: 28px; }
    .qty-btn { width: 30px; height: 26px; font-size: 18px; }
    .qty-num { min-width: 34px; font-size: 14px; }
    .month-selector { margin: 10px 0 12px; padding-bottom: 10px; border-bottom: 1px solid #aaa; }
    .month-item { font-size: 14px; }
    .date-selector { margin-top: -12px; border-top: 0; border-bottom: 0; padding: 10px 0 0; }
    .date-chip { width: 48px; }
    .date-chip .dc-day { width: 30px; height: 30px; font-size: 14px; }
    .rebook-method { margin-top: 12px; gap: 5px; }
    .rebook-method strong { font-size: 14px; }
    .rebook-method span { font-size: 12px; }
    .time-grid { gap: 16px 24px; margin-top: 16px; }
    .time-slot { padding: 7px 4px; font-size: 13px; font-weight: 500; }
    .submit-section { margin: 0; padding: 20px 20px 18px; background: #fff; }
    .btn-submit { height: 48px; padding: 0 16px; border-radius: 0; font-size: 15px; }

    .treatment-sheet-overlay { background: rgba(0,0,0,.58); }
    .treatment-sheet {
        max-height: 54vh; min-height: 48vh; padding: 10px 20px calc(20px + env(safe-area-inset-bottom, 0px));
        border-radius: 0;
    }
    .sheet-handle {
        width: 34px; height: 4px; margin: 2px auto 12px;
        cursor: grab; touch-action: none; user-select: none;
    }
    .sheet-handle:active { cursor: grabbing; }
    .treatment-sheet.is-dragging { animation: none; transition: none; }
    .treatment-sheet.is-snapping { transition: transform .22s ease-out; }
    .sheet-title { margin-bottom: 12px; font-size: 14px; font-weight: 600; }
    .sheet-list { gap: 14px; margin-bottom: 14px; }
    .sheet-section-title { display: none; }
    .sheet-choice-divider { display: none; }
    .sheet-item { padding: 13px 10px; border-radius: 3px; background: #fff; }
    .sheet-item.selected { border: 2px solid #AE1932; box-shadow: none; padding: 12px 9px; }
    .sheet-item-name { margin-bottom: 8px; font-size: 14px; line-height: 1.45; }
    .sheet-item-desc { font-size: 12px; }
    .sheet-item-price { gap: 8px; }
    .price-now { font-size: 15px; }
    .sheet-confirm { height: 48px; padding: 0 16px; border-radius: 0; font-size: 14px; }

    @media (max-width: 420px) {
        .booking-form .form-group { padding-left: 14px; padding-right: 14px; }
        .time-grid { gap: 14px 18px; }
        .treatment-sheet { padding-left: 14px; padding-right: 14px; }
    }
';

$page_js = '
    var HAS_WEEKLY_HOURS = ' . ($booking_has_weekly_hours ? 'true' : 'false') . ';
    var WEEKLY_HOURS = ' . json_encode($booking_weekly_hours, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
    var SPECIAL_CLOSED_DATES = ' . json_encode($booking_special_closed_dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';

    function initDatePicker() {
        var hidden = document.getElementById("bookingDate");
        var monthSel = document.getElementById("monthSelector");
        var dateSel = document.getElementById("dateSelector");
        if (!hidden || !monthSel || !dateSel) return;

        var weekdays = ["일","월","화","수","목","금","토"];
        var today = new Date(' . date('Y') . ', ' . (date('n') - 1) . ', ' . date('j') . '); today.setHours(0,0,0,0);
        var endDate = new Date(today); endDate.setMonth(endDate.getMonth() + 1);
        var dates = [];
        var cur = new Date(today);
        while (cur < endDate) {
            var curValue = cur.getFullYear() + "-" + (cur.getMonth() + 1 < 10 ? "0" : "") + (cur.getMonth() + 1) + "-" + (cur.getDate() < 10 ? "0" : "") + cur.getDate();
            if ((!HAS_WEEKLY_HOURS || WEEKLY_HOURS[cur.getDay()]) && SPECIAL_CLOSED_DATES.indexOf(curValue) < 0) dates.push(new Date(cur));
            cur.setDate(cur.getDate() + 1);
        }
        var months = [];
        dates.forEach(function(d) { var k = d.getFullYear() + "-" + (d.getMonth() + 1); if (months.indexOf(k) < 0) months.push(k); });
        var selectedMonth = months[0];

        function pad(n) { return n < 10 ? "0" + n : "" + n; }
        function fmt(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }

        if (dates.length && !dates.some(function(d) { return fmt(d) === hidden.value; })) hidden.value = fmt(dates[0]);

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
                chip.appendChild(wd);   
                chip.appendChild(day);  
                if (val === fmt(today)) { 
                    var todayLbl = document.createElement("span");
                    todayLbl.className = "dc-today";
                    todayLbl.textContent = "오늘";
                    chip.appendChild(todayLbl);
                }
                chip.onclick = function() {
                    hidden.value = val;
                    dateSel.querySelectorAll(".date-chip").forEach(function(c){ c.classList.remove("active"); });
                    chip.classList.add("active");
                    if (window.buildTimeGrid) window.buildTimeGrid();
                    if (window.updateSubmitState) window.updateSubmitState();
                };
                dateSel.appendChild(chip);
            });
            if (months.length > 1) {
                var tail = document.createElement("span");
                tail.className = "date-selector-tail";
                tail.setAttribute("aria-hidden", "true");
                dateSel.appendChild(tail);
            }
        }
        function scrollToMonth(monthKey) {
            var target = dateSel.querySelector(".date-chip[data-month=\"" + monthKey + "\"]");
            if (!target) return;
            selectedMonth = monthKey;
            renderMonths();
            var targetLeft = target.getBoundingClientRect().left - dateSel.getBoundingClientRect().left + dateSel.scrollLeft;
            dateSel.scrollTo({ left: targetLeft, behavior: "auto" });
        }
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
    }
    initDatePicker();
    var PRESELECT_TIME = ' . json_encode(preg_match('/^\d{2}:\d{2}$/', (string)($_GET['time'] ?? '')) ? (string)$_GET['time'] : '') . ';
    if (PRESELECT_TIME) setTimeout(function(){
        var slots = document.querySelectorAll("#timeGrid .time-slot");
        for (var i = 0; i < slots.length; i++) {
            if (slots[i].textContent.trim() === PRESELECT_TIME && !slots[i].classList.contains("disabled")) { slots[i].click(); break; }
        }
    }, 400);

    var SERVER_TODAY = "' . date('Y-m-d') . '";
    var SERVER_NOW_MIN = ' . (date('G') * 60 + (int)date('i')) . ';
    var LUNCH_START_MIN = ' . ($booking_lunch_start_min === null ? 'null' : (int)$booking_lunch_start_min) . ';
    var LUNCH_END_MIN = ' . ($booking_lunch_end_min === null ? 'null' : (int)$booking_lunch_end_min) . ';

    function buildTimeGrid() {
        var sel = document.getElementById("timeSelect");
        var grid = document.getElementById("timeGrid");
        if (!sel || !grid) return;
        grid.innerHTML = "";
        var selDate = (document.getElementById("bookingDate") || {}).value || "";
        var isToday = (selDate === SERVER_TODAY);
        var selectedDay = selDate ? new Date(selDate + "T00:00:00").getDay() : -1;
        var dayHours = HAS_WEEKLY_HOURS && selectedDay >= 0 ? WEEKLY_HOURS[selectedDay] : null;
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
            var disabled = sel.options[i].disabled;
            var label = sel.options[i].textContent || value;
            var hm = value.split(":");
            var slotMin = parseInt(hm[0], 10) * 60 + parseInt(hm[1], 10);
            if (dayHours && (slotMin < dayHours.open || slotMin >= dayHours.close)) continue;
            if (!disabled && isToday) {
                if (slotMin <= SERVER_NOW_MIN) { disabled = true; }
            }
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
    window.buildTimeGrid = buildTimeGrid; 

    function openTreatmentSheet() {
        var s = document.getElementById("treatmentSheet");
        if (!s) return;
        s.querySelectorAll(".sheet-item:not(.package-item)").forEach(function(el) {
            markSheetItem(el, !!findTreatment(el.getAttribute("data-name")));
        });
        s.querySelectorAll(".package-item").forEach(function(el) {
            markSheetItem(el, !!selectedPackage && parseInt(el.getAttribute("data-package-id"), 10) === selectedPackage.id);
        });
        s.classList.add("open"); document.body.style.overflow = "hidden";
    }
    function closeTreatmentSheet() {
        var s = document.getElementById("treatmentSheet");
        if (s) {
            var panel = s.querySelector(".treatment-sheet");
            if (panel) {
                panel.classList.remove("is-dragging", "is-snapping");
                panel.style.transform = "";
            }
            s.classList.remove("open"); document.body.style.overflow = "";
        }
    }

    (function initTreatmentSheetDrag() {
        var overlay = document.getElementById("treatmentSheet");
        if (!overlay) return;
        var panel = overlay.querySelector(".treatment-sheet");
        var handle = overlay.querySelector(".sheet-handle");
        if (!panel || !handle) return;

        var startY = 0;
        var currentY = 0;
        var startedAt = 0;
        var dragging = false;

        handle.addEventListener("pointerdown", function(e) {
            if (!overlay.classList.contains("open")) return;
            dragging = true;
            startY = e.clientY;
            currentY = 0;
            startedAt = Date.now();
            panel.classList.remove("is-snapping");
            panel.classList.add("is-dragging");
            handle.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        handle.addEventListener("pointermove", function(e) {
            if (!dragging) return;
            var delta = e.clientY - startY;
            currentY = delta < 0 ? Math.max(delta * .22, -28) : delta;
            panel.style.transform = "translateY(" + currentY + "px)";
            e.preventDefault();
        });

        function finishDrag(e) {
            if (!dragging) return;
            dragging = false;
            var elapsed = Math.max(Date.now() - startedAt, 1);
            var velocity = currentY / elapsed;
            panel.classList.remove("is-dragging");

            if (currentY >= 90 || velocity > .65) {
                panel.classList.add("is-snapping");
                panel.style.transform = "translateY(110%)";
                window.setTimeout(closeTreatmentSheet, 220);
            } else {
                panel.classList.add("is-snapping");
                panel.style.transform = "translateY(0)";
                window.setTimeout(function() { panel.classList.remove("is-snapping"); }, 220);
            }
            if (e && handle.hasPointerCapture && handle.hasPointerCapture(e.pointerId)) {
                handle.releasePointerCapture(e.pointerId);
            }
        }

        handle.addEventListener("pointerup", finishDrag);
        handle.addEventListener("pointercancel", finishDrag);
    })();
    function markSheetItem(el, on) {
        if (!el) return;
        el.classList.toggle("selected", !!on);
        el.setAttribute("aria-pressed", on ? "true" : "false");
    }

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
    function pickInlineTreatment(input) { pickTreatment(input.closest("[data-treatment-choice]")); }
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
            var top = document.createElement("div"); top.className = "st-top";
            var nameEl = document.createElement("span"); nameEl.className = "st-name"; nameEl.textContent = t.name;
            top.appendChild(nameEl);
            var rm = document.createElement("button"); rm.type = "button"; rm.className = "st-remove"; rm.textContent = "×";
            rm.onclick = function() { removeTreatment(t.name); };
            top.appendChild(rm);
            item.appendChild(top);
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

        successLockedElements = [];
        document.querySelectorAll("a,button,input,select,textarea,[tabindex]").forEach(function(el) {
            if (overlay.contains(el)) return;
            successLockedElements.push({ el: el, tabindex: el.getAttribute("tabindex") });
            el.setAttribute("tabindex", "-1");
        });

        overlay.querySelectorAll("a").forEach(function(link) {
            link.addEventListener("click", function() { successExitAllowed = true; }, { once: true });
        });

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

    var bookingForm = document.getElementById("bookingForm");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function(e) {
            e.preventDefault();
            var treatment = document.getElementById("treatmentInput").value;
            if (!treatment) {
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
            openBookingConfirm();
            return false;
        });
    }

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

        imageWrapper.addEventListener("scroll", function() {
            var scrollPosition = imageWrapper.scrollLeft;
            var itemWidth = imageWrapper.offsetWidth;
            var newIndex = Math.round(scrollPosition / itemWidth);

            if (newIndex !== currentImageIndex) {
                currentImageIndex = newIndex;
                updateImageCounter();
            }
        });


        window.scrollImageContainer = scrollImageContainer;
    }
';

$hide_global_nav = true;
$hide_admin_inline_edit = true;
include __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
            <a href="<?php echo htmlspecialchars($booking_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="back-btn" aria-label="뒤로"><i class="bi bi-chevron-left"></i></a>
            <div class="page-title"><?php echo $is_rebooking ? '예약 변경' : '예약 선택'; ?></div>
    </div>

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
    <div class="error-message">
        <?php echo htmlspecialchars($booking_error); ?>
    </div>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('booking'); ?>
    <?php endif; ?>


    <form method="POST" class="booking-form<?php echo $is_rebooking ? ' rebooking' : ''; ?>" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="response_mode" value="ajax">

        <div class="form-group head-group">
            <div class="section-head-row">
                <span class="section-head-title">예약을 진행해볼까요?</span>
                <button type="button" class="option-change-btn" onclick="openTreatmentSheet()"<?php echo empty($booking_packages) ? ' style="display:none;"' : ''; ?>>
                    옵션 변경
                </button>
            </div>
            <input type="hidden" name="treatment" id="treatmentInput">
            <input type="hidden" name="quantity" id="quantityInput" value="1">
            <input type="hidden" name="treatments_json" id="treatmentsJson" value="[]">
            <input type="hidden" name="selection_type" id="selectionTypeInput" value="treatments">
            <input type="hidden" name="package_id" id="packageIdInput" value="0">
            <?php if ($preselected_booking_treatment === null): ?>
            <div class="inline-treatment-list" aria-label="<?php echo term('service', '시술'); ?> 선택">
                <?php foreach ($booking_treatments as $inline_treatment):
                    $inline_min_won = booking_price_won($inline_treatment['price_min']);
                    $inline_max_won = booking_price_won($inline_treatment['price_max']);
                    $inline_price = ($inline_min_won > 0)
                        ? (((int)$inline_treatment['price_max'] > (int)$inline_treatment['price_min'])
                            ? number_format($inline_min_won) . '~' . number_format($inline_max_won) . '원'
                            : number_format($inline_min_won) . '원') : '';
                ?>
                <label class="inline-treatment-choice" data-treatment-choice
                       data-name="<?php echo htmlspecialchars($inline_treatment['name'], ENT_QUOTES); ?>"
                       data-price="<?php echo htmlspecialchars($inline_price, ENT_QUOTES); ?>"
                       data-options="<?php echo htmlspecialchars(json_encode($inline_treatment['options'], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">
                    <input type="checkbox" onchange="pickInlineTreatment(this)">
                    <span><?php echo htmlspecialchars($inline_treatment['name']); ?></span>
                    <?php if ($inline_price !== ''): ?><span class="choice-price"><?php echo htmlspecialchars($inline_price); ?></span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div id="selectedTreatmentDisplay" class="selected-treatment" style="display:none;"></div>
            <div id="treatmentPlaceholder" class="form-help"><?php echo $preselected_booking_treatment === null ? '원하는 시술 1개를 선택해주세요.' : '선택한 시술의 세부 옵션을 확인해주세요.'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label date-select-label">언제 방문하시겠어요?</label>
            <input type="hidden" name="date" id="bookingDate" value="<?php
                $bk_get_date = trim((string)($_GET['date'] ?? ''));
                echo htmlspecialchars(preg_match('/^\d{4}-\d{2}-\d{2}$/', $bk_get_date) && $bk_get_date >= date('Y-m-d') ? $bk_get_date : date('Y-m-d'));
            ?>">
            <div class="month-selector" id="monthSelector"></div>
            <div class="date-selector" id="dateSelector"></div>
        </div>

        <div class="form-group">
            <label class="form-label date-select-label">예약 시간을 선택해주세요</label>
            <div class="rebook-method"><strong>예약 확정 방식</strong><span>병원에서 확인 후 일정이 확정돼요</span></div>
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
            <div class="time-grid" id="timeGrid"></div>
        </div>

        <div class="submit-section">
            <button type="submit" class="btn-submit">예약할게요!</button>
        </div>
    </form>

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
                <a href="detail.php?id=<?php echo (int)$hospital_id; ?><?php echo $booking_from_wishlist ? '&from=my_wishlist' : ''; ?>" class="success-action primary">확인했어요</a>
                <a href="my_bookings.php" class="success-action" id="successBookingsLink">내 예약 현황</a>
            </div>
        </section>
    </div>

    <?php
    $sheet_items = [];
    if (!empty($booking_treatments)) {
        foreach ($booking_treatments as $bt) {
            $pl = '';
            if (!empty($bt['price_min']) && $bt['price_min'] > 0) {
                $bt_min_won = booking_price_won($bt['price_min']);
                $bt_max_won = booking_price_won($bt['price_max']);
                $pl = ($bt['price_max'] > $bt['price_min'])
                    ? number_format($bt_min_won) . '~' . number_format($bt_max_won) . '원'
                    : number_format($bt_min_won) . '원';
            }
            $sheet_items[] = ['name' => $bt['name'], 'price' => $pl, 'options' => $bt['options'] ?? []];
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

    $sheet_packages = array_slice($booking_packages, 0, $preselected_sheet_item !== null ? 2 : 3);
    $sheet_item_slots = max(0, 3 - count($sheet_packages));
    $visible_sheet_items = [];
    if ($preselected_sheet_item !== null && $sheet_item_slots > 0) {
        $visible_sheet_items[] = $preselected_sheet_item;
    }
    $remaining_sheet_item_slots = $sheet_item_slots - count($visible_sheet_items);
    if ($remaining_sheet_item_slots > 0) {
        $visible_sheet_items = array_merge($visible_sheet_items, array_slice($sheet_items, 0, $remaining_sheet_item_slots));
    }
    $sheet_items = $visible_sheet_items;
    ?>
    <div class="treatment-sheet-overlay" id="treatmentSheet" onclick="if(event.target===this)closeTreatmentSheet()">
        <div class="treatment-sheet" role="dialog" aria-modal="true" aria-labelledby="treatmentSheetTitle">
            <div class="sheet-handle" aria-hidden="true"></div>
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
if ($booking_from_wishlist) $bottom_nav_active = 'my';
include __DIR__ . '/includes/footer.php';

if (isDevMode($connect)) { echo getDevModeJS_booking(); }

mysqli_close($connect);
?>

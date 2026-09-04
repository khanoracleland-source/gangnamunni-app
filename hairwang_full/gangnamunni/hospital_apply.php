<?php

session_start();
require_once __DIR__ . '/includes/hospital_phone_helper.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '병원 등록 신청';
$admin_current = 'hospital_apply';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/hospital_treatment_images.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

$message = '';
$error = '';
$success = false;
$current_admin = get_current_admin();
if (empty($_SESSION['hospital_apply_csrf'])) {
    $_SESSION['hospital_apply_csrf'] = bin2hex(random_bytes(32));
}
$hospital_apply_csrf = (string)$_SESSION['hospital_apply_csrf'];

if (isset($_GET['check_phone'])) {
    header('Content-Type: application/json; charset=utf-8');
    $checked_phone = trim((string)($_GET['check_phone'] ?? ''));
    if ($checked_phone === '') {
        echo json_encode(['ok' => false, 'error' => '전화번호를 입력해 주세요.']);
    } else {
        $dup = hospital_phone_find_duplicate($connect, $checked_phone);
        echo json_encode([
            'ok' => true,
            'duplicate' => $dup ? true : false,
            'hospital_name' => $dup['name'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$application_price_option_name_column = false;
$application_option_column_result = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
if ($application_option_column_result && mysqli_num_rows($application_option_column_result) > 0) {
    $application_price_option_name_column = true;
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN option_name VARCHAR(100) NOT NULL DEFAULT '' AFTER hospital_treatment_id")) {
    $application_price_option_name_column = true;
}

function normalizeHospitalApplyBusinessHours($raw)
{
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) return [];

    $valid_time = static function ($time) {
        if (!preg_match('/^(\d{2}):(\d{2})$/', (string)$time, $parts)) return false;
        return (int)$parts[1] >= 0 && (int)$parts[1] <= 23
            && (int)$parts[2] >= 0 && (int)$parts[2] <= 59;
    };

    $normalized = [];
    $days = ['월', '화', '수', '목', '금', '토', '일'];
    $closed_statuses = ['휴무', '공사중', '임시휴업'];
    foreach ($days as $day) {
        $value = trim((string)($decoded[$day] ?? ''));
        if (in_array($value, $closed_statuses, true)) {
            $normalized[$day] = $value;
        } elseif (preg_match('/^(\d{2}:\d{2})~(\d{2}:\d{2})$/', $value, $match)
            && $valid_time($match[1]) && $valid_time($match[2]) && $match[1] < $match[2]) {
            $normalized[$day] = $match[1] . '~' . $match[2];
        } else {
            $normalized[$day] = '휴무';
        }
    }

    if (!empty($decoded['no_lunch_break'])) {
        $normalized['no_lunch_break'] = true;
    } else {
        $lunch = trim((string)($decoded['점심'] ?? ''));
        if (preg_match('/^(\d{2}:\d{2})~(\d{2}:\d{2})$/', $lunch, $match)
            && $valid_time($match[1]) && $valid_time($match[2]) && $match[1] < $match[2]) {
            $normalized['점심'] = $match[1] . '~' . $match[2];
        }
    }

    $closed_dates = [];
    foreach ((array)($decoded['special_closed_dates'] ?? []) as $date) {
        $date = trim((string)$date);
        $parts = explode('-', $date);
        if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            $closed_dates[$date] = $date;
        }
    }
    ksort($closed_dates);
    $normalized['special_closed_dates'] = array_values($closed_dates);
    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($hospital_apply_csrf, (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    $error = '요청이 만료되었습니다. 페이지를 새로고침한 후 다시 시도해주세요.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['hospital_name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $business_number = trim($_POST['business_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($phone !== '') $phone = hospital_phone_format($phone);
    $email = trim($_POST['email'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $categories_raw = trim($_POST['categories'] ?? '');
    $categories = !empty($categories_raw) ? json_encode(explode(',', $categories_raw), JSON_UNESCAPED_UNICODE) : '[]';
    $treatment_tags_raw = trim($_POST['treatment_tags'] ?? '');
    $treatment_tags = !empty($treatment_tags_raw) ? json_encode(explode(',', $treatment_tags_raw), JSON_UNESCAPED_UNICODE) : '[]';
    $business_hours_data = normalizeHospitalApplyBusinessHours($_POST['business_hours'] ?? '');
    $business_hours = json_encode($business_hours_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $introduction = trim($_POST['introduction'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $sns_url = trim($_POST['sns_url'] ?? '');

    if (empty($name)) {
        $error = '병원명을 입력해 주세요.';
    } elseif ($phone !== '' && ($duplicate_phone_hospital = hospital_phone_find_duplicate($connect, $phone))) {
        $error = "이미 '{$duplicate_phone_hospital['name']}' 병원에서 사용 중인 전화번호입니다.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '올바른 이메일 주소를 입력해 주세요.';
    } else {
        $applied_by = $current_admin['username'] ?? ($current_admin['name'] ?? 'unknown');
        $upload_owner = 'admin_' . max(0, (int)($current_admin['id'] ?? 0));
        $upload_dir = __DIR__ . '/uploads/hospital_apply/' . $upload_owner . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                $error = '업로드 폴더를 준비할 수 없습니다.';
            }
        }

        $cert_business_path = '';
        $cert_medical_path = '';
        $profile_image_path = '';
        $interior_image_paths = [];

        if (empty($error) && !empty($_FILES['cert_business']['name']) && $_FILES['cert_business']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['cert_business']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['cert_business']['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cert_business']['tmp_name']);
            $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            if (isset($allowed_mimes[$mime]) && in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) && $_FILES['cert_business']['size'] <= 5 * 1024 * 1024) {
                $fname = 'cert_business_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
                if (move_uploaded_file($_FILES['cert_business']['tmp_name'], $upload_dir . $fname)) {
                    $cert_business_path = 'uploads/hospital_apply/' . $upload_owner . '/' . $fname;
                } else {
                    $error = '사업자등록증 파일 저장에 실패했습니다.';
                }
            } else {
                $error = '사업자등록증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
            }
        }

        if (empty($error) && !empty($_FILES['cert_medical']['name']) && $_FILES['cert_medical']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['cert_medical']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['cert_medical']['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cert_medical']['tmp_name']);
            $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            if (isset($allowed_mimes[$mime]) && in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) && $_FILES['cert_medical']['size'] <= 5 * 1024 * 1024) {
                $fname = 'cert_medical_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
                if (move_uploaded_file($_FILES['cert_medical']['tmp_name'], $upload_dir . $fname)) {
                    $cert_medical_path = 'uploads/hospital_apply/' . $upload_owner . '/' . $fname;
                } else {
                    $error = '의료기관 개설허가증 파일 저장에 실패했습니다.';
                }
            } else {
                $error = '의료기관 허가증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
            }
        }

        if (empty($error) && !empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['profile_image']['tmp_name']);
            $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (isset($allowed_mimes[$mime]) && in_array($ext, ['jpg', 'jpeg', 'png'], true) && $_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                $fname = 'profile_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $fname)) {
                    $profile_image_path = 'uploads/hospital_apply/' . $upload_owner . '/' . $fname;
                }
            } else {
                $error = '대표 이미지: 실제 JPG/PNG 파일, 5MB 이하만 가능합니다.';
            }
        }

        if (empty($error) && isset($_FILES['interior_images']['name']) && is_array($_FILES['interior_images']['name'])) {
            $interior_count = count(array_filter($_FILES['interior_images']['name'], 'strlen'));
            if ($interior_count > 10) {
                $error = '병원 내부 이미지는 최대 10장까지 등록할 수 있습니다.';
            } else {
                foreach ($_FILES['interior_images']['name'] as $i => $original_name) {
                    if ($original_name === '') continue;
                    $file_error = (int)($_FILES['interior_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    $tmp_name = $_FILES['interior_images']['tmp_name'][$i] ?? '';
                    $file_size = (int)($_FILES['interior_images']['size'][$i] ?? 0);
                    if ($file_error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_name)) {
                        $error = '병원 내부 이미지 업로드에 실패했습니다.';
                        break;
                    }
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp_name);
                    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($allowed_mimes[$mime]) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || $file_size > 10 * 1024 * 1024) {
                        $error = '병원 내부 이미지: JPG/PNG/WebP, 파일당 10MB 이하만 가능합니다.';
                        break;
                    }
                    $fname = 'interior_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
                    if (!move_uploaded_file($tmp_name, $upload_dir . $fname)) {
                        $error = '병원 내부 이미지 저장에 실패했습니다.';
                        break;
                    }
                    $interior_image_paths[] = 'uploads/hospital_apply/' . $upload_owner . '/' . $fname;
                }
            }
        }

        if (empty($error)) {
            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_hospitals (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, categories, treatment_tags, business_hours, introduction, website, sns_url, profile_image_url, cert_business, cert_medical, applied_by, applied_at, status, is_active, ad_grade, is_demo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0, 'none', ?, NOW())"
            );
            if ($stmt) {
                $is_demo_val = isDevMode($connect) ? 1 : 0;
                mysqli_stmt_bind_param($stmt, 'sssssssssssssssss' . 'si',
                    $name, $owner_name, $business_number, $phone, $email,
                    $contact_name, $contact_phone, $address, $categories,
                    $treatment_tags, $business_hours, $introduction,
                    $website, $sns_url, $profile_image_path,
                    $cert_business_path, $cert_medical_path, $applied_by,
                    $is_demo_val
                );
                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($connect);
                    $success = true;
                    $message = "병원 등록 신청이 접수되었습니다. (신청번호: #{$new_id})";

                    $image_rows = [];
                    if ($profile_image_path !== '') $image_rows[] = ['url' => $profile_image_path, 'type' => 'main', 'order' => 0];
                    foreach ($interior_image_paths as $image_index => $image_path) {
                        $image_rows[] = ['url' => $image_path, 'type' => 'interior', 'order' => $image_index + 1];
                    }
                    if ($image_rows) {
                        $image_stmt = mysqli_prepare($connect, "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                        if ($image_stmt) {
                            foreach ($image_rows as $image_row) {
                                $image_url = $image_row['url'];
                                $image_type = $image_row['type'];
                                $image_order = $image_row['order'];
                                mysqli_stmt_bind_param($image_stmt, 'issi', $new_id, $image_url, $image_type, $image_order);
                                if (!mysqli_stmt_execute($image_stmt)) error_log('[hospital_apply] 병원 이미지 저장 실패: ' . mysqli_stmt_error($image_stmt));
                            }
                            mysqli_stmt_close($image_stmt);
                        }
                    }

                    $doctor_names = is_array($_POST['doctor_name'] ?? null) ? $_POST['doctor_name'] : [];
                    $doctor_specialties = is_array($_POST['doctor_specialty'] ?? null) ? $_POST['doctor_specialty'] : [];
                    $doctor_bios = is_array($_POST['doctor_bio'] ?? null) ? $_POST['doctor_bio'] : [];
                    $doctor_qualifications = is_array($_POST['doctor_qualification'] ?? null) ? $_POST['doctor_qualification'] : [];
                    $doctor_experiences = is_array($_POST['doctor_experience'] ?? null) ? $_POST['doctor_experience'] : [];
                    $doctor_stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_doctors (hospital_id, name, specialty, experience, qualification, bio, photo_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, '', 'active', NOW(), NOW())");
                    if ($doctor_stmt) {
                        foreach ($doctor_names as $doctor_index => $doctor_name_raw) {
                            $doctor_name = trim((string)$doctor_name_raw);
                            if ($doctor_name === '') continue;
                            $doctor_specialty = trim((string)($doctor_specialties[$doctor_index] ?? ''));
                            $doctor_bio = trim((string)($doctor_bios[$doctor_index] ?? ''));
                            $doctor_qualification = trim((string)($doctor_qualifications[$doctor_index] ?? ''));
                            $doctor_experience = max(0, min(80, (int)($doctor_experiences[$doctor_index] ?? 0)));
                            mysqli_stmt_bind_param($doctor_stmt, 'ississ', $new_id, $doctor_name, $doctor_specialty, $doctor_experience, $doctor_qualification, $doctor_bio);
                            if (!mysqli_stmt_execute($doctor_stmt)) {
                                error_log('[hospital_apply] 의사 저장 실패: ' . mysqli_stmt_error($doctor_stmt));
                                continue;
                            }
                            $doctor_id = mysqli_insert_id($connect);
                            $photo_name = $_FILES['doctor_photo']['name'][$doctor_index] ?? '';
                            $photo_tmp = $_FILES['doctor_photo']['tmp_name'][$doctor_index] ?? '';
                            $photo_error = (int)($_FILES['doctor_photo']['error'][$doctor_index] ?? UPLOAD_ERR_NO_FILE);
                            $photo_size = (int)($_FILES['doctor_photo']['size'][$doctor_index] ?? 0);
                            if ($photo_name !== '' && $photo_error === UPLOAD_ERR_OK && is_uploaded_file($photo_tmp)) {
                                $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
                                $photo_mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo_tmp);
                                $photo_allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                                if (isset($photo_allowed[$photo_mime]) && in_array($photo_ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $photo_size <= 5 * 1024 * 1024) {
                                    $photo_file = 'doctor_' . $doctor_id . '_' . bin2hex(random_bytes(8)) . '.' . $photo_allowed[$photo_mime];
                                    if (move_uploaded_file($photo_tmp, $upload_dir . $photo_file)) {
                                        $photo_path = 'uploads/hospital_apply/' . $upload_owner . '/' . $photo_file;
                                        $photo_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_doctors SET photo_url=? WHERE id=? AND hospital_id=?");
                                        if ($photo_stmt) {
                                            mysqli_stmt_bind_param($photo_stmt, 'sii', $photo_path, $doctor_id, $new_id);
                                            mysqli_stmt_execute($photo_stmt);
                                            mysqli_stmt_close($photo_stmt);
                                        }
                                    }
                                } else {
                                    $message .= ' 의사 사진 일부가 형식 또는 용량 제한으로 제외되었습니다.';
                                }
                            }
                        }
                        mysqli_stmt_close($doctor_stmt);
                    }
                    try {
                        $application_treatment_ids = $_POST['application_treatment_ids'] ?? [];
                        $image_warnings = hti_save_application_treatments(
                            $connect, $new_id, $application_treatment_ids, 'hospital'
                        );
                        if ($image_warnings) $message .= ' 단, 일부 시술 이미지 업로드를 확인해 주세요: ' . implode(' / ', $image_warnings);

                        $valid_treatment_ids = hti_application_treatment_ids($connect, $application_treatment_ids);
                        foreach ($valid_treatment_ids as $treatment_id) {
                            $ht_id = 0;
                            $ht_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE hospital_id=? AND treatment_id=? AND is_active=1 LIMIT 1");
                            if (!$ht_stmt) continue;
                            mysqli_stmt_bind_param($ht_stmt, 'ii', $new_id, $treatment_id);
                            mysqli_stmt_execute($ht_stmt);
                            mysqli_stmt_bind_result($ht_stmt, $ht_id);
                            mysqli_stmt_fetch($ht_stmt);
                            mysqli_stmt_close($ht_stmt);
                            if (!$ht_id) continue;

                            $option_names = (array)($_POST['treatment_option_name'][$treatment_id] ?? []);
                            $dosage_values = (array)($_POST['treatment_dosage_value'][$treatment_id] ?? []);
                            $dosage_units = (array)($_POST['treatment_dosage_unit'][$treatment_id] ?? []);
                            $prices = (array)($_POST['treatment_price'][$treatment_id] ?? []);
                            $price_stmt = mysqli_prepare($connect, $application_price_option_name_column
                                ? "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, option_name, dosage_value, dosage_unit, price, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
                                : "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, dosage_value, dosage_unit, price, sort_order) VALUES (?, ?, ?, ?, ?)");
                            if (!$price_stmt) continue;
                            $option_count = max(count($option_names), count($dosage_values), count($prices));
                            for ($option_index = 0; $option_index < $option_count; $option_index++) {
                                $option_name = mb_substr(trim((string)($option_names[$option_index] ?? '')), 0, 100, 'UTF-8');
                                $dosage_value = (float)($dosage_values[$option_index] ?? 0);
                                $dosage_unit = mb_substr(trim((string)($dosage_units[$option_index] ?? '')), 0, 20, 'UTF-8');
                                $price = max(0, (int)($prices[$option_index] ?? 0));
                                if ($dosage_value <= 0 || $dosage_unit === '' || $price <= 0) continue;
                                if ($application_price_option_name_column) {
                                    mysqli_stmt_bind_param($price_stmt, 'isdsii', $ht_id, $option_name, $dosage_value, $dosage_unit, $price, $option_index);
                                } else {
                                    mysqli_stmt_bind_param($price_stmt, 'idsii', $ht_id, $dosage_value, $dosage_unit, $price, $option_index);
                                }
                                if (!mysqli_stmt_execute($price_stmt)) error_log('[hospital_apply] 시술 가격 옵션 저장 실패: ' . mysqli_stmt_error($price_stmt));
                            }
                            mysqli_stmt_close($price_stmt);
                        }
                    } catch (\Throwable $e) {
                        error_log('[hospital_apply] 시술/이미지 저장 실패: ' . $e->getMessage());
                    }

                    try {
                        if (file_exists(__DIR__ . '/includes/mail_helper.php')) {
                            require_once __DIR__ . '/includes/mail_helper.php';
                            $admin_body = "<h2>새 병원 등록 신청</h2>
                                <p><strong>병원명:</strong> " . htmlspecialchars($name) . "</p>
                                <p><strong>대표자:</strong> " . htmlspecialchars($owner_name) . "</p>
                                <p><strong>사업자번호:</strong> " . htmlspecialchars($business_number) . "</p>
                                <p><strong>이메일:</strong> " . htmlspecialchars($email) . "</p>
                                <p><strong>신청자:</strong> " . htmlspecialchars($applied_by) . "</p>
                                <p><a href='" . SITE_FULL_URL . "admin_hospitals.php?status=pending'>신청 목록 확인</a></p>";
                            @mail_send(0, 'admin@seoulbeauty.app', '[서울미녀] 새 병원 등록 신청 - ' . $name, $admin_body);

                            if (!empty($email)) {
                                $user_body = "<h2>병원 등록 신청이 접수되었습니다</h2>
                                    <p>안녕하세요, <strong>" . htmlspecialchars($contact_name) . "</strong>님.</p>
                                    <p><strong>" . htmlspecialchars($name) . "</strong>의 입점 신청이 정상적으로 접수되었습니다.</p>
                                    <p>심사 결과는 1~3 영업일 내에 이메일로 안내드리겠습니다.</p>
                                    <p>감사합니다.<br>" . term('site_name', '서울미녀') . " 팀</p>";
                                @mail_send(0, $email, '[서울미녀] 병원 등록 신청 접수 확인', $user_body);
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log('[hospital_apply] 이메일 발송 실패: ' . $e->getMessage());
                    }

                    try {
                        notifyAdmins($connect, 'hospital_apply',
                            '새 병원 등록 신청',
                            htmlspecialchars($name) . ' (신청번호 #' . $new_id . ')',
                            'admin_hospitals.php?status=pending',
                            ['hospital_id' => $new_id, 'hospital_name' => $name]
                        );
                    } catch (\Throwable $e) {
                        error_log('[hospital_apply] 관리자 알림 발송 실패: ' . $e->getMessage());
                    }

                    header('Location: hospital_apply.php?applied=1&msg=' . urlencode($message));
                    exit;
                } else {
                    $error = '신청 저장 실패: ' . mysqli_error($connect);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'SQL 준비 실패: ' . mysqli_error($connect);
            }
        }
    }
}

if (($_GET['applied'] ?? '') === '1') {
    $success = true;
    $message = (string)($_GET['msg'] ?? '신청이 접수되었습니다.');
}

$my_applications = [];
$applied_by_check = $current_admin['username'] ?? ($current_admin['name'] ?? '');
if (!empty($applied_by_check)) {
    $stmt_my = mysqli_prepare($connect, "SELECT id, name, status, applied_at, rejection_reason FROM gangnamunni_hospitals WHERE applied_by=? ORDER BY applied_at DESC LIMIT 10");
    if ($stmt_my) {
        mysqli_stmt_bind_param($stmt_my, 's', $applied_by_check);
        mysqli_stmt_execute($stmt_my);
        $res_my = mysqli_stmt_get_result($stmt_my);
        while ($row = mysqli_fetch_assoc($res_my)) {
            $my_applications[] = $row;
        }
        mysqli_stmt_close($stmt_my);
    }
}

$category_list = [];
$cat_r = mysqli_query($connect, "SELECT id, name, category_id FROM gangnamunni_treatment_master WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) {
    while ($cat_row = mysqli_fetch_assoc($cat_r)) {
        $category_list[] = ['id' => (int)$cat_row['id'], 'name' => $cat_row['name'], 'category_id' => (int)$cat_row['category_id']];
    }
}
if (empty($category_list)) {
    $fallback_cats = defined('CATEGORIES') ? CATEGORIES : ['코','눈','지방흡입','가슴성형','치아','리프팅','주름/미백','쁘띠','모발이식','제모','남성','눈성형'];
    foreach ($fallback_cats as $fc) {
        $category_list[] = ['id' => 0, 'name' => $fc, 'category_id' => 0];
    }
}

$treatment_tag_list = [];
$tt_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($tt_r) {
    while ($tt_row = mysqli_fetch_assoc($tt_r)) {
        $treatment_tag_list[] = ['id' => (int)$tt_row['id'], 'name' => $tt_row['name']];
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.treatment-select-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
    padding: 10px 12px;
    height: 100%;
}
.treatment-select-card__head {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 8px;
}
.treatment-select-card__step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    color: #fff;
    background: var(--bs-primary);
    font-size: .72rem;
    font-weight: 700;
    flex: 0 0 auto;
}
.treatment-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-content: flex-start;
    max-height: 145px;
    overflow-y: auto;
    padding: 1px 1px 2px;
}
.treatment-chip {
    margin: 0;
    padding: 0;
}
.treatment-chip .form-check-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.treatment-chip .form-check-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 27px;
    padding: 5px 9px;
    border: 1px solid var(--bs-border-color);
    border-radius: 999px;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: .76rem;
    line-height: 1;
    cursor: pointer;
    transition: border-color .15s ease, background-color .15s ease, color .15s ease, box-shadow .15s ease;
}
.treatment-chip .form-check-label::before {
    content: '+';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-weight: 700;
}
.treatment-chip .form-check-label:hover {
    border-color: var(--bs-primary);
    color: var(--bs-primary);
    box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb), .12);
}
.treatment-chip .form-check-input:checked + .form-check-label {
    border-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .1);
    color: var(--bs-primary);
    font-weight: 600;
    box-shadow: inset 0 0 0 1px rgba(var(--bs-primary-rgb), .12);
}
.treatment-chip .form-check-input:checked + .form-check-label::before {
    content: '\2713';
    background: var(--bs-primary);
    color: #fff;
}
.treatment-empty-state {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border: 1px dashed var(--bs-border-color);
    border-radius: 8px;
    background: var(--bs-tertiary-bg);
}
.application-treatment-list {
    max-height: 310px;
    overflow-y: auto;
    padding-right: 2px;
}
.application-treatment-card {
    display: flex;
    align-items: center;
    gap: 9px;
    min-height: 58px;
    padding: 9px 11px;
    border: 1px solid var(--bs-border-color);
    border-radius: 9px;
    background: var(--bs-body-bg);
    cursor: pointer;
    transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.application-treatment-card:hover {
    border-color: var(--bs-primary);
    box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb), .1);
}
.application-treatment-card:has(.application-treatment-check:checked) {
    border-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .07);
}
.application-treatment-checkmark {
    color: var(--bs-primary);
    opacity: 0;
}
.application-treatment-check:checked ~ .application-treatment-checkmark {
    opacity: 1;
}
.application-treatment-detail {
    border-left: 4px solid var(--bs-primary) !important;
}
.application-section {
    padding: 0 !important;
}
.application-section-toggle {
    display: flex !important;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
}
.application-price-row {
    display: grid;
    grid-template-columns: minmax(130px, 1.4fr) minmax(85px, .65fr) 90px minmax(120px, .8fr) 34px;
    gap: 7px;
    align-items: center;
}
.business-lunch-card {
    border: 1px solid rgba(var(--bs-primary-rgb), .2);
    border-radius: 12px;
    background: var(--bs-body-bg);
}
.business-lunch-time-box {
    min-width: 128px;
}
.business-lunch-time-box .form-control {
    min-height: 38px;
}
.business-lunch-separator {
    color: var(--bs-secondary-color);
    font-weight: 700;
    padding-top: 22px;
}
.business-lunch-card.is-disabled .business-lunch-time-area {
    opacity: .45;
}
.business-hours-bulk-card {
    border: 1px solid rgba(var(--bs-primary-rgb), .25);
    border-radius: 12px;
    background: var(--bs-body-bg);
}
.business-hours-bulk-days .btn {
    min-width: 54px;
}
@media (max-width: 767.98px) {
    .application-price-row { grid-template-columns: 1fr 1fr; }
    .application-price-row .remove-application-price { width: 100%; }
    .business-lunch-time-area { width: 100%; }
    .business-lunch-time-box { flex: 1 1 120px; }
}
</style>

<div class="container-fluid px-4 py-4">

<?php if ($success): ?>
    <!-- 신청 완료 -->
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="bi bi-check-circle text-success" style="font-size: 5rem;"></i>
        </div>
        <h2 class="fw-bold mb-3">신청이 접수되었습니다!</h2>
        <p class="text-muted fs-5"><?= htmlspecialchars($message) ?></p>
        <p class="text-muted">심사 결과는 1~3 영업일 내에 이메일로 안내드립니다.</p>
        <div class="mt-4">
            <a href="hospital_apply_status.php" class="btn btn-primary me-2"><i class="bi bi-clock-history me-1"></i>내 신청 현황</a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-house me-1"></i>대시보드</a>
        </div>
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($my_applications)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            이미 <?= count($my_applications) ?>건의 신청 내역이 있습니다.
            <a href="hospital_apply_status.php" class="alert-link">내 신청 현황 보기</a>
        </div>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('hospital_apply'); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>병원 등록 신청</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="applyForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($hospital_apply_csrf, ENT_QUOTES, 'UTF-8') ?>">

                <!-- 기본 정보 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-building me-2"></i>기본 정보</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원명 <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" required maxlength="255" value="<?= htmlspecialchars($_POST['hospital_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">대표자명</label>
                        <input type="text" name="owner_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록번호</label>
                        <input type="text" name="business_number" class="form-control" maxlength="20" placeholder="000-00-00000" value="<?= htmlspecialchars($_POST['business_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 전화번호</label>
                        <div class="input-group">
                            <button type="button" id="checkPhoneDupBtn" class="btn btn-outline-secondary">
                                <i class="bi bi-shield-check me-1"></i>중복 확인
                            </button>
                            <input type="tel" name="phone" id="hospitalPhoneInput" class="form-control" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="02-1234-5678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div id="checkPhoneDupResult" class="form-text"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 아이디(이메일 형식)</label>
                        <input type="email" name="email" class="form-control" maxlength="191" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자명</label>
                        <input type="text" name="contact_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자 전화</label>
                        <input type="tel" name="contact_phone" class="form-control" maxlength="20" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                </div>

                <!-- 병원 정보 / 병원별 시술 배정 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>병원 정보</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 주소</label>
                        <input type="text" name="address" class="form-control" maxlength="500" placeholder="도로명 주소를 입력하세요" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                    <?php
                    $apply_bh_data = normalizeHospitalApplyBusinessHours($_POST['business_hours'] ?? '');
                    $apply_bh_days = ['월'=>'월요일','화'=>'화요일','수'=>'수요일','목'=>'목요일','금'=>'금요일','토'=>'토요일','일'=>'일요일'];
                    $apply_bh_defaults = ['월'=>'10:00~19:00','화'=>'10:00~19:00','수'=>'10:00~19:00','목'=>'10:00~19:00','금'=>'10:00~19:00','토'=>'10:00~15:00','일'=>'휴무'];
                    $apply_no_lunch = !empty($apply_bh_data['no_lunch_break']);
                    $apply_lunch_parts = explode('~', $apply_bh_data['점심'] ?? '13:00~14:00');
                    $apply_closed_dates = $apply_bh_data['special_closed_dates'] ?? [];
                    ?>
                    <div class="col-12">
                        <div class="border rounded overflow-hidden">
                            <button class="btn btn-light w-100 text-start d-flex justify-content-between align-items-center p-3" type="button" data-bs-toggle="collapse" data-bs-target="#businessHoursSection" aria-expanded="false" aria-controls="businessHoursSection">
                                <span class="fw-bold"><i class="bi bi-clock me-2 text-primary"></i>영업시간 설정 <span class="badge bg-secondary fw-normal">선택</span></span>
                                <i class="bi bi-chevron-down business-hours-chevron"></i>
                            </button>
                            <div class="collapse" id="businessHoursSection">
                                <div class="p-3 border-top bg-body-tertiary">
                                    <input type="hidden" name="business_hours" id="businessHoursInput" value="<?= htmlspecialchars($_POST['business_hours'] ?? '{}', ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="business-hours-bulk-card p-3 mb-3">
                                        <div class="fw-bold mb-1"><i class="bi bi-lightning-charge me-1 text-primary"></i>영업시간 일괄 설정</div>
                                        <div class="small text-muted mb-3">시간과 적용할 요일을 선택한 후 적용하세요. 적용 후 아래 표에서 요일별로 다시 수정할 수 있습니다.</div>
                                        <div class="row g-2 align-items-end mb-3">
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1" for="applyBhBulkStart">시작 시간</label>
                                                <input type="time" class="form-control form-control-sm" id="applyBhBulkStart" value="10:00">
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1" for="applyBhBulkEnd">종료 시간</label>
                                                <input type="time" class="form-control form-control-sm" id="applyBhBulkEnd" value="19:00">
                                            </div>
                                            <div class="col-sm-4">
                                                <button type="button" class="btn btn-primary btn-sm w-100" id="applyBhBulkApply"><i class="bi bi-check2 me-1"></i>선택 요일에 적용</button>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="applyBhSelectWeekdays">평일 (월~금)</button>
                                        </div>
                                        <div class="business-hours-bulk-days d-flex flex-wrap gap-2">
                                            <?php foreach ($apply_bh_days as $day_key => $day_label): ?>
                                                <input type="checkbox" class="btn-check apply-bh-bulk-day" id="applyBhBulkDay<?= $day_key ?>" value="<?= $day_key ?>" autocomplete="off">
                                                <label class="btn btn-outline-secondary btn-sm" for="applyBhBulkDay<?= $day_key ?>"><?= $day_label ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="small mt-2 d-none" id="applyBhBulkMessage" role="status"></div>
                                    </div>
                                    <div class="table-responsive bg-body rounded border mb-3">
                                        <table class="table table-sm align-middle mb-0 business-hours-table">
                                            <thead class="table-light"><tr><th>요일</th><th>시작</th><th>종료</th><th>상태</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($apply_bh_days as $day_key => $day_label):
                                                $day_value = $apply_bh_data[$day_key] ?? $apply_bh_defaults[$day_key];
                                                $day_status = in_array($day_value, ['휴무','공사중','임시휴업'], true) ? $day_value : 'normal';
                                                $day_parts = $day_status === 'normal' ? explode('~', $day_value) : ['', ''];
                                            ?>
                                                <tr>
                                                    <td class="fw-semibold text-nowrap"><?= $day_label ?></td>
                                                    <td><input type="time" class="form-control form-control-sm apply-bh-start" data-day="<?= $day_key ?>" value="<?= htmlspecialchars($day_parts[0] ?? '') ?>" <?= $day_status !== 'normal' ? 'disabled' : '' ?>></td>
                                                    <td><input type="time" class="form-control form-control-sm apply-bh-end" data-day="<?= $day_key ?>" value="<?= htmlspecialchars($day_parts[1] ?? '') ?>" <?= $day_status !== 'normal' ? 'disabled' : '' ?>></td>
                                                    <td><select class="form-select form-select-sm apply-bh-status" data-day="<?= $day_key ?>">
                                                        <option value="normal" <?= $day_status === 'normal' ? 'selected' : '' ?>>정상진료</option>
                                                        <option value="휴무" <?= $day_status === '휴무' ? 'selected' : '' ?>>휴무</option>
                                                        <option value="공사중" <?= $day_status === '공사중' ? 'selected' : '' ?>>공사중</option>
                                                        <option value="임시휴업" <?= $day_status === '임시휴업' ? 'selected' : '' ?>>임시휴업</option>
                                                    </select></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="business-lunch-card p-3 mb-3 <?= $apply_no_lunch ? 'is-disabled' : '' ?>" id="businessLunchCard">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <div class="fw-bold">점심시간</div>
                                                    <div class="small text-muted">예약을 받지 않는 점심 휴게 시간을 설정하세요.</div>
                                                </div>
                                            </div>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="applyBhNoLunch" <?= $apply_no_lunch ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold text-nowrap" for="applyBhNoLunch">점심시간 없음</label>
                                            </div>
                                        </div>
                                        <div class="business-lunch-time-area d-flex flex-wrap align-items-center gap-2">
                                            <label class="business-lunch-time-box">
                                                <span class="d-block small fw-semibold text-muted mb-1">시작 시간</span>
                                                <input type="time" class="form-control form-control-sm apply-bh-lunch-time" id="applyBhLunchStart" value="<?= htmlspecialchars($apply_lunch_parts[0] ?? '13:00') ?>" <?= $apply_no_lunch ? 'disabled' : '' ?>>
                                            </label>
                                            <span class="business-lunch-separator"><i class="bi bi-arrow-right"></i></span>
                                            <label class="business-lunch-time-box">
                                                <span class="d-block small fw-semibold text-muted mb-1">종료 시간</span>
                                                <input type="time" class="form-control form-control-sm apply-bh-lunch-time" id="applyBhLunchEnd" value="<?= htmlspecialchars($apply_lunch_parts[1] ?? '14:00') ?>" <?= $apply_no_lunch ? 'disabled' : '' ?>>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="bg-body border rounded p-3">
                                        <div class="fw-semibold mb-1">임시 휴무일 (정기 휴무 외)</div>
                                        <div class="text-muted small mb-2">정기 휴무일 외에 추가로 쉬는 날짜를 등록하세요.</div>
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                            <input type="date" class="form-control form-control-sm" id="applyBhClosedDate" min="<?= date('Y-m-d') ?>" style="max-width:190px;">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="applyBhAddClosedDate"><i class="bi bi-plus-lg me-1"></i>추가</button>
                                        </div>
                                        <div id="applyBhClosedDateList" class="d-flex flex-wrap gap-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 소개</label>
                        <textarea name="introduction" class="form-control" rows="4" maxlength="2000"><?= htmlspecialchars($_POST['introduction'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">홈페이지 URL</label>
                        <input type="url" name="website" class="form-control" placeholder="https://" value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SNS (인스타그램 등)</label>
                        <input type="text" name="sns_url" class="form-control" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($_POST['sns_url'] ?? '') ?>">
                    </div>
                </div>

                <!-- 병원별 시술 배정 -->
                <div class="application-section border rounded mb-4 overflow-hidden w-100">
                    <button class="application-section-toggle btn btn-light text-start justify-content-between align-items-center p-3" type="button" data-bs-toggle="collapse" data-bs-target="#treatmentSection" aria-expanded="false" aria-controls="treatmentSection">
                        <span class="fw-bold"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>시술 추가 <span class="badge bg-secondary fw-normal">선택</span></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse w-100" id="treatmentSection">
                        <div class="p-2 border-top bg-body-tertiary w-100">
                        <div class="bg-body rounded border p-3 w-100">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold"><i class="bi bi-list-check text-primary me-1"></i>배정할 시술 선택</div>
                                    <div class="form-text">카테고리로 찾은 후 여러 시술을 한 번에 선택할 수 있습니다.</div>
                                </div>
                                <span class="badge bg-primary" id="selectedTreatmentCount">0개 선택</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-3" id="applicationTreatmentTabs">
                                <button type="button" class="btn btn-primary btn-sm treatment-filter active" data-cat="all">전체 <span class="badge bg-white text-primary ms-1"><?= count($category_list) ?></span></button>
                                <?php foreach ($treatment_tag_list as $tag): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm treatment-filter" data-cat="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="row g-2 application-treatment-list" id="applicationTreatmentList">
                                <?php foreach ($category_list as $cat): ?>
                                <div class="col-md-6 col-xl-4 application-treatment-item" data-cat="<?= $cat['category_id'] ?>">
                                    <label class="application-treatment-card">
                                        <input class="form-check-input application-treatment-check" type="checkbox" value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" data-category="<?= $cat['category_id'] ?>">
                                        <span class="flex-grow-1">
                                            <strong class="d-block small"><?= htmlspecialchars($cat['name']) ?></strong>
                                            <?php
                                            $application_category_name = '미분류';
                                            foreach ($treatment_tag_list as $tag) {
                                                if ($tag['id'] === $cat['category_id']) {
                                                    $application_category_name = $tag['name'];
                                                    break;
                                                }
                                            }
                                            ?>
                                            <small class="text-muted"><?= htmlspecialchars($application_category_name) ?></small>
                                        </span>
                                        <i class="bi bi-check-circle-fill application-treatment-checkmark"></i>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-2 d-grid gap-2 w-100" id="selectedTreatmentDetails"></div>
                        <input type="hidden" name="treatment_tags" id="treatmentTagsInput">
                        <input type="hidden" name="categories" id="categoriesInput">
                        </div>
                    </div>
                </div>

                <!-- 병원 이미지 -->
                <div class="application-section border rounded mb-4 overflow-hidden w-100">
                    <button class="application-section-toggle btn btn-light text-start justify-content-between align-items-center p-3" type="button" data-bs-toggle="collapse" data-bs-target="#hospitalImageSection" aria-expanded="false" aria-controls="hospitalImageSection">
                        <span class="fw-bold"><i class="bi bi-images me-2 text-primary"></i>병원 이미지 추가 <span class="badge bg-secondary fw-normal">선택</span></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse" id="hospitalImageSection">
                    <div class="p-3 border-top bg-body-tertiary">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="bg-body rounded border p-3 h-100">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-image text-primary"></i>
                                        <label class="form-label fw-semibold mb-0">메인 이미지</label>
                                    </div>
                                    <input type="file" name="profile_image" class="form-control form-control-sm image-preview-input" accept=".jpg,.jpeg,.png" data-preview="mainImagePreview">
                                    <div class="form-text">목록 및 병원 상세 상단 · JPG/PNG · 5MB 이하</div>
                                    <div id="mainImagePreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="bg-body rounded border p-3 h-100">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-images text-primary"></i>
                                        <label class="form-label fw-semibold mb-0" for="interiorImagesInput">내부 상세 이미지</label>
                                        <span class="badge bg-light text-dark border" id="interiorImageCount">0 / 10</span>
                                    </div>
                                    <input type="file" name="interior_images[]" id="interiorImagesInput" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple>
                                    <div class="form-text">한 번에 최대 10장 선택 가능 · JPG/PNG/WebP · 파일당 10MB 이하</div>
                                    <div class="d-flex flex-wrap gap-2 mt-2" id="interiorImagesPreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- 의사 관리 -->
                <div class="application-section border rounded mb-4 overflow-hidden w-100">
                    <button class="application-section-toggle btn btn-light text-start justify-content-between align-items-center p-3" type="button" data-bs-toggle="collapse" data-bs-target="#doctorSection" aria-expanded="false" aria-controls="doctorSection">
                        <span class="fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>의사 추가 <span class="badge bg-secondary fw-normal">선택</span></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse" id="doctorSection">
                    <div class="p-3 border-top">
                    <div class="form-text mb-3">선택 사항입니다. 해당 병원에 소속된 의사가 있을 경우에만 추가해 주세요.</div>
                    <div id="doctorRows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addDoctorButton"><i class="bi bi-plus-circle me-1"></i>다른 의사 추가</button>
                    </div>
                    </div>
                </div>

                <!-- 서류 업로드 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-cloud-upload me-2"></i>서류 업로드</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록증</label>
                        <input type="file" name="cert_business" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하 (선택)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">의료기관 개설허가증</label>
                        <input type="file" name="cert_medical" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하 (선택)</div>
                    </div>
                </div>

                <!-- 약관 동의 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>약관 동의</h6>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms">
                        <label class="form-check-label" for="agreeTerms">
                            <span class="badge bg-secondary me-1">선택</span> 서울미녀 병원 입점 약관에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_privacy" id="agreePrivacy">
                        <label class="form-check-label" for="agreePrivacy">
                            <span class="badge bg-secondary me-1">선택</span> 개인정보 수집/이용에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="agree_marketing" id="agreeMarketing">
                        <label class="form-check-label" for="agreeMarketing">
                            <span class="badge bg-secondary me-1">선택</span> 마케팅 수신에 동의합니다.
                        </label>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-send me-2"></i>신청서 제출
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

</div>

<script>
document.getElementById('interiorImagesInput')?.addEventListener('change', function() {
    var input = this;
    var files = Array.from(input.files || []);
    var preview = document.getElementById('interiorImagesPreview');
    var countLabel = document.getElementById('interiorImageCount');
    if (!preview || !countLabel) return;
    preview.innerHTML = '';

    if (files.length > 10) {
        input.value = '';
        countLabel.textContent = '0 / 10';
        alert('내부 상세 이미지는 최대 10장까지 선택할 수 있습니다.');
        return;
    }

    var invalidFile = files.find(function(file) {
        return !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024;
    });
    if (invalidFile) {
        input.value = '';
        countLabel.textContent = '0 / 10';
        alert('JPG/PNG/WebP 이미지이며 파일당 10MB 이하인지 확인해 주세요.');
        return;
    }

    countLabel.textContent = files.length + ' / 10';
    files.forEach(function(file, index) {
        var item = document.createElement('div');
        item.className = 'text-center';
        item.style.width = '96px';
        var image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.className = 'rounded border object-fit-cover d-block';
        image.style.width = '96px';
        image.style.height = '72px';
        image.onload = function() { URL.revokeObjectURL(image.src); };
        var label = document.createElement('div');
        label.className = 'small text-body-secondary text-truncate mt-1';
        label.title = file.name;
        label.textContent = (index + 1) + '. ' + file.name;
        item.appendChild(image);
        item.appendChild(label);
        preview.appendChild(item);
    });
});

function addDoctorRow() {
    var row = document.createElement('div');
    row.className = 'border rounded p-3 mb-3 doctor-row';
    row.innerHTML = '<div class="row g-2">'
        + '<div class="col-md-4"><label class="form-label small fw-semibold">의사명</label><input type="text" name="doctor_name[]" class="form-control" maxlength="100" placeholder="예: 김서울"></div>'
        + '<div class="col-md-4"><label class="form-label small fw-semibold">전문 분야</label><input type="text" name="doctor_specialty[]" class="form-control" maxlength="255" placeholder="예: 피부과 전문의"></div>'
        + '<div class="col-md-3"><label class="form-label small fw-semibold">경력(년)</label><input type="number" name="doctor_experience[]" class="form-control" min="0" max="80" placeholder="예: 10"></div>'
        + '<div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100 remove-doctor" aria-label="의사 삭제"><i class="bi bi-trash"></i></button></div>'
        + '<div class="col-md-6"><label class="form-label small fw-semibold">자격 및 학회</label><textarea name="doctor_qualification[]" class="form-control" rows="3" maxlength="2000" placeholder="예: 피부과 전문의&#10;대한피부과학회 정회원"></textarea></div>'
        + '<div class="col-md-6"><label class="form-label small fw-semibold">의사 사진</label><input type="file" name="doctor_photo[]" class="form-control" accept=".jpg,.jpeg,.png,.webp"><div class="form-text">JPG/PNG/WebP, 5MB 이하</div></div>'
        + '<div class="col-12"><label class="form-label small fw-semibold">소개 및 주요 경력</label><textarea name="doctor_bio[]" class="form-control" rows="3" maxlength="2000" placeholder="의사 소개, 주요 경력과 진료 철학을 입력해 주세요."></textarea></div>'
        + '</div>';
    document.getElementById('doctorRows').appendChild(row);
}
document.getElementById('addDoctorButton')?.addEventListener('click', addDoctorRow);
document.getElementById('doctorRows')?.addEventListener('click', function(e) {
    var button = e.target.closest('.remove-doctor');
    if (button) button.closest('.doctor-row').remove();
});
document.getElementById('doctorSection')?.addEventListener('shown.bs.collapse', function() {
    if (!document.querySelector('.doctor-row')) addDoctorRow();
}, { once: true });

document.querySelectorAll('.image-preview-input').forEach(function(input) {
    input.addEventListener('change', function() {
        var preview = document.getElementById(input.getAttribute('data-preview'));
        if (!preview) return;
        preview.innerHTML = '';
        Array.from(input.files).slice(0, 10).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.className = 'rounded border object-fit-cover';
            img.style.width = '96px'; img.style.height = '72px';
            img.onload = function() { URL.revokeObjectURL(img.src); };
            preview.appendChild(img);
        });
    });
});

var APPLICATION_PRICE_UNITS = ['샷', 'cc', 'ml', '회', '병', 'IU', '기타'];
function applicationEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function(char) {
        return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char];
    });
}
function applicationPriceRowHtml(treatmentId) {
    var units = APPLICATION_PRICE_UNITS.map(function(unit) {
        return '<option value="' + unit + '"' + (unit === '회' ? ' selected' : '') + '>' + unit + '</option>';
    }).join('');
    return '<div class="application-price-row">'
        + '<input type="text" name="treatment_option_name[' + treatmentId + '][]" class="form-control form-control-sm" maxlength="100" placeholder="옵션명 (예: 주름 100샷)">'
        + '<input type="number" name="treatment_dosage_value[' + treatmentId + '][]" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="용량">'
        + '<select name="treatment_dosage_unit[' + treatmentId + '][]" class="form-select form-select-sm">' + units + '</select>'
        + '<input type="number" name="treatment_price[' + treatmentId + '][]" class="form-control form-control-sm" min="1" step="1" placeholder="가격(원)">'
        + '<button type="button" class="btn btn-outline-danger btn-sm remove-application-price" aria-label="가격 옵션 삭제"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
}
function updateApplicationPriceButtons(detail) {
    var rows = detail.querySelectorAll('.application-price-row');
    rows.forEach(function(row) {
        row.querySelector('.remove-application-price').disabled = rows.length <= 1;
    });
}
function createTreatmentDetail(checkbox) {
    var id = checkbox.value;
    var detail = document.createElement('div');
    detail.className = 'application-treatment-detail bg-body border rounded p-3';
    detail.dataset.treatmentId = id;
    detail.innerHTML = '<input type="hidden" name="application_treatment_ids[]" value="' + id + '">'
        + '<div class="d-flex justify-content-between align-items-center mb-2"><strong>' + applicationEscape(checkbox.dataset.name) + '</strong><span class="badge bg-success">배정 선택</span></div>'
        + '<div class="row g-3"><div class="col-xl-7">'
        + '<div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label small fw-semibold mb-0">가격 옵션 <span class="text-muted fw-normal">(선택)</span></label><button type="button" class="btn btn-outline-primary btn-sm add-application-price"><i class="bi bi-plus-lg me-1"></i>옵션 추가</button></div>'
        + '<div class="d-grid gap-2 application-price-rows">' + applicationPriceRowHtml(id) + '</div>'
        + '</div><div class="col-xl-5">'
        + '<label class="form-label small fw-semibold">상세페이지 이미지 <span class="text-muted fw-normal">(선택)</span></label>'
        + '<input type="file" name="treatment_images[' + id + '][]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.gif,.webp" multiple>'
        + '<div class="form-text">여러 장 선택 가능 · 파일당 10MB 이하</div>'
        + '</div></div>';
    return detail;
}
function refreshSelectedTreatments() {
    var selected = Array.from(document.querySelectorAll('.application-treatment-check:checked'));
    var box = document.getElementById('selectedTreatmentDetails');
    var existing = {};
    box.querySelectorAll('.application-treatment-detail').forEach(function(detail) { existing[detail.dataset.treatmentId] = detail; });
    selected.forEach(function(checkbox) {
        if (existing[checkbox.value]) { delete existing[checkbox.value]; return; }
        box.appendChild(createTreatmentDetail(checkbox));
    });
    Object.keys(existing).forEach(function(id) { existing[id].remove(); });
    document.getElementById('selectedTreatmentCount').textContent = selected.length + '개 선택';
    document.getElementById('categoriesInput').value = selected.map(function(cb) { return cb.dataset.name; }).join(',');
    var categoryNames = [];
    selected.forEach(function(cb) {
        var filter = document.querySelector('.treatment-filter[data-cat="' + cb.dataset.category + '"]');
        if (filter && categoryNames.indexOf(filter.textContent.trim()) === -1) categoryNames.push(filter.textContent.trim());
    });
    document.getElementById('treatmentTagsInput').value = categoryNames.join(',');
}
document.querySelectorAll('.application-treatment-check').forEach(function(checkbox) {
    checkbox.addEventListener('change', refreshSelectedTreatments);
});
document.querySelectorAll('.treatment-filter').forEach(function(button) {
    button.addEventListener('click', function() {
        document.querySelectorAll('.treatment-filter').forEach(function(item) { item.classList.remove('btn-primary', 'active'); item.classList.add('btn-outline-primary'); });
        button.classList.remove('btn-outline-primary'); button.classList.add('btn-primary', 'active');
        var category = button.dataset.cat;
        document.querySelectorAll('.application-treatment-item').forEach(function(item) {
            item.style.display = category === 'all' || item.dataset.cat === category ? '' : 'none';
        });
    });
});
document.getElementById('selectedTreatmentDetails')?.addEventListener('click', function(e) {
    var add = e.target.closest('.add-application-price');
    if (add) {
        var detail = add.closest('.application-treatment-detail');
        detail.querySelector('.application-price-rows').insertAdjacentHTML('beforeend', applicationPriceRowHtml(detail.dataset.treatmentId));
        updateApplicationPriceButtons(detail);
    }
    var remove = e.target.closest('.remove-application-price');
    if (remove) {
        var removeDetail = remove.closest('.application-treatment-detail');
        remove.closest('.application-price-row').remove();
        updateApplicationPriceButtons(removeDetail);
    }
});

var applyBhClosedDates = <?= json_encode($apply_closed_dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function renderApplyBhClosedDates() {
    var list = document.getElementById('applyBhClosedDateList');
    if (!list) return;
    list.innerHTML = '';
    applyBhClosedDates.sort().forEach(function(date) {
        var badge = document.createElement('span');
        badge.className = 'badge text-bg-secondary d-inline-flex align-items-center gap-2 px-2 py-2';
        badge.appendChild(document.createTextNode(date));
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-close btn-close-white';
        remove.style.fontSize = '8px';
        remove.setAttribute('aria-label', '삭제');
        remove.addEventListener('click', function() {
            applyBhClosedDates = applyBhClosedDates.filter(function(value) { return value !== date; });
            renderApplyBhClosedDates();
        });
        badge.appendChild(remove);
        list.appendChild(badge);
    });
}
document.getElementById('applyBhAddClosedDate')?.addEventListener('click', function() {
    var input = document.getElementById('applyBhClosedDate');
    if (input.value && applyBhClosedDates.indexOf(input.value) === -1) {
        applyBhClosedDates.push(input.value);
        input.value = '';
        renderApplyBhClosedDates();
    }
});
document.querySelectorAll('.apply-bh-status').forEach(function(select) {
    select.addEventListener('change', function() {
        var row = select.closest('tr');
        var closed = select.value !== 'normal';
        row.querySelectorAll('input[type="time"]').forEach(function(input) {
            input.disabled = closed;
            if (closed) input.value = '';
        });
    });
});
document.getElementById('applyBhSelectWeekdays')?.addEventListener('click', function() {
    var weekdays = ['월', '화', '수', '목', '금'];
    var weekdayInputs = Array.from(document.querySelectorAll('.apply-bh-bulk-day'))
        .filter(function(input) { return weekdays.indexOf(input.value) !== -1; });
    var shouldSelect = weekdayInputs.some(function(input) { return !input.checked; });
    weekdayInputs.forEach(function(input) { input.checked = shouldSelect; });
});
document.getElementById('applyBhBulkApply')?.addEventListener('click', function() {
    var start = document.getElementById('applyBhBulkStart').value;
    var end = document.getElementById('applyBhBulkEnd').value;
    var selectedDays = Array.from(document.querySelectorAll('.apply-bh-bulk-day:checked'));
    var message = document.getElementById('applyBhBulkMessage');

    message.classList.remove('d-none', 'text-success', 'text-danger');
    if (!start || !end || start >= end) {
        message.classList.add('text-danger');
        message.textContent = '시작 시간보다 늦은 종료 시간을 선택해 주세요.';
        return;
    }
    if (!selectedDays.length) {
        message.classList.add('text-danger');
        message.textContent = '적용할 요일을 하나 이상 선택해 주세요.';
        return;
    }

    selectedDays.forEach(function(input) {
        var day = input.value;
        var status = document.querySelector('.apply-bh-status[data-day="' + day + '"]');
        var startInput = document.querySelector('.apply-bh-start[data-day="' + day + '"]');
        var endInput = document.querySelector('.apply-bh-end[data-day="' + day + '"]');
        status.value = 'normal';
        startInput.disabled = false;
        endInput.disabled = false;
        startInput.value = start;
        endInput.value = end;
    });
    message.classList.add('text-success');
    message.textContent = selectedDays.map(function(input) { return input.value; }).join(', ') + '요일에 적용했습니다.';
});
document.getElementById('applyBhNoLunch')?.addEventListener('change', function() {
    var noLunch = document.getElementById('applyBhNoLunch').checked;
    document.querySelectorAll('.apply-bh-lunch-time').forEach(function(input) {
        input.disabled = noLunch;
    });
    document.getElementById('businessLunchCard')?.classList.toggle('is-disabled', noLunch);
});
document.getElementById('businessHoursSection')?.addEventListener('show.bs.collapse', function() {
    document.querySelector('.business-hours-chevron')?.classList.replace('bi-chevron-down', 'bi-chevron-up');
});
document.getElementById('businessHoursSection')?.addEventListener('hide.bs.collapse', function() {
    document.querySelector('.business-hours-chevron')?.classList.replace('bi-chevron-up', 'bi-chevron-down');
});
function collectApplyBusinessHours() {
    var data = {};
    document.querySelectorAll('.apply-bh-status').forEach(function(select) {
        var day = select.dataset.day;
        if (select.value !== 'normal') {
            data[day] = select.value;
            return;
        }
        var start = document.querySelector('.apply-bh-start[data-day="' + day + '"]').value;
        var end = document.querySelector('.apply-bh-end[data-day="' + day + '"]').value;
        data[day] = start && end && start < end ? start + '~' + end : '휴무';
    });
    var noLunch = document.getElementById('applyBhNoLunch').checked;
    if (noLunch) {
        data.no_lunch_break = true;
    } else {
        var lunchStart = document.getElementById('applyBhLunchStart').value;
        var lunchEnd = document.getElementById('applyBhLunchEnd').value;
        if (lunchStart && lunchEnd && lunchStart < lunchEnd) data['점심'] = lunchStart + '~' + lunchEnd;
    }
    data.special_closed_dates = applyBhClosedDates.slice().sort();
    document.getElementById('businessHoursInput').value = JSON.stringify(data);
}
renderApplyBhClosedDates();
document.getElementById('applyForm')?.addEventListener('submit', collectApplyBusinessHours);

document.querySelector('[name="business_number"]')?.addEventListener('input', function(e) {
    var v = e.target.value.replace(/[^0-9]/g, '');
    if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
    else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
    e.target.value = v;
});

document.getElementById('checkPhoneDupBtn')?.addEventListener('click', function() {
    var btn = this;
    var input = document.getElementById('hospitalPhoneInput');
    var resultEl = document.getElementById('checkPhoneDupResult');
    var phone = (input.value || '').trim();
    if (!phone) {
        resultEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>전화번호를 입력해 주세요.</span>';
        return;
    }
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    resultEl.innerHTML = '';
    fetch('hospital_apply.php?check_phone=' + encodeURIComponent(phone), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.ok) {
                resultEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>' + applicationEscape(data.error || '확인 중 오류가 발생했습니다.') + '</span>';
            } else if (data.duplicate) {
                resultEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>이미 \'' + applicationEscape(data.hospital_name) + '\' 병원에서 사용 중인 전화번호입니다.</span>';
            } else {
                resultEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>사용 가능한 전화번호입니다.</span>';
            }
        })
        .catch(function() {
            resultEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>확인 중 오류가 발생했습니다.</span>';
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});
</script>

<?php if (isDevMode($connect)): ?>
    <?= getDevModeJS_hospital_apply(); ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

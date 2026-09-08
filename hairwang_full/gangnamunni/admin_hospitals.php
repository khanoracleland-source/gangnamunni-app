<?php


session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/hospital_phone_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/gangnamunni/');

require_admin_login();
require_permission('hospitals');

if (empty($_SESSION['admin_hospitals_csrf'])) {
    $_SESSION['admin_hospitals_csrf'] = bin2hex(random_bytes(32));
}
$admin_hospitals_csrf = $_SESSION['admin_hospitals_csrf'];

require_once __DIR__ . '/includes/hospital_filter.php';
require_once __DIR__ . '/includes/hospital_provision_helper.php'; 
$my_ids = get_hospital_filter_ids($connect);
if (($_SESSION['ad_admin_role'] ?? '') === 'editor' && $my_ids === null) {
    $ed_hid = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    $my_ids = $ed_hid > 0 ? [$ed_hid] : [];
}
$my_in  = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

if ($my_ids !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_pa = $_POST['action'] ?? '';
    $_platform_only = ['approve', 'reject', 'bulk_approve', 'bulk_reject', 'bulk_delete', 'delete_hospital'];
    $_target_hid = intval($_POST['hospital_id'] ?? 0);
    if (in_array($_pa, $_platform_only, true) || !in_array($_target_hid, $my_ids, true)) {
        header('Location: admin_hospitals.php?msg=' . urlencode('접근 권한이 없습니다.'));
        exit;
    }
}

$message = '';
$error = '';
$current_admin = get_current_admin();


function admin_hospital_store_image(array $file, $hospital_id, array $allowed_mimes, array $allowed_extensions, $max_bytes, $prefix) {
    $upload_error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($upload_error === UPLOAD_ERR_NO_FILE) return null;
    if ($upload_error !== UPLOAD_ERR_OK) return ['error' => '이미지 업로드에 실패했습니다. (코드: ' . $upload_error . ')'];

    $tmp_name = (string)($file['tmp_name'] ?? '');
    $original_name = (string)($file['name'] ?? '');
    $file_size = (int)($file['size'] ?? 0);
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) return ['error' => '올바른 업로드 파일이 아닙니다.'];
    if ($file_size <= 0 || $file_size > $max_bytes) return ['error' => '허용된 이미지 용량을 초과했습니다.'];

    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp_name);
    if (!isset($allowed_mimes[$mime]) || !in_array($extension, $allowed_extensions, true)) {
        return ['error' => '허용되지 않은 이미지 형식입니다.'];
    }

    $relative_dir = 'uploads/hospitals/' . (int)$hospital_id . '/';
    $upload_dir = __DIR__ . '/' . $relative_dir;
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['error' => '이미지 업로드 폴더를 준비할 수 없습니다.'];
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
    $absolute_path = $upload_dir . $filename;
    if (!move_uploaded_file($tmp_name, $absolute_path)) return ['error' => '이미지 파일 저장에 실패했습니다.'];

    return ['url' => $relative_dir . $filename, 'path' => $absolute_path];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['admin_csrf']) || !hash_equals($admin_hospitals_csrf, (string)$_POST['admin_csrf'])) {
        http_response_code(403);
        exit('잘못된 요청입니다. 페이지를 새로고침한 후 다시 시도해주세요.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hospital_images') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $uploaded_files = [];
        $main_upload = null;
        $interior_uploads = [];

        if ($hospital_id <= 0) {
            $error = '병원 정보가 올바르지 않습니다.';
        } else {
            $main_upload = admin_hospital_store_image(
                $_FILES['admin_main_image'] ?? [],
                $hospital_id,
                ['image/jpeg' => 'jpg', 'image/png' => 'png'],
                ['jpg', 'jpeg', 'png'],
                5 * 1024 * 1024,
                'main'
            );
            if (is_array($main_upload) && isset($main_upload['error'])) {
                $error = '메인 이미지: ' . $main_upload['error'];
            } elseif (is_array($main_upload)) {
                $uploaded_files[] = $main_upload['path'];
            }

            $interior_names = $_FILES['admin_interior_images']['name'] ?? [];
            $requested_interior_count = is_array($interior_names) ? count(array_filter($interior_names, 'strlen')) : 0;
            $existing_interior_count = 0;
            $count_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS cnt FROM hospital_images WHERE hospital_id=? AND is_active=1 AND image_type NOT IN ('main','logo')");
            if ($count_stmt) {
                mysqli_stmt_bind_param($count_stmt, 'i', $hospital_id);
                mysqli_stmt_execute($count_stmt);
                $count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
                $existing_interior_count = (int)($count_row['cnt'] ?? 0);
                mysqli_stmt_close($count_stmt);
            } else {
                $error = '기존 이미지 수를 확인할 수 없습니다.';
            }
            if ($error === '' && $existing_interior_count + $requested_interior_count > 10) {
                $error = '내부 상세 이미지는 기존 이미지를 포함해 최대 10장까지 등록할 수 있습니다.';
            }

            if ($error === '' && is_array($interior_names)) {
                foreach ($interior_names as $index => $name) {
                    if ($name === '') continue;
                    $file = [
                        'name' => $name,
                        'tmp_name' => $_FILES['admin_interior_images']['tmp_name'][$index] ?? '',
                        'size' => $_FILES['admin_interior_images']['size'][$index] ?? 0,
                        'error' => $_FILES['admin_interior_images']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    ];
                    $upload = admin_hospital_store_image(
                        $file,
                        $hospital_id,
                        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                        ['jpg', 'jpeg', 'png', 'webp'],
                        10 * 1024 * 1024,
                        'interior'
                    );
                    if (is_array($upload) && isset($upload['error'])) {
                        $error = '내부 상세 이미지: ' . $upload['error'];
                        break;
                    }
                    if (is_array($upload)) {
                        $interior_uploads[] = $upload;
                        $uploaded_files[] = $upload['path'];
                    }
                }
            }

            if ($error === '' && !$main_upload && !$interior_uploads) {
                $error = '추가할 이미지를 선택해 주세요.';
            }

            if ($error === '') {
                mysqli_begin_transaction($connect);
                try {
                    if (is_array($main_upload)) {
                        $main_url = $main_upload['url'];
                        $main_id = 0;
                        $main_find = mysqli_prepare($connect, "SELECT id FROM hospital_images WHERE hospital_id=? AND image_type='main' ORDER BY is_active DESC, sort_order ASC, id ASC LIMIT 1");
                        if (!$main_find) throw new RuntimeException('기존 메인 이미지 조회에 실패했습니다.');
                        mysqli_stmt_bind_param($main_find, 'i', $hospital_id);
                        if (!mysqli_stmt_execute($main_find)) throw new RuntimeException('기존 메인 이미지 조회에 실패했습니다.');
                        $main_row = mysqli_fetch_assoc(mysqli_stmt_get_result($main_find));
                        $main_id = (int)($main_row['id'] ?? 0);
                        mysqli_stmt_close($main_find);

                        if ($main_id > 0) {
                            $main_stmt = mysqli_prepare($connect, "UPDATE hospital_images SET image_url=?, sort_order=0, is_active=1 WHERE id=? AND hospital_id=?");
                            if (!$main_stmt) throw new RuntimeException('메인 이미지 DB 준비에 실패했습니다.');
                            mysqli_stmt_bind_param($main_stmt, 'sii', $main_url, $main_id, $hospital_id);
                        } else {
                            $main_type = 'main';
                            $main_order = 0;
                            $main_stmt = mysqli_prepare($connect, "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                            if (!$main_stmt) throw new RuntimeException('메인 이미지 DB 준비에 실패했습니다.');
                            mysqli_stmt_bind_param($main_stmt, 'issi', $hospital_id, $main_url, $main_type, $main_order);
                        }
                        if (!mysqli_stmt_execute($main_stmt)) throw new RuntimeException('메인 이미지 DB 저장에 실패했습니다.');
                        if ($main_id <= 0) $main_id = (int)mysqli_insert_id($connect);
                        mysqli_stmt_close($main_stmt);
                        $disable_other_main = mysqli_prepare($connect, "UPDATE hospital_images SET is_active=0 WHERE hospital_id=? AND image_type='main' AND id<>?");
                        if (!$disable_other_main) throw new RuntimeException('기존 메인 이미지 정리에 실패했습니다.');
                        mysqli_stmt_bind_param($disable_other_main, 'ii', $hospital_id, $main_id);
                        if (!mysqli_stmt_execute($disable_other_main)) throw new RuntimeException('기존 메인 이미지 정리에 실패했습니다.');
                        mysqli_stmt_close($disable_other_main);
                        $sync_main = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET main_image_url=?, updated_at=NOW() WHERE id=?");
                        if (!$sync_main) throw new RuntimeException('메인 이미지 동기화 준비에 실패했습니다.');
                        mysqli_stmt_bind_param($sync_main, 'si', $main_url, $hospital_id);
                        if (!mysqli_stmt_execute($sync_main)) throw new RuntimeException('메인 이미지 동기화에 실패했습니다.');
                        mysqli_stmt_close($sync_main);
                    }

                    if ($interior_uploads) {
                        $sort_result = mysqli_query($connect, "SELECT COALESCE(MAX(sort_order),0) AS max_sort FROM hospital_images WHERE hospital_id=" . (int)$hospital_id);
                        $sort_row = $sort_result ? mysqli_fetch_assoc($sort_result) : null;
                        $sort_order = (int)($sort_row['max_sort'] ?? 0);
                        $interior_type = 'interior';
                        $interior_stmt = mysqli_prepare($connect, "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                        if (!$interior_stmt) throw new RuntimeException('내부 이미지 DB 준비에 실패했습니다.');
                        foreach ($interior_uploads as $interior_upload) {
                            $sort_order++;
                            $interior_url = $interior_upload['url'];
                            mysqli_stmt_bind_param($interior_stmt, 'issi', $hospital_id, $interior_url, $interior_type, $sort_order);
                            if (!mysqli_stmt_execute($interior_stmt)) throw new RuntimeException('내부 이미지 DB 저장에 실패했습니다.');
                        }
                        mysqli_stmt_close($interior_stmt);
                    }

                    mysqli_commit($connect);
                    $added_count = count($interior_uploads) + (is_array($main_upload) ? 1 : 0);
                    $message = $added_count . '장의 병원 이미지가 저장되었습니다.';
                } catch (Throwable $image_exception) {
                    mysqli_rollback($connect);
                    foreach ($uploaded_files as $uploaded_path) {
                        if (is_file($uploaded_path)) @unlink($uploaded_path);
                    }
                    error_log('[admin_hospitals] image upload failed: ' . $image_exception->getMessage());
                    $error = $image_exception->getMessage();
                }
            } elseif ($uploaded_files) {
                foreach ($uploaded_files as $uploaded_path) {
                    if (is_file($uploaded_path)) @unlink($uploaded_path);
                }
            }
        }
    }

    if ($action === 'add_hospital_doctors') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $doctor_names = is_array($_POST['doctor_name'] ?? null) ? $_POST['doctor_name'] : [];
        $doctor_specialties = is_array($_POST['doctor_specialty'] ?? null) ? $_POST['doctor_specialty'] : [];
        $doctor_experiences = is_array($_POST['doctor_experience'] ?? null) ? $_POST['doctor_experience'] : [];
        $doctor_qualifications = is_array($_POST['doctor_qualification'] ?? null) ? $_POST['doctor_qualification'] : [];
        $doctor_bios = is_array($_POST['doctor_bio'] ?? null) ? $_POST['doctor_bio'] : [];
        $stored_doctor_photos = [];
        $valid_doctor_count = 0;

        if ($hospital_id <= 0) {
            $error = '병원 정보가 올바르지 않습니다.';
        } elseif (count($doctor_names) > 20) {
            $error = '의사는 한 번에 최대 20명까지 추가할 수 있습니다.';
        } else {
            mysqli_begin_transaction($connect);
            try {
                $doctor_insert = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_doctors (hospital_id, name, specialty, experience, qualification, bio, photo_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, '', 'active', NOW(), NOW())"
                );
                if (!$doctor_insert) throw new RuntimeException('의사 정보 DB 준비에 실패했습니다.');

                foreach ($doctor_names as $doctor_index => $doctor_name_raw) {
                    $doctor_name = trim((string)$doctor_name_raw);
                    if ($doctor_name === '') continue;

                    $doctor_specialty = trim((string)($doctor_specialties[$doctor_index] ?? ''));
                    $doctor_experience = max(0, min(80, (int)($doctor_experiences[$doctor_index] ?? 0)));
                    $doctor_qualification = trim((string)($doctor_qualifications[$doctor_index] ?? ''));
                    $doctor_bio = trim((string)($doctor_bios[$doctor_index] ?? ''));
                    mysqli_stmt_bind_param($doctor_insert, 'ississ',
                        $hospital_id, $doctor_name, $doctor_specialty, $doctor_experience, $doctor_qualification, $doctor_bio
                    );
                    if (!mysqli_stmt_execute($doctor_insert)) throw new RuntimeException('의사 정보 저장에 실패했습니다.');
                    $doctor_id = (int)mysqli_insert_id($connect);

                    $photo_name = (string)($_FILES['doctor_photo']['name'][$doctor_index] ?? '');
                    if ($photo_name !== '') {
                        $photo_upload = admin_hospital_store_image([
                            'name' => $photo_name,
                            'tmp_name' => $_FILES['doctor_photo']['tmp_name'][$doctor_index] ?? '',
                            'size' => $_FILES['doctor_photo']['size'][$doctor_index] ?? 0,
                            'error' => $_FILES['doctor_photo']['error'][$doctor_index] ?? UPLOAD_ERR_NO_FILE,
                        ], $hospital_id,
                            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                            ['jpg', 'jpeg', 'png', 'webp'],
                            5 * 1024 * 1024,
                            'doctor_' . $doctor_id
                        );
                        if (is_array($photo_upload) && isset($photo_upload['error'])) {
                            throw new RuntimeException($doctor_name . ' 의사 사진: ' . $photo_upload['error']);
                        }
                        if (is_array($photo_upload)) {
                            $stored_doctor_photos[] = $photo_upload['path'];
                            $photo_url = $photo_upload['url'];
                            $photo_update = mysqli_prepare($connect, "UPDATE gangnamunni_doctors SET photo_url=? WHERE id=? AND hospital_id=?");
                            if (!$photo_update) throw new RuntimeException('의사 사진 DB 준비에 실패했습니다.');
                            mysqli_stmt_bind_param($photo_update, 'sii', $photo_url, $doctor_id, $hospital_id);
                            if (!mysqli_stmt_execute($photo_update)) throw new RuntimeException('의사 사진 저장에 실패했습니다.');
                            mysqli_stmt_close($photo_update);
                        }
                    }
                    $valid_doctor_count++;
                }
                mysqli_stmt_close($doctor_insert);

                if ($valid_doctor_count === 0) throw new RuntimeException('추가할 의사의 이름을 입력해 주세요.');
                mysqli_commit($connect);
                $message = $valid_doctor_count . '명의 의사가 추가되었습니다.';
            } catch (Throwable $doctor_exception) {
                mysqli_rollback($connect);
                foreach ($stored_doctor_photos as $stored_doctor_photo) {
                    if (is_file($stored_doctor_photo)) @unlink($stored_doctor_photo);
                }
                error_log('[admin_hospitals] doctor add failed: ' . $doctor_exception->getMessage());
                $error = $doctor_exception->getMessage();
            }
        }
    }

    if ($action === 'approve') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $stmt_info = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt_info, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_info);
            $hospital_info = mysqli_stmt_get_result($stmt_info)->fetch_assoc();
            mysqli_stmt_close($stmt_info);

            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET status='approved', is_active=1, ad_grade='none', reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?"
            );
            $admin_name = $current_admin['name'];
            mysqli_stmt_bind_param($stmt, 'si', $admin_name, $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 승인되었습니다.";

                if ($hospital_info) provision_hospital_accounts($connect, $hospital_info);

                notifyAdmins($connect, 'hospital_approve',
                    '병원 승인 완료',
                    '병원 #' . $hospital_id . ' 승인됨',
                    'admin_hospitals.php'
                );
            } else {
                $error = '승인 처리 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'reject') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $reject_reason = trim($_POST['reject_reason'] ?? '');
        if ($hospital_id > 0) {
            $stmt_info2 = mysqli_prepare($connect, "SELECT name, email, contact_name FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt_info2, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_info2);
            $reject_info = mysqli_stmt_get_result($stmt_info2)->fetch_assoc();
            mysqli_stmt_close($stmt_info2);

            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET status='rejected', is_active=0, reviewed_by=?, reviewed_at=NOW(), reject_reason=?, rejection_reason=?, updated_at=NOW() WHERE id=?"
            );
            $admin_name = $current_admin['name'];
            mysqli_stmt_bind_param($stmt, 'sssi', $admin_name, $reject_reason, $reject_reason, $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 반려되었습니다.";

                if ($reject_info && !empty($reject_info['email']) && file_exists(__DIR__ . '/includes/mail_helper.php')) {
                    require_once __DIR__ . '/includes/mail_helper.php';
                    $reject_body = "<h2>병원 등록 신청 반려 안내</h2>
                        <p>안녕하세요, <strong>" . htmlspecialchars($reject_info['contact_name'] ?? $reject_info['name']) . "</strong>님.</p>
                        <p><strong>" . htmlspecialchars($reject_info['name']) . "</strong>의 입점 신청이 반려되었습니다.</p>
                        <div style='background:#fff3cd;padding:15px;border-radius:8px;margin:15px 0;'>
                            <strong>반려 사유:</strong><br>" . nl2br(htmlspecialchars($reject_reason)) . "
                        </div>
                        <p>서류를 보완하신 후 재신청 가능합니다.</p>
                        <p><a href='" . SITE_FULL_URL . "hospital_apply.php'>재신청하기</a></p>
                        <p>감사합니다.<br>서울미녀 팀</p>";
                    try {
                        $reject_mail = mail_send(0, $reject_info['email'], '[서울미녀] 병원 등록 신청 반려 안내', $reject_body);
                        if (!(is_array($reject_mail) ? !empty($reject_mail['success']) : (bool)$reject_mail)) error_log('[Hospital Reject] 이메일 발송 실패 → ' . $reject_info['email']);
                    } catch (Throwable $e) { error_log('[Hospital Reject] 이메일 예외: ' . $e->getMessage()); }
                }

                notifyAdmins($connect, 'hospital_reject',
                    '병원 신청 반려',
                    htmlspecialchars($reject_info['name'] ?? '') . ' — ' . mb_substr($reject_reason, 0, 50),
                    'admin_hospitals.php'
                );
            } else {
                $error = '반려 처리 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'edit_hospital') {
        @ini_set('memory_limit', '256M');
        @set_time_limit(60);
        try {
            $hospital_id = intval($_POST['hospital_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $owner_name = trim($_POST['owner_name'] ?? '');
            $business_number = trim($_POST['business_number'] ?? '');
            $introduction = trim($_POST['introduction'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if ($phone !== '') $phone = hospital_phone_format($phone);
            $email = trim($_POST['email'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $sns_url = trim($_POST['sns_url'] ?? '');
            $normalize_json_list = function($raw) {
                $raw = trim($raw);
                if ($raw === '') return '[]';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $raw;
                $items = array_values(array_filter(array_map('trim', explode(',', $raw)), function($v){ return $v !== ''; }));
                return json_encode($items, JSON_UNESCAPED_UNICODE);
            };
            $normalize_json_obj = function($raw) {
                $raw = trim($raw);
                if ($raw === '') return '{}';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $raw;
                return '{}';
            };
            $categories = $normalize_json_list($_POST['categories'] ?? '');
            $badges = $normalize_json_list($_POST['badges'] ?? '');
            $treatment_tags = $normalize_json_list($_POST['treatment_tags'] ?? '');
            $subway_names = $normalize_json_list($_POST['subway_names'] ?? '');
            $business_hours = $normalize_json_obj($_POST['business_hours'] ?? '');
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $show_rating = isset($_POST['show_rating']) ? 1 : 0;
            $ad_grade_input = trim($_POST['ad_grade'] ?? 'none');
            $allowed_grades = ['S','A','B','C','D','E','none'];
            $ad_grade = in_array($ad_grade_input, $allowed_grades) ? $ad_grade_input : 'none';

            if ($hospital_id <= 0 || empty($name)) {
                $error = '병원 ID와 이름은 필수입니다.';
            } elseif ($phone !== '' && ($duplicate_phone_hospital = hospital_phone_find_duplicate($connect, $phone, $hospital_id))) {
                $error = "이미 '{$duplicate_phone_hospital['name']}' 병원에서 사용 중인 전화번호입니다.";
            } else {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospitals SET name=?, owner_name=?, business_number=?, introduction=?, address=?, phone=?, email=?, contact_name=?, contact_phone=?, website=?, sns_url=?, categories=?, badges=?, treatment_tags=?, subway_names=?, business_hours=?, latitude=?, longitude=?, show_rating=?, ad_grade=?, updated_at=NOW() WHERE id=?"
                );
                if (!$stmt) {
                    $err_msg = mysqli_error($connect);
                    error_log("[admin_hospitals #24] Prepare 실패: {$err_msg}");
                    $error = 'SQL Prepare 실패: ' . $err_msg;
                } else {
                    if (!mysqli_stmt_bind_param($stmt, 'ssssssssssssssssddisi',
                        $name, $owner_name, $business_number, $introduction, $address, $phone, $email,
                        $contact_name, $contact_phone, $website, $sns_url,
                        $categories, $badges, $treatment_tags, $subway_names, $business_hours,
                        $latitude, $longitude, $show_rating, $ad_grade, $hospital_id
                    )) {
                        $err_msg = mysqli_stmt_error($stmt);
                        error_log("[admin_hospitals #24] BindParam 실패: {$err_msg}");
                        $error = 'Bind Param 실패: ' . $err_msg;
                    } elseif (mysqli_stmt_execute($stmt)) {
                        $message = "병원 '{$name}' 정보가 수정되었습니다.";
                    } else {
                        $err_msg = mysqli_error($connect);
                        error_log("[admin_hospitals #24] Execute 실패: {$err_msg} / hospital_id={$hospital_id}");
                        $error = '수정 실패: ' . $err_msg;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        } catch (Throwable $e) {
            error_log("[admin_hospitals #24] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $error = '시스템 오류가 발생했습니다. 관리자에게 문의하세요. (' . $e->getMessage() . ')';
        }
    }


    if ($action === 'delete_hospital') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $stmt_img = mysqli_prepare($connect, "DELETE FROM hospital_images WHERE hospital_id=?");
            mysqli_stmt_bind_param($stmt_img, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_img);
            mysqli_stmt_close($stmt_img);

            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 삭제되었습니다.";
                if (isset($_GET['view'])) {
                    header('Location: admin_hospitals.php?msg=' . urlencode($message));
                    exit;
                }
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'bulk_delete') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        if (!empty($ids)) {
            $placeholders = implode(',', $ids);
            mysqli_query($connect, "DELETE FROM hospital_images WHERE hospital_id IN ({$placeholders})");
            mysqli_query($connect, "DELETE FROM gangnamunni_hospital_revisions WHERE hospital_id IN ({$placeholders})");
            mysqli_query($connect, "DELETE FROM gangnamunni_hospitals WHERE id IN ({$placeholders})");
            $message = count($ids) . '개 병원이 삭제되었습니다.';
        }
    }

    if ($action === 'bulk_approve') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        if (!empty($ids)) {
            $admin_name = $current_admin['name'];
            foreach ($ids as $bid) {
                $stmt_ba = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET status='approved', is_active=1, ad_grade='none', reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt_ba, 'si', $admin_name, $bid);
                mysqli_stmt_execute($stmt_ba);
                $ba_approved = mysqli_stmt_affected_rows($stmt_ba) > 0;
                mysqli_stmt_close($stmt_ba);
                if ($ba_approved) {
                    $ba_hi_r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id=" . (int)$bid);
                    $ba_hi = $ba_hi_r ? mysqli_fetch_assoc($ba_hi_r) : null;
                    if ($ba_hi) provision_hospital_accounts($connect, $ba_hi);
                }
            }
            $message = count($ids) . '개 병원이 승인되었습니다.';
        }
    }

    if ($action === 'bulk_reject') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $reason = trim($_POST['reject_reason'] ?? '일괄 반려');
        if (!empty($ids)) {
            $admin_name = $current_admin['name'];
            foreach ($ids as $bid) {
                $stmt_br = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET status='rejected', is_active=0, reviewed_by=?, reviewed_at=NOW(), reject_reason=?, rejection_reason=? WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt_br, 'sssi', $admin_name, $reason, $reason, $bid);
                mysqli_stmt_execute($stmt_br);
                mysqli_stmt_close($stmt_br);
            }
            $message = count($ids) . '개 병원이 반려되었습니다.';
        }
    }

    if ($action === 'mark_reviewed') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            mysqli_query($connect, "UPDATE gangnamunni_hospital_revisions SET reviewed=1 WHERE hospital_id={$hospital_id} AND reviewed=0");
            $stmt_mr = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET has_unreviewed_changes=0 WHERE id=?");
            mysqli_stmt_bind_param($stmt_mr, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_mr);
            mysqli_stmt_close($stmt_mr);
            $message = '수정 내역을 확인 완료했습니다.';
        }
    }

    if ($action === 'toggle_active') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET is_active = IF(is_active=1, 0, 1), updated_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '활성 상태가 변경되었습니다.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action === 'save_ai_settings') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $ai_enabled = isset($_POST['ai_enabled']) ? 1 : 0;
            $ai_use_own_key = isset($_POST['ai_use_own_key']) ? 1 : 0;
            $ai_own_provider = in_array($_POST['ai_own_provider'] ?? '', ['openai','anthropic','google']) ? $_POST['ai_own_provider'] : 'openai';
            $ai_cost_from_wallet = isset($_POST['ai_cost_from_wallet']) ? 1 : 0;

            $ai_key_raw = trim($_POST['ai_own_api_key'] ?? '');
            $ai_key_sql = '';
            if (!empty($ai_key_raw) && strpos($ai_key_raw, '****') === false) {
                require_once __DIR__ . '/includes/ai_proxy.php';
                $ai_key_encrypted = aiEncryptKey($ai_key_raw);
                $ai_key_sql = ", ai_own_api_key = '" . mysqli_real_escape_string($connect, $ai_key_encrypted) . "'";
            }

            $sql = "UPDATE gangnamunni_hospitals SET ai_enabled={$ai_enabled}, ai_use_own_key={$ai_use_own_key}, ai_own_provider='{$ai_own_provider}', ai_cost_from_wallet={$ai_cost_from_wallet}{$ai_key_sql}, updated_at=NOW() WHERE id={$hospital_id}";
            if (mysqli_query($connect, $sql)) {
                $message = 'AI 설정이 저장되었습니다.';
            } else {
                $error = 'AI 설정 저장 실패: ' . mysqli_error($connect);
            }
        }
    }

    if (!empty($message) && isset($_GET['view'])) {
        $view_id = intval($_GET['view']);
        $edit_flag = isset($_GET['edit']) ? '&edit=1' : '';
        header('Location: admin_hospitals.php?view=' . $view_id . $edit_flag . '&msg=' . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

$view_mode = isset($_GET['view']) ? intval($_GET['view']) : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$hospital = null;
$hospital_images = [];
$application_doctors = [];

if ($view_mode > 0) {
    if ($my_ids !== null && !in_array($view_mode, $my_ids, true)) {
        header('Location: admin_hospitals.php?msg=' . urlencode('접근 권한이 없습니다.'));
        exit;
    }
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $view_mode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hospital = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($hospital) {
        $img_stmt = mysqli_prepare($connect, "SELECT * FROM hospital_images WHERE hospital_id = ? ORDER BY sort_order ASC, id ASC");
        mysqli_stmt_bind_param($img_stmt, 'i', $view_mode);
        mysqli_stmt_execute($img_stmt);
        $img_result = mysqli_stmt_get_result($img_stmt);
        while ($img_row = mysqli_fetch_assoc($img_result)) {
            $hospital_images[] = $img_row;
        }
        mysqli_stmt_close($img_stmt);

        try {
            $doctor_stmt = mysqli_prepare($connect,
                "SELECT id, name, specialty, experience, qualification, bio, photo_url, status
                 FROM gangnamunni_doctors WHERE hospital_id = ? ORDER BY id ASC");
            if ($doctor_stmt) {
                mysqli_stmt_bind_param($doctor_stmt, 'i', $view_mode);
                mysqli_stmt_execute($doctor_stmt);
                $doctor_result = mysqli_stmt_get_result($doctor_stmt);
                while ($doctor_row = mysqli_fetch_assoc($doctor_result)) $application_doctors[] = $doctor_row;
                mysqli_stmt_close($doctor_stmt);
            }
        } catch (\mysqli_sql_exception $e) {
            error_log('[admin_hospitals] 신청 의사 정보 조회 실패: ' . $e->getMessage());
        }
    }
}

$filter_status = $_GET['status'] ?? 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50])) $per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = '1=1';
$where_params = [];
$where_types = '';

$stat_scope = '';
if ($my_in !== null) {
    $where .= ' AND id IN (' . $my_in . ')';
    $stat_scope = ' AND id IN (' . $my_in . ')';
}

if ($filter_status === 'pending') {
    $where .= " AND status = 'pending'";
} elseif ($filter_status === 'approved') {
    $where .= " AND status = 'approved'";
} elseif ($filter_status === 'rejected') {
    $where .= " AND status = 'rejected'";
}

if (!empty($search)) {
    $where .= ' AND (name LIKE ? OR address LIKE ? OR phone LIKE ? OR email LIKE ? OR categories LIKE ?)';
    $search_param = "%{$search}%";
    $where_params = [$search_param, $search_param, $search_param, $search_param, $search_param];
    $where_types = 'sssss';
}

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE 1=1{$stat_scope}");
if ($stat_result) $stats['total'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='pending'{$stat_scope}");
if ($stat_result) $stats['pending'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='approved'{$stat_scope}");
if ($stat_result) $stats['approved'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='rejected'{$stat_scope}");
if ($stat_result) $stats['rejected'] = (int)mysqli_fetch_assoc($stat_result)['c'];

$count_sql = "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE {$where}";
if (!empty($where_params)) {
    $count_stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($count_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['c'];
    mysqli_stmt_close($count_stmt);
} else {
    $total = (int)mysqli_fetch_assoc(mysqli_query($connect, $count_sql))['c'];
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

$hospitals = [];
if (!$view_mode) {
    $list_sql = "SELECT * FROM gangnamunni_hospitals WHERE {$where} ORDER BY created_at DESC LIMIT ?, ?";
    if (!empty($where_params)) {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        $bind_types = $where_types . 'ii';
        $bind_params = array_merge($where_params, [$offset, $per_page]);
        mysqli_stmt_bind_param($list_stmt, $bind_types, ...$bind_params);
    } else {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        mysqli_stmt_bind_param($list_stmt, 'ii', $offset, $per_page);
    }
    mysqli_stmt_execute($list_stmt);
    $list_result = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($list_result)) {
        $hospitals[] = $row;
    }
    mysqli_stmt_close($list_stmt);
}

function buildHospitalUrl($overrides = []) {
    global $filter_status, $per_page, $search;
    $params = [
        'status' => $overrides['status'] ?? $filter_status,
        'per_page' => $overrides['per_page'] ?? $per_page,
        'page' => $overrides['page'] ?? 1,
    ];
    if (!empty($overrides['search'] ?? $search)) {
        $params['search'] = $overrides['search'] ?? $search;
    }
    return '?' . http_build_query($params);
}

function statusBadge($status) {
    $map = [
        'pending'  => ['bg-warning text-dark', '대기'],
        'approved' => ['bg-success', '승인'],
        'rejected' => ['bg-danger', '반려'],
    ];
    $info = $map[$status] ?? ['bg-secondary', $status];
    return '<span class="badge ' . $info[0] . '">' . htmlspecialchars($info[1]) . '</span>';
}

// 페이지네이션 AJAX 부분 갱신: 전체 레이아웃(헤더/필터/통계) 없이 목록 패널 HTML만 반환하고 종료한다.
// 목록 모드(view=0)에서만 유효 — 상세/수정 화면에는 이 패널이 없다.
if (!$view_mode && isset($_GET['ajax_list']) && $_GET['ajax_list'] === '1') {
    include __DIR__ . '/includes/admin_hospitals_list_partial.php';
    exit;
}

$admin_page_title = '병원 관리';
$admin_current = 'hospitals';

$admin_extra_css = '
.hospital-stat-card { border: none; border-radius: 12px; transition: transform 0.2s; cursor: pointer; }
.hospital-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.hospital-stat-card.active-filter { box-shadow: 0 0 0 2px var(--bs-primary); }
.hospital-detail-label { font-size: 12px; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: 2px; }
.hospital-detail-value { font-size: 14px; margin-bottom: 12px; }
.hospital-img-large { max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 8px; }
.application-asset { height: 100%; border: 1px solid var(--bs-border-color); border-radius: 10px; overflow: hidden; background: var(--bs-body-bg); }
.application-asset-preview { height: 210px; display: flex; align-items: center; justify-content: center; background: var(--bs-tertiary-bg); overflow: hidden; }
.application-asset-preview img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
.application-asset-missing { color: var(--bs-secondary-color); text-align: center; }

.category-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
.tag-badge { font-size: 11px; padding: 2px 6px; border-radius: 4px; }
/* 병원 목록 테이블: 전화/카테고리처럼 길어질 수 있는 셀을 최대 2줄로 제한하고 넘치면 ... 처리.
   행 높이가 무한정 늘어나 하단 페이지네이션이 아래로 밀리는 것을 방지한다. */
.admin-table .cell-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    line-height: 1.3;
}
/* 모든 행의 높이를 정확히 고정: 셀 내용이 cell-clamp-2로 이미 최대 2줄로 제한되므로,
   td 자체에도 동일한 고정 높이를 줘서 행마다 더 크거나 작아지지 않고 항상 일정하게 만든다. */
.admin-table td {
    height: 56px;
    vertical-align: middle;
    overflow: hidden;
}
';

// Render in a local scope so the treatment controller cannot overwrite hospital-page state.
$hospital_treatment_panel = '';
if ($hospital && $view_mode > 0) {
    if (check_permission('hospital_treatments')) {
        $hospital_treatment_panel = (static function ($connect, $ht_panel_hospital_id, $ht_panel_edit) {
            ob_start();
            try {
                include __DIR__ . '/admin_hospital_treatments.php';
                return ob_get_clean();
            } catch (Throwable $exception) {
                ob_end_clean();
                throw $exception;
            }
        })($connect, $view_mode, $edit_mode);
    } else {
        $hospital_treatment_panel = '<div class="alert alert-warning m-3">시술 관리 권한이 없습니다.</div>';
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($view_mode && $hospital): ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="admin_hospitals.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="bi bi-arrow-left"></i> 목록으로
        </a>
        <span class="fw-bold fs-5"><?php echo htmlspecialchars($hospital['name']); ?></span>
        <?php echo statusBadge($hospital['status'] ?? 'pending'); ?>
    </div>
    <div class="d-flex gap-1">
        <?php if (!$edit_mode): ?>
        <a href="?view=<?php echo $hospital['id']; ?>&edit=1" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>수정
        </a>
        <?php else: ?>
        <a href="?view=<?php echo $hospital['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>보기 모드
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit_mode): ?>
<?php
$edit_json_list_value = static function ($raw) {
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) return (string)$raw;
    $labels = [];
    foreach ($decoded as $item) {
        $label = is_array($item) ? ($item['name'] ?? '') : $item;
        if (is_scalar($label) && trim((string)$label) !== '') $labels[] = trim((string)$label);
    }
    return implode(', ', $labels);
};
?>
<form method="POST" id="editHospitalForm">
    <input type="hidden" name="action" value="edit_hospital">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">

    <div class="card mb-3">
        <button class="card-header btn border-0 d-flex justify-content-between align-items-center text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#editBasicInfoSection" aria-controls="editBasicInfoSection" aria-expanded="false"><span><i class="bi bi-pencil me-1"></i> 기본 정보 수정</span><i class="bi bi-chevron-down"></i></button>
        <div class="collapse" id="editBasicInfoSection"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">병원 이름 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?php echo htmlspecialchars($hospital['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">이메일</label>
                    <input type="email" name="email" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">대표자명</label>
                    <input type="text" name="owner_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['owner_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">사업자등록번호</label>
                    <input type="text" name="business_number" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['business_number'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">전화번호</label>
                    <input type="tel" name="phone" class="form-control form-control-sm" inputmode="tel" autocomplete="tel" placeholder="02-1234-5678"
                           value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">담당자명</label>
                    <input type="text" name="contact_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['contact_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">담당자 전화</label>
                    <input type="text" name="contact_phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['contact_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">주소</label>
                    <input type="text" name="address" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['address'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">홈페이지 URL</label>
                    <input type="url" name="website" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['website'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">SNS URL</label>
                    <input type="url" name="sns_url" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hospital['sns_url'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">광고 등급</label>
                    <?php $cur_grade = $hospital['ad_grade'] ?? 'none'; ?>
                    <select name="ad_grade" class="form-select form-select-sm">
                        <option value="none" <?php echo $cur_grade === 'none' ? 'selected' : ''; ?>>없음</option>
                        <option value="S" <?php echo $cur_grade === 'S' ? 'selected' : ''; ?>>S (최우선, 빨간 AD)</option>
                        <option value="A" <?php echo $cur_grade === 'A' ? 'selected' : ''; ?>>A (우선, 빨간 AD)</option>
                        <option value="B" <?php echo $cur_grade === 'B' ? 'selected' : ''; ?>>B (제휴, 파란 뱃지)</option>
                        <option value="C" <?php echo $cur_grade === 'C' ? 'selected' : ''; ?>>C (제휴, 파란 뱃지)</option>
                        <option value="D" <?php echo $cur_grade === 'D' ? 'selected' : ''; ?>>D (제휴, 회색 텍스트)</option>
                        <option value="E" <?php echo $cur_grade === 'E' ? 'selected' : ''; ?>>E (제휴, 회색 텍스트)</option>
                    </select>
                    <div class="form-text">S/A: 빨간 AD 뱃지 | B/C: 파란 제휴 뱃지 | D/E: 회색 제휴 텍스트 | 목록 상위 노출</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">병원 소개</label>
                    <textarea name="introduction" class="form-control form-control-sm" rows="5" placeholder="병원의 특장점, 전문 분야, 진료 철학 등을 입력하세요."><?php echo htmlspecialchars($hospital['introduction'] ?? ''); ?></textarea>
                </div>
            </div>
        </div></div>
    </div>

    <div class="card mb-3">
        <button class="card-header btn border-0 d-flex justify-content-between align-items-center text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#editCategoriesSection" aria-controls="editCategoriesSection" aria-expanded="false"><span><i class="bi bi-tags me-1"></i> 카테고리 / 태그</span><i class="bi bi-chevron-down"></i></button>
        <div class="collapse" id="editCategoriesSection"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">카테고리 (쉼표 구분)</label>
                    <input type="text" name="categories" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($edit_json_list_value($hospital['categories'] ?? '')); ?>"
                           placeholder="성형외과, 피부과, 치과">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">뱃지 (쉼표 구분)</label>
                    <input type="text" name="badges" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($edit_json_list_value($hospital['badges'] ?? '')); ?>"
                           placeholder="인기, 신규, 이벤트">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">시술 태그 (쉼표 구분)</label>
                    <input type="text" name="treatment_tags" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($edit_json_list_value($hospital['treatment_tags'] ?? '')); ?>"
                           placeholder="보톡스, 필러, 리프팅">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">인근 지하철 (쉼표 구분)</label>
                    <input type="text" name="subway_names" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($edit_json_list_value($hospital['subway_names'] ?? '')); ?>"
                           placeholder="강남역, 신논현역">
                </div>
            </div>
        </div></div>
    </div>

    <div class="card mb-3">
        <button class="card-header btn btn-light border-0 d-flex justify-content-between align-items-center text-start" type="button" data-bs-toggle="collapse" data-bs-target="#editBusinessHoursSection" aria-expanded="false">
            <span><i class="bi bi-clock me-1"></i><strong>영업시간 설정</strong></span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="collapse" id="editBusinessHoursSection"><div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="text-body-secondary small mb-2">요일별 진료시간, 점심시간, 임시 휴무일을 확인·수정합니다.</div>
                    <?php
                    $bh_data = json_decode($hospital['business_hours'] ?? '{}', true) ?: [];
                    $bh_days = ['월'=>'월요일','화'=>'화요일','수'=>'수요일','목'=>'목요일','금'=>'금요일','토'=>'토요일','일'=>'일요일'];
                    $bh_statuses = ['normal'=>'정상진료','off'=>'휴무','construction'=>'공사중','temp_closed'=>'임시휴업'];
                    ?>
                    <input type="hidden" name="business_hours" id="bhHiddenInput" value="<?php echo htmlspecialchars($hospital['business_hours'] ?? ''); ?>">
                    <table class="table table-sm table-bordered mb-2" style="font-size:13px;">
                        <thead><tr><th style="width:75px;">요일</th><th style="width:110px;">시작</th><th style="width:110px;">종료</th><th style="width:110px;">상태</th></tr></thead>
                        <tbody>
                        <?php foreach ($bh_days as $bdk => $bdl):
                            $bval = $bh_data[$bdk] ?? '';
                            $b_is_off = in_array($bval, ['휴무','공사중','임시휴업','']);
                            $b_status = 'normal';
                            if ($bval === '휴무') $b_status = 'off';
                            elseif ($bval === '공사중') $b_status = 'construction';
                            elseif ($bval === '임시휴업') $b_status = 'temp_closed';
                            $b_parts = (!$b_is_off && strpos($bval, '~') !== false) ? explode('~', $bval) : ['', ''];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?php echo $bdl; ?></td>
                            <td><input type="time" class="form-control form-control-sm bh-start" data-day="<?php echo $bdk; ?>" value="<?php echo htmlspecialchars($b_parts[0]); ?>" <?php echo $b_is_off ? 'disabled' : ''; ?>></td>
                            <td><input type="time" class="form-control form-control-sm bh-end" data-day="<?php echo $bdk; ?>" value="<?php echo htmlspecialchars($b_parts[1]); ?>" <?php echo $b_is_off ? 'disabled' : ''; ?>></td>
                            <td>
                                <select class="form-select form-select-sm bh-status" data-day="<?php echo $bdk; ?>" onchange="bhToggle(this)">
                                    <?php foreach ($bh_statuses as $bsv => $bsl): ?>
                                    <option value="<?php echo $bsv; ?>" <?php echo $b_status === $bsv ? 'selected' : ''; ?>><?php echo $bsl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $bh_no_lunch = !empty($bh_data['no_lunch_break']);
                    $bh_lunch = $bh_data['점심'] ?? $bh_data['점심시간'] ?? '';
                    $bh_lunch_parts = strpos($bh_lunch, '~') !== false ? explode('~', $bh_lunch) : ['13:00','14:00'];
                    $bh_special_closed_dates = array_values(array_filter(
                        is_array($bh_data['special_closed_dates'] ?? null) ? $bh_data['special_closed_dates'] : [],
                        function($date) { return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date); }
                    ));
                    ?>
                    <div class="d-flex align-items-center gap-2 mb-2" style="font-size:13px;">
                        <span class="fw-semibold" style="width:75px;">점심시간</span>
                        <div class="form-check mb-0 me-2">
                            <input class="form-check-input" type="checkbox" id="bhNoLunch" <?php echo $bh_no_lunch ? 'checked' : ''; ?> onchange="bhToggleLunch()">
                            <label class="form-check-label text-nowrap" for="bhNoLunch">점심시간 없음</label>
                        </div>
                        <input type="time" class="form-control form-control-sm" id="bhLunchStart" value="<?php echo htmlspecialchars($bh_lunch_parts[0]); ?>" style="width:110px;" <?php echo $bh_no_lunch ? 'disabled' : ''; ?>>
                        <span>~</span>
                        <input type="time" class="form-control form-control-sm" id="bhLunchEnd" value="<?php echo htmlspecialchars($bh_lunch_parts[1] ?? '14:00'); ?>" style="width:110px;" <?php echo $bh_no_lunch ? 'disabled' : ''; ?>>
                    </div>
                    <div class="border rounded p-2 mb-2" style="font-size:13px;">
                        <div class="fw-semibold mb-2">임시 휴무일 (정기 휴무 외)</div>
                        <div class="d-flex gap-2 align-items-center mb-2">
                            <input type="date" class="form-control form-control-sm" id="bhSpecialClosedDate" style="max-width:180px;" min="<?php echo date('Y-m-d'); ?>">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="bhAddSpecialClosedDate()">추가</button>
                        </div>
                        <div id="bhSpecialClosedList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <script>
                    var bhSpecialClosedDates = <?php echo json_encode($bh_special_closed_dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    function bhRenderSpecialClosedDates() {
                        var list = document.getElementById('bhSpecialClosedList');
                        list.innerHTML = '';
                        bhSpecialClosedDates.sort().forEach(function(date) {
                            var badge = document.createElement('span');
                            badge.className = 'badge text-bg-secondary d-inline-flex align-items-center gap-1';
                            badge.textContent = date;
                            var remove = document.createElement('button');
                            remove.type = 'button'; remove.className = 'btn-close btn-close-white';
                            remove.style.fontSize = '8px'; remove.setAttribute('aria-label', '삭제');
                            remove.onclick = function() {
                                bhSpecialClosedDates = bhSpecialClosedDates.filter(function(v) { return v !== date; });
                                bhRenderSpecialClosedDates();
                            };
                            badge.appendChild(remove); list.appendChild(badge);
                        });
                    }
                    function bhAddSpecialClosedDate() {
                        var input = document.getElementById('bhSpecialClosedDate');
                        if (input.value && bhSpecialClosedDates.indexOf(input.value) < 0) {
                            bhSpecialClosedDates.push(input.value); input.value = ''; bhRenderSpecialClosedDates();
                        }
                    }
                    function bhToggle(sel) {
                        var tr = sel.closest('tr');
                        var inputs = tr.querySelectorAll('input[type=time]');
                        var off = (sel.value !== 'normal');
                        inputs.forEach(function(inp) { inp.disabled = off; if (off) inp.value = ''; });
                    }
                    function bhToggleLunch() {
                        var noLunch = document.getElementById('bhNoLunch').checked;
                        document.getElementById('bhLunchStart').disabled = noLunch;
                        document.getElementById('bhLunchEnd').disabled = noLunch;
                    }
                    function bhCollectJSON() {
                        var data = {};
                        var statusMap = {'off':'휴무','construction':'공사중','temp_closed':'임시휴업'};
                        document.querySelectorAll('.bh-status').forEach(function(sel) {
                            var dk = sel.dataset.day;
                            if (sel.value !== 'normal') {
                                data[dk] = statusMap[sel.value] || '휴무';
                            } else {
                                var s = document.querySelector('.bh-start[data-day="'+dk+'"]').value;
                                var e = document.querySelector('.bh-end[data-day="'+dk+'"]').value;
                                data[dk] = (s && e) ? s+'~'+e : '휴무';
                            }
                        });
                        var noLunch = document.getElementById('bhNoLunch').checked;
                        if (noLunch) {
                            data['no_lunch_break'] = true;
                        } else {
                            var ls = document.getElementById('bhLunchStart').value;
                            var le = document.getElementById('bhLunchEnd').value;
                            if (ls && le) data['점심'] = ls+'~'+le;
                        }
                        data['special_closed_dates'] = bhSpecialClosedDates.slice().sort();
                        document.getElementById('bhHiddenInput').value = JSON.stringify(data);
                    }
                    bhRenderSpecialClosedDates();
                    var bhForm = document.getElementById('bhHiddenInput').closest('form');
                    if (bhForm) bhForm.addEventListener('submit', function() { bhCollectJSON(); });
                    </script>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="show_rating" id="showRating" <?php echo (($hospital['show_rating'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="showRating">평점 표시 (OFF 시 상세페이지에서 별점/평점 숨김)</label>
                    </div>
                </div>
            </div>
        </div></div>
    </div>

    <?php
    $picker_config = [
        'collapse_id' => 'editLocationSection',
        'lat_field'  => 'latitude',
        'lng_field'  => 'longitude',
        'addr_field' => 'address',
        'lat_value'  => $hospital['latitude'] ?? '',
        'lng_value'  => $hospital['longitude'] ?? '',
    ];
    include __DIR__ . '/includes/map_picker.php';
    ?>

</form>

<div class="card mt-3">
    <button class="card-header btn border-0 d-flex justify-content-between align-items-center text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#editDocumentsSection" aria-controls="editDocumentsSection" aria-expanded="false"><span><i class="bi bi-file-earmark-check me-1"></i><strong>제출 서류</strong></span><i class="bi bi-chevron-down"></i></button>
    <div class="collapse" id="editDocumentsSection"><div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php foreach ([
                ['사업자등록증', $hospital['cert_business'] ?? ''],
                ['의료기관개설허가증', $hospital['cert_medical'] ?? ''],
            ] as $edit_asset): ?>
                <?php if (!empty($edit_asset[1])): ?>
                <a href="<?= htmlspecialchars($edit_asset[1]) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i><?= htmlspecialchars($edit_asset[0]) ?></a>
                <?php else: ?>
                <span class="btn btn-outline-secondary btn-sm disabled"><?= htmlspecialchars($edit_asset[0]) ?> 미제출</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div></div>
</div>

<div class="card mt-3">
    <button class="card-header btn text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#editTreatmentsSection" aria-expanded="<?= isset($_GET['treatment_msg']) ? 'true' : 'false' ?>">
        <span><i class="bi bi-clipboard2-pulse me-1"></i><strong>시술 추가 / 관리</strong></span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse<?= isset($_GET['treatment_msg']) ? ' show' : '' ?>" id="editTreatmentsSection">
        <div class="card-body p-0"><?= $hospital_treatment_panel ?></div>
    </div>
</div>

<div class="card mt-3">
    <button class="card-header btn btn-light border-0 d-flex justify-content-between align-items-center text-start" type="button" data-bs-toggle="collapse" data-bs-target="#editDoctorsSection" aria-expanded="false">
        <span><i class="bi bi-person-badge me-1"></i><strong>의사 추가</strong></span><span><span class="badge bg-secondary me-2"><?= count($application_doctors) ?>명</span><i class="bi bi-chevron-down"></i></span>
    </button>
    <div class="collapse" id="editDoctorsSection"><div class="card-body">
        <form method="POST" action="?view=<?php echo (int)$hospital['id']; ?>&amp;edit=1" enctype="multipart/form-data" id="adminHospitalDoctorForm">
            <input type="hidden" name="action" value="add_hospital_doctors">
            <input type="hidden" name="hospital_id" value="<?php echo (int)$hospital['id']; ?>">
            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-text mb-3">해당 병원에 소속된 의사를 추가합니다. 여러 명을 입력한 후 한 번에 저장할 수 있습니다.</div>
            <div id="adminDoctorRows"></div>
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="addAdminDoctorButton"><i class="bi bi-plus-circle me-1"></i>다른 의사 추가</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>의사 저장</button>
            </div>
        </form>

        <?php if (!empty($application_doctors)): ?>
        <hr class="my-4">
        <div class="small fw-semibold mb-2">등록된 의사</div>
        <div class="row g-2">
            <?php foreach ($application_doctors as $edit_doctor): ?>
            <div class="col-md-6"><div class="border rounded p-3 h-100 d-flex gap-3">
                <?php if (!empty($edit_doctor['photo_url'])): ?><img src="<?= htmlspecialchars($edit_doctor['photo_url']) ?>" class="rounded border" style="width:72px;height:88px;object-fit:cover" alt=""><?php endif; ?>
                <div><strong><?= htmlspecialchars($edit_doctor['name']) ?></strong><div class="small text-primary"><?= htmlspecialchars($edit_doctor['specialty'] ?? '') ?: '전문 분야 미입력' ?></div><div class="small">경력 <?= (int)($edit_doctor['experience'] ?? 0) ?>년</div><?php if (!empty($edit_doctor['qualification'])): ?><div class="small mt-1"><?= nl2br(htmlspecialchars($edit_doctor['qualification'])) ?></div><?php endif; ?><?php if (!empty($edit_doctor['bio'])): ?><div class="small text-body-secondary mt-1"><?= nl2br(htmlspecialchars($edit_doctor['bio'])) ?></div><?php endif; ?></div>
            </div></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?><div class="text-body-secondary small mt-4">등록된 의료진이 없습니다.</div><?php endif; ?>
    </div></div>
</div>


<?php
$edit_main_image_url = (string)($hospital['main_image_url'] ?? '');
$edit_detail_image_count = 0;
foreach ($hospital_images as $edit_image) {
    if ((int)($edit_image['is_active'] ?? 1) !== 1) continue;
    $edit_image_type = (string)($edit_image['image_type'] ?? '');
    if ($edit_image_type === 'main' && !empty($edit_image['image_url'])) {
        $edit_main_image_url = (string)$edit_image['image_url'];
    } elseif (!in_array($edit_image_type, ['main', 'logo'], true)) {
        $edit_detail_image_count++;
    }
}
$edit_detail_slots_remaining = max(0, 10 - $edit_detail_image_count);
?>
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <button class="btn btn-link text-body text-decoration-none p-0 flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#editHospitalImagesSection" aria-expanded="false">
            <i class="bi bi-images me-1"></i><strong>병원 이미지 추가</strong> <span class="badge bg-secondary ms-1"><?php echo count($hospital_images); ?>장</span> <i class="bi bi-chevron-down ms-2"></i>
        </button>
        <a href="admin_hospital_images.php?hospital_id=<?php echo $hospital['id']; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i>이미지 관리 (추가/수정/삭제)
        </a>
    </div>
    <div class="collapse" id="editHospitalImagesSection"><div class="card-body">
        <form method="POST" action="?view=<?php echo (int)$hospital['id']; ?>&amp;edit=1" enctype="multipart/form-data" id="adminHospitalImageForm">
            <input type="hidden" name="action" value="add_hospital_images">
            <input type="hidden" name="hospital_id" value="<?php echo (int)$hospital['id']; ?>">
            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="bg-body rounded border p-3 h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-image text-primary"></i>
                            <label class="form-label fw-semibold mb-0" for="adminMainImageInput">메인 이미지</label>
                        </div>
                        <input type="file" name="admin_main_image" id="adminMainImageInput" class="form-control form-control-sm" accept=".jpg,.jpeg,.png">
                        <div class="form-text">목록 및 병원 상세 상단 · JPG/PNG · 5MB 이하</div>
                        <div id="adminMainImagePreview" class="d-flex flex-wrap gap-2 mt-2">
                            <?php if ($edit_main_image_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($edit_main_image_url); ?>" alt="현재 메인 이미지" class="rounded border object-fit-cover" style="width:120px;height:88px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="bg-body rounded border p-3 h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-images text-primary"></i>
                            <label class="form-label fw-semibold mb-0" for="adminInteriorImagesInput">내부 상세 이미지</label>
                            <span class="badge bg-light text-dark border" id="adminInteriorImageCount"><?php echo $edit_detail_image_count; ?> / 10</span>
                        </div>
                        <input type="file" name="admin_interior_images[]" id="adminInteriorImagesInput" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple <?php echo $edit_detail_slots_remaining === 0 ? 'disabled' : ''; ?>>
                        <div class="form-text">한 번에 여러 장 선택 가능 · 추가 가능 <?php echo $edit_detail_slots_remaining; ?>장 · JPG/PNG/WebP · 파일당 10MB 이하</div>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="adminInteriorImagesPreview"></div>
                        <?php if ($edit_detail_slots_remaining === 0): ?>
                        <div class="alert alert-secondary py-2 px-3 mt-2 mb-0 small">내부 상세 이미지가 10장 등록되어 있습니다. 이미지 관리에서 삭제 후 추가해 주세요.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload me-1"></i>선택 이미지 저장</button>
            </div>
        </form>

        <?php if (!empty($hospital_images)): ?>
        <hr>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($hospital_images as $img): ?>
            <div class="text-center" style="width:100px;">
                <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt=""
                     style="width:100px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($img['image_type']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">등록된 이미지가 없습니다. <strong>"이미지 관리"</strong> 버튼으로 추가하세요.</p>
        <?php endif; ?>
    </div></div>
</div>


<div class="card mt-3 border-info">
    <button class="card-header bg-info bg-opacity-10 btn border-0 d-flex justify-content-between align-items-center text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#editAiSettingsSection" aria-controls="editAiSettingsSection" aria-expanded="false"><span>
        <i class="bi bi-robot me-1"></i> AI 기능 설정
        <span class="badge bg-<?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'success' : 'secondary'; ?> ms-2">
            <?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'ON' : 'OFF'; ?>
        </span>
    </span><i class="bi bi-chevron-down"></i></button>
    <div class="collapse" id="editAiSettingsSection"><div class="card-body">
        <form method="POST" action="?view=<?php echo $hospital['id']; ?>&edit=1">
            <input type="hidden" name="action" value="save_ai_settings">
            <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="ai_enabled" id="aiEnabled" <?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aiEnabled"><strong>AI 기능 활성화</strong></label>
                        <div class="form-text">이 병원에서 AI 기능(후기검수, 광고검수, AI제안 등) 사용 허용</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="ai_use_own_key" id="aiUseOwnKey" <?php echo (($hospital['ai_use_own_key'] ?? 0) == 1) ? 'checked' : ''; ?> onchange="document.getElementById('ownKeyArea').style.display=this.checked?'block':'none'">
                        <label class="form-check-label" for="aiUseOwnKey"><strong>자체 AI API 키 사용</strong></label>
                        <div class="form-text">병원이 직접 등록한 API 키 사용 (플랫폼 비용 차감 없음)</div>
                    </div>
                </div>
            </div>

            <div id="ownKeyArea" style="display:<?php echo (($hospital['ai_use_own_key'] ?? 0) == 1) ? 'block' : 'none'; ?>;">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">AI 프로바이더</label>
                        <select name="ai_own_provider" class="form-select form-select-sm">
                            <option value="openai" <?php echo (($hospital['ai_own_provider'] ?? 'openai') === 'openai') ? 'selected' : ''; ?>>OpenAI (GPT)</option>
                            <option value="anthropic" <?php echo (($hospital['ai_own_provider'] ?? '') === 'anthropic') ? 'selected' : ''; ?>>Anthropic (Claude)</option>
                            <option value="google" <?php echo (($hospital['ai_own_provider'] ?? '') === 'google') ? 'selected' : ''; ?>>Google (Gemini)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">API 키</label>
                        <input type="password" name="ai_own_api_key" class="form-control form-control-sm" placeholder="<?php echo !empty($hospital['ai_own_api_key']) ? '(등록됨 — 변경 시 새 키 입력)' : 'sk-... 또는 API 키 입력'; ?>">
                        <div class="form-text">변경하지 않으려면 비워두세요. AES-256-CBC 암호화 저장됩니다.</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ai_cost_from_wallet" id="aiCostWallet" <?php echo (($hospital['ai_cost_from_wallet'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aiCostWallet"><strong>플랫폼 키 사용 시 지갑 차감</strong></label>
                        <div class="form-text">자체 키 미사용 시, AI 비용을 CPV 지갑에서 차감</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-check-lg me-1"></i>AI 설정 저장</button>
        </form>
    </div></div>
</div>

<div class="d-flex gap-2 mt-4 mb-3">
    <button type="submit" form="editHospitalForm" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>저장</button>
    <a href="?view=<?php echo $hospital['id']; ?>" class="btn btn-secondary btn-sm">취소</a>
</div>

<?php else: ?>

<?php if (($hospital['status'] ?? 'pending') === 'pending'): ?>
<div class="card mb-3 border-warning">
    <div class="card-header bg-warning bg-opacity-10">
        <i class="bi bi-clock-history me-1"></i> 승인 대기 중 - 승인 또는 반려 처리가 필요합니다
    </div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="d-inline" onsubmit="return confirm('이 병원을 승인하시겠습니까?')">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-lg me-1"></i>승인
                </button>
            </form>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-lg me-1"></i>반려
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (($hospital['status'] ?? '') === 'rejected' && !empty($hospital['reject_reason'])): ?>
<div class="alert alert-danger py-2 mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>반려 사유:</strong> <?php echo htmlspecialchars($hospital['reject_reason']); ?>
    <span class="text-body-secondary small ms-2">
        (<?php echo htmlspecialchars($hospital['reviewed_by'] ?? ''); ?>,
         <?php echo $hospital['reviewed_at'] ? date('Y-m-d H:i', strtotime($hospital['reviewed_at'])) : '-'; ?>)
    </span>
</div>
<?php endif; ?>

<?php if (($hospital['status'] ?? '') === 'approved' && !empty($hospital['reviewed_by'])): ?>
<div class="alert alert-success py-2 mb-3">
    <i class="bi bi-check-circle me-1"></i>
    <strong>승인 완료</strong> -
    <?php echo htmlspecialchars($hospital['reviewed_by']); ?>,
    <?php echo $hospital['reviewed_at'] ? date('Y-m-d H:i', strtotime($hospital['reviewed_at'])) : '-'; ?>
</div>
<?php endif; ?>

<?php
$unreviewed = $hospital['has_unreviewed_changes'] ?? 0;
$revisions = [];
if ($unreviewed) {
    $rv_stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospital_revisions WHERE hospital_id=? AND reviewed=0 ORDER BY changed_at DESC");
    mysqli_stmt_bind_param($rv_stmt, 'i', $hospital['id']);
    mysqli_stmt_execute($rv_stmt);
    $rv_res = mysqli_stmt_get_result($rv_stmt);
    while ($rv = mysqli_fetch_assoc($rv_res)) $revisions[] = $rv;
    mysqli_stmt_close($rv_stmt);
}
$field_labels_map = ['name'=>'병원명','owner_name'=>'대표자명','business_number'=>'사업자번호','phone'=>'전화','email'=>'이메일','contact_name'=>'담당자','contact_phone'=>'담당자전화','address'=>'주소','introduction'=>'병원 소개','website'=>'홈페이지','sns_url'=>'SNS','business_hours'=>'영업시간','treatment_tags'=>'시술'];
?>
<?php if ($unreviewed && !empty($revisions)): ?>
<div class="card mb-3 border-info">
    <div class="card-header bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square me-1"></i><strong>신청자가 정보를 수정했습니다</strong> (<?= count($revisions) ?>건)</span>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="mark_reviewed">
            <input type="hidden" name="hospital_id" value="<?= $hospital['id'] ?>">
            <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-check-lg me-1"></i>확인 완료</button>
        </form>
    </div>
    <div class="card-body py-2">
        <table class="table table-sm small mb-0">
            <thead><tr><th>항목</th><th>이전</th><th></th><th>변경</th><th>일시</th></tr></thead>
            <tbody>
            <?php foreach ($revisions as $rv): ?>
            <tr>
                <td class="fw-semibold"><?= $field_labels_map[$rv['field_name']] ?? $rv['field_name'] ?></td>
                <td><span style="background:#ffeef0;padding:1px 4px;border-radius:3px;text-decoration:line-through;"><?= htmlspecialchars(mb_substr($rv['old_value'] ?? '', 0, 30)) ?: '-' ?></span></td>
                <td><i class="bi bi-arrow-right"></i></td>
                <td><span style="background:#e6ffed;padding:1px 4px;border-radius:3px;"><?= htmlspecialchars(mb_substr($rv['new_value'] ?? '', 0, 30)) ?: '-' ?></span></td>
                <td class="text-muted"><?= date('m.d H:i', strtotime($rv['changed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($hospital['applied_by'])): ?>
<?php
$application_assets = [
    ['label' => '사업자등록증', '상세' => '필수 서류', 'path' => (string)($hospital['cert_business'] ?? ''), 'required' => true, 'icon' => 'bi-file-earmark-text'],
    ['label' => '의료기관개설허가증', '상세' => '필수 서류', 'path' => (string)($hospital['cert_medical'] ?? ''), 'required' => true, 'icon' => 'bi-file-earmark-medical'],
];
?>
<div class="card mb-3">
    <button class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 btn border-0 text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#viewDocumentsSection" aria-controls="viewDocumentsSection" aria-expanded="false"><span>
        <span><i class="bi bi-file-earmark-check me-1"></i><strong>제출 서류</strong></span>
        <span class="small text-body-secondary">서류를 확인한 후 승인 또는 반려해 주세요.</span>
    </span><i class="bi bi-chevron-down"></i></button>
    <div class="collapse" id="viewDocumentsSection"><div class="card-body">
        <div class="row g-3">
            <?php foreach ($application_assets as $asset):
                $asset_path = trim($asset['path']);
                $asset_ext = strtolower(pathinfo((string)(parse_url($asset_path, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                $is_pdf = $asset_ext === 'pdf';
            ?>
            <div class="col-md-6">
                <div class="application-asset">
                    <div class="p-3 d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold"><i class="bi <?= $asset['icon'] ?> me-1"></i><?= htmlspecialchars($asset['label']) ?></div>
                            <div class="small text-body-secondary"><?= htmlspecialchars($asset['상세']) ?></div>
                        </div>
                        <?php if ($asset_path !== ''): ?>
                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> 제출</span>
                        <?php elseif ($asset['required']): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-lg"></i> 미제출</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">미제출</span>
                        <?php endif; ?>
                    </div>
                    <div class="application-asset-preview">
                        <?php if ($asset_path !== '' && $is_pdf): ?>
                            <div class="text-center p-3">
                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size:3.5rem"></i>
                                <div class="mt-2 fw-semibold">PDF 문서</div>
                            </div>
                        <?php elseif ($asset_path !== ''): ?>
                            <img src="<?= htmlspecialchars($asset_path) ?>" alt="<?= htmlspecialchars($asset['label']) ?>"
                                 onclick='showImageModal(<?= json_encode($asset_path, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <?php else: ?>
                            <div class="application-asset-missing p-3">
                                <i class="bi bi-image-alt" style="font-size:2.5rem"></i>
                                <div class="mt-2"><?= $asset['required'] ? '필수 서류가 저장되지 않았습니다.' : '제출된 이미지가 없습니다.' ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($asset_path !== ''): ?>
                    <div class="p-3 border-top d-flex gap-2">
                        <a href="<?= htmlspecialchars($asset_path) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-fill">
                            <i class="bi bi-box-arrow-up-right me-1"></i>원본 열기
                        </a>
                        <a href="<?= htmlspecialchars($asset_path) ?>" download class="btn btn-outline-secondary btn-sm" title="다운로드"><i class="bi bi-download"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</div>
<?php endif; ?>

<?php if (!$edit_mode): ?>
<div class="card mb-3">
    <button class="card-header btn text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#viewTreatmentsSection" aria-expanded="<?= isset($_GET['treatment_msg']) ? 'true' : 'false' ?>">
        <span><i class="bi bi-clipboard2-pulse me-1"></i><strong>시술 추가 / 관리</strong></span>
        <i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse<?= isset($_GET['treatment_msg']) ? ' show' : '' ?>" id="viewTreatmentsSection">
        <div class="card-body p-0"><?= $hospital_treatment_panel ?></div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($hospital['applied_by'])): ?>
<div class="card mb-3">
    <button class="card-header btn btn-light border-0 d-flex justify-content-between align-items-center text-start" type="button" data-bs-toggle="collapse" data-bs-target="#viewDoctorsSection" aria-expanded="false">
        <span><i class="bi bi-person-badge me-1"></i><strong>의사 추가</strong></span>
        <span><span class="badge bg-body-secondary text-body me-2"><?= count($application_doctors) ?>명</span><i class="bi bi-chevron-down"></i></span>
    </button>
    <div class="collapse" id="viewDoctorsSection"><div class="card-body">
        <?php if (!empty($application_doctors)): ?>
        <div class="row g-3">
            <?php foreach ($application_doctors as $application_doctor): ?>
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 d-flex gap-3">
                    <?php if (!empty($application_doctor['photo_url'])): ?>
                    <img src="<?= htmlspecialchars($application_doctor['photo_url']) ?>" alt="<?= htmlspecialchars($application_doctor['name']) ?>" class="rounded border flex-shrink-0" style="width:92px;height:112px;object-fit:cover;cursor:pointer" onclick='showImageModal(<?= json_encode($application_doctor['photo_url'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <?php endif; ?>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold mb-1"><?= htmlspecialchars($application_doctor['name']) ?></div>
                        <div class="small text-primary mb-2"><?= htmlspecialchars($application_doctor['specialty'] ?? '') ?: '전문 분야 미입력' ?></div>
                        <div class="small mb-1"><strong>경력:</strong> <?= (int)($application_doctor['experience'] ?? 0) ?>년</div>
                        <?php if (!empty($application_doctor['qualification'])): ?><div class="small mb-1"><strong>자격/학회:</strong><br><?= nl2br(htmlspecialchars($application_doctor['qualification'])) ?></div><?php endif; ?>
                        <?php if (!empty($application_doctor['bio'])): ?><div class="small text-body-secondary mt-2"><?= nl2br(htmlspecialchars($application_doctor['bio'])) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i>신청서에 등록된 의료진이 없습니다.</div>
        <?php endif; ?>
    </div></div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building me-1"></i> 기본 정보 <?php if ($unreviewed): ?><span class="badge bg-info ms-2">수정됨</span><?php endif; ?></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="hospital-detail-label">병원 이름</div>
                        <div class="hospital-detail-value fw-bold"><?php echo htmlspecialchars($hospital['name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">대표자명</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['owner_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">사업자등록번호</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['business_number'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">이메일</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['email'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">전화번호</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['phone'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">담당자</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars(($hospital['contact_name'] ?? '') . ' / ' . ($hospital['contact_phone'] ?? '')); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">주소</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['address'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">홈페이지</div>
                        <div class="hospital-detail-value"><?php if (!empty($hospital['website'])): ?><a href="<?= htmlspecialchars($hospital['website']) ?>" target="_blank"><?= htmlspecialchars($hospital['website']) ?></a><?php else: ?>-<?php endif; ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">SNS</div>
                        <div class="hospital-detail-value"><?php if (!empty($hospital['sns_url'])): ?><a href="<?= htmlspecialchars($hospital['sns_url']) ?>" target="_blank"><?= htmlspecialchars($hospital['sns_url']) ?></a><?php else: ?>-<?php endif; ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">병원 소개</div>
                        <div class="hospital-detail-value"><?php echo nl2br(htmlspecialchars($hospital['introduction'] ?? '-')); ?></div>
                    </div>
                    <?php if (!empty($hospital['applied_by'])): ?>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">신청자</div>
                        <div class="hospital-detail-value"><span class="badge bg-secondary"><?= htmlspecialchars($hospital['applied_by']) ?></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">신청일</div>
                        <div class="hospital-detail-value"><?= $hospital['applied_at'] ? date('Y-m-d H:i', strtotime($hospital['applied_at'])) : '-' ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> 상태 / 통계</div>
            <div class="card-body">
                <div class="hospital-detail-label">상태</div>
                <div class="hospital-detail-value"><?php echo statusBadge($hospital['status'] ?? 'pending'); ?></div>

                <div class="hospital-detail-label">활성</div>
                <div class="hospital-detail-value">
                    <?php if ($hospital['is_active']): ?>
                        <span class="badge bg-success">활성</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">비활성</span>
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">광고 등급</div>
                <div class="hospital-detail-value">
                    <?php
                    $dg = $hospital['ad_grade'] ?? 'none';
                    $grade_labels = ['S'=>'S (최우선)','A'=>'A (우선)','B'=>'B (제휴)','C'=>'C (제휴)','D'=>'D (제휴)','E'=>'E (제휴)','none'=>'없음'];
                    $grade_colors = ['S'=>'bg-danger','A'=>'bg-danger','B'=>'bg-primary','C'=>'bg-primary','D'=>'bg-secondary','E'=>'bg-secondary','none'=>'bg-body-secondary text-body'];
                    ?>
                    <span class="badge <?php echo $grade_colors[$dg] ?? 'bg-secondary'; ?>"><?php echo $grade_labels[$dg] ?? $dg; ?></span>
                </div>

                <div class="hospital-detail-label">평점</div>
                <div class="hospital-detail-value">
                    <span class="text-warning"><?php echo str_repeat('&#9733;', (int)($hospital['rating'] ?? 0)); ?></span>
                    <span class="text-body-tertiary"><?php echo str_repeat('&#9733;', 5 - (int)($hospital['rating'] ?? 0)); ?></span>
                    <span class="small text-body-secondary ms-1">(<?php echo number_format($hospital['rating'] ?? 0, 1); ?>)</span>
                </div>

                <div class="hospital-detail-label">리뷰 수</div>
                <div class="hospital-detail-value"><?php echo number_format($hospital['review_count'] ?? 0); ?>건</div>

                <div class="hospital-detail-label">평점 표시</div>
                <div class="hospital-detail-value">
                    <?php if (($hospital['show_rating'] ?? 1) == 1): ?>
                    <span class="badge bg-success">ON</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">OFF</span>
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">위치</div>
                <div class="hospital-detail-value small">
                    <?php if (!empty($hospital['latitude']) && !empty($hospital['longitude'])): ?>
                        <?php echo $hospital['latitude']; ?>, <?php echo $hospital['longitude']; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">등록일</div>
                <div class="hospital-detail-value small"><?php echo $hospital['created_at'] ? date('Y-m-d H:i', strtotime($hospital['created_at'])) : '-'; ?></div>

                <div class="hospital-detail-label">수정일</div>
                <div class="hospital-detail-value small"><?php echo $hospital['updated_at'] ? date('Y-m-d H:i', strtotime($hospital['updated_at'])) : '-'; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <button class="card-header btn btn-light border-0 d-flex justify-content-between align-items-center text-start" type="button" data-bs-toggle="collapse" data-bs-target="#viewBusinessHoursSection" aria-expanded="false">
        <span><i class="bi bi-clock me-1"></i><strong>영업시간 설정</strong></span><i class="bi bi-chevron-down"></i>
    </button>
    <div class="collapse" id="viewBusinessHoursSection"><div class="card-body">
        <?php $detail_hours = json_decode($hospital['business_hours'] ?? '{}', true); ?>
        <?php if (is_array($detail_hours) && !empty($detail_hours)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-3">
                <thead class="table-light"><tr><th>요일</th><th>진료시간</th><th>상태</th></tr></thead>
                <tbody>
                <?php foreach (['월'=>'월요일','화'=>'화요일','수'=>'수요일','목'=>'목요일','금'=>'금요일','토'=>'토요일','일'=>'일요일'] as $hours_key => $hours_label):
                    $hours_value = trim((string)($detail_hours[$hours_key] ?? '휴무'));
                    $hours_is_closed = in_array($hours_value, ['휴무', '공사중', '임시휴업', ''], true);
                ?>
                <tr><td class="fw-semibold"><?= $hours_label ?></td><td><?= $hours_is_closed ? '-' : htmlspecialchars($hours_value) ?></td><td><span class="badge <?= $hours_is_closed ? 'bg-secondary' : 'bg-success' ?>"><?= $hours_is_closed ? htmlspecialchars($hours_value ?: '휴무') : '정상진료' ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap gap-4 small">
            <div><strong>점심시간:</strong> <?= !empty($detail_hours['no_lunch_break']) ? '없음' : htmlspecialchars($detail_hours['점심'] ?? '-') ?></div>
            <div><strong>임시 휴무일:</strong> <?= !empty($detail_hours['special_closed_dates']) ? htmlspecialchars(implode(', ', (array)$detail_hours['special_closed_dates'])) : '없음' ?></div>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-1"></i>등록된 영업시간 정보가 없습니다.</div>
        <?php endif; ?>
    </div></div>
</div>

<div class="card mb-3">
    <button class="card-header btn border-0 d-flex justify-content-between align-items-center text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#viewCategoriesSection" aria-controls="viewCategoriesSection" aria-expanded="false"><span><i class="bi bi-tags me-1"></i> 카테고리 / 태그</span><i class="bi bi-chevron-down"></i></button>
    <div class="collapse" id="viewCategoriesSection"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="hospital-detail-label">카테고리</div>
                <div class="hospital-detail-value">
                    <?php
                    $cats_raw = $hospital['categories'] ?? '';
                    $cats = json_decode($cats_raw, true);
                    if (!is_array($cats)) $cats = array_filter(array_map('trim', explode(',', $cats_raw)));
                    if (!empty($cats)):
                        foreach ($cats as $cat): ?>
                            <span class="badge bg-primary category-badge me-1 mb-1"><?php echo htmlspecialchars($cat); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">뱃지</div>
                <div class="hospital-detail-value">
                    <?php
                    $badges_raw = $hospital['badges'] ?? '';
                    $badges = json_decode($badges_raw, true);
                    if (!is_array($badges)) $badges = array_filter(array_map('trim', explode(',', $badges_raw)));
                    if (!empty($badges)):
                        foreach ($badges as $badge_item): ?>
                            <span class="badge bg-info text-dark category-badge me-1 mb-1"><?php echo htmlspecialchars($badge_item); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">시술 태그</div>
                <div class="hospital-detail-value">
                    <?php
                    $tags_raw = $hospital['treatment_tags'] ?? '';
                    $tags = json_decode($tags_raw, true);
                    if (!is_array($tags)) $tags = array_filter(array_map('trim', explode(',', $tags_raw)));
                    if (!empty($tags)):
                        foreach ($tags as $tag):
                            $tag_label = is_array($tag) ? ($tag['name'] ?? '') : $tag;
                            if ($tag_label === '') continue; ?>
                            <span class="badge bg-body-secondary text-body tag-badge me-1 mb-1"><?php echo htmlspecialchars($tag_label); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">인근 지하철</div>
                <div class="hospital-detail-value">
                    <?php
                    $subways_raw = $hospital['subway_names'] ?? '';
                    $subways = json_decode($subways_raw, true);
                    if (!is_array($subways)) $subways = array_filter(array_map('trim', explode(',', $subways_raw)));
                    if (!empty($subways)):
                        foreach ($subways as $sw): ?>
                            <span class="badge bg-success tag-badge me-1 mb-1"><i class="bi bi-train-front-fill me-1"></i><?php echo htmlspecialchars($sw); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div></div>
</div>

<?php if (empty($hospital['applied_by']) && (!empty($hospital['profile_image_url']) || !empty($hospital['main_image_url']))): ?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-image me-1"></i> 대표 이미지</div>
    <div class="card-body">
        <div class="row g-3">
            <?php if (!empty($hospital['profile_image_url'])): ?>
            <div class="col-md-6">
                <div class="hospital-detail-label">프로필 이미지</div>
                <img src="<?php echo htmlspecialchars($hospital['profile_image_url']); ?>" alt="프로필" class="hospital-img-large">
            </div>
            <?php endif; ?>
            <?php if (!empty($hospital['main_image_url'])): ?>
            <div class="col-md-6">
                <div class="hospital-detail-label">메인 이미지</div>
                <img src="<?php echo htmlspecialchars($hospital['main_image_url']); ?>" alt="메인" class="hospital-img-large">
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <button class="btn btn-link text-body text-decoration-none p-0 flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#viewHospitalImagesSection" aria-expanded="false">
            <i class="bi bi-images me-1"></i><strong>병원 이미지 추가</strong> <span class="badge bg-body-secondary text-body ms-1"><?php echo count($hospital_images); ?>장</span> <i class="bi bi-chevron-down ms-2"></i>
        </button>
        <a href="admin_hospital_images.php?hospital_id=<?= (int)$hospital['id'] ?>" class="btn btn-outline-primary btn-sm">이미지 관리</a>
    </div>
    <div class="collapse" id="viewHospitalImagesSection"><div class="card-body">
        <?php if (!empty($hospital_images)): ?>
        <div class="row g-2">
            <?php foreach ($hospital_images as $img): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>"
                         class="card-img-top" style="height:150px;object-fit:cover;cursor:pointer;"
                         alt="<?php echo htmlspecialchars($img['image_type'] ?? ''); ?>"
                         onclick="showImageModal('<?php echo htmlspecialchars($img['image_url']); ?>')">
                    <div class="card-body p-2 text-center">
                        <small class="text-body-secondary">
                            <?php echo htmlspecialchars($img['image_type'] ?? '-'); ?>
                            <?php if (isset($img['sort_order'])): ?>
                                <span class="badge bg-body-secondary text-body">#<?php echo $img['sort_order']; ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-body-secondary small">등록된 병원 이미지가 없습니다.</div>
        <?php endif; ?>
    </div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <?php if (($hospital['status'] ?? '') !== 'approved'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('이 병원을 승인하시겠습니까?')">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>승인</button>
                </form>
                <?php endif; ?>
                <?php if (($hospital['status'] ?? '') !== 'rejected'): ?>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i>반려
                </button>
                <?php endif; ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('활성 상태를 변경하시겠습니까?')">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-<?php echo $hospital['is_active'] ? 'pause' : 'play'; ?> me-1"></i>
                        <?php echo $hospital['is_active'] ? '비활성화' : '활성화'; ?>
                    </button>
                </form>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('정말 이 병원을 삭제하시겠습니까?\n관련된 이미지도 함께 삭제됩니다.\n이 작업은 되돌릴 수 없습니다.')">
                <input type="hidden" name="action" value="delete_hospital">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>삭제
                </button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-x-circle text-danger me-1"></i> 병원 반려</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <strong><?php echo htmlspecialchars($hospital['name']); ?></strong>
                        <span class="text-body-secondary small ms-1">#<?php echo $hospital['id']; ?></span>
                    </div>
                    <label class="form-label fw-semibold small">반려 사유 <span class="text-danger">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="4" required
                              placeholder="반려 사유를 입력해주세요..."><?php echo htmlspecialchars($hospital['reject_reason'] ?? ''); ?></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg me-1"></i>반려 처리</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-image"></i> 이미지 보기</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2" style="max-height:80vh;overflow:auto;">
                <img id="modalImage" src="" class="img-fluid" style="max-width:100%;">
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($view_mode > 0 && !$hospital): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i> 해당 병원을 찾을 수 없습니다.
    <a href="admin_hospitals.php" class="alert-link ms-2">목록으로 돌아가기</a>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-building"></i> <?php echo term('business'); ?> 관리</h5>

    <a href="admin_hospital_wizard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-magic"></i> 마법사로 등록</a>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'all']); ?>" class="text-decoration-none hospital-status-link" data-status="all">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'all' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                    <div class="small text-body-secondary">전체</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'pending']); ?>" class="text-decoration-none hospital-status-link" data-status="pending">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'pending' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-warning"><?php echo number_format($stats['pending']); ?></div>
                    <div class="small text-body-secondary">대기</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'approved']); ?>" class="text-decoration-none hospital-status-link" data-status="approved">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'approved' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['approved']); ?></div>
                    <div class="small text-body-secondary">승인</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'rejected']); ?>" class="text-decoration-none hospital-status-link" data-status="rejected">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'rejected' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-danger"><?php echo number_format($stats['rejected']); ?></div>
                    <div class="small text-body-secondary">반려</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold small text-body-secondary me-1">상태</span>
            <a href="<?php echo buildHospitalUrl(['status' => 'all']); ?>" data-status="all"
               class="btn btn-sm hospital-status-link <?php echo $filter_status === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">전체</a>
            <a href="<?php echo buildHospitalUrl(['status' => 'pending']); ?>" data-status="pending"
               class="btn btn-sm hospital-status-link <?php echo $filter_status === 'pending' ? 'btn-warning' : 'btn-outline-secondary'; ?>">
                대기
                <?php if ($stats['pending'] > 0): ?>
                <span class="badge bg-white text-warning ms-1"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo buildHospitalUrl(['status' => 'approved']); ?>" data-status="approved"
               class="btn btn-sm hospital-status-link <?php echo $filter_status === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?>">승인</a>
            <a href="<?php echo buildHospitalUrl(['status' => 'rejected']); ?>" data-status="rejected"
               class="btn btn-sm hospital-status-link <?php echo $filter_status === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">반려</a>
        </div>
    </div>
</div>

<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="병원명, 주소, 전화, 이메일, 카테고리 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10, 20, 50] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total); ?>건</span>
    </div>
</div>

<div id="batchGeocodeCard" class="card mb-3 border-info" style="display:none;">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-info-emphasis small">
                <i class="bi bi-geo-alt me-1"></i>좌표 미등록 병원: <strong id="bgPending">0</strong>개
            </span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="bgStartBtn" onclick="bgStart()">
                <i class="bi bi-pin-map me-1"></i>일괄 좌표 변환 시작
            </button>
            <div id="bgProgress" style="display:none;" class="flex-grow-1">
                <div class="progress" style="height:18px;">
                    <div id="bgBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
                </div>
                <div class="small text-body-secondary mt-1">
                    성공: <span id="bgOk">0</span> | 실패: <span id="bgFail">0</span> | 남은: <span id="bgLeft">0</span>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    fetch('api/geocode_batch.php?action=count')
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.pending > 0) {
            document.getElementById('bgPending').textContent = d.pending;
            document.getElementById('batchGeocodeCard').style.display = 'block';
        }
    }).catch(function(){});
})();
var _bgOk=0, _bgFail=0, _bgTotal=0, _bgDone=0;
function bgStart() {
    document.getElementById('bgStartBtn').disabled = true;
    document.getElementById('bgProgress').style.display = 'block';
    _bgTotal = parseInt(document.getElementById('bgPending').textContent) || 0;
    _bgOk=0; _bgFail=0; _bgDone=0;
    bgNext();
}
function bgNext() {
    fetch('api/geocode_batch.php?action=batch', { method:'POST' })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.done) { bgFinish(); return; }
        _bgDone++;
        if (d.success) _bgOk++; else _bgFail++;
        var pct = Math.round(_bgDone / _bgTotal * 100);
        document.getElementById('bgBar').style.width = pct+'%';
        document.getElementById('bgBar').textContent = pct+'%';
        document.getElementById('bgOk').textContent = _bgOk;
        document.getElementById('bgFail').textContent = _bgFail;
        document.getElementById('bgLeft').textContent = Math.max(0, _bgTotal - _bgDone);
        setTimeout(bgNext, 1100);
    })
    .catch(function(){ _bgFail++; _bgDone++; setTimeout(bgNext, 2000); });
}
function bgFinish() {
    document.getElementById('bgBar').className = 'progress-bar bg-success';
    document.getElementById('bgBar').style.width = '100%';
    document.getElementById('bgBar').textContent = '완료!';
    document.getElementById('bgStartBtn').textContent = '완료 (' + _bgOk + '건 성공)';
}
</script>

<div id="hospitalListPanel">
    <?php include __DIR__ . '/includes/admin_hospitals_list_partial.php'; ?>
</div>

<?php endif;  ?>

<?php
$admin_extra_js = '
function showImageModal(src) {
    var img = document.getElementById("modalImage");
    if (img) img.src = src;
    var modal = document.getElementById("imageModal");
    if (modal) new bootstrap.Modal(modal).show();
}
';

?>
<script>
// 페이지네이션 AJAX 갱신: 목록 패널(#hospitalListPanel)만 fetch로 교체한다.
// 전체 페이지를 새로 로드하지 않으므로 스크롤 위치가 그대로 유지된다.
(function() {
    var panel = document.getElementById('hospitalListPanel');
    if (!panel) return;

    function loadListPage(url, pushState) {
        var ajaxUrl = url + (url.indexOf('?') > -1 ? '&' : '?') + 'ajax_list=1';
        panel.style.opacity = '0.5';
        fetch(ajaxUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(res) { if (!res.ok) throw new Error('요청 실패'); return res.text(); })
            .then(function(html) {
                panel.innerHTML = html;
                panel.style.opacity = '';
                if (pushState) history.pushState(null, '', url);
            })
            .catch(function() {
                // AJAX 실패 시 일반 페이지 이동으로 대체(폴백)
                window.location.href = url;
            });
    }

    // 상태 필터 카드/버튼(전체·대기·승인·반려)도 클릭 시 페이지=1로 이동하는 링크라
    // 페이지네이션과 동일하게 AJAX로 처리한다. 이 링크들은 목록 패널 바깥에 있으므로
    // 서버가 다시 그려주지 않는 활성 표시(하이라이트)를 직접 갱신해 준다.
    function syncStatusLinks(url) {
        var status = new URL(url, location.href).searchParams.get('status') || 'all';
        var solidClass = { all: 'btn-primary', pending: 'btn-warning', approved: 'btn-success', rejected: 'btn-danger' };
        document.querySelectorAll('.hospital-status-link').forEach(function(el) {
            var active = el.dataset.status === status;
            var card = el.querySelector('.hospital-stat-card');
            if (card) card.classList.toggle('active-filter', active);
            if (el.classList.contains('btn')) {
                Object.keys(solidClass).forEach(function(k) { el.classList.remove(solidClass[k]); });
                el.classList.remove('btn-outline-secondary');
                el.classList.add(active ? solidClass[el.dataset.status] : 'btn-outline-secondary');
            }
        });
    }

    document.addEventListener('click', function(e) {
        var link = e.target.closest('.pagination .page-link, .hospital-status-link');
        if (!link) return;
        e.preventDefault();
        var href = link.getAttribute('href');
        if (link.classList.contains('hospital-status-link')) syncStatusLinks(href);
        loadListPage(href, true);
    });

    window.addEventListener('popstate', function() {
        var url = location.pathname + location.search;
        syncStatusLinks(url);
        loadListPage(url, false);
    });
})();

document.addEventListener('change', function(e) {
    if (e.target.id === 'checkAll') {
        document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = e.target.checked; });
        updateBulkBar();
    } else if (e.target.classList.contains('row-check')) {
        updateBulkBar();
    }
});

function getCheckedIds() {
    var ids = [];
    document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(cb.value); });
    return ids;
}

function updateBulkBar() {
    var ids = getCheckedIds();
    var bar = document.getElementById('bulkBar');
    if (ids.length > 0) {
        bar.style.cssText = '';
        document.getElementById('bulkCount').textContent = ids.length;
    } else {
        bar.style.cssText = 'display:none !important;';
    }
}

function uncheckAll() {
    document.querySelectorAll('.row-check, #checkAll').forEach(function(cb) { cb.checked = false; });
    updateBulkBar();
}

function bulkSubmit(action, extraData) {
    var ids = getCheckedIds();
    if (ids.length === 0) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input name="action" value="' + action + '"><input name="ids" value="' + ids.join(',') + '"><input name="admin_csrf" value="<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES); ?>">';
    if (extraData) {
        for (var k in extraData) form.innerHTML += '<input name="' + k + '" value="' + extraData[k] + '">';
    }
    document.body.appendChild(form);
    form.submit();
}

// Reveal invalid fields before the browser tries to focus a collapsed section.
document.addEventListener('invalid', function(event) {
    var field = event.target;
    var section = field.closest('.collapse:not(.show)');
    if (!section || !field.closest('#editHospitalForm')) return;
    event.preventDefault();
    section.addEventListener('shown.bs.collapse', function() {
        field.focus();
        field.reportValidity();
    }, { once: true });
    bootstrap.Collapse.getOrCreateInstance(section, { toggle: false }).show();
}, true);

document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function(form) {
    if (!form.querySelector('input[name="admin_csrf"]')) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'admin_csrf';
        input.value = '<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES); ?>';
        form.appendChild(input);
    }
});

function bulkDelete() {
    var ids = getCheckedIds();
    if (confirm(ids.length + '개 병원을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) {
        bulkSubmit('bulk_delete');
    }
}

function bulkApprove() {
    var ids = getCheckedIds();
    if (confirm(ids.length + '개 병원을 승인하시겠습니까?')) {
        bulkSubmit('bulk_approve');
    }
}

function bulkReject() {
    var ids = getCheckedIds();
    var reason = prompt(ids.length + '개 병원 반려 사유를 입력하세요:', '일괄 반려');
    if (reason !== null) {
        bulkSubmit('bulk_reject', { reject_reason: reason });
    }
}


(function() {
    var rows = document.getElementById('adminDoctorRows');
    var addButton = document.getElementById('addAdminDoctorButton');
    var section = document.getElementById('editDoctorsSection');
    if (!rows || !addButton) return;

    function addAdminDoctorRow() {
        if (rows.querySelectorAll('.admin-doctor-row').length >= 20) {
            alert('의사는 한 번에 최대 20명까지 추가할 수 있습니다.');
            return;
        }
        var row = document.createElement('div');
        row.className = 'border rounded p-3 mb-3 admin-doctor-row';
        row.innerHTML = '<div class="row g-2">'
            + '<div class="col-md-4"><label class="form-label small fw-semibold">의사명</label><input type="text" name="doctor_name[]" class="form-control form-control-sm" maxlength="100" placeholder="예: 김서울"></div>'
            + '<div class="col-md-4"><label class="form-label small fw-semibold">전문 분야</label><input type="text" name="doctor_specialty[]" class="form-control form-control-sm" maxlength="255" placeholder="예: 피부과 전문의"></div>'
            + '<div class="col-md-3"><label class="form-label small fw-semibold">경력(년)</label><input type="number" name="doctor_experience[]" class="form-control form-control-sm" min="0" max="80" placeholder="예: 10"></div>'
            + '<div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger btn-sm w-100 remove-admin-doctor" aria-label="의사 삭제"><i class="bi bi-trash"></i></button></div>'
            + '<div class="col-md-6"><label class="form-label small fw-semibold">자격 및 학회</label><textarea name="doctor_qualification[]" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="예: 피부과 전문의&#10;대한피부과학회 정회원"></textarea></div>'
            + '<div class="col-md-6"><label class="form-label small fw-semibold">의사 사진</label><input type="file" name="doctor_photo[]" class="form-control form-control-sm admin-doctor-photo" accept=".jpg,.jpeg,.png,.webp"><div class="form-text">JPG/PNG/WebP · 5MB 이하</div><div class="admin-doctor-photo-preview mt-2"></div></div>'
            + '<div class="col-12"><label class="form-label small fw-semibold">소개 및 주요 경력</label><textarea name="doctor_bio[]" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="의사 소개, 주요 경력과 진료 철학을 입력해 주세요."></textarea></div>'
            + '</div>';
        rows.appendChild(row);
    }

    addButton.addEventListener('click', addAdminDoctorRow);
    rows.addEventListener('click', function(event) {
        var removeButton = event.target.closest('.remove-admin-doctor');
        if (removeButton) removeButton.closest('.admin-doctor-row').remove();
    });
    rows.addEventListener('change', function(event) {
        if (!event.target.classList.contains('admin-doctor-photo')) return;
        var input = event.target;
        var file = input.files && input.files[0];
        var preview = input.closest('.col-md-6').querySelector('.admin-doctor-photo-preview');
        preview.innerHTML = '';
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            input.value = '';
            alert('JPG/PNG/WebP 이미지이며 5MB 이하인지 확인해 주세요.');
            return;
        }
        var image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.className = 'rounded border object-fit-cover';
        image.style.width = '72px';
        image.style.height = '88px';
        image.onload = function() { URL.revokeObjectURL(image.src); };
        preview.appendChild(image);
    });
    if (section) {
        section.addEventListener('shown.bs.collapse', function() {
            if (!rows.querySelector('.admin-doctor-row')) addAdminDoctorRow();
        });
    }
})();

(function() {
    var interiorInput = document.getElementById('adminInteriorImagesInput');
    var interiorPreview = document.getElementById('adminInteriorImagesPreview');
    var countLabel = document.getElementById('adminInteriorImageCount');
    var mainInput = document.getElementById('adminMainImageInput');
    var mainPreview = document.getElementById('adminMainImagePreview');
    if (!countLabel) return;

    var existingCount = <?php echo isset($edit_detail_image_count) ? (int)$edit_detail_image_count : 0; ?>;
    var remainingCount = Math.max(0, 10 - existingCount);

    function renderMainPreview(input, target, maxBytes) {
        var file = input.files && input.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/') || file.size > maxBytes) {
            input.value = '';
            alert(file.size > maxBytes ? '파일 용량 제한을 확인해 주세요.' : '이미지 파일만 선택할 수 있습니다.');
            return;
        }
        target.innerHTML = '';
        var image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.className = 'rounded border object-fit-cover';
        image.style.width = '96px';
        image.style.height = '72px';
        image.onload = function() { URL.revokeObjectURL(image.src); };
        target.appendChild(image);
    }

    function renderInteriorPreviews() {
        if (!interiorInput || !interiorPreview) return;
        var files = Array.from(interiorInput.files || []);
        interiorPreview.innerHTML = '';
        if (files.length > remainingCount) {
            interiorInput.value = '';
            countLabel.textContent = existingCount + ' / 10';
            alert('내부 상세 이미지는 ' + remainingCount + '장까지 추가할 수 있습니다.');
            return;
        }
        var invalidFile = files.find(function(file) {
            return !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024;
        });
        if (invalidFile) {
            interiorInput.value = '';
            countLabel.textContent = existingCount + ' / 10';
            alert('JPG/PNG/WebP 이미지이며 파일당 10MB 이하인지 확인해 주세요.');
            return;
        }
        countLabel.textContent = (existingCount + files.length) + ' / 10';
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
            interiorPreview.appendChild(item);
        });
    }

    if (interiorInput) interiorInput.addEventListener('change', renderInteriorPreviews);
    if (mainInput && mainPreview) {
        mainInput.addEventListener('change', function() { renderMainPreview(mainInput, mainPreview, 5 * 1024 * 1024); });
    }
})();
</script>
<?php
include __DIR__ . '/includes/admin_footer.php';
?>

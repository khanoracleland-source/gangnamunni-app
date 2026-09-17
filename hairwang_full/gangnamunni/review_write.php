<?php
/**
 * 후기 작성 페이지 (프론트엔드)
 * - 로그인 필수 (Gnuboard 회원)
 * - hospital_reviews 테이블에 INSERT (review_status = 'pending' → 관리자 승인 후 공개)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
// $DEFAULT_NOTIFICATIONS 등을 전역 스코프에 로드 (admin_fcm.php가 함수 내부에서 지연 require 하면 전역 미설정 → should_receive_notification 오류)
require_once __DIR__ . '/includes/admin_notifications.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
// 앱 모드 감지 (?app=1 파라미터)
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';
// my_reviews.php "작성가능" 목록에서 넘어온 경우 병원 미리 선택 (?hospital_id=)
$prefill_hospital_id = intval($_GET['hospital_id'] ?? 0);
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/photo_upload_helper.php';
require_once __DIR__ . '/includes/review_booking.php';

// 작성 화면을 연 페이지로 안전하게 돌아간다. 외부 URL/open redirect는 허용하지 않는다.
function rw_safe_back_url($candidate) {
    $fallback = 'my_reviews.php';
    $candidate = trim((string)$candidate);
    if ($candidate === '' || preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $candidate)) return $fallback;

    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
        return $fallback;
    }

    $path = ltrim((string)($parts['path'] ?? ''), '/');
    $allowed = ['my_reviews.php', 'my_bookings.php', 'booking_detail.php', 'booking_request_detail.php', 'review_detail.php', 'reviews.php'];
    if (!in_array($path, $allowed, true)) return $fallback;

    return $path . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
}

$review_back_url = rw_safe_back_url($_GET['return_url'] ?? $_POST['return_url'] ?? '');

// 로그인 확인 (Gnuboard 회원 또는 관리자 세션)
$is_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$is_admin_logged = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

if (!$is_logged_in && !$is_admin_logged) {
    $return_url = urlencode($_SERVER['REQUEST_URI'] ?? 'review_write.php');
    header("Location: auth/login.php?url=" . $return_url);
    exit;
}

if ($is_logged_in) {
    $user_name = $member['mb_nick'] ?? $member['mb_id'] ?? '';
    $user_id = $member['mb_id'] ?? '';
} else {
    // 관리자 세션으로 로그인한 경우
    $user_name = $_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_user'] ?? 'admin';
    $user_id = $_SESSION['ad_admin_user'] ?? 'admin';
}

if (empty($_SESSION['review_write_csrf'])) {
    $_SESSION['review_write_csrf'] = bin2hex(random_bytes(32));
}
$review_write_csrf = $_SESSION['review_write_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = (string)($_POST['csrf_token'] ?? '');
    if ($posted_csrf === '' || !hash_equals($review_write_csrf, $posted_csrf)) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');
    }
}

// 수정 모드 확인 (review_detail.php "수정하기"에서 ?id= 로 진입, 본인 후기만 허용)
$edit_id = intval($_GET['id'] ?? 0);
$edit_review = null;
if ($edit_id > 0) {
    $er_stmt = mysqli_prepare($connect, "SELECT * FROM hospital_reviews WHERE id = ? AND user_id = ? AND is_active = 1");
    if ($er_stmt) {
        mysqli_stmt_bind_param($er_stmt, 'is', $edit_id, $user_id);
        mysqli_stmt_execute($er_stmt);
        $edit_review = mysqli_fetch_assoc(mysqli_stmt_get_result($er_stmt));
        mysqli_stmt_close($er_stmt);
    }
    if (!$edit_review) {
        header('Location: ' . $review_back_url);
        exit;
    }
}

// 병원 목록 로드
$hospitals = [];
$h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 시술 카테고리
$categories = [];
$cat_r = mysqli_query($connect, "SELECT DISTINCT name FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[] = $c['name'];
if (empty($categories)) $categories = ['눈성형', '코성형', '리프팅', '피부관리', '보톡스/필러'];

// 상단 병원 카드 데이터 — 예약(booking) 우선(시술/의사/날짜 포함), 없으면 병원 기본 정보만
$card = null;
$prefill_booking_id = intval($_GET['booking_id'] ?? 0);
if ($prefill_booking_id > 0) {
    $bk_stmt = mysqli_prepare($connect, "SELECT b.hospital_id, b.hospital_name AS name, b.treatment, b.booking_date,
            h.address,
            COALESCE(
                (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi
                 WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='main'
                 ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
                NULLIF(h.main_image_url, ''),
                (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi
                 WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='logo'
                 ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
                NULLIF(h.profile_image_url, '')
            ) AS main_image_url,
            TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS doctor_name
        FROM gangnamunni_bookings b
        LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        LEFT JOIN appointments ap ON b.hms_appointment_id = ap.id
        LEFT JOIN doctors doc ON ap.doctor_id = doc.id
        LEFT JOIN users u ON doc.user_id = u.id
        WHERE b.id = ? AND b.mb_id = ? LIMIT 1");
    if ($bk_stmt) {
        mysqli_stmt_bind_param($bk_stmt, 'is', $prefill_booking_id, $user_id);
        mysqli_stmt_execute($bk_stmt);
        $card = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt)) ?: null;
        mysqli_stmt_close($bk_stmt);
    }
}
// 예약 정보가 없으면 hospital_id(수정 모드/프리필)로 병원 기본 정보만
if (!$card) {
    $card_hospital_id = $edit_review ? intval($edit_review['hospital_id']) : $prefill_hospital_id;
    if ($card_hospital_id > 0) {
        $ch_stmt = mysqli_prepare($connect, "SELECT h.name, h.address,
                COALESCE(
                    (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi
                     WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='main'
                     ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
                    NULLIF(h.main_image_url, ''),
                    (SELECT NULLIF(hi.image_url, '') FROM hospital_images hi
                     WHERE hi.hospital_id=h.id AND hi.is_active=1 AND hi.image_type='logo'
                     ORDER BY hi.sort_order ASC, hi.id ASC LIMIT 1),
                    NULLIF(h.profile_image_url, '')
                ) AS main_image_url
            FROM gangnamunni_hospitals h WHERE h.id = ?");
        if ($ch_stmt) {
            mysqli_stmt_bind_param($ch_stmt, 'i', $card_hospital_id);
            mysqli_stmt_execute($ch_stmt);
            $card = mysqli_fetch_assoc(mysqli_stmt_get_result($ch_stmt)) ?: null;
            mysqli_stmt_close($ch_stmt);
        }
    }
}

// 후기 대상 병원 id (카드/수정/프리필 순)
$sel_hospital_id = 0;
if (!empty($card['hospital_id'])) $sel_hospital_id = (int)$card['hospital_id'];
elseif ($edit_review) $sel_hospital_id = (int)$edit_review['hospital_id'];
elseif ($prefill_hospital_id) $sel_hospital_id = $prefill_hospital_id;

// 의사 검색 바텀시트 — 선택된 병원에 등록된 활성 의사만 노출한다.
$available_doctors = [];
if ($sel_hospital_id > 0) {
    $doc_stmt = mysqli_prepare($connect, "SELECT d.id, d.name, d.specialty, d.photo_url,
            (SELECT GROUP_CONCAT(DISTINCT tc.name ORDER BY tc.sort_order SEPARATOR '||')
             FROM gangnamunni_doctor_treatments dt
             JOIN gangnamunni_treatment_master tm ON dt.treatment_id=tm.id
             JOIN gangnamunni_treatment_categories tc ON tm.category_id=tc.id
             WHERE dt.doctor_id=d.id AND dt.is_active=1) AS treatment_tags
        FROM gangnamunni_doctors d
        WHERE d.hospital_id=? AND d.status='active'
        ORDER BY d.id ASC");
    if ($doc_stmt) {
        mysqli_stmt_bind_param($doc_stmt, 'i', $sel_hospital_id);
        mysqli_stmt_execute($doc_stmt);
        $doc_res = mysqli_stmt_get_result($doc_stmt);
        while ($doctor_row = mysqli_fetch_assoc($doc_res)) $available_doctors[] = $doctor_row;
        mysqli_stmt_close($doc_stmt);
    }
}

// 병원이 실제 보유한 모든 활성 시술 — "어떤 시술을 받으셨나요?" 선택지
$review_categories = [];
if ($sel_hospital_id > 0) {
    $rc_stmt = mysqli_prepare($connect, "SELECT DISTINCT tm.name, tc.sort_order, tm.id
        FROM gangnamunni_hospital_treatments ht
        JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND tm.is_active = 1
        ORDER BY COALESCE(tc.sort_order, 999999) ASC, tm.name ASC, tm.id ASC");
    if ($rc_stmt) {
        mysqli_stmt_bind_param($rc_stmt, 'i', $sel_hospital_id);
        mysqli_stmt_execute($rc_stmt);
        $rc_res = mysqli_stmt_get_result($rc_stmt);
        while ($rc = mysqli_fetch_assoc($rc_res)) { if (!empty($rc['name'])) $review_categories[] = $rc['name']; }
        mysqli_stmt_close($rc_stmt);
    }
}

// 예약에서 실제 주문·시술받은 항목만 고정 선택한다.
$card_treatment_category = '';
if (!empty($card['treatment'])) {
    $booked_treatment = trim((string)$card['treatment']);
    foreach ($review_categories as $rc) {
        if ($rc === $booked_treatment) {
            $card_treatment_category = $rc;
            break;
        }
    }
    // 예약 시술명이 마스터 목록에 없어도 예약에 저장된 정확한 이름을 노출·선택한다.
    if ($card_treatment_category === '') {
        array_unshift($review_categories, $booked_treatment);
        $review_categories = array_values(array_unique(array_filter($review_categories)));
        $card_treatment_category = $booked_treatment;
    }
}

// 후기 사진 저장 (시술 전/후 카테고리별) — review_images.photo_category 에 'before'/'after' 저장
function rw_save_review_photos($connect, $review_id, $field, $category, $max) {
    if (empty($_FILES[$field]) || empty($_FILES[$field]['name'][0])) return;
    // 새 업로드가 있으면 해당 카테고리 기존 이미지 제거 (수정 시 중복 방지)
    if ($del = mysqli_prepare($connect, "DELETE FROM review_images WHERE review_id = ? AND photo_category = ?")) {
        mysqli_stmt_bind_param($del, 'is', $review_id, $category);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }
    $dir = __DIR__ . '/uploads/reviews/' . $review_id . '/';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $cnt = min(count($_FILES[$field]['name']), $max);
    $order = 0;
    for ($i = 0; $i < $cnt; $i++) {
        $tmp = $_FILES[$field]['tmp_name'][$i] ?? '';
        $size = intval($_FILES[$field]['size'][$i] ?? 0);
        $ftype = $_FILES[$field]['type'][$i] ?? '';
        if (empty($tmp) || !is_uploaded_file($tmp)) continue;
        if ($size > 5 * 1024 * 1024) continue;
        if (!in_array($ftype, $allowed)) continue;
        $ext = $ftype === 'image/png' ? 'png' : ($ftype === 'image/gif' ? 'gif' : ($ftype === 'image/webp' ? 'webp' : 'jpg'));
        $fname = $category . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($tmp, $dir . $fname)) {
            @chmod($dir . $fname, 0644);
            $rel = 'uploads/reviews/' . $review_id . '/' . $fname;
            $oname = $_FILES[$field]['name'][$i] ?? '';
            if ($ins = mysqli_prepare($connect, "INSERT INTO review_images (review_id, image_url, image_order, file_size, original_name, photo_category) VALUES (?, ?, ?, ?, ?, ?)")) {
                mysqli_stmt_bind_param($ins, 'isiiss', $review_id, $rel, $order, $size, $oname, $category);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
            $order++;
        }
    }
}

$msg = '';
$msg_type = '';

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_edit_id = intval($_POST['edit_id'] ?? 0);
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    // 시술은 사용자 입력이 아닌 본인 예약 데이터에서만 결정한다.
    $treatments = $post_edit_id > 0
        ? trim((string)($edit_review['treatments'] ?? ''))
        : trim((string)($card['treatment'] ?? ''));
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $doctor_unknown = !empty($_POST['doctor_unknown']);
    $posted_doctor_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['doctor_ids'] ?? ''))), function ($id) { return $id > 0; })));
    $doctor_id = 0;
    $doctor_name = $doctor_unknown ? '담당 의사 모름' : '';
    if (!$doctor_unknown && $posted_doctor_ids && $hospital_id > 0) {
        $doctor_placeholders = implode(',', array_fill(0, count($posted_doctor_ids), '?'));
        $doctor_check = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_doctors WHERE id IN ($doctor_placeholders) AND hospital_id=? AND status='active'");
        if ($doctor_check) {
            $doctor_bind_values = array_merge($posted_doctor_ids, [$hospital_id]);
            mysqli_stmt_bind_param($doctor_check, str_repeat('i', count($doctor_bind_values)), ...$doctor_bind_values);
            mysqli_stmt_execute($doctor_check);
            $valid_doctor_names = [];
            $valid_doctor_ids = [];
            $doctor_result = mysqli_stmt_get_result($doctor_check);
            while ($doctor_row = mysqli_fetch_assoc($doctor_result)) {
                $valid_doctor_ids[(int)$doctor_row['id']] = true;
                $valid_doctor_names[(int)$doctor_row['id']] = trim((string)$doctor_row['name']);
            }
            mysqli_stmt_close($doctor_check);
            // 사용자가 선택한 순서를 유지한다.
            $ordered_names = [];
            $ordered_ids = [];
            foreach ($posted_doctor_ids as $selected_id) {
                if (isset($valid_doctor_ids[$selected_id])) {
                    $ordered_ids[] = $selected_id;
                    $ordered_names[] = $valid_doctor_names[$selected_id];
                }
            }
            if ($ordered_names) {
                $doctor_id = $ordered_ids[0];
                $doctor_name = mb_substr(implode(', ', $ordered_names), 0, 100, 'UTF-8');
            }
        }
    }
    $agreed_guidelines = !empty($_POST['agree_guidelines']);

    // 예약당 후기 1회 제한 (신규 작성 시) — 이미 작성했다면 중복 방지
    if ($post_edit_id <= 0 && $booking_id > 0) {
        $existing_bk_review = getBookingReview($connect, $booking_id);
        if ($existing_bk_review) {
            header('Location: my_reviews.php');
            exit;
        }
    }

    // 필수: 담당 의사(또는 모름), 평점, 커뮤니티 가이드라인 동의.
    // 제목/내용/사진은 선택 입력이다.
    if ($hospital_id <= 0) {
        $msg = '병원 정보를 확인해주세요.';
        $msg_type = 'danger';
    } elseif ($doctor_name === '') {
        $msg = '담당 의사를 입력하거나 ‘담당 의사 모름’을 선택해주세요.';
        $msg_type = 'danger';
    } elseif ($rating < 1 || $rating > 5) {
        $msg = '평점을 선택해주세요.';
        $msg_type = 'danger';
    } elseif ($title === '') {
        $msg = '제목을 입력해주세요.';
        $msg_type = 'danger';
    } elseif (mb_strlen($content, 'UTF-8') < 10) {
        $msg = '내용을 10자 이상 입력해주세요.';
        $msg_type = 'danger';
    } elseif (!$agreed_guidelines) {
        $msg = '커뮤니티 가이드라인에 동의해주세요.';
        $msg_type = 'danger';
    } elseif ($post_edit_id > 0) {
        // 수정 (본인 후기만, 재승인 대기 상태로 전환)
        $stmt = mysqli_prepare($connect,
            "UPDATE hospital_reviews SET hospital_id=?, title=?, content=?, rating=?, treatments=?, doctor_id=NULLIF(?,0), doctor_name=?, review_status='pending', is_active=0, updated_at=NOW()
             WHERE id=? AND user_id=?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issisisis', $hospital_id, $title, $content, $rating, $treatments, $doctor_id, $doctor_name, $post_edit_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $review_id = $post_edit_id;

                $old_hospital_id = (int)($edit_review['hospital_id'] ?? $hospital_id);
                foreach (array_unique([$old_hospital_id, $hospital_id]) as $rating_hospital_id) {
                    if ($rating_hospital_id <= 0) continue;
                    mysqli_query($connect, "UPDATE gangnamunni_hospitals SET
                        review_count=(SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id={$rating_hospital_id} AND is_active=1),
                        rating=(SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))),0) FROM hospital_reviews WHERE hospital_id={$rating_hospital_id} AND is_active=1)
                        WHERE id={$rating_hospital_id}");
                }

                if (!empty($_POST['treatment_ids'])) {
                    require_once __DIR__ . '/includes/treatment_review_scorer.php';
                    scoreReviewTreatments($connect, $review_id, $hospital_id, $content, array_map('intval', $_POST['treatment_ids']));
                }

                rw_save_review_photos($connect, $review_id, 'photos_before', 'before', 2);
                rw_save_review_photos($connect, $review_id, 'photos_after', 'after', 2);

                $msg = term('review', '후기') . '가 수정되었습니다. 관리자 승인 후 다시 공개됩니다.';
                $msg_type = 'success';
            } else {
                $msg = '수정 중 오류가 발생했습니다.';
                $msg_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        ensureReviewBookingColumn($connect);
        $booking_id_val = $booking_id > 0 ? $booking_id : null;
        $stmt = mysqli_prepare($connect,
            "INSERT INTO hospital_reviews (hospital_id, user_id, user_name, title, content, rating, treatments, booking_id, doctor_id, doctor_name, review_status, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, 'pending', 0, NOW())"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssisiis', $hospital_id, $user_id, $user_name, $title, $content, $rating, $treatments, $booking_id_val, $doctor_id, $doctor_name);
            if (mysqli_stmt_execute($stmt)) {
                $review_id = mysqli_insert_id($connect);

                // 시술 감성 점수 분석
                if (!empty($_POST['treatment_ids'])) {
                    require_once __DIR__ . '/includes/treatment_review_scorer.php';
                    scoreReviewTreatments($connect, $review_id, $hospital_id, $content, array_map('intval', $_POST['treatment_ids']));
                }

                // 사진 업로드 처리 (시술 전 2장 + 시술 후 2장)
                rw_save_review_photos($connect, $review_id, 'photos_before', 'before', 2);
                rw_save_review_photos($connect, $review_id, 'photos_after', 'after', 2);

                $msg = term('review', '후기') . '가 등록되었습니다. 관리자 승인 후 공개됩니다.';
                $msg_type = 'success';

                // 관리자 알림
                $hosp_name_r = '';
                $hr = mysqli_query($connect, "SELECT name FROM gangnamunni_hospitals WHERE id={$hospital_id} LIMIT 1");
                if ($hr && $hrow = mysqli_fetch_assoc($hr)) $hosp_name_r = $hrow['name'];
                notifyAdmins($connect, 'review',
                    '새 후기 등록',
                    htmlspecialchars($hosp_name_r) . ' — ' . htmlspecialchars($user_name) . ' (★' . $rating . ')',
                    'admin_reviews.php',
                    ['review_id' => $review_id, 'hospital_id' => $hospital_id]
                );
            } else {
                $msg = '등록 중 오류가 발생했습니다.';
                $msg_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 등록/수정 성공 시 내 후기 목록(my_reviews.php)으로 이동
    if ($msg_type === 'success') {
        header('Location: my_reviews.php');
        exit;
    }
}

// 헤더 (standalone — 공통 header.php / 글로벌 네비 제거, HTML 셸만 직접 구성)
if (!function_exists('getTheme')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/theme_helper.php';
}
if (isset($connect) && $connect && function_exists('loadThemeSettings') && !defined('THEME_LOADED')) {
    loadThemeSettings($connect);
    define('THEME_LOADED', true);
}
if (!isset($page_title)) $page_title = '후기 작성';
$base_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$css_base = ($base_dir === '' || $base_dir === '/') ? '' : $base_dir;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <base href="<?php echo htmlspecialchars(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/app/', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($page_title); ?><?php echo function_exists('getTheme') ? ' | ' . getTheme('site_name') : ''; ?></title>
    <?php if (function_exists('generateFaviconLinks')) echo generateFaviconLinks(); ?>
    <?php if (function_exists('generateGoogleFontsLink')) echo generateGoogleFontsLink(); ?>
    <?php if (function_exists('generateThemeCSS')) echo generateThemeCSS(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/common.css?v=2">
</head>
<body>
    <div class="main-content"><?php ?>

<style>
html{background:#3f3f3f;}
body{margin:0;background:#3f3f3f;color:#111;}
.main-content{width:100%;max-width:640px;min-height:100vh;margin:0 auto;background:#fff;}
.rw-container { max-width: 640px; margin: 0 auto; padding: 4px 20px 18px; }
/* 상단 헤더 (detail.php 스타일) */
.rw-header {
    position: sticky; top: 0; height:64px;
    max-width: 640px; margin: 0 auto; padding:0 14px;
    background:#fff;
    display: flex; align-items: center; gap: 12px;
    z-index: 99;
}
.rw-back-btn {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border: none; background: none;
    font-size:28px;
    color: inherit; cursor: pointer; text-decoration: none;
}
.rw-header-title {
    position: absolute; left: 50%; transform: translateX(-50%);
    max-width: 60%; text-align: center;
    font-size:20px;
    font-weight:500;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
/* 상단 병원 카드 (my_reviews.php writable-card 스타일) */
.writable-card{background:#fff;padding:20px;display:flex;gap:18px;}
.writable-card .wc-img{display:block;width:120px;height:160px;max-width:120px;object-fit:contain;object-position:center;overflow:hidden;flex:0 0 120px;background:#fff;border-radius:0;}
.writable-card .wc-noimg{width:120px;height:160px;border-radius:0;background:#d9d9d9;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:28px;flex:0 0 120px;overflow:hidden;}
.writable-card .wc-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;}
.writable-card .wc-hospital{font-size:15px;font-weight:400;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;}
.writable-card .wc-treatment{font-size:20px;font-weight:700;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;letter-spacing:-.03em;}
.writable-card .wc-address,.writable-card .wc-meta{font-size:15px;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;}
.rw-form-group { margin-bottom: 22px; }
.rw-label { display: block; font-size:15px; font-weight:600; color:#111; margin-bottom:10px; }
.rw-required{color:#AE1932;}
.rw-input, .rw-select, .rw-textarea {
    width: 100%; padding: 12px 14px; border: 1px solid #ddd; border-radius:0;background:#fff;
    font-size:14px; box-sizing: border-box; transition: border-color 0.2s;
}
.rw-input:focus, .rw-select:focus, .rw-textarea:focus { border-color: var(--primary-color, #AE1932); outline: none; }
.rw-textarea { min-height: 190px; resize: vertical; }
.rw-rating { display: flex; gap: 8px; flex-direction: row-reverse; justify-content: center; }
.rw-rating input { display: none; }
.rw-rating label { font-size:42px;line-height:1;color:#d8d8d8;cursor:pointer;transition:color .2s; }
.rw-rating label:hover, .rw-rating label:hover ~ label,
.rw-rating input:checked ~ label { color: #FFB800; }
.rw-submit {
    width:100%;height:52px;padding:0;background:#AE1932;color:#fff;
    border:0;border-radius:0;font-size:18px;font-weight:700;cursor:pointer;
}
.rw-submit:hover { opacity: 0.9; }
.rw-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
.rw-alert-success { background: #d4edda; color: #155724; }
.rw-alert-danger { background: #f8d7da; color: #721c24; }
.rw-treatment-chips { display:flex;flex-wrap:wrap;gap:8px 18px; }
.rw-tchip {
    padding:3px 10px;border:0;
    background:#f1f1f1;color:#aaa;
    font-size:12px;
    cursor: default; font-family: inherit;
}
.rw-tchip.selected { background:#FFEEEE;color:#AE1932; }
.rw-divider { border-bottom:1px solid #d2cfcf;padding-bottom:20px; }
.rw-doctor-head{display:flex;align-items:center;justify-content:space-between;gap:16px;}
.rw-doctor-search{width:112px;height:34px;border:0;background:#AE1932;color:#fff;font-size:13px;cursor:pointer;}
.rw-doctor-row{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:15px;}
.rw-selected-doctor{display:none;width:max-content;max-width:100%;margin-top:8px;padding:5px 9px;border:1px solid #e5a8b1;border-radius:4px;color:#222;font-size:13px;align-items:center;gap:8px;}
.rw-selected-doctor.visible{display:flex;}
.rw-selected-doctor button{border:0;background:transparent;color:#777;font-size:18px;line-height:1;cursor:pointer;}
.rw-selected-doctors{display:flex;flex-wrap:wrap;gap:8px;}
.rw-doctor-modal{position:fixed;inset:0;z-index:20000;display:none;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.55);}
.rw-doctor-modal.open{display:flex;}
.rw-doctor-sheet{width:100%;max-width:640px;max-height:92vh;padding:16px 20px 20px;background:#fff;display:flex;flex-direction:column;transform:translateY(0);transition:transform .25s ease;will-change:transform;}
.rw-doctor-sheet.dragging{transition:none;}
.rw-doctor-handle{width:48px;height:5px;margin:0 auto 18px;border-radius:4px;background:#AE1932;flex:none;cursor:grab;touch-action:none;}
.rw-doctor-handle:active{cursor:grabbing;}
.rw-doctor-sheet h2{font-size:21px;margin:0 0 22px;font-weight:700;}
.rw-doctor-list{max-height:504px;overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;scroll-snap-type:y proximity;display:flex;flex-direction:column;gap:18px;padding-bottom:18px;}
.rw-doctor-card{width:100%;height:150px;flex:0 0 150px;padding:22px;border:3px solid #f1f1f1;background:#fff;display:flex;align-items:center;gap:28px;text-align:left;cursor:pointer;font:inherit;color:#111;}
.rw-doctor-card{scroll-snap-align:start;}
.rw-doctor-card.selected{border-color:#AE1932;}
.rw-doctor-photo{width:104px;height:104px;flex:0 0 104px;border-radius:50%;object-fit:cover;background:#ddd;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:34px;overflow:hidden;}
.rw-doctor-copy{min-width:0;flex:1;}
.rw-doctor-card-name{display:block;font-size:17px;font-weight:700;margin-bottom:10px;}
.rw-doctor-specialty{display:block;font-size:14px;color:#AE1932;margin-bottom:24px;}
.rw-doctor-tags{display:flex;flex-wrap:wrap;gap:8px 14px;}
.rw-doctor-tag{padding:4px 8px;background:#f4f4f4;font-size:13px;}
.rw-doctor-confirm{width:100%;height:52px;flex:none;border:0;background:#AE1932;color:#fff;font-size:18px;font-weight:700;cursor:pointer;}
.rw-doctor-confirm:disabled{opacity:.45;cursor:not-allowed;}
.rw-doctor-empty{padding:50px 20px;text-align:center;color:#888;border:1px solid #eee;}
.rw-counter{text-align:right;margin-top:5px;font-size:11px;color:#aaa;}
.rw-photo-title{margin-bottom:14px;font-size:15px;font-weight:700;}
.rw-photo-note{font-weight:500;}
.rw-container .pu-slot{width:112px;height:112px;border:1px dashed #bbb;border-radius:0;background:#fff;}
.rw-container .pu-grid{gap:12px;}
.rw-container .pu-add{font-size:28px;color:#ccc;}
.rw-photo-row{display:flex;align-items:flex-end;gap:12px;}
.rw-photo-row .pu-section{margin:0;}
.rw-photo-capacity{width:112px;height:112px;border:1px dashed #bbb;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:13px;}
.rw-native-photo-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}
.rw-native-photo-grid{display:flex;flex-wrap:wrap;gap:12px;}
.rw-native-photo-add,.rw-native-photo-preview{width:112px;height:112px;border:1px dashed #bbb;background:#fff;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.rw-native-photo-add{flex-direction:column;gap:7px;color:#bbb;font-size:12px;cursor:pointer;}
.rw-native-photo-add i{font-size:25px;}
.rw-native-photo-preview img{width:100%;height:100%;object-fit:cover;}
.rw-native-photo-count{color:#bbb;font-size:12px;}
@media(max-width:420px){.writable-card{gap:14px}.writable-card .wc-img,.writable-card .wc-noimg{width:120px;height:160px;max-width:120px;flex-basis:120px}.rw-container .pu-slot{width:88px;height:88px}.rw-doctor-list{max-height:432px}.rw-doctor-card{height:126px;flex-basis:126px;padding:16px;gap:16px}.rw-doctor-photo{width:82px;height:82px;flex-basis:82px}.rw-doctor-specialty{margin-bottom:14px}.rw-doctor-sheet{padding-left:16px;padding-right:16px}}
</style>

<div class="rw-header">
    <a href="<?php echo htmlspecialchars($review_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="rw-back-btn"><i class="bi bi-chevron-left"></i></a>
    <div class="rw-header-title"><?php echo $edit_review ? '시술후기 수정' : '시술후기 작성'; ?></div>
</div>

<?php if ($card): ?>
<div class="writable-card">
    <?php $hospital_placeholder = 'assets/images/seoul_beauty_hospital_placeholder.jpeg'; ?>
    <img class="wc-img" src="<?php echo htmlspecialchars(!empty($card['main_image_url']) ? $card['main_image_url'] : $hospital_placeholder); ?>" alt="<?php echo htmlspecialchars($card['name'] ?? ''); ?>" onerror="this.onerror=null;this.src='<?php echo $hospital_placeholder; ?>'">
    <div class="wc-info">
        <div class="wc-hospital"><?php echo htmlspecialchars($card['name'] ?? ''); ?></div>
        <?php if (!empty($card['treatment'])): ?>
        <div class="wc-treatment"><?php echo htmlspecialchars($card['treatment']); ?></div>
        <?php endif; ?>
        <?php if (!empty($card['address'])): ?>
        <div class="wc-address"><?php echo htmlspecialchars($card['address']); ?></div>
        <?php endif; ?>
        <?php if (!empty($card['doctor_name']) || !empty($card['booking_date'])): ?>
        <div class="wc-meta"><?php echo htmlspecialchars($card['doctor_name'] ?? ''); ?><?php echo (!empty($card['doctor_name']) && !empty($card['booking_date'])) ? ' | ' : ''; ?><?php echo !empty($card['booking_date']) ? '시술일 ' . htmlspecialchars(str_replace('-', '.', $card['booking_date'])) : ''; ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="rw-container">

    <?php if ($msg): ?>
    <div class="rw-alert rw-alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php if ($msg_type === 'success'): ?>
    <a href="my_reviews.php" class="rw-submit" style="display:block; text-align:center; text-decoration:none;"><?php echo term('review', '후기'); ?> 목록으로 돌아가기</a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('review'); ?>
    <?php endif; ?>

    <?php if ($msg_type !== 'success'): ?>
    <form method="post" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($review_write_csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($review_back_url, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($edit_review): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int)$edit_review['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="hospital_id" value="<?php echo (int)$sel_hospital_id; ?>">
        <?php if ($prefill_booking_id > 0): ?>
        <input type="hidden" name="booking_id" value="<?php echo (int)$prefill_booking_id; ?>">
        <?php endif; ?>

        <?php $sel_treatment = $edit_review ? trim((string)$edit_review['treatments']) : $card_treatment_category; ?>
        <?php if (!empty($review_categories)): ?>
        <div class="rw-form-group rw-divider">
            <label class="rw-label">어떤 <?php echo term('service', '시술'); ?>을 받으셨나요?<span class="rw-required">*</span></label>
            <input type="hidden" name="treatments" id="treatmentInput" value="<?php echo htmlspecialchars($sel_treatment); ?>">
            <div class="rw-treatment-chips">
                <?php foreach ($review_categories as $rc): ?>
                <span class="rw-tchip <?php echo ($sel_treatment === $rc) ? 'selected' : ''; ?>"><?php echo htmlspecialchars($rc); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="treatments" id="treatmentInput" value="<?php echo htmlspecialchars($sel_treatment); ?>">
        <?php endif; ?>

        <?php
        $selected_doctor_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['doctor_ids'] ?? ''))))));
        if (!$selected_doctor_ids && !empty($edit_review['doctor_id'])) $selected_doctor_ids[] = (int)$edit_review['doctor_id'];
        $edit_doctor_names = array_values(array_filter(array_map('trim', explode(',', (string)($edit_review['doctor_name'] ?? '')))));
        $selected_doctor_names = [];
        foreach ($available_doctors as $_doctor_option) {
            $_option_id = (int)$_doctor_option['id'];
            if (in_array($_option_id, $selected_doctor_ids, true) || in_array(trim((string)$_doctor_option['name']), $edit_doctor_names, true) || (!$selected_doctor_ids && !empty($card['doctor_name']) && trim((string)$card['doctor_name']) === trim((string)$_doctor_option['name']))) {
                if (!in_array($_option_id, $selected_doctor_ids, true)) $selected_doctor_ids[] = $_option_id;
                $selected_doctor_names[$_option_id] = (string)$_doctor_option['name'];
            }
        }
        $doctor_unknown_checked = !empty($_POST['doctor_unknown']);
        ?>
        <div class="rw-form-group rw-divider">
            <div class="rw-doctor-head">
                <label class="rw-label" style="margin:0;">담당 의사<span class="rw-required">*</span></label>
                <button type="button" class="rw-doctor-search" onclick="openDoctorModal()">의사 검색</button>
            </div>
            <input type="hidden" id="reviewDoctorIds" name="doctor_ids" value="<?php echo $doctor_unknown_checked ? '' : htmlspecialchars(implode(',', $selected_doctor_ids)); ?>">
            <input type="hidden" id="reviewDoctorName" name="doctor_name" value="<?php echo $doctor_unknown_checked ? '' : htmlspecialchars(implode(', ', array_values($selected_doctor_names))); ?>">
            <div class="rw-selected-doctors" id="selectedDoctorChips"><?php if (!$doctor_unknown_checked): foreach ($selected_doctor_names as $_selected_id => $_selected_name): ?><div class="rw-selected-doctor visible" data-selected-doctor-id="<?php echo (int)$_selected_id; ?>"><span><?php echo htmlspecialchars($_selected_name); ?></span><button type="button" aria-label="선택 해제" onclick="removeSelectedDoctor(<?php echo (int)$_selected_id; ?>)">×</button></div><?php endforeach; endif; ?></div>
            <label class="rw-doctor-row"><input type="checkbox" id="doctorUnknown" name="doctor_unknown" value="1" onchange="toggleReviewDoctor(this)" <?php echo $doctor_unknown_checked ? 'checked' : ''; ?>><span>담당 의사 모름</span></label>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">평점<span class="rw-required">*</span></label>
            <div class="rw-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i == ($_POST['rating'] ?? ($edit_review['rating'] ?? 0))) ? 'checked' : ''; ?> required>
                <label for="star<?php echo $i; ?>">&#9733;</label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">제목<span class="rw-required">*</span></label>
            <input type="text" name="title" class="rw-input" placeholder="<?php echo term('review', '후기'); ?> 제목을 입력하세요." value="<?php echo htmlspecialchars($_POST['title'] ?? ($edit_review['title'] ?? '')); ?>" required maxlength="200">
        </div>

        <div class="rw-form-group">
            <label class="rw-label">내용<span class="rw-required">*</span></label>
            <textarea name="content" id="reviewContent" class="rw-textarea" placeholder="상세한 <?php echo term('service', '시술'); ?> 경험을 공유해주세요." required minlength="10" maxlength="2000" oninput="updateReviewCounter(this)"><?php echo htmlspecialchars($_POST['content'] ?? ($edit_review['content'] ?? '')); ?></textarea>
            <div class="rw-counter"><span id="reviewContentCount">0</span>자 / 최소 10자</div>
        </div>

        <!-- 사진 첨부 (시술 전 2장 + 시술 후 2장) -->
        <div class="rw-form-group">
            <div class="rw-photo-title"><?php echo term('service', '시술'); ?> 전후 사진 <span class="rw-photo-note">(최대 4장, 각 5MB 이하)</span></div>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--bs-body-color,#333);"><?php echo term('service', '시술'); ?> 전 <span style="color:#999; font-weight:400;"></span></div>
                    <div class="rw-native-photo-grid" id="reviewBeforeGrid">
                        <input class="rw-native-photo-input" type="file" id="reviewBeforeInput" name="photos_before[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="previewReviewPhotos(this,'reviewBeforeGrid','reviewBeforeCount')">
                        <label class="rw-native-photo-add" for="reviewBeforeInput"><i class="bi bi-plus-circle"></i><span>사진 추가</span></label>
                        <div class="rw-photo-capacity" id="reviewBeforeCount">0 / 2</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--bs-body-color,#333);"><?php echo term('service', '시술'); ?> 후 <span style="color:#999; font-weight:400;"></span></div>
                    <div class="rw-native-photo-grid" id="reviewAfterGrid">
                        <input class="rw-native-photo-input" type="file" id="reviewAfterInput" name="photos_after[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="previewReviewPhotos(this,'reviewAfterGrid','reviewAfterCount')">
                        <label class="rw-native-photo-add" for="reviewAfterInput"><i class="bi bi-plus-circle"></i><span>사진 추가</span></label>
                        <div class="rw-photo-capacity" id="reviewAfterCount">0 / 2</div>
                    </div>
                </div>
            </div>
            <!-- 공용 동의 (사진 첨부 시 표시) -->
            <label class="pu-consent" id="reviewPhotoConsent" style="display:none; margin-top:12px; cursor:pointer;">
                <input type="checkbox" name="photo_consent" id="reviewPhotoConsentCb" value="1">
                <span><?php echo term('service', '시술'); ?> <?php echo term('review', '후기'); ?> 사진을 첨부합니다. 등록된 사진은 <?php echo term('review', '후기'); ?>와 함께 공개됩니다.</span>
            </label>
        </div>

        <!-- 커뮤니티 가이드라인 동의 (Apple Guideline 1.2) -->
        <div class="rw-form-group" style="margin-top:16px;">
            <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="agree_guidelines" id="agreeGuidelines" required style="margin-top:3px;">
                <span><a href="community_guidelines.php" target="_blank" style="color:#AE1932; text-decoration:underline;">커뮤니티 가이드라인</a>을 읽었으며, 허위·광고·욕설 등 부적절한 콘텐츠 작성 시 삭제 및 계정 제재에 동의합니다.</span>
            </label>
        </div>

        <p style="font-size:13px;color:#bbb;margin:-6px 0 20px 20px;">등록된 후기는 관리자 승인 후 공개됩니다.</p>
        <button type="submit" class="rw-submit" id="reviewSubmitBtn"><?php echo $edit_review ? '수정했어요' : '작성했어요'; ?></button>
    </form>
    <?php endif; ?>
</div>

<div class="rw-doctor-modal" id="doctorModal" aria-hidden="true" onclick="if(event.target===this)closeDoctorModal()">
    <section class="rw-doctor-sheet" id="doctorSheet" role="dialog" aria-modal="true" aria-labelledby="doctorModalTitle">
        <div class="rw-doctor-handle" id="doctorSheetHandle" aria-label="아래로 끌어 닫기" role="button" tabindex="0"></div>
        <h2 id="doctorModalTitle">어떤 원장님께 시술받으셨나요?</h2>
        <div class="rw-doctor-list">
            <?php if (empty($available_doctors)): ?>
            <div class="rw-doctor-empty">등록된 의사가 없습니다.<br>담당 의사 모름을 선택해주세요.</div>
            <?php else: foreach ($available_doctors as $_doctor):
                $_tags = array_values(array_filter(explode('||', (string)($_doctor['treatment_tags'] ?? ''))));
            ?>
            <button type="button" class="rw-doctor-card" data-doctor-id="<?php echo (int)$_doctor['id']; ?>" data-doctor-name="<?php echo htmlspecialchars($_doctor['name'], ENT_QUOTES, 'UTF-8'); ?>" onclick="selectDoctorCard(this)">
                <span class="rw-doctor-photo"><?php if (!empty($_doctor['photo_url'])): ?><img src="<?php echo htmlspecialchars($_doctor['photo_url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><i class="bi bi-person"></i><?php endif; ?></span>
                <span class="rw-doctor-copy">
                    <span class="rw-doctor-card-name"><?php echo htmlspecialchars($_doctor['name']); ?></span>
                    <span class="rw-doctor-specialty"><i class="bi bi-file-medical"></i> <?php echo htmlspecialchars($_doctor['specialty'] ?: '전문의'); ?></span>
                    <?php if ($_tags): ?><span class="rw-doctor-tags"><?php foreach (array_slice($_tags, 0, 4) as $_tag): ?><span class="rw-doctor-tag"><?php echo htmlspecialchars($_tag); ?></span><?php endforeach; ?></span><?php endif; ?>
                </span>
            </button>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" class="rw-doctor-confirm" id="doctorConfirmButton" onclick="confirmDoctorSelection()" disabled>선택했어요</button>
    </section>
</div>

<script>
function toggleReviewDoctor(checkbox) {
    if (checkbox.checked) clearAllSelectedDoctors(false);
}

var draftDoctors = [];

function getCommittedDoctors() {
    var ids = (document.getElementById('reviewDoctorIds').value || '').split(',').filter(Boolean).map(Number);
    var doctors = [];
    ids.forEach(function(id) {
        var card = document.querySelector('.rw-doctor-card[data-doctor-id="' + id + '"]');
        if (card) doctors.push({ id: id, name: card.dataset.doctorName || '' });
    });
    return doctors;
}

function openDoctorModal() {
    var modal = document.getElementById('doctorModal');
    var sheet = document.getElementById('doctorSheet');
    sheet.classList.remove('dragging');
    sheet.style.transform = 'translateY(0)';
    draftDoctors = getCommittedDoctors();
    document.querySelectorAll('.rw-doctor-card').forEach(function(card) {
        var id = parseInt(card.dataset.doctorId, 10);
        card.classList.toggle('selected', draftDoctors.some(function(doctor) { return doctor.id === id; }));
    });
    var confirmButton = document.getElementById('doctorConfirmButton');
    if (confirmButton) confirmButton.disabled = draftDoctors.length === 0;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeDoctorModal() {
    var modal = document.getElementById('doctorModal');
    var sheet = document.getElementById('doctorSheet');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    sheet.classList.remove('dragging');
    sheet.style.transform = 'translateY(0)';
    document.body.style.overflow = '';
}

function selectDoctorCard(card) {
    var id = parseInt(card.dataset.doctorId || '0', 10);
    var index = draftDoctors.findIndex(function(doctor) { return doctor.id === id; });
    if (index >= 0) {
        draftDoctors.splice(index, 1);
        card.classList.remove('selected');
    } else {
        draftDoctors.push({ id: id, name: card.dataset.doctorName || '' });
        card.classList.add('selected');
    }
    document.getElementById('doctorConfirmButton').disabled = draftDoctors.length === 0;
}

function confirmDoctorSelection() {
    if (draftDoctors.length === 0) return;
    commitDoctors(draftDoctors);
    document.getElementById('doctorUnknown').checked = false;
    closeDoctorModal();
}

function commitDoctors(doctors) {
    document.getElementById('reviewDoctorIds').value = doctors.map(function(doctor) { return doctor.id; }).join(',');
    document.getElementById('reviewDoctorName').value = doctors.map(function(doctor) { return doctor.name; }).join(', ');
    var chips = document.getElementById('selectedDoctorChips');
    chips.innerHTML = '';
    doctors.forEach(function(doctor) {
        var chip = document.createElement('div');
        chip.className = 'rw-selected-doctor visible';
        chip.dataset.selectedDoctorId = String(doctor.id);
        var label = document.createElement('span');
        label.textContent = doctor.name;
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.setAttribute('aria-label', '선택 해제');
        remove.textContent = '×';
        remove.onclick = function() { removeSelectedDoctor(doctor.id); };
        chip.appendChild(label);
        chip.appendChild(remove);
        chips.appendChild(chip);
    });
}

function removeSelectedDoctor(id) {
    commitDoctors(getCommittedDoctors().filter(function(doctor) { return doctor.id !== id; }));
}

function clearAllSelectedDoctors(clearUnknown) {
    document.getElementById('reviewDoctorIds').value = '';
    document.getElementById('reviewDoctorName').value = '';
    document.getElementById('selectedDoctorChips').innerHTML = '';
    if (clearUnknown !== false) document.getElementById('doctorUnknown').checked = false;
}

function updateReviewCounter(textarea) {
    var counter = document.getElementById('reviewContentCount');
    if (counter) counter.textContent = textarea.value.length;
}

var initialReviewContent = document.getElementById('reviewContent');
if (initialReviewContent) updateReviewCounter(initialReviewContent);
document.addEventListener('keydown', function(event) { if (event.key === 'Escape') closeDoctorModal(); });

(function enableDoctorSheetDrag() {
    var handle = document.getElementById('doctorSheetHandle');
    var sheet = document.getElementById('doctorSheet');
    var modal = document.getElementById('doctorModal');
    var startY = 0;
    var currentY = 0;
    var startTime = 0;
    var dragging = false;

    handle.addEventListener('pointerdown', function(event) {
        if (!modal.classList.contains('open')) return;
        dragging = true;
        startY = event.clientY;
        currentY = startY;
        startTime = performance.now();
        sheet.classList.add('dragging');
        handle.setPointerCapture(event.pointerId);
        event.preventDefault();
    });

    handle.addEventListener('pointermove', function(event) {
        if (!dragging) return;
        currentY = event.clientY;
        var distance = Math.max(0, currentY - startY);
        sheet.style.transform = 'translateY(' + distance + 'px)';
        event.preventDefault();
    });

    function finishDrag(event) {
        if (!dragging) return;
        dragging = false;
        var distance = Math.max(0, currentY - startY);
        var elapsed = Math.max(1, performance.now() - startTime);
        var velocity = distance / elapsed;
        sheet.classList.remove('dragging');
        if (distance > Math.min(140, sheet.offsetHeight * 0.22) || velocity > 0.75) {
            sheet.style.transform = 'translateY(100%)';
            window.setTimeout(closeDoctorModal, 220);
        } else {
            sheet.style.transform = 'translateY(0)';
        }
        if (event && handle.hasPointerCapture(event.pointerId)) handle.releasePointerCapture(event.pointerId);
    }

    handle.addEventListener('pointerup', finishDrag);
    handle.addEventListener('pointercancel', finishDrag);
    handle.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            closeDoctorModal();
        }
    });
})();

function previewReviewPhotos(input, gridId, countId) {
    var files = Array.from(input.files || []);
    if (files.some(function(file) { return file.size > 5 * 1024 * 1024; })) {
        alert('각 사진은 5MB 이하만 첨부할 수 있습니다.');
        input.value = '';
        files = [];
    }
    if (files.length > 2) {
        alert('시술 전·후 사진은 각각 최대 2장까지 첨부할 수 있습니다.');
        if (typeof DataTransfer !== 'undefined') {
            var transfer = new DataTransfer();
            files.slice(0, 2).forEach(function(file) { transfer.items.add(file); });
            input.files = transfer.files;
            files = files.slice(0, 2);
        } else {
            input.value = '';
            files = [];
        }
    }
    var grid = document.getElementById(gridId);
    grid.querySelectorAll('.rw-native-photo-preview').forEach(function(preview) {
        var url = preview.dataset.objectUrl;
        if (url) URL.revokeObjectURL(url);
        preview.remove();
    });
    var count = document.getElementById(countId);
    files.forEach(function(file) {
        var url = URL.createObjectURL(file);
        var preview = document.createElement('div');
        preview.className = 'rw-native-photo-preview';
        preview.dataset.objectUrl = url;
        var image = document.createElement('img');
        image.src = url;
        image.alt = '';
        preview.appendChild(image);
        grid.insertBefore(preview, count);
    });
    count.textContent = files.length + ' / 2';
    var beforeInput = document.getElementById('reviewBeforeInput');
    var afterInput = document.getElementById('reviewAfterInput');
    var total = (beforeInput.files ? beforeInput.files.length : 0) + (afterInput.files ? afterInput.files.length : 0);
    var consent = document.getElementById('reviewPhotoConsent');
    if (consent) consent.style.display = total > 0 ? 'flex' : 'none';
}

// 후기 폼 제출 시 시술 전/후 사진 blob을 FormData에 추가
(function() {
    var form = document.querySelector('.rw-container form');
    if (!form) return;

    // 사진이 하나라도 있으면 공용 동의 체크박스 노출
    function updatePhotoConsent() {
        var b = (window._puData && window._puData['review_before']) || [];
        var a = (window._puData && window._puData['review_after']) || [];
        var consent = document.getElementById('reviewPhotoConsent');
        if (!consent) return;
        var has = (b.length + a.length) > 0;
        consent.style.display = has ? 'flex' : 'none';
        if (!has) { var cb = document.getElementById('reviewPhotoConsentCb'); if (cb) cb.checked = false; }
    }
    // puRenderGrid 호출마다 동의 표시 갱신
    if (typeof window.puRenderGrid === 'function') {
        var _origRender = window.puRenderGrid;
        window.puRenderGrid = function(prefix) {
            _origRender(prefix);
            var count = ((window._puData && window._puData[prefix]) || []).length;
            var countEl = document.getElementById(prefix === 'review_before' ? 'reviewBeforeCount' : 'reviewAfterCount');
            if (countEl) countEl.textContent = count + ' / 2';
            if (prefix === 'review_before' || prefix === 'review_after') updatePhotoConsent();
        };
    }

    form.addEventListener('submit', function(e) {
        var doctorIds = document.getElementById('reviewDoctorIds').value;
        var doctorUnknown = document.getElementById('doctorUnknown').checked;
        if (!doctorIds && !doctorUnknown) {
            e.preventDefault();
            alert('담당 의사를 선택하거나 담당 의사 모름을 체크해주세요.');
            return;
        }
        var before = (window._puData && window._puData['review_before']) || [];
        var after = (window._puData && window._puData['review_after']) || [];
        if (before.length === 0 && after.length === 0) return; // 사진 없으면 기본 제출

        e.preventDefault();
        var cb = document.getElementById('reviewPhotoConsentCb');
        if (cb && !cb.checked) { alert('사진 첨부 시 동의가 필요합니다.'); return; }

        var formData = new FormData(form);
        before.forEach(function(p, i) { formData.append('photos_before[]', p.blob, 'before_' + i + '.jpg'); });
        after.forEach(function(p, i) { formData.append('photos_after[]', p.blob, 'after_' + i + '.jpg'); });
        formData.set('photo_consent', (cb && cb.checked) ? '1' : '0');

        var btn = document.getElementById('reviewSubmitBtn');
        if (btn) { btn.disabled = true; btn.textContent = '등록 중...'; }
        fetch(form.action || window.location.href, { method: 'POST', body: formData })
            .then(function(r) {
                // 성공 시 서버가 my_reviews.php로 리다이렉트 → fetch가 따라감
                if (r.redirected) { window.location.href = r.url; return null; }
                return r.text();
            })
            .then(function(html) { if (html === null) return; document.open(); document.write(html); document.close(); })
            .catch(function(err) {
                alert('오류가 발생했습니다: ' + err.message);
                if (btn) { btn.disabled = false; btn.textContent = '<?php echo $edit_review ? '수정했어요' : '작성했어요'; ?>'; }
            });
    });
})();
</script>
<?php if (isDevMode($connect)) { echo getDevModeJS_review(); } ?>
<?php $hide_bottom_nav = true; include __DIR__ . '/includes/footer.php'; ?>

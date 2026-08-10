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
        header('Location: reviews.php');
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
            h.address, h.main_image_url,
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
        $ch_stmt = mysqli_prepare($connect, "SELECT name, address, main_image_url FROM gangnamunni_hospitals WHERE id = ?");
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

// 병원이 보유한 시술 대분류(전체 19개 중) — "어떤 시술을 받으셨나요?" 선택지
$review_categories = [];
if ($sel_hospital_id > 0) {
    $rc_stmt = mysqli_prepare($connect, "SELECT DISTINCT tc.name, tc.sort_order
        FROM gangnamunni_hospital_treatments ht
        JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND tm.is_active = 1
        ORDER BY tc.sort_order ASC");
    if ($rc_stmt) {
        mysqli_stmt_bind_param($rc_stmt, 'i', $sel_hospital_id);
        mysqli_stmt_execute($rc_stmt);
        $rc_res = mysqli_stmt_get_result($rc_stmt);
        while ($rc = mysqli_fetch_assoc($rc_res)) { if (!empty($rc['name'])) $review_categories[] = $rc['name']; }
        mysqli_stmt_close($rc_stmt);
    }
}

// 예약한 시술(카드의 wc-treatment)이 위 5개 대분류 중 어디에 속하는지 → 해당 칩 기본 선택
$card_treatment_category = '';
if (!empty($card['treatment'])) {
    $tcat_stmt = mysqli_prepare($connect, "SELECT tc.name
        FROM gangnamunni_treatment_master tm
        JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.name = ? AND tm.is_active = 1 LIMIT 1");
    if ($tcat_stmt) {
        mysqli_stmt_bind_param($tcat_stmt, 's', $card['treatment']);
        mysqli_stmt_execute($tcat_stmt);
        $tcat_row = mysqli_fetch_assoc(mysqli_stmt_get_result($tcat_stmt));
        if ($tcat_row && in_array($tcat_row['name'], $review_categories, true)) {
            $card_treatment_category = $tcat_row['name'];
        }
        mysqli_stmt_close($tcat_stmt);
    }
    // 정확 매칭 실패 시 부분일치 — 5개 대분류 이름이 예약 시술명에 포함되는지 (예: "보톡스 100단위" → "보톡스")
    if ($card_treatment_category === '') {
        foreach ($review_categories as $rc) {
            if ($rc !== '' && (mb_strpos($card['treatment'], $rc) !== false || mb_strpos($rc, $card['treatment']) !== false)) {
                $card_treatment_category = $rc;
                break;
            }
        }
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
    $treatments = trim($_POST['treatments'] ?? '');
    $booking_id = intval($_POST['booking_id'] ?? 0);

    // 예약당 후기 1회 제한 (신규 작성 시) — 이미 작성했다면 중복 방지
    if ($post_edit_id <= 0 && $booking_id > 0) {
        $existing_bk_review = getBookingReview($connect, $booking_id);
        if ($existing_bk_review) {
            header('Location: my_reviews.php');
            exit;
        }
    }

    // 필수: 평점 / 제목 / 내용 (사진은 선택)
    if ($hospital_id <= 0 || $rating < 1 || $rating > 5 || empty($title) || empty($content)) {
        $msg = '평점, 제목, 내용을 모두 입력해주세요.';
        $msg_type = 'danger';
    } elseif ($post_edit_id > 0) {
        // 수정 (본인 후기만, 재승인 대기 상태로 전환)
        $stmt = mysqli_prepare($connect,
            "UPDATE hospital_reviews SET hospital_id=?, title=?, content=?, rating=?, treatments=?, review_status='pending', updated_at=NOW()
             WHERE id=? AND user_id=?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssis', $hospital_id, $title, $content, $rating, $treatments, $post_edit_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $review_id = $post_edit_id;

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
            "INSERT INTO hospital_reviews (hospital_id, user_id, user_name, title, content, rating, treatments, booking_id, review_status, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssssi', $hospital_id, $user_id, $user_name, $title, $content, $rating, $treatments, $booking_id_val);
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
    <base href="<?php echo htmlspecialchars(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/gangnamunni/', ENT_QUOTES, 'UTF-8'); ?>">
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
.rw-container { max-width: 640px; margin: 0 auto; padding: 20px 16px 80px; }
/* 상단 헤더 (detail.php 스타일) */
.rw-header {
    position: sticky; top: 0;
    max-width: 640px; margin: 0 auto;
    padding: 14px 0px;
    background: var(--bs-body-bg, #fff);
    display: flex; align-items: center; gap: 12px;
    z-index: 99;
}
.rw-back-btn {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    border: none; background: none;
    font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
    color: inherit; cursor: pointer; text-decoration: none;
}
.rw-header-title {
    position: absolute; left: 50%; transform: translateX(-50%);
    max-width: 60%; text-align: center;
    font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
/* 상단 병원 카드 (my_reviews.php writable-card 스타일) */
.writable-card{background:var(--bs-body-bg,#fff);padding:16px;display:flex;gap:12px;}
.writable-card .wc-img{width:100px;height:130px;object-fit:cover;flex-shrink:0;background:var(--bs-tertiary-bg,#eee);border-radius:8px;}
.writable-card .wc-noimg{width:100px;height:130px;border-radius:8px;background:var(--bs-tertiary-bg,#eee);display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color,#bbb);font-size:calc(26px * var(--fs-scale,1) * var(--fs-page,1));flex-shrink:0;}
.writable-card .wc-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;}
.writable-card .wc-hospital{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:var(--bs-body-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;}
.writable-card .wc-treatment{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:var(--primary-color,#AE1932);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;}
.writable-card .wc-address{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;}
.writable-card .wc-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rw-form-group { margin-bottom: 16px; }
.rw-label { display: block; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--bs-body-color, #333); margin-bottom: 6px; }
.rw-input, .rw-select, .rw-textarea {
    width: 100%; padding: 10px 12px; border: 1px solid #ddd; 
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); box-sizing: border-box; transition: border-color 0.2s;
}
.rw-input:focus, .rw-select:focus, .rw-textarea:focus { border-color: var(--primary-color, #AE1932); outline: none; }
.rw-textarea { min-height: 150px; resize: vertical; }
.rw-rating { display: flex; gap: 8px; flex-direction: row-reverse; justify-content: center; }
.rw-rating input { display: none; }
.rw-rating label { font-size:35px ; color: #ddd; cursor: pointer; transition: color 0.2s; }
.rw-rating label:hover, .rw-rating label:hover ~ label,
.rw-rating input:checked ~ label { color: #FFB800; }
.rw-submit {
    width: 100%; padding: 14px; background: var(--primary-color, #AE1932); color: #fff;
    border: none; border-radius: 10px; font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; cursor: pointer;
}
.rw-submit:hover { opacity: 0.9; }
.rw-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
.rw-alert-success { background: #d4edda; color: #155724; }
.rw-alert-danger { background: #f8d7da; color: #721c24; }
.rw-treatment-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.rw-tchip {
    padding: 8px 14px;
    background: var(--bs-body-bg, #dcdcdc); color: #000;
    font-size: 13px;
    cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.rw-tchip.selected { background:  #FFEEEE; color: #AE1932; }
.rw-divider { border-bottom: 1px solid #d2cfcf; padding-bottom: 20px; }
</style>

<div class="rw-header">
    <a href="reviews.php" class="rw-back-btn"><i class="bi bi-chevron-left"></i></a>
    <div class="rw-header-title"><?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정' : '작성'; ?></div>
</div>

<?php if ($card): ?>
<div class="writable-card">
    <?php if (!empty($card['main_image_url'])): ?>
    <img class="wc-img" src="<?php echo htmlspecialchars($card['main_image_url']); ?>" alt="">
    <?php else: ?>
    <div class="wc-noimg"><i class="bi bi-building"></i></div>
    <?php endif; ?>
    <div class="wc-info">
        <div class="wc-hospital"><?php echo htmlspecialchars($card['name'] ?? ''); ?></div>
        <?php if (!empty($card['treatment'])): ?>
        <div class="wc-treatment"><?php echo htmlspecialchars($card['treatment']); ?></div>
        <?php endif; ?>
        <?php if (!empty($card['address'])): ?>
        <div class="wc-address"><?php echo htmlspecialchars($card['address']); ?></div>
        <?php endif; ?>
        <?php if (!empty($card['doctor_name']) || !empty($card['booking_date'])): ?>
        <div class="wc-meta"><?php echo htmlspecialchars($card['doctor_name'] ?? ''); ?><?php echo (!empty($card['doctor_name']) && !empty($card['booking_date'])) ? ' | ' : ''; ?><?php echo !empty($card['booking_date']) ? htmlspecialchars(str_replace('-', '.', $card['booking_date'])) : ''; ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="rw-container">

    <?php if ($msg): ?>
    <div class="rw-alert rw-alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php if ($msg_type === 'success'): ?>
    <a href="reviews.php" class="rw-submit" style="display:block; text-align:center; text-decoration:none;"><?php echo term('review', '후기'); ?> 목록으로 돌아가기</a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('review'); ?>
    <?php endif; ?>

    <?php if ($msg_type !== 'success'): ?>
    <form method="post" action="" enctype="multipart/form-data">
        <?php if ($edit_review): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int)$edit_review['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="hospital_id" value="<?php echo (int)$sel_hospital_id; ?>">
        <?php if ($prefill_booking_id > 0): ?>
        <input type="hidden" name="booking_id" value="<?php echo (int)$prefill_booking_id; ?>">
        <?php endif; ?>

        <?php $sel_treatment = $_POST['treatments'] ?? ($edit_review['treatments'] ?? $card_treatment_category); ?>
        <?php if (!empty($review_categories)): ?>
        <div class="rw-form-group rw-divider">
            <label class="rw-label">어떤 <?php echo term('service', '시술'); ?>을 받으셨나요?</label>
            <input type="hidden" name="treatments" id="treatmentInput" value="<?php echo htmlspecialchars($sel_treatment); ?>">
            <div class="rw-treatment-chips">
                <?php foreach ($review_categories as $rc): ?>
                <button type="button" class="rw-tchip <?php echo ($sel_treatment === $rc) ? 'selected' : ''; ?>" data-val="<?php echo htmlspecialchars($rc); ?>" onclick="selectRwTreatment(this)"><?php echo htmlspecialchars($rc); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="treatments" id="treatmentInput" value="<?php echo htmlspecialchars($sel_treatment); ?>">
        <?php endif; ?>

        <div class="rw-form-group">
            <label class="rw-label">평점</label>
            <div class="rw-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i == ($_POST['rating'] ?? ($edit_review['rating'] ?? 5))) ? 'checked' : ''; ?>>
                <label for="star<?php echo $i; ?>">&#9733;</label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">제목</label>
            <input type="text" name="title" class="rw-input" placeholder="<?php echo term('review', '후기'); ?> 제목을 입력하세요" value="<?php echo htmlspecialchars($_POST['title'] ?? ($edit_review['title'] ?? '')); ?>" required maxlength="200">
        </div>

        <div class="rw-form-group">
            <label class="rw-label">내용 *</label>
            <textarea name="content" class="rw-textarea" placeholder="<?php echo term('service', '시술'); ?> 경험을 자세히 공유해주세요" required><?php echo htmlspecialchars($_POST['content'] ?? ($edit_review['content'] ?? '')); ?></textarea>
        </div>

        <!-- 사진 첨부 (시술 전 2장 + 시술 후 2장) -->
        <div class="rw-form-group">
            <label class="rw-label">📷 <?php echo term('service', '시술'); ?> 사진 <span style="color:#999; font-weight:400;">(선택, 각 5MB 이하)</span></label>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--bs-body-color,#333);"><?php echo term('service', '시술'); ?> 전 <span style="color:#999; font-weight:400;"></span></div>
                    <?php
                    $photo_config = ['prefix' => 'review_before', 'max_photos' => 2, 'label' => '', 'show_consent' => false];
                    include __DIR__ . '/includes/photo_upload_ui.php';
                    ?>
                </div>
                <div>
                    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:var(--bs-body-color,#333);"><?php echo term('service', '시술'); ?> 후 <span style="color:#999; font-weight:400;"></span></div>
                    <?php
                    $photo_config = ['prefix' => 'review_after', 'max_photos' => 2, 'label' => '', 'show_consent' => false];
                    include __DIR__ . '/includes/photo_upload_ui.php';
                    ?>
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

        <button type="submit" class="rw-submit" id="reviewSubmitBtn"><?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정하기' : '등록하기'; ?></button>
        <p style="text-align:center; font-size:12px; color:#999; margin-top:8px;"><?php echo $edit_review ? '수정된' : '등록된'; ?> <?php echo term('review', '후기'); ?>는 관리자 승인 후 공개됩니다.</p>
    </form>
    <?php endif; ?>
</div>

<script>
// "어떤 시술을 받으셨나요?" 칩 단일 선택
function selectRwTreatment(btn) {
    document.querySelectorAll('.rw-tchip').forEach(function(b){ b.classList.remove('selected'); });
    btn.classList.add('selected');
    var inp = document.getElementById('treatmentInput');
    if (inp) inp.value = btn.getAttribute('data-val');
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
            if (prefix === 'review_before' || prefix === 'review_after') updatePhotoConsent();
        };
    }

    form.addEventListener('submit', function(e) {
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
                if (btn) { btn.disabled = false; btn.textContent = '<?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정하기' : '등록하기'; ?>'; }
            });
    });
})();
</script>
<?php if (isDevMode($connect)) { echo getDevModeJS_review(); } ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

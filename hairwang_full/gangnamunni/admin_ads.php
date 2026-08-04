<?php
/**
 * 광고 배너 관리 페이지
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */

session_start();

$admin_page_title = '광고 배너 관리';
$admin_current = 'ads';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

// TEMPORARY FEATURE GATE:
// 광고 관리 페이지를 다시 열 때 true로 변경한다.
// 이 가드는 POST/AJAX 처리보다 먼저 실행되므로 최고관리자를 포함한 모든 관리자의 변경 요청도 차단한다.
$admin_ads_enabled = false;
if (!$admin_ads_enabled) {
    http_response_code(503);
    $admin_extra_css = '
    .ads-disabled-card { max-width: 720px; margin: 8vh auto; border: 1px solid var(--bs-border-color); }
    .ads-disabled-icon { font-size: 3rem; color: var(--bs-warning); }
    ';
    include __DIR__ . '/includes/admin_header.php';
    ?>
    <div class="card ads-disabled-card shadow-sm">
        <div class="card-body text-center p-5">
            <div class="ads-disabled-icon mb-3"><i class="bi bi-pause-circle"></i></div>
            <h4 class="mb-3">광고 관리 기능 일시 중지</h4>
            <p class="text-body-secondary mb-4">현재 광고 관리 페이지는 모든 관리자에게 일시적으로 비활성화되어 있습니다.</p>
            <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i>대시보드로 이동</a>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/admin_footer.php';
    mysqli_close($connect);
    exit;
}

require_permission('ads');

$is_super_admin = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$ad_hospital_ids = get_strict_admin_hospital_ids($connect);
$ad_hospital_in = $ad_hospital_ids === null
    ? null
    : (empty($ad_hospital_ids) ? '0' : implode(',', array_map('intval', $ad_hospital_ids)));

if (empty($_SESSION['admin_ads_csrf'])) {
    $_SESSION['admin_ads_csrf'] = bin2hex(random_bytes(32));
}
$admin_ads_csrf = (string)$_SESSION['admin_ads_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = (string)($_POST['admin_csrf'] ?? '');
    if ($posted_csrf === '' || !hash_equals($admin_ads_csrf, $posted_csrf)) {
        http_response_code(403);
        if (($_POST['action'] ?? '') === 'auto_register_advertiser') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
            exit;
        }
        exit('요청이 만료되었습니다. 페이지를 새로고침해주세요.');
    }
}

function admin_ads_forbidden($message = '접근 권한이 없습니다.') {
    http_response_code(403);
    exit($message);
}

function admin_ads_is_global_action($action) {
    return in_array($action, [
        'approve_ad', 'reject_ad', 'add_promo', 'update_promo',
        'delete_promo', 'toggle_promo', 'bulk_delete_promos', 'update_scroll_settings'
    ], true);
}

function admin_ads_valid_url($url, $allow_empty = false) {
    $url = trim((string)$url);
    if ($url === '') return $allow_empty;
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) return false;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return $scheme === null || $scheme === false || in_array(strtolower($scheme), ['http', 'https'], true);
}

$posted_action = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_ads_is_global_action($posted_action) && !$is_super_admin) {
    admin_ads_forbidden();
}

$ad_id_in_scope = static function (int $ad_id) use ($connect, $ad_hospital_ids, $ad_hospital_in): bool {
    if ($ad_id <= 0) return false;
    if ($ad_hospital_ids === null) return true;
    if ($ad_hospital_in === '0') return false;
    $sql = "SELECT a.id
            FROM gangnamunni_ads a
            LEFT JOIN gangnamunni_advertisers adv ON adv.id = a.advertiser_id
            WHERE a.id = {$ad_id}
              AND COALESCE(a.hospital_id, adv.hospital_id) IN ({$ad_hospital_in})
            LIMIT 1";
    $result = mysqli_query($connect, $sql);
    return $result && mysqli_num_rows($result) === 1;
};

// AJAX: 병원 -> 광고주 자동 등록
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_register_advertiser') {
    header('Content-Type: application/json');
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    if ($ad_hospital_ids !== null && !in_array($hospital_id, $ad_hospital_ids, true)) {
        http_response_code(403);
        echo json_encode(['error' => '접근 권한이 없는 병원입니다.']);
        exit;
    }
    $hr = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}");
    $hospital = mysqli_fetch_assoc($hr);
    if ($hospital) {
        // 이미 등록된 광고주인지 확인
        $existing = mysqli_query($connect, "SELECT id FROM gangnamunni_advertisers WHERE hospital_id = {$hospital_id} LIMIT 1");
        if ($existing && mysqli_num_rows($existing) > 0) {
            echo json_encode(['id' => (int)mysqli_fetch_assoc($existing)['id']]);
        } else {
            $name = mysqli_real_escape_string($connect, $hospital['name']);
            $addr = mysqli_real_escape_string($connect, $hospital['address'] ?? '');
            $phone = mysqli_real_escape_string($connect, $hospital['phone'] ?? '');
            $email = mysqli_real_escape_string($connect, $hospital['email'] ?? '');
            mysqli_query($connect, "INSERT INTO gangnamunni_advertisers (hospital_id, hospital_name, address, contact_phone, contact_email, status) VALUES ({$hospital_id}, '{$name}', '{$addr}', '{$phone}', '{$email}', 'active')");
            echo json_encode(['id' => (int)mysqli_insert_id($connect)]);
        }
    } else {
        echo json_encode(['error' => 'Hospital not found']);
    }
    exit;
}


// 하위 호환: 기존 세션 변수 설정

// 이하 로그인된 사용자만 접근
// ======================================================================

// 로그아웃
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_ads.php');
    exit;
}

$message = $_GET['msg'] ?? '';
$error = '';

// 이미지 파일 업로드 처리 함수
function handleAdImageUpload($file_key = 'image_file') {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$file_key];
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => '파일 크기는 5MB 이하여야 합니다'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error' => '올바르지 않은 업로드입니다'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['error' => '이미지 파일만 업로드 가능합니다 (JPG, PNG, GIF, WebP)'];
    }
    $upload_dir = __DIR__ . '/uploads/ads/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['error' => '업로드 폴더를 만들 수 없습니다'];
    }
    $fname = 'ad_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        return ['url' => 'uploads/ads/' . $fname];
    }
    return ['error' => '파일 저장에 실패했습니다'];
}

// 광고 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ad'])) {
    $raw_title = trim((string)($_POST['title'] ?? ''));
    $raw_subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $title = mysqli_real_escape_string($connect, $raw_title);
    $subtitle = mysqli_real_escape_string($connect, $raw_subtitle);
    // 파일 업로드 우선, 없으면 URL 사용
    $upload_result = handleAdImageUpload('image_file');
    if ($upload_result && isset($upload_result['error'])) {
        $error = $upload_result['error'];
    }
    $image_url = ($upload_result && isset($upload_result['url'])) ? $upload_result['url'] : trim((string)($_POST['image_url'] ?? ''));
    $raw_link_url = trim((string)($_POST['link_url'] ?? ''));
    if ($raw_title === '') $error = '광고 제목을 입력해주세요.';
    if (!admin_ads_valid_url($image_url)) $error = '올바른 이미지 URL을 입력해주세요.';
    if (!admin_ads_valid_url($raw_link_url)) $error = '올바른 링크 URL을 입력해주세요.';
    $image_url = mysqli_real_escape_string($connect, $image_url);
    $link_url = mysqli_real_escape_string($connect, $raw_link_url);
    $display_order = intval($_POST['display_order']);
    $ad_type = (string)($_POST['ad_type'] ?? 'banner');
    if (!in_array($ad_type, ['banner', 'premium', 'vip'], true)) $ad_type = 'banner';
    $ad_type = mysqli_real_escape_string($connect, $ad_type);

    $advertiser_id = intval($_POST['advertiser_id'] ?? 0);
    $pricing_id = intval($_POST['pricing_id'] ?? 0);
    $ad_hospital_id = 0;

    if ($advertiser_id > 0) {
        $adv_result = mysqli_query($connect, "SELECT hospital_id FROM gangnamunni_advertisers WHERE id = {$advertiser_id} LIMIT 1");
        if ($adv_result && $adv_row = mysqli_fetch_assoc($adv_result)) {
            $ad_hospital_id = (int)($adv_row['hospital_id'] ?? 0);
        }
    }
    if ($ad_hospital_id <= 0) {
        $error = '병원과 연결된 광고주를 선택해주세요.';
    } elseif ($ad_hospital_ids !== null && !in_array($ad_hospital_id, $ad_hospital_ids, true)) {
        admin_ads_forbidden('다른 병원의 광고를 생성할 수 없습니다.');
    }

    // pricing에서 ad_type 자동 설정
    if ($pricing_id > 0) {
        $pr = mysqli_query($connect, "SELECT ad_type FROM gangnamunni_ad_pricing WHERE id = {$pricing_id}");
        if ($pr && $prow = mysqli_fetch_assoc($pr)) {
            $pricing_ad_type = (string)($prow['ad_type'] ?? '');
            if (in_array($pricing_ad_type, ['banner', 'premium', 'vip'], true)) {
                $ad_type = $pricing_ad_type;
            }
        }
    }

    if ($error === '') {
        mysqli_query($connect, "INSERT INTO gangnamunni_ads (hospital_id, title, subtitle, image_url, link_url, display_order, ad_type, advertiser_id, pricing_id, is_active, status)
                               VALUES ({$ad_hospital_id}, '{$title}', '{$subtitle}', '{$image_url}', '{$link_url}', {$display_order}, '{$ad_type}', {$advertiser_id}, {$pricing_id}, 0, 'pending')");

        $ad_id = mysqli_insert_id($connect);
        $message = '광고 소재가 추가되었습니다. 승인 및 슬롯 배정 후 노출됩니다.';

        if (isset($_POST['proceed_to_payment']) && $_POST['proceed_to_payment'] === '1') {
            header('Location: partner_wallet.php');
            exit;
        }
    }
}

// 광고 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ad') {
    $id = intval($_POST['id']);
    if (!$ad_id_in_scope($id)) admin_ads_forbidden('이 광고를 수정할 권한이 없습니다.');
    $raw_title = trim((string)($_POST['title'] ?? ''));
    $raw_subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $title = mysqli_real_escape_string($connect, $raw_title);
    $subtitle = mysqli_real_escape_string($connect, $raw_subtitle);
    $upload_result = handleAdImageUpload('edit_image_file');
    if ($upload_result && isset($upload_result['error'])) {
        $error = $upload_result['error'];
    }
    $image_url = ($upload_result && isset($upload_result['url'])) ? $upload_result['url'] : trim((string)($_POST['image_url'] ?? ''));
    $raw_link_url = trim((string)($_POST['link_url'] ?? ''));
    if ($raw_title === '') $error = '광고 제목을 입력해주세요.';
    if (!admin_ads_valid_url($image_url)) $error = '올바른 이미지 URL을 입력해주세요.';
    if (!admin_ads_valid_url($raw_link_url)) $error = '올바른 링크 URL을 입력해주세요.';
    $image_url = mysqli_real_escape_string($connect, $image_url);
    $link_url = mysqli_real_escape_string($connect, $raw_link_url);
    $display_order = intval($_POST['display_order']);
    $ad_type = (string)($_POST['ad_type'] ?? 'banner');
    if (!in_array($ad_type, ['banner', 'premium', 'vip'], true)) $ad_type = 'banner';
    $ad_type = mysqli_real_escape_string($connect, $ad_type);
    $review_reset = $is_super_admin ? '' : ", status='pending', is_active=0, reviewed_by=NULL, reviewed_at=NULL, reject_reason=NULL";
    $sql = sprintf(
        "UPDATE gangnamunni_ads SET title='%s', subtitle='%s', image_url='%s', link_url='%s', display_order=%d, ad_type='%s'%s WHERE id=%d",
        $title, $subtitle, $image_url, $link_url, $display_order, $ad_type, $review_reset, $id
    );
    if ($error === '' && mysqli_query($connect, $sql)) {
        $message = '광고가 수정되었습니다.';
    } elseif ($error === '') {
        $error = '수정 실패: ' . mysqli_error($connect);
    }
}

// 광고 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_ad') {
    $id = intval($_POST['ad_id'] ?? 0);
    if (!$ad_id_in_scope($id)) admin_ads_forbidden('이 광고를 삭제할 권한이 없습니다.');
    if (mysqli_query($connect, "DELETE FROM gangnamunni_ads WHERE id = {$id}")) {
        $message = '광고가 삭제되었습니다.';
    } else {
        $error = '삭제 실패: ' . mysqli_error($connect);
    }
}

// Legacy banner activation is retired. Slot assignment controls paid exposure.
if (isset($_GET['toggle'])) {
    header('Location: admin_ads.php?tab=ads&msg=' . urlencode('광고 노출은 광고 지갑과 슬롯 배정에서 관리해주세요.'));
    exit;
}

// 광고 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete_ads') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ids = array_values(array_filter($ids, $ad_id_in_scope));
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "DELETE FROM gangnamunni_ads WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 광고가 삭제되었습니다.';
        }
    }
}

// Only deactivation is retained as a safety action. Activation requires a slot.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_toggle_ads') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ids = array_values(array_filter($ids, $ad_id_in_scope));
    $toggle_to = intval($_POST['toggle_to'] ?? 1);
    if ($toggle_to === 1) {
        $error = '광고 활성화는 광고 지갑 충전 후 슬롯 관리에서 배정해주세요.';
    } elseif (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "UPDATE gangnamunni_ads SET is_active = 0 WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 광고 소재가 비활성화되었습니다.';
        }
    }
}

// 광고 승인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_ad') {
    if (!$is_super_admin) admin_ads_forbidden();
    $ad_id = intval($_POST['ad_id'] ?? 0);
    $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
    if ($ad_id > 0) {
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_ads a
             SET status='approved',
                 is_active=EXISTS(
                     SELECT 1 FROM gangnamunni_ad_slot_bookings b
                     WHERE b.advertiser_id=a.advertiser_id
                       AND b.status='active'
                       AND b.start_date<=CURDATE()
                       AND (b.end_date IS NULL OR b.end_date>=CURDATE())
                 ),
                 reviewed_by=?,reviewed_at=NOW()
             WHERE a.id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $admin_name, $ad_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    $redirect_status = $_REQUEST['status'] ?? $_POST['current_status'] ?? '';
    header('Location: admin_ads.php?tab=ads&status=' . urlencode($redirect_status) . '&msg=' . urlencode('광고 소재가 승인되었습니다. 노출은 슬롯 배정 후 시작됩니다.'));
    exit;
}

// 광고 거절
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_ad') {
    if (!$is_super_admin) admin_ads_forbidden();
    $ad_id = intval($_POST['ad_id'] ?? 0);
    $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
    $reject_reason = trim($_POST['reject_reason'] ?? '');
    if ($ad_id > 0) {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_ads SET status='rejected', is_active=0, reviewed_by=?, reviewed_at=NOW(), reject_reason=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssi', $admin_name, $reject_reason, $ad_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    $redirect_status = $_REQUEST['status'] ?? $_POST['current_status'] ?? '';
    header('Location: admin_ads.php?tab=ads&status=' . urlencode($redirect_status) . '&msg=' . urlencode('광고가 거절되었습니다.'));
    exit;
}


// 프로모션 배너 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promo'])) {
    if (!$is_super_admin) admin_ads_forbidden();
    $title = mysqli_real_escape_string($connect, $_POST['promo_title']);
    $subtitle = mysqli_real_escape_string($connect, $_POST['promo_subtitle']);
    $gradient = mysqli_real_escape_string($connect, $_POST['promo_gradient']);
    $icon = mysqli_real_escape_string($connect, $_POST['promo_icon']);
    $link_url = mysqli_real_escape_string($connect, $_POST['promo_link_url']);
    $display_order = intval($_POST['promo_display_order']);

    mysqli_query($connect, "INSERT INTO gangnamunni_promos (title, subtitle, background_gradient, icon, link_url, display_order)
                           VALUES ('{$title}', '{$subtitle}', '{$gradient}', '{$icon}', '{$link_url}', {$display_order})");

    $message = '프로모션 배너가 추가되었습니다.';
}

// 프로모션 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_promo') {
    if (!$is_super_admin) admin_ads_forbidden();
    $id = intval($_POST['id']);
    $title = mysqli_real_escape_string($connect, $_POST['promo_title']);
    $subtitle = mysqli_real_escape_string($connect, $_POST['promo_subtitle']);
    $gradient = mysqli_real_escape_string($connect, $_POST['promo_gradient']);
    $icon = mysqli_real_escape_string($connect, $_POST['promo_icon']);
    $link_url = mysqli_real_escape_string($connect, $_POST['promo_link_url']);
    $display_order = intval($_POST['promo_display_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = sprintf(
        "UPDATE gangnamunni_promos SET title='%s', subtitle='%s', background_gradient='%s', icon='%s', link_url='%s', display_order=%d, is_active=%d WHERE id=%d",
        $title, $subtitle, $gradient, $icon, $link_url, $display_order, $is_active, $id
    );
    if (mysqli_query($connect, $sql)) {
        $message = '프로모션 배너가 수정되었습니다.';
    } else {
        $error = '수정 실패: ' . mysqli_error($connect);
    }
}

// 프로모션 배너 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_promo') {
    if (!$is_super_admin) admin_ads_forbidden();
    $id = intval($_POST['promo_id'] ?? 0);
    if (mysqli_query($connect, "DELETE FROM gangnamunni_promos WHERE id = {$id}")) {
        $message = '프로모션 배너가 삭제되었습니다.';
    } else {
        $error = '삭제 실패: ' . mysqli_error($connect);
    }
}

// 프로모션 배너 활성화/비활성화
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_promo') {
    if (!$is_super_admin) admin_ads_forbidden();
    $id = intval($_POST['promo_id'] ?? 0);
    mysqli_query($connect, "UPDATE gangnamunni_promos SET is_active = 1 - is_active WHERE id = {$id}");
    header('Location: admin_ads.php?tab=promos');
    exit;
}

// 프로모션 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete_promos') {
    if (!$is_super_admin) admin_ads_forbidden();
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "DELETE FROM gangnamunni_promos WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 프로모션이 삭제되었습니다.';
        }
    }
}

// 스크롤 설정 업데이트
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scroll_settings'])) {
    if (!$is_super_admin) admin_ads_forbidden();
    $ad_auto_scroll_enabled = isset($_POST['ad_auto_scroll_enabled']) ? '1' : '0';
    $ad_auto_scroll_interval = max(1000, min(60000, intval($_POST['ad_auto_scroll_interval'])));
    $promo_auto_scroll_enabled = isset($_POST['promo_auto_scroll_enabled']) ? '1' : '0';
    $promo_auto_scroll_interval = max(1000, min(60000, intval($_POST['promo_auto_scroll_interval'])));

    mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES ('ad_auto_scroll_enabled', '{$ad_auto_scroll_enabled}') ON DUPLICATE KEY UPDATE setting_value = '{$ad_auto_scroll_enabled}'");
    mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES ('ad_auto_scroll_interval', '{$ad_auto_scroll_interval}') ON DUPLICATE KEY UPDATE setting_value = '{$ad_auto_scroll_interval}'");
    mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES ('promo_auto_scroll_enabled', '{$promo_auto_scroll_enabled}') ON DUPLICATE KEY UPDATE setting_value = '{$promo_auto_scroll_enabled}'");
    mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES ('promo_auto_scroll_interval', '{$promo_auto_scroll_interval}') ON DUPLICATE KEY UPDATE setting_value = '{$promo_auto_scroll_interval}'");

    $message = '스크롤 설정이 업데이트되었습니다.';
}

// 환불 성공/실패 메시지 처리
if (isset($_GET['refund_success'])) {
    $message = '환불이 완료되었습니다.';
}
if (isset($_GET['refund_error'])) {
    $error = '환불 처리 중 오류가 발생했습니다: ' . htmlspecialchars($_GET['refund_error']);
}

// 스크롤 설정 가져오기
function getScrollSetting($connect, $key, $default) {
    $key = mysqli_real_escape_string($connect, $key);
    $result = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = '{$key}'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

$ad_auto_scroll_enabled = getScrollSetting($connect, 'ad_auto_scroll_enabled', '1');
$ad_auto_scroll_interval = getScrollSetting($connect, 'ad_auto_scroll_interval', '10000');
$promo_auto_scroll_enabled = getScrollSetting($connect, 'promo_auto_scroll_enabled', '1');
$promo_auto_scroll_interval = getScrollSetting($connect, 'promo_auto_scroll_interval', '10000');

// 탭 결정
$active_tab = $_GET['tab'] ?? 'ads';
if (!in_array($active_tab, ['ads', 'promos', 'scroll'])) $active_tab = 'ads';
if (!$is_super_admin && $active_tab !== 'ads') $active_tab = 'ads';

// 검색어
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지당 행수
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// 광고 유형 필터
$ad_type_filter = $_GET['ad_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// 보기 모드 (list: 기존 리스트, group: 병원별 그룹)
$view_mode = $_GET['view'] ?? 'group';
// 정렬 (newest: 최신순, name: 이름순)
$sort_mode = $_GET['sort'] ?? 'newest';

// 광고 목록 가져오기 (결제 정보 포함, 페이지네이션)
$where_ads = $ad_hospital_ids === null
    ? '1=1'
    : "COALESCE(a.hospital_id, adv.hospital_id) IN ({$ad_hospital_in})";
if (!empty($search)) {
    $s = mysqli_real_escape_string($connect, $search);
    $where_ads .= " AND (a.title LIKE '%{$s}%' OR a.subtitle LIKE '%{$s}%' OR a.link_url LIKE '%{$s}%')";
}
if (!empty($ad_type_filter) && in_array($ad_type_filter, ['banner', 'premium'])) {
    $where_ads .= " AND a.ad_type = '{$ad_type_filter}'";
}
if (!empty($status_filter) && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $where_ads .= " AND a.status = '{$status_filter}'";
}

// 상태별 통계
$ad_stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$stats_join = " LEFT JOIN gangnamunni_advertisers adv ON adv.id = a.advertiser_id ";
$stats_scope = $ad_hospital_ids === null ? '1=1' : "COALESCE(a.hospital_id, adv.hospital_id) IN ({$ad_hospital_in})";
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads a {$stats_join} WHERE {$stats_scope}");
if ($sr) $ad_stats['total'] = (int)mysqli_fetch_assoc($sr)['c'];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads a {$stats_join} WHERE a.status='pending' AND {$stats_scope}");
if ($sr) $ad_stats['pending'] = (int)mysqli_fetch_assoc($sr)['c'];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads a {$stats_join} WHERE a.status='approved' AND {$stats_scope}");
if ($sr) $ad_stats['approved'] = (int)mysqli_fetch_assoc($sr)['c'];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads a {$stats_join} WHERE a.status='rejected' AND {$stats_scope}");
if ($sr) $ad_stats['rejected'] = (int)mysqli_fetch_assoc($sr)['c'];

$total_ads = 0;
$cnt_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_ads a LEFT JOIN gangnamunni_advertisers adv ON adv.id = a.advertiser_id WHERE {$where_ads}");
if ($cnt_result) $total_ads = (int)mysqli_fetch_assoc($cnt_result)['cnt'];
$total_ads_pages = $total_ads > 0 ? ceil($total_ads / $per_page) : 0;

$ads_order = ($sort_mode === 'name') ? 'adv.hospital_name ASC, a.display_order ASC' : 'a.id DESC';
$payment_select = "NULL AS payment_status,NULL AS payment_date,0 AS paid_amount,";
$payment_join = '';
$slot_exposure_select = "EXISTS(
    SELECT 1 FROM gangnamunni_ad_slot_bookings active_b
    WHERE active_b.advertiser_id=a.advertiser_id
      AND active_b.status='active'
      AND active_b.start_date<=CURDATE()
      AND (active_b.end_date IS NULL OR active_b.end_date>=CURDATE())
) AS exposure_active,";

$ads_query = "
    SELECT a.*,
           {$payment_select}
           {$slot_exposure_select}
           adv.hospital_name as advertiser_name
    FROM gangnamunni_ads a
    {$payment_join}
    LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
    WHERE {$where_ads}
    ORDER BY {$ads_order}
    LIMIT {$offset}, {$per_page}
";
$ads_result = mysqli_query($connect, $ads_query);
$ads = [];
while ($row = mysqli_fetch_assoc($ads_result)) {
    $ads[] = $row;
}

// 프로모션 배너 목록 가져오기 (페이지네이션)
$where_promos = '1=1';
if (!empty($search) && $active_tab === 'promos') {
    $s = mysqli_real_escape_string($connect, $search);
    $where_promos .= " AND (title LIKE '%{$s}%' OR subtitle LIKE '%{$s}%')";
}

$total_promos = 0;
$cnt_result2 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_promos WHERE {$where_promos}");
if ($cnt_result2) $total_promos = (int)mysqli_fetch_assoc($cnt_result2)['cnt'];
$total_promos_pages = $total_promos > 0 ? ceil($total_promos / $per_page) : 0;

$promos_query = "SELECT * FROM gangnamunni_promos WHERE {$where_promos} ORDER BY display_order ASC, id DESC LIMIT {$offset}, {$per_page}";
$promos_result = mysqli_query($connect, $promos_query);
$promos = [];
if ($promos_result) {
    while ($row = mysqli_fetch_assoc($promos_result)) {
        $promos[] = $row;
    }
}

// 공통 헤더 include
$admin_page_title = '광고 관리';
$admin_current = 'ads';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-megaphone"></i> 광고 관리</h5>
    <button class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#adTypeGuideModal" title="광고 유형 안내"><i class="bi bi-question-circle"></i> 유형 안내</button>
    <div class="d-flex gap-2">
        <?php if ($is_super_admin): ?>
        <a href="admin_ad_wizard.php" class="btn btn-sm btn-outline-info" title="광고 등록 마법사"><i class="bi bi-magic"></i> 마법사</a>
        <a href="ai_ad_editor.php" class="btn btn-sm btn-outline-primary" title="AI 광고 에디터">
            <i class="bi bi-robot"></i> AI
        </a>
        <a href="ab_test_dashboard.php" class="btn btn-sm btn-outline-success" title="A/B 테스트 대시보드">
            <i class="bi bi-clipboard-data"></i> A/B
        </a>
        <?php endif; ?>
        <?php if ($active_tab === 'ads'): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdModal">
            <i class="bi bi-plus-lg"></i> 광고 추가
        </button>
        <?php elseif ($active_tab === 'promos'): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPromoModal">
            <i class="bi bi-plus-lg"></i> 프로모션 추가
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 탭 네비게이션 -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab === 'ads' ? 'active' : ''; ?>" href="?tab=ads">
            <i class="bi bi-image"></i> 광고 배너 <span class="badge bg-secondary"><?php echo $total_ads; ?></span>
        </a>
    </li>
    <?php if ($is_super_admin): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab === 'promos' ? 'active' : ''; ?>" href="?tab=promos">
            <i class="bi bi-gift"></i> 프로모션 <span class="badge bg-secondary"><?php echo $total_promos; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab === 'scroll' ? 'active' : ''; ?>" href="?tab=scroll">
            <i class="bi bi-arrow-repeat"></i> 스크롤 설정
        </a>
    </li>
    <?php endif; ?>
</ul>

<?php if ($active_tab === 'ads'): ?>
<!-- ============================================================ -->
<!--                  광고 배너 탭                                  -->
<!-- ============================================================ -->

<!-- 승인 상태 필터 -->
<div class="d-flex gap-2 mb-2 flex-wrap">
    <a href="?tab=ads" class="btn btn-sm <?php echo empty($status_filter) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        전체 <span class="badge bg-light text-dark ms-1"><?php echo $ad_stats['total']; ?></span>
    </a>
    <a href="?tab=ads&status=pending" class="btn btn-sm <?php echo $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
        <i class="bi bi-clock"></i> 대기 <span class="badge bg-warning text-dark ms-1"><?php echo $ad_stats['pending']; ?></span>
    </a>
    <a href="?tab=ads&status=approved" class="btn btn-sm <?php echo $status_filter === 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
        <i class="bi bi-check-circle"></i> 승인 <span class="badge bg-success ms-1"><?php echo $ad_stats['approved']; ?></span>
    </a>
    <a href="?tab=ads&status=rejected" class="btn btn-sm <?php echo $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">
        <i class="bi bi-x-circle"></i> 거절 <span class="badge bg-danger ms-1"><?php echo $ad_stats['rejected']; ?></span>
    </a>
</div>

<!-- 툴바: 검색 + 유형필터 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:500px;">
        <input type="hidden" name="tab" value="ads">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
        <select name="ad_type" class="form-select form-select-sm" style="width:120px;">
            <option value="">전체 유형</option>
            <option value="banner" <?php echo $ad_type_filter === 'banner' ? 'selected' : ''; ?>>배너</option>
            <option value="premium" <?php echo $ad_type_filter === 'premium' ? 'selected' : ''; ?>>프리미엄</option>
        </select>
        <input type="search" name="search" class="form-control form-control-sm" placeholder="제목, 부제목, URL 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10,20,50,100] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total_ads); ?>건</span>
    </div>
</div>

<!-- 일괄 작업 바 -->
<div id="bulkActionsAds" class="alert alert-info py-2 align-items-center gap-2 mb-2" style="display:none;">
    <span><strong id="selectedCountAds">0</strong>개 선택</span>
    <form method="POST" class="d-inline" id="bulkFormAds">
        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="bulk_delete_ads" id="bulkActionTypeAds">
        <span id="bulkIdsContainerAds"></span>
        <button type="submit" class="btn btn-danger btn-sm ms-2" onclick="return confirmBulk('선택한 광고를 삭제하시겠습니까?', 'bulk_delete_ads')">
            <i class="bi bi-trash"></i> 선택 삭제
        </button>
        <button type="submit" class="btn btn-warning btn-sm ms-1" onclick="return confirmBulk('선택한 광고를 비활성화하시겠습니까?', 'bulk_toggle_ads', 0)">
            <i class="bi bi-x-circle"></i> 비활성화
        </button>
    </form>
</div>

<!-- 정렬/보기 모드 -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex gap-2 align-items-center">
        <span class="small text-muted">정렬:</span>
        <div class="btn-group btn-group-sm">
            <a href="?tab=ads&sort=newest&view=<?= $view_mode ?>&search=<?= urlencode($search) ?>&ad_type=<?= $ad_type_filter ?>&per_page=<?= $per_page ?>"
               class="btn btn-outline-secondary <?= $sort_mode==='newest'?'active':'' ?>">
                <i class="bi bi-clock"></i> 최신순
            </a>
            <a href="?tab=ads&sort=name&view=<?= $view_mode ?>&search=<?= urlencode($search) ?>&ad_type=<?= $ad_type_filter ?>&per_page=<?= $per_page ?>"
               class="btn btn-outline-secondary <?= $sort_mode==='name'?'active':'' ?>">
                <i class="bi bi-sort-alpha-down"></i> 이름순
            </a>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="small text-muted">보기:</span>
        <div class="btn-group btn-group-sm">
            <a href="?tab=ads&view=group&sort=<?= $sort_mode ?>&search=<?= urlencode($search) ?>&ad_type=<?= $ad_type_filter ?>&per_page=<?= $per_page ?>"
               class="btn btn-outline-secondary <?= $view_mode==='group'?'active':'' ?>">
                <i class="bi bi-collection"></i> 병원 그룹
            </a>
            <a href="?tab=ads&view=list&sort=<?= $sort_mode ?>&search=<?= urlencode($search) ?>&ad_type=<?= $ad_type_filter ?>&per_page=<?= $per_page ?>"
               class="btn btn-outline-secondary <?= $view_mode==='list'?'active':'' ?>">
                <i class="bi bi-list-ul"></i> 전체 리스트
            </a>
        </div>
        <?php if ($view_mode === 'group'): ?>
        <div class="btn-group btn-group-sm ms-1">
            <button class="btn btn-outline-secondary" onclick="toggleAllAdGroups(true)" title="전체 펼치기"><i class="bi bi-arrows-expand"></i></button>
            <button class="btn btn-outline-secondary" onclick="toggleAllAdGroups(false)" title="전체 접기"><i class="bi bi-arrows-collapse"></i></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 광고 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="adsTable">
        <thead class="table-light">
            <tr>
                <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this, 'ads')"></th>
                <th style="width:50px;">ID</th>
                <th style="width:90px;">이미지</th>
                <th style="min-width:180px;">제목</th>
                <th style="width:130px;">병원(광고주)</th>
                <th style="width:80px;">유형</th>
                <th style="width:90px;">과금 방식</th>
                <th style="width:130px;">광고 기간</th>
                <th style="width:70px;">순서</th>
                <th style="width:70px;">상태</th>
                <th style="width:120px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ads)): ?>
            <tr><td colspan="12" class="empty-state"><i class="bi bi-megaphone"></i>등록된 광고가 없습니다.</td></tr>
            <?php elseif ($view_mode === 'group'): ?>
            <?php
            // 병원별 그룹핑
            $grouped = [];
            foreach ($ads as $ad) {
                $key = $ad['advertiser_id'] ?: 0;
                $grouped[$key][] = $ad;
            }
            foreach ($grouped as $adv_id => $group_ads):
                $grp_name = $group_ads[0]['advertiser_name'] ?? '미지정';
                $grp_count = count($group_ads);
                $grp_active = 0;
                $grp_impressions = 0;
                $grp_clicks = 0;
                foreach ($group_ads as $ga) {
                    if ($ga['exposure_active']) $grp_active++;
                    $grp_impressions += ($ga['impression_count'] ?? 0);
                    $grp_clicks += ($ga['click_count'] ?? 0);
                }
                $grp_ctr = $grp_impressions > 0 ? round($grp_clicks / $grp_impressions * 100, 1) : 0;
                $collapse_id = 'adgrp_' . $adv_id;
            ?>
            <tr class="table-light" style="cursor:pointer;" onclick="toggleAdGroup('<?= $collapse_id ?>')">
                <td><input type="checkbox" class="form-check-input" onclick="event.stopPropagation();toggleGroupCheck(this, '<?= $collapse_id ?>')"></td>
                <td colspan="3" class="fw-bold">
                    <i class="bi bi-chevron-right small me-1 grp-chevron" id="chev_<?= $collapse_id ?>"></i>
                    <i class="bi bi-building me-1 text-primary"></i>
                    <?= htmlspecialchars($grp_name) ?>
                    <span class="badge bg-primary ms-2"><?= $grp_count ?>개</span>
                    <span class="badge bg-success ms-1"><?= $grp_active ?> 활성</span>
                </td>
                <td colspan="2" class="small text-muted">
                    노출 <?= number_format($grp_impressions) ?> / 클릭 <?= number_format($grp_clicks) ?>
                </td>
                <td class="small">CTR <?= $grp_ctr ?>%</td>
                <td colspan="4"></td>
            </tr>
            <?php foreach ($group_ads as $ad): ?>
            <tr class="ad-group-row <?= $collapse_id ?>" style="display:none;">
                <td><input type="checkbox" class="form-check-input row-check-ads" value="<?php echo $ad['id']; ?>" onchange="updateBulkActionsFor('ads')"></td>
                <td class="text-center"><?php echo $ad['id']; ?></td>
                <td>
                    <?php if ($ad['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt=""
                         style="width:80px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--bs-border-color);"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2750%27%3E%3Crect fill=%27%23dee2e6%27 width=%2780%27 height=%2750%27 rx=%274%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 fill=%27%23999%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2711%27%3Eno img%3C/text%3E%3C/svg%3E'">
                    <?php else: ?>
                    <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td style="min-width:180px;max-width:250px;white-space:normal;">
                    <strong style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($ad['title']); ?></strong>
                    <?php if (!empty($ad['subtitle'])): ?>
                    <br><small class="text-body-secondary" style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($ad['subtitle']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($ad['advertiser_name'])): ?>
                    <small class="fw-bold"><?= htmlspecialchars($ad['advertiser_name']) ?></small>
                    <?php else: ?>
                    <small class="text-muted">-</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($ad['ad_type'] ?? 'banner') === 'premium'): ?>
                    <span class="badge bg-warning text-dark">프리미엄</span>
                    <?php else: ?>
                    <span class="badge bg-info">배너</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-info">지갑 일할차감</span>
                </td>
                <td class="small">
                    <?php if (!empty($ad['start_date']) && !empty($ad['end_date'])): ?>
                        <?php echo date('Y-m-d', strtotime($ad['start_date'])); ?><br>
                        ~ <?php echo date('Y-m-d', strtotime($ad['end_date'])); ?>
                        <?php
                        $days_left = ceil((strtotime($ad['end_date']) - time()) / 86400);
                        if ($days_left > 0): ?>
                        <br><span class="badge <?php echo $days_left <= 7 ? 'bg-danger' : 'bg-success'; ?>"><?php echo $days_left; ?>일 남음</span>
                        <?php else: ?>
                        <br><span class="badge bg-secondary">만료됨</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo $ad['display_order']; ?></td>
                <td>
                    <?php if ($ad['exposure_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $st = $ad['status'] ?? 'pending';
                    if ($st === 'approved'): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> 승인</span>
                    <?php elseif ($st === 'rejected'): ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> 거절</span>
                    <?php if (!empty($ad['reject_reason'])): ?>
                    <br><small class="text-danger"><?php echo htmlspecialchars($ad['reject_reason']); ?></small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> 대기</span>
                    <?php endif; ?>
                    <?php if (!empty($ad['reviewed_by'])): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($ad['reviewed_by']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_super_admin && ($ad['status'] ?? 'pending') === 'pending'): ?>
                    <div class="d-flex gap-1 mb-1">
                        <form method="POST" action="admin_ads.php" class="d-inline"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="approve_ad"><input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>"><input type="hidden" name="current_status" value="<?php echo htmlspecialchars($status_filter); ?>"><button type="submit" class="btn btn-success btn-sm" title="승인" onclick="return confirm('승인하시겠습니까?')"><i class="bi bi-check-lg"></i></button></form>
                        <button type="button" class="btn btn-danger btn-sm" title="거절" onclick="openRejectModal(<?php echo $ad['id']; ?>, '<?php echo htmlspecialchars($status_filter); ?>')"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php endif; ?>
                    <div class="btn-group btn-group-sm">
                        <a href="partner_wallet.php" class="btn btn-outline-success" title="광고 지갑">
                            <i class="bi bi-wallet2"></i>
                        </a>
                        <?php if ($is_super_admin): ?><a href="admin_ad_slots.php" class="btn btn-outline-secondary" title="광고 슬롯 관리">
                            <i class="bi bi-grid-3x3-gap"></i>
                        </a><?php endif; ?>
                        <button class="btn btn-outline-primary" onclick='editAd(<?php echo json_encode($ad, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="수정">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="delete_ad">
                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                            <button class="btn btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
                        <?php endforeach; ?>
            <?php endforeach; ?>
            <?php else: ?>
            <?php foreach ($ads as $ad): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input row-check-ads" value="<?php echo $ad['id']; ?>" onchange="updateBulkActionsFor('ads')"></td>
                <td class="text-center"><?php echo $ad['id']; ?></td>
                <td>
                    <?php if ($ad['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt=""
                         style="width:80px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--bs-border-color);"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2750%27%3E%3Crect fill=%27%23dee2e6%27 width=%2780%27 height=%2750%27 rx=%274%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 fill=%27%23999%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2711%27%3Eno img%3C/text%3E%3C/svg%3E'">
                    <?php else: ?>
                    <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td style="min-width:180px;max-width:250px;white-space:normal;">
                    <strong style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($ad['title']); ?></strong>
                    <?php if (!empty($ad['subtitle'])): ?>
                    <br><small class="text-body-secondary" style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($ad['subtitle']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($ad['advertiser_name'])): ?>
                    <small class="fw-bold"><?= htmlspecialchars($ad['advertiser_name']) ?></small>
                    <?php else: ?>
                    <small class="text-muted">-</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($ad['ad_type'] ?? 'banner') === 'premium'): ?>
                    <span class="badge bg-warning text-dark">프리미엄</span>
                    <?php else: ?>
                    <span class="badge bg-info">배너</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-info">지갑 일할차감</span>
                </td>
                <td class="small">
                    <?php if (!empty($ad['start_date']) && !empty($ad['end_date'])): ?>
                        <?php echo date('Y-m-d', strtotime($ad['start_date'])); ?><br>
                        ~ <?php echo date('Y-m-d', strtotime($ad['end_date'])); ?>
                        <?php
                        $days_left = ceil((strtotime($ad['end_date']) - time()) / 86400);
                        if ($days_left > 0): ?>
                        <br><span class="badge <?php echo $days_left <= 7 ? 'bg-danger' : 'bg-success'; ?>"><?php echo $days_left; ?>일 남음</span>
                        <?php else: ?>
                        <br><span class="badge bg-secondary">만료됨</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo $ad['display_order']; ?></td>
                <td>
                    <?php if ($ad['exposure_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $st = $ad['status'] ?? 'pending';
                    if ($st === 'approved'): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> 승인</span>
                    <?php elseif ($st === 'rejected'): ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> 거절</span>
                    <?php if (!empty($ad['reject_reason'])): ?>
                    <br><small class="text-danger"><?php echo htmlspecialchars($ad['reject_reason']); ?></small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> 대기</span>
                    <?php endif; ?>
                    <?php if (!empty($ad['reviewed_by'])): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($ad['reviewed_by']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_super_admin && ($ad['status'] ?? 'pending') === 'pending'): ?>
                    <div class="d-flex gap-1 mb-1">
                        <form method="POST" action="admin_ads.php" class="d-inline"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="approve_ad"><input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>"><input type="hidden" name="current_status" value="<?php echo htmlspecialchars($status_filter); ?>"><button type="submit" class="btn btn-success btn-sm" title="승인" onclick="return confirm('승인하시겠습니까?')"><i class="bi bi-check-lg"></i></button></form>
                        <button type="button" class="btn btn-danger btn-sm" title="거절" onclick="openRejectModal(<?php echo $ad['id']; ?>, '<?php echo htmlspecialchars($status_filter); ?>')"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php endif; ?>
                    <div class="btn-group btn-group-sm">
                        <a href="partner_wallet.php" class="btn btn-outline-success" title="광고 지갑">
                            <i class="bi bi-wallet2"></i>
                        </a>
                        <?php if ($is_super_admin): ?><a href="admin_ad_slots.php" class="btn btn-outline-secondary" title="광고 슬롯 관리">
                            <i class="bi bi-grid-3x3-gap"></i>
                        </a><?php endif; ?>
                        <button class="btn btn-outline-primary" onclick='editAd(<?php echo json_encode($ad, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="수정">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="delete_ad">
                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                            <button class="btn btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
                        <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 광고 페이지네이션 -->
<?php if ($total_ads_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?tab=ads&per_page=<?php echo $per_page; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&ad_type=<?php echo urlencode($ad_type_filter); ?>">&laquo;</a></li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_ads_pages, $page + 4);
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?tab=ads&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&ad_type=<?php echo urlencode($ad_type_filter); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_ads_pages): ?>
        <li class="page-item"><a class="page-link" href="?tab=ads&per_page=<?php echo $per_page; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&ad_type=<?php echo urlencode($ad_type_filter); ?>">&raquo;</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php elseif ($active_tab === 'promos'): ?>
<!-- ============================================================ -->
<!--                  프로모션 배너 탭                              -->
<!-- ============================================================ -->

<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="tab" value="promos">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="제목, 부제목 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10,20,50,100] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total_promos); ?>건</span>
    </div>
</div>

<!-- 일괄 작업 바 -->
<div id="bulkActionsPromos" class="alert alert-info py-2 align-items-center gap-2 mb-2" style="display:none;">
    <span><strong id="selectedCountPromos">0</strong>개 선택</span>
    <form method="POST" class="d-inline" id="bulkFormPromos">
        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="bulk_delete_promos">
        <span id="bulkIdsContainerPromos"></span>
        <button type="submit" class="btn btn-danger btn-sm ms-2" onclick="return confirmBulkPromos('선택한 프로모션을 삭제하시겠습니까?')">
            <i class="bi bi-trash"></i> 선택 삭제
        </button>
    </form>
</div>

<!-- 프로모션 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="promosTable">
        <thead class="table-light">
            <tr>
                <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this, 'promos')"></th>
                <th style="width:50px;">ID</th>
                <th style="width:100px;">미리보기</th>
                <th style="min-width:180px;">제목</th>
                <th>부제목</th>
                <th style="width:60px;">아이콘</th>
                <th style="width:60px;">순서</th>
                <th style="width:70px;">상태</th>
                <th style="width:120px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($promos)): ?>
            <tr><td colspan="9" class="empty-state"><i class="bi bi-gift"></i>등록된 프로모션 배너가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($promos as $promo): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input row-check-promos" value="<?php echo $promo['id']; ?>" onchange="updateBulkActionsFor('promos')"></td>
                <td class="text-center"><?php echo $promo['id']; ?></td>
                <td>
                    <div style="background:<?php echo htmlspecialchars($promo['background_gradient']); ?>;padding:8px;border-radius:6px;color:white;font-size:10px;min-width:80px;text-align:center;">
                        <?php echo $promo['icon']; ?> 미리보기
                    </div>
                </td>
                <td><strong><?php echo $promo['title']; ?></strong></td>
                <td class="small"><?php echo htmlspecialchars($promo['subtitle'] ?? '-'); ?></td>
                <td class="text-center" style="font-size:20px;"><?php echo $promo['icon']; ?></td>
                <td class="text-center"><?php echo $promo['display_order']; ?></td>
                <td>
                    <?php if ($promo['is_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="toggle_promo">
                            <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                            <button class="btn btn-outline-secondary" title="<?php echo $promo['is_active'] ? '비활성화' : '활성화'; ?>">
                                <i class="bi <?php echo $promo['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                            </button>
                        </form>
                        <button class="btn btn-outline-primary" onclick='editPromo(<?php echo json_encode($promo, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="수정">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="delete_promo">
                            <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                            <button class="btn btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 프로모션 페이지네이션 -->
<?php if ($total_promos_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?tab=promos&per_page=<?php echo $per_page; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a></li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_promos_pages, $page + 4);
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?tab=promos&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_promos_pages): ?>
        <li class="page-item"><a class="page-link" href="?tab=promos&per_page=<?php echo $per_page; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php elseif ($active_tab === 'scroll'): ?>
<!-- ============================================================ -->
<!--                  스크롤 설정 탭                                -->
<!-- ============================================================ -->

<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-arrow-repeat"></i> 자동 스크롤 설정</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">광고 배너 스크롤</h6>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="ad_auto_scroll_enabled" value="1" class="form-check-input" id="adScrollEnable"
                               <?php echo $ad_auto_scroll_enabled == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="adScrollEnable">자동 스크롤 활성화</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">스크롤 간격 (밀리초)</label>
                        <input type="number" name="ad_auto_scroll_interval" class="form-control form-control-sm" style="max-width:200px;"
                               value="<?php echo htmlspecialchars($ad_auto_scroll_interval); ?>" min="1000" step="1000" placeholder="10000 = 10초">
                        <div class="form-text">1000 = 1초, 10000 = 10초 (권장: 5000~15000)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">프로모션 배너 스크롤</h6>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="promo_auto_scroll_enabled" value="1" class="form-check-input" id="promoScrollEnable"
                               <?php echo $promo_auto_scroll_enabled == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="promoScrollEnable">자동 스크롤 활성화</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">스크롤 간격 (밀리초)</label>
                        <input type="number" name="promo_auto_scroll_interval" class="form-control form-control-sm" style="max-width:200px;"
                               value="<?php echo htmlspecialchars($promo_auto_scroll_interval); ?>" min="1000" step="1000" placeholder="10000 = 10초">
                        <div class="form-text">1000 = 1초, 10000 = 10초 (권장: 5000~15000)</div>
                    </div>
                </div>
            </div>
            <button type="submit" name="update_scroll_settings" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg"></i> 설정 저장
            </button>
        </form>
    </div>
</div>

<?php endif; ?>

<!-- ============================================================ -->
<!--                  모달: 광고 추가                               -->
<!-- ============================================================ -->
<div class="modal fade" id="addAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle"></i> 새 광고 추가</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 병원 검색 -->
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-primary bg-opacity-10 py-2">
                            <i class="bi bi-search"></i> <strong>병원(광고주) 검색</strong>
                        </div>
                        <div class="card-body py-2">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="adHospitalSearch" class="form-control" placeholder="병원명으로 검색..." autocomplete="off">
                                <button type="button" class="btn btn-outline-primary" onclick="searchAdHospitals()"><i class="bi bi-search"></i></button>
                            </div>
                            <div id="adHospitalResults" style="max-height:180px;overflow-y:auto;"></div>
                        </div>
                    </div>
                    <input type="hidden" name="advertiser_id" id="add_advertiser_id" value="0">
                    <div id="selectedHospitalBadge" class="mb-3" style="display:none;">
                        <span class="badge bg-primary fs-6 px-3 py-2">
                            <i class="bi bi-building me-1"></i><span id="selectedHospitalName"></span>
                            <button type="button" class="btn-close btn-close-white ms-2" style="font-size:10px;" onclick="clearAdHospital()"></button>
                        </span>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">광고 제목 <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-sm" required placeholder="예: 지방흡입에 대한 검색결과">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">부제목</label>
                            <input type="text" name="subtitle" class="form-control form-control-sm" placeholder="예: 이벤트 가격 1,520건">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <!-- [관리자 #5 - 2026-05-28] 이미지 URL 등록 방법 매뉴얼 강화 -->
                            <label class="form-label">이미지 <span class="text-danger">*</span> <small class="text-muted">(URL 직접 입력 OR 파일 업로드 — 둘 중 하나)</small></label>
                            <input type="text" name="image_url" class="form-control form-control-sm mb-1" placeholder="① 다른 서버 이미지 URL 직접 입력 (예: https://cdn.example.com/banner.jpg)" id="add_image_url">
                            <input type="file" name="image_file" class="form-control form-control-sm" accept="image/*" onchange="previewAdImage(this, 'addImgPreview'); if(this.files[0]) document.getElementById('add_image_url').removeAttribute('required');">
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle"></i> <strong>이용 방법</strong>:<br>
                                <strong>방법 1</strong> — 외부 서버에 이미지를 업로드한 후 URL을 위 입력란에 붙여넣기<br>
                                <strong>방법 2</strong> — '파일 선택' 버튼으로 PC에서 직접 업로드 (자동으로 서버 저장 + URL 채워짐)<br>
                                <strong>권장 사이즈</strong> : 배너 1200x300px / 프리미엄 600x600px / VIP 1200x400px (최대 5MB)
                            </div>
                            <img id="addImgPreview" src="" class="mt-1 rounded border" style="max-width:200px;max-height:100px;display:none;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <!-- [관리자 #6 - 2026-05-28] 랜딩 URL 등록 방법 매뉴얼 강화 -->
                            <label class="form-label">랜딩(링크) URL <span class="text-danger">*</span> <small class="text-muted">(광고 클릭 시 이동할 페이지)</small></label>
                            <input type="text" name="link_url" class="form-control form-control-sm" required placeholder="예: search.php?q=지방흡입 또는 https://example.com/event">
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle"></i> <strong>URL 작성 예시</strong>:<br>
                                ▸ 내부 페이지 : <code>detail.php?id=8118</code> (병원 상세) / <code>search.php?q=지방흡입</code> (검색결과) / <code>event_detail.php?id=1</code> (이벤트 상세)<br>
                                ▸ 외부 URL : <code>https://example.com/landing</code> (새 탭 자동 열림)<br>
                                ▸ 이벤트 페이지 게시판 없을 시 → 관리자 콘텐츠 관리 ▸ 이벤트 관리에서 사전 등록 후 ID 확인
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">광고 유형 <span class="text-danger">*</span></label>
                            <select name="ad_type" class="form-select form-select-sm" id="add_ad_type">
                                <option value="banner">배너</option>
                                <option value="premium">프리미엄</option>
                                <option value="vip">VIP</option>
                            </select>
                            <div class="form-text text-muted" id="adTypeHelp"><i class="bi bi-info-circle"></i> <b>배너</b>: 검색결과 상단 가로형 광고 | <b>프리미엄</b>: 메인/상세 페이지 카드형 광고 | <b>VIP</b>: 전면/팝업 프리미엄 광고</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">광고 단가 <span class="text-danger">*</span></label>
                            <select name="pricing_id" class="form-select form-select-sm" id="add_pricing_id" onchange="onPricingChange(this)">
                                <option value="0">-- 단가 선택 --</option>
                                <?php
                                $pr_list = mysqli_query($connect, "SELECT * FROM gangnamunni_ad_pricing WHERE is_active=1 ORDER BY display_order");
                                while ($pr = mysqli_fetch_assoc($pr_list)):
                                    $type_label = ["banner"=>"배너","premium"=>"프리미엄","vip"=>"VIP"][$pr["ad_type"]] ?? $pr["ad_type"];
                                ?>
                                <option value="<?= $pr['id'] ?>"
                                        data-type="<?= $pr['ad_type'] ?>"
                                        data-days="<?= $pr['duration_days'] ?>"
                                        data-price="<?= $pr['total_price'] ?>">
                                    <?= $pr['code'] ?> - <?= htmlspecialchars($pr['display_name'] ?: $pr['name']) ?> (<?= number_format($pr['total_price']) ?>원/<?= $pr['duration_days'] ?>일)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text" id="pricingInfo"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="0" min="0">
                        </div>
                    </div>
                    <input type="hidden" name="proceed_to_payment" id="proceed_to_payment" value="0">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_ad" class="btn btn-sm btn-primary" onclick="document.getElementById('proceed_to_payment').value='0';">광고만 추가</button>
                    <button type="submit" name="add_ad" class="btn btn-sm btn-success" onclick="document.getElementById('proceed_to_payment').value='1';">추가 후 결제</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!--                  모달: 광고 수정                               -->
<!-- ============================================================ -->
<div class="modal fade" id="editAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editAdForm" enctype="multipart/form-data">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_ad">
                <input type="hidden" name="id" id="editAd_id">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 광고 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">광고 제목</label>
                            <input type="text" name="title" id="editAd_title" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">부제목</label>
                            <input type="text" name="subtitle" id="editAd_subtitle" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">이미지</label>
                            <input type="text" name="image_url" id="editAd_image_url" class="form-control form-control-sm mb-1">
                            <input type="file" name="edit_image_file" class="form-control form-control-sm" accept="image/*" onchange="previewAdImage(this, 'editImgPreview')">
                            <div class="form-text text-muted"><i class="bi bi-info-circle"></i> 새 파일 업로드시 기존 이미지 대체 (최대 5MB)</div>
                            <img id="editImgPreview" src="" class="mt-1 rounded border" style="max-width:200px;max-height:100px;display:none;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">링크 URL</label>
                            <input type="text" name="link_url" id="editAd_link_url" class="form-control form-control-sm" required>
                            <div class="form-text text-muted"><i class="bi bi-info-circle"></i> 내부: <code>search.php?q=키워드</code> | 외부: <code>https://example.com</code></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">광고 유형</label>
                            <select name="ad_type" id="editAd_ad_type" class="form-select form-select-sm">
                                <option value="banner">배너 광고</option>
                                <option value="premium">프리미엄 광고</option>
                            </select>
                            <div class="form-text text-muted"><i class="bi bi-info-circle"></i> 배너: 가로형 | 프리미엄: 카드형</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" name="display_order" id="editAd_display_order" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">&nbsp;</label>
                            <div class="form-text">노출 상태는 광고 지갑 및 슬롯 배정에서 관리됩니다.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-sm btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!--                  모달: 프로모션 추가                            -->
<!-- ============================================================ -->
<div class="modal fade" id="addPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle"></i> 새 프로모션 배너 추가</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">제목 (HTML 허용) <span class="text-danger">*</span></label>
                            <input type="text" name="promo_title" class="form-control form-control-sm" required placeholder="예: 서울미녀 회원가입하고<br>5,000포인트 획득!">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">부제목</label>
                            <input type="text" name="promo_subtitle" class="form-control form-control-sm" placeholder="예: 앱 다운받고 포인트 받기">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">배경 그라데이션 (CSS)</label>
                            <input type="text" name="promo_gradient" class="form-control form-control-sm"
                                   value="linear-gradient(135deg, #FF6B35 0%, #FF8C42 50%, #FFA45B 100%)" placeholder="linear-gradient(...)">
                            <div class="form-text">예시: linear-gradient(135deg, #667eea 0%, #764ba2 100%)</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">아이콘 (이모지)</label>
                            <input type="text" name="promo_icon" class="form-control form-control-sm" value="" placeholder="아이콘">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" name="promo_display_order" class="form-control form-control-sm" value="0" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">링크 URL</label>
                        <input type="text" name="promo_link_url" class="form-control form-control-sm" placeholder="#" value="#">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_promo" class="btn btn-sm btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!--                  모달: 프로모션 수정                            -->
<!-- ============================================================ -->
<div class="modal fade" id="editPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editPromoForm">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_promo">
                <input type="hidden" name="id" id="editPromo_id">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 프로모션 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">제목 (HTML 허용)</label>
                            <input type="text" name="promo_title" id="editPromo_title" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">부제목</label>
                            <input type="text" name="promo_subtitle" id="editPromo_subtitle" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">배경 그라데이션 (CSS)</label>
                            <input type="text" name="promo_gradient" id="editPromo_gradient" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">아이콘</label>
                            <input type="text" name="promo_icon" id="editPromo_icon" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" name="promo_display_order" id="editPromo_display_order" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">링크 URL</label>
                            <input type="text" name="promo_link_url" id="editPromo_link_url" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">&nbsp;</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_active" id="editPromo_is_active" value="1" class="form-check-input">
                                <label class="form-check-label" for="editPromo_is_active">활성화</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">미리보기</label>
                        <div id="editPromo_preview" style="padding:12px;border-radius:8px;color:white;font-size:13px;text-align:center;">
                            미리보기 영역
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-sm btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// 페이지별 추가 JS
$admin_extra_js = '
// 결제 상품 옵션 업데이트
function updatePaymentOptions() {
    var adType = document.getElementById("add_ad_type").value;
    var pp = document.getElementById("add_payment_product");
    if (adType === "banner") {
        pp.innerHTML = "<option value=\"banner_1month\">배너 1개월 - 300,000원</option><option value=\"banner_3months\">배너 3개월 - 800,000원 (11% 할인)</option>";
    } else {
        pp.innerHTML = "<option value=\"premium_1month\">프리미엄 1개월 - 500,000원</option><option value=\"premium_3months\">프리미엄 3개월 - 1,350,000원 (10% 할인)</option>";
    }
}

// 광고 수정 모달
function editAd(ad) {
    document.getElementById("editAd_id").value = ad.id;
    document.getElementById("editAd_title").value = ad.title || "";
    document.getElementById("editAd_subtitle").value = ad.subtitle || "";
    document.getElementById("editAd_image_url").value = ad.image_url || "";
    document.getElementById("editAd_link_url").value = ad.link_url || "";
    document.getElementById("editAd_display_order").value = ad.display_order || 0;
    document.getElementById("editAd_ad_type").value = ad.ad_type || "banner";
    new bootstrap.Modal(document.getElementById("editAdModal")).show();
}

// 프로모션 수정 모달
function editPromo(promo) {
    document.getElementById("editPromo_id").value = promo.id;
    document.getElementById("editPromo_title").value = promo.title || "";
    document.getElementById("editPromo_subtitle").value = promo.subtitle || "";
    document.getElementById("editPromo_gradient").value = promo.background_gradient || "";
    document.getElementById("editPromo_icon").value = promo.icon || "";
    document.getElementById("editPromo_link_url").value = promo.link_url || "";
    document.getElementById("editPromo_display_order").value = promo.display_order || 0;
    document.getElementById("editPromo_is_active").checked = promo.is_active == 1;
    // 미리보기 업데이트
    var preview = document.getElementById("editPromo_preview");
    preview.style.background = promo.background_gradient || "#ccc";
    preview.innerHTML = (promo.icon || "") + " " + (promo.title || "미리보기");
    new bootstrap.Modal(document.getElementById("editPromoModal")).show();
}

// 전체 선택 (탭별 구분)
function toggleSelectAll(source, type) {
    var checkboxes = document.querySelectorAll(".row-check-" + type);
    checkboxes.forEach(function(cb) { cb.checked = source.checked; });
    updateBulkActionsFor(type);
}

// 일괄 작업 바 업데이트
function updateBulkActionsFor(type) {
    var checked = document.querySelectorAll(".row-check-" + type + ":checked").length;
    var bar = document.getElementById("bulkActions" + type.charAt(0).toUpperCase() + type.slice(1));
    var countEl = document.getElementById("selectedCount" + type.charAt(0).toUpperCase() + type.slice(1));
    if (bar) {
        bar.style.display = checked > 0 ? "flex" : "none";
        if (countEl) countEl.textContent = checked;
    }
}

// 광고 일괄 삭제/토글 확인
function confirmBulk(msg, action, toggleTo) {
    if (!confirm(msg)) return false;
    var form = document.getElementById("bulkFormAds");
    var actionInput = document.getElementById("bulkActionTypeAds");
    actionInput.value = action;
    // toggleTo 파라미터 처리
    var existing = form.querySelector("input[name=toggle_to]");
    if (existing) existing.remove();
    if (typeof toggleTo !== "undefined") {
        var inp = document.createElement("input");
        inp.type = "hidden"; inp.name = "toggle_to"; inp.value = toggleTo;
        form.appendChild(inp);
    }
    // 선택된 ID 추가
    var container = document.getElementById("bulkIdsContainerAds");
    container.innerHTML = "";
    document.querySelectorAll(".row-check-ads:checked").forEach(function(cb) {
        var inp = document.createElement("input");
        inp.type = "hidden"; inp.name = "ids[]"; inp.value = cb.value;
        container.appendChild(inp);
    });
    return true;
}

// 프로모션 일괄 삭제 확인
function confirmBulkPromos(msg) {
    if (!confirm(msg)) return false;
    var container = document.getElementById("bulkIdsContainerPromos");
    container.innerHTML = "";
    document.querySelectorAll(".row-check-promos:checked").forEach(function(cb) {
        var inp = document.createElement("input");
        inp.type = "hidden"; inp.name = "ids[]"; inp.value = cb.value;
        container.appendChild(inp);
    });
    return true;
}
';


?>
<script>
// 광고 그룹 확장/축소
function toggleAdGroup(id) {
    var rows = document.querySelectorAll('.' + id);
    var chevron = document.getElementById('chev_' + id);
    var visible = rows.length > 0 && rows[0].style.display !== 'none';
    rows.forEach(function(r) { r.style.display = visible ? 'none' : ''; });
    if (chevron) {
        chevron.classList.toggle('bi-chevron-right', visible);
        chevron.classList.toggle('bi-chevron-down', !visible);
    }
}
function toggleAllAdGroups(expand) {
    document.querySelectorAll('.ad-group-row').forEach(function(r) { r.style.display = expand ? '' : 'none'; });
    document.querySelectorAll('.grp-chevron').forEach(function(c) {
        c.classList.toggle('bi-chevron-right', !expand);
        c.classList.toggle('bi-chevron-down', expand);
    });
}
function toggleGroupCheck(cb, grpClass) {
    document.querySelectorAll('.' + grpClass + ' .row-check-ads').forEach(function(c) {
        c.checked = cb.checked;
    });
    updateBulkActionsFor('ads');
}
</script>
<?php

?>
<script>
// 병원(광고주) 검색 데이터
var adAdvertisers = <?php
    $adv_json = [];
    $adv_scope_sql = $ad_hospital_ids === null ? '1=1' : "a.hospital_id IN ({$ad_hospital_in})";
    $adv_r = mysqli_query($connect, "SELECT a.id, a.hospital_id, a.hospital_name, a.address, a.contact_phone, a.status FROM gangnamunni_advertisers a WHERE a.status='active' AND {$adv_scope_sql} ORDER BY a.hospital_name");
    if ($adv_r) { while ($ar = mysqli_fetch_assoc($adv_r)) { $adv_json[] = $ar; } }
    echo json_encode($adv_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

// 광고주가 없으면 병원 테이블에서도 검색
var adHospitals = <?php
    $h_json = [];
    $hospital_scope_sql = $ad_hospital_ids === null ? '1=1' : "id IN ({$ad_hospital_in})";
    $h_r = mysqli_query($connect, "SELECT id, name, address, phone FROM gangnamunni_hospitals WHERE {$hospital_scope_sql} ORDER BY name");
    if ($h_r) { while ($hr = mysqli_fetch_assoc($h_r)) { $h_json[] = $hr; } }
    echo json_encode($h_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

function escapeAdHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(ch) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
    });
}

function searchAdHospitals() {
    var q = document.getElementById("adHospitalSearch").value.trim().toLowerCase();
    var box = document.getElementById("adHospitalResults");
    if (!q) { box.innerHTML = "<div class=\"text-muted small py-2 text-center\">병원명을 입력하세요</div>"; return; }

    // 광고주 검색
    var matches = adAdvertisers.filter(function(a) {
        return a.hospital_name.toLowerCase().indexOf(q) !== -1;
    });

    var html = "";
    if (matches.length > 0) {
        html += "<div class=\"small text-muted px-2 py-1 bg-light\"><i class=\"bi bi-building\"></i> 등록된 광고주</div>";
        html += "<div class=\"list-group list-group-flush\">";
        matches.slice(0,10).forEach(function(a) {
            html += "<a href=\"#\" class=\"list-group-item list-group-item-action py-2 px-3\" onclick=\"selectAdAdvertiser(" + a.id + ",decodeURIComponent('" + encodeURIComponent(a.hospital_name).replace(/'/g,'%27') + "'));return false;\">"
                + "<strong class=\"small\">" + escapeAdHtml(a.hospital_name) + "</strong>"
                + (a.address ? " <span class=\"text-muted\" style=\"font-size:11px;\">" + escapeAdHtml(a.address) + "</span>" : "")
                + " <span class=\"badge bg-success float-end\">선택</span></a>";
        });
        html += "</div>";
    }

    // 미등록 병원도 표시
    var hosp_matches = adHospitals.filter(function(h) {
        return h.name.toLowerCase().indexOf(q) !== -1 && !matches.find(function(a){ return a.hospital_name === h.name; });
    });
    if (hosp_matches.length > 0) {
        html += "<div class=\"small text-muted px-2 py-1 bg-light mt-1\"><i class=\"bi bi-hospital\"></i> 미등록 병원 (광고주 자동 등록)</div>";
        html += "<div class=\"list-group list-group-flush\">";
        hosp_matches.slice(0,10).forEach(function(h) {
            html += "<a href=\"#\" class=\"list-group-item list-group-item-action py-2 px-3\" onclick=\"selectAdHospitalNew(" + h.id + ",decodeURIComponent('" + encodeURIComponent(h.name).replace(/'/g,'%27') + "'));return false;\">"
                + "<strong class=\"small\">" + escapeAdHtml(h.name) + "</strong>"
                + " <span class=\"badge bg-outline-primary border text-primary float-end\">선택+등록</span></a>";
        });
        html += "</div>";
    }

    if (!html) html = "<div class=\"text-muted small py-2 text-center\">검색 결과 없음</div>";
    box.innerHTML = html;
}

function selectAdAdvertiser(id, name) {
    document.getElementById("add_advertiser_id").value = id;
    document.getElementById("selectedHospitalName").textContent = name;
    document.getElementById("selectedHospitalBadge").style.display = "";
    document.getElementById("adHospitalResults").innerHTML = "";
    document.getElementById("adHospitalSearch").value = "";
}

function selectAdHospitalNew(hospitalId, name) {
    // 병원을 광고주로 자동 등록 (AJAX)
    var fd = new FormData();
    fd.append("admin_csrf", <?php echo json_encode($admin_ads_csrf); ?>);
    fd.append("action", "auto_register_advertiser");
    fd.append("hospital_id", hospitalId);
    fetch("admin_ads.php", {method:"POST", body:fd}).then(function(r){return r.json();}).then(function(data){
        if (data.id) {
            selectAdAdvertiser(data.id, name);
        } else {
            alert("광고주 등록 실패");
        }
    }).catch(function(){ selectAdAdvertiser(0, name); });
}

function clearAdHospital() {
    document.getElementById("add_advertiser_id").value = "0";
    document.getElementById("selectedHospitalBadge").style.display = "none";
    document.getElementById("adHospitalSearch").focus();
}

// 단가 선택 시 광고 유형 자동 변경
function onPricingChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    var pType = opt.dataset.type;
    var pDays = opt.dataset.days;
    var pPrice = opt.dataset.price;
    if (pType) {
        document.getElementById("add_ad_type").value = pType;
    }
    var info = document.getElementById("pricingInfo");
    if (pPrice && parseInt(pPrice) > 0) {
        info.innerHTML = "<span class=\"text-primary fw-bold\">" + parseInt(pPrice).toLocaleString() + "원</span> / " + pDays + "일";
    } else {
        info.innerHTML = "";
    }
}

// 검색 디바운스
var adSearchTimer;
document.getElementById("adHospitalSearch").addEventListener("input", function(){
    clearTimeout(adSearchTimer);
    adSearchTimer = setTimeout(searchAdHospitals, 300);
});
document.getElementById("adHospitalSearch").addEventListener("keydown", function(e){
    if(e.key==="Enter"){e.preventDefault();searchAdHospitals();}
});

// 모달 열릴 때 초기화
document.getElementById("addAdModal").addEventListener("show.bs.modal", function(){
    document.getElementById("adHospitalSearch").value = "";
    document.getElementById("adHospitalResults").innerHTML = "";
    document.getElementById("add_advertiser_id").value = "0";
    document.getElementById("selectedHospitalBadge").style.display = "none";
    document.getElementById("add_pricing_id").value = "0";
    document.getElementById("pricingInfo").innerHTML = "";
});

// 이미지 미리보기 (#5)
function previewAdImage(input, previewId) {
    var preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
// 거절 모달
function openRejectModal(adId, currentStatus) {
    document.getElementById('rejectAdId').value = adId;
    document.getElementById('rejectCurrentStatus').value = currentStatus || '';
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectAdModal')).show();
}
</script>

<!-- 광고 거절 사유 모달 -->
<div class="modal fade" id="rejectAdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="admin_ads.php">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_ads_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="reject_ad">
                <input type="hidden" name="ad_id" id="rejectAdId">
                <input type="hidden" name="current_status" id="rejectCurrentStatus" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> 광고 거절</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">거절 사유 <span class="text-danger">*</span></label>
                        <textarea name="reject_reason" id="rejectReason" class="form-control" rows="3" required placeholder="거절 사유를 입력하세요..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> 거절 확인</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 광고 유형 안내 모달 -->
<div class="modal fade" id="adTypeGuideModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-info-circle"></i> 광고 유형별 안내</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr><th>유형</th><th>노출 위치</th><th>이미지 크기</th><th>특징</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="badge bg-secondary">배너</span></td>
                <td>페이지 상/하단, 인피드 (카드 4개마다)</td>
                <td>1200 × 300 px</td>
                <td>기본 광고. 페이지별 ON/OFF 가능<br><small class="text-muted">설정: 광고 표시 설정에서 페이지별 제어</small></td>
              </tr>
              <tr>
                <td><span class="badge bg-warning text-dark">프리미엄</span></td>
                <td>검색 결과 최상단, 병원 목록 상단</td>
                <td>600 × 600 px</td>
                <td>우선 노출 + 하이라이트 테두리<br><small class="text-muted">병원 목록에서 일반 카드보다 상단 배치</small></td>
              </tr>
              <tr>
                <td><span class="badge bg-danger">VIP</span></td>
                <td>메인 페이지 캐러셀 + 전 페이지 상단 고정</td>
                <td>1200 × 400 px</td>
                <td>최대 노출. 메인 슬라이더 + sticky 배너<br><small class="text-muted">브랜딩 효과 극대화, 최고 단가</small></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3 p-3 bg-light rounded">
          <h6><i class="bi bi-gear"></i> 관련 설정 페이지</h6>
          <ul class="mb-0">
            <li><a href="admin_ad_settings.php">광고 표시 설정</a> — 페이지별 ON/OFF, 뷰 모드별 설정</li>
            <li><a href="admin_ad_pricing.php">광고 단가 관리</a> — 유형별 가격, 기간, 할인율 설정</li>
            <li><a href="admin_ad_stats.php">광고 통계</a> — 노출수, 클릭수, CTR 분석</li>
            <li><a href="admin_advertisers.php">광고주 관리</a> — 광고주 등록/수정</li>
          </ul>
        </div>

        <div class="mt-3 p-3 border rounded">
          <h6><i class="bi bi-image"></i> 이미지 등록 방법</h6>
          <ol class="mb-0">
            <li><strong>직접 업로드</strong>: 파일 선택 버튼으로 이미지 업로드 (JPG/PNG/WebP, 최대 5MB)</li>
            <li><strong>URL 입력</strong>: 외부 이미지 URL 직접 입력 (https:// 필수)</li>
          </ol>
          <div class="mt-2 text-muted small">
            <i class="bi bi-info-circle"></i> 권장: 고화질 이미지 사용, WebP 형식 권장 (용량 절약)
          </div>
        </div>

        <div class="mt-3 p-3 border rounded">
          <h6><i class="bi bi-link-45deg"></i> 랜딩 URL 설정</h6>
          <p class="mb-2">광고 클릭 시 이동할 페이지를 설정합니다:</p>
          <div class="row">
            <div class="col-md-6">
              <strong>내부 페이지 예시:</strong>
              <ul class="small mb-0">
                <li><code>hospitals.php</code> — 병원 목록</li>
                <li><code>hospital_detail.php?id=8093</code> — 병원 상세</li>
                <li><code>booking.php</code> — 예약 페이지</li>
                <li><code>events.php</code> — 이벤트 목록</li>
                <li><code>search.php?q=성형외과</code> — 검색 결과</li>
              </ul>
            </div>
            <div class="col-md-6">
              <strong>외부 URL:</strong>
              <ul class="small mb-0">
                <li><code>https://example.com/landing</code></li>
                <li>반드시 <code>https://</code> 포함</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
      </div>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

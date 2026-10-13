<?php
/**
 * 관리자 인증 시스템
 * DB 기반 로그인/권한 관리
 *
 * gangnamunni_admins 테이블 사용
 * @created 2026-02-11
 */

// 역할별 기본 권한 — 6역할 (플랫폼 4 + 병원 2)
// 병원 부관리자(hospital_sub_admin)·병원 직원(hospital_staff)·직원(staff) 역할은 2026-09-29 제거됨
$ADMIN_ROLE_PERMISSIONS = [
    // === 플랫폼 역할 ===
    'super_admin' => ['*'],
    'admin' => ['treatment_units', 'community_moderation', 'dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'hospital_news', 'reviews', 'bookings', 'categories', 'ads', 'ad_slot_manage', 'navigation', 'theme', 'theme_advanced', 'design_mode', 'settings', 'maintenance', 'upgrade', 'members', 'membership', 'faq', 'qna', 'rankings', 'doctor_treatments', 'treatment_packages', 'excel_import', 'excel_export', 'medicines', 'nurses', 'patients', 'pharmacists', 'staffs', 'auto_manual', 'auto_test', 'auto_test_v2', 'hospital_wizard', 'ad_wizard', 'wizard', 'site_settings', 'nav_settings', 'admin_mail', 'mail_templates', 'mail_signatures', 'mail_accounts', 'editor_settings', 'review_wizard', 'approval_queue', 'screenshot_book', 'wizard_guide', 'menu_guide', 'gnuboard_admin', 'notifications', 'fix_log', 'doctors', 'tips', 'events', 'event_entries', 'cpv_settings', 'cpv_wallet', 'cpv_rates', 'cpv_dashboard', 'cpv_settlement', 'dev_ref_cpv', 'dev_ref_confirm', 'dev_ref_qa', 'dev_ref_cpv_qa', 'dev_ref_payment', 'manage_reports', 'grade_permissions', 'admin_manage', 'admin_accounts', 'hospital_landing', 'hospital_apply', 'hospital_apply_status', 'hospital_pending', 'hospital_process', 'hospital_guide', 'site_settings_main', 'booking_revenue'],
    'editor' => ['community_moderation', 'dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'reviews', 'bookings', 'categories', 'ads', 'navigation', 'auto_manual', 'auto_test', 'auto_test_v2', 'hospital_wizard', 'ad_wizard', 'wizard', 'site_settings', 'nav_settings', 'review_wizard', 'screenshot_book', 'wizard_guide', 'menu_guide', 'gnuboard_admin', 'notifications', 'fix_log', 'doctors', 'tips', 'events'],
    'viewer' => ['dashboard', 'hospital_images'],

    // === 병원 역할 (hospital_id 기반 격리) ===
    'hospital_super_admin' => ['dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'hospital_news', 'doctor_treatments', 'treatment_packages', 'doctors', 'nurses', 'patients', 'pharmacists', 'staffs', 'medicines', 'reviews', 'bookings', 'consultations', 'events', 'event_entries', 'notifications', 'tips', 'categories', 'ads', 'partner_hospital', 'partner_treatments', 'partner_wallet', 'partner_settings', 'partner_ai_settings', 'hospital_stats', 'booking_revenue'],
    'hospital_admin' => ['dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'hospital_news', 'doctor_treatments', 'treatment_packages', 'doctors', 'nurses', 'patients', 'pharmacists', 'staffs', 'medicines', 'reviews', 'bookings', 'consultations', 'events', 'event_entries', 'notifications', 'tips', 'categories', 'ads', 'partner_hospital', 'partner_treatments', 'partner_wallet', 'partner_settings', 'partner_ai_settings', 'booking_revenue'],
];

$ADMIN_ROLE_LABELS = [
    'super_admin' => '최고관리자',
    'admin' => '관리자',
    'editor' => '에디터',
    'viewer' => '뷰어',
    'hospital_super_admin' => '병원 최고관리자',
    'hospital_admin' => '병원 관리자',
];

// 역할 그룹 분류
$ADMIN_ROLE_GROUPS = [
    'platform' => ['super_admin', 'admin', 'editor', 'viewer'],
    'hospital' => ['hospital_super_admin', 'hospital_admin'],
];

/**
 * 병원 등급별 기능 제한 체크
 * @param string $feature_key 기능 키 (banner_ads, treatments, doctors 등)
 * @param int|null $hospital_id 병원 ID
 * @return array ['allowed' => bool, 'max_count' => int|null]
 */
function check_grade_permission($feature_key, $hospital_id = null) {
    global $connect;
    if (!$connect) return ['allowed' => true, 'max_count' => null];

    $hid = $hospital_id ?: ($_SESSION['ad_admin_hospital_id'] ?? null);
    if (!$hid) return ['allowed' => true, 'max_count' => null]; // 플랫폼 관리자는 제한 없음

    $stmt = mysqli_prepare($connect, "SELECT gp.is_allowed, gp.max_count FROM gangnamunni_grade_permissions gp JOIN gangnamunni_hospitals h ON h.ad_grade = gp.ad_grade WHERE h.id = ? AND gp.feature_key = ?");
    if (!$stmt) return ['allowed' => true, 'max_count' => null];
    mysqli_stmt_bind_param($stmt, 'is', $hid, $feature_key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) return ['allowed' => true, 'max_count' => null];
    return ['allowed' => (bool)$row['is_allowed'], 'max_count' => $row['max_count']];
}

/**
 * 현재 관리자가 병원 역할인지
 */
function is_hospital_role() {
    $role = $_SESSION['ad_admin_role'] ?? '';
    return in_array($role, ['hospital_super_admin', 'hospital_admin'], true);
}

/**
 * 현재 관리자가 최고관리자인지 (모든 기능 무제한)
 */
function is_super_admin() {
    return check_admin_login() && (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
}

/**
 * 현재 관리자가 접근할 수 있는 병원 범위
 * @return int|null  null = 전체 병원(최고관리자, 병원 미배정 플랫폼 관리자)
 *                   >0   = 해당 병원만
 *                   -1   = 소속 병원이 없는 병원 계정 / 미로그인 (fail-closed)
 */
function admin_scoped_hospital_id() {
    if (!check_admin_login()) return -1;
    if (($_SESSION['ad_admin_role'] ?? '') === 'super_admin') return null;
    $hid = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    if ($hid > 0) return $hid;
    return is_hospital_role() ? -1 : null;
}

/**
 * 관리자 세션 저장
 */
function admin_set_session($admin, $connect = null) {
    global $ADMIN_ROLE_PERMISSIONS;
    if (!$admin || empty($admin['id'])) return false;
    // 제거된 역할(부관리자·직원 등)이나 알 수 없는 역할의 계정은 로그인할 수 없다.
    if (!isset($ADMIN_ROLE_PERMISSIONS[$admin['role'] ?? ''])) return false;

    // 세션에 관리자 정보 저장
    $_SESSION['ad_admin_logged_in'] = true;
    $_SESSION['ad_admin_user'] = $admin['username'];
    $_SESSION['ad_admin_email'] = $admin['email'] ?? '';
    $_SESSION['ad_admin_id'] = $admin['id'];
    $_SESSION['ad_admin_name'] = $admin['name'];
    $_SESSION['ad_admin_role'] = $admin['role'];
    $_SESSION['ad_admin_permissions'] = json_decode($admin['permissions'] ?? '{}', true) ?: [];
    // 병원 관리자용 hospital_id
    $_SESSION['ad_admin_hospital_id'] = $admin['hospital_id'] ?? null;
    $_SESSION['ad_admin_hospital_name'] = $admin['hospital_name'] ?? '';
    $_SESSION['partner_hospital_id'] = $admin['hospital_id'] ?? null;

    // 병원 소속은 관리자 관리(admin_admins.php)에서 지정한 hospital_id 만 사용한다.
    // (이전에는 로그인 아이디/이메일과 같은 email 의 병원으로 hospital_id 를 덮어썼으나 제거함)

    // 마지막 로그인 시간 업데이트
    if ($connect) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $update = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, 'si', $ip, $admin['id']);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
            }
        } catch (Throwable $e) {
            error_log('[admin_set_session] last login update failed: ' . $e->getMessage());
        }
    }

    return true;
}

/**
 * 관리자 계정 조회
 * username/email 및 연결된 Gnuboard mb_id 입력을 허용한다.
 */
function admin_find_by_login($login, $connect) {
    $login = trim($login);
    if ($login === '') return null;

    try {
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_admins WHERE username = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $login);
            mysqli_stmt_execute($stmt);
            $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($admin) return $admin;
        }

        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_admins WHERE email = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $login);
            mysqli_stmt_execute($stmt);
            $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($admin) return $admin;
        }

        $stmt = mysqli_prepare($connect, "SELECT mb_email FROM g5_member WHERE mb_id = ? OR mb_email = ? LIMIT 1");
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, 'ss', $login, $login);
        mysqli_stmt_execute($stmt);
        $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (empty($member['mb_email'])) return null;

        $email = $member['mb_email'];
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_admins WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, 'ss', $email, $email);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $admin ?: null;
    } catch (Throwable $e) {
        error_log('[admin_find_by_login] ' . $e->getMessage());
        return null;
    }
}

/**
 * 관리자 로그인 처리
 */
function admin_login($username, $password, $connect) {
    try {
        $admin = admin_find_by_login($username, $connect);

        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        return admin_set_session($admin, $connect);
    } catch (Throwable $e) {
        error_log('[admin_login] ' . $e->getMessage());
        return false;
    }
}

/**
 * gangnamunni_admins.g5_mb_id (관리자 ↔ 그누보드 회원 명시적 연결) 컬럼 존재 여부
 * 마이그레이션: sql/admin_sso_member_link.sql
 */
function admin_sso_link_ready($connect) {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $r = $connect ? mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_admins LIKE 'g5_mb_id'") : false;
        $ready = $r && mysqli_num_rows($r) > 0;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Gnuboard SSO 토큰으로 병원 관리자 세션 자동 생성
 * 관리자 관리에서 명시적으로 연결(g5_mb_id)된 회원만 해당 관리자로 로그인된다.
 */
function admin_login_from_sso($connect) {
    if (check_admin_login()) return true;

    try {
        $token = $_COOKIE['sb_sso_token'] ?? '';
        if (empty($token) || strlen($token) !== 64) return false;

        $stmt = mysqli_prepare($connect,
            "SELECT s.g5_mb_id, m.mb_email
               FROM sso_tokens s
          LEFT JOIN g5_member m ON m.mb_id = s.g5_mb_id
              WHERE s.token = ? AND s.expires_at > NOW()
              LIMIT 1"
        );
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $sso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$sso || empty($sso['g5_mb_id'])) return false;

        $g5_mb_id = (string)$sso['g5_mb_id'];

        // 관리자 ↔ 회원 연결은 gangnamunni_admins.g5_mb_id 로만 판단한다.
        // (이전에는 회원 mb_id/이메일이 관리자 username/email 과 같기만 하면 그 관리자로 로그인되어,
        //  관리자 아이디나 이메일로 가입한 일반 회원이 병원 관리자 세션을 얻을 수 있었다.)
        // 컬럼이 없거나(마이그레이션 전) 연결되지 않은 계정은 SSO 로그인 불가 — 비밀번호 로그인만 가능.
        if (!admin_sso_link_ready($connect)) return false;

        $stmt = mysqli_prepare($connect,
            "SELECT *
               FROM gangnamunni_admins
              WHERE is_active = 1
                AND role IN ('hospital_super_admin', 'hospital_admin')
                AND g5_mb_id = ?
              LIMIT 2"
        );
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 's', $g5_mb_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($res);
        $ambiguous = $admin && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        // 정확히 하나의 계정에 대소문자까지 같은 값으로 연결된 경우만 허용 (fail-closed)
        if (!$admin || $ambiguous || !hash_equals((string)$admin['g5_mb_id'], (string)$g5_mb_id)) return false;

        return admin_set_session($admin, $connect);
    } catch (Throwable $e) {
        error_log('[admin_login_from_sso] ' . $e->getMessage());
        return false;
    }
}

/**
 * 로그인 상태 확인
 */
function check_admin_login() {
    global $ADMIN_ROLE_PERMISSIONS;
    if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) return false;
    // 제거된 역할로 이미 로그인된 세션은 무효화한다.
    if (is_array($ADMIN_ROLE_PERMISSIONS) && !isset($ADMIN_ROLE_PERMISSIONS[$_SESSION['ad_admin_role'] ?? ''])) {
        unset($_SESSION['ad_admin_logged_in'], $_SESSION['ad_admin_role'], $_SESSION['ad_admin_id']);
        return false;
    }
    return true;
}

/**
 * 로그인 필수 (미로그인시 대시보드로 리다이렉트)
 */
function require_admin_login($redirect = null) {
    if (!check_admin_login()) {
        if ($redirect) {
            $url = $redirect;
        } else {
            // 현재 페이지 URL을 return 파라미터로 전달
            $current = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            $return_page = $current . ($qs ? '?' . $qs : '');
            $url = 'dashboard.php?return=' . urlencode($return_page);
        }
        header('Location: ' . $url);
        exit;
    }
    require_once __DIR__ . '/admin_menu_navigation.php';
    admin_enforce_menu_navigation();
}

/**
 * 현재 관리자 정보
 */
function get_current_admin() {
    return [
        'id' => $_SESSION['ad_admin_id'] ?? 0,
        'username' => $_SESSION['ad_admin_user'] ?? '',
        'name' => $_SESSION['ad_admin_name'] ?? '',
        'role' => $_SESSION['ad_admin_role'] ?? 'viewer',
        'hospital_id' => $_SESSION['ad_admin_hospital_id'] ?? null,
        'hospital_name' => $_SESSION['ad_admin_hospital_name'] ?? '',
        'permissions' => $_SESSION['ad_admin_permissions'] ?? [],
    ];
}

/**
 * 권한 체크
 * 역할 기본값으로만 판단한다 (개별 커스텀 권한 없음)
 */
function check_permission($permission) {
    global $ADMIN_ROLE_PERMISSIONS;

    // 최고관리자는 예외 없이 모든 기능에 접근한다.
    if (check_admin_login() && (($_SESSION['ad_admin_role'] ?? '') === 'super_admin')) {
        return true;
    }

    // 관리자/편집자의 예약 페이지 접근은 허용하되, 실제 조회/생성 범위는
    // admin_bookings.php와 admin_appointment_wizard.php에서 소유 병원으로 fail-closed 제한한다.
    if ($permission === 'bookings' && check_admin_login()
        && in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin', 'editor'], true)) {
        return true;
    }

    // 관리자/편집자가 병원에 명시적으로 배정된 경우 해당 병원의
    // 매출과 정산 보고서만 조회할 수 있다. 실제 데이터 scope는 각 페이지에서
    // session hospital_id로 fail-closed 적용한다.
    if (in_array($permission, ['booking_revenue', 'cpv_settlement'], true) && check_admin_login()) {
        $role = $_SESSION['ad_admin_role'] ?? '';
        if ($role === 'super_admin') return true;
        return in_array($role, ['admin', 'editor', 'hospital_super_admin', 'hospital_admin'], true)
            && (int)($_SESSION['ad_admin_hospital_id'] ?? 0) > 0;
    }

    // 일반 관리자도 관리자 계정 페이지에서 본인 프로필을 수정할 수 있다.
    // 다른 계정에 대한 CRUD 권한은 admin_admins.php에서 super_admin으로 별도 검증한다.
    // 단, 병원 계정(병원 관리자 등)은 관리자 관리 페이지에 접근할 수 없다.
    if ($permission === 'admin_accounts' && check_admin_login()) {
        return !is_hospital_role();
    }

    // 카테고리 관리는 플랫폼 전체에 영향을 주므로 최고관리자만 허용한다.
    if ($permission === 'categories') {
        return (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
    }

    // 병원 이미지/병원별·의사별 시술 배정 메뉴는 모든 로그인 관리자에게 허용한다.
    // 실제 병원 데이터 범위는 각 페이지에서 fail-closed 방식으로 제한한다.
    $all_admin_pages = [
        'hospital_images',
        'hospital_treatments',
        'doctor_treatments',
        'treatment_packages',
    ];
    if (in_array($permission, $all_admin_pages, true) && check_admin_login()) {
        return true;
    }

    $role = $_SESSION['ad_admin_role'] ?? 'viewer';
    $role_perms = $ADMIN_ROLE_PERMISSIONS[$role] ?? [];

    // super_admin은 무조건 전체 권한
    if (in_array('*', $role_perms)) return true;

    // 관리자별 개별(커스텀) 페이지 권한은 사용하지 않는다. 권한은 역할 기본값으로만 결정된다.
    // (permissions JSON의 'pages' 값은 과거 데이터이며 무시된다.)
    return in_array($permission, $role_perms);
}

/**
 * 병원 계정의 첫 화면. 역할 기본값뿐 아니라 개별 pages 설정도 반영한다.
 * 플랫폼 대시보드로 되돌리지 않아 병원 계정의 리다이렉트 반복을 방지한다.
 */
function admin_login_destination($return_url) {
    if (!is_hospital_role()) return $return_url;
    $page = basename(parse_url($return_url, PHP_URL_PATH) ?: 'dashboard.php');
    if ($page === 'dashboard.php'
        || ($page === 'partner_hospital.php' && !check_permission('partner_hospital'))) {
        return hospital_admin_landing_page() ?? 'dashboard.php';
    }
    return $return_url;
}

function hospital_admin_landing_page() {
    require_once __DIR__ . '/admin_menu_navigation.php';
    $menus = admin_sidebar_menus();
    $pages = [
        'partner_hospital' => 'partner_hospital.php',
        'bookings' => 'admin_bookings.php',
        'reviews' => 'admin_reviews.php',
        'notifications' => 'admin_notifications.php',
        'doctors' => 'admin_doctors.php',
        'hospital_images' => 'admin_hospital_images.php',
    ];
    foreach ($pages as $permission => $url) {
        if (check_permission($permission)
            && admin_menu_contains_page($url, $menus, 'check_permission')) return $url;
    }

    // A permitted but hidden profile page would redirect back to dashboard too.
    if (check_permission('admin_accounts')
        && admin_menu_contains_page('admin_admins.php', $menus, 'check_permission')) return 'admin_admins.php';
    return null;
}

/**
 * 플랫폼 전역 데이터(시술 마스터, 광고 단가, 광고주, 추천 병원 등) 관리 페이지 가드.
 * 병원 계정은 역할 권한(ads, hospitals 등)이 있어도 전역 데이터는 변경할 수 없다 → 403.
 * (메뉴 이동 제한은 GET 만 막으므로 POST 직접 호출로 전역 데이터를 바꾸거나 지울 수 있었다.)
 */
function require_platform_admin_page() {
    if (!check_admin_login() || is_hospital_role()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="ko"><meta charset="UTF-8"><title>권한 없음</title><p>플랫폼 관리자만 접근할 수 있는 페이지입니다.</p></html>';
        exit;
    }
}

/**
 * 권한 필수 체크
 */
function require_permission($permission) {
    if (!check_permission($permission)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>권한 없음</title>';
        echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa;}';
        echo 'h2{color:#333;margin-bottom:12px;}p{color:#666;}a{color:#667eea;}</style></head><body>';
        echo '<h2>접근 권한이 없습니다</h2>';
        echo '<p>이 페이지에 접근할 수 있는 권한이 없습니다.</p>';
        echo '<a href="dashboard.php">대시보드로 돌아가기</a>';
        echo '</body></html>';
        exit;
    }
}

/**
 * 역할 라벨
 */
function get_role_label($role) {
    global $ADMIN_ROLE_LABELS;
    return $ADMIN_ROLE_LABELS[$role] ?? $role;
}

/**
 * 로그아웃
 */
function admin_logout() {
    $auto_test_cookie_options = [
        'expires' => time() - 42000,
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ];
    setcookie('hms_auto_test_v1', '', $auto_test_cookie_options + ['path' => '/auto-manual-api/api/auto-test']);
    setcookie('hms_auto_test_v2', '', $auto_test_cookie_options + ['path' => '/auto-test-v2-api/api/v2']);
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

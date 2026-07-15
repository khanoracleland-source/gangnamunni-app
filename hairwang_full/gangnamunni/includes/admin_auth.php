<?php
/**
 * 관리자 인증 시스템
 * DB 기반 로그인/권한 관리
 *
 * gangnamunni_admins 테이블 사용
 * @created 2026-02-11
 */

// 역할별 기본 권한
// 역할별 기본 권한 — 9역할 (플랫폼 5 + 병원 4)
$ADMIN_ROLE_PERMISSIONS = [
    // === 플랫폼 역할 ===
    'super_admin' => ['*'],
    'admin' => ['dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'reviews', 'categories', 'ads', 'navigation', 'theme', 'theme_advanced', 'maintenance', 'upgrade', 'members', 'auto_manual', 'auto_test', 'auto_test_v2', 'hospital_wizard', 'ad_wizard', 'wizard', 'site_settings', 'nav_settings', 'admin_mail', 'mail_templates', 'mail_signatures', 'mail_accounts', 'editor_settings', 'review_wizard', 'approval_queue', 'screenshot_book', 'wizard_guide', 'menu_guide', 'gnuboard_admin', 'admins', 'notifications', 'fix_log', 'doctors', 'tips', 'events', 'cpv_settings', 'cpv_wallet', 'cpv_rates', 'cpv_dashboard', 'cpv_settlement', 'dev_ref_cpv', 'dev_ref_confirm', 'dev_ref_qa', 'dev_ref_cpv_qa', 'dev_ref_payment', 'manage_reports', 'grade_permissions', 'admin_manage', 'hospital_landing', 'hospital_apply', 'hospital_apply_status', 'hospital_pending', 'hospital_process', 'hospital_guide', 'site_settings_main'],
    'editor' => ['dashboard', 'hospitals', 'hospital_images', 'hospital_treatments', 'reviews', 'bookings', 'categories', 'ads', 'navigation', 'auto_manual', 'auto_test', 'auto_test_v2', 'hospital_wizard', 'ad_wizard', 'wizard', 'site_settings', 'nav_settings', 'review_wizard', 'screenshot_book', 'wizard_guide', 'menu_guide', 'gnuboard_admin', 'notifications', 'fix_log', 'doctors', 'tips', 'events'],
    'viewer' => ['dashboard'],
    'staff' => ['dashboard', 'hospitals', 'reviews', 'bookings', 'notifications', 'members'],

    // === 병원 역할 (hospital_id 기반 격리) ===
    'hospital_super_admin' => ['dashboard', 'hospitals', 'hospital_images', 'doctors', 'reviews', 'bookings', 'consultations', 'treatments', 'events', 'event_entries', 'notifications', 'members', 'tips', 'categories', 'partner_hospital', 'partner_treatments', 'partner_wallet', 'partner_settings', 'partner_ai_settings', 'hospital_admins', 'hospital_stats'],
    'hospital_admin' => ['dashboard', 'hospitals', 'hospital_images', 'doctors', 'reviews', 'bookings', 'consultations', 'treatments', 'events', 'event_entries', 'notifications', 'members', 'tips', 'categories', 'partner_hospital', 'partner_treatments', 'partner_wallet', 'partner_settings', 'partner_ai_settings'],
    'hospital_sub_admin' => ['dashboard', 'hospitals', 'doctors', 'reviews', 'bookings', 'partner_treatments', 'notifications'],
    'hospital_staff' => ['dashboard', 'bookings', 'reviews', 'notifications'],
];

$ADMIN_ROLE_LABELS = [
    'super_admin' => '최고관리자',
    'admin' => '관리자',
    'editor' => '에디터',
    'viewer' => '뷰어',
    'staff' => '직원',
    'hospital_super_admin' => '병원 최고관리자',
    'hospital_admin' => '병원 관리자',
    'hospital_sub_admin' => '병원 부관리자',
    'hospital_staff' => '병원 직원',
];

// 역할 그룹 분류
$ADMIN_ROLE_GROUPS = [
    'platform' => ['super_admin', 'admin', 'editor', 'viewer', 'staff'],
    'hospital' => ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff'],
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
    return in_array($role, ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff']);
}

/**
 * 관리자 세션 저장
 */
function admin_set_session($admin, $connect = null) {
    if (!$admin || empty($admin['id'])) return false;

    // 세션에 관리자 정보 저장
    $_SESSION['ad_admin_logged_in'] = true;
    $_SESSION['ad_admin_user'] = $admin['username'];
    $_SESSION['ad_admin_email'] = $admin['email'] ?? ''; // 병원 email 매칭(다병원 소속 판별)용
    $_SESSION['ad_admin_id'] = $admin['id'];
    $_SESSION['ad_admin_name'] = $admin['name'];
    $_SESSION['ad_admin_role'] = $admin['role'];
    $_SESSION['ad_admin_permissions'] = json_decode($admin['permissions'] ?? '{}', true) ?: [];
    // 병원 관리자용 hospital_id
    $_SESSION['ad_admin_hospital_id'] = $admin['hospital_id'] ?? null;
    $_SESSION['ad_admin_hospital_name'] = $admin['hospital_name'] ?? '';
    $_SESSION['partner_hospital_id'] = $admin['hospital_id'] ?? null;

    // 병원 ↔ 관리자 연결 규칙: 관리자 로그인(username/email)이 어떤 병원의 email 과 일치하면
    // 그 병원의 관리자로 간주한다. (병원 관리자는 병원 명의로 가입하므로 email 이 곧 로그인 아이디)
    // 이메일 매칭을 '권위 있는' 소속으로 사용 → 기존 hospital_id 가 다른(잘못된) 병원을 가리켜도 교정한다.
    // (정상 계정은 username=병원.email 이라 같은 병원으로 해석되어 변화 없음)
    // super_admin 은 전체 관리자이므로 제외.
    if ($connect && ($admin['role'] ?? '') !== 'super_admin') {
        $login = trim($admin['username'] ?? '');
        $mail  = trim($admin['email'] ?? '');
        if ($login !== '' || $mail !== '') {
            $hstmt = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE email = ? OR email = ? ORDER BY id LIMIT 1");
            if ($hstmt) {
                mysqli_stmt_bind_param($hstmt, 'ss', $login, $mail);
                mysqli_stmt_execute($hstmt);
                $hrow = mysqli_fetch_assoc(mysqli_stmt_get_result($hstmt));
                mysqli_stmt_close($hstmt);
                if ($hrow && !empty($hrow['id'])) {
                    $matched_hid = (int)$hrow['id'];
                    $_SESSION['ad_admin_hospital_id'] = $matched_hid;
                    $_SESSION['ad_admin_hospital_name'] = $hrow['name'];
                    $_SESSION['partner_hospital_id'] = $matched_hid;
                    // 기존 hospital_id 가 다르면 DB 도 교정해 영속화 (다음부터 조회·리포트에 반영)
                    if ((int)($admin['hospital_id'] ?? 0) !== $matched_hid) {
                        try {
                            $upd = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET hospital_id = ?, hospital_name = ? WHERE id = ?");
                            if ($upd) {
                                mysqli_stmt_bind_param($upd, 'isi', $matched_hid, $hrow['name'], $admin['id']);
                                mysqli_stmt_execute($upd);
                                mysqli_stmt_close($upd);
                            }
                        } catch (Throwable $e) {
                            error_log('[admin_set_session] hospital_id 교정 실패: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

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
 * Gnuboard SSO 토큰으로 병원 관리자 세션 자동 생성
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

        $g5_mb_id = $sso['g5_mb_id'];
        $email = $sso['mb_email'] ?? '';

        $stmt = mysqli_prepare($connect,
            "SELECT *
               FROM gangnamunni_admins
              WHERE is_active = 1
                AND role IN ('hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff')
                AND (username = ? OR email = ? OR username = ?)
              LIMIT 1"
        );
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'sss', $g5_mb_id, $email, $email);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$admin) return false;

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
    return isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
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
 * 커스텀 pages 설정이 있으면 DB 값 우선, 없으면 역할 기본값
 */
function check_permission($permission) {
    global $ADMIN_ROLE_PERMISSIONS;
    $role = $_SESSION['ad_admin_role'] ?? 'viewer';
    $role_perms = $ADMIN_ROLE_PERMISSIONS[$role] ?? [];

    // super_admin은 무조건 전체 권한
    if (in_array('*', $role_perms)) return true;

    // DB에 커스텀 pages가 있으면 그것만 사용
    $extra = $_SESSION['ad_admin_permissions'] ?? [];
    if (is_array($extra) && !empty($extra['pages'])) {
        return in_array($permission, $extra['pages']);
    }

    // 커스텀 없으면 역할 기본값
    return in_array($permission, $role_perms);
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

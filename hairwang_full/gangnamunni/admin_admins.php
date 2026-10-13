<?php
/**
 * 관리자 관리 페이지
 * 부관리자 CRUD + 세부 권한 지정 + 알림 수신 설정
 * Bootstrap 5.3 + 주간/야간 모드
 * @updated 2026-02-26 권한 체크박스 + 알림 설정 추가
 * @updated 2026-09-29 관리자 ↔ 병원 배정(hospital_id)
 * @updated 2026-09-29 역할을 최고관리자/병원 관리자 2종으로 단순화, 개별(커스텀) 페이지 권한 제거
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_notifications.php';
require_once __DIR__ . '/includes/functions.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 + 권한 체크
require_admin_login();
$current_admin = get_current_admin();
$is_super_admin = ($current_admin['role'] === 'super_admin');
$can_edit_own_profile = check_admin_login();

// 최고관리자는 전체 관리, 일반 관리자는 본인 프로필만 관리할 수 있다.
// 병원 계정(병원 관리자 등)은 이 페이지에 접근할 수 없다.
if (!$can_edit_own_profile || is_hospital_role()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>권한 없음</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa;}h2{color:#333;margin-bottom:12px;}p{color:#666;}a{color:#667eea;}</style></head><body>';
    echo '<h2>접근 권한이 없습니다</h2>';
    echo '<p>이 페이지에 접근할 수 있는 권한이 없습니다.</p>';
    echo '<a href="dashboard.php">대시보드로 돌아가기</a>';
    echo '</body></html>';
    exit;
}

$message = '';
$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $error = '요청이 만료되었습니다. 페이지를 새로고침한 후 다시 시도해주세요.';
}

// permissions JSON 파싱 헬퍼
function parse_permissions($perms_json) {
    $perms = json_decode($perms_json ?? '{}', true);
    if (!is_array($perms)) $perms = [];
    return $perms;
}

// permissions JSON 빌드 — 개별 페이지 권한은 사용하지 않고 알림 수신 설정만 저장한다.
// (페이지 접근 권한은 역할 기본값으로만 결정된다.)
function build_permissions_json($post_data) {
    global $NOTIFICATION_TYPES;
    $perms = [];

    // 알림 수신 설정
    $notifs = $post_data['notifications'] ?? [];
    if (!is_array($notifs)) $notifs = [];
    $valid_notifs = array_keys($NOTIFICATION_TYPES);
    $perms['notifications'] = array_values(array_intersect($notifs, $valid_notifs));

    return json_encode($perms, JSON_UNESCAPED_UNICODE);
}

// 이메일이 다른 관리자와 중복되는지 확인한다.
// 이메일은 로그인 식별자(username 또는 email)로 쓰이므로, 중복되면 이메일 로그인이
// 어느 계정으로 매칭될지 모호해진다(admin_find_by_login 은 LIMIT 1). 그래서 유일해야 한다.
function admin_email_taken($connect, $email, $exclude_id = 0) {
    if ($email === '') return false;

    $checks = [
        ['table' => 'users', 'email' => 'email', 'id' => 'id', 'exclude' => 0],
        ['table' => 'g5_member', 'email' => 'mb_email', 'id' => 'mb_no', 'exclude' => 0],
        ['table' => 'gangnamunni_admins', 'email' => 'email', 'id' => 'id', 'exclude' => $exclude_id],
    ];

    foreach ($checks as $check) {
        $table_exists = mysqli_query($connect, "SHOW TABLES LIKE '{$check['table']}'");
        if (!$table_exists || mysqli_num_rows($table_exists) === 0) continue;

        $sql = "SELECT {$check['id']} FROM {$check['table']} WHERE LOWER({$check['email']}) = LOWER(?)";
        if ($check['exclude'] > 0) $sql .= " AND {$check['id']} <> ?";
        $sql .= ' LIMIT 1';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) continue;
        if ($check['exclude'] > 0) mysqli_stmt_bind_param($stmt, 'si', $email, $check['exclude']);
        else mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $taken = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if ($taken) return true;
    }

    return false;
}

// === 역할 / 병원 배정 ===
// admin_admins.php에서 지정 가능한 역할은 2종뿐이다.
// - super_admin    : 모든 기능, 전체 병원
// - hospital_admin : 소속 병원 1곳, 병원 관리자 기본 권한
$ASSIGNABLE_ROLES = ['super_admin', 'hospital_admin'];
$HOSPITAL_ROLES = $ADMIN_ROLE_GROUPS['hospital'];

/**
 * 역할에 맞는 병원 배정을 검증한다.
 * - super_admin: 병원 배정 없음 (항상 전체 접근) → null
 * - 병원 관리자(병원 역할): 병원 필수
 * @return array [hospital_id|null, hospital_name|null, error|null]
 */
function resolve_admin_hospital($connect, $role, $hospital_id) {
    global $HOSPITAL_ROLES;
    $hospital_id = (int)$hospital_id;

    if ($role === 'super_admin') return [null, null, null];
    if ($hospital_id <= 0) {
        if (in_array($role, $HOSPITAL_ROLES, true)) {
            return [null, null, '병원 관리자는 소속 병원을 반드시 선택해야 합니다.'];
        }
        return [null, null, null];
    }

    $stmt = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1");
    if (!$stmt) return [null, null, '병원 정보를 확인할 수 없습니다.'];
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $hospital = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$hospital) return [null, null, '선택한 병원을 찾을 수 없습니다.'];
    return [(int)$hospital['id'], $hospital['name'], null];
}

// 병원 선택 목록 (최고관리자 화면에서만 사용)
$hospital_options = [];
if ($is_super_admin) {
    $h_res = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals ORDER BY name ASC, id ASC");
    if ($h_res) {
        while ($h = mysqli_fetch_assoc($h_res)) $hospital_options[] = $h;
    }
}

// 역할 선택 <option> 렌더링 — 최고관리자 / 병원 관리자 2종만 표시한다.
// 그 외(과거) 역할의 계정은 병원 관리자를 기본 선택으로 보여준다 (최고관리자로 잘못 승격되지 않도록).
function render_role_options($selected) {
    global $ASSIGNABLE_ROLES;
    if (!in_array($selected, $ASSIGNABLE_ROLES, true)) $selected = 'hospital_admin';
    $html = '';
    foreach ($ASSIGNABLE_ROLES as $r) {
        $html .= '<option value="' . htmlspecialchars($r) . '"' . ($r === $selected ? ' selected' : '') . '>'
               . htmlspecialchars(get_role_label($r)) . '</option>';
    }
    return $html;
}

// 병원 선택 <select> 렌더링
function render_hospital_select($selected_id, $select_id) {
    global $hospital_options;
    $selected_id = (int)$selected_id;
    $html = '<select name="hospital_id" id="' . $select_id . '" class="form-select form-select-sm">';
    $html .= '<option value="">— 병원 선택 —</option>';
    $found = false;
    foreach ($hospital_options as $h) {
        $is_sel = ((int)$h['id'] === $selected_id);
        if ($is_sel) $found = true;
        $html .= '<option value="' . (int)$h['id'] . '"' . ($is_sel ? ' selected' : '') . '>'
               . htmlspecialchars($h['name']) . ' (#' . (int)$h['id'] . ')</option>';
    }
    if ($selected_id > 0 && !$found) {
        // 삭제된 병원에 연결된 계정: 현재 값을 표시만 한다 (저장 시 검증에서 거부됨)
        $html .= '<option value="' . $selected_id . '" selected>삭제된 병원 #' . $selected_id . '</option>';
    }
    $html .= '</select>';
    return $html;
}

// === POST 처리 ===

// 관리자 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'add') {
    if (!$is_super_admin) {
        $error = '관리자를 추가할 권한이 없습니다.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'hospital_admin';
    if (!in_array($role, $ASSIGNABLE_ROLES, true)) $role = 'hospital_admin';
    [$add_hospital_id, $add_hospital_name, $hospital_error] = resolve_admin_hospital($connect, $role, $_POST['hospital_id'] ?? 0);

    if (!empty($error)) {
        // 상단에서 확인한 권한/CSRF 오류를 유지한다.
    } elseif (empty($username) || empty($password) || empty($name)) {
        $error = '아이디, 비밀번호, 이름은 필수입니다.';
    } elseif (strlen($password) < 6) {
        $error = '비밀번호는 6자 이상이어야 합니다.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = '아이디는 3~30자 영문/숫자/밑줄만 가능합니다.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '이메일 형식이 올바르지 않습니다.';
    } elseif (admin_email_taken($connect, $email)) {
        $error = '이미 다른 사용자가 사용 중인 이메일입니다.';
    } elseif ($hospital_error) {
        $error = $hospital_error;
    } else {
        $check = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admins WHERE username = ?");
        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);
        $exists = mysqli_stmt_get_result($check)->fetch_assoc();
        mysqli_stmt_close($check);

        if ($exists) {
            $error = '이미 존재하는 아이디입니다.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $perms_json = build_permissions_json($_POST);
            $parent_admin_id = (int)$current_admin['id'];

            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, hospital_id, hospital_name, parent_admin_id, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssssisis', $username, $hashed, $name, $email, $phone, $role, $add_hospital_id, $add_hospital_name, $parent_admin_id, $perms_json);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($connect);
                $message = "관리자 '{$name}'이(가) 추가되었습니다." . ($add_hospital_name ? " (병원: {$add_hospital_name})" : '');
                // 알림 생성
                $notif_body = "역할: " . get_role_label($role) . ($add_hospital_name ? " / 병원: {$add_hospital_name}" : '');
                create_notification($connect, 'admin_created', "새 관리자 등록: {$name}", $notif_body, 'admin_admins.php');
            } else {
                $error = '추가 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 관리자 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'edit') {
    $edit_id = intval($_POST['edit_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';

    $target_stmt = mysqli_prepare($connect, "SELECT role, permissions, password, email, hospital_id, hospital_name FROM gangnamunni_admins WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($target_stmt, 'i', $edit_id);
    mysqli_stmt_execute($target_stmt);
    $target_admin = mysqli_fetch_assoc(mysqli_stmt_get_result($target_stmt));
    mysqli_stmt_close($target_stmt);

    if (!$is_super_admin && $edit_id !== (int)$current_admin['id']) {
        $error = '다른 관리자의 정보를 수정할 권한이 없습니다.';
    } elseif (!$target_admin) {
        $error = '관리자 계정을 찾을 수 없습니다.';
    } elseif (!$is_super_admin
        && ($new_password !== '' || strcasecmp($email, $target_admin['email'] ?? '') !== 0)
        && !password_verify($current_password, $target_admin['password'])) {
        $error = '현재 비밀번호가 일치하지 않습니다.';
    }

    if (!empty($error)) {
        // 대상 계정 및 소유권 검증 오류를 유지한다.
    } elseif ($edit_id <= 0 || empty($name)) {
        $error = '이름은 필수입니다.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '이메일 형식이 올바르지 않습니다.';
    } elseif (admin_email_taken($connect, $email, $edit_id)) {
        $error = '이미 다른 사용자가 사용 중인 이메일입니다.';
    } else {
        $edit_hospital_id = $target_admin['hospital_id'] !== null ? (int)$target_admin['hospital_id'] : null;
        $edit_hospital_name = $target_admin['hospital_name'];
        if ($is_super_admin) {
            // 역할은 최고관리자 / 병원 관리자 중 하나만 저장할 수 있다.
            if (!in_array($role, $ASSIGNABLE_ROLES, true)) {
                $error = '역할은 최고관리자 또는 병원 관리자만 선택할 수 있습니다.';
            }
            // 개별 페이지 권한은 저장하지 않는다 (기존 커스텀 권한도 이 저장으로 제거된다).
            $perms_json = build_permissions_json($_POST);
            [$edit_hospital_id, $edit_hospital_name, $hospital_error] = resolve_admin_hospital($connect, $role, $_POST['hospital_id'] ?? 0);
            if ($hospital_error && empty($error)) $error = $hospital_error;
        } else {
            // 일반 관리자의 임의 role/permissions POST 변조를 무시한다.
            $role = $target_admin['role'];
            $own_perms = [];
            $posted_notifs = $_POST['notifications'] ?? [];
            $own_perms['notifications'] = array_values(array_intersect(
                is_array($posted_notifs) ? $posted_notifs : [],
                array_keys($NOTIFICATION_TYPES)
            ));
            $perms_json = json_encode($own_perms, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($error)) {
            // 병원 배정 검증 오류를 유지한다.
        } elseif ($edit_id == $current_admin['id'] && $role !== $current_admin['role']) {
            $error = '자신의 역할은 변경할 수 없습니다.';
        } else {
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = '비밀번호는 6자 이상이어야 합니다.';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET name=?, email=?, phone=?, role=?, hospital_id=?, hospital_name=?, password=?, permissions=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'ssssisssi', $name, $email, $phone, $role, $edit_hospital_id, $edit_hospital_name, $hashed, $perms_json, $edit_id);
                }
            } else {
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET name=?, email=?, phone=?, role=?, hospital_id=?, hospital_name=?, permissions=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssissi', $name, $email, $phone, $role, $edit_hospital_id, $edit_hospital_name, $perms_json, $edit_id);
            }

            // SSO 연결 회원 (최고관리자만, 마이그레이션 후에만)
            $link_value = null; $link_change = false;
            if (empty($error) && $is_super_admin && admin_sso_link_ready($connect) && array_key_exists('g5_mb_id', $_POST)) {
                $link_input = trim((string)$_POST['g5_mb_id']);
                $link_change = true;
                if ($link_input !== '') {
                    $lm = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_id = ? LIMIT 1");
                    mysqli_stmt_bind_param($lm, 's', $link_input);
                    mysqli_stmt_execute($lm);
                    $lm_row = mysqli_fetch_assoc(mysqli_stmt_get_result($lm));
                    mysqli_stmt_close($lm);
                    if (!$lm_row) {
                        $error = 'SSO 연결 회원 아이디가 존재하지 않습니다.';
                    } else {
                        $link_value = (string)$lm_row['mb_id']; // DB 의 정확한 표기로 저장
                        $ld = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admins WHERE g5_mb_id = ? AND id <> ? LIMIT 1");
                        mysqli_stmt_bind_param($ld, 'si', $link_value, $edit_id);
                        mysqli_stmt_execute($ld);
                        $ld_row = mysqli_fetch_assoc(mysqli_stmt_get_result($ld));
                        mysqli_stmt_close($ld);
                        if ($ld_row) $error = '이 회원은 이미 다른 관리자 계정(#' . (int)$ld_row['id'] . ')에 연결되어 있습니다.';
                    }
                }
            }

            if (empty($error)) {
                if (mysqli_stmt_execute($stmt)) {
                    $message = "관리자 정보가 수정되었습니다.";
                    if ($link_change) {
                        $lu = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET g5_mb_id = ? WHERE id = ?");
                        mysqli_stmt_bind_param($lu, 'si', $link_value, $edit_id);
                        if (!mysqli_stmt_execute($lu)) $message .= ' (SSO 연결 저장 실패: ' . mysqli_error($connect) . ')';
                        mysqli_stmt_close($lu);
                    }
                    if ($edit_id == $current_admin['id']) {
                        $_SESSION['ad_admin_name'] = $name;
                        $_SESSION['ad_admin_email'] = $email;
                        $_SESSION['ad_admin_permissions'] = json_decode($perms_json, true) ?: [];
                        $_SESSION['ad_admin_hospital_id'] = $edit_hospital_id;
                        $_SESSION['ad_admin_hospital_name'] = $edit_hospital_name ?? '';
                        $_SESSION['partner_hospital_id'] = $edit_hospital_id;
                    }
                } else {
                    $error = '수정 실패: ' . mysqli_error($connect);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// 관리자 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'delete') {
    $delete_id = intval($_POST['delete_id'] ?? 0);

    if (!$is_super_admin) {
        $error = '관리자를 삭제할 권한이 없습니다.';
    } elseif ($delete_id == $current_admin['id']) {
        $error = '자기 자신은 삭제할 수 없습니다.';
    } else {
        $target = mysqli_fetch_assoc(mysqli_query($connect, "SELECT role FROM gangnamunni_admins WHERE id = {$delete_id}"));
        if ($target && $target['role'] === 'super_admin') {
            $sa_count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_admins WHERE role='super_admin' AND is_active=1"))['cnt'];
            if ($sa_count <= 1) {
                $error = '마지막 최고관리자는 삭제할 수 없습니다.';
            }
        }

        if (empty($error)) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_admins WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $delete_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '관리자가 삭제되었습니다.';
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 활성/비활성 토글
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'toggle_active') {
    $toggle_id = intval($_POST['toggle_id'] ?? 0);
    if (!$is_super_admin) {
        $error = '관리자 상태를 변경할 권한이 없습니다.';
    } elseif ($toggle_id == $current_admin['id']) {
        $error = '자기 자신은 비활성화할 수 없습니다.';
    } else {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $toggle_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '상태가 변경되었습니다.';
        }
        mysqli_stmt_close($stmt);
    }
}

// === 관리자 목록 조회 ===
$admins = [];
$admin_scope_sql = $is_super_admin
    ? "SELECT * FROM gangnamunni_admins ORDER BY role ASC, id ASC"
    : "SELECT * FROM gangnamunni_admins WHERE id = " . (int)$current_admin['id'] . " LIMIT 1";
$result = mysqli_query($connect, $admin_scope_sql);
while ($row = mysqli_fetch_assoc($result)) {
    $admins[] = $row;
}

// 수정 모드 데이터
$edit_admin = null;
if (!$is_super_admin) {
    $edit_id = (int)$current_admin['id'];
} elseif (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
}
if (isset($edit_id)) {
    foreach ($admins as $a) {
        if ($a['id'] == $edit_id) {
            $edit_admin = $a;
            break;
        }
    }
}
$edit_perms = $edit_admin ? parse_permissions($edit_admin['permissions']) : [];

// 관리자 헤더
$admin_page_title = '관리자 관리';
$admin_current = 'admin_accounts';

$admin_extra_css = '
.role-badge { font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
.role-super_admin { background: #dc3545; color: #fff; }
.role-admin { background: #0d6efd; color: #fff; }
.role-editor { background: #198754; color: #fff; }
.role-viewer { background: #6c757d; color: #fff; }
.role-hospital_super_admin { background: #b02a37; color: #fff; }
.role-hospital_admin { background: #fd7e14; color: #fff; }
.admin-form .form-label { font-weight: 600; font-size: 13px; }
.notif-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 4px; }
.notif-grid label { font-size: 12px; padding: 4px 8px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.notif-grid label:hover { background: var(--bs-tertiary-bg); }
.notif-grid input[type="checkbox"] { margin: 0; }
.notif-icon { width: 20px; text-align: center; }
.perm-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 12px 16px; margin-top: 12px; }
.perm-section-title { font-size: 13px; font-weight: 700; margin-bottom: 8px; }
.select-btns { font-size: 11px; }
.select-btns a { cursor: pointer; text-decoration: underline; margin-right: 8px; }
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-<?= $is_super_admin ? 'people' : 'person-gear' ?> me-2"></i><?= $is_super_admin ? '관리자 관리' : '내 정보 수정' ?></h4>
    <?php if ($is_super_admin): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">
        <i class="bi bi-plus-lg me-1"></i>관리자 추가
    </button>
    <?php endif; ?>
</div>

<?php if ($is_super_admin): ?>
<!-- 추가 폼 -->
<div class="collapse <?= (!empty($error) && ($_POST['action'] ?? '') === 'add') ? 'show' : '' ?>" id="addForm">
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-plus me-1"></i> 새 관리자 추가</div>
    <div class="card-body admin-form">
        <form method="POST" id="addAdminForm">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">아이디 <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control form-control-sm" required
                           pattern="[a-zA-Z0-9_]{3,30}" placeholder="영문/숫자/밑줄 3~30자">
                </div>
                <div class="col-md-4">
                    <label class="form-label">비밀번호 <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control form-control-sm" required
                           minlength="6" placeholder="6자 이상">
                </div>
                <div class="col-md-4">
                    <label class="form-label">이름 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="표시 이름">
                </div>
                <div class="col-md-4">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="선택">
                </div>
                <div class="col-md-4">
                    <label class="form-label">전화번호</label>
                    <input type="text" name="phone" class="form-control form-control-sm" placeholder="선택">
                </div>
                <div class="col-md-4">
                    <label class="form-label">역할</label>
                    <select name="role" class="form-select form-select-sm" id="addRoleSelect">
                        <?php $add_role = ($_POST['action'] ?? '') === 'add' ? ($_POST['role'] ?? '') : '';
                              if (!in_array($add_role, $ASSIGNABLE_ROLES, true)) $add_role = 'hospital_admin'; ?>
                        <?= render_role_options($add_role) ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">소속 병원 <span class="text-danger d-none" id="addHospitalRequired">*</span></label>
                    <?= render_hospital_select(($_POST['action'] ?? '') === 'add' ? ($_POST['hospital_id'] ?? 0) : 0, 'addHospitalSelect') ?>
                    <div class="form-text" id="addHospitalHelp"></div>
                </div>
            </div>

            <!-- 알림 수신 설정 (추가 폼) -->
            <div class="perm-section" id="notif-settings">
                <div class="perm-section-title"><i class="bi bi-bell me-1"></i> 알림 수신 설정
                    <span class="select-btns ms-2">
                        <a onclick="toggleCheckboxes('addAdminForm', 'notifications[]', true)">전체선택</a>
                        <a onclick="toggleCheckboxes('addAdminForm', 'notifications[]', false)">전체해제</a>
                    </span>
                </div>
                <div class="notif-grid">
                    <?php foreach ($NOTIFICATION_TYPES as $ntype => $ninfo): ?>
                    <label>
                        <input type="checkbox" name="notifications[]" value="<?= $ntype ?>"
                            <?= in_array($ntype, $DEFAULT_NOTIFICATIONS) ? 'checked' : '' ?>>
                        <span class="notif-icon text-<?= $ninfo['color'] ?>"><i class="bi <?= $ninfo['icon'] ?>"></i></span>
                        <?= $ninfo['label'] ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>추가</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">취소</button>
            </div>
        </form>
    </div>
</div>
</div>
<?php endif; ?>

<?php if ($edit_admin):
    $ea_role = $edit_admin['role'];
    $ea_notifs = $edit_perms['notifications'] ?? null;
    // null이면 기본값 사용
    if ($ea_notifs === null) $ea_notifs = $DEFAULT_NOTIFICATIONS;
?>
<!-- 수정 폼 -->
<div class="card mb-3 border-primary">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-pencil me-1"></i> 관리자 수정: <?= htmlspecialchars($edit_admin['username']) ?>
    </div>
    <div class="card-body admin-form">
        <form method="POST" id="editAdminForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" value="<?= $edit_admin['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="row g-3">
                <?php if (!$is_super_admin): ?>
                <div class="col-12">
                    <label class="form-label">현재 비밀번호</label>
                    <input type="password" name="current_password" class="form-control form-control-sm"
                           autocomplete="current-password" placeholder="이메일 또는 비밀번호 변경 시 필수">
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">아이디</label>
                    <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($edit_admin['username']) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label">새 비밀번호</label>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           minlength="6" autocomplete="new-password" placeholder="변경 시에만 입력">
                </div>
                <div class="col-md-4">
                    <label class="form-label">이름 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?= htmlspecialchars($edit_admin['name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($edit_admin['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">전화번호</label>
                    <input type="text" name="phone" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($edit_admin['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">역할</label>
                    <?php if ($edit_admin['id'] == $current_admin['id'] || !$is_super_admin): ?>
                    <!-- 본인 계정/일반 관리자: 역할 변경 불가 (현재 역할 표시만) -->
                    <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars(get_role_label($ea_role)) ?>" disabled>
                    <input type="hidden" name="role" id="editRoleSelect" value="<?= htmlspecialchars($ea_role) ?>">
                    <?php else: ?>
                    <select name="role" class="form-select form-select-sm" id="editRoleSelect">
                        <?= render_role_options($ea_role) ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label">소속 병원 <span class="text-danger d-none" id="editHospitalRequired">*</span></label>
                    <?php if ($is_super_admin): ?>
                    <?= render_hospital_select($edit_admin['hospital_id'] ?? 0, 'editHospitalSelect') ?>
                    <div class="form-text" id="editHospitalHelp"></div>
                    <?php else: ?>
                    <input type="text" class="form-control form-control-sm" disabled
                           value="<?= htmlspecialchars($edit_admin['hospital_name'] ?: '배정 없음') ?>">
                    <?php endif; ?>
                </div>
                <?php if ($is_super_admin && admin_sso_link_ready($connect)): ?>
                <div class="col-md-4">
                    <label class="form-label">SSO 연결 회원 아이디</label>
                    <input type="text" name="g5_mb_id" class="form-control form-control-sm" maxlength="20"
                           value="<?= htmlspecialchars($edit_admin['g5_mb_id'] ?? '') ?>" placeholder="연결 안 함">
                    <div class="form-text">이 회원으로 사이트에 로그인하면 이 관리자 계정으로 자동 로그인됩니다. 본인 확인된 회원만 연결하세요.</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- 알림 수신 설정 -->
            <div class="perm-section">
                <div class="perm-section-title"><i class="bi bi-bell me-1"></i> 알림 수신 설정
                    <span class="select-btns ms-2">
                        <a onclick="toggleCheckboxes('editAdminForm', 'notifications[]', true)">전체선택</a>
                        <a onclick="toggleCheckboxes('editAdminForm', 'notifications[]', false)">전체해제</a>
                    </span>
                </div>
                <div class="notif-grid">
                    <?php foreach ($NOTIFICATION_TYPES as $ntype => $ninfo): ?>
                    <label>
                        <input type="checkbox" name="notifications[]" value="<?= $ntype ?>"
                            <?= in_array($ntype, $ea_notifs) ? 'checked' : '' ?>>
                        <span class="notif-icon text-<?= $ninfo['color'] ?>"><i class="bi <?= $ninfo['icon'] ?>"></i></span>
                        <?= $ninfo['label'] ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>저장</button>
                <a href="admin_admins.php" class="btn btn-secondary btn-sm">취소</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($is_super_admin): ?>
<!-- 역할 안내 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-shield-check me-1"></i> 역할 안내</div>
    <div class="card-body p-2">
        <table class="table table-sm mb-0" style="font-size:12px;">
            <tbody>
                <tr>
                    <td style="width:140px;"><span class="role-badge role-super_admin">최고관리자</span></td>
                    <td>모든 기능에 접근하며 전체 병원을 관리합니다. 병원에 배정되지 않습니다.</td>
                </tr>
                <tr>
                    <td><span class="role-badge role-hospital_admin">병원 관리자</span></td>
                    <td>선택한 병원 1곳의 데이터만 관리합니다. 접근 가능한 기능은 병원 관리자 기본 권한으로 고정됩니다.</td>
                </tr>
            </tbody>
        </table>
        <small class="text-body-secondary d-block mt-1 px-2">* 관리자별 개별 권한 추가/제외 기능은 제공하지 않습니다.</small>
    </div>
</div>

<!-- 관리자 목록 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list me-1"></i> 관리자 목록 (<?= count($admins) ?>명)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>아이디</th>
                    <th>이름</th>
                    <th>역할</th>
                    <th>소속 병원</th>
                    <th>알림</th>
                    <th>상태</th>
                    <th>마지막 로그인</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">등록된 관리자가 없습니다.</td></tr>
                <?php else: ?>
                <?php foreach ($admins as $admin):
                    $a_perms = parse_permissions($admin['permissions']);
                    $a_notifs = $a_perms['notifications'] ?? null;
                    $notif_count = is_array($a_notifs) ? count($a_notifs) : count($DEFAULT_NOTIFICATIONS);
                ?>
                <tr class="<?= !$admin['is_active'] ? 'table-secondary' : '' ?>">
                    <td><?= $admin['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($admin['username']) ?></strong>
                        <?= ($admin['id'] == $current_admin['id']) ? '<span class="badge bg-info ms-1">나</span>' : '' ?>
                    </td>
                    <td><?= htmlspecialchars($admin['name']) ?></td>
                    <td><span class="role-badge role-<?= htmlspecialchars($admin['role']) ?>"><?= htmlspecialchars(get_role_label($admin['role'])) ?></span></td>
                    <td>
                        <?php if (!empty($admin['hospital_id'])): ?>
                            <span class="badge bg-light text-dark border" title="병원 #<?= (int)$admin['hospital_id'] ?>">
                                <i class="bi bi-hospital me-1"></i><?= htmlspecialchars($admin['hospital_name'] ?: ('#' . (int)$admin['hospital_id'])) ?>
                            </span>
                        <?php elseif (in_array($admin['role'], $HOSPITAL_ROLES, true)): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger">미배정</span>
                        <?php else: ?>
                            <span class="text-muted">전체</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-info"><?= $notif_count ?>종</span></td>
                    <td>
                        <?php if ($admin['is_active']): ?>
                            <span class="badge bg-success">활성</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">비활성</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($admin['last_login_at']): ?>
                            <?= date('m-d H:i', strtotime($admin['last_login_at'])) ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="?edit=<?= $admin['id'] ?>" class="btn btn-outline-primary" title="수정">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($admin['id'] != $current_admin['id']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('상태를 변경하시겠습니까?')">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="toggle_id" value="<?= $admin['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="btn btn-outline-warning" title="<?= $admin['is_active'] ? '비활성화' : '활성화' ?>">
                                    <i class="bi bi-<?= $admin['is_active'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= $admin['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="btn btn-outline-danger" title="삭제">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// 역할에 따라 병원 선택 필수/비활성 처리
var HOSPITAL_ROLES = <?= json_encode($HOSPITAL_ROLES) ?>;
function syncHospitalField(prefix) {
    var roleSel = document.getElementById(prefix + 'RoleSelect');
    var hospSel = document.getElementById(prefix + 'HospitalSelect');
    if (!roleSel || !hospSel) return;
    var role = roleSel.value;
    var req = document.getElementById(prefix + 'HospitalRequired');
    var help = document.getElementById(prefix + 'HospitalHelp');
    var isHospitalRole = HOSPITAL_ROLES.indexOf(role) !== -1;

    if (role === 'super_admin') {
        hospSel.value = '';
        hospSel.disabled = true;
        hospSel.required = false;
        if (help) help.textContent = '최고관리자는 병원에 배정되지 않고 전체 병원에 접근합니다.';
    } else {
        hospSel.disabled = false;
        hospSel.required = isHospitalRole;
        if (help) help.textContent = '병원 관리자는 선택한 병원의 데이터만 관리할 수 있습니다.';
    }
    if (req) req.classList.toggle('d-none', !isHospitalRole);
}
['add', 'edit'].forEach(function(prefix) {
    var roleSel = document.getElementById(prefix + 'RoleSelect');
    if (!roleSel) return;
    roleSel.addEventListener('change', function() { syncHospitalField(prefix); });
    syncHospitalField(prefix);
});

// 체크박스 전체선택/해제
function toggleCheckboxes(formId, name, checked) {
    var form = document.getElementById(formId);
    if (!form) return;
    form.querySelectorAll('input[name="' + name + '"]').forEach(function(cb) {
        cb.checked = checked;
    });
}
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
?>

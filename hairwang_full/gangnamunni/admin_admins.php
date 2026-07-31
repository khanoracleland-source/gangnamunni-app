<?php
/**
 * 관리자 관리 페이지
 * 부관리자 CRUD + 세부 권한 지정 + 알림 수신 설정
 * Bootstrap 5.3 + 주간/야간 모드
 * @updated 2026-02-26 권한 체크박스 + 알림 설정 추가
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_notifications.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 + 권한 체크
require_admin_login();
require_permission('admin_manage');

// [보안] 관리자 관리(admin_admins.php)는 super_admin(최고관리자) 전용 — 그 외 역할 전부 차단
if (($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>권한 없음</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa;}h2{color:#333;margin-bottom:12px;}p{color:#666;}a{color:#667eea;}</style></head><body>';
    echo '<h2>접근 권한이 없습니다</h2>';
    echo '<p>관리자 관리는 최고관리자(super_admin)만 접근할 수 있습니다.</p>';
    echo '<a href="dashboard.php">대시보드로 돌아가기</a>';
    echo '</body></html>';
    exit;
}

$message = '';
$error = '';
$current_admin = get_current_admin();

// === 전체 페이지 권한 키 목록 ===
$ALL_PAGE_PERMISSIONS = [
    'dashboard' => '대시보드',
    'menu_guide' => '메뉴 안내',
    'hospitals' => '병원 관리',
    'reviews' => '후기 관리',
    'review_wizard' => '후기 검수',
    'hospital_images' => '병원 이미지',
    'ads' => '광고 관리',
    'categories' => '카테고리',
    'hospital_wizard' => '병원 등록',
    'ad_wizard' => '광고 신청',
    'nurse_wizard' => '간호사 등록',
    'pharmacist_wizard' => '약사 등록',
    'patient_wizard' => '환자 등록',
    'staff_wizard' => '직원 등록',
    'medicine_wizard' => '의약품 등록',
    'appointment_wizard' => '예약 등록',
    'excel_import' => '엑셀 등록',
    'excel_export' => '데이터 다운로드',
    'members' => '회원 관리',
    'admin_mail' => '메일함',
    'mail_templates' => '상용구',
    'mail_signatures' => '서명 관리',
    'mail_accounts' => '계정 설정',
    'ai_settings' => 'AI 설정',
    'auto_test' => '자동 테스트',
    'auto_test_v2' => '자동 테스트 v2',
    'theme' => '테마 설정',
    'theme_advanced' => '고급 테마',
    'nav_settings' => '네비게이션',
    'editor_settings' => '에디터 설정',
    'maintenance' => '점검 모드',
    'upgrade' => '확장 계획',
    'admins' => '관리자 관리',
    'wizard_guide' => '마법사 가이드',
    'auto_manual' => '자동 매뉴얼',
    'screenshot_book' => '스크린샷 매뉴얼',
];

// permissions JSON 파싱 헬퍼
function parse_permissions($perms_json) {
    $perms = json_decode($perms_json ?? '{}', true);
    if (!is_array($perms)) $perms = [];
    return $perms;
}

// permissions JSON 빌드
function build_permissions_json($post_data) {
    global $ALL_PAGE_PERMISSIONS, $NOTIFICATION_TYPES;
    $perms = [];

    // 페이지 권한 (커스텀 모드일 때만)
    if (!empty($post_data['custom_pages'])) {
        $pages = $post_data['pages'] ?? [];
        $valid_pages = array_keys($ALL_PAGE_PERMISSIONS);
        $perms['pages'] = array_values(array_intersect($pages, $valid_pages));
    }

    // 알림 수신 설정
    $notifs = $post_data['notifications'] ?? [];
    $valid_notifs = array_keys($NOTIFICATION_TYPES);
    $perms['notifications'] = array_values(array_intersect($notifs, $valid_notifs));

    return json_encode($perms, JSON_UNESCAPED_UNICODE);
}

// 이메일이 다른 관리자와 중복되는지 확인한다.
// 이메일은 로그인 식별자(username 또는 email)로 쓰이므로, 중복되면 이메일 로그인이
// 어느 계정으로 매칭될지 모호해진다(admin_find_by_login 은 LIMIT 1). 그래서 유일해야 한다.
function admin_email_taken($connect, $email, $exclude_id = 0) {
    if ($email === '') return false; // 빈 이메일은 여러 계정이 가질 수 있고 로그인에도 안 쓰인다
    $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admins WHERE email = ? AND id <> ? LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'si', $email, $exclude_id);
    mysqli_stmt_execute($stmt);
    $taken = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $taken;
}

// === POST 처리 ===

// 관리자 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'editor';

    if (empty($username) || empty($password) || empty($name)) {
        $error = '아이디, 비밀번호, 이름은 필수입니다.';
    } elseif (strlen($password) < 6) {
        $error = '비밀번호는 6자 이상이어야 합니다.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = '아이디는 3~30자 영문/숫자/밑줄만 가능합니다.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '이메일 형식이 올바르지 않습니다.';
    } elseif (admin_email_taken($connect, $email)) {
        $error = '이미 다른 관리자가 사용 중인 이메일입니다. (이메일 로그인 식별자로 사용되어 중복 불가)';
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
            $valid_roles = ['super_admin', 'admin', 'editor', 'viewer'];
            if (!in_array($role, $valid_roles)) $role = 'editor';

            $perms_json = build_permissions_json($_POST);

            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssssss', $username, $hashed, $name, $email, $phone, $role, $perms_json);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($connect);
                $message = "관리자 '{$name}'이(가) 추가되었습니다.";
                // 알림 생성
                create_notification($connect, 'admin_created', "새 관리자 등록: {$name}", "역할: " . get_role_label($role), 'admin_admins.php');
            } else {
                $error = '추가 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 관리자 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $edit_id = intval($_POST['edit_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'editor';
    $new_password = $_POST['new_password'] ?? '';

    if ($edit_id <= 0 || empty($name)) {
        $error = '이름은 필수입니다.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '이메일 형식이 올바르지 않습니다.';
    } elseif (admin_email_taken($connect, $email, $edit_id)) {
        $error = '이미 다른 관리자가 사용 중인 이메일입니다. (이메일 로그인 식별자로 사용되어 중복 불가)';
    } else {
        $valid_roles = ['super_admin', 'admin', 'editor', 'viewer'];
        if (!in_array($role, $valid_roles)) $role = 'editor';

        if ($edit_id == $current_admin['id'] && $role !== $current_admin['role']) {
            $error = '자신의 역할은 변경할 수 없습니다.';
        } else {
            $perms_json = build_permissions_json($_POST);

            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = '비밀번호는 6자 이상이어야 합니다.';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET name=?, email=?, phone=?, role=?, password=?, permissions=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $email, $phone, $role, $hashed, $perms_json, $edit_id);
                }
            } else {
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET name=?, email=?, phone=?, role=?, permissions=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $phone, $role, $perms_json, $edit_id);
            }

            if (empty($error)) {
                if (mysqli_stmt_execute($stmt)) {
                    $message = "관리자 정보가 수정되었습니다.";
                    if ($edit_id == $current_admin['id']) {
                        $_SESSION['ad_admin_name'] = $name;
                        $_SESSION['ad_admin_permissions'] = json_decode($perms_json, true) ?: [];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delete_id = intval($_POST['delete_id'] ?? 0);

    if ($delete_id == $current_admin['id']) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $toggle_id = intval($_POST['toggle_id'] ?? 0);
    if ($toggle_id == $current_admin['id']) {
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
$result = mysqli_query($connect, "SELECT * FROM gangnamunni_admins ORDER BY role ASC, id ASC");
while ($row = mysqli_fetch_assoc($result)) {
    $admins[] = $row;
}

// 수정 모드 데이터
$edit_admin = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
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
$admin_current = 'admins';

$admin_extra_css = '
.role-badge { font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
.role-super_admin { background: #dc3545; color: #fff; }
.role-admin { background: #0d6efd; color: #fff; }
.role-editor { background: #198754; color: #fff; }
.role-viewer { background: #6c757d; color: #fff; }
.admin-form .form-label { font-weight: 600; font-size: 13px; }
.perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 4px; }
.perm-grid label { font-size: 12px; padding: 4px 8px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.perm-grid label:hover { background: var(--bs-tertiary-bg); }
.perm-grid input[type="checkbox"] { margin: 0; }
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
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>관리자 관리</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">
        <i class="bi bi-plus-lg me-1"></i>관리자 추가
    </button>
</div>

<!-- 추가 폼 -->
<div class="collapse <?= (!empty($error) && ($_POST['action'] ?? '') === 'add') ? 'show' : '' ?>" id="addForm">
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-plus me-1"></i> 새 관리자 추가</div>
    <div class="card-body admin-form">
        <form method="POST" id="addAdminForm">
            <input type="hidden" name="action" value="add">
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
                    <select name="role" class="form-select form-select-sm">
                        <option value="editor">에디터</option>
                        <option value="admin">관리자</option>
                        <option value="viewer">뷰어</option>
                        <?php if ($current_admin['role'] === 'super_admin'): ?>
                        <option value="super_admin">최고관리자</option>
                        <?php endif; ?>
                    </select>
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

<?php if ($edit_admin):
    $ea_role = $edit_admin['role'];
    $ea_has_custom = !empty($edit_perms['pages']);
    $ea_pages = $edit_perms['pages'] ?? [];
    $ea_notifs = $edit_perms['notifications'] ?? null;
    // null이면 기본값 사용
    if ($ea_notifs === null) $ea_notifs = $DEFAULT_NOTIFICATIONS;
    // 역할 기본 페이지 권한
    $role_default_pages = $ADMIN_ROLE_PERMISSIONS[$ea_role] ?? [];
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
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">아이디</label>
                    <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($edit_admin['username']) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label">새 비밀번호</label>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           minlength="6" placeholder="변경 시에만 입력">
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
                    <select name="role" class="form-select form-select-sm" id="editRoleSelect"
                            <?= ($edit_admin['id'] == $current_admin['id']) ? 'disabled' : '' ?>>
                        <option value="editor" <?= $ea_role === 'editor' ? 'selected' : '' ?>>에디터</option>
                        <option value="admin" <?= $ea_role === 'admin' ? 'selected' : '' ?>>관리자</option>
                        <option value="viewer" <?= $ea_role === 'viewer' ? 'selected' : '' ?>>뷰어</option>
                        <?php if ($current_admin['role'] === 'super_admin'): ?>
                        <option value="super_admin" <?= $ea_role === 'super_admin' ? 'selected' : '' ?>>최고관리자</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($edit_admin['id'] == $current_admin['id']): ?>
                    <input type="hidden" name="role" value="<?= $ea_role ?>">
                    <?php endif; ?>
                </div>
            </div>

            <!-- 페이지 접근 권한 -->
            <div class="perm-section">
                <div class="perm-section-title"><i class="bi bi-shield-check me-1"></i> 페이지 접근 권한</div>
                <div class="mb-2">
                    <label class="form-check form-check-inline" style="font-size:13px;">
                        <input class="form-check-input" type="radio" name="custom_pages" value="" id="useRoleDefault"
                            <?= !$ea_has_custom ? 'checked' : '' ?> onchange="togglePageGrid(false)">
                        역할 기본값 사용
                    </label>
                    <label class="form-check form-check-inline" style="font-size:13px;">
                        <input class="form-check-input" type="radio" name="custom_pages" value="1" id="useCustomPages"
                            <?= $ea_has_custom ? 'checked' : '' ?> onchange="togglePageGrid(true)">
                        커스텀 권한 설정
                    </label>
                    <span class="select-btns ms-2" id="pageSelectBtns" style="<?= $ea_has_custom ? '' : 'display:none;' ?>">
                        <a onclick="toggleCheckboxes('editAdminForm', 'pages[]', true)">전체선택</a>
                        <a onclick="toggleCheckboxes('editAdminForm', 'pages[]', false)">전체해제</a>
                    </span>
                </div>
                <div class="perm-grid" id="pageGrid" style="<?= $ea_has_custom ? '' : 'opacity:0.4;pointer-events:none;' ?>">
                    <?php foreach ($ALL_PAGE_PERMISSIONS as $pkey => $plabel):
                        // 커스텀이면 커스텀 값, 아니면 역할 기본값
                        $is_checked = $ea_has_custom
                            ? in_array($pkey, $ea_pages)
                            : (in_array('*', $role_default_pages) || in_array($pkey, $role_default_pages));
                    ?>
                    <label>
                        <input type="checkbox" name="pages[]" value="<?= $pkey ?>" <?= $is_checked ? 'checked' : '' ?>>
                        <?= $plabel ?>
                    </label>
                    <?php endforeach; ?>
                </div>
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

<!-- 역할별 기본 권한 안내 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-shield-check me-1"></i> 역할별 기본 권한</div>
    <div class="card-body p-2">
        <table class="table table-sm mb-0" style="font-size:12px;">
            <thead>
                <tr>
                    <th>역할</th>
                    <th>대시보드</th>
                    <th>후기</th>
                    <th>광고</th>
                    <th>등록마법사</th>
                    <th>메일</th>
                    <th>AI</th>
                    <th>테마</th>
                    <th>회원</th>
                    <th>관리자</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="role-badge role-super_admin">최고관리자</span></td>
                    <td colspan="9" class="text-center text-success fw-bold">모든 권한</td>
                </tr>
                <?php
                $summary_keys = ['dashboard','reviews','ads','hospital_wizard','admin_mail','ai_settings','theme','members','admins'];
                $admin_perms = $ADMIN_ROLE_PERMISSIONS['admin'];
                $editor_perms = $ADMIN_ROLE_PERMISSIONS['editor'];
                ?>
                <tr>
                    <td><span class="role-badge role-admin">관리자</span></td>
                    <?php foreach($summary_keys as $p): ?>
                    <td class="text-center"><?= in_array($p, $admin_perms) ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>' ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td><span class="role-badge role-editor">에디터</span></td>
                    <?php foreach($summary_keys as $p): ?>
                    <td class="text-center"><?= in_array($p, $editor_perms) ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>' ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td><span class="role-badge role-viewer">뷰어</span></td>
                    <?php foreach($summary_keys as $p): ?>
                    <td class="text-center"><?= $p === 'dashboard' ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <small class="text-body-secondary d-block mt-1 px-2">* 수정 화면에서 관리자별 커스텀 권한을 설정할 수 있습니다.</small>
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
                    <th>권한</th>
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
                    $has_custom_pages = !empty($a_perms['pages']);
                ?>
                <tr class="<?= !$admin['is_active'] ? 'table-secondary' : '' ?>">
                    <td><?= $admin['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($admin['username']) ?></strong>
                        <?= ($admin['id'] == $current_admin['id']) ? '<span class="badge bg-info ms-1">나</span>' : '' ?>
                    </td>
                    <td><?= htmlspecialchars($admin['name']) ?></td>
                    <td><span class="role-badge role-<?= $admin['role'] ?>"><?= get_role_label($admin['role']) ?></span></td>
                    <td>
                        <?php if ($admin['role'] === 'super_admin'): ?>
                            <span class="badge bg-danger">전체</span>
                        <?php elseif ($has_custom_pages): ?>
                            <span class="badge bg-warning text-dark">커스텀 <?= count($a_perms['pages']) ?>개</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">기본값</span>
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
                                <button type="submit" class="btn btn-outline-warning" title="<?= $admin['is_active'] ? '비활성화' : '활성화' ?>">
                                    <i class="bi bi-<?= $admin['is_active'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= $admin['id'] ?>">
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

<script>
// 페이지 권한 그리드 토글
function togglePageGrid(custom) {
    var grid = document.getElementById('pageGrid');
    var btns = document.getElementById('pageSelectBtns');
    if (custom) {
        grid.style.opacity = '1';
        grid.style.pointerEvents = 'auto';
        if (btns) btns.style.display = '';
    } else {
        grid.style.opacity = '0.4';
        grid.style.pointerEvents = 'none';
        if (btns) btns.style.display = 'none';
    }
}

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

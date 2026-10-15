<?php
/**
 * 회원 관리 페이지
 * Gnuboard g5_member 기반 + HMS 환자 연동
 * Bootstrap 5.3 + 주간/야간 모드
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!function_exists("term")) { require_once __DIR__ . "/includes/industry_terms.php"; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 + 권한 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('members');
if (is_hospital_role()) {
    http_response_code(403);
    exit('Hospital administrators cannot manage the global member directory.');
}

if (empty($_SESSION['admin_members_csrf'])) $_SESSION['admin_members_csrf'] = bin2hex(random_bytes(32));
$admin_members_csrf = $_SESSION['admin_members_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (empty($_POST['admin_csrf']) || !hash_equals($admin_members_csrf, (string)$_POST['admin_csrf']))) {
    http_response_code(403);
    exit('잘못된 요청입니다. 페이지를 새로고침한 후 다시 시도해주세요.');
}

$message = '';
$error = '';

/**
 * 회원 삭제 시 연관 데이터를 함께 정리한다.
 * 회원 계정은 g5_member 외에도 member_sync(↔HMS users/patients), sso_tokens,
 * 그리고 병원관계자면 gangnamunni_admins 에 흩어져 있어, g5_member 만 지우면
 * 병원 관리자 계정이 남아 로그인되거나 HMS 재동기화로 회원이 되살아난다.
 * HMS users 는 삭제(진료/예약 데이터 FK) 대신 비활성화(status=0)하고 매핑을 끊는다.
 * @param mixed $connect
 * @param mixed $mb_no
 * @param mixed $mb_id
 * @param mixed $mb_email
 */
function member_cleanup_relations($connect, $mb_no, $mb_id, $mb_email) {
    $mb_no    = (int) $mb_no;
    $mb_id_e  = mysqli_real_escape_string($connect, (string) $mb_id);
    $email_e  = mysqli_real_escape_string($connect, (string) $mb_email);

    // 1) HMS user 비활성화(재동기화로 되살아나지 않도록) 후 매핑 제거
    $sync = mysqli_fetch_assoc(mysqli_query($connect, "SELECT hms_user_id FROM member_sync WHERE mb_no = {$mb_no} OR mb_id = '{$mb_id_e}' LIMIT 1"));
    if ($sync && !empty($sync['hms_user_id'])) {
        mysqli_query($connect, "UPDATE users SET status = 0 WHERE id = " . (int) $sync['hms_user_id']);
    }
    mysqli_query($connect, "DELETE FROM member_sync WHERE mb_no = {$mb_no} OR mb_id = '{$mb_id_e}'");

    // 2) SSO 토큰 제거
    mysqli_query($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = '{$mb_id_e}'");

    // 3) 병원관계자 관리자 계정 제거(플랫폼 관리자는 건드리지 않음)
    //    이 회원에 명시적으로 연결(g5_mb_id)된 계정만 — 이메일/아이디 일치만으로는 지우지 않는다.
    $link_col = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_admins LIKE 'g5_mb_id'");
    if ($link_col && mysqli_num_rows($link_col) > 0) {
        mysqli_query($connect, "DELETE FROM gangnamunni_admins WHERE g5_mb_id = '{$mb_id_e}' AND role IN ('hospital_super_admin','hospital_admin','hospital_sub_admin','hospital_staff')");
    }
}

// === POST 처리 ===

// 회원 등급 변경
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_level') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    $new_level = intval($_POST['new_level'] ?? 1);
    if ($mb_no > 0 && $new_level >= 1 && $new_level <= 10) {
        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_level = ? WHERE mb_no = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $new_level, $mb_no);
        if (mysqli_stmt_execute($stmt)) {
            $message = "회원 등급이 {$new_level}로 변경되었습니다.";
        }
        mysqli_stmt_close($stmt);
    }
}

// 회원 메모 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_memo') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    $memo = trim($_POST['memo'] ?? '');
    if ($mb_no > 0) {
        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_memo = ? WHERE mb_no = ?");
        mysqli_stmt_bind_param($stmt, 'si', $memo, $mb_no);
        if (mysqli_stmt_execute($stmt)) {
            $message = "메모가 저장되었습니다.";
        }
        mysqli_stmt_close($stmt);
    }
}

// 회원 차단/해제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    if ($mb_no > 0) {
        $member = mysqli_fetch_assoc(mysqli_query($connect, "SELECT mb_intercept_date FROM g5_member WHERE mb_no = {$mb_no}"));
        if ($member) {
            $new_date = empty($member['mb_intercept_date']) ? date('Ymd') : '';
            $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_intercept_date = ? WHERE mb_no = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_date, $mb_no);
            if (mysqli_stmt_execute($stmt)) {
                $message = empty($new_date) ? '차단이 해제되었습니다.' : '회원이 차단되었습니다.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ============================================================================
// [관리자 #11 - 2026-05-28] 회원 비밀번호 변경 후 앱 로그인 불가 — 동기화 처리
// 원인 : 기존 코드는 Gnuboard g5_member.mb_password (pbkdf2) 만 업데이트.
//        모바일 앱은 HMS Laravel API(/api/login) 사용 → HMS users.password 별도 관리.
//        두 테이블 비밀번호 미동기화 → 앱 로그인 불가.
// 변경 : g5_member 업데이트 후 HMS users 테이블도 같은 비밀번호로 동기화 (이메일 매핑).
//        HMS는 bcrypt 사용 → password_hash($new_pw, PASSWORD_BCRYPT) 적용.
// 영향 범위 : 신규 비밀번호 초기화에만 영향. 기존 회원 영향 없음.
//             g5_member 정상 업데이트는 보존 → 사이트(Gnuboard) 로그인은 그대로 정상.
// ============================================================================
// 회원 비밀번호 초기화 (#11)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    if ($mb_no > 0) {
        $new_pw = substr(str_shuffle('abcdefghijkmnpqrstuvwxyz23456789'), 0, 8);
        // Gnuboard pbkdf2 compatible hashing (사이트 로그인용)
        if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
        require_once __DIR__ . '/../lib/pbkdf2.compat.php';
        $hashed = create_hash($new_pw);
        $stmt = mysqli_prepare($connect, "UPDATE g5_member SET mb_password = ? WHERE mb_no = ?");
        mysqli_stmt_bind_param($stmt, 'si', $hashed, $mb_no);
        $g5_ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // [#11 - 2026-05-28] HMS users 테이블 동기화 (앱 로그인용)
        $hms_synced = false;
        $hms_err = '';
        if ($g5_ok) {
            // 회원 이메일 조회 후 HMS users.email 매칭
            $email_q = mysqli_query($connect, "SELECT mb_email FROM g5_member WHERE mb_no = {$mb_no}");
            $email_row = $email_q ? mysqli_fetch_assoc($email_q) : null;
            $mb_email = $email_row['mb_email'] ?? '';
            if (!empty($mb_email)) {
                // HMS users 테이블 존재 확인
                $hms_check = mysqli_query($connect, "SHOW TABLES LIKE 'users'");
                if ($hms_check && mysqli_num_rows($hms_check) > 0) {
                    // bcrypt 해시 (Laravel password_hash 호환)
                    $bcrypt_hash = password_hash($new_pw, PASSWORD_BCRYPT);
                    $hms_stmt = mysqli_prepare($connect, "UPDATE users SET password = ? WHERE email = ?");
                    if ($hms_stmt) {
                        mysqli_stmt_bind_param($hms_stmt, 'ss', $bcrypt_hash, $mb_email);
                        if (mysqli_stmt_execute($hms_stmt)) {
                            $hms_synced = (mysqli_stmt_affected_rows($hms_stmt) > 0);
                        } else {
                            $hms_err = mysqli_error($connect);
                        }
                        mysqli_stmt_close($hms_stmt);
                    }
                }
            }
        }

        // 초기화된 회원의 기존 자격증명을 모두 폐기한다. 관리자 본인 세션은
        // 유지해야 하므로 keep_current_session 을 쓴다(대상이 관리자 자신이
        // 아닌 한 영향도 없다).
        if ($g5_ok && function_exists('revoke_all_auth_credentials_for')) {
            $target_q = mysqli_query($connect, "SELECT mb_id FROM g5_member WHERE mb_no = {$mb_no}");
            $target_row = $target_q ? mysqli_fetch_assoc($target_q) : null;
            if (!empty($target_row['mb_id'])) {
                revoke_all_auth_credentials_for($target_row['mb_id'], ['keep_current_session' => true]);
            }
        }

        if ($g5_ok) {
            $sync_msg = $hms_synced
                ? '<span class="badge bg-success">사이트 + 앱 동기화 완료</span>'
                : '<span class="badge bg-warning text-dark">사이트만 적용 — 앱 회원 정보 없음 또는 별도 처리 필요</span>';
            $message = "비밀번호가 초기화되었습니다. 임시 비밀번호가 설정되었습니다. 회원에게 별도 전달하세요. {$sync_msg}";
            $message_pw_hint = $new_pw;
        } else {
            $error = '비밀번호 초기화 실패';
        }
    }
}

// 회원 정보 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_member') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    if ($mb_no > 0) {
        $mb_name = trim($_POST['mb_name'] ?? '');
        $mb_nick = trim($_POST['mb_nick'] ?? '');
        $mb_email = trim($_POST['mb_email'] ?? '');
        $mb_hp = trim($_POST['mb_hp'] ?? '');
        $mb_birth = trim($_POST['mb_birth'] ?? '');
        $mb_sex = in_array($_POST['mb_sex'] ?? '', ['M', 'F', '']) ? $_POST['mb_sex'] : '';
        $mb_addr1 = trim($_POST['mb_addr1'] ?? '');
        $mb_addr2 = trim($_POST['mb_addr2'] ?? '');

        $dup_stmt = mysqli_prepare($connect, "SELECT mb_no FROM g5_member WHERE LOWER(mb_email)=LOWER(?) AND mb_no<>? LIMIT 1");
        mysqli_stmt_bind_param($dup_stmt, 'si', $mb_email, $mb_no);
        mysqli_stmt_execute($dup_stmt);
        mysqli_stmt_store_result($dup_stmt);
        $email_exists = mysqli_stmt_num_rows($dup_stmt) > 0;
        mysqli_stmt_close($dup_stmt);
        if ($email_exists) {
            $error = '이미 다른 회원이 사용 중인 이메일입니다.';
        } else {
        $stmt = mysqli_prepare($connect,
            "UPDATE g5_member SET mb_name=?, mb_nick=?, mb_email=?, mb_hp=?, mb_birth=?, mb_sex=?, mb_addr1=?, mb_addr2=? WHERE mb_no=?");
        mysqli_stmt_bind_param($stmt, 'ssssssssi', $mb_name, $mb_nick, $mb_email, $mb_hp, $mb_birth, $mb_sex, $mb_addr1, $mb_addr2, $mb_no);
        if (mysqli_stmt_execute($stmt)) {
            $message = '회원 정보가 수정되었습니다.';
        } else {
            $error = '수정 실패: ' . mysqli_error($connect);
        }
        mysqli_stmt_close($stmt);
        }
    }
}

// 회원 삭제 (단일)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member') {
    $mb_no = intval($_POST['mb_no'] ?? 0);
    if ($mb_no > 0) {
        // 삭제 대상 식별자 조회 후 연관 데이터(병원관리자/SSO/HMS 매핑) 정리
        $m = mysqli_fetch_assoc(mysqli_query($connect, "SELECT mb_id, mb_email FROM g5_member WHERE mb_no = {$mb_no}"));
        if ($m) {
            member_cleanup_relations($connect, $mb_no, $m['mb_id'], $m['mb_email']);
        }
        $stmt = mysqli_prepare($connect, "DELETE FROM g5_member WHERE mb_no = ?");
        mysqli_stmt_bind_param($stmt, 'i', $mb_no);
        if (mysqli_stmt_execute($stmt)) {
            $message = '회원이 삭제되었습니다. (연관 계정·토큰·동기화 매핑 포함)';
            $detail_member = null;
        } else {
            $error = '삭제 실패: ' . mysqli_error($connect);
        }
        mysqli_stmt_close($stmt);
    }
}

// 선택 회원 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = $_POST['selected_ids'] ?? [];
    if (!empty($ids)) {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($v) => $v > 0);
        if (!empty($ids)) {
            $id_list = implode(',', $ids);
            // 각 회원의 연관 데이터(병원관리자/SSO/HMS 매핑) 먼저 정리
            $rows = mysqli_query($connect, "SELECT mb_no, mb_id, mb_email FROM g5_member WHERE mb_no IN ({$id_list})");
            while ($rows && ($m = mysqli_fetch_assoc($rows))) {
                member_cleanup_relations($connect, $m['mb_no'], $m['mb_id'], $m['mb_email']);
            }
            $del_result = mysqli_query($connect, "DELETE FROM g5_member WHERE mb_no IN ({$id_list})");
            $deleted = mysqli_affected_rows($connect);
            $message = "{$deleted}명의 회원이 삭제되었습니다. (연관 계정·토큰·동기화 매핑 포함)";
        }
    }
}

// === 검색/필터 ===
$search = trim($_GET['search'] ?? '');
$filter_level = $_GET['level'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'mb_datetime';
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$valid_sorts = ['mb_no', 'mb_id', 'mb_name', 'mb_level', 'mb_datetime', 'mb_today_login', 'mb_point'];
if (!in_array($sort, $valid_sorts)) $sort = 'mb_datetime';

// 페이지네이션
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// WHERE 조건
$where = '1=1';
$params = [];
$types = '';

if (!empty($search)) {
    $like = '%' . $search . '%';
    $where .= " AND (mb_id LIKE ? OR mb_name LIKE ? OR mb_nick LIKE ? OR mb_email LIKE ? OR mb_hp LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}

if ($filter_level !== '' && is_numeric($filter_level)) {
    $where .= " AND mb_level = ?";
    $params[] = intval($filter_level);
    $types .= 'i';
}

if ($filter_status === 'active') {
    $where .= " AND mb_leave_date = '' AND mb_intercept_date = ''";
} elseif ($filter_status === 'blocked') {
    $where .= " AND mb_intercept_date != ''";
} elseif ($filter_status === 'left') {
    $where .= " AND mb_leave_date != ''";
}

// 총 수
$count_sql = "SELECT COUNT(*) as cnt FROM g5_member WHERE {$where}";
if (!empty($params)) {
    $stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    mysqli_stmt_close($stmt);
} else {
    $total = mysqli_fetch_assoc(mysqli_query($connect, $count_sql))['cnt'];
}

$total_pages = ceil($total / $per_page);

// 회원 목록 조회
$list_sql = "SELECT * FROM g5_member WHERE {$where} ORDER BY {$sort} {$order} LIMIT {$offset}, {$per_page}";
$members = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $members[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($connect, $list_sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $members[] = $row;
    }
}

// 등급별 통계
$level_stats = [];
$level_result = mysqli_query($connect, "SELECT mb_level, COUNT(*) as cnt FROM g5_member GROUP BY mb_level ORDER BY mb_level");
while ($row = mysqli_fetch_assoc($level_result)) {
    $level_stats[$row['mb_level']] = $row['cnt'];
}

// 상세 보기 회원
$detail_member = null;
if (isset($_GET['view'])) {
    $view_no = intval($_GET['view']);
    $detail_member = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_no = {$view_no}"));
}

// 관리자 헤더
$admin_page_title = '회원 관리';
$admin_current = 'members';

$admin_extra_css = '
.member-stat { text-align: center; padding: 12px; border-radius: 8px; }
.member-stat .num { font-size: 24px; font-weight: 700; }
.member-stat .label { font-size: 12px; color: var(--bs-secondary-color); }
.level-badge { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; border-radius: 50%; font-size: 11px; font-weight: 700; }
.level-1,.level-2 { background: #e9ecef; color: #6c757d; }
.level-3,.level-4 { background: #d1ecf1; color: #0c5460; }
.level-5,.level-6 { background: #d4edda; color: #155724; }
.level-7,.level-8 { background: #fff3cd; color: #856404; }
.level-9 { background: #f8d7da; color: #721c24; }
.level-10 { background: #dc3545; color: #fff; }
.sort-link { color: inherit; text-decoration: none; white-space: nowrap; }
.sort-link:hover { color: var(--bs-primary); }
.sort-link .bi { font-size: 10px; }
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($message) ?>
    <?php if (!empty($message_pw_hint)): ?>
    <div class="mt-2 p-2 bg-light rounded border">
        <strong>임시 비밀번호:</strong> <code><?= htmlspecialchars($message_pw_hint) ?></code>
        <span class="text-muted small ms-2">(이 창을 닫으면 다시 확인할 수 없습니다)</span>
    </div>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($detail_member): ?>
<?php $edit_mode = isset($_GET['edit']); ?>
<!-- 회원 상세 -->
<div class="card mb-3 border-primary">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-circle me-1"></i> 회원 <?= $edit_mode ? '수정' : '상세' ?>: <?= htmlspecialchars($detail_member['mb_name']) ?> (<?= htmlspecialchars($detail_member['mb_id']) ?>)</span>
        <div class="d-flex gap-1">
            <?php if (!$edit_mode): ?>
            <a href="?view=<?= $detail_member['mb_no'] ?>&edit=1&<?= http_build_query(array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'page' => $page])) ?>"
               class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i> 편집</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('정말 이 회원을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')">
                <input type="hidden" name="action" value="delete_member">
                <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> 삭제</button>
            </form>
            <?php endif; ?>
            <a href="admin_members.php?<?= http_build_query(array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'page' => $page])) ?>" class="btn btn-sm btn-light">닫기</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($edit_mode): ?>
        <!-- 편집 모드 -->
        <form method="POST">
            <input type="hidden" name="action" value="edit_member">
            <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><th style="width:120px">회원번호</th><td><?= $detail_member['mb_no'] ?></td></tr>
                        <tr><th>아이디</th><td><strong><?= htmlspecialchars($detail_member['mb_id']) ?></strong> <small class="text-muted">(변경불가)</small></td></tr>
                        <tr><th>이름 <span class="text-danger">*</span></th><td><input type="text" name="mb_name" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_name']) ?>" required></td></tr>
                        <tr><th>닉네임</th><td><input type="text" name="mb_nick" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_nick']) ?>"></td></tr>
                        <tr><th>이메일</th><td><input type="email" name="mb_email" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_email']) ?>"></td></tr>
                        <tr><th>전화번호</th><td><input type="text" name="mb_hp" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_hp']) ?>"></td></tr>
                        <tr><th>성별</th><td>
                            <select name="mb_sex" class="form-select form-select-sm">
                                <option value="" <?= $detail_member['mb_sex'] === '' ? 'selected' : '' ?>>미설정</option>
                                <option value="M" <?= $detail_member['mb_sex'] === 'M' ? 'selected' : '' ?>>남</option>
                                <option value="F" <?= $detail_member['mb_sex'] === 'F' ? 'selected' : '' ?>>여</option>
                            </select>
                        </td></tr>
                        <tr><th>생년월일</th><td><input type="text" name="mb_birth" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_birth']) ?>" placeholder="YYYY-MM-DD"></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><th style="width:120px">주소1</th><td><input type="text" name="mb_addr1" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_addr1']) ?>"></td></tr>
                        <tr><th>주소2</th><td><input type="text" name="mb_addr2" class="form-control form-control-sm" value="<?= htmlspecialchars($detail_member['mb_addr2']) ?>"></td></tr>
                        <tr><th>등급</th><td><span class="level-badge level-<?= $detail_member['mb_level'] ?>"><?= $detail_member['mb_level'] ?></span> <small class="text-muted">등급<?= $detail_member['mb_level'] ?></small></td></tr>
                        <tr><th>포인트</th><td><?= number_format($detail_member['mb_point']) ?>P</td></tr>
                        <tr><th>가입일</th><td><?= $detail_member['mb_datetime'] ?></td></tr>
                        <tr><th>최종로그인</th><td><?= $detail_member['mb_today_login'] ?: '-' ?></td></tr>
                        <tr><th>접속IP</th><td><?= htmlspecialchars($detail_member['mb_login_ip'] ?: '-') ?></td></tr>
                    </table>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                    <a href="?view=<?= $detail_member['mb_no'] ?>&<?= http_build_query(array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'page' => $page])) ?>"
                       class="btn btn-sm btn-outline-secondary">취소</a>
                </div>
            </div>
        </form>
        <?php else: ?>
        <!-- 보기 모드 -->
        <div class="row g-3">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th style="width:120px">회원번호</th><td><?= $detail_member['mb_no'] ?></td></tr>
                    <tr><th>아이디</th><td><strong><?= htmlspecialchars($detail_member['mb_id']) ?></strong></td></tr>
                    <tr><th>이름</th><td><?= htmlspecialchars($detail_member['mb_name']) ?></td></tr>
                    <tr><th>닉네임</th><td><?= htmlspecialchars($detail_member['mb_nick']) ?></td></tr>
                    <tr><th>이메일</th><td><?= htmlspecialchars($detail_member['mb_email']) ?></td></tr>
                    <tr><th>전화번호</th><td><?= htmlspecialchars($detail_member['mb_hp'] ?: $detail_member['mb_tel'] ?: '-') ?></td></tr>
                    <tr><th>성별</th><td><?= $detail_member['mb_sex'] === 'M' ? '남' : ($detail_member['mb_sex'] === 'F' ? '여' : '-') ?></td></tr>
                    <tr><th>생년월일</th><td><?= htmlspecialchars($detail_member['mb_birth'] ?: '-') ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th style="width:120px">등급</th>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="change_level">
                                <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                                <span class="level-badge level-<?= $detail_member['mb_level'] ?>"><?= $detail_member['mb_level'] ?></span>
                                <select name="new_level" class="form-select form-select-sm d-inline-block" style="width:100px" onchange="this.form.submit()">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= $detail_member['mb_level'] == $i ? 'selected' : '' ?>>등급<?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <tr><th>포인트</th><td><?= number_format($detail_member['mb_point']) ?>P</td></tr>
                    <tr><th>가입일</th><td><?= $detail_member['mb_datetime'] ?></td></tr>
                    <tr><th>최종로그인</th><td><?= $detail_member['mb_today_login'] ?: '-' ?></td></tr>
                    <tr><th>접속IP</th><td><?= htmlspecialchars($detail_member['mb_login_ip'] ?: '-') ?></td></tr>
                    <tr><th>주소</th><td><?= htmlspecialchars(trim($detail_member['mb_addr1'] . ' ' . $detail_member['mb_addr2']) ?: '-') ?></td></tr>
                    <tr>
                        <th>상태</th>
                        <td>
                            <?php if ($detail_member['mb_leave_date']): ?>
                                <span class="badge bg-secondary">탈퇴 (<?= $detail_member['mb_leave_date'] ?>)</span>
                            <?php elseif ($detail_member['mb_intercept_date']): ?>
                                <span class="badge bg-danger">차단 (<?= $detail_member['mb_intercept_date'] ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-success">활성</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>SMS/메일</th>
                        <td>
                            SMS: <?= $detail_member['mb_sms'] ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>' ?>
                            메일: <?= $detail_member['mb_mailling'] ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>' ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-12">
                <form method="POST">
                    <input type="hidden" name="action" value="save_memo">
                    <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                    <label class="form-label fw-bold">관리자 메모</label>
                    <textarea name="memo" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($detail_member['mb_memo'] ?? '') ?></textarea>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>메모 저장</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('비밀번호를 초기화하시겠습니까?\n새 임시 비밀번호가 생성됩니다.')">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-info"><i class="bi bi-key me-1"></i>비밀번호 초기화</button>
                        </form>
                        <?php if (empty($detail_member['mb_intercept_date'])): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('이 회원을 차단하시겠습니까?')">
                            <input type="hidden" name="action" value="toggle_block">
                            <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle me-1"></i>차단</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_block">
                            <input type="hidden" name="mb_no" value="<?= $detail_member['mb_no'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check-circle me-1"></i>차단 해제</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 d-inline"><i class="bi bi-person-lines-fill me-2"></i><?= term("member") ?> 관리</h4>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#levelGuideModal" title="등급 안내"><i class="bi bi-info-circle"></i> 등급 안내</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#memberPatientModal" title="회원/환자 차이"><i class="bi bi-question-circle"></i> <?= term("member") ?>/환자 차이</button>
    </div>
    <span class="text-muted">총 <?= number_format($total) ?>명</span>
</div>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col">
        <div class="member-stat bg-body-secondary rounded">
            <div class="num"><?= number_format($total) ?></div>
            <div class="label">전체 회원</div>
        </div>
    </div>
    <?php
    $active_count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE mb_leave_date = '' AND mb_intercept_date = ''"))['c'];
    $blocked_count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE mb_intercept_date != ''"))['c'];
    $today_login = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE DATE(mb_today_login) = CURDATE()"))['c'];
    $today_join = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE DATE(mb_datetime) = CURDATE()"))['c'];
    ?>
    <div class="col">
        <div class="member-stat bg-body-secondary rounded">
            <div class="num text-success"><?= number_format($active_count) ?></div>
            <div class="label">활성 회원</div>
        </div>
    </div>
    <div class="col">
        <div class="member-stat bg-body-secondary rounded">
            <div class="num text-danger"><?= number_format($blocked_count) ?></div>
            <div class="label">차단 회원</div>
        </div>
    </div>
    <div class="col">
        <div class="member-stat bg-body-secondary rounded">
            <div class="num text-primary"><?= number_format($today_login) ?></div>
            <div class="label">오늘 접속</div>
        </div>
    </div>
    <div class="col">
        <div class="member-stat bg-body-secondary rounded">
            <div class="num text-info"><?= number_format($today_join) ?></div>
            <div class="label">오늘 가입</div>
        </div>
    </div>
</div>

<!-- 검색/필터 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">검색</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="이름, 아이디, 이메일, 전화번호" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">등급</label>
                <select name="level" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>" <?= $filter_level == $i ? 'selected' : '' ?>>등급<?= $i ?> (<?= $level_stats[$i] ?? 0 ?>)</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>전체</option>
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>활성</option>
                    <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>차단</option>
                    <option value="left" <?= $filter_status === 'left' ? 'selected' : '' ?>>탈퇴</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">행수</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $per_page == $pp ? 'selected' : '' ?>><?= $pp ?>개</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>검색</button>
            </div>
        </form>
    </div>
</div>

<!-- 일괄 작업 바 -->
<div class="alert alert-warning d-flex justify-content-between align-items-center py-2 mb-2" id="bulkActions" style="display:none !important;">
    <span><strong id="selectedCount">0</strong>명 선택됨</span>
    <div>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()"><i class="bi bi-trash me-1"></i>선택 삭제</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSelectAll(false)"><i class="bi bi-x-lg me-1"></i>선택 해제</button>
    </div>
</div>

<!-- 회원 목록 -->
<form id="bulkForm" method="POST">
<input type="hidden" name="action" value="bulk_delete">
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="memberTable">
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll(this.checked)"></th>
                    <?php
                    $sort_params = array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'per_page' => $per_page]);
                    /**
                     * @param mixed $col
                     * @param mixed $currentSort
                     * @param mixed $currentOrder
                     * @param mixed $params
                     */
                    function sortUrl($col, $currentSort, $currentOrder, $params) {
                        $newOrder = ($currentSort === $col && $currentOrder === 'DESC') ? 'asc' : 'desc';
                        return '?' . http_build_query(array_merge($params, ['sort' => $col, 'order' => $newOrder, 'page' => 1]));
                    }
                    /**
                     * @param mixed $col
                     * @param mixed $currentSort
                     * @param mixed $currentOrder
                     */
                    function sortIcon($col, $currentSort, $currentOrder) {
                        if ($currentSort !== $col) return '';
                        return $currentOrder === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
                    }
                    ?>
                    <th><a class="sort-link" href="<?= sortUrl('mb_no', $sort, $order, $sort_params) ?>">#<?= sortIcon('mb_no', $sort, $order) ?></a></th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_id', $sort, $order, $sort_params) ?>">아이디<?= sortIcon('mb_id', $sort, $order) ?></a></th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_name', $sort, $order, $sort_params) ?>">이름<?= sortIcon('mb_name', $sort, $order) ?></a></th>
                    <th>닉네임</th>
                    <th>이메일</th>
                    <th>전화번호</th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_level', $sort, $order, $sort_params) ?>">등급<?= sortIcon('mb_level', $sort, $order) ?></a></th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_point', $sort, $order, $sort_params) ?>">포인트<?= sortIcon('mb_point', $sort, $order) ?></a></th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_datetime', $sort, $order, $sort_params) ?>">가입일<?= sortIcon('mb_datetime', $sort, $order) ?></a></th>
                    <th><a class="sort-link" href="<?= sortUrl('mb_today_login', $sort, $order, $sort_params) ?>">최종접속<?= sortIcon('mb_today_login', $sort, $order) ?></a></th>
                    <th>상태</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr><td colspan="13" class="text-center text-muted py-4">
                    <i class="bi bi-people" style="font-size:32px;"></i><br>
                    <?= $search ? '검색 결과가 없습니다.' : '등록된 회원이 없습니다.' ?>
                </td></tr>
                <?php else: ?>
                <?php foreach ($members as $m): ?>
                <tr class="<?= !empty($m['mb_intercept_date']) ? 'table-danger' : (!empty($m['mb_leave_date']) ? 'table-secondary' : '') ?>">
                    <td><input type="checkbox" class="form-check-input row-check" name="selected_ids[]" value="<?= $m['mb_no'] ?>" onchange="updateBulkBar()"></td>
                    <td><?= $m['mb_no'] ?></td>
                    <td><strong><?= htmlspecialchars($m['mb_id']) ?></strong></td>
                    <td><?= htmlspecialchars($m['mb_name']) ?></td>
                    <td><?= htmlspecialchars($m['mb_nick']) ?></td>
                    <td><small><?= htmlspecialchars($m['mb_email'] ?: '-') ?></small></td>
                    <td><small><?= htmlspecialchars($m['mb_hp'] ?: '-') ?></small></td>
                    <td><span class="level-badge level-<?= $m['mb_level'] ?>"><?= $m['mb_level'] ?></span> <small class="text-muted">등급<?= $m['mb_level'] ?></small></td>
                    <td><?= number_format($m['mb_point']) ?></td>
                    <td><small><?= $m['mb_datetime'] ? date('Y-m-d', strtotime($m['mb_datetime'])) : '-' ?></small></td>
                    <td><small><?= ($m['mb_today_login'] && $m['mb_today_login'] !== '0000-00-00 00:00:00') ? date('m-d H:i', strtotime($m['mb_today_login'])) : '-' ?></small></td>
                    <td>
                        <?php if (!empty($m['mb_leave_date'])): ?>
                            <span class="badge bg-secondary">탈퇴</span>
                        <?php elseif (!empty($m['mb_intercept_date'])): ?>
                            <span class="badge bg-danger">차단</span>
                        <?php else: ?>
                            <span class="badge bg-success">활성</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="?view=<?= $m['mb_no'] ?>&<?= http_build_query(array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'page' => $page])) ?>"
                           class="btn btn-outline-primary btn-sm py-0" title="상세"><i class="bi bi-eye"></i></a>
                        <a href="?view=<?= $m['mb_no'] ?>&edit=1&<?= http_build_query(array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'page' => $page])) ?>"
                           class="btn btn-outline-warning btn-sm py-0" title="편집"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</form>

    <?php if ($total_pages > 1): ?>
    <!-- 페이지네이션 -->
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= number_format($offset + 1) ?>~<?= number_format(min($offset + $per_page, $total)) ?> / <?= number_format($total) ?>명</small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $page_params = array_filter(['search' => $search, 'level' => $filter_level, 'status' => $filter_status, 'per_page' => $per_page, 'sort' => $sort, 'order' => strtolower($order)]);
                $start_page = max(1, $page - 4);
                $end_page = min($total_pages, $page + 4);
                ?>
                <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($page_params, ['page' => $page - 1])) ?>">&laquo;</a></li>
                <?php endif; ?>
                <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($page_params, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($page_params, ['page' => $page + 1])) ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- 등급 안내 모달 (#12) -->
<div class="modal fade" id="levelGuideModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 bg-primary text-white">
                <h6 class="modal-title"><i class="bi bi-award me-1"></i> <?= term("member") ?> 등급 안내</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr><th>등급</th><th>명칭</th><th>권한/혜택</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><span class="level-badge level-1">1</span></td><td>일반 회원</td><td>기본 조회, 상담 신청</td></tr>
                        <tr><td><span class="level-badge level-2">2</span></td><td>인증 회원</td><td>본인인증 완료, 후기 작성</td></tr>
                        <tr><td><span class="level-badge level-3">3</span></td><td>활동 회원</td><td>후기 3건 이상, 이벤트 참여</td></tr>
                        <tr><td><span class="level-badge level-5">5</span></td><td>우수 회원</td><td>포인트 적립률 150%, 우선 상담</td></tr>
                        <tr><td><span class="level-badge level-7">7</span></td><td>VIP 회원</td><td>전용 혜택, 포인트 200%</td></tr>
                        <tr><td><span class="level-badge level-9">9</span></td><td>프리미엄</td><td>전체 콘텐츠 접근, 1:1 상담</td></tr>
                        <tr><td><span class="level-badge level-10">10</span></td><td>관리자</td><td>관리자 권한 (시스템 설정)</td></tr>
                    </tbody>
                </table>
                <div class="alert alert-info mt-2 mb-0 py-2 small">
                    <i class="bi bi-info-circle me-1"></i> 등급은 <?= term("member") ?> 상세 페이지에서 직접 변경할 수 있습니다.
                    등급 4, 6, 8은 자유롭게 커스텀 용도로 사용 가능합니다.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 회원/환자 차이 모달 (#13) -->
<div class="modal fade" id="memberPatientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 bg-info text-white">
                <h6 class="modal-title"><i class="bi bi-question-circle me-1"></i> <?= term("member") ?>와 환자의 차이</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white py-1"><i class="bi bi-person me-1"></i> <?= term("member") ?> (g5_member)</div>
                            <div class="card-body small">
                                <ul class="mb-0 ps-3">
                                    <li>앱/웹 <b>회원가입</b>한 사용자</li>
                                    <li>아이디/비밀번호로 로그인</li>
                                    <li>후기 작성, 이벤트 참여, 상담 신청</li>
                                    <li>포인트 적립, 등급 관리</li>
                                    <li>Gnuboard 회원 시스템 기반</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success text-white py-1"><i class="bi bi-clipboard2-pulse me-1"></i> 환자 (patients)</div>
                            <div class="card-body small">
                                <ul class="mb-0 ps-3">
                                    <li>병원에서 <b>진료 등록</b>된 환자</li>
                                    <li>환자번호, 진료기록 보유</li>
                                    <li>예약, 진료, 처방, 수납 관리</li>
                                    <li>병원 EMR/HMS 시스템 연동</li>
                                    <li>InfyHMS 환자 시스템 기반</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                    <i class="bi bi-link-45deg me-1"></i> <b>연동</b>: <?= term("member") ?>이 병원에서 진료를 받으면 환자 레코드가 생성되어 <code>member_sync</code> 테이블로 연결됩니다.
                    한 <?= term("member") ?>은 여러 병원의 환자일 수 있습니다.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function(form) {
    if (!form.querySelector('input[name="admin_csrf"]')) {
        var csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = 'admin_csrf';
        csrf.value = 'CSRF_TOKEN_PLACEHOLDER'; form.appendChild(csrf);
    }
});
// 전체 선택/해제
function toggleSelectAll(checked) {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = checked; });
    document.getElementById('selectAll').checked = checked;
    updateBulkBar();
}

// 일괄 작업 바 업데이트
function updateBulkBar() {
    var count = document.querySelectorAll('.row-check:checked').length;
    var bar = document.getElementById('bulkActions');
    bar.style.display = count > 0 ? 'flex' : 'none';
    bar.style.setProperty('display', count > 0 ? 'flex' : 'none', 'important');
    document.getElementById('selectedCount').textContent = count;
    // selectAll 체크 상태 동기화
    var total = document.querySelectorAll('.row-check').length;
    document.getElementById('selectAll').checked = (count === total && total > 0);
}

// 일괄 삭제
function bulkDelete() {
    var count = document.querySelectorAll('.row-check:checked').length;
    if (count === 0) return;
    if (!confirm(count + '명의 회원을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) return;
    document.getElementById('bulkForm').submit();
}
JSEOF;
$admin_extra_js = str_replace('CSRF_TOKEN_PLACEHOLDER', htmlspecialchars($admin_members_csrf, ENT_QUOTES), $admin_extra_js);

include __DIR__ . '/includes/admin_footer.php';
?>

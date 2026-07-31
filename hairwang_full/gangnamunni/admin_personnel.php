<?php
/**
 * 인력 통합 관리 페이지
 * 간호사, 약사, 접수직원, 회계사, 검사기사, 케이스핸들러
 * Bootstrap 5.3 + 관리자 레이아웃
 */

session_start();

$admin_page_title = '인력 관리';
$admin_page_description = '간호사, 약사, 접수직원, 회계사, 검사기사, 케이스핸들러를 통합 관리합니다.';
$admin_current = 'personnel';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

// CSRF 보호
if (empty($_SESSION['admin_personnel_csrf'])) {
    $_SESSION['admin_personnel_csrf'] = bin2hex(random_bytes(32));
}
$admin_personnel_csrf = (string)$_SESSION['admin_personnel_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = (string)($_POST['admin_csrf'] ?? '');
    if ($posted_csrf === '' || !hash_equals($admin_personnel_csrf, $posted_csrf)) {
        http_response_code(403);
        exit('잘못된 요청입니다. 페이지를 새로고침한 후 다시 시도해주세요.');
    }
}

// super_admin 외에는 자신의 병원과 연결된 HMS tenant 인력만 접근 가능
$allowed_hospital_ids = get_strict_admin_hospital_ids($connect);
$allowed_tenant_ids = [];
if ($allowed_hospital_ids !== null && !empty($allowed_hospital_ids)) {
    $hospital_id_list = implode(',', array_map('intval', $allowed_hospital_ids));

    $tenant_result = mysqli_query(
        $connect,
        "SELECT hms_hospital_id AS tenant_id
         FROM gangnamunni_hospitals
         WHERE id IN ({$hospital_id_list})
           AND hms_hospital_id IS NOT NULL
           AND hms_hospital_id <> ''"
    );
    while ($tenant_result && $tenant_row = mysqli_fetch_assoc($tenant_result)) {
        $allowed_tenant_ids[] = (string)$tenant_row['tenant_id'];
    }

    // 구형 매핑 테이블도 함께 지원
    $mapping_result = mysqli_query(
        $connect,
        "SELECT hms_tenant_id AS tenant_id
         FROM gangnamunni_hospital_mapping
         WHERE hospital_id IN ({$hospital_id_list})
           AND hms_tenant_id IS NOT NULL
           AND hms_tenant_id <> ''"
    );
    while ($mapping_result && $mapping_row = mysqli_fetch_assoc($mapping_result)) {
        $allowed_tenant_ids[] = (string)$mapping_row['tenant_id'];
    }
}
$allowed_tenant_ids = array_values(array_unique($allowed_tenant_ids));

$tenant_scope = static function (string $alias = '') use ($connect, $allowed_hospital_ids, $allowed_tenant_ids): string {
    if ($allowed_hospital_ids === null) {
        return '1=1';
    }
    if (empty($allowed_tenant_ids)) {
        return '1=0';
    }

    $column = $alias !== '' ? "{$alias}.tenant_id" : 'tenant_id';
    $quoted_ids = array_map(
        static fn(string $tenant_id): string => "'" . mysqli_real_escape_string($connect, $tenant_id) . "'",
        $allowed_tenant_ids
    );
    return "{$column} IN (" . implode(',', $quoted_ids) . ')';
};

// 인력 유형 정의
$personnel_types = [
    'nurse'          => ['table' => 'nurses',          'label' => '간호사',       'icon' => 'bi-heart-pulse', 'color' => '#ec4899', 'role_id' => 4],
    'pharmacist'     => ['table' => 'pharmacists',     'label' => '약사',         'icon' => 'bi-capsule',     'color' => '#8b5cf6', 'role_id' => 6],
    'receptionist'   => ['table' => 'receptionists',   'label' => '접수직원',     'icon' => 'bi-person-badge','color' => '#3b82f6', 'role_id' => 5],
    'accountant'     => ['table' => 'accountants',     'label' => '회계사',       'icon' => 'bi-calculator',  'color' => '#10b981', 'role_id' => 7],
    'lab_technician' => ['table' => 'lab_technicians', 'label' => '검사기사',     'icon' => 'bi-eyedropper',  'color' => '#f59e0b', 'role_id' => 9],
    'case_handler'   => ['table' => 'case_handlers',   'label' => '케이스핸들러', 'icon' => 'bi-folder2-open','color' => '#6366f1', 'role_id' => 8],
];

// 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['person_id'] ?? 0);
    $del_type = $_POST['person_type'] ?? '';
    if ($del_id > 0 && isset($personnel_types[$del_type])) {
        $tbl = $personnel_types[$del_type]['table'];
        // 서버에서도 병원 소유권을 다시 확인
        $r = mysqli_query($connect, "SELECT user_id, tenant_id FROM {$tbl} WHERE id = {$del_id} AND " . $tenant_scope());
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $uid = intval($row['user_id']);
            mysqli_query($connect, "DELETE FROM {$tbl} WHERE id = {$del_id} AND " . $tenant_scope());
            mysqli_query($connect, "DELETE FROM addresses WHERE owner_id = {$del_id} AND owner_type LIKE '%{$del_type}%'");
            // user는 삭제하지 않음 (다른 역할이 있을 수 있음)
        } else {
            http_response_code(403);
            exit('이 인력을 수정하거나 삭제할 권한이 없습니다.');
        }
        header("Location: admin_personnel.php?type={$del_type}&msg=deleted");
        exit;
    }
}

// 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $upd_id = intval($_POST['person_id'] ?? 0);
    $upd_type = $_POST['person_type'] ?? '';
    if ($upd_id > 0 && isset($personnel_types[$upd_type])) {
        $tbl = $personnel_types[$upd_type]['table'];
        $r = mysqli_query($connect, "SELECT user_id, tenant_id FROM {$tbl} WHERE id = {$upd_id} AND " . $tenant_scope());
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $uid = intval($row['user_id']);
            $user_tenant = mysqli_real_escape_string($connect, (string)$row['tenant_id']);
            $first = mysqli_real_escape_string($connect, $_POST['first_name'] ?? '');
            $last = mysqli_real_escape_string($connect, $_POST['last_name'] ?? '');
            $email = mysqli_real_escape_string($connect, $_POST['email'] ?? '');
            $phone = mysqli_real_escape_string($connect, $_POST['phone'] ?? '');
            $qual = mysqli_real_escape_string($connect, $_POST['qualification'] ?? '');
            $status = intval($_POST['status'] ?? 1) === 1 ? 1 : 0;

            mysqli_query($connect, "UPDATE users SET first_name='{$first}', last_name='{$last}', email='{$email}', phone='{$phone}', status={$status} WHERE id={$uid} AND tenant_id='{$user_tenant}'");
            mysqli_query($connect, "UPDATE {$tbl} SET qualification='{$qual}', updated_at=NOW() WHERE id={$upd_id} AND " . $tenant_scope());
        } else {
            http_response_code(403);
            exit('이 인력을 수정하거나 삭제할 권한이 없습니다.');
        }
        header("Location: admin_personnel.php?type={$upd_type}&msg=updated");
        exit;
    }
}

// 현재 탭
$current_type = $_GET['type'] ?? 'nurse';
if (!isset($personnel_types[$current_type])) $current_type = 'nurse';
$type_info = $personnel_types[$current_type];

// 검색
$search = trim($_GET['search'] ?? '');
$where = $tenant_scope('p');
if ($search) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (u.first_name LIKE '%{$s}%' OR u.last_name LIKE '%{$s}%' OR u.email LIKE '%{$s}%' OR u.phone LIKE '%{$s}%')";
}

// 통계
$stats = [];
foreach ($personnel_types as $key => $info) {
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM {$info['table']} WHERE " . $tenant_scope());
    $stats[$key] = ($r && $row = mysqli_fetch_assoc($r)) ? intval($row['c']) : 0;
}
$total_all = array_sum($stats);

// 페이징
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_r = mysqli_query($connect, "SELECT COUNT(*) as c FROM {$type_info['table']} p JOIN users u ON p.user_id = u.id WHERE {$where}");
$total_count = ($count_r && $row = mysqli_fetch_assoc($count_r)) ? intval($row['c']) : 0;
$total_pages = max(1, ceil($total_count / $per_page));

// 데이터 조회
$sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.gender, u.status as user_status,
        u.created_at as user_created
        FROM {$type_info['table']} p
        JOIN users u ON p.user_id = u.id
        WHERE {$where}
        ORDER BY p.id DESC
        LIMIT {$per_page} OFFSET {$offset}";
$result = mysqli_query($connect, $sql);

include __DIR__ . '/includes/admin_header.php';
?>

<style>
.type-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:10px; text-decoration:none; font-size:0.85rem; font-weight:600; border:1px solid var(--bs-border-color); color:var(--bs-body-color); transition:all 0.2s; }
.type-tab:hover { transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.type-tab.active { color:#fff; border-color:transparent; }
.stat-badge { font-size:0.75rem; padding:2px 8px; border-radius:20px; background:rgba(255,255,255,0.25); }
</style>

<!-- 상단 통계 -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> 인력 통합 관리 <span class="badge bg-secondary"><?= $total_all ?>명</span></h5>
</div>

<!-- 유형 탭 -->
<div class="d-flex flex-wrap gap-2 mb-3">
<?php foreach ($personnel_types as $key => $info): ?>
    <a href="?type=<?= $key ?>" class="type-tab <?= $key === $current_type ? 'active' : '' ?>"
       style="<?= $key === $current_type ? "background:{$info['color']};color:#fff;" : '' ?>">
        <i class="bi <?= $info['icon'] ?>"></i>
        <?= $info['label'] ?>
        <span class="stat-badge"><?= $stats[$key] ?></span>
    </a>
<?php endforeach; ?>
</div>

<?php if (!empty($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?= $_GET['msg'] === 'deleted' ? '삭제되었습니다.' : '수정되었습니다.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 검색 -->
<form class="d-flex gap-2 mb-3" method="get">
    <input type="hidden" name="type" value="<?= $current_type ?>">
    <input type="text" name="search" class="form-control form-control-sm" style="max-width:300px;" placeholder="이름, 이메일, 전화번호 검색..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> 검색</button>
    <?php if ($search): ?>
    <a href="?type=<?= $current_type ?>" class="btn btn-sm btn-outline-secondary">초기화</a>
    <?php endif; ?>
</form>

<!-- 현재 유형 제목 -->
<div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi <?= $type_info['icon'] ?>" style="font-size:1.4rem;color:<?= $type_info['color'] ?>;"></i>
    <h6 class="mb-0"><?= $type_info['label'] ?> 목록 <span class="text-muted">(<?= $total_count ?>명)</span></h6>
    <a href="admin_<?= $current_type === 'receptionist' || $current_type === 'accountant' || $current_type === 'lab_technician' || $current_type === 'case_handler' ? 'staff' : $current_type ?>_wizard.php" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-plus-lg"></i> 신규 등록</a>
</div>

<!-- 테이블 -->
<div class="table-responsive">
<table class="table table-hover table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th width="50">#</th>
            <th>이름</th>
            <th>이메일</th>
            <th>전화번호</th>
            <th>자격/전공</th>
            <th>상태</th>
            <th>등록일</th>
            <th width="120">관리</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td class="text-muted"><?= $row['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? '')) ?></strong>
                <?php if (($row['gender'] ?? '') === 'male'): ?>
                <span class="badge bg-primary-subtle text-primary" style="font-size:0.65rem;">남</span>
                <?php elseif (($row['gender'] ?? '') === 'female'): ?>
                <span class="badge bg-danger-subtle text-danger" style="font-size:0.65rem;">여</span>
                <?php endif; ?>
            </td>
            <td><small><?= htmlspecialchars($row['email'] ?? '-') ?></small></td>
            <td><small><?= htmlspecialchars($row['phone'] ?? '-') ?></small></td>
            <td><small class="text-muted"><?= htmlspecialchars($row['qualification'] ?? '-') ?></small></td>
            <td>
                <?php if (($row['user_status'] ?? 1) == 1): ?>
                <span class="badge bg-success-subtle text-success">활성</span>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">비활성</span>
                <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= date('Y-m-d', strtotime($row['created_at'] ?? $row['user_created'] ?? 'now')) ?></small></td>
            <td>
                <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='editPerson(<?= json_encode($row, JSON_UNESCAPED_UNICODE) ?>)' title="수정"><i class="bi bi-pencil"></i></button>
                <form method="post" style="display:inline;" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                    <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($admin_personnel_csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="person_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="person_type" value="<?= $current_type ?>">
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" title="삭제"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">
            <i class="bi <?= $type_info['icon'] ?>" style="font-size:2rem;"></i><br>
            등록된 <?= $type_info['label'] ?>이(가) 없습니다.
        </td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- 페이징 -->
<?php if ($total_pages > 1): ?>
<nav>
<ul class="pagination pagination-sm justify-content-center">
    <?php if ($page > 1): ?>
    <li class="page-item"><a class="page-link" href="?type=<?= $current_type ?>&page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">&laquo;</a></li>
    <?php endif; ?>
    <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?type=<?= $current_type ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
    <li class="page-item"><a class="page-link" href="?type=<?= $current_type ?>&page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">&raquo;</a></li>
    <?php endif; ?>
</ul>
</nav>
<?php endif; ?>

<!-- 수정 모달 -->
<div class="modal fade" id="editPersonModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($admin_personnel_csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="person_id" id="ep_id">
        <input type="hidden" name="person_type" value="<?= $current_type ?>">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil"></i> <?= $type_info['label'] ?> 정보 수정</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label">성</label>
                    <input type="text" name="last_name" id="ep_last" class="form-control form-control-sm">
                </div>
                <div class="col-6">
                    <label class="form-label">이름</label>
                    <input type="text" name="first_name" id="ep_first" class="form-control form-control-sm">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">이메일</label>
                <input type="email" name="email" id="ep_email" class="form-control form-control-sm">
            </div>
            <div class="mb-3">
                <label class="form-label">전화번호</label>
                <input type="text" name="phone" id="ep_phone" class="form-control form-control-sm">
            </div>
            <div class="mb-3">
                <label class="form-label">자격/전공</label>
                <input type="text" name="qualification" id="ep_qual" class="form-control form-control-sm">
            </div>
            <div class="mb-3">
                <label class="form-label">상태</label>
                <select name="status" id="ep_status" class="form-select form-select-sm">
                    <option value="1">활성</option>
                    <option value="0">비활성</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
            <button type="submit" class="btn btn-primary btn-sm">저장</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editPerson(p) {
    document.getElementById('ep_id').value = p.id;
    document.getElementById('ep_last').value = p.last_name || '';
    document.getElementById('ep_first').value = p.first_name || '';
    document.getElementById('ep_email').value = p.email || '';
    document.getElementById('ep_phone').value = p.phone || '';
    document.getElementById('ep_qual').value = p.qualification || '';
    document.getElementById('ep_status').value = (p.user_status || 1).toString();
    new bootstrap.Modal(document.getElementById('editPersonModal')).show();
}
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

<?php
/**
 * 직원 관리 페이지 (접수, 회계, 검사, 케이스핸들러)
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */
session_start();

$admin_page_title = '직원 관리';
$admin_current = 'staffs';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('staffs');

// 직원 유형별 테이블
$staff_types = [
    'receptionist' => ['table' => 'receptionists', 'label' => '접수'],
    'accountant' => ['table' => 'accountants', 'label' => '회계'],
    'lab_technician' => ['table' => 'lab_technicians', 'label' => '검사'],
    'case_handler' => ['table' => 'case_handlers', 'label' => '케이스'],
];

$filter_type = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 통계
$stats = [];
$grand_total = 0;
foreach ($staff_types as $key => $info) {
    $cr = mysqli_query($connect, "SELECT COUNT(*) as c FROM {$info['table']}");
    $cnt = $cr ? (int)mysqli_fetch_assoc($cr)['c'] : 0;
    $stats[$key] = $cnt;
    $grand_total += $cnt;
}

// 목록 조회 (UNION ALL from all staff tables)
$items = [];
$total = 0;

if (!empty($filter_type) && isset($staff_types[$filter_type])) {
    $tables = [$filter_type => $staff_types[$filter_type]];
} else {
    $tables = $staff_types;
}

$union_parts = [];
foreach ($tables as $key => $info) {
    $tbl = $info['table'];
    $lbl = $info['label'];
    $where = '1=1';
    if (!empty($search)) {
        $s = mysqli_real_escape_string($connect, $search);
        $where = "(u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.email LIKE '%$s%' OR u.phone LIKE '%$s%')";
    }
    $union_parts[] = "SELECT e.id, e.user_id, '' AS qualification, e.created_at, u.first_name, u.last_name, u.email, u.phone, u.gender, u.status as user_status, '$lbl' as staff_type FROM $tbl e JOIN users u ON e.user_id = u.id WHERE $where";
}

if (!empty($union_parts)) {
    $union_sql = implode(' UNION ALL ', $union_parts);
    $cnt_r = mysqli_query($connect, "SELECT COUNT(*) as c FROM ($union_sql) t");
    if ($cnt_r) $total = (int)mysqli_fetch_assoc($cnt_r)['c'];

    $total_pages = $total > 0 ? ceil($total / $per_page) : 0;
    $result = mysqli_query($connect, "SELECT * FROM ($union_sql) t ORDER BY t.created_at DESC LIMIT $offset, $per_page");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
} else {
    $total_pages = 0;
}

include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 중복 안내 (인수인계) -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small"><strong>중복 안내:</strong> 직원은 <strong>인력 관리(admin_personnel.php)</strong>에서 통합 관리됩니다. 이 페이지는 단일 직군 뷰입니다. (중복)</div>
</div>
<?php
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> 직원 관리</h5>
    <a href="admin_staff_wizard.php" class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> 직원 등록 마법사</a>
</div>

<!-- 유형별 통계 -->
<div class="row g-2 mb-3">
    <div class="col">
        <a href="?type=" class="card text-center p-2 text-decoration-none <?php echo empty($filter_type) ? 'border-primary' : ''; ?>">
            <div class="small text-muted">전체</div>
            <div class="h5 mb-0"><?php echo $grand_total; ?></div>
        </a>
    </div>
    <?php foreach ($staff_types as $key => $info): ?>
    <div class="col">
        <a href="?type=<?php echo $key; ?>" class="card text-center p-2 text-decoration-none <?php echo $filter_type === $key ? 'border-primary' : ''; ?>">
            <div class="small text-muted"><?php echo $info['label']; ?></div>
            <div class="h5 mb-0"><?php echo $stats[$key]; ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- 검색 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
            <input type="search" name="search" class="form-control form-control-sm" style="max-width:300px;" placeholder="이름, 이메일, 전화번호 검색..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
            <a href="admin_staffs.php?type=<?php echo urlencode($filter_type); ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 목록 -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">ID</th>
                    <th>이름</th>
                    <th>이메일</th>
                    <th>전화번호</th>
                    <th class="text-center">유형</th>
                    <th>자격</th>
                    <th class="text-center">상태</th>
                    <th>등록일</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i><br>등록된 직원이 없습니다.
                    <br><a href="admin_staff_wizard.php" class="btn btn-primary btn-sm mt-2"><i class="bi bi-magic"></i> 직원 등록 마법사</a>
                </td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-muted"><?php echo $item['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')); ?></strong></td>
                    <td><small><?php echo htmlspecialchars($item['email'] ?? ''); ?></small></td>
                    <td><small><?php echo htmlspecialchars($item['phone'] ?? ''); ?></small></td>
                    <td class="text-center"><span class="badge bg-info"><?php echo htmlspecialchars($item['staff_type'] ?? ''); ?></span></td>
                    <td><small><?php echo htmlspecialchars($item['qualification'] ?? ''); ?></small></td>
                    <td class="text-center">
                        <?php if (($item['user_status'] ?? 1) == 1): ?>
                        <span class="badge bg-success">활성</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">비활성</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($total_pages ?? 0) > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
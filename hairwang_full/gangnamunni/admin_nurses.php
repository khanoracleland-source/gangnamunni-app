<?php
/**
 * 간호사 관리 페이지
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */
session_start();

$admin_page_title = '간호사 관리';
$admin_current = 'nurses';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('nurses');

// 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        // 엔티티 테이블에서 user_id 가져오기
        $r = mysqli_query($connect, "SELECT user_id FROM nurses WHERE id = $id");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $uid = (int)$row['user_id'];
            mysqli_query($connect, "DELETE FROM nurses WHERE id = $id");
            // users 테이블에서도 삭제 (선택적)
            // mysqli_query($connect, "DELETE FROM users WHERE id = $uid");
        }
    }
    header('Location: admin_nurses.php');
    exit;
}

// 검색 & 페이징
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '1=1';
if (!empty($search)) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.email LIKE '%$s%' OR u.phone LIKE '%$s%')";
}

$total = 0;
$cr = mysqli_query($connect, "SELECT COUNT(*) as c FROM nurses e JOIN users u ON e.user_id = u.id WHERE $where");
if ($cr) $total = (int)mysqli_fetch_assoc($cr)['c'];
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

$items = [];
$query = "SELECT e.*, u.first_name, u.last_name, u.email, u.phone, u.gender, u.status as user_status, e.qualification
    FROM nurses e
    JOIN users u ON e.user_id = u.id
    WHERE $where
    ORDER BY e.id DESC
    LIMIT $offset, $per_page";
$result = mysqli_query($connect, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 중복 안내 (인수인계) -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small"><strong>중복 안내:</strong> 간호사는 <strong>인력 관리(admin_personnel.php)</strong>에서 통합 관리됩니다. 이 페이지는 단일 직군 뷰입니다. (중복)</div>
</div>
<?php
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> 간호사 관리</h5>
</div>

<!-- 통계 -->
<div class="alert alert-light py-2 mb-3">
    <strong>총 <?php echo number_format($total); ?>명</strong> 등록
</div>

<!-- 검색 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control form-control-sm" style="max-width:300px;" placeholder="이름, 이메일, 전화번호 검색..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
            <a href="admin_nurses.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> 초기화</a>
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
                    <th class="text-center">성별</th>
                    <th>자격</th>
                    <th class="text-center">상태</th>
                    <th>등록일</th>
                    <th style="width:80px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i><br>등록된 데이터가 없습니다.
                </td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-muted"><?php echo $item['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')); ?></strong></td>
                    <td><small><?php echo htmlspecialchars($item['email'] ?? ''); ?></small></td>
                    <td><small><?php echo htmlspecialchars($item['phone'] ?? ''); ?></small></td>
                    <td class="text-center">
                        <?php echo ($item['gender'] ?? '') == 1 ? '<span class="badge bg-primary">남</span>' : (($item['gender'] ?? '') == 2 ? '<span class="badge bg-danger">여</span>' : '-'); ?>
                    </td>
                    <td><small><?php echo htmlspecialchars($item['qualification'] ?? ''); ?></small></td>
                    <td class="text-center">
                        <?php if (($item['user_status'] ?? 1) == 1): ?>
                        <span class="badge bg-success">활성</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">비활성</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></small></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('삭제하시겠습니까?')" title="삭제"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
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

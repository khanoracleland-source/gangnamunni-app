<?php
/**
 * 의약품 관리 페이지
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */
session_start();

$admin_page_title = '의약품 관리';
$admin_current = 'medicines';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('medicines');

// 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($connect, "DELETE FROM medicines WHERE id = $id");
    }
    header('Location: admin_medicines.php');
    exit;
}

// 필터
$search = trim($_GET['search'] ?? '');
$filter_category = intval($_GET['category_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '1=1';
if (!empty($search)) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (m.name LIKE '%$s%' OR m.salt_composition LIKE '%$s%' OR m.description LIKE '%$s%')";
}
if ($filter_category > 0) {
    $where .= " AND m.category_id = $filter_category";
}

// 통계
$stats = ['total' => 0, 'low_stock' => 0, 'categories' => 0];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM medicines");
if ($sr) $stats['total'] = (int)mysqli_fetch_assoc($sr)['c'];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM medicines WHERE available_quantity < 10");
if ($sr) $stats['low_stock'] = (int)mysqli_fetch_assoc($sr)['c'];
$sr = mysqli_query($connect, "SELECT COUNT(DISTINCT category_id) as c FROM medicines WHERE category_id IS NOT NULL");
if ($sr) $stats['categories'] = (int)mysqli_fetch_assoc($sr)['c'];

// 카테고리 목록
$categories = [];
$cr2 = mysqli_query($connect, "SELECT id, name FROM categories ORDER BY name");
if ($cr2) { while ($row = mysqli_fetch_assoc($cr2)) $categories[] = $row; }

// 목록
$total = 0;
$cr = mysqli_query($connect, "SELECT COUNT(*) as c FROM medicines m WHERE $where");
if ($cr) $total = (int)mysqli_fetch_assoc($cr)['c'];
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

$items = [];
$query = "SELECT m.*, c.name as category_name, b.name as brand_name
    FROM medicines m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN brands b ON m.brand_id = b.id
    WHERE $where
    ORDER BY m.id DESC
    LIMIT $offset, $per_page";
$result = mysqli_query($connect, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-capsule-pill"></i> 의약품 관리</h5>
    <a href="admin_medicine_wizard.php" class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> 의약품 등록 마법사</a>
</div>

<!-- 통계 -->
<div class="row g-2 mb-3">
    <div class="col-4"><div class="card text-center p-2"><div class="small text-muted">전체</div><div class="h5 mb-0"><?php echo $stats['total']; ?></div></div></div>
    <div class="col-4"><div class="card text-center p-2"><div class="small text-muted">재고 부족</div><div class="h5 mb-0 text-danger"><?php echo $stats['low_stock']; ?></div></div></div>
    <div class="col-4"><div class="card text-center p-2"><div class="small text-muted">카테고리</div><div class="h5 mb-0 text-info"><?php echo $stats['categories']; ?></div></div></div>
</div>

<!-- 검색/필터 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select name="category_id" class="form-select form-select-sm" style="width:180px;">
                <option value="">전체 카테고리</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="search" class="form-control form-control-sm" style="max-width:250px;" placeholder="약품명, 성분 검색..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search || $filter_category): ?>
            <a href="admin_medicines.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
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
                    <th>약품명</th>
                    <th>카테고리</th>
                    <th>브랜드</th>
                    <th class="text-end">판매가</th>
                    <th class="text-end">매입가</th>
                    <th class="text-center">재고</th>
                    <th>성분</th>
                    <th style="width:80px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i><br>등록된 의약품이 없습니다.
                    <br><a href="admin_medicine_wizard.php" class="btn btn-primary btn-sm mt-2"><i class="bi bi-magic"></i> 의약품 등록</a>
                </td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-muted"><?php echo $item['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        <?php if (!empty($item['description'])): ?>
                        <br><small class="text-muted"><?php echo mb_strimwidth(htmlspecialchars($item['description']), 0, 40, '...'); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['category_name'] ?? '-'); ?></span></td>
                    <td><small><?php echo htmlspecialchars($item['brand_name'] ?? '-'); ?></small></td>
                    <td class="text-end"><?php echo number_format($item['selling_price']); ?>원</td>
                    <td class="text-end"><small class="text-muted"><?php echo number_format($item['buying_price']); ?>원</small></td>
                    <td class="text-center">
                        <?php if ($item['available_quantity'] < 10): ?>
                        <span class="badge bg-danger"><?php echo $item['available_quantity']; ?></span>
                        <?php else: ?>
                        <span class="badge bg-success"><?php echo $item['available_quantity']; ?></span>
                        <?php endif; ?>
                        / <?php echo $item['quantity']; ?>
                    </td>
                    <td><small><?php echo mb_strimwidth(htmlspecialchars($item['salt_composition'] ?? ''), 0, 30, '...'); ?></small></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('삭제하시겠습니까?')"><i class="bi bi-trash"></i></button>
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
                <a class="page-link" href="?page=<?php echo $i; ?>&category_id=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
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
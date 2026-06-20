<?php
session_start();
$admin_page_title = '약국 관리';
$admin_current = 'pharmacy';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('pharmacy');

$medicines = [];
$r = mysqli_query($connect, "SELECT * FROM medicines ORDER BY name LIMIT 100");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $medicines[] = $row; }

$stocks = [];
$r2 = mysqli_query($connect, "SELECT is2.*, i.name as item_name, ic.name as category_name
    FROM item_stocks is2
    LEFT JOIN items i ON is2.item_id = i.id
    LEFT JOIN item_categories ic ON i.item_category_id = ic.id
    ORDER BY is2.created_at DESC LIMIT 50");
if ($r2) { while ($row = mysqli_fetch_assoc($r2)) $stocks[] = $row; }

include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 조회 전용 안내 (인수인계) -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small"><strong>조회 전용 페이지:</strong> 이 화면은 HMS(병원 본 시스템) 데이터를 <u>조회만</u> 합니다. 추가·수정·삭제는 HMS 본체에서 처리되며, 강남언니 관리자에서는 변경할 수 없습니다. (설계상 의도된 동작)</div>
</div>
<?php
?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-primary"><?= count($medicines) ?></div><div class="text-muted small">등록 약품</div>
    </div></div></div>
    <div class="col-md-6"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-info"><?= count($stocks) ?></div><div class="text-muted small">재고 항목</div>
    </div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-capsule me-2"></i>약품 목록</h5></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>#</th><th>약품명</th><th>카테고리</th><th>브랜드</th><th>판매가</th><th>구매가</th></tr></thead>
            <tbody>
            <?php if (empty($medicines)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">약품 데이터가 없습니다.</td></tr>
            <?php else: foreach ($medicines as $i => $m): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($m['name']) ?></td>
                <td><?= htmlspecialchars($m['category_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($m['brand_id'] ?? '-') ?></td>
                <td><?= number_format($m['selling_price'] ?? 0) ?>원</td>
                <td><?= number_format($m['buying_price'] ?? 0) ?>원</td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>
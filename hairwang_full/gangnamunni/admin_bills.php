<?php
session_start();
$admin_page_title = '청구/결제 관리';
$admin_current = 'bills';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('bills');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$total = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM bills"))['cnt'] ?? 0;
$total_amount = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COALESCE(SUM(amount),0) as s FROM bills"))['s'] ?? 0;
$paid = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM bills WHERE status=1"))['cnt'] ?? 0;
$unpaid = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM bills WHERE status=0 OR status IS NULL"))['cnt'] ?? 0;

$bills = [];
$r = mysqli_query($connect,
    "SELECT b.id, b.bill_id, b.bill_date, b.amount, b.status,
            u.first_name, u.last_name, u.phone
     FROM bills b
     JOIN patients p ON b.patient_id = p.id
     JOIN users u ON p.user_id = u.id
     ORDER BY b.bill_date DESC
     LIMIT {$per_page} OFFSET {$offset}");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $bills[] = $row; }
$total_pages = ceil(max($total,1) / $per_page);

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
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-primary"><?= number_format($total) ?></div><div class="text-muted small">전체 청구</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-success"><?= number_format($total_amount) ?>원</div><div class="text-muted small">총 금액</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-info"><?= $paid ?></div><div class="text-muted small">결제 완료</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-warning"><?= $unpaid ?></div><div class="text-muted small">미결제</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-receipt me-2"></i>청구서 목록</h5></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>청구 #</th><th>환자명</th><th>전화</th><th>금액</th><th>청구일</th><th>상태</th></tr></thead>
            <tbody>
            <?php if (empty($bills)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">청구 데이터가 없습니다. HMS에서 청구서를 생성하면 여기에 표시됩니다.</td></tr>
            <?php else: foreach ($bills as $b): ?>
            <tr>
                <td><span class="badge bg-primary"><?= htmlspecialchars($b['bill_id']) ?></span></td>
                <td class="fw-semibold"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></td>
                <td><?= htmlspecialchars($b['phone'] ?: '-') ?></td>
                <td class="fw-bold"><?= number_format($b['amount']) ?>원</td>
                <td><?= date('Y-m-d', strtotime($b['bill_date'])) ?></td>
                <td><?= $b['status'] ? '<span class="badge bg-success">완료</span>' : '<span class="badge bg-warning text-dark">미결제</span>' ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>
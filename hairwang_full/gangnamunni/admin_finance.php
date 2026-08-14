<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

session_start();
$admin_page_title = '재무 관리';
$admin_current = 'finance';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('finance');

$total_income = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COALESCE(SUM(amount),0) as s FROM incomes"))['s'] ?? 0;
$total_expense = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COALESCE(SUM(amount),0) as s FROM expenses"))['s'] ?? 0;
$total_bills = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COALESCE(SUM(amount),0) as s FROM bills"))['s'] ?? 0;
$net = $total_income - $total_expense;

$recent_income = [];
$r = mysqli_query($connect, "SELECT * FROM incomes ORDER BY date DESC LIMIT 10");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $recent_income[] = $row; }

$recent_expense = [];
$r2 = mysqli_query($connect, "SELECT * FROM expenses ORDER BY date DESC LIMIT 10");
if ($r2) { while ($row = mysqli_fetch_assoc($r2)) $recent_expense[] = $row; }

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
    <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-3">
        <div class="fs-4 fw-bold text-success"><?= number_format($total_income) ?>원</div><div class="text-muted small">총 수입</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-3">
        <div class="fs-4 fw-bold text-danger"><?= number_format($total_expense) ?>원</div><div class="text-muted small">총 지출</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-4 fw-bold text-primary"><?= number_format($total_bills) ?>원</div><div class="text-muted small">총 청구</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-4 fw-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($net) ?>원</div><div class="text-muted small">순이익</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success bg-opacity-10"><h6 class="mb-0 text-success"><i class="bi bi-graph-up-arrow me-1"></i>최근 수입</h6></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>날짜</th><th>이름</th><th>금액</th></tr></thead>
                    <tbody>
                    <?php if (empty($recent_income)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">수입 데이터가 없습니다.</td></tr>
                    <?php else: foreach ($recent_income as $inc): ?>
                    <tr>
                        <td><?= date('m/d', strtotime($inc['date'])) ?></td>
                        <td><?= htmlspecialchars($inc['name'] ?? '-') ?></td>
                        <td class="text-success fw-bold"><?= number_format($inc['amount']) ?>원</td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger bg-opacity-10"><h6 class="mb-0 text-danger"><i class="bi bi-graph-down-arrow me-1"></i>최근 지출</h6></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>날짜</th><th>이름</th><th>금액</th></tr></thead>
                    <tbody>
                    <?php if (empty($recent_expense)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">지출 데이터가 없습니다.</td></tr>
                    <?php else: foreach ($recent_expense as $exp): ?>
                    <tr>
                        <td><?= date('m/d', strtotime($exp['date'])) ?></td>
                        <td><?= htmlspecialchars($exp['name'] ?? '-') ?></td>
                        <td class="text-danger fw-bold"><?= number_format($exp['amount']) ?>원</td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>

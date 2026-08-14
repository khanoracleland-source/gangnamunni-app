<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

session_start();
$admin_page_title = '입원 관리';
$admin_current = 'ipd';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('ipd');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE 1=1";
if ($filter === 'admitted') $where .= " AND i.is_discharge = 0";
elseif ($filter === 'discharged') $where .= " AND i.is_discharge = 1";

$total = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM ipd_patient_departments i
     JOIN patients p ON i.patient_id = p.id
     JOIN users u ON p.user_id = u.id {$where}"))['cnt'];

$ipd_list = [];
$r = mysqli_query($connect,
    "SELECT i.id, i.ipd_number, i.admission_date, i.is_discharge, i.symptoms, i.notes,
            i.height, i.weight, i.bp, i.bill_status,
            u.first_name, u.last_name, u.phone,
            d_u.first_name as doc_first, d_u.last_name as doc_last,
            b.name as bed_name, bt.title as bed_type_name
     FROM ipd_patient_departments i
     JOIN patients p ON i.patient_id = p.id
     JOIN users u ON p.user_id = u.id
     LEFT JOIN doctors d ON i.doctor_id = d.id
     LEFT JOIN users d_u ON d.user_id = d_u.id
     LEFT JOIN beds b ON i.bed_id = b.id
     LEFT JOIN bed_types bt ON i.bed_type_id = bt.id
     {$where}
     ORDER BY i.admission_date DESC
     LIMIT {$per_page} OFFSET {$offset}");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $ipd_list[] = $row; }
$total_pages = ceil($total / $per_page);

$admitted = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM ipd_patient_departments WHERE is_discharge=0"))['cnt'] ?? 0;
$discharged = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM ipd_patient_departments WHERE is_discharge=1"))['cnt'] ?? 0;

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
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-primary"><?= number_format($total) ?></div><div class="text-muted small">전체 입원</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center border-success"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-success"><?= $admitted ?></div><div class="text-muted small">현재 입원중</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-secondary"><?= $discharged ?></div><div class="text-muted small">퇴원</div>
    </div></div></div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <div class="btn-group btn-group-sm">
        <a href="?filter=all" class="btn btn-<?= $filter=='all'?'primary':'outline-primary' ?>">전체</a>
        <a href="?filter=admitted" class="btn btn-<?= $filter=='admitted'?'success':'outline-success' ?>">입원중</a>
        <a href="?filter=discharged" class="btn btn-<?= $filter=='discharged'?'secondary':'outline-secondary' ?>">퇴원</a>
    </div>
</div></div>

<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-hospital me-2"></i>입원 환자 (<?= number_format($total) ?>)</h5></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>IPD #</th><th>환자명</th><th>전화</th><th>담당의</th><th>병상</th><th>입원일</th><th>상태</th><th>청구</th></tr></thead>
            <tbody>
            <?php if (empty($ipd_list)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">입원 데이터가 없습니다.</td></tr>
            <?php else: foreach ($ipd_list as $i): ?>
                <tr>
                    <td><span class="badge bg-info"><?= htmlspecialchars($i['ipd_number']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($i['first_name'].' '.$i['last_name']) ?></td>
                    <td><?= htmlspecialchars($i['phone'] ?: '-') ?></td>
                    <td><?= htmlspecialchars(($i['doc_first'] ?? '').($i['doc_last'] ? ' '.$i['doc_last'] : '')) ?: '-' ?></td>
                    <td><?= htmlspecialchars(($i['bed_name'] ?? '-') . ($i['bed_type_name'] ? ' ('.$i['bed_type_name'].')' : '')) ?></td>
                    <td><?= date('Y-m-d', strtotime($i['admission_date'])) ?></td>
                    <td><?= $i['is_discharge'] ? '<span class="badge bg-secondary">퇴원</span>' : '<span class="badge bg-success">입원중</span>' ?></td>
                    <td><?= $i['bill_status'] ? '<span class="badge bg-primary">완료</span>' : '<span class="badge bg-warning text-dark">미결</span>' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
    <?php if ($total_pages > 1): ?><div class="card-footer"><nav><ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($pg = max(1,$page-3); $pg <= min($total_pages,$page+3); $pg++): ?>
        <li class="page-item <?= $pg==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $pg ?>&filter=<?= $filter ?>"><?= $pg ?></a></li>
        <?php endfor; ?></ul></nav></div><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>

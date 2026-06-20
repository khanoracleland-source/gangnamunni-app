<?php
/**
 * 외래(OPD) 관리 페이지
 * HMS opd_patient_departments 테이블 직접 조회
 */
session_start();
$admin_page_title = '외래 관리';
$admin_current = 'opd';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('opd');

require_once __DIR__ . '/includes/hospital_filter.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$filter_date = $_GET['date'] ?? '';

$where = "WHERE 1=1";
if ($filter_date) {
    $where .= " AND DATE(o.appointment_date) = '" . mysqli_real_escape_string($connect, $filter_date) . "'";
}

// 외래 목록
$total = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM opd_patient_departments o
     JOIN patients p ON o.patient_id = p.id
     JOIN users u ON p.user_id = u.id
     {$where}"))['cnt'];

$opd_list = [];
$r = mysqli_query($connect,
    "SELECT o.id, o.opd_number, o.appointment_date, o.symptoms, o.notes,
            o.height, o.weight, o.bp, o.standard_charge, o.payment_mode,
            u.first_name, u.last_name, u.phone,
            d_u.first_name as doc_first, d_u.last_name as doc_last
     FROM opd_patient_departments o
     JOIN patients p ON o.patient_id = p.id
     JOIN users u ON p.user_id = u.id
     LEFT JOIN doctors d ON o.doctor_id = d.id
     LEFT JOIN users d_u ON d.user_id = d_u.id
     {$where}
     ORDER BY o.appointment_date DESC
     LIMIT {$per_page} OFFSET {$offset}");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $opd_list[] = $row; }
$total_pages = ceil($total / $per_page);

// 통계
$today_cnt = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM opd_patient_departments WHERE DATE(appointment_date) = CURDATE()"))['cnt'] ?? 0;
$week_cnt = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM opd_patient_departments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['cnt'] ?? 0;
$month_cnt = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM opd_patient_departments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"))['cnt'] ?? 0;

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
        <div class="fs-3 fw-bold text-primary"><?= number_format($total) ?></div>
        <div class="text-muted small">전체 외래</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-success"><?= $today_cnt ?></div>
        <div class="text-muted small">오늘 외래</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-info"><?= $week_cnt ?></div>
        <div class="text-muted small">이번 주</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-warning"><?= $month_cnt ?></div>
        <div class="text-muted small">이번 달</div>
    </div></div></div>
</div>

<!-- 날짜 필터 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">날짜 필터</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>필터</button>
            </div>
            <div class="col-md-2">
                <a href="admin_opd.php" class="btn btn-outline-secondary w-100">초기화</a>
            </div>
        </form>
    </div>
</div>

<!-- 외래 목록 -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>외래 환자 목록 (<?= number_format($total) ?>건)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>OPD #</th>
                        <th>환자명</th>
                        <th>전화번호</th>
                        <th>담당의</th>
                        <th>진료일</th>
                        <th>증상</th>
                        <th>신체정보</th>
                        <th>진료비</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($opd_list)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">외래 데이터가 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($opd_list as $o): ?>
                    <tr>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($o['opd_number']) ?></span></td>
                        <td class="fw-semibold"><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                        <td><?= htmlspecialchars($o['phone'] ?: '-') ?></td>
                        <td><?= htmlspecialchars(($o['doc_first'] ?? '') . ' ' . ($o['doc_last'] ?? '')) ?: '-' ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($o['appointment_date'])) ?></td>
                        <td class="text-truncate" style="max-width:200px"><?= htmlspecialchars($o['symptoms'] ?: '-') ?></td>
                        <td>
                            <?php if ($o['height'] || $o['weight'] || $o['bp']): ?>
                            <small><?= $o['height'] ? $o['height'].'cm ' : '' ?><?= $o['weight'] ? $o['weight'].'kg ' : '' ?><?= $o['bp'] ? 'BP:'.$o['bp'] : '' ?></small>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?= $o['standard_charge'] ? number_format($o['standard_charge']) . '원' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($pg = max(1, $page-3); $pg <= min($total_pages, $page+3); $pg++): ?>
            <li class="page-item <?= $pg == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&date=<?= urlencode($filter_date) ?>"><?= $pg ?></a>
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
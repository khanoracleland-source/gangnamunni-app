<?php
/**
 * 처방전 관리 페이지
 * HMS prescriptions + prescriptions_medicines 테이블
 */
session_start();
$admin_page_title = '처방전 관리';
$admin_current = 'prescriptions';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('prescriptions');

require_once __DIR__ . '/includes/hospital_filter.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 처방전 목록
$total = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM prescriptions"))['cnt'];

$rx_list = [];
$r = mysqli_query($connect,
    "SELECT rx.id, rx.food_allergies, rx.medical_history, rx.current_medication,
            rx.problem_description, rx.advice, rx.status,
            rx.plus_rate, rx.temperature, rx.created_at,
            u.first_name as pat_first, u.last_name as pat_last, u.phone as pat_phone,
            d_u.first_name as doc_first, d_u.last_name as doc_last
     FROM prescriptions rx
     JOIN patients p ON rx.patient_id = p.id
     JOIN users u ON p.user_id = u.id
     LEFT JOIN doctors d ON rx.doctor_id = d.id
     LEFT JOIN users d_u ON d.user_id = d_u.id
     ORDER BY rx.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $rx_list[] = $row; }
$total_pages = ceil($total / $per_page);

// 처방 약품 조회 함수
function get_rx_medicines($connect, $rx_id) {
    $meds = [];
    $r = mysqli_query($connect,
        "SELECT pm.*, m.name as medicine_name, m.selling_price
         FROM prescriptions_medicines pm
         LEFT JOIN medicines m ON pm.medicine_id = m.id
         WHERE pm.prescription_id = {$rx_id}");
    if ($r) { while ($row = mysqli_fetch_assoc($r)) $meds[] = $row; }
    return $meds;
}

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
        <div class="fs-3 fw-bold text-primary"><?= number_format($total) ?></div>
        <div class="text-muted small">전체 처방전</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <?php $active = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM prescriptions WHERE status=1"))['cnt'] ?? 0; ?>
        <div class="fs-3 fw-bold text-success"><?= $active ?></div>
        <div class="text-muted small">활성 처방</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-3">
        <?php $today_rx = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM prescriptions WHERE DATE(created_at)=CURDATE()"))['cnt'] ?? 0; ?>
        <div class="fs-3 fw-bold text-info"><?= $today_rx ?></div>
        <div class="text-muted small">오늘 처방</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i>처방전 목록 (<?= number_format($total) ?>건)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>환자명</th>
                        <th>전화번호</th>
                        <th>담당의</th>
                        <th>증상/문제</th>
                        <th>현재 복용약</th>
                        <th>처방 약품</th>
                        <th>상태</th>
                        <th>처방일</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rx_list)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">처방전 데이터가 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($rx_list as $rx): ?>
                    <?php $meds = get_rx_medicines($connect, $rx['id']); ?>
                    <tr>
                        <td><?= $rx['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($rx['pat_first'] . ' ' . $rx['pat_last']) ?></td>
                        <td><?= htmlspecialchars($rx['pat_phone'] ?: '-') ?></td>
                        <td><?= htmlspecialchars(($rx['doc_first'] ?? '') . ' ' . ($rx['doc_last'] ?? '')) ?: '-' ?></td>
                        <td class="text-truncate" style="max-width:150px"><?= htmlspecialchars($rx['problem_description'] ?: '-') ?></td>
                        <td class="text-truncate" style="max-width:150px"><?= htmlspecialchars($rx['current_medication'] ?: '-') ?></td>
                        <td>
                            <?php if ($meds): ?>
                                <?php foreach (array_slice($meds, 0, 3) as $m): ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($m['medicine_name'] ?? 'N/A') ?></span>
                                <?php endforeach; ?>
                                <?php if (count($meds) > 3): ?>
                                    <span class="badge bg-secondary">+<?= count($meds) - 3 ?></span>
                                <?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rx['status']): ?>
                                <span class="badge bg-success">활성</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">비활성</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $rx['created_at'] ? date('Y-m-d', strtotime($rx['created_at'])) : '-' ?></td>
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
                <a class="page-link" href="?page=<?= $pg ?>"><?= $pg ?></a>
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
<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

session_start();
$admin_page_title = '병상 관리';
$admin_current = 'beds';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('beds');

// 병상 유형
$bed_types = [];
$r = mysqli_query($connect, "SELECT bt.id, bt.title, COUNT(b.id) as bed_count,
    SUM(b.is_available) as available_count
    FROM bed_types bt LEFT JOIN beds b ON bt.id = b.bed_type
    GROUP BY bt.id ORDER BY bt.title");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $bed_types[] = $row; }

// 전체 병상
$beds = [];
$r = mysqli_query($connect, "SELECT b.*, bt.title as type_name,
    ba.patient_id, ba.assign_date, ba.status as assign_status,
    u.first_name, u.last_name
    FROM beds b
    LEFT JOIN bed_types bt ON b.bed_type = bt.id
    LEFT JOIN bed_assigns ba ON b.id = ba.bed_id AND ba.discharge_date IS NULL AND ba.status = 1
    LEFT JOIN patients p ON ba.patient_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY bt.title, b.name");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $beds[] = $row; }

$total_beds = count($beds);
$available = count(array_filter($beds, fn($b) => $b['is_available']));
$occupied = $total_beds - $available;

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
        <div class="fs-3 fw-bold text-primary"><?= $total_beds ?></div><div class="text-muted small">전체 병상</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center border-success"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-success"><?= $available ?></div><div class="text-muted small">이용 가능</div>
    </div></div></div>
    <div class="col-md-4"><div class="card text-center border-danger"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-danger"><?= $occupied ?></div><div class="text-muted small">사용중</div>
    </div></div></div>
</div>

<?php if ($bed_types): ?>
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-grid me-1"></i>병상 유형별 현황</h6></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>유형</th><th>전체</th><th>가용</th><th>사용률</th></tr></thead>
            <tbody>
            <?php foreach ($bed_types as $bt): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($bt['title']) ?></td>
                <td><?= $bt['bed_count'] ?></td>
                <td class="text-success"><?= $bt['available_count'] ?? 0 ?></td>
                <td>
                    <?php $usage = $bt['bed_count'] > 0 ? round((($bt['bed_count'] - ($bt['available_count'] ?? 0)) / $bt['bed_count']) * 100) : 0; ?>
                    <div class="progress" style="height:20px">
                        <div class="progress-bar bg-<?= $usage > 80 ? 'danger' : ($usage > 50 ? 'warning' : 'success') ?>" style="width:<?= $usage ?>%"><?= $usage ?>%</div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-hospital me-2"></i>전체 병상 목록</h5></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>병상 ID</th><th>이름</th><th>유형</th><th>요금</th><th>상태</th><th>환자</th><th>입원일</th></tr></thead>
            <tbody>
            <?php foreach ($beds as $b): ?>
            <tr>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($b['bed_id']) ?></span></td>
                <td class="fw-semibold"><?= htmlspecialchars($b['name']) ?></td>
                <td><?= htmlspecialchars($b['type_name'] ?? '-') ?></td>
                <td><?= number_format($b['charge']) ?>원</td>
                <td><?= $b['is_available'] ? '<span class="badge bg-success">가용</span>' : '<span class="badge bg-danger">사용중</span>' ?></td>
                <td><?= $b['first_name'] ? htmlspecialchars($b['first_name'].' '.$b['last_name']) : '-' ?></td>
                <td><?= $b['assign_date'] ? date('Y-m-d', strtotime($b['assign_date'])) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>

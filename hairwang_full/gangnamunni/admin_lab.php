<?php
session_start();
$admin_page_title = '검사실 관리';
$admin_current = 'lab';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('lab');

$path_tests = [];
$r = mysqli_query($connect, "SELECT pt.*, pc.name as category_name
    FROM pathology_tests pt
    LEFT JOIN pathology_categories pc ON pt.category_id = pc.id
    ORDER BY pt.test_name LIMIT 50");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $path_tests[] = $row; }

$rad_tests = [];
$r2 = mysqli_query($connect, "SELECT rt.*, rc.name as category_name
    FROM radiology_tests rt
    LEFT JOIN radiology_categories rc ON rt.category_id = rc.id
    ORDER BY rt.test_name LIMIT 50");
if ($r2) { while ($row = mysqli_fetch_assoc($r2)) $rad_tests[] = $row; }

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
        <div class="fs-3 fw-bold text-primary"><?= count($path_tests) ?></div><div class="text-muted small">병리 검사</div>
    </div></div></div>
    <div class="col-md-6"><div class="card text-center"><div class="card-body py-3">
        <div class="fs-3 fw-bold text-info"><?= count($rad_tests) ?></div><div class="text-muted small">방사선 검사</div>
    </div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pathTab">병리 검사</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#radTab">방사선 검사</a></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="pathTab">
        <div class="card"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light"><tr><th>#</th><th>검사명</th><th>카테고리</th><th>약칭</th><th>비용</th></tr></thead>
                <tbody>
                <?php if (empty($path_tests)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">병리 검사 데이터가 없습니다.</td></tr>
                <?php else: foreach ($path_tests as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($t['test_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['category_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['short_name'] ?? '-') ?></td>
                    <td><?= number_format($t['standard_charge'] ?? 0) ?>원</td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div></div>
    </div>
    <div class="tab-pane fade" id="radTab">
        <div class="card"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light"><tr><th>#</th><th>검사명</th><th>카테고리</th><th>약칭</th><th>비용</th></tr></thead>
                <tbody>
                <?php if (empty($rad_tests)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">방사선 검사 데이터가 없습니다.</td></tr>
                <?php else: foreach ($rad_tests as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($t['test_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['category_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['short_name'] ?? '-') ?></td>
                    <td><?= number_format($t['standard_charge'] ?? 0) ?>원</td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div></div>
    </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>
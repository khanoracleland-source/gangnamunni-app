<?php
/**
 * CPV 정산 보고서
 * 월별 병원 정산 요약 + 엑셀 내보내기
 */
session_start();
$admin_page_title = 'CPV 정산 보고서';
$admin_current = 'cpv_settlement';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
require_admin_login();
require_permission('cpv_settlement');

require_once __DIR__ . '/includes/cpv_helper.php';

$is_super = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$assigned_hospital_id = $is_super ? 0 : (int)($_SESSION['ad_admin_hospital_id'] ?? 0);

// ── 필터 (연/월) ──────────────────────────────────────────
$sel_year  = intval($_GET['year'] ?? date('Y'));
$sel_month = intval($_GET['month'] ?? date('n'));

// 유효 범위 보정
if ($sel_year < 2020 || $sel_year > 2030) $sel_year = (int) date('Y');
if ($sel_month < 1 || $sel_month > 12) $sel_month = (int) date('n');

$period_start = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$period_end   = date('Y-m-t', strtotime($period_start)); // 해당 월 마지막 날

// ── 정산 데이터 조회 ──────────────────────────────────────────
$settlements = [];
$hospital_scope_sql = $is_super ? '' : ' AND s.hospital_id = ?';
$stmt = mysqli_prepare($connect,
    "SELECT s.hospital_id,
            h.name AS hospital_name,
            SUM(s.total_views) AS total_views,
            SUM(s.charged_views) AS charged_views,
            SUM(s.total_amount) AS total_charged,
            SUM(s.unique_visitors) AS unique_visitors,
            AVG(s.avg_rate) AS avg_rate,
            COUNT(DISTINCT s.stat_date) AS active_days
     FROM gangnamunni_cpv_daily_stats s
     LEFT JOIN gangnamunni_hospitals h ON h.id = s.hospital_id
     WHERE s.stat_date BETWEEN ? AND ?{$hospital_scope_sql}
     GROUP BY s.hospital_id
     ORDER BY total_charged DESC");
if ($stmt) {
    if ($is_super) {
        mysqli_stmt_bind_param($stmt, 'ss', $period_start, $period_end);
    } else {
        mysqli_stmt_bind_param($stmt, 'ssi', $period_start, $period_end, $assigned_hospital_id);
    }
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) {
        // VAT 10% 계산
        $charged = (float) $row['total_charged'];
        $row['vat'] = round($charged * 0.1);
        $row['net_amount'] = round($charged * 1.1); // 공급가 + VAT
        $settlements[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// 합계 계산
$sum_views = 0; $sum_charged_views = 0; $sum_amount = 0; $sum_vat = 0; $sum_net = 0; $sum_visitors = 0;
foreach ($settlements as $s) {
    $sum_views += $s['total_views'];
    $sum_charged_views += $s['charged_views'];
    $sum_amount += $s['total_charged'];
    $sum_vat += $s['vat'];
    $sum_net += $s['net_amount'];
    $sum_visitors += $s['unique_visitors'];
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.settlement-wrap { max-width: 1200px; margin: 0 auto; }
.period-badge { font-size: 18px; font-weight: 700; }
.sum-row td { font-weight: 700; background: var(--bs-primary-bg-subtle) !important; }

/* 인쇄용 CSS */
@media print {
    .admin-sidebar, .admin-topbar, .no-print, .btn, nav, footer { display: none !important; }
    .admin-main { margin: 0 !important; padding: 0 !important; }
    .settlement-wrap { max-width: 100%; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    table { font-size: 11px !important; }
    .summary-print { display: block !important; }
}
.summary-print { display: none; }
</style>

<div class="settlement-wrap px-3 py-4">
    <!-- 인쇄 전용 헤더 -->
    <div class="summary-print text-center mb-4">
        <h3>CPV 정산 보고서</h3>
        <p><?= $sel_year ?>년 <?= $sel_month ?>월</p>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 no-print">
        <div>
            <h4 class="mb-1"><i class="bi bi-receipt"></i> CPV 정산 보고서</h4>
            <small class="text-body-secondary">월별 병원 CPV 과금 정산 내역
                <span class="badge <?= $is_super ? 'bg-danger' : 'bg-info' ?>"><?= $is_super ? '전체 병원' : '소속 병원만' ?></span>
            </small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <!-- 연/월 필터 -->
            <form class="d-flex gap-2 align-items-center" method="GET">
                <select name="year" class="form-select form-select-sm" style="width:100px;">
                    <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $sel_year ? 'selected' : '' ?>><?= $y ?>년</option>
                    <?php endfor; ?>
                </select>
                <select name="month" class="form-select form-select-sm" style="width:90px;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $sel_month ? 'selected' : '' ?>><?= $m ?>월</option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> 조회</button>
            </form>

            <!-- 빠른 이동 -->
            <?php
            $prev_m = $sel_month - 1;
            $prev_y = $sel_year;
            if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
            $next_m = $sel_month + 1;
            $next_y = $sel_year;
            if ($next_m > 12) { $next_m = 1; $next_y++; }
            ?>
            <div class="btn-group btn-group-sm">
                <a href="?year=<?= $prev_y ?>&month=<?= $prev_m ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-outline-secondary">이번 달</a>
                <a href="?year=<?= $next_y ?>&month=<?= $next_m ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <!-- 내보내기/인쇄 -->
            <button class="btn btn-sm btn-success" onclick="exportExcel()">
                <i class="bi bi-file-earmark-excel"></i> 엑셀
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> 인쇄
            </button>
        </div>
    </div>

    <?php if (!$is_super && $assigned_hospital_id <= 0): ?>
    <div class="alert alert-warning">계정에 연결된 병원이 없어 정산 정보를 조회할 수 없습니다.</div>
    <?php endif; ?>

    <!-- 기간 요약 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-body-secondary" style="font-size:12px;">대상 기간</div>
                    <div class="period-badge"><?= $sel_year ?>.<span class="text-primary"><?= sprintf('%02d', $sel_month) ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-body-secondary" style="font-size:12px;">정산 병원 수</div>
                    <div class="period-badge text-primary"><?= count($settlements) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-body-secondary" style="font-size:12px;">총 공급가</div>
                    <div class="period-badge"><?= number_format($sum_amount) ?><small class="fw-normal">원</small></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-body-secondary" style="font-size:12px;">VAT 포함 합계</div>
                    <div class="period-badge text-success"><?= number_format($sum_net) ?><small class="fw-normal">원</small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 정산 테이블 -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="settlementTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>병원명</th>
                            <th class="text-end">총 조회수</th>
                            <th class="text-end">과금 조회</th>
                            <th class="text-end">순 방문자</th>
                            <th class="text-end">활동일수</th>
                            <th class="text-end">평균 단가</th>
                            <th class="text-end">공급가</th>
                            <th class="text-end">VAT (10%)</th>
                            <th class="text-end fw-bold">합계</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($settlements)): ?>
                        <tr><td colspan="10" class="text-center text-body-secondary py-5">
                            <i class="bi bi-inbox d-block mb-2" style="font-size:32px;"></i>
                            해당 기간에 정산 데이터가 없습니다.
                        </td></tr>
                    <?php else: ?>
                    <?php foreach ($settlements as $i => $s): ?>
                        <tr>
                            <td class="text-body-secondary"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($s['hospital_name'] ?: '병원 #' . $s['hospital_id']) ?></td>
                            <td class="text-end"><?= number_format($s['total_views']) ?></td>
                            <td class="text-end"><?= number_format($s['charged_views']) ?></td>
                            <td class="text-end"><?= number_format($s['unique_visitors']) ?></td>
                            <td class="text-end"><?= $s['active_days'] ?>일</td>
                            <td class="text-end"><?= number_format($s['avg_rate']) ?>원</td>
                            <td class="text-end"><?= number_format($s['total_charged']) ?>원</td>
                            <td class="text-end"><?= number_format($s['vat']) ?>원</td>
                            <td class="text-end fw-bold"><?= number_format($s['net_amount']) ?>원</td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- 합계 행 -->
                    <tr class="sum-row">
                        <td colspan="2">합계</td>
                        <td class="text-end"><?= number_format($sum_views) ?></td>
                        <td class="text-end"><?= number_format($sum_charged_views) ?></td>
                        <td class="text-end"><?= number_format($sum_visitors) ?></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end"><?= number_format($sum_amount) ?>원</td>
                        <td class="text-end"><?= number_format($sum_vat) ?>원</td>
                        <td class="text-end"><?= number_format($sum_net) ?>원</td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 text-body-secondary no-print" style="font-size:12px;">
        <i class="bi bi-info-circle"></i>
        VAT는 공급가의 10%로 자동 계산됩니다. 실제 정산 시 세금계산서 발행 기준으로 차이가 발생할 수 있습니다.
    </div>
</div>

<!-- SheetJS (엑셀 내보내기) -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
function exportExcel() {
    var table = document.getElementById('settlementTable');
    if (!table) return;

    // SheetJS 테이블 → 워크북 변환
    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.table_to_sheet(table, { raw: true });

    // 컬럼 너비 설정
    ws['!cols'] = [
        { wch: 5 },   // #
        { wch: 25 },  // 병원명
        { wch: 12 },  // 총 조회수
        { wch: 12 },  // 과금 조회
        { wch: 12 },  // 순 방문자
        { wch: 10 },  // 활동일수
        { wch: 12 },  // 평균 단가
        { wch: 15 },  // 공급가
        { wch: 12 },  // VAT
        { wch: 15 },  // 합계
    ];

    var sheetName = '<?= $sel_year ?>년<?= $sel_month ?>월 정산';
    XLSX.utils.book_append_sheet(wb, ws, sheetName);

    var filename = 'CPV_정산_<?= $sel_year ?>년<?= sprintf('%02d', $sel_month) ?>월.xlsx';
    XLSX.writeFile(wb, filename);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

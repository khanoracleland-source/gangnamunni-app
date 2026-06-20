<?php
/**
 * CPV 과금 대시보드 — 매출 분석 + 병원별 통계
 */
session_start();
$admin_page_title = 'CPV 대시보드';
$admin_current = 'cpv_dashboard';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
require_admin_login();
require_permission('cpv_dashboard');

require_once __DIR__ . '/includes/cpv_helper.php';

// ── 날짜 필터 ──────────────────────────────────────────────
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['to'] ?? date('Y-m-d');

// ── 요약 카드 데이터 ──────────────────────────────────────────
// 오늘 매출
$today = date('Y-m-d');
$r = mysqli_query($connect,
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM gangnamunni_cpv_daily_stats WHERE stat_date = '{$today}'");
$revenue_today = (float) mysqli_fetch_assoc($r)['total'];

// 이번 주 매출 (월~일)
$week_start = date('Y-m-d', strtotime('monday this week'));
$r = mysqli_query($connect,
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM gangnamunni_cpv_daily_stats WHERE stat_date >= '{$week_start}'");
$revenue_week = (float) mysqli_fetch_assoc($r)['total'];

// 이번 달 매출
$month_start = date('Y-m-01');
$r = mysqli_query($connect,
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM gangnamunni_cpv_daily_stats WHERE stat_date >= '{$month_start}'");
$revenue_month = (float) mysqli_fetch_assoc($r)['total'];

// 활성 병원 수
$r = mysqli_query($connect,
    "SELECT COUNT(*) AS cnt FROM gangnamunni_cpv_wallets WHERE is_active = 1 AND is_paused = 0");
$active_hospitals = (int) mysqli_fetch_assoc($r)['cnt'];

// 전체 잔액 합계
$r = mysqli_query($connect,
    "SELECT COALESCE(SUM(balance), 0) AS total FROM gangnamunni_cpv_wallets WHERE is_active = 1");
$total_balance = (float) mysqli_fetch_assoc($r)['total'];

// ── Top 10 병원 (기간 내) ──────────────────────────────────
$top_hospitals = [];
$stmt = mysqli_prepare($connect,
    "SELECT s.hospital_id, h.name AS hospital_name,
            SUM(s.total_views) AS views, SUM(s.charged_views) AS charged,
            SUM(s.total_amount) AS spent, AVG(s.avg_rate) AS avg_rate
     FROM gangnamunni_cpv_daily_stats s
     LEFT JOIN gangnamunni_hospitals h ON h.id = s.hospital_id
     WHERE s.stat_date BETWEEN ? AND ?
     GROUP BY s.hospital_id
     ORDER BY spent DESC
     LIMIT 10");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $date_from, $date_to);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $top_hospitals[] = $row;
    mysqli_stmt_close($stmt);
}

// ── 일별 트렌드 (차트용, 최근 30일) ──────────────────────────
$daily_trend = [];
$stmt = mysqli_prepare($connect,
    "SELECT stat_date, SUM(total_amount) AS revenue, SUM(total_views) AS views, SUM(charged_views) AS charged
     FROM gangnamunni_cpv_daily_stats
     WHERE stat_date BETWEEN ? AND ?
     GROUP BY stat_date
     ORDER BY stat_date ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $date_from, $date_to);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $daily_trend[] = $row;
    mysqli_stmt_close($stmt);
}

// ── 최근 거래 50건 ──────────────────────────────────────────
$recent_tx = [];
$r = mysqli_query($connect,
    "SELECT t.*, h.name AS hospital_name
     FROM gangnamunni_cpv_transactions t
     LEFT JOIN gangnamunni_hospitals h ON h.id = t.hospital_id
     ORDER BY t.created_at DESC LIMIT 50");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) $recent_tx[] = $row;
}

// ── 잔액 부족 알림 (5만원 이하) ──────────────────────────────
$low_balance = [];
$r = mysqli_query($connect,
    "SELECT w.*, h.name AS hospital_name
     FROM gangnamunni_cpv_wallets w
     LEFT JOIN gangnamunni_hospitals h ON h.id = w.hospital_id
     WHERE w.is_active = 1 AND w.balance <= 50000
     ORDER BY w.balance ASC LIMIT 20");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) $low_balance[] = $row;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.cpv-dash { max-width: 1400px; margin: 0 auto; }
.summary-card { border-radius: 12px; padding: 20px; transition: transform 0.15s; }
.summary-card:hover { transform: translateY(-2px); }
.summary-value { font-size: 26px; font-weight: 700; }
.summary-label { font-size: 12px; opacity: 0.7; }
.chart-container { position: relative; height: 320px; }
.tx-type { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
</style>

<div class="cpv-dash px-3 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-graph-up-arrow"></i> CPV 대시보드</h4>
            <small class="text-body-secondary">CPV 과금 현황 및 매출 분석</small>
        </div>
        <form class="d-flex gap-2 align-items-center" method="GET">
            <input type="date" class="form-control form-control-sm" name="from" value="<?= htmlspecialchars($date_from) ?>">
            <span class="text-body-secondary">~</span>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= htmlspecialchars($date_to) ?>">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> 조회</button>
        </form>
    </div>

    <!-- 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="summary-card bg-primary text-white">
                <div class="summary-label">오늘 매출</div>
                <div class="summary-value"><?= number_format($revenue_today) ?></div>
                <small>원</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card bg-success text-white">
                <div class="summary-label">이번 주 매출</div>
                <div class="summary-value"><?= number_format($revenue_week) ?></div>
                <small>원</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card bg-info text-white">
                <div class="summary-label">이번 달 매출</div>
                <div class="summary-value"><?= number_format($revenue_month) ?></div>
                <small>원</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card bg-body-secondary">
                <div class="summary-label">활성 병원 / 전체 잔액</div>
                <div class="summary-value"><?= $active_hospitals ?><small class="fw-normal">개</small></div>
                <small><?= number_format($total_balance) ?>원</small>
            </div>
        </div>
    </div>

    <!-- 차트 영역 -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-bar-chart-line"></i> 일별 매출 추이</h6>
            <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-secondary active" onclick="switchChart('revenue')">매출</button>
                <button class="btn btn-outline-secondary" onclick="switchChart('views')">조회수</button>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Top 10 병원 -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-trophy"></i> 지출 Top 10 병원</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>병원명</th>
                                    <th class="text-end">조회수</th>
                                    <th class="text-end">과금 조회</th>
                                    <th class="text-end">지출액</th>
                                    <th class="text-end">평균 단가</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($top_hospitals)): ?>
                                <tr><td colspan="6" class="text-center text-body-secondary py-4">데이터 없음</td></tr>
                            <?php else: ?>
                            <?php foreach ($top_hospitals as $i => $th): ?>
                                <tr>
                                    <td><span class="badge bg-<?= $i < 3 ? 'primary' : 'secondary' ?>"><?= $i + 1 ?></span></td>
                                    <td><?= htmlspecialchars($th['hospital_name'] ?: '병원 #' . $th['hospital_id']) ?></td>
                                    <td class="text-end"><?= number_format($th['views']) ?></td>
                                    <td class="text-end"><?= number_format($th['charged']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($th['spent']) ?>원</td>
                                    <td class="text-end"><?= number_format($th['avg_rate']) ?>원</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 잔액 부족 알림 -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> 잔액 부족 병원</h6>
                    <?php if (count($low_balance) > 0): ?>
                    <span class="badge bg-danger"><?= count($low_balance) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($low_balance)): ?>
                    <div class="text-center text-body-secondary py-4">
                        <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:28px;"></i>
                        잔액 부족 병원 없음
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($low_balance as $lb): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div style="font-size:13px;" class="fw-semibold">
                                    <?= htmlspecialchars($lb['hospital_name'] ?: '병원 #' . $lb['hospital_id']) ?>
                                </div>
                                <small class="text-body-secondary">
                                    일 한도: <?= number_format($lb['daily_cap']) ?>원
                                    <?php if ($lb['is_paused']): ?>
                                    <span class="badge bg-warning text-dark ms-1">정지됨</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold <?= $lb['balance'] <= 10000 ? 'text-danger' : 'text-warning' ?>">
                                    <?= number_format($lb['balance']) ?>원
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 최근 거래 내역 -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clock-history"></i> 최근 거래 내역 (50건)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="sticky-top bg-body">
                        <tr>
                            <th>일시</th>
                            <th>병원명</th>
                            <th>유형</th>
                            <th class="text-end">금액</th>
                            <th class="text-end">잔액</th>
                            <th>내용</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_tx)): ?>
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">거래 내역 없음</td></tr>
                    <?php else: ?>
                    <?php
                    $type_cfg = [
                        'charge' => ['충전', 'bg-success'],
                        'deduct' => ['차감', 'bg-danger'],
                        'refund' => ['환불', 'bg-info'],
                        'adjust' => ['조정', 'bg-secondary'],
                    ];
                    foreach ($recent_tx as $tx):
                        $tc = $type_cfg[$tx['type']] ?? [$tx['type'], 'bg-secondary'];
                    ?>
                    <tr>
                        <td class="text-nowrap" style="font-size:12px;"><?= $tx['created_at'] ?></td>
                        <td style="font-size:13px;"><?= htmlspecialchars($tx['hospital_name'] ?: '병원 #' . $tx['hospital_id']) ?></td>
                        <td><span class="tx-type <?= $tc[1] ?> text-white"><?= $tc[0] ?></span></td>
                        <td class="text-end fw-bold <?= $tx['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $tx['amount'] >= 0 ? '+' : '' ?><?= number_format($tx['amount']) ?>원
                        </td>
                        <td class="text-end"><?= number_format($tx['balance_after']) ?>원</td>
                        <td style="font-size:12px;" class="text-body-secondary"><?= htmlspecialchars($tx['description']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
// 차트 데이터
var trendData = <?= json_encode($daily_trend, JSON_UNESCAPED_UNICODE) ?>;
var chartLabels = trendData.map(function(d) { return d.stat_date.substring(5); }); // MM-DD
var revenueData = trendData.map(function(d) { return parseInt(d.revenue); });
var viewsData = trendData.map(function(d) { return parseInt(d.views); });
var chargedData = trendData.map(function(d) { return parseInt(d.charged); });

var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)';
var textColor = isDark ? '#adb5bd' : '#6c757d';

var ctx = document.getElementById('trendChart').getContext('2d');
var trendChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [{
            label: '매출 (원)',
            data: revenueData,
            backgroundColor: 'rgba(37, 99, 235, 0.7)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
            y: {
                grid: { color: gridColor },
                ticks: {
                    color: textColor,
                    font: { size: 11 },
                    callback: function(v) { return v >= 10000 ? (v / 10000) + '만' : v.toLocaleString(); }
                }
            }
        }
    }
});

function switchChart(mode) {
    // 버튼 토글
    document.querySelectorAll('.btn-group .btn').forEach(function(b) { b.classList.remove('active'); });
    event.target.classList.add('active');

    if (mode === 'revenue') {
        trendChart.data.datasets = [{
            label: '매출 (원)',
            data: revenueData,
            backgroundColor: 'rgba(37, 99, 235, 0.7)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 1,
            borderRadius: 4
        }];
    } else {
        trendChart.data.datasets = [
            {
                label: '전체 조회',
                data: viewsData,
                backgroundColor: 'rgba(108, 117, 125, 0.5)',
                borderColor: 'rgba(108, 117, 125, 1)',
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: '과금 조회',
                data: chargedData,
                backgroundColor: 'rgba(220, 38, 38, 0.7)',
                borderColor: 'rgba(220, 38, 38, 1)',
                borderWidth: 1,
                borderRadius: 4
            }
        ];
    }
    trendChart.update();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

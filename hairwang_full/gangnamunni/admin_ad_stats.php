<?php
/**
 * 광고 통계 페이지
 * 노출수, 클릭수, CTR 일별/주별/월별 통계
 */

session_start();


$admin_page_title = '광고 통계';
$admin_current = 'ad_stats';

require_once __DIR__ . '/includes/admin_auth.php';
require_permission('ads');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);

// 기간 필터
$period = $_GET['period'] ?? '7d';
$ad_id_filter = intval($_GET['ad_id'] ?? 0);

switch ($period) {
    case '1d': $date_from = date('Y-m-d'); $label = '오늘'; break;
    case '7d': $date_from = date('Y-m-d', strtotime('-7 days')); $label = '최근 7일'; break;
    case '30d': $date_from = date('Y-m-d', strtotime('-30 days')); $label = '최근 30일'; break;
    case '90d': $date_from = date('Y-m-d', strtotime('-90 days')); $label = '최근 90일'; break;
    default: $date_from = date('Y-m-d', strtotime('-7 days')); $label = '최근 7일'; $period = '7d';
}
$date_to = date('Y-m-d');

$ad_where = $ad_id_filter ? "AND ad_id = $ad_id_filter" : "";

// 요약 통계
$summary_imp = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM gangnamunni_ad_impressions WHERE created_at >= '$date_from' $ad_where"))['cnt'] ?? 0;
$summary_click = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as cnt FROM gangnamunni_ad_clicks WHERE created_at >= '$date_from' $ad_where"))['cnt'] ?? 0;
$summary_ctr = $summary_imp > 0 ? round($summary_click / $summary_imp * 100, 2) : 0;

// 일별 통계 (차트용)
$daily_stats = [];
$sql = "SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM gangnamunni_ad_impressions
        WHERE created_at >= '$date_from' $ad_where GROUP BY dt ORDER BY dt";
$r = mysqli_query($connect, $sql);
$imp_by_date = [];
if ($r) { while ($row = mysqli_fetch_assoc($r)) { $imp_by_date[$row['dt']] = $row['cnt']; } }

$sql = "SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM gangnamunni_ad_clicks
        WHERE created_at >= '$date_from' $ad_where GROUP BY dt ORDER BY dt";
$r = mysqli_query($connect, $sql);
$click_by_date = [];
if ($r) { while ($row = mysqli_fetch_assoc($r)) { $click_by_date[$row['dt']] = $row['cnt']; } }

// 날짜 범위 생성
$dates = [];
$d = strtotime($date_from);
while ($d <= strtotime($date_to)) {
    $dt = date('Y-m-d', $d);
    $imp = $imp_by_date[$dt] ?? 0;
    $clk = $click_by_date[$dt] ?? 0;
    $dates[] = ['date' => $dt, 'impressions' => $imp, 'clicks' => $clk, 'ctr' => $imp > 0 ? round($clk/$imp*100,2) : 0];
    $d += 86400;
}

// 광고별 통계
$ad_stats = [];
$sql = "SELECT a.id, a.title, a.ad_type, a.is_active, a.impression_count, a.click_count,
        adv.hospital_name,
        (SELECT COUNT(*) FROM gangnamunni_ad_impressions i WHERE i.ad_id = a.id AND i.created_at >= '$date_from') as period_impressions,
        (SELECT COUNT(*) FROM gangnamunni_ad_clicks c WHERE c.ad_id = a.id AND c.created_at >= '$date_from') as period_clicks
        FROM gangnamunni_ads a
        LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
        ORDER BY period_impressions DESC";
$r = mysqli_query($connect, $sql);
if ($r) { while ($row = mysqli_fetch_assoc($r)) { $ad_stats[] = $row; } }

// 광고 목록 (필터용)
$all_ads = [];
$r = mysqli_query($connect, "SELECT id, title FROM gangnamunni_ads ORDER BY id");
if ($r) { while ($row = mysqli_fetch_assoc($r)) { $all_ads[] = $row; } }

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line"></i> 광고 통계</h4>
    <div class="d-flex gap-2">
        <select id="adFilter" class="form-select form-select-sm" style="width:auto;" onchange="applyFilter()">
            <option value="0">전체 광고</option>
            <?php foreach ($all_ads as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $ad_id_filter==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="btn-group btn-group-sm">
            <?php foreach (['1d'=>'오늘','7d'=>'7일','30d'=>'30일','90d'=>'90일'] as $k=>$v): ?>
            <a href="?period=<?= $k ?>&ad_id=<?= $ad_id_filter ?>" class="btn btn-outline-primary <?= $period===$k?'active':'' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 요약 카드 -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small">노출수 (<?= $label ?>)</div>
                <div class="display-6 fw-bold text-primary"><?= number_format($summary_imp) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small">클릭수 (<?= $label ?>)</div>
                <div class="display-6 fw-bold text-success"><?= number_format($summary_click) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small">CTR (<?= $label ?>)</div>
                <div class="display-6 fw-bold text-warning"><?= $summary_ctr ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- 일별 차트 -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-graph-up"></i> 일별 추이</div>
    <div class="card-body">
        <canvas id="dailyChart" height="80"></canvas>
    </div>
</div>

<!-- 광고별 통계 테이블 -->
<div class="card">
    <div class="card-header"><i class="bi bi-table"></i> 광고별 통계 (<?= $label ?>)</div>
    <div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>ID</th>
                <th>광고명</th>
                <th>광고주</th>
                <th>유형</th>
                <th>상태</th>
                <th>기간 노출</th>
                <th>기간 클릭</th>
                <th>CTR</th>
                <th>누적 노출</th>
                <th>누적 클릭</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($ad_stats)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
        <?php else: ?>
            <?php foreach ($ad_stats as $s): ?>
            <tr>
                <td><?= $s['id'] ?></td>
                <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                <td><?= htmlspecialchars($s['hospital_name'] ?? '-') ?></td>
                <td><span class="badge bg-info"><?= $s['ad_type'] ?></span></td>
                <td><?= $s['is_active'] ? '<span class="badge bg-success">활성</span>' : '<span class="badge bg-secondary">비활성</span>' ?></td>
                <td class="text-primary fw-bold"><?= number_format($s['period_impressions']) ?></td>
                <td class="text-success fw-bold"><?= number_format($s['period_clicks']) ?></td>
                <td>
                    <?php $ctr = $s['period_impressions'] > 0 ? round($s['period_clicks']/$s['period_impressions']*100,2) : 0; ?>
                    <span class="badge bg-<?= $ctr >= 3 ? 'success' : ($ctr >= 1 ? 'warning' : 'secondary') ?>"><?= $ctr ?>%</span>
                </td>
                <td><?= number_format($s['impression_count'] ?? 0) ?></td>
                <td><?= number_format($s['click_count'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

</div><!-- /admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function applyFilter() {
    var adId = document.getElementById('adFilter').value;
    location.href = '?period=<?= $period ?>&ad_id=' + adId;
}

// 차트 데이터
var chartData = <?= json_encode($dates) ?>;
var ctx = document.getElementById('dailyChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.map(function(d) { return d.date.substring(5); }),
        datasets: [
            {
                label: '노출수',
                data: chartData.map(function(d) { return d.impressions; }),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: '클릭수',
                data: chartData.map(function(d) { return d.clicks; }),
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { position: 'top' } }
    }
});

// 사이드바/테마
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.toggle('show');
});
document.getElementById('themeToggle')?.addEventListener('click', function() {
    var t = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('admin_theme', t);
    document.getElementById('themeIcon').className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon-stars');
});
(function(){
    var t = localStorage.getItem('admin_theme') || 'light';
    document.getElementById('themeIcon').className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon-stars');
})();
</script>
</body>
</html>
<?php mysqli_close($connect); ?>

<?php
/**
 * 월별 사용량 통계 대시보드
 * AI 호출, 예약, 후기, 이벤트, 회원 등 월별 추이
 * @created 2026-04-06
 */
session_start();
$admin_page_title = '월별 사용량 통계';
$admin_current = 'usage_stats';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('super_admin');

// 최근 6개월 월별 통계 수집
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}

$stats = [];
foreach ($months as $ym) {
    $y = substr($ym, 0, 4);
    $m = substr($ym, 5, 2);
    $start = "{$ym}-01 00:00:00";
    $end = date('Y-m-t 23:59:59', strtotime($start));

    $row = ['month' => $ym];

    // 병원 등록
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE created_at BETWEEN '{$start}' AND '{$end}' AND is_demo=0");
    $row['hospitals'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // 예약
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_bookings WHERE created_at BETWEEN '{$start}' AND '{$end}' AND is_demo=0");
    $row['bookings'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // 후기
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM hospital_reviews WHERE created_at BETWEEN '{$start}' AND '{$end}' AND is_demo=0");
    $row['reviews'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // 이벤트
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_events WHERE created_at BETWEEN '{$start}' AND '{$end}' AND is_demo=0");
    $row['events'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // 회원 가입
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE mb_datetime BETWEEN '{$start}' AND '{$end}'");
    $row['members'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // AI 호출
    $r = mysqli_query($connect, "SELECT COUNT(*) as c, COALESCE(SUM(input_tokens+output_tokens),0) as tokens FROM gangnamunni_ai_usage_log WHERE created_at BETWEEN '{$start}' AND '{$end}'");
    $ai = $r ? mysqli_fetch_assoc($r) : ['c' => 0, 'tokens' => 0];
    $row['ai_calls'] = intval($ai['c']);
    $row['ai_tokens'] = intval($ai['tokens']);

    // 쿠폰 발급
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_coupon_claims WHERE claimed_at BETWEEN '{$start}' AND '{$end}'");
    $row['coupon_claims'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

    // 포인트 적립
    $r = mysqli_query($connect, "SELECT COALESCE(SUM(amount),0) as total FROM gangnamunni_points WHERE type='earn' AND created_at BETWEEN '{$start}' AND '{$end}'");
    $row['points_earned'] = $r ? intval(mysqli_fetch_assoc($r)['total']) : 0;

    $stats[] = $row;
}

// 전체 누적
$totals = [];
$r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE is_demo=0"); $totals['hospitals'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;
$r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_bookings WHERE is_demo=0"); $totals['bookings'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;
$r = mysqli_query($connect, "SELECT COUNT(*) as c FROM hospital_reviews WHERE is_demo=0"); $totals['reviews'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;
$r = mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member"); $totals['members'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;
$r = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ai_usage_log"); $totals['ai_calls'] = $r ? intval(mysqli_fetch_assoc($r)['c']) : 0;

require_once __DIR__ . '/includes/admin_header.php';
?>

<h5 class="mb-3"><i class="bi bi-bar-chart-line me-2"></i>월별 사용량 통계</h5>

<!-- 누적 카드 -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => '병원', 'val' => $totals['hospitals'], 'icon' => 'bi-building', 'color' => 'primary'],
        ['label' => '예약', 'val' => $totals['bookings'], 'icon' => 'bi-calendar-check', 'color' => 'success'],
        ['label' => '후기', 'val' => $totals['reviews'], 'icon' => 'bi-chat-dots', 'color' => 'info'],
        ['label' => '회원', 'val' => $totals['members'], 'icon' => 'bi-people', 'color' => 'warning'],
        ['label' => 'AI 호출', 'val' => $totals['ai_calls'], 'icon' => 'bi-robot', 'color' => 'danger'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm text-center py-2">
            <i class="bi <?= $c['icon'] ?> text-<?= $c['color'] ?> fs-4"></i>
            <div class="fs-4 fw-bold"><?= number_format($c['val']) ?></div>
            <small class="text-muted"><?= $c['label'] ?></small>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 월별 차트 (Canvas) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-2"><h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>월별 추이 (최근 6개월)</h6></div>
    <div class="card-body">
        <canvas id="monthlyChart" height="80"></canvas>
    </div>
</div>

<!-- 월별 테이블 -->
<div class="card border-0 shadow-sm">
    <div class="card-header py-2"><h6 class="mb-0"><i class="bi bi-table me-1"></i>월별 상세</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 text-center">
            <thead class="table-light">
                <tr><th>월</th><th>병원</th><th>예약</th><th>후기</th><th>이벤트</th><th>회원</th><th>AI호출</th><th>AI토큰</th><th>쿠폰</th><th>포인트</th></tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $s): ?>
                <tr>
                    <td class="fw-bold"><?= substr($s['month'], 2) ?></td>
                    <td><?= $s['hospitals'] ?></td>
                    <td><?= $s['bookings'] ?></td>
                    <td><?= $s['reviews'] ?></td>
                    <td><?= $s['events'] ?></td>
                    <td><?= $s['members'] ?></td>
                    <td><?= $s['ai_calls'] ?></td>
                    <td><?= number_format($s['ai_tokens']) ?></td>
                    <td><?= $s['coupon_claims'] ?></td>
                    <td><?= number_format($s['points_earned']) ?>P</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const ctx = document.getElementById('monthlyChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($s) => substr($s['month'], 2), $stats)) ?>,
            datasets: [
                { label: '병원', data: <?= json_encode(array_column($stats, 'hospitals')) ?>, backgroundColor: 'rgba(13,110,253,0.7)' },
                { label: '예약', data: <?= json_encode(array_column($stats, 'bookings')) ?>, backgroundColor: 'rgba(25,135,84,0.7)' },
                { label: '후기', data: <?= json_encode(array_column($stats, 'reviews')) ?>, backgroundColor: 'rgba(13,202,240,0.7)' },
                { label: '회원', data: <?= json_encode(array_column($stats, 'members')) ?>, backgroundColor: 'rgba(255,193,7,0.7)' },
                { label: 'AI', data: <?= json_encode(array_column($stats, 'ai_calls')) ?>, backgroundColor: 'rgba(220,53,69,0.7)' },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

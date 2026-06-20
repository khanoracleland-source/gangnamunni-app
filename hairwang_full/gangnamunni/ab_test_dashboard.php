<?php
/**
 * A/B 테스트 대시보드
 *
 * 광고 성과 추적 및 분석
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) {
    header('Location: admin_ads.php');
    exit;
}

// A/B 테스트 목록 가져오기
$tests_query = "
    SELECT t.*,
           COUNT(v.id) as variant_count,
           SUM(v.impressions) as total_impressions,
           SUM(v.clicks) as total_clicks,
           SUM(v.conversions) as total_conversions
    FROM gangnamunni_ab_tests t
    LEFT JOIN gangnamunni_ab_variants v ON t.id = v.test_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
";
$tests_result = mysqli_query($connect, $tests_query);
$tests = [];
while ($row = mysqli_fetch_assoc($tests_result)) {
    $tests[] = $row;
}

// 특정 테스트의 상세 정보
$selected_test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;
$test_details = null;
$variants = [];

if ($selected_test_id > 0) {
    $test_query = "SELECT * FROM gangnamunni_ab_tests WHERE id = {$selected_test_id}";
    $test_result = mysqli_query($connect, $test_query);
    $test_details = mysqli_fetch_assoc($test_result);

    $variants_query = "SELECT * FROM gangnamunni_ab_variants WHERE test_id = {$selected_test_id} ORDER BY variant_name";
    $variants_result = mysqli_query($connect, $variants_query);
    while ($row = mysqli_fetch_assoc($variants_result)) {
        $variants[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 A/B 테스트 대시보드</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 32px; margin-bottom: 10px; color: #333; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #333; }
        .stat-card .change { font-size: 12px; margin-top: 5px; }
        .change.up { color: #4caf50; }
        .change.down { color: #f44336; }
        .tests-table { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-running { background: #e3f2fd; color: #1976d2; }
        .status-paused { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e9; color: #388e3c; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-view { background: #2196f3; color: white; }
        .test-detail { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .variants-comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .variant-card { background: #f8f9fa; padding: 20px; border-radius: 12px; }
        .variant-card h3 { margin-bottom: 15px; }
        .metric { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; }
        .metric-label { color: #666; font-size: 14px; }
        .metric-value { font-weight: 600; font-size: 16px; }
        .winner { border: 3px solid #4caf50; position: relative; }
        .winner::before { content: '🏆 승리'; position: absolute; top: -10px; right: 10px; background: #4caf50; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .chart-container { margin-top: 30px; height: 300px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_ads.php" class="back-link">← 광고 관리로 돌아가기</a>

        <h1>🧪 A/B 테스트 대시보드</h1>

        <!-- 전체 통계 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>활성 테스트</h3>
                <div class="value"><?php
                    $active_count = 0;
                    foreach ($tests as $test) {
                        if ($test['status'] === 'running') $active_count++;
                    }
                    echo $active_count;
                ?></div>
            </div>
            <div class="stat-card">
                <h3>총 노출 수</h3>
                <div class="value"><?php
                    $total_impressions = 0;
                    foreach ($tests as $test) {
                        $total_impressions += $test['total_impressions'];
                    }
                    echo number_format($total_impressions);
                ?></div>
            </div>
            <div class="stat-card">
                <h3>총 클릭 수</h3>
                <div class="value"><?php
                    $total_clicks = 0;
                    foreach ($tests as $test) {
                        $total_clicks += $test['total_clicks'];
                    }
                    echo number_format($total_clicks);
                ?></div>
            </div>
            <div class="stat-card">
                <h3>평균 CTR</h3>
                <div class="value"><?php
                    $avg_ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions * 100) : 0;
                    echo number_format($avg_ctr, 2);
                ?>%</div>
            </div>
        </div>

        <!-- 테스트 목록 -->
        <div class="tests-table">
            <h2 style="margin-bottom: 20px;">A/B 테스트 목록</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>테스트명</th>
                        <th>상태</th>
                        <th>시작일</th>
                        <th>종료일</th>
                        <th>버전 수</th>
                        <th>노출</th>
                        <th>클릭</th>
                        <th>전환</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tests)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                            등록된 A/B 테스트가 없습니다.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tests as $test): ?>
                        <tr>
                            <td><?= $test['id'] ?></td>
                            <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                            <td>
                                <span class="status-badge status-<?= $test['status'] ?>">
                                    <?php
                                    $status_text = [
                                        'running' => '진행중',
                                        'paused' => '일시정지',
                                        'completed' => '완료'
                                    ];
                                    echo $status_text[$test['status']] ?? $test['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($test['start_date'])) ?></td>
                            <td><?= $test['end_date'] ? date('Y-m-d', strtotime($test['end_date'])) : '-' ?></td>
                            <td><?= $test['variant_count'] ?></td>
                            <td><?= number_format($test['total_impressions']) ?></td>
                            <td><?= number_format($test['total_clicks']) ?></td>
                            <td><?= number_format($test['total_conversions']) ?></td>
                            <td>
                                <a href="?test_id=<?= $test['id'] ?>" class="btn btn-view">상세보기</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 테스트 상세 -->
        <?php if ($test_details): ?>
        <div class="test-detail">
            <h2><?= htmlspecialchars($test_details['test_name']) ?></h2>
            <p style="color: #666; margin-top: 5px;"><?= htmlspecialchars($test_details['description']) ?></p>

            <div class="variants-comparison">
                <?php foreach ($variants as $index => $variant): ?>
                <div class="variant-card <?= $test_details['winner_variant'] === $variant['variant_name'] ? 'winner' : '' ?>">
                    <h3>버전 <?= htmlspecialchars($variant['variant_name']) ?></h3>

                    <div class="metric">
                        <span class="metric-label">광고 제목</span>
                        <span class="metric-value"><?= htmlspecialchars($variant['title']) ?></span>
                    </div>

                    <div class="metric">
                        <span class="metric-label">노출 수</span>
                        <span class="metric-value"><?= number_format($variant['impressions']) ?></span>
                    </div>

                    <div class="metric">
                        <span class="metric-label">클릭 수</span>
                        <span class="metric-value"><?= number_format($variant['clicks']) ?></span>
                    </div>

                    <div class="metric">
                        <span class="metric-label">전환 수</span>
                        <span class="metric-value"><?= number_format($variant['conversions']) ?></span>
                    </div>

                    <div class="metric">
                        <span class="metric-label">CTR (클릭률)</span>
                        <span class="metric-value" style="color: #667eea;"><?= number_format($variant['ctr'], 2) ?>%</span>
                    </div>

                    <div class="metric">
                        <span class="metric-label">전환율</span>
                        <span class="metric-value" style="color: #4caf50;"><?= number_format($variant['conversion_rate'], 2) ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 차트 -->
            <div class="chart-container">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>

        <script>
            // 비교 차트
            const ctx = document.getElementById('comparisonChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['노출 수', '클릭 수', '전환 수'],
                    datasets: [
                        <?php foreach ($variants as $index => $variant): ?>
                        {
                            label: '버전 <?= $variant['variant_name'] ?>',
                            data: [
                                <?= $variant['impressions'] ?>,
                                <?= $variant['clicks'] ?>,
                                <?= $variant['conversions'] ?>
                            ],
                            backgroundColor: '<?= $index === 0 ? 'rgba(102, 126, 234, 0.8)' : 'rgba(118, 75, 162, 0.8)' ?>',
                        },
                        <?php endforeach; ?>
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($connect); ?>

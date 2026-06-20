<?php
/**
 * 시술 가격 이력 차트 컴포넌트
 * Chart.js 라인 차트 (최소가/최대가 + 시장 평균)
 *
 * 사용법:
 *   require_once __DIR__ . '/includes/treatment_price_chart.php';
 *   renderTreatmentPriceChart($connect, $hospital_id, $treatment_id);
 *   renderTreatmentPriceChart($connect, $hospital_id, $treatment_id, true); // compact
 *
 * 의존성: Chart.js (admin_header.php CDN에서 로드됨)
 */

/**
 * 시술 가격 이력 차트 렌더링
 *
 * @param mysqli $connect      DB 연결
 * @param int    $hospital_id  병원 ID
 * @param int    $treatment_id 시술 ID
 * @param bool   $compact      컴팩트 모드 (모달/카드용, 200px)
 */
function renderTreatmentPriceChart($connect, $hospital_id, $treatment_id, $compact = false) {
    $hospital_id  = intval($hospital_id);
    $treatment_id = intval($treatment_id);

    if ($hospital_id <= 0 || $treatment_id <= 0) {
        echo '<div class="text-muted small">차트 데이터 없음</div>';
        return;
    }

    // 고유 캔버스 ID (한 페이지에 여러 차트 가능)
    $canvas_id = 'priceChart_' . $hospital_id . '_' . $treatment_id . '_' . mt_rand(1000, 9999);
    $height = $compact ? 200 : 300;

    // ── 12개월 이력 조회 ──
    $stmt = mysqli_prepare($connect,
        "SELECT price_min, price_max, DATE_FORMAT(recorded_at, '%Y-%m-%d') as recorded_date
         FROM gangnamunni_treatment_price_history
         WHERE hospital_id = ? AND treatment_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
         ORDER BY recorded_at ASC"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $labels   = [];
    $min_data = [];
    $max_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $labels[]   = $row['recorded_date'];
        $min_data[] = intval($row['price_min']);
        $max_data[] = intval($row['price_max']);
    }
    mysqli_stmt_close($stmt);

    // ── 현재 가격 (hospital_treatments) ──
    $current_min = 0;
    $current_max = 0;
    $stmt = mysqli_prepare($connect,
        "SELECT price_min, price_max FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($cur = mysqli_fetch_assoc($result)) {
        $current_min = intval($cur['price_min']);
        $current_max = intval($cur['price_max']);
    }
    mysqli_stmt_close($stmt);

    // ── 시장 평균 (treatment_master) ──
    $avg_min = 0;
    $avg_max = 0;
    $treatment_name = '';
    $stmt = mysqli_prepare($connect,
        "SELECT name, avg_price_min, avg_price_max FROM gangnamunni_treatment_master WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($tm = mysqli_fetch_assoc($result)) {
        $treatment_name = $tm['name'];
        $avg_min = intval($tm['avg_price_min']);
        $avg_max = intval($tm['avg_price_max']);
    }
    mysqli_stmt_close($stmt);

    // 데이터 없는 경우
    $has_data = count($labels) > 0;

    // 현재 가격이 있으면 마지막 포인트로 추가 (오늘 날짜)
    if ($current_min > 0 || $current_max > 0) {
        $today = date('Y-m-d');
        if (empty($labels) || end($labels) !== $today) {
            $labels[]   = $today;
            $min_data[] = $current_min;
            $max_data[] = $current_max;
        }
    }

    // JSON 인코딩
    $labels_json   = json_encode($labels);
    $min_data_json = json_encode($min_data);
    $max_data_json = json_encode($max_data);

    // 만원 변환 헬퍼
    $format_price = function($val) {
        if ($val <= 0) return '-';
        if ($val >= 10000) {
            $man = floor($val / 10000);
            $rem = $val % 10000;
            return $rem > 0 ? "{$man}만 " . number_format($rem) . "원" : "{$man}만원";
        }
        return number_format($val) . '원';
    };

    $current_text = '';
    if ($current_min > 0 || $current_max > 0) {
        $current_text = $format_price($current_min) . ' ~ ' . $format_price($current_max);
    }
    $avg_text = '';
    if ($avg_min > 0 || $avg_max > 0) {
        $avg_text = $format_price($avg_min) . ' ~ ' . $format_price($avg_max);
    }
?>
<div class="treatment-price-chart-wrap" data-chart-id="<?= $canvas_id ?>">
    <?php if (!$compact): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($treatment_name) ?> 가격 추이</h6>
        <div class="small">
            <?php if ($current_text): ?>
                <span class="badge bg-primary">현재 <?= $current_text ?></span>
            <?php endif; ?>
            <?php if ($avg_text): ?>
                <span class="badge bg-secondary ms-1">시장 평균 <?= $avg_text ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$has_data && $current_min <= 0 && $current_max <= 0): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-graph-up"></i> 가격 이력이 없습니다
        </div>
    <?php else: ?>
        <canvas id="<?= $canvas_id ?>" height="<?= $height ?>"></canvas>
        <?php if ($compact && $current_text): ?>
            <div class="text-center small text-muted mt-1">현재 <?= $current_text ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($has_data || $current_min > 0 || $current_max > 0): ?>
<script>
(function() {
    // 다크모드 감지
    var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
    var textColor = isDark ? '#adb5bd' : '#6c757d';

    var ctx = document.getElementById('<?= $canvas_id ?>');
    if (!ctx) return;

    var labels = <?= $labels_json ?>;
    var minData = <?= $min_data_json ?>;
    var maxData = <?= $max_data_json ?>;

    var datasets = [
        {
            label: '최소가',
            data: minData,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.3,
            pointRadius: <?= $compact ? 2 : 4 ?>,
            pointHoverRadius: 6
        },
        {
            label: '최대가',
            data: maxData,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220,53,69,0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.3,
            pointRadius: <?= $compact ? 2 : 4 ?>,
            pointHoverRadius: 6
        }
    ];

    <?php if (!$compact && $avg_min > 0): ?>
    // 시장 평균선 (full 모드만)
    var avgMinLine = Array(labels.length).fill(<?= $avg_min ?>);
    var avgMaxLine = Array(labels.length).fill(<?= $avg_max ?>);
    datasets.push({
        label: '시장 평균(최소)',
        data: avgMinLine,
        borderColor: 'rgba(13,110,253,0.3)',
        borderWidth: 1,
        borderDash: [5, 5],
        fill: false,
        pointRadius: 0,
        pointHoverRadius: 0
    });
    datasets.push({
        label: '시장 평균(최대)',
        data: avgMaxLine,
        borderColor: 'rgba(220,53,69,0.3)',
        borderWidth: 1,
        borderDash: [5, 5],
        fill: false,
        pointRadius: 0,
        pointHoverRadius: 0
    });
    <?php endif; ?>

    // 만원 단위 포맷
    function formatManwon(val) {
        if (val >= 10000) {
            var man = Math.floor(val / 10000);
            var rem = val % 10000;
            return rem > 0 ? man + '만 ' + rem.toLocaleString() + '원' : man + '만원';
        }
        return val.toLocaleString() + '원';
    }

    new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: <?= $compact ? 'false' : 'true' ?>,
                    labels: { color: textColor, boxWidth: 12, padding: 8 }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + formatManwon(ctx.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        maxRotation: 45,
                        maxTicksLimit: <?= $compact ? 5 : 10 ?>,
                        font: { size: <?= $compact ? 10 : 12 ?> }
                    }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: function(val) { return formatManwon(val); },
                        font: { size: <?= $compact ? 10 : 12 ?> }
                    },
                    beginAtZero: false
                }
            }
        }
    });

    // 다크모드 전환 시 차트 색상 업데이트
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-bs-theme') {
                // 페이지 새로고침으로 대응 (Chart.js 업데이트 복잡도 회피)
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
})();
</script>
<?php endif; ?>
<?php
}

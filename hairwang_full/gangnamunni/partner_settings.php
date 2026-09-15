<?php
/**
 * 병원 파트너 예산 설정 페이지 (standalone, 관리자 헤더 없음)
 * 모바일 친화적 레이아웃
 */
session_start();

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

// 병원 ID: 쿼리 파라미터 또는 세션
$hospital_id = intval($_GET['hospital_id'] ?? $_SESSION['partner_hospital_id'] ?? 0);
if ($hospital_id <= 0) {
    die('<div style="text-align:center;padding:60px 20px;font-family:sans-serif;"><h3>접근 오류</h3><p>병원 정보를 확인할 수 없습니다.</p></div>');
}
$_SESSION['partner_hospital_id'] = $hospital_id;

// 병원 정보 조회
$hospital_name = '';
$stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $h = mysqli_fetch_assoc($r);
    $hospital_name = $h ? $h['name'] : '병원 #' . $hospital_id;
    mysqli_stmt_close($stmt);
}

// 지갑 정보 로드
$wallet = null;
$stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($r);
    mysqli_stmt_close($stmt);
}

// 지갑 없으면 기본값
if (!$wallet) {
    $wallet = [
        'balance'       => 0,
        'total_charged' => 0,
        'total_spent'   => 0,
        'daily_cap'     => 50000,
        'is_active'     => 1,
        'is_paused'     => 0,
    ];
}

$message = '';
$error = '';

// ── POST 저장 처리 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $daily_cap       = intval($_POST['daily_cap'] ?? 50000);
    $alert_threshold = intval($_POST['alert_threshold'] ?? 10000);
    $auto_pause      = isset($_POST['auto_pause']) ? 1 : 0;
    $is_paused       = isset($_POST['is_paused']) ? 1 : 0;

    // 값 범위 검증
    $daily_cap = max(0, min(10000000, $daily_cap));
    $alert_threshold = max(0, min($daily_cap, $alert_threshold));

    // UPSERT
    $check = mysqli_prepare($connect, "SELECT hospital_id FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
    mysqli_stmt_bind_param($check, 'i', $hospital_id);
    mysqli_stmt_execute($check);
    $exists = mysqli_stmt_get_result($check)->fetch_assoc();
    mysqli_stmt_close($check);

    if ($exists) {
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_cpv_wallets SET daily_cap = ?, is_paused = ? WHERE hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $daily_cap, $is_paused, $hospital_id);
    } else {
        $zero = 0;
        $one = 1;
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_wallets (hospital_id, balance, total_charged, total_spent, daily_cap, is_active, is_paused)
             VALUES (?, 0, 0, 0, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'iii', $hospital_id, $daily_cap, $is_paused);
    }

    if (mysqli_stmt_execute($stmt)) {
        $message = '설정이 저장되었습니다.';
        // 리로드 지갑 데이터
        $wallet['daily_cap'] = $daily_cap;
        $wallet['is_paused'] = $is_paused;
    } else {
        $error = '저장에 실패했습니다. 다시 시도해주세요.';
    }
    mysqli_stmt_close($stmt);
}

// 오늘 사용량 조회
$today_spent = 0;
$stmt = mysqli_prepare($connect,
    "SELECT total_amount FROM gangnamunni_cpv_daily_stats WHERE hospital_id = ? AND stat_date = CURDATE()");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $ds = mysqli_fetch_assoc($r);
    if ($ds) $today_spent = (float) $ds['total_amount'];
    mysqli_stmt_close($stmt);
}

$daily_cap = (int) $wallet['daily_cap'];
$usage_pct = $daily_cap > 0 ? min(100, round($today_spent / $daily_cap * 100)) : 0;
$balance = (float) $wallet['balance'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>파트너 예산 설정 - <?= htmlspecialchars($hospital_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* 다크모드 anti-flash */
        html { background: var(--bs-body-bg, #fff); }
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }
        .partner-wrap { max-width: 600px; margin: 0 auto; padding: 20px 16px 40px; }
        .stat-card { border-radius: 16px; padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .stat-label { font-size: 13px; opacity: 0.7; }
        .slider-val { font-size: 20px; font-weight: 700; color: var(--bs-primary); }
        .progress { height: 12px; border-radius: 6px; }
    </style>
    <script>
        // 다크모드 anti-flash
        (function() {
            var t = localStorage.getItem('partner_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body>

<div class="partner-wrap">
    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0"><i class="bi bi-gear"></i> 예산 설정</h5>
            <small class="text-body-secondary"><?= htmlspecialchars($hospital_name) ?></small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="themeBtn" onclick="toggleTheme()">
            <i class="bi bi-moon-stars" id="themeIco"></i>
        </button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-x-circle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 잔액 & 오늘 사용량 -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="stat-card bg-primary text-white">
                <div class="stat-label">현재 잔액</div>
                <div class="stat-value"><?= number_format($balance) ?></div>
                <small>원</small>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card bg-body-secondary">
                <div class="stat-label">오늘 사용</div>
                <div class="stat-value"><?= number_format($today_spent) ?></div>
                <small>원</small>
            </div>
        </div>
    </div>

    <!-- 오늘 예산 사용률 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold">오늘 예산 사용률</span>
                <span class="fw-bold <?= $usage_pct >= 90 ? 'text-danger' : ($usage_pct >= 70 ? 'text-warning' : 'text-success') ?>">
                    <?= $usage_pct ?>%
                </span>
            </div>
            <div class="progress">
                <div class="progress-bar <?= $usage_pct >= 90 ? 'bg-danger' : ($usage_pct >= 70 ? 'bg-warning' : 'bg-success') ?>"
                     style="width: <?= $usage_pct ?>%"></div>
            </div>
            <small class="text-body-secondary mt-1 d-block">
                <?= number_format($today_spent) ?>원 / <?= number_format($daily_cap) ?>원
            </small>
        </div>
    </div>

    <!-- 설정 폼 -->
    <form method="POST">
        <input type="hidden" name="save_settings" value="1">

        <!-- 일일 예산 한도 슬라이더 -->
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold">
                    <i class="bi bi-speedometer2"></i> 일일 예산 한도
                </label>
                <div class="text-center mb-2">
                    <span class="slider-val" id="capDisplay"><?= number_format($daily_cap) ?>원</span>
                </div>
                <input type="range" class="form-range" id="dailyCapSlider"
                       min="0" max="1000000" step="10000" value="<?= $daily_cap ?>"
                       oninput="updateCapDisplay(this.value)">
                <input type="hidden" name="daily_cap" id="dailyCapInput" value="<?= $daily_cap ?>">
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-body-secondary">0원</small>
                    <small class="text-body-secondary">100만원</small>
                </div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(10000)">1만</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(50000)">5만</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(100000)">10만</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(300000)">30만</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(500000)">50만</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCap(1000000)">100만</button>
                </div>
            </div>
        </div>

        <!-- 잔액 알림 임계값 -->
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold">
                    <i class="bi bi-bell"></i> 잔액 부족 알림
                </label>
                <p class="text-body-secondary mb-2" style="font-size:13px;">
                    잔액이 이 금액 이하로 떨어지면 알림을 보내드립니다.
                </p>
                <div class="input-group">
                    <input type="number" class="form-control" name="alert_threshold"
                           value="10000" min="0" max="10000000" step="1000">
                    <span class="input-group-text">원</span>
                </div>
            </div>
        </div>

        <!-- 일시정지 & 자동정지 -->
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold">
                    <i class="bi bi-pause-circle"></i> 과금 제어
                </label>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_paused" id="isPaused"
                           <?= $wallet['is_paused'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isPaused">
                        과금 일시정지
                        <small class="text-body-secondary d-block">활성화하면 CPV 과금이 즉시 중단됩니다.</small>
                    </label>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="auto_pause" id="autoPause" checked>
                    <label class="form-check-label" for="autoPause">
                        잔액 소진 시 자동 정지
                        <small class="text-body-secondary d-block">잔액이 0원이 되면 과금을 자동으로 중단합니다.</small>
                    </label>
                </div>
            </div>
        </div>

        <!-- 저장 버튼 -->
        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-check-lg"></i> 설정 저장
        </button>
    </form>

    <!-- 최근 거래 내역 -->
    <div class="mt-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history"></i> 최근 거래 내역</h6>
        <?php
        $stmt = mysqli_prepare($connect,
            "SELECT type, amount, balance_after, description, created_at
             FROM gangnamunni_cpv_transactions
             WHERE hospital_id = ?
             ORDER BY created_at DESC LIMIT 10");
        $transactions = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($r)) $transactions[] = $row;
            mysqli_stmt_close($stmt);
        }
        ?>

        <?php if (empty($transactions)): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="bi bi-inbox d-block mb-2" style="font-size:28px;"></i>
            거래 내역이 없습니다.
        </div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($transactions as $tx): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold" style="font-size:13px;">
                        <?php
                        $type_labels = ['charge' => '충전', 'deduct' => '차감', 'refund' => '환불', 'adjust' => '조정'];
                        echo $type_labels[$tx['type']] ?? $tx['type'];
                        ?>
                    </div>
                    <small class="text-body-secondary"><?= htmlspecialchars($tx['description']) ?></small>
                    <small class="text-body-secondary d-block"><?= $tx['created_at'] ?></small>
                </div>
                <div class="text-end">
                    <div class="fw-bold <?= $tx['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $tx['amount'] >= 0 ? '+' : '' ?><?= number_format($tx['amount']) ?>원
                    </div>
                    <small class="text-body-secondary">잔액 <?= number_format($tx['balance_after']) ?>원</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateCapDisplay(val) {
    document.getElementById('capDisplay').textContent = Number(val).toLocaleString() + '원';
    document.getElementById('dailyCapInput').value = val;
}

function setCap(val) {
    document.getElementById('dailyCapSlider').value = val;
    updateCapDisplay(val);
}

function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-bs-theme');
    var next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('partner_theme', next);
    var ico = document.getElementById('themeIco');
    ico.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
}

// 초기 아이콘 설정
(function() {
    var t = localStorage.getItem('partner_theme') || 'light';
    var ico = document.getElementById('themeIco');
    if (ico) ico.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
})();
</script>
</body>
</html>

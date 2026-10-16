<?php
/**
 * 자동 테스트 시스템
 * 관리자 레이아웃 + 모든 기능 자동 테스트
 */

session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단

// 관리자 권한 확인
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
    loadThemeSettings($connect);
}

// 테스트 결과 저장
$test_results = [];
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

function test($name, $callback) {
    global $test_results, $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    $start_time = microtime(true);
    try {
        $result = $callback();
        $success = $result['success'];
        $message = $result['message'] ?? '';
        $details = $result['details'] ?? '';
        if ($success) $passed_tests++; else $failed_tests++;
    } catch (Exception $e) {
        $success = false;
        $message = $e->getMessage();
        $details = $e->getTraceAsString();
        $failed_tests++;
    }
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    $test_results[] = ['name' => $name, 'success' => $success, 'message' => $message, 'details' => $details, 'time' => $execution_time];
    return $success;
}

// ========================================
// 테스트 실행
// ========================================
test('데이터베이스 연결', function() use ($connect) {
    if ($connect && !mysqli_connect_error()) return ['success' => true, 'message' => 'DB 연결 성공: ' . G5_MYSQL_DB];
    return ['success' => false, 'message' => '연결 실패: ' . mysqli_connect_error()];
});

test('필수 테이블 존재 확인', function() use ($connect) {
    $tables = ['gangnamunni_hospitals','hospital_reviews','gangnamunni_events','gangnamunni_ads','gangnamunni_consultations'];
    $missing = [];
    foreach ($tables as $t) { if (mysqli_num_rows(mysqli_query($connect, "SHOW TABLES LIKE '$t'")) == 0) $missing[] = $t; }
    if (empty($missing)) return ['success' => true, 'message' => count($tables) . '개 테이블 모두 존재'];
    return ['success' => false, 'message' => '누락: ' . implode(', ', $missing)];
});

test('PHP 버전 체크', function() {
    $v = PHP_VERSION;
    return version_compare($v, '8.0.0', '>=') ? ['success' => true, 'message' => "PHP $v"] : ['success' => false, 'message' => "PHP $v < 8.0 필요"];
});

test('PHP 확장 모듈', function() {
    $exts = ['mysqli','json','mbstring','curl'];
    $missing = array_filter($exts, fn($e) => !extension_loaded($e));
    return empty($missing) ? ['success' => true, 'message' => count($exts) . '개 모듈 OK'] : ['success' => false, 'message' => '누락: ' . implode(', ', $missing)];
});

test('파일 권한 체크', function() {
    $files = [__DIR__.'/dashboard.php', __DIR__.'/admin_ads.php', __DIR__.'/assets/css/common.css'];
    $issues = [];
    foreach ($files as $f) { if (!file_exists($f)) $issues[] = basename($f).' (없음)'; elseif (!is_readable($f)) $issues[] = basename($f).' (읽기불가)'; }
    return empty($issues) ? ['success' => true, 'message' => count($files) . '개 파일 접근 가능'] : ['success' => false, 'message' => implode(', ', $issues)];
});

test('CSS 파일 확인', function() {
    $css = [__DIR__.'/assets/css/common.css', __DIR__.'/assets/css/tailwind.css'];
    $missing = array_filter($css, fn($f) => !file_exists($f));
    return empty($missing) ? ['success' => true, 'message' => count($css) . '개 CSS OK'] : ['success' => false, 'message' => '누락: ' . implode(', ', array_map('basename', $missing))];
});

test('병원 데이터 조회', function() use ($connect) {
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals");
    return $r ? ['success' => true, 'message' => mysqli_fetch_assoc($r)['cnt'] . '개 병원'] : ['success' => false, 'message' => mysqli_error($connect)];
});

test('리뷰 데이터 조회', function() use ($connect) {
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews");
    return $r ? ['success' => true, 'message' => mysqli_fetch_assoc($r)['cnt'] . '개 리뷰'] : ['success' => false, 'message' => mysqli_error($connect)];
});

test('광고 데이터 조회', function() use ($connect) {
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_ads WHERE is_active = 1");
    return $r ? ['success' => true, 'message' => mysqli_fetch_assoc($r)['cnt'] . '개 활성 광고'] : ['success' => false, 'message' => mysqli_error($connect)];
});

test('세션 기능', function() {
    $_SESSION['test_key'] = 'test_value';
    $ok = isset($_SESSION['test_key']) && $_SESSION['test_key'] === 'test_value';
    unset($_SESSION['test_key']);
    return $ok ? ['success' => true, 'message' => '세션 읽기/쓰기 정상'] : ['success' => false, 'message' => '세션 기능 오류'];
});

test('디렉토리 쓰기 권한', function() {
    $dirs = [__DIR__.'/cache', __DIR__.'/logs'];
    $issues = [];
    foreach ($dirs as $d) { if (!is_dir($d)) @mkdir($d, 0755, true); if (!is_writable($d)) $issues[] = basename($d); }
    return empty($issues) ? ['success' => true, 'message' => count($dirs) . '개 디렉토리 쓰기 가능'] : ['success' => false, 'message' => '쓰기 불가: ' . implode(', ', $issues)];
});

test('메인 페이지 접근', function() {
    $ch = curl_init('https://hms.seoulbeauty.app/hairwang/app/index.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code === 200 ? ['success' => true, 'message' => "HTTP $code"] : ['success' => false, 'message' => "HTTP $code"];
});

test('대시보드 접근', function() {
    $ch = curl_init('https://hms.seoulbeauty.app/hairwang/app/dashboard.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($code === 200 && strpos($body, '서울미녀') !== false) ? ['success' => true, 'message' => "HTTP $code + 콘텐츠 확인"] : ['success' => false, 'message' => "HTTP $code"];
});

test('JSON 처리', function() {
    $data = ['test'=>'테스트','number'=>123]; return json_decode(json_encode($data), true) === $data ? ['success' => true, 'message' => 'JSON OK'] : ['success' => false, 'message' => json_last_error_msg()];
});

test('메모리 사용량', function() {
    $mb = round(memory_get_usage(true)/1024/1024, 2); $limit = ini_get('memory_limit');
    return $mb < 100 ? ['success' => true, 'message' => "{$mb}MB (한도: {$limit})"] : ['success' => false, 'message' => "{$mb}MB 과다"];
});

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;

// 공통 헤더
$admin_page_title = '자동 테스트';
$admin_current = 'auto_test';
$admin_extra_css = '
    .test-summary-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px; }
    .summary-card { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); padding:20px; border-radius:12px; text-align:center; }
    .summary-card h3 { font-size:36px; font-weight:700; margin:8px 0; }
    .summary-card.total h3 { color:#667eea; }
    .summary-card.passed h3 { color:#198754; }
    .summary-card.failed h3 { color:#dc3545; }
    .test-item { border:2px solid var(--bs-border-color); border-radius:8px; padding:12px 16px; margin-bottom:10px; transition:all 0.2s; }
    .test-item:hover { transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .test-item.success { border-color:#198754; background:rgba(25,135,84,0.05); }
    .test-item.failed { border-color:#dc3545; background:rgba(220,53,69,0.05); }
    .test-time { color:var(--bs-secondary-color); font-size:12px; }
    @media (max-width:767px) { .test-summary-grid { grid-template-columns:1fr; } }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> 자동 테스트 시스템</h5>
    <button onclick="location.reload()" class="btn btn-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> 재테스트</button>
</div>

<!-- 요약 -->
<div class="test-summary-grid">
    <div class="summary-card total">
        <div class="text-body-secondary small">총 테스트</div>
        <h3><?= $total_tests ?></h3>
    </div>
    <div class="summary-card passed">
        <div class="text-body-secondary small">통과</div>
        <h3><?= $passed_tests ?></h3>
    </div>
    <div class="summary-card failed">
        <div class="text-body-secondary small">실패</div>
        <h3><?= $failed_tests ?></h3>
    </div>
</div>

<!-- 성공률 -->
<div class="mb-3">
    <div class="d-flex justify-content-between mb-1">
        <span class="fw-bold small">성공률</span>
        <span class="fw-bold small"><?= $success_rate ?>%</span>
    </div>
    <div class="progress" style="height:24px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated <?= $failed_tests > 0 ? 'bg-warning' : 'bg-success' ?>"
             style="width:<?= $success_rate ?>%"><?= $passed_tests ?> / <?= $total_tests ?></div>
    </div>
</div>

<!-- 테스트 결과 -->
<div class="card">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-list-check"></i> 테스트 결과</h6>
    </div>
    <div class="card-body">
        <?php foreach ($test_results as $i => $r): ?>
        <div class="test-item <?= $r['success'] ? 'success' : 'failed' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge <?= $r['success'] ? 'bg-success' : 'bg-danger' ?>"><?= $r['success'] ? 'PASS' : 'FAIL' ?></span>
                    <strong class="ms-1"><?= ($i+1) ?>. <?= htmlspecialchars($r['name']) ?></strong>
                    <span class="test-time ms-2"><?= $r['time'] ?>ms</span>
                    <div class="text-body-secondary small mt-1"><?= htmlspecialchars($r['message']) ?></div>
                    <?php if (!empty($r['details'])): ?>
                    <div class="mt-1"><small class="text-body-secondary"><?= htmlspecialchars($r['details']) ?></small></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 결과 요약 -->
<div class="alert <?= $failed_tests === 0 ? 'alert-success' : 'alert-warning' ?> mt-3 py-2 text-center">
    <?php if ($failed_tests === 0): ?>
        <strong>모든 테스트 통과!</strong> 시스템이 정상적으로 작동합니다.
    <?php else: ?>
        <strong><?= $failed_tests ?>개의 테스트가 실패했습니다.</strong> 위의 오류를 확인하세요.
    <?php endif; ?>
    <div class="small text-body-secondary mt-1">테스트 완료: <?= date('Y-m-d H:i:s') ?></div>
</div>

<?php
if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

<?php
/**
 * 기능 테스트 페이지
 * 관리자 레이아웃 + 각 기능 독립 테스트
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

// 테스트 실행
$test_type = $_GET['test'] ?? '';
$test_results = [];

if ($test_type === 'search' || empty($test_type)) {
    $keyword = '강남';
    $query = "SELECT * FROM gangnamunni_hospitals WHERE name LIKE '%{$keyword}%' LIMIT 5";
    $result = mysqli_query($connect, $query);
    $test_results['search'] = [
        'name' => '병원 검색', 'success' => $result && mysqli_num_rows($result) > 0,
        'count' => $result ? mysqli_num_rows($result) : 0, 'query' => $query
    ];
}

if ($test_type === 'detail' || empty($test_type)) {
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals ORDER BY id DESC LIMIT 1");
    $hospital = mysqli_fetch_assoc($result);
    $test_results['detail'] = [
        'name' => '병원 상세 조회', 'success' => $hospital !== null,
        'hospital_id' => $hospital['id'] ?? null, 'hospital_name' => $hospital['name'] ?? null
    ];
}

if ($test_type === 'reviews' || empty($test_type)) {
    $result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews");
    $row = mysqli_fetch_assoc($result);
    $test_results['reviews'] = ['name' => '리뷰 조회', 'success' => $result !== false, 'count' => $row['cnt']];
}

if ($test_type === 'events' || empty($test_type)) {
    $result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_events WHERE is_active = 1");
    $row = mysqli_fetch_assoc($result);
    $test_results['events'] = ['name' => '이벤트 조회', 'success' => $result !== false, 'count' => $row['cnt']];
}

if ($test_type === 'ads' || empty($test_type)) {
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_ads WHERE is_active = 1 LIMIT 3");
    $test_results['ads'] = ['name' => '광고 조회', 'success' => $result !== false, 'count' => mysqli_num_rows($result)];
}

if ($test_type === 'consultation' || empty($test_type)) {
    $test_data = ['hospital_id'=>1, 'name'=>'테스트사용자', 'phone'=>'010-1234-5678', 'content'=>'테스트'];
    $validation = true; $errors = [];
    if (empty($test_data['name'])) { $validation = false; $errors[] = '이름 필수'; }
    if (!preg_match('/^01[0-9]-[0-9]{4}-[0-9]{4}$/', $test_data['phone'])) { $validation = false; $errors[] = '전화번호 형식 오류'; }
    $test_results['consultation'] = ['name' => '상담 신청 유효성 검증', 'success' => $validation, 'errors' => $errors];
}

if ($test_type === 'upload' || empty($test_type)) {
    $upload_dir = __DIR__ . '/uploads/reviews';
    $test_results['upload'] = [
        'name' => '파일 업로드 디렉토리', 'success' => is_dir($upload_dir) && is_writable($upload_dir),
        'path' => $upload_dir, 'writable' => is_writable($upload_dir)
    ];
}

if ($test_type === 'session' || empty($test_type)) {
    $_SESSION['test_key'] = 'test_value_' . time();
    $test_results['session'] = [
        'name' => '세션 기능', 'success' => isset($_SESSION['test_key']), 'session_id' => session_id()
    ];
}

// 공통 헤더
$admin_page_title = '기능 테스트';
$admin_current = 'functional_test';
$admin_extra_css = '
    .test-card { border:2px solid var(--bs-border-color); border-radius:12px; padding:20px; margin-bottom:16px; transition:all 0.2s; }
    .test-card:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    .test-card.success { border-color:#198754; background:rgba(25,135,84,0.05); }
    .test-card.failed { border-color:#dc3545; background:rgba(220,53,69,0.05); }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> 기능 테스트</h5>
    <button onclick="location.reload()" class="btn btn-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> 재테스트</button>
</div>

<!-- 테스트 선택 버튼 -->
<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="?test=" class="btn btn-sm <?= empty($test_type) ? 'btn-primary' : 'btn-outline-primary' ?>">전체 테스트</a>
    <a href="?test=search" class="btn btn-sm <?= $test_type==='search' ? 'btn-primary' : 'btn-outline-secondary' ?>">검색</a>
    <a href="?test=detail" class="btn btn-sm <?= $test_type==='detail' ? 'btn-primary' : 'btn-outline-secondary' ?>">상세</a>
    <a href="?test=reviews" class="btn btn-sm <?= $test_type==='reviews' ? 'btn-primary' : 'btn-outline-secondary' ?>">리뷰</a>
    <a href="?test=events" class="btn btn-sm <?= $test_type==='events' ? 'btn-primary' : 'btn-outline-secondary' ?>">이벤트</a>
    <a href="?test=ads" class="btn btn-sm <?= $test_type==='ads' ? 'btn-primary' : 'btn-outline-secondary' ?>">광고</a>
    <a href="?test=consultation" class="btn btn-sm <?= $test_type==='consultation' ? 'btn-primary' : 'btn-outline-secondary' ?>">상담</a>
</div>

<!-- 테스트 결과 -->
<?php foreach ($test_results as $key => $test): ?>
<div class="test-card <?= $test['success'] ? 'success' : 'failed' ?>">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <h6 class="mb-0">
            <span class="badge <?= $test['success'] ? 'bg-success' : 'bg-danger' ?>"><?= $test['success'] ? 'PASS' : 'FAIL' ?></span>
            <?= htmlspecialchars($test['name']) ?>
        </h6>
    </div>
    <div class="row">
        <?php foreach ($test as $k => $v): ?>
            <?php if ($k !== 'name' && $k !== 'success'): ?>
            <div class="col-md-6 mb-2">
                <strong class="small"><?= ucfirst($k) ?>:</strong>
                <?php if (is_array($v)): ?>
                    <pre class="bg-body-tertiary p-2 rounded small mb-0"><?= htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                <?php else: ?>
                    <code><?= htmlspecialchars($v) ?></code>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($test_results)): ?>
<div class="alert alert-info py-2">테스트를 선택하세요.</div>
<?php endif; ?>

<?php
if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

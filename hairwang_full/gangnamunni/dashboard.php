<?php
/**
 * 통합 관리 대시보드
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

function dashboard_safe_return_url($value) {
    $value = trim((string)$value);
    if ($value === '') return 'dashboard.php';
    $value = str_replace('&amp;', '&', $value);
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) return 'dashboard.php';
    if (strpos($value, '//') === 0 || strpos($value, '..') !== false) return 'dashboard.php';
    if (!preg_match('/^[a-zA-Z0-9_\\.\\/\\?=&%\\-]+$/', $value)) return 'dashboard.php';
    return $value;
}

// 로그아웃
if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: dashboard.php');
    exit;
}

$return_url = dashboard_safe_return_url($_POST['return'] ?? $_GET['return'] ?? 'dashboard.php');

// 로그인 처리 (DB 기반 admin_login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (admin_login($username, $password, $connect)) {
        header('Location: ' . admin_login_destination($return_url));
        exit;
    } else {
        $login_error = '로그인 정보가 올바르지 않습니다.';
    }
}

// Gnuboard 로그인 후 SSO 토큰이 있으면 병원 관리자 세션으로 자동 연결
$sso_logged_in = false;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !check_admin_login()) {
    $sso_logged_in = admin_login_from_sso($connect);
}

// 로그인 체크
$is_logged_in = check_admin_login();
if ($is_logged_in && $return_url !== 'dashboard.php' && (!empty($_GET['return']) || $sso_logged_in)) {
    header('Location: ' . admin_login_destination($return_url));
    exit;
}
if ($is_logged_in && $return_url === 'dashboard.php' && function_exists('is_hospital_role') && is_hospital_role()) {
    $landing_page = hospital_admin_landing_page();
    if ($landing_page === null) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="ko"><meta charset="UTF-8"><title>접근 가능한 메뉴 없음</title><p>접근 가능한 관리 메뉴가 없습니다. 관리자에게 문의해주세요.</p></html>';
        exit;
    }
    header('Location: ' . $landing_page);
    exit;
}

// 비로그인 시 로그인 폼 표시
if (!$is_logged_in):
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인 | <?php echo getTheme('site_name'); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <!-- 다크모드 깜빡임 방지 -->
    <style>
    html{background:#fff;color:#212529}
    html[data-bs-theme="dark"]{background:#212529;color:#dee2e6}
    body{background:transparent}
    </style>
    <script>
    (function(){
        var t=localStorage.getItem('admin_theme')||'light';
        document.documentElement.setAttribute('data-bs-theme',t);
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .login-logo { height:48px; width:48px; object-fit:contain; border-radius:10px; }
        [data-bs-theme="dark"] .login-logo { filter: invert(1) brightness(2); }
        .theme-toggle-float {
            position:fixed; top:16px; right:16px; z-index:1050;
            border-radius:50%; width:44px; height:44px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; cursor:pointer;
        }
        .theme-toggle-float:hover { transform:scale(1.1); }
        .login-card { border-radius:16px; border:none; }
        .login-brand { display:flex; flex-direction:column; align-items:center; gap:6px; margin-bottom:1.2rem; }
        .login-brand-name { font-size:1.4rem; font-weight:700; color:var(--bs-body-color); }
        .login-brand-sub { font-size:0.8rem; color:var(--bs-secondary-color); }
    </style>
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <!-- 주간/야간 토글 -->
    <button class="btn btn-outline-secondary theme-toggle-float" id="loginThemeToggle" title="주간/야간 모드">
        <i class="bi bi-moon-stars" id="loginThemeIcon"></i>
    </button>
    <div class="card shadow login-card" style="width:100%;max-width:420px;">
        <div class="card-body p-4">
            <div class="login-brand">
                <img src="assets/images/seoul_beauty_logo.png" alt="서울미녀" class="login-logo">
                <div class="login-brand-name">서울미녀</div>
                <div class="login-brand-sub">관리자 로그인</div>
            </div>

            <?php if (isset($login_error)): ?>
            <div class="alert alert-danger py-2"><?php echo $login_error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES); ?>">
                <div class="mb-3">
                    <label class="form-label">아이디 또는 이메일</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           placeholder="아이디 또는 등록된 이메일" autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label">비밀번호</label>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePw" tabindex="-1">
                            <i class="bi bi-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">로그인</button>
            </form>
            <div class="text-center mt-3">
                <a href="admin_forgot_password.php" class="text-body-secondary small text-decoration-none">
                    <i class="bi bi-key"></i> 비밀번호 찾기
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 비밀번호 눈 아이콘 토글
    document.getElementById('togglePw').addEventListener('click', function() {
        var pw = document.getElementById('loginPassword');
        var icon = document.getElementById('togglePwIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            pw.type = 'password';
            icon.className = 'bi bi-eye';
        }
        pw.focus();
    });
    // 자동 입력 + 즉시 로그인
    function fillLogin(user, pass) {
        document.querySelector('input[name="username"]').value = user;
        document.getElementById('loginPassword').value = pass;
        // .submit()은 name="login" 미포함 → .click()으로 submit 버튼 직접 클릭
        document.querySelector('button[name="login"]').click();
    }
    // 주간/야간 토글
    (function(){
        var html = document.documentElement;
        var btn = document.getElementById('loginThemeToggle');
        var icon = document.getElementById('loginThemeIcon');
        var saved = localStorage.getItem('admin_theme') || 'light';
        html.setAttribute('data-bs-theme', saved);
        if(icon) icon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        if(btn){
            btn.addEventListener('click', function(){
                var cur = html.getAttribute('data-bs-theme');
                var next = cur === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', next);
                localStorage.setItem('admin_theme', next);
                if(icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            });
        }
    })();
    </script>
</body>
</html>
<?php
    mysqli_close($connect);
    exit;
endif;

// ========== 로그인된 사용자 전용 영역 ==========

// 통계 데이터
$stats = [];
$stats['hospitals'] = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals"))['c'];
$stats['reviews'] = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM hospital_reviews"))['c'];
$stats['events'] = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_events"))['c'];
$stats['ads'] = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads WHERE is_active = 1"))['c'];
$stats['consultations_today'] = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_consultations WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"))['c'];

$recent_reviews = [];
$result = mysqli_query($connect, "SELECT hr.*, gh.name as hospital_name FROM hospital_reviews hr LEFT JOIN gangnamunni_hospitals gh ON hr.hospital_id = gh.id ORDER BY hr.created_at DESC LIMIT 5");
if ($result) { while ($row = mysqli_fetch_assoc($result)) { $recent_reviews[] = $row; } }

$recent_consultations = [];
$result = mysqli_query($connect, "SELECT * FROM gangnamunni_consultations ORDER BY created_at DESC LIMIT 5");
if ($result) { while ($row = mysqli_fetch_assoc($result)) { $recent_consultations[] = $row; } }

$db_size = round(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(data_length + index_length)/1024/1024 AS s FROM information_schema.TABLES WHERE table_schema = '" . G5_MYSQL_DB . "'"))['s'], 2);

// 공통 헤더 include
$admin_page_title = '대시보드';
$admin_current = 'dashboard';
$admin_extra_css = '
    .stat-card { border: none; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .stat-border { width: 4px; min-height: 100%; }
    .activity-item { border-bottom: 1px solid var(--bs-border-color); }
    .activity-item:last-child { border-bottom: none; }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- HMS 진료 현황 위젯 -->
<?php
$hms_today_appt = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM appointments WHERE opd_date >= CURDATE() AND opd_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"))['cnt'] ?? 0;
$hms_admitted = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM ipd_patient_departments WHERE is_discharge = 0"))['cnt'] ?? 0;
$hms_total_patients = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM patients"))['cnt'] ?? 0;
$hms_available_beds = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM beds WHERE is_available = 1"))['cnt'] ?? 0;
$hms_total_beds = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM beds"))['cnt'] ?? 0;
$hms_opd_today = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM opd_patient_departments WHERE appointment_date >= CURDATE() AND appointment_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"))['cnt'] ?? 0;
?>
<div class="card mb-4">
    <div class="card-header bg-primary bg-opacity-10">
        <h6 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>HMS 진료 현황</h6>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-md-2 col-4">
                <div class="fs-4 fw-bold text-primary"><?= number_format($hms_total_patients) ?></div>
                <small class="text-muted">전체 환자</small>
            </div>
            <div class="col-md-2 col-4">
                <div class="fs-4 fw-bold text-success"><?= $hms_today_appt ?></div>
                <small class="text-muted">오늘 예약</small>
            </div>
            <div class="col-md-2 col-4">
                <div class="fs-4 fw-bold text-info"><?= $hms_opd_today ?></div>
                <small class="text-muted">오늘 외래</small>
            </div>
            <div class="col-md-2 col-4">
                <div class="fs-4 fw-bold text-warning"><?= $hms_admitted ?></div>
                <small class="text-muted">입원중</small>
            </div>
            <div class="col-md-2 col-4">
                <div class="fs-4 fw-bold text-success"><?= $hms_available_beds ?>/<?= $hms_total_beds ?></div>
                <small class="text-muted">가용 병상</small>
            </div>
            <div class="col-md-2 col-4">
            </div>
        </div>
    </div>
</div>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-speedometer2"></i> 대시보드</h5>
</div>

<!-- 외부 사이트 바로가기 -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="/hairwang/gangnamunni/#" target="_blank" class="btn btn-primary btn-sm">
        <i class="bi bi-code-slash"></i> 개발 대시보드
    </a>
    <a href="/1/" target="_blank" class="btn btn-success btn-sm">
        <i class="bi bi-hospital"></i> HMS 메인
    </a>
    <a href="/" target="_blank" class="btn btn-info btn-sm text-white">
        <i class="bi bi-globe"></i> seoulbeauty.app
    </a>
    <a href="https://play.google.com/store/apps/details?id=com.celebhospital.patient" target="_blank" class="btn btn-sm text-white" style="background:#01875f;">
        <i class="bi bi-google"></i> Google Play
    </a>
</div>

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#667eea;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">총 병원 수</div>
                    <div class="fs-2 fw-bold mt-1"><?php echo number_format($stats['hospitals']); ?></div>
                    <div class="text-body-secondary small">등록된 병원</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#f093fb;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">총 리뷰 수</div>
                    <div class="fs-2 fw-bold mt-1"><?php echo number_format($stats['reviews']); ?></div>
                    <div class="text-body-secondary small">작성된 후기</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#fa709a;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">이벤트</div>
                    <div class="fs-2 fw-bold mt-1"><?php echo number_format($stats['events']); ?></div>
                    <div class="text-body-secondary small">진행 중</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#4facfe;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">활성 광고</div>
                    <div class="fs-2 fw-bold mt-1"><?php echo number_format($stats['ads']); ?></div>
                    <div class="text-body-secondary small">노출 중</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#43e97b;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">오늘 상담</div>
                    <div class="fs-2 fw-bold mt-1"><?php echo number_format($stats['consultations_today']); ?></div>
                    <div class="text-body-secondary small">금일 신청</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 시스템 정보 -->
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-gear"></i> 시스템 정보</h6>
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="bg-body-tertiary rounded-3 p-3">
                    <div class="text-body-secondary small">데이터베이스</div>
                    <div class="fw-bold"><?php echo G5_MYSQL_DB; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bg-body-tertiary rounded-3 p-3">
                    <div class="text-body-secondary small">DB 크기</div>
                    <div class="fw-bold"><?php echo $db_size; ?> MB</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bg-body-tertiary rounded-3 p-3">
                    <div class="text-body-secondary small">PHP 버전</div>
                    <div class="fw-bold"><?php echo PHP_VERSION; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bg-body-tertiary rounded-3 p-3">
                    <div class="text-body-secondary small">서버 시간</div>
                    <div class="fw-bold"><?php echo date('H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 관리 메뉴 바로가기 -->
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-grid-3x3-gap"></i> 관리 메뉴</h6>
        <div class="row g-2">
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_hospital_images.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-images fs-4"></i>
                    <span class="small">병원 이미지</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_reviews.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-chat-dots fs-4"></i>
                    <span class="small">후기 관리</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_categories.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-tags fs-4"></i>
                    <span class="small">카테고리</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_ads.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-megaphone fs-4"></i>
                    <span class="small">광고 관리</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_navigation.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-list fs-4"></i>
                    <span class="small">네비게이션</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_theme.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-palette fs-4"></i>
                    <span class="small">테마 설정</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_theme_advanced.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-gear-wide-connected fs-4"></i>
                    <span class="small">고급 테마</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_maintenance.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-tools fs-4"></i>
                    <span class="small">점검 모드</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="admin_upgrade_plan.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-rocket-takeoff fs-4"></i>
                    <span class="small">확장 계획</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="tips.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-lightbulb fs-4"></i>
                    <span class="small">뷰티팁</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="category_icons.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-emoji-smile fs-4"></i>
                    <span class="small">카테고리 아이콘</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="ab_test_dashboard.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
                    <i class="bi bi-graph-up fs-4"></i>
                    <span class="small">A/B 테스트</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 대기중 승인 요청 -->
<?php
$pending_hospitals = 0;
$ph = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='pending'");
if ($ph) $pending_hospitals = (int)mysqli_fetch_assoc($ph)['c'];

$pending_ads = 0;
$pa = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads WHERE status='pending'");
if ($pa) $pending_ads = (int)mysqli_fetch_assoc($pa)['c'];

if ($pending_hospitals > 0 || $pending_ads > 0):
?>
<div class="card mb-3 border-warning">
    <div class="card-header bg-warning text-dark py-2">
        <i class="bi bi-exclamation-triangle"></i> <strong>승인 대기</strong>
    </div>
    <div class="card-body py-2">
        <div class="d-flex gap-3">
            <?php if ($pending_hospitals > 0): ?>
            <a href="admin_hospitals.php?status=pending" class="text-decoration-none">
                <span class="badge bg-warning text-dark fs-6"><?php echo $pending_hospitals; ?></span>
                <span class="ms-1">병원 승인 대기</span>
            </a>
            <?php endif; ?>
            <?php if ($pending_ads > 0): ?>
            <a href="admin_ads.php?tab=ads&status=pending" class="text-decoration-none">
                <span class="badge bg-warning text-dark fs-6"><?php echo $pending_ads; ?></span>
                <span class="ms-1">광고 승인 대기</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 오늘 예약 섹션 (풀폭) -->
<?php
$today_bookings = [];
$tb_r = mysqli_query($connect, "SELECT b.* FROM gangnamunni_bookings b WHERE b.booking_date = CURDATE() ORDER BY b.booking_time ASC LIMIT 10");
if ($tb_r) while ($tr = mysqli_fetch_assoc($tb_r)) $today_bookings[] = $tr;
$pending_bookings = [];
$pb_r = mysqli_query($connect, "SELECT b.* FROM gangnamunni_bookings b WHERE b.status = 'pending' ORDER BY b.created_at DESC LIMIT 5");
if ($pb_r) while ($pr = mysqli_fetch_assoc($pb_r)) $pending_bookings[] = $pr;
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i>오늘 예약 (<?php echo count($today_bookings); ?>건) / 대기중 (<?php echo count($pending_bookings); ?>건)</h6>
        <a href="admin_bookings.php" class="btn btn-sm btn-outline-primary">전체 보기</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($today_bookings) && empty($pending_bookings)): ?>
        <p class="text-center text-muted py-4 mb-0">예약 내역이 없습니다.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>시간</th><th>예약자</th><th>병원</th><th>시술</th><th>상태</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_merge($today_bookings, $pending_bookings) as $tb): ?>
                    <tr>
                        <td><?php echo substr($tb['booking_time'], 0, 5); ?></td>
                        <td><?php echo htmlspecialchars($tb['name']); ?></td>
                        <td><?php echo htmlspecialchars($tb['hospital_name']); ?></td>
                        <td><?php echo htmlspecialchars($tb['treatment']); ?></td>
                        <td>
                            <?php
                            $badge = ['pending'=>'warning','confirmed'=>'success','completed'=>'info','cancelled'=>'danger'];
                            $lbl = ['pending'=>'대기','confirmed'=>'확정','completed'=>'완료','cancelled'=>'취소'];
                            $s = $tb['status'] ?? 'pending';
                            ?>
                            <span class="badge bg-<?php echo $badge[$s] ?? 'secondary'; ?> small"><?php echo $lbl[$s] ?? $s; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 최근 활동 -->
<div class="row g-3">
    <!-- 최근 리뷰 -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots"></i> 최근 리뷰</h6>
                <?php if (!empty($recent_reviews)): ?>
                    <?php foreach ($recent_reviews as $review): ?>
                    <div class="activity-item d-flex justify-content-between align-items-start p-2">
                        <div>
                            <div class="fw-semibold small">
                                <?php echo htmlspecialchars($review['hospital_name'] ?? '-'); ?>
                                <span class="text-warning ms-1"><?php echo str_repeat('&#9733;', $review['rating']); ?></span>
                            </div>
                            <div class="text-body-secondary small mt-1">
                                <?php echo htmlspecialchars(mb_substr($review['content'], 0, 60)); ?>...
                                &middot; <?php echo htmlspecialchars($review['user_name']); ?>
                            </div>
                        </div>
                        <small class="text-body-secondary text-nowrap ms-3"><?php echo date('m/d H:i', strtotime($review['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-body-secondary py-5">
                        <i class="bi bi-chat-dots d-block" style="font-size:48px;"></i>
                        아직 리뷰가 없습니다
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 최근 상담 신청 -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-envelope"></i> 최근 상담 신청</h6>
                <?php if (!empty($recent_consultations)): ?>
                    <?php foreach ($recent_consultations as $consultation): ?>
                    <div class="activity-item d-flex justify-content-between align-items-start p-2">
                        <div>
                            <div class="fw-semibold small">
                                <?php echo htmlspecialchars($consultation['hospital_name'] ?? '-'); ?>
                                <span class="badge <?php echo ($consultation['status'] ?? 'pending') === 'pending' ? 'bg-warning text-dark' : 'bg-success'; ?>"><?php echo $consultation['status'] ?? 'pending'; ?></span>
                            </div>
                            <div class="text-body-secondary small mt-1">
                                <?php echo htmlspecialchars($consultation['name'] ?? '-'); ?> &middot; <?php echo htmlspecialchars($consultation['phone'] ?? '-'); ?>
                            </div>
                        </div>
                        <small class="text-body-secondary text-nowrap ms-2"><?php echo date('m/d', strtotime($consultation['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-body-secondary py-5">
                        <i class="bi bi-envelope d-block" style="font-size:48px;"></i>
                        아직 상담 신청이 없습니다
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 수정 이력 -->
<?php
// 수정 이력 조회 (최근 5일치, 날짜별 그룹핑)
$fix_logs = [];
$fix_total = 0;
$tbl_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_fix_log'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $fix_result = mysqli_query($connect, "SELECT * FROM gangnamunni_fix_log ORDER BY fix_date DESC, id DESC LIMIT 30");
    if ($fix_result) {
        while ($row = mysqli_fetch_assoc($fix_result)) { $fix_logs[] = $row; }
    }
    $fix_total = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_fix_log"))['c'];
}

// 날짜별 그룹핑
$fix_by_date = [];
$shown_dates = 0;
foreach ($fix_logs as $log) {
    $d = $log['fix_date'];
    if (!isset($fix_by_date[$d])) {
        $shown_dates++;
        if ($shown_dates > 5) break;
        $fix_by_date[$d] = [];
    }
    $fix_by_date[$d][] = $log;
}

$cat_labels = ['bug_fix' => '버그 수정', 'feature' => '기능 추가', 'manual' => '매뉴얼', 'improvement' => '개선'];
$cat_colors = ['bug_fix' => 'danger', 'feature' => 'primary', 'manual' => 'success', 'improvement' => 'warning'];
$cat_icons = ['bug_fix' => 'bi-bug', 'feature' => 'bi-stars', 'manual' => 'bi-book', 'improvement' => 'bi-wrench'];
$status_colors = ['completed' => 'success', 'in_progress' => 'warning', 'planned' => 'secondary'];
?>

<div class="card mt-4 mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-tools"></i> 수정 이력 <span class="badge bg-secondary ms-1"><?php echo $fix_total; ?>건</span></h6>
        </div>

        <?php if (empty($fix_by_date)): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="bi bi-tools d-block" style="font-size:48px;"></i>
            아직 수정 이력이 없습니다
        </div>
        <?php else: ?>
        <?php foreach ($fix_by_date as $date => $logs): ?>
        <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-calendar3 text-primary"></i>
                <strong class="small"><?php echo $date; ?></strong>
                <span class="badge bg-body-secondary text-body-secondary"><?php echo count($logs); ?>건</span>
            </div>
            <div class="border rounded">
                <?php foreach ($logs as $idx => $log): ?>
                <div class="d-flex justify-content-between align-items-start p-2 <?php echo $idx < count($logs) - 1 ? 'border-bottom' : ''; ?>">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if ($log['issue_number']): ?>
                            <span class="badge bg-dark">#<?php echo $log['issue_number']; ?></span>
                            <?php endif; ?>
                            <span class="fw-semibold small"><?php echo htmlspecialchars($log['title']); ?></span>
                            <span class="badge bg-<?php echo $cat_colors[$log['category']] ?? 'secondary'; ?>" style="font-size:10px;">
                                <?php echo $cat_labels[$log['category']] ?? $log['category']; ?>
                            </span>
                            <span class="badge bg-<?php echo $status_colors[$log['status']] ?? 'secondary'; ?>" style="font-size:10px;">
                                <?php echo $log['status']; ?>
                            </span>
                        </div>
                        <div class="text-body-secondary mt-1" style="font-size:12px;">
                            <?php if ($log['target_page']): ?>
                            <i class="bi bi-file-earmark-code"></i> <?php echo htmlspecialchars($log['target_page']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1 ms-2 flex-shrink-0">
                        <?php if ($log['screenshot_before'] || $log['screenshot_after']): ?>
                        <?php if ($log['screenshot_before']): ?>
                        <button class="btn btn-outline-secondary btn-sm" style="font-size:10px;padding:2px 6px;" onclick="showFixScreenshot('<?php echo htmlspecialchars($log['screenshot_before']); ?>', '수정 전')" title="수정 전">
                            <i class="bi bi-image"></i> 전
                        </button>
                        <?php endif; ?>
                        <?php if ($log['screenshot_after']): ?>
                        <button class="btn btn-outline-success btn-sm" style="font-size:10px;padding:2px 6px;" onclick="showFixScreenshot('<?php echo htmlspecialchars($log['screenshot_after']); ?>', '수정 후')" title="수정 후">
                            <i class="bi bi-image"></i> 후
                        </button>
                        <?php endif; ?>
                        <?php if ($log['screenshot_before'] && $log['screenshot_after']): ?>
                        <button class="btn btn-outline-primary btn-sm" style="font-size:10px;padding:2px 6px;" onclick="showFixCompare('<?php echo htmlspecialchars($log['screenshot_before']); ?>', '<?php echo htmlspecialchars($log['screenshot_after']); ?>')" title="비교">
                            <i class="bi bi-arrows-angle-expand"></i>
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($log['target_url']): ?>
                        <a href="<?php echo htmlspecialchars($log['target_url']); ?>" class="btn btn-outline-info btn-sm" style="font-size:10px;padding:2px 6px;" title="바로가기">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 스크린샷 모달 -->
<div class="modal fade" id="fixScreenshotModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="fixScreenshotTitle">스크린샷</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center" style="max-height:80vh;overflow:auto;">
                <img id="fixScreenshotImg" src="" class="img-fluid" style="max-width:100%;">
            </div>
        </div>
    </div>
</div>

<!-- 비교 모달 -->
<div class="modal fade" id="fixCompareModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:95vw;">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-arrows-angle-expand"></i> 수정 전/후 비교</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="text-center mb-1"><span class="badge bg-secondary">수정 전</span></div>
                        <div style="max-height:75vh;overflow:auto;border:1px solid var(--bs-border-color);border-radius:4px;">
                            <img id="fixCompareBefore" src="" class="img-fluid" style="width:100%;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center mb-1"><span class="badge bg-success">수정 후</span></div>
                        <div style="max-height:75vh;overflow:auto;border:1px solid var(--bs-border-color);border-radius:4px;">
                            <img id="fixCompareAfter" src="" class="img-fluid" style="width:100%;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showFixScreenshot(path, title) {
    document.getElementById('fixScreenshotTitle').textContent = title;
    document.getElementById('fixScreenshotImg').src = '/auto-manual-api/' + path;
    new bootstrap.Modal(document.getElementById('fixScreenshotModal')).show();
}
function showFixCompare(beforePath, afterPath) {
    document.getElementById('fixCompareBefore').src = '/auto-manual-api/' + beforePath;
    document.getElementById('fixCompareAfter').src = '/auto-manual-api/' + afterPath;
    new bootstrap.Modal(document.getElementById('fixCompareModal')).show();
}
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

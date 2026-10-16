<?php
/**
 * 병원 등록 대시보드 (통합)
 * 3탭: 입점 안내 / 등록 신청 / 신청 현황
 * 다크모드 + 카카오맵 + 콘솔 디버깅
 * 비로그인 접근 가능 (공개)
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

if (!function_exists('term')) {
    require_once __DIR__ . '/includes/industry_terms.php';
}

$page_title = term('business', '병원') . ' 등록 — ' . term('site_name', '서울미녀');

// 카카오맵 API 키
$kakao_key = '';
$res_k = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='kakao_map_api_key' LIMIT 1");
if ($res_k && $rk = mysqli_fetch_assoc($res_k)) $kakao_key = $rk['setting_value'];

// 통계
$stat_hospitals = 0; $stat_reviews = 0;
$res = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE is_active=1");
if ($res && $row = mysqli_fetch_assoc($res)) $stat_hospitals = $row['cnt'];
$res2 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews WHERE is_active=1");
if ($res2 && $row2 = mysqli_fetch_assoc($res2)) $stat_reviews = $row2['cnt'];

// 카테고리
$category_list = defined('CATEGORIES') ? CATEGORIES : ['코','눈','지방흡입','가슴성형','치아','리프팅','주름/미백','쁘띠','모발이식','제모','남성','눈성형'];

// ===== POST: 신청 제출 =====
$message = ''; $error = ''; $success = false;
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 등록 신청은 hospital_apply_public.php 에서만 받는다.
// (이 페이지의 예전 신청 처리기는 CSRF·이메일 OTP·IP 속도제한·중복검사 없이
//  인증서 업로드 + DB INSERT + 임의 주소로 메일 발송까지 수행했다.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    header('Location: hospital_apply_public.php', true, 303);
    exit;
}

// 신청 현황 조회 (이메일 기반)
$status_list = [];
$status_email = trim($_GET['email'] ?? ($_POST['check_email'] ?? ''));
if (!empty($status_email) && filter_var($status_email, FILTER_VALIDATE_EMAIL)) {
    $stmt_s = mysqli_prepare($connect, "SELECT id, name, status, applied_at, rejection_reason, reviewed_by, reviewed_at, ad_grade FROM gangnamunni_hospitals WHERE email=? ORDER BY applied_at DESC");
    if ($stmt_s) {
        mysqli_stmt_bind_param($stmt_s, 's', $status_email);
        mysqli_stmt_execute($stmt_s);
        $res_s = mysqli_stmt_get_result($stmt_s);
        while ($r = mysqli_fetch_assoc($res_s)) $status_list[] = $r;
        mysqli_stmt_close($stmt_s);
    }
}

mysqli_close($connect);
$active_tab = $_GET['tab'] ?? ($success ? 'apply' : 'info');
if (!empty($status_email)) $active_tab = 'status';
if ($success) $active_tab = 'apply';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- 안티 플래시: 다크모드 즉시 적용 -->
    <script>
        (function(){
            var t = localStorage.getItem('sb_reg_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
            console.log('[RegDash] 테마 초기화:', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --sb-primary: #6c5ce7; --sb-secondary: #a29bfe; --sb-gradient: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 50%, #fd79a8 100%); }
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }

        /* 상단 바 */
        .top-bar { background: var(--sb-gradient); color: #fff; padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .top-bar h1 { font-size: 1.4rem; font-weight: 800; margin: 0; }
        .top-bar .stats { font-size: 0.85rem; opacity: 0.85; }
        .top-controls .btn { border-radius: 50px; font-size: 0.8rem; padding: 6px 14px; }

        /* 탭 */
        .nav-tabs .nav-link { font-weight: 600; color: #666; border: none; padding: 12px 24px; }
        .nav-tabs .nav-link.active { color: var(--sb-primary); border-bottom: 3px solid var(--sb-primary); background: transparent; }
        .nav-tabs .nav-link:hover { color: var(--sb-primary); }

        /* 혜택 카드 */
        .benefit-card { border: none; border-radius: 16px; padding: 1.5rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
        .benefit-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(108,92,231,.15); }
        .benefit-icon { font-size: 2.2rem; margin-bottom: 0.8rem; }

        /* 등급 테이블 */
        .grade-table th { background: var(--sb-primary); color: #fff; text-align: center; vertical-align: middle; font-size: 0.85rem; }
        .grade-table td { text-align: center; vertical-align: middle; font-size: 0.85rem; }

        /* 절차 */
        .step-number { width: 40px; height: 40px; border-radius: 50%; background: var(--sb-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }

        /* FAQ */
        .faq-section .accordion-button:not(.collapsed) { background: rgba(108,92,231,.08); color: var(--sb-primary); }

        /* 폼 섹션 */
        .section-title { font-weight: 700; border-bottom: 2px solid var(--sb-primary); padding-bottom: 8px; margin-bottom: 16px; color: var(--sb-primary); }

        /* 지도 */
        #kakaoMap { width: 100%; height: 300px; border-radius: 12px; border: 1px solid #ddd; }

        /* 허니팟 */
        .hp-field { position: absolute; left: -9999px; }

        /* 모바일 대응 */
        @media (max-width: 576px) {
            .top-bar h1 { font-size: 1rem; }
            .top-bar .stats { font-size: 0.7rem; }
            .nav-tabs .nav-link { padding: 10px 12px; font-size: 0.85rem; }
            .benefit-card { padding: 1rem; }
            .benefit-icon { font-size: 1.8rem; }
            .grade-table { font-size: 0.75rem; }
            .section-title { font-size: 1rem; }
            #kakaoMap { height: 220px; }
            .input-group { flex-wrap: wrap; }
            .input-group .form-control { min-width: 100%; margin-bottom: 4px; }
            .input-group .btn { width: 100%; }
            #addrSuggest { max-height: 150px; }
        }

        /* 다크모드 오버라이드 */
        [data-bs-theme="dark"] { --bs-body-bg: #1a1a2e; --bs-body-color: #e0e0e0; }
        [data-bs-theme="dark"] .top-bar { background: linear-gradient(135deg, #2d1b69 0%, #1a1a2e 100%); }
        [data-bs-theme="dark"] .benefit-card { background: #16213e; color: #e0e0e0; }
        [data-bs-theme="dark"] .benefit-card p { color: #aaa !important; }
        [data-bs-theme="dark"] .bg-light { background: #16213e !important; }
        [data-bs-theme="dark"] .card { background: #16213e; border-color: #2a2a4a; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select { background: #0f3460; border-color: #2a2a4a; color: #e0e0e0; }
        [data-bs-theme="dark"] .nav-tabs { border-bottom-color: #2a2a4a; }
        [data-bs-theme="dark"] .nav-tabs .nav-link { color: #aaa; }
        [data-bs-theme="dark"] .nav-tabs .nav-link.active { color: #a29bfe; border-bottom-color: #a29bfe; }
        [data-bs-theme="dark"] .text-muted { color: #888 !important; }
        [data-bs-theme="dark"] #kakaoMap { border-color: #2a2a4a; }
        [data-bs-theme="dark"] .accordion-button { background: #16213e; color: #e0e0e0; }
        [data-bs-theme="dark"] .accordion-button:not(.collapsed) { background: #1a1a4e; color: #a29bfe; }
        [data-bs-theme="dark"] .table { --bs-table-bg: #16213e; --bs-table-color: #e0e0e0; --bs-table-border-color: #2a2a4a; }
        [data-bs-theme="dark"] footer { color: #666 !important; }
    </style>
</head>
<body>

<!-- 상단 바 -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-hospital me-2"></i><?= term('site_name', '서울미녀') ?> — <?= term('business', '병원') ?> 등록</h1>
            <div class="stats"><?= number_format($stat_hospitals) ?>+ 등록 <?= term('business', '병원') ?> · <?= number_format($stat_reviews) ?>+ <?= term('review', '후기') ?></div>
        </div>
        <div class="top-controls d-flex gap-2 align-items-center">
            <button class="btn btn-outline-light btn-sm" id="themeToggle" title="주간/야간 모드">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>관리자</a>
        </div>
    </div>
</div>

<!-- 탭 네비게이션 -->
<div class="container mt-3">
    <ul class="nav nav-tabs" id="regTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $active_tab === 'info' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabInfo" type="button">
                <i class="bi bi-info-circle me-1"></i>입점 안내
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab === 'apply' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabApply" type="button">
                <i class="bi bi-pencil-square me-1"></i>등록 신청
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab === 'status' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabStatus" type="button">
                <i class="bi bi-clock-history me-1"></i>신청 현황
            </button>
        </li>
    </ul>
</div>

<div class="tab-content container py-4">

<!-- ==================== 탭 1: 입점 안내 ==================== -->
<div class="tab-pane fade <?= $active_tab === 'info' ? 'show active' : '' ?>" id="tabInfo">

    <!-- 혜택 -->
    <h4 class="fw-bold mb-4"><i class="bi bi-gift me-2"></i>입점 혜택</h4>
    <div class="row g-3 mb-5">
        <?php
        $benefits = [
            ['bi-hospital', '무료 프로필', '전용 프로필 페이지 자동 생성'],
            ['bi-calendar-check', '온라인 예약', '환자 직접 예약 + 일정 관리'],
            ['bi-star', 'AI 후기 검수', '부적절 콘텐츠 자동 필터링'],
            ['bi-bar-chart', '통계 대시보드', '실시간 방문자/예약/리뷰 통계'],
            ['bi-phone', '앱 노출', 'Android/iOS 앱 자동 노출'],
            ['bi-geo-alt', '타겟 광고', '지역/시술 기반 환자 도달'],
        ];
        foreach ($benefits as $b): ?>
        <div class="col-lg-4 col-sm-6">
            <div class="benefit-card bg-light">
                <div class="benefit-icon text-primary"><i class="bi <?= $b[0] ?>"></i></div>
                <h6 class="fw-bold"><?= $b[1] ?></h6>
                <p class="text-muted small mb-0"><?= $b[2] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 등급 비교 -->
    <h4 class="fw-bold mb-3"><i class="bi bi-table me-2"></i>등급별 서비스 비교</h4>
    <div class="table-responsive mb-5">
        <table class="table table-bordered grade-table">
            <thead><tr><th>기능</th><th style="background:#27ae60">Free</th><th>D</th><th>C</th><th>B</th><th>A</th><th>S</th></tr></thead>
            <tbody>
                <tr><td class="fw-semibold text-start">프로필</td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td></tr>
                <tr><td class="fw-semibold text-start">시술 등록</td><td>3</td><td>10</td><td>20</td><td>30</td><td>50</td><td>&infin;</td></tr>
                <tr><td class="fw-semibold text-start">의사 등록</td><td>1</td><td>3</td><td>5</td><td>10</td><td>20</td><td>&infin;</td></tr>
                <tr><td class="fw-semibold text-start">배너 광고</td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-x-lg text-muted"></i></td><td>2</td><td>5</td><td>&infin;</td></tr>
                <tr><td class="fw-semibold text-start">AI 기능</td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-x-lg text-muted"></i></td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td></tr>
                <tr><td class="fw-semibold text-start">가격</td><td class="fw-bold text-success">무료</td><td>문의</td><td>문의</td><td>문의</td><td>문의</td><td>문의</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 입점 절차 -->
    <h4 class="fw-bold mb-4"><i class="bi bi-signpost-2 me-2"></i>입점 절차</h4>
    <div class="mx-auto mb-5" style="max-width:550px;">
        <?php foreach ([['신청서 작성','사업자등록증 + 의료기관 허가증 첨부'],['관리자 심사','1~3 영업일'],['승인 + 계정 발급','관리자 이메일로 자동 발송'],['운영 시작','대시보드 로그인 → 즉시 운영']] as $i => $s): ?>
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="step-number"><?= $i+1 ?></div>
            <div><h6 class="fw-bold mb-0"><?= $s[0] ?></h6><small class="text-muted"><?= $s[1] ?></small></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ -->
    <h4 class="fw-bold mb-3"><i class="bi bi-question-circle me-2"></i>자주 묻는 질문</h4>
    <div class="accordion mb-4 faq-section" id="faqAcc">
        <?php foreach ([['입점 비용은?','Free 등급 무료, 프로모션 기간 수수료 0%'],['심사 기간은?','1~3 영업일'],['필요 서류는?','사업자등록증 + 의료기관 개설허가증 (필수)'],['여러 병원 등록 가능한가요?','네, 같은 이메일로 여러 병원을 신청할 수 있습니다. 각 병원별로 별도 심사 후 하나의 계정으로 관리합니다.']] as $i => $f): ?>
        <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden">
            <h2 class="accordion-header"><button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?=$i?>"><?=$f[0]?></button></h2>
            <div id="faq<?=$i?>" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body text-muted"><?=$f[1]?></div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center py-3">
        <button class="btn btn-primary btn-lg px-5" onclick="document.querySelector('[data-bs-target=\'#tabApply\']').click();">
            <i class="bi bi-pencil-square me-2"></i>지금 신청하기
        </button>
    </div>
</div>

<!-- ==================== 탭 2: 등록 신청 ==================== -->
<div class="tab-pane fade <?= $active_tab === 'apply' ? 'show active' : '' ?>" id="tabApply">

    <div class="text-center py-5">
        <i class="bi bi-shield-check text-primary" style="font-size: 4rem;"></i>
        <h3 class="fw-bold mt-3"><?= htmlspecialchars(term('business', '병원')) ?> 등록 신청</h3>
        <p class="text-muted">이메일 인증 후 신청서를 작성합니다. 사업자등록증·의료기관 개설허가증을 준비해 주세요.</p>
        <a href="hospital_apply_public.php" class="btn btn-primary btn-lg px-5 mt-2"><i class="bi bi-pencil-square me-2"></i>신청서 작성하기</a>
    </div>
</div>

<!-- ==================== 탭 3: 신청 현황 ==================== -->
<div class="tab-pane fade <?= $active_tab === 'status' ? 'show active' : '' ?>" id="tabStatus">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-search me-2"></i>신청 현황 조회</h5>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="status">
                <input type="email" name="email" class="form-control" placeholder="신청 시 입력한 이메일 주소" value="<?= htmlspecialchars($status_email) ?>" required>
                <button type="submit" class="btn btn-primary flex-shrink-0"><i class="bi bi-search me-1"></i>조회</button>
            </form>
        </div>
    </div>

    <?php if (!empty($status_email)): ?>
        <?php if (empty($status_list)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <p class="mt-2 text-muted">"<?= htmlspecialchars($status_email) ?>"로 접수된 신청이 없습니다.</p>
        </div>
        <?php else: ?>
            <?php foreach ($status_list as $app):
                $st = $app['status'];
                $badge = match($st) {
                    'pending' => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>심사 중</span>',
                    'approved' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>승인</span>',
                    'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>반려</span>',
                    default => '<span class="badge bg-secondary">'.$st.'</span>',
                };
            ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($app['name']) ?> <?= $badge ?></h5>
                    <p class="text-muted small mb-2">
                        #<?= $app['id'] ?> · 신청일: <?= date('Y-m-d H:i', strtotime($app['applied_at'])) ?>
                        <?php if ($app['reviewed_at']): ?> · 심사일: <?= date('Y-m-d', strtotime($app['reviewed_at'])) ?><?php endif; ?>
                    </p>
                    <?php if ($st === 'rejected' && !empty($app['rejection_reason'])): ?>
                    <div class="alert alert-danger py-2 mb-2"><strong>반려 사유:</strong> <?= htmlspecialchars($app['rejection_reason']) ?></div>
                    <button class="btn btn-outline-primary btn-sm" onclick="document.querySelector('[data-bs-target=\'#tabApply\']').click();"><i class="bi bi-arrow-clockwise me-1"></i>재신청</button>
                    <?php endif; ?>
                    <?php if ($st === 'approved'): ?>
                    <div class="alert alert-success py-2 mb-1">
                        <strong>승인 완료!</strong> 등급: <?= $app['ad_grade'] === 'none' ? 'Free' : strtoupper($app['ad_grade']) ?>
                        · 이메일로 발송된 관리자 계정으로 로그인하세요.
                    </div>
                    <a href="dashboard.php" class="btn btn-success btn-sm"><i class="bi bi-speedometer2 me-1"></i>관리자 대시보드</a>
                    <?php endif; ?>
                    <?php if ($st === 'pending'): ?>
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>1~3 영업일 내 결과 안내 예정</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-envelope" style="font-size: 3rem;"></i>
            <p class="mt-2">신청 시 입력한 이메일로 조회할 수 있습니다.</p>
        </div>
    <?php endif; ?>
</div>

</div><!-- /tab-content -->

<footer class="text-center py-4 text-muted small">
    &copy; <?= date('Y') ?> <?= term('site_name', '서울미녀') ?>. All rights reserved. · <a href="mailto:admin@seoulbeauty.app" class="text-muted">admin@seoulbeauty.app</a>
</footer>

<!-- 카카오 지도 SDK -->
<?php if ($kakao_key): ?>
<script src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?= htmlspecialchars($kakao_key) ?>&libraries=services"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
console.log('[RegDash] 초기화 시작');
console.log('[RegDash] 카카오맵 키:', '<?= $kakao_key ? substr($kakao_key, 0, 6) . "..." : "없음" ?>');
console.log('[RegDash] 통계: 병원 <?= $stat_hospitals ?>개, 후기 <?= $stat_reviews ?>개');

// ===== 다크모드 토글 =====
var themeBtn = document.getElementById('themeToggle');
var themeIcon = document.getElementById('themeIcon');
function updateThemeUI() {
    var t = document.documentElement.getAttribute('data-bs-theme');
    themeIcon.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    console.log('[RegDash] 테마:', t);
}
updateThemeUI();
themeBtn.addEventListener('click', function() {
    var curr = document.documentElement.getAttribute('data-bs-theme');
    var next = curr === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', next);
    localStorage.setItem('sb_reg_theme', next);
    updateThemeUI();
});

// ===== 카카오맵 =====
var map, marker;
var initLat = parseFloat(document.getElementById('latInput')?.value) || 37.5665;
var initLng = parseFloat(document.getElementById('lngInput')?.value) || 126.9780;

function initMap() {
    if (!document.getElementById('kakaoMap')) return; // 신청 폼은 hospital_apply_public.php 로 이동
    if (typeof kakao === 'undefined' || !kakao.maps) {
        console.warn('[RegDash] 카카오맵 SDK 미로드');
        document.getElementById('kakaoMap').innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-geo-alt" style="font-size:2rem;"></i><p>지도를 불러올 수 없습니다.</p></div>';
        return;
    }
    console.log('[RegDash] 카카오맵 초기화: lat=' + initLat + ', lng=' + initLng);

    var container = document.getElementById('kakaoMap');
    map = new kakao.maps.Map(container, {
        center: new kakao.maps.LatLng(initLat, initLng),
        level: 3
    });

    marker = new kakao.maps.Marker({
        position: new kakao.maps.LatLng(initLat, initLng),
        draggable: true
    });
    marker.setMap(map);

    // 마커 드래그 완료
    kakao.maps.event.addListener(marker, 'dragend', function() {
        var pos = marker.getPosition();
        document.getElementById('latInput').value = pos.getLat().toFixed(8);
        document.getElementById('lngInput').value = pos.getLng().toFixed(8);
        console.log('[RegDash] 마커 드래그:', pos.getLat().toFixed(6), pos.getLng().toFixed(6));
    });

    // 지도 클릭
    kakao.maps.event.addListener(map, 'click', function(mouseEvent) {
        var pos = mouseEvent.latLng;
        marker.setPosition(pos);
        document.getElementById('latInput').value = pos.getLat().toFixed(8);
        document.getElementById('lngInput').value = pos.getLng().toFixed(8);
        console.log('[RegDash] 지도 클릭:', pos.getLat().toFixed(6), pos.getLng().toFixed(6));
    });

    console.log('[RegDash] 카카오맵 초기화 완료');
}

// 탭 전환 시 지도 리사이즈
document.querySelector('[data-bs-target="#tabApply"]')?.addEventListener('shown.bs.tab', function() {
    console.log('[RegDash] 신청 탭 활성화 → 지도 리사이즈');
    setTimeout(function() {
        if (map) { map.relayout(); map.setCenter(new kakao.maps.LatLng(initLat, initLng)); }
    }, 200);
});

// ===== 주소 자동완성 + 좌표 변환 =====
var _addrTimer = null;
var addrInput = document.getElementById('addressInput');
var addrSuggest = document.getElementById('addrSuggest');

function geocodeAndMove(addr) {
    if (!addr || typeof kakao === 'undefined' || !kakao.maps || !kakao.maps.services) return;
    console.log('[RegDash] 주소→좌표:', addr);
    var geocoder = new kakao.maps.services.Geocoder();
    geocoder.addressSearch(addr, function(result, status) {
        if (status === kakao.maps.services.Status.OK) {
            var lat = parseFloat(result[0].y);
            var lng = parseFloat(result[0].x);
            document.getElementById('latInput').value = lat.toFixed(8);
            document.getElementById('lngInput').value = lng.toFixed(8);
            initLat = lat; initLng = lng;
            if (map && marker) {
                var pos = new kakao.maps.LatLng(lat, lng);
                map.setCenter(pos);
                marker.setPosition(pos);
            }
            console.log('[RegDash] 좌표 이동:', lat.toFixed(6), lng.toFixed(6));
        }
    });
}

// 타이핑 → 자동완성 드롭다운
addrInput?.addEventListener('input', function() {
    clearTimeout(_addrTimer);
    var q = this.value.trim();
    if (q.length < 2) { addrSuggest.style.display = 'none'; return; }
    _addrTimer = setTimeout(function() {
        if (typeof kakao === 'undefined' || !kakao.maps || !kakao.maps.services) return;
        var geocoder = new kakao.maps.services.Geocoder();
        geocoder.addressSearch(q, function(results, status) {
            if (status === kakao.maps.services.Status.OK && results.length > 0) {
                var html = '';
                results.slice(0, 5).forEach(function(r) {
                    var addr = r.road_address ? r.road_address.address_name : r.address_name;
                    var sub = r.road_address ? r.address_name : '';
                    html += '<button type="button" class="list-group-item list-group-item-action py-2 addr-item" data-addr="' + addr + '" data-lat="' + r.y + '" data-lng="' + r.x + '">'
                        + '<div class="fw-semibold small">' + addr + '</div>'
                        + (sub ? '<div class="text-muted" style="font-size:0.75rem;">' + sub + '</div>' : '')
                        + '</button>';
                });
                addrSuggest.innerHTML = html;
                addrSuggest.style.display = 'block';
                console.log('[RegDash] 주소 후보:', results.length + '건');
            } else {
                addrSuggest.style.display = 'none';
            }
        });
    }, 400);
});

// 후보 클릭 → 선택
addrSuggest?.addEventListener('click', function(e) {
    var item = e.target.closest('.addr-item');
    if (!item) return;
    var addr = item.dataset.addr;
    var lat = parseFloat(item.dataset.lat);
    var lng = parseFloat(item.dataset.lng);
    addrInput.value = addr;
    document.getElementById('latInput').value = lat.toFixed(8);
    document.getElementById('lngInput').value = lng.toFixed(8);
    initLat = lat; initLng = lng;
    if (map && marker) {
        var pos = new kakao.maps.LatLng(lat, lng);
        map.setCenter(pos);
        marker.setPosition(pos);
    }
    addrSuggest.style.display = 'none';
    console.log('[RegDash] 주소 선택:', addr, lat.toFixed(6), lng.toFixed(6));
});

// 외부 클릭 시 드롭다운 닫기
document.addEventListener('click', function(e) {
    if (!addrInput?.contains(e.target) && !addrSuggest?.contains(e.target)) {
        if (addrSuggest) addrSuggest.style.display = 'none';
    }
});

// 엔터 → 첫 번째 후보 선택 또는 직접 변환
addrInput?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        var first = addrSuggest?.querySelector('.addr-item');
        if (first && addrSuggest.style.display !== 'none') {
            first.click();
        } else {
            geocodeAndMove(this.value.trim());
        }
    }
});

// ===== 폼 제출 =====
document.getElementById('applyForm')?.addEventListener('submit', function(e) {
    var checked = [];
    document.querySelectorAll('.cat-check:checked').forEach(function(cb) { checked.push(cb.value); });
    document.getElementById('categoriesInput').value = checked.join(',');
    console.log('[RegDash] 폼 제출 — 카테고리:', checked.length + '개, 위도:', document.getElementById('latInput').value, '경도:', document.getElementById('lngInput').value);
});

// 사업자번호 자동 포맷
document.querySelector('[name="business_number"]')?.addEventListener('input', function(e) {
    var v = e.target.value.replace(/[^0-9]/g, '');
    if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
    else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
    e.target.value = v;
});

// ===== 탭 변경 로그 =====
document.querySelectorAll('#regTabs .nav-link').forEach(function(btn) {
    btn.addEventListener('click', function() {
        console.log('[RegDash] 탭 전환:', this.textContent.trim());
    });
});

// 지도 초기화 (DOM 로드 후)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
} else {
    initMap();
}

// ===== 병원명 입력 시 3개 플랫폼 등록 여부 체크 + 검색 링크 =====
var _checkTimer = null;
var _platformLinks = {
    gangnamunni: { name: '강남언니', color: '#ff6b81', icon: 'bi-heart-pulse', url: 'https://www.gangnamunni.com/search?query=' },
    babitalk:    { name: '바비톡',   color: '#6c5ce7', icon: 'bi-chat-heart',  url: 'https://web.babitalk.com/search?keyword=' },
    yeoshin:     { name: '여신티켓', color: '#00b894', icon: 'bi-ticket-perforated', url: 'https://www.yeoshin.co.kr/search?query=' },
};

function showPlatformLinks(q) {
    var panel = document.getElementById('platformCheck');
    console.log('[RegDash] 플랫폼 검색 링크:', q);
    var html = '<div class="d-flex flex-wrap gap-2 align-items-center">'
        + '<small class="text-muted me-1"><i class="bi bi-search me-1"></i>타 플랫폼에서 확인:</small>';
    for (var key in _platformLinks) {
        var p = _platformLinks[key];
        html += '<a href="' + p.url + encodeURIComponent(q) + '" target="_blank" '
            + 'class="badge text-decoration-none d-flex align-items-center gap-1" '
            + 'style="background:' + p.color + ';color:#fff;" '
            + 'title="' + p.name + '에서 \'' + q + '\' 검색">'
            + '<i class="bi ' + p.icon + '"></i> ' + p.name
            + ' <i class="bi bi-box-arrow-up-right" style="font-size:0.7rem;"></i></a>';
    }
    html += '</div>';
    panel.innerHTML = html;
    panel.style.display = 'block';
}

// 자동: 병원명 입력 시
document.getElementById('hospitalNameInput')?.addEventListener('input', function() {
    clearTimeout(_checkTimer);
    var q = this.value.trim();
    if (q.length < 2) { document.getElementById('platformCheck').style.display = 'none'; return; }
    _checkTimer = setTimeout(function() { showPlatformLinks(q); }, 500);
});

// ===== 수동 플랫폼 검색 버튼 =====
document.getElementById('btnPlatformSearch')?.addEventListener('click', function() {
    var q = document.getElementById('hospitalNameInput').value.trim();
    if (!q) { alert('병원명을 먼저 입력하세요.'); document.getElementById('hospitalNameInput').focus(); return; }
    console.log('[RegDash] 수동 플랫폼 검색:', q);
    showPlatformLinks(q);
});

console.log('[RegDash] 스크립트 로드 완료');
</script>
</body>
</html>

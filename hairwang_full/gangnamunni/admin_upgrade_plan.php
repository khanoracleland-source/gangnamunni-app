<?php
/**
 * 확장 계획 페이지 - 2탭 구조
 * Tab 1: 인프라 확장 계획
 * Tab 2: 채팅 기능 확장 계획
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) {
    header('Location: dashboard.php');
    exit;
}

// 현재 서버 상태
$server_info = [
    'total_hospitals' => mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as count FROM gangnamunni_hospitals"))['count'],
    'total_reviews' => mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as count FROM hospital_reviews"))['count'],
    'total_images' => mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as count FROM hospital_images"))['count'],
    'db_size' => round(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.TABLES WHERE table_schema = '" . G5_MYSQL_DB . "'"))['size_mb'], 2),
];

$traffic_projection = [
    'current' => 1000,
    '6months' => 10000,
    '12months' => 50000,
];

$admin_page_title = '확장 계획';
$admin_current = 'upgrade';
$admin_extra_css = '
    .stat-card-gradient {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #764ba2 100%);
        color: #fff;
    }
    .phase-header-urgent { background: linear-gradient(135deg, #dc3545 0%, #ee5a6f 100%); color: #fff; }
    .phase-header-important { background: linear-gradient(135deg, #fd7e14 0%, #ff7f50 100%); color: #fff; }
    .phase-header-optional { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); color: #fff; }
    .phase-header-chat { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: #fff; }
    .phase-header-ai { background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%); color: #fff; }
    .roi-banner {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #764ba2 100%);
        color: #fff;
    }
    .chat-roi-banner {
        background: linear-gradient(135deg, #198754 0%, #20c997 100%);
        color: #fff;
    }
    .timeline-line {
        position: relative;
        padding-left: 2rem;
        border-left: 3px solid var(--bs-primary);
    }
    .timeline-line .timeline-dot {
        position: absolute;
        left: -0.6rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        background: var(--bs-primary);
        border: 3px solid var(--bs-body-bg);
        box-shadow: 0 0 0 2px var(--bs-primary);
    }
    .timeline-line .timeline-entry {
        position: relative;
        padding-bottom: 1.5rem;
    }
    .timeline-line .timeline-entry:last-child {
        padding-bottom: 0;
    }
    .competitor-logo {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        color: #fff;
    }
    .nav-tabs .nav-link {
        font-weight: 600;
        font-size: 1rem;
        padding: 0.75rem 1.5rem;
    }
    .nav-tabs .nav-link.active {
        border-bottom: 3px solid var(--bs-primary);
    }
    .feature-check { color: #198754; }
    .feature-x { color: #dc3545; }
    .feature-partial { color: #fd7e14; }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-rocket-takeoff"></i> 서울미녀 플랫폼 확장 계획</h5>
</div>

<!-- 탭 네비게이션 -->
<ul class="nav nav-tabs mb-3" id="upgradeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="infra-tab" data-bs-toggle="tab" data-bs-target="#infra-pane" type="button" role="tab" aria-controls="infra-pane" aria-selected="true">
            <i class="bi bi-hdd-rack me-1"></i> 인프라 확장
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat-pane" type="button" role="tab" aria-controls="chat-pane" aria-selected="false">
            <i class="bi bi-chat-dots me-1"></i> 채팅 기능
        </button>
    </li>
</ul>

<!-- 탭 콘텐츠 -->
<div class="tab-content" id="upgradeTabContent">

<!-- ============================================================ -->
<!-- TAB 1: 인프라 확장 -->
<!-- ============================================================ -->
<div class="tab-pane fade show active" id="infra-pane" role="tabpanel" aria-labelledby="infra-tab">

<!-- 알림 배너 -->
<div class="alert alert-danger d-flex align-items-center gap-3 mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
    <div>
        <h6 class="alert-heading fw-bold mb-1">인프라 확장이 필요합니다</h6>
        <p class="mb-0 small">AI 자동화로 <strong>4주 내 전체 인프라 확장</strong>을 완료합니다. DB 분리 + Object Storage + 이중화까지 한 번에!</p>
    </div>
</div>

<!-- 현재 상태 통계 -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card-gradient border-0 h-100">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold"><?= number_format($server_info['total_hospitals']) ?></div>
                <div class="small opacity-75">총 병원 수</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card-gradient border-0 h-100">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold"><?= number_format($server_info['total_reviews']) ?></div>
                <div class="small opacity-75">총 리뷰 수</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card-gradient border-0 h-100">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold"><?= number_format($server_info['total_images']) ?></div>
                <div class="small opacity-75">총 이미지 수</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card-gradient border-0 h-100">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold"><?= $server_info['db_size'] ?> MB</div>
                <div class="small opacity-75">DB 크기</div>
            </div>
        </div>
    </div>
</div>

<!-- 단계별 확장 계획 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-steps"></i> 단계별 확장 계획</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Phase 1 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-urgent rounded-top px-3 py-2 fw-semibold">Phase 1: DB 분리</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">긴급도</td><td class="text-end"><span class="badge bg-danger">높음</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">1주일</td></tr>
                                <tr><td class="text-body-secondary">추가 비용</td><td class="text-end fw-bold text-primary">+$16/월</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 속도 30% UP</td></tr>
                            </tbody>
                        </table>
                        <p class="text-body-secondary small mb-0">데이터베이스 전용 서버로 분리하여 웹 서버 부하를 50% 감소시킵니다.</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-danger" style="width: 90%;"></div></div>
                        <small class="text-body-secondary">긴급도 90%</small>
                    </div>
                </div>
            </div>
            <!-- Phase 2 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-important rounded-top px-3 py-2 fw-semibold">Phase 2: Object Storage</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">긴급도</td><td class="text-end"><span class="badge bg-warning text-dark">중간</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">1주일</td></tr>
                                <tr><td class="text-body-secondary">추가 비용</td><td class="text-end fw-bold text-primary">+$8/월</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 로딩 50% UP</td></tr>
                            </tbody>
                        </table>
                        <p class="text-body-secondary small mb-0">이미지/파일을 별도 저장소로 분리하여 서버 디스크 부담을 80% 감소시킵니다.</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-warning" style="width: 60%;"></div></div>
                        <small class="text-body-secondary">긴급도 60%</small>
                    </div>
                </div>
            </div>
            <!-- Phase 3 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-optional rounded-top px-3 py-2 fw-semibold">Phase 3: 이중화</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">긴급도</td><td class="text-end"><span class="badge bg-info">낮음</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">2주일</td></tr>
                                <tr><td class="text-body-secondary">추가 비용</td><td class="text-end fw-bold text-primary">+$102/월</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 99.9% 가동률</td></tr>
                            </tbody>
                        </table>
                        <p class="text-body-secondary small mb-0">완전 이중화로 서버 장애 시 자동 복구, 데이터 손실 방지를 보장합니다.</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-info" style="width: 30%;"></div></div>
                        <small class="text-body-secondary">긴급도 30%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 월별 비용 비교 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack"></i> 월별 비용 비교</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered admin-table mb-0">
                <thead class="table-light">
                    <tr><th>구분</th><th>현재</th><th>Phase 1</th><th>Phase 2</th><th>Phase 3</th></tr>
                </thead>
                <tbody>
                    <tr><td class="fw-semibold">월 비용</td><td>$37</td><td class="fw-bold text-primary">$53 (+$16)</td><td class="fw-bold text-primary">$61 (+$24)</td><td class="fw-bold text-primary">$139 (+$102)</td></tr>
                    <tr><td class="fw-semibold">처리 능력</td><td>기준 (1배)</td><td>2배</td><td>5배</td><td class="fw-bold text-primary">100배</td></tr>
                    <tr><td class="fw-semibold">동시 접속</td><td>500명</td><td>1,000명</td><td>2,500명</td><td class="fw-bold text-primary">50,000명</td></tr>
                    <tr><td class="fw-semibold">가동률</td><td>98%</td><td>98%</td><td>99%</td><td class="fw-bold text-primary">99.9%</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ROI -->
<div class="card roi-banner border-0 mb-3">
    <div class="card-body text-center py-4">
        <h5 class="fw-bold mb-1">투자 대비 효과 (ROI)</h5>
        <div class="display-2 fw-bold my-3">26배</div>
        <p class="mb-1 opacity-75">비용 3.76배 증가 -> 처리 능력 100배 증가</p>
        <p class="mb-0 opacity-75">Phase 3 완료 시 비용 효율이 <strong>26배</strong> 향상됩니다</p>
    </div>
</div>

<!-- AI 기반 구현 일정 -->
<div class="card mb-3 border-primary">
    <div class="card-header py-2 bg-primary bg-opacity-10">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-robot"></i> AI 기반 구현 일정</h6>
            <span class="badge bg-primary"><i class="bi bi-lightning-charge-fill me-1"></i>전체 1개월 내 완료</span>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Claude AI + 자동화 스크립트</strong>로 기존 6~12개월 작업을 <strong class="text-primary">4주 내</strong>에 완료합니다.
        </div>
        <div class="timeline-line">
            <div class="timeline-entry">
                <div class="timeline-dot bg-danger" style="top: 0.25rem;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger">Week 1</span>
                            <h6 class="text-danger fw-bold mb-0">1주차: DB 분리 + 최적화</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase 1: MariaDB Master-Slave + 쿼리 최적화</p>
                        <small class="text-body-secondary">AI가 자동으로 슬로우 쿼리 분석, 인덱스 생성, 레플리카 구성</small>
                        <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-danger" style="width:100%"></div></div>
                    </div>
                </div>
            </div>
            <div class="timeline-entry">
                <div class="timeline-dot bg-warning" style="top: 0.25rem;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark">Week 2</span>
                            <h6 class="text-warning fw-bold mb-0">2주차: Object Storage + CDN</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase 2: S3 호환 스토리지 + Cloudflare CDN</p>
                        <small class="text-body-secondary">AI가 이미지 마이그레이션 스크립트 자동 생성, CDN 캐시 규칙 최적화</small>
                        <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-warning" style="width:100%"></div></div>
                    </div>
                </div>
            </div>
            <div class="timeline-entry">
                <div class="timeline-dot bg-success" style="top: 0.25rem;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success">Week 3</span>
                            <h6 class="text-success fw-bold mb-0">3주차: 이중화 + 모니터링</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase 3: Docker Swarm + Health Check + 알림</p>
                        <small class="text-body-secondary">AI가 Docker 컴포즈 자동 생성, 장애 감지 + Telegram 알림 구축</small>
                        <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-success" style="width:100%"></div></div>
                    </div>
                </div>
            </div>
            <div class="timeline-entry">
                <div class="timeline-dot bg-primary" style="top: 0.25rem;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary">Week 4</span>
                            <h6 class="text-primary fw-bold mb-0">4주차: 테스트 + 최종 튜닝</h6>
                        </div>
                        <p class="fw-semibold mb-0">부하 테스트 + 보안 점검 + 성능 미세 조정</p>
                        <small class="text-body-secondary">AI가 자동 부하 테스트, 취약점 스캔, 성능 리포트 생성</small>
                        <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-primary" style="width:100%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card bg-body-tertiary border-0 mt-3">
            <div class="card-body py-3">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="text-muted small">기존 수동 방식</div>
                        <div class="fs-4 fw-bold text-body-secondary text-decoration-line-through">6~12개월</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><i class="bi bi-arrow-right fs-4 text-primary"></i></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">AI 자동화 방식</div>
                        <div class="fs-4 fw-bold text-primary">4주 (30일)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 트래픽 예측 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-graph-up-arrow"></i> 트래픽 예측</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered admin-table mb-0">
                <thead class="table-light">
                    <tr><th>시점</th><th>일 방문자</th><th>필요 인프라</th><th>권장 조치</th></tr>
                </thead>
                <tbody>
                    <tr><td>현재</td><td><?= number_format($traffic_projection['current']) ?>명</td><td>현재 구성</td><td><span class="badge bg-success"><i class="bi bi-check-lg"></i> 충분</span></td></tr>
                    <tr class="table-info"><td>1개월 후 (AI 완료)</td><td><?= number_format($traffic_projection['6months']) ?>명 대응 가능</td><td>Phase 1+2+3 전부</td><td><span class="badge bg-success"><i class="bi bi-robot"></i> AI 자동 구축 완료</span></td></tr>
                    <tr class="table-success"><td>6개월 후</td><td><?= number_format($traffic_projection['6months']) ?>명</td><td>이미 구축 완료</td><td><span class="badge bg-success"><i class="bi bi-check-circle"></i> 여유있게 대응</span></td></tr>
                    <tr class="table-success"><td>12개월 후</td><td><?= number_format($traffic_projection['12months']) ?>명</td><td>이미 구축 완료</td><td><span class="badge bg-success"><i class="bi bi-check-circle"></i> 안정 운영</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FAQ -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-question-circle"></i> 자주 묻는 질문</h6>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="faqAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#faq1"><strong>Q1. 서비스 중단 시간이 있나요?</strong></button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-body-secondary small">모든 작업은 새벽 시간대에 진행하며, 실제 서비스 중단은 최대 10분 이내입니다.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#faq2"><strong>Q2. 데이터 손실 위험은?</strong></button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-body-secondary small">모든 작업 전 전체 백업을 수행하며, 롤백 계획이 준비되어 있습니다.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#faq3"><strong>Q3. AI로 정말 4주 만에 가능한가요?</strong></button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-body-secondary small">Claude AI + Playwright 자동화로 인프라 구성, 마이그레이션 스크립트, 모니터링 설정을 자동 생성합니다. 사람이 6개월 걸릴 작업을 AI가 코드 생성 + 자동 배포하면 4주면 충분합니다.</div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /infra-pane -->

<!-- ============================================================ -->
<!-- TAB 2: 채팅 기능 -->
<!-- ============================================================ -->
<div class="tab-pane fade" id="chat-pane" role="tabpanel" aria-labelledby="chat-tab">

<!-- 알림 배너 -->
<div class="alert alert-success d-flex align-items-center gap-3 mb-3" role="alert">
    <i class="bi bi-chat-dots-fill fs-2"></i>
    <div>
        <h6 class="alert-heading fw-bold mb-1">실시간 채팅 상담 기능이 필요합니다</h6>
        <p class="mb-0 small">경쟁 앱 분석 결과, <strong>서울미녀</strong>는 인앱 실시간 채팅을 제공하고 있으며 이는 <strong>전환율 3~5배 향상</strong>의 핵심 기능입니다.</p>
    </div>
</div>

<!-- 경쟁 앱 채팅 기능 비교 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-line"></i> 경쟁 앱 채팅 기능 비교</h6>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <!-- 서울미녀 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="competitor-logo mx-auto mb-2" style="background: linear-gradient(135deg, #ff6b6b, #ee5a6f);">G</div>
                        <h6 class="fw-bold">서울미녀</h6>
                        <span class="badge bg-success mb-2">실시간 인앱 채팅</span>
                        <ul class="list-unstyled text-start small mb-0">
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 실시간 1:1 채팅</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 자동 번역 (Papago)</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 자동 응답 메시지</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 템플릿 메시지</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 병원 관리 대시보드</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 데스크톱 알림</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 사진/파일 전송</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 운영시간 설정</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> WeChat 미니프로그램</li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- 바비톡 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="competitor-logo mx-auto mb-2" style="background: linear-gradient(135deg, #ffa726, #ff7043);">B</div>
                        <h6 class="fw-bold">바비톡</h6>
                        <span class="badge bg-warning text-dark mb-2">상담 요청 방식</span>
                        <ul class="list-unstyled text-start small mb-0">
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 인앱 실시간 채팅 없음</li>
                            <li class="feature-partial"><i class="bi bi-dash-circle-fill me-1"></i> 상담 요청 -> 병원 연락</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 부작용톡 (커뮤니티)</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 자유톡 (커뮤니티)</li>
                            <li class="feature-partial"><i class="bi bi-dash-circle-fill me-1"></i> 카카오톡 외부 연동</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 자동 번역 없음</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 병원 채팅 대시보드 없음</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 후기 82만개+ 기반</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 글로벌 지원 없음</li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- 여신티켓 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="competitor-logo mx-auto mb-2" style="background: linear-gradient(135deg, #ab47bc, #7e57c2);">Y</div>
                        <h6 class="fw-bold">여신티켓</h6>
                        <span class="badge bg-secondary mb-2">카카오톡 의존</span>
                        <ul class="list-unstyled text-start small mb-0">
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 인앱 채팅 없음</li>
                            <li class="feature-partial"><i class="bi bi-dash-circle-fill me-1"></i> 카카오톡 채널 상담</li>
                            <li class="feature-partial"><i class="bi bi-dash-circle-fill me-1"></i> 상담 요청 -> 전화/카톡</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 자동 번역 없음</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 병원 채팅 관리 없음</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 데스크톱 알림 없음</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 480만 회원</li>
                            <li class="feature-check"><i class="bi bi-check-circle-fill me-1"></i> 27,000+ 병원</li>
                            <li class="feature-x"><i class="bi bi-x-circle-fill me-1"></i> 글로벌 지원 없음</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- 비교 요약 테이블 -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered admin-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>기능</th>
                        <th class="text-center">서울미녀</th>
                        <th class="text-center">바비톡</th>
                        <th class="text-center">여신티켓</th>
                        <th class="text-center text-primary fw-bold">서울미녀 (목표)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">인앱 실시간 채팅</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">사진/파일 전송</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">자동 번역</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">자동 응답</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">병원 관리 대시보드</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-partial"><i class="bi bi-dash-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">푸시/데스크톱 알림</td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">AI 챗봇 (자동 상담)</td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-x"><i class="bi bi-x-circle-fill"></i></td>
                        <td class="text-center feature-check"><i class="bi bi-check-circle-fill"></i> <small class="text-primary">차별화</small></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 채팅 기능 단계별 계획 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-steps"></i> 채팅 기능 단계별 구현 계획</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Phase A -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-urgent rounded-top px-3 py-2 fw-semibold">Phase A: 기본 채팅</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">중요도</td><td class="text-end"><span class="badge bg-danger">필수</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">2주일</td></tr>
                                <tr><td class="text-body-secondary">기술 스택</td><td class="text-end">WebSocket + Laravel</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 전환율 3배 UP</td></tr>
                            </tbody>
                        </table>
                        <ul class="small text-body-secondary mb-0 ps-3">
                            <li>사용자-병원 1:1 실시간 채팅</li>
                            <li>텍스트 + 이미지 전송</li>
                            <li>읽음 확인 / 타이핑 표시</li>
                            <li>채팅 내역 저장 (DB)</li>
                            <li>병원 상세 페이지 채팅 버튼</li>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-danger" style="width: 95%;"></div></div>
                        <small class="text-body-secondary">중요도 95%</small>
                    </div>
                </div>
            </div>
            <!-- Phase B -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-chat rounded-top px-3 py-2 fw-semibold">Phase B: 병원 관리 도구</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">중요도</td><td class="text-end"><span class="badge bg-success">높음</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">1주일</td></tr>
                                <tr><td class="text-body-secondary">기술 스택</td><td class="text-end">HMS Laravel + JS</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 응답률 80% UP</td></tr>
                            </tbody>
                        </table>
                        <ul class="small text-body-secondary mb-0 ps-3">
                            <li>병원별 채팅 관리 대시보드</li>
                            <li>자동 인사 메시지 설정</li>
                            <li>영업시간 외 자동 응답</li>
                            <li>템플릿 메시지 (빠른 응답)</li>
                            <li>채팅 통계 (응답 시간 등)</li>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: 80%;"></div></div>
                        <small class="text-body-secondary">중요도 80%</small>
                    </div>
                </div>
            </div>
            <!-- Phase C -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="phase-header-ai rounded-top px-3 py-2 fw-semibold">Phase C: AI + 글로벌</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-2">
                            <tbody>
                                <tr><td class="text-body-secondary">중요도</td><td class="text-end"><span class="badge bg-info">차별화</span></td></tr>
                                <tr><td class="text-body-secondary">예상 기간</td><td class="text-end">2주일</td></tr>
                                <tr><td class="text-body-secondary">기술 스택</td><td class="text-end">Claude API + Papago</td></tr>
                                <tr><td class="text-body-secondary">효과</td><td class="text-end text-success fw-bold"><i class="bi bi-check-lg"></i> 경쟁사 초월</td></tr>
                            </tbody>
                        </table>
                        <ul class="small text-body-secondary mb-0 ps-3">
                            <li>AI 챗봇 (시술 추천, FAQ)</li>
                            <li>실시간 자동 번역 (한/영/일/중)</li>
                            <li>AI 요약 (대화 내용 자동 요약)</li>
                            <li>감정 분석 (불만 고객 감지)</li>
                            <li>푸시 알림 (FCM + 데스크톱)</li>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-info" style="width: 60%;"></div></div>
                        <small class="text-body-secondary">중요도 60% (차별화 전략)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 기술 아키텍처 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3"></i> 채팅 시스템 아키텍처</h6>
    </div>
    <div class="card-body">
        <div class="card bg-body-tertiary border-0 mb-3">
            <div class="card-body py-3">
                <pre class="mb-0 small" style="font-family: 'D2Coding', monospace; line-height: 1.6;">
<strong>[사용자 앱/웹]</strong>                     <strong>[병원 관리자]</strong>
    |                                   |
    |  WebSocket (wss://)               |  WebSocket (wss://)
    |                                   |
    v                                   v
+-------------------------------------------+
|          <strong>채팅 서버 (Node.js)</strong>            |
|  Socket.IO / Laravel WebSocket            |
|  - 실시간 메시지 전달                        |
|  - 타이핑 표시 / 읽음 확인                   |
|  - 접속 상태 관리                            |
+-------------------------------------------+
    |               |               |
    v               v               v
+----------+  +-----------+  +-----------+
| <strong>MariaDB</strong>  |  | <strong>Redis</strong>     |  | <strong>S3 스토리지</strong>|
| 채팅 기록 |  | 세션/캐시  |  | 이미지/파일|
| 사용자   |  | 접속 상태  |  | 저장소    |
+----------+  +-----------+  +-----------+
    |
    v
+-------------------------------------------+
|          <strong>AI 엔진 (선택)</strong>                 |
|  Claude API: 자동 상담, 요약, 감정분석       |
|  Papago API: 실시간 번역                    |
|  FCM: 푸시 알림                             |
+-------------------------------------------+</pre>
            </div>
        </div>

        <!-- DB 테이블 설계 -->
        <h6 class="fw-semibold mb-2"><i class="bi bi-table"></i> DB 테이블 설계 (예정)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered admin-table mb-0">
                <thead class="table-light">
                    <tr><th>테이블</th><th>주요 컬럼</th><th>설명</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold"><code>chat_rooms</code></td>
                        <td class="small">id, hospital_id, user_id, status, last_message_at</td>
                        <td class="small">채팅방 (사용자-병원 1:1)</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold"><code>chat_messages</code></td>
                        <td class="small">id, room_id, sender_type, sender_id, message, type, read_at</td>
                        <td class="small">메시지 내역 (text/image/file/system)</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold"><code>chat_templates</code></td>
                        <td class="small">id, hospital_id, title, content, sort_order</td>
                        <td class="small">병원별 빠른 응답 템플릿</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold"><code>chat_settings</code></td>
                        <td class="small">id, hospital_id, auto_greeting, business_hours, auto_reply</td>
                        <td class="small">병원별 채팅 설정</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold"><code>chat_attachments</code></td>
                        <td class="small">id, message_id, file_path, file_type, file_size</td>
                        <td class="small">첨부 파일 (이미지/문서)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 비용 분석 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack"></i> 채팅 기능 비용 분석</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered admin-table mb-0">
                <thead class="table-light">
                    <tr><th>구분</th><th>Phase A (기본)</th><th>Phase B (관리)</th><th>Phase C (AI)</th></tr>
                </thead>
                <tbody>
                    <tr><td class="fw-semibold">개발 기간</td><td>2주</td><td>1주</td><td>2주</td></tr>
                    <tr><td class="fw-semibold">서버 추가 비용</td><td class="fw-bold text-primary">+$0 (기존 서버)</td><td class="fw-bold text-primary">+$0</td><td class="fw-bold text-primary">+$15/월 (Redis)</td></tr>
                    <tr><td class="fw-semibold">API 비용</td><td>$0</td><td>$0</td><td class="fw-bold text-primary">~$30/월 (Claude + Papago)</td></tr>
                    <tr><td class="fw-semibold">동시 채팅</td><td>100개</td><td>500개</td><td class="fw-bold text-primary">5,000개</td></tr>
                    <tr><td class="fw-semibold">기대 효과</td><td>전환율 3배</td><td>응답률 80%+</td><td class="fw-bold text-primary">경쟁사 초월</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ROI -->
<div class="card chat-roi-banner border-0 mb-3">
    <div class="card-body text-center py-4">
        <h5 class="fw-bold mb-1">채팅 기능 기대 효과</h5>
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="display-4 fw-bold">3~5배</div>
                <div class="small opacity-75">상담 전환율 향상</div>
            </div>
            <div class="col-md-3">
                <div class="display-4 fw-bold">-70%</div>
                <div class="small opacity-75">전화 문의 감소</div>
            </div>
            <div class="col-md-3">
                <div class="display-4 fw-bold">24/7</div>
                <div class="small opacity-75">AI 자동 상담</div>
            </div>
            <div class="col-md-3">
                <div class="display-4 fw-bold">4개국</div>
                <div class="small opacity-75">실시간 자동 번역</div>
            </div>
        </div>
    </div>
</div>

<!-- AI 기반 구현 일정 -->
<div class="card mb-3 border-success">
    <div class="card-header py-2 bg-success bg-opacity-10">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-robot"></i> AI 기반 구현 일정</h6>
            <span class="badge bg-success"><i class="bi bi-lightning-charge-fill me-1"></i>전체 5주 내 완료</span>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-success py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Claude AI</strong>로 채팅 서버 + UI + 관리 도구를 <strong class="text-success">5주 내</strong>에 완료합니다. 기존 HMS + 서울미녀 인프라를 활용합니다.
        </div>
        <div class="timeline-line" style="border-left-color: #198754;">
            <div class="timeline-entry">
                <div class="timeline-dot" style="top: 0.25rem; background: #dc3545; box-shadow: 0 0 0 2px #dc3545;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger">Week 1-2</span>
                            <h6 class="text-danger fw-bold mb-0">1~2주차: 기본 채팅 시스템</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase A: WebSocket 서버 + 채팅 UI + DB 설계</p>
                        <small class="text-body-secondary">Socket.IO 서버, 채팅방 UI (웹+Flutter), 메시지 저장, 읽음 확인</small>
                    </div>
                </div>
            </div>
            <div class="timeline-entry">
                <div class="timeline-dot" style="top: 0.25rem; background: #198754; box-shadow: 0 0 0 2px #198754;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success">Week 3</span>
                            <h6 class="text-success fw-bold mb-0">3주차: 병원 관리 도구</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase B: HMS 채팅 대시보드 + 자동 응답 + 템플릿</p>
                        <small class="text-body-secondary">병원 관리자용 채팅 관리 화면, 자동 인사 메시지, 영업시간 설정</small>
                    </div>
                </div>
            </div>
            <div class="timeline-entry">
                <div class="timeline-dot" style="top: 0.25rem; background: #6f42c1; box-shadow: 0 0 0 2px #6f42c1;"></div>
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge" style="background: #6f42c1;">Week 4-5</span>
                            <h6 class="fw-bold mb-0" style="color: #6f42c1;">4~5주차: AI + 글로벌</h6>
                        </div>
                        <p class="fw-semibold mb-0">Phase C: AI 챗봇 + 자동 번역 + 푸시 알림</p>
                        <small class="text-body-secondary">Claude API 연동 AI 상담봇, Papago 실시간 번역, FCM 푸시 알림</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card bg-body-tertiary border-0 mt-3">
            <div class="card-body py-3">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="text-muted small">기존 수동 방식</div>
                        <div class="fs-4 fw-bold text-body-secondary text-decoration-line-through">3~6개월</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><i class="bi bi-arrow-right fs-4 text-success"></i></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">AI 자동화 방식</div>
                        <div class="fs-4 fw-bold text-success">5주 (35일)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 채팅 FAQ -->
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-question-circle"></i> 자주 묻는 질문</h6>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="chatFaqAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#cfaq1"><strong>Q1. 채팅 서버를 별도로 구축해야 하나요?</strong></button>
                </h2>
                <div id="cfaq1" class="accordion-collapse collapse" data-bs-parent="#chatFaqAccordion">
                    <div class="accordion-body text-body-secondary small">Phase A에서는 기존 서버에 Node.js + Socket.IO를 추가 설치하여 운영합니다. 별도 서버 비용 없이 시작할 수 있으며, 동시 접속 100개까지 안정적으로 처리합니다. 사용자가 늘어나면 Redis + 별도 채팅 서버로 확장합니다.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#cfaq2"><strong>Q2. 서울미녀처럼 Channel.io를 사용하면 안 되나요?</strong></button>
                </h2>
                <div id="cfaq2" class="accordion-collapse collapse" data-bs-parent="#chatFaqAccordion">
                    <div class="accordion-body text-body-secondary small">Channel.io는 월 $36~$84/좌석의 유료 서비스입니다. 병원 100개 기준 최소 월 $3,600 이상의 비용이 발생합니다. 자체 구축 시 월 $15~$45로 동일한 기능을 제공하며, AI 챗봇 등 차별화 기능을 자유롭게 추가할 수 있습니다.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#cfaq3"><strong>Q3. Flutter 앱에서도 채팅이 가능한가요?</strong></button>
                </h2>
                <div id="cfaq3" class="accordion-collapse collapse" data-bs-parent="#chatFaqAccordion">
                    <div class="accordion-body text-body-secondary small">네. Flutter에서 socket_io_client 패키지를 사용하여 동일한 WebSocket 서버에 연결합니다. 웹과 앱 모두 동일한 채팅 경험을 제공하며, FCM 푸시 알림으로 앱이 백그라운드일 때도 새 메시지를 알려줍니다.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#cfaq4"><strong>Q4. AI 챗봇은 어떤 역할을 하나요?</strong></button>
                </h2>
                <div id="cfaq4" class="accordion-collapse collapse" data-bs-parent="#chatFaqAccordion">
                    <div class="accordion-body text-body-secondary small">AI 챗봇은 병원 영업시간 외에 자동으로 시술 정보, 가격 안내, FAQ 응답을 처리합니다. Claude API를 활용하여 자연스러운 한국어 대화가 가능하며, 복잡한 질문은 병원 상담사에게 자동으로 이관합니다. 서울미녀, 바비톡, 여신티켓 모두 AI 챗봇이 없으므로 <strong>서울미녀만의 차별화 포인트</strong>가 됩니다.</div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /chat-pane -->

</div><!-- /tab-content -->

<!-- 탭 상태 유지 JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // URL 해시로 탭 선택
    const hash = window.location.hash;
    if (hash === '#chat') {
        const chatTab = document.getElementById('chat-tab');
        if (chatTab) {
            const tab = new bootstrap.Tab(chatTab);
            tab.show();
        }
    }

    // 탭 변경 시 URL 해시 업데이트
    document.querySelectorAll('#upgradeTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            if (e.target.id === 'chat-tab') {
                history.replaceState(null, '', '#chat');
            } else {
                history.replaceState(null, '', window.location.pathname);
            }
        });
    });
});
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
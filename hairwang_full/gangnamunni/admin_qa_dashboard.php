<?php
/**
 * QA 통합 대시보드
 * - 전체 현황 (구현률, 기능별 상태)
 * - 테스트 시나리오 (클릭 가능한 단계별 테스트)
 * - 버그 수정 이력
 * - 보고서 아카이브
 * - 스크린샷 갤러리
 *
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-23
 */

session_start();
$admin_page_title = 'QA 통합 대시보드';
$admin_current = 'qa_dashboard';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('qa_dashboard');

$base = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
$admin_base = $base;

// ===== 기능 목록 정의 =====
$features = [
    '핵심 기능' => [
        ['name' => '메인 페이지', 'status' => 'done', 'url' => 'index.php', 'date' => '2026-02-11'],
        ['name' => '병원 목록', 'status' => 'done', 'url' => 'hospitals.php', 'date' => '2026-03-04'],
        ['name' => '병원 상세', 'status' => 'done', 'url' => 'detail.php?id=8093', 'date' => '2026-03-14'],
        ['name' => '예약', 'status' => 'done', 'url' => 'booking.php?hospital_id=8093', 'date' => '2026-03-05'],
        ['name' => '마이페이지', 'status' => 'done', 'url' => 'mypage.php', 'date' => '2026-03-13'],
        ['name' => '후기', 'status' => 'done', 'url' => 'reviews.php', 'date' => '2026-02-24'],
        ['name' => '이벤트', 'status' => 'done', 'url' => 'events.php', 'date' => '2026-02-25'],
        ['name' => '지도', 'status' => 'done', 'url' => 'map.php', 'date' => '2026-03-14'],
    ],
    '관리자' => [
        ['name' => '대시보드', 'status' => 'done', 'url' => 'dashboard.php', 'date' => '2026-02-09'],
        ['name' => '병원관리', 'status' => 'done', 'url' => 'admin_hospitals.php', 'date' => '2026-02-11'],
        ['name' => '광고관리', 'status' => 'done', 'url' => 'admin_ads.php', 'date' => '2026-03-04'],
        ['name' => '예약관리', 'status' => 'done', 'url' => 'admin_bookings.php', 'date' => '2026-03-05'],
        ['name' => '후기관리', 'status' => 'done', 'url' => 'admin_reviews.php', 'date' => '2026-02-24'],
        ['name' => '이벤트관리', 'status' => 'done', 'url' => 'admin_events.php', 'date' => '2026-02-25'],
        ['name' => '상담관리', 'status' => 'done', 'url' => 'admin_consultations.php', 'date' => '2026-03-04'],
        ['name' => '회원관리', 'status' => 'done', 'url' => 'admin_members.php', 'date' => '2026-02-11'],
        ['name' => '콘텐츠관리', 'status' => 'done', 'url' => 'admin_tips.php', 'date' => '2026-03-04'],
    ],
    '수익 모델' => [
        ['name' => 'CPV 통합 설정', 'status' => 'done', 'url' => 'admin_cpv_settings.php', 'date' => '2026-03-11'],
        ['name' => 'CPV 지갑 관리', 'status' => 'done', 'url' => 'admin_wallet.php', 'date' => '2026-03-11'],
        ['name' => 'CPV 단가 규칙', 'status' => 'done', 'url' => 'admin_cpv_rates.php', 'date' => '2026-03-11'],
        ['name' => 'CPV 대시보드', 'status' => 'done', 'url' => 'admin_cpv_dashboard.php', 'date' => '2026-03-11'],
        ['name' => 'CPV 정산', 'status' => 'done', 'url' => 'admin_settlement.php', 'date' => '2026-03-11'],
        ['name' => 'PG 결제', 'status' => 'done', 'url' => 'admin_payment.php', 'date' => '2026-03-08'],
        ['name' => '광고 시스템', 'status' => 'done', 'url' => 'admin_ads.php', 'date' => '2026-03-04'],
        ['name' => '광고 단가', 'status' => 'done', 'url' => 'admin_ad_pricing.php', 'date' => '2026-03-04'],
        ['name' => '광고 통계', 'status' => 'done', 'url' => 'admin_ad_stats.php', 'date' => '2026-03-04'],
        ['name' => '프로모션', 'status' => 'done', 'url' => 'admin_promos.php', 'date' => '2026-03-04'],
        ['name' => '결제 브릿지', 'status' => 'done', 'url' => 'admin_pay_bridge.php', 'date' => '2026-03-08'],
        ['name' => '상품 관리', 'status' => 'done', 'url' => 'admin_payment.php?tab=products', 'date' => '2026-03-08'],
        ['name' => '매출 통계', 'status' => 'done', 'url' => 'admin_payment.php?tab=stats', 'date' => '2026-03-08'],
        ['name' => 'PG 설정', 'status' => 'done', 'url' => 'admin_payment.php?tab=pg_settings', 'date' => '2026-03-08'],
    ],
    '최근 개발' => [
        ['name' => '하단 탭 설정', 'status' => 'done', 'url' => 'admin_navigation.php', 'date' => '2026-03-22'],
        ['name' => '복합 필터', 'status' => 'done', 'url' => 'hospitals.php', 'date' => '2026-03-22'],
        ['name' => '정렬 옵션', 'status' => 'done', 'url' => 'hospitals.php', 'date' => '2026-03-22'],
        ['name' => '커뮤니티 CRUD', 'status' => 'done', 'url' => 'community.php', 'date' => '2026-03-22'],
    ],
    '보안/법적' => [
        ['name' => 'UGC Safety', 'status' => 'done', 'url' => 'admin_content_reports.php', 'date' => '2026-03-17'],
        ['name' => '이용약관', 'status' => 'done', 'url' => 'terms_of_service.php', 'date' => '2026-03-17'],
        ['name' => '개인정보처리방침', 'status' => 'done', 'url' => 'privacy_policy_patient_android.html', 'date' => '2026-03-06'],
        ['name' => '계정삭제', 'status' => 'done', 'url' => 'account_deletion_patient.html', 'date' => '2026-03-06'],
        ['name' => '커뮤니티 가이드라인', 'status' => 'done', 'url' => 'community_guidelines.php', 'date' => '2026-03-17'],
    ],
    '앱' => [
        ['name' => 'Android Patient', 'status' => 'done', 'url' => '#', 'date' => '2026-03-17'],
        ['name' => 'Android Partner', 'status' => 'done', 'url' => '#', 'date' => '2026-03-17'],
        ['name' => 'iOS Patient', 'status' => 'partial', 'url' => '#', 'date' => '2026-03-17'],
        ['name' => 'iOS Partner', 'status' => 'partial', 'url' => '#', 'date' => '2026-03-17'],
    ],
];

// 통계 계산
$total = 0; $done = 0; $partial = 0; $notdone = 0;
foreach ($features as $cat => $items) {
    foreach ($items as $item) {
        $total++;
        if ($item['status'] === 'done') $done++;
        elseif ($item['status'] === 'partial') $partial++;
        else $notdone++;
    }
}
$pct = $total > 0 ? round(($done / $total) * 100, 1) : 0;

// ===== 버그 수정 이력 =====
$bugfixes = [];
$r = @mysqli_query($connect, "SELECT * FROM gangnamunni_fix_log ORDER BY fix_date DESC, id DESC LIMIT 50");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $bugfixes[] = $row; }

// ===== HTML 리포트 목록 =====
$report_dir = __DIR__;
$all_reports = [];
$report_files = glob($report_dir . '/*.html');
if ($report_files) {
    foreach ($report_files as $rf) {
        $fn = basename($rf);
        $size = filesize($rf);
        $mtime = filemtime($rf);
        $all_reports[] = [
            'file' => $fn,
            'size' => $size,
            'date' => date('Y-m-d', $mtime),
            'url' => $base . $fn,
        ];
    }
}
usort($all_reports, function($a, $b) { return strcmp($b['date'], $a['date']); });

// 카테고리 분류
function categorize_report($fn) {
    if (preg_match('/^qa_|qa_master|qa_full|qa_round|qa_back|qa_screenshot|qa_scenario|bugfix_test|full_test|client_test|new_features_test/', $fn)) return 'QA 보고서';
    if (preg_match('/^project_status|progress_report/', $fn)) return '프로젝트 현황';
    if (preg_match('/cpv_/', $fn)) return 'CPV 시스템';
    if (preg_match('/privacy_|account_deletion|terms_/', $fn)) return '법적/스토어';
    if (preg_match('/payment_|pg_test/', $fn)) return '결제';
    return '기타';
}

// ===== 스크린샷 목록 =====
$screenshot_dirs = ['cpv_screenshots', 'qa_v1.1_screenshots'];
$screenshots = [];
foreach ($screenshot_dirs as $sd) {
    $sdir = $report_dir . '/' . $sd;
    if (is_dir($sdir)) {
        $imgs = glob($sdir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
        if ($imgs) {
            foreach ($imgs as $img) {
                $screenshots[] = [
                    'file' => $sd . '/' . basename($img),
                    'name' => basename($img),
                    'date' => date('Y-m-d', filemtime($img)),
                    'dir' => $sd,
                    'url' => $base . $sd . '/' . basename($img),
                ];
            }
        }
    }
}
usort($screenshots, function($a, $b) { return strcmp($b['date'], $a['date']); });

// admin_header include
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 콘텐츠 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>QA 통합 대시보드</h4>
    <div>
        <span class="badge bg-primary fs-6 me-2">구현률 <?php echo $pct; ?>%</span>
        <span class="text-muted small">최종 업데이트: <?php echo date('Y-m-d H:i'); ?></span>
    </div>
</div>

<!-- 탭 네비게이션 -->
<ul class="nav nav-tabs mb-4" id="qaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
            <i class="bi bi-bar-chart-line me-1"></i>전체 현황
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="scenarios-tab" data-bs-toggle="tab" data-bs-target="#scenarios" type="button" role="tab">
            <i class="bi bi-list-check me-1"></i>테스트 시나리오
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bugfix-tab" data-bs-toggle="tab" data-bs-target="#bugfix" type="button" role="tab">
            <i class="bi bi-bug me-1"></i>버그 수정 이력
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
            <i class="bi bi-file-earmark-text me-1"></i>보고서 아카이브
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="autocapture-tab" data-bs-toggle="tab" data-bs-target="#autocapture" type="button" role="tab">
            <i class="bi bi-camera-reels me-1"></i>자동 캡처 & 피드백
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="screenshots-tab" data-bs-toggle="tab" data-bs-target="#screenshots" type="button" role="tab">
            <i class="bi bi-camera me-1"></i>스크린샷 갤러리
        </button>
    </li>
</ul>

<div class="tab-content" id="qaTabContent">

<!-- ===== Tab 1: 전체 현황 ===== -->
<div class="tab-pane fade show active" id="overview" role="tabpanel">

    <!-- 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-success"><?php echo $done; ?></div>
                    <div class="text-muted">구현 완료</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-warning"><?php echo $partial; ?></div>
                    <div class="text-muted">부분 구현</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-danger"><?php echo $notdone; ?></div>
                    <div class="text-muted">미구현</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-primary"><?php echo $total; ?></div>
                    <div class="text-muted">총 항목수</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 진행률 바 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">전체 구현률</h6>
            <div class="progress" style="height: 28px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%">
                    <?php echo $pct; ?>% 완료
                </div>
                <?php if ($partial > 0): ?>
                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo round($partial/$total*100,1); ?>%">
                    <?php echo round($partial/$total*100,1); ?>% 부분
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 기능별 상태 테이블 -->
    <?php foreach ($features as $cat => $items): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-bold">
            <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($cat); ?>
            <span class="badge bg-secondary ms-2"><?php echo count($items); ?>개</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>기능명</th>
                            <th style="width:100px">상태</th>
                            <th style="width:120px">최종 업데이트</th>
                            <th style="width:100px">테스트</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="text-muted"><?php echo $i + 1; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>
                                <?php if ($item['status'] === 'done'): ?>
                                    <span class="badge bg-success">완료</span>
                                <?php elseif ($item['status'] === 'partial'): ?>
                                    <span class="badge bg-warning text-dark">부분</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">미구현</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo $item['date']; ?></td>
                            <td>
                                <?php if ($item['url'] !== '#'): ?>
                                <a href="<?php echo $base . $item['url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- ===== Tab 2: 테스트 시나리오 ===== -->
<div class="tab-pane fade" id="scenarios" role="tabpanel">

    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>관리자 로그인 정보:</strong> ID: <code>admin</code> / PW: <code>admin123</code>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        각 단계의 버튼을 클릭하면 해당 페이지가 새 탭에서 열립니다.
    </div>

    <?php
    $scenarios = [
        [
            'title' => '시나리오 1: 일반 사용자 플로우',
            'icon' => 'bi-person',
            'color' => 'primary',
            'steps' => [
                ['label' => '홈 열기', 'desc' => '검색바에 지역(강남) + 시술(성형외과) 선택 후 검색', 'url' => 'index.php'],
                ['label' => '병원 목록', 'desc' => '정렬 드롭다운에서 "평점순" 선택 확인', 'url' => 'hospitals.php'],
                ['label' => '병원 상세', 'desc' => '병원 카드 클릭 후 탭(소개/의사/이벤트/후기/위치) 각각 클릭', 'url' => 'detail.php?id=8093'],
                ['label' => '예약하기', 'desc' => '시술 선택 → 날짜 선택 → 제출', 'url' => 'booking.php?hospital_id=8093'],
                ['label' => '커뮤니티', 'desc' => '카테고리 탭(시술후기) 클릭 → 게시글 클릭 → 상세 보기', 'url' => 'community.php'],
                ['label' => '마이페이지', 'desc' => '로그인 확인 → 메뉴 13개 확인', 'url' => 'mypage.php'],
                ['label' => '이벤트', 'desc' => '이벤트 상세 → 응모', 'url' => 'events.php'],
                ['label' => '지도', 'desc' => '병원 위치 확인', 'url' => 'map.php'],
            ],
        ],
        [
            'title' => '시나리오 2: 병원 관리자 플로우',
            'icon' => 'bi-shield-lock',
            'color' => 'success',
            'steps' => [
                ['label' => '관리자 로그인', 'desc' => 'admin / admin123 로그인', 'url' => 'dashboard.php'],
                ['label' => '대시보드', 'desc' => '위젯 확인 (예약/후기/병원/광고 카운트)', 'url' => 'dashboard.php'],
                ['label' => '병원 관리', 'desc' => '병원 승인/거절 테스트', 'url' => 'admin_hospitals.php'],
                ['label' => '광고 관리', 'desc' => '광고 승인/거절 테스트', 'url' => 'admin_ads.php'],
                ['label' => '예약 관리', 'desc' => '예약 확인/승인 테스트', 'url' => 'admin_bookings.php'],
                ['label' => '후기 관리', 'desc' => '후기 승인/반려 테스트', 'url' => 'admin_reviews.php'],
                ['label' => '파트너 병원 편집', 'desc' => '병원 정보 수정 저장 테스트', 'url' => 'admin_hospitals.php'],
            ],
        ],
        [
            'title' => '시나리오 3: CPV 과금 시스템',
            'icon' => 'bi-eye',
            'color' => 'info',
            'steps' => [
                ['label' => 'CPV 설정', 'desc' => 'Master Switch, 기본단가, 가중치 확인', 'url' => 'admin_cpv_settings.php'],
                ['label' => 'CPV 대시보드', 'desc' => '통계 확인', 'url' => 'admin_cpv_dashboard.php'],
                ['label' => '지갑 관리', 'desc' => '병원별 잔액 확인', 'url' => 'admin_wallet.php'],
                ['label' => '단가 관리', 'desc' => '키워드/시술별 단가 확인', 'url' => 'admin_cpv_rates.php'],
                ['label' => '정산', 'desc' => '정산 내역 확인', 'url' => 'admin_settlement.php'],
                ['label' => '파트너 지갑', 'desc' => '병원측 잔액 확인', 'url' => 'partner_wallet.php'],
                ['label' => 'CPV 시스템 보고서', 'desc' => '전체 현황 리포트', 'url' => 'cpv_system_report.html'],
            ],
        ],
        [
            'title' => '시나리오 4: 하단 탭 관리',
            'icon' => 'bi-toggles',
            'color' => 'warning',
            'steps' => [
                ['label' => '탭 설정 열기', 'desc' => '5개 토글 확인', 'url' => 'admin_navigation.php'],
                ['label' => '이벤트 탭 OFF', 'desc' => '"이벤트" 탭 OFF → 저장', 'url' => 'admin_navigation.php'],
                ['label' => '홈 열기', 'desc' => '하단 4탭 확인 (이벤트 사라짐)', 'url' => 'index.php'],
                ['label' => '이벤트 탭 ON', 'desc' => '다시 "이벤트" 탭 ON → 저장 → 5탭 복원 확인', 'url' => 'admin_navigation.php'],
            ],
        ],
        [
            'title' => '시나리오 5: 검색 필터 + 정렬',
            'icon' => 'bi-funnel',
            'color' => 'danger',
            'steps' => [
                ['label' => '홈', 'desc' => '지역 "서초" + 시술 "피부과" 선택 → 검색', 'url' => 'index.php'],
                ['label' => '병원 목록 (후기순)', 'desc' => '정렬 "후기순" 선택', 'url' => 'hospitals.php?sort=reviews'],
                ['label' => '병원 목록 (최신순)', 'desc' => '정렬 "최신순" 선택', 'url' => 'hospitals.php?sort=latest'],
                ['label' => '병원 목록 (이름순)', 'desc' => '정렬 "이름순" 선택', 'url' => 'hospitals.php?sort=name'],
            ],
        ],
        [
            'title' => '시나리오 6: 커뮤니티 게시판',
            'icon' => 'bi-chat-square-text',
            'color' => 'secondary',
            'steps' => [
                ['label' => '커뮤니티', 'desc' => '전체 목록 확인', 'url' => 'community.php'],
                ['label' => '카테고리 필터', 'desc' => '카테고리 "질문" 클릭 → 필터 확인', 'url' => 'community.php?category=question'],
                ['label' => '글쓰기', 'desc' => '로그인 리다이렉트 확인', 'url' => 'community_write.php'],
                ['label' => '게시글 상세', 'desc' => '좋아요/공유 버튼 확인', 'url' => 'community.php'],
            ],
        ],
        [
            'title' => '시나리오 7: 보안/UGC',
            'icon' => 'bi-shield-check',
            'color' => 'dark',
            'steps' => [
                ['label' => '이용약관', 'desc' => '이용약관 페이지 확인', 'url' => 'terms_of_service.php'],
                ['label' => '개인정보처리방침', 'desc' => '개인정보처리방침 확인', 'url' => 'privacy_policy_patient_android.html'],
                ['label' => '커뮤니티 가이드라인', 'desc' => '가이드라인 페이지 확인', 'url' => 'community_guidelines.php'],
                ['label' => '계정삭제 안내', 'desc' => '계정 삭제 안내 확인', 'url' => 'account_deletion_patient.html'],
            ],
        ],
    ];

    foreach ($scenarios as $si => $scenario):
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent">
            <h6 class="mb-0 fw-bold">
                <i class="bi <?php echo $scenario['icon']; ?> me-2 text-<?php echo $scenario['color']; ?>"></i>
                <?php echo htmlspecialchars($scenario['title']); ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="list-group list-group-flush">
                <?php foreach ($scenario['steps'] as $step_i => $step): ?>
                <div class="list-group-item d-flex align-items-center px-0 border-0">
                    <span class="badge bg-<?php echo $scenario['color']; ?> rounded-circle me-3" style="width:28px;height:28px;line-height:28px;text-align:center;font-size:13px;">
                        <?php echo $step_i + 1; ?>
                    </span>
                    <div class="flex-grow-1">
                        <span class="fw-semibold"><?php echo htmlspecialchars($step['desc']); ?></span>
                    </div>
                    <a href="<?php echo $base . $step['url']; ?>" target="_blank" class="btn btn-sm btn-outline-<?php echo $scenario['color']; ?> ms-2" style="white-space:nowrap;">
                        <i class="bi bi-box-arrow-up-right me-1"></i><?php echo htmlspecialchars($step['label']); ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- ===== Tab 3: 버그 수정 이력 ===== -->
<div class="tab-pane fade" id="bugfix" role="tabpanel">

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-bug me-2"></i>버그 수정 이력</h6>
            <span class="badge bg-primary"><?php echo count($bugfixes); ?>건</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>제목</th>
                            <th style="width:100px">상태</th>
                            <th style="width:100px">구분</th>
                            <th style="width:120px">수정일</th>
                            <th style="width:150px">파일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bugfixes)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">수정 이력이 없습니다.</td></tr>
                        <?php else: ?>
                        <?php foreach ($bugfixes as $i => $bug): ?>
                        <tr>
                            <td class="text-muted"><?php echo $i + 1; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($bug['title'] ?? $bug['description'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $st = $bug['status'] ?? 'fixed';
                                if ($st === 'fixed' || $st === 'completed') echo '<span class="badge bg-success">완료</span>';
                                elseif ($st === 'in_progress') echo '<span class="badge bg-warning text-dark">진행중</span>';
                                else echo '<span class="badge bg-secondary">' . htmlspecialchars($st) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                $phase = $bug['category'] ?? '-';
                                echo '<span class="badge bg-outline-secondary border">' . htmlspecialchars($phase) . '</span>';
                                ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($bug['fix_date'] ?? $bug['created_at'] ?? '-'); ?></td>
                            <td class="small text-truncate" style="max-width:150px;" title="<?php echo htmlspecialchars($bug['target_page'] ?? '-'); ?>">
                                <?php echo htmlspecialchars($bug['target_page'] ?? '-'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ===== Tab 4: 보고서 아카이브 ===== -->
<div class="tab-pane fade" id="reports" role="tabpanel">

    <?php
    // 카테고리별 그룹
    $grouped = [];
    foreach ($all_reports as $rp) {
        $cat = categorize_report($rp['file']);
        $grouped[$cat][] = $rp;
    }
    $cat_order = ['QA 보고서', '프로젝트 현황', 'CPV 시스템', '법적/스토어', '결제', '기타'];
    $cat_colors = [
        'QA 보고서' => 'primary',
        '프로젝트 현황' => 'success',
        'CPV 시스템' => 'info',
        '법적/스토어' => 'warning',
        '결제' => 'danger',
        '기타' => 'secondary',
    ];
    ?>

    <div class="row g-2 mb-4">
        <?php foreach ($cat_order as $cat): ?>
        <?php if (isset($grouped[$cat])): ?>
        <div class="col-auto">
            <span class="badge bg-<?php echo $cat_colors[$cat] ?? 'secondary'; ?> fs-6">
                <?php echo $cat; ?> (<?php echo count($grouped[$cat]); ?>)
            </span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="col-auto">
            <span class="badge bg-dark fs-6">전체 <?php echo count($all_reports); ?>개</span>
        </div>
    </div>

    <?php foreach ($cat_order as $cat): ?>
    <?php if (!isset($grouped[$cat])) continue; ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-bold">
            <span class="badge bg-<?php echo $cat_colors[$cat] ?? 'secondary'; ?> me-2"><?php echo $cat; ?></span>
            <?php echo count($grouped[$cat]); ?>개
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>파일명</th>
                            <th style="width:100px">날짜</th>
                            <th style="width:100px">크기</th>
                            <th style="width:80px">열기</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped[$cat] as $rp): ?>
                        <tr>
                            <td class="fw-semibold small"><?php echo htmlspecialchars($rp['file']); ?></td>
                            <td class="text-muted small"><?php echo $rp['date']; ?></td>
                            <td class="text-muted small"><?php echo round($rp['size'] / 1024); ?> KB</td>
                            <td>
                                <a href="<?php echo htmlspecialchars($rp['url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- ===== Tab 5: 자동 캡처 & 피드백 ===== -->
<div class="tab-pane fade" id="autocapture" role="tabpanel">

    <!-- 상단 컨트롤 바 -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <button class="btn btn-primary" id="btnCaptureAll" onclick="captureAll()">
            <i class="bi bi-arrow-repeat me-1"></i>전체 캡처 실행
        </button>
        <div id="captureSummary" class="d-flex gap-2 ms-auto">
            <span class="badge bg-success fs-6" id="sumPass">PASS 0</span>
            <span class="badge bg-danger fs-6" id="sumFail">FAIL 0</span>
            <span class="badge bg-warning text-dark fs-6" id="sumNeedFix">수정필요 0</span>
            <span class="badge bg-secondary fs-6" id="sumChecking">확인중 0</span>
        </div>
    </div>

    <!-- 캡처 진행 표시 -->
    <div id="captureProgress" class="mb-4" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <strong id="captureProgressText">캡처 진행 중...</strong>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="captureProgressBar" role="progressbar" style="width:0%"></div>
                </div>
                <small class="text-muted mt-1 d-block" id="captureProgressDetail">준비 중...</small>
            </div>
        </div>
    </div>

    <!-- 검색 + 필터 -->
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="captureSearch" placeholder="페이지 검색..." oninput="loadCaptures()">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="captureStatusFilter" onchange="loadCaptures()">
                <option value="">전체 상태</option>
                <option value="pass">정상 (PASS)</option>
                <option value="fail">오류 (FAIL)</option>
                <option value="need_fix">수정필요</option>
                <option value="checking">확인중</option>
            </select>
        </div>
        <div class="col-md-3 text-end">
            <button class="btn btn-outline-secondary btn-sm" onclick="loadCaptures()">
                <i class="bi bi-arrow-clockwise me-1"></i>새로고침
            </button>
        </div>
    </div>

    <!-- 캡처 카드 목록 -->
    <div id="captureCards">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            로딩 중...
        </div>
    </div>

    <!-- 새 페이지 추가 -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent fw-bold">
            <i class="bi bi-plus-circle me-1"></i>새 페이지 추가
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="newPageKey" placeholder="page_key (예: login)">
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="newPageName" placeholder="페이지 이름 (예: 로그인)">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="newPageUrl" placeholder="URL (https://...)">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100" onclick="addPage()">
                        <i class="bi bi-plus-lg me-1"></i>추가
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ===== Tab 6: 스크린샷 갤러리 ===== -->
<div class="tab-pane fade" id="screenshots" role="tabpanel">

    <?php if (empty($screenshots)): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle me-2"></i>스크린샷이 없습니다.
    </div>
    <?php else: ?>
    <p class="text-muted mb-3">총 <?php echo count($screenshots); ?>개 스크린샷 | 클릭하면 원본 크기로 열립니다</p>

    <?php
    $ss_grouped = [];
    foreach ($screenshots as $ss) {
        $ss_grouped[$ss['dir']][] = $ss;
    }
    ?>

    <?php foreach ($ss_grouped as $dir => $imgs): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-bold">
            <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($dir); ?>
            <span class="badge bg-secondary ms-2"><?php echo count($imgs); ?>개</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($imgs as $img): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="<?php echo htmlspecialchars($img['url']); ?>" target="_blank" class="d-block text-decoration-none">
                        <div class="card border h-100">
                            <img src="<?php echo htmlspecialchars($img['url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($img['name']); ?>" style="height:150px;object-fit:cover;" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22><rect fill=%22%23ddd%22 width=%22200%22 height=%22150%22/><text fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22>No Image</text></svg>'">
                            <div class="card-body p-2">
                                <small class="text-truncate d-block" style="max-width:100%"><?php echo htmlspecialchars($img['name']); ?></small>
                                <small class="text-muted"><?php echo $img['date']; ?></small>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

</div><!-- /tab-content -->

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body text-center p-0">
                <img id="lightboxImage" src="" class="img-fluid rounded shadow" style="max-height:90vh;cursor:pointer;" onclick="document.getElementById('lightboxModal').querySelector('.btn-close').click()">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
        </div>
    </div>
</div>

<script>
const QA_API = '<?php echo $base; ?>api_qa_captures.php';
const BASE_URL = '<?php echo $base; ?>';
const NO_IMG = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='200'><rect fill='%23e9ecef' width='300' height='200'/><text fill='%236c757d' x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='14'>No Capture</text></svg>";

// 상태 배지
function statusBadge(status) {
    const map = {
        'pass':     '<span class="badge bg-success">PASS</span>',
        'fail':     '<span class="badge bg-danger">FAIL</span>',
        'need_fix': '<span class="badge bg-warning text-dark">수정필요</span>',
        'checking': '<span class="badge bg-secondary">확인중</span>'
    };
    return map[status] || map['checking'];
}

// 상태 한글
function statusLabel(status) {
    const map = { 'pass': '정상', 'fail': '오류', 'need_fix': '수정필요', 'checking': '확인중' };
    return map[status] || '확인중';
}

// 라이트박스 열기
function openLightbox(src) {
    document.getElementById('lightboxImage').src = src;
    new bootstrap.Modal(document.getElementById('lightboxModal')).show();
}

// 캡처 목록 로드
async function loadCaptures() {
    const search = document.getElementById('captureSearch').value;
    const status = document.getElementById('captureStatusFilter').value;

    let url = QA_API + '?action=list';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (status) url += '&status=' + encodeURIComponent(status);

    try {
        const resp = await fetch(url);
        const data = await resp.json();
        if (!data.success) {
            document.getElementById('captureCards').innerHTML = '<div class="alert alert-danger">로드 실패</div>';
            return;
        }

        // 요약 업데이트
        const s = data.summary || {};
        document.getElementById('sumPass').textContent = 'PASS ' + (s.pass || 0);
        document.getElementById('sumFail').textContent = 'FAIL ' + (s.fail || 0);
        document.getElementById('sumNeedFix').textContent = '수정필요 ' + (s.need_fix || 0);
        document.getElementById('sumChecking').textContent = '확인중 ' + (s.checking || 0);

        // 카드 렌더링
        const items = data.data || [];
        if (items.length === 0) {
            document.getElementById('captureCards').innerHTML = '<div class="alert alert-secondary">등록된 페이지가 없습니다.</div>';
            return;
        }

        let html = '';
        items.forEach(item => {
            const pcSrc = item.screenshot_pc ? (BASE_URL + item.screenshot_pc) : NO_IMG;
            const mobSrc = item.screenshot_mob ? (BASE_URL + item.screenshot_mob) : NO_IMG;
            const capturedAt = item.captured_at ? item.captured_at.substring(0, 16) : '미캡처';
            const hasPc = !!item.screenshot_pc;
            const hasMob = !!item.screenshot_mob;

            html += `
            <div class="card border-0 shadow-sm mb-3" id="card-${item.page_key}">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-file-earmark me-1"></i>
                        <strong>${escHtml(item.page_name)}</strong>
                        <a href="${escHtml(item.page_url)}" target="_blank" class="text-muted ms-2 small">
                            <i class="bi bi-box-arrow-up-right"></i> ${escHtml(item.page_url.replace('https://hms.seoulbeauty.app/hairwang/gangnamunni/', ''))}
                        </a>
                    </div>
                    <div>${statusBadge(item.status)}</div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <div class="text-muted small mb-1"><i class="bi bi-display me-1"></i>PC (1280x800)</div>
                            <img src="${pcSrc}" class="img-fluid rounded border" alt="PC" style="height:180px;width:100%;object-fit:cover;cursor:${hasPc ? 'pointer' : 'default'}"
                                ${hasPc ? `onclick="openLightbox('${pcSrc.split('?')[0]}?t=${Date.now()}')"` : ''}
                                onerror="this.src='${NO_IMG}'">
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small mb-1"><i class="bi bi-phone me-1"></i>모바일 (375x667)</div>
                            <img src="${mobSrc}" class="img-fluid rounded border" alt="Mobile" style="height:180px;width:100%;object-fit:cover;cursor:${hasMob ? 'pointer' : 'default'}"
                                ${hasMob ? `onclick="openLightbox('${mobSrc.split('?')[0]}?t=${Date.now()}')"` : ''}
                                onerror="this.src='${NO_IMG}'">
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small mb-2"><i class="bi bi-clock me-1"></i>마지막 캡처: ${capturedAt}</div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold mb-1">메모</label>
                                <textarea class="form-control form-control-sm" rows="3" id="memo-${item.page_key}" placeholder="피드백/수정요청 입력...">${escHtml(item.memo || '')}</textarea>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="role-${item.page_key}">
                                        <option value="dev" ${item.memo_role === 'dev' ? 'selected' : ''}>개발</option>
                                        <option value="client" ${item.memo_role === 'client' ? 'selected' : ''}>클라이언트</option>
                                        <option value="qa" ${item.memo_role === 'qa' ? 'selected' : ''}>QA</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="status-${item.page_key}">
                                        <option value="pass" ${item.status === 'pass' ? 'selected' : ''}>정상</option>
                                        <option value="need_fix" ${item.status === 'need_fix' ? 'selected' : ''}>수정필요</option>
                                        <option value="checking" ${item.status === 'checking' ? 'selected' : ''}>확인중</option>
                                        <option value="fail" ${item.status === 'fail' ? 'selected' : ''}>오류</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary flex-fill" onclick="saveCapture('${item.page_key}')">
                                    <i class="bi bi-floppy me-1"></i>저장
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="recapture('${item.page_key}')">
                                    <i class="bi bi-camera me-1"></i>재캡처
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        });

        document.getElementById('captureCards').innerHTML = html;

    } catch (e) {
        document.getElementById('captureCards').innerHTML = '<div class="alert alert-danger">API 통신 오류: ' + e.message + '</div>';
    }
}

// 상태/메모 저장
async function saveCapture(pageKey) {
    const memo = document.getElementById('memo-' + pageKey).value;
    const role = document.getElementById('role-' + pageKey).value;
    const status = document.getElementById('status-' + pageKey).value;

    try {
        const resp = await fetch(QA_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                page_key: pageKey,
                status: status,
                memo: memo,
                memo_author: role === 'client' ? '클라이언트' : (role === 'qa' ? 'QA' : '개발자'),
                memo_role: role
            })
        });
        const data = await resp.json();
        if (data.success) {
            showToast('저장 완료', 'success');
            loadCaptures();
        } else {
            showToast('저장 실패: ' + data.message, 'danger');
        }
    } catch (e) {
        showToast('통신 오류: ' + e.message, 'danger');
    }
}

// 단일 재캡처
async function recapture(pageKey) {
    const btn = event.target.closest('button');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 캡처중...';
    btn.disabled = true;

    try {
        const resp = await fetch(QA_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'capture_one', page_key: pageKey })
        });
        const data = await resp.json();
        if (data.success) {
            showToast(pageKey + ' 캡처 완료', 'success');
            loadCaptures();
        } else {
            showToast('캡처 실패: ' + (data.log || data.message || ''), 'danger');
        }
    } catch (e) {
        showToast('통신 오류: ' + e.message, 'danger');
    } finally {
        btn.innerHTML = origHtml;
        btn.disabled = false;
    }
}

// 전체 캡처
async function captureAll() {
    const btn = document.getElementById('btnCaptureAll');
    const progress = document.getElementById('captureProgress');
    const progressBar = document.getElementById('captureProgressBar');
    const progressText = document.getElementById('captureProgressText');
    const progressDetail = document.getElementById('captureProgressDetail');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>캡처 중...';
    progress.style.display = 'block';
    progressBar.style.width = '10%';
    progressText.textContent = '전체 페이지 캡처 진행 중... (2~5분 소요)';
    progressDetail.textContent = 'Puppeteer 실행 중...';

    // 진행 애니메이션
    let pct = 10;
    const interval = setInterval(() => {
        if (pct < 90) {
            pct += 2;
            progressBar.style.width = pct + '%';
        }
    }, 3000);

    try {
        const resp = await fetch(QA_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'capture_all' })
        });
        const data = await resp.json();

        clearInterval(interval);
        progressBar.style.width = '100%';

        if (data.success) {
            progressText.textContent = '캡처 완료!';
            progressDetail.textContent = `총 ${data.total}개 중 ${data.captured}개 캡처 성공`;
            showToast(`전체 캡처 완료: ${data.captured}/${data.total}`, 'success');
            loadCaptures();
        } else {
            progressText.textContent = '캡처 실패';
            progressDetail.textContent = data.message || '알 수 없는 오류';
            showToast('캡처 실패: ' + data.message, 'danger');
        }
    } catch (e) {
        clearInterval(interval);
        progressText.textContent = '통신 오류';
        progressDetail.textContent = e.message;
        showToast('통신 오류: ' + e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>전체 캡처 실행';
        setTimeout(() => { progress.style.display = 'none'; }, 5000);
    }
}

// 새 페이지 추가
async function addPage() {
    const pageKey = document.getElementById('newPageKey').value.trim();
    const pageName = document.getElementById('newPageName').value.trim();
    const pageUrl = document.getElementById('newPageUrl').value.trim();

    if (!pageKey || !pageName || !pageUrl) {
        showToast('모든 필드를 입력해주세요', 'warning');
        return;
    }

    try {
        const resp = await fetch(QA_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_page', page_key: pageKey, page_name: pageName, page_url: pageUrl })
        });
        const data = await resp.json();
        if (data.success) {
            showToast('페이지 추가 완료: ' + pageName, 'success');
            document.getElementById('newPageKey').value = '';
            document.getElementById('newPageName').value = '';
            document.getElementById('newPageUrl').value = '';
            loadCaptures();
        } else {
            showToast('추가 실패: ' + data.message, 'danger');
        }
    } catch (e) {
        showToast('통신 오류: ' + e.message, 'danger');
    }
}

// HTML 이스케이프
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// 토스트 알림
function showToast(msg, type) {
    const id = 'toast-' + Date.now();
    const html = `<div id="${id}" class="toast align-items-center text-bg-${type} border-0 show" role="alert" style="position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;">
        <div class="d-flex">
            <div class="toast-body">${escHtml(msg)}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    setTimeout(() => {
        const el = document.getElementById(id);
        if (el) el.remove();
    }, 4000);
}

// URL 해시로 탭 상태 유지
document.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash) {
        var tab = document.querySelector('[data-bs-target="' + hash + '"]');
        if (tab) new bootstrap.Tab(tab).show();
    }
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(el) {
        el.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.getAttribute('data-bs-target');
            // 자동 캡처 탭 진입 시 목록 로드
            if (e.target.getAttribute('data-bs-target') === '#autocapture') {
                loadCaptures();
            }
        });
    });

    // 초기 해시가 #autocapture면 로드
    if (hash === '#autocapture') {
        loadCaptures();
    }

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>

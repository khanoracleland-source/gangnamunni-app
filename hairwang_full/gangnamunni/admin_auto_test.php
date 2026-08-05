<?php
/**
 * admin_auto_test.php - 자동 테스트 관리 페이지 v3
 *
 * 탭 기반 시스템별 테스트 실행 + 아코디언 상세 결과 + 큰 스크린샷
 * - 탭: 전체 / HMS 관리자 / 서울미녀 관리자 / 프론트엔드
 * - 각 탭에 페이지 목록 표시
 * - 스크린샷: 풀 너비 + 클릭 시 모달 확대
 * - 수정 프롬프트 입력/저장/DOCX 내보내기
 *
 * @since 2026-02-14
 * @version 3.0.0
 */

session_start();
$admin_page_title = '자동 테스트';
$admin_current = 'auto_test';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_admin_login();
require_permission('auto_test');
require_once __DIR__ . '/includes/auto_test_security.php';
require_auto_test_enabled();
if (empty($_SESSION['admin_auto_test_csrf'])) {
    $_SESSION['admin_auto_test_csrf'] = bin2hex(random_bytes(32));
}
$admin_auto_test_csrf = $_SESSION['admin_auto_test_csrf'];
issue_auto_test_api_session('v1', $admin_auto_test_csrf);
require_once __DIR__ . '/includes/admin_header.php';

?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small"><strong>레거시 페이지:</strong> 이 화면은 기존 링크 호환용으로만 유지됩니다. 신규 테스트는 <a href="admin_auto_test_v2.php" class="alert-link">자동 테스트 v2</a>를 사용하세요.</div>
</div>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">자동 테스트</h4>
        <small class="text-muted">시스템별 페이지 테스트 + 스크린샷 + 에러 감지 + 보고서 생성</small>
    </div>
    <div>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadHistory()">
            <i class="bi bi-arrow-clockwise"></i> 새로고침
        </button>
    </div>
</div>

<!-- 요약 카드 -->
<div class="row g-3 mb-4" id="summaryCards">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small">총 페이지</div>
                <div class="fs-2 fw-bold" id="statTotal">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-success">
            <div class="card-body py-3">
                <div class="text-success small">PASS</div>
                <div class="fs-2 fw-bold text-success" id="statPass">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-warning">
            <div class="card-body py-3">
                <div class="text-warning small">WARN</div>
                <div class="fs-2 fw-bold text-warning" id="statWarn">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-danger">
            <div class="card-body py-3">
                <div class="text-danger small">FAIL</div>
                <div class="fs-2 fw-bold text-danger" id="statFail">-</div>
            </div>
        </div>
    </div>
</div>

<!-- 테스트 실행 패널 (탭 기반) -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-play-circle"></i> 테스트 실행</span>
        <span class="badge bg-secondary" id="runStatus">대기</span>
    </div>
    <div class="card-body p-0">
        <!-- 시스템 탭 -->
        <ul class="nav nav-tabs px-3 pt-2" id="systemTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAll" data-system=""
                        type="button" role="tab">
                    <i class="bi bi-grid-3x3-gap-fill"></i> 전체
                    <span class="badge bg-secondary ms-1" id="tabAllCount">-</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHms" data-system="hms_admin"
                        type="button" role="tab">
                    <i class="bi bi-hospital"></i> HMS 관리자
                    <span class="badge bg-secondary ms-1" id="tabHmsCount">-</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAdmin" data-system="gangnamunni_admin"
                        type="button" role="tab">
                    <i class="bi bi-gear-wide-connected"></i> 서울미녀 관리자
                    <span class="badge bg-secondary ms-1" id="tabAdminCount">-</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFrontend" data-system="gangnamunni_frontend"
                        type="button" role="tab">
                    <i class="bi bi-phone"></i> 프론트엔드
                    <span class="badge bg-secondary ms-1" id="tabFrontendCount">-</span>
                </button>
            </li>
        </ul>

        <!-- 탭 컨텐츠 -->
        <div class="tab-content p-3" id="systemTabContent">
            <div class="tab-pane fade show active" id="tabAll" role="tabpanel">
                <div id="tabAllContent" class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm"></div> 설정 로딩 중...
                </div>
            </div>
            <div class="tab-pane fade" id="tabHms" role="tabpanel">
                <div id="tabHmsContent" class="text-center text-muted py-3">로딩 중...</div>
            </div>
            <div class="tab-pane fade" id="tabAdmin" role="tabpanel">
                <div id="tabAdminContent" class="text-center text-muted py-3">로딩 중...</div>
            </div>
            <div class="tab-pane fade" id="tabFrontend" role="tabpanel">
                <div id="tabFrontendContent" class="text-center text-muted py-3">로딩 중...</div>
            </div>
        </div>

        <!-- 공통: 딜레이 + 실행 버튼 + 진행바 + 로그 -->
        <div class="border-top p-3">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label small mb-0">딜레이</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="range" class="form-range" id="delaySlider" min="0" max="15000" step="1000" value="0" style="width:120px"
                               oninput="document.getElementById('delayLabel').textContent = (this.value/1000) + '초'">
                        <span class="badge bg-secondary" id="delayLabel" style="min-width:36px">0초</span>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" id="btnRun" onclick="startTest()">
                        <i class="bi bi-play-fill"></i> <span id="btnRunLabel">전체 테스트 실행</span>
                    </button>
                    <button class="btn btn-outline-danger btn-sm d-none" id="btnStop" onclick="stopTest()">
                        <i class="bi bi-stop-fill"></i> 중지
                    </button>
                </div>
                <div class="col-auto">
                    <span class="text-muted small" id="runTime"></span>
                </div>
            </div>

            <div class="progress mb-3" style="height: 24px; display:none" id="progressBar">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                     style="width: 0%" id="progressFill">0%</div>
            </div>

            <div id="logArea" style="display:none">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">실행 로그</small>
                    <button class="btn btn-link btn-sm p-0" onclick="document.getElementById('logContent').innerHTML=''">지우기</button>
                </div>
                <div id="logContent"
                     style="background:#1e1e1e; color:#d4d4d4; font-family:'Consolas','Courier New',monospace;
                            font-size:12px; padding:12px; border-radius:6px; max-height:300px;
                            overflow-y:auto; white-space:pre-wrap; line-height:1.6">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 상세 결과 (아코디언 뷰) -->
<div id="detailSection" style="display:none" class="mb-4">
    <div class="card border-primary">
        <div class="card-header bg-primary bg-gradient text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clipboard-data"></i> <span id="detailTitle">상세 결과</span></span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-warning" onclick="saveNotes()" id="btnSaveNotes">
                    <i class="bi bi-save"></i> 저장
                </button>
                <button class="btn btn-sm btn-success" onclick="exportDocx()" id="btnExportDocx">
                    <i class="bi bi-file-earmark-word"></i> 문서 저장
                </button>
                <button class="btn btn-sm btn-outline-light" onclick="closeDetail()" title="닫기">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <div id="detailSummary" class="mb-3"></div>
            <div class="accordion" id="systemAccordion"></div>
        </div>
    </div>
</div>

<!-- 스크린샷 모달 -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="ssModalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-1" style="background:var(--bs-tertiary-bg)">
                <img id="ssModalImg" class="img-fluid" style="max-height:85vh">
            </div>
        </div>
    </div>
</div>

<!-- 테스트 이력 -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> 테스트 이력</span>
        <div class="d-flex gap-2" id="historyActions">
            <button class="btn btn-outline-danger btn-sm" onclick="deleteSelected()" id="btnDeleteSelected" style="display:none">
                <i class="bi bi-trash"></i> 선택 삭제 (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="deleteAll()" id="btnDeleteAll" style="display:none">
                <i class="bi bi-trash-fill"></i> 전체 삭제
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px" class="text-center">
                            <input type="checkbox" class="form-check-input" id="checkAll"
                                   onchange="toggleCheckAll(this.checked)" title="전체 선택">
                        </th>
                        <th style="width:160px">날짜</th>
                        <th style="width:80px" class="text-center">총 페이지</th>
                        <th style="width:70px" class="text-center text-success">PASS</th>
                        <th style="width:70px" class="text-center text-warning">WARN</th>
                        <th style="width:70px" class="text-center text-danger">FAIL</th>
                        <th style="width:80px" class="text-center">소요시간</th>
                        <th style="width:70px" class="text-center">스크린샷</th>
                        <th>다운로드</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr><td colspan="10" class="text-center text-muted py-3">로딩 중...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 사용 가이드 -->
<div class="card mb-4">
    <div class="card-header" data-bs-toggle="collapse" data-bs-target="#guideCollapse"
         style="cursor:pointer" role="button">
        <i class="bi bi-book"></i> 사용 가이드
        <i class="bi bi-chevron-down float-end"></i>
    </div>
    <div class="collapse" id="guideCollapse">
        <div class="card-body">
            <h6>개요</h6>
            <p>Playwright 기반 자동 테스트 시스템입니다. 3개 시스템의 페이지를 자동으로 순회하며:</p>
            <ul>
                <li>로그인 자동 처리 (HMS: admin@hms.com, 서울미녀: admin)</li>
                <li>전체 페이지 스크린샷 캡처 (1920x1080)</li>
                <li>3레이어 에러 감지: HTTP(4xx/5xx), PHP에러, JavaScript에러</li>
                <li>Word(DOCX) 보고서 자동 생성 + 수정 프롬프트 포함</li>
            </ul>

            <h6>사용법</h6>
            <ol>
                <li><strong>탭 선택</strong>: 전체 / HMS 관리자 / 서울미녀 관리자 / 프론트엔드</li>
                <li><strong>테스트 실행</strong>: 선택된 탭의 시스템에 대해 테스트 실행</li>
                <li><strong>결과 확인</strong>: 이력에서 날짜 클릭 → 상세 결과 (아코디언)</li>
                <li><strong>스크린샷</strong>: 이미지 클릭 → 모달로 원본 크기 확인</li>
                <li><strong>수정 프롬프트</strong>: 각 페이지 아래 수정 사항 입력 → 저장/문서 내보내기</li>
            </ol>

            <h6>판정 기준</h6>
            <ul>
                <li><span class="badge bg-success">PASS</span> 에러 없음</li>
                <li><span class="badge bg-warning text-dark">WARN</span> 서브리소스 에러, JS 경고, 엘리먼트 미발견</li>
                <li><span class="badge bg-danger">FAIL</span> PHP Fatal, 메인 HTTP 에러, JS 치명적 에러</li>
            </ul>
        </div>
    </div>
</div>

<style>
/* 탭 스타일 */
#systemTabs .nav-link {
    font-size: 13px;
    padding: 8px 14px;
}
#systemTabs .nav-link.active {
    font-weight: 600;
}
/* 페이지 목록 테이블 */
.page-list-table {
    font-size: 13px;
}
.page-list-table td, .page-list-table th {
    padding: 6px 10px;
    vertical-align: middle;
}
.page-list-table .check-icon {
    font-size: 11px;
    padding: 1px 5px;
    margin: 1px;
}
/* 페이지 카드 클릭 헤더 */
.page-card-header {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s;
}
.page-card-header:hover {
    background-color: rgba(0,0,0,0.04) !important;
}
/* 스크린샷 - 풀 너비 */
.ss-thumb {
    max-width: 100%;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    transition: transform 0.15s, box-shadow 0.15s;
    cursor: pointer;
}
.ss-thumb:hover {
    transform: scale(1.01);
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}
/* 분석 배지 */
.analysis-badge {
    font-size: 11px;
    padding: 2px 6px;
    margin: 1px;
}
/* 수정 프롬프트 textarea */
.note-input {
    border: 1px solid #ffc107;
    background: var(--bs-body-bg)df5;
    transition: border-color 0.2s;
}
.note-input:focus {
    border-color: #fd7e14;
    background: var(--bs-body-bg);
    box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.25);
}
.note-input.has-content {
    border-color: #fd7e14;
    background: var(--bs-body-bg)8e1;
}
/* 에러 박스 */
.error-detail-box {
    background: var(--bs-body-bg)5f5;
    border-left: 3px solid #dc3545;
    padding: 8px 12px;
    font-size: 12px;
    border-radius: 0 4px 4px 0;
}
/* 저장 애니메이션 */
@keyframes saveFlash {
    0% { background-color: #198754; }
    100% { background-color: #ffc107; }
}
.save-flash {
    animation: saveFlash 0.6s ease;
}
/* 다크모드 오버라이드 */
[data-bs-theme="dark"] .note-input {
    border-color: #665500;
    background: #2d2a1f;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .note-input:focus {
    background: #2b3035;
}
[data-bs-theme="dark"] .note-input.has-content {
    background: #332d1a;
}
[data-bs-theme="dark"] .error-detail-box {
    background: #2d1f1f;
    color: #f8d7da;
}
</style>

<script>
/**
 * 자동 테스트 관리 v3
 * - 탭 기반 시스템 선택 + 페이지 목록
 * - 큰 스크린샷 + 모달 뷰어
 * - 아코디언 상세 + 수정 프롬프트
 */

const API_BASE = '/auto-manual-api/api/auto-test';
const AUTO_TEST_CSRF = <?= json_encode($admin_auto_test_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('X-CSRF-Token', AUTO_TEST_CSRF);
    return window.fetch(url, { ...options, headers, credentials: 'same-origin' });
}

let currentRunId = null;
let eventSource = null;
let runTimer = null;
let runStartTime = null;
let currentDetailDir = null;
let testConfig = null; // 테스트 설정 캐시

// ============================================================
// 초기 로드
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    loadConfig();
    loadHistory();
    checkRunningTest();

    // 탭 전환 시 버튼 라벨 업데이트
    document.querySelectorAll('#systemTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => {
            updateRunButtonLabel();
        });
    });
});

// ============================================================
// 테스트 설정 로드 + 탭 채우기
// ============================================================

async function loadConfig() {
    try {
        const res = await apiFetch(`${API_BASE}/config`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        testConfig = await res.json();
        populateTabs(testConfig);
    } catch (e) {
        document.getElementById('tabAllContent').innerHTML =
            `<div class="alert alert-danger mb-0">설정 로드 실패: ${escapeHtml(e.message)}</div>`;
    }
}

function populateTabs(config) {
    const systems = config.systems || [];
    let totalPages = 0;

    // 전체 탭 - 시스템 요약 카드
    let allHtml = '<div class="row g-3">';
    systems.forEach((sys, i) => {
        const pageCount = sys.pages ? sys.pages.length : 0;
        totalPages += pageCount;
        const loginInfo = sys.credentials
            ? `${sys.credentials.username}`
            : '불필요';
        const colors = ['primary', 'success', 'info'];
        const icons = ['bi-hospital', 'bi-gear-wide-connected', 'bi-phone'];

        allHtml += `
            <div class="col-md-4">
                <div class="card border-${colors[i % 3]} h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi ${icons[i % 3]} text-${colors[i % 3]}"></i>
                            ${escapeHtml(sys.name)}
                        </h6>
                        <p class="text-muted small mb-2">${escapeHtml(sys.description || '')}</p>
                        <div class="d-flex justify-content-between small">
                            <span><strong>${pageCount}</strong>개 페이지</span>
                            <span class="text-muted">로그인: ${escapeHtml(loginInfo)}</span>
                        </div>
                    </div>
                </div>
            </div>`;
    });
    allHtml += '</div>';

    // 전체 탭 하단에 페이지 수 표시
    allHtml += `<div class="text-muted small mt-2 text-end">총 ${totalPages}개 페이지 + 로그인 캡처</div>`;
    document.getElementById('tabAllContent').innerHTML = allHtml;
    document.getElementById('tabAllCount').textContent = totalPages;

    // 시스템별 탭 - 페이지 목록 테이블
    const tabMap = {
        'hms_admin': { content: 'tabHmsContent', count: 'tabHmsCount' },
        'gangnamunni_admin': { content: 'tabAdminContent', count: 'tabAdminCount' },
        'gangnamunni_frontend': { content: 'tabFrontendContent', count: 'tabFrontendCount' }
    };

    systems.forEach(sys => {
        const target = tabMap[sys.id];
        if (!target) return;

        const pages = sys.pages || [];
        document.getElementById(target.count).textContent = pages.length;

        let html = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <p class="text-muted small mb-0">
                    <strong>${escapeHtml(sys.name)}</strong> - ${escapeHtml(sys.description || '')}
                    ${sys.credentials ? `<span class="ms-2 badge bg-light text-dark border">로그인: ${escapeHtml(sys.credentials.username)}</span>` : ''}
                </p>
                <div class="d-flex gap-1">
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" onclick="togglePageChecks('${sys.id}', true)" style="font-size:12px">
                        <i class="bi bi-check-all"></i> 전체 선택
                    </button>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="togglePageChecks('${sys.id}', false)" style="font-size:12px">
                        <i class="bi bi-x-lg"></i> 전체 해제
                    </button>
                    <span class="badge bg-primary ms-1 page-selected-count" data-system="${sys.id}" style="font-size:11px">
                        ${pages.length}/${pages.length}
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered page-list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px" class="text-center">
                                <input type="checkbox" class="form-check-input" checked
                                       onchange="togglePageChecks('${sys.id}', this.checked)"
                                       data-system-all="${sys.id}" title="전체 선택">
                            </th>
                            <th style="width:30px" class="text-center">#</th>
                            <th style="width:170px">페이지</th>
                            <th>URL</th>
                            <th style="width:230px">설명</th>
                            <th style="width:110px">체크 항목</th>
                        </tr>
                    </thead>
                    <tbody>`;

        pages.forEach((page, i) => {
            const checks = (page.checks || []).map(c => {
                if (c.startsWith('elementExists:')) return `<span class="badge bg-primary check-icon">DOM</span>`;
                if (c === 'noPhpError') return `<span class="badge bg-warning text-dark check-icon">PHP</span>`;
                if (c === 'noJsError') return `<span class="badge bg-info check-icon">JS</span>`;
                return `<span class="badge bg-secondary check-icon">${escapeHtml(c.substring(0,10))}</span>`;
            }).join(' ');

            html += `
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input page-check" checked
                               data-system="${sys.id}" data-url="${escapeHtml(page.url)}"
                               onchange="updatePageSelectedCount('${sys.id}')">
                    </td>
                    <td class="text-center text-muted">${i + 1}</td>
                    <td class="fw-bold">${escapeHtml(page.name)}</td>
                    <td><code class="small">${escapeHtml(page.url)}</code></td>
                    <td class="text-muted small">${escapeHtml(page.description || '')}</td>
                    <td>${checks}</td>
                </tr>`;
        });

        html += '</tbody></table></div>';
        document.getElementById(target.content).innerHTML = html;
    });

    updateRunButtonLabel();
}

function getActiveSystem() {
    const activeTab = document.querySelector('#systemTabs .nav-link.active');
    return activeTab ? activeTab.getAttribute('data-system') || '' : '';
}

// 체크박스: 전체 선택/해제
function togglePageChecks(systemId, checked) {
    document.querySelectorAll(`.page-check[data-system="${systemId}"]`).forEach(cb => cb.checked = checked);
    const allCheck = document.querySelector(`[data-system-all="${systemId}"]`);
    if (allCheck) allCheck.checked = checked;
    updatePageSelectedCount(systemId);
}

// 체크박스: 선택 개수 업데이트
function updatePageSelectedCount(systemId) {
    const all = document.querySelectorAll(`.page-check[data-system="${systemId}"]`);
    const checked = document.querySelectorAll(`.page-check[data-system="${systemId}"]:checked`);
    const badge = document.querySelector(`.page-selected-count[data-system="${systemId}"]`);
    if (badge) badge.textContent = `${checked.length}/${all.length}`;
    const allCheck = document.querySelector(`[data-system-all="${systemId}"]`);
    if (allCheck) allCheck.checked = all.length > 0 && checked.length === all.length;
    updateRunButtonLabel();
}

// 선택된 페이지 URL 수집
function getSelectedPages(systemId) {
    if (!systemId) return []; // 전체 탭이면 빈 배열 (전체 실행)
    const checked = document.querySelectorAll(`.page-check[data-system="${systemId}"]:checked`);
    return Array.from(checked).map(cb => cb.getAttribute('data-url'));
}

function updateRunButtonLabel() {
    const system = getActiveSystem();
    const label = document.getElementById('btnRunLabel');
    if (!system) {
        label.textContent = '전체 테스트 실행';
    } else {
        const all = document.querySelectorAll(`.page-check[data-system="${system}"]`);
        const checked = document.querySelectorAll(`.page-check[data-system="${system}"]:checked`);
        const tab = document.querySelector(`#systemTabs .nav-link[data-system="${system}"]`);
        const tabText = tab ? tab.textContent.trim().replace(/\d+$/, '').trim() : system;
        if (checked.length === all.length || checked.length === 0) {
            label.textContent = `${tabText} 테스트 실행`;
        } else {
            label.textContent = `${tabText} ${checked.length}개 선택 실행`;
        }
    }
}

// ============================================================
// 실행 중인 테스트 확인
// ============================================================

async function checkRunningTest() {
    try {
        const res = await apiFetch(`${API_BASE}/status`);
        const data = await res.json();
        if (data.running) {
            currentRunId = data.running.runId;
            runStartTime = new Date(data.running.startTime);
            setRunningState(true);
            connectSSE(currentRunId);
        }
    } catch (e) {
        console.error('상태 확인 실패:', e);
    }
}

// ============================================================
// 테스트 실행
// ============================================================

async function startTest() {
    const system = getActiveSystem();
    const delay = parseInt(document.getElementById('delaySlider').value) || 0;
    const btn = document.getElementById('btnRun');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 시작 중...';
    document.getElementById('logContent').innerHTML = '';

    try {
        const body = {};
        if (system) {
            body.system = system;
            const selectedPages = getSelectedPages(system);
            const allPages = document.querySelectorAll(`.page-check[data-system="${system}"]`);
            // 일부만 선택된 경우에만 pages 전달
            if (selectedPages.length > 0 && selectedPages.length < allPages.length) {
                body.pages = selectedPages;
            } else if (selectedPages.length === 0) {
                alert('테스트할 페이지를 선택해주세요.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-fill"></i> <span id="btnRunLabel">테스트 실행</span>';
                updateRunButtonLabel();
                return;
            }
        }
        if (delay) body.delay = delay;

        const res = await apiFetch(`${API_BASE}/run`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });

        const data = await res.json();

        if (res.status === 409) {
            alert('테스트가 이미 실행 중입니다.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> <span id="btnRunLabel">테스트 실행</span>';
            updateRunButtonLabel();
            return;
        }

        if (!res.ok) {
            alert('실행 실패: ' + (data.error || '알 수 없는 오류'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> <span id="btnRunLabel">테스트 실행</span>';
            updateRunButtonLabel();
            return;
        }

        currentRunId = data.runId;
        runStartTime = new Date();
        setRunningState(true);
        connectSSE(data.runId);

    } catch (e) {
        alert('API 연결 실패: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> <span id="btnRunLabel">테스트 실행</span>';
        updateRunButtonLabel();
    }
}

async function stopTest() {
    if (!currentRunId || !confirm('실행 중인 테스트를 중지하시겠습니까?')) return;
    const button = document.getElementById('btnStop');
    button.disabled = true;
    try {
        const res = await apiFetch(`${API_BASE}/stop/${currentRunId}`, { method: 'POST' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || '중지 요청 실패');
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 중지 중...';
    } catch (error) {
        alert('중지 실패: ' + error.message);
        button.disabled = false;
    }
}

// ============================================================
// SSE 연결
// ============================================================

function connectSSE(runId) {
    if (eventSource) eventSource.close();

    eventSource = new EventSource(`${API_BASE}/progress/${runId}`);
    const logEl = document.getElementById('logContent');
    let totalPages = 0;

    eventSource.addEventListener('log', (e) => {
        const data = JSON.parse(e.data);
        const msg = data.message || '';

        const pageMatch = msg.match(/\[(\d+)\/(\d+)\]/);
        if (pageMatch) {
            updateProgress(parseInt(pageMatch[1]), parseInt(pageMatch[2]));
            totalPages = parseInt(pageMatch[2]);
        }
        const totalMatch = msg.match(/총 (\d+)페이지/);
        if (totalMatch) totalPages = parseInt(totalMatch[1]);

        let colorStyle = '';
        if (msg.includes('PASS') || msg.includes('[OK]') || msg.includes('성공')) colorStyle = 'color:#4ec990;';
        else if (msg.includes('FAIL') || msg.includes('[ERROR]') || msg.includes('실패')) colorStyle = 'color:#f87171;';
        else if (msg.includes('WARN')) colorStyle = 'color:#fbbf24;';
        else if (msg.includes('===') || msg.includes('━━━')) colorStyle = 'color:#60a5fa; font-weight:bold;';

        logEl.innerHTML += `<div style="${colorStyle}">${escapeHtml(msg)}</div>`;
        logEl.scrollTop = logEl.scrollHeight;
    });

    eventSource.addEventListener('error', (e) => {
        try {
            const data = JSON.parse(e.data);
            logEl.innerHTML += `<div style="color:#f87171">[ERROR] ${escapeHtml(data.message || '')}</div>`;
        } catch (ex) {}
    });

    eventSource.addEventListener('complete', (e) => {
        const data = JSON.parse(e.data);
        eventSource.close();
        eventSource = null;
        setRunningState(false);

        if (data.summary) {
            updateSummaryCards(data.summary);
            logEl.innerHTML += `<div style="color:#60a5fa; font-weight:bold">\n=== 완료: ${data.summary.pass} PASS / ${data.summary.warn} WARN / ${data.summary.fail} FAIL ===</div>`;
        }

        if (data.resultDir) {
            logEl.innerHTML += `<div style="color:#a78bfa">DOCX: <a href="${API_BASE}/download/${data.resultDir}/report.docx" style="color:#a78bfa" target="_blank">다운로드</a></div>`;
            showDetail(data.resultDir);
        }

        updateProgress(totalPages, totalPages);
        loadHistory();
    });

    eventSource.onerror = () => {
        setTimeout(() => {
            if (eventSource && eventSource.readyState === EventSource.CLOSED) {
                setRunningState(false);
                loadHistory();
            }
        }, 2000);
    };
}

// ============================================================
// UI 상태 관리
// ============================================================

function setRunningState(running) {
    const btn = document.getElementById('btnRun');
    const status = document.getElementById('runStatus');
    const progressBar = document.getElementById('progressBar');
    const logArea = document.getElementById('logArea');
    const stopButton = document.getElementById('btnStop');

    if (running) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 실행 중...';
        status.className = 'badge bg-primary';
        status.textContent = '실행 중';
        progressBar.style.display = '';
        logArea.style.display = '';
        stopButton.classList.remove('d-none');
        stopButton.disabled = false;
        stopButton.innerHTML = '<i class="bi bi-stop-fill"></i> 중지';

        if (runTimer) clearInterval(runTimer);
        runTimer = setInterval(() => {
            if (runStartTime) {
                const elapsed = Math.floor((Date.now() - runStartTime.getTime()) / 1000);
                const min = Math.floor(elapsed / 60);
                const sec = elapsed % 60;
                document.getElementById('runTime').textContent = `${min}:${String(sec).padStart(2, '0')}`;
            }
        }, 1000);
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> <span id="btnRunLabel">테스트 실행</span>';
        updateRunButtonLabel();
        status.className = 'badge bg-success';
        status.textContent = '완료';
        stopButton.classList.add('d-none');
        if (runTimer) { clearInterval(runTimer); runTimer = null; }
    }
}

function updateProgress(current, total) {
    if (total <= 0) return;
    const pct = Math.round((current / total) * 100);
    const fill = document.getElementById('progressFill');
    fill.style.width = pct + '%';
    fill.textContent = `${current}/${total} (${pct}%)`;
    if (pct >= 100) fill.classList.remove('progress-bar-animated');
}

function updateSummaryCards(summary) {
    document.getElementById('statTotal').textContent = summary.totalPages || '-';
    document.getElementById('statPass').textContent = summary.pass ?? '-';
    document.getElementById('statWarn').textContent = summary.warn ?? '-';
    document.getElementById('statFail').textContent = summary.fail ?? '-';
}

// ============================================================
// 스크린샷 모달
// ============================================================

function openScreenshot(src, title) {
    document.getElementById('ssModalImg').src = src;
    document.getElementById('ssModalTitle').textContent = title || '스크린샷';
    new bootstrap.Modal(document.getElementById('screenshotModal')).show();
}

// ============================================================
// 상세 결과 - 아코디언 뷰 + 큰 스크린샷
// ============================================================

async function showDetail(dir) {
    currentDetailDir = dir;
    const section = document.getElementById('detailSection');
    const accordion = document.getElementById('systemAccordion');
    const summaryEl = document.getElementById('detailSummary');

    section.style.display = '';
    accordion.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted small">결과 로딩 중...</div></div>';
    summaryEl.innerHTML = '';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const [resResults, resNotes] = await Promise.all([
            apiFetch(`${API_BASE}/results/${dir}`),
            apiFetch(`${API_BASE}/notes/${dir}`)
        ]);

        if (!resResults.ok) throw new Error('HTTP ' + resResults.status);
        const data = await resResults.json();
        const notesData = resNotes.ok ? await resNotes.json() : { notes: {} };
        const savedNotes = notesData.notes || {};
        const s = data.summary || {};
        const results = data.results || [];

        // 제목
        const m = dir.match(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/);
        const dateLabel = m ? `${m[1]}-${m[2]}-${m[3]} ${m[4]}:${m[5]}:${m[6]}` : dir;
        document.getElementById('detailTitle').textContent = `상세 결과 - ${dateLabel}`;

        // 요약 바
        const alertClass = s.fail > 0 ? 'danger' : s.warn > 0 ? 'warning' : 'success';
        summaryEl.innerHTML = `
            <div class="alert alert-${alertClass} d-flex justify-content-between align-items-center mb-3 py-2">
                <div>
                    <strong>${s.totalPages || 0}개 페이지:</strong>
                    <span class="text-success fw-bold">${s.pass || 0} PASS</span> /
                    <span class="text-warning fw-bold">${s.warn || 0} WARN</span> /
                    <span class="text-danger fw-bold">${s.fail || 0} FAIL</span>
                    <span class="ms-2 text-muted">${s.elapsedSeconds || 0}초</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="expandAllSystems(true)" title="모두 펼치기">
                        <i class="bi bi-arrows-expand"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="expandAllSystems(false)" title="모두 접기">
                        <i class="bi bi-arrows-collapse"></i>
                    </button>
                </div>
            </div>`;

        // 시스템별 그룹핑
        const systems = {};
        results.forEach(r => {
            const sysName = r.system || r.systemId || 'Unknown';
            if (!systems[sysName]) systems[sysName] = [];
            systems[sysName].push(r);
        });

        let accHtml = '';
        let sysIdx = 0;

        for (const [sysName, pages] of Object.entries(systems)) {
            const sysPass = pages.filter(p => p.status === 'PASS').length;
            const sysWarn = pages.filter(p => p.status === 'WARN').length;
            const sysFail = pages.filter(p => p.status === 'FAIL').length;
            const sysColor = sysFail > 0 ? 'danger' : sysWarn > 0 ? 'warning' : 'success';
            const collapseId = `sys_${sysIdx}`;

            accHtml += `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button ${sysIdx > 0 ? 'collapsed' : ''}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                        <span class="badge bg-${sysColor} me-2">${pages.length}</span>
                        <strong>${escapeHtml(sysName)}</strong>
                        <span class="ms-auto me-3 small text-muted">
                            <span class="text-success">${sysPass}P</span> /
                            <span class="text-warning">${sysWarn}W</span> /
                            <span class="text-danger">${sysFail}F</span>
                        </span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse ${sysIdx === 0 ? 'show' : ''}"
                     data-bs-parent="#systemAccordion">
                    <div class="accordion-body p-2">`;

            // 페이지별 카드
            pages.forEach((r, i) => {
                const statusBadge = r.status === 'PASS'
                    ? '<span class="badge bg-success">PASS</span>'
                    : r.status === 'WARN'
                    ? '<span class="badge bg-warning text-dark">WARN</span>'
                    : '<span class="badge bg-danger">FAIL</span>';

                const ssFile = r.screenshotPath ? r.screenshotPath.split('/').pop().split('\\').pop() : null;
                const a = r.pageAnalysis || {};
                const errSummary = r.errors?.summary || '';
                const pageUrl = r.url || '';
                const savedNote = savedNotes[pageUrl] || '';
                const pageId = `page_${sysIdx}_${i}`;

                // 분석 배지
                let analysisBadges = '';
                if (a.linkCount !== undefined) {
                    analysisBadges = `
                        <span class="badge bg-light text-dark border analysis-badge">링크 ${a.linkCount || 0}</span>
                        <span class="badge bg-light text-dark border analysis-badge">이미지 ${a.imageCount || 0}</span>
                        <span class="badge bg-light text-dark border analysis-badge">폼 ${a.formCount || 0}</span>
                        <span class="badge bg-light text-dark border analysis-badge">테이블 ${a.tableCount || 0}</span>
                        <span class="badge bg-light text-dark border analysis-badge">버튼 ${a.buttonCount || 0}</span>
                        <span class="badge bg-light text-dark border analysis-badge">DOM ${a.domNodeCount || 0}</span>`;
                }

                // 에러 박스
                let errorBox = '';
                if (r.errors?.hasErrors || r.status !== 'PASS') {
                    const allErrs = [
                        ...(r.errors?.httpErrors || []).map(e => `HTTP ${e.status}: ${e.url || ''}`),
                        ...(r.errors?.phpErrors || []).map(e => `PHP: ${e.message || ''}`),
                        ...(r.errors?.jsErrors || []).map(e => `JS: ${e.message || ''}`),
                    ];
                    const errText = allErrs.length > 0
                        ? allErrs.slice(0, 5).map(e => escapeHtml(e.substring(0, 150))).join('<br>')
                        : escapeHtml(errSummary || '상세 정보 없음');
                    errorBox = `<div class="error-detail-box mt-2">${errText}</div>`;
                }

                // 경고
                let warnings = '';
                if (a.missingAltImages > 0) warnings += `<span class="badge bg-warning text-dark analysis-badge">alt 누락 ${a.missingAltImages}</span>`;
                if ((a.contentLength || 0) < 100) warnings += `<span class="badge bg-danger analysis-badge">텍스트 짧음</span>`;
                if ((a.brokenImageHints || []).length > 0) warnings += `<span class="badge bg-danger analysis-badge">깨진 이미지</span>`;

                const ssUrl = ssFile ? `${API_BASE}/screenshot/${dir}/${ssFile}` : '';

                accHtml += `
                    <div class="border rounded mb-2 overflow-hidden" data-url="${escapeHtml(pageUrl)}">
                        <!-- 페이지 헤더 (클릭 토글) -->
                        <div class="page-card-header d-flex justify-content-between align-items-center px-3 py-2 bg-light border-bottom"
                             onclick="togglePageBody('${pageId}')">
                            <div>
                                ${statusBadge}
                                <strong class="ms-1">${escapeHtml(r.page || '')}</strong>
                                ${r.description ? `<span class="text-muted ms-2 small">${escapeHtml(r.description)}</span>` : ''}
                                ${savedNote ? '<span class="badge bg-warning text-dark ms-1" style="font-size:10px">메모</span>' : ''}
                            </div>
                            <div class="text-muted small text-nowrap">
                                HTTP ${r.httpStatus || '-'} | ${r.loadTimeMs || '-'}ms
                                <i class="bi bi-chevron-down ms-1 page-chevron" id="chevron_${pageId}"></i>
                            </div>
                        </div>
                        <!-- 페이지 상세 (토글) -->
                        <div class="p-3" id="${pageId}" style="display:none">
                            <!-- 스크린샷 (풀 너비, 상단) -->
                            ${ssFile ? `
                            <div class="mb-3 text-center">
                                <img src="${ssUrl}" class="ss-thumb" alt="${escapeHtml(r.page || '')}"
                                     onclick="openScreenshot('${ssUrl}', '${escapeHtml(r.page || '')}')">
                            </div>` : ''}
                            <!-- 정보 + 분석 배지 + 에러 -->
                            <p class="small text-muted mb-2">
                                <i class="bi bi-link-45deg"></i> ${escapeHtml(pageUrl)}
                            </p>
                            ${a.pageTitle ? `<p class="small mb-2"><i class="bi bi-type-h1"></i> ${escapeHtml(a.pageTitle.substring(0, 80))}</p>` : ''}
                            <div class="mb-2">${analysisBadges}${warnings}</div>
                            ${errorBox}
                            <!-- 수정 프롬프트 -->
                            <div class="mt-3">
                                <label class="form-label small fw-bold mb-1">
                                    <i class="bi bi-pencil-square text-warning"></i> 수정 프롬프트
                                </label>
                                <textarea class="form-control form-control-sm note-input ${savedNote ? 'has-content' : ''}"
                                          rows="2" data-page-url="${escapeHtml(pageUrl)}"
                                          placeholder="이 페이지에서 수정할 내용을 입력하세요..."
                                          oninput="this.classList.toggle('has-content', this.value.trim().length > 0)">${escapeHtml(savedNote)}</textarea>
                            </div>
                        </div>
                    </div>`;
            });

            accHtml += `
                    </div>
                </div>
            </div>`;
            sysIdx++;
        }

        accordion.innerHTML = accHtml;

    } catch (e) {
        accordion.innerHTML = `<div class="alert alert-danger">결과 로드 실패: ${escapeHtml(e.message)}</div>`;
    }
}

// 페이지 상세 토글
function togglePageBody(pageId) {
    const el = document.getElementById(pageId);
    const chevron = document.getElementById('chevron_' + pageId);
    if (!el) return;
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? '' : 'none';
    if (chevron) {
        chevron.className = isHidden ? 'bi bi-chevron-up ms-1 page-chevron' : 'bi bi-chevron-down ms-1 page-chevron';
    }
}

// 모든 시스템 펼치기/접기
function expandAllSystems(expand) {
    document.querySelectorAll('#systemAccordion .accordion-collapse').forEach(el => {
        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        expand ? bsCollapse.show() : bsCollapse.hide();
    });
    document.querySelectorAll('#systemAccordion [id^="page_"]').forEach(el => {
        el.style.display = expand ? '' : 'none';
    });
    document.querySelectorAll('.page-chevron').forEach(el => {
        el.className = expand ? 'bi bi-chevron-up ms-1 page-chevron' : 'bi bi-chevron-down ms-1 page-chevron';
    });
}

function closeDetail() {
    document.getElementById('detailSection').style.display = 'none';
    currentDetailDir = null;
}

// ============================================================
// 노트 저장 + DOCX 내보내기
// ============================================================

function collectNotes() {
    const notes = {};
    document.querySelectorAll('.note-input').forEach(textarea => {
        const url = textarea.getAttribute('data-page-url');
        const text = textarea.value.trim();
        if (url && text) notes[url] = text;
    });
    return notes;
}

async function saveNotes() {
    if (!currentDetailDir) return;

    const notes = collectNotes();
    const btn = document.getElementById('btnSaveNotes');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const res = await apiFetch(`${API_BASE}/notes/${currentDetailDir}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notes })
        });
        const data = await res.json();
        if (data.success) {
            btn.innerHTML = '<i class="bi bi-check-lg"></i> 저장 완료';
            btn.classList.add('save-flash');
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.classList.remove('save-flash');
            }, 2000);
        } else {
            alert('저장 실패: ' + (data.error || ''));
            btn.innerHTML = origHtml;
        }
    } catch (e) {
        alert('저장 실패: ' + e.message);
        btn.innerHTML = origHtml;
    }
    btn.disabled = false;
}

async function exportDocx() {
    if (!currentDetailDir) return;

    const notes = collectNotes();
    const btn = document.getElementById('btnExportDocx');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 생성 중...';

    try {
        const res = await apiFetch(`${API_BASE}/export-report/${currentDetailDir}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notes })
        });

        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.error || 'Export failed');
        }

        const blob = await res.blob();
        const cd = res.headers.get('content-disposition') || '';
        let filename = '서울미녀_테스트보고서.docx';
        const fnMatch = cd.match(/filename[^;=\n]*=(?:.*?'.*?'|"([^"]*)"|([^;\n]*))/);
        if (fnMatch) filename = decodeURIComponent(fnMatch[1] || fnMatch[2] || filename);

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);

        btn.innerHTML = '<i class="bi bi-check-lg"></i> 다운로드 완료';
        setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
    } catch (e) {
        alert('문서 생성 실패: ' + e.message);
        btn.innerHTML = origHtml;
    }
    btn.disabled = false;
}

// ============================================================
// 이력 로드
// ============================================================

async function loadHistory() {
    const tbody = document.getElementById('historyBody');
    try {
        const res = await apiFetch(`${API_BASE}/history`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const history = await res.json();

        if (history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">테스트 이력 없음</td></tr>';
            document.getElementById('btnDeleteAll').style.display = 'none';
            document.getElementById('btnDeleteSelected').style.display = 'none';
            document.getElementById('checkAll').checked = false;
            return;
        }

        document.getElementById('btnDeleteAll').style.display = '';
        document.getElementById('btnDeleteSelected').style.display = 'none';
        document.getElementById('checkAll').checked = false;

        if (history[0] && history[0].summary) {
            updateSummaryCards(history[0].summary);
        }

        tbody.innerHTML = history.map(h => {
            const s = h.summary || {};
            const elapsed = s.elapsedSeconds ? `${Math.floor(s.elapsedSeconds / 60)}:${String(Math.round(s.elapsedSeconds % 60)).padStart(2, '0')}` : '-';

            return `<tr class="${currentDetailDir === h.dir ? 'table-active' : ''}">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input history-check" value="${h.dir}"
                           onchange="updateSelectedCount()">
                </td>
                <td>
                    <a href="#" onclick="showDetail('${h.dir}'); return false;" class="text-decoration-none fw-bold">
                        ${escapeHtml(h.date)}
                    </a>
                </td>
                <td class="text-center">${s.totalPages || '-'}</td>
                <td class="text-center text-success fw-bold">${s.pass ?? '-'}</td>
                <td class="text-center text-warning fw-bold">${s.warn ?? '-'}</td>
                <td class="text-center text-danger fw-bold">${s.fail ?? '-'}</td>
                <td class="text-center">${elapsed}</td>
                <td class="text-center">${h.screenshotCount || 0}</td>
                <td>
                    ${h.hasDocx ? `<a href="${API_BASE}/download/${h.dir}/report.docx" class="btn btn-outline-primary btn-sm py-0 px-1 me-1" title="Word"><i class="bi bi-file-earmark-word"></i></a>` : ''}
                    ${h.hasJson ? `<a href="${API_BASE}/download/${h.dir}/results.json" class="btn btn-outline-secondary btn-sm py-0 px-1" title="JSON"><i class="bi bi-filetype-json"></i></a>` : ''}
                </td>
                <td>
                    <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="deleteHistory('${h.dir}')" title="삭제">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-3">이력 로드 실패: ${escapeHtml(e.message)}</td></tr>`;
    }
}

// ============================================================
// 이력 삭제
// ============================================================

async function deleteHistory(dir) {
    if (!confirm(`${dir} 이력을 삭제하시겠습니까?`)) return;
    try {
        const res = await apiFetch(`${API_BASE}/history/${dir}`, { method: 'DELETE' });
        if (res.ok) {
            if (currentDetailDir === dir) closeDetail();
            loadHistory();
        } else {
            const data = await res.json();
            alert('삭제 실패: ' + (data.error || ''));
        }
    } catch (e) {
        alert('삭제 실패: ' + e.message);
    }
}

// ============================================================
// 체크박스 + 일괄 삭제
// ============================================================

function toggleCheckAll(checked) {
    document.querySelectorAll('.history-check').forEach(cb => cb.checked = checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.history-check:checked');
    const total = document.querySelectorAll('.history-check');
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('btnDeleteSelected').style.display = checked.length > 0 ? '' : 'none';
    document.getElementById('checkAll').checked = total.length > 0 && checked.length === total.length;
}

async function deleteSelected() {
    const checked = document.querySelectorAll('.history-check:checked');
    if (checked.length === 0) return;
    if (!confirm(`선택한 ${checked.length}개 이력을 삭제하시겠습니까?`)) return;
    const dirs = Array.from(checked).map(cb => cb.value);
    try {
        const res = await apiFetch(`${API_BASE}/history/bulk-delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dirs })
        });
        const data = await res.json();
        if (data.success) {
            if (dirs.includes(currentDetailDir)) closeDetail();
            loadHistory();
        } else {
            alert('삭제 실패: ' + (data.error || ''));
        }
    } catch (e) {
        alert('삭제 실패: ' + e.message);
    }
}

async function deleteAll() {
    const allChecks = document.querySelectorAll('.history-check');
    if (allChecks.length === 0) return;
    if (!confirm(`전체 ${allChecks.length}개 이력을 모두 삭제하시겠습니까?`)) return;
    const dirs = Array.from(allChecks).map(cb => cb.value);
    try {
        const res = await apiFetch(`${API_BASE}/history/bulk-delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dirs })
        });
        const data = await res.json();
        if (data.success) {
            closeDetail();
            loadHistory();
        } else {
            alert('삭제 실패: ' + (data.error || ''));
        }
    } catch (e) {
        alert('삭제 실패: ' + e.message);
    }
}

// ============================================================
// 유틸리티
// ============================================================

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

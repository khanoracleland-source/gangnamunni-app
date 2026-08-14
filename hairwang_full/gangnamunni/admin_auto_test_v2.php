<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 자동 테스트 v2 - 마법사 인터페이스
 * 플러그인 기반 확장 가능한 테스트 시스템
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

$admin_page_title = '자동 테스트 v2';
$admin_current = 'auto_test_v2';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('auto_test_v2');
require_once __DIR__ . '/includes/auto_test_security.php';
require_auto_test_enabled();
if (empty($_SESSION['admin_auto_test_v2_csrf'])) {
    $_SESSION['admin_auto_test_v2_csrf'] = bin2hex(random_bytes(32));
}
$admin_auto_test_v2_csrf = $_SESSION['admin_auto_test_v2_csrf'];
issue_auto_test_api_session('v2', $admin_auto_test_v2_csrf);
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 마법사 스텝 헤더 ── */
.wizard-steps { display: flex; justify-content: center; align-items: center; padding: 24px 0 32px; gap: 0; }
.wizard-step {
    display: flex; flex-direction: column; align-items: center; position: relative; z-index: 1; cursor: pointer;
}
.wizard-step .step-circle {
    width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 16px; border: 3px solid var(--bs-border-color); background: var(--bs-body-bg);
    color: var(--bs-secondary-color); transition: all 0.3s;
}
.wizard-step.active .step-circle { border-color: #ff6b35; background: #ff6b35; color: #fff; }
.wizard-step.done .step-circle { border-color: #28a745; background: #28a745; color: #fff; }
.wizard-step .step-label { font-size: 12px; margin-top: 6px; color: var(--bs-secondary-color); font-weight: 500; white-space: nowrap; }
.wizard-step.active .step-label { color: #ff6b35; font-weight: 700; }
.wizard-step.done .step-label { color: #28a745; }
.step-line { width: 80px; height: 3px; background: var(--bs-border-color); margin: 0 8px; margin-bottom: 20px; transition: background 0.3s; }
.step-line.done { background: #28a745; }

/* ── 콘텐츠 영역 ── */
.wizard-body { max-width: 960px; margin: 0 auto; }
.wizard-panel { display: none; animation: fadeSlide 0.3s ease; }
.wizard-panel.active { display: block; }
@keyframes fadeSlide { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* ── Step 1: 그룹 카드 ── */
.group-card {
    border: 2px solid var(--bs-border-color); border-radius: 14px; padding: 20px; cursor: pointer;
    transition: all 0.25s; height: 100%; position: relative; overflow: hidden;
}
.group-card:hover { border-color: #ff6b35; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.group-card.selected { border-color: #ff6b35; background: rgba(255,107,53,0.05); box-shadow: 0 0 0 3px rgba(255,107,53,0.15); }
.group-card .group-icon { font-size: 36px; margin-bottom: 8px; }
.group-card .group-name { font-size: 16px; font-weight: 700; }
.group-card .group-desc { font-size: 12px; color: var(--bs-secondary-color); margin-top: 4px; }
.group-card .group-count { font-size: 13px; color: #ff6b35; font-weight: 600; margin-top: 6px; }
.group-card .group-check {
    position: absolute; top: 10px; right: 10px; width: 24px; height: 24px; border-radius: 50%;
    border: 2px solid var(--bs-border-color); display: flex; align-items: center; justify-content: center;
    font-size: 14px; color: transparent; transition: all 0.2s;
}
.group-card.selected .group-check { border-color: #ff6b35; background: #ff6b35; color: #fff; }

/* ── Step 2: 테스트 항목 ── */
.test-group-section { margin-bottom: 20px; }
.test-group-header {
    display: flex; align-items: center; justify-content: space-between; padding: 10px 14px;
    background: var(--bs-tertiary-bg); border-radius: 10px; cursor: pointer; user-select: none;
}
.test-group-header:hover { background: var(--bs-secondary-bg); }
.test-group-header .bi-chevron-down { transition: transform 0.2s; }
.test-group-header.collapsed .bi-chevron-down { transform: rotate(-90deg); }
.test-category { margin: 8px 0 4px 10px; }
.test-category-label {
    font-size: 13px; font-weight: 600; color: var(--bs-secondary-color); padding: 6px 0;
    display: flex; align-items: center; gap: 6px; cursor: pointer;
}
.test-item-list { padding-left: 10px; }
.test-item {
    display: flex; align-items: center; gap: 8px; padding: 5px 8px; border-radius: 6px; font-size: 13px;
}
.test-item:hover { background: var(--bs-tertiary-bg); }
.test-item label { cursor: pointer; flex: 1; margin: 0; }

/* ── Step 3: 옵션 ── */
.option-section { border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.option-section h6 { margin-bottom: 14px; font-weight: 700; }

/* ── Step 4: 실행 ── */
.run-progress { margin-bottom: 20px; }
.run-log {
    background: var(--bs-tertiary-bg); border-radius: 10px; padding: 16px; font-family: 'Consolas', monospace;
    font-size: 13px; max-height: 400px; overflow-y: auto; line-height: 1.6;
}
.run-log .log-pass { color: #28a745; }
.run-log .log-warn { color: #ffc107; }
.run-log .log-fail { color: #dc3545; }
.run-log .log-info { color: var(--bs-secondary-color); }

/* ── 결과 대시보드 ── */
.result-card { border-radius: 12px; text-align: center; padding: 20px; }
.result-card .result-num { font-size: 36px; font-weight: 800; }
.result-card .result-label { font-size: 13px; font-weight: 500; }
.result-item {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--bs-border-color); margin-bottom: 6px; cursor: pointer; transition: background 0.15s;
}
.result-item:hover { background: var(--bs-tertiary-bg); }
.result-item .result-screenshot { width: 120px; height: 68px; object-fit: cover; border-radius: 6px; border: 1px solid var(--bs-border-color); }
.result-item .result-name { font-weight: 600; font-size: 14px; }
.result-item .result-url { font-size: 11px; color: var(--bs-secondary-color); }
.result-badge-pass { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.result-badge-warn { background: var(--bs-body-bg)3cd; color: #856404; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.result-badge-fail { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }

/* ── 프리셋 ── */
.preset-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
.preset-btn {
    padding: 6px 14px; border-radius: 20px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg);
    font-size: 12px; cursor: pointer; transition: all 0.15s;
}
.preset-btn:hover { border-color: #ff6b35; color: #ff6b35; }
.preset-btn.active { background: #ff6b35; color: #fff; border-color: #ff6b35; }

/* ── 히스토리 ── */
.history-item {
    display: flex; align-items: center; justify-content: space-between; padding: 12px 16px;
    border: 1px solid var(--bs-border-color); border-radius: 10px; margin-bottom: 8px;
}

/* ── 스크린샷 모달 ── */
.screenshot-modal-img { max-width: 100%; max-height: 80vh; border-radius: 8px; }
</style>

<!-- 마법사 스텝 헤더 -->
<div class="wizard-steps" id="wizardSteps">
    <div class="wizard-step active" data-step="1" onclick="goToStep(1)">
        <div class="step-circle">1</div>
        <span class="step-label">그룹 선택</span>
    </div>
    <div class="step-line" id="line1"></div>
    <div class="wizard-step" data-step="2" onclick="goToStep(2)">
        <div class="step-circle">2</div>
        <span class="step-label">테스트 항목</span>
    </div>
    <div class="step-line" id="line2"></div>
    <div class="wizard-step" data-step="3" onclick="goToStep(3)">
        <div class="step-circle">3</div>
        <span class="step-label">실행 옵션</span>
    </div>
    <div class="step-line" id="line3"></div>
    <div class="wizard-step" data-step="4" onclick="goToStep(4)">
        <div class="step-circle">4</div>
        <span class="step-label">실행</span>
    </div>
</div>

<div class="wizard-body">

    <!-- ════════════ Step 1: 그룹 선택 ════════════ -->
    <div class="wizard-panel active" id="panel1">
        <h5 class="mb-3"><i class="bi bi-collection"></i> 테스트 그룹 선택</h5>
        <p class="text-muted small mb-3">테스트할 시스템 그룹을 선택하세요 (복수 선택 가능)</p>

        <div class="row g-3" id="groupGrid">
            <!-- JS로 동적 생성 -->
        </div>

        <div class="preset-bar mt-3">
            <button class="preset-btn" onclick="presetSelect('all')">전체 선택</button>
            <button class="preset-btn" onclick="presetSelect('admin')">관리자만</button>
            <button class="preset-btn" onclick="presetSelect('frontend')">프론트만</button>
            <button class="preset-btn" onclick="presetSelect('none')">전체 해제</button>
            <span class="ms-auto">
                <button class="preset-btn" onclick="loadPresets()"><i class="bi bi-folder2-open"></i> 프리셋 불러오기</button>
            </span>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <div>
                <span class="badge bg-secondary" id="selectedGroupCount">0개 그룹 선택</span>
                <span class="badge bg-info ms-2" id="selectedTestCount">0개 테스트</span>
            </div>
            <button class="btn btn-primary" onclick="nextStep()">다음 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- ════════════ Step 2: 테스트 항목 ════════════ -->
    <div class="wizard-panel" id="panel2">
        <h5 class="mb-3"><i class="bi bi-list-check"></i> 테스트 항목 선택</h5>
        <p class="text-muted small mb-3">각 그룹의 세부 테스트를 선택하세요</p>

        <div id="testItemsContainer">
            <!-- JS로 동적 생성 -->
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button class="btn btn-outline-secondary" onclick="prevStep()"><i class="bi bi-arrow-left"></i> 이전</button>
            <div>
                <span class="badge bg-info" id="step2TestCount">0개 선택됨</span>
            </div>
            <button class="btn btn-primary" onclick="nextStep()">다음 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- ════════════ Step 3: 실행 옵션 ════════════ -->
    <div class="wizard-panel" id="panel3">
        <h5 class="mb-3"><i class="bi bi-sliders"></i> 실행 옵션</h5>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="option-section">
                    <h6><i class="bi bi-camera-video"></i> 캡처 설정</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">캡처 딜레이</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" class="form-range flex-grow-1" id="optDelay" min="1" max="10" value="3" oninput="document.getElementById('delayVal').textContent=this.value+'초'">
                            <span id="delayVal" class="badge bg-secondary" style="min-width:40px">3초</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">뷰포트 크기</label>
                        <select class="form-select form-select-sm" id="optViewport">
                            <option value="1920x1080" selected>1920 x 1080 (FHD)</option>
                            <option value="1366x768">1366 x 768 (일반)</option>
                            <option value="1280x720">1280 x 720 (HD)</option>
                            <option value="375x812">375 x 812 (iPhone)</option>
                            <option value="360x800">360 x 800 (Android)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">스크린샷 모드</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="optScreenshot" id="ssFullPage" value="fullPage" checked>
                            <label class="form-check-label small" for="ssFullPage">전체 페이지</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="optScreenshot" id="ssViewport" value="viewport">
                            <label class="form-check-label small" for="ssViewport">뷰포트만</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="option-section">
                    <h6><i class="bi bi-file-earmark-text"></i> 리포트 설정</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="optDocx" checked>
                        <label class="form-check-label small" for="optDocx">DOCX 리포트 자동 생성</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="optCompare" checked>
                        <label class="form-check-label small" for="optCompare">이전 결과와 비교</label>
                    </div>
                </div>
                <div class="option-section">
                    <h6><i class="bi bi-bookmark-star"></i> 프리셋 저장</h6>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="presetName" placeholder="프리셋 이름...">
                        <button class="btn btn-outline-secondary" onclick="savePreset()"><i class="bi bi-save"></i> 저장</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 요약 -->
        <div class="alert alert-light border mt-3">
            <strong><i class="bi bi-info-circle"></i> 실행 요약:</strong>
            <span id="runSummary">-</span>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <button class="btn btn-outline-secondary" onclick="prevStep()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn btn-success btn-lg" onclick="startRun()"><i class="bi bi-rocket-takeoff"></i> 테스트 실행</button>
        </div>
    </div>

    <!-- ════════════ Step 4: 실행 & 결과 ════════════ -->
    <div class="wizard-panel" id="panel4">
        <!-- 실행 중 -->
        <div id="runningView">
            <h5 class="mb-3"><i class="bi bi-hourglass-split"></i> 테스트 실행 중...</h5>
            <div class="run-progress">
                <div class="d-flex justify-content-between small mb-1">
                    <span id="runProgressText">0 / 0</span>
                    <span id="runTimer">00:00</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" id="runProgressBar" style="width:0%"></div>
                </div>
            </div>
            <div class="run-log" id="runLog"></div>
            <div class="mt-3 text-center">
                <button class="btn btn-outline-danger" onclick="stopRun()"><i class="bi bi-stop-circle"></i> 중단</button>
            </div>
        </div>

        <!-- 결과 대시보드 -->
        <div id="resultView" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> 테스트 결과</h5>
                <small class="text-muted" id="resultDate"></small>
            </div>

            <!-- 요약 카드 -->
            <div class="row g-3 mb-4" id="resultSummaryCards">
                <!-- JS 생성 -->
            </div>

            <!-- 비교 (있으면) -->
            <div class="alert alert-info small" id="comparisonAlert" style="display:none">
                <i class="bi bi-arrow-left-right"></i> <strong>이전 실행과 비교:</strong> <span id="comparisonText"></span>
            </div>

            <!-- 시스템별 결과 아코디언 -->
            <div class="accordion" id="resultAccordion">
                <!-- JS 생성 -->
            </div>

            <!-- 액션 버튼 -->
            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button class="btn btn-outline-primary btn-sm" onclick="downloadReport('docx')"><i class="bi bi-file-earmark-word"></i> DOCX</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="downloadReport('json')"><i class="bi bi-filetype-json"></i> JSON</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="downloadReport('zip')"><i class="bi bi-file-zip"></i> ZIP</button>
                <div class="ms-auto">
                    <button class="btn btn-warning btn-sm" onclick="rerunFailed()"><i class="bi bi-arrow-repeat"></i> 실패만 재실행</button>
                    <button class="btn btn-primary btn-sm" onclick="goToStep(1)"><i class="bi bi-arrow-clockwise"></i> 새 테스트</button>
                </div>
            </div>
        </div>

        <!-- 히스토리 -->
        <div class="mt-4 pt-3 border-top" id="historySection">
            <h6 class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> 실행 이력</span>
                <button class="btn btn-outline-secondary btn-sm" onclick="loadHistory()"><i class="bi bi-arrow-clockwise"></i></button>
            </h6>
            <div id="historyList">
                <p class="text-muted small">로딩 중...</p>
            </div>
        </div>
    </div>

</div><!-- /wizard-body -->

<!-- 스크린샷 모달 -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="screenshotModalTitle">스크린샷</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="screenshotModalImg" class="screenshot-modal-img" src="" alt="">
            </div>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
// 자동 테스트 v2 - 마법사 컨트롤러
// ══════════════════════════════════════════════════════════════

const API_BASE = '/auto-test-v2-api/api/v2';
const AUTO_TEST_CSRF = <?= json_encode($admin_auto_test_v2_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('X-CSRF-Token', AUTO_TEST_CSRF);
    return window.fetch(url, { ...options, headers, credentials: 'same-origin' });
}
let currentStep = 1;
let testConfig = null;
let selectedGroups = new Set();
let selectedTests = {};  // { groupId: { basic: Set, functional: Set, scenario: Set } }
let currentRunId = null;
let runTimer = null;
let runStartTime = null;
let eventSource = null;

// ── 초기화 ──
document.addEventListener('DOMContentLoaded', async () => {
    await loadConfig();
    loadHistory();
});

async function loadConfig() {
    try {
        const res = await apiFetch(`${API_BASE}/config`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        testConfig = await res.json();
        renderGroupGrid();
    } catch (e) {
        console.error('Config load failed:', e);
        document.getElementById('groupGrid').innerHTML = '<div class="col-12"><div class="alert alert-danger">설정을 불러올 수 없습니다. 서버 연결을 확인하세요.</div></div>';
    }
}

// ── Step 1: 그룹 카드 렌더링 ──
function renderGroupGrid() {
    if (!testConfig || !testConfig.groups) return;
    const grid = document.getElementById('groupGrid');
    grid.innerHTML = '';

    testConfig.groups.forEach(g => {
        const totalTests = (g.tests || []).reduce((sum, cat) => sum + (cat.items || []).length, 0);
        const authIcon = g.auth && g.auth.type !== 'none'
            ? '<span class="badge bg-warning text-dark" style="font-size:10px"><i class="bi bi-key"></i> 로그인</span>'
            : '<span class="badge bg-success" style="font-size:10px"><i class="bi bi-globe"></i> 공개</span>';

        const card = document.createElement('div');
        card.className = 'col-md-4 col-6';
        card.innerHTML = `
            <div class="group-card" data-group="${g.id}" onclick="toggleGroup('${g.id}', this)">
                <div class="group-check"><i class="bi bi-check"></i></div>
                <div class="group-icon"><i class="bi ${g.icon}"></i></div>
                <div class="group-name">${g.name}</div>
                <div class="group-desc">${g.description || ''}</div>
                <div class="group-count">${totalTests}개 테스트 ${authIcon}</div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function toggleGroup(groupId, el) {
    const card = el.closest ? el : el.querySelector('.group-card');
    if (selectedGroups.has(groupId)) {
        selectedGroups.delete(groupId);
        card.classList.remove('selected');
    } else {
        selectedGroups.add(groupId);
        card.classList.add('selected');
        // 기본 테스트 자동 선택
        if (!selectedTests[groupId]) {
            initGroupTests(groupId);
        }
    }
    updateCounts();
}

function initGroupTests(groupId) {
    const group = testConfig.groups.find(g => g.id === groupId);
    if (!group) return;
    selectedTests[groupId] = {};
    (group.tests || []).forEach(cat => {
        selectedTests[groupId][cat.category] = new Set();
        if (cat.category === 'basic') {
            // 기본 테스트는 자동 전체 선택
            (cat.items || []).forEach(item => selectedTests[groupId][cat.category].add(item.id));
        }
    });
}

function presetSelect(type) {
    document.querySelectorAll('.group-card').forEach(card => {
        const gid = card.dataset.group;
        const group = testConfig.groups.find(g => g.id === gid);
        if (!group) return;

        let shouldSelect = false;
        if (type === 'all') shouldSelect = true;
        else if (type === 'admin') shouldSelect = gid.includes('admin');
        else if (type === 'frontend') shouldSelect = gid.includes('frontend');
        else if (type === 'none') shouldSelect = false;

        if (shouldSelect && !selectedGroups.has(gid)) {
            selectedGroups.add(gid);
            card.classList.add('selected');
            if (!selectedTests[gid]) initGroupTests(gid);
        } else if (!shouldSelect && selectedGroups.has(gid)) {
            selectedGroups.delete(gid);
            card.classList.remove('selected');
        }
    });
    updateCounts();
}

function updateCounts() {
    const groupCount = selectedGroups.size;
    let testCount = 0;
    selectedGroups.forEach(gid => {
        if (selectedTests[gid]) {
            Object.values(selectedTests[gid]).forEach(set => testCount += set.size);
        }
    });
    document.getElementById('selectedGroupCount').textContent = `${groupCount}개 그룹 선택`;
    document.getElementById('selectedTestCount').textContent = `${testCount}개 테스트`;
}

// ── Step 2: 테스트 항목 렌더링 ──
function renderTestItems() {
    const container = document.getElementById('testItemsContainer');
    container.innerHTML = '';

    selectedGroups.forEach(gid => {
        const group = testConfig.groups.find(g => g.id === gid);
        if (!group) return;

        const section = document.createElement('div');
        section.className = 'test-group-section';
        section.innerHTML = `
            <div class="test-group-header" onclick="this.classList.toggle('collapsed'); this.nextElementSibling.classList.toggle('d-none')">
                <span><i class="bi ${group.icon} me-2"></i><strong>${group.name}</strong></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" id="count_${gid}">0</span>
                    <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="event.stopPropagation(); selectAllInGroup('${gid}')">전체</button>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="event.stopPropagation(); deselectAllInGroup('${gid}')">해제</button>
                    <i class="bi bi-chevron-down"></i>
                </span>
            </div>
            <div class="ps-2 pt-1" id="tests_${gid}"></div>
        `;
        container.appendChild(section);

        const testsDiv = section.querySelector(`#tests_${gid}`);
        (group.tests || []).forEach(cat => {
            const catLabel = cat.label || (cat.category === 'basic' ? '기본 테스트 (페이지 접근)' :
                            cat.category === 'functional' ? '폼 페이지 접근 (CRUD 미수행)' :
                            '시나리오 시작 페이지 접근 (흐름 미수행)');
            const catIcon = cat.category === 'basic' ? 'bi-globe' :
                           cat.category === 'functional' ? 'bi-gear' : 'bi-diagram-3';

            const catDiv = document.createElement('div');
            catDiv.className = 'test-category';
            catDiv.innerHTML = `
                <div class="test-category-label" onclick="toggleCategoryAll('${gid}', '${cat.category}', this)">
                    <i class="bi ${catIcon}"></i> ${esc(catLabel)}
                    <span class="badge bg-light text-dark" id="catcount_${gid}_${cat.category}">0/${(cat.items||[]).length}</span>
                </div>
                <div class="test-item-list" id="items_${gid}_${cat.category}"></div>
            `;
            testsDiv.appendChild(catDiv);

            const itemsDiv = catDiv.querySelector(`#items_${gid}_${cat.category}`);
            (cat.items || []).forEach(item => {
                const checked = selectedTests[gid]?.[cat.category]?.has(item.id) ? 'checked' : '';
                const itemDiv = document.createElement('div');
                itemDiv.className = 'test-item';
                itemDiv.innerHTML = `
                    <input class="form-check-input" type="checkbox" id="cb_${item.id}" ${checked}
                           onchange="toggleTestItem('${gid}', '${cat.category}', '${item.id}', this.checked)">
                    <label for="cb_${item.id}">${item.name}</label>
                    <small class="text-muted">${item.description || ''}</small>
                `;
                itemsDiv.appendChild(itemDiv);
            });
        });
        updateGroupCount(gid);
    });
    updateStep2Count();
}

function toggleTestItem(gid, category, itemId, checked) {
    if (!selectedTests[gid]) selectedTests[gid] = {};
    if (!selectedTests[gid][category]) selectedTests[gid][category] = new Set();
    if (checked) selectedTests[gid][category].add(itemId);
    else selectedTests[gid][category].delete(itemId);
    updateGroupCount(gid);
    updateCatCount(gid, category);
    updateStep2Count();
}

function toggleCategoryAll(gid, category) {
    const group = testConfig.groups.find(g => g.id === gid);
    const cat = group?.tests?.find(c => c.category === category);
    if (!cat) return;
    if (!selectedTests[gid]) selectedTests[gid] = {};
    if (!selectedTests[gid][category]) selectedTests[gid][category] = new Set();

    const allSelected = cat.items.every(item => selectedTests[gid][category].has(item.id));
    cat.items.forEach(item => {
        if (allSelected) selectedTests[gid][category].delete(item.id);
        else selectedTests[gid][category].add(item.id);
        const cb = document.getElementById(`cb_${item.id}`);
        if (cb) cb.checked = !allSelected;
    });
    updateGroupCount(gid);
    updateCatCount(gid, category);
    updateStep2Count();
}

function selectAllInGroup(gid) {
    const group = testConfig.groups.find(g => g.id === gid);
    if (!group) return;
    if (!selectedTests[gid]) selectedTests[gid] = {};
    group.tests.forEach(cat => {
        if (!selectedTests[gid][cat.category]) selectedTests[gid][cat.category] = new Set();
        cat.items.forEach(item => {
            selectedTests[gid][cat.category].add(item.id);
            const cb = document.getElementById(`cb_${item.id}`);
            if (cb) cb.checked = true;
        });
        updateCatCount(gid, cat.category);
    });
    updateGroupCount(gid);
    updateStep2Count();
}

function deselectAllInGroup(gid) {
    const group = testConfig.groups.find(g => g.id === gid);
    if (!group) return;
    if (!selectedTests[gid]) selectedTests[gid] = {};
    group.tests.forEach(cat => {
        selectedTests[gid][cat.category] = new Set();
        cat.items.forEach(item => {
            const cb = document.getElementById(`cb_${item.id}`);
            if (cb) cb.checked = false;
        });
        updateCatCount(gid, cat.category);
    });
    updateGroupCount(gid);
    updateStep2Count();
}

function updateGroupCount(gid) {
    let count = 0;
    if (selectedTests[gid]) Object.values(selectedTests[gid]).forEach(s => count += s.size);
    const el = document.getElementById(`count_${gid}`);
    if (el) el.textContent = count;
}

function updateCatCount(gid, category) {
    const group = testConfig.groups.find(g => g.id === gid);
    const cat = group?.tests?.find(c => c.category === category);
    const total = cat?.items?.length || 0;
    const selected = selectedTests[gid]?.[category]?.size || 0;
    const el = document.getElementById(`catcount_${gid}_${category}`);
    if (el) el.textContent = `${selected}/${total}`;
}

function updateStep2Count() {
    let total = 0;
    selectedGroups.forEach(gid => {
        if (selectedTests[gid]) Object.values(selectedTests[gid]).forEach(s => total += s.size);
    });
    document.getElementById('step2TestCount').textContent = `${total}개 선택됨`;
}

// ── 마법사 네비게이션 ──
function goToStep(step) {
    if (step < 1 || step > 4) return;
    if (step > currentStep + 1) return; // 건너뛰기 방지

    // Step 2 진입시 테스트 항목 렌더링
    if (step === 2) renderTestItems();
    // Step 3 진입시 요약 갱신
    if (step === 3) updateRunSummary();

    currentStep = step;
    document.querySelectorAll('.wizard-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(`panel${step}`).classList.add('active');

    document.querySelectorAll('.wizard-step').forEach(s => {
        const sn = parseInt(s.dataset.step);
        s.classList.remove('active', 'done');
        if (sn === step) s.classList.add('active');
        else if (sn < step) s.classList.add('done');
    });
    document.querySelectorAll('.step-line').forEach((l, i) => {
        l.classList.toggle('done', i + 1 < step);
    });
}

function nextStep() {
    if (currentStep === 1 && selectedGroups.size === 0) {
        alert('최소 1개 그룹을 선택하세요.');
        return;
    }
    if (currentStep === 2) {
        let total = 0;
        selectedGroups.forEach(gid => {
            if (selectedTests[gid]) Object.values(selectedTests[gid]).forEach(s => total += s.size);
        });
        if (total === 0) { alert('최소 1개 테스트를 선택하세요.'); return; }
    }
    goToStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) goToStep(currentStep - 1);
}

function updateRunSummary() {
    let groups = 0, basic = 0, functional = 0, scenario = 0;
    selectedGroups.forEach(gid => {
        groups++;
        if (selectedTests[gid]) {
            basic += selectedTests[gid].basic?.size || 0;
            functional += selectedTests[gid].functional?.size || 0;
            scenario += selectedTests[gid].scenario?.size || 0;
        }
    });
    const total = basic + functional + scenario;
    const delay = document.getElementById('optDelay').value;
    document.getElementById('runSummary').innerHTML =
        `<strong>${groups}</strong>개 그룹 / <strong>${total}</strong>개 테스트 (기본 ${basic} + 폼 접근 ${functional} + 흐름 시작점 ${scenario}) / 딜레이 ${delay}초`;
}

// ── Step 4: 테스트 실행 ──
async function startRun() {
    goToStep(4);
    document.getElementById('runningView').style.display = '';
    document.getElementById('resultView').style.display = 'none';

    const logDiv = document.getElementById('runLog');
    logDiv.innerHTML = '';

    // 옵션 수집
    const viewport = document.getElementById('optViewport').value.split('x');
    const options = {
        delay: parseInt(document.getElementById('optDelay').value),
        viewport: { width: parseInt(viewport[0]), height: parseInt(viewport[1]) },
        fullPage: document.querySelector('input[name="optScreenshot"]:checked').value === 'fullPage',
        generateReport: document.getElementById('optDocx').checked,
        compare: document.getElementById('optCompare').checked
    };

    // 선택된 테스트 수집
    const tests = {};
    selectedGroups.forEach(gid => {
        tests[gid] = [];
        if (selectedTests[gid]) {
            Object.entries(selectedTests[gid]).forEach(([cat, items]) => {
                items.forEach(id => tests[gid].push({ id, category: cat }));
            });
        }
    });

    // 타이머 시작
    runStartTime = Date.now();
    runTimer = setInterval(updateTimer, 1000);

    try {
        const res = await apiFetch(`${API_BASE}/run`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tests, options })
        });
        const data = await res.json();
        if (!data.runId) throw new Error(data.error || 'Failed to start');
        currentRunId = data.runId;

        addLog('info', `테스트 시작: ${data.runId}`);
        addLog('info', `총 ${data.totalTests}개 테스트 실행 예정`);

        // SSE 연결
        connectSSE(data.runId);
    } catch (e) {
        addLog('fail', `실행 실패: ${e.message}`);
        clearInterval(runTimer);
    }
}

function connectSSE(runId) {
    if (eventSource) eventSource.close();
    eventSource = new EventSource(`${API_BASE}/progress/${runId}`);

    eventSource.addEventListener('progress', (e) => {
        const d = JSON.parse(e.data);
        const total = d.total || 1;
        const current = d.current || 0;
        const pct = Math.round((current / total) * 100);

        document.getElementById('runProgressBar').style.width = pct + '%';
        document.getElementById('runProgressText').textContent = `${current} / ${total}`;

        if (d.status) {
            const cls = d.status === 'PASS' ? 'pass' : d.status === 'WARN' ? 'warn' : d.status === 'FAIL' ? 'fail' : 'info';
            const icon = d.status === 'PASS' ? '✅' : d.status === 'WARN' ? '⚠️' : d.status === 'FAIL' ? '❌' : '🔄';
            const time = d.duration ? ` (${(d.duration / 1000).toFixed(1)}s)` : '';
            addLog(cls, `${icon} [${d.group || ''}] ${d.name || ''} ${'.'.repeat(Math.max(1, 40 - (d.name||'').length))} ${d.status}${time}`);
        } else if (d.message) {
            addLog('info', d.message);
        }
    });

    eventSource.addEventListener('complete', (e) => {
        const d = JSON.parse(e.data);
        eventSource.close();
        eventSource = null;
        clearInterval(runTimer);

        addLog('info', `\n테스트 완료! (${formatTime(Date.now() - runStartTime)})`);
        showResults(d.dir || currentRunId);
    });

    eventSource.addEventListener('error', (e) => {
        if (eventSource.readyState === EventSource.CLOSED) return;
        addLog('fail', '연결 끊김 - 재연결 시도...');
    });
}

function addLog(type, msg) {
    const logDiv = document.getElementById('runLog');
    const line = document.createElement('div');
    line.className = `log-${type}`;
    line.textContent = msg;
    logDiv.appendChild(line);
    logDiv.scrollTop = logDiv.scrollHeight;
}

function updateTimer() {
    if (!runStartTime) return;
    const elapsed = Date.now() - runStartTime;
    document.getElementById('runTimer').textContent = formatTime(elapsed);
}

function formatTime(ms) {
    const s = Math.floor(ms / 1000);
    return `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;
}

async function stopRun() {
    if (!currentRunId) return;
    if (!confirm('테스트를 중단하시겠습니까?')) return;
    try {
        const res = await apiFetch(`${API_BASE}/stop/${currentRunId}`, { method: 'POST' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        addLog('warn', '사용자에 의해 중단됨');
    } catch (e) {
        addLog('fail', `중단 실패: ${e.message}`);
        return;
    }
    if (eventSource) { eventSource.close(); eventSource = null; }
    clearInterval(runTimer);
}

// ── 결과 표시 ──
async function showResults(dir) {
    try {
        const res = await apiFetch(`${API_BASE}/results/${dir}`);
        const data = await res.json();

        document.getElementById('runningView').style.display = 'none';
        document.getElementById('resultView').style.display = '';
        document.getElementById('resultDate').textContent = data.startTime ? new Date(data.startTime).toLocaleString('ko-KR') : dir;

        // 요약 카드
        const s = data.summary || {};
        document.getElementById('resultSummaryCards').innerHTML = `
            <div class="col-3"><div class="result-card bg-light"><div class="result-num">${s.total||0}</div><div class="result-label">전체</div></div></div>
            <div class="col-3"><div class="result-card" style="background:#d4edda"><div class="result-num text-success">${s.pass||0}</div><div class="result-label">PASS</div></div></div>
            <div class="col-3"><div class="result-card" style="background:var(--bs-body-bg)3cd"><div class="result-num text-warning">${s.warn||0}</div><div class="result-label">WARN</div></div></div>
            <div class="col-3"><div class="result-card" style="background:#f8d7da"><div class="result-num text-danger">${s.fail||0}</div><div class="result-label">FAIL</div></div></div>
        `;

        // 비교
        if (data.comparison) {
            document.getElementById('comparisonAlert').style.display = '';
            document.getElementById('comparisonText').textContent =
                `새 오류 ${data.comparison.newErrors||0}건, 해결 ${data.comparison.resolved||0}건, 변경없음 ${data.comparison.unchanged||0}건`;
        }

        // 시스템별 아코디언
        const acc = document.getElementById('resultAccordion');
        acc.innerHTML = '';
        (data.groups || []).forEach((group, gi) => {
            const passCount = group.results.filter(r => r.status === 'PASS').length;
            const total = group.results.length;
            const statusBadge = passCount === total
                ? `<span class="badge bg-success">${passCount}/${total} PASS</span>`
                : `<span class="badge bg-danger">${passCount}/${total}</span>`;

            let items = '';
            group.results.forEach(r => {
                const badge = r.status === 'PASS' ? 'result-badge-pass' : r.status === 'WARN' ? 'result-badge-warn' : 'result-badge-fail';
                const ssUrl = r.screenshot ? `${API_BASE}/screenshot/${dir}/${r.screenshot}` : '';
                const ssImg = ssUrl ? `<img src="${ssUrl}" class="result-screenshot" onclick="showScreenshot('${ssUrl}', '${esc(r.name)}')">` : '';
                const errors = (r.errors?.php?.length || 0) + (r.errors?.js?.length || 0) + (r.errors?.http?.length || 0);
                const errorBadge = errors > 0 ? `<span class="badge bg-danger ms-1">${errors} errors</span>` : '';
                items += `
                    <div class="result-item">
                        ${ssImg}
                        <div class="flex-grow-1">
                            <div class="result-name">${esc(r.name)} ${errorBadge}</div>
                            <div class="result-url">${esc(r.url || '')}</div>
                        </div>
                        <span class="${badge}">${r.status}</span>
                    </div>`;
            });

            acc.innerHTML += `
                <div class="accordion-item border-0 mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button ${gi > 0 ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#acc_${gi}">
                            <i class="bi ${group.icon || 'bi-folder'} me-2"></i> ${esc(group.name)} ${statusBadge}
                        </button>
                    </h2>
                    <div id="acc_${gi}" class="accordion-collapse collapse ${gi === 0 ? 'show' : ''}" data-bs-parent="#resultAccordion">
                        <div class="accordion-body p-2">${items}</div>
                    </div>
                </div>`;
        });

        loadHistory();
    } catch (e) {
        console.error('Results load failed:', e);
    }
}

function showScreenshot(url, title) {
    document.getElementById('screenshotModalImg').src = url;
    document.getElementById('screenshotModalTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('screenshotModal')).show();
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

// ── 히스토리 ──
async function loadHistory() {
    try {
        const res = await apiFetch(`${API_BASE}/history`);
        const data = await res.json();
        const list = document.getElementById('historyList');
        if (!data.runs || data.runs.length === 0) {
            list.innerHTML = '<p class="text-muted small">실행 이력이 없습니다.</p>';
            return;
        }
        list.innerHTML = '';
        data.runs.forEach(run => {
            const s = run.summary || {};
            const date = run.dir ? run.dir.replace(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/, '$1-$2-$3 $4:$5:$6') : '';
            const passPct = s.total ? Math.round((s.pass / s.total) * 100) : 0;
            const barColor = passPct === 100 ? 'bg-success' : passPct >= 80 ? 'bg-warning' : 'bg-danger';

            list.innerHTML += `
                <div class="history-item">
                    <div>
                        <div class="fw-bold small">${date}</div>
                        <div class="d-flex gap-1 mt-1">
                            <span class="badge bg-secondary">${s.total||0}</span>
                            <span class="badge bg-success">${s.pass||0}</span>
                            <span class="badge bg-warning text-dark">${s.warn||0}</span>
                            <span class="badge bg-danger">${s.fail||0}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <div class="progress" style="width:80px;height:6px">
                            <div class="progress-bar ${barColor}" style="width:${passPct}%"></div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="showResults('${run.dir}')">보기</button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteRun('${run.dir}')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>`;
        });
    } catch (e) {
        document.getElementById('historyList').innerHTML = '<p class="text-muted small">이력 로드 실패</p>';
    }
}

async function deleteRun(dir) {
    if (!confirm('이 결과를 삭제하시겠습니까?')) return;
    const res = await apiFetch(`${API_BASE}/delete/${dir}`, { method: 'DELETE' });
    if (res.ok) loadHistory();
    else alert('삭제 실패: HTTP ' + res.status);
}

// ── 리포트 다운로드 ──
function downloadReport(type) {
    if (!currentRunId) return;
    window.open(`${API_BASE}/download/${currentRunId}?type=${type}`, '_blank');
}

// [Track A4 - 2026-06-12] 실패 항목만 추려서 동일 옵션으로 재실행
async function rerunFailed() {
    if (!currentRunId) { alert('먼저 실행된 테스트 결과가 필요합니다.'); return; }
    try {
        const res = await apiFetch(`${API_BASE}/results/${currentRunId}`);
        const data = await res.json();

        const tests = {};
        let failCount = 0, missingId = 0;
        (data.groups || []).forEach(group => {
            const gid = group.id || group.key || group.name;
            (group.results || []).forEach(r => {
                if (r.status === 'FAIL') {
                    if (!r.id) { missingId++; return; }
                    if (!tests[gid]) tests[gid] = [];
                    tests[gid].push({ id: r.id, category: r.category || '' });
                    failCount++;
                }
            });
        });

        if (failCount === 0) {
            alert(missingId > 0
                ? '실패 항목에 테스트 ID 정보가 없어 재실행할 수 없습니다. (전체 재실행을 이용하세요)'
                : '재실행할 실패 항목이 없습니다. 🎉');
            return;
        }
        if (!confirm(`실패한 ${failCount}개 테스트만 재실행하시겠습니까?`)) return;

        const viewport = document.getElementById('optViewport').value.split('x');
        const options = {
            delay: parseInt(document.getElementById('optDelay').value),
            viewport: { width: parseInt(viewport[0]), height: parseInt(viewport[1]) },
            fullPage: document.querySelector('input[name="optScreenshot"]:checked').value === 'fullPage',
            generateReport: document.getElementById('optDocx').checked,
            compare: document.getElementById('optCompare').checked
        };

        document.getElementById('resultView').style.display = 'none';
        document.getElementById('runningView').style.display = '';
        runStartTime = Date.now();
        runTimer = setInterval(updateTimer, 1000);

        const runRes = await apiFetch(`${API_BASE}/run`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tests, options })
        });
        const runData = await runRes.json();
        if (!runData.runId) throw new Error(runData.error || 'Failed to start');
        currentRunId = runData.runId;
        addLog('info', `실패 항목 재실행 시작: ${runData.runId} (${failCount}개)`);
        connectSSE(runData.runId);
    } catch (e) {
        alert('재실행 실패: ' + e.message);
        clearInterval(runTimer);
    }
}

// ── 프리셋 ──
async function savePreset() {
    const name = document.getElementById('presetName').value.trim();
    if (!name) { alert('프리셋 이름을 입력하세요.'); return; }
    const viewport = document.getElementById('optViewport').value.split('x').map(Number);
    const preset = {
        name,
        tests: {},
        options: {
            delay: parseInt(document.getElementById('optDelay').value),
            viewport: { width: viewport[0], height: viewport[1] },
            fullPage: document.querySelector('input[name="optScreenshot"]:checked').value === 'fullPage',
            generateReport: document.getElementById('optDocx').checked,
            compare: document.getElementById('optCompare').checked
        }
    };
    selectedGroups.forEach(gid => {
        preset.tests[gid] = [];
        if (selectedTests[gid]) {
            Object.entries(selectedTests[gid]).forEach(([cat, items]) => {
                items.forEach(id => preset.tests[gid].push({ id, category: cat }));
            });
        }
    });
    try {
        const res = await apiFetch(`${API_BASE}/presets`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(preset)
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        alert('프리셋이 저장되었습니다.');
        document.getElementById('presetName').value = '';
    } catch (e) {
        alert('저장 실패: ' + e.message);
    }
}

async function loadPresets() {
    try {
        const res = await apiFetch(`${API_BASE}/presets`);
        const presets = await res.json();
        if (!presets.length) { alert('저장된 프리셋이 없습니다.'); return; }
        const names = presets.map((p, i) => `${i + 1}. ${p.name}`).join('\n');
        const choice = prompt(`프리셋 번호를 선택하세요:\n${names}`);
        if (!choice) return;
        const idx = parseInt(choice) - 1;
        if (idx < 0 || idx >= presets.length) return;

        const preset = presets[idx];
        // 그룹 선택 복원
        presetSelect('none');
        const presetGroups = preset.groups?.length ? preset.groups : Object.keys(preset.tests || {});
        presetGroups.forEach(gid => {
            const card = document.querySelector(`.group-card[data-group="${gid}"]`);
            if (card) {
                selectedGroups.add(gid);
                card.classList.add('selected');
            }
        });
        // 테스트 선택 복원
        selectedTests = {};
        if (preset.tests) {
            Object.entries(preset.tests).forEach(([gid, cats]) => {
                selectedTests[gid] = {};
                if (Array.isArray(cats)) {
                    cats.forEach(item => {
                        if (!selectedTests[gid][item.category]) selectedTests[gid][item.category] = new Set();
                        selectedTests[gid][item.category].add(item.id);
                    });
                } else {
                    // 이전 프리셋 형식도 호환해서 읽는다.
                    Object.entries(cats).forEach(([cat, items]) => {
                        selectedTests[gid][cat] = new Set(items);
                    });
                }
            });
        }
        // 옵션 복원
        if (preset.options) {
            if (preset.options.delay !== undefined) {
                document.getElementById('optDelay').value = preset.options.delay;
                document.getElementById('delayVal').textContent = preset.options.delay + '초';
            }
            if (preset.options.viewport) {
                const viewportValue = typeof preset.options.viewport === 'string'
                    ? preset.options.viewport
                    : `${preset.options.viewport.width}x${preset.options.viewport.height}`;
                document.getElementById('optViewport').value = viewportValue;
            }
            if (preset.options.fullPage !== undefined) {
                document.getElementById(preset.options.fullPage ? 'ssFullPage' : 'ssViewport').checked = true;
            }
            if (preset.options.generateReport !== undefined) document.getElementById('optDocx').checked = preset.options.generateReport;
            if (preset.options.compare !== undefined) document.getElementById('optCompare').checked = preset.options.compare;
        }
        updateCounts();
    } catch (e) {
        alert('불러오기 실패');
    }
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

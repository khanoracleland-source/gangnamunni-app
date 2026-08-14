<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 자동 매뉴얼 생성 페이지
 * Playwright 기반 웹 자동 캡처 + marker.js 피드백 + 워드 내보내기
 * Bootstrap 5.3 + 주간/야간 모드
 */

session_start();

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

// 권한 체크
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('auto_manual');

// Node.js 엔진 URL (서버에서 실행 중인 auto_manual_engine)
$engine_url = 'http://localhost:3100';

// 추가 CSS
$admin_extra_css = '
/* 캡처 카드 그리드 */
.capture-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.capture-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.capture-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.capture-card .card-img-top {
    height: 200px;
    object-fit: cover;
    cursor: pointer;
    background: var(--bs-tertiary-bg);
}
.capture-card .card-body {
    padding: 12px;
}
.capture-card .menu-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}
.capture-card .capture-time {
    font-size: 12px;
    color: var(--bs-secondary-color);
}
.capture-card .card-actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}
/* 프로그레스 */
.capture-progress {
    position: relative;
}
.capture-progress .progress-text {
    position: absolute;
    width: 100%;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    line-height: 24px;
}
/* 로그 영역 */
.capture-log {
    max-height: 200px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    background: var(--bs-tertiary-bg);
    border-radius: 6px;
    padding: 12px;
}
.capture-log .log-entry {
    margin-bottom: 2px;
}
.capture-log .log-success { color: #198754; }
.capture-log .log-error { color: #dc3545; }
.capture-log .log-info { color: #0d6efd; }
/* 이미지 확대 모달 */
.img-preview-modal .modal-dialog {
    max-width: 90vw;
}
.img-preview-modal .modal-body img {
    width: 100%;
    height: auto;
}
/* 어노테이션 컨테이너 */
#annotationContainer {
    position: relative;
    display: inline-block;
}
/* 설정 토글 */
.settings-collapse .form-label {
    font-size: 13px;
}
';

$admin_page_title = '자동 매뉴얼';
$admin_current = 'auto_manual';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-book"></i> 자동 매뉴얼 생성</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary" id="engineStatus">엔진 확인 중...</span>
    </div>
</div>

<!-- 알림 메시지 -->
<div id="alertArea"></div>

<!-- 설정 카드 -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-gear"></i> <strong>캡처 설정</strong></span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedSettings">
            <i class="bi bi-sliders"></i> 고급 설정
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="targetUrl" class="form-label fw-semibold">대상 URL <span class="text-danger">*</span></label>
                <input type="url" id="targetUrl" class="form-control form-control-sm"
                       placeholder="https://example.com/admin" value="https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php">
            </div>
            <div class="col-md-3">
                <label for="menuSelector" class="form-label fw-semibold">메뉴 셀렉터</label>
                <input type="text" id="menuSelector" class="form-control form-control-sm"
                       placeholder="nav a, .sidebar a" value=".admin-sidebar a[href$=&quot;.php&quot;]">
            </div>
            <div class="col-md-3">
                <label for="contentSelector" class="form-label fw-semibold">컨텐트 셀렉터</label>
                <input type="text" id="contentSelector" class="form-control form-control-sm"
                       placeholder=".content, main" value=".admin-main">
            </div>
        </div>

        <!-- 고급 설정 (접기/펼치기) -->
        <div class="collapse settings-collapse mt-3" id="advancedSettings">
            <hr class="my-2">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="captureDelay" class="form-label fw-semibold">캡처 대기 (ms)</label>
                    <input type="number" id="captureDelay" class="form-control form-control-sm"
                           value="2000" min="500" max="30000" step="500">
                </div>
                <div class="col-md-3">
                    <label for="viewportWidth" class="form-label fw-semibold">가로 해상도</label>
                    <input type="number" id="viewportWidth" class="form-control form-control-sm"
                           value="1920" min="800" max="3840">
                </div>
                <div class="col-md-3">
                    <label for="viewportHeight" class="form-label fw-semibold">세로 해상도</label>
                    <input type="number" id="viewportHeight" class="form-control form-control-sm"
                           value="1080" min="600" max="2160">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="checkbox" id="fullPage" class="form-check-input">
                        <label class="form-check-label" for="fullPage">전체 페이지 캡처</label>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" id="loginRequired" class="form-check-input" checked>
                        <label class="form-check-label" for="loginRequired">로그인 필요</label>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-1" id="loginFields" style="display:flex;">
                <div class="col-md-4">
                    <label for="loginUrl" class="form-label fw-semibold">로그인 URL</label>
                    <input type="url" id="loginUrl" class="form-control form-control-sm"
                           value="https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php">
                </div>
                <div class="col-md-4">
                    <label for="loginUser" class="form-label fw-semibold">아이디</label>
                    <input type="text" id="loginUser" class="form-control form-control-sm" value="admin">
                </div>
                <div class="col-md-4">
                    <label for="loginPass" class="form-label fw-semibold">비밀번호</label>
                    <input type="password" id="loginPass" class="form-control form-control-sm" value="admin123">
                </div>
            </div>
        </div>

        <!-- 실행 버튼 -->
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm" id="btnStart" onclick="startCapture()">
                <i class="bi bi-play-circle"></i> 매뉴얼 생성 시작
            </button>
            <button class="btn btn-danger btn-sm" id="btnStop" onclick="stopCapture()" style="display:none;">
                <i class="bi bi-stop-circle"></i> 중지
            </button>
            <button class="btn btn-outline-secondary btn-sm" id="btnClear" onclick="clearResults()">
                <i class="bi bi-trash"></i> 결과 초기화
            </button>
        </div>
    </div>
</div>

<!-- 진행 상황 -->
<div class="card mb-3" id="progressCard" style="display:none;">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="fw-semibold" id="progressLabel">캡처 준비 중...</small>
            <small class="text-body-secondary" id="progressCount">0/0</small>
        </div>
        <div class="capture-progress">
            <div class="progress" style="height:24px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar"
                     role="progressbar" style="width:0%"></div>
            </div>
            <span class="progress-text" id="progressPercent">0%</span>
        </div>
    </div>
</div>

<!-- 캡처 로그 -->
<div class="card mb-3" id="logCard" style="display:none;">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-terminal"></i> <strong>실행 로그</strong></span>
        <button class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('captureLog').innerHTML=''">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="card-body py-2">
        <div class="capture-log" id="captureLog"></div>
    </div>
</div>

<!-- 캡처 결과 그리드 -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> 캡처 결과 (<span id="resultCount">0</span>건)</h6>
    <div class="d-flex gap-2" id="exportButtons" style="display:none;">
        <button class="btn btn-sm btn-outline-success" onclick="exportWord()">
            <i class="bi bi-file-earmark-word"></i> 워드 다운로드
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="exportPdf()">
            <i class="bi bi-file-earmark-pdf"></i> PDF 다운로드
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="exportZip()">
            <i class="bi bi-file-earmark-zip"></i> 이미지 ZIP
        </button>
    </div>
</div>

<div class="capture-grid" id="captureGrid">
    <div class="empty-state" id="emptyState">
        <i class="bi bi-camera"></i>
        <p>캡처된 스크린샷이 없습니다.<br>위에서 URL을 입력하고 [매뉴얼 생성 시작]을 클릭하세요.</p>
    </div>
</div>

<!-- 이미지 확대 모달 -->
<div class="modal fade img-preview-modal" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="imageModalTitle">스크린샷</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <img id="imageModalImg" src="" alt="스크린샷">
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-sm btn-warning" onclick="openAnnotation()">
                    <i class="bi bi-pencil-square"></i> 피드백 추가
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 어노테이션 모달 -->
<div class="modal fade" id="annotationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 피드백 / 수정사항 표시</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="annotationContainer">
                    <img id="annotationImg" src="" alt="어노테이션" style="max-width:100%;">
                </div>
                <div class="mt-3">
                    <label for="clientComment" class="form-label fw-semibold">코멘트</label>
                    <textarea id="clientComment" class="form-control form-control-sm" rows="3"
                              placeholder="수정사항이나 피드백을 텍스트로 입력하세요..."></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-sm btn-primary" onclick="saveAnnotation()">
                    <i class="bi bi-check-lg"></i> 저장
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">취소</button>
            </div>
        </div>
    </div>
</div>

<!-- markerjs2 CDN -->
<script src="https://unpkg.com/markerjs2@2.32.7/markerjs2.js"></script>

<?php
// 추가 JavaScript
$admin_extra_js = <<<'JSEOF'

// === 설정 ===
const ENGINE_URL = window.location.protocol + '//' + window.location.host + '/auto-manual-api';
let currentSessionId = null;
let eventSource = null;
let captures = [];
let currentAnnotationIndex = -1;

// === 엔진 상태 확인 ===
async function checkEngine() {
    const badge = document.getElementById('engineStatus');
    try {
        const res = await fetch(ENGINE_URL + '/api/health', { signal: AbortSignal.timeout(3000) });
        if (res.ok) {
            badge.className = 'badge bg-success';
            badge.textContent = '엔진 연결됨';
            return true;
        }
    } catch (e) {}
    badge.className = 'badge bg-danger';
    badge.textContent = '엔진 미연결';
    showAlert('danger', '자동 매뉴얼 엔진이 실행되지 않았습니다. 서버에서 auto_manual_engine을 시작해주세요.');
    return false;
}

// === 로그인 필드 토글 ===
document.getElementById('loginRequired').addEventListener('change', function() {
    document.getElementById('loginFields').style.display = this.checked ? 'flex' : 'none';
});

// === 캡처 시작 ===
async function startCapture() {
    const url = document.getElementById('targetUrl').value.trim();
    if (!url) { showAlert('warning', 'URL을 입력해주세요.'); return; }

    if (!await checkEngine()) return;

    const payload = {
        targetUrl: url,
        menuSelector: document.getElementById('menuSelector').value.trim() || 'nav a',
        contentSelector: document.getElementById('contentSelector').value.trim() || 'main',
        captureDelay: parseInt(document.getElementById('captureDelay').value) || 2000,
        fullPage: document.getElementById('fullPage').checked,
        viewport: {
            width: parseInt(document.getElementById('viewportWidth').value) || 1920,
            height: parseInt(document.getElementById('viewportHeight').value) || 1080
        }
    };

    if (document.getElementById('loginRequired').checked) {
        payload.loginRequired = true;
        payload.loginUrl = document.getElementById('loginUrl').value.trim();
        payload.loginCredentials = {
            username: document.getElementById('loginUser').value.trim(),
            password: document.getElementById('loginPass').value.trim()
        };
    }

    // UI 상태 변경
    document.getElementById('btnStart').style.display = 'none';
    document.getElementById('btnStop').style.display = 'inline-block';
    document.getElementById('progressCard').style.display = 'block';
    document.getElementById('logCard').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';

    addLog('info', '캡처 시작 요청 중...');

    try {
        const res = await fetch(ENGINE_URL + '/api/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.sessionId) {
            currentSessionId = data.sessionId;
            addLog('success', '세션 생성: ' + currentSessionId);
            connectSSE(currentSessionId);
        } else {
            throw new Error(data.error || '세션 생성 실패');
        }
    } catch (e) {
        addLog('error', '시작 실패: ' + e.message);
        resetUI();
    }
}

// === SSE 연결 ===
function connectSSE(sessionId) {
    if (eventSource) eventSource.close();

    eventSource = new EventSource(ENGINE_URL + '/api/progress/' + sessionId);

    eventSource.addEventListener('progress', function(e) {
        const data = JSON.parse(e.data);
        updateProgress(data.current, data.total, data.menuName);
    });

    eventSource.addEventListener('capture', function(e) {
        const data = JSON.parse(e.data);
        addCapture(data);
        addLog('success', '[' + data.index + '/' + data.total + '] ' + data.menuName + ' 캡처 완료');
    });

    eventSource.addEventListener('error_capture', function(e) {
        const data = JSON.parse(e.data);
        addLog('error', '[' + data.index + '/' + data.total + '] ' + data.menuName + ' 실패: ' + data.error);
    });

    eventSource.addEventListener('complete', function(e) {
        const data = JSON.parse(e.data);
        addLog('success', '전체 캡처 완료! 성공: ' + data.success + ', 실패: ' + data.failed);
        finishCapture();
    });

    eventSource.addEventListener('log', function(e) {
        const data = JSON.parse(e.data);
        addLog(data.level || 'info', data.message);
    });

    eventSource.onerror = function() {
        addLog('error', 'SSE 연결 끊김');
        eventSource.close();
    };
}

// === 캡처 중지 ===
async function stopCapture() {
    if (!currentSessionId) return;
    try {
        await fetch(ENGINE_URL + '/api/stop/' + currentSessionId, { method: 'POST' });
        addLog('info', '캡처 중지 요청됨');
    } catch (e) {
        addLog('error', '중지 실패: ' + e.message);
    }
    finishCapture();
}

// === UI 업데이트 ===
function updateProgress(current, total, menuName) {
    const pct = total > 0 ? Math.round((current / total) * 100) : 0;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPercent').textContent = pct + '%';
    document.getElementById('progressCount').textContent = current + '/' + total;
    document.getElementById('progressLabel').textContent = '캡처 중: ' + (menuName || '...');
}

function addCapture(data) {
    captures.push(data);
    const idx = captures.length - 1;
    const grid = document.getElementById('captureGrid');

    const statusBadge = data.status === 'success'
        ? '<span class="badge bg-success">성공</span>'
        : '<span class="badge bg-danger">실패</span>';

    const imgSrc = data.screenshotUrl || '';
    const card = document.createElement('div');
    card.className = 'capture-card';
    card.id = 'capture-' + idx;
    card.innerHTML =
        '<img class="card-img-top" src="' + imgSrc + '" alt="' + (data.menuName || '') + '"' +
        ' onclick="previewImage(' + idx + ')" onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22320%22 height=%22200%22><rect fill=%22%23dee2e6%22 width=%22320%22 height=%22200%22/><text fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22>No Image</text></svg>\'">' +
        '<div class="card-body">' +
            '<div class="d-flex justify-content-between">' +
                '<span class="menu-name">' + (data.menuName || 'Unknown') + '</span>' +
                statusBadge +
            '</div>' +
            '<div class="capture-time">' + (data.capturedAt || '') + '</div>' +
            '<div class="capture-time text-truncate" title="' + (data.menuUrl || '') + '">' + (data.menuUrl || '') + '</div>' +
            (data.errorMessage ? '<div class="text-danger small mt-1">' + data.errorMessage + '</div>' : '') +
            '<div class="card-actions">' +
                '<button class="btn btn-sm btn-outline-warning" onclick="openAnnotationFor(' + idx + ')" title="피드백">' +
                    '<i class="bi bi-pencil-square"></i>' +
                '</button>' +
                '<button class="btn btn-sm btn-outline-info" onclick="recapture(' + idx + ')" title="재캡처">' +
                    '<i class="bi bi-arrow-clockwise"></i>' +
                '</button>' +
                '<button class="btn btn-sm btn-outline-danger" onclick="removeCapture(' + idx + ')" title="삭제">' +
                    '<i class="bi bi-trash"></i>' +
                '</button>' +
            '</div>' +
        '</div>';

    grid.appendChild(card);
    document.getElementById('resultCount').textContent = captures.length;
    document.getElementById('exportButtons').style.display = captures.length > 0 ? 'flex' : 'none';
}

function addLog(level, message) {
    const log = document.getElementById('captureLog');
    const time = new Date().toLocaleTimeString('ko-KR');
    const cls = level === 'error' ? 'log-error' : level === 'success' ? 'log-success' : 'log-info';
    log.innerHTML += '<div class="log-entry ' + cls + '">[' + time + '] ' + message + '</div>';
    log.scrollTop = log.scrollHeight;
}

function showAlert(type, msg) {
    document.getElementById('alertArea').innerHTML =
        '<div class="alert alert-' + type + ' alert-dismissible fade show py-2" role="alert">' +
        msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function finishCapture() {
    document.getElementById('btnStart').style.display = 'inline-block';
    document.getElementById('btnStop').style.display = 'none';
    if (eventSource) { eventSource.close(); eventSource = null; }
    const bar = document.getElementById('progressBar');
    bar.classList.remove('progress-bar-animated');
    bar.classList.add('bg-success');
}

function resetUI() {
    document.getElementById('btnStart').style.display = 'inline-block';
    document.getElementById('btnStop').style.display = 'none';
    document.getElementById('progressCard').style.display = 'none';
}

function clearResults() {
    if (captures.length > 0 && !confirm('캡처 결과를 모두 삭제하시겠습니까?')) return;
    captures = [];
    document.getElementById('captureGrid').innerHTML =
        '<div class="empty-state" id="emptyState"><i class="bi bi-camera"></i>' +
        '<p>캡처된 스크린샷이 없습니다.<br>위에서 URL을 입력하고 [매뉴얼 생성 시작]을 클릭하세요.</p></div>';
    document.getElementById('resultCount').textContent = '0';
    document.getElementById('exportButtons').style.display = 'none';
    document.getElementById('progressCard').style.display = 'none';
    document.getElementById('logCard').style.display = 'none';
    currentSessionId = null;
}

// === 이미지 미리보기 ===
function previewImage(idx) {
    const cap = captures[idx];
    if (!cap) return;
    document.getElementById('imageModalTitle').textContent = cap.menuName || '스크린샷';
    const img = document.getElementById('imageModalImg');
    img.src = cap.annotatedUrl || cap.screenshotUrl || '';
    currentAnnotationIndex = idx;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

// === 어노테이션 (marker.js) ===
function openAnnotation() {
    bootstrap.Modal.getInstance(document.getElementById('imageModal')).hide();
    openAnnotationFor(currentAnnotationIndex);
}

function openAnnotationFor(idx) {
    const cap = captures[idx];
    if (!cap) return;
    currentAnnotationIndex = idx;

    const img = document.getElementById('annotationImg');
    img.src = cap.annotatedUrl || cap.screenshotUrl || '';
    document.getElementById('clientComment').value = cap.comment || '';

    const modal = new bootstrap.Modal(document.getElementById('annotationModal'));
    modal.show();

    // marker.js 초기화 (모달이 완전히 열린 후)
    document.getElementById('annotationModal').addEventListener('shown.bs.modal', function initMarker() {
        const markerArea = new markerjs2.MarkerArea(img);
        markerArea.availableMarkerTypes = [
            markerjs2.ArrowMarker,
            markerjs2.FrameMarker,
            markerjs2.TextMarker,
            markerjs2.FreehandMarker,
            markerjs2.EllipseMarker,
            markerjs2.CalloutMarker,
            markerjs2.HighlightMarker
        ];
        markerArea.renderAtNaturalSize = true;
        markerArea.renderImageType = 'image/png';

        markerArea.addEventListener('render', function(event) {
            img.src = event.dataUrl;
            captures[currentAnnotationIndex].annotatedDataUrl = event.dataUrl;
            addLog('success', captures[currentAnnotationIndex].menuName + ' 어노테이션 저장됨');
        });

        markerArea.show();
        document.getElementById('annotationModal').removeEventListener('shown.bs.modal', initMarker);
    }, { once: true });
}

function saveAnnotation() {
    if (currentAnnotationIndex < 0) return;
    const comment = document.getElementById('clientComment').value.trim();
    captures[currentAnnotationIndex].comment = comment;

    // 서버에 저장
    const cap = captures[currentAnnotationIndex];
    if (cap.annotatedDataUrl || comment) {
        fetch(ENGINE_URL + '/api/annotation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sessionId: currentSessionId,
                captureIndex: currentAnnotationIndex,
                annotatedImage: cap.annotatedDataUrl || null,
                comment: comment
            })
        }).then(res => res.json()).then(data => {
            if (data.annotatedUrl) {
                captures[currentAnnotationIndex].annotatedUrl = data.annotatedUrl;
            }
            addLog('success', '피드백 저장 완료: ' + cap.menuName);
        }).catch(e => {
            addLog('error', '피드백 저장 실패: ' + e.message);
        });
    }

    bootstrap.Modal.getInstance(document.getElementById('annotationModal')).hide();
    showAlert('success', '피드백이 저장되었습니다.');
}

// === 재캡처 ===
async function recapture(idx) {
    const cap = captures[idx];
    if (!cap || !currentSessionId) return;
    addLog('info', cap.menuName + ' 재캡처 요청...');
    try {
        const res = await fetch(ENGINE_URL + '/api/recapture', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sessionId: currentSessionId, captureIndex: idx, menuUrl: cap.menuUrl })
        });
        const data = await res.json();
        if (data.screenshotUrl) {
            captures[idx].screenshotUrl = data.screenshotUrl;
            captures[idx].status = 'success';
            captures[idx].errorMessage = null;
            const card = document.getElementById('capture-' + idx);
            if (card) card.querySelector('.card-img-top').src = data.screenshotUrl;
            addLog('success', cap.menuName + ' 재캡처 완료');
        }
    } catch (e) {
        addLog('error', '재캡처 실패: ' + e.message);
    }
}

// === 삭제 ===
function removeCapture(idx) {
    if (!confirm('이 캡처를 삭제하시겠습니까?')) return;
    const card = document.getElementById('capture-' + idx);
    if (card) card.remove();
    captures[idx] = null;
    const count = captures.filter(c => c !== null).length;
    document.getElementById('resultCount').textContent = count;
    if (count === 0) document.getElementById('exportButtons').style.display = 'none';
}

// === 내보내기 ===
async function exportWord() {
    if (!currentSessionId) { showAlert('warning', '캡처 세션이 없습니다.'); return; }
    addLog('info', '워드 문서 생성 중...');
    try {
        const res = await fetch(ENGINE_URL + '/api/export/' + currentSessionId + '?format=docx', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ captures: captures.filter(c => c !== null) })
        });
        if (res.ok) {
            const blob = await res.blob();
            downloadBlob(blob, 'auto-manual-' + currentSessionId + '.docx');
            addLog('success', '워드 문서 다운로드 완료');
        } else {
            throw new Error('Export failed');
        }
    } catch (e) {
        addLog('error', '워드 내보내기 실패: ' + e.message);
    }
}

async function exportPdf() {
    if (!currentSessionId) { showAlert('warning', '캡처 세션이 없습니다.'); return; }
    addLog('info', 'PDF 생성 중...');
    try {
        const res = await fetch(ENGINE_URL + '/api/export/' + currentSessionId + '?format=pdf', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ captures: captures.filter(c => c !== null) })
        });
        if (res.ok) {
            const blob = await res.blob();
            downloadBlob(blob, 'auto-manual-' + currentSessionId + '.pdf');
            addLog('success', 'PDF 다운로드 완료');
        } else {
            throw new Error('Export failed');
        }
    } catch (e) {
        addLog('error', 'PDF 내보내기 실패: ' + e.message);
    }
}

async function exportZip() {
    if (!currentSessionId) { showAlert('warning', '캡처 세션이 없습니다.'); return; }
    addLog('info', 'ZIP 생성 중...');
    try {
        const res = await fetch(ENGINE_URL + '/api/export/' + currentSessionId + '?format=zip');
        if (res.ok) {
            const blob = await res.blob();
            downloadBlob(blob, 'auto-manual-' + currentSessionId + '.zip');
            addLog('success', 'ZIP 다운로드 완료');
        } else {
            throw new Error('Export failed');
        }
    } catch (e) {
        addLog('error', 'ZIP 내보내기 실패: ' + e.message);
    }
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// === 초기화 ===
checkEngine();

JSEOF;

include __DIR__ . '/includes/admin_footer.php';
?>

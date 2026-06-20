<?php
session_start();
/**
 * admin_screenshot_book.php - 스크린샷 매뉴얼 자동 생성
 *
 * 원클릭으로 35개 관리 페이지를 캡처하여 HTML/DOCX 매뉴얼 생성
 * - Playwright 기반 자동 캡처 + 에러 감지
 * - SSE 실시간 진행률
 * - HTML 미리보기 + DOCX/PDF 다운로드
 *
 * @since 2026-02-26
 * @version 1.0.0
 */

$admin_page_title = '스크린샷 매뉴얼';
$admin_current = 'screenshot_book';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';

$apiBase = '/auto-manual-api';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-camera me-2"></i>스크린샷 매뉴얼</h4>
        <small class="text-muted">35개 관리 페이지를 자동 캡처하여 클라이언트용 매뉴얼을 생성합니다</small>
    </div>
    <div class="d-flex gap-2">
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
                <div class="fs-2 fw-bold" id="statTotal">35</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-success">
            <div class="card-body py-3">
                <div class="text-success small">캡처 완료</div>
                <div class="fs-2 fw-bold text-success" id="statPass">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-warning">
            <div class="card-body py-3">
                <div class="text-warning small">경고</div>
                <div class="fs-2 fw-bold text-warning" id="statWarn">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-danger">
            <div class="card-body py-3">
                <div class="text-danger small">실패</div>
                <div class="fs-2 fw-bold text-danger" id="statFail">-</div>
            </div>
        </div>
    </div>
</div>

<!-- 생성 패널 -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-play-circle me-1"></i> 매뉴얼 생성</span>
        <span class="badge bg-secondary" id="runStatus">대기</span>
    </div>
    <div class="card-body">
        <!-- 생성 버튼 + 옵션 -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <button class="btn btn-primary btn-lg" id="btnGenerate" onclick="startGeneration()">
                <i class="bi bi-camera-fill me-2"></i>매뉴얼 생성 시작
            </button>
            <button class="btn btn-outline-danger btn-sm d-none" id="btnStop" onclick="stopGeneration()">
                <i class="bi bi-stop-circle me-1"></i>중지
            </button>
        </div>

        <!-- 진행률 -->
        <div class="d-none" id="progressArea">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted" id="progressLabel">준비 중...</small>
                <small class="fw-bold" id="progressPercent">0%</small>
            </div>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar"
                     role="progressbar" style="width: 0%"></div>
            </div>

            <!-- 캡처 로그 (최근 3개 표시) -->
            <div id="captureLog" style="max-height:200px; overflow-y:auto; font-size:12px;" class="border rounded p-2 bg-body-tertiary">
            </div>
        </div>

        <!-- 완료 후 결과 -->
        <div class="d-none" id="resultArea">
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <strong>매뉴얼 생성 완료!</strong>
                    <span id="resultSummary"></span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-primary" id="btnPreview" href="#" target="_blank">
                    <i class="bi bi-eye me-1"></i>HTML 미리보기
                </a>
                <a class="btn btn-outline-danger" id="btnDownloadPdf" href="#">
                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF 다운로드
                </a>
                <a class="btn btn-outline-success" id="btnDownloadDocx" href="#">
                    <i class="bi bi-file-earmark-word me-1"></i>DOCX 다운로드
                </a>
                <a class="btn btn-outline-secondary" id="btnDownloadJson" href="#" download>
                    <i class="bi bi-file-earmark-code me-1"></i>JSON 결과
                </a>
            </div>
        </div>
    </div>
</div>

<!-- HTML 미리보기 (iframe) -->
<div class="card mb-4 d-none" id="previewCard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-eye me-1"></i> 미리보기</span>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="togglePreviewSize()">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
            <a class="btn btn-sm btn-outline-primary ms-1" id="btnPreviewExternal" href="#" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> 새 탭
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <iframe id="previewFrame" style="width:100%; height:600px; border:none;"></iframe>
    </div>
</div>

<!-- 이전 생성 이력 -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-clock-history me-1"></i> 생성 이력
    </div>
    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary" id="bulkActions">
        <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll(this.checked)">
            <label class="form-check-label small" for="selectAll">전체</label>
        </div>
        <button class="btn btn-sm btn-outline-secondary py-0" onclick="toggleSelectAll(false)">
            <i class="bi bi-x-square"></i> 선택 해제
        </button>
        <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteSelected()">
            <i class="bi bi-trash"></i> 선택 삭제 (<span id="selectedCount">0</span>)
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th>생성 일시</th>
                        <th>페이지</th>
                        <th>결과</th>
                        <th>다운로드</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr><td colspan="6" class="text-center text-muted py-3">로딩 중...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const API = '<?php echo $apiBase; ?>';
let currentRunId = null;
let sseConnection = null;

// === 매뉴얼 생성 시작 ===
async function startGeneration() {
    const btn = document.getElementById('btnGenerate');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>시작 중...';

    document.getElementById('progressArea').classList.remove('d-none');
    document.getElementById('resultArea').classList.add('d-none');
    document.getElementById('previewCard').classList.add('d-none');
    document.getElementById('captureLog').innerHTML = '';
    document.getElementById('runStatus').className = 'badge bg-primary';
    document.getElementById('runStatus').textContent = '실행 중';
    document.getElementById('btnStop').classList.remove('d-none');

    try {
        console.log('[Manual] Starting generation...');
        const res = await fetch(`${API}/api/book/generate`, { method: 'POST', headers: { 'Content-Type': 'application/json' } });
        if (res.status === 409) {
            const err = await res.json();
            console.warn('[Manual] Already running:', err.runId);
            alert('이미 실행 중입니다: ' + (err.runId || ''));
            resetUI();
            return;
        }
        const data = await res.json();
        currentRunId = data.runId;
        console.log('[Manual] Generation started, runId:', data.runId);
        connectSSE(data.runId);
    } catch (e) {
        console.error('[Manual] Generation failed:', e);
        alert('생성 시작 실패: ' + e.message);
        resetUI();
    }
}

// === SSE 연결 ===
function connectSSE(runId) {
    if (sseConnection) sseConnection.close();
    sseConnection = new EventSource(`${API}/api/book/progress/${runId}`);
    console.log(`[Manual] SSE connected: ${runId}`);

    sseConnection.addEventListener('login', (e) => {
        console.log('[Manual] Login success');
        addLog('✅ 로그인 성공', 'success');
    });

    sseConnection.addEventListener('progress', (e) => {
        const d = JSON.parse(e.data);
        console.log(`[Manual] Progress: ${d.current}/${d.total} (${d.percent}%)`);
        updateProgress(d.current, d.total, d.percent);
    });

    sseConnection.addEventListener('capture', (e) => {
        const d = JSON.parse(e.data);
        console.log(`[Manual] Capture: ${d.label} ${d.status.toUpperCase()} ${d.elapsed || ''}ms`);
        const icon = d.status === 'pass' ? '✅' : d.status === 'warn' ? '⚠️' : '❌';
        addLog(`${icon} [${d.current}/${d.total}] ${d.label} (${d.status.toUpperCase()})`, d.status === 'fail' ? 'danger' : '');
    });

    sseConnection.addEventListener('log', (e) => {
        const d = JSON.parse(e.data);
        console.log('[Manual] Log:', d.message);
        addLog(d.message);
    });

    sseConnection.addEventListener('error_event', (e) => {
        const d = JSON.parse(e.data);
        console.error('[Manual] Error event:', d.message);
        addLog('❌ ' + d.message, 'danger');
    });

    sseConnection.addEventListener('complete', (e) => {
        const d = JSON.parse(e.data);
        console.log('[Manual] Complete event received');
        sseConnection.close();
        sseConnection = null;
        onComplete(d);
    });

    sseConnection.onerror = (e) => {
        console.error('[Manual] SSE error:', e);
        // SSE 연결 끊김 시 3초 후 UI 리셋
        if (sseConnection) {
            sseConnection.close();
            sseConnection = null;
        }
        addLog('⚠️ 서버 연결이 끊어졌습니다. UI를 초기화합니다...', 'danger');
        setTimeout(() => {
            document.getElementById('progressArea').classList.add('d-none');
            resetUI();
            loadHistory(); // 이력 새로고침으로 완료 여부 확인
        }, 3000);
    };
}

// === 진행률 업데이트 ===
function updateProgress(current, total, percent) {
    document.getElementById('progressLabel').textContent = `캡처 중... (${current} / ${total})`;
    document.getElementById('progressPercent').textContent = `${percent}%`;
    document.getElementById('progressBar').style.width = `${percent}%`;
}

// === 로그 추가 ===
function addLog(message, type = '') {
    const log = document.getElementById('captureLog');
    const div = document.createElement('div');
    div.className = type === 'danger' ? 'text-danger' : type === 'success' ? 'text-success' : '';
    div.textContent = `[${new Date().toLocaleTimeString('ko-KR', {hour12:false})}] ${message}`;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}

// === 완료 처리 ===
function onComplete(data) {
    console.group('[Manual] ✅ Complete');
    console.log('data:', data);

    const s = data.summary || {};
    document.getElementById('statPass').textContent = s.pass || 0;
    document.getElementById('statWarn').textContent = s.warn || 0;
    document.getElementById('statFail').textContent = s.fail || 0;

    document.getElementById('runStatus').className = 'badge bg-success';
    document.getElementById('runStatus').textContent = '완료';
    document.getElementById('btnStop').classList.add('d-none');

    // 프로그레스바 완료 상태 전환
    const progressBar = document.getElementById('progressBar');
    progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
    progressBar.classList.add('bg-success');
    progressBar.style.width = '100%';
    document.getElementById('progressLabel').textContent = '캡처 완료!';
    document.getElementById('progressPercent').textContent = '100%';

    // 1.5초 후 프로그레스 영역 숨기고 결과 표시
    setTimeout(() => {
        document.getElementById('progressArea').classList.add('d-none');
        // 결과 영역 표시
        document.getElementById('resultArea').classList.remove('d-none');
        console.log('[Manual] UI switched to result area');
    }, 1500);

    document.getElementById('resultSummary').textContent =
        ` — ${s.total || 35}페이지 캡처, ${s.pass || 0} PASS / ${s.warn || 0} WARN / ${s.fail || 0} FAIL`;

    const dir = data.resultDir || '';
    if (dir) {
        document.getElementById('btnPreview').href = `${API}/api/book/preview/${dir}/`;
        document.getElementById('btnDownloadDocx').href = `${API}/api/book/export-word/${dir}`;
        document.getElementById('btnDownloadPdf').href = `${API}/api/book/export-pdf/${dir}`;
        document.getElementById('btnDownloadJson').href = `${API}/api/book/download/${dir}/results.json`;

        // 미리보기 iframe
        document.getElementById('previewCard').classList.remove('d-none');
        document.getElementById('previewFrame').src = `${API}/api/book/preview/${dir}/`;
        document.getElementById('btnPreviewExternal').href = `${API}/api/book/preview/${dir}/`;
        console.log('[Manual] Preview:', `${API}/api/book/preview/${dir}/`);
        console.log('[Manual] DOCX:', `${API}/api/book/export-word/${dir}`);
        console.log('[Manual] PDF:', `${API}/api/book/export-pdf/${dir}`);
    }

    // 매뉴얼 URL (영구 링크)
    if (data.manualUrl) {
        addLog('📖 매뉴얼 URL: ' + data.manualUrl, 'success');
    }

    console.groupEnd();
    resetButton();
    loadHistory();
}

// === 중지 ===
async function stopGeneration() {
    if (!currentRunId) return;
    try {
        await fetch(`${API}/api/book/stop/${currentRunId}`, { method: 'POST' });
        addLog('⏹ 중지 요청됨', 'danger');
    } catch (e) {}
}

// === 이력 로드 ===
async function loadHistory() {
    console.log('[Manual] Loading history...');
    try {
        const res = await fetch(`${API}/api/book/history`);
        const history = await res.json();
        console.log(`[Manual] History: ${history.length} entries`);
        renderHistory(history);
    } catch (e) {
        console.error('[Manual] History load failed:', e);
        document.getElementById('historyBody').innerHTML =
            '<tr><td colspan="6" class="text-center text-muted py-3">이력을 불러올 수 없습니다</td></tr>';
    }
}

function renderHistory(history) {
    const tbody = document.getElementById('historyBody');
    if (!history || history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">생성 이력이 없습니다</td></tr>';
        return;
    }
    tbody.innerHTML = history.map(h => {
        const s = h.summary || {};
        const statusBadges = [
            s.pass ? `<span class="badge bg-success">${s.pass} PASS</span>` : '',
            s.warn ? `<span class="badge bg-warning text-dark">${s.warn} WARN</span>` : '',
            s.fail ? `<span class="badge bg-danger">${s.fail} FAIL</span>` : ''
        ].filter(Boolean).join(' ');

        return `<tr>
            <td><input type="checkbox" class="form-check-input row-check" value="${h.dir}" onchange="updateSelectedCount()"></td>
            <td class="text-nowrap">${h.date}</td>
            <td>${h.screenshotCount || '-'}장</td>
            <td>${statusBadges || '-'}</td>
            <td class="text-nowrap">
                ${h.hasBook ? `<a href="${API}/api/book/preview/${h.dir}/" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-1" title="HTML 미리보기"><i class="bi bi-eye"></i></a>` : ''}
                ${h.hasDocx ? `<a href="${API}/api/book/export-word/${h.dir}" class="btn btn-sm btn-outline-success py-0 px-1" title="Word 다운로드"><i class="bi bi-file-word"></i></a>` : ''}
                ${h.hasJson ? `<a href="${API}/api/book/export-pdf/${h.dir}" class="btn btn-sm btn-outline-danger py-0 px-1" title="PDF 다운로드"><i class="bi bi-file-pdf"></i></a>` : ''}
                ${h.hasJson ? `<a href="${API}/api/book/download/${h.dir}/results.json" class="btn btn-sm btn-outline-secondary py-0 px-1" title="JSON 결과 데이터"><i class="bi bi-file-code"></i></a>` : ''}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteHistory('${h.dir}')" title="이력 삭제">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

// === 이력 삭제 ===
async function deleteHistory(dir) {
    if (!confirm('이 기록을 삭제하시겠습니까?')) return;
    try {
        await fetch(`${API}/api/book/history/${dir}`, { method: 'DELETE' });
        loadHistory();
    } catch (e) {
        alert('삭제 실패: ' + e.message);
    }
}

// === 미리보기 크기 토글 ===
function togglePreviewSize() {
    const frame = document.getElementById('previewFrame');
    frame.style.height = frame.style.height === '600px' ? '1200px' : '600px';
}

// === UI 리셋 ===
function resetButton() {
    const btn = document.getElementById('btnGenerate');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-camera-fill me-2"></i>매뉴얼 생성 시작';
    document.getElementById('btnStop').classList.add('d-none');
}

function resetUI() {
    resetButton();
    document.getElementById('runStatus').className = 'badge bg-secondary';
    document.getElementById('runStatus').textContent = '대기';
}

// === 전체 선택/해제 ===
function toggleSelectAll(checked) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
    document.getElementById('selectAll').checked = checked;
    updateSelectedCount();
}

// === 선택 개수 업데이트 ===
function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-check:checked');
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('selectAll').checked =
        checked.length > 0 && checked.length === document.querySelectorAll('.row-check').length;
}

// === 선택 삭제 ===
async function deleteSelected() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) { alert('삭제할 항목을 선택해주세요.'); return; }
    if (!confirm(checked.length + '개 항목을 삭제하시겠습니까?')) return;

    let success = 0;
    for (const cb of checked) {
        try {
            const res = await fetch(`${API}/api/book/history/${cb.value}`, { method: 'DELETE' });
            if (res.ok) success++;
        } catch (e) {}
    }
    alert(success + '개 삭제 완료');
    loadHistory();
}

// === 초기 로드 ===
document.addEventListener('DOMContentLoaded', loadHistory);
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

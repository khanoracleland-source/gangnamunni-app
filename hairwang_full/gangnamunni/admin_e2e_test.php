<?php
/**
 * E2E 테스트 대시보드 — Playwright 기반
 * 체크박스로 테스트 선택 → 실행 → 실시간 결과 + DB 저장
 */
session_start();
$admin_page_title = 'E2E 테스트';
$admin_current = 'e2e_test';
$admin_page_description = 'Playwright로 사용자/관리자 동작을 자동 테스트합니다. 에러 시 스크린샷과 수정 링크가 제공됩니다.';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// 테스트 설정 로드
$config_path = __DIR__ . '/auto_manual_engine/e2e/e2e-config.json';
$tests = [];
if (file_exists($config_path)) {
    $cfg = json_decode(file_get_contents($config_path), true);
    $tests = $cfg['tests'] ?? [];
}

// 최근 실행 이력
$history = [];
$hq = mysqli_query($connect, "SELECT * FROM gangnamunni_e2e_runs ORDER BY started_at DESC LIMIT 10");
if ($hq) { while ($r = mysqli_fetch_assoc($hq)) $history[] = $r; }

// 최근 결과
$latest_results = [];
if (!empty($history)) {
    $latest_run = $history[0]['run_id'];
    $rq = mysqli_query($connect, "SELECT * FROM gangnamunni_e2e_results WHERE run_id='" . mysqli_real_escape_string($connect, $latest_run) . "' ORDER BY test_id");
    if ($rq) { while ($r = mysqli_fetch_assoc($rq)) $latest_results[$r['test_id']] = $r; }
}

$page_title = 'E2E 테스트';
include __DIR__ . '/includes/admin_header.php';
?>

<style>
.test-item { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid var(--bs-border-color-translucent); }
.test-item:last-child { border:none; }
.test-id { font-family:'Fira Code',monospace; font-size:12px; color:var(--bs-primary); min-width:30px; }
.test-name { flex:1; font-size:13px; }
.status-pass { color:#22c55e; font-weight:700; }
.status-fail { color:#ef4444; font-weight:700; }
.status-skip { color:#94a3b8; }
.status-running { color:#3b82f6; }
.result-row td { font-size:13px; vertical-align:middle; }
.ss-thumb { width:60px; height:40px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid var(--bs-border-color); }
.run-btn { min-width:160px; }
.log-box { background:#1a1a2e; color:#e5e5e5; font-family:'Fira Code',monospace; font-size:12px; height:200px; overflow-y:auto; padding:12px; border-radius:8px; white-space:pre-wrap; word-break:break-all; }
</style>

<div class="container-fluid py-3">

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold" id="stat-total"><?php echo count($tests); ?></div>
                <div class="small text-muted">전체 테스트</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm" style="border-left:3px solid #22c55e !important;">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success" id="stat-pass">-</div>
                <div class="small text-muted">PASS</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm" style="border-left:3px solid #ef4444 !important;">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-danger" id="stat-fail">-</div>
                <div class="small text-muted">FAIL</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-muted" id="stat-time">-</div>
                <div class="small text-muted">소요 시간</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
<!-- LEFT: 테스트 선택 -->
<div class="col-lg-5">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-user">사용자 (A)</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-admin">관리자 (B)</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-api">API (C)</button></li>
            </ul>
        </div>
        <div class="card-body" style="max-height:450px;overflow-y:auto;">
            <div class="tab-content">
                <?php
                $cats = ['user' => 'tab-user', 'admin' => 'tab-admin', 'api' => 'tab-api'];
                $first = true;
                foreach ($cats as $cat => $tabId):
                    $catTests = array_filter($tests, function($t) use ($cat) { return $t['category'] === $cat; });
                ?>
                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="<?php echo $tabId; ?>">
                    <div class="d-flex gap-2 mb-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleAll('<?php echo $cat; ?>',true)">전체 선택</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleAll('<?php echo $cat; ?>',false)">전체 해제</button>
                    </div>
                    <?php foreach ($catTests as $t):
                        $prev = $latest_results[$t['id']] ?? null;
                        $badge = $prev ? ($prev['status'] === 'PASS' ? '<span class="badge bg-success">PASS</span>' : '<span class="badge bg-danger">FAIL</span>') : '';
                    ?>
                    <div class="test-item">
                        <input type="checkbox" class="form-check-input test-cb" data-cat="<?php echo $cat; ?>" data-id="<?php echo $t['id']; ?>" checked>
                        <span class="test-id"><?php echo $t['id']; ?></span>
                        <span class="test-name"><?php echo htmlspecialchars($t['name']); ?></span>
                        <?php echo $badge; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex gap-2 align-items-center">
            <button class="btn btn-primary run-btn" id="runBtn" onclick="runTests()">
                <i class="bi bi-play-fill me-1"></i>테스트 실행
            </button>
            <button class="btn btn-outline-danger btn-sm" id="abortBtn" onclick="abortTests()" style="display:none;">
                <i class="bi bi-stop-fill"></i> 중단
            </button>
            <div class="progress flex-fill" style="height:6px;display:none;" id="progressWrap">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width:0%"></div>
            </div>
        </div>
    </div>
</div>

<!-- RIGHT: 결과 -->
<div class="col-lg-7">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between">
            <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>테스트 결과</h6>
            <span class="text-muted small" id="resultRunId"></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr><th style="width:40px;">ID</th><th>테스트</th><th style="width:60px;">상태</th><th style="width:50px;">시간</th><th style="width:50px;">캡처</th><th style="width:50px;">수정</th></tr>
                </thead>
                <tbody id="resultBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">테스트를 실행하면 결과가 여기 표시됩니다</td></tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- 로그 -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-transparent"><h6 class="mb-0"><i class="bi bi-terminal me-1"></i>실행 로그</h6></div>
        <div class="card-body p-0">
            <div class="log-box" id="logBox">준비됨. 테스트를 선택하고 실행 버튼을 누르세요.</div>
        </div>
    </div>

    <!-- 에러 상세 (FAIL 클릭 시) -->
    <div class="card border-0 shadow-sm mt-3" id="errorDetail" style="display:none;">
        <div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="bi bi-exclamation-triangle me-1"></i>에러 상세</h6></div>
        <div class="card-body">
            <div id="errorContent"></div>
        </div>
    </div>
</div>
</div>

<!-- 실행 이력 -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent"><h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>실행 이력</h6></div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Run ID</th><th>시작</th><th>PASS</th><th>FAIL</th><th>상태</th><th>소요</th><th style="width:40px;"></th></tr></thead>
            <tbody id="historyBody">
                <?php if (empty($history)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">실행 이력 없음</td></tr>
                <?php else: foreach ($history as $h): ?>
                <tr id="hist-<?php echo htmlspecialchars($h['run_id']); ?>">
                    <td><code class="small"><?php echo htmlspecialchars(substr($h['run_id'], 0, 16)); ?></code></td>
                    <td class="small"><?php echo $h['started_at']; ?></td>
                    <td><span class="badge bg-success"><?php echo $h['passed']; ?></span></td>
                    <td><span class="badge bg-danger"><?php echo $h['failed']; ?></span></td>
                    <td><span class="badge bg-<?php echo $h['status']==='completed'?'primary':'secondary'; ?>"><?php echo $h['status']; ?></span></td>
                    <td class="small"><?php echo round($h['duration_ms']/1000, 1); ?>s</td>
                    <td><button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteRun('<?php echo htmlspecialchars($h['run_id']); ?>')" title="삭제"><i class="bi bi-trash3"></i></button></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<!-- 스크린샷 모달 -->
<div class="modal fade" id="ssModal"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title" id="ssModalTitle">스크린샷</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center"><img id="ssModalImg" src="" style="max-width:100%;border-radius:8px;"></div>
</div></div></div>

<script>
var currentRunId = null;
var pollTimer = null;

function toggleAll(cat, checked) {
    document.querySelectorAll('.test-cb[data-cat="'+cat+'"]').forEach(function(cb) { cb.checked = checked; });
}

function runTests() {
    var selected = [];
    document.querySelectorAll('.test-cb:checked').forEach(function(cb) { selected.push(cb.dataset.id); });
    if (selected.length === 0) { alert('테스트를 선택하세요'); return; }

    currentRunId = 'run_' + Date.now();
    document.getElementById('runBtn').disabled = true;
    document.getElementById('runBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>실행 중...';
    document.getElementById('abortBtn').style.display = '';
    document.getElementById('progressWrap').style.display = '';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('resultRunId').textContent = currentRunId;
    var logBox = document.getElementById('logBox');
    logBox.textContent = '';
    appendLog('╔══════════════════════════════════════╗');
    appendLog('║  E2E 테스트 시작 — ' + selected.length + '개 항목');
    appendLog('╚══════════════════════════════════════╝');
    appendLog('');
    document.getElementById('resultBody').innerHTML = '';
    document.getElementById('errorDetail').style.display = 'none';

    // 서버에 실행 요청
    fetch('api_e2e_test.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=run&run_id=' + encodeURIComponent(currentRunId) + '&tests=' + encodeURIComponent(JSON.stringify(selected))
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            appendLog('러너 시작됨');
            pollTimer = setInterval(pollResults, 2000);
        } else {
            appendLog('ERROR: ' + (d.error || 'unknown'));
            resetUI();
        }
    });
}

function pollResults() {
    if (!currentRunId) return;
    fetch('api_e2e_test.php?action=results&run_id=' + encodeURIComponent(currentRunId))
    .then(function(r) { return r.json(); })
    .then(function(d) {
        console.log('[E2E Poll]', d);
        if (!d.success) { appendLog('폴링 실패: ' + JSON.stringify(d)); return; }
        var run = d.run || {};
        var results = d.results || [];

        if (!run.total_tests) { appendLog('대기 중... (러너 시작 중)'); return; }

        // 통계 업데이트
        var p = parseInt(run.passed) || 0;
        var f = parseInt(run.failed) || 0;
        var s = parseInt(run.skipped) || 0;
        var dur = parseInt(run.duration_ms) || 0;
        document.getElementById('stat-pass').textContent = p;
        document.getElementById('stat-fail').textContent = f;
        document.getElementById('stat-time').textContent = (dur / 1000).toFixed(1) + 's';

        // 진행바
        var total = parseInt(run.total_tests) || 1;
        var done = p + f + s;
        document.getElementById('progressBar').style.width = Math.round(done / total * 100) + '%';

        // 결과 테이블
        var tbody = document.getElementById('resultBody');
        tbody.innerHTML = '';
        results.forEach(function(r) {
            var ms = parseInt(r.duration_ms) || 0;
            var statusCls = r.status === 'PASS' ? 'status-pass' : r.status === 'FAIL' ? 'status-fail' : r.status === 'RUNNING' ? 'status-running' : 'status-skip';
            var statusIcon = r.status === 'PASS' ? '✅' : r.status === 'FAIL' ? '❌' : r.status === 'RUNNING' ? '⏳' : '⏭️';
            var ss = r.screenshot_path ? '<img src="' + r.screenshot_path + '" class="ss-thumb" onclick="showSS(\'' + r.screenshot_path + '\',\'' + r.test_id + '\')">' : '-';
            var fix = r.fix_link ? '<a href="' + r.fix_link + '" target="_blank" class="btn btn-sm btn-outline-warning" title="수정"><i class="bi bi-wrench"></i></a>' : '';
            var errBtn = r.status === 'FAIL' && r.error_msg ? ' <a href="#" onclick="showError(\'' + encodeURIComponent(r.error_msg) + '\',\'' + encodeURIComponent(r.fix_suggestion||'') + '\');return false;" class="text-danger" title="에러 보기"><i class="bi bi-info-circle"></i></a>' : '';
            tbody.innerHTML += '<tr class="result-row"><td><code>' + r.test_id + '</code></td><td>' + r.test_name + errBtn + '</td><td class="' + statusCls + '">' + statusIcon + '</td><td>' + (ms/1000).toFixed(1) + 's</td><td>' + ss + '</td><td>' + fix + '</td></tr>';
        });

        // 새 결과 로그 — 각 테스트별 한 줄씩
        if (results.length > 0) {
            var lastDone = results.filter(function(r) { return r.status !== 'RUNNING'; });
            // 새로 완료된 항목만 로그
            lastDone.forEach(function(r) {
                var key = 'logged_' + r.test_id;
                if (!window[key]) {
                    var icon = r.status === 'PASS' ? '✅' : r.status === 'FAIL' ? '❌' : '⏭️';
                    var ms = parseInt(r.duration_ms) || 0;
                    appendLog(icon + ' [' + r.test_id + '] ' + r.test_name + ' — ' + r.status + ' (' + (ms/1000).toFixed(1) + 's)');
                    if (r.status === 'FAIL' && r.error_msg) {
                        appendLog('   └ 에러: ' + r.error_msg);
                    }
                    window[key] = true;
                }
            });
            appendLog('');
            appendLog('━━━ 진행: ' + lastDone.length + '/' + total + ' (' + p + ' PASS, ' + f + ' FAIL + ' + s + ' SKIP) ━━━');
        }

        // 완료 체크
        if (run.status === 'completed' || run.status === 'aborted') {
            clearInterval(pollTimer);
            appendLog('완료: ' + p + ' PASS, ' + f + ' FAIL (' + (dur/1000).toFixed(1) + 's)');
            resetUI();
        }
    })
    .catch(function(e) { appendLog('폴링 에러: ' + e.message); });
}

function abortTests() {
    if (!currentRunId) return;
    fetch('api_e2e_test.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=abort&run_id=' + encodeURIComponent(currentRunId)
    });
    appendLog('중단 요청됨');
}

function showSS(path, id) {
    document.getElementById('ssModalTitle').textContent = id + ' 스크린샷';
    document.getElementById('ssModalImg').src = path;
    new bootstrap.Modal(document.getElementById('ssModal')).show();
}

function showError(msg, suggestion) {
    document.getElementById('errorDetail').style.display = '';
    document.getElementById('errorContent').innerHTML = '<strong>에러:</strong> ' + decodeURIComponent(msg) + '<br><br><strong>수정 제안:</strong> ' + decodeURIComponent(suggestion);
}

function appendLog(msg) {
    var box = document.getElementById('logBox');
    box.textContent += '[' + new Date().toLocaleTimeString() + '] ' + msg + '\n';
    box.scrollTop = box.scrollHeight;
}

function deleteRun(runId) {
    if (!confirm('이 실행 이력을 삭제하시겠습니까?')) return;
    fetch('api_e2e_test.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&run_id=' + encodeURIComponent(runId)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            var row = document.getElementById('hist-' + runId);
            if (row) row.remove();
        }
    });
}

function resetUI() {
    document.getElementById('runBtn').disabled = false;
    document.getElementById('runBtn').innerHTML = '<i class="bi bi-play-fill me-1"></i>테스트 실행';
    document.getElementById('abortBtn').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

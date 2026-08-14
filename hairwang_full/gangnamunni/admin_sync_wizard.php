<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 동기화 마법사 - 3단계 (대상 선택 → 옵션 → 실행+결과)
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php'); exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('sync');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


// 마지막 동기화 상태 조회
function getLastSync($connect, $type) {
    $r = mysqli_query($connect, "SELECT * FROM gangnamunni_sync_log WHERE sync_type = '".mysqli_real_escape_string($connect, $type)."' ORDER BY id DESC LIMIT 1");
    return $r ? mysqli_fetch_assoc($r) : null;
}

// 테이블 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50),
    direction VARCHAR(20),
    records_processed INT DEFAULT 0,
    records_created INT DEFAULT 0,
    records_updated INT DEFAULT 0,
    records_skipped INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    dry_run TINYINT DEFAULT 0,
    log_detail TEXT,
    started_at DATETIME,
    completed_at DATETIME,
    status ENUM('running','completed','failed') DEFAULT 'running',
    INDEX(sync_type), INDEX(started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 동기화 AJAX 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_sync') {
    header('Content-Type: application/json');
    try {
        $syncTypes = json_decode($_POST['sync_types'] ?? '[]', true);
        $dryRun = intval($_POST['dry_run'] ?? 0);
        $forceOverwrite = intval($_POST['force_overwrite'] ?? 0);

        if (empty($syncTypes)) throw new Exception('동기화 대상을 선택해주세요.');

        $results = [];
        foreach ($syncTypes as $type) {
            $result = runSyncTask($connect, $type, $dryRun, $forceOverwrite);
            $results[] = $result;
        }

        echo json_encode(['success' => true, 'results' => $results]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function runSyncTask($connect, $type, $dryRun, $forceOverwrite) {
    $esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };
    $startTime = date('Y-m-d H:i:s');
    $stats = ['type' => $type, 'processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'logs' => []];

    // 로그 기록 시작
    $logId = 0;
    if (!$dryRun) {
        mysqli_query($connect, "INSERT INTO gangnamunni_sync_log (sync_type, direction, dry_run, started_at, status) VALUES ('{$esc($type)}', '', {$dryRun}, '{$startTime}', 'running')");
        $logId = mysqli_insert_id($connect);
    }

    switch ($type) {
        case 'hms_to_gangnamunni':
            $stats['direction'] = 'HMS → 서울미녀';
            // users(hospital) → gangnamunni_hospitals
            $r = mysqli_query($connect, "SELECT id, first_name, last_name, email, phone, address1, city_id, hospital_type_id FROM users WHERE type = 1 AND status = 1");
            if ($r) {
                while ($row = mysqli_fetch_assoc($r)) {
                    $stats['processed']++;
                    $name = trim($row['first_name'] . ' ' . $row['last_name']);
                    $hmsId = intval($row['id']);
                    $exists = mysqli_fetch_assoc(mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals WHERE hms_hospital_id = {$hmsId}"));
                    if ($exists) {
                        if ($forceOverwrite && !$dryRun) {
                            $n = $esc($name); $e = $esc($row['email']); $p = $esc($row['phone'] ?? ''); $a = $esc($row['address1'] ?? '');
                            mysqli_query($connect, "UPDATE gangnamunni_hospitals SET name='{$n}', email='{$e}', phone='{$p}', address='{$a}' WHERE hms_hospital_id={$hmsId}");
                            $stats['updated']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } else {
                        if (!$dryRun) {
                            $n = $esc($name); $e = $esc($row['email']); $p = $esc($row['phone'] ?? ''); $a = $esc($row['address1'] ?? '');
                            mysqli_query($connect, "INSERT INTO gangnamunni_hospitals (hms_hospital_id, name, email, phone, address, status) VALUES ({$hmsId}, '{$n}', '{$e}', '{$p}', '{$a}', 'active')");
                        }
                        $stats['created']++;
                    }
                    $stats['logs'][] = ($exists ? ($forceOverwrite ? 'UPDATE' : 'SKIP') : 'CREATE') . ": {$name} (HMS #{$hmsId})";
                }
            }
            break;

        case 'gangnamunni_to_hms':
            $stats['direction'] = '서울미녀 → HMS';
            // gangnamunni_bookings → appointments
            $r = mysqli_query($connect, "SELECT b.*, h.hms_hospital_id FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id=h.id WHERE b.status='pending'");
            if ($r) {
                while ($row = mysqli_fetch_assoc($r)) {
                    $stats['processed']++;
                    if (!$row['hms_hospital_id']) { $stats['skipped']++; continue; }
                    $stats['logs'][] = "BOOKING: " . $row['patient_name'] . " → HMS hospital #{$row['hms_hospital_id']}";
                    $stats['created']++;
                }
            }
            break;

        case 'members_bidirectional':
            $stats['direction'] = '회원 양방향';
            // g5_member ↔ users
            $r = mysqli_query($connect, "SELECT m.mb_no, m.mb_id, m.mb_name, m.mb_email, ms.hms_user_id FROM g5_member m LEFT JOIN member_sync ms ON m.mb_no=ms.mb_no");
            if ($r) {
                while ($row = mysqli_fetch_assoc($r)) {
                    $stats['processed']++;
                    if ($row['hms_user_id']) { $stats['skipped']++; }
                    else { $stats['created']++; $stats['logs'][] = "NEW_LINK: {$row['mb_id']} ({$row['mb_email']})"; }
                }
            }
            break;

        case 'categories':
            $stats['direction'] = '카테고리 양방향';
            // hospital_type ↔ gangnamunni_categories
            $r = mysqli_query($connect, "SELECT ht.id, ht.name, gc.id as gc_id FROM hospital_type ht LEFT JOIN gangnamunni_categories gc ON gc.hms_hospital_type_id=ht.id");
            if ($r) {
                while ($row = mysqli_fetch_assoc($r)) {
                    $stats['processed']++;
                    if ($row['gc_id']) { $stats['skipped']++; }
                    else { $stats['created']++; $stats['logs'][] = "NEW_CAT: {$row['name']} (HMS #{$row['id']})"; }
                }
            }
            break;
    }

    // 로그 기록 완료
    if (!$dryRun && $logId) {
        $detail = $esc(implode("\n", array_slice($stats['logs'], 0, 100)));
        $completedAt = date('Y-m-d H:i:s');
        mysqli_query($connect, "UPDATE gangnamunni_sync_log SET direction='{$esc($stats['direction'])}', records_processed={$stats['processed']}, records_created={$stats['created']}, records_updated={$stats['updated']}, records_skipped={$stats['skipped']}, records_failed={$stats['failed']}, log_detail='{$detail}', completed_at='{$completedAt}', status='completed' WHERE id={$logId}");
    }

    return $stats;
}

// 최근 동기화 이력
$recentLogs = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_sync_log ORDER BY id DESC LIMIT 10");
if ($r) while ($row = mysqli_fetch_assoc($r)) $recentLogs[] = $row;

$admin_page_title = '동기화 마법사';
$admin_current = 'sync';
$admin_extra_css = '<link href="includes/wizard_base.css" rel="stylesheet">';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.sync-card { height: 100%; position: relative; }
.sync-card .sync-icon { font-size: 36px; margin-bottom: 8px; }
.sync-card .sync-arrow { font-size: 14px; color: var(--bs-secondary-color); margin-top: 4px; }
.sync-card .sync-last { font-size: 11px; color: var(--bs-secondary-color); margin-top: 6px; }
.opt-card { padding: 16px; }
.opt-card .form-check-label { font-weight: 600; }
.sync-result { border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
.sync-result .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.sync-result .result-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.sync-result .result-stat { background: var(--bs-tertiary-bg); padding: 6px 14px; border-radius: 8px; text-align: center; }
.sync-result .result-stat .num { font-size: 20px; font-weight: 700; }
.sync-result .result-stat .label { font-size: 10px; color: var(--bs-secondary-color); }
.sync-log { font-family: 'Consolas', monospace; font-size: 11px; max-height: 200px; overflow-y: auto; background: var(--bs-tertiary-bg); padding: 10px; border-radius: 8px; margin-top: 8px; }
.sync-log .log-line { padding: 1px 0; }
.sync-log .log-create { color: #28a745; }
.sync-log .log-update { color: #0d6efd; }
.sync-log .log-skip { color: #6c757d; }
.history-table { font-size: 12px; }
</style>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">대상</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">옵션</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">실행</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 동기화 대상 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-arrow-repeat"></i> 동기화 대상 선택 <span class="text-muted small">(복수 선택)</span></h5>
        <div class="row g-2" id="syncGrid">
            <div class="col-6">
                <div class="wz-card sync-card" data-value="hms_to_gangnamunni">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="sync-icon">&#127973;</div>
                    <div class="wz-card-name">HMS → 서울미녀</div>
                    <div class="sync-arrow">병원/의사/진료과</div>
                    <div class="wz-card-desc">HMS 병원 데이터를 서울미녀로 동기화</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card sync-card" data-value="gangnamunni_to_hms">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="sync-icon">&#128203;</div>
                    <div class="wz-card-name">서울미녀 → HMS</div>
                    <div class="sync-arrow">상담/예약</div>
                    <div class="wz-card-desc">서울미녀 예약/상담을 HMS로 동기화</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card sync-card" data-value="members_bidirectional">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="sync-icon">&#128101;</div>
                    <div class="wz-card-name">회원 양방향</div>
                    <div class="sync-arrow">Gnuboard &#8596; HMS</div>
                    <div class="wz-card-desc">회원 정보 양방향 동기화</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card sync-card" data-value="categories">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="sync-icon">&#127991;</div>
                    <div class="wz-card-name">카테고리</div>
                    <div class="sync-arrow">hospital_type &#8596; categories</div>
                    <div class="wz-card-desc">시술 카테고리 양방향 동기화</div>
                </div>
            </div>
        </div>

        <?php if (!empty($recentLogs)): ?>
        <h6 class="mt-4 mb-2 small fw-bold text-muted"><i class="bi bi-clock-history"></i> 최근 이력</h6>
        <div class="table-responsive">
            <table class="table table-sm history-table">
                <thead><tr><th>유형</th><th>방향</th><th>처리</th><th>생성</th><th>업데이트</th><th>일시</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['sync_type']) ?></td>
                    <td><?= htmlspecialchars($log['direction']) ?></td>
                    <td><?= $log['records_processed'] ?></td>
                    <td class="text-success"><?= $log['records_created'] ?></td>
                    <td class="text-primary"><?= $log['records_updated'] ?></td>
                    <td><?= $log['completed_at'] ? date('m/d H:i', strtotime($log['completed_at'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="wz-actions">
            <a href="dashboard.php" class="btn-wz-prev"><i class="bi bi-arrow-left"></i> 대시보드</a>
            <button class="btn-wz-next" onclick="wz.next()">옵션 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 옵션 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-sliders"></i> 동기화 옵션</h5>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="optDryRun" checked>
                    <label class="form-check-label fw-bold" for="optDryRun">시뮬레이션 (Dry Run)</label>
                    <div class="small text-muted">실제 데이터를 변경하지 않고 결과만 미리 확인합니다</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="optForce">
                    <label class="form-check-label fw-bold" for="optForce">강제 덮어쓰기</label>
                    <div class="small text-muted">이미 존재하는 데이터도 최신 값으로 업데이트합니다</div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i> <strong>시뮬레이션 모드</strong>를 먼저 실행하여 변경 내용을 확인한 후 실제 동기화를 진행하는 것을 권장합니다.
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">실행 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 실행 + 결과 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-play-circle"></i> 실행</h5>

        <div id="syncSummary" class="wz-summary mb-3"></div>

        <div class="wz-actions mb-3">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnSync" onclick="runSync()"><i class="bi bi-play-fill"></i> 동기화 실행</button>
        </div>

        <div id="syncResults" style="display:none"></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(3, { storageKey: 'sync_wz' });
let selectedSyncs = [];

// 복수 선택
const syncSelect = initCardSelect(document.getElementById('syncGrid'), {
    multi: true,
    onChange: (vals) => { selectedSyncs = vals; }
});

// 유효성
wz.setValidator(1, () => {
    if (selectedSyncs.length === 0) { alert('동기화 대상을 1개 이상 선택해주세요.'); return false; }
    return true;
});

const syncNames = {
    hms_to_gangnamunni: 'HMS → 서울미녀 (병원/의사)',
    gangnamunni_to_hms: '서울미녀 → HMS (상담/예약)',
    members_bidirectional: '회원 양방향 동기화',
    categories: '카테고리 양방향 동기화'
};

wz.onStepChange = (step) => {
    if (step === 3) {
        const dryRun = document.getElementById('optDryRun').checked;
        const force = document.getElementById('optForce').checked;
        document.getElementById('syncSummary').innerHTML = `
            <h6><i class="bi bi-arrow-repeat"></i> 동기화 요약</h6>
            <div class="mb-2"><strong>대상:</strong> ${selectedSyncs.map(s => wzEsc(syncNames[s] || s)).join(', ')}</div>
            <div class="row">
                <div class="col-6"><strong>모드:</strong> ${dryRun ? '<span class="badge bg-info">시뮬레이션</span>' : '<span class="badge bg-success">실제 실행</span>'}</div>
                <div class="col-6"><strong>덮어쓰기:</strong> ${force ? '<span class="badge bg-warning">강제</span>' : '아니오'}</div>
            </div>
        `;
    }
};

async function runSync() {
    const dryRun = document.getElementById('optDryRun').checked;
    if (!dryRun && !confirm('실제 데이터가 변경됩니다. 계속하시겠습니까?')) return;

    const btn = document.getElementById('btnSync');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 실행 중...';

    const fd = new FormData();
    fd.append('action', 'run_sync');
    fd.append('sync_types', JSON.stringify(selectedSyncs));
    fd.append('dry_run', dryRun ? 1 : 0);
    fd.append('force_overwrite', document.getElementById('optForce').checked ? 1 : 0);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        const resultsDiv = document.getElementById('syncResults');
        resultsDiv.style.display = 'block';

        if (data.success) {
            resultsDiv.innerHTML = data.results.map(r => {
                const logHtml = r.logs.length > 0 ? `<div class="sync-log">${r.logs.map(l => {
                    let cls = 'log-skip';
                    if (l.startsWith('CREATE')) cls = 'log-create';
                    if (l.startsWith('UPDATE')) cls = 'log-update';
                    return '<div class="log-line ' + cls + '">' + wzEsc(l) + '</div>';
                }).join('')}</div>` : '';

                return `<div class="sync-result">
                    <div class="result-header">
                        <div>
                            <strong>${wzEsc(syncNames[r.type] || r.type)}</strong>
                            ${dryRun ? '<span class="badge bg-info ms-2">시뮬레이션</span>' : '<span class="badge bg-success ms-2">완료</span>'}
                        </div>
                        <span class="small text-muted">${r.direction || ''}</span>
                    </div>
                    <div class="result-stats">
                        <div class="result-stat"><div class="num">${r.processed}</div><div class="label">처리</div></div>
                        <div class="result-stat"><div class="num" style="color:#28a745">${r.created}</div><div class="label">생성</div></div>
                        <div class="result-stat"><div class="num" style="color:#0d6efd">${r.updated}</div><div class="label">업데이트</div></div>
                        <div class="result-stat"><div class="num">${r.skipped}</div><div class="label">건너뜀</div></div>
                    </div>
                    ${logHtml}
                </div>`;
            }).join('');

            if (dryRun) {
                resultsDiv.innerHTML += `<div class="text-center mt-3">
                    <button class="btn btn-success" onclick="document.getElementById('optDryRun').checked=false; wz.goTo(3); runSync();">
                        <i class="bi bi-play-fill"></i> 실제 실행하기
                    </button>
                </div>`;
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> 다시 실행';
        } else {
            alert(data.error || '동기화 실패');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> 동기화 실행';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> 동기화 실행';
    }
}
</script>

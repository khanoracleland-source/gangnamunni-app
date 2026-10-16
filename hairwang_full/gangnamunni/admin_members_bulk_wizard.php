<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 회원 일괄 처리 마법사 - 3단계 (대상 선택 → 작업 선택 → 확인+실행)
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php'); exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('members');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 통계 조회
$totalMembers = 0;
$r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM g5_member");
if ($r) $totalMembers = mysqli_fetch_assoc($r)['cnt'];

$levelCounts = [];
$r = mysqli_query($connect, "SELECT mb_level, COUNT(*) as cnt FROM g5_member GROUP BY mb_level ORDER BY mb_level");
if ($r) while ($row = mysqli_fetch_assoc($r)) $levelCounts[$row['mb_level']] = $row['cnt'];

// AJAX: 필터된 회원 조회
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'filter_members') {
    header('Content-Type: application/json');
    $conditions = ['1=1'];
    $level = $_POST['level'] ?? '';
    $status = $_POST['status'] ?? '';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $keyword = trim($_POST['keyword'] ?? '');

    if ($level !== '') $conditions[] = "mb_level = " . intval($level);
    if ($status === 'active') $conditions[] = "mb_intercept_date = ''";
    if ($status === 'blocked') $conditions[] = "mb_intercept_date != ''";
    if ($dateFrom) $conditions[] = "mb_datetime >= '" . $esc($dateFrom) . "'";
    if ($dateTo) $conditions[] = "mb_datetime <= '" . $esc($dateTo) . " 23:59:59'";
    if ($keyword) $conditions[] = "(mb_id LIKE '%" . $esc($keyword) . "%' OR mb_name LIKE '%" . $esc($keyword) . "%' OR mb_email LIKE '%" . $esc($keyword) . "%')";

    $where = implode(' AND ', $conditions);
    $members = [];
    $r = mysqli_query($connect, "SELECT mb_no, mb_id, mb_name, mb_nick, mb_email, mb_level, mb_intercept_date, mb_datetime FROM g5_member WHERE {$where} ORDER BY mb_datetime DESC LIMIT 200");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $members[] = $row;

    $countR = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM g5_member WHERE {$where}");
    $totalFiltered = $countR ? mysqli_fetch_assoc($countR)['cnt'] : 0;

    echo json_encode(['success' => true, 'members' => $members, 'total' => $totalFiltered]);
    exit;
}

// AJAX: 일괄 작업 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_action') {
    header('Content-Type: application/json');
    try {
        $mbNos = json_decode($_POST['mb_nos'] ?? '[]', true);
        $operation = $_POST['operation'] ?? '';
        $opValue = $_POST['op_value'] ?? '';

        if (empty($mbNos)) throw new Exception('대상 회원을 선택해주세요.');

        $ids = array_map('intval', $mbNos);
        $idList = implode(',', $ids);
        $affected = 0;

        switch ($operation) {
            case 'change_level':
                $newLevel = intval($opValue);
                $r = mysqli_query($connect, "UPDATE g5_member SET mb_level = {$newLevel} WHERE mb_no IN ({$idList})");
                $affected = mysqli_affected_rows($connect);
                break;
            case 'block':
                $date = date('Y-m-d');
                $r = mysqli_query($connect, "UPDATE g5_member SET mb_intercept_date = '{$date}' WHERE mb_no IN ({$idList})");
                $affected = mysqli_affected_rows($connect);
                break;
            case 'unblock':
                $r = mysqli_query($connect, "UPDATE g5_member SET mb_intercept_date = '' WHERE mb_no IN ({$idList})");
                $affected = mysqli_affected_rows($connect);
                break;
            case 'add_memo':
                $memo = $esc($opValue);
                $date = date('Y-m-d H:i');
                $r = mysqli_query($connect, "UPDATE g5_member SET mb_memo = CONCAT(mb_memo, '\n[{$date}] {$memo}') WHERE mb_no IN ({$idList})");
                $affected = mysqli_affected_rows($connect);
                break;
            case 'delete':
                // 소프트 삭제 (차단 + 메모)
                $date = date('Y-m-d');
                $r = mysqli_query($connect, "UPDATE g5_member SET mb_intercept_date = '{$date}', mb_memo = CONCAT(mb_memo, '\n[일괄삭제] {$date}') WHERE mb_no IN ({$idList})");
                $affected = mysqli_affected_rows($connect);
                break;
            default:
                throw new Exception('알 수 없는 작업: ' . $operation);
        }

        echo json_encode(['success' => true, 'affected' => $affected, 'message' => "{$affected}명 처리 완료"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$admin_page_title = '회원 일괄 처리';
$admin_current = 'members';
$admin_extra_css = '<link href="includes/wizard_base.css" rel="stylesheet">';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.filter-card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.06); border-radius: 12px; }
.stat-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: var(--bs-tertiary-bg); }
.member-table { font-size: 13px; }
.member-table th { font-weight: 600; background: var(--bs-tertiary-bg); position: sticky; top: 0; }
.member-table-wrap { max-height: 350px; overflow-y: auto; border: 1px solid var(--bs-border-color); border-radius: 10px; }
.op-card { padding: 20px; height: 100%; }
.op-card .op-icon { font-size: 36px; margin-bottom: 8px; }
.op-card.danger { border-color: var(--bs-danger) !important; }
.op-card.danger.selected { background: rgba(220,53,69,.05) !important; border-color: var(--bs-danger) !important; }
.op-config { display: none; margin-top: 12px; padding: 12px; background: var(--bs-tertiary-bg); border-radius: 10px; }
.op-config.show { display: block; }
.result-stats { display: flex; gap: 16px; justify-content: center; margin-top: 16px; }
.result-stat { text-align: center; }
.result-stat .num { font-size: 28px; font-weight: 700; }
.result-stat .label { font-size: 12px; color: var(--bs-secondary-color); }
</style>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">대상 선택</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">작업 선택</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">실행</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 대상 회원 선택 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-people"></i> 대상 회원 선택</h5>

        <div class="card filter-card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold">등급</label>
                        <select class="form-select form-select-sm" id="fLevel">
                            <option value="">전체</option>
                            <?php foreach ($levelCounts as $lv => $cnt): ?>
                            <option value="<?= $lv ?>">레벨 <?= $lv ?> (<?= $cnt ?>명)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold">상태</label>
                        <select class="form-select form-select-sm" id="fStatus">
                            <option value="">전체</option>
                            <option value="active">활성</option>
                            <option value="blocked">차단</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold">가입일 (부터)</label>
                        <input type="date" class="form-control form-control-sm" id="fDateFrom">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold">가입일 (까지)</label>
                        <input type="date" class="form-control form-control-sm" id="fDateTo">
                    </div>
                    <div class="col-9">
                        <input type="text" class="form-control form-control-sm" id="fKeyword" placeholder="ID, 이름, 이메일 검색...">
                    </div>
                    <div class="col-3">
                        <button class="btn btn-sm btn-primary w-100" onclick="filterMembers()"><i class="bi bi-search"></i> 검색</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <span class="stat-badge"><i class="bi bi-people-fill"></i> 전체 <?= number_format($totalMembers) ?>명</span>
                <span class="stat-badge" id="filteredBadge" style="display:none"></span>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleSelectAll()"><i class="bi bi-check-all"></i> 전체선택</button>
            </div>
        </div>

        <div class="member-table-wrap">
            <table class="table table-hover member-table mb-0">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" id="chkAll" onchange="toggleSelectAll()"></th>
                        <th>ID</th>
                        <th>이름</th>
                        <th>이메일</th>
                        <th>레벨</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody id="memberTbody">
                    <tr><td colspan="6" class="text-center text-muted py-4">검색 버튼을 눌러주세요</td></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-2 text-end small text-muted">선택: <strong id="selectedCount">0</strong>명</div>

        <div class="wz-actions">
            <a href="admin_members.php" class="btn-wz-prev"><i class="bi bi-arrow-left"></i> 회원관리</a>
            <button class="btn-wz-next" onclick="wz.next()">작업 선택 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 작업 선택 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-gear"></i> 작업 선택</h5>
        <div class="row g-2" id="opGrid">
            <div class="col-6 col-md-4">
                <div class="wz-card op-card" data-value="change_level">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="op-icon"><i class="bi bi-arrow-up-circle"></i></div>
                    <div class="wz-card-name">등급 변경</div>
                    <div class="wz-card-desc">선택한 회원의 등급을 일괄 변경</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="wz-card op-card" data-value="add_memo">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="op-icon"><i class="bi bi-sticky"></i></div>
                    <div class="wz-card-name">메모 추가</div>
                    <div class="wz-card-desc">일괄 메모 추가</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="wz-card op-card" data-value="block">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="op-icon"><i class="bi bi-shield-x"></i></div>
                    <div class="wz-card-name">차단</div>
                    <div class="wz-card-desc">로그인 차단 처리</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="wz-card op-card" data-value="unblock">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="op-icon"><i class="bi bi-shield-check"></i></div>
                    <div class="wz-card-name">차단 해제</div>
                    <div class="wz-card-desc">차단된 회원 해제</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="wz-card op-card danger" data-value="delete">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="op-icon" style="color:var(--bs-danger)"><i class="bi bi-trash"></i></div>
                    <div class="wz-card-name" style="color:var(--bs-danger)">삭제 (차단)</div>
                    <div class="wz-card-desc">차단 + 삭제 메모 처리</div>
                </div>
            </div>
        </div>

        <!-- 작업별 추가 설정 -->
        <div class="op-config" id="cfgLevel">
            <label class="form-label small fw-bold">변경할 등급</label>
            <select class="form-select" id="newLevel">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                <option value="<?= $i ?>">레벨 <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="op-config" id="cfgMemo">
            <label class="form-label small fw-bold">추가할 메모</label>
            <textarea class="form-control" id="bulkMemo" rows="3" placeholder="일괄 추가할 메모 내용"></textarea>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">실행 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 확인 + 실행 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> 실행 확인</h5>
        <div id="bulkSummary"></div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnBulkRun" onclick="runBulk()"><i class="bi bi-play-fill"></i> 실행</button>
        </div>
        <div id="bulkResult" style="display:none"></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(3, { storageKey: 'members_bulk_wz' });
let filteredMembers = [];
const selectedMbNos = new Set();
let selectedOp = '';

// 필터 검색
async function filterMembers() {
    const fd = new FormData();
    fd.append('action', 'filter_members');
    fd.append('level', document.getElementById('fLevel').value);
    fd.append('status', document.getElementById('fStatus').value);
    fd.append('date_from', document.getElementById('fDateFrom').value);
    fd.append('date_to', document.getElementById('fDateTo').value);
    fd.append('keyword', document.getElementById('fKeyword').value);

    const res = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) return;

    filteredMembers = data.members;
    document.getElementById('filteredBadge').style.display = '';
    document.getElementById('filteredBadge').innerHTML = `<i class="bi bi-funnel"></i> 필터 ${data.total}명`;

    const tbody = document.getElementById('memberTbody');
    if (data.members.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">결과 없음</td></tr>';
        return;
    }
    tbody.innerHTML = data.members.map(m => {
        const blocked = m.mb_intercept_date ? '<span class="badge bg-danger">차단</span>' : '<span class="badge bg-success">활성</span>';
        const checked = selectedMbNos.has(String(m.mb_no)) ? 'checked' : '';
        return `<tr>
            <td><input type="checkbox" class="mbChk" value="${m.mb_no}" ${checked} onchange="toggleMember(this)"></td>
            <td>${wzEsc(m.mb_id)}</td>
            <td>${wzEsc(m.mb_name||m.mb_nick)}</td>
            <td class="text-muted">${wzEsc(m.mb_email)}</td>
            <td><span class="badge bg-secondary">Lv.${m.mb_level}</span></td>
            <td>${blocked}</td>
        </tr>`;
    }).join('');
    updateSelectedCount();
}

function toggleMember(chk) {
    if (chk.checked) selectedMbNos.add(chk.value);
    else selectedMbNos.delete(chk.value);
    updateSelectedCount();
}

function toggleSelectAll() {
    const chkAll = document.getElementById('chkAll');
    const chks = document.querySelectorAll('.mbChk');
    const allChecked = chkAll.checked || ![...chks].every(c=>c.checked);
    chks.forEach(c => { c.checked = allChecked; if (allChecked) selectedMbNos.add(c.value); else selectedMbNos.delete(c.value); });
    chkAll.checked = allChecked;
    updateSelectedCount();
}

function updateSelectedCount() {
    document.getElementById('selectedCount').textContent = selectedMbNos.size;
}

// 작업 선택
const opSelect = initCardSelect(document.getElementById('opGrid'), {
    onChange: (vals) => {
        selectedOp = vals[0] || '';
        document.querySelectorAll('.op-config').forEach(c => c.classList.remove('show'));
        if (selectedOp === 'change_level') document.getElementById('cfgLevel').classList.add('show');
        if (selectedOp === 'add_memo') document.getElementById('cfgMemo').classList.add('show');
    }
});

// 유효성
wz.setValidator(1, () => {
    if (selectedMbNos.size === 0) { alert('대상 회원을 1명 이상 선택해주세요.'); return false; }
    return true;
});
wz.setValidator(2, () => {
    if (!selectedOp) { alert('작업을 선택해주세요.'); return false; }
    if (selectedOp === 'change_level' && !document.getElementById('newLevel').value) { alert('변경할 등급을 선택해주세요.'); return false; }
    if (selectedOp === 'add_memo' && !document.getElementById('bulkMemo').value.trim()) { alert('메모를 입력해주세요.'); return false; }
    return true;
});

// 요약
const opNames = { change_level:'등급 변경', add_memo:'메모 추가', block:'차단', unblock:'차단 해제', delete:'삭제(차단)' };
wz.onStepChange = (step) => {
    if (step === 3) {
        let detail = '';
        if (selectedOp === 'change_level') detail = `<div class="mt-2">변경 등급: <strong>레벨 ${document.getElementById('newLevel').value}</strong></div>`;
        if (selectedOp === 'add_memo') detail = `<div class="mt-2">메모: <strong>${wzEsc(document.getElementById('bulkMemo').value)}</strong></div>`;

        const isDanger = ['block','delete'].includes(selectedOp);
        document.getElementById('bulkSummary').innerHTML = `
            <div class="wz-summary">
                <div class="row">
                    <div class="col-6"><strong>대상 회원:</strong> ${selectedMbNos.size}명</div>
                    <div class="col-6"><strong>작업:</strong> <span class="${isDanger?'text-danger fw-bold':''}">${wzEsc(opNames[selectedOp]||selectedOp)}</span></div>
                </div>
                ${detail}
            </div>
            ${isDanger ? '<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> 이 작업은 되돌리기 어렵습니다. 신중하게 확인해주세요.</div>' : ''}
        `;
    }
};

// 실행
async function runBulk() {
    const isDanger = ['block','delete'].includes(selectedOp);
    if (isDanger && !confirm(`정말 ${selectedMbNos.size}명을 ${opNames[selectedOp]} 처리하시겠습니까?`)) return;

    const btn = document.getElementById('btnBulkRun');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 처리 중...';

    const fd = new FormData();
    fd.append('action', 'bulk_action');
    fd.append('mb_nos', JSON.stringify([...selectedMbNos]));
    fd.append('operation', selectedOp);
    if (selectedOp === 'change_level') fd.append('op_value', document.getElementById('newLevel').value);
    if (selectedOp === 'add_memo') fd.append('op_value', document.getElementById('bulkMemo').value.trim());

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        const resultDiv = document.getElementById('bulkResult');
        resultDiv.style.display = 'block';

        if (data.success) {
            wz.clearStorage();
            resultDiv.innerHTML = `<div class="text-center mt-3">
                <div style="font-size:48px;color:#28a745">&#9989;</div>
                <h5>${wzEsc(data.message)}</h5>
                <div class="result-stats">
                    <div class="result-stat"><div class="num">${selectedMbNos.size}</div><div class="label">대상</div></div>
                    <div class="result-stat"><div class="num" style="color:#28a745">${data.affected}</div><div class="label">처리됨</div></div>
                </div>
                <a href="admin_members.php" class="btn btn-outline-primary mt-3">회원 관리로</a>
            </div>`;
            btn.style.display = 'none';
        } else {
            alert(data.error || '처리 실패');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> 실행';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> 실행';
    }
}
</script>

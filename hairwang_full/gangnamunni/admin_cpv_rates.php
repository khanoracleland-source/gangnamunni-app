<?php
/**
 * CPV 단가 규칙 관리 페이지
 * 키워드/시술/병원/이벤트별 가중치 CRUD + 단가 계산기
 */
session_start();
$admin_page_title = 'CPV 단가 규칙 관리';
$admin_current = 'cpv_rates';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
require_admin_login();
require_permission('cpv_rates');

require_once __DIR__ . '/includes/cpv_helper.php';

$message = '';
$error = '';

// ── AJAX 처리 ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    // 규칙 추가
    if ($action === 'add_rule') {
        $rule_type   = $_POST['rule_type'] ?? '';
        $rule_key    = trim($_POST['rule_key'] ?? '');
        $weight      = floatval($_POST['weight'] ?? 1.0);
        $fixed_rate  = floatval($_POST['fixed_rate'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        $valid_types = ['keyword', 'procedure', 'hospital', 'event'];
        if (!in_array($rule_type, $valid_types) || $rule_key === '') {
            echo json_encode(['error' => '필수 항목을 입력해주세요.']);
            exit;
        }

        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_rates (rule_type, rule_key, weight, fixed_rate, is_active, description)
             VALUES (?, ?, ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'ssdds', $rule_type, $rule_key, $weight, $fixed_rate, $description);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($connect)]);
        } else {
            echo json_encode(['error' => '저장 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 규칙 수정 (인라인 편집)
    if ($action === 'update_rule') {
        $id         = intval($_POST['id'] ?? 0);
        $field      = $_POST['field'] ?? '';
        $value      = $_POST['value'] ?? '';

        $allowed = ['weight', 'fixed_rate', 'description', 'rule_key', 'is_active'];
        if ($id <= 0 || !in_array($field, $allowed)) {
            echo json_encode(['error' => '잘못된 요청입니다.']);
            exit;
        }

        if ($field === 'is_active') {
            $val = intval($value);
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_rates SET is_active = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
        } elseif (in_array($field, ['weight', 'fixed_rate'])) {
            $val = floatval($value);
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_rates SET {$field} = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'di', $val, $id);
        } else {
            $val = trim($value);
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_rates SET {$field} = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $val, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => '수정 실패']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 규칙 삭제
    if ($action === 'delete_rule') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_cpv_rates WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => '잘못된 ID']);
        }
        exit;
    }

    // 단가 계산기
    if ($action === 'calculate') {
        $hospital_id    = intval($_POST['hospital_id'] ?? 0);
        $keyword        = trim($_POST['keyword'] ?? '');
        $procedure_type = trim($_POST['procedure_type'] ?? '');
        $event_active   = ($_POST['event_active'] ?? '0') === '1';

        $rate = calculateCpvRate($hospital_id, $keyword ?: null, $procedure_type ?: null, $event_active);
        echo json_encode([
            'success' => true,
            'rate' => $rate,
            'formatted' => formatCpvCurrency($rate),
            'base_rate' => getCpvSetting('base_cpv_rate', 200)
        ]);
        exit;
    }

    echo json_encode(['error' => '알 수 없는 액션']);
    exit;
}

// ── 규칙 목록 로드 ──────────────────────────────────────────
$rules = ['keyword' => [], 'procedure' => [], 'hospital' => [], 'event' => []];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_cpv_rates ORDER BY rule_type, rule_key");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $t = $row['rule_type'];
        if (isset($rules[$t])) $rules[$t][] = $row;
    }
}

// 병원 목록 (병원 규칙용)
$hospitals = [];
$hr = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals ORDER BY name");
if ($hr) {
    while ($h = mysqli_fetch_assoc($hr)) $hospitals[] = $h;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.cpv-rates-wrap { max-width: 1400px; margin: 0 auto; }
.editable { cursor: pointer; border-bottom: 1px dashed var(--bs-primary); }
.editable:hover { background: var(--bs-primary-bg-subtle); }
.tab-count { font-size: 11px; margin-left: 4px; }
.calc-card { border: 2px solid var(--bs-primary); }
.rule-table th { font-size: 13px; white-space: nowrap; }
.rule-table td { font-size: 13px; vertical-align: middle; }
</style>

<div class="cpv-rates-wrap px-3 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-sliders"></i> CPV 단가 규칙 관리</h4>
            <small class="text-body-secondary">키워드/시술/병원/이벤트별 가중치를 설정합니다. 클릭하여 인라인 편집 가능합니다.</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#calcPanel">
                <i class="bi bi-calculator"></i> 단가 계산기
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                <i class="bi bi-plus-lg"></i> 규칙 추가
            </button>
        </div>
    </div>

    <!-- 단가 계산기 패널 -->
    <div class="collapse mb-4" id="calcPanel">
        <div class="card calc-card">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-calculator"></i> CPV 단가 계산기</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">병원</label>
                        <select class="form-select form-select-sm" id="calcHospital">
                            <option value="0">-- 선택 --</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">키워드</label>
                        <input type="text" class="form-control form-control-sm" id="calcKeyword" placeholder="예: 눈성형">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">시술 종류</label>
                        <input type="text" class="form-control form-control-sm" id="calcProcedure" placeholder="예: rhinoplasty">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">이벤트 진행중</label>
                        <select class="form-select form-select-sm" id="calcEvent">
                            <option value="0">아니오</option>
                            <option value="1">예</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="doCalc()">계산</button>
                    </div>
                </div>
                <div id="calcResult" class="mt-3 d-none">
                    <div class="alert alert-info mb-0">
                        <strong>기본 단가:</strong> <span id="calcBase">-</span> &rarr;
                        <strong>최종 단가:</strong> <span id="calcFinal" class="fs-5 fw-bold text-primary">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4개 탭 -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-keyword" type="button">
                <i class="bi bi-search"></i> 키워드
                <span class="badge bg-secondary tab-count"><?= count($rules['keyword']) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-procedure" type="button">
                <i class="bi bi-scissors"></i> 시술
                <span class="badge bg-secondary tab-count"><?= count($rules['procedure']) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hospital" type="button">
                <i class="bi bi-hospital"></i> 병원별
                <span class="badge bg-secondary tab-count"><?= count($rules['hospital']) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-event" type="button">
                <i class="bi bi-calendar-event"></i> 이벤트
                <span class="badge bg-secondary tab-count"><?= count($rules['event']) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-3">
        <?php
        $tab_types = [
            'keyword'   => ['label' => '키워드', 'placeholder' => '키워드명 (예: 눈성형, 코필러)'],
            'procedure' => ['label' => '시술', 'placeholder' => '시술 코드 (예: rhinoplasty, botox)'],
            'hospital'  => ['label' => '병원', 'placeholder' => '병원 ID'],
            'event'     => ['label' => '이벤트', 'placeholder' => '이벤트 키 (예: active, ended, seasonal)'],
        ];
        $first = true;
        foreach ($tab_types as $type => $meta):
        ?>
        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="tab-<?= $type ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <input type="text" class="form-control form-control-sm" style="width:250px"
                           placeholder="검색..." onkeyup="filterTable(this, 'table-<?= $type ?>')">
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="openAddModal('<?= $type ?>')">
                    <i class="bi bi-plus"></i> <?= $meta['label'] ?> 규칙 추가
                </button>
            </div>

            <?php if (empty($rules[$type])): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:32px;"></i>
                    등록된 <?= $meta['label'] ?> 규칙이 없습니다.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover rule-table mb-0" id="table-<?= $type ?>">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th>규칙 키</th>
                            <th style="width:120px">가중치</th>
                            <th style="width:130px">고정 단가</th>
                            <th>설명</th>
                            <th style="width:80px">상태</th>
                            <th style="width:80px">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rules[$type] as $rule): ?>
                        <tr data-id="<?= $rule['id'] ?>">
                            <td class="text-body-secondary"><?= $rule['id'] ?></td>
                            <td>
                                <span class="editable" data-field="rule_key" data-id="<?= $rule['id'] ?>"
                                      onclick="inlineEdit(this)"><?= htmlspecialchars($rule['rule_key']) ?></span>
                            </td>
                            <td>
                                <span class="editable fw-bold" data-field="weight" data-id="<?= $rule['id'] ?>"
                                      onclick="inlineEdit(this)"><?= number_format($rule['weight'], 2) ?></span>
                                <small class="text-body-secondary">x</small>
                            </td>
                            <td>
                                <span class="editable" data-field="fixed_rate" data-id="<?= $rule['id'] ?>"
                                      onclick="inlineEdit(this)"><?= $rule['fixed_rate'] > 0 ? number_format($rule['fixed_rate']) . '원' : '-' ?></span>
                            </td>
                            <td>
                                <span class="editable text-body-secondary" data-field="description" data-id="<?= $rule['id'] ?>"
                                      onclick="inlineEdit(this)"><?= htmlspecialchars($rule['description'] ?: '(없음)') ?></span>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           <?= $rule['is_active'] ? 'checked' : '' ?>
                                           onchange="toggleActive(<?= $rule['id'] ?>, this.checked)">
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(<?= $rule['id'] ?>, this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php $first = false; endforeach; ?>
    </div>
</div>

<!-- 규칙 추가 모달 -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> 새 규칙 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">규칙 유형</label>
                    <select class="form-select" id="modalRuleType">
                        <option value="keyword">키워드</option>
                        <option value="procedure">시술</option>
                        <option value="hospital">병원별</option>
                        <option value="event">이벤트</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">규칙 키 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modalRuleKey" placeholder="키워드명, 시술코드, 병원ID 등">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">가중치 (배수)</label>
                        <input type="number" class="form-control" id="modalWeight" value="1.00" step="0.01" min="0">
                        <small class="text-body-secondary">기본 단가에 곱할 배수 (1.0 = 기본)</small>
                    </div>
                    <div class="col-6">
                        <label class="form-label">고정 단가 (원)</label>
                        <input type="number" class="form-control" id="modalFixedRate" value="0" min="0">
                        <small class="text-body-secondary">0이면 가중치 방식 사용</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">설명</label>
                    <textarea class="form-control" id="modalDescription" rows="2" placeholder="규칙에 대한 설명 (선택)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="addRule()">
                    <i class="bi bi-check-lg"></i> 추가
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// 인라인 편집
function inlineEdit(el) {
    if (el.querySelector('input')) return; // 이미 편집중
    var field = el.getAttribute('data-field');
    var id = el.getAttribute('data-id');
    var currentVal = el.textContent.trim();
    if (currentVal === '(없음)') currentVal = '';
    if (field === 'fixed_rate') currentVal = currentVal.replace(/[^0-9.-]/g, '') || '0';
    if (field === 'weight') currentVal = currentVal.replace(/[^0-9.-]/g, '');

    var input = document.createElement('input');
    input.type = (field === 'weight' || field === 'fixed_rate') ? 'number' : 'text';
    input.className = 'form-control form-control-sm';
    input.style.cssText = 'width:100%;min-width:60px;';
    input.value = currentVal;
    if (field === 'weight') { input.step = '0.01'; input.min = '0'; }
    if (field === 'fixed_rate') { input.step = '1'; input.min = '0'; }

    var originalHTML = el.innerHTML;
    el.innerHTML = '';
    el.appendChild(input);
    input.focus();
    input.select();

    function save() {
        var newVal = input.value.trim();
        var fd = new FormData();
        fd.append('ajax_action', 'update_rule');
        fd.append('id', id);
        fd.append('field', field);
        fd.append('value', newVal);

        fetch(location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    if (field === 'weight') el.textContent = parseFloat(newVal).toFixed(2);
                    else if (field === 'fixed_rate') el.textContent = parseFloat(newVal) > 0 ? Number(newVal).toLocaleString() + '원' : '-';
                    else if (field === 'description') el.textContent = newVal || '(없음)';
                    else el.textContent = newVal;
                    showToast('수정 완료', 'success');
                } else {
                    el.innerHTML = originalHTML;
                    showToast(d.error || '수정 실패', 'danger');
                }
            })
            .catch(function() { el.innerHTML = originalHTML; });
    }

    input.addEventListener('blur', save);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); save(); }
        if (e.key === 'Escape') { el.innerHTML = originalHTML; }
    });
}

// 활성/비활성 토글
function toggleActive(id, checked) {
    var fd = new FormData();
    fd.append('ajax_action', 'update_rule');
    fd.append('id', id);
    fd.append('field', 'is_active');
    fd.append('value', checked ? '1' : '0');

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) showToast(checked ? '활성화됨' : '비활성화됨', 'success');
            else showToast('변경 실패', 'danger');
        });
}

// 규칙 삭제
function deleteRule(id, btn) {
    if (!confirm('이 규칙을 삭제하시겠습니까?')) return;
    var fd = new FormData();
    fd.append('ajax_action', 'delete_rule');
    fd.append('id', id);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                var row = btn.closest('tr');
                if (row) row.remove();
                showToast('삭제 완료', 'success');
            } else {
                showToast(d.error || '삭제 실패', 'danger');
            }
        });
}

// 규칙 추가
function addRule() {
    var fd = new FormData();
    fd.append('ajax_action', 'add_rule');
    fd.append('rule_type', document.getElementById('modalRuleType').value);
    fd.append('rule_key', document.getElementById('modalRuleKey').value);
    fd.append('weight', document.getElementById('modalWeight').value);
    fd.append('fixed_rate', document.getElementById('modalFixedRate').value);
    fd.append('description', document.getElementById('modalDescription').value);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                showToast('규칙이 추가되었습니다.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addRuleModal')).hide();
                setTimeout(function() { location.reload(); }, 500);
            } else {
                showToast(d.error || '추가 실패', 'danger');
            }
        });
}

// 모달 열기 (탭 컨텍스트 전달)
function openAddModal(type) {
    document.getElementById('modalRuleType').value = type;
    new bootstrap.Modal(document.getElementById('addRuleModal')).show();
}

// 단가 계산기
function doCalc() {
    var fd = new FormData();
    fd.append('ajax_action', 'calculate');
    fd.append('hospital_id', document.getElementById('calcHospital').value);
    fd.append('keyword', document.getElementById('calcKeyword').value);
    fd.append('procedure_type', document.getElementById('calcProcedure').value);
    fd.append('event_active', document.getElementById('calcEvent').value);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                document.getElementById('calcBase').textContent = Number(d.base_rate).toLocaleString() + '원';
                document.getElementById('calcFinal').textContent = d.formatted;
                document.getElementById('calcResult').classList.remove('d-none');
            }
        });
}

// 테이블 검색 필터
function filterTable(input, tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var filter = input.value.toLowerCase();
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}

// 토스트 알림
function showToast(msg, type) {
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' position-fixed shadow';
    toast.style.cssText = 'top:20px;right:20px;z-index:9999;min-width:280px;animation:fadeIn 0.3s;';
    toast.innerHTML = '<i class="bi ' + (type === 'success' ? 'bi-check-circle' : 'bi-x-circle') + '"></i> ' + msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

<?php
/**
 * CPV 지갑 관리 (admin_wallet.php)
 * - 전체 병원 지갑 목록, 잔액/충전/소진 현황
 * - 수동 충전/조정 모달
 * - 병원별 거래내역 확장 테이블
 * @created 2026-03-11
 */
session_start();

$admin_page_title = '지갑 관리';
$admin_current = 'cpv_wallet';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/cpv_helper.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('cpv_wallet');

// ===== 필터 & 검색 =====
$search = $_GET['q'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// WHERE 조건 동적 구성
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($search) {
    $where_clauses[] = "h.name LIKE ?";
    $bind_types .= 's';
    $bind_values[] = '%' . $search . '%';
}
if ($filter_status === 'active') {
    $where_clauses[] = "w.is_active = 1 AND w.is_paused = 0";
} elseif ($filter_status === 'paused') {
    $where_clauses[] = "w.is_paused = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "w.is_active = 0";
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_wallets w LEFT JOIN gangnamunni_hospitals h ON w.hospital_id = h.id WHERE $where";
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
if ($stmt_c) {
    mysqli_stmt_execute($stmt_c);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c))['cnt'] ?? 0;
    mysqli_stmt_close($stmt_c);
} else {
    $total = 0;
}
$total_pages = max(1, ceil($total / $per_page));

// 지갑 목록 조회
$list_sql = "SELECT w.*, h.name as hospital_name
             FROM gangnamunni_cpv_wallets w
             LEFT JOIN gangnamunni_hospitals h ON w.hospital_id = h.id
             WHERE $where
             ORDER BY w.updated_at DESC
             LIMIT ? OFFSET ?";
$stmt_d = mysqli_prepare($connect, $list_sql);
$all_types = $bind_types . 'ii';
$all_values = array_merge($bind_values, [$per_page, $offset]);
if ($all_types) {
    mysqli_stmt_bind_param($stmt_d, $all_types, ...$all_values);
}
mysqli_stmt_execute($stmt_d);
$result = mysqli_stmt_get_result($stmt_d);
$wallets = [];
while ($row = mysqli_fetch_assoc($result)) {
    $wallets[] = $row;
}
mysqli_stmt_close($stmt_d);

// ===== 요약 통계 =====
$summary = ['total_balance' => 0, 'charged_today' => 0, 'spent_today' => 0, 'wallet_count' => 0, 'active_count' => 0, 'paused_count' => 0];

// 전체 잔액 합계 & 지갑 수
$sr = mysqli_query($connect, "SELECT
    COUNT(*) as wallet_count,
    SUM(balance) as total_balance,
    SUM(CASE WHEN is_active = 1 AND is_paused = 0 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN is_paused = 1 THEN 1 ELSE 0 END) as paused_count
    FROM gangnamunni_cpv_wallets");
if ($sr) {
    $s = mysqli_fetch_assoc($sr);
    $summary['total_balance'] = (int)($s['total_balance'] ?? 0);
    $summary['wallet_count'] = (int)($s['wallet_count'] ?? 0);
    $summary['active_count'] = (int)($s['active_count'] ?? 0);
    $summary['paused_count'] = (int)($s['paused_count'] ?? 0);
}

// 오늘 충전/소진 합계
$tr = mysqli_query($connect, "SELECT type, SUM(amount) as total
    FROM gangnamunni_cpv_transactions
    WHERE DATE(created_at) = CURDATE() AND type IN ('charge', 'deduct')
    GROUP BY type");
if ($tr) {
    while ($t = mysqli_fetch_assoc($tr)) {
        if ($t['type'] === 'charge') $summary['charged_today'] = (int)$t['total'];
        if ($t['type'] === 'deduct') $summary['spent_today'] = (int)$t['total'];
    }
}

// 병원 목록 (지갑 생성 모달용)
$hospitals_for_select = [];
$hr = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals ORDER BY name");
if ($hr) {
    while ($h = mysqli_fetch_assoc($hr)) {
        $hospitals_for_select[] = $h;
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-1"><i class="bi bi-wallet2" style="font-size:24px;"></i></div>
                    <h4 class="mb-0"><?php echo formatCpvCurrency($summary['total_balance']); ?></h4>
                    <small class="text-body-secondary">전체 잔액 합계</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-1"><i class="bi bi-plus-circle" style="font-size:24px;"></i></div>
                    <h4 class="mb-0"><?php echo formatCpvCurrency($summary['charged_today']); ?></h4>
                    <small class="text-body-secondary">오늘 충전</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-danger mb-1"><i class="bi bi-dash-circle" style="font-size:24px;"></i></div>
                    <h4 class="mb-0"><?php echo formatCpvCurrency($summary['spent_today']); ?></h4>
                    <small class="text-body-secondary">오늘 소진</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-1"><i class="bi bi-building" style="font-size:24px;"></i></div>
                    <h4 class="mb-0"><?php echo number_format($summary['wallet_count']); ?></h4>
                    <small class="text-body-secondary">
                        지갑 수
                        <span class="badge bg-success"><?php echo $summary['active_count']; ?> 활성</span>
                        <?php if ($summary['paused_count'] > 0): ?>
                        <span class="badge bg-warning"><?php echo $summary['paused_count']; ?> 정지</span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- 상단 제목 + 버튼 -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>지갑 관리</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWalletModal">
            <i class="bi bi-plus-lg me-1"></i>지갑 생성
        </button>
    </div>

    <!-- 알림 영역 -->
    <div id="alertArea"></div>

    <!-- 검색/필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">전체 상태</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>활성</option>
                        <option value="paused" <?php echo $filter_status === 'paused' ? 'selected' : ''; ?>>일시정지</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>비활성</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="병원명 검색" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> 검색</button>
                </div>
                <div class="col-md-2">
                    <a href="admin_wallet.php" class="btn btn-outline-secondary btn-sm w-100">초기화</a>
                </div>
            </form>
        </div>
    </div>

    <!-- 지갑 목록 테이블 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="walletTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>병원명</th>
                        <th class="text-end">잔액</th>
                        <th class="text-end">총 충전</th>
                        <th class="text-end">총 소진</th>
                        <th class="text-end">일일 한도</th>
                        <th>상태</th>
                        <th>최근 업데이트</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wallets)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet" style="font-size:2rem;"></i><br>
                            등록된 지갑이 없습니다.
                        </td>
                    </tr>
                    <?php else: foreach ($wallets as $w): ?>
                    <tr>
                        <td><?php echo $w['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($w['hospital_name'] ?? '병원 #' . $w['hospital_id']); ?></strong>
                            <br><small class="text-body-secondary">ID: <?php echo $w['hospital_id']; ?></small>
                        </td>
                        <td class="text-end fw-bold <?php echo $w['balance'] > 0 ? 'text-primary' : 'text-danger'; ?>">
                            <?php echo formatCpvCurrency($w['balance']); ?>
                        </td>
                        <td class="text-end text-success"><?php echo formatCpvCurrency($w['total_charged']); ?></td>
                        <td class="text-end text-danger"><?php echo formatCpvCurrency($w['total_spent']); ?></td>
                        <td class="text-end">
                            <?php echo $w['daily_cap'] > 0 ? formatCpvCurrency($w['daily_cap']) : '<span class="text-body-secondary">무제한</span>'; ?>
                        </td>
                        <td>
                            <?php if (!$w['is_active']): ?>
                                <span class="badge bg-secondary">비활성</span>
                            <?php elseif ($w['is_paused']): ?>
                                <span class="badge bg-warning text-dark" title="<?php echo htmlspecialchars($w['pause_reason'] ?? ''); ?>">일시정지</span>
                            <?php else: ?>
                                <span class="badge bg-success">활성</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo substr($w['updated_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success" title="충전"
                                        onclick="openChargeModal(<?php echo $w['hospital_id']; ?>, '<?php echo htmlspecialchars($w['hospital_name'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <button class="btn btn-outline-warning" title="조정"
                                        onclick="openAdjustModal(<?php echo $w['hospital_id']; ?>, '<?php echo htmlspecialchars($w['hospital_name'] ?? '', ENT_QUOTES); ?>', <?php echo $w['balance']; ?>)">
                                    <i class="bi bi-sliders"></i>
                                </button>
                                <button class="btn btn-outline-info" title="거래내역"
                                        onclick="toggleTransactions(<?php echo $w['hospital_id']; ?>, this)">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <!-- 거래내역 확장 행 (기본 숨김) -->
                    <tr class="tx-row" id="txRow_<?php echo $w['hospital_id']; ?>" style="display:none;">
                        <td colspan="9" class="p-0">
                            <div class="p-3" style="background:var(--bs-tertiary-bg);">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><i class="bi bi-receipt me-1"></i>거래내역 - <?php echo htmlspecialchars($w['hospital_name'] ?? ''); ?></strong>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary btn-sm active" onclick="filterTx(<?php echo $w['hospital_id']; ?>, '')">전체</button>
                                        <button class="btn btn-outline-success btn-sm" onclick="filterTx(<?php echo $w['hospital_id']; ?>, 'charge')">충전</button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="filterTx(<?php echo $w['hospital_id']; ?>, 'deduct')">차감</button>
                                        <button class="btn btn-outline-info btn-sm" onclick="filterTx(<?php echo $w['hospital_id']; ?>, 'refund')">환불</button>
                                        <button class="btn btn-outline-warning btn-sm" onclick="filterTx(<?php echo $w['hospital_id']; ?>, 'adjust')">조정</button>
                                    </div>
                                </div>
                                <div id="txContent_<?php echo $w['hospital_id']; ?>">
                                    <div class="text-center py-3 text-muted">
                                        <div class="spinner-border spinner-border-sm me-1"></div> 로딩 중...
                                    </div>
                                </div>
                                <div id="txPagination_<?php echo $w['hospital_id']; ?>" class="mt-2"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 지갑 생성 모달 ===== -->
<div class="modal fade" id="createWalletModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-wallet2 me-1"></i>새 지갑 생성</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">병원 선택</label>
                    <select class="form-select" id="createHospitalId">
                        <option value="">-- 병원을 선택하세요 --</option>
                        <?php foreach ($hospitals_for_select as $h): ?>
                        <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo $h['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">일일 소진 한도</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="createDailyCap" value="0" min="0" step="10000">
                        <span class="input-group-text">원 (0 = 무제한)</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="createWallet()">
                    <i class="bi bi-plus-lg me-1"></i>생성
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== 충전 모달 ===== -->
<div class="modal fade" id="chargeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-1 text-success"></i>수동 충전</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    <strong id="chargeHospitalName"></strong> (ID: <span id="chargeHospitalId"></span>)
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">충전 금액</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="chargeAmount" min="1" step="1000" placeholder="충전할 금액 입력">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">보너스 금액 (선택)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="chargeBonusAmount" min="0" step="1000" value="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">설명</label>
                    <input type="text" class="form-control" id="chargeDescription" placeholder="충전 사유 (예: 프로모션 보너스)">
                </div>
                <div class="mb-3">
                    <label class="form-label">PG 주문번호 (선택)</label>
                    <input type="text" class="form-control" id="chargePgOrderId" placeholder="PG 결제 주문번호">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-success" onclick="doCharge()">
                    <i class="bi bi-plus-lg me-1"></i>충전
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== 조정 모달 ===== -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sliders me-1 text-warning"></i>수동 조정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2">
                    <strong id="adjustHospitalName"></strong> (ID: <span id="adjustHospitalId"></span>)
                    <br>현재 잔액: <strong id="adjustCurrentBalance"></strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">조정 금액</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="adjustAmount" placeholder="양수=증가, 음수=감소">
                        <span class="input-group-text">원</span>
                    </div>
                    <small class="text-body-secondary">양수를 입력하면 증가, 음수를 입력하면 감소합니다.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">조정 사유 (필수)</label>
                    <input type="text" class="form-control" id="adjustDescription" placeholder="조정 사유를 입력하세요" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-warning" onclick="doAdjust()">
                    <i class="bi bi-check-lg me-1"></i>조정 적용
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ========================================
// 유틸리티
// ========================================
function showAlert(msg, type) {
    var area = document.getElementById('alertArea');
    area.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' + msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    setTimeout(function() { area.innerHTML = ''; }, 5000);
}

function apiCall(body) {
    return fetch('api_wallet.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    }).then(function(r) { return r.json(); });
}

function formatWon(n) {
    return new Intl.NumberFormat('ko-KR').format(n) + '원';
}

// ========================================
// 지갑 생성
// ========================================
function createWallet() {
    var hospitalId = document.getElementById('createHospitalId').value;
    var dailyCap = document.getElementById('createDailyCap').value;
    if (!hospitalId) { alert('병원을 선택하세요.'); return; }

    apiCall({action: 'create', hospital_id: parseInt(hospitalId), daily_cap: parseInt(dailyCap) || 0})
    .then(function(data) {
        if (data.success) {
            showAlert('지갑이 생성되었습니다. (ID: ' + data.wallet_id + ')', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createWalletModal')).hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showAlert(data.error || '생성 실패', 'danger');
        }
    }).catch(function(e) { showAlert('오류: ' + e.message, 'danger'); });
}

// ========================================
// 충전 모달
// ========================================
function openChargeModal(hospitalId, hospitalName) {
    document.getElementById('chargeHospitalId').textContent = hospitalId;
    document.getElementById('chargeHospitalName').textContent = hospitalName;
    document.getElementById('chargeAmount').value = '';
    document.getElementById('chargeBonusAmount').value = '0';
    document.getElementById('chargeDescription').value = '';
    document.getElementById('chargePgOrderId').value = '';
    new bootstrap.Modal(document.getElementById('chargeModal')).show();
}

function doCharge() {
    var hospitalId = parseInt(document.getElementById('chargeHospitalId').textContent);
    var amount = parseInt(document.getElementById('chargeAmount').value);
    var bonus = parseInt(document.getElementById('chargeBonusAmount').value) || 0;
    var desc = document.getElementById('chargeDescription').value;
    var pgOrderId = document.getElementById('chargePgOrderId').value;

    if (!amount || amount <= 0) { alert('충전 금액을 입력하세요.'); return; }
    if (!confirm(formatWon(amount) + ' 충전하시겠습니까?' + (bonus > 0 ? '\n보너스: ' + formatWon(bonus) : ''))) return;

    apiCall({
        action: 'charge',
        hospital_id: hospitalId,
        amount: amount,
        bonus_amount: bonus,
        description: desc || '관리자 수동 충전',
        pg_order_id: pgOrderId
    }).then(function(data) {
        if (data.success) {
            showAlert('충전 완료: ' + data.amount_formatted + ' (잔액: ' + data.new_balance_formatted + ')', 'success');
            bootstrap.Modal.getInstance(document.getElementById('chargeModal')).hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showAlert(data.error || '충전 실패', 'danger');
        }
    }).catch(function(e) { showAlert('오류: ' + e.message, 'danger'); });
}

// ========================================
// 조정 모달
// ========================================
function openAdjustModal(hospitalId, hospitalName, balance) {
    document.getElementById('adjustHospitalId').textContent = hospitalId;
    document.getElementById('adjustHospitalName').textContent = hospitalName;
    document.getElementById('adjustCurrentBalance').textContent = formatWon(balance);
    document.getElementById('adjustAmount').value = '';
    document.getElementById('adjustDescription').value = '';
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}

function doAdjust() {
    var hospitalId = parseInt(document.getElementById('adjustHospitalId').textContent);
    var amount = parseInt(document.getElementById('adjustAmount').value);
    var desc = document.getElementById('adjustDescription').value.trim();

    if (!amount || amount === 0) { alert('조정 금액을 입력하세요.'); return; }
    if (!desc) { alert('조정 사유를 입력하세요.'); return; }
    if (!confirm('조정 금액: ' + formatWon(amount) + '\n사유: ' + desc + '\n\n적용하시겠습니까?')) return;

    apiCall({
        action: 'adjust',
        hospital_id: hospitalId,
        amount: amount,
        description: desc
    }).then(function(data) {
        if (data.success) {
            showAlert('조정 완료. 새 잔액: ' + data.new_balance_formatted, 'success');
            bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showAlert(data.error || '조정 실패', 'danger');
        }
    }).catch(function(e) { showAlert('오류: ' + e.message, 'danger'); });
}

// ========================================
// 거래내역 토글 & 로딩
// ========================================
var txLoaded = {};
var txFilters = {};

function toggleTransactions(hospitalId, btn) {
    var row = document.getElementById('txRow_' + hospitalId);
    if (row.style.display === 'none') {
        row.style.display = '';
        loadTransactions(hospitalId, 1, '');
    } else {
        row.style.display = 'none';
    }
}

function filterTx(hospitalId, type) {
    txFilters[hospitalId] = type;
    // 버튼 활성 상태 변경
    var buttons = document.getElementById('txRow_' + hospitalId).querySelectorAll('.btn-group .btn');
    buttons.forEach(function(b) { b.classList.remove('active'); });
    event.target.classList.add('active');
    loadTransactions(hospitalId, 1, type);
}

function loadTransactions(hospitalId, page, type) {
    var container = document.getElementById('txContent_' + hospitalId);
    var pagination = document.getElementById('txPagination_' + hospitalId);
    container.innerHTML = '<div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm me-1"></div> 로딩 중...</div>';

    var url = 'api_wallet.php?action=transactions&hospital_id=' + hospitalId + '&page=' + page + '&per_page=10';
    if (type) url += '&type=' + type;

    fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success || !data.transactions || data.transactions.length === 0) {
            container.innerHTML = '<div class="text-center py-3 text-muted">거래내역이 없습니다.</div>';
            pagination.innerHTML = '';
            return;
        }

        var typeLabels = {charge:'충전', deduct:'차감', refund:'환불', adjust:'조정', bonus:'보너스'};
        var typeBadges = {charge:'success', deduct:'danger', refund:'info', adjust:'warning', bonus:'primary'};

        var html = '<table class="table table-sm table-bordered mb-0" style="font-size:12px;">';
        html += '<thead><tr><th>일시</th><th>유형</th><th class="text-end">금액</th><th class="text-end">잔액</th><th>설명</th><th>처리자</th></tr></thead><tbody>';

        data.transactions.forEach(function(tx) {
            var sign = (tx.type === 'deduct') ? '-' : '+';
            var amtClass = (tx.type === 'deduct') ? 'text-danger' : 'text-success';
            html += '<tr>';
            html += '<td class="text-nowrap">' + (tx.created_at || '').substring(0, 16) + '</td>';
            html += '<td><span class="badge bg-' + (typeBadges[tx.type] || 'secondary') + '">' + (typeLabels[tx.type] || tx.type) + '</span></td>';
            html += '<td class="text-end fw-bold ' + amtClass + '">' + sign + tx.amount_formatted + '</td>';
            html += '<td class="text-end">' + tx.balance_after_formatted + '</td>';
            html += '<td>' + (tx.description || '-') + '</td>';
            html += '<td>' + (tx.admin_name || '-') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;

        // 페이지네이션
        if (data.total_pages > 1) {
            var pHtml = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
            for (var i = 1; i <= data.total_pages; i++) {
                pHtml += '<li class="page-item ' + (i === data.page ? 'active' : '') + '">';
                pHtml += '<a class="page-link" href="javascript:void(0)" onclick="loadTransactions(' + hospitalId + ',' + i + ',\'' + (type || '') + '\')">' + i + '</a>';
                pHtml += '</li>';
            }
            pHtml += '</ul></nav>';
            pagination.innerHTML = pHtml;
        } else {
            pagination.innerHTML = '';
        }
    })
    .catch(function(e) {
        container.innerHTML = '<div class="text-center py-3 text-danger">로딩 실패: ' + e.message + '</div>';
    });
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

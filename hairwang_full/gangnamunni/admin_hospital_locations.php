<?php
/**
 * 병원 노출 순서
 * hospitals.php의 기본 병원 노출 순서를 관리한다.
 */

session_start();

$admin_page_title = '병원 노출 순서';
$admin_page_description = 'hospitals.php에서 정렬을 선택하지 않았을 때 적용할 병원 노출 순서를 지정합니다.';
$admin_current = 'hospital_locations';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('hospitals');

// 기존 DB에도 바로 적용될 수 있게 정렬 컬럼을 보완한다.
$display_order_column = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospitals LIKE 'display_order'");
if (!$display_order_column || mysqli_num_rows($display_order_column) === 0) {
    if (!mysqli_query($connect, "ALTER TABLE gangnamunni_hospitals ADD COLUMN display_order INT NULL DEFAULT NULL AFTER is_recommended")) {
        error_log('[admin_hospital_locations] display_order migration failed: ' . mysqli_error($connect));
    }
}

if (empty($_SESSION['admin_hospital_order_csrf'])) {
    $_SESSION['admin_hospital_order_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_hospital_order_csrf'];

// 병원 계정은 자신에게 배정된 병원만 변경할 수 있다.
$scope_ids = get_hospital_filter_ids($connect);
$scope_sql = $scope_ids === null
    ? ''
    : ' AND id IN (' . (empty($scope_ids) ? '0' : implode(',', array_map('intval', $scope_ids))) . ')';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, (string) $_POST['csrf_token'])) {
        http_response_code(403);
        exit('잘못된 요청입니다. 페이지를 새로고침한 후 다시 시도해주세요.');
    }

    $positions = isset($_POST['positions']) && is_array($_POST['positions']) ? $_POST['positions'] : [];
    $allowed_ids = [];
    $allowed_result = mysqli_query($connect,
        "SELECT id FROM gangnamunni_hospitals
          WHERE is_active=1 AND COALESCE(is_demo, 0)=0
            AND (status='approved' OR status IS NULL){$scope_sql}");
    if ($allowed_result) {
        while ($allowed_row = mysqli_fetch_assoc($allowed_result)) $allowed_ids[(int) $allowed_row['id']] = true;
    }

    $requested_order = [];
    foreach ($positions as $hospital_id => $position) {
        $hospital_id = (int) $hospital_id;
        $position = filter_var($position, FILTER_VALIDATE_INT);
        if ($hospital_id <= 0 || !isset($allowed_ids[$hospital_id]) || $position === false || $position < 1) continue;
        $requested_order[] = ['id' => $hospital_id, 'position' => $position];
    }

    usort($requested_order, static function ($a, $b) {
        return ($a['position'] <=> $b['position']) ?: ($a['id'] <=> $b['id']);
    });

    if (empty($requested_order)) {
        $error = '저장할 병원 순서가 없습니다.';
    } else {
        mysqli_begin_transaction($connect);
        try {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET display_order=?, updated_at=NOW() WHERE id=?");
            if (!$stmt) throw new RuntimeException(mysqli_error($connect));

            $normalized_position = 1;
            foreach ($requested_order as $item) {
                $hospital_id = $item['id'];
                mysqli_stmt_bind_param($stmt, 'ii', $normalized_position, $hospital_id);
                if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
                $normalized_position++;
            }
            mysqli_stmt_close($stmt);
            mysqli_commit($connect);
            $message = '병원 노출 순서가 저장되었습니다.';
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            error_log('[admin_hospital_locations] save failed: ' . $e->getMessage());
            $error = '병원 순서를 저장하지 못했습니다.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $error === '',
        'message' => $error !== '' ? $error : $message,
    ], JSON_UNESCAPED_UNICODE);
    mysqli_close($connect);
    exit;
}

$hospitals = [];
$hospital_result = mysqli_query($connect,
    "SELECT id, name, address, rating, review_count, display_order
       FROM gangnamunni_hospitals
      WHERE is_active=1 AND COALESCE(is_demo, 0)=0
        AND (status='approved' OR status IS NULL){$scope_sql}
      ORDER BY CASE WHEN display_order IS NULL OR display_order < 1 THEN 1 ELSE 0 END,
               display_order ASC, name ASC, id ASC");
if ($hospital_result) {
    while ($hospital = mysqli_fetch_assoc($hospital_result)) $hospitals[] = $hospital;
}

include __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-list-ol me-2"></i>병원 노출 순서</h4>
            <p class="text-body-secondary mb-0">숫자가 작을수록 hospitals.php에서 먼저 표시됩니다.</p>
        </div>
        <a href="hospitals.php" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i>병원 목록 확인
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <style>
    .order-card { overflow: hidden; }
    .hospital-order-row { transition: background-color .15s ease, box-shadow .15s ease, opacity .15s ease; }
    .hospital-order-row[draggable="true"] { cursor: grab; }
    .hospital-order-row[draggable="true"]:active { cursor: grabbing; }
    .hospital-order-row.is-dragging { opacity: .42; background: var(--bs-primary-bg-subtle); }
    .hospital-order-row.drag-target { box-shadow: inset 0 3px 0 var(--bs-primary); }
    .drag-handle { color: var(--bs-secondary-color); font-size: 1.15rem; cursor: grab; }
    .position-badge {
        width: 42px; height: 42px; border-radius: 14px; display: inline-flex;
        align-items: center; justify-content: center; font-size: 1rem; font-weight: 700;
        color: var(--bs-primary); background: var(--bs-primary-bg-subtle);
        border: 1px solid var(--bs-primary-border-subtle);
    }
    .hospital-order-row:nth-child(-n+3) .position-badge { color: #fff; border: 0; }
    .hospital-order-row:nth-child(1) .position-badge { background: linear-gradient(135deg,#f5b700,#d99100); }
    .hospital-order-row:nth-child(2) .position-badge { background: linear-gradient(135deg,#9ca3af,#6b7280); }
    .hospital-order-row:nth-child(3) .position-badge { background: linear-gradient(135deg,#c77b45,#98572f); }
    .move-controls { display: inline-flex; gap: 3px; }
    .move-controls .btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .order-auto-save { background: var(--bs-body-bg); }
    .order-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: var(--bs-success); }
    .order-status-dot.pending { background: var(--bs-warning); box-shadow: 0 0 0 4px rgba(255,193,7,.14); }
    @media (max-width: 767.98px) {
        .hospital-address, .hospital-rating { display: none; }
        .position-badge { width: 36px; height: 36px; border-radius: 11px; }
        .move-controls .move-edge { display: none; }
    }
    </style>

    <div class="card order-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <strong><i class="bi bi-hospital me-1"></i>출력 순서 (<?php echo count($hospitals); ?>개 병원)</strong>
                <div class="small text-body-secondary mt-1"><i class="bi bi-grip-vertical"></i> 행을 드래그하거나 화살표 버튼으로 위치를 바꾸세요.</div>
            </div>
            <div class="input-group input-group-sm" style="max-width:320px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="hospitalOrderSearch" class="form-control" placeholder="병원명 또는 주소 검색">
                <button class="btn btn-outline-secondary" type="button" id="clearHospitalSearch" title="검색 초기화"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <form method="post" id="hospitalOrderForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px" class="text-center">위치</th>
                            <th>병원</th>
                            <th>주소</th>
                            <th style="width:110px">평점</th>
                        </tr>
                    </thead>
                    <tbody id="hospitalOrderBody">
                    <?php foreach ($hospitals as $index => $hospital): ?>
                        <?php $position = (int) ($hospital['display_order'] ?: ($index + 1)); ?>
                        <tr class="hospital-order-row" draggable="true"
                            data-hospital-id="<?php echo (int) $hospital['id']; ?>"
                            data-search="<?php echo htmlspecialchars(mb_strtolower(($hospital['name'] ?? '') . ' ' . ($hospital['address'] ?? ''), 'UTF-8')); ?>">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-grip-vertical drag-handle" title="드래그하여 이동"></i>
                                    <span class="position-badge"><?php echo $position; ?></span>
                                </div>
                                <input type="hidden" class="hospital-position"
                                       name="positions[<?php echo (int) $hospital['id']; ?>]"
                                       value="<?php echo $position; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($hospital['name']); ?></strong>
                                        <div class="small text-body-secondary">ID <?php echo (int) $hospital['id']; ?></div>
                                    </div>
                                    <div class="move-controls" aria-label="위치 이동">
                                        <button type="button" class="btn btn-sm btn-outline-secondary move-edge" data-move="top" title="맨 위로"><i class="bi bi-chevron-bar-up"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" title="한 칸 위로"><i class="bi bi-chevron-up"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" title="한 칸 아래로"><i class="bi bi-chevron-down"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary move-edge" data-move="bottom" title="맨 아래로"><i class="bi bi-chevron-bar-down"></i></button>
                                    </div>
                                </div>
                            </td>
                            <td class="small hospital-address"><?php echo htmlspecialchars($hospital['address'] ?: '-'); ?></td>
                            <td class="small hospital-rating">
                                <i class="bi bi-star-fill text-warning"></i>
                                <?php echo number_format((float) $hospital['rating'], 1); ?>
                                <span class="text-body-secondary">(<?php echo (int) $hospital['review_count']; ?>)</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($hospitals)): ?>
                        <tr><td colspan="4" class="text-center text-body-secondary py-5">표시할 병원이 없습니다.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($hospitals)): ?>
                <div class="card-footer order-auto-save d-flex align-items-center gap-2">
                    <div class="small d-flex align-items-center gap-2">
                        <span class="order-status-dot" id="orderStatusDot"></span>
                        <span id="orderStatusText">순서를 바꾸면 자동으로 저장됩니다.</span>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
(function () {
    var search = document.getElementById('hospitalOrderSearch');
    var clearSearch = document.getElementById('clearHospitalSearch');
    var body = document.getElementById('hospitalOrderBody');
    var form = document.getElementById('hospitalOrderForm');
    var statusDot = document.getElementById('orderStatusDot');
    var statusText = document.getElementById('orderStatusText');
    var draggedRow = null;
    var saveInProgress = false;
    var saveQueued = false;
    if (!search || !body || !form) return;

    function rows() {
        return Array.prototype.slice.call(body.querySelectorAll('.hospital-order-row'));
    }

    function showSaving() {
        if (statusDot) statusDot.classList.add('pending');
        if (statusText) statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>순서를 저장하는 중...';
    }

    function saveOrder() {
        if (saveInProgress) {
            saveQueued = true;
            return;
        }

        saveInProgress = true;
        showSaving();
        var data = new FormData(form);
        data.append('ajax', '1');

        fetch(window.location.href, {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('저장 요청 실패');
            return response.json();
        })
        .then(function (result) {
            if (!result.success) throw new Error(result.message || '저장 실패');
            if (statusDot) statusDot.classList.remove('pending');
            if (statusText) statusText.textContent = '순서가 자동으로 저장되었습니다.';
        })
        .catch(function (error) {
            if (statusDot) statusDot.classList.add('pending');
            if (statusText) statusText.textContent = error.message || '저장하지 못했습니다. 다시 이동해주세요.';
        })
        .finally(function () {
            saveInProgress = false;
            if (saveQueued) {
                saveQueued = false;
                saveOrder();
            }
        });
    }

    function renumber() {
        rows().forEach(function (row, index) {
            var position = index + 1;
            var badge = row.querySelector('.position-badge');
            var input = row.querySelector('.hospital-position');
            if (badge) badge.textContent = position;
            if (input) input.value = position;
        });
    }

    function moveRow(row, direction) {
        var allRows = rows();
        var index = allRows.indexOf(row);
        if (index < 0) return;
        if (direction === 'top' && index > 0) body.insertBefore(row, allRows[0]);
        if (direction === 'up' && index > 0) body.insertBefore(row, allRows[index - 1]);
        if (direction === 'down' && index < allRows.length - 1) body.insertBefore(allRows[index + 1], row);
        if (direction === 'bottom' && index < allRows.length - 1) body.appendChild(row);
        if ((direction === 'top' || direction === 'up') && index === 0) return;
        if ((direction === 'down' || direction === 'bottom') && index === allRows.length - 1) return;
        renumber();
        saveOrder();
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    search.addEventListener('input', function () {
        var query = this.value.trim().toLocaleLowerCase();
        rows().forEach(function (row) {
            row.style.display = !query || (row.dataset.search || '').indexOf(query) !== -1 ? '' : 'none';
        });
    });

    if (clearSearch) clearSearch.addEventListener('click', function () {
        search.value = '';
        search.dispatchEvent(new Event('input'));
        search.focus();
    });

    body.addEventListener('click', function (event) {
        var button = event.target.closest('[data-move]');
        if (!button) return;
        var row = button.closest('.hospital-order-row');
        if (row) moveRow(row, button.dataset.move);
    });

    body.addEventListener('dragstart', function (event) {
        var row = event.target.closest('.hospital-order-row');
        if (!row) return;
        draggedRow = row;
        row.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.dataset.hospitalId || '');
    });

    body.addEventListener('dragover', function (event) {
        if (!draggedRow) return;
        var target = event.target.closest('.hospital-order-row');
        if (!target || target === draggedRow) return;
        event.preventDefault();
        rows().forEach(function (row) { row.classList.remove('drag-target'); });
        target.classList.add('drag-target');
        var rect = target.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        body.insertBefore(draggedRow, after ? target.nextSibling : target);
    });

    body.addEventListener('drop', function (event) {
        if (!draggedRow) return;
        event.preventDefault();
        renumber();
        saveOrder();
    });

    body.addEventListener('dragend', function () {
        rows().forEach(function (row) { row.classList.remove('is-dragging', 'drag-target'); });
        draggedRow = null;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
    });
})();
</script>

<?php
mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

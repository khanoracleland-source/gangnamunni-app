<?php
/**
 * 관리자 메뉴 관리 (드래그&드롭 메뉴 빌더)
 * - SortableJS 기반
 * - DB 테이블: gangnamunni_admin_menus
 */

session_start(); // 관리자 세션 시작 (한글 주석)

// 공통 DB/테마/권한 헬퍼 로드 (한글 주석)
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결 (한글 주석)
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    die('DB 연결 실패: ' . mysqli_connect_error());
}
mysqli_set_charset($connect, 'utf8mb4');

// 테마/권한 (한글 주석)
loadThemeSettings($connect);
require_admin_login();
require_permission('nav_settings'); // 기존 네비게이션 설정과 동일 레벨 권한 사용

// 메뉴 테이블 자동 생성 (한글 주석)
$create_sql = "CREATE TABLE IF NOT EXISTS `gangnamunni_admin_menus` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `label` VARCHAR(100) NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'bi-circle',
    `visible` TINYINT(1) NOT NULL DEFAULT 1,
    `target` ENUM('same','new','iframe') NOT NULL DEFAULT 'same',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_parent_sort` (`parent_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($connect, $create_sql);

// AJAX 저장 처리 (한글 주석)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tree') {
    header('Content-Type: application/json; charset=utf-8');
    $json = $_POST['tree_json'] ?? '[]';
    $data = json_decode($json, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'message' => '잘못된 데이터 형식']);
        exit;
    }

    mysqli_begin_transaction($connect);
    try {
        // 기존 데이터 전체 삭제
        mysqli_query($connect, "DELETE FROM gangnamunni_admin_menus");

        // 재귀적으로 삽입 (한글 주석)
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_admin_menus (parent_id, sort_order, label, url, icon, visible, target)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $insertNode = function ($nodes, $parentId = null) use (&$insertNode, $stmt) {
            $order = 0;
            foreach ($nodes as $node) {
                $label = $node['label'] ?? '';
                $url = $node['url'] ?? '#';
                $icon = $node['icon'] ?? 'bi-dot';
                $visible = !empty($node['visible']) ? 1 : 0;
                $target = in_array($node['target'] ?? 'same', ['same','new','iframe'], true) ? $node['target'] : 'same';

                mysqli_stmt_bind_param(
                    $stmt,
                    'iisssis',
                    $parentId,
                    $order,
                    $label,
                    $url,
                    $icon,
                    $visible,
                    $target
                );
                mysqli_stmt_execute($stmt);
                $newParentId = mysqli_insert_id(mysqli_stmt_get_link($stmt));
                if (!empty($node['children']) && is_array($node['children'])) {
                    $insertNode($node['children'], $newParentId);
                }
                $order++;
            }
        };

        $insertNode($data, null);

        mysqli_commit($connect);
        echo json_encode(['success' => true, 'message' => '메뉴 구조가 저장되었습니다.']);
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        echo json_encode(['success' => false, 'message' => '저장 실패: ' . $e->getMessage()]);
    }
    exit;
}

// 현재 메뉴 트리 조회 (한글 주석)
$tree = [];
$result = mysqli_query($connect, "SELECT * FROM gangnamunni_admin_menus ORDER BY parent_id IS NOT NULL, parent_id, sort_order, id");
if ($result) {
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    // id → children 매핑
    $byId = [];
    foreach ($rows as $r) {
        $r['children'] = [];
        $byId[$r['id']] = $r;
    }
    foreach ($byId as $id => &$node) {
        if ($node['parent_id']) {
            $byId[$node['parent_id']]['children'][] = &$node;
        }
    }
    unset($node);
    foreach ($byId as $id => $node) {
        if (!$node['parent_id']) {
            $tree[] = $node;
        }
    }
}

// 페이지 메타 (한글 주석)
$admin_page_title = '사이드바 메뉴 관리';
$admin_current = 'nav_settings'; // 기존 네비게이션 설정 그룹에 속하도록 설정
$admin_page_description = '좌측 사이드바 메뉴 구조를 드래그&드롭으로 관리하고, 표시 여부 및 링크 타겟을 설정합니다.';

include __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-diagram-3"></i> 사이드바 메뉴 관리</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> 새로고침
        </button>
    </div>

    <div class="alert alert-warning small">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> 중요 안내 (2026-06-19 정정):</strong><br>
        - 이 페이지의 저장 내용은 <code>gangnamunni_admin_menus</code> 테이블에 기록되지만,
        <u>현재 좌측 사이드바는 이 테이블을 읽지 않고</u> <code>includes/admin_header.php</code>의 메뉴 정의를 사용합니다.
        따라서 <strong>여기서 재정렬·표시/숨김을 저장해도 실제 사이드바에는 자동 반영되지 않습니다</strong> (DB 연동 개발 예정).<br>
        - 사이드바 메뉴를 실제로 바꾸려면 현재는 <code>includes/admin_header.php</code>의 <code>$admin_menus</code> 배열을 편집해야 합니다.
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-list-task"></i> 메뉴 구조</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="addRootItem()">
                        <i class="bi bi-plus-lg"></i> 상위 메뉴 추가
                    </button>
                </div>
                <div class="card-body">
                    <ul id="menuTree" class="list-group list-group-flush">
                        <!-- 드래그&드롭 메뉴 트리 -->
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <span class="fw-bold"><i class="bi bi-pencil-square"></i> 선택한 메뉴 편집</span>
                </div>
                <div class="card-body">
                    <div id="noSelectionHelp" class="text-body-secondary small mb-2">
                        왼쪽 메뉴 항목을 클릭하면 이곳에서 상세 설정을 편집할 수 있습니다.
                    </div>
                    <form id="menuEditForm">
                        <input type="hidden" id="editNodeId">
                        <div class="mb-2">
                            <label class="form-label fw-semibold">레이블(표시 이름)</label>
                            <input type="text" class="form-control form-control-sm" id="editLabel">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">URL</label>
                            <input type="text" class="form-control form-control-sm" id="editUrl" placeholder="예: admin_hospitals.php">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">아이콘 클래스</label>
                            <input type="text" class="form-control form-control-sm" id="editIcon" placeholder="예: bi-building">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold d-block">표시 여부 / 링크 타겟</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editVisible">
                                    <label class="form-check-label small" for="editVisible">사이드바에 표시</label>
                                </div>
                                <select class="form-select form-select-sm" id="editTarget" style="max-width:180px;">
                                    <option value="same">같은 탭에서 열기</option>
                                    <option value="new">새 탭에서 열기</option>
                                    <option value="iframe">오른쪽 영역 iframe</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteCurrentNode()">
                                <i class="bi bi-trash"></i> 현재 메뉴 삭제
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="saveMenuTree()">
                                <i class="bi bi-save"></i> 전체 저장
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ===== 클라이언트 사이드 메뉴 트리 데이터 (한글 주석) =====
var menuTreeData = <?php echo json_encode($tree, JSON_UNESCAPED_UNICODE); ?>;
var currentEditId = null;

function renderTree() {
    var root = document.getElementById('menuTree');
    root.innerHTML = '';
    menuTreeData.forEach(function (node) {
        root.appendChild(renderNode(node));
    });
    initSortables();
}

function renderNode(node) {
    var li = document.createElement('li');
    li.className = 'list-group-item py-1';
    li.dataset.id = node.id || ('tmp_' + Math.random().toString(36).slice(2));

    var row = document.createElement('div');
    row.className = 'd-flex align-items-center justify-content-between gap-2';
    row.innerHTML =
        '<div class="d-flex align-items-center gap-2 flex-grow-1">' +
        '  <span class="text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></span>' +
        '  <span class="menu-label text-truncate" style="cursor:pointer;">' + (node.label || '(제목 없음)') + '</span>' +
        '</div>' +
        '<div class="d-flex align-items-center gap-2">' +
        '  <span class="badge ' + (node.visible ? 'bg-success' : 'bg-secondary') + ' badge-visible" style="font-size:11px;">' + (node.visible ? '표시' : '숨김') + '</span>' +
        '  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addChildNode(\'' + li.dataset.id + '\')"><i class="bi bi-plus-lg"></i></button>' +
        '</div>';

    row.querySelector('.menu-label').addEventListener('click', function () {
        selectNode(li.dataset.id);
    });

    li.appendChild(row);
    var childrenUl = document.createElement('ul');
    childrenUl.className = 'list-group list-group-flush ms-4 mt-1';
    childrenUl.dataset.childrenOf = li.dataset.id;
    if (node.children && node.children.length) {
        node.children.forEach(function (child) {
            childrenUl.appendChild(renderNode(child));
        });
    }
    li.appendChild(childrenUl);
    return li;
}

function flattenTree() {
    var root = document.getElementById('menuTree');
    function buildArray(container) {
        var arr = [];
        container.querySelectorAll(':scope > li').forEach(function (li, index) {
            var id = li.dataset.id;
            var node = findNodeById(id, menuTreeData) || { id: id, label: '', url: '', icon: 'bi-dot', visible: 1, target: 'same', children: [] };
            var childContainer = li.querySelector('ul');
            var children = childContainer ? buildArray(childContainer) : [];
            arr.push({
                id: (typeof node.id === 'number') ? node.id : null,
                label: node.label || '',
                url: node.url || '',
                icon: node.icon || 'bi-dot',
                visible: node.visible ? 1 : 0,
                target: node.target || 'same',
                children: children
            });
        });
        return arr;
    }
    return buildArray(root);
}

function findNodeById(id, nodes) {
    for (var i = 0; i < nodes.length; i++) {
        if (String(nodes[i].id || nodes[i]._id) === String(id)) return nodes[i];
        if (nodes[i].children) {
            var found = findNodeById(id, nodes[i].children);
            if (found) return found;
        }
    }
    return null;
}

function selectNode(id) {
    currentEditId = id;
    var node = findNodeById(id, menuTreeData);
    if (!node) return;
    document.getElementById('editNodeId').value = id;
    document.getElementById('editLabel').value = node.label || '';
    document.getElementById('editUrl').value = node.url || '';
    document.getElementById('editIcon').value = node.icon || '';
    document.getElementById('editVisible').checked = !!node.visible;
    document.getElementById('editTarget').value = node.target || 'same';
    document.getElementById('noSelectionHelp').style.display = 'none';
}

function syncFormToNode() {
    if (!currentEditId) return;
    var node = findNodeById(currentEditId, menuTreeData);
    if (!node) return;
    node.label = document.getElementById('editLabel').value.trim();
    node.url = document.getElementById('editUrl').value.trim();
    node.icon = document.getElementById('editIcon').value.trim() || 'bi-dot';
    node.visible = document.getElementById('editVisible').checked ? 1 : 0;
    node.target = document.getElementById('editTarget').value;
    renderTree();
    selectNode(currentEditId);
}

['editLabel','editUrl','editIcon','editVisible','editTarget'].forEach(function (id) {
    var el = document.getElementById(id);
    el.addEventListener('change', syncFormToNode);
    if (el.tagName === 'INPUT' && el.type === 'text') {
        el.addEventListener('keyup', function () {
            syncFormToNode();
        });
    }
});

function addRootItem() {
    menuTreeData.push({
        id: null,
        label: '새 메뉴',
        url: '#',
        icon: 'bi-dot',
        visible: 1,
        target: 'same',
        children: []
    });
    renderTree();
}

function addChildNode(parentDomId) {
    var parentNode = findNodeById(parentDomId, menuTreeData);
    if (!parentNode) return;
    if (!Array.isArray(parentNode.children)) parentNode.children = [];
    parentNode.children.push({
        id: null,
        label: '새 하위 메뉴',
        url: '#',
        icon: 'bi-dot',
        visible: 1,
        target: 'same',
        children: []
    });
    renderTree();
}

function deleteCurrentNode() {
    if (!currentEditId) return;
    if (!confirm('선택한 메뉴와 하위 메뉴들을 삭제하시겠습니까?')) return;
    function removeById(id, nodes) {
        return nodes.filter(function (n) {
            if (String(n.id || n._id) === String(id)) return false;
            if (n.children) n.children = removeById(id, n.children);
            return true;
        });
    }
    menuTreeData = removeById(currentEditId, menuTreeData);
    currentEditId = null;
    document.getElementById('menuEditForm').reset();
    document.getElementById('noSelectionHelp').style.display = 'block';
    renderTree();
}

function initSortables() {
    // 루트
    Sortable.create(document.getElementById('menuTree'), {
        handle: '.bi-grip-vertical',
        animation: 150,
        group: 'menu',
        fallbackOnBody: true,
        swapThreshold: 0.65
    });
    // 하위 UL 들
    document.querySelectorAll('#menuTree ul').forEach(function (ul) {
        Sortable.create(ul, {
            handle: '.bi-grip-vertical',
            animation: 150,
            group: 'menu',
            fallbackOnBody: true,
            swapThreshold: 0.65
        });
    });
}

function saveMenuTree() {
    syncFormToNode();
    var flat = flattenTree();
    var fd = new FormData();
    fd.append('action', 'save_tree');
    fd.append('tree_json', JSON.stringify(flat));
    fetch('admin_menu_manager.php', {
        method: 'POST',
        body: fd
    }).then(function (r) { return r.json(); })
      .then(function (data) {
          alert(data.message || (data.success ? '저장되었습니다.' : '저장 실패'));
      }).catch(function () {
          alert('저장 중 오류가 발생했습니다.');
      });
}

// 초기 렌더링
renderTree();
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>


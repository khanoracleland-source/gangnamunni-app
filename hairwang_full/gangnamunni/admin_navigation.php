<?php
/**
 * ========================================
 * 하단 네비게이션 메뉴 관리 페이지 (v2)
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 * - 추가/수정 모두 모달 방식 (카테고리와 동일)
 * - 이모지 피커 (8탭, 264종)
 * - getOrCreateInstance + activeFormPrefix 패턴
 * ========================================
 */

session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단

// 관리자 권한 확인
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('navigation');
if (!function_exists("term")) { require_once __DIR__ . "/includes/industry_terms.php"; }

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

$message = '';
$error = '';

// 메뉴 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_menu'])) {
    $icon = trim($_POST['icon']);
    $label = trim($_POST['label']);
    $page_url = trim($_POST['page_url']);
    $display_order = intval($_POST['display_order']);
    $icon_type = isset($_POST['icon_type']) ? trim($_POST['icon_type']) : 'emoji';
    $icon_image = trim($_POST['icon_image'] ?? '');
    $icon_image_active = trim($_POST['icon_image_active'] ?? '');

    if (!empty($label) && !empty($page_url)) {
        $sql = "INSERT INTO navigation_menu (icon, label, page_url, icon_type, icon_image, icon_image_active, display_order, is_active)
                VALUES (
                    '" . mysqli_real_escape_string($connect, $icon) . "',
                    '" . mysqli_real_escape_string($connect, $label) . "',
                    '" . mysqli_real_escape_string($connect, $page_url) . "',
                    '" . mysqli_real_escape_string($connect, $icon_type) . "',
                    '" . mysqli_real_escape_string($connect, $icon_image) . "',
                    '" . mysqli_real_escape_string($connect, $icon_image_active) . "',
                    {$display_order},
                    1
                )";

        if (mysqli_query($connect, $sql)) {
            $message = "메뉴가 추가되었습니다.";
        } else {
            $error = "메뉴 추가 실패: " . mysqli_error($connect);
        }
    } else {
        $error = "필수 필드를 입력해주세요.";
    }
}

// 메뉴 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_menu'])) {
    $id = intval($_POST['id']);
    $icon = trim($_POST['icon']);
    $label = trim($_POST['label']);
    $page_url = trim($_POST['page_url']);
    $display_order = intval($_POST['display_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $icon_type = isset($_POST['icon_type']) ? trim($_POST['icon_type']) : 'emoji';
    $icon_image = trim($_POST['icon_image'] ?? '');
    $icon_image_active = trim($_POST['icon_image_active'] ?? '');

    $sql = "UPDATE navigation_menu SET
            icon = '" . mysqli_real_escape_string($connect, $icon) . "',
            label = '" . mysqli_real_escape_string($connect, $label) . "',
            page_url = '" . mysqli_real_escape_string($connect, $page_url) . "',
            icon_type = '" . mysqli_real_escape_string($connect, $icon_type) . "',
            icon_image = '" . mysqli_real_escape_string($connect, $icon_image) . "',
            icon_image_active = '" . mysqli_real_escape_string($connect, $icon_image_active) . "',
            display_order = {$display_order},
            is_active = {$is_active}
            WHERE id = {$id}";

    if (mysqli_query($connect, $sql)) {
        $message = "메뉴가 수정되었습니다.";
    } else {
        $error = "메뉴 수정 실패: " . mysqli_error($connect);
    }
}

// 메뉴 삭제
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM navigation_menu WHERE id = {$id}";

    if (mysqli_query($connect, $sql)) {
        $message = "메뉴가 삭제되었습니다.";
    } else {
        $error = "메뉴 삭제 실패: " . mysqli_error($connect);
    }
}

// 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "DELETE FROM navigation_menu WHERE id IN ({$id_list})")) {
            $message = count($ids) . "개 메뉴가 삭제되었습니다.";
        } else {
            $error = "일괄 삭제 실패: " . mysqli_error($connect);
        }
    }
}

// 일괄 활성화/비활성화
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_toggle') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $toggle_value = (int)($_POST['toggle_value'] ?? 1);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "UPDATE navigation_menu SET is_active = {$toggle_value} WHERE id IN ({$id_list})")) {
            $message = count($ids) . "개 메뉴가" . ($toggle_value ? '활성화' : '비활성화') . "되었습니다.";
        } else {
            $error = "일괄 변경 실패: " . mysqli_error($connect);
        }
    }
}

// ---- 기본 네비게이션 저장 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_default') {
    mysqli_query($connect, "DROP TABLE IF EXISTS navigation_menu_default");
    mysqli_query($connect, "CREATE TABLE navigation_menu_default LIKE navigation_menu");
    if (mysqli_query($connect, "INSERT INTO navigation_menu_default SELECT * FROM navigation_menu")) {
        $cnt = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM navigation_menu_default"))['c'];
        $message = "현재 네비게이션 {$cnt}개를 기본값으로 저장했습니다.";
    } else {
        $error = '기본 네비게이션 저장 실패: ' . mysqli_error($connect);
    }
}

// ---- 기본 네비게이션 복구 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_default') {
    $chk = mysqli_query($connect, "SHOW TABLES LIKE 'navigation_menu_default'");
    if (mysqli_num_rows($chk) > 0) {
        mysqli_query($connect, "TRUNCATE TABLE navigation_menu");
        if (mysqli_query($connect, "INSERT INTO navigation_menu SELECT * FROM navigation_menu_default")) {
            $cnt = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM navigation_menu"))['c'];
            $message = "기본 네비게이션 {$cnt}개를 복구했습니다.";
        } else {
            $error = '복구 실패: ' . mysqli_error($connect);
        }
    } else {
        $error = '저장된 기본 네비게이션이 없습니다. 먼저 "기본 저장"을 실행하세요.';
    }
}

// 검색어
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지당 행수
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// 메뉴 목록 조회 (검색 + 페이징)
$where = "1=1";
if (!empty($search)) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (label LIKE '%{$s}%' OR page_url LIKE '%{$s}%' OR icon LIKE '%{$s}%')";
}

$total = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM navigation_menu WHERE {$where}"))['cnt'];
$result = mysqli_query($connect, "SELECT * FROM navigation_menu WHERE {$where} ORDER BY display_order ASC, id ASC LIMIT {$offset}, {$per_page}");
$menus = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $menus[] = $row;
    }
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 전체 메뉴 수 (추가 폼의 기본 순서값용)
$all_count = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM navigation_menu"))['cnt'];

// 백업 테이블 존재 여부
$has_default = mysqli_num_rows(mysqli_query($connect, "SHOW TABLES LIKE 'navigation_menu_default'")) > 0;
$default_count = 0;
if ($has_default) {
    $default_count = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM navigation_menu_default"))['c'];
}

// 공통 헤더 include
$admin_page_title = term('navigation') . ' 관리';
$admin_current = 'nav_settings';
$admin_extra_css = '
    /* 이모지 피커 (카테고리 페이지와 동일) */
    .emoji-picker { display:none; position:absolute; z-index:1060; background:#fff; border:1px solid #dee2e6; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.18); padding:0; width:720px; max-width:95vw; left:50%; transform:translateX(-50%); }
    .emoji-picker.show { display:block; }
    .emoji-tabs { display:flex; flex-wrap:nowrap; gap:0; border-bottom:2px solid #e9ecef; padding:8px 12px 0; background:#f8f9fa; border-radius:10px 10px 0 0; overflow-x:auto; }
    .emoji-tab { padding:10px 16px; font-size:14px; color:#6c757d; cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.15s; white-space:nowrap; border-radius:6px 6px 0 0; }
    .emoji-tab:hover { color:#0d6efd; background:#e7f1ff; }
    .emoji-tab.active { color:#0d6efd; border-bottom-color:#0d6efd; font-weight:600; background:#fff; }
    .emoji-grid-wrap { padding:16px; }
    .emoji-grid { display:grid; grid-template-columns:repeat(11, 1fr); gap:6px; min-height:120px; max-height:none; overflow:visible; }
    .emoji-item { width:52px; height:52px; display:flex; align-items:center; justify-content:center; font-size:28px; cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .emoji-item:hover { background:#e9ecef; transform:scale(1.15); }
    /* 아이콘 미리보기 */
    .icon-preview-box { font-size:24px; min-width:48px; height:48px; display:inline-flex; align-items:center; justify-content:center; background:#f8f9fa; border-radius:8px; border:1px solid #dee2e6; }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-list"></i> 네비게이션 관리</h5>
    <div class="d-flex gap-2 flex-wrap">
        <!-- 기본 네비게이션 복구 -->
        <form method="POST" class="d-inline" onsubmit="return confirm('기본 네비게이션으로 복구하시겠습니까?\n현재 데이터가 모두 기본값으로 대체됩니다.');">
            <input type="hidden" name="action" value="restore_default">
            <button class="btn btn-outline-warning btn-sm" <?php echo !$has_default ? 'disabled' : ''; ?>
                    title="<?php echo $has_default ? "저장된 기본 네비게이션 {$default_count}개로 복구" : '저장된 기본값 없음'; ?>">
                <i class="bi bi-arrow-counterclockwise"></i> 기본 복구
            </button>
        </form>
        <!-- 기본 네비게이션 저장 -->
        <form method="POST" class="d-inline" onsubmit="return confirm('현재 네비게이션을 기본값으로 저장하시겠습니까?');">
            <input type="hidden" name="action" value="save_default">
            <button class="btn btn-outline-success btn-sm">
                <i class="bi bi-download"></i> 기본 저장
            </button>
        </form>
        <!-- 메뉴 추가 -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> 메뉴 추가
        </button>
    </div>
</div>

<!-- [추가 2026-06-19] 실제 반영 경로 안내 -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small">
        <strong>안내:</strong> 이 페이지(<code>navigation_menu</code> 테이블)의 편집은 현재 프론트 하단 탭에 <u>자동 반영되지 않습니다</u>.
        실제 하단 탭 표시/숨김은 <strong>사이드바 → "하단 탭 설정"</strong>(<code>admin_nav_settings.php</code>)에서 관리되며,
        하단 탭 항목·아이콘은 <code>includes/bottom_nav.php</code>에 정의되어 있습니다.
    </div>
</div>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="메뉴명, URL로 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10,20,50,100] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total); ?>건</span>
    </div>
</div>

<!-- 일괄 작업 바 -->
<div id="bulkActions" class="alert alert-info py-2 align-items-center gap-2 mb-2" style="display:none;">
    <span><strong id="selectedCount">0</strong>개 선택</span>
    <form method="POST" class="d-inline" id="bulkDeleteForm" onsubmit="return confirmBulk('선택한 항목을 삭제하시겠습니까?', 'bulk_delete')">
        <input type="hidden" name="action" value="bulk_delete">
        <span class="bulk-ids-slot"></span>
        <button class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> 선택 삭제</button>
    </form>
    <form method="POST" class="d-inline" id="bulkActivateForm" onsubmit="return confirmBulk('선택한 항목을 활성화하시겠습니까?', 'bulk_toggle')">
        <input type="hidden" name="action" value="bulk_toggle">
        <input type="hidden" name="toggle_value" value="1">
        <span class="bulk-ids-slot"></span>
        <button class="btn btn-success btn-sm ms-1"><i class="bi bi-check-circle"></i> 활성화</button>
    </form>
    <form method="POST" class="d-inline" id="bulkDeactivateForm" onsubmit="return confirmBulk('선택한 항목을 비활성화하시겠습니까?', 'bulk_toggle')">
        <input type="hidden" name="action" value="bulk_toggle">
        <input type="hidden" name="toggle_value" value="0">
        <span class="bulk-ids-slot"></span>
        <button class="btn btn-warning btn-sm ms-1"><i class="bi bi-x-circle"></i> 비활성화</button>
    </form>
</div>

<!-- 메뉴 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="navTable">
        <thead class="table-light">
            <tr>
                <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"></th>
                <th style="width:60px;">순서</th>
                <th style="width:60px;">아이콘</th>
                <th>메뉴명</th>
                <th>페이지 URL</th>
                <th style="width:80px;">타입</th>
                <th style="width:70px;">상태</th>
                <th style="width:120px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($menus)): ?>
            <tr><td colspan="8" class="empty-state"><i class="bi bi-list"></i>등록된 메뉴가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($menus as $menu): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input row-check" value="<?php echo $menu['id']; ?>" onchange="updateBulkActions()"></td>
                <td class="text-center"><?php echo $menu['display_order']; ?></td>
                <td class="text-center" style="font-size:20px;">
                    <?php if (($menu['icon_type'] ?? 'emoji') === 'image' && !empty($menu['icon_image'])): ?>
                        <img src="<?php echo htmlspecialchars($menu['icon_image']); ?>" alt="" style="width:24px;height:24px;object-fit:contain;"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2724%27 height=%2724%27%3E%3Crect fill=%27%23dee2e6%27 width=%2724%27 height=%2724%27 rx=%274%27/%3E%3C/svg%3E'">
                    <?php else: ?>
                        <?php echo htmlspecialchars($menu['icon']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($menu['label']); ?></td>
                <td class="text-break small"><?php echo htmlspecialchars($menu['page_url']); ?></td>
                <td>
                    <?php
                    $icon_type = $menu['icon_type'] ?? 'emoji';
                    $type_color = $icon_type === 'image' ? 'info' : 'secondary';
                    $type_label = $icon_type === 'image' ? '이미지' : '이모지';
                    ?>
                    <span class="badge bg-<?php echo $type_color; ?>"><?php echo $type_label; ?></span>
                </td>
                <td>
                    <?php if ($menu['is_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick='editMenu(<?php echo json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)' title="수정">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?delete=<?php echo $menu['id']; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>"
                           class="btn btn-outline-danger" onclick="return confirm('정말 삭제하시겠습니까?')" title="삭제">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?per_page=<?php echo $per_page; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a></li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_pages, $page + 4);
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item"><a class="page-link" href="?per_page=<?php echo $per_page; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- 사용 안내 카드 -->
<div class="card mt-3">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-info-circle"></i> 사용 안내</h6>
    </div>
    <div class="card-body py-2">
        <ul class="small text-body-secondary mb-0" style="line-height:2;">
            <li><strong>아이콘 타입:</strong> 이모지 또는 이미지 중 선택</li>
            <li><strong>이모지:</strong> 피커에서 선택, 선택되지 않은 메뉴는 자동으로 흑백 처리됨</li>
            <li><strong>이미지:</strong> 일반 이미지(흑백)와 활성 이미지(컬러) 각각 URL 입력</li>
            <li>페이지 URL은 실제 파일명을 입력하세요 (예: index.php, hospitals.php)</li>
            <li>표시 순서는 숫자가 작을수록 왼쪽에 표시됩니다</li>
            <li>최대 5~6개의 메뉴를 권장합니다 (모바일 화면 고려)</li>
        </ul>
    </div>
</div>

<!-- ====================================================
     추가 모달: 메뉴 추가 (v2: 이모지 피커 포함)
     ==================================================== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle"></i> 새 메뉴 추가</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 메뉴명 -->
                    <div class="mb-3">
                        <label class="form-label">메뉴명 <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control form-control-sm" placeholder="홈" required>
                    </div>
                    <!-- 페이지 URL -->
                    <div class="mb-3">
                        <label class="form-label">페이지 URL <span class="text-danger">*</span></label>
                        <input type="text" name="page_url" class="form-control form-control-sm" placeholder="index.php" required>
                    </div>
                    <!-- 아이콘 타입 -->
                    <div class="mb-3">
                        <label class="form-label">아이콘 타입</label>
                        <select name="icon_type" class="form-select form-select-sm" id="add_icon_type" onchange="toggleIconFields('add')">
                            <option value="emoji">이모지</option>
                            <option value="image">이미지</option>
                        </select>
                    </div>
                    <!-- 이모지 아이콘 (피커 포함) -->
                    <div class="mb-3 position-relative" id="add_emojiField">
                        <label class="form-label">이모지 아이콘</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="icon" id="addIcon" class="form-control" placeholder="클릭하여 선택" readonly onclick="toggleEmojiPicker('addEmojiPicker', this)">
                            <span class="input-group-text" id="addIconPreview" style="font-size:20px;min-width:42px;justify-content:center;"></span>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEmojiPicker('addEmojiPicker', document.getElementById('addIcon'))"><i class="bi bi-emoji-smile"></i></button>
                        </div>
                        <!-- 이모지 피커 (8탭 264종) -->
                        <div class="emoji-picker" id="addEmojiPicker">
                            <div class="emoji-tabs" id="addEmojiTabs"></div>
                            <div class="emoji-grid-wrap">
                                <div class="emoji-grid" id="addEmojiGrid"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 이미지 URL (일반) -->
                    <div class="mb-3" id="add_imageField" style="display:none;">
                        <label class="form-label">아이콘 이미지 URL (일반)</label>
                        <input type="text" name="icon_image" class="form-control form-control-sm" placeholder="https://example.com/icon.png">
                        <div class="form-text">선택되지 않은 상태 아이콘 (흑백)</div>
                    </div>
                    <!-- 이미지 URL (활성) -->
                    <div class="mb-3" id="add_imageActiveField" style="display:none;">
                        <label class="form-label">아이콘 이미지 URL (활성)</label>
                        <input type="text" name="icon_image_active" class="form-control form-control-sm" placeholder="https://example.com/icon-active.png">
                        <div class="form-text">선택된 상태 아이콘 (컬러)</div>
                    </div>
                    <!-- 표시 순서 -->
                    <div class="mb-3">
                        <label class="form-label">표시 순서</label>
                        <input type="number" name="display_order" class="form-control form-control-sm" value="<?php echo $all_count + 1; ?>" min="0" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_menu" class="btn btn-sm btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================
     수정 모달: 메뉴 수정 (v2: 이모지 피커 포함)
     ==================================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 메뉴 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 메뉴명 -->
                    <div class="mb-3">
                        <label class="form-label">메뉴명 <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="edit_label" class="form-control form-control-sm" required>
                    </div>
                    <!-- 페이지 URL -->
                    <div class="mb-3">
                        <label class="form-label">페이지 URL <span class="text-danger">*</span></label>
                        <input type="text" name="page_url" id="edit_page_url" class="form-control form-control-sm" required>
                    </div>
                    <!-- 아이콘 타입 -->
                    <div class="mb-3">
                        <label class="form-label">아이콘 타입</label>
                        <select name="icon_type" class="form-select form-select-sm" id="edit_icon_type" onchange="toggleIconFields('edit')">
                            <option value="emoji">이모지</option>
                            <option value="image">이미지</option>
                        </select>
                    </div>
                    <!-- 이모지 아이콘 (피커 포함) -->
                    <div class="mb-3 position-relative" id="edit_emojiField">
                        <label class="form-label">이모지 아이콘</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="icon" id="edit_icon" class="form-control" placeholder="클릭하여 선택" readonly onclick="toggleEmojiPicker('editEmojiPicker', this)">
                            <span class="input-group-text" id="editIconPreview" style="font-size:20px;min-width:42px;justify-content:center;"></span>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEmojiPicker('editEmojiPicker', document.getElementById('edit_icon'))"><i class="bi bi-emoji-smile"></i></button>
                        </div>
                        <!-- 이모지 피커 (8탭 264종) -->
                        <div class="emoji-picker" id="editEmojiPicker">
                            <div class="emoji-tabs" id="editEmojiTabs"></div>
                            <div class="emoji-grid-wrap">
                                <div class="emoji-grid" id="editEmojiGrid"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 이미지 URL (일반) -->
                    <div class="mb-3" id="edit_imageField" style="display:none;">
                        <label class="form-label">아이콘 이미지 URL (일반)</label>
                        <input type="text" name="icon_image" id="edit_icon_image" class="form-control form-control-sm" placeholder="https://example.com/icon.png">
                        <div class="form-text">선택되지 않은 상태 아이콘 (흑백)</div>
                    </div>
                    <!-- 이미지 URL (활성) -->
                    <div class="mb-3" id="edit_imageActiveField" style="display:none;">
                        <label class="form-label">아이콘 이미지 URL (활성)</label>
                        <input type="text" name="icon_image_active" id="edit_icon_image_active" class="form-control form-control-sm" placeholder="https://example.com/icon-active.png">
                        <div class="form-text">선택된 상태 아이콘 (컬러)</div>
                    </div>
                    <!-- 표시 순서 -->
                    <div class="mb-3">
                        <label class="form-label">표시 순서</label>
                        <input type="number" name="display_order" id="edit_display_order" class="form-control form-control-sm" min="0" required>
                    </div>
                    <!-- 활성화 체크 -->
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="form-check-input">
                        <label class="form-check-label" for="edit_is_active">활성화</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="edit_menu" class="btn btn-sm btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$admin_extra_js = '
// v2: 현재 활성 폼 추적 (add 또는 edit) - 카테고리 페이지와 동일 패턴
var activeFormPrefix = null;

// ---- 이모지 카테고리별 목록 (카테고리 페이지와 동일) ----
var EMOJI_CATEGORIES = {
    "의료": ["🏥","💉","💊","🩺","🩹","🔬","🧬","🦷","🫀","🧪","🩻","🩼","🧑‍⚕️","👨‍⚕️","👩‍⚕️","🚑","⚕️","🩸","🧫","🧲","🔭","🏨","🛏️","🪥","💺","🩴","🧰","📋","🗂️","📊","🔍","⏱️","🌡️"],
    "얼굴/신체": ["👁️","👀","👃","👄","💋","👂","🦴","💪","🦵","🦶","🫁","🧠","👤","👥","🫦","🦿","🦾","🤲","👐","🙌","🤝","✊","👶","🧒","👦","👧","🧑","👨","👩","🧔","👱","🧓","🫃"],
    "미용/뷰티": ["✨","💎","💄","💅","🪒","💇","💆","🧖","🧴","🪞","🌸","🌺","💐","🎀","👗","👠","💍","🧣","👒","👑","🪮","🧹","🎭","🩱","👙","👘","🥻","👔","🧥","🧤","🧦","🕶️","👓"],
    "하트/감정": ["😊","😍","🥰","💝","💖","❤️","🩷","💜","💙","💚","🤍","🖤","💛","🧡","❤️‍🔥","💕","💞","💓","💗","💘","🥹","😇","😘","😚","😙","🫠","🤪","😜","😝","😛","🫶","❣️","💟"],
    "스마일": ["😀","😃","😄","😁","😆","🤣","😂","🙂","😉","😌","😎","🤩","🥳","😏","🤔","🤗","🫡","🤭","🫣","😶","😑","🙄","😤","😠","😡","🤬","😈","👿","💀","☠️","😱","😨","😰"],
    "손/제스처": ["👍","👎","👌","✌️","🤞","🤟","🤘","🤙","👈","👉","👆","👇","☝️","✋","🤚","🖐️","🖖","👋","🫰","🫶","🫳","🫴","🤌","🤏","👊","✊","🤛","🤜","🙏","💅","🤳","🫵","🫱"],
    "자연/동물": ["🦋","🌿","🍀","🌙","☀️","🌈","❄️","🌊","🪷","🌻","🫧","💐","🌹","🌷","🌼","🍃","🌴","🐾","🦊","🐱","🐶","🕊️","🐰","🦄","🐬","🐠","🦜","🦚","🌳","🌲","🍄","🌾","🍁"],
    "기타": ["⭐","🌟","🔥","⚡","🎯","🏆","🎖️","📌","🔔","💡","🎉","🎊","🎁","🎵","🎶","🎨","📸","🏠","🚀","💫","🪄","🔮","🛡️","⚙️","🔧","📱","💻","🖥️","⌨️","🖱️","📡","🧭","♻️"]
};

// ---- 이모지 피커 초기화 (탭 포함) - 카테고리 페이지와 동일 ----
var emojiPickerState = {};

function initEmojiGrid(gridId, inputId, previewId) {
    var tabsId = gridId.replace("EmojiGrid", "EmojiTabs");
    var tabsEl = document.getElementById(tabsId);
    var gridEl = document.getElementById(gridId);
    if (!tabsEl || !gridEl) return;

    tabsEl.innerHTML = "";
    gridEl.innerHTML = "";

    emojiPickerState[gridId] = { inputId: inputId, previewId: previewId };

    var catNames = Object.keys(EMOJI_CATEGORIES);
    catNames.forEach(function(catName, idx) {
        var tab = document.createElement("button");
        tab.type = "button";
        tab.className = "emoji-tab" + (idx === 0 ? " active" : "");
        tab.textContent = catName;
        tab.setAttribute("data-cat", catName);
        tab.setAttribute("data-grid", gridId);
        tab.onclick = function() { switchEmojiTab(gridId, catName); };
        tabsEl.appendChild(tab);
    });

    renderEmojiCategory(gridId, catNames[0]);
}

function switchEmojiTab(gridId, catName) {
    var tabsId = gridId.replace("EmojiGrid", "EmojiTabs");
    var tabsEl = document.getElementById(tabsId);

    tabsEl.querySelectorAll(".emoji-tab").forEach(function(t) {
        t.classList.toggle("active", t.getAttribute("data-cat") === catName);
    });

    renderEmojiCategory(gridId, catName);
}

function renderEmojiCategory(gridId, catName) {
    var gridEl = document.getElementById(gridId);
    var state = emojiPickerState[gridId];
    if (!gridEl || !state) return;

    gridEl.innerHTML = "";
    var emojis = EMOJI_CATEGORIES[catName] || [];
    emojis.forEach(function(emoji) {
        var btn = document.createElement("div");
        btn.className = "emoji-item";
        btn.textContent = emoji;
        btn.onclick = function() {
            var input = document.getElementById(state.inputId);
            input.value = emoji;
            if (state.previewId) document.getElementById(state.previewId).textContent = emoji;
            closeAllEmojiPickers();
        };
        gridEl.appendChild(btn);
    });
}

function toggleEmojiPicker(pickerId, inputEl) {
    var picker = document.getElementById(pickerId);
    var isShow = picker.classList.contains("show");
    closeAllEmojiPickers();
    if (!isShow) {
        if (pickerId === "addEmojiPicker") initEmojiGrid("addEmojiGrid", "addIcon", "addIconPreview");
        else initEmojiGrid("editEmojiGrid", "edit_icon", "editIconPreview");
        picker.classList.add("show");
    }
}

function closeAllEmojiPickers() {
    document.querySelectorAll(".emoji-picker").forEach(function(p) { p.classList.remove("show"); });
}

// 외부 클릭 시 이모지 피커 닫기
document.addEventListener("click", function(e) {
    if (!e.target.closest(".emoji-picker") && !e.target.closest("[onclick*=toggleEmojiPicker]") && !e.target.closest(".btn-outline-secondary")) {
        closeAllEmojiPickers();
    }
});

// ---- 아이콘 타입 토글 (추가/수정 공용) ----
function toggleIconFields(prefix) {
    var iconType = document.getElementById(prefix + "_icon_type").value;
    var emojiField = document.getElementById(prefix + "_emojiField");
    var imageField = document.getElementById(prefix + "_imageField");
    var imageActiveField = document.getElementById(prefix + "_imageActiveField");

    if (iconType === "image") {
        emojiField.style.display = "none";
        imageField.style.display = "";
        imageActiveField.style.display = "";
    } else {
        emojiField.style.display = "";
        imageField.style.display = "none";
        imageActiveField.style.display = "none";
    }
}

// ---- 수정 모달 열기 (getOrCreateInstance 패턴) ----
function editMenu(menu) {
    activeFormPrefix = "edit";
    document.getElementById("edit_id").value = menu.id;
    document.getElementById("edit_label").value = menu.label;
    document.getElementById("edit_page_url").value = menu.page_url;
    document.getElementById("edit_icon_type").value = menu.icon_type || "emoji";
    document.getElementById("edit_icon").value = menu.icon || "";
    document.getElementById("editIconPreview").textContent = menu.icon || "";
    document.getElementById("edit_icon_image").value = menu.icon_image || "";
    document.getElementById("edit_icon_image_active").value = menu.icon_image_active || "";
    document.getElementById("edit_display_order").value = menu.display_order;
    document.getElementById("edit_is_active").checked = menu.is_active == 1;

    toggleIconFields("edit");

    // 이모지 피커 초기화
    initEmojiGrid("editEmojiGrid", "edit_icon", "editIconPreview");

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("editModal"));
    modal.show();
}

// 추가 모달 열릴 때 activeFormPrefix 설정
document.getElementById("addModal").addEventListener("show.bs.modal", function() {
    activeFormPrefix = "add";
});

// ---- 일괄 작업 (카테고리 페이지와 동일 패턴) ----
function confirmBulk(msg, action) {
    if (!confirm(msg)) return false;
    document.querySelectorAll(".bulk-ids-slot").forEach(function(slot) { slot.innerHTML = ""; });
    var form = event.target;
    var slot = form.querySelector(".bulk-ids-slot");
    document.querySelectorAll(".row-check:checked").forEach(function(cb) {
        var inp = document.createElement("input");
        inp.type = "hidden"; inp.name = "ids[]"; inp.value = cb.value;
        slot.appendChild(inp);
    });
    return true;
}
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

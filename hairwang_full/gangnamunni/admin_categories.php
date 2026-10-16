<?php
/**
 * 카테고리 관리 페이지 (v4)
 * - slug(영문명), icon_svg(SVG 파일경로) 필드
 * - SVG 아이콘 업로드/삭제 관리
 * - SVG 아이콘 인라인 썸네일 그리드 피커 (v4: select→grid)
 * - SVG 관리에서 직접 선택 기능 (v4)
 * - 이모지 선택 팝업 (그리드)
 * - 기본 카테고리 저장/복구 기능
 * - Bootstrap 5.3 + 공통 관리자 레이아웃
 */

session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
if (($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('최고관리자만 접근할 수 있습니다.');
}
if (!function_exists("term")) { require_once __DIR__ . "/includes/industry_terms.php"; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['ad_admin_logged_in'] = true;
        $_SESSION['ad_admin_user'] = $username;
        header('Location: admin_categories.php');
        exit;
    } else {
        $login_error = '로그인 정보가 올바르지 않습니다.';
    }
}

// 비로그인 시 로그인 폼 표시
if (!$is_logged_in):
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인 | <?php echo getTheme('site_name'); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card shadow-sm" style="width:100%;max-width:400px;">
        <div class="card-body p-4">
            <h5 class="card-title text-center mb-4"><i class="bi bi-shield-lock"></i> 관리자 로그인</h5>
            <?php if (isset($login_error)): ?>
            <div class="alert alert-danger py-2"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">아이디</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">비밀번호</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">로그인</button>
            </form>
            <p class="text-center text-body-secondary small mt-3 mb-0">기본 계정: admin / admin123</p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    mysqli_close($connect);
    exit;
endif;

// ========== 로그인된 사용자 전용 영역 ==========

// 로그아웃
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_categories.php');
    exit;
}

// ---- SVG 파일 목록 스캔 (아이콘 선택용) ----
$svg_dir = __DIR__ . '/assets/icons/categories/';
$svg_files = [];
if (is_dir($svg_dir)) {
    $files = glob($svg_dir . '*.svg');
    foreach ($files as $f) {
        $svg_files[] = 'assets/icons/categories/' . basename($f);
    }
    sort($svg_files);
}

$message = '';
$error = '';

// ---- SVG 파일 업로드 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_svg') {
    if (isset($_FILES['svg_file']) && $_FILES['svg_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['svg_file']['tmp_name'];
        $name = basename($_FILES['svg_file']['name']);
        // SVG 확장자 검증
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'svg') {
            $error = 'SVG 파일만 업로드 가능합니다.';
        } else {
            // 파일명 정리 (공백 → 언더스코어)
            $safe_name = preg_replace('/\s+/', '_', $name);
            $dest = $svg_dir . $safe_name;
            if (file_exists($dest)) {
                $error = "이미 존재하는 파일입니다: {$safe_name}";
            } elseif (move_uploaded_file($tmp, $dest)) {
                $message = "SVG 업로드 완료: {$safe_name}";
                // SVG 목록 갱신
                $svg_files = [];
                $files = glob($svg_dir . '*.svg');
                foreach ($files as $f) {
                    $svg_files[] = 'assets/icons/categories/' . basename($f);
                }
                sort($svg_files);
            } else {
                $error = 'SVG 업로드 실패 (권한 문제일 수 있습니다)';
            }
        }
    } else {
        $error = '파일을 선택해주세요.';
    }
}

// ---- SVG 파일 삭제 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_svg') {
    $svg_to_delete = $_POST['svg_path'] ?? '';
    if (!empty($svg_to_delete)) {
        $full_path = __DIR__ . '/' . $svg_to_delete;
        // 안전 경로 검증
        if (strpos(realpath($full_path), realpath($svg_dir)) === 0 && file_exists($full_path)) {
            // 사용 중인 카테고리 확인
            $esc = mysqli_real_escape_string($connect, $svg_to_delete);
            $chk = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_categories WHERE icon_svg = '{$esc}'");
            $using = (int)mysqli_fetch_assoc($chk)['cnt'];
            if ($using > 0) {
                $error = "이 SVG는 {$using}개 카테고리에서 사용 중입니다. 먼저 카테고리의 SVG를 변경해주세요.";
            } elseif (unlink($full_path)) {
                $message = "SVG 삭제 완료: " . basename($svg_to_delete);
                // SVG 목록 갱신
                $svg_files = [];
                $files = glob($svg_dir . '*.svg');
                foreach ($files as $f) {
                    $svg_files[] = 'assets/icons/categories/' . basename($f);
                }
                sort($svg_files);
            } else {
                $error = 'SVG 삭제 실패';
            }
        } else {
            $error = '유효하지 않은 SVG 경로입니다.';
        }
    }
}

// ---- 기본 카테고리 저장 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_default') {
    mysqli_query($connect, "DROP TABLE IF EXISTS gangnamunni_categories_default");
    mysqli_query($connect, "CREATE TABLE gangnamunni_categories_default LIKE gangnamunni_categories");
    if (mysqli_query($connect, "INSERT INTO gangnamunni_categories_default SELECT * FROM gangnamunni_categories")) {
        $cnt = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_categories_default"))['c'];
        $message = "현재 카테고리 {$cnt}개를 기본값으로 저장했습니다.";
    } else {
        $error = '기본 카테고리 저장 실패: ' . mysqli_error($connect);
    }
}

// ---- 기본 카테고리 복구 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_default') {
    $chk = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_categories_default'");
    if (mysqli_num_rows($chk) > 0) {
        mysqli_query($connect, "TRUNCATE TABLE gangnamunni_categories");
        if (mysqli_query($connect, "INSERT INTO gangnamunni_categories SELECT * FROM gangnamunni_categories_default")) {
            $cnt = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_categories"))['c'];
            $message = "기본 카테고리 {$cnt}개를 복구했습니다.";
        } else {
            $error = '복구 실패: ' . mysqli_error($connect);
        }
    } else {
        $error = '저장된 기본 카테고리가 없습니다. 먼저 "기본 카테고리 저장"을 실행하세요.';
    }
}

// ---- 카테고리 추가 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $slug = mysqli_real_escape_string($connect, $_POST['slug']);
    $icon = mysqli_real_escape_string($connect, $_POST['icon']);
    $icon_svg = mysqli_real_escape_string($connect, $_POST['icon_svg']);
    $display_order = intval($_POST['display_order']);

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    }

    $sql = "INSERT INTO gangnamunni_categories (name, slug, icon, icon_svg, display_order) VALUES ('{$name}', '{$slug}', '{$icon}', '{$icon_svg}', {$display_order})";
    if (mysqli_query($connect, $sql)) {
        $message = '카테고리가 추가되었습니다.';
    } else {
        $error = '추가 실패: ' . mysqli_error($connect);
    }
}

// ---- 카테고리 수정 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $slug = mysqli_real_escape_string($connect, $_POST['slug']);
    $icon = mysqli_real_escape_string($connect, $_POST['icon']);
    $icon_svg = mysqli_real_escape_string($connect, $_POST['icon_svg']);
    $display_order = intval($_POST['display_order']);

    $sql = "UPDATE gangnamunni_categories SET name = '{$name}', slug = '{$slug}', icon = '{$icon}', icon_svg = '{$icon_svg}', display_order = {$display_order} WHERE id = {$id}";
    if (mysqli_query($connect, $sql)) {
        $message = '카테고리가 수정되었습니다.';
    } else {
        $error = '수정 실패: ' . mysqli_error($connect);
    }
}

// ---- 카테고리 삭제 ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($connect, "DELETE FROM gangnamunni_categories WHERE id = {$id}")) {
        $message = '카테고리가 삭제되었습니다.';
    } else {
        $error = '삭제 실패: ' . mysqli_error($connect);
    }
}

// ---- 카테고리 활성화/비활성화 토글 ----
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    mysqli_query($connect, "UPDATE gangnamunni_categories SET is_active = 1 - is_active WHERE id = {$id}");
    header('Location: admin_categories.php');
    exit;
}

// ---- 일괄 삭제 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "DELETE FROM gangnamunni_categories WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 카테고리가 삭제되었습니다.';
        }
    }
}

// ---- 일괄 활성화 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_activate') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "UPDATE gangnamunni_categories SET is_active = 1 WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 카테고리가 활성화되었습니다.';
        }
    }
}

// ---- 일괄 비활성화 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_deactivate') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        if (mysqli_query($connect, "UPDATE gangnamunni_categories SET is_active = 0 WHERE id IN ({$id_list})")) {
            $message = count($ids) . '개 카테고리가 비활성화되었습니다.';
        }
    }
}

// ---- 검색 + 페이지네이션 ----
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = '1=1';
if (!empty($search)) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (name LIKE '%{$s}%' OR slug LIKE '%{$s}%' OR icon LIKE '%{$s}%')";
}

$total = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_categories WHERE {$where}"))['cnt'];
$result = mysqli_query($connect, "SELECT * FROM gangnamunni_categories WHERE {$where} ORDER BY display_order ASC, id ASC LIMIT {$offset}, {$per_page}");
$categories = [];
while ($row = mysqli_fetch_assoc($result)) $categories[] = $row;
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 백업 테이블 존재 여부
$has_default = mysqli_num_rows(mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_categories_default'")) > 0;
$default_count = 0;
if ($has_default) {
    $default_count = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_categories_default"))['c'];
}

// 공통 헤더 include
$admin_page_title = term('category') . ' 관리';
$admin_current = 'categories';
$admin_extra_css = '
    /* SVG 미리보기 - 흰색 배경 */
    .svg-preview { width:48px; height:48px; display:inline-flex; align-items:center; justify-content:center; background:#ffffff; border-radius:10px; border:1px solid #e0e0e0; }
    .svg-preview img { width:36px; height:36px; object-fit:contain; }
    /* SVG 관리 모달 썸네일 */
    .svg-thumb { width:64px; height:64px; display:inline-flex; align-items:center; justify-content:center; background:#fff; border-radius:8px; border:1px solid #dee2e6; cursor:pointer; transition:all 0.2s; }
    .svg-thumb:hover { border-color:#0d6efd; box-shadow:0 0 0 2px rgba(13,110,253,.25); }
    .svg-thumb img { width:48px; height:48px; object-fit:contain; }
    .svg-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:8px; max-height:400px; overflow-y:auto; }
    .svg-grid-item { display:flex; flex-direction:column; align-items:center; gap:4px; padding:8px; border:1px solid #dee2e6; border-radius:8px; background:#fff; position:relative; }
    .svg-grid-item .svg-name { font-size:10px; color:#6c757d; text-align:center; word-break:break-all; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .svg-grid-item .btn-delete-svg { position:absolute; top:2px; right:2px; padding:0 4px; font-size:10px; line-height:1.4; }
    .svg-grid-item.in-use { border-color:#198754; }
    .svg-grid-item.in-use::after { content:"사용중"; position:absolute; top:2px; left:2px; font-size:9px; background:#198754; color:#fff; padding:0 4px; border-radius:3px; }
    /* v4: SVG 인라인 피커 (추가/수정 모달용) */
    .svg-inline-picker { display:grid; grid-template-columns:repeat(4, 1fr); gap:6px; max-height:200px; overflow-y:auto; padding:4px; border:1px solid #dee2e6; border-radius:8px; background:#f8f9fa; }
    .svg-pick-item { display:flex; flex-direction:column; align-items:center; gap:2px; padding:6px 4px; border:2px solid transparent; border-radius:6px; background:#fff; cursor:pointer; transition:all 0.15s; }
    .svg-pick-item:hover { border-color:#0d6efd; background:#e7f1ff; }
    .svg-pick-item.selected { border-color:#0d6efd; background:#cfe2ff; box-shadow:0 0 0 2px rgba(13,110,253,.25); }
    .svg-pick-item img { width:36px; height:36px; object-fit:contain; }
    .svg-pick-item .pick-name { font-size:9px; color:#6c757d; text-align:center; max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .svg-pick-none { display:flex; align-items:center; justify-content:center; padding:6px 4px; border:2px solid transparent; border-radius:6px; background:#fff; cursor:pointer; transition:all 0.15s; font-size:11px; color:#6c757d; }
    .svg-pick-none:hover { border-color:#dc3545; background:#fff5f5; }
    .svg-pick-none.selected { border-color:#dc3545; background:#f8d7da; }
    /* v4: SVG 관리 모달 선택 버튼 */
    .svg-grid-item .btn-select-svg { font-size:10px; padding:1px 8px; margin-top:2px; }
    /* 이모지 피커 (v4.1: 대형 + 탭) */
    .emoji-picker { display:none; position:absolute; z-index:1060; background:#fff; border:1px solid #dee2e6; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.18); padding:0; width:720px; max-width:95vw; left:50%; transform:translateX(-50%); }
    .emoji-picker.show { display:block; }
    .emoji-tabs { display:flex; flex-wrap:nowrap; gap:0; border-bottom:2px solid #e9ecef; padding:8px 12px 0; background:#f8f9fa; border-radius:10px 10px 0 0; }
    .emoji-tab { padding:10px 16px; font-size:14px; color:#6c757d; cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.15s; white-space:nowrap; border-radius:6px 6px 0 0; }
    .emoji-tab:hover { color:#0d6efd; background:#e7f1ff; }
    .emoji-tab.active { color:#0d6efd; border-bottom-color:#0d6efd; font-weight:600; background:#fff; }
    .emoji-grid-wrap { padding:16px; }
    .emoji-grid { display:grid; grid-template-columns:repeat(11, 1fr); gap:6px; min-height:120px; max-height:none; overflow:visible; }
    .emoji-item { width:52px; height:52px; display:flex; align-items:center; justify-content:center; font-size:28px; cursor:pointer; border-radius:8px; transition:all 0.15s; }
    .emoji-item:hover { background:#e9ecef; transform:scale(1.15); }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-tags"></i> 카테고리 관리</h5>
    <div class="d-flex gap-2 flex-wrap">
        <!-- SVG 아이콘 관리 -->
        <button class="btn btn-outline-info btn-sm" onclick="openSvgManager()">
            <i class="bi bi-file-earmark-image"></i> SVG 관리 (<?php echo count($svg_files); ?>)
        </button>
        <!-- 기본 카테고리 복구 -->
        <form method="POST" class="d-inline" onsubmit="return confirm('기본 카테고리로 복구하시겠습니까?\n현재 데이터가 모두 기본값으로 대체됩니다.');">
            <input type="hidden" name="action" value="restore_default">
            <button class="btn btn-outline-warning btn-sm" <?php echo !$has_default ? 'disabled' : ''; ?>
                    title="<?php echo $has_default ? "저장된 기본 카테고리 {$default_count}개로 복구" : '저장된 기본값 없음'; ?>">
                <i class="bi bi-arrow-counterclockwise"></i> 기본 복구
            </button>
        </form>
        <!-- 기본 카테고리 저장 -->
        <form method="POST" class="d-inline" onsubmit="return confirm('현재 카테고리를 기본값으로 저장하시겠습니까?');">
            <input type="hidden" name="action" value="save_default">
            <button class="btn btn-outline-success btn-sm">
                <i class="bi bi-download"></i> 기본 저장
            </button>
        </form>
        <!-- 카테고리 추가 -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> 카테고리 추가
        </button>
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
        <input type="search" name="search" class="form-control form-control-sm" placeholder="카테고리명으로 검색..."
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
    <form method="POST" class="d-inline" id="bulkActivateForm" onsubmit="return confirmBulk('선택한 항목을 활성화하시겠습니까?', 'bulk_activate')">
        <input type="hidden" name="action" value="bulk_activate">
        <span class="bulk-ids-slot"></span>
        <button class="btn btn-success btn-sm ms-1"><i class="bi bi-check-circle"></i> 선택 활성화</button>
    </form>
    <form method="POST" class="d-inline" id="bulkDeactivateForm" onsubmit="return confirmBulk('선택한 항목을 비활성화하시겠습니까?', 'bulk_deactivate')">
        <input type="hidden" name="action" value="bulk_deactivate">
        <span class="bulk-ids-slot"></span>
        <button class="btn btn-warning btn-sm ms-1"><i class="bi bi-x-circle"></i> 선택 비활성화</button>
    </form>
</div>

<!-- 카테고리 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="categoryTable">
        <thead class="table-light">
            <tr>
                <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"></th>
                <th style="width:50px;">ID</th>
                <th style="width:60px;">SVG</th>
                <th style="width:50px;">이모지</th>
                <th>카테고리명</th>
                <th style="width:120px;">영문 slug</th>
                <th style="width:70px;">순서</th>
                <th style="width:60px;">상태</th>
                <th style="width:150px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
            <tr><td colspan="9" class="empty-state"><i class="bi bi-tags"></i>등록된 카테고리가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input row-check" value="<?php echo $cat['id']; ?>" onchange="updateBulkActions()"></td>
                <td class="text-center"><?php echo $cat['id']; ?></td>
                <td class="text-center">
                    <?php if (!empty($cat['icon_svg'])): ?>
                    <div class="svg-preview"><img src="<?php echo htmlspecialchars($cat['icon_svg']); ?>" alt="icon" onerror="this.style.display='none'"></div>
                    <?php else: ?>
                    <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center" style="font-size:20px;"><?php echo $cat['icon']; ?></td>
                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                <td><code class="small"><?php echo htmlspecialchars($cat['slug'] ?? ''); ?></code></td>
                <td class="text-center"><?php echo $cat['display_order']; ?></td>
                <td>
                    <?php if ($cat['is_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick='editCategory(<?php echo json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)' title="수정">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?toggle=<?php echo $cat['id']; ?>" class="btn btn-outline-info" title="<?php echo $cat['is_active'] ? '비활성화' : '활성화'; ?>">
                            <i class="bi bi-<?php echo $cat['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                        </a>
                        <a href="?delete=<?php echo $cat['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('정말 삭제하시겠습니까?')" title="삭제">
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

<!-- ====================================================
     SVG 관리 모달 (v4: 선택 버튼 추가)
     ==================================================== -->
<div class="modal fade" id="svgManageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-file-earmark-image"></i> SVG 아이콘 관리</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- SVG 안내 -->
                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>
                            <strong>SVG 아이콘 가이드</strong><br>
                            <small>
                                권장 크기: <code>60 x 60 px</code> (정사각형)<br>
                                형식: <code>.svg</code> (벡터 그래픽)<br>
                                서버 경로: <code>gangnamunni/assets/icons/categories/</code><br>
                                배경: 투명 권장 (표시 시 흰색 배경 자동 적용)
                            </small>
                        </div>
                    </div>
                </div>

                <!-- v4: 선택 모드 안내 (카테고리 편집 중일 때만 표시) -->
                <div class="alert alert-warning py-2 mb-3" id="svgSelectModeAlert" style="display:none;">
                    <i class="bi bi-hand-index-thumb"></i> <strong>선택 모드</strong>: 아이콘을 클릭하면 현재 편집 중인 카테고리에 적용됩니다.
                </div>

                <!-- SVG 업로드 -->
                <form method="POST" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="action" value="upload_svg">
                    <div class="input-group input-group-sm">
                        <input type="file" name="svg_file" class="form-control" accept=".svg" required>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> 업로드</button>
                    </div>
                    <div class="form-text">새 SVG 파일을 서버에 업로드합니다. 파일명은 그대로 유지됩니다.</div>
                </form>

                <!-- SVG 파일 목록 (그리드) -->
                <div class="svg-grid">
                    <?php
                    // 각 SVG가 카테고리에서 사용 중인지 확인
                    $used_svgs = [];
                    $res = mysqli_query($connect, "SELECT icon_svg FROM gangnamunni_categories WHERE icon_svg IS NOT NULL AND icon_svg != ''");
                    while ($r = mysqli_fetch_assoc($res)) $used_svgs[] = $r['icon_svg'];

                    foreach ($svg_files as $svg):
                        $in_use = in_array($svg, $used_svgs);
                    ?>
                    <div class="svg-grid-item <?php echo $in_use ? 'in-use' : ''; ?>">
                        <div class="svg-thumb" onclick="selectSvgFromManager('<?php echo htmlspecialchars($svg, ENT_QUOTES); ?>')" title="클릭하여 선택">
                            <img src="<?php echo htmlspecialchars($svg); ?>" alt="<?php echo basename($svg); ?>" onerror="this.parentElement.textContent='?'">
                        </div>
                        <div class="svg-name" title="<?php echo basename($svg); ?>"><?php echo basename($svg); ?></div>
                        <!-- v4: 선택 버튼 -->
                        <button type="button" class="btn btn-outline-primary btn-select-svg" onclick="selectSvgFromManager('<?php echo htmlspecialchars($svg, ENT_QUOTES); ?>')">
                            <i class="bi bi-check2"></i> 선택
                        </button>
                        <?php if (!$in_use): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('이 SVG를 삭제하시겠습니까?\n<?php echo basename($svg); ?>');">
                            <input type="hidden" name="action" value="delete_svg">
                            <input type="hidden" name="svg_path" value="<?php echo htmlspecialchars($svg); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-delete-svg" title="삭제"><i class="bi bi-x"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($svg_files)): ?>
                <div class="text-center text-body-secondary py-4">SVG 파일이 없습니다.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer py-2">
                <span class="text-body-secondary small me-auto">총 <?php echo count($svg_files); ?>개 SVG / <?php echo count($used_svgs); ?>개 사용중</span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================
     추가 모달: 카테고리 추가 (v4: SVG 인라인 피커)
     ==================================================== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle"></i> 카테고리 추가</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 카테고리명 -->
                    <div class="mb-3">
                        <label class="form-label">카테고리 이름 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="예: 피부관리" required>
                    </div>
                    <!-- 영문 slug -->
                    <div class="mb-3">
                        <label class="form-label">영문 slug</label>
                        <input type="text" name="slug" class="form-control form-control-sm" placeholder="예: skin_care (비워두면 자동생성)">
                        <div class="form-text">URL, API, HMS 연동 시 사용됩니다.</div>
                    </div>
                    <!-- v4: SVG 아이콘 인라인 피커 -->
                    <div class="mb-3">
                        <label class="form-label">SVG 아이콘</label>
                        <input type="hidden" name="icon_svg" id="add_icon_svg" value="">
                        <div class="svg-inline-picker" id="addSvgPicker">
                            <div class="svg-pick-none selected" onclick="selectSvgInline('', 'add')">
                                <i class="bi bi-x-circle me-1"></i>없음
                            </div>
                            <?php foreach ($svg_files as $svg): ?>
                            <div class="svg-pick-item" onclick="selectSvgInline('<?php echo htmlspecialchars($svg, ENT_QUOTES); ?>', 'add')" data-svg="<?php echo htmlspecialchars($svg); ?>">
                                <img src="<?php echo htmlspecialchars($svg); ?>" alt="<?php echo basename($svg); ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22/>'">
                                <span class="pick-name" title="<?php echo basename($svg); ?>"><?php echo basename($svg, '.svg'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">클릭하여 선택 | 권장: 60x60px SVG | <a href="#" onclick="openSvgManager('add'); return false;">SVG 관리/업로드</a></div>
                    </div>
                    <!-- 이모지 (폴백) -->
                    <div class="mb-3 position-relative">
                        <label class="form-label">이모지 (SVG 없을 때 폴백)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="icon" id="addIcon" class="form-control" placeholder="클릭하여 선택" readonly onclick="toggleEmojiPicker('addEmojiPicker', this)">
                            <span class="input-group-text" id="addIconPreview" style="font-size:20px;min-width:42px;justify-content:center;"></span>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEmojiPicker('addEmojiPicker', document.getElementById('addIcon'))"><i class="bi bi-emoji-smile"></i></button>
                        </div>
                        <!-- 이모지 피커 (v4.1: 탭 + 대형) -->
                        <div class="emoji-picker" id="addEmojiPicker">
                            <div class="emoji-tabs" id="addEmojiTabs"></div>
                            <div class="emoji-grid-wrap">
                                <div class="emoji-grid" id="addEmojiGrid"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 정렬 순서 -->
                    <div class="mb-3">
                        <label class="form-label">정렬 순서</label>
                        <input type="number" name="display_order" class="form-control form-control-sm" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_category" class="btn btn-sm btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================
     수정 모달: 카테고리 수정 (v4: SVG 인라인 피커)
     ==================================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 카테고리 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 카테고리명 -->
                    <div class="mb-3">
                        <label class="form-label">카테고리 이름 <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-sm" required>
                    </div>
                    <!-- 영문 slug -->
                    <div class="mb-3">
                        <label class="form-label">영문 slug</label>
                        <input type="text" name="slug" id="edit_slug" class="form-control form-control-sm" placeholder="예: skin_care">
                    </div>
                    <!-- v4: SVG 아이콘 인라인 피커 -->
                    <div class="mb-3">
                        <label class="form-label">SVG 아이콘</label>
                        <input type="hidden" name="icon_svg" id="edit_icon_svg" value="">
                        <div class="svg-inline-picker" id="editSvgPicker">
                            <div class="svg-pick-none selected" onclick="selectSvgInline('', 'edit')">
                                <i class="bi bi-x-circle me-1"></i>없음
                            </div>
                            <?php foreach ($svg_files as $svg): ?>
                            <div class="svg-pick-item" onclick="selectSvgInline('<?php echo htmlspecialchars($svg, ENT_QUOTES); ?>', 'edit')" data-svg="<?php echo htmlspecialchars($svg); ?>">
                                <img src="<?php echo htmlspecialchars($svg); ?>" alt="<?php echo basename($svg); ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22/>'">
                                <span class="pick-name" title="<?php echo basename($svg); ?>"><?php echo basename($svg, '.svg'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">클릭하여 선택 | 권장: 60x60px SVG | <a href="#" onclick="openSvgManager('edit'); return false;">SVG 관리/업로드</a></div>
                    </div>
                    <!-- 이모지 -->
                    <div class="mb-3 position-relative">
                        <label class="form-label">이모지 (SVG 없을 때 폴백)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="icon" id="edit_icon" class="form-control" placeholder="클릭하여 선택" readonly onclick="toggleEmojiPicker('editEmojiPicker', this)">
                            <span class="input-group-text" id="editIconPreview" style="font-size:20px;min-width:42px;justify-content:center;"></span>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEmojiPicker('editEmojiPicker', document.getElementById('edit_icon'))"><i class="bi bi-emoji-smile"></i></button>
                        </div>
                        <!-- 이모지 피커 (v4.1: 탭 + 대형) -->
                        <div class="emoji-picker" id="editEmojiPicker">
                            <div class="emoji-tabs" id="editEmojiTabs"></div>
                            <div class="emoji-grid-wrap">
                                <div class="emoji-grid" id="editEmojiGrid"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 정렬 순서 -->
                    <div class="mb-3">
                        <label class="form-label">정렬 순서</label>
                        <input type="number" name="display_order" id="edit_display_order" class="form-control form-control-sm" min="0" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="edit_category" class="btn btn-sm btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$admin_extra_js = '
// v4: 현재 활성 폼 추적 (add 또는 edit)
var activeFormPrefix = null;

// ---- v4.1: 이모지 카테고리별 목록 ----
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

// ---- v4.1: 이모지 피커 초기화 (탭 포함) ----
var emojiPickerState = {};

function initEmojiGrid(gridId, inputId, previewId) {
    var tabsId = gridId.replace("EmojiGrid", "EmojiTabs");
    var tabsEl = document.getElementById(tabsId);
    var gridEl = document.getElementById(gridId);
    if (!tabsEl || !gridEl) return;

    // 이미 초기화되었으면 리셋
    tabsEl.innerHTML = "";
    gridEl.innerHTML = "";

    // 상태 저장
    emojiPickerState[gridId] = { inputId: inputId, previewId: previewId };

    // 탭 생성
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

    // 첫 번째 카테고리 표시
    renderEmojiCategory(gridId, catNames[0]);
}

function switchEmojiTab(gridId, catName) {
    var tabsId = gridId.replace("EmojiGrid", "EmojiTabs");
    var tabsEl = document.getElementById(tabsId);

    // 탭 활성 상태 변경
    tabsEl.querySelectorAll(".emoji-tab").forEach(function(t) {
        t.classList.toggle("active", t.getAttribute("data-cat") === catName);
    });

    // 그리드 내용 교체
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

// ---- v4: SVG 인라인 피커 선택 ----
function selectSvgInline(svgPath, prefix) {
    // hidden input 업데이트
    var input = document.getElementById(prefix + "_icon_svg");
    input.value = svgPath;

    // 피커 내 선택 상태 업데이트
    var picker = document.getElementById(prefix + "SvgPicker");
    picker.querySelectorAll(".svg-pick-item, .svg-pick-none").forEach(function(el) {
        el.classList.remove("selected");
    });

    if (svgPath === "") {
        picker.querySelector(".svg-pick-none").classList.add("selected");
    } else {
        picker.querySelectorAll(".svg-pick-item").forEach(function(el) {
            if (el.getAttribute("data-svg") === svgPath) {
                el.classList.add("selected");
                // 스크롤하여 보이게
                el.scrollIntoView({ block: "nearest", behavior: "smooth" });
            }
        });
    }
}

// ---- v4: SVG 관리에서 직접 선택 ----
function selectSvgFromManager(svgPath) {
    if (!activeFormPrefix) {
        alert("먼저 카테고리 추가 또는 수정 화면을 열어주세요.");
        return;
    }
    // 인라인 피커에 반영
    selectSvgInline(svgPath, activeFormPrefix);
    // SVG 관리 모달 닫기
    var managerModal = bootstrap.Modal.getInstance(document.getElementById("svgManageModal"));
    if (managerModal) managerModal.hide();
    // 원래 모달 다시 열기
    setTimeout(function() {
        var modalId = activeFormPrefix === "add" ? "addModal" : "editModal";
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId));
        modal.show();
    }, 300);
}

// ---- v4: SVG 관리 모달 열기 (폼 컨텍스트 유지) ----
function openSvgManager(fromForm) {
    if (fromForm) {
        activeFormPrefix = fromForm;
        document.getElementById("svgSelectModeAlert").style.display = "block";
    } else {
        document.getElementById("svgSelectModeAlert").style.display = "none";
    }
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("svgManageModal"));
    modal.show();
}

// ---- 수정 모달 열기 ----
function editCategory(cat) {
    activeFormPrefix = "edit";
    document.getElementById("edit_id").value = cat.id;
    document.getElementById("edit_name").value = cat.name;
    document.getElementById("edit_slug").value = cat.slug || "";
    document.getElementById("edit_icon").value = cat.icon || "";
    document.getElementById("edit_display_order").value = cat.display_order;
    document.getElementById("editIconPreview").textContent = cat.icon || "";

    // v4: SVG 인라인 피커에서 현재 값 선택
    selectSvgInline(cat.icon_svg || "", "edit");

    // 이모지 피커 초기화
    initEmojiGrid("editEmojiGrid", "edit_icon", "editIconPreview");

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("editModal"));
    modal.show();
}

// 추가 모달 열릴 때 activeFormPrefix 설정
document.getElementById("addModal").addEventListener("show.bs.modal", function() {
    activeFormPrefix = "add";
});

// ---- 일괄 작업 ----
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

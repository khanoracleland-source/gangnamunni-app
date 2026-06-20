<?php
/**
 * 이벤트 관리 페이지
 * 이벤트 목록, 상세 보기, 추가, 수정, 삭제, 활성/비활성 토글
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-03-04
 */

session_start();

$admin_page_title = '이벤트 관리';
$admin_current = 'events';

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
loadThemeSettings($connect);
require_admin_login();
require_permission('events');

$message = '';
$error = '';

// ===== 병원 목록 (드롭다운용) =====
$hospitals = [];
$h_result = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active=1 ORDER BY name");
if ($h_result) {
    while ($h_row = mysqli_fetch_assoc($h_result)) {
        $hospitals[] = $h_row;
    }
}

// ===== 카테고리 목록 (동적) =====
$all_categories = [];
$cat_result = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_events WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
if ($cat_result) {
    while ($cat_row = mysqli_fetch_assoc($cat_result)) {
        $all_categories[] = $cat_row['category'];
    }
}

// 카테고리 배지 색상 매핑
function getCategoryBadgeClass($category) {
    $map = [
        '피부관리' => 'info',
        '리프팅'   => 'primary',
        '성형'     => 'danger',
        '다이어트' => 'success',
    ];
    return $map[$category] ?? 'secondary';
}

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 이벤트 이미지 업로드 헬퍼 ---
    $upload_dir = __DIR__ . '/uploads/events/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);

    function handleEventImageUpload($field_name, $upload_dir) {
        if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) return '';
        $file = $_FILES[$field_name];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) return '';
        if ($file['size'] > 5 * 1024 * 1024) return ''; // 5MB 제한
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'event_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return 'uploads/events/' . $filename;
        }
        return '';
    }

    // --- 이벤트 추가 ---
    if ($action === 'add') {
        $title           = trim($_POST['title'] ?? '');
        $subtitle        = trim($_POST['subtitle'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $layout_type     = trim($_POST['layout_type'] ?? 'mixed');
        $content_html    = trim($_POST['content_html'] ?? '');
        $image_url       = trim($_POST['image_url'] ?? '');
        // 파일 업로드 시 URL보다 우선
        $uploaded = handleEventImageUpload('image_file', $upload_dir);
        if ($uploaded) $image_url = $uploaded;
        $hospital_id     = intval($_POST['hospital_id'] ?? 0);
        $hospital_name   = trim($_POST['hospital_name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $original_price  = intval($_POST['original_price'] ?? 0);
        $discount_price  = intval($_POST['discount_price'] ?? 0);
        $discount_rate   = intval($_POST['discount_rate'] ?? 0);
        $start_date      = trim($_POST['start_date'] ?? '');
        $end_date        = trim($_POST['end_date'] ?? '');
        $is_active       = intval($_POST['is_active'] ?? 1);
        $display_order   = intval($_POST['display_order'] ?? 0);
        $has_raffle      = intval($_POST['has_raffle'] ?? 0);
        $raffle_type     = trim($_POST['raffle_type'] ?? '');
        $max_entries     = intval($_POST['max_entries'] ?? 0);
        $raffle_end_date = trim($_POST['raffle_end_date'] ?? '');
        $raffle_prize    = trim($_POST['raffle_prize'] ?? '');
        $raffle_winner_count = intval($_POST['raffle_winner_count'] ?? 1);

        // 병원 선택 시 병원명 자동 설정
        if ($hospital_id > 0 && empty($hospital_name)) {
            foreach ($hospitals as $h) {
                if ($h['id'] == $hospital_id) { $hospital_name = $h['name']; break; }
            }
        }

        // 빈 날짜 -> NULL
        $start_date_val      = !empty($start_date) ? $start_date : null;
        $end_date_val        = !empty($end_date) ? $end_date : null;
        $raffle_end_date_val = !empty($raffle_end_date) ? $raffle_end_date : null;

        if (empty($title)) {
            $error = '이벤트 제목은 필수 입력 항목입니다.';
        } else {
            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_events
                    (title, subtitle, description, layout_type, content_html, image_url,
                     hospital_id, hospital_name, category, original_price, discount_price, discount_rate,
                     start_date, end_date, is_active, display_order,
                     has_raffle, raffle_type, max_entries, raffle_end_date, raffle_prize, raffle_winner_count,
                     created_at, updated_at)
                 VALUES (?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?,?, NOW(), NOW())"
            );
            mysqli_stmt_bind_param($stmt, 'ssssssissiiissiiisissi',
                $title, $subtitle, $description, $layout_type, $content_html, $image_url,
                $hospital_id, $hospital_name, $category, $original_price, $discount_price, $discount_rate,
                $start_date_val, $end_date_val, $is_active, $display_order,
                $has_raffle, $raffle_type, $max_entries, $raffle_end_date_val, $raffle_prize, $raffle_winner_count
            );
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($connect);
                $message = "이벤트 '{$title}'이(가) 등록되었습니다. (ID: {$new_id})";
            } else {
                $error = '등록 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // --- 이벤트 수정 ---
    if ($action === 'edit') {
        $event_id        = intval($_POST['event_id'] ?? 0);
        $title           = trim($_POST['title'] ?? '');
        $subtitle        = trim($_POST['subtitle'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $layout_type     = trim($_POST['layout_type'] ?? 'mixed');
        $content_html    = trim($_POST['content_html'] ?? '');
        $image_url       = trim($_POST['image_url'] ?? '');
        // 파일 업로드 시 URL보다 우선
        $uploaded = handleEventImageUpload('image_file', $upload_dir);
        if ($uploaded) $image_url = $uploaded;
        $hospital_id     = intval($_POST['hospital_id'] ?? 0);
        $hospital_name   = trim($_POST['hospital_name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $original_price  = intval($_POST['original_price'] ?? 0);
        $discount_price  = intval($_POST['discount_price'] ?? 0);
        $discount_rate   = intval($_POST['discount_rate'] ?? 0);
        $start_date      = trim($_POST['start_date'] ?? '');
        $end_date        = trim($_POST['end_date'] ?? '');
        $is_active       = intval($_POST['is_active'] ?? 1);
        $display_order   = intval($_POST['display_order'] ?? 0);
        $has_raffle      = intval($_POST['has_raffle'] ?? 0);
        $raffle_type     = trim($_POST['raffle_type'] ?? '');
        $max_entries     = intval($_POST['max_entries'] ?? 0);
        $raffle_end_date = trim($_POST['raffle_end_date'] ?? '');
        $raffle_prize    = trim($_POST['raffle_prize'] ?? '');
        $raffle_winner_count = intval($_POST['raffle_winner_count'] ?? 1);

        if ($hospital_id > 0 && empty($hospital_name)) {
            foreach ($hospitals as $h) {
                if ($h['id'] == $hospital_id) { $hospital_name = $h['name']; break; }
            }
        }

        $start_date_val      = !empty($start_date) ? $start_date : null;
        $end_date_val        = !empty($end_date) ? $end_date : null;
        $raffle_end_date_val = !empty($raffle_end_date) ? $raffle_end_date : null;

        if ($event_id <= 0 || empty($title)) {
            $error = '이벤트 제목은 필수 입력 항목입니다.';
        } else {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_events SET
                    title=?, subtitle=?, description=?, layout_type=?, content_html=?, image_url=?,
                    hospital_id=?, hospital_name=?, category=?, original_price=?, discount_price=?, discount_rate=?,
                    start_date=?, end_date=?, is_active=?, display_order=?,
                    has_raffle=?, raffle_type=?, max_entries=?, raffle_end_date=?, raffle_prize=?, raffle_winner_count=?,
                    updated_at=NOW()
                 WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'ssssssissiiissiiisissii',
                $title, $subtitle, $description, $layout_type, $content_html, $image_url,
                $hospital_id, $hospital_name, $category, $original_price, $discount_price, $discount_rate,
                $start_date_val, $end_date_val, $is_active, $display_order,
                $has_raffle, $raffle_type, $max_entries, $raffle_end_date_val, $raffle_prize, $raffle_winner_count,
                $event_id
            );
            if (mysqli_stmt_execute($stmt)) {
                $message = "이벤트 '{$title}' 정보가 수정되었습니다.";
            } else {
                $error = '수정 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // --- 이벤트 삭제 ---
    if ($action === 'delete') {
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_events WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $event_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "이벤트 #{$event_id}이(가) 삭제되었습니다.";
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // --- 활성/비활성 토글 ---
    if ($action === 'toggle_active') {
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_events SET is_active = IF(is_active=1, 0, 1), updated_at=NOW() WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'i', $event_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '활성 상태가 변경되었습니다.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ===== 필터, 검색, 페이징 =====
$filter_category = $_GET['category'] ?? 'all';
$filter_active   = $_GET['active'] ?? 'all';
$filter_raffle   = $_GET['raffle'] ?? 'all';
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page        = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50])) $per_page = 20;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// WHERE 절 조립
$where        = '1=1';
$where_params = [];
$where_types  = '';

if ($filter_category !== 'all' && !empty($filter_category)) {
    $where .= ' AND category = ?';
    $where_params[] = $filter_category;
    $where_types   .= 's';
}

if ($filter_active === '1') {
    $where .= ' AND is_active = 1';
} elseif ($filter_active === '0') {
    $where .= ' AND is_active = 0';
}

if ($filter_raffle === '1') {
    $where .= ' AND has_raffle = 1';
} elseif ($filter_raffle === '0') {
    $where .= ' AND has_raffle = 0';
}

if (!empty($search)) {
    $where .= ' AND (title LIKE ? OR category LIKE ? OR hospital_name LIKE ?)';
    $sp = "%{$search}%";
    $where_params = array_merge($where_params, [$sp, $sp, $sp]);
    $where_types .= 'sss';
}

// ===== 통계 =====
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'raffle' => 0, 'total_views' => 0];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c, SUM(view_count) as v FROM gangnamunni_events");
if ($sr) { $r = mysqli_fetch_assoc($sr); $stats['total'] = (int)$r['c']; $stats['total_views'] = (int)($r['v'] ?? 0); }
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_events WHERE is_active=1");
if ($sr) $stats['active'] = (int)mysqli_fetch_assoc($sr)['c'];
$stats['inactive'] = $stats['total'] - $stats['active'];
$sr = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_events WHERE has_raffle=1");
if ($sr) $stats['raffle'] = (int)mysqli_fetch_assoc($sr)['c'];

// 카테고리별 통계
$cat_stats = [];
$csr = mysqli_query($connect, "SELECT category, COUNT(*) as cnt FROM gangnamunni_events WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY cnt DESC");
if ($csr) { while ($cs = mysqli_fetch_assoc($csr)) $cat_stats[$cs['category']] = (int)$cs['cnt']; }

// ===== 필터링된 전체 건수 =====
$count_sql = "SELECT COUNT(*) as c FROM gangnamunni_events WHERE {$where}";
if (!empty($where_params)) {
    $cs_stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($cs_stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($cs_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cs_stmt))['c'];
    mysqli_stmt_close($cs_stmt);
} else {
    $total = (int)mysqli_fetch_assoc(mysqli_query($connect, $count_sql))['c'];
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// ===== 목록 조회 =====
$events = [];
$list_sql = "SELECT * FROM gangnamunni_events WHERE {$where} ORDER BY display_order DESC, created_at DESC LIMIT ?, ?";
if (!empty($where_params)) {
    $ls = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($ls, $where_types . 'ii', ...array_merge($where_params, [$offset, $per_page]));
} else {
    $ls = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($ls, 'ii', $offset, $per_page);
}
mysqli_stmt_execute($ls);
$lr = mysqli_stmt_get_result($ls);
while ($row = mysqli_fetch_assoc($lr)) $events[] = $row;
mysqli_stmt_close($ls);

// URL 빌더 (필터 유지)
function buildEventUrl($overrides = []) {
    global $filter_category, $filter_active, $filter_raffle, $per_page, $search;
    $p = [
        'category' => $overrides['category'] ?? $filter_category,
        'active'   => $overrides['active']   ?? $filter_active,
        'raffle'   => $overrides['raffle']   ?? $filter_raffle,
        'per_page' => $overrides['per_page'] ?? $per_page,
        'page'     => $overrides['page']     ?? 1,
    ];
    if ($p['category'] === 'all') unset($p['category']);
    if ($p['active']   === 'all') unset($p['active']);
    if ($p['raffle']   === 'all') unset($p['raffle']);
    if (!empty($overrides['search'] ?? $search)) $p['search'] = $overrides['search'] ?? $search;
    return '?' . http_build_query($p);
}

// JS용 이벤트 데이터
$events_json = [];
foreach ($events as $ev) $events_json[$ev['id']] = $ev;

// 공통 헤더 CSS
$admin_extra_css = '
.event-stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
.event-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.category-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
.price-original { text-decoration: line-through; color: var(--bs-secondary-color); font-size: 12px; }
.price-discount { font-weight: 700; color: var(--bs-danger); }
.raffle-badge { font-size: 11px; }
.event-thumb { width: 60px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid var(--bs-border-color); }
/* B-4: modal body scroll fix */
/* [관리자 #14 - 2026-05-28] 이벤트 팝업 스크롤 미작동 픽스 - 모바일 터치 스크롤 강화 */
.modal-body { max-height: 70vh; overflow-y: auto !important; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
.modal-dialog-scrollable .modal-body { max-height: calc(100vh - 200px) !important; }
@media (max-width: 768px) { .modal-body { max-height: 60vh; } }
.content-preview { max-height: 300px; overflow-y: auto; padding: 12px; border: 1px solid var(--bs-border-color); border-radius: 8px; background: var(--bs-tertiary-bg); }
.raffle-section { border: 1px solid var(--bs-warning); border-radius: 8px; padding: 12px; margin-top: 8px; background: rgba(255,193,7,0.05); }
[data-bs-theme="dark"] .raffle-section { background: rgba(255,193,7,0.08); }
.detail-label { font-size: 12px; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: 2px; }
.detail-value { font-size: 14px; margin-bottom: 10px; }
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isDevMode($connect)): ?>
    <?php renderDevModeButton('event'); ?>
<?php endif; ?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> 이벤트 관리</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="bi bi-plus-circle me-1"></i>새 이벤트 등록
    </button>

    <a href="ai_event_editor.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-robot"></i> AI 이벤트</a></div>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md">
        <div class="card text-center event-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-body-secondary">전체</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card text-center event-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['active']); ?></div>
                <div class="small text-body-secondary">활성</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card text-center event-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary"><?php echo number_format($stats['inactive']); ?></div>
                <div class="small text-body-secondary">비활성</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card text-center event-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-warning"><?php echo number_format($stats['raffle']); ?></div>
                <div class="small text-body-secondary">응모 이벤트</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card text-center event-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-info"><?php echo number_format($stats['total_views']); ?></div>
                <div class="small text-body-secondary">총 조회수</div>
            </div>
        </div>
    </div>
</div>

<!-- 카테고리별 분포 -->
<?php if (!empty($cat_stats)): ?>
<div class="card mb-3">
    <div class="card-header py-2"><i class="bi bi-tags me-1"></i> 카테고리별 분포</div>
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($cat_stats as $cn => $cc): ?>
            <a href="<?php echo buildEventUrl(['category' => $cn]); ?>"
               class="btn btn-sm <?php echo $filter_category === $cn ? 'btn-' . getCategoryBadgeClass($cn) : 'btn-outline-secondary'; ?>">
                <?php echo htmlspecialchars($cn); ?>
                <span class="badge bg-white text-dark ms-1"><?php echo $cc; ?></span>
            </a>
            <?php endforeach; ?>
            <?php if ($filter_category !== 'all'): ?>
            <a href="<?php echo buildEventUrl(['category' => 'all']); ?>" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-x-lg me-1"></i>필터 해제
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 툴바: 검색 + 필터 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 flex-grow-1 flex-wrap" style="max-width:720px;">
        <div class="d-flex gap-1 flex-grow-1" style="max-width:280px;">
            <input type="search" name="search" class="form-control form-control-sm"
                   placeholder="제목, 카테고리, 병원명 검색..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </div>
        <select name="active" class="form-select form-select-sm" style="width:100px;" onchange="this.form.submit()">
            <option value="all" <?php echo $filter_active === 'all' ? 'selected' : ''; ?>>상태 전체</option>
            <option value="1"   <?php echo $filter_active === '1'   ? 'selected' : ''; ?>>활성</option>
            <option value="0"   <?php echo $filter_active === '0'   ? 'selected' : ''; ?>>비활성</option>
        </select>
        <select name="raffle" class="form-select form-select-sm" style="width:110px;" onchange="this.form.submit()">
            <option value="all" <?php echo $filter_raffle === 'all' ? 'selected' : ''; ?>>응모 전체</option>
            <option value="1"   <?php echo $filter_raffle === '1'   ? 'selected' : ''; ?>>응모 있음</option>
            <option value="0"   <?php echo $filter_raffle === '0'   ? 'selected' : ''; ?>>응모 없음</option>
        </select>
        <select name="category" class="form-select form-select-sm" style="width:120px;" onchange="this.form.submit()">
            <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>카테고리 전체</option>
            <?php foreach ($all_categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10, 20, 50] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total); ?>건</span>
    </div>
</div>

<!-- 이벤트 목록 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="eventTable">
        <thead class="table-light">
            <tr>
                <th style="width:45px;">#</th>
                <th style="width:55px;">이미지</th>
                <th>제목</th>
                <th style="width:85px;">카테고리</th>
                <th style="width:120px;">병원</th>
                <th style="width:130px;">가격</th>
                <th style="width:130px;">기간</th>
                <th style="width:55px;">활성</th>
                <th style="width:70px;">응모</th>
                <th style="width:70px;">조회수</th>
                <th style="width:130px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)): ?>
            <tr><td colspan="11" class="empty-state"><i class="bi bi-calendar-event"></i>등록된 이벤트가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($events as $ev): ?>
            <tr>
                <td class="text-center"><?php echo $ev['id']; ?></td>
                <td class="text-center">
                    <?php if (!empty($ev['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($ev['image_url']); ?>" alt="" class="event-thumb"
                         loading="lazy" onerror="this.style.display='none'">
                    <?php else: ?>
                    <span class="text-body-tertiary"><i class="bi bi-image" style="font-size:20px;"></i></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="javascript:void(0)" onclick="showDetailModal(<?php echo $ev['id']; ?>)"
                       class="text-decoration-none fw-semibold">
                        <?php echo htmlspecialchars($ev['title']); ?>
                    </a>
                    <?php if (!empty($ev['subtitle'])): ?>
                    <div class="small text-body-secondary text-truncate" style="max-width:250px;">
                        <?php echo htmlspecialchars($ev['subtitle']); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($ev['category'])): ?>
                    <span class="badge bg-<?php echo getCategoryBadgeClass($ev['category']); ?> category-badge">
                        <?php echo htmlspecialchars($ev['category']); ?>
                    </span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($ev['hospital_name'] ?? '-'); ?></td>
                <td class="small">
                    <?php if ($ev['discount_price'] > 0 && $ev['original_price'] > $ev['discount_price']): ?>
                        <span class="price-original"><?php echo number_format($ev['original_price']); ?></span><br>
                        <span class="price-discount"><?php echo number_format($ev['discount_price']); ?>원</span>
                        <?php if ($ev['discount_rate'] > 0): ?>
                        <span class="badge bg-danger ms-1" style="font-size:10px;"><?php echo $ev['discount_rate']; ?>%</span>
                        <?php endif; ?>
                    <?php elseif ($ev['original_price'] > 0): ?>
                        <span class="fw-semibold"><?php echo number_format($ev['original_price']); ?>원</span>
                    <?php else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php
                    $sd = $ev['start_date'] ? date('m.d', strtotime($ev['start_date'])) : '';
                    $ed = $ev['end_date']   ? date('m.d', strtotime($ev['end_date']))   : '';
                    if ($sd && $ed) {
                        echo $sd . '~' . $ed;
                        if (strtotime($ev['end_date']) < strtotime('today'))
                            echo ' <span class="badge bg-secondary" style="font-size:9px;">종료</span>';
                    } elseif ($sd) {
                        echo $sd . '~';
                    } else {
                        echo '<span class="text-body-tertiary">-</span>';
                    }
                    ?>
                </td>
                <td class="text-center">
                    <?php if ($ev['is_active']): ?>
                        <span class="badge bg-success">ON</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">OFF</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($ev['has_raffle']): ?>
                        <span class="badge bg-warning text-dark raffle-badge">응모 가능</span>
                    <?php else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-end small">
                    <i class="bi bi-eye text-primary me-1"></i><?php echo number_format($ev['view_count'] ?? 0); ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-info" title="상세보기"
                                onclick="showDetailModal(<?php echo $ev['id']; ?>)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" title="수정"
                                onclick="showEditModal(<?php echo $ev['id']; ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('활성 상태를 변경하시겠습니까?')">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                            <button type="submit" class="btn btn-outline-warning"
                                    title="<?php echo $ev['is_active'] ? '비활성화' : '활성화'; ?>">
                                <i class="bi bi-<?php echo $ev['is_active'] ? 'pause' : 'play'; ?>"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('정말 이 이벤트를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger" title="삭제">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
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
        <li class="page-item">
            <a class="page-link" href="<?php echo buildEventUrl(['page' => $page - 1]); ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php
        $pg_s = max(1, $page - 4);
        $pg_e = min($total_pages, $page + 4);
        if ($pg_s > 1): ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildEventUrl(['page' => 1]); ?>">1</a></li>
        <?php if ($pg_s > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $pg_s; $i <= $pg_e; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo buildEventUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($pg_e < $total_pages): ?>
        <?php if ($pg_e < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildEventUrl(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a></li>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildEventUrl(['page' => $page + 1]); ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>


<!-- ============================================================ -->
<!-- 추가 모달                                                     -->
<!-- ============================================================ -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="addEventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1"></i> 새 이벤트 등록</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- 기본 정보 -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small">제목 <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-sm" required
                                   placeholder="이벤트 제목">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">부제목</label>
                            <input type="text" name="subtitle" class="form-control form-control-sm"
                                   placeholder="이벤트 부제목 (선택)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">카테고리</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">선택 안함</option>
                                <option value="피부관리">피부관리</option>
                                <option value="리프팅">리프팅</option>
                                <option value="성형">성형</option>
                                <option value="다이어트">다이어트</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">레이아웃 타입</label>
                            <select name="layout_type" class="form-select form-select-sm">
                                <option value="mixed">mixed</option>
                                <option value="image">image</option>
                                <option value="text">text</option>
                                <option value="card">card</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">병원</label>
                            <select name="hospital_id" class="form-select form-select-sm"
                                    onchange="syncHospitalName(this,'add')">
                                <option value="0">선택 안함</option>
                                <?php foreach ($hospitals as $h): ?>
                                <option value="<?php echo $h['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($h['name']); ?>">
                                    <?php echo htmlspecialchars($h['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="hospital_name" id="addHospitalName" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">이미지</label>
                            <input type="file" name="image_file" class="form-control form-control-sm mb-1" accept="image/*">
                            <input type="url" name="image_url" class="form-control form-control-sm"
                                   placeholder="또는 이미지 URL 입력">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">설명 (description)</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2"
                                      placeholder="이벤트에 대한 짧은 설명..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">콘텐츠 HTML (content_html)</label>
                            <textarea name="content_html" class="form-control form-control-sm" rows="4"
                                      placeholder="HTML 콘텐츠 (선택)"></textarea>
                        </div>

                        <!-- 가격 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-currency-dollar me-1"></i>가격 정보
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">원래 가격</label>
                            <input type="number" name="original_price" class="form-control form-control-sm"
                                   value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">할인 가격</label>
                            <input type="number" name="discount_price" class="form-control form-control-sm"
                                   value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">할인율 (%)</label>
                            <input type="number" name="discount_rate" class="form-control form-control-sm"
                                   value="0" min="0" max="100">
                        </div>

                        <!-- 기간 / 표시 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-calendar me-1"></i>기간 / 표시 설정
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">시작일</label>
                            <input type="date" name="start_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">종료일</label>
                            <input type="date" name="end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">표시 순서</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">활성</label>
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="1" selected>활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>

                        <!-- 응모 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-trophy me-1"></i>응모 설정
                            </label>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">응모 여부</label>
                            <select name="has_raffle" class="form-select form-select-sm" id="addHasRaffle"
                                    onchange="toggleRaffleFields('add')">
                                <option value="0">없음</option>
                                <option value="1">있음</option>
                            </select>
                        </div>
                        <div class="col-md-3 add-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">응모 유형</label>
                            <input type="text" name="raffle_type" class="form-control form-control-sm"
                                   placeholder="선착순, 추첨 등">
                        </div>
                        <div class="col-md-3 add-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">최대 참여수</label>
                            <input type="number" name="max_entries" class="form-control form-control-sm"
                                   value="0" min="0">
                        </div>
                        <div class="col-md-3 add-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">당첨자 수</label>
                            <input type="number" name="raffle_winner_count" class="form-control form-control-sm"
                                   value="1" min="1">
                        </div>
                        <div class="col-md-6 add-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">응모 마감일</label>
                            <input type="date" name="raffle_end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 add-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">경품 내용</label>
                            <input type="text" name="raffle_prize" class="form-control form-control-sm"
                                   placeholder="경품 설명">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>등록
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================ -->
<!-- 수정 모달                                                     -->
<!-- ============================================================ -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="editEventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="event_id" id="editEventId" value="">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1"></i> 이벤트 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">제목 <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editTitle" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">부제목</label>
                            <input type="text" name="subtitle" id="editSubtitle" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">카테고리</label>
                            <select name="category" id="editCategory" class="form-select form-select-sm">
                                <option value="">선택 안함</option>
                                <option value="피부관리">피부관리</option>
                                <option value="리프팅">리프팅</option>
                                <option value="성형">성형</option>
                                <option value="다이어트">다이어트</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">레이아웃 타입</label>
                            <select name="layout_type" id="editLayoutType" class="form-select form-select-sm">
                                <option value="mixed">mixed</option>
                                <option value="image">image</option>
                                <option value="text">text</option>
                                <option value="card">card</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">병원</label>
                            <select name="hospital_id" id="editHospitalId" class="form-select form-select-sm"
                                    onchange="syncHospitalName(this,'edit')">
                                <option value="0">선택 안함</option>
                                <?php foreach ($hospitals as $h): ?>
                                <option value="<?php echo $h['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($h['name']); ?>">
                                    <?php echo htmlspecialchars($h['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="hospital_name" id="editHospitalName" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">이미지</label>
                            <input type="file" name="image_file" class="form-control form-control-sm mb-1" accept="image/*">
                            <input type="url" name="image_url" id="editImageUrl" class="form-control form-control-sm" placeholder="또는 이미지 URL 입력">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">설명 (description)</label>
                            <textarea name="description" id="editDescription"
                                      class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">콘텐츠 HTML (content_html)</label>
                            <textarea name="content_html" id="editContentHtml"
                                      class="form-control form-control-sm" rows="4"></textarea>
                        </div>

                        <!-- 가격 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-currency-dollar me-1"></i>가격 정보
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">원래 가격</label>
                            <input type="number" name="original_price" id="editOriginalPrice"
                                   class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">할인 가격</label>
                            <input type="number" name="discount_price" id="editDiscountPrice"
                                   class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">할인율 (%)</label>
                            <input type="number" name="discount_rate" id="editDiscountRate"
                                   class="form-control form-control-sm" min="0" max="100">
                        </div>

                        <!-- 기간 / 표시 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-calendar me-1"></i>기간 / 표시 설정
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">시작일</label>
                            <input type="date" name="start_date" id="editStartDate"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">종료일</label>
                            <input type="date" name="end_date" id="editEndDate"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">표시 순서</label>
                            <input type="number" name="display_order" id="editDisplayOrder"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">활성</label>
                            <select name="is_active" id="editIsActive" class="form-select form-select-sm">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>

                        <!-- 응모 -->
                        <div class="col-12">
                            <hr class="my-1">
                            <label class="fw-semibold small text-body-secondary">
                                <i class="bi bi-trophy me-1"></i>응모 설정
                            </label>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">응모 여부</label>
                            <select name="has_raffle" id="editHasRaffle" class="form-select form-select-sm"
                                    onchange="toggleRaffleFields('edit')">
                                <option value="0">없음</option>
                                <option value="1">있음</option>
                            </select>
                        </div>
                        <div class="col-md-3 edit-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">응모 유형</label>
                            <input type="text" name="raffle_type" id="editRaffleType"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 edit-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">최대 참여수</label>
                            <input type="number" name="max_entries" id="editMaxEntries"
                                   class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-md-3 edit-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">당첨자 수</label>
                            <input type="number" name="raffle_winner_count" id="editRaffleWinnerCount"
                                   class="form-control form-control-sm" min="1">
                        </div>
                        <div class="col-md-6 edit-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">응모 마감일</label>
                            <input type="date" name="raffle_end_date" id="editRaffleEndDate"
                                   class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 edit-raffle-field" style="display:none;">
                            <label class="form-label fw-semibold small">경품 내용</label>
                            <input type="text" name="raffle_prize" id="editRafflePrize"
                                   class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>저장
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================ -->
<!-- 상세 보기 모달                                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="detailEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-calendar-event me-1"></i> 이벤트 상세</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="detailEditBtn">
                    <i class="bi bi-pencil me-1"></i>수정
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>


<!-- 이벤트 데이터 (JS) -->
<script>
var eventsData = <?php echo json_encode($events_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php
$admin_extra_js = '
// 병원 선택 시 병원명 동기화
function syncHospitalName(sel, mode) {
    var opt = sel.options[sel.selectedIndex];
    var inp = document.getElementById(mode === "add" ? "addHospitalName" : "editHospitalName");
    if (inp) inp.value = opt.getAttribute("data-name") || "";
}

// 응모 필드 토글
function toggleRaffleFields(mode) {
    var sel = document.getElementById(mode === "add" ? "addHasRaffle" : "editHasRaffle");
    var fields = document.querySelectorAll("." + mode + "-raffle-field");
    var show = sel && sel.value === "1";
    fields.forEach(function(f) { f.style.display = show ? "" : "none"; });
}

// 카테고리 배지 색상
function getCatBadge(cat) {
    var m = {"피부관리":"info","리프팅":"primary","성형":"danger","다이어트":"success"};
    return m[cat] || "secondary";
}

// 숫자 포맷
function nf(n) {
    return (n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// HTML 이스케이프
function esc(s) {
    if (!s) return "";
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}

// select 값 설정
function setVal(sel, val) {
    if (!sel) return;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value == val) { sel.selectedIndex = i; return; }
    }
}

// ===== 상세 보기 모달 =====
function showDetailModal(id) {
    var ev = eventsData[id];
    if (!ev) return;
    var body = document.getElementById("detailModalBody");
    var h = "";

    // 이미지
    if (ev.image_url) {
        h += "<div class=\"text-center mb-3\"><img src=\"" + esc(ev.image_url) + "\" class=\"img-fluid rounded\" style=\"max-height:250px;\" onerror=\"this.style.display=\'none\'\"></div>";
    }

    h += "<div class=\"row g-2 mb-3\">";
    h += "<div class=\"col-md-8\"><div class=\"detail-label\">제목</div><div class=\"detail-value fw-bold\">" + esc(ev.title) + "</div></div>";
    h += "<div class=\"col-md-4\"><div class=\"detail-label\">카테고리</div><div class=\"detail-value\"><span class=\"badge bg-" + getCatBadge(ev.category) + " category-badge\">" + esc(ev.category || "-") + "</span></div></div>";

    if (ev.subtitle) {
        h += "<div class=\"col-12\"><div class=\"detail-label\">부제목</div><div class=\"detail-value\">" + esc(ev.subtitle) + "</div></div>";
    }

    h += "<div class=\"col-md-6\"><div class=\"detail-label\">병원</div><div class=\"detail-value\">" + esc(ev.hospital_name || "-") + "</div></div>";
    h += "<div class=\"col-md-3\"><div class=\"detail-label\">활성 상태</div><div class=\"detail-value\">" + (parseInt(ev.is_active) ? "<span class=\"badge bg-success\">활성</span>" : "<span class=\"badge bg-secondary\">비활성</span>") + "</div></div>";
    h += "<div class=\"col-md-3\"><div class=\"detail-label\">조회수</div><div class=\"detail-value\"><i class=\"bi bi-eye text-primary me-1\"></i>" + nf(ev.view_count) + "</div></div>";

    // 가격
    if (parseInt(ev.original_price) > 0 || parseInt(ev.discount_price) > 0) {
        h += "<div class=\"col-md-4\"><div class=\"detail-label\">원래 가격</div><div class=\"detail-value\">" + nf(ev.original_price) + "원</div></div>";
        h += "<div class=\"col-md-4\"><div class=\"detail-label\">할인 가격</div><div class=\"detail-value price-discount\">" + nf(ev.discount_price) + "원</div></div>";
        h += "<div class=\"col-md-4\"><div class=\"detail-label\">할인율</div><div class=\"detail-value\">" + (ev.discount_rate || 0) + "%</div></div>";
    }

    // 기간
    h += "<div class=\"col-md-4\"><div class=\"detail-label\">시작일</div><div class=\"detail-value\">" + (ev.start_date || "-") + "</div></div>";
    h += "<div class=\"col-md-4\"><div class=\"detail-label\">종료일</div><div class=\"detail-value\">" + (ev.end_date || "-") + "</div></div>";
    h += "<div class=\"col-md-2\"><div class=\"detail-label\">표시 순서</div><div class=\"detail-value\">" + (ev.display_order || 0) + "</div></div>";
    h += "<div class=\"col-md-2\"><div class=\"detail-label\">레이아웃</div><div class=\"detail-value\">" + esc(ev.layout_type || "mixed") + "</div></div>";
    h += "</div>";

    // 설명
    if (ev.description) {
        h += "<div class=\"mb-3\"><div class=\"detail-label\">설명</div><div class=\"detail-value\">" + esc(ev.description).replace(/\\n/g, "<br>") + "</div></div>";
    }

    // 콘텐츠 HTML
    if (ev.content_html) {
        h += "<div class=\"mb-3\"><div class=\"detail-label\">콘텐츠 HTML 미리보기</div><div class=\"content-preview\">" + ev.content_html + "</div></div>";
    }

    // 응모
    if (parseInt(ev.has_raffle)) {
        h += "<div class=\"raffle-section\">";
        h += "<div class=\"fw-semibold small mb-2\"><i class=\"bi bi-trophy-fill text-warning me-1\"></i>응모 정보</div>";
        h += "<div class=\"row g-2\">";
        h += "<div class=\"col-md-3\"><div class=\"detail-label\">응모 유형</div><div class=\"detail-value\">" + esc(ev.raffle_type || "-") + "</div></div>";
        h += "<div class=\"col-md-3\"><div class=\"detail-label\">최대 참여수</div><div class=\"detail-value\">" + nf(ev.max_entries) + "</div></div>";
        h += "<div class=\"col-md-3\"><div class=\"detail-label\">당첨자 수</div><div class=\"detail-value\">" + (ev.raffle_winner_count || 1) + "명</div></div>";
        h += "<div class=\"col-md-3\"><div class=\"detail-label\">마감일</div><div class=\"detail-value\">" + (ev.raffle_end_date || "-") + "</div></div>";
        if (ev.raffle_prize) {
            h += "<div class=\"col-12\"><div class=\"detail-label\">경품</div><div class=\"detail-value\">" + esc(ev.raffle_prize) + "</div></div>";
        }
        h += "</div></div>";
    }

    // 등록/수정일
    h += "<div class=\"row g-2 mt-2\">";
    h += "<div class=\"col-md-6\"><div class=\"detail-label\">등록일</div><div class=\"detail-value small\">" + (ev.created_at || "-") + "</div></div>";
    h += "<div class=\"col-md-6\"><div class=\"detail-label\">수정일</div><div class=\"detail-value small\">" + (ev.updated_at || "-") + "</div></div>";
    h += "</div>";

    body.innerHTML = h;

    // 수정 버튼
    document.getElementById("detailEditBtn").onclick = function() {
        bootstrap.Modal.getInstance(document.getElementById("detailEventModal")).hide();
        setTimeout(function() { showEditModal(id); }, 300);
    };

    new bootstrap.Modal(document.getElementById("detailEventModal")).show();
}

// ===== 수정 모달 열기 =====
function showEditModal(id) {
    var ev = eventsData[id];
    if (!ev) return;

    document.getElementById("editEventId").value = ev.id;
    document.getElementById("editTitle").value = ev.title || "";
    document.getElementById("editSubtitle").value = ev.subtitle || "";
    document.getElementById("editDescription").value = ev.description || "";
    document.getElementById("editContentHtml").value = ev.content_html || "";
    document.getElementById("editImageUrl").value = ev.image_url || "";
    document.getElementById("editOriginalPrice").value = ev.original_price || 0;
    document.getElementById("editDiscountPrice").value = ev.discount_price || 0;
    document.getElementById("editDiscountRate").value = ev.discount_rate || 0;
    document.getElementById("editStartDate").value = ev.start_date || "";
    document.getElementById("editEndDate").value = ev.end_date || "";
    document.getElementById("editDisplayOrder").value = ev.display_order || 0;
    document.getElementById("editRaffleType").value = ev.raffle_type || "";
    document.getElementById("editMaxEntries").value = ev.max_entries || 0;
    document.getElementById("editRaffleWinnerCount").value = ev.raffle_winner_count || 1;
    document.getElementById("editRaffleEndDate").value = ev.raffle_end_date || "";
    document.getElementById("editRafflePrize").value = ev.raffle_prize || "";

    setVal(document.getElementById("editCategory"), ev.category || "");
    setVal(document.getElementById("editLayoutType"), ev.layout_type || "mixed");
    setVal(document.getElementById("editHospitalId"), ev.hospital_id || "0");
    document.getElementById("editHospitalName").value = ev.hospital_name || "";
    setVal(document.getElementById("editIsActive"), ev.is_active ? "1" : "0");
    setVal(document.getElementById("editHasRaffle"), ev.has_raffle ? "1" : "0");
    toggleRaffleFields("edit");

    new bootstrap.Modal(document.getElementById("editEventModal")).show();
}
';

if (isDevMode($connect)) { echo getDevModeJS_event(); }

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

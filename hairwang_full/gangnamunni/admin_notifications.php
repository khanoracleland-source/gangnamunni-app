<?php
/**
 * Admin Center Alert - 관리자 내부 알림 관리 페이지
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-02-26
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_notifications.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('notifications');
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

$admin_id = intval($_SESSION['ad_admin_id']);
$message = '';
$error = '';

// ===== 탭별 알림 유형 그룹 =====
$TAB_GROUPS = [
    'all'      => ['label' => '전체',     'icon' => 'bi-bell-fill',        'types' => null], // null = 모든 타입
    'activity' => ['label' => '고객 활동', 'icon' => 'bi-people-fill',      'types' => ['hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new', 'member_join', 'mail_incoming']],
    'pending'  => ['label' => '승인 대기', 'icon' => 'bi-hourglass-split',  'types' => ['review_pending', 'hospital_pending', 'ad_pending']],
    'system'   => ['label' => '시스템',    'icon' => 'bi-gear-wide-connected', 'types' => ['test_fail', 'login_fail', 'maintenance_toggle', 'admin_created', 'ai_key_error']],
];

// ===== POST 액션 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 탭별 모두 읽음
    if ($action === 'mark_tab_read') {
        $tab = $_POST['tab'] ?? 'all';
        if ($tab === 'all') {
            mark_all_read($connect, $admin_id);
        } else {
            $types = $TAB_GROUPS[$tab]['types'] ?? [];
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '?'));
                $params = array_merge([$admin_id], $types);
                $type_str = str_repeat('s', count($types));
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0 AND type IN ({$placeholders})");
                mysqli_stmt_bind_param($stmt, 'i' . $type_str, ...$params);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        $message = '선택한 알림을 모두 읽음 처리했습니다.';
    }

    // 오래된 읽은 알림 삭제
    if ($action === 'delete_old_read') {
        $days = intval($_POST['days'] ?? 30);
        if ($days < 1) $days = 30;
        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_notifications WHERE admin_id = ? AND is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        mysqli_stmt_bind_param($stmt, 'ii', $admin_id, $days);
        mysqli_stmt_execute($stmt);
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        $message = "{$days}일 이상 된 읽은 알림 {$deleted}건을 삭제했습니다.";
    }

    // 개별 알림 읽음 처리
    if ($action === 'mark_read') {
        $nid = intval($_POST['nid'] ?? 0);
        if ($nid > 0) {
            mark_notification_read($connect, $nid, $admin_id);
        }
        // 링크가 있으면 리다이렉트
        $link = $_POST['link'] ?? '';
        if (!empty($link) && $link !== '#') {
            header('Location: ' . $link);
            exit;
        }
    }

    // POST 후 리다이렉트 (PRG 패턴)
    if ($action !== 'mark_read') {
        $redirect_params = $_GET;
        header('Location: admin_notifications.php?' . http_build_query($redirect_params) . '&msg=' . urlencode($message));
        exit;
    }
}

// GET 메시지
if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

// ===== 필터 파라미터 =====
$current_tab = $_GET['tab'] ?? 'all';
if (!isset($TAB_GROUPS[$current_tab])) $current_tab = 'all';

$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ===== 탭별 읽지 않은 개수 조회 =====
$tab_unread = [];
foreach ($TAB_GROUPS as $tab_key => $tab_info) {
    if ($tab_info['types'] === null) {
        // 전체 탭
        $tab_unread[$tab_key] = get_unread_count($connect, $admin_id);
    } else {
        $types = $tab_info['types'];
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $params = array_merge([$admin_id], $types);
        $type_str = str_repeat('s', count($types));
        $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_notifications WHERE admin_id = ? AND is_read = 0 AND type IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt, 'i' . $type_str, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt)->fetch_assoc();
        $tab_unread[$tab_key] = intval($res['cnt'] ?? 0);
        mysqli_stmt_close($stmt);
    }
}

// ===== 알림 목록 쿼리 빌드 =====
$where_clauses = ["n.admin_id = ?"];
$bind_types = 'i';
$bind_values = [$admin_id];

// 탭 필터 (타입 그룹)
$tab_types = $TAB_GROUPS[$current_tab]['types'] ?? null;
if ($tab_types !== null) {
    $placeholders = implode(',', array_fill(0, count($tab_types), '?'));
    $where_clauses[] = "n.type IN ({$placeholders})";
    $bind_types .= str_repeat('s', count($tab_types));
    $bind_values = array_merge($bind_values, $tab_types);
}

// 개별 타입 필터
if (!empty($filter_type) && isset($NOTIFICATION_TYPES[$filter_type])) {
    $where_clauses[] = "n.type = ?";
    $bind_types .= 's';
    $bind_values[] = $filter_type;
}

// 날짜 필터
if (!empty($filter_date_from)) {
    $where_clauses[] = "n.created_at >= ?";
    $bind_types .= 's';
    $bind_values[] = $filter_date_from . ' 00:00:00';
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "n.created_at <= ?";
    $bind_types .= 's';
    $bind_values[] = $filter_date_to . ' 23:59:59';
}

$where_sql = implode(' AND ', $where_clauses);

// 총 개수
$count_sql = "SELECT COUNT(*) as total FROM gangnamunni_notifications n WHERE {$where_sql}";
$stmt = mysqli_prepare($connect, $count_sql);
mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
mysqli_stmt_execute($stmt);
$total = intval(mysqli_stmt_get_result($stmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($stmt);
$total_pages = max(1, ceil($total / $per_page));

// 알림 목록
$list_sql = "SELECT n.* FROM gangnamunni_notifications n WHERE {$where_sql} ORDER BY n.created_at DESC LIMIT ?, ?";
$list_bind_types = $bind_types . 'ii';
$list_bind_values = array_merge($bind_values, [$offset, $per_page]);
$stmt = mysqli_prepare($connect, $list_sql);
mysqli_stmt_bind_param($stmt, $list_bind_types, ...$list_bind_values);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}
mysqli_stmt_close($stmt);

// 읽음/미읽음 통계
$stats_sql = "SELECT is_read, COUNT(*) as cnt FROM gangnamunni_notifications WHERE admin_id = ? GROUP BY is_read";
$stmt = mysqli_prepare($connect, $stats_sql);
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$stats_res = mysqli_stmt_get_result($stmt);
$stats = ['read' => 0, 'unread' => 0];
while ($row = mysqli_fetch_assoc($stats_res)) {
    if ($row['is_read']) $stats['read'] = intval($row['cnt']);
    else $stats['unread'] = intval($row['cnt']);
}
mysqli_stmt_close($stmt);

// ===== 페이지 설정 =====
$admin_page_title = 'Admin Center Alert';
$admin_current = 'notifications';

// 페이지별 추가 CSS
$admin_extra_css = <<<'CSS'
/* 알림 아이템 */
.notif-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--bs-border-color);
    transition: background 0.15s;
    cursor: pointer;
    text-decoration: none;
    color: var(--bs-body-color);
}
.notif-row:hover {
    background: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
}
.notif-row.unread {
    background: rgba(var(--bs-primary-rgb), 0.04);
    border-left: 3px solid var(--bs-primary);
}
[data-bs-theme="dark"] .notif-row.unread {
    background: rgba(var(--bs-primary-rgb), 0.08);
}
.notif-row .notif-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.notif-row .notif-body {
    flex: 1;
    min-width: 0;
}
.notif-row .notif-title {
    font-size: 13px;
    margin-bottom: 2px;
    line-height: 1.4;
}
.notif-row.unread .notif-title {
    font-weight: 600;
}
.notif-row .notif-msg {
    font-size: 12px;
    color: var(--bs-secondary-color);
    margin-bottom: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.notif-row .notif-meta {
    font-size: 11px;
    color: var(--bs-secondary-color);
}
.notif-row .notif-time {
    flex-shrink: 0;
    font-size: 11px;
    color: var(--bs-secondary-color);
    white-space: nowrap;
    padding-top: 2px;
}
.notif-row .notif-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--bs-primary);
    flex-shrink: 0;
    margin-top: 6px;
}
.notif-row.unread .notif-dot {
    display: block;
}
.notif-row:not(.unread) .notif-dot {
    display: none;
}

/* 탭 배지 */
.nav-pills .nav-link .badge {
    font-size: 10px;
    padding: 2px 6px;
    margin-left: 4px;
    vertical-align: middle;
}

/* 필터 바 */
.notif-filter-bar {
    padding: 12px 20px;
    border-bottom: 1px solid var(--bs-border-color);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.notif-filter-bar .form-select,
.notif-filter-bar .form-control {
    font-size: 13px;
    height: 34px;
}

/* 통계 카드 */
.stat-mini {
    text-align: center;
    padding: 12px 8px;
}
.stat-mini .stat-num {
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
}
.stat-mini .stat-label {
    font-size: 11px;
    color: var(--bs-secondary-color);
    margin-top: 4px;
}

/* 페이지네이션 */
.notif-pagination {
    padding: 12px 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 4px;
}
.notif-pagination .page-link {
    font-size: 13px;
    padding: 4px 10px;
}
CSS;

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if (!empty($message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="font-size:13px;">
    <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert" style="font-size:13px;">
    <i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
</div>
<?php endif; ?>

<!-- 상단 통계 + 액션 -->
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="row g-0 text-center">
                    <div class="col-3 stat-mini border-end">
                        <div class="stat-num text-primary"><?php echo $stats['unread'] + $stats['read']; ?></div>
                        <div class="stat-label">전체 알림</div>
                    </div>
                    <div class="col-3 stat-mini border-end">
                        <div class="stat-num text-danger"><?php echo $stats['unread']; ?></div>
                        <div class="stat-label">읽지 않음</div>
                    </div>
                    <div class="col-3 stat-mini border-end">
                        <div class="stat-num text-success"><?php echo $stats['read']; ?></div>
                        <div class="stat-label">읽음</div>
                    </div>
                    <div class="col-3 stat-mini">
                        <div class="stat-num"><?php echo $total; ?></div>
                        <div class="stat-label">현재 필터</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column justify-content-center gap-2 py-2">
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="mark_tab_read">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" onclick="return confirm('현재 탭의 알림을 모두 읽음 처리하시겠습니까?')">
                        <i class="bi bi-check2-all me-1"></i>현재 탭 모두 읽음
                    </button>
                </form>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_old_read">
                    <input type="hidden" name="days" value="30">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('30일 이상 된 읽은 알림을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')">
                        <i class="bi bi-trash3 me-1"></i>30일 이전 읽은 알림 삭제
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 탭 + 필터 -->
<div class="card">
    <!-- 탭 헤더 -->
    <div class="card-header py-2 px-3">
        <ul class="nav nav-pills" role="tablist" style="gap:4px;">
            <?php foreach ($TAB_GROUPS as $tab_key => $tab_info):
                $is_active = ($current_tab === $tab_key);
                $badge_count = $tab_unread[$tab_key] ?? 0;
                $tab_url = 'admin_notifications.php?tab=' . urlencode($tab_key);
                if (!empty($filter_type)) $tab_url .= '&type=' . urlencode($filter_type);
                if (!empty($filter_date_from)) $tab_url .= '&date_from=' . urlencode($filter_date_from);
                if (!empty($filter_date_to)) $tab_url .= '&date_to=' . urlencode($filter_date_to);
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $tab_url; ?>" style="font-size:13px;padding:6px 14px;">
                    <i class="bi <?php echo $tab_info['icon']; ?> me-1"></i><?php echo $tab_info['label']; ?>
                    <?php if ($badge_count > 0): ?>
                    <span class="badge <?php echo $is_active ? 'bg-light text-primary' : 'bg-danger'; ?>"><?php echo $badge_count > 99 ? '99+' : $badge_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- 필터 바 -->
    <form method="get" class="notif-filter-bar">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
        <div class="d-flex align-items-center gap-1">
            <i class="bi bi-funnel text-body-secondary"></i>
            <span style="font-size:12px;color:var(--bs-secondary-color);">필터:</span>
        </div>
        <select name="type" class="form-select" style="width:180px;">
            <option value="">전체 유형</option>
            <?php
            // 현재 탭에 해당하는 타입만 표시
            $available_types = $TAB_GROUPS[$current_tab]['types'] ?? array_keys($NOTIFICATION_TYPES);
            foreach ($available_types as $type_key):
                if (!isset($NOTIFICATION_TYPES[$type_key])) continue;
                $type_info = $NOTIFICATION_TYPES[$type_key];
            ?>
            <option value="<?php echo $type_key; ?>" <?php echo ($filter_type === $type_key) ? 'selected' : ''; ?>>
                <?php echo $type_info['label']; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>" placeholder="시작일" style="width:150px;">
        <span style="font-size:12px;color:var(--bs-secondary-color);">~</span>
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>" placeholder="종료일" style="width:150px;">
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-search me-1"></i>검색
        </button>
        <?php if (!empty($filter_type) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
        <a href="admin_notifications.php?tab=<?php echo urlencode($current_tab); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>초기화
        </a>
        <?php endif; ?>
    </form>

    <!-- 알림 목록 -->
    <div class="notif-list-wrap">
        <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <h6>알림이 없습니다</h6>
            <p class="text-body-secondary" style="font-size:13px;">
                <?php if ($current_tab !== 'all' || !empty($filter_type) || !empty($filter_date_from)): ?>
                    현재 필터 조건에 맞는 알림이 없습니다. 필터를 변경해 보세요.
                <?php else: ?>
                    아직 수신한 알림이 없습니다. 시스템 이벤트가 발생하면 여기에 표시됩니다.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notif):
            $n_type = $notif['type'];
            $type_info = $NOTIFICATION_TYPES[$n_type] ?? ['label' => $n_type, 'icon' => 'bi-bell', 'color' => 'secondary'];
            $is_unread = !$notif['is_read'];
            $link = $notif['link'] ?: '#';
        ?>
        <form method="post" class="d-inline" style="display:contents;">
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="nid" value="<?php echo $notif['id']; ?>">
            <input type="hidden" name="link" value="<?php echo htmlspecialchars($link); ?>">
            <button type="submit" class="notif-row <?php echo $is_unread ? 'unread' : ''; ?>" style="width:100%;border:none;text-align:left;background:none;">
                <span class="notif-dot"></span>
                <span class="notif-icon bg-<?php echo $type_info['color']; ?> bg-opacity-10 text-<?php echo $type_info['color']; ?>">
                    <i class="bi <?php echo $type_info['icon']; ?>"></i>
                </span>
                <span class="notif-body">
                    <span class="notif-title d-block"><?php echo htmlspecialchars($notif['title']); ?></span>
                    <?php if (!empty($notif['message'])): ?>
                    <span class="notif-msg d-block"><?php echo htmlspecialchars($notif['message']); ?></span>
                    <?php endif; ?>
                    <span class="notif-meta d-block">
                        <span class="badge bg-<?php echo $type_info['color']; ?> bg-opacity-10 text-<?php echo $type_info['color']; ?>" style="font-size:10px;"><?php echo $type_info['label']; ?></span>
                        <?php if ($link !== '#'): ?>
                        <i class="bi bi-box-arrow-up-right ms-1" style="font-size:10px;"></i>
                        <?php endif; ?>
                    </span>
                </span>
                <span class="notif-time">
                    <?php echo time_ago($notif['created_at']); ?>
                    <br>
                    <small style="font-size:10px;color:var(--bs-secondary-color);"><?php echo date('m/d H:i', strtotime($notif['created_at'])); ?></small>
                </span>
            </button>
        </form>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="notif-pagination">
        <?php
        // 페이지네이션 URL 빌드
        $pg_params = ['tab' => $current_tab];
        if (!empty($filter_type)) $pg_params['type'] = $filter_type;
        if (!empty($filter_date_from)) $pg_params['date_from'] = $filter_date_from;
        if (!empty($filter_date_to)) $pg_params['date_to'] = $filter_date_to;

        // 이전 페이지
        if ($page > 1):
            $pg_params['page'] = $page - 1;
        ?>
        <a class="btn btn-sm btn-outline-secondary" href="admin_notifications.php?<?php echo http_build_query($pg_params); ?>">
            <i class="bi bi-chevron-left"></i>
        </a>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
        <?php endif; ?>

        <?php
        // 페이지 번호
        $start_page = max(1, $page - 3);
        $end_page = min($total_pages, $page + 3);
        if ($start_page > 1): $pg_params['page'] = 1; ?>
            <a class="btn btn-sm btn-outline-secondary" href="admin_notifications.php?<?php echo http_build_query($pg_params); ?>">1</a>
            <?php if ($start_page > 2): ?><span class="text-body-secondary mx-1">...</span><?php endif; ?>
        <?php endif;

        for ($i = $start_page; $i <= $end_page; $i++):
            $pg_params['page'] = $i;
            if ($i === $page):
        ?>
            <button class="btn btn-sm btn-primary" disabled><?php echo $i; ?></button>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="admin_notifications.php?<?php echo http_build_query($pg_params); ?>"><?php echo $i; ?></a>
        <?php
            endif;
        endfor;

        if ($end_page < $total_pages): $pg_params['page'] = $total_pages; ?>
            <?php if ($end_page < $total_pages - 1): ?><span class="text-body-secondary mx-1">...</span><?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="admin_notifications.php?<?php echo http_build_query($pg_params); ?>"><?php echo $total_pages; ?></a>
        <?php endif; ?>

        <?php
        // 다음 페이지
        if ($page < $total_pages):
            $pg_params['page'] = $page + 1;
        ?>
        <a class="btn btn-sm btn-outline-secondary" href="admin_notifications.php?<?php echo http_build_query($pg_params); ?>">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>

        <span class="text-body-secondary ms-2" style="font-size:12px;">
            <?php echo number_format($total); ?>건 중 <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total); ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php
mysqli_close($connect);
require_once __DIR__ . '/includes/admin_footer.php';
?>

<?php
/**
 * 차단 관리 (admin_blocked_users.php)
 * Apple Guideline 1.2 준수 — 사용자 간 차단 + 관리자 차단 관리
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 *
 * @created 2026-03-17
 */
session_start();

$admin_page_title = '차단 관리';
$admin_current = 'blocked_users';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect($_DB_HOST, $_DB_USER, $_DB_PASSWORD, $_DB_NAME);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');

$theme = loadThemeSettings($connect);
require_admin_login();
require_permission('manage_reports');

// ===== 테이블 자동 생성 (최초 1회) =====
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_blocked_users'");
if ($table_check && mysqli_num_rows($table_check) === 0) {
    mysqli_query($connect, "CREATE TABLE `gangnamunni_blocked_users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `blocker_id` VARCHAR(100) NOT NULL COMMENT '차단한 사용자 ID (ADMIN=관리자)',
        `blocked_id` VARCHAR(100) NOT NULL COMMENT '차단 대상 사용자 ID',
        `blocked_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '차단 대상 이름 (캐시)',
        `reason` TEXT DEFAULT NULL COMMENT '차단 사유',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_blocker` (`blocker_id`),
        KEY `idx_blocked` (`blocked_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 차단 목록 (Apple 1.2 준수)'");
}

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // viewer 역할은 변경 불가
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        $msg = '뷰어 역할은 차단 관리를 변경할 수 없습니다.';
        $msg_type = 'danger';
        goto skip_action;
    }

    $action = $_POST['action'];

    // 관리자 직접 차단
    if ($action === 'admin_block') {
        $blocked_id = trim($_POST['blocked_id'] ?? '');
        $blocked_name = trim($_POST['blocked_name'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($blocked_id === '') {
            $msg = '차단할 사용자 ID를 입력해주세요.';
            $msg_type = 'danger';
        } else {
            // 이미 관리자가 차단했는지 중복 확인
            $dup_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_blocked_users WHERE blocker_id = 'ADMIN' AND blocked_id = ?");
            if ($dup_stmt) {
                mysqli_stmt_bind_param($dup_stmt, 's', $blocked_id);
                mysqli_stmt_execute($dup_stmt);
                $dup_result = mysqli_stmt_get_result($dup_stmt);
                if ($dup_result && mysqli_num_rows($dup_result) > 0) {
                    $msg = '이미 관리자가 차단한 사용자입니다.';
                    $msg_type = 'warning';
                    mysqli_stmt_close($dup_stmt);
                } else {
                    mysqli_stmt_close($dup_stmt);

                    // blocked_name이 비어있으면 g5_member에서 조회 시도
                    if ($blocked_name === '') {
                        $name_stmt = mysqli_prepare($connect, "SELECT mb_nick FROM g5_member WHERE mb_id = ? LIMIT 1");
                        if ($name_stmt) {
                            mysqli_stmt_bind_param($name_stmt, 's', $blocked_id);
                            mysqli_stmt_execute($name_stmt);
                            $name_r = mysqli_stmt_get_result($name_stmt);
                            if ($name_r && $name_row = mysqli_fetch_assoc($name_r)) {
                                $blocked_name = $name_row['mb_nick'] ?? '';
                            }
                            mysqli_stmt_close($name_stmt);
                        }
                    }

                    $ins_stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_blocked_users (blocker_id, blocked_id, blocked_name, reason) VALUES ('ADMIN', ?, ?, ?)");
                    if ($ins_stmt) {
                        mysqli_stmt_bind_param($ins_stmt, 'sss', $blocked_id, $blocked_name, $reason);
                        if (mysqli_stmt_execute($ins_stmt)) {
                            header('Location: admin_blocked_users.php?tab=admin&msg=' . urlencode('사용자가 차단되었습니다.'));
                            exit;
                        } else {
                            $msg = '차단 처리 실패: ' . mysqli_error($connect);
                            $msg_type = 'danger';
                        }
                        mysqli_stmt_close($ins_stmt);
                    }
                }
            }
        }
    }

    // 차단 해제
    if ($action === 'unblock') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $del_stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_blocked_users WHERE id = ?");
            if ($del_stmt) {
                mysqli_stmt_bind_param($del_stmt, 'i', $id);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }
            // 이전 탭 유지
            $return_tab = $_POST['return_tab'] ?? 'user';
            header('Location: admin_blocked_users.php?tab=' . urlencode($return_tab) . '&msg=' . urlencode('차단이 해제되었습니다.'));
            exit;
        }
    }
}
skip_action:

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['msg_type'] ?? 'success';
}

// ===== 현재 탭 =====
$current_tab = $_GET['tab'] ?? 'user';
if (!in_array($current_tab, ['user', 'admin'])) $current_tab = 'user';

// ===== 검색 + 페이지네이션 =====
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ===== 통계 카드 =====
$stat_user_blocks = 0;
$stat_admin_blocks = 0;

$sr = mysqli_query($connect, "SELECT
    SUM(CASE WHEN blocker_id != 'ADMIN' THEN 1 ELSE 0 END) AS user_cnt,
    SUM(CASE WHEN blocker_id = 'ADMIN' THEN 1 ELSE 0 END) AS admin_cnt
    FROM gangnamunni_blocked_users");
if ($sr && $s = mysqli_fetch_assoc($sr)) {
    $stat_user_blocks = (int)($s['user_cnt'] ?? 0);
    $stat_admin_blocks = (int)($s['admin_cnt'] ?? 0);
}

// ===== 탭별 데이터 조회 =====
// 동적 WHERE 절 구성 (prepared statement)
$where_clauses = [];
$bind_types = '';
$bind_values = [];

if ($current_tab === 'admin') {
    $where_clauses[] = "blocker_id = 'ADMIN'";
} else {
    $where_clauses[] = "blocker_id != 'ADMIN'";
}

if ($search !== '') {
    $where_clauses[] = "(blocker_id LIKE ? OR blocked_id LIKE ? OR blocked_name LIKE ? OR reason LIKE ?)";
    $bind_types .= 'ssss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_blocked_users WHERE " . $where;
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
if ($stmt_c) {
    mysqli_stmt_execute($stmt_c);
    $count_r = mysqli_stmt_get_result($stmt_c);
    $total = $count_r ? (int)mysqli_fetch_assoc($count_r)['cnt'] : 0;
    mysqli_stmt_close($stmt_c);
} else {
    $total = 0;
}
$total_pages = max(1, ceil($total / $per_page));

// 데이터 조회
$data_sql = "SELECT * FROM gangnamunni_blocked_users WHERE " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_d = mysqli_prepare($connect, $data_sql);
$data_bind_types = $bind_types . 'ii';
$data_bind_values = array_merge($bind_values, [$per_page, $offset]);
if ($stmt_d) {
    mysqli_stmt_bind_param($stmt_d, $data_bind_types, ...$data_bind_values);
    mysqli_stmt_execute($stmt_d);
    $r = mysqli_stmt_get_result($stmt_d);
    $rows = [];
    if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($stmt_d);
} else {
    $rows = [];
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-person-slash me-2"></i>차단 관리</h4>
        <span class="badge bg-secondary">Apple Guideline 1.2</span>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($stat_user_blocks); ?></h5>
                    <small class="text-primary">사용자 간 차단</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($stat_admin_blocks); ?></h5>
                    <small class="text-danger">관리자 차단</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 탭 네비게이션 -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_tab === 'user' ? 'active' : ''; ?>"
               href="admin_blocked_users.php?tab=user">
                <i class="bi bi-people me-1"></i>사용자 간 차단
                <span class="badge bg-primary ms-1"><?php echo number_format($stat_user_blocks); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_tab === 'admin' ? 'active' : ''; ?>"
               href="admin_blocked_users.php?tab=admin">
                <i class="bi bi-shield-exclamation me-1"></i>관리자 차단
                <span class="badge bg-danger ms-1"><?php echo number_format($stat_admin_blocks); ?></span>
            </a>
        </li>
    </ul>

    <?php if ($current_tab === 'admin'): ?>
    <!-- 관리자 차단 폼 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger bg-opacity-10">
            <h6 class="mb-0"><i class="bi bi-shield-fill-exclamation me-2"></i>사용자 차단 (관리자)</h6>
        </div>
        <div class="card-body">
            <form method="post" onsubmit="return confirm('이 사용자를 차단하시겠습니까?');">
                <input type="hidden" name="action" value="admin_block">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">사용자 ID <span class="text-danger">*</span></label>
                        <input type="text" name="blocked_id" class="form-control" required
                               placeholder="차단할 사용자 ID" maxlength="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">사용자 이름</label>
                        <input type="text" name="blocked_name" class="form-control"
                               placeholder="이름 (선택, 자동 조회)" maxlength="200">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">차단 사유</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="차단 사유를 입력하세요" maxlength="500">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-person-slash me-1"></i>차단
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 검색 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
                <div class="col-md-6">
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="사용자 ID, 이름, 사유 검색"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-search"></i> 검색
                    </button>
                </div>
                <?php if ($search !== ''): ?>
                <div class="col-md-2">
                    <a href="admin_blocked_users.php?tab=<?php echo htmlspecialchars($current_tab); ?>"
                       class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-x-lg"></i> 초기화
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 차단 목록 테이블 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>차단한 사용자</th>
                        <th>차단 대상</th>
                        <th>사유</th>
                        <th style="width:150px;">차단 일시</th>
                        <th style="width:100px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                            <?php if ($search !== ''): ?>
                                검색 결과가 없습니다.
                            <?php else: ?>
                                <?php echo $current_tab === 'admin' ? '관리자 차단 내역이 없습니다.' : '사용자 간 차단 내역이 없습니다.'; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <?php if ($row['blocker_id'] === 'ADMIN'): ?>
                                <span class="badge bg-danger"><i class="bi bi-shield-fill me-1"></i>ADMIN</span>
                            <?php else: ?>
                                <span class="text-body"><?php echo htmlspecialchars($row['blocker_id']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['blocked_id']); ?></strong>
                            <?php if (!empty($row['blocked_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($row['blocked_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['reason'])): ?>
                                <span title="<?php echo htmlspecialchars($row['reason']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($row['reason'], 0, 50, 'UTF-8'));
                                    echo mb_strlen($row['reason'], 'UTF-8') > 50 ? '...' : ''; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo substr($row['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('차단을 해제하시겠습니까?\n\n대상: <?php echo htmlspecialchars(addslashes($row['blocked_id'])); ?>');">
                                <input type="hidden" name="action" value="unblock">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="return_tab" value="<?php echo htmlspecialchars($current_tab); ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm" title="차단 해제">
                                    <i class="bi bi-unlock me-1"></i>해제
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-transparent">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=<?php echo urlencode($current_tab); ?>&page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>">&laquo;</a>
                    </li>
                    <?php endif; ?>

                    <?php
                    // 페이지네이션 범위 계산 (최대 10개 표시)
                    $range = 5;
                    $start_page = max(1, $page - $range);
                    $end_page = min($total_pages, $page + $range);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?tab=<?php echo urlencode($current_tab); ?>&page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=<?php echo urlencode($current_tab); ?>&page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>">&raquo;</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center mt-2">
                <small class="text-muted">총 <?php echo number_format($total); ?>건 / <?php echo $page; ?> / <?php echo $total_pages; ?>페이지</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

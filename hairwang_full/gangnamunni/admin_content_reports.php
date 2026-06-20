<?php
/**
 * 신고 관리 페이지 (admin_content_reports.php)
 * Apple Guideline 1.2 준수 — 24시간 이내 대응 필수
 * 사용자 콘텐츠 신고 목록 조회, 처리(해결/기각), 일괄 처리
 *
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 * @created 2026-03-17
 */
session_start();

$admin_page_title = '신고 관리';
$admin_current = 'content_reports';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect($_DB_HOST, $_DB_USER, $_DB_PASSWORD, $_DB_NAME);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
$theme = loadThemeSettings($connect);

// 로그인 + 권한 체크 (admin/super_admin만 접근)
require_admin_login();
require_permission('manage_reports');

// ===== 라벨 매핑 (한국어) =====
$reason_labels = [
    'spam'           => '스팸',
    'harassment'     => '괴롭힘',
    'hate_speech'    => '혐오발언',
    'sexual'         => '성적콘텐츠',
    'violence'       => '폭력',
    'misinformation' => '허위정보',
    'copyright'      => '저작권',
    'other'          => '기타',
];
$content_type_labels = [
    'review'    => '후기',
    'qna'       => 'Q&A',
    'community' => '커뮤니티',
    'chat'      => '채팅',
    'comment'   => '댓글',
    'tip'       => '꿀팁',
    'event'     => '이벤트',
];
$status_labels = [
    'pending'       => '대기',
    'investigating' => '조사중',
    'resolved'      => '처리완료',
    'dismissed'     => '기각',
];
$status_colors = [
    'pending'       => 'warning',
    'investigating' => 'info',
    'resolved'      => 'success',
    'dismissed'     => 'secondary',
];
$action_labels = [
    'none'            => '없음',
    'warning'         => '경고',
    'content_removed' => '콘텐츠삭제',
    'user_suspended'  => '정지',
    'user_banned'     => '영구차단',
];

$msg = '';
$msg_type = 'success';

// ===== 테이블 자동 생성 (없으면 CREATE) =====
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_content_reports'");
if ($table_check && mysqli_num_rows($table_check) === 0) {
    $create_sql = "CREATE TABLE `gangnamunni_content_reports` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `reporter_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '신고자 사용자ID',
        `reporter_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '신고자 이름',
        `content_type` ENUM('review','qna','community','chat','comment','tip','event') NOT NULL DEFAULT 'review' COMMENT '콘텐츠 유형',
        `content_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '콘텐츠 원본 ID',
        `content_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '콘텐츠 URL',
        `content_snippet` TEXT COMMENT '콘텐츠 발췌',
        `reported_user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '피신고 사용자ID',
        `reported_user_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '피신고 사용자 이름',
        `reason` ENUM('spam','harassment','hate_speech','sexual','violence','misinformation','copyright','other') NOT NULL DEFAULT 'other' COMMENT '신고 사유',
        `reason_detail` TEXT COMMENT '상세 사유',
        `status` ENUM('pending','investigating','resolved','dismissed') NOT NULL DEFAULT 'pending' COMMENT '처리 상태',
        `action_taken` ENUM('none','warning','content_removed','user_suspended','user_banned') NOT NULL DEFAULT 'none' COMMENT '취해진 조치',
        `action_note` TEXT COMMENT '관리자 메모',
        `admin_id` INT UNSIGNED DEFAULT NULL COMMENT '처리 관리자 ID',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '신고 접수일시',
        `resolved_at` DATETIME DEFAULT NULL COMMENT '처리 완료일시',
        INDEX `idx_status` (`status`),
        INDEX `idx_content_type` (`content_type`),
        INDEX `idx_reason` (`reason`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_status_created` (`status`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='콘텐츠 신고 관리 (Apple 1.2 준수)'";
    mysqli_query($connect, $create_sql);
}

// ===== POST 액션 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $admin_id = intval($_SESSION['ad_admin_id'] ?? 0);

    // --- 개별 처리: 해결 ---
    if ($action === 'resolve') {
        $report_id    = intval($_POST['report_id'] ?? 0);
        $action_taken = $_POST['action_taken'] ?? 'warning';
        $action_note  = trim($_POST['action_note'] ?? '');

        // action_taken 허용값 검증
        $allowed_actions = ['warning', 'content_removed', 'user_suspended', 'user_banned'];
        if ($report_id > 0 && in_array($action_taken, $allowed_actions)) {
            // 신고 레코드 업데이트
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_content_reports
                 SET status = 'resolved', action_taken = ?, action_note = ?, admin_id = ?, resolved_at = NOW()
                 WHERE id = ? AND status IN ('pending', 'investigating')"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssii', $action_taken, $action_note, $admin_id, $report_id);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                // 콘텐츠 삭제 조치인 경우 — 원본 콘텐츠 비활성화
                if ($action_taken === 'content_removed' && $affected > 0) {
                    // 해당 신고의 콘텐츠 정보 조회
                    $rpt_stmt = mysqli_prepare($connect, "SELECT content_type, content_id FROM gangnamunni_content_reports WHERE id = ?");
                    if ($rpt_stmt) {
                        mysqli_stmt_bind_param($rpt_stmt, 'i', $report_id);
                        mysqli_stmt_execute($rpt_stmt);
                        $rpt_res = mysqli_stmt_get_result($rpt_stmt);
                        $rpt_row = mysqli_fetch_assoc($rpt_res);
                        mysqli_stmt_close($rpt_stmt);

                        if ($rpt_row) {
                            $ct = $rpt_row['content_type'];
                            $cid = intval($rpt_row['content_id']);

                            // 콘텐츠 유형별 비활성화 테이블 매핑
                            $deactivate_map = [
                                'review'    => ['table' => 'gangnamunni_reviews',         'col' => 'is_active'],
                                'qna'       => ['table' => 'gangnamunni_qna',             'col' => 'is_active'],
                                'community' => ['table' => 'gangnamunni_community',       'col' => 'is_active'],
                                'comment'   => ['table' => 'gangnamunni_comments',        'col' => 'is_active'],
                                'tip'       => ['table' => 'gangnamunni_tips',            'col' => 'is_active'],
                                'event'     => ['table' => 'gangnamunni_events',          'col' => 'is_active'],
                            ];

                            if (isset($deactivate_map[$ct]) && $cid > 0) {
                                $tbl = $deactivate_map[$ct]['table'];
                                $col = $deactivate_map[$ct]['col'];
                                // 안전한 테이블/컬럼명 (하드코딩 매핑이므로 injection 없음)
                                $deact_sql = "UPDATE `{$tbl}` SET `{$col}` = 0 WHERE id = ?";
                                $deact_stmt = mysqli_prepare($connect, $deact_sql);
                                if ($deact_stmt) {
                                    mysqli_stmt_bind_param($deact_stmt, 'i', $cid);
                                    mysqli_stmt_execute($deact_stmt);
                                    mysqli_stmt_close($deact_stmt);
                                }
                            }
                        }
                    }
                }

                // 사용자 차단 조치인 경우 — 로그 기록 (향후 차단 테이블 확장 대비)
                if ($action_taken === 'user_banned' && $affected > 0) {
                    error_log("[admin_content_reports] User ban logged: report_id={$report_id}, admin_id={$admin_id}");
                }

                if ($affected > 0) {
                    $msg = '신고 #' . $report_id . ' 처리가 완료되었습니다.';
                    $msg_type = 'success';
                } else {
                    $msg = '이미 처리된 신고이거나 존재하지 않는 신고입니다.';
                    $msg_type = 'warning';
                }
            }
        } else {
            $msg = '잘못된 요청입니다.';
            $msg_type = 'danger';
        }
    }

    // --- 개별 처리: 기각 ---
    if ($action === 'dismiss') {
        $report_id = intval($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_content_reports
                 SET status = 'dismissed', admin_id = ?, resolved_at = NOW()
                 WHERE id = ? AND status IN ('pending', 'investigating')"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $admin_id, $report_id);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($affected > 0) {
                    $msg = '신고 #' . $report_id . '이(가) 기각되었습니다.';
                    $msg_type = 'info';
                } else {
                    $msg = '이미 처리된 신고이거나 존재하지 않는 신고입니다.';
                    $msg_type = 'warning';
                }
            }
        }
    }

    // --- 일괄 처리: 해결 ---
    if ($action === 'bulk_resolve') {
        $ids = $_POST['report_ids'] ?? [];
        $bulk_action = $_POST['bulk_action_taken'] ?? 'warning';
        $bulk_note   = trim($_POST['bulk_action_note'] ?? '');

        $allowed_actions = ['warning', 'content_removed', 'user_suspended', 'user_banned'];
        if (!empty($ids) && is_array($ids) && in_array($bulk_action, $allowed_actions)) {
            $resolved_count = 0;
            foreach ($ids as $rid) {
                $rid = intval($rid);
                if ($rid <= 0) continue;

                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_content_reports
                     SET status = 'resolved', action_taken = ?, action_note = ?, admin_id = ?, resolved_at = NOW()
                     WHERE id = ? AND status IN ('pending', 'investigating')"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssii', $bulk_action, $bulk_note, $admin_id, $rid);
                    mysqli_stmt_execute($stmt);
                    if (mysqli_stmt_affected_rows($stmt) > 0) $resolved_count++;
                    mysqli_stmt_close($stmt);
                }
            }
            $msg = $resolved_count . '건의 신고가 일괄 처리되었습니다.';
            $msg_type = 'success';
        } else {
            $msg = '처리할 신고를 선택해주세요.';
            $msg_type = 'warning';
        }
    }

    // POST 후 리다이렉트 (PRG 패턴)
    if ($msg) {
        header('Location: admin_content_reports.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msg_type)
            . (isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '')
            . (isset($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '')
            . (isset($_GET['reason']) ? '&reason=' . urlencode($_GET['reason']) : '')
            . (isset($_GET['page']) ? '&page=' . intval($_GET['page']) : '')
        );
        exit;
    }
}

// GET 메시지 처리
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['msg_type'] ?? 'success';
}

// ===== 필터 파라미터 =====
$filter_status       = $_GET['status'] ?? '';
$filter_content_type = $_GET['content_type'] ?? '';
$filter_reason       = $_GET['reason'] ?? '';
$filter_date_from    = $_GET['date_from'] ?? '';
$filter_date_to      = $_GET['date_to'] ?? '';
$search              = trim($_GET['q'] ?? '');
$page                = max(1, intval($_GET['page'] ?? 1));
$per_page            = 20;
$offset              = ($page - 1) * $per_page;

// ===== WHERE 절 구성 (Prepared Statement) =====
$where_clauses = ['1=1'];
$bind_types = '';
$bind_values = [];

if ($filter_status !== '') {
    $where_clauses[] = 'r.status = ?';
    $bind_types .= 's';
    $bind_values[] = $filter_status;
}
if ($filter_content_type !== '') {
    $where_clauses[] = 'r.content_type = ?';
    $bind_types .= 's';
    $bind_values[] = $filter_content_type;
}
if ($filter_reason !== '') {
    $where_clauses[] = 'r.reason = ?';
    $bind_types .= 's';
    $bind_values[] = $filter_reason;
}
if ($filter_date_from !== '') {
    $where_clauses[] = 'r.created_at >= ?';
    $bind_types .= 's';
    $bind_values[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where_clauses[] = 'r.created_at <= ?';
    $bind_types .= 's';
    $bind_values[] = $filter_date_to . ' 23:59:59';
}
if ($search !== '') {
    $where_clauses[] = '(r.reporter_name LIKE ? OR r.reported_user_name LIKE ? OR r.content_snippet LIKE ?)';
    $bind_types .= 'sss';
    $like_search = '%' . $search . '%';
    $bind_values[] = $like_search;
    $bind_values[] = $like_search;
    $bind_values[] = $like_search;
}

$where_sql = implode(' AND ', $where_clauses);

// ===== 요약 카드 통계 =====
// 1) 대기 중 (pending)
$stat_pending = 0;
$r = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE status = 'pending'");
if ($r) { $row = mysqli_fetch_assoc($r); $stat_pending = intval($row['cnt']); }

// 2) 24시간 초과 (pending + 24h 이상)
$stat_overdue = 0;
$r = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE status = 'pending' AND created_at < NOW() - INTERVAL 24 HOUR");
if ($r) { $row = mysqli_fetch_assoc($r); $stat_overdue = intval($row['cnt']); }

// 3) 오늘 처리 완료
$stat_resolved_today = 0;
$r = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()");
if ($r) { $row = mysqli_fetch_assoc($r); $stat_resolved_today = intval($row['cnt']); }

// 4) 전체 신고
$stat_total = 0;
$r = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports");
if ($r) { $row = mysqli_fetch_assoc($r); $stat_total = intval($row['cnt']); }

// ===== 전체 건수 (필터 적용) =====
$count_sql = "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports r WHERE {$where_sql}";
$total_rows = 0;
if (!empty($bind_types)) {
    $count_stmt = mysqli_prepare($connect, $count_sql);
    if ($count_stmt) {
        mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_values);
        mysqli_stmt_execute($count_stmt);
        $count_res = mysqli_stmt_get_result($count_stmt);
        $total_rows = intval(mysqli_fetch_assoc($count_res)['cnt'] ?? 0);
        mysqli_stmt_close($count_stmt);
    }
} else {
    $r = mysqli_query($connect, $count_sql);
    if ($r) $total_rows = intval(mysqli_fetch_assoc($r)['cnt'] ?? 0);
}
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages) $page = $total_pages;

// ===== 목록 조회 =====
$list_sql = "SELECT r.* FROM gangnamunni_content_reports r
             WHERE {$where_sql}
             ORDER BY
                CASE r.status
                    WHEN 'pending' THEN 0
                    WHEN 'investigating' THEN 1
                    WHEN 'resolved' THEN 2
                    WHEN 'dismissed' THEN 3
                END ASC,
                r.created_at DESC
             LIMIT ?, ?";

$reports = [];
$list_stmt = mysqli_prepare($connect, $list_sql);
if ($list_stmt) {
    $bind_types_list = $bind_types . 'ii';
    $bind_values_list = array_merge($bind_values, [$offset, $per_page]);
    mysqli_stmt_bind_param($list_stmt, $bind_types_list, ...$bind_values_list);
    mysqli_stmt_execute($list_stmt);
    $list_result = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($list_result)) {
        $reports[] = $row;
    }
    mysqli_stmt_close($list_stmt);
}

// ===== AJAX 자동 새로고침 엔드포인트 =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pending_count') {
    header('Content-Type: application/json; charset=utf-8');
    $r = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE status = 'pending'");
    $cnt = $r ? intval(mysqli_fetch_assoc($r)['cnt']) : 0;
    $r2 = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM gangnamunni_content_reports WHERE status = 'pending' AND created_at < NOW() - INTERVAL 24 HOUR");
    $overdue = $r2 ? intval(mysqli_fetch_assoc($r2)['cnt']) : 0;
    echo json_encode(['pending' => $cnt, 'overdue' => $overdue]);
    mysqli_close($connect);
    exit;
}

// ===== 페이지 설명 =====
$admin_page_description = '사용자 콘텐츠 신고를 관리합니다. Apple Guideline 1.2 준수 - 24시간 이내 대응이 필요합니다.';

// ===== HTML 출력 시작 =====
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 커스텀 CSS -->
<style>
/* 24시간 초과 행 하이라이트 */
.row-overdue {
    background-color: rgba(220, 53, 69, 0.08) !important;
}
[data-bs-theme="dark"] .row-overdue {
    background-color: rgba(220, 53, 69, 0.15) !important;
}
/* 24h+ 뱃지 깜빡임 */
@keyframes pulse-red {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.badge-overdue {
    animation: pulse-red 1.5s infinite;
}
/* 요약 카드 */
.stat-card {
    border-radius: 12px;
    transition: transform 0.15s, box-shadow 0.15s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.stat-card .stat-number {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 13px;
    color: var(--bs-secondary-color);
}
/* 필터 바 */
.filter-bar .form-select,
.filter-bar .form-control {
    font-size: 13px;
}
/* 콘텐츠 발췌 */
.content-snippet {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    color: var(--bs-secondary-color);
}
</style>

<!-- 알림 메시지 -->
<?php if ($msg): ?>
<div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ===== 요약 카드 (4열) ===== -->
<div class="row g-3 mb-4">
    <!-- 대기 중 -->
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-number text-warning"><?php echo $stat_pending; ?></div>
                <div class="stat-label">대기 중</div>
                <?php if ($stat_pending > 0): ?>
                <span class="badge bg-warning text-dark mt-1">처리 필요</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- 24시간 초과 (핵심 — Apple 가이드라인) -->
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm h-100" style="<?php echo $stat_overdue > 0 ? 'border-left: 4px solid #dc3545 !important;' : ''; ?>">
            <div class="card-body text-center">
                <div class="stat-number text-danger" id="overdueCount"><?php echo $stat_overdue; ?></div>
                <div class="stat-label">24시간 초과</div>
                <?php if ($stat_overdue > 0): ?>
                <span class="badge bg-danger badge-overdue mt-1">긴급 대응 필요</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- 오늘 처리 -->
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-number text-success"><?php echo $stat_resolved_today; ?></div>
                <div class="stat-label">오늘 처리 완료</div>
            </div>
        </div>
    </div>
    <!-- 전체 -->
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo $stat_total; ?></div>
                <div class="stat-label">전체 신고</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== 필터 바 ===== -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end filter-bar">
            <!-- 상태 -->
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <?php foreach ($status_labels as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filter_status === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- 콘텐츠 유형 -->
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">콘텐츠 유형</label>
                <select name="content_type" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <?php foreach ($content_type_labels as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filter_content_type === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- 신고 사유 -->
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">사유</label>
                <select name="reason" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <?php foreach ($reason_labels as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filter_reason === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- 기간 -->
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">시작일</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_from); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">종료일</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_to); ?>">
            </div>
            <!-- 검색 -->
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:12px;">검색</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="신고자/피신고자 이름" value="<?php echo htmlspecialchars($search); ?>" style="min-width:180px;">
            </div>
            <!-- 버튼 -->
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> 검색</button>
                <a href="admin_content_reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> 초기화</a>
            </div>
        </form>
    </div>
</div>

<!-- ===== 일괄 처리 바 (숨김 → 체크박스 선택 시 표시) ===== -->
<div id="bulkActions" class="alert alert-primary d-flex align-items-center gap-3 mb-3" style="display:none !important;">
    <strong><span id="selectedCount">0</span>건 선택</strong>
    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkResolveModal">
        <i class="bi bi-check-circle"></i> 일괄 처리
    </button>
</div>

<!-- ===== 신고 테이블 ===== -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-flag"></i> 신고 목록 <span class="badge bg-secondary"><?php echo $total_rows; ?>건</span></span>
        <small class="text-body-secondary" id="autoRefreshLabel">60초마다 자동 새로고침</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportsTable">
            <thead class="table-light">
                <tr>
                    <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"></th>
                    <th style="width:50px;">#</th>
                    <th>상태</th>
                    <th>24h</th>
                    <th>유형</th>
                    <th>콘텐츠</th>
                    <th>신고자</th>
                    <th>피신고자</th>
                    <th>사유</th>
                    <th>접수일시</th>
                    <th style="width:180px;">액션</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="11">
                        <div class="empty-state">
                            <i class="bi bi-shield-check"></i>
                            <p>신고 내역이 없습니다.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($reports as $rpt):
                    // 24시간 초과 여부
                    $created_ts = strtotime($rpt['created_at']);
                    $is_overdue = ($rpt['status'] === 'pending' && (time() - $created_ts) > 86400);
                    $row_class = $is_overdue ? 'row-overdue' : '';

                    // 경과 시간 표시
                    $elapsed_sec = time() - $created_ts;
                    if ($elapsed_sec < 3600) {
                        $elapsed_text = intval($elapsed_sec / 60) . '분 전';
                    } elseif ($elapsed_sec < 86400) {
                        $elapsed_text = intval($elapsed_sec / 3600) . '시간 전';
                    } else {
                        $elapsed_text = intval($elapsed_sec / 86400) . '일 전';
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <!-- 체크박스 -->
                    <td>
                        <?php if (in_array($rpt['status'], ['pending', 'investigating'])): ?>
                        <input type="checkbox" class="form-check-input row-check" value="<?php echo intval($rpt['id']); ?>" onchange="updateBulkActions()">
                        <?php endif; ?>
                    </td>
                    <!-- # -->
                    <td><strong><?php echo intval($rpt['id']); ?></strong></td>
                    <!-- 상태 -->
                    <td>
                        <span class="badge bg-<?php echo $status_colors[$rpt['status']] ?? 'secondary'; ?>">
                            <?php echo $status_labels[$rpt['status']] ?? $rpt['status']; ?>
                        </span>
                    </td>
                    <!-- 24h -->
                    <td>
                        <?php if ($is_overdue): ?>
                        <span class="badge bg-danger badge-overdue">24h+</span>
                        <?php elseif ($rpt['status'] === 'pending'): ?>
                        <span class="text-body-secondary" style="font-size:11px;"><?php echo $elapsed_text; ?></span>
                        <?php else: ?>
                        <span class="text-body-secondary" style="font-size:11px;">-</span>
                        <?php endif; ?>
                    </td>
                    <!-- 유형 -->
                    <td>
                        <span class="badge bg-light text-dark border"><?php echo $content_type_labels[$rpt['content_type']] ?? $rpt['content_type']; ?></span>
                    </td>
                    <!-- 콘텐츠 -->
                    <td>
                        <div class="content-snippet" title="<?php echo htmlspecialchars($rpt['content_snippet'] ?? ''); ?>">
                            <?php echo htmlspecialchars(mb_substr($rpt['content_snippet'] ?? '-', 0, 40, 'UTF-8')); ?>
                        </div>
                        <?php if (!empty($rpt['content_url'])): ?>
                        <a href="<?php echo htmlspecialchars($rpt['content_url']); ?>" target="_blank" class="text-primary" style="font-size:11px;">
                            <i class="bi bi-box-arrow-up-right"></i> 보기
                        </a>
                        <?php endif; ?>
                    </td>
                    <!-- 신고자 -->
                    <td style="font-size:13px;"><?php echo htmlspecialchars($rpt['reporter_name'] ?: '(탈퇴)'); ?></td>
                    <!-- 피신고자 -->
                    <td style="font-size:13px;"><?php echo htmlspecialchars($rpt['reported_user_name'] ?: '(탈퇴)'); ?></td>
                    <!-- 사유 -->
                    <td>
                        <span class="badge bg-outline-secondary border" style="font-size:11px;">
                            <?php echo $reason_labels[$rpt['reason']] ?? $rpt['reason']; ?>
                        </span>
                        <?php if (!empty($rpt['reason_detail'])): ?>
                        <i class="bi bi-chat-left-text text-body-secondary" style="font-size:11px;cursor:pointer;"
                           data-bs-toggle="tooltip" title="<?php echo htmlspecialchars(mb_substr($rpt['reason_detail'], 0, 100, 'UTF-8')); ?>"></i>
                        <?php endif; ?>
                    </td>
                    <!-- 접수일시 -->
                    <td style="font-size:12px;">
                        <?php echo date('Y-m-d H:i', $created_ts); ?>
                    </td>
                    <!-- 액션 -->
                    <td>
                        <?php if (in_array($rpt['status'], ['pending', 'investigating'])): ?>
                        <!-- 처리 버튼 -->
                        <button type="button" class="btn btn-sm btn-success" title="처리"
                                onclick="openResolveModal(<?php echo intval($rpt['id']); ?>, '<?php echo htmlspecialchars(addslashes($rpt['content_snippet'] ?? ''), ENT_QUOTES); ?>')">
                            <i class="bi bi-check-lg"></i> 처리
                        </button>
                        <!-- 기각 버튼 -->
                        <form method="POST" class="d-inline" onsubmit="return confirm('신고 #<?php echo intval($rpt['id']); ?>을(를) 기각하시겠습니까?');">
                            <input type="hidden" name="action" value="dismiss">
                            <input type="hidden" name="report_id" value="<?php echo intval($rpt['id']); ?>">
                            <?php
                            // 현재 필터를 hidden으로 전달
                            foreach (['status', 'content_type', 'reason', 'page'] as $fp) {
                                if (isset($_GET[$fp]) && $_GET[$fp] !== '') {
                                    echo '<input type="hidden" name="' . $fp . '" value="' . htmlspecialchars($_GET[$fp]) . '">';
                                }
                            }
                            ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="기각">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- 처리 완료 표시 -->
                        <span class="text-body-secondary" style="font-size:12px;">
                            <?php echo $action_labels[$rpt['action_taken']] ?? '-'; ?>
                            <?php if ($rpt['resolved_at']): ?>
                            <br><small><?php echo date('m/d H:i', strtotime($rpt['resolved_at'])); ?></small>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($rpt['action_note'])): ?>
                        <i class="bi bi-journal-text text-body-secondary" style="cursor:pointer;font-size:12px;"
                           data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($rpt['action_note']); ?>"></i>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-body-secondary">
            전체 <?php echo $total_rows; ?>건 중 <?php echo $offset + 1; ?>~<?php echo min($offset + $per_page, $total_rows); ?>건
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                // 이전 페이지
                $prev_disabled = ($page <= 1) ? 'disabled' : '';
                $qs_base = http_build_query(array_filter([
                    'status' => $filter_status,
                    'content_type' => $filter_content_type,
                    'reason' => $filter_reason,
                    'date_from' => $filter_date_from,
                    'date_to' => $filter_date_to,
                    'q' => $search,
                ], function($v) { return $v !== ''; }));
                ?>
                <li class="page-item <?php echo $prev_disabled; ?>">
                    <a class="page-link" href="?<?php echo $qs_base; ?>&page=<?php echo $page - 1; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php
                // 페이지 번호 (최대 7개 표시)
                $start_p = max(1, $page - 3);
                $end_p = min($total_pages, $page + 3);
                for ($p = $start_p; $p <= $end_p; $p++):
                ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo $qs_base; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
                <!-- 다음 페이지 -->
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo $qs_base; ?>&page=<?php echo $page + 1; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ===== 처리 모달 (Resolve Modal) ===== -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="resolveForm">
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="report_id" id="resolveReportId">
                <?php
                // 현재 필터를 hidden으로 전달
                foreach (['status', 'content_type', 'reason', 'page'] as $fp) {
                    if (isset($_GET[$fp]) && $_GET[$fp] !== '') {
                        echo '<input type="hidden" name="' . $fp . '" value="' . htmlspecialchars($_GET[$fp]) . '">';
                    }
                }
                ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveModalLabel"><i class="bi bi-shield-check"></i> 신고 처리</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-body-secondary mb-3" id="resolveSnippet" style="font-size:13px;"></p>

                    <!-- 조치 선택 -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">조치 선택 <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action_taken" id="act_warning" value="warning" checked>
                                <label class="form-check-label" for="act_warning">
                                    <i class="bi bi-exclamation-triangle text-warning"></i> 경고
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action_taken" id="act_remove" value="content_removed">
                                <label class="form-check-label" for="act_remove">
                                    <i class="bi bi-trash text-danger"></i> 콘텐츠 삭제
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action_taken" id="act_suspend" value="user_suspended">
                                <label class="form-check-label" for="act_suspend">
                                    <i class="bi bi-pause-circle text-info"></i> 사용자 정지
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action_taken" id="act_ban" value="user_banned">
                                <label class="form-check-label" for="act_ban">
                                    <i class="bi bi-slash-circle text-danger"></i> 영구 차단
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 관리자 메모 -->
                    <div class="mb-2">
                        <label class="form-label fw-bold">관리자 메모</label>
                        <textarea name="action_note" class="form-control" rows="3" placeholder="처리 사유를 기록해주세요 (내부 기록용)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> 처리 완료</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== 일괄 처리 모달 (Bulk Resolve Modal) ===== -->
<div class="modal fade" id="bulkResolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="bulkResolveForm">
                <input type="hidden" name="action" value="bulk_resolve">
                <div id="bulkReportIdsContainer"></div>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-all"></i> 일괄 처리</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3"><strong id="bulkCountLabel">0</strong>건의 신고를 일괄 처리합니다.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">조치 선택 <span class="text-danger">*</span></label>
                        <select name="bulk_action_taken" class="form-select">
                            <option value="warning">경고</option>
                            <option value="content_removed">콘텐츠 삭제</option>
                            <option value="user_suspended">사용자 정지</option>
                            <option value="user_banned">영구 차단</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">관리자 메모</label>
                        <textarea name="bulk_action_note" class="form-control" rows="3" placeholder="일괄 처리 사유"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-all"></i> 일괄 처리</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== JavaScript ===== -->
<script>
// 처리 모달 열기
function openResolveModal(reportId, snippet) {
    document.getElementById('resolveReportId').value = reportId;
    document.getElementById('resolveSnippet').textContent = '신고 #' + reportId + ': ' + (snippet || '');
    // 라디오 초기화
    document.getElementById('act_warning').checked = true;
    var modal = new bootstrap.Modal(document.getElementById('resolveModal'));
    modal.show();
}

// 일괄 처리 모달 — 체크된 ID를 hidden input으로 추가
document.getElementById('bulkResolveModal').addEventListener('show.bs.modal', function() {
    var container = document.getElementById('bulkReportIdsContainer');
    container.innerHTML = '';
    var checked = document.querySelectorAll('.row-check:checked');
    checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'report_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkCountLabel').textContent = checked.length;
});

// bulkActions 표시/숨김 (admin_footer.php의 updateBulkActions 오버라이드)
function updateBulkActions() {
    var checked = document.querySelectorAll('.row-check:checked').length;
    var bulkBar = document.getElementById('bulkActions');
    if (bulkBar) {
        bulkBar.style.display = checked > 0 ? 'flex' : 'none';
        bulkBar.style.setProperty('display', checked > 0 ? 'flex' : 'none', 'important');
        var countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = checked;
    }
}

// 전체 선택
function toggleSelectAll(source) {
    var checkboxes = document.querySelectorAll('.row-check');
    checkboxes.forEach(function(cb) { cb.checked = source.checked; });
    updateBulkActions();
}

// 툴팁 초기화
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
});

// ===== 60초 자동 새로고침 (새 신고 감지) =====
(function() {
    var refreshInterval = 60000; // 60초
    var label = document.getElementById('autoRefreshLabel');
    var countdown = refreshInterval / 1000;

    // 카운트다운 표시
    var timer = setInterval(function() {
        countdown--;
        if (countdown <= 0) {
            countdown = refreshInterval / 1000;
        }
        if (label) {
            label.textContent = countdown + '초 후 자동 새로고침';
        }
    }, 1000);

    // 실제 새로고침 — pending 카운트만 폴링하여 변경 시 페이지 리로드
    var lastPending = <?php echo $stat_pending; ?>;
    var lastOverdue = <?php echo $stat_overdue; ?>;

    setInterval(function() {
        fetch('admin_content_reports.php?ajax=pending_count')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // 새 신고 감지 → 자동 리로드
                if (data.pending > lastPending) {
                    window.location.reload();
                    return;
                }
                // overdue 카운트 업데이트
                var overdueEl = document.getElementById('overdueCount');
                if (overdueEl) overdueEl.textContent = data.overdue;
                lastPending = data.pending;
                lastOverdue = data.overdue;
            })
            .catch(function() {});
    }, refreshInterval);
})();
</script>

<?php
// DB 연결 해제
mysqli_close($connect);

// 공통 푸터 포함
require_once __DIR__ . '/includes/admin_footer.php';
?>

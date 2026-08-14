<?php
/**
 * 병원 랭킹 관리 페이지
 * - 랭킹 목록 (순위/병원명/카테고리/점수/후기수/평점/예약수/추천/기간/날짜)
 * - 랭킹 자동 계산 (gangnamunni_reviews + gangnamunni_bookings 기반)
 * - 추천 토글, 수동 점수 수정, 이전 랭킹 삭제
 * - 카테고리/기간 필터
 *
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-16
 */

session_start();
$admin_page_title = '병원 랭킹 관리';
$admin_current = 'rankings';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('rankings');

// ===== 테이블 자동 생성 =====
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    category VARCHAR(50) DEFAULT '전체',
    rank_score DECIMAL(5,2) DEFAULT 0,
    review_count INT DEFAULT 0,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    booking_count INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    period VARCHAR(20) DEFAULT 'monthly',
    rank_date DATE DEFAULT (CURRENT_DATE),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospital (hospital_id),
    INDEX idx_category (category),
    INDEX idx_date (rank_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';
$error = '';

// ===== POST 액션 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 랭킹 자동 계산 ---
    if ($action === 'calculate') {
        $calc_period = $_POST['calc_period'] ?? 'monthly';
        $calc_category = trim($_POST['calc_category'] ?? '전체');
        $valid_periods = ['weekly', 'monthly', 'all-time'];
        if (!in_array($calc_period, $valid_periods)) $calc_period = 'monthly';

        $today = date('Y-m-d');

        // 기간에 따른 날짜 조건
        $date_cond_reviews = '';
        $date_cond_bookings = '';
        if ($calc_period === 'weekly') {
            $date_cond_reviews = " AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $date_cond_bookings = " AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($calc_period === 'monthly') {
            $date_cond_reviews = " AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $date_cond_bookings = " AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        // all-time: 날짜 조건 없음

        // 카테고리 조건 (병원 카테고리 기준)
        $cat_cond = '';
        $safe_category = mysqli_real_escape_string($connect, $calc_category);
        if ($calc_category !== '전체') {
            $cat_cond = " AND h.categories LIKE '%" . $safe_category . "%'";
        }

        // 모든 활성 병원 대상으로 후기/예약 집계
        $sql = "SELECT h.id AS hospital_id,
                    COALESCE(rev.cnt, 0) AS review_count,
                    COALESCE(rev.avg_r, 0) AS avg_rating,
                    COALESCE(bk.cnt, 0) AS booking_count
                FROM gangnamunni_hospitals h
                LEFT JOIN (
                    SELECT r.hospital_id,
                           COUNT(*) AS cnt,
                           ROUND(AVG(r.rating), 2) AS avg_r
                    FROM hospital_reviews r
                    WHERE r.is_active = 1{$date_cond_reviews}
                    GROUP BY r.hospital_id
                ) rev ON rev.hospital_id = h.id
                LEFT JOIN (
                    SELECT b.hospital_id,
                           COUNT(*) AS cnt
                    FROM gangnamunni_bookings b
                    WHERE b.status NOT IN ('cancelled'){$date_cond_bookings}
                    GROUP BY b.hospital_id
                ) bk ON bk.hospital_id = h.id
                WHERE h.is_active = 1{$cat_cond}";

        $result = mysqli_query($connect, $sql);
        if ($result) {
            $calc_count = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                $hid = intval($row['hospital_id']);
                $r_count = intval($row['review_count']);
                $a_rating = round(floatval($row['avg_rating']), 2);
                $b_count = intval($row['booking_count']);
                // rank_score = (avg_rating * 20) + (review_count * 2) + (booking_count * 3)
                $score = round(($a_rating * 20) + ($r_count * 2) + ($b_count * 3), 2);

                // INSERT 또는 UPDATE (동일 hospital_id + category + period + rank_date)
                $check = mysqli_prepare($connect,
                    "SELECT id, is_featured FROM gangnamunni_rankings
                     WHERE hospital_id = ? AND category = ? AND period = ? AND rank_date = ?");
                mysqli_stmt_bind_param($check, 'isss', $hid, $safe_category, $calc_period, $today);
                mysqli_stmt_execute($check);
                $check_result = mysqli_stmt_get_result($check);
                $existing = mysqli_fetch_assoc($check_result);
                mysqli_stmt_close($check);

                if ($existing) {
                    // UPDATE -- is_featured 값은 유지
                    $upd = mysqli_prepare($connect,
                        "UPDATE gangnamunni_rankings
                         SET rank_score = ?, review_count = ?, avg_rating = ?, booking_count = ?
                         WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'didii', $score, $r_count, $a_rating, $b_count, $existing['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                } else {
                    // INSERT
                    $ins = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_rankings (hospital_id, category, rank_score, review_count, avg_rating, booking_count, is_featured, period, rank_date)
                         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
                    mysqli_stmt_bind_param($ins, 'isdidiss', $hid, $safe_category, $score, $r_count, $a_rating, $b_count, $calc_period, $today);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                }
                $calc_count++;
            }
            $message = "랭킹 자동 계산 완료: {$calc_count}개 병원 ({$calc_category} / {$calc_period}, {$today})";
        } else {
            $error = '랭킹 계산 실패: ' . mysqli_error($connect);
        }
    }

    // --- 추천(is_featured) 토글 ---
    if ($action === 'toggle_featured') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_rankings SET is_featured = IF(is_featured = 1, 0, 1) WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '추천 상태가 변경되었습니다.';
            } else {
                $error = '추천 상태 변경 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // --- 수동 점수 수정 ---
    if ($action === 'edit_score') {
        $id = intval($_POST['id'] ?? 0);
        $new_score = floatval($_POST['rank_score'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_rankings SET rank_score = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'di', $new_score, $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '랭킹 점수가 수정되었습니다.';
            } else {
                $error = '점수 수정 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // --- 이전 랭킹 삭제 ---
    if ($action === 'delete_old') {
        $delete_before = trim($_POST['delete_before'] ?? '');
        if (!empty($delete_before) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $delete_before)) {
            $stmt = mysqli_prepare($connect,
                "DELETE FROM gangnamunni_rankings WHERE rank_date < ?");
            mysqli_stmt_bind_param($stmt, 's', $delete_before);
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                $message = "{$delete_before} 이전 랭킹 {$affected}건이 삭제되었습니다.";
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = '올바른 날짜 형식(YYYY-MM-DD)을 입력해주세요.';
        }
    }

    // --- 개별 삭제 ---
    if ($action === 'delete_one') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_rankings WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '랭킹 항목이 삭제되었습니다.';
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ===== 필터 파라미터 =====
$filter_category = trim($_GET['category'] ?? '');
$filter_period = trim($_GET['period'] ?? '');
$filter_date = trim($_GET['date'] ?? '');
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

// ===== WHERE 조건 =====
$where = '1=1';
$params = [];
$types = '';

if ($filter_category !== '') {
    $where .= ' AND r.category = ?';
    $params[] = $filter_category;
    $types .= 's';
}
if ($filter_period !== '' && in_array($filter_period, ['weekly', 'monthly', 'all-time'])) {
    $where .= ' AND r.period = ?';
    $params[] = $filter_period;
    $types .= 's';
}
if ($filter_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $where .= ' AND r.rank_date = ?';
    $params[] = $filter_date;
    $types .= 's';
}
if ($search !== '') {
    $where .= ' AND h.name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

// ===== 전체 건수 =====
$count_sql = "SELECT COUNT(*) AS cnt
              FROM gangnamunni_rankings r
              LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
              WHERE {$where}";
$total = 0;
if (!empty($params)) {
    $stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $total = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);
} else {
    $cr = mysqli_query($connect, $count_sql);
    $total = intval(mysqli_fetch_assoc($cr)['cnt'] ?? 0);
}

$total_pages = max(1, ceil($total / $per_page));

// ===== 랭킹 목록 (rank_score DESC) =====
$list_sql = "SELECT r.*, h.name AS hospital_name
             FROM gangnamunni_rankings r
             LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
             WHERE {$where}
             ORDER BY r.rank_score DESC, r.review_count DESC
             LIMIT {$per_page} OFFSET {$offset}";

$rankings = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($connect, $list_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rankings[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($connect, $list_sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $rankings[] = $row;
    }
}

// ===== 카테고리 목록 (필터용) =====
$cat_list = [];
$cat_r = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_rankings ORDER BY category ASC");
if ($cat_r) {
    while ($c = mysqli_fetch_assoc($cat_r)) {
        $cat_list[] = $c['category'];
    }
}
// gangnamunni_categories에서도 가져옴
$cat_r2 = @mysqli_query($connect, "SELECT DISTINCT name FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC");
if ($cat_r2) {
    while ($c2 = mysqli_fetch_assoc($cat_r2)) {
        if (!empty($c2['name']) && !in_array($c2['name'], $cat_list)) {
            $cat_list[] = $c2['name'];
        }
    }
}
if (!in_array('전체', $cat_list)) {
    array_unshift($cat_list, '전체');
}

// ===== 통계 요약 =====
$stats = ['total' => 0, 'featured' => 0, 'latest_date' => '-'];
$stat_r = mysqli_query($connect, "SELECT COUNT(*) AS total,
    SUM(is_featured) AS featured,
    MAX(rank_date) AS latest_date
    FROM gangnamunni_rankings");
if ($stat_r) {
    $s = mysqli_fetch_assoc($stat_r);
    $stats['total'] = intval($s['total'] ?? 0);
    $stats['featured'] = intval($s['featured'] ?? 0);
    $stats['latest_date'] = $s['latest_date'] ?? '-';
}

// ===== HTML 출력 =====
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 페이지 헤더 -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-trophy me-2"></i>병원 랭킹 관리</h4>
        <small class="text-muted">
            전체 <?= number_format($stats['total']) ?>건
            | 추천 <?= number_format($stats['featured']) ?>건
            | 최신 날짜: <?= htmlspecialchars($stats['latest_date']) ?>
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#calculateModal">
            <i class="bi bi-calculator me-1"></i>랭킹 자동 계산
        </button>
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteOldModal">
            <i class="bi bi-trash me-1"></i>이전 랭킹 삭제
        </button>
    </div>
</div>

<!-- 필터 바 -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">카테고리</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">전체 카테고리</option>
                    <?php foreach ($cat_list as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filter_category === $c ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">기간</label>
                <select name="period" class="form-select form-select-sm">
                    <option value="">전체 기간</option>
                    <option value="weekly" <?= $filter_period === 'weekly' ? 'selected' : '' ?>>주간</option>
                    <option value="monthly" <?= $filter_period === 'monthly' ? 'selected' : '' ?>>월간</option>
                    <option value="all-time" <?= $filter_period === 'all-time' ? 'selected' : '' ?>>전체기간</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">날짜</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">병원 검색</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="병원명 검색..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-search"></i> 검색
                </button>
                <a href="admin_rankings.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- 랭킹 테이블 -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center" style="width:60px">순위</th>
                        <th>병원명</th>
                        <th class="text-center" style="width:90px">카테고리</th>
                        <th class="text-center" style="width:90px">랭킹 점수</th>
                        <th class="text-center" style="width:70px">후기수</th>
                        <th class="text-center" style="width:70px">평점</th>
                        <th class="text-center" style="width:70px">예약수</th>
                        <th class="text-center" style="width:60px">추천</th>
                        <th class="text-center" style="width:70px">기간</th>
                        <th class="text-center" style="width:100px">날짜</th>
                        <th class="text-center" style="width:120px">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rankings)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-inbox d-block fs-1 mb-2"></i>
                            랭킹 데이터가 없습니다. 위의 <strong>"랭킹 자동 계산"</strong> 버튼을 눌러 생성해주세요.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php
                    $rank_num = $offset;
                    foreach ($rankings as $rk):
                        $rank_num++;
                        $rank_class = '';
                        if ($rank_num === 1) $rank_class = 'text-warning fw-bold fs-5';
                        elseif ($rank_num === 2) $rank_class = 'text-secondary fw-bold fs-6';
                        elseif ($rank_num === 3) $rank_class = 'text-danger fw-bold fs-6';
                    ?>
                    <tr>
                        <td class="text-center <?= $rank_class ?>">
                            <?php if ($rank_num <= 3): ?>
                                <i class="bi bi-trophy-fill"></i>
                            <?php endif; ?>
                            #<?= $rank_num ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($rk['hospital_name'] ?? '(삭제된 병원)') ?></strong>
                            <small class="text-muted d-block">ID: <?= intval($rk['hospital_id']) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($rk['category']) ?></span>
                        </td>
                        <td class="text-center fw-bold"><?= number_format(floatval($rk['rank_score']), 2) ?></td>
                        <td class="text-center"><?= number_format(intval($rk['review_count'])) ?></td>
                        <td class="text-center">
                            <span class="text-warning"><i class="bi bi-star-fill"></i></span>
                            <?= number_format(floatval($rk['avg_rating']), 2) ?>
                        </td>
                        <td class="text-center"><?= number_format(intval($rk['booking_count'])) ?></td>
                        <td class="text-center">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="id" value="<?= intval($rk['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-link p-0" title="추천 토글">
                                    <?php if (intval($rk['is_featured'])): ?>
                                        <i class="bi bi-star-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star text-muted fs-5"></i>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-center">
                            <?php
                            $period_labels = ['weekly' => '주간', 'monthly' => '월간', 'all-time' => '전체'];
                            $period_colors = ['weekly' => 'bg-success', 'monthly' => 'bg-primary', 'all-time' => 'bg-secondary'];
                            $p = $rk['period'] ?? 'monthly';
                            ?>
                            <span class="badge <?= $period_colors[$p] ?? 'bg-secondary' ?>">
                                <?= $period_labels[$p] ?? $p ?>
                            </span>
                        </td>
                        <td class="text-center small"><?= htmlspecialchars($rk['rank_date'] ?? '') ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(<?= intval($rk['id']) ?>, <?= floatval($rk['rank_score']) ?>)"
                                    title="점수 수정">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('이 랭킹 항목을 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete_one">
                                <input type="hidden" name="id" value="<?= intval($rk['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="삭제">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php
                $qs_base = http_build_query(array_filter([
                    'category' => $filter_category,
                    'period' => $filter_period,
                    'date' => $filter_date,
                    'q' => $search,
                ]));
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $qs_base ?>&page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php
                $start_pg = max(1, $page - 3);
                $end_pg = min($total_pages, $page + 3);
                for ($p = $start_pg; $p <= $end_pg; $p++):
                ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $qs_base ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $qs_base ?>&page=<?= $page + 1 ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="text-center mt-1">
            <small class="text-muted"><?= number_format($total) ?>건 중 <?= $offset + 1 ?>~<?= min($offset + $per_page, $total) ?> (<?= $page ?>/<?= $total_pages ?>)</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== 모달: 랭킹 자동 계산 ===== -->
<div class="modal fade" id="calculateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="action" value="calculate">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>랭킹 자동 계산</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <strong>계산 공식:</strong><br>
                            <code>rank_score = (avg_rating * 20) + (review_count * 2) + (booking_count * 3)</code><br><br>
                            <i class="bi bi-info-circle"></i>
                            <code>gangnamunni_reviews</code> 테이블의 후기 수와 평점,
                            <code>gangnamunni_bookings</code> 테이블의 예약 수를 기반으로 계산합니다.
                            동일 날짜에 이미 계산된 병원은 점수가 갱신됩니다.
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">카테고리</label>
                        <select name="calc_category" class="form-select">
                            <?php foreach ($cat_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">"전체" 선택 시 모든 활성 병원 대상으로 계산합니다.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">기간</label>
                        <select name="calc_period" class="form-select">
                            <option value="monthly">월간 (최근 30일)</option>
                            <option value="weekly">주간 (최근 7일)</option>
                            <option value="all-time">전체 기간</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calculator me-1"></i>계산 실행
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== 모달: 이전 랭킹 삭제 ===== -->
<div class="modal fade" id="deleteOldModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" onsubmit="return confirm('정말 이 날짜 이전의 모든 랭킹을 삭제하시겠습니까?')">
            <input type="hidden" name="action" value="delete_old">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>이전 랭킹 삭제</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <small><i class="bi bi-exclamation-triangle"></i> 선택한 날짜 이전의 모든 랭킹 데이터가 영구 삭제됩니다.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">이 날짜 이전 삭제</label>
                        <input type="date" name="delete_before" class="form-control" required
                               value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        <div class="form-text">선택한 날짜는 포함되지 않습니다 (미만 삭제).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>삭제 실행
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== 모달: 점수 수동 수정 ===== -->
<div class="modal fade" id="editScoreModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post">
            <input type="hidden" name="action" value="edit_score">
            <input type="hidden" name="id" id="editScoreId" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>점수 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">랭킹 점수</label>
                        <input type="number" name="rank_score" id="editScoreValue"
                               class="form-control" step="0.01" min="0" max="999.99" required>
                        <div class="form-text">자동 계산 공식과 무관하게 수동 설정합니다.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>저장
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// 점수 수정 모달 열기
function openEditModal(id, currentScore) {
    document.getElementById('editScoreId').value = id;
    document.getElementById('editScoreValue').value = currentScore;
    var modal = new bootstrap.Modal(document.getElementById('editScoreModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

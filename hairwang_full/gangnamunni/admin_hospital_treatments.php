<?php
/**
 * 병원별 시술 배정 관리 페이지 (admin_hospital_treatments.php)
 * - 병원 선택 후 시술 배정/해제/가격 수정/프로모션 토글
 * - 승인 대기 시술 관리 (승인/거절)
 * - 가격 변경 이력 기록
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-25
 */
session_start();

$admin_page_title = '병원별 시술 배정';
$admin_current = 'hospital_treatments';

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
check_admin_login();
check_permission('hospital_treatments');

// 승인 워크플로우 헬퍼
require_once __DIR__ . '/includes/treatment_approval_helper.php';
ensure_treatment_approval_schema($connect);

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// CSRF 검증 함수
function verify_csrf_ht($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ===== 병원 목록 로드 =====
$hospitals = [];
$h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 선택된 병원
$hospital_id = intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? 0);

// 카테고리 목록
$categories = [];
$cat_r = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[$c['id']] = $c;

// 설정 조회 (관리 모드, 승인 필요 여부)
$treatment_mgmt_mode = 'both';
$treatment_require_approval = '0';
$set_r = mysqli_query($connect, "SELECT `key`, `value` FROM settings WHERE `key` IN ('treatment_mgmt_mode', 'treatment_require_admin_approval')");
if ($set_r) while ($s = mysqli_fetch_assoc($set_r)) {
    if ($s['key'] === 'treatment_mgmt_mode') $treatment_mgmt_mode = $s['value'];
    if ($s['key'] === 'treatment_require_admin_approval') $treatment_require_approval = $s['value'];
}

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $post_hospital_id = intval($_POST['hospital_id'] ?? 0);

    if (!verify_csrf_ht($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        // --- 시술 배정 (벌크) ---
        if ($action === 'assign' && $post_hospital_id > 0) {
            $treatment_ids = $_POST['treatment_ids'] ?? [];
            if (empty($treatment_ids)) {
                $msg = '배정할 시술을 선택해주세요.';
                $msg_type = 'warning';
            } else {
                $added = 0;
                $skipped = 0;
                foreach ($treatment_ids as $tid) {
                    $tid = intval($tid);
                    if ($tid <= 0) continue;
                    // 중복 체크
                    $chk = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?");
                    mysqli_stmt_bind_param($chk, 'ii', $post_hospital_id, $tid);
                    mysqli_stmt_execute($chk);
                    $chk_r = mysqli_stmt_get_result($chk);
                    if (mysqli_fetch_assoc($chk_r)) {
                        $skipped++;
                        mysqli_stmt_close($chk);
                        continue;
                    }
                    mysqli_stmt_close($chk);

                    // 마스터에서 기본 가격 조회
                    $price_stmt = mysqli_prepare($connect, "SELECT avg_price_min, avg_price_max FROM gangnamunni_treatment_master WHERE id = ?");
                    mysqli_stmt_bind_param($price_stmt, 'i', $tid);
                    mysqli_stmt_execute($price_stmt);
                    $price_r = mysqli_stmt_get_result($price_stmt);
                    $price_row = mysqli_fetch_assoc($price_r);
                    $p_min = intval($price_row['avg_price_min'] ?? 0);
                    $p_max = intval($price_row['avg_price_max'] ?? 0);
                    mysqli_stmt_close($price_stmt);

                    $status = 'active';
                    $registered_by = 'admin';
                    $stmt = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_hospital_treatments (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 0, '', ?, ?, 1, NOW(), NOW())");
                    mysqli_stmt_bind_param($stmt, 'iiiiss', $post_hospital_id, $tid, $p_min, $p_max, $status, $registered_by);
                    if (mysqli_stmt_execute($stmt)) {
                        $added++;
                        // 가격 이력 기록
                        $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
                        $hist = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_treatment_price_history (hospital_id, treatment_id, price_min, price_max, changed_by, recorded_at)
                             VALUES (?, ?, ?, ?, ?, NOW())");
                        mysqli_stmt_bind_param($hist, 'iiiis', $post_hospital_id, $tid, $p_min, $p_max, $admin_name);
                        mysqli_stmt_execute($hist);
                        mysqli_stmt_close($hist);
                    }
                    mysqli_stmt_close($stmt);
                }
                $msg = "{$added}개 시술이 배정되었습니다.";
                if ($skipped > 0) $msg .= " (이미 배정된 {$skipped}개 건너뜀)";
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode($msg));
                exit;
            }
        }

        // --- 시술 해제 ---
        if ($action === 'remove') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('시술 배정이 해제되었습니다.'));
                exit;
            }
        }

        // --- 가격 수정 ---
        if ($action === 'update_price') {
            $id = intval($_POST['id'] ?? 0);
            $new_price_min = intval($_POST['price_min'] ?? 0);
            $new_price_max = intval($_POST['price_max'] ?? 0);
            $new_dosage_value = trim($_POST['dosage_value'] ?? '') !== '' ? floatval($_POST['dosage_value']) : null;
            $new_dosage_unit = trim($_POST['dosage_unit'] ?? '') !== '' ? trim($_POST['dosage_unit']) : null;
            if ($id > 0) {
                // 기존 데이터 조회 (이력 기록용)
                $old_stmt = mysqli_prepare($connect, "SELECT hospital_id, treatment_id FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($old_stmt, 'i', $id);
                mysqli_stmt_execute($old_stmt);
                $old_r = mysqli_stmt_get_result($old_stmt);
                $old_row = mysqli_fetch_assoc($old_r);
                mysqli_stmt_close($old_stmt);

                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET price_min = ?, price_max = ?, dosage_value = ?, dosage_unit = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'iidsi', $new_price_min, $new_price_max, $new_dosage_value, $new_dosage_unit, $id);
                if (mysqli_stmt_execute($stmt)) {
                    // 가격 이력 기록
                    if ($old_row) {
                        $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
                        $hist = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_treatment_price_history (hospital_id, treatment_id, price_min, price_max, changed_by, recorded_at)
                             VALUES (?, ?, ?, ?, ?, NOW())");
                        mysqli_stmt_bind_param($hist, 'iiiis', $old_row['hospital_id'], $old_row['treatment_id'], $new_price_min, $new_price_max, $admin_name);
                        mysqli_stmt_execute($hist);
                        mysqli_stmt_close($hist);
                    }
                    header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('가격이 수정되었습니다.'));
                    exit;
                } else {
                    $msg = '가격 수정 실패: ' . mysqli_error($connect);
                    $msg_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
        }

        // --- 승인 ---
        if ($action === 'approve') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // 기존 상태 조회 (로그용)
                $old_stmt = mysqli_prepare($connect, "SELECT hospital_id, treatment_id, status FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($old_stmt, 'i', $id);
                mysqli_stmt_execute($old_stmt);
                $old_r = mysqli_stmt_get_result($old_stmt);
                $old_row = mysqli_fetch_assoc($old_r);
                mysqli_stmt_close($old_stmt);

                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET status = 'active', reject_reason = NULL, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // 승인 타임스탬프 + 로그 + 알림
                $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
                $admin_id = intval($_SESSION['ad_admin_id'] ?? 0);
                update_treatment_approval_timestamp($connect, $id, 'approved', $admin_name);
                log_treatment_approval($connect, [
                    'hospital_treatment_id' => $id,
                    'hospital_id' => intval($old_row['hospital_id'] ?? $post_hospital_id),
                    'treatment_id' => intval($old_row['treatment_id'] ?? 0),
                    'action' => 'approved',
                    'old_status' => $old_row['status'] ?? 'pending',
                    'new_status' => 'active',
                    'admin_id' => $admin_id,
                    'admin_name' => $admin_name,
                ]);
                notify_treatment_approval($connect, $id, 'approved', $admin_name);

                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('시술이 승인되었습니다.'));
                exit;
            }
        }

        // --- 거절 ---
        if ($action === 'reject') {
            $id = intval($_POST['id'] ?? 0);
            $reason = trim($_POST['reject_reason'] ?? '');
            if ($id > 0) {
                // 기존 상태 조회 (로그용)
                $old_stmt = mysqli_prepare($connect, "SELECT hospital_id, treatment_id, status FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($old_stmt, 'i', $id);
                mysqli_stmt_execute($old_stmt);
                $old_r = mysqli_stmt_get_result($old_stmt);
                $old_row = mysqli_fetch_assoc($old_r);
                mysqli_stmt_close($old_stmt);

                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET status = 'rejected', reject_reason = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $reason, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // 거절 타임스탬프 + 로그 + 알림
                $admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
                $admin_id = intval($_SESSION['ad_admin_id'] ?? 0);
                update_treatment_approval_timestamp($connect, $id, 'rejected', $admin_name);
                log_treatment_approval($connect, [
                    'hospital_treatment_id' => $id,
                    'hospital_id' => intval($old_row['hospital_id'] ?? $post_hospital_id),
                    'treatment_id' => intval($old_row['treatment_id'] ?? 0),
                    'action' => 'rejected',
                    'old_status' => $old_row['status'] ?? 'pending',
                    'new_status' => 'rejected',
                    'reason' => $reason,
                    'admin_id' => $admin_id,
                    'admin_name' => $admin_name,
                ]);
                notify_treatment_approval($connect, $id, 'rejected', $admin_name, $reason);

                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('시술이 거절되었습니다.'));
                exit;
            }
        }

        // --- 프로모션 토글 ---
        if ($action === 'toggle_promoted') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET is_promoted = IF(is_promoted = 1, 0, 1), updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('프로모션 상태가 변경되었습니다.'));
                exit;
            }
        }
    }
}

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = 'success';
}

// ===== 데이터 로드 (병원 선택 시) =====
$assigned_treatments = [];
$pending_treatments = [];
$stats = ['total_assigned' => 0, 'pending' => 0, 'promoted' => 0];
$selected_hospital_name = '';

if ($hospital_id > 0) {
    // 병원명
    foreach ($hospitals as $h) {
        if (intval($h['id']) === $hospital_id) {
            $selected_hospital_name = $h['name'];
            break;
        }
    }

    // 통계
    $stat_sql = "SELECT
        COUNT(CASE WHEN status = 'active' THEN 1 END) AS total_assigned,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
        COUNT(CASE WHEN is_promoted = 1 AND status = 'active' THEN 1 END) AS promoted
        FROM gangnamunni_hospital_treatments WHERE hospital_id = ?";
    $stat_stmt = mysqli_prepare($connect, $stat_sql);
    mysqli_stmt_bind_param($stat_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stat_stmt);
    $stat_r = mysqli_stmt_get_result($stat_stmt);
    if ($s = mysqli_fetch_assoc($stat_r)) {
        $stats['total_assigned'] = intval($s['total_assigned']);
        $stats['pending'] = intval($s['pending']);
        $stats['promoted'] = intval($s['promoted']);
    }
    mysqli_stmt_close($stat_stmt);

    // 승인 대기 시술
    $pend_sql = "SELECT ht.*, tm.name AS treatment_name, tm.hashtag, tc.name AS category_name
        FROM gangnamunni_hospital_treatments ht
        LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'pending'
        ORDER BY ht.created_at DESC";
    $pend_stmt = mysqli_prepare($connect, $pend_sql);
    mysqli_stmt_bind_param($pend_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($pend_stmt);
    $pend_r = mysqli_stmt_get_result($pend_stmt);
    while ($row = mysqli_fetch_assoc($pend_r)) $pending_treatments[] = $row;
    mysqli_stmt_close($pend_stmt);

    // 배정된 활성 시술
    $assign_sql = "SELECT ht.*, tm.name AS treatment_name, tm.hashtag, tm.avg_price_min, tm.avg_price_max, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_hospital_treatments ht
        LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'active'
        ORDER BY tc.sort_order ASC, tm.sort_order ASC, tm.name ASC";
    $assign_stmt = mysqli_prepare($connect, $assign_sql);
    mysqli_stmt_bind_param($assign_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($assign_stmt);
    $assign_r = mysqli_stmt_get_result($assign_stmt);
    while ($row = mysqli_fetch_assoc($assign_r)) $assigned_treatments[] = $row;
    mysqli_stmt_close($assign_stmt);
}

// ===== 미배정 시술 목록 (추가용) =====
$available_treatments = [];
if ($hospital_id > 0) {
    $avail_sql = "SELECT tm.*, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.is_active = 1
          AND tm.id NOT IN (SELECT treatment_id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND status IN ('active','pending'))
        ORDER BY tc.sort_order ASC, tm.name ASC";
    $avail_stmt = mysqli_prepare($connect, $avail_sql);
    mysqli_stmt_bind_param($avail_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($avail_stmt);
    $avail_r = mysqli_stmt_get_result($avail_stmt);
    while ($row = mysqli_fetch_assoc($avail_r)) $available_treatments[] = $row;
    mysqli_stmt_close($avail_stmt);
}

// ===== 병원 미선택 시: 전체 요약 통계 =====
$global_stats = [];
$top_hospitals = [];
$global_pending_count = 0;
if ($hospital_id <= 0) {
    // 전체 통계
    $gs_r = mysqli_query($connect, "SELECT
        COUNT(DISTINCT hospital_id) AS hospital_count,
        COUNT(*) AS total_assignments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'active' AND is_promoted = 1 THEN 1 END) AS promoted_count
        FROM gangnamunni_hospital_treatments");
    if ($gs_r) $global_stats = mysqli_fetch_assoc($gs_r);
    $global_pending_count = intval($global_stats['pending_count'] ?? 0);

    // Top 10 병원 (시술 수 기준)
    $top_sql = "SELECT h.id, h.name, COUNT(ht.id) AS treatment_count,
        COUNT(CASE WHEN ht.is_promoted = 1 THEN 1 END) AS promoted_count,
        COUNT(CASE WHEN ht.status = 'pending' THEN 1 END) AS pending_count
        FROM gangnamunni_hospital_treatments ht
        JOIN gangnamunni_hospitals h ON ht.hospital_id = h.id
        WHERE ht.status = 'active'
        GROUP BY h.id, h.name
        ORDER BY treatment_count DESC
        LIMIT 10";
    $top_r = mysqli_query($connect, $top_sql);
    if ($top_r) while ($row = mysqli_fetch_assoc($top_r)) $top_hospitals[] = $row;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $msg_type === 'danger' ? 'exclamation-triangle-fill' : 'check-circle-fill'; ?> me-1"></i>
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 페이지 헤더 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-clipboard2-pulse me-2"></i>병원별 시술 배정</h4>
            <small class="text-muted">
                관리 모드: <span class="badge bg-secondary"><?php
                    $mode_labels = ['both' => '관리자+병원', 'admin_only' => '관리자 전용', 'hospital_only' => '병원 자체'];
                    echo $mode_labels[$treatment_mgmt_mode] ?? $treatment_mgmt_mode;
                ?></span>
                | 승인 필요: <span class="badge bg-<?php echo $treatment_require_approval ? 'warning' : 'success'; ?>"><?php echo $treatment_require_approval ? 'ON' : 'OFF'; ?></span>
            </small>
        </div>
    </div>

    <!-- 병원 선택 드롭다운 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold mb-1"><i class="bi bi-hospital me-1"></i>병원 선택</label>
                    <select name="hospital_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- 병원을 선택하세요 --</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?php echo intval($h['id']); ?>" <?php echo $hospital_id === intval($h['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo intval($h['id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>조회</button>
                </div>
                <?php if ($hospital_id > 0): ?>
                <div class="col-md-2">
                    <a href="admin_hospital_treatments.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg me-1"></i>초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

<?php if ($hospital_id <= 0): ?>
    <!-- ===== 병원 미선택: 전체 요약 ===== -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>병원을 선택하면 해당 병원의 시술 배정을 관리할 수 있습니다.
    </div>

    <!-- 전체 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary"><?php echo number_format(intval($global_stats['hospital_count'] ?? 0)); ?></h4>
                    <small class="text-muted">참여 병원</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success"><?php echo number_format(intval($global_stats['total_assignments'] ?? 0)); ?></h4>
                    <small class="text-muted">총 배정</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning"><?php echo number_format($global_pending_count); ?></h4>
                    <small class="text-muted">승인 대기</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger"><?php echo number_format(intval($global_stats['promoted_count'] ?? 0)); ?></h4>
                    <small class="text-muted">프로모션</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 병원 -->
    <?php if (!empty($top_hospitals)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 10 병원 (배정 시술 수)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>병원명</th>
                        <th class="text-center" style="width:100px;">배정 시술</th>
                        <th class="text-center" style="width:100px;">프로모션</th>
                        <th class="text-center" style="width:100px;">승인 대기</th>
                        <th style="width:100px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_hospitals as $idx => $th): ?>
                    <tr>
                        <td class="fw-bold"><?php echo $idx + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($th['name']); ?></strong>
                            <small class="text-muted d-block">ID: <?php echo intval($th['id']); ?></small>
                        </td>
                        <td class="text-center"><span class="badge bg-primary"><?php echo number_format(intval($th['treatment_count'])); ?></span></td>
                        <td class="text-center"><span class="badge bg-danger"><?php echo number_format(intval($th['promoted_count'])); ?></span></td>
                        <td class="text-center">
                            <?php if (intval($th['pending_count']) > 0): ?>
                            <span class="badge bg-warning text-dark"><?php echo intval($th['pending_count']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="admin_hospital_treatments.php?hospital_id=<?php echo intval($th['id']); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-right"></i> 관리
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ===== 병원 선택됨: 시술 배정 관리 ===== -->

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-4 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0 text-primary"><?php echo number_format($stats['total_assigned']); ?></h5>
                    <small class="text-muted">배정 시술</small>
                </div>
            </div>
        </div>
        <div class="col-4 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0 text-warning"><?php echo number_format($stats['pending']); ?></h5>
                    <small class="text-muted">승인 대기</small>
                </div>
            </div>
        </div>
        <div class="col-4 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0 text-danger"><?php echo number_format($stats['promoted']); ?></h5>
                    <small class="text-muted">프로모션</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 승인 대기 섹션 ===== -->
    <?php if (!empty($pending_treatments)): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>승인 대기 시술 (<?php echo count($pending_treatments); ?>건)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>시술명</th>
                        <th>카테고리</th>
                        <th class="text-center">해시태그</th>
                        <th class="text-center">가격</th>
                        <th class="text-center">등록자</th>
                        <th class="text-center">신청일</th>
                        <th class="text-center" style="width:200px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_treatments as $pt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pt['treatment_name'] ?? '(삭제됨)'); ?></strong></td>
                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($pt['category_name'] ?? '-'); ?></span></td>
                        <td class="text-center"><small class="text-muted"><?php echo htmlspecialchars($pt['hashtag'] ?? ''); ?></small></td>
                        <td class="text-center"><?php echo number_format(intval($pt['price_min'])); ?>~<?php echo number_format(intval($pt['price_max'])); ?>만원</td>
                        <td class="text-center"><span class="badge bg-<?php echo $pt['registered_by'] === 'admin' ? 'primary' : 'success'; ?>"><?php echo $pt['registered_by'] === 'admin' ? '관리자' : '병원'; ?></span></td>
                        <td class="text-center"><small><?php echo substr($pt['created_at'] ?? '', 0, 16); ?></small></td>
                        <td class="text-center">
                            <!-- 승인 -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo intval($pt['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="승인"><i class="bi bi-check-lg"></i> 승인</button>
                            </form>
                            <!-- 거절 -->
                            <button type="button" class="btn btn-danger btn-sm" title="거절"
                                onclick="openRejectModal(<?php echo intval($pt['id']); ?>, '<?php echo htmlspecialchars(addslashes($pt['treatment_name'] ?? ''), ENT_QUOTES); ?>')">
                                <i class="bi bi-x-lg"></i> 거절
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== 배정 시술 테이블 ===== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>배정 시술 목록 (<?php echo count($assigned_treatments); ?>건)</h6>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addTreatmentSection">
                <i class="bi bi-plus-lg me-1"></i>시술 추가
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th style="width:100px;">해시태그</th>
                        <th>시술명</th>
                        <th class="text-center" style="width:100px;">카테고리</th>
                        <th class="text-center" style="width:160px;">가격 (만원)</th>
                        <th class="text-center" style="width:80px;">프로모션</th>
                        <th style="width:150px;">비고</th>
                        <th class="text-center" style="width:80px;">등록자</th>
                        <th class="text-center" style="width:140px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assigned_treatments)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                            배정된 시술이 없습니다. <strong>"시술 추가"</strong> 버튼을 눌러 배정하세요.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($assigned_treatments as $idx => $at): ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td><small class="text-primary"><?php echo htmlspecialchars($at['hashtag'] ?? ''); ?></small></td>
                        <td><strong><?php echo htmlspecialchars($at['treatment_name'] ?? '(삭제됨)'); ?></strong></td>
                        <td class="text-center"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($at['category_name'] ?? '-'); ?></span></td>
                        <td class="text-center">
                            <?php
                            $disp_min = intval($at['price_min']);
                            $disp_max = intval($at['price_max']);
                            $is_default = ($disp_min == 0 && $disp_max == 0);
                            if ($is_default) {
                                $disp_min = intval($at['avg_price_min'] ?? 0);
                                $disp_max = intval($at['avg_price_max'] ?? 0);
                            }
                            ?>
                            <span class="text-nowrap<?php echo $is_default ? ' text-muted fst-italic' : ''; ?>">
                                <?php echo number_format($disp_min); ?>~<?php echo number_format($disp_max); ?>만원
                            </span>
                            <?php if ($is_default && ($disp_min > 0 || $disp_max > 0)): ?>
                            <br><small class="text-muted">(기본값)</small>
                            <?php endif; ?>
                            <?php if ($at['dosage_value'] !== null && $at['dosage_unit']): ?>
                            <br><span class="badge bg-light text-dark border"><?php echo rtrim(rtrim(number_format((float)$at['dosage_value'], 2), '0'), '.'); ?><?php echo htmlspecialchars($at['dosage_unit']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="toggle_promoted">
                                <input type="hidden" name="id" value="<?php echo intval($at['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-sm btn-link p-0" title="프로모션 토글">
                                    <?php if (intval($at['is_promoted'])): ?>
                                        <i class="bi bi-star-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star text-muted fs-5"></i>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                        <td><small class="text-muted"><?php echo htmlspecialchars(mb_substr($at['note'] ?? '', 0, 20, 'UTF-8')); ?></small></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $at['registered_by'] === 'admin' ? 'primary' : 'success'; ?>">
                                <?php echo $at['registered_by'] === 'admin' ? '관리자' : '병원'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <!-- 가격 수정 -->
                            <button class="btn btn-outline-primary btn-sm" title="가격 수정"
                                onclick="openPriceModal(<?php echo intval($at['id']); ?>, <?php echo intval($at['price_min']); ?>, <?php echo intval($at['price_max']); ?>, '<?php echo htmlspecialchars(addslashes($at['treatment_name'] ?? ''), ENT_QUOTES); ?>', <?php echo $at['dosage_value'] !== null ? (float)$at['dosage_value'] : 'null'; ?>, '<?php echo htmlspecialchars(addslashes($at['dosage_unit'] ?? ''), ENT_QUOTES); ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 배정 해제 -->
                            <form method="post" class="d-inline" onsubmit="return confirm('이 시술 배정을 해제하시겠습니까?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?php echo intval($at['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="배정 해제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== 시술 추가 섹션 (Collapse) ===== -->
    <div class="collapse mb-4" id="addTreatmentSection">
        <div class="card border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10">
                <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>시술 추가 (미배정 시술 <?php echo count($available_treatments); ?>건)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($available_treatments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle" style="font-size:2rem;"></i><br>
                        모든 시술이 이미 배정되어 있습니다.
                    </div>
                <?php else: ?>
                <form method="post" id="assignForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">

                    <!-- 카테고리 탭 필터 -->
                    <ul class="nav nav-pills nav-fill mb-3" id="catTabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-cat="all" onclick="filterCategory(event, 'all')">전체 (<?php echo count($available_treatments); ?>)</a>
                        </li>
                        <?php
                        $avail_cats = [];
                        foreach ($available_treatments as $at) {
                            $cid = intval($at['cat_id'] ?? 0);
                            $cname = $at['category_name'] ?? '미분류';
                            if (!isset($avail_cats[$cid])) $avail_cats[$cid] = ['name' => $cname, 'count' => 0];
                            $avail_cats[$cid]['count']++;
                        }
                        foreach ($avail_cats as $cid => $ci): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-cat="<?php echo $cid; ?>" onclick="filterCategory(event, <?php echo $cid; ?>)">
                                <?php echo htmlspecialchars($ci['name']); ?> (<?php echo $ci['count']; ?>)
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- 전체 선택/해제 + 추가 버튼 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            <label class="form-check-label" for="selectAll">보이는 항목 전체 선택</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>선택 시술 추가
                        </button>
                    </div>

                    <!-- 시술 체크박스 목록 -->
                    <div class="row g-2" id="treatmentList" style="max-height:400px; overflow-y:auto;">
                        <?php foreach ($available_treatments as $at): ?>
                        <div class="col-md-4 col-sm-6 treatment-item" data-cat="<?php echo intval($at['cat_id'] ?? 0); ?>">
                            <div class="card card-body py-2 px-3">
                                <div class="form-check">
                                    <input class="form-check-input treatment-cb" type="checkbox" name="treatment_ids[]" value="<?php echo intval($at['id']); ?>" id="t_<?php echo intval($at['id']); ?>">
                                    <label class="form-check-label" for="t_<?php echo intval($at['id']); ?>">
                                        <strong><?php echo htmlspecialchars($at['name']); ?></strong>
                                        <?php if (!empty($at['hashtag'])): ?>
                                        <small class="text-primary d-block"><?php echo htmlspecialchars($at['hashtag']); ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($at['category_name'] ?? '미분류'); ?>
                                            <?php if (intval($at['avg_price_min']) > 0 || intval($at['avg_price_max']) > 0): ?>
                                            | <?php echo number_format(intval($at['avg_price_min'])); ?>~<?php echo number_format(intval($at['avg_price_max'])); ?>만원
                                            <?php endif; ?>
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== 승인 이력 섹션 ===== -->
    <?php
    $approval_logs = [];
    if ($hospital_id > 0) {
        $log_sql = "SELECT al.*, tm.name AS treatment_name, tm.hashtag
            FROM gangnamunni_treatment_approval_log al
            LEFT JOIN gangnamunni_treatment_master tm ON al.treatment_id = tm.id
            WHERE al.hospital_id = ?
            ORDER BY al.created_at DESC
            LIMIT 50";
        $log_stmt = mysqli_prepare($connect, $log_sql);
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, 'i', $hospital_id);
            mysqli_stmt_execute($log_stmt);
            $log_r = mysqli_stmt_get_result($log_stmt);
            while ($row = mysqli_fetch_assoc($log_r)) $approval_logs[] = $row;
            mysqli_stmt_close($log_stmt);
        }
    }
    if (!empty($approval_logs)):
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>승인 처리 이력 (최근 50건)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:160px;">일시</th>
                        <th>시술명</th>
                        <th class="text-center" style="width:100px;">처리</th>
                        <th class="text-center" style="width:120px;">이전 상태</th>
                        <th class="text-center" style="width:120px;">변경 상태</th>
                        <th>사유</th>
                        <th class="text-center" style="width:100px;">처리자</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approval_logs as $log):
                        $action_badges = [
                            'submitted' => '<span class="badge bg-info">제출</span>',
                            'approved' => '<span class="badge bg-success">승인</span>',
                            'rejected' => '<span class="badge bg-danger">거절</span>',
                            'resubmitted' => '<span class="badge bg-warning text-dark">재제출</span>',
                            'cancelled' => '<span class="badge bg-secondary">취소</span>',
                        ];
                        $status_labels = [
                            'pending' => '<span class="badge bg-warning text-dark">대기</span>',
                            'active' => '<span class="badge bg-success">활성</span>',
                            'rejected' => '<span class="badge bg-danger">거절</span>',
                        ];
                    ?>
                    <tr>
                        <td><small><?php echo substr($log['created_at'] ?? '', 0, 19); ?></small></td>
                        <td>
                            <strong><?php echo htmlspecialchars($log['treatment_name'] ?? '(삭제됨)'); ?></strong>
                            <?php if (!empty($log['hashtag'])): ?>
                            <small class="text-primary ms-1"><?php echo htmlspecialchars($log['hashtag']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $action_badges[$log['action']] ?? htmlspecialchars($log['action']); ?></td>
                        <td class="text-center"><?php echo $status_labels[$log['old_status'] ?? ''] ?? '<span class="text-muted">-</span>'; ?></td>
                        <td class="text-center"><?php echo $status_labels[$log['new_status'] ?? ''] ?? htmlspecialchars($log['new_status'] ?? '-'); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars(mb_substr($log['reason'] ?? '', 0, 50, 'UTF-8')); ?></small></td>
                        <td class="text-center"><small><?php echo htmlspecialchars($log['admin_name'] ?? $log['hospital_user'] ?? '-'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<!-- ===== 가격 수정 모달 ===== -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="id" id="price_id">
            <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-currency-won me-2"></i>가격 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">시술: <strong id="price_treatment_name"></strong></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">최소 가격 (만원)</label>
                            <input type="number" name="price_min" id="price_min" class="form-control" min="0" step="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">최대 가격 (만원)</label>
                            <input type="number" name="price_max" id="price_max" class="form-control" min="0" step="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">샷수/용량 (선택)</label>
                            <input type="number" name="dosage_value" id="dosage_value" class="form-control" min="0" step="0.01" placeholder="예: 600">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">단위</label>
                            <select name="dosage_unit" id="dosage_unit" class="form-select">
                                <option value="">-- 단위 없음 --</option>
                                <option value="샷">샷</option>
                                <option value="cc">cc</option>
                                <option value="ml">ml</option>
                                <option value="회">회</option>
                                <option value="병">병</option>
                                <option value="IU">IU</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2">가격 변경은 이력에 자동 기록됩니다. 샷수/용량은 시술 종류와 별도로 관리됩니다.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== 거절 모달 ===== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="reject_id">
            <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>시술 거절</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>시술 <strong id="reject_treatment_name"></strong>을(를) 거절합니다.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">거절 사유</label>
                        <textarea name="reject_reason" class="form-control" rows="3" placeholder="거절 사유를 입력하세요 (선택)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>거절</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// 가격 수정 모달
function openPriceModal(id, priceMin, priceMax, name, dosageValue, dosageUnit) {
    document.getElementById('price_id').value = id;
    document.getElementById('price_min').value = priceMin;
    document.getElementById('price_max').value = priceMax;
    document.getElementById('dosage_value').value = dosageValue || '';
    document.getElementById('dosage_unit').value = dosageUnit || '';
    document.getElementById('price_treatment_name').textContent = name;
    new bootstrap.Modal(document.getElementById('priceModal')).show();
}

// 거절 모달
function openRejectModal(id, name) {
    document.getElementById('reject_id').value = id;
    document.getElementById('reject_treatment_name').textContent = name;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// 카테고리 탭 필터
function filterCategory(e, catId) {
    e.preventDefault();
    // 탭 활성화
    document.querySelectorAll('#catTabs .nav-link').forEach(function(el) { el.classList.remove('active'); });
    e.target.classList.add('active');
    // 항목 필터
    document.querySelectorAll('.treatment-item').forEach(function(el) {
        if (catId === 'all' || parseInt(el.dataset.cat) === catId) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
    // 전체 선택 체크 해제
    document.getElementById('selectAll').checked = false;
}

// 전체 선택/해제
function toggleSelectAll(el) {
    document.querySelectorAll('.treatment-item').forEach(function(item) {
        if (item.style.display !== 'none') {
            var cb = item.querySelector('.treatment-cb');
            if (cb) cb.checked = el.checked;
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

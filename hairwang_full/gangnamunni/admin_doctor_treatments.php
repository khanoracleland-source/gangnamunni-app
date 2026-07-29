<?php
/**
 * 의사별 시술 전문성 관리 (admin_doctor_treatments.php)
 * - 병원 선택 → 의사 목록 → 시술 배정/수정/해제
 * - speciality_level (basic/advanced/expert), case_count, certification
 * - 카테고리별 시술 선택 모달
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-25
 */
session_start();

$admin_page_title = '의사별 시술 전문성';
$admin_current = 'doctor_treatments';

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
require_permission('doctor_treatments');

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// CSRF 검증 함수
function verify_csrf_dt($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ===== 테이블 자동 생성 =====
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_doctor_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    treatment_id INT NOT NULL,
    case_count INT DEFAULT 0,
    speciality_level ENUM('basic','advanced','expert') DEFAULT 'basic',
    certification VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_doctor_treatment (doctor_id, treatment_id),
    INDEX idx_hospital (hospital_id),
    INDEX idx_treatment (treatment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ===== 병원 목록 로드 =====
$hospitals = [];
$h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 선택된 병원
$hospital_id = intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? 0);

// 선택된 의사
$doctor_id = intval($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);

// 카테고리 목록
$categories = [];
$cat_r = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[$c['id']] = $c;

// ===== doctors 테이블 구조 =====
// HMS doctors: id, user_id, doctor_department_id, specialist, tenant_id (hospital_id 없음)
// 이름은 users 테이블에서 JOIN (users.first_name, users.last_name)

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $post_hospital_id = intval($_POST['hospital_id'] ?? 0);
    $post_doctor_id = intval($_POST['doctor_id'] ?? 0);

    if (!verify_csrf_dt($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        // --- 시술 배정 (벌크) ---
        if ($action === 'assign' && $post_doctor_id > 0 && $post_hospital_id > 0) {
            $treatment_ids = $_POST['treatment_ids'] ?? [];
            $default_level = $_POST['default_level'] ?? 'basic';
            if (!in_array($default_level, ['basic', 'advanced', 'expert'])) $default_level = 'basic';

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
                    $chk = mysqli_prepare($connect, "SELECT id FROM gangnamunni_doctor_treatments WHERE doctor_id = ? AND treatment_id = ?");
                    mysqli_stmt_bind_param($chk, 'ii', $post_doctor_id, $tid);
                    mysqli_stmt_execute($chk);
                    $chk_r = mysqli_stmt_get_result($chk);
                    if (mysqli_fetch_assoc($chk_r)) {
                        $skipped++;
                        mysqli_stmt_close($chk);
                        continue;
                    }
                    mysqli_stmt_close($chk);

                    $stmt = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_doctor_treatments (doctor_id, hospital_id, treatment_id, case_count, speciality_level, certification, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, 0, ?, NULL, 1, NOW(), NOW())");
                    mysqli_stmt_bind_param($stmt, 'iiis', $post_doctor_id, $post_hospital_id, $tid, $default_level);
                    if (mysqli_stmt_execute($stmt)) $added++;
                    mysqli_stmt_close($stmt);
                }
                $msg = "{$added}개 시술이 배정되었습니다.";
                if ($skipped > 0) $msg .= " (이미 배정된 {$skipped}개 건너뜀)";
                header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode($msg));
                exit;
            }
        }

        // --- 전문성 수준 변경 ---
        if ($action === 'update_level') {
            $id = intval($_POST['id'] ?? 0);
            $level = $_POST['speciality_level'] ?? 'basic';
            if (!in_array($level, ['basic', 'advanced', 'expert'])) $level = 'basic';
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_doctor_treatments SET speciality_level = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $level, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode('전문성 수준이 변경되었습니다.'));
                exit;
            }
        }

        // --- 시술 건수 수정 ---
        if ($action === 'update_case_count') {
            $id = intval($_POST['id'] ?? 0);
            $case_count = max(0, intval($_POST['case_count'] ?? 0));
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_doctor_treatments SET case_count = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $case_count, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode('시술 건수가 수정되었습니다.'));
                exit;
            }
        }

        // --- 인증/자격 수정 ---
        if ($action === 'update_certification') {
            $id = intval($_POST['id'] ?? 0);
            $certification = trim(mb_substr($_POST['certification'] ?? '', 0, 500, 'UTF-8'));
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_doctor_treatments SET certification = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $certification, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode('인증/자격이 수정되었습니다.'));
                exit;
            }
        }

        // --- 배정 해제 ---
        if ($action === 'remove') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_doctor_treatments WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode('시술 배정이 해제되었습니다.'));
                exit;
            }
        }

        // --- 일괄 업데이트 (인라인 편집) ---
        if ($action === 'bulk_update' && $post_doctor_id > 0) {
            $ids = $_POST['row_ids'] ?? [];
            $levels = $_POST['row_levels'] ?? [];
            $counts = $_POST['row_counts'] ?? [];
            $certs = $_POST['row_certs'] ?? [];
            $updated = 0;
            foreach ($ids as $idx => $rid) {
                $rid = intval($rid);
                if ($rid <= 0) continue;
                $lv = $levels[$idx] ?? 'basic';
                if (!in_array($lv, ['basic', 'advanced', 'expert'])) $lv = 'basic';
                $cnt = max(0, intval($counts[$idx] ?? 0));
                $cert = trim(mb_substr($certs[$idx] ?? '', 0, 500, 'UTF-8'));
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_doctor_treatments SET speciality_level = ?, case_count = ?, certification = ?, updated_at = NOW() WHERE id = ? AND doctor_id = ?");
                mysqli_stmt_bind_param($stmt, 'sisii', $lv, $cnt, $cert, $rid, $post_doctor_id);
                if (mysqli_stmt_execute($stmt)) $updated++;
                mysqli_stmt_close($stmt);
            }
            header("Location: admin_doctor_treatments.php?hospital_id={$post_hospital_id}&doctor_id={$post_doctor_id}&msg=" . urlencode("{$updated}개 항목이 일괄 수정되었습니다."));
            exit;
        }
    }
}

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = 'success';
}

// ===== 의사 목록 (병원 선택 시) =====
// HMS doctors 테이블: id, user_id, doctor_department_id (이름은 users 테이블)
// hospital_id가 없으므로 전체 의사를 로드하거나, doctor_treatments에서 이 병원에 이미 배정된 의사 + 전체 의사
$doctors = [];
if ($hospital_id > 0) {
    $doc_sql = "SELECT d.id, u.first_name, u.last_name, d.doctor_department_id, d.specialist
        FROM doctors d
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY u.first_name ASC, u.last_name ASC";
    $doc_r = mysqli_query($connect, $doc_sql);
    if ($doc_r) while ($d = mysqli_fetch_assoc($doc_r)) $doctors[] = $d;
}

// ===== 선택 의사의 배정 시술 =====
$assigned_treatments = [];
$selected_doctor_name = '';
if ($doctor_id > 0) {
    // 의사명
    foreach ($doctors as $d) {
        if (intval($d['id']) === $doctor_id) {
            $selected_doctor_name = trim($d['first_name'] . ' ' . $d['last_name']);
            break;
        }
    }
    // 배정된 시술
    $at_sql = "SELECT dt.*, tm.name AS treatment_name, tm.hashtag, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_doctor_treatments dt
        LEFT JOIN gangnamunni_treatment_master tm ON dt.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE dt.doctor_id = ? AND dt.hospital_id = ? AND dt.is_active = 1
        ORDER BY tc.sort_order ASC, tm.name ASC";
    $at_stmt = mysqli_prepare($connect, $at_sql);
    mysqli_stmt_bind_param($at_stmt, 'ii', $doctor_id, $hospital_id);
    mysqli_stmt_execute($at_stmt);
    $at_r = mysqli_stmt_get_result($at_stmt);
    while ($row = mysqli_fetch_assoc($at_r)) $assigned_treatments[] = $row;
    mysqli_stmt_close($at_stmt);
}

// ===== 미배정 시술 목록 (추가 모달용) =====
$available_treatments = [];
if ($doctor_id > 0 && $hospital_id > 0) {
    $avail_sql = "SELECT tm.*, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.is_active = 1
          AND tm.id NOT IN (SELECT treatment_id FROM gangnamunni_doctor_treatments WHERE doctor_id = ? AND is_active = 1)
        ORDER BY tc.sort_order ASC, tm.name ASC";
    $avail_stmt = mysqli_prepare($connect, $avail_sql);
    mysqli_stmt_bind_param($avail_stmt, 'i', $doctor_id);
    mysqli_stmt_execute($avail_stmt);
    $avail_r = mysqli_stmt_get_result($avail_stmt);
    while ($row = mysqli_fetch_assoc($avail_r)) $available_treatments[] = $row;
    mysqli_stmt_close($avail_stmt);
}

// ===== 통계 =====
$stats = ['total_doctors' => 0, 'avg_treatments' => 0, 'expert_count' => 0, 'total_cases' => 0];
if ($hospital_id > 0) {
    // 이 병원에서 시술 배정이 있는 의사 수
    $st_stmt = mysqli_prepare($connect,
        "SELECT COUNT(DISTINCT doctor_id) AS total_doctors,
                COUNT(*) AS total_assignments,
                SUM(CASE WHEN speciality_level = 'expert' THEN 1 ELSE 0 END) AS expert_count,
                SUM(case_count) AS total_cases
         FROM gangnamunni_doctor_treatments WHERE hospital_id = ? AND is_active = 1");
    mysqli_stmt_bind_param($st_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($st_stmt);
    $st_r = mysqli_stmt_get_result($st_stmt);
    if ($s = mysqli_fetch_assoc($st_r)) {
        $stats['total_doctors'] = intval($s['total_doctors']);
        $total_assignments = intval($s['total_assignments']);
        $stats['avg_treatments'] = $stats['total_doctors'] > 0 ? round($total_assignments / $stats['total_doctors'], 1) : 0;
        $stats['expert_count'] = intval($s['expert_count']);
        $stats['total_cases'] = intval($s['total_cases']);
    }
    mysqli_stmt_close($st_stmt);
}

// ===== 전체 요약 (병원 미선택 시) =====
$global_stats = [];
$top_doctors = [];
if ($hospital_id <= 0) {
    $gs_r = mysqli_query($connect, "SELECT
        COUNT(DISTINCT doctor_id) AS doctor_count,
        COUNT(DISTINCT hospital_id) AS hospital_count,
        COUNT(*) AS total_assignments,
        SUM(CASE WHEN speciality_level = 'expert' THEN 1 ELSE 0 END) AS expert_count,
        SUM(case_count) AS total_cases
        FROM gangnamunni_doctor_treatments WHERE is_active = 1");
    if ($gs_r) $global_stats = mysqli_fetch_assoc($gs_r);

    // Top 10 의사 (시술 수 기준)
    $top_sql = "SELECT dt.doctor_id, u.first_name, u.last_name,
        COUNT(dt.id) AS treatment_count,
        SUM(CASE WHEN dt.speciality_level = 'expert' THEN 1 ELSE 0 END) AS expert_count,
        SUM(dt.case_count) AS total_cases
        FROM gangnamunni_doctor_treatments dt
        LEFT JOIN doctors d ON dt.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE dt.is_active = 1
        GROUP BY dt.doctor_id, u.first_name, u.last_name
        ORDER BY treatment_count DESC
        LIMIT 10";
    $top_r = mysqli_query($connect, $top_sql);
    if ($top_r) while ($row = mysqli_fetch_assoc($top_r)) $top_doctors[] = $row;
}

// ===== 의사 프로필 카드 데이터 (의사 선택 시) =====
$doctor_level_dist = ['basic' => 0, 'advanced' => 0, 'expert' => 0];
if ($doctor_id > 0 && count($assigned_treatments) > 0) {
    foreach ($assigned_treatments as $at) {
        $lv = $at['speciality_level'] ?? 'basic';
        if (isset($doctor_level_dist[$lv])) $doctor_level_dist[$lv]++;
    }
}

// 병원명
$selected_hospital_name = '';
foreach ($hospitals as $h) {
    if (intval($h['id']) === $hospital_id) {
        $selected_hospital_name = $h['name'];
        break;
    }
}

require_once __DIR__ . '/includes/admin_header.php';

// 전문성 수준 라벨/뱃지 색상
$level_labels = ['basic' => '기본', 'advanced' => '심화', 'expert' => '전문'];
$level_colors = ['basic' => 'secondary', 'advanced' => 'primary', 'expert' => 'danger'];
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
            <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>의사별 시술 전문성</h4>
            <small class="text-muted">의사에게 시술을 배정하고 전문성 수준을 관리합니다.</small>
        </div>
    </div>

    <!-- 병원 선택 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold mb-1"><i class="bi bi-hospital me-1"></i>병원 선택</label>
                    <select name="hospital_id" class="form-select" onchange="this.form.querySelector('[name=doctor_id]').value=''; this.form.submit()">
                        <option value="">-- 병원을 선택하세요 --</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?php echo intval($h['id']); ?>" <?php echo $hospital_id === intval($h['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo intval($h['id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($hospital_id > 0 && count($doctors) > 0): ?>
                <div class="col-md-4">
                    <label class="form-label fw-bold mb-1"><i class="bi bi-person me-1"></i>의사 선택</label>
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- 의사를 선택하세요 --</option>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo intval($d['id']); ?>" <?php echo $doctor_id === intval($d['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(trim($d['first_name'] . ' ' . $d['last_name'])); ?> (ID: <?php echo intval($d['id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="doctor_id" value="">
                <?php endif; ?>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>조회</button>
                </div>
                <?php if ($hospital_id > 0): ?>
                <div class="col-md-2">
                    <a href="admin_doctor_treatments.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg me-1"></i>초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

<?php if ($hospital_id <= 0): ?>
    <!-- ===== 병원 미선택: 전체 요약 ===== -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>병원을 선택하면 해당 병원 의사들의 시술 전문성을 관리할 수 있습니다.
    </div>

    <!-- 전체 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary"><?php echo number_format(intval($global_stats['doctor_count'] ?? 0)); ?></h4>
                    <small class="text-muted">전문 의사 수</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success"><?php echo number_format(intval($global_stats['total_assignments'] ?? 0)); ?></h4>
                    <small class="text-muted">총 배정 수</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger"><?php echo number_format(intval($global_stats['expert_count'] ?? 0)); ?></h4>
                    <small class="text-muted">전문(Expert) 수</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning"><?php echo number_format(intval($global_stats['total_cases'] ?? 0)); ?></h4>
                    <small class="text-muted">총 시술 건수</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 의사 -->
    <?php if (count($top_doctors) > 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 10 의사 (시술 배정 수 기준)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>의사명</th>
                            <th class="text-center">배정 시술 수</th>
                            <th class="text-center">Expert</th>
                            <th class="text-center">총 시술 건수</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_doctors as $idx => $td): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <i class="bi bi-person-circle me-1 text-primary"></i>
                                <?php echo htmlspecialchars(trim(($td['first_name'] ?? '') . ' ' . ($td['last_name'] ?? ''))); ?>
                            </td>
                            <td class="text-center"><span class="badge bg-primary"><?php echo intval($td['treatment_count']); ?></span></td>
                            <td class="text-center"><span class="badge bg-danger"><?php echo intval($td['expert_count']); ?></span></td>
                            <td class="text-center"><?php echo number_format(intval($td['total_cases'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php elseif ($hospital_id > 0 && $doctor_id <= 0): ?>
    <!-- ===== 병원 선택, 의사 미선택: 병원 요약 ===== -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong><?php echo htmlspecialchars($selected_hospital_name); ?></strong>의 의사를 선택하면 시술 전문성을 관리할 수 있습니다.
        <?php if (count($doctors) === 0): ?>
        <br><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>이 병원에 등록된 의사가 없습니다.</span>
        <?php endif; ?>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary"><?php echo $stats['total_doctors']; ?></h4>
                    <small class="text-muted">시술 배정 의사</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success"><?php echo $stats['avg_treatments']; ?></h4>
                    <small class="text-muted">의사당 평균 시술</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger"><?php echo $stats['expert_count']; ?></h4>
                    <small class="text-muted">전문(Expert)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning"><?php echo number_format($stats['total_cases']); ?></h4>
                    <small class="text-muted">총 시술 건수</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 의사 목록 카드 -->
    <?php if (count($doctors) > 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-people me-2"></i><?php echo htmlspecialchars($selected_hospital_name); ?> 의사 목록 (<?php echo count($doctors); ?>명)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>의사명</th>
                            <th class="text-center">배정 시술 수</th>
                            <th class="text-center">전문성 분포</th>
                            <th class="text-center">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctors as $d):
                            // 각 의사의 배정 수 조회
                            $cnt_stmt = mysqli_prepare($connect, "SELECT
                                COUNT(*) AS total,
                                SUM(CASE WHEN speciality_level='basic' THEN 1 ELSE 0 END) AS cnt_basic,
                                SUM(CASE WHEN speciality_level='advanced' THEN 1 ELSE 0 END) AS cnt_advanced,
                                SUM(CASE WHEN speciality_level='expert' THEN 1 ELSE 0 END) AS cnt_expert
                                FROM gangnamunni_doctor_treatments WHERE doctor_id = ? AND hospital_id = ? AND is_active = 1");
                            mysqli_stmt_bind_param($cnt_stmt, 'ii', $d['id'], $hospital_id);
                            mysqli_stmt_execute($cnt_stmt);
                            $cnt_r = mysqli_stmt_get_result($cnt_stmt);
                            $cnt = mysqli_fetch_assoc($cnt_r);
                            mysqli_stmt_close($cnt_stmt);
                        ?>
                        <tr>
                            <td><?php echo intval($d['id']); ?></td>
                            <td>
                                <i class="bi bi-person-circle me-1 text-primary"></i>
                                <?php echo htmlspecialchars(trim($d['first_name'] . ' ' . $d['last_name'])); ?>
                            </td>
                            <td class="text-center"><span class="badge bg-primary"><?php echo intval($cnt['total'] ?? 0); ?></span></td>
                            <td class="text-center">
                                <?php if (intval($cnt['total'] ?? 0) > 0): ?>
                                <span class="badge bg-secondary me-1" title="기본"><?php echo intval($cnt['cnt_basic'] ?? 0); ?></span>
                                <span class="badge bg-primary me-1" title="심화"><?php echo intval($cnt['cnt_advanced'] ?? 0); ?></span>
                                <span class="badge bg-danger" title="전문"><?php echo intval($cnt['cnt_expert'] ?? 0); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="admin_doctor_treatments.php?hospital_id=<?php echo $hospital_id; ?>&doctor_id=<?php echo intval($d['id']); ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-gear me-1"></i>관리
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ===== 의사 선택됨: 시술 관리 ===== -->

    <!-- 의사 프로필 카드 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                            <i class="bi bi-person-circle text-primary fs-3"></i>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0"><?php echo htmlspecialchars($selected_doctor_name); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($selected_hospital_name); ?> | 배정 시술: <?php echo count($assigned_treatments); ?>개</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-2 mt-2 mt-md-0">
                        <div class="text-center px-3">
                            <span class="badge bg-secondary fs-6"><?php echo $doctor_level_dist['basic']; ?></span>
                            <br><small class="text-muted">기본</small>
                        </div>
                        <div class="text-center px-3">
                            <span class="badge bg-primary fs-6"><?php echo $doctor_level_dist['advanced']; ?></span>
                            <br><small class="text-muted">심화</small>
                        </div>
                        <div class="text-center px-3">
                            <span class="badge bg-danger fs-6"><?php echo $doctor_level_dist['expert']; ?></span>
                            <br><small class="text-muted">전문</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary"><?php echo count($assigned_treatments); ?></h4>
                    <small class="text-muted">배정 시술 수</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger"><?php echo $doctor_level_dist['expert']; ?></h4>
                    <small class="text-muted">전문(Expert)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <?php
                    $total_cases = 0;
                    foreach ($assigned_treatments as $at) $total_cases += intval($at['case_count']);
                    ?>
                    <h4 class="mb-0 text-success"><?php echo number_format($total_cases); ?></h4>
                    <small class="text-muted">총 시술 건수</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <?php
                    $cert_count = 0;
                    foreach ($assigned_treatments as $at) if (!empty($at['certification'])) $cert_count++;
                    ?>
                    <h4 class="mb-0 text-warning"><?php echo $cert_count; ?></h4>
                    <small class="text-muted">인증/자격 보유</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 시술 추가 버튼 -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>배정된 시술 목록</h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTreatmentModal">
            <i class="bi bi-plus-lg me-1"></i>시술 추가
        </button>
    </div>

    <!-- 배정 시술 테이블 (일괄 편집 폼) -->
    <?php if (count($assigned_treatments) > 0): ?>
    <form method="post" action="admin_doctor_treatments.php">
        <input type="hidden" name="action" value="bulk_update">
        <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
        <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>시술명</th>
                                <th>카테고리</th>
                                <th style="width:140px" class="text-center">전문성 수준</th>
                                <th style="width:120px" class="text-center">시술 건수</th>
                                <th>인증/자격</th>
                                <th style="width:80px" class="text-center">삭제</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_treatments as $idx => $at): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="row_ids[]" value="<?php echo intval($at['id']); ?>">
                                    <?php echo $idx + 1; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($at['treatment_name'] ?? '(삭제됨)'); ?></strong>
                                    <?php if (!empty($at['hashtag'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($at['hashtag']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($at['category_name'])): ?>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($at['category_name']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <select name="row_levels[]" class="form-select form-select-sm">
                                        <?php foreach ($level_labels as $lv => $lb): ?>
                                        <option value="<?php echo $lv; ?>" <?php echo ($at['speciality_level'] ?? 'basic') === $lv ? 'selected' : ''; ?>><?php echo $lb; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="number" name="row_counts[]" class="form-control form-control-sm text-center" value="<?php echo intval($at['case_count']); ?>" min="0" style="width:100px">
                                </td>
                                <td>
                                    <input type="text" name="row_certs[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($at['certification'] ?? ''); ?>" placeholder="자격/인증 입력" maxlength="500">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmRemove(<?php echo intval($at['id']); ?>)" title="배정 해제">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>일괄 저장
                </button>
            </div>
        </div>
    </form>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>
            <p>이 의사에게 배정된 시술이 없습니다.</p>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTreatmentModal">
                <i class="bi bi-plus-lg me-1"></i>시술 추가하기
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 삭제 폼 (히든) -->
    <form id="removeForm" method="post" action="admin_doctor_treatments.php" style="display:none">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
        <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="id" id="removeId" value="">
    </form>

    <!-- ===== 시술 추가 모달 ===== -->
    <div class="modal fade" id="addTreatmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="admin_doctor_treatments.php">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                    <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>시술 추가 - <?php echo htmlspecialchars($selected_doctor_name); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- 기본 전문성 수준 -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">기본 전문성 수준</label>
                            <select name="default_level" class="form-select form-select-sm" style="max-width:200px">
                                <option value="basic" selected>기본</option>
                                <option value="advanced">심화</option>
                                <option value="expert">전문</option>
                            </select>
                        </div>

                        <!-- 검색 -->
                        <div class="mb-3">
                            <input type="text" id="treatmentSearch" class="form-control form-control-sm" placeholder="시술명 검색...">
                        </div>

                        <!-- 전체 선택 -->
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="selectAllTreatments">
                                <label class="form-check-label fw-bold" for="selectAllTreatments">전체 선택</label>
                            </div>
                            <span class="text-muted ms-2" id="selectedCount">0개 선택</span>
                        </div>

                        <?php if (count($available_treatments) === 0): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                            모든 시술이 이미 배정되어 있습니다.
                        </div>
                        <?php else:
                            // 카테고리별 그룹핑
                            $grouped = [];
                            foreach ($available_treatments as $at) {
                                $cat_name = $at['category_name'] ?? '미분류';
                                $cat_id = intval($at['cat_id'] ?? 0);
                                if (!isset($grouped[$cat_id])) $grouped[$cat_id] = ['name' => $cat_name, 'items' => []];
                                $grouped[$cat_id]['items'][] = $at;
                            }
                        ?>
                            <?php foreach ($grouped as $cid => $group): ?>
                            <div class="mb-3 treatment-category-group">
                                <h6 class="border-bottom pb-1 mb-2">
                                    <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($group['name']); ?>
                                    <span class="badge bg-secondary ms-1"><?php echo count($group['items']); ?></span>
                                </h6>
                                <div class="row g-1">
                                    <?php foreach ($group['items'] as $item): ?>
                                    <div class="col-md-6 treatment-item" data-name="<?php echo htmlspecialchars(mb_strtolower($item['name'], 'UTF-8')); ?>">
                                        <div class="form-check">
                                            <input class="form-check-input treatment-checkbox" type="checkbox" name="treatment_ids[]" value="<?php echo intval($item['id']); ?>" id="tr_<?php echo intval($item['id']); ?>">
                                            <label class="form-check-label" for="tr_<?php echo intval($item['id']); ?>">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                                <?php if (!empty($item['hashtag'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['hashtag']); ?></small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <?php if (count($available_treatments) > 0): ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>선택 시술 배정</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; ?>

</div><!-- /.container-fluid -->

<script>
// 삭제 확인
function confirmRemove(id) {
    if (confirm('이 시술 배정을 해제하시겠습니까?')) {
        document.getElementById('removeId').value = id;
        document.getElementById('removeForm').submit();
    }
}

// 시술 검색 필터
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('treatmentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            var items = document.querySelectorAll('.treatment-item');
            items.forEach(function(item) {
                var name = item.getAttribute('data-name') || '';
                item.style.display = name.indexOf(query) !== -1 ? '' : 'none';
            });
            // 카테고리 그룹 중 보이는 아이템이 없으면 그룹도 숨김
            var groups = document.querySelectorAll('.treatment-category-group');
            groups.forEach(function(group) {
                var visible = group.querySelectorAll('.treatment-item:not([style*="display: none"])');
                group.style.display = visible.length > 0 ? '' : 'none';
            });
        });
    }

    // 전체 선택
    var selectAll = document.getElementById('selectAllTreatments');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.treatment-checkbox').forEach(function(cb) {
                // 보이는 것만 선택
                var item = cb.closest('.treatment-item');
                if (item && item.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            updateSelectedCount();
        });
    }

    // 선택 카운트
    document.querySelectorAll('.treatment-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        var count = document.querySelectorAll('.treatment-checkbox:checked').length;
        var el = document.getElementById('selectedCount');
        if (el) el.textContent = count + '개 선택';
    }
});
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';

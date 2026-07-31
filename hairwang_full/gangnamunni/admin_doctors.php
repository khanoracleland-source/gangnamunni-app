<?php
/**
 * 병원 공개용 의사 프로필 관리
 * 이름, 소속 병원, 사진, 병원별 시술 분야, 자격·소속 학회만 관리
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-03-04
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php'; // 병원 격리(super_admin=전체, 그 외=소속 병원 의사)

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 + 권한 체크
require_admin_login();
require_permission('doctors');

$message = '';
$error = '';
$current_admin = get_current_admin();
$allowed_hospital_ids = get_strict_admin_hospital_ids($connect);
$hosp_in = $allowed_hospital_ids === null
    ? null
    : (empty($allowed_hospital_ids) ? '0' : implode(',', array_map('intval', $allowed_hospital_ids)));
$hosp_where_plain = ($hosp_in !== null) ? " WHERE hospital_id IN ({$hosp_in})" : "";
$hosp_and_plain   = ($hosp_in !== null) ? " AND hospital_id IN ({$hosp_in})" : "";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function doctor_hospital_allowed($hospital_id, $allowed_hospital_ids) {
    return $hospital_id > 0
        && ($allowed_hospital_ids === null || in_array((int)$hospital_id, $allowed_hospital_ids, true));
}

function validate_doctor_profile($name, $hospital_id, $memberships) {
    if ($name === '') return '의사 이름은 필수입니다.';
    if (mb_strlen($name, 'UTF-8') > 100) return '의사 이름은 100자 이하여야 합니다.';
    if ($hospital_id <= 0) return '소속 병원을 선택해주세요.';
    if (mb_strlen($memberships, 'UTF-8') > 2000) return '자격 및 학회 정보는 2000자 이하여야 합니다.';
    return '';
}

function upload_doctor_photo($file_key, $doctor_id) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('프로필 사진 업로드에 실패했습니다.');
    }
    if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('프로필 사진은 5MB 이하여야 합니다.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$file_key]['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('JPG, PNG, WebP 또는 GIF 이미지만 업로드할 수 있습니다.');
    }

    $upload_dir = __DIR__ . '/uploads/doctors/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('프로필 사진 저장 폴더를 만들 수 없습니다.');
    }
    $filename = 'doctor_' . (int)$doctor_id . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $filename)) {
        throw new RuntimeException('프로필 사진을 저장하지 못했습니다.');
    }
    return 'uploads/doctors/' . $filename;
}

function sync_doctor_treatments($connect, $doctor_id, $hospital_id, $treatment_ids) {
    $doctor_id = (int)$doctor_id;
    $hospital_id = (int)$hospital_id;
    $selected = array_values(array_unique(array_filter(array_map('intval', (array)$treatment_ids))));

    // 선택값을 해당 병원에 실제 배정된 시술로 다시 제한한다.
    $valid = [];
    if (!empty($selected)) {
        $id_csv = implode(',', $selected);
        $result = mysqli_query($connect,
            "SELECT DISTINCT treatment_id
               FROM gangnamunni_hospital_treatments
              WHERE hospital_id = {$hospital_id}
                AND treatment_id IN ({$id_csv})
                AND is_active = 1
                AND status = 'active'");
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $valid[] = (int)$row['treatment_id'];
        }
    }

    if (empty($valid)) {
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_doctor_treatments SET is_active = 0, updated_at = NOW()
              WHERE doctor_id = ? AND hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $doctor_id, $hospital_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return;
    }

    $valid_csv = implode(',', $valid);
    mysqli_query($connect,
        "UPDATE gangnamunni_doctor_treatments SET is_active = 0, updated_at = NOW()
          WHERE doctor_id = {$doctor_id}
            AND hospital_id = {$hospital_id}
            AND treatment_id NOT IN ({$valid_csv})");

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_doctor_treatments
            (doctor_id, hospital_id, treatment_id, case_count, speciality_level, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 0, 'basic', 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE hospital_id=VALUES(hospital_id), is_active=1, updated_at=NOW()");
    foreach ($valid as $treatment_id) {
        mysqli_stmt_bind_param($stmt, 'iii', $doctor_id, $hospital_id, $treatment_id);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

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

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $action = '';
    }

    // 신규 등록
    if ($action === 'create_doctor') {
        $name = trim($_POST['name'] ?? '');
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $memberships = trim($_POST['memberships'] ?? '');

        $error = validate_doctor_profile($name, $hospital_id, $memberships);
        if ($error !== '') {
        } elseif (!doctor_hospital_allowed($hospital_id, $allowed_hospital_ids)) {
            $error = '선택한 병원에 의사를 등록할 권한이 없습니다.';
        } else {
            $uploaded_photo = null;
            mysqli_begin_transaction($connect);
            try {
                $stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_doctors
                        (hospital_id, name, qualification, photo_url, status, created_at, updated_at)
                     VALUES (?, ?, ?, '', 'active', NOW(), NOW())");
                mysqli_stmt_bind_param($stmt, 'iss', $hospital_id, $name, $memberships);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                $doctor_id = mysqli_insert_id($connect);
                mysqli_stmt_close($stmt);

                $uploaded_photo = upload_doctor_photo('photo_file', $doctor_id);
                if ($uploaded_photo !== null) {
                    $photo_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_doctors SET photo_url = ? WHERE id = ?");
                    mysqli_stmt_bind_param($photo_stmt, 'si', $uploaded_photo, $doctor_id);
                    mysqli_stmt_execute($photo_stmt);
                    mysqli_stmt_close($photo_stmt);
                }
                sync_doctor_treatments($connect, $doctor_id, $hospital_id, $_POST['treatment_ids'] ?? []);
                mysqli_commit($connect);
                header('Location: admin_doctors.php?view=' . $doctor_id . '&msg=' . urlencode("의사 '{$name}'이(가) 등록되었습니다."));
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($connect);
                if ($uploaded_photo && str_starts_with($uploaded_photo, 'uploads/doctors/')) {
                    $orphan_path = __DIR__ . '/' . $uploaded_photo;
                    if (is_file($orphan_path)) @unlink($orphan_path);
                }
                $error = '등록 실패: ' . $e->getMessage();
            }
        }
    }

    // 수정
    if ($action === 'edit_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $memberships = trim($_POST['memberships'] ?? '');
        $uploaded_photo = null;
        $own_stmt = mysqli_prepare($connect, "SELECT hospital_id, photo_url FROM gangnamunni_doctors WHERE id = ?");
        mysqli_stmt_bind_param($own_stmt, 'i', $doctor_id);
        mysqli_stmt_execute($own_stmt);
        $own_row = mysqli_fetch_assoc(mysqli_stmt_get_result($own_stmt));
        mysqli_stmt_close($own_stmt);
        $edit_allowed = $own_row
            && doctor_hospital_allowed((int)$own_row['hospital_id'], $allowed_hospital_ids)
            && doctor_hospital_allowed($hospital_id, $allowed_hospital_ids);
        $photo_url = (string)($own_row['photo_url'] ?? '');
        $validation_error = validate_doctor_profile($name, $hospital_id, $memberships);

        // 파일 업로드 처리 (URL보다 우선)
        if ($edit_allowed && $validation_error === '') {
            try {
                $uploaded_photo = upload_doctor_photo('photo_file', $doctor_id);
                if ($uploaded_photo !== null) $photo_url = $uploaded_photo;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $edit_allowed = false;
            }
        }

        if (!$edit_allowed) {
            $error = '이 의사를 수정할 권한이 없습니다.';
        } elseif ($validation_error !== '') {
            $error = $validation_error;
        } elseif ($doctor_id > 0) {
            mysqli_begin_transaction($connect);
            try {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_doctors
                        SET name=?, hospital_id=?, qualification=?, photo_url=?, updated_at=NOW()
                      WHERE id=?"
                );
                if (!$stmt) throw new RuntimeException(mysqli_error($connect));
                mysqli_stmt_bind_param($stmt, 'sissi', $name, $hospital_id, $memberships, $photo_url, $doctor_id);
                if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);

                if ((int)$own_row['hospital_id'] !== $hospital_id) {
                    $clear_old = mysqli_prepare($connect,
                        "UPDATE gangnamunni_doctor_treatments SET is_active=0, updated_at=NOW()
                          WHERE doctor_id = ? AND hospital_id = ?");
                    mysqli_stmt_bind_param($clear_old, 'ii', $doctor_id, $own_row['hospital_id']);
                    mysqli_stmt_execute($clear_old);
                    mysqli_stmt_close($clear_old);
                }
                sync_doctor_treatments($connect, $doctor_id, $hospital_id, $_POST['treatment_ids'] ?? []);
                mysqli_commit($connect);
                $message = "의사 '{$name}' 정보가 수정되었습니다.";
            } catch (Throwable $e) {
                mysqli_rollback($connect);
                if ($uploaded_photo && str_starts_with($uploaded_photo, 'uploads/doctors/')) {
                    $orphan_path = __DIR__ . '/' . $uploaded_photo;
                    if (is_file($orphan_path)) @unlink($orphan_path);
                }
                $error = '수정 실패: ' . $e->getMessage();
            }
        }
    }

    // 삭제 대신 퇴직 처리: 과거 예약/후기 연결을 보존한다.
    if ($action === 'delete_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        if ($doctor_id > 0) {
            mysqli_begin_transaction($connect);
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_doctors SET status='resigned', updated_at=NOW()
                  WHERE id=?
                    AND hospital_id IN (" . ($hosp_in === null ? "SELECT id FROM gangnamunni_hospitals" : $hosp_in) . ")");
            mysqli_stmt_bind_param($stmt, 'i', $doctor_id);
            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) < 1) {
                    mysqli_rollback($connect);
                    $error = '이 의사를 삭제할 권한이 없습니다.';
                } else {
                    $cleanup = mysqli_prepare($connect,
                        "UPDATE gangnamunni_doctor_treatments SET is_active=0, updated_at=NOW() WHERE doctor_id = ?");
                    mysqli_stmt_bind_param($cleanup, 'i', $doctor_id);
                    mysqli_stmt_execute($cleanup);
                    mysqli_stmt_close($cleanup);
                    mysqli_commit($connect);
                    $message = "의사 #{$doctor_id}이(가) 퇴직 처리되었습니다.";
                }
                // 삭제 후 목록으로 리다이렉트
                if (!empty($message) && isset($_GET['view'])) {
                    header('Location: admin_doctors.php?msg=' . urlencode($message));
                    exit;
                }
            } else {
                mysqli_rollback($connect);
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // POST 후 redirect (PRG 패턴) - view 모드일 때
    if (!empty($message) && isset($_GET['view'])) {
        $view_id = intval($_GET['view']);
        $edit_flag = isset($_GET['edit']) ? '&edit=1' : '';
        header('Location: admin_doctors.php?view=' . $view_id . $edit_flag . '&msg=' . urlencode($message));
        exit;
    }
}

// GET 파라미터에서 메시지 수신 (PRG 패턴)
if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

// ===== 상세 보기 / 수정 모드 =====
$view_mode = isset($_GET['view']) ? intval($_GET['view']) : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$doctor = null;

if ($view_mode > 0) {
    $view_scope_sql = $hosp_in === null ? '' : " AND d.hospital_id IN ({$hosp_in})";
    $stmt = mysqli_prepare($connect,
        "SELECT d.*, h.name AS hospital_name
         FROM gangnamunni_doctors d
         LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
         WHERE d.id = ?{$view_scope_sql}"
    );
    mysqli_stmt_bind_param($stmt, 'i', $view_mode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// ===== 병원 목록 (필터 드롭다운/수정 폼 select용) — 소속 병원만 =====
$hospital_list = [];
$h_where = ($hosp_in !== null) ? " WHERE id IN ({$hosp_in})" : "";
$h_result = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals{$h_where} ORDER BY name ASC");
if ($h_result) {
    while ($hr = mysqli_fetch_assoc($h_result)) {
        $hospital_list[] = $hr;
    }
}

// ===== 소속 병원에 실제 배정된 시술 목록 =====
$hospital_treatments = [];
$treatment_scope_sql = $hosp_in === null ? '' : " AND ht.hospital_id IN ({$hosp_in})";
$treatment_result = mysqli_query($connect,
    "SELECT DISTINCT ht.hospital_id, ht.treatment_id, tm.name, tc.name AS category_name
       FROM gangnamunni_hospital_treatments ht
       JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
  LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
      WHERE ht.is_active = 1
        AND ht.status = 'active'
        {$treatment_scope_sql}
   ORDER BY ht.hospital_id, tc.sort_order, tm.name");
while ($treatment_result && $tr = mysqli_fetch_assoc($treatment_result)) {
    $hospital_treatments[(int)$tr['hospital_id']][] = $tr;
}

$doctor_treatment_ids = [];
if ($doctor) {
    $assigned_stmt = mysqli_prepare($connect,
        "SELECT treatment_id FROM gangnamunni_doctor_treatments WHERE doctor_id = ? AND hospital_id = ? AND is_active = 1");
    mysqli_stmt_bind_param($assigned_stmt, 'ii', $doctor['id'], $doctor['hospital_id']);
    mysqli_stmt_execute($assigned_stmt);
    $assigned_result = mysqli_stmt_get_result($assigned_stmt);
    while ($assigned = mysqli_fetch_assoc($assigned_result)) {
        $doctor_treatment_ids[] = (int)$assigned['treatment_id'];
    }
    mysqli_stmt_close($assigned_stmt);
}

// ===== 목록 모드: 필터, 검색, 페이징 =====
$filter_hospital = isset($_GET['hospital']) ? intval($_GET['hospital']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50])) $per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// WHERE 조건 조립
$where = '1=1';
$where_params = [];
$where_types = '';

if ($filter_hospital > 0) {
    $where .= ' AND d.hospital_id = ?';
    $where_params[] = $filter_hospital;
    $where_types .= 'i';
}

if (!empty($search)) {
    $where .= ' AND (d.name LIKE ? OR d.qualification LIKE ? OR h.name LIKE ?
        OR EXISTS (
            SELECT 1
              FROM gangnamunni_doctor_treatments dt_search
              JOIN gangnamunni_treatment_master tm_search ON tm_search.id = dt_search.treatment_id
             WHERE dt_search.doctor_id = d.id
               AND dt_search.is_active = 1
               AND tm_search.name LIKE ?
        ))';
    $search_param = "%{$search}%";
    $where_params = array_merge($where_params, [$search_param, $search_param, $search_param, $search_param]);
    $where_types .= 'ssss';
}

// ===== 병원 격리: 목록에도 소속 병원 의사만 (상단에서 계산한 $hosp_in 재사용) =====
if ($hosp_in !== null) {
    $where .= " AND d.hospital_id IN ({$hosp_in})";
}

// ===== 통계 =====
$stats = ['total' => 0];

$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_doctors" . $hosp_where_plain);
if ($stat_result) $stats['total'] = (int)mysqli_fetch_assoc($stat_result)['c'];

// 소속 병원 수
$hospital_count_result = mysqli_query($connect, "SELECT COUNT(DISTINCT hospital_id) AS c FROM gangnamunni_doctors WHERE hospital_id > 0" . $hosp_and_plain);
$stats['hospitals'] = $hospital_count_result ? (int)mysqli_fetch_assoc($hospital_count_result)['c'] : 0;

// 필터링된 전체 건수
$count_sql = "SELECT COUNT(*) as c FROM gangnamunni_doctors d LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id WHERE {$where}";
if (!empty($where_params)) {
    $count_stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($count_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['c'];
    mysqli_stmt_close($count_stmt);
} else {
    $total = (int)mysqli_fetch_assoc(mysqli_query($connect, $count_sql))['c'];
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 목록 조회
$doctors = [];
if (!$view_mode) {
    $list_sql = "SELECT d.*, h.name AS hospital_name
                 FROM gangnamunni_doctors d
                 LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
                 WHERE {$where}
                 ORDER BY d.created_at DESC LIMIT ?, ?";
    if (!empty($where_params)) {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        $bind_types = $where_types . 'ii';
        $bind_params = array_merge($where_params, [$offset, $per_page]);
        mysqli_stmt_bind_param($list_stmt, $bind_types, ...$bind_params);
    } else {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        mysqli_stmt_bind_param($list_stmt, 'ii', $offset, $per_page);
    }
    mysqli_stmt_execute($list_stmt);
    $list_result = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($list_result)) {
        $row['treatment_names'] = [];
        $doctors[] = $row;
    }
    mysqli_stmt_close($list_stmt);

    if (!empty($doctors)) {
        $doctor_indexes = [];
        foreach ($doctors as $index => $row) {
            $doctor_indexes[(int)$row['id']] = $index;
        }
        $doctor_ids = implode(',', array_keys($doctor_indexes));
        $names_result = mysqli_query($connect,
            "SELECT dt.doctor_id, tm.name
               FROM gangnamunni_doctor_treatments dt
               JOIN gangnamunni_treatment_master tm ON tm.id = dt.treatment_id
              WHERE dt.is_active = 1
                AND dt.doctor_id IN ({$doctor_ids})
              ORDER BY tm.name");
        while ($names_result && $name_row = mysqli_fetch_assoc($names_result)) {
            $index = $doctor_indexes[(int)$name_row['doctor_id']];
            $doctors[$index]['treatment_names'][] = $name_row['name'];
        }
    }
}

// URL 빌더 헬퍼 (현재 필터 유지)
function buildDoctorUrl($overrides = []) {
    global $filter_hospital, $per_page, $search;
    $params = [
        'per_page' => $overrides['per_page'] ?? $per_page,
        'page' => $overrides['page'] ?? 1,
    ];
    $hosp = $overrides['hospital'] ?? $filter_hospital;
    if ($hosp > 0) $params['hospital'] = $hosp;
    $s = $overrides['search'] ?? $search;
    if (!empty($s)) $params['search'] = $s;
    return '?' . http_build_query($params);
}

// 공통 헤더
$admin_page_title = '의사 관리';
$admin_current = 'doctors';

$admin_extra_css = '
.doctor-stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
.doctor-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.doctor-detail-label { font-size: 12px; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: 2px; }
.doctor-detail-value { font-size: 14px; margin-bottom: 12px; }
.doctor-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid var(--bs-border-color); }
.doctor-photo-sm { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 1px solid var(--bs-border-color); }
.specialty-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
.spec-bar { height: 8px; border-radius: 4px; background: var(--bs-tertiary-bg); overflow: hidden; }
.spec-bar-fill { height: 100%; border-radius: 4px; }
.doctor-treatment-group { display: none; }
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

<?php if ($view_mode && $doctor): ?>
<!-- ============================================================ -->
<!-- 상세 보기 / 수정 모드                                        -->
<!-- ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="admin_doctors.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="bi bi-arrow-left"></i> 목록으로
        </a>
        <span class="fw-bold fs-5"><?php echo htmlspecialchars($doctor['name']); ?></span>
    </div>
    <div class="d-flex gap-1">
        <?php if (!$edit_mode): ?>
        <a href="?view=<?php echo $doctor['id']; ?>&edit=1" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>수정
        </a>
        <?php else: ?>
        <a href="?view=<?php echo $doctor['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>보기 모드
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit_mode): ?>
<!-- ===== 수정 폼 ===== -->
<form method="POST" id="editDoctorForm" enctype="multipart/form-data">
    <input type="hidden" name="action" value="edit_doctor">
    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-badge me-1"></i> 기본 정보 수정</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">이름 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?php echo htmlspecialchars($doctor['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">소속 병원 <span class="text-danger">*</span></label>
                    <select name="hospital_id" class="form-select form-select-sm doctor-hospital-select" data-treatment-target="editTreatmentGroups" required>
                        <option value="">-- 선택 --</option>
                        <?php foreach ($hospital_list as $hl): ?>
                        <option value="<?php echo $hl['id']; ?>" <?php echo ($doctor['hospital_id'] ?? 0) == $hl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hl['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">자격 및 소속 학회</label>
                    <textarea name="memberships" class="form-control form-control-sm" rows="5"
                              maxlength="2000"
                              placeholder="피부과 전문의&#10;대한피부과학회 정회원"><?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?></textarea>
                    <div class="form-text">항목마다 줄을 바꿔 입력해주세요. 의사마다 자유롭게 다르게 입력할 수 있습니다.</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">프로필 사진</label>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">파일 업로드</label>
                        <input type="file" name="photo_file" class="form-control form-control-sm" accept="image/*">
                        <div class="form-text">JPG, PNG, WebP (최대 5MB). 파일 업로드 시 URL보다 우선 적용됩니다.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-heart-pulse me-1"></i> 시술 배정</div>
        <div class="card-body" id="editTreatmentGroups">
            <?php foreach ($hospital_list as $hl): ?>
            <div class="doctor-treatment-group" data-hospital-id="<?php echo (int)$hl['id']; ?>">
                <?php $edit_treatments = $hospital_treatments[(int)$hl['id']] ?? []; ?>
                <?php if (empty($edit_treatments)): ?>
                <div class="text-body-secondary small">이 병원에 등록된 활성 시술이 없습니다.</div>
                <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($edit_treatments as $tr): ?>
                    <div class="col-md-4">
                        <label class="form-check border rounded p-2 h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="treatment_ids[]"
                                   value="<?php echo (int)$tr['treatment_id']; ?>"
                                   <?php echo in_array((int)$tr['treatment_id'], $doctor_treatment_ids, true) ? 'checked' : ''; ?>>
                            <span class="small"><?php echo htmlspecialchars($tr['name']); ?></span>
                            <?php if (!empty($tr['category_name'])): ?>
                            <span class="d-block text-body-secondary" style="font-size:11px;"><?php echo htmlspecialchars($tr['category_name']); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>저장</button>
        <a href="?view=<?php echo $doctor['id']; ?>" class="btn btn-secondary btn-sm">취소</a>
    </div>
</form>

<?php else: ?>
<!-- ===== 상세 보기 ===== -->

<!-- 기본 정보 카드 -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-badge me-1"></i> 기본 정보</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="doctor-detail-label">이름</div>
                        <div class="doctor-detail-value fw-bold"><?php echo htmlspecialchars($doctor['name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">소속 병원</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['hospital_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="doctor-detail-label">자격 및 소속 학회</div>
                        <div class="doctor-detail-value"><?php echo nl2br(htmlspecialchars($doctor['qualification'] ?? '-')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-image me-1"></i> 프로필 사진</div>
            <div class="card-body text-center">
                <?php if (!empty($doctor['photo_url'])): ?>
                <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="프로필" class="doctor-photo mb-3">
                <?php else: ?>
                <div class="doctor-photo d-flex align-items-center justify-content-center bg-body-secondary mx-auto mb-3">
                    <i class="bi bi-person-fill" style="font-size:40px;color:var(--bs-secondary-color);"></i>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php
$doctor_treatment_names = [];
foreach (($hospital_treatments[(int)($doctor['hospital_id'] ?? 0)] ?? []) as $tr) {
    if (in_array((int)$tr['treatment_id'], $doctor_treatment_ids, true)) {
        $doctor_treatment_names[] = $tr;
    }
}
?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-heart-pulse me-1"></i> 배정된 시술</div>
    <div class="card-body">
        <?php if (empty($doctor_treatment_names)): ?>
        <span class="text-body-secondary small">배정된 시술이 없습니다.</span>
        <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($doctor_treatment_names as $tr): ?>
            <span class="badge bg-primary-subtle text-primary-emphasis border">
                <?php echo htmlspecialchars($tr['name']); ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 하단 관리 버튼 -->
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <a href="?view=<?php echo $doctor['id']; ?>&edit=1" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>수정
                </a>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('이 의사를 퇴직 처리하시겠습니까?\n기존 예약과 이력은 보존되고 시술 배정은 비활성화됩니다.')">
                <input type="hidden" name="action" value="delete_doctor">
                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-person-x me-1"></i>퇴직 처리
                </button>
            </form>
        </div>
    </div>
</div>

<?php endif; // end edit_mode vs view_mode ?>

<?php elseif ($view_mode > 0 && !$doctor): ?>
<!-- 의사를 찾을 수 없음 -->
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i> 해당 의사를 찾을 수 없습니다.
    <a href="admin_doctors.php" class="alert-link ms-2">목록으로 돌아가기</a>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- 목록 모드                                                     -->
<!-- ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-person-badge"></i> 의사 관리</h5>
    <?php if (!empty($hospital_list)): ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createDoctorModal">
        <i class="bi bi-plus-lg me-1"></i>의사 추가
    </button>
    <?php endif; ?>
</div>
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        <strong>안내:</strong> 의사는 병원 공개 프로필로만 등록됩니다. 이름, 소속 병원, 사진,
        해당 병원의 시술 분야와 자격·소속 학회만 관리합니다.
    </div>
</div>

<div class="modal fade" id="createDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
                <input type="hidden" name="action" value="create_doctor">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>의사 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">의사 이름 <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100" placeholder="홍길동">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">소속 병원 <span class="text-danger">*</span></label>
                            <select name="hospital_id" class="form-select doctor-hospital-select"
                                    data-treatment-target="createTreatmentGroups" required>
                                <option value="">-- 병원 선택 --</option>
                                <?php foreach ($hospital_list as $hl): ?>
                                <option value="<?php echo (int)$hl['id']; ?>"><?php echo htmlspecialchars($hl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">프로필 사진</label>
                            <input type="file" name="photo_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div class="form-text">JPG, PNG, WebP 또는 GIF 파일, 최대 5MB.</div>
                        </div>
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold"><i class="bi bi-award me-1"></i>자격 및 소속 학회</h6>
                        </div>
                        <div class="col-12">
                            <textarea name="memberships" class="form-control" rows="5" maxlength="2000"
                                      placeholder="피부과 전문의&#10;대한피부과학회 정회원"></textarea>
                            <div class="form-text">항목마다 줄을 바꿔 입력해주세요. 의사마다 자유롭게 다르게 입력할 수 있습니다.</div>
                        </div>
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold"><i class="bi bi-heart-pulse me-1"></i>전문 시술 분야</h6>
                            <label class="form-label fw-semibold">해당 병원에서 제공하는 시술 선택</label>
                            <div id="createTreatmentGroups" class="border rounded p-3">
                                <div class="doctor-treatment-placeholder text-body-secondary small">먼저 소속 병원을 선택해주세요.</div>
                                <?php foreach ($hospital_list as $hl): ?>
                                <div class="doctor-treatment-group" data-hospital-id="<?php echo (int)$hl['id']; ?>">
                                    <?php $create_treatments = $hospital_treatments[(int)$hl['id']] ?? []; ?>
                                    <?php if (empty($create_treatments)): ?>
                                    <div class="text-body-secondary small">이 병원에 등록된 활성 시술이 없습니다.</div>
                                    <?php else: ?>
                                    <div class="row g-2">
                                        <?php foreach ($create_treatments as $tr): ?>
                                        <div class="col-md-4">
                                            <label class="form-check border rounded p-2 h-100">
                                                <input class="form-check-input ms-0 me-2" type="checkbox"
                                                       name="treatment_ids[]" value="<?php echo (int)$tr['treatment_id']; ?>">
                                                <span class="small"><?php echo htmlspecialchars($tr['name']); ?></span>
                                                <?php if (!empty($tr['category_name'])): ?>
                                                <span class="d-block text-body-secondary" style="font-size:11px;"><?php echo htmlspecialchars($tr['category_name']); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">저장</button>
                </div>
        </form>
    </div>
</div>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-body-secondary">전체 의사</div>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['hospitals']); ?></div>
                <div class="small text-body-secondary">소속 병원 수</div>
            </div>
        </div>
    </div>
</div>

<!-- 필터 바: 병원 필터 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
            <span class="fw-semibold small text-body-secondary me-1">병원</span>
            <select name="hospital" class="form-select form-select-sm" style="width:220px;" onchange="this.form.submit()">
                <option value="0">전체 병원</option>
                <?php foreach ($hospital_list as $hl): ?>
                <option value="<?php echo $hl['id']; ?>" <?php echo $filter_hospital == $hl['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($hl['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filter_hospital > 0): ?>
            <a href="admin_doctors.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> 초기화</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <?php if ($filter_hospital > 0): ?>
        <input type="hidden" name="hospital" value="<?php echo $filter_hospital; ?>">
        <?php endif; ?>
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="이름, 병원, 시술, 자격·학회 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
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

<!-- 의사 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="doctorTable">
        <thead class="table-light">
            <tr>
                <th style="width:50px;">#</th>
                <th>이름</th>
                <th>소속 병원</th>
                <th>전문 시술 분야</th>
                <th>자격 및 소속 학회</th>
                <th style="width:90px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($doctors)): ?>
            <tr><td colspan="6" class="empty-state"><i class="bi bi-person-badge"></i>등록된 의사가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($doctors as $d): ?>
            <tr>
                <td class="text-center"><?php echo $d['id']; ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($d['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($d['photo_url']); ?>" alt="" class="doctor-photo-sm">
                        <?php else: ?>
                        <span class="doctor-photo-sm d-inline-flex align-items-center justify-content-center bg-body-secondary">
                            <i class="bi bi-person-fill" style="font-size:16px;color:var(--bs-secondary-color);"></i>
                        </span>
                        <?php endif; ?>
                        <a href="?view=<?php echo $d['id']; ?>" class="text-decoration-none fw-semibold">
                            <?php echo htmlspecialchars($d['name']); ?>
                        </a>
                    </div>
                </td>
                <td class="small">
                    <?php if (!empty($d['hospital_name'])): ?>
                    <span class="text-truncate d-inline-block" style="max-width:160px;" title="<?php echo htmlspecialchars($d['hospital_name']); ?>">
                        <?php echo htmlspecialchars($d['hospital_name']); ?>
                    </span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($d['treatment_names'])): ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($d['treatment_names'] as $treatment_name): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis border"><?php echo htmlspecialchars($treatment_name); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo nl2br(htmlspecialchars($d['qualification'] ?? '-')); ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?view=<?php echo $d['id']; ?>" class="btn btn-outline-info" title="상세보기">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="?view=<?php echo $d['id']; ?>&edit=1" class="btn btn-outline-primary" title="수정">
                            <i class="bi bi-pencil"></i>
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
        <li class="page-item">
            <a class="page-link" href="<?php echo buildDoctorUrl(['page' => $page - 1]); ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_pages, $page + 4);
        if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildDoctorUrl(['page' => 1]); ?>">1</a></li>
        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo buildDoctorUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildDoctorUrl(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a></li>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildDoctorUrl(['page' => $page + 1]); ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; // end list mode vs view mode ?>

<?php
$admin_extra_js = '
function updateDoctorTreatmentGroups(select) {
    var target = document.getElementById(select.dataset.treatmentTarget || "");
    if (!target) return;
    var selectedHospital = String(select.value || "");
    var placeholder = target.querySelector(".doctor-treatment-placeholder");
    if (placeholder) placeholder.style.display = selectedHospital ? "none" : "block";
    target.querySelectorAll(".doctor-treatment-group").forEach(function(group) {
        var active = selectedHospital !== "" && group.dataset.hospitalId === selectedHospital;
        group.style.display = active ? "block" : "none";
        group.querySelectorAll("input[type=checkbox]").forEach(function(input) {
            input.disabled = !active;
            if (!active && select.closest("#createDoctorModal")) input.checked = false;
        });
    });
}
document.querySelectorAll(".doctor-hospital-select").forEach(function(select) {
    updateDoctorTreatmentGroups(select);
    select.addEventListener("change", function() { updateDoctorTreatmentGroups(select); });
});
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

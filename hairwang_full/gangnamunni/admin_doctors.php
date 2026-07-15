<?php
/**
 * 의사 관리 페이지
 * 의사 목록, 상세 보기, 수정, 삭제
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

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 수정
    if ($action === 'edit_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $experience = intval($_POST['experience'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $appointment_charge = floatval($_POST['appointment_charge'] ?? 0);
        $photo_url = trim($_POST['photo_url'] ?? '');
        // 파일 업로드 처리 (URL보다 우선)
        if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_type = $_FILES['photo_file']['type'];
            $file_size = $_FILES['photo_file']['size'];
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $ext = pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION);
                $upload_dir = __DIR__ . '/uploads/doctors/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'doctor_' . $doctor_id . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $upload_path)) {
                    // 웹 접근 가능한 URL 생성
                    $photo_url = 'uploads/doctors/' . $filename;
                }
            }
        }
        $bio = trim($_POST['bio'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        // 진료 스케줄 (요일별 JSON)
        $schedule_arr = [];
        $day_keys_post = ['월','화','수','목','금','토','일'];
        foreach ($day_keys_post as $dk) {
            $dk_status = $_POST["sched_status_{$dk}"] ?? 'normal';
            if ($dk_status === 'off') {
                $schedule_arr[$dk] = '휴무';
            } else {
                $s_start = $_POST["sched_start_{$dk}"] ?? '';
                $s_end = $_POST["sched_end_{$dk}"] ?? '';
                $schedule_arr[$dk] = ($s_start && $s_end) ? "{$s_start}~{$s_end}" : '휴무';
            }
        }
        $schedule_json = json_encode($schedule_arr, JSON_UNESCAPED_UNICODE);

        if ($doctor_id > 0 && !empty($name)) {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_doctors SET name=?, specialty=?, hospital_id=?, department_id=?, experience=?, phone=?, email=?, qualification=?, appointment_charge=?, photo_url=?, bio=?, status=?, schedule=?, updated_at=NOW() WHERE id=?"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssiissssdssss' . 'i',
                    $name, $specialty, $hospital_id, $department_id, $experience,
                    $phone, $email, $qualification, $appointment_charge,
                    $photo_url, $bio, $status, $schedule_json, $doctor_id
                );
                if (mysqli_stmt_execute($stmt)) {
                    $message = "의사 '{$name}' 정보가 수정되었습니다.";
                } else {
                    $error = '수정 실패: ' . mysqli_error($connect);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = '쿼리 준비 실패: ' . mysqli_error($connect);
            }
        } else {
            $error = '의사 이름은 필수입니다.';
        }
    }

    // 삭제
    if ($action === 'delete_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        if ($doctor_id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_doctors WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $doctor_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "의사 #{$doctor_id}이(가) 삭제되었습니다.";
                // 삭제 후 목록으로 리다이렉트
                if (isset($_GET['view'])) {
                    header('Location: admin_doctors.php?msg=' . urlencode($message));
                    exit;
                }
            } else {
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
    $stmt = mysqli_prepare($connect,
        "SELECT d.*, h.name AS hospital_name, dep.title AS department_name
         FROM gangnamunni_doctors d
         LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
         LEFT JOIN gangnamunni_departments dep ON d.department_id = dep.id
         WHERE d.id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $view_mode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// ===== 병원 격리 필터 (super_admin=전체, 그 외=소속 병원) — 목록·통계·드롭다운 공통 =====
//   get_hospital_filter_in(): null=전체, "1,2,3"=해당 병원(정수 CSV), '0'=소속 병원 없음
$hosp_in = get_hospital_filter_in($connect);
$hosp_where_plain = ($hosp_in !== null) ? " WHERE hospital_id IN ({$hosp_in})" : ""; // 통계용(별칭 없음)
$hosp_and_plain   = ($hosp_in !== null) ? " AND hospital_id IN ({$hosp_in})" : "";

// ===== 병원 목록 (필터 드롭다운/수정 폼 select용) — 소속 병원만 =====
$hospital_list = [];
$h_where = ($hosp_in !== null) ? " WHERE id IN ({$hosp_in})" : "";
$h_result = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals{$h_where} ORDER BY name ASC");
if ($h_result) {
    while ($hr = mysqli_fetch_assoc($h_result)) {
        $hospital_list[] = $hr;
    }
}

// ===== 부서 목록 (수정 폼 select용) =====
$department_list = [];
$dep_result = mysqli_query($connect, "SELECT id, title AS name FROM gangnamunni_departments ORDER BY title ASC");
if ($dep_result) {
    while ($dr = mysqli_fetch_assoc($dep_result)) {
        $department_list[] = $dr;
    }
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
    $where .= ' AND (d.name LIKE ? OR d.specialty LIKE ? OR d.phone LIKE ? OR d.email LIKE ? OR d.qualification LIKE ? OR h.name LIKE ?)';
    $search_param = "%{$search}%";
    $where_params = array_merge($where_params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $where_types .= 'ssssss';
}

// ===== 병원 격리: 목록에도 소속 병원 의사만 (상단에서 계산한 $hosp_in 재사용) =====
if ($hosp_in !== null) {
    $where .= " AND d.hospital_id IN ({$hosp_in})";
}

// ===== 통계 =====
$stats = ['total' => 0];
$specialty_stats = [];

$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_doctors" . $hosp_where_plain);
if ($stat_result) $stats['total'] = (int)mysqli_fetch_assoc($stat_result)['c'];

// 전문분야별 분포
$spec_result = mysqli_query($connect,
    "SELECT COALESCE(NULLIF(TRIM(specialty),''), '미지정') AS spec, COUNT(*) AS cnt
     FROM gangnamunni_doctors" . $hosp_where_plain . " GROUP BY spec ORDER BY cnt DESC LIMIT 10"
);
if ($spec_result) {
    while ($sr = mysqli_fetch_assoc($spec_result)) {
        $specialty_stats[] = $sr;
    }
}

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
    $list_sql = "SELECT d.*, h.name AS hospital_name, dep.title AS department_name
                 FROM gangnamunni_doctors d
                 LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
                 LEFT JOIN gangnamunni_departments dep ON d.department_id = dep.id
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
        $doctors[] = $row;
    }
    mysqli_stmt_close($list_stmt);
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
        <?php if (!empty($doctor['specialty'])): ?>
        <span class="badge bg-primary specialty-badge ms-1"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
        <?php endif; ?>
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
                    <label class="form-label fw-semibold small">전문분야</label>
                    <input type="text" name="specialty" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($doctor['specialty'] ?? ''); ?>"
                           placeholder="성형외과, 피부과 등">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">소속 병원</label>
                    <select name="hospital_id" class="form-select form-select-sm">
                        <option value="0">-- 선택 --</option>
                        <?php foreach ($hospital_list as $hl): ?>
                        <option value="<?php echo $hl['id']; ?>" <?php echo ($doctor['hospital_id'] ?? 0) == $hl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hl['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">진료과</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="0">-- 선택 --</option>
                        <?php foreach ($department_list as $dl): ?>
                        <option value="<?php echo $dl['id']; ?>" <?php echo ($doctor['department_id'] ?? 0) == $dl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dl['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">경력 (년)</label>
                    <input type="number" name="experience" class="form-control form-control-sm" min="0"
                           value="<?php echo intval($doctor['experience'] ?? 0); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">전화번호</label>
                    <input type="text" name="phone" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">이메일</label>
                    <input type="email" name="email" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">자격</label>
                    <input type="text" name="qualification" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?>"
                           placeholder="전문의, 석사, 박사 등">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">진료비</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" name="appointment_charge" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($doctor['appointment_charge'] ?? '0'); ?>">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">프로필 사진</label>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">파일 업로드</label>
                        <input type="file" name="photo_file" class="form-control form-control-sm" accept="image/*">
                        <div class="form-text">JPG, PNG, WebP (최대 5MB). 파일 업로드 시 URL보다 우선 적용됩니다.</div>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-1">또는 URL 직접 입력</label>
                        <input type="url" name="photo_url" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($doctor['photo_url'] ?? ''); ?>"
                               placeholder="https://...">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">소개 / 약력</label>
                    <textarea name="bio" class="form-control form-control-sm" rows="4"><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea>
                </div>
                <!-- 상태 -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">상태</label>
                    <select name="status" class="form-select form-select-sm">
                        <?php
                        $status_opts = ['active'=>'진료 중','vacation'=>'휴가','leave'=>'휴직','resigned'=>'퇴직'];
                        $cur_status = $doctor['status'] ?? 'active';
                        foreach ($status_opts as $sv => $sl): ?>
                        <option value="<?php echo $sv; ?>" <?php echo $cur_status === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 진료 스케줄 편집 -->
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-clock me-1"></i> 진료 스케줄</div>
        <div class="card-body">
            <?php
            $sched = json_decode($doctor['schedule'] ?? '{}', true) ?: [];
            $sched_days = ['월'=>'월요일','화'=>'화요일','수'=>'수요일','목'=>'목요일','금'=>'금요일','토'=>'토요일','일'=>'일요일'];
            ?>
            <table class="table table-sm table-bordered mb-0" style="font-size:13px;">
                <thead>
                    <tr><th style="width:80px;">요일</th><th style="width:130px;">시작</th><th style="width:130px;">종료</th><th style="width:100px;">상태</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sched_days as $dk => $dl):
                        $day_val = $sched[$dk] ?? '';
                        $is_off = ($day_val === '휴무' || $day_val === '');
                        $parts = (!$is_off && strpos($day_val, '~') !== false) ? explode('~', $day_val) : ['', ''];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?php echo $dl; ?></td>
                        <td><input type="time" name="sched_start_<?php echo $dk; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($parts[0]); ?>" <?php echo $is_off ? 'disabled' : ''; ?>></td>
                        <td><input type="time" name="sched_end_<?php echo $dk; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($parts[1]); ?>" <?php echo $is_off ? 'disabled' : ''; ?>></td>
                        <td>
                            <select name="sched_status_<?php echo $dk; ?>" class="form-select form-select-sm" onchange="toggleSchedRow(this,'<?php echo $dk; ?>')">
                                <option value="normal" <?php echo !$is_off ? 'selected' : ''; ?>>정상</option>
                                <option value="off" <?php echo $is_off ? 'selected' : ''; ?>>휴무</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            function toggleSchedRow(sel, dk) {
                var tr = sel.closest('tr');
                var inputs = tr.querySelectorAll('input[type=time]');
                inputs.forEach(function(inp) {
                    inp.disabled = (sel.value === 'off');
                    if (sel.value === 'off') inp.value = '';
                });
            }
            </script>
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
                        <div class="doctor-detail-label">전문분야</div>
                        <div class="doctor-detail-value">
                            <?php if (!empty($doctor['specialty'])): ?>
                            <span class="badge bg-primary specialty-badge"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
                            <?php else: ?>
                            <span class="text-body-secondary">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">소속 병원</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['hospital_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">진료과</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['department_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">경력</div>
                        <div class="doctor-detail-value"><?php echo intval($doctor['experience'] ?? 0); ?>년</div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">자격</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['qualification'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">전화번호</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['phone'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">이메일</div>
                        <div class="doctor-detail-value"><?php echo htmlspecialchars($doctor['email'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="doctor-detail-label">진료비</div>
                        <div class="doctor-detail-value">
                            <?php if (($doctor['appointment_charge'] ?? 0) > 0): ?>
                            <?php echo number_format($doctor['appointment_charge'], 0); ?>원
                            <?php else: ?>
                            <span class="text-body-secondary">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="doctor-detail-label">소개 / 약력</div>
                        <div class="doctor-detail-value"><?php echo nl2br(htmlspecialchars($doctor['bio'] ?? '-')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> 프로필 / 메타</div>
            <div class="card-body text-center">
                <?php if (!empty($doctor['photo_url'])): ?>
                <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="프로필" class="doctor-photo mb-3">
                <?php else: ?>
                <div class="doctor-photo d-flex align-items-center justify-content-center bg-body-secondary mx-auto mb-3">
                    <i class="bi bi-person-fill" style="font-size:40px;color:var(--bs-secondary-color);"></i>
                </div>
                <?php endif; ?>

                <div class="text-start">
                    <div class="doctor-detail-label">HMS Doctor ID</div>
                    <div class="doctor-detail-value small"><?php echo htmlspecialchars($doctor['hms_doctor_id'] ?? '-'); ?></div>

                    <div class="doctor-detail-label">HMS Tenant ID</div>
                    <div class="doctor-detail-value small"><?php echo htmlspecialchars($doctor['hms_tenant_id'] ?? '-'); ?></div>

                    <div class="doctor-detail-label">동기화 시각</div>
                    <div class="doctor-detail-value small"><?php echo $doctor['synced_at'] ? date('Y-m-d H:i', strtotime($doctor['synced_at'])) : '-'; ?></div>

                    <div class="doctor-detail-label">등록일</div>
                    <div class="doctor-detail-value small"><?php echo $doctor['created_at'] ? date('Y-m-d H:i', strtotime($doctor['created_at'])) : '-'; ?></div>

                    <div class="doctor-detail-label">수정일</div>
                    <div class="doctor-detail-value small"><?php echo $doctor['updated_at'] ? date('Y-m-d H:i', strtotime($doctor['updated_at'])) : '-'; ?></div>
                </div>
            </div>
        </div>
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
            <form method="POST" class="d-inline" onsubmit="return confirm('정말 이 의사를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')">
                <input type="hidden" name="action" value="delete_doctor">
                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>삭제
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
</div>
<!-- [추가 2026-06-19] 이중 스키마 안내 -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        <strong>안내:</strong> 이 목록은 강남언니 <code>gangnamunni_doctors</code> 테이블 기준입니다.
        신규 등록은 <a href="admin_doctor_wizard.php">의사 등록 마법사</a>를 사용하세요(HMS <code>doctors</code>와 동시 저장).
        여기서의 수정/삭제는 강남언니 측에 적용되며, HMS 본 데이터에는 별도 반영되지 않을 수 있습니다.
    </div>
</div>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-body-secondary">전체 의사</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['hospitals']); ?></div>
                <div class="small text-body-secondary">소속 병원 수</div>
            </div>
        </div>
    </div>
    <?php
    // 상위 2개 전문분야 통계 카드
    $top_specs = array_slice($specialty_stats, 0, 2);
    $spec_colors = ['info', 'warning'];
    foreach ($top_specs as $idx => $sp): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-<?php echo $spec_colors[$idx]; ?>"><?php echo number_format($sp['cnt']); ?></div>
                <div class="small text-body-secondary"><?php echo htmlspecialchars($sp['spec']); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($top_specs) < 2): ?>
    <?php for ($fill = count($top_specs); $fill < 2; $fill++): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center doctor-stat-card">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary">-</div>
                <div class="small text-body-secondary">-</div>
            </div>
        </div>
    </div>
    <?php endfor; ?>
    <?php endif; ?>
</div>

<!-- 전문분야 분포 -->
<?php if (!empty($specialty_stats)): ?>
<div class="card mb-3">
    <div class="card-header py-2"><i class="bi bi-bar-chart me-1"></i> 전문분야 분포</div>
    <div class="card-body py-2">
        <div class="row g-2">
            <?php
            $max_cnt = max(array_column($specialty_stats, 'cnt'));
            foreach ($specialty_stats as $sp):
                $pct = $max_cnt > 0 ? round(($sp['cnt'] / $max_cnt) * 100) : 0;
            ?>
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2" style="font-size:13px;">
                    <span class="text-nowrap" style="min-width:80px;"><?php echo htmlspecialchars($sp['spec']); ?></span>
                    <div class="spec-bar flex-grow-1">
                        <div class="spec-bar-fill bg-primary" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                    <span class="badge bg-body-secondary text-body"><?php echo $sp['cnt']; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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
        <input type="search" name="search" class="form-control form-control-sm" placeholder="이름, 전문분야, 전화, 이메일, 자격, 병원명 검색..."
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
                <th>전문분야</th>
                <th>소속 병원</th>
                <th style="width:70px;">경력(년)</th>
                <th style="width:120px;">전화</th>
                <th style="width:160px;">이메일</th>
                <th style="width:100px;">진료비</th>
                <th style="width:90px;">등록일</th>
                <th style="width:90px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($doctors)): ?>
            <tr><td colspan="10" class="empty-state"><i class="bi bi-person-badge"></i>등록된 의사가 없습니다.</td></tr>
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
                <td>
                    <?php if (!empty($d['specialty'])): ?>
                    <span class="badge bg-primary specialty-badge"><?php echo htmlspecialchars($d['specialty']); ?></span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
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
                <td class="text-center">
                    <?php if (($d['experience'] ?? 0) > 0): ?>
                    <?php echo intval($d['experience']); ?>년
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($d['phone'] ?? '-'); ?></td>
                <td class="small">
                    <span class="text-truncate d-inline-block" style="max-width:150px;" title="<?php echo htmlspecialchars($d['email'] ?? ''); ?>">
                        <?php echo htmlspecialchars($d['email'] ?? '-'); ?>
                    </span>
                </td>
                <td class="text-end">
                    <?php if (($d['appointment_charge'] ?? 0) > 0): ?>
                    <?php echo number_format($d['appointment_charge'], 0); ?>원
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo $d['created_at'] ? date('Y.m.d', strtotime($d['created_at'])) : '-'; ?></td>
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
// 의사 관리 JS (확장용)
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

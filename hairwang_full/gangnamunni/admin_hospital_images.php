<?php
/**
 * 병원 이미지 관리 페이지
 * Bootstrap 5.3 + 주간/야간 모드
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


// 모든 로그인 관리자는 접근 가능하다. 데이터 범위는 아래에서 역할/소속 병원으로 제한한다.
require_admin_login();
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

// 이 페이지의 격리 규칙:
// - super_admin: 전체 병원
// - 그 외 모든 관리자: 이메일 또는 세션 hospital_id 로 연결된 자기 병원만
require_once __DIR__ . '/includes/hospital_filter.php';
$is_super_admin = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$my_ids = get_strict_admin_hospital_ids($connect);
$my_in  = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

// super_admin 이 아닌 관리자는 자기 병원 이미지와 병원명만 수정할 수 있다.
if ($my_ids !== null) {
    $act = $_POST['action'] ?? '';
    $first_hid = $my_ids[0] ?? 0;
    // 병원 추가/삭제는 전체 범위 작업이므로 super_admin 전용이다.
    if ($act === 'add_hospital' || isset($_GET['delete_hospital'])) {
        header('Location: admin_hospital_images.php?hospital_id=' . $first_hid . '&msg=' . urlencode('접근 권한이 없습니다.'));
        exit;
    }
    // 병원명 수정도 반드시 자기 병원이어야 한다.
    if ($act === 'update_hospital') {
        $target = (int)($_POST['hospital_edit_id'] ?? 0);
        if (!in_array($target, $my_ids, true)) {
            header('Location: admin_hospital_images.php?hospital_id=' . $first_hid . '&msg=' . urlencode('접근 권한이 없습니다.'));
            exit;
        }
    }
    // 이미지 추가 대상은 반드시 자기 병원이어야 한다.
    if ($act === 'add') {
        $target = (int)($_POST['hospital_id'] ?? 0);
        if (!in_array($target, $my_ids, true)) {
            header('Location: admin_hospital_images.php?hospital_id=' . $first_hid . '&msg=' . urlencode('접근 권한이 없습니다.'));
            exit;
        }
    }
    // 이미지 수정/삭제/일괄삭제: 대상 이미지가 자기 소유 병원 소속인지 검증
    $img_ids_to_check = [];
    if ($act === 'update' && isset($_POST['id'])) $img_ids_to_check[] = (int)$_POST['id'];
    if (isset($_GET['delete'])) $img_ids_to_check[] = (int)$_GET['delete'];
    if ($act === 'bulk_delete') $img_ids_to_check = array_map('intval', $_POST['ids'] ?? []);
    foreach ($img_ids_to_check as $iid) {
        if ($iid <= 0) continue;
        $chk = mysqli_query($connect, "SELECT hospital_id FROM hospital_images WHERE id = " . (int)$iid);
        $chk_row = $chk ? mysqli_fetch_assoc($chk) : null;
        if (!$chk_row || !in_array((int)$chk_row['hospital_id'], $my_ids, true)) {
            header('Location: admin_hospital_images.php?hospital_id=' . $first_hid . '&msg=' . urlencode('접근 권한이 없습니다.'));
            exit;
        }
    }
}

$message = $_GET['msg'] ?? '';
$error = '';

// 병원 목록 검색 및 페이지네이션
$hospital_search = isset($_GET['hospital_search']) ? trim($_GET['hospital_search']) : '';
$hospital_per_page = isset($_GET['hospital_per_page']) ? (int)$_GET['hospital_per_page'] : 20;
if (!in_array($hospital_per_page, [10, 20, 50, 100, 9999])) $hospital_per_page = 20;
$hospital_page = max(1, (int)($_GET['hospital_page'] ?? 1));

// 병원 총 개수 (병원 역할은 자기 병원만)
$hospital_count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE 1=1";
if (!empty($hospital_search)) {
    $hospital_count_sql .= " AND name LIKE '%" . mysqli_real_escape_string($connect, $hospital_search) . "%'";
}
if ($my_in !== null) { $hospital_count_sql .= " AND id IN (" . $my_in . ")"; }
$hospital_count_result = mysqli_query($connect, $hospital_count_sql);
$hospital_total = mysqli_fetch_assoc($hospital_count_result)['cnt'];
$hospital_total_pages = max(1, ceil($hospital_total / $hospital_per_page));
$hospital_offset = ($hospital_page - 1) * $hospital_per_page;

// 병원 목록 가져오기 (병원 역할은 자기 병원만)
$hospitals_sql = "SELECT id, name FROM gangnamunni_hospitals WHERE 1=1";
if (!empty($hospital_search)) {
    $hospitals_sql .= " AND name LIKE '%" . mysqli_real_escape_string($connect, $hospital_search) . "%'";
}
if ($my_in !== null) { $hospitals_sql .= " AND id IN (" . $my_in . ")"; }
$hospitals_sql .= " ORDER BY name ASC LIMIT $hospital_offset, $hospital_per_page";


$hospitals_result = mysqli_query($connect, $hospitals_sql);
$hospitals = [];
while ($row = mysqli_fetch_assoc($hospitals_result)) $hospitals[] = $row;


$selected_hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
// 병원 역할은 자기 소유 병원만 — 선택값이 자기 소유가 아니면 첫 병원으로 (다병원이면 URL 로 자기 병원 간 전환 가능)
if ($my_ids !== null && !in_array($selected_hospital_id, $my_ids, true)) {
    $selected_hospital_id = $my_ids[0] ?? 0;
}

// 검색어
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지당 행수
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// 병원 이미지 파일 업로드 처리 함수 (#3)
// [관리자 #3 - 2026-05-28] 업로드 실패 시 "새로고침만 발생" 픽스 — 에러 메시지 명확화
function handleHospitalImageUpload($file_key = 'image_file') {
    if (!isset($_FILES[$file_key])) {
        return null; // 파일 필드 자체가 없음 (이전 동작 유지)
    }
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        // 에러 코드별 명확한 메시지 (이전엔 null 반환 → 디버깅 불가)
        $upload_errors = [
            UPLOAD_ERR_NO_FILE => null, // 파일 미선택 - null 반환하여 image_url로 대체
            UPLOAD_ERR_INI_SIZE => '파일 크기가 서버 설정(upload_max_filesize) 한도를 초과합니다. PHP 설정을 확인하세요.',
            UPLOAD_ERR_FORM_SIZE => '파일 크기가 폼 한도(MAX_FILE_SIZE)를 초과합니다.',
            UPLOAD_ERR_PARTIAL => '파일이 부분만 업로드되었습니다. 다시 시도해주세요.',
            UPLOAD_ERR_NO_TMP_DIR => '서버 임시 디렉토리가 없습니다.',
            UPLOAD_ERR_CANT_WRITE => '서버 디스크 쓰기 실패.',
            UPLOAD_ERR_EXTENSION => 'PHP 확장이 업로드를 차단했습니다.',
        ];
        $code = $_FILES[$file_key]['error'];
        if ($code === UPLOAD_ERR_NO_FILE) return null;
        return ['error' => $upload_errors[$code] ?? "업로드 에러 코드: {$code}"];
    }
    $file = $_FILES[$file_key];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
    if (!in_array($file['type'], $allowed)) {
        return ['error' => "이미지 파일만 업로드 가능합니다 (JPG, PNG, GIF, WebP) — 받은 MIME: {$file['type']}"];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['error' => '파일 크기는 10MB 이하여야 합니다'];
    }
    $upload_dir = __DIR__ . '/uploads/hospitals/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fname = 'hospital_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        return ['url' => 'uploads/hospitals/' . $fname];
    }
    return ['error' => '파일 저장에 실패했습니다'];
}

// 이미지 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $hospital_id = (int)$_POST['hospital_id'];
    $upload_result = handleHospitalImageUpload('image_file');
    if ($upload_result && isset($upload_result['error'])) {
        $error = $upload_result['error'];
    }
    $image_url = ($upload_result && isset($upload_result['url'])) ? $upload_result['url'] : trim($_POST['image_url']);
    $image_type = trim($_POST['image_type']);
    $sort_order = (int)$_POST['sort_order'];

    if (!empty($hospital_id) && !empty($image_url)) {
        $sql = sprintf("INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active) VALUES (%d, '%s', '%s', %d, 1)",
            $hospital_id, mysqli_real_escape_string($connect, $image_url), mysqli_real_escape_string($connect, $image_type), $sort_order);
        if (mysqli_query($connect, $sql)) {
            header('Location: admin_hospital_images.php?hospital_id=' . $hospital_id . '&msg=' . urlencode('이미지가 추가되었습니다.'));
            exit;
        } else {
            $error = "추가 실패: " . mysqli_error($connect);
        }
    } else {
        $error = "필수 필드를 입력해주세요.";
    }
}

// 이미지 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)$_POST['id'];
    $hospital_id = (int)$_POST['hospital_id'];
    $upload_result = handleHospitalImageUpload('edit_image_file');
    if ($upload_result && isset($upload_result['error'])) {
        $error = $upload_result['error'];
    }
    $image_url = ($upload_result && isset($upload_result['url'])) ? $upload_result['url'] : trim($_POST['image_url']);
    $image_type = trim($_POST['image_type']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $scope_sql = $my_in === null ? '' : " AND hospital_id IN ({$my_in})";
    $sql = sprintf("UPDATE hospital_images SET image_url='%s', image_type='%s', sort_order=%d, is_active=%d WHERE id=%d%s",
        mysqli_real_escape_string($connect, $image_url), mysqli_real_escape_string($connect, $image_type), $sort_order, $is_active, $id, $scope_sql);
    if (mysqli_query($connect, $sql)) {
        header('Location: admin_hospital_images.php?hospital_id=' . $hospital_id . '&msg=' . urlencode('이미지가 수정되었습니다.'));
        exit;
    } else {
        $error = "수정 실패: " . mysqli_error($connect);
    }
}

// 이미지 삭제
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hospital_id = (int)($_GET['hospital_id'] ?? 0);
    $scope_sql = $my_in === null ? '' : " AND hospital_id IN ({$my_in})";
    if (mysqli_query($connect, "DELETE FROM hospital_images WHERE id = {$id}{$scope_sql}")) {
        header('Location: admin_hospital_images.php?hospital_id=' . $hospital_id . '&msg=' . urlencode('이미지가 삭제되었습니다.'));
        exit;
    } else {
        $error = "삭제 실패: " . mysqli_error($connect);
    }
}

// 병원 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_hospital') {
    $hospital_name = trim($_POST['hospital_name']);
    if (!empty($hospital_name)) {
        $sql = sprintf("INSERT INTO gangnamunni_hospitals (name) VALUES ('%s')",
            mysqli_real_escape_string($connect, $hospital_name));
        if (mysqli_query($connect, $sql)) {
            $message = "병원이 추가되었습니다.";
        } else {
            $error = "추가 실패: " . mysqli_error($connect);
        }
    } else {
        $error = "병원명을 입력해주세요.";
    }
}

// 병원 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_hospital') {
    $hospital_id = (int)$_POST['hospital_edit_id'];
    $hospital_name = trim($_POST['hospital_name']);
    if ($hospital_id > 0 && !empty($hospital_name)) {
        $scope_sql = $my_in === null ? '' : " AND id IN ({$my_in})";
        $sql = sprintf("UPDATE gangnamunni_hospitals SET name='%s' WHERE id=%d%s",
            mysqli_real_escape_string($connect, $hospital_name), $hospital_id, $scope_sql);
        if (mysqli_query($connect, $sql)) {
            $message = "병원이 수정되었습니다.";
        } else {
            $error = "수정 실패: " . mysqli_error($connect);
        }
    } else {
        $error = "올바른 정보를 입력해주세요.";
    }
}

// 병원 삭제
if (isset($_GET['delete_hospital'])) {
    $hospital_id = (int)$_GET['delete_hospital'];
    // 먼저 해당 병원의 이미지가 있는지 확인
    $check_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_images WHERE hospital_id = {$hospital_id}");
    $check_row = mysqli_fetch_assoc($check_result);
    if ($check_row['cnt'] > 0) {
        $error = "해당 병원에 등록된 이미지가 있어 삭제할 수 없습니다. 먼저 이미지를 삭제해주세요.";
    } else {
        if (mysqli_query($connect, "DELETE FROM gangnamunni_hospitals WHERE id = {$hospital_id}")) {
            $message = "병원이 삭제되었습니다.";
        } else {
            $error = "삭제 실패: " . mysqli_error($connect);
        }
    }
}

// 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        $scope_sql = $my_in === null ? '' : " AND hospital_id IN ({$my_in})";
        if (mysqli_query($connect, "DELETE FROM hospital_images WHERE id IN ({$id_list}){$scope_sql}")) {
            $message = count($ids) . "개 이미지가 삭제되었습니다.";
        }
    }
}

// 모든 병원의 이미지 목록 조회 (병원별로 그룹화)
$hospitals_with_images = [];
$error_hospitals = [];

foreach ($hospitals as $h) {
    try {
        $h_id = $h['id'];
        $img_sql = "SELECT * FROM hospital_images WHERE hospital_id = {$h_id} ORDER BY sort_order ASC, id ASC";
        $img_result = mysqli_query($connect, $img_sql);

        if (!$img_result) {
            $error_hospitals[] = [
                'id' => $h_id,
                'name' => $h['name'],
                'error' => mysqli_error($connect)
            ];
            error_log("Hospital {$h_id} image query failed: " . mysqli_error($connect));
            // 에러가 있어도 계속 진행
            $h['images'] = [];
            $h['image_count'] = 0;
            $hospitals_with_images[] = $h;
            continue;
        }

        $h['images'] = [];
        while ($img = mysqli_fetch_assoc($img_result)) {
            $h['images'][] = $img;
        }
        $h['image_count'] = count($h['images']);
        $hospitals_with_images[] = $h;
    } catch (Exception $e) {
        $error_hospitals[] = [
            'id' => $h['id'],
            'name' => $h['name'],
            'error' => $e->getMessage()
        ];
        error_log("Hospital {$h['id']} processing exception: " . $e->getMessage());
        // 에러가 있어도 빈 데이터로 추가
        $h['images'] = [];
        $h['image_count'] = 0;
        $hospitals_with_images[] = $h;
    }
}

// 에러가 있으면 로그
if (!empty($error_hospitals)) {
    error_log("Total error hospitals: " . count($error_hospitals));
}

// 기존 단일 병원 이미지 조회 (이미지 추가 버튼용)
$images = [];
$total = 0;
if ($selected_hospital_id > 0) {
    $where = "hospital_id = {$selected_hospital_id}";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($connect, $search);
        $where .= " AND (image_url LIKE '%{$s}%' OR image_type LIKE '%{$s}%')";
    }
    $total = (int)mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM hospital_images WHERE {$where}"))['cnt'];
    $result = mysqli_query($connect, "SELECT * FROM hospital_images WHERE {$where} ORDER BY sort_order ASC, id ASC LIMIT {$offset}, {$per_page}");
    while ($row = mysqli_fetch_assoc($result)) $images[] = $row;
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 전체 라벨(기존 이미지 배지 표시용) — interior/facility/etc/logo 이미지도 올바른 한글 라벨 유지
$image_type_labels = ['main' => '메인', 'logo' => '로고', 'exterior' => '외관', 'interior' => '내부', 'facility' => '시설', 'etc' => '기타'];
// 신규 등록/수정 드롭다운 옵션 — 메인/외관만 (나머지 타입은 프론트 갤러리에서 동일 취급이라 단순화)
$image_types = ['main' => '메인', 'exterior' => '외관'];

// 공통 헤더 include
$admin_page_title = '병원 이미지 관리';
$admin_current = 'hospital_images';
$admin_extra_css = '
    .accordion-header { position: relative; }
    .accordion-header .btn-group { position: absolute; right: 40px; top: 50%; transform: translateY(-50%); z-index: 10; }
    .accordion-button { padding-right: 150px; }
    .accordion-item { border-left: 3px solid transparent; transition: border-color 0.2s; }
    .accordion-item:hover { border-left-color: var(--bs-primary); }
    .accordion-button:not(.collapsed) { background: var(--bs-primary-bg-subtle); font-weight: 600; }
';
include __DIR__ . '/includes/admin_header.php';
?>

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

<?php if (!empty($error_hospitals)): ?>
<div class="alert alert-warning alert-dismissible fade show py-2" role="alert">
    <strong>에러 발생한 병원 (<?php echo count($error_hospitals); ?>개):</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($error_hospitals as $err): ?>
        <li>ID <?php echo $err['id']; ?>: <?php echo htmlspecialchars($err['name']); ?> - <?php echo htmlspecialchars($err['error']); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 병원 목록 & 이미지 -->
<div class="card mb-3">
    <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-hospital"></i> 병원 이미지 관리</h6>
                <span class="badge bg-primary rounded-pill"><?php echo $hospital_total; ?>개 병원</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" class="d-flex align-items-center gap-1">
                    <input type="text" name="hospital_search" class="form-control form-control-sm" placeholder="병원명 검색..." value="<?php echo htmlspecialchars($hospital_search); ?>" style="width:160px;">
                    <select name="hospital_per_page" class="form-select form-select-sm" onchange="this.form.submit()" style="width:75px;">
                        <option value="10" <?php echo $hospital_per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $hospital_per_page == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $hospital_per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $hospital_per_page == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="9999" <?php echo $hospital_per_page == 9999 ? 'selected' : ''; ?>>All</option>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search"></i></button>
                </form>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="expandAll()" title="전체 확장"><i class="bi bi-arrows-angle-expand"></i></button>
                    <button class="btn btn-outline-secondary" onclick="collapseAll()" title="전체 축소"><i class="bi bi-arrows-angle-contract"></i></button>
                </div>
                <?php if ($is_super_admin): ?>
                <button class="btn btn-primary btn-sm" onclick="showAddHospitalForm()"><i class="bi bi-plus-circle"></i> 병원 추가</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Accordion: 병원별 이미지 리스트 -->
    <div class="accordion accordion-flush" id="hospitalAccordion">
        <?php if (empty($hospitals_with_images)): ?>
        <div class="accordion-item">
            <div class="accordion-header">
                <div class="accordion-button collapsed" style="pointer-events:none;">
                    <i class="bi bi-info-circle me-2"></i> 등록된 병원이 없습니다.
                </div>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($hospitals_with_images as $idx => $h): ?>
            <div class="accordion-item" data-hospital-id="<?php echo $h['id']; ?>">
                <h2 class="accordion-header" id="heading-<?php echo $h['id']; ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $h['id']; ?>" aria-expanded="false">
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <i class="bi bi-building"></i>
                            <strong><?php echo htmlspecialchars($h['name']); ?></strong>
                            <span class="badge bg-secondary"><?php echo $h['image_count']; ?>개 이미지</span>
                        </div>
                    </button>
                    <div class="btn-group btn-group-sm">
                        <a href="?hospital_id=<?php echo $h['id']; ?>&hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>" class="btn btn-sm btn-outline-success" title="이미지 추가">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary" data-hospital='<?php echo json_encode(['id'=>$h['id'],'name'=>$h['name']]); ?>' onclick="editHospital(JSON.parse(this.dataset.hospital))" title="편집">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($is_super_admin): ?>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger" onclick="deleteHospital(<?php echo $h['id']; ?>)" title="삭제">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </h2>
                <div id="collapse-<?php echo $h['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $h['id']; ?>" data-bs-parent="#hospitalAccordion">
                    <div class="accordion-body p-2">
                        <?php if (empty($h['images'])): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-image"></i> 등록된 이미지가 없습니다.
                            <a href="?hospital_id=<?php echo $h['id']; ?>&hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>" class="btn btn-sm btn-primary ms-2">
                                <i class="bi bi-plus-lg"></i> 이미지 추가
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="40"><input type="checkbox" class="form-check-input" onclick="toggleAllImages(this, <?php echo $h['id']; ?>)"></th>
                                        <th width="100">썸네일</th>
                                        <th>URL</th>
                                        <th width="80">유형</th>
                                        <th width="60">순서</th>
                                        <th width="60">상태</th>
                                        <th width="100">작업</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($h['images'] as $img):
                                        $type_colors = ['main'=>'primary','logo'=>'dark','exterior'=>'success','interior'=>'info','facility'=>'warning','etc'=>'secondary'];
                                        $tc = $type_colors[$img['image_type']] ?? 'secondary';
                                    ?>
                                    <tr class="<?php echo !$img['is_active'] ? 'opacity-50' : ''; ?>">
                                        <td><input type="checkbox" class="form-check-input row-check hospital-<?php echo $h['id']; ?>-check" value="<?php echo $img['id']; ?>"></td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt=""
                                                 class="rounded" style="width:80px;height:55px;object-fit:cover;cursor:pointer;"
                                                 onclick="window.open(this.src,'_blank')"
                                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2755%27%3E%3Crect fill=%27%23dee2e6%27 width=%2780%27 height=%2755%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 fill=%27%23999%27 text-anchor=%27middle%27 dy=%27.3em%27%3Eerr%3C/text%3E%3C/svg%3E'">
                                        </td>
                                        <td class="text-truncate" style="max-width:300px;" title="<?php echo htmlspecialchars($img['image_url']); ?>">
                                            <?php echo htmlspecialchars($img['image_url']); ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $tc; ?>"><?php echo $image_type_labels[$img['image_type']] ?? $img['image_type']; ?></span></td>
                                        <td><?php echo $img['sort_order']; ?></td>
                                        <td>
                                            <?php if ($img['is_active']): ?>
                                            <span class="badge bg-success">활성</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">비활성</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-sm btn-outline-primary" data-image='<?php echo json_encode($img); ?>' onclick="editImage(JSON.parse(this.dataset.image))" title="수정">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="?hospital_id=<?php echo $h['id']; ?>&delete=<?php echo $img['id']; ?>&hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>"
                                                   class="btn btn-sm btn-outline-danger" onclick="return confirm('삭제하시겠습니까?')" title="삭제">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($hospital_total_pages > 1 && $hospital_per_page != 9999): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($hospital_page > 1): ?>
                <li class="page-item"><a class="page-link" href="?hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>&hospital_page=<?php echo $hospital_page-1; ?>">&laquo;</a></li>
                <?php endif; ?>
                <?php
                $start = max(1, $hospital_page - 2);
                $end = min($hospital_total_pages, $hospital_page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?php echo $i == $hospital_page ? 'active' : ''; ?>">
                    <a class="page-link" href="?hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>&hospital_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($hospital_page < $hospital_total_pages): ?>
                <li class="page-item"><a class="page-link" href="?hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>&hospital_page=<?php echo $hospital_page+1; ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- 병원 편집/추가 폼 (리스트 하단) -->
<div class="card mb-3" id="hospitalFormCard" style="display:none;">
    <div class="card-header py-2 bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-pencil-square"></i> <span id="hospitalFormTitle">병원 편집</span></h6>
            <button class="btn btn-sm btn-light" onclick="hideHospitalForm()"><i class="bi bi-x"></i></button>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" id="hospitalForm">
            <input type="hidden" name="action" id="hospital_action" value="update_hospital">
            <input type="hidden" name="hospital_edit_id" id="hospital_edit_id" value="">

            <div class="mb-3">
                <label class="form-label fw-semibold">병원명 <span class="text-danger">*</span></label>
                <input type="text" name="hospital_name" id="hospital_name" class="form-control" required>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> 저장</button>
                <button type="button" class="btn btn-secondary" onclick="hideHospitalForm()">취소</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_hospital_id > 0): ?>
<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="hospital_id" value="<?php echo $selected_hospital_id; ?>">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="URL, 타입으로 검색..."
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
    <form method="POST" class="d-inline" onsubmit="return confirm('선택한 항목을 삭제하시겠습니까?')">
        <input type="hidden" name="action" value="bulk_delete">
        <span id="bulkIdsContainer"></span>
        <button class="btn btn-danger btn-sm ms-2"><i class="bi bi-trash"></i> 선택 삭제</button>
    </form>
</div>

<!-- 이미지 추가 버튼 -->
<div class="d-flex justify-content-end mb-2">
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>이미지 추가
    </button>
</div>

<!-- 이미지 리스트 -->
<?php if (empty($images)): ?>
<div class="card">
    <div class="card-body empty-state">
        <i class="bi bi-image"></i>등록된 이미지가 없습니다.
    </div>
</div>
<?php else: ?>
<div class="list-group list-group-flush" id="imageTable">
    <?php foreach ($images as $img):
        $type_colors = ['main'=>'primary','logo'=>'dark','exterior'=>'success','interior'=>'info','facility'=>'warning','etc'=>'secondary'];
        $tc = $type_colors[$img['image_type']] ?? 'secondary';
    ?>
    <div class="list-group-item <?php echo !$img['is_active'] ? 'opacity-50' : ''; ?> px-3 py-2">
        <div class="d-flex align-items-center justify-content-between">
            <!-- 왼쪽: 체크박스 -->
            <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width:0;">
                <input type="checkbox" class="form-check-input flex-shrink-0 row-check" value="<?php echo $img['id']; ?>" onchange="updateBulkActions()" style="margin:0;">

                <!-- 썸네일 -->
                <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt=""
                     class="flex-shrink-0 rounded"
                     style="width:80px;height:55px;object-fit:cover;border:1px solid var(--bs-border-color);cursor:pointer;"
                     onclick="window.open(this.src,'_blank')"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2755%27%3E%3Crect fill=%27%23dee2e6%27 width=%2780%27 height=%2755%27 rx=%274%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 fill=%27%23999%27 text-anchor=%27middle%27 dy=%27.3em%27 font-size=%2712%27%3Eerr%3C/text%3E%3C/svg%3E'">

                <!-- 정보 -->
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-<?php echo $tc; ?> small"><?php echo $image_type_labels[$img['image_type']] ?? $img['image_type']; ?></span>
                        <?php if ($img['is_active']): ?>
                        <span class="badge bg-success small">활성</span>
                        <?php else: ?>
                        <span class="badge bg-danger small">비활성</span>
                        <?php endif; ?>
                        <span class="text-body-secondary" style="font-size:11px;">순서: <?php echo $img['sort_order']; ?></span>
                        <span class="text-body-secondary" style="font-size:11px;">ID: <?php echo $img['id']; ?></span>
                    </div>
                    <div class="text-body-secondary text-truncate" style="font-size:12px;" title="<?php echo htmlspecialchars($img['image_url']); ?>">
                        <?php echo htmlspecialchars($img['image_url']); ?>
                    </div>
                </div>
            </div>

            <!-- 오른쪽: 편집 버튼 -->
            <div class="btn-group btn-group-sm flex-shrink-0 ms-2">
                <button class="btn btn-sm btn-outline-primary" onclick='editImage(<?php echo json_encode($img); ?>)' title="수정">
                    <i class="bi bi-pencil"></i> 수정
                </button>
                <a href="?hospital_id=<?php echo $selected_hospital_id; ?>&delete=<?php echo $img['id']; ?>"
                   class="btn btn-sm btn-outline-danger" onclick="return confirm('삭제하시겠습니까?')" title="삭제">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?hospital_id=<?php echo $selected_hospital_id; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a></li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_pages, $page + 4);
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?hospital_id=<?php echo $selected_hospital_id; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item"><a class="page-link" href="?hospital_id=<?php echo $selected_hospital_id; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<!-- 추가 모달 -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="hospital_id" value="<?php echo $selected_hospital_id; ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle"></i> 이미지 추가</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">이미지 파일 업로드</label>
                        <input type="file" name="image_file" class="form-control form-control-sm" accept="image/*" onchange="previewImg(this,'addPreview')">
                        <img id="addPreview" src="" class="mt-1 rounded border" style="max-width:200px;max-height:120px;display:none;">
                        <div class="form-text text-muted"><i class="bi bi-info-circle"></i> JPG, PNG, GIF, WebP (최대 10MB)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">또는 이미지 URL 직접 입력</label>
                        <input type="url" name="image_url" class="form-control form-control-sm" placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">이미지 타입</label>
                        <select name="image_type" class="form-select form-select-sm" required>
                            <?php foreach ($image_types as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">표시 순서</label>
                        <input type="number" name="sort_order" class="form-control form-control-sm" value="<?php echo count($images) + 1; ?>">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-sm btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 수정 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="hospital_id" value="<?php echo $selected_hospital_id; ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil-square"></i> 이미지 수정</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">새 이미지 파일 (선택)</label>
                        <input type="file" name="edit_image_file" class="form-control form-control-sm" accept="image/*" onchange="previewImg(this,'editPreview')">
                        <img id="editPreview" src="" class="mt-1 rounded border" style="max-width:200px;max-height:120px;display:none;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">이미지 URL</label>
                        <input type="url" name="image_url" id="edit_image_url" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">이미지 타입</label>
                        <select name="image_type" id="edit_image_type" class="form-select form-select-sm" required>
                            <?php foreach ($image_types as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">표시 순서</label>
                        <input type="number" name="sort_order" id="edit_sort_order" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="form-check-input">
                        <label class="form-check-label" for="edit_is_active">활성화</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-sm btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'EOD'
// Accordion 전체 확장
function expandAll() {
    try {
        var collapses = document.querySelectorAll("#hospitalAccordion .accordion-collapse");
        collapses.forEach(function(collapse) {
            var bsCollapse = new bootstrap.Collapse(collapse, {toggle: false});
            bsCollapse.show();
        });
    } catch (e) {
        console.error('expandAll 에러:', e);
        alert('전체 확장 중 에러: ' + e.message);
    }
}

// Accordion 전체 축소
function collapseAll() {
    try {
        var collapses = document.querySelectorAll("#hospitalAccordion .accordion-collapse");
        collapses.forEach(function(collapse) {
            var bsCollapse = bootstrap.Collapse.getInstance(collapse);
            if (bsCollapse) {
                bsCollapse.hide();
            } else {
                var newCollapse = new bootstrap.Collapse(collapse, {toggle: false});
                newCollapse.hide();
            }
        });
    } catch (e) {
        console.error('collapseAll 에러:', e);
        alert('전체 축소 중 에러: ' + e.message);
    }
}

// 병원별 이미지 전체 선택
function toggleAllImages(checkbox, hospitalId) {
    try {
        var checkboxes = document.querySelectorAll(".hospital-" + hospitalId + "-check");
        checkboxes.forEach(function(cb) {
            cb.checked = checkbox.checked;
        });
    } catch (e) {
        console.error('toggleAllImages 에러:', e);
        alert('이미지 선택 중 에러: ' + e.message);
    }
}

function editImage(img) {
    try {
        document.getElementById("edit_id").value = img.id;
        document.getElementById("edit_image_url").value = img.image_url;
        document.getElementById("edit_image_type").value = img.image_type;
        document.getElementById("edit_sort_order").value = img.sort_order;
        document.getElementById("edit_is_active").checked = img.is_active == 1;
        new bootstrap.Modal(document.getElementById("editModal")).show();
    } catch (e) {
        console.error('editImage 에러:', e);
        alert('이미지 편집 중 에러: ' + e.message);
    }
}

// 병원 추가 폼 표시
function showAddHospitalForm() {
    try {
        document.getElementById("hospitalFormTitle").textContent = "병원 추가";
        document.getElementById("hospital_action").value = "add_hospital";
        document.getElementById("hospital_edit_id").value = "";
        document.getElementById("hospital_name").value = "";
        document.getElementById("hospitalFormCard").style.display = "block";
        document.getElementById("hospital_name").focus();
    } catch (e) {
        console.error('showAddHospitalForm 에러:', e);
        alert('병원 추가 폼 표시 중 에러: ' + e.message);
    }
}

// 병원 편집 폼 표시
function editHospital(hospital) {
    try {
        document.getElementById("hospitalFormTitle").textContent = "병원 편집";
        document.getElementById("hospital_action").value = "update_hospital";
        document.getElementById("hospital_edit_id").value = hospital.id;
        document.getElementById("hospital_name").value = hospital.name;
        document.getElementById("hospitalFormCard").style.display = "block";
        document.getElementById("hospital_name").focus();
    } catch (e) {
        console.error('editHospital 에러:', e);
        alert('병원 편집 폼 표시 중 에러: ' + e.message);
    }
}

// 병원 폼 숨기기
function hideHospitalForm() {
    try {
        document.getElementById("hospitalFormCard").style.display = "none";
    } catch (e) {
        console.error('hideHospitalForm 에러:', e);
        alert('폼 숨기기 중 에러: ' + e.message);
    }
}

// 병원 삭제
function deleteHospital(hospitalId) {
    try {
        if (confirm("정말 이 병원을 삭제하시겠습니까?\\n\\n※ 등록된 이미지가 있는 경우 삭제할 수 없습니다.")) {
            window.location.href = "?delete_hospital=" + hospitalId + "&hospital_search=<?php echo urlencode($hospital_search); ?>&hospital_per_page=<?php echo $hospital_per_page; ?>&hospital_page=<?php echo $hospital_page; ?>";
        }
    } catch (e) {
        console.error('deleteHospital 에러:', e);
        alert('병원 삭제 중 에러: ' + e.message);
    }
}

// 일괄 삭제 폼에 선택된 ID 추가
var bulkForm = document.querySelector("#bulkActions form");
if (bulkForm) {
    bulkForm.addEventListener("submit", function() {
        var container = document.getElementById("bulkIdsContainer");
        container.innerHTML = "";
        document.querySelectorAll(".row-check:checked").forEach(function(cb) {
            var inp = document.createElement("input");
            inp.type = "hidden"; inp.name = "ids[]"; inp.value = cb.value;
            container.appendChild(inp);
        });
    });
}

// 이미지 미리보기
function previewImg(input, previewId) {
    var preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
EOD;

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

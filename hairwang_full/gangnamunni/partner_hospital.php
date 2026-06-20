<?php
/**
 * 병원 파트너 정보 수정 페이지
 * hospital_admin 역할 전용 — 자기 병원 정보만 수정 가능
 * Bootstrap 5.3 + 관리자 레이아웃
 * @created 2026-03-22
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '병원 정보 수정';
$admin_current = 'partner_hospital';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('partner_hospital');

$current_admin = get_current_admin();
$message = '';
$error = '';

// 병원 ID 결정: hospital_admin은 자기 병원만, super_admin/admin은 GET 파라미터도 가능
$hospital_id = 0;
if (in_array($current_admin['role'], ['super_admin', 'admin'])) {
    $hospital_id = intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? $current_admin['hospital_id'] ?? 0);
} else {
    $hospital_id = intval($current_admin['hospital_id'] ?? 0);
}

if ($hospital_id <= 0) {
    // hospital_id가 없으면 gangnamunni_admins에서 조회 시도
    $stmt = mysqli_prepare($connect, "SELECT hospital_id FROM gangnamunni_admins WHERE id = ?");
    if ($stmt) {
        $admin_id = $current_admin['id'];
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($r);
        if ($row && $row['hospital_id'] > 0) {
            $hospital_id = intval($row['hospital_id']);
            $_SESSION['ad_admin_hospital_id'] = $hospital_id;
        }
        mysqli_stmt_close($stmt);
    }
}

if ($hospital_id <= 0) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>오류</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa;}';
    echo 'h2{color:#333;}p{color:#666;}a{color:#667eea;}</style></head><body>';
    echo '<h2>소속 병원이 설정되지 않았습니다</h2>';
    echo '<p>관리자에게 문의하여 병원 연결을 요청해주세요.</p>';
    echo '<a href="dashboard.php">대시보드로 돌아가기</a>';
    echo '</body></html>';
    exit;
}

// POST 처리: 병원 정보 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_hospital') {
    $post_hospital_id = intval($_POST['hospital_id'] ?? 0);

    // 권한 검증: 자기 병원만 수정 가능
    if ($post_hospital_id !== $hospital_id && !in_array($current_admin['role'], ['super_admin', 'admin'])) {
        $error = '권한이 없습니다.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $introduction = trim($_POST['introduction'] ?? '');
        $business_hours = trim($_POST['business_hours'] ?? '');
        $categories = trim($_POST['categories'] ?? '');
        $treatment_tags = trim($_POST['treatment_tags'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name)) {
            $error = '병원 이름은 필수입니다.';
        } else {
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET name=?, phone=?, address=?, description=?, introduction=?, business_hours=?, categories=?, treatment_tags=?, email=?, updated_at=NOW() WHERE id=?"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssssssssi',
                    $name, $phone, $address, $description, $introduction,
                    $business_hours, $categories, $treatment_tags, $email, $hospital_id
                );
                if (mysqli_stmt_execute($stmt)) {
                    $message = '병원 정보가 저장되었습니다.';
                    if (function_exists('admin_log_error')) {
                        admin_log_error('PARTNER', 'Hospital info updated', ['hospital_id' => $hospital_id, 'admin' => $current_admin['username']]);
                    }
                } else {
                    $error = '저장 실패: ' . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'SQL 오류: ' . mysqli_error($connect);
            }
        }
    }
}

// 병원 정보 조회
$hospital = null;
$stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $hospital = mysqli_fetch_assoc($r);
    mysqli_stmt_close($stmt);
}

if (!$hospital) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>오류</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;background:#f5f7fa;}';
    echo 'h2{color:#333;}p{color:#666;}a{color:#667eea;}</style></head><body>';
    echo '<h2>병원을 찾을 수 없습니다</h2>';
    echo '<p>병원 ID: ' . $hospital_id . '</p>';
    echo '<a href="dashboard.php">대시보드로 돌아가기</a>';
    echo '</body></html>';
    exit;
}

// admin_header include
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- 페이지 제목 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-hospital"></i> 병원 정보 수정</h4>
                    <small class="text-muted">병원 ID: <?php echo $hospital_id; ?> | 상태: <?php echo htmlspecialchars($hospital['status'] ?? 'pending'); ?></small>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 대시보드</a>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- 수정 폼 -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pencil-square"></i> 기본 정보</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_hospital">
                        <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">

                        <div class="row g-3">
                            <!-- 병원명 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">병원명 <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($hospital['name'] ?? ''); ?>"
                                       maxlength="255">
                            </div>

                            <!-- 연락처 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">연락처</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>"
                                       placeholder="02-1234-5678">
                            </div>

                            <!-- 이메일 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">이메일</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['email'] ?? ''); ?>"
                                       placeholder="info@hospital.com">
                            </div>

                            <!-- 주소 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">주소</label>
                                <input type="text" name="address" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['address'] ?? ''); ?>"
                                       placeholder="서울시 강남구...">
                            </div>

                            <!-- 영업시간 -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">영업시간</label>
                                <textarea name="business_hours" class="form-control" rows="3"
                                          placeholder="월-금 10:00~19:00&#10;토 10:00~15:00&#10;일/공휴일 휴무"><?php echo htmlspecialchars($hospital['business_hours'] ?? ''); ?></textarea>
                                <small class="text-muted">줄바꿈으로 요일별 시간을 구분하세요.</small>
                            </div>

                            <!-- 카테고리 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">카테고리</label>
                                <input type="text" name="categories" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['categories'] ?? ''); ?>"
                                       placeholder="성형외과,피부과,치과">
                                <small class="text-muted">쉼표로 구분</small>
                            </div>

                            <!-- 시술 태그 -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">시술 태그</label>
                                <input type="text" name="treatment_tags" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['treatment_tags'] ?? ''); ?>"
                                       placeholder="눈성형,코성형,리프팅,보톡스">
                                <small class="text-muted">쉼표로 구분</small>
                            </div>

                            <!-- 한줄 소개 -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">한줄 소개</label>
                                <input type="text" name="description" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['description'] ?? ''); ?>"
                                       placeholder="병원을 한줄로 소개해주세요" maxlength="500">
                            </div>

                            <!-- 상세 소개 -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">상세 소개</label>
                                <textarea name="introduction" class="form-control" rows="6"
                                          placeholder="병원의 특장점, 전문 분야, 의료진 소개 등을 자세히 작성해주세요."><?php echo htmlspecialchars($hospital['introduction'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- 현재 상태 표시 (읽기전용) -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted">승인 상태</label>
                                <div>
                                    <?php
                                    $status = $hospital['status'] ?? 'pending';
                                    $badge_class = match($status) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default => 'bg-warning text-dark',
                                    };
                                    $status_label = match($status) {
                                        'approved' => '승인됨',
                                        'rejected' => '반려됨',
                                        'pending' => '대기중',
                                        default => $status,
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status_label; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted">활성 상태</label>
                                <div>
                                    <?php if ($hospital['is_active']): ?>
                                    <span class="badge bg-success">활성</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">비활성</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted">최종 수정</label>
                                <div class="text-muted small"><?php echo $hospital['updated_at'] ?? '-'; ?></div>
                            </div>
                        </div>

                        <?php if (!empty($hospital['reject_reason'])): ?>
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle"></i> 반려 사유:</strong>
                            <?php echo nl2br(htmlspecialchars($hospital['reject_reason'])); ?>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> 저장</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">취소</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 이미지 관리 안내 -->
            <div class="card mt-3">
                <div class="card-body text-center py-4">
                    <i class="bi bi-images text-muted" style="font-size:2rem;"></i>
                    <p class="text-muted mt-2 mb-2">병원 이미지 관리는 별도 페이지에서 가능합니다.</p>
                    <a href="admin_hospital_images.php?hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-images"></i> 이미지 관리
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

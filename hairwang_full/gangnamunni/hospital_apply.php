<?php
/**
 * 병원 등록 신청 폼
 * 그누보드 로그인 필수, 파일 업로드 + DB INSERT
 * Bootstrap 5.3 + 관리자 레이아웃
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '병원 등록 신청';
$admin_current = 'hospital_apply';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/hospital_treatment_images.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 필수
require_admin_login();

$message = '';
$error = '';
$success = false;
$current_admin = get_current_admin();

// show_hospital_apply 설정은 마이페이지 노출 여부만 제어
// 관리자 페이지에서의 신청은 항상 가능

// ===== POST 처리: 신청 제출 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 필수 필드 검증
    $name = trim($_POST['hospital_name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $business_number = trim($_POST['business_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $categories_raw = trim($_POST['categories'] ?? '');
    $categories = !empty($categories_raw) ? json_encode(explode(',', $categories_raw), JSON_UNESCAPED_UNICODE) : '[]';
    $treatment_tags_raw = trim($_POST['treatment_tags'] ?? '');
    $treatment_tags = !empty($treatment_tags_raw) ? json_encode(explode(',', $treatment_tags_raw), JSON_UNESCAPED_UNICODE) : '[]';
    $business_hours_text = trim($_POST['business_hours'] ?? '');
    // gangnamunni_hospitals.business_hours 컬럼은 JSON 형식만 허용하는 DB 제약(CHECK)이 있음
    $business_hours = !empty($business_hours_text) ? json_encode(['note' => $business_hours_text], JSON_UNESCAPED_UNICODE) : '{}';
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $sns_url = trim($_POST['sns_url'] ?? '');

    // 약관 동의
    $agree_terms = isset($_POST['agree_terms']);
    $agree_privacy = isset($_POST['agree_privacy']);

    // 유효성 검사
    if (empty($name) || empty($owner_name) || empty($business_number) || empty($phone) || empty($email) || empty($contact_name) || empty($contact_phone) || empty($address)) {
        $error = '필수 항목을 모두 입력해 주세요.';
    } elseif (!$agree_terms || !$agree_privacy) {
        $error = '필수 약관에 동의해 주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '올바른 이메일 주소를 입력해 주세요.';
    } else {
        // 파일 업로드 처리
        $applied_by = $current_admin['username'] ?? ($current_admin['name'] ?? 'unknown');
        $upload_dir = __DIR__ . '/uploads/hospital_apply/' . $applied_by . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $cert_business_path = '';
        $cert_medical_path = '';
        $profile_image_path = '';
        $logo_image_path = '';

        // 사업자등록증 (필수)
        if (!empty($_FILES['cert_business']['name']) && $_FILES['cert_business']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cert_business']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf']) && $_FILES['cert_business']['size'] <= 5 * 1024 * 1024) {
                $fname = 'cert_business_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cert_business']['tmp_name'], $upload_dir . $fname)) {
                    $cert_business_path = 'uploads/hospital_apply/' . $applied_by . '/' . $fname;
                }
            } else {
                $error = '사업자등록증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
            }
        } else {
            $error = '사업자등록증은 필수 서류입니다.';
        }

        // 의료기관 개설허가증 (필수)
        if (empty($error) && !empty($_FILES['cert_medical']['name']) && $_FILES['cert_medical']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cert_medical']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf']) && $_FILES['cert_medical']['size'] <= 5 * 1024 * 1024) {
                $fname = 'cert_medical_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cert_medical']['tmp_name'], $upload_dir . $fname)) {
                    $cert_medical_path = 'uploads/hospital_apply/' . $applied_by . '/' . $fname;
                }
            } else {
                $error = '의료기관 허가증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
            }
        } elseif (empty($error)) {
            $error = '의료기관 개설허가증은 필수 서류입니다.';
        }

        // 대표 이미지 (선택)
        if (empty($error) && !empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && $_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                $fname = 'profile_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $fname)) {
                    $profile_image_path = 'uploads/hospital_apply/' . $applied_by . '/' . $fname;
                }
            }
        }

        // DB INSERT
        if (empty($error)) {
            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_hospitals (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, categories, treatment_tags, business_hours, description, website, sns_url, profile_image_url, cert_business, cert_medical, applied_by, applied_at, status, is_active, ad_grade, is_demo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0, 'none', ?, NOW())"
            );
            if ($stmt) {
                $is_demo_val = isDevMode($connect) ? 1 : 0;
                mysqli_stmt_bind_param($stmt, 'sssssssssssssssss' . 'si',
                    $name, $owner_name, $business_number, $phone, $email,
                    $contact_name, $contact_phone, $address, $categories,
                    $treatment_tags, $business_hours, $description,
                    $website, $sns_url, $profile_image_path,
                    $cert_business_path, $cert_medical_path, $applied_by,
                    $is_demo_val
                );
                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($connect);
                    $image_warnings = hti_save_application_treatments(
                        $connect, $new_id, $_POST['application_treatment_ids'] ?? [], 'hospital'
                    );
                    $success = true;
                    $message = "병원 등록 신청이 접수되었습니다. (신청번호: #{$new_id})";
                    if ($image_warnings) $message .= ' 단, 일부 시술 이미지 업로드를 확인해 주세요: ' . implode(' / ', $image_warnings);

                    // 관리자에게 이메일 알림
                    if (file_exists(__DIR__ . '/includes/mail_helper.php')) {
                        require_once __DIR__ . '/includes/mail_helper.php';
                        $admin_body = "<h2>새 병원 등록 신청</h2>
                            <p><strong>병원명:</strong> " . htmlspecialchars($name) . "</p>
                            <p><strong>대표자:</strong> " . htmlspecialchars($owner_name) . "</p>
                            <p><strong>사업자번호:</strong> " . htmlspecialchars($business_number) . "</p>
                            <p><strong>이메일:</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>신청자:</strong> " . htmlspecialchars($applied_by) . "</p>
                            <p><a href='" . SITE_FULL_URL . "admin_hospitals.php?tab=pending'>신청 목록 확인</a></p>";
                        @mail_send(0, 'admin@seoulbeauty.app', '[서울미녀] 새 병원 등록 신청 - ' . $name, $admin_body);
                    }

                    // 신청자에게 확인 이메일
                    if (!empty($email) && file_exists(__DIR__ . '/includes/mail_helper.php')) {
                        $user_body = "<h2>병원 등록 신청이 접수되었습니다</h2>
                            <p>안녕하세요, <strong>" . htmlspecialchars($contact_name) . "</strong>님.</p>
                            <p><strong>" . htmlspecialchars($name) . "</strong>의 입점 신청이 정상적으로 접수되었습니다.</p>
                            <p>심사 결과는 1~3 영업일 내에 이메일로 안내드리겠습니다.</p>
                            <p>감사합니다.<br>" . term('site_name', '서울미녀') . " 팀</p>";
                        @mail_send(0, $email, '[서울미녀] 병원 등록 신청 접수 확인', $user_body);
                    }

                    // 관리자 FCM + 인앱 알림
                    notifyAdmins($connect, 'hospital_apply',
                        '새 병원 등록 신청',
                        htmlspecialchars($name) . ' (신청번호 #' . $new_id . ')',
                        'admin_hospitals.php?tab=pending',
                        ['hospital_id' => $new_id, 'hospital_name' => $name]
                    );
                } else {
                    $error = '신청 저장 실패: ' . mysqli_error($connect);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'SQL 준비 실패: ' . mysqli_error($connect);
            }
        }
    }
}

// 기존 신청 확인
$my_applications = [];
$applied_by_check = $current_admin['username'] ?? ($current_admin['name'] ?? '');
if (!empty($applied_by_check)) {
    $stmt_my = mysqli_prepare($connect, "SELECT id, name, status, applied_at, rejection_reason FROM gangnamunni_hospitals WHERE applied_by=? ORDER BY applied_at DESC LIMIT 10");
    if ($stmt_my) {
        mysqli_stmt_bind_param($stmt_my, 's', $applied_by_check);
        mysqli_stmt_execute($stmt_my);
        $res_my = mysqli_stmt_get_result($stmt_my);
        while ($row = mysqli_fetch_assoc($res_my)) {
            $my_applications[] = $row;
        }
        mysqli_stmt_close($stmt_my);
    }
}

// 진료과목 체크박스 목록 — gangnamunni_treatment_master에서 동적 로드 (전체 시술, category_id 포함)
// 주요 시술(카테고리)에서 선택한 항목에 따라 관련 진료과목만 표시하기 위해 category_id를 함께 가져옴
$category_list = [];
$cat_r = mysqli_query($connect, "SELECT id, name, category_id FROM gangnamunni_treatment_master WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) {
    while ($cat_row = mysqli_fetch_assoc($cat_r)) {
        $category_list[] = ['id' => (int)$cat_row['id'], 'name' => $cat_row['name'], 'category_id' => (int)$cat_row['category_id']];
    }
}
if (empty($category_list)) {
    $fallback_cats = defined('CATEGORIES') ? CATEGORIES : ['코','눈','지방흡입','가슴성형','치아','리프팅','주름/미백','쁘띠','모발이식','제모','남성','눈성형'];
    foreach ($fallback_cats as $fc) {
        $category_list[] = ['id' => 0, 'name' => $fc, 'category_id' => 0];
    }
}

// 주요 시술 체크박스 목록 — gangnamunni_treatment_categories에서 동적 로드 (id 포함)
$treatment_tag_list = [];
$tt_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($tt_r) {
    while ($tt_row = mysqli_fetch_assoc($tt_r)) {
        $treatment_tag_list[] = ['id' => (int)$tt_row['id'], 'name' => $tt_row['name']];
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">

<?php if ($success): ?>
    <!-- 신청 완료 -->
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="bi bi-check-circle text-success" style="font-size: 5rem;"></i>
        </div>
        <h2 class="fw-bold mb-3">신청이 접수되었습니다!</h2>
        <p class="text-muted fs-5"><?= htmlspecialchars($message) ?></p>
        <p class="text-muted">심사 결과는 1~3 영업일 내에 이메일로 안내드립니다.</p>
        <div class="mt-4">
            <a href="hospital_apply_status.php" class="btn btn-primary me-2"><i class="bi bi-clock-history me-1"></i>내 신청 현황</a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-house me-1"></i>대시보드</a>
        </div>
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($my_applications)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            이미 <?= count($my_applications) ?>건의 신청 내역이 있습니다.
            <a href="hospital_apply_status.php" class="alert-link">내 신청 현황 보기</a>
        </div>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('hospital_apply'); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>병원 등록 신청</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="applyForm">

                <!-- 기본 정보 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-building me-2"></i>기본 정보</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원명 <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" required maxlength="255" value="<?= htmlspecialchars($_POST['hospital_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">대표자명 <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록번호 <span class="text-danger">*</span></label>
                        <input type="text" name="business_number" class="form-control" required maxlength="20" placeholder="000-00-00000" value="<?= htmlspecialchars($_POST['business_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 전화번호 <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" required maxlength="50" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 이메일 <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="191" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자명 <span class="text-danger">*</span></label>
                        <input type="text" name="contact_name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자 전화 <span class="text-danger">*</span></label>
                        <input type="tel" name="contact_phone" class="form-control" required maxlength="20" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- 병원 정보 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>병원 정보</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 주소 <span class="text-danger">*</span></label>
                        <input type="text" name="address" class="form-control" required maxlength="500" placeholder="도로명 주소를 입력하세요" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">주요 시술 <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $sel_tags = isset($_POST['treatment_tags']) ? explode(',', $_POST['treatment_tags']) : [];
                            foreach ($treatment_tag_list as $tag): ?>
                            <div class="form-check">
                                <input class="form-check-input tag-check" type="checkbox" value="<?= htmlspecialchars($tag['name']) ?>" data-cat-id="<?= $tag['id'] ?>" id="tag_<?= md5($tag['name']) ?>" <?= in_array($tag['name'], $sel_tags) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tag_<?= md5($tag['name']) ?>"><?= htmlspecialchars($tag['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="treatment_tags" id="treatmentTagsInput">
                        <div class="form-text">주요 시술을 선택하면 아래 진료과목 목록이 갱신됩니다.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">진료과목 <span class="text-danger">*</span></label>
                        <div id="treatmentPlaceholder" class="text-body-secondary small mb-2">먼저 위에서 주요 시술을 선택하세요.</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $sel_cats = isset($_POST['categories']) ? explode(',', $_POST['categories']) : [];
                            foreach ($category_list as $cat): ?>
                            <div class="form-check" style="display:none;">
                                <input class="form-check-input cat-check" type="checkbox" value="<?= htmlspecialchars($cat['name']) ?>" data-treatment-id="<?= $cat['id'] ?>" data-cat-id="<?= $cat['category_id'] ?>" id="cat_<?= md5($cat['name']) ?>" <?= in_array($cat['name'], $sel_cats) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cat_<?= md5($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="categories" id="categoriesInput">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">시술별 상세페이지 이미지 <span class="text-muted fw-normal">(선택)</span></label>
                        <div class="form-text mb-2">선택한 진료과목마다 상세페이지에 표시할 이미지를 여러 장 등록할 수 있습니다.</div>
                        <div id="treatmentImageFields"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">영업시간</label>
                        <input type="text" name="business_hours" class="form-control" placeholder="예: 평일 10:00~19:00, 토 10:00~15:00" value="<?= htmlspecialchars($_POST['business_hours'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 소개</label>
                        <textarea name="description" class="form-control" rows="4" maxlength="2000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">홈페이지 URL</label>
                        <input type="url" name="website" class="form-control" placeholder="https://" value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SNS (인스타그램 등)</label>
                        <input type="text" name="sns_url" class="form-control" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($_POST['sns_url'] ?? '') ?>">
                    </div>
                </div>

                <!-- 서류 업로드 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-cloud-upload me-2"></i>서류 업로드</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록증 <span class="text-danger">*</span></label>
                        <input type="file" name="cert_business" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">의료기관 개설허가증 <span class="text-danger">*</span></label>
                        <input type="file" name="cert_medical" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 대표 이미지</label>
                        <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png">
                        <div class="form-text">JPG/PNG, 5MB 이하 (선택)</div>
                    </div>
                </div>

                <!-- 약관 동의 -->
                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>약관 동의</h6>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                        <label class="form-check-label" for="agreeTerms">
                            <span class="badge bg-danger me-1">필수</span> 서울미녀 병원 입점 약관에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_privacy" id="agreePrivacy" required>
                        <label class="form-check-label" for="agreePrivacy">
                            <span class="badge bg-danger me-1">필수</span> 개인정보 수집/이용에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="agree_marketing" id="agreeMarketing">
                        <label class="form-check-label" for="agreeMarketing">
                            <span class="badge bg-secondary me-1">선택</span> 마케팅 수신에 동의합니다.
                        </label>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-send me-2"></i>신청서 제출
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

</div>

<script>
// 주요 시술에서 선택한 카테고리에 따라 관련 진료과목만 표시
function updateTreatmentVisibility() {
    var selectedCatIds = [];
    document.querySelectorAll('.tag-check:checked').forEach(function(cb) {
        selectedCatIds.push(cb.getAttribute('data-cat-id'));
    });
    var anySelected = selectedCatIds.length > 0;

    document.querySelectorAll('.cat-check').forEach(function(cb) {
        var wrap = cb.closest('.form-check');
        var show = anySelected && selectedCatIds.indexOf(cb.getAttribute('data-cat-id')) !== -1;
        wrap.style.display = show ? '' : 'none';
        if (!show) cb.checked = false;
    });

    var placeholder = document.getElementById('treatmentPlaceholder');
    if (placeholder) placeholder.style.display = anySelected ? 'none' : '';
    updateTreatmentImageFields();
}
function updateTreatmentImageFields() {
    var box = document.getElementById('treatmentImageFields');
    if (!box) return;
    var selected = Array.from(document.querySelectorAll('.cat-check:checked')).filter(function(cb) {
        return parseInt(cb.getAttribute('data-treatment-id') || '0', 10) > 0;
    });
    var existing = {};
    box.querySelectorAll('[data-image-treatment-id]').forEach(function(el) {
        existing[el.getAttribute('data-image-treatment-id')] = el;
    });
    selected.forEach(function(cb) {
        var id = cb.getAttribute('data-treatment-id');
        if (existing[id]) { delete existing[id]; return; }
        var row = document.createElement('div');
        row.className = 'border rounded p-3 mb-2';
        row.setAttribute('data-image-treatment-id', id);
        row.innerHTML = '<label class="form-label fw-semibold small">' + cb.value.replace(/[&<>"']/g, '') + '</label>'
            + '<input type="hidden" name="application_treatment_ids[]" value="' + id + '">'
            + '<input type="file" name="treatment_images[' + id + '][]" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp" multiple>'
            + '<div class="form-text">JPG/PNG/GIF/WebP, 파일당 10MB 이하</div>';
        box.appendChild(row);
    });
    Object.keys(existing).forEach(function(id) { existing[id].remove(); });
}
document.querySelectorAll('.tag-check').forEach(function(cb) {
    cb.addEventListener('change', updateTreatmentVisibility);
});
updateTreatmentVisibility();
document.querySelectorAll('.cat-check').forEach(function(cb) {
    cb.addEventListener('change', updateTreatmentImageFields);
});

// 카테고리 / 주요 시술 체크박스 → hidden input 동기화
document.getElementById('applyForm')?.addEventListener('submit', function() {
    var checked = [];
    document.querySelectorAll('.cat-check:checked').forEach(function(cb) {
        checked.push(cb.value);
    });
    document.getElementById('categoriesInput').value = checked.join(',');

    var checkedTags = [];
    document.querySelectorAll('.tag-check:checked').forEach(function(cb) {
        checkedTags.push(cb.value);
    });
    document.getElementById('treatmentTagsInput').value = checkedTags.join(',');
});

// 사업자등록번호 자동 포맷
document.querySelector('[name="business_number"]')?.addEventListener('input', function(e) {
    var v = e.target.value.replace(/[^0-9]/g, '');
    if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
    else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
    e.target.value = v;
});
</script>

<?php if (isDevMode($connect)): ?>
    <?= getDevModeJS_hospital_apply(); ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>

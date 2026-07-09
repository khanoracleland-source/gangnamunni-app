<?php
/**
 * admin_hospital_wizard.php - 병원 등록 마법사
 * 7단계 원형 스텝 프로세서로 병원 최초 등록 처리
 *
 * @since 2026-02-14
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'check_email') {
        $email = mysqli_real_escape_string($connect, trim($_POST['email'] ?? ''));
        $r = mysqli_query($connect, "SELECT id FROM users WHERE email = '{$email}' LIMIT 1");
        echo json_encode(['exists' => mysqli_num_rows($r) > 0]);
        exit;
    }

    if ($action === 'check_slug') {
        $slug = mysqli_real_escape_string($connect, trim($_POST['slug'] ?? ''));
        $r = mysqli_query($connect, "SELECT id FROM users WHERE username = '{$slug}' LIMIT 1");
        echo json_encode(['exists' => mysqli_num_rows($r) > 0]);
        exit;
    }

    if ($action === 'upload_image') {
        $uploadDir = __DIR__ . '/uploads/hospitals/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => '파일 업로드 실패']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'error' => '이미지 파일만 가능']);
            exit;
        }
        $fname = 'hospital_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            echo json_encode(['success' => true, 'url' => 'uploads/hospitals/' . $fname, 'filename' => $fname]);
        } else {
            echo json_encode(['success' => false, 'error' => '저장 실패']);
        }
        exit;
    }

    if ($action === 'submit') {
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => '데이터 없음']);
            exit;
        }
        mysqli_begin_transaction($connect);
        try {
            $esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

            // 1) gangnamunni_hospitals 등록
            $name = $esc($data['hospital_name'] ?? '');
            $desc = $esc($data['description'] ?? '');
            $intro = $esc($data['introduction'] ?? '');
            $addr = $esc($data['address'] ?? '');
            $phone = $esc($data['phone'] ?? '');
            $email = $esc($data['email'] ?? '');
            $mainImg = $esc($data['main_image'] ?? '');
            $logoImg = $esc($data['logo_image'] ?? '');
            $cats = $esc(json_encode($data['categories'] ?? [], JSON_UNESCAPED_UNICODE));
            $tags = $esc(json_encode($data['treatment_tags'] ?? [], JSON_UNESCAPED_UNICODE));
            $hours = $esc(json_encode($data['business_hours'] ?? [], JSON_UNESCAPED_UNICODE));
            $subway = $esc(json_encode($data['subway'] ?? [], JSON_UNESCAPED_UNICODE));
            $lat = floatval($data['latitude'] ?? 37.5665);
            $lng = floatval($data['longitude'] ?? 126.978);

            // ============================================================
            // [관리자 #8 - 2026-05-28] 병원 등록 승인 시 HTTP 500 픽스
            // 원인 : mysqli_query 실패해도 예외 미발생 → try-catch 우회 → 후속 코드
            //        에서 mysqli_insert_id=0 + 외래키 위반 등으로 fatal error 발생.
            // 변경 : mysqli_query 결과를 명시 체크 + 실패 시 Exception throw로
            //        트랜잭션 rollback 및 명확한 에러 메시지 전달.
            // 영향 범위 : submit action만. 트랜잭션 BEGIN/COMMIT 흐름 그대로 보존.
            // ============================================================
            $sql = "INSERT INTO gangnamunni_hospitals (name, description, introduction, address, phone, email,
                    main_image_url, profile_image_url, categories, treatment_tags, business_hours,
                    subway_names, latitude, longitude, created_at, updated_at)
                    VALUES ('{$name}', '{$desc}', '{$intro}', '{$addr}', '{$phone}', '{$email}',
                    '{$mainImg}', '{$logoImg}', '{$cats}', '{$tags}', '{$hours}',
                    '{$subway}', {$lat}, {$lng}, NOW(), NOW())";
            $ins_ok = mysqli_query($connect, $sql);
            if (!$ins_ok) {
                $err_msg = mysqli_error($connect);
                error_log("[admin_hospital_wizard #8] 병원 INSERT 실패: {$err_msg}");
                throw new Exception("병원 등록 실패: {$err_msg}");
            }
            $hospitalId = mysqli_insert_id($connect);
            if (!$hospitalId || $hospitalId <= 0) {
                error_log("[admin_hospital_wizard #8] insert_id 0 — INSERT 비정상");
                throw new Exception("병원 ID 생성 실패");
            }

            // 2) 의사 등록
            $doctors = $data['doctors'] ?? [];
            foreach ($doctors as $doc) {
                $dname = $esc($doc['name'] ?? '');
                $dspec = $esc($doc['specialty'] ?? '');
                $dbio = $esc($doc['biography'] ?? '');
                $dphoto = $esc($doc['photo'] ?? '');
                if ($dname) {
                    $nextDocId = 0;
                    $rr = mysqli_query($connect, "SELECT COALESCE(MAX(hms_doctor_id),0)+1 AS next_id FROM gangnamunni_doctors");
                    if ($rr && $row2 = mysqli_fetch_assoc($rr)) $nextDocId = intval($row2['next_id']);
                    mysqli_query($connect, "INSERT INTO gangnamunni_doctors (hms_doctor_id, hospital_id, name, specialty, bio, photo_url, created_at)
                        VALUES ({$nextDocId}, {$hospitalId}, '{$dname}', '{$dspec}', '{$dbio}', '{$dphoto}', NOW())");
                }
            }

            // 3) 로고 이미지 저장 (hospital_images 테이블에 image_type='logo' 로 별도 저장)
            //    - 목록(hospitals.php)에서 로고로 사용하고, 상세(detail.php) 갤러리에는 노출하지 않는다.
            if ($logoImg !== '') {
                $logo_ok = mysqli_query($connect, "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at)
                    VALUES ({$hospitalId}, '{$logoImg}', 'logo', 0, 1, NOW())");
                if (!$logo_ok) {
                    throw new Exception("로고 이미지 저장 실패: " . mysqli_error($connect));
                }
            }

            // 4) 갤러리(내부 사진) 이미지 저장 — detail.php 갤러리에 노출
            $gallery = $data['gallery_images'] ?? [];
            foreach ($gallery as $i => $gimg) {
                $gurl = $esc($gimg);
                $gallery_ok = mysqli_query($connect, "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order, is_active, created_at)
                    VALUES ({$hospitalId}, '{$gurl}', 'interior', " . ($i + 1) . ", 1, NOW())");
                if (!$gallery_ok) {
                    throw new Exception("내부 사진 저장 실패: " . mysqli_error($connect));
                }
            }

            mysqli_commit($connect);
            echo json_encode(['success' => true, 'hospital_id' => $hospitalId, 'message' => '병원이 등록되었습니다. 관리자 승인 후 활성화됩니다.<br><br><a href="https://hms.seoulbeauty.app/super-admin/hospitals" target="_blank" class="btn btn-sm btn-outline-primary">HMS 병원 관리</a>']);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── 카테고리 로드 ──
$categories = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_categories WHERE is_active = 1 ORDER BY id");
while ($row = mysqli_fetch_assoc($r)) $categories[] = $row;

// ── 구독 플랜 로드 ──
$plans = [];
$r = mysqli_query($connect, "SELECT * FROM subscription_plans ORDER BY id");
while ($row = mysqli_fetch_assoc($r)) $plans[] = $row;

$admin_page_title = '병원 등록 마법사';
$admin_current = 'hospital_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 원형 스텝 프로세서 ── */
.wizard-header {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 20px 0 30px;
    gap: 0;
    overflow-x: auto;
}
.wizard-step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 70px;
}
.wizard-step-row {
    display: flex;
    align-items: center;
}
.wizard-circle {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 3px solid transparent;
    position: relative;
    flex-shrink: 0;
}
.wizard-circle.pending { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border-color: var(--bs-border-color); }
.wizard-circle.active { background: #667eea; color: #fff; border-color: #667eea; box-shadow: 0 0 0 5px rgba(102,126,234,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #28a745; color: #fff; border-color: #28a745; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line {
    width: 40px; height: 3px;
    background: var(--bs-border-color);
    transition: background 0.3s;
    flex-shrink: 0;
}
.wizard-line.completed { background: #28a745; }
.wizard-step-label {
    font-size: 11px;
    text-align: center;
    margin-top: 8px;
    color: var(--bs-secondary-color);
    white-space: nowrap;
    transition: all 0.3s;
}
.wizard-step-label.active { color: #667eea; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 카테고리 그리드 ── */
.cat-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 16px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 100px;
}
.cat-card:hover { border-color: #667eea; background: rgba(102,126,234,0.05); }
.cat-card.selected { border-color: #667eea; background: rgba(102,126,234,0.1); box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
.cat-card.primary { border-color: #28a745; background: rgba(40,167,69,0.1); }
.cat-card .cat-emoji { font-size: 32px; display: block; margin-bottom: 6px; }
.cat-card .cat-name { font-size: 12px; font-weight: 600; }
.cat-card .primary-badge { font-size: 10px; color: #28a745; font-weight: 700; display: none; }
.cat-card.primary .primary-badge { display: block; }

/* ── 요일별 시간 ── */
.hours-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.hours-row label { width: 60px; font-weight: 600; font-size: 13px; flex-shrink: 0; }
.hours-row input[type=time] { width: 110px; }

/* ── 의사 카드 ── */
.doctor-card { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; position: relative; }
.doctor-card .btn-remove { position: absolute; top: 8px; right: 8px; }

/* ── 이미지 업로드 ── */
.upload-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 140px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.upload-zone:hover { border-color: #667eea; background: rgba(102,126,234,0.03); }
.upload-zone img { max-height: 120px; max-width: 100%; border-radius: 8px; }
.upload-zone .upload-icon { font-size: 36px; color: var(--bs-secondary-color); }

/* ── 요금제 카드 ── */
.plan-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.plan-card:hover { border-color: #667eea; }
.plan-card.selected { border-color: #667eea; background: rgba(102,126,234,0.08); box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #667eea; }
.review-section h6:hover { text-decoration: underline; }

/* ── 버튼 영역 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-building-add"></i> 병원 등록 마법사</h4>
        <small class="text-muted">새 병원을 단계별로 등록합니다 (7단계)</small>
    </div>
</div>

<!-- 원형 스텝 인디케이터 -->
<div class="card mb-4">
    <div class="card-body p-2">
        <div class="wizard-header" id="wizardHeader">
            <!-- JS로 생성 -->
        </div>
    </div>
</div>

<!-- 스텝 콘텐츠 -->
<div class="card">
    <div class="card-body">

        <!-- Step 1: 기본 정보 -->
        <div class="wizard-content" data-step="1">
            <h5 class="mb-3"><i class="bi bi-building"></i> 기본 정보</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">병원명 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_hospital_name" required placeholder="예: 서울미녀성형외과">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">병원 슬러그 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="w_slug" maxlength="12" pattern="[a-zA-Z0-9]+" placeholder="영숫자 12자" oninput="checkSlug()">
                        <span class="input-group-text" id="slugStatus"></span>
                    </div>
                    <small class="text-muted">URL에 사용될 고유 식별자</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">대표자명 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_rep_name" required placeholder="홍길동">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">사업자등록번호</label>
                    <input type="text" class="form-control" id="w_biz_number" placeholder="000-00-00000" maxlength="12">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">이메일 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="w_email" required placeholder="hospital@example.com" oninput="checkEmail()">
                        <span class="input-group-text" id="emailStatus"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">전화번호 <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="w_phone" required placeholder="02-1234-5678">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">주소 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_address" required placeholder="서울특별시 강남구 ...">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="w_password" required minlength="6" placeholder="6자 이상">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">비밀번호 확인 <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="w_password2" required placeholder="비밀번호 재입력">
                </div>
            </div>
        </div>

        <!-- Step 2: 업종 선택 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-tags"></i> 업종/카테고리 선택</h5>
            <p class="text-muted small mb-3">해당 시술 카테고리를 선택하세요 (복수 선택 가능). 첫 번째 클릭이 <span class="badge bg-success">대표</span> 카테고리가 됩니다.</p>
            <div class="row g-3" id="catGrid">
                <?php foreach ($categories as $cat): ?>
                <div class="col-4 col-md-3 col-lg-2">
                    <div class="cat-card" data-cat-id="<?= $cat['id'] ?>" data-cat-name="<?= htmlspecialchars($cat['name']) ?>" onclick="toggleCategory(this)">
                        <span class="cat-emoji"><?= htmlspecialchars($cat['icon_emoji'] ?? $cat['icon'] ?? '🏥') ?></span>
                        <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <span class="primary-badge">★ 대표</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <label class="form-label fw-bold">전문 시술 태그 (선택)</label>
                <input type="text" class="form-control" id="w_tags" placeholder="쉼표로 구분: 쌍꺼풀, 눈밑지방, 코끝성형">
                <small class="text-muted">검색에 활용됩니다</small>
            </div>
        </div>

        <!-- Step 3: 병원 소개 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-file-text"></i> 병원 소개</h5>
            <div class="mb-3">
                <label class="form-label fw-bold">병원 소개 <span class="text-danger">*</span></label>
                <textarea class="form-control" id="w_description" rows="5" placeholder="병원의 특징과 강점을 소개해주세요..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">상세 소개 (선택)</label>
                <textarea class="form-control" id="w_introduction" rows="3" placeholder="추가 상세 소개..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">진료 시간 <span class="text-danger">*</span></label>
                <div id="businessHours">
                    <div class="hours-row"><label>월요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="mon" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="18:00" data-day="mon" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="mon" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>화요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="tue" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="18:00" data-day="tue" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="tue" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>수요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="wed" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="18:00" data-day="wed" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="wed" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>목요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="thu" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="18:00" data-day="thu" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="thu" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>금요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="fri" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="18:00" data-day="fri" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="fri" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>토요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="sat" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="14:00" data-day="sat" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="sat" data-type="closed"><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row"><label>일요일</label><input type="time" class="form-control form-control-sm" value="09:00" data-day="sun" data-type="open"><span>~</span><input type="time" class="form-control form-control-sm" value="13:00" data-day="sun" data-type="close"><div class="form-check ms-2"><input class="form-check-input" type="checkbox" data-day="sun" data-type="closed" checked><label class="form-check-label small">휴진</label></div></div>
                    <div class="hours-row mt-2"><label>점심시간</label><input type="time" class="form-control form-control-sm" value="12:30" id="lunchOpen"><span>~</span><input type="time" class="form-control form-control-sm" value="13:30" id="lunchClose"></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">가까운 지하철역 (선택)</label>
                <input type="text" class="form-control" id="w_subway" placeholder="쉼표로 구분: 강남역, 신논현역">
            </div>
        </div>

        <!-- Step 4: 이미지 등록 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-images"></i> 이미지 등록</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">대표 이미지 <span class="text-danger">*</span></label>
                    <div class="upload-zone" id="mainImageZone" onclick="document.getElementById('mainImageInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭하여 업로드 (600x400 권장)</span>
                    </div>
                    <input type="file" id="mainImageInput" accept="image/*" class="d-none" onchange="uploadImage(this, 'mainImageZone', 'main_image')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">로고 <span class="text-danger">*</span></label>
                    <div class="upload-zone" id="logoImageZone" onclick="document.getElementById('logoImageInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭하여 업로드 (1:1 비율)</span>
                    </div>
                    <input type="file" id="logoImageInput" accept="image/*" class="d-none" onchange="uploadImage(this, 'logoImageZone', 'logo_image')">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">내부 사진 (최대 10장)</label>
                    <div class="upload-zone" id="galleryZone" onclick="document.getElementById('galleryInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭하여 여러 장 업로드</span>
                    </div>
                    <input type="file" id="galleryInput" accept="image/*" multiple class="d-none" onchange="uploadGallery(this)">
                    <div id="galleryPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Step 5: 의사 정보 -->
        <div class="wizard-content" data-step="5">
            <h5 class="mb-3"><i class="bi bi-person-badge"></i> 의사/전문의 등록</h5>
            <p class="text-muted small mb-3">최소 1명의 의사를 등록해주세요.</p>
            <div id="doctorList">
                <div class="doctor-card" data-doctor-idx="0">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove" onclick="removeDoctor(this)" style="display:none"><i class="bi bi-x-lg"></i></button>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">이름 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm doc-name" placeholder="의사 이름">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">전문 분야</label>
                            <select class="form-select form-select-sm doc-specialty">
                                <option value="">선택</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="일반의">일반의</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">프로필 사진</label>
                            <input type="file" class="form-control form-control-sm doc-photo" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">경력/약력</label>
                            <textarea class="form-control form-control-sm doc-bio" rows="2" placeholder="학력, 경력, 자격 등"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addDoctor()">
                <i class="bi bi-plus-circle"></i> 의사 추가
            </button>
        </div>

        <!-- Step 6: 요금제 선택 -->
        <div class="wizard-content" data-step="6">
            <h5 class="mb-3"><i class="bi bi-credit-card"></i> 요금제 선택</h5>
            <div class="row g-3 mb-4" id="planCards">
                <?php if (empty($plans)): ?>
                <div class="col-md-4">
                    <div class="plan-card selected" data-plan-id="free">
                        <div class="text-warning mb-2"><i class="bi bi-star" style="font-size:32px"></i></div>
                        <h5 class="fw-bold">무료 체험</h5>
                        <div class="display-6 fw-bold my-3">₩0</div>
                        <ul class="list-unstyled text-start small">
                            <li><i class="bi bi-check text-success"></i> 병원 프로필 등록</li>
                            <li><i class="bi bi-check text-success"></i> 기본 검색 노출</li>
                            <li><i class="bi bi-check text-success"></i> 후기 관리</li>
                        </ul>
                        <div class="badge bg-success mt-2">7일 무료 체험</div>
                    </div>
                </div>
                <?php endif; ?>
                <?php foreach ($plans as $plan): ?>
                <div class="col-md-4">
                    <div class="plan-card" data-plan-id="<?= $plan['id'] ?>" onclick="selectPlan(this)">
                        <div class="text-primary mb-2"><i class="bi bi-gem" style="font-size:32px"></i></div>
                        <h5 class="fw-bold"><?= htmlspecialchars($plan['name']) ?></h5>
                        <div class="display-6 fw-bold my-3">₩<?= number_format($plan['price'] ?? 0) ?><small class="fs-6 text-muted">/월</small></div>
                        <ul class="list-unstyled text-start small">
                            <li><i class="bi bi-check text-success"></i> 병원 프로필 등록</li>
                            <li><i class="bi bi-check text-success"></i> 환자 예약 관리</li>
                            <li><i class="bi bi-check text-success"></i> 후기 관리</li>
                            <li><i class="bi bi-check text-success"></i> 통계 대시보드</li>
                        </ul>
                        <?php if ($plan['trial_days'] ?? 0): ?>
                        <div class="badge bg-success mt-2"><?= $plan['trial_days'] ?>일 무료 체험</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">결제 방법</label>
                    <div class="form-check"><input class="form-check-input" type="radio" name="payMethod" value="card" checked id="payCard"><label class="form-check-label" for="payCard">카드 결제</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="payMethod" value="transfer" id="payTransfer"><label class="form-check-label" for="payTransfer">계좌이체</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="payMethod" value="later" id="payLater"><label class="form-check-label" for="payLater">추후 결제</label></div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="agreeTerms" required><label class="form-check-label" for="agreeTerms"><strong>이용약관</strong>에 동의합니다 <span class="text-danger">*</span></label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="agreePrivacy" required><label class="form-check-label" for="agreePrivacy"><strong>개인정보 처리방침</strong>에 동의합니다 <span class="text-danger">*</span></label></div>
                </div>
            </div>
        </div>

        <!-- Step 7: 검토 완료 -->
        <div class="wizard-content" data-step="7">
            <h5 class="mb-3"><i class="bi bi-check-circle"></i> 검토 및 제출</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 제출해주세요. 항목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary">
                <!-- JS로 채움 -->
            </div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-primary" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 병원 등록 신청
                </button>
            </div>
        </div>

    </div>
</div>

<!-- 결과 모달 -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div id="resultIcon" style="font-size:64px"></div>
                <h4 id="resultTitle" class="mt-3"></h4>
                <p id="resultMsg" class="text-muted"></p>
                <a href="admin_hospital_wizard.php" class="btn btn-primary mt-3">새 병원 등록</a>
                <a href="admin_hospital_images.php" class="btn btn-outline-secondary mt-3">병원 관리</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '기본 정보', icon: 'bi-building' },
    { num: 2, label: '업종 선택', icon: 'bi-tags' },
    { num: 3, label: '병원 소개', icon: 'bi-file-text' },
    { num: 4, label: '이미지', icon: 'bi-images' },
    { num: 5, label: '의사 정보', icon: 'bi-person-badge' },
    { num: 6, label: '요금제', icon: 'bi-credit-card' },
    { num: 7, label: '검토 완료', icon: 'bi-check-circle' },
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = {
    main_image: '', logo_image: '', gallery_images: [],
    selectedCategories: [], primaryCategory: '',
    selectedPlan: 'free'
};

// ── 스텝 인디케이터 렌더링 ──
function renderStepHeader() {
    const header = document.getElementById('wizardHeader');
    header.innerHTML = STEPS.map((s, i) => {
        let state = 'pending';
        if (s.num === currentStep) state = 'active';
        else if (completedSteps.has(s.num)) state = 'completed';
        const line = i < STEPS.length - 1
            ? `<div class="wizard-line ${completedSteps.has(s.num) ? 'completed' : ''}"></div>` : '';
        return `
            <div class="wizard-step-item">
                <div class="wizard-step-row">
                    <div class="wizard-circle ${state}" onclick="goToStep(${s.num})" title="${s.label}">
                        <span>${s.num}</span>
                    </div>
                    ${line}
                </div>
                <div class="wizard-step-label ${state}">${s.label}</div>
            </div>`;
    }).join('');
}

// ── 스텝 이동 ──
function showStep(step) {
    document.querySelectorAll('.wizard-content').forEach(el => el.classList.remove('active'));
    const target = document.querySelector(`.wizard-content[data-step="${step}"]`);
    if (target) target.classList.add('active');
    currentStep = step;
    renderStepHeader();
    updateButtons();
    if (step === 7) buildReview();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function goToStep(step) {
    if (step <= currentStep || completedSteps.has(step) || step === currentStep + 1) {
        if (currentStep !== step && currentStep < step) {
            if (!validateStep(currentStep)) return;
            completedSteps.add(currentStep);
        }
        showStep(step);
    }
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    completedSteps.add(currentStep);
    if (currentStep < STEPS.length) showStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function updateButtons() {
    document.getElementById('btnPrev').style.display = currentStep > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display = currentStep < STEPS.length ? '' : 'none';
    document.getElementById('btnSubmit').style.display = currentStep === STEPS.length ? '' : 'none';
}

// ── 유효성 검사 ──
function validateStep(step) {
    if (step === 1) {
        const name = document.getElementById('w_hospital_name').value.trim();
        const email = document.getElementById('w_email').value.trim();
        const phone = document.getElementById('w_phone').value.trim();
        const addr = document.getElementById('w_address').value.trim();
        const pw = document.getElementById('w_password').value;
        const pw2 = document.getElementById('w_password2').value;
        if (!name) { alert('병원명을 입력해주세요.'); return false; }
        if (!email) { alert('이메일을 입력해주세요.'); return false; }
        if (!phone) { alert('전화번호를 입력해주세요.'); return false; }
        if (!addr) { alert('주소를 입력해주세요.'); return false; }
        if (pw.length < 6) { alert('비밀번호는 6자 이상이어야 합니다.'); return false; }
        if (pw !== pw2) { alert('비밀번호가 일치하지 않습니다.'); return false; }
    }
    if (step === 2) {
        if (wizardData.selectedCategories.length === 0) {
            alert('최소 1개의 카테고리를 선택해주세요.');
            return false;
        }
    }
    if (step === 3) {
        if (!document.getElementById('w_description').value.trim()) {
            alert('병원 소개를 입력해주세요.');
            return false;
        }
    }
    if (step === 5) {
        const firstDoc = document.querySelector('.doc-name');
        if (firstDoc && !firstDoc.value.trim()) {
            alert('최소 1명의 의사 이름을 입력해주세요.');
            return false;
        }
    }
    if (step === 6) {
        if (!document.getElementById('agreeTerms').checked || !document.getElementById('agreePrivacy').checked) {
            alert('이용약관과 개인정보 처리방침에 동의해주세요.');
            return false;
        }
    }
    return true;
}

// ── 카테고리 선택 ──
function toggleCategory(el) {
    const catName = el.dataset.catName;
    const idx = wizardData.selectedCategories.indexOf(catName);

    if (idx > -1) {
        wizardData.selectedCategories.splice(idx, 1);
        el.classList.remove('selected', 'primary');
        if (wizardData.primaryCategory === catName) {
            wizardData.primaryCategory = wizardData.selectedCategories[0] || '';
            updatePrimaryBadge();
        }
    } else {
        wizardData.selectedCategories.push(catName);
        el.classList.add('selected');
        if (!wizardData.primaryCategory) {
            wizardData.primaryCategory = catName;
            el.classList.add('primary');
        }
    }
}

function updatePrimaryBadge() {
    document.querySelectorAll('.cat-card').forEach(c => {
        c.classList.remove('primary');
        if (c.dataset.catName === wizardData.primaryCategory) c.classList.add('primary');
    });
}

// ── 이메일/슬러그 중복 체크 ──
let emailTimer, slugTimer;
function checkEmail() {
    clearTimeout(emailTimer);
    const val = document.getElementById('w_email').value.trim();
    const st = document.getElementById('emailStatus');
    if (!val) { st.innerHTML = ''; return; }
    emailTimer = setTimeout(async () => {
        const fd = new FormData();
        fd.append('action', 'check_email');
        fd.append('email', val);
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        st.innerHTML = d.exists
            ? '<i class="bi bi-x-circle text-danger"></i>'
            : '<i class="bi bi-check-circle text-success"></i>';
    }, 500);
}

function checkSlug() {
    clearTimeout(slugTimer);
    const val = document.getElementById('w_slug').value.trim();
    const st = document.getElementById('slugStatus');
    if (!val) { st.innerHTML = ''; return; }
    slugTimer = setTimeout(async () => {
        const fd = new FormData();
        fd.append('action', 'check_slug');
        fd.append('slug', val);
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        st.innerHTML = d.exists
            ? '<i class="bi bi-x-circle text-danger"></i>'
            : '<i class="bi bi-check-circle text-success"></i>';
    }, 500);
}

// ── 이미지 업로드 ──
async function uploadImage(input, zoneId, field) {
    if (!input.files[0]) return;
    const zone = document.getElementById(zoneId);
    zone.innerHTML = '<div class="spinner-border text-primary"></div>';

    const fd = new FormData();
    fd.append('action', 'upload_image');
    fd.append('file', input.files[0]);
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();

    if (d.success) {
        zone.innerHTML = `<img src="${d.url}" alt="uploaded"><br><small class="text-success mt-1">업로드 완료</small>`;
        wizardData[field] = d.url;
    } else {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${d.error}</small>`;
    }
}

async function uploadGallery(input) {
    const preview = document.getElementById('galleryPreview');
    for (const file of input.files) {
        if (wizardData.gallery_images.length >= 10) break;
        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('file', file);
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            wizardData.gallery_images.push(d.url);
            preview.innerHTML += `<div class="position-relative" style="width:80px;height:80px">
                <img src="${d.url}" style="width:80px;height:80px;object-fit:cover;border-radius:6px">
                <button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0" style="width:20px;height:20px;font-size:10px;line-height:1" onclick="removeGalleryImg(this,'${d.url}')">×</button>
            </div>`;
        }
    }
}

function removeGalleryImg(btn, url) {
    wizardData.gallery_images = wizardData.gallery_images.filter(u => u !== url);
    btn.parentElement.remove();
}

// ── 의사 관리 ──
let doctorIdx = 1;
function addDoctor() {
    const list = document.getElementById('doctorList');
    const card = document.createElement('div');
    card.className = 'doctor-card';
    card.dataset.doctorIdx = doctorIdx++;
    card.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove" onclick="removeDoctor(this)"><i class="bi bi-x-lg"></i></button>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small fw-bold">이름</label><input type="text" class="form-control form-control-sm doc-name" placeholder="의사 이름"></div>
            <div class="col-md-4"><label class="form-label small fw-bold">전문 분야</label><select class="form-select form-select-sm doc-specialty">${document.querySelector('.doc-specialty').innerHTML}</select></div>
            <div class="col-md-4"><label class="form-label small fw-bold">프로필 사진</label><input type="file" class="form-control form-control-sm doc-photo" accept="image/*"></div>
            <div class="col-12"><label class="form-label small fw-bold">경력/약력</label><textarea class="form-control form-control-sm doc-bio" rows="2" placeholder="학력, 경력, 자격 등"></textarea></div>
        </div>`;
    list.appendChild(card);
    // 첫 번째 의사 삭제 버튼 표시
    if (list.children.length > 1) {
        list.querySelector('.btn-remove').style.display = '';
    }
}

function removeDoctor(btn) {
    const list = document.getElementById('doctorList');
    if (list.children.length <= 1) return;
    btn.closest('.doctor-card').remove();
    if (list.children.length === 1) list.querySelector('.btn-remove').style.display = 'none';
}

// ── 요금제 선택 ──
function selectPlan(el) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    wizardData.selectedPlan = el.dataset.planId;
}

// ── 진료시간 수집 ──
function getBusinessHours() {
    const days = ['mon','tue','wed','thu','fri','sat','sun'];
    const labels = ['월요일','화요일','수요일','목요일','금요일','토요일','일요일'];
    const hours = {};
    days.forEach((d, i) => {
        const closed = document.querySelector(`input[data-day="${d}"][data-type="closed"]`);
        if (closed && closed.checked) {
            hours[labels[i]] = '휴진';
        } else {
            const open = document.querySelector(`input[data-day="${d}"][data-type="open"]`);
            const close = document.querySelector(`input[data-day="${d}"][data-type="close"]`);
            hours[labels[i]] = `${open?.value || '09:00'} ~ ${close?.value || '18:00'}`;
        }
    });
    hours['점심시간'] = `${document.getElementById('lunchOpen')?.value || '12:30'} ~ ${document.getElementById('lunchClose')?.value || '13:30'}`;
    return hours;
}

// ── 검토 화면 생성 ──
function buildReview() {
    const cats = wizardData.selectedCategories;
    const tags = document.getElementById('w_tags').value.trim();
    const doctors = [];
    document.querySelectorAll('.doctor-card').forEach(card => {
        const name = card.querySelector('.doc-name')?.value?.trim();
        if (name) doctors.push({
            name, specialty: card.querySelector('.doc-specialty')?.value || '',
            biography: card.querySelector('.doc-bio')?.value?.trim() || ''
        });
    });
    const hours = getBusinessHours();

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-building"></i> 1. 기본 정보</h6>
            <div class="row small">
                <div class="col-6"><strong>병원명:</strong> ${esc(document.getElementById('w_hospital_name').value)}</div>
                <div class="col-6"><strong>대표자:</strong> ${esc(document.getElementById('w_rep_name').value)}</div>
                <div class="col-6"><strong>이메일:</strong> ${esc(document.getElementById('w_email').value)}</div>
                <div class="col-6"><strong>전화:</strong> ${esc(document.getElementById('w_phone').value)}</div>
                <div class="col-12"><strong>주소:</strong> ${esc(document.getElementById('w_address').value)}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-tags"></i> 2. 업종/카테고리</h6>
            <div class="small">
                <strong>카테고리:</strong> ${cats.map(c => `<span class="badge ${c === wizardData.primaryCategory ? 'bg-success' : 'bg-primary'} me-1">${esc(c)}</span>`).join('')}
                ${tags ? `<br><strong>태그:</strong> ${esc(tags)}` : ''}
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-file-text"></i> 3. 병원 소개</h6>
            <div class="small">${esc(document.getElementById('w_description').value).substring(0, 200)}${document.getElementById('w_description').value.length > 200 ? '...' : ''}</div>
            <div class="small mt-1"><strong>진료시간:</strong> ${Object.entries(hours).map(([k,v]) => `${k}: ${v}`).join(' / ')}</div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(4)"><i class="bi bi-images"></i> 4. 이미지</h6>
            <div class="d-flex gap-2">
                ${wizardData.main_image ? `<img src="${wizardData.main_image}" style="height:60px;border-radius:4px">` : '<span class="text-danger small">대표 이미지 미등록</span>'}
                ${wizardData.logo_image ? `<img src="${wizardData.logo_image}" style="height:60px;border-radius:4px">` : '<span class="text-danger small">로고 미등록</span>'}
                <span class="small text-muted">갤러리: ${wizardData.gallery_images.length}장</span>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(5)"><i class="bi bi-person-badge"></i> 5. 의사 정보</h6>
            <div class="small">${doctors.map(d => `<span class="badge bg-info text-dark me-1">${esc(d.name)} (${esc(d.specialty || '미지정')})</span>`).join('') || '<span class="text-danger">의사 미등록</span>'}</div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(6)"><i class="bi bi-credit-card"></i> 6. 요금제</h6>
            <div class="small">
                <strong>플랜:</strong> ${esc(wizardData.selectedPlan)}
                &nbsp;|&nbsp;
                <strong>결제:</strong> ${document.querySelector('input[name="payMethod"]:checked')?.value || '-'}
            </div>
        </div>
    `;
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('병원 등록을 신청하시겠습니까?')) return;

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const doctors = [];
    document.querySelectorAll('.doctor-card').forEach(card => {
        const name = card.querySelector('.doc-name')?.value?.trim();
        if (name) doctors.push({
            name,
            specialty: card.querySelector('.doc-specialty')?.value || '',
            biography: card.querySelector('.doc-bio')?.value?.trim() || '',
            photo: ''
        });
    });

    const data = {
        hospital_name: document.getElementById('w_hospital_name').value.trim(),
        slug: document.getElementById('w_slug').value.trim(),
        rep_name: document.getElementById('w_rep_name').value.trim(),
        biz_number: document.getElementById('w_biz_number').value.trim(),
        email: document.getElementById('w_email').value.trim(),
        phone: document.getElementById('w_phone').value.trim(),
        address: document.getElementById('w_address').value.trim(),
        password: document.getElementById('w_password').value,
        categories: wizardData.selectedCategories,
        primary_category: wizardData.primaryCategory,
        treatment_tags: document.getElementById('w_tags').value.split(',').map(t => t.trim()).filter(Boolean),
        description: document.getElementById('w_description').value.trim(),
        introduction: document.getElementById('w_introduction').value.trim(),
        business_hours: getBusinessHours(),
        subway: document.getElementById('w_subway').value.split(',').map(t => t.trim()).filter(Boolean),
        main_image: wizardData.main_image,
        logo_image: wizardData.logo_image,
        gallery_images: wizardData.gallery_images,
        doctors: doctors,
        plan: wizardData.selectedPlan,
        pay_method: document.querySelector('input[name="payMethod"]:checked')?.value || 'later',
    };

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('data', JSON.stringify(data));

    try {
        const r = await fetch('', {method:'POST', body:fd});
        const result = await r.json();
        const modal = new bootstrap.Modal(document.getElementById('resultModal'));
        if (result.success) {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            document.getElementById('resultTitle').textContent = '등록 신청 완료!';
            document.getElementById('resultMsg').innerHTML = result.message;
        } else {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            document.getElementById('resultTitle').textContent = '등록 실패';
            document.getElementById('resultMsg').innerHTML = result.error;
        }
        modal.show();
    } catch (e) {
        alert('오류: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 병원 등록 신청';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

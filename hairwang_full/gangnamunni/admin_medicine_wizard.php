<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * admin_medicine_wizard.php - 의약품 등록 마법사
 * 4단계: 기본 정보 -> 가격/수량 -> 추가 정보 -> 검토 완료
 * categories/brands 비어있을 경우 직접 입력 fallback 제공
 *
 * @since 2026-02-23
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('medicines');
require_once __DIR__ . '/includes/hospital_filter.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
// tenant 격리: super_admin 만 모든 병원(tenant), 그 외에는 소속 병원과 연결된 tenant 에만 조회·등록
$allowed_tenant_ids = get_admin_allowed_tenant_ids($connect);
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 카테고리 로드
    if ($action === 'load_categories') {
        [$tOk, $tId] = admin_validate_tenant_id($connect, $allowed_tenant_ids, $_POST['tenant_id'] ?? '');
        $cats = [];
        $where = !$tOk ? "WHERE 1=0" : ($tId !== null ? "WHERE tenant_id = '" . mysqli_real_escape_string($connect, $tId) . "'" : "");
        $r = mysqli_query($connect, "SELECT id, name FROM categories {$where} ORDER BY name");
        while ($row = mysqli_fetch_assoc($r)) $cats[] = $row;
        echo json_encode(['success' => true, 'data' => $cats]);
        exit;
    }

    // 브랜드 로드
    if ($action === 'load_brands') {
        [$tOk, $tId] = admin_validate_tenant_id($connect, $allowed_tenant_ids, $_POST['tenant_id'] ?? '');
        $brands = [];
        $where = !$tOk ? "WHERE 1=0" : ($tId !== null ? "WHERE tenant_id = '" . mysqli_real_escape_string($connect, $tId) . "'" : "");
        $r = mysqli_query($connect, "SELECT id, name FROM brands {$where} ORDER BY name");
        while ($row = mysqli_fetch_assoc($r)) $brands[] = $row;
        echo json_encode(['success' => true, 'data' => $brands]);
        exit;
    }

    // 병원(tenants) 목록
    if ($action === 'load_hospitals') {
        $hospitals = [];
        $r = mysqli_query($connect, "SELECT id, hospital_name FROM tenants WHERE " . admin_tenant_scope_sql($connect, $allowed_tenant_ids, 'id') . " ORDER BY hospital_name");
        while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;
        echo json_encode(['success' => true, 'data' => $hospitals]);
        exit;
    }

    // 이미지 업로드
    if ($action === 'upload_image') {
        $uploadDir = __DIR__ . '/uploads/medicines/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => '파일 업로드 실패']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'error' => '이미지 파일만 가능합니다']);
            exit;
        }
        $fname = 'med_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            echo json_encode(['success' => true, 'url' => 'uploads/medicines/' . $fname, 'filename' => $fname]);
        } else {
            echo json_encode(['success' => false, 'error' => '저장 실패']);
        }
        exit;
    }

    // 최종 등록
    if ($action === 'submit') {
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => '데이터 없음']);
            exit;
        }

        mysqli_begin_transaction($connect);
        try {
            // tenant id 는 문자열(UUID) — 범위·존재 여부 검증 후 사용 (이전 intval() 은 잘못된 tenant 로 저장)
            [$tenantOk, $tenantIdRaw] = admin_validate_tenant_id($connect, $allowed_tenant_ids, $data['tenant_id'] ?? '');
            if (!$tenantOk) throw new Exception('선택한 병원에 등록할 권한이 없습니다.');
            $tenantId  = $tenantIdRaw !== null ? "'" . mysqli_real_escape_string($connect, $tenantIdRaw) . "'" : 'NULL';
            // tenant 미지정은 super_admin 만 가능 → 그 경우 기존 동작대로 제한하지 않음
            $tenantCond = $tenantIdRaw !== null ? "tenant_id = {$tenantId}" : '1=1';
            $now       = date('Y-m-d H:i:s');

            // 1) 카테고리 - 신규 입력이면 INSERT
            $categoryId = intval($data['category_id'] ?? 0);
            // 기존 카테고리/브랜드는 같은 tenant 소속만 허용
            if ($categoryId > 0) {
                $chk = mysqli_query($connect, "SELECT 1 FROM categories WHERE id = {$categoryId} AND {$tenantCond} LIMIT 1");
                if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('선택한 카테고리를 사용할 수 없습니다.');
            }
            if ($categoryId === 0 && !empty($data['category_name'])) {
                $catName = $esc($data['category_name']);
                mysqli_query($connect, "INSERT INTO categories (name, tenant_id, created_at, updated_at) VALUES ('{$catName}', {$tenantId}, '{$now}', '{$now}')");
                if (mysqli_error($connect)) throw new Exception('카테고리 생성 실패: ' . mysqli_error($connect));
                $categoryId = mysqli_insert_id($connect);
            }

            // 2) 브랜드 - 신규 입력이면 INSERT
            $brandId = intval($data['brand_id'] ?? 0);
            if ($brandId > 0) {
                $chk = mysqli_query($connect, "SELECT 1 FROM brands WHERE id = {$brandId} AND {$tenantCond} LIMIT 1");
                if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('선택한 브랜드를 사용할 수 없습니다.');
            }
            if ($brandId === 0 && !empty($data['brand_name'])) {
                $brandName = $esc($data['brand_name']);
                mysqli_query($connect, "INSERT INTO brands (name, tenant_id, created_at, updated_at) VALUES ('{$brandName}', {$tenantId}, '{$now}', '{$now}')");
                if (mysqli_error($connect)) throw new Exception('브랜드 생성 실패: ' . mysqli_error($connect));
                $brandId = mysqli_insert_id($connect);
            }

            // 3) medicines INSERT
            $name           = $esc($data['name'] ?? '');
            $sellingPrice   = floatval($data['selling_price'] ?? 0);
            $buyingPrice    = floatval($data['buying_price'] ?? 0);
            $quantity       = intval($data['quantity'] ?? 0);
            $saltComp       = $esc($data['salt_composition'] ?? '');
            $sideEffects    = $esc($data['side_effects'] ?? '');
            $description    = $esc($data['description'] ?? '');

            $sql = "INSERT INTO medicines (category_id, brand_id, name, selling_price, buying_price, quantity, available_quantity,
                    salt_composition, side_effects, description, tenant_id, created_at, updated_at)
                    VALUES ({$categoryId}, {$brandId}, '{$name}', {$sellingPrice}, {$buyingPrice}, {$quantity}, {$quantity},
                    '{$saltComp}', '{$sideEffects}', '{$description}', {$tenantId}, '{$now}', '{$now}')";
            mysqli_query($connect, $sql);
            if (mysqli_error($connect)) throw new Exception('medicines INSERT 실패: ' . mysqli_error($connect));
            $medicineId = mysqli_insert_id($connect);

            // 이미지 저장 (media 테이블)
            $image = $data['image'] ?? '';
            if ($image) {
                $imageEsc = $esc($image);
                mysqli_query($connect, "INSERT INTO media (model_type, model_id, collection_name, name, file_name, mime_type, disk, size, created_at, updated_at)
                    VALUES ('App\\\\Models\\\\Medicine', {$medicineId}, 'medicine', 'medicine', '{$imageEsc}', 'image/jpeg', 'public', 0, '{$now}', '{$now}')");
            }

            mysqli_commit($connect);

            echo json_encode([
                'success' => true,
                'medicine_id' => $medicineId,
                'message' => "의약품 '{$name}'이(가) 성공적으로 등록되었습니다."
            ]);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$admin_page_title = '의약품 등록 마법사';
$admin_current = 'medicine_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 테마색: #ffc107 (노란색, 텍스트 #333) ── */
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
    min-width: 80px;
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
.wizard-circle.active { background: #ffc107; color: var(--bs-body-color); border-color: #ffc107; box-shadow: 0 0 0 5px rgba(255,193,7,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #28a745; color: #fff; border-color: #28a745; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line {
    width: 60px; height: 3px;
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
.wizard-step-label.active { color: #d4a106; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

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
.upload-zone:hover { border-color: #ffc107; background: rgba(255,193,7,0.03); }
.upload-zone img { max-height: 120px; max-width: 100%; border-radius: 8px; }
.upload-zone .upload-icon { font-size: 36px; color: var(--bs-secondary-color); }

/* ── 검토 요약 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #d4a106; }
.review-section h6:hover { text-decoration: underline; }

/* ── 버튼 영역 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

.btn-med { background: #ffc107; border-color: #ffc107; color: var(--bs-body-color); }
.btn-med:hover { background: #e0a800; border-color: #e0a800; color: var(--bs-body-color); }

/* ── 직접 입력 토글 ── */
.custom-input-row { display: none; margin-top: 8px; }
.custom-input-row.show { display: block; }
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-capsule" style="color:#ffc107"></i> 의약품 등록 마법사</h4>
        <small class="text-muted">새 의약품을 단계별로 등록합니다 (4단계)</small>
    </div>
</div>

<!-- 원형 스텝 인디케이터 -->
<div class="card mb-4">
    <div class="card-body p-2">
        <div class="wizard-header" id="wizardHeader"></div>
    </div>
</div>

<!-- 스텝 콘텐츠 -->
<div class="card">
    <div class="card-body">

        <!-- Step 1: 기본 정보 -->
        <div class="wizard-content" data-step="1">
            <h5 class="mb-3"><i class="bi bi-capsule" style="color:#ffc107"></i> 기본 정보</h5>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">소속 병원 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_hospital" onchange="onHospitalChange()">
                        <option value="">-- 병원 선택 --</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">약품명 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="w_name" required placeholder="예: 아스피린 100mg">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">카테고리 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_category" onchange="toggleCustomInput('category')">
                        <option value="">-- 선택 --</option>
                        <option value="__custom__">직접 입력</option>
                    </select>
                    <div class="custom-input-row" id="customCategoryRow">
                        <input type="text" class="form-control form-control-sm" id="w_category_custom" placeholder="새 카테고리명 입력">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">브랜드 <span class="text-danger">*</span></label>
                    <select class="form-select" id="w_brand" onchange="toggleCustomInput('brand')">
                        <option value="">-- 선택 --</option>
                        <option value="__custom__">직접 입력</option>
                    </select>
                    <div class="custom-input-row" id="customBrandRow">
                        <input type="text" class="form-control form-control-sm" id="w_brand_custom" placeholder="새 브랜드명 입력">
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">성분 (Salt Composition)</label>
                    <textarea class="form-control" id="w_salt" rows="2" placeholder="예: Acetylsalicylic acid 100mg"></textarea>
                </div>
            </div>
        </div>

        <!-- Step 2: 가격/수량 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-currency-dollar" style="color:#ffc107"></i> 가격 / 수량</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">판매가 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="w_selling_price" required min="0" step="100" placeholder="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">구매가 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="w_buying_price" required min="0" step="100" placeholder="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">수량 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="w_quantity" required min="0" step="1" placeholder="0">
                        <span class="input-group-text">개</span>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-info-circle"></i> 마진율:
                        <strong id="marginRate">0%</strong>
                        (판매가 - 구매가 기준 자동 계산)
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">부작용</label>
                    <textarea class="form-control" id="w_side_effects" rows="3" placeholder="알려진 부작용을 입력해주세요..."></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">설명</label>
                    <textarea class="form-control" id="w_description" rows="3" placeholder="약품에 대한 설명..."></textarea>
                </div>
            </div>
        </div>

        <!-- Step 3: 추가 정보 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-info-circle" style="color:#ffc107"></i> 추가 정보</h5>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">약품 이미지</label>
                    <div class="upload-zone" id="imageZone" onclick="document.getElementById('imageInput').click()">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <span class="text-muted small mt-2">클릭 또는 드래그하여 업로드 (JPG, PNG)</span>
                    </div>
                    <input type="file" id="imageInput" accept="image/*" class="d-none" onchange="uploadImage(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">보관 방법</label>
                    <input type="text" class="form-control" id="w_storage" placeholder="예: 실온 보관, 냉장 보관">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">유효기간</label>
                    <input type="date" class="form-control" id="w_expiry">
                </div>
            </div>
        </div>

        <!-- Step 4: 검토 완료 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-check-circle" style="color:#ffc107"></i> 검토 및 등록</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 등록 버튼을 눌러주세요. 항목을 클릭하면 해당 단계로 이동합니다.</p>
            <div id="reviewSummary"></div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-med" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitWizard()" style="display:none">
                    <i class="bi bi-check-circle"></i> 의약품 등록
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
                <a href="admin_medicine_wizard.php" class="btn btn-med mt-3">새 의약품 등록</a>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3">대시보드</a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 스텝 설정 ──
const STEPS = [
    { num: 1, label: '기본 정보', icon: 'bi-capsule' },
    { num: 2, label: '가격/수량', icon: 'bi-currency-dollar' },
    { num: 3, label: '추가 정보', icon: 'bi-info-circle' },
    { num: 4, label: '검토 완료', icon: 'bi-check-circle' }
];
let currentStep = 1;
let completedSteps = new Set();
let wizardData = {
    image: ''
};

// ── XSS 방지 ──
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

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
    if (step === 4) renderReview();
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
        if (!document.getElementById('w_hospital').value) { alert('소속 병원을 선택해주세요.'); return false; }
        if (!document.getElementById('w_name').value.trim()) { alert('약품명을 입력해주세요.'); return false; }
        // 카테고리: select 또는 직접 입력
        const catSel = document.getElementById('w_category').value;
        const catCustom = document.getElementById('w_category_custom').value.trim();
        if (!catSel && !catCustom) { alert('카테고리를 선택하거나 입력해주세요.'); return false; }
        if (catSel === '__custom__' && !catCustom) { alert('카테고리명을 입력해주세요.'); return false; }
        // 브랜드: select 또는 직접 입력
        const brandSel = document.getElementById('w_brand').value;
        const brandCustom = document.getElementById('w_brand_custom').value.trim();
        if (!brandSel && !brandCustom) { alert('브랜드를 선택하거나 입력해주세요.'); return false; }
        if (brandSel === '__custom__' && !brandCustom) { alert('브랜드명을 입력해주세요.'); return false; }
    }
    if (step === 2) {
        if (!document.getElementById('w_selling_price').value || parseFloat(document.getElementById('w_selling_price').value) < 0) {
            alert('판매가를 입력해주세요.'); return false;
        }
        if (!document.getElementById('w_buying_price').value || parseFloat(document.getElementById('w_buying_price').value) < 0) {
            alert('구매가를 입력해주세요.'); return false;
        }
        if (!document.getElementById('w_quantity').value || parseInt(document.getElementById('w_quantity').value) < 0) {
            alert('수량을 입력해주세요.'); return false;
        }
    }
    return true;
}

// ── 병원 선택 변경 -> 카테고리/브랜드 로드 ──
async function onHospitalChange() {
    const tenantId = document.getElementById('w_hospital').value;
    if (!tenantId) return;
    await loadCategories(tenantId);
    await loadBrands(tenantId);
}

// ── 카테고리 로드 ──
async function loadCategories(tenantId) {
    const fd = new FormData();
    fd.append('action', 'load_categories');
    fd.append('tenant_id', tenantId);
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        const sel = document.getElementById('w_category');
        // 기존 옵션 클리어 (처음 2개 유지: 선택, 직접 입력)
        while (sel.options.length > 2) sel.remove(2);
        if (d.success && d.data.length > 0) {
            d.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('카테고리 로드 실패:', e); }
}

// ── 브랜드 로드 ──
async function loadBrands(tenantId) {
    const fd = new FormData();
    fd.append('action', 'load_brands');
    fd.append('tenant_id', tenantId);
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        const sel = document.getElementById('w_brand');
        while (sel.options.length > 2) sel.remove(2);
        if (d.success && d.data.length > 0) {
            d.data.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('브랜드 로드 실패:', e); }
}

// ── 병원 목록 로드 ──
async function loadHospitals() {
    const fd = new FormData();
    fd.append('action', 'load_hospitals');
    try {
        const r = await fetch('', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success) {
            const sel = document.getElementById('w_hospital');
            d.data.forEach(h => {
                const opt = document.createElement('option');
                opt.value = h.id;
                opt.textContent = h.hospital_name;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.error('병원 목록 로드 실패:', e); }
}

// ── 직접 입력 토글 ──
function toggleCustomInput(type) {
    const sel = document.getElementById(type === 'category' ? 'w_category' : 'w_brand');
    const row = document.getElementById(type === 'category' ? 'customCategoryRow' : 'customBrandRow');
    if (sel.value === '__custom__') {
        row.classList.add('show');
    } else {
        row.classList.remove('show');
    }
}

// ── 마진율 자동 계산 ──
function updateMargin() {
    const sell = parseFloat(document.getElementById('w_selling_price').value) || 0;
    const buy = parseFloat(document.getElementById('w_buying_price').value) || 0;
    let rate = 0;
    if (buy > 0) rate = ((sell - buy) / buy * 100).toFixed(1);
    document.getElementById('marginRate').textContent = rate + '%';
}

// ── 이미지 업로드 ──
async function uploadImage(input) {
    if (!input.files[0]) return;
    const zone = document.getElementById('imageZone');
    zone.innerHTML = '<div class="spinner-border text-warning"></div>';

    const fd = new FormData();
    fd.append('action', 'upload_image');
    fd.append('file', input.files[0]);
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();

    if (d.success) {
        zone.innerHTML = `<img src="${d.url}" alt="medicine"><br><small class="text-success mt-1">업로드 완료</small>`;
        wizardData.image = d.url;
    } else {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${esc(d.error)}</small>`;
    }
}

// 드래그 앤 드롭
(function() {
    const zone = document.getElementById('imageZone');
    if (!zone) return;
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#ffc107'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        const input = document.getElementById('imageInput');
        input.files = e.dataTransfer.files;
        uploadImage(input);
    });
})();

// ── 가격 입력 시 마진 업데이트 ──
document.getElementById('w_selling_price')?.addEventListener('input', updateMargin);
document.getElementById('w_buying_price')?.addEventListener('input', updateMargin);

// ── 검토 화면 ──
function renderReview() {
    const hospitalSel = document.getElementById('w_hospital');
    const hospitalName = hospitalSel.options[hospitalSel.selectedIndex]?.text || '-';

    // 카테고리 표시
    const catSel = document.getElementById('w_category');
    let categoryName = '';
    if (catSel.value === '__custom__') {
        categoryName = document.getElementById('w_category_custom').value.trim() + ' (신규)';
    } else if (catSel.value) {
        categoryName = catSel.options[catSel.selectedIndex]?.text || '-';
    }

    // 브랜드 표시
    const brandSel = document.getElementById('w_brand');
    let brandName = '';
    if (brandSel.value === '__custom__') {
        brandName = document.getElementById('w_brand_custom').value.trim() + ' (신규)';
    } else if (brandSel.value) {
        brandName = brandSel.options[brandSel.selectedIndex]?.text || '-';
    }

    const sell = parseFloat(document.getElementById('w_selling_price').value) || 0;
    const buy = parseFloat(document.getElementById('w_buying_price').value) || 0;
    const qty = parseInt(document.getElementById('w_quantity').value) || 0;

    document.getElementById('reviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-capsule"></i> 1. 기본 정보</h6>
            <div class="row small">
                <div class="col-6"><strong>약품명:</strong> ${esc(document.getElementById('w_name').value)}</div>
                <div class="col-6"><strong>소속 병원:</strong> ${esc(hospitalName)}</div>
                <div class="col-6"><strong>카테고리:</strong> ${esc(categoryName)}</div>
                <div class="col-6"><strong>브랜드:</strong> ${esc(brandName)}</div>
                <div class="col-12"><strong>성분:</strong> ${esc(document.getElementById('w_salt').value) || '-'}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-currency-dollar"></i> 2. 가격/수량</h6>
            <div class="row small">
                <div class="col-4"><strong>판매가:</strong> ${sell.toLocaleString()}원</div>
                <div class="col-4"><strong>구매가:</strong> ${buy.toLocaleString()}원</div>
                <div class="col-4"><strong>수량:</strong> ${qty.toLocaleString()}개</div>
                <div class="col-12 mt-1"><strong>부작용:</strong> ${esc(document.getElementById('w_side_effects').value) || '-'}</div>
                <div class="col-12"><strong>설명:</strong> ${esc(document.getElementById('w_description').value).substring(0, 150) || '-'}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-info-circle"></i> 3. 추가 정보</h6>
            <div class="row small">
                <div class="col-6"><strong>보관 방법:</strong> ${esc(document.getElementById('w_storage').value) || '-'}</div>
                <div class="col-6"><strong>유효기간:</strong> ${esc(document.getElementById('w_expiry').value) || '-'}</div>
                <div class="col-12 mt-1">
                    ${wizardData.image ? `<img src="${wizardData.image}" style="height:60px;border-radius:4px">` : '<span class="text-muted">이미지 미등록</span>'}
                </div>
            </div>
        </div>
    `;
}

// ── 최종 제출 ──
async function submitWizard() {
    if (!confirm('의약품 등록을 진행하시겠습니까?')) return;

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const catSel = document.getElementById('w_category');
    const brandSel = document.getElementById('w_brand');

    const data = {
        tenant_id: document.getElementById('w_hospital').value,
        name: document.getElementById('w_name').value.trim(),
        category_id: (catSel.value && catSel.value !== '__custom__') ? catSel.value : 0,
        category_name: catSel.value === '__custom__' ? document.getElementById('w_category_custom').value.trim() : '',
        brand_id: (brandSel.value && brandSel.value !== '__custom__') ? brandSel.value : 0,
        brand_name: brandSel.value === '__custom__' ? document.getElementById('w_brand_custom').value.trim() : '',
        salt_composition: document.getElementById('w_salt').value.trim(),
        selling_price: parseFloat(document.getElementById('w_selling_price').value) || 0,
        buying_price: parseFloat(document.getElementById('w_buying_price').value) || 0,
        quantity: parseInt(document.getElementById('w_quantity').value) || 0,
        side_effects: document.getElementById('w_side_effects').value.trim(),
        description: document.getElementById('w_description').value.trim(),
        image: wizardData.image,
        storage: document.getElementById('w_storage').value.trim(),
        expiry: document.getElementById('w_expiry').value
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
            document.getElementById('resultTitle').textContent = '등록 완료!';
            document.getElementById('resultMsg').textContent = result.message;
        } else {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            document.getElementById('resultTitle').textContent = '등록 실패';
            document.getElementById('resultMsg').textContent = result.error;
        }
        modal.show();
    } catch (e) {
        alert('오류: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle"></i> 의약품 등록';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);
loadHospitals();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

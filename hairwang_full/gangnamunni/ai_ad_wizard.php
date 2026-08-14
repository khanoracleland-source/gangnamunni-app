<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * AI 광고 생성 마법사
 * 초보자를 위한 단계별 AI 광고 생성 가이드
 */

session_start();

if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (file_exists(__DIR__ . '/ai/openai_config.php')) {
    require_once __DIR__ . '/ai/openai_config.php';
}

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
    loadThemeSettings($connect);
}

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'search_hospitals':
            $keyword = trim($_POST['keyword'] ?? '');
            if (mb_strlen($keyword) < 1) {
                echo json_encode(['success' => true, 'hospitals' => []]);
                exit;
            }
            $keyword_esc = mysqli_real_escape_string($connect, $keyword);
            $sql = "SELECT id, name, address, phone, treatment_tags, categories
                    FROM gangnamunni_hospitals
                    WHERE is_active=1 AND (name LIKE '%{$keyword_esc}%' OR address LIKE '%{$keyword_esc}%')
                    ORDER BY name LIMIT 20";
            $result = mysqli_query($connect, $sql);
            $hospitals_list = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $hospitals_list[] = $row;
            }
            echo json_encode(['success' => true, 'hospitals' => $hospitals_list], JSON_UNESCAPED_UNICODE);
            exit;

        case 'generate_copy':
            $hospital_name = $_POST['hospital_name'] ?? '';
            $treatment = $_POST['treatment'] ?? '';
            $tone = $_POST['tone'] ?? 'professional';
            $target_audience = $_POST['target_audience'] ?? '20-30대 여성';

            if (function_exists('generateAdCopy')) {
                $result = generateAdCopy($hospital_name, $treatment, [
                    'tone' => $tone,
                    'target_audience' => $target_audience,
                ]);
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 모듈이 설정되지 않았습니다']);
            }
            exit;

        case 'generate_image':
            $prompt = $_POST['prompt'] ?? '';
            $size = $_POST['size'] ?? '1024x1024';
            $quality = $_POST['quality'] ?? 'standard';

            if (function_exists('generateAdImage')) {
                $result = generateAdImage($prompt, [
                    'size' => $size,
                    'quality' => $quality,
                ]);
                if ($result && function_exists('saveImageLocally')) {
                    $filename = 'ai_ad_' . date('YmdHis') . '_' . uniqid() . '.png';
                    $local_path = saveImageLocally($result['url'], $filename);
                    if ($local_path) $result['local_path'] = $local_path;
                }
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 모듈이 설정되지 않았습니다']);
            }
            exit;

        case 'save_ad':
            $title = mysqli_real_escape_string($connect, $_POST['title']);
            $subtitle = mysqli_real_escape_string($connect, $_POST['subtitle']);
            $image_url = mysqli_real_escape_string($connect, $_POST['image_url']);
            $link_url = mysqli_real_escape_string($connect, $_POST['link_url']);
            $ad_type = mysqli_real_escape_string($connect, $_POST['ad_type'] ?? 'banner');

            $query = "INSERT INTO gangnamunni_ads (title, subtitle, image_url, link_url, ad_type, is_active)
                      VALUES ('{$title}', '{$subtitle}', '{$image_url}', '{$link_url}', '{$ad_type}', 0)";

            if (mysqli_query($connect, $query)) {
                echo json_encode(['success' => true, 'ad_id' => mysqli_insert_id($connect)]);
            } else {
                echo json_encode(['success' => false, 'message' => '광고 저장 실패']);
            }
            exit;
    }

    echo json_encode(['success' => false, 'message' => '잘못된 요청']);
    exit;
}

$admin_page_title = 'AI 광고 생성 마법사';
$admin_current = 'ai_ad_wizard';
$admin_extra_css = '
    /* 스텝 진행바 */
    .wiz-steps { display:flex; gap:0; margin-bottom:28px; position:relative; }
    .wiz-step { flex:1; text-align:center; position:relative; cursor:pointer; }
    .wiz-step .step-num {
        width:40px; height:40px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
        font-weight:700; font-size:1rem; border:3px solid var(--bs-border-color); background:var(--bs-body-bg);
        color:var(--bs-secondary); position:relative; z-index:2; transition:all 0.3s;
    }
    .wiz-step.active .step-num { border-color:#667eea; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; }
    .wiz-step.done .step-num { border-color:#198754; background:#198754; color:#fff; }
    .wiz-step .step-label { display:block; font-size:0.78rem; margin-top:6px; color:var(--bs-secondary); font-weight:500; }
    .wiz-step.active .step-label { color:var(--bs-body-color); font-weight:600; }
    .wiz-step.done .step-label { color:#198754; }
    .wiz-step::after {
        content:""; position:absolute; top:20px; left:50%; width:100%; height:3px;
        background:var(--bs-border-color); z-index:1;
    }
    .wiz-step:last-child::after { display:none; }
    .wiz-step.done::after { background:#198754; }

    /* 스텝 컨텐츠 */
    .wiz-panel { display:none; }
    .wiz-panel.active { display:block; animation:fadeIn 0.3s ease; }
    @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

    .wiz-card { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:24px; }
    .wiz-card h5 { margin-bottom:4px; }
    .wiz-card .wiz-desc { color:var(--bs-secondary); font-size:0.88rem; margin-bottom:20px; }

    .wiz-nav { display:flex; justify-content:space-between; margin-top:24px; padding-top:16px; border-top:1px solid var(--bs-border-color); }

    /* 병원 검색 드롭다운 */
    .hosp-dropdown { display:none; position:absolute; top:100%; left:0; right:0; z-index:999; max-height:250px; overflow-y:auto; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); margin-top:2px; }
    .hosp-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--bs-border-color); }
    .hosp-item:hover { background:var(--bs-tertiary-bg); }
    .hosp-item:last-child { border-bottom:none; }

    /* 로딩 */
    .wiz-loading { display:none; text-align:center; padding:32px; }
    .wiz-loading.active { display:block; }
    .spinner-wiz { border:3px solid var(--bs-border-color); border-top:3px solid #667eea; border-radius:50%; width:44px; height:44px; animation:spin 1s linear infinite; margin:0 auto 12px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* 미리보기 */
    .wiz-preview { background:var(--bs-tertiary-bg); border-radius:12px; padding:20px; margin-top:16px; display:none; }
    .wiz-preview.show { display:block; }
    .wiz-preview img { max-width:100%; border-radius:8px; }

    /* 완료 화면 */
    .wiz-complete { text-align:center; padding:40px 20px; }
    .wiz-complete .check-icon { font-size:4rem; color:#198754; margin-bottom:16px; }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:767px) { .form-row { grid-template-columns:1fr; } }

    /* 선택된 병원 카드 */
    .selected-hospital { background:linear-gradient(135deg,rgba(102,126,234,0.08),rgba(118,75,162,0.08)); border:2px solid #667eea; border-radius:10px; padding:12px 16px; margin-top:8px; }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-magic"></i> AI 광고 생성 마법사</h5>
    <div class="d-flex gap-2">
        <a href="ai_ad_editor.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders"></i> 고급 모드</a>
        <a href="admin_ads.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 광고 관리</a>
    </div>
</div>

<!-- 스텝 진행바 -->
<div class="wiz-steps">
    <div class="wiz-step active" onclick="goStep(1)">
        <span class="step-num">1</span>
        <span class="step-label">병원 선택</span>
    </div>
    <div class="wiz-step" onclick="goStep(2)">
        <span class="step-num">2</span>
        <span class="step-label">광고 설정</span>
    </div>
    <div class="wiz-step" onclick="goStep(3)">
        <span class="step-num">3</span>
        <span class="step-label">카피 생성</span>
    </div>
    <div class="wiz-step" onclick="goStep(4)">
        <span class="step-num">4</span>
        <span class="step-label">이미지 생성</span>
    </div>
    <div class="wiz-step" onclick="goStep(5)">
        <span class="step-num">5</span>
        <span class="step-label">저장</span>
    </div>
</div>

<div id="wiz-alert" class="alert alert-success" style="display:none;"></div>

<!-- ===== Step 1: 병원 선택 ===== -->
<div class="wiz-panel active" id="step-1">
    <div class="wiz-card">
        <h5><i class="bi bi-hospital me-2"></i>Step 1. 병원을 선택하세요</h5>
        <p class="wiz-desc">광고할 병원을 검색하여 선택합니다. 병원 정보가 자동으로 입력됩니다.</p>

        <div class="mb-3" style="position:relative;">
            <label class="form-label fw-semibold">병원명 검색</label>
            <input type="text" class="form-control form-control-lg" id="w_hospital_search" placeholder="병원명을 입력하세요..." autocomplete="off" oninput="wSearchHospital(this.value)">
            <input type="hidden" id="w_hospital_id">
            <div class="hosp-dropdown" id="w-hosp-dropdown"></div>
        </div>

        <div id="w-selected-hospital" style="display:none;">
            <div class="selected-hospital">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-bold mb-1" id="w-sel-name"></h6>
                        <div class="text-body-secondary small" id="w-sel-addr"></div>
                        <div class="small mt-1" style="color:#667eea;" id="w-sel-tags"></div>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="wClearHospital()"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </div>

        <div class="wiz-nav">
            <div></div>
            <button class="btn btn-primary" onclick="goStep(2)" id="btn-step1-next" disabled>
                다음 단계 <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- ===== Step 2: 광고 설정 ===== -->
<div class="wiz-panel" id="step-2">
    <div class="wiz-card">
        <h5><i class="bi bi-gear me-2"></i>Step 2. 광고 설정</h5>
        <p class="wiz-desc">광고할 시술과 스타일을 설정합니다.</p>

        <div class="mb-3">
            <label class="form-label fw-semibold">시술명 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="w_treatment" placeholder="예: 지방흡입, 필러, 보톡스">
            <div id="w-treatment-tags" style="display:none; margin-top:8px;">
                <div class="form-text mb-1">병원의 시술 태그 (클릭하면 입력됩니다):</div>
                <div id="w-tags-list" class="d-flex flex-wrap gap-1"></div>
            </div>
            <div class="form-text">광고할 시술명을 입력하거나 위 태그를 클릭하세요.</div>
        </div>
        <div class="form-row">
            <div class="mb-3">
                <label class="form-label fw-semibold">톤 & 스타일</label>
                <select class="form-select" id="w_tone">
                    <option value="professional">전문적이고 신뢰감 있는</option>
                    <option value="friendly">친근하고 편안한</option>
                    <option value="luxury">고급스럽고 세련된</option>
                    <option value="energetic">활기차고 젊은</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">타겟 고객</label>
                <select class="form-select" id="w_target">
                    <option value="20-30대 여성">20-30대 여성</option>
                    <option value="30-40대 여성">30-40대 여성</option>
                    <option value="40-50대 여성">40-50대 여성</option>
                    <option value="남성">남성</option>
                    <option value="전 연령">전 연령</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="mb-3">
                <label class="form-label fw-semibold">광고 유형</label>
                <select class="form-select" id="w_ad_type">
                    <option value="banner">배너 광고</option>
                    <option value="premium">프리미엄 광고</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">링크 URL</label>
                <input type="text" class="form-control" id="w_link_url" placeholder="search.php?q=지방흡입" value="#">
            </div>
        </div>

        <div class="wiz-nav">
            <button class="btn btn-outline-secondary" onclick="goStep(1)">
                <i class="bi bi-arrow-left me-1"></i> 이전
            </button>
            <button class="btn btn-primary" onclick="wGenerateCopy()">
                AI 카피 생성 <i class="bi bi-stars ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- ===== Step 3: AI 카피 생성 결과 ===== -->
<div class="wiz-panel" id="step-3">
    <div class="wiz-card">
        <h5><i class="bi bi-pencil-square me-2"></i>Step 3. AI 광고 카피</h5>
        <p class="wiz-desc">AI가 생성한 광고 카피를 확인하고 수정할 수 있습니다.</p>

        <div id="copy-wiz-loading" class="wiz-loading">
            <div class="spinner-wiz"></div>
            <p class="text-body-secondary">AI가 광고 카피를 생성하고 있습니다...</p>
            <p class="text-body-secondary small">잠시만 기다려주세요 (약 5~10초)</p>
        </div>

        <div id="copy-wiz-result" style="display:none;">
            <div class="mb-3">
                <label class="form-label fw-semibold">광고 제목</label>
                <input type="text" class="form-control" id="w_title">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">광고 부제목</label>
                <input type="text" class="form-control" id="w_subtitle">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">상세 설명</label>
                <textarea class="form-control" id="w_description" rows="2"></textarea>
            </div>

            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-outline-primary btn-sm" onclick="wGenerateCopy()"><i class="bi bi-arrow-clockwise"></i> 다시 생성</button>
                <span class="text-body-secondary small align-self-center">결과가 마음에 들지 않으면 다시 생성할 수 있습니다</span>
            </div>
        </div>

        <div class="wiz-nav">
            <button class="btn btn-outline-secondary" onclick="goStep(2)">
                <i class="bi bi-arrow-left me-1"></i> 이전
            </button>
            <button class="btn btn-primary" onclick="goStep(4)" id="btn-step3-next" disabled>
                이미지 생성 <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- ===== Step 4: AI 이미지 생성 ===== -->
<div class="wiz-panel" id="step-4">
    <div class="wiz-card">
        <h5><i class="bi bi-image me-2"></i>Step 4. AI 광고 이미지</h5>
        <p class="wiz-desc">DALL-E로 광고 이미지를 생성합니다. 3단계에서 생성된 카피를 기반으로 프롬프트가 자동 생성됩니다.</p>

        <!-- 3단계 카피 요약 -->
        <div id="step4-copy-summary" class="mb-3" style="background:var(--bs-tertiary-bg); border-radius:8px; padding:12px 16px; border-left:4px solid #667eea;">
            <div class="small text-body-secondary mb-1"><i class="bi bi-pencil-square me-1"></i> 3단계 생성 카피</div>
            <div class="fw-bold" id="s4-title-preview">-</div>
            <div class="text-body-secondary small" id="s4-subtitle-preview">-</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">이미지 프롬프트 (영문 권장)</label>
            <textarea class="form-control" id="w_image_prompt" rows="3" placeholder="예: A modern Korean beauty clinic interior with soft lighting and professional atmosphere"></textarea>
            <div class="form-text">카피 기반으로 자동 생성됩니다. 직접 수정도 가능합니다.</div>
        </div>
        <div class="form-row">
            <div class="mb-3">
                <label class="form-label fw-semibold">이미지 크기</label>
                <select class="form-select" id="w_image_size">
                    <option value="1024x1024">정사각형 (1024x1024)</option>
                    <option value="1792x1024">가로형 (1792x1024)</option>
                    <option value="1024x1792">세로형 (1024x1792)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">품질</label>
                <select class="form-select" id="w_image_quality">
                    <option value="standard">표준 (빠름, 저렴)</option>
                    <option value="hd">HD (고품질, 느림)</option>
                </select>
            </div>
        </div>

        <button class="btn btn-primary" onclick="wGenerateImage()"><i class="bi bi-palette me-1"></i> AI 이미지 생성</button>
        <button class="btn btn-outline-secondary ms-1" onclick="wSkipImage()">이미지 건너뛰기</button>

        <div id="img-wiz-loading" class="wiz-loading">
            <div class="spinner-wiz"></div>
            <p class="text-body-secondary">DALL-E가 이미지를 생성하고 있습니다...</p>
            <p class="text-body-secondary small">약 10~30초 소요됩니다</p>
        </div>

        <div id="img-wiz-result" class="wiz-preview">
            <h6 class="fw-bold mb-2">생성된 이미지</h6>
            <img id="w-gen-image" src="" alt="AI 생성 이미지">
            <p id="w-revised-prompt" class="text-body-secondary small mt-2"></p>
            <div class="mt-2">
                <button class="btn btn-outline-primary btn-sm" onclick="wGenerateImage()"><i class="bi bi-arrow-clockwise"></i> 다시 생성</button>
            </div>
        </div>

        <div class="wiz-nav">
            <button class="btn btn-outline-secondary" onclick="goStep(3)">
                <i class="bi bi-arrow-left me-1"></i> 이전
            </button>
            <button class="btn btn-primary" onclick="goStep(5)" id="btn-step4-next" disabled>
                최종 확인 <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- ===== Step 5: 최종 확인 & 저장 ===== -->
<div class="wiz-panel" id="step-5">
    <div class="wiz-card">
        <h5><i class="bi bi-check2-square me-2"></i>Step 5. 최종 확인 & 저장</h5>
        <p class="wiz-desc">생성된 광고를 확인하고 저장합니다.</p>

        <div style="background:var(--bs-tertiary-bg); border-radius:12px; padding:20px; margin-bottom:16px;">
            <div class="row">
                <div class="col-md-5 mb-3 mb-md-0">
                    <img id="w-final-image" src="" alt="광고 이미지" style="max-width:100%; border-radius:8px; display:none;">
                    <div id="w-no-image" class="text-body-secondary text-center py-4" style="border:2px dashed var(--bs-border-color); border-radius:8px;">
                        <i class="bi bi-image" style="font-size:2rem;"></i>
                        <p class="small mb-0 mt-1">이미지 없음</p>
                    </div>
                </div>
                <div class="col-md-7">
                    <table class="table table-sm mb-0">
                        <tr><th style="width:100px;">병원명</th><td id="w-final-hospital">-</td></tr>
                        <tr><th>시술명</th><td id="w-final-treatment">-</td></tr>
                        <tr><th>제목</th><td><strong id="w-final-title">-</strong></td></tr>
                        <tr><th>부제목</th><td id="w-final-subtitle">-</td></tr>
                        <tr><th>광고 유형</th><td id="w-final-type">-</td></tr>
                        <tr><th>링크</th><td id="w-final-link">-</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="wiz-nav">
            <button class="btn btn-outline-secondary" onclick="goStep(4)">
                <i class="bi bi-arrow-left me-1"></i> 이전
            </button>
            <button class="btn btn-success btn-lg" onclick="wSaveAd()">
                <i class="bi bi-floppy me-1"></i> 광고 저장하기
            </button>
        </div>
    </div>

    <!-- 저장 완료 -->
    <div id="wiz-complete" style="display:none;">
        <div class="wiz-card wiz-complete">
            <div class="check-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h4 class="fw-bold mb-2">광고가 저장되었습니다!</h4>
            <p class="text-body-secondary mb-3">광고 관리 페이지에서 활성화할 수 있습니다.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="admin_ads.php" class="btn btn-primary"><i class="bi bi-list-ul me-1"></i> 광고 관리로 이동</a>
                <a href="ai_ad_wizard.php" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> 새 광고 만들기</a>
            </div>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
var _currentStep = 1;
var _wizData = { hospital_id:'', hospital_name:'', treatment:'', title:'', subtitle:'', description:'', image_url:'', link_url:'#', ad_type:'banner' };

function goStep(n) {
        console.log("[DEBUG] goStep(" + n + ") called, _currentStep=" + _currentStep);
    // 유효성 검사
    if (n > 1 && !_wizData.hospital_name) { alert('먼저 병원을 선택해주세요'); goStep(1); return; }
    if (n > 2 && !document.getElementById('w_treatment').value.trim()) { alert('시술명을 입력해주세요'); goStep(2); return; }
    if (n > 3 && !_wizData.title) { alert('먼저 AI 카피를 생성해주세요'); return; }
    if (n > 4 && !_wizData.image_url && !confirm('이미지 없이 진행하시겠습니까?')) { return; }

    // Step 2 → 데이터 수집
    if (n >= 2) {
        _wizData.treatment = document.getElementById('w_treatment').value.trim();
        _wizData.ad_type = document.getElementById('w_ad_type').value;
        _wizData.link_url = document.getElementById('w_link_url').value || '#';
    }
    // Step 3 → 데이터 수집
    if (n >= 3 && document.getElementById('w_title').value) {
        _wizData.title = document.getElementById('w_title').value;
        _wizData.subtitle = document.getElementById('w_subtitle').value;
        _wizData.description = document.getElementById('w_description').value;
    }

    // Step 4 → 카피 데이터 전달 + 프롬프트 자동 생성
    if (n === 4) {
        // 카피 요약 표시
        document.getElementById('s4-title-preview').textContent = _wizData.title || '-';
        document.getElementById('s4-subtitle-preview').textContent = _wizData.subtitle || '-';

        // 프롬프트가 비어있으면 카피 기반으로 자동 생성
        if (!document.getElementById('w_image_prompt').value.trim()) {
            var autoPrompt = 'Professional Korean beauty clinic advertisement for "' + _wizData.hospital_name + '". '
                + 'Treatment: ' + _wizData.treatment + '. '
                + 'Modern, clean medical aesthetics clinic with soft lighting, elegant interior. '
                + 'High-end beauty and cosmetic surgery atmosphere. '
                + 'Minimalist design, pastel colors, professional and trustworthy feel.';
            document.getElementById('w_image_prompt').value = autoPrompt;
        }
    }

    // Step 5 → 미리보기 업데이트
    if (n === 5) {
        _wizData.title = document.getElementById('w_title').value;
        _wizData.subtitle = document.getElementById('w_subtitle').value;
        document.getElementById('w-final-hospital').textContent = _wizData.hospital_name;
        document.getElementById('w-final-treatment').textContent = _wizData.treatment;
        document.getElementById('w-final-title').textContent = _wizData.title;
        document.getElementById('w-final-subtitle').textContent = _wizData.subtitle;
        document.getElementById('w-final-type').textContent = _wizData.ad_type === 'banner' ? '배너 광고' : '프리미엄 광고';
        document.getElementById('w-final-link').textContent = _wizData.link_url;
        if (_wizData.image_url) {
            document.getElementById('w-final-image').src = _wizData.image_url;
            document.getElementById('w-final-image').style.display = 'block';
            document.getElementById('w-no-image').style.display = 'none';
        } else {
            document.getElementById('w-final-image').style.display = 'none';
            document.getElementById('w-no-image').style.display = 'block';
        }
    }

    _currentStep = n;

    // UI 업데이트
    document.querySelectorAll('.wiz-panel').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('step-' + n).classList.add('active');

    document.querySelectorAll('.wiz-step').forEach(function(s, i) {
        s.classList.remove('active', 'done');
        if (i + 1 === n) s.classList.add('active');
        else if (i + 1 < n) s.classList.add('done');
    });
}

// === 병원 검색 ===
var _wSearchTimer = null;
function wSearchHospital(keyword) {
        console.log("[DEBUG] wSearchHospital:", keyword);
    clearTimeout(_wSearchTimer);
    var dropdown = document.getElementById('w-hosp-dropdown');
    if (keyword.length < 1) { dropdown.style.display = 'none'; return; }

    _wSearchTimer = setTimeout(function() {
        var fd = new FormData();
        fd.append('action', 'search_hospitals');
        fd.append('keyword', keyword);

        fetch('', { method:'POST', body:fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success) return;
            var list = res.hospitals || [];
            if (list.length === 0) {
                dropdown.innerHTML = '<div class="hosp-item text-body-secondary">검색 결과가 없습니다</div>';
                dropdown.style.display = 'block';
                return;
            }
            var html = '';
            list.forEach(function(h) {
                // 태그 표시용 파싱
                var tagDisplay = '';
                if (h.treatment_tags) {
                    try {
                        var tt = JSON.parse(h.treatment_tags);
                        if (Array.isArray(tt)) tagDisplay = tt.map(function(t){return t.name||'';}).filter(Boolean).join(', ');
                    } catch(e) { tagDisplay = h.treatment_tags; }
                }
                html += '<div class="hosp-item" onclick=\'wSelectHospital(' + JSON.stringify(h).replace(/'/g, "&#39;") + ')\'>'
                    + '<div class="fw-semibold">' + esc(h.name) + '</div>'
                    + (h.address ? '<div class="text-body-secondary" style="font-size:0.78rem;">' + esc(h.address) + '</div>' : '')
                    + (tagDisplay ? '<div style="font-size:0.75rem;color:#667eea;">' + esc(tagDisplay) + '</div>' : '')
                    + '</div>';
            });
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
        });
    }, 300);
}

function wSelectHospital(h) {
        console.log("[DEBUG] wSelectHospital:", JSON.stringify(h));
    _wizData.hospital_id = h.id;
    _wizData.hospital_name = h.name;

    document.getElementById('w_hospital_id').value = h.id;
    document.getElementById('w_hospital_search').value = '';
    document.getElementById('w-hosp-dropdown').style.display = 'none';

    // treatment_tags JSON 파싱
    var tagNames = [];
    if (h.treatment_tags) {
        try {
            var tags = JSON.parse(h.treatment_tags);
            if (Array.isArray(tags)) {
                tags.forEach(function(t) { if (t.name) tagNames.push(t.name); });
            }
        } catch(e) {
            // JSON이 아니면 콤마 구분 시도
            tagNames = h.treatment_tags.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        }
    }

    // 선택된 병원 표시
    document.getElementById('w-sel-name').textContent = h.name;
    document.getElementById('w-sel-addr').textContent = h.address || '';
    document.getElementById('w-sel-tags').textContent = tagNames.join(', ');
    document.getElementById('w-selected-hospital').style.display = 'block';

    // Step 2 시술 태그 버튼 생성
    var tagsContainer = document.getElementById('w-tags-list');
    var tagsWrap = document.getElementById('w-treatment-tags');
    if (tagNames.length > 0) {
        var btnsHtml = '';
        tagNames.forEach(function(name) {
            btnsHtml += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="wPickTag(\'' + esc(name).replace(/'/g, "\\'") + '\')">' + esc(name) + '</button>';
        });
        tagsContainer.innerHTML = btnsHtml;
        tagsWrap.style.display = 'block';

        // 첫 태그 자동 입력
        if (!document.getElementById('w_treatment').value) {
            document.getElementById('w_treatment').value = tagNames[0];
        }
    } else {
        tagsContainer.innerHTML = '';
        tagsWrap.style.display = 'none';
    }

    // 다음 버튼 활성화
    document.getElementById('btn-step1-next').disabled = false;
}

function wPickTag(name) {
        console.log("[DEBUG] wPickTag:", name);
    document.getElementById('w_treatment').value = name;
}

function wClearHospital() {
        console.log("[DEBUG] wClearHospital called");
    _wizData.hospital_id = '';
    _wizData.hospital_name = '';
    document.getElementById('w_hospital_id').value = '';
    document.getElementById('w-selected-hospital').style.display = 'none';
    document.getElementById('btn-step1-next').disabled = true;
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#w_hospital_search') && !e.target.closest('#w-hosp-dropdown')) {
        document.getElementById('w-hosp-dropdown').style.display = 'none';
    }
});

// === AI 카피 생성 ===
function wGenerateCopy() {
        console.log("[DEBUG] wGenerateCopy called, _wizData:", JSON.stringify(_wizData));
    var hospital = _wizData.hospital_name;
    var treatment = document.getElementById('w_treatment').value.trim();
    if (!hospital) { alert('병원을 선택해주세요'); goStep(1); return; }
    if (!treatment) { alert('시술명을 입력해주세요'); return; }

    _wizData.treatment = treatment;

    // Step 3으로 이동
    _currentStep = 3;
    document.querySelectorAll('.wiz-panel').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('step-3').classList.add('active');
    document.querySelectorAll('.wiz-step').forEach(function(s, i) {
        s.classList.remove('active', 'done');
        if (i + 1 === 3) s.classList.add('active');
        else if (i + 1 < 3) s.classList.add('done');
    });

    document.getElementById('copy-wiz-loading').classList.add('active');
    document.getElementById('copy-wiz-result').style.display = 'none';
    document.getElementById('btn-step3-next').disabled = true;

    var fd = new FormData();
    fd.append('action', 'generate_copy');
    fd.append('hospital_name', hospital);
    fd.append('treatment', treatment);
    fd.append('tone', document.getElementById('w_tone').value);
    fd.append('target_audience', document.getElementById('w_target').value);

    fetch('', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('copy-wiz-loading').classList.remove('active');
        if (res.success && res.data) {
            var d = res.data;
            document.getElementById('w_title').value = d.title || '';
            document.getElementById('w_subtitle').value = d.subtitle || '';
            document.getElementById('w_description').value = d.description || '';
            _wizData.title = d.title || '';
            _wizData.subtitle = d.subtitle || '';
            _wizData.description = d.description || '';
            document.getElementById('copy-wiz-result').style.display = 'block';
            document.getElementById('btn-step3-next').disabled = false;
        } else {
            alert(res.message || 'AI 카피 생성에 실패했습니다. 다시 시도해주세요.');
        }
    })
    .catch(function() {
        document.getElementById('copy-wiz-loading').classList.remove('active');
        alert('서버 오류가 발생했습니다. 다시 시도해주세요.');
    });
}

// === AI 이미지 생성 ===
function wGenerateImage() {
        console.log("[DEBUG] wGenerateImage called");
    var prompt = document.getElementById('w_image_prompt').value.trim();
    if (!prompt) { alert('이미지 프롬프트를 입력해주세요'); return; }

    document.getElementById('img-wiz-loading').classList.add('active');
    document.getElementById('img-wiz-result').classList.remove('show');

    var fd = new FormData();
    fd.append('action', 'generate_image');
    fd.append('prompt', prompt);
    fd.append('size', document.getElementById('w_image_size').value);
    fd.append('quality', document.getElementById('w_image_quality').value);

    fetch('', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('img-wiz-loading').classList.remove('active');
        if (res.success && res.data) {
            var imgUrl = res.data.local_path || res.data.url;
            document.getElementById('w-gen-image').src = imgUrl;
            document.getElementById('w-revised-prompt').textContent = res.data.revised_prompt ? ('프롬프트: ' + res.data.revised_prompt) : '';
            _wizData.image_url = imgUrl;
            document.getElementById('img-wiz-result').classList.add('show');
            document.getElementById('btn-step4-next').disabled = false;
        } else {
            alert(res.message || '이미지 생성에 실패했습니다. 다시 시도해주세요.');
        }
    })
    .catch(function() {
        document.getElementById('img-wiz-loading').classList.remove('active');
        alert('서버 오류가 발생했습니다. 다시 시도해주세요.');
    });
}

function wSkipImage() {
        console.log("[DEBUG] wSkipImage called");
    _wizData.image_url = '';
    document.getElementById('btn-step4-next').disabled = false;
    goStep(5);
}

// === 광고 저장 ===
function wSaveAd() {
        console.log("[DEBUG] wSaveAd called, _wizData:", JSON.stringify(_wizData));
    if (!_wizData.title) { alert('광고 제목이 없습니다'); return; }

    var fd = new FormData();
    fd.append('action', 'save_ad');
    fd.append('title', _wizData.title);
    fd.append('subtitle', _wizData.subtitle);
    fd.append('image_url', _wizData.image_url);
    fd.append('link_url', _wizData.link_url);
    fd.append('ad_type', _wizData.ad_type);

    fetch('', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            document.querySelector('#step-5 .wiz-card:first-child').style.display = 'none';
            document.getElementById('wiz-complete').style.display = 'block';
            // 모든 스텝 done
            document.querySelectorAll('.wiz-step').forEach(function(s) {
                s.classList.remove('active');
                s.classList.add('done');
            });
        } else {
            alert('저장 실패: ' + (res.message || ''));
        }
    })
    .catch(function() { alert('서버 오류가 발생했습니다'); });
}

function esc(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}


// === DEBUG: Element status check on page load ===
document.addEventListener('DOMContentLoaded', function() {
    console.log('[DEBUG] === DOMContentLoaded ===');

    // Check all SELECT elements
    ['w_tone', 'w_target', 'w_ad_type', 'w_image_size', 'w_image_quality'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            console.log('[DEBUG] SELECT #' + id + ': disabled=' + el.disabled + ', value=' + el.value + ', options=' + el.options.length + ', display=' + getComputedStyle(el).display + ', pointer-events=' + getComputedStyle(el).pointerEvents);
            el.addEventListener('change', function() {
                console.log('[DEBUG] SELECT #' + id + ' changed -> ' + el.value);
            });
            el.addEventListener('mousedown', function() {
                console.log('[DEBUG] SELECT #' + id + ' mousedown');
            });
            el.addEventListener('click', function() {
                console.log('[DEBUG] SELECT #' + id + ' click');
            });
        } else {
            console.warn('[DEBUG] SELECT #' + id + ' NOT FOUND in DOM');
        }
    });

    // Check all INPUT elements
    ['w_hospital_search', 'w_hospital_id', 'w_treatment', 'w_title', 'w_subtitle', 'w_description', 'w_link_url', 'w_image_prompt'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            console.log('[DEBUG] INPUT #' + id + ': type=' + el.type + ', disabled=' + el.disabled + ', readonly=' + el.readOnly);
        } else {
            console.warn('[DEBUG] INPUT #' + id + ' NOT FOUND in DOM');
        }
    });

    // Check Bootstrap
    console.log('[DEBUG] Bootstrap JS:', typeof bootstrap !== 'undefined' ? 'LOADED' : 'NOT LOADED');
    console.log('[DEBUG] Bootstrap CSS:', getComputedStyle(document.body).getPropertyValue('--bs-body-bg') ? 'LOADED' : 'NOT LOADED');

    // Check step visibility
    for (var i = 1; i <= 5; i++) {
        var step = document.getElementById('step' + i);
        if (step) {
            console.log('[DEBUG] #step' + i + ': display=' + getComputedStyle(step).display);
        }
    }

    // Check if any overlay is blocking
    var firstSelect = document.getElementById('w_tone');
    if (firstSelect) {
        var rect = firstSelect.getBoundingClientRect();
        var topEl = document.elementFromPoint(rect.left + rect.width/2, rect.top + rect.height/2);
        console.log('[DEBUG] Element at w_tone position:', topEl ? (topEl.tagName + '#' + topEl.id + '.' + topEl.className) : 'null');
    }

    // Check _wizData
    console.log('[DEBUG] _wizData:', typeof _wizData !== 'undefined' ? JSON.stringify(_wizData) : 'undefined');
    console.log('[DEBUG] _currentStep:', typeof _currentStep !== 'undefined' ? _currentStep : 'undefined');

    console.log('[DEBUG] === Page Ready ===');
});
JSEOF;

if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

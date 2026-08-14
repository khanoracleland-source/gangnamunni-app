<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * AI 이벤트 생성 마법사
 * 5단계: 기본정보 → AI 카피 → 이미지 → 레이아웃/응모 → 미리보기/저장
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}
// admin_events.php의 병원 배정/검증을 우회하는 기존 AI CRUD는 보안 개편 전까지 차단한다.
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'AI 이벤트 기능은 일시 중지되었습니다.']); exit; }
header('Location: admin_events.php'); exit;

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

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($_POST['action']) {
        case 'search_hospitals':
            $kw = trim($_POST['keyword'] ?? '');
            if (mb_strlen($kw) < 1) { echo json_encode(['success'=>true,'hospitals'=>[]]); exit; }
            $esc = mysqli_real_escape_string($connect, $kw);
            $r = mysqli_query($connect, "SELECT id, name, address, phone, treatment_tags, categories FROM gangnamunni_hospitals WHERE is_active=1 AND (name LIKE '%{$esc}%' OR address LIKE '%{$esc}%') ORDER BY name LIMIT 20");
            $list = [];
            while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
            echo json_encode(['success'=>true,'hospitals'=>$list], JSON_UNESCAPED_UNICODE);
            exit;

        case 'generate_event_copy':
            $hospital = $_POST['hospital_name'] ?? '';
            $category = $_POST['category'] ?? '';
            $treatment = $_POST['treatment'] ?? '';
            $tone = $_POST['tone'] ?? 'professional';
            $priceInfo = $_POST['price_info'] ?? '';

            if (function_exists('generateAdCopy')) {
                // AI 카피 생성 - 이벤트용으로 커스텀
                $prompt = "한국 성형/미용 클리닉 이벤트 광고를 작성해주세요.\n"
                    . "병원: {$hospital}\n카테고리: {$category}\n시술: {$treatment}\n가격정보: {$priceInfo}\n톤: {$tone}\n\n"
                    . "다음 형식으로 JSON 응답해주세요:\n"
                    . '{"title":"이벤트 제목","subtitle":"부제목","description":"상세 설명(3-5문장)","cta":"CTA 버튼 텍스트"}';

                $result = generateAdCopy($hospital, $treatment ?: $category, [
                    'tone' => $tone,
                    'target_audience' => '20-40대 여성',
                ]);
                echo json_encode(['success'=>true,'data'=>$result]);
            } else {
                // AI 모듈 없을 때 기본 템플릿
                $templates = [
                    ['title'=>$hospital.' '.($category ?: $treatment).' 특별 이벤트', 'subtitle'=>'지금 바로 특별 혜택을 만나보세요!', 'description'=>$hospital.'에서 준비한 '.($category ?: $treatment).' 특별 이벤트입니다. 한정된 기간 동안 놓칠 수 없는 특별 가격으로 만나보세요.', 'cta'=>'지금 예약하기'],
                    ['title'=>($category ?: $treatment).' 시즌 특가', 'subtitle'=>$hospital.' 단독 프로모션', 'description'=>'이번 시즌, '.$hospital.'에서 특별히 준비한 '.($category ?: $treatment).' 프로모션! 전문 의료진의 섬세한 시술을 합리적인 가격에 경험하세요.', 'cta'=>'혜택 확인하기'],
                ];
                $pick = $templates[array_rand($templates)];
                echo json_encode(['success'=>true,'data'=>$pick]);
            }
            exit;

        case 'generate_event_image':
            $prompt = $_POST['prompt'] ?? '';
            $size = $_POST['size'] ?? '1792x1024';
            $quality = $_POST['quality'] ?? 'standard';

            if (function_exists('generateAdImage')) {
                $result = generateAdImage($prompt, ['size'=>$size, 'quality'=>$quality]);
                if ($result && function_exists('saveImageLocally')) {
                    $fn = 'event_' . date('YmdHis') . '_' . uniqid() . '.png';
                    $lp = saveImageLocally($result['url'], $fn);
                    if ($lp) $result['local_path'] = $lp;
                }
                echo json_encode(['success'=>true,'data'=>$result]);
            } else {
                echo json_encode(['success'=>false,'message'=>'AI 이미지 모듈이 설정되지 않았습니다']);
            }
            exit;

        case 'save_event':
            $title = mysqli_real_escape_string($connect, $_POST['title'] ?? '');
            $subtitle = mysqli_real_escape_string($connect, $_POST['subtitle'] ?? '');
            $description = mysqli_real_escape_string($connect, $_POST['description'] ?? '');
            $layout_type = mysqli_real_escape_string($connect, $_POST['layout_type'] ?? 'mixed');
            $content_html = mysqli_real_escape_string($connect, $_POST['content_html'] ?? '');
            $image_url = mysqli_real_escape_string($connect, $_POST['image_url'] ?? '');
            $hospital_id = intval($_POST['hospital_id'] ?? 0);
            $hospital_name = mysqli_real_escape_string($connect, $_POST['hospital_name'] ?? '');
            $category = mysqli_real_escape_string($connect, $_POST['category'] ?? '');
            $original_price = intval($_POST['original_price'] ?? 0);
            $discount_price = intval($_POST['discount_price'] ?? 0);
            $discount_rate = intval($_POST['discount_rate'] ?? 0);
            $start_date = mysqli_real_escape_string($connect, $_POST['start_date'] ?? date('Y-m-d'));
            $end_date = mysqli_real_escape_string($connect, $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
            $has_raffle = intval($_POST['has_raffle'] ?? 0);
            $raffle_type = mysqli_real_escape_string($connect, $_POST['raffle_type'] ?? '');
            $max_entries = intval($_POST['max_entries'] ?? 0);
            $raffle_end_date = $_POST['raffle_end_date'] ? mysqli_real_escape_string($connect, $_POST['raffle_end_date']) : 'NULL';
            $raffle_prize = mysqli_real_escape_string($connect, $_POST['raffle_prize'] ?? '');
            $raffle_winner_count = intval($_POST['raffle_winner_count'] ?? 1);

            $rdVal = ($raffle_end_date === 'NULL') ? 'NULL' : "'{$raffle_end_date}'";
            $hidVal = $hospital_id > 0 ? $hospital_id : 'NULL';

            $sql = "INSERT INTO gangnamunni_events
                (title, subtitle, description, layout_type, content_html, image_url, hospital_id, hospital_name, category,
                 original_price, discount_price, discount_rate, start_date, end_date,
                 has_raffle, raffle_type, max_entries, raffle_end_date, raffle_prize, raffle_winner_count, is_active)
                VALUES
                ('{$title}', '{$subtitle}', '{$description}', '{$layout_type}', '{$content_html}', '{$image_url}', {$hidVal}, '{$hospital_name}', '{$category}',
                 {$original_price}, {$discount_price}, {$discount_rate}, '{$start_date}', '{$end_date}',
                 {$has_raffle}, '{$raffle_type}', {$max_entries}, {$rdVal}, '{$raffle_prize}', {$raffle_winner_count}, 1)";

            if (mysqli_query($connect, $sql)) {
                $eventId = mysqli_insert_id($connect);

                // 추가 이미지 저장
                $images = json_decode($_POST['images'] ?? '[]', true);
                if (is_array($images)) {
                    foreach ($images as $idx => $img) {
                        $imgUrl = mysqli_real_escape_string($connect, $img['url'] ?? '');
                        $caption = mysqli_real_escape_string($connect, $img['caption'] ?? '');
                        if ($imgUrl) {
                            mysqli_query($connect, "INSERT INTO gangnamunni_event_images (event_id, image_url, caption, display_order) VALUES ({$eventId}, '{$imgUrl}', '{$caption}', {$idx})");
                        }
                    }
                }

                echo json_encode(['success'=>true,'event_id'=>$eventId]);
            } else {
                echo json_encode(['success'=>false,'message'=>'이벤트 저장 실패: '.mysqli_error($connect)]);
            }
            exit;
    }

    echo json_encode(['success'=>false,'message'=>'잘못된 요청']);
    exit;
}

// 카테고리 목록
$categories = ['눈성형','코성형','지방흡입','윤곽','피부관리','리프팅','보톡스/필러','가슴성형','치아','기타'];

$admin_page_title = 'AI 이벤트 생성 마법사';
$admin_current = 'ai_event_wizard';
$admin_extra_css = '
    .wiz-progress { display:flex; justify-content:center; gap:0; margin-bottom:24px; position:relative; }
    .wiz-step-circle { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem; border:2px solid var(--bs-border-color); background:var(--bs-body-bg); color:var(--bs-secondary); position:relative; z-index:1; transition:all 0.3s; }
    .wiz-step-circle.active { border-color:#667eea; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; transform:scale(1.1); }
    .wiz-step-circle.done { border-color:#198754; background:#198754; color:#fff; }
    .wiz-step-item { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; max-width:120px; }
    .wiz-step-label { font-size:0.72rem; text-align:center; color:var(--bs-secondary); }
    .wiz-step-item.active .wiz-step-label { color:var(--bs-primary); font-weight:600; }
    .wiz-connector { flex:1; height:2px; background:var(--bs-border-color); margin-top:20px; max-width:60px; }
    .wiz-connector.done { background:#198754; }
    .wiz-panel { display:none; }
    .wiz-panel.active { display:block; }
    .wiz-card { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:20px; margin-bottom:16px; }
    .form-row-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media(max-width:767px) { .form-row-grid { grid-template-columns:1fr; } }
    .price-preview { background:linear-gradient(135deg,#ff6b35,#ff8c42); color:#fff; padding:12px 16px; border-radius:10px; text-align:center; }
    .price-preview .original { text-decoration:line-through; opacity:0.7; font-size:0.85rem; }
    .price-preview .discount { font-size:1.5rem; font-weight:700; }
    .price-preview .rate { background:rgba(255,255,255,0.3); padding:2px 8px; border-radius:20px; font-size:0.8rem; font-weight:600; margin-left:8px; }
    .layout-option { border:2px solid var(--bs-border-color); border-radius:10px; padding:16px; cursor:pointer; text-align:center; transition:all 0.2s; }
    .layout-option:hover { border-color:var(--bs-primary); }
    .layout-option.selected { border-color:#667eea; background:rgba(102,126,234,0.05); }
    .layout-option .icon { font-size:2rem; margin-bottom:8px; }
    .img-gallery { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
    .img-gallery-item { width:120px; height:90px; border-radius:8px; overflow:hidden; position:relative; border:1px solid var(--bs-border-color); }
    .img-gallery-item img { width:100%; height:100%; object-fit:cover; }
    .img-gallery-item .remove { position:absolute; top:2px; right:2px; background:rgba(0,0,0,0.6); color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:0.7rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .img-gallery-item .caption-input { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.5); padding:2px 4px; }
    .img-gallery-item .caption-input input { background:transparent; border:none; color:#fff; font-size:0.7rem; width:100%; outline:none; }
    .raffle-settings { background:var(--bs-tertiary-bg); border-radius:10px; padding:16px; margin-top:12px; display:none; }
    .raffle-settings.show { display:block; }
    .preview-event { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; overflow:hidden; max-width:400px; margin:0 auto; }
    .preview-event-img { width:100%; aspect-ratio:16/9; object-fit:cover; background:var(--bs-tertiary-bg); }
    .preview-event-body { padding:16px; }
    .loading-spin { display:none; text-align:center; padding:20px; }
    .loading-spin.active { display:block; }
    .spinner-ai { border:3px solid var(--bs-border-color); border-top:3px solid var(--bs-primary); border-radius:50%; width:36px; height:36px; animation:spin 1s linear infinite; margin:0 auto 8px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .tag-btn { display:inline-block; padding:4px 12px; border-radius:20px; border:1px solid var(--bs-border-color); cursor:pointer; font-size:0.82rem; margin:2px; transition:all 0.2s; }
    .tag-btn:hover, .tag-btn.selected { background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> AI 이벤트 생성 마법사</h5>
    <div class="d-flex gap-2">
        <a href="ai_ad_editor.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-robot me-1"></i> AI 에디터</a>
        <a href="admin_ads.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> 광고 관리</a>
    </div>
</div>

<!-- 진행 바 -->
<div class="wiz-progress" id="wiz-progress">
    <div class="wiz-step-item active" data-step="1">
        <div class="wiz-step-circle active">1</div>
        <div class="wiz-step-label">기본 정보</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="2">
        <div class="wiz-step-circle">2</div>
        <div class="wiz-step-label">AI 카피</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="3">
        <div class="wiz-step-circle">3</div>
        <div class="wiz-step-label">이미지</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="4">
        <div class="wiz-step-circle">4</div>
        <div class="wiz-step-label">레이아웃/응모</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="5">
        <div class="wiz-step-circle">5</div>
        <div class="wiz-step-label">저장</div>
    </div>
</div>

<!-- Step 1: 기본 정보 -->
<div class="wiz-panel active" id="step-1">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i> 이벤트 기본 정보</h6>
        <div class="mb-3" style="position:relative;">
            <label class="form-label fw-semibold">병원 선택 <small class="text-body-secondary">(검색)</small></label>
            <input type="text" class="form-control" id="ev_hospital_name" placeholder="병원명 검색..." autocomplete="off" oninput="evSearchHospital(this.value)">
            <input type="hidden" id="ev_hospital_id">
            <div id="ev-hospital-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:999; max-height:200px; overflow-y:auto; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); margin-top:2px;"></div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">카테고리</label>
            <div id="ev-categories">
                <?php foreach ($categories as $cat): ?>
                    <span class="tag-btn" onclick="evSelectCategory(this, '<?= $cat ?>')"><?= $cat ?></span>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="ev_category">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">시술명 <small class="text-body-secondary">(선택)</small></label>
            <input type="text" class="form-control" id="ev_treatment" placeholder="예: 눈매교정, 코끝성형">
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">시작일</label>
                <input type="date" class="form-control" id="ev_start_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">종료일</label>
                <input type="date" class="form-control" id="ev_end_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">원래 가격 <small class="text-body-secondary">(원)</small></label>
                <input type="number" class="form-control" id="ev_original_price" placeholder="2000000" oninput="evCalcDiscount()">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">할인 가격 <small class="text-body-secondary">(원)</small></label>
                <input type="number" class="form-control" id="ev_discount_price" placeholder="1600000" oninput="evCalcDiscount()">
            </div>
        </div>
        <div id="ev-price-preview" style="display:none;">
            <div class="price-preview">
                <div class="original" id="ev-price-orig"></div>
                <div>
                    <span class="discount" id="ev-price-disc"></span>
                    <span class="rate" id="ev-price-rate"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="text-end">
        <button class="btn btn-primary" onclick="evGoStep(2)">다음: AI 카피 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 2: AI 카피 생성 -->
<div class="wiz-panel" id="step-2">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-stars me-1"></i> AI 이벤트 카피 생성</h6>
        <div class="mb-3">
            <label class="form-label fw-semibold">톤 & 스타일</label>
            <select class="form-select" id="ev_tone">
                <option value="professional">전문적이고 신뢰감 있는</option>
                <option value="friendly">친근하고 편안한</option>
                <option value="luxury">고급스럽고 세련된</option>
                <option value="urgent">긴급 할인! 한정 이벤트</option>
                <option value="seasonal">시즌 특별 이벤트</option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm" onclick="evGenerateCopy()"><i class="bi bi-stars me-1"></i> AI 카피 생성</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('ev-manual-copy').style.display='block'"><i class="bi bi-pencil me-1"></i> 직접 입력</button>

        <div id="ev-copy-loading" class="loading-spin">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary small">AI가 이벤트 카피를 생성 중...</p>
        </div>

        <div id="ev-copy-result" style="display:none; margin-top:16px;">
            <div class="mb-3">
                <label class="form-label fw-semibold">이벤트 제목</label>
                <input type="text" class="form-control" id="ev_title" placeholder="이벤트 제목">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">부제목</label>
                <input type="text" class="form-control" id="ev_subtitle" placeholder="부제목">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">상세 설명</label>
                <textarea class="form-control" id="ev_description" rows="4" placeholder="이벤트 상세 설명"></textarea>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="evGenerateCopy()"><i class="bi bi-arrow-clockwise me-1"></i> 다시 생성</button>
        </div>

        <div id="ev-manual-copy" style="display:none; margin-top:16px;">
            <div class="alert alert-info small py-2">직접 입력 모드 - 원하는 내용을 자유롭게 입력하세요</div>
            <div class="mb-3">
                <label class="form-label fw-semibold">이벤트 제목</label>
                <input type="text" class="form-control" id="ev_title_manual" placeholder="예: 여름 맞이 특별 이벤트">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">부제목</label>
                <input type="text" class="form-control" id="ev_subtitle_manual" placeholder="예: 지금 바로 만나보세요!">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">상세 설명</label>
                <textarea class="form-control" id="ev_description_manual" rows="4" placeholder="이벤트 상세 설명"></textarea>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="evGoStep(1)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-primary" onclick="evGoStep(3)">다음: 이미지 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 3: 이미지 생성/업로드 -->
<div class="wiz-panel" id="step-3">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-images me-1"></i> 이벤트 이미지</h6>
        <p class="text-body-secondary small mb-3">메인 이미지와 추가 이미지를 AI로 생성하거나 URL을 입력하세요. 여러 장 등록 가능합니다.</p>

        <!-- AI 이미지 생성 -->
        <div class="mb-3">
            <label class="form-label fw-semibold">AI 이미지 프롬프트 (영문 권장)</label>
            <textarea class="form-control" id="ev_image_prompt" rows="2" placeholder="예: Korean beauty clinic event banner, elegant design, pink and gold color scheme, modern medical aesthetics"></textarea>
        </div>
        <div class="form-row-grid mb-3">
            <div>
                <label class="form-label fw-semibold">크기</label>
                <select class="form-select" id="ev_image_size">
                    <option value="1792x1024">가로형 배너 (1792x1024)</option>
                    <option value="1024x1024">정사각형 (1024x1024)</option>
                    <option value="1024x1792">세로형 (1024x1792)</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">품질</label>
                <select class="form-select" id="ev_image_quality">
                    <option value="standard">표준</option>
                    <option value="hd">HD</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="evGenerateImage()"><i class="bi bi-image me-1"></i> AI 이미지 생성</button>
        <button class="btn btn-outline-secondary btn-sm ms-1" onclick="evAddImageUrl()"><i class="bi bi-link-45deg me-1"></i> URL로 추가</button>

        <div id="ev-img-loading" class="loading-spin">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary small">DALL-E가 이미지를 생성 중...</p>
        </div>

        <!-- 이미지 갤러리 -->
        <div class="mt-3">
            <label class="form-label fw-semibold">등록된 이미지 (<span id="ev-img-count">0</span>장)</label>
            <div class="img-gallery" id="ev-img-gallery"></div>
            <div id="ev-no-images" class="text-body-secondary small py-3 text-center">
                <i class="bi bi-image" style="font-size:2rem;"></i><br>아직 이미지가 없습니다
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="evGoStep(2)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-primary" onclick="evGoStep(4)">다음: 레이아웃/응모 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 4: 레이아웃 선택 + 응모 설정 -->
<div class="wiz-panel" id="step-4">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-layout-text-window me-1"></i> 이벤트 레이아웃</h6>
        <div class="form-row-grid" style="grid-template-columns:1fr 1fr 1fr;">
            <div class="layout-option selected" data-layout="mixed" onclick="evSelectLayout(this,'mixed')">
                <div class="icon"><i class="bi bi-layout-text-window-reverse"></i></div>
                <div class="fw-semibold small">이미지 + 설명</div>
                <div class="text-body-secondary" style="font-size:0.72rem;">이미지와 설명이 번갈아 배치</div>
            </div>
            <div class="layout-option" data-layout="multi_image" onclick="evSelectLayout(this,'multi_image')">
                <div class="icon"><i class="bi bi-images"></i></div>
                <div class="fw-semibold small">슬라이드 갤러리</div>
                <div class="text-body-secondary" style="font-size:0.72rem;">이미지 캐러셀 + 하단 설명</div>
            </div>
            <div class="layout-option" data-layout="full_image" onclick="evSelectLayout(this,'full_image')">
                <div class="icon"><i class="bi bi-phone"></i></div>
                <div class="fw-semibold small">통 이미지</div>
                <div class="text-body-secondary" style="font-size:0.72rem;">풀스크린 이미지 스크롤</div>
            </div>
        </div>
    </div>

    <div class="wiz-card">
        <h6 class="fw-bold mb-3">
            <i class="bi bi-gift me-1"></i> 응모 기능
            <div class="form-check form-switch d-inline-block ms-3">
                <input class="form-check-input" type="checkbox" id="ev_has_raffle" onchange="document.getElementById('ev-raffle-settings').classList.toggle('show', this.checked)">
                <label class="form-check-label small" for="ev_has_raffle">응모 기능 사용</label>
            </div>
        </h6>

        <div class="raffle-settings" id="ev-raffle-settings">
            <div class="form-row-grid">
                <div class="mb-3">
                    <label class="form-label fw-semibold">응모 유형</label>
                    <select class="form-select" id="ev_raffle_type">
                        <option value="lottery">추첨 (랜덤 당첨)</option>
                        <option value="first_come">선착순</option>
                        <option value="quiz">퀴즈 참여</option>
                        <option value="share">공유 이벤트</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">당첨자 수</label>
                    <input type="number" class="form-control" id="ev_raffle_winner_count" value="1" min="1">
                </div>
            </div>
            <div class="form-row-grid">
                <div class="mb-3">
                    <label class="form-label fw-semibold">최대 응모자 수 <small class="text-body-secondary">(0=무제한)</small></label>
                    <input type="number" class="form-control" id="ev_max_entries" value="0" min="0">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">응모 마감일</label>
                    <input type="date" class="form-control" id="ev_raffle_end_date">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">경품/혜택</label>
                <textarea class="form-control" id="ev_raffle_prize" rows="2" placeholder="예: 시술 50% 할인권, 피부관리 무료 이용권 등"></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="evGoStep(3)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-primary" onclick="evGoStep(5)">다음: 미리보기 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 5: 미리보기 + 저장 -->
<div class="wiz-panel" id="step-5">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-eye me-1"></i> 이벤트 미리보기</h6>

        <div class="preview-event" id="ev-preview">
            <div style="position:relative;">
                <img id="ev-preview-img" class="preview-event-img" src="" alt="">
                <div id="ev-preview-noimg" style="width:100%;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:var(--bs-tertiary-bg);color:var(--bs-secondary);font-size:2rem;"><i class="bi bi-image"></i></div>
                <div id="ev-preview-rate-badge" style="display:none;position:absolute;top:12px;right:12px;background:#ff3b5c;color:#fff;padding:4px 12px;border-radius:20px;font-weight:700;font-size:0.88rem;"></div>
            </div>
            <div class="preview-event-body">
                <div id="ev-preview-category" class="small mb-1"></div>
                <h6 class="fw-bold" id="ev-preview-title">이벤트 제목</h6>
                <p class="text-body-secondary small" id="ev-preview-subtitle">부제목</p>
                <div id="ev-preview-price-area" style="display:none;" class="mb-2">
                    <span class="text-decoration-line-through text-body-secondary small" id="ev-preview-orig-price"></span>
                    <span class="fw-bold text-danger ms-1" id="ev-preview-disc-price"></span>
                </div>
                <div class="text-body-secondary small" id="ev-preview-date"></div>
                <div id="ev-preview-hospital" class="small mt-1"></div>
                <div id="ev-preview-raffle-badge" style="display:none;" class="mt-2">
                    <span class="badge bg-danger"><i class="bi bi-gift me-1"></i> 응모 이벤트</span>
                </div>
            </div>
        </div>

        <!-- 상세 정보 테이블 -->
        <table class="table table-sm mt-3" style="font-size:0.84rem;">
            <tbody id="ev-preview-table"></tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="evGoStep(4)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-success btn-lg" onclick="evSaveEvent()"><i class="bi bi-check-circle me-1"></i> 이벤트 저장</button>
    </div>
</div>

<!-- 성공 메시지 -->
<div id="ev-success" class="text-center py-5" style="display:none;">
    <div style="font-size:4rem;color:#198754;"><i class="bi bi-check-circle-fill"></i></div>
    <h4 class="fw-bold mt-3">이벤트가 생성되었습니다!</h4>
    <p class="text-body-secondary" id="ev-success-msg"></p>
    <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="ai_event_wizard.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> 새 이벤트 만들기</a>
        <a href="admin_ads.php" class="btn btn-outline-secondary"><i class="bi bi-list me-1"></i> 목록으로</a>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
var _evData = {
    hospital_id: '', hospital_name: '', category: '', treatment: '',
    start_date: '', end_date: '', original_price: 0, discount_price: 0, discount_rate: 0,
    title: '', subtitle: '', description: '',
    images: [], // [{url:'', caption:''}]
    layout_type: 'mixed',
    has_raffle: 0, raffle_type: 'lottery', raffle_winner_count: 1,
    max_entries: 0, raffle_end_date: '', raffle_prize: ''
};

// === Step 네비게이션 ===
function evGoStep(n) {
    // Step 데이터 수집
    if (n > 1) evCollectStep1();
    if (n > 2) evCollectStep2();
    if (n > 3) evCollectStep3();
    if (n > 4) evCollectStep4();
    if (n === 5) evBuildPreview();

    // UI 전환
    document.querySelectorAll('.wiz-panel').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('step-' + n).classList.add('active');

    // 진행바 업데이트
    document.querySelectorAll('.wiz-step-item').forEach(function(item) {
        var s = parseInt(item.getAttribute('data-step'));
        var circle = item.querySelector('.wiz-step-circle');
        item.classList.remove('active');
        circle.classList.remove('active', 'done');
        if (s < n) { circle.classList.add('done'); }
        else if (s === n) { item.classList.add('active'); circle.classList.add('active'); }
    });
    document.querySelectorAll('.wiz-connector').forEach(function(c, i) {
        c.classList.toggle('done', i < n - 1);
    });

    window.scrollTo({top: 0, behavior: 'smooth'});
}

function evCollectStep1() {
    _evData.hospital_id = document.getElementById('ev_hospital_id').value;
    _evData.hospital_name = document.getElementById('ev_hospital_name').value;
    _evData.category = document.getElementById('ev_category').value;
    _evData.treatment = document.getElementById('ev_treatment').value;
    _evData.start_date = document.getElementById('ev_start_date').value;
    _evData.end_date = document.getElementById('ev_end_date').value;
    _evData.original_price = parseInt(document.getElementById('ev_original_price').value) || 0;
    _evData.discount_price = parseInt(document.getElementById('ev_discount_price').value) || 0;
    evCalcDiscount();

    // Step 3 이미지 프롬프트 자동 세팅
    var promptEl = document.getElementById('ev_image_prompt');
    if (!promptEl.value.trim()) {
        promptEl.value = 'Professional Korean beauty clinic event banner. '
            + 'Treatment: ' + (_evData.treatment || _evData.category) + '. '
            + 'Elegant design with pink and gold accents. '
            + 'Modern medical aesthetics, soft lighting. '
            + 'Clean typography space for text overlay. Wide banner format.';
    }
}

function evCollectStep2() {
    // AI 결과 또는 수동 입력 병합
    var aiTitle = document.getElementById('ev_title').value;
    var manTitle = document.getElementById('ev_title_manual').value;
    _evData.title = aiTitle || manTitle || '';
    _evData.subtitle = document.getElementById('ev_subtitle').value || document.getElementById('ev_subtitle_manual').value || '';
    _evData.description = document.getElementById('ev_description').value || document.getElementById('ev_description_manual').value || '';
}

function evCollectStep3() {
    // images는 실시간으로 _evData.images에 추가되므로 여기선 확인만
}

function evCollectStep4() {
    _evData.layout_type = document.querySelector('.layout-option.selected')?.getAttribute('data-layout') || 'mixed';
    _evData.has_raffle = document.getElementById('ev_has_raffle').checked ? 1 : 0;
    if (_evData.has_raffle) {
        _evData.raffle_type = document.getElementById('ev_raffle_type').value;
        _evData.raffle_winner_count = parseInt(document.getElementById('ev_raffle_winner_count').value) || 1;
        _evData.max_entries = parseInt(document.getElementById('ev_max_entries').value) || 0;
        _evData.raffle_end_date = document.getElementById('ev_raffle_end_date').value;
        _evData.raffle_prize = document.getElementById('ev_raffle_prize').value;
    }
}

// === Step 1: 병원 검색 ===
var _evSearchTimer = null;
function evSearchHospital(kw) {
    clearTimeout(_evSearchTimer);
    var dd = document.getElementById('ev-hospital-dropdown');
    if (kw.length < 1) { dd.style.display = 'none'; return; }
    _evSearchTimer = setTimeout(function() {
        var fd = new FormData();
        fd.append('action', 'search_hospitals');
        fd.append('keyword', kw);
        fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
            if (!res.success) return;
            var list = res.hospitals || [];
            if (list.length === 0) { dd.innerHTML = '<div style="padding:10px 14px;color:var(--bs-secondary);font-size:0.85rem;">검색 결과 없음</div>'; dd.style.display = 'block'; return; }
            var html = '';
            list.forEach(function(h) {
                html += '<div style="padding:8px 14px;cursor:pointer;border-bottom:1px solid var(--bs-border-color);font-size:0.88rem;" onmouseover="this.style.background=\'var(--bs-tertiary-bg)\'" onmouseout="this.style.background=\'transparent\'" onclick=\'evPickHospital(' + JSON.stringify(h).replace(/'/g,"&#39;") + ')\'>'
                    + '<div class="fw-semibold">' + esc(h.name) + '</div>'
                    + (h.address ? '<div style="font-size:0.75rem;color:var(--bs-secondary);">' + esc(h.address) + '</div>' : '')
                    + '</div>';
            });
            dd.innerHTML = html;
            dd.style.display = 'block';
        });
    }, 300);
}

function evPickHospital(h) {
    document.getElementById('ev_hospital_name').value = h.name;
    document.getElementById('ev_hospital_id').value = h.id;
    document.getElementById('ev-hospital-dropdown').style.display = 'none';
    // 시술 자동 입력
    if (h.treatment_tags && !document.getElementById('ev_treatment').value) {
        try {
            var tags = JSON.parse(h.treatment_tags);
            if (Array.isArray(tags) && tags.length > 0 && tags[0].name) {
                document.getElementById('ev_treatment').value = tags[0].name;
            }
        } catch(e) {}
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#ev_hospital_name') && !e.target.closest('#ev-hospital-dropdown')) {
        document.getElementById('ev-hospital-dropdown').style.display = 'none';
    }
});

function evSelectCategory(el, cat) {
    document.querySelectorAll('#ev-categories .tag-btn').forEach(function(b){b.classList.remove('selected');});
    el.classList.add('selected');
    document.getElementById('ev_category').value = cat;
}

function evCalcDiscount() {
    var orig = parseInt(document.getElementById('ev_original_price').value) || 0;
    var disc = parseInt(document.getElementById('ev_discount_price').value) || 0;
    var preview = document.getElementById('ev-price-preview');
    if (orig > 0 && disc > 0 && disc < orig) {
        var rate = Math.round((1 - disc / orig) * 100);
        _evData.discount_rate = rate;
        document.getElementById('ev-price-orig').textContent = orig.toLocaleString() + '원';
        document.getElementById('ev-price-disc').textContent = disc.toLocaleString() + '원';
        document.getElementById('ev-price-rate').textContent = rate + '% OFF';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// === Step 2: AI 카피 ===
function evGenerateCopy() {
    evCollectStep1();
    var loading = document.getElementById('ev-copy-loading');
    loading.classList.add('active');
    document.getElementById('ev-copy-result').style.display = 'none';

    var priceInfo = '';
    if (_evData.original_price > 0) {
        priceInfo = _evData.original_price.toLocaleString() + '원 → ' + _evData.discount_price.toLocaleString() + '원 (' + _evData.discount_rate + '% 할인)';
    }

    var fd = new FormData();
    fd.append('action', 'generate_event_copy');
    fd.append('hospital_name', _evData.hospital_name);
    fd.append('category', _evData.category);
    fd.append('treatment', _evData.treatment);
    fd.append('tone', document.getElementById('ev_tone').value);
    fd.append('price_info', priceInfo);

    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        loading.classList.remove('active');
        if (res.success && res.data) {
            document.getElementById('ev_title').value = res.data.title || '';
            document.getElementById('ev_subtitle').value = res.data.subtitle || '';
            document.getElementById('ev_description').value = res.data.description || '';
            document.getElementById('ev-copy-result').style.display = 'block';
            document.getElementById('ev-manual-copy').style.display = 'none';
        } else {
            alert(res.message || '카피 생성 실패');
        }
    }).catch(function() {
        loading.classList.remove('active');
        alert('서버 오류');
    });
}

// === Step 3: 이미지 ===
function evGenerateImage() {
    var prompt = document.getElementById('ev_image_prompt').value.trim();
    if (!prompt) { alert('이미지 프롬프트를 입력해주세요'); return; }

    var loading = document.getElementById('ev-img-loading');
    loading.classList.add('active');

    var fd = new FormData();
    fd.append('action', 'generate_event_image');
    fd.append('prompt', prompt);
    fd.append('size', document.getElementById('ev_image_size').value);
    fd.append('quality', document.getElementById('ev_image_quality').value);

    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        loading.classList.remove('active');
        if (res.success && res.data) {
            var url = res.data.local_path || res.data.url;
            evAddImage(url, '');
        } else {
            alert(res.message || '이미지 생성 실패');
        }
    }).catch(function() {
        loading.classList.remove('active');
        alert('서버 오류');
    });
}

function evAddImageUrl() {
    var url = prompt('이미지 URL을 입력하세요:');
    if (url && url.trim()) evAddImage(url.trim(), '');
}

function evAddImage(url, caption) {
    _evData.images.push({url: url, caption: caption || ''});
    evRenderGallery();
}

function evRemoveImage(idx) {
    _evData.images.splice(idx, 1);
    evRenderGallery();
}

function evUpdateCaption(idx, val) {
    if (_evData.images[idx]) _evData.images[idx].caption = val;
}

function evRenderGallery() {
    var gallery = document.getElementById('ev-img-gallery');
    var noImg = document.getElementById('ev-no-images');
    document.getElementById('ev-img-count').textContent = _evData.images.length;

    if (_evData.images.length === 0) {
        gallery.innerHTML = '';
        noImg.style.display = 'block';
        return;
    }
    noImg.style.display = 'none';

    var html = '';
    _evData.images.forEach(function(img, i) {
        html += '<div class="img-gallery-item">'
            + '<img src="' + esc(img.url) + '" onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%2290%22><rect fill=%22%23eee%22 width=%22120%22 height=%2290%22/><text x=%2260%22 y=%2250%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2212%22>Error</text></svg>\'">'
            + '<button class="remove" onclick="evRemoveImage(' + i + ')" title="삭제">&times;</button>'
            + '<div class="caption-input"><input type="text" placeholder="캡션" value="' + esc(img.caption) + '" onchange="evUpdateCaption(' + i + ', this.value)"></div>'
            + '</div>';
    });
    gallery.innerHTML = html;
}

// === Step 4: 레이아웃 선택 ===
function evSelectLayout(el, layout) {
    document.querySelectorAll('.layout-option').forEach(function(o){o.classList.remove('selected');});
    el.classList.add('selected');
    _evData.layout_type = layout;
}

// === Step 5: 미리보기 ===
function evBuildPreview() {
    // 이미지
    var imgEl = document.getElementById('ev-preview-img');
    var noImgEl = document.getElementById('ev-preview-noimg');
    if (_evData.images.length > 0) {
        imgEl.src = _evData.images[0].url;
        imgEl.style.display = 'block';
        noImgEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none';
        noImgEl.style.display = 'flex';
    }

    // 할인율 배지
    var rateBadge = document.getElementById('ev-preview-rate-badge');
    if (_evData.discount_rate > 0) {
        rateBadge.textContent = _evData.discount_rate + '% OFF';
        rateBadge.style.display = 'block';
    } else {
        rateBadge.style.display = 'none';
    }

    // 텍스트
    document.getElementById('ev-preview-category').innerHTML = _evData.category ? '<span class="badge bg-primary">' + esc(_evData.category) + '</span>' : '';
    document.getElementById('ev-preview-title').textContent = _evData.title || '제목 없음';
    document.getElementById('ev-preview-subtitle').textContent = _evData.subtitle || '';

    // 가격
    var priceArea = document.getElementById('ev-preview-price-area');
    if (_evData.original_price > 0) {
        document.getElementById('ev-preview-orig-price').textContent = _evData.original_price.toLocaleString() + '원';
        document.getElementById('ev-preview-disc-price').textContent = (_evData.discount_price || _evData.original_price).toLocaleString() + '원';
        priceArea.style.display = 'block';
    } else {
        priceArea.style.display = 'none';
    }

    // 날짜
    document.getElementById('ev-preview-date').textContent = _evData.start_date + ' ~ ' + _evData.end_date;

    // 병원
    document.getElementById('ev-preview-hospital').innerHTML = _evData.hospital_name ? '<i class="bi bi-hospital me-1"></i>' + esc(_evData.hospital_name) : '';

    // 응모 배지
    document.getElementById('ev-preview-raffle-badge').style.display = _evData.has_raffle ? 'block' : 'none';

    // 상세 테이블
    var layoutLabels = {mixed:'이미지+설명 혼합', multi_image:'슬라이드 갤러리', full_image:'통 이미지'};
    var raffleLabels = {lottery:'추첨', first_come:'선착순', quiz:'퀴즈', share:'공유'};
    var rows = [
        ['병원', _evData.hospital_name || '-'],
        ['카테고리', _evData.category || '-'],
        ['시술', _evData.treatment || '-'],
        ['기간', _evData.start_date + ' ~ ' + _evData.end_date],
        ['이미지', _evData.images.length + '장'],
        ['레이아웃', layoutLabels[_evData.layout_type] || _evData.layout_type],
    ];
    if (_evData.original_price > 0) {
        rows.push(['가격', _evData.original_price.toLocaleString() + '원 → ' + (_evData.discount_price || _evData.original_price).toLocaleString() + '원 (' + _evData.discount_rate + '% 할인)']);
    }
    if (_evData.has_raffle) {
        rows.push(['응모 유형', raffleLabels[_evData.raffle_type] || _evData.raffle_type]);
        rows.push(['당첨자 수', _evData.raffle_winner_count + '명']);
        if (_evData.raffle_prize) rows.push(['경품', _evData.raffle_prize]);
    }

    var tHtml = '';
    rows.forEach(function(r) {
        tHtml += '<tr><th class="text-body-secondary" style="width:100px;">' + r[0] + '</th><td>' + esc(r[1]) + '</td></tr>';
    });
    document.getElementById('ev-preview-table').innerHTML = tHtml;
}

// === 이벤트 저장 ===
function evSaveEvent() {
    if (!_evData.title) { alert('이벤트 제목이 필요합니다. Step 2에서 입력해주세요.'); return; }

    var fd = new FormData();
    fd.append('action', 'save_event');
    fd.append('title', _evData.title);
    fd.append('subtitle', _evData.subtitle);
    fd.append('description', _evData.description);
    fd.append('layout_type', _evData.layout_type);
    fd.append('content_html', '');
    fd.append('image_url', _evData.images.length > 0 ? _evData.images[0].url : '');
    fd.append('hospital_id', _evData.hospital_id || '0');
    fd.append('hospital_name', _evData.hospital_name);
    fd.append('category', _evData.category);
    fd.append('original_price', _evData.original_price);
    fd.append('discount_price', _evData.discount_price);
    fd.append('discount_rate', _evData.discount_rate);
    fd.append('start_date', _evData.start_date);
    fd.append('end_date', _evData.end_date);
    fd.append('has_raffle', _evData.has_raffle);
    fd.append('raffle_type', _evData.raffle_type);
    fd.append('max_entries', _evData.max_entries);
    fd.append('raffle_end_date', _evData.raffle_end_date || '');
    fd.append('raffle_prize', _evData.raffle_prize);
    fd.append('raffle_winner_count', _evData.raffle_winner_count);
    fd.append('images', JSON.stringify(_evData.images));

    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        if (res.success) {
            // 성공 UI
            document.querySelectorAll('.wiz-panel, .wiz-progress').forEach(function(el){el.style.display='none';});
            document.getElementById('ev-success').style.display = 'block';
            document.getElementById('ev-success-msg').textContent = '이벤트 ID: ' + res.event_id + ' | ' + _evData.title;
        } else {
            alert(res.message || '이벤트 저장 실패');
        }
    }).catch(function() { alert('서버 오류'); });
}

function esc(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
JSEOF;

if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

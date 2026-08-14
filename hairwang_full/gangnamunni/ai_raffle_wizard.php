<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * AI 응모 마법사
 * 4단계: 이벤트 선택 → 응모 설정 → AI 홍보문 생성 → 미리보기/저장
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'AI 응모 기능은 일시 중지되었습니다.']); exit; }
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
        case 'search_events':
            $kw = trim($_POST['keyword'] ?? '');
            $where = 'is_active=1';
            if ($kw) {
                $esc = mysqli_real_escape_string($connect, $kw);
                $where .= " AND (title LIKE '%{$esc}%' OR hospital_name LIKE '%{$esc}%' OR category LIKE '%{$esc}%')";
            }
            $r = mysqli_query($connect, "SELECT id, title, subtitle, hospital_name, category, image_url, start_date, end_date, discount_rate, has_raffle
                FROM gangnamunni_events WHERE {$where} ORDER BY id DESC LIMIT 20");
            $list = [];
            while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
            echo json_encode(['success'=>true, 'events'=>$list], JSON_UNESCAPED_UNICODE);
            exit;

        case 'load_event':
            $id = intval($_POST['event_id'] ?? 0);
            $r = mysqli_query($connect, "SELECT * FROM gangnamunni_events WHERE id={$id}");
            $ev = mysqli_fetch_assoc($r);
            if ($ev) {
                // 이미지도 가져오기
                $imgR = mysqli_query($connect, "SELECT * FROM gangnamunni_event_images WHERE event_id={$id} ORDER BY display_order");
                $imgs = [];
                while ($img = mysqli_fetch_assoc($imgR)) $imgs[] = $img;
                $ev['images'] = $imgs;
                // 기존 응모 수
                $cntR = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_event_entries WHERE event_id={$id}");
                $ev['entry_count'] = mysqli_fetch_assoc($cntR)['cnt'];
                echo json_encode(['success'=>true, 'event'=>$ev], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success'=>false, 'message'=>'이벤트를 찾을 수 없습니다']);
            }
            exit;

        case 'generate_raffle_copy':
            $eventTitle = $_POST['event_title'] ?? '';
            $hospital = $_POST['hospital_name'] ?? '';
            $prize = $_POST['prize'] ?? '';
            $raffleType = $_POST['raffle_type'] ?? 'lottery';
            $deadline = $_POST['deadline'] ?? '';

            if (function_exists('generateAdCopy')) {
                $result = generateAdCopy($hospital, $eventTitle, [
                    'tone' => 'urgent',
                    'target_audience' => '20-40대 여성',
                ]);
                echo json_encode(['success'=>true, 'data'=>$result]);
            } else {
                // 기본 템플릿
                $typeLabels = ['lottery'=>'추첨', 'first_come'=>'선착순', 'quiz'=>'퀴즈 참여', 'share'=>'공유'];
                $typeLabel = $typeLabels[$raffleType] ?? '참여';
                $templates = [
                    [
                        'title'=>'🎁 ' . $eventTitle . ' 응모 이벤트',
                        'subtitle'=>$typeLabel . '으로 경품을 받아가세요!',
                        'description'=>$hospital . '에서 준비한 특별 응모 이벤트! ' . $prize . '를 ' . $typeLabel . '으로 드립니다. '
                            . ($deadline ? $deadline . '까지 참여하세요!' : '지금 바로 참여하세요!'),
                        'cta'=>'지금 응모하기'
                    ],
                    [
                        'title'=>'놓치면 후회! ' . ($prize ? $prize . ' 응모' : '특별 응모'),
                        'subtitle'=>$hospital . ' 단독 ' . $typeLabel . ' 이벤트',
                        'description'=>'한정된 기회! ' . $eventTitle . ' 참여만 하면 ' . ($prize ?: '특별 경품') . '을 받을 수 있는 절호의 기회. '
                            . $typeLabel . ' 방식으로 공정하게 진행됩니다.',
                        'cta'=>'응모하기'
                    ],
                ];
                echo json_encode(['success'=>true, 'data'=>$templates[array_rand($templates)]]);
            }
            exit;

        case 'save_raffle':
            $eventId = intval($_POST['event_id'] ?? 0);
            $raffleType = mysqli_real_escape_string($connect, $_POST['raffle_type'] ?? 'lottery');
            $winnerCount = intval($_POST['winner_count'] ?? 1);
            $maxEntries = intval($_POST['max_entries'] ?? 0);
            $endDate = $_POST['raffle_end_date'] ? "'" . mysqli_real_escape_string($connect, $_POST['raffle_end_date']) . "'" : 'NULL';
            $prize = mysqli_real_escape_string($connect, $_POST['prize'] ?? '');
            $promoTitle = mysqli_real_escape_string($connect, $_POST['promo_title'] ?? '');
            $promoSubtitle = mysqli_real_escape_string($connect, $_POST['promo_subtitle'] ?? '');
            $promoDesc = mysqli_real_escape_string($connect, $_POST['promo_description'] ?? '');

            if ($eventId > 0) {
                // 기존 이벤트에 응모 설정 업데이트
                $sql = "UPDATE gangnamunni_events SET
                    has_raffle=1, raffle_type='{$raffleType}',
                    raffle_winner_count={$winnerCount}, max_entries={$maxEntries},
                    raffle_end_date={$endDate}, raffle_prize='{$prize}'
                    WHERE id={$eventId}";
                mysqli_query($connect, $sql);
                echo json_encode(['success'=>true, 'event_id'=>$eventId, 'mode'=>'updated']);
            } else {
                // 신규 이벤트로 생성
                $title = $promoTitle ?: '응모 이벤트';
                $subtitle = $promoSubtitle ?: '';
                $description = $promoDesc ?: '';
                $sql = "INSERT INTO gangnamunni_events (title, subtitle, description, has_raffle, raffle_type,
                    raffle_winner_count, max_entries, raffle_end_date, raffle_prize, is_active)
                    VALUES ('{$title}', '{$subtitle}', '{$description}', 1, '{$raffleType}',
                    {$winnerCount}, {$maxEntries}, {$endDate}, '{$prize}', 1)";
                if (mysqli_query($connect, $sql)) {
                    echo json_encode(['success'=>true, 'event_id'=>mysqli_insert_id($connect), 'mode'=>'created']);
                } else {
                    echo json_encode(['success'=>false, 'message'=>'저장 실패: '.mysqli_error($connect)]);
                }
            }
            exit;

        case 'list_entries':
            $eventId = intval($_POST['event_id'] ?? 0);
            $r = mysqli_query($connect, "SELECT * FROM gangnamunni_event_entries WHERE event_id={$eventId} ORDER BY id DESC LIMIT 100");
            $list = [];
            while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
            echo json_encode(['success'=>true, 'entries'=>$list], JSON_UNESCAPED_UNICODE);
            exit;

        case 'draw_winners':
            $eventId = intval($_POST['event_id'] ?? 0);
            $count = intval($_POST['count'] ?? 1);
            // 아직 당첨되지 않은 참가자 중 랜덤 추첨
            mysqli_query($connect, "UPDATE gangnamunni_event_entries SET is_winner=0 WHERE event_id={$eventId}");
            $r = mysqli_query($connect, "SELECT id FROM gangnamunni_event_entries WHERE event_id={$eventId} ORDER BY RAND() LIMIT {$count}");
            $winnerIds = [];
            while ($row = mysqli_fetch_assoc($r)) $winnerIds[] = $row['id'];
            if (count($winnerIds) > 0) {
                $idStr = implode(',', $winnerIds);
                mysqli_query($connect, "UPDATE gangnamunni_event_entries SET is_winner=1 WHERE id IN ({$idStr})");
            }
            echo json_encode(['success'=>true, 'winners'=>count($winnerIds)]);
            exit;
    }

    echo json_encode(['success'=>false,'message'=>'잘못된 요청']);
    exit;
}

$admin_page_title = 'AI 응모 마법사';
$admin_current = 'ai_raffle_wizard';
$admin_extra_css = '
    .wiz-progress { display:flex; justify-content:center; gap:0; margin-bottom:24px; }
    .wiz-step-circle { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem; border:2px solid var(--bs-border-color); background:var(--bs-body-bg); color:var(--bs-secondary); z-index:1; transition:all 0.3s; }
    .wiz-step-circle.active { border-color:#ff3b5c; background:linear-gradient(135deg,#ff3b5c,#ff6b35); color:#fff; transform:scale(1.1); }
    .wiz-step-circle.done { border-color:#198754; background:#198754; color:#fff; }
    .wiz-step-item { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; max-width:120px; }
    .wiz-step-label { font-size:0.72rem; text-align:center; color:var(--bs-secondary); }
    .wiz-step-item.active .wiz-step-label { color:#ff3b5c; font-weight:600; }
    .wiz-connector { flex:1; height:2px; background:var(--bs-border-color); margin-top:20px; max-width:80px; }
    .wiz-connector.done { background:#198754; }
    .wiz-panel { display:none; }
    .wiz-panel.active { display:block; }
    .wiz-card { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:20px; margin-bottom:16px; }
    .form-row-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media(max-width:767px) { .form-row-grid { grid-template-columns:1fr; } }

    .event-pick-card { border:2px solid var(--bs-border-color); border-radius:10px; padding:12px; cursor:pointer; transition:all 0.2s; display:flex; gap:12px; align-items:center; margin-bottom:8px; }
    .event-pick-card:hover { border-color:var(--bs-primary); background:var(--bs-tertiary-bg); }
    .event-pick-card.selected { border-color:#ff3b5c; background:rgba(255,59,92,0.05); }
    .event-pick-thumb { width:80px; height:60px; border-radius:8px; object-fit:cover; background:var(--bs-tertiary-bg); flex-shrink:0; }
    .event-pick-thumb-empty { width:80px; height:60px; border-radius:8px; background:var(--bs-tertiary-bg); display:flex; align-items:center; justify-content:center; color:var(--bs-secondary); flex-shrink:0; }

    .raffle-type-card { border:2px solid var(--bs-border-color); border-radius:12px; padding:16px; text-align:center; cursor:pointer; transition:all 0.2s; }
    .raffle-type-card:hover { border-color:#ff3b5c; }
    .raffle-type-card.selected { border-color:#ff3b5c; background:rgba(255,59,92,0.05); }
    .raffle-type-card .icon { font-size:2rem; margin-bottom:8px; }

    .preview-raffle { max-width:400px; margin:0 auto; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; overflow:hidden; }
    .preview-raffle-header { background:linear-gradient(135deg,#ff3b5c,#ff6b35); color:#fff; padding:20px; text-align:center; }
    .preview-raffle-body { padding:16px; }
    .prize-card { background:linear-gradient(135deg,#ffecd2,#fcb69f); padding:12px 16px; border-radius:10px; text-align:center; margin-bottom:12px; }

    .loading-spin { display:none; text-align:center; padding:20px; }
    .loading-spin.active { display:block; }
    .spinner-ai { border:3px solid var(--bs-border-color); border-top:3px solid #ff3b5c; border-radius:50%; width:36px; height:36px; animation:spin 1s linear infinite; margin:0 auto 8px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-gift" style="color:#ff3b5c;"></i> AI 응모 마법사</h5>
    <div class="d-flex gap-2">
        <a href="ai_event_wizard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-event me-1"></i> 이벤트 마법사</a>
        <a href="ai_ad_editor.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-robot me-1"></i> AI 에디터</a>
    </div>
</div>

<!-- 진행 바 -->
<div class="wiz-progress" id="wiz-progress">
    <div class="wiz-step-item active" data-step="1">
        <div class="wiz-step-circle active">1</div>
        <div class="wiz-step-label">이벤트 선택</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="2">
        <div class="wiz-step-circle">2</div>
        <div class="wiz-step-label">응모 설정</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="3">
        <div class="wiz-step-circle">3</div>
        <div class="wiz-step-label">AI 홍보문</div>
    </div>
    <div class="wiz-connector"></div>
    <div class="wiz-step-item" data-step="4">
        <div class="wiz-step-circle">4</div>
        <div class="wiz-step-label">저장</div>
    </div>
</div>

<!-- Step 1: 이벤트 선택 -->
<div class="wiz-panel active" id="step-1">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-calendar-check me-1"></i> 이벤트 선택</h6>
        <p class="text-body-secondary small mb-3">기존 이벤트에 응모 기능을 추가하거나, 새로운 응모 전용 이벤트를 만들 수 있습니다.</p>

        <div class="mb-3">
            <input type="text" class="form-control" id="rf_search" placeholder="이벤트 제목/병원/카테고리 검색..." oninput="rfSearchDebounce()">
        </div>

        <div id="rf-event-list" style="max-height:400px; overflow-y:auto;"></div>

        <div class="text-center mt-3">
            <button class="btn btn-outline-secondary btn-sm" onclick="rfSkipEvent()"><i class="bi bi-plus-lg me-1"></i> 이벤트 없이 새 응모 만들기</button>
        </div>
    </div>
    <div class="text-end">
        <button class="btn btn-primary" onclick="rfGoStep(2)">다음: 응모 설정 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 2: 응모 설정 -->
<div class="wiz-panel" id="step-2">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-gear me-1"></i> 응모 설정</h6>

        <div class="mb-3">
            <label class="form-label fw-semibold">응모 유형</label>
            <div class="form-row-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="raffle-type-card selected" data-type="lottery" onclick="rfSelectType(this,'lottery')">
                    <div class="icon">🎰</div>
                    <div class="fw-semibold small">추첨</div>
                    <div class="text-body-secondary" style="font-size:0.7rem;">랜덤 당첨</div>
                </div>
                <div class="raffle-type-card" data-type="first_come" onclick="rfSelectType(this,'first_come')">
                    <div class="icon">⚡</div>
                    <div class="fw-semibold small">선착순</div>
                    <div class="text-body-secondary" style="font-size:0.7rem;">빠른 순서</div>
                </div>
                <div class="raffle-type-card" data-type="quiz" onclick="rfSelectType(this,'quiz')">
                    <div class="icon">❓</div>
                    <div class="fw-semibold small">퀴즈</div>
                    <div class="text-body-secondary" style="font-size:0.7rem;">정답 맞추기</div>
                </div>
                <div class="raffle-type-card" data-type="share" onclick="rfSelectType(this,'share')">
                    <div class="icon">📢</div>
                    <div class="fw-semibold small">공유</div>
                    <div class="text-body-secondary" style="font-size:0.7rem;">SNS 공유</div>
                </div>
            </div>
        </div>

        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">당첨자 수</label>
                <input type="number" class="form-control" id="rf_winner_count" value="3" min="1">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">최대 응모자 수 <small class="text-body-secondary">(0=무제한)</small></label>
                <input type="number" class="form-control" id="rf_max_entries" value="0" min="0">
            </div>
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">응모 마감일</label>
                <input type="date" class="form-control" id="rf_end_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">경품/혜택</label>
                <input type="text" class="form-control" id="rf_prize" placeholder="예: 시술 50% 할인권">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">경품 상세 설명</label>
            <textarea class="form-control" id="rf_prize_detail" rows="2" placeholder="예: 1등 100만원 상당 시술권, 2등 50만원 상당 피부관리..."></textarea>
        </div>
    </div>
    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="rfGoStep(1)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-primary" onclick="rfGoStep(3)">다음: AI 홍보문 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 3: AI 홍보문 생성 -->
<div class="wiz-panel" id="step-3">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-stars me-1"></i> AI 응모 홍보문 생성</h6>
        <button class="btn btn-primary btn-sm" onclick="rfGenerateCopy()"><i class="bi bi-stars me-1"></i> AI 홍보문 생성</button>
        <button class="btn btn-outline-secondary btn-sm ms-1" onclick="document.getElementById('rf-copy-form').style.display='block'"><i class="bi bi-pencil me-1"></i> 직접 입력</button>

        <div id="rf-copy-loading" class="loading-spin">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary small">AI가 홍보문을 생성 중...</p>
        </div>

        <div id="rf-copy-form" style="display:none; margin-top:16px;">
            <div class="mb-3">
                <label class="form-label fw-semibold">응모 제목</label>
                <input type="text" class="form-control" id="rf_promo_title" placeholder="응모 이벤트 제목">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">부제목</label>
                <input type="text" class="form-control" id="rf_promo_subtitle" placeholder="한 줄 요약">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">홍보 설명문</label>
                <textarea class="form-control" id="rf_promo_description" rows="4" placeholder="응모 이벤트 상세 설명"></textarea>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="rfGenerateCopy()"><i class="bi bi-arrow-clockwise me-1"></i> 다시 생성</button>
        </div>
    </div>
    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="rfGoStep(2)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-primary" onclick="rfGoStep(4)">다음: 미리보기 <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</div>

<!-- Step 4: 미리보기 + 저장 -->
<div class="wiz-panel" id="step-4">
    <div class="wiz-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-eye me-1"></i> 응모 미리보기</h6>

        <div class="preview-raffle" id="rf-preview">
            <div class="preview-raffle-header">
                <div style="font-size:2.5rem;">🎁</div>
                <h5 class="fw-bold mt-2 mb-1" id="rf-preview-title">응모 이벤트</h5>
                <p class="mb-0 small" style="opacity:0.9;" id="rf-preview-subtitle"></p>
            </div>
            <div class="preview-raffle-body">
                <div class="prize-card">
                    <div class="small fw-semibold mb-1">🏆 경품</div>
                    <div class="fw-bold" id="rf-preview-prize">-</div>
                </div>
                <div class="small text-body-secondary" id="rf-preview-desc"></div>
                <table class="table table-sm mt-3" style="font-size:0.82rem;">
                    <tbody id="rf-preview-table"></tbody>
                </table>
                <button class="btn w-100 fw-bold" style="background:linear-gradient(135deg,#ff3b5c,#ff6b35);color:#fff;border-radius:10px;padding:12px;">
                    <i class="bi bi-gift me-1"></i> 지금 응모하기
                </button>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" onclick="rfGoStep(3)"><i class="bi bi-arrow-left me-1"></i> 이전</button>
        <button class="btn btn-success btn-lg" onclick="rfSave()"><i class="bi bi-check-circle me-1"></i> 응모 이벤트 저장</button>
    </div>
</div>

<!-- 성공 -->
<div id="rf-success" class="text-center py-5" style="display:none;">
    <div style="font-size:4rem;color:#198754;"><i class="bi bi-check-circle-fill"></i></div>
    <h4 class="fw-bold mt-3">응모 이벤트가 생성되었습니다!</h4>
    <p class="text-body-secondary" id="rf-success-msg"></p>
    <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="ai_raffle_wizard.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> 새 응모 만들기</a>
        <a href="ai_event_wizard.php" class="btn btn-outline-secondary"><i class="bi bi-calendar-event me-1"></i> 이벤트 마법사</a>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
var _rfData = {
    event_id: 0, event_title: '', hospital_name: '',
    raffle_type: 'lottery', winner_count: 3, max_entries: 0,
    end_date: '', prize: '', prize_detail: '',
    promo_title: '', promo_subtitle: '', promo_description: ''
};

var _rfSearchTimer = null;

// === Step 네비게이션 ===
function rfGoStep(n) {
    if (n > 1) rfCollectStep1();
    if (n > 2) rfCollectStep2();
    if (n > 3) rfCollectStep3();
    if (n === 4) rfBuildPreview();

    document.querySelectorAll('.wiz-panel').forEach(function(p){p.classList.remove('active');});
    document.getElementById('step-'+n).classList.add('active');

    document.querySelectorAll('.wiz-step-item').forEach(function(item) {
        var s = parseInt(item.getAttribute('data-step'));
        var c = item.querySelector('.wiz-step-circle');
        item.classList.remove('active'); c.classList.remove('active','done');
        if (s < n) c.classList.add('done');
        else if (s === n) { item.classList.add('active'); c.classList.add('active'); }
    });
    document.querySelectorAll('.wiz-connector').forEach(function(c,i){c.classList.toggle('done', i < n-1);});
    window.scrollTo({top:0, behavior:'smooth'});
}

function rfCollectStep1() {
    // 이벤트 선택은 rfPickEvent에서 처리
}
function rfCollectStep2() {
    _rfData.raffle_type = document.querySelector('.raffle-type-card.selected')?.getAttribute('data-type') || 'lottery';
    _rfData.winner_count = parseInt(document.getElementById('rf_winner_count').value) || 1;
    _rfData.max_entries = parseInt(document.getElementById('rf_max_entries').value) || 0;
    _rfData.end_date = document.getElementById('rf_end_date').value;
    _rfData.prize = document.getElementById('rf_prize').value;
    _rfData.prize_detail = document.getElementById('rf_prize_detail').value;
}
function rfCollectStep3() {
    _rfData.promo_title = document.getElementById('rf_promo_title').value;
    _rfData.promo_subtitle = document.getElementById('rf_promo_subtitle').value;
    _rfData.promo_description = document.getElementById('rf_promo_description').value;
}

// === Step 1: 이벤트 검색 ===
function rfSearchDebounce() {
    clearTimeout(_rfSearchTimer);
    _rfSearchTimer = setTimeout(function(){ rfSearchEvents(); }, 400);
}

function rfSearchEvents() {
    var kw = document.getElementById('rf_search').value;
    var fd = new FormData();
    fd.append('action','search_events');
    fd.append('keyword', kw);
    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        if (!res.success) return;
        var list = res.events || [];
        var container = document.getElementById('rf-event-list');
        if (list.length === 0) {
            container.innerHTML = '<p class="text-body-secondary text-center py-3">이벤트가 없습니다</p>';
            return;
        }
        var html = '';
        list.forEach(function(ev) {
            var selected = _rfData.event_id === parseInt(ev.id) ? ' selected' : '';
            var imgHtml = ev.image_url
                ? '<img class="event-pick-thumb" src="' + esc(ev.image_url) + '" onerror="this.outerHTML=\'<div class=event-pick-thumb-empty><i class=bi-image></i></div>\'">'
                : '<div class="event-pick-thumb-empty"><i class="bi bi-image"></i></div>';
            var rateBadge = ev.discount_rate > 0 ? '<span class="badge bg-danger ms-1">' + ev.discount_rate + '%</span>' : '';
            var raffleBadge = ev.has_raffle === '1' ? '<span class="badge bg-warning text-dark ms-1">응모중</span>' : '';

            html += '<div class="event-pick-card' + selected + '" data-id="' + ev.id + '" onclick="rfPickEvent(' + ev.id + ')">'
                + imgHtml
                + '<div style="flex:1;min-width:0;">'
                + '<div class="fw-semibold small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(ev.title) + rateBadge + raffleBadge + '</div>'
                + '<div class="text-body-secondary" style="font-size:0.75rem;">' + esc(ev.hospital_name || '') + ' · ' + esc(ev.category || '') + '</div>'
                + '<div class="text-body-secondary" style="font-size:0.7rem;">' + (ev.start_date||'') + ' ~ ' + (ev.end_date||'') + '</div>'
                + '</div></div>';
        });
        container.innerHTML = html;
    });
}

function rfPickEvent(id) {
    _rfData.event_id = id;
    document.querySelectorAll('.event-pick-card').forEach(function(c){
        c.classList.toggle('selected', parseInt(c.getAttribute('data-id')) === id);
    });
    // 이벤트 상세 로드
    var fd = new FormData();
    fd.append('action','load_event');
    fd.append('event_id', id);
    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        if (res.success && res.event) {
            var ev = res.event;
            _rfData.event_title = ev.title;
            _rfData.hospital_name = ev.hospital_name || '';
            // 기존 응모 설정이 있으면 자동 채우기
            if (ev.has_raffle === '1') {
                document.getElementById('rf_prize').value = ev.raffle_prize || '';
                document.getElementById('rf_winner_count').value = ev.raffle_winner_count || 1;
                document.getElementById('rf_max_entries').value = ev.max_entries || 0;
                if (ev.raffle_end_date) document.getElementById('rf_end_date').value = ev.raffle_end_date;
            }
        }
    });
}

function rfSkipEvent() {
    _rfData.event_id = 0;
    _rfData.event_title = '';
    _rfData.hospital_name = '';
    document.querySelectorAll('.event-pick-card').forEach(function(c){c.classList.remove('selected');});
}

// 페이지 로드 시 이벤트 목록
rfSearchEvents();

// === Step 2: 응모 유형 ===
function rfSelectType(el, type) {
    document.querySelectorAll('.raffle-type-card').forEach(function(c){c.classList.remove('selected');});
    el.classList.add('selected');
    _rfData.raffle_type = type;
}

// === Step 3: AI 홍보문 ===
function rfGenerateCopy() {
    rfCollectStep2();
    var loading = document.getElementById('rf-copy-loading');
    loading.classList.add('active');

    var fd = new FormData();
    fd.append('action','generate_raffle_copy');
    fd.append('event_title', _rfData.event_title || '특별 이벤트');
    fd.append('hospital_name', _rfData.hospital_name);
    fd.append('prize', _rfData.prize);
    fd.append('raffle_type', _rfData.raffle_type);
    fd.append('deadline', _rfData.end_date);

    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        loading.classList.remove('active');
        if (res.success && res.data) {
            document.getElementById('rf_promo_title').value = res.data.title || '';
            document.getElementById('rf_promo_subtitle').value = res.data.subtitle || '';
            document.getElementById('rf_promo_description').value = res.data.description || '';
            document.getElementById('rf-copy-form').style.display = 'block';
        } else {
            alert(res.message || '생성 실패');
        }
    }).catch(function(){
        loading.classList.remove('active');
        alert('서버 오류');
    });
}

// === Step 4: 미리보기 ===
function rfBuildPreview() {
    var typeLabels = {lottery:'🎰 추첨', first_come:'⚡ 선착순', quiz:'❓ 퀴즈', share:'📢 공유'};
    document.getElementById('rf-preview-title').textContent = _rfData.promo_title || _rfData.event_title || '응모 이벤트';
    document.getElementById('rf-preview-subtitle').textContent = _rfData.promo_subtitle || '';
    document.getElementById('rf-preview-prize').textContent = _rfData.prize || '특별 경품';
    document.getElementById('rf-preview-desc').textContent = _rfData.promo_description || '';

    var rows = [
        ['응모 유형', typeLabels[_rfData.raffle_type] || _rfData.raffle_type],
        ['당첨자 수', _rfData.winner_count + '명'],
        ['마감일', _rfData.end_date || '미정'],
    ];
    if (_rfData.max_entries > 0) rows.push(['최대 응모', _rfData.max_entries + '명']);
    if (_rfData.event_title) rows.push(['연결 이벤트', _rfData.event_title]);
    if (_rfData.hospital_name) rows.push(['병원', _rfData.hospital_name]);

    var tHtml = '';
    rows.forEach(function(r) {
        tHtml += '<tr><th class="text-body-secondary" style="width:90px;">' + r[0] + '</th><td>' + esc(r[1]) + '</td></tr>';
    });
    document.getElementById('rf-preview-table').innerHTML = tHtml;
}

// === 저장 ===
function rfSave() {
    var fd = new FormData();
    fd.append('action', 'save_raffle');
    fd.append('event_id', _rfData.event_id);
    fd.append('raffle_type', _rfData.raffle_type);
    fd.append('winner_count', _rfData.winner_count);
    fd.append('max_entries', _rfData.max_entries);
    fd.append('raffle_end_date', _rfData.end_date);
    fd.append('prize', _rfData.prize + (_rfData.prize_detail ? '\n' + _rfData.prize_detail : ''));
    fd.append('promo_title', _rfData.promo_title);
    fd.append('promo_subtitle', _rfData.promo_subtitle);
    fd.append('promo_description', _rfData.promo_description);

    fetch('', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res) {
        if (res.success) {
            document.querySelectorAll('.wiz-panel, .wiz-progress').forEach(function(el){el.style.display='none';});
            document.getElementById('rf-success').style.display = 'block';
            var mode = res.mode === 'updated' ? '기존 이벤트에 응모 추가됨' : '새 응모 이벤트 생성됨';
            document.getElementById('rf-success-msg').textContent = 'Event ID: ' + res.event_id + ' | ' + mode;
        } else {
            alert(res.message || '저장 실패');
        }
    }).catch(function(){ alert('서버 오류'); });
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

<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * AI 응모 에디터
 * GPT-4 + DALL-E를 사용한 자동 응모 이벤트 생성
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

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'generate_copy':
            $event_title = $_POST['event_title'] ?? '';
            $prize = $_POST['prize'] ?? '';
            $tone = $_POST['tone'] ?? 'energetic';

            if (function_exists('generateAdCopy')) {
                $prompt_text = "응모 이벤트: {$event_title}, 경품: {$prize}";
                $result = generateAdCopy('', $prompt_text, [
                    'tone' => $tone,
                    'target_audience' => '이벤트 응모 참여 고객',
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
                    $filename = 'ai_raffle_' . date('YmdHis') . '_' . uniqid() . '.png';
                    $local_path = saveImageLocally($result['url'], $filename);
                    if ($local_path) $result['local_path'] = $local_path;
                }
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 모듈이 설정되지 않았습니다']);
            }
            exit;

        case 'generate_ab_test':
            $title = $_POST['title'] ?? '';
            $subtitle = $_POST['subtitle'] ?? '';

            if (function_exists('generateABTestVariants')) {
                $result = generateABTestVariants($title, $subtitle);
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 모듈이 설정되지 않았습니다']);
            }
            exit;

        case 'save_raffle':
            $title = mysqli_real_escape_string($connect, $_POST['title']);
            $subtitle = mysqli_real_escape_string($connect, $_POST['subtitle']);
            $description = mysqli_real_escape_string($connect, $_POST['description'] ?? '');
            $image_url = mysqli_real_escape_string($connect, $_POST['image_url'] ?? '');
            $raffle_type = mysqli_real_escape_string($connect, $_POST['raffle_type'] ?? 'random');
            $max_entries = intval($_POST['max_entries'] ?? 0);
            $raffle_end_date = mysqli_real_escape_string($connect, $_POST['raffle_end_date'] ?? date('Y-m-d', strtotime('+14 days')));
            $raffle_prize = mysqli_real_escape_string($connect, $_POST['raffle_prize'] ?? '');
            $raffle_winner_count = intval($_POST['raffle_winner_count'] ?? 1);
            $hospital_name = mysqli_real_escape_string($connect, $_POST['hospital_name'] ?? '');

            $query = "INSERT INTO gangnamunni_events (title, subtitle, description, image_url, hospital_name, has_raffle, raffle_type, max_entries, raffle_end_date, raffle_prize, raffle_winner_count, is_active, start_date, end_date)
                      VALUES ('{$title}', '{$subtitle}', '{$description}', '{$image_url}', '{$hospital_name}', 1, '{$raffle_type}', {$max_entries}, '{$raffle_end_date}', '{$raffle_prize}', {$raffle_winner_count}, 0, CURDATE(), '{$raffle_end_date}')";

            if (mysqli_query($connect, $query)) {
                echo json_encode(['success' => true, 'event_id' => mysqli_insert_id($connect)]);
            } else {
                echo json_encode(['success' => false, 'message' => '응모 이벤트 저장 실패: ' . mysqli_error($connect)]);
            }
            exit;

        case 'delete_all_raffles':
            $query = "DELETE FROM gangnamunni_events WHERE has_raffle = 1";
            if (mysqli_query($connect, $query)) {
                $affected = mysqli_affected_rows($connect);
                echo json_encode(['success' => true, 'deleted' => $affected]);
            } else {
                echo json_encode(['success' => false, 'message' => '전체 삭제 실패']);
            }
            exit;

        case 'delete_raffle':
            $event_id = intval($_POST['event_id'] ?? 0);
            if ($event_id > 0) {
                // 응모자 데이터도 함께 삭제
                mysqli_query($connect, "DELETE FROM gangnamunni_event_entries WHERE event_id = {$event_id}");
                $query = "DELETE FROM gangnamunni_events WHERE id = {$event_id} AND has_raffle = 1";
                if (mysqli_query($connect, $query)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => '삭제 실패']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '잘못된 ID']);
            }
            exit;

        case 'load_raffles':
            $raffles = [];
            $result = mysqli_query($connect, "SELECT e.id, e.title, e.subtitle, e.image_url, e.raffle_type, e.max_entries, e.raffle_end_date, e.raffle_prize, e.raffle_winner_count, e.is_active, e.created_at,
                (SELECT COUNT(*) FROM gangnamunni_event_entries WHERE event_id = e.id) as entry_count
                FROM gangnamunni_events e WHERE e.has_raffle = 1 ORDER BY e.id DESC");
            while ($row = mysqli_fetch_assoc($result)) {
                $raffles[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $raffles]);
            exit;
    }

    echo json_encode(['success' => false, 'message' => '잘못된 요청']);
    exit;
}

// 병원 목록
$hospitals = [];
if ($connect) {
    $hospitals_result = mysqli_query($connect, "SELECT DISTINCT name FROM gangnamunni_hospitals ORDER BY name LIMIT 100");
    while ($row = mysqli_fetch_assoc($hospitals_result)) {
        $hospitals[] = $row['name'];
    }
}

$admin_page_title = 'AI 응모 에디터';
$admin_current = 'ai_raffle_editor';
$admin_extra_css = '
    .section-card { margin-bottom:24px; }
    .section-card .card-header h6 i { margin-right:6px; }
    .form-row-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-row-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
    .loading-box { display:none; text-align:center; padding:24px; }
    .loading-box.active { display:block; }
    .spinner-ai { border:3px solid var(--bs-border-color); border-top:3px solid var(--bs-primary); border-radius:50%; width:40px; height:40px; animation:spin 1s linear infinite; margin:0 auto 10px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .preview-box { background:var(--bs-tertiary-bg); padding:20px; border-radius:12px; margin-top:16px; display:none; }
    .preview-ad { background:var(--bs-body-bg); padding:16px; border-radius:8px; border:1px solid var(--bs-border-color); }
    .preview-ad img { max-width:100%; border-radius:8px; margin-bottom:12px; }
    .variants-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:16px; }
    .variant-card { background:var(--bs-body-bg); padding:12px; border-radius:8px; border:2px solid var(--bs-border-color); cursor:pointer; transition:all 0.2s; }
    .variant-card:hover { border-color:var(--bs-primary); transform:translateY(-1px); }
    .variant-card.selected { border-color:var(--bs-primary); background:rgba(102,126,234,0.05); }
    .ab-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px; display:none; }
    .ab-variant { background:var(--bs-body-bg); padding:16px; border-radius:8px; border:1px solid var(--bs-border-color); }
    .history-table { width:100%; font-size:0.88rem; }
    .history-table th { background:var(--bs-tertiary-bg); padding:8px 10px; font-weight:600; white-space:nowrap; }
    .history-table td { padding:8px 10px; border-bottom:1px solid var(--bs-border-color); vertical-align:middle; }
    .history-table tr:hover { background:rgba(102,126,234,0.04); }
    .history-table .thumb-img { width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid var(--bs-border-color); }
    .history-empty { text-align:center; padding:40px 20px; color:var(--bs-secondary-color); }
    .badge-type { font-size:0.72rem; }
    @media (max-width:767px) { .form-row-grid, .form-row-grid-3, .variants-grid, .ab-grid { grid-template-columns:1fr; } }
';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-gift"></i> AI 응모 에디터</h5>
    <a href="admin_events.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 이벤트 관리</a>
</div>

<div id="success-message" class="alert alert-success" style="display:none;"></div>

<!-- 1. 응모 카피 생성 -->
<div class="card section-card">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-pencil-square"></i> AI 응모 카피 생성</h6>
    </div>
    <div class="card-body">
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">이벤트 제목</label>
                <input type="text" class="form-control" id="event_title" placeholder="예: 신년 맞이 럭키 드로우">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">경품</label>
                <input type="text" class="form-control" id="prize_input" placeholder="예: 시술권 50만원, 화장품 세트">
            </div>
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">톤 & 스타일</label>
                <select class="form-select" id="tone">
                    <option value="energetic" selected>활기차고 흥미로운</option>
                    <option value="luxury">고급스럽고 세련된</option>
                    <option value="friendly">친근하고 편안한</option>
                    <option value="professional">전문적이고 신뢰감 있는</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">병원명 (선택)</label>
                <input type="text" class="form-control" id="hospital_name" list="hospitals" placeholder="연결할 병원">
                <datalist id="hospitals">
                    <?php foreach ($hospitals as $h): ?>
                        <option value="<?= htmlspecialchars($h) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="generateCopy()"><i class="bi bi-stars"></i> AI 카피 생성</button>

        <div id="copy-loading" class="loading-box">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary">AI가 응모 카피를 생성하고 있습니다...</p>
        </div>

        <div id="copy-result" class="preview-box">
            <h6 class="fw-bold mb-2">생성된 응모 카피</h6>
            <div class="preview-ad">
                <h5 id="generated-title"></h5>
                <p id="generated-subtitle" class="text-body-secondary"></p>
                <p id="generated-description" class="small"></p>
                <button id="generated-cta" class="btn btn-success btn-sm mt-2"></button>
            </div>
            <div class="variants-grid" id="copy-variants"></div>
        </div>
    </div>
</div>

<!-- 2. 응모 이미지 생성 -->
<div class="card section-card">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-palette"></i> AI 응모 이미지 생성</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-semibold">이미지 프롬프트 (영문 권장)</label>
            <textarea class="form-control" id="image_prompt" rows="3" placeholder="예: A colorful raffle event banner with gift boxes, confetti, golden ticket, and exciting celebration theme."></textarea>
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">이미지 크기</label>
                <select class="form-select" id="image_size">
                    <option value="1024x1024" selected>정사각형 (1024x1024)</option>
                    <option value="1792x1024">가로형 (1792x1024)</option>
                    <option value="1024x1792">세로형 (1024x1792)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">품질</label>
                <select class="form-select" id="image_quality">
                    <option value="standard">표준 (빠름)</option>
                    <option value="hd">HD (고품질)</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="generateImage()"><i class="bi bi-image"></i> AI 이미지 생성</button>

        <div id="image-loading" class="loading-box">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary">DALL-E가 이미지를 생성하고 있습니다...</p>
        </div>

        <div id="image-result" class="preview-box">
            <h6 class="fw-bold mb-2">생성된 이미지</h6>
            <img id="generated-image" src="" alt="AI 생성 이미지" style="max-width:100%; border-radius:8px;">
            <p id="revised-prompt" class="text-body-secondary small mt-2"></p>
        </div>
    </div>
</div>

<!-- 3. A/B 테스트 -->
<div class="card section-card">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-clipboard2-data"></i> A/B 테스트 버전 생성</h6>
    </div>
    <div class="card-body">
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">원본 제목</label>
                <input type="text" class="form-control" id="ab_title" placeholder="예: 럭키 드로우 이벤트">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">원본 부제목</label>
                <input type="text" class="form-control" id="ab_subtitle" placeholder="예: 지금 응모하고 경품 받자!">
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="generateABTest()"><i class="bi bi-diagram-2"></i> A/B 테스트 생성</button>

        <div id="ab-loading" class="loading-box">
            <div class="spinner-ai"></div>
            <p class="text-body-secondary">A/B 테스트 버전을 생성하고 있습니다...</p>
        </div>

        <div id="ab-result" class="ab-grid">
            <div class="ab-variant">
                <h6 class="fw-bold">A 버전 (원본 기반)</h6>
                <p><strong id="ab-a-title"></strong></p>
                <p class="text-body-secondary" id="ab-a-subtitle"></p>
            </div>
            <div class="ab-variant">
                <h6 class="fw-bold">B 버전 (최적화)</h6>
                <p><strong id="ab-b-title"></strong></p>
                <p class="text-body-secondary" id="ab-b-subtitle"></p>
            </div>
        </div>
    </div>
</div>

<!-- 4. 응모 이벤트 저장 -->
<div class="card section-card">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-floppy"></i> 응모 이벤트 저장</h6>
    </div>
    <div class="card-body">
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">이벤트 제목</label>
                <input type="text" class="form-control" id="final_title" placeholder="AI 생성 또는 직접 입력">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">부제목</label>
                <input type="text" class="form-control" id="final_subtitle" placeholder="AI 생성 또는 직접 입력">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">설명</label>
            <textarea class="form-control" id="final_description" rows="3" placeholder="응모 이벤트 상세 설명"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">경품 내용</label>
            <textarea class="form-control" id="final_prize" rows="2" placeholder="예: 1등 시술권 100만원 (1명), 2등 화장품 세트 (5명)"></textarea>
        </div>
        <div class="form-row-grid-3">
            <div class="mb-3">
                <label class="form-label fw-semibold">응모 유형</label>
                <select class="form-select" id="final_raffle_type">
                    <option value="random">랜덤 추첨</option>
                    <option value="first_come">선착순</option>
                    <option value="quiz">퀴즈 정답</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">최대 응모수</label>
                <input type="number" class="form-control" id="final_max_entries" value="100" min="0">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">당첨자 수</label>
                <input type="number" class="form-control" id="final_winner_count" value="1" min="1">
            </div>
        </div>
        <div class="form-row-grid">
            <div class="mb-3">
                <label class="form-label fw-semibold">응모 마감일</label>
                <input type="date" class="form-control" id="final_raffle_end_date">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">이미지 URL</label>
                <input type="text" class="form-control" id="final_image_url" placeholder="AI 생성 이미지 경로">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">병원명</label>
            <input type="text" class="form-control" id="final_hospital_name" list="hospitals" placeholder="연결할 병원">
        </div>
        <button class="btn btn-success btn-sm" onclick="saveRaffle()"><i class="bi bi-floppy"></i> 응모 이벤트 저장</button>
    </div>
</div>

<!-- 5. 생성 내역 -->
<div class="card section-card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history"></i> 응모 이벤트 내역</h6>
        <button class="btn btn-danger btn-sm" onclick="deleteAllRaffles()" id="btn-delete-all" style="display:none;">
            <i class="bi bi-trash3"></i> 전체 삭제
        </button>
    </div>
    <div class="card-body p-0">
        <div id="history-loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-secondary"></div> 불러오는 중...
        </div>
        <div id="history-container"></div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
document.addEventListener('DOMContentLoaded', function() {
    var next14 = new Date(Date.now() + 14*86400000).toISOString().split('T')[0];
    document.getElementById('final_raffle_end_date').value = next14;
    loadHistory();
});

// --- 생성 내역 관리 ---
function loadHistory() {
    var formData = new FormData();
    formData.append('action', 'load_raffles');
    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('history-loading').style.display = 'none';
        if (res.success) renderHistory(res.data);
    })
    .catch(function() {
        document.getElementById('history-loading').innerHTML = '<p class="text-danger">불러오기 실패</p>';
    });
}

function renderHistory(raffles) {
    var container = document.getElementById('history-container');
    var btnAll = document.getElementById('btn-delete-all');
    if (!raffles || raffles.length === 0) {
        container.innerHTML = '<div class="history-empty"><i class="bi bi-inbox" style="font-size:2rem;"></i><p class="mt-2 mb-0">저장된 응모 이벤트가 없습니다</p></div>';
        btnAll.style.display = 'none';
        return;
    }
    btnAll.style.display = '';
    var html = '<div class="table-responsive"><table class="history-table"><thead><tr>';
    html += '<th></th><th>제목</th><th>유형</th><th>경품</th><th>응모/최대</th><th>당첨</th><th>마감일</th><th>상태</th><th>작업</th>';
    html += '</tr></thead><tbody>';
    raffles.forEach(function(r) {
        var thumb = r.image_url ? '<img src="' + r.image_url + '" class="thumb-img" onerror="this.style.display=\'none\'">' : '<span class="text-body-secondary">-</span>';
        var typeMap = { random: '랜덤', first_come: '선착순', quiz: '퀴즈' };
        var typeBadge = '<span class="badge bg-purple badge-type" style="background:#AE1932;">' + (typeMap[r.raffle_type] || r.raffle_type) + '</span>';
        var prize = escH((r.raffle_prize || '').substring(0, 20));
        var entries = (r.entry_count || 0) + '/' + (r.max_entries || '∞');
        var statusBadge = r.is_active == 1 ? '<span class="badge bg-success">활성</span>' : '<span class="badge bg-outline-secondary border">비활성</span>';
        html += '<tr data-id="' + r.id + '">';
        html += '<td>' + thumb + '</td>';
        html += '<td><strong>' + escH(r.title) + '</strong></td>';
        html += '<td>' + typeBadge + '</td>';
        html += '<td class="text-body-secondary">' + prize + '</td>';
        html += '<td>' + entries + '</td>';
        html += '<td>' + (r.raffle_winner_count || 1) + '명</td>';
        html += '<td class="text-body-secondary" style="white-space:nowrap;">' + (r.raffle_end_date || '-') + '</td>';
        html += '<td>' + statusBadge + '</td>';
        html += '<td style="white-space:nowrap;">';
        html += '<button class="btn btn-outline-primary btn-sm me-1" onclick="loadToEditor(' + r.id + ')" title="에디터에 불러오기"><i class="bi bi-pencil-square"></i></button>';
        html += '<button class="btn btn-outline-info btn-sm me-1" onclick="openInImageEditor(' + r.id + ')" title="AI 이미지 에디터"><i class="bi bi-palette"></i></button>';
        html += '<button class="btn btn-outline-danger btn-sm" onclick="deleteSingleRaffle(' + r.id + ')" title="삭제"><i class="bi bi-trash3"></i></button>';
        html += '</td></tr>';
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function escH(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function deleteAllRaffles() {
    if (!confirm('⚠️ 저장된 모든 응모 이벤트가 삭제됩니다.\n응모자 데이터도 함께 삭제됩니다.\n\n정말 전체 삭제하시겠습니까?')) return;
    if (!confirm('마지막 확인: 정말로 전체 삭제를 진행하시겠습니까?')) return;
    var formData = new FormData();
    formData.append('action', 'delete_all_raffles');
    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            var msg = document.getElementById('success-message');
            msg.textContent = '전체 ' + res.deleted + '건의 응모 이벤트가 삭제되었습니다.';
            msg.className = 'alert alert-warning';
            msg.style.display = 'block';
            setTimeout(function() { msg.style.display = 'none'; }, 3000);
            loadHistory();
        } else {
            alert('삭제 실패: ' + (res.message || ''));
        }
    })
    .catch(function() { alert('서버 오류가 발생했습니다'); });
}

function deleteSingleRaffle(eventId) {
    if (!confirm('이 응모 이벤트를 삭제하시겠습니까?\n관련 응모자 데이터도 함께 삭제됩니다.')) return;
    var formData = new FormData();
    formData.append('action', 'delete_raffle');
    formData.append('event_id', eventId);
    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) loadHistory();
        else alert('삭제 실패: ' + (res.message || ''));
    })
    .catch(function() { alert('서버 오류가 발생했습니다'); });
}

function loadToEditor(eventId) {
    var formData = new FormData();
    formData.append('action', 'load_raffles');
    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) return;
        var r = res.data.find(function(e) { return e.id == eventId; });
        if (!r) { alert('응모 이벤트를 찾을 수 없습니다'); return; }
        document.getElementById('final_title').value = r.title || '';
        document.getElementById('final_subtitle').value = r.subtitle || '';
        document.getElementById('final_description').value = r.description || '';
        document.getElementById('final_prize').value = r.raffle_prize || '';
        document.getElementById('final_raffle_type').value = r.raffle_type || 'random';
        document.getElementById('final_max_entries').value = r.max_entries || 100;
        document.getElementById('final_winner_count').value = r.raffle_winner_count || 1;
        document.getElementById('final_raffle_end_date').value = r.raffle_end_date || '';
        document.getElementById('final_image_url').value = r.image_url || '';
        if (r.image_url) {
            document.getElementById('generated-image').src = r.image_url;
            document.getElementById('revised-prompt').textContent = '저장된 이미지';
            document.getElementById('image-result').style.display = 'block';
        }
        document.getElementById('final_title').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        var msg = document.getElementById('success-message');
        msg.textContent = '응모 이벤트 #' + eventId + ' 데이터를 에디터에 불러왔습니다.';
        msg.className = 'alert alert-info';
        msg.style.display = 'block';
        setTimeout(function() { msg.style.display = 'none'; }, 4000);
    })
    .catch(function() { alert('서버 오류가 발생했습니다'); });
}

function openInImageEditor(eventId) {
    window.open('ai_image_editor.php?load_event=' + eventId, '_blank');
}

// --- AI 생성 ---
function generateCopy() {
    var eventTitle = document.getElementById('event_title').value;
    var prize = document.getElementById('prize_input').value;
    var tone = document.getElementById('tone').value;

    if (!eventTitle) { alert('이벤트 제목을 입력해주세요'); return; }

    document.getElementById('copy-loading').classList.add('active');
    document.getElementById('copy-result').style.display = 'none';

    var formData = new FormData();
    formData.append('action', 'generate_copy');
    formData.append('event_title', eventTitle);
    formData.append('prize', prize);
    formData.append('tone', tone);

    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(response) {
        document.getElementById('copy-loading').classList.remove('active');
        if (response.success && response.data) {
            var d = response.data;
            document.getElementById('generated-title').textContent = d.title || '';
            document.getElementById('generated-subtitle').textContent = d.subtitle || '';
            document.getElementById('generated-description').textContent = d.description || '';
            document.getElementById('generated-cta').textContent = d.cta || '';
            document.getElementById('final_title').value = d.title || '';
            document.getElementById('final_subtitle').value = d.subtitle || '';
            if (d.description) document.getElementById('final_description').value = d.description;
            var html = '';
            if (d.variants && d.variants.length > 0) {
                d.variants.forEach(function(v, i) {
                    html += '<div class="variant-card" onclick="selectVariant(this)"><h6>버전 ' + (i+2) + '</h6><p><strong>' + (v.title||'') + '</strong></p><p>' + (v.subtitle||'') + '</p></div>';
                });
            }
            document.getElementById('copy-variants').innerHTML = html;
            document.getElementById('copy-result').style.display = 'block';
        } else {
            alert(response.message || '카피 생성 실패');
        }
    })
    .catch(function() {
        document.getElementById('copy-loading').classList.remove('active');
        alert('서버 오류가 발생했습니다');
    });
}

function generateImage() {
    var prompt = document.getElementById('image_prompt').value;
    var size = document.getElementById('image_size').value;
    var quality = document.getElementById('image_quality').value;

    if (!prompt) { alert('이미지 프롬프트를 입력해주세요'); return; }

    document.getElementById('image-loading').classList.add('active');
    document.getElementById('image-result').style.display = 'none';

    var formData = new FormData();
    formData.append('action', 'generate_image');
    formData.append('prompt', prompt);
    formData.append('size', size);
    formData.append('quality', quality);

    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(response) {
        document.getElementById('image-loading').classList.remove('active');
        if (response.success && response.data) {
            var d = response.data;
            var imgUrl = d.local_path || d.url;
            document.getElementById('generated-image').src = imgUrl;
            document.getElementById('revised-prompt').textContent = '프롬프트: ' + (d.revised_prompt || '');
            document.getElementById('final_image_url').value = imgUrl;
            document.getElementById('image-result').style.display = 'block';
        } else {
            alert(response.message || '이미지 생성 실패');
        }
    })
    .catch(function() {
        document.getElementById('image-loading').classList.remove('active');
        alert('서버 오류가 발생했습니다');
    });
}

function generateABTest() {
    var title = document.getElementById('ab_title').value;
    var subtitle = document.getElementById('ab_subtitle').value;
    if (!title || !subtitle) { alert('원본 제목과 부제목을 입력해주세요'); return; }
    document.getElementById('ab-loading').classList.add('active');
    document.getElementById('ab-result').style.display = 'none';
    var formData = new FormData();
    formData.append('action', 'generate_ab_test');
    formData.append('title', title);
    formData.append('subtitle', subtitle);
    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(response) {
        document.getElementById('ab-loading').classList.remove('active');
        if (response.success && response.data) {
            var d = response.data;
            document.getElementById('ab-a-title').textContent = d.variant_a.title;
            document.getElementById('ab-a-subtitle').textContent = d.variant_a.subtitle;
            document.getElementById('ab-b-title').textContent = d.variant_b.title;
            document.getElementById('ab-b-subtitle').textContent = d.variant_b.subtitle;
            document.getElementById('ab-result').style.display = 'grid';
        } else { alert(response.message || 'A/B 테스트 생성 실패'); }
    })
    .catch(function() {
        document.getElementById('ab-loading').classList.remove('active');
        alert('서버 오류가 발생했습니다');
    });
}

function saveRaffle() {
    var title = document.getElementById('final_title').value;
    if (!title) { alert('이벤트 제목은 필수입니다'); return; }
    var formData = new FormData();
    formData.append('action', 'save_raffle');
    formData.append('title', title);
    formData.append('subtitle', document.getElementById('final_subtitle').value);
    formData.append('description', document.getElementById('final_description').value);
    formData.append('raffle_prize', document.getElementById('final_prize').value);
    formData.append('raffle_type', document.getElementById('final_raffle_type').value);
    formData.append('max_entries', document.getElementById('final_max_entries').value);
    formData.append('raffle_winner_count', document.getElementById('final_winner_count').value);
    formData.append('raffle_end_date', document.getElementById('final_raffle_end_date').value);
    formData.append('image_url', document.getElementById('final_image_url').value);
    formData.append('hospital_name', document.getElementById('final_hospital_name').value);

    fetch('', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(response) {
        if (response.success) {
            var msg = document.getElementById('success-message');
            msg.textContent = '응모 이벤트가 저장되었습니다! (ID: ' + response.event_id + ')';
            msg.className = 'alert alert-success';
            msg.style.display = 'block';
            setTimeout(function() { msg.style.display = 'none'; }, 3000);
            loadHistory();
        } else {
            alert('저장 실패: ' + (response.message || ''));
        }
    })
    .catch(function() { alert('서버 오류가 발생했습니다'); });
}

function selectVariant(el) {
    document.querySelectorAll('.variant-card').forEach(function(c) { c.classList.remove('selected'); });
    el.classList.add('selected');
}
JSEOF;

if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>

<?php
/**
 * 이벤트 상세 페이지 (공개)
 * - gangnamunni_events 상세 정보 표시
 * - has_raffle=1 일 경우 응모 폼 표시
 * - AJAX 응모 → event_api.php?action=enter
 *
 * URL: event_detail.php?id=123
 */
require_once __DIR__ . '/includes/init.php';

// --- DB 연결 ---
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    http_response_code(500);
    echo '<p>서버 오류가 발생했습니다.</p>';
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// --- 이벤트 조회 ---
$event_id = intval($_GET['id'] ?? 0);
$event = null;
$event_images = [];
$already_entered = false;
$entry_count = 0;

if ($event_id > 0) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_events WHERE id = ? AND is_active = 1");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// 이벤트 없거나 비활성
if (!$event) {
    $page_title = '이벤트를 찾을 수 없습니다';
    $show_search = false;
    $page_css = <<<'CSS'
.not-found-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;padding:40px 20px}
.not-found-wrap .nf-icon{font-size:72px;color:#ccc;margin-bottom:16px}
.not-found-wrap h2{font-size:20px;font-weight:700;margin-bottom:8px;color:var(--bs-body-color, #333)}
.not-found-wrap p{color:#888;font-size:14px;margin-bottom:24px}
.not-found-wrap .btn-back{display:inline-block;padding:10px 28px;background:#AE1932;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px}
.not-found-wrap .btn-back:hover{background:#8A1428}
CSS;
    $page_js = '';
    include __DIR__ . '/includes/header.php';
?>
<div class="not-found-wrap">
    <div class="nf-icon">&#128533;</div>
    <h2>이벤트를 찾을 수 없습니다</h2>
    <p>요청하신 이벤트가 존재하지 않거나 종료되었습니다.</p>
    <a href="events.php" class="btn-back">이벤트 목록으로</a>
</div>
<?php
    include __DIR__ . '/includes/footer.php';
    mysqli_close($connect);
    exit;
}

// --- 추가 이미지 조회 ---
$img_stmt = mysqli_prepare($connect, "SELECT id, image_url, caption FROM gangnamunni_event_images WHERE event_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($img_stmt, 'i', $event_id);
mysqli_stmt_execute($img_stmt);
$img_result = mysqli_stmt_get_result($img_stmt);
while ($row = mysqli_fetch_assoc($img_result)) {
    $event_images[] = $row;
}
mysqli_stmt_close($img_stmt);

// --- 응모 관련 데이터 ---
if ($event['has_raffle']) {
    // 현재 응모자 수
    $cnt_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_event_entries WHERE event_id = ?");
    mysqli_stmt_bind_param($cnt_stmt, 'i', $event_id);
    mysqli_stmt_execute($cnt_stmt);
    $cnt_result = mysqli_stmt_get_result($cnt_stmt);
    $entry_count = intval(mysqli_fetch_assoc($cnt_result)['cnt']);
    mysqli_stmt_close($cnt_stmt);

    // 이미 응모했는지 확인 (로그인 회원: member_id, 비로그인: 세션)
    $is_logged_in = function_exists('is_gangnamunni_logged_in') ? is_gangnamunni_logged_in() : false;
    $member = null;
    if ($is_logged_in && function_exists('get_gangnamunni_member')) {
        $member = get_gangnamunni_member();
    }

    if ($member && !empty($member['mb_id'])) {
        $dup_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_event_entries WHERE event_id = ? AND member_id = ? LIMIT 1");
        $mb_id = $member['mb_id'];
        mysqli_stmt_bind_param($dup_stmt, 'is', $event_id, $mb_id);
        mysqli_stmt_execute($dup_stmt);
        $dup_result = mysqli_stmt_get_result($dup_stmt);
        $already_entered = (mysqli_num_rows($dup_result) > 0);
        mysqli_stmt_close($dup_stmt);
    } else {
        // 비로그인: 세션 기반 중복 확인
        if (!empty($_SESSION['event_entries'][$event_id])) {
            $already_entered = true;
        }
    }
}

// --- 기간 / 마감 상태 계산 ---
$now = date('Y-m-d H:i:s');
$event_ended = (!empty($event['end_date']) && $event['end_date'] < date('Y-m-d'));
$raffle_closed = false;
if ($event['has_raffle']) {
    $raffle_date_passed = (!empty($event['raffle_end_date']) && $event['raffle_end_date'] < date('Y-m-d'));
    $max_reached = ($event['max_entries'] > 0 && $entry_count >= $event['max_entries']);
    $raffle_closed = $raffle_date_passed || $max_reached;
}

// --- 가격 포맷 ---
$has_price = ($event['original_price'] > 0 || $event['discount_price'] > 0);

// --- 카테고리 배지 색상 ---
$category_colors = [
    '피부' => '#FF6B9D', '리프팅' => '#C56CF0', '보톡스' => '#7C5CFC',
    '필러' => '#3DC1D3', '눈' => '#63CDDA', '코' => '#F97F51',
    '지방흡입' => '#EAB543', '가슴' => '#FD7272', '치아' => '#58B19F',
    '탈모' => '#8B5CF6', '레이저' => '#06D6A0', '제모' => '#FFB8B8',
];
$cat_color = $category_colors[$event['category'] ?? ''] ?? '#AE1932';

// --- 응모 유형 라벨 ---
$raffle_type_labels = [
    'random' => ['label' => '랜덤 추첨', 'icon' => '&#127922;', 'desc' => '응모자 중 랜덤으로 당첨자를 선정합니다.'],
    'first_come' => ['label' => '선착순', 'icon' => '&#9889;', 'desc' => '선착순으로 당첨자가 결정됩니다.'],
    'quiz' => ['label' => '퀴즈 정답', 'icon' => '&#10067;', 'desc' => '퀴즈 정답자 중에서 당첨자를 선정합니다.'],
];
$raffle_info = $raffle_type_labels[$event['raffle_type'] ?? 'random'] ?? $raffle_type_labels['random'];

// --- 회원 정보 (폼 프리필) ---
$prefill_name = '';
$prefill_phone = '';
$prefill_email = '';
if (!empty($member)) {
    $prefill_name = htmlspecialchars($member['mb_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $prefill_phone = htmlspecialchars($member['mb_hp'] ?? '', ENT_QUOTES, 'UTF-8');
    $prefill_email = htmlspecialchars($member['mb_email'] ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 페이지 변수 ---
$page_title = '이벤트 상세';
$show_search = false;

$e_title = htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8');
$e_subtitle = htmlspecialchars($event['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
$e_desc = nl2br(htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8'));
$e_hospital = htmlspecialchars($event['hospital_name'] ?? '', ENT_QUOTES, 'UTF-8');
$e_image = htmlspecialchars($event['image_url'] ?? '', ENT_QUOTES, 'UTF-8');
$e_category = htmlspecialchars($event['category'] ?? '', ENT_QUOTES, 'UTF-8');
$e_prize = nl2br(htmlspecialchars($event['raffle_prize'] ?? '', ENT_QUOTES, 'UTF-8'));

$page_css = <<<'CSS'
/* ===== 이벤트 상세 페이지 ===== */
*{box-sizing:border-box}
body{background:var(--bs-tertiary-bg, #f5f5f5);margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Noto Sans KR',sans-serif;-webkit-font-smoothing:antialiased}
.ev-container{max-width:640px;margin:0 auto;padding:0 0 100px}

/* 상단 네비 */
.ev-topbar{display:flex;align-items:center;padding:12px 16px;background:var(--bs-body-bg, #fff);position:sticky;top:0;z-index:100;border-bottom:1px solid var(--bs-border-color, #eee)}
.ev-topbar .back-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:var(--bs-tertiary-bg, #f5f5f5);border:none;font-size:18px;cursor:pointer;color:var(--bs-body-color, #333);text-decoration:none;flex-shrink:0}
.ev-topbar .back-btn:hover{background:#eee}
.ev-topbar .topbar-title{flex:1;text-align:center;font-size:16px;font-weight:700;color:#222;margin-right:36px}

/* 이미지 갤러리 */
.ev-gallery{position:relative;background:#000}
.ev-gallery .main-img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block}
.ev-gallery .img-counter{position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.6);color:#fff;font-size:12px;padding:4px 10px;border-radius:20px}
.ev-thumbs{display:flex;gap:8px;padding:12px 16px;overflow-x:auto;background:var(--bs-body-bg, #fff);-webkit-overflow-scrolling:touch}
.ev-thumbs::-webkit-scrollbar{display:none}
.ev-thumbs .thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid transparent;cursor:pointer;flex-shrink:0;opacity:.7;transition:all .2s}
.ev-thumbs .thumb.active{border-color:#AE1932;opacity:1}
.ev-thumbs .thumb:hover{opacity:1}

/* 콘텐츠 영역 */
.ev-content{background:var(--bs-body-bg, #fff);padding:20px 16px;margin-bottom:8px}
.ev-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;color:#fff;margin-bottom:10px}
.ev-title{font-size:22px;font-weight:800;color:#111;line-height:1.35;margin:0 0 6px}
.ev-subtitle{font-size:14px;color:var(--bs-secondary-color, #666);margin:0 0 16px;line-height:1.5}
.ev-desc{font-size:14px;color:#444;line-height:1.7;margin:0;word-break:keep-all}

/* 가격 */
.ev-price-section{background:var(--bs-body-bg, #fff);padding:20px 16px;margin-bottom:8px}
.ev-price-section .price-label{font-size:12px;color:#999;margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.ev-price-row{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.ev-price-original{font-size:16px;color:#aaa;text-decoration:line-through}
.ev-price-discount{font-size:28px;font-weight:800;color:#AE1932}
.ev-price-rate{display:inline-block;padding:4px 10px;background:#AE1932;color:#fff;font-size:13px;font-weight:700;border-radius:6px}
.ev-price-unit{font-size:14px;color:var(--bs-secondary-color, #666);margin-left:2px}

/* 기간 */
.ev-period{background:var(--bs-body-bg, #fff);padding:16px;margin-bottom:8px}
.ev-period-row{display:flex;align-items:center;gap:10px;font-size:14px;color:#444}
.ev-period-row .period-icon{font-size:20px;color:#AE1932;flex-shrink:0}
.ev-period-row .period-dates{flex:1}
.ev-period-row .period-status{font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
.ev-period-row .status-active{background:#e8f5e9;color:#2e7d32}
.ev-period-row .status-ended{background:#ffebee;color:#c62828}

/* 응모 섹션 */
.ev-raffle{background:var(--bs-body-bg, #fff);padding:20px 16px;margin-bottom:8px}
.ev-raffle .raffle-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.ev-raffle .raffle-header .raffle-icon{font-size:28px}
.ev-raffle .raffle-header h3{font-size:18px;font-weight:800;color:#111;margin:0}
.ev-raffle .raffle-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.ev-raffle .raffle-info-card{background:#fafafa;border-radius:10px;padding:14px;text-align:center}
.ev-raffle .raffle-info-card .info-label{font-size:11px;color:#999;margin-bottom:4px;font-weight:600}
.ev-raffle .raffle-info-card .info-value{font-size:15px;font-weight:700;color:var(--bs-body-color, #333)}
.ev-raffle .raffle-prize-box{background:linear-gradient(135deg,#FFF5F0,#FFF0E8);border:1px solid #FFD4C0;border-radius:12px;padding:16px;margin-bottom:16px}
.ev-raffle .raffle-prize-box .prize-title{font-size:13px;font-weight:700;color:#AE1932;margin-bottom:6px}
.ev-raffle .raffle-prize-box .prize-content{font-size:14px;color:var(--bs-body-color, #333);line-height:1.6}
.ev-raffle .raffle-type-desc{font-size:13px;color:var(--bs-secondary-color, #666);background:var(--bs-tertiary-bg, #f8f8f8);padding:12px 14px;border-radius:8px;margin-bottom:16px;line-height:1.5}
.ev-raffle .raffle-progress{margin-bottom:16px}
.ev-raffle .raffle-progress .progress-bar-wrap{height:8px;background:#eee;border-radius:4px;overflow:hidden;margin-bottom:6px}
.ev-raffle .raffle-progress .progress-fill{height:100%;background:linear-gradient(90deg,#AE1932,#FF8A50);border-radius:4px;transition:width .5s}
.ev-raffle .raffle-progress .progress-text{display:flex;justify-content:space-between;font-size:12px;color:#888}

/* 응모 폼 */
.ev-raffle-form{border-top:1px solid var(--bs-border-color, #eee);padding-top:16px}
.ev-raffle-form .form-group{margin-bottom:14px}
.ev-raffle-form .form-label{display:block;font-size:13px;font-weight:600;color:var(--bs-body-color, #333);margin-bottom:6px}
.ev-raffle-form .form-label .required{color:#AE1932;margin-left:2px}
.ev-raffle-form .form-input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:10px;font-size:14px;outline:none;transition:border-color .2s;background:var(--bs-body-bg, #fff);-webkit-appearance:none}
.ev-raffle-form .form-input:focus{border-color:#AE1932;box-shadow:0 0 0 3px rgba(174,25,50,.1)}
.ev-raffle-form .form-input::placeholder{color:#bbb}
.ev-raffle-form .btn-submit{width:100%;padding:14px;background:#AE1932;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px}
.ev-raffle-form .btn-submit:hover{background:#8A1428;transform:translateY(-1px);box-shadow:0 4px 12px rgba(174,25,50,.3)}
.ev-raffle-form .btn-submit:active{transform:translateY(0)}
.ev-raffle-form .btn-submit:disabled{background:#ccc;cursor:not-allowed;transform:none;box-shadow:none}
.ev-raffle-form .btn-submit.loading{position:relative;color:transparent}
.ev-raffle-form .btn-submit.loading::after{content:'';position:absolute;top:50%;left:50%;width:22px;height:22px;margin:-11px 0 0 -11px;border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:ev-spin .6s linear infinite}
@keyframes ev-spin{to{transform:rotate(360deg)}}

/* 응모 완료/마감 상태 */
.ev-raffle-status{text-align:center;padding:24px 16px;border-top:1px solid var(--bs-border-color, #eee)}
.ev-raffle-status .status-icon{font-size:48px;margin-bottom:10px}
.ev-raffle-status .status-text{font-size:16px;font-weight:700;margin-bottom:4px}
.ev-raffle-status .status-desc{font-size:13px;color:#888}
.ev-raffle-status.entered .status-text{color:#2e7d32}
.ev-raffle-status.closed .status-text{color:#c62828}

/* 병원 링크 */
.ev-hospital{background:var(--bs-body-bg, #fff);padding:16px;margin-bottom:8px}
.ev-hospital a{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--bs-body-color, #333);padding:10px 14px;background:var(--bs-tertiary-bg, #f8f8f8);border-radius:12px;transition:background .2s}
.ev-hospital a:hover{background:#f0f0f0}
.ev-hospital .hosp-icon{width:40px;height:40px;border-radius:50%;background:#AE1932;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ev-hospital .hosp-name{font-size:14px;font-weight:600;color:var(--bs-body-color, #333)}
.ev-hospital .hosp-arrow{margin-left:auto;color:#999;font-size:18px}

/* 토스트 */
.ev-toast{position:fixed;top:20px;left:50%;transform:translateX(-50%) translateY(-100px);background:#333;color:#fff;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:500;z-index:9999;opacity:0;transition:all .3s;max-width:90%;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.2)}
.ev-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.ev-toast.success{background:#2e7d32}
.ev-toast.error{background:#c62828}

/* 반응형 */
@media(max-width:400px){
    .ev-title{font-size:18px}
    .ev-price-discount{font-size:24px}
    .ev-raffle .raffle-info-grid{grid-template-columns:1fr}
}
CSS;

$page_js_data = json_encode([
    'event_id' => $event_id,
    'is_logged_in' => !empty($member),
    'already_entered' => $already_entered,
    'raffle_closed' => $raffle_closed,
    'has_raffle' => (bool)$event['has_raffle'],
], JSON_UNESCAPED_UNICODE);

$page_js = <<<JSEOF
(function(){
    var PAGE_DATA = {$page_js_data};
    var allImages = [];
    var currentImageIndex = 0;

    // --- 이미지 갤러리 ---
    function initGallery() {
        var thumbs = document.querySelectorAll('.ev-thumbs .thumb');
        var mainImg = document.getElementById('evMainImg');
        var counter = document.getElementById('evImgCounter');
        if (!mainImg) return;

        // 모든 이미지 수집 (메인 + 추가)
        allImages.push(mainImg.src);
        thumbs.forEach(function(t){ allImages.push(t.dataset.src); });

        if (counter) counter.textContent = '1 / ' + allImages.length;

        thumbs.forEach(function(t, i) {
            t.addEventListener('click', function() {
                currentImageIndex = i + 1;
                mainImg.src = t.dataset.src;
                thumbs.forEach(function(x){ x.classList.remove('active'); });
                t.classList.add('active');
                if (counter) counter.textContent = (currentImageIndex + 1) + ' / ' + allImages.length;
            });
        });

        // 메인 이미지 클릭으로 순환
        mainImg.addEventListener('click', function() {
            currentImageIndex = (currentImageIndex + 1) % allImages.length;
            mainImg.src = allImages[currentImageIndex];
            thumbs.forEach(function(x, i){ x.classList.toggle('active', i === currentImageIndex - 1); });
            if (counter) counter.textContent = (currentImageIndex + 1) + ' / ' + allImages.length;
        });
    }

    // --- 토스트 ---
    function showToast(msg, type) {
        var existing = document.querySelector('.ev-toast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.className = 'ev-toast ' + (type || '');
        toast.textContent = msg;
        document.body.appendChild(toast);
        requestAnimationFrame(function(){
            requestAnimationFrame(function(){ toast.classList.add('show'); });
        });
        setTimeout(function(){
            toast.classList.remove('show');
            setTimeout(function(){ toast.remove(); }, 300);
        }, 3000);
    }

    // --- 전화번호 자동 하이픈 ---
    function formatPhone(input) {
        var val = input.value.replace(/[^0-9]/g, '');
        if (val.length <= 3) {
            input.value = val;
        } else if (val.length <= 7) {
            input.value = val.substring(0, 3) + '-' + val.substring(3);
        } else {
            input.value = val.substring(0, 3) + '-' + val.substring(3, 7) + '-' + val.substring(7, 11);
        }
    }

    // --- 응모 폼 제출 ---
    function submitEntry(e) {
        e.preventDefault();
        var form = document.getElementById('raffleForm');
        if (!form) return;

        // ====================================================================
        // [프론트 #23 - 2026-05-28] 응모 이벤트 로그인 풀림 + 404 + 외부 도메인 픽스
        // 원인 : 기존 '../bbs/login.php' 상대경로가 Cloudflare/Apache 환경에서
        //        잘못 해석되어 /hairwang 컨텍스트 빠진 경로로 이동.
        //        404 페이지 → Back 클릭 시 https://hms.seoulbeauty.app/ 루트로 이동.
        // 변경 : 절대경로 '/hairwang/bbs/login.php' 사용으로 정확한 경로 보장.
        // 영향 범위 : event_detail.php JS 로그인 redirect만. 다른 페이지 영향 없음.
        // ====================================================================
        /* 기존 코드 (보존)
        if (!PAGE_DATA.is_logged_in) {
            if (!confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) return;
            window.location.href = '../bbs/login.php?url=' + encodeURIComponent(window.location.href);
            return;
        }
        */
        // 로그인 확인 (절대경로 사용)
        if (!PAGE_DATA.is_logged_in) {
            if (!confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) return;
            window.location.href = '/hairwang/bbs/login.php?url=' + encodeURIComponent(window.location.href);
            return;
        }

        var name = form.querySelector('[name="user_name"]').value.trim();
        var phone = form.querySelector('[name="user_phone"]').value.trim();
        var email = form.querySelector('[name="user_email"]').value.trim();

        if (!name) { showToast('이름을 입력해주세요.', 'error'); form.querySelector('[name="user_name"]').focus(); return; }
        if (!phone || phone.replace(/[^0-9]/g,'').length < 10) { showToast('올바른 전화번호를 입력해주세요.', 'error'); form.querySelector('[name="user_phone"]').focus(); return; }

        var btn = form.querySelector('.btn-submit');
        btn.disabled = true;
        btn.classList.add('loading');

        var formData = new FormData();
        formData.append('action', 'enter');
        formData.append('event_id', PAGE_DATA.event_id);
        formData.append('user_name', name);
        formData.append('user_phone', phone.replace(/[^0-9]/g, ''));
        formData.append('user_email', email);

        fetch('event_api.php?action=enter', {
            method: 'POST',
            body: formData
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            btn.disabled = false;
            btn.classList.remove('loading');
            if (res.success) {
                showToast('응모가 완료되었습니다!', 'success');
                // 폼을 응모 완료 상태로 교체
                var formWrap = document.getElementById('raffleFormWrap');
                if (formWrap) {
                    formWrap.innerHTML = '<div class="ev-raffle-status entered">'
                        + '<div class="status-icon">&#9989;</div>'
                        + '<div class="status-text">응모가 완료되었습니다</div>'
                        + '<div class="status-desc">당첨 결과는 마감일 이후 발표됩니다.</div>'
                        + '</div>';
                }
                // 응모자 수 업데이트
                var countEl = document.getElementById('entryCountVal');
                if (countEl) {
                    var cur = parseInt(countEl.textContent) || 0;
                    countEl.textContent = cur + 1;
                }
                var progressFill = document.getElementById('progressFill');
                var maxEntries = parseInt(document.getElementById('maxEntriesVal')?.textContent) || 0;
                if (progressFill && maxEntries > 0) {
                    var newCount = (parseInt(countEl?.textContent) || 0);
                    progressFill.style.width = Math.min(100, (newCount / maxEntries) * 100) + '%';
                }
            } else {
                showToast(res.message || '응모에 실패했습니다.', 'error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.classList.remove('loading');
            showToast('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'error');
        });
    }

    // --- 초기화 ---
    document.addEventListener('DOMContentLoaded', function(){
        initGallery();
        var form = document.getElementById('raffleForm');
        if (form) {
            form.addEventListener('submit', submitEntry);
        }
        var phoneInput = document.getElementById('entryPhone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(){ formatPhone(this); });
        }
    });
})();
JSEOF;

include __DIR__ . '/includes/header.php';
?>

<div class="ev-container">

    <!-- 상단 네비 -->
    <div class="ev-topbar">
        <a href="events.php" class="back-btn" title="뒤로가기">&#8592;</a>
        <div class="topbar-title"><?php echo $e_title; ?></div>
    </div>

    <!-- 이미지 갤러리 -->
    <?php if ($e_image): ?>
    <div class="ev-gallery">
        <img id="evMainImg" class="main-img" src="<?php echo $e_image; ?>" alt="<?php echo $e_title; ?>" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22640%22 height=%22400%22><rect fill=%22%23f0f0f0%22 width=%22640%22 height=%22400%22/><text fill=%22%23aaa%22 font-size=%2216%22 x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>No Image</text></svg>'">
        <?php if (count($event_images) > 0): ?>
        <div class="img-counter" id="evImgCounter">1 / <?php echo count($event_images) + 1; ?></div>
        <?php endif; ?>
    </div>
    <?php if (count($event_images) > 0): ?>
    <div class="ev-thumbs">
        <img class="thumb active" src="<?php echo $e_image; ?>" data-src="<?php echo $e_image; ?>" alt="메인 이미지">
        <?php foreach ($event_images as $ei): ?>
        <img class="thumb" src="<?php echo htmlspecialchars($ei['image_url'], ENT_QUOTES, 'UTF-8'); ?>" data-src="<?php echo htmlspecialchars($ei['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ei['caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 카테고리 + 제목 + 설명 -->
    <div class="ev-content">
        <?php if ($e_category): ?>
        <span class="ev-badge" style="background:<?php echo $cat_color; ?>"><?php echo $e_category; ?></span>
        <?php endif; ?>
        <h1 class="ev-title"><?php echo $e_title; ?></h1>
        <?php if ($e_subtitle): ?>
        <p class="ev-subtitle"><?php echo $e_subtitle; ?></p>
        <?php endif; ?>
        <?php if ($e_desc): ?>
        <div class="ev-desc"><?php echo $e_desc; ?></div>
        <?php endif; ?>
    </div>

    <!-- 가격 -->
    <?php if ($has_price): ?>
    <div class="ev-price-section">
        <div class="price-label">가격 안내</div>
        <div class="ev-price-row">
            <?php if ($event['original_price'] > 0 && $event['discount_price'] > 0 && $event['original_price'] > $event['discount_price']): ?>
                <span class="ev-price-original"><?php echo number_format($event['original_price']); ?>원</span>
                <span class="ev-price-discount"><?php echo number_format($event['discount_price']); ?><span class="ev-price-unit">원</span></span>
                <?php if ($event['discount_rate'] > 0): ?>
                <span class="ev-price-rate"><?php echo intval($event['discount_rate']); ?>% OFF</span>
                <?php endif; ?>
            <?php elseif ($event['discount_price'] > 0): ?>
                <span class="ev-price-discount"><?php echo number_format($event['discount_price']); ?><span class="ev-price-unit">원</span></span>
            <?php elseif ($event['original_price'] > 0): ?>
                <span class="ev-price-discount"><?php echo number_format($event['original_price']); ?><span class="ev-price-unit">원</span></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 기간 -->
    <?php if (!empty($event['start_date']) || !empty($event['end_date'])): ?>
    <div class="ev-period">
        <div class="ev-period-row">
            <span class="period-icon">&#128197;</span>
            <div class="period-dates">
                <?php
                    $start = $event['start_date'] ? date('Y.m.d', strtotime($event['start_date'])) : '';
                    $end = $event['end_date'] ? date('Y.m.d', strtotime($event['end_date'])) : '';
                    echo $start . ' ~ ' . $end;
                ?>
            </div>
            <?php if ($event_ended): ?>
            <span class="period-status status-ended">종료</span>
            <?php else: ?>
            <span class="period-status status-active">진행중</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 응모 섹션 -->
    <?php if ($event['has_raffle']): ?>
    <div class="ev-raffle">
        <div class="raffle-header">
            <span class="raffle-icon"><?php echo $raffle_info['icon']; ?></span>
            <h3>응모 이벤트</h3>
        </div>

        <!-- 응모 유형 설명 -->
        <div class="raffle-type-desc">
            <strong><?php echo $raffle_info['label']; ?></strong> &mdash; <?php echo $raffle_info['desc']; ?>
        </div>

        <!-- 응모 정보 그리드 -->
        <div class="raffle-info-grid">
            <div class="raffle-info-card">
                <div class="info-label">응모 유형</div>
                <div class="info-value"><?php echo $raffle_info['label']; ?></div>
            </div>
            <div class="raffle-info-card">
                <div class="info-label">당첨자 수</div>
                <div class="info-value"><?php echo intval($event['raffle_winner_count']); ?>명</div>
            </div>
            <div class="raffle-info-card">
                <div class="info-label">현재 응모</div>
                <div class="info-value"><span id="entryCountVal"><?php echo $entry_count; ?></span><?php if ($event['max_entries'] > 0): ?> / <span id="maxEntriesVal"><?php echo intval($event['max_entries']); ?></span><?php endif; ?></div>
            </div>
            <div class="raffle-info-card">
                <div class="info-label">응모 마감</div>
                <div class="info-value"><?php echo $event['raffle_end_date'] ? date('m.d', strtotime($event['raffle_end_date'])) : '-'; ?></div>
            </div>
        </div>

        <!-- 경품 -->
        <?php if ($e_prize): ?>
        <div class="raffle-prize-box">
            <div class="prize-title">&#127873; 경품 안내</div>
            <div class="prize-content"><?php echo $e_prize; ?></div>
        </div>
        <?php endif; ?>

        <!-- 응모 진행률 -->
        <?php if ($event['max_entries'] > 0): ?>
        <div class="raffle-progress">
            <div class="progress-bar-wrap">
                <div class="progress-fill" id="progressFill" style="width:<?php echo min(100, ($entry_count / max(1, $event['max_entries'])) * 100); ?>%"></div>
            </div>
            <div class="progress-text">
                <span><?php echo $entry_count; ?>명 응모</span>
                <span><?php echo intval($event['max_entries']); ?>명 정원</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- 응모 폼 / 완료 / 마감 상태 -->
        <div id="raffleFormWrap">
            <?php if ($already_entered): ?>
                <!-- 이미 응모한 경우 -->
                <div class="ev-raffle-status entered">
                    <div class="status-icon">&#9989;</div>
                    <div class="status-text">응모가 완료되었습니다</div>
                    <div class="status-desc">당첨 결과는 마감일 이후 발표됩니다.</div>
                </div>
            <?php elseif ($raffle_closed): ?>
                <!-- 마감된 경우 -->
                <div class="ev-raffle-status closed">
                    <div class="status-icon">&#128683;</div>
                    <div class="status-text">응모가 마감되었습니다</div>
                    <div class="status-desc">
                        <?php if (!empty($event['raffle_end_date']) && $event['raffle_end_date'] < date('Y-m-d')): ?>
                            응모 기간이 종료되었습니다.
                        <?php else: ?>
                            최대 응모 인원에 도달했습니다.
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- 응모 폼 -->
                <form class="ev-raffle-form" id="raffleForm" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label">이름 <span class="required">*</span></label>
                        <input type="text" name="user_name" class="form-input" placeholder="실명을 입력해주세요" value="<?php echo $prefill_name; ?>" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">연락처 <span class="required">*</span></label>
                        <input type="tel" name="user_phone" id="entryPhone" class="form-input" placeholder="010-0000-0000" value="<?php echo $prefill_phone; ?>" maxlength="13" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">이메일</label>
                        <input type="email" name="user_email" class="form-input" placeholder="example@email.com" value="<?php echo $prefill_email; ?>">
                    </div>
                    <button type="submit" class="btn-submit">응모하기</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 병원 링크 -->
    <?php if ($e_hospital): ?>
    <div class="ev-hospital">
        <?php if ($event['hospital_id'] > 0): ?>
        <a href="detail.php?id=<?php echo intval($event['hospital_id']); ?>">
        <?php else: ?>
        <a href="hospitals.php">
        <?php endif; ?>
            <div class="hosp-icon">&#127973;</div>
            <div>
                <div class="hosp-name"><?php echo $e_hospital; ?></div>
            </div>
            <span class="hosp-arrow">&#8250;</span>
        </a>
    </div>
    <?php endif; ?>

</div>

<?php
// CPV 추적
$cpv_page_type = 'event';
$cpv_target_id = $event_id;
include __DIR__ . '/includes/cpv_tracker.php';

include __DIR__ . '/includes/report_block_modal.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>

<?php
/**
 * 전문 피드백 패널 v2
 * - 저장 시 html2canvas 자동 스크린샷
 * - 서버 업로드 + DB 저장
 * - 스크린샷 썸네일 표시
 * @created 2026-04-06, upgraded 2026-04-07
 */
if (!function_exists('isDevMode') || !isset($connect) || !isDevMode($connect)) return;

$fb_page_id = basename($_SERVER['PHP_SELF'] ?? 'unknown');
$fb_page_title = $admin_page_title ?? $page_title ?? $fb_page_id;
$fb_submitted_by = $_SESSION['ad_admin_user'] ?? $_SESSION['ad_admin_name'] ?? 'anonymous';
?>

<!-- html2canvas CDN -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<!-- DEV Feedback Panel v2 -->
<div id="devFeedbackPanel" style="position:fixed;bottom:0;left:0;right:0;z-index:9999;">
    <div id="devFbTab" onclick="toggleFeedbackPanel()" style="position:absolute;top:-36px;right:20px;background:linear-gradient(135deg,#ff6b35,#f7931e);color:#fff;padding:6px 16px;border-radius:8px 8px 0 0;cursor:pointer;font-size:13px;font-weight:700;box-shadow:0 -2px 8px rgba(0,0,0,.15);">
        <i class="bi bi-chat-dots me-1"></i> 피드백 <span id="devFbBadge" class="badge bg-white text-dark ms-1" style="display:none;">0</span>
    </div>
    <div id="devFbBody" style="display:none;background:var(--bs-body-bg,#fff);border-top:3px solid #ff6b35;box-shadow:0 -4px 20px rgba(0,0,0,.15);padding:16px 20px;max-height:70vh;overflow-y:auto;">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <strong style="color:#ff6b35;"><i class="bi bi-gear-fill me-1"></i>DEV FEEDBACK PRO</strong>
                <small class="text-muted ms-2"><?= htmlspecialchars($fb_page_title) ?></small>
            </div>
            <button type="button" class="btn-close" onclick="toggleFeedbackPanel()"></button>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-auto">
                <select id="devFbType" class="form-select form-select-sm" style="width:130px;">
                    <option value="bug">버그 수정</option>
                    <option value="improve" selected>개선 요청</option>
                    <option value="feature">신규 기능</option>
                    <option value="question">질문/확인</option>
                    <option value="other">기타</option>
                </select>
            </div>
            <div class="col-auto">
                <select id="devFbPriority" class="form-select form-select-sm" style="width:110px;">
                    <option value="low">낮음</option>
                    <option value="medium" selected>보통</option>
                    <option value="high">높음</option>
                    <option value="urgent">긴급</option>
                </select>
            </div>
            <div class="col">
                <input type="text" id="devFbSection" class="form-control form-control-sm" placeholder="섹션/영역 (선택)" style="max-width:200px;">
            </div>
        </div>
        <textarea id="devFbContent" class="form-control mb-2" rows="7" placeholder="수정사항, 기능 요청, 버그 설명 등을 자유롭게 입력하세요..." style="resize:vertical;min-height:140px;font-size:14px;"></textarea>

        <!-- 스크린샷 미리보기 -->
        <div id="devFbScreenshotPreview" class="mb-2" style="display:none;">
            <div class="d-flex align-items-center gap-2 mb-1">
                <small class="text-muted fw-bold"><i class="bi bi-camera me-1"></i>자동 캡처 미리보기</small>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="captureScreenshot()" style="font-size:11px;"><i class="bi bi-arrow-clockwise me-1"></i>다시 캡처</button>
            </div>
            <img id="devFbScreenshotImg" style="max-width:100%;max-height:200px;border:1px solid var(--bs-border-color);border-radius:6px;cursor:pointer;" onclick="showModal(this)">
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($fb_submitted_by) ?></small>
                <small class="text-muted ms-2" id="devFbCaptureStatus"></small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="captureScreenshot()" title="스크린샷 미리보기"><i class="bi bi-camera"></i></button>
                <a href="admin_dev_feedback.php" class="btn btn-outline-secondary btn-sm me-1" target="_blank"><i class="bi bi-list-ul"></i></a>
                <button type="button" class="btn btn-sm fw-bold text-white" style="background:#ff6b35;" onclick="submitFeedbackPro()" id="devFbSubmitBtn">
                    <i class="bi bi-send me-1"></i>저장 (스크린샷 자동 첨부)
                </button>
            </div>
        </div>

        <!-- 이 페이지 피드백 이력 -->
        <div id="devFbHistory" class="mt-2" style="display:none;">
            <hr class="my-2">
            <small class="text-muted fw-bold">이 페이지의 피드백</small>
            <div id="devFbList" style="max-height:200px;overflow-y:auto;font-size:13px;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const PAGE_ID = '<?= addslashes($fb_page_id) ?>';
    const PAGE_URL = location.href;
    const PAGE_TITLE = '<?= addslashes($fb_page_title) ?>';
    const SUBMITTED_BY = '<?= addslashes($fb_submitted_by) ?>';
    const API = 'api_dev_feedback.php';
    let capturedDataUrl = null;

    const panelOpen = localStorage.getItem('devFbOpen') === '1';
    if (panelOpen) document.getElementById('devFbBody').style.display = 'block';
    loadPageFeedback();

    window.toggleFeedbackPanel = function() {
        const body = document.getElementById('devFbBody');
        const isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
        localStorage.setItem('devFbOpen', isOpen ? '0' : '1');
    };

    // 스크린샷 캡처
    window.captureScreenshot = function() {
        const status = document.getElementById('devFbCaptureStatus');
        status.textContent = '캡처 중...';
        // 패널 숨긴 후 캡처
        const panel = document.getElementById('devFeedbackPanel');
        panel.style.display = 'none';

        html2canvas(document.body, {
            scale: 0.7,
            useCORS: true,
            allowTaint: true,
            logging: false,
            width: window.innerWidth,
            height: window.innerHeight,
            scrollX: -window.scrollX,
            scrollY: -window.scrollY,
            windowWidth: document.documentElement.scrollWidth,
            windowHeight: window.innerHeight,
        }).then(canvas => {
            capturedDataUrl = canvas.toDataURL('image/jpeg', 0.7);
            const preview = document.getElementById('devFbScreenshotPreview');
            const img = document.getElementById('devFbScreenshotImg');
            img.src = capturedDataUrl;
            preview.style.display = 'block';
            status.textContent = '캡처 완료';
            panel.style.display = '';
        }).catch(e => {
            status.textContent = '캡처 실패';
            panel.style.display = '';
            console.error('Screenshot error:', e);
        });
    };

    // 피드백 저장 (스크린샷 자동 첨부)
    window.submitFeedbackPro = function() {
        const content = document.getElementById('devFbContent').value.trim();
        if (!content) { alert('내용을 입력해주세요'); return; }

        const btn = document.getElementById('devFbSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';

        // 1단계: 스크린샷 캡처 (아직 없으면)
        const doCapture = capturedDataUrl ? Promise.resolve() : new Promise(resolve => {
            const panel = document.getElementById('devFeedbackPanel');
            panel.style.display = 'none';
            html2canvas(document.body, {
                scale: 0.7, useCORS: true, allowTaint: true, logging: false,
                width: window.innerWidth, height: window.innerHeight,
                scrollX: -window.scrollX, scrollY: -window.scrollY,
                windowWidth: document.documentElement.scrollWidth, windowHeight: window.innerHeight,
            }).then(canvas => {
                capturedDataUrl = canvas.toDataURL('image/jpeg', 0.7);
                panel.style.display = '';
                resolve();
            }).catch(() => { panel.style.display = ''; resolve(); });
        });

        doCapture.then(() => {
            // 2단계: 피드백 + 스크린샷 전송
            const data = {
                page_id: PAGE_ID, page_url: PAGE_URL, page_title: PAGE_TITLE,
                section: document.getElementById('devFbSection').value.trim(),
                feedback_type: document.getElementById('devFbType').value,
                priority: document.getElementById('devFbPriority').value,
                content: content,
                submitted_by: SUBMITTED_BY,
                screenshot: capturedDataUrl || null,
            };

            return fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>저장 (스크린샷 자동 첨부)';

            if (res.success) {
                document.getElementById('devFbContent').value = '';
                document.getElementById('devFbSection').value = '';
                document.getElementById('devFbScreenshotPreview').style.display = 'none';
                capturedDataUrl = null;

                const toast = document.createElement('div');
                toast.className = 'alert alert-success py-2 px-3 position-fixed shadow';
                toast.style.cssText = 'bottom:80px;right:20px;z-index:99999;font-size:13px;';
                toast.innerHTML = '<i class="bi bi-check-circle me-1"></i>피드백 #' + res.id + ' 저장 완료' + (res.screenshot_url ? ' (스크린샷 첨부)' : '');
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
                loadPageFeedback();
            } else {
                alert('저장 실패: ' + (res.message || res.error));
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>저장 (스크린샷 자동 첨부)';
            alert('네트워크 오류: ' + e.message);
        });
    };

    function loadPageFeedback() {
        fetch(API + '?page_id=' + encodeURIComponent(PAGE_ID))
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.length > 0) {
                const list = document.getElementById('devFbList');
                const history = document.getElementById('devFbHistory');
                const badge = document.getElementById('devFbBadge');
                history.style.display = 'block';
                badge.style.display = 'inline';
                badge.textContent = res.data.length;

                const typeIcons = { bug:'bug', improve:'arrow-up-circle', feature:'plus-circle', question:'question-circle', other:'three-dots' };
                const statusColors = { open:'warning', in_progress:'info', done:'success', rejected:'secondary' };

                list.innerHTML = res.data.map(fb => {
                    const icon = typeIcons[fb.feedback_type] || 'chat';
                    const color = statusColors[fb.status] || 'secondary';
                    const date = fb.created_at ? fb.created_at.substring(5, 16) : '';
                    const ssThumb = fb.screenshot_url ? '<img src="' + fb.screenshot_url + '" style="max-width:60px;max-height:40px;border-radius:4px;margin-top:4px;" onclick="showModal(this)">' : '';
                    return '<div class="d-flex align-items-start gap-2 py-1 border-bottom">' +
                        '<i class="bi bi-' + icon + ' text-' + color + ' mt-1"></i>' +
                        '<div class="flex-grow-1"><div>' + fb.content.substring(0,80) + (fb.content.length>80?'...':'') + '</div>' +
                        ssThumb +
                        '<small class="text-muted">' + date + ' | <span class="badge bg-' + color + '">' + fb.status + '</span></small></div></div>';
                }).join('');
            }
        }).catch(() => {});
    }
})();
</script>

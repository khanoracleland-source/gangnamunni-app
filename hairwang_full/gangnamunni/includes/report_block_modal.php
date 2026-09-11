<?php
/**
 * 신고 / 차단 모달 공통 컴포넌트
 * Apple App Store Guideline 1.2 준수 — 모든 UGC 페이지에서 사용
 *
 * 사용법: 페이지 하단에서 include 후 JS 함수 호출
 *   <?php include __DIR__ . '/includes/report_block_modal.php'; ?>
 *
 *   openReportModal('review', 123, 456, '홍길동');
 *   openBlockModal(456, '홍길동');
 *
 * API 엔드포인트:
 *   - api_report_content.php  (POST: content_type, content_id, reported_user_id, reason, detail)
 *   - api_block_user.php      (POST: blocked_id)
 *
 * @created 2026-03-17
 */

// 중복 로드 방지
if (defined('REPORT_BLOCK_MODAL_LOADED')) return;
define('REPORT_BLOCK_MODAL_LOADED', true);
?>

<!-- ===== 신고/차단 모달 CSS ===== -->
<style>
/* ---------- 공통 오버레이 ---------- */
.rb-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.rb-overlay.rb-visible {
    display: flex;
}
.rb-overlay.rb-fade-in {
    opacity: 1;
}

/* ---------- 모달 패널 ---------- */
.rb-modal {
    background: var(--bs-body-bg, #fff);
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    transform: translateY(20px) scale(0.97);
    transition: transform 0.25s ease;
}
.rb-overlay.rb-fade-in .rb-modal {
    transform: translateY(0) scale(1);
}
.rb-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px 12px;
    border-bottom: 1px solid var(--bs-border-color, #f0f0f0);
}
.rb-modal-title {
    font-size: 18px;
    font-weight: 700;
    color: #111;
    margin: 0;
}
.rb-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #888;
    font-size: 22px;
    line-height: 1;
    transition: color 0.15s;
}
.rb-close-btn:hover {
    color: var(--bs-body-color, #333);
}
.rb-modal-body {
    padding: 16px 24px;
}
.rb-modal-footer {
    display: flex;
    gap: 10px;
    padding: 12px 24px 20px;
    border-top: 1px solid var(--bs-border-color, #f0f0f0);
}

/* ---------- 신고 사유 카드/필 ---------- */
.rb-reason-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}
.rb-reason-item {
    display: none; /* 실제 라디오 숨김 */
}
.rb-reason-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1.5px solid #e5e7eb;
    border-radius: 24px;
    font-size: 14px;
    font-weight: 500;
    color: #555;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    background: #fafafa;
}
.rb-reason-label:hover {
    border-color: #c4c8cd;
    background: #f3f4f6;
}
.rb-reason-item:checked + .rb-reason-label {
    border-color: #ef4444;
    background: #fef2f2;
    color: #dc2626;
    font-weight: 600;
}
.rb-reason-label .rb-reason-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 2px solid #ccc;
    flex-shrink: 0;
    transition: all 0.15s;
}
.rb-reason-item:checked + .rb-reason-label .rb-reason-dot {
    border-color: #ef4444;
    background: #ef4444;
}

/* ---------- 텍스트에어리어 ---------- */
.rb-textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    color: var(--bs-body-color, #333);
    background: #fafafa;
    resize: vertical;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.rb-textarea:focus {
    outline: none;
    border-color: #ef4444;
    background: var(--bs-body-bg, #fff);
}
.rb-textarea::placeholder {
    color: #aaa;
}

/* ---------- 버튼 ---------- */
.rb-btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}
.rb-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.rb-btn-cancel {
    background: #f3f4f6;
    color: #555;
}
.rb-btn-cancel:hover {
    background: #e5e7eb;
}
.rb-btn-danger {
    background: #ef4444;
    color: #fff;
}
.rb-btn-danger:hover:not(:disabled) {
    background: #dc2626;
}

/* ---------- 차단 설명 목록 ---------- */
.rb-block-info {
    background: #f9fafb;
    border-radius: 10px;
    padding: 16px;
    margin: 12px 0 4px;
}
.rb-block-info ul {
    margin: 0;
    padding: 0 0 0 20px;
    list-style: disc;
}
.rb-block-info li {
    font-size: 13px;
    color: var(--bs-secondary-color, #666);
    line-height: 1.7;
}
.rb-block-name {
    font-weight: 700;
    color: #111;
}
.rb-block-msg {
    font-size: 15px;
    color: var(--bs-body-color, #333);
    line-height: 1.6;
    margin-bottom: 4px;
}

/* ---------- 토스트 ---------- */
.rb-toast {
    position: fixed;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    max-width: 400px;
    width: calc(100% - 32px);
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    text-align: center;
    z-index: 10001;
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.3s ease;
    pointer-events: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
.rb-toast.rb-toast-show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
.rb-toast-success { background: #16a34a; }
.rb-toast-error { background: #ef4444; }
.rb-toast-warning { background: #f59e0b; color: #111; }

/* ---------- 다크모드 ---------- */
[data-bs-theme="dark"] .rb-modal {
    background: #1e1e2e;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}
[data-bs-theme="dark"] .rb-modal-header {
    border-bottom-color: #333;
}
[data-bs-theme="dark"] .rb-modal-title {
    color: #e5e5e5;
}
[data-bs-theme="dark"] .rb-close-btn {
    color: #888;
}
[data-bs-theme="dark"] .rb-close-btn:hover {
    color: #ccc;
}
[data-bs-theme="dark"] .rb-modal-footer {
    border-top-color: #333;
}
[data-bs-theme="dark"] .rb-reason-label {
    border-color: #444;
    background: #2a2a3d;
    color: #ccc;
}
[data-bs-theme="dark"] .rb-reason-label:hover {
    border-color: #555;
    background: #333;
}
[data-bs-theme="dark"] .rb-reason-item:checked + .rb-reason-label {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
[data-bs-theme="dark"] .rb-reason-label .rb-reason-dot {
    border-color: #555;
}
[data-bs-theme="dark"] .rb-textarea {
    border-color: #444;
    background: #2a2a3d;
    color: #ddd;
}
[data-bs-theme="dark"] .rb-textarea:focus {
    border-color: #ef4444;
    background: #1e1e2e;
}
[data-bs-theme="dark"] .rb-textarea::placeholder {
    color: #666;
}
[data-bs-theme="dark"] .rb-btn-cancel {
    background: #333;
    color: #ccc;
}
[data-bs-theme="dark"] .rb-btn-cancel:hover {
    background: #444;
}
[data-bs-theme="dark"] .rb-block-info {
    background: #2a2a3d;
}
[data-bs-theme="dark"] .rb-block-info li {
    color: #999;
}
[data-bs-theme="dark"] .rb-block-name {
    color: #e5e5e5;
}
[data-bs-theme="dark"] .rb-block-msg {
    color: #ccc;
}

/* ---------- 모바일 풀스크린 ---------- */
@media (max-width: 768px) {
    .rb-overlay {
        padding: 0;
        align-items: flex-end;
    }
    .rb-modal {
        max-width: 100%;
        border-radius: 16px 16px 0 0;
        max-height: 85vh;
        transform: translateY(100%);
    }
    .rb-overlay.rb-fade-in .rb-modal {
        transform: translateY(0);
    }
    .rb-modal-header {
        padding: 16px 20px 12px;
    }
    .rb-modal-body {
        padding: 12px 20px;
    }
    .rb-modal-footer {
        padding: 12px 20px 24px;
    }
    .rb-toast {
        bottom: 90px;
    }
}
</style>

<!-- ===== 신고 모달 HTML ===== -->
<div class="rb-overlay" id="rbReportOverlay" role="dialog" aria-modal="true" aria-labelledby="rbReportTitle">
    <div class="rb-modal">
        <div class="rb-modal-header">
            <h3 class="rb-modal-title" id="rbReportTitle">콘텐츠 신고</h3>
            <button type="button" class="rb-close-btn" onclick="closeReportModal()" aria-label="닫기">&times;</button>
        </div>
        <div class="rb-modal-body">
            <input type="hidden" id="rbReportContentType">
            <input type="hidden" id="rbReportContentId">
            <input type="hidden" id="rbReportUserId">
            <input type="hidden" id="rbReportUserName">

            <p style="font-size:14px;color:var(--bs-secondary-color, #666);margin:0 0 12px;">신고 사유를 선택해주세요.</p>

            <div class="rb-reason-list" id="rbReasonList">
                <input type="radio" name="rb_reason" value="spam" id="rbr_spam" class="rb-reason-item">
                <label for="rbr_spam" class="rb-reason-label"><span class="rb-reason-dot"></span>스팸/광고</label>

                <input type="radio" name="rb_reason" value="harassment" id="rbr_harassment" class="rb-reason-item">
                <label for="rbr_harassment" class="rb-reason-label"><span class="rb-reason-dot"></span>괴롭힘/모욕</label>

                <input type="radio" name="rb_reason" value="hate_speech" id="rbr_hate" class="rb-reason-item">
                <label for="rbr_hate" class="rb-reason-label"><span class="rb-reason-dot"></span>혐오 발언</label>

                <input type="radio" name="rb_reason" value="sexual" id="rbr_sexual" class="rb-reason-item">
                <label for="rbr_sexual" class="rb-reason-label"><span class="rb-reason-dot"></span>성적 콘텐츠</label>

                <input type="radio" name="rb_reason" value="violence" id="rbr_violence" class="rb-reason-item">
                <label for="rbr_violence" class="rb-reason-label"><span class="rb-reason-dot"></span>폭력적 콘텐츠</label>

                <input type="radio" name="rb_reason" value="misinformation" id="rbr_misinfo" class="rb-reason-item">
                <label for="rbr_misinfo" class="rb-reason-label"><span class="rb-reason-dot"></span>허위 정보</label>

                <input type="radio" name="rb_reason" value="copyright" id="rbr_copyright" class="rb-reason-item">
                <label for="rbr_copyright" class="rb-reason-label"><span class="rb-reason-dot"></span>저작권 침해</label>

                <input type="radio" name="rb_reason" value="other" id="rbr_other" class="rb-reason-item">
                <label for="rbr_other" class="rb-reason-label"><span class="rb-reason-dot"></span>기타</label>
            </div>

            <textarea class="rb-textarea" id="rbReportDetail" placeholder="상세 설명을 입력해주세요 (선택)" maxlength="1000"></textarea>
        </div>
        <div class="rb-modal-footer">
            <button type="button" class="rb-btn rb-btn-cancel" onclick="closeReportModal()">취소</button>
            <button type="button" class="rb-btn rb-btn-danger" id="rbReportSubmitBtn" onclick="submitReport()" disabled>신고하기</button>
        </div>
    </div>
</div>

<!-- ===== 차단 모달 HTML ===== -->
<div class="rb-overlay" id="rbBlockOverlay" role="dialog" aria-modal="true" aria-labelledby="rbBlockTitle">
    <div class="rb-modal">
        <div class="rb-modal-header">
            <h3 class="rb-modal-title" id="rbBlockTitle">사용자 차단</h3>
            <button type="button" class="rb-close-btn" onclick="closeBlockModal()" aria-label="닫기">&times;</button>
        </div>
        <div class="rb-modal-body">
            <input type="hidden" id="rbBlockId">
            <input type="hidden" id="rbBlockName">

            <p class="rb-block-msg"><span class="rb-block-name" id="rbBlockDisplayName"></span>님을 차단하시겠습니까?</p>
            <div class="rb-block-info">
                <ul>
                    <li>이 사용자의 후기, 댓글, 질문이 더 이상 표시되지 않습니다</li>
                    <li>이 사용자가 보낸 채팅 메시지가 숨겨집니다</li>
                    <li>차단은 설정에서 언제든 해제할 수 있습니다</li>
                </ul>
            </div>
        </div>
        <div class="rb-modal-footer">
            <button type="button" class="rb-btn rb-btn-cancel" onclick="closeBlockModal()">취소</button>
            <button type="button" class="rb-btn rb-btn-danger" id="rbBlockSubmitBtn" onclick="submitBlock()">차단하기</button>
        </div>
    </div>
</div>

<!-- ===== 토스트 컨테이너 ===== -->
<div class="rb-toast" id="rbToast"></div>

<!-- ===== JavaScript ===== -->
<script>
(function() {
    'use strict';

    // API 경로 — 현재 페이지 기준 상대 경로
    var basePath = window.location.pathname.replace(/\/[^\/]*$/, '');
    var API_REPORT = basePath + '/api_report_content.php';
    var API_BLOCK  = basePath + '/api_block_user.php';

    // ========== 신고 모달 ==========

    /**
     * 신고 모달 열기
     * @param {string} contentType - 콘텐츠 유형 (review, comment, question, community 등)
     * @param {number|string} contentId - 콘텐츠 ID
     * @param {number|string} reportedUserId - 신고 대상 사용자 ID
     * @param {string} reportedUserName - 신고 대상 사용자 이름 (표시용)
     */
    window.openReportModal = function(contentType, contentId, reportedUserId, reportedUserName) {
        document.getElementById('rbReportContentType').value = contentType || '';
        document.getElementById('rbReportContentId').value = contentId || '';
        document.getElementById('rbReportUserId').value = reportedUserId || '';
        document.getElementById('rbReportUserName').value = reportedUserName || '';

        // 폼 초기화
        var radios = document.querySelectorAll('input[name="rb_reason"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].checked = false;
        }
        document.getElementById('rbReportDetail').value = '';
        document.getElementById('rbReportSubmitBtn').disabled = true;

        showOverlay('rbReportOverlay');
    };

    window.closeReportModal = function() {
        hideOverlay('rbReportOverlay');
    };

    // 사유 선택 시 제출 버튼 활성화
    document.getElementById('rbReasonList').addEventListener('change', function() {
        var selected = document.querySelector('input[name="rb_reason"]:checked');
        document.getElementById('rbReportSubmitBtn').disabled = !selected;
    });

    /**
     * 신고 제출
     */
    window.submitReport = async function() {
        var reason = document.querySelector('input[name="rb_reason"]:checked');
        if (!reason) {
            showToast('신고 사유를 선택해주세요.', 'warning');
            return;
        }

        var btn = document.getElementById('rbReportSubmitBtn');
        btn.disabled = true;
        btn.textContent = '처리 중...';

        var payload = {
            content_type: document.getElementById('rbReportContentType').value,
            content_id: document.getElementById('rbReportContentId').value,
            reported_user_id: document.getElementById('rbReportUserId').value,
            reason: reason.value,
            reason_detail: document.getElementById('rbReportDetail').value.trim(),
            content_url: window.location.href
        };

        try {
            var response = await fetch(API_REPORT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            var data = await response.json();

            if (response.status === 401 || data.login_required) {
                if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                    window.location.href = 'auth/login.php?url=' + encodeURIComponent(window.location.href);
                }
                btn.disabled = false;
                btn.textContent = '신고하기';
                return;
            }

            if (data.success) {
                closeReportModal();
                showToast('신고가 접수되었습니다. 검토 후 조치하겠습니다.', 'success');
            } else {
                showToast(data.error || data.message || '신고 처리 중 오류가 발생했습니다.', 'error');
                btn.disabled = false;
            }
        } catch (err) {
            console.error('Report submit error:', err);
            showToast('네트워크 오류가 발생했습니다. 다시 시도해주세요.', 'error');
            btn.disabled = false;
        }

        btn.textContent = '신고하기';
    };

    // ========== 차단 모달 ==========

    /**
     * 차단 모달 열기
     * @param {number|string} blockedId - 차단할 사용자 ID
     * @param {string} blockedName - 차단할 사용자 이름
     */
    window.openBlockModal = function(blockedId, blockedName) {
        document.getElementById('rbBlockId').value = blockedId || '';
        document.getElementById('rbBlockName').value = blockedName || '';
        document.getElementById('rbBlockDisplayName').textContent = blockedName || '이 사용자';
        document.getElementById('rbBlockSubmitBtn').disabled = false;
        document.getElementById('rbBlockSubmitBtn').textContent = '차단하기';

        showOverlay('rbBlockOverlay');
    };

    window.closeBlockModal = function() {
        hideOverlay('rbBlockOverlay');
    };

    /**
     * 차단 제출
     */
    window.submitBlock = async function() {
        var btn = document.getElementById('rbBlockSubmitBtn');
        btn.disabled = true;
        btn.textContent = '처리 중...';

        var payload = {
            blocked_id: document.getElementById('rbBlockId').value
        };

        try {
            var response = await fetch(API_BLOCK, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            var data = await response.json();

            if (response.status === 401 || data.login_required) {
                if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                    window.location.href = 'auth/login.php?url=' + encodeURIComponent(window.location.href);
                }
                btn.disabled = false;
                btn.textContent = '차단하기';
                return;
            }

            if (data.success) {
                closeBlockModal();
                showToast('사용자를 차단했습니다. 해당 사용자의 콘텐츠가 숨겨집니다.', 'success');
                // 차단 후 페이지 새로고침 (차단된 사용자 콘텐츠 숨김 적용)
                setTimeout(function() {
                    window.location.reload();
                }, 1200);
            } else {
                showToast(data.error || data.message || '차단 처리 중 오류가 발생했습니다.', 'error');
                btn.disabled = false;
                btn.textContent = '차단하기';
            }
        } catch (err) {
            console.error('Block submit error:', err);
            showToast('네트워크 오류가 발생했습니다. 다시 시도해주세요.', 'error');
            btn.disabled = false;
            btn.textContent = '차단하기';
        }
    };

    // ========== 토스트 ==========

    var toastTimer = null;

    /**
     * 토스트 알림 표시
     * @param {string} message - 표시할 메시지
     * @param {string} type - 'success' | 'error' | 'warning'
     */
    window.showToast = function(message, type) {
        type = type || 'success';
        var toast = document.getElementById('rbToast');
        toast.textContent = message;
        toast.className = 'rb-toast rb-toast-' + type;

        // 기존 타이머 제거
        if (toastTimer) {
            clearTimeout(toastTimer);
        }

        // 강제 reflow 후 표시
        void toast.offsetHeight;
        toast.classList.add('rb-toast-show');

        toastTimer = setTimeout(function() {
            toast.classList.remove('rb-toast-show');
            toastTimer = null;
        }, 3000);
    };

    // ========== 오버레이 유틸리티 ==========

    function showOverlay(id) {
        var overlay = document.getElementById(id);
        overlay.classList.add('rb-visible');
        // 강제 reflow 후 페이드인
        void overlay.offsetHeight;
        overlay.classList.add('rb-fade-in');
        // body 스크롤 잠금
        document.body.style.overflow = 'hidden';

        // ESC 키로 닫기
        overlay._escHandler = function(e) {
            if (e.key === 'Escape') {
                hideOverlay(id);
            }
        };
        document.addEventListener('keydown', overlay._escHandler);
    }

    function hideOverlay(id) {
        var overlay = document.getElementById(id);
        overlay.classList.remove('rb-fade-in');

        // ESC 핸들러 제거
        if (overlay._escHandler) {
            document.removeEventListener('keydown', overlay._escHandler);
            overlay._escHandler = null;
        }

        // 애니메이션 완료 후 숨김
        setTimeout(function() {
            overlay.classList.remove('rb-visible');
            // 열려있는 다른 모달이 없으면 스크롤 복원
            var anyOpen = document.querySelector('.rb-overlay.rb-visible');
            if (!anyOpen) {
                document.body.style.overflow = '';
            }
        }, 250);
    }

    // 오버레이 배경 클릭으로 닫기
    document.getElementById('rbReportOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeReportModal();
    });
    document.getElementById('rbBlockOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeBlockModal();
    });

})();
</script>

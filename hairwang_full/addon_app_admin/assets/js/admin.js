/**
 * 앱 관리자 공통 JavaScript
 * @package AddonAppAdmin
 * @version 1.0.0
 */

(function() {
    'use strict';

    // 페이지 로드 완료
    document.addEventListener('DOMContentLoaded', function() {
        // 툴팁 초기화
        initTooltips();

        // 확인 다이얼로그
        initConfirmDialogs();

        // 자동 저장
        initAutoSave();
    });

    /**
     * 툴팁 초기화
     */
    function initTooltips() {
        const tooltips = document.querySelectorAll('[data-tooltip]');

        tooltips.forEach(function(element) {
            element.addEventListener('mouseenter', function() {
                const text = this.getAttribute('data-tooltip');
                showTooltip(this, text);
            });

            element.addEventListener('mouseleave', function() {
                hideTooltip();
            });
        });
    }

    /**
     * 툴팁 표시
     */
    function showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'addon-tooltip';
        tooltip.textContent = text;
        tooltip.id = 'addon-tooltip-temp';

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
        tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
    }

    /**
     * 툴팁 숨기기
     */
    function hideTooltip() {
        const tooltip = document.getElementById('addon-tooltip-temp');
        if (tooltip) {
            tooltip.remove();
        }
    }

    /**
     * 확인 다이얼로그 초기화
     */
    function initConfirmDialogs() {
        const confirmButtons = document.querySelectorAll('[data-confirm]');

        confirmButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm');

                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }

    /**
     * 자동 저장 초기화
     */
    function initAutoSave() {
        const autoSaveForms = document.querySelectorAll('[data-autosave]');

        autoSaveForms.forEach(function(form) {
            const interval = parseInt(form.getAttribute('data-autosave')) || 60000; // 기본 1분

            // 입력 필드 변경 감지
            const inputs = form.querySelectorAll('input, textarea, select');
            let hasChanges = false;

            inputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    hasChanges = true;
                });
            });

            // 주기적 자동 저장
            setInterval(function() {
                if (hasChanges) {
                    autoSaveForm(form);
                    hasChanges = false;
                }
            }, interval);
        });
    }

    /**
     * 폼 자동 저장
     */
    function autoSaveForm(form) {
        const formData = new FormData(form);
        formData.append('autosave', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('자동 저장 완료', 'success');
            }
        })
        .catch(error => {
            console.error('자동 저장 실패:', error);
        });
    }

    /**
     * 알림 표시
     */
    function showNotification(message, type) {
        type = type || 'info';

        const notification = document.createElement('div');
        notification.className = 'addon-notification addon-notification-' + type;
        notification.textContent = message;

        document.body.appendChild(notification);

        // 애니메이션
        setTimeout(function() {
            notification.classList.add('show');
        }, 10);

        // 자동 제거
        setTimeout(function() {
            notification.classList.remove('show');

            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * AJAX 요청 헬퍼
     */
    window.addonAjax = function(url, data, callback) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (typeof callback === 'function') {
                callback(data);
            }
        })
        .catch(error => {
            console.error('AJAX 오류:', error);
            alert('요청 처리 중 오류가 발생했습니다.');
        });
    };

    /**
     * 파일 크기 포맷
     */
    window.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    /**
     * 날짜 포맷
     */
    window.formatDate = function(dateString, format) {
        const date = new Date(dateString);

        format = format || 'Y-m-d H:i:s';

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');

        return format
            .replace('Y', year)
            .replace('m', month)
            .replace('d', day)
            .replace('H', hours)
            .replace('i', minutes)
            .replace('s', seconds);
    };

    /**
     * 디바운스
     */
    window.debounce = function(func, wait) {
        let timeout;

        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };

            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * 클립보드 복사
     */
    window.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('클립보드에 복사되었습니다', 'success');
            });
        } else {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                showNotification('클립보드에 복사되었습니다', 'success');
            } catch (err) {
                alert('클립보드 복사 실패');
            }

            document.body.removeChild(textarea);
        }
    };

    // 전역 함수로 노출
    window.addonShowNotification = showNotification;

})();

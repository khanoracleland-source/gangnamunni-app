/**
 * AI 실시간 작성 도우미
 *
 * 후기/광고 작성 시 실시간 AI 분석 → 개선 제안 표시
 * - debounce 500ms
 * - 부유 패널 UI
 * - 규칙 기반 + LLM 제안 자동 전환
 *
 * 사용법:
 *   <script src="js/ai_writing_helper.js"></script>
 *   <script>
 *     AiHelper.init({
 *       contentSelector: '#reviewContent',  // textarea 또는 contenteditable
 *       titleSelector: '#reviewTitle',       // (선택) 제목 입력
 *       panelSelector: '#aiSuggestions',     // 제안 표시 패널
 *       context: 'review',                   // review | ad
 *       apiUrl: 'api/ai_suggest.php',
 *     });
 *   </script>
 */
const AiHelper = (function () {
    'use strict';

    let config = {
        contentSelector: '',
        titleSelector: '',
        panelSelector: '',
        context: 'review',
        apiUrl: 'api/ai_suggest.php',
        debounceMs: 500,
        minLength: 10,
    };

    let debounceTimer = null;
    let lastContent = '';
    let isLoading = false;
    let panelEl = null;

    // ================================================================
    // 초기화
    // ================================================================
    function init(opts) {
        Object.assign(config, opts);

        const contentEl = document.querySelector(config.contentSelector);
        if (!contentEl) {
            console.warn('[AiHelper] Content element not found:', config.contentSelector);
            return;
        }

        // 패널 생성 또는 기존 사용
        if (config.panelSelector) {
            panelEl = document.querySelector(config.panelSelector);
        }
        if (!panelEl) {
            panelEl = createPanel();
            contentEl.parentElement.appendChild(panelEl);
        }

        // 이벤트 리스너 (textarea / contenteditable 모두 지원)
        const eventName = contentEl.tagName === 'TEXTAREA' || contentEl.tagName === 'INPUT' ? 'input' : 'input';
        contentEl.addEventListener(eventName, function () {
            onContentChange(contentEl);
        });

        // Quill 에디터 지원
        if (window.quill) {
            window.quill.on('text-change', function () {
                const text = window.quill.getText().trim();
                onContentChangeText(text);
            });
        }
    }

    // ================================================================
    // 패널 생성
    // ================================================================
    function createPanel() {
        const panel = document.createElement('div');
        panel.id = 'ai-helper-panel';
        panel.innerHTML = `
            <div class="ai-helper-header">
                <span class="ai-helper-icon">🤖</span>
                <span class="ai-helper-title">AI 작성 도우미</span>
                <span class="ai-helper-status" id="aiHelperStatus"></span>
                <button class="ai-helper-close" onclick="AiHelper.togglePanel()" title="접기">&times;</button>
            </div>
            <div class="ai-helper-body" id="aiHelperBody">
                <div class="ai-helper-empty">
                    내용을 작성하면 AI가 실시간으로<br>개선 제안을 해드립니다.
                </div>
            </div>
        `;
        return panel;
    }

    // ================================================================
    // 내용 변경 감지
    // ================================================================
    function onContentChange(el) {
        const text = el.value || el.textContent || el.innerText || '';
        onContentChangeText(text.trim());
    }

    function onContentChangeText(text) {
        if (text === lastContent) return;
        lastContent = text;

        clearTimeout(debounceTimer);

        if (text.length < config.minLength) {
            showEmpty();
            return;
        }

        debounceTimer = setTimeout(function () {
            fetchSuggestions(text);
        }, config.debounceMs);
    }

    // ================================================================
    // API 호출
    // ================================================================
    function fetchSuggestions(content) {
        if (isLoading) return;
        isLoading = true;
        showLoading();

        const titleEl = config.titleSelector ? document.querySelector(config.titleSelector) : null;
        const title = titleEl ? (titleEl.value || '').trim() : '';

        const fd = new FormData();
        fd.append('action', 'suggest');
        fd.append('content', content);
        fd.append('context', config.context);
        if (title) fd.append('title', title);

        fetch(config.apiUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                isLoading = false;
                if (data.success && data.suggestions && data.suggestions.length > 0) {
                    renderSuggestions(data.suggestions, data.method, data.score);
                } else {
                    showGood();
                }
            })
            .catch(function (e) {
                isLoading = false;
                console.error('[AiHelper]', e);
                showEmpty();
            });
    }

    // ================================================================
    // 렌더링
    // ================================================================
    function renderSuggestions(suggestions, method, score) {
        const body = panelEl.querySelector('#aiHelperBody') || panelEl.querySelector('.ai-helper-body');
        if (!body) return;

        const statusEl = panelEl.querySelector('#aiHelperStatus');
        if (statusEl) {
            statusEl.textContent = method === 'ai' ? '✨ AI 분석' : '📋 규칙 분석';
            statusEl.className = 'ai-helper-status ' + (method === 'ai' ? 'ai-mode' : 'rule-mode');
        }

        let html = '';

        if (score !== null && score !== undefined) {
            const scoreClass = score >= 70 ? 'good' : score >= 40 ? 'mid' : 'low';
            html += '<div class="ai-helper-score ' + scoreClass + '">';
            html += '<span class="score-value">' + score + '</span>';
            html += '<span class="score-label">점</span>';
            html += '</div>';
        }

        suggestions.forEach(function (s) {
            const icon = getTypeIcon(s.type);
            const typeClass = getTypeClass(s.type);
            html += '<div class="ai-suggestion ' + typeClass + '">';
            html += '<span class="suggestion-icon">' + icon + '</span>';
            html += '<span class="suggestion-text">' + escapeHtml(s.text) + '</span>';
            html += '</div>';
        });

        body.innerHTML = html;
        panelEl.classList.add('has-suggestions');
        panelEl.classList.remove('collapsed');
    }

    function showLoading() {
        const body = panelEl.querySelector('#aiHelperBody') || panelEl.querySelector('.ai-helper-body');
        if (body) {
            body.innerHTML = '<div class="ai-helper-loading"><span class="spinner"></span> 분석 중...</div>';
        }
    }

    function showEmpty() {
        const body = panelEl.querySelector('#aiHelperBody') || panelEl.querySelector('.ai-helper-body');
        if (body) {
            body.innerHTML = '<div class="ai-helper-empty">내용을 작성하면 AI가 실시간으로<br>개선 제안을 해드립니다.</div>';
        }
        panelEl.classList.remove('has-suggestions');
    }

    function showGood() {
        const body = panelEl.querySelector('#aiHelperBody') || panelEl.querySelector('.ai-helper-body');
        if (body) {
            body.innerHTML = '<div class="ai-helper-good">✅ 잘 작성되고 있습니다!</div>';
        }
    }

    function togglePanel() {
        panelEl.classList.toggle('collapsed');
    }

    // ================================================================
    // Utility
    // ================================================================
    function getTypeIcon(type) {
        var icons = {
            warning: '⚠️', tip: '💡', improve: '📝',
            quality: '⭐', detail: '🔍', photo: '📷',
        };
        return icons[type] || '💬';
    }

    function getTypeClass(type) {
        if (type === 'warning') return 'suggestion-warning';
        if (type === 'tip') return 'suggestion-tip';
        return 'suggestion-info';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ================================================================
    // Public API
    // ================================================================
    return {
        init: init,
        togglePanel: togglePanel,
        refresh: function () {
            if (lastContent.length >= config.minLength) {
                fetchSuggestions(lastContent);
            }
        },
    };
})();

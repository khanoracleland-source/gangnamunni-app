/**
 * 서울미녀 - 로딩 인디케이터 시스템
 * 모든 페이지에서 API 호출 시 로딩 상태를 표시합니다.
 */

const LoadingIndicator = {
    // 현재 활성 로딩 인디케이터
    activeLoaders: new Set(),

    /**
     * 로딩 타입별 HTML 템플릿
     */
    templates: {
        spinner: `
            <div class="loading-overlay" data-loading-type="spinner">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <div class="loading-text">로딩 중...</div>
                </div>
            </div>
        `,
        dots: `
            <div class="loading-overlay" data-loading-type="dots">
                <div class="loading-content">
                    <div class="dots-loader">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                    <div class="loading-text">로딩 중...</div>
                </div>
            </div>
        `,
        progress: `
            <div class="loading-overlay" data-loading-type="progress">
                <div class="loading-content">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="loading-text">로딩 중...</div>
                </div>
            </div>
        `,
        skeleton: `
            <div class="loading-overlay" data-loading-type="skeleton">
                <div class="loading-content">
                    <div class="skeleton-loader">
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line short"></div>
                    </div>
                </div>
            </div>
        `
    },

    /**
     * 로딩 인디케이터 스타일
     */
    styles: `
        <style id="loading-indicator-styles">
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(4px);
            }

            .loading-content {
                text-align: center;
            }

            .loading-text {
                margin-top: 20px;
                font-size: 16px;
                color: #FF6B9D;
                font-weight: 600;
            }

            /* Spinner Type */
            .spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #FFE5ED;
                border-top-color: #FF6B9D;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Dots Type */
            .dots-loader {
                display: flex;
                gap: 10px;
                justify-content: center;
            }

            .dots-loader .dot {
                width: 12px;
                height: 12px;
                background: #FF6B9D;
                border-radius: 50%;
                animation: bounce 1.4s infinite ease-in-out both;
            }

            .dots-loader .dot:nth-child(1) {
                animation-delay: -0.32s;
            }

            .dots-loader .dot:nth-child(2) {
                animation-delay: -0.16s;
            }

            @keyframes bounce {
                0%, 80%, 100% {
                    transform: scale(0);
                    opacity: 0.5;
                }
                40% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            /* Progress Type */
            .progress-bar {
                width: 200px;
                height: 6px;
                background: #FFE5ED;
                border-radius: 3px;
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #FF6B9D, #FFB6D9);
                animation: progress 1.5s ease-in-out infinite;
            }

            @keyframes progress {
                0% {
                    width: 0%;
                    margin-left: 0%;
                }
                50% {
                    width: 70%;
                    margin-left: 15%;
                }
                100% {
                    width: 0%;
                    margin-left: 100%;
                }
            }

            /* Skeleton Type */
            .skeleton-loader {
                width: 300px;
            }

            .skeleton-line {
                height: 20px;
                background: linear-gradient(90deg, #FFE5ED 25%, #FFB6D9 50%, #FFE5ED 75%);
                background-size: 200% 100%;
                border-radius: 4px;
                margin-bottom: 10px;
                animation: skeleton 1.5s ease-in-out infinite;
            }

            .skeleton-line.short {
                width: 60%;
            }

            @keyframes skeleton {
                0% {
                    background-position: 200% 0;
                }
                100% {
                    background-position: -200% 0;
                }
            }

            /* Mobile optimizations */
            @media (max-width: 768px) {
                .loading-text {
                    font-size: 14px;
                }

                .spinner {
                    width: 40px;
                    height: 40px;
                }

                .skeleton-loader {
                    width: 250px;
                }
            }
        </style>
    `,

    /**
     * 초기화
     */
    init() {
        // 스타일이 아직 추가되지 않았으면 추가
        if (!document.getElementById('loading-indicator-styles')) {
            document.head.insertAdjacentHTML('beforeend', this.styles);
        }
    },

    /**
     * 로딩 인디케이터 표시
     * @param {string} loaderId 로더 고유 ID (같은 ID로 중복 표시 방지)
     * @param {string} type 로딩 타입 (spinner, dots, progress, skeleton)
     * @returns {boolean} 표시 성공 여부
     */
    show(loaderId = 'default', type = null) {
        // 이미 표시 중이면 무시
        if (this.activeLoaders.has(loaderId)) {
            return false;
        }

        // 설정에서 로딩 타입 가져오기
        if (!type) {
            type = this.getLoadingType();
        }

        // 템플릿 가져오기
        const template = this.templates[type] || this.templates.spinner;

        // DOM에 추가
        const div = document.createElement('div');
        div.innerHTML = template;
        const overlay = div.firstElementChild;
        overlay.dataset.loaderId = loaderId;
        document.body.appendChild(overlay);

        // 활성 로더 목록에 추가
        this.activeLoaders.add(loaderId);

        // Debug 로그
        if (window.DEBUG) {
            console.log(`[Loading] Show: ${loaderId} (type: ${type})`);
        }

        return true;
    },

    /**
     * 로딩 인디케이터 숨김
     * @param {string} loaderId 로더 고유 ID
     * @returns {boolean} 숨김 성공 여부
     */
    hide(loaderId = 'default') {
        // 표시 중이 아니면 무시
        if (!this.activeLoaders.has(loaderId)) {
            return false;
        }

        // DOM에서 제거
        const overlay = document.querySelector(`.loading-overlay[data-loader-id="${loaderId}"]`);
        if (overlay) {
            overlay.remove();
        }

        // 활성 로더 목록에서 제거
        this.activeLoaders.delete(loaderId);

        // Debug 로그
        if (window.DEBUG) {
            console.log(`[Loading] Hide: ${loaderId}`);
        }

        return true;
    },

    /**
     * 모든 로딩 인디케이터 숨김
     */
    hideAll() {
        document.querySelectorAll('.loading-overlay').forEach(overlay => {
            overlay.remove();
        });
        this.activeLoaders.clear();

        if (window.DEBUG) {
            console.log('[Loading] Hide all');
        }
    },

    /**
     * 설정에서 로딩 타입 가져오기
     * @returns {string} 로딩 타입
     */
    getLoadingType() {
        try {
            // SettingsManager가 있으면 사용
            if (typeof SettingsManager !== 'undefined') {
                const type = SettingsManager.get('display.loadingType');
                if (type && this.templates[type]) {
                    return type;
                }
            }

            // LocalStorage에서 직접 가져오기
            const settings = localStorage.getItem('seoulminya_settings');
            if (settings) {
                const parsed = JSON.parse(settings);
                const type = parsed.display?.loadingType;
                if (type && this.templates[type]) {
                    return type;
                }
            }
        } catch (error) {
            console.error('[Loading] Error getting loading type:', error);
        }

        // 기본값
        return 'spinner';
    },

    /**
     * API 호출을 래핑하여 자동으로 로딩 표시
     * @param {Function} apiCall API 호출 함수
     * @param {string} loaderId 로더 ID
     * @returns {Promise} API 결과
     */
    async wrap(apiCall, loaderId = 'api-call') {
        this.show(loaderId);
        try {
            const result = await apiCall();
            return result;
        } finally {
            this.hide(loaderId);
        }
    },

    /**
     * Fetch를 래핑하여 자동으로 로딩 표시
     * @param {string} url URL
     * @param {object} options Fetch options
     * @param {string} loaderId 로더 ID
     * @returns {Promise<Response>} Fetch 응답
     */
    async fetch(url, options = {}, loaderId = 'fetch') {
        this.show(loaderId);
        try {
            const response = await fetch(url, options);
            return response;
        } finally {
            this.hide(loaderId);
        }
    }
};

// 자동 초기화
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        LoadingIndicator.init();
    });
} else {
    LoadingIndicator.init();
}

// 전역으로 노출
window.LoadingIndicator = LoadingIndicator;

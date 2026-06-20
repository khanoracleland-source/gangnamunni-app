/**
 * 서울미녀 - 설정 관리 시스템
 * 네비게이션 동작, 디스플레이 설정 등을 관리합니다.
 * 데이터베이스 연동 버전 (API + LocalStorage 캐시)
 */

const SettingsManager = {
    // API 서버 주소
    API_URL: 'http://localhost:8080/api',

    // 기본 설정값
    defaults: {
        navigation: {
            pc: {
                mode: 'same-tab',  // 'same-tab' | 'new-tab'
            },
            mobile: {
                mode: 'same-page',  // 'same-page' | 'popup'
            }
        },
        display: {
            theme: 'light',  // 'light' | 'dark' (향후 확장)
            fontSize: 'medium',  // 'small' | 'medium' | 'large'
            loadingType: 'spinner',  // 'spinner' | 'dots' | 'progress' | 'skeleton'
        },
        filters: {
            categoryType: 'checkbox',  // 'checkbox' | 'radio'
        },
        features: {
            useKivicare: false,  // true: Kivicare 기능 사용, false: 새로 개발한 기능 사용
        }
    },

    // 로컬스토리지 키
    STORAGE_KEY: 'seoulminya_settings',
    SESSION_ID_KEY: 'seoulminya_session_id',

    /**
     * 세션 ID 가져오기 또는 생성
     * @returns {string} 세션 ID
     */
    getSessionId() {
        let sessionId = localStorage.getItem(this.SESSION_ID_KEY);
        if (!sessionId) {
            // UUID v4 형식의 세션 ID 생성
            sessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
            localStorage.setItem(this.SESSION_ID_KEY, sessionId);
        }
        return sessionId;
    },

    /**
     * 디바이스 타입 감지
     * @returns {string} 'pc' | 'mobile'
     */
    getDeviceType() {
        const width = window.innerWidth;
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

        // 768px 이하이거나 터치 디바이스면 모바일로 간주
        if (width <= 768 || isTouchDevice) {
            return 'mobile';
        }
        return 'pc';
    },

    /**
     * 설정 로드 (API 우선, LocalStorage 폴백)
     * @returns {Promise<object>} 설정 객체
     */
    async load() {
        try {
            const sessionId = this.getSessionId();

            // API에서 설정 가져오기
            const response = await fetch(`${this.API_URL}/settings?session_id=${sessionId}`);

            if (response.ok) {
                const settings = await response.json();
                // LocalStorage에 캐시
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(settings));
                console.log('[Settings] 설정을 데이터베이스에서 로드했습니다:', settings);
                return settings;
            } else {
                throw new Error('API 응답 실패');
            }
        } catch (error) {
            console.warn('[Settings] API에서 설정을 가져올 수 없습니다. LocalStorage 사용:', error.message);

            // API 실패 시 LocalStorage에서 가져오기
            try {
                const stored = localStorage.getItem(this.STORAGE_KEY);
                if (stored) {
                    const settings = JSON.parse(stored);
                    return this.mergeSettings(this.defaults, settings);
                }
            } catch (localError) {
                console.error('[Settings] LocalStorage 로드 실패:', localError);
            }

            // 모두 실패하면 기본값 반환
            return { ...this.defaults };
        }
    },

    /**
     * 동기 설정 로드 (LocalStorage만 사용)
     * @returns {object} 설정 객체
     */
    loadSync() {
        try {
            const stored = localStorage.getItem(this.STORAGE_KEY);
            if (stored) {
                const settings = JSON.parse(stored);
                return this.mergeSettings(this.defaults, settings);
            }
        } catch (error) {
            console.error('[Settings] 동기 로드 실패:', error);
        }
        return { ...this.defaults };
    },

    /**
     * 설정 저장 (API + LocalStorage)
     * @param {object} settings 저장할 설정
     * @returns {Promise<boolean>} 저장 성공 여부
     */
    async save(settings) {
        try {
            const sessionId = this.getSessionId();

            // API에 저장
            const response = await fetch(`${this.API_URL}/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    settings: settings
                })
            });

            if (response.ok) {
                const result = await response.json();
                console.log('[Settings] 설정을 데이터베이스에 저장했습니다:', result);

                // LocalStorage에도 캐시
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(settings));
                return true;
            } else {
                throw new Error('API 저장 실패');
            }
        } catch (error) {
            console.error('[Settings] API 저장 실패. LocalStorage만 업데이트:', error);

            // API 실패 시에도 LocalStorage는 업데이트
            try {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(settings));
                return true;
            } catch (localError) {
                console.error('[Settings] LocalStorage 저장 실패:', localError);
                return false;
            }
        }
    },

    /**
     * 기본값과 사용자 설정 병합
     * @param {object} defaults 기본 설정
     * @param {object} user 사용자 설정
     * @returns {object} 병합된 설정
     */
    mergeSettings(defaults, user) {
        const merged = { ...defaults };
        for (const key in user) {
            if (typeof user[key] === 'object' && !Array.isArray(user[key])) {
                merged[key] = this.mergeSettings(defaults[key] || {}, user[key]);
            } else {
                merged[key] = user[key];
            }
        }
        return merged;
    },

    /**
     * 특정 설정값 가져오기 (동기)
     * @param {string} path 설정 경로 (예: 'navigation.pc.mode')
     * @returns {any} 설정값
     */
    get(path) {
        let value = this.loadSync();
        const keys = path.split('.');

        for (const key of keys) {
            if (value && typeof value === 'object') {
                value = value[key];
            } else {
                return undefined;
            }
        }

        return value;
    },

    /**
     * 특정 설정값 설정하기
     * @param {string} path 설정 경로
     * @param {any} value 설정값
     * @returns {Promise<boolean>} 저장 성공 여부
     */
    async set(path, value) {
        const settings = this.loadSync();
        const keys = path.split('.');
        let current = settings;

        // 마지막 키를 제외한 나머지 경로 탐색
        for (let i = 0; i < keys.length - 1; i++) {
            const key = keys[i];
            if (!current[key] || typeof current[key] !== 'object') {
                current[key] = {};
            }
            current = current[key];
        }

        // 마지막 키에 값 설정
        current[keys[keys.length - 1]] = value;

        return await this.save(settings);
    },

    /**
     * 네비게이션 링크 타겟 가져오기
     * @returns {string} '_self' | '_blank' | null (popup)
     */
    getNavigationTarget() {
        const deviceType = this.getDeviceType();
        const mode = this.get(`navigation.${deviceType}.mode`);

        if (deviceType === 'pc') {
            return mode === 'new-tab' ? '_blank' : '_self';
        } else {
            // 모바일: popup 모드는 null 반환 (별도 처리 필요)
            return mode === 'popup' ? null : '_self';
        }
    },

    /**
     * 네비게이션 링크 클릭 핸들러
     * @param {string} url 이동할 URL
     * @param {Event} event 클릭 이벤트
     */
    handleNavigation(url, event) {
        const deviceType = this.getDeviceType();
        const mode = this.get(`navigation.${deviceType}.mode`);

        if (deviceType === 'mobile' && mode === 'popup') {
            // 모바일 팝업 모드
            event.preventDefault();
            const width = Math.min(400, window.innerWidth - 40);
            const height = window.innerHeight - 100;
            const left = (window.innerWidth - width) / 2;
            const top = 50;

            window.open(
                url,
                '_blank',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );
        } else {
            // PC 또는 모바일 same-page 모드
            const target = this.getNavigationTarget();
            if (target === '_blank') {
                event.preventDefault();
                window.open(url, '_blank');
            }
            // _self는 기본 동작 유지
        }
    },

    /**
     * 모든 네비게이션 링크에 설정 적용
     */
    applyNavigationSettings() {
        const deviceType = this.getDeviceType();
        const mode = this.get(`navigation.${deviceType}.mode`);

        console.log(`[Settings] Device: ${deviceType}, Mode: ${mode}`);

        // 네비게이션 링크에 이벤트 리스너 추가
        document.querySelectorAll('a.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleNavigation(link.href, e);
            });
        });

        // 헤더 로고 링크에도 적용
        document.querySelectorAll('a.logo').forEach(link => {
            link.addEventListener('click', (e) => {
                this.handleNavigation(link.href, e);
            });
        });
    },

    /**
     * 설정 초기화 (기본값으로 복원)
     * @returns {Promise<boolean>} 초기화 성공 여부
     */
    async reset() {
        const success = await this.save(this.defaults);
        console.log('[Settings] 설정이 초기화되었습니다.');
        return success;
    },

    /**
     * 페이지 로드 시 자동 초기화
     */
    async init() {
        try {
            // API에서 설정 로드 (초기화)
            const settings = await this.load();

            // 설정이 없으면 기본값으로 초기화
            if (!settings || Object.keys(settings).length === 0) {
                await this.save(this.defaults);
            }
        } catch (error) {
            console.error('[Settings] 초기화 중 오류:', error);
        }

        // DOM이 로드된 후 네비게이션 설정 적용
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.applyNavigationSettings();
            });
        } else {
            this.applyNavigationSettings();
        }

        console.log('[Settings] 설정 시스템 초기화 완료 (API 연동)');
    }
};

// 자동 초기화
SettingsManager.init();

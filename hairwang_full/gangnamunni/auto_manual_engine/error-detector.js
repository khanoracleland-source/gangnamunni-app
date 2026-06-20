/**
 * error-detector.js - PHP/JavaScript 에러 자동 감지 모듈
 *
 * 서울미녀 플랫폼 자동 테스트 시스템의 에러 감지 레이어
 * Playwright 페이지에서 3가지 레이어로 에러를 수집:
 *   1. HTTP 응답 상태 코드 (4xx, 5xx)
 *   2. 페이지 HTML 내 PHP 에러 문자열
 *   3. 브라우저 콘솔 JavaScript 에러
 *
 * 사용법:
 *   const { ErrorDetector } = require('./error-detector');
 *   const detector = new ErrorDetector(page);
 *   detector.startMonitoring();         // 페이지 이동 전 호출
 *   await page.goto(url);
 *   const result = detector.getResults(); // 수집된 에러 반환
 *   detector.reset();                     // 다음 페이지를 위해 초기화
 *
 * @since 2026-02-13
 * @version 1.0.0
 */

class ErrorDetector {
    /**
     * @param {import('playwright').Page} page - Playwright Page 객체
     */
    constructor(page) {
        this.page = page;

        // === 수집 저장소 ===
        this.httpErrors = [];       // HTTP 4xx/5xx 응답
        this.phpErrors = [];        // PHP Fatal/Parse/Warning 등
        this.jsErrors = [];         // JavaScript 콘솔 에러
        this.jsWarnings = [];       // JavaScript 콘솔 경고
        this.pageErrors = [];       // 페이지 크래시 에러 (uncaught exception)
        this.failedResources = [];  // 로드 실패 리소스 (이미지, CSS, JS 등)

        // === 이벤트 리스너 바인딩 (제거 가능하도록 참조 저장) ===
        this._onResponse = this._handleResponse.bind(this);
        this._onConsole = this._handleConsole.bind(this);
        this._onPageError = this._handlePageError.bind(this);
        this._onRequestFailed = this._handleRequestFailed.bind(this);

        this._monitoring = false;
    }

    /**
     * 모니터링 시작 - 페이지 이동 전에 호출
     * 이벤트 리스너를 등록하여 HTTP 응답, 콘솔 로그, 에러를 수집
     */
    startMonitoring() {
        if (this._monitoring) return;

        this.page.on('response', this._onResponse);
        this.page.on('console', this._onConsole);
        this.page.on('pageerror', this._onPageError);
        this.page.on('requestfailed', this._onRequestFailed);

        this._monitoring = true;
    }

    /**
     * 모니터링 중지 - 테스트 완료 후 호출
     * 이벤트 리스너 제거
     */
    stopMonitoring() {
        if (!this._monitoring) return;

        this.page.off('response', this._onResponse);
        this.page.off('console', this._onConsole);
        this.page.off('pageerror', this._onPageError);
        this.page.off('requestfailed', this._onRequestFailed);

        this._monitoring = false;
    }

    /**
     * 수집된 에러 초기화 - 다음 페이지 테스트 전에 호출
     */
    reset() {
        this.httpErrors = [];
        this.phpErrors = [];
        this.jsErrors = [];
        this.jsWarnings = [];
        this.pageErrors = [];
        this.failedResources = [];
    }

    // =========================================================
    // HTTP 응답 감시 (레이어 1)
    // =========================================================

    /**
     * HTTP 응답 핸들러
     * - 메인 페이지의 4xx/5xx 응답을 기록
     * - 서브 리소스(CSS, JS, 이미지) 에러도 별도 기록
     * @param {import('playwright').Response} response
     */
    _handleResponse(response) {
        const status = response.status();
        const url = response.url();

        // 4xx, 5xx 에러 감지
        if (status >= 400) {
            const entry = {
                url: url,
                status: status,
                statusText: response.statusText(),
                // 리소스 타입 판별
                isMainFrame: response.frame() === this.page.mainFrame(),
                resourceType: this._guessResourceType(url),
                timestamp: new Date().toISOString()
            };

            this.httpErrors.push(entry);
        }
    }

    /**
     * URL에서 리소스 타입 추측
     * @param {string} url
     * @returns {string} 'page'|'script'|'stylesheet'|'image'|'font'|'api'|'other'
     */
    _guessResourceType(url) {
        if (url.match(/\.(js|mjs)(\?|$)/i)) return 'script';
        if (url.match(/\.(css)(\?|$)/i)) return 'stylesheet';
        if (url.match(/\.(png|jpg|jpeg|gif|svg|webp|ico)(\?|$)/i)) return 'image';
        if (url.match(/\.(woff2?|ttf|eot|otf)(\?|$)/i)) return 'font';
        if (url.match(/\/api\//i)) return 'api';
        if (url.match(/\.(php|html?)(\?|$)/i)) return 'page';
        return 'other';
    }

    // =========================================================
    // 브라우저 콘솔 감시 (레이어 3)
    // =========================================================

    /**
     * 콘솔 메시지 핸들러
     * - console.error() → jsErrors에 기록
     * - console.warn() → jsWarnings에 기록
     * - 나머지(log, info)는 무시
     * @param {import('playwright').ConsoleMessage} msg
     */
    _handleConsole(msg) {
        const type = msg.type();
        const text = msg.text();
        const location = msg.location();

        // 무시할 콘솔 메시지 패턴
        const ignoreConsolePatterns = [
            'Livewire',                     // Laravel Livewire
            'Unrecognized feature',         // Permissions-Policy
            'web-share',                    // web-share API
            'reCAPTCHA',                    // reCAPTCHA
            'flatpickr',                    // flatpickr 로케일
            'Content Security Policy',      // CSP 차단 (보고는 하되 무시)
        ];
        if (ignoreConsolePatterns.some(p => text.includes(p))) {
            return; // 무시
        }

        if (type === 'error') {
            this.jsErrors.push({
                message: text,
                source: location.url || '',
                line: location.lineNumber || 0,
                column: location.columnNumber || 0,
                timestamp: new Date().toISOString()
            });
        } else if (type === 'warning') {
            // PHP 경고가 콘솔에 출력되는 경우도 있음
            this.jsWarnings.push({
                message: text,
                source: location.url || '',
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * 페이지 에러 핸들러 (uncaught exception)
     * - window.onerror에 해당하는 에러
     * - TypeError, ReferenceError 등 치명적 JS 에러
     * @param {Error} error
     */
    _handlePageError(error) {
        const msg = error.message || '';
        // 무시할 페이지 에러 패턴 (프레임워크 경고, 실제 동작에 영향 없음)
        const ignorePatterns = [
            'Livewire',                        // Laravel Livewire 관련 전체
            'Turbolinks',                      // Turbolinks 플러그인
            'Unrecognized feature',            // Permissions-Policy 미지원 기능
            'web-share',                       // web-share API 미지원
            'reCAPTCHA',                       // reCAPTCHA 미로드
            'onloadCallback',                  // reCAPTCHA 콜백
            'flatpickr',                       // flatpickr 로케일
            "Unexpected token '<'",            // HTML이 JS로 로드된 경우 (Gnuboard 경로)
            'jQuery is not defined',           // Gnuboard jQuery 로드 순서 (bbs/ 경로)
            '$ is not defined',                // jQuery 미로드 후속 에러
            'markerjs2',                       // marker.js CSP 차단
        ];
        if (ignorePatterns.some(p => msg.includes(p))) {
            return; // 무시
        }

        this.pageErrors.push({
            message: error.message,
            stack: error.stack || '',
            name: error.name || 'Error',
            timestamp: new Date().toISOString()
        });
    }

    /**
     * 요청 실패 핸들러
     * - 네트워크 에러, DNS 실패, 타임아웃 등
     * - HTTP 에러와 다름 (응답 자체를 못 받은 경우)
     * @param {import('playwright').Request} request
     */
    _handleRequestFailed(request) {
        // Cloudflare 내부 리소스 무시 (cdn-cgi/rum, challenges 등)
        const url = request.url();
        if (url.includes('/cdn-cgi/') || url.includes('cloudflare') || url.includes('markerjs2') || url.includes('unpkg.com')) {
            return;
        }

        this.failedResources.push({
            url: request.url(),
            method: request.method(),
            resourceType: request.resourceType(),
            failureText: request.failure()?.errorText || 'Unknown',
            timestamp: new Date().toISOString()
        });
    }

    // =========================================================
    // PHP 에러 감지 (레이어 2) - 페이지 HTML 분석
    // =========================================================

    /**
     * 페이지 HTML에서 PHP 에러 문자열 검색
     * - Fatal error, Parse error, Warning, Notice, Deprecated 등
     * - Stack trace, Uncaught Exception 포함
     * - 반드시 페이지 로드 완료 후 호출
     *
     * @returns {Promise<Array>} 발견된 PHP 에러 배열
     */
    async detectPhpErrors() {
        try {
            const content = await this.page.content();

            // PHP 에러 패턴 정의 (패턴: 설명)
            const patterns = [
                { regex: /Fatal error:.*?(?:on line \d+|$)/gi, severity: 'fatal' },
                { regex: /Parse error:.*?(?:on line \d+|$)/gi, severity: 'fatal' },
                { regex: /Warning:.*?(?:on line \d+|$)/gi, severity: 'warning' },
                { regex: /Notice:.*?(?:on line \d+|$)/gi, severity: 'notice' },
                { regex: /Deprecated:.*?(?:on line \d+|$)/gi, severity: 'notice' },
                { regex: /Uncaught (?:Exception|Error|TypeError|ValueError):.*?(?:Stack trace|$)/gi, severity: 'fatal' },
                { regex: /Stack trace:\s*#\d+/gi, severity: 'fatal' },
                { regex: /SQLSTATE\[\w+\].*?(?:\n|$)/gi, severity: 'fatal' },
                { regex: /Call to undefined (?:function|method).*?(?:on line \d+|$)/gi, severity: 'fatal' },
                { regex: /Class ['"][\w\\]+['"] not found/gi, severity: 'fatal' },
            ];

            for (const { regex, severity } of patterns) {
                const matches = content.match(regex);
                if (matches) {
                    for (const match of matches) {
                        // HTML 태그 제거하고 텍스트만 추출
                        const cleanText = match.replace(/<[^>]*>/g, '').trim();
                        if (cleanText.length > 10) { // 너무 짧은 매치 제외
                            this.phpErrors.push({
                                message: cleanText.substring(0, 500), // 최대 500자
                                severity: severity,
                                timestamp: new Date().toISOString()
                            });
                        }
                    }
                }
            }

            // 빈 페이지(완전 빈 body) 감지 - PHP 에러로 페이지가 안 나온 경우
            const bodyText = await this.page.evaluate(() => document.body?.innerText?.trim() || '');
            if (bodyText.length === 0) {
                this.phpErrors.push({
                    message: '페이지 본문이 비어있음 (PHP 에러로 출력 없음 가능성)',
                    severity: 'warning',
                    timestamp: new Date().toISOString()
                });
            }

            return this.phpErrors;
        } catch (err) {
            // 페이지 접근 불가 시 (이미 닫혔거나 네비게이션 중)
            this.phpErrors.push({
                message: `PHP 에러 감지 실패: ${err.message}`,
                severity: 'error',
                timestamp: new Date().toISOString()
            });
            return this.phpErrors;
        }
    }

    // =========================================================
    // 엘리먼트 존재 확인
    // =========================================================

    /**
     * CSS 셀렉터로 엘리먼트 존재 여부 확인
     * - 먼저 DOM에 즉시 존재하는지 확인 (빠른 경로)
     * - 없으면 state:'attached'로 대기 (visibility 무관, DOM 존재만 확인)
     * @param {string} selector - CSS 셀렉터
     * @param {number} timeout - 대기 시간 (ms), 기본 5000
     * @returns {Promise<{found: boolean, count: number, error: string|null}>}
     */
    async checkElement(selector, timeout = 5000) {
        try {
            // 1. DOM에 즉시 존재하는지 확인 (visibility 무관)
            const count = await this.page.locator(selector).count();
            if (count > 0) {
                return { found: true, count, error: null };
            }

            // 2. 동적 렌더링 대기 (state: 'attached' = DOM 존재만, visibility 불필요)
            await this.page.waitForSelector(selector, { timeout, state: 'attached' });
            const finalCount = await this.page.locator(selector).count();
            return { found: finalCount > 0, count: finalCount, error: null };
        } catch (err) {
            return { found: false, count: 0, error: `셀렉터 '${selector}' 미발견 (${timeout}ms 대기)` };
        }
    }

    // =========================================================
    // 종합 결과 반환
    // =========================================================

    /**
     * 현재까지 수집된 모든 에러를 종합 결과로 반환
     *
     * @returns {{
     *   hasErrors: boolean,            // 에러 존재 여부
     *   overallStatus: string,         // 'PASS' | 'WARN' | 'FAIL'
     *   summary: string,              // 요약 문자열
     *   httpErrors: Array,
     *   phpErrors: Array,
     *   jsErrors: Array,
     *   jsWarnings: Array,
     *   pageErrors: Array,
     *   failedResources: Array,
     *   counts: object
     * }}
     */
    getResults() {
        const counts = {
            httpErrors: this.httpErrors.length,
            phpErrors: this.phpErrors.length,
            jsErrors: this.jsErrors.length,
            jsWarnings: this.jsWarnings.length,
            pageErrors: this.pageErrors.length,
            failedResources: this.failedResources.length,
        };

        // 심각도 판단
        const hasFatal = this.phpErrors.some(e => e.severity === 'fatal') ||
                         this.pageErrors.length > 0 ||
                         this.httpErrors.some(e => e.isMainFrame && e.status >= 500);

        const hasErrors = hasFatal ||
                          this.httpErrors.some(e => e.isMainFrame) ||
                          this.jsErrors.length > 0;

        const hasWarnings = this.jsWarnings.length > 0 ||
                           this.phpErrors.some(e => e.severity === 'warning' || e.severity === 'notice') ||
                           this.failedResources.length > 0 ||
                           this.httpErrors.some(e => !e.isMainFrame);

        // 상태 결정: FAIL > WARN > PASS
        let overallStatus = 'PASS';
        if (hasFatal) overallStatus = 'FAIL';
        else if (hasErrors) overallStatus = 'FAIL';
        else if (hasWarnings) overallStatus = 'WARN';

        // 요약 문자열 생성
        const parts = [];
        if (counts.httpErrors > 0) parts.push(`HTTP에러 ${counts.httpErrors}건`);
        if (counts.phpErrors > 0) parts.push(`PHP에러 ${counts.phpErrors}건`);
        if (counts.jsErrors > 0) parts.push(`JS에러 ${counts.jsErrors}건`);
        if (counts.pageErrors > 0) parts.push(`페이지크래시 ${counts.pageErrors}건`);
        if (counts.failedResources > 0) parts.push(`리소스실패 ${counts.failedResources}건`);
        if (counts.jsWarnings > 0) parts.push(`JS경고 ${counts.jsWarnings}건`);
        const summary = parts.length > 0 ? parts.join(', ') : '에러 없음';

        return {
            hasErrors,
            overallStatus,
            summary,
            httpErrors: [...this.httpErrors],
            phpErrors: [...this.phpErrors],
            jsErrors: [...this.jsErrors],
            jsWarnings: [...this.jsWarnings],
            pageErrors: [...this.pageErrors],
            failedResources: [...this.failedResources],
            counts
        };
    }
}

module.exports = { ErrorDetector };

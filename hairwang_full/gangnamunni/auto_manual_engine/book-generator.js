/**
 * book-generator.js - 서울미녀 관리자 매뉴얼 북 자동 생성 엔진
 *
 * Playwright 기반으로 관리자 페이지 35개를 순회하며
 * 로그인 → 라이트모드 강제 → 스크린샷(JPEG) → 에러 감지 → 메타데이터 수집
 *
 * book-config.json의 chapters 구조에 따라 챕터별/페이지별 결과를 수집하고
 * bookData 구조체를 반환하여 HTML 렌더러(book-renderer)에서 사용할 수 있게 합니다.
 *
 * 사용법:
 *   const { generateBook } = require('./book-generator');
 *   const result = await generateBook({ enableAI: false }, (event, data) => {
 *       console.log(event, data);
 *   });
 *
 * 출력:
 *   - uploads/book/YYYYMMDD_HHMMSS/screenshots/*.jpg
 *   - uploads/book/YYYYMMDD_HHMMSS/results.json
 *   - uploads/book/YYYYMMDD_HHMMSS/meta.json
 *
 * @since 2026-02-26
 * @version 1.0.0
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { ErrorDetector } = require('./error-detector');

// =========================================================
// 설정 로드
// =========================================================
const CONFIG_PATH = path.join(__dirname, 'book-config.json');
let config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));

// =========================================================
// 유틸리티 함수
// =========================================================

/**
 * 타임스탬프 기반 출력 디렉토리 생성
 * 형식: uploads/book/YYYYMMDD_HHMMSS/screenshots/
 * @returns {{ outputDir: string, screenshotsDir: string }}
 */
function createOutputDir(overrideBaseDir) {
    const now = new Date();
    const ts = now.toISOString().replace(/[-:T]/g, '').substring(0, 14);
    const formatted = ts.substring(0, 8) + '_' + ts.substring(8);
    const outputBase = overrideBaseDir ? path.resolve(overrideBaseDir) : path.resolve(__dirname, config.book.outputDir);
    const outputDir = path.join(outputBase, formatted);
    const screenshotsDir = path.join(outputDir, 'screenshots');

    fs.mkdirSync(screenshotsDir, { recursive: true });
    return { outputDir, screenshotsDir };
}

/**
 * 파일명에 안전한 문자열로 변환
 * @param {string} name
 * @returns {string}
 */
function sanitize(name) {
    return (name || 'unknown')
        .replace(/[^a-zA-Z0-9가-힣_-]/g, '_')
        .replace(/_+/g, '_')
        .substring(0, 60);
}

/**
 * 로그 출력 (타임스탬프 포함, server.js stdout 캡처용)
 * @param {string} level - INFO|WARN|ERROR|OK|FAIL|START|DONE
 * @param {string} message
 */
function log(level, message) {
    const time = new Date().toLocaleTimeString('ko-KR', { hour12: false });
    const prefix = {
        'INFO': '[INFO]', 'WARN': '[WARN]', 'ERROR': '[ERROR]',
        'OK': '[ OK ]', 'FAIL': '[FAIL]', 'START': '[START]', 'DONE': '[DONE]'
    }[level] || '[    ]';
    console.log(`[${time}] ${prefix} ${message}`);
}

/**
 * 전체 페이지 수 카운트 (진행률 계산용)
 * @returns {number}
 */
function countTotalPages() {
    let total = 0;
    // 로그인 캡처
    if (config.loginCapture && config.loginCapture.enabled) {
        total += config.loginCapture.steps.length;
    }
    for (const chapter of config.chapters) {
        for (const page of chapter.pages) {
            if (page.steps && page.steps.length > 0) {
                total += page.steps.length; // 마법사: 단계별 캡처
            } else {
                total += 1; // 일반 페이지: 1장
            }
        }
    }
    return total;
}

/**
 * 마법사 단계별 폼 데이터 채우기 (page.evaluate로 DOM 직접 조작)
 */
async function fillStepData(page, step) {
    // fill: text/textarea/date inputs
    if (step.fillData && Object.keys(step.fillData).length > 0) {
        await page.evaluate((data) => {
            for (const [sel, val] of Object.entries(data)) {
                const el = document.querySelector(sel);
                if (!el) continue;
                if (el.tagName === 'SELECT') {
                    el.value = val;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (el.tagName === 'TEXTAREA') {
                    el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    // input (text, email, tel, date, number, password)
                    const nativeSetter = Object.getOwnPropertyDescriptor(
                        window.HTMLInputElement.prototype, 'value'
                    ).set;
                    nativeSetter.call(el, val);
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }, step.fillData);
    }

    // selectData: <select> dropdowns
    if (step.selectData) {
        for (const [sel, val] of Object.entries(step.selectData)) {
            try { await page.selectOption(sel, val); } catch (e) { /* 무시 */ }
        }
    }

    // clickData: radio buttons, cards, etc.
    if (step.clickData && step.clickData.length > 0) {
        for (const sel of step.clickData) {
            try { await page.click(sel); } catch (e) { /* 무시 */ }
        }
    }

    await page.waitForTimeout(300); // 데이터 반영 대기
}

/**
 * 로그인 플로우 캡처 (로그인 화면 → 입력 → 대시보드)
 */
async function captureLoginFlow(page, screenshotsDir, progressCallback) {
    const loginConfig = config.login;
    const loginCapture = config.loginCapture;
    const fullUrl = config.baseUrl + config.basePath + loginConfig.url;
    const results = [];
    let stepIdx = 0;

    log('INFO', '=== 로그인 플로우 캡처 시작 ===');

    for (const step of loginCapture.steps) {
        stepIdx++;
        const screenshotName = `00_login_s${String(stepIdx).padStart(2, '0')}.jpg`;
        const screenshotPath = path.join(screenshotsDir, screenshotName);

        try {
            if (step.action === 'navigate') {
                // 1. 로그인 화면으로 이동
                await page.goto(fullUrl, { waitUntil: 'networkidle', timeout: config.browser.timeout });
                await page.waitForSelector(loginConfig.usernameField, { timeout: 10000 });
                await page.waitForTimeout(1000);
            } else if (step.action === 'fill') {
                // 2. 아이디/비밀번호 입력
                await page.fill(loginConfig.usernameField, loginConfig.username);
                await page.fill(loginConfig.passwordField, loginConfig.password);
                await page.waitForTimeout(500);
            } else if (step.action === 'submit') {
                // 3. 로그인 버튼 클릭 → 대시보드
                await page.click(loginConfig.submitButton);
                await page.waitForTimeout(3000);
                try {
                    await page.waitForSelector(loginConfig.successIndicator, { timeout: 10000, state: 'attached' });
                } catch (e) { /* 폴백 무시 */ }
                // 라이트 모드 적용
                await forceLightMode(page);
                await page.waitForTimeout(500);
            }

            // 스크린샷
            await page.screenshot({ path: screenshotPath, fullPage: config.browser.fullPage, type: 'jpeg', quality: 85 });

            results.push({
                chapterId: 'login',
                chapterTitle: '로그인',
                pageKey: 'login',
                label: step.label,
                stepNum: stepIdx,
                screenshotFile: screenshotName,
                status: 'PASS'
            });

            log('OK', `  [로그인 ${stepIdx}/${loginCapture.steps.length}] ${step.label}`);

            if (progressCallback) {
                progressCallback('capture', {
                    chapter: '로그인',
                    chapterId: 'login',
                    key: 'login',
                    label: step.label,
                    status: 'pass',
                    current: stepIdx,
                    total: countTotalPages()
                });
                progressCallback('progress', {
                    current: stepIdx,
                    total: countTotalPages(),
                    percent: Math.round((stepIdx / countTotalPages()) * 100)
                });
            }
        } catch (err) {
            log('WARN', `  로그인 캡처 실패: ${step.label} - ${err.message}`);
            results.push({
                chapterId: 'login',
                chapterTitle: '로그인',
                pageKey: 'login',
                label: step.label,
                stepNum: stepIdx,
                screenshotFile: null,
                status: 'FAIL'
            });
        }
    }

    log('OK', '=== 로그인 플로우 캡처 완료 ===');
    return results;
}

// =========================================================
// 체크 실행
// =========================================================

/**
 * 페이지별 체크 항목 실행 (elementExists, noPhpError, noJsError)
 * @param {import('playwright').Page} page
 * @param {ErrorDetector} detector
 * @param {string[]} checks - 체크 항목 배열
 * @returns {Promise<{ results: Array, allPassed: boolean, hasWarning: boolean }>}
 */
async function runChecks(page, detector, checks) {
    const results = [];
    let allPassed = true;
    let hasWarning = false;

    if (!checks || checks.length === 0) {
        return { results, allPassed: true, hasWarning: false };
    }

    for (const check of checks) {
        if (check === 'noPhpError') {
            // PHP 에러 감지는 detector에서 처리됨
            const phpErrors = detector.phpErrors || [];
            const hasFatal = phpErrors.some(e => e.severity === 'fatal');
            const hasNotice = phpErrors.some(e => e.severity === 'warning' || e.severity === 'notice');
            results.push({
                type: 'noPhpError',
                passed: !hasFatal,
                warning: hasNotice,
                detail: hasFatal ? `PHP Fatal 에러 ${phpErrors.filter(e => e.severity === 'fatal').length}건` :
                        hasNotice ? `PHP 경고/노티스 ${phpErrors.filter(e => e.severity !== 'fatal').length}건` :
                        'PHP 에러 없음'
            });
            if (hasFatal) allPassed = false;
            if (hasNotice) hasWarning = true;

        } else if (check === 'noJsError') {
            // JS 에러는 detector의 jsErrors, pageErrors에서 확인
            const jsErrorCount = (detector.jsErrors || []).length;
            const pageErrorCount = (detector.pageErrors || []).length;
            const totalJsErrors = jsErrorCount + pageErrorCount;
            results.push({
                type: 'noJsError',
                passed: totalJsErrors === 0,
                warning: false,
                detail: totalJsErrors > 0 ? `JS 에러 ${totalJsErrors}건` : 'JS 에러 없음'
            });
            if (totalJsErrors > 0) hasWarning = true;

        } else if (check.startsWith('elementExists:')) {
            const selector = check.replace('elementExists:', '');
            try {
                const count = await page.locator(selector).count();
                const found = count > 0;
                results.push({
                    type: 'elementExists',
                    selector,
                    passed: found,
                    warning: false,
                    detail: found ? `'${selector}' 발견 (${count}개)` : `'${selector}' 미발견`
                });
                if (!found) hasWarning = true;
            } catch (err) {
                results.push({
                    type: 'elementExists',
                    selector,
                    passed: false,
                    warning: false,
                    detail: `셀렉터 확인 실패: ${err.message}`
                });
                hasWarning = true;
            }
        }
    }

    return { results, allPassed, hasWarning };
}

/**
 * 에러 결과와 체크 결과를 종합하여 최종 상태 결정
 * @param {object} errorResults - ErrorDetector.getResults() 반환값
 * @param {{ allPassed: boolean, hasWarning: boolean }} checkSummary
 * @param {number} httpStatus - HTTP 응답 코드
 * @returns {'PASS'|'WARN'|'FAIL'}
 */
function determineStatus(errorResults, checkSummary, httpStatus) {
    // HTTP 5xx → 즉시 FAIL
    if (httpStatus >= 500) return 'FAIL';

    // PHP Fatal / 페이지 크래시 / 메인 프레임 HTTP 에러 → FAIL
    if (errorResults.overallStatus === 'FAIL') return 'FAIL';

    // 체크 항목 중 하나라도 미통과 → FAIL
    if (!checkSummary.allPassed) return 'FAIL';

    // HTTP 4xx → WARN
    if (httpStatus >= 400) return 'WARN';

    // ErrorDetector가 WARN으로 판정 → WARN
    if (errorResults.overallStatus === 'WARN') return 'WARN';

    // 체크 항목에서 경고 발생 → WARN
    if (checkSummary.hasWarning) return 'WARN';

    return 'PASS';
}

// =========================================================
// 페이지 메타데이터 추출
// =========================================================

/**
 * 페이지에서 구조적 메타데이터를 추출
 * @param {import('playwright').Page} page
 * @returns {Promise<object>}
 */
async function extractPageMeta(page) {
    try {
        return await page.evaluate(() => {
            return {
                title: document.querySelector('.page-desc-banner h5')?.textContent?.trim() || document.title,
                description: document.querySelector('.page-desc-banner p')?.textContent?.trim() || '',
                linkCount: document.querySelectorAll('a').length,
                formCount: document.querySelectorAll('form').length,
                tableCount: document.querySelectorAll('table').length,
                buttonCount: document.querySelectorAll('button, .btn').length,
                inputCount: document.querySelectorAll('input, select, textarea').length,
                cardCount: document.querySelectorAll('.card, .stat-card, [class*="card"]').length,
                imageCount: document.querySelectorAll('img').length,
                modalCount: document.querySelectorAll('.modal, [class*="modal"]').length,
                headingText: document.querySelector('h1, h2, h3, h4, h5')?.textContent?.trim() || '',
                hasNavbar: !!document.querySelector('nav, .navbar, .sidebar, [class*="nav"]'),
                hasBreadcrumb: !!document.querySelector('.breadcrumb, [class*="breadcrumb"]'),
                domNodeCount: document.querySelectorAll('*').length,
                contentLength: (document.body?.innerText || '').length
            };
        });
    } catch (err) {
        return { title: '', description: '', error: err.message };
    }
}

// =========================================================
// 로그인 처리
// =========================================================

/**
 * 관리자 로그인 수행
 * @param {import('playwright').Page} page
 * @param {Function|null} progressCallback
 * @returns {Promise<boolean>} 로그인 성공 여부
 */
async function performLogin(page, progressCallback) {
    const loginConfig = config.login;
    const fullUrl = config.baseUrl + config.basePath + loginConfig.url;

    log('INFO', `로그인 시도: ${fullUrl}`);

    try {
        // 로그인 페이지 이동
        const response = await page.goto(fullUrl, {
            waitUntil: 'networkidle',
            timeout: config.browser.timeout
        });

        if (!response || response.status() >= 400) {
            log('ERROR', `로그인 페이지 HTTP ${response?.status() || 'N/A'}`);
            return false;
        }

        // 로그인 폼이 나타날 때까지 대기
        await page.waitForSelector(loginConfig.usernameField, { timeout: 10000 });
        await page.waitForTimeout(1000);

        // 필드 초기화 후 입력
        await page.fill(loginConfig.usernameField, '');
        await page.fill(loginConfig.usernameField, loginConfig.username);

        await page.fill(loginConfig.passwordField, '');
        await page.fill(loginConfig.passwordField, loginConfig.password);

        await page.waitForTimeout(500);

        // 로그인 버튼 클릭
        await page.click(loginConfig.submitButton);

        // 리다이렉트 대기
        await page.waitForTimeout(3000);

        // 성공 지표 확인: successIndicator 셀렉터가 DOM에 존재하는지
        let loginSuccess = false;
        try {
            await page.waitForSelector(loginConfig.successIndicator, {
                timeout: 10000,
                state: 'attached'
            });
            loginSuccess = true;
            log('OK', '로그인 성공 (사이드바 확인됨)');
        } catch (e) {
            // 폴백: 페이지에 대시보드 텍스트가 있는지 확인
            const bodyText = await page.evaluate(() => document.body?.innerText || '');
            if (bodyText.includes('대시보드') || bodyText.includes('Dashboard') || bodyText.includes('통계')) {
                loginSuccess = true;
                log('OK', '로그인 성공 (대시보드 텍스트 확인됨)');
            } else {
                log('WARN', '로그인 결과 불확실 - 계속 진행');
                loginSuccess = true; // 계속 진행
            }
        }

        // 진행 이벤트 발행
        if (progressCallback) {
            progressCallback('login', {
                success: loginSuccess,
                url: fullUrl,
                username: loginConfig.username
            });
        }

        return loginSuccess;

    } catch (err) {
        log('ERROR', `로그인 에러: ${err.message}`);
        if (progressCallback) {
            progressCallback('error', {
                phase: 'login',
                message: err.message
            });
        }
        return false;
    }
}

// =========================================================
// 라이트 모드 강제 적용
// =========================================================

/**
 * localStorage와 DOM 속성 양쪽으로 라이트 모드를 강제합니다.
 * 매뉴얼 스크린샷은 항상 라이트 모드로 통일합니다.
 * @param {import('playwright').Page} page
 */
async function forceLightMode(page) {
    try {
        await page.evaluate(() => {
            localStorage.setItem('admin_theme', 'light');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            document.body?.classList?.remove('dark-mode', 'theme-dark');
            document.body?.classList?.add('light-mode', 'theme-light');
        });
    } catch (e) {
        // 라이트 모드 강제 실패는 무시 (스크린샷에 영향 최소)
    }
}

// =========================================================
// 메인 북 생성 함수
// =========================================================

/**
 * 전체 관리자 페이지를 순회하며 스크린샷 북을 생성합니다.
 *
 * @param {Object} options - { enableAI: boolean }
 * @param {Function|null} progressCallback - (event, data) => void
 *   Events: 'login', 'progress', 'capture', 'error', 'complete'
 * @returns {Promise<{ outputDir: string, results: Array, summary: object, bookData: object }>}
 */
async function generateBook(options = {}, progressCallback = null) {
    // configPath 옵션으로 다른 설정 파일 사용 가능 (앱 매뉴얼 등)
    if (options.configPath) {
        config = JSON.parse(fs.readFileSync(options.configPath, 'utf-8'));
    }
    const startTime = Date.now();
    const totalPages = countTotalPages();

    log('START', '=== Seoul Beauty 관리자 매뉴얼 북 생성 시작 ===');
    log('INFO', `총 ${config.chapters.length}챕터, ${totalPages}페이지`);
    log('INFO', `기본 URL: ${config.baseUrl}${config.basePath}`);

    // ── 1. 출력 디렉토리 생성 ──
    const { outputDir, screenshotsDir } = createOutputDir(options.outputBaseDir);
    log('INFO', `출력 디렉토리: ${outputDir}`);

    // ── 2. 브라우저 시작 ──
    const browser = await chromium.launch({
        headless: config.browser.headless,
        channel: config.browser.channel,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ]
    });

    // ── 3. 브라우저 컨텍스트 생성 (1920x1080) ──
    const context = await browser.newContext({
        viewport: config.browser.viewport,
        locale: 'ko-KR',
        timezoneId: 'Asia/Seoul',
        ignoreHTTPSErrors: true,
    });

    const page = await context.newPage();
    const detector = new ErrorDetector(page);

    const allResults = [];
    let totalPass = 0;
    let totalWarn = 0;
    let totalFail = 0;
    let globalPageIndex = 0;

    try {
        // ── 4. 로그인 (+ 로그인 플로우 캡처) ──
        let loginResults = [];
        if (config.loginCapture && config.loginCapture.enabled) {
            // 로그인 플로우 캡처 (로그인 화면 → 입력 → 대시보드)
            loginResults = await captureLoginFlow(page, screenshotsDir, progressCallback);
            globalPageIndex += loginResults.length;

            // 로그인 성공 확인
            const lastStep = loginResults[loginResults.length - 1];
            if (!lastStep || lastStep.status === 'FAIL') {
                log('FAIL', '로그인 캡처 실패 - 기존 방식으로 재시도');
                const loginSuccess = await performLogin(page, progressCallback);
                if (!loginSuccess) {
                    await browser.close();
                    const failSummary = { total: 0, pass: 0, warn: 0, fail: 0, abortReason: 'login_failed' };
                    if (progressCallback) {
                        progressCallback('complete', { outputDir, summary: failSummary, aborted: true });
                    }
                    return { outputDir, results: [], summary: failSummary, bookData: null };
                }
            }

            loginResults.forEach(r => {
                allResults.push(r);
                if (r.status === 'PASS') totalPass++;
                else totalFail++;
            });
        } else if (config.login && config.login.required !== false) {
            // 기존 방식: 로그인만 수행 (login.required가 false가 아닌 경우)
            const loginSuccess = await performLogin(page, progressCallback);
            if (!loginSuccess) {
                await browser.close();
                const failSummary = { total: 0, pass: 0, warn: 0, fail: 0, abortReason: 'login_failed' };
                if (progressCallback) {
                    progressCallback('complete', { outputDir, summary: failSummary, aborted: true });
                }
                return { outputDir, results: [], summary: failSummary, bookData: null };
            }
        } else {
            log('INFO', '로그인 불필요 (login.required=false) - 건너뜀');
        }

        // ── 5. 라이트 모드 강제 (localStorage 설정) ──
        await forceLightMode(page);

        // ── 6. 챕터별 → 페이지별 순회 ──
        const chapterResults = [];

        for (let ci = 0; ci < config.chapters.length; ci++) {
            const chapter = config.chapters[ci];
            log('INFO', `\n--- [${chapter.id}] ${chapter.title} (${chapter.pages.length}페이지) ---`);

            const pageResults = [];

            for (let pi = 0; pi < chapter.pages.length; pi++) {
                const pageConfig = chapter.pages[pi];
                const fullUrl = config.baseUrl + config.basePath + pageConfig.url;
                const pageStartTime = Date.now();
                const hasSteps = pageConfig.steps && pageConfig.steps.length > 0;

                // ── 마법사 단계별 캡처 모드 ──
                if (hasSteps) {
                    log('INFO', `  [마법사] ${pageConfig.label} (${pageConfig.steps.length}단계)`);

                    try {
                        // 페이지 이동 (1회만)
                        const response = await page.goto(fullUrl, {
                            waitUntil: 'networkidle',
                            timeout: config.browser.timeout
                        });
                        const httpStatus = response ? response.status() : 0;
                        const loadTimeMs = Date.now() - pageStartTime;

                        await page.waitForTimeout(config.browser.captureDelay);
                        await forceLightMode(page);
                        await page.waitForTimeout(300);

                        // 각 단계 순회
                        for (const step of pageConfig.steps) {
                            globalPageIndex++;
                            const stepPadded = String(step.stepNum).padStart(2, '0');
                            const paddedIndex = String(globalPageIndex).padStart(2, '0');
                            const screenshotName = `${paddedIndex}_${sanitize(chapter.id)}_${sanitize(pageConfig.key)}_s${stepPadded}.jpg`;
                            const screenshotPath = path.join(screenshotsDir, screenshotName);

                            // 단계 전환 (Step 1은 이미 표시됨)
                            if (step.stepNum > 1) {
                                await page.evaluate((n) => {
                                    // 이전 단계들을 완료 표시
                                    if (typeof completedSteps !== 'undefined') {
                                        for (let i = 1; i < n; i++) completedSteps.add(i);
                                    }
                                    // showStep 직접 호출 (validateStep 우회)
                                    if (typeof showStep === 'function') showStep(n);
                                }, step.stepNum);
                                await page.waitForTimeout(config.browser.stepDelay || 800);
                            }

                            // 폼 데이터 채우기
                            await fillStepData(page, step);

                            // 라이트 모드 재적용
                            await forceLightMode(page);
                            await page.waitForTimeout(200);

                            // 스크린샷
                            await page.screenshot({
                                path: screenshotPath,
                                fullPage: config.browser.fullPage,
                                type: 'jpeg',
                                quality: 85
                            });

                            const stepLabel = `${pageConfig.label} - Step ${step.stepNum}: ${step.stepLabel}`;
                            const result = {
                                chapterId: chapter.id,
                                chapterTitle: chapter.title,
                                pageKey: pageConfig.key,
                                label: stepLabel,
                                url: fullUrl,
                                description: pageConfig.description || '',
                                httpStatus: httpStatus,
                                loadTimeMs: step.stepNum === 1 ? loadTimeMs : 0,
                                screenshotFile: screenshotName,
                                screenshotPath: screenshotPath,
                                status: 'PASS',
                                meta: {},
                                errors: {},
                                checks: [],
                                globalIndex: globalPageIndex,
                                isStep: true,
                                stepNum: step.stepNum,
                                stepLabel: step.stepLabel,
                                totalSteps: pageConfig.steps.length,
                                wizardKey: pageConfig.key
                            };

                            totalPass++;
                            log('OK', `    [Step ${step.stepNum}/${pageConfig.steps.length}] ${step.stepLabel}`);

                            if (progressCallback) {
                                progressCallback('capture', {
                                    chapter: chapter.title,
                                    chapterId: chapter.id,
                                    key: pageConfig.key,
                                    label: stepLabel,
                                    status: 'pass',
                                    current: globalPageIndex,
                                    total: totalPages,
                                    stepNum: step.stepNum,
                                    totalSteps: pageConfig.steps.length
                                });
                                progressCallback('progress', {
                                    current: globalPageIndex,
                                    total: totalPages,
                                    percent: Math.round((globalPageIndex / totalPages) * 100)
                                });
                            }

                            allResults.push(result);
                            pageResults.push(result);
                        }
                    } catch (err) {
                        log('WARN', `  마법사 캡처 실패: ${pageConfig.label} - ${err.message}`);
                        // 마법사 실패 시 최소 1개 결과 추가
                        globalPageIndex++;
                        const fallbackResult = {
                            chapterId: chapter.id,
                            chapterTitle: chapter.title,
                            pageKey: pageConfig.key,
                            label: pageConfig.label + ' (캡처 실패)',
                            url: fullUrl,
                            status: 'FAIL',
                            globalIndex: globalPageIndex,
                            errors: { summary: err.message }
                        };
                        totalFail++;
                        allResults.push(fallbackResult);
                        pageResults.push(fallbackResult);

                        if (progressCallback) {
                            progressCallback('progress', {
                                current: globalPageIndex,
                                total: totalPages,
                                percent: Math.round((globalPageIndex / totalPages) * 100)
                            });
                        }
                    }

                    continue; // 다음 페이지로
                }

                // ── 일반 페이지 캡처 (기존 로직) ──
                globalPageIndex++;
                log('INFO', `  [${globalPageIndex}/${totalPages}] ${pageConfig.label}...`);

                const paddedIndex = String(globalPageIndex).padStart(2, '0');
                const screenshotName = `${paddedIndex}_${sanitize(chapter.id)}_${sanitize(pageConfig.key)}.jpg`;
                const screenshotPath = path.join(screenshotsDir, screenshotName);

                const result = {
                    chapterId: chapter.id,
                    chapterTitle: chapter.title,
                    pageKey: pageConfig.key,
                    label: pageConfig.label,
                    url: fullUrl,
                    description: pageConfig.description || '',
                    httpStatus: 0,
                    loadTimeMs: 0,
                    screenshotFile: screenshotName,
                    screenshotPath: screenshotPath,
                    status: 'PASS',
                    meta: {},
                    errors: {},
                    checks: [],
                    globalIndex: globalPageIndex
                };

                try {
                    const response = await page.goto(fullUrl, {
                        waitUntil: 'networkidle',
                        timeout: config.browser.timeout
                    });

                    result.httpStatus = response ? response.status() : 0;
                    result.loadTimeMs = Date.now() - pageStartTime;

                    await page.waitForTimeout(config.browser.captureDelay);
                    await forceLightMode(page);
                    await page.waitForTimeout(300);

                    detector.reset();
                    detector.startMonitoring();
                    await detector.detectPhpErrors();

                    const checkSummary = await runChecks(page, detector, pageConfig.checks);
                    result.checks = checkSummary.results;
                    result.meta = await extractPageMeta(page);

                    await page.screenshot({
                        path: screenshotPath,
                        fullPage: config.browser.fullPage,
                        type: 'jpeg',
                        quality: 85
                    });

                    detector.stopMonitoring();
                    const errorResults = detector.getResults();
                    result.errors = errorResults;
                    result.status = determineStatus(errorResults, checkSummary, result.httpStatus);

                } catch (err) {
                    result.status = 'FAIL';
                    result.loadTimeMs = Date.now() - pageStartTime;
                    result.errors = {
                        hasErrors: true,
                        overallStatus: 'FAIL',
                        summary: `페이지 로드 실패: ${err.message}`,
                        httpErrors: [], phpErrors: [],
                        jsErrors: [{ message: err.message }],
                        jsWarnings: [], pageErrors: [], failedResources: [],
                        counts: { httpErrors: 0, phpErrors: 0, jsErrors: 1, jsWarnings: 0, pageErrors: 0, failedResources: 0 }
                    };

                    try {
                        await page.screenshot({ path: screenshotPath, fullPage: false, type: 'jpeg', quality: 85 });
                    } catch (screenshotErr) {
                        result.screenshotFile = null;
                        result.screenshotPath = null;
                    }

                    detector.stopMonitoring();

                    if (progressCallback) {
                        progressCallback('error', { phase: 'capture', chapterId: chapter.id, pageKey: pageConfig.key, message: err.message });
                    }
                }

                if (result.status === 'PASS') totalPass++;
                else if (result.status === 'WARN') totalWarn++;
                else totalFail++;

                const statusTag = result.status === 'PASS' ? '[ OK ]' : result.status === 'WARN' ? '[WARN]' : '[FAIL]';
                log(result.status === 'PASS' ? 'OK' : result.status,
                    `  ${statusTag} ${pageConfig.label} [HTTP ${result.httpStatus}] ${result.loadTimeMs}ms`);

                if (progressCallback) {
                    progressCallback('capture', {
                        chapter: chapter.title, chapterId: chapter.id, pageIndex: pi,
                        total: totalPages, key: pageConfig.key, label: pageConfig.label,
                        status: result.status, screenshotFile: result.screenshotFile,
                        httpStatus: result.httpStatus, loadTimeMs: result.loadTimeMs
                    });
                    progressCallback('progress', {
                        current: globalPageIndex,
                        total: totalPages,
                        percent: Math.round((globalPageIndex / totalPages) * 100)
                    });
                }

                allResults.push(result);
                pageResults.push(result);
            }

            // 챕터 결과 저장
            chapterResults.push({
                id: chapter.id,
                title: chapter.title,
                icon: chapter.icon,
                description: chapter.description || '',
                pages: pageResults
            });
        }

        // ── 7. 브라우저 종료 ──
        await context.close();
        await browser.close();

        // ── 8. bookData 구조체 빌드 ──
        const elapsedSec = ((Date.now() - startTime) / 1000).toFixed(1);

        const summary = {
            total: allResults.length,
            pass: totalPass,
            warn: totalWarn,
            fail: totalFail,
            elapsedSeconds: parseFloat(elapsedSec),
            chapters: config.chapters.length,
            generatedAt: new Date().toISOString()
        };

        // 로그인 챕터 빌드
        const loginChapter = loginResults.length > 0 ? {
            id: 'ch00_login',
            title: '로그인',
            icon: 'bi-box-arrow-in-right',
            description: '시스템 로그인 과정',
            pages: loginResults.map(r => ({
                key: r.pageKey,
                label: r.label,
                status: r.status,
                screenshotFile: r.screenshotFile,
                isStep: true,
                stepNum: r.stepNum
            }))
        } : null;

        const bookData = {
            title: config.book.title,
            subtitle: config.book.subtitle,
            author: config.book.author,
            generatedAt: new Date().toISOString(),
            baseUrl: config.baseUrl,
            basePath: config.basePath,
            chapters: [
                ...(loginChapter ? [loginChapter] : []),
                ...chapterResults.map(ch => ({
                    id: ch.id,
                    title: ch.title,
                    icon: ch.icon,
                    description: ch.description,
                    pages: ch.pages.map(p => ({
                        key: p.pageKey || p.key,
                        label: p.label,
                        url: p.url,
                        description: p.description,
                        status: p.status,
                        httpStatus: p.httpStatus,
                        loadTimeMs: p.loadTimeMs,
                        screenshotFile: p.screenshotFile,
                        meta: p.meta,
                        checks: p.checks,
                        errorSummary: (p.errors && p.errors.summary) || '에러 없음',
                        isStep: p.isStep || false,
                        stepNum: p.stepNum || null,
                        stepLabel: p.stepLabel || null,
                        totalSteps: p.totalSteps || null,
                        wizardKey: p.wizardKey || null
                    }))
                }))
            ],
            summary
        };

        // ── 9. results.json 저장 ──
        const resultsPayload = {
            summary,
            results: allResults
        };
        fs.writeFileSync(
            path.join(outputDir, 'results.json'),
            JSON.stringify(resultsPayload, null, 2),
            'utf-8'
        );

        // ── meta.json 저장 (bookData 구조체) ──
        fs.writeFileSync(
            path.join(outputDir, 'meta.json'),
            JSON.stringify(bookData, null, 2),
            'utf-8'
        );

        log('DONE', '\n=== 매뉴얼 북 생성 완료 ===');
        log('INFO', `총 ${allResults.length}페이지: PASS ${totalPass} / WARN ${totalWarn} / FAIL ${totalFail}`);
        log('INFO', `소요시간: ${elapsedSec}초`);
        log('INFO', `출력: ${outputDir}`);

        // ── 10. complete 이벤트 발행 ──
        if (progressCallback) {
            progressCallback('complete', {
                outputDir,
                summary,
                bookData
            });
        }

        // ── 11. 결과 반환 ──
        return { outputDir, results: allResults, summary, bookData };

    } catch (fatalErr) {
        // 치명적 에러 (브라우저 크래시 등)
        log('ERROR', `치명적 에러: ${fatalErr.message}`);

        try { await browser.close(); } catch (e) { /* 무시 */ }

        const failSummary = {
            total: allResults.length,
            pass: totalPass,
            warn: totalWarn,
            fail: totalFail,
            abortReason: fatalErr.message,
            elapsedSeconds: parseFloat(((Date.now() - startTime) / 1000).toFixed(1))
        };

        // 부분 결과라도 저장
        if (allResults.length > 0) {
            try {
                fs.writeFileSync(
                    path.join(outputDir, 'results_partial.json'),
                    JSON.stringify({ summary: failSummary, results: allResults }, null, 2),
                    'utf-8'
                );
            } catch (e) { /* 무시 */ }
        }

        if (progressCallback) {
            progressCallback('error', {
                phase: 'fatal',
                message: fatalErr.message
            });
            progressCallback('complete', {
                outputDir,
                summary: failSummary,
                aborted: true
            });
        }

        return { outputDir, results: allResults, summary: failSummary, bookData: null };
    }
}

// =========================================================
// CLI 직접 실행 지원
// =========================================================
if (require.main === module) {
    const cliCallback = (event, data) => {
        if (event === 'progress') {
            // 진행률은 한 줄로 표시
            process.stdout.write(`\r  진행: ${data.current}/${data.total} (${data.percent}%)`);
            if (data.current === data.total) process.stdout.write('\n');
        }
    };

    generateBook({ enableAI: false }, cliCallback)
        .then(({ summary }) => {
            process.exit(summary.fail > 0 ? 1 : 0);
        })
        .catch(err => {
            console.error('북 생성 치명적 에러:', err);
            process.exit(2);
        });
}

module.exports = { generateBook };

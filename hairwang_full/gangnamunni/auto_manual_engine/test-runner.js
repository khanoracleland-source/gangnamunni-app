/**
 * test-runner.js - 서울미녀 플랫폼 자동 테스트 실행 엔진
 *
 * Playwright 기반으로 3개 시스템(HMS Admin, 서울미녀 Admin, 서울미녀 Frontend)의
 * 전체 페이지를 순회하며 자동 로그인 → 스크린샷 → 에러 감지 → 보고서 생성
 *
 * 실행 방법:
 *   node test-runner.js                    # 전체 테스트 (33개 페이지)
 *   node test-runner.js --system hms_admin # 특정 시스템만
 *   node test-runner.js --page "대시보드"  # 특정 페이지만
 *   node test-runner.js --no-report        # 보고서 생성 생략
 *
 * 출력:
 *   - uploads/auto_test/YYYYMMDD_HHMMSS/screenshots/*.png
 *   - uploads/auto_test/YYYYMMDD_HHMMSS/report.docx
 *   - uploads/auto_test/YYYYMMDD_HHMMSS/report.pdf
 *   - uploads/auto_test/YYYYMMDD_HHMMSS/results.json
 *
 * @since 2026-02-13
 * @version 1.0.0
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { ErrorDetector } = require('./error-detector');
const AUTO_TEST_ENABLED = String(process.env.AUTO_TEST_ENABLED || '').trim().toLowerCase() === 'true';

if (require.main === module && !AUTO_TEST_ENABLED) {
    console.error('자동 테스트 기능이 일시 중지되었습니다.');
    process.exit(3);
}

// =========================================================
// 설정 로드
// =========================================================
const CONFIG_PATH = path.join(__dirname, 'test-config.json');
const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
if (process.env.AUTO_TEST_BASE_URL) config.baseUrl = process.env.AUTO_TEST_BASE_URL;
const parsedBaseUrl = new URL(config.baseUrl);
if (!['http:', 'https:'].includes(parsedBaseUrl.protocol)) throw new Error('Invalid AUTO_TEST_BASE_URL');
if (parsedBaseUrl.hostname === 'hms.seoulbeauty.app' && process.env.AUTO_TEST_ALLOW_PRODUCTION !== 'true') {
    throw new Error('Production tests require AUTO_TEST_ALLOW_PRODUCTION=true');
}
config.baseUrl = parsedBaseUrl.origin;

// =========================================================
// CLI 인자 파싱
// =========================================================
const args = process.argv.slice(2);
const cliOptions = {
    system: null,       // --system <id> : 특정 시스템만 테스트
    page: null,         // --page <name> : 특정 페이지만 테스트
    pages: null,        // --pages <json> : 선택된 페이지 URL 배열
    noReport: false,    // --no-report : 보고서 생성 생략
    verbose: false,     // --verbose : 상세 로그
    delay: 0,           // --delay <ms> : 페이지 간 딜레이 (실시간 관찰용)
};

for (let i = 0; i < args.length; i++) {
    if (args[i] === '--system' && args[i + 1]) cliOptions.system = args[++i];
    if (args[i] === '--page' && args[i + 1]) cliOptions.page = args[++i];
    if (args[i] === '--pages' && args[i + 1]) {
        try { cliOptions.pages = JSON.parse(args[++i]); } catch(e) { cliOptions.pages = null; }
    }
    if (args[i] === '--no-report') cliOptions.noReport = true;
    if (args[i] === '--verbose') cliOptions.verbose = true;
    if (args[i] === '--delay' && args[i + 1]) cliOptions.delay = parseInt(args[++i]) || 0;
}

// =========================================================
// 유틸리티 함수
// =========================================================

/**
 * 타임스탬프 기반 출력 디렉토리 생성
 * @returns {string} 생성된 디렉토리 경로
 */
function createOutputDir() {
    const now = new Date();
    const ts = now.toISOString().replace(/[-:T]/g, '').substring(0, 14); // 20260213_143052
    const formatted = ts.substring(0, 8) + '_' + ts.substring(8);
    const outputBase = path.resolve(__dirname, config.report.outputDir);
    const outputDir = path.join(outputBase, formatted);
    const screenshotsDir = path.join(outputDir, 'screenshots');

    fs.mkdirSync(screenshotsDir, { recursive: true });
    return outputDir;
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

function resolveSecret(value, label) {
    const match = typeof value === 'string' ? value.match(/^\$\{([A-Z0-9_]+)\}$/) : null;
    if (!match) throw new Error(`${label}: password must reference an environment variable`);
    const resolved = process.env[match[1]];
    if (!resolved) throw new Error(`${label}: ${match[1]} environment variable is required`);
    return resolved;
}

/**
 * 로그 출력 (타임스탬프 포함)
 * @param {string} level - INFO|WARN|ERROR|OK|FAIL
 * @param {string} message
 */
function log(level, message) {
    const time = new Date().toLocaleTimeString('ko-KR', { hour12: false });
    const icon = {
        'INFO': '📋', 'WARN': '⚠️', 'ERROR': '❌',
        'OK': '✅', 'FAIL': '🔴', 'START': '🚀', 'DONE': '🏁'
    }[level] || '  ';
    console.log(`[${time}] ${icon} ${message}`);
}

// =========================================================
// 메인 테스트 실행
// =========================================================

/**
 * 전체 테스트 실행 함수
 * - 브라우저 시작
 * - 시스템별 로그인 → 페이지 순회 → 스크린샷 + 에러 수집
 * - 결과 JSON 저장
 * - (선택) 보고서 생성
 *
 * @returns {Promise<{outputDir: string, results: Array, summary: object}>}
 */
async function runTests() {
    if (!AUTO_TEST_ENABLED) {
        throw new Error('자동 테스트 기능이 일시 중지되었습니다.');
    }

    const startTime = Date.now();
    const outputDir = createOutputDir();
    const screenshotsDir = path.join(outputDir, 'screenshots');

    log('START', `=== 서울미녀 플랫폼 자동 테스트 시작 ===`);
    log('INFO', `출력 디렉토리: ${outputDir}`);
    log('INFO', `기본 URL: ${config.baseUrl}`);

    // 브라우저 시작 (서버의 Google Chrome 사용)
    const browser = await chromium.launch({
        headless: config.browser.headless,
        channel: config.browser.channel, // 'chrome' - 시스템 Chrome 사용
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ]
    });

    const allResults = [];
    let totalPass = 0, totalFail = 0, totalWarn = 0;

    // ── 시스템별 순회 ──
    const systems = config.systems.filter(sys => {
        if (cliOptions.system) return sys.id === cliOptions.system;
        return true;
    });

    for (const system of systems) {
        log('INFO', `\n━━━ ${system.name} ━━━`);
        log('INFO', system.description);

        // 시스템별 브라우저 컨텍스트 (쿠키/세션 격리)
        const context = await browser.newContext({
            viewport: config.browser.viewport,
            locale: 'ko-KR',
            timezoneId: 'Asia/Seoul',
            // SSL 인증서 무시 (자체 서명 대응)
            ignoreHTTPSErrors: true,
        });

        const page = await context.newPage();
        const detector = new ErrorDetector(page);

        // ── 로그인 처리 (전/후 스크린샷 포함) ──
        let loginSuccess = false;
        if (system.loginUrl) {
            const loginResult = await performLogin(page, system, screenshotsDir);
            loginSuccess = loginResult.success;

            // 로그인 캡처 결과를 전체 결과에 추가
            if (loginResult.loginResults && loginResult.loginResults.length > 0) {
                for (const lr of loginResult.loginResults) {
                    allResults.push(lr);
                    if (lr.status === 'PASS') totalPass++;
                    else if (lr.status === 'FAIL') totalFail++;
                    else totalWarn++;
                }
            }

            if (!loginSuccess) {
                log('FAIL', `${system.name} 로그인 실패 - 이 시스템 건너뜀`);
                await context.close();
                continue;
            }
        }

        // ── 페이지별 순회 ──
        const pages = system.pages.filter(p => {
            if (cliOptions.pages && cliOptions.pages.length > 0) return cliOptions.pages.includes(p.url);
            if (cliOptions.page) return p.name.includes(cliOptions.page);
            return true;
        });

        for (let i = 0; i < pages.length; i++) {
            const pageConfig = pages[i];
            const pageNum = i + 1;

            log('INFO', `  [${pageNum}/${pages.length}] ${pageConfig.name}...`);

            // 페이지 간 딜레이 (실시간 관찰용)
            if (cliOptions.delay > 0 && i > 0) {
                log('INFO', `  ... ${cliOptions.delay / 1000}초 대기 중 ...`);
                await page.waitForTimeout(cliOptions.delay);
            }

            const result = await testPage(page, detector, system, pageConfig, screenshotsDir, pageNum);
            allResults.push(result);

            // 상태별 카운트
            if (result.status === 'PASS') totalPass++;
            else if (result.status === 'FAIL') totalFail++;
            else totalWarn++;

            const statusIcon = result.status === 'PASS' ? '✅' : result.status === 'WARN' ? '⚠️' : '🔴';
            log(result.status === 'PASS' ? 'OK' : result.status,
                `  ${statusIcon} ${pageConfig.name} [${result.httpStatus}] ${result.loadTimeMs}ms ${result.errors.summary}`);

            // 상세 정보 출력
            if (result.pageAnalysis) {
                const a = result.pageAnalysis;
                log('INFO', `    페이지 제목: ${a.pageTitle || '(없음)'}`);
                log('INFO', `    링크: ${a.linkCount}개 | 이미지: ${a.imageCount}개 | 폼: ${a.formCount}개 | 테이블: ${a.tableCount}개`);
                if (a.missingAltImages > 0) log('WARN', `    alt 없는 이미지: ${a.missingAltImages}개`);
                if (a.contentLength < 100) log('WARN', `    페이지 텍스트가 매우 짧음 (${a.contentLength}자)`);
            }
        }

        await context.close();
    }

    await browser.close();

    // ── 결과 저장 ──
    const elapsedSec = ((Date.now() - startTime) / 1000).toFixed(1);

    const summary = {
        testDate: new Date().toISOString(),
        baseUrl: config.baseUrl,
        totalPages: allResults.length,
        pass: totalPass,
        fail: totalFail,
        warn: totalWarn,
        elapsedSeconds: parseFloat(elapsedSec),
        systems: systems.map(s => s.name),
    };

    // results.json 저장
    const resultsJson = {
        summary,
        results: allResults
    };
    fs.writeFileSync(
        path.join(outputDir, 'results.json'),
        JSON.stringify(resultsJson, null, 2),
        'utf-8'
    );

    log('DONE', `\n=== 테스트 완료 ===`);
    log('INFO', `총 ${allResults.length}페이지: ✅${totalPass} / ⚠️${totalWarn} / 🔴${totalFail}`);
    log('INFO', `소요시간: ${elapsedSec}초`);
    log('INFO', `결과: ${outputDir}/results.json`);

    // ── 보고서 생성 ──
    if (!cliOptions.noReport) {
        log('INFO', '\n보고서 생성 중...');
        try {
            const { generateReport } = require('./report-generator');
            await generateReport(outputDir, resultsJson);
            log('OK', `보고서 생성 완료: ${outputDir}/report.docx`);
        } catch (err) {
            log('ERROR', `보고서 생성 실패: ${err.message}`);
            if (cliOptions.verbose) console.error(err.stack);
        }
    }

    return { outputDir, results: allResults, summary };
}

// =========================================================
// 로그인 처리
// =========================================================

/**
 * 시스템 로그인 수행 + 로그인 후 스크린샷 캡처
 * - credentials 정보를 사용해 폼 자동 입력
 * - loginSuccessIndicator로 성공 여부 판단
 * - 인증정보가 입력된 로그인 폼은 캡처하지 않음
 *
 * @param {import('playwright').Page} page
 * @param {object} system - test-config.json의 system 항목
 * @param {string} screenshotsDir - 스크린샷 저장 디렉토리
 * @returns {Promise<{success: boolean, loginResults: Array}>} 로그인 성공 + 캡처 결과
 */
async function performLogin(page, system, screenshotsDir) {
    const { loginUrl } = system;
    const credentials = {
        ...system.credentials,
        password: resolveSecret(system.credentials?.password, `${system.id} login`),
    };
    const fullUrl = config.baseUrl + loginUrl;
    const loginResults = []; // 로그인 관련 캡처 결과 모음

    try {
        log('INFO', `  로그인: ${fullUrl}`);

        // 로그인 페이지 이동
        const loginStartTime = Date.now();
        const response = await page.goto(fullUrl, {
            waitUntil: 'networkidle',
            timeout: config.browser.timeout
        });

        if (!response || response.status() >= 400) {
            log('ERROR', `  로그인 페이지 HTTP ${response?.status() || 'N/A'}`);
            return { success: false, loginResults };
        }

        // 로그인 폼이 나타날 때까지 대기
        await page.waitForSelector(credentials.usernameField, { timeout: 10000 });
        await page.waitForTimeout(1000); // 폼 렌더링 대기

        // 필드 초기화 후 입력
        await page.fill(credentials.usernameField, '');
        await page.fill(credentials.usernameField, credentials.username);

        await page.fill(credentials.passwordField, '');
        await page.fill(credentials.passwordField, credentials.password);

        await page.waitForTimeout(500); // 입력 후 약간 대기

        const sysPrefix = sanitize(system.id);

        // ── 로그인 버튼 클릭 ──
        const afterLoginStart = Date.now();
        await page.click(credentials.submitButton);

        // 로그인 성공 대기 (URL 변경 또는 특정 요소 출현)
        await page.waitForTimeout(3000); // 리다이렉트 대기

        const currentUrl = page.url();
        const indicator = system.loginSuccessIndicator;

        let loginSuccess = false;

        const stillOnLogin = await page.$(credentials.usernameField);
        // URL 성공 지표와 로그인 폼 소실을 모두 확인한다.
        if (indicator && currentUrl.includes(indicator) && !stillOnLogin) {
            log('OK', `  로그인 성공: ${currentUrl}`);
            loginSuccess = true;
        } else {
            log('ERROR', `  로그인 실패: 성공 URL/폼 상태 검증 실패`);
        }

        // 로그인에 성공한 뒤의 화면만 저장한다. 실패한 폼에는 비밀번호 값이
        // 남아 있을 수 있으므로 스크린샷을 생성하지 않는다.
        await page.waitForTimeout(1000); // 로딩 대기
        let afterScreenshot = null;
        if (loginSuccess) try {
            afterScreenshot = path.join(screenshotsDir, `00_${sysPrefix}_로그인_성공_후.png`);
            await page.screenshot({
                path: afterScreenshot,
                fullPage: config.browser.fullPage,
            });
            log('OK', '  로그인 후 캡처: 성공');
        } catch (e) {
            log('WARN', `  로그인 후 캡처 실패: ${e.message}`);
            afterScreenshot = null;
        }

        let pageAnalysis = null;
        try { pageAnalysis = await analyzePage(page); } catch (e) {}

        loginResults.push({
            system: system.name,
            systemId: system.id,
            page: `${system.name} - 로그인 ${loginSuccess ? '성공' : '실패'} 후`,
            url: currentUrl,
            description: `로그인 버튼 클릭 후 화면 - ${loginSuccess ? '대시보드 이동됨' : '로그인 실패'}`,
            httpStatus: 200,
            loadTimeMs: Date.now() - afterLoginStart,
            screenshotPath: afterScreenshot,
            status: loginSuccess ? 'PASS' : 'FAIL',
            errors: { hasErrors: !loginSuccess, overallStatus: loginSuccess ? 'PASS' : 'FAIL', summary: loginSuccess ? '에러 없음' : '로그인 실패', httpErrors: [], phpErrors: [], jsErrors: [], jsWarnings: [], pageErrors: [], failedResources: [], counts: { httpErrors: 0, phpErrors: 0, jsErrors: 0, jsWarnings: 0, pageErrors: 0, failedResources: 0 } },
            elementChecks: [],
            pageAnalysis,
        });

        return { success: loginSuccess, loginResults };

    } catch (err) {
        log('ERROR', `  로그인 에러: ${err.message}`);
        loginResults.push({
            system: system.name,
            systemId: system.id,
            page: `${system.name} - 로그인 에러`,
            url: fullUrl,
            description: `로그인 에러: ${err.message}`,
            httpStatus: 0,
            loadTimeMs: 0,
            screenshotPath: null,
            status: 'FAIL',
            errors: { hasErrors: true, overallStatus: 'FAIL', summary: `로그인 에러: ${err.message}`, httpErrors: [], phpErrors: [], jsErrors: [], jsWarnings: [], pageErrors: [], failedResources: [], counts: {} },
            elementChecks: [],
            pageAnalysis: null,
        });
        return { success: false, loginResults };
    }
}

// =========================================================
// 페이지별 테스트
// =========================================================

/**
 * 단일 페이지 테스트
 * - 페이지 이동 → HTTP 상태 확인 → 스크린샷 → PHP 에러 → JS 에러 → 엘리먼트 체크
 *
 * @param {import('playwright').Page} page
 * @param {ErrorDetector} detector
 * @param {object} system - 시스템 설정
 * @param {object} pageConfig - 페이지 설정
 * @param {string} screenshotsDir - 스크린샷 저장 디렉토리
 * @param {number} pageNum - 페이지 번호 (파일명용)
 * @returns {Promise<object>} 테스트 결과
 */
async function testPage(page, detector, system, pageConfig, screenshotsDir, pageNum) {
    const fullUrl = config.baseUrl + pageConfig.url;
    const startTime = Date.now();

    // 에러 감지 초기화 + 모니터링 시작
    detector.reset();
    detector.startMonitoring();

    const result = {
        system: system.name,
        systemId: system.id,
        page: pageConfig.name,
        url: fullUrl,
        description: pageConfig.description || '',
        httpStatus: 0,
        loadTimeMs: 0,
        screenshotPath: null,
        status: 'PASS',
        errors: {},
        elementChecks: [],
        pageAnalysis: null,  // 상세 페이지 분석 결과
    };

    try {
        // ── 1. 페이지 이동 ──
        const response = await page.goto(fullUrl, {
            waitUntil: 'networkidle',
            timeout: config.browser.timeout
        });

        result.httpStatus = response ? response.status() : 0;
        result.loadTimeMs = Date.now() - startTime;

        // HTTP 에러 시 즉시 FAIL
        if (result.httpStatus >= 500) {
            result.status = 'FAIL';
        } else if (result.httpStatus >= 400) {
            result.status = 'WARN';
        }

        // ── 2. 캡처 딜레이 (렌더링 완료 대기) ──
        await page.waitForTimeout(config.browser.captureDelay);

        // ── 3. 스크린샷 ──
        const screenshotName = `${String(pageNum).padStart(2, '0')}_${sanitize(system.id)}_${sanitize(pageConfig.name)}.png`;
        const screenshotPath = path.join(screenshotsDir, screenshotName);

        await page.screenshot({
            path: screenshotPath,
            fullPage: config.browser.fullPage,
        });
        result.screenshotPath = screenshotPath;

        // ── 4. PHP 에러 감지 ──
        await detector.detectPhpErrors();

        // ── 5. 엘리먼트 체크 ──
        const checks = pageConfig.checks || [];
        for (const check of checks) {
            if (check === 'noPhpError' || check === 'noJsError') continue; // 별도 처리

            if (check.startsWith('elementExists:')) {
                const selector = check.replace('elementExists:', '');
                const checkResult = await detector.checkElement(selector);
                result.elementChecks.push({
                    selector,
                    ...checkResult
                });
                if (!checkResult.found) {
                    result.status = result.status === 'FAIL' ? 'FAIL' : 'WARN';
                }
            }
        }

    } catch (err) {
        // 페이지 이동 자체 실패 (타임아웃, 네트워크 에러 등)
        result.status = 'FAIL';
        result.loadTimeMs = Date.now() - startTime;
        result.errors = {
            summary: `페이지 로드 실패: ${err.message}`,
            httpErrors: [],
            phpErrors: [],
            jsErrors: [{ message: err.message }],
            jsWarnings: [],
            pageErrors: [],
            failedResources: [],
            counts: {}
        };

        // 에러 상태에서도 스크린샷 시도
        try {
            const screenshotName = `${String(pageNum).padStart(2, '0')}_${sanitize(system.id)}_${sanitize(pageConfig.name)}_ERROR.png`;
            const screenshotPath = path.join(screenshotsDir, screenshotName);
            await page.screenshot({ path: screenshotPath, fullPage: false });
            result.screenshotPath = screenshotPath;
        } catch (e) { /* 스크린샷 실패 무시 */ }

        detector.stopMonitoring();
        return result;
    }

    // ── 6. 상세 페이지 분석 ──
    try {
        result.pageAnalysis = await analyzePage(page);
    } catch (e) {
        result.pageAnalysis = { error: e.message };
    }

    // ── 7. 에러 종합 결과 ──
    detector.stopMonitoring();
    const errorResults = detector.getResults();
    result.errors = errorResults;

    // 최종 상태 결정 (에러 감지 결과 반영)
    if (errorResults.overallStatus === 'FAIL' && result.status !== 'FAIL') {
        result.status = 'FAIL';
    } else if (errorResults.overallStatus === 'WARN' && result.status === 'PASS') {
        result.status = 'WARN';
    }

    return result;
}

// =========================================================
// 상세 페이지 분석
// =========================================================

/**
 * 페이지의 구조와 콘텐츠를 상세 분석
 * - 페이지 제목, 링크 수, 이미지 수, 폼 수, 테이블 수
 * - 텍스트 길이, alt 속성 누락 이미지
 * - 주요 UI 요소 존재 여부
 *
 * @param {import('playwright').Page} page
 * @returns {Promise<object>} 분석 결과
 */
async function analyzePage(page) {
    return await page.evaluate(() => {
        const body = document.body;
        if (!body) return { error: 'body 없음' };

        const text = body.innerText || '';
        const allLinks = document.querySelectorAll('a[href]');
        const allImages = document.querySelectorAll('img');
        const allForms = document.querySelectorAll('form');
        const allTables = document.querySelectorAll('table');
        const allButtons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
        const allInputs = document.querySelectorAll('input, textarea, select');
        const allCards = document.querySelectorAll('.card, .stat-card, [class*="card"]');
        const allModals = document.querySelectorAll('.modal, [class*="modal"]');

        // alt 없는 이미지 감지
        let missingAltImages = 0;
        const brokenImageHints = [];
        allImages.forEach(img => {
            if (!img.alt && !img.getAttribute('alt')) missingAltImages++;
            // 자연 크기가 0이면 깨진 이미지일 가능성
            if (img.complete && img.naturalWidth === 0 && img.src) {
                brokenImageHints.push(img.src.split('/').pop().substring(0, 60));
            }
        });

        // 외부 링크 vs 내부 링크
        let externalLinks = 0, internalLinks = 0;
        allLinks.forEach(a => {
            const href = a.getAttribute('href') || '';
            if (href.startsWith('http') && !href.includes(location.hostname)) externalLinks++;
            else internalLinks++;
        });

        // 네비게이션/사이드바 감지
        const hasNavbar = !!document.querySelector('nav, .navbar, .sidebar, [class*="nav"]');
        const hasFooter = !!document.querySelector('footer, .footer, [class*="footer"]');
        const hasBreadcrumb = !!document.querySelector('.breadcrumb, [class*="breadcrumb"]');

        // 헤딩 구조
        const headings = {};
        for (let i = 1; i <= 6; i++) {
            const els = document.querySelectorAll('h' + i);
            if (els.length > 0) headings['h' + i] = els.length;
        }

        // 메타 데이터
        const metaViewport = !!document.querySelector('meta[name="viewport"]');
        const metaCharset = !!document.querySelector('meta[charset]') ||
                           !!document.querySelector('meta[http-equiv="Content-Type"]');

        return {
            pageTitle: document.title || '',
            contentLength: text.length,
            linkCount: allLinks.length,
            internalLinks,
            externalLinks,
            imageCount: allImages.length,
            missingAltImages,
            brokenImageHints: brokenImageHints.slice(0, 5),
            formCount: allForms.length,
            inputCount: allInputs.length,
            buttonCount: allButtons.length,
            tableCount: allTables.length,
            cardCount: allCards.length,
            modalCount: allModals.length,
            headings,
            hasNavbar,
            hasFooter,
            hasBreadcrumb,
            metaViewport,
            metaCharset,
            // 성능 관련
            domNodeCount: document.querySelectorAll('*').length,
            scriptCount: document.querySelectorAll('script').length,
            stylesheetCount: document.querySelectorAll('link[rel="stylesheet"]').length + document.querySelectorAll('style').length,
        };
    });
}

// =========================================================
// API 엔드포인트용 래퍼 (server.js에서 호출)
// =========================================================

/**
 * Express 라우트에서 호출하는 래퍼 함수
 * SSE로 실시간 진행률을 전송하면서 테스트 실행
 *
 * @param {object} options - { system, page, sseCallback }
 * @returns {Promise<object>} 테스트 결과
 */
async function runTestsApi(options = {}) {
    if (options.system) cliOptions.system = options.system;
    if (options.page) cliOptions.page = options.page;
    if (options.pages) cliOptions.pages = options.pages;
    if (options.noReport) cliOptions.noReport = options.noReport;
    if (options.delay != null) cliOptions.delay = parseInt(options.delay) || 0;

    return await runTests();
}

// =========================================================
// CLI 직접 실행
// =========================================================
if (require.main === module) {
    runTests()
        .then(({ summary }) => {
            // 종료 코드: 실패가 있으면 1
            process.exit(summary.fail > 0 ? 1 : 0);
        })
        .catch(err => {
            console.error('테스트 실행 치명적 에러:', err);
            process.exit(2);
        });
}

module.exports = { runTests, runTestsApi };

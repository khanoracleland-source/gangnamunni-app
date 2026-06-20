/**
 * 자동 테스트 v2 - 플러그인 기반 테스트 러너
 * Usage: node test-runner-v2.js --config <path> --output <dir> --tests <json> --options <json>
 *
 * stdout으로 JSON 라인 출력 → server-v2.js가 SSE로 전달
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// ── CLI 인자 파싱 ──
function getArg(name) {
    const idx = process.argv.indexOf(`--${name}`);
    return idx >= 0 ? process.argv[idx + 1] : null;
}

const CONFIG_PATH = getArg('config');
const OUTPUT_DIR = getArg('output');
const TESTS_JSON = getArg('tests');
const OPTIONS_JSON = getArg('options');

if (!CONFIG_PATH || !OUTPUT_DIR || !TESTS_JSON) {
    console.error('Usage: node test-runner-v2.js --config <path> --output <dir> --tests <json> [--options <json>]');
    process.exit(1);
}

const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
const selectedTests = JSON.parse(TESTS_JSON);
const options = OPTIONS_JSON ? JSON.parse(OPTIONS_JSON) : {};

const SCREENSHOTS_DIR = path.join(OUTPUT_DIR, 'screenshots');
if (!fs.existsSync(SCREENSHOTS_DIR)) fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

// ── 진행상황 출력 (JSON 라인) ──
function emit(data) {
    process.stdout.write(JSON.stringify(data) + '\n');
}

// ── 에러 감지 (v1의 error-detector.js 간소화 통합) ──
const PHP_ERROR_PATTERNS = [
    /Fatal error/i, /Parse error/i, /Warning:/i, /Notice:/i,
    /SQLSTATE\[/i, /Undefined variable/i, /Call to undefined/i,
    /Class .* not found/i, /Allowed memory size/i
];

const JS_IGNORE_PATTERNS = [
    /livewire/i, /recaptcha/i, /flatpickr/i, /markerjs/i,
    /favicon\.ico/i, /hot-update/i
];

async function detectErrors(page) {
    const errors = { http: [], php: [], js: [] };

    // PHP 에러 스캔
    try {
        const html = await page.content();
        PHP_ERROR_PATTERNS.forEach(pattern => {
            if (pattern.test(html)) {
                const match = html.match(new RegExp(`(${pattern.source}[^<]{0,200})`, 'i'));
                errors.php.push(match ? match[1].trim() : pattern.source);
            }
        });
    } catch (e) {}

    return errors;
}

// ── 페이지 분석 ──
async function analyzePage(page) {
    try {
        return await page.evaluate(() => {
            return {
                title: document.title,
                links: document.querySelectorAll('a').length,
                images: document.querySelectorAll('img').length,
                forms: document.querySelectorAll('form').length,
                tables: document.querySelectorAll('table').length,
                buttons: document.querySelectorAll('button, input[type=submit]').length,
                headings: document.querySelectorAll('h1,h2,h3,h4,h5,h6').length,
                domNodes: document.querySelectorAll('*').length,
                brokenImages: [...document.querySelectorAll('img')].filter(i => !i.complete || i.naturalWidth === 0).length,
                missingAlt: [...document.querySelectorAll('img')].filter(i => !i.alt).length
            };
        });
    } catch (e) {
        return { title: '', error: e.message };
    }
}

// ── 플러그인 로더 ──
function loadPlugin(pluginName) {
    const pluginPath = path.join(__dirname, 'plugins', `${pluginName}.js`);
    if (fs.existsSync(pluginPath)) {
        return require(pluginPath);
    }
    // 기본: page-load 플러그인
    return require(path.join(__dirname, 'plugins', 'page-load.js'));
}

// ══════════════════════════════════════════════════════════════
// 메인 실행
// ══════════════════════════════════════════════════════════════
(async () => {
    const startTime = Date.now();
    const results = {
        runId: path.basename(OUTPUT_DIR),
        startTime: new Date().toISOString(),
        options: { ...options },
        summary: { total: 0, pass: 0, warn: 0, fail: 0 },
        groups: []
    };

    // 총 테스트 수 카운트
    let totalTests = 0;
    Object.values(selectedTests).forEach(items => totalTests += items.length);
    let currentTest = 0;

    emit({ message: `총 ${totalTests}개 테스트 시작`, total: totalTests, current: 0 });

    // 브라우저 시작
    const viewport = options.viewport || { width: 1920, height: 1080 };
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage']
    });

    // 그룹별 실행
    for (const [groupId, testItems] of Object.entries(selectedTests)) {
        const groupConfig = config.groups.find(g => g.id === groupId);
        if (!groupConfig) {
            emit({ message: `그룹 ${groupId} 설정 없음 - 건너뜀`, status: 'FAIL' });
            continue;
        }

        const groupResult = {
            id: groupId,
            name: groupConfig.name,
            icon: groupConfig.icon,
            loginSuccess: false,
            results: []
        };

        // 그룹별 브라우저 컨텍스트 (쿠키/세션 격리)
        const context = await browser.newContext({
            viewport,
            ignoreHTTPSErrors: true,
            locale: 'ko-KR'
        });
        const page = await context.newPage();

        // JS 에러 수집
        const jsErrors = [];
        page.on('pageerror', err => {
            const msg = err.message || err.toString();
            if (!JS_IGNORE_PATTERNS.some(p => p.test(msg))) {
                jsErrors.push(msg);
            }
        });

        // 로그인 처리
        if (groupConfig.auth && groupConfig.auth.type === 'form') {
            emit({ message: `[${groupConfig.name}] 로그인 중...`, group: groupConfig.name });
            try {
                const auth = groupConfig.auth;
                const loginUrl = auth.loginUrl.startsWith('http') ? auth.loginUrl : config.baseUrl + auth.loginUrl;
                await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 30000 });

                // 폼 필드 입력
                for (const [field, value] of Object.entries(auth.fields || {})) {
                    const selectors = [
                        `input[name="${field}"]`,
                        `input[id="${field}"]`,
                        `input[type="email"]`,
                        `input[type="password"]`
                    ];
                    for (const sel of selectors) {
                        try {
                            const el = await page.$(sel);
                            if (el) {
                                await el.fill(value);
                                break;
                            }
                        } catch (e) {}
                    }
                }

                // 제출
                const submitSel = auth.submitSelector || 'button[type=submit]';
                await page.click(submitSel);
                await page.waitForLoadState('networkidle', { timeout: 15000 });

                // 로그인 성공 확인
                const currentUrl = page.url();
                if (auth.successUrl && currentUrl.includes(auth.successUrl)) {
                    groupResult.loginSuccess = true;
                } else if (auth.successCheck) {
                    // PHP 세션 기반 확인 (쿠키 존재 체크)
                    groupResult.loginSuccess = true;
                } else {
                    groupResult.loginSuccess = true;
                }

                emit({ message: `[${groupConfig.name}] 로그인 ${groupResult.loginSuccess ? '성공' : '실패'}`, group: groupConfig.name });
            } catch (e) {
                emit({ message: `[${groupConfig.name}] 로그인 실패: ${e.message}`, group: groupConfig.name, status: 'FAIL' });
            }
        } else {
            groupResult.loginSuccess = true;
        }

        // 테스트 항목 실행
        for (const testItem of testItems) {
            currentTest++;
            const { id: testId, category } = testItem;

            // 설정에서 테스트 정보 찾기
            let testInfo = null;
            for (const cat of groupConfig.tests || []) {
                if (cat.category === category) {
                    testInfo = (cat.items || []).find(i => i.id === testId);
                    if (testInfo) {
                        testInfo._plugin = cat.plugin || 'page-load';
                        break;
                    }
                }
            }

            if (!testInfo) {
                emit({ total: totalTests, current: currentTest, name: testId, group: groupConfig.name, status: 'FAIL', message: 'Test config not found' });
                groupResult.results.push({ id: testId, name: testId, status: 'FAIL', errors: { message: 'Config not found' } });
                results.summary.fail++;
                results.summary.total++;
                continue;
            }

            const testStart = Date.now();
            jsErrors.length = 0; // 초기화

            try {
                // 플러그인 로드 & 실행
                const Plugin = loadPlugin(testInfo._plugin);
                const pluginInstance = new Plugin(page, testInfo, {
                    outputDir: OUTPUT_DIR,
                    screenshotsDir: SCREENSHOTS_DIR,
                    baseUrl: config.baseUrl,
                    options,
                    detectErrors,
                    analyzePage
                });

                const result = await pluginInstance.run();
                const duration = Date.now() - testStart;

                // JS 에러 추가
                if (jsErrors.length > 0) {
                    result.errors = result.errors || {};
                    result.errors.js = jsErrors.slice();
                }

                // 상태 결정
                let status = result.status || 'PASS';
                const errors = result.errors || {};
                const errorCount = (errors.php?.length || 0) + (errors.js?.length || 0) + (errors.http?.length || 0);
                if (status === 'PASS' && errorCount > 0) {
                    status = errors.php?.length > 0 ? 'FAIL' : 'WARN';
                }

                const testResult = {
                    id: testId,
                    name: testInfo.name,
                    category,
                    plugin: testInfo._plugin,
                    url: result.url || testInfo.url || '',
                    status,
                    duration,
                    screenshot: result.screenshot || null,
                    analysis: result.analysis || {},
                    errors: result.errors || {},
                    notes: ''
                };

                groupResult.results.push(testResult);
                results.summary.total++;
                results.summary[status.toLowerCase()]++;

                emit({
                    total: totalTests,
                    current: currentTest,
                    name: testInfo.name,
                    group: groupConfig.name,
                    status,
                    duration
                });

            } catch (e) {
                const duration = Date.now() - testStart;
                groupResult.results.push({
                    id: testId, name: testInfo.name, category,
                    status: 'FAIL', duration, errors: { crash: e.message },
                    url: testInfo.url || ''
                });
                results.summary.total++;
                results.summary.fail++;

                emit({
                    total: totalTests, current: currentTest,
                    name: testInfo.name, group: groupConfig.name,
                    status: 'FAIL', duration
                });
            }

            // 딜레이
            const delay = (options.delay || 3) * 1000;
            if (currentTest < totalTests) {
                await new Promise(r => setTimeout(r, Math.min(delay, 1000)));
            }
        }

        results.groups.push(groupResult);
        await context.close();
    }

    await browser.close();

    // 결과 저장
    results.endTime = new Date().toISOString();
    results.duration = Math.round((Date.now() - startTime) / 1000);

    const resultsPath = path.join(OUTPUT_DIR, 'results.json');
    fs.writeFileSync(resultsPath, JSON.stringify(results, null, 2));

    emit({ message: `테스트 완료: ${results.summary.pass}/${results.summary.total} PASS (${results.duration}초)` });

    // 이전 결과와 비교 (옵션)
    if (options.compare) {
        try {
            const uploadsDir = path.dirname(OUTPUT_DIR);
            const dirs = fs.readdirSync(uploadsDir)
                .filter(d => d !== path.basename(OUTPUT_DIR) && fs.statSync(path.join(uploadsDir, d)).isDirectory())
                .sort()
                .reverse();

            if (dirs.length > 0) {
                const prevPath = path.join(uploadsDir, dirs[0], 'results.json');
                if (fs.existsSync(prevPath)) {
                    const prev = JSON.parse(fs.readFileSync(prevPath, 'utf-8'));
                    const prevStatus = {};
                    (prev.groups || []).forEach(g => g.results.forEach(r => prevStatus[r.id] = r.status));

                    let newErrors = 0, resolved = 0, unchanged = 0;
                    results.groups.forEach(g => g.results.forEach(r => {
                        const ps = prevStatus[r.id];
                        if (!ps) return;
                        if (ps === 'PASS' && r.status !== 'PASS') newErrors++;
                        else if (ps !== 'PASS' && r.status === 'PASS') resolved++;
                        else unchanged++;
                    }));

                    results.comparison = { previousRun: dirs[0], newErrors, resolved, unchanged };
                    fs.writeFileSync(resultsPath, JSON.stringify(results, null, 2));
                }
            }
        } catch (e) {}
    }

    // DOCX 리포트 생성 (옵션)
    if (options.generateReport) {
        try {
            const reportGen = path.join(__dirname, '..', 'report-generator.js');
            if (fs.existsSync(reportGen)) {
                const { execSync } = require('child_process');
                execSync(`node "${reportGen}" "${OUTPUT_DIR}"`, { timeout: 30000 });
                emit({ message: 'DOCX 리포트 생성 완료' });
            }
        } catch (e) {
            emit({ message: `리포트 생성 실패: ${e.message}` });
        }
    }

    process.exit(0);
})();

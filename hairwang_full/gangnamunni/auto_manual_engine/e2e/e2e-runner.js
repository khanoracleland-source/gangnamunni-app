#!/usr/bin/env node
/**
 * E2E 테스트 러너 — Playwright 기반
 * Usage: node e2e-runner.js --tests '["A1","A2","B1"]' --run-id 'abc123'
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const config = require('./e2e-config.json');
const db = require('./helpers/db-reporter');
const { adminLogin } = require('./helpers/auth');
const userTests = require('./tests/user_tests');
const adminTests = require('./tests/admin_tests');
const apiTests = require('./tests/api_tests');

// CLI 인수 파싱
const args = {};
process.argv.slice(2).forEach((arg, i, arr) => {
    if (arg.startsWith('--')) args[arg.slice(2)] = arr[i + 1] || '';
});

const runId = args['run-id'] || 'run_' + Date.now();
const selectedTests = args.tests ? JSON.parse(args.tests) : config.tests.map(t => t.id);
const viewport = args.viewport ? JSON.parse(args.viewport) : { width: 1920, height: 1080 };

const allTests = { ...userTests, ...adminTests, ...apiTests };
const testDefs = config.tests.filter(t => selectedTests.includes(t.id));

const ssDir = path.resolve(__dirname, config.screenshotsDir, runId);

// 에러→수정 매핑
const fixLinks = {
    B4: 'admin_hospitals.php', B5: 'admin_reviews.php', B6: 'admin_ad_settings.php',
    B7: 'admin_design_tokens.php', B8: 'admin_theme.php', B9: 'admin_industry.php',
    B10: 'admin_treatments.php', B11: 'admin_events.php', B12: 'admin_send_notification.php',
};

function getSuggestion(err, testId) {
    const msg = err.message || '';
    if (msg.includes('HTTP 5')) return 'PHP 에러 확인 — 서버 로그: /var/log/httpd/error_log';
    if (msg.includes('없음') || msg.includes('not found')) return 'HTML 구조 변경 확인 — 셀렉터가 일치하지 않습니다';
    if (msg.includes('로그인')) return '관리자 계정 확인 (admin/admin123)';
    if (msg.includes('timeout') || msg.includes('Timeout')) return '서버 응답 속도 확인 — 30초 초과';
    return '테스트 코드 또는 대상 페이지 확인 필요';
}

(async () => {
    console.log(`[E2E] Run: ${runId}, Tests: ${testDefs.length}`);
    fs.mkdirSync(ssDir, { recursive: true });

    await db.createRun(runId, selectedTests, { viewport });

    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
    const page = await context.newPage();

    let passed = 0, failed = 0, warned = 0, skipped = 0;
    const startTime = Date.now();
    let adminLoggedIn = false;

    // Phase 1: 비로그인 사용자 테스트 수행
    // Phase 2: 로그인 후 같은 페이지 재캡처
    // Phase 3: 관리자 테스트

    for (const td of testDefs) {
        if (await db.checkAborted(runId)) { console.log('[E2E] Aborted'); break; }

        const testFn = allTests[td.id];
        if (!testFn) {
            await db.updateResult(runId, td.id, td.name, td.category, 'SKIP', { fix_suggestion: '테스트 함수 미구현' });
            skipped++;
            continue;
        }

        // 관리자 테스트 첫 실행 시 로그인
        if (td.category === 'admin' && !adminLoggedIn && td.id !== 'B1' && td.id !== 'B13') {
            await adminLogin(page, 'admin');
            adminLoggedIn = true;
        }

        console.log(`  [${td.id}] ${td.name}...`);
        await db.updateResult(runId, td.id, td.name, td.category, 'RUNNING');
        const t0 = Date.now();

        try {
            const result = await testFn(page, context);
            const duration = Date.now() - t0;

            // 스크린샷 캡처
            const ssPath = `${ssDir}/${td.id}.png`;
            await page.screenshot({ path: ssPath, fullPage: true }).catch(() => {});

            await db.updateResult(runId, td.id, td.name, td.category, 'PASS', {
                duration_ms: duration,
                screenshot_path: `uploads/e2e_screenshots/${runId}/${td.id}.png`,
                metadata: result?.metadata || {},
            });
            passed++;
            console.log(`  [${td.id}] PASS (${duration}ms)`);

        } catch (err) {
            const duration = Date.now() - t0;
            const ssPath = `${ssDir}/${td.id}_fail.png`;
            await page.screenshot({ path: ssPath, fullPage: false }).catch(() => {});

            await db.updateResult(runId, td.id, td.name, td.category, 'FAIL', {
                error_msg: err.message,
                error_stack: err.stack,
                duration_ms: duration,
                screenshot_path: `uploads/e2e_screenshots/${runId}/${td.id}_fail.png`,
                fix_suggestion: getSuggestion(err, td.id),
                fix_link: fixLinks[td.id] || null,
            });
            failed++;
            console.log(`  [${td.id}] FAIL: ${err.message} (${duration}ms)`);
        }
    }

    // === 로그인 후 사용자 페이지 재캡처 ===
    console.log('\n  [LOGIN] Gnuboard 로그인 후 스크린샷 캡처...');
    try {
        const loggedCtx = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
        const loggedPage = await loggedCtx.newPage();

        // Gnuboard 로그인
        await loggedPage.goto(config.baseUrl + '/bbs/login.php', { waitUntil: 'networkidle', timeout: 15000 });
        await loggedPage.waitForTimeout(500);
        const loginInputs = await loggedPage.$$('input[type="text"], input[type="password"]');
        if (loginInputs.length >= 2) {
            await loginInputs[0].fill('test');
            await loginInputs[1].fill('test1234');
            const btn = await loggedPage.$('button[type="submit"], input[type="submit"]');
            if (btn) { await btn.click(); await loggedPage.waitForTimeout(2000); }
        }

        // 사용자 페이지 재캡처
        const userPages = testDefs.filter(t => t.category === 'user');
        for (const td of userPages) {
            try {
                const pageUrl = td.page || 'index.php';
                await loggedPage.goto(config.baseUrl + '/' + pageUrl, { waitUntil: 'networkidle', timeout: 10000 });
                await loggedPage.waitForTimeout(300);
                const liPath = `${ssDir}/${td.id}_loggedin.png`;
                await loggedPage.screenshot({ path: liPath, fullPage: true });
                console.log(`  [LOGIN] ${td.id} 로그인 후 캡처 OK`);
            } catch(e) {
                console.log(`  [LOGIN] ${td.id} 캡처 실패: ${e.message}`);
            }
        }
        await loggedCtx.close();
    } catch(e) {
        console.log(`  [LOGIN] 로그인 캡처 전체 실패: ${e.message}`);
    }

    await browser.close();

    const totalDuration = Date.now() - startTime;
    await db.finishRun(runId, { status: 'completed', passed, failed, warned, skipped, duration_ms: totalDuration });
    await db.close();

    console.log(`\n[E2E] Complete: ${passed} PASS, ${failed} FAIL, ${skipped} SKIP (${totalDuration}ms)`);
    process.exit(failed > 0 ? 1 : 0);
})();

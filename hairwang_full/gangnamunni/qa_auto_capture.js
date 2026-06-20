#!/usr/bin/env node
/**
 * QA 자동 캡처 스크립트 (확장)
 * Puppeteer로 PC(1280x800) + 모바일(375x667) 캡처
 * 관리자 페이지: 세션 쿠키 기반 로그인 후 캡처
 *
 * 사용법:
 *   node qa_auto_capture.js --auto <json_file>
 *   json: [{"page_key":"index","page_name":"홈페이지","page_url":"https://..."},...]
 *
 *   node qa_auto_capture.js <url> <page_key>
 *   → qa_screenshots/qa_auto_{page_key}_pc.png, qa_screenshots/qa_auto_{page_key}_mob.png
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const SCREENSHOT_DIR = path.join(__dirname, 'qa_screenshots');
const LOGIN_URL = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php';
const ADMIN_ID = 'admin';
const ADMIN_PW = 'admin123';
const PAGE_TIMEOUT = 30000;
const RENDER_WAIT = 1500;

// 관리자 페이지 여부 판단
function isAdminPage(url) {
    return /\/(dashboard|admin_|partner_)/.test(url);
}

// 관리자 로그인
async function loginAdmin(browser) {
    console.log('[LOGIN] 관리자 로그인 시도...');
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });

    try {
        await page.goto(LOGIN_URL, { waitUntil: 'networkidle2', timeout: PAGE_TIMEOUT });
        await new Promise(r => setTimeout(r, 1000));

        // 로그인 폼 입력
        const idInput = await page.$('input[name="ad_admin_id"], input[name="login_id"], input[name="username"], input[type="text"]');
        const pwInput = await page.$('input[name="ad_admin_pw"], input[name="login_pw"], input[name="password"], input[type="password"]');

        if (idInput && pwInput) {
            await idInput.click({ clickCount: 3 });
            await idInput.type(ADMIN_ID, { delay: 30 });
            await pwInput.click({ clickCount: 3 });
            await pwInput.type(ADMIN_PW, { delay: 30 });

            // 로그인 버튼 클릭
            const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                await submitBtn.click();
                await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: PAGE_TIMEOUT }).catch(() => {});
                await new Promise(r => setTimeout(r, 1000));
            }
            console.log('[LOGIN] 로그인 완료');
        } else {
            console.log('[LOGIN] 로그인 폼을 찾을 수 없음 (이미 로그인 상태일 수 있음)');
        }

        // 쿠키 추출
        const cookies = await page.cookies();
        await page.close();
        return cookies;
    } catch (e) {
        console.error('[LOGIN] 로그인 실패:', e.message);
        await page.close();
        return [];
    }
}

// 단일 URL 캡처
async function captureUrl(browser, url, pageKey, cookies) {
    const prefix = `qa_auto_${pageKey}`;
    const results = { page_key: pageKey, pc_path: null, mob_path: null, success: false, error: null };

    // PC 캡처
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 800 });
        if (cookies && cookies.length > 0) {
            await page.setCookie(...cookies);
        }
        await page.goto(url, { waitUntil: 'networkidle2', timeout: PAGE_TIMEOUT });
        await new Promise(r => setTimeout(r, RENDER_WAIT));
        const pcPath = path.join(SCREENSHOT_DIR, `${prefix}_pc.png`);
        await page.screenshot({ path: pcPath, fullPage: false });
        await page.close();
        results.pc_path = `qa_screenshots/${prefix}_pc.png`;
        console.log(`  [OK] PC: ${prefix}_pc.png`);
    } catch (e) {
        console.error(`  [FAIL] PC ${prefix}: ${e.message}`);
        results.error = e.message;
    }

    // 모바일 캡처
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 375, height: 667, isMobile: true });
        await page.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1');
        if (cookies && cookies.length > 0) {
            await page.setCookie(...cookies);
        }
        await page.goto(url, { waitUntil: 'networkidle2', timeout: PAGE_TIMEOUT });
        await new Promise(r => setTimeout(r, RENDER_WAIT));
        const mobPath = path.join(SCREENSHOT_DIR, `${prefix}_mob.png`);
        await page.screenshot({ path: mobPath, fullPage: false });
        await page.close();
        results.mob_path = `qa_screenshots/${prefix}_mob.png`;
        console.log(`  [OK] MOB: ${prefix}_mob.png`);
    } catch (e) {
        console.error(`  [FAIL] MOB ${prefix}: ${e.message}`);
        if (!results.error) results.error = e.message;
    }

    results.success = !!(results.pc_path || results.mob_path);
    return results;
}

async function main() {
    // 스크린샷 디렉토리 생성
    if (!fs.existsSync(SCREENSHOT_DIR)) {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }

    const args = process.argv.slice(2);

    const tmpDir = '/tmp/puppeteer_qa_auto_' + process.pid;
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/usr/bin/google-chrome',
        userDataDir: tmpDir,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-software-rasterizer']
    });

    try {
        if (args[0] === '--auto') {
            // 자동 모드: JSON 파일에서 페이지 목록 읽기
            const jsonFile = args[1];
            if (!jsonFile || !fs.existsSync(jsonFile)) {
                console.error('JSON 파일이 필요합니다');
                process.exit(1);
            }

            const pages = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
            console.log(`[QA Auto Capture] ${pages.length}개 페이지 캡처 시작`);

            // 관리자 페이지가 있으면 먼저 로그인
            const hasAdmin = pages.some(p => isAdminPage(p.page_url));
            let adminCookies = [];
            if (hasAdmin) {
                adminCookies = await loginAdmin(browser);
            }

            const results = [];
            for (const page of pages) {
                const cookies = isAdminPage(page.page_url) ? adminCookies : [];
                console.log(`[${results.length + 1}/${pages.length}] ${page.page_name} (${page.page_key})`);
                const result = await captureUrl(browser, page.page_url, page.page_key, cookies);
                results.push(result);
            }

            // 결과 JSON 출력 (마지막 줄에 파싱 가능한 JSON)
            console.log(JSON.stringify({ success: true, results }));

        } else {
            // 단일 모드
            const url = args[0];
            const pageKey = args[1] || 'capture';

            if (!url) {
                console.error('사용법: node qa_auto_capture.js <url> <page_key>');
                console.error('또는: node qa_auto_capture.js --auto <json_file>');
                process.exit(1);
            }

            console.log(`[QA Auto Capture] ${url} → ${pageKey}`);

            let cookies = [];
            if (isAdminPage(url)) {
                cookies = await loginAdmin(browser);
            }

            const result = await captureUrl(browser, url, pageKey, cookies);
            console.log(JSON.stringify({ success: true, ...result }));
        }
    } finally {
        await browser.close();
        try { fs.rmSync(tmpDir, { recursive: true, force: true }); } catch (e) {}
    }
}

main().catch(e => {
    console.error(JSON.stringify({ success: false, error: e.message }));
    process.exit(1);
});

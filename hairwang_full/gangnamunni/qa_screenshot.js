#!/usr/bin/env node
/**
 * QA 스크린샷 캡처 스크립트
 * Puppeteer로 PC(1280x800) + 모바일(375x667) 캡처
 *
 * 사용법:
 *   node qa_screenshot.js <url> <output_prefix>
 *   예: node qa_screenshot.js https://hms.seoulbeauty.app/hairwang/gangnamunni/index.php F01
 *   → qa_screenshots/F01_pc.png, qa_screenshots/F01_mob.png
 *
 *   node qa_screenshot.js --batch <json_file>
 *   예: node qa_screenshot.js --batch /tmp/qa_batch.json
 *   json: [{"url":"...","prefix":"F01"}, ...]
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const SCREENSHOT_DIR = path.join(__dirname, 'qa_screenshots');

async function captureUrl(browser, url, prefix) {
    const results = {};

    // PC 캡처 (1280x800)
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 800 });
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(r => setTimeout(r, 1000)); // 렌더링 대기
        const pcPath = path.join(SCREENSHOT_DIR, `${prefix}_pc.png`);
        await page.screenshot({ path: pcPath, fullPage: false });
        await page.close();
        results.pc = `qa_screenshots/${prefix}_pc.png`;
        console.log(`  [OK] PC: ${prefix}_pc.png`);
    } catch (e) {
        console.error(`  [FAIL] PC ${prefix}: ${e.message}`);
        results.pc = null;
    }

    // 모바일 캡처 (375x667)
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 375, height: 667, isMobile: true });
        await page.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1');
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(r => setTimeout(r, 1000)); // 렌더링 대기
        const mobPath = path.join(SCREENSHOT_DIR, `${prefix}_mob.png`);
        await page.screenshot({ path: mobPath, fullPage: false });
        await page.close();
        results.mob = `qa_screenshots/${prefix}_mob.png`;
        console.log(`  [OK] MOB: ${prefix}_mob.png`);
    } catch (e) {
        console.error(`  [FAIL] MOB ${prefix}: ${e.message}`);
        results.mob = null;
    }

    return results;
}

async function main() {
    // 스크린샷 디렉토리 생성
    if (!fs.existsSync(SCREENSHOT_DIR)) {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }

    const args = process.argv.slice(2);

    // Apache(httpd) 사용자에서도 실행 가능하도록 임시 userDataDir 설정
    const tmpDir = '/tmp/puppeteer_qa_' + process.pid;
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/usr/bin/google-chrome',
        userDataDir: tmpDir,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-software-rasterizer']
    });

    try {
        if (args[0] === '--batch') {
            // 배치 모드: JSON 파일에서 URL 목록 읽기
            const jsonFile = args[1];
            const items = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
            const results = {};

            console.log(`[QA Screenshot] 배치 캡처 시작: ${items.length}개`);
            for (const item of items) {
                results[item.prefix] = await captureUrl(browser, item.url, item.prefix);
            }

            // 결과 JSON 출력
            console.log(JSON.stringify({ success: true, results }));
        } else {
            // 단일 모드
            const url = args[0];
            const prefix = args[1] || 'capture';

            if (!url) {
                console.error('사용법: node qa_screenshot.js <url> <prefix>');
                process.exit(1);
            }

            console.log(`[QA Screenshot] 캡처: ${url} → ${prefix}`);
            const result = await captureUrl(browser, url, prefix);
            console.log(JSON.stringify({ success: true, ...result }));
        }
    } finally {
        await browser.close();
        // 임시 userDataDir 정리
        try { fs.rmSync(tmpDir, {recursive:true, force:true}); } catch(e) {}
    }
}

main().catch(e => {
    console.error(JSON.stringify({ success: false, error: e.message }));
    process.exit(1);
});

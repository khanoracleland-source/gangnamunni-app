/**
 * After 스크린샷 캡처 (Figma 적용 후)
 * 5개 핵심 페이지 PC + 모바일
 */
const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
const OUT_DIR = '/tmp/figma_after';

const pages = [
    { name: 'home',       url: 'index.php' },
    { name: 'hospitals',  url: 'hospitals.php' },
    { name: 'community',  url: 'community.php' },
    { name: 'mypage',     url: 'mypage.php' },
    { name: 'detail',     url: 'detail.php?id=8093' },
];

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/usr/bin/google-chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
    });

    // Mobile viewport
    const mobile = await browser.newPage();
    await mobile.setViewport({ width: 375, height: 812 });
    await mobile.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1');

    for (const p of pages) {
        const url = BASE + p.url;
        try {
            await mobile.goto(url, { waitUntil: 'networkidle2', timeout: 15000 });
            await new Promise(r => setTimeout(r, 1500));
            const fname = `${p.name}_after_mobile.png`;
            await mobile.screenshot({ path: path.join(OUT_DIR, fname), fullPage: true });
            console.log(`OK mobile: ${fname}`);
        } catch(e) {
            console.log(`FAIL mobile ${p.name}: ${e.message}`);
        }
    }

    // PC viewport
    const pc = await browser.newPage();
    await pc.setViewport({ width: 1280, height: 800 });

    for (const p of pages) {
        const url = BASE + p.url;
        try {
            await pc.goto(url, { waitUntil: 'networkidle2', timeout: 15000 });
            await new Promise(r => setTimeout(r, 1500));
            const fname = `${p.name}_after_pc.png`;
            await pc.screenshot({ path: path.join(OUT_DIR, fname), fullPage: true });
            console.log(`OK pc: ${fname}`);
        } catch(e) {
            console.log(`FAIL pc ${p.name}: ${e.message}`);
        }
    }

    await browser.close();
    console.log(`\nDone! Files in ${OUT_DIR}`);
    const files = fs.readdirSync(OUT_DIR);
    files.forEach(f => {
        const stat = fs.statSync(path.join(OUT_DIR, f));
        console.log(`  ${f} (${Math.round(stat.size/1024)}KB)`);
    });
})();

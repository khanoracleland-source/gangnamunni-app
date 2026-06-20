const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
const SCREENSHOT_DIR = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/uploads/fix_log/20260304/';
const LOGIN_URL = BASE_URL + 'dashboard.php';

// Pages to capture
const pages = [
    // Public pages
    {name: 'index', url: 'index.php', type: 'public'},
    {name: 'reviews', url: 'reviews.php', type: 'public'},
    {name: 'hospitals', url: 'hospitals.php', type: 'public'},
    {name: 'events', url: 'events.php', type: 'public'},
    {name: 'consultation', url: 'consultation.php', type: 'public'},
    {name: 'tips_community', url: 'tips.php?type=community', type: 'public'},
    {name: 'columns', url: 'columns.php', type: 'public'},
    // Admin pages (need login)
    {name: 'dashboard', url: 'dashboard.php', type: 'admin'},
    {name: 'admin_reviews', url: 'admin_reviews.php', type: 'admin'},
    {name: 'admin_hospitals', url: 'admin_hospitals.php', type: 'admin'},
    {name: 'admin_doctors', url: 'admin_doctors.php', type: 'admin'},
    {name: 'admin_events', url: 'admin_events.php', type: 'admin'},
    {name: 'admin_tips', url: 'admin_tips.php', type: 'admin'},
    {name: 'admin_members', url: 'admin_members.php', type: 'admin'},
    {name: 'admin_ads', url: 'admin_ads.php', type: 'admin'},
    {name: 'admin_hospital_wizard', url: 'admin_hospital_wizard.php', type: 'admin'},
    {name: 'admin_hospital_images', url: 'admin_hospital_images.php?hospital_id=8094', type: 'admin'},
    {name: 'admin_forgot_password', url: 'admin_forgot_password.php', type: 'admin_noauth'},
    {name: 'admin_notifications', url: 'admin_notifications.php', type: 'admin'},
];

(async () => {
    // Ensure screenshot dir exists
    fs.mkdirSync(SCREENSHOT_DIR, {recursive: true});

    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const context = await browser.newContext({
        viewport: {width: 1920, height: 1080},
        ignoreHTTPSErrors: true
    });

    const results = [];

    // 1. Capture public pages first
    console.log('--- Public Pages ---');
    for (const page of pages.filter(p => p.type === 'public')) {
        const pg = await context.newPage();
        try {
            await pg.goto(BASE_URL + page.url, {waitUntil: 'networkidle', timeout: 30000});
            await pg.waitForTimeout(1000);
            const filepath = SCREENSHOT_DIR + page.name + '_after.jpg';
            await pg.screenshot({path: filepath, type: 'jpeg', quality: 85, fullPage: true});
            console.log(`  OK: ${page.name} -> ${filepath}`);
            results.push({name: page.name, status: 'OK', path: filepath});
        } catch (e) {
            console.log(`  FAIL: ${page.name} -> ${e.message.substring(0, 100)}`);
            results.push({name: page.name, status: 'FAIL', error: e.message.substring(0, 100)});
        }
        await pg.close();
    }

    // 2. Login to admin
    console.log('--- Admin Login ---');
    const loginPage = await context.newPage();
    try {
        await loginPage.goto(LOGIN_URL, {waitUntil: 'networkidle', timeout: 30000});
        await loginPage.fill('input[name="admin_id"], input[name="username"], #admin_id', 'admin');
        await loginPage.fill('input[name="admin_pw"], input[name="password"], #admin_pw', 'admin123');
        await loginPage.click('button[type="submit"], input[type="submit"]');
        await loginPage.waitForTimeout(3000);
        console.log('  Login: ' + loginPage.url());
    } catch (e) {
        console.log('  Login failed: ' + e.message.substring(0, 100));
    }
    await loginPage.close();

    // 3. Capture admin pages
    console.log('--- Admin Pages ---');
    for (const page of pages.filter(p => p.type === 'admin')) {
        const pg = await context.newPage();
        try {
            // Use direct localhost to avoid Cloudflare 403
            const url = 'http://localhost/hairwang/gangnamunni/' + page.url;
            await pg.goto(url, {waitUntil: 'networkidle', timeout: 30000});
            await pg.waitForTimeout(1000);
            const filepath = SCREENSHOT_DIR + page.name + '_after.jpg';
            await pg.screenshot({path: filepath, type: 'jpeg', quality: 85, fullPage: true});
            console.log(`  OK: ${page.name} -> ${filepath}`);
            results.push({name: page.name, status: 'OK', path: filepath});
        } catch (e) {
            console.log(`  FAIL: ${page.name} -> ${e.message.substring(0, 100)}`);
            results.push({name: page.name, status: 'FAIL', error: e.message.substring(0, 100)});
        }
        await pg.close();
    }

    // 4. No-auth admin pages
    for (const page of pages.filter(p => p.type === 'admin_noauth')) {
        const pg = await context.newPage();
        try {
            await pg.goto(BASE_URL + page.url, {waitUntil: 'networkidle', timeout: 30000});
            await pg.waitForTimeout(1000);
            const filepath = SCREENSHOT_DIR + page.name + '_after.jpg';
            await pg.screenshot({path: filepath, type: 'jpeg', quality: 85, fullPage: true});
            console.log(`  OK: ${page.name}`);
            results.push({name: page.name, status: 'OK', path: filepath});
        } catch (e) {
            console.log(`  FAIL: ${page.name}`);
            results.push({name: page.name, status: 'FAIL', error: e.message.substring(0, 100)});
        }
        await pg.close();
    }

    await browser.close();

    // Save results
    fs.writeFileSync(SCREENSHOT_DIR + 'capture_results.json', JSON.stringify(results, null, 2));
    console.log('\n=== CAPTURE COMPLETE: ' + results.filter(r => r.status === 'OK').length + '/' + results.length + ' OK ===');
})();


const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
const SAVE_DIR = path.join(__dirname, '..', 'uploads', 'fix_log', '20260304');

if (!fs.existsSync(SAVE_DIR)) fs.mkdirSync(SAVE_DIR, { recursive: true });

const publicPages = [
    { url: 'index.php', name: 'index' },
    { url: 'reviews.php', name: 'reviews' },
    { url: 'hospitals.php', name: 'hospitals' },
    { url: 'events.php', name: 'events' },
    { url: 'consultation.php', name: 'consultation' },
    { url: 'tips.php?type=community', name: 'tips_community' },
    { url: 'columns.php', name: 'columns' },
];

const adminPages = [
    { url: 'dashboard.php', name: 'dashboard' },
    { url: 'admin_reviews.php', name: 'admin_reviews' },
    { url: 'admin_hospitals.php', name: 'admin_hospitals' },
    { url: 'admin_doctors.php', name: 'admin_doctors' },
    { url: 'admin_events.php', name: 'admin_events' },
    { url: 'admin_tips.php', name: 'admin_tips' },
    { url: 'admin_members.php', name: 'admin_members' },
    { url: 'admin_ads.php', name: 'admin_ads' },
    { url: 'admin_hospital_wizard.php', name: 'admin_hospital_wizard' },
    { url: 'admin_hospital_images.php?hospital_id=8094', name: 'admin_hospital_images' },
    { url: 'admin_forgot_password.php', name: 'admin_forgot_password' },
    { url: 'admin_notifications.php', name: 'admin_notifications' },
    { url: 'admin_ai_settings.php', name: 'admin_ai_settings' },
];

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const results = [];

    // 1. Public pages
    console.log('=== Public Pages ===');
    const pubCtx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const pubPage = await pubCtx.newPage();
    for (const p of publicPages) {
        try {
            await pubPage.goto(BASE_URL + p.url, { waitUntil: 'networkidle', timeout: 30000 });
            await pubPage.waitForTimeout(1500);
            const fname = p.name + '_after.jpg';
            const fpath = path.join(SAVE_DIR, fname);
            await pubPage.screenshot({ path: fpath, fullPage: true, type: 'jpeg', quality: 85 });
            const size = fs.statSync(fpath).size;
            console.log('OK ' + fname + ' (' + size + ')');
            results.push({ page: p.name, type: 'public', file: fname, size });
        } catch (e) {
            console.log('FAIL ' + p.name + ': ' + e.message.substring(0, 80));
            results.push({ page: p.name, type: 'public', file: '', size: 0, status: 'FAIL' });
        }
    }
    await pubCtx.close();

    // 2. Before login capture
    console.log('\n=== Before Login ===');
    const preCtx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const prePage = await preCtx.newPage();
    await prePage.goto(BASE_URL + 'dashboard.php', { waitUntil: 'networkidle', timeout: 30000 });
    await prePage.waitForTimeout(1000);
    const blFname = 'dashboard_before_login.jpg';
    await prePage.screenshot({ path: path.join(SAVE_DIR, blFname), fullPage: true, type: 'jpeg', quality: 85 });
    const blSize = fs.statSync(path.join(SAVE_DIR, blFname)).size;
    console.log('OK ' + blFname + ' (' + blSize + ')');
    results.push({ page: 'dashboard_before_login', type: 'before_login', file: blFname, size: blSize });
    await preCtx.close();

    // 3. Admin pages (login first)
    console.log('\n=== Admin Pages (logged in) ===');
    const adminCtx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const adminPage = await adminCtx.newPage();

    // Login
    await adminPage.goto(BASE_URL + 'dashboard.php', { waitUntil: 'networkidle', timeout: 30000 });
    await adminPage.waitForTimeout(1000);
    const inputs = await adminPage.$$('input');
    let userField = null, passField = null;
    for (const inp of inputs) {
        const type = await inp.getAttribute('type');
        const name = await inp.getAttribute('name');
        if (name && (name.includes('user') || name.includes('id')) && type !== 'hidden') userField = inp;
        if (type === 'password') passField = inp;
    }
    if (userField && passField) {
        await userField.fill('admin');
        await passField.fill('admin123');
        const btn = await adminPage.$('button[type="submit"], input[type="submit"]');
        if (btn) await btn.click();
        else await adminPage.keyboard.press('Enter');
        await adminPage.waitForTimeout(3000);
        await adminPage.waitForLoadState('networkidle').catch(() => {});
        console.log('Login done. URL: ' + adminPage.url());
    } else {
        console.log('Login form not found');
    }

    // Capture all admin pages
    for (const p of adminPages) {
        try {
            if (p.name !== 'dashboard') {
                await adminPage.goto(BASE_URL + p.url, { waitUntil: 'networkidle', timeout: 30000 });
            }
            await adminPage.waitForTimeout(2000);
            const fname = p.name + '_after.jpg';
            const fpath = path.join(SAVE_DIR, fname);
            await adminPage.screenshot({ path: fpath, fullPage: true, type: 'jpeg', quality: 85 });
            const size = fs.statSync(fpath).size;
            console.log('OK ' + fname + ' (' + size + ')');
            results.push({ page: p.name, type: 'admin', file: fname, size });
        } catch (e) {
            console.log('FAIL ' + p.name + ': ' + e.message.substring(0, 80));
            results.push({ page: p.name, type: 'admin', file: '', size: 0, status: 'FAIL' });
        }
    }
    await adminCtx.close();
    await browser.close();

    fs.writeFileSync(path.join(SAVE_DIR, 'capture_results.json'), JSON.stringify(results, null, 2));
    console.log('\nTotal: ' + results.length + ' captures');
    console.log('DONE');
})();

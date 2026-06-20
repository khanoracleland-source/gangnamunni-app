
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    const inputs = await p.$$('input[type="text"], input[type="password"]');
    if (inputs.length >= 2) {
        await inputs[0].fill('admin');
        await inputs[1].fill('admin123');
        const btn = await p.$('button[type="submit"]');
        if (btn) await btn.click();
        await p.waitForTimeout(2000);
    }
    
    const totalGroups = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse').length);
    const initialOpen = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse.show').length);
    console.log('Initial: ' + initialOpen + '/' + totalGroups + ' open');
    
    const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
    const tests = ['admin_ad_pricing.php', 'admin_hospitals.php', 'admin_send_notification.php', 'admin_bookings.php', 'admin_treatments.php'];
    
    for (const page of tests) {
        await p.goto(BASE + page, {waitUntil:'domcontentloaded', timeout:10000});
        await p.waitForTimeout(500);
        const open = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse.show').length);
        console.log(page.padEnd(35) + ' open=' + open + '/' + totalGroups + (open < initialOpen - 1 ? ' PROBLEM' : ' OK'));
    }
    
    await b.close();
})();

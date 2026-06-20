
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
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=2026-03-06', {waitUntil:'networkidle'});
    await p.waitForTimeout(1500);
    const activeTab = await p.evaluate(() => {
        const active = document.querySelector('.tab-pane.show.active');
        return active ? active.id : 'NONE';
    });
    const highlighted = await p.evaluate(() => document.querySelectorAll('[data-date="2026-03-06"]').length);
    console.log('Active tab:', activeTab);
    console.log('Highlighted:', highlighted);
    console.log(activeTab === 'tab-timeline' ? 'OK' : 'FAIL');
    await b.close();
})();

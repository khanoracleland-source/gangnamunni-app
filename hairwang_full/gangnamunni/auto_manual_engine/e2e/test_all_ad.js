
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
    
    const pages = ['admin_ad_pricing.php', 'admin_ad_stats.php', 'admin_advertisers.php', 'admin_ad_settings.php', 'admin_promos.php'];
    for (const page of pages) {
        await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/' + page, {waitUntil:'networkidle'});
        await p.waitForTimeout(500);
        const open = await p.evaluate(() => {
            const groups = [];
            document.querySelectorAll('.admin-sidebar .collapse.show').forEach(el => groups.push(el.id));
            const active = document.querySelector('.admin-sidebar .nav-link.active');
            return {groups, active: active ? active.textContent.trim() : 'NONE'};
        });
        console.log(page + ': open=' + open.groups.join(',') + ' active=' + open.active);
    }
    
    await b.close();
})();


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
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_ad_pricing.php', {waitUntil:'networkidle'});
    await p.waitForTimeout(1000);
    
    const groups = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar .collapse').forEach(el => {
            result.push({
                id: el.id,
                isShow: el.classList.contains('show'),
                childCount: el.querySelectorAll('.nav-link').length
            });
        });
        return result;
    });
    
    const active = await p.evaluate(() => {
        const a = document.querySelector('.admin-sidebar .nav-link.active');
        return a ? a.textContent.trim() : 'NONE';
    });
    
    console.log('Active menu:', active);
    groups.forEach(g => {
        if (g.isShow) console.log('OPEN:', g.id, '(' + g.childCount + ' items)');
    });
    console.log('Total groups:', groups.length, 'Open:', groups.filter(g => g.isShow).length);
    
    await b.close();
})();

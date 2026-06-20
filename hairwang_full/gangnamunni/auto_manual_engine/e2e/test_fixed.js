
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    const errors = [];
    p.on('pageerror', err => errors.push(err.message));
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    const inputs = await p.$$('input[type="text"], input[type="password"]');
    if (inputs.length >= 2) {
        await inputs[0].fill('admin');
        await inputs[1].fill('admin123');
        const btn = await p.$('button[type="submit"]');
        if (btn) await btn.click();
        await p.waitForTimeout(2000);
    }
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=2026-02-27', {waitUntil:'networkidle'});
    await p.waitForTimeout(3000);
    
    const result = await p.evaluate(() => {
        const tl = document.getElementById('timelineList');
        return {
            content: tl ? tl.textContent.trim().substring(0, 100) : 'NOT FOUND',
            isLoading: tl ? tl.textContent.includes('loading') || tl.textContent.includes('...') : true,
            activeTab: document.querySelector('.tab-pane.show.active')?.id || 'none'
        };
    });
    console.log(JSON.stringify(result));
    console.log('JS errors: ' + errors.length);
    errors.forEach(e => console.log('  ' + e.substring(0, 100)));
    
    await b.close();
})();

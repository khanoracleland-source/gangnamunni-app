
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    
    const errors = [];
    p.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
    p.on('pageerror', err => errors.push('PAGE_ERROR: ' + err.message));
    
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
    await p.waitForTimeout(5000);
    
    console.log('JS Errors: ' + errors.length);
    errors.forEach(e => console.log('  ' + e.substring(0, 150)));
    
    // loadTimeline 호출 시도
    const result = await p.evaluate(async () => {
        try {
            const resp = await fetch('admin_project_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax_action=timeline_list'
            });
            const text = await resp.text();
            return {status: resp.status, length: text.length, start: text.substring(0, 100)};
        } catch(e) {
            return {error: e.message};
        }
    });
    console.log('Manual fetch: ' + JSON.stringify(result));
    
    await b.close();
})();

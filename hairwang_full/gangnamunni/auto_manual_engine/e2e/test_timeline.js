
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
    
    const dates = ['2026-03-06','2026-03-05','2026-03-04','2026-02-27','2026-02-26','2026-02-25','2026-02-24','2026-02-23','2026-02-11','2026-02-09'];
    
    for (const d of dates) {
        const resp = await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=' + d, {waitUntil:'domcontentloaded', timeout:15000});
        const status = resp.status();
        const body = await p.content();
        const hasError = body.includes('Fatal error') || body.includes('Uncaught Error') || body.includes('존재하지 않') || body.includes('not found');
        const hasContent = body.includes(d) || body.includes('타임라인') || body.includes('작업');
        console.log(d + ': HTTP ' + status + (hasError ? ' ERROR' : '') + (hasContent ? ' OK' : ' EMPTY'));
    }
    
    await b.close();
})();

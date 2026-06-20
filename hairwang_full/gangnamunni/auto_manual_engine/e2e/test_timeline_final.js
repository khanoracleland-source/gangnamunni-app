
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
    
    // date=2026-03-06 접근
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=2026-03-06', {waitUntil:'networkidle'});
    await p.waitForTimeout(1000);
    
    // timeline 탭이 활성화되었는지
    const activeTab = await p.evaluate(() => {
        const active = document.querySelector('.tab-pane.show.active');
        return active ? active.id : 'NONE';
    });
    console.log('Active tab:', activeTab);
    
    // 하이라이트된 항목
    const highlighted = await p.evaluate(() => {
        const items = document.querySelectorAll('[data-date="2026-03-06"]');
        return items.length;
    });
    console.log('Highlighted items for 2026-03-06:', highlighted);
    
    console.log(activeTab === 'tab-timeline' ? 'OK: Timeline tab active' : 'FAIL: Wrong tab');
    await b.close();
})();

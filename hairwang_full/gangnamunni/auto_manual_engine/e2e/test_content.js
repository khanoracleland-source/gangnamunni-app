
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
    
    // 쿠키 삭제
    await ctx.clearCookies();
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    // 다시 로그인
    const inputs2 = await p.$$('input[type="text"], input[type="password"]');
    if (inputs2.length >= 2) {
        await inputs2[0].fill('admin');
        await inputs2[1].fill('admin123');
        const btn2 = await p.$('button[type="submit"]');
        if (btn2) await btn2.click();
        await p.waitForTimeout(2000);
    }
    
    // 각 그룹 상태
    const groups = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar .collapse').forEach(el => {
            const toggle = document.querySelector('[href="#' + el.id + '"]');
            const label = toggle ? toggle.textContent.trim() : '?';
            result.push({
                id: el.id,
                label: label.substring(0, 20),
                show: el.classList.contains('show'),
                childCount: el.querySelectorAll('.nav-link').length
            });
        });
        return result;
    });
    
    groups.forEach(g => {
        console.log((g.show ? 'OPEN' : 'CLOSED') + ': ' + g.id + ' (' + g.label + ') [' + g.childCount + ']');
    });
    
    await b.close();
})();

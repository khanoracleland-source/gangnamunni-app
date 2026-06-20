
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
    
    const links = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar a.nav-link[href]').forEach(a => {
            const href = a.getAttribute('href');
            const target = a.getAttribute('target');
            if (href && !href.startsWith('#') && !href.includes('download') && target !== '_blank') {
                // active 상태 확인용
                result.push(href);
            }
        });
        return [...new Set(result)];
    });
    
    const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
    for (const href of links) {
        try {
            const url = href.startsWith('http') ? href : BASE + href;
            await p.goto(url, {waitUntil:'domcontentloaded', timeout:8000});
            const active = await p.evaluate(() => {
                const a = document.querySelector('.admin-sidebar .nav-link.active');
                return a ? a.textContent.trim().substring(0, 25) : 'NONE';
            });
            if (active === 'NONE') {
                console.log('NO_ACTIVE: ' + href);
            }
        } catch(e) {}
    }
    
    await b.close();
})();

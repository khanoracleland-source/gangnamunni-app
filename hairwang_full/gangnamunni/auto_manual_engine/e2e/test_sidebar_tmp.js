
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
    const html = await p.evaluate(() => {
        const sidebar = document.querySelector('.admin-sidebar');
        if (!sidebar) return 'NO SIDEBAR';
        const links = sidebar.querySelectorAll('.nav-link');
        let result = [];
        links.forEach(l => {
            const text = l.textContent.trim();
            if (text.includes('Project') || text.includes('프로젝트') || text.includes('수정 이력')) {
                result.push({text, href: l.getAttribute('href'), expanded: l.getAttribute('aria-expanded'), visible: l.offsetParent !== null});
            }
        });
        return JSON.stringify(result, null, 2);
    });
    console.log(html);
    await b.close();
})();

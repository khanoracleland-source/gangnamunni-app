
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
    
    // Project Status 클릭
    const link = await p.$('a[href="#grp_project_status_group"]');
    if (link) {
        console.log('Before click - expanded:', await link.getAttribute('aria-expanded'));
        await link.click();
        await p.waitForTimeout(1000);
        console.log('After click - expanded:', await link.getAttribute('aria-expanded'));
        
        // collapse 요소 확인
        const collapse = await p.$('#grp_project_status_group');
        if (collapse) {
            const classes = await collapse.getAttribute('class');
            console.log('Collapse classes:', classes);
            const children = await collapse.$$('.nav-link');
            console.log('Children count:', children.length);
            for (const ch of children) {
                const text = await ch.textContent();
                const vis = await ch.isVisible();
                console.log('  Child:', text.trim(), 'visible:', vis);
            }
        } else {
            console.log('Collapse element NOT FOUND');
        }
    } else {
        console.log('Project Status link NOT FOUND');
    }
    
    await b.close();
})();

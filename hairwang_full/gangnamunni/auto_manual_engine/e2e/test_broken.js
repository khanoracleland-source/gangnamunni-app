
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
    
    // admin_ad_stats.php 로드
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_ad_stats.php', {waitUntil:'networkidle'});
    
    const result = await p.evaluate(() => {
        const sidebar = document.querySelector('.admin-sidebar');
        const main = document.querySelector('.admin-main');
        const body = document.body;
        const html = document.documentElement;
        return {
            hasSidebar: !!sidebar,
            sidebarWidth: sidebar ? sidebar.offsetWidth : 0,
            mainMargin: main ? getComputedStyle(main).marginLeft : 'none',
            bodyClasses: body.className,
            htmlTheme: html.getAttribute('data-bs-theme'),
            title: document.title,
            hasBootstrap: !!document.querySelector('link[href*="bootstrap"]'),
            duplicateHtml: (document.querySelectorAll('html').length > 1) || document.querySelectorAll('head').length,
            allHtmlTags: document.querySelectorAll('html').length,
            allHeadTags: document.querySelectorAll('head').length,
            allBodyTags: document.querySelectorAll('body').length,
        };
    });
    console.log(JSON.stringify(result, null, 2));
    await b.close();
})();

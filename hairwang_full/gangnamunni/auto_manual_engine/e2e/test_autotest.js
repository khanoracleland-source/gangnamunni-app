
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
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_auto_test.php', {waitUntil:'networkidle'});
    await p.waitForTimeout(1000);
    
    const result = await p.evaluate(() => {
        const sidebar = document.querySelector('.admin-sidebar');
        const main = document.querySelector('.admin-main');
        return {
            hasSidebar: !!sidebar,
            sidebarVisible: sidebar ? sidebar.offsetWidth > 0 : false,
            sidebarWidth: sidebar ? sidebar.offsetWidth : 0,
            mainMarginLeft: main ? getComputedStyle(main).marginLeft : 'none',
            bodyClasses: document.body.className,
            htmlCount: document.querySelectorAll('html').length,
            headCount: document.querySelectorAll('head').length,
            bodyCount: document.querySelectorAll('body').length
        };
    });
    console.log(JSON.stringify(result, null, 2));
    
    await b.close();
})();

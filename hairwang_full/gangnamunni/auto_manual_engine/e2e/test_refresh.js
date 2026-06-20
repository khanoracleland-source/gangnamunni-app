
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    
    let navCount = 0;
    p.on('framenavigated', frame => { if (frame === p.mainFrame()) navCount++; });
    
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    const inputs = await p.$$('input[type="text"], input[type="password"]');
    if (inputs.length >= 2) {
        await inputs[0].fill('admin');
        await inputs[1].fill('admin123');
        const btn = await p.$('button[type="submit"]');
        if (btn) await btn.click();
        await p.waitForTimeout(2000);
    }
    
    navCount = 0;
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_doctors.php', {waitUntil:'networkidle'});
    await p.waitForTimeout(3000);
    
    // 네비게이션 횟수 (1이면 정상, 2이상이면 리다이렉트/리로드)
    console.log('Navigation count: ' + navCount);
    console.log('Final URL: ' + p.url());
    
    // 사이드바 변화 감지
    const sidebar = await p.evaluate(() => {
        const s = document.querySelector('.admin-sidebar');
        return s ? {width: s.offsetWidth, links: s.querySelectorAll('.nav-link').length} : null;
    });
    console.log('Sidebar: ' + JSON.stringify(sidebar));
    
    await b.close();
})();

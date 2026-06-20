
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
    
    // 03/06 클릭
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=2026-03-06', {waitUntil:'networkidle', timeout:15000});
    
    // 오른쪽 메인 영역에 뭐가 나오는지
    const mainContent = await p.evaluate(() => {
        const main = document.querySelector('.admin-main');
        if (!main) return 'NO .admin-main';
        const text = main.textContent.trim().substring(0, 500);
        const children = main.children.length;
        const height = main.offsetHeight;
        return {text: text.substring(0,300), children, height, innerHTML_length: main.innerHTML.length};
    });
    console.log('Main content:', JSON.stringify(mainContent, null, 2));
    
    // 스크린샷
    await p.screenshot({path:'/tmp/timeline_test.png', fullPage:false});
    
    await b.close();
})();

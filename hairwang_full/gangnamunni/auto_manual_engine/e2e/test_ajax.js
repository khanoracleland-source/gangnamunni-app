
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    
    // 콘솔 로그 캡처
    p.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));
    p.on('response', resp => {
        if (resp.url().includes('admin_project_status') && resp.request().method() === 'POST') {
            console.log('AJAX Response:', resp.status(), resp.url());
        }
    });
    
    // 로그인
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    const inputs = await p.$$('input[type="text"], input[type="password"]');
    if (inputs.length >= 2) {
        await inputs[0].fill('admin');
        await inputs[1].fill('admin123');
        const btn = await p.$('button[type="submit"]');
        if (btn) await btn.click();
        await p.waitForTimeout(2000);
    }
    
    // timeline 페이지
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_project_status.php?date=2026-03-06', {waitUntil:'networkidle'});
    await p.waitForTimeout(3000);
    
    // timelineList 내용 확인
    const content = await p.evaluate(() => {
        const el = document.getElementById('timelineList');
        return el ? el.innerHTML.substring(0, 300) : 'NOT FOUND';
    });
    console.log('timelineList:', content);
    
    // recentTimeline 내용
    const recent = await p.evaluate(() => {
        const el = document.getElementById('recentTimeline');
        return el ? el.innerHTML.substring(0, 200) : 'NOT FOUND';
    });
    console.log('recentTimeline:', recent);
    
    await b.close();
})();

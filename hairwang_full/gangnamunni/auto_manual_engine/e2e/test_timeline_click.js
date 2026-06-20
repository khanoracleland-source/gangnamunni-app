
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
    
    // Project Status 그룹 열기
    const toggle = await p.$('a[href="#grp_project_status_group"]');
    if (toggle) {
        await toggle.click();
        await p.waitForTimeout(500);
    }
    
    // 그룹 안의 자식 링크 전부 확인
    const children = await p.$$('#grp_project_status_group .nav-link');
    console.log('Children count:', children.length);
    
    for (const child of children) {
        const text = await child.textContent();
        const href = await child.getAttribute('href');
        const rect = await child.boundingBox();
        const visible = await child.isVisible();
        
        // elementFromPoint로 클릭 가능한지
        const clickable = await p.evaluate((el) => {
            const r = el.getBoundingClientRect();
            const at = document.elementFromPoint(r.x + r.width/2, r.y + r.height/2);
            return {
                clickable: at === el || el.contains(at),
                elementAtPoint: at ? at.tagName + '.' + at.className.substring(0,30) : 'null',
                pointerEvents: getComputedStyle(el).pointerEvents,
                overflow: getComputedStyle(el.parentElement).overflow
            };
        }, child);
        
        console.log(text.trim().substring(0,25).padEnd(25), 'visible:', visible, 'clickable:', clickable.clickable, 'pe:', clickable.pointerEvents, 'at:', clickable.elementAtPoint);
    }
    
    await b.close();
})();

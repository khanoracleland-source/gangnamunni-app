
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
    
    const result = await p.evaluate(() => {
        const link = document.querySelector('a[href="#grp_project_status_group"]');
        if (!link) return 'NOT FOUND';
        const rect = link.getBoundingClientRect();
        const style = getComputedStyle(link);
        // 위에 겹치는 요소 확인
        const el = document.elementFromPoint(rect.x + rect.width/2, rect.y + rect.height/2);
        return {
            rect: {x:rect.x, y:rect.y, w:rect.width, h:rect.height},
            pointerEvents: style.pointerEvents,
            zIndex: style.zIndex,
            overflow: style.overflow,
            elementAtPoint: el ? el.tagName + '.' + el.className.substring(0,50) : 'null',
            isSame: el === link
        };
    });
    console.log(JSON.stringify(result, null, 2));
    
    await b.close();
})();

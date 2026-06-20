
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
    
    // Full Test Report 그룹 찾기
    const result = await p.evaluate(() => {
        const links = document.querySelectorAll('.admin-sidebar .nav-link');
        let found = [];
        links.forEach(l => {
            if (l.textContent.includes('Full Test') || l.textContent.includes('매뉴얼') || l.textContent.includes('운영 가이드')) {
                found.push({text: l.textContent.trim(), visible: l.offsetParent !== null, href: l.getAttribute('href')});
            }
        });
        // 전체 그룹 수
        const groups = document.querySelectorAll('.admin-sidebar .collapse');
        return {found, totalGroups: groups.length, lastGroup: groups[groups.length-1]?.id};
    });
    console.log(JSON.stringify(result, null, 2));
    await b.close();
})();

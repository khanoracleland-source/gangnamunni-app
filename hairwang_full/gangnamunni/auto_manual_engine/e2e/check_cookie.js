
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
    
    // 1. 쿠키 확인
    const cookies = await ctx.cookies();
    const sb = cookies.find(c => c.name === 'sb_open_groups');
    const ver = cookies.find(c => c.name === 'sb_ver');
    console.log('Cookie sb_open_groups:', sb ? sb.value.substring(0,80) + '...' : 'NONE');
    console.log('Cookie sb_ver:', ver ? ver.value : 'NONE');
    
    // 2. 광고 단가 클릭
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_ad_pricing.php', {waitUntil:'networkidle'});
    await p.waitForTimeout(500);
    
    const cookies2 = await ctx.cookies();
    const sb2 = cookies2.find(c => c.name === 'sb_open_groups');
    console.log('After nav sb_open_groups:', sb2 ? sb2.value.substring(0,80) + '...' : 'NONE');
    
    const openGroups = await p.evaluate(() => {
        return Array.from(document.querySelectorAll('.admin-sidebar .collapse.show')).map(el => el.id);
    });
    console.log('Open groups:', openGroups.length, openGroups.join(',').substring(0,80));
    
    await b.close();
})();

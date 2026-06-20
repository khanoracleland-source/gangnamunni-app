
const {chromium} = require('playwright');
(async()=>{
    const b = await chromium.launch({headless:true, args:['--no-sandbox']});
    const ctx = await b.newContext({viewport:{width:1400,height:900}});
    const p = await ctx.newPage();
    
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
    
    // 1단계: 광고관리 + 콘텐츠관리 그룹 열기
    const toggles = await p.$$('.admin-sidebar [data-bs-toggle="collapse"]');
    for (const t of toggles) {
        const href = await t.getAttribute('href');
        if (href && (href.includes('content') || href.includes('ad_management'))) {
            const target = await p.$('#' + href.replace('#',''));
            if (target && !(await target.evaluate(el => el.classList.contains('show')))) {
                await t.click();
                await p.waitForTimeout(500);
            }
        }
    }
    
    // 쿠키 확인
    const cookies1 = await ctx.cookies();
    const sb = cookies1.find(c => c.name === 'sb_open_groups');
    console.log('Step1 Cookie:', sb ? sb.value : 'NONE');
    
    // 2단계: 대시보드 클릭
    await p.click('a[href="dashboard.php"]');
    await p.waitForTimeout(2000);
    
    // 3단계: 그룹 상태 확인
    const groups = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar .collapse').forEach(el => {
            if (el.classList.contains('show')) result.push(el.id);
        });
        return result;
    });
    console.log('Step3 Open groups:', groups.join(', '));
    console.log('Content open:', groups.includes('grp_content'));
    console.log('Ad open:', groups.includes('grp_ad_management'));
    
    // 쿠키 확인
    const cookies2 = await ctx.cookies();
    const sb2 = cookies2.find(c => c.name === 'sb_open_groups');
    console.log('Step3 Cookie:', sb2 ? sb2.value : 'NONE');
    
    await b.close();
})();


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
    
    // 쿠키 삭제 → 모든 그룹 열림으로 시작
    await ctx.clearCookies();
    await p.goto('https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', {waitUntil:'networkidle'});
    const inputs2 = await p.$$('input[type="text"], input[type="password"]');
    if (inputs2.length >= 2) {
        await inputs2[0].fill('admin');
        await inputs2[1].fill('admin123');
        const btn2 = await p.$('button[type="submit"]');
        if (btn2) await btn2.click();
        await p.waitForTimeout(2000);
    }
    
    // 모든 그룹 수 확인
    const totalGroups = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse').length);
    const initialOpen = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse.show').length);
    console.log('Total groups: ' + totalGroups + ', Initial open: ' + initialOpen);
    
    // 모든 서브 메뉴 URL 수집
    const links = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar a.nav-link[href]').forEach(a => {
            const href = a.getAttribute('href');
            const target = a.getAttribute('target');
            if (href && !href.startsWith('#') && !href.startsWith('javascript') && !href.includes('download') && target !== '_blank') {
                result.push({href, text: a.textContent.trim().substring(0, 25)});
            }
        });
        return result;
    });
    
    console.log('Testing ' + links.length + ' menu links...\n');
    
    let problems = [];
    const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
    
    for (const link of links) {
        try {
            const url = link.href.startsWith('http') ? link.href : BASE + link.href;
            
            // 페이지 이동 전 쿠키에 모든 그룹 저장
            await p.evaluate(() => {
                const open = [];
                document.querySelectorAll('.admin-sidebar .collapse.show').forEach(el => { if (el.id) open.push(el.id); });
                document.cookie = 'sb_open_groups=' + open.join(',') + ';path=/;max-age=86400';
                document.cookie = 'sb_ver=v5;path=/;max-age=86400';
            });
            
            await p.goto(url, {waitUntil:'domcontentloaded', timeout:10000});
            await p.waitForTimeout(300);
            
            const openCount = await p.evaluate(() => document.querySelectorAll('.admin-sidebar .collapse.show').length);
            
            if (openCount < initialOpen - 1) {
                console.log('PROBLEM: ' + link.href.padEnd(40) + ' open=' + openCount + '/' + totalGroups + ' (' + link.text + ')');
                problems.push(link.href);
            }
        } catch(e) {
            // skip timeout errors
        }
    }
    
    console.log('\n=== RESULT ===');
    console.log('Total tested: ' + links.length);
    console.log('Problems: ' + problems.length);
    problems.forEach(p => console.log('  - ' + p));
    
    await b.close();
})();

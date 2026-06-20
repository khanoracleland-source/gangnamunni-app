
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
    
    // 모든 collapse 열기
    const toggles = await p.$$('.admin-sidebar [data-bs-toggle="collapse"]');
    for (const t of toggles) {
        await t.click();
        await p.waitForTimeout(200);
    }
    await p.waitForTimeout(500);
    
    // 모든 사이드바 링크 수집
    const links = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar a.nav-link[href]').forEach(a => {
            const href = a.getAttribute('href');
            if (href && !href.startsWith('#') && !href.startsWith('javascript') && href !== '') {
                result.push({href, text: a.textContent.trim().substring(0, 30)});
            }
        });
        return result;
    });
    
    console.log('Total menu links: ' + links.length);
    
    // 각 링크 방문 + 에러 체크
    let pass = 0, fail = 0;
    const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
    
    for (const link of links) {
        try {
            const url = link.href.startsWith('http') ? link.href : BASE + link.href;
            const resp = await p.goto(url, {waitUntil:'domcontentloaded', timeout:15000});
            const status = resp.status();
            
            // 500 에러 체크
            if (status >= 500) {
                console.log('FAIL [' + status + '] ' + link.href + ' — ' + link.text);
                fail++;
            } else {
                // PHP Fatal Error 체크
                const body = await p.content();
                if (body.includes('Fatal error') || body.includes('Uncaught Error')) {
                    console.log('FAIL [PHP] ' + link.href + ' — ' + link.text);
                    fail++;
                } else {
                    pass++;
                }
            }
        } catch(e) {
            console.log('FAIL [TIMEOUT] ' + link.href + ' — ' + link.text + ' — ' + e.message.substring(0,50));
            fail++;
        }
    }
    
    console.log('\nResult: ' + pass + ' PASS, ' + fail + ' FAIL (total ' + links.length + ')');
    await b.close();
})();

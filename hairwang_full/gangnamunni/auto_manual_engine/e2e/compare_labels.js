
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
    
    // 모든 서브 메뉴 수집
    const links = await p.evaluate(() => {
        const result = [];
        document.querySelectorAll('.admin-sidebar a.nav-link[href]').forEach(a => {
            const href = a.getAttribute('href');
            const target = a.getAttribute('target');
            if (href && !href.startsWith('#') && !href.startsWith('javascript') && !href.includes('download') && target !== '_blank') {
                result.push({href, sidebarLabel: a.textContent.trim()});
            }
        });
        return result;
    });
    
    const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
    let mismatches = [];
    
    for (const link of links) {
        try {
            const url = link.href.startsWith('http') ? link.href : BASE + link.href;
            await p.goto(url, {waitUntil:'domcontentloaded', timeout:8000});
            
            const pageTitle = await p.evaluate(() => {
                const h = document.querySelector('.admin-main h4, .admin-main h5, .page-desc-banner h5');
                return h ? h.textContent.trim().replace(/[\s]+/g, ' ') : document.title.split('|')[0].trim();
            });
            
            if (link.sidebarLabel !== pageTitle && pageTitle.length > 0) {
                console.log('DIFF: sidebar="' + link.sidebarLabel + '" page="' + pageTitle + '" (' + link.href + ')');
                mismatches.push({href: link.href, sidebar: link.sidebarLabel, page: pageTitle});
            }
        } catch(e) {}
    }
    
    console.log('\nTotal: ' + links.length + ', Mismatches: ' + mismatches.length);
    await b.close();
})();

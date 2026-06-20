/**
 * 로그인 헬퍼 — 관리자/병원관리자 로그인
 */
const config = require('../e2e-config.json');

async function adminLogin(page, accountType = 'admin') {
    const account = config.accounts[accountType] || config.accounts.admin;
    const url = config.baseUrl + '/dashboard.php';
    await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
    await page.waitForTimeout(500);

    // 이미 로그인된 상태면 스킵
    if (page.url().includes('dashboard') && !(await page.$('input[type="password"]'))) {
        return true;
    }

    const inputs = await page.$$('input[type="text"], input[type="password"]');
    if (inputs.length >= 2) {
        await inputs[0].fill(account.username);
        await inputs[1].fill(account.password);
        const btn = await page.$('button[type="submit"], .btn-primary');
        if (btn) await btn.click();
        await page.waitForTimeout(2000);
    }

    return page.url().includes('dashboard');
}

module.exports = { adminLogin };

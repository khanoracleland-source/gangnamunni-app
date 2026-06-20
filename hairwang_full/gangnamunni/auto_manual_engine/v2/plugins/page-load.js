/**
 * 플러그인: page-load
 * 기본 페이지 접근 테스트 (v1 호환)
 * - 페이지 이동 + 스크린샷 + 에러 감지 + 요소 분석
 */

const path = require('path');

class PageLoadPlugin {
    constructor(page, testConfig, context) {
        this.page = page;
        this.config = testConfig;
        this.ctx = context;
    }

    async run() {
        const { page, config, ctx } = this;
        const url = config.url.startsWith('http') ? config.url : ctx.baseUrl + config.url;
        const fullPage = ctx.options.fullPage !== false;
        const delay = (ctx.options.delay || 3) * 1000;

        // 1) 페이지 이동
        let httpStatus = 0;
        page.once('response', (resp) => {
            if (resp.url() === url || resp.url() === page.url()) {
                httpStatus = resp.status();
            }
        });

        try {
            const response = await page.goto(url, {
                waitUntil: 'networkidle',
                timeout: 30000
            });
            if (response) httpStatus = response.status();
        } catch (e) {
            return {
                status: 'FAIL',
                url,
                errors: { http: [{ status: 0, message: e.message }] },
                analysis: {}
            };
        }

        // 2) 딜레이 (렌더링 대기)
        await new Promise(r => setTimeout(r, Math.min(delay, 3000)));

        // 3) 스크린샷
        const ssName = `${config.id}.png`;
        const ssPath = path.join(ctx.screenshotsDir, ssName);
        try {
            await page.screenshot({ path: ssPath, fullPage });
        } catch (e) {}

        // 4) 에러 감지
        const errors = await ctx.detectErrors(page);

        // HTTP 상태 에러
        if (httpStatus >= 400) {
            errors.http = errors.http || [];
            errors.http.push({ status: httpStatus, url });
        }

        // 5) 페이지 분석
        const analysis = await ctx.analyzePage(page);

        // 6) 상태 결정
        let status = 'PASS';
        if (httpStatus >= 500 || errors.php?.length > 0) status = 'FAIL';
        else if (httpStatus >= 400 || errors.js?.length > 0) status = 'WARN';

        return {
            status,
            url: page.url(),
            screenshot: ssName,
            analysis,
            errors
        };
    }
}

module.exports = PageLoadPlugin;

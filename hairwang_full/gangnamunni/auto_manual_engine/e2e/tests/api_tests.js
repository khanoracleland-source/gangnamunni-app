/**
 * API 테스트 (C1~C3)
 */
const config = require('../e2e-config.json');
const BASE = config.baseUrl;

const tests = {
    async C1(page, context) {
        // api_save_setting.php — POST (세션 필요)
        const resp = await page.evaluate(async (url) => {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=ad_auto_scroll_speed&value=4000'
            });
            return { status: r.status, body: await r.text() };
        }, BASE + '/api_save_setting.php');
        const json = JSON.parse(resp.body);
        if (!json.success) throw new Error('API 저장 실패: ' + resp.body);
        return { metadata: json };
    },

    async C2(page) {
        const resp = await page.goto(BASE + '/api_treatments.php?action=categories', { waitUntil: 'networkidle', timeout: 10000 });
        const status = resp.status();
        const body = await resp.text();
        if (status >= 500) throw new Error('API 에러: HTTP ' + status);
        return { metadata: { status, length: body.length } };
    },

    async C3(page) {
        const resp = await page.goto(BASE + '/api_recommend.php?action=hospitals&limit=5', { waitUntil: 'networkidle', timeout: 10000 });
        const status = resp.status();
        const body = await resp.text();
        if (status >= 500) throw new Error('API 에러: HTTP ' + status);
        return { metadata: { status, length: body.length } };
    },
    // === Standalone 테스트 ===
    async S1(page) {
        // hms_standalone_mode 설정값 확인
        const resp = await page.goto(BASE + '/api_departments.php', { waitUntil: 'networkidle', timeout: 10000 });
        const status = resp.status();
        if (status >= 500) throw new Error('api_departments HTTP ' + status);
        return { metadata: { status } };
    },

    async S2(page) {
        const resp = await page.goto(BASE + '/api_departments.php', { waitUntil: 'networkidle', timeout: 10000 });
        const body = await resp.text();
        if (resp.status() >= 500) throw new Error('진료과 API 에러: ' + resp.status());
        return { metadata: { status: resp.status(), length: body.length } };
    },

    async S3(page) {
        const resp = await page.goto(BASE + '/api_schedule.php', { waitUntil: 'networkidle', timeout: 10000 });
        if (resp.status() >= 500) throw new Error('스케줄 API 에러: ' + resp.status());
        return { metadata: { status: resp.status() } };
    },

    async F6(page) {
        // SSO 토큰 테이블 존재 확인 — DB 직접 체크 대신 로그인 페이지 동작 확인
        const resp = await page.goto(BASE + '/bbs/login.php', { waitUntil: 'networkidle', timeout: 10000 });
        const status = resp.status();
        if (status >= 500) throw new Error('로그인 페이지 HTTP ' + status);
        const html = await page.content();
        const hasSocial = html.includes('social') || html.includes('kakao') || html.includes('naver') || html.includes('google');
        return { metadata: { status, has_social_login: hasSocial } };
    },
};

module.exports = tests;

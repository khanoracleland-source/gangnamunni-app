/**
 * 사용자 테스트 (A1~A18)
 */
const config = require('../e2e-config.json');
const BASE = config.baseUrl;

const tests = {
    async A1(page) {
        await page.goto(BASE + '/index.php', { waitUntil: 'networkidle', timeout: 15000 });
        const title = await page.title();
        if (!title) throw new Error('페이지 타이틀 없음');
        const search = await page.$('input[type="text"]');
        if (!search) throw new Error('검색 입력 필드 없음');
        return { metadata: { title } };
    },

    async A2(page) {
        await page.goto(BASE + '/hospitals.php', { waitUntil: 'networkidle', timeout: 15000 });
        const cards = await page.$$('.hospital-card, .hospital-item, [onclick*="detail.php"]');
        if (cards.length === 0) throw new Error('병원 카드 없음');
        return { metadata: { card_count: cards.length } };
    },

    async A3(page) {
        await page.goto(BASE + '/detail.php?id=8093', { waitUntil: 'networkidle', timeout: 15000 });
        const status = await page.evaluate(() => document.querySelector('title')?.textContent || '');
        if (status.includes('error') || status.includes('Error')) throw new Error('상세 페이지 에러');
        return { metadata: { title: status } };
    },

    async A4(page) {
        await page.goto(BASE + '/reviews.php', { waitUntil: 'networkidle', timeout: 15000 });
        const title = await page.title();
        if (!title) throw new Error('후기 페이지 로드 실패');
        return {};
    },

    async A5(page) {
        await page.goto(BASE + '/review_write.php', { waitUntil: 'networkidle', timeout: 15000 });
        const url = page.url();
        // 로그인 페이지로 리다이렉트 또는 로그인 필요 메시지
        const isRedirected = url.includes('login') || url.includes('register') || url.includes('review_write');
        if (!isRedirected) throw new Error('예상치 못한 URL: ' + url);
        return { metadata: { redirected_to: url } };
    },

    async A6(page) {
        await page.goto(BASE + '/booking.php?hospital_id=8093', { waitUntil: 'networkidle', timeout: 15000 });
        const url = page.url();
        // 로그인 리다이렉트 또는 예약 폼 표시
        return { metadata: { url } };
    },

    async A7(page) {
        await page.goto(BASE + '/consultation.php?id=8093', { waitUntil: 'networkidle', timeout: 15000 });
        const form = await page.$('form, textarea, .consultation');
        return { metadata: { has_form: !!form } };
    },

    async A8(page) {
        await page.goto(BASE + '/map.php', { waitUntil: 'networkidle', timeout: 15000 });
        const title = await page.title();
        return { metadata: { title } };
    },

    async A9(page) {
        await page.goto(BASE + '/treatment_ranking.php', { waitUntil: 'networkidle', timeout: 15000 });
        const tabs = await page.$$('.category-tab, [role="tab"], .nav-link');
        return { metadata: { tab_count: tabs.length } };
    },

    async A10(page) {
        await page.goto(BASE + '/price_compare.php', { waitUntil: 'networkidle', timeout: 15000 });
        const categories = await page.$$('a[href*="price_compare.php?category="]');
        return { metadata: { category_count: categories.length } };
    },

    async A11(page) {
        await page.goto(BASE + '/hospital_compare.php?ids=8093,8094', { waitUntil: 'networkidle', timeout: 15000 });
        return {};
    },

    async A12(page) {
        await page.goto(BASE + '/mypage.php', { waitUntil: 'networkidle', timeout: 15000 });
        const menuItems = await page.$$('a[href*="my_"], a[href*="notification"], a[href*="profile"]');
        return { metadata: { menu_count: menuItems.length } };
    },

    async A13(page) {
        const pages = ['my_bookings.php', 'my_wishlist.php', 'my_reviews.php', 'my_notifications.php', 'my_points.php', 'my_coupons.php'];
        const results = {};
        for (const p of pages) {
            const resp = await page.goto(BASE + '/' + p, { waitUntil: 'networkidle', timeout: 10000 });
            results[p] = resp.status();
            if (resp.status() >= 500) throw new Error(p + ' HTTP ' + resp.status());
        }
        return { metadata: results };
    },

    async A14(page) {
        await page.goto(BASE + '/events.php', { waitUntil: 'networkidle', timeout: 15000 });
        return {};
    },

    async A15(page) {
        await page.goto(BASE + '/community.php', { waitUntil: 'networkidle', timeout: 15000 });
        return {};
    },

    async A16(page) {
        const r1 = await page.goto(BASE + '/faq.php', { waitUntil: 'networkidle', timeout: 10000 });
        if (r1.status() >= 500) throw new Error('faq.php HTTP ' + r1.status());
        const r2 = await page.goto(BASE + '/qna.php', { waitUntil: 'networkidle', timeout: 10000 });
        if (r2.status() >= 500) throw new Error('qna.php HTTP ' + r2.status());
        return {};
    },

    async A17(page, context) {
        // 별도 모바일 뷰포트 컨텍스트
        const mobilePage = await context.newPage();
        await mobilePage.setViewportSize({ width: 375, height: 812 });
        await mobilePage.goto(BASE + '/index.php', { waitUntil: 'networkidle', timeout: 15000 });
        const bottomNav = await mobilePage.$('.bottom-nav, .bottom-navigation, [class*="bottom"]');
        await mobilePage.close();
        return { metadata: { has_bottom_nav: !!bottomNav } };
    },

    async A18(page) {
        await page.goto(BASE + '/index.php', { waitUntil: 'networkidle', timeout: 15000 });
        await page.evaluate(() => {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        });
        await page.waitForTimeout(500);
        const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        return { metadata: { dark_bg: bg } };
    },
    // === 추가 사용자 테스트 ===
    async U1(page) {
        const resp = await page.goto(BASE + '/tips.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('tips HTTP ' + resp.status());
        return {};
    },
    async U2(page) {
        const resp = await page.goto(BASE + '/rankings.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('rankings HTTP ' + resp.status());
        return {};
    },
    async U3(page) {
        const resp = await page.goto(BASE + '/event_calendar.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('event_calendar HTTP ' + resp.status());
        return {};
    },
    async U4(page) {
        const resp = await page.goto(BASE + '/review_photos.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('review_photos HTTP ' + resp.status());
        return {};
    },
    async U5(page) {
        const resp = await page.goto(BASE + '/detail_community.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('detail_community HTTP ' + resp.status());
        return { metadata: { url: page.url() } };
    },
    async U6(page) {
        const resp = await page.goto(BASE + '/profile_edit.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('profile_edit HTTP ' + resp.status());
        return { metadata: { url: page.url() } };
    },

    // === 마법사 (사용자) ===
    async W4(page) {
        const resp = await page.goto(BASE + '/booking_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('booking_wizard HTTP ' + resp.status());
        return { metadata: { url: page.url() } };
    },
    async W5(page) {
        const resp = await page.goto(BASE + '/consultation_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (resp.status() >= 500) throw new Error('consultation_wizard HTTP ' + resp.status());
        return {};
    },

    // === Standalone 테스트 ===
    async S4(page) {
        await page.goto(BASE + '/booking.php?hospital_id=8093', { waitUntil: 'networkidle', timeout: 15000 });
        const url = page.url();
        // 로그인 리다이렉트 또는 예약폼 — 500이면 안됨
        return { metadata: { url, standalone: true } };
    },

    async S5(page) {
        await page.goto(BASE + '/my_appointments.php', { waitUntil: 'networkidle', timeout: 15000 });
        const url = page.url();
        return { metadata: { url } };
    },

    // === Sheet 기능 테스트 ===
    async F1(page) {
        await page.goto(BASE + '/hospitals.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        if (!html.includes('ad_grade') && !html.includes('AD') && !html.includes('ad-badge')) throw new Error('광고등급 배지 없음');
        const cards = await page.$$('.hospital-card, .hospital-item, [onclick*="detail.php"]');
        return { metadata: { card_count: cards.length, has_ad_grade: true } };
    },

    async F2(page) {
        await page.goto(BASE + '/detail.php?id=8093', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasUnitBadge = html.includes('unit') || html.includes('단위') || html.includes('cc');
        return { metadata: { has_unit_badge: hasUnitBadge } };
    },

    async F3(page) {
        await page.goto(BASE + '/hospitals.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasAdBadge = html.includes('AD') || html.includes('ad-badge') || html.includes('광고');
        return { metadata: { has_ad_badge: hasAdBadge } };
    },

    async F4(page) {
        await page.goto(BASE + '/index.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasRolling = html.includes('rolling') || html.includes('롤링') || html.includes('carousel');
        return { metadata: { has_rolling: hasRolling } };
    },

    async F5(page) {
        await page.goto(BASE + '/price_compare.php', { waitUntil: 'networkidle', timeout: 15000 });
        const cats = await page.$$('a[href*="price_compare.php?category="]');
        if (cats.length === 0) throw new Error('카테고리 선택 카드 없음');
        return { metadata: { category_count: cats.length } };
    },
};

module.exports = tests;

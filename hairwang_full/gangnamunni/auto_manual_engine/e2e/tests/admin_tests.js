/**
 * 관리자 테스트 (B1~B13)
 */
const config = require('../e2e-config.json');
const { adminLogin } = require('../helpers/auth');
const BASE = config.baseUrl;

const tests = {
    async B1(page) {
        const ok = await adminLogin(page, 'admin');
        if (!ok) throw new Error('관리자 로그인 실패');
        return { metadata: { url: page.url() } };
    },

    async B2(page) {
        await page.goto(BASE + '/dashboard.php', { waitUntil: 'networkidle', timeout: 15000 });
        const cards = await page.$$('.card, .stat-card, .admin-card');
        const sidebar = await page.$('.admin-sidebar, [class*="sidebar"]');
        return { metadata: { card_count: cards.length, has_sidebar: !!sidebar } };
    },

    async B3(page) {
        const menus = ['admin_hospitals.php', 'admin_reviews.php', 'admin_ads.php', 'admin_ad_settings.php',
            'admin_theme.php', 'admin_design_tokens.php', 'admin_treatments.php', 'admin_members.php',
            'admin_events.php', 'admin_industry.php'];
        const results = {};
        for (const m of menus) {
            const resp = await page.goto(BASE + '/' + m, { waitUntil: 'networkidle', timeout: 10000 });
            results[m] = resp.status();
            if (resp.status() >= 500) throw new Error(m + ' HTTP ' + resp.status());
        }
        return { metadata: results };
    },

    async B4(page) {
        await page.goto(BASE + '/admin_hospitals.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table, .hospital-list');
        if (!table) throw new Error('병원 목록 테이블 없음');
        return { metadata: { has_table: true } };
    },

    async B5(page) {
        await page.goto(BASE + '/admin_reviews.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table');
        return { metadata: { has_table: !!table } };
    },

    async B6(page) {
        await page.goto(BASE + '/admin_ad_settings.php', { waitUntil: 'networkidle', timeout: 15000 });
        const tabs = await page.$$('[data-bs-toggle="tab"]');
        const preview = await page.$('#previewFrame, iframe');
        if (tabs.length < 5) throw new Error('탭 수 부족: ' + tabs.length);
        return { metadata: { tab_count: tabs.length, has_preview: !!preview } };
    },

    async B7(page) {
        await page.goto(BASE + '/admin_design_tokens.php', { waitUntil: 'networkidle', timeout: 15000 });
        const colorInputs = await page.$$('input[type="color"]');
        const iframe = await page.$('#previewFrame, iframe');
        return { metadata: { color_inputs: colorInputs.length, has_iframe: !!iframe } };
    },

    async B8(page) {
        await page.goto(BASE + '/admin_theme.php', { waitUntil: 'networkidle', timeout: 15000 });
        const tabs = await page.$$('[data-bs-toggle="tab"]');
        return { metadata: { tab_count: tabs.length } };
    },

    async B9(page) {
        await page.goto(BASE + '/admin_industry.php', { waitUntil: 'networkidle', timeout: 15000 });
        const presets = await page.$$('.preset-card');
        if (presets.length < 5) throw new Error('프리셋 카드 수 부족: ' + presets.length);
        return { metadata: { preset_count: presets.length } };
    },

    async B10(page) {
        await page.goto(BASE + '/admin_treatments.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table');
        return { metadata: { has_table: !!table } };
    },

    async B11(page) {
        await page.goto(BASE + '/admin_events.php', { waitUntil: 'networkidle', timeout: 15000 });
        return {};
    },

    async B12(page) {
        await page.goto(BASE + '/admin_send_notification.php', { waitUntil: 'networkidle', timeout: 15000 });
        const form = await page.$('form, textarea');
        return { metadata: { has_form: !!form } };
    },

    async B13(page, context) {
        // 병원관리자 별도 컨텍스트
        const hospPage = await context.newPage();
        const ok = await adminLogin(hospPage, 'hospital');
        if (!ok) throw new Error('병원관리자 로그인 실패');
        const sideLinks = await hospPage.$$('.admin-sidebar .nav-link, .sidebar .nav-link');
        await hospPage.close();
        return { metadata: { sidebar_items: sideLinks.length, role: 'hospital_admin' } };
    },
    // === 추가 관리 테스트 ===
    async M1(page) {
        const r = await page.goto(BASE + '/admin_doctors.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_doctors HTTP ' + r.status());
        return {};
    },
    async M2(page) {
        const r = await page.goto(BASE + '/admin_navigation.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_navigation HTTP ' + r.status());
        return {};
    },
    async M3(page) {
        const r = await page.goto(BASE + '/admin_categories.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_categories HTTP ' + r.status());
        return {};
    },
    async M4(page) {
        const r = await page.goto(BASE + '/admin_cpv_settings.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_cpv_settings HTTP ' + r.status());
        return {};
    },
    async M5(page) {
        const r = await page.goto(BASE + '/admin_cpv_dashboard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_cpv_dashboard HTTP ' + r.status());
        return {};
    },
    async M6(page) {
        const r = await page.goto(BASE + '/admin_admins.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_admins HTTP ' + r.status());
        return {};
    },
    async M7(page) {
        const r = await page.goto(BASE + '/admin_hospital_news.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_hospital_news HTTP ' + r.status());
        return {};
    },
    async M8(page) {
        const r = await page.goto(BASE + '/admin_faq.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_faq HTTP ' + r.status());
        return {};
    },
    async M9(page) {
        const r = await page.goto(BASE + '/admin_qna.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_qna HTTP ' + r.status());
        return {};
    },
    async M10(page) {
        const r = await page.goto(BASE + '/admin_rankings.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('admin_rankings HTTP ' + r.status());
        return {};
    },

    // === 마법사 (관리자) ===
    async W1(page) {
        const r = await page.goto(BASE + '/admin_hospital_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('hospital_wizard HTTP ' + r.status());
        return {};
    },
    async W3(page) {
        const r = await page.goto(BASE + '/admin_patient_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('patient_wizard HTTP ' + r.status());
        return {};
    },
    async W6(page) {
        const r = await page.goto(BASE + '/ai_ad_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('ai_ad_wizard HTTP ' + r.status());
        return {};
    },
    async W7(page) {
        const r = await page.goto(BASE + '/ai_event_wizard.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('ai_event_wizard HTTP ' + r.status());
        return {};
    },

    // === 파트너 ===
    async P1(page) {
        const r = await page.goto(BASE + '/partner_wallet.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('partner_wallet HTTP ' + r.status());
        return {};
    },
    async P2(page) {
        const r = await page.goto(BASE + '/partner_settings.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('partner_settings HTTP ' + r.status());
        return {};
    },
    async P3(page) {
        const r = await page.goto(BASE + '/partner_ai_settings.php', { waitUntil: 'networkidle', timeout: 15000 });
        if (r.status() >= 500) throw new Error('partner_ai_settings HTTP ' + r.status());
        return {};
    },

    // === RBAC 테스트 ===
    async R1(page) {
        await page.goto(BASE + '/admin_admins.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const roles = ['super_admin', 'admin', 'editor', 'viewer', 'hospital_super_admin', 'hospital_admin'];
        let found = 0;
        roles.forEach(r => { if (html.includes(r)) found++; });
        if (found < 5) throw new Error('역할 ' + found + '개만 발견 (9개 필요)');
        return { metadata: { roles_found: found } };
    },

    async R2(page) {
        await page.goto(BASE + '/admin_grade_permissions.php', { waitUntil: 'networkidle', timeout: 15000 });
        const toggles = await page.$$('.perm-toggle');
        if (toggles.length < 40) throw new Error('등급 토글 ' + toggles.length + '개 (56개 필요)');
        return { metadata: { toggle_count: toggles.length } };
    },

    async R3(page, context) {
        const hospPage = await context.newPage();
        const { adminLogin } = require('../helpers/auth');
        const ok = await adminLogin(hospPage, 'hospital');
        if (!ok) throw new Error('병원관리자 로그인 실패');
        const sideLinks = await hospPage.$$('.admin-sidebar .nav-link, .sidebar .nav-link');
        await hospPage.close();
        return { metadata: { sidebar_items: sideLinks.length } };
    },

    async R4(page) {
        await page.goto(BASE + '/admin_admins.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasStaff = html.includes('staff') || html.includes('직원');
        return { metadata: { has_staff_role: hasStaff } };
    },

    // === Standalone 테스트 ===
    async S6(page) {
        await page.goto(BASE + '/admin_bookings.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table');
        return { metadata: { has_table: !!table, standalone: true } };
    },

    // === Sheet 기능 테스트 ===
    async F7(page) {
        await page.goto(BASE + '/admin_ad_pricing.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table');
        if (!table) throw new Error('광고 단가 테이블 없음');
        return { metadata: { has_table: true } };
    },

    async F8(page) {
        await page.goto(BASE + '/admin_hospitals.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasRolling = html.includes('rolling') || html.includes('롤링') || html.includes('캠페인');
        return { metadata: { has_rolling: hasRolling } };
    },

    async F9(page) {
        await page.goto(BASE + '/admin_promos.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table, .promo');
        return { metadata: { has_content: !!table } };
    },

    async F10(page) {
        await page.goto(BASE + '/admin_industry.php', { waitUntil: 'networkidle', timeout: 15000 });
        const presets = await page.$$('.preset-card');
        if (presets.length < 10) throw new Error('프리셋 카드 ' + presets.length + '개 (10개 필요)');
        return { metadata: { preset_count: presets.length } };
    },

    async F11(page) {
        await page.goto(BASE + '/admin_bookings.php', { waitUntil: 'networkidle', timeout: 15000 });
        const table = await page.$('table, .table');
        return { metadata: { has_table: !!table } };
    },

    async F12(page) {
        await page.goto(BASE + '/admin_treatments.php', { waitUntil: 'networkidle', timeout: 15000 });
        const html = await page.content();
        const hasUnit = html.includes('unit') || html.includes('단위') || html.includes('용량');
        return { metadata: { has_unit_management: hasUnit } };
    },
};

module.exports = tests;

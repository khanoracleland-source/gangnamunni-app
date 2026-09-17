<?php
/** The sidebar and page navigation use the same menu definitions. */
function admin_sidebar_menus(): array {
    if (!function_exists('term')) require_once __DIR__ . '/industry_terms.php';
    $menus = [

        // ━━ 홈 ━━
        ['type' => 'group', 'label' => '대시보드', 'icon' => 'bi-speedometer2', 'key' => 'home_group', 'children' => [
            ['url' => 'dashboard.php', 'label' => '대시보드', 'icon' => 'bi-speedometer2', 'key' => 'dashboard'],
            ['url' => 'admin_sns_reviews.php', 'label' => 'SNS / 앱스토어 심사 관리', 'icon' => 'bi-phone', 'key' => 'sns_reviews'],
            ['url' => 'admin_menu_guide.php', 'label' => '메뉴 안내', 'icon' => 'bi-signpost-split', 'key' => 'menu_guide'],
            ['url' => 'admin_menu_manager.php', 'label' => '메뉴 관리', 'icon' => 'bi-grid-1x2', 'key' => 'menu_manager', 'target' => '_blank'],
            ['url' => 'https://hms.seoulbeauty.app/hairwang/adm/config_form.php', 'label' => '그누보드 관리자', 'icon' => 'bi-box-arrow-up-right', 'key' => 'gnuboard_admin', 'target' => '_blank'],
        ]],

        // ━━ 병원 등록 ━━
        ['type' => 'group', 'label' => '병원 등록', 'icon' => 'bi-hospital', 'key' => 'hospital_register', 'children' => [
            ['url' => 'hospital_apply.php', 'label' => '병원 등록 신청', 'icon' => 'bi-pencil-square', 'key' => 'hospital_apply'],
            ['url' => 'hospital_apply_status.php', 'label' => '내 신청 현황', 'icon' => 'bi-clock-history', 'key' => 'hospital_apply_status'],
            ['url' => 'admin_hospitals.php?tab=pending', 'label' => '신청 목록 (관리자)', 'icon' => 'bi-list-check', 'key' => 'hospital_pending'],
        ]],

        // ── 구분선: 콘텐츠/데이터 영역 ──
        ['type' => 'divider'],

        // 콘텐츠 관리 그룹
        ['type' => 'group', 'label' => '콘텐츠 관리', 'icon' => 'bi-collection', 'key' => 'content', 'children' => [
            ['url' => 'admin_content_hub.php', 'label' => '콘텐츠 허브', 'icon' => 'bi-collection-fill', 'key' => 'content_hub'],
            ['url' => 'admin_recommend.php', 'label' => '메인 추천 병원', 'icon' => 'bi-star', 'key' => 'recommend'],
            ['url' => 'admin_hospitals.php', 'label' => term('business') . ' 관리', 'icon' => 'bi-building', 'key' => 'hospitals'],
            ['url' => 'admin_hospital_locations.php', 'label' => '병원 노출 순서', 'icon' => 'bi-sort-numeric-down', 'key' => 'hospitals', 'active_key' => 'hospital_locations'],
            ['url' => 'admin_doctors.php', 'label' => term('doctor') . ' 관리', 'icon' => 'bi-person-badge', 'key' => 'doctors'],
            ['url' => 'admin_personnel.php', 'label' => '인력 관리', 'icon' => 'bi-people', 'key' => 'personnel'],
            ['url' => 'admin_community_posts.php', 'label' => '커뮤니티 심사', 'icon' => 'bi-chat-square-text', 'key' => 'community_moderation'],
            ['url' => 'admin_reviews.php', 'label' => term('review') . ' 관리', 'icon' => 'bi-chat-dots', 'key' => 'reviews'],
            ['url' => 'admin_hospital_images.php', 'label' => '병원 이미지 관리', 'icon' => 'bi-images', 'key' => 'hospital_images'],
            ['url' => 'admin_ads.php', 'label' => '광고 관리', 'icon' => 'bi-megaphone', 'key' => 'ads'],
            ['url' => 'admin_categories.php', 'label' => '카테고리 관리', 'icon' => 'bi-tags', 'key' => 'categories'],
            // TEMPORARILY DISABLED: admin_tips.php (재활성화 시 아래 항목의 주석을 해제)
            // ['url' => 'admin_tips.php', 'label' => '꿀팁 관리', 'icon' => 'bi-lightbulb', 'key' => 'tips'],
            ['url' => 'admin_events.php', 'label' => '이벤트 관리', 'icon' => 'bi-calendar-event', 'key' => 'events'],
            ['url' => 'admin_columns.php', 'label' => '칼럼/뉴스 관리', 'icon' => 'bi-newspaper', 'key' => 'columns'],
            // TEMPORARILY DISABLED: admin_event_entries.php
        ]],
        // 광고 관리 그룹
        ['type' => 'group', 'label' => '광고 관리', 'icon' => 'bi-megaphone', 'key' => 'ad_management', 'children' => [
            ['url' => 'admin_ad_pricing.php', 'label' => '광고 단가 관리', 'icon' => 'bi-currency-dollar', 'key' => 'ad_pricing'],
            ['url' => 'admin_ad_slots.php', 'label' => '광고 슬롯 관리', 'icon' => 'bi-trophy', 'key' => 'ad_slots'],
            ['url' => 'admin_ad_stats.php', 'label' => '광고 통계', 'icon' => 'bi-graph-up', 'key' => 'ad_stats'],
            ['url' => 'admin_advertisers.php', 'label' => '광고주 관리', 'icon' => 'bi-building', 'key' => 'advertisers'],
            ['url' => 'admin_ad_settings.php', 'label' => '광고·디스플레이 설정', 'icon' => 'bi-toggles', 'key' => 'ad_settings'],
            ['url' => 'admin_promos.php', 'label' => '프로모션 관리', 'icon' => 'bi-percent', 'key' => 'promos'],
        ]],
        // 신청 관리 그룹
        ['type' => 'group', 'label' => '신청 관리', 'icon' => 'bi-clipboard2-pulse', 'key' => 'wizard', 'children' => [
            ['url' => 'admin_hospital_wizard.php', 'label' => '병원 등록 마법사', 'icon' => 'bi-building-add', 'key' => 'hospital_wizard'],
            // TEMPORARILY DISABLED: admin_ad_wizard.php (재활성화 시 아래 항목의 주석을 해제)
            // ['url' => 'admin_ad_wizard.php', 'label' => '광고 등록 마법사', 'icon' => 'bi-megaphone-fill', 'key' => 'ad_wizard'],
            ['url' => 'admin_bookings.php', 'label' => '예약 관리', 'icon' => 'bi-calendar-check', 'key' => 'bookings'],
            ['url' => 'admin_consultations.php', 'label' => '상담 관리', 'icon' => 'bi-chat-dots-fill', 'key' => 'consultations'],
        ]],
        // 등록 관리 그룹
        ['type' => 'group', 'label' => '등록 관리', 'icon' => 'bi-person-plus', 'key' => 'register', 'children' => [
            // TEMPORARILY DISABLED: admin_nurse_wizard.php, admin_pharmacist_wizard.php, admin_patient_wizard.php
            ['url' => 'admin_staff_wizard.php', 'label' => '직원 등록 마법사', 'icon' => 'bi-people', 'key' => 'staff_wizard'],
            // TEMPORARILY DISABLED: admin_medicine_wizard.php, admin_medicines.php
            ['url' => 'admin_appointment_wizard.php', 'label' => '예약 등록 마법사', 'icon' => 'bi-calendar-plus', 'key' => 'appointment_wizard'],
        ]],

        // TEMPORARILY HIDDEN FROM ADMIN UI: 진료 관리 group.
        // 시술 관리 그룹
        ['type' => 'group', 'label' => term('service', '시술') . ' 관리', 'icon' => 'bi-heart-pulse', 'key' => 'treatment_mgmt', 'children' => [
            ['url' => 'admin_treatment_units.php', 'label' => '단위 관리', 'icon' => 'bi-rulers', 'key' => 'treatment_units'],
            ['url' => 'admin_treatments.php', 'label' => '시술 마스터 관리', 'icon' => 'bi-scissors', 'key' => 'treatment_master'],
            ['url' => 'admin_doctor_treatments.php', 'label' => '의사별 시술 전문도', 'icon' => 'bi-person-badge', 'key' => 'doctor_treatments'],
            ['url' => 'admin_treatment_packages.php', 'label' => '시술 패키지 관리', 'icon' => 'bi-box-seam', 'key' => 'treatment_packages'],
        ]],
        // 엑셀 관리 그룹
        ['type' => 'group', 'label' => '엑셀 관리', 'icon' => 'bi-file-earmark-spreadsheet', 'key' => 'excel', 'children' => [
            ['url' => 'admin_excel_import_wizard.php', 'label' => '엑셀 대량 등록', 'icon' => 'bi-upload', 'key' => 'excel_import'],
            ['url' => 'admin_excel_export.php', 'label' => '데이터 검색/다운로드', 'icon' => 'bi-download', 'key' => 'excel_export'],
        ]],

        // ── 구분선: 사용자/소통 영역 ──
        ['type' => 'divider'],

        ['url' => 'admin_members.php', 'label' => term('member') . ' 관리', 'icon' => 'bi-people', 'key' => 'members'],

        // 신고/차단 관리 그룹 (Apple Guideline 1.2 UGC Safety)
        ['type' => 'group', 'label' => '신고/차단 관리', 'icon' => 'bi-shield-exclamation', 'key' => 'report_block', 'children' => [
            ['url' => 'admin_content_reports.php', 'label' => '신고 관리', 'icon' => 'bi-flag', 'key' => 'content_reports'],
            ['url' => 'admin_blocked_users.php', 'label' => '차단 관리', 'icon' => 'bi-person-x', 'key' => 'blocked_users'],
            ['url' => 'terms_of_service.php', 'label' => '이용약관', 'icon' => 'bi-file-earmark-text', 'key' => 'terms'],
            ['url' => 'community_guidelines.php', 'label' => '커뮤니티 가이드라인', 'icon' => 'bi-book', 'key' => 'guidelines'],
        ]],

        // 고객 서비스 그룹
        ['type' => 'group', 'label' => '고객 서비스', 'icon' => 'bi-headset', 'key' => 'customer_service', 'children' => [
            ['url' => 'admin_faq.php', 'label' => 'FAQ 관리', 'icon' => 'bi-question-circle', 'key' => 'faq'],
            ['url' => 'admin_qna.php', 'label' => 'Q&A 관리', 'icon' => 'bi-chat-dots', 'key' => 'qna'],
            ['url' => 'admin_coupons.php', 'label' => '쿠폰 관리', 'icon' => 'bi-ticket-perforated', 'key' => 'coupons'],
            ['url' => 'admin_points.php', 'label' => '포인트 관리', 'icon' => 'bi-coin', 'key' => 'points'],
            ['url' => 'admin_membership.php', 'label' => '회원등급 관리', 'icon' => 'bi-award', 'key' => 'membership'],
            ['url' => 'admin_rankings.php', 'label' => '병원 랭킹 관리', 'icon' => 'bi-trophy', 'key' => 'rankings'],
            ['url' => 'admin_hospital_news.php', 'label' => '병원 소식 관리', 'icon' => 'bi-newspaper', 'key' => 'hospital_news'],
        ]],

        // 메일 관리 그룹
        ['type' => 'group', 'label' => '메일 관리', 'icon' => 'bi-envelope', 'key' => 'mailserver', 'children' => [
            ['url' => 'admin_mail.php', 'label' => '메일 관리', 'icon' => 'bi-inbox', 'key' => 'admin_mail'],
            ['url' => 'admin_mail_templates.php', 'label' => '상용구 관리', 'icon' => 'bi-card-text', 'key' => 'mail_templates'],
            ['url' => 'admin_mail_signatures.php', 'label' => '서명 관리', 'icon' => 'bi-pen', 'key' => 'mail_signatures'],
            ['url' => 'admin_mail_accounts.php', 'label' => '계정 설정', 'icon' => 'bi-person-gear', 'key' => 'mail_accounts'],
            ['url' => 'https://mail.seoulbeauty.app/', 'label' => '메일서버(외부)', 'icon' => 'bi-envelope-arrow-up', 'key' => 'mailserver', 'target' => '_blank'],
        ]],

        // ── 구분선: AI/자동화 영역 ──
        ['type' => 'divider'],

        // TEMPORARILY DISABLED: AI / 자동화 group and all child pages.

        // 인수인계 대시보드 — 사이드바에는 노출하지 않고 직접 URL로만 접근 (superadmin 전용)
        ['url' => 'admin_handover.php', 'label' => '인수인계 대시보드', 'icon' => 'bi-clipboard-check', 'key' => 'handover_dashboard', 'hidden' => true],

        // ── 구분선: 시스템/관리자 영역 (하단) ──
        ['type' => 'divider'],

        // 사이트 설정 그룹
        ['type' => 'group', 'label' => '사이트 설정', 'icon' => 'bi-gear-wide-connected', 'key' => 'site_settings', 'children' => [
            ['url' => 'admin_site_settings.php', 'label' => '통합 설정', 'icon' => 'bi-gear', 'key' => 'site_settings_main'],
            ['url' => 'admin_theme.php', 'label' => '테마 설정', 'icon' => 'bi-palette', 'key' => 'theme'],
            ['url' => 'admin_theme_advanced.php', 'label' => '고급 테마 관리', 'icon' => 'bi-brush', 'key' => 'theme_advanced'],
            ['url' => 'admin_theme_wizard.php', 'label' => '테마 설정 마법사', 'icon' => 'bi-magic', 'key' => 'theme'],
            ['url' => 'admin_design_mode.php', 'label' => '디자인 모드 설정', 'icon' => 'bi-palette2', 'key' => 'site_settings_main'],
            ['url' => 'admin_industry.php', 'label' => '업종 프리셋 전환', 'icon' => 'bi-shop', 'key' => 'site_settings_main'],
            ['url' => 'admin_map_settings.php', 'label' => '지도 API 설정', 'icon' => 'bi-geo-alt', 'key' => 'site_settings_main'],
            ['url' => 'admin_navigation.php', 'label' => '네비게이션 관리', 'icon' => 'bi-signpost-2', 'key' => 'nav_settings'],
            ['url' => 'admin_nav_settings.php', 'label' => '하단 탭 설정 (실제 반영)', 'icon' => 'bi-toggle-on', 'key' => 'nav_settings'],
            ['url' => 'admin_editor_settings.php', 'label' => '에디터 설정', 'icon' => 'bi-sliders', 'key' => 'editor_settings'],
            ['url' => 'admin_maintenance.php', 'label' => '점검 모드 관리', 'icon' => 'bi-tools', 'key' => 'maintenance'],
            ['url' => 'admin_upgrade_plan.php', 'label' => '확장 계획', 'icon' => 'bi-rocket-takeoff', 'key' => 'upgrade'],
        ]],
        // 관리자 관리 (최하단)
        ['type' => 'group', 'label' => '관리자 / 알림', 'icon' => 'bi-shield-lock', 'key' => 'admin_notify_group', 'children' => [
            ['url' => 'admin_admins.php', 'label' => '관리자 관리', 'icon' => 'bi-shield-lock', 'key' => 'admin_accounts'],
            ['url' => 'admin_hospital_admins.php', 'label' => '병원 부관리자 관리', 'icon' => 'bi-person-lock', 'key' => 'admins'],
            ['url' => 'admin_hospital_suspension.php', 'label' => '병원 정지/요금 관리', 'icon' => 'bi-pause-circle', 'key' => 'admins'],
            ['url' => 'admin_grade_permissions.php', 'label' => '등급별 기능 관리', 'icon' => 'bi-shield-check', 'key' => 'grade_permissions'],
            ['url' => 'admin_notifications.php', 'label' => '알림 센터', 'icon' => 'bi-bell', 'key' => 'notifications'],
            ['url' => 'admin_send_notification.php', 'label' => '알림 보내기', 'icon' => 'bi-send', 'key' => 'send_notification'],
        ]],

        // ── 구분선: 도움말 영역 (최하단) ──
        ['type' => 'divider'],

        // TEMPORARILY DISABLED: 도움말 group and all child pages.
        // TEMPORARILY HIDDEN FROM ADMIN UI: 운영 관리 group.

        // TEMPORARILY DISABLED: 개발 도구 group and all child pages.

        // ── 구분선: QA / 프로젝트 영역 ──
        ['type' => 'divider'],


        // CPV 과금 관리 그룹
        ['type' => 'group', 'label' => 'CPV 과금 관리', 'icon' => 'bi-eye', 'key' => 'cpv_group', 'children' => [
            ['url' => 'admin_cpv_settings.php', 'label' => 'CPV 단가 설정', 'icon' => 'bi-sliders', 'key' => 'cpv_settings'],
            ['url' => 'admin_wallet.php', 'label' => '지갑 관리', 'icon' => 'bi-wallet2', 'key' => 'cpv_wallet'],
            ['url' => 'admin_cpv_rates.php', 'label' => 'CPV 단가 규칙 관리', 'icon' => 'bi-currency-dollar', 'key' => 'cpv_rates'],
            ['url' => 'admin_cpv_dashboard.php', 'label' => 'CPV 대시보드', 'icon' => 'bi-graph-up-arrow', 'key' => 'cpv_dashboard'],
            ['url' => 'admin_settlement.php', 'label' => 'CPV 정산 보고서', 'icon' => 'bi-file-earmark-bar-graph', 'key' => 'cpv_settlement'],
        ]],

        // 결제 관리 그룹
        ['type' => 'group', 'label' => '결제 관리', 'icon' => 'bi-credit-card-2-front', 'key' => 'payment_group', 'children' => [
            ['url' => 'admin_booking_revenue.php', 'label' => '예약 결제 매출', 'icon' => 'bi-cash-coin', 'key' => 'booking_revenue'],
        ]],

        ['type' => 'divider'],

        // 앱 다운로드 그룹
        ['type' => 'group', 'label' => '앱 다운로드', 'icon' => 'bi-phone', 'key' => 'app_download_group', 'children' => [
            ['url' => 'admin_app_downloads.php?app=patient', 'label' => '서울미녀', 'icon' => 'bi-google-play', 'key' => 'app_patient'],
            ['url' => 'admin_app_downloads.php?app=admin', 'label' => '서울미녀 파트너', 'icon' => 'bi-phone', 'key' => 'app_admin'],
        ]],

        // TEMPORARILY DISABLED: QA & 리포트 group and all 16 report pages.

        // ── 구분선: QA 통합 ──
        ['type' => 'divider'],

        // TEMPORARILY DISABLED: QA 통합 대시보드.

        // ── 구분선: Project Status (사이드바 최하단) ──
        ['type' => 'divider'],

        // TEMPORARILY DISABLED: Project Status group, fix log, timeline and dated reports.

        // ── 구분선: 진행사항 ──
        ['type' => 'divider'],

        // TEMPORARILY DISABLED: 진행사항 group.
    ];

    // 자동 테스트가 비활성화된 동안 관련 메뉴만 숨기고 다른 자동화 도구는 유지한다.
    if (strtolower(trim((string)getenv('AUTO_TEST_ENABLED'))) !== 'true') {
        foreach ($menus as &$menu_item) {
            if (!isset($menu_item['children']) || !is_array($menu_item['children'])) continue;
            $menu_item['children'] = array_values(array_filter(
                $menu_item['children'],
                static function ($child) {
                    return ($child['key'] ?? '') !== 'auto_test_v2';
                }
            ));
        }
        unset($menu_item);
    }

    return $menus;
}

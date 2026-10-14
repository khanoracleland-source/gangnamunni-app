<?php
/**
 * 공통 관리자 헤더
 * Bootstrap 5.3 + 주간/야간 모드 지원
 *
 * 사용법: 각 admin 페이지에서 $admin_page_title, $admin_current 설정 후 include
 */

require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/admin_menu_navigation.php';
admin_enforce_menu_navigation(true);

if (!isset($admin_page_title)) $admin_page_title = '관리자';
if (!isset($admin_current)) $admin_current = '';

// 권한 체크 함수 로드
if (!function_exists('check_permission')) {
    require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/hospital_filter.php';
}
// 알림 헬퍼 로드
if (!function_exists('create_notification')) {
    require_once __DIR__ . '/admin_notifications.php';
}
// 업종 추상화 레이어 로드
if (!function_exists('term')) {
    require_once __DIR__ . '/industry_terms.php';
}

// 관리자 메뉴 목록 (기능별 그룹 + 구분선 정리)
// 사이드바 열린 그룹 쿠키에서 읽기 (리플래시 방지)
// 쿠키 버전 — 변경 시 이전 쿠키 무효화하여 전체 열림으로 리셋
$_sb_cookie_ver = 'v11';
$_sb_open_groups = null; // null = 모든 그룹 열림
if (isset($_COOKIE['sb_open_groups']) && isset($_COOKIE['sb_ver']) && $_COOKIE['sb_ver'] === $_sb_cookie_ver) {
    $_sb_open_groups = $_COOKIE['sb_open_groups'] !== '' ? explode(',', $_COOKIE['sb_open_groups']) : [];
} else {
    // 버전 불일치 또는 첫 방문 → 쿠키 리셋
    setcookie('sb_ver', $_sb_cookie_ver, time() + 86400, '/');
    setcookie('sb_open_groups', '', time() - 3600, '/'); // 이전 쿠키 삭제
}

$admin_menus = admin_sidebar_menus();
$admin_favorite_menu_keys = admin_menu_favorite_keys($connect ?? null);

// 메뉴별 기능 설명 (배너 + 메뉴 안내 페이지에서 공유)
$menu_descriptions = [
    'dashboard' => '전체 현황을 한눈에 확인하고, 주요 기능에 빠르게 접근합니다.',
    'menu_guide' => '모든 메뉴의 기능을 한눈에 확인할 수 있습니다.',
    'gnuboard_admin' => '그누보드 기본환경 설정 등 공식 관리자(adm)로 이동합니다. 새 탭에서 열립니다.',
    'hospitals' => term('business', '병원') . ' 목록을 조회·수정하고, 신규 등록 승인/반려를 처리합니다.',
    'doctors' => term('staff', '의사') . ' 목록을 조회·수정하고, 전문분야별 현황을 확인합니다.',
    'reviews' => '등록된 ' . term('review', '후기') . '를 조회·수정·삭제하고, 노출 상태를 관리합니다.',
    'review_wizard' => 'AI가 ' . term('review', '후기') . '를 분석·검수하고, 승인/반려 처리를 진행합니다.',
    'hospital_images' => term('business', '병원') . '별 이미지(시설사진 등)를 업로드·관리합니다.',
    'ads' => '광고 배너를 등록·수정하고, 게재 상태를 관리합니다.',
    'categories' => term('department', '진료과') . ', ' . term('service', '시술') . ' 카테고리를 추가·수정·정렬합니다.',
    'tips' => '꿀팁 콘텐츠를 등록·수정·삭제하고, 카테고리별 현황을 관리합니다.',
    'events' => '이벤트를 등록·수정·삭제하고, 응모/래플 현황을 관리합니다.',
    'hospital_wizard' => '새 ' . term('business', '병원') . '을 단계별 마법사로 등록합니다.',
    'ad_wizard' => '새 광고를 단계별 마법사로 신청합니다.',
    'bookings' => '예약 목록 조회, 승인/거절, HMS 연동',
    'booking_revenue' => '환자 예약 결제 금액을 병원별로 집계하고, 병원별 환자·' . term('service', '시술') . '별 결제 상세를 조회합니다. (super_admin=전체 병원, 그 외=소속 병원만)',
    'consultations' => '상담 신청 조회, 승인/완료/거절',
    'treatments' => term('service', '시술') . ' CRUD, 카테고리/인기/할인가 관리',
    'treatment_master' => term('service', '시술') . ' 마스터 데이터 관리 (카테고리/인기/할인가)',
    'hospital_treatments' => term('business', '병원') . '별 ' . term('service', '시술') . ' 항목 배정 및 가격 관리',
    'columns' => '칼럼/뉴스 관리',
    'event_entries' => '이벤트 응모/당첨 관리',
    'medicines' => '의약품 목록 조회/수정',
    'ad_pricing' => '유형별 광고 단가 관리',
    'ad_slots' => '시술/지역 키워드별 순위 슬롯 상품 및 병원 배정 관리',
    'ad_stats' => '광고 노출/클릭/CTR 통계',
    'advertisers' => '광고주 정보/계약 관리',
    'ad_settings' => '광고 ON/OFF 12개 플래그',
    'promos' => '프로모션 관리',
    'nurse_wizard' => '간호사를 단계별 마법사로 등록합니다.',
    'pharmacist_wizard' => '약사를 단계별 마법사로 등록합니다.',
    'patient_wizard' => term('client', '환자') . '를 단계별 마법사로 등록합니다.',
    'staff_wizard' => '직원(접수, 회계, 검사 등)을 단계별 마법사로 등록합니다.',
    'medicine_wizard' => '의약품을 단계별 마법사로 등록합니다.',
    'appointment_wizard' => '예약을 단계별 마법사로 등록합니다.',
    'excel_import' => '엑셀 파일로 ' . term('staff', '의사') . '·' . term('client', '환자') . '·직원 등을 대량 등록합니다.',
    'excel_export' => '등록 데이터를 검색하고 엑셀로 다운로드합니다.',
    'members' => '전체 회원을 조회·수정·차단하고, 등급을 관리합니다.',
    'content_reports' => '사용자 신고 접수 목록, 24시간 내 조치 필수 (Apple 1.2 준수)',
    'blocked_users' => '사용자 간 차단 현황 및 관리자 직접 차단/정지 관리',
    'terms' => '서비스 이용약관 (불쾌 콘텐츠 무관용 정책 포함)',
    'guidelines' => '커뮤니티 가이드라인 (허용/금지 콘텐츠 기준, 신고 방법)',
    'faq' => 'FAQ(자주 묻는 질문)를 카테고리별로 등록·수정·관리합니다.',
    'qna' => '사용자 Q&A 질문을 확인하고 답변합니다.',
    'coupons' => '쿠폰 생성·배포·만료 관리, 사용 현황 추적',
    'points' => '포인트 적립·사용 이력 조회, 수동 조정',
    'membership' => '회원 등급 체계 설정, 등급별 혜택 관리',
    'rankings' => term('business', '병원') . ' ' . term('ranking', '랭킹') . ' 자동 계산, 추천 관리',
    'hospital_news' => term('news', '병원 소식') . '·프로모션·공지사항 관리',
    'admin_mail' => '이메일을 발송·수신·검색하는 메일함입니다. (Gmail 스타일)',
    'mail_templates' => '자주 쓰는 메일 문구를 상용구로 저장·관리합니다.',
    'mail_signatures' => '메일 하단에 자동 삽입되는 서명을 관리합니다.',
    'mail_accounts' => 'IMAP/SMTP 메일 계정을 등록·설정합니다.',
    'mailserver' => '외부 메일서버(Mailcow) 관리 화면으로 이동합니다.',
    'ai_settings' => 'AI 프로바이더(OpenAI, Claude 등) API 키와 설정을 관리합니다.',
    'auto_test' => '레거시 페이지 접근·오류·스크린샷 테스트를 실행합니다.',
    'auto_test_v2' => '선택한 페이지의 접근·오류·스크린샷 테스트를 실행합니다.',
    'theme' => '사이트 테마(색상, 로고, 레이아웃)를 설정합니다.',
    'theme_advanced' => 'CSS/HTML 직접 편집으로 고급 테마를 커스터마이징합니다.',
    'nav_settings' => '사이트 상단/하단 네비게이션 메뉴를 구성합니다.',
    'editor_settings' => term('review', '후기') . ' 에디터(Quill.js)의 툴바·옵션을 설정합니다.',
    'maintenance' => '사이트 점검 모드를 켜고, 점검 공지 메시지를 설정합니다.',
    'upgrade' => '서비스 확장 계획과 기능 로드맵을 확인합니다.',
    'admins' => '관리자 계정을 추가·수정하고, 역할(RBAC)을 배정합니다.',
    'notifications' => '알림을 확인하고, 유형별로 관리합니다.',
    'send_notification' => '회원에게 알림을 발송합니다. 전체/그룹/개별 대상 선택 가능.',
    'wizard_guide' => '등록 마법사 7종의 사용법 가이드를 확인합니다.',
    'hospital_suspension' => 'Hospital suspension and payment management.',
    'ipd' => '입원 ' . term('client', '환자') . ' 관리 및 퇴원 처리를 합니다.',
    'beds' => '병상 현황 조회 및 배정을 관리합니다.',
    'bills' => '진료비 청구 및 결제를 관리합니다.',
    'pharmacy' => '약국 및 의약품을 관리합니다.',
    'lab' => '검사실 및 검사 결과를 관리합니다.',
    'schedules' => term('staff', '의사') . ' 일정 및 ' . term('appointment', '진료') . ' 스케줄을 관리합니다.',
    'finance' => '수입/지출 및 재무 현황을 관리합니다.',
    'auto_manual' => '시스템 전체 자동 매뉴얼 문서를 확인합니다.',
    'screenshot_book' => '35개 관리 페이지를 자동 캡처하여 스크린샷 매뉴얼을 생성합니다.',
    'fix_log' => 'QA 수정 이력을 관리하고, 수정 전/후 스크린샷을 비교합니다.',
    'qa_dashboard' => '구현 현황, 테스트 시나리오, 버그 이력, 보고서 아카이브, 스크린샷을 통합 관리합니다.',
];

// $admin_page_description 자동 설정 (페이지에서 직접 지정하지 않은 경우)
if (!isset($admin_page_description) && isset($menu_descriptions[$admin_current])) {
    $admin_page_description = $menu_descriptions[$admin_current];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo admin_page_title($admin_page_title); ?></title>
    <!-- 플래시 방지: 초기 배경색 설정 -->
    <style>
    html{background:#fff;color:#212529}
    html[data-bs-theme="dark"]{background:#212529;color:#dee2e6}
    body{background:transparent}
    </style>
    <!-- 플래시 방지: 저장된 테마를 즉시 적용 -->
    <script>
    (function(){
        var t=localStorage.getItem('admin_theme')||'light';
        document.documentElement.setAttribute('data-bs-theme',t);
    })();
    </script>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* 관리자 공통 스타일 */
        :root {
            --admin-sidebar-width: 280px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif;
            font-size: 14px;
        }

        /* 상단 네비바 */
        .admin-topbar {
            height: 50px;
            z-index: 1030;
        }

        .admin-topbar .navbar-brand {
            font-weight: 700;
            font-size: 16px;
        }

        /* 사이드바 */
        .admin-sidebar {
            position: fixed;
            top: 50px;
            left: 0;
            bottom: 0;
            width: var(--admin-sidebar-width);
            overflow-y: auto;
            z-index: 1020;
            transition: transform 0.3s;
        }

        .admin-sidebar .nav-link {
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 2px 8px;
            color: var(--bs-body-color);
        }

        .admin-sidebar .nav-link:hover {
            background: var(--bs-tertiary-bg);
        }

        .admin-sidebar .nav-link.active {
            background: var(--bs-primary);
            color: #fff;
        }

        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .admin-sidebar .admin-menu-row {
            display: flex;
            align-items: center;
            gap: 2px;
            margin: 2px 8px;
        }

        .admin-sidebar .admin-menu-row .nav-link {
            flex: 1;
            margin: 0;
        }

        .admin-sidebar .admin-menu-favorite {
            width: 24px;
            height: 24px;
            border: 0;
            background: transparent;
            color: var(--bs-secondary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 0;
            flex: 0 0 24px;
        }

        .admin-sidebar .admin-menu-favorite:hover {
            background: var(--bs-tertiary-bg);
            color: #f59f00;
        }

        .admin-sidebar .admin-menu-favorite.is-favorite {
            color: #f59f00;
        }

        .admin-sidebar .admin-menu-favorite i {
            width: auto;
            margin: 0;
            font-size: 13px;
        }

        /* 메인 콘텐츠 */
        /* 사이드바 collapse 초기 로드 시 애니메이션 방지 */
        .admin-sidebar .collapse { transition: none !important; }
        .admin-sidebar .collapsing { transition: none !important; height: auto !important; }
        /* 그룹 chevron: 열림/닫힘(aria-expanded)에 따라 회전 — 클릭으로 토글해도 항상 일치 */
        .admin-sidebar .nav-link .bi-chevron-down { transition: transform .2s; }
        .admin-sidebar .nav-link[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
        
        .admin-main {
            margin-left: var(--admin-sidebar-width);
            padding: 20px 24px;
            min-height: calc(100vh - 50px);
            width: calc(100% - var(--admin-sidebar-width));
        }

        /* 콘텐츠 전체 너비 사용 — max-width 제한 제거 */
        .admin-main > .card,
        .admin-main > .table-responsive,
        .admin-main > form,
        .admin-main > div {
            max-width: none;
        }

        /* 텍스트/폼 왼쪽 정렬 기본 */
        .admin-main .card-body { text-align: left; }
        .admin-main h1, .admin-main h2, .admin-main h3,
        .admin-main h4, .admin-main h5, .admin-main h6 { text-align: left; }

        /* 버튼 그룹 한 줄 유지 */
        .admin-main .btn-toolbar,
        .admin-main .card-header .d-flex { flex-wrap: nowrap; gap: 6px; }
        .admin-main .card-header .d-flex .btn { white-space: nowrap; }

        /* QA·정적 HTML 리포트 iframe (SPA 우측 패널) (한글 주석) */
        .admin-main .admin-qa-report-wrap {
            max-width: none !important;
        }
        .admin-qa-report-frame {
            width: 100%;
            min-height: calc(100vh - 120px);
            border: 0;
            display: block;
            background: var(--bs-body-bg);
        }

        /* 페이지 기능 설명 배너 */
        .page-desc-banner {
            padding: 14px 24px;
            margin: -24px -32px 20px -32px;
            background: var(--bs-tertiary-bg);
            border-bottom: 1px solid var(--bs-border-color);
        }
        .page-desc-banner h5 { font-size: 15px; font-weight: 700; }

        /* 페이지 제목 */
        .admin-main > h4,
        .admin-main > h5,
        .admin-main > .d-flex:first-child {
            margin-bottom: 20px !important;
        }

        /* 카드 내부 여백 */
        .card-body {
            padding: 16px 20px;
        }

        .card-header {
            padding: 12px 20px;
        }

        /* 테이블 표준 스타일 */
        .admin-table, .table {
            font-size: 13px;
        }

        .admin-table th, .table th {
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
            padding: 10px 12px;
        }

        .admin-table td, .table td {
            vertical-align: middle;
            padding: 10px 12px;
        }

        .admin-table .form-check-input {
            cursor: pointer;
        }

        /* 테이블 행 간격 */
        .table > tbody > tr {
            border-bottom: 1px solid var(--bs-border-color);
        }

        .table > tbody > tr:hover {
            background: var(--bs-tertiary-bg);
        }

        /* 검색바 + 행수 컨트롤 */
        .admin-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .admin-toolbar .search-box {
            max-width: 320px;
        }

        /* 빈 상태 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--bs-secondary-color);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        /* 배지 */
        .badge-active { background: #198754; }
        .badge-inactive { background: #dc3545; }

        /* 모바일: 사이드바 숨김 */
        @media (max-width: 991.98px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
                padding: 16px;
                width: 100%;
            }
            .admin-main .card-header .d-flex { flex-wrap: wrap; }
        }

        /* ============================= */
        /* 다크모드 전체 오버라이드      */
        /* ============================= */
        [data-bs-theme="dark"] .admin-topbar {
            border-bottom: 1px solid var(--bs-border-color);
        }

        [data-bs-theme="dark"] .admin-sidebar {
            border-right: 1px solid var(--bs-border-color);
        }

        /* 테이블 헤더 */
        [data-bs-theme="dark"] .table-light,
        [data-bs-theme="dark"] .table-light th,
        [data-bs-theme="dark"] .table-light td,
        [data-bs-theme="dark"] thead.table-light th {
            background-color: #2b3035 !important;
            color: #dee2e6 !important;
            border-color: var(--bs-border-color) !important;
        }
        [data-bs-theme="dark"] tr.table-light td {
            background-color: #2b3035 !important;
            color: #dee2e6 !important;
        }

        /* bg-white → 다크 배경 */
        [data-bs-theme="dark"] .bg-white {
            background-color: var(--bs-body-bg) !important;
            color: var(--bs-body-color) !important;
        }

        /* bg-light → 다크 배경 */
        [data-bs-theme="dark"] .bg-light {
            background-color: #2b3035 !important;
            color: var(--bs-body-color) !important;
        }

        /* text-dark in dark mode */
        [data-bs-theme="dark"] .text-dark {
            color: var(--bs-body-color) !important;
        }

        /* 카드 헤더 bg-white */
        [data-bs-theme="dark"] .card-header.bg-white,
        [data-bs-theme="dark"] .card-header {
            background-color: var(--bs-body-bg) !important;
            border-bottom-color: var(--bs-border-color) !important;
        }

        /* 하드코딩된 흰색/밝은 배경 인라인 스타일 오버라이드 */
        [data-bs-theme="dark"] .section-head.bg-white,
        [data-bs-theme="dark"] .section-head {
            background-color: var(--bs-body-bg) !important;
            color: var(--bs-body-color) !important;
        }

        /* 모달 내부 밝은 배경 */
        [data-bs-theme="dark"] .modal-body[style*="background:#f0f0f0"],
        [data-bs-theme="dark"] .modal-body[style*="background: #f0f0f0"] {
            background-color: #2b3035 !important;
        }

        /* SVG 프리뷰/피커 */
        [data-bs-theme="dark"] .svg-preview,
        [data-bs-theme="dark"] .svg-thumb,
        [data-bs-theme="dark"] .svg-grid-item,
        [data-bs-theme="dark"] .svg-pick-item,
        [data-bs-theme="dark"] .svg-pick-none {
            background-color: #2b3035 !important;
            border-color: var(--bs-border-color) !important;
        }

        /* 이모지 피커 */
        [data-bs-theme="dark"] .emoji-picker {
            background-color: #2b3035 !important;
            border-color: var(--bs-border-color) !important;
        }
        [data-bs-theme="dark"] .emoji-tabs {
            background-color: #212529 !important;
        }
        [data-bs-theme="dark"] .emoji-tab.active {
            background-color: #2b3035 !important;
            color: var(--bs-body-color) !important;
        }

        /* SVG 인라인 피커 */
        [data-bs-theme="dark"] .svg-inline-picker {
            background-color: #2b3035 !important;
        }

        /* 뱃지 bg-white */
        [data-bs-theme="dark"] .badge.bg-white {
            background-color: #343a40 !important;
            color: var(--bs-body-color) !important;
        }

        /* 필드 테이블 th */
        [data-bs-theme="dark"] .field-table th {
            background-color: #2b3035 !important;
            color: #dee2e6 !important;
        }

        /* 상태 색상 블록 - 어두운 버전 */
        [data-bs-theme="dark"] [style*="background:#fffdf5"],
        [data-bs-theme="dark"] [style*="background: #fffdf5"] {
            background-color: #2d2a1f !important;
        }
        [data-bs-theme="dark"] [style*="background:#fff8e1"],
        [data-bs-theme="dark"] [style*="background: #fff8e1"] {
            background-color: #332d1a !important;
        }
        [data-bs-theme="dark"] [style*="background:#fff5f5"],
        [data-bs-theme="dark"] [style*="background: #fff5f5"] {
            background-color: #2d1f1f !important;
        }

        /* TOC 링크 */
        [data-bs-theme="dark"] .toc a {
            background-color: #343a40 !important;
            color: #dee2e6 !important;
        }
        [data-bs-theme="dark"] .toc a:hover,
        [data-bs-theme="dark"] .toc a.active {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        /* 프로그레스 배경 */
        [data-bs-theme="dark"] .progress {
            background-color: #343a40 !important;
        }

        /* 폼 컨트롤 */
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2b3035;
            border-color: var(--bs-border-color);
            color: var(--bs-body-color);
        }

        /* Bootstrap 테이블 상태 색상 - 다크모드 */
        [data-bs-theme="dark"] .table-info,
        [data-bs-theme="dark"] .table-info td,
        [data-bs-theme="dark"] .table-info th {
            background-color: #1a2d3d !important;
            color: #6edff6 !important;
        }
        [data-bs-theme="dark"] .table-success,
        [data-bs-theme="dark"] .table-success td,
        [data-bs-theme="dark"] .table-success th {
            background-color: #1a3a2a !important;
            color: #75b798 !important;
        }
        [data-bs-theme="dark"] .table-warning,
        [data-bs-theme="dark"] .table-warning td,
        [data-bs-theme="dark"] .table-warning th {
            background-color: #332d1a !important;
            color: #ffda6a !important;
        }
        [data-bs-theme="dark"] .table-danger,
        [data-bs-theme="dark"] .table-danger td,
        [data-bs-theme="dark"] .table-danger th {
            background-color: #2c1a1a !important;
            color: #ea868f !important;
        }

        /* 인라인 bg 오버라이드용 유틸리티 */
        [data-bs-theme="dark"] .dark-aware-bg {
            background-color: var(--bs-body-bg) !important;
        }

        /* 추가 페이지별 스타일 슬롯 */
        
        .admin-logo {
            transition: filter 0.3s;
        }
        [data-bs-theme="dark"] .admin-logo {
            filter: invert(1) brightness(2);
        }
        #themeToggle {
            min-width: 68px;
        }

        <?php if (isset($admin_extra_css)) echo $admin_extra_css; ?>
    </style>
</head>
<body>

<!-- 상단 네비바 -->
<nav class="navbar navbar-expand admin-topbar fixed-top bg-body-tertiary border-bottom px-3">
    <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
        <img src="assets/images/seoul_beauty_logo.png" alt="<?php echo site_name(); ?>" class="admin-logo" style="height:32px;width:32px;object-fit:contain;border-radius:6px;">
        <span><?php echo site_name(); ?> Admin</span>
    </a>
    <div class="d-flex gap-1 ms-2">
        <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="sbExpandAll()" title="전체 펼치기"><i class="bi bi-chevron-bar-expand"></i></button>
        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="sbCollapseAll()" title="전체 접기"><i class="bi bi-chevron-bar-contract"></i></button>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <!-- 알림 벨 -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative" id="notifBellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="알림">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifUnreadBadge" style="font-size:10px;display:none;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" id="notifDropdown" style="width:360px;max-height:440px;overflow:hidden;">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong style="font-size:13px;"><i class="bi bi-bell me-1"></i>알림</strong>
                    <button class="btn btn-sm btn-link text-decoration-none p-0" id="notifReadAllBtn" style="font-size:12px;">모두 읽음</button>
                </div>
                <div id="notifList" style="max-height:340px;overflow-y:auto;">
                    <div class="text-center text-muted py-4" style="font-size:13px;">
                        <i class="bi bi-bell-slash d-block mb-2" style="font-size:24px;"></i>알림이 없습니다
                    </div>
                </div>
                <div class="border-top text-center py-2 d-flex justify-content-center gap-3">
                    <a href="admin_notifications.php" style="font-size:12px;text-decoration:none;font-weight:600;">전체 알림 보기 &rarr;</a>
                    <?php if (!function_exists('check_permission') || check_permission('admin_accounts')): ?>
                    <a href="admin_admins.php#notif-settings" style="font-size:12px;text-decoration:none;">알림 수신 설정 &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a href="admin_mail.php" class="btn btn-sm btn-outline-secondary position-relative" title="메일">
            <i class="bi bi-envelope"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="mailUnreadBadge" style="font-size:10px;display:none;">0</span>
        </a>
        <a href="index.php" class="btn btn-sm btn-outline-secondary" target="_blank" title="사이트 보기">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <!-- 알림 벨 -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="dropdown" title="알림" id="notifBell">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="display:none;font-size:10px;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow" style="width:360px;max-height:400px;overflow-y:auto;" id="notifBadgeDropdown">
                <h6 class="dropdown-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bell me-1"></i>알림</span>
                    <a href="admin_dev_feedback.php" class="text-muted" style="font-size:11px;">전체보기</a>
                </h6>
                <div id="notifBadgeList"><div class="text-center text-muted py-3" style="font-size:13px;">알림이 없습니다</div></div>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-warning" id="clearCacheBtn" title="캐시 초기화">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" id="themeToggle" title="주간/야간 모드">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
            <span class="d-none d-md-inline" id="themeLabel" style="font-size:12px;">야간</span>
        </button>
        <?php
        // 표시 이름과 역할 라벨이 동일하면 배지 중복 방지 (한글 주석)
        $cur_admin = function_exists('get_current_admin') ? get_current_admin() : null;
        if ($cur_admin) {
            $dispName = trim((string)($cur_admin['name'] ?? ''));
            $roleLbl = trim((string)(function_exists('get_role_label') ? get_role_label($cur_admin['role']) : ($cur_admin['role'] ?? '')));
            if ($dispName !== '' && $roleLbl !== '' && strcasecmp($dispName, $roleLbl) === 0) {
                echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($dispName) . '</span>';
            } elseif ($dispName !== '') {
                echo '<span class="badge bg-info text-dark me-1">' . htmlspecialchars($dispName) . '</span>';
                if ($roleLbl !== '' && strcasecmp($dispName, $roleLbl) !== 0) {
                    echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($roleLbl) . '</span>';
                }
            } elseif ($roleLbl !== '') {
                echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($roleLbl) . '</span>';
            }
        }
        ?>
        <a href="/hairwang/app/dashboard.php?logout=1" class="btn btn-sm btn-outline-danger" title="로그아웃">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
    <div style="padding:0 12px 4px;">
        <a href="/hairwang/app/_archive/full_test_report_20260308.html" target="_blank"
           style="display:flex; align-items:center; gap:8px; padding:9px 12px; border-radius:8px; font-size:12.5px; font-weight:700; color:#fff; text-decoration:none; background:linear-gradient(135deg,#0ea5e9,#06b6d4); transition:all 0.2s; box-shadow:0 2px 6px rgba(14,165,233,0.25);"
           onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <i class="bi bi-clipboard2-check" style="font-size:15px;"></i>
            Full Test Report
            <i class="bi bi-box-arrow-up-right" style="font-size:10px; margin-left:auto; opacity:0.7;"></i>
        </a>
    </div>

</nav>

<?php
// 예약 관리 배지: 접수된 예약 요청(대기중) 개수 — 병원 격리(super_admin=전체) 반영
$sidebar_bookings_pending = 0;
// 후기 관리 배지: 승인 대기(pending) 후기 개수 — admin_reviews.php 통계와 동일(전역)
$sidebar_reviews_pending = 0;
// 병원 등록 신청 배지: 승인 대기(pending) 병원 신청 개수
$sidebar_hospitals_pending = 0;
if (isset($connect) && $connect) {
    if (function_exists('get_strict_admin_hospital_ids')) {
        $__sidebar_hids = get_strict_admin_hospital_ids($connect);
        $__b_hin = $__sidebar_hids === null
            ? null
            : (empty($__sidebar_hids) ? '0' : implode(',', array_map('intval', $__sidebar_hids)));
        $__b_where = ($__b_hin !== null) ? " AND hospital_id IN ({$__b_hin})" : "";
        $__b_res = mysqli_query($connect, "SELECT COUNT(*) AS c FROM gangnamunni_bookings WHERE status='pending'{$__b_where}");
        if ($__b_res) { $sidebar_bookings_pending = (int)(mysqli_fetch_assoc($__b_res)['c'] ?? 0); }

        $__r_where = ($__b_hin !== null) ? " AND hospital_id IN ({$__b_hin})" : "";
        $__r_res = mysqli_query($connect, "SELECT COUNT(*) AS c FROM hospital_reviews WHERE review_status='pending'{$__r_where}");
        if ($__r_res) { $sidebar_reviews_pending = (int)(mysqli_fetch_assoc($__r_res)['c'] ?? 0); }
    } else {
        $__r_res = mysqli_query($connect, "SELECT COUNT(*) AS c FROM hospital_reviews WHERE review_status='pending'");
        if ($__r_res) { $sidebar_reviews_pending = (int)(mysqli_fetch_assoc($__r_res)['c'] ?? 0); }
    }
    // 병원 등록 신청(승인 대기) 개수 — 전역(신규 병원은 담당 관리자 미배정이라 격리 없음)
    $__h_res = mysqli_query($connect, "SELECT COUNT(*) AS c FROM gangnamunni_hospitals WHERE status='pending'");
    if ($__h_res) { $sidebar_hospitals_pending = (int)(mysqli_fetch_assoc($__h_res)['c'] ?? 0); }
}
?>
<!-- 사이드바 -->
<nav class="admin-sidebar bg-body-tertiary border-end pt-3" id="adminSidebar">


    <ul class="nav flex-column">
        <?php foreach ($admin_menus as $menu):
            // 구분선
            if (isset($menu['type']) && $menu['type'] === 'divider') {
                echo '<li class="nav-item"><hr class="my-2 mx-3" style="border-color:var(--bs-border-color);opacity:0.3"></li>';
                continue;
            }
            // 사이드바 비노출 항목 (직접 URL 접근만 허용, 네비게이션 whitelist용)
            if (!empty($menu['hidden'])) continue;
            // 권한 필터링 (그룹은 children 기반 필터링)
            if (function_exists('check_permission')) {
                if (isset($menu['type']) && $menu['type'] === 'group') {
                    $has_child_perm = false;
                    foreach ($menu['children'] as $ch) {
                        $child_key_for_perm = (string)($ch['key'] ?? '');
                        if (empty($menu['favorite_group']) && in_array($child_key_for_perm, $admin_favorite_menu_keys, true)) continue;
                        if (check_permission($child_key_for_perm)) { $has_child_perm = true; break; }
                    }
                    if (!$has_child_perm) continue;
                } else {
                    if (!check_permission($menu['key'])) continue;
                }
            }

            // 그룹 메뉴 (확장/축소)
            if (isset($menu['type']) && $menu['type'] === 'group'):
                $collapse_id = 'grp_' . $menu['key']; // 분기에서 사용하므로 먼저 정의 (한글 주석)
                $group_keys = array_map(static function ($child) {
                    return $child['active_key'] ?? $child['key'];
                }, $menu['children']);
                // null = 쿠키 없음 → 모든 그룹 열림
                // array = 쿠키 있음 → 저장된 상태 그대로 유지
                if ($_sb_open_groups === null) {
                    $is_group_active = true; // 첫 방문: 전부 열림
                } else {
                    // 쿠키에 저장된 상태 유지
                    $is_group_active = in_array($collapse_id, $_sb_open_groups);
                    // 현재 페이지가 이 그룹에 속하면 열기 (쿠키에 없어도)
                    if (in_array($admin_current, $group_keys) && !in_array($collapse_id, $_sb_open_groups)) {
                        $is_group_active = true;
                    }
                }
        ?>
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center <?php echo $is_group_active ? 'fw-bold' : ''; ?>"
               data-bs-toggle="collapse" href="#<?php echo $collapse_id; ?>" role="button"
               aria-expanded="<?php echo $is_group_active ? 'true' : 'false'; ?>">
                <span><i class="bi <?php echo $menu['icon']; ?>"></i> <?php echo $menu['label']; ?></span>
                <i class="bi bi-chevron-down" style="font-size:10px;"></i>
            </a>
            <div class="collapse <?php echo $is_group_active ? 'show' : ''; ?>" id="<?php echo $collapse_id; ?>">
                <ul class="nav flex-column ms-3">
                    <?php foreach ($menu['children'] as $child):
                        if (function_exists('check_permission') && !check_permission($child['key'])) continue;
                        $child_key = (string)($child['key'] ?? '');
                        $is_favorite = in_array($child_key, $admin_favorite_menu_keys, true);
                        if (empty($menu['favorite_group']) && $is_favorite) continue;
                    ?>
                    <li class="nav-item">
                        <div class="admin-menu-row">
                            <button type="button"
                                    class="admin-menu-favorite <?php echo $is_favorite ? 'is-favorite' : ''; ?>"
                                    data-menu-key="<?php echo htmlspecialchars($child_key, ENT_QUOTES); ?>"
                                    title="<?php echo $is_favorite ? '주요 메뉴에서 제거' : '주요 메뉴에 추가'; ?>"
                                    aria-label="<?php echo $is_favorite ? '주요 메뉴에서 제거' : '주요 메뉴에 추가'; ?>">
                                <i class="bi <?php echo $is_favorite ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                            </button>
                            <a class="nav-link py-1 <?php echo ($admin_current === ($child['active_key'] ?? $child['key'])) ? 'active' : ''; ?>"
                               href="<?php echo (strpos($child['url'], 'http') === 0) ? $child['url'] : (defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/hairwang/app/') . $child['url']; ?>"<?php echo isset($child['target']) ? ' target="'.$child['target'].'"' : ''; ?>
                               style="font-size:12px;">
                                <i class="bi <?php echo $child['icon']; ?>"></i>
                                <?php echo $child['label']; ?>
                                <?php if ($child['key'] === 'bookings' && $sidebar_bookings_pending > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1" style="font-size:10px;"><?php echo $sidebar_bookings_pending > 99 ? '99+' : $sidebar_bookings_pending; ?></span>
                                <?php endif; ?>
                                <?php if ($child['key'] === 'reviews' && $sidebar_reviews_pending > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1" style="font-size:10px;"><?php echo $sidebar_reviews_pending > 99 ? '99+' : $sidebar_reviews_pending; ?></span>
                                <?php endif; ?>
                                <?php /* 입점 신청 배지는 심사 화면(admin_requested_hospitals.php)에만 — 병원 관리 메뉴에는 띄우지 않는다 */ ?>
                                <?php if ($child['key'] === 'hospital_request_review' && $sidebar_hospitals_pending > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1" style="font-size:10px;"><?php echo $sidebar_hospitals_pending > 99 ? '99+' : $sidebar_hospitals_pending; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php else: ?>
        <li class="nav-item">
            <a class="nav-link <?php echo ($admin_current === $menu['key']) ? 'active' : ''; ?>"
               href="<?php echo (strpos($menu['url'], 'http') === 0) ? $menu['url'] : (defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/hairwang/app/') . $menu['url']; ?>"<?php echo isset($menu['target']) ? ' target="'.$menu['target'].'"' : ''; ?>>
                <i class="bi <?php echo $menu['icon']; ?>"></i>
                <?php echo $menu['label']; ?>
            </a>
        </li>
        <?php endif; endforeach; ?>
    </ul>

    <!-- PG Test Report — 사이드바 스크롤 영역 최하단 (한글 주석) -->
    <div style="padding:8px 12px 4px;">
        <a href="/hairwang/app/pg_test_report_20260308.html" target="_blank"
           style="display:flex; align-items:center; gap:8px; padding:9px 12px; border-radius:8px; font-size:12.5px; font-weight:700; color:#fff; text-decoration:none; background:linear-gradient(135deg,#059669,#10b981); transition:all 0.2s; box-shadow:0 2px 6px rgba(5,150,105,0.25);"
           onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <i class="bi bi-credit-card-2-front" style="font-size:15px;"></i>
            PG Test Report
            <i class="bi bi-box-arrow-up-right" style="font-size:10px; margin-left:auto; opacity:0.7;"></i>
        </a>
    </div>

    <!-- 하단 여백 -->
    <div style="height:16px;"></div>




    <div style="height:40px;"></div>

<!-- 사이드바: 그룹 토글 시 쿠키 저장 + 로드 후 애니메이션 복원 -->
<script>
(function(){
    // 전체 확장 (footer localStorage 모드와 동기화 — hidden.bs.collapse 가 다시 열지 않도록) (한글 주석)
    window.sbExpandAll = function() {
        try {
            var _sbAllKeys = []; // grp_* 에서 추출한 그룹 키 목록 (한글 주석)
            document.querySelectorAll('.admin-sidebar a[data-bs-toggle="collapse"]').forEach(function(link) {
                var href = link.getAttribute('href') || '';
                if (href.charAt(0) !== '#') return;
                var cid = href.substring(1);
                if (cid.indexOf('grp_') === 0) _sbAllKeys.push(cid.substring(4));
            });
            localStorage.setItem('admin_sidebar_expand_mode', 'custom');
            localStorage.setItem('admin_sidebar_open_groups', JSON.stringify(_sbAllKeys));
        } catch (e) {}
        document.querySelectorAll('.admin-sidebar .collapse').forEach(function(el) {
            if (!el.classList.contains('show')) {
                el.classList.add('show');
                var t = document.querySelector('[href="#' + el.id + '"]');
                if (t) t.setAttribute('aria-expanded', 'true');
            }
        });
        saveSidebarState();
    };
    // 전체 축소 (expand_all 모드 유지 로직이 닫힘을 되돌리므로 먼저 collapse_all 저장) (한글 주석)
    window.sbCollapseAll = function() {
        try {
            localStorage.setItem('admin_sidebar_expand_mode', 'collapse_all');
            localStorage.setItem('admin_sidebar_open_groups', '[]');
        } catch (e) {}
        document.querySelectorAll('.admin-sidebar .collapse.show').forEach(function(el) {
            el.classList.remove('show');
            var t = document.querySelector('[href="#' + el.id + '"]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
        saveSidebarState();
    };

    // 쿠키 저장 함수
    function saveSidebarState() {
        var open = [];
        document.querySelectorAll('.admin-sidebar .collapse.show').forEach(function(el) {
            if (el.id) open.push(el.id);
        });
        var val = open.join(',');
        document.cookie = 'sb_open_groups=' + val + ';path=/hairwang/app/;max-age=86400';
        document.cookie = 'sb_open_groups=' + val + ';path=/;max-age=86400';
        try { sessionStorage.setItem('sb_open_groups', val); } catch(e) {}
        document.cookie = 'sb_ver=<?php echo $_sb_cookie_ver; ?>;path=/hairwang/app/;max-age=86400';
        document.cookie = 'sb_ver=<?php echo $_sb_cookie_ver; ?>;path=/;max-age=86400';
        return open;
    }
    
    // 초기: 쿠키 없으면 현재 열린 그룹 저장
    if (!document.cookie.includes('sb_open_groups')) {
        saveSidebarState();
    }
    
    // 그룹 토글 시 저장
    document.querySelectorAll('.admin-sidebar [data-bs-toggle="collapse"]').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            setTimeout(saveSidebarState, 400);
        });
    });
    
    // 서브메뉴 전환은 admin_footer.php 의 SPA(fetch)가 담당.
    // 여기서 preventDefault + location.href 를 쓰면 전체 리로드·좌측 흔들림이 난다. (한글 주석)

    document.querySelectorAll('.admin-menu-favorite').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var key = btn.getAttribute('data-menu-key') || '';
            if (!key || btn.disabled) return;
            btn.disabled = true;
            fetch('<?php echo defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/hairwang/app/'; ?>api_admin_menu_favorite.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({menu_key: key})
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (!data || !data.success) throw new Error((data && data.message) || 'failed');
                window.location.reload();
            }).catch(function() {
                btn.disabled = false;
                alert('저장에 실패했습니다. 잠시 후 다시 시도해 주세요.');
            });
        });
    });

    // 0.5초 후 collapse transition 을 켜던 코드는 제거함 — 그룹 펼침 시 좌측에 슬라이드 효과가 생겨 SPA 와 어긋남 (한글 주석)
})();
</script>

</nav>

<!-- 메인 콘텐츠 -->
<div class="admin-main" style="margin-top: 50px;">
<?php if (!empty($admin_page_description)): ?>
<div class="page-desc-banner">
    <h5 class="mb-1"><i class="bi <?php
        // 현재 페이지 아이콘 찾기
        $cur_icon = 'bi-info-circle';
        foreach ($admin_menus as $m) {
            if (isset($m['key']) && $m['key'] === $admin_current) { $cur_icon = $m['icon']; break; }
            if (isset($m['children'])) foreach ($m['children'] as $c) {
                if ($c['key'] === $admin_current) { $cur_icon = $c['icon']; break 2; }
            }
        }
        echo $cur_icon;
    ?>"></i> <?php echo htmlspecialchars($admin_page_title); ?></h5>
    <p class="mb-0 text-body-secondary" style="font-size:13px;"><?php echo htmlspecialchars($admin_page_description); ?></p>
</div>
<?php endif; ?>

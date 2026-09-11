<?php
/**
 * Seoul Beauty (서울미녀) - 개인정보처리방침 / Privacy Policy
 * 공개 페이지 (로그인 불필요)
 * 시행일: 2026년 3월 5일
 */
$page_title = '개인정보처리방침 | Seoul Beauty';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* 다크모드 anti-flash */
        html[data-bs-theme="dark"] { background-color: #212529; }
        body { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; line-height: 1.8; }
        .policy-section { margin-bottom: 2.5rem; }
        .policy-section h2 { font-size: 1.35rem; font-weight: 700; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--bs-primary); }
        .policy-section h3 { font-size: 1.1rem; font-weight: 600; color: var(--bs-secondary-color); margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .policy-header { background: linear-gradient(135deg, #6f42c1 0%, #0d6efd 100%); color: #fff; padding: 3rem 0; margin-bottom: 2rem; }
        .policy-header h1 { font-size: 2rem; font-weight: 800; }
        .info-table td, .info-table th { padding: 0.6rem 1rem; }
        .theme-toggle { cursor: pointer; border: none; background: transparent; font-size: 1.25rem; }
        .lang-toggle { font-size: 0.875rem; }
        .effective-date { font-size: 0.95rem; color: var(--bs-secondary-color); }
        footer { border-top: 1px solid var(--bs-border-color); padding: 1.5rem 0; margin-top: 3rem; }
        @media (max-width: 768px) {
            .policy-header h1 { font-size: 1.5rem; }
            .policy-header { padding: 2rem 0; }
        }
    </style>
    <script>
        // 다크모드 anti-flash: localStorage 기반 즉시 적용
        (function() {
            var t = localStorage.getItem('admin_theme') || localStorage.getItem('pp_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body>
    <!-- 상단 네비게이션 -->
    <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/seoul_beauty_logo.png" alt="Seoul Beauty" width="32" height="32" class="me-2">
                <span class="fw-bold">Seoul Beauty</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <button class="lang-toggle btn btn-sm btn-outline-secondary" onclick="toggleLang()">
                    <span id="langBtn">English</span>
                </button>
                <button class="theme-toggle" onclick="toggleTheme()" title="다크모드 전환" aria-label="Toggle dark mode">
                    <span id="themeIcon">🌙</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- 헤더 -->
    <div class="policy-header text-center">
        <div class="container">
            <h1>
                <span class="ko">개인정보처리방침</span>
                <span class="en d-none">Privacy Policy</span>
            </h1>
            <p class="effective-date text-white-50 mb-0">
                <span class="ko">시행일: 2026년 3월 5일</span>
                <span class="en d-none">Effective Date: March 5, 2026</span>
            </p>
        </div>
    </div>

    <!-- 본문 -->
    <div class="container" style="max-width: 860px;">

        <!-- 서문 -->
        <div class="policy-section">
            <p class="ko">
                서울미녀(Seoul Beauty, 이하 "회사")는 「개인정보 보호법」 제30조에 따라 이용자의 개인정보를 보호하고 이와 관련한 고충을 신속하고 원활하게 처리할 수 있도록 다음과 같이 개인정보처리방침을 수립·공개합니다.
            </p>
            <p class="en d-none">
                Seoul Beauty (hereinafter "the Company") establishes and discloses the following Privacy Policy in accordance with Article 30 of the Personal Information Protection Act to protect users' personal information and to promptly handle related complaints.
            </p>
        </div>

        <!-- 1. 수집하는 개인정보 항목 -->
        <div class="policy-section">
            <h2>
                <span class="ko">1. 수집하는 개인정보 항목</span>
                <span class="en d-none">1. Personal Information Collected</span>
            </h2>
            <p class="ko">회사는 서비스 제공을 위해 다음과 같은 개인정보를 수집합니다.</p>
            <p class="en d-none">The Company collects the following personal information for service provision.</p>

            <h3>
                <span class="ko">필수 항목</span>
                <span class="en d-none">Required Information</span>
            </h3>
            <table class="table table-bordered info-table">
                <thead class="table-light">
                    <tr>
                        <th class="ko">구분</th><th class="ko">수집 항목</th>
                        <th class="en d-none">Category</th><th class="en d-none">Items</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="ko">회원가입</span>
                            <span class="en d-none">Registration</span>
                        </td>
                        <td>
                            <span class="ko">이름, 이메일 주소, 전화번호, 비밀번호(암호화 저장)</span>
                            <span class="en d-none">Name, email address, phone number, password (encrypted)</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">병원 예약</span>
                            <span class="en d-none">Hospital Booking</span>
                        </td>
                        <td>
                            <span class="ko">이름, 전화번호, 희망 시술, 예약 일시, 메모</span>
                            <span class="en d-none">Name, phone number, desired treatment, appointment date/time, memo</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">상담 신청</span>
                            <span class="en d-none">Consultation</span>
                        </td>
                        <td>
                            <span class="ko">이름, 전화번호, 상담 내용</span>
                            <span class="en d-none">Name, phone number, consultation details</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>
                <span class="ko">자동 수집 항목</span>
                <span class="en d-none">Automatically Collected Information</span>
            </h3>
            <ul>
                <li>
                    <span class="ko">기기정보 (기기 모델, OS 버전, 앱 버전)</span>
                    <span class="en d-none">Device information (device model, OS version, app version)</span>
                </li>
                <li>
                    <span class="ko">FCM 푸시 토큰 (알림 발송용)</span>
                    <span class="en d-none">FCM push token (for push notifications)</span>
                </li>
                <li>
                    <span class="ko">접속 IP, 접속 일시, 서비스 이용 기록</span>
                    <span class="en d-none">Access IP, access time, service usage logs</span>
                </li>
                <li>
                    <span class="ko">쿠키 (로그인 세션 유지, SSO 통합 인증용)</span>
                    <span class="en d-none">Cookies (session management, SSO authentication)</span>
                </li>
            </ul>
        </div>

        <!-- 2. 개인정보 수집 목적 -->
        <div class="policy-section">
            <h2>
                <span class="ko">2. 개인정보의 수집 및 이용 목적</span>
                <span class="en d-none">2. Purpose of Collection and Use</span>
            </h2>
            <table class="table table-bordered info-table">
                <thead class="table-light">
                    <tr>
                        <th class="ko">목적</th><th class="ko">상세</th>
                        <th class="en d-none">Purpose</th><th class="en d-none">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="ko">병원 예약 서비스</span>
                            <span class="en d-none">Hospital Booking</span>
                        </td>
                        <td>
                            <span class="ko">예약 접수, 예약 확인/변경/취소, 병원 전달</span>
                            <span class="en d-none">Booking registration, confirmation/modification/cancellation, hospital delivery</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">상담 서비스</span>
                            <span class="en d-none">Consultation</span>
                        </td>
                        <td>
                            <span class="ko">상담 신청 처리, 병원 연결, 결과 회신</span>
                            <span class="en d-none">Consultation processing, hospital connection, result response</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">알림 발송</span>
                            <span class="en d-none">Notifications</span>
                        </td>
                        <td>
                            <span class="ko">예약 확인, 이벤트 안내, 시스템 공지 (FCM 푸시, 인앱 알림)</span>
                            <span class="en d-none">Booking confirmation, event announcements, system notices (FCM push, in-app)</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">서비스 개선</span>
                            <span class="en d-none">Service Improvement</span>
                        </td>
                        <td>
                            <span class="ko">이용 통계 분석, 서비스 품질 향상, 오류 대응</span>
                            <span class="en d-none">Usage statistics, quality improvement, error handling</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">회원 관리</span>
                            <span class="en d-none">Member Management</span>
                        </td>
                        <td>
                            <span class="ko">본인 확인, 부정 이용 방지, 분쟁 조정</span>
                            <span class="en d-none">Identity verification, fraud prevention, dispute resolution</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 3. 개인정보 보유기간 -->
        <div class="policy-section">
            <h2>
                <span class="ko">3. 개인정보의 보유 및 이용 기간</span>
                <span class="en d-none">3. Retention Period</span>
            </h2>
            <p class="ko">
                회사는 개인정보 수집 및 이용 목적이 달성된 후에는 해당 정보를 지체 없이 파기합니다. 단, 관계 법령에 따라 보존할 필요가 있는 경우 해당 기간 동안 보관합니다.
            </p>
            <p class="en d-none">
                The Company destroys personal information without delay after the purpose of collection and use has been achieved. However, if preservation is required by relevant laws, the information is retained for the applicable period.
            </p>
            <table class="table table-bordered info-table">
                <thead class="table-light">
                    <tr>
                        <th class="ko">구분</th><th class="ko">보유 기간</th>
                        <th class="en d-none">Category</th><th class="en d-none">Retention Period</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="ko">회원 정보</span>
                            <span class="en d-none">Member Information</span>
                        </td>
                        <td>
                            <span class="ko">회원 탈퇴 후 <strong>30일</strong> 이내 파기</span>
                            <span class="en d-none">Destroyed within <strong>30 days</strong> after account deletion</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">예약 기록</span>
                            <span class="en d-none">Booking Records</span>
                        </td>
                        <td>
                            <span class="ko">예약 완료 후 <strong>1년</strong> (전자상거래법)</span>
                            <span class="en d-none"><strong>1 year</strong> after booking completion (E-Commerce Act)</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">접속 로그</span>
                            <span class="en d-none">Access Logs</span>
                        </td>
                        <td>
                            <span class="ko"><strong>3개월</strong> (통신비밀보호법)</span>
                            <span class="en d-none"><strong>3 months</strong> (Communications Privacy Act)</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">상담 기록</span>
                            <span class="en d-none">Consultation Records</span>
                        </td>
                        <td>
                            <span class="ko"><strong>3년</strong> (소비자 분쟁해결 기준)</span>
                            <span class="en d-none"><strong>3 years</strong> (Consumer Dispute Resolution)</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 4. 제3자 제공 -->
        <div class="policy-section">
            <h2>
                <span class="ko">4. 개인정보의 제3자 제공</span>
                <span class="en d-none">4. Provision to Third Parties</span>
            </h2>
            <p class="ko">
                회사는 원칙적으로 이용자의 동의 없이 개인정보를 제3자에게 제공하지 않습니다. 다만, 다음의 경우에는 예외로 합니다.
            </p>
            <p class="en d-none">
                The Company does not provide personal information to third parties without user consent, except in the following cases.
            </p>
            <table class="table table-bordered info-table">
                <thead class="table-light">
                    <tr>
                        <th class="ko">제공받는 자</th><th class="ko">제공 항목</th><th class="ko">제공 목적</th>
                        <th class="en d-none">Recipient</th><th class="en d-none">Items Provided</th><th class="en d-none">Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="ko">예약 병원</span>
                            <span class="en d-none">Booked Hospital</span>
                        </td>
                        <td>
                            <span class="ko">이름, 전화번호, 예약일시, 희망 시술</span>
                            <span class="en d-none">Name, phone, booking date/time, desired treatment</span>
                        </td>
                        <td>
                            <span class="ko">예약 처리 및 진료 준비</span>
                            <span class="en d-none">Booking processing and treatment preparation</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="ko">HMS 시스템 (InfyHMS)</span>
                            <span class="en d-none">HMS System (InfyHMS)</span>
                        </td>
                        <td>
                            <span class="ko">예약정보, 환자정보</span>
                            <span class="en d-none">Booking info, patient info</span>
                        </td>
                        <td>
                            <span class="ko">병원 관리 시스템 연동</span>
                            <span class="en d-none">Hospital management system integration</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="ko mt-3">
                <small class="text-body-secondary">※ 법령에 의한 경우, 수사 목적으로 법령에 정해진 절차와 방법에 따라 수사기관의 요구가 있는 경우에도 제공될 수 있습니다.</small>
            </p>
            <p class="en d-none mt-3">
                <small class="text-body-secondary">※ Information may also be provided when required by law or upon request by investigative agencies in accordance with legal procedures.</small>
            </p>
        </div>

        <!-- 5. 개인정보 보호책임자 -->
        <div class="policy-section">
            <h2>
                <span class="ko">5. 개인정보 보호책임자</span>
                <span class="en d-none">5. Privacy Officer</span>
            </h2>
            <p class="ko">회사는 개인정보 처리에 관한 업무를 총괄하고, 개인정보 처리와 관련한 이용자의 불만 처리 및 피해 구제를 위하여 아래와 같이 개인정보 보호책임자를 지정하고 있습니다.</p>
            <p class="en d-none">The Company designates a Privacy Officer to oversee personal information processing and handle user complaints and remedies.</p>
            <div class="card">
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <th style="width:140px;">
                                <span class="ko">회사명</span>
                                <span class="en d-none">Company</span>
                            </th>
                            <td>Seoul Beauty (서울미녀)</td>
                        </tr>
                        <tr>
                            <th>
                                <span class="ko">이메일</span>
                                <span class="en d-none">Email</span>
                            </th>
                            <td><a href="mailto:admin@seoulbeauty.app">admin@seoulbeauty.app</a></td>
                        </tr>
                        <tr>
                            <th>
                                <span class="ko">웹사이트</span>
                                <span class="en d-none">Website</span>
                            </th>
                            <td><a href="https://hms.seoulbeauty.app" target="_blank">https://hms.seoulbeauty.app</a></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- 6. 이용자 권리 -->
        <div class="policy-section">
            <h2>
                <span class="ko">6. 이용자의 권리 및 행사 방법</span>
                <span class="en d-none">6. User Rights and How to Exercise Them</span>
            </h2>
            <p class="ko">이용자는 언제든지 다음과 같은 권리를 행사할 수 있습니다.</p>
            <p class="en d-none">Users may exercise the following rights at any time.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold">
                                <span class="ko">열람 요구</span>
                                <span class="en d-none">Right to Access</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">수집된 개인정보의 열람을 요청할 수 있습니다.</span>
                                <span class="en d-none">You may request access to your collected personal information.</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold">
                                <span class="ko">정정 요구</span>
                                <span class="en d-none">Right to Correction</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">부정확한 개인정보의 정정을 요청할 수 있습니다.</span>
                                <span class="en d-none">You may request correction of inaccurate personal information.</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold">
                                <span class="ko">삭제 요구</span>
                                <span class="en d-none">Right to Deletion</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">개인정보의 삭제를 요청할 수 있습니다. 단, 법령에 따라 보존이 필요한 경우 삭제가 제한될 수 있습니다.</span>
                                <span class="en d-none">You may request deletion of your personal information. Deletion may be restricted when retention is required by law.</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold">
                                <span class="ko">동의 철회</span>
                                <span class="en d-none">Right to Withdraw Consent</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">개인정보 수집·이용에 대한 동의를 철회할 수 있습니다.</span>
                                <span class="en d-none">You may withdraw your consent to the collection and use of personal information.</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="ko mt-3">
                <small class="text-body-secondary">권리 행사는 이메일(<a href="mailto:admin@seoulbeauty.app">admin@seoulbeauty.app</a>)로 요청하실 수 있으며, 회사는 지체 없이 조치하겠습니다.</small>
            </p>
            <p class="en d-none mt-3">
                <small class="text-body-secondary">You may exercise your rights by contacting <a href="mailto:admin@seoulbeauty.app">admin@seoulbeauty.app</a>. The Company will act without delay.</small>
            </p>
        </div>

        <!-- 7. 쿠키 및 자동수집 -->
        <div class="policy-section">
            <h2>
                <span class="ko">7. 쿠키 및 자동 수집 장치</span>
                <span class="en d-none">7. Cookies and Automatic Data Collection</span>
            </h2>
            <p class="ko">회사는 다음과 같은 목적으로 쿠키 및 자동 수집 장치를 운용합니다.</p>
            <p class="en d-none">The Company operates cookies and automatic data collection devices for the following purposes.</p>
            <ul>
                <li>
                    <span class="ko"><strong>세션 쿠키</strong>: 로그인 상태 유지 및 SSO(Single Sign-On) 통합 인증</span>
                    <span class="en d-none"><strong>Session Cookies</strong>: Login state management and SSO authentication</span>
                </li>
                <li>
                    <span class="ko"><strong>테마 설정</strong>: 주간/야간 모드 설정 저장 (localStorage)</span>
                    <span class="en d-none"><strong>Theme Settings</strong>: Light/dark mode preference storage (localStorage)</span>
                </li>
                <li>
                    <span class="ko"><strong>FCM 토큰</strong>: Firebase Cloud Messaging을 통한 푸시 알림 발송을 위한 기기 토큰</span>
                    <span class="en d-none"><strong>FCM Token</strong>: Device token for push notifications via Firebase Cloud Messaging</span>
                </li>
                <li>
                    <span class="ko"><strong>기기 정보</strong>: 앱 오류 진단 및 서비스 최적화를 위한 기기 모델, OS 버전 정보</span>
                    <span class="en d-none"><strong>Device Info</strong>: Device model and OS version for error diagnosis and service optimization</span>
                </li>
            </ul>
            <p class="ko">
                <small class="text-body-secondary">이용자는 웹 브라우저 설정에서 쿠키를 거부할 수 있으나, 일부 서비스 이용이 제한될 수 있습니다.</small>
            </p>
            <p class="en d-none">
                <small class="text-body-secondary">Users may refuse cookies through browser settings, but some services may be limited.</small>
            </p>
        </div>

        <!-- 8. 안전성 확보조치 -->
        <div class="policy-section">
            <h2>
                <span class="ko">8. 개인정보의 안전성 확보조치</span>
                <span class="en d-none">8. Security Measures</span>
            </h2>
            <p class="ko">회사는 개인정보의 안전성 확보를 위해 다음과 같은 조치를 시행하고 있습니다.</p>
            <p class="en d-none">The Company implements the following measures to ensure the security of personal information.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold text-primary">
                                <span class="ko">SSL/TLS 암호화</span>
                                <span class="en d-none">SSL/TLS Encryption</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">모든 데이터 전송 시 HTTPS(SSL/TLS) 암호화 통신을 적용합니다.</span>
                                <span class="en d-none">All data transmissions are encrypted via HTTPS (SSL/TLS).</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold text-primary">
                                <span class="ko">AES-256 암호화</span>
                                <span class="en d-none">AES-256 Encryption</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">민감 정보(비밀번호, API 키 등)는 AES-256-CBC 알고리즘으로 암호화하여 저장합니다.</span>
                                <span class="en d-none">Sensitive information (passwords, API keys) is stored encrypted using AES-256-CBC algorithm.</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold text-primary">
                                <span class="ko">접근 권한 관리</span>
                                <span class="en d-none">Access Control</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">개인정보에 대한 접근 권한을 역할 기반(RBAC)으로 제한하고 관리합니다.</span>
                                <span class="en d-none">Access to personal information is restricted and managed through Role-Based Access Control (RBAC).</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <h6 class="card-title fw-bold text-primary">
                                <span class="ko">SQL Injection 방지</span>
                                <span class="en d-none">SQL Injection Prevention</span>
                            </h6>
                            <p class="card-text small mb-0">
                                <span class="ko">모든 데이터베이스 쿼리에 Prepared Statement를 적용하여 SQL 인젝션 공격을 방지합니다.</span>
                                <span class="en d-none">All database queries use Prepared Statements to prevent SQL injection attacks.</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. 시행일 -->
        <div class="policy-section">
            <h2>
                <span class="ko">9. 개인정보처리방침의 변경</span>
                <span class="en d-none">9. Changes to This Policy</span>
            </h2>
            <p class="ko">
                이 개인정보처리방침은 <strong>2026년 3월 5일</strong>부터 시행됩니다. 법령, 정책, 보안 기술의 변경에 따라 내용이 추가, 삭제, 수정될 수 있으며, 변경 시 시행 7일 전부터 앱 및 웹사이트를 통해 공지합니다.
            </p>
            <p class="en d-none">
                This Privacy Policy is effective as of <strong>March 5, 2026</strong>. It may be updated due to changes in laws, policies, or security technologies. Any changes will be announced through the app and website at least 7 days before implementation.
            </p>
        </div>

    </div>

    <!-- 푸터 -->
    <footer>
        <div class="container text-center">
            <p class="text-body-secondary mb-1">
                &copy; 2026 Seoul Beauty (서울미녀). All rights reserved.
            </p>
            <p class="text-body-secondary small mb-0">
                <a href="mailto:admin@seoulbeauty.app" class="text-decoration-none">admin@seoulbeauty.app</a>
                &nbsp;|&nbsp;
                <a href="https://hms.seoulbeauty.app" target="_blank" class="text-decoration-none">hms.seoulbeauty.app</a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 다크모드 토글
        function toggleTheme() {
            var html = document.documentElement;
            var current = html.getAttribute('data-bs-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('pp_theme', next);
            localStorage.setItem('admin_theme', next);
            updateThemeIcon(next);
        }

        function updateThemeIcon(theme) {
            document.getElementById('themeIcon').textContent = theme === 'dark' ? '☀️' : '🌙';
        }

        // 언어 토글
        var currentLang = 'ko';
        function toggleLang() {
            currentLang = currentLang === 'ko' ? 'en' : 'ko';
            var otherLang = currentLang === 'ko' ? 'en' : 'ko';

            document.querySelectorAll('.ko').forEach(function(el) {
                el.classList.toggle('d-none', currentLang !== 'ko');
            });
            document.querySelectorAll('.en').forEach(function(el) {
                el.classList.toggle('d-none', currentLang !== 'en');
            });

            document.getElementById('langBtn').textContent = currentLang === 'ko' ? 'English' : '한국어';
            document.documentElement.lang = currentLang;
        }

        // 초기 테마 아이콘 설정
        updateThemeIcon(document.documentElement.getAttribute('data-bs-theme'));
    </script>
</body>
</html>

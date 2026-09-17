<?php
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB error');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('sns_reviews');

// SNS data
$sns_reviews = [];
$sns_r = mysqli_query($connect, "SELECT * FROM gangnamunni_sns_reviews ORDER BY FIELD(status,'in_review','submitted','rejected','draft','approved','live','suspended'), updated_at DESC");
if ($sns_r) { while ($row = mysqli_fetch_assoc($sns_r)) { $sns_reviews[] = $row; } }
$sns_stats = ['total'=>0,'draft'=>0,'submitted'=>0,'in_review'=>0,'approved'=>0,'rejected'=>0,'live'=>0,'suspended'=>0];
foreach ($sns_reviews as $sr) { $sns_stats['total']++; if(isset($sns_stats[$sr['status']])) $sns_stats[$sr['status']]++; }

$admin_page_title = 'SNS 심사 관리';
$admin_current = 'sns_reviews';
$admin_extra_css = '';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-phone me-1"></i>SNS / 앱스토어 심사 관리</h5>
</div>

<!-- Main Tabs -->
<ul class="nav nav-tabs mb-3" id="snsMainTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-review" data-bs-toggle="tab" data-bs-target="#pane-review" type="button" role="tab">
            <i class="bi bi-list-check me-1"></i>심사 현황
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-kakao" data-bs-toggle="tab" data-bs-target="#pane-kakao" type="button" role="tab">
            <i class="bi bi-chat-fill text-warning me-1"></i>카카오 로그인
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-naver" data-bs-toggle="tab" data-bs-target="#pane-naver" type="button" role="tab">
            <span class="me-1" style="color:#03C75A;font-weight:bold;">N</span>네이버 로그인
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-google" data-bs-toggle="tab" data-bs-target="#pane-google" type="button" role="tab">
            <i class="bi bi-google text-primary me-1"></i>구글 로그인
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-apple" data-bs-toggle="tab" data-bs-target="#pane-apple" type="button" role="tab">
            <i class="bi bi-apple me-1"></i>Apple 로그인
        </button>
    </li>
</ul>

<div class="tab-content" id="snsMainTabContent">

    <!-- Tab 1: 심사 현황 (기존 그대로) -->
    <div class="tab-pane fade show active" id="pane-review" role="tabpanel">
        <?php include __DIR__ . '/includes/sns_review_panel.php'; ?>
    </div>

    <!-- =============================== -->
    <!-- Tab 2: 카카오 로그인 -->
    <!-- =============================== -->
    <div class="tab-pane fade" id="pane-kakao" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#FEE500 0%,#F7D000 100%);border:none;">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-chat-fill me-2"></i>카카오 로그인 앱 등록 &amp; 심사</h6>
                    <a href="https://developers.kakao.com/" target="_blank" class="btn btn-sm btn-dark">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Kakao Developers
                    </a>
                </div>
            </div>
            <div class="card-body p-4">

                <!-- Step 1: 앱 생성 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-3"><span class="badge bg-warning text-dark me-2">Step 1</span>앱 생성</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li><a href="https://developers.kakao.com/" target="_blank">developers.kakao.com</a> 접속 &rarr; 로그인</li>
                            <li>내 애플리케이션 &rarr; <strong>애플리케이션 추가하기</strong></li>
                            <li>아래 정보 입력 후 저장</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-warning"><tr><th style="width:200px;">항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">앱 이름</td><td><code id="kk_app">서울미녀</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_app')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">사업자명</td><td><code id="kk_biz">서울미녀 성형외과의원</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_biz')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">카테고리</td><td><code id="kk_cat">건강/의료</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_cat')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2: 플랫폼 등록 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-3"><span class="badge bg-warning text-dark me-2">Step 2</span>플랫폼 등록</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <p class="small text-body-secondary mb-1">앱 설정 &rarr; 플랫폼 &rarr; Web / Android / iOS 플랫폼 추가</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-warning"><tr><th style="width:200px;">플랫폼</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Web 사이트 도메인</td><td><code id="kk_web">https://hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_web')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Android 패키지명</td><td><code id="kk_aos">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_aos')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Android 키 해시</td><td><code id="kk_hash">(keytool -exportcert 명령으로 생성)</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_hash')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">iOS 번들 ID</td><td><code id="kk_ios">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_ios')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">관리자 앱 (Android)</td><td><code id="kk_admin_aos">com.celebhospital.admin</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_admin_aos')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">관리자 앱 (iOS)</td><td><code id="kk_admin_ios">com.celebhospital.admin</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_admin_ios')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 3: 카카오 로그인 활성화 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-3"><span class="badge bg-warning text-dark me-2">Step 3</span>카카오 로그인 활성화</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>제품 설정 &rarr; 카카오 로그인 &rarr; <strong>활성화 설정: ON</strong></li>
                            <li>Redirect URI 등록</li>
                            <li>동의항목 설정</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-warning"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Redirect URI (Web)</td><td><code id="kk_redir">https://hms.seoulbeauty.app/hairwang/app/auth/kakao_callback.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_redir')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Redirect URI (앱)</td><td><code id="kk_redir2">kakao{REST_API_KEY}://oauth</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_redir2')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <p class="small fw-semibold mb-2">동의항목 (필수 체크):</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-danger">닉네임 (필수)</span>
                            <span class="badge bg-danger">프로필 사진 (필수)</span>
                            <span class="badge bg-warning text-dark">이메일 (선택 &rarr; 심사 시 필수로 변경)</span>
                            <span class="badge bg-secondary">성별 (선택)</span>
                            <span class="badge bg-secondary">연령대 (선택)</span>
                            <span class="badge bg-secondary">전화번호 (선택)</span>
                        </div>
                    </div>
                </div>

                <!-- Step 4: 비즈 앱 전환 + 심사 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-3"><span class="badge bg-warning text-dark me-2">Step 4</span>비즈 앱 전환 &amp; 심사 신청</h6>
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i><strong>중요:</strong> 이메일 필수 동의를 받으려면 <strong>비즈 앱 전환</strong>이 필요합니다. 개인 개발자 앱은 이메일을 "선택 동의"로만 받을 수 있습니다.
                    </div>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>앱 설정 &rarr; 비즈니스 &rarr; <strong>비즈 앱 전환</strong> 클릭</li>
                            <li>사업자등록증 업로드</li>
                            <li>서비스 URL, 개인정보처리방침 URL 입력</li>
                            <li>심사 신청 (1~3영업일)</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-warning"><tr><th style="width:200px;">심사 제출 항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">서비스 URL</td><td><code id="kk_svc">https://hms.seoulbeauty.app/hairwang/app/</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_svc')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">개인정보처리방침 URL</td><td><code id="kk_priv">https://hms.seoulbeauty.app/hairwang/app/privacy_policy.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_priv')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">이용약관 URL</td><td><code id="kk_terms">https://hms.seoulbeauty.app/hairwang/app/terms.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_terms')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">서비스 아이콘</td><td>512x512px PNG (서울미녀 로고)</td><td></td></tr>
                                <tr><td class="fw-semibold">서비스 설명</td><td><code id="kk_sdesc">서울미녀는 대한민국 성형외과 정보 플랫폼입니다. 병원 검색, 상담 예약, 시술 후기 열람, 이벤트 참여 등의 서비스를 제공하며, 카카오 로그인을 통해 간편하게 서비스를 이용할 수 있습니다.</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_sdesc')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">이메일 수집 사유</td><td><code id="kk_email_reason">회원 식별 및 비밀번호 재설정, 예약 확인 이메일 발송, 중요 서비스 공지 전달을 위해 이메일 주소가 필요합니다.</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('kk_email_reason')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 앱 키 정보 -->
                <div>
                    <h6 class="fw-bold text-dark mb-3"><span class="badge bg-warning text-dark me-2">참고</span>발급된 키 위치</h6>
                    <div class="bg-body-tertiary p-3 rounded">
                        <p class="small mb-1">앱 설정 &rarr; 앱 키 에서 확인:</p>
                        <ul class="small mb-0">
                            <li><strong>REST API 키</strong> &rarr; 서버(PHP) 백엔드 사용</li>
                            <li><strong>JavaScript 키</strong> &rarr; 웹 프론트엔드 SDK 사용</li>
                            <li><strong>Native 앱 키</strong> &rarr; Flutter/Android/iOS SDK 사용</li>
                            <li><strong>Admin 키</strong> &rarr; 서버 관리용 (노출 금지)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================== -->
    <!-- Tab 3: 네이버 로그인 -->
    <!-- =============================== -->
    <div class="tab-pane fade" id="pane-naver" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#03C75A 0%,#00B843 100%);border:none;">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-white"><span class="me-2" style="font-weight:900;">N</span>네이버 로그인 앱 등록 &amp; 검수</h6>
                    <a href="https://developers.naver.com/" target="_blank" class="btn btn-sm btn-light">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Naver Developers
                    </a>
                </div>
            </div>
            <div class="card-body p-4">

                <!-- Step 1 -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3" style="color:#03C75A;"><span class="badge text-bg-success me-2">Step 1</span>애플리케이션 등록</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li><a href="https://developers.naver.com/" target="_blank">developers.naver.com</a> 접속 &rarr; 로그인</li>
                            <li>Application &rarr; 애플리케이션 등록</li>
                            <li>사용 API: <strong>네이버 로그인</strong> 선택</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e8f5e9;"><tr><th style="width:200px;">항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">애플리케이션 이름</td><td><code id="nv_app">서울미녀</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_app')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">사용 API</td><td><code id="nv_api">네이버 로그인 (네아로)</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_api')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2: 권한 & Redirect -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3" style="color:#03C75A;"><span class="badge text-bg-success me-2">Step 2</span>제공 정보 &amp; 환경 설정</h6>
                    <div class="mb-3">
                        <p class="small fw-semibold mb-2">제공 정보 선택 (필수/추가):</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-danger">회원이름 (필수)</span>
                            <span class="badge bg-danger">이메일 (필수)</span>
                            <span class="badge bg-danger">프로필 사진 (필수)</span>
                            <span class="badge bg-warning text-dark">별명 (추가)</span>
                            <span class="badge bg-secondary">성별 (추가)</span>
                            <span class="badge bg-secondary">생일 (추가)</span>
                            <span class="badge bg-secondary">연령대 (추가)</span>
                            <span class="badge bg-secondary">휴대전화 (추가)</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e8f5e9;"><tr><th style="width:200px;">환경</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">서비스 URL (Web)</td><td><code id="nv_url">https://hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_url')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Callback URL (Web)</td><td><code id="nv_cb">https://hms.seoulbeauty.app/hairwang/app/auth/naver_callback.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_cb')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Android 패키지명</td><td><code id="nv_aos">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_aos')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Android URL Scheme</td><td><code id="nv_scheme">seoulbeauty://naver-login</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_scheme')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">iOS Bundle ID</td><td><code id="nv_iosbundle">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_iosbundle')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">iOS URL Scheme</td><td><code id="nv_iosscheme">seoulbeauty</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_iosscheme')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 3: 검수 신청 -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3" style="color:#03C75A;"><span class="badge text-bg-success me-2">Step 3</span>로그인 오픈 API 검수 신청</h6>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i><strong>참고:</strong> 네이버 로그인은 하루 로그인 수 제한이 있으며(개발 상태: 멤버만 로그인 가능), <strong>검수 완료</strong> 후 누구나 로그인할 수 있습니다.
                    </div>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>Application &rarr; 내 앱 선택 &rarr; <strong>API 설정</strong></li>
                            <li>로그인 오픈 API 서비스 환경: "검수 요청" 클릭</li>
                            <li>아래 정보 입력 후 제출 (심사 5~10영업일)</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e8f5e9;"><tr><th style="width:200px;">검수 항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">서비스 소개</td><td><code id="nv_intro">서울미녀는 대한민국 성형외과 정보 플랫폼으로, 병원 검색, 상담 예약, 시술 후기 열람, 이벤트 참여 기능을 제공합니다. 네이버 로그인을 통해 별도 회원가입 없이 간편하게 서비스를 이용할 수 있습니다.</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_intro')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">서비스 URL</td><td><code id="nv_svcurl">https://hms.seoulbeauty.app/hairwang/app/</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_svcurl')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">개인정보처리방침 URL</td><td><code id="nv_priv">https://hms.seoulbeauty.app/hairwang/app/privacy_policy.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_priv')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">이용약관 URL</td><td><code id="nv_terms">https://hms.seoulbeauty.app/hairwang/app/terms.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_terms')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">로그인 화면 스크린샷</td><td>네이버 로그인 버튼이 표시된 로그인 페이지 캡처</td><td></td></tr>
                                <tr><td class="fw-semibold">로그인 후 화면 스크린샷</td><td>로그인 성공 후 사용자 정보가 표시된 마이페이지 캡처</td><td></td></tr>
                                <tr><td class="fw-semibold">이메일 수집 사유</td><td><code id="nv_email_reason">회원 식별, 비밀번호 재설정, 예약 확인 알림, 서비스 공지 발송 목적으로 이메일이 필요합니다.</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('nv_email_reason')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 키 정보 -->
                <div>
                    <h6 class="fw-bold mb-3" style="color:#03C75A;"><span class="badge text-bg-success me-2">참고</span>발급된 키 위치</h6>
                    <div class="bg-body-tertiary p-3 rounded">
                        <ul class="small mb-0">
                            <li><strong>Client ID</strong> &rarr; 서버(PHP) + 프론트엔드 공통 사용</li>
                            <li><strong>Client Secret</strong> &rarr; 서버(PHP) 백엔드에서만 사용 (노출 금지)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================== -->
    <!-- Tab 4: 구글 로그인 -->
    <!-- =============================== -->
    <div class="tab-pane fade" id="pane-google" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#4285F4 0%,#34A853 50%,#FBBC05 75%,#EA4335 100%);border:none;">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-white"><i class="bi bi-google me-2"></i>Google 로그인 OAuth 설정 &amp; 인증</h6>
                    <a href="https://console.cloud.google.com/" target="_blank" class="btn btn-sm btn-light">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Google Cloud Console
                    </a>
                </div>
            </div>
            <div class="card-body p-4">

                <!-- Step 1: 프로젝트 생성 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3"><span class="badge bg-primary me-2">Step 1</span>GCP 프로젝트 생성</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li><a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> 접속</li>
                            <li>프로젝트 선택 &rarr; <strong>새 프로젝트</strong></li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e3f2fd;"><tr><th style="width:200px;">항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">프로젝트 이름</td><td><code id="gg_proj">seoul-beauty</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_proj')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">조직</td><td><code id="gg_org">(해당 시 선택)</code></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2: OAuth 동의 화면 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3"><span class="badge bg-primary me-2">Step 2</span>OAuth 동의 화면 구성</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>API 및 서비스 &rarr; OAuth 동의 화면</li>
                            <li>User Type: <strong>외부</strong> 선택</li>
                            <li>아래 정보 입력</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e3f2fd;"><tr><th style="width:200px;">항목</th><th>입력 내용</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">앱 이름</td><td><code id="gg_name">서울미녀 (Seoul Beauty)</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_name')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">사용자 지원 이메일</td><td><code id="gg_support">admin@seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_support')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">앱 로고</td><td>512x512px PNG (서울미녀 로고)</td><td></td></tr>
                                <tr><td class="fw-semibold">앱 홈페이지</td><td><code id="gg_home">https://hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_home')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">개인정보처리방침 URL</td><td><code id="gg_priv">https://hms.seoulbeauty.app/hairwang/app/privacy_policy.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_priv')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">이용약관 URL</td><td><code id="gg_terms">https://hms.seoulbeauty.app/hairwang/app/terms.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_terms')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">승인된 도메인</td><td><code id="gg_domain">hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_domain')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">개발자 연락처 이메일</td><td><code id="gg_dev">admin@seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_dev')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <p class="small fw-semibold mb-2">범위 (Scopes) 선택:</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-danger">openid (필수)</span>
                            <span class="badge bg-danger">email (필수)</span>
                            <span class="badge bg-danger">profile (필수)</span>
                        </div>
                        <p class="small text-body-secondary mt-1 mb-0">위 3가지는 민감하지 않은 범위로 별도 인증 불필요</p>
                    </div>
                </div>

                <!-- Step 3: 사용자 인증 정보 -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3"><span class="badge bg-primary me-2">Step 3</span>OAuth 클라이언트 ID 생성</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>API 및 서비스 &rarr; 사용자 인증 정보 &rarr; <strong>+ 사용자 인증 정보 만들기</strong></li>
                            <li>OAuth 클라이언트 ID 선택</li>
                            <li>애플리케이션 유형별로 각각 생성 (웹 / Android / iOS)</li>
                        </ol>
                    </div>
                    <h6 class="small fw-bold mt-3 mb-2">A. 웹 애플리케이션</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e3f2fd;"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">이름</td><td><code id="gg_web_name">Seoul Beauty Web</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_web_name')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">승인된 JS 출처</td><td><code id="gg_js_origin">https://hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_js_origin')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">승인된 리디렉션 URI</td><td><code id="gg_redir">https://hms.seoulbeauty.app/hairwang/app/auth/google_callback.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_redir')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h6 class="small fw-bold mt-3 mb-2">B. Android</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e3f2fd;"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">패키지 이름</td><td><code id="gg_aos_pkg">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_aos_pkg')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">SHA-1 인증서 지문</td><td><code id="gg_sha1">(keytool -list -v -keystore으로 확인)</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_sha1')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h6 class="small fw-bold mt-3 mb-2">C. iOS</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead style="background:#e3f2fd;"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">번들 ID</td><td><code id="gg_ios_bundle">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('gg_ios_bundle')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">App Store ID</td><td><code id="gg_ios_store">(앱 출시 후 입력)</code></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 4: 앱 인증 (게시) -->
                <div>
                    <h6 class="fw-bold text-primary mb-3"><span class="badge bg-primary me-2">Step 4</span>앱 게시 (프로덕션)</h6>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i><strong>참고:</strong> "테스트" 상태에서는 등록된 테스트 사용자만 로그인 가능합니다. <strong>앱 게시</strong>를 해야 모든 Google 계정으로 로그인 가능합니다.
                    </div>
                    <div class="bg-body-tertiary p-3 rounded">
                        <ol class="small mb-0">
                            <li>OAuth 동의 화면 &rarr; <strong>앱 게시</strong> 버튼 클릭</li>
                            <li>민감하지 않은 범위(openid, email, profile)만 사용 시 &rarr; <strong>별도 인증 없이 즉시 게시</strong></li>
                            <li>민감한 범위 사용 시 &rarr; Google 인증 심사 필요 (4~6주)</li>
                            <li>인증 심사 시 YouTube 시연 영상, 개인정보처리방침 제출 필요</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================== -->
    <!-- Tab 5: Apple 로그인 -->
    <!-- =============================== -->
    <div class="tab-pane fade" id="pane-apple" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#000000 0%,#333333 100%);border:none;">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-white"><i class="bi bi-apple me-2"></i>Sign in with Apple 설정</h6>
                    <a href="https://developer.apple.com/" target="_blank" class="btn btn-sm btn-light">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Apple Developer
                    </a>
                </div>
            </div>
            <div class="card-body p-4">

                <!-- Step 1 -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><span class="badge bg-dark me-2">Step 1</span>App ID 설정</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li><a href="https://developer.apple.com/account/" target="_blank">developer.apple.com</a> &rarr; Certificates, Identifiers &amp; Profiles</li>
                            <li>Identifiers &rarr; 기존 App ID 선택 또는 새로 생성</li>
                            <li>Capabilities에서 <strong>Sign In with Apple</strong> 체크 &rarr; Edit</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Bundle ID</td><td><code id="ap_bundle">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_bundle')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">App ID Prefix (Team ID)</td><td><code id="ap_team">(Apple Developer 계정의 Team ID)</code></td><td></td></tr>
                                <tr><td class="fw-semibold">Sign In with Apple</td><td><strong>Enable as primary App ID</strong></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2: Service ID (웹용) -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><span class="badge bg-dark me-2">Step 2</span>Service ID 생성 (웹 로그인용)</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>Identifiers &rarr; + 버튼 &rarr; <strong>Services IDs</strong> 선택</li>
                            <li>아래 정보 입력 &rarr; Sign In with Apple 체크 &rarr; Configure</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Description</td><td><code id="ap_desc">Seoul Beauty Web Login</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_desc')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Identifier</td><td><code id="ap_sid">app.seoulbeauty.web</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_sid')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Primary App ID</td><td><code id="ap_primary">com.celebhospital.patient</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_primary')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Domains</td><td><code id="ap_domains">hms.seoulbeauty.app</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_domains')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Return URLs</td><td><code id="ap_return">https://hms.seoulbeauty.app/hairwang/app/auth/apple_callback.php</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_return')"><i class="bi bi-clipboard"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 3: Key 생성 -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><span class="badge bg-dark me-2">Step 3</span>Key 생성 (서버 인증용)</h6>
                    <div class="bg-body-tertiary p-3 rounded mb-3">
                        <ol class="small mb-0">
                            <li>Keys &rarr; + 버튼 &rarr; Key 이름 입력</li>
                            <li><strong>Sign in with Apple</strong> 체크 &rarr; Configure &rarr; Primary App ID 선택</li>
                            <li>Register &rarr; <strong>.p8 키 파일 다운로드</strong> (1회만 가능!)</li>
                        </ol>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark"><tr><th style="width:200px;">항목</th><th>설정값</th><th style="width:70px;">복사</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Key Name</td><td><code id="ap_key">SeoulBeautySignInKey</code></td><td><button class="btn btn-sm btn-outline-secondary" onclick="copyText('ap_key')"><i class="bi bi-clipboard"></i></button></td></tr>
                                <tr><td class="fw-semibold">Key ID</td><td>(생성 후 자동 발급)</td><td></td></tr>
                                <tr><td class="fw-semibold">.p8 파일</td><td>다운로드 후 서버에 안전하게 보관 (재다운로드 불가)</td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 참고 -->
                <div>
                    <h6 class="fw-bold mb-3"><span class="badge bg-dark me-2">참고</span>Apple 로그인 필수 사항</h6>
                    <div class="alert alert-warning small mb-0">
                        <ul class="mb-0">
                            <li><strong>App Store 필수:</strong> 다른 소셜 로그인(카카오, 네이버, 구글)을 제공하는 iOS 앱은 반드시 <strong>Sign in with Apple</strong>도 함께 제공해야 합니다.</li>
                            <li><strong>이메일 숨기기:</strong> 사용자가 이메일 숨기기를 선택하면 @privaterelay.appleid.com 프록시 이메일이 전달됩니다.</li>
                            <li><strong>이름 제공:</strong> 최초 로그인 시에만 이름이 전달되므로, 반드시 첫 응답에서 저장해야 합니다.</li>
                            <li><strong>별도 심사 불필요:</strong> Apple Developer Program 멤버십만 있으면 바로 사용 가능합니다.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function copyText(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const text = el.innerText || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = el.closest('tr').querySelector('button');
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check text-success"></i>';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        }
    });
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>

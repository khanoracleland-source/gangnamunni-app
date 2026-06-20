<?php
/**
 * Seoul Beauty 메일 사용 도움말
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

if (!check_admin_login()) { header('Location: dashboard.php'); exit; }

$admin_page_title = '메일 도움말';
$admin_current = 'mailserver';
$admin_extra_css = '
    .help-section { margin-bottom: 2rem; }
    .help-section h5 { color: var(--bs-primary); border-bottom: 2px solid var(--bs-primary); padding-bottom: 0.5rem; }
    .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--bs-primary); color: white; font-weight: bold; font-size: 0.85rem; margin-right: 0.5rem; flex-shrink: 0; }
    .help-card { border-left: 4px solid var(--bs-primary); }
    .tip-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; }
    [data-bs-theme=dark] .tip-box { background: #332701; border-color: #665000; }
';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="bi bi-question-circle"></i> Seoul Beauty 메일 사용 도움말</h5>
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> 대시보드</a>
</div>

<!-- 1. 메일 확인 방법 -->
<div class="help-section">
    <h5><i class="bi bi-envelope-open"></i> 1. 메일 확인하는 방법</h5>

    <div class="card help-card mb-3">
        <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-browser-chrome"></i> 방법 1: SOGo 웹메일 (가장 쉬움 - 네이버/지메일처럼)</h6>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">1</span>
                <span><a href="https://mail.seoulbeauty.app/SOGo/" target="_blank">https://mail.seoulbeauty.app/SOGo/</a> 접속</span>
            </div>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">2</span>
                <span>아이디: <code>admin@seoulbeauty.app</code> / 비밀번호: <code>SeoulB3auty!2026</code> 입력</span>
            </div>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">3</span>
                <span>로그인하면 네이버/지메일과 동일한 메일 화면이 나옵니다</span>
            </div>
            <div class="tip-box mt-3">
                <strong><i class="bi bi-lightbulb"></i> TIP:</strong> SOGo에서 메일 읽기, 쓰기, 답장, 전달, 첨부파일 모두 가능합니다. 일정관리와 주소록도 사용할 수 있습니다.
            </div>
        </div>
    </div>

    <div class="card help-card mb-3">
        <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-phone"></i> 방법 2: 스마트폰 메일 앱</h6>
            <p class="text-body-secondary">아이폰 Mail, 삼성 이메일, Gmail 앱 등에서 계정 추가</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="bg-body-tertiary rounded-3 p-3">
                        <h6 class="fw-semibold text-success"><i class="bi bi-arrow-down-circle"></i> 수신 서버 (IMAP)</h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="fw-semibold">서버</td><td><code>mail.seoulbeauty.app</code></td></tr>
                            <tr><td class="fw-semibold">포트</td><td><code>993</code></td></tr>
                            <tr><td class="fw-semibold">보안</td><td><code>SSL/TLS</code></td></tr>
                            <tr><td class="fw-semibold">아이디</td><td><code>admin@seoulbeauty.app</code></td></tr>
                            <tr><td class="fw-semibold">비밀번호</td><td><code>SeoulB3auty!2026</code></td></tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bg-body-tertiary rounded-3 p-3">
                        <h6 class="fw-semibold text-primary"><i class="bi bi-arrow-up-circle"></i> 발신 서버 (SMTP)</h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="fw-semibold">서버</td><td><code>mail.seoulbeauty.app</code></td></tr>
                            <tr><td class="fw-semibold">포트</td><td><code>587</code></td></tr>
                            <tr><td class="fw-semibold">보안</td><td><code>STARTTLS</code></td></tr>
                            <tr><td class="fw-semibold">아이디</td><td><code>admin@seoulbeauty.app</code></td></tr>
                            <tr><td class="fw-semibold">비밀번호</td><td><code>SeoulB3auty!2026</code></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card help-card mb-3">
        <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-pc-display"></i> 방법 3: PC 메일 프로그램 (Outlook, 썬더버드)</h6>
            <p class="text-body-secondary">위의 IMAP/SMTP 설정을 그대로 사용합니다. 자동설정(autodiscover)도 지원됩니다.</p>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">1</span>
                <span>Outlook: 파일 > 계정 추가 > <code>admin@seoulbeauty.app</code> 입력</span>
            </div>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">2</span>
                <span>자동설정이 안 되면 "수동 설정"을 선택하고 위의 IMAP/SMTP 정보 입력</span>
            </div>
        </div>
    </div>
</div>

<!-- 2. 메일 보내기 -->
<div class="help-section">
    <h5><i class="bi bi-send"></i> 2. 메일 보내기</h5>
    <div class="card help-card mb-3">
        <div class="card-body">
            <h6 class="fw-bold">SOGo 웹메일에서 보내기</h6>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">1</span>
                <span><a href="https://mail.seoulbeauty.app/SOGo/" target="_blank">SOGo 웹메일</a> 로그인</span>
            </div>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">2</span>
                <span>좌측 상단 <strong>"새 메시지"</strong> (연필 아이콘) 클릭</span>
            </div>
            <div class="d-flex align-items-start mb-2">
                <span class="step-number">3</span>
                <span>받는 사람, 제목, 내용 입력 후 <strong>"보내기"</strong> 클릭</span>
            </div>
            <div class="tip-box mt-3">
                <strong><i class="bi bi-lightbulb"></i> TIP:</strong> 첨부파일은 클립 아이콘으로 추가할 수 있습니다. 최대 25MB까지 가능합니다.
            </div>
        </div>
    </div>
</div>

<!-- 3. 비교표 -->
<div class="help-section">
    <h5><i class="bi bi-table"></i> 3. 메일 확인 방법 비교</h5>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>항목</th>
                    <th><i class="bi bi-browser-chrome"></i> SOGo 웹메일</th>
                    <th><i class="bi bi-phone"></i> 스마트폰 앱</th>
                    <th><i class="bi bi-pc-display"></i> Outlook/썬더버드</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold">설치 필요</td>
                    <td class="text-success">필요 없음 (브라우저)</td>
                    <td>기본 앱 사용</td>
                    <td>설치 필요</td>
                </tr>
                <tr>
                    <td class="fw-semibold">알림</td>
                    <td>브라우저 탭만</td>
                    <td class="text-success">푸시 알림</td>
                    <td class="text-success">데스크톱 알림</td>
                </tr>
                <tr>
                    <td class="fw-semibold">추천 상황</td>
                    <td>가끔 확인할 때</td>
                    <td class="text-success">실시간 알림 필요</td>
                    <td>업무용 PC에서</td>
                </tr>
                <tr>
                    <td class="fw-semibold">일정/주소록</td>
                    <td class="text-success">지원</td>
                    <td>동기화 가능</td>
                    <td class="text-success">동기화 가능</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 4. 관리자 기능 -->
<div class="help-section">
    <h5><i class="bi bi-gear"></i> 4. 관리자 기능 (Mailcow 패널)</h5>
    <div class="card help-card mb-3">
        <div class="card-body">
            <p><a href="https://mail.seoulbeauty.app/" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Mailcow 관리자 패널 열기</a></p>
            <div class="row g-3">
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><i class="bi bi-person-plus text-primary"></i> 새 메일 계정 추가</li>
                        <li class="list-group-item"><i class="bi bi-envelope-arrow-up text-success"></i> 메일 포워딩 설정</li>
                        <li class="list-group-item"><i class="bi bi-funnel text-warning"></i> 스팸 필터 관리</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><i class="bi bi-shield-check text-danger"></i> DKIM/SPF/DMARC 확인</li>
                        <li class="list-group-item"><i class="bi bi-journal-text text-info"></i> 메일 로그 확인</li>
                        <li class="list-group-item"><i class="bi bi-key text-secondary"></i> 비밀번호 변경</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. 메일 계정 목록 -->
<div class="help-section">
    <h5><i class="bi bi-people"></i> 5. 현재 메일 계정</h5>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>이메일</th>
                    <th>용도</th>
                    <th>웹메일</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>admin@seoulbeauty.app</code></td>
                    <td>관리자, HMS 시스템 메일 발송</td>
                    <td><a href="https://mail.seoulbeauty.app/SOGo/" target="_blank" class="btn btn-sm btn-outline-primary">SOGo 열기</a></td>
                </tr>
                <tr>
                    <td><code>info@seoulbeauty.app</code></td>
                    <td>일반 문의 수신</td>
                    <td><a href="https://mail.seoulbeauty.app/SOGo/" target="_blank" class="btn btn-sm btn-outline-primary">SOGo 열기</a></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="tip-box">
        <strong><i class="bi bi-lightbulb"></i> 새 메일 계정이 필요하면?</strong> <a href="https://mail.seoulbeauty.app/" target="_blank">Mailcow 관리자 패널</a> > E-Mail > Mailboxes > Add mailbox 에서 추가할 수 있습니다.
    </div>
</div>

<!-- 6. FAQ -->
<div class="help-section">
    <h5><i class="bi bi-patch-question"></i> 6. 자주 묻는 질문 (FAQ)</h5>
    <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    메일이 스팸으로 분류되면?
                </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    SPF, DKIM, DMARC 레코드가 모두 설정되어 있어 대부분의 메일 서비스에서 정상 수신됩니다. 스팸 분류가 지속되면 Mailcow 관리 패널에서 로그를 확인하세요.
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    메일 용량 제한은?
                </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    기본 첨부파일 제한: 25MB. 메일함 용량은 Mailcow 관리 패널에서 계정별로 설정 가능합니다 (기본: 3GB).
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    비밀번호를 변경하려면?
                </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    <strong>Mailcow 관리자 비밀번호:</strong> <a href="https://mail.seoulbeauty.app/" target="_blank">관리 패널</a> > Access > Edit admin 에서 변경<br>
                    <strong>메일 계정 비밀번호:</strong> <a href="https://mail.seoulbeauty.app/" target="_blank">관리 패널</a> > E-Mail > Mailboxes > 해당 계정 Edit 에서 변경
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    Gmail 앱에서 설정하려면?
                </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Gmail 앱 > 설정 > 계정 추가 > 기타 > <code>admin@seoulbeauty.app</code> 입력 > IMAP 선택 > 위의 수신/발신 서버 정보 입력
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                    아이폰 기본 메일에서 설정하려면?
                </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    설정 > 메일 > 계정 > 계정 추가 > 기타 > 메일 계정 추가 > 이름, 이메일(<code>admin@seoulbeauty.app</code>), 비밀번호 입력 > IMAP 선택 > 수신/발신 서버 정보 입력
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
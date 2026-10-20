<?php
/**
 * 이용약관 (Terms of Service) — Apple Guideline 1.2 UGC Safety 대응
 * 불쾌 콘텐츠 무관용 정책 + 사용자 의무 + 위반 시 조치 명시
 */
session_start();
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect($_DB_HOST, $_DB_USER, $_DB_PASSWORD, $_DB_NAME);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
}

// 약관 동의 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree'])) {
    $user_id = $_SESSION['ss_mb_id'] ?? '';
    if ($user_id && $connect) {
        $version = '1.0';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $types = ['terms_of_service', 'privacy_policy', 'community_guidelines'];
        foreach ($types as $type) {
            $stmt = mysqli_prepare($connect, "INSERT IGNORE INTO gangnamunni_user_agreements (user_id, agreement_type, agreement_version, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss', $user_id, $type, $version, $ip, $ua);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        $agreed = true;
    }
}

$page_title = '이용약관 - 서울미녀';
$dark_mode = $_COOKIE['dark_mode'] ?? 'dark';
$theme_attr = $dark_mode === 'light' ? 'light' : 'dark';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="<?php echo $theme_attr; ?>">
<head>
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<style>
:root { --bg: #0f0f0f; --card: #1a1a1a; --text: #e0e0e0; --muted: #888; --border: #333; --primary: #AC1830; --danger: #ef4444; --success: #16a34a; --warning: #f59e0b; }
[data-bs-theme="light"] { --bg: #f8f9fa; --card: #fff; --text: #212529; --muted: #6c757d; --border: #dee2e6; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; }
.container { max-width: 800px; margin: 0 auto; padding: 20px 16px 100px; }
.header { text-align: center; padding: 32px 0 24px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
.header h1 { font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 800; margin-bottom: 8px; }
.header p { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--muted); }
.tabs { display: flex; gap: 8px; margin-bottom: 24px; }
.tab { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; }
.tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.section { margin-bottom: 28px; }
.section h2 { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--primary); display: inline-block; }
.section h3 { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin: 16px 0 8px; color: var(--primary); }
.section p, .section li { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--text); margin-bottom: 6px; }
.section ul, .section ol { padding-left: 20px; margin-bottom: 12px; }
.warning-box { background: rgba(239,68,68,0.1); border: 1px solid var(--danger); border-radius: 8px; padding: 16px; margin: 16px 0; }
.warning-box h3 { color: var(--danger); margin-top: 0; }
.info-box { background: rgba(139,92,246,0.1); border: 1px solid var(--primary); border-radius: 8px; padding: 16px; margin: 16px 0; }
.penalty-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); }
.penalty-table th, .penalty-table td { padding: 10px 12px; border: 1px solid var(--border); text-align: left; }
.penalty-table th { background: rgba(139,92,246,0.15); font-weight: 600; }
.penalty-table tr:nth-child(even) { background: rgba(255,255,255,0.03); }
.agree-box { position: fixed; bottom: 0; left: 0; right: 0; background: var(--card); border-top: 1px solid var(--border); padding: 16px; text-align: center; z-index: 100; }
.agree-box .btn { padding: 12px 32px; border-radius: 8px; border: none; font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; cursor: pointer; background: var(--primary); color: #fff; }
.agree-box .btn:disabled { opacity: 0.5; cursor: not-allowed; }
.agree-box label { display: block; margin-bottom: 8px; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); }
.effective { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--muted); text-align: center; margin-top: 20px; }
.lang-en { display: none; }
.lang-en.active { display: block; }
.lang-ko { display: block; }
.lang-ko.hidden { display: none; }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>이용약관</h1>
        <p>Terms of Service</p>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="switchLang('ko')">한국어</button>
        <button class="tab" onclick="switchLang('en')">English</button>
    </div>

    <!-- 한국어 -->
    <div id="content-ko" class="lang-ko">
        <div class="section">
            <h2>제1조 (목적)</h2>
            <p>본 약관은 서울미녀(이하 "서비스")가 제공하는 의료/미용 정보 플랫폼 서비스의 이용조건 및 절차, 이용자와 서비스 간의 권리·의무 및 책임사항을 규정함을 목적으로 합니다.</p>
        </div>

        <div class="section">
            <h2>제2조 (정의)</h2>
            <ol>
                <li><strong>"서비스"</strong>란 서울미녀가 운영하는 웹사이트 및 모바일 애플리케이션을 통해 제공하는 병원 정보, 후기, 예약, 상담, 커뮤니티 등의 서비스를 말합니다.</li>
                <li><strong>"이용자"</strong>란 본 약관에 따라 서비스에 접속하여 서비스를 이용하는 회원 및 비회원을 말합니다.</li>
                <li><strong>"회원"</strong>이란 서비스에 가입하여 이용자 아이디를 부여받은 자를 말합니다.</li>
                <li><strong>"사용자 생성 콘텐츠(UGC)"</strong>란 이용자가 서비스 내에서 작성한 후기, 댓글, 질문, 채팅 메시지, 사진 등 모든 형태의 콘텐츠를 말합니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제3조 (약관의 효력 및 변경)</h2>
            <ol>
                <li>본 약관은 서비스를 이용하고자 하는 모든 이용자에게 적용됩니다.</li>
                <li>서비스는 약관을 변경할 수 있으며, 변경된 약관은 공지사항을 통해 공지합니다.</li>
                <li>이용자가 변경된 약관에 동의하지 않는 경우 서비스 이용을 중단하고 탈퇴할 수 있습니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제4조 (회원가입 및 이용계약)</h2>
            <ol>
                <li>이용자는 본 약관, 개인정보처리방침, 커뮤니티 가이드라인에 동의한 후 회원가입을 할 수 있습니다.</li>
                <li>만 14세 미만의 아동은 회원가입이 제한됩니다.</li>
                <li>허위 정보를 기재한 경우 서비스 이용이 제한될 수 있습니다.</li>
            </ol>
        </div>

        <div class="warning-box">
            <h3>제5조 (불쾌 콘텐츠 무관용 정책 — Zero Tolerance Policy)</h3>
            <p><strong>서울미녀는 불쾌하거나 유해한 콘텐츠 및 악의적 사용자에 대해 무관용 원칙을 적용합니다.</strong></p>
            <p>다음에 해당하는 콘텐츠는 즉시 삭제되며, 작성자는 경고 없이 계정이 정지되거나 영구 차단될 수 있습니다:</p>
            <ol>
                <li><strong>스팸 및 광고</strong> — 허가되지 않은 광고, 홍보, 스팸 메시지</li>
                <li><strong>괴롭힘 및 모욕</strong> — 특정 개인이나 집단을 대상으로 한 괴롭힘, 위협, 모욕</li>
                <li><strong>혐오 발언</strong> — 인종, 민족, 성별, 종교, 성적 지향, 장애 등을 이유로 한 차별적 발언</li>
                <li><strong>성적 콘텐츠</strong> — 음란물, 성적으로 노골적인 콘텐츠</li>
                <li><strong>폭력적 콘텐츠</strong> — 폭력을 조장하거나 묘사하는 콘텐츠</li>
                <li><strong>허위 정보</strong> — 허위 후기, 조작된 의료 정보, 사실과 다른 병원 정보</li>
                <li><strong>저작권 침해</strong> — 타인의 저작물을 무단으로 사용</li>
                <li><strong>의료법 위반</strong> — "100% 효과 보장", "부작용 없음" 등 의료법을 위반하는 과장 광고</li>
                <li><strong>개인정보 노출</strong> — 타인의 동의 없이 개인정보를 공개</li>
                <li><strong>기타 위법 행위</strong> — 관련 법률을 위반하는 모든 콘텐츠</li>
            </ol>
        </div>

        <div class="section">
            <h2>제6조 (위반 시 제재)</h2>
            <table class="penalty-table">
                <thead>
                    <tr><th>단계</th><th>조치</th><th>기간</th><th>적용 기준</th></tr>
                </thead>
                <tbody>
                    <tr><td>1단계</td><td>경고 + 콘텐츠 삭제</td><td>즉시</td><td>최초 위반</td></tr>
                    <tr><td>2단계</td><td>계정 일시 정지</td><td>7일</td><td>2회 위반</td></tr>
                    <tr><td>3단계</td><td>계정 장기 정지</td><td>30일</td><td>3회 위반</td></tr>
                    <tr><td>4단계</td><td>영구 차단</td><td>영구</td><td>4회 이상 또는 심각한 위반</td></tr>
                </tbody>
            </table>
            <p>심각한 위반(폭력 위협, 불법 콘텐츠 등)은 경고 없이 즉시 영구 차단될 수 있습니다.</p>
        </div>

        <div class="section">
            <h2>제7조 (콘텐츠 관리)</h2>
            <ol>
                <li><strong>자동 필터링</strong>: 모든 사용자 생성 콘텐츠는 금칙어 필터 및 AI 분석을 거칩니다.</li>
                <li><strong>관리자 검토</strong>: 후기 등 주요 콘텐츠는 관리자 승인 후 게시됩니다.</li>
                <li><strong>신고 시스템</strong>: 이용자는 부적절한 콘텐츠를 신고할 수 있으며, 운영팀은 24시간 이내에 신고를 검토하고 조치합니다.</li>
                <li><strong>자동 비공개</strong>: 3건 이상의 신고가 접수된 콘텐츠는 검토 완료 시까지 자동으로 비공개 처리됩니다.</li>
                <li>서울미녀는 재량에 따라 콘텐츠를 수정, 삭제, 비공개 처리할 수 있습니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제8조 (사용자 차단)</h2>
            <ol>
                <li>이용자는 다른 이용자를 차단할 수 있습니다.</li>
                <li>차단된 이용자의 콘텐츠는 차단한 이용자의 화면에서 즉시 숨겨집니다.</li>
                <li>차단 시 운영팀에 자동으로 통보됩니다.</li>
                <li>차단은 설정에서 언제든 해제할 수 있습니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제9조 (신고 절차)</h2>
            <ol>
                <li>이용자는 부적절한 콘텐츠 발견 시 신고 버튼을 통해 신고할 수 있습니다.</li>
                <li>신고 사유: 스팸/광고, 괴롭힘/모욕, 혐오 발언, 성적 콘텐츠, 폭력적 콘텐츠, 허위 정보, 저작권 침해, 기타</li>
                <li>운영팀은 신고 접수 후 <strong>24시간 이내</strong>에 검토 및 조치합니다.</li>
                <li>조치 내용: 콘텐츠 삭제, 사용자 경고, 계정 정지, 영구 차단</li>
                <li>신고자에게는 처리 결과가 알림으로 통보됩니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제10조 (이의 신청)</h2>
            <ol>
                <li>제재를 받은 이용자는 이의를 신청할 수 있습니다.</li>
                <li>이의 신청은 고객센터(admin@seoulbeauty.app)를 통해 가능합니다.</li>
                <li>이의 신청은 접수 후 72시간 이내에 검토됩니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제11조 (서비스 이용 제한)</h2>
            <p>서울미녀는 다음의 경우 서비스 이용을 제한할 수 있습니다:</p>
            <ol>
                <li>본 약관 또는 커뮤니티 가이드라인을 위반한 경우</li>
                <li>타인의 서비스 이용을 방해한 경우</li>
                <li>서비스의 안정적 운영을 방해한 경우</li>
                <li>관련 법령을 위반한 경우</li>
            </ol>
        </div>

        <div class="section">
            <h2>제12조 (면책 조항)</h2>
            <ol>
                <li>서울미녀는 이용자가 작성한 콘텐츠의 진실성, 정확성에 대해 보증하지 않습니다.</li>
                <li>서울미녀는 이용자 간의 분쟁에 대해 개입할 의무가 없으나, 플랫폼 안전을 위해 필요한 조치를 취할 수 있습니다.</li>
                <li>서울미녀에 게시된 의료 정보는 참고용이며, 실제 의료 행위를 대체하지 않습니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제13조 (지적재산권)</h2>
            <ol>
                <li>이용자가 작성한 콘텐츠의 저작권은 이용자에게 있습니다.</li>
                <li>이용자는 서울미녀에 콘텐츠를 게시함으로써, 서비스 내에서의 사용, 복제, 수정, 배포에 대한 비독점적 라이선스를 부여합니다.</li>
                <li>서비스 탈퇴 시 이용자의 콘텐츠는 관련 법령에 따라 보존 또는 삭제됩니다.</li>
            </ol>
        </div>

        <div class="section">
            <h2>제14조 (준거법 및 관할)</h2>
            <p>본 약관은 대한민국 법률에 따라 해석되며, 분쟁 발생 시 서울중앙지방법원을 관할법원으로 합니다.</p>
        </div>

        <div class="info-box">
            <h3>관련 문서</h3>
            <ul>
                <li><a href="privacy_policy.php" style="color: var(--primary);">개인정보처리방침</a></li>
                <li><a href="community_guidelines.php" style="color: var(--primary);">커뮤니티 가이드라인</a></li>
            </ul>
        </div>

        <div class="effective">
            <p>시행일: 2026년 3월 17일 | 버전 1.0</p>
        </div>
    </div>

    <!-- English -->
    <div id="content-en" class="lang-en">
        <div class="section">
            <h2>Article 1 (Purpose)</h2>
            <p>These Terms of Service define the conditions and procedures for using the medical/beauty information platform service provided by Seoul Beauty (hereinafter "Service"), and establish the rights, obligations, and responsibilities between users and the Service.</p>
        </div>

        <div class="section">
            <h2>Article 2 (Definitions)</h2>
            <ol>
                <li><strong>"Service"</strong> refers to hospital information, reviews, reservations, consultations, community, and other services provided through the website and mobile applications operated by Seoul Beauty.</li>
                <li><strong>"User"</strong> refers to members and non-members who access and use the Service under these Terms.</li>
                <li><strong>"Member"</strong> refers to a person who has registered with the Service and been assigned a user ID.</li>
                <li><strong>"User-Generated Content (UGC)"</strong> refers to all forms of content created by users within the Service, including reviews, comments, questions, chat messages, and photos.</li>
            </ol>
        </div>

        <div class="warning-box">
            <h3>Article 5 (Zero Tolerance Policy for Objectionable Content)</h3>
            <p><strong>Seoul Beauty applies a zero-tolerance principle towards objectionable or harmful content and abusive users.</strong></p>
            <p>The following content will be immediately removed, and the author may have their account suspended or permanently banned without warning:</p>
            <ol>
                <li><strong>Spam and Advertising</strong> — Unauthorized advertising, promotion, or spam messages</li>
                <li><strong>Harassment and Insults</strong> — Harassment, threats, or insults targeting specific individuals or groups</li>
                <li><strong>Hate Speech</strong> — Discriminatory speech based on race, ethnicity, gender, religion, sexual orientation, disability, etc.</li>
                <li><strong>Sexual Content</strong> — Pornography or sexually explicit content</li>
                <li><strong>Violent Content</strong> — Content that promotes or depicts violence</li>
                <li><strong>Misinformation</strong> — Fake reviews, fabricated medical information, false hospital information</li>
                <li><strong>Copyright Infringement</strong> — Unauthorized use of others' copyrighted works</li>
                <li><strong>Medical Law Violations</strong> — Exaggerated advertising violating medical laws such as "100% effective" or "no side effects"</li>
                <li><strong>Privacy Violations</strong> — Disclosure of personal information without consent</li>
                <li><strong>Other Illegal Activities</strong> — Any content violating applicable laws</li>
            </ol>
        </div>

        <div class="section">
            <h2>Article 6 (Penalties for Violations)</h2>
            <table class="penalty-table">
                <thead>
                    <tr><th>Level</th><th>Action</th><th>Duration</th><th>Criteria</th></tr>
                </thead>
                <tbody>
                    <tr><td>Level 1</td><td>Warning + Content Removal</td><td>Immediate</td><td>First violation</td></tr>
                    <tr><td>Level 2</td><td>Temporary Suspension</td><td>7 days</td><td>Second violation</td></tr>
                    <tr><td>Level 3</td><td>Extended Suspension</td><td>30 days</td><td>Third violation</td></tr>
                    <tr><td>Level 4</td><td>Permanent Ban</td><td>Permanent</td><td>4+ violations or severe offense</td></tr>
                </tbody>
            </table>
            <p>Severe violations (violent threats, illegal content, etc.) may result in immediate permanent ban without warning.</p>
        </div>

        <div class="section">
            <h2>Article 7 (Content Management)</h2>
            <ol>
                <li><strong>Automated Filtering</strong>: All user-generated content undergoes keyword filtering and AI analysis.</li>
                <li><strong>Admin Review</strong>: Key content such as reviews is published after administrator approval.</li>
                <li><strong>Reporting System</strong>: Users can report inappropriate content, and the operations team will review and take action within 24 hours.</li>
                <li><strong>Auto-Hide</strong>: Content with 3 or more reports is automatically hidden pending review.</li>
                <li>Seoul Beauty may edit, delete, or hide content at its discretion.</li>
            </ol>
        </div>

        <div class="section">
            <h2>Article 8 (User Blocking)</h2>
            <ol>
                <li>Users can block other users.</li>
                <li>Blocked user's content is immediately hidden from the blocking user's view.</li>
                <li>The operations team is automatically notified when a block occurs.</li>
                <li>Blocks can be removed at any time from Settings.</li>
            </ol>
        </div>

        <div class="section">
            <h2>Article 9 (Reporting Procedure)</h2>
            <ol>
                <li>Users can report inappropriate content using the report button.</li>
                <li>Report reasons: Spam/Advertising, Harassment/Insults, Hate Speech, Sexual Content, Violent Content, Misinformation, Copyright Infringement, Other</li>
                <li>The operations team reviews and takes action <strong>within 24 hours</strong> of receiving a report.</li>
                <li>Actions taken: Content removal, User warning, Account suspension, Permanent ban</li>
                <li>Reporters are notified of the resolution via notification.</li>
            </ol>
        </div>

        <div class="section">
            <h2>Article 10 (Appeals)</h2>
            <ol>
                <li>Users who receive sanctions may file an appeal.</li>
                <li>Appeals can be submitted through customer service (admin@seoulbeauty.app).</li>
                <li>Appeals are reviewed within 72 hours of submission.</li>
            </ol>
        </div>

        <div class="section">
            <h2>Article 12 (Disclaimer)</h2>
            <ol>
                <li>Seoul Beauty does not guarantee the truthfulness or accuracy of user-created content.</li>
                <li>Seoul Beauty has no obligation to intervene in disputes between users but may take necessary measures for platform safety.</li>
                <li>Medical information on Seoul Beauty is for reference only and does not replace actual medical treatment.</li>
            </ol>
        </div>

        <div class="info-box">
            <h3>Related Documents</h3>
            <ul>
                <li><a href="privacy_policy.php" style="color: var(--primary);">Privacy Policy</a></li>
                <li><a href="community_guidelines.php" style="color: var(--primary);">Community Guidelines</a></li>
            </ul>
        </div>

        <div class="effective">
            <p>Effective Date: March 17, 2026 | Version 1.0</p>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['ss_mb_id']) && !isset($agreed)): ?>
<div class="agree-box" id="agreeBox">
    <form method="post">
        <label>
            <input type="checkbox" id="agreeCheck" onchange="document.getElementById('agreeBtn').disabled = !this.checked">
            이용약관, 개인정보처리방침, 커뮤니티 가이드라인에 모두 동의합니다
        </label>
        <button type="submit" name="agree" value="1" class="btn" id="agreeBtn" disabled>동의하기</button>
    </form>
</div>
<?php endif; ?>

<script>
function switchLang(lang) {
    const ko = document.getElementById('content-ko');
    const en = document.getElementById('content-en');
    const tabs = document.querySelectorAll('.tab');
    if (lang === 'en') {
        ko.classList.add('hidden');
        en.classList.add('active');
        tabs[0].classList.remove('active');
        tabs[1].classList.add('active');
    } else {
        ko.classList.remove('hidden');
        en.classList.remove('active');
        tabs[0].classList.add('active');
        tabs[1].classList.remove('active');
    }
}

// 다크모드 anti-flash
(function() {
    const dm = localStorage.getItem('dark_mode') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', dm);
})();
</script>
</body>
</html>

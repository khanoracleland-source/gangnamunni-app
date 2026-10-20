<?php
/**
 * 커뮤니티 가이드라인 (Community Guidelines) — Apple Guideline 1.2 UGC Safety
 * 허용/금지 콘텐츠 기준 + 신고/차단 안내 + 제재 단계
 */
session_start();
// dbconfig는 필요시에만 로드
if (file_exists(__DIR__ . '/theme_helper.php')) {
    require_once __DIR__ . '/theme_helper.php';
}

$page_title = '커뮤니티 가이드라인 - 서울미녀';
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
.container { max-width: 800px; margin: 0 auto; padding: 20px 16px 60px; }
.header { text-align: center; padding: 32px 0 24px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
.header h1 { font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 800; margin-bottom: 8px; }
.header p { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--muted); }
.section { margin-bottom: 28px; }
.section h2 { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--primary); display: inline-block; }
.section h3 { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin: 16px 0 8px; }
.section p, .section li { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 6px; }
.section ul, .section ol { padding-left: 20px; margin-bottom: 12px; }
.do-dont { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; }
@media (max-width: 600px) { .do-dont { grid-template-columns: 1fr; } }
.do-box, .dont-box { padding: 16px; border-radius: 10px; }
.do-box { background: rgba(22,163,106,0.1); border: 1px solid var(--success); }
.dont-box { background: rgba(239,68,68,0.1); border: 1px solid var(--danger); }
.do-box h3 { color: var(--success); margin-top: 0; }
.dont-box h3 { color: var(--danger); margin-top: 0; }
.step-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin: 8px 0; display: flex; align-items: center; gap: 16px; }
.step-num { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink: 0; }
.step-1 .step-num { background: rgba(245,158,11,0.2); color: var(--warning); }
.step-2 .step-num { background: rgba(239,68,68,0.15); color: #f87171; }
.step-3 .step-num { background: rgba(239,68,68,0.25); color: var(--danger); }
.step-4 .step-num { background: rgba(239,68,68,0.4); color: #fff; }
.step-info h4 { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 2px; }
.step-info p { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--muted); margin: 0; }
.how-to { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin: 16px 0; }
.how-to h3 { font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; }
.how-step { display: flex; gap: 12px; margin-bottom: 12px; }
.how-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(172, 24, 48,0.12); display: flex; align-items: center; justify-content: center; font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink: 0; }
.how-text { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); }
.how-text strong { display: block; margin-bottom: 2px; }
.info-box { background: rgba(139,92,246,0.1); border: 1px solid var(--primary); border-radius: 8px; padding: 16px; margin: 16px 0; }
.effective { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--muted); text-align: center; margin-top: 20px; }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>커뮤니티 가이드라인</h1>
        <p>Community Guidelines</p>
    </div>

    <div class="section">
        <h2>서울미녀 커뮤니티 원칙</h2>
        <p>서울미녀는 안전하고 신뢰할 수 있는 의료/미용 정보 플랫폼을 지향합니다. 모든 이용자가 서로를 존중하고, 진실된 정보를 공유하며, 건강한 커뮤니티 문화를 만들어갑니다.</p>
    </div>

    <div class="section">
        <h2>허용 vs 금지 콘텐츠</h2>
        <div class="do-dont">
            <div class="do-box">
                <h3>허용되는 콘텐츠</h3>
                <ul>
                    <li>실제 경험에 기반한 솔직한 후기</li>
                    <li>시술 전후 비교 사진 (본인 동의)</li>
                    <li>병원/의사에 대한 건설적인 평가</li>
                    <li>시술 관련 질문과 정보 공유</li>
                    <li>다른 이용자를 돕는 유용한 팁</li>
                    <li>예의 바른 토론과 의견 교환</li>
                </ul>
            </div>
            <div class="dont-box">
                <h3>금지되는 콘텐츠</h3>
                <ul>
                    <li>허위/조작 후기 (돈 받고 작성한 리뷰)</li>
                    <li>스팸, 광고, 홍보 메시지</li>
                    <li>욕설, 비방, 인신공격</li>
                    <li>혐오 발언, 차별적 표현</li>
                    <li>성적/폭력적 콘텐츠</li>
                    <li>타인의 개인정보 노출</li>
                    <li>"100% 효과 보장" 등 과장 표현</li>
                    <li>타인의 사진/글 무단 사용</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>위반 시 제재 단계</h2>
        <div class="step-card step-1">
            <div class="step-num">1</div>
            <div class="step-info">
                <h4>경고 + 콘텐츠 삭제</h4>
                <p>최초 위반 시 경고 알림과 함께 해당 콘텐츠가 삭제됩니다.</p>
            </div>
        </div>
        <div class="step-card step-2">
            <div class="step-num">2</div>
            <div class="step-info">
                <h4>7일 계정 정지</h4>
                <p>2회 위반 시 7일간 글 작성, 댓글, 채팅이 제한됩니다.</p>
            </div>
        </div>
        <div class="step-card step-3">
            <div class="step-num">3</div>
            <div class="step-info">
                <h4>30일 계정 정지</h4>
                <p>3회 위반 시 30일간 모든 서비스 이용이 제한됩니다.</p>
            </div>
        </div>
        <div class="step-card step-4">
            <div class="step-num">4</div>
            <div class="step-info">
                <h4>영구 차단</h4>
                <p>4회 이상 또는 심각한 위반 시 계정이 영구 차단됩니다.</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>신고 방법</h2>
        <div class="how-to">
            <div class="how-step">
                <div class="how-icon">1</div>
                <div class="how-text">
                    <strong>신고 버튼 클릭</strong>
                    후기, 댓글, 질문, 채팅 등에서 깃발 아이콘(신고) 버튼을 누릅니다.
                </div>
            </div>
            <div class="how-step">
                <div class="how-icon">2</div>
                <div class="how-text">
                    <strong>신고 사유 선택</strong>
                    스팸, 괴롭힘, 혐오발언, 성적콘텐츠, 폭력, 허위정보, 저작권, 기타 중 선택합니다.
                </div>
            </div>
            <div class="how-step">
                <div class="how-icon">3</div>
                <div class="how-text">
                    <strong>상세 설명 (선택)</strong>
                    필요한 경우 구체적인 설명을 추가합니다.
                </div>
            </div>
            <div class="how-step">
                <div class="how-icon">4</div>
                <div class="how-text">
                    <strong>운영팀 검토 (24시간 이내)</strong>
                    운영팀이 신고를 확인하고 적절한 조치를 취합니다.
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>차단 기능</h2>
        <div class="how-to">
            <h3>사용자 차단하기</h3>
            <p>불쾌한 사용자의 콘텐츠를 더 이상 보고 싶지 않다면 차단 기능을 사용하세요.</p>
            <ul style="margin-top: 12px;">
                <li>해당 사용자의 후기, 댓글, 질문이 <strong>즉시 숨겨집니다</strong></li>
                <li>해당 사용자의 채팅 메시지가 <strong>더 이상 표시되지 않습니다</strong></li>
                <li>차단 사실은 운영팀에 자동으로 <strong>통보됩니다</strong></li>
                <li>마이페이지 > 설정에서 <strong>차단 해제</strong>가 가능합니다</li>
            </ul>
        </div>
    </div>

    <div class="section">
        <h2>이의 신청 (Appeal)</h2>
        <p>제재가 부당하다고 판단되는 경우 이의를 신청할 수 있습니다.</p>
        <ol>
            <li><strong>이메일</strong>: admin@seoulbeauty.app로 "이의 신청" 제목으로 발송</li>
            <li><strong>내용</strong>: 회원 ID, 제재 내용, 이의 사유를 포함</li>
            <li><strong>검토</strong>: 접수 후 72시간 이내에 결과를 알려드립니다</li>
        </ol>
    </div>

    <div class="info-box">
        <h3>관련 문서</h3>
        <ul>
            <li><a href="terms_of_service.php" style="color: var(--primary);">이용약관</a></li>
            <li><a href="privacy_policy.php" style="color: var(--primary);">개인정보처리방침</a></li>
        </ul>
    </div>

    <div class="effective">
        <p>시행일: 2026년 3월 17일 | 버전 1.0</p>
    </div>
</div>

<script>
(function() {
    const dm = localStorage.getItem('dark_mode') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', dm);
})();
</script>
</body>
</html>

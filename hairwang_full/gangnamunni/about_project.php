<!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>서울미녀 소개 | 서울미녀</title>
    <meta name="description" content="피부과·성형외과 미용시술 정보를 확인하고 시술별·지역별 가격을 비교하는 서울미녀 앱을 소개합니다.">

    <script>
        // Keep downward gestures available for normal page scrolling in the app.
        window._pullToRefreshInstalled = true;
    </script>
    <style>
        * { box-sizing: border-box; }
        html { overscroll-behavior-y: none; }
        body { margin: 0; background: #fff; color: #171717; font-family: -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", sans-serif; }
        .about-page { width: 100%; max-width: 640px; margin: 0 auto; }
        .about-header { height: auto; aspect-ratio: 640 / 70; display: flex; align-items: center; justify-content: space-between; padding: 0 3.2%; background: #faf8f7; }
        .about-brand { width: 19%; min-width: 72px; max-width: 128px; display: block; }
        .about-brand img { display: block; width: 100%; height: auto; }
        .about-share { display: flex; align-items: center; gap: 6px; border: 0; padding: 6px 0 6px 10px; color: #333; background: transparent; font: inherit; font-size: clamp(12px, 5.1vw, 33px); cursor: pointer; }
        .about-share svg { width: 1em; height: 1em; }
        .about-layout { position: relative; }
        .about-layout > svg { display: block; width: 100%; height: auto; }
        .google-play-link, .app-store-link { position: absolute; top: 98.12%; width: 35.4%; height: .95%; border-radius: 5px; }
        .google-play-link { left: 11.3%; }
        .app-store-link { left: 53.3%; }
        :focus-visible { outline: 3px solid #AC1830; outline-offset: 3px; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .share-copy-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 30; background: #222; color: #fff; border-radius: 8px; padding: 12px 18px; opacity: 0; pointer-events: none; transition: opacity .2s; }
        .share-copy-toast.is-visible { opacity: 1; }
    </style>
</head>
<body>
<div class="about-page">
    <header class="about-header">
        <a class="about-brand" href="index.php" aria-label="서울미녀 홈"><img src="assets/images/logo_129.jpg" alt="서울미녀"></a>
        <button type="button" class="about-share" id="pageShareCopyBtn" aria-label="공유하기"><span>공유하기</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M14 3h7v7M21 3 10 14M9 3H3v18h18v-6"/></svg></button>
    </header>
    <main aria-labelledby="intro-title">
        <h1 id="intro-title" class="sr-only">서울미녀 서비스 소개</h1>
        <div class="about-layout">
            <!-- Omit only the reference's baked-in header; the controls above remain interactive. -->
            <svg width="640" height="7872" viewBox="0 70 640 7872" role="img" aria-labelledby="layout-title layout-description" overflow="hidden">
                <title id="layout-title">서울미녀 앱 소개</title>
                <desc id="layout-description">피부과·성형외과 미용시술 가격 비교 플랫폼. 다양한 미용시술 정보를 한곳에서 확인하고 시술별·지역별로 검색하세요. 낮은 가격, 평점, 인기, 후기를 비교할 수 있어요. 같은 시술도 병원과 이벤트에 따라 가격이 달라요. 예약 전 병원 정보와 위치, 의료진을 확인하고 원하는 날짜와 시간을 선택하세요. 예약 요청 후 병원 확인을 거쳐 확정되며 알림으로 진행 상황을 안내해드려요. 서울미녀 앱 다운로드.</desc>
                <image href="assets/media/about-project-layout.jpg?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-project-layout.jpg') ?>" width="640" height="7942" />
            </svg>
            <a class="google-play-link" href="https://play.google.com/store/apps/details?id=com.celebhospital.patient&amp;pcampaignid=web_share" target="_blank" rel="noopener noreferrer" aria-label="Google Play에서 서울미녀 앱 다운로드"></a>
            <a class="app-store-link" href="https://apps.apple.com/kr/app/서울미녀/id6805706423" target="_blank" rel="noopener noreferrer" aria-label="App Store에서 서울미녀 앱 다운로드"></a>
        </div>
    </main>
</div>
<div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite"></div>
<script>
(function () {
    var toastTimer = null;

    function getShareText() {
        return '이거 완전 괜찮은 앱이야!\n서울미녀\n' + window.location.href;
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        var copied = document.execCommand('copy');
        document.body.removeChild(textarea);
        return copied;
    }

    function showToast(message) {
        var toast = document.getElementById('pageShareCopyToast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 1800);
    }

    function copyShareText() {
        var shareText = getShareText();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(shareText).then(function () {
                showToast('링크가 복사되었습니다.');
            }).catch(function () {
                showToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
            });
            return;
        }
        showToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('pageShareCopyBtn');
        if (button) button.addEventListener('click', copyShareText);
    });
}());
</script>
</body>
</html>

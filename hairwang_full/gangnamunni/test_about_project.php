<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>서울미녀 소개 | 서울미녀</title>
    <meta name="description" content="시술별·지역별 미용시술 정보를 확인하고 등록된 가격을 비교하는 서울미녀를 소개합니다.">
    <link rel="icon" type="image/png" href="assets/images/favicon-32.png">
    <style>
    #treatments-scene img { pointer-events: none; }
    #treatments-scene .treatments-title { clip-path: inset(0 0 74% 0); }
    #treatments-scene .treatments-connector { clip-path: inset(26% 0 67.3% 0); }
    #treatments-scene .treatments-card { clip-path: inset(32.7% 0 7.6% 0); }
    #treatments-scene .treatments-footer { clip-path: inset(92.4% 0 0 0); }
    #treatments-scene:not([hidden]) .treatments-title { animation: treatment-reveal 700ms ease-out 1100ms both; }
    #treatments-scene:not([hidden]) .treatments-connector { animation: treatment-line-down 450ms linear 1650ms both; }
    #treatments-scene:not([hidden]) .treatments-card { animation: treatment-rise 850ms cubic-bezier(.22,.61,.36,1) 1950ms both; }
    #treatments-scene:not([hidden]) .treatments-footer { animation: treatment-footer-down 450ms linear 2600ms both; }
    @keyframes treatment-line-down {
        from { clip-path: inset(26% 0 74% 0); }
        to { clip-path: inset(26% 0 67.3% 0); }
    }
    @keyframes treatment-footer-down {
        from { clip-path: inset(92.4% 0 7.6% 0); }
        to { clip-path: inset(92.4% 0 0 0); }
    }
    @keyframes treatment-reveal { from { opacity: 0; } to { opacity: 1; } }
    @keyframes treatment-rise {
        from { opacity: 0; transform: translate(-50%, 24px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @media (prefers-reduced-motion: reduce) {
        #treatments-scene:not([hidden]) img { animation: none; }
    }

    :root { color-scheme: light; font-family: -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Malgun Gothic", sans-serif; color: #171717; background: #fff; }
    * { box-sizing: border-box; }
    body { margin: 0; --intro-header-height: 64px; }
    .about-header { position: fixed; inset: 0 0 auto; z-index: 20; width: 100%; max-width: 640px; height: var(--intro-header-height); margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; background: #faf8f7; }
    .about-brand img { display: block; width: 105px; height: auto; }
    .about-share { display: flex; align-items: center; gap: 12px; border: 0; padding: 10px 0 10px 12px; background: transparent; color: #111; font: inherit; font-size: 20px; cursor: pointer; }
    .about-share svg { width: 24px; height: 24px; }
    .share-copy-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 30; background: #222; color: #fff; border-radius: 8px; padding: 12px 18px; opacity: 0; pointer-events: none; transition: opacity .2s; }
    .share-copy-toast.is-visible { opacity: 1; }
    @media (max-width: 480px) { body { --intro-header-height: 56px; } .about-header { padding: 0 16px; } .about-brand img { width: 88px; } .about-share { font-size: 17px; } }

    button, a { -webkit-tap-highlight-color: transparent; }
    [hidden] { display: none !important; }
    .introduction { max-width: 640px; padding-top: var(--intro-header-height); margin: 0 auto; background: #fff; }
    .intro-stage { position: relative; display: grid; place-items: center; height: calc(100vh - var(--intro-header-height)); height: calc(100svh - var(--intro-header-height)); min-height: 0; overflow: hidden; background: #fff; }
    .intro-hero { position: absolute; inset: 0; display: grid; place-items: center; background: #faf8f7; overflow: hidden; }
    .intro-hero img { position: absolute; left: 50%; top: 0; transform: translateX(-50%); display: block; width: auto; max-width: none; height: 100%; min-height: 0; }
    .intro-stage.filtering-scroll { touch-action: pan-y pinch-zoom; }
    #filtering-scene { display: block; overflow-y: auto; overscroll-behavior-y: contain; touch-action: pan-y pinch-zoom; }
    #filtering-scene img { position: static; transform: none; width: 100%; height: auto; max-width: 100%; }
    :focus-visible { outline: 2px solid #ae1932; outline-offset: -2px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    /* Vertical gestures advance one scene; retain horizontal gestures and pinch zoom. */
    .intro-stage { touch-action: pan-x pinch-zoom; overscroll-behavior: contain; }
    .introduction.scroll-mode .intro-stage { touch-action: pan-y pinch-zoom; overscroll-behavior: auto; }
    .booking-ending { width: 100%; max-width: 640px; margin: 0 auto; }
    .booking-ending-visual { position: relative; width: 100%; }
    .booking-confirmation { width: calc(100% - 32px); max-width: 560px; margin: 24px auto 0; }
    .booking-confirmation svg, .booking-ending-visual svg { display: block; width: 100%; height: auto; }
    .booking-ending-visual { margin-top: clamp(24px, 6vw, 48px); }
    .google-play-link { position: absolute; left: 7.81%; top: 66.94%; width: 39.59%; height: 17.42%; border-radius: 8px; }
    /* Price comparison animation. */
    .price-scene { position: absolute; inset: 0; display: grid; place-items: center; background: #fff; }
    .hospital-phone { position: absolute; inset: 0; display: grid; place-items: center; background: white; }
    .phone-layout { position: relative; width: min(100%, calc((100svh - var(--intro-header-height)) * .687382)); aspect-ratio: 730 / 1062; container-type: inline-size; }
    .phone-heading { position: absolute; top: 4.8%; left: 0; width: 100%; margin: 0; text-align: center; font-size: 4.38cqw; line-height: 1.4; font-weight: 700; letter-spacing: -.03em; transition: opacity 800ms ease; }
    .show-booking .phone-heading { opacity: 0; }
    .booking-heading { position: absolute; top: 1.2%; left: 0; width: 100%; margin: 0; text-align: center; font-size: 4.38cqw; line-height: 1.6; font-weight: 700; letter-spacing: -.03em; opacity: 0; transform: translateY(2cqw); transition: opacity 1500ms ease, transform 1500ms cubic-bezier(.22,.61,.36,1); }
    .booking-heading span { display: block; white-space: nowrap; }
    .show-booking .booking-heading { opacity: 1; transform: translateY(0); }
    /* Keep the original phone silhouette stationary. Only the clipped screen moves. */
    .phone-shell { position: absolute; top: 12%; left: 20.4%; width: 59.2%; aspect-ratio: 614 / 1280; background: url('assets/media/about-phone-frame.jpeg') center / 100% 100% no-repeat; }
    .phone-screen { position: absolute; left: 4.6%; right: 4.6%; top: 8%; bottom: 4%; overflow: hidden; background: #fff; border-radius: 0 0 7% 7%; }
    .hospital-content { position: absolute; inset: 0; overflow: hidden; background: #fff; }
    .hospital-content svg { display: block; width: 100%; height: auto; pointer-events: none; user-select: none; }
    @keyframes price-scene-enter {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    @keyframes scene-return {
        from { transform: translateY(-100%); }
        to { transform: translateY(0); }
    }
    #intro-stage .scene-enter:not([hidden]) { z-index: 5; animation: price-scene-enter 1500ms cubic-bezier(.22,.61,.36,1) both; }
    #intro-stage .scene-return:not([hidden]) { z-index: 5; animation: scene-return 1500ms cubic-bezier(.22,.61,.36,1) both; }
    @media (prefers-reduced-motion: reduce) {
        #intro-stage .scene-enter:not([hidden]), #intro-stage .scene-return:not([hidden]) { animation: none; }
        .phone-heading, .booking-heading { transition: none; }
    }
    .price-frame { width: min(100%, calc((100svh - var(--intro-header-height)) * .64)); aspect-ratio: 16 / 25; container-type: inline-size; display: flex; align-items: center; }
    .price-content { width: 100%; padding: 0 8%; text-align: center; }
    .price-content h2 { margin: 0 0 5cqw; font-size: 4.6cqw; line-height: 1.5; font-weight: 700; letter-spacing: -.035em; white-space: nowrap; }
    .price-description, .price-note { margin: 0; font-size: 3.2cqw; line-height: 1.8; letter-spacing: -.035em; }
    .price-chart { margin: 6cqw 0; padding: 3.2cqw; border: 1px solid #f4f4f4; border-radius: 3px; box-shadow: 0 1px 8px #00000005; text-align: left; }
    .price-chart h3 { margin: 0 0 3.8cqw; font-size: 2.5cqw; line-height: 1.5; font-weight: 700; }
    .price-row { display: grid; grid-template-columns: 9cqw minmax(0, 1fr) 11cqw; gap: 1.4cqw; align-items: center; margin-bottom: 4cqw; }
    .price-row:last-child { margin-bottom: 1.2cqw; }
    .price-label { color: #999; font-size: 2.2cqw; }
    .price-value { font-size: 2.2cqw; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .price-track { display: block; height: 1cqw; min-height: 3px; background: #fbd4df; border-radius: 99px; overflow: hidden; }
    .price-fill { display: block; width: 0; height: 100%; border-radius: inherit; background: #ae1932; }
    </style>
</head>
<body>
<header class="about-header">
    <a class="about-brand" href="index.php" aria-label="서울미녀 홈"><img src="assets/images/logo_129.jpg" alt="서울미녀"></a>
    <button type="button" class="about-share" id="pageShareCopyBtn" aria-label="공유하기"><span>공유하기</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M14 3h7v7M21 3 10 14M9 3H3v18h18v-6"/></svg></button>
</header>
<div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite"></div>
<main class="introduction" aria-labelledby="intro-title">
    <h1 id="intro-title" class="sr-only">서울미녀 서비스 소개</h1>
    <div id="intro-stage" class="intro-stage">
        <section id="intro-hero" class="intro-hero" aria-label="서울미녀 미용시술 가격 비교 플랫폼">
            <img src="assets/media/about-intro-hero.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-intro-hero.png') ?>" width="778" height="1220" fetchpriority="high" alt="서울미녀. 피부과·성형외과 미용시술 가격 비교 플랫폼. 미용시술, 더 쉽고 합리적으로 비교.">
        </section>
        <section id="treatments-scene" class="intro-hero" aria-label="미용시술 정보와 시술 종류" hidden>
            <img class="treatments-title" src="assets/media/about-treatments.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-treatments.png') ?>" width="778" height="1098" alt="피부과·성형외과의 다양한 미용시술 정보를 한곳에서 확인하고. 리프팅, 보톡스, 필러 등 시술별 정보.">
            <img class="treatments-connector" aria-hidden="true" src="assets/media/about-treatments.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-treatments.png') ?>" width="778" height="1098" alt="">
            <img class="treatments-card" aria-hidden="true" src="assets/media/about-treatments.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-treatments.png') ?>" width="778" height="1098" alt="">
            <img class="treatments-footer" aria-hidden="true" src="assets/media/about-treatments.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-treatments.png') ?>" width="778" height="1098" alt="">
        </section>
        <section id="filtering-scene" class="intro-hero" tabindex="-1" aria-label="시술별·지역별 검색과 가격 비교" hidden>
            <img src="assets/media/about-filtering.png" width="546" height="1292" alt="시술별·지역별 검색하여 가장 합리적인 가격만 한눈에. 낮은가격, 평점, 인기, 후기 기준으로 병원과 시술을 비교하세요.">
        </section>
        <section id="price-scene" class="price-scene" aria-labelledby="price-heading" tabindex="-1" hidden>
            <div class="price-frame">
                <div class="price-content">
                    <h2 id="price-heading">같은 시술, 가격은 어떻게 다를까요?</h2>
                    <p class="price-description">서울미녀는 시술과 지역을 기준으로<br>등록된 가격 정보를 손쉽게 비교할 수 있도록 구성했어요.</p>
                    <div class="price-chart" aria-label="보톡스 예시: 강남 지역 등록 가격">
                        <h3>보톡스 (예시) · 강남 지역 등록 가격</h3>
                        <?php foreach ([['A', 39000, 27], ['B', 55000, 54], ['C', 72000, 81]] as [$label, $price, $width]): ?>
                        <div class="price-row" data-price="<?= $price ?>" data-width="<?= $width ?>" aria-label="<?= $label ?> 병원 <?= number_format($price) ?>원">
                            <span class="price-label" aria-hidden="true"><?= $label ?> 병원</span>
                            <span class="price-track" aria-hidden="true"><span class="price-fill"></span></span>
                            <span class="price-value" aria-hidden="true">0원</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="price-note">동일하거나 유사한 미용시술도<br>병원과 이벤트에 따라 다양한 가격으로 제공되고 있어요.</p>
                </div>
            </div>
        </section>
        <section id="hospital-phone" class="hospital-phone" aria-label="병원 정보 소개" hidden>
            <div class="phone-layout">
                <h2 class="phone-heading">예약 전, 병원 정보를 꼼꼼하게</h2>
                <h2 class="booking-heading" aria-hidden="true"><span>원하는 시술을</span><span>복잡한 과정 없이 간편하게 예약하세요</span></h2>
                <div class="phone-shell">
                    <div class="phone-screen">
                        <section id="hospital-scene" class="hospital-content" aria-label="병원 상세 정보" tabindex="-1" hidden>
                            <svg width="396" height="778" viewBox="163 196 396 778" preserveAspectRatio="xMidYMin meet" role="img" aria-label="병원 사진, 시술 안내와 상세 정보" overflow="hidden">
                                <image href="assets/media/about-hospital-details.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-details.png') ?>" width="730" height="1062" />
                            </svg>
                        </section>
                        <section id="hospital-team-scene" class="hospital-content" aria-label="병원 위치와 의료진 소개" tabindex="-1" hidden>
                            <svg width="388" height="774" viewBox="185 130 388 774" preserveAspectRatio="xMidYMin meet" role="img" aria-label="병원 위치 지도, 병원 소개와 의료진, 상담 및 예약 안내" overflow="hidden">
                                <image href="assets/media/about-hospital-team.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-team.png') ?>" width="730" height="1062" />
                            </svg>
                        </section>
                        <section id="hospital-booking-scene" class="hospital-content" aria-label="시술 예약 날짜와 시간 선택 예시" tabindex="-1" hidden>
                            <svg width="395" height="784" viewBox="165 158 395 784" preserveAspectRatio="xMidYMin meet" role="img" aria-label="예약할 시술과 가격을 확인하고 방문 날짜와 시간을 선택하는 예약 화면 예시" overflow="hidden">
                                <image href="assets/media/about-hospital-booking.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-booking.png') ?>" width="730" height="1062" />
                            </svg>
                        </section>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <section id="booking-ending" class="booking-ending" aria-label="예약 신청 안내와 서울미녀 앱 소개" hidden>
        <div class="booking-confirmation">
            <svg width="394" height="620" viewBox="280 20 394 620" role="img" aria-label="예약 요청이 접수되었어요. 병원 확인 후 예약이 확정됩니다. 알림을 꼭 확인해주세요." overflow="hidden">
                <image href="assets/media/about-booking-ending.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-booking-ending.png') ?>" width="922" height="1380" />
            </svg>
        </div>
        <div class="booking-ending-visual">
            <svg width="922" height="620" viewBox="0 760 922 620" role="img" aria-label="서울미녀 앱, Google Play 및 App Store 소개" overflow="hidden">
                <image href="assets/media/about-booking-ending.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-booking-ending.png') ?>" width="922" height="1380" />
            </svg>
            <a class="google-play-link" href="https://play.google.com/store/apps/details?id=com.celebhospital.patient&amp;pcampaignid=web_share" target="_blank" rel="noopener noreferrer" aria-label="Google Play에서 서울미녀 앱 다운로드"></a>
        </div>
    </section>
    <div class="sr-only" id="intro-description">
        <div>
            <p>피부과·성형외과의 다양한 미용시술 정보를 한곳에서 확인하고, 시술별·지역별로 검색하여 가격을 비교할 수 있는 미용의료 정보 플랫폼이에요.</p>
            <h2>같은 시술, 가격은 어떻게 다를까요?</h2>
            <p>서울미녀는 시술과 지역을 기준으로 등록된 가격 정보를 손쉽게 비교할 수 있도록 구성했어요. 동일하거나 유사한 미용시술도 병원과 이벤트에 따라 다양한 가격으로 제공되고 있어요.</p>
            <p>가격과 병원 목록은 서비스 설명을 위한 예시입니다.</p>
        </div>
    </div>
</main>
<script>
(function () {
    'use strict';
    var stage = document.querySelector('.intro-stage');
    var priceScene = document.getElementById('price-scene');
    var hospitalPhone = document.getElementById('hospital-phone');
    var hospitalScenes = Array.from(document.querySelectorAll('.hospital-content'));
    var priceRows = Array.from(priceScene.querySelectorAll('.price-row'));
    var priceAnimation = null;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    // The remaining introduction starts with prices, followed by image scenes.
    var firstHospitalScene = 4;
    var imageScenes = Array.from(document.querySelectorAll('.intro-hero'));
    var scene = 0;
    var moving = false;
    var outgoingPanel = null;
    var transitionDirection = 1;
    var touchStart = null;
    var wheelLocked = false;
    var wheelTimer;
    var scrollMode = false;

    function updateState() {
        stage.classList.toggle('filtering-scroll', scene === 2);
        stage.dataset.scene = String(scene + 1);
        stage.dataset.playing = String(moving);
        var totalScenes = firstHospitalScene + hospitalScenes.length;
        var label = '서울미녀 소개 ' + (scene + 1) + '/' + totalScenes;
        if (scene < totalScenes - 1) label += '. 위로 밀면 다음 내용을 볼 수 있습니다.';
        stage.setAttribute('aria-label', label);
    }
    function renderPrices(progress) {
        priceRows.forEach(function (row, index) {
            var local = Math.max(0, Math.min(1, (progress * 1690 - index * 120) / 1450));
            var eased = 1 - Math.pow(1 - local, 3);
            row.querySelector('.price-fill').style.width = (Number(row.dataset.width) * eased) + '%';
            var value = Math.round(Number(row.dataset.price) * eased);
            row.querySelector('.price-value').textContent = value.toLocaleString('ko-KR') + '원';
        });
    }
    function completeReturn() {
        if (outgoingPanel) outgoingPanel.hidden = true;
        outgoingPanel = null;
        stage.querySelectorAll('.scene-enter, .scene-return').forEach(function (panel) {
            panel.classList.remove('scene-enter', 'scene-return');
        });
        if (scene < firstHospitalScene) hospitalPhone.hidden = true;
        if (scene < imageScenes.length) priceScene.hidden = true;
    }
    function enterPanel(panel) {
        panel.classList.remove('scene-enter', 'scene-return');
        panel.hidden = false;
        panel.classList.add(transitionDirection < 0 ? 'scene-return' : 'scene-enter');
    }
    function panelForScene(index) {
        if (index < imageScenes.length) return imageScenes[index];
        if (index < firstHospitalScene) return priceScene;
        return hospitalScenes[index - firstHospitalScene];
    }
    function finishPrices() {
        completeReturn();
        imageScenes.forEach(function (panel) { panel.hidden = true; });
        document.getElementById('intro-hero').hidden = true;
        if (priceAnimation !== null) cancelAnimationFrame(priceAnimation);
        priceAnimation = null;
        renderPrices(1);
        moving = false;
        updateState();
    }
    function showPrices() {
        enterPanel(priceScene);
        moving = true;
        updateState();
        renderPrices(0);
        if (reduceMotion.matches) { finishPrices(); return; }
        var started = null;
        function tick(now) {
            if (started === null) started = now;
            // Let the final section slide into place before growing its prices.
            var elapsed = now - started;
            var progress = Math.max(0, Math.min(1, (elapsed - 1500) / 1690));
            renderPrices(progress);
            if (progress < 1) priceAnimation = requestAnimationFrame(tick);
            else finishPrices();
        }
        priceAnimation = requestAnimationFrame(tick);
    }
    function finishHospital() {
        completeReturn();
        priceScene.hidden = true;
        hospitalScenes.forEach(function (panel, index) {
            panel.hidden = index !== scene - firstHospitalScene;
        });
        moving = false;
        if (scene === firstHospitalScene + hospitalScenes.length - 1) {
            scrollMode = true;
            document.querySelector('.introduction').classList.add('scroll-mode');
            document.getElementById('booking-ending').hidden = false;
            touchStart = null;
            clearTimeout(wheelTimer);
            wheelLocked = false;
        }
        updateState();
    }
    function showHospital() {
        var hospitalScene = hospitalScenes[scene - firstHospitalScene];
        var previousScene = scene === firstHospitalScene ? priceScene : hospitalScenes[scene - firstHospitalScene - 1];
        var enteringPhone = hospitalPhone.hidden;
        if (enteringPhone) enterPanel(hospitalPhone);
        var isBooking = hospitalScene.id === 'hospital-booking-scene';
        hospitalPhone.classList.toggle('show-booking', isBooking);
        hospitalPhone.querySelector('.booking-heading').setAttribute('aria-hidden', String(!isBooking));
        hospitalPhone.querySelector('.phone-heading').setAttribute('aria-hidden', String(isBooking));
        if (enteringPhone) hospitalScene.hidden = false;
        else enterPanel(hospitalScene);
        if (previousScene.contains(document.activeElement)) hospitalScene.focus({ preventScroll: true });
        moving = true;
        updateState();
        if (reduceMotion.matches) finishHospital();
    }
    hospitalPhone.addEventListener('animationend', function (event) {
        if (event.target === hospitalPhone && scene === firstHospitalScene) finishHospital();
    });
    hospitalScenes.forEach(function (panel) {
        panel.addEventListener('animationend', function (event) {
            if (event.target === panel && panel === hospitalScenes[scene - firstHospitalScene]) finishHospital();
        });
    });
    function finishImageScene() {
        completeReturn();
        imageScenes.forEach(function (panel, index) { panel.hidden = index !== scene; });
        moving = false;
        updateState();
    }
    imageScenes.forEach(function (panel, index) {
        panel.addEventListener('animationend', function (event) {
            if (event.target === panel && scene === index) finishImageScene();
        });
    });
    function playScene() {
        if (scene === 0 && !outgoingPanel) { imageScenes[0].hidden = false; updateState(); return; }
        if (scene < imageScenes.length) {
            enterPanel(imageScenes[scene]);
            moving = true;
            updateState();
            if (reduceMotion.matches) finishImageScene();
            return;
        }
        if (scene >= firstHospitalScene) showHospital();
        else showPrices();
    }
    function navigate(direction) {
        if (moving) return;
        if (direction < 0 && scene > 0) {
            outgoingPanel = panelForScene(scene);
            scene--;
            transitionDirection = -1;
            playScene();
            return;
        }
        if (scene === firstHospitalScene + hospitalScenes.length - 1) return;
        if (direction > 0) {
            outgoingPanel = panelForScene(scene);
            transitionDirection = 1;
            scene++;
            playScene();
        }
    }
    function filteringAtBottom() {
        var panel = document.getElementById('filtering-scene');
        return panel.scrollTop + panel.clientHeight >= panel.scrollHeight - 2;
    }
    function filteringAtTop() {
        return document.getElementById('filtering-scene').scrollTop <= 2;
    }
    stage.addEventListener('touchstart', function (event) {
        if (scrollMode) return;
        touchStart = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY, canAdvance: scene !== 2 || filteringAtBottom(), canGoBack: scene !== 2 || filteringAtTop() } : null;
    }, { passive: true });
    stage.addEventListener('touchend', function (event) {
        if (scrollMode) return;
        if (!touchStart || !event.changedTouches.length) return;
        var dx = event.changedTouches[0].clientX - touchStart.x;
        var dy = event.changedTouches[0].clientY - touchStart.y;
        var canAdvance = touchStart.canAdvance;
        var canGoBack = touchStart.canGoBack;
        touchStart = null;
        if (dy < 0 && (!canAdvance || (scene === 2 && !filteringAtBottom()))) return;
        if (dy > 0 && (!canGoBack || (scene === 2 && !filteringAtTop()))) return;
        if (Math.abs(dy) >= 40 && Math.abs(dy) > Math.abs(dx)) navigate(dy < 0 ? 1 : -1);
    }, { passive: true });
    stage.addEventListener('touchcancel', function () { touchStart = null; }, { passive: true });
    stage.addEventListener('wheel', function (event) {
        if (scrollMode) return;
        if (event.ctrlKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
        if (scene === 2 && ((event.deltaY < 0 && !filteringAtTop()) || (event.deltaY > 0 && !filteringAtBottom()))) {
            wheelLocked = true;
            clearTimeout(wheelTimer);
            wheelTimer = setTimeout(function () { wheelLocked = false; }, 250);
            return;
        }
        event.preventDefault();
        clearTimeout(wheelTimer);
        wheelTimer = setTimeout(function () { wheelLocked = false; }, 250);
        if (wheelLocked || Math.abs(event.deltaY) < 8) return;
        wheelLocked = true;
        navigate(event.deltaY > 0 ? 1 : -1);
    }, { passive: false });
    document.addEventListener('keydown', function (event) {
        if (scrollMode || (event.target.closest && event.target.closest('button, a, input, textarea, select'))) return;
        var direction = ['ArrowDown', 'PageDown', ' '].includes(event.key) ? 1
            : ['ArrowUp', 'PageUp'].includes(event.key) ? -1 : 0;
        if (scene === 2 && ((direction > 0 && !filteringAtBottom()) || (direction < 0 && !filteringAtTop()))) {
            event.preventDefault();
            var panel = document.getElementById('filtering-scene');
            panel.scrollBy({ top: direction * (['ArrowDown', 'ArrowUp'].includes(event.key) ? 40 : panel.clientHeight * .85) });
            return;
        }
        if (!direction || event.repeat) return;
        event.preventDefault();
        navigate(direction);
    });
    reduceMotion.addEventListener('change', function (event) {
        if (event.matches && scene < imageScenes.length) { finishImageScene(); return; }
        if (event.matches && scene >= firstHospitalScene) finishHospital();
        else if (event.matches && scene === firstHospitalScene - 1) finishPrices();
    });
    updateState();
    playScene();
})();
</script>
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

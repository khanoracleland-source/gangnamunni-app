<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>서울미녀 소개 | 서울미녀</title>
    <meta name="description" content="시술별·지역별 미용시술 정보를 확인하고 등록된 가격을 비교하는 서울미녀를 소개합니다.">
    <link rel="icon" type="image/png" href="assets/images/favicon-32.png">
    <style>
    :root { color-scheme: light; font-family: -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Malgun Gothic", sans-serif; color: #171717; background: #fff; }
    * { box-sizing: border-box; }
    body { margin: 0; }
    button, a { -webkit-tap-highlight-color: transparent; }
    [hidden] { display: none !important; }
    .introduction { max-width: 640px; margin: 0 auto; background: #fff; }
    .intro-stage { position: relative; display: grid; place-items: center; height: 100vh; height: 100svh; min-height: 280px; overflow: hidden; background: #fff; }
    .intro-video { display: block; width: 100%; height: 100%; min-height: 0; object-fit: contain; background: #fff; }
    :focus-visible { outline: 2px solid #ae1932; outline-offset: -2px; }
    .intro-error { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; padding: 40px; background: #fff; text-align: center; line-height: 1.8; }
    .intro-error h2 { color: #ae1932; font-size: 26px; }
    .intro-error-detail { color: #888; font-size: 13px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    /* Vertical gestures advance one scene; retain horizontal gestures and pinch zoom. */
    .intro-stage { touch-action: pan-x pinch-zoom; overscroll-behavior: contain; }
    .introduction.scroll-mode .intro-stage { touch-action: pan-y pinch-zoom; overscroll-behavior: auto; }
    .booking-ending { width: min(100%, 68.7382svh); margin: 0 auto; }
    .booking-ending-visual { position: relative; width: 100%; }
    .booking-ending-visual img { width: 100%; height: auto; display: block; }
    .google-play-link { position: absolute; left: 7.81%; top: 85.14%; width: 39.59%; height: 7.83%; border-radius: 8px; }
    /* Native final scene: the bars and prices animate independently of the video. */
    .price-scene { position: absolute; inset: 0; display: grid; place-items: center; background: #fff; }
    .price-scene:not([hidden]), .hospital-phone:not([hidden]) { animation: price-scene-enter 1500ms cubic-bezier(.22, .61, .36, 1) both; }
    .hospital-phone { position: absolute; inset: 0; display: grid; place-items: center; background: white; }
    .phone-layout { position: relative; width: min(100%, 68.7382svh); aspect-ratio: 730 / 1062; container-type: inline-size; }
    .phone-heading { position: absolute; top: 4.8%; left: 0; width: 100%; margin: 0; text-align: center; font-size: 4.38cqw; line-height: 1.4; font-weight: 700; letter-spacing: -.03em; transition: opacity 800ms ease; }
    .show-team .phone-heading { opacity: 0; }
    .booking-heading { position: absolute; top: 1.2%; left: 0; width: 100%; margin: 0; text-align: center; font-size: 4.38cqw; line-height: 1.6; font-weight: 700; letter-spacing: -.03em; opacity: 0; transform: translateY(2cqw); transition: opacity 1500ms ease, transform 1500ms cubic-bezier(.22,.61,.36,1); }
    .booking-heading span { display: block; white-space: nowrap; }
    .show-booking .booking-heading { opacity: 1; transform: translateY(0); }
    /* Keep the original phone silhouette stationary. Only the clipped screen moves. */
    .phone-shell { position: absolute; top: 13.84%; left: 21.23%; width: 59.18%; aspect-ratio: 432 / 902; background: url('assets/media/about-hospital-details.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-details.png') ?>') 48.6577% 84.375% / 168.9815% auto no-repeat; }
    .phone-screen { position: absolute; left: 4.167%; top: 5.987%; width: 91.898%; aspect-ratio: 397 / 811; overflow: hidden; border-radius: 0 0 9% 9%; background: #fff; }
    .hospital-content { position: absolute; inset: 0; overflow: hidden; background: #fff; }
    .hospital-content img { position: absolute; display: block; max-width: none; width: 183.8791%; height: auto; pointer-events: none; user-select: none; }
    #hospital-scene img { left: -41.058%; top: -23.3046%; }
    #hospital-team-scene img { left: -45.592%; top: -14.6732%; clip-path: inset(0 0 14.97% 0); }
    #hospital-booking-scene img { left: -41.5617%; top: -18.4957%; clip-path: inset(0 0 10.55% 0); }
    .hospital-content:not(#hospital-scene):not([hidden]) { animation: price-scene-enter 1500ms cubic-bezier(.22,.61,.36,1) both; }
    @keyframes price-scene-enter {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .price-scene:not([hidden]), .hospital-phone:not([hidden]), .hospital-content:not(#hospital-scene):not([hidden]) { animation: none; }
        .phone-heading, .booking-heading { transition: none; }
    }
    .price-frame { width: min(100%, 64svh); aspect-ratio: 16 / 25; container-type: inline-size; display: flex; align-items: center; }
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
<main class="introduction" aria-labelledby="intro-title">
    <h1 id="intro-title" class="sr-only">서울미녀 서비스 소개</h1>
    <div class="intro-stage">
        <video id="intro-video" class="intro-video" tabindex="0" muted playsinline preload="auto"
               poster="assets/media/about-project-poster.jpg" aria-label="서울미녀 서비스 소개 애니메이션"
               aria-describedby="intro-description">
            <source src="assets/media/about-project.mp4" type="video/mp4">
            <p>이 브라우저는 동영상 재생을 지원하지 않습니다. 아래 서비스 소개를 확인해주세요.</p>
        </video>
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
                            <img src="assets/media/about-hospital-details.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-details.png') ?>" width="730" height="1062" alt="병원 사진, 시술 안내와 상세 정보">
                        </section>
                        <section id="hospital-team-scene" class="hospital-content" aria-label="병원 위치와 의료진 소개" tabindex="-1" hidden>
                            <img src="assets/media/about-hospital-team.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-team.png') ?>" width="730" height="1062" alt="병원 위치 지도, 병원 소개와 의료진, 상담 및 예약 안내">
                        </section>
                        <section id="hospital-booking-scene" class="hospital-content" aria-label="시술 예약 날짜와 시간 선택 예시" tabindex="-1" hidden>
                            <img src="assets/media/about-hospital-booking.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-hospital-booking.png') ?>" width="730" height="1062" alt="예약할 시술과 가격을 확인하고 방문 날짜와 시간을 선택하는 예약 화면 예시">
                        </section>
                    </div>
                </div>
            </div>
        </section>
        <div id="intro-error" class="intro-error" hidden>
            <h2>서울미녀를 소개합니다</h2>
            <p>시술별·지역별 미용시술 정보를 한곳에서 확인하고, 병원별 등록 가격을 비교해보세요.</p>
            <p class="intro-error-detail">소개 영상을 불러오지 못했습니다. 페이지를 새로고침하여 다시 시도해주세요.</p>
        </div>
    </div>
    <section id="booking-ending" class="booking-ending" aria-label="예약 신청 안내와 서울미녀 앱 소개" hidden>
        <div class="booking-ending-visual">
            <img src="assets/media/about-booking-ending.png?v=<?= (int)filemtime(__DIR__ . '/assets/media/about-booking-ending.png') ?>" width="922" height="1380" alt="예약 요청이 접수되었어요. 병원 확인 후 예약이 확정됩니다. 서울미녀 앱, Google Play 및 App Store 소개 이미지.">
            <a class="google-play-link" href="https://play.google.com/store/apps/details?id=com.celebhospital.patient&amp;pcampaignid=web_share" target="_blank" rel="noopener noreferrer" aria-label="Google Play에서 서울미녀 앱 다운로드"></a>
        </div>
    </section>
    <div class="sr-only" id="intro-description">
        <div>
            <p>피부과·성형외과의 다양한 미용시술 정보를 한곳에서 확인하고, 시술별·지역별로 검색하여 가격을 비교할 수 있는 미용의료 정보 플랫폼이에요.</p>
            <h2>같은 시술, 가격은 어떻게 다를까요?</h2>
            <p>서울미녀는 시술과 지역을 기준으로 등록된 가격 정보를 손쉽게 비교할 수 있도록 구성했어요. 동일하거나 유사한 미용시술도 병원과 이벤트에 따라 다양한 가격으로 제공되고 있어요.</p>
            <p>영상 속 가격과 병원 목록은 서비스 설명을 위한 예시입니다.</p>
        </div>
    </div>
</main>
<script>
(function () {
    'use strict';
    var video = document.getElementById('intro-video');
    var stage = document.querySelector('.intro-stage');
    var error = document.getElementById('intro-error');
    var priceScene = document.getElementById('price-scene');
    var hospitalPhone = document.getElementById('hospital-phone');
    var hospitalScenes = Array.from(document.querySelectorAll('.hospital-content'));
    var priceRows = Array.from(priceScene.querySelectorAll('.price-row'));
    var priceAnimation = null;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    // Play through the headline and treatment card before waiting for the first swipe.
    var stops = [7.5, 9.0, 10.8, 15.9];
    var scene = 0;
    var moving = false;
    var resumeOnVisible = false;
    var frameRequest = null;
    var touchStart = null;
    var wheelLocked = false;
    var wheelTimer;
    var scrollMode = false;
    video.muted = true;

    function updateState() {
        stage.dataset.scene = String(scene + 1);
        stage.dataset.playing = String(moving);
        var totalScenes = stops.length + hospitalScenes.length;
        var label = '서울미녀 소개 ' + (scene + 1) + '/' + totalScenes;
        if (scene < totalScenes - 1) label += '. 위로 밀면 다음 내용을 볼 수 있습니다.';
        stage.setAttribute('aria-label', label);
        video.setAttribute('aria-label', label);
    }
    function cancelFrame() {
        if (frameRequest !== null && video.cancelVideoFrameCallback) video.cancelVideoFrameCallback(frameRequest);
        frameRequest = null;
    }
    function hold() {
        moving = false;
        cancelFrame();
        video.pause();
        // Keep the decoded frame when pausing forward playback. Only seek for a
        // reduced-motion navigation, avoiding a blank seek frame.
        if (Math.abs(video.currentTime - stops[scene]) > 0.15) {
            video.currentTime = Math.min(stops[scene], video.duration || stops[scene]);
        }
        updateState();
    }
    function checkFrame() {
        frameRequest = null;
        if (!moving) return;
        if (video.currentTime >= stops[scene]) { hold(); return; }
        if (video.requestVideoFrameCallback) frameRequest = video.requestVideoFrameCallback(checkFrame);
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
    function finishPrices() {
        if (priceAnimation !== null) cancelAnimationFrame(priceAnimation);
        priceAnimation = null;
        video.hidden = true;
        renderPrices(1);
        moving = false;
        updateState();
    }
    function showPrices() {
        cancelFrame();
        video.pause();
        var hadFocus = document.activeElement === video;
        priceScene.hidden = false;
        if (hadFocus) priceScene.focus({ preventScroll: true });
        moving = true;
        updateState();
        renderPrices(0);
        if (reduceMotion.matches) { finishPrices(); return; }
        var started = null;
        function tick(now) {
            if (started === null) started = now;
            // Let the final section slide into place before growing its prices.
            var elapsed = now - started;
            if (elapsed >= 1500) video.hidden = true;
            var progress = Math.max(0, Math.min(1, (elapsed - 1500) / 1690));
            renderPrices(progress);
            if (progress < 1) priceAnimation = requestAnimationFrame(tick);
            else finishPrices();
        }
        priceAnimation = requestAnimationFrame(tick);
    }
    function finishHospital() {
        priceScene.hidden = true;
        hospitalScenes.forEach(function (panel, index) {
            panel.hidden = index !== scene - stops.length;
        });
        moving = false;
        if (scene === stops.length + hospitalScenes.length - 1) {
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
        var hospitalScene = hospitalScenes[scene - stops.length];
        var previousScene = scene === stops.length ? priceScene : hospitalScenes[scene - stops.length - 1];
        hospitalPhone.hidden = false;
        hospitalPhone.classList.toggle('show-team', scene > stops.length);
        var isBooking = hospitalScene.id === 'hospital-booking-scene';
        hospitalPhone.classList.toggle('show-booking', isBooking);
        hospitalPhone.querySelector('.booking-heading').setAttribute('aria-hidden', String(!isBooking));
        hospitalPhone.querySelector('.phone-heading').setAttribute('aria-hidden', String(scene > stops.length));
        hospitalScene.hidden = false;
        if (previousScene.contains(document.activeElement)) hospitalScene.focus({ preventScroll: true });
        moving = true;
        updateState();
        if (reduceMotion.matches) finishHospital();
    }
    hospitalPhone.addEventListener('animationend', function (event) {
        if (event.target === hospitalPhone && scene === stops.length) finishHospital();
    });
    hospitalScenes.forEach(function (panel) {
        panel.addEventListener('animationend', function (event) {
            if (event.target === panel && panel === hospitalScenes[scene - stops.length]) finishHospital();
        });
    });
    async function playScene() {
        if (scene >= stops.length) { showHospital(); return; }
        if (scene === stops.length - 1) { showPrices(); return; }
        if (reduceMotion.matches) { hold(); return; }
        moving = true;
        updateState();
        cancelFrame();
        if (video.requestVideoFrameCallback) frameRequest = video.requestVideoFrameCallback(checkFrame);
        try { await video.play(); }
        catch (error) { moving = false; cancelFrame(); stage.dataset.playbackError = error.name; updateState(); }
    }
    function navigate(direction) {
        if (moving || !video.readyState || !error.hidden) return;
        if (scene === stops.length + hospitalScenes.length - 1) return;
        if (direction > 0) {
            // A gesture also retries playback if the browser blocked the initial autoplay.
            if (scene < stops.length - 1 && video.currentTime < stops[scene] - 0.1) { playScene(); return; }
            scene++;
            playScene();
        }
    }
    stage.addEventListener('touchstart', function (event) {
        if (scrollMode) return;
        touchStart = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { passive: true });
    stage.addEventListener('touchend', function (event) {
        if (scrollMode) return;
        if (!touchStart || !event.changedTouches.length) return;
        var dx = event.changedTouches[0].clientX - touchStart.x;
        var dy = event.changedTouches[0].clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dy) >= 40 && Math.abs(dy) > Math.abs(dx)) navigate(dy < 0 ? 1 : -1);
    }, { passive: true });
    stage.addEventListener('touchcancel', function () { touchStart = null; }, { passive: true });
    stage.addEventListener('wheel', function (event) {
        if (scrollMode) return;
        if (event.ctrlKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
        event.preventDefault();
        clearTimeout(wheelTimer);
        wheelTimer = setTimeout(function () { wheelLocked = false; }, 250);
        if (wheelLocked || Math.abs(event.deltaY) < 8) return;
        wheelLocked = true;
        navigate(event.deltaY > 0 ? 1 : -1);
    }, { passive: false });
    document.addEventListener('keydown', function (event) {
        if (scrollMode) return;
        var direction = ['ArrowDown', 'PageDown', ' '].includes(event.key) ? 1
            : 0;
        if (!direction || event.repeat) return;
        event.preventDefault();
        navigate(direction);
    });
    video.addEventListener('timeupdate', function () {
        if (scene < stops.length - 1 && moving && video.currentTime >= stops[scene]) hold();
    });
    video.addEventListener('ended', hold);
    function showError() { moving = false; cancelFrame(); error.hidden = false; updateState(); }
    video.addEventListener('error', showError);
    video.querySelector('source').addEventListener('error', showError);
    document.addEventListener('visibilitychange', function () {
        if (scene >= stops.length - 1) return;
        if (document.hidden) {
            resumeOnVisible = moving;
            video.pause();
        } else if (resumeOnVisible) {
            resumeOnVisible = false;
            playScene();
        }
    });
    reduceMotion.addEventListener('change', function (event) {
        if (event.matches && scene >= stops.length) finishHospital();
        else if (event.matches && scene === stops.length - 1) finishPrices();
        else if (event.matches && video.readyState) hold();
    });
    updateState();
    if (video.error) showError();
    else if (reduceMotion.matches && !video.readyState) video.addEventListener('loadedmetadata', hold, { once: true });
    else playScene();
})();
</script>
</body>
</html>

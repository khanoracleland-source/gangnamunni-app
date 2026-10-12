<?php
// Ilova WebView eski nusxani ko'rsatmasligi uchun sahifa keshlanmaydi.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>이용 가이드 | 서울미녀</title>
    <meta name="guide-build" content="2026-10-13 10:15">
    <meta name="description" content="피부과·성형외과 미용시술 가격을 병원별로 한눈에 비교하는 서울미녀 이용 가이드입니다.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script>window._pullToRefreshInstalled = true;</script>
    <style>
        :root {
            --brand: #AE1932;
            --brand-deep: #AE1932; /* barcha qizil ranglar bir xil */
            --brand-soft: #f6dfe3;
            --ink: #111;
            --text: #333;
            --muted: #8a8a8a;
            --line: #e6e6e6;
            --header-h: 136px;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        html { overscroll-behavior: none; }
        body { margin: 0; background: #fff; color: var(--ink); font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", sans-serif; -webkit-font-smoothing: antialiased; word-break: keep-all; }
        button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; }
        :focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
        .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

        .guide { position: relative; width: 100%; max-width: 720px; height: 100%; margin: 0 auto; display: flex; flex-direction: column; overflow: hidden; }

        /* ---------- Header ---------- */
        .guide-top { flex: none; background: #fff; border-bottom: 1px solid var(--line); } /* safe-area bo'shlig'i olib tashlandi: ilova WebView'ida status bar ostida ortiqcha oq joy qolardi */
        .guide-bar { position: relative; height: clamp(48px, calc(12 * var(--vw, 1vw)), 56px); display: flex; align-items: center; justify-content: center; }
        .guide-back { position: absolute; left: 4px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; display: grid; place-items: center; }
        .guide-back svg { width: 24px; height: 24px; }
        .guide-title { margin: 0; font-size: clamp(18px, calc(4.6 * var(--vw, 1vw)), 21px); font-weight: 700; letter-spacing: -.01em; }
        .guide-tabs { display: flex; overflow-x: auto; scrollbar-width: none; }
        .guide-tabs::-webkit-scrollbar { display: none; }
        .guide-tab { position: relative; flex: 1 0 auto; min-width: 20%; height: clamp(36px, calc(9 * var(--vw, 1vw)), 42px); padding: 0 10px; font-size: clamp(13px, calc(3.4 * var(--vw, 1vw)), 15px); font-weight: 500; color: var(--ink); white-space: nowrap; }
        .guide-tab[aria-selected="true"] { color: var(--brand); font-weight: 700; }
        .guide-tab[aria-selected="true"]::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 2px; background: var(--brand); }

        /* ---------- Horizontal slides ---------- */
        .guide-track { flex: 1; min-height: 0; display: flex; overflow-x: auto; overflow-y: hidden; scroll-snap-type: x mandatory; scrollbar-width: none; overscroll-behavior-x: contain; }
        .guide-track::-webkit-scrollbar { display: none; }
        .guide-slide { flex: 0 0 100%; height: 100%; overflow-y: auto; scroll-snap-align: start; scroll-snap-stop: always; padding: 40px 20px 0; text-align: center; display: flex; flex-direction: column; }
        .guide-slide > * { flex: none; }
        .guide-slide > .mock { flex: 1 0 auto; margin-bottom: 0; } /* telefon ramkasi ekran tagigacha cho'ziladi, pasti ochiq qoladi */
        .guide-slide { overscroll-behavior-y: none; } /* pastga tortganda rezina-cho'zilish bo'lmasin */


        .guide-dots { display: flex; align-items: center; justify-content: center; margin-bottom: 38px; }
        .guide-dots i { width: 9px; height: 9px; background: #d4d4d4; }
        .guide-dots i.is-on { background: var(--brand-deep); }
        .guide-dots i + i { margin-left: 34px; position: relative; }
        .guide-dots i + i::before { content: ""; position: absolute; right: 13px; top: 4px; width: 26px; height: 1px; background: #cfcfcf; }

        .slide-title { margin: 0 20px 18px; font-size: clamp(21px, calc(5.8 * var(--vw, 1vw)), 27px); font-weight: 800; letter-spacing: -.02em; line-height: 1.35; }
        .slide-lead { margin: 0 20px; font-size: clamp(16px, calc(4.4 * var(--vw, 1vw)), 20px); line-height: 1.6; color: var(--text); }
        .slide-lead strong { color: var(--ink); font-weight: 800; }

        /* ---------- Phone mockup ---------- */
        .mock { margin: 52px auto 0; width: calc(100% - 64px); max-width: 470px; padding: 52px 22px 24px; border: 12px solid #ececec; border-bottom: 0; border-radius: 44px 44px 0 0; }
        .mock-title { margin: 0 0 20px; font-size: clamp(17px, calc(4.6 * var(--vw, 1vw)), 21px); font-weight: 800; letter-spacing: -.02em; }

        .compare { position: relative; text-align: left; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 16px 16px 20px; margin-bottom: 74px; }
        .compare-head { margin: 0 0 14px; font-size: 13px; font-weight: 700; color: #222; }
                .bar-row { display: grid; grid-template-columns: 52px 1fr auto; align-items: center; gap: 8px; font-size: 12px; }
        .bar-row + .bar-row { margin-top: 12px; }
        .bar-row dt { color: var(--muted); }
        .bar-row dd { margin: 0; }
        .bar-track { height: 5px; border-radius: 3px; background: var(--brand-soft); overflow: hidden; }
        .bar-fill { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--brand-deep); }
        .bar-value { min-width: 64px; text-align: right; color: #222; font-variant-numeric: tabular-nums; }
        .compare-badge { position: absolute; right: -2px; top: calc(100% + 18px); padding: 10px 16px; border-radius: 8px; white-space: nowrap; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; box-shadow: 0 4px 10px rgba(174,25,50,.25); }

        .compare-badge::before { content: ""; position: absolute; right: 22px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .mock-note { margin: 0 0 24px; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 16px); line-height: 1.6; color: var(--text); }

        .hosp-list { list-style: none; margin: 0; padding: 0; text-align: left; }
        .hosp { position: relative; display: flex; gap: 16px; padding: 18px 0; border-top: 1px solid var(--line); }
        .hosp:first-child { border-top: 0; }
        .hosp-thumb { flex: none; width: 70px; height: 70px; display: grid; place-items: center; border: 1px solid #ddd; color: #aaa; font-size: 13px; }
        .hosp-body { min-width: 0; flex: 1; }
        .hosp-name { margin: 0 0 3px; font-size: 12px; font-weight: 700; color: #222; }
        .hosp-addr { margin: 0 0 6px; font-size: 10px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 24px; }
        .hosp-item { margin: 0 0 4px; font-size: 13px; font-weight: 700; }
        .hosp-vat { margin: 0; font-size: 10px; color: #bbb; }
        .hosp-vat s { margin-left: 28px; }
        .hosp-price { margin: 2px 0 0; color: var(--brand); font-size: 14px; font-weight: 800; }
        .hosp-price span { font-size: 10px; font-weight: 400; }
        .hosp-star { position: absolute; right: 0; top: 18px; width: 18px; height: 18px; color: #555; }

        /* ---------- 시작하기 · 2 (login mock) ---------- */
        .mock-login { padding-top: 64px; padding-bottom: 120px; }
        .login-brand { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 34px; }
        .login-brand img { display: block; width: 210px; max-width: 60%; height: auto; }
        .login-brand span { font-size: 14px; letter-spacing: .42em; margin-right: -.42em; color: #222; font-family: "Helvetica Neue", Arial, sans-serif; }
        .login-box { position: relative; margin-top: 92px; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 10px; }
        .login-badge { position: absolute; left: 10px; bottom: calc(100% + 18px); padding: 9px 16px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 16px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .login-badge::after { content: ""; position: absolute; left: 30px; top: 100%; border: 8px solid transparent; border-bottom: 0; border-top-color: var(--brand); }
        .login-btns { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .lb { display: flex; align-items: center; justify-content: center; gap: 12px; height: 43px; font-size: clamp(13px, calc(3.6 * var(--vw, 1vw)), 15px); font-weight: 700; }
        .lb svg { width: 20px; height: 20px; flex: none; }
        .lb-kakao { background: #fae54d; color: #191600; }
        .lb-google { background: #fff; color: #202124; border: 1px solid #cfcfcf; }
        .lb-apple { background: #000; color: #fff; }
        .lb-email { background: #1a0202; color: #fff; }
        .login-links { display: flex; justify-content: center; margin: 62px 0 0; font-size: 14px; color: #222; }
        .login-links span + span { border-left: 1px solid #ccc; margin-left: 18px; padding-left: 18px; }
        .login-captcha { margin: 64px 0 0; text-align: left; font-size: 12px; line-height: 1.75; color: #111; }
        .login-captcha u { color: var(--muted); text-underline-offset: 3px; }
        /* 시작하기 · 2 — kirish animatsiyasi (slayd har ochilganda qayta o'ynaydi) */
        .mock-anim .anim { opacity: 0; }
        .is-active .mock-anim .anim { animation: var(--a) both; }
        .a-logo  { --a: lg-rise .6s cubic-bezier(.2,.7,.2,1) .1s; }
        .a-sub   { --a: lg-track .8s ease-out .35s; }
        .a-box   { --a: lg-box .5s ease-out .55s; }
        .a-btn   { --a: lg-btn .45s cubic-bezier(.2,.8,.2,1) calc(.75s + var(--i) * .12s); }
        .a-badge { transform-origin: 30px 100%; }
        .a-foot  { --a: lg-fade .5s ease-out 1.6s; }
        .is-active .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 1.35s both, lg-bob 2.4s ease-in-out 2s infinite; }
        .is-active .lb-kakao.a-btn { animation: lg-btn .45s cubic-bezier(.2,.8,.2,1) .75s both, lg-tap 2.4s ease-in-out 2.2s infinite; }
        @keyframes lg-rise  { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @keyframes lg-track { from { opacity: 0; letter-spacing: .1em; } to { opacity: 1; letter-spacing: .42em; } }
        @keyframes lg-box   { from { opacity: 0; transform: scale(.96); } to { opacity: 1; transform: none; } }
        @keyframes lg-btn   { from { opacity: 0; transform: translateX(-24px); } to { opacity: 1; transform: none; } }
        @keyframes lg-pop   { from { opacity: 0; transform: scale(.4); } to { opacity: 1; transform: none; } }
        @keyframes lg-bob   { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        @keyframes lg-fade  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes lg-tap   { 0%,70%,100% { opacity: 1; transform: none; box-shadow: none; } 80% { opacity: 1; transform: scale(.97); box-shadow: 0 0 0 4px rgba(250,229,77,.45); } }
        /* ---------- 찾아보기 · 1 (category mock) ---------- */
        .mock-cat { padding: 34px 17px 110px; }
        .mock-cat .mock-title { margin-bottom: 30px; font-size: clamp(18px, calc(4.8 * var(--vw, 1vw)), 21px); }
        .cat-wrap { display: flex; align-items: flex-start; text-align: left; }
        .cat-side { flex: none; width: 118px; margin: 0; padding: 0; list-style: none; background: #f7f7f7; }
        .cat-side li { height: 34.7px; display: flex; align-items: center; padding-left: 33px; font-size: 11.5px; color: #8a8a8a; }
        .cat-side li.is-on { background: #fff; color: var(--brand); font-weight: 700; }
        .cat-main { position: relative; flex: 1; min-width: 0; }
        .cat-box { border: 2px solid var(--brand-deep); border-radius: 10px; padding: 4px 0; background: #fff; }
        .cat-items { display: grid; grid-template-columns: 1fr 1fr; margin: 0; padding: 0; list-style: none; text-align: center; }
        .cat-items li { height: 34.7px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700; color: #111; }
        .cat-badge { position: absolute; left: 50%; top: calc(100% + 18px); transform: translateX(-50%); padding: 9px 14px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 16px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .cat-badge::before { content: ""; position: absolute; left: 50%; margin-left: -8px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .a-fade { --a: lg-fade .5s ease-out .05s; }
        .a-side { --a: cat-side .35s ease-out calc(.15s + var(--i) * .035s); }
        .a-item { --a: lg-rise .4s cubic-bezier(.2,.7,.2,1) calc(.95s + var(--i) * .05s); }
        .mock-cat .a-box { --a: lg-box .45s ease-out .75s; }
        .mock-cat .a-badge { transform-origin: 50% 0; }
        .is-active .mock-cat .a-badge { animation: cat-pop .5s cubic-bezier(.3,1.6,.5,1) 1.75s both, cat-bob 2.4s ease-in-out 2.4s infinite; }
        .is-active .cat-side li.is-on { animation: cat-side .35s ease-out .15s both, cat-pick 2.4s ease-in-out 2.4s infinite; }
        @keyframes cat-side { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: none; } }
        @keyframes cat-pop  { from { opacity: 0; transform: translateX(-50%) scale(.4); } to { opacity: 1; transform: translateX(-50%); } }
        @keyframes cat-bob  { 0%,100% { transform: translateX(-50%); } 50% { transform: translateX(-50%) translateY(-5px); } }
        @keyframes cat-pick { 0%,70%,100% { box-shadow: none; } 82% { box-shadow: inset 3px 0 0 var(--brand); } }
        @media (max-width: 480px) {
            .mock-cat { padding-left: 10px; padding-right: 10px; }
            .cat-side { width: 96px; }
            .cat-side li { padding-left: 18px; font-size: 11px; }
            .cat-items li { font-size: 11px; }
        }
        /* ---------- 찾아보기 · 2 (region filter mock) ---------- */
        .mock-region { padding: 44px 16px 40px; text-align: left; }
        .rg-search { display: flex; align-items: center; gap: 8px; margin: 0 0 8px 10px; }
        .rg-input { flex: 1; min-width: 0; padding: 10px 0; border-bottom: 1px solid #333; font-size: 10px; color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rg-pick { flex: none; display: flex; align-items: center; gap: 10px; height: 40px; padding: 0 22px 0 12px; border-radius: 20px; background: #1f0505; color: #fff; font-size: 12px; font-weight: 700; }
        .rg-pick svg { width: 22px; height: 22px; }
        .rg-box { position: relative; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 3px 14px 8px; margin-bottom: 70px; }
        .rg-group { display: grid; grid-template-columns: repeat(6, 1fr); margin: 0; padding: 4px 0; list-style: none; }
        .rg-group + .rg-group { border-top: 1px solid #ececec; }
        .rg-group li { display: flex; align-items: center; gap: 7px; height: 26px; font-size: 10px; color: #222; white-space: nowrap; }
        .rg-group li i { flex: none; position: relative; width: 9px; height: 9px; border: 1px solid #c8c8c8; border-radius: 1px; }
        .rg-group li.rg-on i::after { content: ""; position: absolute; inset: -1px; border-radius: 1px; background: var(--brand) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpath d='m2 5 2 2 4-4' fill='none' stroke='%23fff' stroke-width='1.5'/%3E%3C/svg%3E") center/100% no-repeat; }
        .rg-group li.rg-on { color: var(--brand); }
        .rg-badge { position: absolute; left: -2px; top: calc(100% + 16px); padding: 9px 14px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 16px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .rg-badge::before { content: ""; position: absolute; left: 22px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .rg-filter { display: flex; align-items: center; gap: 6px; height: 30px; border: 1px solid #ddd; padding-left: 8px; margin-bottom: 26px; font-size: 10px; }
        .rg-reset { display: flex; align-items: center; gap: 4px; margin-right: 6px; color: #333; }
        .rg-reset svg { width: 12px; height: 12px; }
        .rg-chip { display: flex; align-items: center; gap: 6px; height: 22px; padding: 0 6px; border: 1px solid #e3a2ad; border-radius: 3px; color: #333; }
        .rg-chip svg { width: 9px; height: 9px; }
        .rg-done { margin-left: auto; align-self: stretch; display: flex; align-items: center; padding: 0 clamp(12px, calc(5 * var(--vw, 1vw)), 36px); background: var(--brand); color: #fff; font-weight: 700; }
        .rg-sort { display: flex; justify-content: center; gap: 10px; margin-bottom: 16px; }
        .rg-sort span { display: flex; align-items: center; gap: 4px; height: 28px; padding: 0 8px; border: 1px solid #ccc; border-radius: 3px; color: #aaa; font-size: 12px; }
        .rg-sort span.is-on { border-color: #222; color: #111; }
        .rg-sort svg { width: 16px; height: 16px; }
        .mock-region .hosp-list { padding: 0 8%; }
        /* animatsiya: 강남 → 천호 belgilanadi, chiplari qo'shiladi, keyin 필터 선택 완료 bosiladi */
        .a-fade2 { --a: lg-fade .4s ease-out .5s; }
        .a-fade3 { --a: lg-rise .5s ease-out .6s; }
        .is-active .rg-on i::after { animation: rg-check .3s cubic-bezier(.3,1.6,.5,1) calc(1.1s + var(--k) * .7s) both; }
        .is-active .rg-on { animation: rg-color .01s linear calc(1.1s + var(--k) * .7s) both; }
        .is-active .rg-chip { animation: lg-pop .35s cubic-bezier(.3,1.6,.5,1) calc(1.25s + var(--k) * .7s) both; }
        .is-active .mock-region .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 2.6s both, lg-bob 2.4s ease-in-out 3.2s infinite; transform-origin: 22px 0; }
        .is-active .rg-done { animation: rg-press 2.4s ease-in-out 3s infinite; }
        @keyframes rg-check { from { opacity: 0; transform: scale(0); } to { opacity: 1; transform: none; } }
        @keyframes rg-color { from { color: #222; } to { color: var(--brand); } }
        @keyframes rg-press { 0%,70%,100% { filter: none; box-shadow: none; } 80% { filter: brightness(.85); box-shadow: inset 0 0 0 2px rgba(255,255,255,.35); } }
        @media (max-width: 480px) {
            .mock-region { padding-left: 8px; padding-right: 8px; }
            .rg-box { padding: 3px 6px 6px; }
            .rg-group li { gap: 3px; font-size: 9px; }
            .rg-group li i { width: 8px; height: 8px; }
            .rg-pick { height: 32px; padding: 0 14px 0 8px; font-size: 11px; gap: 6px; }
            .rg-pick svg { width: 16px; height: 16px; }
            .rg-filter { font-size: 9px; gap: 4px; }
            .rg-sort { gap: 6px; }
            .rg-sort span { padding: 0 5px; font-size: 11px; }
            .mock-region .hosp-list { padding: 0 4%; }
        }
        /* ---------- 찾아보기 · 3 (sort mock) ---------- */
        .mock-sort .rg-search { margin-bottom: 10px; }
        .mock-sort .rg-filter { margin: 0 0 14px 10px; }
        .sort-box { position: relative; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 14px 20px; margin-bottom: 84px; }
        .sort-box .rg-sort { justify-content: space-between; gap: 8px; margin: 0; }
        .sort-box .rg-sort span { height: 34px; padding: 0 10px; font-size: clamp(13px, calc(3.6 * var(--vw, 1vw)), 16px); border-radius: 4px; gap: 6px; }
        .sort-box .rg-sort svg { width: 20px; height: 20px; }
        .sort-badge { position: absolute; left: -2px; top: calc(100% + 18px); padding: 9px 16px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .sort-badge::before { content: ""; position: absolute; left: 78px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        /* animatsiya: tanlangan saralash 낮은가격 → 평점 → 인기 → 후기 bo'ylab aylanadi */
        .is-active .mock-sort .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 1.2s both, lg-bob 2.4s ease-in-out 1.8s infinite; transform-origin: 78px 0; }
        .is-active .sort-box .rg-sort span { animation: sort-cycle 4.8s steps(1, end) calc(2.4s + var(--s) * 1.2s) infinite; }
        .is-active .mock-sort .hosp-list.anim { animation: lg-rise .5s ease-out .6s both, sort-refresh 1.2s ease-out 3.6s infinite; }
        .is-active .mock-sort .rg-done, .is-active .mock-sort .rg-chip { animation: none; }
        @keyframes sort-cycle { 0% { border-color: #222; color: #111; } 25%, 100% { border-color: #ccc; color: #aaa; } }
        @keyframes sort-refresh { 0% { opacity: .25; transform: translateY(6px); } 35%, 100% { opacity: 1; transform: none; } }
        @media (max-width: 480px) {
            .sort-box { padding: 10px 8px; }
            .sort-box .rg-sort { gap: 4px; }
            .sort-box .rg-sort span { padding: 0 6px; gap: 3px; height: 30px; }
            .sort-box .rg-sort svg { width: 15px; height: 15px; }
            .sort-badge::before { left: 54px; }
        }
        /* ---------- 클리닉 상세 mock (찾아보기 · 4, 예약하기 · 1) ---------- */
        .mock-clinic { padding: 44px 32px 60px; text-align: left; }
        .ct-tabs-box { position: relative; z-index: 2; margin: 0 -14px 20px; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 0 0 8px; }
        .ct-tabs-box.is-plain { border-color: transparent; }
        .ct-tabs { position: relative; display: grid; grid-template-columns: repeat(4, 1fr); margin: 0 2px; }
        .ct-tabs span { height: 44px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #222; box-shadow: inset 0 -1px 0 #ddd; } /* har tabning o'z chizig'i: kasr pikselsiz, aniq */
        .ct-tabs span:first-child { color: var(--brand); }
        .ct-ink { position: absolute; z-index: 1; left: 0; bottom: 0; width: 25%; height: 2px; background: var(--brand); will-change: transform; backface-visibility: hidden; transform: translate3d(0,0,0); } /* faol tab chizig'i: tablar bo'ylab silliq sirpanadi */
        .ct-badge { position: absolute; z-index: 3; left: -2px; top: calc(100% + 14px); padding: 9px 16px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.3); }
        .ct-badge::before { content: ""; position: absolute; left: 52px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .ct-photo { display: block; width: 100%; height: auto; aspect-ratio: 380 / 126; object-fit: cover; }
        .ct-info p { margin: 0; }
        .ct-name { display: flex; justify-content: space-between; align-items: center; margin-top: 14px !important; font-size: 10px; color: #111; }
        .ct-name svg { width: 18px; height: 18px; color: #444; }
        .ct-meta { display: flex; gap: 22px; padding: 4px 0 8px; border-bottom: 1px solid #ddd; font-size: 8.5px; color: #999; }
        .ct-meta span { display: flex; align-items: center; gap: 5px; }
        .ct-meta span:last-child { color: #222; font-weight: 700; }
        .ct-meta b { color: #222; }
        .ct-meta svg { width: 13px; height: 13px; }
        .ct-hours { display: flex; align-items: center; gap: 6px; padding: 14px 0; border-bottom: 1px solid #ddd; font-size: 10px; color: #222; }
        .ct-hours svg { width: 16px; height: 16px; color: var(--brand); }
        .ct-hours b { color: var(--brand); margin-right: 6px; }
        .ct-hours .ct-chev { width: 14px; height: 14px; margin-left: 12px; }
        .ct-q { margin: 14px 0 4px; font-size: 14px; font-weight: 700; }
        .ct-note { margin: 0 0 8px; font-size: 9px; color: #222; }
        .ct-cards { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
        .ct-cards li { position: relative; border: 1px solid #ddd; padding: 12px 16px 8px; background: #fff; }
        .ct-cards li.is-pick { z-index: 2; }
        .ct-cards li.is-pick::after { content: ""; position: absolute; inset: -8px -17px; border: 2px solid var(--brand-deep); border-radius: 10px; pointer-events: none; }
        .pick-badge { left: -17px; top: calc(100% + 24px); }
        .ct-cards p { margin: 0; font-size: 10px; color: #111; }
        .ct-cards p span { margin-left: 8px; }
        .ct-cards .ct-price { margin-top: 14px; text-align: right; font-size: 10px; }
        .ct-price s { margin-right: 12px; color: #aaa; font-size: 8px; text-decoration: none; }
        .ct-price b { color: var(--brand); font-size: 16px; font-weight: 800; }
        .mock-clinic .a-item { --a: lg-rise .45s ease-out calc(1s + var(--i) * .12s); }
        /* 찾아보기 · 4: ichki tablar 홈 → 의료진 → 시술목록 → 시술후기 bo'ylab aylanadi */
        .is-active .mock-clinic .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 1.2s both, lg-bob 2.4s ease-in-out 1.8s infinite; transform-origin: 52px 0; }
                .is-active .mock-clinic-tabs .ct-tabs span { animation: ct-tab 6s linear calc(2.2s + var(--s) * 1.5s) infinite; }
        .is-active .mock-clinic-tabs .ct-ink { animation: ct-ink 6s cubic-bezier(.65,0,.35,1) 2.2s infinite; }
        @keyframes ct-ink { 0%, 20% { transform: translate3d(0,0,0); } 25%, 45% { transform: translate3d(100%,0,0); } 50%, 70% { transform: translate3d(200%,0,0); } 75%, 95% { transform: translate3d(300%,0,0); } 100% { transform: translate3d(0,0,0); } }
        @keyframes ct-tab { 0%, 21% { color: var(--brand); } 25%, 100% { color: #222; } }
        /* 예약하기 · 1: birinchi karta tanlanadi va bosilgandek silkinadi */
        .is-active .mock-clinic-pick .a-badge { animation-delay: 1.9s, 2.5s; }
        .is-active .mock-clinic-pick li.is-pick { animation: lg-rise .45s ease-out 1s both, ct-press 2.4s ease-in-out 2.6s infinite; }
        .mock-clinic-pick li.is-pick::after { opacity: 0; }
        .is-active .mock-clinic-pick li.is-pick::after { animation: ct-ring .4s cubic-bezier(.3,1.6,.5,1) 1.6s both; }
        @keyframes ct-ring { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }
        @keyframes ct-press { 0%, 70%, 100% { transform: none; background: #fff; } 80% { transform: scale(.98); background: #fbf1f3; } }
        @media (max-width: 480px) {
            .mock-clinic { padding: 36px 16px 40px; }
            .ct-tabs-box { margin: 0 -6px 18px; }
            .ct-tabs span { font-size: 11px; }
            .ct-hours { font-size: 9px; gap: 4px; }
            .ct-hours .ct-chev { margin-left: 4px; }
            .ct-cards li.is-pick::after { inset: -6px -8px; }
            .pick-badge { left: -8px; }
        }
        /* ---------- 예약하기 · 2 (date/time mock) ---------- */
        .mock-time { padding: 28px 0 60px; text-align: left; }
        .tm-sec { padding: 0 30px 22px; border-bottom: 8px solid #eee; }
        .tm-sec + .tm-sec { padding-top: 22px; }
        .tm-last { border-bottom: 0; }
        .tm-h { margin: 0 0 12px; padding-bottom: 12px; border-bottom: 1px solid #ddd; font-size: 13px; font-weight: 700; }
        .tm-h.tm-plain { border-bottom: 0; padding-bottom: 0; }
        .tm-sec p { margin: 0; }
        .tm-item { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 18px !important; }
        .tm-item span { font-size: 14px; line-height: 1; }
        .tm-price { font-size: 11px; }
        .tm-month { display: flex; gap: 16px; padding-bottom: 12px; border-bottom: 1px solid #bbb; font-size: 11px; }
        .tm-month span { color: #aaa; }
        .tm-week, .tm-dates { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; }
        .tm-week span:first-child, .tm-dates span:first-child { text-align: left; }
        .tm-week { padding: 10px 0 6px; font-size: 9px; color: #999; }
        .tm-week span:first-child { padding-left: 8px; }
        .tm-dates { padding-bottom: 12px; border-bottom: 1px solid #bbb; font-size: 12px; font-weight: 500; }
        .tm-dates span { position: relative; height: 34px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 6px; }
        .tm-dates span.is-off { color: #aaa; }
        .tm-dates span.is-today { align-items: flex-start; }
        .tm-dates span.is-today::before { content: "28"; display: grid; place-items: center; width: 25px; height: 25px; margin-top: -4px; background: var(--brand); color: #fff; }
        .tm-dates span.is-today { color: transparent; font-size: 0; }
        .tm-dates span.is-today::before { font-size: 12px; }
        .tm-dates small { display: block; margin-top: 4px; font-size: 9px; color: #999; }
        .tm-mode { margin-top: 10px !important; font-size: 10px; font-weight: 700; }
        .tm-mode-note { margin-top: 6px !important; font-size: 8.5px; color: var(--brand); }
        .tm-box { position: relative; margin: 14px -16px 0; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 8px 14px; }
        .tm-slots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px 28px; }
        .tm-slots span { height: 25px; display: grid; place-items: center; border: 1px solid #e3e3e3; font-size: 11px; color: #111; }
        .tm-slots span.is-pick { border-color: var(--brand); color: var(--brand); }
        .tm-badge { position: absolute; right: -2px; bottom: calc(100% + 18px); padding: 9px 26px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .tm-badge::after { content: ""; position: absolute; right: 52px; top: 100%; border: 8px solid transparent; border-bottom: 0; border-top-color: var(--brand); }
        .tm-submit { display: grid; place-items: center; height: 36px; margin-top: 20px; background: var(--brand); color: #fff; font-size: 11px; }
        /* animatsiya: 28-sana tanlanadi → 10:30 tanlanadi → 예약하기 bosiladi */
        .is-active .tm-dates span.is-today::before { animation: lg-pop .35s cubic-bezier(.3,1.6,.5,1) 1s both; }
        .is-active .tm-slots span.is-pick { animation: tm-pick .35s ease-out 1.7s both; }
        .is-active .mock-time .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 1.3s both, lg-bob 2.4s ease-in-out 1.9s infinite; transform-origin: calc(100% - 52px) 100%; }
        .is-active .tm-submit { animation: ct-press 2.4s ease-in-out 2.4s infinite; }
        @keyframes tm-pick { from { border-color: #e3e3e3; color: #111; transform: scale(1); } 50% { transform: scale(1.08); } to { border-color: var(--brand); color: var(--brand); transform: none; } }

        /* ---------- 예약하기 · 3 (request done mock) ---------- */
        .mock-done { padding: 0; background: #dedede; text-align: left; overflow: hidden; }
        .dn-top { padding: 28px 28px 18px; }
        .dn-top p { margin: 0; }
        .dn-bar { position: relative; text-align: center; font-size: 12px; color: #222; }
        .dn-bar svg { position: absolute; left: 0; top: 50%; width: 18px; height: 18px; transform: translateY(-50%); }
        .dn-tag { display: inline-flex; gap: 28px; margin-top: 30px !important; padding: 7px 14px; background: var(--brand); color: #fff; font-size: 12px; }
        .dn-no { margin-top: 18px !important; font-size: 11px; color: #222; }
        .dn-no span { margin-right: 24px; color: #999; }
        .dn-sheet { position: relative; margin: 0 2px; padding: 14px 32px 120px; border-radius: 28px 28px 0 0; background: #fff; text-align: center; }
        .dn-handle { display: block; width: 36px; height: 3px; margin: 0 auto 46px; border-radius: 2px; background: var(--brand); }
        .dn-check { display: block; width: 42px; height: 42px; margin: 0 auto 18px; }
        .dn-title { margin: 0 0 14px; font-size: 15px; font-weight: 700; }
        .dn-text { margin: 0 0 22px; font-size: 10px; line-height: 1.7; color: #111; }
        .dn-box { position: relative; margin: 0 22px; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 14px; }
        .dn-panel { padding: 22px 18px 18px; background: #f8eef0; }
        .dn-panel p { margin: 0; }
        .dn-p-h { margin-bottom: 6px !important; font-size: 11px; font-weight: 700; }
        .dn-p-t { font-size: 8.5px; line-height: 1.75; color: #111; }
        .dn-note { margin-top: 16px; padding: 16px 10px 12px; background: #fff; }
        .dn-n-h { margin-bottom: 6px !important; font-size: 11px; color: var(--brand); }
        .dn-badge { position: absolute; right: -10px; bottom: calc(100% + 14px); padding: 9px 22px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .dn-badge::after { content: ""; position: absolute; right: 52px; top: 100%; border: 8px solid transparent; border-bottom: 0; border-top-color: var(--brand); }
        .dn-btns { display: flex; justify-content: center; gap: 16px; margin: 28px 0 0; }
        .dn-btns span { width: 156px; max-width: 45%; height: 38px; display: grid; place-items: center; border: 1px solid var(--brand); color: var(--brand); font-size: 11px; }
        .dn-btns span.is-fill { background: var(--brand); color: #fff; }
        /* animatsiya: pastdan oyna chiqadi → belgi chiziladi → izoh paydo bo'ladi */
        .is-active .dn-sheet { animation: dn-up .6s cubic-bezier(.2,.8,.2,1) .2s both; }
        .dn-check path { stroke-dasharray: 30; }
        .is-active .dn-check circle { animation: lg-pop .4s cubic-bezier(.3,1.6,.5,1) .7s both; transform-origin: center; }
        .is-active .dn-check path { animation: dn-draw .4s ease-out 1s both; }
        .mock-done .a-box { --a: lg-box .45s ease-out 1.3s; }
        .mock-done .a-fade3 { --a: lg-fade .45s ease-out 1.1s; }
        .is-active .mock-done .a-badge { animation: lg-pop .5s cubic-bezier(.3,1.6,.5,1) 1.8s both, lg-bob 2.4s ease-in-out 2.4s infinite; transform-origin: calc(100% - 52px) 100%; }
        .is-active .dn-note { animation: dn-glow 2.4s ease-in-out 2.6s infinite; }
        @keyframes dn-up { from { transform: translateY(70%); } to { transform: none; } }
        @keyframes dn-draw { from { stroke-dashoffset: 30; } to { stroke-dashoffset: 0; } }
        @keyframes dn-glow { 0%, 70%, 100% { box-shadow: none; } 82% { box-shadow: 0 0 0 3px rgba(174,25,50,.25); } }
        @media (max-width: 480px) {
            .tm-sec { padding-left: 16px; padding-right: 16px; }
            .tm-box { margin: 14px -8px 0; padding: 8px; }
            .tm-slots { gap: 16px 10px; }
            .tm-slots span { font-size: 10px; }
            .tm-badge, .dn-badge { padding: 8px 14px; }
            .dn-top { padding: 0 16px 14px; }
            .dn-sheet { padding: 12px 14px 60px; }
            .dn-box { margin: 0; padding: 10px; }
            .dn-badge { right: -2px; }
            .dn-panel { padding: 16px 8px 12px; }
        }
        /* ---------- 결제하기 · 1 (push notification mock) ---------- */
        .mock-push { padding: 24px 0 300px; background: #dcdcdc; text-align: left; }
        .nt-list { width: 82%; max-width: 395px; margin: 0 auto; padding: 0; list-style: none; display: grid; gap: 16px; }
        .nt { position: relative; display: flex; gap: 18px; padding: 16px 20px 18px; border-radius: 14px; background: #ececec; }
        .nt.is-pay { z-index: 2; background: #f7f7f7; margin-top: 14px; }
        .nt.is-pay::after { content: ""; position: absolute; inset: -11px -21px; border: 2px solid var(--brand-deep); border-radius: 10px; pointer-events: none; }
        .nt-icon { flex: none; width: 48px; height: 48px; border-radius: 6px; background: #fff; overflow: hidden; }
        .nt-icon img { display: block; width: 100%; height: 100%; }
        .nt:not(.is-pay) .nt-icon, .nt:not(.is-pay) .nt-body { opacity: .62; }
        .nt-body { flex: 1; min-width: 0; }
        .nt-body p { margin: 0; }
        .nt-app { display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: #111; }
        .nt-app span { font-weight: 400; font-size: 10.5px; color: #555; }
        .nt-title { margin: 4px 0 6px !important; font-size: 12.5px; font-weight: 700; color: #111; }
        .nt-text { font-size: 10px; line-height: 1.75; color: #222; }
        .nt-badge { position: absolute; z-index: 3; left: 50%; top: calc(100% + 32px); transform: translateX(-50%); padding: 9px 18px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .nt-badge::before { content: ""; position: absolute; left: 50%; margin-left: -8px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        /* animatsiya: bildirishnomalar tepadan birin-ketin keladi, oxirgisi ajratiladi va bosiladi */
        .is-active .nt { animation: nt-in .5s cubic-bezier(.2,.8,.2,1) calc(.4s + var(--i) * .6s) both; }
        .is-active .nt:not(.is-pay) .nt-icon, .is-active .nt:not(.is-pay) .nt-body { animation: nt-dim .4s ease-out calc(1s + var(--i) * .6s) both; }
        .mock-push .nt.is-pay::after { opacity: 0; }
        .is-active .mock-push .nt.is-pay::after { animation: ct-ring .4s cubic-bezier(.3,1.6,.5,1) 2.1s both; }
        .is-active .mock-push .nt.is-pay { animation: nt-in .5s cubic-bezier(.2,.8,.2,1) 1.6s both, nt-press 2.4s ease-in-out 3.1s infinite; }
        .is-active .mock-push .a-badge { animation: cat-pop .5s cubic-bezier(.3,1.6,.5,1) 2.5s both, cat-bob 2.4s ease-in-out 3.1s infinite; transform-origin: 50% 0; }
        @keyframes nt-in { from { opacity: 0; transform: translateY(-26px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes nt-press { 0%, 70%, 100% { transform: none; background: #f7f7f7; } 80% { transform: scale(.98); background: #fff; } }
        @keyframes nt-dim { from { opacity: 1; } to { opacity: .62; } }
        @media (max-width: 480px) {
            .nt-list { width: 86%; }
            .nt { gap: 10px; padding: 12px 12px 14px; }
            .nt-icon { width: 38px; height: 38px; }
            .nt.is-pay::after { inset: -8px -10px; }
            .nt-text { font-size: 9.5px; }
        }
        /* ---------- 결제하기 · 2 (payment method mock) ---------- */
        .mock-pay { padding: 38px 24px 48px; text-align: left; }
        .py-h { margin: 0 0 10px 10px; font-size: 12.5px; font-weight: 700; }
        .py-box { position: relative; z-index: 2; border: 2px solid var(--brand-deep); border-radius: 10px; padding: 10px 12px; }
        .py-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 11px 12px; margin: 0; padding: 0; list-style: none; }
        .py-methods li { height: 52px; display: grid; place-items: center; border: 1px solid #d8d8d8; background: #fff; font-size: 12.5px; color: #111; }
        .py-methods li.is-pick { border-color: var(--brand); background: #fbeef0; color: var(--brand); }
        .py-npay { display: inline-flex; align-items: center; gap: 4px; color: #1ec800; font-size: 19px; font-weight: 900; letter-spacing: -.05em; }
        .py-npay i { display: inline-grid; place-items: center; width: 19px; height: 19px; border-radius: 50%; background: #1ec800; color: #fff; font-size: 12px; font-style: normal; }
        .py-kakao { display: inline-flex; align-items: center; height: 24px; padding: 0 9px 0 6px; border-radius: 14px; background: #ffdf00; color: #111; font-size: 17px; font-weight: 900; letter-spacing: -.06em; }
        .py-kakao::before { content: ""; width: 13px; height: 11px; border-radius: 50%; background: #111; margin-right: 2px; }
        .py-toss { display: inline-flex; align-items: center; color: #2b2f45; font-size: 19px; font-weight: 800; letter-spacing: -.06em; }
        .py-toss::before { content: ""; width: 19px; height: 19px; margin-right: 4px; border-radius: 50%; background: linear-gradient(135deg, #1258ff 50%, #20a7ff 51%); }
        .py-toss em { margin-left: 4px; font-style: normal; font-weight: 500; color: #4e5968; }
        .py-badge { position: absolute; z-index: 3; left: 50%; top: calc(100% + 28px); transform: translateX(-50%); padding: 9px 18px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .py-badge::before { content: ""; position: absolute; left: 50%; margin-left: -8px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .py-inst { display: flex; align-items: center; gap: 4px; margin: 12px 0 0 14px; font-size: 10px; color: #333; }
        .py-inst svg { width: 12px; height: 12px; }
        .py-rest { margin: 18px -12px 0; padding: 40px 26px 0; border-top: 7px solid #eee; }
        .py-rest p { margin: 0; }
        .py-h2 { margin: 0 0 22px; }
        .py-row { display: flex; justify-content: space-between; font-size: 10.5px; color: #4e4e4e; }
        .py-row + .py-row { margin-top: 8px; }
        .py-row b { color: #111; font-weight: 700; }
        .py-sub span:first-child::before { content: "•"; margin: 0 8px 0 4px; }
        .py-sub span:last-child { color: #999; }
        .py-total { margin-top: 10px !important; padding-top: 12px; border-top: 1px solid #ddd; color: #111; font-weight: 700; }
        .py-total small { margin-left: 8px; font-size: 8px; font-weight: 400; color: #666; }
        .py-total b { color: var(--brand); font-size: 13px; }
        .py-refund-h { display: flex; justify-content: space-between; align-items: center; margin: 24px 0 12px !important; font-size: 12.5px; font-weight: 700; }
        .py-refund-h svg { width: 16px; height: 16px; }
        .py-refund { padding: 18px; border: 1px solid #ddd; font-size: 10px; line-height: 1.8; color: #222; }
        .py-agree { display: flex; align-items: center; gap: 8px; margin-top: 14px !important; }
        .py-agree i { position: relative; width: 10px; height: 10px; border: 1px solid #c8c8c8; }
        .py-agree i::after { content: ""; position: absolute; inset: -1px; background: var(--brand) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpath d='m2 5 2 2 4-4' fill='none' stroke='%23fff' stroke-width='1.5'/%3E%3C/svg%3E") center/100% no-repeat; }
        .py-fine { margin-top: 18px !important; font-size: 8.5px; line-height: 1.8; color: #999; }
        .py-total2 { margin-top: 40px !important; border-top-color: #ddd; }
        .py-cta { display: grid; place-items: center; height: 37px; margin-top: 22px; background: var(--brand); color: #fff; font-size: 12px; }
        .a-pm { --a: lg-rise .4s cubic-bezier(.2,.7,.2,1) calc(.5s + var(--i) * .08s); }
        .is-active .py-methods li.is-pick { animation: lg-rise .4s cubic-bezier(.2,.7,.2,1) .58s both, py-pick .45s cubic-bezier(.3,1.6,.5,1) 1.4s both; }
        .is-active .mock-pay .a-badge { animation: cat-pop .5s cubic-bezier(.3,1.6,.5,1) 1.9s both, cat-bob 2.4s ease-in-out 2.5s infinite; transform-origin: 50% 0; }
        .is-active .py-agree i::after { animation: rg-check .3s cubic-bezier(.3,1.6,.5,1) 2.4s both; }
        .is-active .py-cta { animation: rg-press 2.4s ease-in-out 3s infinite; }
        @keyframes py-pick { 0% { border-color: #d8d8d8; background: #fff; color: #111; transform: none; } 50% { transform: scale(1.06); } 100% { border-color: var(--brand); background: #fbeef0; color: var(--brand); transform: none; } }

        /* ---------- 결제하기 · 3 (booking confirmed mock) ---------- */
        .mock-booked { padding: 70px 46px 130px; }
        .bk-check { display: grid; place-items: center; width: 52px; height: 52px; margin: 0 auto 14px; border-radius: 50%; background: var(--brand); }
        .bk-check svg { width: 32px; height: 32px; }
        .bk-check path { stroke-dasharray: 20; }
        .bk-h { margin: 0 0 16px; font-size: clamp(16px, calc(4.4 * var(--vw, 1vw)), 20px); font-weight: 700; }
        .bk-text { margin: 0 0 34px; font-size: clamp(12px, calc(3.3 * var(--vw, 1vw)), 14px); line-height: 1.7; color: #111; }
        .bk-panel { margin: 0 12px; padding: 26px 26px 22px; background: #f8eced; text-align: left; }
        .bk-panel-h { margin: 0 0 16px; font-size: 13px; font-weight: 700; }
        .bk-panel dl { margin: 0; }
        .bk-panel dl div { display: flex; font-size: 11px; line-height: 1; }
        .bk-panel dl div + div { margin-top: 18px; }
        .bk-panel dt { width: 90px; flex: none; }
        .bk-panel dd { margin: 0; }
        .bk-btn-box { position: relative; margin: 12px 50px 0; padding: 38px 0; border: 2px solid var(--brand-deep); border-radius: 10px; }
        .bk-btn { display: grid; place-items: center; height: 46px; margin: 0 auto; width: 66%; border: 1px solid var(--brand); color: var(--brand); font-size: 13px; }
        .bk-badge { position: absolute; left: 50%; top: calc(100% + 22px); transform: translateX(-50%); padding: 9px 18px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 16px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .bk-badge::before { content: ""; position: absolute; left: 50%; margin-left: -8px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .mock-booked .a-fade { --a: lg-fade .5s ease-out .9s; }
        .mock-booked .a-box { --a: lg-box .45s ease-out 1.2s; }
        .mock-booked .a-item { --a: lg-rise .4s ease-out calc(1.4s + var(--i) * .1s); }
        .mock-booked .bk-btn-box { opacity: 0; }
        .is-active .bk-check { animation: lg-pop .45s cubic-bezier(.3,1.6,.5,1) .3s both; }
        .is-active .bk-check path { animation: bk-draw .4s ease-out .7s both; }
        .is-active .mock-booked .bk-btn-box { animation: ct-ring .4s cubic-bezier(.3,1.6,.5,1) 2s both; }
        .is-active .mock-booked .a-badge { animation: cat-pop .5s cubic-bezier(.3,1.6,.5,1) 2.4s both, cat-bob 2.4s ease-in-out 3s infinite; transform-origin: 50% 0; }
        .is-active .bk-btn { animation: bk-press 2.4s ease-in-out 3.2s infinite; }
        @keyframes bk-draw { from { stroke-dashoffset: 20; } to { stroke-dashoffset: 0; } }
        @keyframes bk-press { 0%, 70%, 100% { transform: none; background: #fff; } 80% { transform: scale(.97); background: #fbeef0; } }
        @media (max-width: 480px) {
            .mock-pay { padding: 30px 12px 36px; }
            .py-box { padding: 8px; }
            .py-methods { gap: 8px; }
            .py-methods li { height: 44px; font-size: 11px; }
            .py-npay, .py-toss { font-size: 15px; }
            .py-kakao { font-size: 14px; }
            .py-rest { margin: 18px -4px 0; padding: 30px 12px 0; }
            .mock-booked { padding: 50px 16px 110px; }
            .bk-panel { margin: 0; padding: 20px 16px; }
            .bk-panel dt { width: 70px; }
            .bk-btn-box { margin: 12px 24px 0; padding: 28px 0; }
        }
        /* ---------- 후기작성 (review list mock) ---------- */
        .mock-review { padding: 46px 12px 420px; text-align: left; }
        .rv-tabs { display: grid; grid-template-columns: 1fr 1fr; margin: 0 0 26px; border-bottom: 1px solid #ccc; font-size: 12px; text-align: center; }
        .rv-tabs span { padding-bottom: 12px; }
        .rv-tabs .is-on { position: relative; color: var(--brand); }
        .rv-tabs .is-on::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 1px; background: var(--brand); }
        .rv-list { margin: 0; padding: 0; list-style: none; }
        .rv { display: grid; grid-template-columns: 94px 1fr; column-gap: 15px; }
        .rv:first-child { position: relative; z-index: 2; } /* belgi keyingi karta chizig'i ostida qolmasin */
        .rv + .rv { margin-top: 30px; padding-top: 30px; border-top: 1px solid #e6e6e6; }
        .rv-thumb { grid-row: span 2; width: 94px; height: 94px; display: grid; place-items: center; border: 1px solid #ccc; color: #888; font-size: 15px; }
        .rv-body p { margin: 0; color: #222; }
        .rv-hosp { font-size: 10px; }
        .rv-name { margin: 6px 0 8px !important; font-size: 13.5px; }
        .rv-meta { font-size: 10px; }
        .rv-meta + .rv-meta { margin-top: 6px !important; }
        .rv-meta span + span::before { content: "|"; margin: 0 10px; color: #bbb; }
        .rv-cta-wrap { position: relative; margin-top: 16px; }
        .rv-cta { display: grid; place-items: center; height: 31px; background: #fbeeee; color: var(--brand); font-size: 9.5px; }
        .rv-cta-wrap.is-pick { z-index: 2; }
        .rv-cta-wrap.is-pick::after { content: ""; position: absolute; inset: -11px -12px -10px -12px; border: 2px solid var(--brand-deep); border-radius: 10px; pointer-events: none; }
        .rv-badge { position: absolute; z-index: 3; left: 50%; top: calc(100% + 26px); transform: translateX(-50%); padding: 9px 18px; border-radius: 8px; background: var(--brand); color: #fff; font-size: clamp(14px, calc(3.8 * var(--vw, 1vw)), 17px); font-weight: 700; white-space: nowrap; box-shadow: 0 4px 10px rgba(174,25,50,.25); }
        .rv-badge::before { content: ""; position: absolute; left: 50%; margin-left: -8px; top: -8px; border: 8px solid transparent; border-top: 0; border-bottom-color: var(--brand); }
        .mock-review .a-item { --a: lg-rise .45s ease-out calc(.5s + var(--i) * .2s); }
        .mock-review .rv-cta-wrap.is-pick::after { opacity: 0; }
        .is-active .mock-review .rv-cta-wrap.is-pick::after { animation: ct-ring .4s cubic-bezier(.3,1.6,.5,1) 1.3s both; }
        .is-active .mock-review .a-badge { animation: cat-pop .5s cubic-bezier(.3,1.6,.5,1) 1.7s both, cat-bob 2.4s ease-in-out 2.3s infinite; transform-origin: 50% 0; }
        .is-active .rv-cta-wrap.is-pick .rv-cta { animation: rv-press 2.4s ease-in-out 2.6s infinite; }
        @keyframes rv-press { 0%, 70%, 100% { transform: none; background: #fbeeee; } 80% { transform: scale(.98); background: #f5d9de; } }
        @media (max-width: 480px) {
            .mock-review { padding: 36px 10px 300px; }
            .rv { grid-template-columns: 70px 1fr; column-gap: 10px; }
            .rv-thumb { width: 70px; height: 70px; font-size: 12px; }
            .rv-cta-wrap.is-pick::after { inset: -8px -6px; }
        }
        .slide-soon { margin: 60px 20px; color: var(--muted); }

        /* ---------- First-visit coach overlay ---------- */
        .coach:focus { outline: none; }
        .coach { position: absolute; left: 0; right: 0; bottom: 0; top: var(--hdr, 0px); z-index: 50; /* sarlavha va tablar ochiq qoladi */ display: flex; flex-direction: column; align-items: center; justify-content: flex-start; overflow-y: auto; padding: 16px 16px env(safe-area-inset-bottom); background: rgba(0,0,0,.824); /* oq fon ustida #2D2D2D */ color: #fff; text-align: center; animation: coach-in .25s ease-out; }
        .coach[hidden] { display: none; }
        @keyframes coach-in { from { opacity: 0; } }
        .coach-hand { --w: min(272px, calc(86 * var(--vw, 1vw))); position: relative; width: var(--w); height: 110px; flex: none; }
        .coach-hand img { position: absolute; left: 0; top: 0; width: 110px; height: 110px; transform: translateX(calc((var(--w) - 100px) / 2)); animation: coach-tap 3s ease-in-out infinite; }
        .coach-arrow { position: absolute; z-index: 2; top: 70px; width: 15px; height: 18px; filter: drop-shadow(0 0 2px rgba(0,0,0,.35)); }
        .coach-arrow.l { left: 0; }
        .coach-arrow.r { right: 0; }
        /* qo'l 110px; ko'rinadigan qismi ~15–85px; har ikki o'qqa 8px qolganda to'xtaydi */
        @keyframes coach-tap { 0%, 8% { transform: translateX(8px); } 42%, 58% { transform: translateX(calc(var(--w) - 108px)); } 92%, 100% { transform: translateX(8px); } }
        .coach-title { margin: clamp(16px, 4.5 * var(--vh, 1vh), 48px) 0 clamp(8px, 1.4 * var(--vh, 1vh), 14px); font-size: clamp(21px, calc(5.8 * var(--vw, 1vw)), 27px); font-weight: 700; letter-spacing: -.02em; }
        .coach-desc { margin: 0 0 clamp(24px, 8.5 * var(--vh, 1vh), 96px); font-size: clamp(15px, calc(4.2 * var(--vw, 1vw)), 19px); }
        .coach-stores { display: grid; grid-template-columns: repeat(2, 236px); gap: 54px; justify-content: center; } /* ikkala tugma bir xil o'lchamda */
        .store-badge { display: flex; align-items: center; justify-content: center; gap: 12px; height: 70px; padding: 0 14px; width: 100%; box-sizing: border-box; border: 1.5px solid #a6a6a6; border-radius: 9px; background: #000; color: #fff; text-decoration: none; text-align: left; }
        .store-badge svg { flex: none; width: 36px; height: 36px; }
        .store-badge small { display: block; font-size: 12px; font-weight: 500; letter-spacing: .02em; line-height: 1.1; white-space: nowrap; }
        .store-badge b { display: block; font-size: 26px; font-weight: 500; letter-spacing: -.01em; line-height: 1.1; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif; }
        .coach-actions { display: flex; flex-direction: column; align-items: center; gap: clamp(12px, 4 * var(--vh, 1vh), 48px); padding-top: 16px; }
        /* bo'sh joy: tepaga 3 ulush, blok bilan tugmalar orasiga 2 ulush (joy yetmasa — skroll) */
        /* maketdagi nisbat: qo'l ~26%, tugmalar oxiri ~66%, 다시 보지 않기 ~81%, 닫기 ~88% */
        .coach::before { content: ""; flex: 25 1 0; }
        .coach-spacer { flex: 11 1 0; }
        .coach::after { content: ""; flex: 9 1 0; }
        .coach-link { font-size: clamp(17px, calc(4.6 * var(--vw, 1vw)), 21px); font-weight: 500; padding: 6px 12px; }
        .coach-link.u { text-decoration: underline; text-underline-offset: 5px; }

        @media (max-width: 480px) {
            .guide-tab { min-width: 0; flex: 1 1 0; padding: 0 4px; }
            .slide-title { font-size: 21px; }
            .slide-lead { font-size: 15px; }
            .coach-desc { font-size: 14px; }
            .coach-stores { grid-template-columns: repeat(2, minmax(0, 158px)); gap: 14px; }
            .store-badge { height: 52px; padding: 0 8px; gap: 7px; }
            .store-badge svg { width: 24px; height: 24px; }
            .store-badge small { font-size: 9px; }
            .store-badge b { font-size: 17px; }
            .mock { width: calc(100% - 32px); padding: 25px 14px 20px; border-width: 9px; border-radius: 34px 34px 0 0; }
            .mock.mock-clinic { padding-top: 10px; } /* 찾아보기·4 va 예약하기·1 (klinika sahifasi) */
        }
        @media (prefers-reduced-motion: reduce) {
            .coach-hand img, .coach { animation: none; }
            .mock-anim .anim, .is-active .mock-anim .anim, .is-active .a-badge, .is-active .mock-cat .a-badge, .is-active .cat-side li.is-on, .is-active .lb-kakao.a-btn, .is-active .rg-on i::after, .is-active .rg-on, .is-active .rg-chip, .is-active .mock-region .a-badge, .is-active .rg-done, .is-active .mock-sort .a-badge, .is-active .sort-box .rg-sort span, .is-active .mock-sort .hosp-list.anim, .is-active .mock-clinic .a-badge, .is-active .mock-clinic-tabs .ct-ink, .is-active .mock-clinic-tabs .ct-tabs span, .is-active .mock-clinic-pick li.is-pick, .is-active .mock-clinic-pick li.is-pick::after, .is-active .tm-dates span.is-today::before, .is-active .tm-slots span.is-pick, .is-active .mock-time .a-badge, .is-active .tm-submit, .is-active .dn-sheet, .is-active .dn-check circle, .is-active .dn-check path, .is-active .mock-done .a-badge, .is-active .dn-note, .is-active .nt, .is-active .mock-push .nt.is-pay, .is-active .mock-push .nt.is-pay::after, .is-active .nt:not(.is-pay) .nt-icon, .is-active .nt:not(.is-pay) .nt-body, .is-active .mock-push .a-badge, .is-active .py-methods li.is-pick, .is-active .mock-pay .a-badge, .is-active .py-agree i::after, .is-active .py-cta, .is-active .bk-check, .is-active .bk-check path, .mock-booked .bk-btn-box, .is-active .mock-booked .bk-btn-box, .is-active .mock-booked .a-badge, .is-active .bk-btn, .is-active .mock-review .rv-cta-wrap.is-pick::after, .is-active .mock-review .a-badge, .is-active .rv-cta-wrap.is-pick .rv-cta { opacity: 1; animation: none; }
            .guide-track { scroll-behavior: auto; }
        }
    </style>
</head>
<body>
<div class="guide">
    <header class="guide-top">
        <div class="guide-bar">
            <button type="button" class="guide-back" id="guideBack" aria-label="뒤로 가기">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
            </button>
            <h1 class="guide-title">이용 가이드</h1>
        </div>
        <nav class="guide-tabs" role="tablist" aria-label="이용 가이드 단계">
            <button type="button" class="guide-tab" role="tab" data-tab="start" aria-selected="true">시작하기</button>
            <button type="button" class="guide-tab" role="tab" data-tab="browse" aria-selected="false">찾아보기</button>
            <button type="button" class="guide-tab" role="tab" data-tab="book" aria-selected="false">예약하기</button>
            <button type="button" class="guide-tab" role="tab" data-tab="pay" aria-selected="false">결제하기</button>
            <button type="button" class="guide-tab" role="tab" data-tab="review" aria-selected="false">후기작성</button>
        </nav>
    </header>

    <main class="guide-track" id="guideTrack" aria-live="polite">
        <!-- 시작하기 · 1 -->
        <section class="guide-slide" data-tab="start" aria-labelledby="s-start-1">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-start-1">미용시술 가격, 한곳에서 비교하세요</h2>
            <p class="slide-lead">피부과·성형외과 미용시술 가격을<br><strong>병원별로 한눈에 비교</strong>할 수 있어요</p>

            <div class="mock">
                <h3 class="mock-title">같은 시술, 가격은 어떻게 다를까요?</h3>
                <div class="compare">
                    <p class="compare-head">관자 필러 1cc (예시) · 강남 지역 등록 가격</p>
                    <dl class="bars">
                        <div class="bar-row"><dt>A 병원</dt><dd class="bar-track"><span class="bar-fill" data-w="27"></span></dd><dd class="bar-value" data-v="39000">39,000원</dd></div>
                        <div class="bar-row"><dt>B 병원</dt><dd class="bar-track"><span class="bar-fill" data-w="54"></span></dd><dd class="bar-value" data-v="55000">55,000원</dd></div>
                        <div class="bar-row"><dt>C 병원</dt><dd class="bar-track"><span class="bar-fill" data-w="81"></span></dd><dd class="bar-value" data-v="72000">72,000원</dd></div>
                    </dl>
                    <span class="compare-badge">병원별 가격을 한눈에!</span>
                </div>
                <p class="mock-note">동일하거나 유사한 미용시술도<br>병원과 이벤트에 따라 다양한 가격으로 제공돼요</p>
                <ul class="hosp-list">
                    <?php
                    $hospitals = [['A', '30,000'], ['B', '50,000'], ['C', '60,000']];
                    foreach ($hospitals as [$n, $price]): ?>
                    <li class="hosp">
                        <div class="hosp-thumb" aria-hidden="true"><?= $n ?> 병원</div>
                        <div class="hosp-body">
                            <p class="hosp-name"><?= $n ?> 병원</p>
                            <p class="hosp-addr">강남 &nbsp;|&nbsp; 서울 강남구 선릉로612(삼성동, 서울빌딩)</p>
                            <p class="hosp-item">관자 필러 1cc</p>
                            <p class="hosp-vat">VAT 포함<s>정가</s></p>
                            <p class="hosp-price"><?= $price ?><span>원</span></p>
                        </div>
                        <svg class="hosp-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z"/></svg>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- 시작하기 · 2 -->
        <section class="guide-slide" data-tab="start" aria-labelledby="s-start-2">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-start-2">간편하게 시작하세요</h2>
            <p class="slide-lead"><strong>카카오톡 · 구글 · Apple · 이메일</strong> 중<br>자주 쓰는 계정으로 바로 시작할 수 있어요</p>

            <div class="mock mock-login mock-anim">
                <div class="login-brand anim a-logo">
                    <img src="assets/media/about-guide-logo.svg?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-logo.svg') ?>" alt="서울미녀 SEOUL BEAUTY" width="210" height="76">
                </div>
                <div class="login-box anim a-box">
                    <span class="login-badge anim a-badge">자주 쓰는 계정으로 시작!</span>
                    <ul class="login-btns" aria-label="로그인 방법 예시">
                        <li class="lb lb-kakao anim a-btn" style="--i:0"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3C6.48 3 2 6.48 2 10.78c0 2.76 1.82 5.18 4.57 6.56l-.93 3.47c-.08.3.25.54.51.36l4.1-2.73c.57.08 1.16.12 1.75.12 5.52 0 10-3.48 10-7.78S17.52 3 12 3Z"/></svg>카카오톡으로 시작하기</li>
                        <li class="lb lb-google anim a-btn" style="--i:1"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.91h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.4Z"/><path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.760-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.39 13.87A6 6 0 0 1 6.08 12c0-.65.11-1.28.31-1.870V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.62Z"/><path fill="#EA4335" d="M12 6c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.62C7.18 7.760 9.39 6 12 6Z"/></svg>구글로 시작하기</li>
                        <li class="lb lb-apple anim a-btn" style="--i:2"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 12.54c-.02-2.19 1.79-3.25 1.87-3.3a4.02 4.02 0 0 0-3.17-1.72c-1.33-.14-2.62.8-3.3.8-.7 0-1.74-.78-2.88-.76a4.2 4.2 0 0 0-3.540 2.16c-1.53 2.65-.39 6.54 1.08 8.68.74 1.05 1.6 2.23 2.74 2.19 1.11-.05 1.53-.7 2.87-.7 1.33 0 1.72.7 2.88.67 1.2-.02 1.95-1.05 2.66-2.11a8.6 8.6 0 0 0 1.22-2.48 3.77 3.77 0 0 1-2.43-3.43ZM14.88 6.1a3.83 3.83 0 0 0 .88-2.75 3.9 3.9 0 0 0-2.54 1.31 3.65 3.65 0 0 0-.9 2.65 3.22 3.22 0 0 0 2.56-1.21Z"/></svg>Apple로 시작하기</li>
                        <li class="lb lb-email anim a-btn" style="--i:3"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h17A1.5 1.5 0 0 1 22 5.5v.3l-10 6.4L2 5.8zM2 8.2l10 6.4 10-6.4v10.3a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 2 18.5z"/></svg>이메일로 시작하기</li>
                    </ul>
                </div>
                <p class="login-links anim a-foot" aria-hidden="true"><span>계정 찾기</span><span>문의하기</span><span>회원가입</span></p>
                <p class="login-captcha anim a-foot" aria-hidden="true">이 페이지는 Google reCAPTCHA의 보호를 받아 사용자가<br>로봇이 아님을 확인합니다.<br><u>자세히 보기</u></p>
            </div>
        </section>

        <!-- Qolgan tablar (dizayn kutilmoqda) -->
        <!-- 찾아보기 · 1 -->
        <section class="guide-slide" data-tab="browse" aria-labelledby="s-browse-1">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-browse-1">시술별로 찾아보세요</h2>
            <p class="slide-lead"><strong>리프팅 · 보톡스 · 필러</strong> 등 카테고리에서<br>원하는 시술을 골라보세요</p>

            <div class="mock mock-cat mock-anim">
                <h3 class="mock-title anim a-fade">시술찾기</h3>
                <div class="cat-wrap">
                    <ul class="cat-side" aria-label="카테고리 예시">
                        <li class="anim a-side is-on" style="--i:0">리프팅</li>
                        <li class="anim a-side" style="--i:1">HIFU</li>
                        <li class="anim a-side" style="--i:2">보톡스</li>
                        <li class="anim a-side" style="--i:3">HA 필러</li>
                        <li class="anim a-side" style="--i:4">고분자필러</li>
                        <li class="anim a-side" style="--i:5">콜라겐 주사</li>
                        <li class="anim a-side" style="--i:6">연어주사</li>
                        <li class="anim a-side" style="--i:7">부스터</li>
                        <li class="anim a-side" style="--i:8">니들 RF</li>
                        <li class="anim a-side" style="--i:9">색소</li>
                        <li class="anim a-side" style="--i:10">제모</li>
                        <li class="anim a-side" style="--i:11">스킨케어</li>
                        <li class="anim a-side" style="--i:12">실리프팅</li>
                        <li class="anim a-side" style="--i:13">눈성형</li>
                        <li class="anim a-side" style="--i:14">코성형</li>
                        <li class="anim a-side" style="--i:15">윤곽성형</li>
                        <li class="anim a-side" style="--i:16">가슴수술</li>
                        <li class="anim a-side" style="--i:17">지방성형</li>
                        <li class="anim a-side" style="--i:18">치아</li>
                    </ul>
                    <div class="cat-main">
                        <div class="cat-box anim a-box">
                            <ul class="cat-items" aria-label="리프팅 시술 예시">
                                <li class="anim a-item" style="--i:0">온다 리프팅</li>
                                <li class="anim a-item" style="--i:1">티타늄</li>
                                <li class="anim a-item" style="--i:2">인모드</li>
                                <li class="anim a-item" style="--i:3">울타이트</li>
                                <li class="anim a-item" style="--i:4">튠페이스</li>
                                <li class="anim a-item" style="--i:5">써마지FLX</li>
                                <li class="anim a-item" style="--i:6">텐써마</li>
                                <li class="anim a-item" style="--i:7">울리지오</li>
                                <li class="anim a-item" style="--i:8">덴서티</li>
                                <li class="anim a-item" style="--i:9">세르프</li>
                                <li class="anim a-item" style="--i:10">볼뉴머</li>
                                <li class="anim a-item" style="--i:11">쿨페이스</li>
                            </ul>
                        </div>
                        <span class="cat-badge anim a-badge">카테고리에서 골라보세요!</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 찾아보기 · 2–4 (dizayn kutilmoqda) -->
        <!-- 찾아보기 · 2 -->
        <section class="guide-slide" data-tab="browse" aria-labelledby="s-browse-2">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-browse-2">지역별로 찾아보세요</h2>
            <p class="slide-lead"><strong>여러 지역</strong>을 한 번에 선택하고<br><strong>필터 선택 완료</strong>를 눌러주세요</p>

            <div class="mock mock-region mock-anim" aria-hidden="true">
                <div class="rg-search anim a-fade">
                    <span class="rg-input">시술, 병원 이름을 검색해보세요</span>
                    <span class="rg-pick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-6.3-7-11.5A7 7 0 0 1 19 9.5C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.6"/></svg>지역 선택</span>
                </div>
                <div class="rg-box anim a-box">
                        <ul class="rg-group"><li class="rg-on" style="--k:0"><i></i>강남</li><li><i></i>신사</li><li><i></i>압구정</li><li><i></i>청담</li><li><i></i>교대</li><li><i></i>삼성</li><li><i></i>송파</li><li class="rg-on" style="--k:1"><i></i>천호</li><li><i></i>목동</li><li><i></i>강북</li><li><i></i>노원</li></ul>
                        <ul class="rg-group"><li><i></i>인천</li><li><i></i>성남</li><li><i></i>동탄</li><li><i></i>일산</li><li><i></i>의정부</li></ul>
                        <ul class="rg-group"><li><i></i>부산</li><li><i></i>대구</li><li><i></i>대전</li><li><i></i>청주</li><li><i></i>광주</li><li><i></i>전주</li><li><i></i>춘천</li><li><i></i>제주</li></ul>
                    <span class="rg-badge anim a-badge">여러 지역을 한 번에 선택!</span>
                </div>
                <div class="rg-filter anim a-fade2">
                    <span class="rg-reset"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 12a8 8 0 1 1-2.4-5.7M20 4v4h-4"/></svg>초기화</span>
                    <span class="rg-chip" style="--k:0">강남<svg viewBox="0 0 12 12" stroke="currentColor" stroke-width="1.3"><path d="m2 2 8 8M10 2l-8 8"/></svg></span>
                    <span class="rg-chip" style="--k:1">천호<svg viewBox="0 0 12 12" stroke="currentColor" stroke-width="1.3"><path d="m2 2 8 8M10 2l-8 8"/></svg></span>
                    <span class="rg-done">필터 선택 완료</span>
                </div>
                <div class="rg-sort anim a-fade3">
                    <span class="is-on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h3v16H3zM9 9h3v11H9zM16 6v12m-3-3 3 3 3-3"/></svg>낮은가격</span>
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m12 7 1.5 3.2 3.5.4-2.6 2.4.7 3.4-3.1-1.8-3.1 1.8.7-3.4L7 10.6l3.5-.4z"/></svg>평점</span>
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M12 14v4m-4 2h8"/></svg>인기</span>
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 4h16v12H9l-5 4zM8 9h8M8 12h5"/></svg>후기</span>
                </div>
                <ul class="hosp-list anim a-fade3">
                    <?php foreach ($hospitals as [$n, $price]): ?>
                    <li class="hosp">
                        <div class="hosp-thumb"><?= $n ?> 병원</div>
                        <div class="hosp-body">
                            <p class="hosp-name"><?= $n ?> 병원</p>
                            <p class="hosp-addr">강남 &nbsp;|&nbsp; 서울 강남구 선릉로612(삼성동, 서울빌딩)</p>
                            <p class="hosp-item">관자 필러 1cc</p>
                            <p class="hosp-vat">VAT 포함<s>정가</s></p>
                            <p class="hosp-price"><?= $price ?><span>원</span></p>
                        </div>
                        <svg class="hosp-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z"/></svg>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <!-- 찾아보기 · 3 -->
        <section class="guide-slide" data-tab="browse" aria-labelledby="s-browse-3">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-browse-3">가장 합리적인 가격만 한눈에</h2>
            <p class="slide-lead"><strong>낮은가격 · 평점 · 인기 · 후기</strong> 순으로<br>나에게 맞는 병원을 빠르게 찾아보세요</p>

            <div class="mock mock-region mock-sort mock-anim" aria-hidden="true">
                <div class="rg-search anim a-fade">
                    <span class="rg-input">시술, 병원 이름을 검색해보세요</span>
                    <span class="rg-pick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-6.3-7-11.5A7 7 0 0 1 19 9.5C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.6"/></svg>지역 선택</span>
                </div>
                <div class="rg-filter anim a-fade">
                    <span class="rg-reset"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 12a8 8 0 1 1-2.4-5.7M20 4v4h-4"/></svg>초기화</span>
                    <span class="rg-chip" style="--k:0">강남<svg viewBox="0 0 12 12" stroke="currentColor" stroke-width="1.3"><path d="m2 2 8 8M10 2l-8 8"/></svg></span>
                    <span class="rg-chip" style="--k:1">천호<svg viewBox="0 0 12 12" stroke="currentColor" stroke-width="1.3"><path d="m2 2 8 8M10 2l-8 8"/></svg></span>
                    <span class="rg-done">필터 선택 완료</span>
                </div>
                <div class="sort-box anim a-box">
                    <div class="rg-sort">
                    <span class="is-on" style="--s:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h3v16H3zM9 9h3v11H9zM16 6v12m-3-3 3 3 3-3"/></svg>낮은가격</span>
                    <span style="--s:1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m12 7 1.5 3.2 3.5.4-2.6 2.4.7 3.4-3.1-1.8-3.1 1.8.7-3.4L7 10.6l3.5-.4z"/></svg>평점</span>
                    <span style="--s:2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M12 14v4m-4 2h8"/></svg>인기</span>
                    <span style="--s:3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 4h16v12H9l-5 4zM8 9h8M8 12h5"/></svg>후기</span>
                </div>
                    <span class="sort-badge anim a-badge">정렬 기준을 바꿔보세요!</span>
                </div>
                <ul class="hosp-list anim a-fade3">
                    <?php foreach ([['A', '30,000'], ['B', '50,000'], ['C', '60,000'], ['D', '80,000']] as [$n, $price]): ?>
                    <li class="hosp">
                        <div class="hosp-thumb"><?= $n ?> 병원</div>
                        <div class="hosp-body">
                            <p class="hosp-name"><?= $n ?> 병원</p>
                            <p class="hosp-addr">강남 &nbsp;|&nbsp; 서울 강남구 선릉로612(삼성동, 서울빌딩)</p>
                            <p class="hosp-item">관자 필러 1cc</p>
                            <p class="hosp-vat">VAT 포함<s>정가</s></p>
                            <p class="hosp-price"><?= $price ?><span>원</span></p>
                        </div>
                        <svg class="hosp-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z"/></svg>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <!-- 찾아보기 · 4 -->
        <section class="guide-slide" data-tab="browse" aria-labelledby="s-browse-4">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-browse-4">예약 전, 병원 정보를 꼼꼼하게</h2>
            <p class="slide-lead"><strong>의료진 · 시술목록 · 시술후기</strong>와<br>진료시간을 미리 확인하세요</p>

            <div class="mock mock-clinic mock-clinic-tabs mock-anim" aria-hidden="true">
                <div class="ct-tabs-box anim a-box">
                    <div class="ct-tabs"><span style="--s:0">홈</span><span style="--s:1">의료진</span><span style="--s:2">시술목록</span><span style="--s:3">시술후기</span><i class="ct-ink"></i></div>
                    <span class="ct-badge anim a-badge">탭을 눌러 둘러보세요!</span>
                </div>
                <img class="ct-photo anim a-fade2" src="assets/media/about-guide-clinic.jpg?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-clinic.jpg') ?>" alt="" width="380" height="126">
                <div class="ct-info anim a-fade3">
                    <p class="ct-name">청담오라클피부과의원<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z"/></svg></p>
                    <p class="ct-meta"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="m12 7 1.5 3.2 3.5.4-2.6 2.4.7 3.4-3.1-1.8-3.1 1.8.7-3.4L7 10.6l3.5-.4z"/></svg><b>4.9</b>&nbsp;/ 5.0</span><span><svg viewBox="0 0 24 24" fill="none" stroke="#e8a317" stroke-width="1.6"><path d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M12 14v4m-4 2h8"/></svg>고객평가우수병원</span></p>
                    <p class="ct-hours"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4m-7 8 2 2 4-4"/></svg><b>진료중</b>10:00 ~ 21:00 (공휴일/일요일 휴무)<svg class="ct-chev" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></p>
                </div>
                <h3 class="ct-q anim a-fade3">어떤 시술을 예약하시겠어요?</h3>
                <p class="ct-note anim a-fade3">시술에 대한 정보는 참고 사항이며, 병원 방문 시 개인의 상태에 따라 달라질 수 있어요</p>
                <ul class="ct-cards">
                    <li class="anim a-item" style="--i:0"><p>코어톡스 주름 (얼굴 전체)<span>1회</span></p><p class="ct-price"><s>정가</s><b>50,000</b>원</p></li>
                    <li class="anim a-item" style="--i:1"><p>코어톡스 주름 (얼굴 전체)<span>2회</span></p><p class="ct-price"><s>정가</s><b>60,000</b>원</p></li>
                    <li class="anim a-item" style="--i:2"><p>코어톡스 주름 (얼굴 전체)<span>3회</span></p><p class="ct-price"><s>정가</s><b>70,000</b>원</p></li>
                </ul>
            </div>
        </section>
        <!-- 예약하기 · 1 -->
        <section class="guide-slide" data-tab="book" aria-labelledby="s-book-1">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-book-1">시술을 골라 예약하세요</h2>
            <p class="slide-lead"><strong>병원 상세에서 원하는 시술을 고르고</strong><br><strong>예약하기</strong>를 눌러주세요</p>

            <div class="mock mock-clinic mock-clinic-pick mock-anim" aria-hidden="true">
                <div class="ct-tabs-box is-plain anim a-fade">
                    <div class="ct-tabs"><span style="--s:0">홈</span><span style="--s:1">의료진</span><span style="--s:2">시술목록</span><span style="--s:3">시술후기</span><i class="ct-ink"></i></div>
                </div>
                <img class="ct-photo anim a-fade2" src="assets/media/about-guide-clinic.jpg?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-clinic.jpg') ?>" alt="" width="380" height="126">
                <div class="ct-info anim a-fade3">
                    <p class="ct-name">청담오라클피부과의원<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z"/></svg></p>
                    <p class="ct-meta"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="m12 7 1.5 3.2 3.5.4-2.6 2.4.7 3.4-3.1-1.8-3.1 1.8.7-3.4L7 10.6l3.5-.4z"/></svg><b>4.9</b>&nbsp;/ 5.0</span><span><svg viewBox="0 0 24 24" fill="none" stroke="#e8a317" stroke-width="1.6"><path d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M12 14v4m-4 2h8"/></svg>고객평가우수병원</span></p>
                    <p class="ct-hours"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4m-7 8 2 2 4-4"/></svg><b>진료중</b>10:00 ~ 21:00 (공휴일/일요일 휴무)<svg class="ct-chev" viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></p>
                </div>
                <h3 class="ct-q anim a-fade3">어떤 시술을 예약하시겠어요?</h3>
                <p class="ct-note anim a-fade3">시술에 대한 정보는 참고 사항이며, 병원 방문 시 개인의 상태에 따라 달라질 수 있어요</p>
                <ul class="ct-cards">
                    <li class="anim a-item is-pick" style="--i:0"><p>코어톡스 주름 (얼굴 전체)<span>1회</span></p><p class="ct-price"><s>정가</s><b>50,000</b>원</p><span class="ct-badge pick-badge anim a-badge">원하는 시술을 선택하세요!</span></li>
                    <li class="anim a-item" style="--i:1"><p>코어톡스 주름 (얼굴 전체)<span>2회</span></p><p class="ct-price"><s>정가</s><b>60,000</b>원</p></li>
                    <li class="anim a-item" style="--i:2"><p>코어톡스 주름 (얼굴 전체)<span>3회</span></p><p class="ct-price"><s>정가</s><b>70,000</b>원</p></li>
                </ul>
            </div>
        </section>

        <!-- 예약하기 · 2–3 (dizayn kutilmoqda) -->
        <!-- 예약하기 · 2 -->
        <section class="guide-slide" data-tab="book" aria-labelledby="s-book-2">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-book-2">날짜와 시간만 고르면 돼요</h2>
            <p class="slide-lead">복잡한 과정 없이 <strong>날짜와 시간</strong>을 선택하고<br><strong>예약하기</strong>를 눌러주세요</p>

            <div class="mock mock-time mock-anim" aria-hidden="true">
                <div class="tm-sec anim a-fade">
                    <h3 class="tm-h">예약을 진행해볼까요?</h3>
                    <p class="tm-item">주름보톡스<span>×</span></p>
                    <p class="tm-price">[금액]원</p>
                </div>
                <div class="tm-sec anim a-fade2">
                    <h3 class="tm-h tm-plain">언제 방문하시겠어요?</h3>
                    <p class="tm-month"><b>3월</b><span>4월</span></p>
                    <div class="tm-week"><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span><span>일</span></div>
                    <div class="tm-dates"><span class="is-today">28<small>오늘</small></span><span class="">29</span><span class="">30</span><span class="">31</span><span class="">1</span><span class="">2</span><span class="is-off">3</span></div>
                </div>
                <div class="tm-sec tm-last anim a-fade3">
                    <h3 class="tm-h tm-plain">예약 시간을 선택해주세요</h3>
                    <p class="tm-mode">예약 확정 방식</p>
                    <p class="tm-mode-note">병원에서 확인 후 일정이 확정돼요</p>
                    <div class="tm-box anim a-box">
                        <span class="tm-badge anim a-badge">원하는 시간을 선택하세요!</span>
                        <div class="tm-slots"><span class="">10:00</span><span class="is-pick">10:30</span><span class="">11:00</span><span class="">11:30</span><span class="">12:00</span><span class="">12:30</span><span class="">13:00</span><span class="">13:30</span><span class="">14:00</span><span class="">14:30</span><span class="">15:00</span><span class="">15:30</span><span class="">16:00</span><span class="">16:30</span><span class="">17:00</span><span class="">17:30</span><span class="">18:00</span><span class="">19:30</span><span class="">20:00</span><span class="">20:30</span></div>
                    </div>
                    <span class="tm-submit">예약하기</span>
                </div>
            </div>
        </section>

        <!-- 예약하기 · 3 -->
        <section class="guide-slide" data-tab="book" aria-labelledby="s-book-3">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-book-3">병원이 확인하면 알려드려요</h2>
            <p class="slide-lead">예약 요청 후 병원이 일정을 확인해요<br>평균 <strong>1~3시간</strong> 안에 알림으로 알려드려요</p>

            <div class="mock mock-done mock-anim" aria-hidden="true">
                <div class="dn-top">
                    <p class="dn-bar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 5-7 7 7 7"/></svg>이대로 예약을 진행할까요?</p>
                    <p class="dn-tag"><span>시술예정</span><span>D-3</span></p>
                    <p class="dn-no"><span>예약번호</span>12345678</p>
                </div>
                <div class="dn-sheet">
                    <i class="dn-handle"></i>
                    <svg class="dn-check" viewBox="0 0 44 44"><circle cx="22" cy="22" r="21" fill="#AE1932"/><path d="m12.5 22.5 6.5 6.5 13-14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <h3 class="dn-title">예약 요청이 접수되었어요!</h3>
                    <p class="dn-text anim a-fade3">원하시는 날짜와 시간으로 예약을 요청했어요<br>병원에 예약 가능 여부를 확인하고 있어요</p>
                    <div class="dn-box anim a-box">
                        <span class="dn-badge anim a-badge">알림을 꼭 확인하세요!</span>
                        <div class="dn-panel">
                            <p class="dn-p-h">예상 확인 시간</p>
                            <p class="dn-p-t">평균 1~3시간 이내<br>(병원 상황에 따라 달라질 수 있어요)</p>
                            <div class="dn-note">
                                <p class="dn-n-h">알림을 꼭 확인해주세요!</p>
                                <p class="dn-p-t">예약 진행 상황은 알림(푸시)으로 안내해드려요<br>알림을 허용해두시면 빠르게 확인하실 수 있어요</p>
                            </div>
                        </div>
                    </div>
                    <p class="dn-btns anim a-fade3"><span class="is-fill">확인했어요</span><span>내 예약 현황</span></p>
                </div>
            </div>
        </section>
        <!-- 결제하기 · 1 -->
        <section class="guide-slide" data-tab="pay" aria-labelledby="s-pay-1">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-pay-1">알림이 오면 결제하세요</h2>
            <p class="slide-lead"><strong>결제를 진행해주세요</strong> 알림을 누르면<br>바로 결제 화면으로 이동해요</p>

            <div class="mock mock-push mock-anim" aria-hidden="true">
                <ul class="nt-list">
                    <li class="nt" style="--i:0">
                        <span class="nt-icon"><img src="assets/media/about-guide-appicon.png?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-appicon.png') ?>" alt="" width="48" height="48"></span>
                        <div class="nt-body"><p class="nt-app">서울미녀<span>지금</span></p><p class="nt-title">예약 요청이 접수되었어요!</p><p class="nt-text">원하시는 일정으로 병원에 예약 가능 여부를 확인하고 있어요</p></div>
                    </li>
                    <li class="nt" style="--i:1">
                        <span class="nt-icon"><img src="assets/media/about-guide-appicon.png?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-appicon.png') ?>" alt="" width="48" height="48"></span>
                        <div class="nt-body"><p class="nt-app">서울미녀<span>지금</span></p><p class="nt-title">병원 확인 중이에요</p><p class="nt-text">병원과 예약 가능 여부를 확인하고 있어요<br>확인이 완료되면 알림으로 안내해드릴게요</p></div>
                    </li>
                    <li class="nt is-pay" style="--i:2">
                        <span class="nt-icon"><img src="assets/media/about-guide-appicon.png?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-appicon.png') ?>" alt="" width="48" height="48"></span>
                        <div class="nt-body"><p class="nt-app">서울미녀<span>지금</span></p><p class="nt-title">결제를 진행해주세요!</p><p class="nt-text">병원에서 예약이 가능하다고 확인했어요<br>예약을 확정하려면 결제를 진행해주세요</p></div>
                        <span class="nt-badge anim a-badge">결제 알림을 눌러주세요!</span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- 결제하기 · 2 -->
        <section class="guide-slide" data-tab="pay" aria-labelledby="s-pay-2">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-pay-2">결제 수단을 고르고 결제!</h2>
            <p class="slide-lead"><strong>카드 · 계좌이체 · 간편결제</strong> 중<br>편한 방법으로 결제하세요</p>

            <div class="mock mock-pay mock-anim" aria-hidden="true">
                <h3 class="py-h anim a-fade">어떤 결제 수단을 이용하시겠어요?</h3>
                <div class="py-box anim a-box">
                    <ul class="py-methods">
                        <li class="anim a-pm" style="--i:0">계좌이체</li>
                        <li class="anim a-pm is-pick" style="--i:1">신용/체크카드</li>
                        <li class="anim a-pm" style="--i:2"><span class="py-npay"><i>N</i>pay</span></li>
                        <li class="anim a-pm" style="--i:3"><span class="py-kakao">pay</span></li>
                        <li class="anim a-pm" style="--i:4"><span class="py-toss">toss<em>pay</em></span></li>
                    </ul>
                    <span class="py-badge anim a-badge">결제 수단을 골라주세요!</span>
                </div>
                <p class="py-inst anim a-fade">신용카드 무이자 할부 안내<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg></p>
                <div class="py-rest anim a-fade3">
                    <h3 class="py-h py-h2">결제 금액을 확인해보세요</h3>
                    <p class="py-row"><span>시술 금액</span><b>[금액]원</b></p>
                    <p class="py-row py-sub"><span>할인</span><span>-[금액]원</span></p>
                    <p class="py-row py-sub"><span>포인트 사용 금액</span><span>-[포인트]P</span></p>
                    <p class="py-row py-total"><span>총 결제 금액<small>(VAT포함)</small></span><b>[금액]원</b></p>
                    <p class="py-refund-h">취소 및 환불 규정을 확인해주세요<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></p>
                    <div class="py-refund">
                        <p>방문 하루 전 오후 5시까지 앱에서 취소하면 100% 환불돼요<br>당일 취소는 병원에 직접 연락해 주세요</p>
                        <p class="py-agree"><i></i>위 내용을 모두 확인했어요</p>
                    </div>
                    <p class="py-fine">서비스 이용에 관한 개인정보 수집 동의<br>개인정보 및 민감정보 수집 이용, 개인정보 제3자 제공 동의</p>
                    <p class="py-fine">서울미녀는 고객 편의를 위해 예약·앱에서 결제 기능을 제공하며, 이 기능에 대해 의료기관으로부터 대가를 받지 않습니다. 거래의 당사자는 고객과 병원이며, 서울미녀는 이를 중개하지 않습니다. 위 내용을 확인하였으며, 결제에 동의합니다.</p>
                    <p class="py-row py-total py-total2"><span>총 결제 금액<small>(VAT포함)</small></span><b>[금액]원</b></p>
                    <span class="py-cta">결제하기</span>
                </div>
            </div>
        </section>

        <!-- 결제하기 · 3 -->
        <section class="guide-slide" data-tab="pay" aria-labelledby="s-pay-3">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-pay-3">예약이 확정됐어요!</h2>
            <p class="slide-lead">결제가 끝나면 <strong>예약이 바로 확정</strong>돼요<br><strong>마이페이지 › 내 예약</strong>에서 확인할 수 있어요</p>

            <div class="mock mock-booked mock-anim" aria-hidden="true">
                <span class="bk-check"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 12.5 4 4 8-9"/></svg></span>
                <h3 class="bk-h anim a-fade">예약이 확정되었어요!</h3>
                <p class="bk-text anim a-fade">결제가 완료되어 예약이 최종 확정되었습니다<br>예약 내역에서 상세 정보를 확인하세요</p>
                <div class="bk-panel anim a-box">
                    <p class="bk-panel-h">예약 정보</p>
                    <dl>
                        <div class="anim a-item" style="--i:0"><dt>병원</dt><dd>[병원명]</dd></div>
                        <div class="anim a-item" style="--i:1"><dt>시술</dt><dd>[시술명]</dd></div>
                        <div class="anim a-item" style="--i:2"><dt>날짜/시간</dt><dd>[날짜][시간]</dd></div>
                        <div class="anim a-item" style="--i:3"><dt>예약자</dt><dd>[성함][전화번호]</dd></div>
                        <div class="anim a-item" style="--i:4"><dt>예약번호</dt><dd>[예약번호]</dd></div>
                    </dl>
                </div>
                <div class="bk-btn-box">
                    <span class="bk-btn">예약 내역 보기</span>
                    <span class="bk-badge anim a-badge">예약 내역에서 확인하세요!</span>
                </div>
            </div>
        </section>
        <!-- 후기작성 -->
        <section class="guide-slide" data-tab="review" aria-labelledby="s-review-1">
            <div class="guide-dots" aria-hidden="true"></div>
            <h2 class="slide-title" id="s-review-1">시술 후 후기를 남겨주세요</h2>
            <p class="slide-lead"><strong>마이페이지 › 내가 작성한 후기</strong>의<br><strong>작성가능</strong> 탭에서 바로 쓸 수 있어요.</p>

            <div class="mock mock-review mock-anim" aria-hidden="true">
                <p class="rv-tabs anim a-fade"><span class="is-on">작성가능</span><span>작성완료</span></p>
                <ul class="rv-list">
                    <li class="rv anim a-item" style="--i:0">
                        <div class="rv-thumb">A 병원</div>
                        <div class="rv-body">
                            <p class="rv-hosp">[병원명]</p>
                            <p class="rv-name">[시술명]</p>
                            <p class="rv-meta"><span>[지역]</span><span>[주소]</span></p>
                            <p class="rv-meta"><span>[원장명]</span><span>시술일[날짜]</span></p>
                        </div>
                        <div class="rv-cta-wrap is-pick"><span class="rv-cta">후기를 작성해볼까요?</span><span class="rv-badge anim a-badge">여기를 눌러 후기를 써보세요!</span></div>
                    </li>
                    <li class="rv anim a-item" style="--i:1">
                        <div class="rv-thumb">B 병원</div>
                        <div class="rv-body">
                            <p class="rv-hosp">[병원명]</p>
                            <p class="rv-name">[시술명]</p>
                            <p class="rv-meta"><span>[지역]</span><span>[주소]</span></p>
                            <p class="rv-meta"><span>[원장명]</span><span>시술일[날짜]</span></p>
                        </div>
                        <div class="rv-cta-wrap"><span class="rv-cta">후기를 작성해볼까요?</span></div>
                    </li>
                </ul>
            </div>
        </section>
    </main>

    <!-- Birinchi kirishda chiqadigan yo'riqnoma qatlami -->
    <div class="coach" id="guideCoach" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="coachTitle" hidden>
        <div class="coach-hand" aria-hidden="true">
            <svg class="coach-arrow l" viewBox="0 0 15 18"><path d="M0 8.66 15 0v17.32z" fill="#fff"/></svg>
            <img src="assets/media/about-guide-hand.png?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-guide-hand.png') ?>" alt="" width="142" height="142">
            <svg class="coach-arrow r" viewBox="0 0 15 18"><path d="M15 8.66 0 17.32V0z" fill="#fff"/></svg>
        </div>
        <p class="coach-title" id="coachTitle">화면을 좌우로 이동할 수 있어요</p>
        <p class="coach-desc">좌우로 움직여 서울미녀 이용 방법을 확인해 보세요</p>
        <div class="coach-stores">
            <a class="store-badge" href="https://play.google.com/store/apps/details?id=com.celebhospital.patient&amp;pcampaignid=web_share" target="_blank" rel="noopener noreferrer" aria-label="Google Play에서 서울미녀 앱 다운로드">
                <svg viewBox="0 0 40 44" aria-hidden="true">
                    <path d="M2 2 23 22 2 42c-.7-.4-1-1.2-1-2.1V4.1C1 3.2 1.3 2.4 2 2z" fill="#00d7fe"/>
                    <path d="M30 15 23 22 2 2c.6-.4 1.5-.4 2.4.1z" fill="#00f076"/>
                    <path d="M30 29 4.4 41.9c-.9.5-1.8.5-2.4.1l21-20z" fill="#f63448"/>
                    <path d="M30 15l7.3 4.1c2.2 1.3 2.2 4.5 0 5.8L30 29l-7-7z" fill="#ffd400"/>
                </svg>
                <span><small>GET IT ON</small><b>Google Play</b></span>
            </a>
            <a class="store-badge" href="https://apps.apple.com/kr/app/서울미녀/id6805706423" target="_blank" rel="noopener noreferrer" aria-label="App Store에서 서울미녀 앱 다운로드">
                <svg viewBox="0 0 384 512" fill="#fff" aria-hidden="true"><path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/></svg>
                <span><small>Download on the</small><b>App Store</b></span>
            </a>
        </div>
        <i class="coach-spacer" aria-hidden="true"></i>
        <div class="coach-actions">
            <button type="button" class="coach-link u" id="coachNever">다시 보지 않기</button>
            <button type="button" class="coach-link" id="coachClose">닫기</button>
        </div>
    </div>
</div>


<script>
(function () {
    var HIDE_KEY = 'seoulBeauty.aboutGuide.coachHidden';
    var track = document.getElementById('guideTrack');
    var slides = [].slice.call(track.querySelectorAll('.guide-slide'));
    var tabs = [].slice.call(document.querySelectorAll('.guide-tab'));
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = -1;

    // Har bir slayd uchun o'z tabidagi nuqtalarni chizish
    slides.forEach(function (slide) {
        var group = slides.filter(function (s) { return s.dataset.tab === slide.dataset.tab; });
        var dots = slide.querySelector('.guide-dots');
        if (!dots) return;
        if (group.length < 2) { dots.style.visibility = 'hidden'; dots.innerHTML = '<i></i>'; return; }
        dots.innerHTML = group.map(function (s) { return '<i' + (s === slide ? ' class="is-on"' : '') + '></i>'; }).join('');
    });

    function goTo(index) {
        track.scrollTo({ left: index * track.clientWidth, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    function setActive(index) {
        if (index === current || !slides[index]) return;
        current = index;
        slides.forEach(function (s, i) { if (i !== index) s.classList.remove('is-active'); });
        slides[index].classList.remove('is-active');
        void slides[index].offsetWidth; // animatsiyani qayta boshlash
        slides[index].classList.add('is-active');
        var tab = slides[index].dataset.tab;
        tabs.forEach(function (t) {
            var on = t.dataset.tab === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on && t.scrollIntoView) t.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
        });
        if (slides[index].querySelector('.bars')) animatePrices(slides[index]);
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            for (var i = 0; i < slides.length; i++) if (slides[i].dataset.tab === t.dataset.tab) return goTo(i);
        });
    });

    track.addEventListener('scroll', function () {
        setActive(Math.round(track.scrollLeft / track.clientWidth));
    }, { passive: true });


    document.addEventListener('keydown', function (e) {
        if (!coach.hidden) { if (e.key === 'Escape') closeCoach(false); return; }
        if (e.key === 'ArrowRight' && current < slides.length - 1) goTo(current + 1);
        if (e.key === 'ArrowLeft' && current > 0) goTo(current - 1);
    });

    // Narx ustunlari: 0 dan yakuniy qiymatgacha animatsiya
    function animatePrices(slide) {
        var fills = slide.querySelectorAll('.bar-fill');
        var values = slide.querySelectorAll('.bar-value');
        var fmt = function (n) { return Math.round(n).toLocaleString('ko-KR') + '원'; };
        if (reduceMotion) {
            fills.forEach(function (f) { f.style.width = f.dataset.w + '%'; });
            values.forEach(function (v) { v.textContent = fmt(v.dataset.v); });
            return;
        }
        var start = null, duration = 1000;
        function step(now) {
            if (start === null) start = now;
            var p = Math.min(1, (now - start) / duration), e = 1 - Math.pow(1 - p, 3);
            fills.forEach(function (f) { f.style.width = (f.dataset.w * e) + '%'; });
            values.forEach(function (v) { v.textContent = fmt(v.dataset.v * e); });
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // Orqaga
    document.getElementById('guideBack').addEventListener('click', function () {
        if (history.length > 1 && document.referrer) history.back();
        else location.href = 'index.php';
    });

    // Yo'riqnoma qatlami
    var coach = document.getElementById('guideCoach');
    function storageGet() { try { return localStorage.getItem(HIDE_KEY); } catch (e) { return null; } }
    function storageSet() { try { localStorage.setItem(HIDE_KEY, '1'); } catch (e) {} }
    function closeCoach(forever) {
        if (forever) storageSet();
        coach.hidden = true;
    }
    document.getElementById('coachClose').addEventListener('click', function () { closeCoach(false); });
    document.getElementById('coachNever').addEventListener('click', function () { closeCoach(true); });
    if (storageGet() !== '1') {
        coach.hidden = false;
        coach.focus({ preventScroll: true });
    }

    // 440px (iPhone Pro Max) dan tor ekranlarda butun sahifani mutanosib kichraytirish:
    // sahifa 440px kenglikda chiziladi va ekranga sig'adigan qilib zoom qilinadi.
    var BASE_W = 440;
    var guideEl = document.querySelector('.guide');
    function fitToScreen() {
        var vw = window.innerWidth, vh = window.innerHeight;
        var z = Math.min(1, vw / BASE_W);
        [guideEl].forEach(function (el) {
            if (z < 1) {
                el.style.zoom = z;
                el.style.maxWidth = 'none';
                el.style.width = (vw / z) + 'px';
                el.style.height = (vh / z) + 'px';
                el.style.setProperty('--vw', (vw / z / 100) + 'px'); // vw o'lchamlari ham 440px asosida
                el.style.setProperty('--vh', (vh / z / 100) + 'px'); // yo'riqnoma bo'shliqlari ekran balandligiga bog'liq
            } else {
                el.style.zoom = el.style.maxWidth = el.style.width = el.style.height = '';
                el.style.removeProperty('--vw');
                el.style.removeProperty('--vh');
            }
        });
        guideEl.style.setProperty('--hdr', document.querySelector('.guide-top').offsetHeight + 'px'); // qatlam sarlavha ostidan boshlanadi
        if (current >= 0) track.scrollLeft = current * track.clientWidth;
    }
    fitToScreen();
    window.addEventListener('resize', fitToScreen);
    window.addEventListener('orientationchange', function () { setTimeout(fitToScreen, 200); });

    setActive(0);
}());
</script>
</body>
</html>

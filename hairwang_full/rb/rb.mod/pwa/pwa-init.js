(function () {
    if (!('serviceWorker' in navigator)) return;
    if (!(window.RB_PWA_CFG && window.RB_PWA_CFG.enabled === 1)) return;

    const __rbSyncInstalled = (async function syncInstalledOnce() {
        try {
            const r = await fetch('/rb/rb.mod/pwa/api/is_installed.php', {
                credentials: 'include',
                cache: 'no-store'
            });
            const j = await r.json();
            if (j && j.installed) {
                try {
                    localStorage.setItem('rb_pwa_app_installed', '1');
                } catch (_) {}
                document.getElementById('rb-pwa-install')?.remove();
                document.getElementById('rb-pwa-push-help')?.remove();
            }
        } catch (_) {}
    })();


   // 관리자 UI 설정 연동  ← __rbSyncInstalled 바로 아래 (지금 위치 OK)
const UI = (window.RB_PWA_UI || {});
const USE = { install: UI.pop2_use ? 1 : 0, pushHelp: UI.pop1_use ? 1 : 0 };
const TXT = {
  install_title: (UI.words3 || '앱으로 설치하면 더 편해요'),
  install_body:  (UI.words4 || '홈 화면에서 바로 접속하고, 주요 알림을 푸시로 받을 수 있어요.'),
  push_title:    (UI.words1 || '알림이 꺼져 있어요'),
  push_body:     (UI.words2 || '주소(URL) 옆 아이콘을 클릭 하시면 알림 설정을 변경할 수 있어요.')
};

    // ---------- 팝업 세로 스택 레이아웃 ----------
    const POPUP_BOTTOM_GAP = 20; // 화면 하단 여백
    const POPUP_STACK_GAP = 10; // 팝업 간격

    function isInstalledMark() {
        try {
            return localStorage.getItem('rb_pwa_app_installed') === '1';
        } catch (_) {
            return false;
        }
    }

    // 현재 창이 팝업(별도 윈도우)인지 휴리스틱으로 판별
    function isPopupWindow() {
        try {
            // 1) window.open으로 열린 경우 대부분 opener가 존재
            if (window.opener && !window.opener.closed) return true;

            // 2) 관례적으로 팝업 window.name 사용 (선택)
            if (window.name && /popup|pop|win|modal|layer/i.test(window.name)) return true;

            // 3) 데스크톱에서 아주 작은 외곽 크기 + opener 존재 -> 팝업일 확률 높음
            const ua = navigator.userAgent || '';
            const isMobile = /Android|iPhone|iPad|iPod|Mobile|Windows Phone|BlackBerry/i.test(ua);
            const tiny = (window.outerWidth && window.outerHeight) ? (window.outerWidth < 600 || window.outerHeight < 500) : false;
            if (!isMobile && tiny && window.opener) return true;
        } catch (_) {}
        return false;
    }


    function hasInstalledCookie() {
        return document.cookie.indexOf('rb_pwa_installed=1') !== -1;
    }

    function positionPopups() {
        const install = document.getElementById('rb-pwa-install'); // 설치 패널: 항상 "아래"
        const push = document.getElementById('rb-pwa-push-help'); // 알림 패널: "위"에 쌓임
        let bottom = POPUP_BOTTOM_GAP;

        // 설치 패널이 있으면 맨 아래 배치
        if (install) {
            install.style.right = '20px';
            install.style.bottom = bottom + 'px';
            install.style.position = 'fixed';
            bottom += install.offsetHeight + POPUP_STACK_GAP;
        }

        // 알림 패널이 있으면 설치 패널 위로(설치 없으면 바로 하단)
        if (push) {
            push.style.right = '20px';
            push.style.bottom = (install ? bottom : POPUP_BOTTOM_GAP) + 'px';
            push.style.position = 'fixed';
        }
    }

    function isStandaloneApp() {
        try {
            // 크로미움/사파리 PWA
            const modes = ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'];
            if (typeof window.matchMedia === 'function' && modes.some(m => matchMedia(`(display-mode: ${m})`).matches)) {
                return true;
            }
        } catch (_) {}
        // iOS Safari PWA 전용 플래그
        try {
            if (navigator.standalone) return true;
        } catch (_) {}
        return false;
    }

    function isMobileWeb() {
        return isMobileUA() && !isStandaloneApp();
    }

    function isMobileUA() {
        try {
            if (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean') {
                return navigator.userAgentData.mobile;
            }
        } catch (_) {}
        return /Android|iPhone|iPad|iPod|Mobile|Windows Phone|BlackBerry/i.test(navigator.userAgent || '');
    }

    function onBodyReady(cb) {
        if (document.body) return cb();
        document.addEventListener('DOMContentLoaded', cb, {
            once: true
        });
    }

    // DOM 변화 직후 레이아웃 갱신이 필요할 때 호출
    function schedulePosition() {
        requestAnimationFrame(positionPopups);
        requestAnimationFrame(positionPopups); // 폰트/이미지 로드 지연 보정
    }

    // 리사이즈 시에도 정렬 보정
    window.addEventListener('resize', positionPopups);


    // 공통: 아이콘 URL (있으면 노출)
    function rbGuessAppIcon() {
        // 설정 경로의 192 아이콘을 기본 가정
        return '/data/pwa/icons/icon-192.png';
    }

    // 공통: 카드 폭 반응형(모바일 ≤512px 에서는 100%)
    function setCardWidth(el) {
        if (!el) return;
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        el.style.width = (vw <= 512) ? 'calc(100vw - 40px)' : '300px';
    }



    // ---------- 공통 유틸 ----------
    const SNOOZE_MS = 6 * 60 * 60 * 1000; // 6시간
    const LS = window.localStorage;

    function nowMs() {
        return Date.now();
    }

    function getMs(key) {
        try {
            return parseInt(LS.getItem(key) || '0', 10) || 0;
        } catch (_) {
            return 0;
        }
    }

    function setMs(key, ms) {
        try {
            LS.setItem(key, String(ms));
        } catch (_) {}
    }

    function clearKey(key) {
        try {
            LS.removeItem(key);
        } catch (_) {}
    }

    function isSnoozed(key) {
        const until = getMs(key);
        return until && until > nowMs();
    }

    function snoozeFor(key, ms) {
        setMs(key, nowMs() + ms);
    }

    // 같은 기기 식별 (중복 레코드 방지)
    function rbGetDeviceUID() {
        try {
            var k = 'rb_pwa_uid';
            var v = LS.getItem(k);
            if (!v) {
                v = (self.crypto && crypto.randomUUID) ?
                    crypto.randomUUID() :
                    (Math.random().toString(36).slice(2) + Date.now().toString(36));
                LS.setItem(k, v);
            }
            return v;
        } catch (_) {
            return 'tmp-' + (navigator.userAgent || '') + '-' + Date.now();
        }
    }
    const DEVICE_UID = rbGetDeviceUID();

    let deferredPrompt = null;

    // ---------- 설치 패널 ----------
    const INSTALL_SNOOZE_KEY = 'rb_install_snooze_until';

    function ensureInstallPanel() {
        if (!USE.install) return;   //관리자에서 설치팝업 사용 OFF면 생성 금지
        // 팝업 창이면 생성 안 함
        if (isPopupWindow()) return;
        // 앱(standalone)이거나 설치마크가 있으면 절대 표시하지 않음
        if (isStandaloneApp() || isInstalledMark() || hasInstalledCookie()) return;
        if (isSnoozed(INSTALL_SNOOZE_KEY)) return;
        if (document.getElementById('rb-pwa-install')) return;

        if (!document.body) {
            document.addEventListener('DOMContentLoaded', ensureInstallPanel, {
                once: true
            });
            return;
        }

        const wrap = document.createElement('div');
        wrap.id = 'rb-pwa-install';
        wrap.style.cssText = [
    'z-index:101; position:fixed; right:20px; bottom:20px;',
    'border-radius:18px; background:#fff; color:#111;',
    'box-shadow:0 18px 40px rgba(0,0,0,.18);',
    'padding:18px 18px 12px; line-height:1.45; overflow:hidden'
  ].join(';');

        // 아이콘: 있으면 상단 중앙 원형
        const iconUrl = rbGuessAppIcon();
        const iconHtml = `
    <div style="display:flex; justify-content:center; margin-top:6px; margin-bottom:10px;">
      <img id="rb-install-icon" src="${iconUrl}" alt="app"
           style="width:48px; height:48px; border-radius:12px; object-fit:cover;"/>
    </div>
  `;

        wrap.innerHTML = `
    <button id="rb-pwa-close" aria-label="닫기"
            style="position:absolute; top:17px; right:17px; background:transparent; border:0; color:#111; font-size:20px; cursor:pointer; line-height:1">×</button>

    ${iconHtml}

    <div style="text-align:center; padding:0 6px;">
      <div class="font-B" style="font-size:16px; margin-bottom:6px;">${TXT.install_title}</div>
      <div class="font-R" style="font-size:14px; opacity:.75;">${TXT.install_body}</div>

      <button id="rb-pwa-install-btn"
              style="margin-top:14px; width:100%; height:44px; border:0; border-radius:999px; cursor:pointer;
                     background:#25282B; color:#fff; font-size:15px;">
        설치
      </button>

      <div id="rb-pwa-ios-help" style="display:none; margin-top:10px; font-size:12px; opacity:.9;">
        iOS: <b>공유</b> 버튼 → <b>홈 화면에 추가</b> 으로 설치하세요.
      </div>

      <button id="rb-pwa-install-later"
              style="display:block; margin:12px auto 2px; background:transparent; border:0; color:#9aa1a7;
                     font-size:13px; text-decoration:underline; cursor:pointer;">
        나중에 할게요
      </button>
    </div>
  `;

        document.body.appendChild(wrap);
        setCardWidth(wrap);
        schedulePosition();

        // 아이콘 로드 실패 시 숨김
        const img = wrap.querySelector('#rb-install-icon');
        if (img) img.onerror = () => {
            img.parentElement.style.display = 'none';
        };

        // 반응형
        window.addEventListener('resize', () => {
            setCardWidth(wrap);
            schedulePosition();
        });

        // 이벤트 바인딩
        const btnInstall = document.getElementById('rb-pwa-install-btn');
        const btnLater = document.getElementById('rb-pwa-install-later');
        const btnClose = document.getElementById('rb-pwa-close');

        btnInstall.onclick = function () {
            try {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt = null;
                } else if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
                    const iosHelp = document.getElementById('rb-pwa-ios-help');
                    if (iosHelp) {
                        iosHelp.style.display = 'block';
                        schedulePosition();
                    }
                }
            } catch (_) {}
        };

        btnLater.onclick = function () {
            snoozeFor(INSTALL_SNOOZE_KEY, SNOOZE_MS);
            wrap.remove();
            schedulePosition();
        };
        btnClose.onclick = function () {
            wrap.remove();
            schedulePosition();
        };
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        onBodyReady(() => __rbSyncInstalled.finally(() => ensureInstallPanel()));
    });

    // iOS는 수동 노출 (스누즈 적용)
    if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
        onBodyReady(() => {
            // 앱(standalone)이면 설치 패널을 아예 만들지 않음
            __rbSyncInstalled.finally(() => {
                if (isStandaloneApp() || isInstalledMark() || hasInstalledCookie()) return;
                ensureInstallPanel();
            });
            const iosHelp = document.getElementById('rb-pwa-ios-help');
            if (iosHelp && document.getElementById('rb-pwa-install')) {
                iosHelp.style.display = 'block';
            }
        });
    }
    // ---------- 알림 권한 안내 배너 ----------
    const PUSH_SNOOZE_KEY = 'rb_push_snooze_until';

    function ensurePushHelpPanel() {
        if (!USE.pushHelp) return;  //관리자에서 알림팝업 사용 OFF면 생성 금지
        if (isPopupWindow()) return;
        if (isSnoozed(PUSH_SNOOZE_KEY)) return;

        // 앱(standalone)에서는 안내 배너 자체를 노출하지 않음
        if (isStandaloneApp() || isInstalledMark() || hasInstalledCookie()) return;
        if (document.getElementById('rb-pwa-push-help')) return;

        // 모바일 웹(브라우저 탭)에서는 배너 감춤
        if (isMobileWeb()) return;

        if (!document.body) {
            document.addEventListener('DOMContentLoaded', ensurePushHelpPanel, {
                once: true
            });
            return;
        }


        // 앱(standalone) vs 일반 브라우저/PC
        const displayModes = ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'];
        const isStandalone = (function () {
            try {
                if (typeof window.matchMedia === 'function') {
                    if (displayModes.some(m => matchMedia(`(display-mode: ${m})`).matches)) return true;
                }
            } catch (_) {}
            return !!(navigator.standalone);
        })();
        
        const guideFixedStandalone = '앱 설정에서 알림을 활성화 할 수 있어요.';
        const guideDefaultBrowser  = '주소(URL) 옆 아이콘을 클릭 하시면 알림 설정을 변경할 수 있어요.';

        // TXT.push_body(= words2)가 있으면 브라우저용 문구로 사용, 없으면 기본값
        const guideDefault = (TXT.push_body && TXT.push_body.trim())
          ? TXT.push_body
          : guideDefaultBrowser;

        // 최종 가이드: standalone이면 항상 고정 문구, 그 외에는 guideDefault
        const guide = isStandalone ? guideFixedStandalone : guideDefault;

        const wrap = document.createElement('div');
        wrap.id = 'rb-pwa-push-help';
        wrap.style.cssText = [
    'z-index:100; position:fixed; right:20px; bottom:20px;',
    'border-radius:18px; background:#fff; color:#111;',
    'box-shadow:0 18px 40px rgba(0,0,0,.18);',
    'padding:18px 18px 12px; line-height:1.45; overflow:hidden'
  ].join(';');


        wrap.innerHTML = `
    <button id="rb-push-x" aria-label="닫기"
            style="position:absolute; top:17px; right:17px; background:transparent; border:0; color:#111; font-size:20px; cursor:pointer; line-height:1">×</button>



    <div style="text-align:center; padding:0 6px;">
      <div class="font-B" style="font-size:16px; margin-bottom:6px;">${TXT.push_title}</div>
      <div class="font-R" style="font-size:14px; opacity:.75;">${guide}</div>

      <button id="rb-push-later"
              style="display:block; margin:12px auto 2px; background:transparent; border:0; color:#9aa1a7;
                     font-size:13px; text-decoration:underline; cursor:pointer;">
        나중에 할게요
      </button>
    </div>
  `;

        document.body.appendChild(wrap);
        setCardWidth(wrap);
        schedulePosition();



        window.addEventListener('resize', () => {
            setCardWidth(wrap);
            schedulePosition();
        });

        const btnLater = document.getElementById('rb-push-later');
        const btnX = document.getElementById('rb-push-x');

        btnLater.onclick = function () {
            snoozeFor(PUSH_SNOOZE_KEY, SNOOZE_MS);
            wrap.remove();
            schedulePosition();
        };
        btnX.onclick = function () {
            wrap.remove();
            schedulePosition();
        };
    }

    // ---------- 서버 보고 & 구독 해지 ----------
    async function rbMarkUnsubscribed(reg, reason) {
        const why = (reason === 'denied') ? 'denied' : 'uninstall';
        try {
            const sub = reg ? await reg.pushManager.getSubscription() : null;

            try {
                await fetch('/rb/rb.mod/pwa/api/unsubscribe.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        endpoint: sub ? sub.endpoint : '',
                        device_uid: DEVICE_UID,
                        reason: why
                    })
                });
            } catch (_) {}

            if (sub) {
                try {
                    await sub.unsubscribe();
                } catch (_) {}
            }
        } catch (_) {}
    }

    // ---------- 진입점 ----------
    async function init() {
        // SW 등록
        let reg = null;
        try {
            reg = await navigator.serviceWorker.register('/rb/rb.mod/pwa/service-worker.js');
        } catch (_) {
            // SW 등록 실패 → 설치 패널은 그대로, 푸시는 불가
        }

        if (!(window.RB_PWA_CFG && window.RB_PWA_CFG.push === 1)) {
            // 푸시 미사용이면 설치 패널만 동작
            return;
        }

        if (typeof Notification === 'undefined' || !reg || !reg.pushManager) {
            // 브라우저가 푸시를 지원하지 않음 → 안내 패널 항상 노출(스누즈 제외)
            ensurePushHelpPanel('unsupported');
            return;
        }

        // 권한 확인
        let perm = 'default';
        try {
            perm = Notification.permission;
        } catch (_) {}

        // default(미확정)면 한 번 요청
        if (perm === 'default') {
            try {
                perm = await Notification.requestPermission();
            } catch (_) {}
        }

        // ---- 알림 불가하면 무조건 안내 표시 (스누즈만 예외) ----
        if (perm !== 'granted') {
            if (perm === 'denied') {
                await rbMarkUnsubscribed(reg, 'denied');
            }
            if (!isMobileWeb()) ensurePushHelpPanel(perm);
            return;
        }

        // ----- 허용(granted) → 서버 키 확인 후 구독 처리 -----
        let k = null;
        try {
            k = await fetch('/rb/rb.mod/pwa/api/vapid_pubkey.php', {
                cache: 'no-store'
            }).then(r => r.json());
        } catch (_) {}
        if (!k || !k.publicKey) return;

        const keyNow = k.publicKey;
        const keyOld = (function () {
            try {
                return LS.getItem('rb_vapid_pub') || '';
            } catch (_) {
                return '';
            }
        })();

        async function toUint8(b64url) {
            const p = '='.repeat((4 - b64url.length % 4) % 4);
            const s = (b64url + p).replace(/-/g, '+').replace(/_/g, '/');
            const r = atob(s);
            const o = new Uint8Array(r.length);
            for (let i = 0; i < r.length; i++) o[i] = r.charCodeAt(i);
            return o;
        }

        try {
            const subOld = await reg.pushManager.getSubscription();
            if (subOld && keyOld && keyOld !== keyNow) {
                try {
                    await subOld.unsubscribe();
                } catch (_) {}
            }

            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                try {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: await toUint8(keyNow)
                    });
                } catch (_) {
                    sub = null;
                }
            }
            // 구독이 없으면 서버 호출 생략
            if (!sub) return;
            // 서버 등록/갱신 (항상 업서트)
            const payload = sub ?
                (typeof sub.toJSON === 'function' ? sub.toJSON() : {
                    endpoint: sub.endpoint || '',
                    keys: sub.keys || {}
                }) : {
                    endpoint: '',
                    keys: {}
                };
            payload.device_uid = DEVICE_UID;

            await fetch('/rb/rb.mod/pwa/api/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            try {
                LS.setItem('rb_vapid_pub', keyNow);
            } catch (_) {}

        } catch (_) {
            // 조용히 실패
        }
    }

    // 설치 이벤트 훅은 위에서 등록했고, iOS는 수동 호출함.
    init();


    // device uid 재사용
    function getUid() {
        try {
            return localStorage.getItem('rb_pwa_uid') || '';
        } catch (_) {
            return '';
        }
    }

    function send(url, body) {
        try {
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(body)], {
                    type: 'application/json'
                });
                navigator.sendBeacon(url, blob);
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body)
                });
            }
        } catch (_) {}
    }

    // 현재 display-mode
    var display = 'browser';
    try {
        if (window.matchMedia && matchMedia('(display-mode: standalone)').matches) display = 'standalone';
        else if (typeof navigator.standalone === 'boolean' && navigator.standalone) display = 'standalone'; // iOS
    } catch (_) {}

    var uid = getUid();
    if (!uid) return;

    // 설치 여부(로컬 플래그)
    var installedMark = '';
    try {
        installedMark = localStorage.getItem('rb_pwa_app_installed') || '';
    } catch (_) {}

    // 설치됨 또는 standalone 실행일 때만 기록 전송
    var isRealAppUse = (display === 'standalone') || (installedMark === '1') || hasInstalledCookie();
    if (isRealAppUse) {
        send('/rb/rb.mod/pwa/api/app_install_event.php', {
            device_uid: uid,
            event: 'open',
            display: display,
            source: ''
        });
    }

    // 2) 크롬/안드에서 앱 설치 완료 이벤트
    window.addEventListener('appinstalled', function () {
        send('/rb/rb.mod/pwa/api/app_install_event.php', {
            device_uid: uid,
            event: 'installed',
            display: 'standalone',
            source: 'beforeinstallprompt'
        });
        try {
            localStorage.setItem('rb_pwa_app_installed', '1');
        } catch (_) {}
        // 설치 직후 패널 즉시 제거
        document.getElementById('rb-pwa-install')?.remove();
        document.getElementById('rb-pwa-push-help')?.remove();
    });

    window.addEventListener('pageshow', () => {
        if (isPopupWindow() || isStandaloneApp() || isInstalledMark() || hasInstalledCookie()) {
            document.getElementById('rb-pwa-install')?.remove();
            document.getElementById('rb-pwa-push-help')?.remove();
        }
    });

    // 3) iOS A2HS 추정: standalone 첫 실행인데 installed 로그 없으면 설치로 처리
    try {
        var mark = localStorage.getItem('rb_pwa_app_installed') || '';
        if (display === 'standalone' && !mark) {
            send('/rb/rb.mod/pwa/api/app_install_event.php', {
                device_uid: uid,
                event: 'installed',
                display: 'standalone',
                source: 'a2hs-ios'
            });
            localStorage.setItem('rb_pwa_app_installed', '1');
            // iOS A2HS 첫 실행 시도 즉시 패널 제거
            document.getElementById('rb-pwa-install')?.remove();
            document.getElementById('rb-pwa-push-help')?.remove();
        }
    } catch (_) {}
})();
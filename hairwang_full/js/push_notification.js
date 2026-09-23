// Native WebView -> Gnuboard FCM token bridge.
(function () {
    'use strict';

    var tokenStorageKey = 'fcm_token';
    var deviceStorageKey = 'seoulbeauty_fcm_device_id';

    function getDeviceId() {
        try {
            var existing = localStorage.getItem(deviceStorageKey);
            if (existing) return existing;

            var generated = (window.crypto && crypto.randomUUID)
                ? crypto.randomUUID()
                : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
            localStorage.setItem(deviceStorageKey, generated);
            return generated;
        } catch (e) {
            return '';
        }
    }

    function detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/iphone|ipad|ipod/i.test(ua)) return 'ios';
        if (/android/i.test(ua)) return 'android';
        return 'web';
    }

    function savePushTokenToGnuboard(token) {
        if (typeof g5_is_member === 'undefined' || !g5_is_member) {
            return;
        }

        var bbsUrl = typeof g5_bbs_url === 'string' ? g5_bbs_url : '/bbs';
        fetch(bbsUrl + '/ajax.push_token.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({
                token: token,
                platform: detectPlatform(),
                device_id: getDeviceId(),
                device_info: {
                    user_agent: navigator.userAgent || '',
                    app_version: 'webview'
                }
            })
        }).catch(function () {
            // Saved token is retried on later page loads.
        });
    }

    function receiveFcmToken(token) {
        if (typeof token !== 'string' || token.length < 20 || token.length > 500) {
            return;
        }

        try {
            localStorage.setItem(tokenStorageKey, token);
        } catch (e) {
            // Continue even when localStorage is unavailable.
        }

        savePushTokenToGnuboard(token);
    }

    window.receiveFCMToken = receiveFcmToken;
    window.onTokenReceived = receiveFcmToken;

    document.addEventListener('DOMContentLoaded', function () {
        try {
            var savedToken = localStorage.getItem(tokenStorageKey)
                || localStorage.getItem('seoulbeauty_fcm_token');
            if (savedToken) receiveFcmToken(savedToken);
        } catch (e) {
            // Ignore storage access failures.
        }

        if (window.Android && typeof window.Android.requestToken === 'function') {
            try {
                window.Android.requestToken();
            } catch (e) {
                // Older app builds may not expose this method on every page.
            }
        }

        if (
            window.webkit &&
            window.webkit.messageHandlers &&
            window.webkit.messageHandlers.invokeAction
        ) {
            try {
                window.webkit.messageHandlers.invokeAction.postMessage('getToken');
            } catch (e) {
                // Older app builds may not expose this handler on every page.
            }
        }
    });
})();

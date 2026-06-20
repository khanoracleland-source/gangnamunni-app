/**
 * 앱 SNS 로그인 JavaScript 라이브러리
 *
 * Android / iOS 앱에서 SNS 자동 로그인을 지원하는 JavaScript 브릿지
 *
 * 작성일: 2026-01-19
 * 작성자: Claude Code
 *
 * 지원 플랫폼:
 * - Android: Chrome Custom Tabs (시스템 Chrome 브라우저 쿠키 공유)
 * - iOS: ASWebAuthenticationSession (시스템 Safari 쿠키 공유)
 * - 웹: 일반 OAuth 팝업 (기존 방식)
 *
 * 지원 SNS:
 * - naver: 네이버
 * - kakao: 카카오
 * - google: 구글
 * - facebook: 페이스북
 * - twitter: 트위터
 * - payco: 페이코
 *
 * 사용 예시:
 * ```javascript
 * <script src="/js/app_sns_login.js"></script>
 * <script>
 *   AppSnsLogin.startLogin('naver')
 *     .then(token => {
 *       console.log('로그인 성공:', token);
 *       // 서버로 토큰 전송하여 세션 생성
 *       return AppSnsLogin.convertTokenToSession(token);
 *     })
 *     .then(() => {
 *       console.log('세션 생성 완료');
 *       location.href = '/';
 *     })
 *     .catch(error => {
 *       console.error('로그인 실패:', error);
 *       alert('로그인에 실패했습니다: ' + error);
 *     });
 * </script>
 * ```
 */

var AppSnsLogin = (function() {
    'use strict';

    // 서버 API 기본 URL
    var apiBaseURL = '/api/sns';

    // 현재 플랫폼 (android / ios / web)
    var currentPlatform = null;

    // Promise resolve/reject 콜백 저장
    var loginPromiseResolve = null;
    var loginPromiseReject = null;

    /**
     * 현재 플랫폼 감지 (Android / iOS / 웹)
     *
     * @returns {string} 'android' / 'ios' / 'web'
     */
    function detectPlatform() {
        // 이미 감지된 경우 캐시된 값 반환
        if (currentPlatform) {
            return currentPlatform;
        }

        var userAgent = navigator.userAgent || navigator.vendor || window.opera;

        // Android 앱 감지
        if (typeof Android !== 'undefined' && Android.getPlatform) {
            try {
                var platform = Android.getPlatform();
                if (platform === 'android') {
                    currentPlatform = 'android';
                    console.log('[AppSnsLogin] 플랫폼 감지: Android 앱');
                    return currentPlatform;
                }
            } catch (e) {
                console.warn('[AppSnsLogin] Android 플랫폼 감지 실패:', e);
            }
        }

        // iOS 앱 감지
        if (typeof webkit !== 'undefined' && webkit.messageHandlers && webkit.messageHandlers.invokeAction) {
            try {
                // iOS에서 플랫폼 정보 요청
                webkit.messageHandlers.invokeAction.postMessage('getPlatform');
                // 플랫폼 정보는 onPlatformReceived() 콜백으로 수신됨
                // 일단 iOS로 가정
                currentPlatform = 'ios';
                console.log('[AppSnsLogin] 플랫폼 감지: iOS 앱');
                return currentPlatform;
            } catch (e) {
                console.warn('[AppSnsLogin] iOS 플랫폼 감지 실패:', e);
            }
        }

        // 웹 브라우저
        currentPlatform = 'web';
        console.log('[AppSnsLogin] 플랫폼 감지: 웹 브라우저');
        return currentPlatform;
    }

    /**
     * SNS 로그인 시작
     *
     * 플랫폼별로 다른 방식으로 로그인을 시작합니다.
     * - Android: Android.startSnsLogin() 호출 → Chrome Custom Tabs
     * - iOS: webkit.messageHandlers.invokeAction.postMessage() 호출 → ASWebAuthenticationSession
     * - 웹: 팝업 창 열기 (기존 OAuth 방식)
     *
     * @param {string} provider SNS 제공자 (naver, kakao, google, facebook, twitter, payco)
     * @returns {Promise<string>} JWT 토큰
     */
    function startLogin(provider) {
        return new Promise(function(resolve, reject) {
            console.log('[AppSnsLogin] SNS 로그인 시작 - 제공자: ' + provider);

            // Promise 콜백 저장
            loginPromiseResolve = resolve;
            loginPromiseReject = reject;

            var platform = detectPlatform();

            if (platform === 'android') {
                // Android 앱
                startLoginAndroid(provider);
            } else if (platform === 'ios') {
                // iOS 앱
                startLoginIos(provider);
            } else {
                // 웹 브라우저 (팝업)
                startLoginWeb(provider);
            }
        });
    }

    /**
     * Android 앱에서 SNS 로그인 시작
     *
     * @param {string} provider SNS 제공자
     */
    function startLoginAndroid(provider) {
        console.log('[AppSnsLogin] Android 앱에서 SNS 로그인 시작');

        if (typeof Android === 'undefined' || !Android.startSnsLogin) {
            loginPromiseReject('Android JavaScript Interface가 없습니다');
            return;
        }

        try {
            // Android의 JavaScript Interface 호출
            // Chrome Custom Tabs가 열리고, 딥링크 콜백으로 토큰을 받음
            Android.startSnsLogin(provider);

            // 콜백은 onSnsLoginSuccess() 또는 onSnsLoginError()로 수신됨
        } catch (error) {
            console.error('[AppSnsLogin] Android SNS 로그인 시작 실패:', error);
            loginPromiseReject('Android SNS 로그인 시작 실패: ' + error.message);
        }
    }

    /**
     * iOS 앱에서 SNS 로그인 시작
     *
     * @param {string} provider SNS 제공자
     */
    function startLoginIos(provider) {
        console.log('[AppSnsLogin] iOS 앱에서 SNS 로그인 시작');

        if (typeof webkit === 'undefined' || !webkit.messageHandlers || !webkit.messageHandlers.invokeAction) {
            loginPromiseReject('iOS WKWebView MessageHandler가 없습니다');
            return;
        }

        try {
            // iOS의 WKScriptMessageHandler 호출
            // ASWebAuthenticationSession이 열리고, 딥링크 콜백으로 토큰을 받음
            webkit.messageHandlers.invokeAction.postMessage('startSnsLogin:' + provider);

            // 콜백은 onSnsLoginSuccess() 또는 onSnsLoginError()로 수신됨
        } catch (error) {
            console.error('[AppSnsLogin] iOS SNS 로그인 시작 실패:', error);
            loginPromiseReject('iOS SNS 로그인 시작 실패: ' + error.message);
        }
    }

    /**
     * 웹 브라우저에서 SNS 로그인 시작 (팝업)
     *
     * 기존 OAuth 팝업 방식을 사용합니다.
     * 서버의 /api/sns/app_login.php에서 OAuth URL을 가져와서 팝업으로 엽니다.
     *
     * @param {string} provider SNS 제공자
     */
    function startLoginWeb(provider) {
        console.log('[AppSnsLogin] 웹 브라우저에서 SNS 로그인 시작 (팝업)');

        // CSRF 보안 토큰 생성
        var state = generateRandomString(32);

        // 리디렉션 URL (웹용 콜백 페이지)
        var redirectUri = window.location.origin + '/api/sns/web_callback.html';

        // 서버에서 OAuth URL 가져오기
        var url = apiBaseURL + '/app_login.php' +
            '?provider=' + encodeURIComponent(provider) +
            '&redirect_uri=' + encodeURIComponent(redirectUri) +
            '&state=' + encodeURIComponent(state) +
            '&platform=web';

        console.log('[AppSnsLogin] 서버에서 OAuth URL 요청:', url);

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.error || '알 수 없는 오류');
                }

                console.log('[AppSnsLogin] OAuth URL 받음, 팝업 열기');

                // OAuth URL로 팝업 열기
                var popup = window.open(
                    data.auth_url,
                    'SNS 로그인',
                    'width=600,height=700,scrollbars=yes,resizable=yes'
                );

                // 팝업에서 메시지 수신 대기 (postMessage 사용)
                var messageListener = function(event) {
                    // 보안: origin 확인
                    if (event.origin !== window.location.origin) {
                        return;
                    }

                    if (event.data && event.data.type === 'sns_login_callback') {
                        window.removeEventListener('message', messageListener);

                        if (event.data.token) {
                            console.log('[AppSnsLogin] 팝업에서 토큰 수신');
                            loginPromiseResolve(event.data.token);
                        } else if (event.data.error) {
                            console.error('[AppSnsLogin] 팝업에서 오류 수신:', event.data.error);
                            loginPromiseReject(event.data.error);
                        }

                        if (popup && !popup.closed) {
                            popup.close();
                        }
                    }
                };

                window.addEventListener('message', messageListener);

                // 팝업이 닫히면 Promise reject (취소로 간주)
                var checkClosed = setInterval(function() {
                    if (popup.closed) {
                        clearInterval(checkClosed);
                        window.removeEventListener('message', messageListener);

                        if (loginPromiseReject) {
                            loginPromiseReject('사용자가 로그인을 취소했습니다');
                        }
                    }
                }, 500);
            })
            .catch(function(error) {
                console.error('[AppSnsLogin] OAuth URL 가져오기 실패:', error);
                loginPromiseReject('OAuth URL 가져오기 실패: ' + error.message);
            });
    }

    /**
     * JWT 토큰을 그누보드 세션으로 변환
     *
     * 서버의 /api/sns/token_to_session.php를 호출하여
     * JWT 토큰을 검증하고 그누보드 세션을 생성합니다.
     *
     * @param {string} token JWT 토큰
     * @returns {Promise<void>}
     */
    function convertTokenToSession(token) {
        return new Promise(function(resolve, reject) {
            console.log('[AppSnsLogin] JWT 토큰을 세션으로 변환 중');

            var url = apiBaseURL + '/token_to_session.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: token
                }),
                credentials: 'include' // 쿠키 포함
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.error || '알 수 없는 오류');
                }

                console.log('[AppSnsLogin] 세션 생성 성공 - 회원: ' + data.member.mb_id);
                resolve(data.member);
            })
            .catch(function(error) {
                console.error('[AppSnsLogin] 세션 변환 실패:', error);
                reject('세션 변환 실패: ' + error.message);
            });
        });
    }

    /**
     * 랜덤 문자열 생성 (CSRF state용)
     *
     * @param {number} length 문자열 길이
     * @returns {string} 랜덤 문자열
     */
    function generateRandomString(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    // ==================== 앱에서 호출하는 콜백 함수 ====================

    /**
     * SNS 로그인 성공 콜백 (앱에서 호출)
     *
     * Android / iOS 앱에서 SNS 로그인이 성공하면 이 함수를 호출합니다.
     * JWT 토큰을 전달받아 Promise를 resolve합니다.
     *
     * @param {string} token JWT 토큰
     */
    window.onSnsLoginSuccess = function(token) {
        console.log('[AppSnsLogin] SNS 로그인 성공 콜백 - 토큰 수신');

        if (loginPromiseResolve) {
            loginPromiseResolve(token);
            loginPromiseResolve = null;
            loginPromiseReject = null;
        }
    };

    /**
     * SNS 로그인 실패 콜백 (앱에서 호출)
     *
     * Android / iOS 앱에서 SNS 로그인이 실패하면 이 함수를 호출합니다.
     * 오류 메시지를 전달받아 Promise를 reject합니다.
     *
     * @param {string} error 오류 메시지
     */
    window.onSnsLoginError = function(error) {
        console.error('[AppSnsLogin] SNS 로그인 실패 콜백 - 오류: ' + error);

        if (loginPromiseReject) {
            loginPromiseReject(error);
            loginPromiseResolve = null;
            loginPromiseReject = null;
        }
    };

    /**
     * 플랫폼 정보 수신 콜백 (iOS에서 호출)
     *
     * iOS 앱에서 플랫폼 정보를 전달할 때 이 함수를 호출합니다.
     *
     * @param {string} platform 플랫폼 ('ios')
     */
    window.onPlatformReceived = function(platform) {
        console.log('[AppSnsLogin] 플랫폼 정보 수신: ' + platform);
        currentPlatform = platform;
    };

    // ==================== Public API ====================

    return {
        /**
         * 현재 플랫폼 감지
         * @returns {string} 'android' / 'ios' / 'web'
         */
        getPlatform: detectPlatform,

        /**
         * SNS 로그인 시작
         * @param {string} provider SNS 제공자 (naver, kakao, google, facebook, twitter, payco)
         * @returns {Promise<string>} JWT 토큰
         */
        startLogin: startLogin,

        /**
         * JWT 토큰을 그누보드 세션으로 변환
         * @param {string} token JWT 토큰
         * @returns {Promise<Object>} 회원 정보
         */
        convertTokenToSession: convertTokenToSession,

        /**
         * 버전 정보
         */
        version: '1.0.0'
    };
})();

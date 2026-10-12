import 'dart:convert';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart' show defaultTargetPlatform, TargetPlatform;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../configs.dart';
import '../utils/app_common.dart';
import 'deep_link_service.dart';
import 'native_auth_service.dart';
import 'payment_app_launcher.dart';
import 'package:nb_utils/nb_utils.dart' show toast;

/// WebView <-> Flutter 양방향 통신 브릿지 (환자 앱)
///
/// JavaScript에서 Flutter로의 메시지 전달(FlutterBridge 채널)과
/// Flutter에서 WebView로의 JavaScript 실행을 모두 지원합니다.
///
/// ## JavaScript -> Flutter (FlutterBridge 채널)
///
/// 웹 페이지에서 아래와 같이 호출합니다:
/// ```javascript
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'navigate',      // Flutter 화면으로 이동
///   route: 'appointment/123'
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'openWebView',   // 새 WebView에서 URL 열기
///   url: 'https://example.com'
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'goBack'         // 뒤로 가기
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'share',         // 공유
///   text: '공유할 텍스트',
///   url: 'https://example.com'
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'getToken'       // 인증 토큰 요청
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'closeWebView'   // WebView 닫기
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'authExchange',  // 로그인 직후 1회용 코드 -> 네이티브 토큰 교환
///   code: '...'
/// }));
///
/// FlutterBridge.postMessage(JSON.stringify({
///   action: 'logout'         // 웹 로그아웃과 함께 네이티브 토큰도 폐기
/// }));
/// ```
///
/// ## Flutter -> WebView
///
/// ```dart
/// final bridge = WebViewBridge(controller: webViewController);
/// bridge.sendToWebView('eventName', {'key': 'value'});
/// ```
///
/// ## 지원하는 커스텀 URL 스킴
///   - wellness://
///   - kivicare://
class WebViewBridge {
  // -------------------------------------------------------------------
  // 생성자 및 필드
  // -------------------------------------------------------------------

  /// WebView 컨트롤러 (Flutter -> WebView 통신에 필요)
  final WebViewController controller;

  final Future<void> Function()? onHospitalListBack;
  final NativeAuthService _auth;

  /// 토큰 요청 시 커스텀 핸들러 (null이면 기본 loginUserData.apiToken 사용)
  final Future<String> Function()? tokenProvider;

  /// 공유 요청 시 커스텀 핸들러 (null이면 기본 클립보드 복사 사용)
  final void Function(String text, String url)? onShareRequested;

  /// 이 앱이 처리하는 커스텀 URL 스킴 목록
  static const List<String> _supportedSchemes = ['wellness', 'kivicare'];

  /// 로그 태그
  static const String _tag = 'WebViewBridge';

  WebViewBridge({
    required this.controller,
    this.onHospitalListBack,
    this.tokenProvider,
    this.onShareRequested,
    NativeAuthService? authService,
  }) : _auth = authService ?? NativeAuthService.instance;

  // -------------------------------------------------------------------
  // JavaScript 채널 생성
  // -------------------------------------------------------------------

  /// WebViewController에 모든 JavaScript 채널을 직접 등록합니다.
  ///
  /// 사용 예시:
  /// ```dart
  /// final bridge = WebViewBridge(controller: controller);
  /// bridge.registerChannels();
  /// ```
  Future<void> registerChannels() async {
    await controller.addJavaScriptChannel(
      'FlutterBridge',
      onMessageReceived: _handleFlutterBridgeMessage,
    );
  }

  // -------------------------------------------------------------------
  // FlutterBridge 메시지 핸들러
  // -------------------------------------------------------------------

  /// FlutterBridge 채널로 수신된 JSON 메시지를 파싱하고 적절한 액션을 수행
  void _handleFlutterBridgeMessage(JavaScriptMessage message) async {
    Map<String, dynamic> data;
    try {
      data = jsonDecode(message.message) as Map<String, dynamic>;
    } catch (e) {
      _log('Invalid bridge message');
      return;
    }

    final action = data['action'];
    if (action is! String || action.isEmpty) {
      _log('action 필드가 없거나 비어 있음');
      return;
    }

    switch (action) {
      case 'navigate':
        _handleNavigate(data);
        break;

      case 'openWebView':
        _handleOpenWebView(data);
        break;

      case 'hospitalListBack':
        if (await _isFromTrustedOrigin()) {
          await onHospitalListBack?.call();
        }
        break;

      case 'goBack':
        _handleGoBack();
        break;

      case 'share':
        _handleShare(data);
        break;

      case 'getToken':
        _handleGetToken();
        break;

      case 'requestFcmToken':
        requestFcmTokenForWebsite();
        break;

      case 'closeWebView':
        _handleCloseWebView();
        break;

      case 'authExchange':
        await _handleAuthExchange(data);
        break;

      case 'logout':
        await _handleNativeLogout();
        break;

      default:
        _log('알 수 없는 action: $action');
        break;
    }
  }

  /// JavaScriptChannel은 컨트롤러 전역에 노출되므로, 로그인 사이트가 아닌
  /// 다른 곳(카카오 OAuth, 결제 게이트웨이 등)으로 이동한 상태에서 같은
  /// 이름의 채널을 호출해도 토큰 관련 액션은 절대 처리하지 않는다.
  ///
  /// scheme(https만)+host+port를 모두 맞춰본다 — host만 같고 http로 다운그레이드
  /// 됐거나 포트가 다른 경우까지 "신뢰"로 오인하지 않기 위함이다.
  ///
  /// 한계: webview_flutter의 JavaScriptChannel API는 메시지를 보낸 프레임의
  /// origin을 알려주지 않는다 — currentUrl()은 최상위 프레임 기준이라, 신뢰된
  /// 최상위 페이지 안에 포함된 (서드파티 광고 등) iframe이 이 채널을 직접
  /// 호출해도 여기서는 걸러낼 수 없다. 이 앱은 그런 서드파티 iframe을 로드하지
  /// 않지만, 향후 광고/결제 iframe을 넣는다면 네이티브 플랫폼 코드로 프레임별
  /// 검증을 추가해야 한다.
  Future<bool> _isFromTrustedOrigin() async {
    try {
      final current = await controller.currentUrl();
      if (current == null) return false;
      final currentUri = Uri.parse(current);
      final trustedUri = Uri.parse(STARTUP_WEBVIEW_URL);
      return currentUri.scheme.toLowerCase() == 'https' &&
          currentUri.host.toLowerCase() == trustedUri.host.toLowerCase() &&
          currentUri.port == trustedUri.port;
    } catch (e) {
      _log('origin 확인 실패: $e');
      return false;
    }
  }

  /// authExchange 액션: 로그인 직후 페이지가 건네준 1회용 코드를 네이티브
  /// accessToken/refreshToken으로 교환한다 (HTTPS 응답으로만 전달받음 — 코드
  /// 자체는 이 채널로 오지만 실제 토큰 값은 여기를 거치지 않는다).
  ///
  /// JSON 형식: { "action": "authExchange", "code": "<1회용 코드>" }
  Future<void> _handleAuthExchange(Map<String, dynamic> data) async {
    if (!await _isFromTrustedOrigin()) {
      _log('authExchange 거부: 신뢰되지 않은 origin');
      return;
    }
    final code = data['code'];
    if (code is! String || code.isEmpty) {
      _log('authExchange 액션에 code가 없음');
      return;
    }
    final device = data['device_id'];
    if (device != null && device is! String) return;
    final ok = await _auth.exchangeCode(code, deviceId: device as String?);
    _log('authExchange 결과: $ok');
    if (!ok) {
      toast('로그인 유지 설정을 완료하지 못했습니다. 인터넷 연결을 확인한 후 새로고침해주세요.');
    }
  }

  /// A login can finish outside the 60-second page-emission window. Recover
  /// from the authenticated WebView on page completion instead of waiting
  /// for another manual login. The server only returns a short-lived code.
  Future<void> recoverNativeSession() async {
    try {
      if (!await _isFromTrustedOrigin() || await _auth.hasStoredSession) {
        return;
      }
      final current = Uri.tryParse(await controller.currentUrl() ?? '');
      if (current?.queryParameters.containsKey('app_logout') == true) return;
      await controller.runJavaScript('''
        (function() {
          if (window.__nativeAuthBootstrapPending) return;
          window.__nativeAuthBootstrapPending = true;
          fetch('/hairwang/gangnamunni/api/app_auth_bootstrap.php', {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: {'X-Native-Auth': '1'}
          }).then(function(r) { return r.ok ? r.json() : null; })
            .then(function(data) {
              if (data && data.success && data.code && window.FlutterBridge) {
                FlutterBridge.postMessage(JSON.stringify({action: 'authExchange', code: data.code, device_id: data.device_id}));
              }
            }).catch(function() {}).finally(function() { window.__nativeAuthBootstrapPending = false; });
        })();
      ''');
    } catch (_) {
      _log('Native session recovery unavailable; next page can retry');
    }
  }

  /// logout 액션: 웹 페이지에서 로그아웃했을 때 네이티브 토큰도 함께 폐기한다.
  Future<void> _handleNativeLogout() async {
    if (!await _isFromTrustedOrigin()) {
      _log('logout 거부: 신뢰되지 않은 origin');
      return;
    }
    try {
      await _auth.logout();
      _log('네이티브 토큰 로그아웃 완료');
    } catch (_) {
      _log('Native logout could not be saved');
      toast('로그아웃 상태를 저장하지 못했습니다. 다시 시도해주세요.');
    }
  }

  // -------------------------------------------------------------------
  // 개별 액션 핸들러
  // -------------------------------------------------------------------

  /// navigate 액션: Flutter 화면으로 이동
  ///
  /// JSON 형식: { "action": "navigate", "route": "appointment/123" }
  /// route는 딥링크 경로와 동일한 형식 (예: "appointment/123", "dashboard")
  void _handleNavigate(Map<String, dynamic> data) {
    final String? route = data['route'] as String?;
    if (route == null || route.isEmpty) {
      _log('navigate 액션에 route가 없음');
      return;
    }

    _log('Flutter 화면으로 이동: $route');

    // route를 딥링크 URI로 변환하여 DeepLinkService에 위임
    // "appointment/123" -> "wellness://appointment/123"
    final String deepLinkUrl = '${_supportedSchemes.first}://$route';
    DeepLinkService.instance.handleDeepLinkFromString(deepLinkUrl);
  }

  /// openWebView 액션: 새 WebView에서 URL 열기
  ///
  /// JSON 형식: { "action": "openWebView", "url": "https://example.com" }
  void _handleOpenWebView(Map<String, dynamic> data) {
    final String? url = data['url'] as String?;
    if (url == null || url.isEmpty) {
      _log('openWebView 액션에 url이 없음');
      return;
    }

    // URL 유효성 검증: http/https만 허용
    if (!_isValidHttpUrl(url)) {
      _log('유효하지 않은 URL (http/https만 허용): $url');
      return;
    }

    _log('새 WebView에서 URL 열기: $url');

    // 딥링크 서비스의 webview 경로로 위임
    final String encodedUrl = Uri.encodeComponent(url);
    final String deepLinkUrl =
        '${_supportedSchemes.first}://webview?url=$encodedUrl';
    DeepLinkService.instance.handleDeepLinkFromString(deepLinkUrl);
  }

  /// goBack 액션: 뒤로 가기 (GetX 네비게이션)
  void _handleGoBack() {
    _log('뒤로 가기');
    if (Get.currentRoute.isNotEmpty) {
      Get.back();
    }
  }

  /// share 액션: 텍스트/URL 공유
  ///
  /// JSON 형식: { "action": "share", "text": "공유 텍스트", "url": "https://..." }
  void _handleShare(Map<String, dynamic> data) {
    final String text = (data['text'] as String?) ?? '';
    final String url = (data['url'] as String?) ?? '';

    if (text.isEmpty && url.isEmpty) {
      _log('share 액션에 text와 url이 모두 비어 있음');
      return;
    }

    _log('공유 요청: text="$text", url="$url"');

    if (onShareRequested != null) {
      onShareRequested!(text, url);
      return;
    }

    // 기본 구현: 클립보드에 복사하고 스낵바 표시
    final String shareContent =
        [text, url].where((s) => s.isNotEmpty).join('\n');
    Clipboard.setData(ClipboardData(text: shareContent));
    Get.snackbar(
      '복사됨',
      '클립보드에 복사되었습니다.',
      snackPosition: SnackPosition.BOTTOM,
      duration: const Duration(seconds: 2),
    );
  }

  /// getToken 액션: 인증 토큰을 WebView에 전달
  ///
  /// JSON 형식: { "action": "getToken" }
  /// 결과는 JavaScript 콜백 window.onFlutterToken(token)으로 전달됩니다.
  void _handleGetToken() async {
    _log('토큰 요청');

    String token;
    if (tokenProvider != null) {
      token = await tokenProvider!();
    } else {
      token = loginUserData.value.apiToken;
    }

    if (token.isEmpty) {
      _log('토큰이 비어 있음 (로그인되지 않은 상태)');
      // 빈 토큰이라도 웹 페이지에 알려줌
    }

    // JavaScript 콜백으로 토큰 전달
    final String escapedToken = _escapeJsString(token);
    final String js = '''
      if (typeof window.onFlutterToken === 'function') {
        window.onFlutterToken('$escapedToken');
      } else if (typeof window.onFlutterResponse === 'function') {
        window.onFlutterResponse(JSON.stringify({action: 'getToken', token: '$escapedToken'}));
      }
    ''';

    try {
      await controller.runJavaScript(js);
      _log('토큰 전달 완료');
    } catch (e) {
      _log('토큰 전달 JavaScript 실행 실패: $e');
    }
  }

  /// Returns the device FCM token to the website. The website posts it using
  /// its own authenticated Kakao/Gnuboard session, so the backend can safely
  /// associate the token with the current mb_id.
  Future<void> requestFcmTokenForWebsite() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) {
        _log('FCM 토큰이 비어 있음');
        return;
      }

      final escapedToken = _escapeJsString(token);
      await controller.runJavaScript('''
        if (typeof window.onTokenReceived === 'function') {
          window.onTokenReceived('$escapedToken');
        } else if (typeof window.receiveFCMToken === 'function') {
          window.receiveFCMToken('$escapedToken');
        }
      ''');
      _log('FCM 토큰 전달 완료');
    } catch (e) {
      _log('FCM 토큰 전달 실패: $e');
    }
  }

  /// closeWebView 액션: 현재 WebView를 닫고 이전 화면으로 복귀
  void _handleCloseWebView() {
    _log('WebView 닫기');
    Get.back();
  }

  // -------------------------------------------------------------------
  // Flutter -> WebView 통신
  // -------------------------------------------------------------------

  /// Flutter에서 WebView의 JavaScript를 호출합니다.
  ///
  /// 웹 페이지에 window.onFlutterEvent(eventName, dataJson) 함수가
  /// 정의되어 있어야 합니다.
  ///
  /// [eventName] 이벤트 이름 (예: 'userLoggedIn', 'themeChanged')
  /// [data] JavaScript로 전달할 데이터 (JSON으로 직렬화됨)
  Future<void> sendToWebView(
    String eventName, [
    Map<String, dynamic>? data,
  ]) async {
    final String dataJson = data != null ? jsonEncode(data) : '{}';
    final String escapedEvent = _escapeJsString(eventName);
    final String js = '''
      if (typeof window.onFlutterEvent === 'function') {
        window.onFlutterEvent('$escapedEvent', $dataJson);
      }
    ''';

    try {
      await controller.runJavaScript(js);
      _log('WebView에 이벤트 전송: $eventName');
    } catch (e) {
      _log('WebView 이벤트 전송 실패: $e');
    }
  }

  /// Flutter에서 WebView에 임의의 JavaScript를 실행합니다.
  ///
  /// [js] 실행할 JavaScript 코드
  Future<void> evaluateJavaScript(String js) async {
    try {
      await controller.runJavaScript(js);
    } catch (e) {
      _log('JavaScript 실행 실패: $e');
    }
  }

  // -------------------------------------------------------------------
  // NavigationDelegate 생성
  // -------------------------------------------------------------------

  /// 커스텀 URL 스킴을 가로채는 NavigationDelegate를 반환합니다.
  ///
  /// wellness:// 또는 kivicare:// 스킴의 URL을 DeepLinkService로 위임하고,
  /// http/https는 정상적으로 WebView에서 로드합니다.
  ///
  /// [onPageStarted] 페이지 로딩 시작 콜백
  /// [onPageFinished] 페이지 로딩 완료 콜백
  /// [onWebResourceError] 리소스 에러 콜백
  /// [additionalSchemes] 추가로 차단하고 싶은 스킴 목록
  NavigationDelegate createNavigationDelegate({
    void Function(String url)? onPageStarted,
    void Function(String url)? onPageFinished,
    void Function(WebResourceError error)? onWebResourceError,
    List<String> additionalSchemes = const [],
  }) {
    return NavigationDelegate(
      onPageStarted: onPageStarted,
      onPageFinished: onPageFinished,
      onWebResourceError: (WebResourceError error) {
        _launchPaymentAppFromSubframeError(error);
        onWebResourceError?.call(error);
      },
      onNavigationRequest: (NavigationRequest request) async {
        // 카드사 앱 URL 은 형식이 깨져 있을 수 있어 Uri.parse 가 FormatException 을 던질 수 있다.
        final Uri? parsedUri = Uri.tryParse(request.url);
        if (parsedUri == null) {
          // 파싱 불가 URL: 결제 앱 링크(문자열 기반 판별)만 허용하고, 그 외는 차단한다.
          final PaymentAppLink? rawLink = PaymentAppLauncher.parse(request.url);
          if (rawLink != null) {
            _log('결제 앱 호출(비표준 URL): ${rawLink.appUrl}');
            await _openPaymentApp(rawLink);
          } else {
            _log('잘못된 URL 차단: ${request.url}');
          }
          return NavigationDecision.prevent;
        }
        final Uri uri = parsedUri;
        final String scheme = uri.scheme.toLowerCase();

        // 커스텀 스킴 감지: 딥링크로 처리
        if (_supportedSchemes.contains(scheme) ||
            additionalSchemes.contains(scheme)) {
          _log('커스텀 스킴 URL 가로챔: ${request.url}');
          DeepLinkService.instance.handleDeepLinkFromString(request.url);
          return NavigationDecision.prevent;
        }

        // 결제(토스페이먼츠) 중 카드사·간편결제·은행 앱 호출은 외부 앱으로 연다.
        // 허용 목록에 있는 결제 앱만 해당하며, 그 외 스킴은 아래에서 계속 차단한다.
        // 카카오 로그인 intent(kakaokompassauth/kakaolink)는 기존 처리를 그대로 쓴다.
        final bool kakaoLoginIntent = scheme == 'intent' &&
            (request.url.contains('scheme=kakaokompassauth') ||
                request.url.contains('scheme=kakaolink'));
        if (!kakaoLoginIntent) {
          final PaymentAppLink? paymentLink = PaymentAppLauncher.parse(request.url);
          if (paymentLink != null) {
            _log('결제 앱 호출: ${paymentLink.appUrl}');
            await _openPaymentApp(paymentLink);
            return NavigationDecision.prevent;
          }
        }

        // intent:// 스킴 차단 (Android에서 앱 인텐트)
        if (scheme == 'intent') {
          if (request.url.contains('package=com.kakao.talk')) {
            try {
              await launchUrl(uri, mode: LaunchMode.externalApplication);
            } catch (e) {
              _log('KakaoTalk intent launch failed: $e');
            }
          }
          _log('intent:// 스킴 차단: ${request.url}');
          return NavigationDecision.prevent;
        }

        // javascript: 스킴 차단 (XSS 방지)
        if (scheme == 'javascript') {
          _log('javascript: 스킴 차단');
          return NavigationDecision.prevent;
        }

        // http/https는 정상 로드
        if (scheme == 'http' || scheme == 'https') {
          return NavigationDecision.navigate;
        }

        // Kakao OAuth uses these app-specific schemes to continue in the
        // installed KakaoTalk app. Do not open arbitrary schemes from web content.
        const kakaoSchemes = {'kakaotalk', 'kakaokompassauth', 'kakaolink'};
        if (kakaoSchemes.contains(scheme)) {
          try {
            await launchUrl(uri, mode: LaunchMode.externalApplication);
          } catch (e) {
            _log('KakaoTalk custom scheme launch failed: $e');
          }
          return NavigationDecision.prevent;
        }

        // 그 외 알 수 없는 스킴은 차단
        _log('알 수 없는 스킴 차단: $scheme (${request.url})');
        // 차단된 앱 스킴이 사용자에게 무반응으로 보이지 않도록 알린다. 일반 링크(tel/mailto/sms 등)와
        // 내부 스킴은 제외하고, 문구는 결제에 한정하지 않는다.
        const silentSchemes = {'about', 'data', 'blob', 'file', 'tel', 'mailto', 'sms', 'smsto'};
        if (scheme.isNotEmpty && !silentSchemes.contains(scheme)) {
          toast('앱을 열 수 없습니다.');
        }
        return NavigationDecision.prevent;
      },
    );
  }

  /// 결제 앱을 열고, 열지 못하면(iOS 에서 앱 미설치 등) 사용자에게 알린다.
  /// Android 는 [PaymentAppLauncher.launch] 가 Play 스토어로 보내므로 스토어도 못 연 경우에만 알린다.
  Future<void> _openPaymentApp(PaymentAppLink link) async {
    final bool opened = await PaymentAppLauncher.launch(link);
    if (!opened) {
      _log('결제 앱 실행 실패: ${link.appUrl}');
      toast(defaultTargetPlatform == TargetPlatform.iOS
          ? '결제 앱이 설치되어 있지 않습니다. App Store에서 앱을 설치한 뒤 다시 시도해주세요.'
          : '결제 앱을 열 수 없습니다. 앱 설치 후 다시 시도해주세요.');
    }
  }

  /// iframe 안에서 시작된 결제 앱 호출 처리.
  ///
  /// webview_flutter_android 3.16.1 이후(현재 4.x)는 iframe(서브 프레임) 이동을
  /// onNavigationRequest 로 넘기지 않는다(android_webview_controller `_handleNavigation`
  /// 의 `if (!isForMainFrame ...) return;`). 카드사 안심클릭 iframe 이 `intent://` 등으로
  /// 앱을 부르면 WebView 가 그 URL 을 직접 열려다 ERR_UNKNOWN_URL_SCHEME 로 실패하고,
  /// 우리는 onWebResourceError(url 포함)로만 알게 된다. 토스 웹뷰 가이드가
  /// "webview_flutter_android 3.16.0 이하"를 요구하는 이유가 이것이며, 버전을 내리는 대신
  /// 여기서 같은 결제 앱 허용 목록으로 외부 앱을 연다.
  String? _lastSubframeAppUrl;
  DateTime? _lastSubframeAppAt;

  void _launchPaymentAppFromSubframeError(WebResourceError error) {
    // 메인 프레임 이동은 onNavigationRequest 가 이미 처리했다.
    if (error.isForMainFrame == true) return;
    final String? url = error.url;
    if (url == null || url.isEmpty) return;
    final PaymentAppLink? link = PaymentAppLauncher.parse(url);
    if (link == null) return;
    // 같은 오류가 연달아 보고되는 경우 앱을 두 번 열지 않는다.
    final DateTime now = DateTime.now();
    if (_lastSubframeAppUrl == url &&
        _lastSubframeAppAt != null &&
        now.difference(_lastSubframeAppAt!) < const Duration(seconds: 3)) {
      return;
    }
    _lastSubframeAppUrl = url;
    _lastSubframeAppAt = now;
    _log('iframe 결제 앱 호출: ${link.appUrl}');
    _openPaymentApp(link);
  }

  // -------------------------------------------------------------------
  // 유틸리티 메서드
  // -------------------------------------------------------------------

  /// JavaScript 문자열에 안전하게 삽입하기 위해 이스케이프 처리
  String _escapeJsString(String value) {
    return value
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'")
        .replaceAll('"', '\\"')
        .replaceAll('\n', '\\n')
        .replaceAll('\r', '\\r')
        .replaceAll('\t', '\\t');
  }

  /// URL이 http 또는 https 스킴인지 검증
  bool _isValidHttpUrl(String url) {
    try {
      final Uri parsed = Uri.parse(url);
      return parsed.scheme == 'http' || parsed.scheme == 'https';
    } catch (_) {
      return false;
    }
  }

  /// 디버그 로그 출력
  void _log(String message) {
    debugPrint('[$_tag] $message');
  }
}

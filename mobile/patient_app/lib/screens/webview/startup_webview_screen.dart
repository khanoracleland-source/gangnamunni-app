import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/configs.dart';
import 'package:kivicare_patient/services/webview_bridge.dart';
import 'package:kivicare_patient/services/webview_cookie_persistence.dart';

/// 앱 시작 시 보여지는 WebView 화면
/// configs.dart의 SHOW_WEBVIEW_ON_STARTUP = true 일 때 실행됨
class StartupWebViewScreen extends StatefulWidget {
  final String url;
  final String type; // 'general' or 'sns'

  /// This install's stable device id (see NativeAuthService). Injected as the
  /// sb_app_device cookie so the server's device binding (native tokens AND
  /// the legacy ck_auto remember-cookie) always agrees with what the app
  /// itself considers "this device" — regardless of what the WebView's own
  /// cookie jar did or did not manage to persist.
  final String? deviceId;

  /// A one-shot WebView session-bridge token (see app_auth_verify/refresh.php)
  /// from a native accessToken/refreshToken that was already verified valid.
  /// When present, it is injected as sb_sso_token before the first page load
  /// so the site opens already logged in — no login page, no cookie roulette.
  final String? sessionBridgeToken;

  const StartupWebViewScreen({
    Key? key,
    required this.url,
    this.type = 'general',
    this.deviceId,
    this.sessionBridgeToken,
  }) : super(key: key);

  @override
  State<StartupWebViewScreen> createState() => _StartupWebViewScreenState();
}

class _StartupWebViewScreenState extends State<StartupWebViewScreen>
    with WidgetsBindingObserver {
  late final WebViewController _controller;
  final WebViewCookieManager _cookieManager = WebViewCookieManager();
  bool _isLoading = true;
  bool _isRefreshing = false;
  String? _currentUrl;
  DateTime? _lastExitBackPressedAt;

  static const Duration _exitConfirmationWindow = Duration(seconds: 2);

  // 페이지 상단에서 아래로 당겨서 새로고침하는 JS (Android/iOS 호환)
  static const String _pullToRefreshJs = '''
(function() {
  if (window._pullToRefreshInstalled) return;
  window._pullToRefreshInstalled = true;
  var startY = 0;
  var pulling = false;
  function getScrollTop() {
    return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
  }
  document.addEventListener('touchstart', function(e) {
    if (getScrollTop() <= 0) {
      startY = e.touches[0].pageY;
      pulling = true;
    }
  }, {passive: true});
  document.addEventListener('touchmove', function(e) {
    if (pulling && getScrollTop() <= 0) {
      var dy = e.touches[0].pageY - startY;
      if (dy > 100) {
        pulling = false;
        PullToRefresh.postMessage('refresh');
      }
    } else {
      pulling = false;
    }
  }, {passive: true});
  document.addEventListener('touchend', function() {
    pulling = false;
  }, {passive: true});
})();
''';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // 상태바(배터리, 시간 등) 표시
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ));
    _initializeWebView();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.detached) {
      WebViewCookiePersistence.flush();
    }
  }

  Future<void> _initializeWebView() async {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted);

    // Tell the website that this request comes from the patient app. The
    // marker is deliberately not an auth token; it only lets the login page
    // enable its rotating 120-day remember-login token by default.
    final startupUri = Uri.parse(widget.url);
    await _cookieManager.setCookie(
      WebViewCookie(
        name: 'sb_patient_app',
        value: '1',
        domain: startupUri.host,
        path: '/',
      ),
    );

    // Pin the server's device binding to the same id NativeAuthService uses,
    // instead of letting the server mint one and the WebView cookie jar be
    // the only place it's remembered.
    if (widget.deviceId != null && widget.deviceId!.isNotEmpty) {
      await _cookieManager.setCookie(
        WebViewCookie(
          name: 'sb_app_device',
          value: widget.deviceId!,
          domain: startupUri.host,
          path: '/',
        ),
      );
    }

    // A verified accessToken (or a fresh one from refreshToken) hands the
    // WebView an already-authenticated session — sso_auto_login.php promotes
    // this cookie to a real login on the very first page load.
    if (widget.sessionBridgeToken != null && widget.sessionBridgeToken!.isNotEmpty) {
      await _cookieManager.setCookie(
        WebViewCookie(
          name: 'sb_sso_token',
          value: widget.sessionBridgeToken!,
          domain: startupUri.host,
          path: '/',
        ),
      );
    }

    // WebViewBridge 생성 및 채널 등록 (FlutterBridge + PullToRefresh)
    final bridge = WebViewBridge(
      controller: _controller,
      onRefreshRequested: _handlePullToRefresh,
    );
    bridge.registerChannels();

    // NavigationDelegate (URL scheme 인터셉트 포함)
    _controller
      ..setNavigationDelegate(
        bridge.createNavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
              _currentUrl = url;
            });
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
              _isRefreshing = false;
              _currentUrl = url;
            });
            // pull-to-refresh JS 주입
            _controller.runJavaScript(_pullToRefreshJs);
            // Register the current FCM token using the website's authenticated
            // Kakao/Gnuboard session. The page also requests it through the
            // bridge, but this direct call makes registration resilient to
            // cached HTML and delayed JavaScript execution.
            bridge.requestFcmTokenForWebsite();
            // Login and remember-token cookies are commonly received during a
            // redirect. Persist them immediately so Android "Close all" cannot
            // discard the authenticated WebView state with the process RAM.
            WebViewCookiePersistence.flush();
            // 하단 네비게이션 바 숨김 CSS 주입
            if (HIDE_WEBVIEW_BOTTOM_NAV) {
              _controller.runJavaScript('''
                (function() {
                  var style = document.createElement('style');
                  style.innerHTML = '.bottom-nav, .mobile-nav, .mobile-bottom-nav, nav.fixed-bottom, .nav-bottom, footer.fixed-bottom, [class*="bottom-nav"], [class*="mobile-nav"], #bottom-nav, #mobile-nav { display: none !important; } body { padding-bottom: 0 !important; }';
                  document.head.appendChild(style);
                })();
              ''');
            }
          },
          onWebResourceError: (WebResourceError error) {
            setState(() {
              _isRefreshing = false;
            });
            toast('페이지 로딩 오류: ${error.description}');
          },
        ),
      )
      ..loadRequest(startupUri);
  }

  Future<void> _handlePullToRefresh() async {
    if (_isRefreshing) return;
    setState(() {
      _isRefreshing = true;
    });
    await _controller.reload();
  }

  String _normalizedPath(Uri uri) {
    var path = uri.path.toLowerCase();
    if (path.endsWith('/index.php')) {
      path = path.substring(0, path.length - '/index.php'.length);
    }
    return path.replaceFirst(RegExp(r'/+$'), '');
  }

  bool _isRootPage(String? url) {
    if (url == null || url.isEmpty) return false;

    final uri = Uri.tryParse(url);
    final startupUri = Uri.tryParse(widget.url);
    if (uri == null || startupUri == null) return false;

    return uri.host.toLowerCase() == startupUri.host.toLowerCase() &&
        _normalizedPath(uri) == _normalizedPath(startupUri);
  }

  Future<void> _handleBackPressed() async {
    final currentUrl = await _controller.currentUrl() ?? _currentUrl;
    final canGoBack = await _controller.canGoBack();

    // index.php is the app's root page. WebView history can still contain
    // redirects/login pages, so it must not navigate further back from here.
    if (!_isRootPage(currentUrl) && canGoBack) {
      _lastExitBackPressedAt = null;
      await _controller.goBack();
      return;
    }

    final now = DateTime.now();
    final shouldExit = _lastExitBackPressedAt != null &&
        now.difference(_lastExitBackPressedAt!) <= _exitConfirmationWindow;

    if (shouldExit) {
      await SystemNavigator.pop();
      return;
    }

    _lastExitBackPressedAt = now;
    toast('종료하려면 뒤로가기 버튼을 한 번 더 누르세요.');
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    WebViewCookiePersistence.flush();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) {
          _handleBackPressed();
        }
      },
      child: Scaffold(
        body: SafeArea(
          child: Stack(
            children: [
              WebViewWidget(controller: _controller),
              // 플로팅 새로고침 버튼 (상단 중앙) - 설정에 따라 표시
              if (!HIDE_WEBVIEW_REFRESH_BUTTON)
                Positioned(
                  top: 8,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: GestureDetector(
                      onTap: () => _controller.reload(),
                      child: Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.9),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black26,
                              blurRadius: 6,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Icon(
                          Icons.refresh,
                          color: context.primaryColor,
                          size: 22,
                        ),
                      ),
                    ),
                  ),
                ),
              // Pull-to-refresh 인디케이터
              if (_isRefreshing)
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  child: LinearProgressIndicator(
                    backgroundColor: Colors.transparent,
                    color: context.primaryColor,
                  ),
                ),
              if (_isLoading && !_isRefreshing)
                Center(
                  child: CircularProgressIndicator(),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

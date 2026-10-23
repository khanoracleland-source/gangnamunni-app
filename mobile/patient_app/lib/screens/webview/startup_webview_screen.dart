import 'dart:async';

import 'package:flutter/material.dart';
import '../../services/native_auth_service.dart';
import '../../services/hospital_list_navigation.dart';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/configs.dart';
import 'package:kivicare_patient/services/webview_bridge.dart';
import 'package:kivicare_patient/services/next_pilot.dart';
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
  final VoidCallback? onAuthRetry;
  final NativeAuthService? authService;

  const StartupWebViewScreen({
    super.key,
    required this.url,
    this.type = 'general',
    this.deviceId,
    this.sessionBridgeToken,
    this.onAuthRetry,
    this.authService,
  });

  @override
  State<StartupWebViewScreen> createState() => _StartupWebViewScreenState();
}

class _StartupWebViewScreenState extends State<StartupWebViewScreen>
    with WidgetsBindingObserver {
  late final WebViewController _controller;
  final WebViewCookieManager _cookieManager = WebViewCookieManager();
  final _hospitalNavigation =
      HospitalListNavigation(Uri.parse(STARTUP_WEBVIEW_URL));
  bool _returningToList = false;
  bool _isLoading = true;
  bool _hasRenderedPage = false;
  bool _showLoadingIndicator = false;
  Timer? _loadingIndicatorTimer;
  bool _loadFailed = false;
  String? _currentUrl;
  DateTime? _lastExitBackPressedAt;
  WebViewBridge? _bridge;
  DateTime? _lastTurboHousekeepingAt;

  /// Session recovery and cookie flush are cheap but not free; a Turbo visit
  /// can happen many times per minute, so they run at most this often.
  static const Duration _turboHousekeepingInterval = Duration(seconds: 30);

  static const Duration _exitConfirmationWindow = Duration(seconds: 2);

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
    _scheduleLoadingIndicator();
  }

  // Fast HTTP-cache hits should finish without flashing a spinner. Once a
  // page has rendered, navigation uses a small, non-blocking progress bar.
  void _scheduleLoadingIndicator() {
    _loadingIndicatorTimer?.cancel();
    _loadingIndicatorTimer = Timer(const Duration(milliseconds: 250), () {
      if (!mounted || !_isLoading || _loadFailed) return;
      setState(() => _showLoadingIndicator = true);
    });
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
    _controller = WebViewController();
    try {
      await _configureWebView();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadFailed = true;
        _isLoading = false;
      });
    }
  }

  Future<void> _configureWebView() async {
    await _controller.setJavaScriptMode(JavaScriptMode.unrestricted);

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

    // This app version understands the website's in-page (Turbo) navigation: the
    // site reports each one with a {action: 'pageChanged'} bridge message, which
    // _handleTurboPageChanged handles. Older app versions never send this cookie,
    // so the website keeps normal full-page loads for them. Not a credential.
    await _cookieManager.setCookie(
      WebViewCookie(
        name: 'sb_turbo_bridge',
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
    if (widget.sessionBridgeToken != null &&
        widget.sessionBridgeToken!.isNotEmpty) {
      await _cookieManager.setCookie(
        WebViewCookie(
          name: 'sb_sso_token',
          value: widget.sessionBridgeToken!,
          domain: startupUri.host,
          path: '/',
        ),
      );
    }

    // WebViewBridge 생성 및 채널 등록 (FlutterBridge)
    final bridge = WebViewBridge(
      controller: _controller,
      authService: widget.authService,
      onHospitalListBack: _returnToHospitalList,
      onPageChanged: _handleTurboPageChanged,
    );
    _bridge = bridge;
    await bridge.registerChannels();

    // NavigationDelegate (URL scheme 인터셉트 포함)
    await _controller.setNavigationDelegate(
      bridge.createNavigationDelegate(
        rewriteHttp: NextPilot.rewrite,
        onPageStarted: (String url) {
          if (!mounted) return;
          _hospitalNavigation.pageStarted(url);
          setState(() {
            _isLoading = true;
            _loadFailed = false;
            _showLoadingIndicator = false;
            _currentUrl = url;
          });
          _scheduleLoadingIndicator();
        },
        onPageFinished: (String url) {
          if (!mounted || _loadFailed) return;
          _loadingIndicatorTimer?.cancel();
          setState(() {
            _isLoading = false;
            _hasRenderedPage = true;
            _showLoadingIndicator = false;
            _currentUrl = url;
          });
          if (_hospitalNavigation.returnUrl != null) {
            _installHospitalHeaderBack();
          }
          // Register the current FCM token using the website's authenticated
          // Kakao/Gnuboard session. The page also requests it through the
          // bridge, but this direct call makes registration resilient to
          // cached HTML and delayed JavaScript execution.
          bridge.requestFcmTokenForWebsite();
          bridge.recoverNativeSession();
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
          if (!mounted || error.isForMainFrame == false) return;
          _loadingIndicatorTimer?.cancel();
          setState(() {
            _loadFailed = true;
            _isLoading = false;
            _showLoadingIndicator = false;
          });
        },
      ),
    );
    if (!mounted) return;
    await _controller.loadRequest(startupUri);
  }

  Future<void> _installHospitalHeaderBack() async {
    try {
      await _controller.runJavaScript(HospitalListNavigation.headerBackScript);
    } catch (_) {
      // A page may navigate away before injection; its next load retries.
    }
  }

  /// The website finished a Turbo navigation (no real page load happened).
  ///
  /// Only the per-navigation work from onPageStarted/onPageFinished is repeated:
  /// URL bookkeeping and the hospital-detail back button (its DOM node was just
  /// replaced). FCM token registration is deliberately NOT repeated: the website
  /// registers it on full loads, and Turbo-enabled pages never log a user in.
  /// Session recovery and cookie flush are throttled.
  Future<void> _handleTurboPageChanged() async {
    if (!mounted || _loadFailed) return;
    final url = await _controller.currentUrl();
    if (!mounted || url == null) return;

    _hospitalNavigation.pageStarted(url);
    setState(() => _currentUrl = url);
    if (_hospitalNavigation.returnUrl != null) {
      _installHospitalHeaderBack();
    }

    final now = DateTime.now();
    final last = _lastTurboHousekeepingAt;
    if (last == null || now.difference(last) >= _turboHousekeepingInterval) {
      _lastTurboHousekeepingAt = now;
      _bridge?.recoverNativeSession();
      WebViewCookiePersistence.flush();
    }
  }

  Future<void> _returnToHospitalList() async {
    if (_returningToList) return;
    _returningToList = true;
    try {
      final current = Uri.tryParse(await _controller.currentUrl() ?? '');
      final target = _hospitalNavigation.returnUrl;
      if (!mounted ||
          current == null ||
          target == null ||
          !_hospitalNavigation.isHospitalDetail(current)) {
        return;
      }
      // The previous history entry can be booking or another detail tab.
      // loadRequest from Dart skips onNavigationRequest on Android, so apply
      // the Next.js pilot rewrite here as well.
      await _controller.loadRequest(NextPilot.rewrite(target) ?? target);
    } catch (_) {
      if (mounted) toast('목록으로 돌아가지 못했습니다. 다시 시도해주세요.');
    } finally {
      _returningToList = false;
    }
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
    _loadingIndicatorTimer?.cancel();
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
            fit: StackFit.expand,
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
                          color: Colors.white.withValues(alpha: 0.9),
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
              if (_loadFailed)
                Positioned.fill(
                    child: ColoredBox(
                  color: Theme.of(context).scaffoldBackgroundColor,
                  child: Center(
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Text('페이지를 불러오지 못했습니다. 다시 시도해주세요.'),
                    ElevatedButton(
                        onPressed: widget.onAuthRetry ??
                            () {
                              setState(() {
                                _loadFailed = false;
                                _isLoading = true;
                                _showLoadingIndicator = false;
                              });
                              _scheduleLoadingIndicator();
                              _controller.reload();
                            },
                        child: const Text('다시 시도')),
                  ])),
                )),
              if (_isLoading && _showLoadingIndicator && !_hasRenderedPage)
                const IgnorePointer(
                  child: Center(child: CircularProgressIndicator()),
                ),
              if (_isLoading && _showLoadingIndicator && _hasRenderedPage)
                const Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  child: IgnorePointer(
                    child: LinearProgressIndicator(minHeight: 2),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

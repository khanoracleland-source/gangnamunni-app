import 'dart:convert';
import 'package:http/testing.dart';
import 'package:http/http.dart' as http;
import 'package:kivicare_patient/services/next_pilot.dart';
import 'package:kivicare_patient/services/native_auth_service.dart';
import 'package:kivicare_patient/services/hospital_list_navigation.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/screens/webview/app_auth_gate_screen.dart';
import 'package:kivicare_patient/screens/webview/startup_webview_screen.dart';
// ignore: depend_on_referenced_packages
import 'package:webview_flutter_platform_interface/webview_flutter_platform_interface.dart';

// The old PHP home on the app's host: it must open as the Next.js home.
const url = 'https://seoulbeauty.app/hairwang/gangnamunni/';

class TestPlatform extends WebViewPlatform {
  final events = <String>[];
  final cookies = <WebViewCookie>[];
  final channels = <String, JavaScriptChannelParams>{};
  bool failClear = false, failSet = false;
  bool hasBackHistory = false;
  String currentPage = url;
  PageEventCallback? pageStarted;
  PageEventCallback? pageFinished;
  final scripts = <String>[];
  final loadedUrls = <Uri>[];

  Completer<void>? channelReady;
  @override
  PlatformWebViewCookieManager createPlatformCookieManager(
          PlatformWebViewCookieManagerCreationParams params) =>
      TestCookies(params, this);
  @override
  PlatformWebViewController createPlatformWebViewController(
          PlatformWebViewControllerCreationParams params) =>
      TestController(params, this);
  @override
  PlatformWebViewWidget createPlatformWebViewWidget(
          PlatformWebViewWidgetCreationParams params) =>
      TestWidget(params);
  @override
  PlatformNavigationDelegate createPlatformNavigationDelegate(
          PlatformNavigationDelegateCreationParams params) =>
      TestDelegate(params, this);
}

class TestCookies extends PlatformWebViewCookieManager {
  TestCookies(super.params, this.owner) : super.implementation();
  final TestPlatform owner;
  @override
  Future<bool> clearCookies() async {
    owner.events.add('clear');
    if (owner.failClear) throw PlatformException(code: 'cookie_clear_failed');
    owner.cookies.clear();
    return true;
  }

  @override
  Future<void> setCookie(WebViewCookie cookie) async {
    if (owner.failSet) throw PlatformException(code: 'cookie_set_failed');
    owner.cookies.add(cookie);
    owner.events.add('cookie:${cookie.name}');
  }
}

class TestController extends PlatformWebViewController {
  TestController(super.params, this.owner) : super.implementation();
  final TestPlatform owner;
  @override
  Future<void> setJavaScriptMode(JavaScriptMode mode) async {}
  @override
  Future<void> addJavaScriptChannel(JavaScriptChannelParams params) async {
    await owner.channelReady?.future;
    owner.events.add('channel:${params.name}');
    owner.channels[params.name] = params;
  }

  @override
  Future<void> setPlatformNavigationDelegate(
      PlatformNavigationDelegate handler) async {
    owner.events.add('delegate');
  }

  @override
  Future<void> loadRequest(LoadRequestParams params) async {
    owner.events.add('load');
    owner.loadedUrls.add(params.uri);
  }

  @override
  Future<void> runJavaScript(String javascript) async {
    owner.scripts.add(javascript);
  }

  @override
  Future<String?> currentUrl() async => owner.currentPage;
  @override
  Future<bool> canGoBack() async => owner.hasBackHistory;
  @override
  Future<void> goBack() async {
    owner.events.add('goBack');
  }
}

class TestDelegate extends PlatformNavigationDelegate {
  TestDelegate(super.params, this.owner) : super.implementation();
  final TestPlatform owner;
  @override
  Future<void> setOnNavigationRequest(
      NavigationRequestCallback callback) async {}
  @override
  Future<void> setOnPageStarted(PageEventCallback callback) async {
    owner.pageStarted = callback;
  }

  @override
  Future<void> setOnPageFinished(PageEventCallback callback) async {
    owner.pageFinished = callback;
  }

  @override
  Future<void> setOnWebResourceError(WebResourceErrorCallback callback) async {}
}

class TestWidget extends PlatformWebViewWidget {
  TestWidget(super.params) : super.implementation();
  @override
  Widget build(BuildContext context) => const SizedBox();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storageChannel =
      MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  late TestPlatform platform;
  late Map<String, String> disk;
  Future<void> pumpReady(WidgetTester tester, Widget widget) async {
    await tester.pumpWidget(MaterialApp(home: widget));
    for (var i = 0; i < 8; i++) {
      await tester.pump(const Duration(milliseconds: 10));
    }
  }

  setUp(() {
    platform = TestPlatform();
    WebViewPlatform.instance = platform;
    disk = {'native_device_id': 'a' * 64};
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storageChannel, (call) async {
      final key = call.arguments['key'] as String;
      if (call.method == 'read') return disk[key];
      if (call.method == 'write') disk[key] = call.arguments['value'] as String;
      if (call.method == 'delete') disk.remove(key);
      return null;
    });
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storageChannel, null);
  });
  const listUrl =
      'https://seoulbeauty.app/hairwang/gangnamunni/hospitals.php?treatment=%ED%8B%B0%ED%83%80%EB%8A%84&sort=price&region=강남';
  const detailUrl =
      'https://seoulbeauty.app/hairwang/gangnamunni/detail.php?id=8216&treatment_id=2&treatment=%ED%8B%B0%ED%83%80%EB%8A%84';
  // NextPilot is on by default, so the list the header arrow returns to is
  // loaded as its /next/hospitals equivalent (query/filters preserved).
  final nextListUrl = NextPilot.rewrite(Uri.parse(listUrl))!;
  void visit(String next) {
    platform.currentPage = next;
    platform.pageStarted!(next);
  }

  Future<void> headerBack(WidgetTester tester) async {
    platform.channels['FlutterBridge']!.onMessageReceived(
        const JavaScriptMessage(message: '{"action":"hospitalListBack"}'));
    for (var i = 0; i < 8; i++) {
      await tester.pump();
    }
  }

  testWidgets(
      'app header arrow loads the filtered list explicitly',
      (tester) async {
    await pumpReady(tester, const StartupWebViewScreen(url: url));
    visit(listUrl);
    visit(detailUrl);
    platform.hasBackHistory = true;
    platform.pageFinished!(detailUrl);
    await tester.pump();
    expect(platform.scripts, contains(HospitalListNavigation.headerBackScript));
    await headerBack(tester);
    expect(platform.events, isNot(contains('goBack')));
    expect(platform.loadedUrls.last, nextListUrl);
  });
  testWidgets('booking back to detail then header back returns to list', (tester) async {
    await pumpReady(tester, const StartupWebViewScreen(url: url));
    visit(listUrl);
    visit(detailUrl);
    visit(detailUrl.replaceFirst('detail.php', 'booking.php'));
    visit(detailUrl);
    platform.hasBackHistory = true;
    await headerBack(tester);
    expect(platform.loadedUrls.last, nextListUrl);
    expect(platform.events, isNot(contains('goBack')));
  });
  for (final tabs in [false, true]) {
    testWidgets(
        'app header retains all filters with detail tabs=$tabs and no referrer',
        (tester) async {
      await pumpReady(tester, const StartupWebViewScreen(url: url));
      visit(listUrl);
      visit(detailUrl);
      if (tabs) visit('$detailUrl&tab=reviews');
      platform.hasBackHistory = tabs;
      await headerBack(tester);
      expect(platform.loadedUrls.last, nextListUrl);
      expect(platform.events, isNot(contains('goBack')));
    });
  }
  for (final next in ['$detailUrl&from=my_wishlist', url]) {
    testWidgets('header callback never revives a previous filter from $next',
        (tester) async {
      await pumpReady(tester, const StartupWebViewScreen(url: url));
      visit(listUrl);
      visit(next);
      await headerBack(tester);
      expect(platform.loadedUrls.length, 1);
      expect(platform.events, isNot(contains('goBack')));
    });
  }

  testWidgets('malformed auth bridge messages cannot mutate native credentials',
      (tester) async {
    var requests = 0;
    final auth = NativeAuthService.testing(client: MockClient((_) async {
      requests++;
      return http.Response('{}', 500);
    }));
    await pumpReady(tester, StartupWebViewScreen(url: url, authService: auth));
    final callback = platform.channels['FlutterBridge']!.onMessageReceived;
    for (final payload in [
      {'action': 42},
      {
        'action': 'authExchange',
        'code': ['wrong type']
      },
      {
        'action': 'authExchange',
        'code': 'b' * 64,
        'device_id': ['wrong type']
      },
    ]) {
      callback(JavaScriptMessage(message: jsonEncode(payload)));
      await tester.pump();
      expect(tester.takeException(), isNull);
    }
    expect(requests, 0);
    expect(disk.containsKey('native_session_v2'), isFalse);
  });

  testWidgets(
      'no stored session keeps cookies and sends the stored device id before load',
      (tester) async {
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    expect(platform.events, isNot(contains('clear')));
    // NestJS never mints sb_app_device; bootstrap answers 401 without it.
    final device =
        platform.cookies.singleWhere((c) => c.name == 'sb_app_device');
    expect(device.value, 'a' * 64);
    expect(device.domain, 'seoulbeauty.app');
    expect(platform.cookies.any((c) => c.name == 'sb_sso_token'), isFalse);
    expect(platform.events.indexOf('cookie:sb_app_device'),
        lessThan(platform.events.indexOf('load')));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('fresh install creates one device id and sends it as the cookie',
      (tester) async {
    disk.clear();
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    final device =
        platform.cookies.singleWhere((c) => c.name == 'sb_app_device');
    expect(device.value, matches(RegExp(r'^[a-f0-9]{64}$')));
    expect(disk['native_device_id'], device.value);
    expect(platform.events, isNot(contains('clear')));
    expect(platform.events, contains('load'));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('durable logout clears stale cookies before loading',
      (tester) async {
    disk['native_session_v2'] = '{"signed_out":true}';
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    expect(platform.events.first, 'clear');
    expect(platform.events, contains('load'));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
      'failed cookie clearing shows retry without opening stale account',
      (tester) async {
    disk['native_session_v2'] = '{"signed_out":true}';
    platform.failClear = true;
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    expect(platform.events, isNot(contains('load')));
    expect(find.widgetWithText(ElevatedButton, '다시 시도'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'device and bridge cookies and JS channels finish before first request',
      (tester) async {
    platform.channelReady = Completer<void>();
    await pumpReady(
        tester,
        StartupWebViewScreen(
            url: url, deviceId: 'a' * 64, sessionBridgeToken: 'b' * 64));
    expect(platform.events, isNot(contains('load')));
    expect(platform.cookies.map((c) => c.name),
        containsAll(['sb_app_device', 'sb_sso_token']));
    platform.channelReady!.complete();
    await tester.pump(const Duration(milliseconds: 20));
    expect(platform.events.last, 'load');
    expect(platform.events.indexOf('channel:FlutterBridge'),
        lessThan(platform.events.indexOf('load')));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('a verified session lands on the NestJS endpoint of the app host',
      (tester) async {
    // An old push link on the previous app host: cookies and the landing go to the app host.
    await pumpReady(
        tester,
        StartupWebViewScreen(
            url:
                'https://hms.seoulbeauty.app/hairwang/gangnamunni/my_bookings.php?tab=completed',
            deviceId: 'a' * 64,
            sessionBridgeToken: 'b' * 64));
    expect(platform.cookies.map((c) => c.domain).toSet(), {'seoulbeauty.app'});
    final first = platform.loadedUrls.single;
    expect(first.scheme, 'https');
    expect(first.host, 'seoulbeauty.app');
    expect(first.path, '/next/api/v1/app-auth/land');
    expect(first.queryParameters,
        {'url': '/next/mypage/bookings?tab=completed'});
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('without a verified session the start page loads directly',
      (tester) async {
    await pumpReady(
        tester, const StartupWebViewScreen(url: 'https://seoulbeauty.app/next/'));
    expect(platform.loadedUrls.single, Uri.parse('https://seoulbeauty.app/next/'));
    expect(platform.cookies.any((c) => c.name == 'sb_sso_token'), isFalse);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('a page outside the site never receives the session landing',
      (tester) async {
    await pumpReady(
        tester,
        StartupWebViewScreen(
            url: 'https://example.com/page', sessionBridgeToken: 'b' * 64));
    expect(platform.loadedUrls.single, Uri.parse('https://example.com/page'));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
      'cookie injection failure shows retry instead of silent guest login',
      (tester) async {
    platform.failSet = true;
    var retries = 0;
    await pumpReady(
        tester,
        StartupWebViewScreen(
            url: url,
            onAuthRetry: () {
              retries++;
            }));
    expect(platform.events, isNot(contains('load')));
    await tester.tap(find.widgetWithText(ElevatedButton, '다시 시도'));
    expect(retries, 1);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('gate retry recreates WebView after a cookie setup failure',
      (tester) async {
    platform.failSet = true;
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    expect(platform.events, isNot(contains('load')));
    platform.failSet = false;
    await tester.tap(find.widgetWithText(ElevatedButton, '다시 시도'));
    for (var i = 0; i < 8; i++) {
      await tester.pump(const Duration(milliseconds: 10));
    }
    expect(platform.events, contains('load'));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
      'verified native account replaces a stale WebView account before load',
      (tester) async {
    disk['native_session_v2'] = jsonEncode({
      'mb_id': 'patient',
      'device_id': 'a' * 64,
      'access_token': 'b' * 64,
      'refresh_token': 'c' * 64,
      'access_expires_at': DateTime.now().millisecondsSinceEpoch ~/ 1000 + 3600,
    });
    platform.cookies.add(const WebViewCookie(
        name: 'PHPSESSID',
        value: 'old-account',
        domain: 'seoulbeauty.app'));
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response(
                    jsonEncode({
                      'success': true,
                      'session_bridge_token': 'd' * 64,
                      'user': {'mb_id': 'patient'}
                    }),
                    200)))));
    expect(platform.events.first, 'clear');
    expect(platform.cookies.any((c) => c.name == 'PHPSESSID'), isFalse);
    expect(platform.cookies.singleWhere((c) => c.name == 'sb_sso_token').value,
        'd' * 64);
    expect(platform.events.last, 'load');
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
}

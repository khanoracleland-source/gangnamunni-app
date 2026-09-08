import 'dart:convert';
import 'package:http/testing.dart';
import 'package:http/http.dart' as http;
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

const url = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';

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
      'https://hms.seoulbeauty.app/hairwang/gangnamunni/hospitals.php?treatment=%ED%8B%B0%ED%83%80%EB%8A%84&sort=price&region=강남';
  const detailUrl =
      'https://hms.seoulbeauty.app/hairwang/gangnamunni/detail.php?id=8216&treatment_id=2&treatment=%ED%8B%B0%ED%83%80%EB%8A%84';
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
      'app header arrow uses native history for a direct filtered-list visit',
      (tester) async {
    await pumpReady(tester, const StartupWebViewScreen(url: url));
    visit(listUrl);
    visit(detailUrl);
    platform.hasBackHistory = true;
    platform.pageFinished!(detailUrl);
    await tester.pump();
    expect(platform.scripts, contains(HospitalListNavigation.headerBackScript));
    await headerBack(tester);
    expect(platform.events.where((e) => e == 'goBack').length, 1);
    expect(platform.loadedUrls.length,
        1); // Only startup; never a bare hospitals.php load.
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
      expect(platform.loadedUrls.last, Uri.parse(listUrl));
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
      'cookie-only upgrade neither clears cookies nor overwrites device binding',
      (tester) async {
    await pumpReady(
        tester,
        AppAuthGateScreen(
            url: url,
            authService: NativeAuthService.testing(
                client: MockClient((_) async => http.Response('', 500)))));
    expect(platform.events, isNot(contains('clear')));
    expect(platform.cookies.where((c) => c.name == 'sb_app_device'), isEmpty);
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
        domain: 'hms.seoulbeauty.app'));
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

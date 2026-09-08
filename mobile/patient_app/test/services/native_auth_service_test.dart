import 'dart:async';
import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kivicare_patient/services/native_auth_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  final device = 'a' * 64;
  final code = 'b' * 64;
  late Map<String, String> disk;
  late List<MethodCall> storageCalls;
  late List<http.Request> requests;
  late FutureOr<http.Response> Function(http.Request) respond;
  Future<void> Function(MethodCall)? beforeStorage;
  NativeAuthService makeService(
          {Duration timeout = const Duration(seconds: 1)}) =>
      NativeAuthService.testing(
        client: MockClient((r) async {
          requests.add(r);
          return await respond(r);
        }),
        requestTimeout: timeout,
        delay: (_) async {},
      );
  Map<String, dynamic> bundle(
          {String member = 'patient', String token = 'c'}) =>
      {
        'success': true,
        'user': {'mb_id': member},
        'access_token': token * 64,
        'refresh_token': 'd' * 64,
        'access_expires_at':
            DateTime.now().millisecondsSinceEpoch ~/ 1000 + 3600,
        'refresh_expires_at':
            DateTime.now().millisecondsSinceEpoch ~/ 1000 + 120 * 86400,
        'session_bridge_token': 'e' * 64,
      };
  http.Response jsonResponse(Map<String, dynamic> data, [int status = 200]) =>
      http.Response(jsonEncode(data), status);
  void seed({bool expired = false}) {
    final data = bundle();
    disk['native_device_id'] = device;
    disk['native_session_v2'] = jsonEncode({
      ...data,
      'mb_id': 'patient',
      'device_id': device,
      if (expired) 'access_expires_at': 1,
    });
  }

  setUp(() {
    disk = {};
    storageCalls = [];
    requests = [];
    beforeStorage = null;
    respond = (_) => jsonResponse(bundle());
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      storageCalls.add(call);
      await beforeStorage?.call(call);
      final args = Map<String, dynamic>.from(call.arguments as Map);
      final key = args['key'] as String?;
      switch (call.method) {
        case 'read':
          return disk[key];
        case 'write':
          disk[key!] = args['value'] as String;
          return null;
        case 'delete':
          disk.remove(key);
          return null;
        default:
          throw StateError('Unexpected storage operation');
      }
    });
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  test('corrupt session does not prevent a durable explicit logout', () async {
    disk['native_session_v2'] = '{broken';
    disk['native_refresh_token'] = 'd' * 64;
    await makeService().logout();
    expect(jsonDecode(disk['native_session_v2']!)['signed_out'], isTrue);
    expect(disk.containsKey('native_refresh_token'), isFalse);
    expect(requests, isEmpty);
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
  });

  test('storage read failure still attempts the logout tombstone write',
      () async {
    seed();
    beforeStorage = (call) async {
      if (call.method == 'read') throw PlatformException(code: 'read_failed');
    };
    await makeService().logout();
    expect(jsonDecode(disk['native_session_v2']!)['signed_out'], isTrue);
  });

  test('failed logout write is reported instead of pretending success',
      () async {
    seed();
    final saved = disk['native_session_v2'];
    beforeStorage = (call) async {
      if (call.method == 'write') throw PlatformException(code: 'write_failed');
    };
    await expectLater(
        makeService().logout(), throwsA(isA<PlatformException>()));
    expect(disk['native_session_v2'], saved);
    expect(requests, isEmpty);
  });

  for (final expired in [false, true]) {
    for (final user in [
      null,
      {'mb_id': 'another_patient'},
      {'mb_id': 42}
    ]) {
      test(
          'wrong or missing response identity preserves session ($expired, $user)',
          () async {
        seed(expired: expired);
        final saved = disk['native_session_v2'];
        respond = (_) => jsonResponse({...bundle(), 'user': user});
        expect((await makeService().resolveAuthState()).status,
            NativeAuthStatus.retry);
        expect(disk['native_session_v2'], saved);
      });
    }
  }

  for (final missing in <String?>[null, '']) {
    test('no native token ($missing) preserves cookie-only login', () async {
      if (missing != null) disk['native_refresh_token'] = missing;
      expect((await makeService().resolveAuthState()).status,
          NativeAuthStatus.noStoredSession);
      expect(storageCalls.every((c) => c.method == 'read'), isTrue);
      expect(requests, isEmpty);
    });
  }
  test('parallel first requests share one durable device id', () async {
    final service = makeService();
    final ids = await Future.wait(
        List.generate(10, (_) => service.getOrCreateDeviceId()));
    expect(ids.toSet().length, 1);
    expect(disk['native_device_id'], ids.first);
    expect(storageCalls.where((c) => c.method == 'write').length, 1);
  });
  test(
      'exchange survives a fresh service (cold start) and verifies before WebView',
      () async {
    final service = makeService();
    expect(await service.exchangeCode(code), isTrue);
    final saved = disk['native_session_v2'];
    expect(saved, isNotNull);
    expect(
        storageCalls
            .where((c) =>
                c.method == 'write' &&
                c.arguments['key'] == 'native_session_v2')
            .length,
        1);
    final result = await makeService().resolveAuthState();
    expect(result.status, NativeAuthStatus.valid);
    expect(result.sessionBridgeToken, 'e' * 64);
    expect(requests.last.url.path, endsWith('app_auth_verify.php'));
    expect(disk['native_session_v2'], saved);
  });
  test('legacy credentials migrate without forcing login', () async {
    disk.addAll({
      'native_device_id': device,
      'native_mb_id': 'patient',
      'native_refresh_token': 'd' * 64,
      'native_access_expires_at': '0'
    });
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.valid);
    expect(jsonDecode(disk['native_session_v2']!)['mb_id'], 'patient');
    expect(disk.containsKey('native_refresh_token'), isFalse);
  });
  test('cookie-only upgrade adopts authenticated WebView device binding',
      () async {
    disk['native_device_id'] = 'f' * 64;
    expect(await makeService().exchangeCode(code, deviceId: device), isTrue);
    expect(jsonDecode(requests.single.body)['device_id'], device);
    expect(jsonDecode(disk['native_session_v2']!)['device_id'], device);
  });
  test('stored session repairs a missing separate device key', () async {
    seed();
    disk.remove('native_device_id');
    expect(await makeService().getOrCreateDeviceId(), device);
    expect(disk['native_device_id'], device);
  });
  test('legacy login with lost device id is not revoked using a new random id',
      () async {
    disk.addAll({'native_mb_id': 'patient', 'native_refresh_token': 'd' * 64});
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.retry);
    expect(requests, isEmpty);
    expect(disk['native_refresh_token'], 'd' * 64);
  });
  test('expired access refreshes instead of logging out', () async {
    seed(expired: true);
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.valid);
    expect(requests.single.url.path, endsWith('app_auth_refresh.php'));
  });
  test('401 access falls back to valid refresh', () async {
    seed();
    respond = (r) => r.url.path.endsWith('verify.php')
        ? jsonResponse({'success': false, 'error': 'access_token_invalid'}, 401)
        : jsonResponse(bundle());
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.valid);
    expect(requests.length, 2);
  });
  for (final status in [400, 401, 403, 429, 500, 503]) {
    test('unrecognized HTTP $status never erases refresh credentials',
        () async {
      seed(expired: true);
      final saved = Map.of(disk);
      respond = (_) => http.Response('<html>proxy error</html>', status);
      expect((await makeService().resolveAuthState()).status,
          NativeAuthStatus.retry);
      expect(disk, saved);
    });
  }
  for (final error in ['refresh_invalid', 'account_unavailable']) {
    test('explicit $error persists signed-out state across restart', () async {
      seed(expired: true);
      respond = (_) => jsonResponse({'success': false, 'error': error}, 401);
      expect((await makeService().resolveAuthState()).status,
          NativeAuthStatus.needsLogin);
      expect((await makeService().resolveAuthState()).status,
          NativeAuthStatus.needsLogin);
      expect(disk['native_device_id'], device);
      expect(requests.length, 1);
    });
  }
  test('offline and timeout keep the saved session', () async {
    seed(expired: true);
    final saved = Map.of(disk);
    respond = (_) => throw const SocketLikeException();
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.retry);
    respond = (_) => Completer<http.Response>().future;
    expect(
        (await makeService(timeout: const Duration(milliseconds: 5))
                .resolveAuthState())
            .status,
        NativeAuthStatus.retry);
    expect(disk, saved);
  });
  for (final field in [
    'access_token',
    'refresh_token',
    'session_bridge_token',
    'access_expires_at',
    'refresh_expires_at'
  ]) {
    test('partial refresh response missing $field leaves old bundle intact',
        () async {
      seed(expired: true);
      final saved = Map.of(disk);
      respond = (_) => jsonResponse(bundle()..remove(field));
      expect((await makeService().resolveAuthState()).status,
          NativeAuthStatus.retry);
      expect(disk, saved);
    });
  }
  test('partial exchange is not acknowledged or stored', () async {
    respond = (_) => jsonResponse(bundle()..remove('refresh_token'));
    expect(await makeService().exchangeCode(code), isFalse);
    expect(disk.containsKey('native_session_v2'), isFalse);
  });
  test('failed atomic write leaves previous bundle usable', () async {
    seed(expired: true);
    final saved = Map.of(disk);
    beforeStorage = (c) async {
      if (c.method == 'write' && c.arguments['key'] == 'native_session_v2') {
        throw PlatformException(code: 'disk_full');
      }
    };
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.retry);
    expect(disk, saved);
  });
  test('exchange storage failure returns false without an async exception',
      () async {
    beforeStorage = (c) async {
      if (c.method == 'write') throw PlatformException(code: 'disk_full');
    };
    expect(await makeService().exchangeCode(code), isFalse);
  });
  test('lost exchange response retries identical code and device', () async {
    var n = 0;
    respond = (_) {
      if (++n == 1) throw const SocketLikeException();
      return jsonResponse(bundle());
    };
    expect(await makeService().exchangeCode(code), isTrue);
    expect(requests.length, 2);
    expect(requests.first.body, requests.last.body);
  });
  test('repeated same-code messages share one exchange and one saved result',
      () async {
    final service = makeService();
    expect(
        await Future.wait(List.generate(5, (_) => service.exchangeCode(code))),
        everyElement(isTrue));
    expect(await service.exchangeCode(code), isTrue);
    expect(requests.length, 1);
  });
  test('concurrent startup callers share one verify/bridge', () async {
    seed();
    final service = makeService();
    final results =
        await Future.wait(List.generate(5, (_) => service.resolveAuthState()));
    expect(results.every((r) => r.status == NativeAuthStatus.valid), isTrue);
    expect(requests.length, 1);
  });
  test('late verify after logout cannot restore the account', () async {
    seed();
    final started = Completer<void>();
    final response = Completer<http.Response>();
    respond = (r) {
      if (r.url.path.endsWith('verify.php')) {
        started.complete();
        return response.future;
      }
      return jsonResponse({'success': true});
    };
    final service = makeService();
    final resolving = service.resolveAuthState();
    await started.future;
    await service.logout();
    response.complete(jsonResponse(bundle()));
    expect((await resolving).status, NativeAuthStatus.retry);
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
  });
  test('logout is durable before waiting for server response', () async {
    seed();
    final started = Completer<void>();
    final response = Completer<http.Response>();
    respond = (_) {
      started.complete();
      return response.future;
    };
    final service = makeService();
    final loggingOut = service.logout();
    await started.future;
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
    response.complete(jsonResponse({'success': true}));
    await loggingOut;
  });
  test('logout waits for an in-progress disk write then persists tombstone',
      () async {
    final writeStarted = Completer<void>();
    final releaseWrite = Completer<void>();
    beforeStorage = (c) async {
      if (c.method == 'write' &&
          c.arguments['key'] == 'native_session_v2' &&
          !((c.arguments['value'] as String).contains('signed_out'))) {
        writeStarted.complete();
        await releaseWrite.future;
      }
    };
    final service = makeService();
    final exchanging = service.exchangeCode(code);
    await writeStarted.future;
    final loggingOut = service.logout();
    releaseWrite.complete();
    await exchanging;
    await loggingOut;
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
    expect(await service.exchangeCode(code), isFalse);
  });
  test('logout tombstone dominates leftover legacy keys after delete failure',
      () async {
    seed();
    disk['native_refresh_token'] = 'f' * 64;
    beforeStorage = (c) async {
      if (c.method == 'delete') throw PlatformException(code: 'delete_failed');
    };
    await makeService().logout();
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
  });
  test('old refresh rejection cannot clear a new login', () async {
    seed(expired: true);
    final started = Completer<void>();
    final response = Completer<http.Response>();
    respond = (r) {
      if (r.url.path.endsWith('refresh.php')) {
        started.complete();
        return response.future;
      }
      return jsonResponse(bundle(member: 'new_patient'));
    };
    final service = makeService();
    final resolving = service.resolveAuthState();
    await started.future;
    expect(await service.exchangeCode(code), isTrue);
    response.complete(
        jsonResponse({'success': false, 'error': 'refresh_invalid'}, 401));
    expect((await resolving).status, NativeAuthStatus.retry);
    expect(jsonDecode(disk['native_session_v2']!)['mb_id'], 'new_patient');
  });
  test(
      'different exchange codes execute in order and only newest result is saved',
      () async {
    final firstStarted = Completer<void>();
    final firstResponse = Completer<http.Response>();
    respond = (r) {
      if (jsonDecode(r.body)['code'] == code) {
        firstStarted.complete();
        return firstResponse.future;
      }
      return jsonResponse(bundle(member: 'new_patient'));
    };
    final service = makeService();
    final first = service.exchangeCode(code);
    await firstStarted.future;
    final second = service.exchangeCode('f' * 64);
    await Future<void>.delayed(Duration.zero);
    expect(requests.length, 1);
    firstResponse.complete(jsonResponse(bundle()));
    expect(await first, isFalse);
    expect(await second, isTrue);
    expect(jsonDecode(disk['native_session_v2']!)['mb_id'], 'new_patient');
  });
  test('new login waits for delayed server logout to finish', () async {
    seed();
    final logoutStarted = Completer<void>();
    final logoutResponse = Completer<http.Response>();
    respond = (r) {
      if (r.url.path.endsWith('app_logout.php')) {
        logoutStarted.complete();
        return logoutResponse.future;
      }
      return jsonResponse(bundle(member: 'new_patient'));
    };
    final service = makeService();
    final logout = service.logout();
    await logoutStarted.future;
    final login = service.exchangeCode(code);
    await Future<void>.delayed(Duration.zero);
    expect(requests.length, 1);
    logoutResponse.complete(jsonResponse({'success': true}));
    await logout;
    expect(await login, isTrue);
    expect(jsonDecode(disk['native_session_v2']!)['mb_id'], 'new_patient');
  });
  test('exchange finishing after logout is revoked without writing credentials',
      () async {
    final started = Completer<void>();
    final response = Completer<http.Response>();
    respond = (r) {
      if (r.url.path.endsWith('app_auth_exchange.php')) {
        started.complete();
        return response.future;
      }
      return jsonResponse({'success': true});
    };
    final service = makeService();
    final login = service.exchangeCode(code);
    await started.future;
    final logout = service.logout();
    response.complete(jsonResponse(bundle()));
    expect(await login, isFalse);
    await logout;
    expect(requests.last.url.path, endsWith('app_logout.php'));
    expect((await makeService().resolveAuthState()).status,
        NativeAuthStatus.needsLogin);
  });
}

class SocketLikeException implements Exception {
  const SocketLikeException();
}

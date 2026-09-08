import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import '../configs.dart';

enum NativeAuthStatus {
  /// Upgrade/failed exchange: the WebView may still have a valid login.
  noStoredSession,
  valid,

  /// Explicit logout or a credential rejection from the auth API.
  needsLogin,

  /// No verdict. Keep credentials and let the user retry.
  retry,
}

class NativeAuthResult {
  final NativeAuthStatus status;
  final String? sessionBridgeToken;
  final String? errorMessage;
  const NativeAuthResult(this.status,
      {this.sessionBridgeToken, this.errorMessage});
}

/// Web login -> short-lived exchange code -> credentials in Keychain/Keystore.
/// All session fields are committed as ONE encrypted value. Legacy separate
/// keys are read for upgrades, but can never override the new session record.
class NativeAuthService {
  NativeAuthService._() : this.testing(client: http.Client());

  @visibleForTesting
  NativeAuthService.testing({
    required http.Client client,
    FlutterSecureStorage storage = const FlutterSecureStorage(
      aOptions: AndroidOptions(encryptedSharedPreferences: true),
    ),
    Duration requestTimeout = const Duration(seconds: 12),
    Future<void> Function(Duration)? delay,
  })  : _client = client,
        _storage = storage,
        _requestTimeout = requestTimeout,
        _delay = delay ?? Future<void>.delayed;

  static final NativeAuthService instance = NativeAuthService._();
  final http.Client _client;
  final FlutterSecureStorage _storage;
  final Duration _requestTimeout;
  final Future<void> Function(Duration) _delay;

  static const _kSession = 'native_session_v2';
  static const _kDeviceId = 'native_device_id';
  static const _legacyKeys = <String>[
    'native_mb_id',
    'native_access_token',
    'native_access_expires_at',
    'native_refresh_token',
    'native_refresh_expires_at',
  ];
  static final _tokenPattern = RegExp(r'^[a-f0-9]{64}$');
  static final Uri _apiBase = Uri.parse(STARTUP_WEBVIEW_URL);

  int _epoch = 0;
  int _lastLogoutEpoch = 0;
  Future<void> _mutationTail = Future<void>.value();
  Future<void> _exchangeTail = Future<void>.value();
  Future<String>? _deviceInFlight;
  Future<NativeAuthResult>? _resolveInFlight;
  final Map<String, Future<bool>> _exchanges = {};
  final Set<String> _loggedOutCodes = {};
  String? _completedCode;

  Uri _endpoint(String file) => _apiBase.replace(
      path: '/hairwang/gangnamunni/api/$file', query: '', fragment: '');

  // Serialize the epoch check AND the asynchronous write/delete, so logout
  // cannot interleave between the check and a delayed platform write.
  Future<T> _mutate<T>(Future<T> Function() action) {
    final next = _mutationTail.then((_) => action());
    _mutationTail =
        next.then<void>((_) {}, onError: (Object _, StackTrace __) {});
    return next;
  }

  Future<Map<String, dynamic>?> _readSession() async {
    final encoded = await _storage.read(key: _kSession);
    if (encoded != null) {
      final value = jsonDecode(encoded);
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid session record');
      }
      return value; // Includes the durable signed_out tombstone.
    }
    final refresh = await _storage.read(key: 'native_refresh_token');
    if (refresh == null || refresh.isEmpty) return null;
    return {
      'mb_id': await _storage.read(key: 'native_mb_id'),
      'refresh_token': refresh,
      'access_token': await _storage.read(key: 'native_access_token'),
      'access_expires_at': int.tryParse(
              await _storage.read(key: 'native_access_expires_at') ?? '') ??
          0,
      'refresh_expires_at': int.tryParse(
              await _storage.read(key: 'native_refresh_expires_at') ?? '') ??
          0,
    };
  }

  Future<String> getOrCreateDeviceId() {
    return _deviceInFlight ??=
        _mutate(_loadDeviceId).whenComplete(() => _deviceInFlight = null);
  }

  Future<String> _loadDeviceId() async {
    final session = await _readSession();
    final stored = await _storage.read(key: _kDeviceId);
    // A v2 bundle also carries its device binding; restore it if the separate
    // device key was lost. Never invent a new binding for an existing login.
    final bundled = session?['device_id'];
    if (bundled is String && _tokenPattern.hasMatch(bundled)) {
      if (stored != bundled) {
        await _storage.write(key: _kDeviceId, value: bundled);
      }
      return bundled;
    }
    if (stored != null && _tokenPattern.hasMatch(stored)) return stored;
    if (session != null && session['signed_out'] != true) {
      throw StateError('Missing device binding for stored session');
    }
    final random = Random.secure();
    final id = List<int>.generate(32, (_) => random.nextInt(256))
        .map((b) => b.toRadixString(16).padLeft(2, '0'))
        .join();
    await _storage.write(key: _kDeviceId, value: id);
    return id;
  }

  Future<bool> get hasStoredSession async {
    final session = await _readSession();
    return session != null && session['signed_out'] != true;
  }

  Future<NativeAuthResult> resolveAuthState() {
    return _resolveInFlight ??=
        _resolve().whenComplete(() => _resolveInFlight = null);
  }

  Future<NativeAuthResult> _resolve() async {
    final epoch = _epoch;
    try {
      final session = await _readSession();
      if (epoch != _epoch) return _superseded;
      if (session == null) {
        return const NativeAuthResult(NativeAuthStatus.noStoredSession);
      }
      if (session['signed_out'] == true) {
        return const NativeAuthResult(NativeAuthStatus.needsLogin);
      }
      final mbId = session['mb_id'];
      final refresh = session['refresh_token'];
      if (mbId is! String || mbId.isEmpty || !_isToken(refresh)) {
        return const NativeAuthResult(NativeAuthStatus.retry,
            errorMessage: 'incomplete_session');
      }
      final device = await getOrCreateDeviceId();
      final access = session['access_token'];
      final expiry = session['access_expires_at'];
      if (_isToken(access) &&
          expiry is int &&
          expiry - 30 > DateTime.now().millisecondsSinceEpoch ~/ 1000) {
        final res = await _post('app_auth_verify.php',
            {'device_id': device, 'access_token': access});
        if (epoch != _epoch) return _superseded;
        final data = _decode(res);
        if (res.statusCode == 200 &&
            data?['success'] == true &&
            _responseMember(data) == mbId &&
            _isToken(data?['session_bridge_token'])) {
          return NativeAuthResult(NativeAuthStatus.valid,
              sessionBridgeToken: data!['session_bridge_token']);
        }
        if (!_rejected(
            res, data, {'access_token_invalid', 'account_unavailable'})) {
          return const NativeAuthResult(NativeAuthStatus.retry,
              errorMessage: 'verify_unavailable');
        }
        // An expired/revoked access token alone does not invalidate refresh.
      }
      final res = await _post('app_auth_refresh.php', {
        'mb_id': mbId,
        'refresh_token': refresh,
        'device_id': device,
      });
      if (epoch != _epoch) return _superseded;
      final data = _decode(res);
      if (res.statusCode == 200 && data?['success'] == true) {
        if (!_validBundle(data!) || _responseMember(data) != mbId) {
          return const NativeAuthResult(NativeAuthStatus.retry,
              errorMessage: 'malformed_refresh_response');
        }
        if (!await _persist(epoch, mbId, device, data)) return _superseded;
        return NativeAuthResult(NativeAuthStatus.valid,
            sessionBridgeToken: data['session_bridge_token']);
      }
      if (_rejected(res, data, {'refresh_invalid', 'account_unavailable'})) {
        final cleared = await _mutate(() async {
          if (epoch != _epoch) return false;
          await _clearSession();
          return true;
        });
        return cleared
            ? const NativeAuthResult(NativeAuthStatus.needsLogin)
            : _superseded;
      }
      return const NativeAuthResult(NativeAuthStatus.retry,
          errorMessage: 'refresh_unavailable');
    } catch (_) {
      return const NativeAuthResult(NativeAuthStatus.retry,
          errorMessage: 'auth_unavailable');
    }
  }

  static const _superseded =
      NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'auth_changed');
  static String? _responseMember(Map<String, dynamic>? data) {
    final user = data?['user'];
    final id = user is Map ? user['mb_id'] : null;
    return id is String && id.isNotEmpty ? id : null;
  }

  static bool _isToken(dynamic value) =>
      value is String && _tokenPattern.hasMatch(value);
  static bool _validBundle(Map<String, dynamic> data) =>
      _isToken(data['access_token']) &&
      _isToken(data['refresh_token']) &&
      _isToken(data['session_bridge_token']) &&
      data['access_expires_at'] is int &&
      data['access_expires_at'] > 0 &&
      data['refresh_expires_at'] is int &&
      data['refresh_expires_at'] > 0;

  static Map<String, dynamic>? _decode(http.Response response) {
    try {
      final data = jsonDecode(response.body);
      return data is Map<String, dynamic> ? data : null;
    } catch (_) {
      return null;
    }
  }

  static bool _rejected(
          http.Response res, Map<String, dynamic>? data, Set<String> errors) =>
      res.statusCode == 401 &&
      data?['success'] == false &&
      errors.contains(data?['error']);

  Future<http.Response> _post(String file, Map<String, dynamic> body) => _client
      .post(
        _endpoint(file),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode(body),
      )
      .timeout(_requestTimeout);

  Future<bool> exchangeCode(String code, {String? deviceId}) {
    if (!_isToken(code) || _loggedOutCodes.contains(code)) {
      return Future.value(false);
    }
    if (deviceId != null && !_isToken(deviceId)) return Future.value(false);
    if (_completedCode == code) return Future.value(true);
    final pending = _exchanges[code];
    if (pending != null) return pending;
    final epoch = ++_epoch;
    final operation = _exchangeTail
        .then((_) => _exchange(code, epoch, deviceId))
        .whenComplete(() {
      _exchanges.remove(code);
    });
    _exchangeTail =
        operation.then<void>((_) {}, onError: (Object _, StackTrace __) {});
    _exchanges[code] = operation;
    return operation;
  }

  Future<bool> _exchange(String code, int epoch, String? webDeviceId) async {
    try {
      if (webDeviceId != null) {
        final adopted = await _mutate(() async {
          if (epoch != _epoch) return false;
          final session = await _readSession();
          if (session == null || session['signed_out'] == true) {
            await _storage.write(key: _kDeviceId, value: webDeviceId);
            return true;
          }
          return (session['device_id'] ??
                  await _storage.read(key: _kDeviceId)) ==
              webDeviceId;
        });
        if (!adopted) return false;
      }
      final device = await getOrCreateDeviceId();
      for (var attempt = 1; attempt <= 3; attempt++) {
        if (epoch != _epoch) return false;
        http.Response? res;
        try {
          res = await _post(
              'app_auth_exchange.php', {'code': code, 'device_id': device});
        } catch (_) {/* A lost response can retry the same idempotent code. */}
        if (epoch != _epoch) {
          if (_lastLogoutEpoch > epoch && res?.statusCode == 200) {
            final discarded = _decode(res!);
            if (discarded != null &&
                _validBundle(discarded) &&
                discarded['user'] is Map) {
              await _revoke({
                ...discarded,
                'mb_id': discarded['user']['mb_id'],
                'device_id': device
              });
            }
          }
          return false;
        }
        if (res == null || res.statusCode >= 500 || res.statusCode == 429) {
          if (attempt == 3) return false;
          await _delay(Duration(milliseconds: 400 * attempt));
          continue;
        }
        final data = _decode(res);
        if (res.statusCode != 200 ||
            data?['success'] != true ||
            !_validBundle(data!)) {
          return false;
        }
        final mbId = _responseMember(data);
        if (mbId == null) return false;
        if (!await _persist(epoch, mbId, device, data)) return false;
        _completedCode = code;
        return true;
      }
    } catch (_) {
      // Storage failure is an exchange failure, never an unhandled async error.
    }
    return false;
  }

  Future<bool> _persist(
          int epoch, String mbId, String device, Map<String, dynamic> data) =>
      _mutate(() async {
        if (epoch != _epoch) return false;
        await _storage.write(
            key: _kSession,
            value: jsonEncode({
              'mb_id': mbId,
              'device_id': device,
              'access_token': data['access_token'],
              'access_expires_at': data['access_expires_at'],
              'refresh_token': data['refresh_token'],
              'refresh_expires_at': data['refresh_expires_at'],
            }));
        await _removeLegacyKeys();
        return epoch == _epoch;
      });

  Future<void> _removeLegacyKeys() async {
    for (final key in _legacyKeys) {
      try {
        await _storage.delete(key: key);
      } catch (_) {/* v2 remains authoritative. */}
    }
  }

  Future<void> _clearSession() async {
    // Persist logout BEFORE deleting legacy keys or making a network request.
    // A process kill now cannot revive stale v1 keys on the next launch.
    await _storage.write(key: _kSession, value: '{"signed_out":true}');
    _completedCode = null;
    await _removeLegacyKeys();
  }

  Future<void> _revoke(Map<String, dynamic> session) async {
    try {
      await _post('app_logout.php', {
        'mb_id': session['mb_id'],
        'device_id': session['device_id'],
        'access_token': session['access_token'],
        'refresh_token': session['refresh_token'],
      });
    } catch (_) {/* Local logout is already durable even when offline. */}
  }

  Future<void> logout() async {
    _lastLogoutEpoch = ++_epoch;
    _loggedOutCodes.addAll(_exchanges.keys);
    if (_completedCode != null) _loggedOutCodes.add(_completedCode!);
    _completedCode = null;
    // A new exchange must wait until this device's old server credentials
    // have been revoked; a delayed logout must never revoke the new login.
    final previousNetwork = _exchangeTail;
    final finished = Completer<void>();
    _exchangeTail = finished.future;
    try {
      final session = await _mutate(() async {
        Map<String, dynamic>? saved;
        String? device;
        try {
          saved = await _readSession();
          device = await _storage.read(key: _kDeviceId);
        } catch (_) {
          // Corrupt/unreadable credentials cannot prevent explicit logout.
          // The tombstone write must still succeed before logout completes.
        }
        await _clearSession();
        return saved == null
            ? null
            : {...saved, 'device_id': saved['device_id'] ?? device};
      });
      await previousNetwork;
      if (session != null && session['signed_out'] != true) {
        await _revoke(session);
      }
    } finally {
      finished.complete();
    }
  }
}

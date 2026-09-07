import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import '../configs.dart';

/// How [NativeAuthService.resolveAuthState] came out.
enum NativeAuthStatus {
  /// A usable [NativeAuthResult.sessionBridgeToken] is ready — load the
  /// WebView straight into the logged-in site, no login screen.
  valid,

  /// No refresh token, or the server rejected it (expired/revoked/device
  /// mismatch/account blocked). Show the normal (WebView) login page.
  needsLogin,

  /// A network or server error prevented reaching a verdict. Tokens were
  /// NOT touched — show a retry affordance instead of forcing a login.
  retry,
}

class NativeAuthResult {
  final NativeAuthStatus status;
  final String? sessionBridgeToken;
  final String? errorMessage;
  const NativeAuthResult(this.status, {this.sessionBridgeToken, this.errorMessage});
}

/// Bridges an already-authenticated WebView session (email, Kakao, or Apple
/// login — login itself still happens on the PHP page, unchanged) to a
/// native accessToken/refreshToken pair kept in FlutterSecureStorage
/// (Keystore/Keychain). This is what survives an Android WebView cookie
/// flush/kill that the browser cookie jar alone cannot guarantee.
///
/// Server contract (hairwang_full/gangnamunni/api/app_auth_*.php):
///   - app_auth_exchange.php : one-shot code (from the JS bridge) -> tokens
///   - app_auth_verify.php   : accessToken -> fresh WebView session-bridge token
///   - app_auth_refresh.php  : refreshToken -> new accessToken (+ bridge token)
///   - app_logout.php        : {mb_id, device_id} -> revokes this device only
class NativeAuthService {
  NativeAuthService._();
  static final NativeAuthService instance = NativeAuthService._();

  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _kAccessToken = 'native_access_token';
  static const _kAccessExpiresAt = 'native_access_expires_at';
  static const _kRefreshToken = 'native_refresh_token';
  static const _kRefreshExpiresAt = 'native_refresh_expires_at';
  static const _kMbId = 'native_mb_id';
  static const _kDeviceId = 'native_device_id';

  static final Uri _apiBase = () {
    final startup = Uri.parse(STARTUP_WEBVIEW_URL);
    return Uri(scheme: startup.scheme, host: startup.host, port: startup.hasPort ? startup.port : null);
  }();

  static const _requestTimeout = Duration(seconds: 12);

  // A refresh already in flight is awaited by every concurrent caller
  // instead of firing duplicate requests against the same refresh token.
  Completer<NativeAuthResult>? _refreshInFlight;

  // Bumped by logout(). Any exchange/refresh call started before a logout
  // captures the epoch at its start and checks it again right before writing
  // tokens — if logout ran in the meantime, the now-stale write is dropped
  // instead of resurrecting a session the user just ended on this phone.
  int _epoch = 0;

  Uri _endpoint(String file) => _apiBase.replace(path: '/hairwang/gangnamunni/api/$file');

  /// Stable per-install device identifier. Also injected into the WebView as
  /// the `sb_app_device` cookie (see StartupWebViewScreen) so the legacy
  /// cookie-based remember-login and this native flow bind to the same
  /// device — a logout from either side revokes both consistently.
  Future<String> getOrCreateDeviceId() async {
    final existing = await _storage.read(key: _kDeviceId);
    if (existing != null && RegExp(r'^[a-f0-9]{64}$').hasMatch(existing)) {
      return existing;
    }
    final bytes = List<int>.generate(32, (_) => Random.secure().nextInt(256));
    final id = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    await _storage.write(key: _kDeviceId, value: id);
    return id;
  }

  Future<bool> get hasStoredSession async =>
      (await _storage.read(key: _kRefreshToken)) != null;

  /// Entry point for app cold start. Never throws.
  Future<NativeAuthResult> resolveAuthState() async {
    try {
      final refreshToken = await _storage.read(key: _kRefreshToken);
      if (refreshToken == null || refreshToken.isEmpty) {
        return const NativeAuthResult(NativeAuthStatus.needsLogin);
      }

      final accessToken = await _storage.read(key: _kAccessToken);
      final accessExpiresAt = int.tryParse(await _storage.read(key: _kAccessExpiresAt) ?? '') ?? 0;
      final localNowSecs = DateTime.now().millisecondsSinceEpoch ~/ 1000;

      // A local expiry check only decides which call to make first — the
      // server has final say either way (it may have revoked the token even
      // if it "looks" unexpired on the phone).
      const skewSeconds = 30;
      if (accessToken != null && accessToken.isNotEmpty && accessExpiresAt - skewSeconds > localNowSecs) {
        final verified = await _verifyAccess(accessToken);
        if (verified.status != NativeAuthStatus.needsLogin) return verified;
        // access_token_invalid specifically -> fall through to refresh
        // instead of giving up (the phone's clock/estimate may be stale).
      }

      return await _refresh();
    } catch (e) {
      debugPrint('[NativeAuthService] resolveAuthState unexpected error: $e');
      return const NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'unexpected_error');
    }
  }

  Future<NativeAuthResult> _verifyAccess(String accessToken) async {
    final deviceId = await getOrCreateDeviceId();
    try {
      final res = await http
          .post(
            _endpoint('app_auth_verify.php'),
            headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer $accessToken'},
            body: jsonEncode({'device_id': deviceId}),
          )
          .timeout(_requestTimeout);

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body) as Map<String, dynamic>;
        if (data['success'] == true && data['session_bridge_token'] is String) {
          return NativeAuthResult(NativeAuthStatus.valid, sessionBridgeToken: data['session_bridge_token']);
        }
        return const NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'malformed_verify_response');
      }
      if (res.statusCode == 401) {
        return const NativeAuthResult(NativeAuthStatus.needsLogin, errorMessage: 'access_token_invalid');
      }
      // 4xx (other than 401) or 5xx: server-side problem, not a verdict.
      return NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'verify_http_${res.statusCode}');
    } on TimeoutException {
      return const NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'verify_timeout');
    } catch (e) {
      return NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'verify_error: $e');
    }
  }

  Future<NativeAuthResult> _refresh() {
    final inFlight = _refreshInFlight;
    if (inFlight != null) return inFlight.future;

    final completer = Completer<NativeAuthResult>();
    _refreshInFlight = completer;
    _doRefresh().then((result) {
      _refreshInFlight = null;
      completer.complete(result);
    }, onError: (e) {
      _refreshInFlight = null;
      completer.complete(NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'refresh_error: $e'));
    });
    return completer.future;
  }

  Future<NativeAuthResult> _doRefresh() async {
    final startEpoch = _epoch;
    final mbId = await _storage.read(key: _kMbId);
    final refreshToken = await _storage.read(key: _kRefreshToken);
    if (mbId == null || refreshToken == null) {
      return const NativeAuthResult(NativeAuthStatus.needsLogin);
    }
    final deviceId = await getOrCreateDeviceId();

    try {
      final res = await http
          .post(
            _endpoint('app_auth_refresh.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'mb_id': mbId, 'refresh_token': refreshToken, 'device_id': deviceId}),
          )
          .timeout(_requestTimeout);

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body) as Map<String, dynamic>;
        if (data['success'] != true) {
          return const NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'malformed_refresh_response');
        }
        if (startEpoch != _epoch) {
          // logout() ran while this request was in flight — the account on
          // this phone is meant to be signed out now; do not write these
          // (still technically valid) tokens back into storage.
          return const NativeAuthResult(NativeAuthStatus.needsLogin, errorMessage: 'superseded_by_logout');
        }
        await _persistTokens(mbId: mbId, data: data);
        return NativeAuthResult(NativeAuthStatus.valid, sessionBridgeToken: data['session_bridge_token'] as String?);
      }
      if (res.statusCode == 401) {
        // The refresh token itself is invalid/expired/revoked, or the
        // account was blocked in the meantime. This is the ONLY case that
        // should send the user back to the login page.
        if (startEpoch == _epoch) await _clearAll();
        return const NativeAuthResult(NativeAuthStatus.needsLogin, errorMessage: 'refresh_invalid');
      }
      // Any other status (5xx, etc.): keep the tokens, let the caller retry.
      // The refresh token renews in place server-side, so a lost response
      // here never strands a still-valid credential.
      return NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'refresh_http_${res.statusCode}');
    } on TimeoutException {
      return const NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'refresh_timeout');
    } catch (e) {
      return NativeAuthResult(NativeAuthStatus.retry, errorMessage: 'refresh_error: $e');
    }
  }

  /// Called by the WebView JS bridge right after a fresh login (any method)
  /// with the short-lived code the PHP page just handed it. Never sends the
  /// long-lived refresh token through the bridge/JS layer — only this code
  /// crosses that boundary; the actual tokens come back over HTTPS.
  ///
  /// Server-side (app_consume_exchange_code) treats the code as valid until
  /// its own ~90s expiry rather than deleting it after one use, specifically
  /// so this can retry the SAME code — including after the server already
  /// issued a success response that never reached the phone — instead of
  /// depending on another page loading within the 60s exchange window to
  /// mint a fresh one. Retries on network failures and 5xx (transient); a
  /// 4xx means the server made a final call on this exact request and
  /// retrying it would only repeat that answer.
  Future<bool> exchangeCode(String code) async {
    // Captured ONCE, before any attempt — not per-iteration. A logout()
    // firing between attempt 1 and attempt 2 must still be detected by
    // attempt 2's persist check; re-reading _epoch inside the loop would
    // silently adopt the post-logout epoch and let a late success repopulate
    // storage right after the user signed out on this phone.
    final startEpoch = _epoch;
    const maxAttempts = 3;

    for (var attempt = 1; attempt <= maxAttempts; attempt++) {
      final deviceId = await getOrCreateDeviceId();
      http.Response? res;
      try {
        res = await http
            .post(
              _endpoint('app_auth_exchange.php'),
              headers: {'Content-Type': 'application/json'},
              body: jsonEncode({'code': code, 'device_id': deviceId}),
            )
            .timeout(_requestTimeout);
      } catch (e) {
        debugPrint('[NativeAuthService] exchangeCode network error (attempt $attempt/$maxAttempts): $e');
      }

      final shouldRetry = res == null || res.statusCode >= 500;
      if (shouldRetry) {
        if (attempt == maxAttempts) return false;
        await Future.delayed(Duration(milliseconds: 400 * attempt));
        continue;
      }

      if (res!.statusCode != 200) return false; // 4xx: a retry would only repeat this answer
      Map<String, dynamic> data;
      try {
        data = jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {
        return false;
      }
      if (data['success'] != true) return false;

      final user = data['user'] as Map<String, dynamic>?;
      final mbId = user?['mb_id'] as String?;
      if (mbId == null || mbId.isEmpty) return false;

      if (startEpoch != _epoch) {
        // The user logged out (on this phone) since this exchange started.
        // The code may still be valid server-side, but these freshly-issued
        // tokens must not repopulate secure storage after that.
        return false;
      }
      await _persistTokens(mbId: mbId, data: data);
      return true;
    }
    return false;
  }

  Future<void> _persistTokens({required String mbId, required Map<String, dynamic> data}) async {
    await Future.wait([
      _storage.write(key: _kMbId, value: mbId),
      _storage.write(key: _kAccessToken, value: data['access_token'] as String?),
      _storage.write(key: _kAccessExpiresAt, value: '${data['access_expires_at']}'),
      _storage.write(key: _kRefreshToken, value: data['refresh_token'] as String?),
      _storage.write(key: _kRefreshExpiresAt, value: '${data['refresh_expires_at']}'),
    ]);
  }

  Future<void> _clearAll() async {
    await Future.wait([
      _storage.delete(key: _kAccessToken),
      _storage.delete(key: _kAccessExpiresAt),
      _storage.delete(key: _kRefreshToken),
      _storage.delete(key: _kRefreshExpiresAt),
      _storage.delete(key: _kMbId),
      // device_id is intentionally kept — it is not a secret by itself and
      // keeping it stable means a future login re-binds to the same device.
    ]);
  }

  /// Revokes this device's tokens on the server (best effort) and always
  /// clears local storage, even if the network call fails — a user who taps
  /// "logout" must never be stuck in a logged-in state on this phone.
  Future<void> logout() async {
    // Bump first: any refresh/exchange already in flight will see this
    // change and refuse to write its (now-stale) tokens back afterward.
    _epoch++;

    final mbId = await _storage.read(key: _kMbId);
    final deviceId = await _storage.read(key: _kDeviceId);
    final accessToken = await _storage.read(key: _kAccessToken);
    final refreshToken = await _storage.read(key: _kRefreshToken);
    if (mbId != null && deviceId != null) {
      try {
        // The server requires proof of ownership (a still-valid access or
        // refresh token matching mb_id+device_id) before revoking anything —
        // mb_id/device_id alone are not treated as sufficient authorization.
        await http
            .post(
              _endpoint('app_logout.php'),
              headers: {'Content-Type': 'application/json'},
              body: jsonEncode({
                'mb_id': mbId,
                'device_id': deviceId,
                if (accessToken != null) 'access_token': accessToken,
                if (refreshToken != null) 'refresh_token': refreshToken,
              }),
            )
            .timeout(_requestTimeout);
      } catch (e) {
        debugPrint('[NativeAuthService] server-side logout failed (clearing locally anyway): $e');
      }
    }
    await _clearAll();
  }
}

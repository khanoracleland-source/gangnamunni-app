import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../services/native_auth_service.dart';
import 'startup_webview_screen.dart';

/// 앱 시작점의 "문지기" 화면.
///
/// FlutterSecureStorage에 저장된 accessToken/refreshToken을 서버와 함께
/// 검증한 뒤에야 StartupWebViewScreen을 띄운다:
///   - 유효    -> sb_sso_token을 주입해 로그인된 상태로 바로 연다
///   - 로그인 필요 -> 평소처럼 로그인 페이지가 보이는 WebView를 연다
///   - 네트워크/서버 오류 -> 토큰은 그대로 두고 재시도 화면을 보여준다
///     (여기서 로그인 화면으로 넘기면 멀쩡한 refreshToken을 낭비하게 된다)
class AppAuthGateScreen extends StatefulWidget {
  final String url;
  final String type;

  const AppAuthGateScreen({Key? key, required this.url, this.type = 'general'}) : super(key: key);

  @override
  State<AppAuthGateScreen> createState() => _AppAuthGateScreenState();
}

class _AppAuthGateScreenState extends State<AppAuthGateScreen> {
  bool _loading = true;
  bool _showRetry = false;
  String? _deviceId;
  String? _sessionBridgeToken;

  @override
  void initState() {
    super.initState();
    _resolve();
  }

  Future<void> _resolve() async {
    setState(() {
      _loading = true;
      _showRetry = false;
    });

    try {
      final deviceId = await NativeAuthService.instance.getOrCreateDeviceId();
      final result = await NativeAuthService.instance.resolveAuthState();

      if (result.status == NativeAuthStatus.needsLogin) {
        // The native verdict is "logged out". If the WebView's own cookie jar
        // still holds a legacy ck_mb_id/ck_auto/sb_sso_token, common.php's
        // cookie-based auto-login would otherwise silently restore a session
        // this screen just decided should not exist — the user would end up
        // looking logged in despite the refresh token having been rejected.
        try {
          await WebViewCookieManager().clearCookies();
        } catch (_) {
          // Best effort — worst case the legacy cookie flow behaves as before.
        }
      }

      if (!mounted) return;

      setState(() {
        _loading = false;
        _deviceId = deviceId;
        _showRetry = result.status == NativeAuthStatus.retry;
        _sessionBridgeToken =
            result.status == NativeAuthStatus.valid ? result.sessionBridgeToken : null;
      });
    } catch (_) {
      // Secure storage can fail before resolveAuthState handles errors.
      // Preserve credentials and allow another attempt instead of spinning.
      if (!mounted) return;
      setState(() {
        _loading = false;
        _showRetry = true;
        _deviceId = null;
        _sessionBridgeToken = null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_showRetry) {
      return Scaffold(
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 16),
                  const Text(
                    '로그인 상태를 확인하지 못했습니다.\n잠시 후 다시 시도해주세요.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(onPressed: _resolve, child: const Text('다시 시도')),
                ],
              ),
            ),
          ),
        ),
      );
    }

    // needsLogin이든 valid든 결국 같은 WebView 화면이다 — 차이는 로그인된
    // 상태로 열리는지(sessionBridgeToken 주입) 여부뿐이다.
    return StartupWebViewScreen(
      url: widget.url,
      type: widget.type,
      deviceId: _deviceId,
      sessionBridgeToken: _sessionBridgeToken,
    );
  }
}

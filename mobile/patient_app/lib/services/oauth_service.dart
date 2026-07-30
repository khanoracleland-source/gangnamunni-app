import 'package:flutter/material.dart';
import 'package:flutter_custom_tabs/flutter_custom_tabs.dart';
import 'package:url_launcher/url_launcher.dart' as url_launcher;

class OAuthService {
  // OAuth URL 생성
  static String buildGoogleOAuthUrl({
    required String clientId,
    required String redirectUri,
    List<String> scopes = const ['openid', 'profile', 'email'],
  }) {
    final scopeString = scopes.join(' ');
    return 'https://accounts.google.com/o/oauth2/v2/auth?'
        'client_id=$clientId&'
        'redirect_uri=$redirectUri&'
        'response_type=code&'
        'scope=$scopeString&'
        'access_type=offline';
  }

  static String buildAppleOAuthUrl({
    required String clientId,
    required String redirectUri,
    List<String> scopes = const ['name', 'email'],
  }) {
    final scopeString = scopes.join(' ');
    return 'https://appleid.apple.com/auth/authorize?'
        'client_id=$clientId&'
        'redirect_uri=$redirectUri&'
        'response_type=code&'
        'scope=$scopeString&'
        'response_mode=form_post';
  }

  // Chrome Custom Tabs 런처 (Android) & ASWebAuthenticationSession (iOS)
  static Future<void> launchOAuthWithCustomTabs(
    BuildContext context,
    String url,
  ) async {
    try {
      await launchUrl(
        Uri.parse(url),
        customTabsOptions: CustomTabsOptions(
          colorSchemes: CustomTabsColorSchemes.defaults(
            toolbarColor: Theme.of(context).primaryColor,
          ),
          shareState: CustomTabsShareState.on,
          urlBarHidingEnabled: true,
          showTitle: true,
          browser: const CustomTabsBrowserConfiguration(
            fallbackCustomTabs: [
              'com.android.chrome',
              'com.chrome.beta',
              'com.chrome.dev',
            ],
          ),
        ),
        safariVCOptions: SafariViewControllerOptions(
          preferredBarTintColor: Theme.of(context).primaryColor,
          preferredControlTintColor: Colors.white,
          barCollapsingEnabled: true,
          dismissButtonStyle: SafariViewControllerDismissButtonStyle.close,
        ),
      );
    } catch (e) {
      debugPrint('OAuth Custom Tabs Error: $e');
      // Fallback to external browser
      await _launchExternalBrowser(url);
    }
  }

  // ASWebAuthenticationSession 런처 (iOS)
  static Future<void> launchOAuthWithSafari(
    BuildContext context,
    String url,
  ) async {
    try {
      await url_launcher.launchUrl(
        Uri.parse(url),
        mode: url_launcher.LaunchMode.externalApplication, // iOS에서 자동으로 ASWebAuthenticationSession 사용
      );
    } catch (e) {
      debugPrint('OAuth Safari Error: $e');
      await _launchExternalBrowser(url);
    }
  }

  // Fallback: 외부 브라우저 실행
  static Future<void> _launchExternalBrowser(String url) async {
    final uri = Uri.parse(url);
    if (await url_launcher.canLaunchUrl(uri)) {
      await url_launcher.launchUrl(uri, mode: url_launcher.LaunchMode.externalApplication);
    } else {
      throw 'Could not launch $url';
    }
  }

  // Deep Link 콜백 파싱
  static Map<String, String>? parseOAuthCallback(String callbackUrl) {
    try {
      final uri = Uri.parse(callbackUrl);
      final code = uri.queryParameters['code'];
      final state = uri.queryParameters['state'];
      final error = uri.queryParameters['error'];

      if (error != null) {
        return {'error': error};
      }

      if (code != null) {
        return {'code': code, 'state': state ?? ''};
      }

      return null;
    } catch (e) {
      debugPrint('Parse OAuth Callback Error: $e');
      return null;
    }
  }
}

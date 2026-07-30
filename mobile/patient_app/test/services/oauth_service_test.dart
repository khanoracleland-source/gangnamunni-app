// Unit tests for OAuthService URL building and callback parsing.
//
// Tests the static methods that do not require BuildContext:
//   - buildGoogleOAuthUrl()  -- constructs Google OAuth 2.0 authorization URL
//   - buildAppleOAuthUrl()   -- constructs Apple Sign-In authorization URL
//   - parseOAuthCallback()   -- parses callback URL to extract code/state/error
//
// The launcher methods (launchOAuthWithCustomTabs, launchOAuthWithSafari) are
// excluded because they require BuildContext and platform interaction.

import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/services/oauth_service.dart';

void main() {
  // =====================================================================
  // GROUP: buildGoogleOAuthUrl
  // =====================================================================
  group('buildGoogleOAuthUrl', () {
    test('returns URL with correct base domain', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test-client-id',
        redirectUri: 'wellness://oauth/callback',
      );
      expect(url.startsWith('https://accounts.google.com/o/oauth2/v2/auth?'), isTrue);
    });

    test('includes client_id parameter', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'my-google-client-id',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['client_id'], 'my-google-client-id');
    });

    test('includes redirect_uri parameter', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['redirect_uri'], 'wellness://oauth/callback');
    });

    test('response_type is code', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['response_type'], 'code');
    });

    test('access_type is offline', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['access_type'], 'offline');
    });

    test('default scopes are openid profile email', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], 'openid profile email');
    });

    test('custom scopes are joined with space', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
        scopes: ['email', 'calendar'],
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], 'email calendar');
    });

    test('single custom scope works', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
        scopes: ['openid'],
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], 'openid');
    });

    test('empty scopes list produces empty scope parameter', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
        scopes: [],
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], '');
    });

    test('URL is a valid URI that can be parsed', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'client.apps.googleusercontent.com',
        redirectUri: 'wellness://oauth/callback',
      );
      expect(() => Uri.parse(url), returnsNormally);
      final uri = Uri.parse(url);
      expect(uri.scheme, 'https');
      expect(uri.host, 'accounts.google.com');
    });

    test('special characters in clientId are preserved', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: '123-abc.apps.googleusercontent.com',
        redirectUri: 'wellness://oauth/callback',
      );
      // The URL is built via string concatenation, so the clientId
      // appears as-is in the URL string
      expect(url.contains('123-abc.apps.googleusercontent.com'), isTrue);
    });

    test('redirect_uri with kivicare scheme works', () {
      final url = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'kivicare://oauth/callback',
      );
      expect(url.contains('kivicare://oauth/callback'), isTrue);
    });
  });

  // =====================================================================
  // GROUP: buildAppleOAuthUrl
  // =====================================================================
  group('buildAppleOAuthUrl', () {
    test('returns URL with correct Apple base domain', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'com.example.app',
        redirectUri: 'wellness://oauth/callback',
      );
      expect(url.startsWith('https://appleid.apple.com/auth/authorize?'), isTrue);
    });

    test('includes client_id parameter', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'com.example.kivicare',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['client_id'], 'com.example.kivicare');
    });

    test('includes redirect_uri parameter', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['redirect_uri'], 'wellness://oauth/callback');
    });

    test('response_type is code', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['response_type'], 'code');
    });

    test('response_mode is form_post', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['response_mode'], 'form_post');
    });

    test('default scopes are name email', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], 'name email');
    });

    test('custom scopes are joined with space', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
        scopes: ['email'],
      );
      final uri = Uri.parse(url);
      expect(uri.queryParameters['scope'], 'email');
    });

    test('URL is a valid URI', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'com.example.app',
        redirectUri: 'kivicare://oauth/callback',
      );
      expect(() => Uri.parse(url), returnsNormally);
      final uri = Uri.parse(url);
      expect(uri.scheme, 'https');
      expect(uri.host, 'appleid.apple.com');
    });

    test('Apple URL does not contain access_type (Google-specific)', () {
      final url = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      expect(url.contains('access_type'), isFalse);
    });
  });

  // =====================================================================
  // GROUP: Differences between Google and Apple URLs
  // =====================================================================
  group('Google vs Apple URL differences', () {
    test('Google uses accounts.google.com, Apple uses appleid.apple.com', () {
      final googleUrl = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final appleUrl = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );

      expect(Uri.parse(googleUrl).host, 'accounts.google.com');
      expect(Uri.parse(appleUrl).host, 'appleid.apple.com');
    });

    test('Google includes access_type=offline, Apple includes response_mode=form_post', () {
      final googleUrl = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final appleUrl = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );

      expect(googleUrl.contains('access_type=offline'), isTrue);
      expect(appleUrl.contains('response_mode=form_post'), isTrue);
      expect(googleUrl.contains('response_mode'), isFalse);
      expect(appleUrl.contains('access_type'), isFalse);
    });

    test('default scopes differ between providers', () {
      final googleUrl = OAuthService.buildGoogleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );
      final appleUrl = OAuthService.buildAppleOAuthUrl(
        clientId: 'test',
        redirectUri: 'wellness://oauth/callback',
      );

      final googleScope = Uri.parse(googleUrl).queryParameters['scope']!;
      final appleScope = Uri.parse(appleUrl).queryParameters['scope']!;

      expect(googleScope, 'openid profile email');
      expect(appleScope, 'name email');
      expect(googleScope != appleScope, isTrue);
    });
  });

  // =====================================================================
  // GROUP: parseOAuthCallback
  // =====================================================================
  group('parseOAuthCallback', () {
    // --- Successful callback with code ---
    test('extracts code from callback URL', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=auth_code_123',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'auth_code_123');
    });

    test('extracts code and state from callback URL', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=abc123&state=xyz789',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'abc123');
      expect(result!['state'], 'xyz789');
    });

    test('state defaults to empty string when not present', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=mycode',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'mycode');
      expect(result!['state'], '');
    });

    test('code with special characters is extracted correctly', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=4%2FaBC-dEf_123',
      );
      expect(result, isNotNull);
      expect(result!['code'], '4/aBC-dEf_123');
    });

    // --- Error callback ---
    test('extracts error from callback URL', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?error=access_denied',
      );
      expect(result, isNotNull);
      expect(result!['error'], 'access_denied');
      expect(result!.containsKey('code'), isFalse);
    });

    test('error takes priority over code in the same URL', () {
      // The implementation checks error first, so if error is present,
      // it returns {'error': ...} without code
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?error=invalid_request&code=abc123',
      );
      expect(result, isNotNull);
      expect(result!['error'], 'invalid_request');
      expect(result!.containsKey('code'), isFalse);
    });

    test('error with descriptive message is extracted', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?error=server_error',
      );
      expect(result, isNotNull);
      expect(result!['error'], 'server_error');
    });

    // --- No code or error ---
    test('returns null when neither code nor error is present', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback',
      );
      expect(result, isNull);
    });

    test('returns null when only state is present without code', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?state=some_state',
      );
      expect(result, isNull);
    });

    test('returns null when only unrelated parameters are present', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?foo=bar&baz=qux',
      );
      expect(result, isNull);
    });

    // --- Different URL schemes ---
    test('works with kivicare:// scheme', () {
      final result = OAuthService.parseOAuthCallback(
        'kivicare://oauth/callback?code=kivicare_code',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'kivicare_code');
    });

    test('works with https:// scheme (server-side redirect)', () {
      final result = OAuthService.parseOAuthCallback(
        'https://example.com/oauth/callback?code=https_code&state=s1',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'https_code');
      expect(result!['state'], 's1');
    });

    // --- Empty and edge cases ---
    test('empty URL string returns null (no code or error in empty path)', () {
      final result = OAuthService.parseOAuthCallback('');
      expect(result, isNull);
    });

    test('URL with empty code returns code as empty string', () {
      // Uri.parse will have code="" which is not null, so it should return it
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=',
      );
      // The implementation checks `if (code != null)` -- empty string is not null
      expect(result, isNotNull);
      expect(result!['code'], '');
    });

    test('URL with empty error returns error as empty string', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?error=',
      );
      // error="" is not null, so implementation returns {'error': ''}
      expect(result, isNotNull);
      expect(result!['error'], '');
    });

    test('handles URL with fragment identifier', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=frag_code#fragment',
      );
      expect(result, isNotNull);
      expect(result!['code'], 'frag_code');
    });

    // --- Result map structure ---
    test('successful result contains exactly code and state keys', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=test&state=s',
      );
      expect(result, isNotNull);
      expect(result!.keys.toSet(), {'code', 'state'});
    });

    test('error result contains exactly error key', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?error=denied',
      );
      expect(result, isNotNull);
      expect(result!.keys.toSet(), {'error'});
    });

    // --- Long and complex values ---
    test('handles long authorization code', () {
      final longCode = 'a' * 500;
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=$longCode',
      );
      expect(result, isNotNull);
      expect(result!['code'], longCode);
      expect(result!['code']!.length, 500);
    });

    test('handles state with URL-encoded characters', () {
      final result = OAuthService.parseOAuthCallback(
        'wellness://oauth/callback?code=c&state=a%20b%26c%3Dd',
      );
      expect(result, isNotNull);
      expect(result!['state'], 'a b&c=d');
    });
  });
}

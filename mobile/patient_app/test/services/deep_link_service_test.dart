// Unit tests for DeepLinkService URL parsing logic.
//
// Since the actual navigation methods (Get.to, Get.offAll) require a full
// Flutter app with GetMaterialApp, these tests verify the URL parsing and
// route extraction logic that DeepLinkService relies on internally.
//
// The service parses URIs as follows:
//   wellness://appointment/123 -> host="appointment", pathSegments=["123"]
//   kivicare://webview?url=...  -> host="webview", queryParameters={url: ...}
//
// We replicate this parsing logic in tests to ensure correctness.

import 'package:flutter_test/flutter_test.dart';

void main() {
  // -------------------------------------------------------------------
  // Helper: parse a deep link URI and return its components
  // Mirrors how DeepLinkService.handleDeepLink extracts host, segments,
  // and query parameters.
  // -------------------------------------------------------------------
  Map<String, dynamic> parseDeepLink(String url) {
    final uri = Uri.parse(url);
    return {
      'scheme': uri.scheme,
      'host': uri.host,
      'pathSegments': uri.pathSegments,
      'queryParameters': uri.queryParameters,
    };
  }

  /// Mirrors DeepLinkService._extractId
  int? extractId(List<String> pathSegments) {
    if (pathSegments.isEmpty) return null;
    return int.tryParse(pathSegments.first);
  }

  /// Mirrors DeepLinkService._isSupportedScheme
  bool isSupportedScheme(String scheme) {
    return ['wellness', 'kivicare'].contains(scheme.toLowerCase());
  }

  // =====================================================================
  // GROUP: Supported URL Schemes
  // =====================================================================
  group('Supported URL schemes', () {
    test('wellness:// scheme is supported', () {
      expect(isSupportedScheme('wellness'), isTrue);
    });

    test('kivicare:// scheme is supported', () {
      expect(isSupportedScheme('kivicare'), isTrue);
    });

    test('schemes are case-insensitive', () {
      expect(isSupportedScheme('WELLNESS'), isTrue);
      expect(isSupportedScheme('KiviCare'), isTrue);
      expect(isSupportedScheme('KIVICARE'), isTrue);
      expect(isSupportedScheme('Wellness'), isTrue);
    });

    test('http:// scheme is not supported', () {
      expect(isSupportedScheme('http'), isFalse);
    });

    test('https:// scheme is not supported', () {
      expect(isSupportedScheme('https'), isFalse);
    });

    test('empty scheme is not supported', () {
      expect(isSupportedScheme(''), isFalse);
    });

    test('arbitrary schemes are not supported', () {
      expect(isSupportedScheme('myapp'), isFalse);
      expect(isSupportedScheme('ftp'), isFalse);
      expect(isSupportedScheme('mailto'), isFalse);
    });
  });

  // =====================================================================
  // GROUP: Dashboard Route
  // =====================================================================
  group('Dashboard route parsing', () {
    test('wellness://dashboard parses correctly', () {
      final result = parseDeepLink('wellness://dashboard');
      expect(result['scheme'], 'wellness');
      expect(result['host'], 'dashboard');
      expect((result['pathSegments'] as List).isEmpty, isTrue);
    });

    test('kivicare://dashboard parses correctly', () {
      final result = parseDeepLink('kivicare://dashboard');
      expect(result['scheme'], 'kivicare');
      expect(result['host'], 'dashboard');
    });
  });

  // =====================================================================
  // GROUP: Appointment Route (appointment/{id})
  // =====================================================================
  group('Appointment route parsing', () {
    test('wellness://appointment/123 extracts host and ID', () {
      final result = parseDeepLink('wellness://appointment/123');
      expect(result['host'], 'appointment');
      final segments = result['pathSegments'] as List;
      expect(segments, ['123']);
      expect(extractId(segments.cast<String>()), 123);
    });

    test('kivicare://appointment/456 extracts ID correctly', () {
      final result = parseDeepLink('kivicare://appointment/456');
      expect(result['host'], 'appointment');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 456);
    });

    test('appointment with large ID parses correctly', () {
      final result = parseDeepLink('wellness://appointment/999999');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 999999);
    });

    test('appointment with zero ID parses correctly', () {
      final result = parseDeepLink('wellness://appointment/0');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 0);
    });

    test('appointment without ID returns empty segments', () {
      final result = parseDeepLink('wellness://appointment');
      final segments = result['pathSegments'] as List;
      expect(segments.isEmpty, isTrue);
      expect(extractId(segments.cast<String>()), isNull);
    });

    test('appointment with non-numeric ID returns null from extractId', () {
      final result = parseDeepLink('wellness://appointment/abc');
      final segments = result['pathSegments'] as List;
      expect(segments, ['abc']);
      expect(extractId(segments.cast<String>()), isNull);
    });

    test('appointment with negative ID parses as negative integer', () {
      // wellness://appointment/-1 -> pathSegments = ["-1"]
      final result = parseDeepLink('wellness://appointment/-1');
      final segments = result['pathSegments'] as List;
      // int.tryParse("-1") returns -1, which is technically valid
      expect(extractId(segments.cast<String>()), -1);
    });
  });

  // =====================================================================
  // GROUP: Clinic Route (clinic/{id})
  // =====================================================================
  group('Clinic route parsing', () {
    test('wellness://clinic/10 extracts host and ID', () {
      final result = parseDeepLink('wellness://clinic/10');
      expect(result['host'], 'clinic');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 10);
    });

    test('kivicare://clinic/200 extracts ID correctly', () {
      final result = parseDeepLink('kivicare://clinic/200');
      expect(result['host'], 'clinic');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 200);
    });

    test('clinic without ID returns null', () {
      final result = parseDeepLink('wellness://clinic');
      expect(extractId((result['pathSegments'] as List).cast<String>()), isNull);
    });
  });

  // =====================================================================
  // GROUP: Doctor Route (doctor/{id})
  // =====================================================================
  group('Doctor route parsing', () {
    test('wellness://doctor/42 extracts host and ID', () {
      final result = parseDeepLink('wellness://doctor/42');
      expect(result['host'], 'doctor');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 42);
    });

    test('kivicare://doctor/1 extracts ID correctly', () {
      final result = parseDeepLink('kivicare://doctor/1');
      expect(result['host'], 'doctor');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 1);
    });

    test('doctor with text ID returns null from extractId', () {
      final result = parseDeepLink('wellness://doctor/dr-smith');
      expect(extractId((result['pathSegments'] as List).cast<String>()), isNull);
    });
  });

  // =====================================================================
  // GROUP: Booking Route
  // =====================================================================
  group('Booking route parsing', () {
    test('wellness://booking parses correctly with no segments', () {
      final result = parseDeepLink('wellness://booking');
      expect(result['host'], 'booking');
      expect((result['pathSegments'] as List).isEmpty, isTrue);
    });

    test('kivicare://booking parses correctly', () {
      final result = parseDeepLink('kivicare://booking');
      expect(result['host'], 'booking');
    });
  });

  // =====================================================================
  // GROUP: Service Route (service/{id})
  // =====================================================================
  group('Service route parsing', () {
    test('wellness://service/77 extracts host and ID', () {
      final result = parseDeepLink('wellness://service/77');
      expect(result['host'], 'service');
      expect(extractId((result['pathSegments'] as List).cast<String>()), 77);
    });

    test('service without ID returns null', () {
      final result = parseDeepLink('wellness://service');
      expect(extractId((result['pathSegments'] as List).cast<String>()), isNull);
    });
  });

  // =====================================================================
  // GROUP: Profile Route
  // =====================================================================
  group('Profile route parsing', () {
    test('wellness://profile parses correctly', () {
      final result = parseDeepLink('wellness://profile');
      expect(result['host'], 'profile');
      expect((result['pathSegments'] as List).isEmpty, isTrue);
    });
  });

  // =====================================================================
  // GROUP: Settings Route
  // =====================================================================
  group('Settings route parsing', () {
    test('wellness://settings parses correctly', () {
      final result = parseDeepLink('wellness://settings');
      expect(result['host'], 'settings');
      expect((result['pathSegments'] as List).isEmpty, isTrue);
    });
  });

  // =====================================================================
  // GROUP: WebView Route (webview?url=)
  // =====================================================================
  group('WebView route parsing', () {
    test('wellness://webview?url=https://example.com extracts URL', () {
      final result = parseDeepLink('wellness://webview?url=https%3A%2F%2Fexample.com');
      expect(result['host'], 'webview');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], 'https://example.com');
    });

    test('webview with url and type parameters', () {
      final result = parseDeepLink(
          'wellness://webview?url=https%3A%2F%2Fexample.com%2Fpage&type=sns');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], 'https://example.com/page');
      expect(params['type'], 'sns');
    });

    test('webview without url parameter has empty query params for url', () {
      final result = parseDeepLink('wellness://webview');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], isNull);
    });

    test('webview with empty url parameter', () {
      final result = parseDeepLink('wellness://webview?url=');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], '');
    });

    test('webview URL decoding works with Korean characters', () {
      // Korean URL encoded: https://example.com/한국어
      final encodedUrl = Uri.encodeComponent('https://example.com/한국어');
      final result = parseDeepLink('wellness://webview?url=$encodedUrl');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], 'https://example.com/한국어');
    });

    test('webview URL with multiple query parameters in target URL', () {
      final targetUrl = 'https://example.com/page?foo=bar&baz=qux';
      final encodedUrl = Uri.encodeComponent(targetUrl);
      final result = parseDeepLink('wellness://webview?url=$encodedUrl');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['url'], targetUrl);
    });
  });

  // =====================================================================
  // GROUP: OAuth Callback Route (oauth/callback)
  // =====================================================================
  group('OAuth callback route parsing', () {
    test('wellness://oauth/callback?code=abc123 extracts code', () {
      final result = parseDeepLink('wellness://oauth/callback?code=abc123');
      expect(result['host'], 'oauth');
      final segments = result['pathSegments'] as List;
      expect(segments, ['callback']);
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['code'], 'abc123');
    });

    test('oauth callback with code and state', () {
      final result = parseDeepLink(
          'wellness://oauth/callback?code=mycode&state=mystate');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['code'], 'mycode');
      expect(params['state'], 'mystate');
    });

    test('oauth callback with error parameter', () {
      final result = parseDeepLink(
          'wellness://oauth/callback?error=access_denied');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['error'], 'access_denied');
    });

    test('oauth callback without code or error', () {
      final result = parseDeepLink('wellness://oauth/callback');
      final params = result['queryParameters'] as Map<String, String>;
      expect(params['code'], isNull);
      expect(params['error'], isNull);
    });

    test('oauth with non-callback path is detected', () {
      final result = parseDeepLink('wellness://oauth/other');
      expect(result['host'], 'oauth');
      final segments = result['pathSegments'] as List;
      expect(segments.first, 'other');
      // The service checks segments.first == 'callback' and would reject this
      expect(segments.first != 'callback', isTrue);
    });

    test('oauth without any path segments', () {
      final result = parseDeepLink('wellness://oauth');
      expect(result['host'], 'oauth');
      final segments = result['pathSegments'] as List;
      expect(segments.isEmpty, isTrue);
    });
  });

  // =====================================================================
  // GROUP: ID Extraction Edge Cases
  // =====================================================================
  group('ID extraction edge cases', () {
    test('empty path segments returns null', () {
      expect(extractId([]), isNull);
    });

    test('valid integer string returns integer', () {
      expect(extractId(['123']), 123);
    });

    test('zero returns 0', () {
      expect(extractId(['0']), 0);
    });

    test('very large integer parses correctly', () {
      expect(extractId(['2147483647']), 2147483647);
    });

    test('decimal string returns null', () {
      expect(extractId(['12.5']), isNull);
    });

    test('empty string returns null', () {
      expect(extractId(['']), isNull);
    });

    test('string with leading/trailing spaces is parsed by int.tryParse', () {
      // Note: Dart int.tryParse trims whitespace, so ' 123 ' parses to 123.
      // In practice, URI path segments from deep links will never contain
      // raw spaces (they would be percent-encoded as %20).
      expect(extractId([' 123 ']), 123);
    });

    test('alphanumeric string returns null', () {
      expect(extractId(['id123']), isNull);
    });

    test('negative integer string returns negative', () {
      expect(extractId(['-5']), -5);
    });

    test('only first segment is used for ID extraction', () {
      expect(extractId(['42', '99', '100']), 42);
    });

    test('string with only whitespace returns null', () {
      expect(extractId(['   ']), isNull);
    });

    test('hexadecimal 0x-prefixed string is parsed by int.tryParse', () {
      // Note: Dart int.tryParse supports 0x prefix for hexadecimal.
      // '0xFF' parses to 255. In practice, deep link IDs are always
      // decimal integers, but this documents the Dart behavior.
      expect(extractId(['0xFF']), 255);
    });

    test('string with comma-separated numbers returns null', () {
      expect(extractId(['1,000']), isNull);
    });

    test('plain alphabetic string returns null', () {
      expect(extractId(['abc']), isNull);
    });

    test('string with special characters returns null', () {
      expect(extractId(['12@34']), isNull);
    });
  });

  // =====================================================================
  // GROUP: Unknown/Invalid Routes
  // =====================================================================
  group('Unknown and invalid routes', () {
    test('unknown host is detected', () {
      final result = parseDeepLink('wellness://unknown');
      expect(result['host'], 'unknown');
      // The service would fall into default case and show toast
    });

    test('empty URI path returns empty host', () {
      final result = parseDeepLink('wellness://');
      expect(result['host'], '');
    });

    test('scheme with path but no host', () {
      // In Dart Uri parsing, wellness:///path -> host="", pathSegments=["path"]
      final uri = Uri.parse('wellness:///path');
      expect(uri.host, '');
      expect(uri.pathSegments, ['path']);
    });

    test('unsupported scheme with valid path structure', () {
      final result = parseDeepLink('https://appointment/123');
      expect(isSupportedScheme(result['scheme'] as String), isFalse);
    });
  });

  // =====================================================================
  // GROUP: URI Component Consistency
  // =====================================================================
  group('URI component consistency between schemes', () {
    test('same route produces same host/segments across both schemes', () {
      final wellness = parseDeepLink('wellness://appointment/55');
      final kivicare = parseDeepLink('kivicare://appointment/55');

      expect(wellness['host'], kivicare['host']);
      expect(
        (wellness['pathSegments'] as List).first,
        (kivicare['pathSegments'] as List).first,
      );
    });

    test('query parameters are identical across schemes', () {
      final wellness = parseDeepLink(
          'wellness://webview?url=https%3A%2F%2Ftest.com&type=general');
      final kivicare = parseDeepLink(
          'kivicare://webview?url=https%3A%2F%2Ftest.com&type=general');

      expect(wellness['queryParameters'], kivicare['queryParameters']);
    });
  });

  // =====================================================================
  // GROUP: handleDeepLinkFromString edge cases
  // =====================================================================
  group('String-based deep link edge cases', () {
    test('valid URL string can be parsed', () {
      // Simulates what handleDeepLinkFromString does
      const url = 'wellness://appointment/100';
      expect(url.isNotEmpty, isTrue);
      final uri = Uri.parse(url);
      expect(uri.scheme, 'wellness');
      expect(uri.host, 'appointment');
    });

    test('empty string is detected before parsing', () {
      const url = '';
      expect(url.isEmpty, isTrue);
      // handleDeepLinkFromString returns early on empty strings
    });

    test('malformed URL does not throw with Uri.parse', () {
      // Uri.parse is lenient and does not throw on most inputs
      expect(() => Uri.parse('not a url'), returnsNormally);
    });

    test('URL with percent-encoded space in path', () {
      final result = parseDeepLink('wellness://appointment/123%20456');
      final segments = result['pathSegments'] as List;
      // The segment will be the decoded value "123 456"
      expect(segments.first, '123 456');
      // extractId will fail since it contains an embedded space character
      // (int.tryParse("123 456") returns null for strings with internal spaces)
      expect(extractId(segments.cast<String>()), isNull);
    });
  });

  // =====================================================================
  // GROUP: Multiple path segments
  // =====================================================================
  group('Multiple path segments handling', () {
    test('extra path segments after ID are parsed but ignored by extractId', () {
      final result = parseDeepLink('wellness://appointment/123/details');
      final segments = result['pathSegments'] as List;
      expect(segments.length, 2);
      expect(segments[0], '123');
      expect(segments[1], 'details');
      // extractId only looks at first segment
      expect(extractId(segments.cast<String>()), 123);
    });

    test('trailing slash produces empty last segment or is ignored', () {
      final result = parseDeepLink('wellness://appointment/123/');
      final segments = result['pathSegments'] as List;
      // Dart Uri.parse typically strips trailing empty segments
      // but behavior may vary; verify the ID is still extractable
      expect(extractId(segments.cast<String>()), 123);
    });
  });
}

// KiviCare Patient App - Test entry point
//
// This file replaces the default Flutter counter boilerplate test.
// The actual tests are organized in subdirectories:
//
//   test/services/deep_link_service_test.dart  - Deep link URL parsing tests
//   test/services/oauth_service_test.dart      - OAuth URL building/callback tests
//   test/utils/korean_formatters_test.dart     - Korean formatting utility tests
//
// Run all tests:  flutter test
// Run a specific file:  flutter test test/utils/korean_formatters_test.dart

import 'package:flutter_test/flutter_test.dart';

void main() {
  group('KiviCare Patient App - Smoke Tests', () {
    test('Uri.parse handles wellness:// scheme correctly', () {
      final uri = Uri.parse('wellness://dashboard');
      expect(uri.scheme, 'wellness');
      expect(uri.host, 'dashboard');
    });

    test('Uri.parse handles kivicare:// scheme correctly', () {
      final uri = Uri.parse('kivicare://appointment/42');
      expect(uri.scheme, 'kivicare');
      expect(uri.host, 'appointment');
      expect(uri.pathSegments.first, '42');
    });

    test('int.tryParse returns null for invalid input', () {
      expect(int.tryParse('abc'), isNull);
      expect(int.tryParse('123'), 123);
    });

    test('DateTime weekday values are 1-7 (Monday-Sunday)', () {
      final monday = DateTime(2025, 1, 6);
      expect(monday.weekday, 1);
      final sunday = DateTime(2025, 1, 12);
      expect(sunday.weekday, 7);
    });
  });
}

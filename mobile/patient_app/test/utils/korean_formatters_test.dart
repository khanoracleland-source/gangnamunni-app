// Unit tests for KoreanFormatters utility class.
//
// Tests all static formatting and validation methods with comprehensive
// edge case coverage including boundary conditions, empty inputs,
// and Korean-specific formatting rules.

import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/utils/korean/korean_formatters.dart';

void main() {
  // =====================================================================
  // GROUP: formatPhoneNumber
  // =====================================================================
  group('formatPhoneNumber', () {
    // --- Standard mobile numbers (010) ---
    test('formats 11-digit mobile number 01012345678 -> 010-1234-5678', () {
      expect(KoreanFormatters.formatPhoneNumber('01012345678'), '010-1234-5678');
    });

    test('formats 010 number with different digits', () {
      expect(KoreanFormatters.formatPhoneNumber('01098765432'), '010-9876-5432');
    });

    test('formats 010 number starting with zeros in middle', () {
      expect(KoreanFormatters.formatPhoneNumber('01000001111'), '010-0000-1111');
    });

    // --- Country code handling (82) ---
    test('strips 82 country code and formats: 821012345678 -> 010-1234-5678', () {
      expect(KoreanFormatters.formatPhoneNumber('821012345678'), '010-1234-5678');
    });

    test('handles +82 with non-digit stripping', () {
      expect(KoreanFormatters.formatPhoneNumber('+821012345678'), '010-1234-5678');
    });

    test('handles 82-10-1234-5678 format with dashes', () {
      expect(KoreanFormatters.formatPhoneNumber('82-10-1234-5678'), '010-1234-5678');
    });

    // --- Seoul numbers (02) ---
    test('formats 9-digit Seoul number: 021234567 -> 02-123-4567', () {
      expect(KoreanFormatters.formatPhoneNumber('021234567'), '02-123-4567');
    });

    test('formats 10-digit Seoul number: 0212345678 -> 02-1234-5678', () {
      expect(KoreanFormatters.formatPhoneNumber('0212345678'), '02-1234-5678');
    });

    // --- Other area codes (10-digit) ---
    test('formats 10-digit local number: 0311234567 -> 031-123-4567', () {
      expect(KoreanFormatters.formatPhoneNumber('0311234567'), '031-123-4567');
    });

    test('formats 10-digit Busan number: 0511234567 -> 051-123-4567', () {
      expect(KoreanFormatters.formatPhoneNumber('0511234567'), '051-123-4567');
    });

    // --- Pre-formatted input (with dashes/spaces) ---
    test('handles already-formatted number with dashes', () {
      expect(KoreanFormatters.formatPhoneNumber('010-1234-5678'), '010-1234-5678');
    });

    test('handles number with spaces', () {
      expect(KoreanFormatters.formatPhoneNumber('010 1234 5678'), '010-1234-5678');
    });

    test('handles number with parentheses and dashes', () {
      expect(KoreanFormatters.formatPhoneNumber('(010) 1234-5678'), '010-1234-5678');
    });

    // --- Edge cases ---
    test('returns raw digits for unsupported length', () {
      // 8 digits - does not match any known pattern
      expect(KoreanFormatters.formatPhoneNumber('12345678'), '12345678');
    });

    test('returns empty string for empty input', () {
      expect(KoreanFormatters.formatPhoneNumber(''), '');
    });

    test('returns empty string for non-digit input', () {
      expect(KoreanFormatters.formatPhoneNumber('abcdef'), '');
    });

    test('handles number with leading/trailing whitespace after cleaning', () {
      expect(KoreanFormatters.formatPhoneNumber(' 01012345678 '), '010-1234-5678');
    });

    // --- Old mobile prefixes ---
    test('formats 011 number (11 digits) - old mobile prefix', () {
      // 011 is 11 digits: 01112345678
      // startsWith('010') is false, so it won't match the 010 branch
      // It falls through to return raw digits
      expect(KoreanFormatters.formatPhoneNumber('01112345678'), '01112345678');
    });
  });

  // =====================================================================
  // GROUP: formatCurrency
  // =====================================================================
  group('formatCurrency', () {
    test('formats 50000 -> "50,000원" with symbol', () {
      expect(KoreanFormatters.formatCurrency(50000), '50,000원');
    });

    test('formats 50000 -> "50,000" without symbol', () {
      expect(KoreanFormatters.formatCurrency(50000, includeSymbol: false), '50,000');
    });

    test('formats zero amount', () {
      expect(KoreanFormatters.formatCurrency(0), '0원');
    });

    test('formats single digit amount', () {
      expect(KoreanFormatters.formatCurrency(5), '5원');
    });

    test('formats hundreds', () {
      expect(KoreanFormatters.formatCurrency(999), '999원');
    });

    test('formats thousands with comma', () {
      expect(KoreanFormatters.formatCurrency(1000), '1,000원');
    });

    test('formats millions with commas', () {
      expect(KoreanFormatters.formatCurrency(1000000), '1,000,000원');
    });

    test('formats large amount with commas', () {
      expect(KoreanFormatters.formatCurrency(123456789), '123,456,789원');
    });

    test('truncates decimal part (toInt)', () {
      // The method calls amount.toInt(), so 50000.99 -> 50000
      expect(KoreanFormatters.formatCurrency(50000.99), '50,000원');
    });

    test('truncates small decimal amount', () {
      expect(KoreanFormatters.formatCurrency(0.5), '0원');
    });

    test('formats negative amount', () {
      // NumberFormat handles negatives with a minus sign
      expect(KoreanFormatters.formatCurrency(-5000), '-5,000원');
    });

    test('formats negative amount without symbol', () {
      expect(KoreanFormatters.formatCurrency(-5000, includeSymbol: false), '-5,000');
    });

    test('includeSymbol defaults to true', () {
      final result = KoreanFormatters.formatCurrency(100);
      expect(result.endsWith('원'), isTrue);
    });
  });

  // =====================================================================
  // GROUP: formatDate
  // =====================================================================
  group('formatDate', () {
    test('formats standard date: 2025-01-14 -> 2025년 1월 14일', () {
      final date = DateTime(2025, 1, 14);
      expect(KoreanFormatters.formatDate(date), '2025년 1월 14일');
    });

    test('formats date with double-digit month and day', () {
      final date = DateTime(2025, 12, 31);
      expect(KoreanFormatters.formatDate(date), '2025년 12월 31일');
    });

    test('formats date at start of year', () {
      final date = DateTime(2025, 1, 1);
      expect(KoreanFormatters.formatDate(date), '2025년 1월 1일');
    });

    test('formats leap year date: 2024-02-29', () {
      final date = DateTime(2024, 2, 29);
      expect(KoreanFormatters.formatDate(date), '2024년 2월 29일');
    });

    test('formats date in far past', () {
      final date = DateTime(1900, 6, 15);
      expect(KoreanFormatters.formatDate(date), '1900년 6월 15일');
    });

    test('formats date in far future', () {
      final date = DateTime(2100, 3, 1);
      expect(KoreanFormatters.formatDate(date), '2100년 3월 1일');
    });

    test('single-digit month has no leading zero', () {
      final date = DateTime(2025, 3, 5);
      final result = KoreanFormatters.formatDate(date);
      expect(result, '2025년 3월 5일');
      // Ensure there is no leading zero
      expect(result.contains('03'), isFalse);
    });
  });

  // =====================================================================
  // GROUP: formatTime
  // =====================================================================
  group('formatTime', () {
    test('formats afternoon time: 14:30 -> 오후 2시 30분', () {
      final time = DateTime(2025, 1, 1, 14, 30);
      expect(KoreanFormatters.formatTime(time), '오후 2시 30분');
    });

    test('formats morning time: 09:05 -> 오전 9시 05분', () {
      final time = DateTime(2025, 1, 1, 9, 5);
      expect(KoreanFormatters.formatTime(time), '오전 9시 05분');
    });

    test('formats midnight: 00:00 -> 오전 12시 00분', () {
      final time = DateTime(2025, 1, 1, 0, 0);
      expect(KoreanFormatters.formatTime(time), '오전 12시 00분');
    });

    test('formats noon: 12:00 -> 오후 12시 00분', () {
      final time = DateTime(2025, 1, 1, 12, 0);
      expect(KoreanFormatters.formatTime(time), '오후 12시 00분');
    });

    test('formats 1 AM: 01:00 -> 오전 1시 00분', () {
      final time = DateTime(2025, 1, 1, 1, 0);
      expect(KoreanFormatters.formatTime(time), '오전 1시 00분');
    });

    test('formats 11 AM: 11:59 -> 오전 11시 59분', () {
      final time = DateTime(2025, 1, 1, 11, 59);
      expect(KoreanFormatters.formatTime(time), '오전 11시 59분');
    });

    test('formats 1 PM: 13:00 -> 오후 1시 00분', () {
      final time = DateTime(2025, 1, 1, 13, 0);
      expect(KoreanFormatters.formatTime(time), '오후 1시 00분');
    });

    test('formats 11 PM: 23:59 -> 오후 11시 59분', () {
      final time = DateTime(2025, 1, 1, 23, 59);
      expect(KoreanFormatters.formatTime(time), '오후 11시 59분');
    });

    test('minutes are zero-padded: 14:05 -> 오후 2시 05분', () {
      final time = DateTime(2025, 1, 1, 14, 5);
      expect(KoreanFormatters.formatTime(time), '오후 2시 05분');
    });

    test('minute zero is shown as 00: 10:00 -> 오전 10시 00분', () {
      final time = DateTime(2025, 1, 1, 10, 0);
      expect(KoreanFormatters.formatTime(time), '오전 10시 00분');
    });

    // Boundary: exactly at period change
    test('11:59 is still 오전', () {
      final time = DateTime(2025, 1, 1, 11, 59);
      expect(KoreanFormatters.formatTime(time).startsWith('오전'), isTrue);
    });

    test('12:00 is 오후', () {
      final time = DateTime(2025, 1, 1, 12, 0);
      expect(KoreanFormatters.formatTime(time).startsWith('오후'), isTrue);
    });
  });

  // =====================================================================
  // GROUP: formatDateTime
  // =====================================================================
  group('formatDateTime', () {
    test('combines date and time: 2025-01-14 14:30', () {
      final dt = DateTime(2025, 1, 14, 14, 30);
      expect(
        KoreanFormatters.formatDateTime(dt),
        '2025년 1월 14일 오후 2시 30분',
      );
    });

    test('combines date and time for midnight', () {
      final dt = DateTime(2025, 12, 31, 0, 0);
      expect(
        KoreanFormatters.formatDateTime(dt),
        '2025년 12월 31일 오전 12시 00분',
      );
    });

    test('combines date and time for noon', () {
      final dt = DateTime(2025, 6, 15, 12, 0);
      expect(
        KoreanFormatters.formatDateTime(dt),
        '2025년 6월 15일 오후 12시 00분',
      );
    });

    test('result equals formatDate + space + formatTime', () {
      final dt = DateTime(2025, 3, 10, 9, 45);
      final expected =
          '${KoreanFormatters.formatDate(dt)} ${KoreanFormatters.formatTime(dt)}';
      expect(KoreanFormatters.formatDateTime(dt), expected);
    });
  });

  // =====================================================================
  // GROUP: formatBusinessNumber
  // =====================================================================
  group('formatBusinessNumber', () {
    test('formats 10-digit number: 1234567890 -> 123-45-67890', () {
      expect(KoreanFormatters.formatBusinessNumber('1234567890'), '123-45-67890');
    });

    test('formats number with existing dashes: 123-45-67890 -> 123-45-67890', () {
      expect(KoreanFormatters.formatBusinessNumber('123-45-67890'), '123-45-67890');
    });

    test('formats number with spaces', () {
      expect(KoreanFormatters.formatBusinessNumber('123 45 67890'), '123-45-67890');
    });

    test('returns raw digits for incorrect length (9 digits)', () {
      expect(KoreanFormatters.formatBusinessNumber('123456789'), '123456789');
    });

    test('returns raw digits for incorrect length (11 digits)', () {
      expect(KoreanFormatters.formatBusinessNumber('12345678901'), '12345678901');
    });

    test('returns empty string for empty input', () {
      expect(KoreanFormatters.formatBusinessNumber(''), '');
    });

    test('returns empty string for non-digit input', () {
      expect(KoreanFormatters.formatBusinessNumber('abcdefghij'), '');
    });

    test('all zeros: 0000000000 -> 000-00-00000', () {
      expect(KoreanFormatters.formatBusinessNumber('0000000000'), '000-00-00000');
    });
  });

  // =====================================================================
  // GROUP: isValidPhoneNumber
  // =====================================================================
  group('isValidPhoneNumber', () {
    // --- Valid mobile numbers ---
    test('010 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01012345678'), isTrue);
    });

    test('011 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01112345678'), isTrue);
    });

    test('016 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01612345678'), isTrue);
    });

    test('017 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01712345678'), isTrue);
    });

    test('018 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01812345678'), isTrue);
    });

    test('019 number (11 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('01912345678'), isTrue);
    });

    test('formatted 010 number with dashes is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('010-1234-5678'), isTrue);
    });

    // --- Valid Seoul numbers ---
    test('Seoul 9-digit number (02XXXXXXX) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('021234567'), isTrue);
    });

    test('Seoul 10-digit number (02XXXXXXXX) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('0212345678'), isTrue);
    });

    // --- Valid area code numbers ---
    test('031 area code (10 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('0311234567'), isTrue);
    });

    test('051 area code (10 digits) is valid', () {
      expect(KoreanFormatters.isValidPhoneNumber('0511234567'), isTrue);
    });

    // --- Invalid numbers ---
    test('too short number (7 digits) is invalid', () {
      expect(KoreanFormatters.isValidPhoneNumber('1234567'), isFalse);
    });

    test('too long number (12 digits) is invalid', () {
      expect(KoreanFormatters.isValidPhoneNumber('010123456789'), isFalse);
    });

    test('empty string is invalid', () {
      expect(KoreanFormatters.isValidPhoneNumber(''), isFalse);
    });

    test('non-digit string is invalid', () {
      expect(KoreanFormatters.isValidPhoneNumber('abcdefghijk'), isFalse);
    });

    test('number not starting with 0 is invalid (10 digits)', () {
      // 10-digit number not starting with 0
      expect(KoreanFormatters.isValidPhoneNumber('1234567890'), isFalse);
    });

    test('11-digit number not starting with 01 is invalid', () {
      expect(KoreanFormatters.isValidPhoneNumber('02123456789'), isFalse);
    });
  });

  // =====================================================================
  // GROUP: getDayOfWeek
  // =====================================================================
  group('getDayOfWeek', () {
    test('Monday returns 월요일', () {
      // 2025-01-06 is a Monday
      final date = DateTime(2025, 1, 6);
      expect(date.weekday, DateTime.monday);
      expect(KoreanFormatters.getDayOfWeek(date), '월요일');
    });

    test('Tuesday returns 화요일', () {
      final date = DateTime(2025, 1, 7);
      expect(date.weekday, DateTime.tuesday);
      expect(KoreanFormatters.getDayOfWeek(date), '화요일');
    });

    test('Wednesday returns 수요일', () {
      final date = DateTime(2025, 1, 8);
      expect(date.weekday, DateTime.wednesday);
      expect(KoreanFormatters.getDayOfWeek(date), '수요일');
    });

    test('Thursday returns 목요일', () {
      final date = DateTime(2025, 1, 9);
      expect(date.weekday, DateTime.thursday);
      expect(KoreanFormatters.getDayOfWeek(date), '목요일');
    });

    test('Friday returns 금요일', () {
      final date = DateTime(2025, 1, 10);
      expect(date.weekday, DateTime.friday);
      expect(KoreanFormatters.getDayOfWeek(date), '금요일');
    });

    test('Saturday returns 토요일', () {
      final date = DateTime(2025, 1, 11);
      expect(date.weekday, DateTime.saturday);
      expect(KoreanFormatters.getDayOfWeek(date), '토요일');
    });

    test('Sunday returns 일요일', () {
      final date = DateTime(2025, 1, 12);
      expect(date.weekday, DateTime.sunday);
      expect(KoreanFormatters.getDayOfWeek(date), '일요일');
    });

    test('all 7 days are unique', () {
      final days = <String>{};
      for (int i = 6; i <= 12; i++) {
        days.add(KoreanFormatters.getDayOfWeek(DateTime(2025, 1, i)));
      }
      expect(days.length, 7);
    });

    test('all day names end with 요일', () {
      for (int i = 6; i <= 12; i++) {
        final dayName = KoreanFormatters.getDayOfWeek(DateTime(2025, 1, i));
        expect(dayName.endsWith('요일'), isTrue, reason: '$dayName should end with 요일');
      }
    });
  });

  // =====================================================================
  // GROUP: parsePhoneNumber
  // =====================================================================
  group('parsePhoneNumber', () {
    test('strips dashes', () {
      expect(KoreanFormatters.parsePhoneNumber('010-1234-5678'), '01012345678');
    });

    test('strips spaces', () {
      expect(KoreanFormatters.parsePhoneNumber('010 1234 5678'), '01012345678');
    });

    test('strips parentheses', () {
      expect(KoreanFormatters.parsePhoneNumber('(010)12345678'), '01012345678');
    });

    test('strips plus sign', () {
      expect(KoreanFormatters.parsePhoneNumber('+821012345678'), '821012345678');
    });

    test('returns empty string for alphabetic input', () {
      expect(KoreanFormatters.parsePhoneNumber('abcdef'), '');
    });

    test('returns empty string for empty input', () {
      expect(KoreanFormatters.parsePhoneNumber(''), '');
    });

    test('preserves only digits from mixed input', () {
      expect(KoreanFormatters.parsePhoneNumber('abc010def1234ghi5678'), '01012345678');
    });
  });

  // =====================================================================
  // GROUP: isValidEmail
  // =====================================================================
  group('isValidEmail', () {
    test('valid standard email', () {
      expect(KoreanFormatters.isValidEmail('user@example.com'), isTrue);
    });

    test('valid email with subdomain', () {
      expect(KoreanFormatters.isValidEmail('user@mail.example.com'), isTrue);
    });

    test('valid email with dots in local part', () {
      expect(KoreanFormatters.isValidEmail('first.last@example.com'), isTrue);
    });

    test('valid email with hyphen in domain', () {
      expect(KoreanFormatters.isValidEmail('user@my-domain.com'), isTrue);
    });

    test('invalid email without @', () {
      expect(KoreanFormatters.isValidEmail('userexample.com'), isFalse);
    });

    test('invalid email without domain', () {
      expect(KoreanFormatters.isValidEmail('user@'), isFalse);
    });

    test('invalid email without local part', () {
      expect(KoreanFormatters.isValidEmail('@example.com'), isFalse);
    });

    test('empty string is invalid', () {
      expect(KoreanFormatters.isValidEmail(''), isFalse);
    });
  });

  // =====================================================================
  // GROUP: formatResidentNumber
  // =====================================================================
  group('formatResidentNumber', () {
    test('formats and masks 13-digit number', () {
      expect(
        KoreanFormatters.formatResidentNumber('9001011234567'),
        '900101-1******',
      );
    });

    test('formats without mask when mask=false', () {
      expect(
        KoreanFormatters.formatResidentNumber('9001011234567', mask: false),
        '900101-1234567',
      );
    });

    test('handles already-formatted input with dash', () {
      expect(
        KoreanFormatters.formatResidentNumber('900101-1234567'),
        '900101-1******',
      );
    });

    test('returns raw digits for incorrect length', () {
      expect(
        KoreanFormatters.formatResidentNumber('12345'),
        '12345',
      );
    });

    test('returns empty string for empty input', () {
      expect(KoreanFormatters.formatResidentNumber(''), '');
    });
  });
}

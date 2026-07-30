import 'package:intl/intl.dart';

class KoreanFormatters {
  /// Format Korean phone number
  /// Example: 01012345678 -> 010-1234-5678
  static String formatPhoneNumber(String phone) {
    String cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');

    // Remove country code if present
    if (cleanPhone.startsWith('82')) {
      cleanPhone = '0' + cleanPhone.substring(2);
    }

    // Format based on length
    if (cleanPhone.length == 11 && cleanPhone.startsWith('010')) {
      // Mobile: 010-XXXX-XXXX
      return '${cleanPhone.substring(0, 3)}-${cleanPhone.substring(3, 7)}-${cleanPhone.substring(7)}';
    } else if (cleanPhone.length == 10) {
      // Local: 0XX-XXX-XXXX
      if (cleanPhone.startsWith('02')) {
        // Seoul: 02-XXX-XXXX or 02-XXXX-XXXX
        return cleanPhone.length == 9
            ? '${cleanPhone.substring(0, 2)}-${cleanPhone.substring(2, 5)}-${cleanPhone.substring(5)}'
            : '${cleanPhone.substring(0, 2)}-${cleanPhone.substring(2, 6)}-${cleanPhone.substring(6)}';
      } else {
        return '${cleanPhone.substring(0, 3)}-${cleanPhone.substring(3, 6)}-${cleanPhone.substring(6)}';
      }
    } else if (cleanPhone.length == 9 && cleanPhone.startsWith('02')) {
      // Seoul short: 02-XXX-XXXX
      return '${cleanPhone.substring(0, 2)}-${cleanPhone.substring(2, 5)}-${cleanPhone.substring(5)}';
    }

    return cleanPhone;
  }

  /// Format Korean Won (KRW) currency
  /// Example: 50000 -> ₩50,000 or 50,000원
  static String formatCurrency(double amount, {bool includeSymbol = true}) {
    final formatter = NumberFormat('#,###');
    final formattedAmount = formatter.format(amount.toInt());

    if (includeSymbol) {
      return '$formattedAmount원';
    } else {
      return formattedAmount;
    }
  }

  /// Format date in Korean style
  /// Example: 2025-01-14 -> 2025년 1월 14일
  static String formatDate(DateTime date) {
    return '${date.year}년 ${date.month}월 ${date.day}일';
  }

  /// Format time in Korean style
  /// Example: 14:30 -> 오후 2시 30분
  static String formatTime(DateTime time) {
    final hour = time.hour;
    final minute = time.minute;
    final period = hour < 12 ? '오전' : '오후';
    final displayHour = hour > 12 ? hour - 12 : (hour == 0 ? 12 : hour);

    return '$period $displayHour시 ${minute.toString().padLeft(2, '0')}분';
  }

  /// Format datetime in Korean style
  /// Example: 2025-01-14 14:30 -> 2025년 1월 14일 오후 2시 30분
  static String formatDateTime(DateTime dateTime) {
    return '${formatDate(dateTime)} ${formatTime(dateTime)}';
  }

  /// Format business number (사업자등록번호)
  /// Example: 1234567890 -> 123-45-67890
  static String formatBusinessNumber(String number) {
    String cleanNumber = number.replaceAll(RegExp(r'[^0-9]'), '');

    if (cleanNumber.length == 10) {
      return '${cleanNumber.substring(0, 3)}-${cleanNumber.substring(3, 5)}-${cleanNumber.substring(5)}';
    }

    return cleanNumber;
  }

  /// Format resident registration number (주민등록번호) - masked
  /// Example: 9001011234567 -> 900101-1******
  static String formatResidentNumber(String number, {bool mask = true}) {
    String cleanNumber = number.replaceAll(RegExp(r'[^0-9]'), '');

    if (cleanNumber.length == 13) {
      if (mask) {
        return '${cleanNumber.substring(0, 6)}-${cleanNumber.substring(6, 7)}******';
      } else {
        return '${cleanNumber.substring(0, 6)}-${cleanNumber.substring(6)}';
      }
    }

    return cleanNumber;
  }

  /// Parse phone number to clean digits only
  static String parsePhoneNumber(String phone) {
    return phone.replaceAll(RegExp(r'[^0-9]'), '');
  }

  /// Validate Korean phone number
  static bool isValidPhoneNumber(String phone) {
    String cleanPhone = parsePhoneNumber(phone);

    // Mobile: 010, 011, 016, 017, 018, 019
    if (cleanPhone.length == 11 && cleanPhone.startsWith('01')) {
      return true;
    }

    // Seoul: 02
    if (cleanPhone.length >= 9 &&
        cleanPhone.length <= 10 &&
        cleanPhone.startsWith('02')) {
      return true;
    }

    // Other areas: 031, 032, 051, etc.
    if (cleanPhone.length == 10 && cleanPhone.startsWith('0')) {
      return true;
    }

    return false;
  }

  /// Validate email
  static bool isValidEmail(String email) {
    return RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(email);
  }

  /// Get day of week in Korean
  static String getDayOfWeek(DateTime date) {
    const days = ['월', '화', '수', '목', '금', '토', '일'];
    return days[date.weekday - 1] + '요일';
  }
}

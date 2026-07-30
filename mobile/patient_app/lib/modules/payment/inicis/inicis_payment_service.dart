import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:nb_utils/nb_utils.dart';
import '../../../configs.dart';
import '../../../utils/constants.dart';

class InicisPaymentService {
  /// Request INICIS payment initialization
  /// Returns payment URL and transaction data
  Future<Map<String, dynamic>> requestPayment({
    required String orderNumber,
    required double amount,
    required String productName,
    required String buyerName,
    required String buyerEmail,
    required String buyerTel,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('${BASE_URL}payment/inicis/request'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${getStringAsync(TOKEN)}',
        },
        body: jsonEncode({
          'order_number': orderNumber,
          'amount': amount.toString(),
          'product_name': productName,
          'buyer_name': buyerName,
          'buyer_email': buyerEmail,
          'buyer_tel': buyerTel,
        }),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('결제 요청 실패: ${response.body}');
      }
    } catch (e) {
      throw Exception('INICIS 결제 요청 중 오류 발생: $e');
    }
  }

  /// Verify payment result from INICIS
  Future<Map<String, dynamic>> verifyPayment({
    required String tid,
    required String orderNumber,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('${BASE_URL}payment/inicis/verify'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${getStringAsync(TOKEN)}',
        },
        body: jsonEncode({
          'tid': tid,
          'order_number': orderNumber,
        }),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('결제 검증 실패: ${response.body}');
      }
    } catch (e) {
      throw Exception('INICIS 결제 검증 중 오류 발생: $e');
    }
  }

  /// Cancel payment
  Future<Map<String, dynamic>> cancelPayment({
    required String tid,
    required String orderNumber,
    required double cancelAmount,
    required String cancelReason,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('${BASE_URL}payment/inicis/cancel'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ${getStringAsync(TOKEN)}',
        },
        body: jsonEncode({
          'tid': tid,
          'order_number': orderNumber,
          'cancel_amount': cancelAmount.toString(),
          'cancel_reason': cancelReason,
        }),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('결제 취소 실패: ${response.body}');
      }
    } catch (e) {
      throw Exception('INICIS 결제 취소 중 오류 발생: $e');
    }
  }

  /// Format Korean phone number for INICIS
  /// Example: 010-1234-5678
  String formatPhoneNumber(String phone) {
    String cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');

    if (cleanPhone.startsWith('82')) {
      cleanPhone = '0' + cleanPhone.substring(2);
    }

    if (cleanPhone.length == 11 && cleanPhone.startsWith('010')) {
      return '${cleanPhone.substring(0, 3)}-${cleanPhone.substring(3, 7)}-${cleanPhone.substring(7)}';
    } else if (cleanPhone.length == 10) {
      return '${cleanPhone.substring(0, 3)}-${cleanPhone.substring(3, 6)}-${cleanPhone.substring(6)}';
    }

    return cleanPhone;
  }

  /// Format amount to Korean Won (KRW)
  String formatAmount(double amount) {
    return amount.toInt().toString();
  }
}

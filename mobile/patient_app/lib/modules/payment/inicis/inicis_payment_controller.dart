import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:intl/intl.dart';
import 'inicis_payment_service.dart';
import 'inicis_webview_screen.dart';

class InicisPaymentController extends GetxController {
  final InicisPaymentService _paymentService = InicisPaymentService();

  var isProcessing = false.obs;

  /// Start INICIS payment flow
  Future<bool> startPayment({
    required BuildContext context,
    required String orderNumber,
    required double amount,
    required String productName,
    required String buyerName,
    required String buyerEmail,
    required String buyerTel,
  }) async {
    try {
      isProcessing.value = true;

      // Format phone number for INICIS
      final formattedPhone = _paymentService.formatPhoneNumber(buyerTel);

      // Request payment from backend
      toast('결제를 준비하는 중...');

      final paymentData = await _paymentService.requestPayment(
        orderNumber: orderNumber,
        amount: amount,
        productName: productName,
        buyerName: buyerName,
        buyerEmail: buyerEmail,
        buyerTel: formattedPhone,
      );

      if (paymentData['success'] == true && paymentData['payment_url'] != null) {
        // Open WebView for payment
        final result = await Navigator.push<Map<String, dynamic>>(
          context,
          MaterialPageRoute(
            builder: (context) => InicisWebViewScreen(
              paymentUrl: paymentData['payment_url'],
              orderNumber: orderNumber,
              onPaymentResult: (success, tid) async {
                if (success && tid != null) {
                  // Verify payment
                  await _verifyAndCompletePayment(orderNumber, tid);
                }
              },
            ),
          ),
        );

        isProcessing.value = false;
        return result?['success'] ?? false;
      } else {
        throw Exception(paymentData['message'] ?? '결제 요청 실패');
      }
    } catch (e) {
      isProcessing.value = false;
      toast('결제 오류: $e', print: true);
      return false;
    }
  }

  /// Verify and complete payment
  Future<bool> _verifyAndCompletePayment(String orderNumber, String tid) async {
    try {
      toast('결제를 확인하는 중...');

      final verifyResult = await _paymentService.verifyPayment(
        tid: tid,
        orderNumber: orderNumber,
      );

      if (verifyResult['success'] == true) {
        toast('결제가 완료되었습니다', print: true);
        return true;
      } else {
        throw Exception(verifyResult['message'] ?? '결제 검증 실패');
      }
    } catch (e) {
      toast('결제 검증 오류: $e', print: true);
      return false;
    }
  }

  /// Cancel payment
  Future<bool> cancelPayment({
    required String tid,
    required String orderNumber,
    required double cancelAmount,
    required String cancelReason,
  }) async {
    try {
      isProcessing.value = true;
      toast('결제를 취소하는 중...');

      final cancelResult = await _paymentService.cancelPayment(
        tid: tid,
        orderNumber: orderNumber,
        cancelAmount: cancelAmount,
        cancelReason: cancelReason,
      );

      isProcessing.value = false;

      if (cancelResult['success'] == true) {
        toast('결제가 취소되었습니다', print: true);
        return true;
      } else {
        throw Exception(cancelResult['message'] ?? '결제 취소 실패');
      }
    } catch (e) {
      isProcessing.value = false;
      toast('결제 취소 오류: $e', print: true);
      return false;
    }
  }

  /// Generate unique order number
  String generateOrderNumber() {
    final now = DateTime.now();
    final timestamp = now.millisecondsSinceEpoch;
    return 'ORD${timestamp}';
  }

  /// Format amount for display (Korean Won)
  String formatAmountForDisplay(double amount) {
    final formatter = NumberFormat('#,###');
    return '${formatter.format(amount.toInt())}원';
  }

  @override
  void dispose() {
    isProcessing.close();
    super.dispose();
  }
}

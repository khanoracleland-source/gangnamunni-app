// PhonePe 서비스 비활성화 - 인도 UPI 결제 (한국 서비스 불필요)
// phonepe_payment_sdk 제거로 인해 stub 클래스로 대체

import 'package:flutter/material.dart';

class PhonePeServices {
  int bookingId;
  num totalAmount;
  late Function(Map<String, dynamic>) onComplete;

  PhonePeServices({
    this.totalAmount = 0,
    this.bookingId = 0,
    required this.onComplete,
  });

  Future<void> phonePeCheckout(BuildContext context) async {
    // PhonePe 비활성화 - 인도 전용 결제
    throw UnsupportedError('PhonePe is not available in this region');
  }
}

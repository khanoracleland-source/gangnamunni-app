// RazorPay 서비스 비활성화 - 인도 전용 결제 (한국 서비스 불필요)
// razorpay_flutter SDK 제거로 인해 stub 클래스로 대체

class RazorPayService {
  num totalAmount = 0;
  int bookingId = 0;
  late Function(Map<String, dynamic>) onComplete;

  void init({
    required String razorKey,
    required num totalAmount,
    required Function(Map<String, dynamic>) onComplete,
  }) {
    this.totalAmount = totalAmount;
    this.onComplete = onComplete;
  }

  Future<void> razorPayCheckout() async {
    // RazorPay 비활성화 - 인도 전용 결제
    throw UnsupportedError('RazorPay is not available in this region');
  }
}

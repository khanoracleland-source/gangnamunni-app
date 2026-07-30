class UserWalletData {
  num walletAmount;
  String message;
  bool status;

  UserWalletData({
    this.walletAmount = 0,
    this.message = "",
    this.status = false,
  });

  factory UserWalletData.fromJson(Map<String, dynamic> json) {
    return UserWalletData(
      walletAmount: json['wallet_amount'] is num ? json['wallet_amount'] : 0,
      message: json['message'] is String ? json['message'] : "",
      status: json['status'] is bool ? json['status'] : false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'wallet_amount': walletAmount,
      'message': message,
      'status': status,
    };
  }
}

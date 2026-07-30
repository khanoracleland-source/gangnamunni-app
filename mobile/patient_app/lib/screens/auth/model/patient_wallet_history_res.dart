class PatientHistoryRes {
  bool status;
  List<WalletHistoryElement> data;
  String message;

  PatientHistoryRes({
    this.status = false,
    this.data = const <WalletHistoryElement>[],
    this.message = "",
  });

  factory PatientHistoryRes.fromJson(Map<String, dynamic> json) {
    return PatientHistoryRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<WalletHistoryElement>.from(json['data'].map((x) => WalletHistoryElement.fromJson(x))) : [],
      message: json['message'] is String ? json['message'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': data.map((e) => e.toJson()).toList(),
      'message': message,
    };
  }
}

class WalletHistoryElement {
  String title;
  num amount;
  num creditDebitAmount;
  String date;
  String transactionType;

  WalletHistoryElement({
    this.title = "",
    this.amount = 0,
    this.creditDebitAmount = 0,
    this.date = "",
    this.transactionType = "",
  });

  factory WalletHistoryElement.fromJson(Map<String, dynamic> json) {
    return WalletHistoryElement(
      title: json['title'] is String ? json['title'] : "",
      amount: json['amount'] is num ? json['amount'] : 0,
      creditDebitAmount: json['credit_debit_amount'] is num ? json['credit_debit_amount'] : 0,
      date: json['date'] is String ? json['date'] : "",
      transactionType: json['transaction_type'] is String ? json['transaction_type'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'amount': amount,
      'credit_debit_amount': creditDebitAmount,
      'transaction_id': date,
      'transaction_type': transactionType,
    };
  }
}

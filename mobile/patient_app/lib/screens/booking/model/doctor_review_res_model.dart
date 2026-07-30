import 'employee_review_data.dart';

class DoctorReviewRes {
  bool status;
  List<DoctorReviewData> reviewData;
  String message;

  DoctorReviewRes({
    this.status = false,
    this.reviewData = const <DoctorReviewData>[],
    this.message = "",
  });

  factory DoctorReviewRes.fromJson(Map<String, dynamic> json) {
    return DoctorReviewRes(
      status: json['status'] is bool ? json['status'] : false,
      reviewData: json['data'] is List ? List<DoctorReviewData>.from(json['data'].map((x) => DoctorReviewData.fromJson(x))) : [],
      message: json['message'] is String ? json['message'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': reviewData.map((e) => e.toJson()).toList(),
      'message': message,
    };
  }
}

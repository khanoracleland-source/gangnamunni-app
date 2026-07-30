import 'package:kivicare_patient/screens/service/model/service_list_model.dart';

class ServiceDetailModel {
  bool status;
  ServiceElement data;
  String message;

  ServiceDetailModel({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory ServiceDetailModel.fromJson(Map<String, dynamic> json) {
    return ServiceDetailModel(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is Map ? ServiceElement.fromJson(json['data']) : ServiceElement(),
      message: json['message'] is String ? json['message'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': data.toJson(),
      'message': message,
    };
  }
}

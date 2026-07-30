import 'appointments_res_model.dart';

class AppointmentDetailRes {
  bool status;
  AppointmentData data;
  String message;

  AppointmentDetailRes({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory AppointmentDetailRes.fromJson(Map<String, dynamic> json) {
    return AppointmentDetailRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is Map ? AppointmentData.fromJson(json['data']) : AppointmentData(),
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

class MedicalReport {
  int id;
  String url;

  ///Encounter Detail
  int encounterId;
  int userId;
  String name;
  String date;
  String fileUrl;

  MedicalReport({
    this.id = -1,
    this.url = "",
    this.encounterId = -1,
    this.userId = -1,
    this.name = "",
    this.date = "",
    this.fileUrl = "",
  });

  factory MedicalReport.fromJson(Map<String, dynamic> json) {
    return MedicalReport(
      id: json['id'] is int ? json['id'] : -1,
      url: json['url'] is String ? json['url'] : "",
      encounterId: json['encounter_id'] is int ? json['encounter_id'] : -1,
      userId: json['user_id'] is int ? json['user_id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      date: json['date'] is String ? json['date'] : "",
      fileUrl: json['file_url'] is String ? json['file_url'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'url': url,
      'encounter_id': encounterId,
      'user_id': userId,
      'name': name,
      'date': date,
      'file_url': fileUrl,
    };
  }
}

import 'doctor_list_res.dart';

class DoctorDetailModel {
  bool status;
  Doctor data;
  String message;

  DoctorDetailModel({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory DoctorDetailModel.fromJson(Map<String, dynamic> json) {
    return DoctorDetailModel(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is Map ? Doctor.fromJson(json['data']) : Doctor(),
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

class Commissions {
  int id;
  int employeeId;
  int commissionId;
  String title;
  String commissionType;
  int commissionValue;
  dynamic charges;
  dynamic name;

  Commissions({
    this.id = -1,
    this.employeeId = -1,
    this.commissionId = -1,
    this.title = "",
    this.commissionType = "",
    this.commissionValue = -1,
    this.charges,
    this.name,
  });

  factory Commissions.fromJson(Map<String, dynamic> json) {
    return Commissions(
      id: json['id'] is int ? json['id'] : -1,
      employeeId: json['employee_id'] is int ? json['employee_id'] : -1,
      commissionId: json['commission_id'] is int ? json['commission_id'] : -1,
      title: json['title'] is String ? json['title'] : "",
      commissionType:
      json['commission_type'] is String ? json['commission_type'] : "",
      commissionValue:
      json['commission_value'] is int ? json['commission_value'] : -1,
      charges: json['charges'],
      name: json['name'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'employee_id': employeeId,
      'commission_id': commissionId,
      'title': title,
      'commission_type': commissionType,
      'commission_value': commissionValue,
      'charges': charges,
      'name': name,
    };
  }
}

class Qualifications {
  int id;
  int doctorId;
  String degree;
  String university;
  String year;
  int status;
  int createdBy;
  int updatedBy;
  int deletedBy;
  String createdAt;
  String updatedAt;
  String deletedAt;

  Qualifications({
    this.id = -1,
    this.doctorId = -1,
    this.degree = "",
    this.university = "",
    this.year = "",
    this.status = -1,
    this.createdBy = -1,
    this.updatedBy = -1,
    this.deletedBy = -1,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt = "",
  });

  factory Qualifications.fromJson(Map<String, dynamic> json) {
    return Qualifications(
      id: json['id'] is int ? json['id'] : -1,
      doctorId: json['doctor_id'] is int ? json['doctor_id'] : -1,
      degree: json['degree'] is String ? json['degree'] : "",
      university: json['university'] is String ? json['university'] : "",
      year: json['year'] is String ? json['year'] : "",
      status: json['status'] is int ? json['status'] : -1,
      createdBy: json['created_by'] is int ? json['created_by'] : -1,
      updatedBy: json['updated_by'] is int ? json['updated_by'] : -1,
      deletedBy: json['deleted_by'] is int ? json['deleted_by'] : -1,
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      deletedAt: json['deleted_at'] is String ? json['deleted_at'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'doctor_id': doctorId,
      'degree': degree,
      'university': university,
      'year': year,
      'status': status,
      'created_by': createdBy,
      'updated_by': updatedBy,
      'deleted_by': deletedBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
    };
  }
}

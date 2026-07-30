import 'clinics_res_model.dart';

class ClinicDetailModel {
  bool status;
  Clinic data;
  String message;

  ClinicDetailModel({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory ClinicDetailModel.fromJson(Map<String, dynamic> json) {
    return ClinicDetailModel(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is Map ? Clinic.fromJson(json['data']) : Clinic(clinicSession: ClinicSession()),
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

class ClinicSession {
  List<OpenDays> openDays;
  List<String> closeDays;

  ClinicSession({
    this.openDays = const <OpenDays>[],
    this.closeDays = const <String>[],
  });

  factory ClinicSession.fromJson(Map<String, dynamic> json) {
    return ClinicSession(
      openDays: json['open_days'] is List ? List<OpenDays>.from(json['open_days'].map((x) => OpenDays.fromJson(x))) : [],
      closeDays: json['close_days'] is List ? List<String>.from(json['close_days'].map((x) => x)) : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'open_days': openDays.map((e) => e.toJson()).toList(),
      'close_days': closeDays.map((e) => e).toList(),
    };
  }
}

class OpenDays {
  String day;
  String startTime;
  String endTime;

  OpenDays({
    this.day = "",
    this.startTime = "",
    this.endTime = "",
  });

  factory OpenDays.fromJson(Map<String, dynamic> json) {
    return OpenDays(
      day: json['day'] is String ? json['day'] : "",
      startTime: json['start_time'] is String ? json['start_time'] : "",
      endTime: json['end_time'] is String ? json['end_time'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'day': day,
      'start_time': startTime,
      'end_time': endTime,
    };
  }
}

class AllClinicSession {
  int id;
  int clinicId;
  String day;
  String startTime;
  String endTime;
  bool isHoliday;
  List<BreakListModel> breaks;
  dynamic createdBy;
  dynamic updatedBy;
  dynamic deletedBy;
  String createdAt;
  String updatedAt;
  dynamic deletedAt;

  AllClinicSession({
    this.id = -1,
    this.clinicId = -1,
    this.day = "",
    this.startTime = "",
    this.endTime = "",
    this.isHoliday = false,
    this.breaks = const <BreakListModel>[],
    this.createdBy,
    this.updatedBy,
    this.deletedBy,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt,
  });

  factory AllClinicSession.fromJson(Map<String, dynamic> json) {
    return AllClinicSession(
      id: json['id'] is int ? json['id'] : -1,
      clinicId: json['clinic_id'] is int ? json['clinic_id'] : -1,
      day: json['day'] is String ? json['day'] : "",
      startTime: json['start_time'] is String ? json['start_time'] : "",
      endTime: json['end_time'] is String ? json['end_time'] : "",
      isHoliday: json['is_holiday'] is int
          ? json['is_holiday'] == 1
              ? true
              : false
          : false,
      breaks: json['breaks'] is List ? List<BreakListModel>.from(json['breaks'].map((x) => BreakListModel.fromJson(x))) : [],
      createdBy: json['created_by'],
      updatedBy: json['updated_by'],
      deletedBy: json['deleted_by'],
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      deletedAt: json['deleted_at'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'clinic_id': clinicId,
      'day': day,
      'start_time': startTime,
      'end_time': endTime,
      'is_holiday': isHoliday,
      'breaks': breaks.map((e) => e.toJson()).toList(),
      'created_by': createdBy,
      'updated_by': updatedBy,
      'deleted_by': deletedBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
    };
  }
}

class BreakListModel {
  String startBreak;
  String endBreak;

  BreakListModel({
    this.startBreak = "",
    this.endBreak = "",
  });

  factory BreakListModel.fromJson(Map<String, dynamic> json) {
    return BreakListModel(
      startBreak: json['start_break'] is String ? json['start_break'] : "",
      endBreak: json['end_break'] is String ? json['end_break'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'start_break': startBreak,
      'end_break': endBreak,
    };
  }
}

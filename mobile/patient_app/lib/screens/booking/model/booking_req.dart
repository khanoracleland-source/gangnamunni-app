import 'package:file_picker/file_picker.dart';

class BookingReq {
  String clinicId;
  String serviceId;
  String appointmentDate;
  String userId;
  String status;
  String doctorId;
  String appointmentTime;
  String description;
  bool isOnlineService;

  //Extra local variables
  String serviceName;
  String doctorName;
  String clinicName;
  String location;
  num totalAmount;
  bool isEnableAdvancePayment;
  num advancePayableAmount;
  num remainingPayableAmountAfterService;
  String otherPatientId;
  //Files local variable
  List<PlatformFile> files;

  BookingReq({
    this.clinicId = "",
    this.serviceId = "",
    this.appointmentDate = "",
    this.userId = "",
    this.status = "",
    this.doctorId = "",
    this.appointmentTime = "",
    this.description = "",
    this.isOnlineService = false,

    //Extra local variables
    this.serviceName = "",
    this.doctorName = "",
    this.clinicName = "",
    this.location = "",
    this.totalAmount = 0,
    this.isEnableAdvancePayment = false,
    this.advancePayableAmount = 0.0,
    this.remainingPayableAmountAfterService = 0.0,
    this.otherPatientId = "",
    //Files local variable
    this.files = const <PlatformFile>[],
  });

  factory BookingReq.fromJson(Map<String, dynamic> json) {
    return BookingReq(
      clinicId: json['clinic_id'] is String ? json['clinic_id'] : "",
      serviceId: json['service_id'] is String ? json['service_id'] : "",
      appointmentDate: json['appointment_date'] is String ? json['appointment_date'] : "",
      userId: json['user_id'] is String ? json['user_id'] : "",
      status: json['status'] is String ? json['status'] : "",
      doctorId: json['doctor_id'] is String ? json['doctor_id'] : "",
      appointmentTime: json['appointment_time'] is String ? json['appointment_time'] : "",
      description: json['description'] is String ? json['description'] : "",
      otherPatientId: json['otherpatient_id'] is num ? json['otherpatient_id'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'clinic_id': clinicId,
      'service_id': serviceId,
      'appointment_date': appointmentDate,
      'user_id': userId,
      'status': status,
      'doctor_id': doctorId,
      'appointment_time': appointmentTime,
      'appointment_extra_info': description,
      'otherpatient_id': otherPatientId,
    };
  }
}

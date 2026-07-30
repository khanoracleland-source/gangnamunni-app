class BedHistoryModel {
  bool status;
  List<BedHistoryData> data;

  BedHistoryModel({
    this.status = false,
    this.data = const <BedHistoryData>[],
  });

  factory BedHistoryModel.fromJson(Map<String, dynamic> json) {
    return BedHistoryModel(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List
          ? List<BedHistoryData>.from(json['data'].map((x) => BedHistoryData.fromJson(x)))
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': data.map((e) => e.toJson()).toList(),
    };
  }
}

class BedHistoryData {
  int encounterId;
  int id;
  int patientId;
  String patientName;
  int bedTypeId;
  String bedTypeName;
  int bedMasterId;
  String bedMasterName;
  String assignDate;
  String dischargeDate;
  String description;
  int charge;
  int perBedCharge;
  int bedPaymentStatus;
  String temperature;
  String symptoms;
  String notes;
  String createdAt;
  String updatedAt;
  bool status;

  BedHistoryData({
    this.encounterId = -1,
    this.id = -1,
    this.patientId = -1,
    this.patientName = "",
    this.bedTypeId = -1,
    this.bedTypeName = "",
    this.bedMasterId = -1,
    this.bedMasterName = "",
    this.assignDate = "",
    this.dischargeDate = "",
    this.description = "",
    this.charge = -1,
    this.perBedCharge = -1,
    this.bedPaymentStatus = -1,
    this.temperature = "",
    this.symptoms = "",
    this.notes = "",
    this.createdAt = "",
    this.updatedAt = "",
    this.status = false,
  });

  factory BedHistoryData.fromJson(Map<String, dynamic> json) {
    return BedHistoryData(
      encounterId: json['encounter_id'] is int ? json['encounter_id'] : -1,
      id: json['id'] is int ? json['id'] : -1,
      patientId: json['patient_id'] is int ? json['patient_id'] : -1,
      patientName: json['patient_name'] is String ? json['patient_name'] : "",
      bedTypeId: json['bed_type_id'] is int ? json['bed_type_id'] : -1,
      bedTypeName: json['bed_type_name'] is String ? json['bed_type_name'] : "",
      bedMasterId: json['bed_master_id'] is int ? json['bed_master_id'] : -1,
      bedMasterName:
      json['bed_master_name'] is String ? json['bed_master_name'] : "",
      assignDate: json['assign_date'] is String ? json['assign_date'] : "",
      dischargeDate:
      json['discharge_date'] is String ? json['discharge_date'] : "",
      description: json['description'] is String ? json['description'] : "",
      charge: json['charge'] is int ? json['charge'] : -1,
      perBedCharge: json['per_bed_charge'] is int ? json['per_bed_charge'] : -1,
      bedPaymentStatus:
      json['bed_payment_status'] is int ? json['bed_payment_status'] : -1,
      temperature: json['temperature'] is String ? json['temperature'] : "",
      symptoms: json['symptoms'] is String ? json['symptoms'] : "",
      notes: json['notes'] is String ? json['notes'] : "",
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      status: json['status'] is bool ? json['status'] : false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'encounter_id': encounterId,
      'id': id,
      'patient_id': patientId,
      'patient_name': patientName,
      'bed_type_id': bedTypeId,
      'bed_type_name': bedTypeName,
      'bed_master_id': bedMasterId,
      'bed_master_name': bedMasterName,
      'assign_date': assignDate,
      'discharge_date': dischargeDate,
      'description': description,
      'charge': charge,
      'per_bed_charge': perBedCharge,
      'bed_payment_status': bedPaymentStatus,
      'temperature': temperature,
      'symptoms': symptoms,
      'notes': notes,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'status': status,
    };
  }
}

class DoctorServiceListRes {
  bool status;
  List<DoctorServiceElement> data;
  String message;

  DoctorServiceListRes({
    this.status = false,
    this.data = const <DoctorServiceElement>[],
    this.message = "",
  });

  factory DoctorServiceListRes.fromJson(Map<String, dynamic> json) {
    return DoctorServiceListRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<DoctorServiceElement>.from(json['data'].map((x) => DoctorServiceElement.fromJson(x))) : [],
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

class DoctorServiceElement {
  int id;
  String name;
  String description;
  num charges;
  String categoryId;
  int subCategoryId;
  String vendorId;
  int durationMin;
  int isVideoConsultancy;
  int status;
  String serviceImage;
  int createdBy;
  int updatedBy;
  int deletedBy;
  String createdAt;
  String updatedAt;
  String deletedAt;

  DoctorServiceElement({
    this.id = -1,
    this.name = "",
    this.description = "",
    this.charges = 0,
    this.categoryId = "",
    this.subCategoryId = -1,
    this.vendorId = "",
    this.durationMin = -1,
    this.isVideoConsultancy = -1,
    this.status = -1,
    this.serviceImage = "",
    this.createdBy = -1,
    this.updatedBy = -1,
    this.deletedBy = -1,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt = "",
  });

  factory DoctorServiceElement.fromJson(Map<String, dynamic> json) {
    return DoctorServiceElement(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      description: json['description'] is String ? json['description'] : "",
      charges: json['charges'] is num ? json['charges'] : 0,
      categoryId: json['category_id'] is String ? json['category_id'] : "",
      subCategoryId: json['sub_category_id'] is int ? json['sub_category_id'] : -1,
      vendorId: json['vendor_id'] is String ? json['vendor_id'] : "",
      durationMin: json['duration_min'] is int ? json['duration_min'] : -1,
      isVideoConsultancy: json['is_video_consultancy'] is int ? json['is_video_consultancy'] : -1,
      status: json['status'] is int ? json['status'] : -1,
      serviceImage: json['service_image'] is String ? json['service_image'] : "",
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
      'name': name,
      'description': description,
      'charges': charges,
      'category_id': categoryId,
      'sub_category_id': subCategoryId,
      'vendor_id': vendorId,
      'duration_min': durationMin,
      'is_video_consultancy': isVideoConsultancy,
      'status': status,
      'service_image': serviceImage,
      'created_by': createdBy,
      'updated_by': updatedBy,
      'deleted_by': deletedBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
    };
  }
}

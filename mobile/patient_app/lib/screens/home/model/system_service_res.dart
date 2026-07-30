class SystemServicesRes {
  bool status;
  List<SystemService> data;
  String message;

  SystemServicesRes({
    this.status = false,
    this.data = const <SystemService>[],
    this.message = "",
  });

  factory SystemServicesRes.fromJson(Map<String, dynamic> json) {
    return SystemServicesRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<SystemService>.from(json['data'].map((x) => SystemService.fromJson(x))) : [],
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

class SystemService {
  int id;
  String name;
  String description;
  int parentId;
  int status;
  int isFeatured;
  int categoryId;
  int subcategoryId;
  int vendorId;
  String categoryName;
  String subcategoryName;
  String systemServiceImage;
  int totalServices;
  int createdBy;
  int updatedBy;
  int deletedBy;
  String createdAt;
  String updatedAt;
  String deletedAt;

  SystemService({
    this.id = -1,
    this.name = "",
    this.description = "",
    this.parentId = -1,
    this.status = -1,
    this.isFeatured = -1,
    this.categoryId = -1,
    this.subcategoryId = -1,
    this.vendorId = -1,
    this.categoryName = "",
    this.subcategoryName = "",
    this.systemServiceImage = "",
    this.totalServices = -1,
    this.createdBy = -1,
    this.updatedBy = -1,
    this.deletedBy = -1,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt = "",
  });

  factory SystemService.fromJson(Map<String, dynamic> json) {
    return SystemService(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      description: json['description'] is String ? json['description'] : "",
      parentId: json['parent_id'] is int ? json['parent_id'] : -1,
      status: json['status'] is int ? json['status'] : -1,
      isFeatured: json['is_featured'] is int ? json['is_featured'] : -1,
      categoryId: json['category_id'] is int ? json['category_id'] : -1,
      subcategoryId: json['subcategory_id'] is int ? json['subcategory_id'] : -1,
      vendorId: json['vendor_id'] is int ? json['vendor_id'] : -1,
      categoryName: json['category_name'] is String ? json['category_name'] : "",
      subcategoryName: json['subcategory_name'] is String ? json['subcategory_name'] : "",
      systemServiceImage: json['system_service_image'] is String ? json['system_service_image'] : "",
      totalServices: json['total_services'] is int ? json['total_services'] : -1,
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
      'parent_id': parentId,
      'status': status,
      'is_featured': isFeatured,
      'category_id': categoryId,
      'subcategory_id': subcategoryId,
      'vendor_id': vendorId,
      'category_name': categoryName,
      'subcategory_name': subcategoryName,
      'system_service_image': systemServiceImage,
      'total_services': totalServices,
      'created_by': createdBy,
      'updated_by': updatedBy,
      'deleted_by': deletedBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
    };
  }
}

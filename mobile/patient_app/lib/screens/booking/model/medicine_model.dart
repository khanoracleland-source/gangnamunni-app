class MedicineListRes {
  int code;
  bool status;
  String message;
  List<Medicine> data;

  MedicineListRes({
    this.code = -1,
    this.status = false,
    this.message = "",
    this.data = const <Medicine>[],
  });

  factory MedicineListRes.fromJson(Map<String, dynamic> json) {
    return MedicineListRes(
      code: json['code'] is int ? json['code'] : -1,
      status: json['status'] is bool ? json['status'] : false,
      message: json['message'] is String ? json['message'] : "",
      data: json['data'] is List ? List<Medicine>.from(json['data'].map((x) => Medicine.fromJson(x))) : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'code': code,
      'status': status,
      'message': message,
      'data': data.map((e) => e.toJson()).toList(),
    };
  }
}

class Medicine {
  int id;
  String name;
  String dosage;
  MedicineCategory category;
  MedicineForm form;
  String expiryDate;
  String note;
  Supplier supplier;
  String contactNumber;
  String paymentTerms;
  String quntity;
  String reOrderLevel;
  Manufacturer manufacturer;
  String batchNo;
  String startSerialNo;
  String endSerialNo;
  String purchasePrice;
  num sellingPrice;
  String stockValue;
  bool isInclusiveTax;

  Medicine({
    this.id = -1,
    this.name = "",
    this.dosage = "",
    required this.category,
    required this.form,
    this.expiryDate = "",
    this.note = "",
    required this.supplier,
    this.contactNumber = "",
    this.paymentTerms = "",
    this.quntity = "",
    this.reOrderLevel = "",
    required this.manufacturer,
    this.batchNo = "",
    this.startSerialNo = "",
    this.endSerialNo = "",
    this.purchasePrice = "",
    this.sellingPrice = 0,
    this.stockValue = "",
    this.isInclusiveTax = false,
  });

  factory Medicine.fromJson(Map<String, dynamic> json) {
    return Medicine(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      dosage: json['dosage'] is String ? json['dosage'] : "",
      category: json['category'] is Map ? MedicineCategory.fromJson(json['category']) : MedicineCategory(),
      form: json['form'] is Map ? MedicineForm.fromJson(json['form']) : MedicineForm(),
      expiryDate: json['expiry_date'] is String ? json['expiry_date'] : "",
      note: json['note'] is String ? json['note'] : "",
      supplier: json['supplier'] is Map ? Supplier.fromJson(json['supplier']) : Supplier(supplierType: SupplierType()),
      contactNumber: json['contact_number'] is String ? json['contact_number'] : "",
      paymentTerms: json['payment_terms'] is String ? json['payment_terms'] : "",
      quntity: json['quntity'] is String ? json['quntity'] : "",
      reOrderLevel: json['re_order_level'] is String ? json['re_order_level'] : "",
      manufacturer: json['manufacturer'] is Map ? Manufacturer.fromJson(json['manufacturer']) : Manufacturer(),
      batchNo: json['batch_no'] is String ? json['batch_no'] : "",
      startSerialNo: json['start_serial_no'] is String ? json['start_serial_no'] : "",
      endSerialNo: json['end_serial_no'] is String ? json['end_serial_no'] : "",
      purchasePrice: json['purchase_price'] is String ? json['purchase_price'] : "",
      sellingPrice: json['selling_price'] is num ? json['selling_price'] : 0,
      stockValue: json['stock_value'] is String ? json['stock_value'] : "",
      isInclusiveTax: json['is_inclusive_tax'] is bool ? json['is_inclusive_tax'] : json['is_inclusive_tax'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'dosage': dosage,
      'category': category.toJson(),
      'form': form.toJson(),
      'expiry_date': expiryDate,
      'note': note,
      'supplier': supplier.toJson(),
      'contact_number': contactNumber,
      'payment_terms': paymentTerms,
      'quntity': quntity,
      're_order_level': reOrderLevel,
      'manufacturer': manufacturer.toJson(),
      'batch_no': batchNo,
      'start_serial_no': startSerialNo,
      'end_serial_no': endSerialNo,
      'purchase_price': purchasePrice,
      'selling_price': sellingPrice,
      'stock_value': stockValue,
      'is_inclusive_tax': isInclusiveTax,
    };
  }
}

class MedicineCategory {
  int id;
  String name;
  int dosage;
  String createdAt;
  String updatedAt;

  MedicineCategory({
    this.id = -1,
    this.name = "",
    this.dosage = -1,
    this.createdAt = "",
    this.updatedAt = "",
  });

  factory MedicineCategory.fromJson(Map<String, dynamic> json) {
    return MedicineCategory(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      dosage: json['dosage'] is int ? json['dosage'] : -1,
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'dosage': dosage,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}

class MedicineForm {
  int id;
  String name;
  int status;

  MedicineForm({
    this.id = -1,
    this.name = "",
    this.status = -1,
  });

  factory MedicineForm.fromJson(Map<String, dynamic> json) {
    return MedicineForm(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      status: json['status'] is int ? json['status'] : -1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'status': status,
    };
  }
}

class Supplier {
  String firstName;
  String lastName;
  String email;
  String contactNumber;
  SupplierType supplierType;
  int pharmaId;
  String paymentTerms;

  Supplier({
    this.firstName = "",
    this.lastName = "",
    this.email = "",
    this.contactNumber = "",
    required this.supplierType,
    this.pharmaId = -1,
    this.paymentTerms = "",
  });

  factory Supplier.fromJson(Map<String, dynamic> json) {
    return Supplier(
      firstName: json['first_name'] is String ? json['first_name'] : "",
      lastName: json['last_name'] is String ? json['last_name'] : "",
      email: json['email'] is String ? json['email'] : "",
      contactNumber: json['contact_number'] is String ? json['contact_number'] : "",
      supplierType: json['supplier_type'] is Map ? SupplierType.fromJson(json['supplier_type']) : SupplierType(),
      pharmaId: json['pharma_id'] is int ? json['pharma_id'] : -1,
      paymentTerms: json['payment_terms'] is String ? json['payment_terms'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'first_name': firstName,
      'last_name': lastName,
      'email': email,
      'contact_number': contactNumber,
      'supplier_type': supplierType.toJson(),
      'pharma_id': pharmaId,
      'payment_terms': paymentTerms,
    };
  }
}

class SupplierType {
  int id;
  String name;
  int status;

  SupplierType({
    this.id = -1,
    this.name = "",
    this.status = -1,
  });

  factory SupplierType.fromJson(Map<String, dynamic> json) {
    return SupplierType(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      status: json['status'] is int ? json['status'] : -1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'status': status,
    };
  }
}

class Manufacturer {
  int id;
  String name;
  dynamic status;

  Manufacturer({
    this.id = -1,
    this.name = "",
    this.status,
  });

  factory Manufacturer.fromJson(Map<String, dynamic> json) {
    return Manufacturer(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      status: json['status'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'status': status,
    };
  }
}

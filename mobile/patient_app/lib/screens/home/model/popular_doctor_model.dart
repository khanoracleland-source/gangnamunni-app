// ignore_for_file: non_constant_identifier_names

class TopDoctorDetailRes {
  bool status;
  List<TopDoctor> data;
  String message;

  TopDoctorDetailRes({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory TopDoctorDetailRes.fromJson(Map<String, dynamic> json) {
    return TopDoctorDetailRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<TopDoctor>.from(json['data'].map((x) => TopDoctor.fromJson(x))) : [],
      message: json['message'] is String ? json['message'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': data,
      'message': message,
    };
  }
}

class TopDoctor {
  int id;
  int doctorId;
  String firstName;
  String lastName;
  String fullName;
  String email;
  String mobile;
  String? playerId;
  String gender;
  String expert;
  String dateOfBirth;
  String? emailVerifiedAt;
  int status;
  int isBanned;
  int isManager;
  int countryId;
  int stateId;
  int cityId;
  String countryName;
  String stateName;
  String cityName;
  String address;
  String pincode;
  String latitude;
  String longitude;
  List<DoctorService> services;

  TopDoctor({
    this.id = -1,
    this.doctorId = -1,
    this.firstName = "",
    this.lastName = "",
    this.fullName = "",
    this.email = "",
    this.mobile = "",
    this.playerId,
    this.gender = "",
    this.expert = "",
    this.dateOfBirth = "",
    this.emailVerifiedAt,
    this.status = 1,
    this.isBanned = 0,
    this.isManager = 0,
    this.countryId = -1,
    this.stateId = -1,
    this.cityId = -1,
    this.countryName = "",
    this.stateName = "",
    this.cityName = "",
    this.address = "",
    this.pincode = "",
    this.latitude = "",
    this.longitude = "",
    this.services = const [],
  });

  factory TopDoctor.fromJson(Map<String, dynamic> json) {
    return TopDoctor(
      id: json['id'] ?? -1,
      doctorId: json['doctor_id'] ?? -1,
      firstName: json['first_name'] ?? '',
      lastName: json['last_name'] ?? '',
      fullName: json['full_name'] ?? '',
      email: json['email'] ?? '',
      mobile: json['mobile'] ?? '',
      playerId: json['player_id'],
      gender: json['gender'] ?? '',
      expert: json['expert'] ?? '',
      dateOfBirth: json['date_of_birth'] ?? '',
      emailVerifiedAt: json['email_verified_at'],
      status: json['status'] ?? 1,
      isBanned: json['is_banned'] ?? 0,
      isManager: json['is_manager'] ?? 0,
      countryId: json['country_id'] ?? -1,
      stateId: json['state_id'] ?? -1,
      cityId: json['city_id'] ?? -1,
      countryName: json['country_name'] ?? '',
      stateName: json['state_name'] ?? '',
      cityName: json['city_name'] ?? '',
      address: json['address'] ?? '',
      pincode: json['pincode'] ?? '',
      latitude: json['latitude'] ?? '',
      longitude: json['longitude'] ?? '',
      services: json['services'] != null ? List<DoctorService>.from(json['services'].map((x) => DoctorService.fromJson(x))) : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,
      "doctor_id": doctorId,
      "first_name": firstName,
      "last_name": lastName,
      "full_name": fullName,
      "email": email,
      "mobile": mobile,
      "player_id": playerId,
      "gender": gender,
      "expert": expert,
      "date_of_birth": dateOfBirth,
      "email_verified_at": emailVerifiedAt,
      "status": status,
      "is_banned": isBanned,
      "is_manager": isManager,
      "country_id": countryId,
      "state_id": stateId,
      "city_id": cityId,
      "country_name": countryName,
      "state_name": stateName,
      "city_name": cityName,
      "address": address,
      "pincode": pincode,
      "latitude": latitude,
      "longitude": longitude,
      "services": services.map((e) => e.toJson()).toList(),
    };
  }
}

class DoctorService {
  int id;
  int serviceId;
  int clinicId;
  int doctorId;
  num charges;
  bool isEnableAdvancePayment;
  num? advancePaymentAmount;
  PriceDetail priceDetail;
  String name;
  String doctorName;
  String clinicName;
  String topDoctor;

  DoctorService({
    this.id = -1,
    this.serviceId = -1,
    this.clinicId = -1,
    this.doctorId = -1,
    this.charges = 0,
    this.isEnableAdvancePayment = false,
    this.advancePaymentAmount,
    required this.priceDetail,
    this.name = '',
    this.doctorName = '',
    this.clinicName = '',
    this.topDoctor = '',
  });

  factory DoctorService.fromJson(Map<String, dynamic> json) {
    return DoctorService(
      id: json['id'] ?? -1,
      serviceId: json['service_id'] ?? -1,
      clinicId: json['clinic_id'] ?? -1,
      doctorId: json['doctor_id'] ?? -1,
      charges: json['charges'] ?? 0,
      isEnableAdvancePayment: json['is_enable_advance_payment'] == 1,
      advancePaymentAmount: json['advance_payment_amount'],
      priceDetail: json['price_detail'] != null ? PriceDetail.fromJson(json['price_detail']) : PriceDetail(),
      name: json['name'] ?? '',
      doctorName: json['doctor_name'] ?? '',
      clinicName: json['clinic_name'] ?? '',
      topDoctor: json['doctor_profile'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,
      "service_id": serviceId,
      "clinic_id": clinicId,
      "doctor_id": doctorId,
      "charges": charges,
      "is_enable_advance_payment": isEnableAdvancePayment,
      "advance_payment_amount": advancePaymentAmount,
      "price_detail": priceDetail.toJson(),
      "name": name,
      "doctor_name": doctorName,
      "clinic_name": clinicName,
      "doctor_profile": topDoctor,
    };
  }
}

class PriceDetail {
  num servicePrice;
  num serviceAmount;
  num totalAmount;
  int duration;
  String? discountType;
  num discountValue;
  num discountAmount;
  num totalInclusiveTax;
  num totalExclusiveTax;
  dynamic inclusiveTaxJson;
  List<dynamic> inclusiveTaxData;

  PriceDetail({
    this.servicePrice = 0,
    this.serviceAmount = 0,
    this.totalAmount = 0,
    this.duration = 0,
    this.discountType = '',
    this.discountValue = 0,
    this.discountAmount = 0,
    this.totalInclusiveTax = 0,
    this.totalExclusiveTax = 0,
    this.inclusiveTaxJson = 0,
    this.inclusiveTaxData = const [],
  });

  factory PriceDetail.fromJson(Map<String, dynamic> json) {
    return PriceDetail(
      servicePrice: json['service_price'] ?? 0,
      serviceAmount: json['service_amount'] ?? 0,
      totalAmount: json['total_amount'] ?? 0,
      duration: json['duration'] ?? 0,
      discountType: json['discount_type'],
      discountValue: json['discount_value'] ?? 0,
      discountAmount: json['discount_amount'] ?? 0,
      totalInclusiveTax: json['total_inclusive_tax'] ?? 0,
      totalExclusiveTax: json['total_exclusive_tax'] ?? 0,
      inclusiveTaxJson: json['service_inclusive_tax'] ?? 0,
      inclusiveTaxData: json['inclusive_tax_data'] ?? [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "service_price": servicePrice,
      "service_amount": serviceAmount,
      "total_amount": totalAmount,
      "duration": duration,
      "discount_type": discountType,
      "discount_value": discountValue,
      "discount_amount": discountAmount,
      "total_inclusive_tax": totalInclusiveTax,
      "total_exclusive_tax": totalExclusiveTax,
      "service_inclusive_tax": inclusiveTaxJson,
      "inclusive_tax_data": inclusiveTaxData,
    };
  }
}

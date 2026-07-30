import '../../booking/model/employee_review_data.dart';
import '../../clinic/model/clinics_res_model.dart';
import '../../service/model/service_list_model.dart';
import 'doctor_detail_model.dart';

class DoctorListRes {
  bool status;
  List<Doctor> data;
  String message;

  DoctorListRes({
    this.status = false,
    this.data = const <Doctor>[],
    this.message = "",
  });

  factory DoctorListRes.fromJson(Map<String, dynamic> json) {
    return DoctorListRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<Doctor>.from(json['data'].map((x) => Doctor.fromJson(x))) : [],
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

class PopularDoctorData {
  String title;
  String subTitle;
  List<Doctor> selectedDoctor;

  PopularDoctorData({
    this.title = "",
    this.subTitle = "",
    this.selectedDoctor = const [],
  });

  factory PopularDoctorData.fromJson(Map<String, dynamic> json) {
    return PopularDoctorData(
      title: json['title'] is String ? json['title'] : "",
      subTitle: json['sub_title'] is String ? json['sub_title'] : "",
      selectedDoctor: json['selected_doctor'] is List
          ? List<Doctor>.from(
              json['selected_doctor'].map((x) => Doctor.fromJson(x)),
            )
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'sub_title': subTitle,
      'selected_doctor': selectedDoctor.map((e) => e.toJson()).toList(),
    };
  }
}

class Doctor {
  int id;
  int doctorId;
  String firstName;
  String lastName;
  String fullName;
  String email;
  String mobile;
  String gender;
  String expert;
  String dateOfBirth;
  String emailVerifiedAt;
  String profileImage;
  int status;
  int isBanned;
  int isManager;
  String createdAt;
  String updatedAt;
  String deletedAt;
  String aboutSelf;
  String facebookLink;
  String instagramLink;
  String twitterLink;
  String dribbbleLink;
  String experience;
  String description;
  String signature;

  /// Doctor Detail
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
  List<Clinic> clinics;
  List<Commissions> commissions;
  int totalServices;
  int totalReviews;
  num averageRating;
  int totalAppointmemt;
  List<DoctorReviewData> reviews;
  List<Qualifications> qualifications;
  List<ServiceElement> services;

  Doctor({
    this.id = -1,
    this.doctorId = -1,
    this.firstName = "",
    this.lastName = "",
    this.fullName = "",
    this.email = "",
    this.mobile = "",
    this.gender = "",
    this.expert = "",
    this.dateOfBirth = "",
    this.emailVerifiedAt = "",
    this.profileImage = "",
    this.status = -1,
    this.isBanned = -1,
    this.isManager = -1,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt = "",
    this.aboutSelf = "",
    this.facebookLink = "",
    this.instagramLink = "",
    this.twitterLink = "",
    this.dribbbleLink = "",
    this.experience = "",
    this.description = "",
    this.signature = "",
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
    this.clinics = const <Clinic>[],
    this.commissions = const <Commissions>[],
    this.totalServices = 0,
    this.totalReviews = 0,
    this.totalAppointmemt = 0,
    this.averageRating = 0.0,
    this.reviews = const <DoctorReviewData>[],
    this.qualifications = const <Qualifications>[],
    this.services = const <ServiceElement>[],
  });

  factory Doctor.fromJson(Map<String, dynamic> json) {
    return Doctor(
      id: json['id'] is int ? json['id'] : -1,
      doctorId: json['doctor_id'] is int ? json['doctor_id'] : -1,
      firstName: json['first_name'] is String ? json['first_name'] : "",
      lastName: json['last_name'] is String ? json['last_name'] : "",
      fullName: json['full_name'] is String ? json['full_name'] : "",
      email: json['email'] is String ? json['email'] : "",
      mobile: json['mobile'] is String ? json['mobile'] : "",
      gender: json['gender'] is String ? json['gender'] : "",
      expert: json['expert'] is String ? json['expert'] : "",
      dateOfBirth: json['date_of_birth'] is String ? json['date_of_birth'] : "",
      emailVerifiedAt: json['email_verified_at'] is String ? json['email_verified_at'] : "",
      profileImage: json['profile_image'] is String ? json['profile_image'] : "",
      status: json['status'] is int ? json['status'] : -1,
      isBanned: json['is_banned'] is int ? json['is_banned'] : -1,
      isManager: json['is_manager'] is int ? json['is_manager'] : -1,
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      deletedAt: json['deleted_at'] is String ? json['deleted_at'] : "",
      aboutSelf: json['about_self'] is String ? json['about_self'] : "",
      facebookLink: json['facebook_link'] is String ? json['facebook_link'] : "",
      instagramLink: json['instagram_link'] is String ? json['instagram_link'] : "",
      twitterLink: json['twitter_link'] is String ? json['twitter_link'] : "",
      dribbbleLink: json['dribbble_link'] is String ? json['dribbble_link'] : "",
      experience: json['experience'] is String ? json['experience'] : "",
      description: json['description'] is String ? json['description'] : "",
      signature: json['signature'] is String ? json['signature'] : "",
      countryId: json['country_id'] is int ? json['country_id'] : -1,
      stateId: json['state_id'] is int ? json['state_id'] : -1,
      cityId: json['city_id'] is int ? json['city_id'] : -1,
      countryName: json['country_name'] is String ? json['country_name'] : "",
      stateName: json['state_name'] is String ? json['state_name'] : "",
      cityName: json['city_name'] is String ? json['city_name'] : "",
      address: json['address'] is String ? json['address'] : "",
      pincode: json['pincode'] is String ? json['pincode'] : "",
      latitude: json['latitude'] is String ? json['latitude'] : "",
      longitude: json['longitude'] is String ? json['longitude'] : "",
      clinics: json['clinics'] is List ? List<Clinic>.from(json['clinics'].map((x) => Clinic.fromJson(x))) : [],
      commissions: json['commissions'] is List ? List<Commissions>.from(json['commissions'].map((x) => Commissions.fromJson(x))) : [],
      totalServices: json['total_services'] is int ? json['total_services'] : 0,
      totalReviews: json['total_reviews'] is int ? json['total_reviews'] : 0,
      totalAppointmemt: json['total_appointment'] is int ? json['total_appointment'] : 0,
      averageRating: json['average_rating'] is num ? json['average_rating'] : 0.0,
      reviews: json['reviews'] is List ? List<DoctorReviewData>.from(json['reviews'].map((x) => DoctorReviewData.fromJson(x))) : [],
      qualifications: json['qualifications'] is List ? List<Qualifications>.from(json['qualifications'].map((x) => Qualifications.fromJson(x))) : [],
      services: json['services'] is List ? List<ServiceElement>.from(json['services'].map((x) => ServiceElement.fromJson(x))) : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'doctor_id': doctorId,
      'first_name': firstName,
      'last_name': lastName,
      'full_name': fullName,
      'email': email,
      'mobile': mobile,
      'gender': gender,
      'expert': expert,
      'date_of_birth': dateOfBirth,
      'email_verified_at': emailVerifiedAt,
      'profile_image': profileImage,
      'status': status,
      'is_banned': isBanned,
      'is_manager': isManager,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
      'about_self': aboutSelf,
      'facebook_link': facebookLink,
      'instagram_link': instagramLink,
      'twitter_link': twitterLink,
      'dribbble_link': dribbbleLink,
      'experience': experience,
      'description': description,
      'signature': signature,
      'country_id': countryId,
      'state_id': stateId,
      'city_id': cityId,
      'country_name': countryName,
      'state_name': stateName,
      'city_name': cityName,
      'address': address,
      'pincode': pincode,
      'latitude': latitude,
      'longitude': longitude,
      'clinics': clinics.map((e) => e.toJson()).toList(),
      'commissions': commissions.map((e) => e.toJson()).toList(),
      'total_services': totalServices,
      'total_reviews': totalReviews,
      'average_rating': averageRating,
      'total_appointment': totalAppointmemt,
      'reviews': reviews.map((e) => e.toJson()).toList(),
      'qualifications': qualifications.map((e) => e.toJson()).toList(),
      'services': services.map((e) => e.toJson()).toList(),
    };
  }
}

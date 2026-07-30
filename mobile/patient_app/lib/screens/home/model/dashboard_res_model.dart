
import 'package:kivicare_patient/utils/constants.dart';

import '../../booking/model/appointments_res_model.dart';
import '../../category/model/category_list_model.dart';
import '../../clinic/model/clinics_res_model.dart';
import '../../doctor/model/doctor_list_res.dart';
import '../../service/model/service_list_model.dart';

class DashboardRes {
  bool status;
  DashboardData data;
  String message;

  DashboardRes({
    this.status = false,
    required this.data,
    this.message = "",
  });

  factory DashboardRes.fromJson(Map<String, dynamic> json) {
    return DashboardRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is Map ? DashboardData.fromJson(json['data']) : DashboardData(),
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

class DashboardData {
  List<CategoryElement> categories;
  List<Clinic> nearByClinic;
  List<ServiceElement> featuredServices;
  PopularServicesData popularService;
  List<AppointmentData> upcomingAppointment;
  PopularClinicData popularClinic;
  PopularDoctorData topDoctor;

  List<Slider> slider;

  //Unread Notificaions
  int unReadCount;

  DashboardData({
    this.categories = const <CategoryElement>[],
    this.nearByClinic = const <Clinic>[],
    this.featuredServices = const <ServiceElement>[],
    this.upcomingAppointment = const <AppointmentData>[],
    PopularClinicData? popularClinic ,
    PopularServicesData? popularService,
    PopularDoctorData? topDoctor,
    this.slider = const <Slider>[],

    //Unread Notificaions
    this.unReadCount = 0,
  }): popularService = popularService ?? PopularServicesData(),popularClinic = popularClinic ?? PopularClinicData(),topDoctor = topDoctor ?? PopularDoctorData();

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      categories: json['category'] is List ? List<CategoryElement>.from(json['category'].map((x) => CategoryElement.fromJson(x))) : [],
      upcomingAppointment: json['upcoming_appointment'] is List ? List<AppointmentData>.from(json['upcoming_appointment'].map((x) => AppointmentData.fromJson(x))) : [],
      nearByClinic: json['clinics'] is List ? List<Clinic>.from(json['clinics'].map((x) => Clinic.fromJson(x))) : [],
      featuredServices: json['featured_services'] is List ? List<ServiceElement>.from(json['featured_services'].map((x) => ServiceElement.fromJson(x))) : [],
      popularClinic: json['perfect_clinics'] != null
          ? PopularClinicData.fromJson(json['perfect_clinics'])
          : null,
        topDoctor: json['popular_doctors'] != null
            ? PopularDoctorData.fromJson(json['popular_doctors'])
            : null,
      popularService: json['popular_services'] != null
          ? PopularServicesData.fromJson(json['popular_services'])
          : null,
      slider: json['slider'] is List ? List<Slider>.from(json['slider'].map((x) => Slider.fromJson(x))) : [],

      //Unread Notificaions
      unReadCount: json['notification_count'] is int ? json['notification_count'] : 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'category': categories.map((e) => e.toJson()).toList(),
      'upcoming_appointment': upcomingAppointment.map((e) => e.toJson()).toList(),
      'clinics': nearByClinic.map((e) => e.toJson()).toList(),
      'featured_services': featuredServices.map((e) => e.toJson()).toList(),
      'perfect_clinics': popularClinic.toJson(),
      'popular_doctors': topDoctor.toJson(),
      'slider': slider.map((e) => e.toJson()).toList(),
      'popular_services': popularService.toJson(),
      //Unread Notificaions
      'notification_count': unReadCount,
    };
  }
}

class Slider {
  int id;
  String name;
  int status;
  String type;
  String link;
  int linkId;
  String sliderImage;

  Slider({
    this.id = -1,
    this.name = "",
    this.status = -1,
    this.type = "",
    this.link = "",
    this.linkId = -1,
    this.sliderImage = "",
  });

  factory Slider.fromJson(Map<String, dynamic> json) {
    return Slider(
      id: json['id'] is int ? json['id'] : -1,
      name: json['name'] is String ? json['name'] : "",
      status: json['status'] is int ? json['status'] : -1,
      type: json['type'] is String ? json['type'] : "",
      link: json['link'] is String ? json['link'] : "",
      linkId: json['link_id'] is int ? json['link_id'] : -1,
      sliderImage: json['slider_image'] is String ? json['slider_image'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'status': status,
      'type': type,
      'link': link,
      'link_id': linkId,
      'slider_image': sliderImage,
    };
  }
}

class TaxPercentage {
  int id;
  String title;
  String type;
  String taxScope;
  String bookingType;
  num value;
  num amount;
  num? totalCalculatedValue;

  TaxPercentage({
    this.id = -1,
    this.title = "",
    this.type = "",
    this.taxScope = TaxType.exclusiveTax,
    this.bookingType = "",
    this.value = 0,
    this.amount = 0,
    this.totalCalculatedValue,
  });

  factory TaxPercentage.fromJson(Map<String, dynamic> json) {
    return TaxPercentage(
      id: json['id'] is int ? json['id'] : -1,
      title: json['title'] is String ? json['title'] : "",
      type: json['type'] is String ? json['type'] : "",
      taxScope: json['tax_scope'] is String ? json['tax_scope'] : TaxType.exclusiveTax,
      bookingType: json['booking_type'] is String ? json['booking_type'] : "",
      value: json['value'] is num ? json['value'] : 0,
      amount: json['amount'] is num ? json['amount'] : 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'type': type,
      'tax_scope': taxScope,
      'booking_type': bookingType,
      'value': value,
      'amount': amount,
    };
  }
}

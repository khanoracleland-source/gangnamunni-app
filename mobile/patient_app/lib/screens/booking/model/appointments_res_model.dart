import 'package:nb_utils/nb_utils.dart';

import '../../../utils/constants.dart';
import '../../home/model/dashboard_res_model.dart';
import 'appointment_detail_res.dart';
import 'billing_item_model.dart';
import 'employee_review_data.dart';

class AppointmentListRes {
  bool status;
  List<AppointmentData> data;
  String message;

  AppointmentListRes({
    this.status = false,
    this.data = const <AppointmentData>[],
    this.message = "",
  });

  factory AppointmentListRes.fromJson(Map<String, dynamic> json) {
    return AppointmentListRes(
      status: json['status'] is bool ? json['status'] : false,
      data: json['data'] is List ? List<AppointmentData>.from(json['data'].map((x) => AppointmentData.fromJson(x))) : [],
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

class AppointmentData {
  int id;
  String status;
  String startDateTime;
  int userId;
  String userName;
  String userImage;
  String userMobile;
  String userPhone;
  int clinicId;
  String clinicName;
  int doctorId;
  String doctorName;
  String doctorImage;
  String doctorMobile;
  String doctorPhone;
  String appointmentDate;
  String appointmentTime;
  String endTime;
  int duration;
  int serviceId;
  String serviceName;
  String serviceType;
  bool isVideoConsultancy;
  String serviceImage;
  String categoryName;
  String appointmentExtraInfo;
  num totalAmount;
  num servicePrice;
  String discountType;
  num discountValue;
  num discountAmount;
  num serviceTotal;
  num subtotal;
  String paymentStatus;
  bool isEnableAdvancePayment;
  num advancePaymentAmount;
  int advancePaymentStatus;
  num advancePaidAmount;
  num remainingPayableAmount;
  String googleLink;
  String zoomLink;
  List<MedicalReport> medicalReport;
  int encounterId;
  String encounterDescription;
  bool encounterStatus;
  String billingFinalDiscountType;
  bool enableFinalBillingDiscount;
  num billingFinalDiscountValue;
  num billingFinalDiscountAmount;
  num totalExclusiveTax;
  num totalInclusiveTax;
  List<TaxPercentage> exclusiveTaxList;
  List<BillingItem> billingItems;
  DoctorReviewData? reviews;
  String bookForName;
  String booForImage;
  int createdBy;
  int updatedBy;
  int deletedBy;
  String createdAt;
  String updatedAt;
  String deletedAt;

  /// Option key from api
  num serviceAmount;

  //For notification read
  String notificationId;

  num cancellationCharges;
  num cancellationChargeAmount;
  String cancellationType;
  String reason;
  num refundAmount;
  String refundStatus;
  num paidAmount;
  String durationDiff;

  List<dynamic> bedAllocation;
  num bedPrice;
  String bedTypeName;
  String bed;

  bool get isAdvancePaymentDone => paidAmount.validate() != 0;

  ///Tax

  bool get isInclusiveTaxesAvailable => totalInclusiveTax > 0;

  bool get isExclusiveTaxesAvailable => exclusiveTaxList.isNotEmpty;

  bool get isDiscountAvailable => discountAmount > 0;

  bool isBedPricePaid;

  List<BedDetails> bedDetails;

  num finalTotalAmount;

  AppointmentData({
    this.id = -1,
    this.status = "",
    this.startDateTime = "",
    this.userId = -1,
    this.userName = "",
    this.userImage = "",
    this.userMobile = "",
    this.userPhone = "",
    this.clinicId = -1,
    this.clinicName = "",
    this.doctorId = -1,
    this.doctorName = "",
    this.doctorImage = "",
    this.doctorMobile = "",
    this.doctorPhone = "",
    this.appointmentDate = "",
    this.appointmentTime = "",
    this.endTime = "",
    this.duration = -1,
    this.serviceId = -1,
    this.serviceName = "",
    this.serviceType = "",
    this.isVideoConsultancy = false,
    this.serviceImage = "",
    this.categoryName = "",
    this.appointmentExtraInfo = "",
    this.totalAmount = 0,
    this.servicePrice = 0,
    this.discountType = "",
    this.discountValue = 0.0,
    this.discountAmount = 0,
    this.serviceTotal = 0,
    this.subtotal = 0,
    this.paymentStatus = PaymentStatus.pending,
    this.isEnableAdvancePayment = false,
    this.advancePaymentAmount = 0,
    this.advancePaymentStatus = 0,
    this.advancePaidAmount = 0,
    this.remainingPayableAmount = 0,
    this.googleLink = "",
    this.zoomLink = "",
    this.medicalReport = const <MedicalReport>[],
    this.encounterId = -1,
    this.encounterDescription = "",
    this.encounterStatus = false,
    this.billingFinalDiscountType = "",
    this.enableFinalBillingDiscount = false,
    this.billingFinalDiscountValue = 0,
    this.billingFinalDiscountAmount = 0,
    this.totalExclusiveTax = 0,
    this.totalInclusiveTax = 0,
    this.exclusiveTaxList = const <TaxPercentage>[],
    this.billingItems = const <BillingItem>[],
    this.reviews,
    this.bookForName = "",
    this.booForImage = "",
    this.createdBy = -1,
    this.updatedBy = -1,
    this.deletedBy = -1,
    this.createdAt = "",
    this.updatedAt = "",
    this.deletedAt = "",
    this.serviceAmount = 0,
    this.notificationId = "",
    this.cancellationCharges = 0,
    this.cancellationChargeAmount = 0,
    this.cancellationType = "",
    this.reason = "",
    this.refundAmount = 0,
    this.refundStatus = "",
    this.durationDiff = "",
    this.paidAmount = 0,
    this.bedAllocation = const [],
    this.bedPrice = 0,
    this.bedTypeName = "",
    this.bed = "",
    this.isBedPricePaid = false,
    this.bedDetails = const [],
    this.finalTotalAmount = 0,
  });

  factory AppointmentData.fromJson(Map<String, dynamic> json) {
    return AppointmentData(
      id: json['id'] is int ? json['id'] : -1,
      status: json['status'] is String ? json['status'] : "",
      startDateTime: json['start_date_time'] is String ? json['start_date_time'] : "",
      userId: json['user_id'] is int ? json['user_id'] : -1,
      userName: json['user_name'] is String ? json['user_name'] : "",
      userImage: json['user_image'] is String ? json['user_image'] : "",
      userMobile: json['user_mobile'] is String ? json['user_mobile'] : "",
      userPhone: json['user_phone'] is String ? json['user_phone'] : "",
      clinicId: json['clinic_id'] is int ? json['clinic_id'] : -1,
      clinicName: json['clinic_name'] is String ? json['clinic_name'] : "",
      doctorId: json['doctor_id'] is int ? json['doctor_id'] : -1,
      doctorName: json['doctor_name'] is String ? json['doctor_name'] : "",
      doctorImage: json['doctor_image'] is String ? json['doctor_image'] : "",
      doctorMobile: json['doctor_mobile'] is String ? json['doctor_mobile'] : "",
      doctorPhone: json['doctor_phone'] is String ? json['doctor_phone'] : "",
      appointmentDate: json['appointment_date'] is String ? json['appointment_date'] : "",
      appointmentTime: json['appointment_time'] is String ? json['appointment_time'] : "",
      endTime: json['end_time'] is String ? json['end_time'] : "",
      duration: json['duration'] is int ? json['duration'] : -1,
      serviceId: json['service_id'] is int ? json['service_id'] : -1,
      serviceName: json['service_name'] is String ? json['service_name'] : "",
      serviceType: json['service_type'] is String ? json['service_type'] : "",
      isVideoConsultancy: json['is_video_consultancy'] is bool ? json['is_video_consultancy'] : json['is_video_consultancy'] == 1,
      serviceImage: json['service_image'] is String ? json['service_image'] : "",
      categoryName: json['category_name'] is String ? json['category_name'] : "",
      appointmentExtraInfo: json['appointment_extra_info'] is String ? json['appointment_extra_info'] : "",
      totalAmount: json['total_amount'] is num ? json['total_amount'] : 0,
      servicePrice: json['service_price'] is num ? json['service_price'] : 0,
      discountType: json['discount_type'] is String ? json['discount_type'] : "",
      discountValue: json['discount_value'] is num ? json['discount_value'] : 0.0,
      discountAmount: json['discount_amount'] is num ? json['discount_amount'] : 0,
      subtotal: json['subtotal'] is num ? json['subtotal'] : 0,
      serviceTotal: json['service_total'] is num ? json['service_total'] : 0,
      billingFinalDiscountType: json['billing_final_discount_type'] is String ? json['billing_final_discount_type'] : "",
      enableFinalBillingDiscount: json['enable_final_billing_discount'] is bool ? json['enable_final_billing_discount'] : json['enable_final_billing_discount'] == 1,
      billingFinalDiscountValue: json['billing_final_discount_value'] is num ? json['billing_final_discount_value'] : 0,
      billingFinalDiscountAmount: json['billing_final_discount_amount'] is num ? json['billing_final_discount_amount'] : 0,
      paymentStatus: () {
        final int payStatus = json['payment_status'] is int ? json['payment_status'] : 0;
        final bool isCancelled = json['status'] == BookingStatusConst.CANCELLED;
        final bool isAdvanceEnabled = json['is_enable_advance_payment'] == 1 || json['is_enable_advance_payment'] == true;
        final int advanceStatus = json['advance_payment_status'] is int ? json['advance_payment_status'] : 0;
        final num remainingAmount = json['remaining_payable_amount'] is num ? json['remaining_payable_amount'] : 0;

        if (isCancelled) {
          if (payStatus == 1) return PaymentStatus.REFUNDED;
          if (isAdvanceEnabled && advanceStatus == 1 && payStatus == 0) {
            return PaymentStatus.ADVANCE_REFUNDED;
          }
        }
        if (payStatus == 1 && advanceStatus == 0) {
          return PaymentStatus.PAID;
        }
        if (isAdvanceEnabled && advanceStatus == 1) {
          if (remainingAmount > 0 || payStatus == 0) {
            return PaymentStatus.ADVANCE_PAID;
          }
          if (payStatus == 1) {
            return PaymentStatus.ADVANCE_PAID; // advance + full payment (treated as advance paid complete)
          }
        }
        if (payStatus == 0) return PaymentStatus.pending;
        return PaymentStatus.failed;
      }(),
      isEnableAdvancePayment: json['is_enable_advance_payment'] is bool ? json['is_enable_advance_payment'] : json['is_enable_advance_payment'] == 1,
      advancePaymentAmount: json['advance_payment_amount'] is num ? json['advance_payment_amount'] : 0,
      advancePaymentStatus: json['advance_payment_status'] is int ? json['advance_payment_status'] : 0,
      advancePaidAmount: json['advance_paid_amount'] is num ? json['advance_paid_amount'] : 0,
      remainingPayableAmount: json['remaining_payable_amount'] is num ? json['remaining_payable_amount'] : 0,
      googleLink: json['google_link'] is String ? json['google_link'] : "",
      zoomLink: json['zoom_link'] is String ? json['zoom_link'] : "",
      medicalReport: json['medical_report'] is List ? List<MedicalReport>.from(json['medical_report'].map((x) => MedicalReport.fromJson(x))) : [],
      encounterId: json['encounter_id'] is int ? json['encounter_id'] : -1,
      encounterDescription: json['encounter_description'] is String ? json['encounter_description'] : "",
      encounterStatus: json['encounter_status'] is bool ? json['encounter_status'] : json['encounter_status'] == 1,
      exclusiveTaxList: json['tax_data'] is List ? List<TaxPercentage>.from(json['tax_data'].map((x) => TaxPercentage.fromJson(x))) : [],
      totalExclusiveTax: json['total_tax'] is num ? json['total_tax'] : 0,
      totalInclusiveTax: json['total_inclusive_tax'] is num ? json['total_inclusive_tax'] : 0,
      billingItems: json['billing_items'] is List ? List<BillingItem>.from(json['billing_items'].map((x) => BillingItem.fromJson(x))) : [],
      reviews: json['reviews'] is Map ? DoctorReviewData.fromJson(json['reviews']) : null,
      bookForName: json['book_for_name'] is String ? json['book_for_name'] : "",
      booForImage: json['book_for_image'] is String ? json['book_for_image'] : "",
      createdBy: json['created_by'] is int ? json['created_by'] : -1,
      updatedBy: json['updated_by'] is int ? json['updated_by'] : -1,
      deletedBy: json['deleted_by'] is int ? json['deleted_by'] : -1,
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      deletedAt: json['deleted_at'] is String ? json['deleted_at'] : "",
      serviceAmount: json['service_amount'] is num ? json['service_amount'] : 0,
      cancellationCharges: json['cancellation_charge'] is num ? json['cancellation_charge'] : 0,
      cancellationChargeAmount: json['cancellation_charge_amount'] is num ? json['cancellation_charge_amount'] : 0,
      cancellationType: json['cancellation_type'] is String ? json['cancellation_type'] : '',
      reason: json['reason'] is String ? json['reason'] : "",
      refundAmount: json['refund_amount'] is num ? json['refund_amount'] : 0,
      refundStatus: json['refund_status'] is String ? json['refund_status'] : "",
      durationDiff: json['duration_diff'] is String ? json['duration_diff'] : "",
      bedAllocation: json['bed_allocation'] is List ? List<dynamic>.from(json['bed_allocation']) : [],
      bedPrice: json['bed_price'] is num ? json['bed_price'] : 0,
      bedTypeName: json['bed_type_name'] is String ? json['bed_type_name'] : "",
      bed: json['bed'] is String ? json['bed'] : "",
      isBedPricePaid: json['is_bed_price_paid'] is bool ? json['is_bed_price_paid'] : json['is_bed_price_paid'] == 1,
      bedDetails: json['bed_details'] is List ? List<BedDetails>.from(json['bed_details'].map((x) => BedDetails.fromJson(x))) : [],
      finalTotalAmount: json['final_total_amount'] is num ? json['final_total_amount'] : 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'status': status,
      'start_date_time': startDateTime,
      'user_id': userId,
      'user_name': userName,
      'user_image': userImage,
      'user_mobile': userMobile,
      'user_phone': userPhone,
      'clinic_id': clinicId,
      'clinic_name': clinicName,
      'doctor_id': doctorId,
      'doctor_name': doctorName,
      'doctor_image': doctorImage,
      'doctor_mobile': doctorMobile,
      'doctor_phone': doctorPhone,
      'appointment_date': appointmentDate,
      'appointment_time': appointmentTime,
      'end_time': endTime,
      'duration': duration,
      'service_id': serviceId,
      'service_name': serviceName,
      'service_type': serviceType,
      'is_video_consultancy': isVideoConsultancy ? 1 : 0,
      'service_image': serviceImage,
      'category_name': categoryName,
      'appointment_extra_info': appointmentExtraInfo,
      'total_amount': totalAmount,
      'service_price': servicePrice,
      'discount_type': discountType,
      'discount_value': discountValue,
      'discount_amount': discountAmount,
      'service_total': serviceTotal,
      'subtotal': subtotal,
      'billing_final_discount_type': billingFinalDiscountType,
      'enable_final_billing_discount': enableFinalBillingDiscount ? 1 : 0,
      'billing_final_discount_value': billingFinalDiscountValue,
      'billing_final_discount_amount': billingFinalDiscountAmount,
      'payment_status': paymentStatus,
      'is_enable_advance_payment': isEnableAdvancePayment ? 1 : 0,
      'advance_payment_amount': advancePaymentAmount,
      'advance_payment_status': advancePaymentStatus,
      'advance_paid_amount': advancePaidAmount,
      'remaining_payable_amount': remainingPayableAmount,
      'google_link': googleLink,
      'zoom_link': zoomLink,
      'medical_report': medicalReport.map((e) => e.toJson()).toList(),
      'encounter_id': encounterId,
      'encounter_description': encounterDescription,
      'encounter_status': encounterStatus ? 1 : 0,
      'total_tax': totalExclusiveTax,
      'total_inclusive_tax': totalInclusiveTax,
      'tax_data': exclusiveTaxList.map((e) => e.toJson()).toList(),
      'billing_items': billingItems.map((e) => e.toJson()).toList(),
      if (reviews != null) 'customer_review': reviews!.toJson(),
      'book_for_name': bookForName,
      'book_for_Image': booForImage,
      'created_by': createdBy,
      'updated_by': updatedBy,
      'deleted_by': deletedBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'deleted_at': deletedAt,
      'service_amount': serviceAmount,
      'cancellation_charge_amount': cancellationChargeAmount,
      'cancellation_charge': cancellationCharges,
      'cancellation_type': cancellationType,
      'reason': reason,
      'refund_amount': refundAmount,
      'refund_status': refundStatus,
      'duration_diff': durationDiff,
      'bed_allocation': bedAllocation,
      'bed_price': bedPrice,
      'bed_type_name': bedTypeName,
      'bed': bed,
      'is_bed_price_paid': isBedPricePaid,
      'final_total_amount' : finalTotalAmount,
    };
  }
}

class BedDetails {
  int bedAllocationId;
  int bedId;
  int bedTypeId;
  String bedName;
  String bedType;
  int charge;
  String assignDate;
  String dischargeDate;
  int perDayCharge;

  BedDetails({
    this.bedAllocationId = -1,
    this.bedId = -1,
    this.bedTypeId = -1,
    this.bedName = "",
    this.bedType = "",
    this.charge = 0,
    this.assignDate = "",
    this.dischargeDate = "",
    this.perDayCharge = -1,
  });

  factory BedDetails.fromJson(Map<String, dynamic> json) {
    return BedDetails(
      bedAllocationId:
      json['bed_allocation_id'] is int ? json['bed_allocation_id'] : -1,
      bedId: json['bed_id'] is int ? json['bed_id'] : -1,
      bedTypeId: json['bed_type_id'] is int ? json['bed_type_id'] : -1,
      bedName: json['bed_name'] is String ? json['bed_name'] : "",
      bedType: json['bed_type'] is String ? json['bed_type'] : "",
      charge: json['charge'] is int ? json['charge'] : -1,
      assignDate: json['assign_date'] is String ? json['assign_date'] : "",
      dischargeDate:
      json['discharge_date'] is String ? json['discharge_date'] : "",
      perDayCharge: json['per_day_charge'] is int ? json['per_day_charge'] : -1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'bed_allocation_id': bedAllocationId,
      'bed_id': bedId,
      'bed_type_id': bedTypeId,
      'bed_name': bedName,
      'bed_type': bedType,
      'charge': charge,
      'assign_date': assignDate,
      'discharge_date': dischargeDate,
      'per_day_charge': perDayCharge,
    };
  }
}

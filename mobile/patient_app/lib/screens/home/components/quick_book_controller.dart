import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/booking/model/booking_req.dart';
import 'package:kivicare_patient/screens/clinic/model/clinics_res_model.dart';
import 'package:kivicare_patient/screens/service/model/service_list_model.dart';
import 'package:kivicare_patient/screens/slots/components/appointment_summary_comp.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:kivicare_patient/utils/constants.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../api/core_apis.dart';

class QuickBookController extends GetxController {
  TextEditingController serviceCont = TextEditingController();
  TextEditingController clinicCont = TextEditingController();
  TextEditingController dateCont = TextEditingController();
  TextEditingController timeCont = TextEditingController();
  RxBool isLoading = false.obs;
  RxBool hasErrorFetchingServices = false.obs;
  RxString errorMessageServices = "".obs;
  RxBool hasErrorFetchingClinic = false.obs;
  RxString errorMessageClinic = "".obs;
  RxList<ServiceElement> serviceList = RxList();
  RxString searchService = "".obs;
  RxString searchClinic = "".obs;
  RxInt selectedServiceId = (-1).obs;
  RxInt selectedClinicId = (-1).obs;
  RxInt selectedDoctorId = (-1).obs;
  ServiceElement? serviceData;
  Clinic? selectedClinicData;
  RxString selectedService = "".obs;
  RxString selectedClinic = "".obs;
  RxString doctorName = "".obs;

  RxString selectedDate = DateTime.now().formatDateYYYYmmdd().obs;
  RxBool nextBtnVisible = false.obs;

  //get list of services
  Rx<Future<RxList<ServiceElement>>> servicesFuture = Future(() => RxList<ServiceElement>()).obs;
  RxList<ServiceElement> servicesList = RxList();

  //get list of clinic
  Rx<Future<RxList<Clinic>>> clinicFuture = Future(() => RxList<Clinic>()).obs;
  RxList<Clinic> clinicList = RxList();

  Rx<Future<RxList<String>>> slotsFuture = Future(() => RxList<String>()).obs;
  RxList<String> slots = RxList();
  RxString timeSlotsMessage = ''.obs;
  RxBool timeSlotsIsHoliday = false.obs;
  RxString selectedSlot = "".obs;
  RxString price = "".obs;
  // Cache of dates that are holidays/unavailable (YYYY-MM-DD)
  final Set<String> holidayDates = <String>{};

  // Validation error messages
  RxString serviceError = "".obs;
  RxString clinicError = "".obs;
  RxString dateError = "".obs;
  RxString timeError = "".obs;

  BookingReq bookingReq = BookingReq();
  RxBool hasMoreData = true.obs;
  RxInt currentPage = 1.obs;

  @override
  void onInit() {
    resetFields();
    super.onInit();
  }

  void resetFields() {
    serviceCont.clear();
    clinicCont.clear();
    dateCont.clear();
    timeCont.clear();

    selectedDate.value = '';
    selectedSlot.value = '';

    serviceList.clear();
    clinicList.clear();
    slots.clear();

    hasErrorFetchingServices.value = false;
    hasErrorFetchingClinic.value = false;
    isLoading.value = false;

    // Clear validation errors
    serviceError.value = "";
    clinicError.value = "";
    dateError.value = "";
    timeError.value = "";
  }

  Future<void> getServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await servicesFuture(
      CoreServiceApis.getServiceList(
        serviceList: serviceList,
        page: currentPage.value,
        search: searchService.value,
        enableAdvancePayment: 0,
        lastPageCallBack: (value) {
          hasMoreData.value = value;
        },
      ),
    ).then((value) {
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
    }).whenComplete(() => isLoading(false));
  }

  Future<void> getClinicList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await clinicFuture(CoreServiceApis.getClinics(clinics: clinicList, search: searchClinic.value, serviceId: selectedServiceId.value)).then((value) {
      clinicList(value);
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
    }).whenComplete(() => isLoading(false));
    if(clinicList.length == 1){
      clinicCont.text = clinicList.first.name;
      selectedClinic(clinicList.first.name);
      selectedClinicId(clinicList.first.id);
      selectedClinicData = clinicList.first;
    } else {
      clinicCont.text = '';
      selectedClinic('');
      selectedClinicId(-1);
      selectedClinicData = null;
    }
  }

  Future<void> getTimeSlot({bool showLoader = true}) async {
    if (showLoader) isLoading(true);
    try {
      if (serviceData != null && serviceData!.assignDoctor.isNotEmpty) {
        final doctor = serviceData!.assignDoctor.firstWhere(
          (element) => element.clinicId == selectedClinicId.value,
          orElse: () => serviceData!.assignDoctor.first,
        );

        selectedDoctorId.value = doctor.doctorId;
        doctorName.value = doctor.doctorName;
        price.value = doctor.priceDetail.totalAmount.toString();
      }

      final timeSlots = await slotsFuture(
        CoreServiceApis.getTimeSlots(
          slots: slots,
          date: selectedDate.value,
          serviceId: selectedServiceId.value,
          clinicId: selectedClinicId.value,
          doctorId: selectedDoctorId.value,
          messageHolder: timeSlotsMessage,
          isHolidayHolder: timeSlotsIsHoliday,
        ),
      );

      log('Fetched ${timeSlots.length} time slots');
    } catch (e) {
      log("getTimeSlots error: $e");
    } finally {
      if (showLoader) isLoading(false);
    }
  }

  bool isHoliday(DateTime day) {
    return holidayDates.contains(day.formatDateYYYYmmdd());
  }

  void onDateTimeChange() {
    final appointmentDateTime = "${selectedDate.value} ${selectedSlot.value}";
    if (appointmentDateTime.isValidDateTime) {
      nextBtnVisible(true);
    } else {
      nextBtnVisible(false);
    }
  }

  void bookAppointment() {
    //BookingReq
    bookingReq.clinicId = selectedClinicId.value.toString();
    bookingReq.serviceId = selectedServiceId.value.toString();
    bookingReq.appointmentDate = selectedDate.value;
    bookingReq.userId = loginUserData.value.id.toString();
    bookingReq.status = StatusConst.pending;
    bookingReq.doctorId = selectedDoctorId.value.toString();
    bookingReq.appointmentTime = selectedSlot.value;
    //
    bookingReq.serviceName = serviceData!.name;
    bookingReq.doctorName = doctorName.value;
    bookingReq.clinicName = selectedClinicData!.name;
    bookingReq.location = selectedClinicData!.address;
    bookingReq.totalAmount = price.value.toDouble();
    bookingReq.isEnableAdvancePayment = serviceData!.isEnableAdvancePayment;
    bookingReq.advancePayableAmount = serviceData!.advancePaymentAmount;
    bookingReq.isOnlineService = serviceData!.type.toLowerCase() == ServiceTypeConst.online;

    showInDialog(
      Get.context!,
      contentPadding: EdgeInsets.zero,
      builder: (_) {
        return AppointmentSummaryWidget(
          bookingData: bookingReq,
          isQuickBook: true,
        );
      },
    ).then((value) {
      dispose();
    });
  }

  void setSearchService(String value) {
    searchService.value = value;
    currentPage.value = 1;
    serviceList.clear();
  }

  @override
  void dispose() {
    serviceCont.clear();
    clinicCont.clear();
    dateCont.clear();
    timeCont.clear();
    slots.clear();
    selectedSlot.value = "";
    selectedServiceId(-1);
    selectedClinicId(-1);
    selectedDoctorId(-1);
    currentPage(1);
    hasMoreData(false);
    super.dispose();
  }
}

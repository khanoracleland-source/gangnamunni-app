// ignore_for_file: depend_on_referenced_packages

import 'dart:async';
import 'dart:ui';

import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';

import '../../api/auth_apis.dart';
import '../../generated/assets.dart';
import '../../utils/common_base.dart';
import '../category/model/category_list_model.dart';
import '../home/home_controller.dart';
import 'model/appointment_status_model.dart';
import 'model/appointments_res_model.dart';

class AppointmentsController extends GetxController {
  RxBool isLoading = false.obs;
  RxBool isLastPage = false.obs;
  Rx<Future<RxList<AppointmentData>>> getAppointments = Future(() => RxList<AppointmentData>()).obs;
  RxList<AppointmentData> appointments = RxList();

  // Keep an unfiltered copy for client-side filters
  final RxList<AppointmentData> _allAppointments = RxList();
  Rx<CategoryElement> selectedCategory = CategoryElement().obs;
  RxInt page = 1.obs;
  RxInt clinicId = 0.obs;
  RxInt doctorId = 0.obs;
  RxInt selectedServiceId = 0.obs;
  RxString firstDate = ''.obs;
  RxString lastDate = "".obs;
  RxString serviceType = "".obs;
  RxString priceMin = ''.obs;
  RxString priceMax = ''.obs;
  RxString consolationType = "".obs;
  RxString paymentStatus = "".obs;
  RxSet<String> selectedStatus = RxSet();
  RxSet<String> selectedService = RxSet();
  RxInt filterCount = 0.obs;

  // Filters
  RxInt filterClinicId = (-1).obs;
  RxInt filterDoctorId = (-1).obs;
  RxString filterDate = ''.obs; // yyyy-MM-dd
  RxString filterService = ''.obs; // service name contains
  RxString filterCategory = ''.obs; // category name contains
  RxString filterConsultationType = ''.obs; // online/offline
  RxString filterPaymentStatus = ''.obs; // paid/pending/failed/advance_paid

  //Tabs
  // RxList<AppointmentStatusModel> filterStatus = RxList();
  Rx<AppointmentStatusModel> selectedTab = AppointmentStatusModel(icon: Assets.iconsIcMenu, name: locale.value.all.obs).obs;

  @override
  void onInit() {
    if (filterStatus.isNotEmpty) {
      selectedTab(filterStatus.first);
      getAppointmentList(showLoader: false);
    }
    super.onInit();
  }

  Future<void> getAppointmentList({bool showLoader = true, String search = "", String status = ""}) async {
    if (showLoader) {
      isLoading(true);
    }
    await getAppointments(
      CoreServiceApis.getAppointmentList(
        filterByStatus: selectedTab.value.name!.toLowerCase(),
        lastDate: lastDate.value,
        firstDate: firstDate.value,
        paymentStatus: paymentStatus.value,
        categoryID: selectedCategory.value.id,
        doctorId: doctorId.value,
        clinicId: clinicId.value,
        consolationType: consolationType.value,
        serviceID: selectedServiceId.value,
        page: page.value,
        appointments: appointments,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {}).catchError((e) {
      isLoading(false);
      log('getAppointments E: $e');
    }).whenComplete(() {
      // Preserve an unfiltered copy and apply filters locally
      _allAppointments.clear();
      _allAppointments.addAll(appointments);
      _applyLocalFilters();
      isLoading(false);
    });
  }

  void clearFilters() {
    filterClinicId(-1);
    filterDoctorId(-1);
    filterDate('');
    filterService('');
    filterCategory('');
    filterConsultationType('');
    filterPaymentStatus('');
    _applyLocalFilters();
  }

  void _applyLocalFilters() {
    List<AppointmentData> list = List.from(_allAppointments);
    // Clinic
    if (filterClinicId.value > 0) {
      list = list.where((a) => a.clinicId == filterClinicId.value).toList();
    }
    // Doctor
    if (filterDoctorId.value > 0) {
      list = list.where((a) => a.doctorId == filterDoctorId.value).toList();
    }
    // Date (exact match)
    if (filterDate.value.trim().isNotEmpty) {
      list = list.where((a) => a.appointmentDate == filterDate.value.trim()).toList();
    }
    // Service name contains
    if (filterService.value.trim().isNotEmpty) {
      final q = filterService.value.trim().toLowerCase();
      list = list.where((a) => a.serviceName.toLowerCase().contains(q)).toList();
    }
    // Category name contains
    if (filterCategory.value.trim().isNotEmpty) {
      final q = filterCategory.value.trim().toLowerCase();
      list = list.where((a) => a.categoryName.toLowerCase().contains(q)).toList();
    }
    // Consultation type (online/offline) matches serviceType
    if (filterConsultationType.value.trim().isNotEmpty) {
      final q = filterConsultationType.value.trim().toLowerCase();
      list = list.where((a) => a.serviceType.toLowerCase() == q).toList();
    }
    // Payment status
    if (filterPaymentStatus.value.trim().isNotEmpty) {
      final q = filterPaymentStatus.value.trim().toLowerCase();
      list = list.where((a) => a.paymentStatus.toLowerCase() == q).toList();
    }
    appointments(list);
  }

  // Public API for UI to set filters and apply
  void setClinicFilter(int clinicId) {
    filterClinicId(clinicId);
    _applyLocalFilters();
  }

  void setDoctorFilter(int doctorId) {
    filterDoctorId(doctorId);
    _applyLocalFilters();
  }

  void setDateFilter(String date) {
    filterDate(date);
    _applyLocalFilters();
  }

  void setServiceFilter(String name) {
    filterService(name);
    _applyLocalFilters();
  }

  void setCategoryFilter(String name) {
    filterCategory(name);
    _applyLocalFilters();
  }

  void setConsultationTypeFilter(String type) {
    filterConsultationType(type);
    _applyLocalFilters();
  }

  void setPaymentStatusFilter(String status) {
    filterPaymentStatus(status);
    _applyLocalFilters();
  }

  Future<void> updateStatus({required int appointmentId, required String status, VoidCallback? onUpdateBooking}) async {
    isLoading(true);
    hideKeyBoardWithoutContext();

    final Map<String, dynamic> req = {
      "status": status,
    };

    await CoreServiceApis.updateStatus(request: req, appointmentId: appointmentId).then((value) async {
      if (onUpdateBooking != null) {
        onUpdateBooking.call();
        toast(locale.value.appointmentCancelSuccessfully);
      }
      try {
        final HomeController hCont = Get.find();
        hCont.init();
      } catch (e) {
        log('onItemSelected Err: $e');
      }
      try {
        AuthServiceApis.getUserWallet();
      } catch (e) {
        log('onItemSelected Err: $e');
      }
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
      toast(e.toString(), print: true);
    });
  }
}

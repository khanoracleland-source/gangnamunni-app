// ignore_for_file: depend_on_referenced_packages

import 'dart:async';
import 'dart:ui';

import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';

import '../../api/auth_apis.dart';
import '../../generated/assets.dart';
import '../../services/app_cache_service.dart';
import '../../utils/api_end_points.dart';
import '../../utils/common_base.dart';
import '../../utils/constants.dart';
import '../../utils/local_storage.dart';
import '../category/model/category_list_model.dart';
import '../home/home_controller.dart';
import 'model/appointment_status_model.dart';
import 'model/appointments_res_model.dart';

class AppointmentsController extends GetxController {
  AppointmentsController({AppCacheService? cache})
      : _cache = cache ?? AppCacheService(localStorage);

  final AppCacheService _cache;

  RxBool isLoading = false.obs;
  RxBool isLastPage = false.obs;
  Rx<Future<RxList<AppointmentData>>> getAppointments =
      Future(() => RxList<AppointmentData>()).obs;
  RxList<AppointmentData> appointments = RxList();
  String? _freshRequestKey;
  String? _freshRequestScope;
  Future<RxList<AppointmentData>>? _freshRequest;
  int _requestGeneration = 0;

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
  Rx<AppointmentStatusModel> selectedTab = AppointmentStatusModel(
          icon: Assets.iconsIcMenu, name: locale.value.all.obs)
      .obs;

  @override
  void onInit() {
    if (filterStatus.isNotEmpty) {
      selectedTab(filterStatus.first);
      getAppointmentList(showLoader: false);
    }
    super.onInit();
  }

  Future<void> getAppointmentList({
    bool showLoader = true,
    String search = "",
    String status = "",
    bool forceRefresh = false,
  }) async {
    if (isClosed) return;
    final request = _AppointmentListRequest(
      page: page.value,
      perPage: Constants.perPageItem,
      filterByStatus: selectedTab.value.type.name,
      search: search.trim(),
      lastDate: lastDate.value,
      firstDate: firstDate.value,
      paymentStatus: paymentStatus.value,
      categoryId: selectedCategory.value.id,
      doctorId: doctorId.value,
      clinicId: clinicId.value,
      consolationType: consolationType.value,
      serviceId: selectedServiceId.value,
    );
    final cacheKey = _appointmentCacheKey(request);
    final cached = !forceRefresh && request.page == 1
        ? _cache.read<_CachedAppointmentList>(cacheKey, _decodeAppointmentList)
        : null;

    if (cached != null) {
      appointments.assignAll(cached.data.appointments);
      isLastPage(cached.data.isLastPage);
      _allAppointments
        ..clear()
        ..addAll(appointments);
      _applyLocalFilters();
      isLoading(false);
      getAppointments(Future.value(appointments));
      unawaited(
        _refreshAppointmentList(
          request: request,
          cacheKey: cacheKey,
          showLoader: false,
          keepVisibleDataOnError: true,
        ).catchError((e) {
          log('getAppointments background refresh E: $e');
          return appointments;
        }),
      );
      return;
    }

    final freshRequest = _refreshAppointmentList(
      request: request,
      cacheKey: cacheKey,
      showLoader: showLoader,
      keepVisibleDataOnError: false,
    );
    getAppointments(freshRequest);
    await freshRequest.catchError((e) {
      log('getAppointments E: $e');
      return appointments;
    });
  }

  String _appointmentCacheKey(_AppointmentListRequest request) {
    return AppCacheService.buildKey(
      endpoint: APIEndPoints.getAppointments,
      queryParameters: {
        'page': request.page,
        'per_page': request.perPage,
        'status': request.filterByStatus,
        'search': request.search,
        'last_date': request.lastDate,
        'first_date': request.firstDate,
        'payment_status': request.paymentStatus,
        'category_id': request.categoryId,
        'doctor_id': request.doctorId,
        'clinic_id': request.clinicId,
        'consultation_type': request.consolationType,
        'service_id': request.serviceId,
      },
    );
  }

  _CachedAppointmentList _decodeAppointmentList(Map<String, dynamic> json) {
    final data = json['data'];
    if (data is! List) {
      throw const FormatException('Invalid appointments cache');
    }
    return _CachedAppointmentList(
      appointments: data
          .map((appointment) => AppointmentData.fromJson(
                Map<String, dynamic>.from(appointment as Map),
              ))
          .toList(),
      isLastPage: json['isLastPage'] == true,
    );
  }

  Map<String, dynamic> _encodeAppointmentList({
    required List<AppointmentData> data,
    required bool isLastPage,
  }) {
    return {
      'data': data.map((appointment) => appointment.toJson()).toList(),
      'isLastPage': isLastPage,
    };
  }

  Future<RxList<AppointmentData>> _refreshAppointmentList({
    required _AppointmentListRequest request,
    required String cacheKey,
    required bool showLoader,
    required bool keepVisibleDataOnError,
  }) {
    if (_freshRequestKey == cacheKey && _freshRequest != null) {
      return _freshRequest!;
    }

    final generation = ++_requestGeneration;
    _freshRequestKey = cacheKey;
    _freshRequestScope =
        AppCacheService.buildKey(endpoint: APIEndPoints.getAppointments);
    if (showLoader) {
      isLoading(true);
    }

    final future = _fetchFreshAppointmentList(
      request: request,
      cacheKey: cacheKey,
      generation: generation,
      keepVisibleDataOnError: keepVisibleDataOnError,
    );
    _freshRequest = future;
    return future;
  }

  bool _isCurrentRequest(String cacheKey, int generation) {
    return !isClosed &&
        generation == _requestGeneration &&
        _freshRequestKey == cacheKey &&
        _freshRequestScope ==
            AppCacheService.buildKey(endpoint: APIEndPoints.getAppointments);
  }

  Future<RxList<AppointmentData>> _fetchFreshAppointmentList({
    required _AppointmentListRequest request,
    required String cacheKey,
    required int generation,
    required bool keepVisibleDataOnError,
  }) async {
    bool freshIsLastPage = isLastPage.value;

    try {
      final targetAppointments =
          request.page == 1 ? <AppointmentData>[] : _allAppointments.toList();
      final freshAppointments = await CoreServiceApis.getAppointmentList(
        filterByStatus: request.filterByStatus,
        lastDate: request.lastDate,
        firstDate: request.firstDate,
        paymentStatus: request.paymentStatus,
        categoryID: request.categoryId,
        doctorId: request.doctorId,
        clinicId: request.clinicId,
        consolationType: request.consolationType,
        serviceID: request.serviceId,
        page: request.page,
        perPage: request.perPage,
        search: request.search,
        appointments: targetAppointments,
        lastPageCallBack: (value) {
          freshIsLastPage = value;
        },
      );

      if (!_isCurrentRequest(cacheKey, generation)) {
        return appointments;
      }

      if (request.page == 1) {
        unawaited(
          _cache.write(
            cacheKey,
            _encodeAppointmentList(
              data: freshAppointments,
              isLastPage: freshIsLastPage,
            ),
            ttl: AppCacheService.appointmentsTtl,
            maxStale: AppCacheService.appointmentsMaxStale,
          ),
        );
      }
      isLastPage(freshIsLastPage);
      _allAppointments.assignAll(freshAppointments);
      _applyLocalFilters();
      return appointments;
    } catch (e) {
      if (_isCurrentRequest(cacheKey, generation)) {
        log('getAppointments E: $e');
      }
      if (keepVisibleDataOnError) {
        return appointments;
      }
      rethrow;
    } finally {
      if (!isClosed && generation == _requestGeneration) {
        isLoading(false);
        _freshRequest = null;
        _freshRequestKey = null;
        _freshRequestScope = null;
      }
    }
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
      list = list
          .where((a) => a.appointmentDate == filterDate.value.trim())
          .toList();
    }
    // Service name contains
    if (filterService.value.trim().isNotEmpty) {
      final q = filterService.value.trim().toLowerCase();
      list =
          list.where((a) => a.serviceName.toLowerCase().contains(q)).toList();
    }
    // Category name contains
    if (filterCategory.value.trim().isNotEmpty) {
      final q = filterCategory.value.trim().toLowerCase();
      list =
          list.where((a) => a.categoryName.toLowerCase().contains(q)).toList();
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
    page(1); // BUG FIX #2: Reset page when filter changes
    filterClinicId(clinicId);
    _applyLocalFilters();
  }

  void setDoctorFilter(int doctorId) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterDoctorId(doctorId);
    _applyLocalFilters();
  }

  void setDateFilter(String date) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterDate(date);
    _applyLocalFilters();
  }

  void setServiceFilter(String name) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterService(name);
    _applyLocalFilters();
  }

  void setCategoryFilter(String name) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterCategory(name);
    _applyLocalFilters();
  }

  void setConsultationTypeFilter(String type) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterConsultationType(type);
    _applyLocalFilters();
  }

  void setPaymentStatusFilter(String status) {
    page(1); // BUG FIX #2: Reset page when filter changes
    filterPaymentStatus(status);
    _applyLocalFilters();
  }

  Future<void> updateStatus(
      {required int appointmentId,
      required String status,
      VoidCallback? onUpdateBooking}) async {
    isLoading(true);
    hideKeyBoardWithoutContext();

    final Map<String, dynamic> req = {
      "status": status,
    };

    await CoreServiceApis.updateStatus(
            request: req, appointmentId: appointmentId)
        .then((value) async {
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

  @override
  void onClose() {
    _requestGeneration++;
    // BUG FIX #3 & #5: Clear RxList in onClose to prevent memory leak
    appointments.clear();
    _allAppointments.clear();
    _freshRequest = null;
    _freshRequestKey = null;
    _freshRequestScope = null;
    super.onClose();
  }
}

class _AppointmentListRequest {
  const _AppointmentListRequest({
    required this.page,
    required this.perPage,
    required this.filterByStatus,
    required this.search,
    required this.lastDate,
    required this.firstDate,
    required this.paymentStatus,
    required this.categoryId,
    required this.doctorId,
    required this.clinicId,
    required this.consolationType,
    required this.serviceId,
  });

  final int page;
  final int perPage;
  final String filterByStatus;
  final String search;
  final String lastDate;
  final String firstDate;
  final String paymentStatus;
  final int categoryId;
  final int doctorId;
  final int clinicId;
  final String consolationType;
  final int serviceId;
}

class _CachedAppointmentList {
  const _CachedAppointmentList({
    required this.appointments,
    required this.isLastPage,
  });

  final List<AppointmentData> appointments;
  final bool isLastPage;
}

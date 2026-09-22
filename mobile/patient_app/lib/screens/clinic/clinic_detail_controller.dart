// ignore_for_file: depend_on_referenced_packages
import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import '../../services/app_cache_service.dart';
import '../../utils/api_end_points.dart';
import '../../utils/local_storage.dart';
import '../doctor/model/doctor_list_res.dart';
import '../service/model/service_list_model.dart';
import 'model/clinic_detail_model.dart';
import 'model/clinics_res_model.dart';

class ClinicDetailController extends GetxController {
  ClinicDetailController({
    AppCacheService? cache,
    Future<ClinicDetailModel> Function(int clinicId)? detailLoader,
  })  : _cache = cache ?? AppCacheService(localStorage),
        _detailLoader = detailLoader ??
            ((clinicId) => CoreServiceApis.getClinicDetails(
                  clinicId: clinicId,
                ));

  final AppCacheService _cache;
  final Future<ClinicDetailModel> Function(int clinicId) _detailLoader;

  RxBool isLoading = false.obs;

  Rx<Future<ClinicDetailModel>> getClinicDetail = Future(
          () => ClinicDetailModel(data: Clinic(clinicSession: ClinicSession())))
      .obs;
  Rx<Clinic> clinicData = Clinic(clinicSession: ClinicSession()).obs;
  String? _detailRequestKey;
  Future<ClinicDetailModel>? _detailRequest;
  int _detailGeneration = 0;

  //Services
  Rx<Future<RxList<ServiceElement>>> serviceListFuture =
      Future(() => RxList<ServiceElement>()).obs;
  RxBool isServicesLoading = false.obs;
  RxList<ServiceElement> serviceList = RxList();
  RxBool isServicesLastPage = false.obs;
  RxInt servicesPage = 1.obs;
  RxInt activeServicesCount = 0.obs;

  //Doctors
  Rx<Future<RxList<Doctor>>> doctorsFuture = Future(() => RxList<Doctor>()).obs;
  RxBool isDoctorsLoading = false.obs;
  RxList<Doctor> doctors = RxList();
  RxBool isDoctorsLastPage = false.obs;
  RxInt doctorsPage = 1.obs;
  RxInt activeDoctorsCount = 0.obs;

  @override
  void onInit() {
    if (Get.arguments is Clinic) {
      clinicData(Get.arguments);
    }
    init(showLoader: false);
    super.onInit();
  }

  ///Get Clinic Detail
  Future<void> init({bool showLoader = true, bool forceRefresh = false}) async {
    final clinicId = clinicData.value.id;
    if (clinicId.isNegative) {
      return;
    }

    final cacheKey = AppCacheService.buildKey(
      endpoint: APIEndPoints.getClinicDetails,
      queryParameters: {'clinic_id': clinicId},
    );

    if (!forceRefresh) {
      final cached = _cache.read<ClinicDetailModel>(
        cacheKey,
        _decodeClinicDetail,
      );
      if (cached != null) {
        clinicData(cached.data.data);
        isLoading(false);
        getClinicDetail(Future.value(cached.data));
        _loadClinicChildren();
        unawaited(
          _refreshClinicDetail(
            clinicId: clinicId,
            cacheKey: cacheKey,
            showLoader: false,
            keepVisibleDataOnError: true,
          ).catchError((e) {
            log('ClinicDetail background refresh err ==> $e');
            return ClinicDetailModel(data: clinicData.value);
          }),
        );
        return;
      }
    }

    final freshRequest = _refreshClinicDetail(
      clinicId: clinicId,
      cacheKey: cacheKey,
      showLoader: showLoader,
      keepVisibleDataOnError: false,
    );
    getClinicDetail(freshRequest);
    await freshRequest.catchError((e) {
      log('ClinicDetail getClinicDetail err ==> $e');
      isLoading(false);
      return ClinicDetailModel(data: clinicData.value);
    });
  }

  ClinicDetailModel _decodeClinicDetail(Map<String, dynamic> json) {
    if (json['data'] is! Map) {
      throw const FormatException('Invalid clinic detail cache');
    }
    return ClinicDetailModel.fromJson(json);
  }

  Future<ClinicDetailModel> _refreshClinicDetail({
    required int clinicId,
    required String cacheKey,
    required bool showLoader,
    required bool keepVisibleDataOnError,
  }) {
    if (_detailRequestKey == cacheKey && _detailRequest != null) {
      return _detailRequest!;
    }

    final generation = ++_detailGeneration;
    _detailRequestKey = cacheKey;
    if (showLoader) {
      isLoading(true);
    }

    final future = _fetchFreshClinicDetail(
      clinicId: clinicId,
      cacheKey: cacheKey,
      generation: generation,
      keepVisibleDataOnError: keepVisibleDataOnError,
    );
    _detailRequest = future;
    return future;
  }

  bool _isCurrentClinicDetail(String cacheKey, int generation) {
    return !isClosed &&
        generation == _detailGeneration &&
        _detailRequestKey == cacheKey;
  }

  Future<ClinicDetailModel> _fetchFreshClinicDetail({
    required int clinicId,
    required String cacheKey,
    required int generation,
    required bool keepVisibleDataOnError,
  }) async {
    try {
      final fresh = await _detailLoader(clinicId);
      if (!_isCurrentClinicDetail(cacheKey, generation)) {
        return ClinicDetailModel(data: clinicData.value);
      }
      clinicData(fresh.data);
      getClinicDetail(Future.value(fresh));
      isLoading(false);
      _loadClinicChildren();
      unawaited(
        _cache.write(
          cacheKey,
          fresh.toJson(),
          ttl: AppCacheService.clinicDetailTtl,
          maxStale: AppCacheService.clinicDetailMaxStale,
        ),
      );
      return fresh;
    } catch (e) {
      if (_isCurrentClinicDetail(cacheKey, generation)) {
        log('ClinicDetail getClinicDetail err ==> $e');
      }
      if (keepVisibleDataOnError) {
        return ClinicDetailModel(data: clinicData.value);
      }
      rethrow;
    } finally {
      if (!isClosed && generation == _detailGeneration) {
        isLoading(false);
        _detailRequest = null;
        _detailRequestKey = null;
      }
    }
  }

  void _loadClinicChildren() {
    getServiceList(showLoader: false);
    getDoctors(showLoader: false);
  }

  Future<void> getServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isServicesLoading(true);
    }
    await serviceListFuture(
      CoreServiceApis.getServiceList(
        page: servicesPage.value,
        serviceList: serviceList,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {
          isServicesLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
      // Load all services to get accurate count
      getAllServicesForCount();
    }).catchError((e) {
      log('Clinic detail getServiceList err ==> $e');
    }).whenComplete(() => isServicesLoading(false));
  }

  /// Load all services for counting active services
  Future<void> getAllServicesForCount() async {
    try {
      final RxList<ServiceElement> allServicesList = RxList();
      await CoreServiceApis.getServiceList(
        serviceList: allServicesList,
        clinicId: clinicData.value.id,
        allServices: 'all',
        lastPageCallBack: (p0) {},
      );
      activeServicesCount(
          allServicesList.where((service) => service.status == 1).length);
    } catch (e) {
      log('Error loading all services for count: $e');
      activeServicesCount(
          serviceList.where((service) => service.status == 1).length);
    }
  }

  /// Load all doctors for counting active doctors
  Future<void> getAllDoctorsForCount() async {
    try {
      final RxList<Doctor> allDoctorsList = RxList();
      await CoreServiceApis.getDoctors(
        perPage: 1000,
        doctors: allDoctorsList,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {},
      );
      activeDoctorsCount(
          allDoctorsList.where((doctor) => doctor.status == 1).length);
    } catch (e) {
      activeDoctorsCount(doctors.where((doctor) => doctor.status == 1).length);
    }
  }

  Future<void> getDoctors({bool showLoader = true}) async {
    if (showLoader) {
      isDoctorsLoading(true);
    }
    await doctorsFuture(
      CoreServiceApis.getDoctors(
        page: doctorsPage.value,
        doctors: doctors,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {
          isDoctorsLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
      // Load all doctors to get accurate count
      getAllDoctorsForCount();
    }).catchError((e) {
      log("getDoctors error $e");
    }).whenComplete(() => isDoctorsLoading(false));
  }

  @override
  void onClose() {
    _detailGeneration++;
    super.onClose();
  }
}

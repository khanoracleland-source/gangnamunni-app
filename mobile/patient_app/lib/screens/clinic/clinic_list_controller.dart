import 'dart:async';

import 'package:flutter/cupertino.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:stream_transform/stream_transform.dart';
import 'package:kivicare_patient/api/core_apis.dart';
import '../../services/app_cache_service.dart';
import '../../utils/api_end_points.dart';
import '../../utils/local_storage.dart';
import '../service/model/service_list_model.dart';
import 'model/clinic_detail_model.dart';
import 'model/clinics_res_model.dart';

class ClinicListController extends GetxController {
  ClinicListController({AppCacheService? cache})
      : _clinicCache = cache ?? AppCacheService(localStorage);

  static const int _clinicPerPage = 10;

  final AppCacheService _clinicCache;

  Rx<Future<RxList<Clinic>>> clinicsFuture = Future(() => RxList<Clinic>()).obs;
  RxBool isLoading = false.obs;
  RxList<Clinic> clinics = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;
  Rx<ServiceElement> service = ServiceElement().obs;
  RxList selectedServicesId = RxList();
  Rx<Clinic> selectedClinic = Clinic(clinicSession: ClinicSession()).obs;

  ///Search
  TextEditingController searchClinicCont = TextEditingController();
  RxBool isSearchClinicText = false.obs;
  StreamController<String> searchClinicStream = StreamController<String>();
  final _scrollController = ScrollController();
  StreamSubscription<String>? _searchSubscription;
  RxBool isSearchText = false.obs;
  RxInt isPopular = (-1).obs;
  RxString serviceType = "".obs;
  RxString priceMin = ''.obs;
  RxString priceMax = ''.obs;
  RxInt clinicId = (-1).obs;
  String? _freshRequestKey;
  Future<RxList<Clinic>>? _freshRequest;
  int _requestGeneration = 0;

  @override
  void onInit() {
    _scrollController.addListener(
        () => Get.context != null ? hideKeyboard(Get.context) : null);
    _searchSubscription = searchClinicStream.stream
        .debounce(const Duration(seconds: 1))
        .listen((s) {
      if (isClosed) return;
      // BUG FIX #1: Reset page to 1 when search changes
      page(1);
      getClinicList();
    });
    if (Get.arguments is ServiceElement) {
      service(Get.arguments);
    } else if (Get.arguments is Map) {
      getClinicList();
    } else if (Get.arguments is int) {
      clinicId(Get.arguments as int);
      log('clinicId==== $clinicId');
      getClinicList();
    }
    getClinicList();
    super.onInit();
  }

  Future<void> getClinicList({bool showLoader = true}) async {
    if (isClosed) return;
    final request = _ClinicListRequest(
      page: page.value,
      serviceId: service.value.id,
      search: searchClinicCont.text.trim(),
      servicePriceMin: priceMin.value,
      servicePriceMax: priceMax.value,
      clinicId: clinicId.value,
      isPopular: isPopular.value,
    );
    final cacheKey = _clinicCacheKey(request);
    final cached = request.page == 1
        ? _clinicCache.read<_CachedClinicList>(cacheKey, _decodeClinicList)
        : null;

    if (cached != null) {
      clinics.assignAll(cached.data.clinics);
      isLastPage(cached.data.isLastPage);
      isLoading(false);
      clinicsFuture(Future.value(clinics));
      unawaited(
        _refreshClinicList(
          request: request,
          cacheKey: cacheKey,
          showLoader: false,
          keepVisibleDataOnError: true,
        ).catchError((e) {
          log("getClinics background refresh error $e");
          return clinics;
        }),
      );
      return;
    }

    final freshRequest = _refreshClinicList(
      request: request,
      cacheKey: cacheKey,
      showLoader: showLoader,
      keepVisibleDataOnError: false,
    );
    clinicsFuture(freshRequest);
    await freshRequest.catchError((e) {
      log("getClinics error $e");
      return clinics;
    });
  }

  String _clinicCacheKey(_ClinicListRequest request) {
    return AppCacheService.buildKey(
      endpoint: APIEndPoints.getClinicList,
      queryParameters: {
        'page': request.page,
        'per_page': _clinicPerPage,
        'service_id': request.serviceId,
        'search': request.search,
        'is_price_min': request.servicePriceMin,
        'is_price_max': request.servicePriceMax,
        'clinic_id': request.clinicId,
        'is_popular': request.isPopular,
      },
    );
  }

  _CachedClinicList _decodeClinicList(Map<String, dynamic> json) {
    final data = json['data'];
    if (data is! List) {
      throw const FormatException('Invalid clinic cache');
    }
    return _CachedClinicList(
      clinics: data
          .map((clinic) =>
              Clinic.fromJson(Map<String, dynamic>.from(clinic as Map)))
          .toList(),
      isLastPage: json['isLastPage'] == true,
    );
  }

  Map<String, dynamic> _encodeClinicList({
    required List<Clinic> data,
    required bool isLastPage,
  }) {
    return {
      'data': data.map((clinic) => clinic.toJson()).toList(),
      'isLastPage': isLastPage,
    };
  }

  Future<RxList<Clinic>> _refreshClinicList({
    required _ClinicListRequest request,
    required String cacheKey,
    required bool showLoader,
    required bool keepVisibleDataOnError,
  }) {
    if (_freshRequestKey == cacheKey && _freshRequest != null) {
      return _freshRequest!;
    }

    final generation = ++_requestGeneration;
    _freshRequestKey = cacheKey;
    if (showLoader) {
      isLoading(true);
    }

    final future = _fetchFreshClinicList(
      request: request,
      cacheKey: cacheKey,
      generation: generation,
      keepVisibleDataOnError: keepVisibleDataOnError,
    );
    _freshRequest = future;
    return future;
  }

  bool _isCurrentRequest(String cacheKey, int generation) {
    // BUG FIX #2: Use stored key instead of rebuilding to avoid race conditions
    return !isClosed &&
        generation == _requestGeneration &&
        cacheKey == _freshRequestKey;
  }

  Future<RxList<Clinic>> _fetchFreshClinicList({
    required _ClinicListRequest request,
    required String cacheKey,
    required int generation,
    required bool keepVisibleDataOnError,
  }) async {
    bool freshIsLastPage = isLastPage.value;

    try {
      final targetClinics = request.page == 1 ? <Clinic>[] : clinics.toList();
      final freshClinics = await CoreServiceApis.getClinics(
        page: request.page,
        perPage: _clinicPerPage,
        clinics: targetClinics,
        serviceId: request.serviceId,
        search: request.search,
        servicePriceMin: request.servicePriceMin,
        servicePriceMax: request.servicePriceMax,
        clinicId: request.clinicId,
        isPopulars: request.isPopular,
        lastPageCallBack: (value) {
          freshIsLastPage = value;
        },
      );

      if (!_isCurrentRequest(cacheKey, generation)) {
        return clinics;
      }

      clinics.assignAll(freshClinics);
      if (request.page == 1) {
        unawaited(
          _clinicCache.write(
            cacheKey,
            _encodeClinicList(data: clinics, isLastPage: freshIsLastPage),
            ttl: AppCacheService.clinicListTtl,
            maxStale: AppCacheService.clinicListMaxStale,
          ),
        );
      }
      isLastPage(freshIsLastPage);
      log('value.length ==> ${clinics.length}');
      return clinics;
    } catch (e) {
      if (_isCurrentRequest(cacheKey, generation)) {
        log("getClinics error $e");
      }
      if (keepVisibleDataOnError) {
        return clinics;
      }
      rethrow;
    } finally {
      if (!isClosed && generation == _requestGeneration) {
        isLoading(false);
        _freshRequest = null;
        _freshRequestKey = null;
      }
    }
  }

  @override
  void onClose() {
    _requestGeneration++;
    _searchSubscription?.cancel();
    _searchSubscription = null;
    unawaited(searchClinicStream.close());
    searchClinicCont.dispose();
    _scrollController.dispose();
    _freshRequest = null;
    _freshRequestKey = null;
    super.onClose();
  }
}

class _ClinicListRequest {
  const _ClinicListRequest({
    required this.page,
    required this.serviceId,
    required this.search,
    required this.servicePriceMin,
    required this.servicePriceMax,
    required this.clinicId,
    required this.isPopular,
  });

  final int page;
  final int serviceId;
  final String search;
  final String servicePriceMin;
  final String servicePriceMax;
  final int clinicId;
  final int isPopular;
}

class _CachedClinicList {
  const _CachedClinicList({
    required this.clinics,
    required this.isLastPage,
  });

  final List<Clinic> clinics;
  final bool isLastPage;
}

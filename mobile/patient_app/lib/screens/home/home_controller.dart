import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/home_apis.dart';
import '../../services/app_cache_service.dart';
import '../../services/cache_first_controller.dart';
import '../../utils/api_end_points.dart';
import '../../utils/app_common.dart';
import '../../utils/local_storage.dart';
import '../dashboard/dashboard_controller.dart';
import 'model/dashboard_res_model.dart';

class HomeController extends CacheFirstController<DashboardRes> {
  HomeController(
      {AppCacheService? cache, Future<DashboardRes> Function()? loader})
      : super(
          cache: cache ?? AppCacheService(localStorage),
          cacheKey: () => AppCacheService.buildKey(
            endpoint: APIEndPoints.getDashboard,
            queryParameters: {
              if (isLoggedIn.value) 'user_id': loginUserData.value.id,
            },
          ),
          load: () async {
            final response = await (loader ?? HomeServiceApis.getDashboard)();
            if (!response.status) {
              throw StateError(response.message.isEmpty
                  ? '데이터를 불러오지 못했습니다.'
                  : response.message);
            }
            return response;
          },
          decode: (json) {
            if (json['status'] != true || json['data'] is! Map) {
              throw const FormatException('Invalid dashboard cache');
            }
            return DashboardRes.fromJson(json);
          },
          encode: (response) => response.toJson(),
          ttl: AppCacheService.dashboardTtl,
          maxStale: AppCacheService.dashboardMaxStale,
        );

  Worker? _scopeWorker;
  late PackageInfoData info;
  TextEditingController searchCont = TextEditingController();
  Rx<DashboardData> dashboardData = DashboardData().obs;
  PageController pageController = PageController();
  RxInt currentPage = 0.obs;

  ///Slider
  PageController sliderPageController = PageController();
  RxInt sliderCurrentPage = 0.obs;

  @override
  void onInit() {
    super.onInit();
    _scopeWorker = everAll(
      [isLoggedIn, loginUserData, selectedLanguageCode],
      (_) => getDashboardDetail(),
    );
    init();
  }

  Future<void> init() async {
    getDashboardDetail();
    info = await getPackageInfo();
    // _checkAndShowDialog(getContext, getValueFromLocal(AutoUpdateConst.isAutoUpdateOn) ?? false);
  }

  ///Get ChooseService List
  Future<void> getDashboardDetail({bool isFromSwipeRefresh = false}) async {
    unawaited(getAppConfigurations());
    await refresh();
  }

  @override
  void onData(DashboardRes? data) {
    handleDashboardRes(data ?? DashboardRes(data: DashboardData()));
  }

  void handleDashboardRes(DashboardRes value) {
    dashboardData(value.data);
    unreadNotificationCount(value.data.unReadCount);
  }

  @override
  void onClose() {
    _scopeWorker?.dispose();
    searchCont.dispose();
    pageController.dispose();
    sliderPageController.dispose();
    super.onClose();
  }

/*  Future<void> _checkAndShowDialog(BuildContext context, bool isAutoUpdateOn) async {
    if (!isAutoUpdateOn) {
      debugPrint('Update dialog suppressed by flag.');
      return;
    }

    final result = await PlayxVersionUpdate.showUpdateDialog(
      context: context,
      options: PlayxUpdateOptions(
        androidPackageName: 'com.celebhospital.patient',
        iosBundleId: 'com.Innoquad Technologies LLP.id6743613222',
        minVersion: info.versionName,
        forceUpdate: true,
      ),
      uiOptions: PlayxUpdateUIOptions(
        displayType: PlayxUpdateDisplayType.dialog,
        title: (info) => '${locale.value.updateTo} v${info.newVersion} ${locale.value.available}',
        titleTextStyle: primaryTextStyle(),
        description: (info) => locale.value.aVertionUpdateIsAvailable,
        descriptionTextStyle: secondaryTextStyle(),
        updateButtonText: locale.value.updateNow,
        dismissButtonText: locale.value.later,
        showReleaseNotes: true,
        releaseNotesTitleTextStyle: primaryTextStyle(),
        releaseNotesTextStyle: secondaryTextStyle(),
      ),
    );

    result.when(
      success: (isShown) {
        debugPrint(isShown ? 'Update prompt displayed' : 'No update needed or user chose later.');
      },
      error: (error) {
        debugPrint('Version check failed: ${error.message}');
      },
    );
  }*/
}

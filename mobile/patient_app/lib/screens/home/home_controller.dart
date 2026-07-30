import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/home_apis.dart';
import '../../utils/app_common.dart';
import '../dashboard/dashboard_controller.dart';
import 'model/dashboard_res_model.dart';

class HomeController extends GetxController {
  RxBool isLoading = false.obs;
  RxBool isRefresh = false.obs;
  late PackageInfoData info;
  TextEditingController searchCont = TextEditingController();
  Rx<Future<DashboardRes>> getDashboardDetailFuture = Future(() => DashboardRes(data: DashboardData())).obs;
  Rx<DashboardData> dashboardData = DashboardData().obs;
  PageController pageController = PageController();
  RxInt currentPage = 0.obs;

  ///Slider
  PageController sliderPageController = PageController();
  RxInt sliderCurrentPage = 0.obs;

  @override
  void onReady() {
    init();
    super.onReady();
  }

  Future<void> init() async {
    getDashboardDetail();
    info = await getPackageInfo();
    // _checkAndShowDialog(getContext, getValueFromLocal(AutoUpdateConst.isAutoUpdateOn) ?? false);
  }

  ///Get ChooseService List
  Future<void> getDashboardDetail({bool isFromSwipeRefresh = false}) async {
    if (!isFromSwipeRefresh) {
      isLoading(true);
    }
    getAppConfigurations();
    await getDashboardDetailFuture(
      HomeServiceApis.getDashboard(),
    ).then((value) {
      handleDashboardRes(value);
    }).whenComplete(() => isLoading(false));
  }

  void handleDashboardRes(DashboardRes value) {
    debugPrint('NEARBYCLINIC.LENGTH: ${dashboardData.value.nearByClinic.length}');
    debugPrint('VALUE.DATA: ${value.data.nearByClinic.length}');
    dashboardData(value.data);
    unreadNotificationCount(value.data.unReadCount);
    debugPrint('After NEARBYCLINIC.LENGTH: ${dashboardData.value.nearByClinic.length}');
    //More Logic....
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

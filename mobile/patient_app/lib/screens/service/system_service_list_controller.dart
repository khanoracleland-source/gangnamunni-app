import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';

import '../category/model/category_list_model.dart';
import '../home/model/system_service_res.dart';

class SystemServiceListController extends GetxController {
  Rx<Future<RxList<SystemService>>> systemServiceListFuture = Future(() => RxList<SystemService>()).obs;
  RxBool isLoading = false.obs;
  RxList<SystemService> systemServiceList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;
  Rx<CategoryElement> category = CategoryElement().obs;

  @override
  void onInit() {
    if (Get.arguments is CategoryElement) {
      category(Get.arguments);
      getSystemServiceList();
    }
    super.onInit();
  }

  Future<void> getSystemServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await systemServiceListFuture(
      CoreServiceApis.getSystemService(
        page: page.value,
        systemServiceList: systemServiceList,
        categoryId: category.value.id,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log('SystemServiceList getSystemServiceList err ==> $e');
    }).whenComplete(() => isLoading(false));
  }
}

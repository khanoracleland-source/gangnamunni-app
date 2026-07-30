import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';
import '../category/model/category_list_model.dart';
import '../home/model/system_service_res.dart';
import 'model/service_list_model.dart';

class ServiceListController extends GetxController {
  Rx<Future<RxList<ServiceElement>>> serviceListFuture = Future(() => RxList<ServiceElement>()).obs;
  RxBool isLoading = false.obs;
  RxList<ServiceElement> serviceList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;
  Rx<CategoryElement> category = CategoryElement().obs;
  Rx<ServiceElement> serviceData = ServiceElement().obs;
  Rx<SystemService> systemServiceData = SystemService().obs;
  RxInt clinicId = (-1).obs;
  RxInt categoryId = (-1).obs;
  RxInt isPopular = (-1).obs;
  RxInt selectedFilterCount = 0.obs;
  ///Service Filter
  RxString serviceType = "".obs;
  RxString priceMin = ''.obs;
  RxString priceMax = ''.obs;

  ///Search
  TextEditingController searchCont = TextEditingController();
  RxBool isSearchText = false.obs;
  StreamController<String> searchStream = StreamController<String>();
  final scrollController = ScrollController();

  @override
  void onInit() {

    if (Get.arguments is CategoryElement) {
      category(Get.arguments);
      getServiceList();
    } else if (Get.arguments is SystemService) {
      systemServiceData(Get.arguments);
      getServiceList();
    } else if (Get.arguments is ServiceElement) {
      serviceData(Get.arguments);
      getServiceList();
    }
    else if (Get.arguments is int) {
      clinicId(Get.arguments as int);
      log('clinicId==== $clinicId');
      getServiceList();
    }
    else if(Get.arguments   is Map) {

      getServiceList();
    }
   // getServiceList();
    super.onInit();
  }

  Future<void> getServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await serviceListFuture(
      CoreServiceApis.getServiceList(
        page: page.value,
        serviceList: serviceList,
        categoryId: category.value.id,
        systemServiceId: systemServiceData.value.id,
        isFeatures: serviceData.value.featured,
        clinicId: clinicId.value,
        search: searchCont.text.trim(),
        serviceType: serviceType.value.trim(),
        servicePriceMin: priceMin.value,
        servicePriceMax: priceMax.value,
        isPopulars: isPopular.value,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).catchError((e) {

      isLoading(false);
      log('ServiceList getServiceList err ==> $e');
      return e;
    }).whenComplete(() => isLoading(false));
  }

  @override
  void onClose() {
    searchStream.close();
    page(1);
    searchCont.clear();
    if (Get.context != null) {
      scrollController.removeListener(() => hideKeyboard(Get.context));
    }
    super.onClose();
  }
}
